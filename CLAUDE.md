# CLAUDE.md - Development Guidelines for Woo AI Assistant Plugin

## 📋 Project Overview

**Project Name:** Woo AI Assistant  
**Version:** 1.0  
**Description:** AI-powered chatbot for WooCommerce that automatically creates a knowledge base from site content and provides 24/7 customer support with advanced purchase assistance.  
**Development Environment:** macOS with MAMP (PHP 8.2.20, Apache port 8888, MySQL port 8889)  

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
For **EVERY** task you perform on this project, you **MUST**:

1. **Follow the roadmap strictly** - All development must follow the task sequence in `ROADMAP.md`
2. **Update roadmap after each task** - Mark tasks as in_progress when starting, completed when finished, and update all relevant fields (dates, status, notes, dependencies)
3. **Write comprehensive documentation** explaining what was implemented and why
4. **Create unit tests** that verify the functionality works as specified
5. **Update relevant documentation files** to reflect changes
6. **Follow all coding standards** outlined in this document
7. **Ensure tests verify adherence** to naming conventions and best practices

### Roadmap Management
- **Before starting any task:** Mark it as "in_progress" with start date in `ROADMAP.md`
- **During development:** Update progress notes and any issues encountered
- **After completing task:** Mark as "completed" with completion date, update progress summary
- **Dependencies:** Never start a task until all its dependencies are completed
- **File tracking:** Update the "File Coverage Checklist" section when files are created
- **Bug tracking:** Log any bugs discovered in the Bug Tracker section

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
   - Update start date

2. 🔧 wp-backend-developer OR ⚛️ react-frontend-specialist
   - Implement the actual functionality
   - Follow coding standards exactly
   - Create comprehensive documentation

3. ✅ qa-testing-specialist (MANDATORY)
   - Execute ALL quality gates
   - Run unit tests with >90% coverage
   - Verify naming conventions
   - Test WordPress/WooCommerce integration
   - ONLY proceed if ALL checks pass

4. 📋 roadmap-project-manager
   - Mark task as "completed" (ONLY after QA passes)
   - Update completion date and notes
   - Update File Coverage Checklist
   - Recalculate progress metrics
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

---

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

### macOS MAMP Configuration

#### Required Versions
- **PHP:** 8.2.20 (already configured in MAMP)
- **Apache:** Port 8888
- **MySQL:** Port 8889
- **WordPress:** Latest stable
- **WooCommerce:** Latest stable

#### Environment Variables for Development
Create a `.env` file in the plugin root:
```env
# Development API Keys (TEMPORARY - for testing only)
OPENROUTER_API_KEY=your_openrouter_key_here
STRIPE_SECRET_KEY=sk_test_your_stripe_test_key
STRIPE_PUBLISHABLE_KEY=pk_test_your_stripe_test_key

# Development Settings
WOO_AI_DEBUG=true
WOO_AI_CACHE_TTL=60
WOO_AI_USE_DUMMY_EMBEDDINGS=true

# MAMP Configuration
DB_HOST=localhost:8889
WP_HOME=http://localhost:8888/wp
WP_SITEURL=http://localhost:8888/wp
```

#### wp-config.php Additions for Development
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

## 🎯 MANDATORY Quality Assurance Process

**CRITICAL:** This section defines MANDATORY steps that must be completed before marking ANY task as completed.

### 🚨 ABSOLUTE ENFORCEMENT RULES

**🚫 TASK COMPLETION IS FORBIDDEN UNTIL ALL QUALITY GATES PASS**

#### **Rule #1: Zero Tolerance Policy**
- If **ANY** quality gate fails, task completion is **STRICTLY FORBIDDEN**
- No exceptions, no partial completion, no "we'll fix it later"
- Task remains "in_progress" until ALL gates pass

#### **Rule #2: Mandatory Sequence**
1. **ALWAYS** run `composer run verify-all` before any completion attempt
2. **ALWAYS** check exit code - if non-zero, **STOP IMMEDIATELY**
3. **ALWAYS** fix ALL errors before proceeding
4. **NEVER** mark task as completed with failing quality gates

