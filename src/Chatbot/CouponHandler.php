<?php

/**
 * Coupon Handler Class
 *
 * Handles comprehensive coupon management functionality for the AI chatbot including
 * applying existing WooCommerce coupons, auto-generating new coupons (Unlimited plan only),
 * validating coupon eligibility, tracking usage with audit logging, and implementing
 * security guardrails to prevent abuse.
 *
 * @package WooAiAssistant
 * @subpackage Chatbot
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Chatbot;

use WooAiAssistant\Common\Utils;
use WooAiAssistant\Common\Traits\Singleton;
use WooAiAssistant\Api\LicenseManager;
use WC_Coupon;
use WP_Error;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class CouponHandler
 *
 * Comprehensive coupon management system that handles coupon application,
 * auto-generation (Unlimited plan only), validation, security checks,
 * and audit logging for the AI chatbot system.
 *
 * @since 1.0.0
 */
class CouponHandler
{
    use Singleton;

    /**
     * Coupon type constants
     *
     * @since 1.0.0
     */
    const COUPON_TYPE_FIXED_CART = 'fixed_cart';
    const COUPON_TYPE_PERCENT = 'percent';
    const COUPON_TYPE_FIXED_PRODUCT = 'fixed_product';
    const COUPON_TYPE_PERCENT_PRODUCT = 'percent_product';

    /**
     * Rate limiting constants
     *
     * @since 1.0.0
     */
    const MAX_COUPON_ATTEMPTS_PER_HOUR = 5;
    const MAX_COUPON_GENERATION_PER_DAY = 3; // Unlimited plan only
    const RATE_LIMIT_TRANSIENT_PREFIX = 'woo_ai_coupon_rate_limit_';
    const GENERATION_LIMIT_TRANSIENT_PREFIX = 'woo_ai_coupon_gen_limit_';

    /**
     * Security constants
     *
     * @since 1.0.0
     */
    const MIN_CART_VALUE_FOR_AUTO_GENERATION = 50; // Minimum cart value for auto-generation
    const MAX_AUTO_COUPON_VALUE = 50; // Maximum auto-generated coupon value
    const AUTO_COUPON_PREFIX = 'AI_'; // Prefix for auto-generated coupons

    /**
     * Action constants for audit logging
     *
     * @since 1.0.0
     */
    const ACTION_APPLY_COUPON = 'apply_coupon';
    const ACTION_GENERATE_COUPON = 'generate_coupon';
    const ACTION_VALIDATE_COUPON = 'validate_coupon';
    const ACTION_RATE_LIMIT_EXCEEDED = 'rate_limit_exceeded';
    const ACTION_FRAUD_DETECTED = 'fraud_detected';

    /**
     * License manager instance
     *
     * @since 1.0.0
     * @var LicenseManager|null
     */
    private ?LicenseManager $licenseManager = null;

    /**
     * Database table for agent actions
     *
     * @since 1.0.0
     * @var string
     */
    private string $agentActionsTable;

    /**
     * Constructor
     *
     * Initializes the coupon handler with license manager integration
     * and sets up database table references.
     *
     * @since 1.0.0
     */
    private function __construct()
    {
        global $wpdb;

        $this->licenseManager = LicenseManager::getInstance();
        $this->agentActionsTable = $wpdb->prefix . 'woo_ai_agent_actions';
        $this->setupHooks();

        Utils::logDebug('CouponHandler initialized');
    }

    /**
     * Setup WordPress hooks
     *
     * @since 1.0.0
     * @return void
     */
    private function setupHooks(): void
    {
        // WooCommerce coupon hooks
        add_action('woocommerce_applied_coupon', [$this, 'handleCouponApplied']);
        add_action('woocommerce_removed_coupon', [$this, 'handleCouponRemoved']);

        // Security hooks
        add_action('woo_ai_assistant_coupon_fraud_detected', [$this, 'handleFraudDetected']);

        Utils::logDebug('CouponHandler hooks registered');
    }

