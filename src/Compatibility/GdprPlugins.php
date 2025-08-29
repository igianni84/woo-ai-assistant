<?php

/**
 * GDPR Compliance Class
 *
 * Handles automatic detection and integration with popular WordPress GDPR and cookie consent
 * plugins including Complianz, CookieYes, Cookiebot, Cookie Notice, GDPR Cookie Compliance,
 * and Borlabs Cookie. Provides zero-config GDPR compliance for the AI chat system.
 *
 * @package WooAiAssistant
 * @subpackage Compatibility
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Compatibility;

use WooAiAssistant\Common\Utils;
use WooAiAssistant\Common\Traits\Singleton;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class GdprPlugins
 *
 * Comprehensive GDPR compliance system that automatically detects installed
 * cookie consent and GDPR plugins, implements consent checking, provides minimal
 * mode when consent is not given, and handles data retention, export, and deletion.
 *
 * @since 1.0.0
 */
class GdprPlugins
{
    use Singleton;

    /**
     * Detected GDPR plugin
     *
     * @since 1.0.0
     * @var string|null
     */
    private ?string $detectedPlugin = null;

    /**
     * Current consent status
     *
     * @since 1.0.0
     * @var bool
     */
    private bool $consentGiven = false;

    /**
     * Minimal mode status
     *
     * @since 1.0.0
     * @var bool
     */
    private bool $minimalMode = false;

    /**
     * Data retention period in days
     *
     * @since 1.0.0
     * @var int
     */
    private int $dataRetentionDays = 30;

    /**
     * Consent cache
     *
     * @since 1.0.0
     * @var array
     */
    private array $consentCache = [];

    /**
     * Supported GDPR plugins
     *
     * @since 1.0.0
     * @var array
     */
    private array $supportedPlugins = [
        'complianz' => 'Complianz GDPR/CCPA',
        'cookieyes' => 'CookieYes',
        'cookiebot' => 'Cookiebot',
        'cookie_notice' => 'Cookie Notice & Compliance',
        'gdpr_cookie_compliance' => 'GDPR Cookie Compliance',
        'borlabs_cookie' => 'Borlabs Cookie',
        'real_cookie_banner' => 'Real Cookie Banner',
        'wp_gdpr_compliance' => 'WP GDPR Compliance',
        'gdpr_framework' => 'The GDPR Framework'
    ];

    /**
     * Cookie categories that require consent
     *
     * @since 1.0.0
     * @var array
     */
    private array $requiredConsentCategories = [
        'functional',
        'statistics',
        'marketing',
        'preferences'
    ];

    /**
     * Constructor
     *
     * Initializes the GDPR compliance system by detecting active plugins,
     * checking consent status, and setting up data retention policies.
     *
     * @since 1.0.0
     */
    private function __construct()
    {
        $this->detectGdprPlugin();
        $this->initializeConsentStatus();
        $this->initializeDataRetention();
        $this->setupHooks();
    }

    /**
     * Detect active GDPR plugin
     *
     * Automatically detects which GDPR/cookie consent plugin is active and sets up
     * appropriate integration hooks and methods.
     *
     * @since 1.0.0
     * @return void
     */
    private function detectGdprPlugin(): void
    {
        try {
            // Check for Complianz
            if ($this->isComplianzActive()) {
                $this->detectedPlugin = 'complianz';
                Utils::logDebug('Complianz GDPR plugin detected and activated');
                return;
            }

            // Check for CookieYes
            if ($this->isCookieYesActive()) {
                $this->detectedPlugin = 'cookieyes';
                Utils::logDebug('CookieYes plugin detected and activated');
                return;
            }

            // Check for Cookiebot
            if ($this->isCookiebotActive()) {
                $this->detectedPlugin = 'cookiebot';
                Utils::logDebug('Cookiebot plugin detected and activated');
                return;
            }

            // Check for Cookie Notice
            if ($this->isCookieNoticeActive()) {
                $this->detectedPlugin = 'cookie_notice';
                Utils::logDebug('Cookie Notice plugin detected and activated');
                return;
            }

            // Check for GDPR Cookie Compliance
            if ($this->isGdprCookieComplianceActive()) {
                $this->detectedPlugin = 'gdpr_cookie_compliance';
                Utils::logDebug('GDPR Cookie Compliance plugin detected and activated');
                return;
            }

            // Check for Borlabs Cookie
            if ($this->isBorlabsCookieActive()) {
                $this->detectedPlugin = 'borlabs_cookie';
                Utils::logDebug('Borlabs Cookie plugin detected and activated');
                return;
            }

            // Check for Real Cookie Banner
            if ($this->isRealCookieBannerActive()) {
                $this->detectedPlugin = 'real_cookie_banner';
                Utils::logDebug('Real Cookie Banner plugin detected and activated');
                return;
            }

            // Check for WP GDPR Compliance
            if ($this->isWpGdprComplianceActive()) {
                $this->detectedPlugin = 'wp_gdpr_compliance';
                Utils::logDebug('WP GDPR Compliance plugin detected and activated');
                return;
            }

            // Check for GDPR Framework
            if ($this->isGdprFrameworkActive()) {
                $this->detectedPlugin = 'gdpr_framework';
                Utils::logDebug('GDPR Framework plugin detected and activated');
                return;
            }

            // No GDPR plugin detected - use default compliance mode
            $this->detectedPlugin = null;
            $this->minimalMode = true; // Default to minimal mode for safety
            Utils::logDebug('No GDPR plugin detected - using default compliance mode with minimal operation');
        } catch (\Exception $e) {
            Utils::logError('Error detecting GDPR plugin: ' . $e->getMessage());
            $this->detectedPlugin = null;
            $this->minimalMode = true; // Default to minimal mode on error
        }
    }

