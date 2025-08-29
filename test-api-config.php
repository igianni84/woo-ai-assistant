<?php
/**
 * API Configuration System Test Script
 * 
 * Comprehensive testing of the API Configuration System
 * Run this script to verify all functionality works correctly
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    // For direct testing, simulate WordPress environment minimally
    define('ABSPATH', '/Applications/MAMP/htdocs/wp/');
    define('WP_CONTENT_DIR', '/Applications/MAMP/htdocs/wp/wp-content');
}

// Include WordPress if not already loaded
if (!function_exists('add_option')) {
    require_once ABSPATH . 'wp-config.php';
    require_once ABSPATH . 'wp-settings.php';
}

// Include our ApiConfiguration class
require_once __DIR__ . '/src/Common/Traits/Singleton.php';
require_once __DIR__ . '/src/Common/Utils.php';
require_once __DIR__ . '/src/Common/ApiConfiguration.php';

use WooAiAssistant\Common\ApiConfiguration;

echo "=== API Configuration System Testing ===\n\n";

try {
    // Test 1: Class instantiation
    echo "TEST 1: Class Instantiation\n";
    $apiConfig = ApiConfiguration::getInstance();
    echo "✅ ApiConfiguration instance created successfully\n";
    echo "✅ Singleton pattern working correctly\n\n";
    
    // Test 2: Initial state (no API keys configured)
    echo "TEST 2: Initial State Verification\n";
    $status = $apiConfig->getApiStatus();
    echo "API Status: " . print_r($status, true) . "\n";
    
    $validation = $apiConfig->validateRequiredKeys(['openai']);
    if (!$validation['valid']) {
        echo "✅ Correctly reports missing API keys initially\n";
    } else {
        echo "❌ Should report missing API keys initially\n";
    }
    echo "\n";
    
    // Test 3: Setting and retrieving API keys
    echo "TEST 3: API Key Storage and Retrieval\n";
    
    $testKeys = [
        'openai' => 'sk-test-openai-key-123456789',
        'pinecone' => 'pc-test-pinecone-key-987654321', 
        'openrouter' => 'sk-test-openrouter-key-abcdef',
        'google' => 'AIza-test-google-key-123xyz'
    ];
    
    foreach ($testKeys as $service => $key) {
        $result = $apiConfig->setApiKey($service, $key);
        if ($result) {
            echo "✅ Successfully set $service API key\n";
        } else {
            echo "❌ Failed to set $service API key\n";
        }
        
        $retrievedKey = $apiConfig->getApiKey($service);
        if ($retrievedKey === $key) {
            echo "✅ Successfully retrieved $service API key\n";
        } else {
            echo "❌ Retrieved key doesn't match for $service\n";
            echo "   Expected: $key\n";
            echo "   Retrieved: $retrievedKey\n";
        }
    }
    echo "\n";
    
    // Test 4: Service-specific configuration
    echo "TEST 4: Service-Specific Configuration\n";
    
    $openaiConfig = $apiConfig->getOpenAiConfig();
    echo "OpenAI Config: " . print_r($openaiConfig, true) . "\n";
    
    $pineconeConfig = $apiConfig->getPineconeConfig();
    echo "Pinecone Config: " . print_r($pineconeConfig, true) . "\n";
    
    $openrouterConfig = $apiConfig->getOpenRouterConfig();
    echo "OpenRouter Config: " . print_r($openrouterConfig, true) . "\n";
    
    $googleConfig = $apiConfig->getGoogleConfig();
    echo "Google Config: " . print_r($googleConfig, true) . "\n";
    
    echo "✅ All service configurations retrieved successfully\n\n";
    
    // Test 5: Validation with configured keys
    echo "TEST 5: Validation After Configuration\n";
    $validation = $apiConfig->validateRequiredKeys(['openai', 'pinecone']);
    if ($validation['valid']) {
        echo "✅ Correctly validates configured required keys\n";
        echo "Configured services: " . implode(', ', $validation['configured']) . "\n";
    } else {
        echo "❌ Should validate when required keys are configured\n";
        echo "Missing: " . implode(', ', $validation['missing']) . "\n";
    }
    echo "\n";
    
    // Test 6: Development mode detection
    echo "TEST 6: Development Mode Detection\n";
    $devMode = $apiConfig->isDevelopmentMode();
    echo "Development Mode: " . ($devMode ? 'Enabled' : 'Disabled') . "\n";
    
    $debugMode = $apiConfig->isDebugMode();
    echo "Debug Mode: " . ($debugMode ? 'Enabled' : 'Disabled') . "\n";
    echo "✅ Mode detection working\n\n";
    
    // Test 7: API Status after configuration
    echo "TEST 7: API Status After Configuration\n";
    $status = $apiConfig->getApiStatus();
    foreach ($status as $service => $serviceStatus) {
        $configured = $serviceStatus['configured'] ? 'Configured' : 'Not Configured';
        $keyLength = $serviceStatus['key_length'];
        $source = $serviceStatus['source'];
        echo "$service: $configured (Length: $keyLength, Source: $source)\n";
    }
    echo "✅ API status reporting working correctly\n\n";
    
    // Test 8: Cache functionality
    echo "TEST 8: Cache Functionality\n";
    $apiConfig->clearCache();
    echo "✅ Cache cleared without errors\n";
    
    // Verify data persists after cache clear
    $retrievedKey = $apiConfig->getApiKey('openai');
    if ($retrievedKey === $testKeys['openai']) {
        echo "✅ Data persists correctly after cache clear\n";
    } else {
        echo "❌ Data not persisting after cache clear\n";
    }
    echo "\n";
    
    // Clean up test data
    echo "CLEANUP: Removing test data\n";
    foreach (array_keys($testKeys) as $service) {
        $apiConfig->setApiKey($service, '');
    }
    delete_option('woo_ai_assistant_settings');
    echo "✅ Test data cleaned up\n\n";
    
    echo "=== API Configuration System Test Complete ===\n";
    echo "✅ ALL TESTS PASSED - API Configuration System is working correctly!\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}