    /**
     * Apply existing WooCommerce coupon to cart
     *
     * @since 1.0.0
     * @param string $couponCode Coupon code to apply
     * @param int|null $userId User ID (null for current user)
     * @return array Application result with success status and message
     *
     * @example
     * ```php
     * $couponHandler = CouponHandler::getInstance();
     * $result = $couponHandler->applyCoupon('SAVE10', 123);
     * if ($result['success']) {
     *     echo 'Coupon applied: ' . $result['message'];
     * }
     * ```
     */
    public function applyCoupon(string $couponCode, ?int $userId = null): array
    {
        try {
            // Sanitize coupon code
            $couponCode = sanitize_text_field(strtoupper(trim($couponCode)));

            if (empty($couponCode)) {
                return $this->formatErrorResponse('Coupon code cannot be empty');
            }

            // Get user ID
            $userId = $userId ?? get_current_user_id();

            // Check rate limits
            if (!$this->checkRateLimits($userId)) {
                $this->logCouponUsage($couponCode, $userId, self::ACTION_RATE_LIMIT_EXCEEDED);
                return $this->formatErrorResponse('Too many coupon attempts. Please wait before trying again.');
            }

            // Validate coupon eligibility
            $eligibilityCheck = $this->validateCouponEligibility($couponCode, $userId);
            if (!$eligibilityCheck['eligible']) {
                return $this->formatErrorResponse($eligibilityCheck['message']);
            }

            // Check if WooCommerce is active
            if (!class_exists('WC_Cart') || !WC()->cart) {
                return $this->formatErrorResponse('Shopping cart is not available');
            }

            // Check if coupon is already applied
            if (WC()->cart->has_discount($couponCode)) {
                return $this->formatErrorResponse('Coupon is already applied');
            }

            // Apply coupon to cart
            $applied = WC()->cart->apply_coupon($couponCode);

            if ($applied) {
                // Log successful application
                $this->logCouponUsage($couponCode, $userId, self::ACTION_APPLY_COUPON);

                // Get coupon info for response
                $couponInfo = $this->getCouponInfo($couponCode);

                return [
                    'success' => true,
                    'message' => sprintf('Coupon "%s" applied successfully', $couponCode),
                    'coupon_info' => $couponInfo,
                    'discount_amount' => $this->calculateDiscountAmount($couponCode)
                ];
            } else {
                // Get WooCommerce error messages
                $notices = wc_get_notices('error');
                $errorMessage = !empty($notices) ? $notices[0]['notice'] : 'Failed to apply coupon';
                wc_clear_notices();

                return $this->formatErrorResponse($errorMessage);
            }
        } catch (\Exception $e) {
            Utils::logError('Error applying coupon: ' . $e->getMessage(), [
                'coupon_code' => $couponCode,
                'user_id' => $userId ?? 0
            ]);

            return $this->formatErrorResponse('An error occurred while applying the coupon');
        }
    }

    /**
     * Auto-generate new coupon (Unlimited plan only)
     *
     * @since 1.0.0
     * @param string $type Coupon type (percent, fixed_cart, etc.)
     * @param float $value Coupon value
     * @param int|null $userId User ID (null for current user)
     * @return array Generation result with success status and coupon details
     *
     * @example
     * ```php
     * $couponHandler = CouponHandler::getInstance();
     * $result = $couponHandler->generateCoupon('percent', 10, 123);
     * if ($result['success']) {
     *     echo 'Generated coupon: ' . $result['coupon_code'];
     * }
     * ```
     */
    public function generateCoupon(string $type, float $value, ?int $userId = null): array
    {
        try {
            $userId = $userId ?? get_current_user_id();

            // Check if feature is enabled (Unlimited plan only)
            if (!$this->licenseManager->isFeatureEnabled(LicenseManager::FEATURE_AUTO_COUPON)) {
                return $this->formatErrorResponse('Auto-coupon generation is only available in the Unlimited plan');
            }

            // Check generation rate limits
            if (!$this->checkGenerationLimits($userId)) {
                $this->logCouponUsage('AUTO_GENERATION', $userId, self::ACTION_RATE_LIMIT_EXCEEDED);
                return $this->formatErrorResponse('Daily coupon generation limit exceeded');
            }

            // Validate coupon parameters
            $validationResult = $this->validateCouponGeneration($type, $value, $userId);
            if (!$validationResult['valid']) {
                return $this->formatErrorResponse($validationResult['message']);
            }

            // Check for fraud patterns
            if ($this->detectFraudulentActivity($userId)) {
                $this->logCouponUsage('FRAUD_DETECTED', $userId, self::ACTION_FRAUD_DETECTED);
                return $this->formatErrorResponse('Suspicious activity detected. Contact support if you believe this is an error.');
            }

            // Generate unique coupon code
            $couponCode = $this->generateUniqueCouponCode();

            // Create WooCommerce coupon
            $coupon = $this->createWooCommerceCoupon($couponCode, $type, $value, $userId);

            if (is_wp_error($coupon)) {
                return $this->formatErrorResponse('Failed to create coupon: ' . $coupon->get_error_message());
            }

            // Log successful generation
            $this->logCouponUsage($couponCode, $userId, self::ACTION_GENERATE_COUPON, [
                'type' => $type,
                'value' => $value,
                'coupon_id' => $coupon->get_id()
            ]);

            // Update generation limits
            $this->updateGenerationLimits($userId);

            return [
                'success' => true,
                'message' => 'Coupon generated successfully',
                'coupon_code' => $couponCode,
                'coupon_info' => $this->getCouponInfo($couponCode),
                'expires_at' => $coupon->get_date_expires() ? $coupon->get_date_expires()->format('Y-m-d H:i:s') : null
            ];
        } catch (\Exception $e) {
            Utils::logError('Error generating coupon: ' . $e->getMessage(), [
                'type' => $type,
                'value' => $value,
                'user_id' => $userId ?? 0
            ]);

            return $this->formatErrorResponse('An error occurred while generating the coupon');
        }
    }

