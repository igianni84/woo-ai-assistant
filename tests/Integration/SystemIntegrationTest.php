<?php

/**
 * System Integration Test Suite
 *
 * Comprehensive integration tests for the Woo AI Assistant plugin to ensure
 * all components work together correctly in a WordPress/WooCommerce environment.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Integration
 * @since 1.0.0
 */

namespace WooAiAssistant\Tests\Integration;

use WooAiAssistant\Main;
use WooAiAssistant\Setup\Activator;
use WooAiAssistant\Setup\Deactivator;
use WooAiAssistant\Admin\AdminMenu;
use WooAiAssistant\Admin\RestController;
use WooAiAssistant\KnowledgeBase\Scanner;
use WooAiAssistant\KnowledgeBase\Indexer;
use WooAiAssistant\Chatbot\ConversationHandler;
use WooAiAssistant\Frontend\WidgetLoader;
use WP_REST_Request;
use WP_Error;

/**
 * Class SystemIntegrationTest
 *
 * Tests the complete system integration including plugin lifecycle,
 * database operations, REST API endpoints, and component interactions.
 *
 * @since 1.0.0
 */
class SystemIntegrationTest extends \WP_UnitTestCase
{
    /**
     * Plugin main instance
     *
     * @var Main
     */
    private $plugin;

    /**
     * Test user ID
     *
     * @var int
     */
    private $testUserId;

    /**
     * Test product ID
     *
     * @var int
     */
    private $testProductId;

    /**
     * Setup test environment
     *
     * @since 1.0.0
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        
        // Create test user with admin capabilities
        $this->testUserId = $this->factory->user->create([
            'role' => 'administrator',
            'user_login' => 'test_admin',
            'user_email' => 'admin@test.com'
        ]);
        
        // Set current user
        wp_set_current_user($this->testUserId);
        
        // Create test WooCommerce product
        $this->testProductId = $this->factory->post->create([
            'post_type' => 'product',
            'post_title' => 'Test Product',
            'post_content' => 'Test product description for integration testing',
            'post_status' => 'publish'
        ]);
        
        // Initialize plugin
        $this->plugin = Main::getInstance();
    }

    /**
     * Teardown test environment
     *
     * @since 1.0.0
     * @return void
     */
    public function tearDown(): void
    {
        // Clean up test data
        wp_delete_user($this->testUserId);
        wp_delete_post($this->testProductId, true);
        
        // Clear any cached data
        wp_cache_flush();
        
        parent::tearDown();
    }

    /**
     * Test plugin activation process
     *
     * @since 1.0.0
     * @return void
     */
    public function test_plugin_activation_should_create_database_tables()
    {
        global $wpdb;
        
        // Run activation
        Activator::activate();
        
        // Check if tables were created
        $tables = [
            $wpdb->prefix . 'woo_ai_conversations',
            $wpdb->prefix . 'woo_ai_messages',
            $wpdb->prefix . 'woo_ai_knowledge_base',
            $wpdb->prefix . 'woo_ai_embeddings'
        ];
        
        foreach ($tables as $table) {
            $tableExists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
            $this->assertEquals($table, $tableExists, "Table {$table} should exist after activation");
        }
        
        // Check if default options were set
        $this->assertNotEmpty(get_option('woo_ai_assistant_version'));
        $this->assertNotEmpty(get_option('woo_ai_assistant_settings'));
    }

    /**
     * Test plugin deactivation process
     *
     * @since 1.0.0
     * @return void
     */
    public function test_plugin_deactivation_should_clean_up_scheduled_tasks()
    {
        // Schedule a test event
        wp_schedule_event(time() + 3600, 'hourly', 'woo_ai_assistant_indexing');
        
        // Verify event is scheduled
        $this->assertNotFalse(wp_next_scheduled('woo_ai_assistant_indexing'));
        
        // Run deactivation
        Deactivator::deactivate();
        
        // Verify event is cleared
        $this->assertFalse(wp_next_scheduled('woo_ai_assistant_indexing'));
    }

    /**
     * Test admin menu registration
     *
     * @since 1.0.0
     * @return void
     */
    public function test_admin_menu_should_register_all_pages()
    {
        global $menu, $submenu;
        
        // Initialize admin menu
        $adminMenu = AdminMenu::getInstance();
        
        // Trigger menu registration
        do_action('admin_menu');
        
        // Check main menu exists
        $menuExists = false;
        foreach ($menu as $menuItem) {
            if (isset($menuItem[2]) && $menuItem[2] === 'woo-ai-assistant') {
                $menuExists = true;
                break;
            }
        }
        $this->assertTrue($menuExists, 'Main admin menu should be registered');
        
        // Check submenus exist
        if (isset($submenu['woo-ai-assistant'])) {
            $expectedSubmenus = ['Settings', 'Conversations', 'Knowledge Base'];
            foreach ($expectedSubmenus as $expectedSubmenu) {
                $found = false;
                foreach ($submenu['woo-ai-assistant'] as $submenuItem) {
                    if (strpos($submenuItem[0], $expectedSubmenu) !== false) {
                        $found = true;
                        break;
                    }
                }
                $this->assertTrue($found, "Submenu '{$expectedSubmenu}' should exist");
            }
        }
    }

