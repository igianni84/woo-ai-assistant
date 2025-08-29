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
use WooAiAssistant\Admin\AdminMenu;
use WooAiAssistant\Admin\Pages\DashboardPage;
use WooAiAssistant\Admin\Pages\ConversationsLogPage;
use WooAiAssistant\RestApi\RestController;
use WooAiAssistant\Api\IntermediateServerClient;
use WooAiAssistant\Api\LicenseManager;
use WooAiAssistant\KnowledgeBase\Scanner;
use WooAiAssistant\KnowledgeBase\Indexer;
use WooAiAssistant\KnowledgeBase\VectorManager;
use WooAiAssistant\KnowledgeBase\AIManager;
use WooAiAssistant\KnowledgeBase\CronManager;
use WooAiAssistant\KnowledgeBase\HealthMonitor;
use WooAiAssistant\Frontend\WidgetLoader;
use WooAiAssistant\Chatbot\ConversationHandler;
use WooAiAssistant\Chatbot\RagEngine;
use WooAiAssistant\Chatbot\ProactiveTriggers;
use WooAiAssistant\Chatbot\CouponHandler;
use WooAiAssistant\Setup\AutoIndexer;
use WooAiAssistant\Setup\WooCommerceDetector;
use WooAiAssistant\Setup\DefaultMessageSetup;
use WooAiAssistant\Compatibility\WpmlAndPolylang;
use WooAiAssistant\Compatibility\GdprPlugins;
use WooAiAssistant\Security\InputSanitizer;
use WooAiAssistant\Security\CsrfProtection;
use WooAiAssistant\Security\RateLimiter;
use WooAiAssistant\Security\PromptDefense;
use WooAiAssistant\Security\AuditLogger;

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
class Main
{
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
     * Knowledge Base initialization status
     *
     * @since 1.0.0
     * @var bool
     */
    private bool $kbInitialized = false;

    /**
     * Knowledge Base health status
     *
     * @since 1.0.0
     * @var array
     */
    private array $kbHealthStatus = [];

    /**
     * Constructor
     *
     * Initializes the plugin by setting up hooks and loading components.
     * This is called by the singleton trait's getInstance() method.
     *
     * @since 1.0.0
     */
    private function __construct()
    {
        $this->version = WOO_AI_ASSISTANT_VERSION;
        $this->setupHooks();
    }

    /**
     * Setup WordPress hooks
     *
     * @since 1.0.0
     * @return void
     */
    private function setupHooks(): void
    {
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

        // Auto-installation hooks
        add_action('woo_ai_assistant_auto_install', [$this, 'handleScheduledAutoInstall']);
    }