    /**
     * Validate coupon eligibility and restrictions
     *
     * @since 1.0.0
     * @param string $couponCode Coupon code to validate
     * @param int|null $userId User ID (null for current user)
     * @return array Validation result with eligibility status and message
     *
     * @example
     * ```php
     * $couponHandler = CouponHandler::getInstance();
     * $result = $couponHandler->validateCouponEligibility('SAVE10', 123);
     * if ($result['eligible']) {
     *     // Apply coupon
     * }
     * ```
     */
    public function validateCouponEligibility(string $couponCode, ?int $userId = null): array
    {
        try {
            $couponCode = sanitize_text_field(strtoupper(trim($couponCode)));
            $userId = $userId ?? get_current_user_id();

            // Log validation attempt
            $this->logCouponUsage($couponCode, $userId, self::ACTION_VALIDATE_COUPON);

            // Check if coupon exists
            $coupon = new WC_Coupon($couponCode);
            if (!$coupon->get_id()) {
                return [
                    'eligible' => false,
                    'message' => 'Coupon does not exist'
                ];
            }

            // Check if coupon is valid
            $validationErrors = [];

            // Check if coupon is enabled
            if ($coupon->get_status() !== 'publish') {
                $validationErrors[] = 'Coupon is not active';
            }

            // Check expiry date
            if ($coupon->get_date_expires() && $coupon->get_date_expires()->getTimestamp() < time()) {
                $validationErrors[] = 'Coupon has expired';
            }

            // Check usage limits
            $usageLimit = $coupon->get_usage_limit();
            if ($usageLimit > 0 && $coupon->get_usage_count() >= $usageLimit) {
                $validationErrors[] = 'Coupon usage limit reached';
            }

            // Check per-user usage limits
            $userUsageLimit = $coupon->get_usage_limit_per_user();
            if ($userUsageLimit > 0 && $userId > 0) {
                global $wpdb;
                $userUsageCount = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}wc_order_stats os 
                     JOIN {$wpdb->prefix}woocommerce_order_items oi ON os.order_id = oi.order_id 
                     WHERE oi.order_item_type = 'coupon' 
                     AND oi.order_item_name = %s 
                     AND os.customer_id = %d",
                    $couponCode,
                    $userId
                ));

