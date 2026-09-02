# syntax=docker/dockerfile:1
FROM serversideup/php:8.5-frankenphp

USER root

RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt,sharing=locked \
    apt-get update \
 && apt-get install -y --no-install-recommends postgresql-client \
 && install-php-extensions gd intl \
 && curl -fsSL https://taskfile.dev/install.sh | sh -s -- -b /usr/local/bin

USER www-data

WORKDIR $APP_BASE_DIR

COPY --chown=www-data:www-data composer.json composer.lock ./

RUN composer install --no-dev --no-interaction --no-progress --no-scripts --ansi

COPY --chown=www-data:www-data --exclude=.docker . .

RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --ansi

COPY --chown=www-data:www-data --chmod=755 .docker/dev/php/entrypoint.d/70-post-start.sh /etc/entrypoint.d/70-post-start.sh
