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

// Load test configuration if available
if (file_exists(__DIR__ . '/test-config.php')) {
    require_once __DIR__ . '/test-config.php';
}

// Define test environment constants
// Create a temporary WordPress directory structure for tests
$temp_wp_dir = sys_get_temp_dir() . '/wordpress_test/';
if (!is_dir($temp_wp_dir)) {
    mkdir($temp_wp_dir, 0755, true);
}
if (!is_dir($temp_wp_dir . 'wp-admin/includes')) {
    mkdir($temp_wp_dir . 'wp-admin/includes', 0755, true);
}

// Create a mock upgrade.php file that doesn't declare dbDelta
if (!file_exists($temp_wp_dir . 'wp-admin/includes/upgrade.php')) {
    file_put_contents($temp_wp_dir . 'wp-admin/includes/upgrade.php', '<?php
// Mock upgrade.php for testing
// dbDelta function is already mocked in bootstrap.php
');
}

if (!defined('ABSPATH')) {
    define('ABSPATH', $temp_wp_dir);
}

// Define WordPress database constants (use test config if loaded, otherwise defaults)
if (!defined('DB_NAME')) {
    define('DB_NAME', defined('TEST_DB_NAME') ? TEST_DB_NAME : 'woo_ai_test');
}
if (!defined('DB_USER')) {
    define('DB_USER', defined('TEST_DB_USER') ? TEST_DB_USER : 'root');
}
if (!defined('DB_PASSWORD')) {
    define('DB_PASSWORD', defined('TEST_DB_PASSWORD') ? TEST_DB_PASSWORD : 'root');
}
if (!defined('DB_HOST')) {
    // Use 127.0.0.1 instead of localhost for MAMP compatibility
    define('DB_HOST', defined('TEST_DB_HOST') ? TEST_DB_HOST : '127.0.0.1:8889');
}
if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', defined('TEST_DB_CHARSET') ? TEST_DB_CHARSET : 'utf8mb4');
}
if (!defined('DB_COLLATE')) {
    define('DB_COLLATE', defined('TEST_DB_COLLATE') ? TEST_DB_COLLATE : '');
}
// Debug constants are defined in test-config.php
if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}
if (!defined('WP_DEBUG_LOG')) {
    define('WP_DEBUG_LOG', false);
}
if (!defined('WP_DEBUG_DISPLAY')) {
    define('WP_DEBUG_DISPLAY', false);
}

// Set global WordPress version for compatibility checks
global $wp_version;
$wp_version = '6.5.0';

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

// Load Brain Monkey mock
require_once __DIR__ . '/mocks/BrainMonkeyMock.php';

// Load the base WP_UnitTestCase class
require_once __DIR__ . '/WP_UnitTestCase.php';

// Create global WP_UnitTestCase alias
if (!class_exists('WP_UnitTestCase')) {
    class WP_UnitTestCase extends \WooAiAssistant\Tests\WP_UnitTestCase {
        // Factory property is inherited from parent
        // setUp() method is also inherited and will be called automatically
    }
}

// Global registry for test data sharing  
global $test_data_registry;
$test_data_registry = [];

// Simple conversation mapping for mock
global $conversation_session_map;
$conversation_session_map = [];

// Mock factory for creating test data
class MockFactory {
    public $post;
    
    public function __construct() {
        $this->post = new MockPostFactory();
    }
}

class MockPostFactory {
    public function create($args = []) {
        global $mock_posts_storage;
        
        // Generate a unique post ID
        $post_id = rand(1000, 9999);
        
        // Store in mock storage
        $mock_posts_storage[$post_id] = $args;
        
        return $post_id;
    }
}

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
        // Strip tags and trim whitespace like WordPress does
        $filtered = trim(strip_tags($str));
        // Remove dangerous patterns including alert, script, etc.
        $filtered = preg_replace('/[<>&"\']|alert|script|javascript:/i', '', $filtered);
        return $filtered;
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str) {
        return trim(strip_tags($str));
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email) {
        // Check for dangerous content before sanitizing
        if (strpos($email, '<script') !== false || 
            strpos($email, 'javascript:') !== false ||
            strpos($email, '<') !== false || 
            strpos($email, '>') !== false) {
            return '';
        }
        
        return filter_var($email, FILTER_SANITIZE_EMAIL);
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        // WordPress sanitizes keys to lowercase alphanumeric + underscore + dash
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key));
    }
}

if (!function_exists('sanitize_title')) {
    function sanitize_title($title) {
        // WordPress sanitizes titles for use in URLs  
        $title = strip_tags($title);
        $title = strtolower($title);
        $title = preg_replace('/[^a-z0-9\s\-_]/', '', $title);
        $title = preg_replace('/[\s_]+/', '-', $title);
        return trim($title, '-');
    }
}

if (!function_exists('sanitize_sql_orderby')) {
    function sanitize_sql_orderby($orderby) {
        // WordPress function to sanitize SQL ORDER BY clause
        // Only allow valid column names and directions
        $valid_columns = ['id', 'created_at', 'updated_at', 'severity', 'category', 'event_type', 'user_id', 'ip_address', 'timestamp', 'severity_level'];
        
        if (in_array($orderby, $valid_columns)) {
            return $orderby;
        }
        
        return false; // Return false for invalid column names
    }
}

if (!function_exists('wp_kses')) {
    function wp_kses($string, $allowed_html, $allowed_protocols = array()) {
        // Simple implementation - strip all tags except allowed ones
        if (empty($allowed_html)) {
            return strip_tags($string);
        }
        
        // For test purposes, allow basic tags and strip dangerous ones
        $string = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $string);
        $string = preg_replace('/javascript:/i', '', $string);
        $string = preg_replace('/on\w+\s*=/i', '', $string);
        
        // Allow specific tags based on allowed_html array
        if (is_array($allowed_html)) {
            $allowed_tags = '<' . implode('><', array_keys($allowed_html)) . '>';
            return strip_tags($string, $allowed_tags);
        }
        
        return strip_tags($string);
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg($param, $value = null, $url = '') {
        if (is_array($param)) {
            // Multiple parameters
            $query = http_build_query($param);
        } else {
            // Single parameter
            $query = urlencode($param) . '=' . urlencode($value);
        }
        
        if (empty($url)) {
            $url = $_SERVER['REQUEST_URI'] ?? '';
        }
        
        $separator = strpos($url, '?') !== false ? '&' : '?';
        return $url . $separator . $query;
    }
}

if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

if (!function_exists('wp_referer_field')) {
    function wp_referer_field($echo = true) {
        $referer_field = '<input type="hidden" name="_wp_http_referer" value="' . esc_attr($_SERVER['REQUEST_URI'] ?? '') . '" />';
        
        if ($echo) {
            echo $referer_field;
            return;
        }
        
        return $referer_field;
    }
}

if (!function_exists('absint')) {
    function absint($maybeint) {
        return abs(intval($maybeint));
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action = -1) {
        // For test cases, check if nonce is invalid or wrong
        if ($nonce === 'invalid-nonce' || 
            $nonce === 'wrong-nonce' || 
            $nonce === 'expired-nonce' ||
            empty($nonce)) {
            return false;
        }
        
        // Check if nonce matches expected action
        global $test_nonce_actions;
        if (isset($test_nonce_actions[$nonce]) && $test_nonce_actions[$nonce] !== $action) {
            return false;
        }
        
        return true; // Return true for valid nonces in tests
    }
}

// Global storage for test nonce actions
global $test_nonce_actions;
if (!isset($test_nonce_actions)) {
    $test_nonce_actions = [];
}

// Custom exception for wp_die testing
class WPDieException extends Exception {}

if (!function_exists('wp_die')) {
    function wp_die($message = '', $title = '', $args = array()) {
        throw new WPDieException($message);
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability, ...$args) {
        return true; // Always return true for unit tests
    }
}

