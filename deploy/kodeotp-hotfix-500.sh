#!/usr/bin/env bash
set -Eeuo pipefail

STACK_DIR=/opt/kodeotp
APP_DIR=/opt/kodeotp/app
DOMAIN=otpku.jagoanproject.com
LOG_FILE=/root/otpku-hotfix-500-v7.log

exec > >(tee -a "$LOG_FILE") 2>&1
trap 'rc=$?; echo "[ERROR] Baris $LINENO gagal, exit=$rc"; echo "[INFO] Log: $LOG_FILE"; exit $rc' ERR

[ "$(id -u)" -eq 0 ] || { echo "Harus dijalankan sebagai root."; exit 1; }
[ -s "$STACK_DIR/.env" ] || { echo "$STACK_DIR/.env tidak ditemukan."; exit 1; }
[ -s "$STACK_DIR/app.env" ] || { echo "$STACK_DIR/app.env tidak ditemukan."; exit 1; }
[ -f "$STACK_DIR/docker-compose.yml" ] || { echo "$STACK_DIR/docker-compose.yml tidak ditemukan."; exit 1; }

exec 9>"$STACK_DIR/.hotfix-500.lock"
flock -w 60 9 || { echo "Hotfix lain sedang berjalan."; exit 1; }

cd "$APP_DIR"
COMPOSE=(docker compose --env-file "$STACK_DIR/.env" -p kodeotp -f "$STACK_DIR/docker-compose.yml")

echo "[INFO] Hotfix runtime dimulai: $(date -Is)"
echo "[INFO] Tidak menjalankan docker build, install ulang, atau menghapus volume."

"${COMPOSE[@]}" up -d --no-build postgres redis >/dev/null

wait_healthy() {
    local service="$1" max_wait="${2:-180}" cid status elapsed=0
    cid="$("${COMPOSE[@]}" ps -q "$service")"
    [ -n "$cid" ] || { echo "Container $service tidak ditemukan."; exit 1; }

    while [ "$elapsed" -lt "$max_wait" ]; do
        status="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$cid" 2>/dev/null || true)"
        case "$status" in
            healthy|running)
                echo "[OK] $service siap ($status)."
                return 0
                ;;
            exited|dead|unhealthy)
                echo "[ERROR] $service berstatus $status."
                docker logs --tail 150 "$cid" || true
                exit 1
                ;;
        esac
        sleep 3
        elapsed=$((elapsed + 3))
    done

    echo "[ERROR] Timeout menunggu $service."
    docker logs --tail 150 "$cid" || true
    exit 1
}

wait_healthy postgres 180
wait_healthy redis 120

ACTIVE="$(cat "$STACK_DIR/.active_color" 2>/dev/null || echo blue)"
case "$ACTIVE" in
    blue) ACTIVE_SERVICE=app_blue ;;
    green) ACTIVE_SERVICE=app_green ;;
    *) ACTIVE=blue; ACTIVE_SERVICE=app_blue ;;
esac

ACTIVE_CID="$("${COMPOSE[@]}" ps -q "$ACTIVE_SERVICE")"
if [ -z "$ACTIVE_CID" ]; then
    echo "[ERROR] Container aktif $ACTIVE_SERVICE tidak ditemukan."
    "${COMPOSE[@]}" ps || true
    exit 1
fi

echo "[INFO] Slot aktif: $ACTIVE_SERVICE"
echo "[INFO] Container: $ACTIVE_CID"

