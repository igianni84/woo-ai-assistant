# CLAUDE.md - Development Guidelines for Woo AI Assistant Plugin

## 📋 Project Overview

**Project Name:** Woo AI Assistant  
**Version:** 1.0  
**Description:** AI-powered chatbot for WooCommerce with zero-config knowledge base and 24/7 customer support.  
**Development Environment:** macOS with MAMP (PHP 8.2.20, Apache port 8888, MySQL port 8889)  
**Architecture:** See `ARCHITETTURA.md` for detailed system design  
**Roadmap:** See `ROADMAP.md` for task progression and status  

## 🎯 Core Development Principles

### Zero-Config Philosophy
Every feature must work immediately after plugin activation without manual configuration. This is our primary competitive advantage - "Niente frizioni" (No friction).

### ⚠️ NEVER ASSUME RULE
**CRITICAL:** You must NEVER assume or presume anything during development. Always verify facts by:
1. **Reading the relevant files** to confirm information exists
2. **Checking documentation** to verify specifications  
3. **Asking the user** when you have doubts or need clarification
4. **Stopping work** if you cannot verify something and cannot proceed safely

If you find yourself using words like "presumably", "probably", "should be", or "likely" - STOP and verify first.

### Task Requirements
For **EVERY** task, follow the mandatory workflow in the Specialized Agents section below.

## 🤖 SPECIALIZED AGENTS WORKFLOW

> **CRITICAL:** This project uses specialized AI agents for different development tasks. You MUST use the correct agent for each type of work.

### 🎯 Agent Selection Rules

#### **wp-backend-developer** 🔧
**Use for:** All PHP backend development and WordPress/WooCommerce integration

**Trigger Keywords:** PHP class, WooCommerce, WordPress hooks, database, PSR-4, backend, API endpoint, REST controller
**File Extensions:** `.php` files in `src/` directory
**Example Tasks:**
- Task 0.1: Plugin Skeleton (Main.php, Activator.php, etc.)
- Task 1.x: Core Infrastructure (AdminMenu.php, RestController.php, Database schema)
- Task 2.x: Knowledge Base Core (Scanner.php, Indexer.php, VectorManager.php, AIManager.php)
- Task 3.x: Server Integration (IntermediateServerClient.php, LicenseManager.php)
- Task 5.x: Chat Logic & AI (ConversationHandler.php, ChatEndpoint.php)
- Task 6.x-8.x: Advanced Features (CouponHandler.php, ProactiveTriggers.php, etc.)

**Always use when:**
- Creating or modifying PHP classes
- Implementing WordPress hooks and filters
- Working with WooCommerce integration
- Database operations and schema changes
- REST API endpoint development

#### **react-frontend-specialist** ⚛️
**Use for:** All React frontend development for the chat widget

**Trigger Keywords:** React, widget, frontend, JavaScript, chat interface, components, hooks
**File Extensions:** `.js`, `.jsx` files in `widget-src/` directory
**Example Tasks:**
- Task 4.1: React Widget Base (App.js, index.js)
- Task 4.2: Chat Components (ChatWindow.js, Message.js)
- Task 4.3: API Service Layer (ApiService.js)
- Task 4.4: Product Cards & Actions (ProductCard.js, QuickAction.js)
- Task 4.5: Widget Loader (WidgetLoader.php integration with React)

**Always use when:**
- Creating or modifying React components
- Managing state and hooks (useChat.js)
- Implementing chat UI and user interactions
- API service layer for frontend-backend communication
- Widget styling and responsive design

#### **qa-testing-specialist** ✅
**Use for:** MANDATORY quality assurance before completing ANY task

**Trigger Keywords:** completed, finished, quality gates, testing, verification, standards
**Required for:** EVERY SINGLE TASK before marking as completed
**Example Usage:**
- After implementing KnowledgeBaseScanner class
- Before marking any task as "completed" in roadmap
- When running comprehensive test suites
- Verifying code standards compliance
- Pre-deployment verification

