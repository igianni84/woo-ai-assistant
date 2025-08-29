<?php

/**
 * Widget Loader Class
 *
 * Handles the loading and enqueueing of the React-based chat widget on the frontend.
 * Manages script/style enqueueing, data localization, conditional loading logic,
 * and performance optimizations for the AI chat widget.
 *
 * @package WooAiAssistant
 * @subpackage Frontend
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Frontend;

use WooAiAssistant\Common\Utils;
use WooAiAssistant\Common\Traits\Singleton;
use WooAiAssistant\Api\LicenseManager;
use WooAiAssistant\Performance\CacheManager;
use WooAiAssistant\Performance\PerformanceMonitor;
use WooAiAssistant\Performance\CDNIntegration;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WidgetLoader
 *
 * Manages the frontend loading of the React chat widget with optimal performance,
 * conditional loading, and proper WordPress integration. Handles asset enqueueing,
 * data localization, and performance optimizations.
 *
 * @since 1.0.0
 */
class WidgetLoader
{
    use Singleton;

    /**
     * Widget script handle for WordPress enqueueing
     *
     * @since 1.0.0
     * @var string
     */
    private const WIDGET_SCRIPT_HANDLE = 'woo-ai-assistant-widget';

    /**
     * Widget style handle for WordPress enqueueing
     *
     * @since 1.0.0
     * @var string
     */
    private const WIDGET_STYLE_HANDLE = 'woo-ai-assistant-widget-style';

    /**
     * Widget localization handle for JavaScript data
     *
     * @since 1.0.0
     * @var string
     */
    private const WIDGET_LOCALIZE_HANDLE = 'wooAiAssistantWidget';

    /**
     * Minimum screen width for widget display (mobile-first)
     *
     * @since 1.0.0
     * @var int
     */
    private const MIN_SCREEN_WIDTH = 320;

    /**
     * Widget initialization status
     *
     * @since 1.0.0
     * @var bool
     */
    private bool $initialized = false;

    /**
     * Widget configuration
     *
     * @since 1.0.0
     * @var array
     */
    private array $config = [];

    /**
     * Loading conditions
     *
     * @since 1.0.0
     * @var array
     */
    private array $loadingConditions = [
        'enabled' => true,
        'pages' => [],
        'exclude_pages' => [],
        'user_roles' => [],
        'exclude_user_roles' => []
    ];

    /**
     * Constructor
     *
     * Initializes the widget loader by setting up hooks and configuration.
     *
     * @since 1.0.0
     */
    private function __construct()
    {
        $this->setupHooks();
        $this->loadConfiguration();
        $this->initializePerformanceFeatures();
    }

    /**
     * Setup WordPress hooks
     *
     * @since 1.0.0
     * @return void
     */
    private function setupHooks(): void
    {
        // Frontend script and style enqueueing
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets'], 10);

        // Widget container injection
        add_action('wp_footer', [$this, 'renderWidgetContainer'], 999);

        // Performance optimization hooks
        add_filter('script_loader_tag', [$this, 'optimizeScriptLoading'], 10, 3);
        add_filter('style_loader_tag', [$this, 'optimizeStyleLoading'], 10, 4);

        // Conditional loading filters
        add_filter('woo_ai_assistant_should_load_widget', [$this, 'evaluateLoadingConditions'], 10, 1);

        // Cache optimization
        add_action('wp_head', [$this, 'addPreloadHints'], 1);

        Utils::logDebug('WidgetLoader hooks initialized');
    }

    /**
     * Load widget configuration from database and defaults
     *
     * @since 1.0.0
     * @return void
     */
    private function loadConfiguration(): void
    {
        // Default configuration
        $defaults = [
            'widget_enabled' => true,
            'widget_position' => 'bottom-right',
            'widget_theme' => 'auto',
            'widget_z_index' => 9999,
            'widget_mobile_enabled' => true,
            'widget_animation_enabled' => true,
            'widget_accessibility_mode' => false,
            'widget_debug_mode' => defined('WP_DEBUG') && WP_DEBUG,
            'api_endpoint' => rest_url('woo-ai-assistant/v1/'),
            'nonce' => wp_create_nonce('woo_ai_assistant_nonce'),
            'user_id' => get_current_user_id(),
            'site_info' => $this->getSiteInfo(),
            'performance_mode' => get_option('woo_ai_assistant_performance_mode', 'balanced')
        ];

        // Merge with saved options
        $savedOptions = get_option('woo_ai_assistant_widget_settings', []);
        $this->config = array_merge($defaults, $savedOptions);

        // Load conditional loading settings
        $this->loadingConditions = get_option('woo_ai_assistant_loading_conditions', $this->loadingConditions);

        Utils::logDebug('Widget configuration loaded', $this->config);
    }