if (!function_exists('add_action')) {
    function add_action($hook_name, $callback, $priority = 10, $accepted_args = 1) {
        global $mock_actions;
        if (!isset($mock_actions[$hook_name])) {
            $mock_actions[$hook_name] = array();
        }
        $mock_actions[$hook_name][] = array(
            'callback' => $callback,
            'priority' => $priority,
            'accepted_args' => $accepted_args
        );
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
        global $mock_actions;
        if (isset($mock_actions[$hook_name])) {
            foreach ($mock_actions[$hook_name] as $action) {
                $callback = $action['callback'];
                if (is_callable($callback)) {
                    $callback(...$args);
                }
            }
        }
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

if (!function_exists('remove_all_filters')) {
    function remove_all_filters($hook_name = '', $priority = false) {
        global $mock_actions;
        if ($hook_name) {
            unset($mock_actions[$hook_name]);
        } else {
            $mock_actions = [];
        }
        return true;
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show = '', $filter = 'raw') {
        switch ($show) {
            case 'version':
                return '6.4.2';
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

// Mock options and transients storage for testing
global $mock_options, $mock_transients, $mock_actions;
if (!isset($mock_options)) {
    $mock_options = array();
}
if (!isset($mock_transients)) {
    $mock_transients = array();
}
if (!isset($mock_actions)) {
    $mock_actions = array();
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        global $mock_options;
        return isset($mock_options[$option]) ? $mock_options[$option] : $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value, $autoload = null) {
        global $mock_options;
        $mock_options[$option] = $value;
        return true;
    }
}

if (!function_exists('add_option')) {
    function add_option($option, $value, $deprecated = '', $autoload = 'yes') {
        global $mock_options;
        if (!isset($mock_options[$option])) {
            $mock_options[$option] = $value;
        }
        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option($option) {
        global $mock_options;
        unset($mock_options[$option]);
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

// Mock transient functions
if (!function_exists('get_transient')) {
    function get_transient($transient) {
        global $mock_transients;
        return isset($mock_transients[$transient]) ? $mock_transients[$transient] : false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration = 0) {
        global $mock_transients;
        $mock_transients[$transient] = $value;
        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($transient) {
        global $mock_transients;
        unset($mock_transients[$transient]);
        return true;
    }
}

// Mock URL functions
if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url) {
        // First validate that it's a valid URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }
        
        // Check for dangerous protocols
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['scheme'])) {
            return '';
        }
        
        $allowed_schemes = ['http', 'https', 'ftp', 'ftps', 'mailto'];
        if (!in_array(strtolower($parsed['scheme']), $allowed_schemes)) {
            return '';
        }
        
        return filter_var($url, FILTER_SANITIZE_URL);
    }
}

// Mock HTTP functions
if (!function_exists('wp_remote_request')) {
    function wp_remote_request($url, $args = array()) {
        // Mock HTTP response for testing
        return array(
            'response' => array(
                'code' => 200,
                'message' => 'OK'
            ),
            'body' => json_encode(array(
                'success' => true,
                'data' => 'mock response'
            ))
        );
    }
}

if (!function_exists('wp_remote_head')) {
    function wp_remote_head($url, $args = array()) {
        // Make localhost:3000 return 404 to simulate server not being available
        if (strpos($url, 'localhost:3000') !== false) {
            return array(
                'response' => array(
                    'code' => 404,
                    'message' => 'Not Found'
                )
            );
        }
        
        return array(
            'response' => array(
                'code' => 200,
                'message' => 'OK'
            )
        );
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

// Mock WP_List_Table class
if (!class_exists('WP_List_Table')) {
    class WP_List_Table {
        public $items = [];
        
        public function __construct($args = []) {
            // Mock constructor
        }
        
        public function get_items_per_page($option, $default = 20) {
            return $default;
        }
        
        public function get_pagenum() {
            return 1;
        }
        
        public function set_pagination_args($args) {
            // Mock pagination
        }
        
        public function current_action() {
            return false;
        }
        
        public function prepare_items() {
            // Mock prepare items
        }
        
        public function display() {
            echo '<table><tbody></tbody></table>';
        }
        
        public function row_actions($actions) {
            $output = '<div class="row-actions">';
            foreach ($actions as $action => $link) {
                $output .= '<span class="' . $action . '">' . $link . '</span>';
            }
            $output .= '</div>';
            return $output;
        }
    }
}

// Mock WP_REST_Server class
if (!class_exists('WP_REST_Server')) {
    class WP_REST_Server {
        const READABLE = 'GET';
        const CREATABLE = 'POST';
        const EDITABLE = 'POST, PUT, PATCH';
        const DELETABLE = 'DELETE';
        
        private $routes = [];
        
        public function register_route($namespace, $route, $route_args) {
            $this->routes[$namespace . $route] = $route_args;
            return true;
        }
        
        public function get_routes($namespace = '') {
            if ($namespace) {
                $filtered_routes = [];
                foreach ($this->routes as $route => $args) {
                    if (strpos($route, $namespace) === 0) {
                        $filtered_routes[$route] = $args;
                    }
                }
                return $filtered_routes;
            }
            return $this->routes;
        }
    }
}

// Mock WP_REST_Request class
if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request {
        private $params = [];
        private $method = 'GET';
        
        public function __construct($method = 'GET') {
            $this->method = $method;
        }
        
        public function set_param($key, $value) {
            $this->params[$key] = $value;
        }
        
        public function get_param($key) {
            return isset($this->params[$key]) ? $this->params[$key] : null;
        }
        
        public function get_params() {
            return $this->params;
        }
        
        public function get_method() {
            return $this->method;
        }
    }
}

// Mock WP_REST_Response class
if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        private $data;
        private $status;
        
        public function __construct($data = null, $status = 200) {
            $this->data = $data;
            $this->status = $status;
        }
        
        public function get_data() {
            return $this->data;
        }
        
        public function set_data($data) {
            $this->data = $data;
        }
        
        public function get_status() {
            return $this->status;
        }
        
        public function set_status($status) {
            $this->status = $status;
        }
    }
}

// Mock WP_Query class
if (!class_exists('WP_Query')) {
    class WP_Query {
        public $posts = [];
        
        public function __construct($args = []) {
            global $mock_posts_storage;
            
            // Handle post__in parameter for specific post IDs
            if (isset($args['post__in']) && !empty($args['post__in'])) {
                foreach ($args['post__in'] as $post_id) {
                    // Only return posts that exist in mock storage
                    if (isset($mock_posts_storage[$post_id])) {
                        $this->posts[] = $post_id;
                    }
                }
                // If post__in is specified but no posts found, return empty array
                // (this is already handled by only adding posts that exist)
            } else {
                // Return all posts from mock storage that match criteria
                $post_type = $args['post_type'] ?? 'post';
                foreach ($mock_posts_storage as $post_id => $post_data) {
                    if (($post_data['post_type'] ?? 'post') === $post_type) {
                        $this->posts[] = $post_id;
                    }
                }
            }
            
            // Respect the limit
            if (isset($args['posts_per_page']) && $args['posts_per_page'] > 0) {
                $this->posts = array_slice($this->posts, 0, $args['posts_per_page']);
            }
        }
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
        $user->has_cap = function($capability) { 
            return true; // Mock that user has all capabilities for testing
        };
        return $user;
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1) {
        global $test_nonce_actions;
        $nonce = 'test_nonce_' . $action . '_' . uniqid();
        
        // Store the action for this nonce for verification
        $test_nonce_actions[$nonce] = $action;
        
        return $nonce;
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

// Mock post storage for tracking created posts
global $mock_posts_storage;
if (!isset($mock_posts_storage)) {
    $mock_posts_storage = [];
}

// Mock get_posts function
if (!function_exists('get_posts')) {
    function get_posts($args = []) {
        global $mock_posts_storage;
        
        // Return mock posts based on arguments
        $mock_posts = [];
        
        // Handle post__in parameter for specific post IDs
        if (isset($args['post__in']) && !empty($args['post__in'])) {
            foreach ($args['post__in'] as $post_id) {
                // Only return posts that actually exist in mock storage or are in reasonable range
                if (isset($mock_posts_storage[$post_id]) || $post_id <= 100) {
                    $post_data = [
                        'ID' => $post_id,
                        'post_title' => isset($mock_posts_storage[$post_id]) ? $mock_posts_storage[$post_id]['post_title'] : 'Test Post ' . $post_id,
                        'post_content' => isset($mock_posts_storage[$post_id]) ? $mock_posts_storage[$post_id]['post_content'] : 'Test content for post ' . $post_id,
                        'post_status' => 'publish',
                        'post_type' => isset($args['post_type']) ? $args['post_type'] : 'post',
                        'post_date' => '2023-01-0' . min($post_id, 9) . ' 00:00:00',
                        'post_modified' => '2023-01-0' . min($post_id, 9) . ' 00:00:00',
                        'post_author' => 1,
                        'post_excerpt' => ''
                    ];
                    
                    $mock_posts[] = new WP_Post($post_data);
                }
            }
            return $mock_posts;
        }
        
        $num_posts = isset($args['numberposts']) ? $args['numberposts'] : 5;
        
        for ($i = 1; $i <= $num_posts; $i++) {
            $post_data = [
                'ID' => $i,
                'post_title' => 'Test Post ' . $i,
                'post_content' => 'Test content for post ' . $i,
                'post_status' => 'publish',
                'post_type' => isset($args['post_type']) ? $args['post_type'] : 'post',
                'post_date' => '2023-01-0' . min($i, 9) . ' 00:00:00',
                'post_modified' => '2023-01-0' . min($i, 9) . ' 00:00:00',
                'post_author' => 1,
                'post_excerpt' => ''
            ];
            
            $mock_posts[] = new WP_Post($post_data);
        }
        
        return $mock_posts;
    }
}

// Mock get_the_author_meta function
if (!function_exists('get_the_author_meta')) {
    function get_the_author_meta($field, $user_id = null) {
        switch ($field) {
            case 'display_name':
                return 'Test Author';
            case 'user_email':
                return 'test@example.com';
            case 'user_login':
                return 'testuser';
            default:
                return 'Test Meta Value';
        }
    }
}

// Mock get_author_posts_url function
if (!function_exists('get_author_posts_url')) {
    function get_author_posts_url($author_id, $author_nicename = '') {
        return 'http://example.com/author/test-author/';
    }
}

// Mock additional WordPress functions for Scanner tests
if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = array()) {
        if (is_object($args)) {
            $parsed_args = get_object_vars($args);
        } elseif (is_array($args)) {
            $parsed_args = &$args;
        } else {
            wp_parse_str($args, $parsed_args);
        }

        if (is_array($defaults) && $defaults) {
            return array_merge($defaults, $parsed_args);
        }
        return $parsed_args;
    }
}

if (!function_exists('wp_parse_str')) {
    function wp_parse_str($string, &$array) {
        parse_str($string, $array);
    }
}

// Mock WP_Post class
if (!class_exists('WP_Post')) {
    class WP_Post {
        public $ID;
        public $post_title;
        public $post_content;
        public $post_status;
        public $post_type;
        public $post_date;
        public $post_modified;
        public $post_author;
        public $post_excerpt;
        
        public function __construct($post_data = []) {
            $this->ID = isset($post_data['ID']) ? $post_data['ID'] : 0;
            $this->post_title = isset($post_data['post_title']) ? $post_data['post_title'] : '';
            $this->post_content = isset($post_data['post_content']) ? $post_data['post_content'] : '';
            $this->post_status = isset($post_data['post_status']) ? $post_data['post_status'] : 'publish';
            $this->post_type = isset($post_data['post_type']) ? $post_data['post_type'] : 'post';
            $this->post_date = isset($post_data['post_date']) ? $post_data['post_date'] : '2023-01-01 00:00:00';
            $this->post_modified = isset($post_data['post_modified']) ? $post_data['post_modified'] : '2023-01-01 00:00:00';
            $this->post_author = isset($post_data['post_author']) ? $post_data['post_author'] : 1;
            $this->post_excerpt = isset($post_data['post_excerpt']) ? $post_data['post_excerpt'] : '';
        }
    }
}

// Mock WP_Query for product/page scanning
if (!class_exists('WP_Query')) {
    class WP_Query {
        public $posts = [];
        
        public function __construct($args = []) {
            // Mock some post IDs for testing
            $this->posts = [1, 2, 3, 4, 5];
        }
    }
}

// Additional WordPress functions for Scanner tests
if (!function_exists('get_post')) {
    function get_post($post_id, $output = OBJECT, $filter = 'raw') {
        global $mock_posts_storage;
        
        $post_data = [
            'ID' => $post_id,
            'post_title' => 'Test Post ' . $post_id,
            'post_content' => 'Test content for post ' . $post_id,
            'post_status' => 'publish',
            'post_type' => 'post',
            'post_date' => '2023-01-01 00:00:00',
            'post_modified' => '2023-01-01 00:00:00',
            'post_author' => 1,
            'post_excerpt' => ''
        ];
        
        // Use stored post data if available
        if (isset($mock_posts_storage[$post_id])) {
            $post_data = array_merge($post_data, $mock_posts_storage[$post_id]);
        }
        
        return new WP_Post($post_data);
    }
}

if (!function_exists('wp_delete_post')) {
    function wp_delete_post($postid = 0, $force_delete = false) {
        return true;
    }
}

if (!function_exists('wp_insert_post')) {
    function wp_insert_post($postarr, $wp_error = false) {
        global $mock_posts_storage;
        
        // Generate a mock post ID
        $post_id = rand(1000, 9999);
        
        // Store post data in mock storage
        $mock_posts_storage[$post_id] = $postarr;
        
        return $post_id;
    }
}

if (!function_exists('wp_update_post')) {
    function wp_update_post($postarr = array(), $wp_error = false) {
        global $mock_posts_storage;
        
        $post_id = isset($postarr['ID']) ? $postarr['ID'] : rand(1000, 9999);
        
        // Update post data in mock storage
        if (isset($mock_posts_storage[$post_id])) {
            $mock_posts_storage[$post_id] = array_merge($mock_posts_storage[$post_id], $postarr);
        } else {
            $mock_posts_storage[$post_id] = $postarr;
        }
        
        return $post_id;
    }
}

if (!function_exists('get_permalink')) {
    function get_permalink($post = 0, $leavename = false) {
        return 'http://example.com/post-' . $post . '/';
    }
}

if (!function_exists('get_term_link')) {
    function get_term_link($term, $taxonomy = '') {
        if (is_object($term)) {
            return 'http://example.com/term-' . $term->term_id . '/';
        }
        return 'http://example.com/term-' . $term . '/';
    }
}

if (!function_exists('get_term')) {
    function get_term($term, $taxonomy = '', $output = OBJECT, $filter = 'raw') {
        if (is_wp_error($term)) {
            return $term;
        }
        
        $term_obj = new \stdClass();
        $term_obj->term_id = is_numeric($term) ? $term : rand(1, 100);
        $term_obj->name = 'Test Term ' . $term_obj->term_id;
        $term_obj->slug = 'test-term-' . $term_obj->term_id;
        $term_obj->description = 'Test term description';
        $term_obj->taxonomy = $taxonomy ?: 'category';
        $term_obj->count = rand(1, 10);
        $term_obj->parent = 0;
        
        return $term_obj;
    }
}

if (!function_exists('get_terms')) {
    function get_terms($args = array(), $deprecated = '') {
        // Return an array of mock terms
        $terms = [];
        for ($i = 1; $i <= 3; $i++) {
            $term = new \stdClass();
            $term->term_id = $i;
            $term->name = 'Test Term ' . $i;
            $term->slug = 'test-term-' . $i;
            $term->description = 'Test term description ' . $i;
            $term->taxonomy = isset($args['taxonomy']) ? $args['taxonomy'] : 'category';
            $term->count = rand(1, 10);
            $term->parent = 0;
            $terms[] = $term;
        }
        return $terms;
    }
}

if (!function_exists('taxonomy_exists')) {
    function taxonomy_exists($taxonomy) {
        $valid_taxonomies = ['category', 'post_tag', 'product_cat', 'product_tag'];
        return in_array($taxonomy, $valid_taxonomies);
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return false; // Simplified for testing
    }
}

if (!function_exists('get_the_category')) {
    function get_the_category($post_id = false) {
        $category = new \stdClass();
        $category->term_id = 1;
        $category->name = 'Test Category';
        $category->slug = 'test-category';
        return [$category];
    }
}

if (!function_exists('get_the_tags')) {
    function get_the_tags($post_id = false) {
        $tag = new \stdClass();
        $tag->term_id = 1;
        $tag->name = 'Test Tag';
        $tag->slug = 'test-tag';
        return [$tag];
    }
}

if (!function_exists('wp_cache_delete')) {
    function wp_cache_delete($key, $group = '') {
        return true;
    }
}

if (!function_exists('wp_cache_flush_group')) {
    function wp_cache_flush_group($group) {
        return true;
    }
}

if (!function_exists('wp_cache_flush')) {
    function wp_cache_flush() {
        return true;
    }
}

if (!function_exists('wp_get_referer')) {
    function wp_get_referer() {
        return 'http://example.com/';
    }
}

if (!function_exists('session_id')) {
    function session_id($id = '') {
        if ($id !== '') {
            return $id;
        }
        return 'test_session_' . rand(1000, 9999);
    }
}

if (!function_exists('has_action')) {
    function has_action($tag, $function_to_check = false) {
        global $mock_actions;
        
        if (!isset($mock_actions[$tag])) {
            return false;
        }
        
        if ($function_to_check) {
            // Check for specific callback
            foreach ($mock_actions[$tag] as $action) {
                if ($action['callback'] === $function_to_check) {
                    return $action['priority'];
                }
            }
            return false;
        }
        
        // Return true if hook has any registered callbacks
        return !empty($mock_actions[$tag]);
    }
}

if (!function_exists('has_filter')) {
    function has_filter($tag, $function_to_check = false) {
        return has_action($tag, $function_to_check);
    }
}

if (!function_exists('get_locale')) {
    function get_locale() {
        return 'en_US';
    }
}

// Add missing WordPress scheduling functions
if (!function_exists('wp_schedule_single_event')) {
    function wp_schedule_single_event($timestamp, $hook, $args = []) {
        return true; // Mock successful scheduling
    }
}

// Add missing wp_json_encode function
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0, $depth = 512) {
        return json_encode($data, $options, $depth);
    }
}

// Add missing wp_trim_words function
if (!function_exists('wp_trim_words')) {
    function wp_trim_words($text, $num_words = 55, $more = null) {
        if (null === $more) {
            $more = '…';
        }
        
        $words = explode(' ', trim(strip_tags($text)));
        if (count($words) <= $num_words) {
            return $text;
        }
        
        return implode(' ', array_slice($words, 0, $num_words)) . $more;
    }
}

// Add admin URL function
if (!function_exists('admin_url')) {
    function admin_url($path = '', $scheme = 'admin') {
        return 'http://example.com/wp-admin/' . ltrim($path, '/');
    }
}

// Define missing WordPress constants
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}

