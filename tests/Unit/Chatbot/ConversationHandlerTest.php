<?php
/**
 * Conversation Handler Test Class
 *
 * Comprehensive unit tests for the ConversationHandler class covering
 * all functionality including conversation persistence, context management,
 * session handling, message threading, and cleanup operations.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Unit\Chatbot
 * @since 1.0.0
 * @author Claude Code Assistant
 */

namespace WooAiAssistant\Tests\Unit\Chatbot;

use WooAiAssistant\Chatbot\ConversationHandler;
use WooAiAssistant\Tests\WP_UnitTestCase;
use WP_Error;
use ReflectionClass;
use ReflectionMethod;

/**
 * Class ConversationHandlerTest
 *
 * @since 1.0.0
 */
class ConversationHandlerTest extends WP_UnitTestCase
{
    /**
     * ConversationHandler instance for testing
     *
     * @var ConversationHandler
     */
    private $handler;

    /**
     * Test database table names
     *
     * @var array
     */
    private $tableNames;

    /**
     * Test user ID
     *
     * @var int
     */
    private $testUserId;

    /**
     * Test session ID
     *
     * @var string
     */
    private $testSessionId;

    /**
     * Setup test environment before each test
     *
     * @since 1.0.0
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        global $wpdb;
        
        // Clear any previous mock data to ensure clean state
        if (method_exists($wpdb, 'clearMockData')) {
            $wpdb->clearMockData();
        }

        // Create database tables before testing
        $this->createTestTables();

        $this->handler = ConversationHandler::getInstance();
        $this->tableNames = [
            'conversations' => $wpdb->prefix . 'woo_ai_conversations',
            'messages' => $wpdb->prefix . 'woo_ai_messages',
            'usage_stats' => $wpdb->prefix . 'woo_ai_usage_stats'
        ];

        // Create test user
        $this->testUserId = $this->factory->user->create([
            'role' => 'customer',
            'user_login' => 'test_user_' . time()
        ]);

        $this->testSessionId = 'test_session_' . wp_generate_uuid4();

        // Clean up any existing test data
        $this->cleanupTestData();
    }

    /**
     * Clean up after each test
     *
     * @since 1.0.0
     * @return void
     */
    public function tearDown(): void
    {
        $this->cleanupTestData();
        parent::tearDown();
    }

    // ========================================================================
    // Class Existence and Structure Tests
    // ========================================================================

    /**
     * Test class exists and can be instantiated
     *
     * @since 1.0.0
     */
    public function test_class_exists_and_instantiates()
    {
        $this->assertTrue(class_exists('WooAiAssistant\Chatbot\ConversationHandler'));
        $this->assertInstanceOf(ConversationHandler::class, $this->handler);
        $this->assertInstanceOf(ConversationHandler::class, ConversationHandler::getInstance());
    }

    /**
     * Test singleton pattern implementation
     *
     * @since 1.0.0
     */
    public function test_singleton_pattern_implementation()
    {
        $instance1 = ConversationHandler::getInstance();
        $instance2 = ConversationHandler::getInstance();
        
        $this->assertSame($instance1, $instance2, 'Singleton pattern should return same instance');
    }

