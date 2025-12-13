#!/bin/bash
# Start the full Docker stack
set -e

echo "🐳 Starting Chat Docker Stack..."

# Build and start
docker compose up -d --build

echo ""
echo "✅ Stack is running!"
echo ""
echo "Services:"
echo "  App:      http://localhost:8000"
echo "  Vite:     http://localhost:5173"
echo "  Mailpit:  http://localhost:8025"
echo "  Postgres: localhost:5432"
echo "  Redis:    localhost:6379"
echo ""
echo "Commands:"
echo "  docker compose logs -f app     # Watch app logs"
echo "  docker compose exec app bash   # Shell into app"
echo "  ./scripts/docker-test.sh       # Run browser tests"
