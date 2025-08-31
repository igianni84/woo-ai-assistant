<?php

/**
 * API Configuration Class
 *
 * Centralizes API configuration management for all external services.
 * Handles both development and production modes, manages API endpoints,
 * and provides methods to get API keys based on environment.
 *
 * @package WooAiAssistant
 * @subpackage Config
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Config;

use WooAiAssistant\Common\Traits\Singleton;
use WooAiAssistant\Common\Logger;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ApiConfiguration
 *
 * Manages API configuration for all external services.
 *
 * @since 1.0.0
 */
class ApiConfiguration
{
    use Singleton;

    /**
     * Development configuration instance
     *
     * @var DevelopmentConfig
     */
    private DevelopmentConfig $developmentConfig;

    /**
     * API endpoints configuration
     *
     * @var array
     */
    private array $apiEndpoints = [
        'openrouter' => [
            'base_url' => 'https://openrouter.ai/api/v1',
            'chat_completions' => '/chat/completions',
            'models' => '/models'
        ],
        'openai' => [
            'base_url' => 'https://api.openai.com/v1',
            'embeddings' => '/embeddings',
            'chat_completions' => '/chat/completions'
        ],
        'pinecone' => [
            'base_url' => 'https://{index}.pinecone.io',
            'upsert' => '/vectors/upsert',
            'query' => '/vectors/query',
            'delete' => '/vectors/delete'
        ],
        'google' => [
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'generate_content' => '/models/{model}:generateContent',
            'embed_content' => '/models/{model}:embedContent'
        ],
        'stripe' => [
            'base_url' => 'https://api.stripe.com/v1',
            'charges' => '/charges',
            'subscriptions' => '/subscriptions',
            'customers' => '/customers'
        ]
    ];

    /**
     * Default AI models configuration
     *
     * @var array
     */
    private array $defaultModels = [
        'primary_chat' => 'google/gemini-2.0-flash-exp:free',
        'fallback_chat' => 'google/gemini-2.0-flash-thinking-exp:free',
        'premium_chat' => 'google/gemini-2.0-pro',
        'embedding' => 'text-embedding-3-small'
    ];

    /**
     * Configuration cache
     *
     * @var array
     */
    private array $configCache = [];

    /**
     * Server URL configuration with fallback chain
     *
     * @var array
     */
    private array $serverUrls = [
        'production' => 'https://api.woo-ai-assistant.eu',
        'staging' => 'https://staging-api.woo-ai-assistant.eu',
        'backup' => 'https://backup-api.woo-ai-assistant.eu'
    ];

    /**
     * Configuration precedence levels
     *
     * @var array
     */
    private array $configPrecedence = [
        'environment_variables',
        'wp_options',
        'development_override',
        'defaults'
    ];

    /**
     * Cache TTL for different configuration types
     *
     * @var array
     */
    private array $cacheTtl = [
        'api_keys' => 3600,        // 1 hour
        'server_config' => 1800,   // 30 minutes
        'model_config' => 7200,    // 2 hours
        'license_config' => 600    // 10 minutes
    ];

    /**
     * Initialize API configuration
     *
     * @return void
     */
    protected function init(): void
    {
        $this->developmentConfig = DevelopmentConfig::getInstance();

        Logger::debug('API configuration initialized', [
            'is_development' => $this->isDevelopmentMode(),
            'endpoints_count' => count($this->apiEndpoints)
        ]);
    }

    /**
     * Check if we're in development mode
     *
     * @return bool True if in development mode
     */
    public function isDevelopmentMode(): bool
    {
        return $this->developmentConfig->isDevelopmentEnvironment();
    }

