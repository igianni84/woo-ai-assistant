<?php

/**
 * Rating Endpoint Class
 *
 * Handles conversation rating submissions, feedback collection, and rating analytics
 * for the Woo AI Assistant plugin. Implements secure rating processing with
 * comprehensive validation, spam prevention, and database persistence.
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
use WooAiAssistant\Api\LicenseManager;
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
 * Comprehensive rating endpoint that handles conversation ratings, feedback collection,
 * spam prevention, analytics tracking, and integration with the licensing system
 * for usage statistics and performance monitoring.
 *
 * @since 1.0.0
 */
class RatingEndpoint
{
    use Singleton;

    /**
     * Minimum rating value allowed
     *
     * @since 1.0.0
     * @var int
     */
    private const MIN_RATING = 1;

    /**
     * Maximum rating value allowed
     *
     * @since 1.0.0
     * @var int
     */
    private const MAX_RATING = 5;

    /**
     * Maximum feedback text length
     *
     * @since 1.0.0
     * @var int
     */
    private const MAX_FEEDBACK_LENGTH = 1000;

    /**
     * Rate limiting: Maximum ratings per hour per user/IP
     *
     * @since 1.0.0
     * @var int
     */
    private const RATE_LIMIT_PER_HOUR = 10;

    /**
     * Duplicate rating prevention window (seconds)
     *
     * @since 1.0.0
     * @var int
     */
    private const DUPLICATE_PREVENTION_WINDOW = 300; // 5 minutes

    /**
     * LicenseManager instance for usage tracking
     *
     * @since 1.0.0
     * @var LicenseManager
     */
    private $licenseManager;

    /**
     * Rating validation patterns for spam detection
     *
     * @since 1.0.0
     * @var array
     */
    private $spamPatterns = [
        '/https?:\/\/[^\s]+/i', // URLs
        '/\b(?:buy|sale|discount|offer|deal|cheap|free)\b/i', // Commercial terms
        '/(.)\1{4,}/', // Repeated characters
        '/[^\w\s.,!?-]/u' // Unusual characters
    ];

