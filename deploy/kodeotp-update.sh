#!/usr/bin/env bash
set -Eeuo pipefail

DEPLOY_SCRIPT_VERSION="2026.08.09-auto-maintenance-v20-shared-media"

APP_DIR="${APP_DIR:-/opt/kodeotp/app}"
STACK_DIR="${STACK_DIR:-/opt/kodeotp}"
BRANCH="${BRANCH:-master}"
PROJECT="${PROJECT:-kodeotp}"
RELEASES_DIR="$STACK_DIR/releases"
DEPLOYED_SHA_FILE="$STACK_DIR/.deployed_sha"
ACTIVE_FILE="$STACK_DIR/.active_color"
LOCK_FILE="$STACK_DIR/.deploy.lock"
STACK_ENV="$STACK_DIR/.env"
APP_ENV="$STACK_DIR/app.env"
SHARED_PUBLIC_DIR="$STACK_DIR/shared/public"
CHANGED_FILE=""
OLD_RELEASE_DIR=""
NEW_RELEASE_DIR=""
NEW_SHA=""
SHORT_SHA=""
SUCCESS=0
FIRST_INCREMENTAL=0
NEEDS_ASSETS=0
NEEDS_RESTART=0
NEEDS_WORKERS=0
NEEDS_MIGRATE=0
NEEDS_INFRA=0
NEEDS_FULL_BUILD=0
NEEDS_RUNTIME_OVERLAY=0
RUNTIME_IMAGE=""
CURRENT_COLOR="none"
CURRENT_SERVICE=""
CURRENT_CID=""

log() {
  printf '[deploy] %s\n' "$*"
}

log "KodeOTP deployer $DEPLOY_SCRIPT_VERSION"

read_env_value() {
  local key="$1" file="$2"
  [ -f "$file" ] || return 0
  grep -E "^[[:space:]]*${key}=" "$file" \
    | tail -n 1 \
    | sed -E "s/^[^=]+=//; s/^['\"]//; s/['\"]$//" \
    || true
}

set_env_value() {
  local file="$1" key="$2" value="$3" tmp
  tmp="$(mktemp)"
  if [ -f "$file" ]; then
    awk -v key="$key" -v value="$value" '
      BEGIN { done = 0 }
      $0 ~ "^[[:space:]]*" key "=" {
        if (!done) print key "=" value
        done = 1
        next
      }
      { print }
      END { if (!done) print key "=" value }
    ' "$file" > "$tmp"
  else
    printf '%s=%s\n' "$key" "$value" > "$tmp"
  fi
  install -m 0600 "$tmp" "$file"
  rm -f "$tmp"
}

cleanup_exit() {
  local rc=$?
  trap - EXIT
  if [ "$SUCCESS" -ne 1 ] && [ -n "$OLD_RELEASE_DIR" ]; then
    set_env_value "$STACK_ENV" KODEOTP_RELEASE_DIR "$OLD_RELEASE_DIR" || true
    log "deploy gagal; KODEOTP_RELEASE_DIR dikembalikan ke $OLD_RELEASE_DIR"
  fi
  [ -n "$CHANGED_FILE" ] && rm -f "$CHANGED_FILE" || true
  rm -f "$LOCK_FILE" || true
  exit "$rc"
}
trap cleanup_exit EXIT

mkdir -p "$STACK_DIR" "$STACK_DIR/caddy" "$RELEASES_DIR" "$SHARED_PUBLIC_DIR"
exec 9>"$LOCK_FILE"
flock -w "${LOCK_WAIT_SECONDS:-180}" 9 || {
  log 'deployment lain masih berjalan'
  exit 1
}
printf '%s\n' "$$" > "$LOCK_FILE"

[ -f "$STACK_ENV" ] || {
  log "$STACK_ENV belum ada. Instalasi awal belum lengkap."
  exit 1
}
[ -f "$APP_ENV" ] || {
  log "$APP_ENV belum ada. Instalasi awal belum lengkap."
  exit 1
}

cancel_stale_build_clients() {
  local pids
  pids="$(pgrep -f 'docker build .*kodeotp-app|docker build .*Dockerfile.*kodeotp' || true)"
  if [ -n "$pids" ]; then
    log "menghentikan client build KodeOTP lama: $pids"
    kill -TERM $pids 2>/dev/null || true
    sleep 3
    kill -KILL $pids 2>/dev/null || true
  fi
}
cancel_stale_build_clients

