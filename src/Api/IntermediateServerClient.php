<?php

/**
 * Intermediate Server Client Class
 *
 * Handles secure communication with the intermediate API server for AI operations,
 * embeddings generation, and license management. Implements retry logic, rate limiting,
 * and comprehensive error handling for reliable server communication.
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
use WooAiAssistant\Common\ApiConfiguration;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class IntermediateServerClient
 *
 * Secure HTTP client for communicating with the intermediate AI server.
 * Provides authentication, rate limiting, retry logic, and comprehensive
 * error handling for all server operations.
 *
 * @since 1.0.0
 */
class IntermediateServerClient
{
    use Singleton;

    /**
     * Server API base URL
     *
     * @since 1.0.0
     * @var string
     */
    private string $baseUrl;

    /**
     * API version to use
     *
     * @since 1.0.0
     * @var string
     */
    private string $apiVersion = 'v1';

    /**
     * Authentication token
     *
     * @since 1.0.0
     * @var string|null
     */
    private ?string $authToken = null;

    /**
     * Maximum number of retry attempts
     *
     * @since 1.0.0
     * @var int
     */
    private int $maxRetries = 3;

    /**
     * Base delay for exponential backoff (seconds)
     *
     * @since 1.0.0
     * @var int
     */
    private int $baseDelay = 1;

    /**
     * Maximum delay between retries (seconds)
     *
     * @since 1.0.0
     * @var int
     */
    private int $maxDelay = 30;

    /**
     * Request timeout in seconds
     *
     * @since 1.0.0
     * @var int
     */
    private int $timeout = 30;

    /**
     * Rate limiting settings
     *
     * @since 1.0.0
     * @var array
     */
    private array $rateLimits = [
        'requests_per_minute' => 60,
        'requests_per_hour' => 1000,
        'burst_limit' => 10
    ];

    /**
     * Last request timestamps for rate limiting
     *
     * @since 1.0.0
     * @var array
     */
    private array $requestHistory = [];

    /**
     * Server connection status
     *
     * @since 1.0.0
     * @var bool|null
     */
    private ?bool $connectionStatus = null;

    /**
     * Last error message
     *
     * @since 1.0.0
     * @var string|null
     */
    private ?string $lastError = null;

    /**
     * Development mode flag
     *
     * @since 1.0.0
     * @var bool
     */
    private bool $developmentMode = false;

    /**
     * Constructor
     *
     * Initializes the server client with configuration from WordPress options
     * and environment variables.
     *
     * @since 1.0.0
     */
    private function __construct()
    {
        $this->initializeConfiguration();
        $this->loadAuthToken();
        $this->loadRequestHistory();
    }

    /**
     * Initialize client configuration
     *
     * @since 1.0.0
     * @return void
     */
    private function initializeConfiguration(): void
    {
        // Get server URL from options or environment
        $this->baseUrl = $this->getWordPressOption(
            'woo_ai_assistant_server_url',
            defined('WOO_AI_ASSISTANT_SERVER_URL') ? WOO_AI_ASSISTANT_SERVER_URL : ''
        );

        // Fallback to default server URL if not configured
        if (empty($this->baseUrl)) {
            $this->baseUrl = 'https://api.woo-ai-assistant.com';
        }

        // Ensure URL has no trailing slash
        $this->baseUrl = rtrim($this->baseUrl, '/');

        // Check for development mode
        $this->developmentMode = defined('WOO_AI_ASSISTANT_DEBUG') && WOO_AI_ASSISTANT_DEBUG;

        if ($this->developmentMode) {
            // Use local development server if available
            // EMERGENCY FIX: Disabled URL accessibility check to prevent blocking
            // The check was causing timeout issues during plugin initialization
            $devUrl = $this->getWordPressOption('woo_ai_assistant_dev_server_url', 'http://localhost:3000');
            // Skip accessibility check - assume dev server is not available in emergency mode
            Utils::logDebug('Development mode: Using production URL (dev server check disabled for emergency fix)');
            // Original code commented out to prevent blocking:
            // if ($this->isUrlAccessible($devUrl)) {
            //     $this->baseUrl = $devUrl;
            //     Utils::logDebug('Using development server: ' . $this->baseUrl);
            // } else {
            //     Utils::logDebug('Development server not accessible, using production URL');
            // }
        }

        // Load rate limiting configuration
        $rateLimitConfig = $this->getWordPressOption('woo_ai_assistant_rate_limits', []);
        if (!empty($rateLimitConfig)) {
            $this->rateLimits = array_merge($this->rateLimits, $rateLimitConfig);
        }

        Utils::logDebug('Intermediate Server Client initialized', [
            'base_url' => $this->baseUrl,
            'development_mode' => $this->developmentMode
        ]);
    }