#### **Rule #3: Quality Gates Verification Commands**
```bash
# THIS COMMAND MUST RETURN EXIT CODE 0 (success) BEFORE TASK COMPLETION
composer run verify-all

# IF THE ABOVE FAILS, RUN INDIVIDUAL CHECKS:
composer run verify-paths        # Must pass
composer run verify-standards   # Must pass  
composer run phpcs              # Must pass
composer run phpstan            # Must pass
composer run test               # Must pass with >90% coverage
```

#### **Rule #4: Agent Responsibility**
- **qa-testing-specialist agent**: MUST run quality gates and **REFUSE** to approve if any fail
- **roadmap-project-manager agent**: MUST NOT mark completed until QA explicitly approves
- **ALL agents**: MUST check quality gates before claiming task completion

#### **Rule #5: Physical Enforcement Mechanism**
A `.quality-gates-status` file is created to track quality gates status:
- **GREEN** = All gates passed, task completion allowed
- **RED** = Gates failed, task completion BLOCKED
- **YELLOW** = Gates not run yet, task completion BLOCKED

```bash
# Only mark task completed if this file exists and contains "PASSED"
cat .quality-gates-status
# Must output: "QUALITY_GATES_STATUS=PASSED" for task completion
```

### 🚨 Pre-Completion Requirements

Before marking any task as completed, you **MUST**:

1. **Run Automated Verification Scripts** (see templates below)
2. **Execute All Unit Tests** and ensure >90% coverage  
3. **Verify All Standards Compliance** using the provided checklists
4. **Test File Existence and Paths** to prevent missing class/file errors
5. **Run WordPress/WooCommerce Integration Tests**

### 🔧 Automated Verification Scripts

#### PHP Standards Verification Script
Create and run this script for every PHP file:

```php
<?php
/**
 * Standards Verification Script
 * Run this before completing any task
 */

// Check class naming (PascalCase)
function verifyClassNames($filePath) {
    $content = file_get_contents($filePath);
    preg_match_all('/class\s+([a-zA-Z_][a-zA-Z0-9_]*)/', $content, $matches);
    
    foreach ($matches[1] as $className) {
        if (!preg_match('/^[A-Z][a-zA-Z0-9]*$/', $className)) {
            throw new Exception("Class '$className' in $filePath does not follow PascalCase");
        }
    }
}

// Check method naming (camelCase)  
function verifyMethodNames($filePath) {
    $content = file_get_contents($filePath);
    preg_match_all('/(?:public|private|protected)\s+function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $content, $matches);
    
    foreach ($matches[1] as $methodName) {
        if ($methodName === '__construct' || strpos($methodName, '__') === 0) continue;
        
        if (!preg_match('/^[a-z][a-zA-Z0-9]*$/', $methodName)) {
            throw new Exception("Method '$methodName' in $filePath does not follow camelCase");
        }
    }
}

// Check file exists and is readable
function verifyFileExists($filePath) {
    if (!file_exists($filePath) || !is_readable($filePath)) {
        throw new Exception("File $filePath does not exist or is not readable");
    }
}

// Usage example:
// verifyFileExists('/path/to/file.php');
// verifyClassNames('/path/to/file.php'); 
// verifyMethodNames('/path/to/file.php');
```

#### File Path Verification Script
Run this to verify all referenced files exist:

