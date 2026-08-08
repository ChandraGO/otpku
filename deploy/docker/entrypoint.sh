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
fi
exec "$@"