    /**
     * Constructor
     *
     * Initializes the RatingEndpoint with required dependencies and sets up
     * WordPress hooks for AJAX handling and maintenance tasks.
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

            // Initialize License Manager for usage tracking
            $this->licenseManager = $main->getComponent('license_manager');
            if (!$this->licenseManager) {
                Utils::logDebug('LicenseManager component not available in RatingEndpoint');
            }

            Utils::logDebug('RatingEndpoint dependencies initialized successfully');
        } catch (\Exception $e) {
            Utils::logError('Error initializing RatingEndpoint dependencies: ' . $e->getMessage());
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
        // AJAX hooks for rating submission
        add_action('wp_ajax_woo_ai_assistant_submit_rating', [$this, 'handleAjaxRatingSubmission']);
        add_action('wp_ajax_nopriv_woo_ai_assistant_submit_rating', [$this, 'handleAjaxRatingSubmission']);

        // Rating analytics update hook
        add_action('woo_ai_assistant_update_rating_analytics', [$this, 'updateRatingAnalytics']);

        // Cleanup old rating data hook
        add_action('woo_ai_assistant_cleanup_rating_data', [$this, 'cleanupOldRatingData']);
    }

    /**
     * Submit conversation rating and optional feedback
     *
     * Main method that handles the complete rating submission pipeline:
     * validation, spam detection, duplicate prevention, database storage,
     * analytics updates, and usage tracking with comprehensive security measures.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request REST API request object containing rating data
     * @return WP_REST_Response|WP_Error Response object with submission status or error
     *
     * @example
     * POST /wp-json/woo-ai-assistant/v1/rating
     * {
     *   "conversation_id": "conv-123-456",
     *   "rating": 5,
     *   "feedback": "Great assistance, very helpful!",
     *   "category": "helpful",
     *   "nonce": "abc123xyz"
     * }
     */
    public function submitRating(WP_REST_Request $request)
    {
        $startTime = microtime(true);

        try {
            // Step 1: Validate request and security
            $validationResult = $this->validateRequest($request);
            if (is_wp_error($validationResult)) {
                return $validationResult;
            }

            // Step 2: Extract and sanitize parameters
            $conversationId = $this->sanitizeConversationId($request->get_param('conversation_id'));
            $rating = $this->sanitizeRating($request->get_param('rating'));
            $feedback = $this->sanitizeFeedback($request->get_param('feedback') ?: '');
            $category = $this->sanitizeCategory($request->get_param('category') ?: '');
            $metadata = $this->sanitizeMetadata($request->get_param('metadata') ?: []);

            Utils::logDebug('Processing rating submission', [
                'conversation_id' => $conversationId,
                'rating' => $rating,
                'has_feedback' => !empty($feedback),
                'category' => $category
            ]);

            // Step 3: Check rate limiting
            $rateLimitResult = $this->checkRateLimit();
            if (is_wp_error($rateLimitResult)) {
                return $rateLimitResult;
            }

            // Step 4: Validate conversation exists
            $conversationValidation = $this->validateConversationExists($conversationId);
            if (is_wp_error($conversationValidation)) {
                return $conversationValidation;
            }

            // Step 5: Check for duplicate ratings
            $duplicateCheck = $this->checkDuplicateRating($conversationId);
            if (is_wp_error($duplicateCheck)) {
                return $duplicateCheck;
            }

            // Step 6: Perform spam detection on feedback
            $spamCheck = $this->performSpamDetection($feedback);
            if (is_wp_error($spamCheck)) {
                return $spamCheck;
            }

            // Step 7: Prepare rating data
            $ratingData = $this->prepareRatingData($conversationId, $rating, $feedback, $category, $metadata);

            // Step 8: Save rating to database
            $saveResult = $this->saveRating($ratingData);
            if (is_wp_error($saveResult)) {
                return $saveResult;
            }

            // Step 9: Update conversation with rating
            $this->updateConversationRating($conversationId, $rating);

            // Step 10: Update analytics
            $this->scheduleAnalyticsUpdate($rating, $category);

            // Step 11: Update usage statistics
            $this->updateUsageStatistics();

            // Step 12: Trigger rating submitted action
            $this->triggerRatingSubmittedAction($ratingData, $saveResult);

            $executionTime = microtime(true) - $startTime;

            // Step 13: Build success response
            return $this->buildSuccessResponse([
                'rating_id' => $saveResult['rating_id'],
                'conversation_id' => $conversationId,
                'rating' => $rating,
                'feedback_recorded' => !empty($feedback),
                'category' => $category,
                'submitted_at' => current_time('c'),
                'metadata' => [
                    'execution_time' => round($executionTime, 4),
                    'spam_score' => $spamCheck['score'] ?? 0,
                    'duplicate_prevented' => false
                ]
            ]);
        } catch (\InvalidArgumentException $e) {
            Utils::logDebug('Validation error in rating submission: ' . $e->getMessage());
            return $this->buildErrorResponse('Invalid rating data', 'validation_error', 400);
        } catch (\RuntimeException $e) {
            Utils::logError('Runtime error in rating submission: ' . $e->getMessage());
            return $this->buildErrorResponse('Service temporarily unavailable', 'service_error', 503);
        } catch (\Exception $e) {
            Utils::logError('Unexpected error in rating submission: ' . $e->getMessage());
            return $this->buildErrorResponse('An unexpected error occurred', 'general_error', 500);
        }
    }

    /**
     * Validate rating submission request for security and compliance
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return true|WP_Error True if valid, WP_Error otherwise
     */
    private function validateRequest(WP_REST_Request $request)
    {
        // Validate nonce
        $nonce = $request->get_param('nonce');
        if (!wp_verify_nonce($nonce, 'woo_ai_rating')) {
            Utils::logDebug('Invalid nonce in rating request');
            return $this->buildErrorResponse('Security check failed', 'invalid_nonce', 403);
        }

        // Validate required parameters
        $conversationId = $request->get_param('conversation_id');
        if (empty($conversationId)) {
            return $this->buildErrorResponse('Conversation ID is required', 'missing_conversation_id', 400);
        }

        $rating = $request->get_param('rating');
        if ($rating === null || $rating === '') {
            return $this->buildErrorResponse('Rating is required', 'missing_rating', 400);
        }

        // Validate rating range
        $ratingValue = intval($rating);
        if ($ratingValue < self::MIN_RATING || $ratingValue > self::MAX_RATING) {
            return $this->buildErrorResponse(
                sprintf('Rating must be between %d and %d', self::MIN_RATING, self::MAX_RATING),
                'invalid_rating_range',
                400
            );
        }

        // Validate feedback length if provided
        $feedback = $request->get_param('feedback');
        if (!empty($feedback) && strlen($feedback) > self::MAX_FEEDBACK_LENGTH) {
            return $this->buildErrorResponse(
                sprintf('Feedback too long (max %d characters)', self::MAX_FEEDBACK_LENGTH),
                'feedback_too_long',
                400
            );
        }

        return true;
    }

