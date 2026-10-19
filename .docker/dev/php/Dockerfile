# syntax=docker/dockerfile:1

FROM node:26-alpine AS theme

WORKDIR /theme

COPY app/themes/theme/package.json app/themes/theme/pnpm-lock.yaml app/themes/theme/pnpm-workspace.yaml ./

RUN npm install -g --color=always --allow-scripts=pnpm pnpm@12 \
    && pnpm install --color=always --prod --frozen-lockfile

COPY app/themes/theme/ ./

RUN FORCE_COLOR=1 pnpm build

FROM serversideup/php:8.5-fpm-nginx

ENV NGINX_WEBROOT=/var/www/html/web \
    PHP_OPCACHE_ENABLE=1

USER root

RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt,sharing=locked \
    apt-get update \
 && apt-get install -y --no-install-recommends git \
 && install-php-extensions gd intl \
 && curl -fsSL https://taskfile.dev/install.sh | sh -s -- -b /usr/local/bin

USER www-data

WORKDIR $APP_BASE_DIR

COPY --chown=www-data:www-data --parents composer.json composer.lock patches.lock.json patches assets app ./

RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader --ansi

COPY --chown=www-data:www-data --exclude=.docker . .
COPY --chown=www-data:www-data --from=theme /theme/dist app/themes/theme/dist

RUN mkdir -p web/sites/default/files private \
 && chmod 444 web/sites/default/settings.php \
 && chmod 555 web/sites/default

COPY --chown=www-data:www-data --chmod=755 .docker/dev/php/entrypoint.d/70-post-start.sh /etc/entrypoint.d/70-post-start.sh
