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
define('WOO_AI_ASSISTANT_PLUGIN_URL', WOO_AI_ASSISTANT_PLUGIN_DIR_URL); // EMERGENCY FIX: Add missing constant
define('WOO_AI_ASSISTANT_ASSETS_URL', WOO_AI_ASSISTANT_PLUGIN_DIR_URL . 'assets/');
define('WOO_AI_ASSISTANT_WIDGET_SRC_URL', WOO_AI_ASSISTANT_PLUGIN_DIR_URL . 'widget-src/');

// Version management constants
define('WOO_AI_ASSISTANT_DB_VERSION', '1.0.0');
define('WOO_AI_ASSISTANT_MIN_WP_VERSION', '6.0');
define('WOO_AI_ASSISTANT_MIN_WC_VERSION', '7.0');
define('WOO_AI_ASSISTANT_MIN_PHP_VERSION', '8.2');

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

// Development mode constant - Auto-detect development environment
if (!defined('WOO_AI_DEVELOPMENT_MODE')) {
    $isDevelopment = 
        // Check for common development indicators
        (defined('WP_DEBUG') && WP_DEBUG) ||
        (isset($_SERVER['SERVER_NAME']) && (
            strpos($_SERVER['SERVER_NAME'], 'localhost') !== false ||
            strpos($_SERVER['SERVER_NAME'], '127.0.0.1') !== false ||
            strpos($_SERVER['SERVER_NAME'], '.local') !== false ||
            strpos($_SERVER['SERVER_NAME'], '.dev') !== false
        )) ||
        // Check for MAMP/XAMPP/WAMP
        (isset($_SERVER['SERVER_SOFTWARE']) && (
            strpos($_SERVER['SERVER_SOFTWARE'], 'MAMP') !== false ||
            strpos($_SERVER['SERVER_SOFTWARE'], 'XAMPP') !== false ||
            strpos($_SERVER['SERVER_SOFTWARE'], 'WAMP') !== false
        ));

    define('WOO_AI_DEVELOPMENT_MODE', $isDevelopment);
}

/**
 * Get plugin version information
 * 
 * @since 1.0.0
 * @return array Plugin version information
 */
function woo_ai_assistant_get_version_info() {
    return [
        'plugin_version' => WOO_AI_ASSISTANT_VERSION,
        'db_version' => WOO_AI_ASSISTANT_DB_VERSION,
        'min_wp_version' => WOO_AI_ASSISTANT_MIN_WP_VERSION,
        'min_wc_version' => WOO_AI_ASSISTANT_MIN_WC_VERSION,
        'min_php_version' => WOO_AI_ASSISTANT_MIN_PHP_VERSION,
        'current_wp_version' => get_bloginfo('version'),
        'current_php_version' => PHP_VERSION,
        'is_wp_compatible' => version_compare(get_bloginfo('version'), WOO_AI_ASSISTANT_MIN_WP_VERSION, '>='),
        'is_php_compatible' => version_compare(PHP_VERSION, WOO_AI_ASSISTANT_MIN_PHP_VERSION, '>='),
        'is_wc_active' => woo_ai_assistant_is_woocommerce_active(),
    ];
}

/**
 * Check if system meets all plugin requirements
 * 
 * @since 1.0.0
 * @return array Requirements check results
 */
function woo_ai_assistant_check_requirements() {
    $version_info = woo_ai_assistant_get_version_info();
    
    $requirements = [
        'php_version' => [
            'passed' => $version_info['is_php_compatible'],
            'required' => WOO_AI_ASSISTANT_MIN_PHP_VERSION,
            'current' => PHP_VERSION,
            'message' => 'PHP version requirement'
        ],
        'wp_version' => [
            'passed' => $version_info['is_wp_compatible'],
            'required' => WOO_AI_ASSISTANT_MIN_WP_VERSION,
            'current' => get_bloginfo('version'),
            'message' => 'WordPress version requirement'
        ],
        'woocommerce' => [
            'passed' => $version_info['is_wc_active'],
            'required' => 'Active',
            'current' => $version_info['is_wc_active'] ? 'Active' : 'Inactive',
            'message' => 'WooCommerce requirement'
        ]
    ];

    // Check WooCommerce version if active
    if ($version_info['is_wc_active'] && defined('WC_VERSION')) {
        $requirements['wc_version'] = [
            'passed' => version_compare(WC_VERSION, WOO_AI_ASSISTANT_MIN_WC_VERSION, '>='),
            'required' => WOO_AI_ASSISTANT_MIN_WC_VERSION,
            'current' => WC_VERSION,
            'message' => 'WooCommerce version requirement'
        ];
    }

    $requirements['all_passed'] = !in_array(false, array_column($requirements, 'passed'), true);

    return $requirements;
}

