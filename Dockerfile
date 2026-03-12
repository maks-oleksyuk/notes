FROM serversideup/php:8.5-fpm-nginx

# Switch to root so we can do root things
USER root

# Install the intl and bcmath extensions with root permissions
RUN apt-get update && apt-get install -y --no-install-recommends git && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions intl

# Symfony startup script
COPY .dokploy/autorun.sh /etc/cont-init.d/10-symfony
RUN chmod +x /etc/cont-init.d/10-symfony

# Drop back to our unprivileged user
USER www-data

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .

ENV APP_RUNTIME_OPTIONS='{"disable_dotenv":true}'

RUN composer install --no-scripts --optimize-autoloader --no-interaction --ansi \
    && composer recipes:install --force --no-interaction