cd "$APP_DIR"
log "fetch origin/$BRANCH"
git fetch --prune origin "$BRANCH"
NEW_SHA="$(git rev-parse "origin/$BRANCH")"
SHORT_SHA="$(printf '%s' "$NEW_SHA" | cut -c1-12)"
NEW_RELEASE_DIR="$RELEASES_DIR/$NEW_SHA"

DEPLOYED_SHA="$(cat "$DEPLOYED_SHA_FILE" 2>/dev/null || true)"
CHANGED_FILE="$(mktemp)"
if [ -n "$DEPLOYED_SHA" ] && git cat-file -e "$DEPLOYED_SHA^{commit}" 2>/dev/null; then
  git diff --name-only "$DEPLOYED_SHA" "$NEW_SHA" > "$CHANGED_FILE" || true
  log "commit sukses sebelumnya: $DEPLOYED_SHA"
else
  FIRST_INCREMENTAL=1
  log 'marker deploy incremental belum ada; melakukan bootstrap cepat tanpa full build'
fi
log "commit target: $NEW_SHA"

if [ -s "$CHANGED_FILE" ]; then
  log 'file berubah sejak deploy sukses terakhir:'
  sed 's/^/[deploy]   /' "$CHANGED_FILE" | head -n 200
else
  log 'tidak ada diff aplikasi atau ini bootstrap pertama'
fi

classify_changes() {
  if [ "$FIRST_INCREMENTAL" -eq 1 ]; then
    NEEDS_RESTART=1
    NEEDS_WORKERS=1
    NEEDS_MIGRATE=1
    NEEDS_INFRA=1
    return 0
  fi

  # Tailwind v4 memindai resources/views/**/*.blade.php saat Vite build.
  # Karena itu perubahan Blade dapat memperkenalkan utility class baru dan WAJIB
  # membangun ulang CSS. Tanpa ini markup baru bisa tampil tanpa style lengkap
  # (contoh: modal crop terlihat inline/terpotong atau tema dark tidak terpakai).
  grep -Eq '^(resources/css/|resources/js/|resources/views/|vite\.config\.js$|package\.json$|package-lock\.json$)' "$CHANGED_FILE" \
    && NEEDS_ASSETS=1 || true

  grep -Eq '^(app/|bootstrap/|config/|database/|public/|resources/|routes/|artisan$)' "$CHANGED_FILE" \
    && NEEDS_RESTART=1 || true

  # Worker hanya direstart bila kode yang benar-benar dapat dijalankan oleh
  # queue/scheduler berubah. Perubahan controller, Blade, atau route web tidak
  # perlu memutus worker yang sedang menyelesaikan job.
  grep -Eq '^(app/(Jobs|Models|Notifications|Providers|Services|Support)/|bootstrap/|config/|database/|routes/console\.php$|composer\.json$|composer\.lock$)' "$CHANGED_FILE" \
    && NEEDS_WORKERS=1 || true

  # Setiap perubahan aplikasi menjalankan `migrate --force`. Perintah Laravel
  # ini aman dijalankan berulang karena migration yang sudah tercatat akan
  # dilewati. Dengan begitu migration tidak perlu lagi dijalankan manual di VPS,
  # termasuk ketika satu commit berisi file baru/overwrite bersama perubahan lain.
  if grep -Ev '^(deploy/|\.github/|.*\.(md|txt)$|README($|\.)|LICENSE($|\.))' "$CHANGED_FILE" \
      | grep -q .; then
    NEEDS_MIGRATE=1
    # Fallback penting: file aplikasi baru yang belum tercantum di regex lama
    # tetap membuat release baru aktif, bukan hanya ditarik oleh Git.
    NEEDS_RESTART=1
  fi

  grep -Eq '^(deploy/|\.github/workflows/deploy\.yml$|\.dockerignore$)' "$CHANGED_FILE" \
    && NEEDS_INFRA=1 || true

  if grep -Eq '^deploy/docker-compose\.yml$' "$CHANGED_FILE"; then
    NEEDS_RESTART=1
    NEEDS_WORKERS=1
  fi

  # File konfigurasi runtime (entrypoint/nginx/php/supervisor) cukup
  # dilapiskan di atas image aktif. Tidak perlu compile ulang PHP extensions.
  grep -Eq '^deploy/docker/' "$CHANGED_FILE" \
    && NEEDS_RUNTIME_OVERLAY=1 || true

  # Full build hanya untuk perubahan yang benar-benar mengubah dependency/runtime
  # dasar. Ini mencegah deploy entrypoint sederhana menghabiskan >8 menit.
  grep -Eq '^(Dockerfile$|composer\.json$|composer\.lock$)' "$CHANGED_FILE" \
    && NEEDS_FULL_BUILD=1 || true

  [ "$NEEDS_ASSETS" -eq 1 ] && NEEDS_RESTART=1
  if [ "$NEEDS_RUNTIME_OVERLAY" -eq 1 ]; then
    NEEDS_RESTART=1
    NEEDS_WORKERS=1
  fi
  if [ "$NEEDS_FULL_BUILD" -eq 1 ]; then
    NEEDS_RESTART=1
    NEEDS_WORKERS=1
  fi
}
classify_changes