    /**
     * Sanitize conversation ID
     *
     * @since 1.0.0
     * @param string $conversationId Raw conversation ID
     * @return string Sanitized conversation ID
     * @throws \InvalidArgumentException When conversation ID is invalid
     */
    private function sanitizeConversationId(string $conversationId): string
    {
        $sanitized = sanitize_text_field(trim($conversationId));

        if (empty($sanitized)) {
            throw new \InvalidArgumentException('Conversation ID cannot be empty');
        }

        // Validate format (should be like 'conv-uuid' or similar)
        if (!preg_match('/^conv-[a-f0-9-]+$/', $sanitized)) {
            throw new \InvalidArgumentException('Invalid conversation ID format');
        }

        return $sanitized;
    }

    /**
     * Sanitize rating value
     *
     * @since 1.0.0
     * @param mixed $rating Raw rating value
     * @return int Sanitized rating value
     * @throws \InvalidArgumentException When rating is invalid
     */
    private function sanitizeRating($rating): int
    {
        $ratingValue = intval($rating);

        if ($ratingValue < self::MIN_RATING || $ratingValue > self::MAX_RATING) {
            throw new \InvalidArgumentException(sprintf(
                'Rating must be between %d and %d',
                self::MIN_RATING,
                self::MAX_RATING
            ));
        }

        return $ratingValue;
    }

    /**
     * Sanitize feedback text
     *
     * @since 1.0.0
     * @param string $feedback Raw feedback text
     * @return string Sanitized feedback text
     * @throws \InvalidArgumentException When feedback is invalid
     */
    private function sanitizeFeedback(string $feedback): string
    {
        $sanitized = sanitize_textarea_field(trim($feedback));

        if (strlen($sanitized) > self::MAX_FEEDBACK_LENGTH) {
            throw new \InvalidArgumentException(sprintf(
                'Feedback too long (max %d characters)',
                self::MAX_FEEDBACK_LENGTH
            ));
        }

        return $sanitized;
    }

    /**
     * Sanitize rating category
     *
     * @since 1.0.0
     * @param string $category Raw category value
     * @return string Sanitized category value
     */
    private function sanitizeCategory(string $category): string
    {
        $sanitized = sanitize_text_field(trim($category));

        // Validate against allowed categories
        $allowedCategories = [
            'helpful',
            'accurate',
            'fast',
            'friendly',
            'knowledgeable',
            'unhelpful',
            'inaccurate',
            'slow',
            'confusing',
            'other'
        ];

        if (!empty($sanitized) && !in_array($sanitized, $allowedCategories, true)) {
            return 'other';
        }

        return $sanitized;
    }

    /**
     * Sanitize metadata array
     *
     * @since 1.0.0
     * @param array $metadata Raw metadata
     * @return array Sanitized metadata
     */
    private function sanitizeMetadata(array $metadata): array
    {
        $sanitized = [];

        // Sanitize user agent
        if (isset($metadata['user_agent'])) {
            $sanitized['user_agent'] = sanitize_text_field($metadata['user_agent']);
        }

        // Sanitize page context
        if (isset($metadata['page_context'])) {
            $sanitized['page_context'] = sanitize_text_field($metadata['page_context']);
        }

        // Sanitize session duration
        if (isset($metadata['session_duration'])) {
            $sanitized['session_duration'] = absint($metadata['session_duration']);
        }

        // Sanitize response time (user's perception)
        if (isset($metadata['perceived_response_time'])) {
            $sanitized['perceived_response_time'] = absint($metadata['perceived_response_time']);
        }

        // Sanitize interaction count
        if (isset($metadata['interaction_count'])) {
            $sanitized['interaction_count'] = absint($metadata['interaction_count']);
        }

        return $sanitized;
    }

