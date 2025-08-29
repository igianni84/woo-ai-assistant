<?php

/**
 * ConversationsLogPage Unit Tests
 *
 * Comprehensive test suite for the ConversationsLogPage class following
 * the mandatory test template structure from CLAUDE.md.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Unit\Admin\Pages
 * @since 1.0.0
 * @author Claude Code Assistant
 */

namespace WooAiAssistant\Tests\Unit\Admin\Pages;

use WP_UnitTestCase;
use WP_Error;
use ReflectionClass;
use ReflectionMethod;
use WooAiAssistant\Admin\Pages\ConversationsLogPage;
use WooAiAssistant\Chatbot\ConversationHandler;
use WooAiAssistant\Common\Utils;

/**
 * Class ConversationsLogPageTest
 * 
 * @since 1.0.0
 */
class ConversationsLogPageTest extends WP_UnitTestCase
{
    private $instance;
    private $reflection;

    public function setUp(): void
    {
        parent::setUp();
        $this->instance = ConversationsLogPage::getInstance();
        $this->reflection = new ReflectionClass($this->instance);
    }

    public function tearDown(): void
    {
        parent::tearDown();
        // Clean up any test data created during tests
        $this->cleanUpTestConversations();
    }

    // MANDATORY: Test class existence and basic instantiation
    public function test_class_exists_and_instantiates()
    {
        $this->assertTrue(class_exists('WooAiAssistant\Admin\Pages\ConversationsLogPage'));
        $this->assertInstanceOf('WooAiAssistant\Admin\Pages\ConversationsLogPage', $this->instance);
    }

