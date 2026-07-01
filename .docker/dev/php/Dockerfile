FROM serversideup/php:8.5-frankenphp@sha256:00d0699b22265e91e7e27f85f57b08f23f946bbb59bf811e520b99c748979d77

USER root

RUN apt-get update && apt-get install -y postgresql-client && rm -rf /var/lib/apt/lists/*

RUN install-php-extensions gd intl

RUN sh -c "$(curl -s --location https://taskfile.dev/install.sh)" -- -d -b /usr/local/bin > /dev/null 2>&1

USER www-data

WORKDIR $APP_BASE_DIR

COPY --chown=www-data:www-data composer.json composer.lock ./

RUN composer install --no-progress --no-scripts --no-interaction --prefer-dist --ansi

COPY --chown=www-data:www-data . .

RUN php artisan app:setup --ansi
