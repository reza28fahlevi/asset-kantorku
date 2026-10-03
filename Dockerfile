# syntax=docker/dockerfile:1
#
# Image produksi Asset Kantorku: Nginx + PHP-FPM + scheduler dalam satu container.
# Build : docker build -t asset-kantorku .
# Jalan : lihat docker-compose.yml dan DOCKER.md

ARG PHP_VERSION=8.4

############################
# Base: PHP + ekstensi
############################
FROM php:${PHP_VERSION}-fpm-alpine AS base

COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/

RUN apk add --no-cache nginx supervisor su-exec tzdata \
    && install-php-extensions pdo_pgsql pgsql gd zip intl bcmath opcache exif \
    && rm -rf /tmp/* /var/cache/apk/*

WORKDIR /var/www/html

############################
# Vendor: dependency Composer (tanpa dev)
############################
FROM base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && rm -rf bootstrap/cache/*.php

############################
# Runtime
############################
FROM base AS runtime

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    TZ=Asia/Jakarta

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zzz-app.conf
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint

COPY --from=vendor --chown=www-data:www-data /var/www/html /var/www/html

RUN sed -i 's/\r$//' /usr/local/bin/entrypoint \
    && chmod +x /usr/local/bin/entrypoint \
    && mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions \
                storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

VOLUME ["/var/www/html/storage"]
EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD wget -qO- http://127.0.0.1/up > /dev/null || exit 1

ENTRYPOINT ["entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