    /**
     * Check if Complianz is active
     *
     * @since 1.0.0
     * @return bool True if Complianz is active and functional
     */
    private function isComplianzActive(): bool
    {
        return defined('COMPLIANZ_VERSION') &&
               class_exists('COMPLIANZ\\Cookie_Blocker') &&
               function_exists('cmplz_get_value');
    }

    /**
     * Check if CookieYes is active
     *
     * @since 1.0.0
     * @return bool True if CookieYes is active and functional
     */
    private function isCookieYesActive(): bool
    {
        return defined('CLI_VERSION') &&
               class_exists('Cookie_Law_Info') &&
               function_exists('cli_get_cookie');
    }

    /**
     * Check if Cookiebot is active
     *
     * @since 1.0.0
     * @return bool True if Cookiebot is active and functional
     */
    private function isCookiebotActive(): bool
    {
        return defined('COOKIEBOT_PLUGIN_VERSION') &&
               class_exists('Cookiebot_WP') &&
               function_exists('cookiebot_is_active');
    }

    /**
     * Check if Cookie Notice is active
     *
     * @since 1.0.0
     * @return bool True if Cookie Notice is active and functional
     */
    private function isCookieNoticeActive(): bool
    {
        return class_exists('Cookie_Notice') &&
               defined('COOKIE_NOTICE_VERSION');
    }

    /**
     * Check if GDPR Cookie Compliance is active
     *
     * @since 1.0.0
     * @return bool True if GDPR Cookie Compliance is active and functional
     */
    private function isGdprCookieComplianceActive(): bool
    {
        return class_exists('GDPR_Cookie_Compliance_Free') &&
               defined('GDPR_COOKIE_COMPLIANCE_PLUGIN_VERSION');
    }

    /**
     * Check if Borlabs Cookie is active
     *
     * @since 1.0.0
     * @return bool True if Borlabs Cookie is active and functional
     */
    private function isBorlabsCookieActive(): bool
    {
        return class_exists('BorlabsCookie\\Cookie\\Frontend\\Consent') &&
               defined('BORLABS_COOKIE_VERSION');
    }

    /**
     * Check if Real Cookie Banner is active
     *
     * @since 1.0.0
     * @return bool True if Real Cookie Banner is active and functional
     */
    private function isRealCookieBannerActive(): bool
    {
        return class_exists('DevOwl\\RealCookieBanner\\Vendor\\DevOwl\\CookieConsent\\frontend\\Consent') &&
               defined('RCB_VERSION');
    }

    /**
     * Check if WP GDPR Compliance is active
     *
     * @since 1.0.0
     * @return bool True if WP GDPR Compliance is active and functional
     */
    private function isWpGdprComplianceActive(): bool
    {
        return class_exists('WPGDPRC\\WPGDPRC') &&
               defined('WPGDPRC_VERSION');
    }

    /**
     * Check if GDPR Framework is active
     *
     * @since 1.0.0
     * @return bool True if GDPR Framework is active and functional
     */
    private function isGdprFrameworkActive(): bool
    {
        return class_exists('WordPress\\GDPR') &&
               defined('WORDPRESS_GDPR_VERSION');
    }

    /**
     * Initialize consent status
     *
     * Checks current consent status based on the detected GDPR plugin
     * and determines if minimal mode should be activated.
     *
     * @since 1.0.0
     * @return void
     */
    private function initializeConsentStatus(): void
    {
        try {
            $this->consentGiven = $this->checkConsentStatus();
            $this->minimalMode = !$this->consentGiven;

            Utils::logDebug('Consent status initialized', [
                'plugin' => $this->detectedPlugin,
                'consent_given' => $this->consentGiven,
                'minimal_mode' => $this->minimalMode
            ]);

            // Apply consent status to chat functionality
            $this->applyConsentStatus();
        } catch (\Exception $e) {
            Utils::logError('Error initializing consent status: ' . $e->getMessage());
            $this->consentGiven = false;
            $this->minimalMode = true;
        }
    }

