#!/usr/bin/env bash
set -Eeuo pipefail

STACK_DIR=/opt/kodeotp
APP_DIR=/opt/kodeotp/app
DOMAIN=otpku.jagoanproject.com
LOG_FILE=/root/otpku-catalog-v9.log

exec > >(tee -a "$LOG_FILE") 2>&1
trap 'rc=$?; echo "[ERROR] Baris $LINENO gagal, exit=$rc"; echo "[INFO] Log: $LOG_FILE"; exit $rc' ERR

[ "$(id -u)" -eq 0 ] || { echo "Harus dijalankan sebagai root."; exit 1; }
[ -s "$STACK_DIR/.env" ] || { echo "$STACK_DIR/.env tidak ditemukan."; exit 1; }
[ -s "$STACK_DIR/app.env" ] || { echo "$STACK_DIR/app.env tidak ditemukan."; exit 1; }
[ -f "$STACK_DIR/docker-compose.yml" ] || { echo "$STACK_DIR/docker-compose.yml tidak ditemukan."; exit 1; }

exec 9>"$STACK_DIR/.catalog-v9.lock"
flock -w 60 9 || { echo "Sinkronisasi lain sedang berjalan."; exit 1; }

cd "$APP_DIR"
COMPOSE=(docker compose --env-file "$STACK_DIR/.env" -p kodeotp -f "$STACK_DIR/docker-compose.yml")

echo "[INFO] Memulai perbaikan katalog v9: $(date -Is)"
echo "[INFO] Tanpa docker build, npm install, composer install, migrasi ulang, atau penghapusan volume."

"${COMPOSE[@]}" up -d --no-build postgres redis >/dev/null

wait_service() {
    local service="$1" max_wait="${2:-180}" cid status elapsed=0
    cid="$("${COMPOSE[@]}" ps -q "$service")"
    [ -n "$cid" ] || { echo "[ERROR] Container $service tidak ditemukan."; exit 1; }

    while [ "$elapsed" -lt "$max_wait" ]; do
        status="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$cid" 2>/dev/null || true)"
        case "$status" in
            healthy|running)
                echo "[OK] $service siap ($status)."
                return 0
                ;;
            unhealthy|exited|dead)
                echo "[ERROR] $service berstatus $status."
                docker logs --tail 160 "$cid" || true
                exit 1
                ;;
        esac
        sleep 3
        elapsed=$((elapsed + 3))
    done

    echo "[ERROR] Timeout menunggu $service."
    docker logs --tail 160 "$cid" || true
    exit 1
}

wait_service postgres 180
wait_service redis 120

ACTIVE="$(cat "$STACK_DIR/.active_color" 2>/dev/null || echo blue)"
case "$ACTIVE" in
    blue) ACTIVE_SERVICE=app_blue ;;
    green) ACTIVE_SERVICE=app_green ;;
    *) ACTIVE=blue; ACTIVE_SERVICE=app_blue ;;
esac

PATCH_FILES=(
    app/Services/SmsVirtualClient.php
    app/Services/CatalogSyncService.php
)

copy_patch() {
    local service="$1" cid state rel
    cid="$("${COMPOSE[@]}" ps -q "$service" 2>/dev/null || true)"
    [ -n "$cid" ] || return 0

    state="$(docker inspect -f '{{.State.Status}}' "$cid" 2>/dev/null || true)"
    [ "$state" = running ] || return 0

    echo "[INFO] Memasang patch katalog ke $service."

    for rel in "${PATCH_FILES[@]}"; do
        [ -f "$APP_DIR/$rel" ] || { echo "[ERROR] Source tidak ditemukan: $rel"; exit 1; }
        docker cp "$APP_DIR/$rel" "$cid:/var/www/html/$rel"
    done

    docker exec -u 0 "$cid" sh -lc '
        cd /var/www/html
        chown -R www-data:www-data app/Services
        php -l app/Services/SmsVirtualClient.php
        php -l app/Services/CatalogSyncService.php
    '
}

for service in app_blue app_green worker scheduler; do
    copy_patch "$service"
done

echo "[INFO] Restart ringan hanya untuk memuat class baru."
for service in "$ACTIVE_SERVICE" worker scheduler; do
    cid="$("${COMPOSE[@]}" ps -q "$service" 2>/dev/null || true)"
    [ -n "$cid" ] && docker restart "$cid" >/dev/null
done

wait_service "$ACTIVE_SERVICE" 180
ACTIVE_CID="$("${COMPOSE[@]}" ps -q "$ACTIVE_SERVICE")"
[ -n "$ACTIVE_CID" ] || { echo "[ERROR] Container aktif tidak ditemukan."; exit 1; }

docker exec "$ACTIVE_CID" sh -lc '
    cd /var/www/html
    php artisan optimize:clear
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
'

cat >/tmp/kodeotp-sync-v9.php <<'PHP'
<?php

require '/var/www/html/vendor/autoload.php';

$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

function short_json(mixed $value, int $limit = 3500): string
{
    $json = json_encode(
        $value,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
    );

    if (! is_string($json)) {
        return '[gagal encode JSON]';
    }

    return mb_substr($json, 0, $limit);
}