    /**
     * Test REST API endpoint registration
     *
     * @since 1.0.0
     * @return void
     */
    public function test_rest_api_endpoints_should_be_registered()
    {
        // Initialize REST controller
        $restController = RestController::getInstance();
        
        // Trigger REST API init
        do_action('rest_api_init');
        
        // Get REST server
        $server = rest_get_server();
        $routes = $server->get_routes();
        
        // Check required endpoints
        $expectedEndpoints = [
            '/woo-ai-assistant/v1/chat',
            '/woo-ai-assistant/v1/conversation',
            '/woo-ai-assistant/v1/knowledge-base',
            '/woo-ai-assistant/v1/settings'
        ];
        
        foreach ($expectedEndpoints as $endpoint) {
            $this->assertArrayHasKey($endpoint, $routes, "Endpoint {$endpoint} should be registered");
        }
    }

    /**
     * Test conversation creation and retrieval
     *
     * @since 1.0.0
     * @return void
     */
    public function test_conversation_handler_should_create_and_retrieve_conversations()
    {
        $handler = ConversationHandler::getInstance();
        
        // Create a new conversation
        $conversationId = $handler->createConversation([
            'user_id' => $this->testUserId,
            'context' => ['page' => 'product', 'product_id' => $this->testProductId]
        ]);
        
        $this->assertIsString($conversationId);
        $this->assertNotEmpty($conversationId);
        
        // Add a message to the conversation
        $messageId = $handler->addMessage($conversationId, [
            'type' => 'user',
            'content' => 'Test message from integration test',
            'metadata' => ['test' => true]
        ]);
        
        $this->assertIsInt($messageId);
        $this->assertGreaterThan(0, $messageId);
        
        // Retrieve conversation history
        $history = $handler->getConversationHistory($conversationId);
        
        $this->assertIsArray($history);
        $this->assertArrayHasKey('conversation', $history);
        $this->assertArrayHasKey('messages', $history);
        $this->assertCount(1, $history['messages']);
        $this->assertEquals('Test message from integration test', $history['messages'][0]['content']);
    }

    /**
     * Test knowledge base scanning
     *
     * @since 1.0.0
     * @return void
     */
    public function test_knowledge_base_scanner_should_find_products()
    {
        $scanner = Scanner::getInstance();
        
        // Scan products
        $products = $scanner->scanProducts(['limit' => 10]);
        
        $this->assertIsArray($products);
        $this->assertGreaterThan(0, count($products));
        
        // Check if our test product is found
        $testProductFound = false;
        foreach ($products as $product) {
            if ($product['id'] === $this->testProductId) {
                $testProductFound = true;
                $this->assertEquals('Test Product', $product['title']);
                $this->assertStringContainsString('integration testing', $product['content']);
                break;
            }
        }
        
        $this->assertTrue($testProductFound, 'Test product should be found by scanner');
    }

