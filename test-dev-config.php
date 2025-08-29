<?php
/**
 * Development Configuration Test Script
 *
 * This script helps verify that the development configuration system
 * is working correctly and API keys are properly loaded.
 *
 * Usage: Run this script from the plugin root directory
 * php test-dev-config.php
 *
 * @package WooAiAssistant
 * @since 1.0.0
 */

// Prevent direct web access
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line.');
}

// Set up basic WordPress constants for testing
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__, 3) . '/');
}

if (!defined('WOO_AI_ASSISTANT_PLUGIN_DIR_PATH')) {
    define('WOO_AI_ASSISTANT_PLUGIN_DIR_PATH', __DIR__ . '/');
}

// Include required files
require_once 'src/Common/Traits/Singleton.php';
require_once 'src/Common/Utils.php';
require_once 'src/Common/DevelopmentConfig.php';
require_once 'src/Common/ApiConfiguration.php';

echo "=== Woo AI Assistant - Development Configuration Test ===\n\n";

try {
    // Test DevelopmentConfig
    echo "1. Testing DevelopmentConfig...\n";
    $devConfig = \WooAiAssistant\Common\DevelopmentConfig::getInstance();
    
    echo "   ✓ DevelopmentConfig instantiated successfully\n";
    echo "   - Development mode: " . ($devConfig->isDevelopmentMode() ? "✓ ENABLED" : "✗ DISABLED") . "\n";
    echo "   - License bypass: " . ($devConfig->shouldBypassLicenseValidation() ? "✓ ENABLED" : "✗ DISABLED") . "\n";
    echo "   - Dummy data: " . ($devConfig->shouldUseDummyData() ? "✓ ENABLED" : "✗ DISABLED") . "\n";
    echo "   - Enhanced debug: " . ($devConfig->isEnhancedDebugEnabled() ? "✓ ENABLED" : "✗ DISABLED") . "\n";
    
    // Test API key loading
    echo "\n2. Testing API key loading...\n";
    $services = ['openrouter', 'openai', 'pinecone', 'google'];
    
    foreach ($services as $service) {
        $apiKey = $devConfig->getApiKey($service);
        $status = !empty($apiKey) ? "✓ LOADED (" . strlen($apiKey) . " chars)" : "✗ NOT FOUND";
        echo "   - {$service}: {$status}\n";
    }
    
    // Test ApiConfiguration integration
    echo "\n3. Testing ApiConfiguration integration...\n";
    $apiConfig = \WooAiAssistant\Common\ApiConfiguration::getInstance();
    echo "   ✓ ApiConfiguration instantiated successfully\n";
    echo "   - Development mode detected: " . ($apiConfig->isDevelopmentMode() ? "✓ YES" : "✗ NO") . "\n";
    echo "   - License bypass available: " . ($apiConfig->shouldBypassLicenseValidation() ? "✓ YES" : "✗ NO") . "\n";
    
    // Test configuration retrieval
    echo "\n4. Testing configuration retrieval...\n";
    $configs = [
        'Pinecone' => $apiConfig->getPineconeConfig(),
        'OpenAI' => $apiConfig->getOpenAiConfig(),
        'OpenRouter' => $apiConfig->getOpenRouterConfig(),
        'Google' => $apiConfig->getGoogleConfig(),
    ];
    
    foreach ($configs as $name => $config) {
        $hasApiKey = !empty($config['api_key']);
        echo "   - {$name} config: " . ($hasApiKey ? "✓ API key loaded" : "✗ No API key") . "\n";
    }
    
    // Development configuration summary
    echo "\n5. Development Configuration Summary:\n";
    $debugConfig = $devConfig->exportConfigForDebug();
    
    echo "   - API Keys Status:\n";
    foreach ($debugConfig['api_keys'] as $service => $status) {
        echo "     * {$service}: {$status}\n";
    }
    
    echo "   - Feature Flags:\n";
    foreach ($debugConfig['features'] as $feature => $enabled) {
        $status = $enabled ? "✓ enabled" : "✗ disabled";
        echo "     * {$feature}: {$status}\n";
    }
    
    echo "   - Limits:\n";
    foreach ($debugConfig['limits'] as $limit => $value) {
        echo "     * {$limit}: {$value}\n";
    }
    
    // Test environment file detection
    echo "\n6. Environment File Detection:\n";
    $envFile = __DIR__ . '/.env.development';
    if (file_exists($envFile)) {
        echo "   ✓ .env.development file found\n";
        echo "   - File size: " . filesize($envFile) . " bytes\n";
        echo "   - Readable: " . (is_readable($envFile) ? "✓ YES" : "✗ NO") . "\n";
    } else {
        echo "   ✗ .env.development file not found\n";
    }
    
    $localEnvFile = __DIR__ . '/.env.development.local';
    if (file_exists($localEnvFile)) {
        echo "   ✓ .env.development.local file found\n";
        echo "   - File size: " . filesize($localEnvFile) . " bytes\n";
        echo "   - Readable: " . (is_readable($localEnvFile) ? "✓ YES" : "✗ NO") . "\n";
    } else {
        echo "   ✗ .env.development.local file not found (this is normal if you haven't created it yet)\n";
    }
    
    echo "\n=== Test Results ===\n";
    
    if ($devConfig->isDevelopmentMode()) {
        echo "✓ SUCCESS: Development mode is active and working correctly!\n\n";
        
        echo "Next steps:\n";
        echo "1. Copy .env.development to .env.development.local\n";
        echo "2. Add your actual API keys to .env.development.local\n";
        echo "3. The plugin will automatically use your development configuration\n";
        echo "4. License validation will be bypassed in development mode\n";
    } else {
        echo "⚠ WARNING: Development mode is not active.\n\n";
        
        echo "Possible reasons:\n";
        echo "1. You're not running on localhost/development environment\n";
        echo "2. WP_DEBUG is not enabled\n";
        echo "3. WOO_AI_DEVELOPMENT_MODE is not set to 'true'\n";
        echo "\nTo enable development mode:\n";
        echo "- Set WOO_AI_DEVELOPMENT_MODE=true in your .env.development file\n";
        echo "- Or enable WP_DEBUG in your wp-config.php\n";
    }

} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n=== Test Complete ===\n";