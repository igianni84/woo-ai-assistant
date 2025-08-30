<?php

/**
 * Testing Infrastructure Integration Tests
 *
 * Verifies that the testing infrastructure is properly set up and working.
 * Tests the test environment, fixtures, database connections, and test utilities.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Integration
 * @since 1.0.0
 * @author Claude Code Assistant
 */

namespace WooAiAssistant\Tests\Integration;

use WooAiAssistant\Tests\Integration\WooAiIntegrationTestCase;
use WooAiAssistant\Tests\Fixtures\FixtureLoader;

/**
 * Class TestingInfrastructureTest
 *
 * Integration tests for the testing infrastructure itself.
 *
 * @since 1.0.0
 */
class TestingInfrastructureTest extends WooAiIntegrationTestCase
{
    /**
     * Test that WordPress test environment is properly configured
     *
     * @return void
     */
    public function test_wordpress_test_environment_should_be_configured(): void
    {
        // Check WordPress constants
        $this->assertTrue(defined('ABSPATH'), 'ABSPATH should be defined');
        $this->assertTrue(defined('WP_DEBUG'), 'WP_DEBUG should be defined');

        // Check database connection
        global $wpdb;
        $this->assertInstanceOf('wpdb', $wpdb, 'WordPress database should be available');

        // Test database query
        $result = $wpdb->get_var("SELECT 1");
        $this->assertEquals(1, $result, 'Database should be responsive');
    }

    /**
     * Test that plugin constants are properly defined
     *
     * @return void
     */
    public function test_plugin_constants_should_be_defined(): void
    {
        $requiredConstants = [
            'WOO_AI_ASSISTANT_TESTING',
            'WOO_AI_ASSISTANT_PLUGIN_DIR',
            'WOO_AI_ASSISTANT_PLUGIN_FILE',
            'WOO_AI_ASSISTANT_BASENAME'
        ];

        foreach ($requiredConstants as $constant) {
            $this->assertTrue(defined($constant), "Constant {$constant} should be defined in test environment");
        }

        // Verify constant values make sense
        $this->assertTrue(WOO_AI_ASSISTANT_TESTING, 'Testing mode should be active');
        $this->assertTrue(is_dir(WOO_AI_ASSISTANT_PLUGIN_DIR), 'Plugin directory should exist');
        $this->assertTrue(file_exists(WOO_AI_ASSISTANT_PLUGIN_FILE), 'Plugin file should exist');
    }

    /**
     * Test that WooCommerce is available in test environment
     *
     * @return void
     */
    public function test_woocommerce_should_be_available(): void
    {
        $this->assertTrue(class_exists('WooCommerce'), 'WooCommerce class should be available');
        $this->assertTrue(function_exists('wc_get_product'), 'WooCommerce functions should be available');
        $this->assertTrue(class_exists('WC_Product_Simple'), 'WooCommerce product classes should be available');
    }

    /**
     * Test fixture loading functionality
     *
     * @return void
     */
    public function test_fixture_loader_should_work_correctly(): void
    {
        // Test JSON fixture loading
        $products = FixtureLoader::loadJsonFixture('sample-products');
        $this->assertIsArray($products, 'Product fixtures should be loaded as array');
        $this->assertNotEmpty($products, 'Product fixtures should not be empty');

        $users = FixtureLoader::loadJsonFixture('sample-users');
        $this->assertIsArray($users, 'User fixtures should be loaded as array');
        $this->assertNotEmpty($users, 'User fixtures should not be empty');

        $config = FixtureLoader::loadJsonFixture('plugin-configurations');
        $this->assertIsArray($config, 'Configuration fixtures should be loaded as array');
        $this->assertNotEmpty($config, 'Configuration fixtures should not be empty');
    }

