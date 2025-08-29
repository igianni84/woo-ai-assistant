<?php
/**
 * Base Test Case Class
 * 
 * Foundation class for all test cases in the Woo AI Assistant plugin,
 * providing common utilities and setup methods.
 * 
 * @package WooAiAssistant
 * @subpackage Tests
 * @since 1.0.0
 * @author Claude Code Assistant
 */

declare(strict_types=1);

namespace WooAiAssistant\Tests\Base;

use WP_UnitTestCase;
use WooAiAssistant\Main;
use WooAiAssistant\Tests\Helpers\MockHelpers;
use WC_Helper_Product;
use WC_Helper_Customer;

/**
 * Base Test Case Class
 * 
 * @since 1.0.0
 */
abstract class BaseTestCase extends WP_UnitTestCase
{
    use MockHelpers;
    /**
     * Plugin main instance
     * 
     * @var Main
     */
    protected Main $plugin;
    
    /**
     * Test user IDs
     * 
     * @var array<string, int>
     */
    protected array $testUsers = [];
    
    /**
     * Test product IDs
     * 
     * @var array<int>
     */
    protected array $testProducts = [];
    
    /**
     * Original WordPress options
     * 
     * @var array<string, mixed>
     */
    protected array $originalOptions = [];
    
    /**
     * Set up test environment
     */
    public function setUp(): void
    {
        parent::setUp();
        
        // Initialize plugin
        $this->plugin = Main::getInstance();
        
        // Set up test environment with proper mocking
        $this->setUpTestEnvironment();
        
        // Disable rate limiting for all tests
        $this->disableRateLimiting();
        
        // Mock external HTTP requests
        $this->mockExternalRequests();
        
        // Create test users
        $this->createTestUsers();
        
        // Create test products (if WooCommerce is active)
        if (class_exists('WooCommerce')) {
            $this->createTestProducts();
            $this->mockWooCommerceCart();
        }
        
        // Store original options
        $this->storeOriginalOptions();
        
        // Set test-specific options
        $this->setTestOptions();
        
        // Setup default nonce verification (can be overridden in individual tests)
        $this->mockNonceVerification(true);
    }
    
    /**
     * Tear down test environment
     */
    public function tearDown(): void
    {
        // Reset all mocks first
        $this->resetAllMocks();
        
        // Clean up test data
        $this->cleanUpTestData();
        
        // Restore original options
        $this->restoreOriginalOptions();
        
        // Clear caches
        $this->clearCaches();
        
        parent::tearDown();
    }
    
    /**
     * Set up test environment
     */
    protected function setUpTestEnvironment(): void
    {
        // Set test constants
        if (!defined('WOO_AI_ASSISTANT_TESTING')) {
            define('WOO_AI_ASSISTANT_TESTING', true);
        }
        
        // Enable debugging for tests
        if (!defined('WOO_AI_ASSISTANT_DEBUG')) {
            define('WOO_AI_ASSISTANT_DEBUG', true);
        }
        
        // Set timezone
        date_default_timezone_set('UTC');
        
        // Initialize WordPress environment
        $this->initializeWordPressEnvironment();
    }
    
    /**
     * Initialize WordPress environment for testing
     */
    protected function initializeWordPressEnvironment(): void
    {
        // Ensure admin user exists
        if (!get_user_by('login', 'admin')) {
            $this->factory->user->create([
                'user_login' => 'admin',
                'user_email' => 'admin@example.com',
                'role' => 'administrator'
            ]);
        }
        
        // Initialize WordPress hooks
        do_action('init');
        do_action('wp_loaded');
        
        // Initialize plugin hooks
        if (method_exists($this->plugin, 'initializeHooks')) {
            $this->plugin->initializeHooks();
        }
    }
    
    /**
     * Create test users with different roles
     */
    protected function createTestUsers(): void
    {
        $userRoles = [
            'administrator' => 'admin',
            'shop_manager' => 'shop_manager', 
            'customer' => 'customer',
            'subscriber' => 'subscriber'
        ];
        
        foreach ($userRoles as $role => $login) {
            $this->testUsers[$role] = $this->factory->user->create([
                'user_login' => "test_{$login}",
                'user_email' => "test_{$login}@example.com",
                'role' => $role
            ]);
        }
    }
    
