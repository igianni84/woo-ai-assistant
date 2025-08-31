<?php

/**
 * Chat Endpoint Class
 *
 * Handles REST API endpoints for chat functionality including message processing,
 * AI response generation, and conversation management.
 *
 * @package WooAiAssistant
 * @subpackage RestApi\Endpoints
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\RestApi\Endpoints;

use WooAiAssistant\Common\Logger;
use WooAiAssistant\Common\Utils;
use WooAiAssistant\Common\Cache;
use WooAiAssistant\Chatbot\ConversationHandler;
use WooAiAssistant\KnowledgeBase\AIManager;
use WooAiAssistant\KnowledgeBase\VectorManager;
use WooAiAssistant\Config\ApiConfiguration;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use Exception;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ChatEndpoint
 *
 * Manages chat-related REST API endpoints with full AI response generation,
 * conversation management, knowledge base integration, and streaming support.
 *
 * @since 1.0.0
 */
class ChatEndpoint
{
    /**
     * Conversation handler instance
     *
     * @var ConversationHandler
     */
    private ConversationHandler $conversationHandler;

    /**
     * AI manager instance
     *
     * @var AIManager
     */
    private AIManager $aiManager;

    /**
     * Vector manager instance
     *
     * @var VectorManager
     */
    private VectorManager $vectorManager;

    /**
     * API configuration instance
     *
     * @var ApiConfiguration
     */
    private ApiConfiguration $apiConfig;

    /**
     * Cache instance
     *
     * @var Cache
     */
    private Cache $cache;

    /**
     * Logger instance
     *
     * @var Logger
     */
    private Logger $logger;

    /**
     * Maximum messages per conversation
     *
     * @var int
     */
    private int $maxMessagesPerConversation = 50;

    /**
     * Rate limiting configuration
     *
     * @var array
     */
    private array $rateLimits = [
        'messages_per_minute' => 20,
        'messages_per_hour' => 100,
        'concurrent_conversations' => 5
    ];

