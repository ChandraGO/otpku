#!/usr/bin/env bash
set -Eeuo pipefail

STACK_DIR=/opt/kodeotp
APP_DIR=/opt/kodeotp/app
DOMAIN=otpku.jagoanproject.com
LOG_FILE=/root/otpku-price-parser-v10.log

exec > >(tee -a "$LOG_FILE") 2>&1
trap 'rc=$?; echo "[ERROR] Baris $LINENO gagal, exit=$rc"; echo "[INFO] Log: $LOG_FILE"; exit $rc' ERR

[ "$(id -u)" -eq 0 ] || { echo "Harus dijalankan sebagai root."; exit 1; }
[ -s "$STACK_DIR/.env" ] || { echo "$STACK_DIR/.env tidak ditemukan."; exit 1; }
[ -s "$STACK_DIR/app.env" ] || { echo "$STACK_DIR/app.env tidak ditemukan."; exit 1; }
[ -f "$STACK_DIR/docker-compose.yml" ] || { echo "$STACK_DIR/docker-compose.yml tidak ditemukan."; exit 1; }
[ -f "$APP_DIR/app/Services/CatalogSyncService.php" ] || {
    echo "CatalogSyncService.php tidak ditemukan."
    exit 1
}

exec 9>"$STACK_DIR/.price-parser-v10.lock"
flock -w 60 9 || {
    echo "Sinkronisasi harga lain sedang berjalan."
    exit 1
}

cd "$APP_DIR"
COMPOSE=(
    docker compose
    --env-file "$STACK_DIR/.env"
    -p kodeotp
    -f "$STACK_DIR/docker-compose.yml"
)

echo "[INFO] Memulai parser harga v10: $(date -Is)"
echo "[INFO] Tidak menjalankan docker build, npm install, composer install, migrasi, atau penghapusan volume."

"${COMPOSE[@]}" up -d --no-build postgres redis >/dev/null

wait_service() {
    local service="$1"
    local max_wait="${2:-180}"
    local cid status elapsed=0

    cid="$("${COMPOSE[@]}" ps -q "$service")"
    [ -n "$cid" ] || {
        echo "[ERROR] Container $service tidak ditemukan."
        exit 1
    }

    while [ "$elapsed" -lt "$max_wait" ]; do
        status="$(docker inspect -f \
            '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' \
            "$cid" 2>/dev/null || true)"

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
    *) ACTIVE_SERVICE=app_blue ;;
esac

copy_patch() {
    local service="$1"
    local cid state

    cid="$("${COMPOSE[@]}" ps -q "$service" 2>/dev/null || true)"
    [ -n "$cid" ] || return 0

    state="$(docker inspect -f '{{.State.Status}}' "$cid" 2>/dev/null || true)"
    [ "$state" = running ] || return 0

    echo "[INFO] Memasang parser harga v10 ke $service."

    docker cp \
        "$APP_DIR/app/Services/CatalogSyncService.php" \
        "$cid:/var/www/html/app/Services/CatalogSyncService.php"

    docker exec -u 0 "$cid" sh -lc '
        cd /var/www/html
        chown www-data:www-data app/Services/CatalogSyncService.php
        php -l app/Services/CatalogSyncService.php
    '
}

for service in app_blue app_green worker scheduler; do
    copy_patch "$service"
done

echo "[INFO] Restart ringan container PHP untuk memuat class baru."

for service in "$ACTIVE_SERVICE" worker scheduler; do
    cid="$("${COMPOSE[@]}" ps -q "$service" 2>/dev/null || true)"
    [ -n "$cid" ] && docker restart "$cid" >/dev/null
done

wait_service "$ACTIVE_SERVICE" 180

ACTIVE_CID="$("${COMPOSE[@]}" ps -q "$ACTIVE_SERVICE")"
[ -n "$ACTIVE_CID" ] || {
    echo "[ERROR] Container aplikasi aktif tidak ditemukan."
    exit 1
}

docker exec "$ACTIVE_CID" sh -lc '
    cd /var/www/html
    php artisan optimize:clear
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
'

cat >/tmp/kodeotp-prices-v10.php <<'PHP'
<?php

require '/var/www/html/vendor/autoload.php';