if (!defined('ARRAY_N')) {
    define('ARRAY_N', 'ARRAY_N');
}

if (!defined('OBJECT_K')) {
    define('OBJECT_K', 'OBJECT_K');
}

// WordPress time constants
if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}

if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 24 * HOUR_IN_SECONDS);
}

if (!defined('WEEK_IN_SECONDS')) {
    define('WEEK_IN_SECONDS', 7 * DAY_IN_SECONDS);
}

// Mock WooCommerce functions for Scanner tests
if (!function_exists('wc_get_product')) {
    function wc_get_product($product_id = false) {
        if (!$product_id) return false;
        
        return new WC_Product($product_id);
    }
}

// Mock additional WordPress functions
if (!function_exists('dbDelta')) {
    function dbDelta($queries = '', $execute = true) {
        return ['All tables created successfully'];
    }
}

if (!function_exists('wp_get_scheduled_event')) {
    function wp_get_scheduled_event($hook, $args = [], $timestamp = null) {
        return false; // No scheduled events in tests
    }
}

if (!function_exists('wp_schedule_event')) {
    function wp_schedule_event($timestamp, $recurrence, $hook, $args = []) {
        return true;
    }
}

if (!function_exists('wp_clear_scheduled_hook')) {
    function wp_clear_scheduled_hook($hook, $args = []) {
        return true;
    }
}

