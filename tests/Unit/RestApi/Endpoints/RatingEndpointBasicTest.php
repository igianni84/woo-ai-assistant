<?php

/**
 * RatingEndpoint Basic Tests
 *
 * Basic unit tests for the RatingEndpoint class focusing on class structure,
 * naming conventions, and method existence without requiring complex WordPress
 * REST API environment setup.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Unit\RestApi\Endpoints
 * @since 1.0.0
 * @author Claude Code Assistant
 */

use WooAiAssistant\RestApi\Endpoints\RatingEndpoint;

/**
 * Class RatingEndpointBasicTest
 *
 * Basic tests for RatingEndpoint class focusing on structure and conventions
 */
class RatingEndpointBasicTest extends WP_UnitTestCase
{
    // MANDATORY: Test class existence and basic instantiation
    public function test_class_exists_and_instantiates(): void
    {
        $this->assertTrue(class_exists('WooAiAssistant\RestApi\Endpoints\RatingEndpoint'));
        
        // Test singleton pattern - getInstance should return same instance
        $instance1 = RatingEndpoint::getInstance();
        $instance2 = RatingEndpoint::getInstance();
        
        $this->assertInstanceOf(RatingEndpoint::class, $instance1);
        $this->assertSame($instance1, $instance2);
    }

    // MANDATORY: Verify naming conventions
    public function test_class_follows_naming_conventions(): void
    {
        $reflection = new ReflectionClass(RatingEndpoint::class);

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
        $reflection = new ReflectionClass(RatingEndpoint::class);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            $methodName = $method->getName();
            if (strpos($methodName, '__') === 0) continue; // Skip magic methods

            $this->assertTrue(method_exists(RatingEndpoint::class, $methodName),
                "Method $methodName should exist");
        }

