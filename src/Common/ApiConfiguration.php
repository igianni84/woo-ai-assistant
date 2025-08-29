<?php

/**
 * API Configuration Manager Class
 *
 * Provides centralized management of all API keys and configurations.
 * Handles storage, retrieval, validation, and environment fallbacks
 * for all external API services used by the plugin.
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
use WooAiAssistant\Common\DevelopmentConfig;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ApiConfiguration
 *
 * Centralized API configuration management for all external services.
 * Provides secure storage, retrieval, and validation of API keys with
 * support for development fallbacks and environment detection.
 *
 * @since 1.0.0
 */
class ApiConfiguration
{
    use Singleton;

    /**
     * Settings option name in WordPress database
     *
     * @since 1.0.0
     * @var string
     */
    private const SETTINGS_OPTION = 'woo_ai_assistant_settings';

    /**
     * Legacy option names for backward compatibility
     *
     * @since 1.0.0
     * @var array
     */
    private const LEGACY_OPTIONS = [
        'openrouter' => 'woo_ai_assistant_openrouter_key',
        'google' => 'woo_ai_assistant_gemini_key',
        'openai' => 'woo_ai_assistant_openai_key',
        'pinecone' => 'woo_ai_assistant_pinecone_key',
    ];

    /**
     * Cached settings array
     *
     * @since 1.0.0
     * @var array|null
     */
    private ?array $settings = null;

    /**
     * Environment variables cache
     *
     * @since 1.0.0
     * @var array
     */
    private array $envCache = [];

    /**
     * Development configuration instance (lazy loaded)
     *
     * @since 1.0.0
     * @var DevelopmentConfig|null
     */
    private ?DevelopmentConfig $developmentConfig = null;

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    private function __construct()
    {
        $this->loadEnvironmentVariables();
        // DevelopmentConfig is now lazy loaded to prevent circular references
    }

    /**
     * Get DevelopmentConfig instance (lazy loaded)
     *
     * @since 1.0.0
     * @return DevelopmentConfig|null
     */
    private function getDevelopmentConfig(): ?DevelopmentConfig
    {
        if ($this->developmentConfig === null && class_exists('WooAiAssistant\\Common\\DevelopmentConfig')) {
            $this->developmentConfig = DevelopmentConfig::getInstance();
        }
        return $this->developmentConfig;
    }

    /**
     * Get API key for specified service
     *
     * @since 1.0.0
     * @param string $service Service name (openrouter, openai, pinecone, google)
     * @return string API key or empty string if not found
     */
    public function getApiKey(string $service): string
    {
        // Check development configuration first (highest priority in dev mode)
        if ($this->getDevelopmentConfig() && $this->getDevelopmentConfig()->isDevelopmentMode()) {
            $devKey = $this->getDevelopmentConfig()->getApiKey($service);
            if (!empty($devKey)) {
                return $devKey;
            }
        }

        // Check environment variables
        $envKey = $this->getEnvironmentKey($service);
        if (!empty($envKey)) {
            return $envKey;
        }

        // Check main settings
        $settings = $this->getSettings();
        $apiSettings = $settings['api'] ?? [];

        $keyField = $service === 'google' ? 'google_api_key' : $service . '_api_key';
        if (!empty($apiSettings[$keyField])) {
            return $apiSettings[$keyField];
        }

        // Check legacy options for backward compatibility
        if (isset(self::LEGACY_OPTIONS[$service])) {
            $legacyKey = get_option(self::LEGACY_OPTIONS[$service], '');
            if (!empty($legacyKey)) {
                return $legacyKey;
            }
        }

        return '';
    }

