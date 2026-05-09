# syntax=docker/dockerfile:1.7

# ----------------------------------------------------------------------------
# TryPost Dockerfile — multi-stage with `dev` and `production` targets.
#
#   docker build --target dev        -t trypost:dev  -f docker/Dockerfile .
#   docker build --target production -t trypost:prod -f docker/Dockerfile .
# ----------------------------------------------------------------------------

ARG PHP_VERSION=8.4
ARG NODE_VERSION=22

# ----------------------------------------------------------------------------
# Stage 1: system-base — PHP-FPM + system packages + extensions
# ----------------------------------------------------------------------------
FROM php:${PHP_VERSION}-fpm-alpine AS system-base

RUN apk add --no-cache \
        nginx \
        supervisor \
        bash \
        curl \
        git \
        unzip \
        shadow \
        tzdata \
        postgresql-client \
        postgresql-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        libzip-dev \
        oniguruma-dev \
        icu-dev \
        linux-headers \
        $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        gd \
        zip \
        opcache \
        bcmath \
        exif \
        pcntl \
        intl \
        sockets \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS \
    && rm -rf /tmp/* /var/cache/apk/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ----------------------------------------------------------------------------
# Stage 2: composer-deps — full PHP deps (incl. dev) for tooling/asset build
# ----------------------------------------------------------------------------
FROM system-base AS composer-deps

COPY composer.json composer.lock ./
RUN composer install \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction

# ----------------------------------------------------------------------------
# Stage 3: composer-deps-prod — production deps only (no dev tooling)
# ----------------------------------------------------------------------------
FROM system-base AS composer-deps-prod

COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction

# ----------------------------------------------------------------------------
# Stage 4: wayfinder-gen — generate TS route helpers (needs PHP + Laravel)
# ----------------------------------------------------------------------------
FROM composer-deps AS wayfinder-gen

COPY --from=composer-deps /var/www/html/vendor ./vendor
COPY . .

# Stub env so artisan can boot; overridden at runtime.
ENV APP_KEY=base64:c3R1Yi13YXlmaW5kZXItZ2VuLWtleS1mb3ItYnVpbGRpbmctYXNzZXRzMA== \
    APP_ENV=production \
    APP_DEBUG=false

RUN composer dump-autoload --no-scripts --optimize \
    && php artisan wayfinder:generate --with-form

# ----------------------------------------------------------------------------
# Stage 5: asset-build — Vite + Inertia SSR build
# ----------------------------------------------------------------------------
FROM node:${NODE_VERSION}-alpine AS asset-build

WORKDIR /var/www/html

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

# Pull source + freshly generated wayfinder TS from previous stage.
COPY --from=wayfinder-gen /var/www/html /var/www/html

RUN npm run build && npm run build:ssr

# ----------------------------------------------------------------------------
# Stage 6: dev — local development image (bind-mount the source at runtime)
# ----------------------------------------------------------------------------
FROM system-base AS dev

ARG UID=1000
ARG GID=1000

# Node + npm for in-container Vite, npm scripts, ad-hoc tooling.
RUN apk add --no-cache nodejs npm

# Create non-root app user matching host UID/GID for clean bind-mount writes.
RUN groupmod -g "${GID}" www-data 2>/dev/null || groupadd -g "${GID}" app \
    && (id -u app >/dev/null 2>&1 || useradd -u "${UID}" -g "${GID}" -d /home/app -m -s /bin/bash app) \
    && chown -R "${UID}:${GID}" /var/www/html

COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/php.dev.ini /usr/local/etc/php/conf.d/99-trypost.ini
COPY docker/supervisord.dev.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/.env.docker.example /var/www/html/.env.docker.example
RUN chmod +x /usr/local/bin/entrypoint.sh

ENV TRYPOST_TARGET=dev

EXPOSE 80 5173 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=5 \
    CMD curl -fsS http://127.0.0.1/up || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

# ----------------------------------------------------------------------------
# Stage 7: production — self-contained image for self-hosters
# ----------------------------------------------------------------------------
FROM system-base AS production

# Non-root runtime user (fixed UID; overridable per orchestrator if needed).
RUN addgroup -g 1000 -S app \
    && adduser -u 1000 -S app -G app -h /home/app

COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/php.prod.ini /usr/local/etc/php/conf.d/99-trypost.ini
COPY docker/supervisord.prod.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Production composer deps only.
COPY --from=composer-deps-prod /var/www/html/vendor /var/www/html/vendor

# Application source + generated wayfinder TS + built assets.
COPY --from=wayfinder-gen /var/www/html /var/www/html
COPY --from=asset-build /var/www/html/public/build /var/www/html/public/build
COPY --from=asset-build /var/www/html/bootstrap/ssr /var/www/html/bootstrap/ssr

RUN composer dump-autoload --optimize --classmap-authoritative --no-scripts \
    && chown -R app:app /var/www/html/storage /var/www/html/bootstrap/cache

ENV TRYPOST_TARGET=production

EXPOSE 80 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -fsS http://127.0.0.1/up || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