                if ($userUsageCount >= $userUsageLimit) {
                    $validationErrors[] = 'You have reached the usage limit for this coupon';
                }
            }

            // Check minimum cart amount
            $minimumAmount = $coupon->get_minimum_amount();
            if ($minimumAmount > 0 && WC()->cart) {
                $cartTotal = WC()->cart->get_subtotal();
                if ($cartTotal < $minimumAmount) {
                    $validationErrors[] = sprintf(
                        'Minimum cart amount of %s required',
                        wc_price($minimumAmount)
                    );
                }
            }

            // Check maximum cart amount
            $maximumAmount = $coupon->get_maximum_amount();
            if ($maximumAmount > 0 && WC()->cart) {
                $cartTotal = WC()->cart->get_subtotal();
                if ($cartTotal > $maximumAmount) {
                    $validationErrors[] = sprintf(
                        'Maximum cart amount of %s exceeded',
                        wc_price($maximumAmount)
                    );
                }
            }

            // Check product/category restrictions
            if (WC()->cart) {
                $productRestrictionResult = $this->checkProductRestrictions($coupon);
                if (!$productRestrictionResult['valid']) {
                    $validationErrors[] = $productRestrictionResult['message'];
                }
            }

            // Return validation result
            if (empty($validationErrors)) {
                return [
                    'eligible' => true,
                    'message' => 'Coupon is valid and can be applied'
                ];
            } else {
                return [
                    'eligible' => false,
                    'message' => implode('. ', $validationErrors)
                ];
            }
        } catch (\Exception $e) {
            Utils::logError('Error validating coupon eligibility: ' . $e->getMessage(), [
                'coupon_code' => $couponCode,
                'user_id' => $userId ?? 0
            ]);

            return [
                'eligible' => false,
                'message' => 'Error validating coupon'
            ];
        }
    }

    /**
     * Get AI-suggested coupons based on cart total and user context
     *
     * @since 1.0.0
     * @param float $cartTotal Cart total amount
     * @param int|null $userId User ID (null for current user)
     * @return array Array of suggested coupon codes with details
     *
     * @example
     * ```php
     * $couponHandler = CouponHandler::getInstance();
     * $suggestions = $couponHandler->getCouponSuggestions(100.00, 123);
     * foreach ($suggestions as $suggestion) {
     *     echo 'Try coupon: ' . $suggestion['code'];
     * }
     * ```
     */
    public function getCouponSuggestions(float $cartTotal, ?int $userId = null): array
    {
        try {
            $userId = $userId ?? get_current_user_id();
            $suggestions = [];

            // Get all active coupons
            $coupons = get_posts([
                'post_type' => 'shop_coupon',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'meta_query' => [
                    'relation' => 'OR',
                    [
                        'key' => 'date_expires',
                        'value' => current_time('timestamp'),
                        'compare' => '>',
                        'type' => 'NUMERIC'
                    ],
                    [
                        'key' => 'date_expires',
                        'compare' => 'NOT EXISTS'
                    ]
                ]
            ]);

            foreach ($coupons as $couponPost) {
                $coupon = new WC_Coupon($couponPost->post_title);

                // Skip if not eligible
                $eligibility = $this->validateCouponEligibility($coupon->get_code(), $userId);
                if (!$eligibility['eligible']) {
                    continue;
                }

                // Check if coupon makes sense for current cart
                $minimumAmount = $coupon->get_minimum_amount();
                if ($minimumAmount > 0 && $cartTotal < $minimumAmount) {
                    continue;
                }

                // Calculate potential savings
                $potentialSavings = $this->calculatePotentialSavings($coupon, $cartTotal);
                if ($potentialSavings <= 0) {
                    continue;
                }

                $suggestions[] = [
                    'code' => $coupon->get_code(),
                    'description' => $coupon->get_description(),
                    'type' => $coupon->get_discount_type(),
                    'amount' => $coupon->get_amount(),
                    'potential_savings' => $potentialSavings,
                    'minimum_amount' => $minimumAmount,
                    'expires_at' => $coupon->get_date_expires() ? $coupon->get_date_expires()->format('Y-m-d') : null
                ];
            }

            // Sort by potential savings (highest first)
            usort($suggestions, function ($a, $b) {
                return $b['potential_savings'] <=> $a['potential_savings'];
            });

            // Limit to top 5 suggestions
            $suggestions = array_slice($suggestions, 0, 5);

            Utils::logDebug('Generated coupon suggestions', [
                'cart_total' => $cartTotal,
                'suggestions_count' => count($suggestions),
                'user_id' => $userId
            ]);

            return $suggestions;
        } catch (\Exception $e) {
            Utils::logError('Error getting coupon suggestions: ' . $e->getMessage(), [
                'cart_total' => $cartTotal,
                'user_id' => $userId ?? 0
            ]);

            return [];
        }
    }

    /**
     * Log coupon usage for audit tracking
     *
     * @since 1.0.0
     * @param string $couponCode Coupon code
     * @param int $userId User ID
     * @param string $action Action type
     * @param array $metadata Additional metadata
     * @return bool True if logged successfully
     */
    public function logCouponUsage(string $couponCode, int $userId, string $action, array $metadata = []): bool
    {
        try {
            global $wpdb;

            $logData = [
                'action_type' => $action,
                'entity_id' => $userId,
                'entity_type' => 'coupon',
                'metadata' => wp_json_encode(array_merge([
                    'coupon_code' => $couponCode,
                    'user_ip' => $this->getUserIP(),
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'timestamp' => current_time('mysql')
                ], $metadata)),
                'created_at' => current_time('mysql')
            ];

            $inserted = $wpdb->insert(
                $this->agentActionsTable,
                $logData,
                ['%s', '%d', '%s', '%s', '%s']
            );

            if ($inserted === false) {
                Utils::logError('Failed to log coupon usage to database', [
                    'coupon_code' => $couponCode,
                    'user_id' => $userId,
                    'action' => $action
                ]);
                return false;
            }

            Utils::logDebug('Coupon usage logged', [
                'coupon_code' => $couponCode,
                'user_id' => $userId,
                'action' => $action
            ]);

            return true;
        } catch (\Exception $e) {
            Utils::logError('Error logging coupon usage: ' . $e->getMessage(), [
                'coupon_code' => $couponCode,
                'user_id' => $userId,
                'action' => $action
            ]);

            return false;
        }
    }

    /**
     * Check rate limits to prevent abuse
     *
     * @since 1.0.0
     * @param int $userId User ID
     * @return bool True if within limits
     */
    public function checkRateLimits(int $userId): bool
    {
        $userIP = $this->getUserIP();
        $transientKey = self::RATE_LIMIT_TRANSIENT_PREFIX . $userId . '_' . md5($userIP);

        $attempts = get_transient($transientKey) ?: 0;

        if ($attempts >= self::MAX_COUPON_ATTEMPTS_PER_HOUR) {
            Utils::logDebug('Rate limit exceeded for coupon attempts', [
                'user_id' => $userId,
                'user_ip' => $userIP,
                'attempts' => $attempts
            ]);
            return false;
        }

        // Increment attempt counter
        set_transient($transientKey, $attempts + 1, HOUR_IN_SECONDS);

        return true;
    }

    /**
     * Get detailed coupon information
     *
     * @since 1.0.0
     * @param string $couponCode Coupon code
     * @return array Coupon details or empty array if not found
     *
     * @example
     * ```php
     * $couponHandler = CouponHandler::getInstance();
     * $info = $couponHandler->getCouponInfo('SAVE10');
     * echo 'Discount: ' . $info['discount_type'] . ' ' . $info['amount'];
     * ```
     */
    public function getCouponInfo(string $couponCode): array
    {
        try {
            $couponCode = sanitize_text_field(strtoupper(trim($couponCode)));
            $coupon = new WC_Coupon($couponCode);

            if (!$coupon->get_id()) {
                return [];
            }

            return [
                'id' => $coupon->get_id(),
                'code' => $coupon->get_code(),
                'amount' => $coupon->get_amount(),
                'discount_type' => $coupon->get_discount_type(),
                'description' => $coupon->get_description(),
                'expires_at' => $coupon->get_date_expires() ? $coupon->get_date_expires()->format('Y-m-d H:i:s') : null,
                'minimum_amount' => $coupon->get_minimum_amount(),
                'maximum_amount' => $coupon->get_maximum_amount(),
                'usage_count' => $coupon->get_usage_count(),
                'usage_limit' => $coupon->get_usage_limit(),
                'usage_limit_per_user' => $coupon->get_usage_limit_per_user(),
                'individual_use' => $coupon->get_individual_use(),
                'product_ids' => $coupon->get_product_ids(),
                'excluded_product_ids' => $coupon->get_excluded_product_ids(),
                'product_categories' => $coupon->get_product_categories(),
                'excluded_product_categories' => $coupon->get_excluded_product_categories()
            ];
        } catch (\Exception $e) {
            Utils::logError('Error getting coupon info: ' . $e->getMessage(), [
                'coupon_code' => $couponCode
            ]);

            return [];
        }
    }

    /**
     * Check generation rate limits (Unlimited plan only)
     *
     * @since 1.0.0
     * @param int $userId User ID
     * @return bool True if within limits
     */
    private function checkGenerationLimits(int $userId): bool
    {
        $transientKey = self::GENERATION_LIMIT_TRANSIENT_PREFIX . $userId;
        $generationsToday = get_transient($transientKey) ?: 0;

        return $generationsToday < self::MAX_COUPON_GENERATION_PER_DAY;
    }

    /**
     * Update generation limits counter
     *
     * @since 1.0.0
     * @param int $userId User ID
     * @return void
     */
    private function updateGenerationLimits(int $userId): void
    {
        $transientKey = self::GENERATION_LIMIT_TRANSIENT_PREFIX . $userId;
        $generationsToday = get_transient($transientKey) ?: 0;

        set_transient($transientKey, $generationsToday + 1, DAY_IN_SECONDS);
    }

    /**
     * Validate coupon generation parameters
     *
     * @since 1.0.0
     * @param string $type Coupon type
     * @param float $value Coupon value
     * @param int $userId User ID
     * @return array Validation result
     */
    private function validateCouponGeneration(string $type, float $value, int $userId): array
    {
        // Validate coupon type
        $validTypes = [
            self::COUPON_TYPE_FIXED_CART,
            self::COUPON_TYPE_PERCENT,
            self::COUPON_TYPE_FIXED_PRODUCT,
            self::COUPON_TYPE_PERCENT_PRODUCT
        ];

        if (!in_array($type, $validTypes)) {
            return [
                'valid' => false,
                'message' => 'Invalid coupon type'
            ];
        }

        // Validate coupon value
        if ($value <= 0) {
            return [
                'valid' => false,
                'message' => 'Coupon value must be greater than zero'
            ];
        }

        // Check maximum value limits
        if ($value > self::MAX_AUTO_COUPON_VALUE) {
            return [
                'valid' => false,
                'message' => sprintf('Maximum coupon value is %s', self::MAX_AUTO_COUPON_VALUE)
            ];
        }

        // For percentage coupons, limit to reasonable percentages
        if (in_array($type, [self::COUPON_TYPE_PERCENT, self::COUPON_TYPE_PERCENT_PRODUCT]) && $value > 50) {
            return [
                'valid' => false,
                'message' => 'Percentage coupons cannot exceed 50%'
            ];
        }

        // Check minimum cart value for auto-generation
        if (WC()->cart) {
            $cartTotal = WC()->cart->get_subtotal();
            if ($cartTotal < self::MIN_CART_VALUE_FOR_AUTO_GENERATION) {
                return [
                    'valid' => false,
                    'message' => sprintf(
                        'Minimum cart value of %s required for coupon generation',
                        wc_price(self::MIN_CART_VALUE_FOR_AUTO_GENERATION)
                    )
                ];
            }
        }

        return [
            'valid' => true,
            'message' => 'Validation passed'
        ];
    }

    /**
     * Detect fraudulent coupon activity
     *
     * @since 1.0.0
     * @param int $userId User ID
     * @return bool True if fraud detected
     */
    private function detectFraudulentActivity(int $userId): bool
    {
        global $wpdb;

        // Check for suspicious patterns in the last 24 hours
        $last24Hours = date('Y-m-d H:i:s', strtotime('-24 hours'));

        // Check for excessive generation attempts
        $recentGenerations = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->agentActionsTable} 
             WHERE entity_id = %d 
             AND action_type = %s 
             AND created_at > %s",
            $userId,
            self::ACTION_GENERATE_COUPON,
            $last24Hours
        ));

        if ($recentGenerations >= 5) { // More than 5 generations in 24 hours
            return true;
        }

        // Check for multiple different IP addresses
        $userIP = $this->getUserIP();
        $recentIPs = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.user_ip')) as ip 
             FROM {$this->agentActionsTable} 
             WHERE entity_id = %d 
             AND created_at > %s",
            $userId,
            $last24Hours
        ));

        if (count($recentIPs) > 3) { // More than 3 different IPs in 24 hours
            return true;
        }

        return false;
    }

    /**
     * Generate unique coupon code
     *
     * @since 1.0.0
     * @return string Unique coupon code
     */
    private function generateUniqueCouponCode(): string
    {
        $attempts = 0;
        $maxAttempts = 10;

        do {
            $randomString = strtoupper(wp_generate_password(8, false));
            $couponCode = self::AUTO_COUPON_PREFIX . $randomString;

            // Check if coupon code already exists
            $existingCoupon = new WC_Coupon($couponCode);
            $exists = $existingCoupon->get_id() > 0;

            $attempts++;
        } while ($exists && $attempts < $maxAttempts);

        if ($exists) {
            // Fallback with timestamp
            $couponCode = self::AUTO_COUPON_PREFIX . time() . wp_generate_password(4, false);
        }

        return $couponCode;
    }

    /**
     * Create WooCommerce coupon
     *
     * @since 1.0.0
     * @param string $couponCode Coupon code
     * @param string $type Coupon type
     * @param float $value Coupon value
     * @param int $userId User ID
     * @return WC_Coupon|WP_Error Created coupon or error
     */
    private function createWooCommerceCoupon(string $couponCode, string $type, float $value, int $userId): WC_Coupon|WP_Error
    {
        try {
            // Create coupon post
            $couponId = wp_insert_post([
                'post_title' => $couponCode,
                'post_content' => sprintf('Auto-generated coupon for user %d', $userId),
                'post_status' => 'publish',
                'post_type' => 'shop_coupon',
                'post_author' => $userId
            ]);

            if (is_wp_error($couponId)) {
                return $couponId;
            }

            // Create coupon object and set properties
            $coupon = new WC_Coupon($couponId);
            $coupon->set_code($couponCode);
            $coupon->set_discount_type($type);
            $coupon->set_amount($value);

            // Set expiration to 30 days from now
            $expiryDate = new \DateTime('+30 days');
            $coupon->set_date_expires($expiryDate);

            // Set usage limits
            $coupon->set_usage_limit(1); // Single use
            $coupon->set_usage_limit_per_user(1); // Once per user
            $coupon->set_individual_use(true); // Cannot be combined

            // Set minimum amount if it's a cart-based coupon
            if (in_array($type, [self::COUPON_TYPE_FIXED_CART, self::COUPON_TYPE_PERCENT])) {
                $coupon->set_minimum_amount(self::MIN_CART_VALUE_FOR_AUTO_GENERATION);
            }

            // Save coupon
            $coupon->save();

            // Add meta to identify as auto-generated
            update_post_meta($couponId, '_woo_ai_auto_generated', true);
            update_post_meta($couponId, '_woo_ai_generated_by_user', $userId);
            update_post_meta($couponId, '_woo_ai_generated_at', current_time('mysql'));

            return $coupon;
        } catch (\Exception $e) {
            return new WP_Error('coupon_creation_failed', $e->getMessage());
        }
    }

    /**
     * Check product and category restrictions for coupon
     *
     * @since 1.0.0
     * @param WC_Coupon $coupon Coupon object
     * @return array Validation result
     */
    private function checkProductRestrictions(WC_Coupon $coupon): array
    {
        if (!WC()->cart) {
            return ['valid' => true, 'message' => 'No cart available'];
        }

        $productIds = $coupon->get_product_ids();
        $excludedProductIds = $coupon->get_excluded_product_ids();
        $categoryIds = $coupon->get_product_categories();
        $excludedCategoryIds = $coupon->get_excluded_product_categories();

        // If no restrictions, allow all products
        if (
            empty($productIds) && empty($excludedProductIds) &&
            empty($categoryIds) && empty($excludedCategoryIds)
        ) {
            return ['valid' => true, 'message' => 'No restrictions'];
        }

        $cartProducts = [];
        foreach (WC()->cart->get_cart() as $cartItem) {
            $product = $cartItem['data'];
            $cartProducts[] = [
                'id' => $product->get_id(),
                'categories' => wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'ids'])
            ];
        }

        // Check for excluded products
        foreach ($cartProducts as $cartProduct) {
            if (in_array($cartProduct['id'], $excludedProductIds)) {
                return [
                    'valid' => false,
                    'message' => 'Cart contains excluded products'
                ];
            }

            // Check for excluded categories
            $hasExcludedCategory = array_intersect($cartProduct['categories'], $excludedCategoryIds);
            if (!empty($hasExcludedCategory)) {
                return [
                    'valid' => false,
                    'message' => 'Cart contains products from excluded categories'
                ];
            }
        }

        // Check for required products/categories
        if (!empty($productIds) || !empty($categoryIds)) {
            $hasValidProduct = false;

            foreach ($cartProducts as $cartProduct) {
                // Check if product is in allowed list
                if (!empty($productIds) && in_array($cartProduct['id'], $productIds)) {
                    $hasValidProduct = true;
                    break;
                }

                // Check if product is in allowed categories
                if (!empty($categoryIds)) {
                    $hasAllowedCategory = array_intersect($cartProduct['categories'], $categoryIds);
                    if (!empty($hasAllowedCategory)) {
                        $hasValidProduct = true;
                        break;
                    }
                }
            }

            if (!$hasValidProduct) {
                return [
                    'valid' => false,
                    'message' => 'Cart does not contain required products or categories'
                ];
            }
        }

        return ['valid' => true, 'message' => 'Product restrictions satisfied'];
    }

    /**
     * Calculate actual discount amount for applied coupon
     *
     * @since 1.0.0
     * @param string $couponCode Coupon code
     * @return float Discount amount
     */
    private function calculateDiscountAmount(string $couponCode): float
    {
        if (!WC()->cart) {
            return 0.0;
        }

        $coupon = new WC_Coupon($couponCode);
        if (!$coupon->get_id()) {
            return 0.0;
        }

        $cartTotal = WC()->cart->get_subtotal();
        $discountAmount = 0.0;

        switch ($coupon->get_discount_type()) {
            case 'fixed_cart':
                $discountAmount = min($coupon->get_amount(), $cartTotal);
                break;

            case 'percent':
                $discountAmount = ($cartTotal * $coupon->get_amount()) / 100;
                break;

            case 'fixed_product':
            case 'percent_product':
                // This would require more complex calculation for specific products
                $discountAmount = $coupon->get_amount();
                break;
        }

        return round($discountAmount, 2);
    }

    /**
     * Calculate potential savings for coupon suggestion
     *
     * @since 1.0.0
     * @param WC_Coupon $coupon Coupon object
     * @param float $cartTotal Cart total
     * @return float Potential savings amount
     */
    private function calculatePotentialSavings(WC_Coupon $coupon, float $cartTotal): float
    {
        switch ($coupon->get_discount_type()) {
            case 'fixed_cart':
                return min($coupon->get_amount(), $cartTotal);

            case 'percent':
                return ($cartTotal * $coupon->get_amount()) / 100;

            case 'fixed_product':
                return $coupon->get_amount(); // Simplified

            case 'percent_product':
                return ($cartTotal * $coupon->get_amount()) / 100; // Simplified

            default:
                return 0.0;
        }
    }

    /**
     * Get user IP address
     *
     * @since 1.0.0
     * @return string User IP address
     */
    private function getUserIP(): string
    {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];

        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Format error response
     *
     * @since 1.0.0
     * @param string $message Error message
     * @return array Error response array
     */
    private function formatErrorResponse(string $message): array
    {
        return [
            'success' => false,
            'message' => $message,
            'error' => true
        ];
    }

    /**
     * Handle coupon applied event
     *
     * @since 1.0.0
     * @param string $couponCode Applied coupon code
     * @return void
     */
    public function handleCouponApplied(string $couponCode): void
    {
        $userId = get_current_user_id();

        Utils::logDebug('Coupon applied via WooCommerce', [
            'coupon_code' => $couponCode,
            'user_id' => $userId
        ]);

        // Log the application if not already logged
        $this->logCouponUsage($couponCode, $userId, self::ACTION_APPLY_COUPON, [
            'applied_via' => 'woocommerce_hook'
        ]);
    }

    /**
     * Handle coupon removed event
     *
     * @since 1.0.0
     * @param string $couponCode Removed coupon code
     * @return void
     */
    public function handleCouponRemoved(string $couponCode): void
    {
        $userId = get_current_user_id();

        Utils::logDebug('Coupon removed via WooCommerce', [
            'coupon_code' => $couponCode,
            'user_id' => $userId
        ]);

        $this->logCouponUsage($couponCode, $userId, 'remove_coupon', [
            'removed_via' => 'woocommerce_hook'
        ]);
    }

    /**
     * Handle fraud detection
     *
     * @since 1.0.0
     * @param array $fraudData Fraud detection data
     * @return void
     */
    public function handleFraudDetected(array $fraudData): void
    {
        Utils::logError('Coupon fraud detected', $fraudData);

        // Additional fraud handling logic could be added here
        // such as temporarily blocking the user or sending alerts
    }
}