**MANDATORY use when:**
- Running quality gates verification (./scripts/mandatory-verification.sh)
- Executing unit tests with coverage requirements
- Verifying naming conventions and coding standards
- Testing WordPress/WooCommerce integration
- Checking file paths and class loading

#### **roadmap-project-manager** 📋
**Use for:** Managing project roadmap, tracking progress, coordinating tasks

**Trigger Keywords:** roadmap, task status, next task, dependencies, progress, milestone
**Required for:** Task coordination and project management
**Example Usage:**
- Before starting any new task (mark as in_progress)
- After completing a task (mark as completed)
- When updating progress summaries
- Managing task dependencies
- Tracking bugs and issues

**Always use when:**
- Starting a new task (update ROADMAP.md status)
- Completing a task (only after QA passes)
- Checking task dependencies
- Updating project progress metrics
- Managing the File Coverage Checklist

### 🔄 MANDATORY WORKFLOW SEQUENCE

For **EVERY** task, follow this exact sequence:

```
1. 📋 roadmap-project-manager
   - Mark task as "in_progress" in ROADMAP.md
   - Verify all dependencies are completed

2. 🔧 wp-backend-developer OR ⚛️ react-frontend-specialist
   - Implement the functionality per task specifications
   - Follow coding standards (see below)

3. ✅ qa-testing-specialist (MANDATORY)
   - Run: composer run quality-gates-enforce
   - Must see: "QUALITY_GATES_STATUS=PASSED"
   - Fix any failures and re-run until passed

4. 📋 roadmap-project-manager
   - Mark task as "completed" ONLY after QA passes
   - Update completion date and progress metrics
```

### ⚠️ CRITICAL RULES

1. **NEVER mark a task completed without qa-testing-specialist approval**
2. **ALWAYS use roadmap-project-manager for status updates**
3. **Use the correct specialist agent based on file type and task nature**
4. **Backend tasks = wp-backend-developer, Frontend tasks = react-frontend-specialist**
5. **Every task completion = qa-testing-specialist verification**

### 🎯 Agent Usage by Task Phase

| Task Phase | Primary Agent | Secondary Agent | QA Required |
|------------|---------------|----------------|-------------|
| **0.x Foundation** | wp-backend-developer | roadmap-project-manager | ✅ |
| **1.x Core Infrastructure** | wp-backend-developer | roadmap-project-manager | ✅ |
| **2.x Knowledge Base** | wp-backend-developer | roadmap-project-manager | ✅ |
| **3.x Server Integration** | wp-backend-developer | roadmap-project-manager | ✅ |
| **4.x Widget Frontend** | react-frontend-specialist | roadmap-project-manager | ✅ |
| **5.x Chat Logic** | wp-backend-developer + react-frontend-specialist | roadmap-project-manager | ✅ |
| **6.x+ Advanced Features** | wp-backend-developer + react-frontend-specialist | roadmap-project-manager | ✅ |



## 🏗️ Architecture Overview

> **📋 Complete Architecture:** For detailed architectural design, component relationships, data flow diagrams, and system specifications, see `ARCHITETTURA.md`.

### Core Structure
```
woo-ai-assistant/
├── woo-ai-assistant.php        # Main plugin file
├── src/Main.php                # Singleton orchestrator
├── src/                        # PHP backend (PSR-4: WooAiAssistant\)
│   ├── Setup/                  # Installation & lifecycle
│   ├── KnowledgeBase/          # Content scanning & indexing
│   ├── Chatbot/                # Chat logic & conversation handling
│   ├── Admin/                  # WordPress admin interface
│   └── Frontend/               # Public site integration
├── widget-src/                 # React frontend source
└── assets/                     # Compiled frontend assets
```

### Technology Stack
- **Backend:** PHP 8.2+, WordPress 6.0+, WooCommerce 7.0+
- **Frontend:** React 18+, Webpack 5
- **AI Models:** OpenRouter (Gemini 2.5 Flash/Pro)
- **Vector DB:** Pinecone via intermediate server
- **Embeddings:** OpenAI text-embedding-3-small
- **Payments:** Stripe integration
- **Development Server:** MAMP (Apache 8888, MySQL 8889)

