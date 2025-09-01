<?php

/**
 * Rating Endpoint Class
 *
 * Handles REST API endpoints for conversation rating and feedback functionality,
 * allowing users to rate AI responses and provide detailed feedback.
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
use WooAiAssistant\Chatbot\ConversationHandler;
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
 * Class RatingEndpoint
 *
 * Manages rating and feedback REST API endpoints for conversation evaluation.
 * Provides comprehensive rating system with analytics and detailed feedback support.
 *
 * @since 1.0.0
 */
class RatingEndpoint
{
    /**
     * Conversation handler instance
     *
     * @var ConversationHandler
     */
    private ConversationHandler $conversationHandler;

    /**
     * Logger instance
     *
     * @var Logger
     */
    private Logger $logger;

    /**
     * WordPress database instance
     *
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Valid rating values (1-5 stars)
     *
     * @var array
     */
    private array $validRatingValues = [1, 2, 3, 4, 5];

    /**
     * Maximum feedback text length
     *
     * @var int
     */
    private int $maxFeedbackLength = 2000;

    /**
     * Initialize rating endpoint
     *
     * @return void
     */
    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->conversationHandler = ConversationHandler::getInstance();
        $this->logger = Logger::getInstance();
    }
    /**
     * Register rating routes
     *
     * Registers all rating-related REST API endpoints including rating submission,
     * feedback collection, rating retrieval, and analytics.
     *
     * @param string $namespace API namespace
     * @return void
     */
    public function registerRoutes(string $namespace): void
    {
        // Submit rating endpoint
        register_rest_route(
            $namespace,
            '/rating/submit',
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'submitRating'],
                'permission_callback' => [$this, 'checkRatingPermission'],
                'args' => [
                    'conversation_id' => [
                        'required' => true,
                        'type' => 'integer',
                        'description' => 'Conversation ID to rate',
                        'validate_callback' => [$this, 'validateConversationId'],
                        'sanitize_callback' => 'absint'
                    ],
                    'rating' => [
                        'required' => true,
                        'type' => 'integer',
                        'description' => 'Rating value (1-5 stars)',
                        'validate_callback' => [$this, 'validateRating'],
                        'sanitize_callback' => 'absint'
                    ],
                    'feedback' => [
                        'type' => 'string',
                        'description' => 'Optional feedback text',
                        'validate_callback' => [$this, 'validateFeedback'],
                        'sanitize_callback' => 'sanitize_textarea_field'
                    ]
                ]
            ]
        );

        // Submit detailed feedback endpoint (separate from rating)
        register_rest_route(
            $namespace,
            '/rating/feedback',
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'submitFeedback'],
                'permission_callback' => [$this, 'checkRatingPermission'],
                'args' => [
                    'conversation_id' => [
                        'required' => true,
                        'type' => 'integer',
                        'description' => 'Conversation ID for feedback',
                        'validate_callback' => [$this, 'validateConversationId'],
                        'sanitize_callback' => 'absint'
                    ],
                    'feedback_text' => [
                        'required' => true,
                        'type' => 'string',
                        'description' => 'Detailed feedback text',
                        'validate_callback' => [$this, 'validateFeedback'],
                        'sanitize_callback' => 'sanitize_textarea_field'
                    ],
                    'feedback_type' => [
                        'type' => 'string',
                        'description' => 'Type of feedback (general, bug, suggestion, complaint)',
                        'default' => 'general',
                        'enum' => ['general', 'bug', 'suggestion', 'complaint', 'compliment'],
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'user_email' => [
                        'type' => 'string',
                        'description' => 'Optional email for follow-up',
                        'validate_callback' => [$this, 'validateEmail'],
                        'sanitize_callback' => 'sanitize_email'
                    ]
                ]
            ]
        );

        // Get conversation rating endpoint
        register_rest_route(
            $namespace,
            '/rating/conversation/(?P<id>\d+)',
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getConversationRating'],
                'permission_callback' => [$this, 'checkRatingPermission'],
                'args' => [
                    'id' => [
                        'required' => true,
                        'type' => 'integer',
                        'description' => 'Conversation ID',
                        'sanitize_callback' => 'absint'
                    ]
                ]
            ]
        );

        // Update rating endpoint (for rating changes)
        register_rest_route(
            $namespace,
            '/rating/update',
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateRating'],
                'permission_callback' => [$this, 'checkRatingPermission'],
                'args' => [
                    'conversation_id' => [
                        'required' => true,
                        'type' => 'integer',
                        'description' => 'Conversation ID to update',
                        'validate_callback' => [$this, 'validateConversationId'],
                        'sanitize_callback' => 'absint'
                    ],
                    'rating' => [
                        'required' => true,
                        'type' => 'integer',
                        'description' => 'New rating value (1-5 stars)',
                        'validate_callback' => [$this, 'validateRating'],
                        'sanitize_callback' => 'absint'
                    ]
                ]
            ]
        );

        // Get rating analytics endpoint (admin only)
        register_rest_route(
            $namespace,
            '/rating/analytics',
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getAnalytics'],
                'permission_callback' => [$this, 'checkAnalyticsPermission'],
                'args' => [
                    'period' => [
                        'type' => 'string',
                        'description' => 'Time period for analytics (day, week, month, year)',
                        'default' => 'month',
                        'enum' => ['day', 'week', 'month', 'year', 'all'],
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'rating_filter' => [
                        'type' => 'integer',
                        'description' => 'Filter by specific rating (1-5)',
                        'validate_callback' => [$this, 'validateRating'],
                        'sanitize_callback' => 'absint'
                    ],
                    'include_feedback' => [
                        'type' => 'boolean',
                        'description' => 'Include feedback text in results',
                        'default' => false
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of results',
                        'default' => 100,
                        'sanitize_callback' => 'absint'
                    ]
                ]
            ]
        );

        $this->logger->debug('Rating endpoints registered', [
            'endpoints' => [
                'POST /rating/submit',
                'POST /rating/feedback',
                'GET /rating/conversation/{id}',
                'PUT /rating/update',
                'GET /rating/analytics'
            ]
        ]);
    }

    /**
     * Submit conversation rating
     *
     * Processes rating submissions with optional feedback, stores in database,
     * and tracks analytics for performance monitoring.
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public function submitRating(WP_REST_Request $request)
    {
        $startTime = microtime(true);

        try {
            // Extract and validate parameters
            $conversationId = $request->get_param('conversation_id');
            $rating = $request->get_param('rating');
            $feedback = $request->get_param('feedback');
            $userId = get_current_user_id() ?: null;
            $userIp = Utils::getUserIp();

            $this->logger->info('Processing rating submission', [
                'conversation_id' => $conversationId,
                'rating' => $rating,
                'user_id' => $userId,
                'has_feedback' => !empty($feedback)
            ]);

            // Get conversation and verify access
            $conversation = $this->conversationHandler->getConversation($conversationId);
            if (!$conversation) {
                return new WP_Error(
                    'conversation_not_found',
                    'Conversation not found.',
                    ['status' => 404]
                );
            }

            // Check if user can rate this conversation
            if (!$this->canRateConversation($conversation, $userId)) {
                return new WP_Error(
                    'rating_access_denied',
                    'You do not have permission to rate this conversation.',
                    ['status' => 403]
                );
            }

            // Check if conversation already has a rating
            $existingRating = $this->getExistingRating($conversationId);
            $isUpdate = $existingRating !== null;

            if ($isUpdate) {
                $this->logger->info('Updating existing rating', [
                    'conversation_id' => $conversationId,
                    'old_rating' => $existingRating,
                    'new_rating' => $rating
                ]);
            }

            // Update conversation rating
            $updateResult = $this->wpdb->update(
                $this->wpdb->prefix . 'woo_ai_conversations',
                [
                    'rating' => $rating,
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $conversationId],
                ['%d', '%s'],
                ['%d']
            );

            if ($updateResult === false) {
                throw new Exception('Failed to update conversation rating: ' . $this->wpdb->last_error);
            }

            // Store feedback as a special message if provided
            $feedbackMessageId = null;
            if (!empty($feedback)) {
                $feedbackMessageId = $this->storeFeedbackMessage($conversationId, $feedback, $rating);
            }

            // Track rating analytics
            $this->trackRatingAnalytics($conversationId, $rating, $isUpdate, [
                'user_id' => $userId,
                'session_id' => $conversation['session_id'],
                'previous_rating' => $existingRating,
                'has_feedback' => !empty($feedback),
                'feedback_length' => $feedback ? strlen($feedback) : 0,
                'processing_time' => round((microtime(true) - $startTime) * 1000)
            ]);

            $processingTime = microtime(true) - $startTime;

            // Prepare response
            $response = [
                'success' => true,
                'message' => $isUpdate ? 'Rating updated successfully' : 'Rating submitted successfully',
                'data' => [
                    'conversation_id' => $conversationId,
                    'rating' => $rating,
                    'previous_rating' => $existingRating,
                    'is_update' => $isUpdate,
                    'feedback_stored' => !empty($feedback),
                    'feedback_message_id' => $feedbackMessageId,
                    'timestamp' => current_time('mysql')
                ],
                'metadata' => [
                    'processing_time' => round($processingTime, 3),
                    'can_update' => true,
                    'rating_scale' => '1-5 stars'
                ]
            ];

            $this->logger->info('Rating submitted successfully', [
                'conversation_id' => $conversationId,
                'rating' => $rating,
                'is_update' => $isUpdate,
                'processing_time' => round($processingTime, 3)
            ]);

            // Trigger action hooks for extensions
            do_action('woo_ai_assistant_rating_submitted', [
                'conversation_id' => $conversationId,
                'rating' => $rating,
                'previous_rating' => $existingRating,
                'feedback' => $feedback,
                'user_id' => $userId,
                'is_update' => $isUpdate
            ]);

            return new WP_REST_Response($response, $isUpdate ? 200 : 201);
        } catch (Exception $e) {
            $this->logger->error('Rating submission failed', [
                'conversation_id' => $conversationId ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'processing_time' => microtime(true) - $startTime
            ]);

            return new WP_Error(
                'rating_submission_error',
                'An error occurred while submitting your rating. Please try again.',
                ['status' => 500]
            );
        }
    }

    /**
     * Submit detailed feedback without rating
     *
     * Handles detailed feedback submissions that are separate from star ratings.
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public function submitFeedback(WP_REST_Request $request)
    {
        try {
            $conversationId = $request->get_param('conversation_id');
            $feedbackText = $request->get_param('feedback_text');
            $feedbackType = $request->get_param('feedback_type') ?: 'general';
            $userEmail = $request->get_param('user_email');
            $userId = get_current_user_id() ?: null;

            $this->logger->info('Processing feedback submission', [
                'conversation_id' => $conversationId,
                'feedback_type' => $feedbackType,
                'feedback_length' => strlen($feedbackText),
                'user_id' => $userId,
                'has_email' => !empty($userEmail)
            ]);

            // Get conversation and verify access
            $conversation = $this->conversationHandler->getConversation($conversationId);
            if (!$conversation) {
                return new WP_Error(
                    'conversation_not_found',
                    'Conversation not found.',
                    ['status' => 404]
                );
            }

            if (!$this->canRateConversation($conversation, $userId)) {
                return new WP_Error(
                    'feedback_access_denied',
                    'You do not have permission to provide feedback for this conversation.',
                    ['status' => 403]
                );
            }

            // Store feedback as a special message
            $messageMetadata = [
                'feedback_type' => $feedbackType,
                'user_email' => $userEmail,
                'user_ip' => Utils::getUserIp(),
                'user_agent' => Utils::getUserAgent(),
                'is_standalone_feedback' => true,
                'timestamp' => current_time('mysql')
            ];

            $messageId = $this->conversationHandler->addMessage(
                $conversationId,
                ConversationHandler::ROLE_USER,
                $feedbackText,
                array_merge($messageMetadata, ['type' => 'feedback'])
            );

            if (!$messageId) {
                throw new Exception('Failed to store feedback message');
            }

            // Track feedback analytics
            $this->trackFeedbackAnalytics($conversationId, $feedbackType, strlen($feedbackText), [
                'user_id' => $userId,
                'session_id' => $conversation['session_id'],
                'has_email' => !empty($userEmail),
                'feedback_type' => $feedbackType
            ]);

            $response = [
                'success' => true,
                'message' => 'Feedback submitted successfully',
                'data' => [
                    'conversation_id' => $conversationId,
                    'feedback_message_id' => $messageId,
                    'feedback_type' => $feedbackType,
                    'timestamp' => current_time('mysql')
                ],
                'metadata' => [
                    'will_followup' => !empty($userEmail),
                    'feedback_id' => $messageId
                ]
            ];

            $this->logger->info('Feedback submitted successfully', [
                'conversation_id' => $conversationId,
                'message_id' => $messageId,
                'feedback_type' => $feedbackType
            ]);

            // Trigger action hooks
            do_action('woo_ai_assistant_feedback_submitted', [
                'conversation_id' => $conversationId,
                'feedback_text' => $feedbackText,
                'feedback_type' => $feedbackType,
                'user_email' => $userEmail,
                'user_id' => $userId,
                'message_id' => $messageId
            ]);

            return new WP_REST_Response($response, 201);
        } catch (Exception $e) {
            $this->logger->error('Feedback submission failed', [
                'conversation_id' => $conversationId ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return new WP_Error(
                'feedback_submission_error',
                'An error occurred while submitting your feedback. Please try again.',
                ['status' => 500]
            );
        }
    }

    /**
     * Get conversation rating
     *
     * Retrieves rating information for a specific conversation including
     * rating value and associated feedback.
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public function getConversationRating(WP_REST_Request $request)
    {
        try {
            $conversationId = (int) $request->get_param('id');
            $userId = get_current_user_id() ?: null;

            $this->logger->debug('Retrieving conversation rating', [
                'conversation_id' => $conversationId,
                'user_id' => $userId
            ]);

            // Get conversation and verify access
            $conversation = $this->conversationHandler->getConversation($conversationId);
            if (!$conversation) {
                return new WP_Error(
                    'conversation_not_found',
                    'Conversation not found.',
                    ['status' => 404]
                );
            }

            if (!$this->canRateConversation($conversation, $userId)) {
                return new WP_Error(
                    'rating_access_denied',
                    'You do not have permission to view this conversation\'s rating.',
                    ['status' => 403]
                );
            }

            // Get rating and feedback information
            $ratingData = $this->getRatingData($conversationId);

            $response = [
                'success' => true,
                'data' => array_merge([
                    'conversation_id' => $conversationId,
                    'can_rate' => $this->canRateConversation($conversation, $userId),
                    'rating_scale' => '1-5 stars'
                ], $ratingData)
            ];

            $this->logger->info('Conversation rating retrieved', [
                'conversation_id' => $conversationId,
                'has_rating' => !is_null($ratingData['rating'])
            ]);

            return new WP_REST_Response($response, 200);
        } catch (Exception $e) {
            $this->logger->error('Failed to retrieve conversation rating', [
                'conversation_id' => $conversationId ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return new WP_Error(
                'rating_retrieval_error',
                'Failed to retrieve conversation rating.',
                ['status' => 500]
            );
        }
    }

    /**
     * Update existing rating
     *
     * Allows users to update previously submitted ratings.
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public function updateRating(WP_REST_Request $request)
    {
        // For now, redirect to submitRating which handles both new and update cases
        return $this->submitRating($request);
    }

    /**
     * Get rating analytics
     *
     * Provides comprehensive rating analytics for administrators including
     * rating distributions, trends, and feedback analysis.
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public function getAnalytics(WP_REST_Request $request)
    {
        try {
            $period = $request->get_param('period') ?: 'month';
            $ratingFilter = $request->get_param('rating_filter');
            $includeFeedback = $request->get_param('include_feedback') ?: false;
            $limit = $request->get_param('limit') ?: 100;

            $this->logger->info('Generating rating analytics', [
                'period' => $period,
                'rating_filter' => $ratingFilter,
                'include_feedback' => $includeFeedback,
                'limit' => $limit
            ]);

            // Build date filter based on period
            $dateFilter = $this->buildDateFilter($period);

            // Get rating distribution
            $distribution = $this->getRatingDistribution($dateFilter, $ratingFilter);

            // Get rating trends
            $trends = $this->getRatingTrends($period, $dateFilter);

            // Get feedback summary
            $feedbackSummary = $this->getFeedbackSummary($dateFilter, $includeFeedback, $limit);

            // Calculate performance metrics
            $metrics = $this->calculateRatingMetrics($distribution);

            // Get top feedback by type
            $topFeedback = $this->getTopFeedbackByType($dateFilter, $limit);

            $analytics = [
                'period' => $period,
                'date_range' => $dateFilter,
                'summary' => [
                    'total_ratings' => array_sum(array_column($distribution, 'count')),
                    'average_rating' => $metrics['average_rating'],
                    'rating_distribution' => $distribution,
                    'satisfaction_rate' => $metrics['satisfaction_rate'],
                    'total_feedback_messages' => $feedbackSummary['total_count']
                ],
                'trends' => $trends,
                'feedback' => $feedbackSummary,
                'top_feedback_by_type' => $topFeedback,
                'metrics' => $metrics,
                'generated_at' => current_time('mysql')
            ];

            $response = [
                'success' => true,
                'data' => $analytics
            ];

            $this->logger->info('Rating analytics generated successfully', [
                'period' => $period,
                'total_ratings' => $analytics['summary']['total_ratings'],
                'average_rating' => $analytics['summary']['average_rating']
            ]);

            return new WP_REST_Response($response, 200);
        } catch (Exception $e) {
            $this->logger->error('Failed to generate rating analytics', [
                'period' => $period ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return new WP_Error(
                'analytics_error',
                'Failed to generate rating analytics.',
                ['status' => 500]
            );
        }
    }

    /**
     * Check rating permission
     *
     * @param WP_REST_Request $request Request object
     * @return bool|WP_Error True if permission granted
     */
    public function checkRatingPermission(WP_REST_Request $request)
    {
        // Allow both logged in and guest users to rate conversations
        // Access control is handled per conversation in individual methods
        return true;
    }

    /**
     * Check analytics permission (admin only)
     *
     * @param WP_REST_Request $request Request object
     * @return bool|WP_Error True if permission granted
     */
    public function checkAnalyticsPermission(WP_REST_Request $request)
    {
        // Only allow administrators to access analytics
        if (!current_user_can('manage_woocommerce')) {
            return new WP_Error(
                'analytics_access_denied',
                'You do not have permission to access rating analytics.',
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Validate conversation ID
     *
     * @param int $conversationId Conversation ID
     * @param WP_REST_Request $request Request object
     * @param string $param Parameter name
     * @return bool|WP_Error True if valid
     */
    public function validateConversationId($conversationId, $request, $param)
    {
        if ($conversationId <= 0) {
            return new WP_Error(
                'invalid_conversation_id',
                'Conversation ID must be a positive integer.',
                ['status' => 400]
            );
        }

        return true;
    }

    /**
     * Validate rating value
     *
     * @param int $rating Rating value
     * @param WP_REST_Request $request Request object
     * @param string $param Parameter name
     * @return bool|WP_Error True if valid
     */
    public function validateRating($rating, $request, $param)
    {
        if (!in_array($rating, $this->validRatingValues, true)) {
            return new WP_Error(
                'invalid_rating',
                'Rating must be between 1 and 5 stars.',
                ['status' => 400]
            );
        }

        return true;
    }

    /**
     * Validate feedback text
     *
     * @param string $feedback Feedback text
     * @param WP_REST_Request $request Request object
     * @param string $param Parameter name
     * @return bool|WP_Error True if valid
     */
    public function validateFeedback($feedback, $request, $param)
    {
        if (empty($feedback)) {
            return true; // Optional field
        }

        if (strlen($feedback) > $this->maxFeedbackLength) {
            return new WP_Error(
                'feedback_too_long',
                sprintf('Feedback text cannot exceed %d characters.', $this->maxFeedbackLength),
                ['status' => 400]
            );
        }

        return true;
    }

    /**
     * Validate email address
     *
     * @param string $email Email address
     * @param WP_REST_Request $request Request object
     * @param string $param Parameter name
     * @return bool|WP_Error True if valid
     */
    public function validateEmail($email, $request, $param)
    {
        if (empty($email)) {
            return true; // Optional field
        }

        if (!is_email($email)) {
            return new WP_Error(
                'invalid_email',
                'Please provide a valid email address.',
                ['status' => 400]
            );
        }

        return true;
    }

    /**
     * Check if user can rate a conversation
     *
     * @param array $conversation Conversation data
     * @param int|null $userId User ID
     * @return bool True if user can rate
     */
    private function canRateConversation(array $conversation, ?int $userId): bool
    {
        // Allow rating if:
        // 1. User owns the conversation
        // 2. Same session (for guest users)
        // 3. User is admin (can rate any conversation)
        $sessionId = $_COOKIE['woo_ai_session'] ?? '';

        return (
            ($conversation['user_id'] && $conversation['user_id'] == $userId) ||
            ($conversation['session_id'] === $sessionId) ||
            current_user_can('manage_woocommerce')
        );
    }

    /**
     * Get existing rating for a conversation
     *
     * @param int $conversationId Conversation ID
     * @return int|null Rating value or null if not rated
     */
    private function getExistingRating(int $conversationId): ?int
    {
        $rating = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT rating FROM {$this->wpdb->prefix}woo_ai_conversations WHERE id = %d",
            $conversationId
        ));

        return $rating ? (int) $rating : null;
    }

    /**
     * Store feedback as a special message
     *
     * @param int $conversationId Conversation ID
     * @param string $feedback Feedback text
     * @param int $rating Associated rating
     * @return int|false Message ID or false on failure
     */
    private function storeFeedbackMessage(int $conversationId, string $feedback, int $rating)
    {
        $messageMetadata = [
            'type' => 'rating_feedback',
            'associated_rating' => $rating,
            'user_ip' => Utils::getUserIp(),
            'user_agent' => Utils::getUserAgent(),
            'timestamp' => current_time('mysql')
        ];

        return $this->conversationHandler->addMessage(
            $conversationId,
            ConversationHandler::ROLE_USER,
            $feedback,
            $messageMetadata
        );
    }

    /**
     * Track rating analytics
     *
     * @param int $conversationId Conversation ID
     * @param int $rating Rating value
     * @param bool $isUpdate Whether this is an update
     * @param array $additionalData Additional context data
     * @return void
     */
    private function trackRatingAnalytics(int $conversationId, int $rating, bool $isUpdate, array $additionalData = []): void
    {
        $analyticsData = array_merge([
            'rating_value' => $rating,
            'is_update' => $isUpdate,
            'conversation_id' => $conversationId
        ], $additionalData);

        // Track rating submission
        $this->wpdb->insert(
            $this->wpdb->prefix . 'woo_ai_analytics',
            [
                'metric_type' => $isUpdate ? 'rating_updated' : 'rating_submitted',
                'metric_value' => $rating,
                'context' => wp_json_encode($analyticsData),
                'created_at' => current_time('mysql'),
                'user_id' => $additionalData['user_id'],
                'conversation_id' => $conversationId,
                'session_id' => $additionalData['session_id'],
                'source' => 'rating_system'
            ],
            ['%s', '%f', '%s', '%s', '%d', '%d', '%s', '%s']
        );

        // Track rating distribution
        $this->wpdb->insert(
            $this->wpdb->prefix . 'woo_ai_analytics',
            [
                'metric_type' => "rating_distribution_{$rating}_star",
                'metric_value' => 1,
                'context' => wp_json_encode(['rating' => $rating, 'conversation_id' => $conversationId]),
                'created_at' => current_time('mysql'),
                'user_id' => $additionalData['user_id'],
                'conversation_id' => $conversationId,
                'session_id' => $additionalData['session_id'],
                'source' => 'rating_system'
            ],
            ['%s', '%f', '%s', '%s', '%d', '%d', '%s', '%s']
        );
    }

    /**
     * Track feedback analytics
     *
     * @param int $conversationId Conversation ID
     * @param string $feedbackType Type of feedback
     * @param int $feedbackLength Length of feedback text
     * @param array $additionalData Additional context data
     * @return void
     */
    private function trackFeedbackAnalytics(int $conversationId, string $feedbackType, int $feedbackLength, array $additionalData = []): void
    {
        $analyticsData = array_merge([
            'feedback_type' => $feedbackType,
            'feedback_length' => $feedbackLength,
            'conversation_id' => $conversationId
        ], $additionalData);

        $this->wpdb->insert(
            $this->wpdb->prefix . 'woo_ai_analytics',
            [
                'metric_type' => 'feedback_submitted',
                'metric_value' => $feedbackLength,
                'context' => wp_json_encode($analyticsData),
                'created_at' => current_time('mysql'),
                'user_id' => $additionalData['user_id'],
                'conversation_id' => $conversationId,
                'session_id' => $additionalData['session_id'],
                'source' => 'rating_system'
            ],
            ['%s', '%f', '%s', '%s', '%d', '%d', '%s', '%s']
        );
    }

    /**
     * Get complete rating data for a conversation
     *
     * @param int $conversationId Conversation ID
     * @return array Rating data including feedback
     */
    private function getRatingData(int $conversationId): array
    {
        // Get basic rating
        $rating = $this->getExistingRating($conversationId);

        // Get associated feedback messages
        $feedbackMessages = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT content, metadata, created_at 
             FROM {$this->wpdb->prefix}woo_ai_messages 
             WHERE conversation_id = %d 
             AND role = 'user' 
             AND JSON_EXTRACT(metadata, '$.type') IN ('rating_feedback', 'feedback')
             ORDER BY created_at DESC",
            $conversationId
        ), ARRAY_A);

        $feedback = [];
        foreach ($feedbackMessages as $message) {
            $metadata = json_decode($message['metadata'] ?? '{}', true);
            $feedback[] = [
                'text' => $message['content'],
                'type' => $metadata['type'] ?? 'general',
                'feedback_type' => $metadata['feedback_type'] ?? 'general',
                'created_at' => $message['created_at'],
                'associated_rating' => $metadata['associated_rating'] ?? null
            ];
        }

        return [
            'rating' => $rating,
            'has_rating' => !is_null($rating),
            'feedback' => $feedback,
            'feedback_count' => count($feedback),
            'last_rated_at' => $rating ? current_time('mysql') : null
        ];
    }

    /**
     * Build date filter SQL based on period
     *
     * @param string $period Time period
     * @return array Date filter information
     */
    private function buildDateFilter(string $period): array
    {
        $now = current_time('mysql');

        switch ($period) {
            case 'day':
                $startDate = date('Y-m-d 00:00:00', strtotime('-1 day', strtotime($now)));
                break;
            case 'week':
                $startDate = date('Y-m-d 00:00:00', strtotime('-1 week', strtotime($now)));
                break;
            case 'month':
                $startDate = date('Y-m-d 00:00:00', strtotime('-1 month', strtotime($now)));
                break;
            case 'year':
                $startDate = date('Y-m-d 00:00:00', strtotime('-1 year', strtotime($now)));
                break;
            case 'all':
            default:
                $startDate = '1970-01-01 00:00:00';
                break;
        }

        return [
            'start_date' => $startDate,
            'end_date' => $now,
            'period' => $period
        ];
    }

    /**
     * Get rating distribution statistics
     *
     * @param array $dateFilter Date filter
     * @param int|null $ratingFilter Specific rating filter
     * @return array Rating distribution data
     */
    private function getRatingDistribution(array $dateFilter, ?int $ratingFilter = null): array
    {
        $whereClause = "WHERE c.rating IS NOT NULL AND c.updated_at >= %s AND c.updated_at <= %s";
        $params = [$dateFilter['start_date'], $dateFilter['end_date']];

        if ($ratingFilter) {
            $whereClause .= " AND c.rating = %d";
            $params[] = $ratingFilter;
        }

        $sql = "
            SELECT 
                c.rating,
                COUNT(*) as count,
                ROUND(COUNT(*) * 100.0 / SUM(COUNT(*)) OVER(), 2) as percentage
            FROM {$this->wpdb->prefix}woo_ai_conversations c
            {$whereClause}
            GROUP BY c.rating
            ORDER BY c.rating ASC
        ";

        $results = $this->wpdb->get_results($this->wpdb->prepare($sql, $params), ARRAY_A);

        return array_map(function ($row) {
            return [
                'rating' => (int) $row['rating'],
                'count' => (int) $row['count'],
                'percentage' => (float) $row['percentage']
            ];
        }, $results);
    }

    /**
     * Get rating trends over time
     *
     * @param string $period Time period
     * @param array $dateFilter Date filter
     * @return array Trend data
     */
    private function getRatingTrends(string $period, array $dateFilter): array
    {
        $groupBy = match ($period) {
            'day' => 'DATE(c.updated_at)',
            'week' => 'YEARWEEK(c.updated_at, 1)',
            'month' => 'DATE_FORMAT(c.updated_at, "%Y-%m")',
            'year' => 'YEAR(c.updated_at)',
            default => 'DATE(c.updated_at)'
        };

        $sql = "
            SELECT 
                {$groupBy} as period,
                AVG(c.rating) as average_rating,
                COUNT(*) as total_ratings,
                MIN(c.updated_at) as period_start,
                MAX(c.updated_at) as period_end
            FROM {$this->wpdb->prefix}woo_ai_conversations c
            WHERE c.rating IS NOT NULL 
            AND c.updated_at >= %s 
            AND c.updated_at <= %s
            GROUP BY {$groupBy}
            ORDER BY period ASC
        ";

        $results = $this->wpdb->get_results($this->wpdb->prepare(
            $sql,
            $dateFilter['start_date'],
            $dateFilter['end_date']
        ), ARRAY_A);

        return array_map(function ($row) {
            return [
                'period' => $row['period'],
                'average_rating' => round((float) $row['average_rating'], 2),
                'total_ratings' => (int) $row['total_ratings'],
                'period_start' => $row['period_start'],
                'period_end' => $row['period_end']
            ];
        }, $results);
    }

    /**
     * Get feedback summary statistics
     *
     * @param array $dateFilter Date filter
     * @param bool $includeFeedback Whether to include feedback text
     * @param int $limit Maximum results
     * @return array Feedback summary data
     */
    private function getFeedbackSummary(array $dateFilter, bool $includeFeedback, int $limit): array
    {
        // Get feedback count by type
        $typeCounts = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT 
                JSON_UNQUOTE(JSON_EXTRACT(m.metadata, '$.feedback_type')) as feedback_type,
                COUNT(*) as count
             FROM {$this->wpdb->prefix}woo_ai_messages m
             WHERE JSON_EXTRACT(m.metadata, '$.type') IN ('rating_feedback', 'feedback')
             AND m.created_at >= %s 
             AND m.created_at <= %s
             GROUP BY JSON_UNQUOTE(JSON_EXTRACT(m.metadata, '$.feedback_type'))
             ORDER BY count DESC",
            $dateFilter['start_date'],
            $dateFilter['end_date']
        ), ARRAY_A);

        $summary = [
            'total_count' => array_sum(array_column($typeCounts, 'count')),
            'by_type' => $typeCounts
        ];

        // Include recent feedback if requested
        if ($includeFeedback) {
            $recentFeedback = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT 
                    m.conversation_id,
                    m.content,
                    m.created_at,
                    JSON_UNQUOTE(JSON_EXTRACT(m.metadata, '$.feedback_type')) as feedback_type,
                    JSON_UNQUOTE(JSON_EXTRACT(m.metadata, '$.associated_rating')) as associated_rating,
                    c.rating as conversation_rating
                 FROM {$this->wpdb->prefix}woo_ai_messages m
                 LEFT JOIN {$this->wpdb->prefix}woo_ai_conversations c ON m.conversation_id = c.id
                 WHERE JSON_EXTRACT(m.metadata, '$.type') IN ('rating_feedback', 'feedback')
                 AND m.created_at >= %s 
                 AND m.created_at <= %s
                 ORDER BY m.created_at DESC
                 LIMIT %d",
                $dateFilter['start_date'],
                $dateFilter['end_date'],
                $limit
            ), ARRAY_A);

            $summary['recent_feedback'] = array_map(function ($row) {
                return [
                    'conversation_id' => (int) $row['conversation_id'],
                    'content' => $row['content'],
                    'feedback_type' => $row['feedback_type'] ?: 'general',
                    'associated_rating' => $row['associated_rating'] ? (int) $row['associated_rating'] : null,
                    'conversation_rating' => $row['conversation_rating'] ? (int) $row['conversation_rating'] : null,
                    'created_at' => $row['created_at']
                ];
            }, $recentFeedback);
        }

        return $summary;
    }

    /**
     * Calculate rating metrics
     *
     * @param array $distribution Rating distribution data
     * @return array Calculated metrics
     */
    private function calculateRatingMetrics(array $distribution): array
    {
        if (empty($distribution)) {
            return [
                'average_rating' => 0,
                'satisfaction_rate' => 0,
                'total_responses' => 0
            ];
        }

        $totalRatings = array_sum(array_column($distribution, 'count'));
        $weightedSum = array_reduce($distribution, function ($sum, $item) {
            return $sum + ($item['rating'] * $item['count']);
        }, 0);

        $averageRating = $totalRatings > 0 ? $weightedSum / $totalRatings : 0;

        // Calculate satisfaction rate (4-5 stars considered satisfied)
        $satisfiedCount = array_reduce($distribution, function ($sum, $item) {
            return $item['rating'] >= 4 ? $sum + $item['count'] : $sum;
        }, 0);

        $satisfactionRate = $totalRatings > 0 ? ($satisfiedCount / $totalRatings) * 100 : 0;

        return [
            'average_rating' => round($averageRating, 2),
            'satisfaction_rate' => round($satisfactionRate, 2),
            'total_responses' => $totalRatings,
            'satisfied_responses' => $satisfiedCount
        ];
    }

    /**
     * Get top feedback by type
     *
     * @param array $dateFilter Date filter
     * @param int $limit Maximum results per type
     * @return array Top feedback by type
     */
    private function getTopFeedbackByType(array $dateFilter, int $limit): array
    {
        $feedbackTypes = ['general', 'bug', 'suggestion', 'complaint', 'compliment'];
        $result = [];

        foreach ($feedbackTypes as $type) {
            $feedback = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT 
                    m.conversation_id,
                    m.content,
                    m.created_at,
                    JSON_UNQUOTE(JSON_EXTRACT(m.metadata, '$.associated_rating')) as associated_rating,
                    c.rating as conversation_rating
                 FROM {$this->wpdb->prefix}woo_ai_messages m
                 LEFT JOIN {$this->wpdb->prefix}woo_ai_conversations c ON m.conversation_id = c.id
                 WHERE JSON_EXTRACT(m.metadata, '$.feedback_type') = %s
                 AND JSON_EXTRACT(m.metadata, '$.type') IN ('rating_feedback', 'feedback')
                 AND m.created_at >= %s 
                 AND m.created_at <= %s
                 ORDER BY m.created_at DESC
                 LIMIT %d",
                $type,
                $dateFilter['start_date'],
                $dateFilter['end_date'],
                $limit
            ), ARRAY_A);

            $result[$type] = array_map(function ($row) {
                return [
                    'conversation_id' => (int) $row['conversation_id'],
                    'content' => $row['content'],
                    'associated_rating' => $row['associated_rating'] ? (int) $row['associated_rating'] : null,
                    'conversation_rating' => $row['conversation_rating'] ? (int) $row['conversation_rating'] : null,
                    'created_at' => $row['created_at']
                ];
            }, $feedback);
        }

        return $result;
    }
}
