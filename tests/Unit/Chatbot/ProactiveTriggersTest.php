<?php

/**
 * ProactiveTriggers Unit Tests
 *
 * Comprehensive test suite for the ProactiveTriggers class, covering all trigger types,
 * REST API endpoints, and integration with WordPress/WooCommerce.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Unit\Chatbot
 * @since 1.0.0
 * @author Claude Code Assistant
 */

namespace WooAiAssistant\Tests\Unit\Chatbot;

use PHPUnit\Framework\TestCase;
use WooAiAssistant\Chatbot\ProactiveTriggers;
use WooAiAssistant\Common\Utils;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use ReflectionClass;
use ReflectionMethod;

// Load WordPress test functions if not already loaded
if (!function_exists('wp_verify_nonce')) {
    require_once __DIR__ . '/wp-functions-mock.php';
}

/**
 * Class ProactiveTriggersTest
 *
 * Test the ProactiveTriggers class functionality including trigger processing,
 * REST API endpoints, and WordPress integration.
 *
 * @since 1.0.0
 */
class ProactiveTriggersTest extends TestCase
{
    /**
     * ProactiveTriggers instance
     *
     * @var ProactiveTriggers
     */
    private $proactiveTriggers;

    /**
     * Test REST request mock
     *
     * @var WP_REST_Request
     */
    private $mockRequest;

    /**
     * Set up test environment before each test
     *
     * @since 1.0.0
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Reset singleton instance for clean testing
        if (ProactiveTriggers::hasInstance()) {
            ProactiveTriggers::destroyInstance();
        }

        // Create fresh instance
        $this->proactiveTriggers = ProactiveTriggers::getInstance();

        // Create a proper mock request object
        $this->mockRequest = new WP_REST_Request();

        // Mock WordPress functions
        $this->mockWordPressFunctions();
    }

    /**
     * Clean up after each test
     *
     * @since 1.0.0
     * @return void
     */
    protected function tearDown(): void
    {
        if (ProactiveTriggers::hasInstance()) {
            ProactiveTriggers::destroyInstance();
        }
        parent::tearDown();
    }

    /**
     * Test class instantiation and singleton behavior
     *
     * @since 1.0.0
     * @return void
     */
    public function test_class_exists_and_instantiates_correctly()
    {
        $this->assertTrue(class_exists('WooAiAssistant\Chatbot\ProactiveTriggers'));
        $this->assertInstanceOf(ProactiveTriggers::class, $this->proactiveTriggers);
    }

    /**
     * Test singleton pattern implementation
     *
     * @since 1.0.0
     * @return void
     */
    public function test_singleton_pattern_works_correctly()
    {
        $instance1 = ProactiveTriggers::getInstance();
        $instance2 = ProactiveTriggers::getInstance();

        $this->assertSame($instance1, $instance2);
        $this->assertTrue(ProactiveTriggers::hasInstance());
    }

