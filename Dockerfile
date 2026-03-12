FROM serversideup/php:8.5-fpm-nginx

# Switch to root so we can do root things
USER root

# Install the intl and bcmath extensions with root permissions
RUN install-php-extensions intl

# Drop back to our unprivileged user
USER www-data

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .

ENV APP_RUNTIME_OPTIONS='{"disable_dotenv":true}'

RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --ansi \
    && composer recipes:install --force --no-interaction