## 📝 Coding Standards

### PHP Standards (PSR-12 Extended)

#### Class Naming Conventions
```php
// ✅ Correct
class KnowledgeBaseScanner
class ProductIndexer
class ConversationHandler

// ❌ Wrong
class kb_scanner
class productindexer
class conversation_Handler
```

#### Method Naming Conventions
```php
// ✅ Correct - Actions/Commands (verbs)
public function scanProducts()
public function indexContent()
public function handleConversation()
public function validateCoupon()

// ✅ Correct - Queries (is/has/get/can)
public function isValid()
public function hasPermission()
public function getProductData()
public function canApplyCoupon()

// ❌ Wrong
public function products() // Too vague
public function validation() // Should be validateSomething()
public function permission() // Should be hasPermission()
```

#### Variable Naming Conventions
```php
// ✅ Correct
$productId = 123;
$conversationData = [];
$isValidCoupon = true;
$totalPrice = 99.99;

// ❌ Wrong
$pid = 123;
$conv_data = [];
$valid = true;
$price = 99.99;
```

#### Constants
```php
// ✅ Correct
const MAX_CONVERSATIONS_PER_MONTH = 100;
const KNOWLEDGE_BASE_CHUNK_SIZE = 1000;
const AI_MODEL_GEMINI_FLASH = 'gemini-2.5-flash';

// ❌ Wrong
const max_conversations = 100;
const ChunkSize = 1000;
const aiModel = 'gemini';
```

#### Database Table and Column Names
```php
// ✅ Correct table names (prefixed with woo_ai_)
$table_name = $wpdb->prefix . 'woo_ai_conversations';
$table_name = $wpdb->prefix . 'woo_ai_knowledge_base';

// ✅ Correct column names (snake_case)
'conversation_id', 'user_id', 'created_at', 'message_content'

// ❌ Wrong
'conversationId', 'ConversationID', 'messagecontent'
```

#### WordPress Hook Naming
```php
// ✅ Correct
do_action('woo_ai_assistant_before_index', $content_type);
apply_filters('woo_ai_assistant_kb_content', $content, $post_id);

// ❌ Wrong
do_action('wooai_index'); // Too short
apply_filters('woo-ai-content'); // Hyphens instead of underscores
```

### JavaScript/React Standards

#### Component Naming (PascalCase)
```jsx
// ✅ Correct
const ChatWindow = () => {};
const ProductCard = () => {};
const MessageBubble = () => {};

// ❌ Wrong
const chatWindow = () => {};
const product_card = () => {};
const messagebubble = () => {};
```

#### Variable and Function Naming (camelCase)
```javascript
// ✅ Correct
const conversationId = 123;
const isTyping = false;
const handleMessageSend = () => {};
const validateUserInput = () => {};

// ❌ Wrong
const conversation_id = 123;
const IsTyping = false;
const HandleMessageSend = () => {};
```

#### File Naming
```
// ✅ Correct
ChatWindow.js
ProductCard.js
useChat.js
ApiService.js

// ❌ Wrong
chatwindow.js
product-card.js
use_chat.js
apiservice.js
```

## 🧪 Unit Testing Standards

### PHP Testing with PHPUnit

#### Test Class Naming
```php
// ✅ Correct
class KnowledgeBaseScannerTest extends WP_UnitTestCase
class CouponHandlerTest extends WP_UnitTestCase

// ❌ Wrong
class TestKnowledgeBaseScanner
class KB_Scanner_Test
```

#### Test Method Naming
```php
// ✅ Correct - Descriptive and follows pattern
public function test_scanProducts_should_return_array_when_products_exist()
public function test_validateCoupon_should_return_false_when_coupon_expired()
public function test_indexContent_should_throw_exception_when_content_empty()

// ❌ Wrong
public function testScanProducts()
public function test_validation()
public function testStuff()
```

#### Required Test Categories
Every class must have tests for:

