<?php
/**
 * Test for Singleton Trait
 * 
 * Following MANDATORY template from CLAUDE.md for QA compliance
 */

namespace WooAiAssistant\Tests\Unit\Common\Traits;

use PHPUnit\Framework\TestCase;

// Test class that uses Singleton trait
class TestSingletonClass {
    use \WooAiAssistant\Common\Traits\Singleton;
    
    public $testProperty = 'test';
}

class SingletonTest extends TestCase {
    
    public function tearDown(): void {
        // Clean up singleton instances after each test
        if (method_exists(TestSingletonClass::class, 'destroyInstance')) {
            TestSingletonClass::destroyInstance();
        }
        parent::tearDown();
    }
    
    // MANDATORY: Test trait existence and basic functionality
    public function test_trait_exists_and_works() {
        $this->assertTrue(trait_exists('WooAiAssistant\Common\Traits\Singleton'));
        $this->assertTrue(method_exists(TestSingletonClass::class, 'getInstance'));
    }
    
    // MANDATORY: Verify naming conventions
    public function test_trait_follows_naming_conventions() {
        $reflection = new \ReflectionClass('WooAiAssistant\Common\Traits\Singleton');
        
        // All public methods must be camelCase
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            $methodName = $method->getName();
            if (strpos($methodName, '__') === 0) continue; // Skip magic methods
            
            $this->assertMatchesRegularExpression('/^[a-z][a-zA-Z0-9]*$/', $methodName,
                "Method '$methodName' must be camelCase");
        }
    }
    
    // MANDATORY: Test each public method exists and returns expected type
    public function test_public_methods_exist_and_return_correct_types() {
        $this->assertTrue(method_exists(TestSingletonClass::class, 'getInstance'), 'Method getInstance should exist');
        $this->assertTrue(method_exists(TestSingletonClass::class, 'hasInstance'), 'Method hasInstance should exist');
        $this->assertTrue(method_exists(TestSingletonClass::class, 'destroyInstance'), 'Method destroyInstance should exist');
        
        // Test return types
        $instance = TestSingletonClass::getInstance();
        $this->assertInstanceOf(TestSingletonClass::class, $instance);
        $this->assertIsBool(TestSingletonClass::hasInstance());
    }
    
    // Test singleton pattern enforcement
    public function test_getInstance_returns_same_instance() {
        $instance1 = TestSingletonClass::getInstance();
        $instance2 = TestSingletonClass::getInstance();
        
        $this->assertSame($instance1, $instance2, 'getInstance should return the same instance');
    }
    
    // Test hasInstance method
    public function test_hasInstance_works_correctly() {
        $this->assertFalse(TestSingletonClass::hasInstance(), 'Should return false before getInstance is called');
        
        TestSingletonClass::getInstance();
        $this->assertTrue(TestSingletonClass::hasInstance(), 'Should return true after getInstance is called');
    }
    
    // Test destroyInstance method
    public function test_destroyInstance_works_correctly() {
        $instance1 = TestSingletonClass::getInstance();
        $this->assertTrue(TestSingletonClass::hasInstance());
        
        TestSingletonClass::destroyInstance();
        $this->assertFalse(TestSingletonClass::hasInstance());
        
        $instance2 = TestSingletonClass::getInstance();
        $this->assertNotSame($instance1, $instance2, 'New instance should be created after destroy');
    }
    
    // Test cloning prevention
    public function test_cloning_throws_exception() {
        $instance = TestSingletonClass::getInstance();
        
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Call to private');
        
        $cloned = clone $instance;
    }
    
    // Test unserialization prevention
    public function test_unserialization_throws_exception() {
        $instance = TestSingletonClass::getInstance();
        $serialized = serialize($instance);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot unserialize singleton instance');
        
        unserialize($serialized);
    }
}