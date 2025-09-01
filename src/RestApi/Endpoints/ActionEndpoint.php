<?php

/**
 * Action Endpoint Class
 *
 * Handles REST API endpoints for executing actions like adding products to cart,
 * applying coupons, and other WooCommerce-related actions triggered by the AI assistant.
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
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ActionEndpoint
 *
 * Manages action-related REST API endpoints for WooCommerce integration.
 * This is a placeholder implementation for Task 5.3.
 *
 * @since 1.0.0
 */
class ActionEndpoint
{
    /**
     * Register action routes
     *
     * @param string $namespace API namespace
     * @return void
     */
    public function registerRoutes(string $namespace): void
    {
        // Add to cart endpoint
        register_rest_route(
            $namespace,
            '/actions/add-to-cart',
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'addToCart'],
                'permission_callback' => [$this, 'checkActionPermission'],
                'args' => [
                    'product_id' => [
                        'required' => true,
                        'type' => 'integer',
                        'description' => 'Product ID to add to cart',
                        'validate_callback' => [$this, 'validateProductId'],
                        'sanitize_callback' => 'absint'
                    ],
                    'quantity' => [
                        'type' => 'integer',
                        'description' => 'Quantity to add',
                        'default' => 1,
                        'sanitize_callback' => 'absint'
                    ],
                    'variation_id' => [
                        'type' => 'integer',
                        'description' => 'Variation ID for variable products',
                        'sanitize_callback' => 'absint'
                    ],
                    'variation' => [
                        'type' => 'object',
                        'description' => 'Variation attributes',
                        'default' => []
                    ]
                ]
            ]
        );

        // Apply coupon endpoint
        register_rest_route(
            $namespace,
            '/actions/apply-coupon',
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'applyCoupon'],
                'permission_callback' => [$this, 'checkActionPermission'],
                'args' => [
                    'coupon_code' => [
                        'required' => true,
                        'type' => 'string',
                        'description' => 'Coupon code to apply',
                        'validate_callback' => [$this, 'validateCouponCode'],
                        'sanitize_callback' => 'sanitize_text_field'
                    ]
                ]
            ]
        );

        // Remove coupon endpoint
        register_rest_route(
            $namespace,
            '/actions/remove-coupon',
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'removeCoupon'],
                'permission_callback' => [$this, 'checkActionPermission'],
                'args' => [
                    'coupon_code' => [
                        'required' => true,
                        'type' => 'string',
                        'description' => 'Coupon code to remove',
                        'sanitize_callback' => 'sanitize_text_field'
                    ]
                ]
            ]
        );

        // Update cart quantity endpoint
        register_rest_route(
            $namespace,
            '/actions/update-cart',
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateCart'],
                'permission_callback' => [$this, 'checkActionPermission'],
                'args' => [
                    'cart_item_key' => [
                        'required' => true,
                        'type' => 'string',
                        'description' => 'Cart item key',
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'quantity' => [
                        'required' => true,
                        'type' => 'integer',
                        'description' => 'New quantity',
                        'sanitize_callback' => 'absint'
                    ]
                ]
            ]
        );

        // Clear cart endpoint
        register_rest_route(
            $namespace,
            '/actions/clear-cart',
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'clearCart'],
                'permission_callback' => [$this, 'checkActionPermission']
            ]
        );

        // Remove from cart endpoint
        register_rest_route(
            $namespace,
            '/actions/remove-from-cart',
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'removeFromCart'],
                'permission_callback' => [$this, 'checkActionPermission'],
                'args' => [
                    'cart_item_key' => [
                        'required' => true,
                        'type' => 'string',
                        'description' => 'Cart item key to remove',
                        'sanitize_callback' => 'sanitize_text_field'
                    ]
                ]
            ]
        );

        // Get cart contents endpoint
        register_rest_route(
            $namespace,
            '/actions/cart',
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getCart'],
                'permission_callback' => [$this, 'checkActionPermission']
            ]
        );

        Logger::debug('Action endpoints registered');
    }

    /**
     * Add product to cart
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public function addToCart(WP_REST_Request $request)
    {
        try {
            if (!Utils::canUseCart()) {
                return new WP_Error(
                    'cart_unavailable',
                    'Cart functionality is not available',
                    ['status' => 400]
                );
            }

            $productId = $request->get_param('product_id');
            $quantity = $request->get_param('quantity') ?: 1;
            $variationId = $request->get_param('variation_id') ?: 0;
            $variation = $request->get_param('variation') ?: [];

            // Get product object
            $product = wc_get_product($productId);
            if (!$product) {
                return new WP_Error(
                    'product_not_found',
                    'Product not found',
                    ['status' => 404]
                );
            }

            // Check if product is purchasable
            if (!$product->is_purchasable()) {
                return new WP_Error(
                    'product_not_purchasable',
                    'Product is not available for purchase',
                    ['status' => 400]
                );
            }

            // Handle variable products
            if ($product->is_type('variable')) {
                if (!$variationId) {
                    return new WP_Error(
                        'variation_required',
                        'Variation ID is required for variable products',
                        ['status' => 400]
                    );
                }

                $variationProduct = wc_get_product($variationId);
                if (!$variationProduct || $variationProduct->get_parent_id() !== $productId) {
                    return new WP_Error(
                        'invalid_variation',
                        'Invalid variation for this product',
                        ['status' => 400]
                    );
                }

                // Use variation for stock checks
                $product = $variationProduct;
            }

            // Check stock availability
            if (!$product->has_enough_stock($quantity)) {
                return new WP_Error(
                    'insufficient_stock',
                    sprintf(
                        'Not enough stock. Only %d available.',
                        $product->get_stock_quantity() ?: 0
                    ),
                    ['status' => 400]
                );
            }

            // Check if product is already in cart
            $cartItemKey = WC()->cart->find_product_in_cart(
                WC()->cart->generate_cart_id($productId, $variationId, $variation)
            );

            if ($cartItemKey) {
                // Update existing cart item quantity
                $currentQuantity = WC()->cart->cart_contents[$cartItemKey]['quantity'];
                $newQuantity = $currentQuantity + $quantity;

                if (!$product->has_enough_stock($newQuantity)) {
                    return new WP_Error(
                        'insufficient_stock',
                        sprintf(
                            'Cannot add %d more items. Maximum available: %d',
                            $quantity,
                            $product->get_stock_quantity() - $currentQuantity
                        ),
                        ['status' => 400]
                    );
                }

                WC()->cart->set_quantity($cartItemKey, $newQuantity);
                $message = 'Product quantity updated in cart';
            } else {
                // Add new item to cart
                $cartItemKey = WC()->cart->add_to_cart(
                    $productId,
                    $quantity,
                    $variationId,
                    $variation
                );

                if (!$cartItemKey) {
                    return new WP_Error(
                        'add_to_cart_failed',
                        'Failed to add product to cart',
                        ['status' => 500]
                    );
                }

                $message = 'Product added to cart successfully';
            }

            // Get updated cart data
            $cartData = $this->getCartData();

            Logger::info('Product added to cart', [
                'product_id' => $productId,
                'variation_id' => $variationId,
                'quantity' => $quantity,
                'cart_item_key' => $cartItemKey
            ]);

            // Trigger action hook
            do_action('woo_ai_assistant_product_added_to_cart', [
                'product_id' => $productId,
                'variation_id' => $variationId,
                'quantity' => $quantity,
                'cart_item_key' => $cartItemKey,
                'cart_data' => $cartData
            ]);

            return new WP_REST_Response([
                'success' => true,
                'message' => $message,
                'data' => [
                    'cart_item_key' => $cartItemKey,
                    'product' => [
                        'id' => $productId,
                        'name' => $product->get_name(),
                        'price' => $product->get_price(),
                        'image' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail')
                    ],
                    'quantity' => $quantity,
                    'cart_totals' => $cartData['totals']
                ]
            ], 200);
        } catch (Exception $e) {
            Logger::error('Exception in add to cart', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return new WP_Error(
                'add_to_cart_exception',
                'An error occurred while adding the product to cart',
                ['status' => 500]
            );
        }
    }

    /**
     * Apply coupon to cart
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public function applyCoupon(WP_REST_Request $request)
    {
        try {
            if (!Utils::canUseCart()) {
                return new WP_Error(
                    'cart_unavailable',
                    'Cart functionality is not available',
                    ['status' => 400]
                );
            }

            $couponCode = strtolower(trim($request->get_param('coupon_code')));

            // Check if cart is empty
            if (WC()->cart->is_empty()) {
                return new WP_Error(
                    'cart_empty',
                    'Cannot apply coupon to empty cart',
                    ['status' => 400]
                );
            }

            // Check if coupon is already applied
            if (WC()->cart->has_discount($couponCode)) {
                return new WP_Error(
                    'coupon_already_applied',
                    'Coupon is already applied to cart',
                    ['status' => 400]
                );
            }

            // Get coupon object
            $coupon = new \WC_Coupon($couponCode);
            if (!$coupon->get_id()) {
                return new WP_Error(
                    'coupon_not_found',
                    'Coupon code not found',
                    ['status' => 404]
                );
            }

            // Validate coupon
            $validationResult = $coupon->is_valid();
            if (is_wp_error($validationResult)) {
                return new WP_Error(
                    'coupon_invalid',
                    $validationResult->get_error_message(),
                    ['status' => 400]
                );
            }

            // Check coupon usage restrictions
            if (!$coupon->is_valid_for_cart()) {
                return new WP_Error(
                    'coupon_not_applicable',
                    'Coupon is not applicable to current cart contents',
                    ['status' => 400]
                );
            }

            // Apply coupon
            $applied = WC()->cart->apply_coupon($couponCode);
            if (!$applied) {
                return new WP_Error(
                    'coupon_application_failed',
                    'Failed to apply coupon. Please check coupon restrictions.',
                    ['status' => 400]
                );
            }

            // Get updated cart data
            $cartData = $this->getCartData();

            Logger::info('Coupon applied to cart', [
                'coupon_code' => $couponCode,
                'discount_amount' => $coupon->get_amount(),
                'discount_type' => $coupon->get_discount_type()
            ]);

            // Trigger action hook
            do_action('woo_ai_assistant_coupon_applied', [
                'coupon_code' => $couponCode,
                'coupon' => $coupon,
                'cart_data' => $cartData
            ]);

            return new WP_REST_Response([
                'success' => true,
                'message' => 'Coupon applied successfully',
                'data' => [
                    'coupon' => [
                        'code' => $couponCode,
                        'description' => $coupon->get_description(),
                        'discount_type' => $coupon->get_discount_type(),
                        'amount' => $coupon->get_amount(),
                        'minimum_amount' => $coupon->get_minimum_amount(),
                        'maximum_amount' => $coupon->get_maximum_amount()
                    ],
                    'cart_totals' => $cartData['totals'],
                    'applied_coupons' => $cartData['coupons']
                ]
            ], 200);
        } catch (Exception $e) {
            Logger::error('Exception in apply coupon', [
                'error' => $e->getMessage(),
                'coupon_code' => $couponCode ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);

            return new WP_Error(
                'coupon_application_exception',
                'An error occurred while applying the coupon',
                ['status' => 500]
            );
        }
    }

    /**
     * Remove coupon from cart
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public function removeCoupon(WP_REST_Request $request)
    {
        try {
            if (!Utils::canUseCart()) {
                return new WP_Error(
                    'cart_unavailable',
                    'Cart functionality is not available',
                    ['status' => 400]
                );
            }

            $couponCode = strtolower(trim($request->get_param('coupon_code')));

            // Check if coupon is applied
            if (!WC()->cart->has_discount($couponCode)) {
                return new WP_Error(
                    'coupon_not_applied',
                    'Coupon is not currently applied to cart',
                    ['status' => 400]
                );
            }

            // Remove coupon
            $removed = WC()->cart->remove_coupon($couponCode);
            if (!$removed) {
                return new WP_Error(
                    'coupon_removal_failed',
                    'Failed to remove coupon from cart',
                    ['status' => 500]
                );
            }

            // Get updated cart data
            $cartData = $this->getCartData();

            Logger::info('Coupon removed from cart', [
                'coupon_code' => $couponCode
            ]);

            // Trigger action hook
            do_action('woo_ai_assistant_coupon_removed', [
                'coupon_code' => $couponCode,
                'cart_data' => $cartData
            ]);

            return new WP_REST_Response([
                'success' => true,
                'message' => 'Coupon removed successfully',
                'data' => [
                    'removed_coupon' => $couponCode,
                    'cart_totals' => $cartData['totals'],
                    'applied_coupons' => $cartData['coupons']
                ]
            ], 200);
        } catch (Exception $e) {
            Logger::error('Exception in remove coupon', [
                'error' => $e->getMessage(),
                'coupon_code' => $couponCode ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);

            return new WP_Error(
                'coupon_removal_exception',
                'An error occurred while removing the coupon',
                ['status' => 500]
            );
        }
    }

    /**
     * Update cart item quantity
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public function updateCart(WP_REST_Request $request)
    {
        try {
            if (!Utils::canUseCart()) {
                return new WP_Error(
                    'cart_unavailable',
                    'Cart functionality is not available',
                    ['status' => 400]
                );
            }

            $cartItemKey = $request->get_param('cart_item_key');
            $quantity = $request->get_param('quantity');

            // Check if cart item exists
            $cartItem = WC()->cart->get_cart_item($cartItemKey);
            if (!$cartItem) {
                return new WP_Error(
                    'cart_item_not_found',
                    'Cart item not found',
                    ['status' => 404]
                );
            }

            // If quantity is 0, remove the item
            if ($quantity === 0) {
                $removed = WC()->cart->remove_cart_item($cartItemKey);
                if (!$removed) {
                    return new WP_Error(
                        'cart_item_removal_failed',
                        'Failed to remove item from cart',
                        ['status' => 500]
                    );
                }

                $message = 'Item removed from cart';
            } else {
                // Get product to check stock
                $product = $cartItem['data'];
                if (!$product->has_enough_stock($quantity)) {
                    return new WP_Error(
                        'insufficient_stock',
                        sprintf(
                            'Not enough stock. Only %d available.',
                            $product->get_stock_quantity() ?: 0
                        ),
                        ['status' => 400]
                    );
                }

                // Update quantity
                $updated = WC()->cart->set_quantity($cartItemKey, $quantity);
                if (!$updated) {
                    return new WP_Error(
                        'cart_update_failed',
                        'Failed to update cart item quantity',
                        ['status' => 500]
                    );
                }

                $message = 'Cart item quantity updated';
            }

            // Get updated cart data
            $cartData = $this->getCartData();

            Logger::info('Cart item updated', [
                'cart_item_key' => $cartItemKey,
                'quantity' => $quantity,
                'product_id' => $cartItem['product_id']
            ]);

            // Trigger action hook
            do_action('woo_ai_assistant_cart_item_updated', [
                'cart_item_key' => $cartItemKey,
                'quantity' => $quantity,
                'cart_item' => $cartItem,
                'cart_data' => $cartData
            ]);

            return new WP_REST_Response([
                'success' => true,
                'message' => $message,
                'data' => [
                    'cart_item_key' => $cartItemKey,
                    'quantity' => $quantity,
                    'cart_totals' => $cartData['totals'],
                    'cart_items_count' => WC()->cart->get_cart_contents_count()
                ]
            ], 200);
        } catch (Exception $e) {
            Logger::error('Exception in update cart', [
                'error' => $e->getMessage(),
                'cart_item_key' => $cartItemKey ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);

            return new WP_Error(
                'cart_update_exception',
                'An error occurred while updating the cart',
                ['status' => 500]
            );
        }
    }

    /**
     * Remove item from cart
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public function removeFromCart(WP_REST_Request $request)
    {
        try {
            if (!Utils::canUseCart()) {
                return new WP_Error(
                    'cart_unavailable',
                    'Cart functionality is not available',
                    ['status' => 400]
                );
            }

            $cartItemKey = $request->get_param('cart_item_key');

            // Check if cart item exists
            $cartItem = WC()->cart->get_cart_item($cartItemKey);
            if (!$cartItem) {
                return new WP_Error(
                    'cart_item_not_found',
                    'Cart item not found',
                    ['status' => 404]
                );
            }

            // Remove item
            $removed = WC()->cart->remove_cart_item($cartItemKey);
            if (!$removed) {
                return new WP_Error(
                    'cart_item_removal_failed',
                    'Failed to remove item from cart',
                    ['status' => 500]
                );
            }

            // Get updated cart data
            $cartData = $this->getCartData();

            Logger::info('Item removed from cart', [
                'cart_item_key' => $cartItemKey,
                'product_id' => $cartItem['product_id']
            ]);

            // Trigger action hook
            do_action('woo_ai_assistant_cart_item_removed', [
                'cart_item_key' => $cartItemKey,
                'cart_item' => $cartItem,
                'cart_data' => $cartData
            ]);

            return new WP_REST_Response([
                'success' => true,
                'message' => 'Item removed from cart successfully',
                'data' => [
                    'removed_item_key' => $cartItemKey,
                    'cart_totals' => $cartData['totals'],
                    'cart_items_count' => WC()->cart->get_cart_contents_count()
                ]
            ], 200);
        } catch (Exception $e) {
            Logger::error('Exception in remove from cart', [
                'error' => $e->getMessage(),
                'cart_item_key' => $cartItemKey ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);

            return new WP_Error(
                'cart_removal_exception',
                'An error occurred while removing the item from cart',
                ['status' => 500]
            );
        }
    }

    /**
     * Clear all items from cart
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public function clearCart(WP_REST_Request $request)
    {
        try {
            if (!Utils::canUseCart()) {
                return new WP_Error(
                    'cart_unavailable',
                    'Cart functionality is not available',
                    ['status' => 400]
                );
            }

            // Check if cart is already empty
            if (WC()->cart->is_empty()) {
                return new WP_REST_Response([
                    'success' => true,
                    'message' => 'Cart is already empty',
                    'data' => [
                        'cart_totals' => $this->getCartTotals(),
                        'cart_items_count' => 0
                    ]
                ], 200);
            }

            $itemsCount = WC()->cart->get_cart_contents_count();

            // Clear cart
            WC()->cart->empty_cart();

            Logger::info('Cart cleared', [
                'items_removed' => $itemsCount
            ]);

            // Trigger action hook
            do_action('woo_ai_assistant_cart_cleared', [
                'items_count' => $itemsCount
            ]);

            return new WP_REST_Response([
                'success' => true,
                'message' => 'Cart cleared successfully',
                'data' => [
                    'items_removed' => $itemsCount,
                    'cart_totals' => $this->getCartTotals(),
                    'cart_items_count' => 0
                ]
            ], 200);
        } catch (Exception $e) {
            Logger::error('Exception in clear cart', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return new WP_Error(
                'cart_clear_exception',
                'An error occurred while clearing the cart',
                ['status' => 500]
            );
        }
    }

    /**
     * Get cart contents and totals
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public function getCart(WP_REST_Request $request)
    {
        try {
            if (!Utils::canUseCart()) {
                return new WP_Error(
                    'cart_unavailable',
                    'Cart functionality is not available',
                    ['status' => 400]
                );
            }

            $cartData = $this->getCartData();

            return new WP_REST_Response([
                'success' => true,
                'message' => 'Cart data retrieved successfully',
                'data' => $cartData
            ], 200);
        } catch (Exception $e) {
            Logger::error('Exception in get cart', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return new WP_Error(
                'cart_retrieval_exception',
                'An error occurred while retrieving cart data',
                ['status' => 500]
            );
        }
    }

    /**
     * Check action permission
     *
     * @param WP_REST_Request $request Request object
     * @return bool|WP_Error True if permission granted
     */
    public function checkActionPermission(WP_REST_Request $request)
    {
        // Actions require WooCommerce to be active
        if (!Utils::canUseCart()) {
            return new WP_Error(
                'cart_unavailable',
                'Cart functionality is not available',
                ['status' => 400]
            );
        }

        // Allow both logged in and guest users to perform cart actions
        return true;
    }

    /**
     * Validate product ID
     *
     * @param int $productId Product ID
     * @param WP_REST_Request $request Request object
     * @param string $param Parameter name
     * @return bool|WP_Error True if valid
     */
    public function validateProductId($productId, $request, $param)
    {
        if ($productId <= 0) {
            return new WP_Error(
                'invalid_product_id',
                'Product ID must be a positive integer',
                ['status' => 400]
            );
        }

        $product = wc_get_product($productId);
        if (!$product) {
            return new WP_Error(
                'product_not_found',
                'Product not found',
                ['status' => 404]
            );
        }

        if (!$product->is_purchasable()) {
            return new WP_Error(
                'product_not_purchasable',
                'Product is not purchasable',
                ['status' => 400]
            );
        }

        return true;
    }

    /**
     * Validate coupon code
     *
     * @param string $couponCode Coupon code
     * @param WP_REST_Request $request Request object
     * @param string $param Parameter name
     * @return bool|WP_Error True if valid
     */
    public function validateCouponCode($couponCode, $request, $param)
    {
        if (empty(trim($couponCode))) {
            return new WP_Error(
                'empty_coupon_code',
                'Coupon code cannot be empty',
                ['status' => 400]
            );
        }

        if (strlen($couponCode) > 50) {
            return new WP_Error(
                'coupon_code_too_long',
                'Coupon code is too long',
                ['status' => 400]
            );
        }

        return true;
    }

    /**
     * Get comprehensive cart data including items, totals, and coupons
     *
     * @return array Complete cart data
     */
    private function getCartData(): array
    {
        $cart = WC()->cart;
        $cartItems = [];

        // Process cart items
        foreach ($cart->get_cart() as $cartItemKey => $cartItem) {
            $product = $cartItem['data'];
            $productId = $cartItem['product_id'];
            $variationId = $cartItem['variation_id'];

            $cartItems[] = [
                'key' => $cartItemKey,
                'product_id' => $productId,
                'variation_id' => $variationId,
                'quantity' => $cartItem['quantity'],
                'line_total' => $cartItem['line_total'],
                'line_subtotal' => $cartItem['line_subtotal'],
                'product' => [
                    'name' => $product->get_name(),
                    'price' => $product->get_price(),
                    'regular_price' => $product->get_regular_price(),
                    'sale_price' => $product->get_sale_price(),
                    'image' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail'),
                    'permalink' => $product->get_permalink(),
                    'sku' => $product->get_sku(),
                    'stock_status' => $product->get_stock_status(),
                    'stock_quantity' => $product->get_stock_quantity()
                ],
                'variation' => $cartItem['variation'] ?? []
            ];
        }

        return [
            'items' => $cartItems,
            'items_count' => $cart->get_cart_contents_count(),
            'totals' => $this->getCartTotals(),
            'coupons' => $this->getAppliedCoupons(),
            'needs_payment' => $cart->needs_payment(),
            'needs_shipping' => $cart->needs_shipping(),
            'is_empty' => $cart->is_empty()
        ];
    }

    /**
     * Get cart totals information
     *
     * @return array Cart totals data
     */
    private function getCartTotals(): array
    {
        $cart = WC()->cart;

        return [
            'subtotal' => $cart->get_subtotal(),
            'subtotal_tax' => $cart->get_subtotal_tax(),
            'discount_total' => $cart->get_discount_total(),
            'discount_tax' => $cart->get_discount_tax(),
            'shipping_total' => $cart->get_shipping_total(),
            'shipping_tax' => $cart->get_shipping_tax(),
            'fee_total' => $cart->get_fee_total(),
            'fee_tax' => $cart->get_fee_tax(),
            'tax_total' => $cart->get_total_tax(),
            'total' => $cart->get_total('edit'),
            'currency' => get_woocommerce_currency(),
            'currency_symbol' => get_woocommerce_currency_symbol()
        ];
    }

    /**
     * Get applied coupons information
     *
     * @return array Applied coupons data
     */
    private function getAppliedCoupons(): array
    {
        $appliedCoupons = [];
        $cart = WC()->cart;

        foreach ($cart->get_applied_coupons() as $couponCode) {
            $coupon = new \WC_Coupon($couponCode);
            $discountAmount = $cart->get_coupon_discount_amount($couponCode, $cart->display_prices_including_tax());

            $appliedCoupons[] = [
                'code' => $couponCode,
                'description' => $coupon->get_description(),
                'discount_type' => $coupon->get_discount_type(),
                'amount' => $coupon->get_amount(),
                'discount_amount' => $discountAmount,
                'free_shipping' => $coupon->get_free_shipping(),
                'minimum_amount' => $coupon->get_minimum_amount(),
                'maximum_amount' => $coupon->get_maximum_amount()
            ];
        }

        return $appliedCoupons;
    }
}
