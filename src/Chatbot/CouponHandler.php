<?php

/**
 * Coupon Handler Class
 *
 * Manages intelligent coupon generation, validation, and application for the AI assistant.
 * Implements business rules, rate limiting, and abuse prevention while providing contextual
 * coupon offers based on user behavior, cart value, and conversation sentiment.
 *
 * @package WooAiAssistant
 * @subpackage Chatbot
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Chatbot;

use WooAiAssistant\Common\Traits\Singleton;
use WooAiAssistant\Common\Utils;
use WC_Coupon;
use WP_Error;
use Exception;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class CouponHandler
 *
 * Handles coupon generation, validation, and business rules for AI-driven offers.
 *
 * @since 1.0.0
 */
class CouponHandler
{
    use Singleton;

    /**
     * WordPress database instance
     *
     * @var \wpdb
     */
    private \wpdb $wpdb;

    /**
     * Coupon types and their configurations
     *
     * @var array
     */
    private array $couponTypes = [
        'percentage' => [
            'type' => 'percent',
            'min_discount' => 5,
            'max_discount' => 20,
            'min_cart_amount' => 50,
        ],
        'fixed_amount' => [
            'type' => 'fixed_cart',
            'min_discount' => 5,
            'max_discount' => 25,
            'min_cart_amount' => 100,
        ],
        'free_shipping' => [
            'type' => 'percent',
            'discount' => 0,
            'free_shipping' => true,
            'min_cart_amount' => 75,
        ],
    ];

    /**
     * Rate limiting settings
     *
     * @var array
     */
    private array $rateLimits = [
        'max_coupons_per_day' => 1,
        'max_coupons_per_week' => 3,
        'max_coupons_per_month' => 10,
        'min_time_between_coupons' => 3600, // 1 hour
    ];

    /**
     * Coupon expiry settings
     *
     * @var array
     */
    private array $expirySettings = [
        'default_expiry_days' => 7,
        'urgent_expiry_hours' => 24,
        'abandoned_cart_hours' => 48,
    ];

    /**
     * Initialize the coupon handler
     *
     * Sets up database connection and WordPress hooks.
     *
     * @return void
     */
    protected function init(): void
    {
        global $wpdb;
        $this->wpdb = $wpdb;

        // Hook into WordPress actions
        add_action('wp_ajax_woo_ai_apply_coupon', [$this, 'handleAjaxApplyCoupon']);
        add_action('wp_ajax_nopriv_woo_ai_apply_coupon', [$this, 'handleAjaxApplyCoupon']);
        add_action('woocommerce_before_checkout_form', [$this, 'displayCouponNotifications']);
        add_action('woo_ai_assistant_hourly_cleanup', [$this, 'cleanupExpiredCoupons']);

        // Filter coupon data for analytics
        add_filter('woocommerce_coupon_is_valid', [$this, 'validateCouponContext'], 10, 2);
    }

