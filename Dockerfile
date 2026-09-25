FROM php:8.4-fpm

# System deps (libpq for pdo_pgsql, nginx)
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev libonig-dev libxml2-dev zip unzip git curl nginx \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# PHP extensions
RUN docker-php-ext-install pdo_pgsql mbstring pcntl bcmath

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install dependencies (scripts off until source present)
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-dev --ignore-platform-reqs --no-scripts \
    && composer clear-cache

# App source
COPY --chown=www-data:www-data . /var/www/html

# Post-install scripts
RUN composer dump-autoload --no-interaction --classmap-authoritative

# Nginx config for Laravel
COPY docker/nginx.conf /etc/nginx/sites-available/jualemas
RUN ln -sf /etc/nginx/sites-available/jualemas /etc/nginx/sites-enabled/jualemas \
    && rm -f /etc/nginx/sites-enabled/default

# Entrypoint: migrate+seed real DB, then start nginx+php-fpm
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Storage permissions
RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80

CMD ["/entrypoint.sh"]