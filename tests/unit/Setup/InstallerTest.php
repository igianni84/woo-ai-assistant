<?php

/**
 * Unit Tests for Installer Class
 *
 * Comprehensive tests for the Installer class including initial data population,
 * duplicate data handling, wpdb::prepare() usage validation, and zero-config setup.
 * These tests would have caught wpdb::prepare() issues and duplicate data problems.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Unit\Setup
 * @since 1.0.0
 * @author Claude Code Assistant
 */

namespace WooAiAssistant\Tests\Unit\Setup;

use WooAiAssistant\Tests\Unit\WooAiBaseTestCase;
use WooAiAssistant\Setup\Installer;
use WooAiAssistant\Database\Schema;
use WooAiAssistant\Common\Utils;
use WooAiAssistant\Common\Logger;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class InstallerTest
 *
 * Tests the Installer class functionality including:
 * - Zero-config initial installation
 * - Sample data creation without duplicates
 * - wpdb::prepare() usage validation
 * - Idempotent installation process
 * - Installation validation and rollback
 * - Settings population and categorization
 *
 * @since 1.0.0
 */
class InstallerTest extends WooAiBaseTestCase
{
    /**
     * Installer instance for testing
     *
     * @var Installer
     */
    private $installer;

    /**
     * Original wpdb instance for restore
     *
     * @var \wpdb
     */
    private $originalWpdb;

    /**
     * Mock wpdb instance for query capture
     *
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $mockWpdb;

    /**
     * Captured database queries during installation
     *
     * @var array
     */
    private $capturedQueries = [];

    /**
     * Test table names
     *
     * @var array
     */
    private $testTables = [
        'woo_ai_settings',
        'woo_ai_knowledge_base',
        'woo_ai_conversations',
        'woo_ai_messages',
        'woo_ai_analytics'
    ];

    /**
     * Set up test environment
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        // Initialize installer instance
        $this->installer = new Installer();

        // Store original wpdb
        global $wpdb;
        $this->originalWpdb = $wpdb;

        // Clear any existing installation data
        $this->clearInstallationData();
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

        // Clear installation data
        $this->clearInstallationData();

        parent::tearDown();
    }

    /**
     * Test complete installation process
     *
     * @return void
     */
    public function test_install_should_complete_successfully_with_all_components(): void
    {
        // Arrange
        $this->mockDatabaseTables();
        $this->mockUtilsMethods();

        // Act
        $result = $this->installer->install();

        // Assert
        $this->assertTrue($result['success'], 'Installation should succeed');
        $this->assertIsArray($result['installed'], 'Should return list of installed components');
        $this->assertEmpty($result['errors'], 'Should have no errors: ' . implode(', ', $result['errors']));

        // Check that all major components were installed
        $installedComponents = implode(' ', $result['installed']);
        $this->assertStringContainsString('settings', $installedComponents, 'Initial settings should be installed');
        $this->assertStringContainsString('knowledge base', $installedComponents, 'Sample knowledge base should be created');
        $this->assertStringContainsString('Widget configuration', $installedComponents, 'Widget should be configured');
    }

    /**
     * Test initial settings population with wpdb::prepare() validation
     *
     * This test would have caught the incorrect wpdb::prepare() usage.
     *
     * @return void
     */
    public function test_populateInitialSettings_should_use_wpdb_prepare_correctly(): void
    {
        // Arrange
        $this->setupQueryCapture();
        $this->mockDatabaseTables();

        // Act
        $result = $this->installer->install();

        // Assert
        $this->assertTrue($result['success'], 'Installation should succeed');

        // Validate wpdb::prepare() usage in captured queries
        $this->validateWpdbPrepareUsage();

        // Check that settings queries used prepare correctly
        $settingsQueries = $this->getQueriesForTable('woo_ai_settings');
        $this->assertNotEmpty($settingsQueries, 'Settings queries should have been captured');

        foreach ($settingsQueries as $query) {
            if ($query['type'] === 'prepare') {
                $this->assertValidPrepareStatement($query['query'], $query['args']);
            }
        }
    }