1. **Happy Path Tests** - Normal operation
2. **Edge Case Tests** - Boundary conditions
3. **Error Handling Tests** - Exception scenarios
4. **Integration Tests** - WordPress/WooCommerce integration
5. **Naming Convention Tests** - Verify adherence to standards

#### Example Test Structure
```php
class KnowledgeBaseScannerTest extends WP_UnitTestCase {
    
    private $scanner;
    
    public function setUp(): void {
        parent::setUp();
        $this->scanner = new KnowledgeBaseScanner();
    }
    
    public function test_scanProducts_should_return_array_when_products_exist() {
        // Arrange
        $product = $this->factory->post->create(['post_type' => 'product']);
        
        // Act
        $result = $this->scanner->scanProducts();
        
        // Assert
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }
    
    public function test_method_names_follow_camelCase_convention() {
        // Verify all public methods follow camelCase
        $reflection = new ReflectionClass($this->scanner);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        
        foreach ($methods as $method) {
            $methodName = $method->getName();
            // Skip magic methods and constructors
            if (strpos($methodName, '__') === 0 || $methodName === 'setUp') {
                continue;
            }
            
            $this->assertTrue(
                ctype_lower($methodName[0]) && !strpos($methodName, '_'),
                "Method {$methodName} should follow camelCase convention"
            );
        }
    }
}
```

### React Testing with Jest

#### Test File Naming
```
// ✅ Correct
ChatWindow.test.js
ProductCard.test.js
useChat.test.js

// ❌ Wrong
ChatWindow.spec.js
test-product-card.js
```

#### Test Structure
```javascript
describe('ChatWindow', () => {
  describe('when user sends message', () => {
    it('should call handleMessageSend with correct parameters', () => {
      // Test implementation
    });
    
    it('should clear input field after sending', () => {
      // Test implementation
    });
  });
  
  describe('naming conventions', () => {
    it('should use PascalCase for component name', () => {
      expect(ChatWindow.name).toBe('ChatWindow');
    });
  });
});
```

## 🌍 Development Environment Setup

### ⚠️ CRITICAL: Production vs Development Architecture

#### Production Architecture (What Users Get)
```
┌─────────────┐      License Key      ┌──────────────────┐      API Keys     ┌─────────────┐
│  WP Plugin  │ ──────────────────────>│ Intermediate     │ ───────────────> │ AI Services │
│  (Client)   │                        │ Server (EU)      │                  │ (OpenRouter,│
└─────────────┘                        └──────────────────┘                  │  OpenAI,    │
                                              │                               │  Pinecone)  │
                                              │                               └─────────────┘
                                              ▼
                                       ┌──────────────┐
                                       │    Stripe    │
                                       │   Billing    │
                                       └──────────────┘
```

**Key Points:**
- Plugin uses ONLY a license key (no API keys in production)
- ALL API keys are on the intermediate server
- Server manages costs, rate limiting, usage tracking
- This ensures the SaaS business model

#### Development Architecture (Local Testing)
```
┌─────────────┐      Direct API Calls     ┌─────────────┐
│  WP Plugin  │ ─────────────────────────>│ AI Services │
│  (Local)    │   (Using .env API keys)   │ (OpenRouter,│
└─────────────┘                           │  OpenAI,    │
                                          │  Pinecone)  │
                                          └─────────────┘
```

**Key Points:**
- Uses `.env` file for API keys (NEVER in production)
- Bypasses license validation
- Direct API calls for testing
- DevelopmentConfig.php manages this mode

### macOS MAMP Configuration

#### Required Versions
- **PHP:** 8.2.20 (already configured in MAMP)
- **Apache:** Port 8888
- **MySQL:** Port 8889
- **WordPress:** Latest stable
- **WooCommerce:** Latest stable

#### Development Setup Steps

##### Step 1: Create Configuration File
```bash
cp .env.example .env
```

