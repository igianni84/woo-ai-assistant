<?php

/**
 * License Manager Class
 *
 * Handles comprehensive license management for all subscription plans (Free, Pro, Unlimited).
 * Manages license validation, feature enforcement, usage tracking, graceful degradation,
 * and communication with the intermediate server for secure license operations.
 *
 * @package WooAiAssistant
 * @subpackage Api
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Api;

use WooAiAssistant\Common\Traits\Singleton;
use WooAiAssistant\Common\Utils;
use WooAiAssistant\Api\IntermediateServerClient;
use WooAiAssistant\Common\ApiConfiguration;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LicenseManager
 *
 * Comprehensive license management system that handles subscription plans,
 * feature enforcement, usage tracking, and graceful degradation scenarios.
 * Integrates with IntermediateServerClient for secure server communication.
 *
 * @since 1.0.0
 */
class LicenseManager
{
    use Singleton;

    /**
     * License status constants
     *
     * @since 1.0.0
     */
    const STATUS_ACTIVE = 'active';
    const STATUS_EXPIRED = 'expired';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_CANCELED = 'canceled';
    const STATUS_GRACE_PERIOD = 'grace_period';
    const STATUS_UNKNOWN = 'unknown';

    /**
     * Plan type constants
     *
     * @since 1.0.0
     */
    const PLAN_FREE = 'free';
    const PLAN_PRO = 'pro';
    const PLAN_UNLIMITED = 'unlimited';
    const PLAN_MULTI_WEBSITE = 'multi_website';

    /**
     * Feature constants
     *
     * @since 1.0.0
     */
    const FEATURE_BASIC_CHAT = 'basic_chat';
    const FEATURE_PROACTIVE_TRIGGERS = 'proactive_triggers';
    const FEATURE_CUSTOM_MESSAGES = 'custom_messages';
    const FEATURE_ADD_TO_CART = 'add_to_cart';
    const FEATURE_AUTO_COUPON = 'auto_coupon';
    const FEATURE_UPSELL_CROSSSELL = 'upsell_crosssell';
    const FEATURE_WHITE_LABEL = 'white_label';
    const FEATURE_ADVANCED_AI = 'advanced_ai';
    const FEATURE_CHAT_RECOVERY = 'chat_recovery';

    /**
     * Current license data
     *
     * @since 1.0.0
     * @var array
     */
    private array $licenseData = [];

    /**
     * Current plan configuration
     *
     * @since 1.0.0
     * @var array
     */
    private array $planConfig = [];

    /**
     * Usage tracking data
     *
     * @since 1.0.0
     * @var array
     */
    private array $usageData = [];

    /**
     * Grace period settings
     *
     * @since 1.0.0
     * @var array
     */
    private array $gracePeriodConfig = [
        'duration_days' => 7,
        'warning_days' => 3,
        'features_limited' => true
    ];

    /**
     * Plan configurations
     *
     * @since 1.0.0
     * @var array
     */
    private array $planConfigurations = [
        self::PLAN_FREE => [
            'name' => 'Free Plan',
            'price' => 0,
            'conversations_per_month' => 30,
            'items_indexable' => 30,
            'ai_model' => 'gemini-2.5-flash',
            'branding' => 'Powered by Woo AI Assistant',
            'sla_delay_seconds' => 3,
            'features' => [
                self::FEATURE_BASIC_CHAT => true,
                self::FEATURE_PROACTIVE_TRIGGERS => false, // OOTB only, not configurable
                self::FEATURE_CUSTOM_MESSAGES => false,
                self::FEATURE_ADD_TO_CART => false,
                self::FEATURE_AUTO_COUPON => false,
                self::FEATURE_UPSELL_CROSSSELL => false,
                self::FEATURE_WHITE_LABEL => false,
                self::FEATURE_ADVANCED_AI => false,
                self::FEATURE_CHAT_RECOVERY => false
            ]
        ],
        self::PLAN_PRO => [
            'name' => 'Pro Plan',
            'price' => 19,
            'conversations_per_month' => 100,
            'items_indexable' => 100,
            'ai_model' => 'gemini-2.5-flash',
            'branding' => 'Powered by Woo AI Assistant',
            'sla_delay_seconds' => 0,
            'features' => [
                self::FEATURE_BASIC_CHAT => true,
                self::FEATURE_PROACTIVE_TRIGGERS => true, // Configurable
                self::FEATURE_CUSTOM_MESSAGES => true,
                self::FEATURE_ADD_TO_CART => false,
                self::FEATURE_AUTO_COUPON => false,
                self::FEATURE_UPSELL_CROSSSELL => false,
                self::FEATURE_WHITE_LABEL => false,
                self::FEATURE_ADVANCED_AI => false,
                self::FEATURE_CHAT_RECOVERY => false
            ]
        ],
        self::PLAN_UNLIMITED => [
            'name' => 'Unlimited Plan',
            'price' => 39,
            'conversations_per_month' => 1000,
            'items_indexable' => 2000,
            'ai_model' => 'gemini-2.5-pro',
            'branding' => 'white-label',
            'sla_delay_seconds' => 0,
            'features' => [
                self::FEATURE_BASIC_CHAT => true,
                self::FEATURE_PROACTIVE_TRIGGERS => true,
                self::FEATURE_CUSTOM_MESSAGES => true,
                self::FEATURE_ADD_TO_CART => true,
                self::FEATURE_AUTO_COUPON => true,
                self::FEATURE_UPSELL_CROSSSELL => true,
                self::FEATURE_WHITE_LABEL => true,
                self::FEATURE_ADVANCED_AI => true,
                self::FEATURE_CHAT_RECOVERY => true
            ]
        ]
    ];

