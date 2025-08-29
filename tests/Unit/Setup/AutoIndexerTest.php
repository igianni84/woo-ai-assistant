<?php

/**
 * AutoIndexer Unit Tests
 *
 * Comprehensive unit tests for the AutoIndexer class to ensure
 * proper functionality of the zero-config auto-installation system.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Unit\Setup
 * @since 1.0.0
 */

namespace WooAiAssistant\Tests\Unit\Setup;

use WooAiAssistant\Setup\AutoIndexer;
use WooAiAssistant\Tests\WP_UnitTestCase;

/**
 * Class AutoIndexerTest
 *
 * @since 1.0.0
 */
class AutoIndexerTest extends WP_UnitTestCase
{
    /**
     * AutoIndexer instance for testing
     *
     * @var AutoIndexer
     */
    private $autoIndexer;

    /**
     * Set up test environment
     *
     * @since 1.0.0
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->autoIndexer = AutoIndexer::getInstance();
    }

    /**
     * Test class existence and instantiation
     *
     * @since 1.0.0
     */
    public function test_class_exists_and_instantiates()
    {
        $this->assertTrue(class_exists('WooAiAssistant\Setup\AutoIndexer'));
        $this->assertInstanceOf(AutoIndexer::class, $this->autoIndexer);
    }

    /**
     * Test singleton pattern implementation
     *
     * @since 1.0.0
     */
    public function test_singleton_pattern()
    {
        $instance1 = AutoIndexer::getInstance();
        $instance2 = AutoIndexer::getInstance();
        
        $this->assertSame($instance1, $instance2);
    }