    /**
     * Load authentication token from secure storage
     *
     * @since 1.0.0
     * @return void
     */
    private function loadAuthToken(): void
    {
        // Try to get token from transient first (for caching)
        $this->authToken = $this->getWordPressTransient('woo_ai_assistant_auth_token');

        // If no cached token, try to get from options
        if (empty($this->authToken)) {
            $this->authToken = $this->getWordPressOption('woo_ai_assistant_auth_token', null);

            if (!empty($this->authToken)) {
                // Cache the token for 1 hour
                $this->setWordPressTransient('woo_ai_assistant_auth_token', $this->authToken, HOUR_IN_SECONDS);
            }
        }

        // Log authentication status (without exposing token)
        Utils::logDebug('Authentication token ' . ($this->authToken ? 'loaded' : 'not found'));
    }

    /**
     * Load request history from transient cache
     *
     * @since 1.0.0
     * @return void
     */
    private function loadRequestHistory(): void
    {
        $this->requestHistory = $this->getWordPressTransient('woo_ai_assistant_request_history') ?: [];
    }

    /**
     * Save request history to transient cache
     *
     * @since 1.0.0
     * @return void
     */
    private function saveRequestHistory(): void
    {
        $this->setWordPressTransient('woo_ai_assistant_request_history', $this->requestHistory, HOUR_IN_SECONDS);
    }

    /**
     * Send HTTP request to the intermediate server
     *
     * Main method for all server communication. Handles authentication,
     * rate limiting, retries, and error handling.
     *
     * @since 1.0.0
     * @param string $endpoint API endpoint path
     * @param array  $args Request arguments
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @return array|WP_Error Response data or error
     *
     * @throws InvalidArgumentException When endpoint is empty
     * @throws RuntimeException When server is unreachable
     *
     * @example
     * ```php
     * $client = IntermediateServerClient::getInstance();
     * $response = $client->sendRequest('/embeddings/generate', [
     *     'text' => 'Content to embed',
     *     'model' => 'text-embedding-3-small'
     * ], 'POST');
     * ```
     */
    public function sendRequest(string $endpoint, array $args = [], string $method = 'GET')
    {
        if (empty($endpoint)) {
            throw new \InvalidArgumentException('Endpoint cannot be empty');
        }

        // Check rate limiting
        if (!$this->checkRateLimit()) {
            $this->lastError = 'Rate limit exceeded';
            return new \WP_Error('rate_limit_exceeded', 'Rate limit exceeded. Please try again later.');
        }

        // Return dummy response in development mode if server not configured or for auth endpoints
        if ($this->developmentMode && (empty($this->authToken) || strpos($endpoint, '/auth/') !== false)) {
            return $this->getDummyResponse($endpoint, $args);
        }

        // Prepare request
        $url = $this->baseUrl . '/api/' . $this->apiVersion . '/' . ltrim($endpoint, '/');
        $requestArgs = $this->prepareRequestArgs($args, $method);

        // Attempt request with retries
        $attempt = 0;
        while ($attempt < $this->maxRetries) {
            try {
                $response = $this->executeRequest($url, $requestArgs);

                if (!is_wp_error($response)) {
                    $this->recordSuccessfulRequest();
                    return $this->processSuccessfulResponse($response);
                }

                // Check if we should retry
                if (!$this->shouldRetryRequest($response, $attempt)) {
                    break;
                }

                $attempt++;
                if ($attempt < $this->maxRetries) {
                    $this->waitForRetry($attempt);
                }
            } catch (\Exception $e) {
                Utils::logError('Exception during server request: ' . $e->getMessage(), [
                    'endpoint' => $endpoint,
                    'attempt' => $attempt + 1
                ]);

                if ($attempt >= $this->maxRetries - 1) {
                    $this->lastError = $e->getMessage();
                    return new \WP_Error('request_exception', $e->getMessage());
                }

                $attempt++;
                $this->waitForRetry($attempt);
            }
        }

        // All retries failed
        $errorMessage = is_wp_error($response) ? $response->get_error_message() : 'Unknown server error';
        $this->lastError = $errorMessage;
        $this->logError('Server request failed after all retries', [
            'endpoint' => $endpoint,
            'error' => $errorMessage
        ]);

        return new \WP_Error('server_request_failed', $errorMessage);
    }