##### Step 2: Configure Environment Variables
Create a `.env` file in the plugin root:
```env
# Enable development mode
WOO_AI_DEVELOPMENT_MODE=true

# Development API Keys (TEMPORARY - for testing only)
OPENROUTER_API_KEY=your_openrouter_key_here
OPENAI_API_KEY=your_openai_key_here
PINECONE_API_KEY=your_pinecone_key_here
PINECONE_ENVIRONMENT=development
PINECONE_INDEX_NAME=woo-ai-assistant-dev

# Stripe Test Keys
STRIPE_SECRET_KEY=sk_test_your_stripe_test_key
STRIPE_PUBLISHABLE_KEY=pk_test_your_stripe_test_key

# Google/Gemini (alternative)
GOOGLE_API_KEY=your_google_key_here

# Development Settings
WOO_AI_DEBUG=true
WOO_AI_ASSISTANT_DEBUG=true
WOO_AI_CACHE_TTL=60
WOO_AI_USE_DUMMY_EMBEDDINGS=true
WOO_AI_ENHANCED_DEBUG=true

# Development Features
WOO_AI_USE_DUMMY_DATA=false
WOO_AI_MOCK_API_CALLS=false
WOO_AI_DEVELOPMENT_SERVER_URL=http://localhost:3000

# Performance Settings
WOO_AI_DEV_API_TIMEOUT=10
WOO_AI_DEV_CACHE_TTL=60
WOO_AI_DEV_MAX_ITEMS=10

# MAMP Configuration
DB_HOST=localhost:8889
WP_HOME=http://localhost:8888/wp
WP_SITEURL=http://localhost:8888/wp

# Development License (any value accepted in dev mode)
WOO_AI_DEVELOPMENT_LICENSE_KEY=dev-license-12345
```

##### Step 3: Update wp-config.php
```php
// Development constants
define('WOO_AI_ASSISTANT_DEBUG', true);
define('WOO_AI_ASSISTANT_API_URL', 'http://localhost:3000'); // When server ready
define('WOO_AI_ASSISTANT_USE_DUMMY_DATA', true);

// Enable WordPress debugging
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', true);
```

##### Step 4: Verify Configuration
```bash
php test-development-config.php
```

#### Development Mode Features

The development configuration system automatically:
- **Detects Development Environment:** Automatically detects localhost, MAMP, XAMPP, etc.
- **Bypasses License Validation:** Any license key is accepted as valid in development mode
- **Loads Development API Keys:** Uses API keys from `.env` file
- **Enables Advanced Features:** Grants Unlimited plan features for testing
- **Provides Development Logging:** Enhanced debug logging when enabled

#### Configuration Hierarchy

The system checks for configuration in this order:
1. **Development Environment Variables** (highest priority in dev mode)
2. **Regular Environment Variables**
3. **WordPress Admin Settings**
4. **Legacy Options** (for backward compatibility)

#### Security Notes

- **Never commit real API keys** - use `.env` for actual keys (file is gitignored)
- **Development mode is for local use only** - not for production
- **API keys are loaded only in development mode** - safe for production
- **License bypass only works in development** - production validation unchanged

#### Common Development Issues

##### Development Mode Not Detected
- Ensure you're running on localhost, MAMP, XAMPP, or similar
- Set `WOO_AI_DEVELOPMENT_MODE=true` explicitly in your `.env` file
- Check that WP_DEBUG is enabled in wp-config.php

##### API Keys Not Loading
- Verify the `.env` file exists and is readable
- Check file permissions
- Ensure variable names match exactly (case-sensitive)
- Review error logs for parsing issues

##### License Still Requiring Validation
- Confirm development mode is active (check admin notice)
- Clear any cached license data
- Verify ApiConfiguration is detecting development mode

## 🔧 Build and Development Commands

### PHP Commands
```bash
# Install dependencies
composer install

# Run code style checks
composer run phpcs

# Fix code style issues
composer run phpcbf

# Run static analysis
composer run phpstan

# Run unit tests
composer run test

# Run tests with coverage
composer run test:coverage
```

