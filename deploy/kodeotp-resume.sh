#!/usr/bin/env bash
set -Eeuo pipefail

STACK_DIR=/opt/kodeotp
APP_DIR=/opt/kodeotp/app
DOMAIN=otpku.jagoanproject.com
LOG_FILE=/root/otpku-resume-v6.log

exec > >(tee -a "$LOG_FILE") 2>&1
trap 'rc=$?; echo "[ERROR] Baris $LINENO gagal, exit=$rc"; echo "[INFO] Log: $LOG_FILE"; exit $rc' ERR

[ "$(id -u)" -eq 0 ] || { echo 'Harus dijalankan sebagai root.'; exit 1; }
: "${ADMIN_EMAIL:?ADMIN_EMAIL tidak tersedia}"
: "${ADMIN_WHATSAPP:?ADMIN_WHATSAPP tidak tersedia}"
: "${ADMIN_PASSWORD:?ADMIN_PASSWORD tidak tersedia}"

for cmd in docker git curl awk flock; do
    command -v "$cmd" >/dev/null || { echo "$cmd tidak tersedia."; exit 1; }
done
docker compose version >/dev/null

[ -d "$APP_DIR/.git" ] || { echo "$APP_DIR belum berisi repository Git."; exit 1; }
[ -s "$STACK_DIR/.env" ] || { echo "$STACK_DIR/.env tidak ditemukan. Resume-only tidak akan menginstal ulang."; exit 1; }
[ -s "$STACK_DIR/app.env" ] || { echo "$STACK_DIR/app.env tidak ditemukan. Resume-only tidak akan menginstal ulang."; exit 1; }

mkdir -p "$STACK_DIR/caddy"
exec 9>"$STACK_DIR/.resume.lock"
flock -w 60 9 || { echo 'Proses resume lain masih berjalan.'; exit 1; }

echo "[INFO] Resume dimulai: $(date -Is)"
cd "$APP_DIR"

# Pastikan file runtime tersedia, tanpa menjalankan install atau build.
[ -f "$STACK_DIR/docker-compose.yml" ] || install -m 0644 deploy/docker-compose.yml "$STACK_DIR/docker-compose.yml"
[ -f "$STACK_DIR/Caddyfile" ] || install -m 0644 deploy/Caddyfile "$STACK_DIR/Caddyfile"

set_env_value() {
    local file="$1" key="$2" value="$3" tmp
    tmp="$(mktemp)"
    awk -v k="$key" -v v="$value" '
        BEGIN { found=0 }
        index($0, k "=") == 1 { print k "=" v; found=1; next }
        { print }
        END { if (!found) print k "=" v }
    ' "$file" > "$tmp"
    cat "$tmp" > "$file"
    rm -f "$tmp"
}

# Username admin memakai email seperti permintaan pengguna.
set_env_value "$STACK_DIR/app.env" ADMIN_USERNAME "$ADMIN_EMAIL"
set_env_value "$STACK_DIR/app.env" ADMIN_EMAIL "$ADMIN_EMAIL"
set_env_value "$STACK_DIR/app.env" ADMIN_WHATSAPP "$ADMIN_WHATSAPP"
set_env_value "$STACK_DIR/app.env" ADMIN_PASSWORD "$ADMIN_PASSWORD"
chmod 600 "$STACK_DIR/.env" "$STACK_DIR/app.env"

# Gunakan image yang SUDAH selesai dibangun. Dilarang docker build pada resume ini.
IMAGE="$(docker image ls --filter 'reference=kodeotp-app:*' --format '{{.Repository}}:{{.Tag}}' \
    | grep -E '^kodeotp-app:[0-9a-f]{12}$' \
    | head -n 1 || true)"
[ -n "$IMAGE" ] || {
    echo 'Image kodeotp-app hasil build sebelumnya tidak ditemukan.'
    echo 'Resume dihentikan agar tidak melakukan build ulang dan tidak menaikkan RAM.'
    exit 1
}

echo "[INFO] Menggunakan image lama: $IMAGE"
export KODEOTP_IMAGE="$IMAGE"
COMPOSE=(docker compose --env-file "$STACK_DIR/.env" -p kodeotp -f "$STACK_DIR/docker-compose.yml")

# Hanya menyalakan database yang sudah dibuat, tanpa menghapus volume.
"${COMPOSE[@]}" up -d --no-build postgres redis

