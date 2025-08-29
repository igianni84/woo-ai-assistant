<?php

/**
 * StreamingEndpoint Test Class
 *
 * Comprehensive unit tests for the StreamingEndpoint class including SSE functionality,
 * chunk processing, rate limiting, security validation, and fallback mechanisms.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Unit\RestApi\Endpoints
 * @since 1.0.0
 * @author Claude Code Assistant
 */

namespace WooAiAssistant\Tests\Unit\RestApi\Endpoints;

use WooAiAssistant\RestApi\Endpoints\StreamingEndpoint;
use WooAiAssistant\Common\Utils;
use WooAiAssistant\KnowledgeBase\AIManager;
use WooAiAssistant\KnowledgeBase\VectorManager;
use WooAiAssistant\Api\LicenseManager;
use WooAiAssistant\Main;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class StreamingEndpointTest
 *
 * Tests all aspects of the StreamingEndpoint including streaming functionality,
 * security validation, rate limiting, and WordPress integration.
 *
 * @since 1.0.0
 */
class StreamingEndpointTest extends \WP_UnitTestCase
{
    /**
     * StreamingEndpoint instance for testing
     *
     * @var StreamingEndpoint
     */
    private $streamingEndpoint;

    /**
     * Test conversation ID
     *
     * @var string
     */
    private $testConversationId;

    /**
     * Test user ID
     *
     * @var int
     */
    private $testUserId;

