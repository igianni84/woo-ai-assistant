<?php
/**
 * PHPUnit Bootstrap for Woo AI Assistant Plugin
 *
 * This bootstrap file sets up the WordPress testing environment and initializes
 * the plugin for testing. It handles database setup, WordPress core loading,
 * and plugin activation in the test environment.
 *
 * @package WooAiAssistant
 * @subpackage Tests
 * @since 1.0.0
 * @author Claude Code Assistant
 */

// Define testing mode early (only if not already defined)
if (!defined('WOO_AI_ASSISTANT_TESTING')) {
    define('WOO_AI_ASSISTANT_TESTING', true);
}

// Exit if accessed directly (but allow CLI for PHPUnit)
if (!defined('ABSPATH') && php_sapi_name() !== 'cli') {
    exit;
}

// Set memory limit for tests
ini_set('memory_limit', '512M');

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

// Define basename manually to avoid dependency on WordPress functions at this stage
if (!defined('WOO_AI_ASSISTANT_BASENAME')) {
    $pluginRelativePath = str_replace(dirname(dirname(WOO_AI_ASSISTANT_PLUGIN_DIR)), '', WOO_AI_ASSISTANT_PLUGIN_FILE);
    define('WOO_AI_ASSISTANT_BASENAME', ltrim($pluginRelativePath, '/\\'));
}

// WordPress test configuration
$_tests_dir = getenv('WP_TESTS_DIR');

// Try different locations for WordPress tests
if (!$_tests_dir) {
    $possible_paths = [
        // Common locations
        '/tmp/wordpress-tests-lib',
        dirname(__FILE__) . '/../../../../tests/phpunit',
        dirname(__FILE__) . '/../../../../../../tests/phpunit',
        // MAMP specific paths
        '/Applications/MAMP/htdocs/wordpress-tests-lib',
        // Manual installation paths
        dirname(__FILE__) . '/wordpress-tests-lib',
    ];
    
    foreach ($possible_paths as $path) {
        if (is_dir($path)) {
            $_tests_dir = $path;
            break;
        }
    }
}

if (!$_tests_dir) {
    echo "WordPress tests directory not found. Please install wordpress-tests-lib or set WP_TESTS_DIR environment variable.\n";
    echo "Installation instructions:\n";
    echo "1. cd /tmp\n";
    echo "2. svn co https://develop.svn.wordpress.org/tags/6.3/tests/phpunit/ wordpress-tests-lib\n";
    echo "3. cd wordpress-tests-lib\n";
    echo "4. cp wp-tests-config-sample.php wp-tests-config.php\n";
    echo "5. Edit wp-tests-config.php with your test database settings\n";
    exit(1);
}

// Check if WordPress test functions are available
if (!file_exists($_tests_dir . '/includes/functions.php')) {
    echo "Could not find WordPress test functions at: {$_tests_dir}/includes/functions.php\n";
    exit(1);
}

// Load WordPress test functions
require_once $_tests_dir . '/includes/functions.php';

/**
 * Load the plugin and WooCommerce for testing
 */
