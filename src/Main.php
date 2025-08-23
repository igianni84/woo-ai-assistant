<?php
/**
 * Main Plugin Class
 *
 * Main orchestrator class that initializes and coordinates all plugin components.
 * Implements the singleton pattern to ensure single instance throughout the
 * application lifecycle.
 *
 * @package WooAiAssistant
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant;

use WooAiAssistant\Common\Traits\Singleton;
use WooAiAssistant\Common\Utils;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Main
 * 
 * The main plugin class that handles initialization and coordination of all
 * plugin components. Uses the singleton pattern to ensure only one instance
 * exists throughout the application lifecycle.
 * 
 * @since 1.0.0
 */
class Main {

    use Singleton;

    /**
     * Plugin version
     *
     * @since 1.0.0
     * @var string
     */
    private string $version;

    /**
     * Minimum WordPress version required
     *
     * @since 1.0.0
     * @var string
     */
    private string $minWpVersion = '6.0';

    /**
     * Minimum WooCommerce version required
     *
     * @since 1.0.0
     * @var string
     */
    private string $minWcVersion = '7.0';

    /**
     * Minimum PHP version required
     *
     * @since 1.0.0
     * @var string
     */
    private string $minPhpVersion = '8.2';

    /**
     * Plugin initialization flag
     *
     * @since 1.0.0
     * @var bool
     */
    private bool $initialized = false;

    /**
     * Plugin components
     *
     * @since 1.0.0
     * @var array
     */
    private array $components = [];

    /**
     * Constructor
     *
     * Initializes the plugin by setting up hooks and loading components.
     * This is called by the singleton trait's getInstance() method.
     *
     * @since 1.0.0
     */
    private function __construct() {
        $this->version = WOO_AI_ASSISTANT_VERSION;
        $this->setupHooks();
    }

    /**
     * Setup WordPress hooks
     *
     * @since 1.0.0
     * @return void
     */
    private function setupHooks(): void {
        // Initialize plugin on WordPress init
        add_action('init', [$this, 'init'], 0);

        // Load textdomain for translations
        add_action('plugins_loaded', [$this, 'loadTextdomain'], 10);

        // Admin-specific hooks
        if (is_admin()) {
            add_action('admin_init', [$this, 'adminInit']);
        }

        // Frontend-specific hooks
        if (!is_admin()) {
            add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);
        }

        // AJAX hooks for both admin and frontend
        add_action('wp_ajax_woo_ai_assistant_ajax', [$this, 'handleAjax']);
        add_action('wp_ajax_nopriv_woo_ai_assistant_ajax', [$this, 'handleAjax']);

        // Plugin lifecycle hooks
        add_action('activated_plugin', [$this, 'onPluginActivated']);
        add_action('deactivated_plugin', [$this, 'onPluginDeactivated']);

