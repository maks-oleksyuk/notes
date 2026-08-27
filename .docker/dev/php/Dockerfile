# syntax=docker/dockerfile:1
FROM serversideup/php:8.5-frankenphp

USER root

RUN apt-get update && apt-get install -y postgresql-client && rm -rf /var/lib/apt/lists/*

RUN install-php-extensions gd intl

RUN sh -c "$(curl -s --location https://taskfile.dev/install.sh)" -- -b /usr/local/bin

USER www-data

WORKDIR $APP_BASE_DIR

COPY --chown=www-data:www-data composer.json composer.lock ./

RUN composer install --no-progress --no-scripts --no-interaction --prefer-dist --ansi

COPY --chown=www-data:www-data --exclude=.docker . .

RUN php artisan app:setup --ansi

COPY --chown=www-data:www-data --chmod=755 .docker/dev/php/entrypoint.d/70-post-start.sh /etc/entrypoint.d/70-post-start.sh