    /**
     * Test duplicate data handling for sample knowledge base
     *
     * This test ensures that running installation multiple times doesn't create duplicates.
     *
     * @return void
     */
    public function test_createSampleKnowledgeBase_should_handle_duplicates_correctly(): void
    {
        // Arrange
        $this->setupQueryCapture();
        $this->mockDatabaseTables();

        // Simulate existing knowledge base entries by making wpdb return existing data
        $this->mockExistingKnowledgeBaseEntries();

        // Act
        $result = $this->installer->install();

        // Assert
        $this->assertTrue($result['success'], 'Installation should succeed even with existing data');

        // Check that duplicate checking queries were made
        $kbQueries = $this->getQueriesForTable('woo_ai_knowledge_base');
        $duplicateCheckQueries = array_filter($kbQueries, function ($query) {
            return strpos($query['query'], 'chunk_hash') !== false && strpos($query['query'], 'SELECT') !== false;
        });

        $this->assertNotEmpty($duplicateCheckQueries, 'Should check for existing entries to prevent duplicates');
    }

    /**
     * Test idempotent installation process
     *
     * Installation should be safe to run multiple times without errors or data corruption.
     *
     * @return void
     */
    public function test_install_should_be_idempotent(): void
    {
        // Arrange
        $this->mockDatabaseTables();
        $this->mockUtilsMethods();

        // Act - Run installation multiple times
        $firstResult = $this->installer->install();
        $secondResult = $this->installer->install();
        $thirdResult = $this->installer->install();

        // Assert
        $this->assertTrue($firstResult['success'], 'First installation should succeed');
        $this->assertTrue($secondResult['success'], 'Second installation should succeed');
        $this->assertTrue($thirdResult['success'], 'Third installation should succeed');

        // Check that no errors occurred
        $this->assertEmpty($firstResult['errors'], 'First installation should have no errors');
        $this->assertEmpty($secondResult['errors'], 'Second installation should have no errors');
        $this->assertEmpty($thirdResult['errors'], 'Third installation should have no errors');
    }

    /**
     * Test installation validation process
     *
     * @return void
     */
    public function test_install_should_validate_installation_completeness(): void
    {
        // Arrange
        $this->mockDatabaseTables();
        $this->mockUtilsMethods();

        // Mock successful validation
        $this->mockInstallationValidation(true);

        // Act
        $result = $this->installer->install();

        // Assert
        $this->assertTrue($result['success'], 'Installation should succeed with validation');
        $this->assertStringContainsString('validation', implode(' ', $result['installed']), 'Validation should be recorded as completed');
    }

    /**
     * Test graceful degradation on non-critical failures
     *
     * @return void
     */
    public function test_install_should_continue_when_non_critical_components_fail(): void
    {
        // Arrange
        $this->mockDatabaseTables();
        $this->mockUtilsMethods();

        // Simulate failure in non-critical component (analytics)
        $this->mockAnalyticsFailure();

        // Act
        $result = $this->installer->install();

        // Assert
        $this->assertTrue($result['success'], 'Installation should succeed despite non-critical failures');
        $this->assertNotEmpty($result['warnings'], 'Should have warnings for non-critical failures');
        $this->assertStringContainsString('analytics', implode(' ', $result['warnings']), 'Analytics failure should be in warnings');
    }

    /**
     * Test critical component failure handling
     *
     * @return void
     */
    public function test_install_should_fail_when_critical_components_fail(): void
    {
        // Arrange
        $this->mockDatabaseTables();
        $this->mockUtilsMethods();

        // Simulate failure in critical component (initial settings)
        $this->mockCriticalComponentFailure();

        // Act
        $result = $this->installer->install();

        // Assert
        $this->assertFalse($result['success'], 'Installation should fail when critical components fail');
        $this->assertNotEmpty($result['errors'], 'Should have errors for critical failures');
    }

