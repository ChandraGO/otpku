#!/usr/bin/env sh
set -eu
mkdir -p storage/app/public storage/app/backups storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
if [ "${1:-}" = "supervisord" ]; then
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
fi
exec "$@"
