<?php

/**
 * Widget Loader Class
 *
 * Handles loading and initialization of the AI Assistant widget on the frontend.
 * Manages widget asset enqueuing, context detection, and data localization for
 * seamless integration with WordPress frontend and WooCommerce pages.
 *
 * @package WooAiAssistant
 * @subpackage Frontend
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Frontend;

use WooAiAssistant\Common\Traits\Singleton;
use WooAiAssistant\Common\Logger;
use WooAiAssistant\Common\Utils;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WidgetLoader
 *
 * Manages frontend widget loading and initialization.
 *
 * @since 1.0.0
 */
class WidgetLoader
{
    use Singleton;

    /**
     * Asset version for cache busting
     *
     * @var string
     */
    private string $version;

    /**
     * Plugin URL for asset paths
     *
     * @var string
     */
    private string $pluginUrl;

    /**
     * Whether widget assets have been enqueued
     *
     * @var bool
     */
    private bool $widgetAssetsEnqueued = false;

    /**
     * Widget configuration options
     *
     * @var array
     */
    private array $widgetConfig = [];

    /**
     * Initialize the widget loader
     *
     * @return void
     */
    protected function init(): void
    {
        $this->version = Utils::getVersion();
        $this->pluginUrl = Utils::getPluginUrl();

        // Only load on frontend, not in admin
        if (!is_admin()) {
            // Hook into WordPress frontend asset system
            add_action('wp_enqueue_scripts', [$this, 'conditionallyEnqueueWidgetAssets'], 20);

            // Add widget container to footer
            add_action('wp_footer', [$this, 'renderWidgetContainer']);

            // Add widget initialization styles to head
            add_action('wp_head', [$this, 'addInitializationStyles']);
        }

        // Register assets early
        add_action('init', [$this, 'registerWidgetAssets'], 5);

        Logger::debug('WidgetLoader initialized');
    }

    /**
     * Register widget assets
     *
     * @return void
     */
    public function registerWidgetAssets(): void
    {
        // Register widget CSS
        wp_register_style(
            'woo-ai-assistant-widget',
            $this->pluginUrl . 'assets/css/widget.css',
            [],
            $this->version,
            'all'
        );

        // Register widget JavaScript
        wp_register_script(
            'woo-ai-assistant-widget',
            $this->pluginUrl . 'assets/js/widget.js',
            ['jquery'],
            $this->version,
            true
        );

        // Register vendor dependencies if needed
        if (!wp_script_is('react', 'registered') && file_exists($this->pluginUrl . 'assets/js/vendor.js')) {
            wp_register_script(
                'woo-ai-assistant-vendor',
                $this->pluginUrl . 'assets/js/vendor.js',
                [],
                $this->version,
                true
            );
        }

        Logger::debug('Widget assets registered');
    }

    /**
     * Conditionally enqueue widget assets based on page context
     *
     * @return void
     */
    public function conditionallyEnqueueWidgetAssets(): void
    {
        // Skip if already enqueued
        if ($this->widgetAssetsEnqueued) {
            return;
        }

        // Skip in admin area
        if (is_admin()) {
            return;
        }

        // Check if widget should be loaded on this page
        if (!$this->shouldLoadWidgetOnCurrentPage()) {
            return;
        }

        $this->enqueueWidgetAssets();

        Logger::debug('Widget assets conditionally enqueued', [
            'page_type' => $this->getCurrentPageType(),
            'user_id' => Utils::getCurrentUserId(),
            'is_woocommerce_page' => $this->isWooCommercePage()
        ]);
    }

