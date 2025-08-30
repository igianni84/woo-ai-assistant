# Testing Infrastructure for Woo AI Assistant Plugin

This directory contains the comprehensive testing infrastructure for the Woo AI Assistant WordPress plugin.

## 📁 Directory Structure

```
tests/
├── bootstrap.php                    # WordPress integration bootstrap
├── bootstrap-simple.php            # Simple bootstrap (no WordPress)
├── run-tests.php                   # Test runner script
├── README.md                       # This file
├── unit/                          # Unit tests
│   ├── WooAiBaseTestCase.php      # Base test case class
│   ├── SimpleTestExample.php      # Basic functionality tests
│   ├── MainTest.php              # Tests for Main.php class
│   ├── Common/                   # Tests for Common classes
│   │   └── UtilsTest.php        # Utils class tests
│   └── Config/                  # Tests for Config classes
│       └── DevelopmentConfigTest.php
├── integration/                   # Integration tests
│   ├── WooAiIntegrationTestCase.php
│   ├── WordPressIntegrationTest.php
│   └── TestingInfrastructureTest.php
├── fixtures/                     # Test data and fixtures
│   ├── FixtureLoader.php        # Utility for loading test data
│   ├── sample-products.json     # Sample WooCommerce products
│   ├── sample-users.json        # Sample WordPress users
│   └── plugin-configurations.json # Plugin configuration data
├── logs/                         # Test execution logs
└── tmp/                         # Temporary files for testing
```

## 🚀 Quick Start

### Option 1: Simple Tests (Recommended for initial development)

Run basic tests without WordPress integration:

```bash
# Using simple configuration
./vendor/bin/phpunit --configuration phpunit-simple.xml

# Or using composer
composer run test-simple
```

### Option 2: Full WordPress Integration Tests

For full WordPress integration tests, you need to install the WordPress test suite first:

```bash
# Install WordPress test suite
cd /tmp
svn co https://develop.svn.wordpress.org/tags/6.3/tests/phpunit/ wordpress-tests-lib
cd wordpress-tests-lib
cp wp-tests-config-sample.php wp-tests-config.php

# Edit wp-tests-config.php with your test database settings
# Then run full tests
composer run test
```

### Option 3: Using the Test Runner

```bash
# Run all tests
php tests/run-tests.php

# Run only unit tests
php tests/run-tests.php unit

# Run with coverage
php tests/run-tests.php all --coverage

# Run with verbose output
php tests/run-tests.php all --verbose
```

## 📋 Test Types

### Unit Tests (`tests/unit/`)

- **Purpose**: Test individual classes and methods in isolation
- **Environment**: Minimal dependencies, fast execution
- **Coverage**: All src/ classes should have corresponding unit tests
- **Naming**: `ClassNameTest.php` for `ClassName.php`

**Example Unit Test:**
```php
<?php
namespace WooAiAssistant\Tests\Unit;

class UtilsTest extends WooAiBaseTestCase
{
    public function test_isWooCommerceActive_should_return_boolean(): void
    {
        $result = Utils::isWooCommerceActive();
        $this->assertIsBool($result);
    }
}
```

### Integration Tests (`tests/integration/`)

- **Purpose**: Test component interactions and WordPress/WooCommerce integration
- **Environment**: Full WordPress test environment
- **Coverage**: Plugin integration with WordPress core, WooCommerce, database
- **Naming**: `FeatureIntegrationTest.php`

**Example Integration Test:**
```php
<?php
namespace WooAiAssistant\Tests\Integration;

class WordPressIntegrationTest extends WooAiIntegrationTestCase
{
    public function test_plugin_should_activate_successfully(): void
    {
        $plugin = Main::getInstance();
        $this->assertInstanceOf(Main::class, $plugin);
    }
}
```

## 🔧 Test Configuration Files

### phpunit.xml (Full WordPress Integration)
- Requires WordPress test suite installation
- Includes database configuration for MAMP environment
- Full coverage reporting
- All test suites

### phpunit-simple.xml (Basic Testing)
- No WordPress dependencies
- Fast execution
- Basic functionality verification
- Ideal for development and CI

## 🎯 Writing Tests

### Naming Conventions (Following CLAUDE.md Standards)

```php
// ✅ Correct Test Method Names
public function test_methodName_should_return_expected_result_when_condition(): void
public function test_className_should_follow_pascal_case_convention(): void
public function test_variableName_should_follow_camel_case_convention(): void

// ❌ Wrong Test Method Names  
public function testMethodName(): void
public function test_something(): void
public function testStuff(): void
```

### Required Test Categories

Every class should have tests for:

1. **Happy Path Tests** - Normal operation
2. **Edge Case Tests** - Boundary conditions  
3. **Error Handling Tests** - Exception scenarios
4. **Integration Tests** - WordPress/WooCommerce integration
5. **Naming Convention Tests** - Code standards compliance

### Base Test Classes

**WooAiBaseTestCase** - For unit tests
- Extends WordPress `WP_UnitTestCase`
- Provides plugin-specific test utilities
- Automatic test data cleanup
- Naming convention assertions

**WooAiIntegrationTestCase** - For integration tests
- Full WordPress environment
- WooCommerce integration
- Database transaction management

## 📊 Test Fixtures

### Loading Test Data