    /**
     * Set API key for specified service
     *
     * @since 1.0.0
     * @param string $service Service name
     * @param string $key API key
     * @return bool True on success, false on failure
     */
    public function setApiKey(string $service, string $key): bool
    {
        $settings = $this->getSettings();
        $keyField = $service === 'google' ? 'google_api_key' : $service . '_api_key';

        if (!isset($settings['api'])) {
            $settings['api'] = [];
        }

        $settings['api'][$keyField] = sanitize_text_field($key);

        // Update WordPress option
        $result = update_option(self::SETTINGS_OPTION, $settings);

        if ($result) {
            // Clear cache
            $this->settings = null;

            // Update legacy option for backward compatibility
            if (isset(self::LEGACY_OPTIONS[$service])) {
                update_option(self::LEGACY_OPTIONS[$service], $key);
            }
        }

        return $result;
    }

    /**
     * Get Pinecone configuration
     *
     * @since 1.0.0
     * @return array Pinecone configuration array
     */
    public function getPineconeConfig(): array
    {
        // Check development configuration first
        if ($this->getDevelopmentConfig() && $this->getDevelopmentConfig()->isDevelopmentMode()) {
            $devConfig = $this->getDevelopmentConfig()->getPineconeConfig();
            if (!empty($devConfig)) {
                return $devConfig;
            }
        }

        $settings = $this->getSettings();
        $apiSettings = $settings['api'] ?? [];

        return [
            'api_key' => $this->getApiKey('pinecone'),
            'environment' => $apiSettings['pinecone_environment'] ?? '',
            'index_name' => $apiSettings['pinecone_index_name'] ?? 'woo-ai-assistant',
        ];
    }

    /**
     * Get OpenAI configuration
     *
     * @since 1.0.0
     * @return array OpenAI configuration array
     */
    public function getOpenAiConfig(): array
    {
        // Check development configuration first
        if ($this->getDevelopmentConfig() && $this->getDevelopmentConfig()->isDevelopmentMode()) {
            $devConfig = $this->getDevelopmentConfig()->getOpenAiConfig();
            if (!empty($devConfig)) {
                return $devConfig;
            }
        }

        $settings = $this->getSettings();
        $apiSettings = $settings['api'] ?? [];

        return [
            'api_key' => $this->getApiKey('openai'),
            'model' => 'text-embedding-3-small',
            'timeout' => absint($apiSettings['timeout_seconds'] ?? 30),
            'retry_attempts' => absint($apiSettings['retry_attempts'] ?? 3),
        ];
    }

    /**
     * Get OpenRouter configuration
     *
     * @since 1.0.0
     * @return array OpenRouter configuration array
     */
    public function getOpenRouterConfig(): array
    {
        // Check development configuration first
        if ($this->getDevelopmentConfig() && $this->getDevelopmentConfig()->isDevelopmentMode()) {
            $devConfig = $this->getDevelopmentConfig()->getOpenRouterConfig();
            if (!empty($devConfig)) {
                return $devConfig;
            }
        }

        $settings = $this->getSettings();
        $apiSettings = $settings['api'] ?? [];

        return [
            'api_key' => $this->getApiKey('openrouter'),
            'timeout' => absint($apiSettings['timeout_seconds'] ?? 30),
            'retry_attempts' => absint($apiSettings['retry_attempts'] ?? 3),
        ];
    }

    /**
     * Get Google API configuration
     *
     * @since 1.0.0
     * @return array Google API configuration array
     */
    public function getGoogleConfig(): array
    {
        // Check development configuration first
        if ($this->getDevelopmentConfig() && $this->getDevelopmentConfig()->isDevelopmentMode()) {
            $devConfig = $this->getDevelopmentConfig()->getGoogleConfig();
            if (!empty($devConfig)) {
                return $devConfig;
            }
        }

        $settings = $this->getSettings();
        $apiSettings = $settings['api'] ?? [];

        return [
            'api_key' => $this->getApiKey('google'),
            'timeout' => absint($apiSettings['timeout_seconds'] ?? 30),
            'retry_attempts' => absint($apiSettings['retry_attempts'] ?? 3),
        ];
    }

