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

## 📋 Test-to-Task Mapping Table

### Complete Task-Test Mapping Matrix

| Phase | Task | Test File | Test Type | Dependencies | Coverage Target |
|-------|------|-----------|-----------|--------------|-----------------|
| **Phase 0** | | | | | **30%** |
| 0 | 0.1 Plugin Skeleton | `tests/unit/PluginActivationTest.php` | Unit | None | Basic structure |
| 0 | 0.1 Main Class | `tests/unit/MainTest.php` | Unit | Plugin file | Singleton pattern |
| 0 | 0.2 Config | `tests/unit/Config/DevelopmentConfigTest.php` | Unit | Main class | Environment detection |
| 0 | 0.3 Testing Infrastructure | `tests/bootstrap.php` | Infrastructure | All above | Test setup |
| 0 | 0.4 CI/CD | `.github/workflows/test.yml` | Pipeline | Test infrastructure | Automation |
| 0 | 0.5 Database Migrations | `tests/unit/Database/MigrationsTest.php` | Unit | All above | Schema creation |
| **Phase 1** | | | | | **50%** |
| 1 | 1.1 AdminMenu | `tests/unit/Admin/AdminMenuTest.php` | Unit | Phase 0 complete | Menu registration |
| 1 | 1.2 RestController | `tests/unit/RestApi/RestControllerTest.php` | Unit | AdminMenu | Endpoint registration |
| 1 | 1.3 Database Schema | `tests/unit/Setup/ActivatorTest.php` | Integration | Migrations | Table creation |
| **Phase 2** | | | | | **70%** |
| 2 | 2.1 Scanner | `tests/unit/KnowledgeBase/ScannerTest.php` | Unit | Phase 1 complete | Content extraction |
| 2 | 2.2 Indexer | `tests/unit/KnowledgeBase/IndexerTest.php` | Unit | Scanner | Data processing |
| 2 | 2.2 ChunkingStrategy | `tests/unit/KnowledgeBase/ChunkingStrategyTest.php` | Unit | Indexer | Text chunking |
| 2 | 2.3 VectorManager | `tests/unit/KnowledgeBase/VectorManagerTest.php` | Mock | ChunkingStrategy | Vector operations |
| 2 | 2.3 EmbeddingGenerator | `tests/unit/KnowledgeBase/EmbeddingGeneratorTest.php` | Mock | VectorManager | Embedding creation |
| 2 | 2.4 AIManager | `tests/unit/KnowledgeBase/AIManagerTest.php` | Mock | EmbeddingGenerator | AI integration |
| 2 | 2.4 PromptBuilder | `tests/unit/KnowledgeBase/PromptBuilderTest.php` | Unit | AIManager | Prompt construction |
| 2 | 2.5 KB Integration | `tests/integration/KnowledgeBase/FullKBTest.php` | Integration | All KB components | End-to-end KB |
| **Phase 3** | | | | | **75%** |
| 3 | 3.1 ServerClient | `tests/unit/Api/IntermediateServerClientTest.php` | Mock | Phase 2 complete | API communication |
| 3 | 3.2 LicenseManager | `tests/unit/Api/LicenseManagerTest.php` | Mock | ServerClient | License validation |
| 3 | 3.3 API Config | `tests/unit/Config/ApiConfigurationTest.php` | Unit | LicenseManager | Config management |
| **Phase 4** | | | | | **80%** |
| 4 | 4.1 React App | `widget-src/src/App.test.js` | React | Phase 3 complete | Component structure |
| 4 | 4.2 ChatWindow | `widget-src/src/components/ChatWindow.test.js` | React | App | Chat interface |
| 4 | 4.2 Message Component | `widget-src/src/components/Message.test.js` | React | ChatWindow | Message display |
| 4 | 4.3 ApiService | `widget-src/src/services/ApiService.test.js` | React | Message | API communication |
| 4 | 4.4 ProductCard | `widget-src/src/components/ProductCard.test.js` | React | ApiService | Product display |
| 4 | 4.4 QuickAction | `widget-src/src/components/QuickAction.test.js` | React | ProductCard | Action buttons |
| 4 | 4.5 WidgetLoader | `tests/unit/Frontend/WidgetLoaderTest.php` | Unit | All React | PHP-JS integration |
| **Phase 5** | | | | | **85%** |
| 5 | 5.1 ConversationHandler | `tests/unit/Chatbot/ConversationHandlerTest.php` | Unit | Phase 4 complete | Conversation logic |
| 5 | 5.2 ChatEndpoint | `tests/unit/RestApi/Endpoints/ChatEndpointTest.php` | Integration | ConversationHandler | Chat API |
| 5 | 5.3 ActionEndpoint | `tests/unit/RestApi/Endpoints/ActionEndpointTest.php` | Integration | ChatEndpoint | Action execution |
| 5 | 5.4 RatingEndpoint | `tests/unit/RestApi/Endpoints/RatingEndpointTest.php` | Integration | ActionEndpoint | Feedback system |
| 5 | 5.x Full Chat Flow | `tests/integration/Chat/FullChatFlowTest.php` | End-to-End | All endpoints | Complete workflow |
| **Phase 6** | | | | | **87%** |
| 6 | 6.1 CouponHandler | `tests/unit/Chatbot/CouponHandlerTest.php` | Unit | Phase 5 complete | Coupon logic |
| 6 | 6.2 ProactiveTriggers | `tests/unit/Chatbot/ProactiveTriggersTest.php` | Unit | CouponHandler | Trigger logic |
| 6 | 6.3 Handoff | `tests/unit/Chatbot/HandoffTest.php` | Unit | ProactiveTriggers | Human takeover |
| **Phase 7** | | | | | **89%** |
| 7 | 7.1 DashboardPage | `tests/unit/Admin/pages/DashboardPageTest.php` | Unit | Phase 6 complete | Admin dashboard |
| 7 | 7.2 SettingsPage | `tests/unit/Admin/pages/SettingsPageTest.php` | Unit | DashboardPage | Settings interface |
| 7 | 7.3 ConversationsLog | `tests/unit/Admin/pages/ConversationsLogPageTest.php` | Unit | SettingsPage | Log management |
| **Phase 8** | | | | | **90%+** |
| 8 | 8.1 Performance | `tests/performance/LoadTest.php` | Performance | Phase 7 complete | Speed optimization |
| 8 | 8.2 Security | `tests/security/SecurityTest.php` | Security | Performance | Security hardening |
| 8 | 8.3 Documentation | `tests/integration/E2E/FullPluginTest.php` | End-to-End | Security | Complete testing |

