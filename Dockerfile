FROM php:8.4-fpm

# System deps (libpq for pdo_pgsql, nginx)
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev \
        libonig-dev \
        libxml2-dev \
        zip \
        unzip \
        git \
        curl \
        nginx \
    && rm -f /etc/nginx/sites-enabled/default \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# PHP extensions
RUN docker-php-ext-install pdo_pgsql mbstring pcntl bcmath

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install dependencies after the PHP extensions are available.
COPY composer.json composer.lock ./
RUN --mount=type=cache,id=composer-cache,target=/root/.cache/composer \
    composer install --no-interaction --prefer-dist --no-scripts --classmap-authoritative

# Application source and runtime configuration.
COPY --chown=www-data:www-data . /var/www/html
RUN composer dump-autoload --no-interaction --no-scripts --classmap-authoritative \
    && php artisan package:discover --ansi --no-interaction

COPY docker/nginx.conf /etc/nginx/sites-available/jualemas
RUN ln -sf /etc/nginx/sites-available/jualemas /etc/nginx/sites-enabled/jualemas \
    && rm -f /etc/nginx/sites-enabled/default

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 80

CMD ["/entrypoint.sh"]