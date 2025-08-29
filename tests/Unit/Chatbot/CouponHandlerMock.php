<?php
/**
 * WordPress Functions Mock for CouponHandler Testing
 *
 * Provides simplified mock implementations of WordPress and WooCommerce functions
 * needed specifically for testing CouponHandler in isolation.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Unit\Chatbot
 * @since 1.0.0
 */

// Define constants
if (!defined('HOUR_IN_SECONDS')) define('HOUR_IN_SECONDS', 3600);
if (!defined('DAY_IN_SECONDS')) define('DAY_IN_SECONDS', 86400);
if (!defined('ABSPATH')) define('ABSPATH', '/tmp/');

// Mock WP_Error class
if (!class_exists('WP_Error')) {
    class WP_Error {
        private $errors = [];
        
        public function __construct($code = '', $message = '', $data = '') {
            if ($code) {
                $this->errors[$code][] = $message;
            }
        }
        
        public function get_error_code() {
            return array_keys($this->errors)[0] ?? '';
        }
        
        public function get_error_message() {
            $code = $this->get_error_code();
            return $this->errors[$code][0] ?? '';
        }
    }
}

// Mock WC_Coupon class
if (!class_exists('WC_Coupon')) {
    class WC_Coupon {
        private $id = 0;
        private $code = '';
        public $data = []; // Make public for easy access in tests
        
        public function __construct($code = '') {
            // Check if we have a mock coupon stored globally
            if ($code && isset($GLOBALS['mock_coupons'][$code])) {
                $mockCoupon = $GLOBALS['mock_coupons'][$code];
                $this->id = $mockCoupon->get_id();
                $this->code = $mockCoupon->get_code();
                $this->data = $mockCoupon->data;
                return;
            }
            
            $this->code = $code;
            $this->id = $code ? rand(1, 1000) : 0;
            
            // Mock default coupon data
            $this->data = [
                'discount_type' => 'percent',
                'amount' => 10,
                'individual_use' => false,
                'product_ids' => [],
                'excluded_product_ids' => [],
                'usage_limit' => 0,
                'usage_limit_per_user' => 0,
                'usage_count' => 0,
                'date_expires' => null,
                'minimum_amount' => 0,
                'maximum_amount' => 0,
                'product_categories' => [],
                'excluded_product_categories' => [],
                'description' => 'Test coupon description',
                'status' => 'publish'
            ];
        }
        
        public function get_id() { return $this->id; }
        public function get_code() { return $this->code; }
        public function get_discount_type() { return $this->data['discount_type']; }
        public function get_amount() { return floatval($this->data['amount']); }
        public function get_individual_use() { return $this->data['individual_use']; }
        public function get_product_ids() { return $this->data['product_ids']; }
        public function get_excluded_product_ids() { return $this->data['excluded_product_ids']; }
        public function get_usage_limit() { return intval($this->data['usage_limit']); }
        public function get_usage_limit_per_user() { return intval($this->data['usage_limit_per_user']); }
        public function get_usage_count() { return intval($this->data['usage_count']); }
        public function get_minimum_amount() { return floatval($this->data['minimum_amount']); }
        public function get_maximum_amount() { return floatval($this->data['maximum_amount']); }
        public function get_product_categories() { return $this->data['product_categories']; }
        public function get_excluded_product_categories() { return $this->data['excluded_product_categories']; }
        public function get_description() { return $this->data['description']; }
        public function get_status() { return $this->data['status']; }
        
        public function get_date_expires() {
            if ($this->data['date_expires']) {
                if ($this->data['date_expires'] instanceof \DateTime) {
                    return $this->data['date_expires'];
                }
                return new \DateTime($this->data['date_expires']);
            }
            return null;
        }
        
        public function set_code($code) { $this->code = $code; }
        public function set_discount_type($type) { $this->data['discount_type'] = $type; }
        public function set_amount($amount) { $this->data['amount'] = $amount; }
        public function set_date_expires($date) { 
            if ($date instanceof \DateTime) {
                $this->data['date_expires'] = $date;
            } else {
                $this->data['date_expires'] = $date ? new \DateTime($date) : null; 
            }
        }
        public function set_usage_limit($limit) { $this->data['usage_limit'] = $limit; }
        public function set_usage_limit_per_user($limit) { $this->data['usage_limit_per_user'] = $limit; }
        public function set_individual_use($value) { $this->data['individual_use'] = $value; }
        public function set_minimum_amount($amount) { $this->data['minimum_amount'] = $amount; }
        
        public function save() { return true; }
    }
}