    /**
     * Check consent status
     *
     * Determines if user has given consent for functional cookies
     * based on the detected GDPR plugin.
     *
     * @since 1.0.0
     * @return bool True if consent is given for functional cookies
     */
    private function checkConsentStatus(): bool
    {
        // Check cache first
        $cacheKey = 'consent_status_' . $this->detectedPlugin . '_' . $this->getCurrentUserId();
        if (isset($this->consentCache[$cacheKey])) {
            return $this->consentCache[$cacheKey];
        }

        $consentGiven = false;

        try {
            switch ($this->detectedPlugin) {
                case 'complianz':
                    $consentGiven = $this->checkComplianzConsent();
                    break;

                case 'cookieyes':
                    $consentGiven = $this->checkCookieYesConsent();
                    break;

                case 'cookiebot':
                    $consentGiven = $this->checkCookiebotConsent();
                    break;

                case 'cookie_notice':
                    $consentGiven = $this->checkCookieNoticeConsent();
                    break;

                case 'gdpr_cookie_compliance':
                    $consentGiven = $this->checkGdprCookieComplianceConsent();
                    break;

                case 'borlabs_cookie':
                    $consentGiven = $this->checkBorlabsCookieConsent();
                    break;

                case 'real_cookie_banner':
                    $consentGiven = $this->checkRealCookieBannerConsent();
                    break;

                case 'wp_gdpr_compliance':
                    $consentGiven = $this->checkWpGdprComplianceConsent();
                    break;

                case 'gdpr_framework':
                    $consentGiven = $this->checkGdprFrameworkConsent();
                    break;

                default:
                    // No GDPR plugin detected - check if admin disabled minimal mode
                    $consentGiven = $this->isConsentAssumed();
                    break;
            }
        } catch (\Exception $e) {
            Utils::logError('Error checking consent status: ' . $e->getMessage());
            $consentGiven = false;
        }

        // Cache the result for 5 minutes
        $this->consentCache[$cacheKey] = $consentGiven;
        wp_cache_set($cacheKey, $consentGiven, 'woo_ai_assistant_gdpr', 300);

        return $consentGiven;
    }

    /**
     * Check Complianz consent status
     *
     * @since 1.0.0
     * @return bool True if functional consent is given
     */
    private function checkComplianzConsent(): bool
    {
        if (!$this->isComplianzActive()) {
            return false;
        }

        // Check for functional category consent
        if (function_exists('cmplz_has_consent')) {
            return cmplz_has_consent('functional');
        }

        // Fallback check using cookie
        return isset($_COOKIE['complianz_consent_status']) &&
               strpos($_COOKIE['complianz_consent_status'], '"functional":true') !== false;
    }

    /**
     * Check CookieYes consent status
     *
     * @since 1.0.0
     * @return bool True if functional consent is given
     */
    private function checkCookieYesConsent(): bool
    {
        if (!$this->isCookieYesActive()) {
            return false;
        }

        // Check CLI cookie
        if (function_exists('cli_get_cookie')) {
            $cookie = cli_get_cookie();
            return isset($cookie['functional']) && $cookie['functional'] === 'yes';
        }

        // Fallback check using cookie
        return isset($_COOKIE['cookielawinfo-checkbox-functional']) &&
               $_COOKIE['cookielawinfo-checkbox-functional'] === 'yes';
    }

    /**
     * Check Cookiebot consent status
     *
     * @since 1.0.0
     * @return bool True if functional consent is given
     */
    private function checkCookiebotConsent(): bool
    {
        if (!$this->isCookiebotActive()) {
            return false;
        }

        // Check Cookiebot consent
        return isset($_COOKIE['CookieConsent']) &&
               strpos($_COOKIE['CookieConsent'], 'preferences:true') !== false;
    }

    /**
     * Check Cookie Notice consent status
     *
     * @since 1.0.0
     * @return bool True if consent is given
     */
    private function checkCookieNoticeConsent(): bool
    {
        if (!$this->isCookieNoticeActive()) {
            return false;
        }

        return isset($_COOKIE['cookie_notice_accepted']) &&
               $_COOKIE['cookie_notice_accepted'] === 'true';
    }

    /**
     * Check GDPR Cookie Compliance consent status
     *
     * @since 1.0.0
     * @return bool True if functional consent is given
     */
    private function checkGdprCookieComplianceConsent(): bool
    {
        if (!$this->isGdprCookieComplianceActive()) {
            return false;
        }

        return isset($_COOKIE['wpl_user_preference']) &&
               strpos($_COOKIE['wpl_user_preference'], '"functional":1') !== false;
    }