// Global mock storage for cron jobs
global $mock_cron_jobs;
if (!isset($mock_cron_jobs)) {
    $mock_cron_jobs = [];
}

if (!function_exists('wp_schedule_event')) {
    function wp_schedule_event($timestamp, $recurrence, $hook, $args = []) {
        global $mock_cron_jobs;
        $mock_cron_jobs[$hook] = [
            'timestamp' => $timestamp,
            'recurrence' => $recurrence,
            'args' => $args
        ];
        return true;
    }
}

if (!function_exists('wp_next_scheduled')) {
    function wp_next_scheduled($hook, $args = []) {
        global $mock_cron_jobs;
        return isset($mock_cron_jobs[$hook]) ? $mock_cron_jobs[$hook]['timestamp'] : false;
    }
}

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir($time = null, $create_dir = true, $refresh_cache = false) {
        return [
            'path' => '/tmp/uploads',
            'url' => 'http://example.com/uploads',
            'subdir' => '',
            'basedir' => '/tmp/uploads',
            'baseurl' => 'http://example.com/uploads',
            'error' => false
        ];
    }
}

if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($target) {
        return true;
    }
}

if (!function_exists('get_role')) {
    function get_role($role) {
        $role_obj = new class {
            public function add_cap($cap) {
                return true;
            }
            public function remove_cap($cap) {
                return true;
            }
        };
        return $role_obj;
    }
}

// Add missing WooCommerce functions
if (!function_exists('wc_price')) {
    function wc_price($price, $args = []) {
        return '$' . number_format(floatval($price), 2);
    }
}

// Mock WooCommerce core functions
if (!function_exists('WC')) {
    function WC() {
        return new class {
            public $countries;
            public $payment_gateways;
            
            public function __construct() {
                $this->countries = new class {
                    public function get_base_address() {
                        return 'Test Address';
                    }
                    
                    public function get_base_city() {
                        return 'Test City';
                    }
                    
                    public function get_base_country() {
                        return 'US';
                    }
                    
                    public function get_base_postcode() {
                        return '12345';
                    }
                };
                
                $this->payment_gateways = new class {
                    public function get_available_payment_gateways() {
                        $gateway = new class {
                            public $enabled = 'yes';
                            
                            public function get_title() {
                                return 'Test Payment Gateway';
                            }
                            
                            public function get_description() {
                                return 'Test payment method';
                            }
                        };
                        
                        return ['test_gateway' => $gateway];
                    }
                };
            }
            
            public function payment_gateways() {
                return $this->payment_gateways;
            }
        };
    }
}

if (!function_exists('get_woocommerce_currency')) {
    function get_woocommerce_currency() {
        return 'USD';
    }
}

if (!function_exists('get_woocommerce_currency_symbol')) {
    function get_woocommerce_currency_symbol($currency = '') {
        return '$';
    }
}

if (!function_exists('wc_tax_enabled')) {
    function wc_tax_enabled() {
        return true;
    }
}

if (!function_exists('wc_prices_include_tax')) {
    function wc_prices_include_tax() {
        return false;
    }
}

// Mock WooCommerce product class
if (!class_exists('WC_Product')) {
    class WC_Product {
        private $id;
        
        public function __construct($id = 0) {
            $this->id = $id;
        }
        
        public function exists() {
            return $this->id > 0;
        }
        
        public function get_id() {
            return $this->id;
        }
        
        public function get_name() {
            return 'Test Product ' . $this->id;
        }
        
        public function get_description() {
            return 'Test product description for product ' . $this->id;
        }
        
        public function get_short_description() {
            return 'Short description for product ' . $this->id;
        }
        
        public function get_price() {
            return '99.99';
        }
        
        public function get_regular_price() {
            return '99.99';
        }
        
        public function get_sale_price() {
            return '';
        }
        
        public function get_sku() {
            return 'SKU-' . $this->id;
        }
        
        public function get_category_ids() {
            return [1, 2];
        }
        
        public function get_tag_ids() {
            return [1];
        }
        
        public function get_attributes() {
            return [];
        }
        
        public function is_type($type) {
            return $type === 'simple';
        }
        
        public function get_permalink() {
            return 'http://example.com/product-' . $this->id . '/';
        }
        
        public function is_on_sale() {
            return false; // Mock product not on sale
        }
        
        public function managing_stock() {
            return false; // Mock product not managing stock
        }
        
        public function get_stock_status() {
            return 'instock';
        }
        
        public function get_type() {
            return 'simple';
        }
        
        public function is_purchasable() {
            return $this->id > 0;
        }
        
        public function is_in_stock() {
            return true;
        }
        
        public function has_enough_stock($quantity) {
            return true;
        }
        
        public function get_upsell_ids() {
            return [$this->id + 10, $this->id + 11];
        }
        
        public function get_cross_sell_ids() {
            return [$this->id + 20, $this->id + 21];
        }
    }
}

// Mock WooCommerce class availability
if (!class_exists('WooCommerce')) {
    class WooCommerce {
        public function __construct() {
            // Mock WooCommerce class
        }
    }
}

// Mock WooCommerce shipping zones
if (!class_exists('WC_Shipping_Zones')) {
    class WC_Shipping_Zones {
        public static function get_zones() {
            return [
                [
                    'zone_name' => 'Test Zone',
                    'shipping_methods' => [
                        new class {
                            public function get_title() {
                                return 'Free Shipping';
                            }
                            
                            public function get_option($option, $default = '') {
                                return $option === 'cost' ? 'Free' : $default;
                            }
                        }
                    ]
                ]
            ];
        }
    }
}

if (!function_exists('stripslashes_deep')) {
    function stripslashes_deep($value) {
        return is_array($value) ? array_map('stripslashes_deep', $value) : stripslashes($value);
    }
}

if (!function_exists('get_magic_quotes_gpc')) {
    function get_magic_quotes_gpc() {
        return false;
    }
}

// Add missing WordPress functions
if (!function_exists('wp_generate_uuid4')) {
    function wp_generate_uuid4() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}

if (!function_exists('wp_set_current_user')) {
    function wp_set_current_user($id, $name = '') {
        return $id;
    }
}

if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script($handle, $src = '', $deps = array(), $ver = false, $in_footer = false) {
        return true;
    }
}

if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style($handle, $src = '', $deps = array(), $ver = false, $media = 'all') {
        return true;
    }
}

if (!function_exists('wp_localize_script')) {
    function wp_localize_script($handle, $object_name, $l10n) {
        return true;
    }
}

if (!function_exists('wp_script_is')) {
    function wp_script_is($handle, $list = 'enqueued') {
        return true; // Mock that all scripts are enqueued
    }
}

