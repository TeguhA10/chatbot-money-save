FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

FROM php:8.3-cli-alpine
WORKDIR /var/www
RUN docker-php-ext-install pdo pdo_sqlite
COPY --from=vendor /app/vendor ./vendor
COPY . .
RUN addgroup -S app && adduser -S app -G app && mkdir -p storage/framework storage/logs && chown -R app:app storage bootstrap/cache
USER app
EXPOSE 8000
HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD php -r "exit(@file_get_contents('http://127.0.0.1:8000/api/health/live') ? 0 : 1);"
CMD ["sh", "-c", "php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=8000"]
