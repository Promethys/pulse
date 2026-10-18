# syntax=docker/dockerfile:1

# One Dockerfile for dev and prod:
# - base: PHP runtime (FrankenPHP) shared by every stage
# - dev:  base + Node and Composer; compose.yaml mounts the source here
# - build: installs dependencies and compiles the Vite assets
# - prod: the production image (web later, `php artisan digest:tick` for the cron)

FROM dunglas/frankenphp:1-php8.4 AS base

RUN install-php-extensions pdo_pgsql intl zip opcache pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

FROM base AS dev

# Node is needed to build the Vue assets; Wayfinder's Vite plugin also calls PHP,
# so Node and PHP must live in the same image.
COPY --from=node:24-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:24-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

FROM dev AS build

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && npm run build \
    && rm -rf node_modules

FROM base AS prod

ENV APP_ENV=production \
    APP_DEBUG=false

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --from=build /app /app

RUN chown -R www-data:www-data storage bootstrap/cache
