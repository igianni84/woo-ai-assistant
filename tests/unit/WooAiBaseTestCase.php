<?php
/**
 * Base Test Case for Woo AI Assistant Plugin
 *
 * Provides common functionality for all plugin unit tests including
 * WordPress test environment setup, WooCommerce integration, and
 * test utilities specific to the Woo AI Assistant plugin.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Unit
 * @since 1.0.0
 * @author Claude Code Assistant
 */

namespace WooAiAssistant\Tests\Unit;

use WP_UnitTestCase;
use WooAiAssistant\Main;
use WooAiAssistant\Common\Utils;
use WooAiAssistant\Common\Logger;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WooAiBaseTestCase
 *
 * Base test case providing common functionality for all plugin tests.
 * Extends WordPress WP_UnitTestCase to leverage WordPress testing framework.
 *
 * @since 1.0.0
 */
abstract class WooAiBaseTestCase extends WP_UnitTestCase
{
    /**
     * Plugin main instance
     *
     * @var Main|null
     */
    protected $plugin;

    /**
     * Test products created during tests
     *
     * @var array
     */
    protected $testProducts = [];

    /**
     * Test users created during tests
     *
     * @var array
     */
    protected $testUsers = [];

    /**
     * Original error reporting level
     *
     * @var int
     */
    protected $originalErrorReporting;

    /**
     * Set up test environment
     *
     * Called before each test method. Initializes the plugin instance,
     * sets up test data, and configures the testing environment.
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        // Store original error reporting
        $this->originalErrorReporting = error_reporting();
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

        // Ensure WooCommerce is available for testing
        if (!class_exists('WooCommerce')) {
            $this->markTestSkipped('WooCommerce is not available for testing');
        }

        // Initialize plugin main instance
        $this->plugin = Main::getInstance();

        // Ensure development mode is active
        add_filter('woo_ai_assistant_is_development_mode', '__return_true');

        // Mock external API calls
        add_filter('woo_ai_assistant_use_mock_api', '__return_true');

        // Set up test-specific options
        update_option('woo_ai_assistant_test_mode', true);

        // Initialize any required test data
        $this->setUpTestData();

        // Clear any cached data
        $this->clearCaches();
    }

    /**
     * Tear down test environment
     *
     * Called after each test method. Cleans up test data,
     * restores original settings, and performs cleanup.
     *
     * @return void
     */
    public function tearDown(): void
    {
        // Clean up test data
        $this->cleanUpTestData();

        // Clear caches
        $this->clearCaches();

        // Restore error reporting
        error_reporting($this->originalErrorReporting);

        // Remove test-specific options
        delete_option('woo_ai_assistant_test_mode');

        parent::tearDown();
    }

    /**
     * Set up test data
     *
     * Override in child classes to create specific test data.
     *
     * @return void
     */
    protected function setUpTestData(): void
    {
        // Base implementation - override in child classes
    }

    /**
     * Clean up test data
     *
     * Removes all test data created during the test.
     *
     * @return void
     */
    protected function cleanUpTestData(): void
    {
        // Clean up test products
        foreach ($this->testProducts as $product_id) {
            wp_delete_post($product_id, true);
        }
        $this->testProducts = [];

        // Clean up test users
        foreach ($this->testUsers as $user_id) {
            wp_delete_user($user_id);
        }
        $this->testUsers = [];

        // Clean up test options
        $this->cleanUpTestOptions();
    }

    /**
     * Clean up test options
     *
     * @return void
     */
    protected function cleanUpTestOptions(): void
    {
        global $wpdb;

        // Remove all plugin test options
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'woo_ai_assistant_test_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_woo_ai_test_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_woo_ai_test_%'");
    }

    /**
     * Clear caches
     *
     * @return void
     */
    protected function clearCaches(): void
    {
        wp_cache_flush();

        // Clear WooCommerce caches if available
        if (function_exists('wc_delete_product_transients')) {
            wc_delete_product_transients();
        }

        // Clear plugin-specific caches
        do_action('woo_ai_assistant_clear_caches');
    }

    /**
     * Create test product
     *
     * @param array $args Product arguments
     * @return \WC_Product|false Product object or false on failure
     */
    protected function createTestProduct(array $args = []): ?\WC_Product
    {
        $product = woo_ai_create_test_product($args);

        if ($product && $product->get_id()) {
            $this->testProducts[] = $product->get_id();
            return $product;
        }

        return null;
    }

    /**
     * Create test user
     *
     * @param string $role User role
     * @param array $args Additional arguments
     * @return int|false User ID or false on failure
     */
    protected function createTestUser(string $role = 'customer', array $args = []): ?int
    {
        $user_id = woo_ai_create_test_user($role, $args);

        if (!is_wp_error($user_id)) {
            $this->testUsers[] = $user_id;
            return $user_id;
        }

        return null;
    }

    /**
     * Assert method name follows camelCase convention
     *
     * @param object $instance Object to check
     * @param string $method_name Method name to check
     * @return void
     */
    protected function assertMethodFollowsCamelCase(object $instance, string $method_name): void
    {
        $this->assertTrue(
            method_exists($instance, $method_name),
            "Method {$method_name} does not exist"
        );

        // Check camelCase convention (first letter lowercase, no underscores)
        $this->assertTrue(
            ctype_lower($method_name[0]) && !strpos($method_name, '_'),
            "Method {$method_name} should follow camelCase convention (first letter lowercase, no underscores)"
        );
    }

