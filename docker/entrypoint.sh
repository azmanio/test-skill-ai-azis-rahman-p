#!/bin/sh
set -eu

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

attempt=1
until su -s /bin/sh www-data -c "php artisan migrate --force --no-interaction"; do
    if [ "$attempt" -ge 30 ]; then
        echo "Database unavailable after 30 migration attempts." >&2
        exit 1
    fi

    attempt=$((attempt + 1))
    echo "Migration failed; retrying (${attempt}/30)..." >&2
    sleep 2
done

if [ "${SEED_DATABASE:-false}" = "true" ]; then
    su -s /bin/sh www-data -c "php artisan db:seed --force --no-interaction"
fi

port="${PORT:-80}"
case "$port" in
    ''|*[!0-9]*)
        echo "PORT must be a number." >&2
        exit 1
        ;;
esac

sed "s/listen 80;/listen $port;/" /etc/nginx/sites-available/jualemas \
    > /etc/nginx/sites-available/jualemas.runtime
ln -sf /etc/nginx/sites-available/jualemas.runtime /etc/nginx/sites-enabled/jualemas

php-fpm -F &
php_pid=$!
nginx -g 'daemon off;' &
nginx_pid=$!

shutdown() {
    kill "$php_pid" "$nginx_pid" 2>/dev/null || true
    wait "$php_pid" "$nginx_pid" 2>/dev/null || true
}
trap shutdown EXIT INT TERM

while kill -0 "$php_pid" 2>/dev/null && kill -0 "$nginx_pid" 2>/dev/null; do
    sleep 2 &
    wait $! || true
done

shutdown
wait "$php_pid" "$nginx_pid" 2>/dev/null || true