    /**
     * Server client instance
     *
     * @since 1.0.0
     * @var IntermediateServerClient|null
     */
    private ?IntermediateServerClient $serverClient = null;

    /**
     * API configuration instance
     *
     * @since 1.0.0
     * @var ApiConfiguration|null
     */
    private ?ApiConfiguration $apiConfig = null;

    /**
     * Constructor
     *
     * Initializes the license manager with current license data and usage tracking.
     *
     * @since 1.0.0
     */
    private function __construct()
    {
        $this->serverClient = IntermediateServerClient::getInstance();
        // Lazy load ApiConfiguration to prevent circular dependency
        // $this->apiConfig = ApiConfiguration::getInstance();
        $this->loadLicenseData();
        $this->loadUsageData();
        $this->setupHooks();
        $this->initializeGracePeriodHandling();
    }

    /**
     * Setup WordPress hooks
     *
     * @since 1.0.0
     * @return void
     */
    private function setupHooks(): void
    {
        // License validation hooks
        add_action('woo_ai_assistant_validate_license', [$this, 'validateLicense']);
        add_action('woo_ai_assistant_license_check', [$this, 'performLicenseCheck']);

        // Usage tracking hooks
        add_action('woo_ai_assistant_conversation_started', [$this, 'trackConversationUsage']);
        add_action('woo_ai_assistant_content_indexed', [$this, 'trackIndexingUsage']);

        // Admin notices hooks
        add_action('admin_notices', [$this, 'displayLicenseNotices']);

        // Grace period handling
        add_action('woo_ai_assistant_grace_period_check', [$this, 'handleGracePeriod']);

        // Daily license validation
        add_action('woo_ai_assistant_daily_license_check', [$this, 'performDailyLicenseCheck']);

        Utils::logDebug('License Manager hooks registered');
    }

    /**
     * Load license data from WordPress options
     *
     * @since 1.0.0
     * @return void
     */
    private function loadLicenseData(): void
    {
        $this->licenseData = get_option('woo_ai_assistant_license_data', [
            'plan' => self::PLAN_FREE,
            'status' => self::STATUS_ACTIVE,
            'license_key' => '',
            'expires_at' => null,
            'last_validated' => null,
            'validation_errors' => [],
            'grace_period_started' => null
        ]);

        // Set plan configuration based on current plan
        $this->planConfig = $this->planConfigurations[$this->licenseData['plan']] ?? $this->planConfigurations[self::PLAN_FREE];

        Utils::logDebug('License data loaded', [
            'plan' => $this->licenseData['plan'],
            'status' => $this->licenseData['status']
        ]);
    }

    /**
     * Load usage data from WordPress options
     *
     * @since 1.0.0
     * @return void
     */
    private function loadUsageData(): void
    {
        $currentMonth = date('Y-m');

        $this->usageData = get_option('woo_ai_assistant_usage_data', [
            'current_month' => $currentMonth,
            'conversations_count' => 0,
            'items_indexed' => 0,
            'monthly_reset_date' => date('Y-m-01'),
            'last_updated' => current_time('mysql'),
            'daily_usage' => [],
            'feature_usage' => []
        ]);

        // Reset monthly counters if we're in a new month
        if ($this->usageData['current_month'] !== $currentMonth) {
            $this->resetMonthlyUsage();
        }

        Utils::logDebug('Usage data loaded', [
            'conversations' => $this->usageData['conversations_count'],
            'items_indexed' => $this->usageData['items_indexed']
        ]);
    }

    /**
     * Reset monthly usage counters
     *
     * @since 1.0.0
     * @return void
     */
    private function resetMonthlyUsage(): void
    {
        $currentMonth = date('Y-m');

        $this->usageData = array_merge($this->usageData, [
            'current_month' => $currentMonth,
            'conversations_count' => 0,
            'monthly_reset_date' => date('Y-m-01'),
            'last_updated' => current_time('mysql'),
            'daily_usage' => []
        ]);

        $this->saveUsageData();

        Utils::logDebug('Monthly usage reset for: ' . $currentMonth);

        /**
         * Monthly usage reset action
         *
         * @since 1.0.0
         * @param string $currentMonth The current month (Y-m format)
         */
        do_action('woo_ai_assistant_monthly_usage_reset', $currentMonth);
    }

