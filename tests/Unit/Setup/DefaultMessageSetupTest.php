<?php

/**
 * DefaultMessageSetup Unit Tests
 *
 * Comprehensive unit tests for the DefaultMessageSetup class to ensure
 * proper configuration of default conversation messages and triggers.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Unit\Setup
 * @since 1.0.0
 */

namespace WooAiAssistant\Tests\Unit\Setup;

use WooAiAssistant\Setup\DefaultMessageSetup;
use WooAiAssistant\Tests\WP_UnitTestCase;

/**
 * Class DefaultMessageSetupTest
 *
 * @since 1.0.0
 */
class DefaultMessageSetupTest extends WP_UnitTestCase
{
    /**
     * DefaultMessageSetup instance for testing
     *
     * @var DefaultMessageSetup
     */
    private $messageSetup;

    /**
     * Set up test environment
     *
     * @since 1.0.0
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->messageSetup = DefaultMessageSetup::getInstance();
    }

    /**
     * Test class existence and instantiation
     *
     * @since 1.0.0
     */
    public function test_class_exists_and_instantiates()
    {
        $this->assertTrue(class_exists('WooAiAssistant\Setup\DefaultMessageSetup'));
        $this->assertInstanceOf(DefaultMessageSetup::class, $this->messageSetup);
    }

    /**
     * Test singleton pattern implementation
     *
     * @since 1.0.0
     */
    public function test_singleton_pattern()
    {
        $instance1 = DefaultMessageSetup::getInstance();
        $instance2 = DefaultMessageSetup::getInstance();
        
        $this->assertSame($instance1, $instance2);
    }

