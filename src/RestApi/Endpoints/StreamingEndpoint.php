<?php

/**
 * Streaming Endpoint Class
 *
 * Implements Server-Sent Events (SSE) and WebSocket fallback for real-time AI response streaming.
 * Provides chunked response delivery, progressive response generation, and enhanced user experience
 * with streaming capabilities while maintaining security, rate limiting, and WordPress integration.
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
 * Class StreamingEndpoint
 *
 * Comprehensive streaming endpoint that handles Server-Sent Events (SSE) for real-time
 * AI response delivery, progressive chunk processing, and enhanced user experience with
 * fallback mechanisms for non-streaming clients.
 *
 * @since 1.0.0
 */
class StreamingEndpoint
{
    use Singleton;

    /**
     * SSE connection timeout (seconds)
     *
     * @since 1.0.0
     * @var int
     */
    private const SSE_TIMEOUT = 30;

    /**
     * Default chunk size for streaming responses
     *
     * @since 1.0.0
     * @var int
     */
    private const DEFAULT_CHUNK_SIZE = 50;

    /**
     * Maximum chunk size allowed
     *
     * @since 1.0.0
     * @var int
     */
    private const MAX_CHUNK_SIZE = 200;

    /**
     * SSE event types
     *
     * @since 1.0.0
     * @var array
     */
    private const SSE_EVENTS = [
        'CHUNK' => 'response_chunk',
        'COMPLETE' => 'response_complete',
        'ERROR' => 'response_error',
        'HEARTBEAT' => 'heartbeat',
        'TYPING_START' => 'typing_start',
        'TYPING_STOP' => 'typing_stop'
    ];

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
     * Current streaming session data
     *
     * @since 1.0.0
     * @var array
     */
    private $streamingSession = [];

    /**
     * Constructor
     *
     * Initializes the StreamingEndpoint with required dependencies and sets up
     * WordPress hooks for SSE handling and streaming management.
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
                Utils::logError('AIManager component not available in StreamingEndpoint');
            }

            // Initialize Vector Manager
            $this->vectorManager = $main->getComponent('kb_vector_manager');
            if (!$this->vectorManager) {
                Utils::logError('VectorManager component not available in StreamingEndpoint');
            }

            // Initialize License Manager
            $this->licenseManager = $main->getComponent('license_manager');
            if (!$this->licenseManager) {
                Utils::logError('LicenseManager component not available in StreamingEndpoint');
            }

            Utils::logDebug('StreamingEndpoint dependencies initialized successfully');
        } catch (\Exception $e) {
            Utils::logError('Error initializing StreamingEndpoint dependencies: ' . $e->getMessage());
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
        // AJAX hooks for SSE connection setup
        add_action('wp_ajax_woo_ai_assistant_stream_init', [$this, 'initializeStreamingSession']);
        add_action('wp_ajax_nopriv_woo_ai_assistant_stream_init', [$this, 'initializeStreamingSession']);

        // SSE cleanup hook
        add_action('woo_ai_assistant_cleanup_streaming_sessions', [$this, 'cleanupStreamingSessions']);
    }

    /**
     * Handle streaming chat request with SSE
     *
     * Main method that orchestrates Server-Sent Events streaming for AI responses,
     * implementing progressive chunk delivery, typing indicators, and real-time
     * response generation with comprehensive error handling and fallback mechanisms.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request REST API request object containing message and streaming parameters
     * @return WP_REST_Response|WP_Error Response object with streaming data or error
     *
     * @example
     * POST /wp-json/woo-ai-assistant/v1/stream
     * {
     *   "message": "What are your shipping options?",
     *   "conversation_id": "conv-123-456",
     *   "user_context": {
     *     "page": "shop",
     *     "product_id": 789
     *   },
     *   "stream_config": {
     *     "chunk_size": 75,
     *     "enable_typing_indicator": true,
     *     "heartbeat_interval": 5
     *   },
     *   "nonce": "abc123xyz"
     * }
     */
    public function handleStreamingRequest(WP_REST_Request $request)
    {
        $startTime = microtime(true);

        try {
            // Step 1: Validate request and security
            $validationResult = $this->validateStreamingRequest($request);
            if (is_wp_error($validationResult)) {
                return $validationResult;
            }

            // Step 2: Extract and sanitize parameters
            $message = $this->sanitizeMessage($request->get_param('message'));
            $conversationId = $this->sanitizeConversationId($request->get_param('conversation_id'));
            $userContext = $this->sanitizeUserContext($request->get_param('user_context') ?: []);
            $streamConfig = $this->sanitizeStreamConfig($request->get_param('stream_config') ?: []);

            Utils::logDebug('Processing streaming chat request', [
                'message_length' => strlen($message),
                'conversation_id' => $conversationId,
                'chunk_size' => $streamConfig['chunk_size'],
                'typing_indicator' => $streamConfig['enable_typing_indicator']
            ]);

            // Step 3: Check license limits for streaming
            $licenseCheckResult = $this->checkStreamingLimits();
            if (is_wp_error($licenseCheckResult)) {
                return $licenseCheckResult;
            }

            // Step 4: Initialize streaming session
            $sessionId = $this->initializeSession($conversationId, $streamConfig);

            // Step 5: Detect client streaming support
            $clientSupportsSSE = $this->detectSSESupport($request);

            if ($clientSupportsSSE && $streamConfig['enable_sse']) {
                // Step 6a: Handle SSE streaming
                return $this->handleSSEStreaming($message, $conversationId, $userContext, $streamConfig, $sessionId);
            } else {
                // Step 6b: Handle fallback chunked response
                return $this->handleChunkedFallback($message, $conversationId, $userContext, $streamConfig, $sessionId);
            }
        } catch (\InvalidArgumentException $e) {
            Utils::logError('Validation error in streaming request: ' . $e->getMessage());
            return $this->buildErrorResponse('Invalid streaming request parameters', 'validation_error', 400);
        } catch (\RuntimeException $e) {
            Utils::logError('Runtime error in streaming request: ' . $e->getMessage());
            return $this->buildErrorResponse('Streaming service temporarily unavailable', 'service_error', 503);
        } catch (\Exception $e) {
            Utils::logError('Unexpected error in streaming request: ' . $e->getMessage());
            return $this->buildErrorResponse('An unexpected streaming error occurred', 'general_error', 500);
        }
    }

