<?php

/**
 * License Manager Class
 *
 * Manages license validation, plan-based features, and usage limits.
 * Integrates with the intermediate server for license validation
 * while providing graceful degradation and development mode support.
 *
 * @package WooAiAssistant
 * @subpackage Api
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Api;

use WooAiAssistant\Common\Traits\Singleton;
use WooAiAssistant\Common\Logger;
use WooAiAssistant\Common\Cache;
use WooAiAssistant\Common\Exceptions\ApiException;
use WooAiAssistant\Common\Exceptions\ValidationException;
use WooAiAssistant\Common\Exceptions\WooAiException;
use WooAiAssistant\Config\ApiConfiguration;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LicenseManager
 *
 * Handles license validation and feature gating based on subscription plans.
 *
 * @since 1.0.0
 */
class LicenseManager
{
    use Singleton;

    /**
     * Plan constants
     */
    public const PLAN_FREE = 'free';
    public const PLAN_PRO = 'pro';
    public const PLAN_UNLIMITED = 'unlimited';

    /**
     * License status constants
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_INVALID = 'invalid';

    /**
     * Cache key prefixes
     */
    private const CACHE_LICENSE_VALIDATION = 'license_validation';
    private const CACHE_LICENSE_USAGE = 'license_usage';
    private const CACHE_FEATURE_ACCESS = 'feature_access';

    /**
     * Intermediate server client
     *
     * @var IntermediateServerClient
     */
    private IntermediateServerClient $serverClient;

    /**
     * API configuration instance
     *
     * @var ApiConfiguration
     */
    private ApiConfiguration $apiConfiguration;

    /**
     * Cache instance
     *
     * @var Cache
     */
    private Cache $cache;

    /**
     * Current license data
     *
     * @var array|null
     */
    private ?array $licenseData = null;

    /**
     * Plan features matrix
     *
     * @var array
     */
    private array $planFeatures = [
        self::PLAN_FREE => [
            'conversations_per_month' => 50,
            'knowledge_base_items' => 100,
            'ai_models' => ['gemini-2.0-flash-exp:free'],
            'features' => [
                'basic_chat' => true,
                'knowledge_base' => true,
                'product_recommendations' => false,
                'advanced_coupon_generation' => false,
                'proactive_triggers' => false,
                'analytics' => false,
                'priority_support' => false,
                'custom_training' => false,
                'api_access' => false,
                'white_label' => false
            ]
        ],
        self::PLAN_PRO => [
            'conversations_per_month' => 500,
            'knowledge_base_items' => 1000,
            'ai_models' => ['gemini-2.0-flash-exp', 'gemini-2.0-pro'],
            'features' => [
                'basic_chat' => true,
                'knowledge_base' => true,
                'product_recommendations' => true,
                'advanced_coupon_generation' => true,
                'proactive_triggers' => true,
                'analytics' => true,
                'priority_support' => false,
                'custom_training' => false,
                'api_access' => false,
                'white_label' => false
            ]
        ],
        self::PLAN_UNLIMITED => [
            'conversations_per_month' => -1, // Unlimited
            'knowledge_base_items' => -1, // Unlimited
            'ai_models' => ['gemini-2.0-flash-exp', 'gemini-2.0-pro', 'claude-3-haiku', 'claude-3-sonnet'],
            'features' => [
                'basic_chat' => true,
                'knowledge_base' => true,
                'product_recommendations' => true,
                'advanced_coupon_generation' => true,
                'proactive_triggers' => true,
                'analytics' => true,
                'priority_support' => true,
                'custom_training' => true,
                'api_access' => true,
                'white_label' => true
            ]
        ]
    ];

    /**
     * Initialize the license manager
     *
     * @return void
     */
    protected function init(): void
    {
        $this->serverClient = IntermediateServerClient::getInstance();
        $this->apiConfiguration = ApiConfiguration::getInstance();
        $this->cache = Cache::getInstance();

        // Schedule daily license validation
        if (!wp_next_scheduled('woo_ai_assistant_validate_license')) {
            wp_schedule_event(time(), 'daily', 'woo_ai_assistant_validate_license');
        }

        add_action('woo_ai_assistant_validate_license', [$this, 'scheduledLicenseValidation']);

        Logger::debug('LicenseManager initialized', [
            'is_development' => $this->isDevelopmentMode(),
            'plans_configured' => array_keys($this->planFeatures)
        ]);
    }