### Test Execution Commands by Phase

```bash
# Phase 0 - Foundation Tests
./scripts/run-phase-tests.sh 0

# Phase 1 - Core Infrastructure Tests  
./scripts/run-phase-tests.sh 1

# Phase 2 - Knowledge Base Tests
./scripts/run-phase-tests.sh 2

# Phase 3 - Server Integration Tests
./scripts/run-phase-tests.sh 3

# Phase 4 - Frontend Tests
./scripts/run-phase-tests.sh 4

# Phase 5 - Chat Logic Tests
./scripts/run-phase-tests.sh 5

# Phase 6-8 - Advanced Feature Tests
./scripts/run-phase-tests.sh 6
./scripts/run-phase-tests.sh 7 
./scripts/run-phase-tests.sh 8

# All tests for completed phases
./scripts/run-phase-tests.sh all
```

### Quality Gates by Phase

| Phase | Must Pass Before Completion |
|-------|----------------------------|
| 0 | Plugin activation, basic structure tests |
| 1 | Admin menu, database schema, REST endpoints |
| 2 | Knowledge Base unit tests with mocks |
| 3 | API integration tests with mocks |
| 4 | React component tests, widget loading |
| 5 | Chat flow integration tests |
| 6 | Advanced feature unit tests |
| 7 | Admin interface tests |
| 8 | Performance, security, E2E tests |

---

*Last Updated: 2025-08-30 | Version: 2.1.0 - Progressive Testing with Task Mapping*