wait_healthy() {
    local service="$1" max_wait="${2:-180}" cid status elapsed=0
    cid="$("${COMPOSE[@]}" ps -q "$service")"
    [ -n "$cid" ] || { echo "Container $service tidak ditemukan."; exit 1; }

    while [ "$elapsed" -lt "$max_wait" ]; do
        status="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$cid" 2>/dev/null || true)"
        if [ "$status" = healthy ]; then
            echo "[OK] $service sehat."
            return 0
        fi
        if [ "$status" = exited ] || [ "$status" = dead ] || [ "$status" = unhealthy ]; then
            echo "[ERROR] $service berstatus $status"
            docker logs --tail 150 "$cid" || true
            exit 1
        fi
        sleep 3
        elapsed=$((elapsed + 3))
    done

    echo "[ERROR] Timeout menunggu $service sehat."
    docker logs --tail 150 "$cid" || true
    exit 1
}

# Perbaikan utama: tunggu PostgreSQL dan Redis benar-benar sehat sebelum migrasi.
wait_healthy postgres 240
wait_healthy redis 120

# Jalankan hanya migrasi dan seeder dari image yang sudah ada.
docker run --rm \
    --network kodeotp_backend \
    --env-file "$STACK_DIR/app.env" \
    "$IMAGE" php artisan migrate --force

docker run --rm \
    --network kodeotp_backend \
    --env-file "$STACK_DIR/app.env" \
    "$IMAGE" php artisan db:seed --force

touch "$STACK_DIR/.seeded"

# Resume satu slot saja agar penggunaan RAM rendah.
TARGET=blue
TARGET_SERVICE=app_blue
OLD_SERVICE=app_green

cat > "$STACK_DIR/caddy/upstream.caddy" <<'EOF'
reverse_proxy app_blue:8080 {
  header_up Host {http.request.host}
  header_up X-Forwarded-Host {http.request.host}
  header_up X-Forwarded-Proto {http.request.header.X-Forwarded-Proto}
  health_uri /healthz
}
EOF

"${COMPOSE[@]}" up -d --no-deps --no-build --force-recreate "$TARGET_SERVICE"
wait_healthy "$TARGET_SERVICE" 240

"${COMPOSE[@]}" up -d --no-deps --no-build caddy
"${COMPOSE[@]}" exec -T caddy caddy reload --config /etc/caddy/Caddyfile --adapter caddyfile || true

# Worker dan scheduler memakai image yang sama, tanpa build.
"${COMPOSE[@]}" up -d --no-deps --no-build --force-recreate worker scheduler
if "${COMPOSE[@]}" ps -q "$OLD_SERVICE" | grep -q .; then
    "${COMPOSE[@]}" stop "$OLD_SERVICE" || true
fi

printf '%s\n' "$TARGET" > "$STACK_DIR/.active_color"
docker tag "$IMAGE" kodeotp-app:latest

LOCAL_OK=0
for _ in $(seq 1 60); do
    if curl -fsS http://127.0.0.1:3280/healthz 2>/dev/null | grep -q '"service":"kodeotp"'; then
        LOCAL_OK=1
        break
    fi
    sleep 2
done
[ "$LOCAL_OK" -eq 1 ] || {
    echo '[ERROR] Health check lokal gagal.'
    "${COMPOSE[@]}" ps || true
    exit 1
}
echo '[OK] KodeOTP lokal sehat di 127.0.0.1:3280.'

configure_host_caddy() {
    local config=/etc/caddy/Caddyfile
    [ -f "$config" ] || return 1
    command -v caddy >/dev/null || return 1
    systemctl is-active --quiet caddy || return 1

    if ! grep -Fq "$DOMAIN {" "$config"; then
        cp "$config" "$config.backup-$(date +%Y%m%d-%H%M%S)"
        cat >> "$config" <<EOF

$DOMAIN {
    encode zstd gzip
    reverse_proxy 127.0.0.1:3280
}
EOF
    fi
    caddy validate --config "$config" --adapter caddyfile
    systemctl reload caddy
    echo '[OK] Caddy host dikonfigurasi.'
    return 0
}

