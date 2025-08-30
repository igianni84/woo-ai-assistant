# 🐳 Docker Development Guide - Woo AI Assistant

## 📋 Table of Contents

1. [Overview](#overview)
2. [Quick Start](#quick-start)
3. [Services Architecture](#services-architecture)
4. [Development Workflow](#development-workflow)
5. [Testing in Docker](#testing-in-docker)
6. [Troubleshooting](#troubleshooting)
7. [Advanced Usage](#advanced-usage)

---

## 🎯 Overview

Docker provides a complete, consistent development environment for the Woo AI Assistant plugin. It includes:

- **WordPress** with PHP 8.2 and pre-installed WooCommerce
- **MySQL** 8.0 database with optimized settings
- **phpMyAdmin** for database management
- **Mailhog** for email testing
- **Redis** for caching (optional)
- **Node.js** development server for React widget
- **Test Runner** for isolated testing

### Benefits of Docker Development

✅ **Consistency**: Identical environment across all machines  
✅ **Isolation**: No conflicts with host system  
✅ **Production-like**: Similar to deployment environment  
✅ **Easy Reset**: Complete environment reset in minutes  
✅ **Pre-configured**: WordPress + WooCommerce ready instantly  
✅ **Testing**: Isolated test containers  

---

## 🚀 Quick Start

### Prerequisites
- Docker Desktop installed and running
- Git configured  
- Basic command line knowledge

### 1-Minute Setup
```bash
# Clone the project
git clone [repository-url] woo-ai-assistant
cd woo-ai-assistant

# Run automated setup
./scripts/docker-setup.sh

# Access your environment
open http://localhost:8080
```

### Access Points
- **WordPress**: http://localhost:8080
- **Admin Panel**: http://localhost:8080/wp-admin
  - Username: `admin`
  - Password: `password`
- **phpMyAdmin**: http://localhost:8081
- **Mailhog**: http://localhost:8025

---

## 🏗️ Services Architecture

### Core Services

#### WordPress (`wordpress`)
- **Image**: Custom build with PHP 8.2 + Apache
- **Port**: 8080
- **Features**: 
  - Pre-configured with WooCommerce
  - Development-optimized PHP settings
  - WP-CLI and Composer installed
  - Sample products created automatically

#### MySQL (`mysql`)
- **Image**: mysql:8.0
- **Port**: 3306 (external), internal networking
- **Features**:
  - Optimized for development
  - Multiple databases (dev, test, staging)
  - Slow query logging enabled

#### phpMyAdmin (`phpmyadmin`)
- **Image**: phpmyadmin/phpmyadmin:5
- **Port**: 8081
- **Purpose**: Database management interface

#### Mailhog (`mailhog`)
- **Image**: mailhog/mailhog
- **Port**: 8025 (web), 1025 (SMTP)
- **Purpose**: Email testing and capture

### Development Services

#### Node.js Development (`node-dev`)
- **Profile**: `development`
- **Port**: 3000 (Webpack dev server)
- **Purpose**: React widget development with hot reload

#### Test Runner (`test-runner`)
- **Profile**: `testing`  
- **Purpose**: Isolated testing environment with PHPUnit and Jest

#### Redis (`redis`)
- **Image**: redis:7-alpine
- **Port**: 6379
- **Purpose**: Caching layer (optional)

---

## 💻 Development Workflow

### Daily Commands

#### Start Development Environment
```bash
# Start all core services
docker-compose up -d

# Start with development services (React widget)
docker-compose --profile development up -d

# View startup logs
docker-compose logs -f
```

#### Stop Environment
```bash
# Stop all services
docker-compose down

# Stop and remove volumes (data loss!)
docker-compose down --volumes
```

### Working with WordPress

#### WP-CLI Commands
```bash
# General WP-CLI usage
docker-compose exec wordpress wp --allow-root [command]

# Examples
docker-compose exec wordpress wp --allow-root plugin list
docker-compose exec wordpress wp --allow-root user list
docker-compose exec wordpress wp --allow-root post list
docker-compose exec wordpress wp --allow-root option get siteurl
```

#### Plugin Development
```bash
# Install plugin dependencies
docker-compose exec wordpress composer install

# Activate plugin
docker-compose exec wordpress wp --allow-root plugin activate woo-ai-assistant

# Check plugin status
docker-compose exec wordpress wp --allow-root plugin status woo-ai-assistant
```

### Working with React Widget

#### Start React Development Server
```bash
# Start Node.js development container
docker-compose --profile development up -d node-dev

# View React development logs
docker-compose logs -f node-dev

# Access development server: http://localhost:3000
```

#### Build Widget Assets
```bash
# Build for development
docker-compose exec -T wordpress bash -c "cd /var/www/html/wp-content/plugins/woo-ai-assistant && npm run build"

# Build for production
docker-compose exec -T wordpress bash -c "cd /var/www/html/wp-content/plugins/woo-ai-assistant && npm run build:production"
```

### Database Operations

#### Connect to Database
```bash
# Using MySQL client in container
docker-compose exec mysql mysql -u wordpress -p woo_ai_dev

# Using phpMyAdmin (web interface)
open http://localhost:8081
```

#### Backup and Restore
```bash
# Create backup
docker-compose exec mysql mysqldump -u root -p woo_ai_dev > backup.sql

# Restore backup
docker-compose exec -T mysql mysql -u root -p woo_ai_dev < backup.sql
```

---

## 🧪 Testing in Docker

### Automated Testing
```bash
# Run all tests
./scripts/docker-test.sh

# Run specific test suites
./scripts/docker-test.sh php      # PHPUnit tests
./scripts/docker-test.sh js       # Jest tests
./scripts/docker-test.sh quality  # Quality gates
```

### Manual Testing

#### PHP Testing
```bash
# Start test environment
docker-compose --profile testing run --rm test-runner bash

# Inside test container:
composer test
vendor/bin/phpunit
```

#### JavaScript Testing
```bash
# Run Jest tests
docker-compose --profile development run --rm node-dev npm test

# Run with coverage
docker-compose --profile development run --rm node-dev npm run test:coverage
```

#### Quality Gates
```bash
# Run PHP CodeSniffer
docker-compose exec wordpress composer run phpcs

# Run PHPStan
docker-compose exec wordpress composer run phpstan

# Run all quality gates
docker-compose exec wordpress composer run quality-gates-enforce
```

---

## 🔍 Troubleshooting

### Common Issues

#### Services Won't Start
```bash
# Check Docker is running
docker info

# Check port conflicts
lsof -i :8080  # WordPress
lsof -i :3306  # MySQL
lsof -i :8081  # phpMyAdmin

# View service logs
docker-compose logs [service-name]
```

#### WordPress Loading Issues
```bash
# Check WordPress container logs
docker-compose logs -f wordpress

# Check file permissions
docker-compose exec wordpress ls -la /var/www/html/wp-content/plugins/

# Restart WordPress service
docker-compose restart wordpress
```

#### Database Connection Issues
```bash
# Check MySQL status
docker-compose logs mysql

# Test database connection
docker-compose exec wordpress wp --allow-root db check

# Reset MySQL password
docker-compose exec mysql mysql -u root -p -e "ALTER USER 'wordpress'@'%' IDENTIFIED BY 'wordpress';"
```

#### Plugin Issues
```bash
# Check plugin files
docker-compose exec wordpress ls -la /var/www/html/wp-content/plugins/woo-ai-assistant/

# Check plugin status
docker-compose exec wordpress wp --allow-root plugin status

# Reactivate plugin
docker-compose exec wordpress wp --allow-root plugin deactivate woo-ai-assistant
docker-compose exec wordpress wp --allow-root plugin activate woo-ai-assistant
```

### Performance Issues

#### Slow Build Times
```bash
# Use Docker build cache
docker-compose build --parallel

# Clean unused images
docker system prune

# Use multi-stage builds (already implemented)
```

#### Slow File Sync
```bash
# On macOS, consider using Docker Desktop with improved file sharing
# Or use delegated volumes in docker-compose.yml (already configured)
```

---

## 🔧 Advanced Usage

### Custom Configuration

#### Environment Variables
Create or modify `.env` file:
```env
# Database settings
DB_NAME=custom_db_name
DB_USER=custom_user
DB_PASSWORD=custom_password

# WordPress settings
WP_DEBUG=true
WP_DEBUG_LOG=true

# Plugin settings
WOO_AI_DEVELOPMENT_MODE=true
```

#### Docker Compose Overrides
Create `docker-compose.override.yml`:
```yaml
version: '3.8'
services:
  wordpress:
    volumes:
      - ./custom-uploads:/var/www/html/wp-content/uploads
    environment:
      CUSTOM_ENV_VAR: custom_value
```

### Development Profiles

#### Available Profiles
- `default`: Core services (WordPress, MySQL, phpMyAdmin, Mailhog, Redis)
- `development`: Includes Node.js development server
- `testing`: Includes test runner environment
- `cli`: WP-CLI only

#### Using Profiles
```bash
# Development with React server
docker-compose --profile development up -d

# Testing environment
docker-compose --profile testing run test-runner

# Multiple profiles
docker-compose --profile development --profile testing up -d
```

### Scaling and Performance

#### Optimize for Development
```bash
# Increase memory for containers
# Edit docker-compose.yml and add:
services:
  wordpress:
    deploy:
      resources:
        limits:
          memory: 1G
        reservations:
          memory: 512M
```

#### Monitor Resource Usage
```bash
# Check container stats
docker stats

# Check disk usage
docker system df

# Clean up unused resources
docker system prune -a
```

### Debugging

#### Xdebug Configuration
Add to WordPress container:
```dockerfile
# Install Xdebug
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# Configure Xdebug
RUN echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/xdebug.ini
```

#### Log Monitoring
```bash
# WordPress logs
docker-compose logs -f wordpress

# MySQL slow query log
docker-compose exec mysql tail -f /var/log/mysql/slow.log

# All service logs
docker-compose logs -f
```

---

## 📚 Additional Resources

### Docker Commands Reference
```bash
# Container management
docker-compose up -d              # Start in background
docker-compose down               # Stop and remove
docker-compose restart [service]  # Restart service
docker-compose logs -f [service]  # Follow logs

# Cleanup commands
docker system prune              # Remove unused resources
docker volume prune             # Remove unused volumes
docker image prune              # Remove unused images
```

### File Locations
- **Plugin files**: `/var/www/html/wp-content/plugins/woo-ai-assistant/`
- **WordPress files**: `/var/www/html/`
- **MySQL data**: Docker volume `woo-ai-assistant_mysql_data`
- **Logs**: `./logs/` on host, `/var/log/` in containers

### Useful Links
- [Docker Compose Documentation](https://docs.docker.com/compose/)
- [WordPress Docker Image](https://hub.docker.com/_/wordpress)
- [MySQL Docker Image](https://hub.docker.com/_/mysql)

---

## 🆘 Need Help?

### Quick Fixes
1. **Environment won't start**: Run `./scripts/docker-reset.sh`
2. **Plugin not working**: Check logs with `docker-compose logs wordpress`
3. **Database issues**: Access phpMyAdmin at http://localhost:8081
4. **Port conflicts**: Change ports in `docker-compose.yml`

### Getting Support
1. Check the troubleshooting section above
2. Review Docker and WordPress logs
3. Try a complete reset with `./scripts/docker-reset.sh`
4. Ask for help in the development team chat

---

*Last Updated: 2024-12-30 | Docker Compose Version: 3.8*