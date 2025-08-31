<?php

/**
 * Conversation Handler Class
 *
 * Manages conversation persistence, context management, and session handling
 * for the AI-powered chat system. Handles the complete lifecycle of conversations
 * including creation, message storage, context tracking, and cleanup.
 *
 * @package WooAiAssistant
 * @subpackage Chatbot
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Chatbot;

use WooAiAssistant\Common\Traits\Singleton;
use WooAiAssistant\Common\Logger;
use WooAiAssistant\Common\Utils;
use WooAiAssistant\Common\Cache;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ConversationHandler
 *
 * Manages conversation lifecycle, persistence, and context management.
 *
 * @since 1.0.0
 */
class ConversationHandler
{
    use Singleton;

    /**
     * WordPress database instance
     *
     * @var \wpdb
     */
    private \wpdb $wpdb;

    /**
     * Logger instance
     *
     * @var Logger
     */
    private Logger $logger;

    /**
     * Cache instance
     *
     * @var Cache
     */
    private Cache $cache;

    /**
     * Conversation statuses
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_ABANDONED = 'abandoned';
    public const STATUS_HANDOFF = 'handoff';

    /**
     * Message roles
     */
    public const ROLE_USER = 'user';
    public const ROLE_ASSISTANT = 'assistant';
    public const ROLE_SYSTEM = 'system';

    /**
     * Cache keys
     */
    public const CACHE_CONVERSATION_PREFIX = 'woo_ai_conversation_';
    public const CACHE_SESSION_PREFIX = 'woo_ai_session_';
    public const CACHE_CONTEXT_PREFIX = 'woo_ai_context_';

    /**
     * Session and context settings
     */
    public const SESSION_TIMEOUT_MINUTES = 30;
    public const MAX_CONVERSATIONS_PER_SESSION = 10;
    public const CONTEXT_RETENTION_HOURS = 24;
    public const MAX_CONTEXT_SIZE = 10000; // bytes

    /**
     * Initialize the conversation handler
     *
     * @return void
     */
    protected function init(): void
    {
        global $wpdb;

        $this->wpdb = $wpdb;
        $this->logger = Logger::getInstance();
        $this->cache = Cache::getInstance();
    }