// Global functions needed by CouponHandler
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags($str));
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id() {
        return 1;
    }
}

if (!function_exists('get_transient')) {
    function get_transient($transient) {
        return $GLOBALS['mock_transients'][$transient] ?? false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration) {
        $GLOBALS['mock_transients'][$transient] = $value;
        return true;
    }
}

if (!function_exists('current_time')) {
    function current_time($type) {
        return $type === 'mysql' ? date('Y-m-d H:i:s') : date('c');
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data);
    }
}

if (!function_exists('wp_generate_password')) {
    function wp_generate_password($length = 12, $special_chars = true) {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        return substr(str_shuffle($chars), 0, $length);
    }
}

if (!function_exists('wp_insert_post')) {
    function wp_insert_post($args) {
        return rand(1000, 9999); // Mock post ID
    }
}

if (!function_exists('update_post_meta')) {
    function update_post_meta($post_id, $meta_key, $meta_value) {
        return true;
    }
}

if (!function_exists('wc_price')) {
    function wc_price($price) {
        return '$' . number_format($price, 2);
    }
}

if (!function_exists('get_posts')) {
    function get_posts($args = []) {
        return [
            (object) ['ID' => 1, 'post_title' => 'SAVE10', 'post_type' => 'shop_coupon'],
            (object) ['ID' => 2, 'post_title' => 'SAVE20', 'post_type' => 'shop_cououn']
        ];
    }
}

if (!function_exists('wp_get_post_terms')) {
    function wp_get_post_terms($post_id, $taxonomy, $args = []) {
        if ($args['fields'] === 'ids') {
            return [1, 2, 3]; // Mock category IDs
        }
        return [];
    }
}

if (!function_exists('wc_get_notices')) {
    function wc_get_notices($type = 'error') {
        return [
            ['notice' => 'Mock WooCommerce error message']
        ];
    }
}

if (!function_exists('wc_clear_notices')) {
    function wc_clear_notices() {
        return true;
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('has_action')) {
    function has_action($tag, $callback = false) {
        return $callback ? 10 : true;
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $args = 1) {
        $GLOBALS['wp_actions'][$hook][] = $callback;
    }
}

if (!function_exists('do_action')) {
    function do_action($tag, ...$args) {
        // Mock action - do nothing
    }
}

// Mock WC() function
if (!function_exists('WC')) {
    function WC() {
        static $wc = null;
        if (!$wc) {
            $wc = new stdClass();
            $wc->cart = new class {
                public function has_discount($code) { 
                    return isset($GLOBALS['applied_coupons']) && in_array($code, $GLOBALS['applied_coupons']); 
                }
                public function apply_coupon($code) { 
                    $GLOBALS['applied_coupons'][] = $code; 
                    return true; 
                }
                public function get_subtotal() { return 100.0; }
                public function get_cart() { return []; }
            };
        }
        return $wc;
    }
}

// Initialize global variables
$GLOBALS['mock_transients'] = [];
$GLOBALS['mock_coupons'] = [];
$GLOBALS['applied_coupons'] = [];
$GLOBALS['wp_actions'] = [];

// Mock wpdb
if (!isset($GLOBALS['wpdb'])) {
    $GLOBALS['wpdb'] = new class {
        public $prefix = 'wp_';
        public $insert_id = 1;
        
        public function insert($table, $data, $format = null) {
            return 1; // Mock successful insert
        }
        
        public function get_var($query) {
            // Mock different responses based on query content
            if (strpos($query, 'COUNT') !== false) {
                return rand(0, 5); // Random count for fraud detection tests
            }
            return 'result';
        }
        
        public function get_results($query, $output = OBJECT) {
            return [];
        }
        
        public function get_row($query, $output = OBJECT) {
            return null;
        }
        
        public function prepare($query, ...$args) {
            return $query; // Simple mock - just return query
        }
        
        public function query($query) {
            return 1; // Mock successful query
        }
        
        public function get_charset_collate() {
            return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        }
    };
}

// Mock dbDelta function
if (!function_exists('dbDelta')) {
    function dbDelta($queries) {
        return ['test_table' => 'Created table test_table'];
    }
}