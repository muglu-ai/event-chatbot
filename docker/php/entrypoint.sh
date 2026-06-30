#!/bin/sh
set -e

cd /var/www/html

if [ ! -f .env ] && [ -f .env.example ]; then
  cp .env.example .env
fi

touch database/database.sqlite
chown -R www-data:www-data storage bootstrap/cache database/database.sqlite

php artisan key:generate --force --no-interaction 2>/dev/null || true
php artisan migrate --force --no-interaction
php artisan config:cache
php artisan route:cache

exec docker-php-entrypoint "$@"
