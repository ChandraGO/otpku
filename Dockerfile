FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader --no-scripts
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-interaction

FROM node:22-alpine AS assets
WORKDIR /app
ENV NODE_OPTIONS=--max-old-space-size=768
COPY package.json ./
RUN npm install --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
COPY --from=vendor /app/vendor/laravel/framework/src/Illuminate/Pagination/resources/views /app/vendor/laravel/framework/src/Illuminate/Pagination/resources/views
RUN npm run build

FROM php:8.4-fpm-alpine AS runtime
ARG PHP_BUILD_JOBS=2
RUN apk add --no-cache bash curl nginx supervisor icu-libs libzip libxml2 oniguruma postgresql-client gzip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS curl-dev icu-dev libzip-dev libxml2-dev oniguruma-dev postgresql-dev linux-headers \
    && docker-php-ext-install -j"${PHP_BUILD_JOBS}" bcmath curl dom intl mbstring opcache pcntl pdo_pgsql xml zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps
WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build
COPY deploy/docker/nginx.conf /etc/nginx/http.d/default.conf
COPY deploy/docker/supervisord.conf /etc/supervisord.conf
COPY deploy/docker/php.ini /usr/local/etc/php/conf.d/99-kodeotp.ini
COPY deploy/docker/entrypoint.sh /usr/local/bin/kodeotp-entrypoint
RUN chmod +x /usr/local/bin/kodeotp-entrypoint \
    && mkdir -p storage/app/public storage/app/backups storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache /run/nginx \
    && chown -R www-data:www-data storage bootstrap/cache
EXPOSE 8080
ENTRYPOINT ["kodeotp-entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
