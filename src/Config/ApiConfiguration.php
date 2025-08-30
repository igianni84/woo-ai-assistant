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
     * Get intermediate server configuration
     *
     * In production, the plugin connects to an intermediate server that handles
     * all API calls and license validation. In development, direct API calls are made.
     *
     * @return array Intermediate server configuration
     */
    public function getIntermediateServerConfig(): array
    {
        if ($this->isDevelopmentMode()) {
            return [
                'enabled' => false,
                'url' => $this->developmentConfig->getEnvironmentVariable('WOO_AI_DEVELOPMENT_SERVER_URL'),
                'bypass_license' => true
            ];
        }

        return [
            'enabled' => true,
            'url' => get_option('woo_ai_assistant_server_url', 'https://api.woo-ai-assistant.eu'),
            'bypass_license' => false
        ];
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
     * Clear configuration cache
     *
     * @return void
     */
    public function clearCache(): void
    {
        $this->configCache = [];
        Logger::debug('API configuration cache cleared');
    }
}
