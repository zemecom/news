FROM php:8.5-fpm-alpine

WORKDIR /app

ARG WITH_XDEBUG=0

# Install system dependencies, PHP extensions and build dependencies
# Note: opcache, mbstring, and xml are built-in in php:8.5-alpine
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS linux-headers \
    git unzip postgresql-dev libzip-dev oniguruma-dev icu-dev \
    && apk add --no-cache libzip icu-libs libpq oniguruma libxml2 nodejs npm libstdc++ \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && if [ "$WITH_XDEBUG" = "1" ]; then pecl install xdebug && docker-php-ext-enable xdebug; fi \
    && docker-php-ext-install pdo pdo_pgsql zip intl sockets \
    && apk del .build-deps

# Configure Opcache
ENV PHP_OPCACHE_ENABLE=1
ENV PHP_OPCACHE_VALIDATE_TIMESTAMPS=0
ENV PHP_OPCACHE_MAX_ACCELERATED_FILES=10000
ENV PHP_OPCACHE_MEMORY_CONSUMPTION=192
ENV PHP_OPCACHE_MAX_WASTED_PERCENTAGE=10

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Install PHP dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --optimize-autoloader --no-scripts

# Copy application code
COPY . .

# Prepare directories and run framework setup
RUN mkdir -p bootstrap/cache storage/framework/{cache,sessions,views} storage/logs \
    && php artisan package:discover --ansi \
    && php artisan storage:link || true

# Setup Xdebug config if needed
RUN if [ "$WITH_XDEBUG" = "1" ]; then cp docker/php/conf.d/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini; fi

# Fix permissions for www-data user
RUN chown -R www-data:www-data /app

# Switch to non-root user
USER www-data

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD php-fpm -t || exit 1

CMD ["php-fpm", "-F"]
