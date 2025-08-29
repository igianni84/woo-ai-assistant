<?php

/**
 * Human Handoff Handler Test Class
 *
 * Comprehensive unit tests for the Handoff class covering all functionality
 * including handoff initiation, notification systems, transcript generation,
 * and integration with multiple communication channels.
 *
 * @package WooAiAssistant
 * @subpackage Tests/Unit/Chatbot
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Tests\Unit\Chatbot;

use WooAiAssistant\Chatbot\Handoff;
use WooAiAssistant\Chatbot\ConversationHandler;
use WooAiAssistant\Api\LicenseManager;
use WooAiAssistant\Tests\WP_UnitTestCase;
use WP_Error;
use ReflectionClass;
use ReflectionMethod;

/**
 * Class HandoffTest
 *
 * @since 1.0.0
 */
class HandoffTest extends WP_UnitTestCase
{
    /**
     * Handoff instance
     *
     * @var Handoff
     */
    private $handoff;

    /**
     * Mock conversation handler
     *
     * @var ConversationHandler|\PHPUnit\Framework\MockObject\MockObject
     */
    private $mockConversationHandler;

    /**
     * Mock license manager
     *
     * @var LicenseManager|\PHPUnit\Framework\MockObject\MockObject
     */
    private $mockLicenseManager;

    /**
     * Test conversation ID
     *
     * @var string
     */
    private $testConversationId = 'conv-test-123';

    /**
     * Set up test environment
     *
     * @since 1.0.0
     */
    public function setUp(): void
    {
        parent::setUp();

        // Create database tables
        $this->createTestTables();

        // Mock dependencies
        $this->mockConversationHandler = $this->createMock(ConversationHandler::class);
        $this->mockLicenseManager = $this->createMock(LicenseManager::class);

        // Get Handoff instance and inject mocks
        $this->handoff = Handoff::getInstance();
        
        // Use reflection to inject mock dependencies
        $reflection = new ReflectionClass($this->handoff);
        
        $conversationHandlerProp = $reflection->getProperty('conversationHandler');
        $conversationHandlerProp->setAccessible(true);
        $conversationHandlerProp->setValue($this->handoff, $this->mockConversationHandler);
        
        $licenseManagerProp = $reflection->getProperty('licenseManager');
        $licenseManagerProp->setAccessible(true);
        $licenseManagerProp->setValue($this->handoff, $this->mockLicenseManager);

        // Set up test options
        update_option('woo_ai_assistant_handoff_emails', ['admin@test.com']);
        update_option('woo_ai_assistant_whatsapp_settings', [
            'enabled' => false,
            'phone_number' => '',
            'api_key' => '',
        ]);
        update_option('woo_ai_assistant_live_chat_settings', [
            'enabled' => false,
            'platform' => '',
            'api_key' => '',
        ]);
    }

    /**
     * Tear down test environment
     *
     * @since 1.0.0
     */
    public function tearDown(): void
    {
        $this->dropTestTables();
        parent::tearDown();
    }