    /**
     * Create a new conversation
     *
     * @param array $args Conversation arguments
     * @param int|null $args['user_id'] WordPress user ID (null for guests)
     * @param string|null $args['session_id'] Session identifier (auto-generated if not provided)
     * @param array $args['context'] Initial conversation context
     * @param string|null $args['user_ip'] User IP address
     * @param string|null $args['user_agent'] User agent string
     *
     * @return array|false Conversation data array or false on failure
     *                     Returns ['id' => int, 'session_id' => string, 'created_at' => string]
     *
     * @throws \InvalidArgumentException When context data is too large
     *
     * @example
     * ```php
     * $handler = ConversationHandler::getInstance();
     * $conversation = $handler->createConversation([
     *     'user_id' => 123,
     *     'context' => ['page' => 'product', 'product_id' => 456],
     *     'user_ip' => '127.0.0.1'
     * ]);
     * ```
     */
    public function createConversation(array $args = []): array|false
    {
        try {
            $defaults = [
                'user_id' => null,
                'session_id' => null,
                'context' => [],
                'user_ip' => null,
                'user_agent' => null
            ];

            $args = wp_parse_args($args, $defaults);

            // Generate session ID if not provided
            if (empty($args['session_id'])) {
                $args['session_id'] = $this->generateSessionId();
            }

            // Validate session limits
            if (!$this->validateSessionLimits($args['session_id'])) {
                $this->logger->warning('Session limit exceeded', [
                    'session_id' => $args['session_id']
                ]);
                return false;
            }

            // Validate context size
            $contextJson = json_encode($args['context']);
            if (strlen($contextJson) > self::MAX_CONTEXT_SIZE) {
                throw new \InvalidArgumentException('Context data exceeds maximum size limit');
            }

            // Prepare conversation data
            $conversationData = [
                'user_id' => $args['user_id'],
                'session_id' => $args['session_id'],
                'status' => self::STATUS_ACTIVE,
                'context_data' => $contextJson,
                'user_ip' => $args['user_ip'] ?: Utils::getUserIp(),
                'user_agent' => $args['user_agent'] ?: Utils::getUserAgent(),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ];

            // Insert conversation
            $result = $this->wpdb->insert(
                $this->wpdb->prefix . 'woo_ai_conversations',
                $conversationData,
                ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
            );

            if ($result === false) {
                $this->logger->error('Failed to create conversation', [
                    'session_id' => $args['session_id'],
                    'error' => $this->wpdb->last_error
                ]);
                return false;
            }

            $conversationId = $this->wpdb->insert_id;

            // Cache conversation data
            $conversationResult = [
                'id' => $conversationId,
                'session_id' => $args['session_id'],
                'user_id' => $args['user_id'],
                'status' => self::STATUS_ACTIVE,
                'context' => $args['context'],
                'created_at' => $conversationData['created_at'],
                'total_messages' => 0
            ];

            $this->cache->set(
                self::CACHE_CONVERSATION_PREFIX . $conversationId,
                $conversationResult,
                HOUR_IN_SECONDS
            );

            // Update session conversation count
            $this->updateSessionConversationCount($args['session_id']);

            // Log conversation creation
            $this->logger->info('Conversation created successfully', [
                'conversation_id' => $conversationId,
                'session_id' => $args['session_id'],
                'user_id' => $args['user_id']
            ]);

            do_action('woo_ai_conversation_created', $conversationId, $args);

            return $conversationResult;
        } catch (\Exception $e) {
            $this->logger->error('Exception creating conversation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Get conversation by ID with caching
     *
     * @param int $conversationId Conversation ID
     * @param bool $includeMessages Whether to include message history
     *
     * @return array|null Conversation data or null if not found
     */
    public function getConversation(int $conversationId, bool $includeMessages = false): ?array
    {
        // Try cache first
        $cacheKey = self::CACHE_CONVERSATION_PREFIX . $conversationId;
        $conversation = $this->cache->get($cacheKey);

        if ($conversation === false) {
            // Query database
            $conversation = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->wpdb->prefix}woo_ai_conversations WHERE id = %d",
                    $conversationId
                ),
                ARRAY_A
            );

            if (!$conversation) {
                return null;
            }

            // Parse context data
            $conversation['context'] = json_decode($conversation['context_data'] ?: '{}', true);
            unset($conversation['context_data']);

            // Cache the result
            $this->cache->set($cacheKey, $conversation, HOUR_IN_SECONDS);
        }

        // Include messages if requested
        if ($includeMessages) {
            $conversation['messages'] = $this->getConversationMessages($conversationId);
        }

        return $conversation;
    }

    /**
     * Get conversation by session ID
     *
     * @param string $sessionId Session identifier
     * @param string $status Filter by conversation status (optional)
     *
     * @return array|null Most recent conversation data or null if not found
     */
    public function getConversationBySession(string $sessionId, string $status = self::STATUS_ACTIVE): ?array
    {
        $query = "SELECT * FROM {$this->wpdb->prefix}woo_ai_conversations 
                  WHERE session_id = %s";

        $queryArgs = [$sessionId];

        if ($status) {
            $query .= " AND status = %s";
            $queryArgs[] = $status;
        }

        $query .= " ORDER BY created_at DESC LIMIT 1";

        $conversation = $this->wpdb->get_row(
            $this->wpdb->prepare($query, $queryArgs),
            ARRAY_A
        );

        if (!$conversation) {
            return null;
        }

        // Parse context data
        $conversation['context'] = json_decode($conversation['context_data'] ?: '{}', true);
        unset($conversation['context_data']);

        return $conversation;
    }