```bash
#!/bin/bash
# verify-paths.sh - Run before completing any task

echo "🔍 Verifying all file paths and classes exist..."

# Check that all require/include paths exist
find src/ -name "*.php" -exec grep -l "require\|include" {} \; | while read file; do
    grep -o "require[^;]*\|include[^;]*" "$file" | while read line; do
        path=$(echo "$line" | grep -o "'[^']*'\|\"[^\"]*\"" | tr -d "\"'")
        if [[ ! -f "$path" && ! -f "src/$path" ]]; then
            echo "❌ Missing file: $path referenced in $file"
            exit 1
        fi
    done
done

# Check that all class references have corresponding files
find src/ -name "*.php" -exec grep -l "new \|use \|extends \|implements " {} \; | while read file; do
    # Extract class names and verify PSR-4 structure
    # This would need to be customized based on actual namespace structure
    echo "✅ $file verified"
done

echo "✅ All file paths verified"
```

### 🧪 Mandatory Unit Test Templates

#### PHP Class Testing Template
Every PHP class MUST have tests following this template:

```php
<?php
/**
 * Test Template - Copy this for every new class
 */
class [ClassName]Test extends WP_UnitTestCase {
    
    private $instance;
    
    public function setUp(): void {
        parent::setUp();
        $this->instance = new [ClassName]();
    }
    
    // MANDATORY: Test class existence and basic instantiation
    public function test_class_exists_and_instantiates() {
        $this->assertTrue(class_exists('[ClassName]'));
        $this->assertInstanceOf('[ClassName]', $this->instance);
    }
    
    // MANDATORY: Verify naming conventions
    public function test_class_follows_naming_conventions() {
        $reflection = new ReflectionClass($this->instance);
        
        // Class name must be PascalCase
        $className = $reflection->getShortName();
        $this->assertMatchesRegularExpression('/^[A-Z][a-zA-Z0-9]*$/', $className, 
            "Class name '$className' must be PascalCase");
            
        // All public methods must be camelCase
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            $methodName = $method->getName();
            if (strpos($methodName, '__') === 0) continue; // Skip magic methods
            
            $this->assertMatchesRegularExpression('/^[a-z][a-zA-Z0-9]*$/', $methodName,
                "Method '$methodName' must be camelCase");
        }
    }
    
    // MANDATORY: Test each public method exists and returns expected type
    public function test_public_methods_exist_and_return_correct_types() {
        $reflection = new ReflectionClass($this->instance);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        
        foreach ($methods as $method) {
            $methodName = $method->getName();
            if (strpos($methodName, '__') === 0) continue; // Skip magic methods
            
            $this->assertTrue(method_exists($this->instance, $methodName),
                "Method $methodName should exist");
            
            // Add specific return type checks based on your method documentation
        }
    }
    
    // TODO: Add specific functional tests for each method
    // public function test_[methodName]_should_[expected_behavior]_when_[condition]()
}
```

### 📋 Detailed Quality Checklist

**EVERY SINGLE ITEM** must be verified before task completion:

#### File and Path Verification
- [ ] **All referenced files exist** (run verify-paths.sh script)
- [ ] **All class names match file names** exactly (case sensitive)
- [ ] **All `use` statements** point to existing classes
- [ ] **All `require/include` statements** point to existing files
- [ ] **PSR-4 autoloading paths** are correct

#### PHP Standards Compliance  
- [ ] **All classes** follow PascalCase naming (verified by script)
- [ ] **All methods** follow camelCase naming (verified by script)
- [ ] **All variables** follow camelCase naming
- [ ] **All constants** are UPPER_SNAKE_CASE
- [ ] **Database tables** use woo_ai_ prefix
- [ ] **Database columns** use snake_case
- [ ] **WordPress hooks** use woo_ai_assistant_ prefix with underscores

#### Documentation and Security
- [ ] **All functions have proper DocBlocks** with @param, @return, @throws
- [ ] **Input is properly sanitized** using WordPress functions
- [ ] **Nonce verification** is implemented for all forms/AJAX
- [ ] **Capability checks** are in place for all admin functions
- [ ] **No hardcoded credentials** (use .env for development)
- [ ] **Error handling** is implemented with try/catch where needed
- [ ] **Logging** is properly implemented using error_log()

