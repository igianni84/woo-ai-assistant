<?php

/**
 * API Exception Class
 *
 * Exception for API-related errors including HTTP requests,
 * authentication failures, and server communication issues.
 *
 * @package WooAiAssistant
 * @subpackage Common\Exceptions
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Common\Exceptions;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ApiException
 *
 * Exception for API-related errors.
 *
 * @since 1.0.0
 */
class ApiException extends WooAiException
{
    /**
     * HTTP status code
     *
     * @var int
     */
    protected int $httpStatusCode;

    /**
     * API service name
     *
     * @var string
     */
    protected string $service;

    /**
     * API endpoint
     *
     * @var string
     */
    protected string $endpoint;

    /**
     * Request data
     *
     * @var array
     */
    protected array $requestData;

    /**
     * Response data
     *
     * @var array
     */
    protected array $responseData;

    /**
     * Constructor
     *
     * @param string $message Error message
     * @param int $httpStatusCode HTTP status code
     * @param string $service API service name
     * @param string $endpoint API endpoint
     * @param array $requestData Request data (sanitized)
     * @param array $responseData Response data
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message = '',
        int $httpStatusCode = 0,
        string $service = '',
        string $endpoint = '',
        array $requestData = [],
        array $responseData = [],
        ?\Throwable $previous = null
    ) {
        $this->httpStatusCode = $httpStatusCode;
        $this->service = $service;
        $this->endpoint = $endpoint;
        $this->requestData = $requestData;
        $this->responseData = $responseData;

        $context = [
            'http_status' => $httpStatusCode,
            'service' => $service,
            'endpoint' => $this->sanitizeEndpointForLogging($endpoint),
            'request_size' => count($requestData),
            'response_size' => count($responseData),
            'has_response_error' => isset($responseData['error'])
        ];

        parent::__construct($message, 'API_ERROR', $context, $previous);
    }

    /**
     * Get HTTP status code
     *
     * @return int HTTP status code
     */
    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    /**
     * Get API service name
     *
     * @return string Service name
     */
    public function getService(): string
    {
        return $this->service;
    }

    /**
     * Get API endpoint
     *
     * @return string Endpoint URL
     */
    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    /**
     * Get request data (sanitized)
     *
     * @return array Request data
     */
    public function getRequestData(): array
    {
        return $this->requestData;
    }

    /**
     * Get response data
     *
     * @return array Response data
     */
    public function getResponseData(): array
    {
        return $this->responseData;
    }

    /**
     * Check if this is a rate limit error
     *
     * @return bool True if rate limited
     */
    public function isRateLimited(): bool
    {
        return in_array($this->httpStatusCode, [429, 503]) ||
               isset($this->responseData['error']['code']) &&
               in_array($this->responseData['error']['code'], ['rate_limit_exceeded', 'quota_exceeded']);
    }

    /**
     * Check if this is an authentication error
     *
     * @return bool True if authentication failed
     */
    public function isAuthenticationError(): bool
    {
        return $this->httpStatusCode === 401 ||
               isset($this->responseData['error']['code']) &&
               in_array($this->responseData['error']['code'], ['invalid_api_key', 'unauthorized']);
    }

    /**
     * Check if this is a server error (5xx)
     *
     * @return bool True if server error
     */
    public function isServerError(): bool
    {
        return $this->httpStatusCode >= 500;
    }

    /**
     * Check if this is a client error (4xx)
     *
     * @return bool True if client error
     */
    public function isClientError(): bool
    {
        return $this->httpStatusCode >= 400 && $this->httpStatusCode < 500;
    }

    /**
     * Get retry delay based on error type
     *
     * @return int Delay in seconds, 0 if should not retry
     */
    public function getRetryDelay(): int
    {
        if ($this->isRateLimited()) {
            return 60; // Wait 1 minute for rate limits
        }

        if ($this->isServerError()) {
            return 30; // Wait 30 seconds for server errors
        }

        if ($this->isClientError() && !$this->isAuthenticationError()) {
            return 5; // Short delay for client errors (except auth)
        }

        return 0; // Don't retry authentication errors
    }

    /**
     * Should this error be retried
     *
     * @return bool True if should retry
     */
    public function shouldRetry(): bool
    {
        return $this->getRetryDelay() > 0;
    }

    /**
     * Convert to array with API-specific data
     *
     * @param bool $includeTrace Whether to include stack trace
     * @return array Exception data array
     */
    public function toArray(bool $includeTrace = false): array
    {
        $data = parent::toArray($includeTrace);
        
        $data['api_error'] = [
            'http_status' => $this->httpStatusCode,
            'service' => $this->service,
            'endpoint' => $this->sanitizeEndpointForLogging($this->endpoint),
            'is_rate_limited' => $this->isRateLimited(),
            'is_auth_error' => $this->isAuthenticationError(),
            'is_server_error' => $this->isServerError(),
            'should_retry' => $this->shouldRetry(),
            'retry_delay' => $this->getRetryDelay()
        ];

        if (defined('WOO_AI_ASSISTANT_DEBUG') && WOO_AI_ASSISTANT_DEBUG) {
            $data['debug'] = [
                'request_data' => $this->requestData,
                'response_data' => $this->responseData
            ];
        }

        return $data;
    }

    /**
     * Sanitize endpoint URL for logging (remove sensitive data)
     *
     * @param string $endpoint Endpoint URL
     * @return string Sanitized endpoint
     */
    private function sanitizeEndpointForLogging(string $endpoint): string
    {
        $parsed = parse_url($endpoint);
        
        if (!$parsed) {
            return '[invalid-endpoint]';
        }
        
        $sanitized = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? 'unknown');
        
        if (isset($parsed['port'])) {
            $sanitized .= ':' . $parsed['port'];
        }
        
        if (isset($parsed['path'])) {
            $sanitized .= $parsed['path'];
        }
        
        // Don't include query parameters or fragments as they might contain sensitive data
        return $sanitized;
    }
}