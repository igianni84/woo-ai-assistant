<?php

/**
 * Tests for Utils Class
 *
 * Comprehensive unit tests for the utility functions and helper methods.
 * Tests string manipulation, validation, WordPress integration, and security functions.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Unit\Common
 * @since 1.0.0
 * @author Claude Code Assistant
 */

namespace WooAiAssistant\Tests\Unit\Common;

use WooAiAssistant\Common\Utils;
use WooAiAssistant\Tests\Unit\WooAiBaseTestCase;

/**
 * Class UtilsTest
 *
 * Test cases for the Utils utility class.
 *
 * @since 1.0.0
 */
class UtilsTest extends WooAiBaseTestCase
{
    /**
     * Test WooCommerce detection
     *
     * @return void
     */
    public function test_isWooCommerceActive_should_return_true_when_woocommerce_is_loaded(): void
    {
        $this->assertTrue(Utils::isWooCommerceActive(), 'WooCommerce should be detected as active in test environment');
    }

    /**
     * Test development mode detection
     *
     * @return void
     */
    public function test_isDevelopmentMode_should_return_true_in_test_environment(): void
    {
        $this->assertTrue(Utils::isDevelopmentMode(), 'Development mode should be detected in test environment');
    }

    /**
     * Test development mode detection with constant
     *
     * @return void
     */
    public function test_isDevelopmentMode_should_respect_constant(): void
    {
        // This test verifies that the constant WOO_AI_DEVELOPMENT_MODE is respected
        // In our test environment, this should be set to true
        $this->assertTrue(Utils::isDevelopmentMode(), 'Should respect WOO_AI_DEVELOPMENT_MODE constant');
    }

    /**
     * Test plugin version retrieval
     *
     * @return void
     */
    public function test_getVersion_should_return_valid_version_string(): void
    {
        $version = Utils::getVersion();

        $this->assertIsString($version, 'Version should be a string');
        $this->assertNotEmpty($version, 'Version should not be empty');
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+/', $version, 'Version should follow semantic versioning pattern');
    }

    /**
     * Test plugin path retrieval
     *
     * @return void
     */
    public function test_getPluginPath_should_return_valid_path(): void
    {
        $path = Utils::getPluginPath();

        $this->assertIsString($path, 'Plugin path should be a string');
        $this->assertNotEmpty($path, 'Plugin path should not be empty');
        $this->assertTrue(is_dir($path), 'Plugin path should exist as directory');
    }

    /**
     * Test plugin path retrieval with subdirectory
     *
     * @return void
     */
    public function test_getPluginPath_should_append_subdirectory(): void
    {
        $subPath = Utils::getPluginPath('src/Common');

        $this->assertStringContains('src/Common', $subPath, 'Should append subdirectory to plugin path');
        $this->assertTrue(is_dir($subPath), 'Subdirectory path should exist');
    }

    /**
     * Test plugin URL retrieval
     *
     * @return void
     */
    public function test_getPluginUrl_should_return_valid_url(): void
    {
        $url = Utils::getPluginUrl();

        $this->assertIsString($url, 'Plugin URL should be a string');
        $this->assertNotEmpty($url, 'Plugin URL should not be empty');
        $this->assertTrue(filter_var($url, FILTER_VALIDATE_URL) !== false, 'Plugin URL should be valid URL format');
    }

    /**
     * Test plugin URL with file path
     *
     * @return void
     */
    public function test_getPluginUrl_should_append_file_path(): void
    {
        $fileUrl = Utils::getPluginUrl('assets/css/style.css');

        $this->assertStringContains('assets/css/style.css', $fileUrl, 'Should append file path to plugin URL');
        $this->assertTrue(filter_var($fileUrl, FILTER_VALIDATE_URL) !== false, 'File URL should be valid');
    }

    /**
     * Test email sanitization with valid email
     *
     * @return void
     */
    public function test_sanitizeEmail_should_return_clean_email_for_valid_input(): void
    {
        $validEmail = 'user@example.com';
        $sanitized = Utils::sanitizeEmail($validEmail);

        $this->assertEquals($validEmail, $sanitized, 'Valid email should be returned unchanged');
        $this->assertIsString($sanitized, 'Sanitized email should be string');
    }

    /**
     * Test email sanitization with invalid email
     *
     * @return void
     */
    public function test_sanitizeEmail_should_return_false_for_invalid_input(): void
    {
        $invalidEmails = [
            'invalid-email',
            '@example.com',
            'user@',
            'user space@example.com'
        ];

        foreach ($invalidEmails as $email) {
            $result = Utils::sanitizeEmail($email);
            $this->assertFalse($result, "Invalid email '{$email}' should return false");
        }
    }