if [ "$FIRST_INCREMENTAL" -eq 0 ] \
  && [ "$NEEDS_ASSETS" -eq 0 ] \
  && [ "$NEEDS_RESTART" -eq 0 ] \
  && [ "$NEEDS_MIGRATE" -eq 0 ] \
  && [ "$NEEDS_INFRA" -eq 0 ] \
  && [ "$NEEDS_FULL_BUILD" -eq 0 ] \
  && [ "$NEEDS_RUNTIME_OVERLAY" -eq 0 ]; then
  log 'commit sudah terdeploy; tidak ada pekerjaan'
  printf '%s\n' "$NEW_SHA" > "$DEPLOYED_SHA_FILE"
  git reset --hard "$NEW_SHA"
  SUCCESS=1
  exit 0
fi

log "mode: assets=$NEEDS_ASSETS restart=$NEEDS_RESTART workers=$NEEDS_WORKERS migrate=$NEEDS_MIGRATE infra=$NEEDS_INFRA overlay=$NEEDS_RUNTIME_OVERLAY full_build=$NEEDS_FULL_BUILD"

create_release() {
  rm -rf "$NEW_RELEASE_DIR"
  mkdir -p "$NEW_RELEASE_DIR"
  git archive "$NEW_SHA" | tar -x -C "$NEW_RELEASE_DIR"

  # ZIP/upload dari Windows dapat menyimpan CRLF di blob Git. Shell Linux
  # memerlukan LF pada shebang, jadi normalkan semua deploy script di release.
  if [ -d "$NEW_RELEASE_DIR/deploy" ]; then
    while IFS= read -r -d '' shell_file; do
      sed -i 's/\r$//' "$shell_file"
    done < <(find "$NEW_RELEASE_DIR/deploy" -type f -name '*.sh' -print0)
  fi

  mkdir -p "$NEW_RELEASE_DIR/public"
  if [ ! -e "$NEW_RELEASE_DIR/public/storage" ]; then
    ln -s /var/www/html/storage/app/public "$NEW_RELEASE_DIR/public/storage"
  fi
}
create_release

install -m 0644 "$NEW_RELEASE_DIR/deploy/docker-compose.yml" "$STACK_DIR/docker-compose.yml"
install -m 0644 "$NEW_RELEASE_DIR/deploy/Caddyfile" "$STACK_DIR/Caddyfile"
# File sudah dinormalkan oleh create_release(). Tetap gunakan install agar
# permission executable dan penggantian deployer berlangsung atomik.
install -m 0755 "$NEW_RELEASE_DIR/deploy/kodeotp-update.sh" /usr/local/bin/kodeotp-update

OLD_RELEASE_DIR="$(read_env_value KODEOTP_RELEASE_DIR "$STACK_ENV")"
if [ -z "$OLD_RELEASE_DIR" ] || [ ! -d "$OLD_RELEASE_DIR" ]; then
  OLD_RELEASE_DIR="$APP_DIR"
fi
set_env_value "$STACK_ENV" KODEOTP_RELEASE_DIR "$OLD_RELEASE_DIR"

COMPOSE=(
  docker compose
  --env-file "$STACK_ENV"
  -p "$PROJECT"
  -f "$STACK_DIR/docker-compose.yml"
)

service_cid() {
  "${COMPOSE[@]}" ps -q "$1" 2>/dev/null | tail -n 1
}

is_running() {
  local cid
  cid="$(service_cid "$1")"
  [ -n "$cid" ] \
    && [ "$(docker inspect -f '{{.State.Status}}' "$cid" 2>/dev/null || true)" = running ]
}

