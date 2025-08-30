# 🤖 Woo AI Assistant - WordPress Plugin

**AI-powered chatbot for WooCommerce with zero-config knowledge base and 24/7 customer support.**

[![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](https://github.com/woo-ai-assistant/woo-ai-assistant)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-7.0%2B-purple.svg)](https://woocommerce.com/)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4.svg)](https://php.net/)
[![Docker](https://img.shields.io/badge/Docker-Ready-2496ED.svg)](https://docker.com/)

---

## 🚀 Quick Start with Docker

Get your development environment running in under 5 minutes:

```bash
# 1. Clone the repository
git clone [repository-url] woo-ai-assistant
cd woo-ai-assistant

# 2. Run automated Docker setup
./scripts/docker-setup.sh

# 3. Access your environment
# WordPress: http://localhost:8080
# Admin: http://localhost:8080/wp-admin (admin/password)
```

**That's it!** You now have a fully configured WordPress + WooCommerce + AI Assistant development environment.

---

## 📍 Access Points

| Service | URL | Credentials |
|---------|-----|-------------|
| WordPress | http://localhost:8080 | - |
| Admin Panel | http://localhost:8080/wp-admin | admin / password |
| phpMyAdmin | http://localhost:8081 | wordpress / wordpress |
| Mailhog | http://localhost:8025 | - |
| React Dev Server | http://localhost:3000 | - |

---

## 🎯 Project Overview

### Core Features
- ✅ **Zero-Config Setup**: Works immediately after activation
- 🧠 **Smart Knowledge Base**: Auto-indexes products and content
- 💬 **AI-Powered Chat**: OpenRouter integration with Gemini models
- 🛒 **WooCommerce Integration**: Product recommendations, order assistance
- 🎟️ **Smart Coupons**: AI-generated discount codes
- 📊 **Analytics Dashboard**: Conversation insights and metrics
- 🌐 **Multi-language**: i18n ready
- 🔒 **Enterprise Security**: Data encryption and privacy protection

### Technology Stack
- **Backend**: PHP 8.2+, WordPress 6.0+, WooCommerce 7.0+
- **Frontend**: React 18+, Webpack 5
- **AI Models**: OpenRouter (Gemini 2.5 Flash/Pro)
- **Vector DB**: Pinecone via intermediate server
- **Development**: Docker, MAMP (alternative)

---

## 🐳 Docker Development Environment

### Why Docker?
- **Consistent**: Same environment across all machines
- **Isolated**: No conflicts with host system  
- **Production-like**: Similar to deployment environment
- **Fast**: Complete reset in minutes
- **Pre-configured**: WordPress + WooCommerce ready instantly

### Services Included
- **WordPress**: PHP 8.2 + Apache with WooCommerce pre-installed
- **MySQL**: 8.0 with development optimizations
- **phpMyAdmin**: Database management interface
- **Mailhog**: Email testing and capture
- **Redis**: Caching layer (optional)
- **Node.js**: React development server
- **Test Runner**: Isolated testing environment

### Daily Docker Commands
```bash
# Start environment
docker-compose up -d

# Stop environment
docker-compose down

# View logs
docker-compose logs -f wordpress

# Run WP-CLI commands
docker-compose exec wordpress wp --allow-root plugin list

# Run tests
./scripts/docker-test.sh

# Complete reset
./scripts/docker-reset.sh
```

---

## 📋 Development Workflow

### 1. Setup (First Time)
```bash
git clone [repo] && cd woo-ai-assistant
./scripts/docker-setup.sh
```

### 2. Daily Development
```bash
# Start environment
docker-compose up -d

# Edit code in your favorite IDE
# Files are automatically synced with containers

# View changes at http://localhost:8080
```

### 3. Testing
```bash
# Run all tests
./scripts/docker-test.sh

# Run specific tests
./scripts/docker-test.sh php      # PHPUnit
./scripts/docker-test.sh js       # Jest  
./scripts/docker-test.sh quality  # Quality Gates
```

### 4. Building
```bash
# Build React widget
docker-compose exec wordpress npm run build

# Install PHP dependencies
docker-compose exec wordpress composer install
```

---

## 🏗️ Project Structure

```
woo-ai-assistant/
├── 🐳 docker/                     # Docker configuration
│   ├── wordpress/                 # WordPress container setup
│   ├── mysql/                     # MySQL container setup  
│   ├── node/                      # Node.js development
│   └── test/                      # Testing environment
├── 📜 scripts/                    # Automation scripts
│   ├── docker-setup.sh            # Environment setup
│   ├── docker-reset.sh            # Complete reset
│   └── docker-test.sh             # Testing runner
├── 🔧 src/                        # PHP backend (PSR-4)
│   ├── Setup/                     # Installation & lifecycle
│   ├── KnowledgeBase/             # Content indexing
│   ├── Chatbot/                   # AI conversation logic
│   ├── Admin/                     # WordPress admin
│   └── Frontend/                  # Public integration
├── ⚛️ widget-src/                 # React frontend source
│   ├── components/                # React components
│   ├── hooks/                     # Custom hooks
│   └── services/                  # API services
├── 📦 assets/                     # Compiled assets
├── 🧪 tests/                      # Test suites
├── 🐳 docker-compose.yml          # Docker services
├── 🔧 .env.docker                 # Environment template
└── 📚 docs/                       # Documentation
```

---

## 🧪 Testing

### Automated Testing
The project includes comprehensive testing with Docker:

```bash
# Run all tests
./scripts/docker-test.sh

# Specific test types
./scripts/docker-test.sh php      # PHP/PHPUnit tests
./scripts/docker-test.sh js       # JavaScript/Jest tests
./scripts/docker-test.sh quality  # Code quality gates
```

### Test Coverage
- **PHP Backend**: PHPUnit with WordPress testing framework
- **React Frontend**: Jest with React Testing Library
- **Integration**: Full WordPress + WooCommerce integration tests
- **Quality Gates**: PSR-12, PHPStan, ESLint compliance

---

## 📚 Documentation

| Document | Purpose |
|----------|---------|
| [DOCKER_GUIDE.md](./DOCKER_GUIDE.md) | Complete Docker usage guide |
| [DEVELOPMENT_GUIDE.md](./DEVELOPMENT_GUIDE.md) | Development workflow and standards |
| [CLAUDE.md](./CLAUDE.md) | Coding standards and AI agent workflows |
| [ROADMAP.md](./ROADMAP.md) | Project tasks and progress tracking |
| [ARCHITETTURA.md](./ARCHITETTURA.md) | System architecture and design |
| [TESTING_STRATEGY.md](./TESTING_STRATEGY.md) | Progressive testing approach |

---

## 🔧 Configuration

### Environment Variables
Edit `.env` file (created during setup):

```env
# API Keys (Development)
OPENROUTER_API_KEY=your_key_here
OPENAI_API_KEY=your_key_here  
PINECONE_API_KEY=your_key_here

# Development Settings
WOO_AI_DEVELOPMENT_MODE=true
WOO_AI_ASSISTANT_DEBUG=true
```

### WordPress Configuration
Development constants are automatically set:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WOO_AI_ASSISTANT_DEBUG', true);
define('WOO_AI_DEVELOPMENT_MODE', true);
```

---

## 🆘 Troubleshooting

### Common Issues

#### Environment Won't Start
```bash
# Check Docker is running
docker info

# Reset everything
./scripts/docker-reset.sh
```

#### Plugin Not Working
```bash
# Check logs
docker-compose logs -f wordpress

# Reactivate plugin
docker-compose exec wordpress wp --allow-root plugin activate woo-ai-assistant
```

#### Database Issues
```bash
# Access phpMyAdmin
open http://localhost:8081

# Check database connection
docker-compose exec wordpress wp --allow-root db check
```

### Getting Help
1. Check [DOCKER_GUIDE.md](./DOCKER_GUIDE.md) troubleshooting section
2. Review container logs: `docker-compose logs [service]`
3. Try complete reset: `./scripts/docker-reset.sh`
4. Check [GitHub Issues](https://github.com/woo-ai-assistant/woo-ai-assistant/issues)

---

## 🏢 Production vs Development

### Development (Docker/MAMP)
- Direct API calls using `.env` keys
- Debug logging enabled
- Sample data auto-created
- Hot reload for React development

### Production (WordPress.org)
- License key-based authentication
- API calls via intermediate server (EU)
- Optimized performance
- Analytics and billing integration

---

## 🤝 Contributing

### Development Setup
1. Fork the repository
2. Run `./scripts/docker-setup.sh`
3. Create feature branch: `git checkout -b feature/my-feature`
4. Make changes and test: `./scripts/docker-test.sh`
5. Submit pull request

### Code Standards
- **PHP**: PSR-12 compliance, WordPress standards
- **JavaScript**: ESLint + Prettier configuration
- **Testing**: Minimum 80% coverage required
- **Documentation**: Update relevant .md files

---

## 📄 License

This project is licensed under the GPL v2 or later - see the [LICENSE](./LICENSE) file for details.

---

## 🙏 Acknowledgments

- **WordPress Community**: For the amazing ecosystem
- **WooCommerce Team**: For the e-commerce platform
- **Docker**: For containerization technology
- **OpenRouter**: For AI model access
- **Contributors**: All developers who contribute to this project

---

## 📞 Support

- **Documentation**: Check the `docs/` directory
- **Issues**: [GitHub Issues](https://github.com/woo-ai-assistant/woo-ai-assistant/issues)
- **Discussions**: [GitHub Discussions](https://github.com/woo-ai-assistant/woo-ai-assistant/discussions)

---

**🚀 Ready to start? Run `./scripts/docker-setup.sh` and begin developing!**

---

*Generated with ❤️ by the Woo AI Assistant team*