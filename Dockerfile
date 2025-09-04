FROM php:8.3-fpm

# System deps
RUN apt-get update && apt-get install -y \
    git unzip libpq-dev libicu-dev libzip-dev \
 && docker-php-ext-install pdo pdo_pgsql intl opcache \
 && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