if (!function_exists('wp_deregister_script')) {
    function wp_deregister_script($handle) {
        return true;
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id() {
        return 1; // Mock user ID for testing
    }
}

if (!function_exists('get_user_by')) {
    function get_user_by($field, $value) {
        // Mock user for testing
        $user = new stdClass();
        $user->ID = 1;
        $user->user_login = 'admin';
        $user->user_email = 'admin@example.com';
        $user->display_name = 'Test Admin';
        return $user;
    }
}

// Note: Cannot override built-in disk_* functions, fixed division by zero in HealthMonitor.php instead

if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args = array()) {
        // Mock successful API response for testing
        return array(
            'response' => array(
                'code' => 200
            ),
            'body' => json_encode(array(
                'choices' => array(
                    array(
                        'message' => array(
                            'content' => 'Mock AI response for testing'
                        )
                    )
                ),
                'usage' => array(
                    'total_tokens' => 50
                ),
                'model' => 'mock-model'
            ))
        );
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        return isset($response['response']['code']) ? $response['response']['code'] : 200;
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        return isset($response['body']) ? $response['body'] : '';
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return ($thing instanceof WP_Error);
    }
}

if (!function_exists('register_rest_route')) {
    function register_rest_route($namespace, $route, $args = array(), $override = false) {
        // Mock implementation for testing
        return true;
    }
}

if (!function_exists('rest_url')) {
    function rest_url($path = '', $scheme = 'rest') {
        // Mock REST URL generation for testing
        return 'http://example.com/wp-json/' . ltrim($path, '/');
    }
}

// Mock WP_Error class
if (!class_exists('WP_Error')) {
    class WP_Error {
        public $errors = array();
        public $error_data = array();
        
        public function __construct($code = '', $message = '', $data = '') {
            if (!empty($code)) {
                $this->errors[$code][] = $message;
                if (!empty($data)) {
                    $this->error_data[$code] = $data;
                }
            }
        }
        
        public function get_error_message($code = '') {
            if (empty($code)) {
                $code = $this->get_error_code();
            }
            
            if (isset($this->errors[$code])) {
                return $this->errors[$code][0];
            }
            
            return '';
        }
        
        public function get_error_code() {
            if (empty($this->errors)) {
                return '';
            }
            
            return array_keys($this->errors)[0];
        }
        
        public function get_error_data($code = '') {
            if (empty($code)) {
                $code = $this->get_error_code();
            }
            
            if (isset($this->error_data[$code])) {
                return $this->error_data[$code];
            }
            
            return null;
        }
    }
}

// Mock global $wp_filter for hook testing
global $wp_filter;
if (!isset($wp_filter)) {
    $wp_filter = array(
        'wp_ajax_woo_ai_assistant_stream_response' => new stdClass(),
        'wp_ajax_nopriv_woo_ai_assistant_stream_response' => new stdClass()
    );
    
    // Mock the hooks structure
    $wp_filter['wp_ajax_woo_ai_assistant_stream_response']->callbacks = array(
        10 => array(
            'woo_ai_assistant_callback' => array(
                'function' => 'test_callback',
                'accepted_args' => 1
            )
        )
    );
    
    $wp_filter['wp_ajax_nopriv_woo_ai_assistant_stream_response']->callbacks = array(
        10 => array(
            'woo_ai_assistant_callback' => array(
                'function' => 'test_callback',
                'accepted_args' => 1
            )
        )
    );
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
$wpdb = new class {
    public $prefix = 'wp_';
    private $mockData = [];
    private $insertId = 1;
    
    public function get_charset_collate() {
        return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
    }
    
    public function prepare($query, ...$args) {
        // Handle WordPress prepared query placeholders
        if (empty($args)) {
            return $query;
        }
        
        // Count placeholders in query
        $placeholder_count = substr_count($query, '%s') + 
                           substr_count($query, '%d') + 
                           substr_count($query, '%f');
        
        // If no placeholders, return original query
        if ($placeholder_count === 0) {
            return $query;
        }
        
        // If we have more placeholders than arguments, pad with appropriate defaults
        while (count($args) < $placeholder_count) {
            $args[] = '';
        }
        
        // If we have more arguments than placeholders, truncate
        $args = array_slice($args, 0, $placeholder_count);
        
        // Use str_replace for simple placeholder replacement to avoid sprintf issues
        $prepared_query = $query;
        $arg_index = 0;
        
        // Replace %s placeholders
        while (($pos = strpos($prepared_query, '%s')) !== false) {
            if ($arg_index < count($args)) {
                $prepared_query = substr_replace($prepared_query, "'" . $args[$arg_index] . "'", $pos, 2);
                $arg_index++;
            } else {
                $prepared_query = substr_replace($prepared_query, "''", $pos, 2);
            }
        }
        
        // Replace %d placeholders  
        while (($pos = strpos($prepared_query, '%d')) !== false) {
            if ($arg_index < count($args)) {
                $prepared_query = substr_replace($prepared_query, intval($args[$arg_index]), $pos, 2);
                $arg_index++;
            } else {
                $prepared_query = substr_replace($prepared_query, '0', $pos, 2);
            }
        }
        
        // Replace %f placeholders
        while (($pos = strpos($prepared_query, '%f')) !== false) {
            if ($arg_index < count($args)) {
                $prepared_query = substr_replace($prepared_query, floatval($args[$arg_index]), $pos, 2);
                $arg_index++;
            } else {
                $prepared_query = substr_replace($prepared_query, '0.0', $pos, 2);
            }
        }
        
        return $prepared_query;
    }
    
    public function query($query) {
        // Handle different query types
        if (preg_match('/DELETE FROM/i', $query)) {
            return 1; // Return number of affected rows
        }
        if (preg_match('/^START TRANSACTION|^COMMIT|^ROLLBACK/i', $query)) {
            return true;
        }
        return true;
    }
    
    public function get_results($query, $output = OBJECT) {
        // Handle DESCRIBE table queries for database structure tests
        if (preg_match('/DESCRIBE\s+(\w+)/i', $query, $matches)) {
            $table_name = $matches[1];
            
            // Mock conversations table structure
            if (strpos($table_name, 'woo_ai_conversations') !== false) {
                return [
                    (object) [
                        'Field' => 'id',
                        'Type' => 'bigint(20) unsigned',
                        'Null' => 'NO',
                        'Key' => 'PRI',
                        'Default' => null,
                        'Extra' => 'auto_increment'
                    ],
                    (object) [
                        'Field' => 'conversation_id',
                        'Type' => 'varchar(255)',
                        'Null' => 'NO',
                        'Key' => '',
                        'Default' => null,
                        'Extra' => ''
                    ],
                    (object) [
                        'Field' => 'user_id',
                        'Type' => 'bigint(20) unsigned',
                        'Null' => 'YES',
                        'Key' => '',
                        'Default' => null,
                        'Extra' => ''
                    ]
                ];
            }
            
            // Mock knowledge base table structure
            if (strpos($table_name, 'woo_ai_knowledge_base') !== false) {
                return [
                    (object) [
                        'Field' => 'id',
                        'Type' => 'bigint(20) unsigned',
                        'Null' => 'NO',
                        'Key' => 'PRI',
                        'Default' => null,
                        'Extra' => 'auto_increment'
                    ],
                    (object) [
                        'Field' => 'chunk_id',
                        'Type' => 'varchar(255)',
                        'Null' => 'NO',
                        'Key' => 'UNI',
                        'Default' => null,
                        'Extra' => ''
                    ]
                ];
            }
        }
        
        // Return mock statistical data for chunk stats queries
        if (preg_match('/SELECT.*source_type.*COUNT/i', $query)) {
            return [
                (object) [
                    'source_type' => 'test_stats',
                    'total_chunks' => 1,
                    'avg_chunk_size' => 20
                ]
            ];
        }
        return [];
    }
    
    public function get_row($query, $output = OBJECT, $y = 0) {
        // Extract conversation_id from query to provide more realistic mock data
        $conversationId = 'test_conv_id';
        $userId = 123; // Default test user ID
        $sessionId = 'test_session';
        
        // Try to extract real values from the query - conversation_id can contain spaces and dashes
        if (preg_match("/conversation_id = (conv_[^)]+)/", $query, $matches)) {
            $conversationId = $matches[1];
            
            // Try to extract user_id from conversation_id pattern (conv_USER_...)
            if (preg_match("/conv_(\d+)_/", $conversationId, $userMatches)) {
                $userId = (int)$userMatches[1];
                
                // Try to get session_id from conversation mapping first
                global $conversation_session_map;
                if (isset($conversation_session_map[$conversationId])) {
                    $sessionId = $conversation_session_map[$conversationId];
                } else {
                    // Fallback: try to reconstruct session_id from conversation_id pattern
                    if (preg_match("/conv_\d+_([a-f0-9]+)_/", $conversationId, $sessionMatches)) {
                        $sessionPart = $sessionMatches[1];
                        // Create a session_id that ends with the session part from conversation_id
                        $sessionId = 'test_session_12345678-1234-1234-1234-123456' . $sessionPart;
                    }
                }
            }
        }
        
        // Try to extract session_id if present (for queries that include session_id directly)
        if (preg_match("/session_id = '([^']+)'/", $query, $matches)) {
            $sessionId = $matches[1];
        } elseif (preg_match("/session_id = ([^\s]+)/", $query, $matches)) {
            $sessionId = $matches[1];
        }
        
        $data = [
            'id' => 1,
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'session_id' => $sessionId,
            'status' => 'active',
            'context' => '{}',
            'started_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'total_messages' => 0,
            'source_type' => 'test'
        ];
        
        if ($output === ARRAY_A) {
            return $data;
        }
        
        $row = new stdClass();
        foreach ($data as $key => $value) {
            $row->$key = $value;
        }
        return $row;
    }
    
    public function get_var($query, $x = 0, $y = 0) {
        // Handle table existence check - extract table name from query
        if (preg_match('/SHOW TABLES LIKE\s+[\'"]([^\'"]+)[\'"]/', $query, $matches)) {
            // Return the actual table name being queried (table exists)
            return $matches[1];
        }
        // Handle hash existence check - always return 0 (no duplicates) for successful processing
        if (preg_match('/SELECT.*COUNT.*FROM.*hash/i', $query)) {
            return '0'; // No duplicate hash found
        }
        // Handle other hash queries
        if (preg_match('/SELECT.*hash/i', $query)) {
            return null; // No duplicate hash found
        }
        return '1';
    }
    
    public function get_col($query, $x = 0) {
        // Mock for cleanup methods that select conversation IDs
        if (preg_match('/SELECT conversation_id/i', $query)) {
            return ['old_conv_123', 'old_conv_456'];
        }
        return [];
    }
    
    public function insert($table, $data, $format = null) {
        // Store inserted data for verification
        $id = $this->insertId++;
        $this->mockData[] = array_merge($data, ['id' => $id]);
        
        // Store conversation → session mapping for get_row queries
        if (strpos($table, 'woo_ai_conversations') !== false && 
            isset($data['conversation_id']) && isset($data['session_id'])) {
            global $conversation_session_map;
            $conversation_session_map[$data['conversation_id']] = $data['session_id'];
        }
        
        return 1; // Number of rows affected
    }
    
    public function update($table, $data, $where, $format = null, $where_format = null) {
        return 1;
    }
    
    public function delete($table, $where, $where_format = null) {
        return 1;
    }
    
    public function getMockData() {
        return $this->mockData;
    }
    
    public function clearMockData() {
        $this->mockData = [];
        $this->insertId = 1;
    }
};

// Add missing WordPress post meta functions
if (!function_exists('update_post_meta')) {
    function update_post_meta($post_id, $meta_key, $meta_value, $prev_value = '') {
        global $mock_post_meta;
        if (!isset($mock_post_meta)) {
            $mock_post_meta = [];
        }
        if (!isset($mock_post_meta[$post_id])) {
            $mock_post_meta[$post_id] = [];
        }
        $mock_post_meta[$post_id][$meta_key] = $meta_value;
        return true;
    }
}

if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id, $key = '', $single = false) {
        global $mock_post_meta;
        if (!isset($mock_post_meta[$post_id])) {
            return $single ? '' : [];
        }
        
        if (empty($key)) {
            return $mock_post_meta[$post_id];
        }
        
        if (isset($mock_post_meta[$post_id][$key])) {
            return $single ? $mock_post_meta[$post_id][$key] : [$mock_post_meta[$post_id][$key]];
        }
        
        return $single ? '' : [];
    }
}

