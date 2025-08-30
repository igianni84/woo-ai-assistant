<?php

/**
 * Config Endpoint Class
 *
 * Handles REST API endpoints for widget configuration management.
 * Provides configuration data for the frontend widget and admin settings.
 *
 * @package WooAiAssistant
 * @subpackage RestApi\Endpoints
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\RestApi\Endpoints;

use WooAiAssistant\Common\Logger;
use WooAiAssistant\Common\Utils;
use WooAiAssistant\Config\ApiConfiguration;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ConfigEndpoint
 *
 * Manages configuration-related REST API endpoints for widget settings.
 *
 * @since 1.0.0
 */
class ConfigEndpoint
{
    /**
     * Register configuration routes
     *
     * @param string $namespace API namespace
     * @return void
     */
    public function registerRoutes(string $namespace): void
    {
        // Get widget configuration (public)
        register_rest_route(
            $namespace,
            '/config/widget',
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getWidgetConfig'],
                'permission_callback' => [$this, 'checkPublicPermission'],
                'args' => [
                    'context' => [
                        'type' => 'string',
                        'description' => 'Context (page, product, cart, checkout)',
                        'default' => 'general',
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'product_id' => [
                        'type' => 'integer',
                        'description' => 'Product ID for product-specific config',
                        'sanitize_callback' => 'absint'
                    ],
                    'category_id' => [
                        'type' => 'integer',
                        'description' => 'Category ID for category-specific config',
                        'sanitize_callback' => 'absint'
                    ]
                ]
            ]
        );

        // Update widget settings (admin only)
        register_rest_route(
            $namespace,
            '/config/widget',
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateWidgetConfig'],
                'permission_callback' => [$this, 'checkAdminPermission'],
                'args' => [
                    'position' => [
                        'type' => 'string',
                        'description' => 'Widget position (bottom-right, bottom-left, top-right, top-left)',
                        'validate_callback' => [$this, 'validatePosition'],
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'theme' => [
                        'type' => 'string',
                        'description' => 'Widget theme (light, dark, auto)',
                        'validate_callback' => [$this, 'validateTheme'],
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'greeting_message' => [
                        'type' => 'string',
                        'description' => 'Greeting message',
                        'validate_callback' => [$this, 'validateGreeting'],
                        'sanitize_callback' => 'sanitize_textarea_field'
                    ],
                    'enabled' => [
                        'type' => 'boolean',
                        'description' => 'Whether the widget is enabled'
                    ],
                    'pages' => [
                        'type' => 'array',
                        'description' => 'Pages where widget should appear',
                        'items' => [
                            'type' => 'string'
                        ],
                        'default' => []
                    ]
                ]
            ]
        );

        // Get system configuration (admin only)
        register_rest_route(
            $namespace,
            '/config/system',
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getSystemConfig'],
                'permission_callback' => [$this, 'checkAdminPermission']
            ]
        );

        // Get feature flags
        register_rest_route(
            $namespace,
            '/config/features',
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getFeatureFlags'],
                'permission_callback' => [$this, 'checkPublicPermission'],
                'args' => [
                    'user_id' => [
                        'type' => 'integer',
                        'description' => 'User ID for user-specific features',
                        'sanitize_callback' => 'absint'
                    ]
                ]
            ]
        );

