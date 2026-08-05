#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="${APP_DIR:-/opt/kodeotp/app}"
STACK_DIR="${STACK_DIR:-/opt/kodeotp}"
BRANCH="${BRANCH:-master}"

ok() { printf '[OK] %s\n' "$*"; }
fail() { printf '[ERROR] %s\n' "$*" >&2; exit 1; }
info() { printf '[INFO] %s\n' "$*"; }

[ "$(id -u)" -eq 0 ] || fail 'Jalankan sebagai root.'
command -v git >/dev/null 2>&1 || fail 'git belum terpasang.'
command -v docker >/dev/null 2>&1 || fail 'Docker belum terpasang.'
docker compose version >/dev/null 2>&1 || fail 'Docker Compose plugin belum tersedia.'
[ -d "$APP_DIR/.git" ] || fail "$APP_DIR bukan Git repository."
[ -f "$STACK_DIR/.env" ] || fail "$STACK_DIR/.env tidak ditemukan."
[ -f "$STACK_DIR/app.env" ] || fail "$STACK_DIR/app.env tidak ditemukan."
[ -f "$APP_DIR/deploy/kodeotp-update.sh" ] || fail 'deploy/kodeotp-update.sh tidak ditemukan.'

cd "$APP_DIR"
REMOTE_URL="$(git remote get-url origin 2>/dev/null || true)"
[ -n "$REMOTE_URL" ] || fail 'Remote origin belum dikonfigurasi.'
info "origin: $REMOTE_URL"
info "branch target: $BRANCH"

if ! git fetch --dry-run origin "$BRANCH"; then
  fail 'VPS tidak dapat mengambil repository GitHub. Untuk repository private, pasang GitHub Deploy Key pada VPS.'
fi
ok 'VPS dapat mengambil commit terbaru dari GitHub.'

install -m 0755 "$APP_DIR/deploy/kodeotp-update.sh" /usr/local/bin/kodeotp-update
bash -n /usr/local/bin/kodeotp-update
ok 'Deployer terpasang di /usr/local/bin/kodeotp-update.'

ACTIVE="$(cat "$STACK_DIR/.active_color" 2>/dev/null || true)"
if [ -n "$ACTIVE" ]; then
  ok "warna aktif saat ini: $ACTIVE"
else
  info 'Marker .active_color belum ada; deploy pertama akan melakukan bootstrap incremental.'
fi

cat <<'EOF'

Pemeriksaan VPS selesai.
Selanjutnya pastikan GitHub repository memiliki secret VPS_SSH_KEY.
Secret VPS_HOST, VPS_USER, dan VPS_PORT bersifat opsional karena workflow memiliki default.
EOF