    /**
     * Initialize performance features for widget optimization
     *
     * @since 1.0.0
     * @return void
     */
    private function initializePerformanceFeatures(): void
    {
        // Initialize performance monitoring if enabled
        if (defined('WOO_AI_ASSISTANT_DEBUG') && WOO_AI_ASSISTANT_DEBUG) {
            PerformanceMonitor::getInstance();
        }

        // Initialize cache manager for FAQ and widget data caching
        CacheManager::getInstance();

        // Initialize CDN integration for asset optimization
        CDNIntegration::getInstance();

        Utils::logDebug('Performance features initialized');
    }

    /**
     * Enqueue widget scripts and styles
     *
     * @since 1.0.0
     * @return void
     */
    public function enqueueAssets(): void
    {
        // Check if widget should be loaded on current page
        if (!$this->shouldLoadWidget()) {
            return;
        }

        // Start performance monitoring for asset loading
        $performanceMonitor = PerformanceMonitor::getInstance();
        $performanceMonitor->startBenchmark('widget_asset_loading');

        try {
            // Enqueue widget stylesheet with performance optimizations
            $this->enqueueWidgetStyles();

            // Enqueue widget JavaScript with conditional loading and code splitting
            $this->enqueueWidgetScripts();

            // Localize script with widget data
            $this->localizeWidgetData();

            // End performance monitoring
            $performanceMonitor->endBenchmark('widget_asset_loading');

            Utils::logDebug('Widget assets enqueued successfully');
        } catch (\Exception $e) {
            $performanceMonitor->endBenchmark('widget_asset_loading');
            Utils::logError('Failed to enqueue widget assets: ' . $e->getMessage());
        }
    }

    /**
     * Enqueue widget stylesheet
     *
     * @since 1.0.0
     * @return void
     */
    private function enqueueWidgetStyles(): void
    {
        $styleUrl = WOO_AI_ASSISTANT_ASSETS_URL . 'css/widget.min.css';
        $stylePath = WOO_AI_ASSISTANT_ASSETS_PATH . 'css/widget.min.css';

        // Apply CDN optimization to style URL
        $cdnIntegration = CDNIntegration::getInstance();
        $styleUrl = apply_filters('woo_ai_assistant_widget_style_url', $styleUrl);

        // Get file version for cache busting
        $version = apply_filters(
            'woo_ai_assistant_asset_version',
            file_exists($stylePath) ? filemtime($stylePath) : WOO_AI_ASSISTANT_VERSION
        );

        wp_enqueue_style(
            self::WIDGET_STYLE_HANDLE,
            $styleUrl,
            [],
            $version,
            'all'
        );

        // Add inline styles for configuration-based customization
        $this->addInlineStyles();
    }

    /**
     * Enqueue widget JavaScript
     *
     * @since 1.0.0
     * @return void
     */
    private function enqueueWidgetScripts(): void
    {
        // Use code-split core bundle for initial load (smaller bundle size)
        $coreScriptUrl = WOO_AI_ASSISTANT_ASSETS_URL . 'js/widget-core.min.js';
        $coreScriptPath = WOO_AI_ASSISTANT_ASSETS_PATH . 'js/widget-core.min.js';

        // Fallback to full bundle if core bundle doesn't exist
        if (!file_exists($coreScriptPath)) {
            $coreScriptUrl = WOO_AI_ASSISTANT_ASSETS_URL . 'js/widget.min.js';
            $coreScriptPath = WOO_AI_ASSISTANT_ASSETS_PATH . 'js/widget.min.js';
        }

        // Apply CDN optimization to script URL
        $coreScriptUrl = apply_filters('woo_ai_assistant_widget_script_url', $coreScriptUrl);

        // Get file version for cache busting
        $version = apply_filters(
            'woo_ai_assistant_asset_version',
            file_exists($coreScriptPath) ? filemtime($coreScriptPath) : WOO_AI_ASSISTANT_VERSION
        );

        // Dependencies for the widget
        $dependencies = ['wp-element', 'wp-api-fetch', 'wp-i18n'];

        wp_enqueue_script(
            self::WIDGET_SCRIPT_HANDLE,
            $coreScriptUrl,
            $dependencies,
            $version,
            true
        );

        // Pre-register lazy-loaded chunks
        $this->registerLazyLoadedChunks();

        // Mark script for async/defer loading based on performance mode
        $this->optimizeScriptLoadingStrategy();
    }

