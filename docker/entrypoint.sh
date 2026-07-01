#!/bin/sh
set -e
cd /app
php artisan key:generate --no-interaction 2>/dev/null || true
php artisan migrate --force --no-interaction
php artisan config:cache
php artisan route:cache
exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