    /**
     * Initialize the plugin
     *
     * @since 1.0.0
     * @return void
     */
    public function init(): void
    {
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

        // Initialize Knowledge Base system with protection
        try {
            $this->initializeKnowledgeBase();
            Utils::logDebug('Knowledge Base system initialized');
        } catch (\Exception $e) {
            Utils::logError('Failed to initialize Knowledge Base: ' . $e->getMessage());
        }

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
    public function loadTextdomain(): void
    {
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
    public function adminInit(): void
    {
        // Admin-specific initialization will be handled by AdminMenu component
        Utils::logDebug('Admin initialization hook fired');
    }

    /**
     * Enqueue frontend scripts and styles
     *
     * @since 1.0.0
     * @return void
     */
    public function enqueueScripts(): void
    {
        // Frontend scripts are automatically handled by WidgetLoader component
        // when loaded in loadFrontendComponents() method
        Utils::logDebug('Frontend scripts enqueue hook fired');
    }

    /**
     * Handle AJAX requests
     *
     * @since 1.0.0
     * @return void
     */
    public function handleAjax(): void
    {
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
    public function onPluginActivated(string $plugin): void
    {
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
    public function onPluginDeactivated(string $plugin): void
    {
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
    public function onWooCommerceLoaded(): void
    {
        Utils::logDebug('WooCommerce loaded - ready for integration');
    }

    /**
     * Handle scheduled auto-installation
     *
     * @since 1.0.0
     * @return void
     */
    public function handleScheduledAutoInstall(): void
    {
        try {
            Utils::logDebug('Processing scheduled auto-installation');

            // Get AutoIndexer component
            $autoIndexer = $this->getComponent('auto_indexer');

            if (!$autoIndexer) {
                // Try to load it if not already loaded
                if (class_exists('WooAiAssistant\Setup\AutoIndexer')) {
                    $autoIndexer = \WooAiAssistant\Setup\AutoIndexer::getInstance();
                    $this->registerComponent('auto_indexer', $autoIndexer);
                } else {
                    throw new \Exception('AutoIndexer component not available');
                }
            }

            // Trigger auto-indexing
            $results = $autoIndexer->triggerAutoIndexing(true);

            if ($results && !isset($results['error'])) {
                Utils::logDebug('Scheduled auto-installation completed successfully', $results);
                update_option('woo_ai_assistant_needs_auto_install', false);

                // Trigger completion action
                do_action('woo_ai_assistant_auto_install_completed', $results);
            } else {
                throw new \Exception('Auto-indexing failed: ' . ($results['error'] ?? 'Unknown error'));
            }
        } catch (\Exception $e) {
            Utils::logError('Scheduled auto-installation failed: ' . $e->getMessage());

            // Reschedule for later if it failed
            if (!wp_next_scheduled('woo_ai_assistant_auto_install')) {
                wp_schedule_single_event(time() + 1800, 'woo_ai_assistant_auto_install'); // Try again in 30 minutes
                Utils::logDebug('Auto-installation rescheduled due to failure');
            }
        }
    }

    /**
     * Check system requirements
     *
     * @since 1.0.0
     * @return bool True if all requirements are met
     */
    private function checkSystemRequirements(): bool
    {
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
    private function loadComponents(): void
    {
        // Load core components (available in both admin and frontend)
        $this->loadCoreComponents();

        // Load admin components if in admin area
        if (is_admin()) {
            $this->loadAdminComponents();
        }

        // Load frontend components if not in admin
        if (!is_admin()) {
            $this->loadFrontendComponents();
        }

        Utils::logDebug('Plugin components loaded');

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
     * Load core components (available in both admin and frontend)
     *
     * @since 1.0.0
     * @return void
     */
    private function loadCoreComponents(): void
    {
        // PROGRESSIVE RESTORATION: Loading components with protection
        Utils::logDebug('Starting progressive component restoration with error protection');

        // Load REST API Controller (needed for both admin and frontend)
        try {
            if (class_exists('WooAiAssistant\RestApi\RestController')) {
                $restController = RestController::getInstance();
                $this->registerComponent('rest_controller', $restController);
                Utils::logDebug('REST Controller loaded successfully');
            } else {
                Utils::logError('RestController class not found - autoloader may not be initialized properly');
            }
        } catch (\Exception $e) {
            Utils::logError('Failed to load REST Controller: ' . $e->getMessage());
        }

        // Load Intermediate Server Client for API communication (fixed blocking issue)
        try {
            if (class_exists('WooAiAssistant\Api\IntermediateServerClient')) {
                $serverClient = IntermediateServerClient::getInstance();
                $this->registerComponent('server_client', $serverClient);
                Utils::logDebug('Intermediate Server Client loaded');
            } else {
                Utils::logError('IntermediateServerClient class not found');
            }
        } catch (\Exception $e) {
            Utils::logError('Failed to load Intermediate Server Client: ' . $e->getMessage());
        }

        // Load remaining components with protection
        Utils::logDebug('Loading components with fixed circular dependencies');

        // Load Conversation Handler (safe to load)
        try {
            if (class_exists('WooAiAssistant\Chatbot\ConversationHandler')) {
                $conversationHandler = ConversationHandler::getInstance();
                $this->registerComponent('conversation_handler', $conversationHandler);
                Utils::logDebug('Conversation Handler loaded');
            } else {
                Utils::logError('ConversationHandler class not found');
            }
        } catch (\Exception $e) {
            Utils::logError('Failed to load Conversation Handler: ' . $e->getMessage());
        }

        // Load Knowledge Base components with protection
        try {
            Utils::logDebug('Starting to load Knowledge Base components');
            $this->loadKnowledgeBaseComponents();
            Utils::logDebug('Knowledge Base components loaded successfully');
        } catch (\Exception $e) {
            Utils::logError('Failed to load Knowledge Base components: ' . $e->getMessage());
        }

        // Load License Manager with protection (now with fixed lazy loading)
        try {
            if (class_exists('WooAiAssistant\Api\LicenseManager')) {
                $licenseManager = LicenseManager::getInstance();
                $this->registerComponent('license_manager', $licenseManager);
                Utils::logDebug('License Manager loaded successfully');
            } else {
                Utils::logError('LicenseManager class not found');
            }
        } catch (\Exception $e) {
            Utils::logError('Failed to load License Manager: ' . $e->getMessage());
        }

        // Load RAG Engine with protection
        try {
            if (class_exists('WooAiAssistant\Chatbot\RagEngine')) {
                $ragEngine = RagEngine::getInstance();
                $this->registerComponent('rag_engine', $ragEngine);
                Utils::logDebug('RAG Engine loaded successfully');
            } else {
                Utils::logError('RagEngine class not found');
            }
        } catch (\Exception $e) {
            Utils::logError('Failed to load RAG Engine: ' . $e->getMessage());
        }

        // Load Proactive Triggers with protection
        try {
            if (class_exists('WooAiAssistant\Chatbot\ProactiveTriggers')) {
                $proactiveTriggers = ProactiveTriggers::getInstance();
                $this->registerComponent('proactive_triggers', $proactiveTriggers);
                Utils::logDebug('Proactive Triggers loaded successfully');
            } else {
                Utils::logError('ProactiveTriggers class not found');
            }
        } catch (\Exception $e) {
            Utils::logError('Failed to load Proactive Triggers: ' . $e->getMessage());
        }

        // Load Coupon Handler with protection
        try {
            if (class_exists('WooAiAssistant\Chatbot\CouponHandler')) {
                $couponHandler = CouponHandler::getInstance();
                $this->registerComponent('coupon_handler', $couponHandler);
                Utils::logDebug('Coupon Handler loaded successfully');
            } else {
                Utils::logError('CouponHandler class not found');
            }
        } catch (\Exception $e) {
            Utils::logError('Failed to load Coupon Handler: ' . $e->getMessage());
        }


        // Core components loaded action
        do_action('woo_ai_assistant_core_components_loaded', $this);
    }

    /**
     * Load auto-installation components
     *
     * @since 1.0.0
     * @return void
     */
    private function loadAutoInstallationComponents(): void
    {
        try {
            // Load AutoIndexer for immediate content indexing
            if (class_exists('WooAiAssistant\Setup\AutoIndexer')) {
                $autoIndexer = AutoIndexer::getInstance();
                $this->registerComponent('auto_indexer', $autoIndexer);
                Utils::logDebug('AutoIndexer component loaded');
            } else {
                Utils::logError('AutoIndexer class not found');
            }

            // Load WooCommerceDetector for settings extraction
            if (class_exists('WooAiAssistant\Setup\WooCommerceDetector')) {
                $wooDetector = WooCommerceDetector::getInstance();
                $this->registerComponent('woo_detector', $wooDetector);
                Utils::logDebug('WooCommerceDetector component loaded');
            } else {
                Utils::logError('WooCommerceDetector class not found');
            }

            // Load DefaultMessageSetup for conversation configuration
            if (class_exists('WooAiAssistant\Setup\DefaultMessageSetup')) {
                $messageSetup = DefaultMessageSetup::getInstance();
                $this->registerComponent('message_setup', $messageSetup);
                Utils::logDebug('DefaultMessageSetup component loaded');
            } else {
                Utils::logError('DefaultMessageSetup class not found');
            }

            Utils::logDebug('Auto-installation components loaded successfully');

            /**
             * Auto-installation components loaded action
             *
             * Fired after all auto-installation components have been loaded.
             *
             * @since 1.0.0
             * @param Main $instance The main plugin instance
             */
            do_action('woo_ai_assistant_auto_install_components_loaded', $this);
        } catch (\Exception $e) {
            Utils::logError('Failed to load auto-installation components: ' . $e->getMessage());
        }
    }

    /**
     * Load compatibility components
     *
     * @since 1.0.0
     * @return void
     */
    private function loadCompatibilityComponents(): void
    {
        try {
            // Load multilingual support (WPML/Polylang/TranslatePress)
            if (class_exists('WooAiAssistant\Compatibility\WpmlAndPolylang')) {
                $multilingualSupport = WpmlAndPolylang::getInstance();
                $this->registerComponent('multilingual_support', $multilingualSupport);
                Utils::logDebug('Multilingual support component loaded');
            } else {
                Utils::logError('WpmlAndPolylang class not found');
            }

            // Load GDPR compliance support
            if (class_exists('WooAiAssistant\Compatibility\GdprPlugins')) {
                $gdprSupport = GdprPlugins::getInstance();
                $this->registerComponent('gdpr_support', $gdprSupport);
                Utils::logDebug('GDPR compliance component loaded');
            } else {
                Utils::logError('GdprPlugins class not found');
            }

            Utils::logDebug('Compatibility components loaded successfully');

            /**
             * Compatibility components loaded action
             *
             * Fired after all compatibility components have been loaded.
             *
             * @since 1.0.0
             * @param Main $instance The main plugin instance
             */
            do_action('woo_ai_assistant_compatibility_components_loaded', $this);
        } catch (\Exception $e) {
            Utils::logError('Failed to load compatibility components: ' . $e->getMessage());
        }
    }

    /**
     * Load security components
     *
     * Initializes all security-related components including input sanitization,
     * CSRF protection, rate limiting, prompt defense, and audit logging.
     *
     * @since 1.0.0
     * @return void
     */
    private function loadSecurityComponents(): void
    {
        try {
            // Load InputSanitizer for comprehensive input validation
            if (class_exists('WooAiAssistant\Security\InputSanitizer')) {
                $inputSanitizer = InputSanitizer::getInstance();
                $this->registerComponent('input_sanitizer', $inputSanitizer);
                Utils::logDebug('InputSanitizer component loaded');
            } else {
                Utils::logError('InputSanitizer class not found');
            }

            // Load CsrfProtection for CSRF attack prevention
            if (class_exists('WooAiAssistant\Security\CsrfProtection')) {
                $csrfProtection = CsrfProtection::getInstance();
                $this->registerComponent('csrf_protection', $csrfProtection);
                Utils::logDebug('CsrfProtection component loaded');
            } else {
                Utils::logError('CsrfProtection class not found');
            }

            // Load RateLimiter for abuse prevention
            if (class_exists('WooAiAssistant\Security\RateLimiter')) {
                $rateLimiter = RateLimiter::getInstance();
                $this->registerComponent('rate_limiter', $rateLimiter);
                Utils::logDebug('RateLimiter component loaded');
            } else {
                Utils::logError('RateLimiter class not found');
            }

            // Load PromptDefense for AI prompt injection protection
            if (class_exists('WooAiAssistant\Security\PromptDefense')) {
                $promptDefense = PromptDefense::getInstance();
                $this->registerComponent('prompt_defense', $promptDefense);
                Utils::logDebug('PromptDefense component loaded');
            } else {
                Utils::logError('PromptDefense class not found');
            }

            // Load AuditLogger for comprehensive security event logging
            if (class_exists('WooAiAssistant\Security\AuditLogger')) {
                $auditLogger = AuditLogger::getInstance();
                $this->registerComponent('audit_logger', $auditLogger);
                Utils::logDebug('AuditLogger component loaded');
            } else {
                Utils::logError('AuditLogger class not found');
            }

            Utils::logDebug('Security components loaded successfully');

            /**
             * Security components loaded action
             *
             * Fired after all security components have been loaded.
             *
             * @since 1.0.0
             * @param Main $instance The main plugin instance
             */
            do_action('woo_ai_assistant_security_components_loaded', $this);
        } catch (\Exception $e) {
            Utils::logError('Failed to load security components: ' . $e->getMessage());
        }
    }

    /**
     * Load admin-specific components
     *
     * @since 1.0.0
     * @return void
     */
    private function loadAdminComponents(): void
    {
        // PROGRESSIVE RESTORATION: Loading admin components with protection
        Utils::logDebug('Starting admin component restoration');

        try {
            // Load AdminMenu component
            if (class_exists('WooAiAssistant\Admin\AdminMenu')) {
                $adminMenu = AdminMenu::getInstance();
                $this->registerComponent('admin_menu', $adminMenu);
                Utils::logDebug('AdminMenu component loaded');
            } else {
                Utils::logError('AdminMenu class not found');
            }

            // Load DashboardPage component
            if (class_exists('WooAiAssistant\Admin\Pages\DashboardPage')) {
                $dashboardPage = DashboardPage::getInstance();
                $this->registerComponent('dashboard_page', $dashboardPage);
                Utils::logDebug('DashboardPage component loaded');
            } else {
                Utils::logError('DashboardPage class not found');
            }

            // Load ConversationsLogPage component
            if (class_exists('WooAiAssistant\Admin\Pages\ConversationsLogPage')) {
                $conversationsLogPage = ConversationsLogPage::getInstance();
                $this->registerComponent('conversations_log_page', $conversationsLogPage);
                Utils::logDebug('ConversationsLogPage component loaded');
            } else {
                Utils::logError('ConversationsLogPage class not found');
            }

            Utils::logDebug('Admin components loaded successfully');
        } catch (\Exception $e) {
            Utils::logError('Failed to load admin components: ' . $e->getMessage());
        }

        /**
         * Admin components loaded action
         *
         * @since 1.0.0
         * @param Main $instance The main plugin instance
         */
        do_action('woo_ai_assistant_admin_components_loaded', $this);
    }

    /**
     * Load frontend-specific components
     *
     * @since 1.0.0
     * @return void
     */
    private function loadFrontendComponents(): void
    {
        // Restore frontend components with protection
        try {
            Utils::logDebug('Starting frontend component restoration');

            // Load Widget Loader for frontend chat widget
            if (class_exists('WooAiAssistant\Frontend\WidgetLoader')) {
                $widgetLoader = WidgetLoader::getInstance();
                $this->registerComponent('widget_loader', $widgetLoader);
                Utils::logDebug('WidgetLoader component loaded');
            } else {
                Utils::logError('WidgetLoader class not found');
            }

            Utils::logDebug('Frontend components loaded successfully');
        } catch (\Exception $e) {
            Utils::logError('Failed to load frontend components: ' . $e->getMessage());
        }

        /* ORIGINAL CODE DISABLED FOR EMERGENCY FIX:
        try {
            // Load Widget Loader for frontend chat widget
            $this->components['widget_loader'] = WidgetLoader::getInstance();
            Utils::logDebug('WidgetLoader component initialized');
        } catch (\Exception $e) {
            Utils::logError('Failed to load frontend components: ' . $e->getMessage());
        }

        Utils::logDebug('Frontend components loaded');
        */

        /**
         * Frontend components loaded action
         *
         * @since 1.0.0
         * @param Main $instance The main plugin instance
         */
        do_action('woo_ai_assistant_frontend_components_loaded', $this);
    }

    /**
     * WordPress version notice
     *
     * @since 1.0.0
     * @return void
     */
    public function wpVersionNotice(): void
    {
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
    public function phpVersionNotice(): void
    {
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
    public function woocommerceNotice(): void
    {
        $message = esc_html__('Woo AI Assistant requires WooCommerce to be installed and active. Please install and activate WooCommerce.', 'woo-ai-assistant');
        $this->displayNotice($message, 'error');
    }

    /**
     * WooCommerce version notice
     *
     * @since 1.0.0
     * @return void
     */
    public function wcVersionNotice(): void
    {
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
    private function displayNotice(string $message, string $type = 'info'): void
    {
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
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Check if plugin is initialized
     *
     * @since 1.0.0
     * @return bool True if initialized
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * Get component instance
     *
     * @since 1.0.0
     * @param string $componentName Component name
     * @return mixed|null Component instance or null if not found
     */
    public function getComponent(string $componentName)
    {
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
    public function registerComponent(string $name, $instance): void
    {
        $this->components[$name] = $instance;
        Utils::logDebug("Component '{$name}' registered");
    }

    /**
     * Get all registered components
     *
     * @since 1.0.0
     * @return array Array of registered components
     */
    public function getComponents(): array
    {
        return $this->components;
    }

    /**
     * Load Knowledge Base components
     *
     * @since 1.0.0
     * @return void
     */
    private function loadKnowledgeBaseComponents(): void
    {
        try {
            // Initialize Scanner
            if (class_exists('WooAiAssistant\KnowledgeBase\Scanner')) {
                $scanner = Scanner::getInstance();
                $this->registerComponent('kb_scanner', $scanner);
                Utils::logDebug('Knowledge Base Scanner loaded');
            } else {
                Utils::logError('Scanner class not found');
                return;
            }

            // Initialize Indexer
            if (class_exists('WooAiAssistant\KnowledgeBase\Indexer')) {
                $indexer = Indexer::getInstance();
                $this->registerComponent('kb_indexer', $indexer);
                Utils::logDebug('Knowledge Base Indexer loaded');
            } else {
                Utils::logError('Indexer class not found');
                return;
            }

            // Initialize VectorManager
            if (class_exists('WooAiAssistant\KnowledgeBase\VectorManager')) {
                $vectorManager = VectorManager::getInstance();
                $this->registerComponent('kb_vector_manager', $vectorManager);
                Utils::logDebug('Knowledge Base VectorManager loaded');
            } else {
                Utils::logError('VectorManager class not found');
                return;
            }

            // Initialize AIManager
            if (class_exists('WooAiAssistant\KnowledgeBase\AIManager')) {
                $aiManager = AIManager::getInstance();
                $this->registerComponent('kb_ai_manager', $aiManager);
                Utils::logDebug('Knowledge Base AIManager loaded');
            } else {
                Utils::logError('AIManager class not found');
                return;
            }

            // Initialize CronManager
            if (class_exists('WooAiAssistant\KnowledgeBase\CronManager')) {
                $cronManager = CronManager::getInstance();
                $this->registerComponent('kb_cron_manager', $cronManager);
                Utils::logDebug('Knowledge Base CronManager loaded');
            } else {
                Utils::logError('CronManager class not found');
            }

            // Initialize HealthMonitor
            if (class_exists('WooAiAssistant\KnowledgeBase\HealthMonitor')) {
                $healthMonitor = HealthMonitor::getInstance();
                $this->registerComponent('kb_health_monitor', $healthMonitor);
                Utils::logDebug('Knowledge Base HealthMonitor loaded');
            } else {
                Utils::logError('HealthMonitor class not found');
            }

            Utils::logDebug('All Knowledge Base components loaded successfully');

            /**
             * Knowledge Base components loaded action
             *
             * Fired after all KB components have been loaded.
             *
             * @since 1.0.0
             * @param Main $instance The main plugin instance
             */
            do_action('woo_ai_assistant_kb_components_loaded', $this);
        } catch (Exception $e) {
            Utils::logError('Failed to load Knowledge Base components: ' . $e->getMessage());
        }
    }

    /**
     * Initialize Knowledge Base system
     *
     * @since 1.0.0
     * @return void
     */
    private function initializeKnowledgeBase(): void
    {
        if ($this->kbInitialized) {
            return;
        }

        try {
            Utils::logDebug('Initializing Knowledge Base system');

            // Run KB health check
            $this->performKnowledgeBaseHealthCheck();

            // Setup KB hooks
            $this->setupKnowledgeBaseHooks();

            // EMERGENCY FIX: Temporarily disable auto-indexing to prevent crashes
            // Auto-index on first activation if needed - DISABLED FOR EMERGENCY
            // $this->maybeAutoIndex();
            Utils::logDebug('Auto-indexing temporarily disabled for emergency fix');

            $this->kbInitialized = true;

            /**
             * Knowledge Base initialized action
             *
             * Fired after the KB system has been fully initialized.
             *
             * @since 1.0.0
             * @param Main $instance The main plugin instance
             */
            do_action('woo_ai_assistant_kb_initialized', $this);

            Utils::logDebug('Knowledge Base system initialized successfully');
        } catch (Exception $e) {
            Utils::logError('Failed to initialize Knowledge Base: ' . $e->getMessage());
        }
    }

    /**
     * Setup Knowledge Base WordPress hooks
     *
     * @since 1.0.0
     * @return void
     */
    private function setupKnowledgeBaseHooks(): void
    {
        // Product update hooks
        add_action('woocommerce_update_product', [$this, 'onProductUpdated'], 10, 1);
        add_action('woocommerce_new_product', [$this, 'onProductCreated'], 10, 1);
        add_action('woocommerce_delete_product', [$this, 'onProductDeleted'], 10, 1);

        // Page/Post update hooks
        add_action('save_post', [$this, 'onPostSaved'], 10, 3);
        add_action('before_delete_post', [$this, 'onPostDeleted'], 10, 1);

        // WooCommerce settings hooks
        add_action('woocommerce_settings_saved', [$this, 'onWooSettingsUpdated'], 10);

        // KB maintenance hooks
        add_action('woo_ai_assistant_daily_maintenance', [$this, 'performDailyMaintenance']);

        Utils::logDebug('Knowledge Base hooks registered');
    }

    /**
     * Perform Knowledge Base health check
     *
     * @since 1.0.0
     * @return void
     */
    private function performKnowledgeBaseHealthCheck(): void
    {
        $this->kbHealthStatus = [
            'status' => 'healthy',
            'last_check' => current_time('mysql'),
            'components' => [],
            'issues' => []
        ];

        // Check Scanner component
        $scanner = $this->getComponent('kb_scanner');
        if ($scanner) {
            $this->kbHealthStatus['components']['scanner'] = 'loaded';
        } else {
            $this->kbHealthStatus['components']['scanner'] = 'failed';
            $this->kbHealthStatus['issues'][] = 'Scanner component not loaded';
            $this->kbHealthStatus['status'] = 'degraded';
        }

        // Check Indexer component
        $indexer = $this->getComponent('kb_indexer');
        if ($indexer) {
            $this->kbHealthStatus['components']['indexer'] = 'loaded';
        } else {
            $this->kbHealthStatus['components']['indexer'] = 'failed';
            $this->kbHealthStatus['issues'][] = 'Indexer component not loaded';
            $this->kbHealthStatus['status'] = 'degraded';
        }

        // Check VectorManager component
        $vectorManager = $this->getComponent('kb_vector_manager');
        if ($vectorManager) {
            $this->kbHealthStatus['components']['vector_manager'] = 'loaded';
        } else {
            $this->kbHealthStatus['components']['vector_manager'] = 'failed';
            $this->kbHealthStatus['issues'][] = 'VectorManager component not loaded';
            $this->kbHealthStatus['status'] = 'degraded';
        }

        // Check AIManager component
        $aiManager = $this->getComponent('kb_ai_manager');
        if ($aiManager) {
            $this->kbHealthStatus['components']['ai_manager'] = 'loaded';
        } else {
            $this->kbHealthStatus['components']['ai_manager'] = 'failed';
            $this->kbHealthStatus['issues'][] = 'AIManager component not loaded';
            $this->kbHealthStatus['status'] = 'critical';
        }

        // Update health status option
        update_option('woo_ai_assistant_kb_health', $this->kbHealthStatus);

        Utils::logDebug('Knowledge Base health check completed', $this->kbHealthStatus);
    }

    /**
     * Auto-index content on first activation
     *
     * @since 1.0.0
     * @return void
     */
    private function maybeAutoIndex(): void
    {
        $needsIndexing = get_option('woo_ai_assistant_needs_initial_index', true);

        if ($needsIndexing && Utils::isWooCommerceActive()) {
            Utils::logDebug('Scheduling initial Knowledge Base indexing');

            // Schedule background indexing
            if (!wp_next_scheduled('woo_ai_assistant_initial_index')) {
                wp_schedule_single_event(time() + 60, 'woo_ai_assistant_initial_index');
            }

            update_option('woo_ai_assistant_needs_initial_index', false);
        }
    }

    /**
     * Handle product updated event
     *
     * @since 1.0.0
     * @param int|\WC_Product $product Product ID or object
     * @return void
     */
    public function onProductUpdated($product): void
    {
        try {
            $scanner = $this->getComponent('kb_scanner');
            if ($scanner && method_exists($scanner, 'onProductUpdated')) {
                $scanner->onProductUpdated($product);
            }
        } catch (Exception $e) {
            Utils::logError('Error handling product update: ' . $e->getMessage());
        }
    }

    /**
     * Handle product created event
     *
     * @since 1.0.0
     * @param int $productId Product ID
     * @return void
     */
    public function onProductCreated(int $productId): void
    {
        $this->onProductUpdated($productId);
    }

    /**
     * Handle product deleted event
     *
     * @since 1.0.0
     * @param int $productId Product ID
     * @return void
     */
    public function onProductDeleted(int $productId): void
    {
        try {
            $indexer = $this->getComponent('kb_indexer');
            if ($indexer && method_exists($indexer, 'removeContent')) {
                $indexer->removeContent('product', $productId);
            }
        } catch (Exception $e) {
            Utils::logError('Error handling product deletion: ' . $e->getMessage());
        }
    }

    /**
     * Handle post saved event
     *
     * @since 1.0.0
     * @param int $postId Post ID
     * @param \WP_Post $post Post object
     * @param bool $update Whether this is an update
     * @return void
     */
    public function onPostSaved(int $postId, \WP_Post $post, bool $update): void
    {
        try {
            $scanner = $this->getComponent('kb_scanner');
            if ($scanner && method_exists($scanner, 'onPostSaved')) {
                $scanner->onPostSaved($postId, $post, $update);
            }
        } catch (Exception $e) {
            Utils::logError('Error handling post save: ' . $e->getMessage());
        }
    }

    /**
     * Handle post deleted event
     *
     * @since 1.0.0
     * @param int $postId Post ID
     * @return void
     */
    public function onPostDeleted(int $postId): void
    {
        try {
            $indexer = $this->getComponent('kb_indexer');
            if ($indexer && method_exists($indexer, 'removeContent')) {
                $indexer->removeContent('post', $postId);
            }
        } catch (Exception $e) {
            Utils::logError('Error handling post deletion: ' . $e->getMessage());
        }
    }

    /**
     * Handle WooCommerce settings updated event
     *
     * @since 1.0.0
     * @return void
     */
    public function onWooSettingsUpdated(): void
    {
        try {
            $scanner = $this->getComponent('kb_scanner');
            if ($scanner && method_exists($scanner, 'clearCache')) {
                $scanner->clearCache('woo_settings');
            }

            // Schedule re-indexing of settings
            if (!wp_next_scheduled('woo_ai_assistant_reindex_settings')) {
                wp_schedule_single_event(time() + 300, 'woo_ai_assistant_reindex_settings');
            }
        } catch (Exception $e) {
            Utils::logError('Error handling WooCommerce settings update: ' . $e->getMessage());
        }
    }

    /**
     * Perform daily maintenance tasks
     *
     * @since 1.0.0
     * @return void
     */
    public function performDailyMaintenance(): void
    {
        try {
            Utils::logDebug('Starting daily KB maintenance');

            // Perform health check
            $this->performKnowledgeBaseHealthCheck();

            // Clean up old conversation data
            $this->cleanupOldConversations();

            // Update usage statistics
            $this->updateUsageStatistics();

            Utils::logDebug('Daily KB maintenance completed');
        } catch (Exception $e) {
            Utils::logError('Error during daily maintenance: ' . $e->getMessage());
        }
    }

    /**
     * Clean up old conversations
     *
     * @since 1.0.0
     * @return void
     */
    private function cleanupOldConversations(): void
    {
        global $wpdb;

        $retentionDays = apply_filters('woo_ai_assistant_conversation_retention_days', 30);
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));

        $tableName = $wpdb->prefix . 'woo_ai_conversations';

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$tableName} WHERE created_at < %s AND status = 'completed'",
            $cutoffDate
        ));

        if ($deleted !== false && $deleted > 0) {
            Utils::logDebug("Cleaned up {$deleted} old conversations");
        }
    }

    /**
     * Update usage statistics
     *
     * @since 1.0.0
     * @return void
     */
    private function updateUsageStatistics(): void
    {
        global $wpdb;

        $stats = [
            'total_conversations' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}woo_ai_conversations"),
            'total_messages' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}woo_ai_messages"),
            'kb_entries' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}woo_ai_knowledge_base"),
            'last_updated' => current_time('mysql')
        ];

        update_option('woo_ai_assistant_usage_stats', $stats);
    }

    /**
     * Get Knowledge Base health status
     *
     * @since 1.0.0
     * @return array Health status array
     */
    public function getKnowledgeBaseHealth(): array
    {
        return $this->kbHealthStatus ?: get_option('woo_ai_assistant_kb_health', [
            'status' => 'unknown',
            'last_check' => null,
            'components' => [],
            'issues' => ['Health check not performed yet']
        ]);
    }

    /**
     * Check if Knowledge Base is initialized
     *
     * @since 1.0.0
     * @return bool True if initialized
     */
    public function isKnowledgeBaseInitialized(): bool
    {
        return $this->kbInitialized;
    }
}
