# syntax=docker/dockerfile:1
#
# One image, several roles (set CONTAINER_ROLE): web | reverb | queue
#   web    nginx + php-fpm serving the site
#   reverb the WebSocket server (live orders / reservations)
#   queue  the queue worker
#
# Build:  docker build -t kusina-app .   (see DEPLOY.md; docker-compose.yml passes the build args)

# ---------------------------------------------------------------- 1) front-end assets
FROM node:22-alpine AS assets
WORKDIR /app

# The browser needs the PUBLIC websocket address, which Vite bakes into the JS at build time.
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT=443
ARG VITE_REVERB_SCHEME=https
ENV VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY \
    VITE_REVERB_HOST=$VITE_REVERB_HOST \
    VITE_REVERB_PORT=$VITE_REVERB_PORT \
    VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY vite.config.js tailwind.config.js postcss.config.js ./
COPY resources ./resources
RUN npm run build

# ---------------------------------------------------------------- 2) PHP dependencies
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# Platform extensions are provided by the final image; here we only need the packages.
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative --no-interaction --no-scripts --ignore-platform-reqs

# ---------------------------------------------------------------- 3) runtime
FROM php:8.4-fpm-alpine AS runtime

RUN apk add --no-cache nginx supervisor su-exec gettext tzdata \
        icu-libs libzip libpng libjpeg-turbo libwebp freetype \
 && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
 && docker-php-ext-install -j"$(nproc)" gd intl pdo_mysql zip bcmath opcache pcntl \
 && apk del .build-deps

# PHP: production defaults + our overrides. Web-only restrictions live in the FPM pool (docker/php/www.conf)
# so artisan/queue/reverb keep the functions they legitimately need.
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php/zz-kusina.ini "$PHP_INI_DIR/conf.d/zz-kusina.ini"
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-www.conf

COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/default.conf.template /etc/nginx/templates/default.conf.template
COPY docker/supervisor/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

WORKDIR /var/www/html
COPY --from=vendor /app/ ./
COPY --from=assets /app/public/build ./public/build

# Writable runtime dirs; the entrypoint fixes ownership again because /storage is a volume.
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/public bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache \
 && rm -rf tests docker Dockerfile docker-compose.yml .git

ENV APP_ENV=production \
    APP_DEBUG=false

EXPOSE 80 8080
HEALTHCHECK --interval=30s --timeout=5s --start-period=40s CMD wget -qO- http://127.0.0.1/up >/dev/null 2>&1 || exit 1

ENTRYPOINT ["entrypoint"]