    /**
     * Check Borlabs Cookie consent status
     *
     * @since 1.0.0
     * @return bool True if functional consent is given
     */
    private function checkBorlabsCookieConsent(): bool
    {
        if (!$this->isBorlabsCookieActive()) {
            return false;
        }

        if (class_exists('BorlabsCookie\\Cookie\\Frontend\\Consent')) {
            $consent = \BorlabsCookie\Cookie\Frontend\Consent::getInstance();
            return $consent->checkConsent('essential') || $consent->checkConsent('functional');
        }

        return isset($_COOKIE['borlabs-cookie']) &&
               strpos($_COOKIE['borlabs-cookie'], '"functional":true') !== false;
    }

    /**
     * Check Real Cookie Banner consent status
     *
     * @since 1.0.0
     * @return bool True if functional consent is given
     */
    private function checkRealCookieBannerConsent(): bool
    {
        if (!$this->isRealCookieBannerActive()) {
            return false;
        }

        return isset($_COOKIE['real-cookie-banner']) &&
               strpos($_COOKIE['real-cookie-banner'], '"functional":true') !== false;
    }

    /**
     * Check WP GDPR Compliance consent status
     *
     * @since 1.0.0
     * @return bool True if consent is given
     */
    private function checkWpGdprComplianceConsent(): bool
    {
        if (!$this->isWpGdprComplianceActive()) {
            return false;
        }

        return isset($_COOKIE['wpgdprc']) &&
               $_COOKIE['wpgdprc'] === '1';
    }

    /**
     * Check GDPR Framework consent status
     *
     * @since 1.0.0
     * @return bool True if consent is given
     */
    private function checkGdprFrameworkConsent(): bool
    {
        if (!$this->isGdprFrameworkActive()) {
            return false;
        }

        if (class_exists('WordPress\\GDPR')) {
            $gdpr = \WordPress\GDPR::getInstance();
            return $gdpr->hasConsent();
        }

        return false;
    }

    /**
     * Check if consent is assumed when no GDPR plugin is detected
     *
     * @since 1.0.0
     * @return bool True if consent should be assumed
     */
    private function isConsentAssumed(): bool
    {
        // Check admin setting for default consent behavior
        $assumeConsent = function_exists('get_option') ? get_option('woo_ai_assistant_assume_consent_no_gdpr', false) : false;

        // Apply filter to allow customization
        return function_exists('apply_filters') ? apply_filters('woo_ai_assistant_assume_consent_no_gdpr', $assumeConsent) : $assumeConsent;
    }

    /**
     * Get current user ID for consent tracking
     *
     * @since 1.0.0
     * @return string User identifier
     */
    private function getCurrentUserId(): string
    {
        if (function_exists('is_user_logged_in') && is_user_logged_in()) {
            return 'user_' . get_current_user_id();
        }

        // Use IP + User Agent for anonymous users (hashed for privacy)
        $identifier = ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '_' . ($_SERVER['HTTP_USER_AGENT'] ?? '');
        return 'guest_' . (function_exists('wp_hash') ? wp_hash($identifier) : md5($identifier));
    }

    /**
     * Initialize data retention policies
     *
     * Sets up data retention periods and cleanup schedules.
     *
     * @since 1.0.0
     * @return void
     */
    private function initializeDataRetention(): void
    {
        try {
            // Get retention period from settings
            $this->dataRetentionDays = function_exists('get_option') ? (int) get_option('woo_ai_assistant_data_retention_days', 30) : 30;

            // Ensure minimum retention period
            if ($this->dataRetentionDays < 1) {
                $this->dataRetentionDays = 30;
            }

            // Apply filter to allow customization
            $this->dataRetentionDays = function_exists('apply_filters') ? (int) apply_filters('woo_ai_assistant_data_retention_days', $this->dataRetentionDays) : $this->dataRetentionDays;

            // Schedule cleanup if not already scheduled
            if (function_exists('wp_next_scheduled') && function_exists('wp_schedule_event')) {
                if (!wp_next_scheduled('woo_ai_assistant_cleanup_old_data')) {
                    wp_schedule_event(time(), 'daily', 'woo_ai_assistant_cleanup_old_data');
                }
            }

            Utils::logDebug('Data retention policies initialized', [
                'retention_days' => $this->dataRetentionDays
            ]);
        } catch (\Exception $e) {
            Utils::logError('Error initializing data retention: ' . $e->getMessage());
            $this->dataRetentionDays = 30; // Default fallback
        }
    }