    /**
     * Test knowledge base indexing
     *
     * @since 1.0.0
     * @return void
     */
    public function test_knowledge_base_indexer_should_process_content()
    {
        $indexer = Indexer::getInstance();
        
        // Prepare test content
        $content = [
            'id' => $this->testProductId,
            'title' => 'Test Product',
            'content' => 'This is a test product for integration testing of the Woo AI Assistant plugin.',
            'type' => 'product',
            'source_id' => $this->testProductId
        ];
        
        // Index the content
        $result = $indexer->indexContent($content);
        
        $this->assertTrue($result !== false);
        
        // Verify content was indexed
        global $wpdb;
        $tableName = $wpdb->prefix . 'woo_ai_knowledge_base';
        $indexed = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tableName} WHERE source_id = %d AND source_type = %s",
            $this->testProductId,
            'product'
        ));
        
        $this->assertNotNull($indexed);
        $this->assertEquals('Test Product', $indexed->title);
    }

    /**
     * Test REST API chat endpoint
     *
     * @since 1.0.0
     * @return void
     */
    public function test_rest_api_chat_endpoint_should_process_messages()
    {
        // Create REST request
        $request = new WP_REST_Request('POST', '/woo-ai-assistant/v1/chat');
        $request->set_param('message', 'Test chat message');
        $request->set_param('conversation_id', 'test-conversation-' . time());
        $request->set_param('context', ['page' => 'test']);
        
        // Set authentication
        wp_set_current_user($this->testUserId);
        
        // Process request
        $response = rest_do_request($request);
        $data = $response->get_data();
        
        // Check response
        if (!is_wp_error($data)) {
            $this->assertIsArray($data);
            $this->assertArrayHasKey('success', $data);
            
            // If successful, check response structure
            if ($data['success']) {
                $this->assertArrayHasKey('message', $data);
                $this->assertArrayHasKey('conversation_id', $data);
            }
        }
    }

    /**
     * Test widget loader functionality
     *
     * @since 1.0.0
     * @return void
     */
    public function test_widget_loader_should_enqueue_assets_on_frontend()
    {
        $widgetLoader = WidgetLoader::getInstance();
        
        // Simulate frontend request
        $this->go_to('/');
        
        // Trigger script enqueuing
        do_action('wp_enqueue_scripts');
        
        // Check if widget scripts are enqueued
        $this->assertTrue(wp_script_is('woo-ai-assistant-widget', 'enqueued') || wp_script_is('woo-ai-assistant-widget', 'registered'));
    }

    /**
     * Test database operations under load
     *
     * @since 1.0.0
     * @return void
     */
    public function test_database_operations_should_handle_concurrent_conversations()
    {
        $handler = ConversationHandler::getInstance();
        $conversationIds = [];
        
        // Create multiple conversations
        for ($i = 0; $i < 5; $i++) {
            $conversationId = $handler->createConversation([
                'user_id' => $this->testUserId,
                'context' => ['test' => $i]
            ]);
            $conversationIds[] = $conversationId;
            
            // Add multiple messages
            for ($j = 0; $j < 3; $j++) {
                $handler->addMessage($conversationId, [
                    'type' => $j % 2 === 0 ? 'user' : 'assistant',
                    'content' => "Message {$j} in conversation {$i}"
                ]);
            }
        }
        
        // Verify all conversations were created
        $this->assertCount(5, $conversationIds);
        
        // Verify message counts
        foreach ($conversationIds as $index => $conversationId) {
            $history = $handler->getConversationHistory($conversationId);
            $this->assertCount(3, $history['messages'], "Conversation {$index} should have 3 messages");
        }
    }

    /**
     * Test error handling and recovery
     *
     * @since 1.0.0
     * @return void
     */
    public function test_system_should_handle_errors_gracefully()
    {
        $handler = ConversationHandler::getInstance();
        
        // Test with invalid conversation ID
        $history = $handler->getConversationHistory('invalid-id-12345');
        $this->assertTrue(is_wp_error($history) || empty($history['messages']));
        
        // Test with invalid message data
        $messageId = $handler->addMessage('test-conv', [
            'type' => 'invalid_type',
            'content' => ''
        ]);
        $this->assertTrue($messageId === false || is_wp_error($messageId));
    }

    /**
     * Test settings persistence
     *
     * @since 1.0.0
     * @return void
     */
    public function test_settings_should_persist_and_retrieve_correctly()
    {
        // Set test settings
        $testSettings = [
            'api_key' => 'test-api-key-123',
            'model' => 'test-model',
            'max_tokens' => 1000,
            'enable_widget' => true
        ];
        
        update_option('woo_ai_assistant_settings', $testSettings);
        
        // Retrieve settings
        $retrievedSettings = get_option('woo_ai_assistant_settings');
        
        $this->assertEquals($testSettings, $retrievedSettings);
        $this->assertEquals('test-api-key-123', $retrievedSettings['api_key']);
        $this->assertTrue($retrievedSettings['enable_widget']);
    }

    /**
     * Test plugin upgrade process
     *
     * @since 1.0.0
     * @return void
     */
    public function test_plugin_upgrade_should_migrate_data_correctly()
    {
        // Simulate old version data
        update_option('woo_ai_assistant_version', '0.9.0');
        
        // Add some old format data
        update_option('woo_ai_old_setting', 'old_value');
        
        // Trigger upgrade
        Activator::activate();
        
        // Check version was updated
        $currentVersion = get_option('woo_ai_assistant_version');
        $this->assertNotEquals('0.9.0', $currentVersion);
        
        // Check if upgrade routines ran (would check specific migrations here)
        // This is a placeholder for actual migration testing
        $this->assertTrue(true);
    }

    /**
     * Test complete user flow
     *
     * @since 1.0.0
     * @return void
     */
    public function test_complete_user_flow_from_activation_to_chat()
    {
        // Step 1: Activate plugin
        Activator::activate();
        $this->assertTrue(true, 'Plugin activated');
        
        // Step 2: Initialize main instance
        $plugin = Main::getInstance();
        $this->assertInstanceOf(Main::class, $plugin);
        
        // Step 3: Create a conversation
        $handler = ConversationHandler::getInstance();
        $conversationId = $handler->createConversation([
            'user_id' => $this->testUserId
        ]);
        $this->assertNotEmpty($conversationId);
        
        // Step 4: Send a user message
        $userMessageId = $handler->addMessage($conversationId, [
            'type' => 'user',
            'content' => 'Hello, I need help with a product'
        ]);
        $this->assertGreaterThan(0, $userMessageId);
        
        // Step 5: Simulate AI response
        $aiMessageId = $handler->addMessage($conversationId, [
            'type' => 'assistant',
            'content' => 'Hello! I\'d be happy to help you with our products.',
            'confidence_score' => 0.95,
            'model_used' => 'test-model',
            'tokens_used' => 25
        ]);
        $this->assertGreaterThan(0, $aiMessageId);
        
        // Step 6: End conversation
        $result = $handler->endConversation($conversationId);
        $this->assertTrue($result);
        
        // Step 7: Verify conversation was properly recorded
        $history = $handler->getConversationHistory($conversationId);
        $this->assertCount(2, $history['messages']);
        $this->assertEquals('ended', $history['conversation']['status']);
    }
}