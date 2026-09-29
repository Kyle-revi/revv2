#!/bin/sh
set -e

PORT_NUM="${PORT:-8080}"
echo "Configuring Nginx for port ${PORT_NUM}..."
if [ "$PORT_NUM" != "8080" ] && [ "$PORT_NUM" != "80" ] && [ "$PORT_NUM" != "3000" ]; then
    sed -i "s/listen 8080 default_server;/listen ${PORT_NUM} default_server;/g" /etc/nginx/nginx.conf
fi

# Ensure Nginx runtime and log directories
mkdir -p /run/nginx /var/log/nginx /var/lib/nginx/tmp /var/lib/nginx/logs
chown -R www-data:www-data /run/nginx /var/log/nginx /var/lib/nginx

# Ensure all Laravel storage directories exist with full 777 permissions
mkdir -p /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

touch /var/www/html/storage/logs/laravel.log
chmod -R 777 /var/www/html/storage /var/www/html/bootstrap/cache

# Populate .env from runtime environment variables so PHP-FPM and CLI have all DB and App configs
rm -f /var/www/html/.env
php /var/www/html/docker/generate-env.php || true
chmod 666 /var/www/html/.env

# Create storage symlink
php artisan storage:link || true

# Clear cache
php artisan config:clear || true
php artisan cache:clear || true

# Tune Alpine musl DNS resolution for Railway internal networking
if [ -w /etc/resolv.conf ]; then
    echo "options ndots:1 single-request-reopen" >> /etc/resolv.conf 2>/dev/null || true
fi

# Run database migrations with retry
echo "Attempting database migration..."
MAX_TRIES=15
COUNT=0
while [ $COUNT -lt $MAX_TRIES ]; do
    if php artisan migrate --force --no-interaction; then
        echo "Database migration completed successfully."
        # Seed predetermined accounts
        php artisan db:seed --class=AdminSeeder --force || true
        php artisan db:seed --class=TeacherDemoSeeder --force || true
        break
    else
        COUNT=$((COUNT + 1))
        echo "Database not ready yet (attempt $COUNT/$MAX_TRIES), waiting 3 seconds..."
        sleep 3
    fi
done

# Cache optimizations for production
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

nginx -t

echo "Starting Supervisor (Nginx + PHP-FPM)..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf