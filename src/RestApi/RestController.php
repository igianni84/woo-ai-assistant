<?php

/**
 * REST API Controller Class
 *
 * Central controller for managing all REST API endpoints in the plugin.
 * Handles namespace registration, authentication, security, and endpoint routing.
 *
 * @package WooAiAssistant
 * @subpackage RestApi
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\RestApi;

use WooAiAssistant\Common\Traits\Singleton;
use WooAiAssistant\Common\Logger;
use WooAiAssistant\Common\Utils;
use WooAiAssistant\Common\Validator;
use WooAiAssistant\Common\Sanitizer;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class RestController
 *
 * Manages REST API endpoints and handles authentication, validation, and security.
 *
 * @since 1.0.0
 */
class RestController
{
    use Singleton;

    /**
     * REST API namespace
     *
     * @var string
     */
    const NAMESPACE = 'woo-ai-assistant/v1';

    /**
     * Registered endpoints
     *
     * @var array
     */
    private array $endpoints = [];

    /**
     * Rate limiting cache
     *
     * @var array
     */
    private array $rateLimitCache = [];

    /**
     * Initialize REST API controller
     *
     * @return void
     */
    protected function init(): void
    {
        add_action('rest_api_init', [$this, 'registerEndpoints']);
        add_filter('rest_pre_dispatch', [$this, 'addSecurityHeaders'], 10, 3);
        add_filter('rest_pre_serve_request', [$this, 'addCorsHeaders'], 10, 4);

        Logger::debug('REST API Controller initialized');
    }

