# 🧪 Woo AI Assistant - Progressive Testing Strategy

## 📋 Table of Contents

1. [Progressive Testing Philosophy](#progressive-testing-philosophy)
2. [Phase 0: Foundation Testing](#phase-0-foundation-testing)
3. [Phase 1: Core Infrastructure Testing](#phase-1-core-infrastructure-testing)
4. [Phase 2: Knowledge Base Testing](#phase-2-knowledge-base-testing)
5. [Phase 3: Server Integration Testing](#phase-3-server-integration-testing)
6. [Phase 4: Widget Frontend Testing](#phase-4-widget-frontend-testing)
7. [Phase 5: Chat Logic Testing](#phase-5-chat-logic-testing)
8. [Phase 6-8: Advanced Features Testing](#phase-6-8-advanced-features-testing)
9. [Test Execution Commands](#test-execution-commands)
10. [Coverage Requirements by Phase](#coverage-requirements-by-phase)

---

## 🎯 Progressive Testing Philosophy

**CRITICAL:** Tests evolve WITH the code, not BEFORE it exists!

### Core Principles
1. **Test what exists** - Never test classes that haven't been created yet
2. **Progressive coverage** - Start with 0%, build to 90% gradually
3. **Phase-appropriate testing** - Each phase has specific test requirements
4. **No premature testing** - Don't test integrations before components exist
5. **Graceful failures expected** - Early phases WILL have failing tests

---

## 📈 Phase 0: Foundation Testing (Tasks 0.1-0.5)
**Status: READY TO START**
**Expected Coverage: 0% → 30%**

### What EXISTS at this point:
- Basic plugin file structure
- Configuration files (composer.json, package.json)
- Main plugin file

### Tests to Run:

#### 0.1 - Plugin Activation Test
```php
// tests/unit/PluginActivationTest.php
class PluginActivationTest extends WP_UnitTestCase {
    public function test_plugin_file_exists() {
        $this->assertFileExists(
            WOO_AI_ASSISTANT_PATH . 'woo-ai-assistant.php'
        );
    }
    
    public function test_plugin_can_be_activated() {
        activate_plugin('woo-ai-assistant/woo-ai-assistant.php');
        $this->assertTrue(is_plugin_active('woo-ai-assistant/woo-ai-assistant.php'));
    }
}
```

#### 0.2 - Composer Autoload Test
```bash
# Simple test - can composer autoload?
composer dump-autoload
php -r "require 'vendor/autoload.php'; echo 'Autoload works!';"
```

#### 0.3 - Directory Structure Test
```bash
# tests/structure-test.sh
#!/bin/bash
echo "Testing directory structure..."
[ -d "src" ] && echo "✓ src/ exists" || echo "✗ src/ missing"
[ -d "widget-src" ] && echo "✓ widget-src/ exists" || echo "✗ widget-src/ missing"
[ -d "tests" ] && echo "✓ tests/ exists" || echo "✗ tests/ missing"
```

### What NOT to test yet:
- ❌ Class methods (classes don't exist)
- ❌ Database operations (schema not created)
- ❌ API endpoints (not implemented)
- ❌ React components (not created)

---

## 🏗️ Phase 1: Core Infrastructure Testing (Tasks 1.1-1.3)
**Prerequisites: Phase 0 complete**
**Expected Coverage: 30% → 50%**

### What EXISTS at this point:
- Main.php singleton class
- AdminMenu.php
- RestController.php
- Database schema

### Tests to Add:

#### 1.1 - Singleton Pattern Test
```php
class MainTest extends WP_UnitTestCase {
    public function test_singleton_returns_same_instance() {
        $instance1 = Main::getInstance();
        $instance2 = Main::getInstance();
        $this->assertSame($instance1, $instance2);
    }
}
```

#### 1.2 - Admin Menu Test
```php
class AdminMenuTest extends WP_UnitTestCase {
    public function test_admin_menu_is_registered() {
        $admin = new AdminMenu();
        $this->assertTrue(has_action('admin_menu'));
    }
}
```

#### 1.3 - Database Creation Test
```php
class DatabaseTest extends WP_UnitTestCase {
    public function test_tables_are_created_on_activation() {
        global $wpdb;
        Activator::activate();
        
        $table = $wpdb->prefix . 'woo_ai_conversations';
        $this->assertEquals($table, $wpdb->get_var(
            "SHOW TABLES LIKE '$table'"
        ));
    }
}
```

### Still NOT testing:
- ❌ Knowledge Base operations (not implemented)
- ❌ Chat functionality (not implemented)
- ❌ React widget (not created)

---

## 🧠 Phase 2: Knowledge Base Testing (Tasks 2.1-2.5)
**Prerequisites: Phase 1 complete**
**Expected Coverage: 50% → 70%**

### What EXISTS at this point:
- Scanner.php
- Indexer.php
- ChunkingStrategy.php
- VectorManager.php (mock mode)

### Tests to Add:

#### 2.1 - Scanner Unit Tests
```php
class ScannerTest extends WP_UnitTestCase {
    private $scanner;
    
    protected function setUp(): void {
        parent::setUp();
        $this->scanner = new Scanner();
    }
    
    public function test_scan_products_returns_array() {
        // Create test product
        $product_id = $this->factory->post->create([
            'post_type' => 'product',
            'post_title' => 'Test Product'
        ]);
        
        $results = $this->scanner->scanProducts();
        $this->assertIsArray($results);
        $this->assertCount(1, $results);
    }
}
```

#### 2.2 - Chunking Strategy Tests
```php
class ChunkingStrategyTest extends WP_UnitTestCase {
    public function test_text_is_chunked_correctly() {
        $strategy = new ChunkingStrategy();
        $text = str_repeat('Lorem ipsum ', 200); // Long text
        
        $chunks = $strategy->chunk($text, 100);
        $this->assertIsArray($chunks);
        $this->assertGreaterThan(1, count($chunks));
    }
}
```

### Mock External Services:
```php
// Use mocks for external APIs
$this->apiClient = $this->createMock(IntermediateServerClient::class);
$this->apiClient->method('generateEmbedding')
    ->willReturn(['embedding' => array_fill(0, 1536, 0.1)]);
```

---

## 🔌 Phase 3: Server Integration Testing (Tasks 3.1-3.3)
**Prerequisites: Phase 2 complete**
**Expected Coverage: 70% → 75%**

### What EXISTS at this point:
- IntermediateServerClient.php
- LicenseManager.php
- Development mode configuration

### Tests to Add:

#### 3.1 - Development Mode Test
```php
class DevelopmentConfigTest extends WP_UnitTestCase {
    public function test_development_mode_bypasses_license() {
        putenv('WOO_AI_DEVELOPMENT_MODE=true');
        
        $config = new DevelopmentConfig();
        $this->assertTrue($config->isDevelopmentMode());
        $this->assertTrue($config->isLicenseValid());
    }
}
```

#### 3.2 - API Client Mock Test
```php
class IntermediateServerClientTest extends WP_UnitTestCase {
    public function test_api_client_handles_errors_gracefully() {
        $client = $this->createMock(IntermediateServerClient::class);
        $client->method('call')
            ->willThrowException(new ApiException('Network error'));
        
        $handler = new ApiHandler($client);
        $result = $handler->safeCall('test');
        
        $this->assertFalse($result);
    }
}
```

---

## ⚛️ Phase 4: Widget Frontend Testing (Tasks 4.1-4.5)
**Prerequisites: Phase 3 complete**
**Expected Coverage: 75% → 80%**

### What EXISTS at this point:
- React components (ChatWindow, Message, etc.)
- ApiService.js
- Widget loader PHP

### Tests to Add:

#### 4.1 - React Component Tests
```javascript
// widget-src/src/components/ChatWindow.test.js
import { render, screen } from '@testing-library/react';
import ChatWindow from './ChatWindow';

describe('ChatWindow', () => {
    test('renders when visible', () => {
        render(<ChatWindow isVisible={true} />);
        expect(screen.getByRole('dialog')).toBeInTheDocument();
    });
    
    test('does not render when hidden', () => {
        const { container } = render(<ChatWindow isVisible={false} />);
        expect(container.firstChild).toBeNull();
    });
});
```

#### 4.2 - Widget Loader Test
```php
class WidgetLoaderTest extends WP_UnitTestCase {
    public function test_widget_scripts_are_enqueued() {
        $loader = new WidgetLoader();
        $loader->enqueueScripts();
        
        $this->assertTrue(wp_script_is('woo-ai-widget', 'enqueued'));
    }
}
```

---

## 💬 Phase 5: Chat Logic Testing (Tasks 5.1-5.4)
**Prerequisites: Phase 4 complete**
**Expected Coverage: 80% → 85%**

### What EXISTS at this point:
- ConversationHandler.php
- ChatEndpoint.php
- Full chat flow

### Integration Tests Now Possible:
```php
class ChatIntegrationTest extends WP_UnitTestCase {
    public function test_full_chat_flow() {
        // Create conversation
        $handler = new ConversationHandler();
        $conversation_id = $handler->createConversation();
        
        // Send message
        $response = $handler->processMessage(
            $conversation_id, 
            'Show me blue shirts'
        );
        
        // Verify response
        $this->assertArrayHasKey('response', $response);
        $this->assertArrayHasKey('products', $response);
    }
}
```

---

## 🚀 Phase 6-8: Advanced Features Testing
**Prerequisites: Phase 5 complete**
**Expected Coverage: 85% → 90%+**

### Progressive Feature Tests:

#### Phase 6 - Coupon Management
```php
class CouponHandlerTest extends WP_UnitTestCase {
    public function test_coupon_application_with_guardrails() {
        $handler = new CouponHandler();
        
        // Test rate limiting
        $this->assertTrue($handler->canApplyCoupon($user_id));
        $handler->applyCoupon($coupon_code, $user_id);
        $this->assertFalse($handler->canApplyCoupon($user_id));
    }
}
```

#### Phase 7 - Analytics
```php
class AnalyticsTest extends WP_UnitTestCase {
    public function test_metrics_are_tracked() {
        $analytics = new Analytics();
        $analytics->track('conversation_started');
        
        $metrics = $analytics->getMetrics();
        $this->assertArrayHasKey('conversation_started', $metrics);
    }
}
```

---

## 🎯 Test Execution Commands

### Phase-Specific Test Execution

```bash
# Phase 0 - Foundation only
vendor/bin/phpunit --group phase0
npm test -- --testPathPattern="foundation"

# Phase 1 - Core + Foundation
vendor/bin/phpunit --group phase0,phase1
npm test -- --testPathPattern="(foundation|core)"

# Phase 2 - Add Knowledge Base
vendor/bin/phpunit --group phase0,phase1,phase2
npm test -- --testPathPattern="(foundation|core|knowledge)"

# And so on...
```

### Progressive Coverage Check

```bash
# Create phase-specific coverage script
# scripts/check-coverage-phase.sh
#!/bin/bash

PHASE=$1
case $PHASE in
    0) MIN_COVERAGE=30 ;;
    1) MIN_COVERAGE=50 ;;
    2) MIN_COVERAGE=70 ;;
    3) MIN_COVERAGE=75 ;;
    4) MIN_COVERAGE=80 ;;
    5) MIN_COVERAGE=85 ;;
    *) MIN_COVERAGE=90 ;;
esac

vendor/bin/phpunit --coverage-text | grep "Lines:" | \
    awk -v min=$MIN_COVERAGE '{
        gsub("%", "", $3);
        if ($3 >= min) {
            print "✅ Coverage " $3 "% meets Phase '$PHASE' requirement (" min "%)"
            exit 0
        } else {
            print "❌ Coverage " $3 "% below Phase '$PHASE' requirement (" min "%)"
            exit 1
        }
    }'
```

---

## 📊 Coverage Requirements by Phase

| Phase | Description | Min Coverage | New Test Types |
|-------|-------------|--------------|----------------|
| 0 | Foundation | 30% | Structure, Activation |
| 1 | Core Infrastructure | 50% | Unit tests for core classes |
| 2 | Knowledge Base | 70% | Scanner, Indexer tests |
| 3 | Server Integration | 75% | API mocks, License tests |
| 4 | Widget Frontend | 80% | React component tests |
| 5 | Chat Logic | 85% | Integration tests |
| 6 | Advanced Features | 87% | Feature-specific tests |
| 7 | Analytics | 89% | Metrics and reporting |
| 8 | Optimization | 90%+ | Performance, Security |

---

## ⚠️ Important Notes

### Expected Test Failures by Phase

**Phase 0:**
- ✅ Directory structure tests pass
- ❌ Class tests fail (classes don't exist yet)

**Phase 1:**
- ✅ Basic class tests pass
- ❌ Integration tests fail (no integrations yet)

**Phase 2:**
- ✅ Unit tests pass with mocks
- ❌ Real API tests fail (no server configured)

### Progressive Test Groups

Mark your tests with appropriate groups:

```php
/**
 * @group phase0
 * @group foundation
 */
public function test_plugin_activation() { }

/**
 * @group phase1
 * @group core
 */
public function test_singleton_pattern() { }
```

This allows running phase-appropriate tests only.

---

*Last Updated: 2025-01-30 | Version: 2.0.0 - Progressive Testing*