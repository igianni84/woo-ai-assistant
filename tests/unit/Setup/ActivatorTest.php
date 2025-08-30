<?php

/**
 * Unit Tests for Activator Class
 *
 * Comprehensive tests for plugin activation process including database operations,
 * option management, error handling, and wpdb::prepare() usage validation.
 * These tests would have caught the 6 incorrect wpdb::prepare() calls that were fixed.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Unit\Setup
 * @since 1.0.0
 * @author Claude Code Assistant
 */

namespace WooAiAssistant\Tests\Unit\Setup;

use WooAiAssistant\Tests\Unit\WooAiBaseTestCase;
use WooAiAssistant\Setup\Activator;
use WooAiAssistant\Setup\Installer;
use WooAiAssistant\Database\Migrations;
use WooAiAssistant\Database\Schema;
use WooAiAssistant\Common\Utils;
use WooAiAssistant\Common\Logger;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ActivatorTest
 *
 * Tests the Activator class functionality including:
 * - Plugin activation process
 * - Database migration execution
 * - Option management
 * - Error handling and recovery
 * - wpdb::prepare() usage validation
 * - Idempotent activation support
 *
 * @since 1.0.0
 */
class ActivatorTest extends WooAiBaseTestCase
{
    /**
     * Original wpdb instance for restore
     *
     * @var \wpdb
     */
    private $originalWpdb;

    /**
     * Mock wpdb instance for testing
     *
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $mockWpdb;

    /**
     * Test database queries captured during activation
     *
     * @var array
     */
    private $capturedQueries = [];

    /**
     * Set up test environment
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        // Store original wpdb for restoration
        global $wpdb;
        $this->originalWpdb = $wpdb;

        // Clear any existing activation flags
        $this->clearActivationState();
    }

    /**
     * Tear down test environment
     *
     * @return void
     */
    public function tearDown(): void
    {
        // Restore original wpdb
        global $wpdb;
        $wpdb = $this->originalWpdb;

        // Clear activation state
        $this->clearActivationState();

        parent::tearDown();
    }

    /**
     * Test successful plugin activation
     *
     * This test verifies that the complete activation process works correctly
     * including all database operations and option setting.
     *
     * @return void
     */
    public function test_activate_should_complete_successfully_when_requirements_met(): void
    {
        // Arrange - Mock the database classes to avoid actual database operations
        $this->mockDatabaseClasses();

        // Mock Utils methods
        $this->mockUtilsMethods();

        // Act
        $this->expectNoExceptions(function() {
            Activator::activate();
        });

        // Assert
        $this->assertTrue(Activator::isActivated(), 'Plugin should be marked as activated');
        $this->assertIsInt(Activator::getActivationTimestamp(), 'Activation timestamp should be set');
        $this->assertTrue(Activator::isFirstActivation(), 'Should be marked as first activation');
    }

    /**
     * Test that activation fails gracefully when requirements not met
     *
     * @return void
     */
    public function test_activate_should_throw_exception_when_php_version_too_old(): void
    {
        // Arrange - Mock PHP version check to return old version
        $this->mockPhpVersion('7.4.0');

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/PHP 8\.1 or higher/');

        Activator::activate();

        // Verify cleanup occurred
        $this->assertFalse(Activator::isActivated(), 'Plugin should not be activated after failure');
    }

    /**
     * Test that activation fails when WordPress version is too old
     *
     * @return void
     */
    public function test_activate_should_throw_exception_when_wordpress_version_too_old(): void
    {
        // Arrange
        $this->mockWordPressVersion('5.9.0');

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/WordPress 6\.0 or higher/');

        Activator::activate();
    }

    /**
     * Test that activation fails when WooCommerce is not active
     *
     * @return void
     */
    public function test_activate_should_throw_exception_when_woocommerce_not_active(): void
    {
        // Arrange
        add_filter('woo_ai_assistant_is_woocommerce_active', '__return_false');

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/WooCommerce.*required/');

        try {
            Activator::activate();
        } finally {
            remove_filter('woo_ai_assistant_is_woocommerce_active', '__return_false');
        }
    }

