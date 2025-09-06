FROM php:8.3-fpm

# System deps + amqp build deps
RUN apt-get update && apt-get install -y \
    bash git unzip libpq-dev libicu-dev libzip-dev libssl-dev librabbitmq-dev \
 && pecl install amqp \
 && docker-php-ext-enable amqp \
 && docker-php-ext-install pdo pdo_pgsql intl opcache \
 && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