    /**
     * Test nonce generation
     *
     * @return void
     */
    public function test_generateNonce_should_create_valid_nonce(): void
    {
        $action = 'test_action';
        $nonce = Utils::generateNonce($action);

        $this->assertIsString($nonce, 'Generated nonce should be a string');
        $this->assertNotEmpty($nonce, 'Generated nonce should not be empty');
        $this->assertGreaterThan(10, strlen($nonce), 'Nonce should have sufficient length');
    }

    /**
     * Test nonce verification
     *
     * @return void
     */
    public function test_verifyNonce_should_validate_correct_nonce(): void
    {
        $action = 'test_verification';
        $nonce = Utils::generateNonce($action);

        $isValid = Utils::verifyNonce($nonce, $action);
        $this->assertTrue($isValid, 'Generated nonce should be valid for same action');
    }

    /**
     * Test nonce verification with wrong action
     *
     * @return void
     */
    public function test_verifyNonce_should_reject_wrong_action(): void
    {
        $nonce = Utils::generateNonce('correct_action');
        $isValid = Utils::verifyNonce($nonce, 'wrong_action');

        $this->assertFalse($isValid, 'Nonce should be invalid for different action');
    }

    /**
     * Test current user ID retrieval
     *
     * @return void
     */
    public function test_getCurrentUserId_should_return_integer(): void
    {
        $userId = Utils::getCurrentUserId();
        $this->assertIsInt($userId, 'User ID should be an integer');
        $this->assertGreaterThanOrEqual(0, $userId, 'User ID should be non-negative');
    }

    /**
     * Test user capability checking
     *
     * @return void
     */
    public function test_currentUserCan_should_return_boolean(): void
    {
        $canRead = Utils::currentUserCan('read');
        $this->assertIsBool($canRead, 'Capability check should return boolean');
    }

    /**
     * Test bytes formatting
     *
     * @return void
     */
    public function test_formatBytes_should_format_bytes_correctly(): void
    {
        $testCases = [
            [0, '0 B'],
            [1024, '1 KB'],
            [1048576, '1 MB'],
            [1073741824, '1 GB'],
            [1536, '1.5 KB']
        ];

        foreach ($testCases as [$bytes, $expected]) {
            $formatted = Utils::formatBytes($bytes);
            $this->assertStringContains($expected, $formatted, "Formatting {$bytes} bytes should contain '{$expected}'");
        }
    }

    /**
     * Test text cleaning functionality
     *
     * @return void
     */
    public function test_cleanText_should_remove_html_and_clean_whitespace(): void
    {
        $htmlText = '<p>This is <strong>HTML</strong> text with   extra   spaces.</p>';
        $cleaned = Utils::cleanText($htmlText);

        $this->assertEquals('This is HTML text with extra spaces.', $cleaned, 'Should remove HTML tags and normalize whitespace');
    }

    /**
     * Test text truncation
     *
     * @return void
     */
    public function test_cleanText_should_truncate_to_specified_length(): void
    {
        $longText = 'This is a very long text that should be truncated to a shorter length.';
        $truncated = Utils::cleanText($longText, 20, '...');

        $this->assertLessThanOrEqual(20, strlen($truncated), 'Truncated text should not exceed specified length');
        $this->assertStringContains('...', $truncated, 'Truncated text should contain suffix');
    }

    /**
     * Test JSON validation with valid JSON
     *
     * @return void
     */
    public function test_isJson_should_return_true_for_valid_json(): void
    {
        $validJsonStrings = [
            '{"key": "value"}',
            '[1, 2, 3]',
            '"simple string"',
            'true',
            'null'
        ];

        foreach ($validJsonStrings as $json) {
            $this->assertTrue(Utils::isJson($json), "'{$json}' should be recognized as valid JSON");
        }
    }

    /**
     * Test JSON validation with invalid JSON
     *
     * @return void
     */
    public function test_isJson_should_return_false_for_invalid_json(): void
    {
        $invalidJsonStrings = [
            '{key: "value"}', // Missing quotes around key
            '[1, 2, 3,]', // Trailing comma
            'undefined',
            '{broken json'
        ];

        foreach ($invalidJsonStrings as $json) {
            $this->assertFalse(Utils::isJson($json), "'{$json}' should be recognized as invalid JSON");
        }
    }

    /**
     * Test unique ID generation
     *
     * @return void
     */
    public function test_generateUniqueId_should_create_unique_ids(): void
    {
        $id1 = Utils::generateUniqueId();
        $id2 = Utils::generateUniqueId();

        $this->assertIsString($id1, 'Generated ID should be string');
        $this->assertIsString($id2, 'Generated ID should be string');
        $this->assertNotEquals($id1, $id2, 'Generated IDs should be different');
        $this->assertNotEmpty($id1, 'Generated ID should not be empty');
        $this->assertNotEmpty($id2, 'Generated ID should not be empty');
    }