    /**
     * Enqueue widget assets and localize data
     *
     * @return void
     */
    public function enqueueWidgetAssets(): void
    {
        // Enqueue styles
        wp_enqueue_style('woo-ai-assistant-widget');

        // Enqueue scripts with dependencies
        $dependencies = ['jquery'];
        if (wp_script_is('woo-ai-assistant-vendor', 'registered')) {
            $dependencies[] = 'woo-ai-assistant-vendor';
        }

        wp_enqueue_script('woo-ai-assistant-widget');

        // Localize script with widget configuration and context data
        wp_localize_script('woo-ai-assistant-widget', 'wooAiAssistantWidget', [
            // API Configuration
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('woo-ai-assistant/v1/'),
            'nonce' => Utils::generateNonce('widget_action'),
            'chatNonce' => Utils::generateNonce('chat_message'),

            // Plugin Configuration
            'pluginUrl' => $this->pluginUrl,
            'version' => $this->version,
            'developmentMode' => Utils::isDevelopmentMode(),

            // User Context
            'user' => $this->getCurrentUserContext(),
            'page' => $this->getCurrentPageContext(),
            'cart' => $this->getCartContext(),
            'woocommerce' => $this->getWooCommerceContext(),

            // Widget Settings
            'settings' => $this->getWidgetSettings(),
            'features' => $this->getEnabledFeatures(),

            // Localized Strings
            'strings' => $this->getLocalizedStrings(),

            // Performance Settings
            'config' => [
                'autoStart' => $this->shouldAutoStartWidget(),
                'loadDelay' => 1000, // 1 second delay for performance
                'maxRetries' => 3,
                'apiTimeout' => 30000, // 30 seconds
            ]
        ]);

        $this->widgetAssetsEnqueued = true;

        Logger::debug('Widget assets enqueued with localized data');
    }

    /**
     * Render widget container in footer
     *
     * @return void
     */
    public function renderWidgetContainer(): void
    {
        // Only render if assets are enqueued
        if (!$this->widgetAssetsEnqueued) {
            return;
        }

        // Widget container with accessibility attributes
        ?>
        <div id="woo-ai-assistant-widget-container" 
             role="complementary" 
             aria-label="<?php esc_attr_e('AI Shopping Assistant', 'woo-ai-assistant'); ?>"
             data-widget-ready="false"
             style="display: none;">
            <div id="woo-ai-assistant-widget-root"></div>
        </div>
        <?php
    }

    /**
     * Add initialization styles to head for better UX
     *
     * @return void
     */
    public function addInitializationStyles(): void
    {
        // Only add if widget will be loaded
        if (!$this->shouldLoadWidgetOnCurrentPage()) {
            return;
        }

        // Critical CSS for widget initialization
        ?>
        <style id="woo-ai-assistant-widget-init">
            #woo-ai-assistant-widget-container {
                position: fixed;
                bottom: 20px;
                right: 20px;
                z-index: 999999;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            }
            