    /**
     * Get API key for a specific service
     *
     * Uses configuration hierarchy: env vars > admin settings > defaults
     *
     * @param string $service Service name (openrouter, openai, pinecone, etc.)
     * @return string API key or empty string if not found
     */
    public function getApiKey(string $service): string
    {
        $cacheKey = "api_key_{$service}";

        if (isset($this->configCache[$cacheKey])) {
            return $this->configCache[$cacheKey];
        }

        $apiKey = '';

        // In development mode, try to get from environment variables first
        if ($this->isDevelopmentMode()) {
            $apiKey = $this->developmentConfig->getApiKey($service);

            if (!empty($apiKey)) {
                $this->configCache[$cacheKey] = $apiKey;
                Logger::debug("API key loaded from environment for service: {$service}");
                return $apiKey;
            }
        }

        // Try WordPress options (admin settings)
        $optionKey = $this->getApiKeyOptionName($service);
        $apiKey = get_option($optionKey, '');

        if (!empty($apiKey)) {
            // In production, API keys should be encrypted
            if (!$this->isDevelopmentMode()) {
                $apiKey = $this->decryptApiKey($apiKey);
            }

            $this->configCache[$cacheKey] = $apiKey;
            Logger::debug("API key loaded from WordPress options for service: {$service}");
            return $apiKey;
        }

        // No API key found
        Logger::warning("No API key found for service: {$service}");
        $this->configCache[$cacheKey] = '';
        return '';
    }

    /**
     * Get API endpoint URL for a specific service and action
     *
     * @param string $service Service name
     * @param string $endpoint Endpoint name
     * @param array $params Parameters to replace in URL placeholders
     * @return string Full endpoint URL
     */
    public function getApiEndpoint(string $service, string $endpoint, array $params = []): string
    {
        if (!isset($this->apiEndpoints[$service])) {
            Logger::error("Unknown API service: {$service}");
            return '';
        }

        $serviceConfig = $this->apiEndpoints[$service];

        if (!isset($serviceConfig[$endpoint])) {
            Logger::error("Unknown endpoint '{$endpoint}' for service: {$service}");
            return '';
        }

        $baseUrl = $serviceConfig['base_url'];
        $endpointPath = $serviceConfig[$endpoint];

        // Replace placeholders in base URL and endpoint path
        foreach ($params as $key => $value) {
            $placeholder = '{' . $key . '}';
            $baseUrl = str_replace($placeholder, $value, $baseUrl);
            $endpointPath = str_replace($placeholder, $value, $endpointPath);
        }

        $fullUrl = rtrim($baseUrl, '/') . $endpointPath;

        Logger::debug("API endpoint resolved", [
            'service' => $service,
            'endpoint' => $endpoint,
            'url' => $this->sanitizeUrlForLogging($fullUrl)
        ]);

        return $fullUrl;
    }