fix_container() {
    local service="$1" cid
    cid="$("${COMPOSE[@]}" ps -q "$service" 2>/dev/null || true)"
    [ -n "$cid" ] || return 0

    state="$(docker inspect -f '{{.State.Status}}' "$cid" 2>/dev/null || true)"
    [ "$state" = running ] || return 0

    echo "[INFO] Memeriksa aset dan cache pada $service."

    docker exec -u 0 "$cid" sh -lc '
        set -eu
        cd /var/www/html

        rm -f public/hot

        if [ ! -s public/build/manifest.json ]; then
            if [ -s public/build/.vite/manifest.json ]; then
                echo "[FIX] Menyalin public/build/.vite/manifest.json ke public/build/manifest.json"
                cp public/build/.vite/manifest.json public/build/manifest.json
            else
                FOUND="$(find public/build -maxdepth 3 -type f -name manifest.json | head -n 1 || true)"
                if [ -n "$FOUND" ]; then
                    echo "[FIX] Menyalin $FOUND ke public/build/manifest.json"
                    cp "$FOUND" public/build/manifest.json
                fi
            fi
        fi

        echo "[INFO] Isi direktori build:"
        find public/build -maxdepth 3 -type f -printf "%p\n" 2>/dev/null | sort | head -n 80 || true

        if [ ! -s public/build/manifest.json ]; then
            echo "[ERROR] Manifest Laravel Vite tetap tidak ditemukan."
            exit 1
        fi

        mkdir -p \
            storage/app/public \
            storage/app/backups \
            storage/framework/cache \
            storage/framework/sessions \
            storage/framework/views \
            storage/logs \
            bootstrap/cache

        chown -R www-data:www-data storage bootstrap/cache
        chmod -R ug+rwX storage bootstrap/cache

        php artisan optimize:clear
        php artisan config:cache
        php artisan route:cache
        php artisan view:cache

        php artisan migrate:status --no-ansi | tail -n 30 || true
    '
}

fix_container app_blue
fix_container app_green

echo "[INFO] Restart ringan container aktif agar PHP opcache bersih."
docker restart "$ACTIVE_CID" >/dev/null
wait_healthy "$ACTIVE_SERVICE" 180

# Pastikan gateway internal tetap hidup.
"${COMPOSE[@]}" up -d --no-build caddy >/dev/null
CADDY_CID="$("${COMPOSE[@]}" ps -q caddy)"
[ -n "$CADDY_CID" ] || { echo "[ERROR] Gateway Caddy internal tidak ditemukan."; exit 1; }

LOGIN_BODY=/tmp/otpku-login-v7.html
LOGIN_HEADERS=/tmp/otpku-login-v7.headers
LOCAL_STATUS=000

for _ in $(seq 1 40); do
    LOCAL_STATUS="$(curl -sS \
        -D "$LOGIN_HEADERS" \
        -o "$LOGIN_BODY" \
        -H "Host: $DOMAIN" \
        -w "%{http_code}" \
        http://127.0.0.1:3280/login || true)"

    [ "$LOCAL_STATUS" = 200 ] && break
    sleep 2
done

echo "[INFO] Status lokal /login: $LOCAL_STATUS"

if [ "$LOCAL_STATUS" != 200 ]; then
    echo "========== RESPONSE HEADER =========="
    cat "$LOGIN_HEADERS" 2>/dev/null || true
    echo "========== RESPONSE BODY =========="
    head -c 4000 "$LOGIN_BODY" 2>/dev/null || true
    echo
    echo "========== CONTAINER LOG =========="
    docker logs --tail 250 "$ACTIVE_CID" || true
    echo "========== LARAVEL DAILY LOG =========="
    docker exec "$ACTIVE_CID" sh -lc '
        cd /var/www/html
        for f in $(find storage/logs -maxdepth 1 -type f -name "*.log" | sort); do
            echo "----- $f -----"
            tail -n 180 "$f" || true
        done
    ' || true
    exit 1
fi

PUBLIC_STATUS=000
for _ in $(seq 1 40); do
    PUBLIC_STATUS="$(curl -ksS -o /tmp/otpku-public-login.html -w "%{http_code}" "https://$DOMAIN/login" || true)"
    [ "$PUBLIC_STATUS" = 200 ] && break
    sleep 3
done

echo "[INFO] Status publik /login: $PUBLIC_STATUS"
[ "$PUBLIC_STATUS" = 200 ] || {
    echo "[ERROR] Login lokal sudah 200 tetapi akses publik masih $PUBLIC_STATUS."
    exit 1
}

# Pastikan akun admin sesuai nilai default yang telah ditentukan pada deployment.
docker exec "$ACTIVE_CID" php artisan db:seed --force >/dev/null
docker exec "$ACTIVE_CID" php artisan optimize:clear >/dev/null
docker exec "$ACTIVE_CID" php artisan config:cache >/dev/null
docker exec "$ACTIVE_CID" php artisan route:cache >/dev/null
docker exec "$ACTIVE_CID" php artisan view:cache >/dev/null

echo "[OK] HOTFIX SELESAI TANPA BUILD ULANG."
echo "WEBSITE=https://$DOMAIN"
echo "LOGIN=https://$DOMAIN/login"
echo "ADMIN=https://$DOMAIN/admin"
echo "LOG=$LOG_FILE"