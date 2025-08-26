<?php
/**
 * Plugin Name: Woo AI Assistant
 * Plugin URI: https://woo-ai-assistant.com
 * Description: AI-powered chatbot for WooCommerce that automatically creates a knowledge base from site content and provides 24/7 customer support with advanced purchase assistance.
 * Version: 1.0.0
 * Author: Claude Code Assistant
 * Author URI: https://claude.ai/code
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woo-ai-assistant
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.4
 * Requires PHP: 8.2
 * Requires Plugins: woocommerce
 * Network: false
 *
 * WooCommerce requires at least: 7.0
 * WooCommerce tested up to: 8.4
 *
 * @package WooAiAssistant
 * @author Giovanni Broegg
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WOO_AI_ASSISTANT_VERSION', '1.0.0');
define('WOO_AI_ASSISTANT_PLUGIN_FILE', __FILE__);
define('WOO_AI_ASSISTANT_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('WOO_AI_ASSISTANT_PLUGIN_DIR_PATH', plugin_dir_path(__FILE__));
define('WOO_AI_ASSISTANT_PLUGIN_DIR_URL', plugin_dir_url(__FILE__));
define('WOO_AI_ASSISTANT_PLUGIN_SLUG', 'woo-ai-assistant');
define('WOO_AI_ASSISTANT_TEXT_DOMAIN', 'woo-ai-assistant');

// Define directory paths
define('WOO_AI_ASSISTANT_SRC_PATH', WOO_AI_ASSISTANT_PLUGIN_DIR_PATH . 'src/');
define('WOO_AI_ASSISTANT_ASSETS_PATH', WOO_AI_ASSISTANT_PLUGIN_DIR_PATH . 'assets/');
define('WOO_AI_ASSISTANT_TEMPLATES_PATH', WOO_AI_ASSISTANT_PLUGIN_DIR_PATH . 'templates/');
define('WOO_AI_ASSISTANT_LANGUAGES_PATH', WOO_AI_ASSISTANT_PLUGIN_DIR_PATH . 'languages/');

// Define URL paths
define('WOO_AI_ASSISTANT_URL', WOO_AI_ASSISTANT_PLUGIN_DIR_URL);
define('WOO_AI_ASSISTANT_ASSETS_URL', WOO_AI_ASSISTANT_PLUGIN_DIR_URL . 'assets/');
define('WOO_AI_ASSISTANT_WIDGET_SRC_URL', WOO_AI_ASSISTANT_PLUGIN_DIR_URL . 'widget-src/');

// Environment-specific constants (for development)
if (!defined('WOO_AI_ASSISTANT_DEBUG')) {
    define('WOO_AI_ASSISTANT_DEBUG', defined('WP_DEBUG') && WP_DEBUG);
}

if (!defined('WOO_AI_ASSISTANT_API_URL')) {
    define('WOO_AI_ASSISTANT_API_URL', 'https://api.woo-ai-assistant.com'); // Production URL
}

if (!defined('WOO_AI_ASSISTANT_USE_DUMMY_DATA')) {
    define('WOO_AI_ASSISTANT_USE_DUMMY_DATA', false);
}

/**
 * Plugin bootstrap function
 * 
 * Handles plugin initialization, dependency checks, and main class instantiation.
 * 
 * @since 1.0.0
 * @return void
 */
function woo_ai_assistant_init() {
    // Check if WordPress is loaded
    if (!function_exists('add_action')) {
        return;
    }

    // Check PHP version
    if (version_compare(PHP_VERSION, '8.2', '<')) {
        add_action('admin_notices', 'woo_ai_assistant_php_version_notice');
        return;
    }

    // Check if WooCommerce is active
    if (!woo_ai_assistant_is_woocommerce_active()) {
        add_action('admin_notices', 'woo_ai_assistant_woocommerce_missing_notice');
        return;
    }

    // Load Composer autoloader
    $autoloader = WOO_AI_ASSISTANT_PLUGIN_DIR_PATH . 'vendor/autoload.php';
    if (file_exists($autoloader)) {
        require_once $autoloader;
    } else {
        // Fallback: manual autoloader if Composer not available
        spl_autoload_register('woo_ai_assistant_autoloader');
    }

    // Initialize the main plugin class
    try {
        \WooAiAssistant\Main::getInstance();
    } catch (Exception $e) {
        if (WOO_AI_ASSISTANT_DEBUG) {
            error_log('Woo AI Assistant initialization error: ' . $e->getMessage());
        }
        add_action('admin_notices', function() use ($e) {
            woo_ai_assistant_error_notice($e->getMessage());
        });
    }
}

/**
 * Manual PSR-4 autoloader fallback
 * 
 * Used when Composer autoloader is not available.
 * 
 * @since 1.0.0
 * @param string $class The fully-qualified class name
 * @return void
 */
function woo_ai_assistant_autoloader($class) {
    // Check if the class belongs to our namespace
    $namespace = 'WooAiAssistant\\';
    if (strpos($class, $namespace) !== 0) {
        return;
    }

    // Remove the namespace prefix
    $relative_class = substr($class, strlen($namespace));

    // Convert namespace separators to directory separators
    $file = WOO_AI_ASSISTANT_SRC_PATH . str_replace('\\', '/', $relative_class) . '.php';

    // Load the file if it exists
    if (file_exists($file)) {
        require_once $file;
    }
}