try {
    $client = app(App\Services\SmsVirtualClient::class);

    echo "=== TES ENDPOINT KATALOG ===\n";
    $countrySample = $client->countries([
        'page' => 1,
        'pageSize' => 2,
    ]);
    echo "COUNTRIES_SAMPLE=".short_json($countrySample)."\n";

    $serviceSample = $client->services([
        'page' => 1,
        'pageSize' => 2,
    ]);
    echo "SERVICES_SAMPLE=".short_json($serviceSample)."\n";

    echo "=== SINKRONISASI PENUH ===\n";
    $result = app(App\Services\CatalogSyncService::class)->sync();
    echo "SYNC_RESULT=".short_json($result)."\n";

    $counts = [
        'countries' => App\Models\SmsCountry::query()
            ->where('is_active', true)
            ->count(),
        'services' => App\Models\SmsService::query()
            ->where('is_active', true)
            ->count(),
        'prices' => App\Models\SmsServicePrice::query()
            ->where('is_active', true)
            ->count(),
        'available' => App\Models\SmsServicePrice::query()
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->count(),
    ];

    echo "COUNTS=".json_encode($counts, JSON_UNESCAPED_SLASHES)."\n";

    if ($counts['countries'] < 1 || $counts['services'] < 1) {
        throw new RuntimeException(
            'Sinkronisasi selesai tetapi negara atau layanan masih kosong.',
        );
    }

    if ($counts['prices'] < 1) {
        $country = App\Models\SmsCountry::query()
            ->where('is_active', true)
            ->first();

        if ($country) {
            $priceSample = $client->servicesByCountry(
                $country->provider_id,
                ['page' => 1, 'pageSize' => 2],
            );

            echo "PRICE_SAMPLE=".short_json($priceSample, 6000)."\n";
        }

        throw new RuntimeException(
            'Negara dan layanan masuk, tetapi tabel harga masih kosong. '.
            'Contoh respons harga sudah dicetak di atas.',
        );
    }
} catch (Throwable $e) {
    fwrite(STDERR, "\n=== EXCEPTION LENGKAP ===\n");
    fwrite(STDERR, get_class($e).": ".$e->getMessage()."\n");
    fwrite(STDERR, $e->getFile().":".$e->getLine()."\n");
    fwrite(STDERR, $e->getTraceAsString()."\n");
    exit(1);
}
PHP

docker cp /tmp/kodeotp-sync-v9.php "$ACTIVE_CID:/tmp/kodeotp-sync-v9.php"

set +e
docker exec "$ACTIVE_CID" php /tmp/kodeotp-sync-v9.php 2>&1 | tee /tmp/kodeotp-sync-v9-output.log
SYNC_RC=${PIPESTATUS[0]}
set -e

if [ "$SYNC_RC" -ne 0 ]; then
    echo "========== API LOG TERBARU =========="
    docker exec "$ACTIVE_CID" php -r '
        require "/var/www/html/vendor/autoload.php";
        $app = require "/var/www/html/bootstrap/app.php";
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        foreach (
            App\Models\ApiLog::query()
                ->where("provider", "sms_virtual")
                ->latest("id")
                ->limit(12)
                ->get([
                    "method",
                    "endpoint",
                    "status_code",
                    "successful",
                    "error_code",
                    "error_message",
                    "request_meta",
                    "created_at",
                ]) as $row
        ) {
            echo json_encode(
                $row->toArray(),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ).PHP_EOL;
        }
    ' || true

    echo "[ERROR] Sinkronisasi katalog masih gagal. Exception lengkap sudah tampil di log."
    exit "$SYNC_RC"
fi

COUNTS_LINE="$(grep '^COUNTS=' /tmp/kodeotp-sync-v9-output.log | tail -n 1)"
echo "[INFO] $COUNTS_LINE"

curl -fsS -H "Host: $DOMAIN" http://127.0.0.1:3280/healthz >/dev/null
HOME_STATUS="$(curl -ksS -o /tmp/otpku-home-v9.html -w '%{http_code}' "https://$DOMAIN/" || true)"
PRICE_STATUS="$(curl -ksS -o /tmp/otpku-price-v9.html -w '%{http_code}' "https://$DOMAIN/harga" || true)"

echo "[INFO] HTTP home: $HOME_STATUS"
echo "[INFO] HTTP harga: $PRICE_STATUS"

[ "$HOME_STATUS" = 200 ] || { echo "[ERROR] Home bukan HTTP 200."; exit 1; }
[ "$PRICE_STATUS" = 200 ] || { echo "[ERROR] Halaman harga bukan HTTP 200."; exit 1; }

echo "[OK] Sinkronisasi katalog v9 selesai tanpa rebuild."
echo "$COUNTS_LINE"
echo "HOME=https://$DOMAIN"
echo "HARGA=https://$DOMAIN/harga"
echo "LAYANAN=https://$DOMAIN/layanan"
echo "LOG=$LOG_FILE"
