FROM php:8.4-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libsqlite3-dev \
        libcurl4-openssl-dev \
        libonig-dev \
    && docker-php-ext-install pdo_sqlite mbstring pcntl bcmath zip curl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-interaction --no-progress --prefer-dist

EXPOSE 8000