        Logger::debug('Config endpoints registered');
    }

    /**
     * Get widget configuration
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public function getWidgetConfig(WP_REST_Request $request): WP_REST_Response
    {
        $context = $request->get_param('context') ?: 'general';
        $productId = $request->get_param('product_id');
        $categoryId = $request->get_param('category_id');
        $userId = get_current_user_id();

        Logger::info('Widget config requested', [
            'context' => $context,
            'product_id' => $productId,
            'category_id' => $categoryId,
            'user_id' => $userId
        ]);

        $config = [
            'version' => Utils::getVersion(),
            'api_base_url' => rest_url('woo-ai-assistant/v1'),
            'nonce' => wp_create_nonce('woo_ai_assistant_nonce'),
            'context' => $context,
            'enabled' => $this->isWidgetEnabled($context),
            'appearance' => [
                'position' => get_option('woo_ai_widget_position', 'bottom-right'),
                'theme' => get_option('woo_ai_widget_theme', 'light'),
                'custom_css' => get_option('woo_ai_widget_custom_css', ''),
                'avatar_url' => Utils::getPluginUrl() . 'assets/images/avatar-default.svg',
                'brand_color' => get_option('woo_ai_widget_brand_color', '#0073aa')
            ],
            'messages' => [
                'greeting' => get_option('woo_ai_greeting_message', __('Hi! How can I help you today?', 'woo-ai-assistant')),
                'offline' => get_option('woo_ai_offline_message', __('We\'re currently offline. Please leave a message.', 'woo-ai-assistant')),
                'error' => __('Something went wrong. Please try again.', 'woo-ai-assistant'),
                'typing' => __('AI is typing...', 'woo-ai-assistant')
            ],
            'user' => [
                'id' => $userId,
                'is_logged_in' => is_user_logged_in(),
                'display_name' => $userId ? get_userdata($userId)->display_name : '',
                'avatar_url' => $userId ? get_avatar_url($userId, 32) : ''
            ],
            'woocommerce' => [
                'active' => Utils::isWooCommerceActive(),
                'currency' => Utils::isWooCommerceActive() ? get_woocommerce_currency() : 'USD',
                'currency_symbol' => Utils::isWooCommerceActive() ? get_woocommerce_currency_symbol() : '$',
                'cart_url' => Utils::isWooCommerceActive() ? wc_get_cart_url() : '',
                'checkout_url' => Utils::isWooCommerceActive() ? wc_get_checkout_url() : ''
            ],
            'features' => $this->getFeatureFlagsForUser($userId),
            'rate_limiting' => [
                'messages_per_minute' => 10,
                'actions_per_minute' => 5
            ]
        ];

        // Add context-specific configuration
        if ($context === 'product' && $productId) {
            $config['product'] = $this->getProductContext($productId);
        }

        if ($context === 'category' && $categoryId) {
            $config['category'] = $this->getCategoryContext($categoryId);
        }

        // Apply filters to allow customization
        $config = apply_filters('woo_ai_assistant_widget_config', $config, $context, $request);
        $config = apply_filters("woo_ai_assistant_widget_config_{$context}", $config, $request);

        return new WP_REST_Response($config, 200);
    }

    /**
     * Update widget configuration (admin only)
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public function updateWidgetConfig(WP_REST_Request $request): WP_REST_Response
    {
        Logger::info('Widget config update requested');

        $updates = [];
        $params = $request->get_params();

        // Process each configuration parameter
        foreach ($params as $key => $value) {
            switch ($key) {
                case 'position':
                    update_option('woo_ai_widget_position', $value);
                    $updates['position'] = $value;
                    break;

                case 'theme':
                    update_option('woo_ai_widget_theme', $value);
                    $updates['theme'] = $value;
                    break;

                case 'greeting_message':
                    update_option('woo_ai_greeting_message', $value);
                    $updates['greeting_message'] = $value;
                    break;

                case 'enabled':
                    update_option('woo_ai_widget_enabled', $value ? 1 : 0);
                    $updates['enabled'] = $value;
                    break;

                case 'pages':
                    update_option('woo_ai_widget_pages', $value);
                    $updates['pages'] = $value;
                    break;
            }
        }

        do_action('woo_ai_assistant_widget_config_updated', $updates, $request);

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Widget configuration updated successfully',
            'data' => $updates
        ], 200);
    }

    /**
     * Get system configuration (admin only)
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public function getSystemConfig(WP_REST_Request $request): WP_REST_Response
    {
        $apiConfig = ApiConfiguration::getInstance();

        $config = [
            'plugin' => [
                'version' => Utils::getVersion(),
                'path' => Utils::getPluginPath(),
                'url' => Utils::getPluginUrl(),
                'development_mode' => Utils::isDevelopmentMode()
            ],
            'wordpress' => [
                'version' => get_bloginfo('version'),
                'multisite' => is_multisite(),
                'debug' => defined('WP_DEBUG') && WP_DEBUG,
                'memory_limit' => WP_MEMORY_LIMIT,
                'locale' => get_locale()
            ],
            'woocommerce' => [
                'active' => Utils::isWooCommerceActive(),
                'version' => Utils::isWooCommerceActive() ? WC_VERSION : null,
                'currency' => Utils::isWooCommerceActive() ? get_woocommerce_currency() : null
            ],
            'server' => [
                'php_version' => PHP_VERSION,
                'memory_usage' => [
                    'current' => memory_get_usage(true),
                    'peak' => memory_get_peak_usage(true)
                ],
                'extensions' => [
                    'curl' => extension_loaded('curl'),
                    'openssl' => extension_loaded('openssl'),
                    'json' => extension_loaded('json'),
                    'mbstring' => extension_loaded('mbstring')
                ]
            ],
            'api' => [
                'has_license' => $apiConfig->hasValidLicense(),
                'license_status' => $apiConfig->getLicenseStatus(),
                'plan' => $apiConfig->getCurrentPlan(),
                'usage' => $apiConfig->getUsageStats()
            ]
        ];

        return new WP_REST_Response($config, 200);
    }

    /**
     * Get feature flags
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public function getFeatureFlags(WP_REST_Request $request): WP_REST_Response
    {
        $userId = $request->get_param('user_id') ?: get_current_user_id();
        $features = $this->getFeatureFlagsForUser($userId);

        return new WP_REST_Response([
            'success' => true,
            'features' => $features
        ], 200);
    }

    /**
     * Check public permission
     *
     * @param WP_REST_Request $request Request object
     * @return bool True if permission granted
     */
    public function checkPublicPermission(WP_REST_Request $request): bool
    {
        return true;
    }

    /**
     * Check admin permission
     *
     * @param WP_REST_Request $request Request object
     * @return bool|WP_Error True if permission granted
     */
    public function checkAdminPermission(WP_REST_Request $request)
    {
        if (!current_user_can('manage_woocommerce')) {
            return new WP_Error(
                'insufficient_permissions',
                'Administrator permissions required',
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Check if widget is enabled for context
     *
     * @param string $context Context name
     * @return bool True if enabled
     */
    private function isWidgetEnabled(string $context): bool
    {
        $enabled = get_option('woo_ai_widget_enabled', 1);
        if (!$enabled) {
            return false;
        }

        $allowedPages = get_option('woo_ai_widget_pages', ['all']);
        if (in_array('all', $allowedPages)) {
            return true;
        }

        return in_array($context, $allowedPages);
    }

    /**
     * Get feature flags for user
     *
     * @param int $userId User ID
     * @return array Feature flags
     */
    private function getFeatureFlagsForUser(int $userId): array
    {
        $apiConfig = ApiConfiguration::getInstance();
        $plan = $apiConfig->getCurrentPlan();

        $features = [
            'chat_enabled' => true,
            'product_recommendations' => Utils::isWooCommerceActive(),
            'coupon_generation' => Utils::isWooCommerceActive() && in_array($plan, ['Pro', 'Unlimited']),
            'advanced_analytics' => in_array($plan, ['Pro', 'Unlimited']),
            'human_handoff' => $plan === 'Unlimited',
            'custom_branding' => in_array($plan, ['Pro', 'Unlimited']),
            'priority_support' => in_array($plan, ['Pro', 'Unlimited']),
            'api_access' => $plan === 'Unlimited'
        ];

        return apply_filters('woo_ai_assistant_feature_flags', $features, $userId, $plan);
    }

    /**
     * Get product context information
     *
     * @param int $productId Product ID
     * @return array|null Product context
     */
    private function getProductContext(int $productId): ?array
    {
        if (!Utils::isWooCommerceActive()) {
            return null;
        }

        $product = wc_get_product($productId);
        if (!$product) {
            return null;
        }

        return [
            'id' => $productId,
            'name' => $product->get_name(),
            'type' => $product->get_type(),
            'price' => $product->get_price(),
            'in_stock' => $product->is_in_stock(),
            'categories' => wp_get_post_terms($productId, 'product_cat', ['fields' => 'names'])
        ];
    }

    /**
     * Get category context information
     *
     * @param int $categoryId Category ID
     * @return array|null Category context
     */
    private function getCategoryContext(int $categoryId): ?array
    {
        $category = get_term($categoryId, 'product_cat');
        if (!$category || is_wp_error($category)) {
            return null;
        }

        return [
            'id' => $categoryId,
            'name' => $category->name,
            'slug' => $category->slug,
            'product_count' => $category->count
        ];
    }

    /**
     * Validate widget position
     *
     * @param string $position Widget position
     * @return bool|WP_Error True if valid
     */
    public function validatePosition($position)
    {
        $validPositions = ['bottom-right', 'bottom-left', 'top-right', 'top-left'];

        if (!in_array($position, $validPositions)) {
            return new WP_Error(
                'invalid_position',
                'Invalid position. Allowed: ' . implode(', ', $validPositions),
                ['status' => 400]
            );
        }

        return true;
    }

    /**
     * Validate widget theme
     *
     * @param string $theme Widget theme
     * @return bool|WP_Error True if valid
     */
    public function validateTheme($theme)
    {
        $validThemes = ['light', 'dark', 'auto'];

        if (!in_array($theme, $validThemes)) {
            return new WP_Error(
                'invalid_theme',
                'Invalid theme. Allowed: ' . implode(', ', $validThemes),
                ['status' => 400]
            );
        }

        return true;
    }

    /**
     * Validate greeting message
     *
     * @param string $greeting Greeting message
     * @return bool|WP_Error True if valid
     */
    public function validateGreeting($greeting)
    {
        if (strlen($greeting) > 200) {
            return new WP_Error(
                'greeting_too_long',
                'Greeting message is too long (maximum 200 characters)',
                ['status' => 400]
            );
        }

        return true;
    }
}
