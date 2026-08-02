#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="${APP_DIR:-/opt/kodeotp/app}"
STACK_DIR="${STACK_DIR:-/opt/kodeotp}"
PROJECT="${PROJECT:-kodeotp}"
LOCK_FILE="$STACK_DIR/.deploy.lock"
ACTIVE_FILE="$STACK_DIR/.active_color"

mkdir -p "$STACK_DIR" "$STACK_DIR/caddy"
exec 9>"$LOCK_FILE"
flock -w "${LOCK_WAIT_SECONDS:-180}" 9 || {
  echo '[deploy] deployment lain masih berjalan'
  exit 1
}
printf '%s\n' "$$" > "$LOCK_FILE"
trap 'rm -f "$LOCK_FILE"' EXIT

[ -f "$STACK_DIR/.env" ] || {
  echo "[deploy] $STACK_DIR/.env belum ada. Jalankan deploy/install.sh terlebih dahulu."
  exit 1
}
[ -f "$STACK_DIR/app.env" ] || {
  echo "[deploy] $STACK_DIR/app.env belum ada. Jalankan deploy/install.sh terlebih dahulu."
  exit 1
}

cd "$APP_DIR"
install -m 0644 deploy/docker-compose.yml "$STACK_DIR/docker-compose.yml"
install -m 0644 deploy/Caddyfile "$STACK_DIR/Caddyfile"
install -m 0755 deploy/kodeotp-update.sh /usr/local/bin/kodeotp-update

COMPOSE=(
  docker compose
  --env-file "$STACK_DIR/.env"
  -p "$PROJECT"
  -f "$STACK_DIR/docker-compose.yml"
)

SHA="$(git rev-parse --short=12 HEAD 2>/dev/null || date +%Y%m%d%H%M%S)"
IMAGE="kodeotp-app:$SHA"
BUILD_ARGS=(-t "$IMAGE")

# Jangan menarik base image pada setiap perubahan UI. Aktifkan manual dengan
# KODEOTP_BUILD_PULL=1 saat memang ingin memperbarui image dasar.
if [ "${KODEOTP_BUILD_PULL:-0}" = 1 ]; then
  BUILD_ARGS+=(--pull)
fi

BUILD_HELP="$(docker build --help 2>&1 || true)"
if grep -q -- '--cpu-period' <<<"$BUILD_HELP"; then
  BUILD_ARGS+=(--cpu-period "${KODEOTP_BUILD_CPU_PERIOD:-100000}")
fi
if grep -q -- '--cpu-quota' <<<"$BUILD_HELP"; then
  # 80000/100000 = maksimum sekitar 0,8 vCPU untuk proses build.
  BUILD_ARGS+=(--cpu-quota "${KODEOTP_BUILD_CPU_QUOTA:-80000}")
fi
if grep -q -- '--cpu-shares' <<<"$BUILD_HELP"; then
  BUILD_ARGS+=(--cpu-shares "${KODEOTP_BUILD_CPU_SHARES:-128}")
fi
if grep -q -- '--memory' <<<"$BUILD_HELP"; then
  BUILD_ARGS+=(--memory "${KODEOTP_BUILD_MEMORY:-1536m}")
fi

BUILD_ARGS+=(
  --build-arg "PHP_BUILD_JOBS=${KODEOTP_PHP_BUILD_JOBS:-2}"
)
BUILD_ARGS+=("$APP_DIR")

echo "[deploy] build hemat CPU $IMAGE"
if command -v ionice >/dev/null 2>&1; then
  ionice -c 3 nice -n 15 docker build "${BUILD_ARGS[@]}" || \
    nice -n 15 docker build "${BUILD_ARGS[@]}"
else
  nice -n 15 docker build "${BUILD_ARGS[@]}"
fi

export KODEOTP_IMAGE="$IMAGE"
"${COMPOSE[@]}" up -d postgres redis

POSTGRES_CID="$("${COMPOSE[@]}" ps -q postgres)"
[ -n "$POSTGRES_CID" ] || {
  echo '[deploy] container PostgreSQL tidak ditemukan'
  exit 1
}

for _ in $(seq 1 60); do
  DB_STATUS="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$POSTGRES_CID" 2>/dev/null || true)"
  [ "$DB_STATUS" = healthy ] && break
  [ "$DB_STATUS" = unhealthy ] && {
    docker logs --tail 150 "$POSTGRES_CID"
    exit 1
  }
  sleep 2
