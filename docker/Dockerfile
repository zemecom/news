# Stage 1: builder
FROM php:8.4-cli AS builder
WORKDIR /app
RUN apt-get update && apt-get install -y git unzip libpq-dev libzip-dev libxml2-dev libonig-dev libicu-dev \
    && docker-php-ext-install pdo pdo_pgsql zip intl sockets mbstring
COPY composer.json composer.lock ./
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm composer-setup.php
RUN composer install --no-dev --prefer-dist --optimize-autoloader --no-scripts
COPY . .
RUN php artisan package:discover --ansi
# RUN npm ci && npm run build # для фронта, если понадобится

# Stage 2: production
FROM php:8.4-fpm-alpine AS runtime
WORKDIR /app
RUN apk add --no-cache $PHPIZE_DEPS linux-headers libzip-dev libxml2-dev oniguruma-dev icu-dev postgresql-dev \
    && docker-php-ext-install pdo pdo_pgsql zip xml mbstring intl sockets \
    && apk del $PHPIZE_DEPS
COPY --from=builder /app /app

# Nginx image can be separate; here only php-fpm
CMD ["php-fpm", "-F"]