    /**
     * Apply consent status to chat functionality
     *
     * Configures chat system based on current consent status.
     *
     * @since 1.0.0
     * @return void
     */
    private function applyConsentStatus(): void
    {
        if ($this->minimalMode) {
            // Enable minimal mode features
            add_filter('woo_ai_assistant_enable_conversation_persistence', '__return_false');
            add_filter('woo_ai_assistant_enable_user_tracking', '__return_false');
            add_filter('woo_ai_assistant_enable_analytics', '__return_false');
            add_filter('woo_ai_assistant_session_data_collection', '__return_false');

            // Limit data collection
            add_filter('woo_ai_assistant_collect_user_preferences', '__return_false');
            add_filter('woo_ai_assistant_store_chat_history', '__return_false');

            Utils::logDebug('Minimal mode activated - data collection limited');
        } else {
            // Full functionality enabled
            Utils::logDebug('Full mode activated - all features available');
        }

        /**
         * Consent status applied action
         *
         * @since 1.0.0
         * @param bool $consentGiven Whether consent is given
         * @param bool $minimalMode Whether minimal mode is active
         * @param string|null $plugin Detected GDPR plugin
         */
        do_action('woo_ai_assistant_consent_status_applied', $this->consentGiven, $this->minimalMode, $this->detectedPlugin);
    }

    /**
     * Setup WordPress hooks
     *
     * @since 1.0.0
     * @return void
     */
    private function setupHooks(): void
    {
        // Consent status checking hooks
        add_action('wp_loaded', [$this, 'maybeUpdateConsentStatus'], 10);
        add_action('init', [$this, 'handleConsentChanges'], 15);

        // Data retention and cleanup hooks
        add_action('woo_ai_assistant_cleanup_old_data', [$this, 'cleanupOldData']);

        // Privacy hooks
        add_filter('wp_privacy_personal_data_exporters', [$this, 'registerDataExporter']);
        add_filter('wp_privacy_personal_data_erasers', [$this, 'registerDataEraser']);

        // Admin hooks
        add_action('admin_init', [$this, 'registerPrivacySettings']);

        // REST API hooks
        add_filter('woo_ai_assistant_rest_request_context', [$this, 'addGdprContext'], 10, 2);

        // Chat functionality hooks
        add_filter('woo_ai_assistant_can_start_conversation', [$this, 'checkConversationPermission'], 10, 2);
        add_filter('woo_ai_assistant_conversation_data_retention', [$this, 'getDataRetentionPeriod']);

        // Plugin-specific consent change hooks
        switch ($this->detectedPlugin) {
            case 'complianz':
                add_action('cmplz_consent_changed', [$this, 'onConsentChanged']);
                break;
            case 'cookieyes':
                add_action('cli_consent_changed', [$this, 'onConsentChanged']);
                break;
            case 'cookiebot':
                add_action('cookiebot_consent_changed', [$this, 'onConsentChanged']);
                break;
        }

        Utils::logDebug('GDPR compliance hooks registered for plugin: ' . ($this->detectedPlugin ?? 'none'));
    }

    /**
     * Maybe update consent status
     *
     * Checks if consent status has changed and updates accordingly.
     *
     * @since 1.0.0
     * @return void
     */
    public function maybeUpdateConsentStatus(): void
    {
        $previousConsent = $this->consentGiven;
        $newConsent = $this->checkConsentStatus();

        if ($newConsent !== $previousConsent) {
            $this->consentGiven = $newConsent;
            $this->minimalMode = !$newConsent;

            $this->applyConsentStatus();

            /**
             * Consent status changed action
             *
             * @since 1.0.0
             * @param bool $newConsent New consent status
             * @param bool $previousConsent Previous consent status
             * @param string|null $plugin Detected GDPR plugin
             */
            do_action('woo_ai_assistant_consent_changed', $newConsent, $previousConsent, $this->detectedPlugin);

            Utils::logDebug("Consent status changed from " . ($previousConsent ? 'true' : 'false') . " to " . ($newConsent ? 'true' : 'false'));
        }
    }

    /**
     * Handle consent changes
     *
     * Processes consent changes from GDPR plugins.
     *
     * @since 1.0.0
     * @return void
     */
    public function handleConsentChanges(): void
    {
        // Clear consent cache on init to ensure fresh status
        $this->clearConsentCache();

        // Update consent status
        $this->maybeUpdateConsentStatus();
    }

    /**
     * On consent changed callback
     *
     * Handles consent change notifications from GDPR plugins.
     *
     * @since 1.0.0
     * @return void
     */
    public function onConsentChanged(): void
    {
        $this->clearConsentCache();
        $this->maybeUpdateConsentStatus();

        Utils::logDebug('External consent change detected and processed');
    }

    /**
     * Get current consent status
     *
     * @since 1.0.0
     * @return bool True if consent is given
     */
    public function isConsentGiven(): bool
    {
        return $this->consentGiven;
    }