    /**
     * Test class follows naming conventions
     *
     * @since 1.0.0
     */
    public function test_class_follows_naming_conventions()
    {
        $reflection = new ReflectionClass($this->handler);
        
        // Class name must be PascalCase
        $className = $reflection->getShortName();
        $this->assertMatchesRegularExpression('/^[A-Z][a-zA-Z0-9]*$/', $className, 
            "Class name '$className' must be PascalCase");
            
        // All public methods must be camelCase
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            $methodName = $method->getName();
            if (strpos($methodName, '__') === 0) continue; // Skip magic methods
            
            $this->assertMatchesRegularExpression('/^[a-z][a-zA-Z0-9]*$/', $methodName,
                "Method '$methodName' must be camelCase");
        }
    }

    /**
     * Test required public methods exist
     *
     * @since 1.0.0
     */
    public function test_required_public_methods_exist()
    {
        $requiredMethods = [
            'startConversation',
            'addMessage',
            'getConversationHistory',
            'updateContext',
            'endConversation',
            'cleanupOldConversations',
            'getSessionData',
            'updateSessionData'
        ];

        foreach ($requiredMethods as $methodName) {
            $this->assertTrue(method_exists($this->handler, $methodName),
                "Required method $methodName should exist");
        }
    }

    // ========================================================================
    // Conversation Management Tests
    // ========================================================================

    /**
     * Test starting a new conversation with valid data
     *
     * @since 1.0.0
     */
    public function test_startConversation_should_create_new_conversation_when_valid_data_provided()
    {
        // Debug: Check what conversations exist before starting
        global $wpdb;
        $existingConversations = $wpdb->get_results("SELECT * FROM {$this->tableNames['conversations']}");
        error_log("Existing conversations before test: " . print_r($existingConversations, true));
        
        $context = [
            'page' => 'product',
            'product_id' => 123,
            'user_agent' => 'Test Browser'
        ];

        // Debug: Check again right before startConversation
        $existingConversations2 = $wpdb->get_results("SELECT * FROM {$this->tableNames['conversations']}");
        error_log("Existing conversations right before startConversation: " . print_r($existingConversations2, true));
        
        $conversationId = $this->handler->startConversation($this->testUserId, $this->testSessionId, $context);
        
        // Debug: Check conversations after startConversation
        $existingConversations3 = $wpdb->get_results("SELECT * FROM {$this->tableNames['conversations']}");
        error_log("Existing conversations after startConversation: " . print_r($existingConversations3, true));

        $this->assertNotInstanceOf(WP_Error::class, $conversationId);
        $this->assertIsString($conversationId);
        $this->assertNotEmpty($conversationId);

        // Verify conversation exists in database
        global $wpdb;
        $conversation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tableNames['conversations']} WHERE conversation_id = %s",
            $conversationId
        ));

        $this->assertNotNull($conversation);
        $this->assertEquals($this->testUserId, $conversation->user_id);
        $this->assertEquals($this->testSessionId, $conversation->session_id);
        $this->assertEquals('active', $conversation->status);
        $this->assertEquals(0, $conversation->total_messages);
    }

    /**
     * Test starting conversation with guest user (null user ID)
     *
     * @since 1.0.0
     */
    public function test_startConversation_should_handle_guest_users()
    {
        $conversationId = $this->handler->startConversation(null, $this->testSessionId);

        $this->assertNotInstanceOf(WP_Error::class, $conversationId);
        
        // Verify conversation exists with null user_id
        global $wpdb;
        $conversation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tableNames['conversations']} WHERE conversation_id = %s",
            $conversationId
        ));

        $this->assertNotNull($conversation);
        $this->assertNull($conversation->user_id);
        $this->assertEquals($this->testSessionId, $conversation->session_id);
    }

    /**
     * Test starting conversation returns existing active conversation
     *
     * @since 1.0.0
     */
    public function test_startConversation_should_return_existing_active_conversation()
    {
        // Start first conversation
        $conversationId1 = $this->handler->startConversation($this->testUserId, $this->testSessionId);
        $this->assertNotInstanceOf(WP_Error::class, $conversationId1);

        // Start second conversation with same user/session
        $conversationId2 = $this->handler->startConversation($this->testUserId, $this->testSessionId);
        
        $this->assertEquals($conversationId1, $conversationId2, 
            'Should return existing active conversation');
    }

    /**
     * Test starting conversation with invalid session ID
     *
     * @since 1.0.0
     */
    public function test_startConversation_should_return_error_when_invalid_session_id()
    {
        // Test empty session ID
        $result = $this->handler->startConversation($this->testUserId, '');
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('invalid_session', $result->get_error_code());

        // Test session ID too long
        $longSessionId = str_repeat('a', 256);
        $result = $this->handler->startConversation($this->testUserId, $longSessionId);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('invalid_session', $result->get_error_code());
    }

    // ========================================================================
    // Message Management Tests
    // ========================================================================

    /**
     * Test adding valid message to conversation
     *
     * @since 1.0.0
     */
    public function test_addMessage_should_add_message_when_valid_data_provided()
    {
        // Create conversation first
        $conversationId = $this->handler->startConversation($this->testUserId, $this->testSessionId);
        $this->assertNotInstanceOf(WP_Error::class, $conversationId);

        $messageContent = 'Hello, I need help with my order';
        $metadata = [
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Test Browser'
        ];

        $messageId = $this->handler->addMessage($conversationId, 'user', $messageContent, $metadata);
        
        $this->assertNotInstanceOf(WP_Error::class, $messageId);
        $this->assertIsInt($messageId);
        $this->assertGreaterThan(0, $messageId);

        // Verify message exists in database
        global $wpdb;
        $message = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tableNames['messages']} WHERE id = %d",
            $messageId
        ));

        $this->assertNotNull($message);
        $this->assertEquals($conversationId, $message->conversation_id);
        $this->assertEquals('user', $message->message_type);
        $this->assertEquals($messageContent, $message->message_content);
    }

    /**
     * Test adding message with all message types
     *
     * @since 1.0.0
     */
    public function test_addMessage_should_handle_all_message_types()
    {
        $conversationId = $this->handler->startConversation($this->testUserId, $this->testSessionId);
        $this->assertNotInstanceOf(WP_Error::class, $conversationId);

        $messageTypes = ['user', 'assistant', 'system'];

        foreach ($messageTypes as $type) {
            $messageId = $this->handler->addMessage($conversationId, $type, "Test $type message");
            $this->assertNotInstanceOf(WP_Error::class, $messageId, 
                "Should handle message type: $type");
        }
    }

    /**
     * Test adding message with optional metadata
     *
     * @since 1.0.0
     */
    public function test_addMessage_should_store_optional_metadata()
    {
        $conversationId = $this->handler->startConversation($this->testUserId, $this->testSessionId);
        $this->assertNotInstanceOf(WP_Error::class, $conversationId);

        $metadata = [
            'tokens_used' => 150,
            'model_used' => 'gpt-4',
            'confidence_score' => 0.95,
            'custom_data' => ['key' => 'value']
        ];

        $messageId = $this->handler->addMessage($conversationId, 'assistant', 'AI response', $metadata);
        $this->assertNotInstanceOf(WP_Error::class, $messageId);

        // Verify metadata stored correctly
        global $wpdb;
        $message = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tableNames['messages']} WHERE id = %d",
            $messageId
        ));

        $this->assertNotNull($message);
        $this->assertEquals(150, $message->tokens_used);
        $this->assertEquals('gpt-4', $message->model_used);
        $this->assertEquals(0.95, $message->confidence_score);
    }

    /**
     * Test adding message with invalid message type
     *
     * @since 1.0.0
     */
    public function test_addMessage_should_return_error_when_invalid_message_type()
    {
        $conversationId = $this->handler->startConversation($this->testUserId, $this->testSessionId);
        $this->assertNotInstanceOf(WP_Error::class, $conversationId);

        $result = $this->handler->addMessage($conversationId, 'invalid_type', 'Test message');
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('invalid_message_type', $result->get_error_code());
    }

    /**
     * Test adding message with invalid content
     *
     * @since 1.0.0
     */
    public function test_addMessage_should_return_error_when_invalid_content()
    {
        $conversationId = $this->handler->startConversation($this->testUserId, $this->testSessionId);
        $this->assertNotInstanceOf(WP_Error::class, $conversationId);

        // Test empty content
        $result = $this->handler->addMessage($conversationId, 'user', '');
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('invalid_content', $result->get_error_code());

        // Test content too long
        $longContent = str_repeat('a', 10001);
        $result = $this->handler->addMessage($conversationId, 'user', $longContent);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('invalid_content', $result->get_error_code());
    }

    /**
     * Test adding message to inactive conversation
     *
     * @since 1.0.0
     */
    public function test_addMessage_should_return_error_when_conversation_inactive()
    {
        $conversationId = $this->handler->startConversation($this->testUserId, $this->testSessionId);
        $this->assertNotInstanceOf(WP_Error::class, $conversationId);

        // End the conversation
        $endResult = $this->handler->endConversation($conversationId);
        $this->assertTrue($endResult);

        // Try to add message to ended conversation
        $result = $this->handler->addMessage($conversationId, 'user', 'Test message');
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('conversation_inactive', $result->get_error_code());
    }

    // ========================================================================
    // Conversation History Tests
    // ========================================================================

    /**
     * Test getting conversation history with messages
     *
     * @since 1.0.0
     */
    public function test_getConversationHistory_should_return_messages_in_chronological_order()
    {
        $conversationId = $this->handler->startConversation($this->testUserId, $this->testSessionId);
        $this->assertNotInstanceOf(WP_Error::class, $conversationId);

        // Add multiple messages
        $messages = [
            ['user', 'Hello'],
            ['assistant', 'Hi there!'],
            ['user', 'I need help'],
            ['assistant', 'How can I assist you?']
        ];

        $messageIds = [];
        foreach ($messages as $message) {
            $messageId = $this->handler->addMessage($conversationId, $message[0], $message[1]);
            $this->assertNotInstanceOf(WP_Error::class, $messageId);
            $messageIds[] = $messageId;
        }

        $history = $this->handler->getConversationHistory($conversationId);
        $this->assertNotInstanceOf(WP_Error::class, $history);
        $this->assertIsArray($history);

        // Check structure
        $this->assertArrayHasKey('conversation', $history);
        $this->assertArrayHasKey('messages', $history);
        $this->assertArrayHasKey('pagination', $history);

        // Check message count
        $this->assertEquals(4, count($history['messages']));

        // Check chronological order
        for ($i = 0; $i < count($messages); $i++) {
            $this->assertEquals($messages[$i][0], $history['messages'][$i]['type']);
            $this->assertEquals($messages[$i][1], $history['messages'][$i]['content']);
        }
    }

    /**
     * Test getting conversation history with pagination
     *
     * @since 1.0.0
     */
    public function test_getConversationHistory_should_handle_pagination()
    {
        $conversationId = $this->handler->startConversation($this->testUserId, $this->testSessionId);
        $this->assertNotInstanceOf(WP_Error::class, $conversationId);

        // Add multiple messages
        for ($i = 1; $i <= 15; $i++) {
            $messageId = $this->handler->addMessage($conversationId, 'user', "Message $i");
            $this->assertNotInstanceOf(WP_Error::class, $messageId);
        }

        // Test with limit
        $history = $this->handler->getConversationHistory($conversationId, ['limit' => 5]);
        $this->assertNotInstanceOf(WP_Error::class, $history);
        $this->assertEquals(5, count($history['messages']));

        // Test with offset
        $history = $this->handler->getConversationHistory($conversationId, [
            'limit' => 5,
            'offset' => 10
        ]);
        $this->assertNotInstanceOf(WP_Error::class, $history);
        $this->assertEquals(5, count($history['messages']));

        // Check pagination info
        $this->assertEquals(15, $history['pagination']['total_messages']);
        $this->assertEquals(5, $history['pagination']['limit']);
        $this->assertEquals(10, $history['pagination']['offset']);
    }

    /**
     * Test getting conversation history with metadata
     *
     * @since 1.0.0
     */
    public function test_getConversationHistory_should_include_metadata_when_requested()
    {
        $conversationId = $this->handler->startConversation($this->testUserId, $this->testSessionId);
        $this->assertNotInstanceOf(WP_Error::class, $conversationId);

        $metadata = ['custom_field' => 'test_value'];
        $messageId = $this->handler->addMessage($conversationId, 'user', 'Test', $metadata);
        $this->assertNotInstanceOf(WP_Error::class, $messageId);

        // Get history with metadata
        $history = $this->handler->getConversationHistory($conversationId, [
            'include_metadata' => true
        ]);

        $this->assertNotInstanceOf(WP_Error::class, $history);
        $this->assertArrayHasKey('metadata', $history['messages'][0]);
        $this->assertEquals('test_value', $history['messages'][0]['metadata']['custom_field']);

        // Get history without metadata
        $history = $this->handler->getConversationHistory($conversationId, [
            'include_metadata' => false
        ]);

        $this->assertArrayNotHasKey('metadata', $history['messages'][0]);
    }

    // ========================================================================
    // Context Management Tests
    // ========================================================================

    /**
     * Test updating conversation context
     *
     * @since 1.0.0
     */
    public function test_updateContext_should_merge_new_context_with_existing()
    {
        $initialContext = ['page' => 'product', 'product_id' => 123];
        $conversationId = $this->handler->startConversation($this->testUserId, $this->testSessionId, $initialContext);
        $this->assertNotInstanceOf(WP_Error::class, $conversationId);

        $newContext = ['user_action' => 'add_to_cart', 'quantity' => 2];
        $result = $this->handler->updateContext($conversationId, $newContext);
        $this->assertTrue($result);

        // Verify context merged correctly
        $history = $this->handler->getConversationHistory($conversationId);
        $this->assertNotInstanceOf(WP_Error::class, $history);

        $context = $history['conversation']['context'];
        $this->assertEquals('product', $context['page']);
        $this->assertEquals(123, $context['product_id']);
        $this->assertEquals('add_to_cart', $context['user_action']);
        $this->assertEquals(2, $context['quantity']);
    }

    /**
     * Test replacing conversation context
     *
     * @since 1.0.0
     */
    public function test_updateContext_should_replace_context_when_requested()
    {
        $initialContext = ['page' => 'product', 'product_id' => 123];
        $conversationId = $this->handler->startConversation($this->testUserId, $this->testSessionId, $initialContext);
        $this->assertNotInstanceOf(WP_Error::class, $conversationId);

        $newContext = ['page' => 'checkout', 'step' => 1];
        $result = $this->handler->updateContext($conversationId, $newContext, true);
        $this->assertTrue($result);

        // Verify context replaced
        $history = $this->handler->getConversationHistory($conversationId);
        $this->assertNotInstanceOf(WP_Error::class, $history);

        $context = $history['conversation']['context'];
        $this->assertEquals('checkout', $context['page']);
        $this->assertEquals(1, $context['step']);
        $this->assertArrayNotHasKey('product_id', $context);
    }

    // ========================================================================
    // Conversation Ending Tests
    // ========================================================================

    /**
     * Test ending active conversation
     *
     * @since 1.0.0
     */
    public function test_endConversation_should_mark_conversation_as_ended()
    {
        $conversationId = $this->handler->startConversation($this->testUserId, $this->testSessionId);
        $this->assertNotInstanceOf(WP_Error::class, $conversationId);

        $result = $this->handler->endConversation($conversationId);
        $this->assertTrue($result);

        // Verify conversation marked as ended
        global $wpdb;
        $conversation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tableNames['conversations']} WHERE conversation_id = %s",
            $conversationId
        ));

        $this->assertEquals('ended', $conversation->status);
        $this->assertNotNull($conversation->ended_at);
    }

    /**
     * Test ending conversation with feedback
     *
     * @since 1.0.0
     */
    public function test_endConversation_should_store_feedback_when_provided()
    {
        $conversationId = $this->handler->startConversation($this->testUserId, $this->testSessionId);
        $this->assertNotInstanceOf(WP_Error::class, $conversationId);

        $feedback = [
            'rating' => 5,
            'feedback' => 'Excellent service!'
        ];

        $result = $this->handler->endConversation($conversationId, $feedback);
        $this->assertTrue($result);

        // Verify feedback stored
        global $wpdb;
        $conversation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tableNames['conversations']} WHERE conversation_id = %s",
            $conversationId
        ));

        $this->assertEquals(5, $conversation->user_rating);
        $this->assertEquals('Excellent service!', $conversation->user_feedback);
    }

    /**
     * Test ending conversation that's already ended
     *
     * @since 1.0.0
     */
    public function test_endConversation_should_return_error_when_conversation_already_ended()
    {
        $conversationId = $this->handler->startConversation($this->testUserId, $this->testSessionId);
        $this->assertNotInstanceOf(WP_Error::class, $conversationId);

        // End conversation first time
        $result1 = $this->handler->endConversation($conversationId);
        $this->assertTrue($result1);

        // Try to end again
        $result2 = $this->handler->endConversation($conversationId);
        $this->assertInstanceOf(WP_Error::class, $result2);
        $this->assertEquals('conversation_not_active', $result2->get_error_code());
    }

    // ========================================================================
    // Session Management Tests
    // ========================================================================

    /**
     * Test getting session data
     *
     * @since 1.0.0
     */
    public function test_getSessionData_should_return_session_information()
    {
        $sessionData = $this->handler->getSessionData($this->testSessionId);
        
        $this->assertIsArray($sessionData);
        $this->assertEquals($this->testSessionId, $sessionData['session_id']);
        $this->assertArrayHasKey('created_at', $sessionData);
        $this->assertArrayHasKey('last_activity', $sessionData);
        $this->assertArrayHasKey('conversation_count', $sessionData);
    }

    /**
     * Test updating session data
     *
     * @since 1.0.0
     */
    public function test_updateSessionData_should_update_session_information()
    {
        $updateData = [
            'page_views' => ['/product/123', '/cart'],
            'user_preferences' => ['theme' => 'dark']
        ];

        $result = $this->handler->updateSessionData($this->testSessionId, $updateData);
        $this->assertTrue($result);

        // Verify data updated
        $sessionData = $this->handler->getSessionData($this->testSessionId);
        $this->assertEquals(['/product/123', '/cart'], $sessionData['page_views']);
        $this->assertEquals(['theme' => 'dark'], $sessionData['user_preferences']);
    }

    // ========================================================================
    // Cleanup and Maintenance Tests
    // ========================================================================

    /**
     * Test cleanup of old conversations
     *
     * @since 1.0.0
     */
    public function test_cleanupOldConversations_should_remove_expired_conversations()
    {
        // Create old conversation by manipulating database directly
        global $wpdb;
        $oldConversationId = 'old_conv_' . wp_generate_uuid4();
        $oldDate = date('Y-m-d H:i:s', strtotime('-31 days'));

        $wpdb->insert($this->tableNames['conversations'], [
            'conversation_id' => $oldConversationId,
            'user_id' => null, // Guest conversation
            'session_id' => 'old_session',
            'status' => 'ended',
            'context' => '{}',
            'started_at' => $oldDate,
            'updated_at' => $oldDate,
            'ended_at' => $oldDate,
            'total_messages' => 0
        ]);

        // Run cleanup
        $results = $this->handler->cleanupOldConversations([
            'older_than_days' => 30,
            'dry_run' => false
        ]);

        $this->assertIsArray($results);
        $this->assertEquals(1, $results['conversations_deleted']);
        $this->assertEmpty($results['errors']);

        // Verify conversation deleted
        $conversation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tableNames['conversations']} WHERE conversation_id = %s",
            $oldConversationId
        ));
        $this->assertNull($conversation);
    }

    /**
     * Test cleanup dry run mode
     *
     * @since 1.0.0
     */
    public function test_cleanupOldConversations_should_not_delete_data_in_dry_run()
    {
        // Create old conversation
        global $wpdb;
        $oldConversationId = 'old_conv_' . wp_generate_uuid4();
        $oldDate = date('Y-m-d H:i:s', strtotime('-31 days'));

        $wpdb->insert($this->tableNames['conversations'], [
            'conversation_id' => $oldConversationId,
            'user_id' => null, // Guest conversation
            'session_id' => 'old_session',
            'status' => 'ended',
            'context' => '{}',
            'started_at' => $oldDate,
            'updated_at' => $oldDate,
            'ended_at' => $oldDate,
            'total_messages' => 0
        ]);

        // Run dry run cleanup
        $results = $this->handler->cleanupOldConversations([
            'older_than_days' => 30,
            'dry_run' => true
        ]);

        $this->assertTrue($results['dry_run']);
        $this->assertEquals(1, $results['conversations_found']);
        $this->assertEquals(0, $results['conversations_deleted']);

        // Verify conversation still exists
        $conversation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tableNames['conversations']} WHERE conversation_id = %s",
            $oldConversationId
        ));
        $this->assertNotNull($conversation);
    }

    // ========================================================================
    // Error Handling Tests
    // ========================================================================

    /**
     * Test handling of non-existent conversation ID
     *
     * @since 1.0.0
     */
    public function test_methods_should_return_error_for_nonexistent_conversation()
    {
        $nonexistentId = 'nonexistent_conv_id';

        // Test addMessage
        $result = $this->handler->addMessage($nonexistentId, 'user', 'Test message');
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('conversation_not_found', $result->get_error_code());

        // Test getConversationHistory
        $result = $this->handler->getConversationHistory($nonexistentId);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('conversation_not_found', $result->get_error_code());

        // Test updateContext
        $result = $this->handler->updateContext($nonexistentId, ['key' => 'value']);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('conversation_not_found', $result->get_error_code());

        // Test endConversation
        $result = $this->handler->endConversation($nonexistentId);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('conversation_not_found', $result->get_error_code());
    }

    // ========================================================================
    // WordPress Integration Tests
    // ========================================================================

    /**
     * Test WordPress hooks are properly registered
     *
     * @since 1.0.0
     */
    public function test_wordpress_hooks_are_registered()
    {
        // Test that required hooks are registered
        $this->assertGreaterThan(9, has_action('init', [$this->handler, 'scheduleCleanupTasks']));
        $this->assertGreaterThan(9, has_action('woo_ai_assistant_conversation_cleanup', [$this->handler, 'performCleanupTasks']));
    }

    /**
     * Test action hooks are fired correctly
     *
     * @since 1.0.0
     */
    public function test_action_hooks_are_fired()
    {
        $hooksFired = [];

        // Register hook listeners
        add_action('woo_ai_assistant_conversation_started', function($conversationId) use (&$hooksFired) {
            $hooksFired[] = 'conversation_started';
        });

        add_action('woo_ai_assistant_message_added', function($messageId) use (&$hooksFired) {
            $hooksFired[] = 'message_added';
        });

        // Perform actions
        $conversationId = $this->handler->startConversation($this->testUserId, $this->testSessionId);
        $this->handler->addMessage($conversationId, 'user', 'Test message');

        // Verify hooks were fired
        $this->assertContains('conversation_started', $hooksFired);
        $this->assertContains('message_added', $hooksFired);
    }

    // ========================================================================
    // Performance and Cache Tests
    // ========================================================================

    /**
     * Test conversation caching functionality
     *
     * @since 1.0.0
     */
    public function test_conversation_caching_works_correctly()
    {
        $conversationId = $this->handler->startConversation($this->testUserId, $this->testSessionId);
        $this->assertNotInstanceOf(WP_Error::class, $conversationId);

        // First call should cache the data
        $history1 = $this->handler->getConversationHistory($conversationId);
        $this->assertNotInstanceOf(WP_Error::class, $history1);

        // Second call should use cached data
        $history2 = $this->handler->getConversationHistory($conversationId);
        $this->assertNotInstanceOf(WP_Error::class, $history2);

        $this->assertEquals($history1, $history2);
    }

    // ========================================================================
    // Helper Methods
    // ========================================================================

    /**
     * Clean up test data from database
     *
     * @since 1.0.0
     * @return void
     */
    private function cleanupTestData(): void
    {
        global $wpdb;

        // Clean up ALL test data to ensure test isolation
        // This is safe since we're in a test environment
        $wpdb->query("TRUNCATE TABLE {$this->tableNames['messages']}");
        $wpdb->query("TRUNCATE TABLE {$this->tableNames['conversations']}");
        $wpdb->query("TRUNCATE TABLE {$this->tableNames['usage_stats']}");
        
        // Clear all caches including WordPress and internal handler caches
        wp_cache_flush();
        
        // Clear internal ConversationHandler caches using reflection
        if ($this->handler) {
            $reflection = new \ReflectionClass($this->handler);
            
            // Clear conversation cache
            $conversationCacheProperty = $reflection->getProperty('conversationCache');
            $conversationCacheProperty->setAccessible(true);
            $conversationCacheProperty->setValue($this->handler, []);
            
            // Clear session cache
            $sessionCacheProperty = $reflection->getProperty('sessionCache');
            $sessionCacheProperty->setAccessible(true);
            $sessionCacheProperty->setValue($this->handler, []);
        }
    }

    /**
     * Create sample conversation with messages for testing
     *
     * @since 1.0.0
     * @param int $messageCount Number of messages to create
     * @return string Conversation ID
     */
    private function createSampleConversation(int $messageCount = 3): string
    {
        $conversationId = $this->handler->startConversation($this->testUserId, $this->testSessionId);
        $this->assertNotInstanceOf(WP_Error::class, $conversationId);

        for ($i = 1; $i <= $messageCount; $i++) {
            $messageType = ($i % 2 === 1) ? 'user' : 'assistant';
            $content = "Test message $i";
            
            $messageId = $this->handler->addMessage($conversationId, $messageType, $content);
            $this->assertNotInstanceOf(WP_Error::class, $messageId);
        }

        return $conversationId;
    }

    /**
     * Create database tables for testing
     *
     * @since 1.0.0
     * @return void
     */
    private function createTestTables(): void
    {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charset_collate = $wpdb->get_charset_collate();

        // Conversations table
        $conversations_table = $wpdb->prefix . 'woo_ai_conversations';
        $sql_conversations = "CREATE TABLE $conversations_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            conversation_id varchar(255) NOT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            session_id varchar(255) NOT NULL,
            status varchar(20) DEFAULT 'active',
            context longtext DEFAULT NULL,
            started_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            ended_at datetime DEFAULT NULL,
            total_messages int(11) DEFAULT 0,
            user_rating tinyint(1) DEFAULT NULL,
            user_feedback text DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY conversation_id (conversation_id),
            KEY user_id (user_id),
            KEY session_id (session_id),
            KEY status (status),
            KEY started_at (started_at)
        ) $charset_collate;";

        // Messages table  
        $messages_table = $wpdb->prefix . 'woo_ai_messages';
        $sql_messages = "CREATE TABLE $messages_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            conversation_id varchar(255) NOT NULL,
            message_type enum('user','assistant','system') NOT NULL,
            message_content longtext NOT NULL,
            metadata longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            tokens_used int(11) DEFAULT NULL,
            model_used varchar(100) DEFAULT NULL,
            confidence_score decimal(3,2) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY conversation_id (conversation_id),
            KEY message_type (message_type),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Usage stats table
        $usage_stats_table = $wpdb->prefix . 'woo_ai_usage_stats';
        $sql_usage = "CREATE TABLE $usage_stats_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NULL,
            session_id varchar(100) NOT NULL,
            event_type varchar(50) NOT NULL,
            event_data text NULL,
            timestamp datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY session_id (session_id),
            KEY event_type (event_type),
            KEY timestamp (timestamp)
        ) $charset_collate;";

        dbDelta($sql_conversations);
        dbDelta($sql_messages);
        dbDelta($sql_usage);
    }
}