    /**
     * Register all REST API endpoints
     *
     * @return void
     */
    public function registerEndpoints(): void
    {
        Logger::info('Registering REST API endpoints');

        // Register base route for API health check
        register_rest_route(
            self::NAMESPACE,
            '/health',
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'healthCheck'],
                'permission_callback' => '__return_true',
                'args' => []
            ]
        );

        // Register configuration endpoint
        register_rest_route(
            self::NAMESPACE,
            '/config',
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getWidgetConfig'],
                'permission_callback' => [$this, 'checkPublicPermission'],
                'args' => [
                    'context' => [
                        'description' => 'Request context (page, product, cart, etc.)',
                        'type' => 'string',
                        'default' => 'general',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'user_id' => [
                        'description' => 'Current user ID (optional)',
                        'type' => 'integer',
                        'sanitize_callback' => 'absint',
                    ]
                ]
            ]
        );

        // Load and register endpoint classes
        $this->loadEndpointClasses();

        // Apply filter to allow endpoint registration by other plugins/modules
        do_action('woo_ai_assistant_register_rest_endpoints', self::NAMESPACE);

        Logger::info('REST API endpoints registered', [
            'namespace' => self::NAMESPACE,
            'endpoint_count' => count($this->endpoints)
        ]);
    }

    /**
     * Load and initialize endpoint classes
     *
     * @return void
     */
    private function loadEndpointClasses(): void
    {
        $endpointClasses = [
            'chat' => 'WooAiAssistant\RestApi\Endpoints\ChatEndpoint',
            'action' => 'WooAiAssistant\RestApi\Endpoints\ActionEndpoint',
            'rating' => 'WooAiAssistant\RestApi\Endpoints\RatingEndpoint',
            'config' => 'WooAiAssistant\RestApi\Endpoints\ConfigEndpoint'
        ];

        foreach ($endpointClasses as $key => $className) {
            try {
                if (class_exists($className)) {
                    $endpoint = new $className();
                    if (method_exists($endpoint, 'registerRoutes')) {
                        $endpoint->registerRoutes(self::NAMESPACE);
                        $this->endpoints[$key] = $endpoint;
                        Logger::debug("Endpoint registered: {$key}");
                    }
                }
            } catch (Exception $e) {
                Logger::error("Failed to load endpoint: {$key}", [
                    'class' => $className,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Health check endpoint
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public function healthCheck(WP_REST_Request $request): WP_REST_Response
    {
        $healthData = [
            'status' => 'healthy',
            'timestamp' => current_time('timestamp'),
            'version' => Utils::getVersion(),
            'wordpress_version' => get_bloginfo('version'),
            'woocommerce_active' => Utils::isWooCommerceActive(),
            'php_version' => PHP_VERSION,
            'memory_usage' => [
                'current' => memory_get_usage(true),
                'peak' => memory_get_peak_usage(true)
            ]
        ];

        // Add development mode info if in dev mode
        if (Utils::isDevelopmentMode()) {
            $healthData['development_mode'] = true;
            $healthData['debug_enabled'] = defined('WP_DEBUG') && WP_DEBUG;
        }

        return new WP_REST_Response($healthData, 200);
    }

    /**
     * Get widget configuration for frontend
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public function getWidgetConfig(WP_REST_Request $request): WP_REST_Response
    {
        $context = $request->get_param('context') ?: 'general';
        $userId = $request->get_param('user_id') ?: get_current_user_id();

        // Apply rate limiting
        if (!$this->checkRateLimit($request)) {
            return new WP_Error(
                'rate_limit_exceeded',
                'Rate limit exceeded. Please try again later.',
                ['status' => 429]
            );
        }

        $config = [
            'api_base_url' => rest_url(self::NAMESPACE),
            'nonce' => wp_create_nonce('woo_ai_assistant_nonce'),
            'context' => $context,
            'user' => [
                'id' => $userId,
                'is_logged_in' => is_user_logged_in(),
                'display_name' => $userId ? get_userdata($userId)->display_name : '',
            ],
            'woocommerce' => [
                'active' => Utils::isWooCommerceActive(),
                'currency' => Utils::isWooCommerceActive() ? get_woocommerce_currency() : '',
                'currency_symbol' => Utils::isWooCommerceActive() ? get_woocommerce_currency_symbol() : '',
            ],
            'features' => [
                'chat_enabled' => true,
                'product_recommendations' => Utils::isWooCommerceActive(),
                'coupon_generation' => Utils::isWooCommerceActive(),
                'human_handoff' => false, // Will be enabled in later tasks
            ],
            'settings' => [
                'widget_position' => get_option('woo_ai_widget_position', 'bottom-right'),
                'widget_theme' => get_option('woo_ai_widget_theme', 'light'),
                'greeting_message' => get_option('woo_ai_greeting_message', __('Hi! How can I help you today?', 'woo-ai-assistant')),
            ]
        ];

        // Filter config to allow customization
        $config = apply_filters('woo_ai_assistant_widget_config', $config, $context, $userId);

        return new WP_REST_Response($config, 200);
    }

    /**
     * Check public permission (no authentication required)
     *
     * @param WP_REST_Request $request Request object
     * @return bool True if permission granted
     */
    public function checkPublicPermission(WP_REST_Request $request): bool
    {
        // Public endpoints - no authentication required
        return true;
    }

    /**
     * Check user permission (authentication required)
     *
     * @param WP_REST_Request $request Request object
     * @return bool|WP_Error True if permission granted, WP_Error otherwise
     */
    public function checkUserPermission(WP_REST_Request $request)
    {
        if (!is_user_logged_in()) {
            return new WP_Error(
                'authentication_required',
                'Authentication required',
                ['status' => 401]
            );
        }

        return true;
    }

    /**
     * Check admin permission
     *
     * @param WP_REST_Request $request Request object
     * @return bool|WP_Error True if permission granted, WP_Error otherwise
     */
    public function checkAdminPermission(WP_REST_Request $request)
    {
        if (!current_user_can('manage_woocommerce')) {
            return new WP_Error(
                'insufficient_permissions',
                'Insufficient permissions',
                ['status' => 403]
            );
        }

        return $this->verifyNonce($request);
    }

    /**
     * Verify nonce for security
     *
     * @param WP_REST_Request $request Request object
     * @return bool|WP_Error True if valid, WP_Error otherwise
     */
    public function verifyNonce(WP_REST_Request $request)
    {
        $nonce = $request->get_header('X-WP-Nonce') ?: $request->get_param('_wpnonce');

        if (!$nonce) {
            return new WP_Error(
                'missing_nonce',
                'Security token is missing',
                ['status' => 400]
            );
        }

        if (!wp_verify_nonce($nonce, 'woo_ai_assistant_nonce')) {
            return new WP_Error(
                'invalid_nonce',
                'Security token is invalid',
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Check rate limiting
     *
     * @param WP_REST_Request $request Request object
     * @param int $limit Maximum requests per minute
     * @return bool True if within limit
     */
    public function checkRateLimit(WP_REST_Request $request, int $limit = 60): bool
    {
        if (Utils::isDevelopmentMode()) {
            // No rate limiting in development mode
            return true;
        }

        $clientIp = $this->getClientIp($request);
        $currentMinute = floor(time() / 60);
        $cacheKey = "rate_limit_{$clientIp}_{$currentMinute}";

        $currentCount = wp_cache_get($cacheKey, 'woo_ai_assistant_rate_limit') ?: 0;

        if ($currentCount >= $limit) {
            Logger::warning('Rate limit exceeded', [
                'ip' => $clientIp,
                'count' => $currentCount,
                'limit' => $limit
            ]);
            return false;
        }

        wp_cache_set($cacheKey, $currentCount + 1, 'woo_ai_assistant_rate_limit', 60);
        return true;
    }

    /**
     * Get client IP address
     *
     * @param WP_REST_Request $request Request object
     * @return string Client IP address
     */
    private function getClientIp(WP_REST_Request $request): string
    {
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = sanitize_text_field($_SERVER[$header]);
                // Handle comma-separated IPs (from proxies)
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                return trim($ip);
            }
        }

        return '0.0.0.0';
    }

    /**
     * Add security headers to REST API responses
     *
     * @param mixed $response Response data
     * @param array $handler Route handler
     * @param WP_REST_Request $request Request object
     * @return mixed Response data
     */
    public function addSecurityHeaders($response, $handler, WP_REST_Request $request)
    {
        // Only apply to our API endpoints
        if (strpos($request->get_route(), '/' . self::NAMESPACE) !== 0) {
            return $response;
        }

        // Add security headers
        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: DENY');
            header('X-XSS-Protection: 1; mode=block');
            header('Referrer-Policy: strict-origin-when-cross-origin');

            // Add Content Security Policy for API responses
            if (!Utils::isDevelopmentMode()) {
                header("Content-Security-Policy: default-src 'self'");
            }
        }

        return $response;
    }

    /**
     * Add CORS headers for cross-origin requests
     *
     * @param bool $served Whether the request has already been served
     * @param WP_HTTP_Response $result Response object
     * @param WP_REST_Request $request Request object
     * @param WP_REST_Server $server REST server instance
     * @return bool Whether the request has been served
     */
    public function addCorsHeaders($served, $result, WP_REST_Request $request, WP_REST_Server $server): bool
    {
        // Only apply to our API endpoints
        if (strpos($request->get_route(), '/' . self::NAMESPACE) !== 0) {
            return $served;
        }

        $origin = get_http_origin();
        $allowedOrigins = $this->getAllowedOrigins();

        if (!headers_sent()) {
            if (in_array($origin, $allowedOrigins) || Utils::isDevelopmentMode()) {
                header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
                header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
                header('Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce');
                header('Access-Control-Allow-Credentials: true');
                header('Access-Control-Max-Age: 3600');
            }

            // Handle preflight requests
            if ($request->get_method() === 'OPTIONS') {
                status_header(200);
                exit;
            }
        }

        return $served;
    }

    /**
     * Get allowed CORS origins
     *
     * @return array Allowed origins
     */
    private function getAllowedOrigins(): array
    {
        $origins = [
            home_url(),
            admin_url()
        ];

        // Add development origins
        if (Utils::isDevelopmentMode()) {
            $origins = array_merge($origins, [
                'http://localhost:3000',
                'http://localhost:8080',
                'http://localhost:8888'
            ]);
        }

        // Allow filtering of origins
        return apply_filters('woo_ai_assistant_cors_origins', $origins);
    }

    /**
     * Sanitize and validate REST API request data
     *
     * @param array $data Request data
     * @param array $rules Validation rules
     * @return array|WP_Error Sanitized data or error
     */
    public function validateRequest(array $data, array $rules)
    {
        try {
            $validator = new Validator();
            $sanitizer = new Sanitizer();

            // First sanitize the data
            $sanitizedData = $sanitizer->sanitizeArray($data);

            // Then validate
            $validationResult = $validator->validate($sanitizedData, $rules);

            if ($validationResult !== true) {
                return new WP_Error(
                    'validation_failed',
                    'Request validation failed',
                    ['status' => 400, 'errors' => $validationResult]
                );
            }

            return $sanitizedData;
        } catch (Exception $e) {
            Logger::error('Request validation error', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            return new WP_Error(
                'validation_error',
                'Internal validation error',
                ['status' => 500]
            );
        }
    }

    /**
     * Get registered endpoints
     *
     * @return array Registered endpoints
     */
    public function getEndpoints(): array
    {
        return $this->endpoints;
    }

    /**
     * Get API namespace
     *
     * @return string API namespace
     */
    public function getNamespace(): string
    {
        return self::NAMESPACE;
    }

    /**
     * Check if endpoint is registered
     *
     * @param string $endpoint Endpoint name
     * @return bool True if registered
     */
    public function hasEndpoint(string $endpoint): bool
    {
        return isset($this->endpoints[$endpoint]);
    }

    /**
     * Get endpoint instance
     *
     * @param string $endpoint Endpoint name
     * @return object|null Endpoint instance or null
     */
    public function getEndpoint(string $endpoint): ?object
    {
        return $this->endpoints[$endpoint] ?? null;
    }
}