    /**
     * Get API headers for a specific service
     *
     * @param string $service Service name
     * @param array $additionalHeaders Additional headers to include
     * @return array HTTP headers array
     */
    public function getApiHeaders(string $service, array $additionalHeaders = []): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'WooAiAssistant/' . $this->getPluginVersion()
        ];

        $apiKey = $this->getApiKey($service);

        if (empty($apiKey)) {
            Logger::warning("No API key available for service: {$service}");
            return array_merge($headers, $additionalHeaders);
        }

        // Set authorization header based on service
        switch ($service) {
            case 'openrouter':
                $headers['Authorization'] = 'Bearer ' . $apiKey;
                $headers['HTTP-Referer'] = home_url();
                $headers['X-Title'] = get_bloginfo('name') . ' - WooCommerce AI Assistant';
                break;

            case 'openai':
                $headers['Authorization'] = 'Bearer ' . $apiKey;
                break;

            case 'pinecone':
                $headers['Api-Key'] = $apiKey;
                break;

            case 'google':
                $headers['x-goog-api-key'] = $apiKey;
                break;

            case 'stripe':
                $headers['Authorization'] = 'Bearer ' . $apiKey;
                $headers['Stripe-Version'] = '2020-08-27';
                break;

            default:
                $headers['Authorization'] = 'Bearer ' . $apiKey;
        }

        return array_merge($headers, $additionalHeaders);
    }

    /**
     * Get AI model configuration
     *
     * @param string $type Model type (primary_chat, fallback_chat, premium_chat, embedding)
     * @return string Model identifier
     */
    public function getAiModel(string $type): string
    {
        $cacheKey = "ai_model_{$type}";

        if (isset($this->configCache[$cacheKey])) {
            return $this->configCache[$cacheKey];
        }

        $model = '';

        // Try environment variables in development mode
        if ($this->isDevelopmentMode()) {
            $envKey = 'WOO_AI_' . strtoupper($type) . '_MODEL';
            $model = $this->developmentConfig->getEnvironmentVariable($envKey);
        }

        // Try WordPress options
        if (empty($model)) {
            $optionKey = 'woo_ai_assistant_' . $type . '_model';
            $model = get_option($optionKey);
        }

        // Use default
        if (empty($model) && isset($this->defaultModels[$type])) {
            $model = $this->defaultModels[$type];
        }

        $this->configCache[$cacheKey] = $model;
        return $model;
    }

    /**
     * Get model parameters for AI requests
     *
     * @return array Model parameters
     */
    public function getModelParameters(): array
    {
        $cacheKey = 'model_parameters';

        if (isset($this->configCache[$cacheKey])) {
            return $this->configCache[$cacheKey];
        }

        $defaults = [
            'max_tokens' => 2000,
            'temperature' => 0.7,
            'top_p' => 0.9,
            'frequency_penalty' => 0.0,
            'presence_penalty' => 0.0
        ];

        $parameters = [];

        foreach ($defaults as $key => $default) {
            // Try environment variables in development mode
            if ($this->isDevelopmentMode()) {
                $envKey = 'WOO_AI_' . strtoupper($key);
                $value = $this->developmentConfig->getEnvironmentVariable($envKey);

                if (!empty($value)) {
                    $parameters[$key] = is_numeric($value) ? (float) $value : $value;
                    continue;
                }
            }

            // Try WordPress options
            $optionKey = 'woo_ai_assistant_' . $key;
            $value = get_option($optionKey);

            if ($value !== false && $value !== '') {
                $parameters[$key] = is_numeric($value) ? (float) $value : $value;
            } else {
                $parameters[$key] = $default;
            }
        }

        $this->configCache[$cacheKey] = $parameters;
        return $parameters;
    }

    /**
     * Get intermediate server configuration with fallback chain
     *
     * In production, the plugin connects to an intermediate server that handles
     * all API calls and license validation. In development, direct API calls are made.
     * Supports multiple server URLs with automatic failover.
     *
     * @param bool $includeBackups Whether to include backup server URLs
     * @return array Intermediate server configuration
     */
    public function getIntermediateServerConfig(bool $includeBackups = true): array
    {
        $cacheKey = 'intermediate_server_config';

        if (isset($this->configCache[$cacheKey])) {
            return $this->configCache[$cacheKey];
        }

        if ($this->isDevelopmentMode()) {
            $config = [
                'enabled' => $this->shouldUseIntermediateServerInDev(),
                'primary_url' => $this->getDevelopmentServerUrl(),
                'fallback_urls' => [],
                'bypass_license' => true,
                'timeout' => $this->getDevelopmentTimeout(),
                'retry_attempts' => 1, // Fewer retries in development
                'environment' => 'development'
            ];
        } else {
            $config = [
                'enabled' => true,
                'primary_url' => $this->getPrimaryServerUrl(),
                'fallback_urls' => $includeBackups ? $this->getBackupServerUrls() : [],
                'bypass_license' => false,
                'timeout' => $this->getProductionTimeout(),
                'retry_attempts' => 3,
                'environment' => 'production'
            ];
        }

        // Add server health check configuration
        $config['health_check'] = [
            'enabled' => true,
            'endpoint' => '/api/v1/health',
            'interval' => 300, // 5 minutes
            'timeout' => 10
        ];

        // Add rate limiting configuration
        $config['rate_limiting'] = [
            'enabled' => !$this->isDevelopmentMode(),
            'requests_per_minute' => $this->isDevelopmentMode() ? 1000 : 100,
            'burst_limit' => $this->isDevelopmentMode() ? 50 : 10
        ];

        $this->configCache[$cacheKey] = $config;

        Logger::debug('Intermediate server configuration loaded', [
            'environment' => $config['environment'],
            'enabled' => $config['enabled'],
            'has_fallbacks' => count($config['fallback_urls']) > 0
        ]);

        return $config;
    }

    /**
     * Get license configuration
     *
     * @return array License configuration
     */
    public function getLicenseConfig(): array
    {
        if ($this->isDevelopmentMode() && $this->developmentConfig->shouldBypassLicenseValidation()) {
            return [
                'key' => $this->developmentConfig->getDevelopmentLicenseKey(),
                'status' => 'active',
                'plan' => 'unlimited',
                'bypass_validation' => true
            ];
        }

        return [
            'key' => get_option('woo_ai_assistant_license_key', ''),
            'status' => get_option('woo_ai_assistant_license_status', 'inactive'),
            'plan' => get_option('woo_ai_assistant_license_plan', 'free'),
            'bypass_validation' => false
        ];
    }

    /**
     * Set API key for a service (admin use)
     *
     * @param string $service Service name
     * @param string $apiKey API key to set
     * @return bool True on success, false on failure
     */
    public function setApiKey(string $service, string $apiKey): bool
    {
        if ($this->isDevelopmentMode()) {
            Logger::warning('Cannot set API keys in development mode - use .env file instead');
            return false;
        }

        if (empty($apiKey)) {
            Logger::error("Cannot set empty API key for service: {$service}");
            return false;
        }

        // Encrypt API key before storing in production
        $encryptedKey = $this->encryptApiKey($apiKey);

        $optionKey = $this->getApiKeyOptionName($service);
        $result = update_option($optionKey, $encryptedKey);

        if ($result) {
            // Clear cache
            unset($this->configCache["api_key_{$service}"]);
            Logger::info("API key updated for service: {$service}");
        } else {
            Logger::error("Failed to update API key for service: {$service}");
        }

        return $result;
    }

    /**
     * Get all configuration for debugging (safe for logging)
     *
     * @return array Safe configuration array without sensitive data
     */
    public function getSafeConfiguration(): array
    {
        $config = [
            'is_development_mode' => $this->isDevelopmentMode(),
            'plugin_version' => $this->getPluginVersion(),
            'api_services' => [],
            'models' => [
                'primary_chat' => $this->getAiModel('primary_chat'),
                'fallback_chat' => $this->getAiModel('fallback_chat'),
                'embedding' => $this->getAiModel('embedding')
            ],
            'model_parameters' => $this->getModelParameters(),
            'intermediate_server' => [
                'enabled' => $this->getIntermediateServerConfig()['enabled'],
                'url_configured' => !empty($this->getIntermediateServerConfig()['url'])
            ],
            'license' => [
                'status' => $this->getLicenseConfig()['status'],
                'plan' => $this->getLicenseConfig()['plan'],
                'bypass_validation' => $this->getLicenseConfig()['bypass_validation']
            ]
        ];

        // Add API service status (without keys)
        foreach (array_keys($this->apiEndpoints) as $service) {
            $config['api_services'][$service] = [
                'has_key' => !empty($this->getApiKey($service)),
                'endpoints_available' => count($this->apiEndpoints[$service]) - 1 // Exclude base_url
            ];
        }

        return $config;
    }

    /**
     * Get WordPress option name for API key storage
     *
     * @param string $service Service name
     * @return string WordPress option name
     */
    private function getApiKeyOptionName(string $service): string
    {
        return 'woo_ai_assistant_' . $service . '_api_key';
    }

    /**
     * Encrypt API key for secure storage
     *
     * @param string $apiKey API key to encrypt
     * @return string Encrypted API key
     */
    private function encryptApiKey(string $apiKey): string
    {
        // In a real implementation, use WordPress's encryption functions
        // or a proper encryption library. This is a placeholder.
        if (function_exists('wp_hash')) {
            return base64_encode($apiKey . wp_hash($apiKey));
        }

        return base64_encode($apiKey);
    }

    /**
     * Decrypt API key from storage
     *
     * @param string $encryptedKey Encrypted API key
     * @return string Decrypted API key
     */
    private function decryptApiKey(string $encryptedKey): string
    {
        // This is a placeholder - implement proper decryption in production
        return base64_decode($encryptedKey);
    }

    /**
     * Get plugin version
     *
     * @return string Plugin version
     */
    private function getPluginVersion(): string
    {
        return defined('WOO_AI_ASSISTANT_VERSION') ? WOO_AI_ASSISTANT_VERSION : '1.0.0';
    }

    /**
     * Sanitize URL for logging (remove sensitive parameters)
     *
     * @param string $url URL to sanitize
     * @return string Sanitized URL
     */
    private function sanitizeUrlForLogging(string $url): string
    {
        $parsed = parse_url($url);

        if (!$parsed) {
            return '[invalid-url]';
        }

        $sanitized = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? 'unknown');

        if (isset($parsed['port'])) {
            $sanitized .= ':' . $parsed['port'];
        }

        if (isset($parsed['path'])) {
            $sanitized .= $parsed['path'];
        }

        // Don't include query parameters in logs as they might contain sensitive data
        return $sanitized;
    }

    /**
     * Get development server URL with fallbacks
     *
     * @return string Development server URL
     */
    private function getDevelopmentServerUrl(): string
    {
        // Check for explicit development server URL
        $devUrl = $this->developmentConfig->getEnvironmentVariable('WOO_AI_DEVELOPMENT_SERVER_URL');
        if (!empty($devUrl)) {
            return $devUrl;
        }

        // Check for generic development API URL
        $apiUrl = $this->developmentConfig->getEnvironmentVariable('WOO_AI_ASSISTANT_API_URL');
        if (!empty($apiUrl)) {
            return $apiUrl;
        }

        // Default development server URL
        return 'http://localhost:3000';
    }

    /**
     * Get primary production server URL
     *
     * @return string Primary server URL
     */
    private function getPrimaryServerUrl(): string
    {
        // Check WordPress options first
        $configuredUrl = get_option('woo_ai_assistant_server_url');
        if (!empty($configuredUrl)) {
            return $configuredUrl;
        }

        // Use default production URL
        return $this->serverUrls['production'];
    }

    /**
     * Get backup server URLs for failover
     *
     * @return array Backup server URLs
     */
    private function getBackupServerUrls(): array
    {
        $backupUrls = [];

        // Add configured backup URLs from WordPress options
        $configuredBackups = get_option('woo_ai_assistant_backup_servers', []);
        if (is_array($configuredBackups)) {
            $backupUrls = array_merge($backupUrls, $configuredBackups);
        }

        // Add default backup servers
        if (isset($this->serverUrls['staging'])) {
            $backupUrls[] = $this->serverUrls['staging'];
        }
        if (isset($this->serverUrls['backup'])) {
            $backupUrls[] = $this->serverUrls['backup'];
        }

        // Remove duplicates and the primary URL
        $primaryUrl = $this->getPrimaryServerUrl();
        $backupUrls = array_unique($backupUrls);
        $backupUrls = array_filter($backupUrls, function ($url) use ($primaryUrl) {
            return $url !== $primaryUrl;
        });

        return array_values($backupUrls);
    }

    /**
     * Should use intermediate server in development mode
     *
     * @return bool True if should use intermediate server in development
     */
    private function shouldUseIntermediateServerInDev(): bool
    {
        // Check explicit environment variable
        $useServer = $this->developmentConfig->getEnvironmentVariable('WOO_AI_USE_INTERMEDIATE_SERVER');
        if ($useServer === 'true') {
            return true;
        }
        if ($useServer === 'false') {
            return false;
        }

        // Default: use server if development server URL is configured
        $devUrl = $this->getDevelopmentServerUrl();
        return !empty($devUrl) && $devUrl !== 'http://localhost:3000';
    }

    /**
     * Get development request timeout
     *
     * @return int Timeout in seconds
     */
    private function getDevelopmentTimeout(): int
    {
        $timeout = $this->developmentConfig->getEnvironmentVariable('WOO_AI_DEV_API_TIMEOUT');
        return !empty($timeout) && is_numeric($timeout) ? (int) $timeout : 30;
    }

    /**
     * Get production request timeout
     *
     * @return int Timeout in seconds
     */
    private function getProductionTimeout(): int
    {
        $timeout = get_option('woo_ai_assistant_api_timeout', 60);
        return is_numeric($timeout) ? (int) $timeout : 60;
    }

    /**
     * Test server connectivity
     *
     * @param string $serverUrl Server URL to test
     * @param int $timeout Timeout in seconds
     * @return array Connectivity test results
     */
    public function testServerConnectivity(string $serverUrl, int $timeout = 10): array
    {
        $startTime = microtime(true);
        $healthUrl = rtrim($serverUrl, '/') . '/api/v1/health';

        Logger::debug('Testing server connectivity', [
            'url' => $this->sanitizeUrlForLogging($healthUrl),
            'timeout' => $timeout
        ]);

        try {
            $response = wp_remote_get($healthUrl, [
                'timeout' => $timeout,
                'sslverify' => !$this->isDevelopmentMode(),
                'headers' => [
                    'User-Agent' => 'WooAiAssistant/' . $this->getPluginVersion() . ' (Health Check)',
                ]
            ]);

            $duration = microtime(true) - $startTime;

            if (is_wp_error($response)) {
                return [
                    'success' => false,
                    'error' => $response->get_error_message(),
                    'error_code' => $response->get_error_code(),
                    'duration' => $duration,
                    'timestamp' => time()
                ];
            }

            $statusCode = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            $result = [
                'success' => $statusCode >= 200 && $statusCode < 300,
                'status_code' => $statusCode,
                'duration' => $duration,
                'timestamp' => time()
            ];

            // Try to parse response body
            $responseData = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $result['response_data'] = $responseData;
                if (isset($responseData['status'])) {
                    $result['server_status'] = $responseData['status'];
                }
            }

            return $result;
        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_code' => 'exception',
                'duration' => $duration,
                'timestamp' => time()
            ];
        }
    }

    /**
     * Get all server URLs with their status
     *
     * @return array Server URLs with status information
     */
    public function getAllServerStatus(): array
    {
        $cacheKey = 'all_server_status';
        $cached = $this->configCache[$cacheKey] ?? null;

        // Return cached result if less than 5 minutes old
        if ($cached && (time() - $cached['timestamp']) < 300) {
            return $cached['data'];
        }

        $serverConfig = $this->getIntermediateServerConfig();
        $servers = [
            'primary' => $serverConfig['primary_url']
        ];

        foreach ($serverConfig['fallback_urls'] as $index => $url) {
            $servers["fallback_" . ($index + 1)] = $url;
        }

        $results = [];
        foreach ($servers as $type => $url) {
            $results[$type] = array_merge(
                ['url' => $url, 'type' => $type],
                $this->testServerConnectivity($url, 5) // Shorter timeout for status check
            );
        }

        // Cache the results
        $this->configCache[$cacheKey] = [
            'data' => $results,
            'timestamp' => time()
        ];

        return $results;
    }

    /**
     * Get configuration by precedence
     *
     * @param string $key Configuration key
     * @param mixed $default Default value
     * @return mixed Configuration value
     */
    public function getConfigByPrecedence(string $key, $default = null)
    {
        foreach ($this->configPrecedence as $source) {
            $value = null;

            switch ($source) {
                case 'environment_variables':
                    if ($this->isDevelopmentMode()) {
                        $value = $this->developmentConfig->getEnvironmentVariable($key);
                    }
                    break;

                case 'wp_options':
                    $value = get_option($key);
                    break;

                case 'development_override':
                    if ($this->isDevelopmentMode()) {
                        $value = $this->getDevelopmentOverride($key);
                    }
                    break;

                case 'defaults':
                    $value = $this->getDefaultValue($key);
                    break;
            }

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return $default;
    }

    /**
     * Get development override value
     *
     * @param string $key Configuration key
     * @return mixed Override value or null
     */
    private function getDevelopmentOverride(string $key)
    {
        $overrides = [
            'woo_ai_assistant_max_tokens' => 4000, // Higher limit in development
            'woo_ai_assistant_temperature' => 0.8,  // More creative in development
            'woo_ai_assistant_cache_ttl' => 60,     // Shorter cache in development
        ];

        return $overrides[$key] ?? null;
    }

    /**
     * Get default configuration value
     *
     * @param string $key Configuration key
     * @return mixed Default value or null
     */
    private function getDefaultValue(string $key)
    {
        $defaults = [
            'woo_ai_assistant_max_tokens' => 2000,
            'woo_ai_assistant_temperature' => 0.7,
            'woo_ai_assistant_cache_ttl' => 3600,
            'woo_ai_assistant_api_timeout' => 60,
            'woo_ai_assistant_retry_attempts' => 3
        ];

        return $defaults[$key] ?? null;
    }

    /**
     * Validate configuration settings
     *
     * @return array Validation results
     */
    public function validateConfiguration(): array
    {
        $results = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'recommendations' => []
        ];

        // Check API keys availability
        $requiredServices = ['openrouter', 'openai'];
        foreach ($requiredServices as $service) {
            $apiKey = $this->getApiKey($service);
            if (empty($apiKey)) {
                if ($this->isDevelopmentMode()) {
                    $results['warnings'][] = "No {$service} API key configured for development";
                } else {
                    $results['errors'][] = "Missing {$service} API key for production";
                    $results['valid'] = false;
                }
            }
        }

        // Check server connectivity in production
        if (!$this->isDevelopmentMode()) {
            $serverStatus = $this->getAllServerStatus();
            $hasWorkingServer = false;

            foreach ($serverStatus as $server) {
                if ($server['success']) {
                    $hasWorkingServer = true;
                    break;
                }
            }

            if (!$hasWorkingServer) {
                $results['errors'][] = 'No intermediate servers are accessible';
                $results['valid'] = false;
            } elseif (!$serverStatus['primary']['success']) {
                $results['warnings'][] = 'Primary server is not accessible, using fallback servers';
            }
        }

        // Check license configuration
        $licenseConfig = $this->getLicenseConfig();
        if (!$this->isDevelopmentMode()) {
            if (empty($licenseConfig['key'])) {
                $results['errors'][] = 'No license key configured';
                $results['valid'] = false;
            } elseif ($licenseConfig['status'] !== 'active') {
                $results['warnings'][] = 'License status is not active: ' . $licenseConfig['status'];
            }
        }

        // Performance recommendations
        $cacheEnabled = get_option('woo_ai_assistant_enable_cache', true);
        if (!$cacheEnabled) {
            $results['recommendations'][] = 'Enable caching for better performance';
        }

        return $results;
    }

    /**
     * Clear configuration cache
     *
     * @param string|null $specific Clear specific cache key or all if null
     * @return void
     */
    public function clearCache(?string $specific = null): void
    {
        if ($specific) {
            unset($this->configCache[$specific]);
            Logger::debug("API configuration cache cleared for: {$specific}");
        } else {
            $this->configCache = [];
            Logger::debug('API configuration cache cleared completely');
        }
    }
}
