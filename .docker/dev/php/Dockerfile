FROM serversideup/php:8.5-fpm-nginx-alpine

USER root

RUN install-php-extensions intl

RUN sh -c "$(curl -s --location https://taskfile.dev/install.sh)" -- -d -b /usr/local/bin > /dev/null 2>&1

USER www-data

WORKDIR $APP_BASE_DIR

COPY --chown=www-data:www-data composer.json composer.lock ./

RUN composer install --no-progress --no-scripts --no-interaction --ansi;

COPY --chown=www-data:www-data . .

ENV APP_RUNTIME_OPTIONS='{"disable_dotenv":true}' \
    DEFAULT_URI='http://localhost' \
    AUTORUN_ENABLED=false

RUN php bin/console assets:install --ansi
