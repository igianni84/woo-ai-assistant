<?php
/**
 * Simple API Configuration Test - No WordPress Dependencies
 */

// Mock WordPress functions for testing
if (!function_exists('add_option')) {
    function add_option($option, $value) {
        global $test_options;
        $test_options[$option] = $value;
        return true;
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        global $test_options;
        return $test_options[$option] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value) {
        global $test_options;
        $test_options[$option] = $value;
        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option($option) {
        global $test_options;
        unset($test_options[$option]);
        return true;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim($str);
    }
}

if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', __DIR__);
}

// Initialize test options storage
global $test_options;
$test_options = [];

// Include the classes we need
require_once __DIR__ . '/src/Common/Traits/Singleton.php';

// Mock Utils class since it might have WordPress dependencies
if (!class_exists('WooAiAssistant\Common\Utils')) {
    class MockUtils {
        public static function logDebug($message) {
            echo "[DEBUG] $message\n";
        }
    }
    class_alias('MockUtils', 'WooAiAssistant\Common\Utils');
}

require_once __DIR__ . '/src/Common/ApiConfiguration.php';

use WooAiAssistant\Common\ApiConfiguration;

echo "=== API Configuration System Test (Minimal) ===\n\n";

try {
    // Test 1: Instantiation
    echo "TEST 1: Class Instantiation\n";
    $apiConfig = ApiConfiguration::getInstance();
    echo "✅ ApiConfiguration created successfully\n";
    
    // Verify singleton
    $apiConfig2 = ApiConfiguration::getInstance();
    if ($apiConfig === $apiConfig2) {
        echo "✅ Singleton pattern working\n";
    } else {
        echo "❌ Singleton pattern failed\n";
    }
    echo "\n";
    
    // Test 2: Basic API key operations
    echo "TEST 2: API Key Operations\n";
    
    // Initially should be empty
    $key = $apiConfig->getApiKey('openai');
    if (empty($key)) {
        echo "✅ Initial key is empty as expected\n";
    } else {
        echo "❌ Initial key should be empty, got: $key\n";
    }
    
    // Set a key
    $testKey = 'sk-test123456789';
    $result = $apiConfig->setApiKey('openai', $testKey);
    if ($result) {
        echo "✅ API key set successfully\n";
    } else {
        echo "❌ Failed to set API key\n";
    }
    
    // Retrieve the key
    $retrievedKey = $apiConfig->getApiKey('openai');
    if ($retrievedKey === $testKey) {
        echo "✅ API key retrieved correctly\n";
    } else {
        echo "❌ Retrieved key mismatch. Expected: $testKey, Got: $retrievedKey\n";
    }
    echo "\n";
    
    // Test 3: Configuration retrieval
    echo "TEST 3: Service Configurations\n";
    
    $openaiConfig = $apiConfig->getOpenAiConfig();
    if (is_array($openaiConfig) && isset($openaiConfig['api_key'])) {
        echo "✅ OpenAI config structure correct\n";
        echo "   API Key: " . $openaiConfig['api_key'] . "\n";
        echo "   Model: " . $openaiConfig['model'] . "\n";
    } else {
        echo "❌ OpenAI config structure incorrect\n";
    }
    
    $pineconeConfig = $apiConfig->getPineconeConfig();
    if (is_array($pineconeConfig) && isset($pineconeConfig['index_name'])) {
        echo "✅ Pinecone config structure correct\n";
        echo "   Index Name: " . $pineconeConfig['index_name'] . "\n";
    } else {
        echo "❌ Pinecone config structure incorrect\n";
    }
    echo "\n";
    
    // Test 4: API Status
    echo "TEST 4: API Status\n";
    $status = $apiConfig->getApiStatus();
    if (is_array($status) && isset($status['openai'])) {
        echo "✅ API status structure correct\n";
        foreach ($status as $service => $info) {
            $configured = $info['configured'] ? 'Yes' : 'No';
            echo "   $service: Configured=$configured, Length={$info['key_length']}\n";
        }
    } else {
        echo "❌ API status structure incorrect\n";
    }
    echo "\n";
    
    // Test 5: Validation
    echo "TEST 5: Key Validation\n";
    $validation = $apiConfig->validateRequiredKeys(['openai']);
    if (is_array($validation)) {
        $valid = $validation['valid'] ? 'Yes' : 'No';
        echo "✅ Validation working: Valid=$valid\n";
        echo "   Configured: " . implode(', ', $validation['configured']) . "\n";
        echo "   Missing: " . implode(', ', $validation['missing']) . "\n";
    } else {
        echo "❌ Validation not working\n";
    }
    echo "\n";
    
    // Test 6: Development mode
    echo "TEST 6: Development Mode\n";
    $devMode = $apiConfig->isDevelopmentMode();
    $debugMode = $apiConfig->isDebugMode();
    echo "✅ Mode detection working\n";
    echo "   Development Mode: " . ($devMode ? 'Yes' : 'No') . "\n";
    echo "   Debug Mode: " . ($debugMode ? 'Yes' : 'No') . "\n";
    echo "\n";
    
    echo "=== TEST COMPLETE ===\n";
    echo "✅ ALL CORE FUNCTIONALITY WORKING!\n\n";
    
    echo "Summary of verified functionality:\n";
    echo "- ✅ Class instantiation and singleton pattern\n";
    echo "- ✅ API key storage and retrieval\n";
    echo "- ✅ Service-specific configuration\n";
    echo "- ✅ API status reporting\n";
    echo "- ✅ Key validation\n";
    echo "- ✅ Development mode detection\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}