    /**
     * Add a message to an existing conversation
     *
     * @param int $conversationId Conversation ID
     * @param string $role Message role (user, assistant, system)
     * @param string $content Message content
     * @param array $metadata Optional message metadata
     * @param array $metadata['tokens_used'] Number of tokens used
     * @param array $metadata['processing_time_ms'] Processing time in milliseconds
     * @param array $metadata['model_used'] AI model used for response
     * @param array $metadata['temperature'] AI temperature setting
     * @param array $metadata['error_message'] Error message if any
     *
     * @return int|false Message ID or false on failure
     */
    public function addMessage(int $conversationId, string $role, string $content, array $metadata = []): int|false
    {
        try {
            // Validate role
            if (!in_array($role, [self::ROLE_USER, self::ROLE_ASSISTANT, self::ROLE_SYSTEM])) {
                throw new \InvalidArgumentException('Invalid message role');
            }

            // Verify conversation exists
            $conversation = $this->getConversation($conversationId);
            if (!$conversation) {
                throw new \InvalidArgumentException('Conversation not found');
            }

            // Prepare message data
            $messageData = [
                'conversation_id' => $conversationId,
                'role' => $role,
                'content' => $content,
                'metadata' => json_encode($metadata),
                'created_at' => current_time('mysql'),
                'tokens_used' => $metadata['tokens_used'] ?? null,
                'processing_time_ms' => $metadata['processing_time_ms'] ?? null,
                'model_used' => $metadata['model_used'] ?? null,
                'temperature' => $metadata['temperature'] ?? null,
                'error_message' => $metadata['error_message'] ?? null
            ];

            // Insert message
            $result = $this->wpdb->insert(
                $this->wpdb->prefix . 'woo_ai_messages',
                $messageData,
                ['%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%f', '%s']
            );

            if ($result === false) {
                $this->logger->error('Failed to add message', [
                    'conversation_id' => $conversationId,
                    'role' => $role,
                    'error' => $this->wpdb->last_error
                ]);
                return false;
            }

            $messageId = $this->wpdb->insert_id;

            // Update conversation message count and last activity
            $this->updateConversationActivity($conversationId);

            // Clear cached conversation data to force refresh
            $this->cache->delete(self::CACHE_CONVERSATION_PREFIX . $conversationId);

            // Log message addition
            $this->logger->debug('Message added to conversation', [
                'message_id' => $messageId,
                'conversation_id' => $conversationId,
                'role' => $role,
                'content_length' => strlen($content)
            ]);

            do_action('woo_ai_message_added', $messageId, $conversationId, $role);

            return $messageId;
        } catch (\Exception $e) {
            $this->logger->error('Exception adding message', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Get messages for a conversation
     *
     * @param int $conversationId Conversation ID
     * @param int $limit Maximum number of messages to retrieve
     * @param int $offset Number of messages to skip
     *
     * @return array Array of message objects
     */
    public function getConversationMessages(int $conversationId, int $limit = 50, int $offset = 0): array
    {
        $messages = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}woo_ai_messages 
                 WHERE conversation_id = %d 
                 ORDER BY created_at ASC 
                 LIMIT %d OFFSET %d",
                $conversationId,
                $limit,
                $offset
            ),
            ARRAY_A
        );

        if (!$messages) {
            return [];
        }

        // Parse metadata for each message
        foreach ($messages as &$message) {
            $message['metadata'] = json_decode($message['metadata'] ?: '{}', true);
        }

        return $messages;
    }

