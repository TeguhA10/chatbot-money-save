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

# Bind to dynamic cloud port (Render, Railway, Fly.io) if PORT env var is provided
if [ -n "$PORT" ] && [ "$PORT" != "8000" ]; then
    echo "Binding Octane server to cloud port $PORT..."
    set -- $(echo "$@" | sed "s/--port=8000/--port=$PORT/g")
fi

# Start Laravel scheduler daemon in background if enabled
if [ "$ENABLE_SCHEDULER" = "true" ] || ( [ -f .env ] && grep -Eq '^ENABLE_SCHEDULER=true' .env ); then
    echo "Starting Laravel background scheduler daemon (schedule:work)..."
    php artisan schedule:work &
fi

# Start Laravel queue worker daemon in background if enabled
if [ "$ENABLE_QUEUE_WORKER" = "true" ] || ( [ -f .env ] && grep -Eq '^ENABLE_QUEUE_WORKER=true' .env ); then
    echo "Starting Laravel background queue worker (queue:work)..."
    php artisan queue:work --sleep=1 --tries=3 --timeout=90 &
fi

# Execute passed command (defaults to Octane FrankenPHP)
exec "$@"
