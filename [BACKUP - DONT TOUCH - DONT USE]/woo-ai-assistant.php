<?php
/**
 * Plugin Name: Woo AI Assistant
 * Plugin URI: https://github.com/woo-ai-assistant/woo-ai-assistant
 * Description: AI-powered chatbot for WooCommerce with zero-config knowledge base
 * Version: 1.0.0
 * Author: Woo AI Assistant Team
 * Author URI: https://woo-ai-assistant.com
 * Text Domain: woo-ai-assistant
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package WooAiAssistant
 */

namespace WooAiAssistant;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WOO_AI_ASSISTANT_VERSION', '1.0.0');
define('WOO_AI_ASSISTANT_PATH', plugin_dir_path(__FILE__));
define('WOO_AI_ASSISTANT_URL', plugin_dir_url(__FILE__));
define('WOO_AI_ASSISTANT_FILE', __FILE__);
define('WOO_AI_ASSISTANT_BASENAME', plugin_basename(__FILE__));

// Minimum requirements check
if (version_compare(PHP_VERSION, '8.1', '<')) {
    add_action('admin_notices', function() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('Woo AI Assistant requires PHP 8.1 or higher. Your current version is ' . PHP_VERSION, 'woo-ai-assistant'); ?></p>
        </div>
        <?php
    });
    return;
}

// Check if WooCommerce is active
add_action('plugins_loaded', function() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p><?php _e('Woo AI Assistant requires WooCommerce to be installed and activated.', 'woo-ai-assistant'); ?></p>
            </div>
            <?php
        });
        return;
    }
    
    // Load composer autoloader if it exists
    if (file_exists(WOO_AI_ASSISTANT_PATH . 'vendor/autoload.php')) {
        require_once WOO_AI_ASSISTANT_PATH . 'vendor/autoload.php';
    }
    
    // Initialize the plugin
    if (class_exists('WooAiAssistant\Main')) {
        Main::getInstance();
    }
});

// Activation hook
register_activation_hook(__FILE__, function() {
    if (file_exists(WOO_AI_ASSISTANT_PATH . 'vendor/autoload.php')) {
        require_once WOO_AI_ASSISTANT_PATH . 'vendor/autoload.php';
    }
    
    if (class_exists('WooAiAssistant\Setup\Activator')) {
        Setup\Activator::activate();
    }
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    if (file_exists(WOO_AI_ASSISTANT_PATH . 'vendor/autoload.php')) {
        require_once WOO_AI_ASSISTANT_PATH . 'vendor/autoload.php';
    }
    
    if (class_exists('WooAiAssistant\Setup\Deactivator')) {
        Setup\Deactivator::deactivate();
    }
});