    /**
     * Generate a contextual coupon based on conversation and user data
     *
     * Analyzes user context, cart value, sentiment, and history to generate
     * an appropriate coupon offer with proper business rule validation.
     *
     * @since 1.0.0
     * @param array $context Conversation context data.
     * @param array $context['user_id'] Current user ID.
     * @param array $context['cart_total'] Current cart total.
     * @param array $context['sentiment'] Conversation sentiment (positive, negative, neutral).
     * @param array $context['intent'] User intent (purchase, browse, support).
     * @param array $context['page_type'] Current page type (product, cart, checkout).
     * @param array $options Optional parameters for coupon generation.
     * @param string $options['type'] Preferred coupon type (percentage, fixed_amount, free_shipping).
     * @param int $options['urgency'] Urgency level (1-5) for expiry timing.
     *
     * @return array|WP_Error Coupon data array or WP_Error on failure.
     *                       Success array contains 'coupon_code', 'discount_type', 'amount', 'description'.
     *
     * @throws Exception When WooCommerce is not active.
     *
     * @example
     * ```php
     * $handler = CouponHandler::getInstance();
     * $coupon = $handler->generateContextualCoupon([
     *     'user_id' => 123,
     *     'cart_total' => 85.50,
     *     'sentiment' => 'frustrated',
     *     'intent' => 'purchase',
     *     'page_type' => 'cart'
     * ], ['urgency' => 4]);
     * ```
     */
    public function generateContextualCoupon(array $context, array $options = []): array|WP_Error
    {
        try {
            // Validate prerequisites
            if (!Utils::isWooCommerceActive()) {
                throw new Exception('WooCommerce is required for coupon generation');
            }

            // Sanitize and validate context
            $context = $this->sanitizeContext($context);
            $options = $this->sanitizeOptions($options);

            // Check rate limits
            $rateLimitCheck = $this->checkRateLimits($context['user_id']);
            if (is_wp_error($rateLimitCheck)) {
                return $rateLimitCheck;
            }

            // Determine best coupon type based on context
            $couponType = $this->determineCouponType($context, $options);

            // Calculate coupon parameters
            $couponParams = $this->calculateCouponParameters($couponType, $context);

            // Generate unique coupon code
            $couponCode = $this->generateCouponCode($context);

            // Create WooCommerce coupon
            $wcCoupon = $this->createWooCommerceCoupon($couponCode, $couponParams, $context);
            if (is_wp_error($wcCoupon)) {
                return $wcCoupon;
            }

            // Track coupon generation
            $this->trackCouponGeneration($couponCode, $context, $couponParams);

            // Prepare response data
            $response = [
                'coupon_code' => $couponCode,
                'discount_type' => $couponParams['discount_type'],
                'amount' => $couponParams['amount'],
                'description' => $this->generateCouponDescription($couponParams),
                'expiry_date' => $couponParams['expiry_date'],
                'min_amount' => $couponParams['minimum_amount'] ?? 0,
                'free_shipping' => $couponParams['free_shipping'] ?? false,
                'usage_limit' => 1,
                'generated_at' => current_time('mysql'),
            ];

            Utils::debugLog("Generated contextual coupon: {$couponCode}", 'CouponHandler');

            return $response;
        } catch (Exception $e) {
            $error = new WP_Error(
                'coupon_generation_failed',
                'Failed to generate coupon: ' . $e->getMessage(),
                ['context' => $context]
            );

            Utils::debugLog("Coupon generation failed: " . $e->getMessage(), 'CouponHandler');
            return $error;
        }
    }

