ARG PHP_VERSION="wpjscc/php:8.1.15-fpm-alpine"
FROM ${PHP_VERSION}

COPY  . /var/www

WORKDIR /var/www

RUN composer install --ignore-platform-reqs --no-dev --no-interaction -o -vvv