    /**
     * Validate license key
     *
     * @param string $licenseKey License key to validate
     * @param bool $useCache Whether to use cached results
     * @return array Validation result with license data
     * @throws ValidationException When license key is invalid format
     * @throws ApiException When validation request fails
     */
    public function validateLicense(string $licenseKey, bool $useCache = true): array
    {
        if (empty(trim($licenseKey))) {
            throw new ValidationException('License key cannot be empty', 'LICENSE_ERROR', [
                'field' => 'license_key',
                'validation' => 'required'
            ]);
        }

        // In development mode, bypass validation if configured
        if ($this->isDevelopmentMode()) {
            Logger::debug('License validation bypassed in development mode');

            $developmentLicense = [
                'valid' => true,
                'status' => self::STATUS_ACTIVE,
                'plan' => self::PLAN_UNLIMITED,
                'expires_at' => null,
                'features' => $this->planFeatures[self::PLAN_UNLIMITED]['features'],
                'limits' => [
                    'conversations_per_month' => -1,
                    'knowledge_base_items' => -1
                ],
                'usage' => [
                    'conversations_used' => 0,
                    'knowledge_base_items' => 0,
                    'reset_date' => null
                ],
                'development_mode' => true,
                'validated_at' => time()
            ];

            $this->licenseData = $developmentLicense;
            return $developmentLicense;
        }

        $cacheKey = self::CACHE_LICENSE_VALIDATION . '_' . hash('sha256', $licenseKey);

        // Try to get from cache first if requested
        if ($useCache) {
            $cachedData = $this->cache->get($cacheKey);
            if ($cachedData !== false) {
                Logger::debug('License validation returned from cache');
                $this->licenseData = $cachedData;
                return $cachedData;
            }
        }

        try {
            $validationResponse = $this->serverClient->validateLicense($licenseKey);

            $licenseData = [
                'valid' => $validationResponse['valid'] ?? false,
                'status' => $validationResponse['status'] ?? self::STATUS_INVALID,
                'plan' => $validationResponse['plan'] ?? self::PLAN_FREE,
                'expires_at' => $validationResponse['expires_at'] ?? null,
                'features' => $this->getFeaturesForPlan($validationResponse['plan'] ?? self::PLAN_FREE),
                'limits' => $this->getLimitsForPlan($validationResponse['plan'] ?? self::PLAN_FREE),
                'usage' => $validationResponse['usage'] ?? [
                    'conversations_used' => 0,
                    'knowledge_base_items' => 0,
                    'reset_date' => null
                ],
                'development_mode' => false,
                'validated_at' => time()
            ];

            // Cache for 24 hours
            $this->cache->set($cacheKey, $licenseData, DAY_IN_SECONDS);

            // Store as current license data
            $this->licenseData = $licenseData;

            // Update WordPress options
            update_option('woo_ai_assistant_license_key', $licenseKey);
            update_option('woo_ai_assistant_license_status', $licenseData['status']);
            update_option('woo_ai_assistant_license_plan', $licenseData['plan']);
            update_option('woo_ai_assistant_license_validated_at', time());

            Logger::info('License validated successfully', [
                'status' => $licenseData['status'],
                'plan' => $licenseData['plan'],
                'expires_at' => $licenseData['expires_at']
            ]);

            return $licenseData;
        } catch (ApiException $e) {
            Logger::warning('License validation failed', [
                'error' => $e->getMessage(),
                'status_code' => $e->getHttpStatusCode()
            ]);

            // Return graceful degradation to FREE plan
            $fallbackLicense = [
                'valid' => false,
                'status' => self::STATUS_INVALID,
                'plan' => self::PLAN_FREE,
                'expires_at' => null,
                'features' => $this->getFeaturesForPlan(self::PLAN_FREE),
                'limits' => $this->getLimitsForPlan(self::PLAN_FREE),
                'usage' => [
                    'conversations_used' => 0,
                    'knowledge_base_items' => 0,
                    'reset_date' => null
                ],
                'development_mode' => false,
                'validated_at' => time(),
                'validation_error' => $e->getMessage()
            ];

            // Cache failed validation for 1 hour (shorter than successful validation)
            $this->cache->set($cacheKey, $fallbackLicense, HOUR_IN_SECONDS);
            $this->licenseData = $fallbackLicense;

            return $fallbackLicense;
        }
    }