    /**
     * Test product creation from fixtures
     *
     * @return void
     */
    public function test_fixture_product_creation_should_work(): void
    {
        // Load a single product fixture
        $productData = [
            'name' => 'Test Integration Product',
            'slug' => 'test-integration-product',
            'regular_price' => '19.99',
            'short_description' => 'Test product for integration testing',
            'description' => 'This is a test product created during integration testing.',
            'categories' => ['Test Category'],
            'tags' => ['integration', 'test']
        ];

        $productIds = FixtureLoader::createTestProducts([$productData]);

        $this->assertIsArray($productIds, 'Product creation should return array of IDs');
        $this->assertCount(1, $productIds, 'Should create one product');

        $productId = $productIds[0];
        $this->assertGreaterThan(0, $productId, 'Product ID should be positive integer');

        // Verify product was created correctly
        $product = wc_get_product($productId);
        $this->assertInstanceOf('WC_Product', $product, 'Should create valid WooCommerce product');
        $this->assertEquals('Test Integration Product', $product->get_name(), 'Product name should match');
        $this->assertEquals('19.99', $product->get_regular_price(), 'Product price should match');

        // Clean up
        wp_delete_post($productId, true);
    }

    /**
     * Test user creation from fixtures
     *
     * @return void
     */
    public function test_fixture_user_creation_should_work(): void
    {
        $userData = [
            'user_login' => 'test_integration_user',
            'user_email' => 'integration@test.com',
            'user_pass' => 'password123',
            'first_name' => 'Integration',
            'last_name' => 'Test',
            'role' => 'customer'
        ];

        $userIds = FixtureLoader::createTestUsers([$userData]);

        $this->assertIsArray($userIds, 'User creation should return array of IDs');
        $this->assertCount(1, $userIds, 'Should create one user');

        $userId = $userIds[0];
        $this->assertGreaterThan(0, $userId, 'User ID should be positive integer');

        // Verify user was created correctly
        $user = get_user_by('id', $userId);
        $this->assertInstanceOf('WP_User', $user, 'Should create valid WordPress user');
        $this->assertEquals('test_integration_user', $user->user_login, 'Username should match');
        $this->assertEquals('integration@test.com', $user->user_email, 'Email should match');

        // Clean up
        wp_delete_user($userId);
    }

    /**
     * Test configuration loading and application
     *
     * @return void
     */
    public function test_configuration_loading_should_work(): void
    {
        $devConfig = FixtureLoader::loadPluginConfig('development_config');

        $this->assertIsArray($devConfig, 'Development config should be array');
        $this->assertArrayHasKey('development_mode', $devConfig, 'Should have development_mode key');
        $this->assertTrue($devConfig['development_mode'], 'Development mode should be true in dev config');

        // Test applying configuration
        FixtureLoader::applyPluginConfig('development_config');

        $appliedValue = get_option('woo_ai_assistant_development_mode');
        $this->assertTrue($appliedValue, 'Configuration should be applied as WordPress options');

        // Clean up
        delete_option('woo_ai_assistant_development_mode');
    }

    /**
     * Test PHPUnit integration with WordPress
     *
     * @return void
     */
    public function test_phpunit_wordpress_integration_should_work(): void
    {
        // Test WordPress test factory
        $this->assertInstanceOf('WP_UnitTest_Factory', $this->factory, 'WordPress test factory should be available');

        // Test creating WordPress objects through factory
        $postId = $this->factory->post->create(['post_title' => 'Test Post']);
        $this->assertGreaterThan(0, $postId, 'Should be able to create posts through factory');

        $userId = $this->factory->user->create(['user_login' => 'test_factory_user']);
        $this->assertGreaterThan(0, $userId, 'Should be able to create users through factory');

        // Test WordPress assertions
        $this->assertInstanceOf('WP_Post', get_post($postId), 'Created post should be WP_Post instance');
        $this->assertInstanceOf('WP_User', get_user_by('id', $userId), 'Created user should be WP_User instance');
    }