    /**
     * Assert class name follows PascalCase convention
     *
     * @param string $class_name Class name to check
     * @return void
     */
    protected function assertClassFollowsPascalCase(string $class_name): void
    {
        // Remove namespace for checking
        $class_name = basename(str_replace('\\', '/', $class_name));

        $this->assertTrue(
            ctype_upper($class_name[0]) && !strpos($class_name, '_'),
            "Class {$class_name} should follow PascalCase convention (first letter uppercase, no underscores)"
        );
    }

    /**
     * Assert variable name follows camelCase convention
     *
     * @param string $variable_name Variable name to check (without $)
     * @return void
     */
    protected function assertVariableFollowsCamelCase(string $variable_name): void
    {
        $this->assertTrue(
            ctype_lower($variable_name[0]) && !strpos($variable_name, '_'),
            "Variable \${$variable_name} should follow camelCase convention (first letter lowercase, no underscores)"
        );
    }

    /**
     * Assert constant follows SCREAMING_SNAKE_CASE convention
     *
     * @param string $constant_name Constant name to check
     * @return void
     */
    protected function assertConstantFollowsScreamingSnakeCase(string $constant_name): void
    {
        $this->assertTrue(
            ctype_upper($constant_name) && preg_match('/^[A-Z][A-Z0-9_]*$/', $constant_name),
            "Constant {$constant_name} should follow SCREAMING_SNAKE_CASE convention (all uppercase with underscores)"
        );
    }

    /**
     * Assert hook name follows WordPress convention
     *
     * @param string $hook_name Hook name to check
     * @return void
     */
    protected function assertHookFollowsWordPressConvention(string $hook_name): void
    {
        $this->assertTrue(
            preg_match('/^[a-z][a-z0-9_]*$/', $hook_name) && strpos($hook_name, 'woo_ai_assistant_') === 0,
            "Hook {$hook_name} should follow WordPress convention (lowercase with underscores, prefixed with 'woo_ai_assistant_')"
        );
    }

    /**
     * Mock API response
     *
     * @param string $endpoint API endpoint
     * @param array $response Mock response data
     * @return void
     */
    protected function mockApiResponse(string $endpoint, array $response): void
    {
        add_filter('woo_ai_assistant_mock_api_response_' . $endpoint, function() use ($response) {
            return $response;
        });
    }

    /**
     * Assert array contains expected structure
     *
     * @param array $expected Expected structure
     * @param array $actual Actual array to check
     * @param string $message Optional message
     * @return void
     */
    protected function assertArrayStructure(array $expected, array $actual, string $message = ''): void
    {
        foreach ($expected as $key => $type) {
            $this->assertArrayHasKey(
                $key, 
                $actual, 
                $message ?: "Array should contain key '{$key}'"
            );

            if (is_string($type)) {
                $this->assertInternalType(
                    $type, 
                    $actual[$key], 
                    $message ?: "Key '{$key}' should be of type '{$type}'"
                );
            } elseif (is_array($type) && is_array($actual[$key])) {
                $this->assertArrayStructure($type, $actual[$key], $message);
            }
        }
    }

    /**
     * Get reflection method (including private/protected)
     *
     * @param object $instance Object instance
     * @param string $method_name Method name
     * @return \ReflectionMethod
     */
    protected function getReflectionMethod(object $instance, string $method_name): \ReflectionMethod
    {
        $reflection = new \ReflectionClass($instance);
        $method = $reflection->getMethod($method_name);
        $method->setAccessible(true);
        
        return $method;
    }

    /**
     * Get reflection property (including private/protected)
     *
     * @param object $instance Object instance
     * @param string $property_name Property name
     * @return \ReflectionProperty
     */
    protected function getReflectionProperty(object $instance, string $property_name): \ReflectionProperty
    {
        $reflection = new \ReflectionClass($instance);
        $property = $reflection->getProperty($property_name);
        $property->setAccessible(true);
        
        return $property;
    }

    /**
     * Invoke private/protected method
     *
     * @param object $instance Object instance
     * @param string $method_name Method name
     * @param array $args Method arguments
     * @return mixed Method return value
     */
    protected function invokeMethod(object $instance, string $method_name, array $args = [])
    {
        $method = $this->getReflectionMethod($instance, $method_name);
        return $method->invokeArgs($instance, $args);
    }

    /**
     * Get private/protected property value
     *
     * @param object $instance Object instance
     * @param string $property_name Property name
     * @return mixed Property value
     */
    protected function getPropertyValue(object $instance, string $property_name)
    {
        $property = $this->getReflectionProperty($instance, $property_name);
        return $property->getValue($instance);
    }

    /**
     * Set private/protected property value
     *
     * @param object $instance Object instance
     * @param string $property_name Property name
     * @param mixed $value Property value
     * @return void
     */
    protected function setPropertyValue(object $instance, string $property_name, $value): void
    {
        $property = $this->getReflectionProperty($instance, $property_name);
        $property->setValue($instance, $value);
    }
}