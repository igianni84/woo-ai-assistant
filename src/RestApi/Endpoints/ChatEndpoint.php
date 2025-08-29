<?php

/**
 * Chat Endpoint Class
 *
 * Handles comprehensive chat message processing, context extraction, knowledge base
 * search integration, and AI response generation for the Woo AI Assistant plugin.
 * Implements secure message handling with proper validation, nonce verification,
 * and rate limiting.
 *
 * @package WooAiAssistant
 * @subpackage RestApi\Endpoints
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\RestApi\Endpoints;

use WooAiAssistant\Common\Utils;
use WooAiAssistant\Common\Traits\Singleton;
use WooAiAssistant\KnowledgeBase\AIManager;
use WooAiAssistant\KnowledgeBase\VectorManager;
use WooAiAssistant\Api\LicenseManager;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ChatEndpoint
 *
 * Comprehensive chat endpoint that handles message processing, context extraction,
 * knowledge base search integration, and AI response generation with enterprise-grade
 * security, validation, and performance optimization features.
 *
 * @since 1.0.0
 */
class ChatEndpoint
{
    use Singleton;

    /**
     * Maximum message length allowed
     *
     * @since 1.0.0
     * @var int
     */
    private const MAX_MESSAGE_LENGTH = 2000;

    /**
     * Maximum conversation context to maintain
     *
     * @since 1.0.0
     * @var int
     */
    private const MAX_CONVERSATION_CONTEXT = 10;

    /**
     * Message processing timeout (seconds)
     *
     * @since 1.0.0
     * @var int
     */
    private const PROCESSING_TIMEOUT = 30;

    /**
     * Context extraction cache TTL (seconds)
     *
     * @since 1.0.0
     * @var int
     */
    private const CONTEXT_CACHE_TTL = 300; // 5 minutes

    /**
     * AIManager instance for response generation
     *
     * @since 1.0.0
     * @var AIManager
     */
    private $aiManager;

    /**
     * VectorManager instance for knowledge base search
     *
     * @since 1.0.0
     * @var VectorManager
     */
    private $vectorManager;

    /**
     * LicenseManager instance for plan validation
     *
     * @since 1.0.0
     * @var LicenseManager
     */
    private $licenseManager;

    /**
     * Constructor
     *
     * Initializes the ChatEndpoint with required dependencies and sets up
     * WordPress hooks for AJAX handling and conversation management.
     *
     * @since 1.0.0
     */
    protected function __construct()
    {
        $this->initializeDependencies();
        $this->setupHooks();
    }

    /**
     * Initialize required dependencies
     *
     * @since 1.0.0
     * @return void
     */
    private function initializeDependencies(): void
    {
        try {
            // Get Main plugin instance
            $main = \WooAiAssistant\Main::getInstance();

            // Initialize AI Manager
            $this->aiManager = $main->getComponent('kb_ai_manager');
            if (!$this->aiManager) {
                Utils::logError('AIManager component not available in ChatEndpoint');
            }

            // Initialize Vector Manager
            $this->vectorManager = $main->getComponent('kb_vector_manager');
            if (!$this->vectorManager) {
                Utils::logError('VectorManager component not available in ChatEndpoint');
            }

            // Initialize License Manager
            $this->licenseManager = $main->getComponent('license_manager');
            if (!$this->licenseManager) {
                Utils::logError('LicenseManager component not available in ChatEndpoint');
            }

            Utils::logDebug('ChatEndpoint dependencies initialized successfully');
        } catch (\Exception $e) {
            Utils::logError('Error initializing ChatEndpoint dependencies: ' . $e->getMessage());
        }
    }

    /**
     * Setup WordPress hooks
     *
     * @since 1.0.0
     * @return void
     */
    private function setupHooks(): void
    {
        // AJAX hooks for chat processing
        add_action('wp_ajax_woo_ai_assistant_process_chat', [$this, 'handleAjaxChatRequest']);
        add_action('wp_ajax_nopriv_woo_ai_assistant_process_chat', [$this, 'handleAjaxChatRequest']);

        // Conversation cleanup hook
        add_action('woo_ai_assistant_cleanup_conversations', [$this, 'cleanupExpiredConversations']);
    }

