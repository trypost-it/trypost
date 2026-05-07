#!/bin/sh

# Ensure storage directories exist and have correct permissions
mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache storage/logs
chown -R www-data:www-data storage bootstrap/cache

# Check if APP_KEY is set
if [ -z "$APP_KEY" ]; then
    echo "WARNING: APP_KEY is not set. Generating one..."
    php artisan key:generate --force --no-interaction
fi

# Run migrations if database is ready
if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "Running migrations..."
    php artisan migrate --force --no-interaction
fi

# Cache configuration and routes for performance
echo "Caching configuration and routes..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start Apache in the foreground
echo "Starting Apache on port $PORT..."
apache2-foreground
