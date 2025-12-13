#!/bin/bash
# Run browser tests with Playwright in Docker
set -e

FILTER=${1:-"Browser"}

echo "🎭 Running browser tests with Playwright..."
echo "   Filter: $FILTER"

# Use the playwright profile
docker compose --profile testing run --rm playwright php artisan test --filter="$FILTER"

echo ""
echo "📸 Screenshots saved to: storage/app/screenshots/"