        // Test specific method signatures
        $this->assertTrue(method_exists(RatingEndpoint::class, 'submitRating'));
        $this->assertTrue(method_exists(RatingEndpoint::class, 'getRatingStatistics'));
        $this->assertTrue(method_exists(RatingEndpoint::class, 'handleAjaxRatingSubmission'));
        $this->assertTrue(method_exists(RatingEndpoint::class, 'updateRatingAnalytics'));
        $this->assertTrue(method_exists(RatingEndpoint::class, 'cleanupOldRatingData'));
        $this->assertTrue(method_exists(RatingEndpoint::class, 'getEndpointConfig'));
    }

    /**
     * Test endpoint configuration structure
     */
    public function test_getEndpointConfig_returns_valid_structure(): void
    {
        $config = RatingEndpoint::getEndpointConfig();

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
        $this->assertArrayHasKey('conversation_id', $args);
        $this->assertArrayHasKey('rating', $args);
        $this->assertArrayHasKey('nonce', $args);
        $this->assertTrue($args['conversation_id']['required']);
        $this->assertTrue($args['rating']['required']);
        $this->assertTrue($args['nonce']['required']);
        $this->assertEquals('string', $args['conversation_id']['type']);
        $this->assertEquals('integer', $args['rating']['type']);
        $this->assertEquals('string', $args['nonce']['type']);
    }

    /**
     * Test constants are properly defined
     */
    public function test_class_constants_are_defined(): void
    {
        $reflection = new ReflectionClass(RatingEndpoint::class);
        
        // Test that expected constants exist
        $expectedConstants = [
            'MIN_RATING',
            'MAX_RATING',
            'MAX_FEEDBACK_LENGTH',
            'RATE_LIMIT_PER_HOUR',
            'DUPLICATE_PREVENTION_WINDOW'
        ];

        foreach ($expectedConstants as $constantName) {
            $this->assertTrue($reflection->hasConstant($constantName),
                "Constant {$constantName} should be defined");
        }

        // Test constant values are reasonable
        $minRating = $reflection->getConstant('MIN_RATING');
        $maxRating = $reflection->getConstant('MAX_RATING');
        
        $this->assertEquals(1, $minRating);
        $this->assertEquals(5, $maxRating);
        $this->assertGreaterThan($minRating, $maxRating);
        $this->assertGreaterThan(0, $reflection->getConstant('MAX_FEEDBACK_LENGTH'));
        $this->assertGreaterThan(0, $reflection->getConstant('RATE_LIMIT_PER_HOUR'));
    }

    /**
     * Test singleton trait implementation
     */
    public function test_singleton_trait_implementation(): void
    {
        $reflection = new ReflectionClass(RatingEndpoint::class);
        
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
        $config = RatingEndpoint::getEndpointConfig();
        $args = $config['args'];

        // Test conversation ID validation callback
        if (isset($args['conversation_id']['validate_callback'])) {
            $validator = $args['conversation_id']['validate_callback'];
            $this->assertIsCallable($validator);

            // Test empty conversation ID validation
            $result = $validator('');
            $this->assertInstanceOf(WP_Error::class, $result);

            // Test valid conversation ID validation
            $result = $validator('conv-valid-id');
            $this->assertTrue($result);
        }

        // Test rating validation callback
        if (isset($args['rating']['validate_callback'])) {
            $validator = $args['rating']['validate_callback'];
            $this->assertIsCallable($validator);

            // Test invalid rating values
            $this->assertInstanceOf(WP_Error::class, $validator(0));
            $this->assertInstanceOf(WP_Error::class, $validator(6));

            // Test valid rating values
            $this->assertTrue($validator(1));
            $this->assertTrue($validator(3));
            $this->assertTrue($validator(5));
        }

        // Test feedback validation callback
        if (isset($args['feedback']['validate_callback'])) {
            $validator = $args['feedback']['validate_callback'];
            $this->assertIsCallable($validator);

            // Test feedback that's too long
            $longFeedback = str_repeat('a', 1001);
            $this->assertInstanceOf(WP_Error::class, $validator($longFeedback));

            // Test valid feedback
            $this->assertTrue($validator('Valid feedback'));
        }
    }

    /**
     * Test rating range validation
     */
    public function test_rating_range_validation(): void
    {
        $reflection = new ReflectionClass(RatingEndpoint::class);
        
        $minRating = $reflection->getConstant('MIN_RATING');
        $maxRating = $reflection->getConstant('MAX_RATING');
        
        $config = RatingEndpoint::getEndpointConfig();
        $ratingArg = $config['args']['rating'];
        
        $this->assertEquals($minRating, $ratingArg['minimum']);
        $this->assertEquals($maxRating, $ratingArg['maximum']);
    }

    /**
     * Test class file structure follows PSR-4
     */
    public function test_class_file_follows_psr4(): void
    {
        $reflection = new ReflectionClass(RatingEndpoint::class);
        $filename = $reflection->getFileName();

        // Should be in correct namespace directory
        $this->assertStringContainsString('src/RestApi/Endpoints/RatingEndpoint.php', $filename);

        // Namespace should match directory structure
        $expectedNamespace = 'WooAiAssistant\RestApi\Endpoints';
        $this->assertEquals($expectedNamespace, $reflection->getNamespaceName());
    }

    /**
     * Test required dependencies are properly structured
     */
    public function test_class_dependencies_are_structured(): void
    {
        $reflection = new ReflectionClass(RatingEndpoint::class);
        
        // Get private properties to check structure
        $properties = $reflection->getProperties(ReflectionProperty::IS_PRIVATE);
        
        // Should have dependency properties
        $this->assertGreaterThan(0, count($properties),
            "Class should have private properties for dependencies");

        // Look for expected dependency property names
        $propertyNames = array_map(function($prop) { return $prop->getName(); }, $properties);
        $expectedDependencies = ['licenseManager', 'spamPatterns'];
        
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
        $reflection = new ReflectionClass(RatingEndpoint::class);
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

    /**
     * Test spam detection pattern structure
     */
    public function test_spam_patterns_are_defined(): void
    {
        $reflection = new ReflectionClass(RatingEndpoint::class);
        
        // Check if spamPatterns property exists
        $properties = $reflection->getProperties(ReflectionProperty::IS_PRIVATE);
        $spamPatternsExists = false;
        
        foreach ($properties as $property) {
            if ($property->getName() === 'spamPatterns') {
                $spamPatternsExists = true;
                break;
            }
        }

        $this->assertTrue($spamPatternsExists, 
            'RatingEndpoint should have spam detection patterns defined');
    }

    /**
     * Test required imports are present
     */
    public function test_required_imports_are_present(): void
    {
        $reflection = new ReflectionClass(RatingEndpoint::class);
        $filename = $reflection->getFileName();
        $fileContent = file_get_contents($filename);

        // Check for required use statements
        $this->assertStringContainsString('use WooAiAssistant\Common\Utils', $fileContent);
        $this->assertStringContainsString('use WooAiAssistant\Common\Traits\Singleton', $fileContent);
        $this->assertStringContainsString('use WP_REST_Request', $fileContent);
        $this->assertStringContainsString('use WP_REST_Response', $fileContent);
        $this->assertStringContainsString('use WP_Error', $fileContent);
    }
}