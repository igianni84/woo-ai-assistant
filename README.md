# Woo AI Assistant

**Zero-Config AI Chatbot for WooCommerce** - Automatically creates a knowledge base from your site content and provides 24/7 customer support with advanced purchase assistance.

> **🎯 Core Value:** "Niente Frizioni" - Works immediately after activation without any manual configuration.

## 🚀 Quick Start

### Requirements
- **WordPress:** 6.0+
- **WooCommerce:** 7.0+  
- **PHP:** 8.1+
- **Memory:** 128MB minimum (256MB recommended)

### Installation
1. Upload plugin files to `/wp-content/plugins/woo-ai-assistant/`
2. Activate the plugin through WordPress admin
3. **That's it!** The plugin automatically:
   - Creates necessary database tables
   - Indexes your products and content  
   - Configures default settings
   - Shows chat widget on your site

## 🛠 Development Setup

### Environment Setup (macOS MAMP)
```bash
# Clone repository
git clone [repository-url] woo-ai-assistant
cd woo-ai-assistant

# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install

# Development build with watching
npm run watch

# Or production build
npm run build
```

### MAMP Configuration
- **Apache:** Port 8888
- **MySQL:** Port 8889  
- **PHP:** 8.2.20 (already configured)

## 📁 Project Structure

> **📋 Complete details:** See [ARCHITETTURA.md](./ARCHITETTURA.md)

```
woo-ai-assistant/
├── src/                    # PHP backend (PSR-4: WooAiAssistant\)
├── widget-src/             # React frontend source
├── assets/                 # Compiled assets
└── [config files]          # composer.json, package.json, webpack.config.js
```

## ⚡ Key Features by Plan

| Feature | Free | Pro | Unlimited |
|---------|------|-----|-----------|
| **Conversations/month** | 30 | 100 | 1000 |
| **Products indexed** | 30 | 100 | 2000 |
| **AI Model** | Gemini Flash | Gemini Flash | Gemini Pro |
| **Zero-Config Setup** | ✅ | ✅ | ✅ |
| **Proactive Triggers** | Basic | Custom | Custom |
| **Add to Cart from Chat** | ❌ | ❌ | ✅ |
| **Auto-Coupon Generation** | ❌ | ❌ | ✅ |
| **White-label** | ❌ | ❌ | ✅ |

## 🧪 Development Commands

### Build & Watch
```bash
npm run watch          # Development with hot reload
npm run build          # Production build
npm run lint           # Code linting
npm run test           # Run tests
```

### PHP Development  
```bash
composer run phpstan   # Static analysis
composer run phpcs     # Code standards check
composer run test      # PHPUnit tests
```

## 🔧 Configuration (Optional)

Access **WooCommerce > AI Assistant** for:
- Widget appearance customization
- Proactive trigger settings
- Coupon management rules  
- KB content exclusions
- GDPR compliance settings

### Development Constants
```php
// wp-config.php
define('WOO_AI_ASSISTANT_DEBUG', true);
define('WOO_AI_ASSISTANT_USE_DUMMY_DATA', true);
```

## 🚦 API Endpoints

```
POST /wp-json/woo-ai-assistant/v1/chat      # Handle chat messages
POST /wp-json/woo-ai-assistant/v1/action    # Execute bot actions  
POST /wp-json/woo-ai-assistant/v1/rating    # Conversation ratings
GET  /wp-json/woo-ai-assistant/v1/config    # Widget configuration
```

## 🐛 Troubleshooting

### Common Issues
- **Plugin won't activate:** Check PHP 8.1+ and WooCommerce active
- **Widget not showing:** Check JS console for errors, test with default theme
- **Slow indexing:** Verify WordPress cron is working, check memory limits

### Debug Mode
```php
// wp-config.php  
define('WOO_AI_ASSISTANT_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## 📚 Documentation

- **[PROJECT_SPECIFICATIONS.md](./docs/specifications/PROJECT_SPECIFICATIONS.md)** - Complete project specifications and requirements
- **[CLAUDE.md](./CLAUDE.md)** - Development guidelines, coding standards, and workflow  
- **[ROADMAP.md](./ROADMAP.md)** - Active development roadmap and task tracking
- **[ARCHITETTURA.md](./ARCHITETTURA.md)** - Detailed file structure and component architecture
- **[DEVELOPMENT_CONFIG_README.md](./docs/specifications/DEVELOPMENT_CONFIG_README.md)** - Development environment configuration guide
- **[TESTING_GUIDE.md](./docs/specifications/TESTING_GUIDE.md)** - Comprehensive testing guidelines
- **[DEPLOYMENT_CHECKLIST.md](./docs/specifications/DEPLOYMENT_CHECKLIST.md)** - Production deployment checklist

## 📄 License

GPL v2 or later. Built for the WordPress ecosystem.

---

**Ready to develop?** Follow the roadmap in [ROADMAP.md](./ROADMAP.md) starting with Task 0.1.