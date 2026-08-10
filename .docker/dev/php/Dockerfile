FROM serversideup/php:8.5-fpm-nginx

USER root

RUN install-php-extensions mysqli exif imagick intl

RUN sh -c "$(curl -s --location https://taskfile.dev/install.sh)" -- -d -b /usr/local/bin > /dev/null 2>&1

RUN curl -sO https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
	&& chmod +x wp-cli.phar \
	&& mv wp-cli.phar /usr/local/bin/wp

ENV NGINX_WEBROOT=$APP_BASE_DIR/web

USER www-data

WORKDIR $APP_BASE_DIR

COPY --chown=www-data:www-data composer.json composer.lock ./
COPY --chown=www-data:www-data src/ ./src/

RUN composer install --no-progress --no-scripts --no-interaction --ansi

COPY --chown=www-data:www-data . .

ENV AUTORUN_ENABLED=false

RUN composer run-script post-install-cmd --no-interaction --ansi
