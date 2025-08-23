# Docker Development Environment

This Docker setup provides a complete development environment for Woo AI Assistant plugin as an alternative to MAMP.

## 🚀 Quick Start

```bash
# Start all services
docker-compose up -d

# View logs
docker-compose logs -f wordpress

# Stop all services
docker-compose down

# Reset environment (removes all data)
docker-compose down -v
```

## 🔧 Services Available

| Service | URL | Credentials |
|---------|-----|-------------|
| **WordPress** | http://localhost:8080 | admin/admin |
| **phpMyAdmin** | http://localhost:8081 | wordpress/wordpress_password |
| **Grafana** | http://localhost:3002 | admin/admin123 |
| **Node Dev** | http://localhost:3000 | - |

## 📊 Performance Monitoring

### Grafana Dashboards
Access http://localhost:3002 to view:
- WordPress performance metrics
- Database query performance
- Plugin-specific metrics
- Chat widget performance
- Memory and CPU usage

### InfluxDB Metrics
The setup automatically collects:
- Response times
- Database query counts
- Memory usage
- Chat conversation metrics
- Knowledge base indexing performance

## 🔧 Development Workflow

1. **Start Environment:**
   ```bash
   docker-compose up -d
   ```

2. **Install WordPress:**
   - Visit http://localhost:8080
   - Complete WordPress setup
   - Install WooCommerce plugin
   - Activate Woo AI Assistant plugin

3. **Development:**
   ```bash
   # Watch React changes
   docker-compose exec node npm run watch
   
   # Run tests
   docker-compose exec node npm test
   
   # PHP tests
   docker-compose exec wordpress composer test
   ```

## 📁 Volume Mounts

- `./` → `/var/www/html/wp-content/plugins/woo-ai-assistant`
- `./logs/` → Various log directories
- Persistent data in Docker volumes

## ⚡ Performance Features

### Redis Caching
- Object caching for WordPress
- Session storage for chat conversations
- KB query result caching

### MySQL Optimization
- Tuned configuration for development
- Slow query logging enabled
- Performance schema enabled

### Monitoring Integration
- Real-time performance metrics
- Query analysis
- Resource usage tracking

## 🐛 Troubleshooting

### Common Issues

**Services won't start:**
```bash
# Check port conflicts
docker-compose ps
netstat -tulpn | grep :8080

# Reset everything
docker-compose down -v
docker-compose up -d
```

**WordPress permission errors:**
```bash
# Fix file permissions
docker-compose exec wordpress chown -R www-data:www-data /var/www/html
```

**Database connection issues:**
```bash
# Check database status
docker-compose logs db

# Reset database
docker-compose down
docker volume rm woo-ai-assistant_db_data
docker-compose up -d
```

## 🔧 Customization

### Environment Variables
Create `.env` file:
```env
# WordPress
WP_DEBUG=true
WOO_AI_ASSISTANT_DEBUG=true

# Database
MYSQL_ROOT_PASSWORD=your_secure_password
MYSQL_PASSWORD=your_wordpress_password

# Grafana
GF_SECURITY_ADMIN_PASSWORD=your_admin_password
```

### Configuration Files
- `docker/mysql/conf.d/` - MySQL configuration
- `docker/redis/redis.conf` - Redis configuration
- `docker/grafana/provisioning/` - Grafana dashboards

## 📊 Monitoring Setup

The Docker environment includes comprehensive monitoring:

1. **WordPress Performance:**
   - Page load times
   - Database query performance
   - Memory usage tracking

2. **Plugin Metrics:**
   - Chat conversation volume
   - KB indexing performance
   - AI response times

3. **Infrastructure:**
   - Database performance
   - Redis cache hit rates
   - System resource usage

Access Grafana at http://localhost:3002 to view all metrics in real-time dashboards.