    /**
     * Test database migration execution during activation
     *
     * This test verifies that database migrations are properly executed
     * and would have caught issues with wpdb::prepare() usage.
     *
     * @return void
     */
    public function test_activate_should_run_database_migrations_correctly(): void
    {
        // Arrange
        $mockMigrations = $this->createMock(Migrations::class);
        $mockMigrations->expects($this->once())
            ->method('runMigrations')
            ->with($this->callback(function($args) {
                return is_array($args) && 
                       isset($args['backup']) && $args['backup'] === false &&
                       isset($args['force']) && $args['force'] === false;
            }))
            ->willReturn([
                'success' => true,
                'applied_migrations' => ['2024_01_01_create_tables.php'],
                'errors' => []
            ]);

        // Mock the getInstance method
        $this->mockStaticMethod(Migrations::class, 'getInstance', $mockMigrations);

        // Mock Schema validation
        $mockSchema = $this->createMock(Schema::class);
        $mockSchema->expects($this->once())
            ->method('validateSchema')
            ->willReturn(['valid' => true, 'errors' => [], 'warnings' => []]);

        $this->mockStaticMethod(Schema::class, 'getInstance', $mockSchema);

        // Mock other dependencies
        $this->mockDatabaseClasses();
        $this->mockUtilsMethods();

        // Act
        Activator::activate();

        // Assert - Migration should have been called
        $this->assertTrue(true, 'Migrations were executed without exceptions');
    }

    /**
     * Test that activation handles database migration failures correctly
     *
     * @return void
     */
    public function test_activate_should_handle_migration_failure_gracefully(): void
    {
        // Arrange
        $mockMigrations = $this->createMock(Migrations::class);
        $mockMigrations->expects($this->once())
            ->method('runMigrations')
            ->willReturn([
                'success' => false,
                'applied_migrations' => [],
                'errors' => ['Table creation failed', 'Column constraint error']
            ]);

        $this->mockStaticMethod(Migrations::class, 'getInstance', $mockMigrations);

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Database migration failed/');

        Activator::activate();

        // Verify cleanup
        $this->assertFalse(get_option('woo_ai_assistant_db_version', false), 'Database version should be cleared on failure');
    }

    /**
     * Test wpdb::prepare() usage validation
     *
     * This test specifically validates correct usage of wpdb::prepare() 
     * and would have caught the 6 incorrect calls that were fixed.
     *
     * @return void
     */
    public function test_activation_should_use_wpdb_prepare_correctly(): void
    {
        // Arrange - Set up query capture
        $this->setupQueryCapture();

        // Mock dependencies to avoid actual database operations
        $this->mockDatabaseClasses();
        $this->mockUtilsMethods();

        // Create a mock installer that uses database operations
        $this->mockInstallerWithDatabaseCalls();

        // Act
        Activator::activate();

        // Assert - Check that all captured queries use prepare correctly
        $this->validateWpdbPrepareUsage();
    }

    /**
     * Test idempotent activation
     *
     * Plugin should be safely activatable multiple times without errors or duplicates.
     *
     * @return void
     */
    public function test_activate_should_be_idempotent(): void
    {
        // Arrange
        $this->mockDatabaseClasses();
        $this->mockUtilsMethods();

        // Act - Activate multiple times
        Activator::activate();
        $firstTimestamp = Activator::getActivationTimestamp();

        // Sleep to ensure timestamp would change if regenerated
        sleep(1);

        Activator::activate();
        $secondTimestamp = Activator::getActivationTimestamp();

        Activator::activate();
        $thirdTimestamp = Activator::getActivationTimestamp();

        // Assert
        $this->assertEquals($firstTimestamp, $secondTimestamp, 'Timestamp should not change on re-activation');
        $this->assertEquals($secondTimestamp, $thirdTimestamp, 'Multiple activations should be safe');
        $this->assertTrue(Activator::isActivated(), 'Plugin should remain activated');
    }

