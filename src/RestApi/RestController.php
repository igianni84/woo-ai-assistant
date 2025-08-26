<?php

/**
 * REST API Controller Class
 *
 * Manages all REST API endpoints for the Woo AI Assistant plugin.
 * Handles authentication, security, and provides comprehensive API structure
 * for communication between the React widget and WordPress backend.
 *
 * @package WooAiAssistant
 * @subpackage RestApi
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\RestApi;

use WooAiAssistant\Common\Utils;
use WooAiAssistant\Common\Traits\Singleton;
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
 * Handles REST API registration, authentication, security, and endpoint management
 * for the Woo AI Assistant plugin. Provides a comprehensive API structure for
 * chat functionality, admin operations, and frontend integration.
 *
 * @since 1.0.0
 */
class RestController
{
    use Singleton;

    /**
     * REST API namespace
     *
     * @since 1.0.0
     * @var string
     */
    private string $namespace = 'woo-ai-assistant/v1';

    /**
     * Rate limiting data
     *
     * @since 1.0.0
     * @var array
     */
    private array $rateLimits = [];

    /**
     * Default rate limit settings
     *
     * @since 1.0.0
     * @var array
     */
    private array $defaultRateLimits = [
        'chat' => ['requests' => 60, 'window' => 3600], // 60 requests per hour for chat
        'action' => ['requests' => 30, 'window' => 3600], // 30 requests per hour for actions
        'rating' => ['requests' => 10, 'window' => 3600], // 10 requests per hour for ratings
        'default' => ['requests' => 100, 'window' => 3600] // 100 requests per hour default
    ];

    /**
     * Constructor
     *
     * Initializes the REST API controller and sets up WordPress hooks.
     *
     * @since 1.0.0
     */
    private function __construct()
    {
        $this->setupHooks();
    }

    /**
     * Setup WordPress hooks
     *
     * @since 1.0.0
     * @return void
     */
    private function setupHooks(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
        add_filter('rest_pre_serve_request', [$this, 'addCorsHeaders'], 10, 4);
        add_action('rest_api_init', [$this, 'initializeRateLimiting']);
    }

    /**
     * Register all REST API routes
     *
     * @since 1.0.0
     * @return void
     */
    public function registerRoutes(): void
    {
        Utils::logDebug('Registering REST API routes for namespace: ' . $this->namespace);

        // Frontend endpoints
        $this->registerFrontendRoutes();

        // Admin endpoints
        $this->registerAdminRoutes();

        // System endpoints
        $this->registerSystemRoutes();

        // Test endpoints (for development and integration testing)
        $this->registerTestRoutes();

        /**
         * REST routes registered action
         *
         * Fired after all REST API routes have been registered.
         *
         * @since 1.0.0
         * @param RestController $instance The REST controller instance
         */
        do_action('woo_ai_assistant_rest_routes_registered', $this);

        Utils::logDebug('All REST API routes registered successfully');
    }

    /**
     * Register frontend-specific endpoints
     *
     * @since 1.0.0
     * @return void
     */
    private function registerFrontendRoutes(): void
    {
        // Chat endpoint - Handle chat messages
        \register_rest_route($this->namespace, '/chat', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'handleChat'],
            'permission_callback' => [$this, 'checkFrontendPermissions'],
            'args' => [
                'message' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => 'User message content',
                    'sanitize_callback' => 'sanitize_textarea_field',
                    'validate_callback' => [$this, 'validateChatMessage']
                ],
                'conversation_id' => [
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Existing conversation ID',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'user_context' => [
                    'required' => false,
                    'type' => 'object',
                    'description' => 'User context data (page, product, etc.)',
                    'default' => []
                ],
                'nonce' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Security nonce',
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);

        // Action endpoint - Execute agentic actions
        \register_rest_route($this->namespace, '/action', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'handleAction'],
            'permission_callback' => [$this, 'checkFrontendPermissions'],
            'args' => [
                'action_type' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Type of action to execute',
                    'enum' => ['add_to_cart', 'apply_coupon', 'get_product_info', 'get_shipping_info'],
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'action_data' => [
                    'required' => true,
                    'type' => 'object',
                    'description' => 'Action-specific data'
                ],
                'conversation_id' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Conversation ID for tracking',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'nonce' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Security nonce',
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);