function _manually_load_plugin_and_dependencies() {
    // Load WooCommerce first (required for our plugin)
    if (!function_exists('is_plugin_active')) {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    // Define WooCommerce path (adjust based on your MAMP setup)
    $woocommerce_path = WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
    
    if (file_exists($woocommerce_path)) {
        require_once $woocommerce_path;
        echo "WooCommerce loaded for testing\n";
    } else {
        echo "Warning: WooCommerce not found at {$woocommerce_path}\n";
        echo "Some tests may fail without WooCommerce active\n";
    }

    // Load Composer autoloader
    $autoloader = WOO_AI_ASSISTANT_PLUGIN_DIR . '/vendor/autoload.php';
    if (file_exists($autoloader)) {
        require_once $autoloader;
        echo "Composer autoloader loaded\n";
    } else {
        echo "Warning: Composer autoloader not found. Run 'composer install' first.\n";
    }

    // Load the plugin
    require_once WOO_AI_ASSISTANT_PLUGIN_FILE;
    echo "Woo AI Assistant plugin loaded for testing\n";
}

// Hook plugin loading
tests_add_filter('muplugins_loaded', '_manually_load_plugin_and_dependencies');

/**
 * Setup test database and activate plugins
 */
function _setup_test_environment() {
    // Activate WooCommerce
    activate_plugin('woocommerce/woocommerce.php');
    
    // Activate our plugin
    activate_plugin(WOO_AI_ASSISTANT_BASENAME);
    
    echo "Plugins activated in test environment\n";
}

// Hook environment setup
tests_add_filter('wp_loaded', '_setup_test_environment');

/**
 * Create test data after setup
 */
function _create_test_data() {
    // Create test product categories
    if (function_exists('wp_insert_term')) {
        wp_insert_term('Electronics', 'product_cat', [
            'description' => 'Electronic products for testing',
            'slug' => 'electronics'
        ]);
        
        wp_insert_term('Clothing', 'product_cat', [
            'description' => 'Clothing products for testing', 
            'slug' => 'clothing'
        ]);
    }
    
    // Set WooCommerce currency for testing
    if (function_exists('update_option')) {
        update_option('woocommerce_currency', 'USD');
        update_option('woocommerce_price_decimal_sep', '.');
        update_option('woocommerce_price_thousand_sep', ',');
        update_option('woocommerce_price_num_decimals', 2);
    }
    
    echo "Test data created\n";
}

// Hook test data creation
tests_add_filter('init', '_create_test_data', 20);

// Load WordPress test environment
require $_tests_dir . '/includes/bootstrap.php';

// Additional test utilities and helpers

/**
 * Create a test product
 *
 * @param array $args Product arguments
 * @return WC_Product|false Product object or false on failure
 */
function woo_ai_create_test_product($args = []) {
    if (!class_exists('WC_Product_Simple')) {
        return false;
    }
    
    $defaults = [
        'name' => 'Test Product',
        'slug' => 'test-product',
        'regular_price' => '29.99',
        'short_description' => 'A test product for unit testing',
        'description' => 'This is a detailed description of the test product used for unit testing the Woo AI Assistant plugin.',
        'status' => 'publish',
        'catalog_visibility' => 'visible',
        'featured' => false,
        'manage_stock' => false,
    ];
    
    $args = wp_parse_args($args, $defaults);
    
    $product = new WC_Product_Simple();
    $product->set_name($args['name']);
    $product->set_slug($args['slug']);
    $product->set_regular_price($args['regular_price']);
    $product->set_short_description($args['short_description']);
    $product->set_description($args['description']);
    $product->set_status($args['status']);
    $product->set_catalog_visibility($args['catalog_visibility']);
    $product->set_featured($args['featured']);
    $product->set_manage_stock($args['manage_stock']);
    
    $product_id = $product->save();
    
    return $product_id ? wc_get_product($product_id) : false;
}

/**
 * Create test user with specific role
 *
 * @param string $role User role
 * @param array $args Additional user arguments
 * @return int|WP_Error User ID on success, WP_Error on failure
 */
function woo_ai_create_test_user($role = 'customer', $args = []) {
    $defaults = [
        'user_login' => 'test_user_' . uniqid(),
        'user_email' => 'test_' . uniqid() . '@example.com',
        'user_pass' => 'password123',
        'first_name' => 'Test',
        'last_name' => 'User',
        'role' => $role
    ];
    
    $args = wp_parse_args($args, $defaults);
    
    return wp_insert_user($args);
}

/**
 * Clean up test data
 */
function woo_ai_cleanup_test_data() {
    global $wpdb;
    
    // Clean up products
    $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type = 'product'");
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE post_id NOT IN (SELECT ID FROM {$wpdb->posts})");
    
    // Clean up test users (keep admin)
    $wpdb->query("DELETE FROM {$wpdb->users} WHERE ID > 1");
    $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE user_id NOT IN (SELECT ID FROM {$wpdb->users})");
    
    // Clean up terms
    $wpdb->query("DELETE FROM {$wpdb->terms} WHERE slug LIKE 'test-%'");
    
    // Clean up plugin options
    delete_option('woo_ai_assistant_test_option');
    
    // Clean up transients
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_woo_ai_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_woo_ai_%'");
}

/**
 * Register shutdown function to clean up
 */
register_shutdown_function('woo_ai_cleanup_test_data');

// Output initialization message
echo "\n";
echo "=== Woo AI Assistant Test Environment Initialized ===\n";
echo "Plugin Directory: " . WOO_AI_ASSISTANT_PLUGIN_DIR . "\n";
echo "WordPress Tests Directory: " . $_tests_dir . "\n";
echo "Test Database: " . DB_NAME . "\n";
echo "WooCommerce: " . (class_exists('WooCommerce') ? 'Loaded' : 'Not Available') . "\n";
echo "Development Mode: " . (defined('WOO_AI_DEVELOPMENT_MODE') && WOO_AI_DEVELOPMENT_MODE ? 'Yes' : 'No') . "\n";
echo "=====================================================\n";
echo "\n";