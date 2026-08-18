FROM composer:latest@sha256:4d71c3c2109c61d5415544264b59ad4087e4c5b7244481723664138fd36d5040 AS composer

FROM php:8.5-fpm@sha256:32ef9f35b567a741f24c5d2c3312f803fe6c9e34b7db46212f95fce675e1d13f

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    zip \
    unzip \
    curl \
    ca-certificates \
    default-mysql-client \
    libmagickwand-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    libzip-dev \
    && curl -fsSL https://deb.nodesource.com/setup_24.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql bcmath gd zip opcache sockets \
    && php -r 'exit(function_exists("imagecreatefromwebp") && (gd_info()["WebP Support"] ?? false) ? 0 : 1);' \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html