    /**
     * Register lazy-loaded widget chunks
     *
     * @since 1.0.0
     * @return void
     */
    private function registerLazyLoadedChunks(): void
    {
        $chunks = [
            'widget-chat' => 'js/widget-chat.min.js',
            'widget-products' => 'js/widget-products.min.js'
        ];

        foreach ($chunks as $handle => $path) {
            $chunkUrl = WOO_AI_ASSISTANT_ASSETS_URL . $path;
            $chunkPath = WOO_AI_ASSISTANT_ASSETS_PATH . $path;

            // Only register if chunk file exists
            if (file_exists($chunkPath)) {
                $chunkUrl = apply_filters('woo_ai_assistant_widget_script_url', $chunkUrl);
                $version = apply_filters('woo_ai_assistant_asset_version', filemtime($chunkPath));

                wp_register_script(
                    $handle,
                    $chunkUrl,
                    [self::WIDGET_SCRIPT_HANDLE],
                    $version,
                    true
                );
            }
        }
    }

    /**
     * Optimize script loading strategy based on performance mode
     *
     * @since 1.0.0
     * @return void
     */
    private function optimizeScriptLoadingStrategy(): void
    {
        add_filter('script_loader_tag', function ($tag, $handle) {
            if ($handle !== self::WIDGET_SCRIPT_HANDLE) {
                return $tag;
            }

            switch ($this->config['performance_mode']) {
                case 'performance':
                    // Async loading for best performance
                    return str_replace('<script ', '<script async ', $tag);

                case 'balanced':
                    // Defer for balanced performance
                    return str_replace('<script ', '<script defer ', $tag);

                default:
                    // No modification for compatibility mode
                    return $tag;
            }
        }, 10, 2);
    }

    /**
     * Localize widget data for JavaScript
     *
     * @since 1.0.0
     * @return void
     */
    private function localizeWidgetData(): void
    {
        // DEVELOPMENT MODE: Add additional debug information
        $isDevelopment = defined('WOO_AI_DEVELOPMENT_MODE') && WOO_AI_DEVELOPMENT_MODE;
        $isLocal = $this->isLocalDevelopment();

        $widgetData = [
            'apiEndpoint' => $this->config['api_endpoint'],
            'nonce' => $this->config['nonce'],
            'userId' => $this->config['user_id'],
            'siteInfo' => $this->config['site_info'],
            'config' => [
                'position' => $this->config['widget_position'],
                'theme' => $this->config['widget_theme'],
                'zIndex' => $this->config['widget_z_index'],
                'mobileEnabled' => $this->config['widget_mobile_enabled'],
                'animationEnabled' => $this->config['widget_animation_enabled'],
                'accessibilityMode' => $this->config['widget_accessibility_mode'],
                'debugMode' => $this->config['widget_debug_mode'] || $isDevelopment || $isLocal
            ],
            'strings' => [
                'chat_title' => __('AI Assistant', 'woo-ai-assistant'),
                'type_message' => __('Type your message...', 'woo-ai-assistant'),
                'send_button' => __('Send', 'woo-ai-assistant'),
                'minimize' => __('Minimize', 'woo-ai-assistant'),
                'close' => __('Close', 'woo-ai-assistant'),
                'connecting' => __('Connecting...', 'woo-ai-assistant'),
                'error_message' => __('Sorry, I\'m having trouble responding right now. Please try again.', 'woo-ai-assistant'),
                'retry_button' => __('Retry', 'woo-ai-assistant'),
                'powered_by' => __('Powered by Woo AI Assistant', 'woo-ai-assistant')
            ],
            'features' => $this->getEnabledFeatures(),
            'performance' => [
                'lazyLoad' => $this->config['performance_mode'] === 'performance',
                'preloadThreshold' => $this->config['performance_mode'] === 'performance' ? 2000 : 1000,
                'cacheEnabled' => $this->config['performance_mode'] !== 'compatibility'
            ],
            'development' => [
                'isDevelopment' => $isDevelopment,
                'isLocal' => $isLocal,
                'debugLogging' => $isDevelopment || $isLocal
            ]
        ];

        // Add demo message for development mode
        if ($isDevelopment || $isLocal) {
            $widgetData['strings']['demo_message'] = __('Development Mode: This is a demo of the AI Assistant widget. API calls may be mocked.', 'woo-ai-assistant');
        }

        wp_localize_script(
            self::WIDGET_SCRIPT_HANDLE,
            self::WIDGET_LOCALIZE_HANDLE,
            $widgetData
        );

        Utils::logDebug('Widget data localized', [
            'development' => $isDevelopment,
            'local' => $isLocal,
            'features_count' => count($widgetData['features'])
        ]);
    }