### Node.js Commands
```bash
# Install dependencies
npm install

# Development build with watching
npm run watch

# Production build
npm run build

# Run linting
npm run lint
npm run lint:fix

# Run tests
npm run test
npm run test:watch
npm run test:coverage

# Bundle analysis
npm run analyze
```

## 📚 Documentation Requirements

### Code Documentation Standards

#### PHP DocBlocks
```php
/**
 * Scans WooCommerce products and extracts content for knowledge base indexing.
 * 
 * This method retrieves all published products, processes their content including
 * titles, descriptions, categories, and custom attributes, then prepares them
 * for embedding generation.
 *
 * @since 1.0.0
 * @param array $args Optional. Arguments for product query filtering.
 * @param int   $args['limit'] Maximum number of products to scan. Default 100.
 * @param bool  $args['force_update'] Whether to rescan existing products. Default false.
 * 
 * @return array Array of product data formatted for indexing.
 *               Each element contains 'id', 'title', 'content', 'type', 'url'.
 * 
 * @throws InvalidArgumentException When limit is not a positive integer.
 * @throws RuntimeException When WooCommerce is not active.
 * 
 * @example
 * ```php
 * $scanner = new KnowledgeBaseScanner();
 * $products = $scanner->scanProducts(['limit' => 50, 'force_update' => true]);
 * ```
 */
public function scanProducts(array $args = []): array {
    // Implementation
}
```

#### React/JavaScript JSDoc
```javascript
/**
 * Chat window component that handles user conversations with the AI assistant.
 * 
 * @component
 * @param {Object} props - Component properties
 * @param {string} props.conversationId - Unique identifier for the conversation
 * @param {boolean} props.isVisible - Whether the chat window is visible
 * @param {Function} props.onClose - Callback when chat window is closed
 * @param {Object} props.userContext - Current user context (page, user info)
 * 
 * @returns {JSX.Element} Chat window component
 * 
 * @example
 * <ChatWindow
 *   conversationId="conv-123"
 *   isVisible={true}
 *   onClose={() => setShowChat(false)}
 *   userContext={{page: 'product', productId: 456}}
 * />
 */
const ChatWindow = ({ conversationId, isVisible, onClose, userContext }) => {
    // Implementation
};
```

### File Header Templates

#### PHP File Header
```php
<?php
/**
 * Knowledge Base Scanner Class
 *
 * Handles scanning and indexing of WooCommerce products and site content
 * for the AI-powered knowledge base.
 *
 * @package WooAiAssistant
 * @subpackage KnowledgeBase
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\KnowledgeBase;

use WooAiAssistant\Common\Utils;
use WooAiAssistant\Common\Traits\Singleton;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class KnowledgeBaseScanner
 * 
 * @since 1.0.0
 */
class KnowledgeBaseScanner {
    // Class implementation
}
```

#### React File Header
```javascript
/**
 * Chat Window Component
 * 
 * Main chat interface component that handles user conversations
 * with the AI assistant.
 * 
 * @package WooAiAssistant
 * @subpackage Components
 * @since 1.0.0
 * @author Claude Code Assistant
 */

import React, { useState, useEffect } from 'react';
import PropTypes from 'prop-types';
```

## 🔒 Security Standards

### Input Sanitization
```php
// ✅ Correct
$user_message = sanitize_textarea_field($_POST['message']);
$conversation_id = absint($_POST['conversation_id']);
$user_email = sanitize_email($_POST['email']);

// ❌ Wrong
$user_message = $_POST['message'];
$conversation_id = $_POST['conversation_id'];
```

### Nonce Verification
```php
// ✅ Correct
if (!wp_verify_nonce($_POST['nonce'], 'woo_ai_chat_action')) {
    wp_die('Security check failed');
}

// ❌ Wrong
// No nonce verification
```

### Capability Checks
```php
// ✅ Correct
if (!current_user_can('manage_woocommerce')) {
    wp_die('Insufficient permissions');
}

// ❌ Wrong
if (!is_admin()) { // Too broad
    wp_die('Not allowed');
}
```

## 🚀 Performance Standards

