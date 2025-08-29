<?php

/**
 * Human Handoff Handler Class
 *
 * Manages the seamless transition from AI chatbot to human agents when complex issues arise.
 * Handles email notifications, WhatsApp integration, live chat backend functionality,
 * transcript management, and maintains conversation context during handoff.
 *
 * @package WooAiAssistant
 * @subpackage Chatbot
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Chatbot;

use WooAiAssistant\Common\Utils;
use WooAiAssistant\Common\Traits\Singleton;
use WooAiAssistant\Api\LicenseManager;
use WP_Error;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Handoff
 *
 * Comprehensive human handoff management system that handles the transition
 * from AI to human agents, including notification systems, chat backend,
 * transcript management, and multiple communication channels.
 *
 * @since 1.0.0
 */
class Handoff
{
    use Singleton;

    /**
     * Handoff status constants
     *
     * @since 1.0.0
     */
    const STATUS_PENDING = 'pending';
    const STATUS_ASSIGNED = 'assigned';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_ESCALATED = 'escalated';

    /**
     * Notification channel constants
     *
     * @since 1.0.0
     */
    const CHANNEL_EMAIL = 'email';
    const CHANNEL_WHATSAPP = 'whatsapp';
    const CHANNEL_LIVE_CHAT = 'live_chat';
    const CHANNEL_SLACK = 'slack';

    /**
     * Priority level constants
     *
     * @since 1.0.0
     */
    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';

    /**
     * Handoff reason constants
     *
     * @since 1.0.0
     */
    const REASON_USER_REQUEST = 'user_request';
    const REASON_COMPLEX_ISSUE = 'complex_issue';
    const REASON_PAYMENT_ISSUE = 'payment_issue';
    const REASON_TECHNICAL_ISSUE = 'technical_issue';
    const REASON_COMPLAINT = 'complaint';
    const REASON_SENTIMENT_NEGATIVE = 'sentiment_negative';

    /**
     * Rate limiting constants
     *
     * @since 1.0.0
     */
    const MAX_HANDOFFS_PER_HOUR = 10;
    const HANDOFF_COOLDOWN_MINUTES = 5;
    const RATE_LIMIT_TRANSIENT_PREFIX = 'woo_ai_handoff_rate_';

    /**
     * Email template constants
     *
     * @since 1.0.0
     */
    const EMAIL_TEMPLATE_TAKEOVER = 'human-takeover';
    const EMAIL_TEMPLATE_RECAP = 'chat-recap';

    /**
     * License manager instance
     *
     * @since 1.0.0
     * @var LicenseManager|null
     */
    private ?LicenseManager $licenseManager = null;

    /**
     * Conversation handler instance
     *
     * @since 1.0.0
     * @var ConversationHandler|null
     */
    private ?ConversationHandler $conversationHandler = null;

    /**
     * Database table for handoff records
     *
     * @since 1.0.0
     * @var string
     */
    private string $handoffTable;

    /**
     * Database table for handoff transcripts
     *
     * @since 1.0.0
     * @var string
     */
    private string $transcriptTable;

    /**
     * WhatsApp Business API settings
     *
     * @since 1.0.0
     * @var array
     */
    private array $whatsappSettings = [];

    /**
     * Live chat backend settings
     *
     * @since 1.0.0
     * @var array
     */
    private array $liveChatSettings = [];

    /**
     * Initialize the handoff handler
     *
     * Sets up database tables, loads settings, and registers hooks
     * for handoff functionality.
     *
     * @since 1.0.0
     */
    protected function init(): void
    {
        global $wpdb;

        // Set database table names
        $this->handoffTable = $wpdb->prefix . 'woo_ai_handoffs';
        $this->transcriptTable = $wpdb->prefix . 'woo_ai_handoff_transcripts';

        // Initialize dependencies
        $this->licenseManager = LicenseManager::getInstance();
        $this->conversationHandler = ConversationHandler::getInstance();

        // Load settings
        $this->loadSettings();

        // Register hooks
        $this->registerHooks();

        // Schedule cleanup tasks
        $this->scheduleCleanupTasks();
    }