current_color() {
  local saved upstream
  saved="$(cat "$ACTIVE_FILE" 2>/dev/null || true)"
  if [ "$saved" = blue ] && is_running app_blue; then echo blue; return; fi
  if [ "$saved" = green ] && is_running app_green; then echo green; return; fi

  upstream="$(cat "$STACK_DIR/caddy/upstream.caddy" 2>/dev/null || true)"
  if grep -q 'app_blue:8080' <<<"$upstream" && is_running app_blue; then echo blue; return; fi
  if grep -q 'app_green:8080' <<<"$upstream" && is_running app_green; then echo green; return; fi

  if is_running app_blue; then echo blue; return; fi
  if is_running app_green; then echo green; return; fi
  echo none
}

CURRENT_COLOR="$(current_color)"
if [ "$CURRENT_COLOR" != none ]; then
  CURRENT_SERVICE="app_$CURRENT_COLOR"
  CURRENT_CID="$(service_cid "$CURRENT_SERVICE")"
fi

preserve_public_uploads() {
  local temp_dir
  mkdir -p "$SHARED_PUBLIC_DIR"

  # Versi lama menyimpan Storage::disk('public') di writable layer container.
  # Sebelum slot lama dibuang, salin apa pun yang masih tersisa ke direktori
  # bersama. Setelah V20, direktori ini di-mount ke semua slot sehingga upload
  # pengumuman bertahan melewati deploy/recreate berikutnya.
  [ -n "$CURRENT_CID" ] || return 0
  temp_dir="$(mktemp -d)"
  if docker cp "$CURRENT_CID:/var/www/html/storage/app/public/." "$temp_dir/" 2>/dev/null; then
    cp -an "$temp_dir/." "$SHARED_PUBLIC_DIR/" 2>/dev/null || true
    log 'upload publik lama dipertahankan ke shared/public'
  fi
  rm -rf "$temp_dir"
}
preserve_public_uploads

select_runtime_image() {
  local image_id
  if [ -n "$CURRENT_CID" ]; then
    image_id="$(docker inspect -f '{{.Image}}' "$CURRENT_CID")"
    docker tag "$image_id" kodeotp-runtime-base:current
    RUNTIME_IMAGE=kodeotp-runtime-base:current
  elif docker image inspect kodeotp-app:latest >/dev/null 2>&1; then
    docker tag kodeotp-app:latest kodeotp-runtime-base:current
    RUNTIME_IMAGE=kodeotp-runtime-base:current
  else
    log 'tidak ada image runtime lama. Full build diperlukan.'
    NEEDS_FULL_BUILD=1
  fi
}
select_runtime_image

copy_existing_assets() {
  rm -rf "$NEW_RELEASE_DIR/public/build"
  if [ -d "$OLD_RELEASE_DIR/public/build" ]; then
    cp -a "$OLD_RELEASE_DIR/public/build" "$NEW_RELEASE_DIR/public/build"
    return 0
  fi
  if [ -n "$CURRENT_CID" ]; then
    if docker cp "$CURRENT_CID:/var/www/html/public/build" "$NEW_RELEASE_DIR/public/build" 2>/dev/null; then
      return 0
    fi
  fi
  return 1
}

fix_manifest() {
  local build="$NEW_RELEASE_DIR/public/build"
  if [ ! -s "$build/manifest.json" ] && [ -s "$build/.vite/manifest.json" ]; then
    cp "$build/.vite/manifest.json" "$build/manifest.json"
  fi
  [ -s "$build/manifest.json" ] || {
    log 'manifest Vite tidak ditemukan setelah menyiapkan assets'
    return 1
  }
}

