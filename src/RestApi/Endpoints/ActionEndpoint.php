<?php

/**
 * Action Endpoint Class
 *
 * Handles advanced cart manipulation capabilities including add-to-cart from chat,
 * wishlist integration, product recommendations, up-sell/cross-sell logic, and
 * cart operations. Provides comprehensive e-commerce actions with AI-powered
 * suggestions and seamless WooCommerce integration.
 *
 * @package WooAiAssistant
 * @subpackage RestApi\Endpoints
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\RestApi\Endpoints;

use WooAiAssistant\Common\Utils;
use WooAiAssistant\Common\Traits\Singleton;
use WooAiAssistant\KnowledgeBase\AIManager;
use WooAiAssistant\KnowledgeBase\VectorManager;
use WooAiAssistant\Api\LicenseManager;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use Exception;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ActionEndpoint
 *
 * Advanced cart manipulation endpoint that provides comprehensive e-commerce
 * actions including intelligent product recommendations, wishlist management,
 * up-sell/cross-sell suggestions, and secure cart operations with AI integration.
 *
 * @since 1.0.0
 */
class ActionEndpoint
{
    use Singleton;

    /**
     * Maximum cart operations per minute per user
     *
     * @since 1.0.0
     * @var int
     */
    private const MAX_CART_OPERATIONS_PER_MINUTE = 10;

    /**
     * Maximum recommendation items to return
     *
     * @since 1.0.0
     * @var int
     */
    private const MAX_RECOMMENDATION_ITEMS = 6;

    /**
     * Cache TTL for product recommendations (seconds)
     *
     * @since 1.0.0
     * @var int
     */
    private const RECOMMENDATION_CACHE_TTL = 900; // 15 minutes

    /**
     * Maximum items in wishlist per user
     *
     * @since 1.0.0
     * @var int
     */
    private const MAX_WISHLIST_ITEMS = 50;

    /**
     * AIManager instance for intelligent recommendations
     *
     * @since 1.0.0
     * @var AIManager
     */
    private $aiManager;

    /**
     * VectorManager instance for product similarity
     *
     * @since 1.0.0
     * @var VectorManager
     */
    private $vectorManager;

    /**
     * LicenseManager instance for feature validation
     *
     * @since 1.0.0
     * @var LicenseManager
     */
    private $licenseManager;

    /**
     * Rate limiting data for cart operations
     *
     * @since 1.0.0
     * @var array
     */
    private $cartOperationLimits = [];

    /**
     * Supported wishlist plugins
     *
     * @since 1.0.0
     * @var array
     */
    private $supportedWishlistPlugins = [
        'yith-woocommerce-wishlist' => 'YITH_WCWL',
        'ti-woocommerce-wishlist' => 'TInvWL'
    ];

    /**
     * Constructor
     *
     * Initializes the action endpoint with required dependencies.
     *
     * @since 1.0.0
     */
    private function __construct()
    {
        $this->initializeDependencies();
        $this->setupHooks();
    }

    /**
     * Initialize component dependencies
     *
     * @since 1.0.0
     * @return void
     */
    private function initializeDependencies(): void
    {
        try {
            $main = \WooAiAssistant\Main::getInstance();
            $this->aiManager = $main->getComponent('kb_ai_manager');
            $this->vectorManager = $main->getComponent('kb_vector_manager');
            $this->licenseManager = $main->getComponent('license_manager');
        } catch (Exception $e) {
            Utils::logError('ActionEndpoint: Failed to initialize dependencies - ' . $e->getMessage());
        }
    }

    /**
     * Setup WordPress hooks
     *
     * @since 1.0.0
     * @return void
     */
    private function setupHooks(): void
    {
        // Register REST API routes
        add_action('rest_api_init', [$this, 'registerRoutes']);

        // Initialize cart operations tracking
        add_action('init', [$this, 'initializeCartOperationTracking']);

        // Clean up expired rate limits
        add_action('woo_ai_assistant_cleanup_rate_limits', [$this, 'cleanupExpiredRateLimits']);
    }