    /**
     * Load handoff settings from database
     *
     * Retrieves WhatsApp, live chat, and other handoff-related settings
     * from WordPress options.
     *
     * @since 1.0.0
     */
    private function loadSettings(): void
    {
        $this->whatsappSettings = get_option('woo_ai_assistant_whatsapp_settings', [
            'enabled' => false,
            'phone_number' => '',
            'api_key' => '',
            'webhook_url' => '',
            'business_id' => '',
        ]);

        $this->liveChatSettings = get_option('woo_ai_assistant_live_chat_settings', [
            'enabled' => false,
            'platform' => '', // intercom, crisp, tawk, zendesk
            'api_key' => '',
            'workspace_id' => '',
            'agent_assignment' => 'round_robin', // round_robin, least_busy, skills_based
        ]);
    }

    /**
     * Register WordPress hooks for handoff functionality
     *
     * Sets up action and filter hooks for handoff events and processing.
     *
     * @since 1.0.0
     */
    private function registerHooks(): void
    {
        // REST API endpoints
        add_action('rest_api_init', [$this, 'registerRestEndpoints']);

        // Admin notifications
        add_action('woo_ai_assistant_handoff_requested', [$this, 'processHandoffRequest'], 10, 3);

        // Email hooks
        add_filter('woocommerce_email_classes', [$this, 'registerEmailClasses']);

        // Live chat webhook
        add_action('woo_ai_assistant_webhook_received', [$this, 'processWebhook'], 10, 2);

        // AJAX handlers for admin
        add_action('wp_ajax_woo_ai_assign_handoff', [$this, 'ajaxAssignHandoff']);
        add_action('wp_ajax_woo_ai_resolve_handoff', [$this, 'ajaxResolveHandoff']);

        // Cleanup hooks
        add_action('woo_ai_assistant_daily_cleanup', [$this, 'cleanupOldHandoffs']);
    }

    /**
     * Schedule cleanup tasks for handoff data
     *
     * Sets up WordPress cron jobs for regular maintenance of handoff records.
     *
     * @since 1.0.0
     */
    private function scheduleCleanupTasks(): void
    {
        if (!wp_next_scheduled('woo_ai_assistant_handoff_cleanup')) {
            wp_schedule_event(time(), 'daily', 'woo_ai_assistant_handoff_cleanup');
        }
    }

