FROM composer:2.10.3@sha256:af98f42dfff7c68ba8d53c2164fd9fde1087b7d449514baa38c418b1f6bc4bac AS composer

FROM php:8.5.11-fpm-bookworm@sha256:53eab56a8f43f51a92119f6c29b3448af98eec288ff17f92829354a4b4c9ca05

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    zip \
    unzip \
    curl \
    ca-certificates \
    default-mysql-client \
    ffmpeg \
    ghostscript \
    gzip \
    imagemagick \
    poppler-utils \
    libicu-dev \
    libmagickwand-dev \
    libonig-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    libzip-dev \
    && curl -fsSL https://deb.nodesource.com/setup_24.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql bcmath gd intl mbstring zip sockets \
    && php -r 'exit(extension_loaded("Zend OPcache") ? 0 : 1);' \
    && php -r 'foreach (["fileinfo", "imagick", "intl", "mbstring", "zip"] as $extension) { if (!extension_loaded($extension)) { fwrite(STDERR, "Missing PHP extension: {$extension}\n"); exit(1); } }' \
    && php -r 'exit(function_exists("imagecreatefromwebp") && (gd_info()["WebP Support"] ?? false) ? 0 : 1);' \
    && command -v ffmpeg >/dev/null \
    && command -v ffprobe >/dev/null \
    && command -v gs >/dev/null \
    && command -v pdftotext >/dev/null \
    && command -v zip >/dev/null \
    && command -v mysqldump >/dev/null \
    && command -v gzip >/dev/null \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY .docker/production/php.ini /usr/local/etc/php/conf.d/zz-stemmechanics.ini

WORKDIR /var/www/html
