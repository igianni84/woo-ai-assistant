<?php

/**
 * Rating Endpoint Class
 *
 * Handles REST API endpoints for conversation rating and feedback collection.
 * Manages user ratings, feedback submission, and analytics tracking.
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
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class RatingEndpoint
 *
 * Manages rating and feedback-related REST API endpoints.
 * This is a placeholder implementation for Task 5.4.
 *
 * @since 1.0.0
 */
class RatingEndpoint
{
    /**
     * Register rating routes
     *
     * @param string $namespace API namespace
     * @return void
     */
    public function registerRoutes(string $namespace): void
    {
        // Submit conversation rating endpoint
        register_rest_route(
            $namespace,
            '/rating/conversation',
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'rateConversation'],
                'permission_callback' => [$this, 'checkRatingPermission'],
                'args' => [
                    'conversation_id' => [
                        'required' => true,
                        'type' => 'string',
                        'description' => 'Conversation ID to rate',
                        'validate_callback' => [$this, 'validateConversationId'],
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'rating' => [
                        'required' => true,
                        'type' => 'integer',
                        'description' => 'Rating value (1-5)',
                        'validate_callback' => [$this, 'validateRating'],
                        'sanitize_callback' => 'absint'
                    ],
                    'feedback' => [
                        'type' => 'string',
                        'description' => 'Optional feedback text',
                        'validate_callback' => [$this, 'validateFeedback'],
                        'sanitize_callback' => 'sanitize_textarea_field'
                    ],
                    'categories' => [
                        'type' => 'array',
                        'description' => 'Feedback categories',
                        'items' => [
                            'type' => 'string'
                        ],
                        'default' => []
                    ]
                ]
            ]
        );

