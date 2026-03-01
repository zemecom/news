# ==========================================
# BASE STAGE (Foundation for both Dev & Prod)
# ==========================================
FROM alpine:3.23.3 AS base

WORKDIR /app

# Ensure www-data user exists
RUN set -x ; \
    addgroup -g 82 -S www-data ; \
    adduser -u 82 -D -S -G www-data www-data

# Install PHP 8.5 and required system dependencies
RUN --mount=type=cache,target=/var/cache/apk \
    apk update && apk add \
    php85 \
    php85-fpm \
    php85-ctype \
    php85-curl \
    php85-dom \
    php85-fileinfo \
    php85-mbstring \
    php85-openssl \
    php85-pdo \
    php85-pdo_pgsql \
    php85-pdo_sqlite \
    php85-phar \
    php85-session \
    php85-tokenizer \
    php85-xml \
    php85-simplexml \
    php85-xmlreader \
    php85-xmlwriter \
    php85-pcntl \
    php85-posix \
    php85-iconv \
    php85-intl \
    php85-zip \
    php85-sockets \
    php85-pecl-redis \
    ca-certificates \
    icu-data-full \
    curl \
    && ln -s /usr/bin/php85 /usr/bin/php

# Configure Opcache (common settings)
ENV PHP_OPCACHE_ENABLE="1" \
    PHP_OPCACHE_MAX_ACCELERATED_FILES="10000" \
    PHP_OPCACHE_MEMORY_CONSUMPTION="192" \
    PHP_OPCACHE_MAX_WASTED_PERCENTAGE="10" \
    PATH="/tmp/roadrunner-bin:${PATH}"


# Install Composer
COPY --from=composer:2.9.5 /usr/bin/composer /usr/local/bin/composer

# Healthcheck
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD php -m || exit 1

EXPOSE 8000
CMD ["sh", "docker/bin/start-octane.sh"]

# ==========================================
# DEV STAGE (Local Development)
# ==========================================
FROM base AS local

# Opcache: Reload code automatically
ENV PHP_OPCACHE_VALIDATE_TIMESTAMPS="1"

# Setup Xdebug if needed
ARG WITH_XDEBUG=0
RUN --mount=type=cache,target=/var/cache/apk \
    apk update && apk add \
    nodejs \
    npm \
    git \
    unzip \
    && if [ "$WITH_XDEBUG" = "1" ]; then \
    apk add php85-pecl-xdebug \
    && if [ -f docker/php/conf.d/xdebug.ini ]; then cp docker/php/conf.d/xdebug.ini /etc/php85/conf.d/xdebug.ini; fi; \
    fi

COPY --chown=www-data:www-data composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/cache/composer \
    COMPOSER_CACHE_DIR=/tmp/cache/composer \
    composer install --prefer-dist --no-scripts --no-autoloader

COPY --chown=www-data:www-data . .
RUN composer dump-autoload

RUN mkdir -p bootstrap/cache storage/framework/{cache,sessions,views} storage/logs \
    && php artisan storage:link || true

RUN chown -R www-data:www-data vendor storage bootstrap/cache /var/log/php85

USER www-data


# ==========================================
# PROD STAGE (Production / CI/CD)
# ==========================================
FROM base AS production

# Opcache: Never reload code
ENV PHP_OPCACHE_VALIDATE_TIMESTAMPS="0"

COPY --chown=www-data:www-data composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/cache/composer \
    COMPOSER_CACHE_DIR=/tmp/cache/composer \
    composer install --no-dev --prefer-dist --no-scripts --no-autoloader

COPY --chown=www-data:www-data . .
RUN composer dump-autoload --optimize

RUN mkdir -p bootstrap/cache storage/framework/{cache,sessions,views} storage/logs \
    && php artisan package:discover --ansi \
    && php artisan storage:link || true

RUN chown -R www-data:www-data vendor storage bootstrap/cache /var/log/php85

USER www-data
