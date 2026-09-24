FROM php:8.4-fpm

# System deps needed to compile PHP extensions (libpq for pdo_pgsql)
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev \
        libonig-dev \
        libxml2-dev \
        zip \
        unzip \
        git \
        curl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# ext-pcntl required by the concurrency test (pcntl_fork); bcmath for money-safe math
RUN docker-php-ext-install pdo_pgsql mbstring pcntl bcmath

# Composer (official image)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install runtime dependencies with scripts disabled (artisan is not present yet;
# package:discover/post-autoload run later after source copy)
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-dev --ignore-platform-reqs --no-scripts \
    && composer clear-cache

# Application source (vendor/ excluded via .dockerignore)
COPY --chown=www-data:www-data . /var/www/html

# Now run the framework's post-install scripts (package discovery)
RUN composer dump-autoload --no-interaction --classmap-authoritative

RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8000

CMD php artisan serve --host=0.0.0.0 --port=8000