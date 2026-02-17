# Stage 1: builder
FROM php:8.5-cli AS builder
WORKDIR /app

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libpq-dev libzip-dev libxml2-dev libonig-dev libicu-dev \
    && docker-php-ext-install pdo pdo_pgsql zip intl sockets mbstring \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --optimize-autoloader --no-scripts

COPY . .
RUN mkdir -p bootstrap/cache storage/framework/{cache,sessions,views} storage/logs \
    && php artisan package:discover --ansi
# RUN npm ci && npm run build # для фронта, если понадобится

# Stage 2: production
FROM php:8.5-fpm-alpine AS runtime
WORKDIR /app

ARG WITH_XDEBUG=0

# Separate build-time and runtime dependencies
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS linux-headers \
    && apk add --no-cache libzip icu-libs libpq oniguruma libxml2 nodejs npm \
    && if [ "$WITH_XDEBUG" = "1" ]; then pecl install xdebug && docker-php-ext-enable xdebug; fi \
    && apk add --no-cache --virtual .ext-build-deps libzip-dev libxml2-dev oniguruma-dev icu-dev postgresql-dev \
    && docker-php-ext-install pdo pdo_pgsql zip xml mbstring intl sockets \
    && apk del .build-deps .ext-build-deps

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
COPY --from=builder /app /app

RUN if [ "$WITH_XDEBUG" = "1" ]; then cp docker/php/conf.d/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini; fi

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD php-fpm -t || exit 1

CMD ["php-fpm", "-F"]
