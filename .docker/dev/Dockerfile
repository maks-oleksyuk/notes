FROM serversideup/php:8.5-fpm-nginx

USER root

RUN install-php-extensions intl

COPY --chmod=755 .dokploy/autorun.sh /etc/entrypoint.d/20-symfony.sh

USER www-data

WORKDIR /var/www/html

COPY --chown=www-data:www-data composer.json composer.lock ./

RUN composer install --no-scripts --optimize-autoloader --no-interaction --ansi

COPY --chown=www-data:www-data . .