    /**
     * Validate license with intermediate server
     *
     * @since 1.0.0
     * @param bool $forceValidation Force validation even if recently validated
     * @return array Validation result
     *
     * @example
     * ```php
     * $licenseManager = LicenseManager::getInstance();
     * $result = $licenseManager->validateLicense(true);
     * if ($result['valid']) {
     *     echo 'License is valid';
     * }
     * ```
     */
    public function validateLicense(bool $forceValidation = false): array
    {
        try {
            // Lazy load ApiConfiguration when needed
            if (!$this->apiConfig) {
                $this->apiConfig = ApiConfiguration::getInstance();
            }
            // Check if development mode bypass is enabled
            if ($this->apiConfig && $this->apiConfig->shouldBypassLicenseValidation()) {
                return $this->handleDevelopmentModeValidation();
            }

            // Skip validation for Free plan
            if ($this->licenseData['plan'] === self::PLAN_FREE) {
                return $this->handleFreePlanValidation();
            }

            // Check if validation is needed
            if (!$forceValidation && $this->isRecentlyValidated()) {
                return [
                    'valid' => $this->licenseData['status'] === self::STATUS_ACTIVE,
                    'status' => $this->licenseData['status'],
                    'message' => 'Using cached validation result',
                    'cached' => true
                ];
            }

            // Perform server validation
            $response = $this->serverClient->sendRequest('/license/validate', [
                'license_key' => $this->licenseData['license_key'],
                'domain' => $this->getSiteDomain(),
                'plugin_version' => WOO_AI_ASSISTANT_VERSION
            ], 'POST');

            return $this->processServerValidationResponse($response);
        } catch (\Exception $e) {
            Utils::logError('License validation error: ' . $e->getMessage());

            return [
                'valid' => false,
                'status' => self::STATUS_UNKNOWN,
                'message' => 'Validation error: ' . $e->getMessage(),
                'error' => true
            ];
        }
    }

    /**
     * Handle development mode license validation bypass
     *
     * @since 1.0.0
     * @return array Validation result for development mode
     */
    private function handleDevelopmentModeValidation(): array
    {
        // Set up development license data
        // Lazy load ApiConfiguration when needed
        if (!$this->apiConfig) {
            $this->apiConfig = ApiConfiguration::getInstance();
        }
        $developmentLicenseKey = $this->apiConfig->getDevelopmentLicenseKey();

        $this->licenseData['status'] = self::STATUS_ACTIVE;
        $this->licenseData['last_validated'] = current_time('mysql');
        $this->licenseData['license_key'] = $developmentLicenseKey;
        $this->licenseData['validation_errors'] = [];
        $this->licenseData['grace_period_started'] = null;

        // Use Unlimited plan features for development testing
        $this->licenseData['plan'] = self::PLAN_UNLIMITED;
        $this->planConfig = $this->planConfigurations[self::PLAN_UNLIMITED];

        $this->saveLicenseData();

        Utils::logDebug('Development mode license validation bypassed', [
            'plan' => $this->licenseData['plan'],
            'license_key' => $developmentLicenseKey
        ]);

        return [
            'valid' => true,
            'status' => self::STATUS_ACTIVE,
            'message' => 'Development mode - license validation bypassed',
            'plan' => self::PLAN_UNLIMITED,
            'development_mode' => true
        ];
    }

    /**
     * Handle Free plan validation
     *
     * @since 1.0.0
     * @return array Validation result
     */
    private function handleFreePlanValidation(): array
    {
        $this->licenseData['status'] = self::STATUS_ACTIVE;
        $this->licenseData['last_validated'] = current_time('mysql');
        $this->saveLicenseData();

        return [
            'valid' => true,
            'status' => self::STATUS_ACTIVE,
            'message' => 'Free plan is always valid',
            'plan' => self::PLAN_FREE
        ];
    }

    /**
     * Check if license was recently validated
     *
     * @since 1.0.0
     * @return bool True if recently validated
     */
    private function isRecentlyValidated(): bool
    {
        if (!$this->licenseData['last_validated']) {
            return false;
        }

        $lastValidated = strtotime($this->licenseData['last_validated']);
        $validationInterval = apply_filters('woo_ai_assistant_validation_interval', HOUR_IN_SECONDS);

        return (time() - $lastValidated) < $validationInterval;
    }

    /**
     * Process server validation response
     *
     * @since 1.0.0
     * @param array|\WP_Error $response Server response
     * @return array Processed validation result
     */
    private function processServerValidationResponse($response): array
    {
        if (is_wp_error($response)) {
            return $this->handleValidationError($response->get_error_message());
        }

        if (!isset($response['valid'])) {
            return $this->handleValidationError('Invalid server response format');
        }

        $this->licenseData['last_validated'] = current_time('mysql');

        if ($response['valid']) {
            $this->licenseData['status'] = self::STATUS_ACTIVE;
            $this->licenseData['validation_errors'] = [];
            $this->licenseData['grace_period_started'] = null;

            // Update plan information if provided
            if (isset($response['plan'])) {
                $this->updatePlanFromServer($response['plan']);
            }

            Utils::logDebug('License validation successful', [
                'plan' => $this->licenseData['plan'],
                'expires_at' => $response['expires_at'] ?? 'N/A'
            ]);
        } else {
            $this->handleInvalidLicense($response);
        }

        $this->saveLicenseData();

        return [
            'valid' => $response['valid'],
            'status' => $this->licenseData['status'],
            'message' => $response['message'] ?? 'License validated',
            'plan' => $this->licenseData['plan'],
            'expires_at' => $response['expires_at'] ?? null
        ];
    }

