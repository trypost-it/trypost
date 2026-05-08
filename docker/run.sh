#!/bin/sh
# Use the PORT environment variable provided by Cloud Run (defaults to 8080)
if [ -z "$PORT" ]; then
    PORT=8080
fi
sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf
sed -i "s/:80/:$PORT/g" /etc/apache2/sites-available/000-default.conf

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
    echo "Attempting to run migrations..."
    php artisan migrate --force --no-interaction || echo "Migration failed - check DB connection."
fi

# Cache configuration and routes for performance
echo "Caching configuration and routes..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start Apache in the foreground
echo "Starting Apache on port $PORT..."
apache2-foreground