build_assets_only() {
  local image="kodeotp-assets:$SHORT_SHA" temp_cid legacy_assets
  [ -n "$RUNTIME_IMAGE" ] || {
    log 'assets build memerlukan runtime image lama untuk source pagination Laravel'
    return 1
  }
  log 'build assets-only; PHP runtime dan Composer tidak dibangun ulang'
  DOCKER_BUILDKIT=1 timeout "${ASSET_BUILD_TIMEOUT_SECONDS:-900}" \
    nice -n 15 docker build \
      --progress=plain \
      --build-arg BUILDKIT_INLINE_CACHE=1 \
      --cache-from kodeotp-assets:cache \
      --build-arg "BASE_IMAGE=$RUNTIME_IMAGE" \
      --target assets \
      -f "$NEW_RELEASE_DIR/deploy/Dockerfile.assets" \
      -t "$image" \
      "$NEW_RELEASE_DIR"

  # Keep the previous hashed files for one or more release generations. A user
  # can load HTML just before the blue/green switch and request the old JS/CSS
  # hash just after the switch. Deleting that hash immediately causes the
  # intermittent /build/assets/app-*.js 404 seen during deployments.
  legacy_assets="$(mktemp -d)"
  if [ -d "$OLD_RELEASE_DIR/public/build/assets" ]; then
    cp -a "$OLD_RELEASE_DIR/public/build/assets/." "$legacy_assets/" 2>/dev/null || true
  fi

  temp_cid="$(docker create "$image")"
  rm -rf "$NEW_RELEASE_DIR/public/build"
  docker cp "$temp_cid:/app/public/build" "$NEW_RELEASE_DIR/public/build"
  docker rm "$temp_cid" >/dev/null

  mkdir -p "$NEW_RELEASE_DIR/public/build/assets"
  if find "$legacy_assets" -mindepth 1 -maxdepth 1 -print -quit | grep -q .; then
    cp -an "$legacy_assets/." "$NEW_RELEASE_DIR/public/build/assets/" 2>/dev/null || true
    log 'hash asset release sebelumnya dipertahankan untuk mencegah 404 saat switch'
  fi
  rm -rf "$legacy_assets"

  docker tag "$image" kodeotp-assets:cache
  fix_manifest
}

if [ "$NEEDS_ASSETS" -eq 1 ]; then
  build_assets_only
elif ! copy_existing_assets; then
  log 'assets lama tidak ditemukan; menjalankan assets-only build satu kali'
  build_assets_only
else
  fix_manifest
fi

build_runtime_overlay() {
  local image="kodeotp-app:$SHORT_SHA" overlay_dir
  [ -n "$RUNTIME_IMAGE" ] || return 1

  log 'runtime config berubah; membuat overlay image cepat tanpa compile ulang PHP'
  overlay_dir="$(mktemp -d)"
  cp "$NEW_RELEASE_DIR/deploy/docker/nginx.conf" "$overlay_dir/nginx.conf"
  cp "$NEW_RELEASE_DIR/deploy/docker/supervisord.conf" "$overlay_dir/supervisord.conf"
  cp "$NEW_RELEASE_DIR/deploy/docker/php.ini" "$overlay_dir/php.ini"
  cp "$NEW_RELEASE_DIR/deploy/docker/entrypoint.sh" "$overlay_dir/entrypoint.sh"

  cat > "$overlay_dir/Dockerfile" <<'EOF'
ARG BASE_IMAGE
FROM ${BASE_IMAGE}
COPY nginx.conf /etc/nginx/http.d/default.conf
COPY supervisord.conf /etc/supervisord.conf
COPY php.ini /usr/local/etc/php/conf.d/99-kodeotp.ini
COPY entrypoint.sh /usr/local/bin/kodeotp-entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/kodeotp-entrypoint \
    && chmod +x /usr/local/bin/kodeotp-entrypoint
EOF

  DOCKER_BUILDKIT=1 timeout "${RUNTIME_OVERLAY_TIMEOUT_SECONDS:-300}" \
    nice -n 10 docker build \
      --progress=plain \
      --build-arg "BASE_IMAGE=$RUNTIME_IMAGE" \
      -t "$image" \
      "$overlay_dir"
  rm -rf "$overlay_dir"

  docker tag "$image" kodeotp-runtime-base:current
  RUNTIME_IMAGE=kodeotp-runtime-base:current
}

if [ "$NEEDS_RUNTIME_OVERLAY" -eq 1 ] && [ -n "$RUNTIME_IMAGE" ]; then
  build_runtime_overlay
fi