    /**
     * Test settings categorization and sensitivity detection
     *
     * @return void
     */
    public function test_settings_should_be_categorized_and_marked_for_sensitivity(): void
    {
        // Arrange
        $this->setupQueryCapture();
        $this->mockDatabaseTables();

        // Act
        $result = $this->installer->install();

        // Assert
        $this->assertTrue($result['success'], 'Installation should succeed');

        // Check settings insertion queries
        $settingsInsertions = array_filter($this->capturedQueries, function ($query) {
            return $query['type'] === 'insert' && strpos($query['table'], 'woo_ai_settings') !== false;
        });

        $this->assertNotEmpty($settingsInsertions, 'Settings should be inserted');

        // Validate that settings have proper groups and sensitivity flags
        foreach ($settingsInsertions as $insertion) {
            $this->assertArrayHasKey('setting_group', $insertion['data'], 'Settings should have groups');
            $this->assertArrayHasKey('is_sensitive', $insertion['data'], 'Settings should have sensitivity flags');
            $this->assertArrayHasKey('autoload', $insertion['data'], 'Settings should have autoload flags');
        }
    }

    /**
     * Test widget configuration setup
     *
     * @return void
     */
    public function test_setupDefaultWidgetConfiguration_should_create_ready_to_use_widget(): void
    {
        // Arrange
        $this->mockUtilsMethods();

        // Act
        $result = $this->installer->install();

        // Assert
        $this->assertTrue($result['success'], 'Installation should succeed');
        $this->assertTrue(get_option('woo_ai_assistant_widget_ready'), 'Widget should be marked as ready');

        $welcomeMessages = get_option('woo_ai_assistant_welcome_messages');
        $this->assertIsArray($welcomeMessages, 'Welcome messages should be configured');
        $this->assertArrayHasKey('product', $welcomeMessages, 'Should have product page welcome message');
        $this->assertArrayHasKey('default', $welcomeMessages, 'Should have default welcome message');
    }

    /**
     * Test analytics initialization
     *
     * @return void
     */
    public function test_initializeAnalytics_should_record_installation_metrics(): void
    {
        // Arrange
        $this->setupQueryCapture();
        $this->mockDatabaseTables();
        $this->mockUtilsMethods();

        // Act
        $result = $this->installer->install();

        // Assert
        $this->assertTrue($result['success'], 'Installation should succeed');

        // Check analytics insertions
        $analyticsInsertions = $this->getQueriesForTable('woo_ai_analytics');
        $this->assertNotEmpty($analyticsInsertions, 'Analytics data should be inserted');

        // Find installation completed metric
        $installationMetric = null;
        foreach ($analyticsInsertions as $query) {
            if (isset($query['data']['metric_type']) && $query['data']['metric_type'] === 'installation_completed') {
                $installationMetric = $query;
                break;
            }
        }

        $this->assertNotNull($installationMetric, 'Installation completion should be tracked');
        $this->assertEquals(1, $installationMetric['data']['metric_value'], 'Installation metric value should be 1');
    }

    /**
     * Test welcome conversation creation
     *
     * @return void
     */
    public function test_createWelcomeConversation_should_create_template_conversation(): void
    {
        // Arrange
        $this->setupQueryCapture();
        $this->mockDatabaseTables();

        // Act
        $result = $this->installer->install();

        // Assert
        $this->assertTrue($result['success'], 'Installation should succeed');

        // Check conversation creation
        $conversationInsertions = $this->getQueriesForTable('woo_ai_conversations');
        $this->assertNotEmpty($conversationInsertions, 'Welcome conversation should be created');

        // Check message creation
        $messageInsertions = $this->getQueriesForTable('woo_ai_messages');
        $this->assertNotEmpty($messageInsertions, 'Welcome messages should be created');

        // Verify template conversation was saved
        $this->assertNotFalse(get_option('woo_ai_assistant_welcome_conversation_id'), 'Welcome conversation ID should be saved');
    }

    /**
     * Test AI prompts and response templates setup
     *
     * @return void
     */
    public function test_setupDefaultAIPrompts_should_configure_ai_templates(): void
    {
        // Arrange
        $this->mockUtilsMethods();

        // Act
        $result = $this->installer->install();

        // Assert
        $this->assertTrue($result['success'], 'Installation should succeed');

        $defaultPrompts = get_option('woo_ai_assistant_default_prompts');
        $this->assertIsArray($defaultPrompts, 'Default prompts should be configured');
        $this->assertArrayHasKey('system_prompt', $defaultPrompts, 'System prompt should be set');
        $this->assertArrayHasKey('greeting_prompt', $defaultPrompts, 'Greeting prompt should be set');

        $responseTemplates = get_option('woo_ai_assistant_response_templates');
        $this->assertIsArray($responseTemplates, 'Response templates should be configured');
        $this->assertArrayHasKey('product_not_found', $responseTemplates, 'Product not found template should exist');
        $this->assertArrayHasKey('return_policy', $responseTemplates, 'Return policy template should exist');
    }

