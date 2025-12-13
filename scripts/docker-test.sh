#!/bin/bash
# Run browser tests in Docker
# Usage: ./scripts/docker-test.sh [filter]

set -e

FILTER=${1:-"Browser"}

echo "🐳 Building development container..."
docker compose -f docker-compose.dev.yml build

echo "📦 Installing dependencies..."
docker compose -f docker-compose.dev.yml run --rm app bash -c "
    composer install --no-interaction
    npm ci
    npx playwright install chromium --with-deps
    npm run build
"

echo "🧪 Running browser tests..."
docker compose -f docker-compose.dev.yml run --rm app bash -c "
    cp .env.example .env 2>/dev/null || true
    php artisan key:generate --force
    touch database/database.sqlite
    php artisan migrate --force
    php artisan test --filter=$FILTER
"

echo "✅ Done!"