#### Testing Requirements
- [ ] **Unit tests cover all public methods** (>90% coverage required)
- [ ] **Naming convention tests** are included (using template above)
- [ ] **All tests pass** without errors or warnings  
- [ ] **Integration tests** verify WordPress/WooCommerce compatibility
- [ ] **Code passes all linting** (phpcs, eslint)

#### WordPress/WooCommerce Integration
- [ ] **Plugin loads without errors** in fresh WordPress install
- [ ] **Activation/deactivation hooks** work correctly
- [ ] **Database tables** are created properly on activation
- [ ] **No conflicts** with WooCommerce core functions
- [ ] **Proper enqueueing** of scripts and styles

### 🛠 Automated Quality Gates Implementation

The quality gates are now enforced by a physical blocking mechanism:

#### Required Commands for Task Completion:
```bash
# 🚨 MANDATORY: Run this before attempting ANY task completion
composer run quality-gates-enforce

# Check current completion status
composer run quality-gates-check

# Reset status (use only when necessary)  
composer run quality-gates-reset

# Traditional verify-all now runs the enforcer
composer run verify-all
```

#### Physical Status File:
The system creates `.quality-gates-status` file:
- **MUST contain "QUALITY_GATES_STATUS=PASSED" for task completion**
- File is automatically managed by the enforcer
- Task completion is **PHYSICALLY BLOCKED** without this file

### 🚨 Failure Protocol

**If ANY verification fails:**
1. **DO NOT mark task as completed**
2. **Fix all issues immediately** 
3. **Re-run verification** until all checks pass
4. **Update tests** if needed to maintain coverage
5. **Only then** mark task as completed

**Remember: A task is NOT completed until ALL quality gates pass.**

### 🤖 AGENT-SPECIFIC ENFORCEMENT INSTRUCTIONS

#### **For qa-testing-specialist Agent:**
```bash
# MANDATORY: Always run this command first
composer run quality-gates-enforce

# If command fails (non-zero exit code):
# 1. Report ALL failures to user
# 2. REFUSE to approve task completion
# 3. List specific steps to fix failures
# 4. Do NOT proceed until ALL gates pass

# If command succeeds:
# 1. Verify .quality-gates-status contains "PASSED"
# 2. Only then approve task completion
```

#### **For roadmap-project-manager Agent:**
```bash
# BEFORE marking any task as completed:
composer run quality-gates-check

# Only mark completed if:
# 1. Command returns exit code 0
# 2. qa-testing-specialist has explicitly approved
# 3. .quality-gates-status file exists and shows "PASSED"
```

#### **For ALL Development Agents:**
- **NEVER** claim task completion without QA verification
- **ALWAYS** run `composer run quality-gates-check` before completion claims
- **STOP** immediately if quality gates fail
- **FIX** all failures before proceeding

### 🎯 SUMMARY OF NEW ENFORCEMENT SYSTEM

✅ **Physical blocking mechanism** prevents completion with failed gates  
✅ **Clear status file** shows exact gate status  
✅ **Agent-specific instructions** define responsibility  
✅ **Zero tolerance policy** for quality gate failures  
✅ **Automatic verification** of all standards and tests

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

## 🗺️ Development Workflow

### MANDATORY: Roadmap-Driven Development
**CRITICAL:** All development MUST follow this exact workflow:

1. **Check roadmap:** Always start by reading `ROADMAP.md` to see current status
2. **Select next task:** Only work on tasks marked as "TO DO" with completed dependencies  
3. **Update status:** Mark task as "in_progress" with start date before coding
4. **Follow specifications:** Implement exactly what's described in task requirements
5. **Test thoroughly:** Create and run unit tests for all new functionality
6. **🚨 RUN MANDATORY QUALITY GATES:** Execute all verification scripts and checklists
7. **Update roadmap:** Mark task as "completed" with completion date and notes ONLY after all quality gates pass
8. **Update progress:** Recalculate and update the Progress Summary section

### 🔒 ENHANCED Pre-Completion Protocol

**BEFORE marking any task as completed, you MUST execute this exact sequence:**

