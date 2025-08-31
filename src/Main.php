<?php

/**
 * Main Plugin Class
 *
 * Singleton orchestrator class that initializes and coordinates all plugin functionality.
 * This is the central hub that loads modules, registers hooks, and manages the plugin lifecycle.
 *
 * @package WooAiAssistant
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant;

use WooAiAssistant\Common\Traits\Singleton;
use WooAiAssistant\Common\Utils;
use WooAiAssistant\Common\Logger;
use WooAiAssistant\Common\Cache;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Main
 *
 * Main plugin orchestrator using singleton pattern.
 *
 * @since 1.0.0
 */
class Main
{
    use Singleton;

    /**
     * Plugin modules
     *
     * @var array
     */
    private array $modules = [];

    /**
     * Plugin initialization status
     *
     * @var bool
     */
    private bool $initialized = false;

    /**
     * Initialize the plugin
     *
     * @return void
     */
    protected function init(): void
    {
        // Initialize hooks immediately
        $this->initHooks();

        // Log plugin initialization
        Logger::info('Woo AI Assistant plugin initializing', [
            'version' => Utils::getVersion(),
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
            'wc_active' => Utils::isWooCommerceActive(),
            'dev_mode' => Utils::isDevelopmentMode()
        ]);

        // Load modules after WordPress is fully loaded
        add_action('init', [$this, 'loadModules'], 5);

        // Mark as initialized
        $this->initialized = true;

        Logger::debug('Main plugin class initialized');
    }

    /**
     * Initialize WordPress hooks
     *
     * Register activation, deactivation, and other core hooks.
     *
     * @return void
     */
    private function initHooks(): void
    {
        // Plugin lifecycle hooks are registered in main plugin file
        // Additional hooks can be registered here as needed

        // Register plugin action links
        add_filter('plugin_action_links_' . WOO_AI_ASSISTANT_BASENAME, [$this, 'addPluginActionLinks']);

        // Register plugin row meta links
        add_filter('plugin_row_meta', [$this, 'addPluginRowMeta'], 10, 2);

        // Register admin notices for development mode
        if (Utils::isDevelopmentMode()) {
            add_action('admin_notices', [$this, 'showDevelopmentNotice']);
        }

        // Register AJAX handlers for logged in and non-logged in users
        $this->registerAjaxHooks();

        // Register REST API initialization
        add_action('rest_api_init', [$this, 'initRestApi']);

        // Register frontend initialization
        add_action('wp_loaded', [$this, 'initFrontend']);

        // Register admin initialization
        if (is_admin()) {
            add_action('admin_init', [$this, 'initAdmin']);
        }

        Logger::debug('WordPress hooks initialized');
    }

    /**
     * Load and initialize plugin modules
     *
     * This method will be expanded in future tasks to load specific modules
     * like Knowledge Base, Admin, Frontend, etc.
     *
     * @return void
     */
    public function loadModules(): void
    {
        if (!Utils::isWooCommerceActive()) {
            Logger::warning('WooCommerce not active, skipping module loading');
            return;
        }

        Logger::info('Loading plugin modules');

        // Load Admin module (Task 1.1)
        if (is_admin()) {
            $this->loadAdminModule();
        }

        // Load Knowledge Base module (Task 2.1)
        $this->loadKnowledgeBaseModule();

        // Load Frontend module (Task 4.5)
        if (!is_admin() && !wp_doing_ajax() && !wp_doing_cron()) {
            $this->loadFrontendModule();
        }

        // TODO: In future tasks, initialize additional modules:
        // - Chatbot Handler (Task 5.1)

        // Apply filter to allow module registration by other code
        $this->modules = apply_filters('woo_ai_assistant_load_modules', $this->modules);

        // Fire action after modules are loaded
        do_action('woo_ai_assistant_modules_loaded', $this->modules);

        Logger::info('Plugin modules loaded', [
            'module_count' => count($this->modules)
        ]);
    }

    /**
     * Initialize REST API endpoints
     *
     * @return void
     */
    public function initRestApi(): void
    {
        // Load REST API controller (Task 1.2)
        $this->loadRestApiModule();

        do_action('woo_ai_assistant_rest_api_init');
    }

    /**
     * Initialize frontend functionality
     *
     * @return void
     */
    public function initFrontend(): void
    {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return;
        }

        // Frontend module will be loaded in loadModules method if needed
        Logger::debug('Frontend initialization hook fired');

