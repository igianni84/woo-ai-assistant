<?php
/**
 * Test Configuration for MAMP Environment
 *
 * Configuration specific for macOS with MAMP setup
 * 
 * @package WooAiAssistant\Tests
 * @since 1.0.0
 */

// MAMP Database Configuration for Tests
define('TEST_DB_NAME', 'woo_ai_test');
define('TEST_DB_USER', 'root');
define('TEST_DB_PASSWORD', 'root');
define('TEST_DB_HOST', '127.0.0.1:8889'); // MAMP MySQL port
define('TEST_DB_CHARSET', 'utf8mb4');
define('TEST_DB_COLLATE', '');

// WordPress Test Installation Path
// Using the actual WordPress installation for test framework
define('WP_TESTS_DIR', '/Applications/MAMP/htdocs/wp/');
define('WP_CORE_DIR', '/Applications/MAMP/htdocs/wp/');

// Test Site Configuration
if (!defined('WP_TESTS_DOMAIN')) {
    define('WP_TESTS_DOMAIN', 'localhost:8888');
}
if (!defined('WP_TESTS_EMAIL')) {
    define('WP_TESTS_EMAIL', 'test@woo-ai-assistant.local');
}
if (!defined('WP_TESTS_TITLE')) {
    define('WP_TESTS_TITLE', 'Woo AI Assistant Test Suite');
}
if (!defined('WP_TESTS_NETWORK_TITLE')) {
    define('WP_TESTS_NETWORK_TITLE', 'Woo AI Assistant Test Network');
}

// MAMP PHP Binary Path (for M1 Macs)
if (!defined('WP_PHP_BINARY')) {
    define('WP_PHP_BINARY', '/Applications/MAMP/bin/php/php8.2.20/bin/php');
}

// Enable Debug Mode for Tests
if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}
if (!defined('WP_DEBUG_LOG')) {
    define('WP_DEBUG_LOG', true);
}
if (!defined('WP_DEBUG_DISPLAY')) {
    define('WP_DEBUG_DISPLAY', false);
}

// Memory Limits for Tests
define('WP_MEMORY_LIMIT', '256M');
define('WP_MAX_MEMORY_LIMIT', '512M');

// Plugin Test Constants
define('WOO_AI_ASSISTANT_TEST_MODE', true);
define('WOO_AI_ASSISTANT_USE_DUMMY_DATA', true);

// Disable External HTTP Requests During Tests
define('WP_HTTP_BLOCK_EXTERNAL', true);
define('WP_ACCESSIBLE_HOSTS', 'api.wordpress.org,downloads.wordpress.org');

// Table Prefix for Test Database
$table_prefix = 'wptests_';