    /**
     * Handle validation error
     *
     * @since 1.0.0
     * @param string $errorMessage Error message
     * @return array Error result
     */
    private function handleValidationError(string $errorMessage): array
    {
        $this->licenseData['validation_errors'][] = [
            'message' => $errorMessage,
            'timestamp' => current_time('mysql')
        ];

        // Start grace period if not already started
        if (!$this->licenseData['grace_period_started'] && $this->licenseData['status'] === self::STATUS_ACTIVE) {
            $this->startGracePeriod();
        }

        $this->saveLicenseData();

        return [
            'valid' => false,
            'status' => $this->licenseData['status'],
            'message' => $errorMessage,
            'error' => true
        ];
    }

    /**
     * Handle invalid license
     *
     * @since 1.0.0
     * @param array $response Server response
     * @return void
     */
    private function handleInvalidLicense(array $response): void
    {
        $this->licenseData['status'] = $response['status'] ?? self::STATUS_EXPIRED;
        $this->licenseData['validation_errors'] = $response['errors'] ?? [];

        // Start grace period for expired licenses
        if ($this->licenseData['status'] === self::STATUS_EXPIRED && !$this->licenseData['grace_period_started']) {
            $this->startGracePeriod();
        }

        Utils::logDebug('License validation failed', [
            'status' => $this->licenseData['status'],
            'errors' => $this->licenseData['validation_errors']
        ]);
    }

    /**
     * Update plan configuration from server response
     *
     * @since 1.0.0
     * @param array $planData Plan data from server
     * @return void
     */
    private function updatePlanFromServer(array $planData): void
    {
        $newPlan = $planData['type'] ?? $this->licenseData['plan'];

        if (isset($this->planConfigurations[$newPlan])) {
            $oldPlan = $this->licenseData['plan'];
            $this->licenseData['plan'] = $newPlan;
            $this->planConfig = $this->planConfigurations[$newPlan];

            if ($oldPlan !== $newPlan) {
                Utils::logDebug("Plan updated from {$oldPlan} to {$newPlan}");

                /**
                 * Plan changed action
                 *
                 * @since 1.0.0
                 * @param string $newPlan New plan
                 * @param string $oldPlan Old plan
                 */
                do_action('woo_ai_assistant_plan_changed', $newPlan, $oldPlan);
            }
        }

        // Update expiration date if provided
        if (isset($planData['expires_at'])) {
            $this->licenseData['expires_at'] = $planData['expires_at'];
        }
    }

    /**
     * Start grace period
     *
     * @since 1.0.0
     * @return void
     */
    private function startGracePeriod(): void
    {
        $this->licenseData['grace_period_started'] = current_time('mysql');
        $this->licenseData['status'] = self::STATUS_GRACE_PERIOD;

        Utils::logDebug('Grace period started');

        /**
         * Grace period started action
         *
         * @since 1.0.0
         * @param array $licenseData Current license data
         */
        do_action('woo_ai_assistant_grace_period_started', $this->licenseData);
    }

    /**
     * Check if feature is enabled for current plan
     *
     * @since 1.0.0
     * @param string $feature Feature constant
     * @return bool True if feature is enabled
     *
     * @example
     * ```php
     * $licenseManager = LicenseManager::getInstance();
     * if ($licenseManager->isFeatureEnabled(LicenseManager::FEATURE_ADD_TO_CART)) {
     *     // Show add to cart functionality
     * }
     * ```
     */
    public function isFeatureEnabled(string $feature): bool
    {
        // Check grace period limitations
        if ($this->isInGracePeriod() && $this->gracePeriodConfig['features_limited']) {
            return $this->isFeatureAllowedInGracePeriod($feature);
        }

        // Check plan feature availability
        return $this->planConfig['features'][$feature] ?? false;
    }

    /**
     * Check if currently in grace period
     *
     * @since 1.0.0
     * @return bool True if in grace period
     */
    public function isInGracePeriod(): bool
    {
        return $this->licenseData['status'] === self::STATUS_GRACE_PERIOD;
    }

    /**
     * Check if feature is allowed during grace period
     *
     * @since 1.0.0
     * @param string $feature Feature constant
     * @return bool True if allowed in grace period
     */
    private function isFeatureAllowedInGracePeriod(string $feature): bool
    {
        // Only basic features allowed in grace period
        $allowedFeatures = [
            self::FEATURE_BASIC_CHAT,
            self::FEATURE_PROACTIVE_TRIGGERS
        ];

        return in_array($feature, $allowedFeatures);
    }