    /**
     * Add inline styles for widget customization
     *
     * @since 1.0.0
     * @return void
     */
    private function addInlineStyles(): void
    {
        $customCss = '';

        // Position-based styles
        $position = $this->config['widget_position'];
        if ($position !== 'bottom-right') {
            $customCss .= $this->generatePositionCSS($position);
        }

        // Z-index customization
        if ($this->config['widget_z_index'] !== 9999) {
            $customCss .= ".woo-ai-widget { z-index: {$this->config['widget_z_index']} !important; }";
        }

        // Mobile-specific styles
        if (!$this->config['widget_mobile_enabled']) {
            $customCss .= "@media (max-width: 768px) { .woo-ai-widget { display: none !important; } }";
        }

        // Accessibility mode styles
        if ($this->config['widget_accessibility_mode']) {
            $customCss .= $this->generateAccessibilityCSS();
        }

        if (!empty($customCss)) {
            wp_add_inline_style(self::WIDGET_STYLE_HANDLE, $customCss);
        }
    }

    /**
     * Generate position-based CSS
     *
     * @since 1.0.0
     * @param string $position Widget position (bottom-right, bottom-left, top-right, top-left)
     * @return string Generated CSS
     */
    private function generatePositionCSS(string $position): string
    {
        $css = '.woo-ai-widget { ';

        switch ($position) {
            case 'bottom-left':
                $css .= 'left: 20px; right: auto; bottom: 20px; top: auto;';
                break;
            case 'top-right':
                $css .= 'right: 20px; left: auto; top: 20px; bottom: auto;';
                break;
            case 'top-left':
                $css .= 'left: 20px; right: auto; top: 20px; bottom: auto;';
                break;
            default:
                return '';
        }

        $css .= ' }';
        return $css;
    }

    /**
     * Generate accessibility mode CSS
     *
     * @since 1.0.0
     * @return string Generated CSS
     */
    private function generateAccessibilityCSS(): string
    {
        return '
            .woo-ai-widget { 
                font-size: 16px !important; 
                line-height: 1.6 !important; 
            }
            .woo-ai-widget button { 
                min-height: 44px !important; 
                min-width: 44px !important; 
            }
            .woo-ai-widget .chat-message { 
                padding: 12px !important; 
                margin: 8px 0 !important; 
            }
        ';
    }

    /**
     * Render widget container in footer
     *
     * @since 1.0.0
     * @return void
     */
    public function renderWidgetContainer(): void
    {
        // Double-check if widget should be loaded
        if (!$this->shouldLoadWidget()) {
            Utils::logDebug('Widget container NOT rendered: shouldLoadWidget returned false');
            return;
        }

        // Add debug information for development
        $debugInfo = '';
        if (defined('WOO_AI_DEVELOPMENT_MODE') && WOO_AI_DEVELOPMENT_MODE) {
            $debugInfo = '<!-- Woo AI Assistant Widget Debug Info: Development Mode Active -->';
        } elseif ($this->isLocalDevelopment()) {
            $debugInfo = '<!-- Woo AI Assistant Widget Debug Info: Local Development Detected -->';
        }

        // Render widget container with proper accessibility attributes
        echo $debugInfo . '
<div id="woo-ai-assistant-widget-root" 
                   class="woo-ai-widget-container" 
                   role="complementary" 
                   aria-label="' . esc_attr__('AI Chat Assistant', 'woo-ai-assistant') . '"
                   data-widget-initialized="false"
                   data-debug-mode="' . (defined('WOO_AI_DEVELOPMENT_MODE') && WOO_AI_DEVELOPMENT_MODE ? 'true' : 'false') . '">
                   <noscript>
                       <p>' . esc_html__('This AI chat widget requires JavaScript to function properly.', 'woo-ai-assistant') . '</p>
                   </noscript>
              </div>';

        Utils::logDebug('Widget container rendered successfully', [
            'development_mode' => defined('WOO_AI_DEVELOPMENT_MODE') ? WOO_AI_DEVELOPMENT_MODE : false,
            'local_development' => $this->isLocalDevelopment()
        ]);
    }