    /**
     * Get intermediate server configuration
     *
     * @since 1.0.0
     * @return array Server configuration array
     */
    public function getServerConfig(): array
    {
        // Check development configuration first
        if ($this->getDevelopmentConfig() && $this->getDevelopmentConfig()->isDevelopmentMode()) {
            $devConfig = $this->getDevelopmentConfig()->getServerConfig();
            if (!empty($devConfig)) {
                return $devConfig;
            }
        }

        $settings = $this->getSettings();
        $apiSettings = $settings['api'] ?? [];

        return [
            'url' => $apiSettings['intermediate_server_url'] ?? 'https://api.wooaiassistant.com',
            'timeout' => absint($apiSettings['timeout_seconds'] ?? 30),
            'retry_attempts' => absint($apiSettings['retry_attempts'] ?? 3),
        ];
    }

    /**
     * Check if development fallbacks are enabled
     *
     * @since 1.0.0
     * @return bool True if development fallbacks are enabled
     */
    public function isDevelopmentMode(): bool
    {
        // Use DevelopmentConfig for authoritative development mode detection
        $devConfig = $this->getDevelopmentConfig();
        if ($devConfig) {
            return $devConfig->isDevelopmentMode();
        }

        // Fallback check for environment variable
        if (defined('WOO_AI_DEVELOPMENT_MODE')) {
            return (bool) WOO_AI_DEVELOPMENT_MODE;
        }

        // Check plugin settings
        $settings = $this->getSettings();
        $apiSettings = $settings['api'] ?? [];

        return !empty($apiSettings['use_development_fallbacks']);
    }

    /**
     * Check if debug mode is enabled
     *
     * @since 1.0.0
     * @return bool True if debug mode is enabled
     */
    public function isDebugMode(): bool
    {
        // Check WordPress debug constant first
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return true;
        }

        // Check plugin settings
        $settings = $this->getSettings();
        $apiSettings = $settings['api'] ?? [];