        do_action('woo_ai_assistant_frontend_init');
    }

    /**
     * Initialize admin functionality
     *
     * @return void
     */
    public function initAdmin(): void
    {
        // Admin module will be loaded in loadModules method
        Logger::debug('Admin initialization hook fired');

        do_action('woo_ai_assistant_admin_init');
    }

    /**
     * Load admin module
     *
     * Initializes the admin interface including menu, pages, and assets.
     *
     * @return void
     */
    private function loadAdminModule(): void
    {
        try {
            // Load admin menu and pages
            $adminMenu = \WooAiAssistant\Admin\AdminMenu::getInstance();
            $this->registerModule('admin_menu', $adminMenu);

            // Load assets manager
            $assets = \WooAiAssistant\Admin\Assets::getInstance();
            $this->registerModule('admin_assets', $assets);

            Logger::info('Admin module loaded successfully');
        } catch (Exception $e) {
            Logger::error('Failed to load admin module', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Load REST API module
     *
     * Initializes the REST API controller and registers all endpoints.
     *
     * @return void
     */
    private function loadRestApiModule(): void
    {
        try {
            // Load REST API controller
            $restController = \WooAiAssistant\RestApi\RestController::getInstance();
            $this->registerModule('rest_api', $restController);

            Logger::info('REST API module loaded successfully');
        } catch (Exception $e) {
            Logger::error('Failed to load REST API module', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Load Knowledge Base module
     *
     * Initializes the Knowledge Base scanner and related functionality.
     *
     * @return void
     */
    private function loadKnowledgeBaseModule(): void
    {
        try {
            // Load Knowledge Base scanner
            $scanner = \WooAiAssistant\KnowledgeBase\Scanner::getInstance();
            $this->registerModule('knowledge_base_scanner', $scanner);

            Logger::info('Knowledge Base module loaded successfully');
        } catch (Exception $e) {
            Logger::error('Failed to load Knowledge Base module', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Load Frontend module
     *
     * Initializes the frontend widget loader for public site integration.
     *
     * @return void
     */
    private function loadFrontendModule(): void
    {
        try {
            // Load Widget Loader
            $widgetLoader = \WooAiAssistant\Frontend\WidgetLoader::getInstance();
            $this->registerModule('frontend_widget_loader', $widgetLoader);

            Logger::info('Frontend module loaded successfully');
        } catch (Exception $e) {
            Logger::error('Failed to load Frontend module', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Register AJAX hooks
     *
     * @return void
     */
    private function registerAjaxHooks(): void
    {
        // TODO: Register AJAX handlers in future tasks
        Logger::debug('AJAX hooks registration placeholder');

        do_action('woo_ai_assistant_ajax_hooks_init');
    }

    /**
     * Add plugin action links
     *
     * @param array $links Existing action links
     * @return array Modified action links
     */
    public function addPluginActionLinks(array $links): array
    {
        $settingsLink = '<a href="' . admin_url('admin.php?page=woo-ai-assistant') . '">' . __('Settings', 'woo-ai-assistant') . '</a>';
        array_unshift($links, $settingsLink);

        return $links;
    }

    /**
     * Add plugin row meta links
     *
     * @param array $links Existing meta links
     * @param string $file Plugin file
     * @return array Modified meta links
     */
    public function addPluginRowMeta(array $links, string $file): array
    {
        if ($file !== WOO_AI_ASSISTANT_BASENAME) {
            return $links;
        }

        $additionalLinks = [
            '<a href="https://github.com/woo-ai-assistant/woo-ai-assistant" target="_blank">' . __('GitHub', 'woo-ai-assistant') . '</a>',
            '<a href="https://woo-ai-assistant.com/support" target="_blank">' . __('Support', 'woo-ai-assistant') . '</a>',
            '<a href="https://woo-ai-assistant.com/docs" target="_blank">' . __('Documentation', 'woo-ai-assistant') . '</a>'
        ];

        return array_merge($links, $additionalLinks);
    }

    /**
     * Show development mode notice
     *
     * @return void
     */
    public function showDevelopmentNotice(): void
    {
        $screen = get_current_screen();

        // Only show on plugin-related pages
        if (!$screen || strpos($screen->id, 'woo-ai-assistant') === false) {
            return;
        }

        ?>
        <div class="notice notice-info">
            <p>
                <strong><?php _e('Woo AI Assistant Development Mode', 'woo-ai-assistant'); ?></strong><br>
                <?php _e('Development mode is active. Debug logging is enabled and some features may behave differently.', 'woo-ai-assistant'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Get loaded modules
     *
     * @return array Loaded modules
     */
    public function getModules(): array
    {
        return $this->modules;
    }

    /**
     * Register a module
     *
     * @param string $name Module name
     * @param object $instance Module instance
     * @return void
     */
    public function registerModule(string $name, object $instance): void
    {
        $this->modules[$name] = $instance;

        Logger::debug("Module registered: {$name}", [
            'class' => get_class($instance)
        ]);
    }

    /**
     * Get specific module instance
     *
     * @param string $name Module name
     * @return object|null Module instance or null if not found
     */
    public function getModule(string $name): ?object
    {
        return $this->modules[$name] ?? null;
    }

    /**
     * Check if plugin is initialized
     *
     * @return bool Initialization status
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * Get plugin information
     *
     * @return array Plugin information
     */
    public function getPluginInfo(): array
    {
        return [
            'name' => 'Woo AI Assistant',
            'version' => Utils::getVersion(),
            'path' => Utils::getPluginPath(),
            'url' => Utils::getPluginUrl(),
            'development_mode' => Utils::isDevelopmentMode(),
            'woocommerce_active' => Utils::isWooCommerceActive(),
            'modules_loaded' => count($this->modules),
            'cache_enabled' => Cache::getInstance()->isEnabled(),
            'logging_enabled' => Logger::getInstance()->isEnabled(),
        ];
    }

    /**
     * Shutdown handler
     *
     * Perform cleanup tasks when the plugin is shutting down.
     *
     * @return void
     */
    public function shutdown(): void
    {
        Logger::debug('Plugin shutdown initiated');

        // Allow modules to perform cleanup
        do_action('woo_ai_assistant_shutdown');

        Logger::info('Woo AI Assistant plugin shutdown complete');
    }
}