    /**
     * Create test database tables
     *
     * @since 1.0.0
     */
    private function createTestTables(): void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Create handoffs table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}woo_ai_handoffs (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            conversation_id varchar(50) NOT NULL,
            reason varchar(50) NOT NULL,
            priority varchar(20) NOT NULL DEFAULT 'medium',
            status varchar(20) NOT NULL DEFAULT 'pending',
            requested_at datetime NOT NULL,
            assigned_to bigint(20) unsigned DEFAULT NULL,
            assigned_at datetime DEFAULT NULL,
            resolved_at datetime DEFAULT NULL,
            user_id bigint(20) unsigned DEFAULT 0,
            user_email varchar(100) DEFAULT '',
            user_message text,
            resolution_notes text,
            metadata longtext,
            PRIMARY KEY (id),
            KEY conversation_id (conversation_id),
            KEY status (status),
            KEY requested_at (requested_at)
        ) $charset_collate";
        $wpdb->query($sql);

        // Create transcripts table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}woo_ai_handoff_transcripts (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            handoff_id bigint(20) unsigned NOT NULL,
            conversation_id varchar(50) NOT NULL,
            transcript_data longtext NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY handoff_id (handoff_id)
        ) $charset_collate";
        $wpdb->query($sql);

        // Create events table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}woo_ai_handoff_events (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            handoff_id bigint(20) unsigned NOT NULL,
            event_type varchar(50) NOT NULL,
            event_data longtext,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY handoff_id (handoff_id)
        ) $charset_collate";
        $wpdb->query($sql);
    }

    /**
     * Drop test database tables
     *
     * @since 1.0.0
     */
    private function dropTestTables(): void
    {
        global $wpdb;
        
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}woo_ai_handoffs");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}woo_ai_handoff_transcripts");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}woo_ai_handoff_events");
    }

    /**
     * Test class exists and instantiates
     *
     * @since 1.0.0
     */
    public function test_class_exists_and_instantiates()
    {
        $this->assertTrue(class_exists('WooAiAssistant\Chatbot\Handoff'));
        $this->assertInstanceOf(Handoff::class, $this->handoff);
    }

    /**
     * Test singleton pattern implementation
     *
     * @since 1.0.0
     */
    public function test_singleton_pattern()
    {
        $instance1 = Handoff::getInstance();
        $instance2 = Handoff::getInstance();
        
        $this->assertSame($instance1, $instance2);
    }

    /**
     * Test naming conventions compliance
     *
     * @since 1.0.0
     */
    public function test_class_follows_naming_conventions()
    {
        $reflection = new ReflectionClass($this->handoff);
        
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
     * Test successful handoff initiation
     *
     * @since 1.0.0
     */
    public function test_initiateHandoff_should_create_handoff_when_valid_conversation()
    {
        // Arrange
        $mockConversation = [
            'conversation_id' => $this->testConversationId,
            'user_id' => 1,
            'user_email' => 'customer@test.com',
            'started_at' => current_time('mysql'),
        ];
        
        $this->mockConversationHandler
            ->expects($this->once())
            ->method('getConversation')
            ->with($this->testConversationId)
            ->willReturn($mockConversation);
        
        $this->mockConversationHandler
            ->expects($this->once())
            ->method('getConversationMessages')
            ->willReturn([
                ['role' => 'user', 'content' => 'Hello', 'created_at' => current_time('mysql')],
                ['role' => 'assistant', 'content' => 'Hi there!', 'created_at' => current_time('mysql')],
            ]);
        
        $this->mockConversationHandler
            ->expects($this->once())
            ->method('getConversationMetadata')
            ->willReturn(['started_at' => current_time('mysql'), 'duration' => 300]);

        // Act
        $result = $this->handoff->initiateHandoff(
            $this->testConversationId,
            Handoff::REASON_COMPLEX_ISSUE,
            ['priority' => Handoff::PRIORITY_HIGH]
        );

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('handoff_id', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertEquals(Handoff::STATUS_PENDING, $result['status']);
        $this->assertArrayHasKey('priority', $result);
        $this->assertEquals(Handoff::PRIORITY_HIGH, $result['priority']);
    }

    /**
     * Test handoff fails with invalid conversation
     *
     * @since 1.0.0
     */
    public function test_initiateHandoff_should_return_error_when_conversation_not_found()
    {
        // Arrange
        $this->mockConversationHandler
            ->expects($this->once())
            ->method('getConversation')
            ->with('invalid-id')
            ->willReturn(new WP_Error('not_found', 'Conversation not found'));

        // Act
        $result = $this->handoff->initiateHandoff(
            'invalid-id',
            Handoff::REASON_USER_REQUEST
        );

        // Assert
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('not_found', $result->get_error_code());
    }

    /**
     * Test rate limiting prevention
     *
     * @since 1.0.0
     */
    public function test_initiateHandoff_should_enforce_rate_limiting()
    {
        // Arrange
        $mockConversation = [
            'conversation_id' => $this->testConversationId,
            'user_id' => 1,
            'user_email' => 'customer@test.com',
        ];
        
        $this->mockConversationHandler
            ->method('getConversation')
            ->willReturn($mockConversation);
        
        $this->mockConversationHandler
            ->method('getConversationMessages')
            ->willReturn([]);
        
        $this->mockConversationHandler
            ->method('getConversationMetadata')
            ->willReturn([]);

        // Simulate hitting rate limit
        $transientKey = 'woo_ai_handoff_rate_' . md5($this->testConversationId);
        set_transient($transientKey, Handoff::MAX_HANDOFFS_PER_HOUR, HOUR_IN_SECONDS);

        // Act
        $result = $this->handoff->initiateHandoff(
            $this->testConversationId,
            Handoff::REASON_USER_REQUEST
        );

        // Assert
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('rate_limited', $result->get_error_code());

        // Clean up
        delete_transient($transientKey);
    }

    /**
     * Test transcript generation
     *
     * @since 1.0.0
     */
    public function test_generateTranscript_should_format_conversation_data()
    {
        // Arrange
        $messages = [
            [
                'role' => 'user',
                'content' => 'I need help with my order',
                'created_at' => '2025-01-01 10:00:00',
                'metadata' => ['intent' => 'support'],
            ],
            [
                'role' => 'assistant',
                'content' => 'I can help you with that',
                'created_at' => '2025-01-01 10:00:30',
                'metadata' => [],
            ],
        ];
        
        $metadata = [
            'started_at' => '2025-01-01 10:00:00',
            'duration' => 60,
            'user_info' => ['name' => 'Test User'],
            'current_page' => '/shop',
            'cart_value' => 99.99,
        ];
        
        $this->mockConversationHandler
            ->expects($this->once())
            ->method('getConversationMessages')
            ->with($this->testConversationId)
            ->willReturn($messages);
        
        $this->mockConversationHandler
            ->expects($this->once())
            ->method('getConversationMetadata')
            ->with($this->testConversationId)
            ->willReturn($metadata);

        // Act
        $transcript = $this->handoff->generateTranscript($this->testConversationId);

        // Assert
        $this->assertIsArray($transcript);
        $this->assertEquals($this->testConversationId, $transcript['conversation_id']);
        $this->assertCount(2, $transcript['messages']);
        $this->assertEquals('I need help with my order', $transcript['messages'][0]['content']);
        $this->assertEquals('/shop', $transcript['context']['current_page']);
        $this->assertEquals(99.99, $transcript['context']['cart_value']);
    }

    /**
     * Test handoff assignment
     *
     * @since 1.0.0
     */
    public function test_assignHandoff_should_update_status_and_agent()
    {
        global $wpdb;
        
        // Arrange - Create a handoff record
        $wpdb->insert(
            $wpdb->prefix . 'woo_ai_handoffs',
            [
                'conversation_id' => $this->testConversationId,
                'reason' => Handoff::REASON_COMPLEX_ISSUE,
                'priority' => Handoff::PRIORITY_HIGH,
                'status' => Handoff::STATUS_PENDING,
                'requested_at' => current_time('mysql'),
                'user_email' => 'customer@test.com',
            ]
        );
        $handoffId = $wpdb->insert_id;
        $agentId = 2; // Test agent user ID

        // Act
        $result = $this->handoff->assignHandoff($handoffId, $agentId);

        // Assert
        $this->assertTrue($result);
        
        // Verify database was updated
        $handoff = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}woo_ai_handoffs WHERE id = %d",
            $handoffId
        ));
        
        $this->assertEquals(Handoff::STATUS_ASSIGNED, $handoff->status);
        $this->assertEquals($agentId, $handoff->assigned_to);
        $this->assertNotNull($handoff->assigned_at);
    }

    /**
     * Test handoff resolution
     *
     * @since 1.0.0
     */
    public function test_resolveHandoff_should_mark_as_resolved_with_notes()
    {
        global $wpdb;
        
        // Arrange - Create a handoff record
        $wpdb->insert(
            $wpdb->prefix . 'woo_ai_handoffs',
            [
                'conversation_id' => $this->testConversationId,
                'reason' => Handoff::REASON_PAYMENT_ISSUE,
                'priority' => Handoff::PRIORITY_URGENT,
                'status' => Handoff::STATUS_ASSIGNED,
                'requested_at' => current_time('mysql'),
                'assigned_to' => 2,
                'assigned_at' => current_time('mysql'),
                'user_email' => 'customer@test.com',
            ]
        );
        $handoffId = $wpdb->insert_id;

        $this->mockConversationHandler
            ->expects($this->once())
            ->method('updateConversationStatus')
            ->with($this->testConversationId, 'resolved_by_human');

        // Act
        $result = $this->handoff->resolveHandoff($handoffId, [
            'notes' => 'Payment issue resolved. Refund processed.',
            'send_recap' => false,
        ]);

        // Assert
        $this->assertTrue($result);
        
        // Verify database was updated
        $handoff = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}woo_ai_handoffs WHERE id = %d",
            $handoffId
        ));
        
        $this->assertEquals(Handoff::STATUS_RESOLVED, $handoff->status);
        $this->assertNotNull($handoff->resolved_at);
        $this->assertEquals('Payment issue resolved. Refund processed.', $handoff->resolution_notes);
    }

    /**
     * Test getting handoff by ID
     *
     * @since 1.0.0
     */
    public function test_getHandoff_should_return_handoff_data_when_exists()
    {
        global $wpdb;
        
        // Arrange - Create a handoff record
        $testData = [
            'conversation_id' => $this->testConversationId,
            'reason' => Handoff::REASON_COMPLAINT,
            'priority' => Handoff::PRIORITY_HIGH,
            'status' => Handoff::STATUS_PENDING,
            'requested_at' => current_time('mysql'),
            'user_email' => 'customer@test.com',
            'user_message' => 'Product quality issue',
        ];
        
        $wpdb->insert($wpdb->prefix . 'woo_ai_handoffs', $testData);
        $handoffId = $wpdb->insert_id;

        // Act
        $result = $this->handoff->getHandoff($handoffId);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals($handoffId, $result['id']);
        $this->assertEquals($this->testConversationId, $result['conversation_id']);
        $this->assertEquals(Handoff::REASON_COMPLAINT, $result['reason']);
        $this->assertEquals('Product quality issue', $result['user_message']);
    }

    /**
     * Test getting handoff statistics
     *
     * @since 1.0.0
     */
    public function test_getHandoffStatistics_should_return_aggregated_data()
    {
        global $wpdb;
        
        // Arrange - Create multiple handoff records
        $testHandoffs = [
            [
                'conversation_id' => 'conv-1',
                'reason' => Handoff::REASON_COMPLEX_ISSUE,
                'status' => Handoff::STATUS_RESOLVED,
                'requested_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
                'resolved_at' => date('Y-m-d H:i:s', strtotime('-2 days +30 minutes')),
                'assigned_to' => 2,
                'assigned_at' => date('Y-m-d H:i:s', strtotime('-2 days +5 minutes')),
            ],
            [
                'conversation_id' => 'conv-2',
                'reason' => Handoff::REASON_USER_REQUEST,
                'status' => Handoff::STATUS_PENDING,
                'requested_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            ],
            [
                'conversation_id' => 'conv-3',
                'reason' => Handoff::REASON_COMPLEX_ISSUE,
                'status' => Handoff::STATUS_RESOLVED,
                'requested_at' => date('Y-m-d H:i:s'),
                'resolved_at' => date('Y-m-d H:i:s', strtotime('+45 minutes')),
                'assigned_to' => 2,
                'assigned_at' => date('Y-m-d H:i:s', strtotime('+10 minutes')),
            ],
        ];
        
        foreach ($testHandoffs as $handoff) {
            $wpdb->insert($wpdb->prefix . 'woo_ai_handoffs', $handoff);
        }

        // Act
        $stats = $this->handoff->getHandoffStatistics();

        // Assert
        $this->assertIsArray($stats);
        $this->assertEquals(3, $stats['total_handoffs']);
        $this->assertIsNumeric($stats['avg_resolution_time_minutes']);
        $this->assertIsArray($stats['status_breakdown']);
        $this->assertIsArray($stats['reason_breakdown']);
        $this->assertIsArray($stats['agent_performance']);
    }

    /**
     * Test cleanup of old handoffs
     *
     * @since 1.0.0
     */
    public function test_cleanupOldHandoffs_should_delete_resolved_handoffs_older_than_threshold()
    {
        global $wpdb;
        
        // Arrange - Create old and recent handoffs
        $oldHandoff = [
            'conversation_id' => 'old-conv',
            'reason' => Handoff::REASON_USER_REQUEST,
            'status' => Handoff::STATUS_RESOLVED,
            'requested_at' => date('Y-m-d H:i:s', strtotime('-100 days')),
            'resolved_at' => date('Y-m-d H:i:s', strtotime('-99 days')),
        ];
        
        $recentHandoff = [
            'conversation_id' => 'recent-conv',
            'reason' => Handoff::REASON_COMPLEX_ISSUE,
            'status' => Handoff::STATUS_RESOLVED,
            'requested_at' => date('Y-m-d H:i:s', strtotime('-10 days')),
            'resolved_at' => date('Y-m-d H:i:s', strtotime('-9 days')),
        ];
        
        $wpdb->insert($wpdb->prefix . 'woo_ai_handoffs', $oldHandoff);
        $oldHandoffId = $wpdb->insert_id;
        
        $wpdb->insert($wpdb->prefix . 'woo_ai_handoffs', $recentHandoff);
        $recentHandoffId = $wpdb->insert_id;

        // Act
        $deletedCount = $this->handoff->cleanupOldHandoffs(90);

        // Assert
        $this->assertEquals(1, $deletedCount);
        
        // Verify old handoff was deleted
        $oldExists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}woo_ai_handoffs WHERE id = %d",
            $oldHandoffId
        ));
        $this->assertEquals(0, $oldExists);
        
        // Verify recent handoff still exists
        $recentExists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}woo_ai_handoffs WHERE id = %d",
            $recentHandoffId
        ));
        $this->assertEquals(1, $recentExists);
    }

    /**
     * Test priority constants are defined correctly
     *
     * @since 1.0.0
     */
    public function test_priority_constants_are_defined()
    {
        $this->assertEquals('low', Handoff::PRIORITY_LOW);
        $this->assertEquals('medium', Handoff::PRIORITY_MEDIUM);
        $this->assertEquals('high', Handoff::PRIORITY_HIGH);
        $this->assertEquals('urgent', Handoff::PRIORITY_URGENT);
    }

    /**
     * Test status constants are defined correctly
     *
     * @since 1.0.0
     */
    public function test_status_constants_are_defined()
    {
        $this->assertEquals('pending', Handoff::STATUS_PENDING);
        $this->assertEquals('assigned', Handoff::STATUS_ASSIGNED);
        $this->assertEquals('in_progress', Handoff::STATUS_IN_PROGRESS);
        $this->assertEquals('resolved', Handoff::STATUS_RESOLVED);
        $this->assertEquals('escalated', Handoff::STATUS_ESCALATED);
    }

    /**
     * Test reason constants are defined correctly
     *
     * @since 1.0.0
     */
    public function test_reason_constants_are_defined()
    {
        $this->assertEquals('user_request', Handoff::REASON_USER_REQUEST);
        $this->assertEquals('complex_issue', Handoff::REASON_COMPLEX_ISSUE);
        $this->assertEquals('payment_issue', Handoff::REASON_PAYMENT_ISSUE);
        $this->assertEquals('technical_issue', Handoff::REASON_TECHNICAL_ISSUE);
        $this->assertEquals('complaint', Handoff::REASON_COMPLAINT);
        $this->assertEquals('sentiment_negative', Handoff::REASON_SENTIMENT_NEGATIVE);
    }

    /**
     * Test channel constants are defined correctly
     *
     * @since 1.0.0
     */
    public function test_channel_constants_are_defined()
    {
        $this->assertEquals('email', Handoff::CHANNEL_EMAIL);
        $this->assertEquals('whatsapp', Handoff::CHANNEL_WHATSAPP);
        $this->assertEquals('live_chat', Handoff::CHANNEL_LIVE_CHAT);
        $this->assertEquals('slack', Handoff::CHANNEL_SLACK);
    }

    /**
     * Test email template constants are defined correctly
     *
     * @since 1.0.0
     */
    public function test_email_template_constants_are_defined()
    {
        $this->assertEquals('human-takeover', Handoff::EMAIL_TEMPLATE_TAKEOVER);
        $this->assertEquals('chat-recap', Handoff::EMAIL_TEMPLATE_RECAP);
    }

    /**
     * Test multiple notification channels
     *
     * @since 1.0.0
     */
    public function test_initiateHandoff_should_send_notifications_to_multiple_channels()
    {
        // Arrange
        $mockConversation = [
            'conversation_id' => $this->testConversationId,
            'user_email' => 'customer@test.com',
        ];
        
        $this->mockConversationHandler
            ->method('getConversation')
            ->willReturn($mockConversation);
        
        $this->mockConversationHandler
            ->method('getConversationMessages')
            ->willReturn([]);
        
        $this->mockConversationHandler
            ->method('getConversationMetadata')
            ->willReturn([]);

        // Act
        $result = $this->handoff->initiateHandoff(
            $this->testConversationId,
            Handoff::REASON_URGENT,
            [
                'channels' => [Handoff::CHANNEL_EMAIL, Handoff::CHANNEL_WHATSAPP],
                'priority' => Handoff::PRIORITY_URGENT,
            ]
        );

        // Assert
        $this->assertIsArray($result);
        $this->assertContains(Handoff::CHANNEL_EMAIL, $result['channels_notified']);
        $this->assertContains(Handoff::CHANNEL_WHATSAPP, $result['channels_notified']);
    }
}