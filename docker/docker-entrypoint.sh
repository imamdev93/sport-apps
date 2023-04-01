#!/usr/bin/env sh

set -e

role=${CONTAINER_ROLE:-app}
env=${APP_ENV:-production}

if [ "$env" != "local" ]; then
    echo "Setup configuration..."
    echo "Caching configuration..."
    php /var/www/html/artisan optimize
fi

if [ "$role" = "app" ]; then
    # Start app
    echo "Starting Laravel Octane..."
    php -d variables_order=EGPCS /var/www/html/artisan octane:start --server=swoole --host=0.0.0.0 --port=80 --workers=auto --task-workers=auto --watch

elif [ "$role" = "queue" ]; then
    # Start queue worker
    echo "Running the queue worker..."
    php /var/www/html/artisan queue:work -vvv --tries=3 --timeout=90

elif [ "$role" = "scheduler" ]; then
    # Start scheduler
    echo "Running scheduler..."
    php /var/www/html/artisan schedule:run -vvv --no-interaction

else
    echo "Could not match the container role \"$role\""
    exit 1
fi