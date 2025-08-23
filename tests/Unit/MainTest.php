<?php
/**
 * Test for Main Class
 * 
 * Following MANDATORY template from CLAUDE.md for QA compliance
 */

namespace WooAiAssistant\Tests\Unit;

use WooAiAssistant\Main;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class MainTest extends TestCase {
    
    private $instance;
    
    public function setUp(): void {
        parent::setUp();
        // Reset singleton instance for testing
        if (method_exists(Main::class, 'destroyInstance')) {
            Main::destroyInstance();
        }
        $this->instance = Main::getInstance();
    }
    
    public function tearDown(): void {
        // Clean up singleton instance
        if (method_exists(Main::class, 'destroyInstance')) {
            Main::destroyInstance();
        }
        parent::tearDown();
    }
    
    // MANDATORY: Test class existence and basic instantiation
    public function test_class_exists_and_instantiates() {
        $this->assertTrue(class_exists('WooAiAssistant\Main'));
        $this->assertInstanceOf('WooAiAssistant\Main', $this->instance);
    }
    
    // MANDATORY: Verify naming conventions
    public function test_class_follows_naming_conventions() {
        $reflection = new ReflectionClass($this->instance);
        
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
    public function test_public_methods_exist_and_return_correct_types() {
        $this->assertTrue(method_exists($this->instance, 'init'), 'Method init should exist');
        $this->assertTrue(method_exists($this->instance, 'getVersion'), 'Method getVersion should exist');
        $this->assertTrue(method_exists($this->instance, 'isInitialized'), 'Method isInitialized should exist');
        $this->assertTrue(method_exists($this->instance, 'getComponent'), 'Method getComponent should exist');
        $this->assertTrue(method_exists($this->instance, 'registerComponent'), 'Method registerComponent should exist');
        $this->assertTrue(method_exists($this->instance, 'getComponents'), 'Method getComponents should exist');
        
        // Test return types
        $this->assertIsString($this->instance->getVersion());
        $this->assertIsBool($this->instance->isInitialized());
        $this->assertIsArray($this->instance->getComponents());
    }
    
    // Test singleton pattern
    public function test_singleton_pattern_works_correctly() {
        $instance1 = Main::getInstance();
        $instance2 = Main::getInstance();
        
        $this->assertSame($instance1, $instance2, 'Singleton should return the same instance');
    }
    
    // Test version retrieval
    public function test_getVersion_returns_correct_version() {
        $version = $this->instance->getVersion();
        $this->assertEquals(WOO_AI_ASSISTANT_VERSION, $version);
        $this->assertIsString($version);
    }
    
    // Test component registration
    public function test_component_registration_works() {
        $testComponent = new \stdClass();
        $testComponent->name = 'test';
        
        $this->instance->registerComponent('test', $testComponent);
        
        $retrievedComponent = $this->instance->getComponent('test');
        $this->assertSame($testComponent, $retrievedComponent);
        
        $components = $this->instance->getComponents();
        $this->assertArrayHasKey('test', $components);
    }
    
    // Test initialization state
    public function test_initialization_state_tracking() {
        // Initially should be false before init is called
        $this->assertFalse($this->instance->isInitialized());
        
        // Note: Full init() testing would require WordPress environment setup
        // For now, we test that the method exists and tracking works
    }
    
    // Test non-existent component retrieval
    public function test_get_nonexistent_component_returns_null() {
        $component = $this->instance->getComponent('non-existent');
        $this->assertNull($component);
    }
}