    /**
     * Check usage limit for current plan
     *
     * @since 1.0.0
     * @param string $type Usage type ('conversations' or 'items')
     * @param int $amount Amount to check (default 1)
     * @return bool True if within limits
     *
     * @example
     * ```php
     * $licenseManager = LicenseManager::getInstance();
     * if ($licenseManager->checkUsageLimit('conversations', 1)) {
     *     // Process conversation
     * } else {
     *     // Show usage limit message
     * }
     * ```
     */
    public function checkUsageLimit(string $type, int $amount = 1): bool
    {
        switch ($type) {
            case 'conversations':
                $current = $this->usageData['conversations_count'];
                $limit = $this->planConfig['conversations_per_month'];
                break;

            case 'items':
                $current = $this->usageData['items_indexed'];
                $limit = $this->planConfig['items_indexable'];
                break;

            default:
                return false;
        }

        return ($current + $amount) <= $limit;
    }

    /**
     * Track conversation usage
     *
     * @since 1.0.0
     * @param int $conversationId Conversation ID
     * @return bool True if tracked successfully
     */
    public function trackConversationUsage(int $conversationId): bool
    {
        if (!$this->checkUsageLimit('conversations', 1)) {
            Utils::logDebug('Conversation limit exceeded', [
                'current' => $this->usageData['conversations_count'],
                'limit' => $this->planConfig['conversations_per_month']
            ]);

            return false;
        }

        $this->usageData['conversations_count']++;
        $this->recordDailyUsage('conversations', 1);
        $this->saveUsageData();

        Utils::logDebug('Conversation usage tracked', [
            'conversation_id' => $conversationId,
            'total_count' => $this->usageData['conversations_count']
        ]);

        /**
         * Conversation usage tracked action
         *
         * @since 1.0.0
         * @param int $conversationId Conversation ID
         * @param array $usageData Current usage data
         */
        do_action('woo_ai_assistant_conversation_usage_tracked', $conversationId, $this->usageData);

        return true;
    }

    /**
     * Track indexing usage
     *
     * @since 1.0.0
     * @param string $contentType Content type
     * @param int $itemsCount Number of items indexed
     * @return bool True if tracked successfully
     */
    public function trackIndexingUsage(string $contentType, int $itemsCount = 1): bool
    {
        if (!$this->checkUsageLimit('items', $itemsCount)) {
            Utils::logDebug('Indexing limit exceeded', [
                'current' => $this->usageData['items_indexed'],
                'limit' => $this->planConfig['items_indexable'],
                'attempted_items' => $itemsCount
            ]);

            return false;
        }

        $this->usageData['items_indexed'] += $itemsCount;
        $this->recordDailyUsage('items_indexed', $itemsCount);
        $this->saveUsageData();

        Utils::logDebug('Indexing usage tracked', [
            'content_type' => $contentType,
            'items_count' => $itemsCount,
            'total_indexed' => $this->usageData['items_indexed']
        ]);

        /**
         * Indexing usage tracked action
         *
         * @since 1.0.0
         * @param string $contentType Content type
         * @param int $itemsCount Items count
         * @param array $usageData Current usage data
         */
        do_action('woo_ai_assistant_indexing_usage_tracked', $contentType, $itemsCount, $this->usageData);

        return true;
    }

    /**
     * Record daily usage for analytics
     *
     * @since 1.0.0
     * @param string $type Usage type
     * @param int $amount Amount
     * @return void
     */
    private function recordDailyUsage(string $type, int $amount): void
    {
        $today = date('Y-m-d');

        if (!isset($this->usageData['daily_usage'][$today])) {
            $this->usageData['daily_usage'][$today] = [];
        }

        if (!isset($this->usageData['daily_usage'][$today][$type])) {
            $this->usageData['daily_usage'][$today][$type] = 0;
        }

        $this->usageData['daily_usage'][$today][$type] += $amount;
    }

    /**
     * Get current usage statistics
     *
     * @since 1.0.0
     * @return array Usage statistics
     *
     * @example
     * ```php
     * $licenseManager = LicenseManager::getInstance();
     * $stats = $licenseManager->getUsageStatistics();
     * echo "Conversations used: {$stats['conversations']['used']} / {$stats['conversations']['limit']}";
     * ```
     */
    public function getUsageStatistics(): array
    {
        return [
            'conversations' => [
                'used' => $this->usageData['conversations_count'],
                'limit' => $this->planConfig['conversations_per_month'],
                'percentage' => $this->planConfig['conversations_per_month'] > 0
                    ? round(($this->usageData['conversations_count'] / $this->planConfig['conversations_per_month']) * 100, 2)
                    : 0
            ],
            'items_indexed' => [
                'used' => $this->usageData['items_indexed'],
                'limit' => $this->planConfig['items_indexable'],
                'percentage' => $this->planConfig['items_indexable'] > 0
                    ? round(($this->usageData['items_indexed'] / $this->planConfig['items_indexable']) * 100, 2)
                    : 0
            ],
            'current_month' => $this->usageData['current_month'],
            'reset_date' => $this->usageData['monthly_reset_date'],
            'daily_usage' => $this->usageData['daily_usage']
        ];
    }

