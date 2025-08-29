<?php

/**
 * Standalone Widget Loader Class
 *
 * Emergency fallback widget loader that works independently of the main plugin
 * architecture. Used in development mode when the main system fails to initialize.
 *
 * @package WooAiAssistant
 * @subpackage Frontend
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Frontend;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class StandaloneWidgetLoader
 *
 * Minimal widget loader that bypasses all dependencies and focuses solely
 * on getting the chat widget to load in development environments.
 *
 * @since 1.0.0
 */
class StandaloneWidgetLoader
{
    /**
     * Initialize the standalone widget loader
     *
     * @since 1.0.0
     * @return void
     */
    public static function init(): void
    {
        // Only run in development mode
        if (!self::isDevelopmentMode()) {
            return;
        }

        // Only run on frontend
        if (is_admin()) {
            return;
        }

        // Hook into WordPress
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueueAssets'], 999);
        add_action('wp_footer', [__CLASS__, 'renderWidget'], 999);

        // Log the initialization
        error_log('Woo AI Assistant: StandaloneWidgetLoader initialized');
    }

    /**
     * Enqueue widget assets
     *
     * @since 1.0.0
     * @return void
     */
    public static function enqueueAssets(): void
    {
        $assetsUrl = self::getAssetsUrl();
        $assetsPath = self::getAssetsPath();

        // Enqueue CSS
        $cssFile = $assetsPath . 'css/widget.min.css';
        if (file_exists($cssFile)) {
            wp_enqueue_style(
                'woo-ai-assistant-standalone-widget',
                $assetsUrl . 'css/widget.min.css',
                [],
                filemtime($cssFile)
            );
        }

        // Enqueue JS
        $jsFile = $assetsPath . 'js/widget.min.js';
        if (file_exists($jsFile)) {
            wp_enqueue_script(
                'woo-ai-assistant-standalone-widget',
                $assetsUrl . 'js/widget.min.js',
                ['wp-element', 'wp-api-fetch', 'wp-i18n'],
                filemtime($jsFile),
                true
            );

            // Localize script with minimal data
            wp_localize_script(
                'woo-ai-assistant-standalone-widget',
                'wooAiAssistantWidget',
                self::getWidgetConfig()
            );
        }

        error_log('Woo AI Assistant: Standalone widget assets enqueued');
    }

    /**
     * Render the widget container
     *
     * @since 1.0.0
     * @return void
     */
    public static function renderWidget(): void
    {
        echo '<!-- Woo AI Assistant Standalone Widget -->';
        echo '<div id="woo-ai-assistant-widget-root" 
                   class="woo-ai-widget-container" 
                   role="complementary" 
                   aria-label="AI Chat Assistant"
                   data-widget-initialized="false"
                   data-debug-mode="true"
                   data-standalone="true">';
        echo '    <noscript>';
        echo '        <p>This AI chat widget requires JavaScript to function properly.</p>';
        echo '    </noscript>';
        echo '</div>';
        echo '<!-- /Woo AI Assistant Standalone Widget -->';

        error_log('Woo AI Assistant: Standalone widget container rendered');
    }

    /**
     * Get widget configuration for JavaScript
     *
     * @since 1.0.0
     * @return array Widget configuration
     */
    private static function getWidgetConfig(): array
    {
        return [
            'apiEndpoint' => rest_url('woo-ai-assistant/v1/'),
            'nonce' => wp_create_nonce('woo_ai_assistant_nonce'),
            'userId' => get_current_user_id(),
            'siteInfo' => [
                'name' => get_bloginfo('name'),
                'url' => get_home_url(),
                'language' => get_locale(),
                'timezone' => wp_timezone_string(),
                'currency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD',
                'page_type' => 'unknown',
                'page_id' => is_singular() ? get_the_ID() : 0,
                'is_mobile' => wp_is_mobile()
            ],
            'config' => [
                'position' => 'bottom-right',
                'theme' => 'auto',
                'zIndex' => 9999,
                'mobileEnabled' => true,
                'animationEnabled' => true,
                'accessibilityMode' => false,
                'debugMode' => true
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
                'powered_by' => __('Powered by Woo AI Assistant', 'woo-ai-assistant'),
                'demo_message' => __('Development Mode: This is a demo of the AI Assistant widget. API calls may be mocked.', 'woo-ai-assistant')
            ],
            'features' => [
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
            ],
            'performance' => [
                'lazyLoad' => false,
                'preloadThreshold' => 1000,
                'cacheEnabled' => false
            ],
            'development' => [
                'isDevelopment' => true,
                'isLocal' => true,
                'debugLogging' => true,
                'standalone' => true
            ]
        ];
    }

    /**
     * Check if development mode is active
     *
     * @since 1.0.0
     * @return bool True if development mode is active
     */
    private static function isDevelopmentMode(): bool
    {
        // Check plugin constant
        if (defined('WOO_AI_DEVELOPMENT_MODE')) {
            return WOO_AI_DEVELOPMENT_MODE;
        }

        // Check WordPress debug
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return true;
        }

        // Check server indicators
        if (isset($_SERVER['SERVER_NAME'])) {
            $devIndicators = ['localhost', '127.0.0.1', '.local', '.dev'];
            foreach ($devIndicators as $indicator) {
                if (strpos($_SERVER['SERVER_NAME'], $indicator) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get assets URL
     *
     * @since 1.0.0
     * @return string Assets URL
     */
    private static function getAssetsUrl(): string
    {
        if (defined('WOO_AI_ASSISTANT_ASSETS_URL')) {
            return WOO_AI_ASSISTANT_ASSETS_URL;
        }

        return plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/';
    }

    /**
     * Get assets path
     *
     * @since 1.0.0
     * @return string Assets path
     */
    private static function getAssetsPath(): string
    {
        if (defined('WOO_AI_ASSISTANT_ASSETS_PATH')) {
            return WOO_AI_ASSISTANT_ASSETS_PATH;
        }

        return plugin_dir_path(dirname(dirname(__FILE__))) . 'assets/';
    }
}