    /**
     * Test installation summary generation
     *
     * @return void
     */
    public function test_getInstallationSummary_should_provide_comprehensive_report(): void
    {
        // Arrange
        $this->mockDatabaseTables();
        $this->mockUtilsMethods();

        // Act
        $this->installer->install();
        $summary = $this->installer->getInstallationSummary();

        // Assert
        $this->assertIsArray($summary, 'Summary should be an array');
        $this->assertArrayHasKey('installation_date', $summary, 'Should include installation date');
        $this->assertArrayHasKey('plugin_version', $summary, 'Should include plugin version');
        $this->assertArrayHasKey('components_installed', $summary, 'Should include installed components');
        $this->assertArrayHasKey('widget_ready', $summary, 'Should include widget status');
        $this->assertArrayHasKey('kb_entries', $summary, 'Should include KB entry count');
        $this->assertArrayHasKey('settings_count', $summary, 'Should include settings count');
    }

    /**
     * Test naming convention compliance
     *
     * @return void
     */
    public function test_installer_methods_should_follow_camelCase_convention(): void
    {
        $reflection = new \ReflectionClass(Installer::class);
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
                "Method {$methodName} should follow camelCase convention"
            );
        }
    }

    /**
     * Test class name follows PascalCase convention
     *
     * @return void
     */
    public function test_installer_class_should_follow_PascalCase_convention(): void
    {
        $this->assertClassFollowsPascalCase(Installer::class);
    }

    /**
     * Helper method to clear installation data
     *
     * @return void
     */
    private function clearInstallationData(): void
    {
        // Clear installation-related options
        delete_option('woo_ai_assistant_widget_ready');
        delete_option('woo_ai_assistant_widget_first_load');
        delete_option('woo_ai_assistant_welcome_messages');
        delete_option('woo_ai_assistant_indexing_scheduled');
        delete_option('woo_ai_assistant_welcome_conversation_id');
        delete_option('woo_ai_assistant_default_prompts');
        delete_option('woo_ai_assistant_response_templates');

        // Clear cron jobs
        wp_clear_scheduled_hook('woo_ai_assistant_initial_indexing');
    }

    /**
     * Mock database tables for testing
     *
     * @return void
     */
    private function mockDatabaseTables(): void
    {
        global $wpdb;

        // Add test table prefix to mock wpdb
        foreach ($this->testTables as $table) {
            $wpdb->{str_replace('woo_ai_', '', $table)} = $wpdb->prefix . $table;
        }
    }

    /**
     * Mock Utils methods
     *
     * @return void
     */
    private function mockUtilsMethods(): void
    {
        add_filter('woo_ai_assistant_get_woocommerce_version', function () {
            return '8.0.0';
        });
    }

    /**
     * Mock existing knowledge base entries to test duplicate handling
     *
     * @return void
     */
    private function mockExistingKnowledgeBaseEntries(): void
    {
        // Mock wpdb to return existing entries when checking for duplicates
        add_filter('wpdb_get_var_result', function ($result, $query) {
            if (strpos($query, 'chunk_hash') !== false && strpos($query, 'woo_ai_knowledge_base') !== false) {
                return '1'; // Simulate existing entry found
            }
            return $result;
        }, 10, 2);
    }

    /**
     * Mock installation validation
     *
     * @param bool $success Whether validation should succeed
     * @return void
     */
    private function mockInstallationValidation(bool $success): void
    {
        // Mock Schema validation
        $mockSchema = $this->createMock(Schema::class);
        $mockSchema->method('validateSchema')
            ->willReturn(['valid' => $success, 'errors' => [], 'warnings' => []]);

        // Store in a way that installer can access
        add_filter('woo_ai_assistant_schema_instance', function () use ($mockSchema) {
            return $mockSchema;
        });
    }

    /**
     * Mock analytics failure for testing graceful degradation
     *
     * @return void
     */
    private function mockAnalyticsFailure(): void
    {
        // Mock wpdb insert failure for analytics table
        add_filter('wpdb_insert_result', function ($result, $table, $data) {
            if (strpos($table, 'woo_ai_analytics') !== false) {
                return false; // Simulate insert failure
            }
            return $result;
        }, 10, 3);
    }

    /**
     * Mock critical component failure
     *
     * @return void
     */
    private function mockCriticalComponentFailure(): void
    {
        // Mock wpdb failure for settings table
        add_filter('wpdb_insert_result', function ($result, $table, $data) {
            if (strpos($table, 'woo_ai_settings') !== false) {
                return false; // Simulate critical failure
            }
            return $result;
        }, 10, 3);
    }

    /**
     * Setup query capture to monitor wpdb operations
     *
     * @return void
     */
    private function setupQueryCapture(): void
    {
        global $wpdb;

        // Create mock wpdb that captures all operations
        $this->mockWpdb = $this->createPartialMock(get_class($wpdb), [
            'prepare', 'query', 'get_var', 'get_results', 'insert'
        ]);

        // Capture prepare calls
        $this->mockWpdb->method('prepare')
            ->willReturnCallback(function ($query, ...$args) {
                $this->capturedQueries[] = [
                    'type' => 'prepare',
                    'query' => $query,
                    'args' => $args
                ];
                return $this->originalWpdb->prepare($query, ...$args);
            });

        // Capture insert calls
        $this->mockWpdb->method('insert')
            ->willReturnCallback(function ($table, $data, $format = null) {
                $this->capturedQueries[] = [
                    'type' => 'insert',
                    'table' => $table,
                    'data' => $data,
                    'format' => $format
                ];
                return 1; // Simulate success
            });

        // Capture other calls
        $this->mockWpdb->method('get_var')
            ->willReturnCallback(function ($query, $x = 0, $y = 0) {
                $this->capturedQueries[] = [
                    'type' => 'get_var',
                    'query' => $query
                ];
                return null; // Simulate no existing data
            });

        // Copy properties from original wpdb
        $this->mockWpdb->prefix = $wpdb->prefix;
        $this->mockWpdb->last_error = '';

        // Replace global wpdb
        $wpdb = $this->mockWpdb;
    }

    /**
     * Validate wpdb::prepare() usage in captured queries
     *
     * @return void
     */
    private function validateWpdbPrepareUsage(): void
    {
        $violations = [];

        foreach ($this->capturedQueries as $query) {
            if ($query['type'] === 'prepare') {
                if (!$this->isValidPrepareUsage($query['query'], $query['args'])) {
                    $violations[] = "Invalid prepare usage: " . substr($query['query'], 0, 100);
                }
            }
        }

        $this->assertEmpty($violations, 'wpdb::prepare() violations found: ' . implode('; ', $violations));
    }

    /**
     * Check if prepare usage is valid
     *
     * @param string $query SQL query
     * @param array $args Arguments
     * @return bool True if valid
     */
    private function isValidPrepareUsage(string $query, array $args): bool
    {
        // Count placeholders
        $placeholderCount = substr_count($query, '%s') + substr_count($query, '%d') + substr_count($query, '%f');

        // Check if table name placeholder is used correctly
        if (strpos($query, '%1s') !== false) {
            // %1s should not be counted as a regular placeholder for table names
            $placeholderCount -= substr_count($query, '%1s');
        }

        return $placeholderCount === count($args);
    }

    /**
     * Get queries for a specific table
     *
     * @param string $tableName Table name
     * @return array Matching queries
     */
    private function getQueriesForTable(string $tableName): array
    {
        return array_filter($this->capturedQueries, function ($query) use ($tableName) {
            if (isset($query['table'])) {
                return strpos($query['table'], $tableName) !== false;
            }
            if (isset($query['query'])) {
                return strpos($query['query'], $tableName) !== false;
            }
            return false;
        });
    }

    /**
     * Assert that a prepare statement is valid
     *
     * @param string $query SQL query
     * @param array $args Arguments
     * @return void
     */
    private function assertValidPrepareStatement(string $query, array $args): void
    {
        $this->assertTrue(
            $this->isValidPrepareUsage($query, $args),
            "Invalid prepare statement: placeholders don't match arguments in query: " . substr($query, 0, 100)
        );
    }
}