    /**
     * Get current plan
     *
     * @return string Current plan (FREE, PRO, UNLIMITED)
     */
    public function getCurrentPlan(): string
    {
        if ($this->licenseData === null) {
            $this->loadCurrentLicense();
        }

        return $this->licenseData['plan'] ?? self::PLAN_FREE;
    }

    /**
     * Check if a feature is enabled for current license
     *
     * @param string $feature Feature name to check
     * @return bool True if feature is enabled
     */
    public function isFeatureEnabled(string $feature): bool
    {
        if ($this->licenseData === null) {
            $this->loadCurrentLicense();
        }

        $cacheKey = self::CACHE_FEATURE_ACCESS . "_{$feature}_" . $this->getCurrentPlan();
        $cached = $this->cache->get($cacheKey);

        if ($cached !== false) {
            return $cached;
        }

        $enabled = $this->licenseData['features'][$feature] ?? false;

        // Cache feature access for 1 hour
        $this->cache->set($cacheKey, $enabled, HOUR_IN_SECONDS);

        Logger::debug("Feature access checked", [
            'feature' => $feature,
            'enabled' => $enabled,
            'plan' => $this->getCurrentPlan()
        ]);

        return $enabled;
    }

    /**
     * Check usage limits for current license
     *
     * @param string $metric Metric to check (conversations, knowledge_base_items)
     * @param int $increment Amount to add to usage (for pre-checking)
     * @return array Limit check result with usage details
     */
    public function checkLimits(string $metric, int $increment = 0): array
    {
        if ($this->licenseData === null) {
            $this->loadCurrentLicense();
        }

        $limits = $this->licenseData['limits'] ?? [];
        $usage = $this->licenseData['usage'] ?? [];

        $limit = $limits[$metric] ?? 0;
        $used = ($usage[$metric . '_used'] ?? 0) + $increment;

        // -1 means unlimited
        if ($limit === -1) {
            return [
                'within_limits' => true,
                'limit' => -1,
                'used' => $used,
                'remaining' => -1,
                'percentage_used' => 0,
                'metric' => $metric
            ];
        }

        $withinLimits = $used <= $limit;
        $remaining = max(0, $limit - $used);
        $percentageUsed = $limit > 0 ? round(($used / $limit) * 100, 2) : 100;

        $result = [
            'within_limits' => $withinLimits,
            'limit' => $limit,
            'used' => $used,
            'remaining' => $remaining,
            'percentage_used' => $percentageUsed,
            'metric' => $metric
        ];

        Logger::debug("Limit check performed", array_merge($result, [
            'plan' => $this->getCurrentPlan(),
            'increment' => $increment
        ]));

        return $result;
    }

    /**
     * Update usage statistics
     *
     * @param string $metric Metric to update
     * @param int $increment Amount to increment
     * @return bool True if update was successful and within limits
     * @throws WooAiException When usage exceeds limits
     */
    public function updateUsage(string $metric, int $increment = 1): bool
    {
        // Check limits before updating
        $limitCheck = $this->checkLimits($metric, $increment);

        if (!$limitCheck['within_limits']) {
            throw new WooAiException(
                "Usage limit exceeded for {$metric}",
                'RATE_LIMIT_ERROR',
                [
                    'metric' => $metric,
                    'current_usage' => $limitCheck['used'] - $increment,
                    'limit' => $limitCheck['limit'],
                    'increment' => $increment,
                    'plan' => $this->getCurrentPlan()
                ]
            );
        }

        // Update local usage data
        if (!isset($this->licenseData['usage'])) {
            $this->licenseData['usage'] = [];
        }

        $usageKey = $metric . '_used';
        $this->licenseData['usage'][$usageKey] = ($this->licenseData['usage'][$usageKey] ?? 0) + $increment;

        // Update cached license data
        $licenseKey = get_option('woo_ai_assistant_license_key', '');
        if (!empty($licenseKey)) {
            $cacheKey = self::CACHE_LICENSE_VALIDATION . '_' . hash('sha256', $licenseKey);
            $this->cache->set($cacheKey, $this->licenseData, DAY_IN_SECONDS);
        }

        // Store usage locally for offline capability
        $optionKey = "woo_ai_assistant_usage_{$metric}";
        update_option($optionKey, $this->licenseData['usage'][$usageKey]);

        Logger::info("Usage updated", [
            'metric' => $metric,
            'increment' => $increment,
            'new_total' => $this->licenseData['usage'][$usageKey],
            'plan' => $this->getCurrentPlan()
        ]);

        return true;
    }

