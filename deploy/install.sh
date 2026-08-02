#!/usr/bin/env bash
set -Eeuo pipefail
[ "$(id -u)" -eq 0 ] || { echo 'Jalankan sebagai root.'; exit 1; }
command -v docker >/dev/null || { echo 'Docker belum terpasang.'; exit 1; }
docker compose version >/dev/null || { echo 'Docker Compose plugin belum terpasang.'; exit 1; }
command -v openssl >/dev/null || { echo 'openssl belum terpasang.'; exit 1; }
command -v flock >/dev/null || { echo 'flock (util-linux) belum terpasang.'; exit 1; }
command -v ss >/dev/null || { echo 'ss (iproute2) belum terpasang.'; exit 1; }

REPO_DIR="${1:-/opt/kodeotp/app}"
STACK_DIR="${STACK_DIR:-/opt/kodeotp}"
DOMAIN="${KODEOTP_DOMAIN:-otp.example.com}"
GATEWAY_HOST="${KODEOTP_GATEWAY_HOST:-127.0.0.1}"
GATEWAY_PORT="${KODEOTP_GATEWAY_PORT:-3280}"
[[ "$GATEWAY_PORT" =~ ^[0-9]+$ ]] && [ "$GATEWAY_PORT" -ge 1024 ] && [ "$GATEWAY_PORT" -le 65535 ] || { echo 'Port gateway harus 1024-65535.'; exit 1; }
if ss -ltnH 2>/dev/null | awk -v p=":$GATEWAY_PORT" '$4 ~ p"$"{f=1} END{exit !f}'; then
  echo "Port $GATEWAY_PORT sudah dipakai. Pilih KODEOTP_GATEWAY_PORT lain."; exit 1
fi

POSTGRES_DB="${POSTGRES_DB:-kodeotp}"
POSTGRES_USER="${POSTGRES_USER:-kodeotp_app}"
POSTGRES_PASSWORD="${POSTGRES_PASSWORD:-$(openssl rand -hex 24)}"
ADMIN_USERNAME="${ADMIN_USERNAME:-admin}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@example.com}"
ADMIN_WHATSAPP="${ADMIN_WHATSAPP:-6280000000000}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-Adm!$(openssl rand -hex 12)}"
APP_KEY="base64:$(openssl rand -base64 32 | tr -d '\n')"
WEBHOOK_SECRET="$(openssl rand -hex 32)"
mkdir -p "$STACK_DIR/caddy"
chmod 700 "$STACK_DIR"
cat > "$STACK_DIR/.env" <<EOF
KODEOTP_DOMAIN=$DOMAIN
KODEOTP_GATEWAY_HOST=$GATEWAY_HOST
KODEOTP_GATEWAY_PORT=$GATEWAY_PORT
POSTGRES_DB=$POSTGRES_DB
POSTGRES_USER=$POSTGRES_USER
POSTGRES_PASSWORD=$POSTGRES_PASSWORD
EOF
cat > "$STACK_DIR/app.env" <<EOF
APP_NAME=KodeOTP
APP_ENV=production
APP_KEY=$APP_KEY
APP_DEBUG=false
APP_URL=https://$DOMAIN
APP_TIMEZONE=Asia/Makassar
APP_LOCALE=id
APP_FALLBACK_LOCALE=en
LOG_CHANNEL=stack
LOG_STACK=stderr,daily
LOG_LEVEL=info
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=$POSTGRES_DB
DB_USERNAME=$POSTGRES_USER
DB_PASSWORD=$POSTGRES_PASSWORD
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_QUEUE_RETRY_AFTER=1200
REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PORT=6379
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@$DOMAIN
MAIL_FROM_NAME=KodeOTP
SMS_VIRTUAL_BASE_URL=https://api.sms-virtual.net
SMS_VIRTUAL_API_KEY=
SMS_VIRTUAL_TIMEOUT=30
PAKASIR_BASE_URL=https://app.pakasir.com
PAKASIR_PROJECT=
PAKASIR_API_KEY=
PAKASIR_PAYMENT_METHOD=qris
ADMIN_USERNAME=$ADMIN_USERNAME
ADMIN_EMAIL=$ADMIN_EMAIL
ADMIN_WHATSAPP=$ADMIN_WHATSAPP
ADMIN_PASSWORD=$ADMIN_PASSWORD
OTP_GATEWAY_SECRET=$WEBHOOK_SECRET
BACKUP_MAX_MB=100
EOF
chmod 600 "$STACK_DIR/.env" "$STACK_DIR/app.env"
install -m 0755 "$REPO_DIR/deploy/kodeotp-update.sh" /usr/local/bin/kodeotp-update
APP_DIR="$REPO_DIR" STACK_DIR="$STACK_DIR" /usr/local/bin/kodeotp-update
ACTIVE="$(cat "$STACK_DIR/.active_color")"
export KODEOTP_IMAGE="kodeotp-app:latest"
COMPOSE=(docker compose --env-file "$STACK_DIR/.env" -p kodeotp -f "$STACK_DIR/docker-compose.yml")
if [ ! -f "$STACK_DIR/.seeded" ]; then
  "${COMPOSE[@]}" exec -T "app_$ACTIVE" php artisan db:seed --force
  touch "$STACK_DIR/.seeded"
fi
cat <<EOF

INSTALASI SELESAI
Domain             : $DOMAIN
Gateway internal   : http://$GATEWAY_HOST:$GATEWAY_PORT
Admin email        : $ADMIN_EMAIL
Admin password     : $ADMIN_PASSWORD

Tambahkan pada Caddy UTAMA VPS (bukan Caddy stack ini):
$DOMAIN {
  encode zstd gzip
  reverse_proxy $GATEWAY_HOST:$GATEWAY_PORT
}

Lalu reload Caddy utama dan jalankan:
  bash $REPO_DIR/deploy/check-routing.sh

Simpan password admin di password manager, login, lalu isi SMTP, SMS Virtual, dan Pakasir dari menu Admin > Pengaturan.
EOF
