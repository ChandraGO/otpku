#!/usr/bin/env sh
set -eu
mkdir -p storage/app/public storage/app/backups storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
if [ "${1:-}" = "supervisord" ]; then
  # Container web selalu dimulai dari cache Laravel yang bersih. Ini membuat
  # perubahan config/route/view langsung aktif saat auto deploy tanpa perlu
  # `php artisan optimize:clear` manual di VPS.
  php artisan optimize:clear
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache

  # `view:cache` hanya mengompilasi Blade; command tersebut tidak selalu
  # mengeksekusi / mem-parse seluruh file PHP hasil kompilasi. Lint semua
  # compiled view agar syntax Blade yang rusak membuat slot baru gagal start
  # dan blue/green deploy tidak pernah mengalihkan trafik ke release rusak.
  echo "[entrypoint] validating compiled Blade PHP syntax"
  if ! find storage/framework/views -type f -name '*.php' -exec php -l {} \; >/tmp/kodeotp-blade-lint.log 2>&1; then
    cat /tmp/kodeotp-blade-lint.log >&2
    echo "[entrypoint] compiled Blade syntax validation failed" >&2
    exit 1
  fi
fi
exec "$@"