    /**
     * Get license usage statistics
     *
     * @param bool $refresh Whether to fetch fresh data from server
     * @return array Usage statistics
     */
    public function getUsageStatistics(bool $refresh = false): array
    {
        if ($this->isDevelopmentMode()) {
            return [
                'conversations_used' => 0,
                'knowledge_base_items' => 0,
                'limit_conversations' => -1,
                'limit_knowledge_base' => -1,
                'reset_date' => null,
                'development_mode' => true
            ];
        }

        $cacheKey = self::CACHE_LICENSE_USAGE;

        if (!$refresh) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
        }

        $licenseKey = get_option('woo_ai_assistant_license_key', '');

        if (empty($licenseKey)) {
            return $this->getFreePlanUsage();
        }

        try {
            $usage = $this->serverClient->getLicenseUsage($licenseKey);

            // Cache for 1 hour
            $this->cache->set($cacheKey, $usage, HOUR_IN_SECONDS);

            return $usage;
        } catch (ApiException $e) {
            Logger::warning('Failed to fetch usage statistics', [
                'error' => $e->getMessage()
            ]);

            // Return local usage data as fallback
            return $this->getLocalUsageData();
        }
    }

    /**
     * Get available AI models for current plan
     *
     * @return array Available AI models
     */
    public function getAvailableModels(): array
    {
        $plan = $this->getCurrentPlan();
        return $this->planFeatures[$plan]['ai_models'] ?? [];
    }

    /**
     * Check if current license is valid and active
     *
     * @return bool True if license is valid and active
     */
    public function isLicenseValid(): bool
    {
        if ($this->licenseData === null) {
            $this->loadCurrentLicense();
        }

        if ($this->isDevelopmentMode()) {
            return true;
        }

        $status = $this->licenseData['status'] ?? self::STATUS_INVALID;
        $valid = $this->licenseData['valid'] ?? false;

        return $valid && $status === self::STATUS_ACTIVE;
    }

    /**
     * Get license expiration date
     *
     * @return int|null Expiration timestamp or null if no expiration
     */
    public function getLicenseExpiration(): ?int
    {
        if ($this->licenseData === null) {
            $this->loadCurrentLicense();
        }

        return $this->licenseData['expires_at'] ?? null;
    }

    /**
     * Check if license is expired
     *
     * @return bool True if license is expired
     */
    public function isLicenseExpired(): bool
    {
        $expiration = $this->getLicenseExpiration();

        if ($expiration === null) {
            return false; // No expiration date
        }

        return time() > $expiration;
    }

    /**
     * Get days until license expiration
     *
     * @return int|null Days until expiration or null if no expiration
     */
    public function getDaysUntilExpiration(): ?int
    {
        $expiration = $this->getLicenseExpiration();

        if ($expiration === null) {
            return null;
        }

        $daysRemaining = ceil(($expiration - time()) / DAY_IN_SECONDS);
        return max(0, $daysRemaining);
    }

    /**
     * Scheduled license validation (runs daily)
     *
     * @return void
     */
    public function scheduledLicenseValidation(): void
    {
        $licenseKey = get_option('woo_ai_assistant_license_key', '');

        if (empty($licenseKey)) {
            Logger::debug('No license key for scheduled validation');
            return;
        }

        try {
            // Force refresh from server
            $this->validateLicense($licenseKey, false);
            Logger::info('Scheduled license validation completed');
        } catch (Exception $e) {
            Logger::error('Scheduled license validation failed', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Clear license cache
     *
     * @return void
     */
    public function clearLicenseCache(): void
    {
        $this->cache->deleteByPattern(self::CACHE_LICENSE_VALIDATION . '*');
        $this->cache->deleteByPattern(self::CACHE_LICENSE_USAGE . '*');
        $this->cache->deleteByPattern(self::CACHE_FEATURE_ACCESS . '*');

        $this->licenseData = null;

        Logger::info('License cache cleared');
    }

    /**
     * Check if we're in development mode
     *
     * @return bool True if in development mode
     */
    private function isDevelopmentMode(): bool
    {
        return $this->apiConfiguration->isDevelopmentMode();
    }

    /**
     * Load current license from storage/cache
     *
     * @return void
     */
    private function loadCurrentLicense(): void
    {
        $licenseKey = get_option('woo_ai_assistant_license_key', '');

        if (empty($licenseKey)) {
            // No license key, default to free plan
            $this->licenseData = [
                'valid' => false,
                'status' => self::STATUS_INACTIVE,
                'plan' => self::PLAN_FREE,
                'features' => $this->getFeaturesForPlan(self::PLAN_FREE),
                'limits' => $this->getLimitsForPlan(self::PLAN_FREE),
                'usage' => $this->getLocalUsageData()
            ];
            return;
        }

        try {
            // Try to validate license (will use cache if available)
            $this->validateLicense($licenseKey, true);
        } catch (Exception $e) {
            Logger::warning('Failed to load current license, defaulting to free plan', [
                'error' => $e->getMessage()
            ]);

            // Fallback to free plan
            $this->licenseData = [
                'valid' => false,
                'status' => self::STATUS_INVALID,
                'plan' => self::PLAN_FREE,
                'features' => $this->getFeaturesForPlan(self::PLAN_FREE),
                'limits' => $this->getLimitsForPlan(self::PLAN_FREE),
                'usage' => $this->getLocalUsageData()
            ];
        }
    }

    /**
     * Get features for a specific plan
     *
     * @param string $plan Plan name
     * @return array Features array
     */
    private function getFeaturesForPlan(string $plan): array
    {
        return $this->planFeatures[$plan]['features'] ?? $this->planFeatures[self::PLAN_FREE]['features'];
    }

    /**
     * Get limits for a specific plan
     *
     * @param string $plan Plan name
     * @return array Limits array
     */
    private function getLimitsForPlan(string $plan): array
    {
        $planConfig = $this->planFeatures[$plan] ?? $this->planFeatures[self::PLAN_FREE];

        return [
            'conversations_per_month' => $planConfig['conversations_per_month'],
            'knowledge_base_items' => $planConfig['knowledge_base_items']
        ];
    }

    /**
     * Get free plan usage (defaults)
     *
     * @return array Free plan usage statistics
     */
    private function getFreePlanUsage(): array
    {
        return [
            'conversations_used' => get_option('woo_ai_assistant_usage_conversations', 0),
            'knowledge_base_items' => get_option('woo_ai_assistant_usage_knowledge_base_items', 0),
            'limit_conversations' => $this->planFeatures[self::PLAN_FREE]['conversations_per_month'],
            'limit_knowledge_base' => $this->planFeatures[self::PLAN_FREE]['knowledge_base_items'],
            'reset_date' => strtotime('first day of next month'),
            'plan' => self::PLAN_FREE
        ];
    }

    /**
     * Get local usage data from WordPress options
     *
     * @return array Local usage data
     */
    private function getLocalUsageData(): array
    {
        return [
            'conversations_used' => get_option('woo_ai_assistant_usage_conversations', 0),
            'knowledge_base_items_used' => get_option('woo_ai_assistant_usage_knowledge_base_items', 0),
            'reset_date' => get_option('woo_ai_assistant_usage_reset_date', strtotime('first day of next month'))
        ];
    }
}
