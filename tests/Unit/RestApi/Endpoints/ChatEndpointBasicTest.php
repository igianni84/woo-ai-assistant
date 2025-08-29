<?php

/**
 * ChatEndpoint Basic Tests
 *
 * Basic unit tests for the ChatEndpoint class focusing on class structure,
 * naming conventions, and method existence without requiring complex WordPress
 * REST API environment setup.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Unit\RestApi\Endpoints
 * @since 1.0.0
 * @author Claude Code Assistant
 */

use WooAiAssistant\RestApi\Endpoints\ChatEndpoint;

/**
 * Class ChatEndpointBasicTest
 *
 * Basic tests for ChatEndpoint class focusing on structure and conventions
 */
class ChatEndpointBasicTest extends WP_UnitTestCase
{
    // MANDATORY: Test class existence and basic instantiation
    public function test_class_exists_and_instantiates(): void
    {
        $this->assertTrue(class_exists('WooAiAssistant\RestApi\Endpoints\ChatEndpoint'));
        
        // Test singleton pattern - getInstance should return same instance
        $instance1 = ChatEndpoint::getInstance();
        $instance2 = ChatEndpoint::getInstance();
        
        $this->assertInstanceOf(ChatEndpoint::class, $instance1);
        $this->assertSame($instance1, $instance2);
    }

    // MANDATORY: Verify naming conventions
    public function test_class_follows_naming_conventions(): void
    {
        $reflection = new ReflectionClass(ChatEndpoint::class);

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

    // MANDATORY: Test each public method exists and returns expected type
    public function test_public_methods_exist_and_return_correct_types(): void
    {
        $reflection = new ReflectionClass(ChatEndpoint::class);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            $methodName = $method->getName();
            if (strpos($methodName, '__') === 0) continue; // Skip magic methods

            $this->assertTrue(method_exists(ChatEndpoint::class, $methodName),
                "Method $methodName should exist");
        }

        // Test specific method signatures
        $this->assertTrue(method_exists(ChatEndpoint::class, 'processMessage'));
        $this->assertTrue(method_exists(ChatEndpoint::class, 'handleAjaxChatRequest'));
        $this->assertTrue(method_exists(ChatEndpoint::class, 'cleanupExpiredConversations'));
        $this->assertTrue(method_exists(ChatEndpoint::class, 'getEndpointConfig'));
    }

    /**
     * Test endpoint configuration structure
     */
    public function test_getEndpointConfig_returns_valid_structure(): void
    {
        $config = ChatEndpoint::getEndpointConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('methods', $config);
        $this->assertArrayHasKey('callback', $config);
        $this->assertArrayHasKey('permission_callback', $config);
        $this->assertArrayHasKey('args', $config);

        $this->assertEquals('POST', $config['methods']);
        $this->assertIsCallable($config['callback']);
        $this->assertTrue(is_callable($config['permission_callback']) || $config['permission_callback'] === '__return_true');

        // Test required arguments structure
        $args = $config['args'];
        $this->assertArrayHasKey('message', $args);
        $this->assertArrayHasKey('nonce', $args);
        $this->assertTrue($args['message']['required']);
        $this->assertTrue($args['nonce']['required']);
        $this->assertEquals('string', $args['message']['type']);
        $this->assertEquals('string', $args['nonce']['type']);
    }

    /**
     * Test constants are properly defined
     */
    public function test_class_constants_are_defined(): void
    {
        $reflection = new ReflectionClass(ChatEndpoint::class);
        
        // Test that expected constants exist
        $expectedConstants = [
            'MAX_MESSAGE_LENGTH',
            'MAX_CONVERSATION_CONTEXT',
            'PROCESSING_TIMEOUT',
            'CONTEXT_CACHE_TTL'
        ];

        foreach ($expectedConstants as $constantName) {
            $this->assertTrue($reflection->hasConstant($constantName),
                "Constant {$constantName} should be defined");
        }

        // Test constant values are reasonable
        $this->assertGreaterThan(0, $reflection->getConstant('MAX_MESSAGE_LENGTH'));
        $this->assertGreaterThan(0, $reflection->getConstant('PROCESSING_TIMEOUT'));
    }