$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $before = [
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
        'countries_without_prices' => App\Models\SmsCountry::query()
            ->where('is_active', true)
            ->whereDoesntHave(
                'prices',
                fn ($query) => $query->where('is_active', true),
            )
            ->count(),
    ];

    echo 'BEFORE='.json_encode(
        $before,
        JSON_UNESCAPED_SLASHES,
    ).PHP_EOL;

    $result = app(App\Services\CatalogSyncService::class)
        ->syncPricesOnly(true);

    echo 'SYNC_RESULT='.json_encode(
        $result,
        JSON_UNESCAPED_SLASHES,
    ).PHP_EOL;

    $after = [
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
        'countries_without_prices' => App\Models\SmsCountry::query()
            ->where('is_active', true)
            ->whereDoesntHave(
                'prices',
                fn ($query) => $query->where('is_active', true),
            )
            ->count(),
    ];

    echo 'AFTER='.json_encode(
        $after,
        JSON_UNESCAPED_SLASHES,
    ).PHP_EOL;

    $samples = App\Models\SmsServicePrice::query()
        ->with(['country:id,name', 'service:id,name'])
        ->where('is_active', true)
        ->where('stock', '>', 0)
        ->orderBy('sell_price')
        ->limit(5)
        ->get()
        ->map(fn ($price) => [
            'id' => $price->id,
            'provider_price_id' => $price->provider_price_id,
            'country' => $price->country?->name,
            'service' => $price->service?->name,
            'provider_price' => (float) $price->provider_price,
            'sell_price' => (float) $price->sell_price,
            'stock' => $price->stock,
        ])
        ->all();

    echo 'SAMPLES='.json_encode(
        $samples,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
    ).PHP_EOL;

    if ($after['prices'] < 1 || $after['available'] < 1) {
        throw new RuntimeException(
            'Parser v10 selesai tetapi harga aktif masih kosong.',
        );
    }
} catch (Throwable $e) {
    fwrite(STDERR, PHP_EOL.'=== EXCEPTION V10 ==='.PHP_EOL);
    fwrite(STDERR, get_class($e).': '.$e->getMessage().PHP_EOL);
    fwrite(STDERR, $e->getFile().':'.$e->getLine().PHP_EOL);
    fwrite(STDERR, $e->getTraceAsString().PHP_EOL);
    exit(1);
}
PHP

docker cp \
    /tmp/kodeotp-prices-v10.php \
    "$ACTIVE_CID:/tmp/kodeotp-prices-v10.php"

set +e
docker exec "$ACTIVE_CID" \
    php /tmp/kodeotp-prices-v10.php \
    2>&1 | tee /tmp/kodeotp-prices-v10-output.log
SYNC_RC=${PIPESTATUS[0]}
set -e

if [ "$SYNC_RC" -ne 0 ]; then
    echo "========== API LOG SMS VIRTUAL TERBARU =========="

    docker exec "$ACTIVE_CID" php -r '
        require "/var/www/html/vendor/autoload.php";

        $app = require "/var/www/html/bootstrap/app.php";
        $app->make(
            Illuminate\Contracts\Console\Kernel::class,
        )->bootstrap();

        foreach (
            App\Models\ApiLog::query()
                ->where("provider", "sms_virtual")
                ->latest("id")
                ->limit(15)
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
                JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES,
            ).PHP_EOL;
        }
    ' || true

    exit "$SYNC_RC"
fi

curl -fsS \
    -H "Host: $DOMAIN" \
    http://127.0.0.1:3280/healthz \
    >/dev/null

HOME_STATUS="$(curl -ksS \
    -o /tmp/otpku-home-v10.html \
    -w '%{http_code}' \
    "https://$DOMAIN/" || true)"

PRICE_STATUS="$(curl -ksS \
    -o /tmp/otpku-price-v10.html \
    -w '%{http_code}' \
    "https://$DOMAIN/harga" || true)"

SERVICE_STATUS="$(curl -ksS \
    -o /tmp/otpku-services-v10.html \
    -w '%{http_code}' \
    "https://$DOMAIN/layanan" || true)"

echo "[INFO] HTTP home: $HOME_STATUS"
echo "[INFO] HTTP harga: $PRICE_STATUS"
echo "[INFO] HTTP layanan: $SERVICE_STATUS"

[ "$HOME_STATUS" = 200 ] || {
    echo "[ERROR] Home bukan HTTP 200."
    exit 1
}

[ "$PRICE_STATUS" = 200 ] || {
    echo "[ERROR] Halaman harga bukan HTTP 200."
    exit 1
}

case "$SERVICE_STATUS" in
    200|302) ;;
    *)
        echo "[ERROR] Halaman layanan memberi HTTP $SERVICE_STATUS."
        exit 1
        ;;
esac

echo "[OK] PARSER HARGA V10 SELESAI TANPA BUILD ULANG."
grep -E '^(BEFORE|SYNC_RESULT|AFTER|SAMPLES)=' \
    /tmp/kodeotp-prices-v10-output.log || true
echo "HOME=https://$DOMAIN"
echo "HARGA=https://$DOMAIN/harga"
echo "LAYANAN=https://$DOMAIN/layanan"
echo "LOG=$LOG_FILE"