```php
use WooAiAssistant\Tests\Fixtures\FixtureLoader;

// Load sample products
$products = FixtureLoader::loadJsonFixture('sample-products');

// Create test products in database
$productIds = FixtureLoader::createTestProducts($products);

// Create test users
$userIds = FixtureLoader::createTestUsers();

// Apply test configuration
FixtureLoader::applyPluginConfig('development_config');

// Cleanup (automatic in tearDown)
FixtureLoader::cleanupTestData($productIds, $userIds);
```

### Available Fixtures

- **sample-products.json**: 5 diverse WooCommerce products with categories, tags, attributes
- **sample-users.json**: Test users with different roles (customer, shop_manager, etc.)
- **plugin-configurations.json**: Various plugin configurations for different scenarios

## 🐛 Debugging Tests

### Enable Debug Mode

```bash
# Set debug environment
export WOO_AI_ASSISTANT_DEBUG=true
export WOO_AI_DEVELOPMENT_MODE=true

# Run tests with debug output
./vendor/bin/phpunit --debug --verbose
```

### Debug Utilities

```php
// In test methods
$this->debugLog('Test checkpoint reached');
$this->debugLog($data, 'Variable dump');

// Check private/protected properties
$value = $this->getPropertyValue($instance, 'privateProperty');
$this->setPropertyValue($instance, 'privateProperty', $newValue);

// Invoke private/protected methods
$result = $this->invokeMethod($instance, 'privateMethod', [$arg1, $arg2]);
```

## ⚡ Performance Guidelines

### Test Performance Standards

- Unit tests: < 100ms per test
- Integration tests: < 5s per test
- Memory usage: < 10MB per test suite
- Total suite: < 30s for unit tests

### Optimization Tips

```php
// ✅ Good - Create minimal test data
$product = $this->createTestProduct(['name' => 'Test Product']);

// ❌ Bad - Create unnecessary data
$products = FixtureLoader::createTestProducts(); // Creates 5 products

// ✅ Good - Mock external calls  
$this->mockApiResponse('chat_endpoint', ['response' => 'test']);

// ❌ Bad - Make real API calls
$response = ApiClient::callRealApi();
```

## 🔒 Security Testing

### Security Test Examples

```php
// Test nonce verification
public function test_nonce_verification_should_prevent_csrf(): void
{
    $nonce = Utils::generateNonce('test_action');
    $this->assertTrue(Utils::verifyNonce($nonce, 'test_action'));
    $this->assertFalse(Utils::verifyNonce($nonce, 'wrong_action'));
}

// Test capability checks
public function test_admin_functions_should_require_proper_capabilities(): void
{
    wp_set_current_user($this->createTestUser('customer'));
    $this->assertFalse(current_user_can('manage_woocommerce'));
}

// Test input sanitization
public function test_user_input_should_be_sanitized(): void
{
    $maliciousInput = '<script>alert("xss")</script>test@example.com';
    $sanitized = Utils::sanitizeEmail($maliciousInput);
    $this->assertEquals('test@example.com', $sanitized);
}
```

## 📈 Coverage Requirements

- **Minimum Coverage**: 70% overall
- **Critical Classes**: 90% coverage (Main, Utils, etc.)
- **New Features**: 80% coverage required
- **Integration Tests**: Must cover all public APIs

### Check Coverage

```bash
# Generate HTML coverage report
composer run test:coverage

# View coverage report
open coverage/html/index.html
```

## 🚨 Quality Gates

Before completing any task, ALL quality gates must pass:

```bash
# Run mandatory quality gates
composer run quality-gates-enforce

# Check status
cat .quality-gates-status  # Must show "QUALITY_GATES_STATUS=PASSED"
```

Quality gates include:
- All tests passing
- Code coverage requirements
- Coding standards (PSR-12)
- Static analysis (PHPStan)
- Security checks

## 🔄 Continuous Integration

### GitHub Actions Integration

The testing infrastructure is designed to work with GitHub Actions:

```yaml
- name: Run Tests
  run: composer run test-simple

- name: Run Quality Gates  
  run: composer run quality-gates-enforce

- name: Upload Coverage
  uses: codecov/codecov-action@v1
  with:
    file: ./coverage/clover.xml
```

## 🆘 Troubleshooting

### Common Issues

**"WordPress test functions not found"**
```bash
# Install WordPress test suite
cd /tmp && svn co https://develop.svn.wordpress.org/tags/6.3/tests/phpunit/ wordpress-tests-lib
```

**"Class 'WooCommerce' not found"**  
- Ensure WooCommerce is installed and active in test environment
- Check bootstrap.php is loading WooCommerce correctly

**"Permission denied" errors**
```bash
# Fix directory permissions
chmod -R 755 tests/
chmod -R 755 coverage/
```

**Memory limit exceeded**
```bash
# Increase memory limit
ini_set('memory_limit', '512M');
# Or set in php.ini: memory_limit = 512M
```

### Debug Commands

```bash
# Test specific file
./vendor/bin/phpunit tests/unit/MainTest.php

# Test specific method  
./vendor/bin/phpunit --filter test_method_name

# Run with debug output
./vendor/bin/phpunit --debug --verbose --stop-on-failure
```

## 📚 Additional Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [WordPress Testing Handbook](https://make.wordpress.org/core/handbook/testing/)
- [WooCommerce Testing Guide](https://woocommerce.com/document/create-a-plugin/)
- [CLAUDE.md](../CLAUDE.md) - Project development guidelines