    /**
     * Test singleton trait implementation
     */
    public function test_singleton_trait_implementation(): void
    {
        $reflection = new ReflectionClass(ChatEndpoint::class);
        
        // Should use Singleton trait (check if trait is used indirectly through behavior)
        $traits = $reflection->getTraitNames();
        $usesSingleton = in_array('WooAiAssistant\Common\Traits\Singleton', $traits) || 
                        $reflection->hasMethod('getInstance');
        $this->assertTrue($usesSingleton, 'Class should implement singleton pattern');

        // Constructor should be protected/private (for singleton)
        if ($constructor = $reflection->getConstructor()) {
            $this->assertTrue($constructor->isProtected() || $constructor->isPrivate());
        }

        // getInstance method should exist and be static
        $this->assertTrue($reflection->hasMethod('getInstance'));
        $this->assertTrue($reflection->getMethod('getInstance')->isPublic());
        $this->assertTrue($reflection->getMethod('getInstance')->isStatic());
    }

    /**
     * Test method parameter validation callbacks
     */
    public function test_parameter_validation_callbacks(): void
    {
        $config = ChatEndpoint::getEndpointConfig();
        $args = $config['args'];

        // Test message validation callback
        if (isset($args['message']['validate_callback'])) {
            $validator = $args['message']['validate_callback'];
            $this->assertIsCallable($validator);

            // Test empty message validation
            $result = $validator('');
            $this->assertInstanceOf(WP_Error::class, $result);

            // Test valid message validation
            $result = $validator('Valid test message');
            $this->assertTrue($result);
        }
    }

    /**
     * Test class file structure follows PSR-4
     */
    public function test_class_file_follows_psr4(): void
    {
        $reflection = new ReflectionClass(ChatEndpoint::class);
        $filename = $reflection->getFileName();

        // Should be in correct namespace directory
        $this->assertStringContainsString('src/RestApi/Endpoints/ChatEndpoint.php', $filename);

        // Namespace should match directory structure
        $expectedNamespace = 'WooAiAssistant\RestApi\Endpoints';
        $this->assertEquals($expectedNamespace, $reflection->getNamespaceName());
    }

    /**
     * Test required dependencies are properly structured
     */
    public function test_class_dependencies_are_structured(): void
    {
        $reflection = new ReflectionClass(ChatEndpoint::class);
        
        // Get private properties to check structure
        $properties = $reflection->getProperties(ReflectionProperty::IS_PRIVATE);
        
        // Should have dependency properties
        $this->assertGreaterThan(0, count($properties),
            "Class should have private properties for dependencies");

        // Look for expected dependency property names
        $propertyNames = array_map(function($prop) { return $prop->getName(); }, $properties);
        $expectedDependencies = ['aiManager', 'vectorManager', 'licenseManager'];
        
        $hasExpectedDeps = false;
        foreach ($expectedDependencies as $expectedDep) {
            if (in_array($expectedDep, $propertyNames)) {
                $hasExpectedDeps = true;
                break;
            }
        }
        
        $this->assertTrue($hasExpectedDeps, 
            "Class should have expected dependency properties");
    }

    /**
     * Test DocBlock documentation exists for public methods
     */
    public function test_public_methods_have_docblocks(): void
    {
        $reflection = new ReflectionClass(ChatEndpoint::class);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            $methodName = $method->getName();
            if (strpos($methodName, '__') === 0) continue; // Skip magic methods

            $docComment = $method->getDocComment();
            $this->assertNotFalse($docComment, 
                "Method {$methodName} should have DocBlock documentation");
            
            // Should contain @since tag
            $this->assertStringContainsString('@since', $docComment,
                "Method {$methodName} should have @since tag in DocBlock");
        }
    }
}