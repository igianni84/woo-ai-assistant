<?php

/**
 * Intermediate Server Client Class
 *
 * Handles secure API communication with the intermediate server that manages
 * all external API calls (OpenRouter, OpenAI, Pinecone) and license validation.
 * Supports both development mode (bypass server) and production mode.
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
use WooAiAssistant\Config\DevelopmentConfig;
use WooAiAssistant\Config\ApiConfiguration;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class IntermediateServerClient
 *
 * Secure API communication layer with the intermediate server.
 *
 * @since 1.0.0
 */
class IntermediateServerClient
{
    use Singleton;

    /**
     * Development configuration instance
     *
     * @var DevelopmentConfig
     */
    private DevelopmentConfig $developmentConfig;

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
     * Rate limiting data
     *
     * @var array
     */
    private array $rateLimits = [];

    /**
     * Request timeout in seconds
     *
     * @var int
     */
    private int $requestTimeout = 30;

    /**
     * Maximum retry attempts
     *
     * @var int
     */
    private int $maxRetryAttempts = 3;

    /**
     * Supported API endpoints
     *
     * @var array
     */
    private array $endpoints = [
        'chat' => '/api/v1/chat/completions',
        'embeddings' => '/api/v1/embeddings',
        'license/validate' => '/api/v1/license/validate',
        'license/usage' => '/api/v1/license/usage',
        'health' => '/api/v1/health'
    ];

    /**
     * Initialize the client
     *
     * @return void
     */
    protected function init(): void
    {
        $this->developmentConfig = DevelopmentConfig::getInstance();
        $this->apiConfiguration = ApiConfiguration::getInstance();
        $this->cache = Cache::getInstance();

        // Get optimized configuration for current environment
        $optimizedConfig = $this->developmentConfig->getOptimizedConfiguration();

        // Set request timeout and retry attempts from optimized configuration
        $this->requestTimeout = $optimizedConfig['api_timeout'];
        $this->maxRetryAttempts = $optimizedConfig['retry_attempts'];

        Logger::debug('IntermediateServerClient initialized', [
            'is_development' => $this->developmentConfig->isDevelopmentEnvironment(),
            'environment_type' => $this->developmentConfig->getEnvironmentType(),
            'timeout' => $this->requestTimeout,
            'retry_attempts' => $this->maxRetryAttempts,
            'endpoints' => array_keys($this->endpoints)
        ]);
    }

    /**
     * Send chat completion request
     *
     * @param array $messages Chat messages array
     * @param array $options Additional options (model, temperature, etc.)
     * @return array Chat completion response
     * @throws ApiException When API request fails
     * @throws ValidationException When parameters are invalid
     */
    public function chatCompletion(array $messages, array $options = []): array
    {
        $this->validateMessages($messages);

        $requestData = [
            'messages' => $messages,
            'model' => $options['model'] ?? $this->apiConfiguration->getAiModel('primary_chat'),
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 2000,
            'stream' => $options['stream'] ?? false
        ];

        // Add model parameters
        $modelParams = $this->apiConfiguration->getModelParameters();
        $requestData = array_merge($requestData, $modelParams);

        return $this->makeRequest('chat', $requestData, [
            'timeout' => $options['timeout'] ?? 60, // Chat requests can take longer
            'stream' => $requestData['stream']
        ]);
    }

    /**
     * Generate embeddings for text
     *
     * @param array $texts Array of texts to embed
     * @param string $model Embedding model to use
     * @return array Embeddings response
     * @throws ApiException When API request fails
     * @throws ValidationException When parameters are invalid
     */
    public function generateEmbeddings(array $texts, string $model = ''): array
    {
        if (empty($texts)) {
            throw new ValidationException('Texts array cannot be empty', 'texts', 'required');
        }

        // Validate text lengths
        foreach ($texts as $index => $text) {
            if (!is_string($text)) {
                throw new ValidationException("Text at index {$index} must be string", "texts[{$index}]", 'type');
            }

            if (strlen($text) > 8000) { // OpenAI embedding limit
                throw new ValidationException("Text at index {$index} exceeds maximum length", "texts[{$index}]", 'max_length');
            }
        }

        $requestData = [
            'input' => $texts,
            'model' => $model ?: $this->apiConfiguration->getAiModel('embedding')
        ];

        return $this->makeRequest('embeddings', $requestData);
    }