    /**
     * Check rate limiting for rating submissions
     *
     * @since 1.0.0
     * @return true|WP_Error True if within limits, error otherwise
     */
    private function checkRateLimit()
    {
        $userId = get_current_user_id();
        $userKey = $userId ? "user_{$userId}" : 'ip_' . $this->getClientIp();

        $rateLimitKey = "woo_ai_rating_rate_limit_{$userKey}";
        $currentCount = get_transient($rateLimitKey) ?: 0;

        if ($currentCount >= self::RATE_LIMIT_PER_HOUR) {
            Utils::logDebug('Rating rate limit exceeded', ['user_key' => $userKey, 'count' => $currentCount]);
            return $this->buildErrorResponse(
                'Too many rating submissions. Please try again later.',
                'rate_limit_exceeded',
                429
            );
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
     * Validate that conversation exists in database
     *
     * @since 1.0.0
     * @param string $conversationId Conversation identifier
     * @return true|WP_Error True if exists, error otherwise
     */
    private function validateConversationExists(string $conversationId)
    {
        global $wpdb;

        try {
            $conversationTable = $wpdb->prefix . 'woo_ai_conversations';

            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$conversationTable} WHERE conversation_id = %s",
                $conversationId
            ));

            if (!$exists) {
                Utils::logDebug('Conversation not found for rating', ['conversation_id' => $conversationId]);
                return $this->buildErrorResponse(
                    'Conversation not found',
                    'conversation_not_found',
                    404
                );
            }

            return true;
        } catch (\Exception $e) {
            Utils::logError('Error validating conversation existence: ' . $e->getMessage());
            return $this->buildErrorResponse('Database error', 'database_error', 500);
        }
    }

    /**
     * Check for duplicate rating submissions
     *
     * @since 1.0.0
     * @param string $conversationId Conversation identifier
     * @return true|WP_Error True if no duplicate, error otherwise
     */
    private function checkDuplicateRating(string $conversationId)
    {
        global $wpdb;

        try {
            // Check if rating already exists for this conversation
            $ratingsTable = $wpdb->prefix . 'woo_ai_conversation_ratings';

            // Check for recent rating from same user/IP
            $userId = get_current_user_id();
            $clientIp = $this->getClientIp();
            $cutoffTime = date('Y-m-d H:i:s', time() - self::DUPLICATE_PREVENTION_WINDOW);

            $duplicateQuery = $wpdb->prepare(
                "SELECT COUNT(*) FROM {$ratingsTable} 
                WHERE conversation_id = %s 
                AND (user_id = %d OR ip_address = %s) 
                AND created_at > %s",
                $conversationId,
                $userId,
                $clientIp,
                $cutoffTime
            );

            $duplicateCount = $wpdb->get_var($duplicateQuery);

            if ($duplicateCount > 0) {
                Utils::logDebug('Duplicate rating attempt detected', [
                    'conversation_id' => $conversationId,
                    'user_id' => $userId,
                    'ip' => $clientIp
                ]);
                return $this->buildErrorResponse(
                    'You have already rated this conversation recently',
                    'duplicate_rating',
                    409
                );
            }

            return true;
        } catch (\Exception $e) {
            Utils::logError('Error checking duplicate rating: ' . $e->getMessage());
            // Allow rating to continue if check fails
            return true;
        }
    }

    /**
     * Perform spam detection on feedback text
     *
     * @since 1.0.0
     * @param string $feedback Feedback text to analyze
     * @return array|WP_Error Analysis result with spam score or error
     */
    private function performSpamDetection(string $feedback)
    {
        if (empty($feedback)) {
            return ['score' => 0, 'is_spam' => false];
        }

        try {
            $spamScore = 0;
            $maxScore = count($this->spamPatterns) * 10; // Each pattern can add up to 10 points

            // Check against spam patterns
            foreach ($this->spamPatterns as $pattern) {
                if (preg_match($pattern, $feedback)) {
                    $spamScore += 10;
                }
            }

            // Check for excessive capitalization
            $uppercaseRatio = (strlen($feedback) - strlen(preg_replace('/[A-Z]/', '', $feedback))) / strlen($feedback);
            if ($uppercaseRatio > 0.5) {
                $spamScore += 15;
            }

            // Normalize spam score to 0-100
            $normalizedScore = min(100, ($spamScore / $maxScore) * 100);

            // Consider spam if score is above threshold
            $spamThreshold = apply_filters('woo_ai_assistant_spam_threshold', 70);
            $isSpam = $normalizedScore > $spamThreshold;

            if ($isSpam) {
                Utils::logDebug('Spam detected in rating feedback', [
                    'spam_score' => $normalizedScore,
                    'threshold' => $spamThreshold
                ]);
                return $this->buildErrorResponse(
                    'Feedback appears to contain spam or inappropriate content',
                    'spam_detected',
                    400
                );
            }

            return [
                'score' => $normalizedScore,
                'is_spam' => false,
                'patterns_matched' => $spamScore / 10
            ];
        } catch (\Exception $e) {
            Utils::logError('Error in spam detection: ' . $e->getMessage());
            // Allow feedback if spam detection fails
            return ['score' => 0, 'is_spam' => false];
        }
    }

    /**
     * Prepare rating data for database storage
     *
     * @since 1.0.0
     * @param string $conversationId Conversation identifier
     * @param int $rating Rating value
     * @param string $feedback Feedback text
     * @param string $category Rating category
     * @param array $metadata Additional metadata
     * @return array Prepared rating data
     */
    private function prepareRatingData(
        string $conversationId,
        int $rating,
        string $feedback,
        string $category,
        array $metadata
    ): array {
        $userId = get_current_user_id();

        return [
            'conversation_id' => $conversationId,
            'user_id' => $userId ?: null,
            'rating' => $rating,
            'feedback' => $feedback,
            'category' => $category,
            'ip_address' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referer' => $_SERVER['HTTP_REFERER'] ?? '',
            'metadata' => wp_json_encode(array_merge($metadata, [
                'submitted_from' => 'rest_api',
                'timestamp' => current_time('c'),
                'user_type' => $userId ? 'registered' : 'guest'
            ])),
            'created_at' => current_time('mysql')
        ];
    }

    /**
     * Save rating data to database
     *
     * @since 1.0.0
     * @param array $ratingData Prepared rating data
     * @return array|WP_Error Success result with rating ID or error
     */
    private function saveRating(array $ratingData)
    {
        global $wpdb;

        try {
            $ratingsTable = $wpdb->prefix . 'woo_ai_conversation_ratings';

            $result = $wpdb->insert($ratingsTable, $ratingData, [
                '%s', // conversation_id
                '%d', // user_id (can be null)
                '%d', // rating
                '%s', // feedback
                '%s', // category
                '%s', // ip_address
                '%s', // user_agent
                '%s', // referer
                '%s', // metadata
                '%s'  // created_at
            ]);

            if ($result === false) {
                Utils::logError('Failed to save rating data: ' . $wpdb->last_error);
                return $this->buildErrorResponse('Failed to save rating', 'database_error', 500);
            }

            $ratingId = $wpdb->insert_id;

            Utils::logDebug('Rating saved successfully', [
                'rating_id' => $ratingId,
                'conversation_id' => $ratingData['conversation_id'],
                'rating' => $ratingData['rating']
            ]);

            return [
                'rating_id' => $ratingId,
                'success' => true
            ];
        } catch (\Exception $e) {
            Utils::logError('Error saving rating: ' . $e->getMessage());
            return $this->buildErrorResponse('Database error', 'database_error', 500);
        }
    }

    /**
     * Update conversation record with rating information
     *
     * @since 1.0.0
     * @param string $conversationId Conversation identifier
     * @param int $rating Rating value
     * @return void
     */
    private function updateConversationRating(string $conversationId, int $rating): void
    {
        global $wpdb;

        try {
            $conversationTable = $wpdb->prefix . 'woo_ai_conversations';

            $wpdb->update(
                $conversationTable,
                [
                    'rating' => $rating,
                    'rated_at' => current_time('mysql')
                ],
                ['conversation_id' => $conversationId],
                ['%d', '%s'],
                ['%s']
            );

            if ($wpdb->last_error) {
                Utils::logError('Error updating conversation rating: ' . $wpdb->last_error);
            }
        } catch (\Exception $e) {
            Utils::logError('Error updating conversation rating: ' . $e->getMessage());
        }
    }

    /**
     * Schedule analytics update for rating data
     *
     * @since 1.0.0
     * @param int $rating Rating value
     * @param string $category Rating category
     * @return void
     */
    private function scheduleAnalyticsUpdate(int $rating, string $category): void
    {
        try {
            // Schedule analytics update if not already scheduled
            if (!wp_next_scheduled('woo_ai_assistant_update_rating_analytics')) {
                wp_schedule_single_event(
                    time() + 300, // 5 minutes delay
                    'woo_ai_assistant_update_rating_analytics'
                );
            }

            // Immediately update some real-time metrics
            $this->updateRealTimeMetrics($rating, $category);
        } catch (\Exception $e) {
            Utils::logError('Error scheduling analytics update: ' . $e->getMessage());
        }
    }

    /**
     * Update real-time rating metrics
     *
     * @since 1.0.0
     * @param int $rating Rating value
     * @param string $category Rating category
     * @return void
     */
    private function updateRealTimeMetrics(int $rating, string $category): void
    {
        try {
            // Update daily rating statistics
            $today = date('Y-m-d');
            $dailyStatsKey = "woo_ai_daily_ratings_{$today}";

            $dailyStats = get_option($dailyStatsKey, [
                'total_ratings' => 0,
                'rating_sum' => 0,
                'rating_distribution' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
                'categories' => []
            ]);

            $dailyStats['total_ratings']++;
            $dailyStats['rating_sum'] += $rating;
            $dailyStats['rating_distribution'][$rating]++;

            if (!empty($category)) {
                $dailyStats['categories'][$category] = ($dailyStats['categories'][$category] ?? 0) + 1;
            }

            update_option($dailyStatsKey, $dailyStats);

            // Update overall statistics
            $overallStats = get_option('woo_ai_assistant_rating_stats', [
                'total_ratings' => 0,
                'average_rating' => 0,
                'last_updated' => null
            ]);

            $overallStats['total_ratings']++;
            $overallStats['average_rating'] = (
                ($overallStats['average_rating'] * ($overallStats['total_ratings'] - 1)) + $rating
            ) / $overallStats['total_ratings'];
            $overallStats['last_updated'] = current_time('mysql');

            update_option('woo_ai_assistant_rating_stats', $overallStats);
        } catch (\Exception $e) {
            Utils::logError('Error updating real-time metrics: ' . $e->getMessage());
        }
    }

    /**
     * Update usage statistics for licensing
     *
     * @since 1.0.0
     * @return void
     */
    private function updateUsageStatistics(): void
    {
        if (!$this->licenseManager) {
            return;
        }

        try {
            $this->licenseManager->recordUsage('rating', [
                'timestamp' => current_time('mysql')
            ]);
        } catch (\Exception $e) {
            Utils::logError('Error updating usage statistics: ' . $e->getMessage());
        }
    }

    /**
     * Trigger rating submitted action for extensibility
     *
     * @since 1.0.0
     * @param array $ratingData Rating data
     * @param array $saveResult Save operation result
     * @return void
     */
    private function triggerRatingSubmittedAction(array $ratingData, array $saveResult): void
    {
        try {
            /**
             * Rating submitted action
             *
             * Fired after a rating has been successfully submitted.
             *
             * @since 1.0.0
             * @param array $ratingData The rating data that was submitted
             * @param array $saveResult The result of the save operation
             */
            do_action('woo_ai_assistant_rating_submitted', $ratingData, $saveResult);
        } catch (\Exception $e) {
            Utils::logError('Error triggering rating submitted action: ' . $e->getMessage());
        }
    }

    /**
     * Get rating statistics for a specific conversation or overall
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response with statistics
     */
    public function getRatingStatistics(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $conversationId = $request->get_param('conversation_id');
            $period = $request->get_param('period') ?: 'all';

            global $wpdb;
            $ratingsTable = $wpdb->prefix . 'woo_ai_conversation_ratings';

            $stats = [];

            if ($conversationId) {
                // Get statistics for specific conversation
                $stats = $this->getConversationRatingStats($conversationId);
            } else {
                // Get overall statistics
                $stats = $this->getOverallRatingStats($period);
            }

            return $this->buildSuccessResponse($stats);
        } catch (\Exception $e) {
            Utils::logError('Error getting rating statistics: ' . $e->getMessage());
            return $this->buildErrorResponse('Failed to get statistics', 'statistics_error', 500);
        }
    }

    /**
     * Get rating statistics for a specific conversation
     *
     * @since 1.0.0
     * @param string $conversationId Conversation identifier
     * @return array Rating statistics
     */
    private function getConversationRatingStats(string $conversationId): array
    {
        global $wpdb;
        $ratingsTable = $wpdb->prefix . 'woo_ai_conversation_ratings';

        try {
            $stats = $wpdb->get_row($wpdb->prepare(
                "SELECT 
                    COUNT(*) as total_ratings,
                    AVG(rating) as average_rating,
                    MIN(rating) as min_rating,
                    MAX(rating) as max_rating,
                    COUNT(CASE WHEN rating >= 4 THEN 1 END) as positive_ratings,
                    COUNT(CASE WHEN rating <= 2 THEN 1 END) as negative_ratings,
                    COUNT(CASE WHEN feedback != '' THEN 1 END) as ratings_with_feedback
                FROM {$ratingsTable} 
                WHERE conversation_id = %s",
                $conversationId
            ), ARRAY_A);

            return [
                'conversation_id' => $conversationId,
                'total_ratings' => intval($stats['total_ratings'] ?? 0),
                'average_rating' => round(floatval($stats['average_rating'] ?? 0), 2),
                'min_rating' => intval($stats['min_rating'] ?? 0),
                'max_rating' => intval($stats['max_rating'] ?? 0),
                'positive_ratings' => intval($stats['positive_ratings'] ?? 0),
                'negative_ratings' => intval($stats['negative_ratings'] ?? 0),
                'ratings_with_feedback' => intval($stats['ratings_with_feedback'] ?? 0)
            ];
        } catch (\Exception $e) {
            Utils::logError('Error getting conversation rating stats: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get overall rating statistics
     *
     * @since 1.0.0
     * @param string $period Time period (day, week, month, all)
     * @return array Overall rating statistics
     */
    private function getOverallRatingStats(string $period): array
    {
        global $wpdb;
        $ratingsTable = $wpdb->prefix . 'woo_ai_conversation_ratings';

        try {
            $whereClause = '';
            $params = [];

            switch ($period) {
                case 'day':
                    $whereClause = 'WHERE DATE(created_at) = CURDATE()';
                    break;
                case 'week':
                    $whereClause = 'WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
                    break;
                case 'month':
                    $whereClause = 'WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
                    break;
                case 'all':
                default:
                    // No additional WHERE clause
                    break;
            }

            $stats = $wpdb->get_row(
                "SELECT 
                    COUNT(*) as total_ratings,
                    AVG(rating) as average_rating,
                    MIN(rating) as min_rating,
                    MAX(rating) as max_rating,
                    COUNT(CASE WHEN rating = 1 THEN 1 END) as rating_1_count,
                    COUNT(CASE WHEN rating = 2 THEN 1 END) as rating_2_count,
                    COUNT(CASE WHEN rating = 3 THEN 1 END) as rating_3_count,
                    COUNT(CASE WHEN rating = 4 THEN 1 END) as rating_4_count,
                    COUNT(CASE WHEN rating = 5 THEN 1 END) as rating_5_count,
                    COUNT(CASE WHEN feedback != '' THEN 1 END) as ratings_with_feedback
                FROM {$ratingsTable} 
                {$whereClause}",
                ARRAY_A
            );

            return [
                'period' => $period,
                'total_ratings' => intval($stats['total_ratings'] ?? 0),
                'average_rating' => round(floatval($stats['average_rating'] ?? 0), 2),
                'min_rating' => intval($stats['min_rating'] ?? 0),
                'max_rating' => intval($stats['max_rating'] ?? 0),
                'rating_distribution' => [
                    1 => intval($stats['rating_1_count'] ?? 0),
                    2 => intval($stats['rating_2_count'] ?? 0),
                    3 => intval($stats['rating_3_count'] ?? 0),
                    4 => intval($stats['rating_4_count'] ?? 0),
                    5 => intval($stats['rating_5_count'] ?? 0)
                ],
                'ratings_with_feedback' => intval($stats['ratings_with_feedback'] ?? 0),
                'satisfaction_rate' => $this->calculateSatisfactionRate($stats)
            ];
        } catch (\Exception $e) {
            Utils::logError('Error getting overall rating stats: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Calculate satisfaction rate from rating statistics
     *
     * @since 1.0.0
     * @param array $stats Rating statistics
     * @return float Satisfaction rate percentage
     */
    private function calculateSatisfactionRate(array $stats): float
    {
        $totalRatings = intval($stats['total_ratings'] ?? 0);

        if ($totalRatings === 0) {
            return 0.0;
        }

        $positiveRatings = intval($stats['rating_4_count'] ?? 0) + intval($stats['rating_5_count'] ?? 0);

        return round(($positiveRatings / $totalRatings) * 100, 2);
    }

    /**
     * Handle AJAX rating submission
     *
     * @since 1.0.0
     * @return void
     */
    public function handleAjaxRatingSubmission(): void
    {
        try {
            // Create a mock WP_REST_Request from AJAX data
            $request = new WP_REST_Request('POST', '/woo-ai-assistant/v1/rating');
            $request->set_param('conversation_id', $_POST['conversation_id'] ?? '');
            $request->set_param('rating', $_POST['rating'] ?? '');
            $request->set_param('feedback', $_POST['feedback'] ?? '');
            $request->set_param('category', $_POST['category'] ?? '');
            $request->set_param('metadata', $_POST['metadata'] ?? []);
            $request->set_param('nonce', $_POST['nonce'] ?? '');

            $response = $this->submitRating($request);

            if (is_wp_error($response)) {
                wp_send_json_error([
                    'message' => $response->get_error_message(),
                    'code' => $response->get_error_code()
                ]);
            } else {
                wp_send_json_success($response->get_data());
            }
        } catch (\Exception $e) {
            Utils::logError('AJAX rating submission error: ' . $e->getMessage());
            wp_send_json_error([
                'message' => 'An error occurred processing your rating',
                'code' => 'ajax_error'
            ]);
        }
    }

    /**
     * Update rating analytics (scheduled task)
     *
     * @since 1.0.0
     * @return void
     */
    public function updateRatingAnalytics(): void
    {
        try {
            Utils::logDebug('Running scheduled rating analytics update');

            // Update comprehensive analytics
            $this->calculateComprehensiveAnalytics();

            // Clean up old temporary data
            $this->cleanupTempAnalyticsData();

            Utils::logDebug('Rating analytics update completed');
        } catch (\Exception $e) {
            Utils::logError('Error updating rating analytics: ' . $e->getMessage());
        }
    }

    /**
     * Calculate comprehensive analytics
     *
     * @since 1.0.0
     * @return void
     */
    private function calculateComprehensiveAnalytics(): void
    {
        global $wpdb;

        try {
            $ratingsTable = $wpdb->prefix . 'woo_ai_conversation_ratings';

            // Calculate monthly trends
            $monthlyTrends = $wpdb->get_results(
                "SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as total_ratings,
                    AVG(rating) as average_rating
                FROM {$ratingsTable} 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                GROUP BY month
                ORDER BY month DESC",
                ARRAY_A
            );

            update_option('woo_ai_assistant_monthly_rating_trends', $monthlyTrends);

            // Calculate category analytics
            $categoryStats = $wpdb->get_results(
                "SELECT 
                    category,
                    COUNT(*) as count,
                    AVG(rating) as average_rating
                FROM {$ratingsTable} 
                WHERE category != '' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY category
                ORDER BY count DESC",
                ARRAY_A
            );

            update_option('woo_ai_assistant_category_rating_stats', $categoryStats);
        } catch (\Exception $e) {
            Utils::logError('Error calculating comprehensive analytics: ' . $e->getMessage());
        }
    }

    /**
     * Clean up temporary analytics data
     *
     * @since 1.0.0
     * @return void
     */
    private function cleanupTempAnalyticsData(): void
    {
        try {
            // Clean up daily stats older than 90 days
            global $wpdb;

            $cutoffDate = date('Y-m-d', strtotime('-90 days'));
            $optionPattern = "woo_ai_daily_ratings_{$cutoffDate}%";

            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $optionPattern
            ));
        } catch (\Exception $e) {
            Utils::logError('Error cleaning up temp analytics data: ' . $e->getMessage());
        }
    }

    /**
     * Clean up old rating data (scheduled task)
     *
     * @since 1.0.0
     * @return void
     */
    public function cleanupOldRatingData(): void
    {
        try {
            global $wpdb;
            $ratingsTable = $wpdb->prefix . 'woo_ai_conversation_ratings';

            $retentionDays = apply_filters('woo_ai_assistant_rating_retention_days', 365); // 1 year
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));

            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$ratingsTable} WHERE created_at < %s",
                $cutoffDate
            ));

            if ($deleted !== false && $deleted > 0) {
                Utils::logDebug("Cleaned up {$deleted} old rating records");
            }
        } catch (\Exception $e) {
            Utils::logError('Error cleaning up old rating data: ' . $e->getMessage());
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
            'callback' => [self::getInstance(), 'submitRating'],
            'permission_callback' => '__return_true', // Public endpoint
            'args' => [
                'conversation_id' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Conversation identifier',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ($value) {
                        if (empty(trim($value))) {
                            return new WP_Error('empty_conversation_id', 'Conversation ID cannot be empty');
                        }
                        return true;
                    }
                ],
                'rating' => [
                    'required' => true,
                    'type' => 'integer',
                    'description' => 'Rating value (1-5 stars)',
                    'minimum' => self::MIN_RATING,
                    'maximum' => self::MAX_RATING,
                    'validate_callback' => function ($value) {
                        $rating = intval($value);
                        if ($rating < self::MIN_RATING || $rating > self::MAX_RATING) {
                            return new WP_Error('invalid_rating', sprintf(
                                'Rating must be between %d and %d',
                                self::MIN_RATING,
                                self::MAX_RATING
                            ));
                        }
                        return true;
                    }
                ],
                'feedback' => [
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Optional feedback text',
                    'sanitize_callback' => 'sanitize_textarea_field',
                    'validate_callback' => function ($value) {
                        if (strlen($value) > self::MAX_FEEDBACK_LENGTH) {
                            return new WP_Error('feedback_too_long', sprintf(
                                'Feedback too long (max %d characters)',
                                self::MAX_FEEDBACK_LENGTH
                            ));
                        }
                        return true;
                    }
                ],
                'category' => [
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Rating category',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'metadata' => [
                    'required' => false,
                    'type' => 'object',
                    'description' => 'Additional metadata',
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
