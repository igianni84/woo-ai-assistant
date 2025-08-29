<?php

/**
 * Development Configuration Manager Class
 *
 * Handles development-specific configuration management including API keys,
 * feature flags, mock data settings, and license bypassing for development
 * and testing environments.
 *
 * @package WooAiAssistant
 * @subpackage Common
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Common;

use WooAiAssistant\Common\Traits\Singleton;
use WooAiAssistant\Common\Utils;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class DevelopmentConfig
 *
 * Provides development-specific configuration management with secure loading
 * of environment variables, feature flags, and development-only settings.
 * Automatically bypasses license validation in development mode.
 *
 * @since 1.0.0
 */
class DevelopmentConfig
{
    use Singleton;

    /**
     * Development environment file path
     *
     * @since 1.0.0
     * @var string
     */
    private const DEV_ENV_FILE = '.env.development';

    /**
     * Cached development configuration
     *
     * @since 1.0.0
     * @var array|null
     */
    private ?array $developmentConfig = null;

    /**
     * Environment variables cache
     *
     * @since 1.0.0
     * @var array
     */
    private array $envCache = [];

    /**
     * Development mode status cache
     *
     * @since 1.0.0
     * @var bool|null
     */
    private ?bool $isDevelopmentMode = null;

    /**
     * Constructor
     *
     * Initializes development configuration by loading environment variables
     * and setting up development-specific behaviors.
     *
     * @since 1.0.0
     */
    private function __construct()
    {
        $this->loadDevelopmentEnvironment();
        $this->setupDevelopmentHooks();

        if ($this->isDevelopmentMode()) {
            $this->initializeDevelopmentMode();
        }
    }

    /**
     * Check if development mode is enabled
     *
     * @since 1.0.0
     * @return bool True if development mode is enabled
     */
    public function isDevelopmentMode(): bool
    {
        if ($this->isDevelopmentMode !== null) {
            return $this->isDevelopmentMode;
        }

        // Check multiple sources for development mode flag
        $this->isDevelopmentMode =
            // Environment variable (highest priority)
            $this->getEnvironmentVariable('WOO_AI_DEVELOPMENT_MODE') === 'true' ||
            // WordPress debug constant
            (defined('WP_DEBUG') && WP_DEBUG) ||
            // Plugin debug constant
            (defined('WOO_AI_ASSISTANT_DEBUG') && WOO_AI_ASSISTANT_DEBUG) ||
            // Local development detection
            $this->detectLocalDevelopment();

        Utils::logDebug('Development mode status determined', [
            'is_development' => $this->isDevelopmentMode,
            'wp_debug' => defined('WP_DEBUG') ? WP_DEBUG : false,
            'plugin_debug' => defined('WOO_AI_ASSISTANT_DEBUG') ? WOO_AI_ASSISTANT_DEBUG : false
        ]);

        return $this->isDevelopmentMode;
    }

    /**
     * Get development API key for specified service
     *
     * @since 1.0.0
     * @param string $service Service name (openrouter, openai, pinecone, google)
     * @return string API key or empty string if not found
     */
    public function getApiKey(string $service): string
    {
        if (!$this->isDevelopmentMode()) {
            return '';
        }

        $envVar = strtoupper($service) . '_API_KEY';
        $apiKey = $this->getEnvironmentVariable($envVar);

        if (!empty($apiKey)) {
            Utils::logDebug("Development API key found for service: {$service}");
            return $apiKey;
        }

        return '';
    }

    /**
     * Get development license key
     *
     * @since 1.0.0
     * @return string Development license key
     */
    public function getDevelopmentLicenseKey(): string
    {
        if (!$this->isDevelopmentMode()) {
            return '';
        }

        return $this->getEnvironmentVariable('WOO_AI_DEVELOPMENT_LICENSE_KEY', 'dev-license-bypass');
    }

    /**
     * Check if license validation should be bypassed in development
     *
     * @since 1.0.0
     * @return bool True if license validation should be bypassed
     */
    public function shouldBypassLicenseValidation(): bool
    {
        return $this->isDevelopmentMode();
    }

    /**
     * Get Pinecone development configuration
     *
     * @since 1.0.0
     * @return array Pinecone configuration for development
     */
    public function getPineconeConfig(): array
    {
        if (!$this->isDevelopmentMode()) {
            return [];
        }

        return [
            'api_key' => $this->getApiKey('pinecone'),
            'environment' => $this->getEnvironmentVariable('PINECONE_ENVIRONMENT', 'development'),
            'index_name' => $this->getEnvironmentVariable('PINECONE_INDEX_NAME', 'woo-ai-assistant-dev'),
        ];
    }