find_public_caddy_container() {
    local cid image name ports mode
    while read -r cid; do
        [ -n "$cid" ] || continue
        image="$(docker inspect -f '{{.Config.Image}}' "$cid" 2>/dev/null || true)"
        name="$(docker inspect -f '{{.Name}}' "$cid" 2>/dev/null | sed 's#^/##')"
        mode="$(docker inspect -f '{{.HostConfig.NetworkMode}}' "$cid" 2>/dev/null || true)"
        printf '%s %s' "$image" "$name" | grep -qi caddy || continue
        ports="$(docker port "$cid" 2>/dev/null || true)"
        if printf '%s\n' "$ports" | grep -Eq '(80/tcp|443/tcp) -> .*:(80|443)$'; then
            printf '%s' "$cid"
            return 0
        fi
        if [ "$mode" = host ]; then
            printf '%s' "$cid"
            return 0
        fi
    done < <(docker ps -q)
    return 1
}

configure_docker_caddy() {
    local public_caddy caddyfile_source caddydir_source network_mode gateway_id gateway_name upstream
    public_caddy="$(find_public_caddy_container || true)"
    [ -n "$public_caddy" ] || return 1

    caddyfile_source="$(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/etc/caddy/Caddyfile"}}{{.Source}}{{end}}{{end}}' "$public_caddy" 2>/dev/null || true)"
    if [ -z "$caddyfile_source" ]; then
        caddydir_source="$(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/etc/caddy"}}{{.Source}}{{end}}{{end}}' "$public_caddy" 2>/dev/null || true)"
        if [ -n "$caddydir_source" ] && [ -f "$caddydir_source/Caddyfile" ]; then
            caddyfile_source="$caddydir_source/Caddyfile"
        fi
    fi
    [ -n "$caddyfile_source" ] && [ -f "$caddyfile_source" ] || return 1

    network_mode="$(docker inspect -f '{{.HostConfig.NetworkMode}}' "$public_caddy")"
    if [ "$network_mode" = host ]; then
        upstream='127.0.0.1:3280'
    else
        docker network inspect kodeotp_frontend >/dev/null
        gateway_id="$("${COMPOSE[@]}" ps -q caddy)"
        [ -n "$gateway_id" ] || return 1
        gateway_name="$(docker inspect -f '{{.Name}}' "$gateway_id" | sed 's#^/##')"
        if ! docker inspect -f '{{json .NetworkSettings.Networks}}' "$public_caddy" | grep -q kodeotp_frontend; then
            docker network connect kodeotp_frontend "$public_caddy"
        fi
        upstream="${gateway_name}:80"
    fi

    if ! grep -Fq "$DOMAIN {" "$caddyfile_source"; then
        cp "$caddyfile_source" "$caddyfile_source.backup-$(date +%Y%m%d-%H%M%S)"
        cat >> "$caddyfile_source" <<EOF

$DOMAIN {
    encode zstd gzip
    reverse_proxy $upstream
}
EOF
    fi

    docker exec "$public_caddy" caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile
    docker exec "$public_caddy" caddy reload --config /etc/caddy/Caddyfile --adapter caddyfile \
        || docker restart "$public_caddy" >/dev/null
    echo "[OK] Caddy Docker diarahkan ke $upstream."
    return 0
}

if configure_host_caddy; then
    :
elif configure_docker_caddy; then
    :
else
    echo '[ERROR] Caddy publik tidak dapat dikonfigurasi otomatis.'
    docker ps --format 'table {{.Names}}\t{{.Image}}\t{{.Ports}}'
    exit 1
fi

PUBLIC_OK=0
for _ in $(seq 1 80); do
    if curl -fsS "https://$DOMAIN/healthz" 2>/dev/null | grep -q '"service":"kodeotp"'; then
        PUBLIC_OK=1
        break
    fi
    sleep 3
done
[ "$PUBLIC_OK" -eq 1 ] || {
    echo '[ERROR] Aplikasi lokal sehat, tetapi HTTPS publik belum sehat.'
    getent hosts "$DOMAIN" || true
    exit 1
}

echo '[OK] RESUME SELESAI TANPA BUILD ULANG.'
echo "WEBSITE=https://$DOMAIN"
echo "LOGIN=https://$DOMAIN/login"
echo "ADMIN=https://$DOMAIN/admin"
echo "ADMIN_EMAIL=$ADMIN_EMAIL"
echo "LOG=$LOG_FILE"