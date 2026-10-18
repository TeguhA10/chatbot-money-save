FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

FROM dunglas/frankenphp:latest-php8.3-alpine

WORKDIR /var/www

# Install high-performance production extensions
RUN install-php-extensions pdo_pgsql pdo_sqlite redis pcntl opcache bcmath

ENV OCTANE_SERVER=frankenphp

COPY --from=vendor /app/vendor ./vendor
COPY . .

# Ensure storage directories exist and have proper permissions
RUN mkdir -p storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8000

HEALTHCHECK --interval=20s --timeout=5s --retries=3 \
    CMD php -r "exit(@file_get_contents('http://127.0.0.1:8000/api/health/live') ? 0 : 1);"

CMD ["sh", "-c", "php artisan migrate --force && php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=8000 --workers=auto"]