    /**
     * Create test products for WooCommerce testing
     */
    protected function createTestProducts(): void
    {
        if (!class_exists('WC_Helper_Product')) {
            return;
        }
        
        // Simple product
        $this->testProducts[] = WC_Helper_Product::create_simple_product(
            false,
            [
                'name' => 'Test Simple Product',
                'regular_price' => '19.99',
                'description' => 'A test product for unit testing'
            ]
        )->get_id();
        
        // Variable product
        $variableProduct = WC_Helper_Product::create_variable_product();
        $this->testProducts[] = $variableProduct->get_id();
        
        // External product
        $this->testProducts[] = WC_Helper_Product::create_external_product()->get_id();
        
        // Grouped product
        $this->testProducts[] = WC_Helper_Product::create_grouped_product()->get_id();
    }
    
    /**
     * Store original WordPress options
     */
    protected function storeOriginalOptions(): void
    {
        $optionsToStore = [
            'woo_ai_assistant_enabled',
            'woo_ai_assistant_api_key',
            'woo_ai_assistant_welcome_message',
            'woo_ai_assistant_auto_index',
            'woo_ai_assistant_debug_mode'
        ];
        
        foreach ($optionsToStore as $option) {
            $this->originalOptions[$option] = get_option($option);
        }
    }
    
    /**
     * Set test-specific options
     */
    protected function setTestOptions(): void
    {
        update_option('woo_ai_assistant_enabled', true);
        update_option('woo_ai_assistant_debug_mode', true);
        update_option('woo_ai_assistant_welcome_message', 'Test welcome message');
        update_option('woo_ai_assistant_api_key', 'test-api-key-for-testing');
        update_option('woo_ai_assistant_auto_index', true);
    }
    
    /**
     * Clean up test data
     */
    protected function cleanUpTestData(): void
    {
        global $wpdb;
        
        // Clean up test users
        foreach ($this->testUsers as $userId) {
            wp_delete_user($userId, true);
        }
        
        // Clean up test products
        foreach ($this->testProducts as $productId) {
            wp_delete_post($productId, true);
        }
        
        // Clean up plugin tables
        $pluginTables = [
            $wpdb->prefix . 'woo_ai_conversations',
            $wpdb->prefix . 'woo_ai_knowledge_base',
            $wpdb->prefix . 'woo_ai_analytics'
        ];
        
        foreach ($pluginTables as $table) {
            $wpdb->query("TRUNCATE TABLE {$table}");
        }
        
        // Clean up transients
        delete_transient('woo_ai_test_transient');
        
        // Clean up temporary files
        $this->cleanUpTempFiles();
    }
    
    /**
     * Restore original WordPress options
     */
    protected function restoreOriginalOptions(): void
    {
        foreach ($this->originalOptions as $option => $value) {
            if ($value === false) {
                delete_option($option);
            } else {
                update_option($option, $value);
            }
        }
    }
    
    /**
     * Clear all caches
     */
    protected function clearCaches(): void
    {
        // Clear WordPress object cache
        wp_cache_flush();
        
        // Clear WooCommerce cache if available
        if (function_exists('wc_delete_product_transients')) {
            foreach ($this->testProducts as $productId) {
                wc_delete_product_transients($productId);
            }
        }
        
        // Clear plugin-specific caches
        wp_cache_delete('woo_ai_knowledge_base', 'woo_ai_assistant');
        wp_cache_delete('woo_ai_settings', 'woo_ai_assistant');
    }
    
    /**
     * Clean up temporary files
     */
    protected function cleanUpTempFiles(): void
    {
        $tempDirs = [
            WP_CONTENT_DIR . '/uploads/woo-ai-assistant/temp',
            sys_get_temp_dir() . '/woo-ai-assistant-test'
        ];
        
        foreach ($tempDirs as $dir) {
            if (is_dir($dir)) {
                $this->recursiveRemoveDirectory($dir);
            }
        }
    }
    