    /**
     * Test naming conventions compliance
     *
     * @since 1.0.0
     */
    public function test_class_follows_naming_conventions()
    {
        $reflection = new \ReflectionClass($this->messageSetup);
        
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
     * Test setupInitialConfiguration returns proper structure
     *
     * @since 1.0.0
     */
    public function test_setupInitialConfiguration_returns_proper_structure()
    {
        $result = $this->messageSetup->setupInitialConfiguration();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
        
        if ($result['status'] === 'success') {
            $this->assertArrayHasKey('welcome_messages', $result);
            $this->assertArrayHasKey('fallback_responses', $result);
            $this->assertArrayHasKey('conversation_starters', $result);
            $this->assertArrayHasKey('help_responses', $result);
            $this->assertArrayHasKey('proactive_triggers', $result);
            
            $this->assertIsInt($result['welcome_messages']);
            $this->assertIsInt($result['fallback_responses']);
            $this->assertIsInt($result['conversation_starters']);
            $this->assertIsInt($result['help_responses']);
            $this->assertIsInt($result['proactive_triggers']);
            
            // Check that configuration timestamp is set
            $this->assertTrue((bool) get_option('woo_ai_assistant_default_messages_configured_at', false));
        }
    }

    /**
     * Test welcome message retrieval
     *
     * @since 1.0.0
     */
    public function test_getWelcomeMessage_returns_appropriate_messages()
    {
        // Setup initial configuration
        $this->messageSetup->setupInitialConfiguration();
        
        // Test default/general message
        $message = $this->messageSetup->getWelcomeMessage();
        $this->assertIsString($message);
        $this->assertNotEmpty($message);
        
        // Test context-specific messages
        $productMessage = $this->messageSetup->getWelcomeMessage('product');
        $this->assertIsString($productMessage);
        $this->assertNotEmpty($productMessage);
        
        $shopMessage = $this->messageSetup->getWelcomeMessage('shop');
        $this->assertIsString($shopMessage);
        $this->assertNotEmpty($shopMessage);
        
        // Test fallback for non-existent context
        $fallbackMessage = $this->messageSetup->getWelcomeMessage('nonexistent');
        $this->assertIsString($fallbackMessage);
        $this->assertNotEmpty($fallbackMessage);
    }

    /**
     * Test fallback response retrieval
     *
     * @since 1.0.0
     */
    public function test_getFallbackResponse_returns_appropriate_responses()
    {
        // Setup initial configuration
        $this->messageSetup->setupInitialConfiguration();
        
        // Test default fallback
        $response = $this->messageSetup->getFallbackResponse();
        $this->assertIsString($response);
        $this->assertNotEmpty($response);
        
        // Test specific trigger fallback
        $noMatchResponse = $this->messageSetup->getFallbackResponse('no_match_general');
        $this->assertIsString($noMatchResponse);
        $this->assertNotEmpty($noMatchResponse);
        
        // Test context-specific fallback
        $productResponse = $this->messageSetup->getFallbackResponse('no_match_product', 'product');
        $this->assertIsString($productResponse);
        $this->assertNotEmpty($productResponse);
        
        // Test fallback for non-existent trigger
        $fallback = $this->messageSetup->getFallbackResponse('nonexistent');
        $this->assertIsString($fallback);
        $this->assertNotEmpty($fallback);
    }

    /**
     * Test conversation starters retrieval
     *
     * @since 1.0.0
     */
    public function test_getConversationStarters_returns_proper_format()
    {
        // Setup initial configuration
        $this->messageSetup->setupInitialConfiguration();
        
        // Test with default limit
        $starters = $this->messageSetup->getConversationStarters();
        $this->assertIsArray($starters);
        $this->assertLessThanOrEqual(6, count($starters));
        
        // Test with custom limit
        $limitedStarters = $this->messageSetup->getConversationStarters(3);
        $this->assertIsArray($limitedStarters);
        $this->assertLessThanOrEqual(3, count($limitedStarters));
        
        // Verify structure of starter items
        if (!empty($starters)) {
            foreach ($starters as $starter) {
                $this->assertIsArray($starter);
                $this->assertArrayHasKey('text', $starter);
                $this->assertArrayHasKey('action', $starter);
                $this->assertArrayHasKey('icon', $starter);
                $this->assertArrayHasKey('priority', $starter);
                
                $this->assertIsString($starter['text']);
                $this->assertIsString($starter['action']);
                $this->assertIsString($starter['icon']);
                $this->assertIsInt($starter['priority']);
            }
        }
    }

    /**
     * Test conversation starters when disabled
     *
     * @since 1.0.0
     */
    public function test_getConversationStarters_returns_empty_when_disabled()
    {
        // Setup initial configuration
        $this->messageSetup->setupInitialConfiguration();
        
        // Disable conversation starters
        update_option('woo_ai_assistant_show_conversation_starters', 'no');
        
        $starters = $this->messageSetup->getConversationStarters();
        $this->assertIsArray($starters);
        $this->assertEmpty($starters);
    }

    /**
     * Test configuration status checking
     *
     * @since 1.0.0
     */
    public function test_isConfigured_returns_correct_status()
    {
        // Initially should not be configured
        delete_option('woo_ai_assistant_default_messages_configured_at');
        $this->assertFalse($this->messageSetup->isConfigured());
        
        // After setup should be configured
        $this->messageSetup->setupInitialConfiguration();
        $this->assertTrue($this->messageSetup->isConfigured());
    }

    /**
     * Test configuration timestamp retrieval
     *
     * @since 1.0.0
     */
    public function test_getConfigurationTime_returns_correct_value()
    {
        // Initially should return false
        delete_option('woo_ai_assistant_default_messages_configured_at');
        $this->assertFalse($this->messageSetup->getConfigurationTime());
        
        // After setup should return timestamp
        $this->messageSetup->setupInitialConfiguration();
        $timestamp = $this->messageSetup->getConfigurationTime();
        $this->assertIsInt($timestamp);
        $this->assertGreaterThan(0, $timestamp);
    }

    /**
     * Test reset to defaults functionality
     *
     * @since 1.0.0
     */
    public function test_resetToDefaults_works_correctly()
    {
        // Setup initial configuration
        $this->messageSetup->setupInitialConfiguration();
        $this->assertTrue($this->messageSetup->isConfigured());
        
        // Modify a setting
        update_option('woo_ai_assistant_welcome_message', 'Modified message');
        
        // Reset to defaults
        $result = $this->messageSetup->resetToDefaults();
        $this->assertTrue($result);
        
        // Verify it was reconfigured
        $this->assertTrue($this->messageSetup->isConfigured());
        
        // Verify the modified setting was reset
        $welcomeMessage = get_option('woo_ai_assistant_welcome_message');
        $this->assertNotEquals('Modified message', $welcomeMessage);
    }

    /**
     * Test welcome message updating
     *
     * @since 1.0.0
     */
    public function test_updateWelcomeMessage_works_correctly()
    {
        // Setup initial configuration
        $this->messageSetup->setupInitialConfiguration();
        
        $newMessage = 'Updated welcome message for testing';
        $result = $this->messageSetup->updateWelcomeMessage($newMessage, 'standard');
        
        $this->assertTrue($result);
        
        // Verify the message was updated
        $retrievedMessage = $this->messageSetup->getWelcomeMessage('general');
        $this->assertEquals($newMessage, $retrievedMessage);
        
        // Verify the main option was also updated
        $mainWelcome = get_option('woo_ai_assistant_welcome_message');
        $this->assertEquals($newMessage, $mainWelcome);
    }

    /**
     * Test setup statistics
     *
     * @since 1.0.0
     */
    public function test_getSetupStatistics_returns_proper_structure()
    {
        $stats = $this->messageSetup->getSetupStatistics();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('is_configured', $stats);
        $this->assertArrayHasKey('configured_at', $stats);
        $this->assertArrayHasKey('welcome_messages_count', $stats);
        $this->assertArrayHasKey('fallback_responses_count', $stats);
        $this->assertArrayHasKey('conversation_starters_count', $stats);
        $this->assertArrayHasKey('help_responses_count', $stats);
        $this->assertArrayHasKey('proactive_triggers_enabled', $stats);
        $this->assertArrayHasKey('conversation_starters_enabled', $stats);
        
        $this->assertIsBool($stats['is_configured']);
        $this->assertIsInt($stats['welcome_messages_count']);
        $this->assertIsInt($stats['fallback_responses_count']);
        $this->assertIsInt($stats['conversation_starters_count']);
        $this->assertIsInt($stats['help_responses_count']);
        $this->assertIsBool($stats['proactive_triggers_enabled']);
        $this->assertIsBool($stats['conversation_starters_enabled']);
    }

    /**
     * Test default message structure setup
     *
     * @since 1.0.0
     */
    public function test_setupInitialConfiguration_creates_proper_options()
    {
        // Setup initial configuration
        $result = $this->messageSetup->setupInitialConfiguration();
        
        if ($result['status'] === 'success') {
            // Check that all expected options are created
            $welcomeMessages = get_option('woo_ai_assistant_welcome_messages');
            $this->assertIsArray($welcomeMessages);
            $this->assertNotEmpty($welcomeMessages);
            
            $fallbackResponses = get_option('woo_ai_assistant_fallback_responses');
            $this->assertIsArray($fallbackResponses);
            $this->assertNotEmpty($fallbackResponses);
            
            $conversationStarters = get_option('woo_ai_assistant_conversation_starters');
            $this->assertIsArray($conversationStarters);
            $this->assertNotEmpty($conversationStarters);
            
            $helpResponses = get_option('woo_ai_assistant_help_responses');
            $this->assertIsArray($helpResponses);
            $this->assertNotEmpty($helpResponses);
            
            $proactiveTriggers = get_option('woo_ai_assistant_proactive_triggers_templates');
            $this->assertIsArray($proactiveTriggers);
            $this->assertNotEmpty($proactiveTriggers);
            
            // Check conversation flow is setup
            $conversationFlow = get_option('woo_ai_assistant_conversation_flow');
            $this->assertIsArray($conversationFlow);
            $this->assertArrayHasKey('greeting_patterns', $conversationFlow);
            $this->assertArrayHasKey('help_patterns', $conversationFlow);
        }
    }

    /**
     * Test proactive triggers are disabled by default
     *
     * @since 1.0.0
     */
    public function test_proactive_triggers_disabled_by_default()
    {
        $this->messageSetup->setupInitialConfiguration();
        
        $enabled = get_option('woo_ai_assistant_proactive_triggers_enabled');
        $this->assertEquals('no', $enabled);
        
        $stats = $this->messageSetup->getSetupStatistics();
        $this->assertFalse($stats['proactive_triggers_enabled']);
    }

    /**
     * Test conversation starters are enabled by default
     *
     * @since 1.0.0
     */
    public function test_conversation_starters_enabled_by_default()
    {
        $this->messageSetup->setupInitialConfiguration();
        
        $enabled = get_option('woo_ai_assistant_show_conversation_starters');
        $this->assertEquals('yes', $enabled);
        
        $stats = $this->messageSetup->getSetupStatistics();
        $this->assertTrue($stats['conversation_starters_enabled']);
    }

    /**
     * Test store information integration
     *
     * @since 1.0.0
     */
    public function test_store_information_integration()
    {
        // Set up store information
        update_option('blogname', 'Test Store Name');
        
        // Setup messages
        $this->messageSetup->setupInitialConfiguration();
        
        // Get welcome message
        $welcomeMessage = $this->messageSetup->getWelcomeMessage();
        
        // Should include store name
        $this->assertStringContainsString('Test Store Name', $welcomeMessage);
    }

    /**
     * Test private property initialization
     *
     * @since 1.0.0
     */
    public function test_private_properties_are_properly_initialized()
    {
        $reflection = new \ReflectionClass($this->messageSetup);
        
        $expectedProperties = [
            'welcomeMessages',
            'fallbackResponses',
            'conversationStarters',
            'helpResponses',
            'proactiveTriggers',
            'storeInfo'
        ];
        
        foreach ($expectedProperties as $propertyName) {
            $this->assertTrue($reflection->hasProperty($propertyName));
            $property = $reflection->getProperty($propertyName);
            $this->assertTrue($property->isPrivate());
        }
    }

    /**
     * Test method visibility is correct
     *
     * @since 1.0.0
     */
    public function test_method_visibility_is_correct()
    {
        $reflection = new \ReflectionClass($this->messageSetup);
        
        // Public methods
        $publicMethods = [
            'setupInitialConfiguration',
            'getWelcomeMessage',
            'getFallbackResponse',
            'getConversationStarters',
            'isConfigured',
            'getConfigurationTime',
            'resetToDefaults',
            'updateWelcomeMessage',
            'getSetupStatistics'
        ];
        
        foreach ($publicMethods as $methodName) {
            $this->assertTrue($reflection->hasMethod($methodName));
            $method = $reflection->getMethod($methodName);
            $this->assertTrue($method->isPublic());
        }
        
        // Private methods
        $privateMethods = [
            'initializeStoreInfo',
            'setupDefaultMessages',
            'setupWelcomeMessages',
            'setupFallbackResponses',
            'setupConversationStarters',
            'setupHelpResponses',
            'setupProactiveTriggers',
            'configureWelcomeMessages',
            'configureFallbackResponses',
            'configureConversationStarters',
            'configureHelpResponses',
            'configureProactiveTriggers',
            'setupConversationFlow'
        ];
        
        foreach ($privateMethods as $methodName) {
            $this->assertTrue($reflection->hasMethod($methodName));
            $method = $reflection->getMethod($methodName);
            $this->assertTrue($method->isPrivate());
        }
    }

    /**
     * Test error handling in setup
     *
     * @since 1.0.0
     */
    public function test_setup_handles_errors_gracefully()
    {
        // This tests that the setup doesn't fail catastrophically
        // Even if some components are missing or fail
        
        $result = $this->messageSetup->setupInitialConfiguration();
        
        // Should always return an array with status
        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
        
        // Status should be either success or error
        $this->assertContains($result['status'], ['success', 'error']);
        
        if ($result['status'] === 'error') {
            $this->assertArrayHasKey('message', $result);
            $this->assertIsString($result['message']);
        }
    }

    /**
     * Test configuration persists across instances
     *
     * @since 1.0.0
     */
    public function test_configuration_persists_across_instances()
    {
        // Setup with first instance
        $setup1 = DefaultMessageSetup::getInstance();
        $setup1->setupInitialConfiguration();
        $this->assertTrue($setup1->isConfigured());
        
        // Create new instance (should be same due to singleton)
        $setup2 = DefaultMessageSetup::getInstance();
        $this->assertTrue($setup2->isConfigured());
        $this->assertSame($setup1, $setup2);
        
        // Configuration should persist in database
        $welcomeMessage1 = $setup1->getWelcomeMessage();
        $welcomeMessage2 = $setup2->getWelcomeMessage();
        $this->assertEquals($welcomeMessage1, $welcomeMessage2);
    }

    /**
     * Tear down test environment
     *
     * @since 1.0.0
     */
    public function tearDown(): void
    {
        // Clean up options
        delete_option('woo_ai_assistant_default_messages_configured_at');
        delete_option('woo_ai_assistant_welcome_message');
        delete_option('woo_ai_assistant_welcome_messages');
        delete_option('woo_ai_assistant_fallback_responses');
        delete_option('woo_ai_assistant_conversation_starters');
        delete_option('woo_ai_assistant_help_responses');
        delete_option('woo_ai_assistant_proactive_triggers_templates');
        delete_option('woo_ai_assistant_proactive_triggers_enabled');
        delete_option('woo_ai_assistant_show_conversation_starters');
        delete_option('woo_ai_assistant_conversation_flow');
        delete_option('woo_ai_assistant_response_settings');
        delete_option('blogname');
        
        parent::tearDown();
    }
}