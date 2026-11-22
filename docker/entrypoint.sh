#!/bin/sh
set -e
cd /var/www/html

ROLE="${CONTAINER_ROLE:-web}"

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/public bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

if [ -z "${APP_URL:-}" ] && [ -n "${RENDER_EXTERNAL_URL:-}" ]; then export APP_URL="$RENDER_EXTERNAL_URL"; fi

for v in APP_KEY APP_URL DB_HOST DB_DATABASE DB_USERNAME DB_PASSWORD; do
    eval "val=\${$v:-}"
    if [ -z "$val" ]; then echo "FATAL: required environment variable $v is not set" >&2; exit 1; fi
done

[ -e public/storage ] || php artisan storage:link --no-interaction || true

su-exec www-data php artisan config:cache --no-interaction
su-exec www-data php artisan route:cache --no-interaction
su-exec www-data php artisan view:cache --no-interaction
su-exec www-data php artisan event:cache --no-interaction || true

case "$ROLE" in
  web|all)
    if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
        echo "Running database migrations..."
        su-exec www-data php artisan migrate --force --no-interaction
        su-exec www-data php artisan db:seed --class='Database\Seeders\MenuSeeder' --force --no-interaction
        su-exec www-data php artisan db:seed --class='Database\Seeders\FloorSeeder' --force --no-interaction
    fi

    export PORT="${PORT:-80}"
    if [ "$ROLE" = "all" ]; then export REVERB_UPSTREAM="${REVERB_UPSTREAM:-127.0.0.1:8080}"; fi
    export REVERB_UPSTREAM="${REVERB_UPSTREAM:-reverb:8080}"
    export NGINX_RESOLVER="${NGINX_RESOLVER:-$(awk '/^nameserver/{print $2; exit}' /etc/resolv.conf)}"
    export NGINX_RESOLVER="${NGINX_RESOLVER:-127.0.0.11}"
    mkdir -p /etc/nginx/http.d
    envsubst '${PORT} ${REVERB_UPSTREAM} ${NGINX_RESOLVER}' < /etc/nginx/templates/default.conf.template > /etc/nginx/http.d/default.conf
    nginx -t

    POOL=/usr/local/etc/php-fpm.d/zz-www.conf
    sed -i "s/^pm.max_children = .*/pm.max_children = ${FPM_MAX_CHILDREN:-20}/; s/^pm.start_servers = .*/pm.start_servers = ${FPM_START_SERVERS:-4}/; s/^pm.min_spare_servers = .*/pm.min_spare_servers = ${FPM_MIN_SPARE:-2}/; s/^pm.max_spare_servers = .*/pm.max_spare_servers = ${FPM_MAX_SPARE:-6}/" "$POOL"

    CONF=/etc/supervisor/supervisord.conf
    if [ "$ROLE" = "all" ]; then CONF=/etc/supervisor/supervisord-all.conf; fi
    exec supervisord -c "$CONF"
    ;;

  reverb)
    exec su-exec www-data php artisan reverb:start --host=0.0.0.0 --port="${REVERB_SERVER_PORT:-8080}" --no-interaction
    ;;

  queue)
    exec su-exec www-data php artisan queue:work --sleep=3 --tries=3 --max-time=3600 --no-interaction
    ;;

  *)
    echo "Unknown CONTAINER_ROLE '$ROLE' (use web | all | reverb | queue)" >&2
    exit 1
    ;;
esac
