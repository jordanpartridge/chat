#!/bin/bash
# Run tests in Docker (including browser tests)
set -e

FILTER=${1:-""}

echo "🧪 Running tests in Docker..."

if [ -n "$FILTER" ]; then
    echo "   Filter: $FILTER"
    docker compose run --rm app php artisan test --filter="$FILTER"
else
    docker compose run --rm app php artisan test
fi