    /**
     * Recursively remove directory
     * 
     * @param string $dir Directory to remove
     */
    protected function recursiveRemoveDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $filePath = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($filePath)) {
                $this->recursiveRemoveDirectory($filePath);
            } else {
                unlink($filePath);
            }
        }
        rmdir($dir);
    }
    
    /**
     * Assert that a hook is registered
     * 
     * @param string $hook Hook name
     * @param string $function Function name
     * @param int $priority Priority
     */
    protected function assertHookRegistered(string $hook, string $function, int $priority = 10): void
    {
        $this->assertNotFalse(
            has_action($hook, $function),
            "Hook '{$hook}' should be registered with function '{$function}'"
        );
        
        if ($priority !== 10) {
            $this->assertEquals(
                $priority,
                has_action($hook, $function),
                "Hook '{$hook}' should be registered with priority {$priority}"
            );
        }
    }
    
    /**
     * Assert that a database table exists
     * 
     * @param string $tableName Table name
     */
    protected function assertTableExists(string $tableName): void
    {
        global $wpdb;
        
        $tableExists = $wpdb->get_var(
            $wpdb->prepare("SHOW TABLES LIKE %s", $tableName)
        );
        
        $this->assertEquals(
            $tableName,
            $tableExists,
            "Database table '{$tableName}' should exist"
        );
    }
    
    /**
     * Assert that a table has specific columns
     * 
     * @param string $tableName Table name
     * @param array<string> $columns Expected columns
     */
    protected function assertTableHasColumns(string $tableName, array $columns): void
    {
        global $wpdb;
        
        $tableColumns = $wpdb->get_col("DESCRIBE {$tableName}");
        
        foreach ($columns as $column) {
            $this->assertContains(
                $column,
                $tableColumns,
                "Table '{$tableName}' should have column '{$column}'"
            );
        }
    }
    
    /**
     * Create a mock conversation for testing
     * 
     * @param int|null $userId User ID (null for guest)
     * @return int Conversation ID
     */
    protected function createMockConversation(?int $userId = null): int
    {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'woo_ai_conversations',
            [
                'user_id' => $userId,
                'session_id' => 'test_session_' . wp_generate_uuid4(),
                'status' => 'active',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s', '%s']
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Create mock chat messages
     * 
     * @param int $conversationId Conversation ID
     * @param array<string> $messages Messages to create
     */
    protected function createMockMessages(int $conversationId, array $messages): void
    {
        global $wpdb;
        
        foreach ($messages as $index => $message) {
            $sender = $index % 2 === 0 ? 'user' : 'ai';
            
            $wpdb->insert(
                $wpdb->prefix . 'woo_ai_messages',
                [
                    'conversation_id' => $conversationId,
                    'sender' => $sender,
                    'message' => $message,
                    'created_at' => current_time('mysql')
                ],
                ['%d', '%s', '%s', '%s']
            );
        }
    }
    
    /**
     * Assert response is successful REST response
     * 
     * @param mixed $response Response to check
     */
    protected function assertSuccessfulRestResponse($response): void
    {
        $this->assertInstanceOf('WP_REST_Response', $response);
        $this->assertGreaterThanOrEqual(200, $response->get_status());
        $this->assertLessThan(300, $response->get_status());
    }
    
    /**
     * Assert response is error REST response
     * 
     * @param mixed $response Response to check
     * @param int $expectedStatus Expected error status code
     */
    protected function assertErrorRestResponse($response, int $expectedStatus): void
    {
        $this->assertInstanceOf('WP_REST_Response', $response);
        $this->assertEquals($expectedStatus, $response->get_status());
    }
    
    /**
     * Get test user by role
     * 
     * @param string $role User role
     * @return int User ID
     */
    protected function getTestUser(string $role): int
    {
        if (!isset($this->testUsers[$role])) {
            $this->fail("Test user with role '{$role}' not found");
        }
        
        return $this->testUsers[$role];
    }
    
    /**
     * Get test product by index
     * 
     * @param int $index Product index
     * @return int Product ID
     */
    protected function getTestProduct(int $index = 0): int
    {
        if (!isset($this->testProducts[$index])) {
            $this->fail("Test product at index {$index} not found");
        }
        
        return $this->testProducts[$index];
    }
    
    /**
     * Mock external API responses
     * 
     * @param string $url URL to mock
     * @param array<string, mixed> $response Mock response
     */
    protected function mockHttpRequest(string $url, array $response): void
    {
        add_filter('pre_http_request', function($preempt, $args, $requestUrl) use ($url, $response) {
            if (strpos($requestUrl, $url) !== false) {
                return [
                    'headers' => [],
                    'body' => json_encode($response),
                    'response' => [
                        'code' => $response['code'] ?? 200,
                        'message' => $response['message'] ?? 'OK'
                    ],
                    'cookies' => [],
                    'filename' => null
                ];
            }
            return $preempt;
        }, 10, 3);
    }
    
    /**
     * Create test WooCommerce order
     * 
     * @param array $args Order arguments
     * @return int Order ID
     */
    protected function createTestOrder(array $args = []): int
    {
        if (!class_exists('WC_Order')) {
            $this->markTestSkipped('WooCommerce not available');
        }
        
        $defaults = [
            'status' => 'completed',
            'customer_id' => $this->getTestUser('customer'),
            'total' => '99.99'
        ];
        
        $order_args = array_merge($defaults, $args);
        
        $order = new \WC_Order();
        $order->set_customer_id($order_args['customer_id']);
        $order->set_status($order_args['status']);
        $order->set_total($order_args['total']);
        
        return $order->save();
    }
    
    /**
     * Create test conversation in database
     * 
     * @param array $args Conversation arguments
     * @return array Conversation data
     */
    protected function createTestConversation(array $args = []): array
    {
        global $wpdb;
        
        $defaults = [
            'conversation_id' => 'conv_test_' . uniqid(),
            'user_id' => $this->getTestUser('customer'),
            'session_id' => 'test_session_' . wp_generate_uuid4(),
            'status' => 'active',
            'context' => json_encode(['page' => 'test']),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];
        
        $conversation_data = array_merge($defaults, $args);
        
        $wpdb->insert(
            $wpdb->prefix . 'woo_ai_conversations',
            $conversation_data,
            ['%s', '%d', '%s', '%s', '%s', '%s', '%s']
        );
        
        return $conversation_data;
    }
    
    /**
     * Assert that response contains expected structure
     * 
     * @param mixed $response Response to check
     * @param array $expectedKeys Expected keys in response
     */
    protected function assertResponseStructure($response, array $expectedKeys): void
    {
        $this->assertNotNull($response, 'Response should not be null');
        
        if (is_object($response) && method_exists($response, 'get_data')) {
            $data = $response->get_data();
        } elseif (is_array($response)) {
            $data = $response;
        } else {
            $this->fail('Response is not in expected format');
        }
        
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $data, "Response should contain key: {$key}");
        }
    }
    
    /**
     * Assert that no external HTTP requests are made during test
     */
    protected function assertNoExternalRequests(): void
    {
        $request_made = false;
        
        add_filter('pre_http_request', function($preempt, $parsed_args, $url) use (&$request_made) {
            if (!$this->isLocalRequest($url)) {
                $request_made = true;
                $this->fail("Unexpected external HTTP request to: {$url}");
            }
            return $preempt;
        }, 1, 3);
        
        // Run the test and check at the end
        register_shutdown_function(function() use (&$request_made) {
            $this->assertFalse($request_made, 'No external HTTP requests should be made during tests');
        });
    }
    
    /**
     * Skip test if specific service is required but not available
     * 
     * @param string $service Service name
     */
    protected function skipIfServiceNotAvailable(string $service): void
    {
        $available_services = [
            'woocommerce' => class_exists('WooCommerce'),
            'wpml' => class_exists('WPML'),
            'yith_wishlist' => function_exists('YITH_WCWL')
        ];
        
        if (isset($available_services[$service]) && !$available_services[$service]) {
            $this->markTestSkipped("Service '{$service}' not available");
        }
    }
    
    /**
     * Create test knowledge base chunks
     * 
     * @param int $count Number of chunks to create
     * @return array Created chunk data
     */
    protected function createTestKnowledgeBaseChunks(int $count = 3): array
    {
        global $wpdb;
        
        $chunks = [];
        
        for ($i = 0; $i < $count; $i++) {
            $chunk_data = [
                'chunk_id' => 'test_chunk_' . uniqid(),
                'source_type' => 'product',
                'source_id' => 123 + $i,
                'content' => "Test knowledge base content {$i}",
                'content_hash' => md5("test content {$i}"),
                'chunk_size' => strlen("test content {$i}"),
                'metadata' => json_encode(['title' => "Test Product {$i}"]),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ];
            
            $wpdb->insert(
                $wpdb->prefix . 'woo_ai_knowledge_base',
                $chunk_data,
                ['%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s']
            );
            
            $chunks[] = $chunk_data;
        }
        
        return $chunks;
    }
    
    /**
     * Assert plugin tables exist and have correct structure
     */
    protected function assertPluginTablesExist(): void
    {
        global $wpdb;
        
        $expected_tables = [
            $wpdb->prefix . 'woo_ai_conversations',
            $wpdb->prefix . 'woo_ai_knowledge_base'
        ];
        
        foreach ($expected_tables as $table) {
            $this->assertTableExists($table);
        }
    }
    
    /**
     * Get test API request with proper authentication
     * 
     * @param array $params Request parameters
     * @return \WP_REST_Request
     */
    protected function getAuthenticatedApiRequest(array $params = []): \WP_REST_Request
    {
        $request = $this->createMockRestRequest('POST', $params);
        
        // Add authentication headers/nonce
        $request->set_param('nonce', wp_create_nonce('wp_rest'));
        
        return $request;
    }
}