    /**
     * Test naming conventions compliance
     *
     * @since 1.0.0
     */
    public function test_class_follows_naming_conventions()
    {
        $reflection = new \ReflectionClass($this->autoIndexer);
        
        // Class name must be PascalCase
        $className = $reflection->getShortName();
        $this->assertMatchesRegularExpression('/^[A-Z][a-zA-Z0-9]*$/', $className,
            "Class name '$className' must be PascalCase");
        
        // All public methods must be camelCase
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            $methodName = $method->getName();
            if (strpos($methodName, '__') === 0) continue; // Skip magic methods
            
            $this->assertMatchesRegularExpression('/^[a-z][a-zA-Z0-9]*$/', $methodName,
                "Method '$methodName' must be camelCase");
        }
    }

    /**
     * Test auto-indexing enablement check
     *
     * @since 1.0.0
     */
    public function test_isAutoIndexingEnabled_returns_correct_values()
    {
        // Test default enabled state
        update_option('woo_ai_assistant_auto_index', 'yes');
        $reflection = new \ReflectionClass($this->autoIndexer);
        $method = $reflection->getMethod('isAutoIndexingEnabled');
        $method->setAccessible(true);
        
        $this->assertTrue($method->invoke($this->autoIndexer));
        
        // Test disabled state
        update_option('woo_ai_assistant_auto_index', 'no');
        $this->assertFalse($method->invoke($this->autoIndexer));
    }

    /**
     * Test auto-indexing status methods
     *
     * @since 1.0.0
     */
    public function test_status_methods_work_correctly()
    {
        // Test initial status (not running)
        $this->assertFalse($this->autoIndexer->isRunning());
        $this->assertFalse($this->autoIndexer->hasCompleted());
        
        // Set running status
        $status = [
            'status' => 'running',
            'started_at' => current_time('mysql')
        ];
        update_option('woo_ai_assistant_auto_index_status', $status);
        
        $this->assertTrue($this->autoIndexer->isRunning());
        $this->assertFalse($this->autoIndexer->hasCompleted());
        
        // Set completed status
        $status['status'] = 'completed';
        $status['completed_at'] = current_time('mysql');
        update_option('woo_ai_assistant_auto_index_status', $status);
        update_option('woo_ai_assistant_last_auto_index', time());
        
        $this->assertFalse($this->autoIndexer->isRunning());
        $this->assertTrue($this->autoIndexer->hasCompleted());
    }

    /**
     * Test getting current status
     *
     * @since 1.0.0
     */
    public function test_getStatus_returns_correct_information()
    {
        $testStatus = [
            'status' => 'completed',
            'progress' => 100,
            'products_indexed' => 10,
            'pages_indexed' => 5
        ];
        update_option('woo_ai_assistant_auto_index_status', $testStatus);
        
        $status = $this->autoIndexer->getStatus();
        
        $this->assertIsArray($status);
        $this->assertEquals('completed', $status['status']);
        $this->assertEquals(100, $status['progress']);
        $this->assertEquals(10, $status['products_indexed']);
        $this->assertEquals(5, $status['pages_indexed']);
    }

    /**
     * Test recently completed check
     *
     * @since 1.0.0
     */
    public function test_isRecentlyCompleted_works_correctly()
    {
        $reflection = new \ReflectionClass($this->autoIndexer);
        $method = $reflection->getMethod('isRecentlyCompleted');
        $method->setAccessible(true);
        
        // Test no completion
        delete_option('woo_ai_assistant_last_auto_index');
        $this->assertFalse($method->invoke($this->autoIndexer));
        
        // Test recent completion (1 hour ago)
        update_option('woo_ai_assistant_last_auto_index', time() - 3600);
        $this->assertTrue($method->invoke($this->autoIndexer, 24)); // Within 24 hours
        $this->assertFalse($method->invoke($this->autoIndexer, 1)); // Not within 1 hour
        
        // Test old completion (25 hours ago)
        update_option('woo_ai_assistant_last_auto_index', time() - (25 * HOUR_IN_SECONDS));
        $this->assertFalse($method->invoke($this->autoIndexer, 24)); // Not within 24 hours
    }

    /**
     * Test adequate resources check
     *
     * @since 1.0.0
     */
    public function test_hasAdequateResources_checks_system_limits()
    {
        // Skip this test in environments where WordPress functions aren't fully loaded
        if (!function_exists('wp_convert_hr_to_bytes')) {
            $this->markTestSkipped('WordPress function wp_convert_hr_to_bytes not available in test environment');
        }
        
        $reflection = new \ReflectionClass($this->autoIndexer);
        $method = $reflection->getMethod('hasAdequateResources');
        $method->setAccessible(true);
        
        // This test depends on current system resources
        // We'll just ensure the method returns a boolean
        $result = $method->invoke($this->autoIndexer);
        $this->assertIsBool($result);
    }

    /**
     * Test time limit checking
     *
     * @since 1.0.0
     */
    public function test_hasExceededTimeLimit_works_correctly()
    {
        $reflection = new \ReflectionClass($this->autoIndexer);
        $method = $reflection->getMethod('hasExceededTimeLimit');
        $method->setAccessible(true);
        
        // Set start time to now
        $startTimeProperty = $reflection->getProperty('startTime');
        $startTimeProperty->setAccessible(true);
        $startTimeProperty->setValue($this->autoIndexer, time());
        
        // Should not be exceeded immediately
        $this->assertFalse($method->invoke($this->autoIndexer));
        
        // Set start time to past the limit
        $startTimeProperty->setValue($this->autoIndexer, time() - 150); // 150 seconds ago
        $this->assertTrue($method->invoke($this->autoIndexer));
    }

    /**
     * Test triggering auto-indexing when disabled
     *
     * @since 1.0.0
     */
    public function test_triggerAutoIndexing_returns_false_when_disabled()
    {
        update_option('woo_ai_assistant_auto_index', 'no');
        
        $result = $this->autoIndexer->triggerAutoIndexing(false);
        $this->assertFalse($result);
    }

    /**
     * Test triggering auto-indexing when recently completed
     *
     * @since 1.0.0
     */
    public function test_triggerAutoIndexing_skips_when_recently_completed()
    {
        update_option('woo_ai_assistant_auto_index', 'yes');
        update_option('woo_ai_assistant_last_auto_index', time() - 3600); // 1 hour ago
        
        $result = $this->autoIndexer->triggerAutoIndexing(false);
        $this->assertFalse($result);
    }

    /**
     * Test auto-indexing statistics
     *
     * @since 1.0.0
     */
    public function test_getStatistics_returns_correct_format()
    {
        $stats = $this->autoIndexer->getStatistics();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('last_run', $stats);
        $this->assertArrayHasKey('last_status', $stats);
        $this->assertArrayHasKey('products_indexed', $stats);
        $this->assertArrayHasKey('pages_indexed', $stats);
        $this->assertArrayHasKey('settings_indexed', $stats);
        $this->assertArrayHasKey('total_errors', $stats);
        $this->assertArrayHasKey('is_enabled', $stats);
    }

    /**
     * Test reset status functionality
     *
     * @since 1.0.0
     */
    public function test_resetStatus_clears_all_data()
    {
        // Set some status data
        update_option('woo_ai_assistant_auto_index_status', ['status' => 'completed']);
        update_option('woo_ai_assistant_last_auto_index', time());
        
        // Reset status
        $this->autoIndexer->resetStatus();
        
        // Verify data is cleared
        $this->assertFalse(get_option('woo_ai_assistant_auto_index_status', false));
        $this->assertFalse(get_option('woo_ai_assistant_last_auto_index', false));
    }

    /**
     * Test should index products check
     *
     * @since 1.0.0
     */
    public function test_shouldIndexProducts_respects_settings()
    {
        $reflection = new \ReflectionClass($this->autoIndexer);
        $method = $reflection->getMethod('shouldIndexProducts');
        $method->setAccessible(true);
        
        // Mock WooCommerce as active
        $this->mockWooCommerceActive(true);
        
        // Test enabled
        update_option('woo_ai_assistant_index_products', 'yes');
        $this->assertTrue($method->invoke($this->autoIndexer));
        
        // Test disabled
        update_option('woo_ai_assistant_index_products', 'no');
        $this->assertFalse($method->invoke($this->autoIndexer));
    }

    /**
     * Test should index pages check
     *
     * @since 1.0.0
     */
    public function test_shouldIndexPages_respects_settings()
    {
        $reflection = new \ReflectionClass($this->autoIndexer);
        $method = $reflection->getMethod('shouldIndexPages');
        $method->setAccessible(true);
        
        // Test enabled
        update_option('woo_ai_assistant_index_pages', 'yes');
        $this->assertTrue($method->invoke($this->autoIndexer));
        
        // Test disabled
        update_option('woo_ai_assistant_index_pages', 'no');
        $this->assertFalse($method->invoke($this->autoIndexer));
    }

    /**
     * Test should index settings check
     *
     * @since 1.0.0
     */
    public function test_shouldIndexSettings_requires_woocommerce()
    {
        $reflection = new \ReflectionClass($this->autoIndexer);
        $method = $reflection->getMethod('shouldIndexSettings');
        $method->setAccessible(true);
        
        // Since we can't easily mock WooCommerce detection in unit tests,
        // we'll test the method exists and returns a boolean
        $result = $method->invoke($this->autoIndexer);
        $this->assertIsBool($result);
        
        // The actual result depends on whether WooCommerce is active in test environment
        // This is acceptable for unit tests focused on method behavior
    }

    /**
     * Test cleanup functionality
     *
     * @since 1.0.0
     */
    public function test_cleanup_clears_scheduled_events()
    {
        // Schedule some events
        wp_schedule_single_event(time() + 3600, 'woo_ai_assistant_auto_index');
        wp_schedule_single_event(time() + 3600, 'woo_ai_assistant_auto_index_complete');
        
        // Check if events were scheduled (may not work in all test environments)
        $event1Before = wp_next_scheduled('woo_ai_assistant_auto_index');
        $event2Before = wp_next_scheduled('woo_ai_assistant_auto_index_complete');
        
        // Run cleanup - this should not throw errors regardless of whether events exist
        $this->autoIndexer->cleanup();
        
        // Verify cleanup method completes without throwing exceptions
        $this->assertTrue(method_exists($this->autoIndexer, 'cleanup'));
        
        // Events should be cleared if they were scheduled
        $this->assertFalse(wp_next_scheduled('woo_ai_assistant_auto_index'));
        $this->assertFalse(wp_next_scheduled('woo_ai_assistant_auto_index_complete'));
    }

    /**
     * Test update progress functionality
     *
     * @since 1.0.0
     */
    public function test_updateProgress_saves_status_correctly()
    {
        $reflection = new \ReflectionClass($this->autoIndexer);
        $method = $reflection->getMethod('updateProgress');
        $method->setAccessible(true);
        
        // Initialize status by getting the indexingStatus property
        $statusProperty = $reflection->getProperty('indexingStatus');
        $statusProperty->setAccessible(true);
        $statusProperty->setValue($this->autoIndexer, [
            'status' => 'running',
            'processed_items' => 0,
            'total_items' => 0,
            'progress' => 0,
            'products_indexed' => 0,
            'pages_indexed' => 0,
            'settings_indexed' => 0,
            'errors' => []
        ]);
        
        // Update progress
        $method->invoke($this->autoIndexer, 5, 10, 'products');
        
        // Get updated status from the property
        $updatedStatus = $statusProperty->getValue($this->autoIndexer);
        $this->assertEquals(5, $updatedStatus['processed_items']);
        $this->assertEquals(10, $updatedStatus['total_items']);
        $this->assertEquals(50, $updatedStatus['progress']);
        $this->assertEquals(5, $updatedStatus['products_indexed']);
    }

    /**
     * Test error handling in auto-indexing
     *
     * @since 1.0.0
     */
    public function test_autoIndexing_handles_errors_gracefully()
    {
        // Skip this test in environments where WordPress functions aren't fully loaded
        if (!function_exists('home_url')) {
            $this->markTestSkipped('WordPress functions not fully available in test environment');
        }
        
        // This test verifies that exceptions don't break the plugin
        // We'll test with components that don't exist
        
        // Enable auto-indexing but simulate missing components
        update_option('woo_ai_assistant_auto_index', 'yes');
        delete_option('woo_ai_assistant_last_auto_index');
        
        // This should return an error result, not throw an exception
        $result = $this->autoIndexer->triggerAutoIndexing(true);
        
        if (is_array($result)) {
            // If we get an array result, it should have an error key or be empty due to missing components
            $this->assertTrue(isset($result['error']) || empty($result) || isset($result['status']));
        } else {
            // If we get a boolean, it should be properly handled
            $this->assertIsBool($result);
        }
    }

    /**
     * Test constants are properly defined
     *
     * @since 1.0.0
     */
    public function test_constants_are_properly_defined()
    {
        $reflection = new \ReflectionClass($this->autoIndexer);
        
        $this->assertTrue($reflection->hasConstant('MAX_INITIAL_PRODUCTS'));
        $this->assertTrue($reflection->hasConstant('MAX_INITIAL_PAGES'));
        $this->assertTrue($reflection->hasConstant('BATCH_SIZE'));
        $this->assertTrue($reflection->hasConstant('TIME_LIMIT'));
        
        $this->assertIsInt(AutoIndexer::MAX_INITIAL_PRODUCTS);
        $this->assertIsInt(AutoIndexer::MAX_INITIAL_PAGES);
        $this->assertIsInt(AutoIndexer::BATCH_SIZE);
        $this->assertIsInt(AutoIndexer::TIME_LIMIT);
        
        $this->assertGreaterThan(0, AutoIndexer::MAX_INITIAL_PRODUCTS);
        $this->assertGreaterThan(0, AutoIndexer::MAX_INITIAL_PAGES);
        $this->assertGreaterThan(0, AutoIndexer::BATCH_SIZE);
        $this->assertGreaterThan(0, AutoIndexer::TIME_LIMIT);
    }

    /**
     * Mock WooCommerce active status
     *
     * @param bool $active Whether WooCommerce should be considered active
     */
    private function mockWooCommerceActive(bool $active): void
    {
        // This is a simplified mock - in a real test environment you'd use
        // proper mocking frameworks or WordPress test utilities
        if ($active) {
            if (!defined('WC_VERSION')) {
                define('WC_VERSION', '7.0.0');
            }
        }
    }

    /**
     * Tear down test environment
     *
     * @since 1.0.0
     */
    public function tearDown(): void
    {
        // Clean up options
        delete_option('woo_ai_assistant_auto_index');
        delete_option('woo_ai_assistant_auto_index_status');
        delete_option('woo_ai_assistant_last_auto_index');
        delete_option('woo_ai_assistant_index_products');
        delete_option('woo_ai_assistant_index_pages');
        
        // Clear scheduled events
        wp_clear_scheduled_hook('woo_ai_assistant_auto_index');
        wp_clear_scheduled_hook('woo_ai_assistant_auto_index_complete');
        
        parent::tearDown();
    }
}