    /**
     * Test test database isolation
     *
     * @return void
     */
    public function test_database_isolation_should_work(): void
    {
        global $wpdb;

        // Check that we're using test database
        $this->assertStringContains('test', DB_NAME, 'Should be using test database');

        // Check table prefix
        $this->assertStringContains('test', $wpdb->prefix, 'Should be using test table prefix');

        // Test that we can create and clean up data without affecting main site
        $testOption = 'woo_ai_test_isolation_check';
        add_option($testOption, 'test_value');

        $this->assertEquals('test_value', get_option($testOption), 'Should be able to set test options');

        delete_option($testOption);
        $this->assertFalse(get_option($testOption), 'Should be able to clean up test options');
    }

    /**
     * Test performance of testing infrastructure
     *
     * @return void
     */
    public function test_testing_infrastructure_performance_should_be_acceptable(): void
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        // Perform typical testing operations
        $products = FixtureLoader::loadJsonFixture('sample-products');
        $users = FixtureLoader::loadJsonFixture('sample-users');
        $config = FixtureLoader::loadPluginConfig();

        // Create and clean up test data
        $productIds = FixtureLoader::createTestProducts(array_slice($products, 0, 2));
        $userIds = FixtureLoader::createTestUsers(array_slice($users, 0, 2));

        FixtureLoader::cleanupTestData($productIds, $userIds);

        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        $executionTime = $endTime - $startTime;
        $memoryUsage = $endMemory - $startMemory;

        $this->assertLessThan(5.0, $executionTime, 'Testing infrastructure operations should complete within 5 seconds');
        $this->assertLessThan(10485760, $memoryUsage, 'Memory usage should be less than 10MB'); // 10MB limit
    }

    /**
     * Test error handling in testing infrastructure
     *
     * @return void
     */
    public function test_testing_infrastructure_error_handling(): void
    {
        // Test loading non-existent fixture
        $this->expectException(\Exception::class);
        FixtureLoader::loadJsonFixture('non-existent-fixture');
    }

    /**
     * Test cleanup functionality
     *
     * @return void
     */
    public function test_cleanup_functionality_should_work(): void
    {
        // Create test data
        $testProducts = FixtureLoader::createTestProducts([
            [
                'name' => 'Cleanup Test Product',
                'regular_price' => '9.99'
            ]
        ]);

        $testUsers = FixtureLoader::createTestUsers([
            [
                'user_login' => 'cleanup_test_user',
                'user_email' => 'cleanup@test.com',
                'user_pass' => 'password123'
            ]
        ]);

        // Verify data exists
        $this->assertNotEmpty($testProducts, 'Test products should be created');
        $this->assertNotEmpty($testUsers, 'Test users should be created');

        $productId = $testProducts[0];
        $userId = $testUsers[0];

        $this->assertInstanceOf('WC_Product', wc_get_product($productId), 'Test product should exist');
        $this->assertInstanceOf('WP_User', get_user_by('id', $userId), 'Test user should exist');

        // Clean up
        FixtureLoader::cleanupTestData($testProducts, $testUsers);

        // Verify cleanup worked
        $this->assertFalse(wc_get_product($productId), 'Test product should be deleted');
        $this->assertFalse(get_user_by('id', $userId), 'Test user should be deleted');
    }

    /**
     * Test that all test directories exist
     *
     * @return void
     */
    public function test_test_directories_should_exist(): void
    {
        $testDirs = [
            'tests',
            'tests/unit',
            'tests/integration',
            'tests/fixtures',
            'tests/logs',
            'tests/tmp',
            'tests/unit/Common',
            'tests/unit/Config'
        ];

        $pluginDir = WOO_AI_ASSISTANT_PLUGIN_DIR;

        foreach ($testDirs as $dir) {
            $fullPath = $pluginDir . '/' . $dir;
            $this->assertTrue(is_dir($fullPath), "Test directory should exist: {$fullPath}");
        }
    }
}
