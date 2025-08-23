<?php
/**
 * Test for Utils Class
 * 
 * Following MANDATORY template from CLAUDE.md for QA compliance
 */

namespace WooAiAssistant\Tests\Unit\Common;

use WooAiAssistant\Common\Utils;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class UtilsTest extends TestCase {
    
    // MANDATORY: Test class existence and basic instantiation
    public function test_class_exists() {
        $this->assertTrue(class_exists('WooAiAssistant\Common\Utils'));
    }
    
    // MANDATORY: Verify naming conventions
    public function test_class_follows_naming_conventions() {
        $reflection = new ReflectionClass('WooAiAssistant\Common\Utils');
        
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
        $this->assertTrue(method_exists(Utils::class, 'logDebug'), 'Method logDebug should exist');
        $this->assertTrue(method_exists(Utils::class, 'isWooCommerceActive'), 'Method isWooCommerceActive should exist');
        $this->assertTrue(method_exists(Utils::class, 'formatBytes'), 'Method formatBytes should exist');
        $this->assertTrue(method_exists(Utils::class, 'generateUniqueId'), 'Method generateUniqueId should exist');
        $this->assertTrue(method_exists(Utils::class, 'getPluginVersion'), 'Method getPluginVersion should exist');
        $this->assertTrue(method_exists(Utils::class, 'sanitizeTextField'), 'Method sanitizeTextField should exist');
        
        // Test return types
        $this->assertIsBool(Utils::isWooCommerceActive());
        $this->assertIsString(Utils::formatBytes(1024));
        $this->assertIsString(Utils::generateUniqueId());
        $this->assertIsString(Utils::getPluginVersion());
        $this->assertIsString(Utils::sanitizeTextField('test'));
    }
    
    // Test sanitizeTextField method
    public function test_sanitizeTextField_works_correctly() {
        // Test normal text
        $text = 'Hello World';
        $sanitized = Utils::sanitizeTextField($text);
        $this->assertIsString($sanitized);
        $this->assertEquals($text, $sanitized);
        
        // Test empty text
        $empty = Utils::sanitizeTextField('');
        $this->assertEquals('', $empty);
        
        // Test null value
        $null = Utils::sanitizeTextField(null);
        $this->assertEquals('', $null);
        
        // Test numeric value
        $numeric = Utils::sanitizeTextField(123);
        $this->assertEquals('123', $numeric);
    }
    
    // Test formatBytes method
    public function test_formatBytes_formats_correctly() {
        $this->assertEquals('1 B', Utils::formatBytes(1));
        $this->assertEquals('1024 B', Utils::formatBytes(1024));
        $this->assertEquals('1 KB', Utils::formatBytes(1025));
        $this->assertEquals('1 MB', Utils::formatBytes(1024 * 1024 + 1));
        
        // Test zero
        $this->assertEquals('0 B', Utils::formatBytes(0));
    }
    
    // Test generateUniqueId method
    public function test_generateUniqueId_creates_unique_ids() {
        $id1 = Utils::generateUniqueId();
        $id2 = Utils::generateUniqueId();
        
        $this->assertIsString($id1);
        $this->assertIsString($id2);
        $this->assertNotEquals($id1, $id2, 'Generated IDs should be unique');
        
        // Test with prefix
        $prefixed = Utils::generateUniqueId('test');
        $this->assertStringStartsWith('test', $prefixed);
    }
    
    // Test getMemoryUsage method
    public function test_getMemoryUsage_returns_array_structure() {
        $memoryInfo = Utils::getMemoryUsage();
        
        $this->assertIsArray($memoryInfo);
        $this->assertArrayHasKey('current', $memoryInfo);
        $this->assertArrayHasKey('current_formatted', $memoryInfo);
        $this->assertArrayHasKey('peak', $memoryInfo);
        $this->assertArrayHasKey('peak_formatted', $memoryInfo);
        $this->assertArrayHasKey('limit', $memoryInfo);
        
        // Test that values are correct types
        $this->assertIsInt($memoryInfo['current']);
        $this->assertIsString($memoryInfo['current_formatted']);
        $this->assertIsInt($memoryInfo['peak']);
        $this->assertIsString($memoryInfo['peak_formatted']);
    }
    
    // Test logDebug method (should not throw errors)
    public function test_logDebug_works_without_errors() {
        // Test that debug logging doesn't throw errors
        $this->expectNotToPerformAssertions();
        
        Utils::logDebug('Test debug message');
        Utils::logDebug('Test debug message', 'info');
        Utils::logDebug('Test debug message', 'error');
        Utils::logDebug('Test debug message', 'warning');
    }
}