#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="${APP_DIR:-/opt/dapetotp/app}"
ENV_FILE="${ENV_FILE:-/opt/dapetotp/production.env}"
STATE_DIR="${STATE_DIR:-/opt/dapetotp/.deploy-state}"
COMPOSE_FILE="${COMPOSE_FILE:-$APP_DIR/compose.yaml}"
PROJECT_NAME="${PROJECT_NAME:-dapetotp}"
WEB_HEALTH_URL="${WEB_HEALTH_URL:-http://127.0.0.1:3281/}"
API_HEALTH_URL="${API_HEALTH_URL:-http://127.0.0.1:3281/api/public/settings}"

exec 9>/var/lock/dapetotp-deploy.lock
if ! flock -n 9; then
  echo "Deploy dapetOTP lain sedang berjalan."
  exit 1
fi

require_command() {
  command -v "$1" >/dev/null 2>&1 || {
    echo "Command '$1' belum tersedia di VPS." >&2
    exit 1
  }
}

require_command docker
require_command python3
require_command curl

if ! docker compose version >/dev/null 2>&1; then
  echo "Docker Compose plugin belum tersedia." >&2
  exit 1
fi

if [ ! -d "$APP_DIR" ]; then
  echo "Folder aplikasi tidak ditemukan: $APP_DIR" >&2
  exit 1
fi

if [ ! -f "$COMPOSE_FILE" ]; then
  echo "compose.yaml tidak ditemukan: $COMPOSE_FILE" >&2
  exit 1
fi

if [ ! -f "$ENV_FILE" ]; then
  echo "Environment production tidak ditemukan: $ENV_FILE" >&2
  exit 1
fi

chmod 600 "$ENV_FILE" || true
mkdir -p "$STATE_DIR"
chmod 700 "$STATE_DIR" || true
STATE_FILE="$STATE_DIR/fingerprints.env"

cd "$APP_DIR"

docker compose \
  --env-file "$ENV_FILE" \
  -p "$PROJECT_NAME" \
  -f "$COMPOSE_FILE" \
  config >/dev/null

fingerprint() {
  python3 - "$@" <<'PYHASH'
from pathlib import Path
import hashlib
import os
import sys

roots = [Path(item) for item in sys.argv[1:]]
ignore_dirs = {'.git', '.github', 'node_modules', '__pycache__', '.deploy-state'}
ignore_names = {'.env', 'production.env'}
files = []

for root in roots:
    if not root.exists():
        continue
    if root.is_file():
        files.append(root)
        continue

    for current, dirs, names in os.walk(root):
        current_path = Path(current)
        dirs[:] = [d for d in dirs if d not in ignore_dirs]
        for name in names:
            if name in ignore_names or name.endswith('.pyc'):
                continue
            files.append(current_path / name)

h = hashlib.sha256()
for path in sorted(set(files), key=lambda p: p.as_posix()):
    rel = path.as_posix().encode('utf-8')
    h.update(len(rel).to_bytes(4, 'big'))
    h.update(rel)
    try:
        data = path.read_bytes()
    except OSError:
        continue
    h.update(len(data).to_bytes(8, 'big'))
    h.update(data)

print(h.hexdigest())
PYHASH
}

BACKEND_HASH="$(fingerprint backend deploy/backend.Dockerfile deploy/requirements-prod.txt compose.yaml)"
WEB_HASH="$(fingerprint frontend deploy/frontend.Dockerfile deploy/nginx.conf compose.yaml)"

OLD_BACKEND_HASH=""
OLD_WEB_HASH=""
if [ -f "$STATE_FILE" ]; then
  # shellcheck disable=SC1090
  . "$STATE_FILE" || true
  OLD_BACKEND_HASH="${DEPLOY_BACKEND_HASH:-}"
  OLD_WEB_HASH="${DEPLOY_WEB_HASH:-}"
fi

backend_changed=0
web_changed=0
[ "$BACKEND_HASH" = "$OLD_BACKEND_HASH" ] || backend_changed=1
[ "$WEB_HASH" = "$OLD_WEB_HASH" ] || web_changed=1

if [ ! -f "$STATE_FILE" ]; then
  echo "Deploy pertama: backend dan web akan dibuild."
else
  echo "Perubahan terdeteksi: backend=$backend_changed web=$web_changed"
fi

build_services=()
(( backend_changed )) && build_services+=(backend)
(( web_changed )) && build_services+=(web)

if ((${#build_services[@]})); then
  echo "Build service: ${build_services[*]}"
  DOCKER_BUILDKIT=1 docker compose \
    --env-file "$ENV_FILE" \
    -p "$PROJECT_NAME" \
    -f "$COMPOSE_FILE" \
    build --pull "${build_services[@]}"
else
  echo "Tidak ada source backend/web yang berubah; build dilewati."
fi

# Jalankan/recreate service berdasarkan image terbaru dan pastikan Mongo tetap hidup.
docker compose \
  --env-file "$ENV_FILE" \
  -p "$PROJECT_NAME" \
  -f "$COMPOSE_FILE" \
  up -d --no-build --remove-orphans

show_diagnostics() {
  echo >&2
  echo "=== docker compose ps ===" >&2
  docker compose --env-file "$ENV_FILE" -p "$PROJECT_NAME" -f "$COMPOSE_FILE" ps >&2 || true
  echo >&2
  echo "=== backend logs ===" >&2
  docker compose --env-file "$ENV_FILE" -p "$PROJECT_NAME" -f "$COMPOSE_FILE" logs --no-color --tail=120 backend >&2 || true
  echo >&2
  echo "=== web logs ===" >&2
  docker compose --env-file "$ENV_FILE" -p "$PROJECT_NAME" -f "$COMPOSE_FILE" logs --no-color --tail=120 web >&2 || true
}

wait_url() {
  local label="$1"
  local url="$2"
  local attempt

  echo "Cek $label: $url"
  for attempt in $(seq 1 45); do
    if curl -fsS --connect-timeout 3 --max-time 10 "$url" >/dev/null 2>&1; then
      echo "$label OK."
      return 0
    fi
    if (( attempt % 10 == 0 )); then
      echo "Masih menunggu $label... ($attempt/45)"
    fi
    sleep 2
  done

  echo "$label gagal merespons." >&2
  show_diagnostics
  return 1
}

wait_url "frontend" "$WEB_HEALTH_URL"
wait_url "backend API" "$API_HEALTH_URL"

cat > "$STATE_FILE.tmp" <<EOFSTATE
DEPLOY_BACKEND_HASH='$BACKEND_HASH'
DEPLOY_WEB_HASH='$WEB_HASH'
EOFSTATE
mv "$STATE_FILE.tmp" "$STATE_FILE"
chmod 600 "$STATE_FILE" || true

echo
echo "Deploy dapetOTP berhasil."
docker compose \
  --env-file "$ENV_FILE" \
  -p "$PROJECT_NAME" \
  -f "$COMPOSE_FILE" \
  ps