    /**
     * Process chat message and generate AI response
     *
     * Main method that orchestrates the complete chat processing pipeline:
     * validation, context extraction, knowledge base search, AI response generation,
     * and conversation persistence with comprehensive security and performance optimization.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request REST API request object containing message and context data
     * @return WP_REST_Response|WP_Error Response object with AI-generated content or error
     *
     * @example
     * POST /wp-json/woo-ai-assistant/v1/chat
     * {
     *   "message": "What are your shipping options?",
     *   "conversation_id": "conv-123-456",
     *   "user_context": {
     *     "page": "shop",
     *     "product_id": 789,
     *     "user_id": 456
     *   },
     *   "nonce": "abc123xyz"
     * }
     */
    public function processMessage(WP_REST_Request $request)
    {
        $startTime = microtime(true);

        try {
            // Step 1: Validate request and security
            $validationResult = $this->validateRequest($request);
            if (is_wp_error($validationResult)) {
                return $validationResult;
            }

            // Step 2: Extract and sanitize parameters
            $message = $this->sanitizeMessage($request->get_param('message'));
            $conversationId = $this->sanitizeConversationId($request->get_param('conversation_id'));
            $userContext = $this->sanitizeUserContext($request->get_param('user_context') ?: []);
            $streamResponse = $this->sanitizeBoolParam($request->get_param('stream') ?: false);

            Utils::logDebug('Processing chat message', [
                'message_length' => strlen($message),
                'conversation_id' => $conversationId,
                'has_context' => !empty($userContext),
                'stream_response' => $streamResponse
            ]);

            // Step 3: Check license limits and permissions
            $licenseCheckResult = $this->checkLicenseLimits();
            if (is_wp_error($licenseCheckResult)) {
                return $licenseCheckResult;
            }

            // Step 4: Extract comprehensive context
            $extractedContext = $this->extractComprehensiveContext($userContext, $conversationId);

            // Step 5: Prepare conversation data
            $conversationData = $this->prepareConversationData($message, $conversationId, $extractedContext);

            // Step 6: Generate AI response with RAG
            $aiResponse = $this->generateAIResponse($conversationData, $streamResponse);

            // Step 7: Process and validate AI response
            $processedResponse = $this->processAIResponse($aiResponse, $conversationData);

            // Step 8: Save conversation to database
            $this->saveConversationData($conversationData, $processedResponse);

            // Step 9: Update usage statistics
            $this->updateUsageStatistics($conversationId);

            $executionTime = microtime(true) - $startTime;

            // Step 10: Build final response
            return $this->buildSuccessResponse([
                'conversation_id' => $conversationId,
                'response' => $processedResponse['response'],
                'confidence' => $processedResponse['confidence'] ?? 0.8,
                'sources' => $processedResponse['sources'] ?? [],
                'quick_actions' => $this->generateQuickActions($processedResponse, $extractedContext),
                'metadata' => [
                    'execution_time' => round($executionTime, 4),
                    'model_used' => $processedResponse['model_used'] ?? 'unknown',
                    'tokens_used' => $processedResponse['tokens_used'] ?? 0,
                    'context_chunks' => $processedResponse['context_chunks'] ?? 0,
                    'timestamp' => current_time('c')
                ]
            ]);
        } catch (\InvalidArgumentException $e) {
            Utils::logDebug('Validation error in chat processing: ' . $e->getMessage());
            return $this->buildErrorResponse('Invalid request parameters', 'validation_error', 400);
        } catch (\RuntimeException $e) {
            Utils::logError('Runtime error in chat processing: ' . $e->getMessage());
            return $this->buildErrorResponse('Service temporarily unavailable', 'service_error', 503);
        } catch (\Exception $e) {
            Utils::logError('Unexpected error in chat processing: ' . $e->getMessage());
            return $this->buildErrorResponse('An unexpected error occurred', 'general_error', 500);
        }
    }

    /**
     * Validate chat request for security and compliance
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return true|WP_Error True if valid, WP_Error otherwise
     */
    private function validateRequest(WP_REST_Request $request)
    {
        // Validate nonce
        $nonce = $request->get_param('nonce');
        if (!wp_verify_nonce($nonce, 'woo_ai_chat')) {
            Utils::logDebug('Invalid nonce in chat request');
            return $this->buildErrorResponse('Security check failed', 'invalid_nonce', 403);
        }

        // Check rate limiting
        if (!$this->checkRateLimit()) {
            Utils::logDebug('Rate limit exceeded for chat request');
            return $this->buildErrorResponse('Too many requests', 'rate_limit_exceeded', 429);
        }

        // Validate message content
        $message = $request->get_param('message');
        if (empty(trim($message))) {
            return $this->buildErrorResponse('Message cannot be empty', 'empty_message', 400);
        }

        if (strlen($message) > self::MAX_MESSAGE_LENGTH) {
            return $this->buildErrorResponse(
                'Message too long (max ' . self::MAX_MESSAGE_LENGTH . ' characters)',
                'message_too_long',
                400
            );
        }

        // Check for malicious content
        if ($this->containsMaliciousContent($message)) {
            Utils::logDebug('Malicious content detected in message');
            return $this->buildErrorResponse('Invalid message content', 'malicious_content', 400);
        }

        return true;
    }

    /**
     * Sanitize user message input
     *
     * @since 1.0.0
     * @param string $message Raw message input
     * @return string Sanitized message
     * @throws \InvalidArgumentException When message is invalid
     */
    private function sanitizeMessage(string $message): string
    {
        $sanitized = sanitize_textarea_field(trim($message));

        if (empty($sanitized)) {
            throw new \InvalidArgumentException('Message cannot be empty after sanitization');
        }

        return $sanitized;
    }

    /**
     * Sanitize conversation ID
     *
     * @since 1.0.0
     * @param string|null $conversationId Raw conversation ID
     * @return string Sanitized conversation ID (generates new if empty)
     */
    private function sanitizeConversationId(?string $conversationId): string
    {
        if (empty($conversationId)) {
            return $this->generateConversationId();
        }

        $sanitized = sanitize_text_field($conversationId);

        // Validate format (should be like 'conv-uuid' or similar)
        if (!preg_match('/^conv-[a-f0-9-]+$/', $sanitized)) {
            return $this->generateConversationId();
        }

        return $sanitized;
    }

    /**
     * Sanitize user context data
     *
     * @since 1.0.0
     * @param array $context Raw context data
     * @return array Sanitized context data
     */
    private function sanitizeUserContext(array $context): array
    {
        $sanitized = [];

        // Sanitize page context
        if (isset($context['page'])) {
            $sanitized['page'] = sanitize_text_field($context['page']);
        }

        // Sanitize product ID
        if (isset($context['product_id'])) {
            $sanitized['product_id'] = absint($context['product_id']);
        }

        // Sanitize user ID
        if (isset($context['user_id'])) {
            $sanitized['user_id'] = absint($context['user_id']);
        }

        // Sanitize category ID
        if (isset($context['category_id'])) {
            $sanitized['category_id'] = absint($context['category_id']);
        }

        // Sanitize URL
        if (isset($context['url'])) {
            $sanitized['url'] = esc_url_raw($context['url']);
        }

        // Sanitize language
        if (isset($context['language'])) {
            $sanitized['language'] = sanitize_text_field($context['language']);
        }

        // Sanitize viewport information
        if (isset($context['viewport']) && is_array($context['viewport'])) {
            $sanitized['viewport'] = [
                'width' => absint($context['viewport']['width'] ?? 0),
                'height' => absint($context['viewport']['height'] ?? 0),
                'is_mobile' => (bool)($context['viewport']['is_mobile'] ?? false)
            ];
        }

        return $sanitized;
    }