    /**
     * Optimize script loading with async/defer attributes
     *
     * @since 1.0.0
     * @param string $tag Script tag HTML
     * @param string $handle Script handle
     * @param string $src Script source URL
     * @return string Modified script tag
     */
    public function optimizeScriptLoading(string $tag, string $handle, string $src): string
    {
        if ($handle !== self::WIDGET_SCRIPT_HANDLE) {
            return $tag;
        }

        // Add defer attribute for better performance
        if ($this->config['performance_mode'] === 'balanced') {
            $tag = str_replace('<script ', '<script defer ', $tag);
        }

        return $tag;
    }

    /**
     * Optimize style loading with preload hints
     *
     * @since 1.0.0
     * @param string $html Style tag HTML
     * @param string $handle Style handle
     * @param string $href Style source URL
     * @param string $media Media attribute
     * @return string Modified style tag
     */
    public function optimizeStyleLoading(string $html, string $handle, string $href, string $media): string
    {
        if ($handle !== self::WIDGET_STYLE_HANDLE) {
            return $html;
        }

        // Add preload hint for critical CSS
        if ($this->config['performance_mode'] === 'performance') {
            $html = '<link rel="preload" href="' . esc_url($href) . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">' . $html;
        }

        return $html;
    }

    /**
     * Add preload hints for better performance
     *
     * @since 1.0.0
     * @return void
     */
    public function addPreloadHints(): void
    {
        if (!$this->shouldLoadWidget()) {
            return;
        }

        // Preload critical widget assets
        if ($this->config['performance_mode'] === 'performance') {
            $scriptUrl = WOO_AI_ASSISTANT_ASSETS_URL . 'js/widget.min.js';
            echo '<link rel="preload" href="' . esc_url($scriptUrl) . '" as="script">' . "\n";

            // DNS prefetch for API endpoints
            $apiDomain = parse_url($this->config['api_endpoint'], PHP_URL_HOST);
            if ($apiDomain) {
                echo '<link rel="dns-prefetch" href="//' . esc_attr($apiDomain) . '">' . "\n";
            }
        }
    }

    /**
     * Evaluate loading conditions for the widget
     *
     * @since 1.0.0
     * @param bool $shouldLoad Initial loading decision
     * @return bool Whether widget should be loaded
     */
    public function evaluateLoadingConditions(bool $shouldLoad): bool
    {
        // DEVELOPMENT MODE: Bypass all loading conditions in development
        if (defined('WOO_AI_DEVELOPMENT_MODE') && WOO_AI_DEVELOPMENT_MODE) {
            Utils::logDebug('Development mode: All loading conditions bypassed');
            return true;
        }

        // LOCAL DEVELOPMENT: Bypass all loading conditions for local development
        if ($this->isLocalDevelopment()) {
            Utils::logDebug('Local development detected: All loading conditions bypassed');
            return true;
        }

        if (!$shouldLoad) {
            Utils::logDebug('Widget loading disabled by initial filter');
            return false;
        }

        // Check if widget is globally enabled
        if (!$this->config['widget_enabled']) {
            Utils::logDebug('Widget loading disabled: widget_enabled = false');
            return false;
        }

        // Check page-specific conditions
        if (!$this->checkPageConditions()) {
            Utils::logDebug('Widget loading disabled: page conditions failed');
            return false;
        }

        // Check user role conditions
        if (!$this->checkUserRoleConditions()) {
            Utils::logDebug('Widget loading disabled: user role conditions failed');
            return false;
        }

        // Check device conditions
        if (!$this->checkDeviceConditions()) {
            Utils::logDebug('Widget loading disabled: device conditions failed');
            return false;
        }

        Utils::logDebug('Widget loading approved: All conditions passed');
        return true;
    }

