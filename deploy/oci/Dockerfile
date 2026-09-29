# PHP 8.3 CLI worker image for Horizon (OCI / OCIR)
FROM php:8.3-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libpng-dev libonig-dev libxml2-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-install -j$(nproc) pdo_mysql pcntl posix bcmath zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get purge -y $PHPIZE_DEPS \
    && apt-get autoremove -y \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Expect build context = repo root
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi || true \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

USER www-data

ENV HORIZON_ROLE=oci-heavy \
    OCI_WORKERS_ENABLED=false \
    QUEUE_CONNECTION=redis

CMD ["php", "artisan", "horizon"]