    /**
     * Get OpenAI development configuration
     *
     * @since 1.0.0
     * @return array OpenAI configuration for development
     */
    public function getOpenAiConfig(): array
    {
        if (!$this->isDevelopmentMode()) {
            return [];
        }

        return [
            'api_key' => $this->getApiKey('openai'),
            'model' => 'text-embedding-3-small',
            'timeout' => (int) $this->getEnvironmentVariable('WOO_AI_DEV_API_TIMEOUT', '10'),
            'retry_attempts' => 1, // Reduced for development
        ];
    }

    /**
     * Get OpenRouter development configuration
     *
     * @since 1.0.0
     * @return array OpenRouter configuration for development
     */
    public function getOpenRouterConfig(): array
    {
        if (!$this->isDevelopmentMode()) {
            return [];
        }

        return [
            'api_key' => $this->getApiKey('openrouter'),
            'timeout' => (int) $this->getEnvironmentVariable('WOO_AI_DEV_API_TIMEOUT', '10'),
            'retry_attempts' => 1, // Reduced for development
        ];
    }

    /**
     * Get Google API development configuration
     *
     * @since 1.0.0
     * @return array Google API configuration for development
     */
    public function getGoogleConfig(): array
    {
        if (!$this->isDevelopmentMode()) {
            return [];
        }

        return [
            'api_key' => $this->getApiKey('google'),
            'timeout' => (int) $this->getEnvironmentVariable('WOO_AI_DEV_API_TIMEOUT', '10'),
            'retry_attempts' => 1, // Reduced for development
        ];
    }

    /**
     * Get development server configuration
     *
     * @since 1.0.0
     * @return array Server configuration for development
     */
    public function getServerConfig(): array
    {
        if (!$this->isDevelopmentMode()) {
            return [];
        }

        return [
            'url' => $this->getEnvironmentVariable('WOO_AI_DEVELOPMENT_SERVER_URL', 'http://localhost:3000'),
            'timeout' => (int) $this->getEnvironmentVariable('WOO_AI_DEV_API_TIMEOUT', '10'),
            'retry_attempts' => 1, // Reduced for development
        ];
    }

    /**
     * Check if dummy data should be used
     *
     * @since 1.0.0
     * @return bool True if dummy data should be used
     */
    public function shouldUseDummyData(): bool
    {
        return $this->isDevelopmentMode() &&
               $this->getEnvironmentVariable('WOO_AI_USE_DUMMY_DATA') === 'true';
    }

    /**
     * Check if API calls should be mocked
     *
     * @since 1.0.0
     * @return bool True if API calls should be mocked
     */
    public function shouldMockApiCalls(): bool
    {
        return $this->isDevelopmentMode() &&
               $this->getEnvironmentVariable('WOO_AI_MOCK_API_CALLS') === 'true';
    }

    /**
     * Check if enhanced debug logging is enabled
     *
     * @since 1.0.0
     * @return bool True if enhanced debug logging is enabled
     */
    public function isEnhancedDebugEnabled(): bool
    {
        return $this->isDevelopmentMode() &&
               $this->getEnvironmentVariable('WOO_AI_ENHANCED_DEBUG') === 'true';
    }

    /**
     * Get development cache TTL
     *
     * @since 1.0.0
     * @return int Cache TTL in seconds
     */
    public function getCacheTtl(): int
    {
        if (!$this->isDevelopmentMode()) {
            return HOUR_IN_SECONDS; // Default production TTL
        }

        return (int) $this->getEnvironmentVariable('WOO_AI_DEV_CACHE_TTL', '60');
    }

    /**
     * Get maximum items to process in development
     *
     * @since 1.0.0
     * @return int Maximum items limit
     */
    public function getMaxItemsLimit(): int
    {
        if (!$this->isDevelopmentMode()) {
            return 1000; // Default production limit
        }

        return (int) $this->getEnvironmentVariable('WOO_AI_DEV_MAX_ITEMS', '10');
    }

    /**
     * Get development conversation limit
     *
     * @since 1.0.0
     * @return int Conversation limit for development
     */
    public function getConversationLimit(): int
    {
        if (!$this->isDevelopmentMode()) {
            return 100; // Default production limit
        }

        return (int) $this->getEnvironmentVariable('WOO_AI_DEV_CONVERSATION_LIMIT', '5');
    }