    /**
     * Check if widget should be loaded on current page
     *
     * @since 1.0.0
     * @return bool
     */
    private function shouldLoadWidget(): bool
    {
        // DEVELOPMENT MODE: Always load widget in development mode
        if (defined('WOO_AI_DEVELOPMENT_MODE') && WOO_AI_DEVELOPMENT_MODE) {
            Utils::logDebug('Development mode: Widget loading forced');
            return true;
        }

        // DEVELOPMENT FALLBACK: Check for localhost/local development indicators
        if ($this->isLocalDevelopment()) {
            Utils::logDebug('Local development detected: Widget loading forced');
            return true;
        }

        return apply_filters('woo_ai_assistant_should_load_widget', true);
    }

    /**
     * Check page-specific loading conditions
     *
     * @since 1.0.0
     * @return bool
     */
    private function checkPageConditions(): bool
    {
        global $post;

        $currentPageId = is_singular() ? get_the_ID() : 0;
        $currentPageType = $this->getCurrentPageType();

        // Check excluded pages
        if (!empty($this->loadingConditions['exclude_pages'])) {
            if (
                in_array($currentPageId, $this->loadingConditions['exclude_pages']) ||
                in_array($currentPageType, $this->loadingConditions['exclude_pages'])
            ) {
                return false;
            }
        }

        // Check included pages (if specified)
        if (!empty($this->loadingConditions['pages'])) {
            return in_array($currentPageId, $this->loadingConditions['pages']) ||
                   in_array($currentPageType, $this->loadingConditions['pages']);
        }

        return true;
    }

    /**
     * Check user role loading conditions
     *
     * @since 1.0.0
     * @return bool
     */
    private function checkUserRoleConditions(): bool
    {
        $currentUser = wp_get_current_user();
        $userRoles = $currentUser->roles ?? ['guest'];

        // Check excluded user roles
        if (!empty($this->loadingConditions['exclude_user_roles'])) {
            if (array_intersect($userRoles, $this->loadingConditions['exclude_user_roles'])) {
                return false;
            }
        }

        // Check included user roles (if specified)
        if (!empty($this->loadingConditions['user_roles'])) {
            return array_intersect($userRoles, $this->loadingConditions['user_roles']);
        }

        return true;
    }

    /**
     * Check device-specific loading conditions
     *
     * @since 1.0.0
     * @return bool
     */
    private function checkDeviceConditions(): bool
    {
        // Mobile device detection
        if (!$this->config['widget_mobile_enabled'] && wp_is_mobile()) {
            return false;
        }

        return true;
    }

    /**
     * Get current page type
     *
     * @since 1.0.0
     * @return string
     */
    private function getCurrentPageType(): string
    {
        if (is_front_page()) {
            return 'front_page';
        }
        if (is_home()) {
            return 'blog_home';
        }
        if (is_shop()) {
            return 'shop';
        }
        if (is_product_category()) {
            return 'product_category';
        }
        if (is_product_tag()) {
            return 'product_tag';
        }
        if (is_product()) {
            return 'product';
        }
        if (is_cart()) {
            return 'cart';
        }
        if (is_checkout()) {
            return 'checkout';
        }
        if (is_account_page()) {
            return 'account';
        }
        if (is_page()) {
            return 'page';
        }
        if (is_single()) {
            return 'post';
        }
        if (is_category()) {
            return 'category';
        }
        if (is_tag()) {
            return 'tag';
        }
        if (is_search()) {
            return 'search';
        }
        if (is_404()) {
            return '404';
        }

        return 'other';
    }

    /**
     * Check if running in local development environment
     *
     * @since 1.0.0
     * @return bool True if local development detected
     */
    private function isLocalDevelopment(): bool
    {
        // Check for common local development indicators
        $localIndicators = [
            'localhost',
            '127.0.0.1',
            '.local',
            '.dev',
            'staging',
            'development',
            'test'
        ];

        // Check site URL
        $siteUrl = get_home_url();
        foreach ($localIndicators as $indicator) {
            if (strpos($siteUrl, $indicator) !== false) {
                return true;
            }
        }

        // Check server name
        if (isset($_SERVER['SERVER_NAME'])) {
            foreach ($localIndicators as $indicator) {
                if (strpos($_SERVER['SERVER_NAME'], $indicator) !== false) {
                    return true;
                }
            }
        }

        // Check for MAMP/XAMPP/WAMP
        if (isset($_SERVER['SERVER_SOFTWARE'])) {
            $serverSoftware = $_SERVER['SERVER_SOFTWARE'];
            $devSoftware = ['MAMP', 'XAMPP', 'WAMP', 'Local'];

            foreach ($devSoftware as $software) {
                if (strpos($serverSoftware, $software) !== false) {
                    return true;
                }
            }
        }

        // Check WordPress debug mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return true;
        }