    /**
     * Handle Server-Sent Events streaming
     *
     * @since 1.0.0
     * @param string $message User message
     * @param string $conversationId Conversation identifier
     * @param array $userContext User context data
     * @param array $streamConfig Streaming configuration
     * @param string $sessionId Streaming session identifier
     * @return WP_REST_Response Response with SSE stream
     */
    private function handleSSEStreaming(
        string $message,
        string $conversationId,
        array $userContext,
        array $streamConfig,
        string $sessionId
    ): WP_REST_Response {
        try {
            // Set SSE headers
            $this->setSSEHeaders();

            // Send typing indicator
            if ($streamConfig['enable_typing_indicator']) {
                $this->sendSSEEvent(self::SSE_EVENTS['TYPING_START'], [
                    'conversation_id' => $conversationId,
                    'timestamp' => current_time('c')
                ]);
                $this->flushSSEOutput();
            }

            // Generate AI response with streaming callback
            $responseData = $this->generateStreamingResponse(
                $message,
                $conversationId,
                $userContext,
                $streamConfig,
                [$this, 'handleSSEChunk']
            );

            // Send completion event
            if ($streamConfig['enable_typing_indicator']) {
                $this->sendSSEEvent(self::SSE_EVENTS['TYPING_STOP'], [
                    'conversation_id' => $conversationId
                ]);
            }

            $this->sendSSEEvent(self::SSE_EVENTS['COMPLETE'], [
                'conversation_id' => $conversationId,
                'total_chunks' => $responseData['chunk_count'],
                'metadata' => $responseData['metadata'],
                'session_id' => $sessionId,
                'timestamp' => current_time('c')
            ]);

            $this->flushSSEOutput();

            // Clean up session
            $this->cleanupSession($sessionId);

            return new WP_REST_Response([
                'success' => true,
                'streaming' => true,
                'session_id' => $sessionId,
                'message' => 'SSE streaming completed'
            ], 200);
        } catch (\Exception $e) {
            Utils::logError('SSE streaming error: ' . $e->getMessage());

            // Send error event
            $this->sendSSEEvent(self::SSE_EVENTS['ERROR'], [
                'error' => 'Streaming failed',
                'conversation_id' => $conversationId
            ]);
            $this->flushSSEOutput();

            return $this->buildErrorResponse('SSE streaming failed', 'sse_error', 500);
        }
    }