done

DB_STATUS="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$POSTGRES_CID")"
[ "$DB_STATUS" = healthy ] || {
  echo '[deploy] PostgreSQL belum sehat'
  exit 1
}

# Migrasi dijalankan dari image baru sebelum trafik dialihkan.
docker run --rm \
  --network "${PROJECT}_backend" \
  --env-file "$STACK_DIR/app.env" \
  "$IMAGE" php artisan migrate --force

ACTIVE="$(cat "$ACTIVE_FILE" 2>/dev/null || true)"
if [ "$ACTIVE" = blue ]; then
  TARGET=green
  OLD=blue
else
  TARGET=blue
  OLD=green
fi
TARGET_SERVICE="app_$TARGET"
OLD_SERVICE="app_$OLD"

echo "[deploy] menyalakan slot $TARGET"
"${COMPOSE[@]}" up -d --no-deps --force-recreate "$TARGET_SERVICE"
CID="$("${COMPOSE[@]}" ps -q "$TARGET_SERVICE")"
[ -n "$CID" ] || {
  echo '[deploy] container target tidak ditemukan'
  exit 1
}

for _ in $(seq 1 60); do
  STATUS="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$CID" 2>/dev/null || true)"
  [ "$STATUS" = healthy ] && break
  [ "$STATUS" = unhealthy ] && {
    docker logs --tail 150 "$CID"
    exit 1
  }
  sleep 3
done

STATUS="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$CID")"
[ "$STATUS" = healthy ] || {
  docker logs --tail 150 "$CID"
  echo '[deploy] health check timeout'
  exit 1
}

cat > "$STACK_DIR/caddy/upstream.caddy.tmp" <<EOF
reverse_proxy ${TARGET_SERVICE}:8080 {
  header_up Host {http.request.host}
  header_up X-Forwarded-Host {http.request.host}
  header_up X-Forwarded-Proto {http.request.header.X-Forwarded-Proto}
  health_uri /healthz
}
EOF
mv "$STACK_DIR/caddy/upstream.caddy.tmp" "$STACK_DIR/caddy/upstream.caddy"

"${COMPOSE[@]}" up -d caddy
"${COMPOSE[@]}" exec -T caddy \
  caddy reload --config /etc/caddy/Caddyfile --adapter caddyfile

# Verifikasi gateway lokal sebelum slot lama dihentikan. Jika gagal, arahkan
# kembali ke slot lama sehingga push yang gagal tidak memutus website.
GATEWAY_OK=0
for _ in $(seq 1 30); do
  if curl -fsS --max-time 5 \
      "http://${KODEOTP_GATEWAY_HOST:-127.0.0.1}:${KODEOTP_GATEWAY_PORT:-3280}/healthz" \
      | grep -q '"service":"kodeotp"'; then
    GATEWAY_OK=1
    break
  fi
  sleep 2
done

if [ "$GATEWAY_OK" -ne 1 ]; then
  echo '[deploy] gateway baru gagal, melakukan rollback upstream'
  cat > "$STACK_DIR/caddy/upstream.caddy.tmp" <<EOF
reverse_proxy ${OLD_SERVICE}:8080 {
  header_up Host {http.request.host}
  header_up X-Forwarded-Host {http.request.host}
  header_up X-Forwarded-Proto {http.request.header.X-Forwarded-Proto}
  health_uri /healthz
}
EOF
  mv "$STACK_DIR/caddy/upstream.caddy.tmp" "$STACK_DIR/caddy/upstream.caddy"
  "${COMPOSE[@]}" exec -T caddy \
    caddy reload --config /etc/caddy/Caddyfile --adapter caddyfile || true
  docker logs --tail 150 "$CID" || true
  exit 1
fi

# Worker dan scheduler mengikuti image baru setelah web sehat.
"${COMPOSE[@]}" up -d --no-deps --force-recreate worker scheduler
if "${COMPOSE[@]}" ps -q "$OLD_SERVICE" | grep -q .; then
  "${COMPOSE[@]}" stop "$OLD_SERVICE" || true
fi
printf '%s\n' "$TARGET" > "$ACTIVE_FILE"
docker tag "$IMAGE" kodeotp-app:latest

docker image prune -f --filter 'until=168h' >/dev/null || true
echo "[deploy] selesai. slot aktif=$TARGET image=$IMAGE"
