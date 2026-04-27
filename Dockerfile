# Use official PHP 8.3 FPM image
FROM php:8.3-fpm

# Install necessary PHP extensions for Laravel
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libpq-dev \
    unzip \
    git \
    curl \
    gnupg2 \
    lsb-release \
    ca-certificates \
    apt-transport-https \
    nano \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        gd \
        zip \
        pdo \
        pdo_pgsql \
    && apt-get install -y libmagickwand-dev --no-install-recommends \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && rm -rf /var/lib/apt/lists/*

RUN set -eux; \
    ini="${PHP_INI_DIR}/php.ini"; \
    if [ ! -f "$ini" ]; then cp "${PHP_INI_DIR}/php.ini-production" "$ini"; fi; \
    sed -i -E 's/^;?[[:space:]]*upload_max_filesize[[:space:]]*=.*/upload_max_filesize = 80M/' "$ini"; \
    sed -i -E 's/^;?[[:space:]]*post_max_size[[:space:]]*=.*/post_max_size = 80M/' "$ini"

# Install the latest stable version of Node.js and npm
RUN curl -fsSL https://deb.nodesource.com/setup_lts.x | bash - \
    && apt-get install -y nodejs \
    && npm install -g npm@latest \
    && rm -rf /var/lib/apt/lists/*

# Install Xdebug
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# Set the working directory
WORKDIR /var/www/html

# Install Composer (dependency manager for PHP)
RUN curl -sS https://getcomposer.org/installer | php -- \
    --install-dir=/usr/local/bin \
    --filename=composer

# Expose the port the app runs on
EXPOSE 9000