    /**
     * Check if minimal mode is active
     *
     * @since 1.0.0
     * @return bool True if minimal mode is active
     */
    public function isMinimalMode(): bool
    {
        return $this->minimalMode;
    }

    /**
     * Get detected GDPR plugin
     *
     * @since 1.0.0
     * @return string|null Plugin identifier or null if none detected
     */
    public function getDetectedPlugin(): ?string
    {
        return $this->detectedPlugin;
    }

    /**
     * Get data retention period
     *
     * @since 1.0.0
     * @return int Data retention period in days
     */
    public function getDataRetentionPeriod(): int
    {
        return $this->dataRetentionDays;
    }

    /**
     * Check conversation permission
     *
     * Determines if user can start a conversation based on consent status.
     *
     * @since 1.0.0
     * @param bool $canStart Current permission status
     * @param array $context Request context
     * @return bool True if conversation can be started
     */
    public function checkConversationPermission(bool $canStart, array $context = []): bool
    {
        if (!$canStart) {
            return false;
        }

        // In minimal mode, still allow conversations but with limited data collection
        if ($this->minimalMode) {
            Utils::logDebug('Conversation allowed in minimal mode with limited data collection');
            return true;
        }

        return $this->consentGiven;
    }

    /**
     * Add GDPR context to REST API requests
     *
     * @since 1.0.0
     * @param array $context Request context
     * @param \WP_REST_Request $request REST request object
     * @return array Modified context with GDPR information
     */
    public function addGdprContext(array $context, \WP_REST_Request $request): array
    {
        $context['gdpr'] = [
            'consent_given' => $this->isConsentGiven(),
            'minimal_mode' => $this->isMinimalMode(),
            'detected_plugin' => $this->getDetectedPlugin(),
            'data_retention_days' => $this->getDataRetentionPeriod(),
            'supported_plugins' => $this->supportedPlugins
        ];

        return $context;
    }