/**
 * Check if WooCommerce is active
 * 
 * @since 1.0.0
 * @return bool True if WooCommerce is active, false otherwise
 */
function woo_ai_assistant_is_woocommerce_active() {
    // Check if WooCommerce class exists
    if (class_exists('WooCommerce')) {
        return true;
    }

    // Check if WooCommerce plugin is active
    $active_plugins = get_option('active_plugins', array());
    
    if (is_multisite()) {
        $active_plugins = array_merge($active_plugins, array_keys(get_site_option('active_sitewide_plugins', array())));
    }
    
    return in_array('woocommerce/woocommerce.php', $active_plugins, true);
}

/**
 * Display PHP version requirement notice
 * 
 * @since 1.0.0
 * @return void
 */
function woo_ai_assistant_php_version_notice() {
    $message = sprintf(
        /* translators: 1: Required PHP version, 2: Current PHP version */
        esc_html__('Woo AI Assistant requires PHP version %1$s or higher. You are running version %2$s. Please upgrade PHP to use this plugin.', 'woo-ai-assistant'),
        '8.2',
        PHP_VERSION
    );

    printf(
        '<div class="notice notice-error is-dismissible"><p><strong>%s:</strong> %s</p></div>',
        esc_html__('Woo AI Assistant', 'woo-ai-assistant'),
        $message
    );
}

/**
 * Display WooCommerce missing notice
 * 
 * @since 1.0.0
 * @return void
 */
function woo_ai_assistant_woocommerce_missing_notice() {
    $message = esc_html__('Woo AI Assistant requires WooCommerce to be installed and active. Please install and activate WooCommerce first.', 'woo-ai-assistant');
    $install_url = wp_nonce_url(
        add_query_arg(
            array(
                'action' => 'install-plugin',
                'plugin' => 'woocommerce'
            ),
            admin_url('update.php')
        ),
        'install-plugin_woocommerce'
    );

    printf(
        '<div class="notice notice-error is-dismissible"><p><strong>%s:</strong> %s <a href="%s" class="button button-primary">%s</a></p></div>',
        esc_html__('Woo AI Assistant', 'woo-ai-assistant'),
        $message,
        esc_url($install_url),
        esc_html__('Install WooCommerce', 'woo-ai-assistant')
    );
}

/**
 * Display general error notice
 * 
 * @since 1.0.0
 * @param string $message Error message to display
 * @return void
 */
function woo_ai_assistant_error_notice($message) {
    printf(
        '<div class="notice notice-error is-dismissible"><p><strong>%s:</strong> %s</p></div>',
        esc_html__('Woo AI Assistant Error', 'woo-ai-assistant'),
        esc_html($message)
    );
}

/**
 * Plugin activation hook
 * 
 * @since 1.0.0
 * @return void
 */
function woo_ai_assistant_activate() {
    // Load Composer autoloader
    $autoloader = WOO_AI_ASSISTANT_PLUGIN_DIR_PATH . 'vendor/autoload.php';
    if (file_exists($autoloader)) {
        require_once $autoloader;
    } else {
        spl_autoload_register('woo_ai_assistant_autoloader');
    }

    try {
        \WooAiAssistant\Setup\Activator::activate();
    } catch (Exception $e) {
        if (WOO_AI_ASSISTANT_DEBUG) {
            error_log('Woo AI Assistant activation error: ' . $e->getMessage());
        }
        wp_die(
            sprintf(
                /* translators: %s: Error message */
                esc_html__('Woo AI Assistant could not be activated. Error: %s', 'woo-ai-assistant'),
                $e->getMessage()
            ),
            esc_html__('Plugin Activation Error', 'woo-ai-assistant'),
            array('back_link' => true)
        );
    }
}

/**
 * Plugin deactivation hook
 * 
 * @since 1.0.0
 * @return void
 */
function woo_ai_assistant_deactivate() {
    // Load Composer autoloader
    $autoloader = WOO_AI_ASSISTANT_PLUGIN_DIR_PATH . 'vendor/autoload.php';
    if (file_exists($autoloader)) {
        require_once $autoloader;
    } else {
        spl_autoload_register('woo_ai_assistant_autoloader');
    }

    try {
        \WooAiAssistant\Setup\Deactivator::deactivate();
    } catch (Exception $e) {
        if (WOO_AI_ASSISTANT_DEBUG) {
            error_log('Woo AI Assistant deactivation error: ' . $e->getMessage());
        }
    }
}

// Register activation and deactivation hooks
register_activation_hook(__FILE__, 'woo_ai_assistant_activate');
register_deactivation_hook(__FILE__, 'woo_ai_assistant_deactivate');

// Initialize the plugin when WordPress is ready
add_action('plugins_loaded', 'woo_ai_assistant_init', 10);

// Load plugin text domain for translations
add_action('init', function() {
    load_plugin_textdomain(
        'woo-ai-assistant',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );
});