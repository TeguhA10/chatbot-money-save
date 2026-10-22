#!/bin/sh
set -e

# Ensure writable storage and cache
mkdir -p /var/www/storage/framework/cache/data \
         /var/www/storage/framework/sessions \
         /var/www/storage/framework/views \
         /var/www/storage/logs \
         /var/www/bootstrap/cache

# Run database migrations only if explicitly flagged (prevents race condition in scaled replicas)
if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "Running database migrations..."
    php artisan migrate --force
fi

# Production cache optimization
if [ "$APP_ENV" = "production" ]; then
    echo "Warming up Laravel production caches..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
fi

# Execute passed command (defaults to Octane FrankenPHP)
exec "$@"