    /**
     * Sanitize boolean parameter
     *
     * @since 1.0.0
     * @param mixed $value Raw boolean value
     * @return bool Sanitized boolean value
     */
    private function sanitizeBoolParam($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Check rate limiting for current user/IP
     *
     * @since 1.0.0
     * @return bool True if within limits
     */
    private function checkRateLimit(): bool
    {
        $userId = get_current_user_id();
        $userKey = $userId ? "user_{$userId}" : 'ip_' . $this->getClientIp();

        $rateLimitKey = "woo_ai_chat_rate_limit_{$userKey}";
        $currentCount = get_transient($rateLimitKey) ?: 0;

        // Allow 30 requests per hour for chat
        $maxRequests = apply_filters('woo_ai_assistant_chat_rate_limit', 30);

        if ($currentCount >= $maxRequests) {
            return false;
        }

        // Increment counter
        set_transient($rateLimitKey, $currentCount + 1, HOUR_IN_SECONDS);

        return true;
    }

    /**
     * Get client IP address
     *
     * @since 1.0.0
     * @return string Client IP address
     */
    private function getClientIp(): string
    {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];

        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) && !empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Check if message contains malicious content
     *
     * @since 1.0.0
     * @param string $message Message to check
     * @return bool True if malicious content detected
     */
    private function containsMaliciousContent(string $message): bool
    {
        // Check for script injection attempts
        $maliciousPatterns = [
            '/<script[^>]*>/i',
            '/javascript:/i',
            '/onclick\s*=/i',
            '/onload\s*=/i',
            '/eval\s*\(/i',
            '/exec\s*\(/i',
            '/system\s*\(/i'
        ];

        foreach ($maliciousPatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check license limits and permissions
     *
     * @since 1.0.0
     * @return true|WP_Error True if within limits, error otherwise
     */
    private function checkLicenseLimits()
    {
        if (!$this->licenseManager) {
            // Allow basic functionality if license manager not available
            return true;
        }

        try {
            // Check if basic chat feature is enabled for current plan
            if (!$this->licenseManager->isFeatureEnabled(LicenseManager::FEATURE_BASIC_CHAT)) {
                return $this->buildErrorResponse(
                    'Chat feature not available in your plan',
                    'feature_not_available',
                    403
                );
            }

            // Check monthly conversation limits
            $usageStats = $this->licenseManager->getUsageStatistics();
            $planConfig = $this->licenseManager->getPlanConfiguration();

            $monthlyLimit = $planConfig['conversations_per_month'] ?? 0;
            $currentUsage = $usageStats['conversations_this_month'] ?? 0;

            if ($monthlyLimit > 0 && $currentUsage >= $monthlyLimit) {
                return $this->buildErrorResponse(
                    'Monthly conversation limit reached. Please upgrade your plan.',
                    'limit_exceeded',
                    403
                );
            }

            return true;
        } catch (\Exception $e) {
            Utils::logError('Error checking license limits: ' . $e->getMessage());
            // Allow request to continue if license check fails
            return true;
        }
    }

    /**
     * Extract comprehensive context from request and WordPress environment
     *
     * @since 1.0.0
     * @param array $userContext User-provided context
     * @param string $conversationId Conversation identifier
     * @return array Comprehensive context data
     */
    private function extractComprehensiveContext(array $userContext, string $conversationId): array
    {
        $cacheKey = 'woo_ai_context_' . md5(serialize($userContext));
        $cachedContext = get_transient($cacheKey);

        if ($cachedContext !== false) {
            return $cachedContext;
        }

        $context = [
            'conversation_id' => $conversationId,
            'timestamp' => current_time('c'),
            'user_info' => $this->extractUserInfo(),
            'page_context' => $this->extractPageContext($userContext),
            'product_context' => $this->extractProductContext($userContext),
            'woocommerce_context' => $this->extractWooCommerceContext($userContext),
            'site_context' => $this->extractSiteContext(),
            'session_context' => $this->extractSessionContext($conversationId)
        ];

        // Cache context for performance
        set_transient($cacheKey, $context, self::CONTEXT_CACHE_TTL);

        return $context;
    }

    /**
     * Extract user information context
     *
     * @since 1.0.0
     * @return array User context information
     */
    private function extractUserInfo(): array
    {
        $userId = get_current_user_id();

        if ($userId === 0) {
            return [
                'type' => 'guest',
                'ip' => $this->getClientIp()
            ];
        }

        $user = get_user_by('id', $userId);
        $customerData = [];

        // Get WooCommerce customer data if available
        if (class_exists('WC_Customer')) {
            try {
                $customer = new \WC_Customer($userId);
                $customerData = [
                    'billing_country' => $customer->get_billing_country(),
                    'is_paying_customer' => $customer->get_is_paying_customer(),
                    'orders_count' => $customer->get_order_count(),
                    'total_spent' => $customer->get_total_spent()
                ];
            } catch (\Exception $e) {
                Utils::logDebug('Error getting customer data: ' . $e->getMessage());
            }
        }

        return [
            'type' => 'registered',
            'id' => $userId,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
            'registration_date' => $user->user_registered,
            'customer_data' => $customerData
        ];
    }

    /**
     * Extract page context information
     *
     * @since 1.0.0
     * @param array $userContext User-provided context
     * @return array Page context information
     */
    private function extractPageContext(array $userContext): array
    {
        $context = [
            'page_type' => $userContext['page'] ?? 'unknown',
            'url' => $userContext['url'] ?? '',
            'language' => $userContext['language'] ?? get_locale(),
            'viewport' => $userContext['viewport'] ?? []
        ];

        // Add specific page information based on type
        switch ($context['page_type']) {
            case 'product':
                $context['is_product_page'] = true;
                break;
            case 'shop':
            case 'category':
                $context['is_shop_page'] = true;
                break;
            case 'cart':
                $context['is_cart_page'] = true;
                break;
            case 'checkout':
                $context['is_checkout_page'] = true;
                break;
            case 'account':
                $context['is_account_page'] = true;
                break;
        }

        return $context;
    }

    /**
     * Extract product context information
     *
     * @since 1.0.0
     * @param array $userContext User-provided context
     * @return array Product context information
     */
    private function extractProductContext(array $userContext): array
    {
        $productId = $userContext['product_id'] ?? 0;

        if (!$productId || !function_exists('wc_get_product')) {
            return [];
        }

        try {
            $product = wc_get_product($productId);
            if (!$product) {
                return [];
            }

            return [
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'type' => $product->get_type(),
                'status' => $product->get_status(),
                'featured' => $product->is_featured(),
                'price' => $product->get_price(),
                'regular_price' => $product->get_regular_price(),
                'sale_price' => $product->get_sale_price(),
                'on_sale' => $product->is_on_sale(),
                'in_stock' => $product->is_in_stock(),
                'stock_quantity' => $product->get_stock_quantity(),
                'categories' => wp_get_post_terms($productId, 'product_cat', ['fields' => 'names']),
                'tags' => wp_get_post_terms($productId, 'product_tag', ['fields' => 'names'])
            ];
        } catch (\Exception $e) {
            Utils::logDebug('Error extracting product context: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Extract WooCommerce store context
     *
     * @since 1.0.0
     * @param array $userContext User-provided context
     * @return array WooCommerce context information
     */
    private function extractWooCommerceContext(array $userContext): array
    {
        if (!Utils::isWooCommerceActive()) {
            return [];
        }

        try {
            $context = [
                'store_name' => get_option('blogname'),
                'currency' => get_woocommerce_currency(),
                'currency_symbol' => get_woocommerce_currency_symbol(),
                'tax_enabled' => wc_tax_enabled(),
                'prices_include_tax' => wc_prices_include_tax(),
                'shop_url' => wc_get_page_permalink('shop')
            ];

            // Add cart context if user has items in cart
            if (function_exists('WC') && WC()->cart && !WC()->cart->is_empty()) {
                $context['cart'] = [
                    'items_count' => WC()->cart->get_cart_contents_count(),
                    'subtotal' => WC()->cart->get_subtotal(),
                    'total' => WC()->cart->get_total('raw')
                ];
            }

            return $context;
        } catch (\Exception $e) {
            Utils::logDebug('Error extracting WooCommerce context: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Extract site context information
     *
     * @since 1.0.0
     * @return array Site context information
     */
    private function extractSiteContext(): array
    {
        return [
            'site_name' => get_bloginfo('name'),
            'site_description' => get_bloginfo('description'),
            'site_url' => home_url(),
            'admin_email' => get_option('admin_email'),
            'timezone' => get_option('timezone_string'),
            'language' => get_locale(),
            'wordpress_version' => get_bloginfo('version'),
            'woocommerce_version' => defined('WC_VERSION') ? WC_VERSION : null
        ];
    }

    /**
     * Extract session context for conversation continuity
     *
     * @since 1.0.0
     * @param string $conversationId Conversation identifier
     * @return array Session context information
     */
    private function extractSessionContext(string $conversationId): array
    {
        global $wpdb;

        try {
            // Get recent conversation history
            $conversationTable = $wpdb->prefix . 'woo_ai_conversations';

            $recentMessages = $wpdb->get_results($wpdb->prepare(
                "SELECT user_message, assistant_response, confidence, created_at 
                FROM {$conversationTable} 
                WHERE conversation_id = %s 
                ORDER BY created_at DESC 
                LIMIT %d",
                $conversationId,
                5
            ), ARRAY_A);

            $context = [
                'conversation_id' => $conversationId,
                'message_count' => count($recentMessages),
                'last_interaction' => !empty($recentMessages) ? $recentMessages[0]['created_at'] : null
            ];

            // Calculate average confidence if messages exist
            if (!empty($recentMessages)) {
                $confidenceSum = array_sum(array_column($recentMessages, 'confidence'));
                $context['avg_confidence'] = $confidenceSum / count($recentMessages);
            }

            return $context;
        } catch (\Exception $e) {
            Utils::logDebug('Error extracting session context: ' . $e->getMessage());
            return ['conversation_id' => $conversationId];
        }
    }

    /**
     * Prepare conversation data for AI processing
     *
     * @since 1.0.0
     * @param string $message User message
     * @param string $conversationId Conversation identifier
     * @param array $context Extracted context
     * @return array Prepared conversation data
     */
    private function prepareConversationData(string $message, string $conversationId, array $context): array
    {
        return [
            'user_message' => $message,
            'conversation_id' => $conversationId,
            'context' => $context,
            'request_time' => microtime(true),
            'user_id' => get_current_user_id(),
            'ip_address' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referer' => $_SERVER['HTTP_REFERER'] ?? '',
            'session_data' => [
                'started_at' => $context['session_context']['last_interaction'] ?? current_time('mysql'),
                'message_count' => ($context['session_context']['message_count'] ?? 0) + 1
            ]
        ];
    }

    /**
     * Generate AI response using Knowledge Base integration
     *
     * @since 1.0.0
     * @param array $conversationData Prepared conversation data
     * @param bool $streamResponse Whether to enable streaming
     * @return array AI response data
     */
    private function generateAIResponse(array $conversationData, bool $streamResponse = false): array
    {
        if (!$this->aiManager) {
            Utils::logError('AIManager not available for response generation');
            return $this->buildDummyResponse($conversationData['user_message']);
        }

        try {
            $options = [
                'conversation_id' => $conversationData['conversation_id'],
                'context' => $conversationData['context'],
                'stream' => $streamResponse,
                'max_tokens' => $this->getMaxTokensForPlan(),
                'temperature' => 0.7
            ];

            // Add model selection based on plan
            if ($this->licenseManager) {
                $options['model'] = $this->licenseManager->getCurrentAiModel();
            }

            return $this->aiManager->generateResponse($conversationData['user_message'], $options);
        } catch (\Exception $e) {
            Utils::logError('Error generating AI response: ' . $e->getMessage());
            return $this->buildDummyResponse($conversationData['user_message']);
        }
    }

    /**
     * Get maximum tokens allowed for current plan
     *
     * @since 1.0.0
     * @return int Maximum tokens
     */
    private function getMaxTokensForPlan(): int
    {
        if (!$this->licenseManager) {
            return 500; // Default for free usage
        }

        $planConfig = $this->licenseManager->getPlanConfiguration();
        return $planConfig['max_tokens'] ?? 1000;
    }

    /**
     * Build dummy response for fallback scenarios
     *
     * @since 1.0.0
     * @param string $userMessage Original user message
     * @return array Dummy response data
     */
    private function buildDummyResponse(string $userMessage): array
    {
        $responses = [
            'product' => "I'd be happy to help you with product information. Could you please provide more details about what you're looking for?",
            'shipping' => "We offer various shipping options to meet your needs. Standard shipping typically takes 3-5 business days, and we also offer express delivery options.",
            'return' => "Our return policy allows returns within 30 days of purchase. Items should be in original condition with all tags attached.",
            'payment' => "We accept all major credit cards, PayPal, and other secure payment methods. All transactions are encrypted for your security.",
            'support' => "I'm here to help! Please let me know what specific information you need, and I'll do my best to assist you.",
            'default' => "Thank you for your message! I'm here to help with any questions about our products or services. How can I assist you today?"
        ];

        // Simple keyword matching for dummy responses
        $response = $responses['default'];
        foreach ($responses as $keyword => $dummyResponse) {
            if ($keyword !== 'default' && stripos($userMessage, $keyword) !== false) {
                $response = $dummyResponse;
                break;
            }
        }

        return [
            'success' => true,
            'response' => $response,
            'confidence' => 0.7,
            'sources' => [],
            'model_used' => 'dummy-fallback',
            'tokens_used' => strlen($response) / 4,
            'context_chunks' => 0
        ];
    }

    /**
     * Process AI response and apply post-processing
     *
     * @since 1.0.0
     * @param array $aiResponse Raw AI response
     * @param array $conversationData Original conversation data
     * @return array Processed response data
     */
    private function processAIResponse(array $aiResponse, array $conversationData): array
    {
        if (!$aiResponse['success']) {
            Utils::logDebug('AI response generation failed, using fallback');
            $aiResponse = $this->buildDummyResponse($conversationData['user_message']);
        }

        // Apply content filtering
        $response = $this->applyContentFilters($aiResponse['response']);

        // Enhance response with context-aware information
        $enhancedResponse = $this->enhanceResponseWithContext($response, $conversationData['context']);

        return [
            'response' => $enhancedResponse,
            'confidence' => $aiResponse['confidence'] ?? 0.7,
            'sources' => $aiResponse['sources'] ?? [],
            'model_used' => $aiResponse['model_used'] ?? 'unknown',
            'tokens_used' => $aiResponse['tokens_used'] ?? 0,
            'context_chunks' => $aiResponse['context_chunks'] ?? 0,
            'processing_time' => $aiResponse['metadata']['response_time'] ?? 0
        ];
    }

    /**
     * Apply content filters to AI response
     *
     * @since 1.0.0
     * @param string $response AI-generated response
     * @return string Filtered response
     */
    private function applyContentFilters(string $response): string
    {
        // Remove any potential harmful content
        $response = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $response);
        $response = preg_replace('/javascript:/i', '', $response);

        // Apply WordPress content filters
        $response = wp_kses_post($response);

        // Apply custom filters
        return apply_filters('woo_ai_assistant_filter_response', $response);
    }

    /**
     * Enhance response with context-aware information
     *
     * @since 1.0.0
     * @param string $response Original AI response
     * @param array $context Conversation context
     * @return string Enhanced response
     */
    private function enhanceResponseWithContext(string $response, array $context): string
    {
        // Add personalization if user is logged in
        if ($context['user_info']['type'] === 'registered') {
            $displayName = $context['user_info']['display_name'] ?? '';
            if ($displayName && strpos($response, $displayName) === false) {
                // Optionally add personalization - be careful not to over-personalize
            }
        }

        // Add store-specific information if relevant
        if (isset($context['woocommerce_context']['currency_symbol'])) {
            $currencySymbol = $context['woocommerce_context']['currency_symbol'];
            // Replace generic currency references with actual currency symbol
            $response = str_replace('$', $currencySymbol, $response);
        }

        return $response;
    }

    /**
     * Generate contextual quick actions based on response and context
     *
     * @since 1.0.0
     * @param array $processedResponse Processed AI response
     * @param array $context Conversation context
     * @return array Quick action suggestions
     */
    private function generateQuickActions(array $processedResponse, array $context): array
    {
        $quickActions = [];

        // Product page quick actions
        if (isset($context['page_context']['is_product_page']) && $context['page_context']['is_product_page']) {
            $quickActions[] = [
                'type' => 'add_to_cart',
                'label' => __('Add to Cart', 'woo-ai-assistant'),
                'action' => 'woo_ai_add_to_cart',
                'data' => [
                    'product_id' => $context['product_context']['id'] ?? 0
                ]
            ];
        }

        // General shop quick actions
        if (isset($context['page_context']['is_shop_page']) && $context['page_context']['is_shop_page']) {
            $quickActions[] = [
                'type' => 'view_products',
                'label' => __('View Products', 'woo-ai-assistant'),
                'action' => 'woo_ai_view_products',
                'data' => []
            ];
        }

        // Cart-related quick actions
        if (isset($context['woocommerce_context']['cart']) && !empty($context['woocommerce_context']['cart'])) {
            $quickActions[] = [
                'type' => 'view_cart',
                'label' => __('View Cart', 'woo-ai-assistant'),
                'action' => 'woo_ai_view_cart',
                'data' => []
            ];

            $quickActions[] = [
                'type' => 'checkout',
                'label' => __('Proceed to Checkout', 'woo-ai-assistant'),
                'action' => 'woo_ai_checkout',
                'data' => []
            ];
        }

        // Support quick actions
        $quickActions[] = [
            'type' => 'contact_support',
            'label' => __('Contact Support', 'woo-ai-assistant'),
            'action' => 'woo_ai_contact_support',
            'data' => [
                'conversation_id' => $context['conversation_id']
            ]
        ];

        return apply_filters('woo_ai_assistant_quick_actions', $quickActions, $processedResponse, $context);
    }

    /**
     * Save conversation data to database
     *
     * @since 1.0.0
     * @param array $conversationData Original conversation data
     * @param array $processedResponse Processed AI response
     * @return bool Success status
     */
    private function saveConversationData(array $conversationData, array $processedResponse): bool
    {
        global $wpdb;

        try {
            $conversationTable = $wpdb->prefix . 'woo_ai_conversations';

            $result = $wpdb->insert($conversationTable, [
                'conversation_id' => $conversationData['conversation_id'],
                'user_id' => $conversationData['user_id'],
                'user_message' => $conversationData['user_message'],
                'assistant_response' => $processedResponse['response'],
                'confidence' => $processedResponse['confidence'],
                'model_used' => $processedResponse['model_used'],
                'tokens_used' => $processedResponse['tokens_used'],
                'context_data' => wp_json_encode($conversationData['context']),
                'session_data' => wp_json_encode($conversationData['session_data']),
                'ip_address' => $conversationData['ip_address'],
                'user_agent' => $conversationData['user_agent'],
                'referer' => $conversationData['referer'],
                'processing_time' => $processedResponse['processing_time'],
                'created_at' => current_time('mysql')
            ], [
                '%s', // conversation_id
                '%d', // user_id
                '%s', // user_message
                '%s', // assistant_response
                '%f', // confidence
                '%s', // model_used
                '%d', // tokens_used
                '%s', // context_data
                '%s', // session_data
                '%s', // ip_address
                '%s', // user_agent
                '%s', // referer
                '%f', // processing_time
                '%s'  // created_at
            ]);

            if ($result === false) {
                Utils::logError('Failed to save conversation data: ' . $wpdb->last_error);
                return false;
            }

            Utils::logDebug('Conversation data saved successfully', [
                'conversation_id' => $conversationData['conversation_id']
            ]);

            return true;
        } catch (\Exception $e) {
            Utils::logError('Error saving conversation data: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update usage statistics for licensing
     *
     * @since 1.0.0
     * @param string $conversationId Conversation identifier
     * @return void
     */
    private function updateUsageStatistics(string $conversationId): void
    {
        if (!$this->licenseManager) {
            return;
        }

        try {
            $this->licenseManager->recordUsage('conversation', [
                'conversation_id' => $conversationId,
                'timestamp' => current_time('mysql')
            ]);
        } catch (\Exception $e) {
            Utils::logError('Error updating usage statistics: ' . $e->getMessage());
        }
    }

    /**
     * Generate unique conversation ID
     *
     * @since 1.0.0
     * @return string Unique conversation identifier
     */
    private function generateConversationId(): string
    {
        return 'conv-' . wp_generate_uuid4();
    }

    /**
     * Handle AJAX chat request
     *
     * @since 1.0.0
     * @return void
     */
    public function handleAjaxChatRequest(): void
    {
        try {
            // Create a mock WP_REST_Request from AJAX data
            $request = new WP_REST_Request('POST', '/woo-ai-assistant/v1/chat');
            $request->set_param('message', $_POST['message'] ?? '');
            $request->set_param('conversation_id', $_POST['conversation_id'] ?? '');
            $request->set_param('user_context', $_POST['user_context'] ?? []);
            $request->set_param('nonce', $_POST['nonce'] ?? '');
            $request->set_param('stream', $_POST['stream'] ?? false);

            $response = $this->processMessage($request);

            if (is_wp_error($response)) {
                wp_send_json_error([
                    'message' => $response->get_error_message(),
                    'code' => $response->get_error_code()
                ]);
            } else {
                wp_send_json_success($response->get_data());
            }
        } catch (\Exception $e) {
            Utils::logError('AJAX chat request error: ' . $e->getMessage());
            wp_send_json_error([
                'message' => 'An error occurred processing your request',
                'code' => 'ajax_error'
            ]);
        }
    }

    /**
     * Clean up expired conversations
     *
     * @since 1.0.0
     * @return void
     */
    public function cleanupExpiredConversations(): void
    {
        global $wpdb;

        try {
            $retentionDays = apply_filters('woo_ai_assistant_conversation_retention_days', 30);
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));

            $conversationTable = $wpdb->prefix . 'woo_ai_conversations';

            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$conversationTable} WHERE created_at < %s",
                $cutoffDate
            ));

            if ($deleted !== false && $deleted > 0) {
                Utils::logDebug("Cleaned up {$deleted} expired conversations");
            }
        } catch (\Exception $e) {
            Utils::logError('Error cleaning up conversations: ' . $e->getMessage());
        }
    }

    /**
     * Build success response
     *
     * @since 1.0.0
     * @param array $data Response data
     * @param int $status HTTP status code
     * @return WP_REST_Response Response object
     */
    private function buildSuccessResponse(array $data, int $status = 200): WP_REST_Response
    {
        return new WP_REST_Response([
            'success' => true,
            'data' => $data,
            'timestamp' => current_time('c')
        ], $status);
    }

    /**
     * Build error response
     *
     * @since 1.0.0
     * @param string $message Error message
     * @param string $code Error code
     * @param int $status HTTP status code
     * @return WP_Error Error object
     */
    private function buildErrorResponse(string $message, string $code, int $status = 400): WP_Error
    {
        return new WP_Error($code, $message, ['status' => $status]);
    }

    /**
     * Get endpoint configuration for registration
     *
     * @since 1.0.0
     * @return array Endpoint configuration
     */
    public static function getEndpointConfig(): array
    {
        return [
            'methods' => 'POST',
            'callback' => [self::getInstance(), 'processMessage'],
            'permission_callback' => '__return_true', // Public endpoint
            'args' => [
                'message' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => 'User message content',
                    'sanitize_callback' => 'sanitize_textarea_field',
                    'validate_callback' => function ($value) {
                        if (empty(trim($value))) {
                            return new WP_Error('empty_message', 'Message cannot be empty');
                        }
                        if (strlen($value) > self::MAX_MESSAGE_LENGTH) {
                            return new WP_Error('message_too_long', 'Message is too long');
                        }
                        return true;
                    }
                ],
                'conversation_id' => [
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Conversation identifier',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'user_context' => [
                    'required' => false,
                    'type' => 'object',
                    'description' => 'User context data',
                    'default' => []
                ],
                'stream' => [
                    'required' => false,
                    'type' => 'boolean',
                    'description' => 'Enable response streaming',
                    'default' => false
                ],
                'nonce' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Security nonce',
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ];
    }
}
