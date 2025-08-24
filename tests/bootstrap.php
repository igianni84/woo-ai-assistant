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

// Load the base WP_UnitTestCase class
require_once __DIR__ . '/WP_UnitTestCase.php';

// Create global WP_UnitTestCase alias
if (!class_exists('WP_UnitTestCase')) {
    class WP_UnitTestCase extends \WooAiAssistant\Tests\WP_UnitTestCase {}
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
        $post = new \stdClass();
        $post->ID = $post_id;
        $post->post_title = 'Test Post ' . $post_id;
        $post->post_content = 'Test content for post ' . $post_id;
        $post->post_status = 'publish';
        $post->post_type = 'post';
        $post->post_date = '2023-01-01 00:00:00';
        $post->post_modified = '2023-01-01 00:00:00';
        $post->post_author = 1;
        $post->post_excerpt = '';
        return $post;
    }
}

if (!function_exists('wp_delete_post')) {
    function wp_delete_post($postid = 0, $force_delete = false) {
        return true;
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

if (!function_exists('has_action')) {
    function has_action($tag, $function_to_check = false) {
        if ($function_to_check) {
            return 10; // Priority level when checking specific callback
        }
        // Return true for hooks that should exist, false otherwise
        $existingHooks = [
            'woo_ai_assistant_content_updated',
            'woo_ai_assistant_bulk_reindex'
        ];
        return in_array($tag, $existingHooks);
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

if (!function_exists('wp_next_scheduled')) {
    function wp_next_scheduled($hook, $args = []) {
        return false;
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
        return sprintf($query, ...$args);
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
        $row = new stdClass();
        $row->id = 1;
        $row->source_type = 'test';
        return $row;
    }
    
    public function get_var($query, $x = 0, $y = 0) {
        // Handle table existence check
        if (preg_match('/SHOW TABLES LIKE/i', $query)) {
            return 'wp_woo_ai_knowledge_base'; // Table exists
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
    
    public function insert($table, $data, $format = null) {
        // Store inserted data for verification
        $id = $this->insertId++;
        $this->mockData[] = array_merge($data, ['id' => $id]);
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

// Set up basic PHP environment
date_default_timezone_set('UTC');

echo "PHPUnit Bootstrap loaded successfully.\n";