    /**
     * Register REST API routes for cart actions
     *
     * @since 1.0.0
     * @return void
     */
    public function registerRoutes(): void
    {
        $namespace = 'woo-ai-assistant/v1';

        // Add to cart endpoint
        register_rest_route($namespace, '/action/add-to-cart', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'addToCart'],
            'permission_callback' => [$this, 'checkActionPermissions'],
            'args' => $this->getAddToCartArgs()
        ]);

        // Update cart endpoint
        register_rest_route($namespace, '/action/update-cart', [
            'methods' => \WP_REST_Server::EDITABLE,
            'callback' => [$this, 'updateCart'],
            'permission_callback' => [$this, 'checkActionPermissions'],
            'args' => $this->getUpdateCartArgs()
        ]);

        // Remove from cart endpoint
        register_rest_route($namespace, '/action/remove-from-cart', [
            'methods' => \WP_REST_Server::DELETABLE,
            'callback' => [$this, 'removeFromCart'],
            'permission_callback' => [$this, 'checkActionPermissions'],
            'args' => $this->getRemoveFromCartArgs()
        ]);

        // Get cart status endpoint
        register_rest_route($namespace, '/action/cart-status', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getCartStatus'],
            'permission_callback' => [$this, 'checkActionPermissions']
        ]);

        // Wishlist operations endpoint
        register_rest_route($namespace, '/action/wishlist', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'manageWishlist'],
            'permission_callback' => [$this, 'checkActionPermissions'],
            'args' => $this->getWishlistArgs()
        ]);

        // Product recommendations endpoint
        register_rest_route($namespace, '/action/recommendations', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getRecommendations'],
            'permission_callback' => [$this, 'checkActionPermissions'],
            'args' => $this->getRecommendationsArgs()
        ]);

        // Up-sell products endpoint
        register_rest_route($namespace, '/action/upsell', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getUpsellProducts'],
            'permission_callback' => [$this, 'checkActionPermissions'],
            'args' => $this->getUpsellArgs()
        ]);

        // Cross-sell products endpoint
        register_rest_route($namespace, '/action/cross-sell', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getCrossSellProducts'],
            'permission_callback' => [$this, 'checkActionPermissions'],
            'args' => $this->getCrossSellArgs()
        ]);

        Utils::logDebug('ActionEndpoint: REST API routes registered successfully');
    }

    /**
     * Add product to cart from chat
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function addToCart(WP_REST_Request $request)
    {
        try {
            // Verify feature availability
            if (!$this->isFeatureEnabled(LicenseManager::FEATURE_ADD_TO_CART)) {
                return $this->createErrorResponse('feature_disabled', 'Add to cart feature not available in current plan', 403);
            }

            // Rate limiting check
            if (!$this->checkCartOperationRateLimit()) {
                return $this->createErrorResponse('rate_limit_exceeded', 'Too many cart operations', 429);
            }

            // Verify nonce
            if (!$this->verifyNonce($request->get_param('nonce'), 'woo_ai_action')) {
                return $this->createErrorResponse('invalid_nonce', 'Security check failed', 403);
            }

            $productId = absint($request->get_param('product_id'));
            $quantity = absint($request->get_param('quantity')) ?: 1;
            $variationId = absint($request->get_param('variation_id')) ?: 0;
            $variation = $request->get_param('variation') ?: [];
            $conversationId = sanitize_text_field($request->get_param('conversation_id'));

            // Validate product exists and is purchasable
            $product = wc_get_product($productId);
            if (!$product || !$product->is_purchasable()) {
                return $this->createErrorResponse('invalid_product', 'Product not available for purchase', 400);
            }

            // Check stock availability
            if (!$product->has_enough_stock($quantity)) {
                return $this->createErrorResponse('insufficient_stock', 'Not enough stock available', 400);
            }

            // Handle variation products
            if ($product->is_type('variable') && $variationId) {
                $variationProduct = wc_get_product($variationId);
                if (!$variationProduct || !$variationProduct->is_purchasable()) {
                    return $this->createErrorResponse('invalid_variation', 'Product variation not available', 400);
                }
            }

            // Add to cart
            $cartItemKey = WC()->cart->add_to_cart($productId, $quantity, $variationId, $variation);

            if (!$cartItemKey) {
                return $this->createErrorResponse('add_to_cart_failed', 'Failed to add product to cart', 500);
            }

            // Log the action for analytics
            $this->logCartAction('add_to_cart', [
                'product_id' => $productId,
                'quantity' => $quantity,
                'variation_id' => $variationId,
                'conversation_id' => $conversationId,
                'cart_item_key' => $cartItemKey
            ]);

            // Get updated cart information
            $cartData = $this->getCartData();

            // Get product recommendations based on added item
            $recommendations = $this->getProductRecommendations($productId, 'related');

            $response = [
                'success' => true,
                'message' => sprintf(__('Added %s to cart successfully!', 'woo-ai-assistant'), $product->get_name()),
                'cart_item_key' => $cartItemKey,
                'product' => $this->formatProductData($product),
                'cart' => $cartData,
                'recommendations' => $recommendations,
                'actions' => [
                    'view_cart' => wc_get_cart_url(),
                    'checkout' => wc_get_checkout_url(),
                    'continue_shopping' => wc_get_page_permalink('shop')
                ]
            ];

            return $this->createSuccessResponse($response);
        } catch (Exception $e) {
            Utils::logError('ActionEndpoint: Add to cart error - ' . $e->getMessage());
            return $this->createErrorResponse('add_to_cart_error', 'Failed to add product to cart', 500);
        }
    }

    /**
     * Update cart item quantity
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function updateCart(WP_REST_Request $request)
    {
        try {
            // Rate limiting and security checks
            if (!$this->checkCartOperationRateLimit()) {
                return $this->createErrorResponse('rate_limit_exceeded', 'Too many cart operations', 429);
            }

            if (!$this->verifyNonce($request->get_param('nonce'), 'woo_ai_action')) {
                return $this->createErrorResponse('invalid_nonce', 'Security check failed', 403);
            }

            $cartItemKey = sanitize_text_field($request->get_param('cart_item_key'));
            $quantity = absint($request->get_param('quantity'));
            $conversationId = sanitize_text_field($request->get_param('conversation_id'));

            // Validate cart item exists
            $cartItem = WC()->cart->get_cart_item($cartItemKey);
            if (!$cartItem) {
                return $this->createErrorResponse('invalid_cart_item', 'Cart item not found', 404);
            }

            // Update quantity (0 removes item)
            if ($quantity === 0) {
                WC()->cart->remove_cart_item($cartItemKey);
                $message = __('Item removed from cart', 'woo-ai-assistant');
            } else {
                WC()->cart->set_quantity($cartItemKey, $quantity);
                $message = sprintf(__('Cart updated - quantity set to %d', 'woo-ai-assistant'), $quantity);
            }

            // Log the action
            $this->logCartAction('update_cart', [
                'cart_item_key' => $cartItemKey,
                'quantity' => $quantity,
                'conversation_id' => $conversationId
            ]);

            $response = [
                'success' => true,
                'message' => $message,
                'cart' => $this->getCartData()
            ];

            return $this->createSuccessResponse($response);
        } catch (Exception $e) {
            Utils::logError('ActionEndpoint: Update cart error - ' . $e->getMessage());
            return $this->createErrorResponse('update_cart_error', 'Failed to update cart', 500);
        }
    }

    /**
     * Remove item from cart
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function removeFromCart(WP_REST_Request $request)
    {
        try {
            // Rate limiting and security checks
            if (!$this->checkCartOperationRateLimit()) {
                return $this->createErrorResponse('rate_limit_exceeded', 'Too many cart operations', 429);
            }

            if (!$this->verifyNonce($request->get_param('nonce'), 'woo_ai_action')) {
                return $this->createErrorResponse('invalid_nonce', 'Security check failed', 403);
            }

            $cartItemKey = sanitize_text_field($request->get_param('cart_item_key'));
            $conversationId = sanitize_text_field($request->get_param('conversation_id'));

            // Validate cart item exists
            $cartItem = WC()->cart->get_cart_item($cartItemKey);
            if (!$cartItem) {
                return $this->createErrorResponse('invalid_cart_item', 'Cart item not found', 404);
            }

            // Remove from cart
            if (WC()->cart->remove_cart_item($cartItemKey)) {
                // Log the action
                $this->logCartAction('remove_from_cart', [
                    'cart_item_key' => $cartItemKey,
                    'conversation_id' => $conversationId
                ]);

                $response = [
                    'success' => true,
                    'message' => __('Item removed from cart successfully', 'woo-ai-assistant'),
                    'cart' => $this->getCartData()
                ];

                return $this->createSuccessResponse($response);
            } else {
                return $this->createErrorResponse('remove_failed', 'Failed to remove item from cart', 500);
            }
        } catch (Exception $e) {
            Utils::logError('ActionEndpoint: Remove from cart error - ' . $e->getMessage());
            return $this->createErrorResponse('remove_from_cart_error', 'Failed to remove item from cart', 500);
        }
    }

    /**
     * Get current cart status and information
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function getCartStatus(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $cartData = $this->getCartData();

            // Add cart recommendations if items exist
            if (!empty($cartData['items'])) {
                $cartData['recommendations'] = $this->getCartBasedRecommendations();
            }

            return $this->createSuccessResponse($cartData);
        } catch (Exception $e) {
            Utils::logError('ActionEndpoint: Get cart status error - ' . $e->getMessage());
            return $this->createErrorResponse('cart_status_error', 'Failed to get cart status', 500);
        }
    }

    /**
     * Manage wishlist operations (add, remove, view)
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function manageWishlist(WP_REST_Request $request)
    {
        try {
            // Check if any supported wishlist plugin is active
            $activeWishlistPlugin = $this->getActiveWishlistPlugin();
            if (!$activeWishlistPlugin) {
                return $this->createErrorResponse('wishlist_not_supported', 'No supported wishlist plugin found', 400);
            }

            // Rate limiting and security checks
            if (!$this->checkCartOperationRateLimit()) {
                return $this->createErrorResponse('rate_limit_exceeded', 'Too many operations', 429);
            }

            if (!$this->verifyNonce($request->get_param('nonce'), 'woo_ai_action')) {
                return $this->createErrorResponse('invalid_nonce', 'Security check failed', 403);
            }

            $operation = sanitize_text_field($request->get_param('operation'));
            $productId = absint($request->get_param('product_id'));
            $conversationId = sanitize_text_field($request->get_param('conversation_id'));

            switch ($operation) {
                case 'add':
                    return $this->addToWishlist($productId, $conversationId, $activeWishlistPlugin);
                case 'remove':
                    return $this->removeFromWishlist($productId, $conversationId, $activeWishlistPlugin);
                case 'view':
                    return $this->getWishlistItems($activeWishlistPlugin);
                default:
                    return $this->createErrorResponse('invalid_operation', 'Invalid wishlist operation', 400);
            }
        } catch (Exception $e) {
            Utils::logError('ActionEndpoint: Wishlist operation error - ' . $e->getMessage());
            return $this->createErrorResponse('wishlist_error', 'Wishlist operation failed', 500);
        }
    }

    /**
     * Get AI-powered product recommendations
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function getRecommendations(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $context = sanitize_text_field($request->get_param('context')) ?: 'general';
            $productId = absint($request->get_param('product_id')) ?: 0;
            $categoryId = absint($request->get_param('category_id')) ?: 0;
            $userId = get_current_user_id();
            $limit = absint($request->get_param('limit')) ?: 6;

            // Ensure limit doesn't exceed maximum
            $limit = min($limit, self::MAX_RECOMMENDATION_ITEMS);

            // Generate cache key
            $cacheKey = "woo_ai_recommendations_{$context}_{$productId}_{$categoryId}_{$userId}_{$limit}";

            // Try to get from cache first
            $recommendations = wp_cache_get($cacheKey, 'woo_ai_assistant');

            if ($recommendations === false) {
                // Generate new recommendations based on context
                switch ($context) {
                    case 'product':
                        $recommendations = $this->getProductRecommendations($productId, 'related', $limit);
                        break;
                    case 'category':
                        $recommendations = $this->getCategoryRecommendations($categoryId, $limit);
                        break;
                    case 'user_history':
                        $recommendations = $this->getUserHistoryRecommendations($userId, $limit);
                        break;
                    case 'trending':
                        $recommendations = $this->getTrendingRecommendations($limit);
                        break;
                    default:
                        $recommendations = $this->getGeneralRecommendations($limit);
                }

                // Cache the results
                wp_cache_set($cacheKey, $recommendations, 'woo_ai_assistant', self::RECOMMENDATION_CACHE_TTL);
            }

            $response = [
                'context' => $context,
                'recommendations' => $recommendations,
                'count' => count($recommendations)
            ];

            return $this->createSuccessResponse($response);
        } catch (Exception $e) {
            Utils::logError('ActionEndpoint: Get recommendations error - ' . $e->getMessage());
            return $this->createErrorResponse('recommendations_error', 'Failed to get recommendations', 500);
        }
    }

    /**
     * Get up-sell products for current cart
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function getUpsellProducts(WP_REST_Request $request): WP_REST_Response
    {
        try {
            // Check feature availability
            if (!$this->isFeatureEnabled(LicenseManager::FEATURE_UPSELL_CROSSSELL)) {
                return $this->createErrorResponse('feature_disabled', 'Upsell feature not available in current plan', 403);
            }

            $limit = absint($request->get_param('limit')) ?: 4;
            $cartItems = WC()->cart->get_cart();

            if (empty($cartItems)) {
                return $this->createSuccessResponse([
                    'upsells' => [],
                    'message' => __('No items in cart for upsell suggestions', 'woo-ai-assistant')
                ]);
            }

            $upsellProducts = $this->generateUpsellSuggestions($cartItems, $limit);

            $response = [
                'upsells' => $upsellProducts,
                'count' => count($upsellProducts),
                'cart_value' => WC()->cart->get_cart_contents_total(),
                'potential_value' => $this->calculatePotentialUpsellValue($upsellProducts)
            ];

            return $this->createSuccessResponse($response);
        } catch (Exception $e) {
            Utils::logError('ActionEndpoint: Get upsell products error - ' . $e->getMessage());
            return $this->createErrorResponse('upsell_error', 'Failed to get upsell products', 500);
        }
    }

    /**
     * Get cross-sell products for current cart
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function getCrossSellProducts(WP_REST_Request $request): WP_REST_Response
    {
        try {
            // Check feature availability
            if (!$this->isFeatureEnabled(LicenseManager::FEATURE_UPSELL_CROSSSELL)) {
                return $this->createErrorResponse('feature_disabled', 'Cross-sell feature not available in current plan', 403);
            }

            $limit = absint($request->get_param('limit')) ?: 4;
            $cartItems = WC()->cart->get_cart();

            if (empty($cartItems)) {
                return $this->createSuccessResponse([
                    'crosssells' => [],
                    'message' => __('No items in cart for cross-sell suggestions', 'woo-ai-assistant')
                ]);
            }

            $crossSellProducts = $this->generateCrossSellSuggestions($cartItems, $limit);

            $response = [
                'crosssells' => $crossSellProducts,
                'count' => count($crossSellProducts),
                'categories' => $this->getCartCategories($cartItems)
            ];

            return $this->createSuccessResponse($response);
        } catch (Exception $e) {
            Utils::logError('ActionEndpoint: Get cross-sell products error - ' . $e->getMessage());
            return $this->createErrorResponse('crosssell_error', 'Failed to get cross-sell products', 500);
        }
    }

    /**
     * Check action permissions
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return bool True if permitted
     */
    public function checkActionPermissions(WP_REST_Request $request): bool
    {
        // Allow frontend users (including guests) for cart actions
        return true;
    }

    /**
     * Initialize cart operation tracking
     *
     * @since 1.0.0
     * @return void
     */
    public function initializeCartOperationTracking(): void
    {
        $this->cartOperationLimits = get_transient('woo_ai_cart_operation_limits') ?: [];
    }

    /**
     * Clean up expired rate limits
     *
     * @since 1.0.0
     * @return void
     */
    public function cleanupExpiredRateLimits(): void
    {
        $now = time();
        $cleaned = false;

        foreach ($this->cartOperationLimits as $key => $data) {
            if (($now - $data['window_start']) > MINUTE_IN_SECONDS) {
                unset($this->cartOperationLimits[$key]);
                $cleaned = true;
            }
        }

        if ($cleaned) {
            set_transient('woo_ai_cart_operation_limits', $this->cartOperationLimits, HOUR_IN_SECONDS);
        }
    }

    /**
     * Check cart operation rate limit
     *
     * @since 1.0.0
     * @return bool True if within limits
     */
    private function checkCartOperationRateLimit(): bool
    {
        $userId = $this->getCurrentUserId();
        $now = time();

        if (!isset($this->cartOperationLimits[$userId])) {
            $this->cartOperationLimits[$userId] = [
                'operations' => 1,
                'window_start' => $now
            ];
        } else {
            $data = $this->cartOperationLimits[$userId];

            // Reset window if expired
            if (($now - $data['window_start']) > MINUTE_IN_SECONDS) {
                $this->cartOperationLimits[$userId] = [
                    'operations' => 1,
                    'window_start' => $now
                ];
            } else {
                // Check if within limits
                if ($data['operations'] >= self::MAX_CART_OPERATIONS_PER_MINUTE) {
                    return false;
                }

                $this->cartOperationLimits[$userId]['operations']++;
            }
        }

        // Save rate limits to cache
        set_transient('woo_ai_cart_operation_limits', $this->cartOperationLimits, HOUR_IN_SECONDS);
        return true;
    }

    /**
     * Get current user ID for rate limiting
     *
     * @since 1.0.0
     * @return string User ID or IP address for guests
     */
    private function getCurrentUserId(): string
    {
        $userId = get_current_user_id();
        if ($userId) {
            return 'user_' . $userId;
        }

        // Use IP address for guest users
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        return 'ip_' . md5($ip);
    }

    /**
     * Verify nonce for security
     *
     * @since 1.0.0
     * @param string $nonce Nonce to verify
     * @param string $action Action name
     * @return bool True if valid
     */
    private function verifyNonce(string $nonce, string $action): bool
    {
        return wp_verify_nonce($nonce, $action);
    }

    /**
     * Check if feature is enabled in current plan
     *
     * @since 1.0.0
     * @param string $feature Feature constant
     * @return bool True if enabled
     */
    private function isFeatureEnabled(string $feature): bool
    {
        if (!$this->licenseManager) {
            return true; // Allow if license manager not available (development mode)
        }

        return $this->licenseManager->isFeatureEnabled($feature);
    }

    /**
     * Get cart data with formatted information
     *
     * @since 1.0.0
     * @return array Formatted cart data
     */
    private function getCartData(): array
    {
        $cart = WC()->cart;
        $cartItems = [];

        foreach ($cart->get_cart() as $cartItemKey => $cartItem) {
            $product = $cartItem['data'];
            $productId = $cartItem['product_id'];
            $variationId = $cartItem['variation_id'];

            $cartItems[] = [
                'key' => $cartItemKey,
                'product_id' => $productId,
                'variation_id' => $variationId,
                'quantity' => $cartItem['quantity'],
                'name' => $product->get_name(),
                'price' => $product->get_price(),
                'total' => $cart->get_product_subtotal($product, $cartItem['quantity']),
                'image' => wp_get_attachment_image_src(get_post_thumbnail_id($productId), 'thumbnail')[0] ?? '',
                'permalink' => get_permalink($productId)
            ];
        }

        return [
            'items' => $cartItems,
            'count' => $cart->get_cart_contents_count(),
            'subtotal' => $cart->get_cart_subtotal(),
            'total' => $cart->get_cart_total(),
            'tax_total' => $cart->get_cart_tax(),
            'shipping_total' => $cart->get_shipping_total(),
            'needs_shipping' => $cart->needs_shipping(),
            'is_empty' => $cart->is_empty(),
            'urls' => [
                'cart' => wc_get_cart_url(),
                'checkout' => wc_get_checkout_url()
            ]
        ];
    }

    /**
     * Format product data for API response
     *
     * @since 1.0.0
     * @param \WC_Product $product Product object
     * @return array Formatted product data
     */
    private function formatProductData(\WC_Product $product): array
    {
        return [
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'price' => $product->get_price(),
            'regular_price' => $product->get_regular_price(),
            'sale_price' => $product->get_sale_price(),
            'in_stock' => $product->is_in_stock(),
            'stock_quantity' => $product->get_stock_quantity(),
            'image' => wp_get_attachment_image_src(get_post_thumbnail_id($product->get_id()), 'medium')[0] ?? '',
            'permalink' => get_permalink($product->get_id()),
            'short_description' => $product->get_short_description(),
            'categories' => wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names'])
        ];
    }

    /**
     * Log cart action for analytics and debugging
     *
     * @since 1.0.0
     * @param string $action Action type
     * @param array $data Action data
     * @return void
     */
    private function logCartAction(string $action, array $data): void
    {
        $logData = array_merge($data, [
            'action' => $action,
            'user_id' => get_current_user_id(),
            'timestamp' => current_time('mysql'),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);

        Utils::logDebug("Cart Action: {$action}", $logData);

        // Store in database for analytics (optional)
        do_action('woo_ai_assistant_cart_action_logged', $action, $logData);
    }

    /**
     * Get active wishlist plugin
     *
     * @since 1.0.0
     * @return string|false Active plugin identifier or false
     */
    private function getActiveWishlistPlugin()
    {
        foreach ($this->supportedWishlistPlugins as $plugin => $class) {
            if (class_exists($class)) {
                return $plugin;
            }
        }
        return false;
    }

    /**
     * Add product to wishlist
     *
     * @since 1.0.0
     * @param int $productId Product ID
     * @param string $conversationId Conversation ID
     * @param string $plugin Active wishlist plugin
     * @return WP_REST_Response|WP_Error Response object or error
     */
    private function addToWishlist(int $productId, string $conversationId, string $plugin)
    {
        try {
            $success = false;
            $message = '';

            switch ($plugin) {
                case 'yith-woocommerce-wishlist':
                    if (function_exists('YITH_WCWL')) {
                        $success = YITH_WCWL()->add($productId);
                        $message = $success ? __('Added to wishlist', 'woo-ai-assistant') : __('Already in wishlist', 'woo-ai-assistant');
                    }
                    break;

                case 'ti-woocommerce-wishlist':
                    if (class_exists('TInvWL_Public_Wishlist_Buttons')) {
                        // TI Wishlist implementation
                        $success = true; // Implement specific TI wishlist logic
                        $message = __('Added to wishlist', 'woo-ai-assistant');
                    }
                    break;
            }

            if ($success) {
                $this->logCartAction('add_to_wishlist', [
                    'product_id' => $productId,
                    'conversation_id' => $conversationId,
                    'plugin' => $plugin
                ]);
            }

            return $this->createSuccessResponse([
                'success' => $success,
                'message' => $message,
                'product_id' => $productId
            ]);
        } catch (Exception $e) {
            Utils::logError('ActionEndpoint: Add to wishlist error - ' . $e->getMessage());
            return $this->createErrorResponse('wishlist_add_error', 'Failed to add to wishlist', 500);
        }
    }

    /**
     * Remove product from wishlist
     *
     * @since 1.0.0
     * @param int $productId Product ID
     * @param string $conversationId Conversation ID
     * @param string $plugin Active wishlist plugin
     * @return WP_REST_Response|WP_Error Response object or error
     */
    private function removeFromWishlist(int $productId, string $conversationId, string $plugin)
    {
        try {
            $success = false;

            switch ($plugin) {
                case 'yith-woocommerce-wishlist':
                    if (function_exists('YITH_WCWL')) {
                        $success = YITH_WCWL()->remove($productId);
                    }
                    break;

                case 'ti-woocommerce-wishlist':
                    // TI Wishlist implementation
                    $success = true; // Implement specific TI wishlist logic
                    break;
            }

            if ($success) {
                $this->logCartAction('remove_from_wishlist', [
                    'product_id' => $productId,
                    'conversation_id' => $conversationId,
                    'plugin' => $plugin
                ]);
            }

            return $this->createSuccessResponse([
                'success' => $success,
                'message' => __('Removed from wishlist', 'woo-ai-assistant'),
                'product_id' => $productId
            ]);
        } catch (Exception $e) {
            Utils::logError('ActionEndpoint: Remove from wishlist error - ' . $e->getMessage());
            return $this->createErrorResponse('wishlist_remove_error', 'Failed to remove from wishlist', 500);
        }
    }

    /**
     * Get wishlist items
     *
     * @since 1.0.0
     * @param string $plugin Active wishlist plugin
     * @return WP_REST_Response Response object
     */
    private function getWishlistItems(string $plugin): WP_REST_Response
    {
        try {
            $items = [];

            switch ($plugin) {
                case 'yith-woocommerce-wishlist':
                    if (function_exists('YITH_WCWL')) {
                        $wishlistItems = YITH_WCWL()->get_products();
                        foreach ($wishlistItems as $item) {
                            $product = wc_get_product($item['prod_id']);
                            if ($product) {
                                $items[] = $this->formatProductData($product);
                            }
                        }
                    }
                    break;

                case 'ti-woocommerce-wishlist':
                    // TI Wishlist implementation
                    break;
            }

            return $this->createSuccessResponse([
                'items' => $items,
                'count' => count($items),
                'plugin' => $plugin
            ]);
        } catch (Exception $e) {
            Utils::logError('ActionEndpoint: Get wishlist items error - ' . $e->getMessage());
            return $this->createErrorResponse('wishlist_get_error', 'Failed to get wishlist items', 500);
        }
    }

    /**
     * Get product recommendations using AI and similarity
     *
     * @since 1.0.0
     * @param int $productId Product ID for recommendations
     * @param string $type Recommendation type (related, similar, etc.)
     * @param int $limit Number of recommendations
     * @return array Array of recommended products
     */
    private function getProductRecommendations(int $productId, string $type = 'related', int $limit = 6): array
    {
        try {
            $recommendations = [];
            $product = wc_get_product($productId);

            if (!$product) {
                return $recommendations;
            }

            // Use vector similarity if available
            if ($this->vectorManager) {
                $similarProducts = $this->vectorManager->searchSimilar(
                    $product->get_name() . ' ' . $product->get_short_description(),
                    $limit + 1 // +1 to account for the original product
                );

                foreach ($similarProducts as $similar) {
                    if (isset($similar['metadata']['product_id']) && $similar['metadata']['product_id'] != $productId) {
                        $similarProduct = wc_get_product($similar['metadata']['product_id']);
                        if ($similarProduct && $similarProduct->is_purchasable()) {
                            $recommendations[] = $this->formatProductData($similarProduct);
                        }
                    }
                }
            }

            // Fallback to WooCommerce related products
            if (count($recommendations) < $limit) {
                $relatedIds = wc_get_related_products($productId, $limit);
                foreach ($relatedIds as $relatedId) {
                    $relatedProduct = wc_get_product($relatedId);
                    if ($relatedProduct && $relatedProduct->is_purchasable()) {
                        $recommendations[] = $this->formatProductData($relatedProduct);
                    }
                }
            }

            return array_slice($recommendations, 0, $limit);
        } catch (Exception $e) {
            Utils::logError('ActionEndpoint: Get product recommendations error - ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get category-based recommendations
     *
     * @since 1.0.0
     * @param int $categoryId Category ID
     * @param int $limit Number of recommendations
     * @return array Array of recommended products
     */
    private function getCategoryRecommendations(int $categoryId, int $limit = 6): array
    {
        try {
            $args = [
                'post_type' => 'product',
                'posts_per_page' => $limit,
                'post_status' => 'publish',
                'meta_query' => [
                    [
                        'key' => '_visibility',
                        'value' => ['catalog', 'visible'],
                        'compare' => 'IN'
                    ]
                ],
                'tax_query' => [
                    [
                        'taxonomy' => 'product_cat',
                        'field' => 'term_id',
                        'terms' => $categoryId
                    ]
                ],
                'orderby' => 'menu_order',
                'order' => 'ASC'
            ];

            $products = get_posts($args);
            $recommendations = [];

            foreach ($products as $product) {
                $wcProduct = wc_get_product($product->ID);
                if ($wcProduct && $wcProduct->is_purchasable()) {
                    $recommendations[] = $this->formatProductData($wcProduct);
                }
            }

            return $recommendations;
        } catch (Exception $e) {
            Utils::logError('ActionEndpoint: Get category recommendations error - ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get user history-based recommendations
     *
     * @since 1.0.0
     * @param int $userId User ID
     * @param int $limit Number of recommendations
     * @return array Array of recommended products
     */
    private function getUserHistoryRecommendations(int $userId, int $limit = 6): array
    {
        try {
            if (!$userId) {
                return [];
            }

            // Get user's order history
            $orders = wc_get_orders([
                'customer_id' => $userId,
                'limit' => 10,
                'status' => ['completed']
            ]);

            $purchasedProducts = [];
            $categories = [];

            foreach ($orders as $order) {
                foreach ($order->get_items() as $item) {
                    $productId = $item->get_product_id();
                    $purchasedProducts[] = $productId;

                    $productCategories = wp_get_post_terms($productId, 'product_cat');
                    foreach ($productCategories as $category) {
                        $categories[] = $category->term_id;
                    }
                }
            }

            // Get recommendations from similar categories
            if (!empty($categories)) {
                $categoryId = array_count_values($categories);
                $mostPurchasedCategory = array_keys($categoryId, max($categoryId))[0];
                return $this->getCategoryRecommendations($mostPurchasedCategory, $limit);
            }

            return [];
        } catch (Exception $e) {
            Utils::logError('ActionEndpoint: Get user history recommendations error - ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get trending recommendations
     *
     * @since 1.0.0
     * @param int $limit Number of recommendations
     * @return array Array of recommended products
     */
    private function getTrendingRecommendations(int $limit = 6): array
    {
        try {
            $args = [
                'post_type' => 'product',
                'posts_per_page' => $limit,
                'post_status' => 'publish',
                'meta_query' => [
                    [
                        'key' => '_visibility',
                        'value' => ['catalog', 'visible'],
                        'compare' => 'IN'
                    ]
                ],
                'orderby' => 'meta_value_num',
                'meta_key' => 'total_sales',
                'order' => 'DESC'
            ];

            $products = get_posts($args);
            $recommendations = [];

            foreach ($products as $product) {
                $wcProduct = wc_get_product($product->ID);
                if ($wcProduct && $wcProduct->is_purchasable()) {
                    $recommendations[] = $this->formatProductData($wcProduct);
                }
            }

            return $recommendations;
        } catch (Exception $e) {
            Utils::logError('ActionEndpoint: Get trending recommendations error - ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get general recommendations
     *
     * @since 1.0.0
     * @param int $limit Number of recommendations
     * @return array Array of recommended products
     */
    private function getGeneralRecommendations(int $limit = 6): array
    {
        try {
            $args = [
                'post_type' => 'product',
                'posts_per_page' => $limit,
                'post_status' => 'publish',
                'meta_query' => [
                    [
                        'key' => '_featured',
                        'value' => 'yes'
                    ]
                ]
            ];

            $products = get_posts($args);
            $recommendations = [];

            foreach ($products as $product) {
                $wcProduct = wc_get_product($product->ID);
                if ($wcProduct && $wcProduct->is_purchasable()) {
                    $recommendations[] = $this->formatProductData($wcProduct);
                }
            }

            // If no featured products, get random products
            if (empty($recommendations)) {
                $args['meta_query'] = [
                    [
                        'key' => '_visibility',
                        'value' => ['catalog', 'visible'],
                        'compare' => 'IN'
                    ]
                ];
                $args['orderby'] = 'rand';
                unset($args['meta_query'][0]['key']);

                $products = get_posts($args);
                foreach ($products as $product) {
                    $wcProduct = wc_get_product($product->ID);
                    if ($wcProduct && $wcProduct->is_purchasable()) {
                        $recommendations[] = $this->formatProductData($wcProduct);
                    }
                }
            }

            return $recommendations;
        } catch (Exception $e) {
            Utils::logError('ActionEndpoint: Get general recommendations error - ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get cart-based recommendations
     *
     * @since 1.0.0
     * @return array Array of recommended products
     */
    private function getCartBasedRecommendations(): array
    {
        try {
            $cartItems = WC()->cart->get_cart();
            $recommendations = [];

            if (empty($cartItems)) {
                return $recommendations;
            }

            // Get cross-sell products from cart items
            $crossSells = [];
            foreach ($cartItems as $cartItem) {
                $product = $cartItem['data'];
                $productCrossSells = $product->get_cross_sell_ids();
                $crossSells = array_merge($crossSells, $productCrossSells);
            }

            // Remove duplicates and current cart products
            $crossSells = array_unique($crossSells);
            $cartProductIds = array_column($cartItems, 'product_id');
            $crossSells = array_diff($crossSells, $cartProductIds);

            // Format cross-sell products
            foreach (array_slice($crossSells, 0, 4) as $productId) {
                $product = wc_get_product($productId);
                if ($product && $product->is_purchasable()) {
                    $recommendations[] = $this->formatProductData($product);
                }
            }

            return $recommendations;
        } catch (Exception $e) {
            Utils::logError('ActionEndpoint: Get cart-based recommendations error - ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Generate up-sell suggestions based on cart contents
     *
     * @since 1.0.0
     * @param array $cartItems Cart items
     * @param int $limit Number of suggestions
     * @return array Array of up-sell products
     */
    private function generateUpsellSuggestions(array $cartItems, int $limit = 4): array
    {
        try {
            $upsells = [];
            $cartTotal = WC()->cart->get_cart_contents_total();

            foreach ($cartItems as $cartItem) {
                $product = $cartItem['data'];
                $productUpsells = $product->get_upsell_ids();

                foreach ($productUpsells as $upsellId) {
                    $upsellProduct = wc_get_product($upsellId);
                    if ($upsellProduct && $upsellProduct->is_purchasable()) {
                        $productData = $this->formatProductData($upsellProduct);
                        $productData['upsell_value'] = (float)$upsellProduct->get_price() - (float)$product->get_price();
                        $productData['upsell_percentage'] = round(($productData['upsell_value'] / (float)$product->get_price()) * 100, 2);
                        $upsells[] = $productData;
                    }
                }
            }

            // Remove duplicates and sort by upsell value
            $upsells = array_unique($upsells, SORT_REGULAR);
            usort($upsells, function ($a, $b) {
                return $b['upsell_value'] <=> $a['upsell_value'];
            });

            return array_slice($upsells, 0, $limit);
        } catch (Exception $e) {
            Utils::logError('ActionEndpoint: Generate upsell suggestions error - ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Generate cross-sell suggestions based on cart contents
     *
     * @since 1.0.0
     * @param array $cartItems Cart items
     * @param int $limit Number of suggestions
     * @return array Array of cross-sell products
     */
    private function generateCrossSellSuggestions(array $cartItems, int $limit = 4): array
    {
        try {
            $crossSells = [];
            $cartProductIds = array_column($cartItems, 'product_id');

            foreach ($cartItems as $cartItem) {
                $product = $cartItem['data'];
                $productCrossSells = $product->get_cross_sell_ids();

                foreach ($productCrossSells as $crossSellId) {
                    if (!in_array($crossSellId, $cartProductIds)) {
                        $crossSellProduct = wc_get_product($crossSellId);
                        if ($crossSellProduct && $crossSellProduct->is_purchasable()) {
                            $crossSells[] = $this->formatProductData($crossSellProduct);
                        }
                    }
                }
            }

            // Remove duplicates
            $crossSells = array_unique($crossSells, SORT_REGULAR);

            return array_slice($crossSells, 0, $limit);
        } catch (Exception $e) {
            Utils::logError('ActionEndpoint: Generate cross-sell suggestions error - ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Calculate potential upsell value
     *
     * @since 1.0.0
     * @param array $upsellProducts Upsell products
     * @return float Total potential value
     */
    private function calculatePotentialUpsellValue(array $upsellProducts): float
    {
        $totalValue = 0.0;

        foreach ($upsellProducts as $product) {
            $totalValue += (float)($product['upsell_value'] ?? 0);
        }

        return $totalValue;
    }

    /**
     * Get cart categories
     *
     * @since 1.0.0
     * @param array $cartItems Cart items
     * @return array Array of category names
     */
    private function getCartCategories(array $cartItems): array
    {
        $categories = [];

        foreach ($cartItems as $cartItem) {
            $productCategories = wp_get_post_terms($cartItem['product_id'], 'product_cat');
            foreach ($productCategories as $category) {
                $categories[] = $category->name;
            }
        }

        return array_unique($categories);
    }

    /**
     * Get add-to-cart endpoint arguments
     *
     * @since 1.0.0
     * @return array Endpoint arguments
     */
    private function getAddToCartArgs(): array
    {
        return [
            'product_id' => [
                'required' => true,
                'type' => 'integer',
                'description' => 'Product ID to add to cart',
                'validate_callback' => [$this, 'validateProductId']
            ],
            'quantity' => [
                'required' => false,
                'type' => 'integer',
                'default' => 1,
                'minimum' => 1,
                'description' => 'Quantity to add'
            ],
            'variation_id' => [
                'required' => false,
                'type' => 'integer',
                'default' => 0,
                'description' => 'Variation ID for variable products'
            ],
            'variation' => [
                'required' => false,
                'type' => 'object',
                'default' => [],
                'description' => 'Variation attributes'
            ],
            'conversation_id' => [
                'required' => true,
                'type' => 'string',
                'description' => 'Conversation ID for tracking',
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'nonce' => [
                'required' => true,
                'type' => 'string',
                'description' => 'Security nonce',
                'sanitize_callback' => 'sanitize_text_field'
            ]
        ];
    }

    /**
     * Get update-cart endpoint arguments
     *
     * @since 1.0.0
     * @return array Endpoint arguments
     */
    private function getUpdateCartArgs(): array
    {
        return [
            'cart_item_key' => [
                'required' => true,
                'type' => 'string',
                'description' => 'Cart item key to update',
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'quantity' => [
                'required' => true,
                'type' => 'integer',
                'minimum' => 0,
                'description' => 'New quantity (0 to remove)'
            ],
            'conversation_id' => [
                'required' => false,
                'type' => 'string',
                'description' => 'Conversation ID for tracking',
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'nonce' => [
                'required' => true,
                'type' => 'string',
                'description' => 'Security nonce',
                'sanitize_callback' => 'sanitize_text_field'
            ]
        ];
    }

    /**
     * Get remove-from-cart endpoint arguments
     *
     * @since 1.0.0
     * @return array Endpoint arguments
     */
    private function getRemoveFromCartArgs(): array
    {
        return [
            'cart_item_key' => [
                'required' => true,
                'type' => 'string',
                'description' => 'Cart item key to remove',
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'conversation_id' => [
                'required' => false,
                'type' => 'string',
                'description' => 'Conversation ID for tracking',
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'nonce' => [
                'required' => true,
                'type' => 'string',
                'description' => 'Security nonce',
                'sanitize_callback' => 'sanitize_text_field'
            ]
        ];
    }

    /**
     * Get wishlist endpoint arguments
     *
     * @since 1.0.0
     * @return array Endpoint arguments
     */
    private function getWishlistArgs(): array
    {
        return [
            'operation' => [
                'required' => true,
                'type' => 'string',
                'enum' => ['add', 'remove', 'view'],
                'description' => 'Wishlist operation to perform'
            ],
            'product_id' => [
                'required' => false,
                'type' => 'integer',
                'description' => 'Product ID (required for add/remove operations)',
                'validate_callback' => [$this, 'validateProductId']
            ],
            'conversation_id' => [
                'required' => false,
                'type' => 'string',
                'description' => 'Conversation ID for tracking',
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'nonce' => [
                'required' => true,
                'type' => 'string',
                'description' => 'Security nonce',
                'sanitize_callback' => 'sanitize_text_field'
            ]
        ];
    }

    /**
     * Get recommendations endpoint arguments
     *
     * @since 1.0.0
     * @return array Endpoint arguments
     */
    private function getRecommendationsArgs(): array
    {
        return [
            'context' => [
                'required' => false,
                'type' => 'string',
                'default' => 'general',
                'enum' => ['general', 'product', 'category', 'user_history', 'trending'],
                'description' => 'Recommendation context'
            ],
            'product_id' => [
                'required' => false,
                'type' => 'integer',
                'description' => 'Product ID for product context'
            ],
            'category_id' => [
                'required' => false,
                'type' => 'integer',
                'description' => 'Category ID for category context'
            ],
            'limit' => [
                'required' => false,
                'type' => 'integer',
                'default' => 6,
                'minimum' => 1,
                'maximum' => self::MAX_RECOMMENDATION_ITEMS,
                'description' => 'Number of recommendations to return'
            ]
        ];
    }

    /**
     * Get upsell endpoint arguments
     *
     * @since 1.0.0
     * @return array Endpoint arguments
     */
    private function getUpsellArgs(): array
    {
        return [
            'limit' => [
                'required' => false,
                'type' => 'integer',
                'default' => 4,
                'minimum' => 1,
                'maximum' => 8,
                'description' => 'Number of upsell products to return'
            ]
        ];
    }

    /**
     * Get cross-sell endpoint arguments
     *
     * @since 1.0.0
     * @return array Endpoint arguments
     */
    private function getCrossSellArgs(): array
    {
        return [
            'limit' => [
                'required' => false,
                'type' => 'integer',
                'default' => 4,
                'minimum' => 1,
                'maximum' => 8,
                'description' => 'Number of cross-sell products to return'
            ]
        ];
    }

    /**
     * Validate product ID
     *
     * @since 1.0.0
     * @param int $value Product ID
     * @param WP_REST_Request $request Request object
     * @param string $param Parameter name
     * @return bool|WP_Error True if valid, WP_Error otherwise
     */
    public function validateProductId($value, $request, $param)
    {
        $productId = absint($value);

        if ($productId <= 0) {
            return new WP_Error('invalid_product_id', 'Product ID must be a positive integer');
        }

        $product = wc_get_product($productId);
        if (!$product) {
            return new WP_Error('product_not_found', 'Product not found');
        }

        return true;
    }

    /**
     * Create success response
     *
     * @since 1.0.0
     * @param mixed $data Response data
     * @param int $status HTTP status code
     * @return WP_REST_Response Response object
     */
    private function createSuccessResponse($data, int $status = 200): WP_REST_Response
    {
        return new WP_REST_Response([
            'success' => true,
            'data' => $data,
            'timestamp' => current_time('c')
        ], $status);
    }

    /**
     * Create error response
     *
     * @since 1.0.0
     * @param string $code Error code
     * @param string $message Error message
     * @param int $status HTTP status code
     * @return WP_Error Error object
     */
    private function createErrorResponse(string $code, string $message, int $status = 400): WP_Error
    {
        return new WP_Error($code, $message, ['status' => $status]);
    }
}