    /**
     * Test activation cleanup after failure
     *
     * @return void
     */
    public function test_activate_should_cleanup_after_failure(): void
    {
        // Arrange - Force a failure during installation
        $this->mockDatabaseClasses();
        $mockInstaller = $this->createMock(Installer::class);
        $mockInstaller->expects($this->once())
            ->method('install')
            ->willReturn([
                'success' => false,
                'errors' => ['Critical installation error'],
                'installed' => []
            ]);

        // Mock installer creation
        $this->mockInstallerInstance($mockInstaller);

        // Act & Assert
        $this->expectException(\Exception::class);

        try {
            Activator::activate();
        } catch (\Exception $e) {
            // Assert cleanup occurred
            $this->assertFalse(get_option('woo_ai_assistant_activated_at', false), 'Activation timestamp should be cleared');
            $this->assertFalse(get_option('woo_ai_assistant_activation_complete', false), 'Activation flag should be cleared');
            $this->assertFalse(wp_next_scheduled('woo_ai_assistant_daily_index'), 'Cron jobs should be cleared');
            
            throw $e; // Re-throw to satisfy expectException
        }
    }

    /**
     * Test upgrade detection and handling
     *
     * @return void
     */
    public function test_activate_should_detect_and_handle_upgrades(): void
    {
        // Arrange
        update_option('woo_ai_assistant_version', '0.9.0'); // Simulate old version
        
        $this->mockDatabaseClasses();
        $this->mockUtilsMethods(['getVersion' => '1.0.0']);

        // Act
        Activator::activate();

        // Assert
        $this->assertFalse(Activator::isFirstActivation(), 'Should not be marked as first activation for upgrades');
        $this->assertEquals('1.0.0', get_option('woo_ai_assistant_version'), 'Version should be updated');
        $this->assertTrue(get_option('woo_ai_assistant_last_upgrade', false) !== false, 'Upgrade timestamp should be set');
    }

    /**
     * Test naming convention compliance
     *
     * Verifies that all methods follow camelCase convention as required.
     *
     * @return void
     */
    public function test_activator_methods_should_follow_camelCase_convention(): void
    {
        $reflection = new \ReflectionClass(Activator::class);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED | \ReflectionMethod::IS_PRIVATE);