    /**
     * Update conversation context data
     *
     * @param int $conversationId Conversation ID
     * @param array $contextData New context data (will be merged with existing)
     * @param bool $replace Whether to replace existing context or merge
     *
     * @return bool Success status
     */
    public function updateConversationContext(int $conversationId, array $contextData, bool $replace = false): bool
    {
        try {
            $conversation = $this->getConversation($conversationId);
            if (!$conversation) {
                return false;
            }

            // Merge or replace context
            if ($replace) {
                $newContext = $contextData;
            } else {
                $newContext = array_merge($conversation['context'] ?? [], $contextData);
            }

            // Validate context size
            $contextJson = json_encode($newContext);
            if (strlen($contextJson) > self::MAX_CONTEXT_SIZE) {
                $this->logger->warning('Context update exceeds size limit', [
                    'conversation_id' => $conversationId,
                    'size' => strlen($contextJson)
                ]);
                return false;
            }

            // Update database
            $result = $this->wpdb->update(
                $this->wpdb->prefix . 'woo_ai_conversations',
                [
                    'context_data' => $contextJson,
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $conversationId],
                ['%s', '%s'],
                ['%d']
            );

            if ($result === false) {
                $this->logger->error('Failed to update conversation context', [
                    'conversation_id' => $conversationId,
                    'error' => $this->wpdb->last_error
                ]);
                return false;
            }

            // Clear cache
            $this->cache->delete(self::CACHE_CONVERSATION_PREFIX . $conversationId);

            $this->logger->debug('Conversation context updated', [
                'conversation_id' => $conversationId,
                'context_keys' => array_keys($newContext)
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Exception updating conversation context', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Update conversation status
     *
     * @param int $conversationId Conversation ID
     * @param string $status New status
     * @param array $additionalData Additional data to update
     *
     * @return bool Success status
     */
    public function updateConversationStatus(int $conversationId, string $status, array $additionalData = []): bool
    {
        try {
            $allowedStatuses = [self::STATUS_ACTIVE, self::STATUS_COMPLETED, self::STATUS_ABANDONED, self::STATUS_HANDOFF];

            if (!in_array($status, $allowedStatuses)) {
                throw new \InvalidArgumentException('Invalid conversation status');
            }

            $updateData = array_merge([
                'status' => $status,
                'updated_at' => current_time('mysql')
            ], $additionalData);

            $result = $this->wpdb->update(
                $this->wpdb->prefix . 'woo_ai_conversations',
                $updateData,
                ['id' => $conversationId],
                array_fill(0, count($updateData), '%s'),
                ['%d']
            );

            if ($result === false) {
                $this->logger->error('Failed to update conversation status', [
                    'conversation_id' => $conversationId,
                    'status' => $status,
                    'error' => $this->wpdb->last_error
                ]);
                return false;
            }

            // Clear cache
            $this->cache->delete(self::CACHE_CONVERSATION_PREFIX . $conversationId);

            $this->logger->info('Conversation status updated', [
                'conversation_id' => $conversationId,
                'status' => $status
            ]);

            do_action('woo_ai_conversation_status_changed', $conversationId, $status);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Exception updating conversation status', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get active conversations for a session
     *
     * @param string $sessionId Session identifier
     * @param int $limit Maximum number of conversations to retrieve
     *
     * @return array Array of conversation objects
     */
    public function getSessionConversations(string $sessionId, int $limit = 10): array
    {
        $conversations = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}woo_ai_conversations 
                 WHERE session_id = %s 
                 ORDER BY created_at DESC 
                 LIMIT %d",
                $sessionId,
                $limit
            ),
            ARRAY_A
        );

        if (!$conversations) {
            return [];
        }

        // Parse context data for each conversation
        foreach ($conversations as &$conversation) {
            $conversation['context'] = json_decode($conversation['context_data'] ?: '{}', true);
            unset($conversation['context_data']);
        }

        return $conversations;
    }

    /**
     * Clean up old conversations and sessions
     *
     * @param int $olderThanHours Remove conversations older than this many hours
     * @param array $statuses Conversation statuses to clean up
     *
     * @return int Number of conversations cleaned up
     */
    public function cleanupOldConversations(int $olderThanHours = 48, array $statuses = ['abandoned', 'completed']): int
    {
        try {
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$olderThanHours} hours"));

            $statusPlaceholders = implode(',', array_fill(0, count($statuses), '%s'));

            $query = $this->wpdb->prepare(
                "DELETE FROM {$this->wpdb->prefix}woo_ai_conversations 
                 WHERE updated_at < %s AND status IN ({$statusPlaceholders})",
                array_merge([$cutoffDate], $statuses)
            );

            $deletedCount = $this->wpdb->query($query);

            if ($deletedCount > 0) {
                $this->logger->info('Old conversations cleaned up', [
                    'deleted_count' => $deletedCount,
                    'cutoff_date' => $cutoffDate,
                    'statuses' => $statuses
                ]);
            }

            return $deletedCount ?: 0;
        } catch (\Exception $e) {
            $this->logger->error('Exception during conversation cleanup', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Generate a unique session ID
     *
     * @return string Session ID
     */
    private function generateSessionId(): string
    {
        return 'sess_' . uniqid() . '_' . wp_generate_password(16, false);
    }

    /**
     * Validate session limits
     *
     * @param string $sessionId Session identifier
     *
     * @return bool Whether the session can create more conversations
     */
    private function validateSessionLimits(string $sessionId): bool
    {
        $count = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->wpdb->prefix}woo_ai_conversations 
                 WHERE session_id = %s AND created_at > %s",
                $sessionId,
                date('Y-m-d H:i:s', strtotime('-' . self::SESSION_TIMEOUT_MINUTES . ' minutes'))
            )
        );

        return $count < self::MAX_CONVERSATIONS_PER_SESSION;
    }

    /**
     * Update session conversation count cache
     *
     * @param string $sessionId Session identifier
     *
     * @return void
     */
    private function updateSessionConversationCount(string $sessionId): void
    {
        $cacheKey = self::CACHE_SESSION_PREFIX . $sessionId;
        $currentCount = $this->cache->get($cacheKey) ?: 0;
        $this->cache->set($cacheKey, $currentCount + 1, HOUR_IN_SECONDS);
    }

    /**
     * Update conversation activity and message count
     *
     * @param int $conversationId Conversation ID
     *
     * @return void
     */
    private function updateConversationActivity(int $conversationId): void
    {
        $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$this->wpdb->prefix}woo_ai_conversations 
                 SET updated_at = %s, total_messages = total_messages + 1 
                 WHERE id = %d",
                current_time('mysql'),
                $conversationId
            )
        );
    }
}