    /**
     * Check if analytics should be disabled
     *
     * @since 1.0.0
     * @return bool True if analytics should be disabled
     */
    public function shouldDisableAnalytics(): bool
    {
        return $this->isDevelopmentMode() &&
               $this->getEnvironmentVariable('WOO_AI_DISABLE_ANALYTICS') === 'true';
    }

    /**
     * Check if test data seeding is enabled
     *
     * @since 1.0.0
     * @return bool True if test data seeding is enabled
     */
    public function isTestDataEnabled(): bool
    {
        return $this->isDevelopmentMode() &&
               $this->getEnvironmentVariable('WOO_AI_ENABLE_TEST_DATA') === 'true';
    }

    /**
     * Get all development configuration
     *
     * @since 1.0.0
     * @return array Complete development configuration
     */
    public function getDevelopmentConfig(): array
    {
        if ($this->developmentConfig !== null) {
            return $this->developmentConfig;
        }

        $this->developmentConfig = [
            'is_development_mode' => $this->isDevelopmentMode(),
            'api_keys' => [
                'openrouter' => !empty($this->getApiKey('openrouter')),
                'openai' => !empty($this->getApiKey('openai')),
                'pinecone' => !empty($this->getApiKey('pinecone')),
                'google' => !empty($this->getApiKey('google')),
            ],
            'features' => [
                'use_dummy_data' => $this->shouldUseDummyData(),
                'mock_api_calls' => $this->shouldMockApiCalls(),
                'enhanced_debug' => $this->isEnhancedDebugEnabled(),
                'disable_analytics' => $this->shouldDisableAnalytics(),
                'test_data_enabled' => $this->isTestDataEnabled(),
            ],
            'limits' => [
                'cache_ttl' => $this->getCacheTtl(),
                'max_items' => $this->getMaxItemsLimit(),
                'conversations' => $this->getConversationLimit(),
            ],
            'license' => [
                'bypass_validation' => $this->shouldBypassLicenseValidation(),
                'development_key' => $this->getDevelopmentLicenseKey(),
            ]
        ];

        return $this->developmentConfig;
    }

