FROM php:8.2-cli

# Install dependencies
RUN apt-get update && apt-get install -y \
    git unzip zip curl libzip-dev libonig-dev libxml2-dev \
    && docker-php-ext-install zip

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy project files
COPY . .

# Install PHP tools
RUN composer install

# Optional: install PHPCS globally (if not in composer.json)
RUN composer global require "squizlabs/php_codesniffer=*"

# Add Composer global bin to PATH
ENV PATH="/root/.composer/vendor/bin:${PATH}"
