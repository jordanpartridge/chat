#!/bin/bash
set -e

# Setup environment if not exists
if [ ! -f ".env" ]; then
    cp .env.example .env
    php artisan key:generate
fi

# Create SQLite database if not exists
if [ ! -f "database/database.sqlite" ]; then
    touch database/database.sqlite
fi

# Run migrations
php artisan migrate --force

# Clear caches
php artisan config:clear
php artisan cache:clear

# Start PHP built-in server
exec php artisan serve --host=0.0.0.0 --port=80