if (!function_exists('add_post_meta')) {
    function add_post_meta($post_id, $meta_key, $meta_value, $unique = false) {
        return update_post_meta($post_id, $meta_key, $meta_value);
    }
}

if (!function_exists('delete_post_meta')) {
    function delete_post_meta($post_id, $meta_key, $meta_value = '') {
        global $mock_post_meta;
        if (isset($mock_post_meta[$post_id][$meta_key])) {
            unset($mock_post_meta[$post_id][$meta_key]);
        }
        return true;
    }
}

if (!function_exists('wp_get_post_terms')) {
    function wp_get_post_terms($post_id, $taxonomy, $args = []) {
        // Mock some terms for testing
        $term1 = new stdClass();
        $term1->term_id = 1;
        $term1->name = 'Test Term 1';
        $term1->slug = 'test-term-1';
        
        $term2 = new stdClass();
        $term2->term_id = 2;
        $term2->name = 'Test Term 2';
        $term2->slug = 'test-term-2';
        
        return [$term1, $term2];
    }
}

if (!function_exists('wp_set_post_terms')) {
    function wp_set_post_terms($post_id, $terms, $taxonomy = 'post_tag', $append = false) {
        return true;
    }
}

if (!function_exists('wp_insert_term')) {
    function wp_insert_term($term_name, $taxonomy, $args = []) {
        $term_id = rand(100, 999);
        return [
            'term_id' => $term_id,
            'term_taxonomy_id' => $term_id
        ];
    }
}

if (!function_exists('wc_get_orders')) {
    function wc_get_orders($args = []) {
        // Mock some orders for testing
        $order1 = new class {
            public function get_items() {
                $item1 = new class {
                    public function get_product_id() {
                        return 123;
                    }
                };
                return [$item1];
            }
        };
        
        return [$order1];
    }
}

if (!function_exists('wc_get_related_products')) {
    function wc_get_related_products($product_id, $limit = 4, $exclude = []) {
        // Mock related product IDs
        $related = [];
        for ($i = 1; $i <= $limit; $i++) {
            if ($product_id + $i !== $product_id) {
                $related[] = $product_id + $i;
            }
        }
        return array_slice($related, 0, $limit);
    }
}

if (!function_exists('wc_get_cart_url')) {
    function wc_get_cart_url() {
        return 'http://example.com/cart/';
    }
}

if (!function_exists('wc_get_checkout_url')) {
    function wc_get_checkout_url() {
        return 'http://example.com/checkout/';
    }
}

if (!function_exists('wc_get_page_permalink')) {
    function wc_get_page_permalink($page) {
        return 'http://example.com/' . $page . '/';
    }
}

if (!function_exists('wp_get_attachment_image_src')) {
    function wp_get_attachment_image_src($attachment_id, $size = 'thumbnail', $icon = false) {
        return [
            'http://example.com/image-' . $attachment_id . '.jpg',
            150,
            150,
            false
        ];
    }
}

if (!function_exists('get_post_thumbnail_id')) {
    function get_post_thumbnail_id($post_id = null) {
        return 123; // Mock attachment ID
    }
}

// Mock WooCommerce cart functionality
if (!class_exists('WC_Cart')) {
    class WC_Cart {
        private $cart_contents = [];
        private $removed_items = [];
        
        public function add_to_cart($product_id, $quantity = 1, $variation_id = 0, $variation = [], $cart_item_data = []) {
            $cart_item_key = md5($product_id . $variation_id . serialize($variation));
            
            if (isset($this->cart_contents[$cart_item_key])) {
                $this->cart_contents[$cart_item_key]['quantity'] += $quantity;
            } else {
                $this->cart_contents[$cart_item_key] = [
                    'product_id' => $product_id,
                    'variation_id' => $variation_id,
                    'variation' => $variation,
                    'quantity' => $quantity,
                    'data' => wc_get_product($variation_id ?: $product_id),
                    'line_total' => 99.99 * $quantity,
                    'line_subtotal' => 99.99 * $quantity
                ];
            }
            
            return $cart_item_key;
        }
        
        public function get_cart() {
            return $this->cart_contents;
        }
        
        public function get_cart_item($cart_item_key) {
            return isset($this->cart_contents[$cart_item_key]) ? $this->cart_contents[$cart_item_key] : false;
        }
        
        public function set_quantity($cart_item_key, $quantity = 1) {
            if (isset($this->cart_contents[$cart_item_key])) {
                if ($quantity <= 0) {
                    $this->remove_cart_item($cart_item_key);
                } else {
                    $this->cart_contents[$cart_item_key]['quantity'] = $quantity;
                    $this->cart_contents[$cart_item_key]['line_total'] = 99.99 * $quantity;
                    $this->cart_contents[$cart_item_key]['line_subtotal'] = 99.99 * $quantity;
                }
                return true;
            }
            return false;
        }
        
        public function remove_cart_item($cart_item_key) {
            if (isset($this->cart_contents[$cart_item_key])) {
                $this->removed_items[$cart_item_key] = $this->cart_contents[$cart_item_key];
                unset($this->cart_contents[$cart_item_key]);
                return true;
            }
            return false;
        }
        
        public function empty_cart() {
            $this->cart_contents = [];
            $this->removed_items = [];
        }
        
        public function get_cart_contents_count() {
            return array_sum(array_column($this->cart_contents, 'quantity'));
        }
        
        public function get_cart_subtotal() {
            return '$' . number_format(array_sum(array_column($this->cart_contents, 'line_subtotal')), 2);
        }
        
        public function get_cart_total() {
            return '$' . number_format(array_sum(array_column($this->cart_contents, 'line_total')), 2);
        }
        
        public function get_cart_contents_total() {
            return array_sum(array_column($this->cart_contents, 'line_total'));
        }
        
        public function get_cart_tax() {
            return '$0.00';
        }
        
        public function get_shipping_total() {
            return '$0.00';
        }
        
        public function needs_shipping() {
            return false;
        }
        
        public function is_empty() {
            return empty($this->cart_contents);
        }
        
        public function get_product_subtotal($product, $quantity) {
            return '$' . number_format($product->get_price() * $quantity, 2);
        }
    }
}

