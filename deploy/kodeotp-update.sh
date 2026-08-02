#!/usr/bin/env bash
set -Eeuo pipefail
APP_DIR="${APP_DIR:-/opt/kodeotp/app}"
STACK_DIR="${STACK_DIR:-/opt/kodeotp}"
PROJECT="${PROJECT:-kodeotp}"
LOCK_FILE="$STACK_DIR/.deploy.lock"
ACTIVE_FILE="$STACK_DIR/.active_color"
mkdir -p "$STACK_DIR" "$STACK_DIR/caddy"
exec 9>"$LOCK_FILE"
flock -w "${LOCK_WAIT_SECONDS:-180}" 9 || { echo '[deploy] deployment lain masih berjalan'; exit 1; }
printf '%s\n' "$$" > "$LOCK_FILE"
trap 'rm -f "$LOCK_FILE"' EXIT

[ -f "$STACK_DIR/.env" ] || { echo "[deploy] $STACK_DIR/.env belum ada. Jalankan deploy/install.sh terlebih dahulu."; exit 1; }
[ -f "$STACK_DIR/app.env" ] || { echo "[deploy] $STACK_DIR/app.env belum ada. Jalankan deploy/install.sh terlebih dahulu."; exit 1; }
cd "$APP_DIR"
install -m 0644 deploy/docker-compose.yml "$STACK_DIR/docker-compose.yml"
install -m 0644 deploy/Caddyfile "$STACK_DIR/Caddyfile"
install -m 0755 deploy/kodeotp-update.sh /usr/local/bin/kodeotp-update

COMPOSE=(docker compose --env-file "$STACK_DIR/.env" -p "$PROJECT" -f "$STACK_DIR/docker-compose.yml")
SHA="$(git rev-parse --short=12 HEAD 2>/dev/null || date +%Y%m%d%H%M%S)"
IMAGE="kodeotp-app:$SHA"
echo "[deploy] build $IMAGE"
docker build --pull -t "$IMAGE" "$APP_DIR"
export KODEOTP_IMAGE="$IMAGE"
"${COMPOSE[@]}" up -d postgres redis

# Migrasi dijalankan dari image baru sebelum trafik dialihkan.
docker run --rm \
  --network "${PROJECT}_backend" \
  --env-file "$STACK_DIR/app.env" \
  "$IMAGE" php artisan migrate --force

ACTIVE="$(cat "$ACTIVE_FILE" 2>/dev/null || true)"
if [ "$ACTIVE" = blue ]; then TARGET=green; OLD=blue; else TARGET=blue; OLD=green; fi
TARGET_SERVICE="app_$TARGET"
OLD_SERVICE="app_$OLD"
echo "[deploy] menyalakan slot $TARGET"
"${COMPOSE[@]}" up -d --no-deps --force-recreate "$TARGET_SERVICE"
CID="$("${COMPOSE[@]}" ps -q "$TARGET_SERVICE")"
[ -n "$CID" ] || { echo '[deploy] container target tidak ditemukan'; exit 1; }
for _ in $(seq 1 60); do
  STATUS="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$CID" 2>/dev/null || true)"
  [ "$STATUS" = healthy ] && break
  [ "$STATUS" = unhealthy ] && { docker logs --tail 150 "$CID"; exit 1; }
  sleep 3
done
STATUS="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$CID")"
[ "$STATUS" = healthy ] || { docker logs --tail 150 "$CID"; echo '[deploy] health check timeout'; exit 1; }

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
"${COMPOSE[@]}" exec -T caddy caddy reload --config /etc/caddy/Caddyfile --adapter caddyfile

# Worker/scheduler ikut image baru setelah web sehat.
"${COMPOSE[@]}" up -d --no-deps --force-recreate worker scheduler
if "${COMPOSE[@]}" ps -q "$OLD_SERVICE" | grep -q .; then "${COMPOSE[@]}" stop "$OLD_SERVICE" || true; fi
printf '%s\n' "$TARGET" > "$ACTIVE_FILE"
docker tag "$IMAGE" kodeotp-app:latest

docker image prune -f --filter 'until=168h' >/dev/null || true
echo "[deploy] selesai. slot aktif=$TARGET image=$IMAGE"
