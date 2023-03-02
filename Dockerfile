ARG PHP_VERSION="wpjscc/php:8.0.9-fpm-alpine3.13"
FROM ${PHP_VERSION}

COPY  . /var/www

WORKDIR /var/www

RUN composer install --ignore-platform-reqs --no-dev --no-interaction -o -vvv


