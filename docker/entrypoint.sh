#!/bin/sh
set -e

# Storage & bootstrap cache must be writable
mkdir -p storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Wait for Postgres (Neon) to be reachable before migrating
if [ -n "$DB_HOST" ]; then
  echo "Waiting for DB at $DB_HOST:${DB_PORT:-5432}..."
  i=0
  while ! php -r "try { new PDO('pgsql:host=' . getenv('DB_HOST') . ';port=' . (getenv('DB_PORT') ?: '5432') . ';dbname=' . getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); echo 'ok'; } catch (Exception \$e) { exit(1); }" 2>/dev/null | grep -q ok; do
    i=$((i+1))
    if [ $i -ge 30 ]; then echo "DB not reachable after 30 tries"; exit 1; fi
    echo "  retry $i..."
    sleep 2
  done
  echo "DB reachable."
fi

# Run migrations + seed (idempotent)
su -s /bin/sh www-data -c "php artisan migrate --force --no-interaction" || true
su -s /bin/sh www-data -c "php artisan db:seed --force --no-interaction" || true

# Start nginx (foreground) + php-fpm
php-fpm &
nginx -g 'daemon off;'