    /**
     * Check if user is eligible for a coupon based on rate limits
     *
     * Validates user against various rate limiting rules including daily,
     * weekly, and monthly limits, plus minimum time between coupons.
     *
     * @since 1.0.0
     * @param int $userId User ID to check eligibility for.
     *
     * @return true|WP_Error True if eligible, WP_Error with details if not.
     *
     * @throws Exception When database query fails.
     */
    public function checkRateLimits(int $userId): true|WP_Error
    {
        try {
            // Get user's coupon history
            $couponHistory = $this->getUserCouponHistory($userId);

            // Check daily limit
            $dailyCoupons = $this->countCouponsInPeriod($couponHistory, 'day');
            if ($dailyCoupons >= $this->rateLimits['max_coupons_per_day']) {
                return new WP_Error(
                    'daily_limit_exceeded',
                    'Daily coupon limit reached. Try again tomorrow!',
                    ['limit' => $this->rateLimits['max_coupons_per_day']]
                );
            }

            // Check weekly limit
            $weeklyCoupons = $this->countCouponsInPeriod($couponHistory, 'week');
            if ($weeklyCoupons >= $this->rateLimits['max_coupons_per_week']) {
                return new WP_Error(
                    'weekly_limit_exceeded',
                    'Weekly coupon limit reached. Please wait before requesting another coupon.',
                    ['limit' => $this->rateLimits['max_coupons_per_week']]
                );
            }

            // Check monthly limit
            $monthlyCoupons = $this->countCouponsInPeriod($couponHistory, 'month');
            if ($monthlyCoupons >= $this->rateLimits['max_coupons_per_month']) {
                return new WP_Error(
                    'monthly_limit_exceeded',
                    'Monthly coupon limit reached.',
                    ['limit' => $this->rateLimits['max_coupons_per_month']]
                );
            }

            // Check minimum time between coupons
            $lastCouponTime = $this->getLastCouponTime($userId);
            $timeSinceLastCoupon = time() - $lastCouponTime;

            if ($timeSinceLastCoupon < $this->rateLimits['min_time_between_coupons']) {
                $waitTime = $this->rateLimits['min_time_between_coupons'] - $timeSinceLastCoupon;
                return new WP_Error(
                    'time_limit_not_met',
                    sprintf('Please wait %d minutes before requesting another coupon.', ceil($waitTime / 60)),
                    ['wait_seconds' => $waitTime]
                );
            }

            return true;
        } catch (Exception $e) {
            Utils::debugLog("Rate limit check failed: " . $e->getMessage(), 'CouponHandler');
            return new WP_Error(
                'rate_limit_check_failed',
                'Unable to verify coupon eligibility',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Apply a generated coupon to the user's cart
     *
     * Validates and applies the specified coupon code to the current WooCommerce cart,
     * with additional context validation to ensure proper usage.
     *
     * @since 1.0.0
     * @param string $couponCode Coupon code to apply.
     * @param array $context Optional context for validation.
     *
     * @return true|WP_Error True if applied successfully, WP_Error on failure.
     */
    public function applyCouponToCart(string $couponCode, array $context = []): true|WP_Error
    {
        try {
            // Validate prerequisites
            if (!Utils::canUseCart()) {
                return new WP_Error(
                    'cart_not_available',
                    'Cart is not available for coupon application'
                );
            }

            // Sanitize coupon code
            $couponCode = sanitize_text_field($couponCode);
            if (empty($couponCode)) {
                return new WP_Error(
                    'invalid_coupon_code',
                    'Please provide a valid coupon code'
                );
            }

            // Check if coupon exists and is valid
            $coupon = new WC_Coupon($couponCode);
            if (!$coupon->is_valid()) {
                return new WP_Error(
                    'invalid_coupon',
                    'This coupon is not valid or has expired'
                );
            }

            // Verify coupon is AI-generated (has our prefix)
            if (!$this->isAiGeneratedCoupon($couponCode)) {
                return new WP_Error(
                    'unauthorized_coupon',
                    'This coupon cannot be applied through the AI assistant'
                );
            }

            // Get cart instance
            $cart = WC()->cart;
            if (!$cart) {
                return new WP_Error(
                    'cart_error',
                    'Unable to access cart for coupon application'
                );
            }

            // Check if coupon is already applied
            $appliedCoupons = $cart->get_applied_coupons();
            if (in_array($couponCode, $appliedCoupons)) {
                return new WP_Error(
                    'coupon_already_applied',
                    'This coupon is already applied to your cart'
                );
            }

            // Apply the coupon
            $applied = $cart->apply_coupon($couponCode);
            if (!$applied) {
                return new WP_Error(
                    'coupon_application_failed',
                    'Failed to apply coupon to cart'
                );
            }

            // Update coupon usage tracking
            $this->trackCouponUsage($couponCode, $context);

            Utils::debugLog("Applied coupon to cart: {$couponCode}", 'CouponHandler');
            return true;
        } catch (Exception $e) {
            Utils::debugLog("Coupon application failed: " . $e->getMessage(), 'CouponHandler');
            return new WP_Error(
                'coupon_application_error',
                'An error occurred while applying the coupon',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Get coupon recommendations based on user context
     *
     * Analyzes user behavior and cart contents to suggest the most appropriate
     * coupon offers without actually generating them.
     *
     * @since 1.0.0
     * @param array $context User and cart context data.
     *
     * @return array Array of coupon recommendations with estimated benefits.
     */
    public function getCouponRecommendations(array $context): array
    {
        $context = $this->sanitizeContext($context);
        $recommendations = [];

        // Check rate limits first
        $rateLimitCheck = $this->checkRateLimits($context['user_id']);
        if (is_wp_error($rateLimitCheck)) {
            return [
                'eligible' => false,
                'reason' => $rateLimitCheck->get_error_message(),
                'recommendations' => []
            ];
        }

        // Analyze cart and generate recommendations
        $cartTotal = $context['cart_total'];
        $sentiment = $context['sentiment'] ?? 'neutral';
        $intent = $context['intent'] ?? 'browse';

        // Percentage discount recommendation
        if ($cartTotal >= $this->couponTypes['percentage']['min_cart_amount']) {
            $discount = $this->calculateOptimalPercentageDiscount($context);
            $savings = ($cartTotal * $discount) / 100;

            $recommendations['percentage'] = [
                'type' => 'percentage',
                'discount' => $discount,
                'estimated_savings' => $savings,
                'description' => "Save {$discount}% on your order ($" . number_format($savings, 2) . ")",
                'urgency' => $this->calculateUrgency($sentiment, $intent),
                'recommended' => $sentiment === 'frustrated' || $intent === 'abandon'
            ];
        }

        // Fixed amount recommendation
        if ($cartTotal >= $this->couponTypes['fixed_amount']['min_cart_amount']) {
            $discount = $this->calculateOptimalFixedDiscount($context);

            $recommendations['fixed_amount'] = [
                'type' => 'fixed_amount',
                'discount' => $discount,
                'estimated_savings' => $discount,
                'description' => "Save \${$discount} on your order",
                'urgency' => $this->calculateUrgency($sentiment, $intent),
                'recommended' => $cartTotal >= 150 && $intent === 'purchase'
            ];
        }

        // Free shipping recommendation
        if ($cartTotal >= $this->couponTypes['free_shipping']['min_cart_amount']) {
            $shippingCost = $this->estimateShippingCost($context);

            $recommendations['free_shipping'] = [
                'type' => 'free_shipping',
                'discount' => 0,
                'estimated_savings' => $shippingCost,
                'description' => "Get free shipping (save $" . number_format($shippingCost, 2) . ")",
                'urgency' => $this->calculateUrgency($sentiment, $intent),
                'recommended' => $shippingCost > 10 && $cartTotal < 200
            ];
        }

        return [
            'eligible' => !empty($recommendations),
            'recommendations' => $recommendations,
            'best_option' => $this->selectBestRecommendation($recommendations)
        ];
    }

    /**
     * Handle AJAX request for coupon application
     *
     * Processes AJAX requests from the frontend to apply coupons to cart.
     *
     * @return void
     */
    public function handleAjaxApplyCoupon(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'woo_ai_coupon_apply')) {
            wp_die(json_encode(['success' => false, 'message' => 'Security verification failed']), 403);
        }

        $couponCode = sanitize_text_field($_POST['coupon_code'] ?? '');
        $context = [
            'user_id' => get_current_user_id(),
            'page_type' => sanitize_text_field($_POST['page_type'] ?? ''),
            'conversation_id' => sanitize_text_field($_POST['conversation_id'] ?? '')
        ];

        $result = $this->applyCouponToCart($couponCode, $context);

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message(),
                'code' => $result->get_error_code()
            ]);
        } else {
            wp_send_json_success([
                'message' => 'Coupon applied successfully!',
                'coupon_code' => $couponCode
            ]);
        }
    }

    /**
     * Clean up expired coupons from the database
     *
     * Removes expired AI-generated coupons to keep the database clean.
     * Should be called via scheduled cron job.
     *
     * @return int Number of coupons cleaned up.
     */
    public function cleanupExpiredCoupons(): int
    {
        $cleanedUp = 0;

        try {
            // Get all AI-generated coupons
            $aiCoupons = get_posts([
                'post_type' => 'shop_coupon',
                'post_status' => 'publish',
                'meta_query' => [
                    [
                        'key' => '_woo_ai_generated',
                        'value' => '1',
                        'compare' => '='
                    ]
                ],
                'posts_per_page' => -1
            ]);

            foreach ($aiCoupons as $couponPost) {
                $coupon = new WC_Coupon($couponPost->ID);

                // Check if coupon is expired
                $expiryDate = $coupon->get_date_expires();
                if ($expiryDate && $expiryDate->getTimestamp() < current_time('timestamp')) {
                    // Check if coupon has been used
                    if ($coupon->get_usage_count() === 0) {
                        wp_delete_post($couponPost->ID, true);
                        $cleanedUp++;
                    }
                }
            }

            Utils::debugLog("Cleaned up {$cleanedUp} expired coupons", 'CouponHandler');
        } catch (Exception $e) {
            Utils::debugLog("Coupon cleanup failed: " . $e->getMessage(), 'CouponHandler');
        }

        return $cleanedUp;
    }

    /**
     * Sanitize context data for coupon generation
     *
     * @param array $context Raw context data
     * @return array Sanitized context data
     */
    private function sanitizeContext(array $context): array
    {
        return [
            'user_id' => absint($context['user_id'] ?? get_current_user_id()),
            'cart_total' => floatval($context['cart_total'] ?? 0),
            'sentiment' => sanitize_text_field($context['sentiment'] ?? 'neutral'),
            'intent' => sanitize_text_field($context['intent'] ?? 'browse'),
            'page_type' => sanitize_text_field($context['page_type'] ?? 'unknown'),
            'conversation_id' => sanitize_text_field($context['conversation_id'] ?? ''),
            'products' => array_map('absint', $context['products'] ?? []),
        ];
    }

    /**
     * Sanitize options for coupon generation
     *
     * @param array $options Raw options data
     * @return array Sanitized options data
     */
    private function sanitizeOptions(array $options): array
    {
        $validTypes = ['percentage', 'fixed_amount', 'free_shipping'];

        return [
            'type' => in_array($options['type'] ?? '', $validTypes) ? $options['type'] : null,
            'urgency' => max(1, min(5, intval($options['urgency'] ?? 3))),
            'max_discount' => floatval($options['max_discount'] ?? 0),
        ];
    }

    /**
     * Determine the best coupon type based on context
     *
     * @param array $context User context data
     * @param array $options Generation options
     * @return string Coupon type key
     */
    private function determineCouponType(array $context, array $options): string
    {
        // Use specified type if valid
        if ($options['type']) {
            return $options['type'];
        }

        $cartTotal = $context['cart_total'];
        $sentiment = $context['sentiment'];
        $intent = $context['intent'];

        // High-value carts get fixed discounts
        if ($cartTotal >= 200) {
            return 'fixed_amount';
        }

        // Frustrated or abandoning users get percentage discounts
        if (in_array($sentiment, ['frustrated', 'negative']) || $intent === 'abandon') {
            return 'percentage';
        }

        // Free shipping for medium-value carts
        if ($cartTotal >= 75 && $cartTotal < 150) {
            return 'free_shipping';
        }

        // Default to percentage
        return 'percentage';
    }

    /**
     * Calculate coupon parameters based on type and context
     *
     * @param string $type Coupon type
     * @param array $context User context
     * @return array Coupon parameters
     */
    private function calculateCouponParameters(string $type, array $context): array
    {
        $config = $this->couponTypes[$type];
        $urgency = $this->calculateUrgency($context['sentiment'], $context['intent']);

        $params = [
            'discount_type' => $config['type'],
            'minimum_amount' => $config['min_cart_amount'],
            'usage_limit' => 1,
            'usage_limit_per_user' => 1,
            'expiry_date' => $this->calculateExpiryDate($urgency),
        ];

        if ($type === 'free_shipping') {
            $params['amount'] = 0;
            $params['free_shipping'] = true;
        } else {
            if ($type === 'percentage') {
                $params['amount'] = $this->calculateOptimalPercentageDiscount($context);
            } else {
                $params['amount'] = $this->calculateOptimalFixedDiscount($context);
            }
        }

        return $params;
    }

    /**
     * Calculate optimal percentage discount based on context
     *
     * @param array $context User context
     * @return float Discount percentage
     */
    private function calculateOptimalPercentageDiscount(array $context): float
    {
        $baseDiscount = 10; // Default 10%
        $sentiment = $context['sentiment'];
        $cartTotal = $context['cart_total'];

        // Increase discount for negative sentiment
        if (in_array($sentiment, ['frustrated', 'negative'])) {
            $baseDiscount += 5;
        }

        // Adjust based on cart value
        if ($cartTotal >= 150) {
            $baseDiscount += 2;
        } elseif ($cartTotal >= 100) {
            $baseDiscount += 1;
        }

        // Ensure within limits
        return min($baseDiscount, $this->couponTypes['percentage']['max_discount']);
    }

    /**
     * Calculate optimal fixed discount based on context
     *
     * @param array $context User context
     * @return float Discount amount
     */
    private function calculateOptimalFixedDiscount(array $context): float
    {
        $cartTotal = $context['cart_total'];
        $baseDiscount = 10; // Default $10

        // Scale with cart value
        if ($cartTotal >= 200) {
            $baseDiscount = 20;
        } elseif ($cartTotal >= 150) {
            $baseDiscount = 15;
        }

        // Increase for negative sentiment
        if (in_array($context['sentiment'], ['frustrated', 'negative'])) {
            $baseDiscount += 5;
        }

        // Ensure within limits
        return min($baseDiscount, $this->couponTypes['fixed_amount']['max_discount']);
    }

    /**
     * Calculate urgency level based on sentiment and intent
     *
     * @param string $sentiment User sentiment
     * @param string $intent User intent
     * @return int Urgency level (1-5)
     */
    private function calculateUrgency(string $sentiment, string $intent): int
    {
        $urgency = 3; // Default

        if ($intent === 'abandon') {
            $urgency = 5;
        } elseif ($intent === 'purchase') {
            $urgency = 4;
        }

        if (in_array($sentiment, ['frustrated', 'negative'])) {
            $urgency = min(5, $urgency + 1);
        }

        return $urgency;
    }

    /**
     * Calculate expiry date based on urgency
     *
     * @param int $urgency Urgency level
     * @return string Expiry date in Y-m-d format
     */
    private function calculateExpiryDate(int $urgency): string
    {
        $days = $this->expirySettings['default_expiry_days'];

        if ($urgency >= 5) {
            $days = 1; // 24 hours for urgent cases
        } elseif ($urgency >= 4) {
            $days = 2; // 48 hours for high urgency
        } elseif ($urgency >= 3) {
            $days = 3; // 3 days for medium urgency
        }

        return date('Y-m-d', strtotime("+{$days} days"));
    }

    /**
     * Generate unique coupon code
     *
     * @param array $context User context
     * @return string Unique coupon code
     */
    private function generateCouponCode(array $context): string
    {
        $prefix = 'AI';
        $userId = str_pad($context['user_id'], 4, '0', STR_PAD_LEFT);
        $timestamp = substr(time(), -6);
        $random = strtoupper(substr(uniqid(), -3));

        return "{$prefix}{$userId}{$timestamp}{$random}";
    }

    /**
     * Create WooCommerce coupon object
     *
     * @param string $code Coupon code
     * @param array $params Coupon parameters
     * @param array $context Generation context
     * @return WC_Coupon|WP_Error Created coupon or error
     */
    private function createWooCommerceCoupon(string $code, array $params, array $context): WC_Coupon|WP_Error
    {
        try {
            $couponData = [
                'post_title' => $code,
                'post_content' => '',
                'post_status' => 'publish',
                'post_author' => 1,
                'post_type' => 'shop_coupon'
            ];

            $couponId = wp_insert_post($couponData);
            if (is_wp_error($couponId)) {
                return $couponId;
            }

            // Set coupon meta data
            update_post_meta($couponId, 'discount_type', $params['discount_type']);
            update_post_meta($couponId, 'coupon_amount', $params['amount']);
            update_post_meta($couponId, 'individual_use', 'yes');
            update_post_meta($couponId, 'usage_limit', $params['usage_limit']);
            update_post_meta($couponId, 'usage_limit_per_user', $params['usage_limit_per_user']);
            update_post_meta($couponId, 'limit_usage_to_x_items', '');
            update_post_meta($couponId, 'free_shipping', $params['free_shipping'] ? 'yes' : 'no');
            update_post_meta($couponId, 'minimum_amount', $params['minimum_amount']);
            update_post_meta($couponId, 'date_expires', strtotime($params['expiry_date']));

            // Mark as AI-generated
            update_post_meta($couponId, '_woo_ai_generated', '1');
            update_post_meta($couponId, '_woo_ai_context', json_encode($context));
            update_post_meta($couponId, '_woo_ai_generated_at', current_time('mysql'));

            return new WC_Coupon($code);
        } catch (Exception $e) {
            return new WP_Error(
                'coupon_creation_failed',
                'Failed to create WooCommerce coupon: ' . $e->getMessage()
            );
        }
    }

    /**
     * Generate coupon description for display
     *
     * @param array $params Coupon parameters
     * @return string Human-readable description
     */
    private function generateCouponDescription(array $params): string
    {
        if (!empty($params['free_shipping'])) {
            return 'Free shipping on your order';
        }

        if ($params['discount_type'] === 'percent') {
            return "Save {$params['amount']}% on your order";
        }

        return "Save \${$params['amount']} on your order";
    }

    /**
     * Track coupon generation for analytics
     *
     * @param string $code Coupon code
     * @param array $context Generation context
     * @param array $params Coupon parameters
     * @return void
     */
    private function trackCouponGeneration(string $code, array $context, array $params): void
    {
        $data = [
            'coupon_code' => $code,
            'user_id' => $context['user_id'],
            'context' => json_encode($context),
            'parameters' => json_encode($params),
            'generated_at' => current_time('mysql'),
            'ip_address' => Utils::getUserIp(),
            'user_agent' => Utils::getUserAgent()
        ];

        // Store in custom table for analytics
        $tableName = $this->wpdb->prefix . 'woo_ai_coupon_analytics';
        $this->wpdb->insert($tableName, $data);
    }

    /**
     * Track coupon usage for analytics
     *
     * @param string $code Coupon code
     * @param array $context Usage context
     * @return void
     */
    private function trackCouponUsage(string $code, array $context): void
    {
        $data = [
            'coupon_code' => $code,
            'user_id' => $context['user_id'] ?? get_current_user_id(),
            'used_at' => current_time('mysql'),
            'context' => json_encode($context)
        ];

        $tableName = $this->wpdb->prefix . 'woo_ai_coupon_usage';
        $this->wpdb->insert($tableName, $data);
    }

    /**
     * Check if coupon is AI-generated
     *
     * @param string $code Coupon code
     * @return bool True if AI-generated
     */
    private function isAiGeneratedCoupon(string $code): bool
    {
        $coupon = new WC_Coupon($code);
        return $coupon->exists() && get_post_meta($coupon->get_id(), '_woo_ai_generated', true) === '1';
    }

    /**
     * Get user's coupon history
     *
     * @param int $userId User ID
     * @return array Coupon history records
     */
    private function getUserCouponHistory(int $userId): array
    {
        $tableName = $this->wpdb->prefix . 'woo_ai_coupon_analytics';

        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$tableName} WHERE user_id = %d ORDER BY generated_at DESC",
            $userId
        ), ARRAY_A);
    }

    /**
     * Count coupons in specific time period
     *
     * @param array $history Coupon history
     * @param string $period Period (day, week, month)
     * @return int Count of coupons
     */
    private function countCouponsInPeriod(array $history, string $period): int
    {
        $now = current_time('timestamp');
        $count = 0;

        foreach ($history as $record) {
            $generatedTime = strtotime($record['generated_at']);
            $diff = $now - $generatedTime;

            $periodSeconds = match ($period) {
                'day' => DAY_IN_SECONDS,
                'week' => WEEK_IN_SECONDS,
                'month' => MONTH_IN_SECONDS,
                default => DAY_IN_SECONDS
            };

            if ($diff <= $periodSeconds) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get timestamp of last coupon generated by user
     *
     * @param int $userId User ID
     * @return int Timestamp or 0 if no coupons
     */
    private function getLastCouponTime(int $userId): int
    {
        $tableName = $this->wpdb->prefix . 'woo_ai_coupon_analytics';

        $result = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT UNIX_TIMESTAMP(generated_at) FROM {$tableName} 
             WHERE user_id = %d ORDER BY generated_at DESC LIMIT 1",
            $userId
        ));

        return $result ? (int) $result : 0;
    }

    /**
     * Estimate shipping cost for free shipping calculations
     *
     * @param array $context User context
     * @return float Estimated shipping cost
     */
    private function estimateShippingCost(array $context): float
    {
        // Simple estimation - in real implementation, this would
        // integrate with WooCommerce shipping calculations
        $baseShipping = 8.99;
        $cartTotal = $context['cart_total'];

        // Reduce shipping for higher cart values
        if ($cartTotal >= 100) {
            return max(5.99, $baseShipping * 0.7);
        }

        return $baseShipping;
    }

    /**
     * Select best recommendation from available options
     *
     * @param array $recommendations Available recommendations
     * @return string|null Best recommendation type
     */
    private function selectBestRecommendation(array $recommendations): ?string
    {
        $bestType = null;
        $bestSavings = 0;

        foreach ($recommendations as $type => $data) {
            if ($data['estimated_savings'] > $bestSavings || $data['recommended']) {
                $bestType = $type;
                $bestSavings = $data['estimated_savings'];
            }
        }

        return $bestType;
    }

    /**
     * Display coupon notifications on checkout page
     *
     * Shows available coupon offers to users during checkout.
     *
     * @return void
     */
    public function displayCouponNotifications(): void
    {
        if (!is_checkout() || !Utils::canUseCart()) {
            return;
        }

        $userId = get_current_user_id();
        if (!$userId) {
            return;
        }

        // Check if user has unused AI coupons
        $availableCoupons = $this->getAvailableCoupons($userId);

        if (!empty($availableCoupons)) {
            echo '<div class="woo-ai-coupon-notification">';
            echo '<h4>Special Offers Available!</h4>';

            foreach ($availableCoupons as $coupon) {
                echo '<p>Use code <strong>' . esc_html($coupon['code']) . '</strong> - ' . esc_html($coupon['description']) . '</p>';
            }

            echo '</div>';
        }
    }

    /**
     * Get available coupons for user
     *
     * @param int $userId User ID
     * @return array Available coupon codes and descriptions
     */
    private function getAvailableCoupons(int $userId): array
    {
        // Implementation would query for unused AI-generated coupons for the user
        // This is a placeholder for the actual database query
        return [];
    }

    /**
     * Validate coupon context during WooCommerce validation
     *
     * Additional validation hook for WooCommerce coupon validation.
     *
     * @param bool $isValid Current validation status
     * @param WC_Coupon $coupon Coupon being validated
     * @return bool Updated validation status
     */
    public function validateCouponContext(bool $isValid, WC_Coupon $coupon): bool
    {
        if (!$isValid) {
            return $isValid;
        }

        // Additional AI-specific validation can be added here
        if ($this->isAiGeneratedCoupon($coupon->get_code())) {
            // Add any AI-specific validation rules
            Utils::debugLog("Validating AI coupon: " . $coupon->get_code(), 'CouponHandler');
        }

        return $isValid;
    }
}