    /**
     * Handle chunked response fallback for non-SSE clients
     *
     * @since 1.0.0
     * @param string $message User message
     * @param string $conversationId Conversation identifier
     * @param array $userContext User context data
     * @param array $streamConfig Streaming configuration
     * @param string $sessionId Streaming session identifier
     * @return WP_REST_Response Response with chunked data
     */
    private function handleChunkedFallback(
        string $message,
        string $conversationId,
        array $userContext,
        array $streamConfig,
        string $sessionId
    ): WP_REST_Response {
        try {
            $chunks = [];
            $chunkIndex = 0;

            // Generate AI response with chunking callback
            $responseData = $this->generateStreamingResponse(
                $message,
                $conversationId,
                $userContext,
                $streamConfig,
                function ($chunk, $metadata) use (&$chunks, &$chunkIndex) {
                    $chunks[] = [
                        'index' => $chunkIndex++,
                        'content' => $chunk,
                        'metadata' => $metadata,
                        'timestamp' => current_time('c')
                    ];
                }
            );

            // Clean up session
            $this->cleanupSession($sessionId);

            return new WP_REST_Response([
                'success' => true,
                'streaming' => false,
                'fallback_mode' => 'chunked',
                'conversation_id' => $conversationId,
                'session_id' => $sessionId,
                'chunks' => $chunks,
                'metadata' => [
                    'total_chunks' => count($chunks),
                    'execution_time' => $responseData['execution_time'],
                    'model_used' => $responseData['model_used'],
                    'tokens_used' => $responseData['tokens_used']
                ],
                'timestamp' => current_time('c')
            ], 200);
        } catch (\Exception $e) {
            Utils::logError('Chunked fallback error: ' . $e->getMessage());
            return $this->buildErrorResponse('Chunked response failed', 'chunked_error', 500);
        }
    }