/**
 * Handle plugin updates and migrations
 * 
 * @since 1.0.0
 * @return void
 */
function woo_ai_assistant_handle_updates() {
    $installed_version = get_option('woo_ai_assistant_version', '0.0.0');
    $current_version = WOO_AI_ASSISTANT_VERSION;
    
    // Skip if already up to date
    if (version_compare($installed_version, $current_version, '>=')) {
        return;
    }
    
    // Log update process
    if (WOO_AI_ASSISTANT_DEBUG) {
        error_log("Woo AI Assistant: Updating from version {$installed_version} to {$current_version}");
    }
    
    // Perform version-specific updates
    woo_ai_assistant_perform_version_updates($installed_version, $current_version);
    
    // Update stored version
    update_option('woo_ai_assistant_version', $current_version);
    
    // Update DB version if needed
    $installed_db_version = get_option('woo_ai_assistant_db_version', '0.0.0');
    if (version_compare($installed_db_version, WOO_AI_ASSISTANT_DB_VERSION, '<')) {
        woo_ai_assistant_update_database($installed_db_version, WOO_AI_ASSISTANT_DB_VERSION);
        update_option('woo_ai_assistant_db_version', WOO_AI_ASSISTANT_DB_VERSION);
    }
    
    // Clear any caches
    wp_cache_flush();
    
    // Trigger update hook
    do_action('woo_ai_assistant_updated', $installed_version, $current_version);
}

/**
 * Perform version-specific updates
 * 
 * @since 1.0.0
 * @param string $from_version Previous version
 * @param string $to_version New version
 * @return void
 */
function woo_ai_assistant_perform_version_updates($from_version, $to_version) {
    // Example version-specific updates
    
    // if (version_compare($from_version, '1.1.0', '<') && version_compare($to_version, '1.1.0', '>=')) {
    //     // Update logic for version 1.1.0
    //     woo_ai_assistant_update_to_1_1_0();
    // }
    
    // if (version_compare($from_version, '1.2.0', '<') && version_compare($to_version, '1.2.0', '>=')) {
    //     // Update logic for version 1.2.0
    //     woo_ai_assistant_update_to_1_2_0();
    // }
    
    // Allow other plugins to hook into version updates
    do_action('woo_ai_assistant_version_update', $from_version, $to_version);
}

/**
 * Update database schema if needed
 * 
 * @since 1.0.0
 * @param string $from_version Previous DB version
 * @param string $to_version New DB version
 * @return void
 */