        return !empty($apiSettings['enable_debug_mode']);
    }

    /**
     * Get API status for all configured services
     *
     * @since 1.0.0
     * @return array Status array for all services
     */
    public function getApiStatus(): array
    {
        $services = ['openrouter', 'openai', 'pinecone', 'google'];
        $status = [];

        foreach ($services as $service) {
            $key = $this->getApiKey($service);
            $status[$service] = [
                'configured' => !empty($key),
                'key_length' => !empty($key) ? strlen($key) : 0,
                'source' => $this->getApiKeySource($service),
            ];
        }

        return $status;
    }

    /**
     * Validate if all required API keys are configured
     *
     * @since 1.0.0
     * @param array $requiredServices Required service names
     * @return array Validation result
     */
    public function validateRequiredKeys(array $requiredServices = ['openai']): array
    {
        $missing = [];
        $configured = [];

        foreach ($requiredServices as $service) {
            $key = $this->getApiKey($service);
            if (empty($key)) {
                $missing[] = $service;
            } else {
                $configured[] = $service;
            }
        }

        return [
            'valid' => empty($missing),
            'missing' => $missing,
            'configured' => $configured,
            'development_mode' => $this->isDevelopmentMode(),
        ];
    }

    /**
     * Get source of API key (environment, settings, legacy, or none)
     *
     * @since 1.0.0
     * @param string $service Service name
     * @return string Source of the API key
     */
    private function getApiKeySource(string $service): string
    {
        // Check environment first
        if (!empty($this->getEnvironmentKey($service))) {
            return 'environment';
        }

        // Check main settings
        $settings = $this->getSettings();
        $apiSettings = $settings['api'] ?? [];
        $keyField = $service === 'google' ? 'google_api_key' : $service . '_api_key';

        if (!empty($apiSettings[$keyField])) {
            return 'settings';
        }

        // Check legacy options
        if (isset(self::LEGACY_OPTIONS[$service])) {
            $legacyKey = get_option(self::LEGACY_OPTIONS[$service], '');
            if (!empty($legacyKey)) {
                return 'legacy';
            }
        }

        return 'none';
    }

    /**
     * Get API key from environment variables
     *
     * @since 1.0.0
     * @param string $service Service name
     * @return string Environment API key or empty string
     */
    private function getEnvironmentKey(string $service): string
    {
        $envVar = strtoupper($service) . '_API_KEY';

        // Check cached environment variables first
        if (isset($this->envCache[$envVar])) {
            return $this->envCache[$envVar];
        }

        // Check defined constants
        if (defined($envVar)) {
            return constant($envVar);
        }

        // Check $_ENV superglobal
        if (!empty($_ENV[$envVar])) {
            return $_ENV[$envVar];
        }

        // Check getenv() function
        $envValue = getenv($envVar);
        if ($envValue !== false) {
            return $envValue;
        }

        return '';
    }

    /**
     * Load environment variables from .env file if it exists
     *
     * @since 1.0.0
     * @return void
     */
    private function loadEnvironmentVariables(): void
    {
        // Skip environment loading if WordPress constants aren't available
        if (!defined('WP_CONTENT_DIR')) {
            return;
        }

        $envFile = \WP_CONTENT_DIR . '/plugins/woo-ai-assistant/.env';

        if (!file_exists($envFile) || !is_readable($envFile)) {
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
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
    }

    /**
     * Get all plugin settings
     *
     * @since 1.0.0
     * @return array Plugin settings array
     */
    private function getSettings(): array
    {
        if ($this->settings === null) {
            $this->settings = get_option(self::SETTINGS_OPTION, []);
        }

        return $this->settings;
    }

    /**
     * Check if license validation should be bypassed in development
     *
     * @since 1.0.0
     * @return bool True if license validation should be bypassed
     */
    public function shouldBypassLicenseValidation(): bool
    {
        if ($this->getDevelopmentConfig() && $this->getDevelopmentConfig()->isDevelopmentMode()) {
            return $this->getDevelopmentConfig()->shouldBypassLicenseValidation();
        }

        return false;
    }

    /**
     * Get development license key for testing
     *
     * @since 1.0.0
     * @return string Development license key or empty string
     */
    public function getDevelopmentLicenseKey(): string
    {
        if ($this->getDevelopmentConfig() && $this->getDevelopmentConfig()->isDevelopmentMode()) {
            return $this->getDevelopmentConfig()->getDevelopmentLicenseKey();
        }

        return '';
    }

    /**
     * Clear settings cache
     *
     * @since 1.0.0
     * @return void
     */
    public function clearCache(): void
    {
        $this->settings = null;

        // Clear development config cache too
        $devConfig = $this->getDevelopmentConfig();
        if ($devConfig) {
            $devConfig->clearCache();
        }
    }

    /**
     * Migrate legacy API keys to new settings structure
     *
     * @since 1.0.0
     * @return bool True if migration was needed and successful
     */
    public function migrateLegacyKeys(): bool
    {
        $migrated = false;
        $settings = $this->getSettings();

        if (!isset($settings['api'])) {
            $settings['api'] = [];
        }

        foreach (self::LEGACY_OPTIONS as $service => $legacyOption) {
            $keyField = $service === 'google' ? 'google_api_key' : $service . '_api_key';

            // Only migrate if new setting is empty and legacy option exists
            if (empty($settings['api'][$keyField])) {
                $legacyKey = get_option($legacyOption, '');
                if (!empty($legacyKey)) {
                    $settings['api'][$keyField] = $legacyKey;
                    $migrated = true;

                    Utils::logDebug("Migrated legacy API key for service: $service");
                }
            }
        }

        if ($migrated) {
            update_option(self::SETTINGS_OPTION, $settings);
            $this->settings = null; // Clear cache
        }

        return $migrated;
    }
}
