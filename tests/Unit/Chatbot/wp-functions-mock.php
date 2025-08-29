<?php
/**
 * WordPress Functions Mock
 *
 * Mock implementations of WordPress functions for unit testing
 * without requiring WordPress core.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Mocks
 * @since 1.0.0
 * @author Claude Code Assistant
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/');
}

// Mock cache storage
global $wp_mock_cache;
$wp_mock_cache = [];

// Mock WordPress functions
if (!function_exists('wp_cache_get')) {
    function wp_cache_get($key, $group = '') {
        global $wp_mock_cache;
        $cache_key = $group . ':' . $key;
        return isset($wp_mock_cache[$cache_key]) ? $wp_mock_cache[$cache_key] : false;
    }
}

if (!function_exists('wp_cache_set')) {
    function wp_cache_set($key, $data, $group = '', $expire = 0) {
        global $wp_mock_cache;
        $cache_key = $group . ':' . $key;
        $wp_mock_cache[$cache_key] = $data;
        return true;
    }
}

if (!function_exists('wp_cache_delete')) {
    function wp_cache_delete($key, $group = '') {
        global $wp_mock_cache;
        $cache_key = $group . ':' . $key;
        unset($wp_mock_cache[$cache_key]);
        return true;
    }
}

if (!function_exists('wp_cache_flush')) {
    function wp_cache_flush() {
        global $wp_mock_cache;
        $wp_mock_cache = [];
        return true;
    }
}

// Mock transient storage
global $wp_mock_transients;
$wp_mock_transients = [];

if (!function_exists('get_transient')) {
    function get_transient($transient) {
        global $wp_mock_transients;
        return isset($wp_mock_transients[$transient]) ? $wp_mock_transients[$transient] : false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration = 0) {
        global $wp_mock_transients;
        $wp_mock_transients[$transient] = $value;
        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($transient) {
        global $wp_mock_transients;
        unset($wp_mock_transients[$transient]);
        return true;
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

if (!function_exists('wp_die')) {
    function wp_die($message = '', $title = '', $args = array()) {
        throw new Exception($message);
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability, $object_id = null) {
        return true; // Mock as having permission
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action = -1) {
        return true; // Mock as valid nonce
    }
}

if (!function_exists('error_log')) {
    function error_log($message, $message_type = 0, $destination = null, $extra_headers = null) {
        // Mock error logging
        return true;
    }
}

if (!function_exists('do_action')) {
    function do_action($hook_name, ...$arg) {
        // Mock action execution
        return null;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($hook_name, $value, ...$args) {
        // Mock filter application - return original value
        return $value;
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

// Mock WP_Error class
if (!class_exists('WP_Error')) {
    class WP_Error {
        public $errors = array();
        public $error_data = array();

        public function __construct($code = '', $message = '', $data = '') {
            if (empty($code)) {
                return;
            }

            $this->add($code, $message, $data);
        }

        public function add($code, $message, $data = '') {
            $this->errors[$code][] = $message;
            if (!empty($data)) {
                $this->error_data[$code] = $data;
            }
        }

        public function get_error_code() {
            $codes = array_keys($this->errors);
            if (empty($codes)) {
                return '';
            }

            return $codes[0];
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

        public function get_error_messages($code = '') {
            if (empty($code)) {
                $all_messages = array();
                foreach ((array) $this->errors as $code => $messages) {
                    $all_messages = array_merge($all_messages, $messages);
                }

                return $all_messages;
            }

            if (isset($this->errors[$code])) {
                return $this->errors[$code];
            } else {
                return array();
            }
        }
    }
}

// Mock global $wpdb
global $wpdb;
if (!isset($wpdb)) {
    $wpdb = new stdClass();
    $wpdb->prefix = 'wp_';
    $wpdb->last_error = '';
    $wpdb->insert_id = 1;
    
    $wpdb->prepare = function($query, ...$args) {
        return vsprintf(str_replace('%s', "'%s'", $query), $args);
    };
    
    $wpdb->get_results = function($query) {
        // Return mock data for different queries
        if (stripos($query, 'woo_ai_conversations') !== false) {
            // Check if it's a specific test query
            if (stripos($query, 'test_') !== false) {
                return [
                    (object) ['id' => 1, 'user_id' => 1, 'message' => 'test_message_1', 'response' => 'test_response_1', 'created_at' => '2024-01-01 00:00:00', 'status' => 'active', 'rating' => 5, 'session_id' => 'test_session_1'],
                    (object) ['id' => 2, 'user_id' => 1, 'message' => 'test_message_2', 'response' => 'test_response_2', 'created_at' => '2024-01-01 00:01:00', 'status' => 'active', 'rating' => 4, 'session_id' => 'test_session_1'],
                    (object) ['id' => 3, 'user_id' => 1, 'message' => 'test_message_3', 'response' => 'test_response_3', 'created_at' => '2024-01-01 00:02:00', 'status' => 'active', 'rating' => 5, 'session_id' => 'test_session_2']
                ];
            }
            return [
                (object) ['id' => 1, 'user_id' => 1, 'message' => 'Test message', 'created_at' => '2024-01-01 00:00:00']
            ];
        } elseif (stripos($query, 'woo_ai_knowledge_base') !== false) {
            // Check if it's a specific test query
            if (stripos($query, 'test_') !== false) {
                return [
                    (object) ['id' => 1, 'content_type' => 'product', 'title' => 'test_product_1', 'content' => 'Test product content 1', 'embedding' => null, 'post_id' => 1, 'updated_at' => '2024-01-01 00:00:00', 'status' => 'active'],
                    (object) ['id' => 2, 'content_type' => 'product', 'title' => 'test_product_2', 'content' => 'Test product content 2', 'embedding' => null, 'post_id' => 2, 'updated_at' => '2024-01-01 00:00:00', 'status' => 'active'],
                    (object) ['id' => 3, 'content_type' => 'page', 'title' => 'test_page_1', 'content' => 'Test page content', 'embedding' => null, 'post_id' => 3, 'updated_at' => '2024-01-01 00:00:00', 'status' => 'active']
                ];
            }
            return [
                (object) ['id' => 1, 'title' => 'Test KB Entry', 'content' => 'Test content', 'type' => 'product']
            ];
        }
        return array(); // Default empty results
    };
    
    $wpdb->get_var = function($query) {
        // Return mock counts for statistics queries
        if (stripos($query, 'COUNT') !== false) {
            return '42'; // Mock count
        }
        return null; // Mock null result
    };
    
    $wpdb->insert = function($table, $data, $format = null) {
        return 1; // Mock successful insert
    };
    
    $wpdb->update = function($table, $data, $where, $format = null, $where_format = null) {
        return 1; // Mock successful update
    };
    
    $wpdb->delete = function($table, $where, $where_format = null) {
        return 1; // Mock successful delete
    };
    
    $wpdb->query = function($query) {
        // Mock successful query execution for CREATE TABLE, etc.
        return true;
    };
}

// Define WordPress constants if not already defined
if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

if (!defined('WEEK_IN_SECONDS')) {
    define('WEEK_IN_SECONDS', 604800);
}

if (!defined('MONTH_IN_SECONDS')) {
    define('MONTH_IN_SECONDS', 2592000);
}

if (!defined('YEAR_IN_SECONDS')) {
    define('YEAR_IN_SECONDS', 31536000);
}

// Additional WordPress functions
if (!function_exists('get_site_url')) {
    function get_site_url($blog_id = null, $path = '', $scheme = null) {
        return 'https://example.com' . $path;
    }
}

if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script($handle, $src = '', $deps = array(), $ver = false, $in_footer = false) {
        global $wp_scripts;
        if (!isset($wp_scripts)) {
            $wp_scripts = array();
        }
        $wp_scripts[$handle] = array(
            'src' => $src,
            'deps' => $deps,
            'ver' => $ver,
            'in_footer' => $in_footer
        );
        return true;
    }
}

if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style($handle, $src = '', $deps = array(), $ver = false, $media = 'all') {
        global $wp_styles;
        if (!isset($wp_styles)) {
            $wp_styles = array();
        }
        $wp_styles[$handle] = array(
            'src' => $src,
            'deps' => $deps,
            'ver' => $ver,
            'media' => $media
        );
        return true;
    }
}

if (!function_exists('wp_register_script')) {
    function wp_register_script($handle, $src, $deps = array(), $ver = false, $in_footer = false) {
        return wp_enqueue_script($handle, $src, $deps, $ver, $in_footer);
    }
}

if (!function_exists('wp_register_style')) {
    function wp_register_style($handle, $src, $deps = array(), $ver = false, $media = 'all') {
        return wp_enqueue_style($handle, $src, $deps, $ver, $media);
    }
}

if (!function_exists('wp_localize_script')) {
    function wp_localize_script($handle, $object_name, $l10n) {
        return true;
    }
}

if (!function_exists('plugins_url')) {
    function plugins_url($path = '', $plugin = '') {
        return 'https://example.com/wp-content/plugins/' . ltrim($path, '/');
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) {
        return 'https://example.com/wp-content/plugins/woo-ai-assistant/';
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return '/Applications/MAMP/htdocs/wp/wp-content/plugins/woo-ai-assistant/';
    }
}

if (!function_exists('register_rest_route')) {
    function register_rest_route($namespace, $route, $args = array()) {
        global $wp_rest_routes;
        if (!isset($wp_rest_routes)) {
            $wp_rest_routes = array();
        }
        
        $full_route = '/' . trim($namespace, '/') . '/' . trim($route, '/');
        $wp_rest_routes[$full_route] = $args;
        return true;
    }
}

if (!function_exists('rest_ensure_response')) {
    function rest_ensure_response($response) {
        if (is_wp_error($response)) {
            return $response;
        }
        
        if ($response instanceof WP_REST_Response) {
            return $response;
        }
        
        return new WP_REST_Response($response);
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id() {
        return 1; // Mock user ID
    }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in() {
        return true; // Mock as logged in
    }
}

if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user() {
        $user = new stdClass();
        $user->ID = 1;
        $user->user_login = 'testuser';
        $user->user_email = 'test@example.com';
        return $user;
    }
}

if (!function_exists('get_post_type')) {
    function get_post_type($post = null) {
        if ($post === null || $post === 123) {
            return 'product'; // Mock product post type
        }
        return 'post';
    }
}

if (!function_exists('wp_mail')) {
    function wp_mail($to, $subject, $message, $headers = '', $attachments = array()) {
        return true; // Mock successful email
    }
}

if (!function_exists('home_url')) {
    function home_url($path = '', $scheme = null) {
        return 'https://example.com' . $path;
    }
}

// Mock WP_REST_Response class
if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        public $data;
        public $status;
        public $headers;

        public function __construct($data = null, $status = 200, $headers = array()) {
            $this->data = $data;
            $this->status = $status;
            $this->headers = $headers;
        }

        public function get_data() {
            return $this->data;
        }

        public function get_status() {
            return $this->status;
        }

        public function get_headers() {
            return $this->headers;
        }

        public function set_data($data) {
            $this->data = $data;
        }

        public function set_status($code) {
            $this->status = $code;
        }

        public function header($key, $value, $replace = true) {
            $this->headers[$key] = $value;
        }
    }
}

// Mock WP_REST_Request class
if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request {
        private $params = array();
        private $method = 'GET';

        public function __construct($method = 'GET', $route = '', $attributes = array()) {
            $this->method = $method;
        }

        public function get_param($key) {
            return isset($this->params[$key]) ? $this->params[$key] : null;
        }

        public function get_params() {
            return $this->params;
        }

        public function set_param($key, $value) {
            $this->params[$key] = $value;
        }

        public function get_method() {
            return $this->method;
        }

        public function set_method($method) {
            $this->method = $method;
        }
    }
}