        // WooCommerce integration hooks
        add_action('woocommerce_loaded', [$this, 'onWooCommerceLoaded']);
    }

    /**
     * Initialize the plugin
     *
     * @since 1.0.0
     * @return void
     */
    public function init(): void {
        // Prevent multiple initializations
        if ($this->initialized) {
            return;
        }

        // Check system requirements
        if (!$this->checkSystemRequirements()) {
            return;
        }

        Utils::logDebug('Initializing Woo AI Assistant plugin');

        // Load core components
        $this->loadComponents();

        // Mark as initialized
        $this->initialized = true;

        /**
         * Plugin initialized action
         *
         * Fired after the plugin has been fully initialized.
         *
         * @since 1.0.0
         * @param Main $instance The main plugin instance
         */
        do_action('woo_ai_assistant_initialized', $this);

        Utils::logDebug('Woo AI Assistant plugin initialized successfully');
    }

    /**
     * Load plugin textdomain for translations
     *
     * @since 1.0.0
     * @return void
     */
    public function loadTextdomain(): void {
        load_plugin_textdomain(
            'woo-ai-assistant',
            false,
            dirname(plugin_basename(WOO_AI_ASSISTANT_PLUGIN_FILE)) . '/languages/'
        );
    }

    /**
     * Admin initialization
     *
     * @since 1.0.0
     * @return void
     */
    public function adminInit(): void {
        // Admin-specific initialization will be handled by AdminMenu component
        Utils::logDebug('Admin initialization hook fired');
    }

    /**
     * Enqueue frontend scripts and styles
     *
     * @since 1.0.0
     * @return void
     */
    public function enqueueScripts(): void {
        // Frontend scripts will be handled by WidgetLoader component
        Utils::logDebug('Frontend scripts enqueue hook fired');
    }

    /**
     * Handle AJAX requests
     *
     * @since 1.0.0
     * @return void
     */
    public function handleAjax(): void {
        // AJAX handling will be delegated to appropriate components
        Utils::logDebug('AJAX request received');
        
        // For now, return a simple response
        wp_send_json_error('AJAX handler not implemented yet');
    }

    /**
     * Handle plugin activation
     *
     * @since 1.0.0
     * @param string $plugin Path to the plugin file
     * @return void
     */
    public function onPluginActivated(string $plugin): void {
        if ($plugin === plugin_basename(WOO_AI_ASSISTANT_PLUGIN_FILE)) {
            Utils::logDebug('Woo AI Assistant plugin activated');
        }
    }

    /**
     * Handle plugin deactivation
     *
     * @since 1.0.0
     * @param string $plugin Path to the plugin file
     * @return void
     */
    public function onPluginDeactivated(string $plugin): void {
        if ($plugin === plugin_basename(WOO_AI_ASSISTANT_PLUGIN_FILE)) {
            Utils::logDebug('Woo AI Assistant plugin deactivated');
        }
    }

    /**
     * Handle WooCommerce loaded
     *
     * @since 1.0.0
     * @return void
     */
    public function onWooCommerceLoaded(): void {
        Utils::logDebug('WooCommerce loaded - ready for integration');
    }

    /**
     * Check system requirements
     *
     * @since 1.0.0
     * @return bool True if all requirements are met
     */
    private function checkSystemRequirements(): bool {
        // Check WordPress version
        if (version_compare(get_bloginfo('version'), $this->minWpVersion, '<')) {
            add_action('admin_notices', [$this, 'wpVersionNotice']);
            return false;
        }

        // Check PHP version
        if (version_compare(PHP_VERSION, $this->minPhpVersion, '<')) {
            add_action('admin_notices', [$this, 'phpVersionNotice']);
            return false;
        }

        // Check if WooCommerce is active
        if (!Utils::isWooCommerceActive()) {
            add_action('admin_notices', [$this, 'woocommerceNotice']);
            return false;
        }

        // Check WooCommerce version
        if (defined('WC_VERSION') && version_compare(WC_VERSION, $this->minWcVersion, '<')) {
            add_action('admin_notices', [$this, 'wcVersionNotice']);
            return false;
        }

        return true;
    }

    /**
     * Load plugin components
     *
     * @since 1.0.0
     * @return void
     */
    private function loadComponents(): void {
        // Components will be loaded here as they are implemented
        // For now, just log that we're ready to load components
        Utils::logDebug('Ready to load plugin components');

        /**
         * Components loaded action
         *
         * Fired after all plugin components have been loaded.
         *
         * @since 1.0.0
         * @param Main $instance The main plugin instance
         */
        do_action('woo_ai_assistant_components_loaded', $this);
    }

    /**
     * WordPress version notice
     *
     * @since 1.0.0
     * @return void
     */
    public function wpVersionNotice(): void {
        $message = sprintf(
            /* translators: 1: Required WordPress version, 2: Current WordPress version */
            esc_html__('Woo AI Assistant requires WordPress version %1$s or higher. You are running version %2$s. Please upgrade WordPress.', 'woo-ai-assistant'),
            $this->minWpVersion,
            get_bloginfo('version')
        );

        $this->displayNotice($message, 'error');
    }

    /**
     * PHP version notice
     *
     * @since 1.0.0
     * @return void
     */
    public function phpVersionNotice(): void {
        $message = sprintf(
            /* translators: 1: Required PHP version, 2: Current PHP version */
            esc_html__('Woo AI Assistant requires PHP version %1$s or higher. You are running version %2$s. Please upgrade PHP.', 'woo-ai-assistant'),
            $this->minPhpVersion,
            PHP_VERSION
        );

        $this->displayNotice($message, 'error');
    }

    /**
     * WooCommerce missing notice
     *
     * @since 1.0.0
     * @return void
     */
    public function woocommerceNotice(): void {
        $message = esc_html__('Woo AI Assistant requires WooCommerce to be installed and active. Please install and activate WooCommerce.', 'woo-ai-assistant');
        $this->displayNotice($message, 'error');
    }

    /**
     * WooCommerce version notice
     *
     * @since 1.0.0
     * @return void
     */
    public function wcVersionNotice(): void {
        $message = sprintf(
            /* translators: 1: Required WooCommerce version, 2: Current WooCommerce version */
            esc_html__('Woo AI Assistant requires WooCommerce version %1$s or higher. You are running version %2$s. Please upgrade WooCommerce.', 'woo-ai-assistant'),
            $this->minWcVersion,
            defined('WC_VERSION') ? WC_VERSION : 'Unknown'
        );

        $this->displayNotice($message, 'error');
    }

    /**
     * Display admin notice
     *
     * @since 1.0.0
     * @param string $message Notice message
     * @param string $type Notice type (success, info, warning, error)
     * @return void
     */
    private function displayNotice(string $message, string $type = 'info'): void {
        printf(
            '<div class="notice notice-%s is-dismissible"><p><strong>%s:</strong> %s</p></div>',
            esc_attr($type),
            esc_html__('Woo AI Assistant', 'woo-ai-assistant'),
            esc_html($message)
        );
    }

    /**
     * Get plugin version
     *
     * @since 1.0.0
     * @return string Plugin version
     */
    public function getVersion(): string {
        return $this->version;
    }

    /**
     * Check if plugin is initialized
     *
     * @since 1.0.0
     * @return bool True if initialized
     */
    public function isInitialized(): bool {
        return $this->initialized;
    }

    /**
     * Get component instance
     *
     * @since 1.0.0
     * @param string $componentName Component name
     * @return mixed|null Component instance or null if not found
     */
    public function getComponent(string $componentName) {
        return $this->components[$componentName] ?? null;
    }

    /**
     * Register component
     *
     * @since 1.0.0
     * @param string $name Component name
     * @param mixed $instance Component instance
     * @return void
     */
    public function registerComponent(string $name, $instance): void {
        $this->components[$name] = $instance;
        Utils::logDebug("Component '{$name}' registered");
    }

    /**
     * Get all registered components
     *
     * @since 1.0.0
     * @return array Array of registered components
     */
    public function getComponents(): array {
        return $this->components;
    }
}