        return false;
    }

    /**
     * Get site information for widget context
     *
     * @since 1.0.0
     * @return array
     */
    private function getSiteInfo(): array
    {
        return [
            'name' => get_bloginfo('name'),
            'url' => get_home_url(),
            'language' => get_locale(),
            'timezone' => wp_timezone_string(),
            'currency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD',
            'page_type' => $this->getCurrentPageType(),
            'page_id' => is_singular() ? get_the_ID() : 0,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'is_mobile' => wp_is_mobile()
        ];
    }

    /**
     * Get enabled features based on license plan
     *
     * @since 1.0.0
     * @return array
     */
    private function getEnabledFeatures(): array
    {
        // DEVELOPMENT MODE: Enable all features in development
        if (defined('WOO_AI_DEVELOPMENT_MODE') && WOO_AI_DEVELOPMENT_MODE) {
            Utils::logDebug('Development mode: All features enabled');
            return [
                'basic_chat' => true,
                'product_search' => true,
                'faq_answers' => true,
                'proactive_engagement' => true,
                'custom_messages' => true,
                'conversation_history' => true,
                'coupon_generation' => true,
                'cart_actions' => true,
                'advanced_analytics' => true,
                'priority_support' => true
            ];
        }

        // LOCAL DEVELOPMENT: Enable all features for local development
        if ($this->isLocalDevelopment()) {
            Utils::logDebug('Local development detected: All features enabled');
            return [
                'basic_chat' => true,
                'product_search' => true,
                'faq_answers' => true,
                'proactive_engagement' => true,
                'custom_messages' => true,
                'conversation_history' => true,
                'coupon_generation' => true,
                'cart_actions' => true,
                'advanced_analytics' => true,
                'priority_support' => true
            ];
        }

        try {
            $licenseManager = LicenseManager::getInstance();
            $licenseStatus = $licenseManager->getLicenseStatus();
            $plan = $licenseStatus['plan'] ?? 'free';
        } catch (Exception $e) {
            Utils::logError('Error getting license status: ' . $e->getMessage());
            // Fallback to basic features if license manager fails
            $plan = 'free';
        }

        $features = [
            'basic_chat' => true,
            'product_search' => true,
            'faq_answers' => true
        ];

        if ($plan === 'pro' || $plan === 'unlimited') {
            $features['proactive_engagement'] = true;
            $features['custom_messages'] = true;
            $features['conversation_history'] = true;
        }

        if ($plan === 'unlimited') {
            $features['coupon_generation'] = true;
            $features['cart_actions'] = true;
            $features['advanced_analytics'] = true;
            $features['priority_support'] = true;
        }

        Utils::logDebug('Features determined based on plan', ['plan' => $plan, 'features' => $features]);
        return $features;
    }

    /**
     * Get widget loading status
     *
     * @since 1.0.0
     * @return bool
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * Get widget configuration
     *
     * @since 1.0.0
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Update widget configuration
     *
     * @since 1.0.0
     * @param array $newConfig New configuration values
     * @return bool Success status
     */
    public function updateConfig(array $newConfig): bool
    {
        try {
            $this->config = array_merge($this->config, $newConfig);

            // Save to database
            update_option('woo_ai_assistant_widget_settings', $this->config);

            Utils::logDebug('Widget configuration updated', $newConfig);
            return true;
        } catch (\Exception $e) {
            Utils::logError('Failed to update widget configuration: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Reset widget configuration to defaults
     *
     * @since 1.0.0
     * @return bool Success status
     */
    public function resetConfig(): bool
    {
        try {
            delete_option('woo_ai_assistant_widget_settings');
            delete_option('woo_ai_assistant_loading_conditions');

            $this->loadConfiguration();

            Utils::logDebug('Widget configuration reset to defaults');
            return true;
        } catch (\Exception $e) {
            Utils::logError('Failed to reset widget configuration: ' . $e->getMessage());
            return false;
        }
    }
}