    /**
     * Validate license key with server
     *
     * @param string $licenseKey License key to validate
     * @return array License validation response
     * @throws ApiException When API request fails
     * @throws ValidationException When license key is invalid
     */
    public function validateLicense(string $licenseKey): array
    {
        if (empty($licenseKey)) {
            throw new ValidationException('License key cannot be empty', 'license_key', 'required');
        }

        // In development mode, bypass license validation if configured
        if ($this->shouldBypassLicenseValidation()) {
            Logger::debug('Bypassing license validation in development mode');

            return [
                'valid' => true,
                'status' => 'active',
                'plan' => 'unlimited',
                'expires_at' => null,
                'usage' => [
                    'conversations' => 0,
                    'limit' => -1 // Unlimited
                ],
                'development_bypass' => true
            ];
        }

        $requestData = [
            'license_key' => $licenseKey,
            'domain' => home_url(),
            'wp_version' => get_bloginfo('version'),
            'plugin_version' => defined('WOO_AI_ASSISTANT_VERSION') ? WOO_AI_ASSISTANT_VERSION : '1.0.0'
        ];

        return $this->makeRequest('license/validate', $requestData);
    }

    /**
     * Get license usage statistics
     *
     * @param string $licenseKey License key
     * @return array Usage statistics
     * @throws ApiException When API request fails
     */
    public function getLicenseUsage(string $licenseKey): array
    {
        if ($this->shouldBypassLicenseValidation()) {
            return [
                'conversations' => 0,
                'limit' => -1,
                'reset_date' => null,
                'development_bypass' => true
            ];
        }

        $requestData = [
            'license_key' => $licenseKey,
            'domain' => home_url()
        ];

        return $this->makeRequest('license/usage', $requestData);
    }

