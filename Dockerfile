###############################################################################
# base: PHP-FPM runtime with the extensions InstallHub needs.
###############################################################################
FROM php:8.3-fpm-alpine AS base

# mlocati/docker-php-extension-installer resolves build deps for each extension
# and removes them afterwards, which keeps the final image small.
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
        bcmath \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        redis \
        zip \
    && apk add --no-cache fcgi

WORKDIR /var/www/html

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-installhub.ini
COPY docker/php/zz-fpm.conf /usr/local/etc/php-fpm.d/zz-fpm.conf

###############################################################################
# dev: used by docker-compose locally; source is bind-mounted.
###############################################################################
FROM base AS dev

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN install-php-extensions xdebug-stable \
    && echo "xdebug.mode=off" > /usr/local/etc/php/conf.d/zz-xdebug.ini

USER www-data

###############################################################################
# vendor: resolve production dependencies in an isolated stage.
###############################################################################
FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
COPY packages/ packages/
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

###############################################################################
# production: immutable image deployed to EC2.
###############################################################################
FROM base AS production

ENV APP_ENV=production \
    APP_DEBUG=false

COPY docker/php/opcache-prod.ini /usr/local/etc/php/conf.d/zz-opcache-prod.ini
COPY --from=vendor --chown=www-data:www-data /app /var/www/html

RUN rm -rf tests docker .github \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

HEALTHCHECK --interval=30s --timeout=3s --retries=3 \
    CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 || exit 1

CMD ["php-fpm"]