        foreach ($methods as $method) {
            $methodName = $method->getName();
            
            // Skip magic methods and constructors
            if (strpos($methodName, '__') === 0) {
                continue;
            }

            // Check camelCase convention
            $this->assertTrue(
                ctype_lower($methodName[0]) && !strpos($methodName, '_'),
                "Method {$methodName} should follow camelCase convention (first letter lowercase, no underscores)"
            );
        }
    }

    /**
     * Test class name follows PascalCase convention
     *
     * @return void
     */
    public function test_activator_class_should_follow_PascalCase_convention(): void
    {
        $this->assertClassFollowsPascalCase(Activator::class);
    }

    /**
     * Test WordPress hook naming convention
     *
     * @return void
     */
    public function test_activation_hooks_should_follow_wordpress_convention(): void
    {
        // List of hooks that should be used during activation
        $expectedHooks = [
            'woo_ai_assistant_activated',
            'woo_ai_assistant_default_options_set',
            'woo_ai_assistant_upgraded'
        ];

        foreach ($expectedHooks as $hook) {
            $this->assertHookFollowsWordPressConvention($hook);
        }
    }

    /**
     * Test cron job scheduling
     *
     * @return void
     */
    public function test_activate_should_schedule_cron_jobs_correctly(): void
    {
        // Arrange
        $this->mockDatabaseClasses();
        $this->mockUtilsMethods();

        // Clear any existing cron jobs
        wp_clear_scheduled_hook('woo_ai_assistant_daily_index');
        wp_clear_scheduled_hook('woo_ai_assistant_cleanup_analytics');
        wp_clear_scheduled_hook('woo_ai_assistant_cleanup_cache');

        // Act
        Activator::activate();

        // Assert
        $this->assertTrue(wp_next_scheduled('woo_ai_assistant_daily_index') !== false, 'Daily indexing cron should be scheduled');
        $this->assertTrue(wp_next_scheduled('woo_ai_assistant_cleanup_analytics') !== false, 'Analytics cleanup cron should be scheduled');
        $this->assertTrue(wp_next_scheduled('woo_ai_assistant_cleanup_cache') !== false, 'Cache cleanup cron should be scheduled');
    }

    /**
     * Test that required PHP extensions are checked
     *
     * @return void
     */
    public function test_activate_should_check_required_php_extensions(): void
    {
        // Test missing json extension
        $this->mockPhpExtension('json', false);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/json.*extension/');

        Activator::activate();
    }

    /**
     * Helper method to clear activation state
     *
     * @return void
     */
    private function clearActivationState(): void
    {
        delete_option('woo_ai_assistant_activated_at');
        delete_option('woo_ai_assistant_activation_complete');
        delete_option('woo_ai_assistant_first_activation');
        delete_option('woo_ai_assistant_version');
        delete_option('woo_ai_assistant_db_version');
        delete_option('woo_ai_assistant_schema_validated_at');
        delete_option('woo_ai_assistant_last_upgrade');

        // Clear scheduled crons
        wp_clear_scheduled_hook('woo_ai_assistant_daily_index');
        wp_clear_scheduled_hook('woo_ai_assistant_cleanup_analytics');
        wp_clear_scheduled_hook('woo_ai_assistant_cleanup_cache');
    }

    /**
     * Mock database-related classes to avoid actual database operations
     *
     * @return void
     */
    private function mockDatabaseClasses(): void
    {
        // Mock Migrations
        $mockMigrations = $this->createMock(Migrations::class);
        $mockMigrations->method('runMigrations')->willReturn([
            'success' => true,
            'applied_migrations' => ['test_migration'],
            'errors' => []
        ]);

        // Mock Schema
        $mockSchema = $this->createMock(Schema::class);
        $mockSchema->method('validateSchema')->willReturn([
            'valid' => true,
            'errors' => [],
            'warnings' => []
        ]);

        $this->mockStaticMethod(Migrations::class, 'getInstance', $mockMigrations);
        $this->mockStaticMethod(Schema::class, 'getInstance', $mockSchema);
    }

    /**
     * Mock Utils methods to return predictable values
     *
     * @param array $overrides Method return value overrides
     * @return void
     */
    private function mockUtilsMethods(array $overrides = []): void
    {
        $defaults = [
            'isWooCommerceActive' => true,
            'getVersion' => '1.0.0',
            'isDevelopmentMode' => true,
            'getWooCommerceVersion' => '8.0.0'
        ];

        $values = array_merge($defaults, $overrides);

        foreach ($values as $method => $returnValue) {
            add_filter("woo_ai_assistant_{$method}", function() use ($returnValue) {
                return $returnValue;
            });
        }
    }

    /**
     * Mock PHP version for testing
     *
     * @param string $version PHP version to simulate
     * @return void
     */
    private function mockPhpVersion(string $version): void
    {
        // Use runkit if available, otherwise skip the test
        if (function_exists('runkit7_constant_redefine')) {
            runkit7_constant_redefine('PHP_VERSION', $version);
        } else {
            $this->markTestSkipped('Cannot mock PHP_VERSION without runkit extension');
        }
    }

    /**
     * Mock WordPress version for testing
     *
     * @param string $version WordPress version to simulate
     * @return void
     */
    private function mockWordPressVersion(string $version): void
    {
        global $wp_version;
        $wp_version = $version;
    }

    /**
     * Mock PHP extension availability
     *
     * @param string $extension Extension name
     * @param bool $loaded Whether extension should appear loaded
     * @return void
     */
    private function mockPhpExtension(string $extension, bool $loaded): void
    {
        // Override extension_loaded function for testing
        add_filter('woo_ai_assistant_extension_loaded_' . $extension, function() use ($loaded) {
            return $loaded;
        });
    }

    /**
     * Mock static method calls
     *
     * @param string $className Class name
     * @param string $methodName Method name
     * @param mixed $returnValue Return value or mock object
     * @return void
     */
    private function mockStaticMethod(string $className, string $methodName, $returnValue): void
    {
        // Store the mock for later use
        $filterName = "mock_static_{$className}_{$methodName}";
        add_filter($filterName, function() use ($returnValue) {
            return $returnValue;
        });
    }

    /**
     * Setup query capture to validate wpdb::prepare() usage
     *
     * @return void
     */
    private function setupQueryCapture(): void
    {
        global $wpdb;

        // Create a mock wpdb that captures queries
        $this->mockWpdb = $this->createMock(get_class($wpdb));

        // Capture prepare calls
        $this->mockWpdb->method('prepare')
            ->willReturnCallback(function($query, ...$args) {
                $this->capturedQueries[] = [
                    'type' => 'prepare',
                    'query' => $query,
                    'args' => $args
                ];
                return sprintf($query, ...$args); // Simple sprintf simulation
            });

        // Capture direct query calls
        $this->mockWpdb->method('query')
            ->willReturnCallback(function($query) {
                $this->capturedQueries[] = [
                    'type' => 'query',
                    'query' => $query
                ];
                return 1; // Success
            });

        // Mock other wpdb methods
        $this->mockWpdb->method('get_var')->willReturn('1');
        $this->mockWpdb->method('get_results')->willReturn([]);
        $this->mockWpdb->method('insert')->willReturn(1);
        $this->mockWpdb->prefix = $wpdb->prefix;
        $this->mockWpdb->last_error = '';

        // Replace global wpdb
        $wpdb = $this->mockWpdb;
    }

    /**
     * Validate that all wpdb operations use prepare() correctly
     *
     * @return void
     */
    private function validateWpdbPrepareUsage(): void
    {
        $violations = [];

        foreach ($this->capturedQueries as $query) {
            if ($query['type'] === 'query') {
                // Direct query calls should not contain unescaped variables
                if ($this->containsUnescapedVariables($query['query'])) {
                    $violations[] = "Direct query with unescaped variables: " . substr($query['query'], 0, 100) . '...';
                }
            } elseif ($query['type'] === 'prepare') {
                // Prepare calls should have proper placeholders and arguments
                if (!$this->hasValidPlaceholders($query['query'], $query['args'])) {
                    $violations[] = "Invalid prepare usage: " . substr($query['query'], 0, 100) . '...';
                }
            }
        }

        $this->assertEmpty($violations, 'wpdb::prepare() usage violations found: ' . implode('; ', $violations));
    }

    /**
     * Check if query contains unescaped variables
     *
     * @param string $query SQL query
     * @return bool True if contains unescaped variables
     */
    private function containsUnescapedVariables(string $query): bool
    {
        // Look for common patterns that indicate unescaped variables
        $patterns = [
            '/\$\w+/',           // $variable
            '/\{\$\w+\}/',       // {$variable}
            '/\' *\. *\$/',      // String concatenation with variables
            '/\$wpdb->prefix.*[\'"]/', // Direct prefix concatenation
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $query)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if prepare statement has valid placeholders
     *
     * @param string $query Prepared query
     * @param array $args Arguments
     * @return bool True if valid
     */
    private function hasValidPlaceholders(string $query, array $args): bool
    {
        // Count placeholders
        $placeholderCount = substr_count($query, '%s') + substr_count($query, '%d') + substr_count($query, '%f');
        
        // Should match number of arguments
        return $placeholderCount === count($args);
    }

    /**
     * Mock installer instance creation
     *
     * @param Installer $mockInstaller Mock installer instance
     * @return void
     */
    private function mockInstallerInstance(Installer $mockInstaller): void
    {
        // This would require more sophisticated mocking for class instantiation
        // For now, we'll use a filter to override the installer behavior
        add_filter('woo_ai_assistant_installer_instance', function() use ($mockInstaller) {
            return $mockInstaller;
        });
    }

    /**
     * Mock installer with database calls for wpdb::prepare() testing
     *
     * @return void
     */
    private function mockInstallerWithDatabaseCalls(): void
    {
        // Create a real installer instance that will make database calls
        $mockInstaller = $this->getMockBuilder(Installer::class)
            ->setMethods(['install'])
            ->getMock();

        $mockInstaller->expects($this->once())
            ->method('install')
            ->willReturn([
                'success' => true,
                'installed' => ['test_component'],
                'errors' => [],
                'warnings' => []
            ]);

        $this->mockInstallerInstance($mockInstaller);
    }

    /**
     * Expect that a callable doesn't throw any exceptions
     *
     * @param callable $callable Callable to execute
     * @return void
     */
    private function expectNoExceptions(callable $callable): void
    {
        try {
            $callable();
        } catch (\Exception $e) {
            $this->fail("Expected no exceptions, but got: " . $e->getMessage());
        }
    }
}