<?php
/**
 * PHPUnit Bootstrap File for Woo AI Assistant Plugin
 *
 * Sets up the testing environment including WordPress core,
 * WooCommerce, and plugin-specific configurations.
 *
 * @package WooAiAssistant\Tests
 * @since 1.0.0
 */

// Define test environment constants
define('ABSPATH', true);
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', false);
define('WP_DEBUG_DISPLAY', false);

// Plugin constants for testing
if (!defined('WOO_AI_ASSISTANT_VERSION')) {
    define('WOO_AI_ASSISTANT_VERSION', '1.0.0');
}
if (!defined('WOO_AI_ASSISTANT_DEBUG')) {
    define('WOO_AI_ASSISTANT_DEBUG', true);
}
if (!defined('WOO_AI_ASSISTANT_PLUGIN_FILE')) {
    define('WOO_AI_ASSISTANT_PLUGIN_FILE', __DIR__ . '/../woo-ai-assistant.php');
}

// Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Mock WordPress functions for unit tests
if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default') {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags($str));
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str) {
        return trim(strip_tags($str));
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email) {
        return filter_var($email, FILTER_SANITIZE_EMAIL);
    }
}

if (!function_exists('absint')) {
    function absint($maybeint) {
        return abs(intval($maybeint));
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action = -1) {
        return true; // Always return true for unit tests
    }
}

if (!function_exists('wp_die')) {
    function wp_die($message = '', $title = '', $args = array()) {
        throw new Exception($message);
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability, ...$args) {
        return true; // Always return true for unit tests
    }
}

if (!function_exists('add_action')) {
    function add_action($hook_name, $callback, $priority = 10, $accepted_args = 1) {
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook_name, $callback, $priority = 10, $accepted_args = 1) {
        return true;
    }
}

if (!function_exists('do_action')) {
    function do_action($hook_name, ...$args) {
        return true;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($hook_name, $value, ...$args) {
        return $value;
    }
}

if (!function_exists('remove_action')) {
    function remove_action($hook_name, $callback, $priority = 10) {
        return true;
    }
}

if (!function_exists('remove_filter')) {
    function remove_filter($hook_name, $callback, $priority = 10) {
        return true;
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show = '', $filter = 'raw') {
        switch ($show) {
            case 'version':
                return '6.0';
            default:
                return 'Test Blog';
        }
    }
}

if (!function_exists('is_admin')) {
    function is_admin() {
        return true;
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null, $status_code = null, $options = 0) {
        throw new Exception('AJAX Error: ' . json_encode($data));
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null, $status_code = null, $options = 0) {
        return json_encode(['success' => true, 'data' => $data]);
    }
}

if (!function_exists('load_plugin_textdomain')) {
    function load_plugin_textdomain($domain, $deprecated = false, $plugin_rel_path = false) {
        return true;
    }
}

if (!function_exists('plugin_basename')) {
    function plugin_basename($file) {
        return basename($file);
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value, $autoload = null) {
        return true;
    }
}

if (!function_exists('add_option')) {
    function add_option($option, $value, $deprecated = '', $autoload = 'yes') {
        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option($option) {
        return true;
    }
}

if (!function_exists('wp_cache_get')) {
    function wp_cache_get($key, $group = '') {
        return false;
    }
}

if (!function_exists('wp_cache_set')) {
    function wp_cache_set($key, $data, $group = '', $expire = 0) {
        return true;
    }
}

if (!function_exists('flush_rewrite_rules')) {
    function flush_rewrite_rules($hard = true) {
        return true;
    }
}

if (!function_exists('version_compare')) {
    // This function exists in PHP, but we ensure it's available
}

if (!function_exists('current_time')) {
    function current_time($type, $gmt = 0) {
        return date($type === 'timestamp' ? 'U' : 'Y-m-d H:i:s');
    }
}

if (!function_exists('error_log')) {
    // This function exists in PHP, but we ensure it's available
}

if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user() {
        $user = new stdClass();
        $user->exists = function() { return false; };
        $user->allcaps = [];
        return $user;
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1) {
        return 'test_nonce_' . $action;
    }
}

if (!function_exists('wp_date')) {
    function wp_date($format, $timestamp = null, $timezone = null) {
        return date($format, $timestamp ?: time());
    }
}

if (!function_exists('is_email')) {
    function is_email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

// Mock constants for testing
if (!defined('WOO_AI_ASSISTANT_PLUGIN_DIR_PATH')) {
    define('WOO_AI_ASSISTANT_PLUGIN_DIR_PATH', __DIR__ . '/../');
}

if (!defined('WOO_AI_ASSISTANT_PLUGIN_DIR_URL')) {
    define('WOO_AI_ASSISTANT_PLUGIN_DIR_URL', 'http://example.com/wp-content/plugins/woo-ai-assistant/');
}

if (!defined('WOO_AI_ASSISTANT_ASSETS_URL')) {
    define('WOO_AI_ASSISTANT_ASSETS_URL', 'http://example.com/wp-content/plugins/woo-ai-assistant/assets/');
}

// Mock global variables
global $wpdb;
$wpdb = new stdClass();
$wpdb->prefix = 'wp_';

// Set up basic PHP environment
date_default_timezone_set('UTC');

echo "PHPUnit Bootstrap loaded successfully.\n";