            #woo-ai-assistant-widget-container[data-widget-ready="false"] {
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.3s ease;
            }
            
            #woo-ai-assistant-widget-container[data-widget-ready="true"] {
                opacity: 1;
                pointer-events: auto;
            }

            /* Loading indicator */
            .woo-ai-assistant-loading {
                display: inline-block;
                width: 20px;
                height: 20px;
                border: 2px solid rgba(255,255,255,.3);
                border-radius: 50%;
                border-top-color: #ffffff;
                animation: woo-ai-spin 1s ease-in-out infinite;
            }

            @keyframes woo-ai-spin {
                to { transform: rotate(360deg); }
            }
        </style>
        <?php
    }

    /**
     * Check if widget should be loaded on current page
     *
     * @return bool True if widget should be loaded
     */
    private function shouldLoadWidgetOnCurrentPage(): bool
    {
        // Don't load on login, register, or checkout pages unless specifically enabled
        if (is_page(['login', 'register']) || (function_exists('is_account_page') && is_account_page() && is_page('lost-password'))) {
            return false;
        }

        // Don't load on admin pages
        if (is_admin()) {
            return false;
        }

        // Load on all frontend pages by default (can be customized via settings)
        $allowedPages = apply_filters('woo_ai_assistant_widget_allowed_pages', [
            'shop', 'product', 'cart', 'checkout', 'account', 'home', 'page', 'post'
        ]);

        $currentPageType = $this->getCurrentPageType();

        return in_array($currentPageType, $allowedPages);
    }

    /**
     * Get current page type for context
     *
     * @return string Current page type
     */
    private function getCurrentPageType(): string
    {
        if (is_front_page()) {
            return 'home';
        } elseif (function_exists('is_shop') && is_shop()) {
            return 'shop';
        } elseif (function_exists('is_product') && is_product()) {
            return 'product';
        } elseif (function_exists('is_cart') && is_cart()) {
            return 'cart';
        } elseif (function_exists('is_checkout') && is_checkout()) {
            return 'checkout';
        } elseif (function_exists('is_account_page') && is_account_page()) {
            return 'account';
        } elseif (is_page()) {
            return 'page';
        } elseif (is_single()) {
            return 'post';
        } elseif (is_category() || is_tag() || is_archive()) {
            return 'archive';
        } elseif (is_search()) {
            return 'search';
        }

        return 'unknown';
    }

    /**
     * Check if current page is WooCommerce related
     *
     * @return bool True if WooCommerce page
     */
    private function isWooCommercePage(): bool
    {
        if (!Utils::isWooCommerceActive()) {
            return false;
        }

        return function_exists('is_woocommerce') && is_woocommerce();
    }

    /**
     * Get current user context
     *
     * @return array User context data
     */
    private function getCurrentUserContext(): array
    {
        $userId = Utils::getCurrentUserId();

        $context = [
            'isLoggedIn' => $userId > 0,
            'userId' => $userId,
            'canManageWoocommerce' => Utils::currentUserCan('manage_woocommerce'),
        ];

        if ($userId > 0) {
            $user = get_userdata($userId);
            if ($user) {
                $context['displayName'] = $user->display_name;
                $context['email'] = $user->user_email;
                $context['roles'] = $user->roles;
            }
        }

        return $context;
    }

    /**
     * Get current page context
     *
     * @return array Page context data
     */
    private function getCurrentPageContext(): array
    {
        global $post;

        $context = [
            'type' => $this->getCurrentPageType(),
            'isWoocommerce' => $this->isWooCommercePage(),
            'url' => get_permalink(),
            'title' => get_the_title(),
        ];

        // Add post-specific data
        if ($post) {
            $context['postId'] = $post->ID;
            $context['postType'] = $post->post_type;
        }

        // Add product-specific data
        if ($this->getCurrentPageType() === 'product' && Utils::isWooCommerceActive()) {
            global $product;
            if ($product && is_a($product, 'WC_Product')) {
                $context['product'] = [
                    'id' => $product->get_id(),
                    'name' => $product->get_name(),
                    'price' => $product->get_price(),
                    'type' => $product->get_type(),
                    'inStock' => $product->is_in_stock(),
                    'categories' => wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names']),
                ];
            }
        }

        return $context;
    }

    /**
     * Get cart context for WooCommerce
     *
     * @return array Cart context data
     */
    private function getCartContext(): array
    {
        if (!Utils::isWooCommerceActive() || !function_exists('WC')) {
            return ['available' => false];
        }

        $cart = WC()->cart;
        if (!$cart) {
            return ['available' => false];
        }

        return [
            'available' => true,
            'itemCount' => $cart->get_cart_contents_count(),
            'total' => $cart->get_cart_total(),
            'subtotal' => $cart->get_cart_subtotal(),
            'isEmpty' => $cart->is_empty(),
            'needsShipping' => $cart->needs_shipping(),
        ];
    }

    /**
     * Get WooCommerce context data
     *
     * @return array WooCommerce context data
     */
    private function getWooCommerceContext(): array
    {
        return [
            'active' => Utils::isWooCommerceActive(),
            'version' => Utils::getWooCommerceVersion(),
            'currency' => Utils::isWooCommerceActive() ? get_woocommerce_currency() : 'USD',
            'currencySymbol' => Utils::isWooCommerceActive() ? get_woocommerce_currency_symbol() : '$',
        ];
    }

    /**
     * Get widget settings from admin configuration
     *
     * @return array Widget settings
     */
    private function getWidgetSettings(): array
    {
        // Default settings - these would normally come from admin settings
        return apply_filters('woo_ai_assistant_widget_settings', [
            'position' => 'bottom-right',
            'theme' => 'auto',
            'showWelcomeMessage' => true,
            'enableProductRecommendations' => true,
            'enableCouponGeneration' => true,
            'maxConversationHistory' => 50,
            'autoExpandOnMobile' => false,
        ]);
    }

    /**
     * Get enabled features based on license and settings
     *
     * @return array Enabled features
     */
    private function getEnabledFeatures(): array
    {
        // In development mode, all features are enabled
        if (Utils::isDevelopmentMode()) {
            return [
                'chat' => true,
                'productRecommendations' => true,
                'couponGeneration' => true,
                'orderTracking' => true,
                'proactiveSuggestions' => true,
                'analyticsTracking' => true,
            ];
        }

        // In production, features would be determined by license level
        return apply_filters('woo_ai_assistant_enabled_features', [
            'chat' => true,
            'productRecommendations' => false,
            'couponGeneration' => false,
            'orderTracking' => false,
            'proactiveSuggestions' => false,
            'analyticsTracking' => false,
        ]);
    }

    /**
     * Check if widget should auto-start
     *
     * @return bool True if should auto-start
     */
    private function shouldAutoStartWidget(): bool
    {
        // Auto-start based on page type and user behavior
        $pageType = $this->getCurrentPageType();

        // Auto-start on product pages or if user has items in cart
        return in_array($pageType, ['product', 'cart', 'checkout']) ||
               ($this->getCartContext()['itemCount'] ?? 0) > 0;
    }

    /**
     * Get localized strings for JavaScript
     *
     * @return array Localized strings
     */
    private function getLocalizedStrings(): array
    {
        return [
            'chatTitle' => __('AI Shopping Assistant', 'woo-ai-assistant'),
            'chatPlaceholder' => __('Ask me anything about products...', 'woo-ai-assistant'),
            'chatSend' => __('Send', 'woo-ai-assistant'),
            'chatMinimize' => __('Minimize chat', 'woo-ai-assistant'),
            'chatClose' => __('Close chat', 'woo-ai-assistant'),
            'chatLoading' => __('AI is thinking...', 'woo-ai-assistant'),
            'chatError' => __('Sorry, something went wrong. Please try again.', 'woo-ai-assistant'),
            'chatWelcome' => __('Hi! How can I help you find what you\'re looking for?', 'woo-ai-assistant'),
            'chatOffline' => __('Chat is currently offline. Please try again later.', 'woo-ai-assistant'),
            'addToCart' => __('Add to Cart', 'woo-ai-assistant'),
            'viewProduct' => __('View Product', 'woo-ai-assistant'),
            'applyCoupon' => __('Apply Coupon', 'woo-ai-assistant'),
            'copyCode' => __('Copy Code', 'woo-ai-assistant'),
            'codeCopied' => __('Code copied!', 'woo-ai-assistant'),
            'retry' => __('Retry', 'woo-ai-assistant'),
            'poweredBy' => __('Powered by Woo AI Assistant', 'woo-ai-assistant'),
        ];
    }

    /**
     * Get widget configuration
     *
     * @return array Widget configuration
     */
    public function getWidgetConfig(): array
    {
        return $this->widgetConfig;
    }

    /**
     * Check if widget assets are enqueued
     *
     * @return bool Assets enqueued status
     */
    public function areWidgetAssetsEnqueued(): bool
    {
        return $this->widgetAssetsEnqueued;
    }

    /**
     * Force enqueue widget assets (for manual loading)
     *
     * @return void
     */
    public function forceEnqueueWidgetAssets(): void
    {
        $this->widgetAssetsEnqueued = false;
        $this->enqueueWidgetAssets();
    }

    /**
     * Get current page URL for context
     *
     * @return string Current page URL
     */
    public function getCurrentPageUrl(): string
    {
        return get_permalink() ?: home_url();
    }

    /**
     * Check if user is on mobile device
     *
     * @return bool True if mobile device
     */
    public function isMobileDevice(): bool
    {
        return wp_is_mobile();
    }
}