<?php
/**
 * Simple Bootstrap for Basic PHPUnit Tests
 *
 * Simplified bootstrap that doesn't require WordPress test suite.
 * Useful for testing basic functionality without full WordPress integration.
 *
 * @package WooAiAssistant
 * @subpackage Tests
 * @since 1.0.0
 * @author Claude Code Assistant
 */

// Define testing mode early
if (!defined('WOO_AI_ASSISTANT_TESTING')) {
    define('WOO_AI_ASSISTANT_TESTING', true);
}

// Set memory limit
ini_set('memory_limit', '256M');

// Error reporting
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('UTC');

// Define plugin constants for testing
if (!defined('WOO_AI_ASSISTANT_PLUGIN_DIR')) {
    define('WOO_AI_ASSISTANT_PLUGIN_DIR', dirname(__DIR__));
}

if (!defined('WOO_AI_ASSISTANT_PLUGIN_FILE')) {
    define('WOO_AI_ASSISTANT_PLUGIN_FILE', WOO_AI_ASSISTANT_PLUGIN_DIR . '/woo-ai-assistant.php');
}

if (!defined('WOO_AI_ASSISTANT_BASENAME')) {
    define('WOO_AI_ASSISTANT_BASENAME', 'woo-ai-assistant/woo-ai-assistant.php');
}

// Load Composer autoloader if available
$autoloader = WOO_AI_ASSISTANT_PLUGIN_DIR . '/vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
}

// Create necessary test directories
$testDirs = [
    WOO_AI_ASSISTANT_PLUGIN_DIR . '/tests/logs',
    WOO_AI_ASSISTANT_PLUGIN_DIR . '/tests/tmp',
    WOO_AI_ASSISTANT_PLUGIN_DIR . '/coverage',
    WOO_AI_ASSISTANT_PLUGIN_DIR . '/coverage/html-simple'
];

foreach ($testDirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

echo "=== Simple Test Bootstrap Loaded ===\n";
echo "Plugin Directory: " . WOO_AI_ASSISTANT_PLUGIN_DIR . "\n";
echo "Testing Mode: " . (WOO_AI_ASSISTANT_TESTING ? 'Active' : 'Inactive') . "\n";
echo "===================================\n\n";