        // Submit general feedback endpoint
        register_rest_route(
            $namespace,
            '/rating/feedback',
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'submitFeedback'],
                'permission_callback' => [$this, 'checkRatingPermission'],
                'args' => [
                    'type' => [
                        'required' => true,
                        'type' => 'string',
                        'description' => 'Feedback type (feature_request, bug_report, general)',
                        'validate_callback' => [$this, 'validateFeedbackType'],
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'message' => [
                        'required' => true,
                        'type' => 'string',
                        'description' => 'Feedback message',
                        'validate_callback' => [$this, 'validateFeedback'],
                        'sanitize_callback' => 'sanitize_textarea_field'
                    ],
                    'email' => [
                        'type' => 'string',
                        'description' => 'Contact email (optional)',
                        'validate_callback' => [$this, 'validateEmail'],
                        'sanitize_callback' => 'sanitize_email'
                    ],
                    'context' => [
                        'type' => 'object',
                        'description' => 'Additional context information',
                        'default' => []
                    ]
                ]
            ]
        );

        // Get conversation rating endpoint
        register_rest_route(
            $namespace,
            '/rating/conversation/(?P<id>[a-zA-Z0-9-]+)',
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getConversationRating'],
                'permission_callback' => [$this, 'checkRatingPermission'],
                'args' => [
                    'id' => [
                        'required' => true,
                        'type' => 'string',
                        'description' => 'Conversation ID',
                        'sanitize_callback' => 'sanitize_text_field'
                    ]
                ]
            ]
        );

        // Get rating statistics (admin only)
        register_rest_route(
            $namespace,
            '/rating/stats',
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getRatingStats'],
                'permission_callback' => [$this, 'checkAdminPermission'],
                'args' => [
                    'period' => [
                        'type' => 'string',
                        'description' => 'Time period (day, week, month, year)',
                        'default' => 'month',
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'start_date' => [
                        'type' => 'string',
                        'description' => 'Start date (Y-m-d format)',
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'end_date' => [
                        'type' => 'string',
                        'description' => 'End date (Y-m-d format)',
                        'sanitize_callback' => 'sanitize_text_field'
                    ]
                ]
            ]
        );

        Logger::debug('Rating endpoints registered');
    }

    /**
     * Rate a conversation
     * Placeholder for Task 5.4 implementation
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public function rateConversation(WP_REST_Request $request)
    {
        Logger::info('Rate conversation endpoint called (placeholder)');

        // TODO: Task 5.4 - Implement conversation rating functionality
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Conversation rating not yet implemented',
            'data' => [
                'placeholder' => true,
                'task' => 'Task 5.4 - Rating Endpoint Implementation',
                'request_params' => [
                    'conversation_id' => $request->get_param('conversation_id'),
                    'rating' => $request->get_param('rating'),
                    'feedback' => $request->get_param('feedback'),
                    'categories' => $request->get_param('categories')
                ]
            ]
        ], 501);
    }

    /**
     * Submit general feedback
     * Placeholder for Task 5.4 implementation
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public function submitFeedback(WP_REST_Request $request)
    {
        Logger::info('Submit feedback endpoint called (placeholder)');

        // TODO: Task 5.4 - Implement feedback submission functionality
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Feedback submission not yet implemented',
            'data' => [
                'placeholder' => true,
                'task' => 'Task 5.4 - Rating Endpoint Implementation',
                'request_params' => [
                    'type' => $request->get_param('type'),
                    'message' => $request->get_param('message'),
                    'email' => $request->get_param('email'),
                    'context' => $request->get_param('context')
                ]
            ]
        ], 501);
    }

    /**
     * Get conversation rating
     * Placeholder for Task 5.4 implementation
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public function getConversationRating(WP_REST_Request $request)
    {
        $conversationId = $request->get_param('id');

        Logger::info('Get conversation rating endpoint called (placeholder)', [
            'conversation_id' => $conversationId
        ]);

        // TODO: Task 5.4 - Implement rating retrieval functionality
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Rating retrieval not yet implemented',
            'data' => [
                'placeholder' => true,
                'task' => 'Task 5.4 - Rating Endpoint Implementation',
                'conversation_id' => $conversationId
            ]
        ], 501);
    }

    /**
     * Get rating statistics
     * Placeholder for Task 5.4 implementation
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public function getRatingStats(WP_REST_Request $request)
    {
        Logger::info('Get rating stats endpoint called (placeholder)');

        // TODO: Task 5.4 - Implement rating statistics functionality
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Rating statistics not yet implemented',
            'data' => [
                'placeholder' => true,
                'task' => 'Task 5.4 - Rating Endpoint Implementation',
                'request_params' => [
                    'period' => $request->get_param('period'),
                    'start_date' => $request->get_param('start_date'),
                    'end_date' => $request->get_param('end_date')
                ]
            ]
        ], 501);
    }

    /**
     * Check rating permission
     *
     * @param WP_REST_Request $request Request object
     * @return bool|WP_Error True if permission granted
     */
    public function checkRatingPermission(WP_REST_Request $request)
    {
        // Allow both logged in and guest users to submit ratings
        return true;
    }

    /**
     * Check admin permission for statistics
     *
     * @param WP_REST_Request $request Request object
     * @return bool|WP_Error True if permission granted
     */
    public function checkAdminPermission(WP_REST_Request $request)
    {
        if (!current_user_can('manage_woocommerce')) {
            return new WP_Error(
                'insufficient_permissions',
                'Administrator permissions required',
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Validate conversation ID
     *
     * @param string $conversationId Conversation ID
     * @param WP_REST_Request $request Request object
     * @param string $param Parameter name
     * @return bool|WP_Error True if valid
     */
    public function validateConversationId($conversationId, $request, $param)
    {
        if (empty(trim($conversationId))) {
            return new WP_Error(
                'empty_conversation_id',
                'Conversation ID cannot be empty',
                ['status' => 400]
            );
        }

        if (!preg_match('/^[a-zA-Z0-9-]+$/', $conversationId)) {
            return new WP_Error(
                'invalid_conversation_id',
                'Invalid conversation ID format',
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
        if ($rating < 1 || $rating > 5) {
            return new WP_Error(
                'invalid_rating',
                'Rating must be between 1 and 5',
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
        if (strlen($feedback) > 2000) {
            return new WP_Error(
                'feedback_too_long',
                'Feedback is too long (maximum 2000 characters)',
                ['status' => 400]
            );
        }

        return true;
    }

    /**
     * Validate feedback type
     *
     * @param string $type Feedback type
     * @param WP_REST_Request $request Request object
     * @param string $param Parameter name
     * @return bool|WP_Error True if valid
     */
    public function validateFeedbackType($type, $request, $param)
    {
        $validTypes = ['feature_request', 'bug_report', 'general', 'improvement'];

        if (!in_array($type, $validTypes)) {
            return new WP_Error(
                'invalid_feedback_type',
                'Invalid feedback type. Allowed: ' . implode(', ', $validTypes),
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
        if (!empty($email) && !is_email($email)) {
            return new WP_Error(
                'invalid_email',
                'Invalid email address format',
                ['status' => 400]
            );
        }

        return true;
    }
}