    /**
     * Get current license status
     *
     * @since 1.0.0
     * @return array License status information
     *
     * @example
     * ```php
     * $licenseManager = LicenseManager::getInstance();
     * $status = $licenseManager->getLicenseStatus();
     * echo "Current plan: {$status['plan']}";
     * ```
     */
    public function getLicenseStatus(): array
    {
        return [
            'plan' => $this->licenseData['plan'],
            'plan_name' => $this->planConfig['name'],
            'status' => $this->licenseData['status'],
            'license_key' => $this->maskLicenseKey($this->licenseData['license_key']),
            'expires_at' => $this->licenseData['expires_at'],
            'last_validated' => $this->licenseData['last_validated'],
            'grace_period_started' => $this->licenseData['grace_period_started'],
            'features' => $this->planConfig['features'],
            'usage' => $this->getUsageStatistics(),
            'validation_errors' => $this->licenseData['validation_errors']
        ];
    }

    /**
     * Mask license key for display
     *
     * @since 1.0.0
     * @param string $licenseKey License key
     * @return string Masked license key
     */
    private function maskLicenseKey(string $licenseKey): string
    {
        if (empty($licenseKey)) {
            return '';
        }

        $length = strlen($licenseKey);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($licenseKey, 0, 4) . str_repeat('*', $length - 8) . substr($licenseKey, -4);
    }

    /**
     * Set license key and validate
     *
     * @since 1.0.0
     * @param string $licenseKey License key
     * @return array Validation result
     *
     * @example
     * ```php
     * $licenseManager = LicenseManager::getInstance();
     * $result = $licenseManager->setLicenseKey('your-license-key');
     * if ($result['valid']) {
     *     echo 'License activated successfully';
     * }
     * ```
     */
    public function setLicenseKey(string $licenseKey): array
    {
        $licenseKey = sanitize_text_field($licenseKey);

        if (empty($licenseKey)) {
            return [
                'valid' => false,
                'message' => 'License key cannot be empty'
            ];
        }

        // Lazy load ApiConfiguration when needed
        if (!$this->apiConfig) {
            $this->apiConfig = ApiConfiguration::getInstance();
        }
        // In development mode, accept any license key as valid
        if ($this->apiConfig && $this->apiConfig->shouldBypassLicenseValidation()) {
            $this->licenseData['license_key'] = $licenseKey;
            $this->saveLicenseData();

            Utils::logDebug('Development mode: License key accepted without validation', [
                'key_length' => strlen($licenseKey)
            ]);

            return $this->handleDevelopmentModeValidation();
        }

        $this->licenseData['license_key'] = $licenseKey;
        $this->saveLicenseData();

        // Validate the new license key
        $validationResult = $this->validateLicense(true);

        Utils::logDebug('License key set and validated', [
            'valid' => $validationResult['valid'],
            'plan' => $this->licenseData['plan']
        ]);

        return $validationResult;
    }

    /**
     * Clear license key and revert to Free plan
     *
     * @since 1.0.0
     * @return bool True if cleared successfully
     */
    public function clearLicenseKey(): bool
    {
        $this->licenseData = [
            'plan' => self::PLAN_FREE,
            'status' => self::STATUS_ACTIVE,
            'license_key' => '',
            'expires_at' => null,
            'last_validated' => current_time('mysql'),
            'validation_errors' => [],
            'grace_period_started' => null
        ];

        $this->planConfig = $this->planConfigurations[self::PLAN_FREE];
        $this->saveLicenseData();

        Utils::logDebug('License key cleared, reverted to Free plan');

        /**
         * License cleared action
         *
         * @since 1.0.0
         */
        do_action('woo_ai_assistant_license_cleared');

        return true;
    }

    /**
     * Display admin notices for license issues
     *
     * @since 1.0.0
     * @return void
     */
    public function displayLicenseNotices(): void
    {
        $currentScreen = get_current_screen();
        if (!$currentScreen || strpos($currentScreen->id, 'woo-ai-assistant') === false) {
            return;
        }

        // Grace period notice
        if ($this->isInGracePeriod()) {
            $this->displayGracePeriodNotice();
        }

        // Usage limit notices
        $this->displayUsageLimitNotices();

        // Validation error notices
        if (!empty($this->licenseData['validation_errors'])) {
            $this->displayValidationErrorNotices();
        }
    }

    /**
     * Display grace period notice
     *
     * @since 1.0.0
     * @return void
     */
    private function displayGracePeriodNotice(): void
    {
        $gracePeriodStarted = strtotime($this->licenseData['grace_period_started']);
        $gracePeriodEnds = $gracePeriodStarted + ($this->gracePeriodConfig['duration_days'] * DAY_IN_SECONDS);
        $daysRemaining = ceil(($gracePeriodEnds - time()) / DAY_IN_SECONDS);

        $message = sprintf(
            /* translators: %d: Days remaining in grace period */
            __('Your Woo AI Assistant license has expired. You have %d days remaining in your grace period. Please renew your license to continue using all features.', 'woo-ai-assistant'),
            max(0, $daysRemaining)
        );

        printf(
            '<div class="notice notice-warning is-dismissible"><p><strong>%s</strong></p></div>',
            esc_html($message)
        );
    }

