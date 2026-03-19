FROM php:8.4-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    unzip \
    zip \
    libzip-dev \
    libsqlite3-dev \
    sqlite3 \
    libonig-dev \
    libxml2-dev \
    libicu-dev \
    libpq-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libfreetype6-dev \
    libmagickwand-dev \
    python3 \
    python3-pip \
    ca-certificates \
    gnupg \
    && docker-php-ext-install pdo pdo_sqlite mbstring zip intl \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && mkdir -p /etc/apt/keyrings \
    && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key \
      | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_24.x nodistro main" \
      > /etc/apt/sources.list.d/nodesource.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends nodejs \
    && npm install -g npm@latest \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /workspace