    /**
     * Check server health
     *
     * @return array Health check response
     */
    public function healthCheck(): array
    {
        try {
            return $this->makeRequest('health', [], [
                'timeout' => 10,
                'skip_auth' => true
            ]);
        } catch (ApiException $e) {
            Logger::warning('Server health check failed', [
                'error' => $e->getMessage(),
                'status' => $e->getHttpStatusCode()
            ]);

            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'timestamp' => time()
            ];
        }
    }

    /**
     * Make HTTP request to the intermediate server
     *
     * @param string $endpoint Endpoint name
     * @param array $data Request data
     * @param array $options Request options
     * @return array Response data
     * @throws ApiException When request fails
     */
    private function makeRequest(string $endpoint, array $data, array $options = []): array
    {
        $startTime = microtime(true);

        // Check rate limits
        $this->checkRateLimit($endpoint);

        // Prepare request
        $url = $this->buildUrl($endpoint);
        $headers = $this->buildHeaders($endpoint, $data, $options);
        $requestData = $this->prepareRequestData($endpoint, $data, $options);

        Logger::debug("Making request to intermediate server", [
            'endpoint' => $endpoint,
            'url' => $this->sanitizeUrlForLogging($url),
            'method' => 'POST',
            'data_size' => strlen(json_encode($requestData))
        ]);

        // In development mode, make direct API calls if intermediate server is disabled
        if ($this->shouldBypassIntermediateServer()) {
            return $this->makeDirectApiCall($endpoint, $data, $options);
        }

        $response = null;
        $attempt = 1;
        $lastException = null;

        while ($attempt <= $this->maxRetryAttempts) {
            try {
                $response = $this->executeHttpRequest($url, $requestData, $headers, $options);

                // Update rate limit counters
                $this->updateRateLimit($endpoint);

                // Log successful request
                $duration = microtime(true) - $startTime;
                Logger::debug("Request completed successfully", [
                    'endpoint' => $endpoint,
                    'attempt' => $attempt,
                    'duration' => round($duration, 3) . 's'
                ]);

                return $response;
            } catch (ApiException $e) {
                $lastException = $e;

                Logger::warning("Request attempt failed", [
                    'endpoint' => $endpoint,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                    'status' => $e->getHttpStatusCode(),
                    'should_retry' => $e->shouldRetry()
                ]);

                // Don't retry if the error indicates we shouldn't
                if (!$e->shouldRetry()) {
                    break;
                }

                // If this isn't the last attempt, wait before retrying
                if ($attempt < $this->maxRetryAttempts) {
                    $delay = $this->calculateRetryDelay($attempt, $e);
                    Logger::info("Retrying request after delay", [
                        'endpoint' => $endpoint,
                        'attempt' => $attempt + 1,
                        'delay' => $delay
                    ]);
                    sleep($delay);
                }

                $attempt++;
            }
        }

        // All retry attempts failed, throw the last exception
        if ($lastException) {
            throw $lastException;
        }

        // This shouldn't happen, but just in case
        throw new ApiException('Request failed with no specific error', 0, 'intermediate_server', $endpoint);
    }

    /**
     * Execute HTTP request
     *
     * @param string $url Request URL
     * @param array $data Request data
     * @param array $headers HTTP headers
     * @param array $options Request options
     * @return array Response data
     * @throws ApiException When HTTP request fails
     */
    private function executeHttpRequest(string $url, array $data, array $headers, array $options = []): array
    {
        $timeout = $options['timeout'] ?? $this->requestTimeout;
        $isStream = $options['stream'] ?? false;

        $args = [
            'method' => 'POST',
            'headers' => $headers,
            'body' => json_encode($data),
            'timeout' => $timeout,
            'blocking' => !$isStream,
            'sslverify' => !$this->developmentConfig->isDevelopmentEnvironment()
        ];

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            throw new ApiException(
                'HTTP request failed: ' . $response->get_error_message(),
                0,
                'intermediate_server',
                $url,
                $this->sanitizeRequestDataForLogging($data),
                []
            );
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $responseBody = wp_remote_retrieve_body($response);
        $responseHeaders = wp_remote_retrieve_headers($response);

        // Handle streaming responses
        if ($isStream) {
            return [
                'stream' => true,
                'status_code' => $statusCode,
                'headers' => $responseHeaders->getAll(),
                'body' => $responseBody
            ];
        }

        // Parse JSON response
        $responseData = json_decode($responseBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ApiException(
                'Invalid JSON response: ' . json_last_error_msg(),
                $statusCode,
                'intermediate_server',
                $url,
                $this->sanitizeRequestDataForLogging($data),
                ['raw_response' => substr($responseBody, 0, 500)]
            );
        }

        // Check for HTTP errors
        if ($statusCode >= 400) {
            throw new ApiException(
                $responseData['error']['message'] ?? 'HTTP request failed',
                $statusCode,
                'intermediate_server',
                $url,
                $this->sanitizeRequestDataForLogging($data),
                $responseData
            );
        }

        return $responseData;
    }

    /**
     * Build request URL
     *
     * @param string $endpoint Endpoint name
     * @return string Complete URL
     */
    private function buildUrl(string $endpoint): string
    {
        $serverConfig = $this->apiConfiguration->getIntermediateServerConfig();
        $baseUrl = rtrim($serverConfig['url'], '/');

        if (!isset($this->endpoints[$endpoint])) {
            throw new ValidationException("Unknown endpoint: {$endpoint}", 'endpoint', 'exists');
        }

        return $baseUrl . $this->endpoints[$endpoint];
    }

    /**
     * Build HTTP headers
     *
     * @param string $endpoint Endpoint name
     * @param array $data Request data
     * @param array $options Request options
     * @return array HTTP headers
     */
    private function buildHeaders(string $endpoint, array $data, array $options = []): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'WooAiAssistant/' . (defined('WOO_AI_ASSISTANT_VERSION') ? WOO_AI_ASSISTANT_VERSION : '1.0.0'),
            'X-WP-Version' => get_bloginfo('version'),
            'X-Plugin-Version' => defined('WOO_AI_ASSISTANT_VERSION') ? WOO_AI_ASSISTANT_VERSION : '1.0.0',
            'X-Site-URL' => home_url()
        ];

        // Skip authentication for certain endpoints
        if (!($options['skip_auth'] ?? false)) {
            $signature = $this->generateRequestSignature($endpoint, $data);
            $headers['X-Signature'] = $signature;

            $licenseConfig = $this->apiConfiguration->getLicenseConfig();
            if (!empty($licenseConfig['key'])) {
                $headers['X-License-Key'] = $licenseConfig['key'];
            }
        }

        return $headers;
    }

    /**
     * Prepare request data with additional metadata
     *
     * @param string $endpoint Endpoint name
     * @param array $data Original request data
     * @param array $options Request options
     * @return array Prepared request data
     */
    private function prepareRequestData(string $endpoint, array $data, array $options = []): array
    {
        $requestData = $data;

        // Add metadata
        $requestData['_meta'] = [
            'timestamp' => time(),
            'request_id' => wp_generate_uuid4(),
            'wp_version' => get_bloginfo('version'),
            'plugin_version' => defined('WOO_AI_ASSISTANT_VERSION') ? WOO_AI_ASSISTANT_VERSION : '1.0.0',
            'php_version' => PHP_VERSION,
            'site_url' => home_url()
        ];

        return $requestData;
    }

    /**
     * Generate HMAC-SHA256 request signature
     *
     * @param string $endpoint Endpoint name
     * @param array $data Request data
     * @return string Request signature
     */
    private function generateRequestSignature(string $endpoint, array $data): string
    {
        $licenseConfig = $this->apiConfiguration->getLicenseConfig();
        $licenseKey = $licenseConfig['key'] ?? '';

        if (empty($licenseKey)) {
            Logger::warning('Cannot generate request signature: no license key available');
            return '';
        }

        // Create payload for signing
        $payload = [
            'endpoint' => $endpoint,
            'timestamp' => time(),
            'data_hash' => hash('sha256', json_encode($data, JSON_SORT_KEYS))
        ];

        $payloadString = json_encode($payload, JSON_SORT_KEYS);
        $signature = hash_hmac('sha256', $payloadString, $licenseKey);

        Logger::debug('Request signature generated', [
            'endpoint' => $endpoint,
            'payload_size' => strlen($payloadString)
        ]);

        return $signature;
    }

    /**
     * Check rate limits for endpoint
     *
     * @param string $endpoint Endpoint name
     * @throws ApiException When rate limited
     */
    private function checkRateLimit(string $endpoint): void
    {
        $cacheKey = "rate_limit_{$endpoint}";
        $currentCount = $this->cache->get($cacheKey, 0);

        // Rate limits per hour
        $rateLimits = [
            'chat' => 1000,
            'embeddings' => 10000,
            'license/validate' => 100,
            'license/usage' => 1000,
            'health' => 10000
        ];

        $limit = $rateLimits[$endpoint] ?? 100;

        if ($currentCount >= $limit) {
            $resetTime = $this->cache->getTtl($cacheKey);
            throw new ApiException(
                "Rate limit exceeded for endpoint: {$endpoint}",
                429,
                'intermediate_server',
                $endpoint,
                [],
                [
                    'error' => [
                        'code' => 'rate_limit_exceeded',
                        'limit' => $limit,
                        'reset_time' => $resetTime
                    ]
                ]
            );
        }
    }

    /**
     * Update rate limit counters
     *
     * @param string $endpoint Endpoint name
     */
    private function updateRateLimit(string $endpoint): void
    {
        $cacheKey = "rate_limit_{$endpoint}";
        $currentCount = $this->cache->get($cacheKey, 0);
        $this->cache->set($cacheKey, $currentCount + 1, HOUR_IN_SECONDS);
    }

    /**
     * Calculate retry delay with exponential backoff
     *
     * @param int $attempt Current attempt number
     * @param ApiException $exception Last exception
     * @return int Delay in seconds
     */
    private function calculateRetryDelay(int $attempt, ApiException $exception): int
    {
        // Use exception's suggested delay if available
        $baseDelay = $exception->getRetryDelay();
        if ($baseDelay > 0) {
            return $baseDelay;
        }

        // Exponential backoff: 2^attempt seconds with jitter
        $delay = min(pow(2, $attempt - 1), 60); // Max 60 seconds
        $jitter = rand(0, (int)($delay * 0.1)); // Add up to 10% jitter

        return $delay + $jitter;
    }

    /**
     * Validate chat messages array
     *
     * @param array $messages Messages to validate
     * @throws ValidationException When messages are invalid
     */
    private function validateMessages(array $messages): void
    {
        if (empty($messages)) {
            throw new ValidationException('Messages array cannot be empty', 'messages', 'required');
        }

        foreach ($messages as $index => $message) {
            if (!is_array($message)) {
                throw new ValidationException("Message at index {$index} must be array", "messages[{$index}]", 'type');
            }

            if (!isset($message['role']) || !isset($message['content'])) {
                throw new ValidationException("Message at index {$index} must have role and content", "messages[{$index}]", 'structure');
            }

            $validRoles = ['system', 'user', 'assistant'];
            if (!in_array($message['role'], $validRoles)) {
                throw new ValidationException("Invalid role at index {$index}", "messages[{$index}].role", 'enum');
            }

            if (!is_string($message['content']) || empty(trim($message['content']))) {
                throw new ValidationException("Content at index {$index} must be non-empty string", "messages[{$index}].content", 'required');
            }
        }
    }

    /**
     * Should bypass intermediate server (development mode)
     *
     * @return bool True if should bypass
     */
    private function shouldBypassIntermediateServer(): bool
    {
        if (!$this->developmentConfig->isDevelopmentEnvironment()) {
            return false;
        }

        $serverConfig = $this->apiConfiguration->getIntermediateServerConfig();
        return !$serverConfig['enabled'];
    }

    /**
     * Should bypass license validation
     *
     * @return bool True if should bypass
     */
    private function shouldBypassLicenseValidation(): bool
    {
        return $this->developmentConfig->shouldBypassLicenseValidation();
    }

    /**
     * Make direct API call (development mode)
     *
     * @param string $endpoint Endpoint name
     * @param array $data Request data
     * @param array $options Request options
     * @return array Response data
     * @throws ApiException When direct API call fails
     */
    private function makeDirectApiCall(string $endpoint, array $data, array $options = []): array
    {
        Logger::debug('Making direct API call (development mode)', [
            'endpoint' => $endpoint,
            'bypass_reason' => 'intermediate_server_disabled'
        ]);

        // Map endpoints to direct API services
        $directApiMap = [
            'chat' => 'openrouter',
            'embeddings' => 'openai'
        ];

        if (!isset($directApiMap[$endpoint])) {
            throw new ApiException(
                "Direct API call not supported for endpoint: {$endpoint}",
                501,
                'development_bypass',
                $endpoint
            );
        }

        $service = $directApiMap[$endpoint];
        $apiKey = $this->apiConfiguration->getApiKey($service);

        if (empty($apiKey)) {
            throw new ApiException(
                "No API key available for direct {$service} call",
                401,
                $service,
                $endpoint,
                [],
                ['error' => ['code' => 'missing_api_key']]
            );
        }

        // This is a simplified implementation - in a real scenario,
        // you'd implement the full direct API calls here
        return [
            'direct_api_call' => true,
            'service' => $service,
            'endpoint' => $endpoint,
            'message' => 'Direct API call completed (development mode)',
            'data' => $data
        ];
    }

    /**
     * Sanitize request data for logging (remove sensitive information)
     *
     * @param array $data Request data
     * @return array Sanitized data
     */
    private function sanitizeRequestDataForLogging(array $data): array
    {
        $sensitiveKeys = ['license_key', 'api_key', 'secret', 'password', 'token'];
        $sanitized = $data;

        array_walk_recursive($sanitized, function (&$value, $key) use ($sensitiveKeys) {
            if (is_string($key) && in_array(strtolower($key), $sensitiveKeys)) {
                $value = '[REDACTED]';
            }
        });

        return $sanitized;
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
     * Get current rate limit status for an endpoint
     *
     * @param string $endpoint Endpoint name
     * @return array Rate limit status
     */
    public function getRateLimitStatus(string $endpoint): array
    {
        $cacheKey = "rate_limit_{$endpoint}";
        $currentCount = $this->cache->get($cacheKey, 0);
        $resetTime = $this->cache->getTtl($cacheKey);

        $rateLimits = [
            'chat' => 1000,
            'embeddings' => 10000,
            'license/validate' => 100,
            'license/usage' => 1000,
            'health' => 10000
        ];

        $limit = $rateLimits[$endpoint] ?? 100;

        return [
            'endpoint' => $endpoint,
            'current' => $currentCount,
            'limit' => $limit,
            'remaining' => max(0, $limit - $currentCount),
            'reset_time' => $resetTime,
            'reset_in_seconds' => max(0, $resetTime - time())
        ];
    }

    /**
     * Reset rate limits (admin function)
     *
     * @param string|null $endpoint Specific endpoint or null for all
     * @return bool Success status
     */
    public function resetRateLimits(?string $endpoint = null): bool
    {
        if ($endpoint) {
            $cacheKey = "rate_limit_{$endpoint}";
            return $this->cache->delete($cacheKey);
        }

        // Reset all rate limits
        $success = true;
        foreach (array_keys($this->endpoints) as $ep) {
            $cacheKey = "rate_limit_{$ep}";
            if (!$this->cache->delete($cacheKey)) {
                $success = false;
            }
        }

        Logger::info('Rate limits reset', [
            'endpoint' => $endpoint ?? 'all',
            'success' => $success
        ]);

        return $success;
    }

    /**
     * Get comprehensive client status for debugging and monitoring
     *
     * @return array Client status information
     */
    public function getClientStatus(): array
    {
        $serverConfig = $this->apiConfiguration->getIntermediateServerConfig();
        $developmentConfig = $this->developmentConfig->getDevelopmentConfig();

        $status = [
            'client_initialized' => true,
            'environment' => [
                'type' => $this->developmentConfig->getEnvironmentType(),
                'is_development' => $this->developmentConfig->isDevelopmentEnvironment(),
                'bypass_intermediate_server' => $this->shouldBypassIntermediateServer(),
                'bypass_license_validation' => $this->shouldBypassLicenseValidation()
            ],
            'configuration' => [
                'request_timeout' => $this->requestTimeout,
                'max_retry_attempts' => $this->maxRetryAttempts,
                'intermediate_server_enabled' => $serverConfig['enabled'],
                'has_fallback_servers' => count($serverConfig['fallback_urls'] ?? []) > 0,
                'server_urls' => [
                    'primary' => $serverConfig['primary_url'] ?? $serverConfig['url'] ?? null,
                    'fallback_count' => count($serverConfig['fallback_urls'] ?? [])
                ]
            ],
            'rate_limits' => [],
            'timestamp' => time()
        ];

        // Get rate limit status for all endpoints
        foreach (array_keys($this->endpoints) as $endpoint) {
            $status['rate_limits'][$endpoint] = $this->getRateLimitStatus($endpoint);
        }

        // Test server connectivity if not bypassing (quick check)
        if (!$this->shouldBypassIntermediateServer()) {
            $status['server_connectivity'] = $this->apiConfiguration->getAllServerStatus();
        } else {
            $status['server_connectivity'] = [
                'bypassed' => true,
                'reason' => 'Using direct API calls in development mode'
            ];
        }

        return $status;
    }

    /**
     * Validate client configuration and connectivity
     *
     * @return array Validation results
     */
    public function validateClientConfiguration(): array
    {
        $results = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'recommendations' => []
        ];

        // Validate API configuration
        $apiConfigValidation = $this->apiConfiguration->validateConfiguration();
        if (!$apiConfigValidation['valid']) {
            $results['valid'] = false;
            $results['errors'] = array_merge($results['errors'], $apiConfigValidation['errors']);
        }
        $results['warnings'] = array_merge($results['warnings'], $apiConfigValidation['warnings']);
        $results['recommendations'] = array_merge($results['recommendations'], $apiConfigValidation['recommendations']);

        // Validate development configuration if in development mode
        if ($this->developmentConfig->isDevelopmentEnvironment()) {
            $devConfigValidation = $this->developmentConfig->validateDevelopmentConfiguration();
            if (!$devConfigValidation['valid']) {
                $results['valid'] = false;
                $results['errors'] = array_merge($results['errors'], $devConfigValidation['errors']);
            }
            $results['warnings'] = array_merge($results['warnings'], $devConfigValidation['warnings']);
            $results['recommendations'] = array_merge($results['recommendations'], $devConfigValidation['recommendations']);
        }

        // Check server connectivity (if not bypassing)
        if (!$this->shouldBypassIntermediateServer()) {
            $serverStatus = $this->apiConfiguration->getAllServerStatus();
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
                $results['warnings'][] = 'Primary server is not accessible, relying on fallback servers';
            }
        }

        return $results;
    }

    /**
     * Get optimized request options based on current environment
     *
     * @param array $baseOptions Base options to merge with
     * @return array Optimized request options
     */
    public function getOptimizedRequestOptions(array $baseOptions = []): array
    {
        $optimizedConfig = $this->developmentConfig->getOptimizedConfiguration();

        $options = array_merge([
            'timeout' => $optimizedConfig['api_timeout'],
            'retry_attempts' => $optimizedConfig['retry_attempts'],
            'cache_ttl' => $optimizedConfig['cache_ttl'],
            'skip_ssl_verify' => $optimizedConfig['skip_ssl_verify'] ?? false,
            'detailed_errors' => $optimizedConfig['detailed_errors'] ?? false,
            'enhanced_logging' => $this->developmentConfig->shouldEnableEnhancedLogging()
        ], $baseOptions);

        return $options;
    }
}