    /**
     * Display usage limit notices
     *
     * @since 1.0.0
     * @return void
     */
    private function displayUsageLimitNotices(): void
    {
        $stats = $this->getUsageStatistics();

        // Conversations limit warning
        if ($stats['conversations']['percentage'] >= 90) {
            $message = sprintf(
                /* translators: 1: Used conversations, 2: Total conversations limit */
                __('You have used %1$d out of %2$d conversations this month. Consider upgrading your plan to continue chatbot service.', 'woo-ai-assistant'),
                $stats['conversations']['used'],
                $stats['conversations']['limit']
            );

            $noticeType = $stats['conversations']['percentage'] >= 100 ? 'error' : 'warning';

            printf(
                '<div class="notice notice-%s is-dismissible"><p><strong>%s</strong></p></div>',
                esc_attr($noticeType),
                esc_html($message)
            );
        }

        // Items indexed limit warning
        if ($stats['items_indexed']['percentage'] >= 90) {
            $message = sprintf(
                /* translators: 1: Used items, 2: Total items limit */
                __('You have indexed %1$d out of %2$d items. Consider upgrading your plan to index more content.', 'woo-ai-assistant'),
                $stats['items_indexed']['used'],
                $stats['items_indexed']['limit']
            );

            $noticeType = $stats['items_indexed']['percentage'] >= 100 ? 'error' : 'warning';

            printf(
                '<div class="notice notice-%s is-dismissible"><p><strong>%s</strong></p></div>',
                esc_attr($noticeType),
                esc_html($message)
            );
        }
    }

    /**
     * Display validation error notices
     *
     * @since 1.0.0
     * @return void
     */
    private function displayValidationErrorNotices(): void
    {
        $recentErrors = array_slice($this->licenseData['validation_errors'], -3);

        foreach ($recentErrors as $error) {
            $message = sprintf(
                /* translators: 1: Error message, 2: Timestamp */
                __('License validation error: %1$s (at %2$s)', 'woo-ai-assistant'),
                $error['message'],
                $error['timestamp']
            );

            printf(
                '<div class="notice notice-error is-dismissible"><p><strong>%s</strong></p></div>',
                esc_html($message)
            );
        }
    }

    /**
     * Perform daily license check
     *
     * @since 1.0.0
     * @return void
     */
    public function performDailyLicenseCheck(): void
    {
        Utils::logDebug('Performing daily license check');

        // Validate license
        $this->validateLicense(true);

        // Check grace period expiration
        $this->checkGracePeriodExpiration();

        // Clean up old usage data
        $this->cleanupOldUsageData();

        Utils::logDebug('Daily license check completed');
    }

    /**
     * Handle grace period expiration
     *
     * @since 1.0.0
     * @return void
     */
    public function handleGracePeriod(): void
    {
        if (!$this->isInGracePeriod()) {
            return;
        }

        $this->checkGracePeriodExpiration();
    }

    /**
     * Check if grace period has expired
     *
     * @since 1.0.0
     * @return void
     */
    private function checkGracePeriodExpiration(): void
    {
        if (!$this->isInGracePeriod()) {
            return;
        }

        $gracePeriodStarted = strtotime($this->licenseData['grace_period_started']);
        $gracePeriodEnds = $gracePeriodStarted + ($this->gracePeriodConfig['duration_days'] * DAY_IN_SECONDS);

        if (time() > $gracePeriodEnds) {
            $this->endGracePeriod();
        }
    }

    /**
     * End grace period and revert to Free plan
     *
     * @since 1.0.0
     * @return void
     */
    private function endGracePeriod(): void
    {
        Utils::logDebug('Grace period expired, reverting to Free plan');

        $oldPlan = $this->licenseData['plan'];

        $this->licenseData['plan'] = self::PLAN_FREE;
        $this->licenseData['status'] = self::STATUS_ACTIVE;
        $this->licenseData['grace_period_started'] = null;
        $this->planConfig = $this->planConfigurations[self::PLAN_FREE];

        $this->saveLicenseData();

        /**
         * Grace period expired action
         *
         * @since 1.0.0
         * @param string $oldPlan Previous plan
         */
        do_action('woo_ai_assistant_grace_period_expired', $oldPlan);
    }

    /**
     * Initialize grace period handling
     *
     * @since 1.0.0
     * @return void
     */
    private function initializeGracePeriandHandling(): void
    {
        // Schedule daily grace period check
        if (!wp_next_scheduled('woo_ai_assistant_grace_period_check')) {
            wp_schedule_event(time(), 'daily', 'woo_ai_assistant_grace_period_check');
        }

        // Schedule daily license check
        if (!wp_next_scheduled('woo_ai_assistant_daily_license_check')) {
            wp_schedule_event(time(), 'daily', 'woo_ai_assistant_daily_license_check');
        }
    }