build_full_image() {
  local image="kodeotp-app:$SHORT_SHA"
  log 'perubahan runtime/dependency PHP terdeteksi; menjalankan full build langka'
  log 'website lama tetap aktif selama full build'
  local cache_args=()
  if docker image inspect kodeotp-app:latest >/dev/null 2>&1; then
    cache_args=(--cache-from kodeotp-app:latest)
  elif [ -n "$RUNTIME_IMAGE" ] && docker image inspect "$RUNTIME_IMAGE" >/dev/null 2>&1; then
    cache_args=(--cache-from "$RUNTIME_IMAGE")
  fi

  if [ ! -f "$NEW_RELEASE_DIR/composer.lock" ]; then
    log 'PERINGATAN: composer.lock tidak ada; dependency full-build tidak terkunci versinya.'
  fi

  DOCKER_BUILDKIT=1 timeout "${FULL_BUILD_TIMEOUT_SECONDS:-3300}" \
    nice -n 15 docker build \
      --progress=plain \
      --build-arg BUILDKIT_INLINE_CACHE=1 \
      "${cache_args[@]}" \
      --build-arg "PHP_BUILD_JOBS=${KODEOTP_PHP_BUILD_JOBS:-2}" \
      -t "$image" \
      "$NEW_RELEASE_DIR"
  docker tag "$image" kodeotp-runtime-base:current
  RUNTIME_IMAGE=kodeotp-runtime-base:current
}

if [ "$NEEDS_FULL_BUILD" -eq 1 ] \
  && { [ "$FIRST_INCREMENTAL" -eq 0 ] || [ -z "$RUNTIME_IMAGE" ]; }; then
  build_full_image
fi

[ -n "$RUNTIME_IMAGE" ] || {
  log 'runtime image tidak tersedia'
  exit 1
}
export KODEOTP_IMAGE="$RUNTIME_IMAGE"
# Persist juga ke stack .env supaya perintah manual `docker compose up` tidak
# kembali mencoba menarik image default kodeotp-app:latest yang tidak ada.
set_env_value "$STACK_ENV" KODEOTP_IMAGE "$RUNTIME_IMAGE"

# Mulai titik ini compose target memakai release baru. Container aktif lama
# tetap mempertahankan mount release lamanya sampai trafik berhasil diswitch.
set_env_value "$STACK_ENV" KODEOTP_RELEASE_DIR "$NEW_RELEASE_DIR"

wait_healthy() {
  local service="$1" attempts="${2:-60}" cid status
  cid="$(service_cid "$service")"
  [ -n "$cid" ] || { log "container $service tidak ditemukan"; return 1; }
  while [ "$attempts" -gt 0 ]; do
    status="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$cid" 2>/dev/null || true)"
    case "$status" in
      healthy) return 0 ;;
      unhealthy|exited|dead|restarting)
        log "$service status=$status"
        docker logs --tail 180 "$cid" || true
        return 1
        ;;
    esac
    sleep 1
    attempts=$((attempts - 1))
  done
  log "$service belum sehat"
  docker logs --tail 180 "$cid" || true
  return 1
}

log 'memastikan PostgreSQL, Redis, dan gateway internal aktif'
"${COMPOSE[@]}" up -d postgres redis caddy
wait_healthy postgres 60
wait_healthy redis 40

if [ "$NEEDS_MIGRATE" -eq 1 ]; then
  log 'auto maintenance: menjalankan migration pending (php artisan migrate --force)'
  "${COMPOSE[@]}" run --rm --no-deps app_blue php artisan migrate --force
fi

reload_caddy() {
  "${COMPOSE[@]}" exec -T caddy \
    caddy reload --config /etc/caddy/Caddyfile --adapter caddyfile
}

write_upstream() {
  local service="$1"
  cat > "$STACK_DIR/caddy/upstream.caddy.tmp" <<EOF
reverse_proxy ${service}:8080 {
  header_up Host {http.request.host}
  header_up X-Forwarded-Host {http.request.host}
  header_up X-Forwarded-Proto {http.request.header.X-Forwarded-Proto}
  health_uri /healthz
}
EOF
  mv "$STACK_DIR/caddy/upstream.caddy.tmp" "$STACK_DIR/caddy/upstream.caddy"
}

