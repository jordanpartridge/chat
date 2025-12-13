#!/bin/bash
# Fresh install: rebuild everything from scratch
set -e

echo "🔄 Fresh Docker build..."

# Stop and remove everything
docker compose down -v --remove-orphans

# Rebuild
docker compose build --no-cache

# Start
docker compose up -d

# Run migrations and seed
docker compose exec app php artisan migrate:fresh --seed

echo ""
echo "✅ Fresh install complete!"