// Mock WooCommerce session handler
if (!class_exists('WC_Session_Handler')) {
    class WC_Session_Handler {
        private $session_data = [];
        
        public function init() {
            // Mock initialization - always return true
            return true;
        }
        
        public function get($key, $default = null) {
            return isset($this->session_data[$key]) ? $this->session_data[$key] : $default;
        }
        
        public function set($key, $value) {
            $this->session_data[$key] = $value;
        }
        
        public function __get($key) {
            return $this->get($key);
        }
        
        public function __set($key, $value) {
            $this->set($key, $value);
        }
        
        public function get_session_cookie() {
            return 'test_session_' . time();
        }
        
        public function has_session() {
            return true;
        }
        
        public function set_customer_session_cookie($set) {
            // Mock method
        }
        
        public function cleanup_sessions() {
            // Mock cleanup
        }
    }
}

// Update WC_Product to support more cart functionality
if (!class_exists('WC_Product_Extended')) {
    class WC_Product_Extended extends WC_Product {
        public function is_purchasable() {
            return $this->id > 0;
        }
        
        public function is_in_stock() {
            return true;
        }
        
        public function get_stock_quantity() {
            return 100;
        }
        
        public function has_enough_stock($quantity) {
            return $this->get_stock_quantity() >= $quantity;
        }
        
        public function get_upsell_ids() {
            return [$this->id + 10, $this->id + 11];
        }
        
        public function get_cross_sell_ids() {
            return [$this->id + 20, $this->id + 21];
        }
    }
}

// Override the original wc_get_product function
if (!function_exists('wc_get_product_extended')) {
    function wc_get_product_extended($product_id = false) {
        if (!$product_id || $product_id === 99999) return false;
        return new WC_Product_Extended($product_id);
    }
    
    // Override the original function using runkit if available, otherwise keep original
    $original_function = 'wc_get_product';
    if (function_exists('runkit_function_redefine')) {
        runkit_function_redefine($original_function, '$product_id = false', 'return wc_get_product_extended($product_id);');
    }
}

// Mock WooCommerce core class
if (!class_exists('WC_Mock_Core')) {
    class WC_Mock_Core {
        public $cart;
        public $session;
        
        public function __construct() {
            $this->cart = new WC_Cart();
            $this->session = new WC_Session_Handler();
            // Initialize session immediately
            $this->session->init();
        }
        
        public function __get($prop) {
            // Ensure cart and session are always available
            if ($prop === 'cart' && ($this->cart === null)) {
                $this->cart = new WC_Cart();
            }
            if ($prop === 'session' && ($this->session === null)) {
                $this->session = new WC_Session_Handler();
                $this->session->init();
            }
            
            if (property_exists($this, $prop)) {
                return $this->$prop;
            }
            return null;
        }
        
        public function __set($prop, $value) {
            $this->$prop = $value;
        }
    }
}

// Mock WooCommerce global
if (!function_exists('WC')) {
    function WC() {
        static $wc = null;
        if ($wc === null) {
            $wc = new WC_Mock_Core();
        }
        
        // Always ensure cart and session are available
        if ($wc->cart === null) {
            $wc->cart = new WC_Cart();
        }
        if ($wc->session === null) {
            $wc->session = new WC_Session_Handler();
            $wc->session->init();
        }
        
        return $wc;
    }
}

// Mock rest_get_server function
if (!function_exists('rest_get_server')) {
    function rest_get_server() {
        static $server = null;
        if ($server === null) {
            $server = new WP_REST_Server();
        }
        return $server;
    }
}

// Mock wp_convert_hr_to_bytes function
if (!function_exists('wp_convert_hr_to_bytes')) {
    function wp_convert_hr_to_bytes($value) {
        $value = strtolower(trim($value));
        $bytes = (int) $value;
        
        if (false !== strpos($value, 'g')) {
            $bytes *= 1024 * 1024 * 1024;
        } elseif (false !== strpos($value, 'm')) {
            $bytes *= 1024 * 1024;
        } elseif (false !== strpos($value, 'k')) {
            $bytes *= 1024;
        }
        
        return $bytes;
    }
}

// Create namespace-specific version
eval('
namespace WooAiAssistant\Setup;
function wp_convert_hr_to_bytes($value) {
    return \wp_convert_hr_to_bytes($value);
}
');

// Mock YITH Wishlist functions for testing
if (!function_exists('YITH_WCWL')) {
    function YITH_WCWL() {
        return new class {
            public function add($product_id) {
                return true;
            }
            
            public function remove($product_id) {
                return true;
            }
            
            public function get_products($args = []) {
                return [
                    ['prod_id' => 123],
                    ['prod_id' => 456]
                ];
            }
        };
    }
}

// Mock additional WordPress functions for ActionEndpoint
if (!function_exists('wp_safe_redirect')) {
    function wp_safe_redirect($location, $status = 302) {
        return true;
    }
}

if (!function_exists('wp_redirect')) {
    function wp_redirect($location, $status = 302) {
        return true;
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($string, $remove_breaks = false) {
        return strip_tags($string);
    }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post($data) {
        return strip_tags($data, '<p><br><strong><em><ul><ol><li>');
    }
}

// Mock session functions
if (!function_exists('session_start')) {
    function session_start() {
        return true;
    }
}

if (!function_exists('session_status')) {
    function session_status() {
        return PHP_SESSION_ACTIVE;
    }
}

if (!defined('PHP_SESSION_ACTIVE')) {
    define('PHP_SESSION_ACTIVE', 2);
}

// Mock WordPress nonce functions for security testing
if (!function_exists('wp_nonce_url')) {
    function wp_nonce_url($actionurl, $action = -1, $name = '_wpnonce') {
        return $actionurl . '?' . $name . '=test_nonce_' . $action;
    }
}

if (!function_exists('wp_nonce_field')) {
    function wp_nonce_field($action = -1, $name = "_wpnonce", $referer = true, $echo = true) {
        $html = '<input type="hidden" id="' . $name . '" name="' . $name . '" value="test_nonce_' . $action . '" />';
        if ($echo) {
            echo $html;
        }
        return $html;
    }
}

if (!function_exists('esc_like')) {
    function esc_like($text) {
        return addcslashes($text, '_%\\');
    }
}

// Add missing WordPress functions that cause test failures
if (!function_exists('esc_sql')) {
    function esc_sql($sql) {
        return addslashes($sql);
    }
}

if (!function_exists('is_multisite')) {
    function is_multisite() {
        return false;
    }
}

if (!function_exists('wp_timezone_string')) {
    function wp_timezone_string() {
        return 'UTC';
    }
}

if (!function_exists('get_the_title')) {
    function get_the_title($post = 0) {
        if (is_object($post) && isset($post->post_title)) {
            return $post->post_title;
        }
        return 'Test Title ' . $post;
    }
}

if (!function_exists('wp_doing_ajax')) {
    function wp_doing_ajax() {
        return defined('DOING_AJAX') && DOING_AJAX;
    }
}

if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url, $component = -1) {
        return parse_url($url, $component);
    }
}

// Mock WP_Scripts class if not exists
if (!class_exists('WP_Scripts')) {
    class WP_Scripts {
        public $registered = [];
        public $queue = [];
        
        public function add($handle, $src, $deps = [], $ver = false, $args = null) {
            $this->registered[$handle] = compact('handle', 'src', 'deps', 'ver', 'args');
            return true;
        }
        
        public function enqueue($handle) {
            $this->queue[] = $handle;
            return true;
        }
        
        public function localize($handle, $object_name, $data) {
            // Mock localize script
            return true;
        }
        
        public function get_data($handle, $key) {
            return isset($this->registered[$handle]) ? '' : false;
        }
    }
}