```bash
# 1. Verify all files exist and paths are correct
bash scripts/verify-paths.sh

# 2. Run standards verification on all modified files  
php scripts/verify-standards.php

# 3. Execute all unit tests with coverage requirement
composer run test:coverage

# 4. Run linting and code analysis
composer run phpcs
composer run phpstan  

# 5. Test plugin activation/deactivation
# (Manual verification in WordPress admin)

# 6. Verify integration works without errors
# (Manual verification of WordPress/WooCommerce integration)
```

**If ANY step fails → DO NOT mark task as completed**

### Roadmap Update Template
When completing a task, update these sections in `ROADMAP.md`:
```
#### Task X.Y: [Task Name] ✅
*Status: COMPLETED*
*Started: YYYY-MM-DD*
*Completed: YYYY-MM-DD*
- [x] All checklist items
- **Output:** [What was delivered]
- **Dependencies:** [Prerequisites met]
- **Notes:** [Implementation notes, challenges, decisions]
```

### Quality Gates
Before marking any task as completed:
- [ ] All code follows naming conventions
- [ ] Unit tests pass with >90% coverage  
- [ ] Documentation is complete and accurate
- [ ] No security vulnerabilities introduced
- [ ] Performance requirements met
- [ ] Roadmap is fully updated

---

---

## 🎯 ENHANCED USER PROMPT TEMPLATE

**To ensure adherence to these quality standards, use this enhanced prompt:**

```
Sono uno sviluppatore che lavora sul plugin WordPress "Woo AI Assistant" - 
un chatbot AI per WooCommerce.

La cartella contiene 5 file di documentazione completa:
- ROADMAP.md - Task corrente e progressione
- CLAUDE.md - Standard di sviluppo e convenzioni  
- ARCHITETTURA.md - Struttura file e componenti
- docs/specifications/PROJECT_SPECIFICATIONS.md - Specifiche funzionali
- README.md - Setup e comandi

WORKFLOW OBBLIGATORIO CON AGENTI:
1. **Leggi TUTTI i 5 file** per avere il contesto completo del progetto
2. **USA roadmap-project-manager** per identificare il prossimo task "TO DO" disponibile
3. **USA roadmap-project-manager** per marcare task come "in_progress"
4. **USA l'agente specializzato corretto**:
   - wp-backend-developer per classi PHP e integrazione WordPress/WooCommerce
   - react-frontend-specialist per componenti React e widget frontend
5. **USA SEMPRE qa-testing-specialist** prima di completare qualsiasi task (OBBLIGATORIO)
6. **USA roadmap-project-manager** per marcare task come "completed" SOLO dopo che QA passa
7. Usa TodoWrite per tracciare sub-task durante lo sviluppo

SEQUENZA AGENTI OBBLIGATORIA:
1. 📋 roadmap-project-manager (mark in_progress)
2. 🔧 wp-backend-developer OR ⚛️ react-frontend-specialist (implement)
3. ✅ qa-testing-specialist (MANDATORY quality gates)
4. 📋 roadmap-project-manager (mark completed ONLY if QA passes)

QUALITY GATES OBBLIGATORI (da CLAUDE.md):
- Esegui script di verifica standards
- Verifica esistenza di tutti i file e path  
- Esegui tutti i unit test con copertura >90%
- Verifica naming conventions automaticamente
- Test integrazione WordPress/WooCommerce

Ambiente: macOS MAMP (dettagli in CLAUDE.md)
Procedi solo dopo aver letto tutti i file di documentazione E aver confermato che seguirai rigorosamente tutti i quality gates.
```

**This enhanced prompt explicitly reminds about quality gates and mandatory verification steps.**

---

**Remember:** This project's success depends on maintaining high code quality, comprehensive testing, thorough documentation, and **strict adherence to the roadmap AND quality gates**. Every line of code should follow these standards to ensure maintainability and reliability.

🤖 **Generated with Claude Code**