    /**
     * Fix typo in method name - this is the correct version
     *
     * @since 1.0.0
     * @return void
     */
    private function initializeGracePeriodHandling(): void
    {
        $this->initializeGracePeriandHandling();
    }

    /**
     * Clean up old usage data
     *
     * @since 1.0.0
     * @return void
     */
    private function cleanupOldUsageData(): void
    {
        $cutoffDate = date('Y-m-d', strtotime('-3 months'));

        $cleaned = 0;
        foreach ($this->usageData['daily_usage'] as $date => $usage) {
            if ($date < $cutoffDate) {
                unset($this->usageData['daily_usage'][$date]);
                $cleaned++;
            }
        }

        if ($cleaned > 0) {
            $this->saveUsageData();
            Utils::logDebug("Cleaned up {$cleaned} old usage data entries");
        }
    }

    /**
     * Get site domain for license validation
     *
     * @since 1.0.0
     * @return string Site domain
     */
    private function getSiteDomain(): string
    {
        if (function_exists('get_site_url')) {
            $siteUrl = get_site_url();
            $parsedUrl = wp_parse_url($siteUrl);
            return $parsedUrl['host'] ?? 'unknown';
        }

        // Fallback for test environment
        return 'test-domain.com';
    }

    /**
     * Save license data to WordPress options
     *
     * @since 1.0.0
     * @return bool True if saved successfully
     */
    private function saveLicenseData(): bool
    {
        return update_option('woo_ai_assistant_license_data', $this->licenseData);
    }

    /**
     * Save usage data to WordPress options
     *
     * @since 1.0.0
     * @return bool True if saved successfully
     */
    private function saveUsageData(): bool
    {
        $this->usageData['last_updated'] = current_time('mysql');
        return update_option('woo_ai_assistant_usage_data', $this->usageData);
    }

    /**
     * Get plan configuration
     *
     * @since 1.0.0
     * @param string|null $plan Plan type (null for current plan)
     * @return array Plan configuration
     */
    public function getPlanConfiguration(?string $plan = null): array
    {
        if ($plan === null) {
            return $this->planConfig;
        }

        return $this->planConfigurations[$plan] ?? [];
    }

    /**
     * Get all available plans
     *
     * @since 1.0.0
     * @return array All plan configurations
     */
    public function getAvailablePlans(): array
    {
        return $this->planConfigurations;
    }

    /**
     * Check if upgrade is available
     *
     * @since 1.0.0
     * @return bool True if upgrade available
     */
    public function canUpgrade(): bool
    {
        return $this->licenseData['plan'] !== self::PLAN_UNLIMITED;
    }

    /**
     * Get upgrade URL
     *
     * @since 1.0.0
     * @return string Upgrade URL
     */
    public function getUpgradeUrl(): string
    {
        $baseUrl = apply_filters('woo_ai_assistant_upgrade_url', 'https://woo-ai-assistant.com/upgrade');

        if (function_exists('add_query_arg')) {
            return add_query_arg([
                'current_plan' => $this->licenseData['plan'],
                'domain' => $this->getSiteDomain()
            ], $baseUrl);
        }

        // Fallback for test environment
        $query = http_build_query([
            'current_plan' => $this->licenseData['plan'],
            'domain' => $this->getSiteDomain()
        ]);

        return $baseUrl . (strpos($baseUrl, '?') !== false ? '&' : '?') . $query;
    }

    /**
     * Get current AI model based on plan
     *
     * @since 1.0.0
     * @return string AI model identifier
     */
    public function getCurrentAiModel(): string
    {
        return $this->planConfig['ai_model'] ?? 'gemini-2.5-flash';
    }

    /**
     * Apply SLA delay based on plan
     *
     * @since 1.0.0
     * @return void
     */
    public function applySlaDelay(): void
    {
        $delay = $this->planConfig['sla_delay_seconds'] ?? 0;

        if ($delay > 0) {
            sleep($delay);
            Utils::logDebug("Applied SLA delay: {$delay} seconds");
        }
    }

    /**
     * Get branding configuration
     *
     * @since 1.0.0
     * @return array Branding configuration
     */
    public function getBrandingConfig(): array
    {
        $branding = $this->planConfig['branding'] ?? 'Powered by Woo AI Assistant';

        return [
            'show_branding' => $branding !== 'white-label',
            'branding_text' => $branding === 'white-label' ? '' : $branding,
            'white_label' => $branding === 'white-label'
        ];
    }

    /**
     * Get current plan name
     *
     * @since 1.0.0
     * @return string Current plan name
     */
    public function getCurrentPlan(): string
    {
        return $this->licenseData['plan'] ?? self::PLAN_FREE;
    }

    /**
     * Check if in development mode
     *
     * @since 1.0.0
     * @return bool True if in development mode
     */
    private function isDevelopmentMode(): bool
    {
        return defined('WOO_AI_ASSISTANT_DEBUG') && WOO_AI_ASSISTANT_DEBUG;
    }
}