deploy_release() {
  local target target_service old_service cid gateway_host gateway_port
  if [ "$CURRENT_COLOR" = blue ]; then target=green; else target=blue; fi
  [ "$CURRENT_COLOR" = none ] && target=blue
  target_service="app_$target"
  old_service="app_$CURRENT_COLOR"

  log "menyalakan release $SHORT_SHA pada slot $target"
  "${COMPOSE[@]}" rm -sf "$target_service" >/dev/null 2>&1 || true
  if ! "${COMPOSE[@]}" up -d --no-deps --force-recreate "$target_service"; then
    "${COMPOSE[@]}" rm -sf "$target_service" >/dev/null 2>&1 || true
    return 1
  fi
  wait_healthy "$target_service" 70 || {
    "${COMPOSE[@]}" rm -sf "$target_service" >/dev/null 2>&1 || true
    return 1
  }

  write_upstream "$target_service"
  if ! reload_caddy; then
    log 'reload gateway gagal; target baru dibatalkan'
    if [ "$CURRENT_COLOR" != none ]; then
      write_upstream "$old_service"
      reload_caddy || true
    fi
    "${COMPOSE[@]}" rm -sf "$target_service" >/dev/null 2>&1 || true
    return 1
  fi

  gateway_host="$(read_env_value KODEOTP_GATEWAY_HOST "$STACK_ENV")"
  gateway_port="$(read_env_value KODEOTP_GATEWAY_PORT "$STACK_ENV")"
  gateway_host="${gateway_host:-127.0.0.1}"
  gateway_port="${gateway_port:-3280}"

  for _ in $(seq 1 30); do
    if curl -fsS --max-time 5 "http://${gateway_host}:${gateway_port}/healthz" \
      | grep -q '"service":"kodeotp"'; then
      printf '%s\n' "$target" > "$ACTIVE_FILE"
      if [ "$CURRENT_COLOR" != none ] && [ "$CURRENT_COLOR" != "$target" ]; then
        "${COMPOSE[@]}" rm -sf "$old_service" >/dev/null 2>&1 || true
      fi
      if [ "$NEEDS_WORKERS" -eq 1 ]; then
        log 'restart ringan worker dan scheduler karena kode backend berubah'
        if ! "${COMPOSE[@]}" up -d --no-deps --force-recreate worker scheduler; then
          log 'PERINGATAN: web sudah sehat, tetapi worker/scheduler gagal direstart. Web tetap dipertahankan.'
        fi
      fi
      return 0
    fi
    sleep 2
  done

  log 'gateway release baru gagal; mengembalikan trafik lama'
  if [ "$CURRENT_COLOR" != none ]; then
    write_upstream "$old_service"
    reload_caddy || true
  fi
  cid="$(service_cid "$target_service")"
  [ -n "$cid" ] && docker logs --tail 180 "$cid" || true
  "${COMPOSE[@]}" rm -sf "$target_service" >/dev/null 2>&1 || true
  return 1
}

if [ "$NEEDS_RESTART" -eq 1 ] || [ "$NEEDS_FULL_BUILD" -eq 1 ]; then
  deploy_release
elif [ "$NEEDS_INFRA" -eq 1 ]; then
  log 'hanya infra/workflow berubah; image aplikasi dan app container tidak dibangun ulang'
  reload_caddy || true
fi

if [ "$NEEDS_FULL_BUILD" -eq 1 ] || [ "$NEEDS_RUNTIME_OVERLAY" -eq 1 ]; then
  docker tag "$RUNTIME_IMAGE" kodeotp-app:latest || true
fi

printf '%s\n' "$NEW_SHA" > "$DEPLOYED_SHA_FILE"
if ! git reset --hard "$NEW_SHA"; then
  log 'PERINGATAN: release sudah aktif tetapi working tree gagal di-reset. Deploy tetap dianggap berhasil.'
fi
SUCCESS=1

cleanup_releases() {
  local dir mounted count=0
  mounted="$(docker ps -aq | xargs -r docker inspect -f '{{range .Mounts}}{{println .Source}}{{end}}' 2>/dev/null || true)"
  while IFS= read -r dir; do
    [ -n "$dir" ] || continue
    count=$((count + 1))
    [ "$count" -le 10 ] && continue
    grep -Fxq "$dir" <<<"$mounted" && continue
    rm -rf "$dir"
  done < <(find "$RELEASES_DIR" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' \
    | sort -rn | awk '{print $2}')
}
cleanup_releases

# Prune Docker dapat memakan CPU dan membuang layer cache yang membuat deploy
# berikutnya cepat. Jalankan hanya bila operator mengaktifkannya secara eksplisit.
if [ "${KODEOTP_DEPLOY_PRUNE:-0}" = 1 ]; then
  log 'membersihkan container/image lama karena KODEOTP_DEPLOY_PRUNE=1'
  docker container prune -f >/dev/null 2>&1 || true
  docker image prune -f --filter 'until=336h' >/dev/null 2>&1 || true
fi

log "selesai cepat. commit=$SHORT_SHA release=$NEW_RELEASE_DIR"
