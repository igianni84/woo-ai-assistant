#!/bin/bash
set -euo pipefail

# Woo AI Assistant Docker Reset Script
# This script completely resets the Docker development environment

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

echo "🧹 Woo AI Assistant Docker Environment Reset"
echo "⚠️  This will completely reset your development environment!"
echo "   - All containers will be stopped and removed"
echo "   - All volumes (database, WordPress files) will be deleted"
echo "   - All cached images will be removed"
echo ""

# Confirmation prompt
read -p "Are you sure you want to reset everything? [y/N] " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "❌ Reset cancelled"
    exit 0
fi

# Navigate to project directory
cd "$PROJECT_DIR"

echo ""
echo "🛑 Stopping all services..."
docker-compose down --volumes --remove-orphans || echo "Services were not running"

echo ""
echo "🗑️  Removing Docker volumes..."
docker volume rm woo-ai-assistant_wordpress_data 2>/dev/null || echo "WordPress volume already removed"
docker volume rm woo-ai-assistant_mysql_data 2>/dev/null || echo "MySQL volume already removed"
docker volume rm woo-ai-assistant_redis_data 2>/dev/null || echo "Redis volume already removed"
docker volume rm woo-ai-assistant_node_modules 2>/dev/null || echo "Node modules volume already removed"

echo ""
echo "🧹 Removing unused Docker resources..."
docker system prune -f --volumes

echo ""
echo "🏗️  Removing project-specific images..."
docker image rm woo-ai-assistant-wordpress 2>/dev/null || echo "WordPress image not found"
docker image rm woo-ai-assistant-node-dev 2>/dev/null || echo "Node dev image not found"  
docker image rm woo-ai-assistant-test-runner 2>/dev/null || echo "Test runner image not found"

echo ""
echo "📁 Cleaning local files..."
# Remove logs but keep directory
rm -rf logs/*
mkdir -p logs
touch logs/.gitkeep

# Remove any cached files
rm -rf .phpunit.cache/
rm -rf node_modules/
rm -rf vendor/
rm -rf coverage/
rm -f .quality-gates-status

echo ""
echo "🔄 Rebuilding environment..."

# Check if .env exists
if [ ! -f .env ]; then
    echo "📝 Creating .env file from template..."
    cp .env.docker .env
    echo "⚠️  Don't forget to add your API keys to .env file!"
fi

# Rebuild everything
echo "🔨 Rebuilding Docker images..."
docker-compose build --no-cache --pull

echo "🚀 Starting fresh environment..."
docker-compose up -d mysql redis mailhog

# Wait for MySQL
echo "⏳ Waiting for MySQL..."
timeout=60
while ! docker-compose exec -T mysql mysqladmin ping -h"localhost" --silent 2>/dev/null; do
    sleep 2
    ((timeout--))
    if [[ $timeout -eq 0 ]]; then
        echo "❌ MySQL failed to start"
        exit 1
    fi
done

# Start WordPress
docker-compose up -d wordpress phpmyadmin

# Wait for WordPress
echo "⏳ Waiting for WordPress..."
timeout=120
while ! curl -s http://localhost:8080 > /dev/null 2>&1; do
    sleep 5
    ((timeout--))
    if [[ $timeout -eq 0 ]]; then
        echo "❌ WordPress failed to start"
        exit 1
    fi
done

echo ""
echo "✨ Environment reset complete!"
echo ""
echo "📍 Fresh environment ready at:"
echo "   WordPress:    http://localhost:8080"
echo "   Admin:        http://localhost:8080/wp-admin (admin/password)"
echo "   phpMyAdmin:   http://localhost:8081"
echo "   Mailhog:      http://localhost:8025"
echo ""
echo "📝 Next steps:"
echo "   1. Update .env with your API keys"
echo "   2. Visit WordPress and activate the plugin"
echo "   3. Start developing!"
echo ""