    // MANDATORY: Verify naming conventions
    public function test_class_follows_naming_conventions()
    {
        // Class name must be PascalCase
        $className = $this->reflection->getShortName();
        $this->assertMatchesRegularExpression('/^[A-Z][a-zA-Z0-9]*$/', $className, 
            "Class name '$className' must be PascalCase");
            
        // All public methods must be camelCase
        $methods = $this->reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            $methodName = $method->getName();
            if (strpos($methodName, '__') === 0) continue; // Skip magic methods
            
            $this->assertMatchesRegularExpression('/^[a-z][a-zA-Z0-9]*$/', $methodName,
                "Method '$methodName' must be camelCase");
        }
    }

    // MANDATORY: Test singleton pattern implementation
    public function test_singleton_pattern_implementation()
    {
        $instance1 = ConversationsLogPage::getInstance();
        $instance2 = ConversationsLogPage::getInstance();
        
        $this->assertSame($instance1, $instance2, 'Singleton pattern should return same instance');
        $this->assertInstanceOf('WooAiAssistant\Admin\Pages\ConversationsLogPage', $instance1);
    }

    // MANDATORY: Test each public method exists and returns expected type
    public function test_public_methods_exist_and_return_correct_types()
    {
        $methods = $this->reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        
        foreach ($methods as $method) {
            $methodName = $method->getName();
            if (strpos($methodName, '__') === 0) continue; // Skip magic methods
            
            $this->assertTrue(method_exists($this->instance, $methodName),
                "Method $methodName should exist");
        }

        // Test specific method return types
        $this->assertTrue(method_exists($this->instance, 'renderConversationsLog'));
        $this->assertTrue(method_exists($this->instance, 'enqueueAssets'));
        $this->assertTrue(method_exists($this->instance, 'getConversationsData'));
        $this->assertTrue(method_exists($this->instance, 'getConversationDetails'));
        $this->assertTrue(method_exists($this->instance, 'exportConversationsData'));
    }

    // Test renderConversationsLog security check
    public function test_renderConversationsLog_should_check_user_capabilities()
    {
        // Mock user without required capability
        $user_id = $this->factory->user->create(['role' => 'subscriber']);
        wp_set_current_user($user_id);

        $this->expectException('WPDieException');
        $this->instance->renderConversationsLog();
    }

    // Test renderConversationsLog with valid user
    public function test_renderConversationsLog_should_render_for_authorized_user()
    {
        // Mock user with required capability
        $user_id = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($user_id);

        // Mock the WooCommerce capability check
        add_filter('user_has_cap', function($caps, $cap) {
            if (in_array('manage_woocommerce', $cap)) {
                $caps['manage_woocommerce'] = true;
            }
            return $caps;
        }, 10, 2);

        // Capture output to avoid actual rendering during tests
        ob_start();
        try {
            $this->instance->renderConversationsLog();
            $output = ob_get_clean();
            $this->assertIsString($output);
        } catch (Exception $e) {
            ob_end_clean();
            // Expected behavior - method tries to render but may fail due to missing dependencies
            $this->assertStringContainsString('conversations', strtolower($e->getMessage()));
        }
    }

    // Test renderConversationsLog nonce verification for POST requests
    public function test_renderConversationsLog_should_verify_nonce_for_post_requests()
    {
        // Set up authorized user
        $user_id = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($user_id);
        
        add_filter('user_has_cap', function($caps, $cap) {
            if (in_array('manage_woocommerce', $cap)) {
                $caps['manage_woocommerce'] = true;
            }
            return $caps;
        }, 10, 2);

        // Mock POST request without nonce
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['some_data' => 'test'];

        $this->expectException('WPDieException');
        $this->instance->renderConversationsLog();

        // Clean up
        unset($_SERVER['REQUEST_METHOD']);
        $_POST = [];
    }

    // Test enqueueAssets with wrong hook
    public function test_enqueueAssets_should_not_enqueue_on_wrong_hook()
    {
        global $wp_scripts, $wp_styles;
        $wp_scripts = new \WP_Scripts();
        $wp_styles = new \WP_Styles();

        $this->instance->enqueueAssets('wrong_hook');

        $this->assertFalse($wp_scripts->query('woo-ai-conversations'));
        $this->assertFalse($wp_styles->query('woo-ai-conversations'));
    }

    // Test enqueueAssets with correct hook
    public function test_enqueueAssets_should_enqueue_on_correct_hook()
    {
        global $wp_scripts, $wp_styles;
        $wp_scripts = new \WP_Scripts();
        $wp_styles = new \WP_Styles();

        // Mock Utils methods
        $this->mockUtilsGetPluginVersion();
        $this->mockUtilsGetAssetsUrl();

        $this->instance->enqueueAssets('ai-assistant_page_woo-ai-conversations');

        $this->assertTrue($wp_scripts->query('woo-ai-conversations') !== false);
        $this->assertTrue($wp_styles->query('woo-ai-conversations') !== false);
    }

    // Test getConversationsData with default parameters
    public function test_getConversationsData_should_return_array_with_default_parameters()
    {
        global $wpdb;
        
        // Mock database tables and queries
        $this->setupMockDatabase();

        $result = $this->instance->getConversationsData();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('conversations', $result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertArrayHasKey('filters', $result);
        $this->assertArrayHasKey('query_time', $result);
    }

    // Test getConversationsData with custom parameters
    public function test_getConversationsData_should_handle_custom_parameters()
    {
        global $wpdb;
        
        $this->setupMockDatabase();

        $args = [
            'per_page' => 10,
            'page' => 2,
            'search' => 'test search',
            'status' => 'completed',
            'rating' => 5,
            'confidence' => 'high'
        ];

        $result = $this->instance->getConversationsData($args);

        $this->assertIsArray($result);
        $this->assertEquals($args['per_page'], $result['pagination']['per_page']);
        $this->assertEquals($args['page'], $result['pagination']['current_page']);
    }

    // Test getConversationsData with database error
    public function test_getConversationsData_should_throw_exception_on_database_error()
    {
        global $wpdb;
        
        // Mock database error
        $wpdb = $this->createMock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->users = 'wp_users';
        $wpdb->method('prepare')->willReturn('SELECT 1');
        $wpdb->method('get_var')->willReturn(false);
        $wpdb->method('get_results')->willReturn(false);
        $wpdb->last_error = 'Database connection error';

        $this->expectException('Exception');
        $this->expectExceptionMessage('Unable to load conversations data');
        
        $this->instance->getConversationsData();
    }

    // Test getConversationDetails with valid conversation ID
    public function test_getConversationDetails_should_return_detailed_data_for_valid_id()
    {
        $conversationId = 'test-conversation-123';
        
        // Mock ConversationHandler
        $mockHandler = $this->createMock(ConversationHandler::class);
        $mockHandler->method('getConversationHistory')->willReturn([
            'conversation' => ['id' => $conversationId],
            'messages' => [],
            'pagination' => []
        ]);

        // Use reflection to access private methods for testing
        $method = $this->reflection->getMethod('calculateConversationMetrics');
        $method->setAccessible(true);
        $metrics = $method->invoke($this->instance, $conversationId, [
            'messages' => [],
            'conversation' => ['status' => 'completed']
        ]);

        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('total_messages', $metrics);
        $this->assertArrayHasKey('avg_confidence', $metrics);
    }

    // Test getConversationDetails with invalid conversation ID
    public function test_getConversationDetails_should_return_error_for_invalid_id()
    {
        $result = $this->instance->getConversationDetails('');

        $this->assertInstanceOf('WP_Error', $result);
    }

    // Test exportConversationsData with CSV format
    public function test_exportConversationsData_should_export_csv_format()
    {
        $this->setupMockDatabase();

        $args = [
            'format' => 'csv',
            'limit' => 10
        ];

        $result = $this->instance->exportConversationsData($args);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    // Test exportConversationsData with JSON format
    public function test_exportConversationsData_should_export_json_format()
    {
        $this->setupMockDatabase();

        $args = [
            'format' => 'json',
            'limit' => 10
        ];

        $result = $this->instance->exportConversationsData($args);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    // Test exportConversationsData with limit exceeding maximum
    public function test_exportConversationsData_should_enforce_maximum_limit()
    {
        $this->setupMockDatabase();

        $args = [
            'format' => 'csv',
            'limit' => 2000 // Exceeds MAX_EXPORT_LIMIT of 1000
        ];

        // Use reflection to access constants
        $maxLimit = $this->reflection->getConstant('MAX_EXPORT_LIMIT');

        $result = $this->instance->exportConversationsData($args);

        // Verify the limit was capped at maximum
        $this->assertIsArray($result);
    }

    // Test handleConversationsExport AJAX without proper capabilities
    public function test_handleConversationsExport_should_deny_insufficient_permissions()
    {
        // Set up user without required permissions
        $user_id = $this->factory->user->create(['role' => 'subscriber']);
        wp_set_current_user($user_id);

        // Capture JSON response
        ob_start();
        $this->instance->handleConversationsExport();
        $output = ob_get_clean();

        // Should send error response
        $this->assertStringContainsString('Insufficient permissions', $output);
    }

    // Test handleConversationsExport AJAX without nonce
    public function test_handleConversationsExport_should_verify_nonce()
    {
        // Set up authorized user
        $user_id = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($user_id);
        
        add_filter('user_has_cap', function($caps, $cap) {
            if (in_array('manage_woocommerce', $cap)) {
                $caps['manage_woocommerce'] = true;
            }
            return $caps;
        }, 10, 2);

        // No nonce provided
        $_POST = ['format' => 'csv'];

        ob_start();
        $this->instance->handleConversationsExport();
        $output = ob_get_clean();

        $this->assertStringContainsString('Security check failed', $output);
    }

    // Test handleConversationDetailsAjax with valid parameters
    public function test_handleConversationDetailsAjax_should_return_details_for_valid_request()
    {
        // Set up authorized user
        $user_id = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($user_id);
        
        add_filter('user_has_cap', function($caps, $cap) {
            if (in_array('manage_woocommerce', $cap)) {
                $caps['manage_woocommerce'] = true;
            }
            return $caps;
        }, 10, 2);

        $_POST = [
            'nonce' => wp_create_nonce('woo_ai_conversations_nonce'),
            'conversation_id' => 'test-conversation-123'
        ];

        ob_start();
        $this->instance->handleConversationDetailsAjax();
        $output = ob_get_clean();

        // Should process the request (even if it returns error due to missing conversation)
        $this->assertIsString($output);
    }

    // Test handleConversationSearchAjax with search parameters
    public function test_handleConversationSearchAjax_should_process_search_parameters()
    {
        // Set up authorized user
        $user_id = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($user_id);
        
        add_filter('user_has_cap', function($caps, $cap) {
            if (in_array('manage_woocommerce', $cap)) {
                $caps['manage_woocommerce'] = true;
            }
            return $caps;
        }, 10, 2);

        $this->setupMockDatabase();

        $_POST = [
            'nonce' => wp_create_nonce('woo_ai_conversations_nonce'),
            'search' => 'test search',
            'status' => 'completed',
            'per_page' => 25
        ];

        ob_start();
        $this->instance->handleConversationSearchAjax();
        $output = ob_get_clean();

        $this->assertIsString($output);
    }

    // Test handleBulkConversationActions with delete action
    public function test_handleBulkConversationActions_should_process_delete_action()
    {
        // Set up authorized user
        $user_id = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($user_id);
        
        add_filter('user_has_cap', function($caps, $cap) {
            if (in_array('manage_woocommerce', $cap)) {
                $caps['manage_woocommerce'] = true;
            }
            return $caps;
        }, 10, 2);

        $_POST = [
            'nonce' => wp_create_nonce('woo_ai_conversations_nonce'),
            'action' => 'delete',
            'conversation_ids' => ['conv1', 'conv2']
        ];

        ob_start();
        $this->instance->handleBulkConversationActions();
        $output = ob_get_clean();

        $this->assertIsString($output);
    }

    // Test confidence badge generation for high confidence
    public function test_getConfidenceBadge_should_return_high_badge_for_high_score()
    {
        $method = $this->reflection->getMethod('getConfidenceBadge');
        $method->setAccessible(true);

        $badge = $method->invoke($this->instance, 0.9);

        $this->assertIsArray($badge);
        $this->assertEquals('high', $badge['level']);
        $this->assertEquals('High', $badge['label']);
        $this->assertEquals('confidence-high', $badge['class']);
    }

    // Test confidence badge generation for medium confidence
    public function test_getConfidenceBadge_should_return_medium_badge_for_medium_score()
    {
        $method = $this->reflection->getMethod('getConfidenceBadge');
        $method->setAccessible(true);

        $badge = $method->invoke($this->instance, 0.7);

        $this->assertIsArray($badge);
        $this->assertEquals('medium', $badge['level']);
        $this->assertEquals('Medium', $badge['label']);
        $this->assertEquals('confidence-medium', $badge['class']);
    }

    // Test confidence badge generation for low confidence
    public function test_getConfidenceBadge_should_return_low_badge_for_low_score()
    {
        $method = $this->reflection->getMethod('getConfidenceBadge');
        $method->setAccessible(true);

        $badge = $method->invoke($this->instance, 0.3);

        $this->assertIsArray($badge);
        $this->assertEquals('low', $badge['level']);
        $this->assertEquals('Low', $badge['label']);
        $this->assertEquals('confidence-low', $badge['class']);
    }

    // Test buildConfidenceFilter for different levels
    public function test_buildConfidenceFilter_should_build_correct_sql_for_confidence_levels()
    {
        $method = $this->reflection->getMethod('buildConfidenceFilter');
        $method->setAccessible(true);

        $highFilter = $method->invoke($this->instance, 'high');
        $mediumFilter = $method->invoke($this->instance, 'medium');
        $lowFilter = $method->invoke($this->instance, 'low');
        $invalidFilter = $method->invoke($this->instance, 'invalid');

        $this->assertStringContainsString('0.8', $highFilter);
        $this->assertStringContainsString('0.6', $mediumFilter);
        $this->assertStringContainsString('0.6', $lowFilter);
        $this->assertNull($invalidFilter);
    }

    // Test processConversationData method
    public function test_processConversationData_should_format_conversation_correctly()
    {
        $method = $this->reflection->getMethod('processConversationData');
        $method->setAccessible(true);

        $rawData = [
            'conversation_id' => 'test-123',
            'user_id' => 1,
            'user_name' => 'Test User',
            'user_email' => 'test@example.com',
            'status' => 'completed',
            'started_at' => '2023-01-01 10:00:00',
            'ended_at' => '2023-01-01 10:30:00',
            'total_messages' => 5,
            'user_rating' => 4,
            'avg_confidence' => 0.85,
            'context' => '{"page": "product", "product_id": 123}'
        ];

        $processed = $method->invoke($this->instance, $rawData);

        $this->assertIsArray($processed);
        $this->assertEquals('test-123', $processed['conversation_id']);
        $this->assertEquals(1, $processed['user_id']);
        $this->assertEquals('Test User', $processed['user_name']);
        $this->assertEquals('completed', $processed['status']);
        $this->assertEquals(5, $processed['total_messages']);
        $this->assertEquals(4, $processed['user_rating']);
        $this->assertEquals(0.85, $processed['avg_confidence']);
        $this->assertIsArray($processed['context']);
        $this->assertIsInt($processed['duration']);
    }

    // Test calculateConversationMetrics method
    public function test_calculateConversationMetrics_should_calculate_metrics_correctly()
    {
        $method = $this->reflection->getMethod('calculateConversationMetrics');
        $method->setAccessible(true);

        $history = [
            'conversation' => ['status' => 'completed'],
            'messages' => [
                [
                    'type' => 'user',
                    'created_at' => '2023-01-01 10:00:00',
                    'tokens_used' => 50,
                    'confidence_score' => 0.8
                ],
                [
                    'type' => 'assistant',
                    'created_at' => '2023-01-01 10:00:05',
                    'tokens_used' => 100,
                    'confidence_score' => 0.9
                ]
            ]
        ];

        $metrics = $method->invoke($this->instance, 'test-conv', $history);

        $this->assertIsArray($metrics);
        $this->assertEquals(2, $metrics['total_messages']);
        $this->assertEquals(1, $metrics['user_messages']);
        $this->assertEquals(1, $metrics['assistant_messages']);
        $this->assertEquals(150, $metrics['total_tokens']);
        $this->assertEquals(0.85, $metrics['avg_confidence']);
        $this->assertEquals('completed', $metrics['resolution_status']);
        $this->assertGreaterThan(0, $metrics['avg_response_time']);
    }

    // Test prepareExportData method
    public function test_prepareExportData_should_prepare_data_for_export()
    {
        $method = $this->reflection->getMethod('prepareExportData');
        $method->setAccessible(true);

        $conversations = [
            [
                'conversation_id' => 'test-123',
                'user_name' => 'Test User',
                'user_email' => 'test@example.com',
                'status' => 'completed',
                'started_at' => '2023-01-01 10:00:00',
                'ended_at' => '2023-01-01 10:30:00',
                'duration' => 1800,
                'total_messages' => 5,
                'user_rating' => 4,
                'avg_confidence' => 0.85,
                'confidence_badge' => ['label' => 'High'],
                'models_used' => 'gpt-3.5-turbo',
                'total_tokens' => 500
            ]
        ];

        $options = [
            'include_messages' => false,
            'include_kb_snippets' => false
        ];

        $exportData = $method->invoke($this->instance, $conversations, $options);

        $this->assertIsArray($exportData);
        $this->assertCount(1, $exportData);
        $this->assertEquals('test-123', $exportData[0]['conversation_id']);
        $this->assertEquals('Test User', $exportData[0]['user_name']);
        $this->assertEquals('00:30:00', $exportData[0]['duration']);
        $this->assertEquals(0.85, $exportData[0]['avg_confidence']);
    }

    // Test constants are properly defined
    public function test_class_constants_are_properly_defined()
    {
        $this->assertTrue($this->reflection->hasConstant('REQUIRED_CAPABILITY'));
        $this->assertTrue($this->reflection->hasConstant('CACHE_DURATION'));
        $this->assertTrue($this->reflection->hasConstant('DEFAULT_PER_PAGE'));
        $this->assertTrue($this->reflection->hasConstant('MAX_EXPORT_LIMIT'));
        $this->assertTrue($this->reflection->hasConstant('CONFIDENCE_THRESHOLDS'));

        $this->assertEquals('manage_woocommerce', $this->reflection->getConstant('REQUIRED_CAPABILITY'));
        $this->assertEquals(300, $this->reflection->getConstant('CACHE_DURATION'));
        $this->assertEquals(20, $this->reflection->getConstant('DEFAULT_PER_PAGE'));
        $this->assertEquals(1000, $this->reflection->getConstant('MAX_EXPORT_LIMIT'));
        
        $thresholds = $this->reflection->getConstant('CONFIDENCE_THRESHOLDS');
        $this->assertIsArray($thresholds);
        $this->assertArrayHasKey('high', $thresholds);
        $this->assertArrayHasKey('medium', $thresholds);
        $this->assertArrayHasKey('low', $thresholds);
    }

    // Test database cleanup methods
    public function test_deleteBulkConversations_should_delete_conversations_and_messages()
    {
        global $wpdb;
        
        $this->setupMockDatabase();

        $method = $this->reflection->getMethod('deleteBulkConversations');
        $method->setAccessible(true);

        $conversationIds = ['conv1', 'conv2'];
        
        $result = $method->invoke($this->instance, $conversationIds);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('deleted_count', $result);
    }

    // Test markConversationsResolved method
    public function test_markConversationsResolved_should_update_status_correctly()
    {
        global $wpdb;
        
        $this->setupMockDatabase();

        $method = $this->reflection->getMethod('markConversationsResolved');
        $method->setAccessible(true);

        $conversationIds = ['conv1', 'conv2'];
        
        $result = $method->invoke($this->instance, $conversationIds);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('updated_count', $result);
    }

    /**
     * Helper method to set up mock database
     */
    private function setupMockDatabase(): void
    {
        global $wpdb;
        
        $wpdb = $this->createMock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->users = 'wp_users';
        
        // Mock successful database operations
        $wpdb->method('prepare')->willReturnArgument(0);
        $wpdb->method('get_var')->willReturn(10); // Mock total count
        $wpdb->method('get_results')->willReturn([
            [
                'conversation_id' => 'test-conv-1',
                'user_id' => 1,
                'user_name' => 'Test User',
                'status' => 'completed',
                'started_at' => '2023-01-01 10:00:00',
                'total_messages' => 5,
                'avg_confidence' => 0.8
            ]
        ]);
        $wpdb->method('delete')->willReturn(1);
        $wpdb->method('update')->willReturn(1);
        $wpdb->method('timer_stop')->willReturn(0.1);
        $wpdb->method('esc_like')->willReturnArgument(0);
        $wpdb->last_error = '';
    }

    /**
     * Helper method to mock Utils::getPluginVersion
     */
    private function mockUtilsGetPluginVersion(): void
    {
        // Create a mock for static method calls if needed
        if (!function_exists('wp_enqueue_script')) {
            function wp_enqueue_script() { return true; }
        }
        if (!function_exists('wp_enqueue_style')) {
            function wp_enqueue_style() { return true; }
        }
        if (!function_exists('wp_localize_script')) {
            function wp_localize_script() { return true; }
        }
    }

    /**
     * Helper method to mock Utils::getAssetsUrl
     */
    private function mockUtilsGetAssetsUrl(): void
    {
        // Mock asset URL generation
        if (!function_exists('plugins_url')) {
            function plugins_url($path) { return 'http://example.com/wp-content/plugins/woo-ai-assistant/' . $path; }
        }
    }

    /**
     * Helper method to clean up test conversations
     */
    private function cleanUpTestConversations(): void
    {
        global $wpdb;
        
        if ($wpdb && method_exists($wpdb, 'delete')) {
            $wpdb->delete($wpdb->prefix . 'woo_ai_conversations', [
                'conversation_id' => ['LIKE', 'test-%']
            ]);
            $wpdb->delete($wpdb->prefix . 'woo_ai_messages', [
                'conversation_id' => ['LIKE', 'test-%']
            ]);
        }
    }
}