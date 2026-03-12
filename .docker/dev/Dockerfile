FROM serversideup/php:8.5-fpm-nginx

USER root

RUN install-php-extensions intl

COPY .dokploy/autorun.sh /etc/cont-init.d/10-symfony
RUN chmod +x /etc/cont-init.d/10-symfony

# Drop back to our unprivileged user
USER www-data

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .

RUN composer install --no-scripts --optimize-autoloader --no-interaction --ansi
