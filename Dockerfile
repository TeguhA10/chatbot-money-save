# ------------------------------------------------------------------------------
# Stage 1: PHP Dependencies (Vendor)
# ------------------------------------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist --no-interaction --ignore-platform-req=ext-gd

# ------------------------------------------------------------------------------
# Stage 2: Frontend Assets Builder (Vue + Vite)
# ------------------------------------------------------------------------------
FROM node:22-alpine AS frontend-builder
WORKDIR /app
COPY backend/package.json backend/yarn.lock* backend/package-lock.json* ./
RUN npm install
COPY backend/vite.config.js ./
COPY backend/resources ./resources
COPY backend/public ./public
RUN npm run build

# ------------------------------------------------------------------------------
# Stage 3: Production Runtime (FrankenPHP Octane)
# ------------------------------------------------------------------------------
FROM dunglas/frankenphp:1-php8.4-alpine

WORKDIR /var/www

# Install high-performance production extensions
RUN install-php-extensions pdo_pgsql pdo_sqlite redis pcntl opcache bcmath gd

# Production OPcache configuration for ultra-low latency & high throughput
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.enable_cli=1'; \
    echo 'opcache.memory_consumption=256'; \
    echo 'opcache.interned_strings_buffer=16'; \
    echo 'opcache.max_accelerated_files=30000'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.save_comments=1'; \
    echo 'opcache.fast_shutdown=1'; \
} > /usr/local/etc/php/conf.d/opcache-prod.ini

ENV OCTANE_SERVER=frankenphp

# Copy composer binary from vendor stage
COPY --from=vendor /usr/bin/composer /usr/bin/composer

# Copy backend dependencies and app code
COPY --from=vendor /app/vendor ./vendor
COPY backend/ .
# Copy compiled frontend assets from Stage 2
COPY --from=frontend-builder /app/public/build ./public/build

# Generate optimized authoritative classmap including app code and discover packages
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# Fix for "Operation not permitted" (exit status 126) on Render and restricted cloud runtimes
RUN setcap -r /usr/local/bin/frankenphp || true && chmod +x /usr/local/bin/frankenphp

# Setup entrypoint script
RUN cp /var/www/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/docker-entrypoint.sh

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

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=8000", "--workers=auto"]