function woo_ai_assistant_update_database($from_version, $to_version) {
    // Database update logic would go here
    // This would typically involve running SQL migrations
    
    if (WOO_AI_ASSISTANT_DEBUG) {
        error_log("Woo AI Assistant: Updating database from version {$from_version} to {$to_version}");
    }
    
    // Allow other components to hook into DB updates
    do_action('woo_ai_assistant_db_update', $from_version, $to_version);
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
    if (version_compare(PHP_VERSION, WOO_AI_ASSISTANT_MIN_PHP_VERSION, '<')) {
        add_action('admin_notices', 'woo_ai_assistant_php_version_notice');
        return;
    }
    
    // Check WordPress version
    if (version_compare(get_bloginfo('version'), WOO_AI_ASSISTANT_MIN_WP_VERSION, '<')) {
        add_action('admin_notices', 'woo_ai_assistant_wp_version_notice');
        return;
    }

    // Check if WooCommerce is active and meets version requirements
    if (!woo_ai_assistant_is_woocommerce_active()) {
        add_action('admin_notices', 'woo_ai_assistant_woocommerce_missing_notice');
        return;
    }

    // Check WooCommerce version if available
    if (defined('WC_VERSION') && version_compare(WC_VERSION, WOO_AI_ASSISTANT_MIN_WC_VERSION, '<')) {
        add_action('admin_notices', 'woo_ai_assistant_wc_version_notice');
        return;
    }

    // Handle plugin updates
    woo_ai_assistant_handle_updates();

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
        
        // DEVELOPMENT FALLBACK: Try to load widget directly if main initialization fails
        if (WOO_AI_DEVELOPMENT_MODE && !is_admin()) {
            add_action('init', function() {
                try {
                    if (class_exists('WooAiAssistant\\Frontend\\WidgetLoader')) {
                        \WooAiAssistant\Frontend\WidgetLoader::getInstance();
                        error_log('Woo AI Assistant: WidgetLoader loaded via development fallback');
                    }
                } catch (Exception $fallbackError) {
                    error_log('Woo AI Assistant: Development fallback also failed: ' . $fallbackError->getMessage());
                    
                    // ULTIMATE FALLBACK: Try standalone widget loader
                    try {
                        if (class_exists('WooAiAssistant\\Frontend\\StandaloneWidgetLoader')) {
                            \WooAiAssistant\Frontend\StandaloneWidgetLoader::init();
                            error_log('Woo AI Assistant: StandaloneWidgetLoader initialized as ultimate fallback');
                        }
                    } catch (Exception $ultimateError) {
                        error_log('Woo AI Assistant: Even ultimate fallback failed: ' . $ultimateError->getMessage());
                    }
                }
            }, 20);
        }
    }
    
    // EMERGENCY WIDGET LOADER: If everything fails, try to load standalone widget
    if (WOO_AI_DEVELOPMENT_MODE && !is_admin()) {
        add_action('wp_loaded', function() {
            // Check if main widget loader is active
            global $wp_filter;
            $hasWidgetLoader = false;
            
            if (isset($wp_filter['wp_footer']) && isset($wp_filter['wp_footer']->callbacks)) {
                foreach ($wp_filter['wp_footer']->callbacks as $priority => $callbacks) {
                    foreach ($callbacks as $callback) {
                        if (is_array($callback['function']) && isset($callback['function'][0])) {
                            $className = is_object($callback['function'][0]) ? get_class($callback['function'][0]) : '';
                            if (strpos($className, 'WidgetLoader') !== false) {
                                $hasWidgetLoader = true;
                                break 2;
                            }
                        }
                    }
                }
            }
            
            if (!$hasWidgetLoader) {
                if (class_exists('WooAiAssistant\\Frontend\\StandaloneWidgetLoader')) {
                    \WooAiAssistant\Frontend\StandaloneWidgetLoader::init();
                    error_log('Woo AI Assistant: Emergency StandaloneWidgetLoader activated - no main widget loader detected');
                }
            }
        }, 30);
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
        WOO_AI_ASSISTANT_MIN_PHP_VERSION,
        PHP_VERSION
    );

    printf(
        '<div class="notice notice-error is-dismissible"><p><strong>%s:</strong> %s</p></div>',
        esc_html__('Woo AI Assistant', 'woo-ai-assistant'),
        $message
    );
}

/**
 * Display WordPress version requirement notice
 * 
 * @since 1.0.0
 * @return void
 */
function woo_ai_assistant_wp_version_notice() {
    $message = sprintf(
        /* translators: 1: Required WordPress version, 2: Current WordPress version */
        esc_html__('Woo AI Assistant requires WordPress version %1$s or higher. You are running version %2$s. Please upgrade WordPress to use this plugin.', 'woo-ai-assistant'),
        WOO_AI_ASSISTANT_MIN_WP_VERSION,
        get_bloginfo('version')
    );

    printf(
        '<div class="notice notice-error is-dismissible"><p><strong>%s:</strong> %s</p></div>',
        esc_html__('Woo AI Assistant', 'woo-ai-assistant'),
        $message
    );
}

/**
 * Display WooCommerce version requirement notice
 * 
 * @since 1.0.0
 * @return void
 */
function woo_ai_assistant_wc_version_notice() {
    $message = sprintf(
        /* translators: 1: Required WooCommerce version, 2: Current WooCommerce version */
        esc_html__('Woo AI Assistant requires WooCommerce version %1$s or higher. You are running version %2$s. Please upgrade WooCommerce to use this plugin.', 'woo-ai-assistant'),
        WOO_AI_ASSISTANT_MIN_WC_VERSION,
        defined('WC_VERSION') ? WC_VERSION : 'Unknown'
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