        // Rating endpoint - Collect conversation ratings
        \register_rest_route($this->namespace, '/rating', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'handleRating'],
            'permission_callback' => [$this, 'checkFrontendPermissions'],
            'args' => [
                'conversation_id' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Conversation ID to rate',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'rating' => [
                    'required' => true,
                    'type' => 'integer',
                    'description' => 'Rating value (1-5 stars)',
                    'minimum' => 1,
                    'maximum' => 5
                ],
                'feedback' => [
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Optional feedback text',
                    'sanitize_callback' => 'sanitize_textarea_field'
                ],
                'nonce' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Security nonce',
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);

        // Config endpoint - Provide widget configuration
        \register_rest_route($this->namespace, '/config', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'getWidgetConfig'],
            'permission_callback' => [$this, 'checkPublicPermissions'],
            'args' => [
                'page_context' => [
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Current page context',
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);

        Utils::logDebug('Frontend REST API routes registered');
    }

    /**
     * Register admin-specific endpoints
     *
     * @since 1.0.0
     * @return void
     */
    private function registerAdminRoutes(): void
    {
        // Dashboard data endpoint
        \register_rest_route($this->namespace, '/admin/dashboard', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'getDashboardData'],
            'permission_callback' => [$this, 'checkAdminPermissions']
        ]);

        // Conversations endpoint
        \register_rest_route($this->namespace, '/admin/conversations', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getConversations'],
                'permission_callback' => [$this, 'checkAdminPermissions'],
                'args' => [
                    'page' => [
                        'required' => false,
                        'type' => 'integer',
                        'default' => 1,
                        'minimum' => 1
                    ],
                    'per_page' => [
                        'required' => false,
                        'type' => 'integer',
                        'default' => 20,
                        'minimum' => 1,
                        'maximum' => 100
                    ],
                    'status' => [
                        'required' => false,
                        'type' => 'string',
                        'enum' => ['all', 'active', 'completed', 'transferred']
                    ]
                ]
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'deleteConversation'],
                'permission_callback' => [$this, 'checkAdminPermissions'],
                'args' => [
                    'conversation_id' => [
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field'
                    ]
                ]
            ]
        ]);

        // Settings endpoints
        \register_rest_route($this->namespace, '/admin/settings', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getSettings'],
                'permission_callback' => [$this, 'checkAdminPermissions']
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateSettings'],
                'permission_callback' => [$this, 'checkAdminPermissions'],
                'args' => [
                    'settings' => [
                        'required' => true,
                        'type' => 'object',
                        'description' => 'Settings object'
                    ]
                ]
            ]
        ]);

        // Knowledge base health endpoint
        \register_rest_route($this->namespace, '/admin/kb-health', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'getKnowledgeBaseHealth'],
            'permission_callback' => [$this, 'checkAdminPermissions']
        ]);

        // License management endpoints
        $this->registerLicenseRoutes();

        Utils::logDebug('Admin REST API routes registered');
    }

    /**
     * Register system-level endpoints
     *
     * @since 1.0.0
     * @return void
     */
    private function registerSystemRoutes(): void
    {
        // Health check endpoint
        \register_rest_route($this->namespace, '/health', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'healthCheck'],
            'permission_callback' => [$this, 'checkPublicPermissions']
        ]);

        // Version info endpoint
        \register_rest_route($this->namespace, '/version', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'getVersionInfo'],
            'permission_callback' => [$this, 'checkPublicPermissions']
        ]);

        Utils::logDebug('System REST API routes registered');
    }

    /**
     * Register test endpoints for KB functionality
     *
     * @since 1.0.0
     * @return void
     */
    private function registerTestRoutes(): void
    {
        // Test Scanner functionality
        \register_rest_route($this->namespace, '/test/scan', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'testScanner'],
            'permission_callback' => [$this, 'checkAdminPermissions'],
            'args' => [
                'content_type' => [
                    'required' => false,
                    'type' => 'string',
                    'default' => 'products',
                    'enum' => ['products', 'pages', 'posts', 'woo_settings', 'categories'],
                    'description' => 'Type of content to scan'
                ],
                'limit' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 10,
                    'minimum' => 1,
                    'maximum' => 100,
                    'description' => 'Number of items to scan'
                ]
            ]
        ]);

        // Test Indexer functionality
        \register_rest_route($this->namespace, '/test/index', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'testIndexer'],
            'permission_callback' => [$this, 'checkAdminPermissions'],
            'args' => [
                'sample_content' => [
                    'required' => false,
                    'type' => 'string',
                    'default' => 'This is sample content for testing the indexer functionality. It should be chunked and stored properly.',
                    'description' => 'Sample content to index'
                ],
                'chunk_size' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 500,
                    'minimum' => 100,
                    'maximum' => 2000,
                    'description' => 'Chunk size for processing'
                ]
            ]
        ]);

        // Test VectorManager functionality
        \register_rest_route($this->namespace, '/test/vector', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'testVectorManager'],
            'permission_callback' => [$this, 'checkAdminPermissions'],
            'args' => [
                'query' => [
                    'required' => false,
                    'type' => 'string',
                    'default' => 'shipping cost information',
                    'description' => 'Query for similarity search'
                ],
                'top_k' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 5,
                    'minimum' => 1,
                    'maximum' => 20,
                    'description' => 'Number of results to return'
                ]
            ]
        ]);

        // Test AIManager functionality
        \register_rest_route($this->namespace, '/test/ai', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'testAIManager'],
            'permission_callback' => [$this, 'checkAdminPermissions'],
            'args' => [
                'query' => [
                    'required' => false,
                    'type' => 'string',
                    'default' => 'What are your shipping options?',
                    'description' => 'Query for AI response generation'
                ],
                'use_rag' => [
                    'required' => false,
                    'type' => 'boolean',
                    'default' => true,
                    'description' => 'Whether to use RAG for context'
                ]
            ]
        ]);

        // Test full KB pipeline
        \register_rest_route($this->namespace, '/test/full-pipeline', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'testFullPipeline'],
            'permission_callback' => [$this, 'checkAdminPermissions'],
            'args' => [
                'query' => [
                    'required' => false,
                    'type' => 'string',
                    'default' => 'Tell me about your return policy',
                    'description' => 'Query for full pipeline test'
                ]
            ]
        ]);

        Utils::logDebug('Test REST API routes registered');
    }

    /**
     * Handle chat message processing
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function handleChat(WP_REST_Request $request)
    {
        try {
            // Verify nonce
            if (!$this->verifyNonce($request->get_param('nonce'), 'woo_ai_chat')) {
                return $this->createErrorResponse('invalid_nonce', 'Security check failed', 403);
            }

            // Apply rate limiting
            if (!$this->checkRateLimit('chat', $this->getCurrentUserId())) {
                return $this->createErrorResponse('rate_limit_exceeded', 'Too many requests', 429);
            }

            $message = $request->get_param('message');
            $conversationId = $request->get_param('conversation_id');
            $userContext = $request->get_param('user_context') ?: [];

            Utils::logDebug('Processing chat message', [
                'message_length' => strlen($message),
                'conversation_id' => $conversationId,
                'user_context' => $userContext
            ]);

            // TODO: Implement actual chat processing logic in Phase 5
            // For now, return a mock response
            $response = [
                'success' => true,
                'data' => [
                    'conversation_id' => $conversationId ?: $this->generateConversationId(),
                    'response' => 'Thank you for your message. This is a placeholder response until the AI system is implemented.',
                    'timestamp' => current_time('mysql'),
                    'confidence' => 0.8,
                    'sources' => []
                ]
            ];

            return $this->createSuccessResponse($response);
        } catch (Exception $e) {
            Utils::logError('Chat processing error: ' . $e->getMessage());
            return $this->createErrorResponse('chat_processing_error', 'Failed to process chat message', 500);
        }
    }

    /**
     * Handle agentic action execution
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function handleAction(WP_REST_Request $request)
    {
        try {
            // Verify nonce
            if (!$this->verifyNonce($request->get_param('nonce'), 'woo_ai_action')) {
                return $this->createErrorResponse('invalid_nonce', 'Security check failed', 403);
            }

            // Apply rate limiting
            if (!$this->checkRateLimit('action', $this->getCurrentUserId())) {
                return $this->createErrorResponse('rate_limit_exceeded', 'Too many requests', 429);
            }

            $actionType = $request->get_param('action_type');
            $actionData = $request->get_param('action_data');
            $conversationId = $request->get_param('conversation_id');

            Utils::logDebug('Processing action', [
                'action_type' => $actionType,
                'conversation_id' => $conversationId
            ]);

            // TODO: Implement actual action processing logic in Phase 8
            // For now, return a mock response
            $response = [
                'success' => true,
                'data' => [
                    'action_type' => $actionType,
                    'result' => 'Action queued for processing',
                    'conversation_id' => $conversationId,
                    'timestamp' => current_time('mysql')
                ]
            ];

            return $this->createSuccessResponse($response);
        } catch (Exception $e) {
            Utils::logError('Action processing error: ' . $e->getMessage());
            return $this->createErrorResponse('action_processing_error', 'Failed to process action', 500);
        }
    }

    /**
     * Handle conversation rating submission
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function handleRating(WP_REST_Request $request)
    {
        try {
            // Verify nonce
            if (!$this->verifyNonce($request->get_param('nonce'), 'woo_ai_rating')) {
                return $this->createErrorResponse('invalid_nonce', 'Security check failed', 403);
            }

            // Apply rate limiting
            if (!$this->checkRateLimit('rating', $this->getCurrentUserId())) {
                return $this->createErrorResponse('rate_limit_exceeded', 'Too many requests', 429);
            }

            $conversationId = $request->get_param('conversation_id');
            $rating = absint($request->get_param('rating'));
            $feedback = $request->get_param('feedback');

            Utils::logDebug('Processing rating', [
                'conversation_id' => $conversationId,
                'rating' => $rating,
                'has_feedback' => !empty($feedback)
            ]);

            // TODO: Implement actual rating storage logic in Phase 5
            // For now, return a mock response
            $response = [
                'success' => true,
                'data' => [
                    'conversation_id' => $conversationId,
                    'rating' => $rating,
                    'feedback_recorded' => !empty($feedback),
                    'timestamp' => current_time('mysql')
                ]
            ];

            return $this->createSuccessResponse($response);
        } catch (Exception $e) {
            Utils::logError('Rating processing error: ' . $e->getMessage());
            return $this->createErrorResponse('rating_processing_error', 'Failed to process rating', 500);
        }
    }

    /**
     * Get widget configuration
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function getWidgetConfig(WP_REST_Request $request): WP_REST_Response
    {
        $pageContext = $request->get_param('page_context');

        // Get basic widget configuration
        $config = [
            'enabled' => true,
            'theme' => get_option('woo_ai_assistant_theme', 'light'),
            'position' => get_option('woo_ai_assistant_position', 'bottom-right'),
            'greeting_message' => get_option('woo_ai_assistant_greeting', __('Hello! How can I help you today?', 'woo-ai-assistant')),
            'avatar_url' => get_option('woo_ai_assistant_avatar', WOO_AI_ASSISTANT_URL . 'assets/images/avatar-default.png'),
            'nonces' => [
                'chat' => wp_create_nonce('woo_ai_chat'),
                'action' => wp_create_nonce('woo_ai_action'),
                'rating' => wp_create_nonce('woo_ai_rating')
            ],
            'endpoints' => [
                'chat' => rest_url($this->namespace . '/chat'),
                'action' => rest_url($this->namespace . '/action'),
                'rating' => rest_url($this->namespace . '/rating')
            ],
            'features' => [
                'ratings' => true,
                'quick_actions' => true,
                'product_cards' => Utils::isWooCommerceActive(),
                'typing_indicator' => true
            ]
        ];

        // Add page-specific configuration
        if ($pageContext) {
            $config['page_context'] = $this->getPageContextData($pageContext);
        }

        return $this->createSuccessResponse($config);
    }

    /**
     * Get dashboard data for admin
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function getDashboardData(WP_REST_Request $request): WP_REST_Response
    {
        // TODO: Implement actual dashboard data retrieval in Phase 7
        $data = [
            'stats' => [
                'total_conversations' => 0,
                'resolution_rate' => 0,
                'avg_rating' => 0,
                'total_actions' => 0
            ],
            'recent_conversations' => [],
            'kb_health' => [
                'score' => 0,
                'last_updated' => null,
                'total_entries' => 0
            ]
        ];

        return $this->createSuccessResponse($data);
    }

    /**
     * Get conversations for admin
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function getConversations(WP_REST_Request $request): WP_REST_Response
    {
        $page = $request->get_param('page');
        $perPage = $request->get_param('per_page');
        $status = $request->get_param('status');

        // TODO: Implement actual conversation retrieval in Phase 7
        $data = [
            'conversations' => [],
            'total' => 0,
            'pages' => 0,
            'current_page' => $page
        ];

        return $this->createSuccessResponse($data);
    }

    /**
     * Delete conversation
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function deleteConversation(WP_REST_Request $request): WP_REST_Response
    {
        $conversationId = $request->get_param('conversation_id');

        // TODO: Implement actual conversation deletion in Phase 7
        return $this->createSuccessResponse(['deleted' => true, 'conversation_id' => $conversationId]);
    }

    /**
     * Get plugin settings
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function getSettings(WP_REST_Request $request): WP_REST_Response
    {
        // TODO: Implement actual settings retrieval in Phase 7
        $settings = [
            'general' => [],
            'appearance' => [],
            'ai_settings' => [],
            'proactive_triggers' => []
        ];

        return $this->createSuccessResponse($settings);
    }

    /**
     * Update plugin settings
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function updateSettings(WP_REST_Request $request): WP_REST_Response
    {
        $settings = $request->get_param('settings');

        // TODO: Implement actual settings update in Phase 7
        return $this->createSuccessResponse(['updated' => true]);
    }

    /**
     * Get knowledge base health information
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function getKnowledgeBaseHealth(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $main = \WooAiAssistant\Main::getInstance();
            $health = $main->getKnowledgeBaseHealth();

            // Add additional health metrics
            global $wpdb;

            $kbTable = $wpdb->prefix . 'woo_ai_knowledge_base';
            $conversationsTable = $wpdb->prefix . 'woo_ai_conversations';

            $health['metrics'] = [
                'total_kb_entries' => $wpdb->get_var("SELECT COUNT(*) FROM {$kbTable}"),
                'total_conversations' => $wpdb->get_var("SELECT COUNT(*) FROM {$conversationsTable}"),
                'avg_confidence' => $wpdb->get_var("SELECT AVG(confidence) FROM {$conversationsTable} WHERE confidence > 0"),
                'last_kb_update' => $wpdb->get_var("SELECT MAX(updated_at) FROM {$kbTable}")
            ];

            // Calculate health score based on component status
            $componentCount = count($health['components']);
            $healthyComponents = array_count_values($health['components'])['loaded'] ?? 0;
            $health['calculated_score'] = $componentCount > 0 ? round(($healthyComponents / $componentCount) * 100) : 0;

            return $this->createSuccessResponse($health);
        } catch (Exception $e) {
            Utils::logError('KB health check error: ' . $e->getMessage());
            return $this->createErrorResponse('kb_health_check_error', $e->getMessage(), 500);
        }
    }

    /**
     * Health check endpoint
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function healthCheck(WP_REST_Request $request): WP_REST_Response
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => current_time('c'),
            'version' => WOO_AI_ASSISTANT_VERSION,
            'wordpress' => get_bloginfo('version'),
            'woocommerce' => defined('WC_VERSION') ? WC_VERSION : 'not_active',
            'php' => PHP_VERSION,
            'checks' => [
                'database' => $this->checkDatabaseConnection(),
                'dependencies' => $this->checkDependencies(),
                'permissions' => $this->checkFilePermissions()
            ]
        ];

        return $this->createSuccessResponse($health);
    }

    /**
     * Get version information
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function getVersionInfo(WP_REST_Request $request): WP_REST_Response
    {
        $info = [
            'plugin_version' => WOO_AI_ASSISTANT_VERSION,
            'api_version' => 'v1',
            'wordpress_version' => get_bloginfo('version'),
            'woocommerce_version' => defined('WC_VERSION') ? WC_VERSION : null,
            'php_version' => PHP_VERSION,
            'api_namespace' => $this->namespace
        ];

        return $this->createSuccessResponse($info);
    }

    /**
     * Check frontend permissions
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return bool True if permitted
     */
    public function checkFrontendPermissions(WP_REST_Request $request): bool
    {
        // Allow all frontend users (including guests) for chat functionality
        return true;
    }

    /**
     * Check admin permissions
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return bool True if permitted
     */
    public function checkAdminPermissions(WP_REST_Request $request): bool
    {
        return current_user_can('manage_woocommerce') || current_user_can('manage_options');
    }

    /**
     * Check public permissions (no authentication required)
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return bool Always true
     */
    public function checkPublicPermissions(WP_REST_Request $request): bool
    {
        return true;
    }

    /**
     * Add CORS headers
     *
     * @since 1.0.0
     * @param bool $served Whether the request has already been served
     * @param WP_HTTP_Response $result Result to send to the client
     * @param WP_REST_Request $request Request used to generate the response
     * @param WP_REST_Server $server Server instance
     * @return bool
     */
    public function addCorsHeaders($served, $result, $request, $server): bool
    {
        // Only add CORS headers for our API endpoints
        if (strpos($request->get_route(), $this->namespace) !== false) {
            $result->header('Access-Control-Allow-Origin', '*');
            $result->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
            $result->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-WP-Nonce');
            $result->header('Access-Control-Allow-Credentials', 'true');
        }

        return $served;
    }

    /**
     * Initialize rate limiting
     *
     * @since 1.0.0
     * @return void
     */
    public function initializeRateLimiting(): void
    {
        // Load rate limits from cache or database
        $this->rateLimits = get_transient('woo_ai_assistant_rate_limits') ?: [];
    }

    /**
     * Check rate limit for specific action and user
     *
     * @since 1.0.0
     * @param string $action Action type
     * @param string $userId User identifier
     * @return bool True if within limits
     */
    private function checkRateLimit(string $action, string $userId): bool
    {
        $limits = $this->defaultRateLimits[$action] ?? $this->defaultRateLimits['default'];
        $key = $action . '_' . $userId;
        $now = time();

        if (!isset($this->rateLimits[$key])) {
            $this->rateLimits[$key] = [
                'requests' => 1,
                'window_start' => $now
            ];
        } else {
            $data = $this->rateLimits[$key];

            // Reset window if expired
            if ($now - $data['window_start'] > $limits['window']) {
                $this->rateLimits[$key] = [
                    'requests' => 1,
                    'window_start' => $now
                ];
            } else {
                // Check if within limits
                if ($data['requests'] >= $limits['requests']) {
                    return false;
                }

                $this->rateLimits[$key]['requests']++;
            }
        }

        // Save rate limits to cache
        set_transient('woo_ai_assistant_rate_limits', $this->rateLimits, HOUR_IN_SECONDS);

        return true;
    }

    /**
     * Verify nonce for security
     *
     * @since 1.0.0
     * @param string $nonce Nonce to verify
     * @param string $action Action name
     * @return bool True if valid
     */
    private function verifyNonce(string $nonce, string $action): bool
    {
        return wp_verify_nonce($nonce, $action);
    }

    /**
     * Get current user ID for rate limiting
     *
     * @since 1.0.0
     * @return string User ID or IP address for guests
     */
    private function getCurrentUserId(): string
    {
        $userId = get_current_user_id();
        if ($userId) {
            return 'user_' . $userId;
        }

        // Use IP address for guest users
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        return 'ip_' . md5($ip);
    }

    /**
     * Generate unique conversation ID
     *
     * @since 1.0.0
     * @return string Unique conversation ID
     */
    private function generateConversationId(): string
    {
        return 'conv_' . uniqid() . '_' . time();
    }

    /**
     * Get page context data
     *
     * @since 1.0.0
     * @param string $pageContext Page context identifier
     * @return array Context data
     */
    private function getPageContextData(string $pageContext): array
    {
        // TODO: Implement context-specific data retrieval
        return [
            'page_type' => $pageContext,
            'data' => []
        ];
    }

    /**
     * Validate chat message
     *
     * @since 1.0.0
     * @param string $value Message content
     * @param WP_REST_Request $request Request object
     * @param string $param Parameter name
     * @return bool|WP_Error True if valid, WP_Error otherwise
     */
    public function validateChatMessage($value, $request, $param)
    {
        if (empty(trim($value))) {
            return new WP_Error('empty_message', 'Message cannot be empty', ['status' => 400]);
        }

        if (strlen($value) > 2000) {
            return new WP_Error('message_too_long', 'Message is too long (max 2000 characters)', ['status' => 400]);
        }

        return true;
    }

    /**
     * Create success response
     *
     * @since 1.0.0
     * @param mixed $data Response data
     * @param int $status HTTP status code
     * @return WP_REST_Response Response object
     */
    private function createSuccessResponse($data, int $status = 200): WP_REST_Response
    {
        return new WP_REST_Response([
            'success' => true,
            'data' => $data,
            'timestamp' => current_time('c')
        ], $status);
    }

    /**
     * Create error response
     *
     * @since 1.0.0
     * @param string $code Error code
     * @param string $message Error message
     * @param int $status HTTP status code
     * @return WP_Error Error object
     */
    private function createErrorResponse(string $code, string $message, int $status = 400): WP_Error
    {
        return new WP_Error($code, $message, ['status' => $status]);
    }

    /**
     * Check database connection
     *
     * @since 1.0.0
     * @return bool True if connection is working
     */
    private function checkDatabaseConnection(): bool
    {
        global $wpdb;
        return $wpdb->check_connection();
    }

    /**
     * Check plugin dependencies
     *
     * @since 1.0.0
     * @return bool True if all dependencies are met
     */
    private function checkDependencies(): bool
    {
        return Utils::isWooCommerceActive() &&
               version_compare(PHP_VERSION, '8.2', '>=') &&
               version_compare(get_bloginfo('version'), '6.0', '>=');
    }

    /**
     * Check file permissions
     *
     * @since 1.0.0
     * @return bool True if permissions are correct
     */
    private function checkFilePermissions(): bool
    {
        $uploadDir = wp_upload_dir();
        return is_writable($uploadDir['basedir']);
    }

    /**
     * Get REST API namespace
     *
     * @since 1.0.0
     * @return string API namespace
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * Get registered endpoints
     *
     * @since 1.0.0
     * @return array Array of registered endpoints
     */
    public function getEndpoints(): array
    {
        return [
            'frontend' => [
                'chat' => rest_url($this->namespace . '/chat'),
                'action' => rest_url($this->namespace . '/action'),
                'rating' => rest_url($this->namespace . '/rating'),
                'config' => rest_url($this->namespace . '/config')
            ],
            'admin' => [
                'dashboard' => rest_url($this->namespace . '/admin/dashboard'),
                'conversations' => rest_url($this->namespace . '/admin/conversations'),
                'settings' => rest_url($this->namespace . '/admin/settings'),
                'kb_health' => rest_url($this->namespace . '/admin/kb-health')
            ],
            'system' => [
                'health' => rest_url($this->namespace . '/health'),
                'version' => rest_url($this->namespace . '/version')
            ],
            'test' => [
                'scan' => rest_url($this->namespace . '/test/scan'),
                'index' => rest_url($this->namespace . '/test/index'),
                'vector' => rest_url($this->namespace . '/test/vector'),
                'ai' => rest_url($this->namespace . '/test/ai'),
                'full_pipeline' => rest_url($this->namespace . '/test/full-pipeline')
            ]
        ];
    }

    /**
     * Test Scanner functionality
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function testScanner(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $contentType = $request->get_param('content_type');
            $limit = $request->get_param('limit');

            Utils::logDebug('Testing Scanner functionality', ['content_type' => $contentType, 'limit' => $limit]);

            // Get Main instance to access KB components
            $main = \WooAiAssistant\Main::getInstance();
            $scanner = $main->getComponent('kb_scanner');

            if (!$scanner) {
                return $this->createErrorResponse('scanner_not_found', 'Scanner component not available', 500);
            }

            $startTime = microtime(true);
            $results = [];

            // Test different scanning methods based on content type
            switch ($contentType) {
                case 'products':
                    $results = $scanner->scanProducts(['limit' => $limit]);
                    break;
                case 'pages':
                    $results = $scanner->scanPages(['limit' => $limit]);
                    break;
                case 'woo_settings':
                    $results = $scanner->scanWooSettings();
                    break;
                case 'categories':
                    $results = $scanner->scanCategories(['limit' => $limit]);
                    break;
                default:
                    $results = $scanner->scanProducts(['limit' => $limit]);
            }

            $executionTime = microtime(true) - $startTime;

            $response = [
                'test_type' => 'scanner',
                'content_type' => $contentType,
                'execution_time' => round($executionTime, 4),
                'results_count' => count($results),
                'sample_results' => array_slice($results, 0, 3), // Show first 3 results
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true)
            ];

            return $this->createSuccessResponse($response);
        } catch (Exception $e) {
            Utils::logError('Scanner test error: ' . $e->getMessage());
            return $this->createErrorResponse('scanner_test_error', $e->getMessage(), 500);
        }
    }

    /**
     * Test Indexer functionality
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function testIndexer(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $sampleContent = $request->get_param('sample_content');
            $chunkSize = $request->get_param('chunk_size');

            Utils::logDebug('Testing Indexer functionality', ['chunk_size' => $chunkSize]);

            $main = \WooAiAssistant\Main::getInstance();
            $indexer = $main->getComponent('kb_indexer');

            if (!$indexer) {
                return $this->createErrorResponse('indexer_not_found', 'Indexer component not available', 500);
            }

            $startTime = microtime(true);

            // Create test content array
            $testContent = [[
                'id' => 'test_' . uniqid(),
                'title' => 'Test Content for Indexer',
                'content' => $sampleContent,
                'type' => 'test',
                'url' => 'http://test.com/test-content',
                'metadata' => [
                    'test' => true,
                    'created_at' => current_time('mysql')
                ]
            ]];

            // Test indexing
            $indexResults = $indexer->indexContent($testContent, ['chunk_size' => $chunkSize]);

            $executionTime = microtime(true) - $startTime;

            $response = [
                'test_type' => 'indexer',
                'chunk_size' => $chunkSize,
                'execution_time' => round($executionTime, 4),
                'chunks_created' => $indexResults['chunks_created'] ?? 0,
                'content_length' => strlen($sampleContent),
                'index_results' => $indexResults,
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true)
            ];

            return $this->createSuccessResponse($response);
        } catch (Exception $e) {
            Utils::logError('Indexer test error: ' . $e->getMessage());
            return $this->createErrorResponse('indexer_test_error', $e->getMessage(), 500);
        }
    }

    /**
     * Test VectorManager functionality
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function testVectorManager(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $query = $request->get_param('query');
            $topK = $request->get_param('top_k');

            Utils::logDebug('Testing VectorManager functionality', ['query' => $query, 'top_k' => $topK]);

            $main = \WooAiAssistant\Main::getInstance();
            $vectorManager = $main->getComponent('kb_vector_manager');

            if (!$vectorManager) {
                return $this->createErrorResponse('vector_manager_not_found', 'VectorManager component not available', 500);
            }

            $startTime = microtime(true);

            // Test similarity search
            $searchResults = $vectorManager->searchSimilar($query, $topK);

            $executionTime = microtime(true) - $startTime;

            $response = [
                'test_type' => 'vector_manager',
                'query' => $query,
                'top_k' => $topK,
                'execution_time' => round($executionTime, 4),
                'results_count' => count($searchResults),
                'search_results' => $searchResults,
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true)
            ];

            return $this->createSuccessResponse($response);
        } catch (Exception $e) {
            Utils::logError('VectorManager test error: ' . $e->getMessage());
            return $this->createErrorResponse('vector_manager_test_error', $e->getMessage(), 500);
        }
    }

    /**
     * Test AIManager functionality
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function testAIManager(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $query = $request->get_param('query');
            $useRag = $request->get_param('use_rag');

            Utils::logDebug('Testing AIManager functionality', ['query' => $query, 'use_rag' => $useRag]);

            $main = \WooAiAssistant\Main::getInstance();
            $aiManager = $main->getComponent('kb_ai_manager');

            if (!$aiManager) {
                return $this->createErrorResponse('ai_manager_not_found', 'AIManager component not available', 500);
            }

            $startTime = microtime(true);

            // Test AI response generation
            $aiResponse = $aiManager->generateResponse($query, [
                'use_rag' => $useRag,
                'conversation_id' => 'test_' . uniqid(),
                'user_context' => ['test_mode' => true]
            ]);

            $executionTime = microtime(true) - $startTime;

            $response = [
                'test_type' => 'ai_manager',
                'query' => $query,
                'use_rag' => $useRag,
                'execution_time' => round($executionTime, 4),
                'ai_response' => $aiResponse,
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true)
            ];

            return $this->createSuccessResponse($response);
        } catch (Exception $e) {
            Utils::logError('AIManager test error: ' . $e->getMessage());
            return $this->createErrorResponse('ai_manager_test_error', $e->getMessage(), 500);
        }
    }

    /**
     * Test full KB pipeline
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function testFullPipeline(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $query = $request->get_param('query');

            Utils::logDebug('Testing full KB pipeline', ['query' => $query]);

            $main = \WooAiAssistant\Main::getInstance();
            $scanner = $main->getComponent('kb_scanner');
            $indexer = $main->getComponent('kb_indexer');
            $vectorManager = $main->getComponent('kb_vector_manager');
            $aiManager = $main->getComponent('kb_ai_manager');

            if (!$scanner || !$indexer || !$vectorManager || !$aiManager) {
                return $this->createErrorResponse('components_not_found', 'One or more KB components not available', 500);
            }

            $startTime = microtime(true);
            $pipelineResults = [];

            // Step 1: Scan some content
            $scanStart = microtime(true);
            $scanResults = $scanner->scanProducts(['limit' => 5]);
            $pipelineResults['scan'] = [
                'execution_time' => round(microtime(true) - $scanStart, 4),
                'results_count' => count($scanResults)
            ];

            // Step 2: Index a sample item
            if (!empty($scanResults)) {
                $indexStart = microtime(true);
                $sampleContent = array_slice($scanResults, 0, 1);
                $indexResults = $indexer->indexContent($sampleContent);
                $pipelineResults['index'] = [
                    'execution_time' => round(microtime(true) - $indexStart, 4),
                    'chunks_created' => $indexResults['chunks_created'] ?? 0
                ];
            }

            // Step 3: Perform vector search
            $vectorStart = microtime(true);
            $vectorResults = $vectorManager->searchSimilar($query, 3);
            $pipelineResults['vector_search'] = [
                'execution_time' => round(microtime(true) - $vectorStart, 4),
                'results_count' => count($vectorResults)
            ];

            // Step 4: Generate AI response
            $aiStart = microtime(true);
            $aiResponse = $aiManager->generateResponse($query, [
                'use_rag' => true,
                'conversation_id' => 'pipeline_test_' . uniqid()
            ]);
            $pipelineResults['ai_response'] = [
                'execution_time' => round(microtime(true) - $aiStart, 4),
                'response_generated' => !empty($aiResponse['response'])
            ];

            $totalExecutionTime = microtime(true) - $startTime;

            $response = [
                'test_type' => 'full_pipeline',
                'query' => $query,
                'total_execution_time' => round($totalExecutionTime, 4),
                'pipeline_results' => $pipelineResults,
                'final_ai_response' => $aiResponse,
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true)
            ];

            return $this->createSuccessResponse($response);
        } catch (Exception $e) {
            Utils::logError('Full pipeline test error: ' . $e->getMessage());
            return $this->createErrorResponse('full_pipeline_test_error', $e->getMessage(), 500);
        }
    }

    /**
     * Register license management routes
     *
     * @since 1.0.0
     * @return void
     */
    private function registerLicenseRoutes(): void
    {
        // License status endpoint
        \register_rest_route($this->namespace, '/admin/license/status', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'getLicenseStatus'],
            'permission_callback' => [$this, 'checkAdminPermissions']
        ]);

        // License validation endpoint
        \register_rest_route($this->namespace, '/admin/license/validate', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'validateLicense'],
            'permission_callback' => [$this, 'checkAdminPermissions'],
            'args' => [
                'force_validation' => [
                    'required' => false,
                    'type' => 'boolean',
                    'default' => false,
                    'description' => 'Force validation even if recently validated'
                ]
            ]
        ]);

        // Set license key endpoint
        \register_rest_route($this->namespace, '/admin/license/set-key', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'setLicenseKey'],
            'permission_callback' => [$this, 'checkAdminPermissions'],
            'args' => [
                'license_key' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => 'License key to set',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => [$this, 'validateLicenseKeyFormat']
                ]
            ]
        ]);

        // Clear license key endpoint
        \register_rest_route($this->namespace, '/admin/license/clear', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'clearLicense'],
            'permission_callback' => [$this, 'checkAdminPermissions']
        ]);

        // Usage statistics endpoint
        \register_rest_route($this->namespace, '/admin/license/usage', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'getUsageStatistics'],
            'permission_callback' => [$this, 'checkAdminPermissions']
        ]);

        // Available plans endpoint
        \register_rest_route($this->namespace, '/admin/license/plans', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'getAvailablePlans'],
            'permission_callback' => [$this, 'checkAdminPermissions']
        ]);

        // Feature check endpoint (can be used by frontend)
        \register_rest_route($this->namespace, '/license/features', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'getEnabledFeatures'],
            'permission_callback' => [$this, 'checkPublicPermissions']
        ]);

        Utils::logDebug('License management routes registered');
    }

    /**
     * Get license status
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function getLicenseStatus(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $licenseManager = $this->getLicenseManager();
            $status = $licenseManager->getLicenseStatus();

            return $this->createSuccessResponse($status);
        } catch (Exception $e) {
            Utils::logError('Error getting license status: ' . $e->getMessage());
            return $this->createErrorResponse('license_status_error', $e->getMessage(), 500);
        }
    }

    /**
     * Validate license
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function validateLicense(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $forceValidation = $request->get_param('force_validation') ?? false;

            $licenseManager = $this->getLicenseManager();
            $result = $licenseManager->validateLicense($forceValidation);

            if ($result['valid']) {
                return $this->createSuccessResponse($result);
            } else {
                return $this->createErrorResponse('license_validation_failed', $result['message'] ?? 'License validation failed', 400);
            }
        } catch (Exception $e) {
            Utils::logError('Error validating license: ' . $e->getMessage());
            return $this->createErrorResponse('license_validation_error', $e->getMessage(), 500);
        }
    }

    /**
     * Set license key
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function setLicenseKey(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $licenseKey = $request->get_param('license_key');

            $licenseManager = $this->getLicenseManager();
            $result = $licenseManager->setLicenseKey($licenseKey);

            if ($result['valid']) {
                return $this->createSuccessResponse([
                    'message' => 'License key set and validated successfully',
                    'license_data' => $result
                ]);
            } else {
                return $this->createErrorResponse('invalid_license_key', $result['message'] ?? 'Invalid license key', 400);
            }
        } catch (Exception $e) {
            Utils::logError('Error setting license key: ' . $e->getMessage());
            return $this->createErrorResponse('license_key_error', $e->getMessage(), 500);
        }
    }

    /**
     * Clear license
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function clearLicense(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $licenseManager = $this->getLicenseManager();
            $result = $licenseManager->clearLicenseKey();

            return $this->createSuccessResponse([
                'message' => 'License cleared successfully, reverted to Free plan',
                'success' => $result
            ]);
        } catch (Exception $e) {
            Utils::logError('Error clearing license: ' . $e->getMessage());
            return $this->createErrorResponse('license_clear_error', $e->getMessage(), 500);
        }
    }

    /**
     * Get usage statistics
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function getUsageStatistics(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $licenseManager = $this->getLicenseManager();
            $stats = $licenseManager->getUsageStatistics();

            return $this->createSuccessResponse($stats);
        } catch (Exception $e) {
            Utils::logError('Error getting usage statistics: ' . $e->getMessage());
            return $this->createErrorResponse('usage_stats_error', $e->getMessage(), 500);
        }
    }

    /**
     * Get available plans
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function getAvailablePlans(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $licenseManager = $this->getLicenseManager();
            $plans = $licenseManager->getAvailablePlans();

            $response = [
                'plans' => $plans,
                'upgrade_url' => $licenseManager->getUpgradeUrl(),
                'can_upgrade' => $licenseManager->canUpgrade()
            ];

            return $this->createSuccessResponse($response);
        } catch (Exception $e) {
            Utils::logError('Error getting available plans: ' . $e->getMessage());
            return $this->createErrorResponse('plans_error', $e->getMessage(), 500);
        }
    }

    /**
     * Get enabled features for current plan
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function getEnabledFeatures(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $licenseManager = $this->getLicenseManager();
            $planConfig = $licenseManager->getPlanConfiguration();

            $features = [
                'basic_chat' => $licenseManager->isFeatureEnabled(\WooAiAssistant\Api\LicenseManager::FEATURE_BASIC_CHAT),
                'proactive_triggers' => $licenseManager->isFeatureEnabled(\WooAiAssistant\Api\LicenseManager::FEATURE_PROACTIVE_TRIGGERS),
                'custom_messages' => $licenseManager->isFeatureEnabled(\WooAiAssistant\Api\LicenseManager::FEATURE_CUSTOM_MESSAGES),
                'add_to_cart' => $licenseManager->isFeatureEnabled(\WooAiAssistant\Api\LicenseManager::FEATURE_ADD_TO_CART),
                'auto_coupon' => $licenseManager->isFeatureEnabled(\WooAiAssistant\Api\LicenseManager::FEATURE_AUTO_COUPON),
                'upsell_crosssell' => $licenseManager->isFeatureEnabled(\WooAiAssistant\Api\LicenseManager::FEATURE_UPSELL_CROSSSELL),
                'white_label' => $licenseManager->isFeatureEnabled(\WooAiAssistant\Api\LicenseManager::FEATURE_WHITE_LABEL),
                'advanced_ai' => $licenseManager->isFeatureEnabled(\WooAiAssistant\Api\LicenseManager::FEATURE_ADVANCED_AI),
                'chat_recovery' => $licenseManager->isFeatureEnabled(\WooAiAssistant\Api\LicenseManager::FEATURE_CHAT_RECOVERY)
            ];

            $response = [
                'features' => $features,
                'plan' => $planConfig['name'] ?? 'Unknown',
                'ai_model' => $licenseManager->getCurrentAiModel(),
                'branding' => $licenseManager->getBrandingConfig(),
                'usage_limits' => [
                    'conversations' => $planConfig['conversations_per_month'] ?? 0,
                    'items_indexable' => $planConfig['items_indexable'] ?? 0
                ]
            ];

            return $this->createSuccessResponse($response);
        } catch (Exception $e) {
            Utils::logError('Error getting enabled features: ' . $e->getMessage());
            return $this->createErrorResponse('features_error', $e->getMessage(), 500);
        }
    }

    /**
     * Get license manager instance
     *
     * @since 1.0.0
     * @return \WooAiAssistant\Api\LicenseManager
     * @throws Exception If license manager not available
     */
    private function getLicenseManager(): \WooAiAssistant\Api\LicenseManager
    {
        // Get from main plugin instance
        $main = \WooAiAssistant\Main::getInstance();
        $licenseManager = $main->getComponent('license_manager');

        if (!$licenseManager) {
            throw new Exception('License manager not available');
        }

        return $licenseManager;
    }

    /**
     * Validate license key format
     *
     * @since 1.0.0
     * @param string $value License key value
     * @param WP_REST_Request $request Request object
     * @param string $param Parameter name
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public function validateLicenseKeyFormat($value, $request, $param)
    {
        if (empty($value)) {
            return new WP_Error('invalid_license_key', 'License key cannot be empty');
        }

        // Basic format validation (adjust based on actual license key format)
        if (strlen($value) < 10 || strlen($value) > 100) {
            return new WP_Error('invalid_license_key_format', 'License key format is invalid');
        }

        return true;
    }
}