    /**
     * Generate streaming AI response with chunk callback
     *
     * @since 1.0.0
     * @param string $message User message
     * @param string $conversationId Conversation identifier
     * @param array $userContext User context data
     * @param array $streamConfig Streaming configuration
     * @param callable $chunkCallback Callback for handling chunks
     * @return array Response metadata
     */
    private function generateStreamingResponse(
        string $message,
        string $conversationId,
        array $userContext,
        array $streamConfig,
        callable $chunkCallback
    ): array {
        $startTime = microtime(true);

        if (!$this->aiManager) {
            throw new \RuntimeException('AIManager not available for streaming response generation');
        }

        try {
            // Prepare AI request options
            $options = [
                'conversation_id' => $conversationId,
                'context' => $userContext,
                'streaming' => true,
                'chunk_size' => $streamConfig['chunk_size'],
                'max_tokens' => $this->getMaxTokensForPlan(),
                'temperature' => 0.7
            ];

            // Add model selection based on plan
            if ($this->licenseManager) {
                $options['model'] = $this->licenseManager->getCurrentAiModel();
            }

            // Generate AI response
            $aiResponse = $this->aiManager->generateResponse($message, $options);

            if (!$aiResponse['success']) {
                throw new \RuntimeException('AI response generation failed: ' . ($aiResponse['error'] ?? 'Unknown error'));
            }

            // Process response into chunks
            $responseText = $aiResponse['response'];
            $chunks = $this->chunkResponse($responseText, $streamConfig['chunk_size']);

            $chunkCount = 0;
            foreach ($chunks as $chunk) {
                $chunkMetadata = [
                    'chunk_index' => $chunkCount,
                    'is_final' => ($chunkCount === count($chunks) - 1),
                    'conversation_id' => $conversationId
                ];

                // Call the chunk callback
                call_user_func($chunkCallback, $chunk, $chunkMetadata);
                $chunkCount++;

                // Add delay between chunks for better UX
                if ($streamConfig['chunk_delay'] > 0) {
                    usleep($streamConfig['chunk_delay'] * 1000); // Convert to microseconds
                }
            }

            $executionTime = microtime(true) - $startTime;

            return [
                'chunk_count' => $chunkCount,
                'execution_time' => round($executionTime, 4),
                'model_used' => $aiResponse['model_used'] ?? 'unknown',
                'tokens_used' => $aiResponse['tokens_used'] ?? 0,
                'confidence' => $aiResponse['confidence'] ?? 0.8,
                'sources' => $aiResponse['sources'] ?? [],
                'metadata' => [
                    'original_response_length' => strlen($responseText),
                    'average_chunk_size' => $chunkCount > 0 ? strlen($responseText) / $chunkCount : 0,
                    'processing_time' => round($executionTime, 4)
                ]
            ];
        } catch (\Exception $e) {
            Utils::logError('Error in streaming response generation: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Chunk response text into appropriate sizes for streaming
     *
     * @since 1.0.0
     * @param string $response Full AI response
     * @param int $chunkSize Target chunk size
     * @return array Array of response chunks
     */
    private function chunkResponse(string $response, int $chunkSize): array
    {
        if (strlen($response) <= $chunkSize) {
            return [$response];
        }

        $chunks = [];
        $sentences = preg_split('/(?<=[.!?])\s+/', $response, -1, PREG_SPLIT_NO_EMPTY);
        $currentChunk = '';

        foreach ($sentences as $sentence) {
            // If adding this sentence would exceed chunk size, save current chunk
            if (strlen($currentChunk . ' ' . $sentence) > $chunkSize && !empty($currentChunk)) {
                $chunks[] = trim($currentChunk);
                $currentChunk = $sentence;
            } else {
                $currentChunk .= (empty($currentChunk) ? '' : ' ') . $sentence;
            }
        }

        // Add the last chunk if it's not empty
        if (!empty(trim($currentChunk))) {
            $chunks[] = trim($currentChunk);
        }

        return $chunks;
    }

    /**
     * Handle SSE chunk callback
     *
     * @since 1.0.0
     * @param string $chunk Response chunk
     * @param array $metadata Chunk metadata
     * @return void
     */
    public function handleSSEChunk(string $chunk, array $metadata): void
    {
        $this->sendSSEEvent(self::SSE_EVENTS['CHUNK'], [
            'chunk' => $chunk,
            'index' => $metadata['chunk_index'],
            'is_final' => $metadata['is_final'],
            'conversation_id' => $metadata['conversation_id'],
            'timestamp' => current_time('c')
        ]);
        $this->flushSSEOutput();
    }

    /**
     * Set Server-Sent Events headers
     *
     * @since 1.0.0
     * @return void
     */
    private function setSSEHeaders(): void
    {
        // Prevent timeout
        set_time_limit(0);

        // Set SSE headers
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Cache-Control');

        // Disable output buffering
        if (ob_get_level()) {
            ob_end_clean();
        }
    }

    /**
     * Send SSE event
     *
     * @since 1.0.0
     * @param string $event Event type
     * @param array $data Event data
     * @return void
     */
    private function sendSSEEvent(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo "data: " . wp_json_encode($data) . "\n\n";
    }

    /**
     * Flush SSE output
     *
     * @since 1.0.0
     * @return void
     */
    private function flushSSEOutput(): void
    {
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }

    /**
     * Detect if client supports Server-Sent Events
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return bool True if SSE is supported
     */
    private function detectSSESupport(WP_REST_Request $request): bool
    {
        // Check Accept header
        $acceptHeader = $request->get_header('accept');
        if ($acceptHeader && strpos($acceptHeader, 'text/event-stream') !== false) {
            return true;
        }

        // Check for explicit SSE request parameter
        if ($request->get_param('sse_support') === true) {
            return true;
        }

        // Check User-Agent for known SSE-capable browsers
        $userAgent = $request->get_header('user_agent');
        if ($userAgent) {
            $sseCapableBrowsers = [
                'Chrome', 'Firefox', 'Safari', 'Edge', 'Opera'
            ];

            foreach ($sseCapableBrowsers as $browser) {
                if (stripos($userAgent, $browser) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Validate streaming request
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return true|WP_Error True if valid, error otherwise
     */
    private function validateStreamingRequest(WP_REST_Request $request)
    {
        // Validate nonce
        $nonce = $request->get_param('nonce');
        if (!wp_verify_nonce($nonce, 'woo_ai_stream')) {
            Utils::logDebug('Invalid nonce in streaming request');
            return $this->buildErrorResponse('Security check failed', 'invalid_nonce', 403);
        }

        // Check rate limiting
        if (!$this->checkStreamingRateLimit()) {
            Utils::logDebug('Rate limit exceeded for streaming request');
            return $this->buildErrorResponse('Too many streaming requests', 'rate_limit_exceeded', 429);
        }

        // Validate message content
        $message = $request->get_param('message');
        if (empty(trim($message))) {
            return $this->buildErrorResponse('Message cannot be empty', 'empty_message', 400);
        }

        if (strlen($message) > 2000) {
            return $this->buildErrorResponse('Message too long (max 2000 characters)', 'message_too_long', 400);
        }

        return true;
    }

    /**
     * Check streaming-specific rate limits
     *
     * @since 1.0.0
     * @return bool True if within limits
     */
    private function checkStreamingRateLimit(): bool
    {
        $userId = get_current_user_id();
        $userKey = $userId ? "user_{$userId}" : 'ip_' . $this->getClientIp();

        $rateLimitKey = "woo_ai_stream_rate_limit_{$userKey}";
        $currentCount = get_transient($rateLimitKey) ?: 0;

        // Allow 10 streaming requests per hour (more restrictive than regular chat)
        $maxRequests = apply_filters('woo_ai_assistant_stream_rate_limit', 10);

        if ($currentCount >= $maxRequests) {
            return false;
        }

        // Increment counter
        set_transient($rateLimitKey, $currentCount + 1, HOUR_IN_SECONDS);

        return true;
    }

    /**
     * Check streaming license limits
     *
     * @since 1.0.0
     * @return true|WP_Error True if within limits, error otherwise
     */
    private function checkStreamingLimits()
    {
        if (!$this->licenseManager) {
            // Allow basic functionality if license manager not available
            return true;
        }

        try {
            // Check if streaming feature is enabled for current plan
            if (!$this->licenseManager->isFeatureEnabled('streaming_responses')) {
                return $this->buildErrorResponse(
                    'Streaming responses not available in your plan',
                    'feature_not_available',
                    403
                );
            }

            return true;
        } catch (\Exception $e) {
            Utils::logError('Error checking streaming limits: ' . $e->getMessage());
            // Allow request to continue if license check fails
            return true;
        }
    }

    /**
     * Initialize streaming session
     *
     * @since 1.0.0
     * @param string $conversationId Conversation identifier
     * @param array $streamConfig Stream configuration
     * @return string Session identifier
     */
    private function initializeSession(string $conversationId, array $streamConfig): string
    {
        $sessionId = wp_generate_uuid4();

        $this->streamingSession = [
            'session_id' => $sessionId,
            'conversation_id' => $conversationId,
            'start_time' => microtime(true),
            'config' => $streamConfig,
            'user_id' => get_current_user_id(),
            'ip_address' => $this->getClientIp(),
            'status' => 'active'
        ];

        // Store session in transient for cleanup
        set_transient("woo_ai_stream_session_{$sessionId}", $this->streamingSession, self::SSE_TIMEOUT + 60);

        Utils::logDebug('Streaming session initialized', [
            'session_id' => $sessionId,
            'conversation_id' => $conversationId
        ]);

        return $sessionId;
    }

    /**
     * Clean up streaming session
     *
     * @since 1.0.0
     * @param string $sessionId Session identifier
     * @return void
     */
    private function cleanupSession(string $sessionId): void
    {
        delete_transient("woo_ai_stream_session_{$sessionId}");
        $this->streamingSession = [];

        Utils::logDebug('Streaming session cleaned up', ['session_id' => $sessionId]);
    }

    /**
     * Clean up expired streaming sessions
     *
     * @since 1.0.0
     * @return void
     */
    public function cleanupStreamingSessions(): void
    {
        global $wpdb;

        try {
            // Get all streaming session transients
            $transients = $wpdb->get_results(
                "SELECT option_name FROM {$wpdb->options} 
                WHERE option_name LIKE '_transient_woo_ai_stream_session_%'",
                ARRAY_A
            );

            $cleanedCount = 0;
            foreach ($transients as $transient) {
                $sessionData = get_transient(str_replace('_transient_', '', $transient['option_name']));

                // If session data is false or expired, clean it up
                if ($sessionData === false) {
                    delete_transient(str_replace('_transient_', '', $transient['option_name']));
                    $cleanedCount++;
                }
            }

            if ($cleanedCount > 0) {
                Utils::logDebug("Cleaned up {$cleanedCount} expired streaming sessions");
            }
        } catch (\Exception $e) {
            Utils::logError('Error cleaning up streaming sessions: ' . $e->getMessage());
        }
    }

    /**
     * Initialize streaming session via AJAX
     *
     * @since 1.0.0
     * @return void
     */
    public function initializeStreamingSession(): void
    {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'woo_ai_stream_init')) {
                wp_send_json_error(['message' => 'Security check failed']);
                return;
            }

            $conversationId = sanitize_text_field($_POST['conversation_id'] ?? '');
            $streamConfig = $this->sanitizeStreamConfig($_POST['stream_config'] ?? []);

            if (empty($conversationId)) {
                $conversationId = 'conv-' . wp_generate_uuid4();
            }

            $sessionId = $this->initializeSession($conversationId, $streamConfig);

            wp_send_json_success([
                'session_id' => $sessionId,
                'conversation_id' => $conversationId,
                'sse_url' => rest_url('woo-ai-assistant/v1/stream'),
                'config' => $streamConfig
            ]);
        } catch (\Exception $e) {
            Utils::logError('Error initializing streaming session: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Failed to initialize streaming session']);
        }
    }

    /**
     * Sanitize stream configuration
     *
     * @since 1.0.0
     * @param array $config Raw stream configuration
     * @return array Sanitized configuration
     */
    private function sanitizeStreamConfig(array $config): array
    {
        $defaults = [
            'chunk_size' => self::DEFAULT_CHUNK_SIZE,
            'chunk_delay' => 100, // milliseconds
            'enable_typing_indicator' => true,
            'enable_sse' => true,
            'heartbeat_interval' => 10, // seconds
            'max_chunks' => 100
        ];

        $sanitized = array_merge($defaults, $config);

        // Sanitize chunk size
        $sanitized['chunk_size'] = max(10, min(absint($sanitized['chunk_size']), self::MAX_CHUNK_SIZE));

        // Sanitize chunk delay
        $sanitized['chunk_delay'] = max(0, min(absint($sanitized['chunk_delay']), 2000));

        // Sanitize boolean values
        $sanitized['enable_typing_indicator'] = (bool)$sanitized['enable_typing_indicator'];
        $sanitized['enable_sse'] = (bool)$sanitized['enable_sse'];

        // Sanitize heartbeat interval
        $sanitized['heartbeat_interval'] = max(1, min(absint($sanitized['heartbeat_interval']), 30));

        // Sanitize max chunks
        $sanitized['max_chunks'] = max(1, min(absint($sanitized['max_chunks']), 1000));

        return $sanitized;
    }

    /**
     * Sanitize message input
     *
     * @since 1.0.0
     * @param string $message Raw message
     * @return string Sanitized message
     */
    private function sanitizeMessage(string $message): string
    {
        return sanitize_textarea_field(trim($message));
    }

    /**
     * Sanitize conversation ID
     *
     * @since 1.0.0
     * @param string|null $conversationId Raw conversation ID
     * @return string Sanitized conversation ID
     */
    private function sanitizeConversationId(?string $conversationId): string
    {
        if (empty($conversationId)) {
            return 'conv-' . wp_generate_uuid4();
        }

        return sanitize_text_field($conversationId);
    }

    /**
     * Sanitize user context
     *
     * @since 1.0.0
     * @param array $context Raw context data
     * @return array Sanitized context
     */
    private function sanitizeUserContext(array $context): array
    {
        $sanitized = [];

        if (isset($context['page'])) {
            $sanitized['page'] = sanitize_text_field($context['page']);
        }

        if (isset($context['product_id'])) {
            $sanitized['product_id'] = absint($context['product_id']);
        }

        if (isset($context['user_id'])) {
            $sanitized['user_id'] = absint($context['user_id']);
        }

        if (isset($context['url'])) {
            $sanitized['url'] = esc_url_raw($context['url']);
        }

        return $sanitized;
    }

    /**
     * Get client IP address
     *
     * @since 1.0.0
     * @return string Client IP
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
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Get maximum tokens for current plan
     *
     * @since 1.0.0
     * @return int Maximum tokens
     */
    private function getMaxTokensForPlan(): int
    {
        if (!$this->licenseManager) {
            return 500;
        }

        $planConfig = $this->licenseManager->getPlanConfiguration();
        return $planConfig['max_tokens'] ?? 1000;
    }

    /**
     * Build error response
     *
     * @since 1.0.0
     * @param string $message Error message
     * @param string $code Error code
     * @param int $status HTTP status
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
            'callback' => [self::getInstance(), 'handleStreamingRequest'],
            'permission_callback' => '__return_true', // Public endpoint
            'args' => [
                'message' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => 'User message content',
                    'sanitize_callback' => 'sanitize_textarea_field'
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
                'stream_config' => [
                    'required' => false,
                    'type' => 'object',
                    'description' => 'Streaming configuration',
                    'default' => []
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