    /**
     * Initiate a human handoff request
     *
     * Creates a handoff record and triggers notifications to human agents
     * based on the specified parameters and conversation context.
     *
     * @since 1.0.0
     * @param string $conversationId The conversation ID requiring handoff
     * @param string $reason The reason for handoff (use class constants)
     * @param array $options Optional parameters for handoff configuration
     * @return array|WP_Error Handoff details or error object
     *
     * @throws \Exception When database operations fail
     *
     * @example
     * ```php
     * $handoff = Handoff::getInstance();
     * $result = $handoff->initiateHandoff('conv-123', Handoff::REASON_COMPLEX_ISSUE, [
     *     'priority' => Handoff::PRIORITY_HIGH,
     *     'channels' => [Handoff::CHANNEL_EMAIL, Handoff::CHANNEL_WHATSAPP],
     *     'message' => 'Customer needs help with payment processing'
     * ]);
     * ```
     */
    public function initiateHandoff(string $conversationId, string $reason, array $options = []): array|WP_Error
    {
        try {
            // Check rate limiting
            if ($this->isRateLimited($conversationId)) {
                return new WP_Error(
                    'rate_limited',
                    __('Too many handoff requests. Please wait before trying again.', 'woo-ai-assistant')
                );
            }

            // Get conversation details
            $conversation = $this->conversationHandler->getConversation($conversationId);
            if (is_wp_error($conversation)) {
                return $conversation;
            }

            // Set default options
            $defaults = [
                'priority' => self::PRIORITY_MEDIUM,
                'channels' => [self::CHANNEL_EMAIL],
                'message' => '',
                'user_info' => [],
                'metadata' => [],
            ];
            $options = wp_parse_args($options, $defaults);

            // Create handoff record
            global $wpdb;
            $handoffData = [
                'conversation_id' => $conversationId,
                'reason' => $reason,
                'priority' => $options['priority'],
                'status' => self::STATUS_PENDING,
                'requested_at' => current_time('mysql'),
                'user_id' => $conversation['user_id'] ?? 0,
                'user_email' => $conversation['user_email'] ?? '',
                'user_message' => $options['message'],
                'metadata' => wp_json_encode($options['metadata']),
            ];

            $inserted = $wpdb->insert($this->handoffTable, $handoffData);
            if (!$inserted) {
                throw new \Exception('Failed to create handoff record');
            }

            $handoffId = $wpdb->insert_id;

            // Generate transcript
            $transcript = $this->generateTranscript($conversationId);
            $this->saveTranscript($handoffId, $transcript);

            // Send notifications through specified channels
            foreach ($options['channels'] as $channel) {
                $this->sendNotification($handoffId, $channel, $conversation, $transcript);
            }

            // Log the handoff event
            $this->logHandoffEvent($handoffId, 'initiated', [
                'reason' => $reason,
                'channels' => $options['channels'],
            ]);

            // Fire action for other plugins to hook into
            do_action('woo_ai_assistant_handoff_initiated', $handoffId, $conversationId, $reason);

            return [
                'handoff_id' => $handoffId,
                'status' => self::STATUS_PENDING,
                'channels_notified' => $options['channels'],
                'priority' => $options['priority'],
                'estimated_wait_time' => $this->estimateWaitTime($options['priority']),
            ];
        } catch (\Exception $e) {
            error_log('Woo AI Assistant - Handoff Error: ' . $e->getMessage());
            return new WP_Error('handoff_failed', $e->getMessage());
        }
    }

    /**
     * Send notification through specified channel
     *
     * Dispatches handoff notifications to human agents through the
     * configured communication channel.
     *
     * @since 1.0.0
     * @param int $handoffId The handoff record ID
     * @param string $channel The notification channel
     * @param array $conversation Conversation details
     * @param array $transcript Conversation transcript
     * @return bool Success status
     */
    private function sendNotification(int $handoffId, string $channel, array $conversation, array $transcript): bool
    {
        switch ($channel) {
            case self::CHANNEL_EMAIL:
                return $this->sendEmailNotification($handoffId, $conversation, $transcript);

            case self::CHANNEL_WHATSAPP:
                return $this->sendWhatsAppNotification($handoffId, $conversation, $transcript);

            case self::CHANNEL_LIVE_CHAT:
                return $this->sendLiveChatNotification($handoffId, $conversation, $transcript);

            case self::CHANNEL_SLACK:
                return $this->sendSlackNotification($handoffId, $conversation, $transcript);

            default:
                error_log("Woo AI Assistant - Unknown notification channel: $channel");
                return false;
        }
    }