    /**
     * Cleanup old data
     *
     * Removes data older than the retention period.
     *
     * @since 1.0.0
     * @return void
     */
    public function cleanupOldData(): void
    {
        try {
            global $wpdb;

            $cutoffDate = gmdate('Y-m-d H:i:s', strtotime("-{$this->dataRetentionDays} days"));

            // Clean up conversation data
            $conversationsTable = $wpdb->prefix . 'woo_ai_conversations';
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $conversationsTable)) === $conversationsTable) {
                $deletedConversations = $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$conversationsTable} WHERE created_at < %s",
                    $cutoffDate
                ));

                if ($deletedConversations !== false) {
                    Utils::logDebug("Cleaned up {$deletedConversations} old conversations");
                }
            }

            // Clean up message data
            $messagesTable = $wpdb->prefix . 'woo_ai_messages';
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $messagesTable)) === $messagesTable) {
                $deletedMessages = $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$messagesTable} WHERE created_at < %s",
                    $cutoffDate
                ));

                if ($deletedMessages !== false) {
                    Utils::logDebug("Cleaned up {$deletedMessages} old messages");
                }
            }

            // Clean up cache entries
            $this->clearOldCacheEntries();

            /**
             * Data cleanup completed action
             *
             * @since 1.0.0
             * @param int $retentionDays Data retention period
             * @param string $cutoffDate Cutoff date for cleanup
             */
            do_action('woo_ai_assistant_data_cleanup_completed', $this->dataRetentionDays, $cutoffDate);

            Utils::logDebug("Data cleanup completed for data older than {$cutoffDate}");
        } catch (\Exception $e) {
            Utils::logError('Error during data cleanup: ' . $e->getMessage());
        }
    }

    /**
     * Register data exporter for WordPress privacy tools
     *
     * @since 1.0.0
     * @param array $exporters Current exporters
     * @return array Modified exporters array
     */
    public function registerDataExporter(array $exporters): array
    {
        $exporters['woo-ai-assistant'] = [
            'exporter_friendly_name' => __('Woo AI Assistant Chat Data', 'woo-ai-assistant'),
            'callback' => [$this, 'exportUserData']
        ];

        return $exporters;
    }

    /**
     * Register data eraser for WordPress privacy tools
     *
     * @since 1.0.0
     * @param array $erasers Current erasers
     * @return array Modified erasers array
     */
    public function registerDataEraser(array $erasers): array
    {
        $erasers['woo-ai-assistant'] = [
            'eraser_friendly_name' => __('Woo AI Assistant Chat Data', 'woo-ai-assistant'),
            'callback' => [$this, 'eraseUserData']
        ];

        return $erasers;
    }

    /**
     * Export user data for privacy requests
     *
     * @since 1.0.0
     * @param string $emailAddress User email address
     * @param int $page Page number for pagination
     * @return array Export data response
     */
    public function exportUserData(string $emailAddress, int $page = 1): array
    {
        try {
            global $wpdb;

            if (!function_exists('get_user_by')) {
                return [
                    'data' => [],
                    'done' => true
                ];
            }

            $user = get_user_by('email', $emailAddress);
            if (!$user) {
                return [
                    'data' => [],
                    'done' => true
                ];
            }

            $data = [];
            $conversationsTable = $wpdb->prefix . 'woo_ai_conversations';
            $messagesTable = $wpdb->prefix . 'woo_ai_messages';

            // Get user conversations
            $conversations = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$conversationsTable} WHERE user_id = %d ORDER BY created_at DESC",
                $user->ID
            ));

            foreach ($conversations as $conversation) {
                $conversationData = [
                    'group_id' => 'woo-ai-assistant-conversations',
                    'group_label' => __('AI Assistant Conversations', 'woo-ai-assistant'),
                    'item_id' => 'conversation-' . $conversation->conversation_id,
                    'data' => [
                        [
                            'name' => __('Conversation ID', 'woo-ai-assistant'),
                            'value' => $conversation->conversation_id
                        ],
                        [
                            'name' => __('Created', 'woo-ai-assistant'),
                            'value' => $conversation->created_at
                        ],
                        [
                            'name' => __('Status', 'woo-ai-assistant'),
                            'value' => $conversation->status ?? 'active'
                        ]
                    ]
                ];

                // Get messages for this conversation
                $messages = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$messagesTable} WHERE conversation_id = %s ORDER BY created_at ASC",
                    $conversation->conversation_id
                ));

                foreach ($messages as $message) {
                    $conversationData['data'][] = [
                        'name' => __('Message', 'woo-ai-assistant') . ' (' . $message->sender_type . ')',
                        'value' => function_exists('wp_kses_post') ? wp_kses_post($message->message_content) : strip_tags($message->message_content)
                    ];
                }

                $data[] = $conversationData;
            }

            return [
                'data' => $data,
                'done' => true
            ];
        } catch (\Exception $e) {
            Utils::logError('Error exporting user data: ' . $e->getMessage());
            return [
                'data' => [],
                'done' => true
            ];
        }
    }

    /**
     * Erase user data for privacy requests
     *
     * @since 1.0.0
     * @param string $emailAddress User email address
     * @param int $page Page number for pagination
     * @return array Erasure response
     */
    public function eraseUserData(string $emailAddress, int $page = 1): array
    {
        try {
            global $wpdb;

            if (!function_exists('get_user_by')) {
                return [
                    'items_removed' => 0,
                    'items_retained' => 0,
                    'messages' => [],
                    'done' => true
                ];
            }

            $user = get_user_by('email', $emailAddress);
            if (!$user) {
                return [
                    'items_removed' => 0,
                    'items_retained' => 0,
                    'messages' => [],
                    'done' => true
                ];
            }

            $itemsRemoved = 0;
            $conversationsTable = $wpdb->prefix . 'woo_ai_conversations';
            $messagesTable = $wpdb->prefix . 'woo_ai_messages';

            // Get conversation IDs to delete messages
            $conversationIds = $wpdb->get_col($wpdb->prepare(
                "SELECT conversation_id FROM {$conversationsTable} WHERE user_id = %d",
                $user->ID
            ));

            // Delete messages
            foreach ($conversationIds as $conversationId) {
                $deletedMessages = $wpdb->delete(
                    $messagesTable,
                    ['conversation_id' => $conversationId],
                    ['%s']
                );

                if ($deletedMessages !== false) {
                    $itemsRemoved += $deletedMessages;
                }
            }

            // Delete conversations
            $deletedConversations = $wpdb->delete(
                $conversationsTable,
                ['user_id' => $user->ID],
                ['%d']
            );

            if ($deletedConversations !== false) {
                $itemsRemoved += $deletedConversations;
            }

            // Clear user-specific caches
            $this->clearUserCache($user->ID);

            $messages = [];
            if ($itemsRemoved > 0) {
                $messages[] = sprintf(
                    __('Removed %d AI Assistant conversation items.', 'woo-ai-assistant'),
                    $itemsRemoved
                );
            }

            return [
                'items_removed' => $itemsRemoved,
                'items_retained' => 0,
                'messages' => $messages,
                'done' => true
            ];
        } catch (\Exception $e) {
            Utils::logError('Error erasing user data: ' . $e->getMessage());
            return [
                'items_removed' => 0,
                'items_retained' => 0,
                'messages' => [__('Error occurred during data erasure.', 'woo-ai-assistant')],
                'done' => true
            ];
        }
    }

    /**
     * Register privacy settings
     *
     * @since 1.0.0
     * @return void
     */
    public function registerPrivacySettings(): void
    {
        // Add privacy policy content
        if (function_exists('wp_add_privacy_policy_content')) {
            wp_add_privacy_policy_content(
                __('Woo AI Assistant', 'woo-ai-assistant'),
                $this->getPrivacyPolicyContent()
            );
        }
    }

    /**
     * Get privacy policy content
     *
     * @since 1.0.0
     * @return string Privacy policy content
     */
    private function getPrivacyPolicyContent(): string
    {
        $content = '<h3>' . __('AI Assistant Chat Data', 'woo-ai-assistant') . '</h3>';
        $content .= '<p>' . __('When you use our AI-powered chat assistant, we may collect and process the following information:', 'woo-ai-assistant') . '</p>';
        $content .= '<ul>';
        $content .= '<li>' . __('Chat messages and conversation history', 'woo-ai-assistant') . '</li>';
        $content .= '<li>' . __('User preferences and settings', 'woo-ai-assistant') . '</li>';
        $content .= '<li>' . __('Technical information (browser, IP address) for functionality', 'woo-ai-assistant') . '</li>';
        $content .= '</ul>';

        $content .= '<h4>' . __('Data Retention', 'woo-ai-assistant') . '</h4>';
        $content .= '<p>' . sprintf(
            __('Chat data is automatically deleted after %d days. You can request immediate deletion of your data at any time.', 'woo-ai-assistant'),
            $this->dataRetentionDays
        ) . '</p>';

        $content .= '<h4>' . __('GDPR Compliance', 'woo-ai-assistant') . '</h4>';
        if ($this->detectedPlugin) {
            $content .= '<p>' . sprintf(
                __('This site uses %s for cookie consent management. Chat functionality respects your consent preferences.', 'woo-ai-assistant'),
                $this->supportedPlugins[$this->detectedPlugin]
            ) . '</p>';
        } else {
            $content .= '<p>' . __('No cookie consent plugin detected. Chat operates in minimal mode by default to ensure privacy compliance.', 'woo-ai-assistant') . '</p>';
        }

        return $content;
    }

    /**
     * Clear consent cache
     *
     * @since 1.0.0
     * @return void
     */
    private function clearConsentCache(): void
    {
        $this->consentCache = [];
        wp_cache_flush_group('woo_ai_assistant_gdpr');

        /**
         * Consent cache cleared action
         *
         * @since 1.0.0
         */
        do_action('woo_ai_assistant_consent_cache_cleared');
    }

    /**
     * Clear user-specific cache
     *
     * @since 1.0.0
     * @param int $userId User ID
     * @return void
     */
    private function clearUserCache(int $userId): void
    {
        $cacheKeys = [
            'consent_status_' . $this->detectedPlugin . '_user_' . $userId,
            'user_data_export_' . $userId,
            'user_conversations_' . $userId
        ];

        foreach ($cacheKeys as $key) {
            wp_cache_delete($key, 'woo_ai_assistant_gdpr');
        }
    }

    /**
     * Clear old cache entries
     *
     * @since 1.0.0
     * @return void
     */
    private function clearOldCacheEntries(): void
    {
        // WordPress doesn't provide a direct way to clear old cache entries
        // This is a placeholder for cache cleanup logic
        wp_cache_flush_group('woo_ai_assistant_gdpr');
    }

    /**
     * Get GDPR plugin info
     *
     * @since 1.0.0
     * @return array GDPR plugin information
     */
    public function getPluginInfo(): array
    {
        return [
            'detected' => $this->detectedPlugin,
            'name' => $this->supportedPlugins[$this->detectedPlugin] ?? 'None',
            'is_active' => $this->detectedPlugin !== null,
            'consent_given' => $this->consentGiven,
            'minimal_mode' => $this->minimalMode,
            'data_retention_days' => $this->dataRetentionDays,
            'supported_plugins' => $this->supportedPlugins
        ];
    }

    /**
     * Force consent check
     *
     * Forces a fresh consent check, bypassing cache.
     *
     * @since 1.0.0
     * @return bool Current consent status
     */
    public function forceConsentCheck(): bool
    {
        $this->clearConsentCache();
        return $this->checkConsentStatus();
    }

    /**
     * Set minimal mode (for testing/admin override)
     *
     * @since 1.0.0
     * @param bool $enable Whether to enable minimal mode
     * @return void
     */
    public function setMinimalMode(bool $enable): void
    {
        $this->minimalMode = $enable;
        $this->consentGiven = !$enable;
        $this->applyConsentStatus();

        Utils::logDebug('Minimal mode ' . ($enable ? 'enabled' : 'disabled') . ' via admin override');
    }

    /**
     * Check if GDPR compliance is active
     *
     * @since 1.0.0
     * @return bool True if a GDPR plugin is detected and active
     */
    public function isGdprActive(): bool
    {
        return $this->detectedPlugin !== null;
    }
}
