#!/usr/bin/env bash
set -Eeuo pipefail

STACK_DIR=/opt/kodeotp
APP_DIR=/opt/kodeotp/app
DOMAIN=otpku.jagoanproject.com
LOG_FILE=/root/otpku-integration-v8.log

exec > >(tee -a "$LOG_FILE") 2>&1
trap 'rc=$?; echo "[ERROR] Baris $LINENO gagal, exit=$rc"; echo "[INFO] Log: $LOG_FILE"; exit $rc' ERR

[ "$(id -u)" -eq 0 ] || { echo "Harus dijalankan sebagai root."; exit 1; }
[ -s "$STACK_DIR/.env" ] || { echo "$STACK_DIR/.env tidak ditemukan."; exit 1; }
[ -s "$STACK_DIR/app.env" ] || { echo "$STACK_DIR/app.env tidak ditemukan."; exit 1; }
[ -f "$STACK_DIR/docker-compose.yml" ] || { echo "$STACK_DIR/docker-compose.yml tidak ditemukan."; exit 1; }

exec 9>"$STACK_DIR/.integration-v8.lock"
flock -w 60 9 || { echo "Hotfix lain sedang berjalan."; exit 1; }

cd "$APP_DIR"
COMPOSE=(docker compose --env-file "$STACK_DIR/.env" -p kodeotp -f "$STACK_DIR/docker-compose.yml")

echo "[INFO] Memulai integrasi v8: $(date -Is)"
echo "[INFO] Tidak menjalankan docker build, composer install, npm install, atau menghapus volume."

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

ACTIVE_CID="$("${COMPOSE[@]}" ps -q "$ACTIVE_SERVICE")"
[ -n "$ACTIVE_CID" ] || {
    echo "[ERROR] Container aplikasi aktif $ACTIVE_SERVICE tidak ditemukan."
    "${COMPOSE[@]}" ps
    exit 1
}

PATCH_FILES=(
    app/Http/Controllers/Admin/SettingsController.php
    app/Services/SmsVirtualClient.php
    app/Services/CatalogSyncService.php
    resources/views/home.blade.php
    resources/views/layouts/app.blade.php
    resources/views/admin/dashboard.blade.php
    resources/views/admin/settings/index.blade.php
)

copy_patches() {
    local service="$1" cid state rel
    cid="$("${COMPOSE[@]}" ps -q "$service" 2>/dev/null || true)"
    [ -n "$cid" ] || return 0

    state="$(docker inspect -f '{{.State.Status}}' "$cid" 2>/dev/null || true)"
    [ "$state" = running ] || return 0

    echo "[INFO] Memasang patch ke $service."

    for rel in "${PATCH_FILES[@]}"; do
        [ -f "$APP_DIR/$rel" ] || { echo "[ERROR] File source tidak ditemukan: $rel"; exit 1; }
        docker cp "$APP_DIR/$rel" "$cid:/var/www/html/$rel"
    done

    docker exec -u 0 "$cid" sh -lc '
        cd /var/www/html
        chown -R www-data:www-data app resources/views
        mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
        chown -R www-data:www-data storage bootstrap/cache
        chmod -R ug+rwX storage bootstrap/cache
    '
}

for service in app_blue app_green worker scheduler; do
    copy_patches "$service"
done

echo "[INFO] Restart ringan untuk memuat source baru; tidak ada rebuild."
for service in "$ACTIVE_SERVICE" worker scheduler; do
    cid="$("${COMPOSE[@]}" ps -q "$service" 2>/dev/null || true)"
    if [ -n "$cid" ]; then
        docker restart "$cid" >/dev/null
    fi
done

wait_service "$ACTIVE_SERVICE" 180
ACTIVE_CID="$("${COMPOSE[@]}" ps -q "$ACTIVE_SERVICE")"

docker exec "$ACTIVE_CID" sh -lc '
    cd /var/www/html
    php artisan optimize:clear
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
'

echo "[INFO] Menjalankan sinkronisasi katalog secara langsung."
SYNC_OUTPUT="$(
    docker exec "$ACTIVE_CID" php artisan tinker --execute='
        $result = app(\App\Services\CatalogSyncService::class)->sync();
        echo "SYNC_RESULT=".json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
    ' 2>&1
)"
printf '%s\n' "$SYNC_OUTPUT"

COUNTS="$(
    docker exec "$ACTIVE_CID" php artisan tinker --execute='
        echo "COUNTS=".json_encode([
            "countries" => \App\Models\SmsCountry::query()->where("is_active", true)->count(),
            "services" => \App\Models\SmsService::query()->where("is_active", true)->count(),
            "prices" => \App\Models\SmsServicePrice::query()->where("is_active", true)->count(),
            "available" => \App\Models\SmsServicePrice::query()->where("is_active", true)->where("stock", ">", 0)->count(),
            "failed_jobs" => \Illuminate\Support\Facades\DB::table("failed_jobs")->count(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
    ' 2>&1
)"
printf '%s\n' "$COUNTS"

AVAILABLE="$(printf '%s\n' "$COUNTS" | sed -n 's/.*"available":\([0-9][0-9]*\).*/\1/p' | tail -n 1)"
SERVICES="$(printf '%s\n' "$COUNTS" | sed -n 's/.*"services":\([0-9][0-9]*\).*/\1/p' | tail -n 1)"
COUNTRIES="$(printf '%s\n' "$COUNTS" | sed -n 's/.*"countries":\([0-9][0-9]*\).*/\1/p' | tail -n 1)"

if [ "${SERVICES:-0}" -eq 0 ] || [ "${COUNTRIES:-0}" -eq 0 ]; then
    echo "[DIAG] Katalog masih kosong. Menampilkan bentuk respons publik pertama tanpa secret."
    docker exec "$ACTIVE_CID" php artisan tinker --execute='
        $client = app(\App\Services\SmsVirtualClient::class);
        echo "COUNTRIES_SAMPLE=".json_encode($client->countries(["page" => 1, "pageSize" => 2]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
        echo "SERVICES_SAMPLE=".json_encode($client->services(["page" => 1, "pageSize" => 2]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
    ' || true

    echo "[ERROR] Provider dapat dihubungi untuk balance, tetapi respons katalog belum menghasilkan negara/layanan."
    exit 1
fi

curl -fsS -H "Host: $DOMAIN" http://127.0.0.1:3280/healthz >/dev/null

HOME_STATUS="$(curl -ksS -o /tmp/otpku-home-v8.html -w '%{http_code}' "https://$DOMAIN/" || true)"
SETTINGS_STATUS="$(curl -ksS -o /dev/null -w '%{http_code}' "https://$DOMAIN/admin/settings?tab=sms_virtual" || true)"

echo "[INFO] HTTP home: $HOME_STATUS"
echo "[INFO] HTTP settings (redirect/login juga valid): $SETTINGS_STATUS"

[ "$HOME_STATUS" = 200 ] || {
    echo "[ERROR] Halaman home tidak mengembalikan HTTP 200."
    docker logs --tail 200 "$ACTIVE_CID" || true
    exit 1
}

echo "[OK] Integrasi pengaturan diperbaiki tanpa rebuild."
echo "COUNTRIES=${COUNTRIES:-0}"
echo "SERVICES=${SERVICES:-0}"
echo "AVAILABLE=${AVAILABLE:-0}"
echo "WEBSITE=https://$DOMAIN"
echo "LOGIN=https://$DOMAIN/login"
echo "ADMIN=https://$DOMAIN/admin"
echo "LOG=$LOG_FILE"
