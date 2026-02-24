# Stage 1: builder
FROM php:8.5-cli AS builder
WORKDIR /app

# Install build dependencies
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libpq-dev libzip-dev libxml2-dev libonig-dev libicu-dev \
    && docker-php-ext-install pdo pdo_pgsql zip intl sockets mbstring xml \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Copy composer files first to leverage Docker cache
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --optimize-autoloader --no-scripts

# Copy application code
COPY . .

# Prepare directories and run framework setup
RUN mkdir -p bootstrap/cache storage/framework/{cache,sessions,views} storage/logs \
    && php artisan package:discover --ansi \
    && php artisan storage:link || true

# Stage 2: production
FROM php:8.5-fpm-alpine AS runtime
WORKDIR /app

ARG WITH_XDEBUG=0

# Install runtime dependencies and extensions
# Added opcache for production performance
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS linux-headers \
    && apk add --no-cache libzip icu-libs libpq oniguruma libxml2 nodejs npm \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && if [ "$WITH_XDEBUG" = "1" ]; then pecl install xdebug && docker-php-ext-enable xdebug; fi \
    && apk add --no-cache --virtual .ext-build-deps libzip-dev libxml2-dev oniguruma-dev icu-dev postgresql-dev \
    && docker-php-ext-install pdo pdo_pgsql zip xml mbstring intl sockets opcache \
    && apk del .build-deps .ext-build-deps

# Configure Opcache (can be overridden via env vars or custom ini)
ENV PHP_OPCACHE_ENABLE=1
ENV PHP_OPCACHE_VALIDATE_TIMESTAMPS=0
ENV PHP_OPCACHE_MAX_ACCELERATED_FILES=10000
ENV PHP_OPCACHE_MEMORY_CONSUMPTION=192
ENV PHP_OPCACHE_MAX_WASTED_PERCENTAGE=10

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
COPY --from=builder /app /app

# Setup Xdebug config if needed
RUN if [ "$WITH_XDEBUG" = "1" ]; then cp docker/php/conf.d/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini; fi

# Fix permissions for www-data user
RUN chown -R www-data:www-data /app

# Switch to non-root user
USER www-data

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD php-fpm -t || exit 1

CMD ["php-fpm", "-F"]