    /**
     * Load development environment variables
     *
     * @since 1.0.0
     * @return void
     */
    private function loadDevelopmentEnvironment(): void
    {
        if (!defined('WOO_AI_ASSISTANT_PLUGIN_DIR_PATH')) {
            return;
        }

        $envFile = WOO_AI_ASSISTANT_PLUGIN_DIR_PATH . self::DEV_ENV_FILE;

        if (!file_exists($envFile) || !is_readable($envFile)) {
            Utils::logDebug('Development environment file not found: ' . $envFile);
            return;
        }

        try {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            foreach ($lines as $lineNumber => $line) {
                $line = trim($line);

                // Skip comments and empty lines
                if (empty($line) || strpos($line, '#') === 0) {
                    continue;
                }

                // Parse KEY=VALUE pairs
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);

                    // Remove quotes if present
                    if (
                        (substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                        (substr($value, 0, 1) === "'" && substr($value, -1) === "'")
                    ) {
                        $value = substr($value, 1, -1);
                    }

                    $this->envCache[$key] = $value;
                }
            }

            Utils::logDebug('Development environment loaded', [
                'file' => $envFile,
                'variables_count' => count($this->envCache)
            ]);
        } catch (\Exception $e) {
            Utils::logError('Failed to load development environment: ' . $e->getMessage());
        }
    }

    /**
     * Get environment variable value
     *
     * @since 1.0.0
     * @param string $key Environment variable key
     * @param string $default Default value if not found
     * @return string Environment variable value
     */
    private function getEnvironmentVariable(string $key, string $default = ''): string
    {
        // Check cached environment variables first
        if (isset($this->envCache[$key])) {
            return $this->envCache[$key];
        }

        // Check defined constants
        if (defined($key)) {
            return (string) constant($key);
        }

        // Check $_ENV superglobal
        if (!empty($_ENV[$key])) {
            return $_ENV[$key];
        }

        // Check getenv() function
        $envValue = getenv($key);
        if ($envValue !== false) {
            return $envValue;
        }

        return $default;
    }

    /**
     * Detect if running in local development environment
     *
     * @since 1.0.0
     * @return bool True if local development detected
     */
    private function detectLocalDevelopment(): bool
    {
        // Check for common local development indicators
        $localIndicators = [
            'localhost',
            '127.0.0.1',
            '.local',
            '.dev',
            'staging',
            'development',
            'test'
        ];

        if (function_exists('get_site_url')) {
            $siteUrl = get_site_url();

            foreach ($localIndicators as $indicator) {
                if (strpos($siteUrl, $indicator) !== false) {
                    return true;
                }
            }
        }

        // Check server name
        if (isset($_SERVER['SERVER_NAME'])) {
            foreach ($localIndicators as $indicator) {
                if (strpos($_SERVER['SERVER_NAME'], $indicator) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Setup development-specific WordPress hooks
     *
     * @since 1.0.0
     * @return void
     */
    private function setupDevelopmentHooks(): void
    {
        // Only register hooks if WordPress functions are available
        if (!function_exists('add_filter') || !function_exists('add_action')) {
            return;
        }

        if (!$this->isDevelopmentMode()) {
            return;
        }

        // Override license validation in development
        add_filter('woo_ai_assistant_bypass_license_validation', '__return_true');
        add_filter('woo_ai_assistant_development_license_key', [$this, 'getDevelopmentLicenseKey']);

        // Development-specific logging
        add_action('woo_ai_assistant_log_debug', [$this, 'handleDevelopmentLogging'], 10, 2);

        // Development admin notices
        add_action('admin_notices', [$this, 'displayDevelopmentNotices']);

        Utils::logDebug('Development hooks registered');
    }

    /**
     * Initialize development mode specific settings
     *
     * @since 1.0.0
     * @return void
     */
    private function initializeDevelopmentMode(): void
    {
        // Define development constants if not already defined
        if (!defined('WOO_AI_ASSISTANT_DEBUG')) {
            define('WOO_AI_ASSISTANT_DEBUG', true);
        }

        if (!defined('WOO_AI_ASSISTANT_USE_DUMMY_DATA')) {
            define('WOO_AI_ASSISTANT_USE_DUMMY_DATA', $this->shouldUseDummyData());
        }

        // Set development-specific ini settings
        if ($this->isEnhancedDebugEnabled()) {
            ini_set('display_errors', 1);
            ini_set('log_errors', 1);
        }

        Utils::logDebug('Development mode initialized', [
            'config_loaded' => !empty($this->envCache),
            'dummy_data' => $this->shouldUseDummyData(),
            'enhanced_debug' => $this->isEnhancedDebugEnabled()
        ]);
    }

    /**
     * Handle development-specific logging
     *
     * @since 1.0.0
     * @param string $message Log message
     * @param array $context Log context
     * @return void
     */
    public function handleDevelopmentLogging(string $message, array $context = []): void
    {
        if (!$this->isEnhancedDebugEnabled()) {
            return;
        }

        $logMessage = '[WOO-AI-DEV] ' . $message;

        if (!empty($context)) {
            $logMessage .= ' | Context: ' . wp_json_encode($context);
        }

        error_log($logMessage);
    }

    /**
     * Display development mode notices in admin
     *
     * @since 1.0.0
     * @return void
     */
    public function displayDevelopmentNotices(): void
    {
        // Check if WordPress functions are available
        if (!function_exists('current_user_can') || !function_exists('get_current_screen')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'woo-ai-assistant') === false) {
            return;
        }

        printf(
            '<div class="notice notice-info is-dismissible" style="border-left-color: #ff6600;">
                <p><strong>%s</strong></p>
                <p>%s</p>
            </div>',
            esc_html__('Woo AI Assistant - Development Mode Active', 'woo-ai-assistant'),
            esc_html__('Development configuration is loaded. License validation is bypassed and development API keys are in use.', 'woo-ai-assistant')
        );
    }

    /**
     * Clear development configuration cache
     *
     * @since 1.0.0
     * @return void
     */
    public function clearCache(): void
    {
        $this->developmentConfig = null;
        $this->isDevelopmentMode = null;

        Utils::logDebug('Development configuration cache cleared');
    }

    /**
     * Export development configuration for debugging
     *
     * @since 1.0.0
     * @return array Sanitized development configuration (without sensitive data)
     */
    public function exportConfigForDebug(): array
    {
        $config = $this->getDevelopmentConfig();

        // Remove sensitive information
        $config['api_keys'] = array_map(function ($hasKey) {
            return $hasKey ? 'configured' : 'not_configured';
        }, $config['api_keys']);

        $config['license']['development_key'] = !empty($config['license']['development_key']) ? 'configured' : 'not_configured';

        return $config;
    }
}