    /**
     * Send email notification for handoff
     *
     * Sends email to administrators with conversation transcript and
     * handoff details using the configured email template.
     *
     * @since 1.0.0
     * @param int $handoffId The handoff record ID
     * @param array $conversation Conversation details
     * @param array $transcript Conversation transcript
     * @return bool Success status
     */
    private function sendEmailNotification(int $handoffId, array $conversation, array $transcript): bool
    {
        try {
            // Get admin email addresses
            $adminEmails = $this->getAdminEmails();
            if (empty($adminEmails)) {
                throw new \Exception('No admin emails configured');
            }

            // Prepare email data
            $emailData = [
                'handoff_id' => $handoffId,
                'conversation_id' => $conversation['conversation_id'],
                'user_email' => $conversation['user_email'] ?? 'Guest',
                'user_name' => $this->getUserDisplayName($conversation['user_id'] ?? 0),
                'started_at' => $conversation['started_at'] ?? current_time('mysql'),
                'transcript' => $transcript,
                'admin_url' => admin_url('admin.php?page=woo-ai-assistant-handoff&id=' . $handoffId),
            ];

            // Load email template
            $template = $this->loadEmailTemplate(self::EMAIL_TEMPLATE_TAKEOVER, $emailData);

            // Send email
            $subject = sprintf(
                __('[%s] Human Handoff Required - Conversation #%s', 'woo-ai-assistant'),
                get_bloginfo('name'),
                $conversation['conversation_id']
            );

            $headers = [
                'Content-Type: text/html; charset=UTF-8',
                'Reply-To: ' . $conversation['user_email'],
            ];

            $sent = wp_mail($adminEmails, $subject, $template, $headers);

            if ($sent) {
                $this->logHandoffEvent($handoffId, 'email_sent', [
                    'recipients' => $adminEmails,
                ]);
            }

            return $sent;
        } catch (\Exception $e) {
            error_log('Woo AI Assistant - Email notification failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send WhatsApp notification for handoff
     *
     * Sends handoff notification through WhatsApp Business API
     * to configured phone numbers.
     *
     * @since 1.0.0
     * @param int $handoffId The handoff record ID
     * @param array $conversation Conversation details
     * @param array $transcript Conversation transcript
     * @return bool Success status
     */
    private function sendWhatsAppNotification(int $handoffId, array $conversation, array $transcript): bool
    {
        try {
            // Check if WhatsApp is enabled
            if (!$this->whatsappSettings['enabled']) {
                return false;
            }

            // Prepare WhatsApp message
            $message = $this->formatWhatsAppMessage($handoffId, $conversation, $transcript);

            // Get admin phone numbers
            $phoneNumbers = $this->getAdminPhoneNumbers();

            foreach ($phoneNumbers as $phone) {
                // Send via WhatsApp Business API
                $response = $this->sendWhatsAppMessage($phone, $message);

                if ($response) {
                    $this->logHandoffEvent($handoffId, 'whatsapp_sent', [
                        'recipient' => $phone,
                    ]);
                }
            }

            return true;
        } catch (\Exception $e) {
            error_log('Woo AI Assistant - WhatsApp notification failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send live chat notification for handoff
     *
     * Integrates with live chat platforms to notify agents
     * about handoff requests.
     *
     * @since 1.0.0
     * @param int $handoffId The handoff record ID
     * @param array $conversation Conversation details
     * @param array $transcript Conversation transcript
     * @return bool Success status
     */
    private function sendLiveChatNotification(int $handoffId, array $conversation, array $transcript): bool
    {
        try {
            // Check if live chat is enabled
            if (!$this->liveChatSettings['enabled']) {
                return false;
            }

            // Route to appropriate platform handler
            switch ($this->liveChatSettings['platform']) {
                case 'intercom':
                    return $this->sendIntercomNotification($handoffId, $conversation, $transcript);

                case 'crisp':
                    return $this->sendCrispNotification($handoffId, $conversation, $transcript);

                case 'tawk':
                    return $this->sendTawkNotification($handoffId, $conversation, $transcript);

                case 'zendesk':
                    return $this->sendZendeskNotification($handoffId, $conversation, $transcript);

                default:
                    throw new \Exception('Unknown live chat platform: ' . $this->liveChatSettings['platform']);
            }
        } catch (\Exception $e) {
            error_log('Woo AI Assistant - Live chat notification failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate conversation transcript for handoff
     *
     * Creates a formatted transcript of the conversation including
     * all messages, timestamps, and context information.
     *
     * @since 1.0.0
     * @param string $conversationId The conversation ID
     * @return array Formatted transcript data
     */
    public function generateTranscript(string $conversationId): array
    {
        try {
            // Get all messages from conversation
            $messages = $this->conversationHandler->getConversationMessages($conversationId);

            // Get conversation metadata
            $metadata = $this->conversationHandler->getConversationMetadata($conversationId);

            // Format transcript
            $transcript = [
                'conversation_id' => $conversationId,
                'started_at' => $metadata['started_at'] ?? current_time('mysql'),
                'duration' => $metadata['duration'] ?? 0,
                'message_count' => count($messages),
                'user_info' => $metadata['user_info'] ?? [],
                'messages' => [],
            ];

            // Process each message
            foreach ($messages as $message) {
                $transcript['messages'][] = [
                    'timestamp' => $message['created_at'],
                    'role' => $message['role'], // user or assistant
                    'content' => $message['content'],
                    'metadata' => $message['metadata'] ?? [],
                ];
            }

            // Add context information
            $transcript['context'] = [
                'current_page' => $metadata['current_page'] ?? '',
                'cart_value' => $metadata['cart_value'] ?? 0,
                'products_viewed' => $metadata['products_viewed'] ?? [],
                'coupons_applied' => $metadata['coupons_applied'] ?? [],
            ];

            return $transcript;
        } catch (\Exception $e) {
            error_log('Woo AI Assistant - Transcript generation failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Save transcript to database
     *
     * Stores the conversation transcript for future reference
     * and audit purposes.
     *
     * @since 1.0.0
     * @param int $handoffId The handoff record ID
     * @param array $transcript The conversation transcript
     * @return bool Success status
     */
    private function saveTranscript(int $handoffId, array $transcript): bool
    {
        global $wpdb;

        try {
            $data = [
                'handoff_id' => $handoffId,
                'conversation_id' => $transcript['conversation_id'],
                'transcript_data' => wp_json_encode($transcript),
                'created_at' => current_time('mysql'),
            ];

            return (bool) $wpdb->insert($this->transcriptTable, $data);
        } catch (\Exception $e) {
            error_log('Woo AI Assistant - Failed to save transcript: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Assign handoff to a human agent
     *
     * Updates handoff status and assigns it to a specific agent
     * for handling.
     *
     * @since 1.0.0
     * @param int $handoffId The handoff record ID
     * @param int $agentId The agent user ID
     * @param array $options Optional assignment parameters
     * @return bool Success status
     */
    public function assignHandoff(int $handoffId, int $agentId, array $options = []): bool
    {
        global $wpdb;

        try {
            // Update handoff record
            $updated = $wpdb->update(
                $this->handoffTable,
                [
                    'status' => self::STATUS_ASSIGNED,
                    'assigned_to' => $agentId,
                    'assigned_at' => current_time('mysql'),
                ],
                ['id' => $handoffId]
            );

            if (!$updated) {
                throw new \Exception('Failed to assign handoff');
            }

            // Notify the assigned agent
            $this->notifyAssignedAgent($handoffId, $agentId);

            // Log the assignment
            $this->logHandoffEvent($handoffId, 'assigned', [
                'agent_id' => $agentId,
            ]);

            // Fire action for other plugins
            do_action('woo_ai_assistant_handoff_assigned', $handoffId, $agentId);

            return true;
        } catch (\Exception $e) {
            error_log('Woo AI Assistant - Handoff assignment failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark handoff as resolved
     *
     * Updates handoff status to resolved and optionally sends
     * a recap to the customer.
     *
     * @since 1.0.0
     * @param int $handoffId The handoff record ID
     * @param array $resolution Resolution details
     * @return bool Success status
     */
    public function resolveHandoff(int $handoffId, array $resolution = []): bool
    {
        global $wpdb;

        try {
            // Get handoff details
            $handoff = $this->getHandoff($handoffId);
            if (!$handoff) {
                throw new \Exception('Handoff not found');
            }

            // Update handoff record
            $updated = $wpdb->update(
                $this->handoffTable,
                [
                    'status' => self::STATUS_RESOLVED,
                    'resolved_at' => current_time('mysql'),
                    'resolution_notes' => $resolution['notes'] ?? '',
                ],
                ['id' => $handoffId]
            );

            if (!$updated) {
                throw new \Exception('Failed to resolve handoff');
            }

            // Send recap email if requested
            if ($resolution['send_recap'] ?? false) {
                $this->sendRecapEmail($handoffId, $handoff, $resolution);
            }

            // Log the resolution
            $this->logHandoffEvent($handoffId, 'resolved', $resolution);

            // Update conversation status
            $this->conversationHandler->updateConversationStatus(
                $handoff['conversation_id'],
                'resolved_by_human'
            );

            // Fire action for other plugins
            do_action('woo_ai_assistant_handoff_resolved', $handoffId, $resolution);

            return true;
        } catch (\Exception $e) {
            error_log('Woo AI Assistant - Handoff resolution failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get handoff details by ID
     *
     * Retrieves complete handoff record from database.
     *
     * @since 1.0.0
     * @param int $handoffId The handoff record ID
     * @return array|null Handoff data or null if not found
     */
    public function getHandoff(int $handoffId): ?array
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT * FROM {$this->handoffTable} WHERE id = %d",
            $handoffId
        );

        $result = $wpdb->get_row($query, ARRAY_A);

        return $result ?: null;
    }

    /**
     * Get handoff statistics
     *
     * Returns analytics data about handoff performance including
     * average resolution time, handoff rate, and agent performance.
     *
     * @since 1.0.0
     * @param array $filters Optional filters for date range, status, etc.
     * @return array Statistics data
     */
    public function getHandoffStatistics(array $filters = []): array
    {
        global $wpdb;

        try {
            // Set default date range (last 30 days)
            $dateFrom = $filters['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
            $dateTo = $filters['date_to'] ?? date('Y-m-d');

            // Total handoffs
            $totalHandoffs = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->handoffTable} 
                WHERE requested_at BETWEEN %s AND %s",
                $dateFrom . ' 00:00:00',
                $dateTo . ' 23:59:59'
            ));

            // Handoffs by status
            $statusBreakdown = $wpdb->get_results($wpdb->prepare(
                "SELECT status, COUNT(*) as count 
                FROM {$this->handoffTable} 
                WHERE requested_at BETWEEN %s AND %s
                GROUP BY status",
                $dateFrom . ' 00:00:00',
                $dateTo . ' 23:59:59'
            ), ARRAY_A);

            // Average resolution time
            $avgResolutionTime = $wpdb->get_var($wpdb->prepare(
                "SELECT AVG(TIMESTAMPDIFF(MINUTE, requested_at, resolved_at)) 
                FROM {$this->handoffTable} 
                WHERE status = %s 
                AND requested_at BETWEEN %s AND %s",
                self::STATUS_RESOLVED,
                $dateFrom . ' 00:00:00',
                $dateTo . ' 23:59:59'
            ));

            // Handoffs by reason
            $reasonBreakdown = $wpdb->get_results($wpdb->prepare(
                "SELECT reason, COUNT(*) as count 
                FROM {$this->handoffTable} 
                WHERE requested_at BETWEEN %s AND %s
                GROUP BY reason",
                $dateFrom . ' 00:00:00',
                $dateTo . ' 23:59:59'
            ), ARRAY_A);

            // Agent performance
            $agentPerformance = $wpdb->get_results($wpdb->prepare(
                "SELECT assigned_to, 
                COUNT(*) as total_handled,
                AVG(TIMESTAMPDIFF(MINUTE, assigned_at, resolved_at)) as avg_resolution_time
                FROM {$this->handoffTable} 
                WHERE status = %s 
                AND requested_at BETWEEN %s AND %s
                AND assigned_to IS NOT NULL
                GROUP BY assigned_to",
                self::STATUS_RESOLVED,
                $dateFrom . ' 00:00:00',
                $dateTo . ' 23:59:59'
            ), ARRAY_A);

            return [
                'total_handoffs' => (int) $totalHandoffs,
                'avg_resolution_time_minutes' => round((float) $avgResolutionTime, 2),
                'status_breakdown' => $statusBreakdown,
                'reason_breakdown' => $reasonBreakdown,
                'agent_performance' => $agentPerformance,
                'date_range' => [
                    'from' => $dateFrom,
                    'to' => $dateTo,
                ],
            ];
        } catch (\Exception $e) {
            error_log('Woo AI Assistant - Failed to get handoff statistics: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if user is rate limited for handoff requests
     *
     * Implements rate limiting to prevent abuse of handoff system.
     *
     * @since 1.0.0
     * @param string $conversationId The conversation ID
     * @return bool True if rate limited, false otherwise
     */
    private function isRateLimited(string $conversationId): bool
    {
        $transientKey = self::RATE_LIMIT_TRANSIENT_PREFIX . md5($conversationId);
        $attempts = get_transient($transientKey);

        if ($attempts === false) {
            set_transient($transientKey, 1, HOUR_IN_SECONDS);
            return false;
        }

        if ($attempts >= self::MAX_HANDOFFS_PER_HOUR) {
            return true;
        }

        set_transient($transientKey, $attempts + 1, HOUR_IN_SECONDS);
        return false;
    }

    /**
     * Estimate wait time based on priority and current queue
     *
     * Calculates estimated time until human agent responds based
     * on current queue size and priority level.
     *
     * @since 1.0.0
     * @param string $priority Priority level
     * @return int Estimated wait time in minutes
     */
    private function estimateWaitTime(string $priority): int
    {
        global $wpdb;

        // Get pending handoffs count
        $pendingCount = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->handoffTable} 
            WHERE status IN (%s, %s)",
            self::STATUS_PENDING,
            self::STATUS_ASSIGNED
        ));

        // Base wait time calculation
        $baseWaitTime = 5; // 5 minutes base
        $queueMultiplier = max(1, $pendingCount * 2); // 2 minutes per pending request

        // Adjust by priority
        $priorityMultiplier = match ($priority) {
            self::PRIORITY_URGENT => 0.5,
            self::PRIORITY_HIGH => 0.75,
            self::PRIORITY_MEDIUM => 1.0,
            self::PRIORITY_LOW => 1.5,
            default => 1.0,
        };

        return (int) ($baseWaitTime + ($queueMultiplier * $priorityMultiplier));
    }

    /**
     * Log handoff event for audit trail
     *
     * Records handoff-related events for tracking and analysis.
     *
     * @since 1.0.0
     * @param int $handoffId The handoff record ID
     * @param string $event Event type
     * @param array $data Event data
     */
    private function logHandoffEvent(int $handoffId, string $event, array $data = []): void
    {
        global $wpdb;

        try {
            $wpdb->insert(
                $wpdb->prefix . 'woo_ai_handoff_events',
                [
                    'handoff_id' => $handoffId,
                    'event_type' => $event,
                    'event_data' => wp_json_encode($data),
                    'created_at' => current_time('mysql'),
                ]
            );
        } catch (\Exception $e) {
            error_log('Woo AI Assistant - Failed to log handoff event: ' . $e->getMessage());
        }
    }

    /**
     * Get admin email addresses for notifications
     *
     * Retrieves configured email addresses for handoff notifications.
     *
     * @since 1.0.0
     * @return array List of email addresses
     */
    private function getAdminEmails(): array
    {
        $emails = get_option('woo_ai_assistant_handoff_emails', []);

        // Fallback to admin email if none configured
        if (empty($emails)) {
            $emails = [get_option('admin_email')];
        }

        return array_filter($emails, 'is_email');
    }

    /**
     * Load email template with data
     *
     * Loads and processes email template file with provided data.
     *
     * @since 1.0.0
     * @param string $templateName Template file name
     * @param array $data Template data
     * @return string Processed template content
     */
    private function loadEmailTemplate(string $templateName, array $data): string
    {
        $templatePath = WOO_AI_ASSISTANT_PLUGIN_DIR . 'templates/emails/' . $templateName . '.php';

        if (!file_exists($templatePath)) {
            return $this->getDefaultEmailTemplate($templateName, $data);
        }

        // Extract data for template
        extract($data, EXTR_SKIP);

        // Capture template output
        ob_start();
        include $templatePath;
        return ob_get_clean();
    }

    /**
     * Get default email template
     *
     * Returns a default email template when custom template is not available.
     *
     * @since 1.0.0
     * @param string $templateName Template name
     * @param array $data Template data
     * @return string Default template content
     */
    private function getDefaultEmailTemplate(string $templateName, array $data): string
    {
        if ($templateName === self::EMAIL_TEMPLATE_TAKEOVER) {
            return $this->getDefaultTakeoverTemplate($data);
        } elseif ($templateName === self::EMAIL_TEMPLATE_RECAP) {
            return $this->getDefaultRecapTemplate($data);
        }

        return '';
    }

    /**
     * Get default takeover email template
     *
     * @since 1.0.0
     * @param array $data Template data
     * @return string Template HTML
     */
    private function getDefaultTakeoverTemplate(array $data): string
    {
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>';
        $html .= '<h2>' . __('Human Handoff Required', 'woo-ai-assistant') . '</h2>';
        $html .= '<p>' . sprintf(__('Conversation ID: %s', 'woo-ai-assistant'), $data['conversation_id']) . '</p>';
        $html .= '<p>' . sprintf(__('Customer: %s (%s)', 'woo-ai-assistant'), $data['user_name'], $data['user_email']) . '</p>';
        $html .= '<h3>' . __('Conversation Transcript', 'woo-ai-assistant') . '</h3>';

        foreach ($data['transcript']['messages'] as $message) {
            $role = $message['role'] === 'user' ? __('Customer', 'woo-ai-assistant') : __('AI Assistant', 'woo-ai-assistant');
            $html .= '<p><strong>' . $role . '</strong> (' . $message['timestamp'] . '):<br>' . esc_html($message['content']) . '</p>';
        }

        $html .= '<p><a href="' . esc_url($data['admin_url']) . '">' . __('View in Admin', 'woo-ai-assistant') . '</a></p>';
        $html .= '</body></html>';

        return $html;
    }

    /**
     * Clean up old handoff records
     *
     * Removes old handoff records based on retention policy.
     *
     * @since 1.0.0
     * @param int $daysToKeep Number of days to retain records (default: 90)
     * @return int Number of records deleted
     */
    public function cleanupOldHandoffs(int $daysToKeep = 90): int
    {
        global $wpdb;

        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->handoffTable} WHERE requested_at < %s AND status = %s",
            $cutoffDate,
            self::STATUS_RESOLVED
        ));

        // Also clean up associated transcripts
        $wpdb->query($wpdb->prepare(
            "DELETE t FROM {$this->transcriptTable} t
            LEFT JOIN {$this->handoffTable} h ON t.handoff_id = h.id
            WHERE h.id IS NULL"
        ));

        return (int) $deleted;
    }

    /**
     * Register REST API endpoints for handoff functionality
     *
     * @since 1.0.0
     */
    public function registerRestEndpoints(): void
    {
        register_rest_route('woo-ai-assistant/v1', '/handoff/request', [
            'methods' => 'POST',
            'callback' => [$this, 'restHandoffRequest'],
            'permission_callback' => [$this, 'restPermissionCheck'],
        ]);

        register_rest_route('woo-ai-assistant/v1', '/handoff/status/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetHandoffStatus'],
            'permission_callback' => '__return_true',
        ]);
    }
}