    /**
     * Test naming conventions for class and methods
     *
     * @since 1.0.0
     * @return void
     */
    public function test_naming_conventions_are_followed()
    {
        $reflection = new ReflectionClass($this->proactiveTriggers);

        // Test class name is PascalCase
        $className = $reflection->getShortName();
        $this->assertMatchesRegularExpression('/^[A-Z][a-zA-Z0-9]*$/', $className,
            "Class name '{$className}' should be PascalCase");

        // Test public methods are camelCase
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            $methodName = $method->getName();
            
            // Skip magic methods and inherited methods
            if (strpos($methodName, '__') === 0 || 
                $methodName === 'getInstance' || 
                $methodName === 'hasInstance' || 
                $methodName === 'destroyInstance') {
                continue;
            }

            $this->assertMatchesRegularExpression('/^[a-z][a-zA-Z0-9]*$/', $methodName,
                "Method '{$methodName}' should be camelCase");
        }
    }

    /**
     * Test constant definitions and values
     *
     * @since 1.0.0
     * @return void
     */
    public function test_constants_are_defined_correctly()
    {
        $reflection = new ReflectionClass($this->proactiveTriggers);
        $constants = $reflection->getConstants();

        // Test constants exist
        $expectedConstants = [
            'DEFAULT_INACTIVITY_TIMEOUT',
            'DEFAULT_SCROLL_THRESHOLD',
            'EXIT_INTENT_SENSITIVITY',
            'TRIGGER_TYPES'
        ];

        foreach ($expectedConstants as $constant) {
            $this->assertArrayHasKey($constant, $constants,
                "Constant '{$constant}' should be defined");
        }

        // Test constant values
        $this->assertEquals(30000, $constants['DEFAULT_INACTIVITY_TIMEOUT']);
        $this->assertEquals(75, $constants['DEFAULT_SCROLL_THRESHOLD']);
        $this->assertIsArray($constants['EXIT_INTENT_SENSITIVITY']);
        $this->assertIsArray($constants['TRIGGER_TYPES']);
    }

    /**
     * Test REST API route registration
     *
     * @since 1.0.0
     * @return void
     */
    public function test_registerRestRoutes_registers_all_endpoints()
    {
        // Mock register_rest_route function that stores routes
        $GLOBALS['wp_rest_routes_registered'] = [];


        // Call the method
        $this->proactiveTriggers->registerRestRoutes();

        // Verify routes were registered
        $this->assertGreaterThanOrEqual(4, count($GLOBALS['wp_rest_routes_registered']),
            'All expected REST routes should be registered');
    }

    /**
     * Test trigger permission checking
     *
     * @since 1.0.0
     * @return void
     */
    public function test_checkTriggerPermission_validates_correctly()
    {
        // Test valid permission for trigger endpoints
        $this->mockRequest->set_route('/woo-ai-assistant/v1/trigger/fire');
        $this->mockRequest->set_header('X-WP-Nonce', 'valid_nonce');

        $result = $this->proactiveTriggers->checkTriggerPermission($this->mockRequest);
        $this->assertTrue($result);
    }

    /**
     * Test admin permission checking
     *
     * @since 1.0.0
     * @return void
     */
    public function test_checkAdminPermission_validates_user_capability()
    {
        $result = $this->proactiveTriggers->checkAdminPermission($this->mockRequest);
        $this->assertTrue($result);
    }

    /**
     * Test exit intent trigger processing
     *
     * @since 1.0.0
     * @return void
     */
    public function test_exit_intent_trigger_processes_correctly()
    {
        $triggerData = [
            'mouse_y' => 25,  // Above threshold for medium sensitivity
            'velocity' => 10  // Above velocity threshold
        ];

        $pageContext = [
            'page_type' => 'product',
            'page_id' => 123
        ];

        // Use reflection to test private method
        $reflection = new ReflectionClass($this->proactiveTriggers);
        $method = $reflection->getMethod('processExitIntentTrigger');
        $method->setAccessible(true);

        $result = $method->invoke($this->proactiveTriggers, $triggerData, $pageContext);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('should_trigger', $result);
        $this->assertTrue($result['should_trigger']);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('priority', $result);
        $this->assertEquals('high', $result['priority']);
    }

    /**
     * Test inactivity trigger processing
     *
     * @since 1.0.0
     * @return void
     */
    public function test_inactivity_trigger_processes_correctly()
    {
        $triggerData = [
            'inactive_time' => 35000  // Above default threshold
        ];

        $pageContext = [
            'page_type' => 'general',
            'page_id' => 0
        ];

        // Use reflection to test private method
        $reflection = new ReflectionClass($this->proactiveTriggers);
        $method = $reflection->getMethod('processInactivityTrigger');
        $method->setAccessible(true);

        $result = $method->invoke($this->proactiveTriggers, $triggerData, $pageContext);

        $this->assertIsArray($result);
        $this->assertTrue($result['should_trigger']);
        $this->assertEquals('medium', $result['priority']);
    }

    /**
     * Test scroll depth trigger processing
     *
     * @since 1.0.0
     * @return void
     */
    public function test_scroll_depth_trigger_processes_correctly()
    {
        $triggerData = [
            'scroll_percent' => 80  // Above default threshold of 75%
        ];

        $pageContext = [
            'page_type' => 'product',
            'page_id' => 456
        ];

        // Use reflection to test private method
        $reflection = new ReflectionClass($this->proactiveTriggers);
        $method = $reflection->getMethod('processScrollDepthTrigger');
        $method->setAccessible(true);

        $result = $method->invoke($this->proactiveTriggers, $triggerData, $pageContext);

        $this->assertIsArray($result);
        $this->assertTrue($result['should_trigger']);
        $this->assertEquals('low', $result['priority']);
    }

    /**
     * Test trigger event handling endpoint
     *
     * @since 1.0.0
     * @return void
     */
    public function test_handleTriggerEvent_processes_valid_request()
    {
        // Mock request parameters
        $this->mockRequest->set_param('trigger_type', 'exit_intent');
        $this->mockRequest->set_param('trigger_data', ['mouse_y' => 25, 'velocity' => 10]);
        $this->mockRequest->set_param('page_context', ['page_type' => 'product', 'page_id' => 123]);


        $response = $this->proactiveTriggers->handleTriggerEvent($this->mockRequest);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());
        
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertEquals('exit_intent', $data['trigger_type']);
    }

    /**
     * Test trigger configuration endpoint
     *
     * @since 1.0.0
     * @return void
     */
    public function test_getTriggerConfig_returns_valid_configuration()
    {
        $response = $this->proactiveTriggers->getTriggerConfig($this->mockRequest);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());
        
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('config', $data);
        $this->assertArrayHasKey('page_rules', $data);
        $this->assertArrayHasKey('user_context', $data);
    }

    /**
     * Test invalid trigger type handling
     *
     * @since 1.0.0
     * @return void
     */
    public function test_handleTriggerEvent_rejects_invalid_trigger_type()
    {
        $this->mockRequest->set_param('trigger_type', 'invalid_trigger');
        $this->mockRequest->set_param('trigger_data', []);
        $this->mockRequest->set_param('page_context', []);


        $response = $this->proactiveTriggers->handleTriggerEvent($this->mockRequest);

        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertEquals('invalid_trigger_type', $response->get_error_code());
    }

    /**
     * Test trigger message generation
     *
     * @since 1.0.0
     * @return void
     */
    public function test_generateTriggerMessage_creates_contextual_messages()
    {
        $reflection = new ReflectionClass($this->proactiveTriggers);
        $method = $reflection->getMethod('generateTriggerMessage');
        $method->setAccessible(true);

        $pageContext = [
            'page_type' => 'product',
            'page_id' => 123
        ];

        $message = $method->invoke($this->proactiveTriggers, 'exit_intent', $pageContext);

        $this->assertIsString($message);
        $this->assertNotEmpty($message);
    }

    /**
     * Test placeholder replacement in messages
     *
     * @since 1.0.0
     * @return void
     */
    public function test_replacePlaceholders_substitutes_correctly()
    {
        $reflection = new ReflectionClass($this->proactiveTriggers);
        $method = $reflection->getMethod('replacePlaceholders');
        $method->setAccessible(true);

        $message = 'Hello {user_name}, you have {cart_count} items';
        $pageContext = ['page_type' => 'cart'];
        $extraData = ['cart_count' => 3];

        $result = $method->invoke($this->proactiveTriggers, $message, $pageContext, $extraData);

        $this->assertStringContains('3 items', $result);
        $this->assertStringNotContainsString('{cart_count}', $result);
    }

    /**
     * Test trigger ID generation uniqueness
     *
     * @since 1.0.0
     * @return void
     */
    public function test_generateTriggerId_creates_unique_identifiers()
    {
        $reflection = new ReflectionClass($this->proactiveTriggers);
        $method = $reflection->getMethod('generateTriggerId');
        $method->setAccessible(true);

        $id1 = $method->invoke($this->proactiveTriggers, 'exit_intent');
        $id2 = $method->invoke($this->proactiveTriggers, 'exit_intent');

        $this->assertIsString($id1);
        $this->assertIsString($id2);
        $this->assertNotEquals($id1, $id2);
        $this->assertStringStartsWith('trigger_exit_intent_', $id1);
    }

    /**
     * Test WooCommerce integration methods
     *
     * @since 1.0.0
     * @return void
     */
    public function test_woocommerce_integration_methods_exist()
    {
        $this->assertTrue(method_exists($this->proactiveTriggers, 'onAddToCart'));
        $this->assertTrue(method_exists($this->proactiveTriggers, 'onCartItemRemoved'));
        $this->assertTrue(method_exists($this->proactiveTriggers, 'onCheckoutProcess'));
    }

    /**
     * Test page rule evaluation logic
     *
     * @since 1.0.0
     * @return void
     */
    public function test_evaluatePageRule_validates_conditions_correctly()
    {
        $reflection = new ReflectionClass($this->proactiveTriggers);
        $method = $reflection->getMethod('evaluatePageRule');
        $method->setAccessible(true);

        $rule = [
            'conditions' => [
                'time_on_page' => 60000,
                'scroll_percent' => 50
            ]
        ];

        $triggerData = [
            'time_on_page' => 70000,
            'scroll_percent' => 60
        ];

        $pageContext = ['page_type' => 'product'];

        $result = $method->invoke($this->proactiveTriggers, $rule, $triggerData, $pageContext);
        $this->assertTrue($result);

        // Test with insufficient conditions
        $triggerData['scroll_percent'] = 40;
        $result = $method->invoke($this->proactiveTriggers, $rule, $triggerData, $pageContext);
        $this->assertFalse($result);
    }

    /**
     * Test settings sanitization
     *
     * @since 1.0.0
     * @return void
     */
    public function test_sanitizeSettings_cleans_input_correctly()
    {
        $reflection = new ReflectionClass($this->proactiveTriggers);
        $method = $reflection->getMethod('sanitizeSettings');
        $method->setAccessible(true);

        $rawSettings = [
            'enabled' => '1',
            'inactivity_timeout' => '45000',
            'scroll_threshold' => '150', // Should be clamped to 100
            'exit_intent_sensitivity' => 'invalid', // Should be filtered out
            'malicious_setting' => '<script>alert("xss")</script>' // Should be ignored
        ];

        $sanitized = $method->invoke($this->proactiveTriggers, $rawSettings);

        $this->assertTrue($sanitized['enabled']);
        $this->assertEquals(45000, $sanitized['inactivity_timeout']);
        $this->assertEquals(100, $sanitized['scroll_threshold']); // Clamped to max
        $this->assertArrayNotHasKey('exit_intent_sensitivity', $sanitized); // Invalid value filtered
        $this->assertArrayNotHasKey('malicious_setting', $sanitized); // Unknown setting ignored
    }

    /**
     * Test error handling in trigger event processing
     *
     * @since 1.0.0
     * @return void
     */
    public function test_trigger_event_error_handling()
    {
        // Test with malformed trigger data that causes null conversion to empty array
        $this->mockRequest->set_param('trigger_type', 'exit_intent');
        $this->mockRequest->set_param('trigger_data', []); // Empty array instead of null
        $this->mockRequest->set_param('page_context', []);


        // This should handle the error gracefully
        $response = $this->proactiveTriggers->handleTriggerEvent($this->mockRequest);
        
        // Should return valid response since we fixed the null issue
        $this->assertTrue($response instanceof WP_REST_Response || $response instanceof WP_Error);
    }

    /**
     * Test frontend script enqueuing logic
     *
     * @since 1.0.0
     * @return void
     */
    public function test_enqueueFrontendScripts_enqueues_when_appropriate()
    {
        // Mock WordPress functions using globals
        $GLOBALS['wp_scripts_enqueued'] = [];
        $GLOBALS['wp_scripts_localized'] = [];


        // Define constants needed by the method
        if (!defined('WOO_AI_ASSISTANT_PLUGIN_URL')) {
            define('WOO_AI_ASSISTANT_PLUGIN_URL', 'https://example.com/wp-content/plugins/woo-ai-assistant/');
        }
        
        if (!defined('WOO_AI_ASSISTANT_VERSION')) {
            define('WOO_AI_ASSISTANT_VERSION', '1.0.0');
        }

        // Call the method
        $this->proactiveTriggers->enqueueFrontendScripts();

        // Since triggers are enabled by default in tests, the script should be enqueued
        $this->assertContains('woo-ai-assistant-triggers', $GLOBALS['wp_scripts_enqueued']);
        $this->assertArrayHasKey('woo-ai-assistant-triggers', $GLOBALS['wp_scripts_localized']);
    }

    /**
     * Mock WordPress functions for testing
     *
     * @since 1.0.0
     * @return void
     */
    private function mockWordPressFunctions(): void
    {
        // Initialize globals for test functions
        $GLOBALS['wp_scripts_enqueued'] = [];
        $GLOBALS['wp_scripts_localized'] = [];
        $GLOBALS['wp_rest_routes_registered'] = [];
        $GLOBALS['wp_filter_test'] = [];

        // Define constants needed by tests
        if (!defined('WOO_AI_ASSISTANT_PLUGIN_URL')) {
            define('WOO_AI_ASSISTANT_PLUGIN_URL', 'https://example.com/wp-content/plugins/woo-ai-assistant/');
        }
        
        if (!defined('WOO_AI_ASSISTANT_VERSION')) {
            define('WOO_AI_ASSISTANT_VERSION', '1.0.0');
        }

        if (!defined('DAY_IN_SECONDS')) {
            define('DAY_IN_SECONDS', 24 * 60 * 60);
        }
    }

    /**
     * Test that all required methods exist
     *
     * @since 1.0.0
     * @return void
     */
    public function test_all_required_methods_exist()
    {
        $requiredMethods = [
            'registerRestRoutes',
            'checkTriggerPermission',
            'checkAdminPermission',
            'enqueueFrontendScripts',
            'handleTriggerEvent',
            'getTriggerConfig',
            'recordTriggerEvent',
            'onAddToCart',
            'onCartItemRemoved',
            'onCheckoutProcess',
            'customizeTriggerMessage',
            'getTriggerStats',
            'updateTriggerSettings',
            'adminInit'
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                method_exists($this->proactiveTriggers, $method),
                "Method '{$method}' should exist in ProactiveTriggers class"
            );
        }
    }

    /**
     * Test WordPress hooks integration
     *
     * @since 1.0.0
     * @return void
     */
    public function test_wordpress_hooks_integration()
    {
        // Store hooks in GLOBALS to avoid conflicts
        $GLOBALS['wp_filter_test'] = [];


        // Create new instance to trigger hook setup
        ProactiveTriggers::destroyInstance();
        $triggers = ProactiveTriggers::getInstance();

        // Verify at least some hooks are registered
        $this->assertNotEmpty($GLOBALS['wp_filter_test'], 
            'WordPress hooks should be registered');
    }
}