    /**
     * Initialize chat endpoint
     *
     * @return void
     */
    public function __construct()
    {
        $this->conversationHandler = ConversationHandler::getInstance();
        $this->aiManager = AIManager::getInstance();
        $this->vectorManager = VectorManager::getInstance();
        $this->apiConfig = ApiConfiguration::getInstance();
        $this->cache = Cache::getInstance();
        $this->logger = Logger::getInstance();
    }
    /**
     * Register chat routes
     *
     * @param string $namespace API namespace
     * @return void
     */
    public function registerRoutes(string $namespace): void
    {
        // Send message endpoint
        register_rest_route(
            $namespace,
            '/chat/message',
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'sendMessage'],
                'permission_callback' => [$this, 'checkChatPermission'],
                'args' => [
                    'message' => [
                        'required' => true,
                        'type' => 'string',
                        'description' => 'User message content',
                        'validate_callback' => [$this, 'validateMessage'],
                        'sanitize_callback' => 'sanitize_textarea_field'
                    ],
                    'conversation_id' => [
                        'type' => 'integer',
                        'description' => 'Existing conversation ID (optional)',
                        'sanitize_callback' => 'absint'
                    ],
                    'context' => [
                        'type' => 'object',
                        'description' => 'Chat context (page, product, etc.)',
                        'default' => []
                    ],
                    'streaming' => [
                        'type' => 'boolean',
                        'description' => 'Enable streaming response',
                        'default' => false
                    ]
                ]
            ]
        );

        // Get conversation history endpoint
        register_rest_route(
            $namespace,
            '/chat/conversation/(?P<id>\d+)',
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getConversation'],
                'permission_callback' => [$this, 'checkChatPermission'],
                'args' => [
                    'id' => [
                        'required' => true,
                        'type' => 'integer',
                        'description' => 'Conversation ID',
                        'sanitize_callback' => 'absint'
                    ],
                    'include_messages' => [
                        'type' => 'string',
                        'description' => 'Whether to include messages',
                        'default' => 'true',
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of messages',
                        'default' => 50,
                        'sanitize_callback' => 'absint'
                    ],
                    'offset' => [
                        'type' => 'integer',
                        'description' => 'Number of messages to skip',
                        'default' => 0,
                        'sanitize_callback' => 'absint'
                    ]
                ]
            ]
        );

        // Start new conversation endpoint
        register_rest_route(
            $namespace,
            '/chat/conversation',
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'startConversation'],
                'permission_callback' => [$this, 'checkChatPermission'],
                'args' => [
                    'context' => [
                        'type' => 'object',
                        'description' => 'Initial chat context',
                        'default' => []
                    ],
                    'user_id' => [
                        'type' => 'integer',
                        'description' => 'User ID (optional)',
                        'sanitize_callback' => 'absint'
                    ],
                    'initial_message' => [
                        'type' => 'string',
                        'description' => 'Optional initial message to start conversation',
                        'sanitize_callback' => 'sanitize_textarea_field'
                    ]
                ]
            ]
        );

        // Streaming endpoint
        register_rest_route(
            $namespace,
            '/chat/stream',
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'streamMessage'],
                'permission_callback' => [$this, 'checkChatPermission'],
                'args' => [
                    'message' => [
                        'required' => true,
                        'type' => 'string',
                        'description' => 'User message content',
                        'validate_callback' => [$this, 'validateMessage'],
                        'sanitize_callback' => 'sanitize_textarea_field'
                    ],
                    'conversation_id' => [
                        'required' => true,
                        'type' => 'integer',
                        'description' => 'Conversation ID',
                        'sanitize_callback' => 'absint'
                    ],
                    'context' => [
                        'type' => 'object',
                        'description' => 'Chat context',
                        'default' => []
                    ]
                ]
            ]
        );

        $this->logger->debug('Chat endpoints registered', [
            'endpoints' => [
                'POST /chat/message',
                'GET /chat/conversation/{id}',
                'POST /chat/conversation',
                'POST /chat/stream'
            ]
        ]);
    }

    /**
     * Send message and get AI response
     *
     * Processes user messages, manages conversation state, generates AI responses
     * using knowledge base context, and handles both regular and streaming responses.
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public function sendMessage(WP_REST_Request $request)
    {
        $startTime = microtime(true);

        try {
            // Extract and validate parameters
            $message = $request->get_param('message');
            $conversationId = $request->get_param('conversation_id');
            $context = $request->get_param('context') ?: [];
            $streaming = $request->get_param('streaming') ?: false;
            $userId = get_current_user_id() ?: null;
            $sessionId = $this->getOrCreateSessionId($request);

            $this->logger->info('Processing chat message', [
                'message_length' => strlen($message),
                'conversation_id' => $conversationId,
                'user_id' => $userId,
                'session_id' => $sessionId,
                'streaming' => $streaming,
                'context' => $context
            ]);

            // Rate limiting check
            if (!$this->checkRateLimit($userId, $sessionId)) {
                return new WP_Error(
                    'rate_limit_exceeded',
                    'Rate limit exceeded. Please try again later.',
                    ['status' => 429]
                );
            }

            // Handle conversation management
            $conversation = $this->getOrCreateConversation($conversationId, $userId, $sessionId, $context);
            if (!$conversation) {
                return new WP_Error(
                    'conversation_error',
                    'Unable to create or retrieve conversation.',
                    ['status' => 500]
                );
            }

            $conversationId = $conversation['id'];

            // Check conversation limits
            if (!$this->checkConversationLimits($conversationId)) {
                return new WP_Error(
                    'conversation_limit_exceeded',
                    'Conversation message limit exceeded. Please start a new conversation.',
                    ['status' => 400]
                );
            }

            // Add user message to conversation
            $userMessageId = $this->conversationHandler->addMessage(
                $conversationId,
                ConversationHandler::ROLE_USER,
                $message,
                [
                    'user_ip' => Utils::getUserIp(),
                    'user_agent' => Utils::getUserAgent(),
                    'timestamp' => current_time('mysql')
                ]
            );

            if (!$userMessageId) {
                return new WP_Error(
                    'message_storage_error',
                    'Failed to store user message.',
                    ['status' => 500]
                );
            }

            // Get conversation history for context
            $conversationHistory = $this->getConversationHistory($conversationId, 10);

            // Handle streaming response
            if ($streaming) {
                return $this->handleStreamingResponse(
                    $message,
                    $conversationId,
                    $conversationHistory,
                    $context,
                    $startTime
                );
            }

            // Generate AI response
            $aiResponse = $this->generateAIResponse(
                $message,
                $conversationId,
                $conversationHistory,
                $context
            );

            // Store AI response
            $assistantMessageId = $this->conversationHandler->addMessage(
                $conversationId,
                ConversationHandler::ROLE_ASSISTANT,
                $aiResponse['response'],
                [
                    'model_used' => $aiResponse['model_used'],
                    'tokens_used' => $aiResponse['tokens_used'],
                    'processing_time_ms' => round($aiResponse['processing_time'] * 1000),
                    'context_chunks' => $aiResponse['context_chunks'],
                    'temperature' => $aiResponse['temperature'] ?? 0.7,
                    'is_fallback' => $aiResponse['is_fallback'] ?? false
                ]
            );

            if (!$assistantMessageId) {
                $this->logger->error('Failed to store AI response', [
                    'conversation_id' => $conversationId,
                    'response_length' => strlen($aiResponse['response'])
                ]);
            }

            // Update conversation context if needed
            if (!empty($context)) {
                $this->conversationHandler->updateConversationContext($conversationId, $context);
            }

            $totalProcessingTime = microtime(true) - $startTime;

            // Prepare response
            $response = [
                'success' => true,
                'data' => [
                    'conversation_id' => $conversationId,
                    'message_id' => $assistantMessageId,
                    'response' => $aiResponse['response'],
                    'model_used' => $aiResponse['model_used'],
                    'tokens_used' => $aiResponse['tokens_used'],
                    'processing_time' => round($totalProcessingTime, 3),
                    'context_chunks_used' => $aiResponse['context_chunks'],
                    'timestamp' => current_time('mysql'),
                    'is_fallback' => $aiResponse['is_fallback'] ?? false
                ],
                'metadata' => [
                    'conversation_status' => 'active',
                    'total_messages' => count($conversationHistory) + 2, // +2 for current exchange
                    'session_id' => $sessionId,
                    'rate_limit_remaining' => $this->getRateLimitRemaining($userId, $sessionId)
                ]
            ];

            $this->logger->info('Chat message processed successfully', [
                'conversation_id' => $conversationId,
                'response_length' => strlen($aiResponse['response']),
                'total_processing_time' => round($totalProcessingTime, 3),
                'tokens_used' => $aiResponse['tokens_used']
            ]);

            return new WP_REST_Response($response, 200);
        } catch (Exception $e) {
            $this->logger->error('Chat message processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'processing_time' => microtime(true) - $startTime
            ]);

            return new WP_Error(
                'chat_processing_error',
                'An error occurred while processing your message. Please try again.',
                ['status' => 500]
            );
        }
    }

    /**
     * Get conversation history
     *
     * Retrieves conversation details including messages, metadata, and context.
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public function getConversation(WP_REST_Request $request)
    {
        try {
            $conversationId = (int) $request->get_param('id');
            $includeMessages = $request->get_param('include_messages') !== 'false';
            $limit = (int) ($request->get_param('limit') ?: 50);
            $offset = (int) ($request->get_param('offset') ?: 0);

            $this->logger->debug('Retrieving conversation', [
                'conversation_id' => $conversationId,
                'include_messages' => $includeMessages,
                'limit' => $limit,
                'offset' => $offset
            ]);

            // Get conversation data
            $conversation = $this->conversationHandler->getConversation($conversationId, $includeMessages);

            if (!$conversation) {
                return new WP_Error(
                    'conversation_not_found',
                    'Conversation not found.',
                    ['status' => 404]
                );
            }

            // Check permission to access conversation
            if (!$this->canAccessConversation($conversation)) {
                return new WP_Error(
                    'conversation_access_denied',
                    'Access denied to this conversation.',
                    ['status' => 403]
                );
            }

            // Format messages if included
            if ($includeMessages && isset($conversation['messages'])) {
                $conversation['messages'] = $this->formatMessages($conversation['messages']);
                $conversation['total_messages'] = count($conversation['messages']);
            }

            // Add metadata
            $conversation['metadata'] = [
                'created_at_human' => human_time_diff(strtotime($conversation['created_at'])) . ' ago',
                'updated_at_human' => human_time_diff(strtotime($conversation['updated_at'])) . ' ago',
                'is_active' => $conversation['status'] === ConversationHandler::STATUS_ACTIVE,
                'can_send_messages' => $this->canSendMessages($conversation)
            ];

            $response = [
                'success' => true,
                'data' => $conversation
            ];

            $this->logger->info('Conversation retrieved successfully', [
                'conversation_id' => $conversationId,
                'message_count' => isset($conversation['messages']) ? count($conversation['messages']) : 0,
                'status' => $conversation['status']
            ]);

            return new WP_REST_Response($response, 200);
        } catch (Exception $e) {
            $this->logger->error('Failed to retrieve conversation', [
                'conversation_id' => $conversationId ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return new WP_Error(
                'conversation_retrieval_error',
                'Failed to retrieve conversation.',
                ['status' => 500]
            );
        }
    }

    /**
     * Start new conversation
     *
     * Creates a new conversation with optional initial context and user information.
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public function startConversation(WP_REST_Request $request)
    {
        try {
            $context = $request->get_param('context') ?: [];
            $userId = $request->get_param('user_id') ?: get_current_user_id();
            $sessionId = $this->getOrCreateSessionId($request);
            $initialMessage = $request->get_param('initial_message');

            $this->logger->info('Starting new conversation', [
                'user_id' => $userId,
                'session_id' => $sessionId,
                'context' => $context,
                'has_initial_message' => !empty($initialMessage)
            ]);

            // Check if user can create new conversations
            if (!$this->canCreateConversation($userId, $sessionId)) {
                return new WP_Error(
                    'conversation_limit_exceeded',
                    'Maximum number of active conversations reached.',
                    ['status' => 400]
                );
            }

            // Create conversation
            $conversation = $this->conversationHandler->createConversation([
                'user_id' => $userId,
                'session_id' => $sessionId,
                'context' => $context,
                'user_ip' => Utils::getUserIp(),
                'user_agent' => Utils::getUserAgent()
            ]);

            if (!$conversation) {
                return new WP_Error(
                    'conversation_creation_error',
                    'Failed to create conversation.',
                    ['status' => 500]
                );
            }

            // Add initial system message if context suggests it
            $systemMessage = $this->generateWelcomeMessage($context);
            if ($systemMessage) {
                $this->conversationHandler->addMessage(
                    $conversation['id'],
                    ConversationHandler::ROLE_SYSTEM,
                    $systemMessage,
                    ['type' => 'welcome_message']
                );
            }

            // Process initial message if provided
            $initialResponse = null;
            if (!empty($initialMessage)) {
                // Add user message
                $this->conversationHandler->addMessage(
                    $conversation['id'],
                    ConversationHandler::ROLE_USER,
                    $initialMessage,
                    [
                        'user_ip' => Utils::getUserIp(),
                        'user_agent' => Utils::getUserAgent(),
                        'is_initial' => true
                    ]
                );

                // Generate AI response
                $aiResponse = $this->generateAIResponse(
                    $initialMessage,
                    $conversation['id'],
                    [],
                    $context
                );

                // Store AI response
                $this->conversationHandler->addMessage(
                    $conversation['id'],
                    ConversationHandler::ROLE_ASSISTANT,
                    $aiResponse['response'],
                    [
                        'model_used' => $aiResponse['model_used'],
                        'tokens_used' => $aiResponse['tokens_used'],
                        'is_initial_response' => true
                    ]
                );

                $initialResponse = $aiResponse['response'];
            }

            // Prepare response
            $response = [
                'success' => true,
                'data' => [
                    'conversation_id' => $conversation['id'],
                    'session_id' => $conversation['session_id'],
                    'status' => $conversation['status'],
                    'created_at' => $conversation['created_at'],
                    'context' => $conversation['context'],
                    'initial_response' => $initialResponse,
                    'welcome_message' => $systemMessage
                ],
                'metadata' => [
                    'user_id' => $userId,
                    'can_send_messages' => true,
                    'message_count' => $initialMessage ? 2 : ($systemMessage ? 1 : 0),
                    'rate_limit_remaining' => $this->getRateLimitRemaining($userId, $sessionId)
                ]
            ];

            $this->logger->info('New conversation created successfully', [
                'conversation_id' => $conversation['id'],
                'session_id' => $conversation['session_id'],
                'user_id' => $userId,
                'has_initial_message' => !empty($initialMessage)
            ]);

            return new WP_REST_Response($response, 201);
        } catch (Exception $e) {
            $this->logger->error('Failed to create conversation', [
                'error' => $e->getMessage(),
                'user_id' => $userId ?? 'unknown',
                'session_id' => $sessionId ?? 'unknown'
            ]);

            return new WP_Error(
                'conversation_creation_error',
                'Failed to create conversation.',
                ['status' => 500]
            );
        }
    }

    /**
     * Check chat permission
     *
     * @param WP_REST_Request $request Request object
     * @return bool|WP_Error True if permission granted
     */
    public function checkChatPermission(WP_REST_Request $request)
    {
        // Basic security checks

        // Check if chat is enabled
        if (!$this->isChatEnabled()) {
            return new WP_Error(
                'chat_disabled',
                'Chat functionality is currently disabled.',
                ['status' => 503]
            );
        }

        // Check if site is in maintenance mode
        if ($this->isMaintenanceMode()) {
            return new WP_Error(
                'maintenance_mode',
                'Site is in maintenance mode. Chat is temporarily unavailable.',
                ['status' => 503]
            );
        }

        // Allow both logged in and guest users to chat
        // Rate limiting is handled in individual methods
        return true;
    }

    /**
     * Check if chat is enabled
     *
     * @return bool True if chat is enabled
     */
    private function isChatEnabled(): bool
    {
        // Check API configuration and license status
        return (
            $this->apiConfig->isValidConfiguration() &&
            ($this->apiConfig->isDevelopmentMode() || $this->apiConfig->hasValidLicense())
        );
    }

    /**
     * Check if site is in maintenance mode
     *
     * @return bool True if in maintenance mode
     */
    private function isMaintenanceMode(): bool
    {
        // Check WordPress maintenance mode or custom maintenance flag
        return (
            defined('WP_MAINTENANCE_MODE') && WP_MAINTENANCE_MODE ||
            get_option('woo_ai_assistant_maintenance_mode', false)
        );
    }

    /**
     * Validate message content
     *
     * @param string $message Message content
     * @param WP_REST_Request $request Request object
     * @param string $param Parameter name
     * @return bool|WP_Error True if valid
     */
    public function validateMessage($message, $request, $param)
    {
        if (empty(trim($message))) {
            return new WP_Error(
                'empty_message',
                'Message cannot be empty',
                ['status' => 400]
            );
        }

        if (strlen($message) > 4000) {
            return new WP_Error(
                'message_too_long',
                'Message is too long (maximum 4000 characters)',
                ['status' => 400]
            );
        }

        return true;
    }

    /**
     * Handle streaming message response
     *
     * Processes messages with streaming AI responses for real-time user experience.
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public function streamMessage(WP_REST_Request $request)
    {
        try {
            $message = $request->get_param('message');
            $conversationId = (int) $request->get_param('conversation_id');
            $context = $request->get_param('context') ?: [];

            // Verify conversation exists
            $conversation = $this->conversationHandler->getConversation($conversationId);
            if (!$conversation || !$this->canAccessConversation($conversation)) {
                return new WP_Error(
                    'conversation_not_found',
                    'Conversation not found or access denied.',
                    ['status' => 404]
                );
            }

            // Set headers for streaming
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no'); // Disable nginx buffering

            // Add user message
            $userMessageId = $this->conversationHandler->addMessage(
                $conversationId,
                ConversationHandler::ROLE_USER,
                $message
            );

            // Get conversation history
            $conversationHistory = $this->getConversationHistory($conversationId, 10);

            // Stream AI response
            $fullResponse = '';
            $totalTokens = 0;
            $startTime = microtime(true);

            foreach (
                $this->aiManager->streamResponse($message, [
                'conversation_id' => $conversationId,
                'conversation_history' => $conversationHistory,
                'user_context' => $context,
                'streaming' => true
                ]) as $chunk
            ) {
                $fullResponse .= $chunk['content'];
                $totalTokens = $chunk['tokens_used'];

                // Send chunk to client
                echo "data: " . wp_json_encode([
                    'type' => 'chunk',
                    'content' => $chunk['content'],
                    'is_final' => $chunk['is_final'],
                    'tokens_used' => $totalTokens
                ]) . "\n\n";

                if (ob_get_level()) {
                    ob_flush();
                }
                flush();

                // Check if client disconnected
                if (connection_aborted()) {
                    break;
                }
            }

            // Store complete AI response
            $assistantMessageId = $this->conversationHandler->addMessage(
                $conversationId,
                ConversationHandler::ROLE_ASSISTANT,
                $fullResponse,
                [
                    'tokens_used' => $totalTokens,
                    'processing_time_ms' => round((microtime(true) - $startTime) * 1000),
                    'streaming' => true
                ]
            );

            // Send final message
            echo "data: " . wp_json_encode([
                'type' => 'complete',
                'message_id' => $assistantMessageId,
                'total_tokens' => $totalTokens,
                'processing_time' => round(microtime(true) - $startTime, 3)
            ]) . "\n\n";

            if (ob_get_level()) {
                ob_flush();
            }
            flush();

            // Close connection
            echo "data: [DONE]\n\n";
            exit;
        } catch (Exception $e) {
            // Send error as server-sent event
            echo "data: " . wp_json_encode([
                'type' => 'error',
                'error' => $e->getMessage()
            ]) . "\n\n";
            exit;
        }
    }

    /**
     * Get or create session ID from request
     *
     * @param WP_REST_Request $request Request object
     * @return string Session ID
     */
    private function getOrCreateSessionId(WP_REST_Request $request): string
    {
        // Try to get from request headers or parameters
        $sessionId = $request->get_header('X-Session-ID') ?: $request->get_param('session_id');

        if (empty($sessionId)) {
            // Generate new session ID
            $sessionId = 'sess_' . uniqid() . '_' . wp_generate_password(16, false);
        }

        return sanitize_text_field($sessionId);
    }

    /**
     * Get or create conversation
     *
     * @param int|null $conversationId Existing conversation ID
     * @param int|null $userId User ID
     * @param string $sessionId Session ID
     * @param array $context Context data
     * @return array|false Conversation data or false on failure
     */
    private function getOrCreateConversation(?int $conversationId, ?int $userId, string $sessionId, array $context)
    {
        if ($conversationId) {
            $conversation = $this->conversationHandler->getConversation($conversationId);
            if ($conversation && $this->canAccessConversation($conversation)) {
                return $conversation;
            }
        }

        // Create new conversation
        return $this->conversationHandler->createConversation([
            'user_id' => $userId,
            'session_id' => $sessionId,
            'context' => $context,
            'user_ip' => Utils::getUserIp(),
            'user_agent' => Utils::getUserAgent()
        ]);
    }

    /**
     * Check if user can access conversation
     *
     * @param array $conversation Conversation data
     * @return bool True if user can access
     */
    private function canAccessConversation(array $conversation): bool
    {
        $currentUserId = get_current_user_id();
        $sessionId = $this->getOrCreateSessionId(new WP_REST_Request());

        // Allow access if:
        // 1. User owns the conversation
        // 2. Same session ID (for guest users)
        // 3. User is admin
        return (
            ($conversation['user_id'] && $conversation['user_id'] == $currentUserId) ||
            ($conversation['session_id'] === $sessionId) ||
            current_user_can('manage_woocommerce')
        );
    }

    /**
     * Check rate limiting
     *
     * @param int|null $userId User ID
     * @param string $sessionId Session ID
     * @return bool True if within limits
     */
    private function checkRateLimit(?int $userId, string $sessionId): bool
    {
        $identifier = $userId ? "user_{$userId}" : "session_{$sessionId}";
        $cacheKey = "woo_ai_rate_limit_{$identifier}";

        $rateLimitData = $this->cache->get($cacheKey);

        if ($rateLimitData === false) {
            $rateLimitData = [
                'messages_minute' => 0,
                'messages_hour' => 0,
                'minute_reset' => time() + 60,
                'hour_reset' => time() + 3600
            ];
        }

        // Reset counters if time periods have passed
        if (time() >= $rateLimitData['minute_reset']) {
            $rateLimitData['messages_minute'] = 0;
            $rateLimitData['minute_reset'] = time() + 60;
        }

        if (time() >= $rateLimitData['hour_reset']) {
            $rateLimitData['messages_hour'] = 0;
            $rateLimitData['hour_reset'] = time() + 3600;
        }

        // Check limits
        if (
            $rateLimitData['messages_minute'] >= $this->rateLimits['messages_per_minute'] ||
            $rateLimitData['messages_hour'] >= $this->rateLimits['messages_per_hour']
        ) {
            return false;
        }

        // Increment counters
        $rateLimitData['messages_minute']++;
        $rateLimitData['messages_hour']++;

        // Cache for 1 hour
        $this->cache->set($cacheKey, $rateLimitData, 3600);

        return true;
    }

    /**
     * Get remaining rate limit for user/session
     *
     * @param int|null $userId User ID
     * @param string $sessionId Session ID
     * @return array Rate limit remaining counts
     */
    private function getRateLimitRemaining(?int $userId, string $sessionId): array
    {
        $identifier = $userId ? "user_{$userId}" : "session_{$sessionId}";
        $cacheKey = "woo_ai_rate_limit_{$identifier}";

        $rateLimitData = $this->cache->get($cacheKey);

        if ($rateLimitData === false) {
            return [
                'messages_per_minute_remaining' => $this->rateLimits['messages_per_minute'],
                'messages_per_hour_remaining' => $this->rateLimits['messages_per_hour']
            ];
        }

        return [
            'messages_per_minute_remaining' => max(0, $this->rateLimits['messages_per_minute'] - $rateLimitData['messages_minute']),
            'messages_per_hour_remaining' => max(0, $this->rateLimits['messages_per_hour'] - $rateLimitData['messages_hour'])
        ];
    }

    /**
     * Check conversation message limits
     *
     * @param int $conversationId Conversation ID
     * @return bool True if within limits
     */
    private function checkConversationLimits(int $conversationId): bool
    {
        $messageCount = $this->conversationHandler->getConversationMessages($conversationId, 1000);
        return count($messageCount) < $this->maxMessagesPerConversation;
    }

    /**
     * Get conversation history formatted for AI context
     *
     * @param int $conversationId Conversation ID
     * @param int $limit Maximum messages to retrieve
     * @return array Formatted conversation history
     */
    private function getConversationHistory(int $conversationId, int $limit = 10): array
    {
        $messages = $this->conversationHandler->getConversationMessages($conversationId, $limit);

        $formattedHistory = [];
        foreach ($messages as $message) {
            if ($message['role'] !== ConversationHandler::ROLE_SYSTEM) {
                $formattedHistory[] = [
                    'role' => $message['role'],
                    'content' => $message['content'],
                    'timestamp' => $message['created_at']
                ];
            }
        }

        return $formattedHistory;
    }

    /**
     * Generate AI response using knowledge base and conversation context
     *
     * @param string $message User message
     * @param int $conversationId Conversation ID
     * @param array $conversationHistory Previous messages
     * @param array $context Additional context
     * @return array AI response data
     */
    private function generateAIResponse(string $message, int $conversationId, array $conversationHistory, array $context): array
    {
        return $this->aiManager->generateResponse($message, [
            'conversation_id' => $conversationId,
            'conversation_history' => $conversationHistory,
            'user_context' => $this->formatUserContext($context),
            'streaming' => false
        ]);
    }

    /**
     * Handle streaming response processing
     *
     * @param string $message User message
     * @param int $conversationId Conversation ID
     * @param array $conversationHistory Previous messages
     * @param array $context Additional context
     * @param float $startTime Start time for timing
     * @return WP_REST_Response Streaming response
     */
    private function handleStreamingResponse(string $message, int $conversationId, array $conversationHistory, array $context, float $startTime): WP_REST_Response
    {
        // For now, return a response indicating streaming should use the dedicated endpoint
        return new WP_REST_Response([
            'success' => true,
            'streaming' => true,
            'message' => 'Use the /chat/stream endpoint for streaming responses',
            'data' => [
                'conversation_id' => $conversationId,
                'stream_endpoint' => '/wp-json/woo-ai-assistant/v1/chat/stream',
                'instructions' => 'POST to stream endpoint with conversation_id and message'
            ]
        ], 200);
    }

    /**
     * Format user context for AI processing
     *
     * @param array $context Raw context data
     * @return string Formatted context string
     */
    private function formatUserContext(array $context): string
    {
        if (empty($context)) {
            return '';
        }

        $contextParts = [];

        if (isset($context['page'])) {
            $contextParts[] = "page: {$context['page']}";
        }

        if (isset($context['product_id'])) {
            $contextParts[] = "product_id: {$context['product_id']}";
        }

        if (isset($context['category'])) {
            $contextParts[] = "category: {$context['category']}";
        }

        if (isset($context['user_type'])) {
            $contextParts[] = "user_type: {$context['user_type']}";
        }

        return implode(', ', $contextParts);
    }

    /**
     * Format messages for API response
     *
     * @param array $messages Raw message data
     * @return array Formatted messages
     */
    private function formatMessages(array $messages): array
    {
        return array_map(function ($message) {
            return [
                'id' => $message['id'],
                'role' => $message['role'],
                'content' => $message['content'],
                'created_at' => $message['created_at'],
                'tokens_used' => $message['tokens_used'],
                'model_used' => $message['model_used'],
                'processing_time_ms' => $message['processing_time_ms'],
                'metadata' => json_decode($message['metadata'] ?? '{}', true)
            ];
        }, $messages);
    }

    /**
     * Check if user can send messages to conversation
     *
     * @param array $conversation Conversation data
     * @return bool True if user can send messages
     */
    private function canSendMessages(array $conversation): bool
    {
        return (
            $conversation['status'] === ConversationHandler::STATUS_ACTIVE &&
            $this->canAccessConversation($conversation)
        );
    }

    /**
     * Check if user can create new conversations
     *
     * @param int|null $userId User ID
     * @param string $sessionId Session ID
     * @return bool True if user can create conversations
     */
    private function canCreateConversation(?int $userId, string $sessionId): bool
    {
        // Check active conversation limit
        $activeConversations = $this->conversationHandler->getSessionConversations($sessionId);
        $activeCount = array_reduce($activeConversations, function ($count, $conv) {
            return $conv['status'] === ConversationHandler::STATUS_ACTIVE ? $count + 1 : $count;
        }, 0);

        return $activeCount < $this->rateLimits['concurrent_conversations'];
    }

    /**
     * Generate welcome message based on context
     *
     * @param array $context Context data
     * @return string|null Welcome message or null if none needed
     */
    private function generateWelcomeMessage(array $context): ?string
    {
        if (isset($context['page'])) {
            switch ($context['page']) {
                case 'product':
                    return 'Hello! I can help you with questions about this product, pricing, availability, and more.';
                case 'cart':
                    return 'Hi! I\'m here to help with your shopping cart, checkout process, or any questions about your selected items.';
                case 'checkout':
                    return 'Hello! I can assist you with the checkout process, payment options, shipping, and any concerns you might have.';
                case 'shop':
                    return 'Hi! I\'m here to help you find products, answer questions about our store, and assist with your shopping experience.';
                default:
                    return 'Hello! I\'m your AI shopping assistant. How can I help you today?';
            }
        }

        return 'Hello! I\'m your AI shopping assistant. How can I help you today?';
    }
}