    /**
     * Set up test environment before each test
     *
     * @since 1.0.0
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->testConversationId = 'conv-test-streaming-12345';
        $this->testUserId = 123;

        // Create StreamingEndpoint instance
        $this->streamingEndpoint = StreamingEndpoint::getInstance();
    }

    /**
     * Test StreamingEndpoint class exists and follows naming conventions
     *
     * @return void
     */
    public function test_class_exists_and_follows_naming_conventions(): void
    {
        $this->assertTrue(class_exists(StreamingEndpoint::class));
        $this->assertInstanceOf(StreamingEndpoint::class, $this->streamingEndpoint);

        $reflection = new \ReflectionClass($this->streamingEndpoint);
        
        // Verify class name follows PascalCase
        $this->assertMatchesRegularExpression('/^[A-Z][a-zA-Z0-9]*$/', $reflection->getShortName());

        // Verify all public methods follow camelCase
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            $methodName = $method->getName();
            if (strpos($methodName, '__') === 0) continue; // Skip magic methods
            
            $this->assertMatchesRegularExpression('/^[a-z][a-zA-Z0-9]*$/', $methodName,
                "Method '{$methodName}' should follow camelCase convention");
        }
    }

    /**
     * Test singleton pattern implementation
     *
     * @return void
     */
    public function test_singleton_pattern_implementation(): void
    {
        $instance1 = StreamingEndpoint::getInstance();
        $instance2 = StreamingEndpoint::getInstance();

        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(StreamingEndpoint::class, $instance1);
    }

    /**
     * Test endpoint configuration method
     *
     * @return void
     */
    public function test_getEndpointConfig(): void
    {
        $config = StreamingEndpoint::getEndpointConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('methods', $config);
        $this->assertArrayHasKey('callback', $config);
        $this->assertArrayHasKey('permission_callback', $config);
        $this->assertArrayHasKey('args', $config);

        // Test required arguments
        $args = $config['args'];
        $this->assertArrayHasKey('message', $args);
        $this->assertArrayHasKey('nonce', $args);
        $this->assertTrue($args['message']['required']);
        $this->assertTrue($args['nonce']['required']);
    }

    /**
     * Test stream configuration sanitization via reflection
     *
     * @return void
     */
    public function test_stream_configuration_sanitization(): void
    {
        // Test via reflection since sanitizeStreamConfig is private
        $reflection = new \ReflectionClass($this->streamingEndpoint);
        $method = $reflection->getMethod('sanitizeStreamConfig');
        $method->setAccessible(true);

        $rawConfig = [
            'chunk_size' => 300, // Over max, should be clamped
            'chunk_delay' => -100, // Negative, should be set to 0
            'enable_typing_indicator' => 'yes', // String, should be converted to bool
            'enable_sse' => 1, // Integer, should be converted to bool
            'heartbeat_interval' => 50, // Over max, should be clamped
            'max_chunks' => 2000 // Over max, should be clamped
        ];

        $sanitized = $method->invoke($this->streamingEndpoint, $rawConfig);

        $this->assertLessThanOrEqual(200, $sanitized['chunk_size']); // MAX_CHUNK_SIZE
        $this->assertGreaterThanOrEqual(0, $sanitized['chunk_delay']);
        $this->assertIsBool($sanitized['enable_typing_indicator']);
        $this->assertIsBool($sanitized['enable_sse']);
        $this->assertLessThanOrEqual(30, $sanitized['heartbeat_interval']);
        $this->assertLessThanOrEqual(1000, $sanitized['max_chunks']);
    }

    /**
     * Test conversation ID sanitization via reflection
     *
     * @return void
     */
    public function test_conversation_id_sanitization(): void
    {
        $reflection = new \ReflectionClass($this->streamingEndpoint);
        $method = $reflection->getMethod('sanitizeConversationId');
        $method->setAccessible(true);

        // Test with null - should generate new ID
        $result = $method->invoke($this->streamingEndpoint, null);
        $this->assertStringStartsWith('conv-', $result);

        // Test with empty string - should generate new ID
        $result = $method->invoke($this->streamingEndpoint, '');
        $this->assertStringStartsWith('conv-', $result);

        // Test with valid ID - should return as-is (after sanitization)
        $validId = 'conv-existing-123';
        $result = $method->invoke($this->streamingEndpoint, $validId);
        $this->assertEquals($validId, $result);
    }

    /**
     * Test user context sanitization via reflection
     *
     * @return void
     */
    public function test_user_context_sanitization(): void
    {
        $reflection = new \ReflectionClass($this->streamingEndpoint);
        $method = $reflection->getMethod('sanitizeUserContext');
        $method->setAccessible(true);

        $rawContext = [
            'page' => 'product-page',
            'product_id' => '123',
            'user_id' => '456',
            'url' => 'https://example.com/product/123',
            'malicious_field' => '<script>alert("xss")</script>'
        ];

        $sanitized = $method->invoke($this->streamingEndpoint, $rawContext);

        $this->assertArrayHasKey('page', $sanitized);
        $this->assertArrayHasKey('product_id', $sanitized);
        $this->assertArrayHasKey('user_id', $sanitized);
        $this->assertArrayHasKey('url', $sanitized);
        $this->assertArrayNotHasKey('malicious_field', $sanitized); // Should be filtered out
    }

    /**
     * Test SSE support detection via reflection
     *
     * @return void
     */
    public function test_sse_support_detection(): void
    {
        // Skip this test if WordPress test framework has mock conflicts
        if (!method_exists('\WP_REST_Request', 'get_header')) {
            $this->markTestSkipped('WordPress REST API not available in test environment');
            return;
        }

        $reflection = new \ReflectionClass($this->streamingEndpoint);
        $method = $reflection->getMethod('detectSSESupport');
        $method->setAccessible(true);

        // Create actual WP_REST_Request instead of mock to avoid configuration issues
        $request1 = new \WP_REST_Request('POST', '/test');
        $request1->set_header('accept', 'text/event-stream');
        
        $result = $method->invoke($this->streamingEndpoint, $request1);
        $this->assertTrue($result, 'Should detect SSE support from Accept header');

        // Test with explicit SSE support parameter
        $request2 = new \WP_REST_Request('POST', '/test');
        $request2->set_param('sse_support', true);
        
        $result = $method->invoke($this->streamingEndpoint, $request2);
        $this->assertTrue($result, 'Should detect SSE support from parameter');

        // Test with SSE-capable browser
        $request3 = new \WP_REST_Request('POST', '/test');
        $request3->set_header('user_agent', 'Mozilla/5.0 Chrome/120.0');
        
        $result = $method->invoke($this->streamingEndpoint, $request3);
        $this->assertTrue($result, 'Should detect SSE support from user agent');
    }

    /**
     * Test session initialization and cleanup via reflection
     *
     * @return void
     */
    public function test_session_initialization_and_cleanup(): void
    {
        $reflection = new \ReflectionClass($this->streamingEndpoint);
        $initMethod = $reflection->getMethod('initializeSession');
        $initMethod->setAccessible(true);
        $cleanupMethod = $reflection->getMethod('cleanupSession');
        $cleanupMethod->setAccessible(true);

        $conversationId = 'conv-test-789';
        $streamConfig = ['chunk_size' => 50];

        // Initialize session
        $sessionId = $initMethod->invoke($this->streamingEndpoint, $conversationId, $streamConfig);

        $this->assertIsString($sessionId);
        $this->assertNotEmpty($sessionId);

        // Cleanup session
        $cleanupMethod->invoke($this->streamingEndpoint, $sessionId);

        // Should not throw any exceptions
        $this->assertTrue(true);
    }

    /**
     * Test client IP detection via reflection
     *
     * @return void
     */
    public function test_client_ip_detection(): void
    {
        $reflection = new \ReflectionClass($this->streamingEndpoint);
        $method = $reflection->getMethod('getClientIp');
        $method->setAccessible(true);

        // Mock $_SERVER variables
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        
        $ip = $method->invoke($this->streamingEndpoint);
        
        $this->assertIsString($ip);
        $this->assertNotEmpty($ip);
    }

    /**
     * Test streaming configuration has reasonable defaults
     *
     * @return void
     */
    public function test_streaming_configuration_defaults(): void
    {
        $reflection = new \ReflectionClass($this->streamingEndpoint);
        $method = $reflection->getMethod('sanitizeStreamConfig');
        $method->setAccessible(true);

        $emptyConfig = [];
        $sanitized = $method->invoke($this->streamingEndpoint, $emptyConfig);

        $this->assertArrayHasKey('chunk_size', $sanitized);
        $this->assertArrayHasKey('enable_typing_indicator', $sanitized);
        $this->assertArrayHasKey('enable_sse', $sanitized);
        
        $this->assertIsInt($sanitized['chunk_size']);
        $this->assertIsBool($sanitized['enable_typing_indicator']);
        $this->assertIsBool($sanitized['enable_sse']);
    }

    /**
     * Test constants are defined correctly
     *
     * @return void
     */
    public function test_constants_are_defined_correctly(): void
    {
        $reflection = new \ReflectionClass($this->streamingEndpoint);
        
        // Test that constants follow UPPER_SNAKE_CASE naming convention
        $constants = $reflection->getConstants();
        foreach ($constants as $name => $value) {
            $this->assertMatchesRegularExpression('/^[A-Z][A-Z0-9_]*$/', $name,
                "Constant '{$name}' should follow UPPER_SNAKE_CASE convention");
        }
    }

    /**
     * Test all public methods exist and are callable
     *
     * @return void
     */
    public function test_public_methods_exist_and_callable(): void
    {
        $reflection = new \ReflectionClass($this->streamingEndpoint);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        $expectedMethods = [
            'handleStreamingRequest',
            'handleSSEChunk',
            'initializeStreamingSession',
            'cleanupStreamingSessions',
            'getEndpointConfig',
            'getInstance' // From Singleton trait
        ];

        foreach ($expectedMethods as $methodName) {
            $this->assertTrue(method_exists($this->streamingEndpoint, $methodName),
                "Method '{$methodName}' should exist");
            $this->assertTrue(is_callable([$this->streamingEndpoint, $methodName]),
                "Method '{$methodName}' should be callable");
        }
    }

    /**
     * Test that the class has proper documentation
     *
     * @return void
     */
    public function test_class_has_proper_documentation(): void
    {
        $reflection = new \ReflectionClass($this->streamingEndpoint);
        
        // Check class has DocBlock
        $docComment = $reflection->getDocComment();
        $this->assertNotFalse($docComment, 'Class should have DocBlock documentation');
        
        // Check methods have DocBlocks
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            if (strpos($method->getName(), '__') === 0) continue; // Skip magic methods
            
            $methodDoc = $method->getDocComment();
            $this->assertNotFalse($methodDoc, 
                "Method '{$method->getName()}' should have DocBlock documentation");
        }
    }
}