// Mock WP_Styles class if not exists
if (!class_exists('WP_Styles')) {
    class WP_Styles {
        public $registered = [];
        public $queue = [];
        
        public function add($handle, $src, $deps = [], $ver = false, $media = 'all') {
            $this->registered[$handle] = compact('handle', 'src', 'deps', 'ver', 'media');
            return true;
        }
        
        public function enqueue($handle) {
            $this->queue[] = $handle;
            return true;
        }
    }
}

// Define wpdb constants
if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}
if (!defined('ARRAY_N')) {
    define('ARRAY_N', 'ARRAY_N');
}

// Mock wpdb class if not exists
if (!class_exists('wpdb')) {
    class wpdb {
        public $prefix = 'wp_';
        public $last_error = '';
        public $num_rows = 0;
        public $insert_id = 0;
        public $last_query = '';
        public $queries = [];
        // Store mock data persistently during test run
        private static $mock_conversations = [];
        private static $mock_messages = [];
        private static $last_insert_id = 0;
        
        public function get_results($query, $output = OBJECT) {
            $this->last_query = $query;
            // Return mock results based on query
            if (strpos($query, 'woo_ai_conversations') !== false) {
                return $this->getConversationsFromQuery($query);
            }
            if (strpos($query, 'woo_ai_messages') !== false) {
                return $this->getMessagesFromQuery($query);
            }
            return [];
        }
        
        public function get_row($query, $output = OBJECT, $offset = 0) {
            $results = $this->get_results($query, $output);
            return isset($results[$offset]) ? $results[$offset] : null;
        }
        
        public function get_var($query, $column_offset = 0, $row_offset = 0) {
            $this->last_query = $query;
            if (strpos($query, 'COUNT') !== false) {
                if (strpos($query, 'woo_ai_conversations') !== false) {
                    return count(self::$mock_conversations);
                }
                if (strpos($query, 'woo_ai_messages') !== false) {
                    return count(self::$mock_messages);
                }
                return 0;
            }
            return 0;
        }
        
        public function insert($table, $data, $format = null) {
            self::$last_insert_id++;
            $this->insert_id = self::$last_insert_id;
            
            // Store data in appropriate mock storage
            if (strpos($table, 'woo_ai_conversations') !== false) {
                $data['id'] = $this->insert_id;
                self::$mock_conversations[] = (object)$data;
            } elseif (strpos($table, 'woo_ai_messages') !== false) {
                $data['id'] = $this->insert_id;
                self::$mock_messages[] = (object)$data;
            }
            
            return 1;
        }
        
        public function update($table, $data, $where, $format = null, $where_format = null) {
            // Update mock data
            if (strpos($table, 'woo_ai_conversations') !== false) {
                foreach (self::$mock_conversations as &$conv) {
                    $match = true;
                    foreach ($where as $key => $value) {
                        if (!isset($conv->$key) || $conv->$key != $value) {
                            $match = false;
                            break;
                        }
                    }
                    if ($match) {
                        foreach ($data as $key => $value) {
                            $conv->$key = $value;
                        }
                    }
                }
            }
            return 1;
        }
        
        public function delete($table, $where, $where_format = null) {
            // Delete from mock data
            if (strpos($table, 'woo_ai_conversations') !== false) {
                self::$mock_conversations = array_filter(self::$mock_conversations, function($conv) use ($where) {
                    foreach ($where as $key => $value) {
                        if (!isset($conv->$key) || $conv->$key != $value) {
                            return true; // Keep this item
                        }
                    }
                    return false; // Delete this item
                });
                self::$mock_conversations = array_values(self::$mock_conversations);
            } elseif (strpos($table, 'woo_ai_messages') !== false) {
                self::$mock_messages = array_filter(self::$mock_messages, function($msg) use ($where) {
                    foreach ($where as $key => $value) {
                        if (!isset($msg->$key) || $msg->$key != $value) {
                            return true;
                        }
                    }
                    return false;
                });
                self::$mock_messages = array_values(self::$mock_messages);
            }
            return 1;
        }
        
        public function prepare($query, ...$args) {
            if (empty($args)) {
                return $query;
            }
            return vsprintf(str_replace(['%s', '%d', '%f'], ['\'%s\'', '%d', '%f'], $query), $args);
        }
        
        public function query($query) {
            $this->last_query = $query;
            $this->queries[] = $query;
            return true;
        }
        
        public function esc_like($text) {
            return addcslashes($text, '_%\\');
        }
        
        public function get_charset_collate() {
            return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        }
        
        public function timer_start() {
            return true;
        }
        
        public function timer_stop() {
            return 0.001;
        }
        
        private function getConversationsFromQuery($query) {
            $results = [];
            foreach (self::$mock_conversations as $conv) {
                $match = true;
                
                // Check for WHERE conditions - handle AND conditions properly
                if (strpos($query, 'WHERE') !== false) {
                    // Check conversation_id
                    if (preg_match('/conversation_id\s*=\s*[\'"]([^\'"]+)[\'"]/', $query, $matches)) {
                        if (!isset($conv->conversation_id) || $conv->conversation_id != $matches[1]) {
                            $match = false;
                        }
                    }
                    // Check user_id (including NULL checks)
                    if (preg_match('/user_id\s*=\s*(\d+)/', $query, $matches)) {
                        if (!isset($conv->user_id) || $conv->user_id != $matches[1]) {
                            $match = false;
                        }
                    }
                    if (strpos($query, 'user_id IS NULL') !== false) {
                        if (isset($conv->user_id) && $conv->user_id !== null) {
                            $match = false;
                        }
                    }
                    // Check session_id
                    if (preg_match('/session_id\s*=\s*[\'"]([^\'"]+)[\'"]/', $query, $matches)) {
                        if (!isset($conv->session_id) || $conv->session_id != $matches[1]) {
                            $match = false;
                        }
                    }
                    // Check status
                    if (preg_match('/status\s*=\s*[\'"]([^\'"]+)[\'"]/', $query, $matches)) {
                        if (!isset($conv->status) || $conv->status != $matches[1]) {
                            $match = false;
                        }
                    }
                }
                
                if ($match) {
                    $results[] = $conv;
                }
            }
            
            // Handle LIMIT
            if (preg_match('/LIMIT\s+(\d+)/', $query, $matches)) {
                $results = array_slice($results, 0, (int)$matches[1]);
            }
            
            return $results;
        }
        
        private function getMessagesFromQuery($query) {
            $results = [];
            foreach (self::$mock_messages as $msg) {
                $match = true;
                
                // Check for WHERE conditions
                if (preg_match('/WHERE.*conversation_id\s*=\s*[\'"]([^\'"]+)[\'"]/', $query, $matches)) {
                    if ($msg->conversation_id != $matches[1]) {
                        $match = false;
                    }
                }
                
                if ($match) {
                    $results[] = $msg;
                }
            }
            
            // Handle ORDER BY
            if (strpos($query, 'ORDER BY created_at ASC') !== false) {
                usort($results, function($a, $b) {
                    return strcmp($a->created_at, $b->created_at);
                });
            }
            
            return $results;
        }
        
        // Clear all mock data (useful for test setup/teardown)
        public function clearMockData() {
            self::$mock_conversations = [];
            self::$mock_messages = [];
            self::$last_insert_id = 0;
        }
    }
}

// Create global wpdb instance
global $wpdb;
if (!isset($wpdb)) {
    $wpdb = new wpdb();
}

// Define namespace functions using eval (required for runtime namespace function creation)
if (!function_exists('WooAiAssistant\Chatbot\wp_rand')) {
    eval('
    namespace WooAiAssistant\Chatbot;
    function wp_rand($min = 0, $max = 0) {
        if ($max == 0) {
            return rand();
        }
        return rand($min, $max);
    }
    ');
}

if (!function_exists('WooAiAssistant\Chatbot\wp_get_document_title')) {
    eval('
    namespace WooAiAssistant\Chatbot;
    function wp_get_document_title() {
        return "Test Page Title";
    }
    ');
}

if (!function_exists('WooAiAssistant\Setup\wc_terms_and_conditions_page_id')) {
    eval('
    namespace WooAiAssistant\Setup;
    function wc_terms_and_conditions_page_id() {
        return 1;
    }
    ');
}

// Set up basic PHP environment
date_default_timezone_set('UTC');

// Load test helpers
require_once __DIR__ . '/Helpers/MockHelpers.php';
require_once __DIR__ . '/Defaults/TestDefaults.php';

// Load enhanced test bootstrap after all WordPress functions are available
require_once __DIR__ . '/TestBootstrap.php';

echo "PHPUnit Bootstrap loaded successfully.\n";