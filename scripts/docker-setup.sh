#!/bin/bash
set -euo pipefail

# Woo AI Assistant Docker Setup Script
# This script sets up the complete Docker development environment

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

echo "🚀 Setting up Woo AI Assistant Docker development environment..."
echo "📁 Project directory: $PROJECT_DIR"
echo ""

# Check if Docker is installed and running
if ! command -v docker &> /dev/null; then
    echo "❌ Docker is not installed. Please install Docker Desktop first."
    echo "   Download from: https://www.docker.com/products/docker-desktop"
    exit 1
fi

if ! docker info &> /dev/null; then
    echo "❌ Docker is not running. Please start Docker Desktop."
    exit 1
fi

# Check if Docker Compose is available
if ! command -v docker-compose &> /dev/null; then
    echo "❌ Docker Compose is not installed. Please install Docker Compose."
    exit 1
fi

echo "✅ Docker and Docker Compose are available"

# Navigate to project directory
cd "$PROJECT_DIR"

# Create .env file if it doesn't exist
if [ ! -f .env ]; then
    echo "📝 Creating .env file from template..."
    cp .env.docker .env
    echo "✅ .env file created"
    echo ""
    echo "⚠️  IMPORTANT: Edit .env file and add your real API keys:"
    echo "   - OPENROUTER_API_KEY"
    echo "   - OPENAI_API_KEY"
    echo "   - PINECONE_API_KEY"
    echo "   - GOOGLE_API_KEY (optional)"
    echo "   - STRIPE_SECRET_KEY (for testing)"
    echo ""
    read -p "Press Enter after you've updated the API keys in .env file..."
else
    echo "✅ .env file already exists"
fi

# Create .dockerignore file
echo "📝 Creating .dockerignore file..."
cat > .dockerignore << 'EOF'
# Git
.git
.gitignore

# Node modules (will be installed in container)
node_modules
npm-debug.log*

# Composer vendor (will be installed in container)
vendor

# IDE files
.vscode
.idea
*.swp
*.swo

# OS files
.DS_Store
Thumbs.db

# Logs
*.log
logs

# Coverage reports
coverage
.nyc_output

# Environment files with secrets
.env.production
.env.staging

# Cache
.cache
*.cache

# Temporary files
tmp
temp

# Build artifacts
dist
build

# Documentation (not needed in container)
*.md
docs

# Test files (will be mounted separately)
tests
*.test.js

# Other
.phpunit.result.cache
.sass-cache
EOF

echo "✅ .dockerignore file created"

# Create logs directory
echo "📁 Creating logs directory..."
mkdir -p logs
echo "✅ logs directory created"

# Build and start the services
echo "🔨 Building Docker images..."
docker-compose build --pull

echo "🚀 Starting services..."
docker-compose up -d mysql redis mailhog

# Wait for MySQL to be ready
echo "⏳ Waiting for MySQL to be ready..."
timeout=60
while ! docker-compose exec -T mysql mysqladmin ping -h"localhost" --silent; do
    sleep 2
    ((timeout--))
    if [[ $timeout -eq 0 ]]; then
        echo "❌ MySQL failed to start within 60 seconds"
        exit 1
    fi
done
echo "✅ MySQL is ready"

# Start WordPress
echo "🌐 Starting WordPress..."
docker-compose up -d wordpress

# Wait for WordPress to be ready
echo "⏳ Waiting for WordPress to be ready..."
timeout=120
while ! curl -s http://localhost:8080 > /dev/null; do
    sleep 5
    ((timeout--))
    if [[ $timeout -eq 0 ]]; then
        echo "❌ WordPress failed to start within 10 minutes"
        echo "💡 Try running: docker-compose logs wordpress"
        exit 1
    fi
    echo "   Still waiting... (${timeout} attempts remaining)"
done
echo "✅ WordPress is ready"

# Start additional services
echo "🔧 Starting additional services..."
docker-compose up -d phpmyadmin

echo ""
echo "🎉 Docker environment setup complete!"
echo ""
echo "📍 Access URLs:"
echo "   WordPress:    http://localhost:8080"
echo "   Admin:        http://localhost:8080/wp-admin"
echo "   phpMyAdmin:   http://localhost:8081"
echo "   Mailhog:      http://localhost:8025"
echo ""
echo "👤 WordPress Admin Credentials:"
echo "   Username: admin"
echo "   Password: password"
echo ""
echo "📊 Database Connection (from host):"
echo "   Host:     localhost:3306"
echo "   Database: woo_ai_dev"
echo "   Username: wordpress"
echo "   Password: wordpress"
echo ""
echo "🔧 Useful Commands:"
echo "   View logs:        docker-compose logs -f [service]"
echo "   Stop all:         docker-compose down"
echo "   Restart:          docker-compose restart [service]"
echo "   Run WP-CLI:       docker-compose exec wordpress wp --allow-root [command]"
echo "   Run Composer:     docker-compose exec wordpress composer [command]"
echo "   Run tests:        ./scripts/docker-test.sh"
echo "   Clean reset:      ./scripts/docker-reset.sh"
echo ""
echo "📝 Next steps:"
echo "   1. Visit http://localhost:8080 to see WordPress"
echo "   2. Go to http://localhost:8080/wp-admin and login with admin/password"
echo "   3. Activate the Woo AI Assistant plugin"
echo "   4. Start developing!"
echo ""