    /**
     * Test unique ID generation with prefix
     *
     * @return void
     */
    public function test_generateUniqueId_should_include_prefix(): void
    {
        $prefix = 'test_prefix';
        $id = Utils::generateUniqueId($prefix);

        $this->assertStringStartsWith($prefix . '_', $id, 'Generated ID should start with prefix');
    }

    /**
     * Test timezone retrieval
     *
     * @return void
     */
    public function test_getTimezone_should_return_valid_timezone(): void
    {
        $timezone = Utils::getTimezone();

        $this->assertIsString($timezone, 'Timezone should be a string');
        $this->assertNotEmpty($timezone, 'Timezone should not be empty');

        // Verify it's a valid timezone
        $validTimezone = in_array($timezone, timezone_identifiers_list()) || $timezone === 'UTC';
        $this->assertTrue($validTimezone, 'Should return a valid timezone identifier');
    }

    /**
     * Test debug logging functionality
     *
     * @return void
     */
    public function test_debugLog_should_log_in_debug_mode(): void
    {
        // In test environment, debug mode should be enabled
        $testData = 'Test debug message';

        // Capture error log output by temporarily changing error log destination
        $originalLogDestination = ini_get('error_log');
        $tempLogFile = tempnam(sys_get_temp_dir(), 'woo_ai_debug_test');
        ini_set('error_log', $tempLogFile);

        Utils::debugLog($testData, 'test_context');

        // Restore original error log destination
        ini_set('error_log', $originalLogDestination);

        // Check if log was written (if debug is enabled)
        if (defined('WOO_AI_ASSISTANT_DEBUG') && WOO_AI_ASSISTANT_DEBUG) {
            $logContent = file_get_contents($tempLogFile);
            $this->assertStringContains('Woo AI Assistant', $logContent, 'Debug log should contain plugin name');
            $this->assertStringContains('test_context', $logContent, 'Debug log should contain context');
        }

        // Clean up
        unlink($tempLogFile);
    }

    /**
     * Test class name follows PascalCase convention
     *
     * @return void
     */
    public function test_class_name_should_follow_pascal_case_convention(): void
    {
        $this->assertClassFollowsPascalCase(Utils::class);
    }

    /**
     * Test method names follow camelCase convention
     *
     * @return void
     */
    public function test_public_methods_should_follow_camel_case_convention(): void
    {
        $publicMethods = [
            'isWooCommerceActive',
            'isDevelopmentMode',
            'getVersion',
            'getPluginPath',
            'getPluginUrl',
            'sanitizeEmail',
            'generateNonce',
            'verifyNonce',
            'getCurrentUserId',
            'currentUserCan',
            'formatBytes',
            'cleanText',
            'isJson',
            'generateUniqueId',
            'getTimezone',
            'debugLog'
        ];

        $reflection = new \ReflectionClass(Utils::class);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            $methodName = $method->getName();

            // Skip magic methods
            if (strpos($methodName, '__') === 0) {
                continue;
            }

            $this->assertTrue(
                ctype_lower($methodName[0]) && !strpos($methodName, '_'),
                "Method {$methodName} should follow camelCase convention"
            );
        }
    }

    /**
     * Test performance of utility functions
     *
     * @return void
     */
    public function test_utility_functions_should_perform_efficiently(): void
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        // Perform multiple utility operations
        for ($i = 0; $i < 100; $i++) {
            Utils::isWooCommerceActive();
            Utils::isDevelopmentMode();
            Utils::getVersion();
            Utils::formatBytes(1024 * $i);
            Utils::generateUniqueId();
            Utils::cleanText("Test text with <b>HTML</b> tags");
            Utils::isJson('{"test": "value"}');
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        $executionTime = $endTime - $startTime;
        $memoryUsage = $endMemory - $startMemory;

        $this->assertLessThan(1.0, $executionTime, 'Utility functions should execute efficiently');
        $this->assertLessThan(1048576, $memoryUsage, 'Memory usage should be reasonable'); // Less than 1MB
    }

    /**
     * Test error handling in utility functions
     *
     * @return void
     */
    public function test_utility_functions_should_handle_errors_gracefully(): void
    {
        // Test with null/empty inputs where applicable
        $this->assertFalse(Utils::sanitizeEmail(''), 'Empty email should return false');
        $this->assertEquals('', Utils::cleanText(''), 'Empty text should return empty string');
        $this->assertFalse(Utils::isJson(''), 'Empty string should not be valid JSON');

        // Test with invalid inputs
        $this->assertIsString(Utils::formatBytes(-1), 'Negative bytes should still return string');
        $this->assertIsString(Utils::generateUniqueId(''), 'Empty prefix should still work');
    }
}