### Database Queries
```php
// ✅ Correct - Use prepared statements
$results = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}woo_ai_conversations WHERE user_id = %d AND created_at > %s",
    $user_id,
    $date_threshold
));

// ❌ Wrong - Direct query
$results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}woo_ai_conversations WHERE user_id = {$user_id}");
```

### Caching Implementation
```php
// ✅ Correct
$cache_key = "woo_ai_products_{$page}_{$per_page}";
$products = wp_cache_get($cache_key, 'woo_ai_assistant');

if (false === $products) {
    $products = $this->fetchProducts($page, $per_page);
    wp_cache_set($cache_key, $products, 'woo_ai_assistant', HOUR_IN_SECONDS);
}
```

### React Performance
```javascript
// ✅ Correct - Use React.memo for expensive components
const ProductCard = React.memo(({ product, onAddToCart }) => {
    // Component implementation
});

// ✅ Correct - Use useCallback for event handlers
const handleMessageSend = useCallback((message) => {
    // Handler implementation
}, [conversationId]);
```

## 📊 Monitoring and Logging

### Error Logging
```php
// ✅ Correct
if (!$result) {
    error_log('Woo AI Assistant: Failed to index product ID ' . $product_id);
    do_action('woo_ai_assistant_indexing_error', $product_id, $error);
}
```

### Debug Logging
```php
// ✅ Correct
if (defined('WOO_AI_ASSISTANT_DEBUG') && WOO_AI_ASSISTANT_DEBUG) {
    error_log('Woo AI Assistant Debug: Processing conversation ' . $conversation_id);
}
```

## 🎯 Quality Gates & Testing

### Mandatory Quality Gates
**🚫 TASK COMPLETION IS FORBIDDEN UNTIL ALL QUALITY GATES PASS**

```bash
# Run quality gates enforcement (MANDATORY before task completion)
composer run quality-gates-enforce

# Check status
cat .quality-gates-status  # Must show "QUALITY_GATES_STATUS=PASSED"
```

**Quality Gates Scripts:**
- `scripts/quality-gates-enforcer.sh` - Main enforcement script  
- `scripts/verify-paths.sh` - Verifies file paths exist
- `scripts/verify-standards.php` - Checks PHP naming conventions
- See `TESTING_STRATEGY.md` for progressive testing approach


## 🔄 Git Workflow

### Branch Naming
```
feature/kb-scanner-implementation
bugfix/conversation-handler-memory-leak
hotfix/security-vulnerability-fix
```

### Commit Messages
```
feat(kb): implement product content scanning

- Add KnowledgeBaseScanner class with product indexing
- Include unit tests for scanner functionality  
- Add documentation for scanning methods
- Implement caching for improved performance

Co-Authored-By: Claude <noreply@anthropic.com>
```

## 📞 Support and Resources

### Documentation Links
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [WooCommerce Development Guidelines](https://woocommerce.com/document/create-a-plugin/)
- [React Best Practices](https://react.dev/learn)

### Development Tools
- **IDE:** PhpStorm, VSCode with PHP extensions
- **Debugging:** Xdebug for PHP, React DevTools for frontend
- **Testing:** PHPUnit, Jest
- **Code Quality:** PHP_CodeSniffer, ESLint, Prettier

---

---

## 📦 Quick Start Guide

**For developers starting on this project:**

1. **Read Documentation:**
   - `ROADMAP.md` - Current task status and progression
   - `CLAUDE.md` - This file with development standards
   - `ARCHITETTURA.md` - System architecture and components
   - `TESTING_STRATEGY.md` - Progressive testing approach

2. **Follow the Mandatory Workflow** (see Specialized Agents section above)

3. **Run Quality Gates:**
   ```bash
   composer run quality-gates-enforce  # Must pass before task completion
   ```

4. **Key Rules:**
   - NEVER skip qa-testing-specialist agent
   - NEVER mark tasks completed if quality gates fail
   - ALWAYS follow the task sequence in ROADMAP.md
   - ALWAYS use the correct agent for each file type

---

🤖 **Generated with Claude Code**