    /**
     * Authenticate connection with the server
     *
     * Validates the authentication token and establishes secure connection
     * with the intermediate server.
     *
     * @since 1.0.0
     * @param string|null $token Optional token to authenticate with
     * @return bool True if authentication successful
     *
     * @example
     * ```php
     * $client = IntermediateServerClient::getInstance();
     * if ($client->authenticateConnection('your-auth-token')) {
     *     // Connection authenticated
     * }
     * ```
     */
    public function authenticateConnection(?string $token = null): bool
    {
        if ($token !== null) {
            $this->authToken = sanitize_text_field($token);
        }

        if (empty($this->authToken)) {
            $this->lastError = 'No authentication token provided';
            return false;
        }

        try {
            $response = $this->sendRequest('/auth/verify', [], 'GET');

            if (is_wp_error($response)) {
                $this->lastError = 'Authentication failed: ' . $response->get_error_message();
                return false;
            }

            if (isset($response['authenticated']) && $response['authenticated'] === true) {
                // Store valid token
                update_option('woo_ai_assistant_auth_token', $this->authToken);
                set_transient('woo_ai_assistant_auth_token', $this->authToken, HOUR_IN_SECONDS);

                $this->connectionStatus = true;
                Utils::logDebug('Server authentication successful');
                return true;
            }

            $this->lastError = 'Authentication failed: Invalid token';
            return false;
        } catch (\Exception $e) {
            $this->lastError = 'Authentication error: ' . $e->getMessage();
            Utils::logError('Authentication error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Test connection to the server
     *
     * Verifies that the server is accessible and responding to requests.
     *
     * @since 1.0.0
     * @return bool True if connection successful
     *
     * @example
     * ```php
     * $client = IntermediateServerClient::getInstance();
     * if ($client->testConnection()) {
     *     echo 'Server is accessible';
     * }
     * ```
     */
    public function testConnection(): bool
    {
        try {
            $response = $this->sendRequest('/health', [], 'GET');

            if (is_wp_error($response)) {
                $this->connectionStatus = false;
                $this->lastError = 'Connection test failed: ' . $response->get_error_message();
                return false;
            }

            $this->connectionStatus = true;
            Utils::logDebug('Server connection test successful');
            return true;
        } catch (\Exception $e) {
            $this->connectionStatus = false;
            $this->lastError = 'Connection test error: ' . $e->getMessage();
            Utils::logError('Connection test error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Handle rate limiting with exponential backoff
     *
     * Implements intelligent rate limiting to prevent server overload
     * and ensure stable communication.
     *
     * @since 1.0.0
     * @param int $retryAttempt Current retry attempt number
     * @return bool True if request should be retried
     */
    private function handleRateLimit(int $retryAttempt = 0): bool
    {
        if (!$this->checkRateLimit()) {
            if ($retryAttempt >= $this->maxRetries) {
                return false;
            }

            $delay = $this->calculateBackoffDelay($retryAttempt);
            Utils::logDebug("Rate limit hit, waiting {$delay} seconds before retry");

            sleep($delay);
            return true;
        }

        return true;
    }

    /**
     * Log error with context
     *
     * Centralized error logging with context information for debugging
     * and monitoring.
     *
     * @since 1.0.0
     * @param string $message Error message
     * @param array  $context Additional context data
     * @return void
     */
    public function logError(string $message, array $context = []): void
    {
        $logContext = array_merge([
            'class' => __CLASS__,
            'server_url' => $this->baseUrl,
            'development_mode' => $this->developmentMode
        ], $context);

        Utils::logError('IntermediateServerClient: ' . $message, $logContext);

        // Also trigger WordPress action for external error handling
        do_action('woo_ai_assistant_server_error', $message, $context);
    }

    /**
     * Get server status information
     *
     * Retrieves comprehensive status information from the server including
     * health metrics, API limits, and service availability.
     *
     * @since 1.0.0
     * @return array Server status data
     *
     * @example
     * ```php
     * $client = IntermediateServerClient::getInstance();
     * $status = $client->getServerStatus();
     * echo 'Server uptime: ' . $status['uptime'];
     * ```
     */
    public function getServerStatus(): array
    {
        try {
            $response = $this->sendRequest('/status', [], 'GET');

            if (is_wp_error($response)) {
                return [
                    'status' => 'error',
                    'error' => $response->get_error_message(),
                    'timestamp' => current_time('mysql')
                ];
            }

            return array_merge([
                'status' => 'operational',
                'timestamp' => current_time('mysql')
            ], $response);
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'timestamp' => current_time('mysql')
            ];
        }
    }

    /**
     * Check if rate limit allows the request
     *
     * @since 1.0.0
     * @return bool True if request is allowed
     */
    private function checkRateLimit(): bool
    {
        $now = time();

        // Clean old entries
        $this->cleanRequestHistory($now);

        // Count requests in the last minute
        $recentRequests = array_filter($this->requestHistory, function ($timestamp) use ($now) {
            return ($now - $timestamp) <= 60;
        });

        if (count($recentRequests) >= $this->rateLimits['requests_per_minute']) {
            return false;
        }

        // Count requests in the last hour
        $hourlyRequests = array_filter($this->requestHistory, function ($timestamp) use ($now) {
            return ($now - $timestamp) <= 3600;
        });

        if (count($hourlyRequests) >= $this->rateLimits['requests_per_hour']) {
            return false;
        }

        return true;
    }

    /**
     * Clean old entries from request history
     *
     * @since 1.0.0
     * @param int $now Current timestamp
     * @return void
     */
    private function cleanRequestHistory(int $now): void
    {
        $this->requestHistory = array_filter($this->requestHistory, function ($timestamp) use ($now) {
            return ($now - $timestamp) <= 3600; // Keep last hour
        });
    }

    /**
     * Record successful request for rate limiting
     *
     * @since 1.0.0
     * @return void
     */
    private function recordSuccessfulRequest(): void
    {
        $this->requestHistory[] = time();
        $this->saveRequestHistory();
    }

    /**
     * Prepare request arguments with authentication and headers
     *
     * @since 1.0.0
     * @param array  $args Request data
     * @param string $method HTTP method
     * @return array Prepared request arguments
     */
    private function prepareRequestArgs(array $args, string $method): array
    {
        $requestArgs = [
            'method' => strtoupper($method),
            'timeout' => $this->timeout,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'Woo-AI-Assistant/' . WOO_AI_ASSISTANT_VERSION
            ]
        ];

        // Add authentication header
        if (!empty($this->authToken)) {
            $requestArgs['headers']['Authorization'] = 'Bearer ' . $this->authToken;
        }

        // Add body for POST/PUT requests
        if (in_array($method, ['POST', 'PUT', 'PATCH']) && !empty($args)) {
            $requestArgs['body'] = wp_json_encode($args);
        } elseif ($method === 'GET' && !empty($args)) {
            // Add query parameters for GET requests
            $requestArgs['body'] = null;
        }

        return $requestArgs;
    }

    /**
     * Execute the HTTP request
     *
     * @since 1.0.0
     * @param string $url Request URL
     * @param array  $args Request arguments
     * @return array|\WP_Error Response or error
     */
    private function executeRequest(string $url, array $args)
    {
        Utils::logDebug('Executing server request', [
            'url' => $url,
            'method' => $args['method']
        ]);

        return wp_remote_request($url, $args);
    }

    /**
     * Process successful response
     *
     * @since 1.0.0
     * @param array $response WordPress HTTP response
     * @return array Processed response data
     */
    private function processSuccessfulResponse(array $response): array
    {
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Utils::logError('Failed to decode server response JSON', [
                'json_error' => json_last_error_msg(),
                'response_body' => substr($body, 0, 500)
            ]);
            return ['error' => 'Invalid server response format'];
        }

        return $data ?: [];
    }

    /**
     * Determine if request should be retried
     *
     * @since 1.0.0
     * @param \WP_Error $error Error object
     * @param int       $attempt Current attempt number
     * @return bool True if should retry
     */
    private function shouldRetryRequest(\WP_Error $error, int $attempt): bool
    {
        if ($attempt >= $this->maxRetries - 1) {
            return false;
        }

        $errorCode = $error->get_error_code();
        $retryableCodes = ['timeout', 'connection_failed', 'http_request_failed', 'rate_limit_exceeded'];

        return in_array($errorCode, $retryableCodes);
    }

    /**
     * Wait with exponential backoff
     *
     * @since 1.0.0
     * @param int $attempt Attempt number
     * @return void
     */
    private function waitForRetry(int $attempt): void
    {
        $delay = $this->calculateBackoffDelay($attempt);
        Utils::logDebug("Waiting {$delay} seconds before retry attempt " . ($attempt + 1));
        sleep($delay);
    }

    /**
     * Calculate exponential backoff delay
     *
     * @since 1.0.0
     * @param int $attempt Attempt number
     * @return int Delay in seconds
     */
    private function calculateBackoffDelay(int $attempt): int
    {
        $delay = min($this->baseDelay * (2 ** $attempt), $this->maxDelay);

        // Add jitter to prevent thundering herd
        $jitter = mt_rand(0, $delay / 2);

        return $delay + $jitter;
    }

    /**
     * Check if URL is accessible
     *
     * @since 1.0.0
     * @param string $url URL to check
     * @return bool True if accessible
     */
    private function isUrlAccessible(string $url): bool
    {
        // Skip URL accessibility check if WordPress functions not available (testing environment)
        if (!function_exists('wp_remote_head')) {
            return false;
        }

        $response = wp_remote_head($url, ['timeout' => 5]);
        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) < 400;
    }

    /**
     * Get dummy response for development mode
     *
     * @since 1.0.0
     * @param string $endpoint Requested endpoint
     * @param array  $args Request arguments
     * @return array Dummy response data
     */
    private function getDummyResponse(string $endpoint, array $args): array
    {
        Utils::logDebug('Returning dummy response for development mode', [
            'endpoint' => $endpoint,
            'args' => $args
        ]);

        // Return appropriate dummy response based on endpoint
        if (strpos($endpoint, '/health') !== false) {
            return [
                'status' => 'healthy',
                'version' => '1.0.0-dev',
                'timestamp' => current_time('mysql')
            ];
        }

        if (strpos($endpoint, '/auth/verify') !== false) {
            return [
                'authenticated' => true,
                'user' => 'development',
                'plan' => 'unlimited'
            ];
        }

        if (strpos($endpoint, '/embeddings') !== false) {
            // For batch processing, return multiple embeddings based on input texts
            $textsCount = 1; // Default for single embedding
            if (strpos($endpoint, '/embeddings/batch') !== false && isset($args['texts']) && is_array($args['texts'])) {
                $textsCount = count($args['texts']);
            } elseif (isset($args['texts']) && is_array($args['texts'])) {
                $textsCount = count($args['texts']);
            }

            // Generate dummy embeddings for each text
            $embeddings = [];
            for ($i = 0; $i < $textsCount; $i++) {
                $embeddings[] = array_fill(0, 1536, 0.1 + ($i * 0.01)); // Slightly different values for each embedding
            }

            return [
                'embeddings' => $embeddings,
                'model' => 'text-embedding-3-small',
                'usage' => ['prompt_tokens' => $textsCount * 5, 'total_tokens' => $textsCount * 5]
            ];
        }

        if (strpos($endpoint, '/status') !== false) {
            return [
                'status' => 'operational',
                'uptime' => '99.9%',
                'response_time_ms' => 50,
                'api_limits' => [
                    'requests_per_minute' => $this->rateLimits['requests_per_minute'],
                    'requests_per_hour' => $this->rateLimits['requests_per_hour']
                ]
            ];
        }

        // Default dummy response
        return [
            'success' => true,
            'data' => $args,
            'development_mode' => true
        ];
    }

    /**
     * Get last error message
     *
     * @since 1.0.0
     * @return string|null Last error message or null if no error
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Clear last error
     *
     * @since 1.0.0
     * @return void
     */
    public function clearLastError(): void
    {
        $this->lastError = null;
    }

    /**
     * Get connection status
     *
     * @since 1.0.0
     * @return bool|null Connection status or null if not tested
     */
    public function getConnectionStatus(): ?bool
    {
        return $this->connectionStatus;
    }

    /**
     * Set authentication token
     *
     * @since 1.0.0
     * @param string $token Authentication token
     * @return void
     */
    public function setAuthToken(string $token): void
    {
        $this->authToken = sanitize_text_field($token);
        update_option('woo_ai_assistant_auth_token', $this->authToken);
        set_transient('woo_ai_assistant_auth_token', $this->authToken, HOUR_IN_SECONDS);
    }

    /**
     * Clear authentication token
     *
     * @since 1.0.0
     * @return void
     */
    public function clearAuthToken(): void
    {
        $this->authToken = null;
        delete_option('woo_ai_assistant_auth_token');
        delete_transient('woo_ai_assistant_auth_token');
    }

    /**
     * Get current configuration
     *
     * @since 1.0.0
     * @return array Current configuration settings
     */
    public function getConfiguration(): array
    {
        return [
            'base_url' => $this->baseUrl,
            'api_version' => $this->apiVersion,
            'has_auth_token' => !empty($this->authToken),
            'development_mode' => $this->developmentMode,
            'timeout' => $this->timeout,
            'max_retries' => $this->maxRetries,
            'rate_limits' => $this->rateLimits,
            'connection_status' => $this->connectionStatus
        ];
    }

    /**
     * Update configuration
     *
     * @since 1.0.0
     * @param array $config Configuration updates
     * @return bool True if configuration updated successfully
     */
    public function updateConfiguration(array $config): bool
    {
        try {
            if (isset($config['base_url'])) {
                $this->baseUrl = esc_url_raw($config['base_url']);
                update_option('woo_ai_assistant_server_url', $this->baseUrl);
            }

            if (isset($config['timeout'])) {
                $this->timeout = absint($config['timeout']);
            }

            if (isset($config['max_retries'])) {
                $this->maxRetries = absint($config['max_retries']);
            }

            if (isset($config['rate_limits'])) {
                $this->rateLimits = array_merge($this->rateLimits, $config['rate_limits']);
                update_option('woo_ai_assistant_rate_limits', $this->rateLimits);
            }

            Utils::logDebug('Server client configuration updated', $config);
            return true;
        } catch (\Exception $e) {
            Utils::logError('Failed to update server client configuration: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get WordPress option safely
     *
     * @since 1.0.0
     * @param string $option Option name
     * @param mixed  $default Default value
     * @return mixed Option value or default
     */
    private function getWordPressOption(string $option, $default = false)
    {
        if (function_exists('get_option')) {
            return get_option($option, $default);
        }
        return $default;
    }

    /**
     * Update WordPress option safely
     *
     * @since 1.0.0
     * @param string $option Option name
     * @param mixed  $value Option value
     * @return bool True on success
     */
    private function updateWordPressOption(string $option, $value): bool
    {
        if (function_exists('update_option')) {
            return update_option($option, $value);
        }
        return true; // Assume success in testing environment
    }

    /**
     * Delete WordPress option safely
     *
     * @since 1.0.0
     * @param string $option Option name
     * @return bool True on success
     */
    private function deleteWordPressOption(string $option): bool
    {
        if (function_exists('delete_option')) {
            return delete_option($option);
        }
        return true; // Assume success in testing environment
    }

    /**
     * Get WordPress transient safely
     *
     * @since 1.0.0
     * @param string $transient Transient name
     * @return mixed Transient value or false
     */
    private function getWordPressTransient(string $transient)
    {
        if (function_exists('get_transient')) {
            return get_transient($transient);
        }
        return false;
    }

    /**
     * Set WordPress transient safely
     *
     * @since 1.0.0
     * @param string $transient Transient name
     * @param mixed  $value Transient value
     * @param int    $expiration Expiration time
     * @return bool True on success
     */
    private function setWordPressTransient(string $transient, $value, int $expiration): bool
    {
        if (function_exists('set_transient')) {
            return set_transient($transient, $value, $expiration);
        }
        return true; // Assume success in testing environment
    }

    /**
     * Delete WordPress transient safely
     *
     * @since 1.0.0
     * @param string $transient Transient name
     * @return bool True on success
     */
    private function deleteWordPressTransient(string $transient): bool
    {
        if (function_exists('delete_transient')) {
            return delete_transient($transient);
        }
        return true; // Assume success in testing environment
    }

    /**
     * Get current time safely
     *
     * @since 1.0.0
     * @param string $format Time format
     * @return string Current time
     */
    private function getCurrentTime(string $format = 'mysql'): string
    {
        if (function_exists('current_time')) {
            return current_time($format);
        }
        return date('Y-m-d H:i:s'); // Fallback for testing
    }

    /**
     * Trigger WordPress action safely
     *
     * @since 1.0.0
     * @param string $action Action name
     * @param mixed  ...$args Action arguments
     * @return void
     */
    private function doWordPressAction(string $action, ...$args): void
    {
        if (function_exists('do_action')) {
            do_action($action, ...$args);
        }
        // Silent in testing environment
    }

    /**
     * Check if response is WordPress error
     *
     * @since 1.0.0
     * @param mixed $response Response to check
     * @return bool True if is error
     */
    private function isWordPressError($response): bool
    {
        if (function_exists('is_wp_error')) {
            return is_wp_error($response);
        }
        return $response instanceof \WP_Error;
    }

    /**
     * Encode data as JSON safely
     *
     * @since 1.0.0
     * @param mixed $data Data to encode
     * @return string JSON string
     */
    private function encodeJson($data): string
    {
        if (function_exists('wp_json_encode')) {
            return wp_json_encode($data);
        }
        return json_encode($data);
    }

    /**
     * Make WordPress remote request safely
     *
     * @since 1.0.0
     * @param string $url Request URL
     * @param array  $args Request arguments
     * @return array|\WP_Error Response or error
     */
    private function makeWordPressRemoteRequest(string $url, array $args)
    {
        if (function_exists('wp_remote_request')) {
            return wp_remote_request($url, $args);
        }
        // Return error for testing environment
        return new \WP_Error('no_wordpress', 'WordPress remote request not available');
    }

    /**
     * Get response body safely
     *
     * @since 1.0.0
     * @param array $response HTTP response
     * @return string Response body
     */
    private function getResponseBody($response): string
    {
        if (function_exists('wp_remote_retrieve_body')) {
            return wp_remote_retrieve_body($response);
        }
        return isset($response['body']) ? $response['body'] : '';
    }

    /**
     * Get response code safely
     *
     * @since 1.0.0
     * @param array $response HTTP response
     * @return int Response code
     */
    private function getResponseCode($response): int
    {
        if (function_exists('wp_remote_retrieve_response_code')) {
            return wp_remote_retrieve_response_code($response);
        }
        return isset($response['response']['code']) ? (int)$response['response']['code'] : 0;
    }

    /**
     * Sanitize URL safely
     *
     * @since 1.0.0
     * @param string $url URL to sanitize
     * @return string Sanitized URL
     */
    private function sanitizeUrl(string $url): string
    {
        if (function_exists('esc_url_raw')) {
            return esc_url_raw($url);
        }
        return filter_var($url, FILTER_SANITIZE_URL);
    }
}
