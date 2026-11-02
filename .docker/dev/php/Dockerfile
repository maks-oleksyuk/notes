# syntax=docker/dockerfile:1
FROM serversideup/php:8.5-frankenphp

USER root

RUN docker-php-serversideup-dep-install-debian postgresql-client \
 && install-php-extensions gd intl sockets \
 && curl -fsSL https://taskfile.dev/install.sh | sh -s -- -b /usr/local/bin

COPY --chmod=755 .docker/dev/php/entrypoint.d/ /etc/entrypoint.d/

USER www-data

WORKDIR $APP_BASE_DIR

COPY --chown=www-data:www-data composer.json composer.lock ./

RUN composer install --no-dev --no-interaction --no-progress --no-scripts --ansi

COPY --chown=www-data:www-data --exclude=.docker . .

RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --ansi
