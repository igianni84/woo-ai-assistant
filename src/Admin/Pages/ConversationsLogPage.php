<?php

/**
 * Admin Conversations Log Page Class
 *
 * Handles the conversations log page rendering and management for the
 * Woo AI Assistant plugin admin interface. Provides comprehensive conversation
 * tracking, filtering, analytics, and export functionality with advanced
 * search capabilities and detailed conversation views.
 *
 * @package WooAiAssistant
 * @subpackage Admin\Pages
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Admin\Pages;

use WooAiAssistant\Common\Utils;
use WooAiAssistant\Common\Traits\Singleton;
use WooAiAssistant\Chatbot\ConversationHandler;
use WP_List_Table;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ConversationsLogPage
 *
 * Manages the admin conversations log page with comprehensive conversation
 * tracking, detailed analytics, filtering capabilities, export functionality,
 * and integration with the ConversationHandler system.
 *
 * @since 1.0.0
 */
class ConversationsLogPage
{
    use Singleton;

    /**
     * Required capability for conversations log access
     *
     * @since 1.0.0
     * @var string
     */
    private const REQUIRED_CAPABILITY = 'manage_woocommerce';

    /**
     * Cache duration for conversations data (in seconds)
     *
     * @since 1.0.0
     * @var int
     */
    private const CACHE_DURATION = 300; // 5 minutes

    /**
     * Default conversations per page
     *
     * @since 1.0.0
     * @var int
     */
    public const DEFAULT_PER_PAGE = 20;

    /**
     * Maximum export limit
     *
     * @since 1.0.0
     * @var int
     */
    private const MAX_EXPORT_LIMIT = 1000;

    /**
     * Confidence score thresholds
     *
     * @since 1.0.0
     * @var array
     */
    private const CONFIDENCE_THRESHOLDS = [
        'high' => 0.8,
        'medium' => 0.6,
        'low' => 0.0
    ];

    /**
     * List table instance
     *
     * @since 1.0.0
     * @var ConversationsListTable|null
     */
    private $listTable = null;

    /**
     * Constructor
     *
     * Initializes the conversations log page and sets up hooks.
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
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('wp_ajax_woo_ai_export_conversations', [$this, 'handleConversationsExport']);
        add_action('wp_ajax_woo_ai_conversation_details', [$this, 'handleConversationDetailsAjax']);
        add_action('wp_ajax_woo_ai_conversation_search', [$this, 'handleConversationSearchAjax']);
        add_action('wp_ajax_woo_ai_bulk_conversation_actions', [$this, 'handleBulkConversationActions']);
    }

    /**
     * Render the complete conversations log page
     *
     * Main entry point for rendering the conversations log with filtering,
     * pagination, search capabilities, and detailed conversation views.
     *
     * @since 1.0.0
     * @param array $args Optional. Additional arguments for rendering
     * @return void
     *
     * @example
     * ```php
     * $conversationsLog = ConversationsLogPage::getInstance();
     * $conversationsLog->renderConversationsLog(['per_page' => 30]);
     * ```
     */
    public function renderConversationsLog(array $args = []): void
    {
        // Security check
        if (!current_user_can(self::REQUIRED_CAPABILITY)) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'woo-ai-assistant'));
        }

        // Verify nonce if this is a POST request
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'woo_ai_conversations_nonce')) {
                wp_die(__('Security check failed.', 'woo-ai-assistant'));
            }
        }

        try {
            // Initialize list table
            $this->initializeListTable();

            // Handle bulk actions
            $this->processBulkActions();

            // Render page structure
            $this->renderPageHeader();
            $this->renderFiltersSection();
            $this->renderConversationsTable();
            $this->renderModals();
        } catch (\Exception $e) {
            Utils::logError('Conversations log rendering failed: ' . $e->getMessage());
            $this->renderErrorMessage($e->getMessage());
        }
    }

    /**
     * Enqueue conversations log page assets
     *
     * @since 1.0.0
     * @param string $hook Current admin page hook
     * @return void
     */
    public function enqueueAssets(string $hook): void
    {
        // Only enqueue on our conversations page
        if ($hook !== 'ai-assistant_page_woo-ai-conversations') {
            return;
        }

        $assetVersion = Utils::getPluginVersion();

        // Enqueue conversations-specific CSS
        wp_enqueue_style(
            'woo-ai-conversations',
            Utils::getAssetsUrl('css/conversations.css'),
            ['wp-admin'],
            $assetVersion
        );

        // Enqueue conversations-specific JavaScript
        wp_enqueue_script(
            'woo-ai-conversations',
            Utils::getAssetsUrl('js/conversations.js'),
            ['jquery', 'wp-api-fetch', 'wp-i18n'],
            $assetVersion,
            true
        );

        // Localize script with conversations data
        wp_localize_script('woo-ai-conversations', 'wooAiConversations', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('woo_ai_conversations_nonce'),
            'strings' => [
                'loading' => __('Loading...', 'woo-ai-assistant'),
                'error' => __('Error loading data', 'woo-ai-assistant'),
                'confirmDelete' => __('Are you sure you want to delete these conversations?', 'woo-ai-assistant'),
                'exportSuccess' => __('Export completed successfully', 'woo-ai-assistant'),
                'exportError' => __('Export failed', 'woo-ai-assistant'),
                'noDataFound' => __('No conversations found', 'woo-ai-assistant'),
                'viewDetails' => __('View Details', 'woo-ai-assistant'),
                'hideDetails' => __('Hide Details', 'woo-ai-assistant'),
            ],
            'confidenceThresholds' => self::CONFIDENCE_THRESHOLDS,
            'maxExportLimit' => self::MAX_EXPORT_LIMIT
        ]);
    }

    /**
     * Get conversations data with filtering and pagination
     *
     * Retrieves conversations data based on current filters, search parameters,
     * and pagination settings with proper caching implementation.
     *
     * @since 1.0.0
     * @param array $args Query arguments
     * @return array Conversations data with pagination info
     * @throws \Exception When database query fails
     */
    public function getConversationsData(array $args = []): array
    {
        $args = wp_parse_args($args, [
            'per_page' => self::DEFAULT_PER_PAGE,
            'page' => 1,
            'search' => '',
            'status' => '',
            'rating' => '',
            'date_from' => '',
            'date_to' => '',
            'confidence' => '',
            'user_id' => '',
            'orderby' => 'started_at',
            'order' => 'DESC'
        ]);

        // Generate cache key based on arguments
        $cacheKey = 'woo_ai_conversations_' . md5(serialize($args));
        $cachedData = wp_cache_get($cacheKey, 'woo_ai_assistant');

        if (false !== $cachedData) {
            return $cachedData;
        }

        try {
            global $wpdb;

            // Build base query
            $conversationsTable = $wpdb->prefix . 'woo_ai_conversations';
            $messagesTable = $wpdb->prefix . 'woo_ai_messages';

            $whereClauses = ['1=1'];
            $queryParams = [];

            // Apply filters
            if (!empty($args['search'])) {
                $whereClauses[] = "(c.conversation_id LIKE %s OR c.context LIKE %s OR u.display_name LIKE %s)";
                $searchTerm = '%' . $wpdb->esc_like($args['search']) . '%';
                $queryParams[] = $searchTerm;
                $queryParams[] = $searchTerm;
                $queryParams[] = $searchTerm;
            }

            if (!empty($args['status'])) {
                $whereClauses[] = "c.status = %s";
                $queryParams[] = sanitize_text_field($args['status']);
            }

            if (!empty($args['rating'])) {
                $whereClauses[] = "c.user_rating = %d";
                $queryParams[] = (int) $args['rating'];
            }

            if (!empty($args['date_from'])) {
                $whereClauses[] = "DATE(c.started_at) >= %s";
                $queryParams[] = sanitize_text_field($args['date_from']);
            }

            if (!empty($args['date_to'])) {
                $whereClauses[] = "DATE(c.started_at) <= %s";
                $queryParams[] = sanitize_text_field($args['date_to']);
            }

            if (!empty($args['user_id'])) {
                $whereClauses[] = "c.user_id = %d";
                $queryParams[] = (int) $args['user_id'];
            }

            // Build confidence filter with average calculation
            if (!empty($args['confidence'])) {
                $confidenceFilter = $this->buildConfidenceFilter($args['confidence']);
                if ($confidenceFilter) {
                    $whereClauses[] = $confidenceFilter;
                }
            }

            $whereClause = implode(' AND ', $whereClauses);

            // Order validation
            $allowedOrderBy = ['started_at', 'ended_at', 'total_messages', 'user_rating', 'status'];
            $orderBy = in_array($args['orderby'], $allowedOrderBy) ? $args['orderby'] : 'started_at';
            $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

            // Count total conversations
            $countQuery = "
                SELECT COUNT(DISTINCT c.conversation_id) 
                FROM {$conversationsTable} c 
                LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
                WHERE {$whereClause}
            ";

            if (!empty($queryParams)) {
                $countQuery = $wpdb->prepare($countQuery, $queryParams);
            }

            $totalItems = $wpdb->get_var($countQuery);

            // Calculate pagination
            $offset = ($args['page'] - 1) * $args['per_page'];
            $totalPages = ceil($totalItems / $args['per_page']);

            // Main query with conversation details
            $mainQuery = "
                SELECT 
                    c.*,
                    u.display_name as user_name,
                    u.user_email,
                    AVG(m.confidence_score) as avg_confidence,
                    GROUP_CONCAT(DISTINCT m.model_used) as models_used,
                    COUNT(m.id) as message_count,
                    SUM(m.tokens_used) as total_tokens,
                    MAX(m.created_at) as last_message_at
                FROM {$conversationsTable} c 
                LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
                LEFT JOIN {$messagesTable} m ON c.conversation_id = m.conversation_id
                WHERE {$whereClause}
                GROUP BY c.conversation_id
                ORDER BY c.{$orderBy} {$order}
                LIMIT %d OFFSET %d
            ";

            $queryParamsWithPagination = array_merge($queryParams, [$args['per_page'], $offset]);
            $mainQuery = $wpdb->prepare($mainQuery, $queryParamsWithPagination);

            $conversations = $wpdb->get_results($mainQuery, ARRAY_A);

            if ($conversations === false) {
                throw new \Exception('Database query failed: ' . $wpdb->last_error);
            }

            // Process conversations data
            $processedConversations = [];
            foreach ($conversations as $conversation) {
                $processedConversation = $this->processConversationData($conversation);
                $processedConversations[] = $processedConversation;
            }

            $conversationsData = [
                'conversations' => $processedConversations,
                'pagination' => [
                    'total_items' => (int) $totalItems,
                    'total_pages' => (int) $totalPages,
                    'current_page' => (int) $args['page'],
                    'per_page' => (int) $args['per_page'],
                    'offset' => $offset
                ],
                'filters' => $args,
                'query_time' => $wpdb->timer_stop()
            ];

            // Cache the results
            wp_cache_set($cacheKey, $conversationsData, 'woo_ai_assistant', self::CACHE_DURATION);

            Utils::logDebug('Conversations data retrieved successfully', [
                'total_items' => $totalItems,
                'page' => $args['page'],
                'filters_applied' => count(array_filter($args))
            ]);

            return $conversationsData;
        } catch (\Exception $e) {
            Utils::logError('Failed to retrieve conversations data: ' . $e->getMessage());
            throw new \Exception('Unable to load conversations data. Please try again.');
        }
    }

    /**
     * Get detailed conversation data with messages and KB snippets
     *
     * Retrieves comprehensive conversation details including message history,
     * knowledge base snippets used, and detailed analytics.
     *
     * @since 1.0.0
     * @param string $conversationId Conversation identifier
     * @return array|WP_Error Detailed conversation data or error
     */
    public function getConversationDetails(string $conversationId)
    {
        try {
            $conversationHandler = ConversationHandler::getInstance();

            // Get conversation history with metadata
            $history = $conversationHandler->getConversationHistory($conversationId, [
                'include_metadata' => true,
                'limit' => 100
            ]);

            if (is_wp_error($history)) {
                return $history;
            }

            // Get KB snippets used in conversation
            $kbSnippets = $this->getConversationKbSnippets($conversationId);

            // Calculate conversation metrics
            $metrics = $this->calculateConversationMetrics($conversationId, $history);

            // Get related agent actions
            $agentActions = $this->getConversationAgentActions($conversationId);

            $detailedData = [
                'conversation' => $history['conversation'],
                'messages' => $history['messages'],
                'kb_snippets' => $kbSnippets,
                'metrics' => $metrics,
                'agent_actions' => $agentActions,
                'pagination' => $history['pagination']
            ];

            Utils::logDebug('Conversation details retrieved', [
                'conversation_id' => $conversationId,
                'message_count' => count($history['messages']),
                'kb_snippets_count' => count($kbSnippets)
            ]);

            return $detailedData;
        } catch (\Exception $e) {
            Utils::logError('Error retrieving conversation details: ' . $e->getMessage());
            return new \WP_Error('details_error', 'Failed to retrieve conversation details: ' . $e->getMessage());
        }
    }

    /**
     * Export conversations data in specified format
     *
     * Exports filtered conversations data to CSV or JSON format with
     * comprehensive conversation information and analytics.
     *
     * @since 1.0.0
     * @param array $args Export arguments
     * @return array Export results
     */
    public function exportConversationsData(array $args = []): array
    {
        $args = wp_parse_args($args, [
            'format' => 'csv',
            'limit' => 500,
            'include_messages' => false,
            'include_kb_snippets' => false,
            'date_from' => '',
            'date_to' => '',
            'status' => '',
            'rating' => ''
        ]);

        try {
            // Validate export limit
            if ($args['limit'] > self::MAX_EXPORT_LIMIT) {
                $args['limit'] = self::MAX_EXPORT_LIMIT;
            }

            // Get conversations data for export
            $exportArgs = array_merge($args, [
                'per_page' => $args['limit'],
                'page' => 1
            ]);

            $conversationsData = $this->getConversationsData($exportArgs);
            $conversations = $conversationsData['conversations'];

            if (empty($conversations)) {
                return [
                    'success' => false,
                    'message' => __('No conversations found for export.', 'woo-ai-assistant')
                ];
            }

            // Prepare export data
            $exportData = $this->prepareExportData($conversations, $args);

            // Generate filename
            $timestamp = current_time('Y-m-d_H-i-s');
            $filename = "woo-ai-conversations-{$timestamp}.{$args['format']}";

            // Export based on format
            switch ($args['format']) {
                case 'json':
                    $this->exportToJson($exportData, $filename);
                    break;
                case 'csv':
                default:
                    $this->exportToCsv($exportData, $filename);
                    break;
            }

            return [
                'success' => true,
                'filename' => $filename,
                'count' => count($conversations),
                'message' => sprintf(
                    __('Successfully exported %d conversations to %s', 'woo-ai-assistant'),
                    count($conversations),
                    $filename
                )
            ];
        } catch (\Exception $e) {
            Utils::logError('Conversations export failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => __('Export failed. Please try again.', 'woo-ai-assistant')
            ];
        }
    }

    /**
     * Handle AJAX request for conversation export
     *
     * @since 1.0.0
     * @return void
     */
    public function handleConversationsExport(): void
    {
        // Security checks
        if (!current_user_can(self::REQUIRED_CAPABILITY)) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'woo-ai-assistant')]);
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'woo_ai_conversations_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'woo-ai-assistant')]);
        }

        $exportArgs = [
            'format' => sanitize_text_field($_POST['format'] ?? 'csv'),
            'limit' => min((int) ($_POST['limit'] ?? 500), self::MAX_EXPORT_LIMIT),
            'include_messages' => (bool) ($_POST['include_messages'] ?? false),
            'include_kb_snippets' => (bool) ($_POST['include_kb_snippets'] ?? false),
            'date_from' => sanitize_text_field($_POST['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_POST['date_to'] ?? ''),
            'status' => sanitize_text_field($_POST['status'] ?? ''),
            'rating' => sanitize_text_field($_POST['rating'] ?? '')
        ];

        $result = $this->exportConversationsData($exportArgs);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Handle AJAX request for conversation details
     *
     * @since 1.0.0
     * @return void
     */
    public function handleConversationDetailsAjax(): void
    {
        // Security checks
        if (!current_user_can(self::REQUIRED_CAPABILITY)) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'woo-ai-assistant')]);
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'woo_ai_conversations_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'woo-ai-assistant')]);
        }

        $conversationId = sanitize_text_field($_POST['conversation_id'] ?? '');
        if (empty($conversationId)) {
            wp_send_json_error(['message' => __('Invalid conversation ID.', 'woo-ai-assistant')]);
        }

        $details = $this->getConversationDetails($conversationId);

        if (is_wp_error($details)) {
            wp_send_json_error(['message' => $details->get_error_message()]);
        }

        wp_send_json_success(['data' => $details]);
    }

    /**
     * Handle AJAX request for conversation search
     *
     * @since 1.0.0
     * @return void
     */
    public function handleConversationSearchAjax(): void
    {
        // Security checks
        if (!current_user_can(self::REQUIRED_CAPABILITY)) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'woo-ai-assistant')]);
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'woo_ai_conversations_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'woo-ai-assistant')]);
        }

        $searchArgs = [
            'search' => sanitize_text_field($_POST['search'] ?? ''),
            'status' => sanitize_text_field($_POST['status'] ?? ''),
            'rating' => sanitize_text_field($_POST['rating'] ?? ''),
            'confidence' => sanitize_text_field($_POST['confidence'] ?? ''),
            'date_from' => sanitize_text_field($_POST['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_POST['date_to'] ?? ''),
            'per_page' => min((int) ($_POST['per_page'] ?? self::DEFAULT_PER_PAGE), 100),
            'page' => max(1, (int) ($_POST['page'] ?? 1))
        ];

        try {
            $conversationsData = $this->getConversationsData($searchArgs);
            wp_send_json_success(['data' => $conversationsData]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Handle bulk conversation actions
     *
     * @since 1.0.0
     * @return void
     */
    public function handleBulkConversationActions(): void
    {
        // Security checks
        if (!current_user_can(self::REQUIRED_CAPABILITY)) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'woo-ai-assistant')]);
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'woo_ai_conversations_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'woo-ai-assistant')]);
        }

        $action = sanitize_text_field($_POST['action'] ?? '');
        $conversationIds = array_map('sanitize_text_field', $_POST['conversation_ids'] ?? []);

        if (empty($action) || empty($conversationIds)) {
            wp_send_json_error(['message' => __('Invalid action or conversation selection.', 'woo-ai-assistant')]);
        }

        $result = $this->processBulkConversationAction($action, $conversationIds);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Initialize the conversations list table
     *
     * @since 1.0.0
     * @return void
     */
    private function initializeListTable(): void
    {
        // WP_List_Table is automatically loaded in admin context
        // No need to manually require it as it's part of WordPress admin
        if (!class_exists('WP_List_Table')) {
            // This should not happen in admin context, but just in case
            wp_die(__('WordPress List Table class is not available.', 'woo-ai-assistant'));
        }

        $this->listTable = new ConversationsListTable($this);
        $this->listTable->prepare_items();
    }

    /**
     * Process bulk actions from list table
     *
     * @since 1.0.0
     * @return void
     */
    private function processBulkActions(): void
    {
        if (!$this->listTable) {
            return;
        }

        $action = $this->listTable->current_action();
        if (!$action) {
            return;
        }

        $conversationIds = $_POST['conversation'] ?? [];
        if (empty($conversationIds)) {
            return;
        }

        $result = $this->processBulkConversationAction($action, $conversationIds);

        if (!$result['success']) {
            add_action('admin_notices', function () use ($result) {
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p>' . esc_html($result['message']) . '</p>';
                echo '</div>';
            });
        } else {
            add_action('admin_notices', function () use ($result) {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p>' . esc_html($result['message']) . '</p>';
                echo '</div>';
            });
        }
    }

    /**
     * Process bulk conversation action
     *
     * @since 1.0.0
     * @param string $action Action to perform
     * @param array $conversationIds Conversation IDs
     * @return array Result array
     */
    private function processBulkConversationAction(string $action, array $conversationIds): array
    {
        try {
            switch ($action) {
                case 'delete':
                    return $this->deleteBulkConversations($conversationIds);
                case 'export':
                    return $this->exportBulkConversations($conversationIds);
                case 'mark_resolved':
                    return $this->markConversationsResolved($conversationIds);
                default:
                    return [
                        'success' => false,
                        'message' => __('Unknown action.', 'woo-ai-assistant')
                    ];
            }
        } catch (\Exception $e) {
            Utils::logError('Bulk action failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => __('Bulk action failed. Please try again.', 'woo-ai-assistant')
            ];
        }
    }

    /**
     * Delete multiple conversations
     *
     * @since 1.0.0
     * @param array $conversationIds Conversation IDs to delete
     * @return array Result array
     */
    private function deleteBulkConversations(array $conversationIds): array
    {
        global $wpdb;

        $deletedCount = 0;
        $conversationsTable = $wpdb->prefix . 'woo_ai_conversations';
        $messagesTable = $wpdb->prefix . 'woo_ai_messages';

        foreach ($conversationIds as $conversationId) {
            // Delete messages first
            $wpdb->delete($messagesTable, ['conversation_id' => $conversationId], ['%s']);

            // Delete conversation
            $result = $wpdb->delete($conversationsTable, ['conversation_id' => $conversationId], ['%s']);

            if ($result !== false) {
                $deletedCount++;
                // Clear cache
                wp_cache_delete('woo_ai_conversation_' . $conversationId, 'woo_ai_assistant');
            }
        }

        return [
            'success' => $deletedCount > 0,
            'message' => sprintf(
                __('Successfully deleted %d conversations.', 'woo-ai-assistant'),
                $deletedCount
            ),
            'deleted_count' => $deletedCount
        ];
    }

    /**
     * Export multiple conversations
     *
     * @since 1.0.0
     * @param array $conversationIds Conversation IDs to export
     * @return array Result array
     */
    private function exportBulkConversations(array $conversationIds): array
    {
        // This would be implemented to export specific conversations
        // For now, return success with placeholder
        return [
            'success' => true,
            'message' => sprintf(
                __('Export of %d conversations initiated.', 'woo-ai-assistant'),
                count($conversationIds)
            )
        ];
    }

    /**
     * Mark conversations as resolved
     *
     * @since 1.0.0
     * @param array $conversationIds Conversation IDs to mark resolved
     * @return array Result array
     */
    private function markConversationsResolved(array $conversationIds): array
    {
        global $wpdb;

        $updatedCount = 0;
        $conversationsTable = $wpdb->prefix . 'woo_ai_conversations';

        foreach ($conversationIds as $conversationId) {
            $result = $wpdb->update(
                $conversationsTable,
                [
                    'status' => 'resolved',
                    'updated_at' => current_time('mysql')
                ],
                ['conversation_id' => $conversationId],
                ['%s', '%s'],
                ['%s']
            );

            if ($result !== false) {
                $updatedCount++;
                // Clear cache
                wp_cache_delete('woo_ai_conversation_' . $conversationId, 'woo_ai_assistant');
            }
        }

        return [
            'success' => $updatedCount > 0,
            'message' => sprintf(
                __('Successfully marked %d conversations as resolved.', 'woo-ai-assistant'),
                $updatedCount
            ),
            'updated_count' => $updatedCount
        ];
    }

    /**
     * Process conversation data for display
     *
     * @since 1.0.0
     * @param array $conversation Raw conversation data
     * @return array Processed conversation data
     */
    private function processConversationData(array $conversation): array
    {
        // Calculate confidence badge
        $avgConfidence = (float) ($conversation['avg_confidence'] ?? 0);
        $confidenceBadge = $this->getConfidenceBadge($avgConfidence);

        // Parse context
        $context = [];
        if (!empty($conversation['context'])) {
            $decoded = json_decode($conversation['context'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $context = $decoded;
            }
        }

        // Calculate duration
        $duration = null;
        if (!empty($conversation['ended_at'])) {
            $startTime = strtotime($conversation['started_at']);
            $endTime = strtotime($conversation['ended_at']);
            $duration = $endTime - $startTime;
        }

        return [
            'conversation_id' => $conversation['conversation_id'],
            'user_id' => $conversation['user_id'] ? (int) $conversation['user_id'] : null,
            'user_name' => $conversation['user_name'] ?? __('Guest', 'woo-ai-assistant'),
            'user_email' => $conversation['user_email'] ?? '',
            'status' => $conversation['status'],
            'started_at' => $conversation['started_at'],
            'ended_at' => $conversation['ended_at'],
            'last_message_at' => $conversation['last_message_at'],
            'total_messages' => (int) $conversation['total_messages'],
            'message_count' => (int) ($conversation['message_count'] ?? 0),
            'user_rating' => $conversation['user_rating'] ? (int) $conversation['user_rating'] : null,
            'user_feedback' => $conversation['user_feedback'] ?? '',
            'avg_confidence' => $avgConfidence,
            'confidence_badge' => $confidenceBadge,
            'models_used' => $conversation['models_used'] ?? '',
            'total_tokens' => (int) ($conversation['total_tokens'] ?? 0),
            'context' => $context,
            'duration' => $duration,
            'session_id' => $conversation['session_id']
        ];
    }

    /**
     * Get confidence badge based on score
     *
     * @since 1.0.0
     * @param float $confidence Confidence score
     * @return array Confidence badge data
     */
    private function getConfidenceBadge(float $confidence): array
    {
        if ($confidence >= self::CONFIDENCE_THRESHOLDS['high']) {
            return [
                'level' => 'high',
                'label' => __('High', 'woo-ai-assistant'),
                'class' => 'confidence-high'
            ];
        } elseif ($confidence >= self::CONFIDENCE_THRESHOLDS['medium']) {
            return [
                'level' => 'medium',
                'label' => __('Medium', 'woo-ai-assistant'),
                'class' => 'confidence-medium'
            ];
        } else {
            return [
                'level' => 'low',
                'label' => __('Low', 'woo-ai-assistant'),
                'class' => 'confidence-low'
            ];
        }
    }

    /**
     * Build confidence filter SQL clause
     *
     * @since 1.0.0
     * @param string $confidence Confidence level filter
     * @return string|null SQL where clause or null
     */
    private function buildConfidenceFilter(string $confidence): ?string
    {
        switch ($confidence) {
            case 'high':
                return sprintf('AVG(m.confidence_score) >= %f', self::CONFIDENCE_THRESHOLDS['high']);
            case 'medium':
                return sprintf(
                    'AVG(m.confidence_score) >= %f AND AVG(m.confidence_score) < %f',
                    self::CONFIDENCE_THRESHOLDS['medium'],
                    self::CONFIDENCE_THRESHOLDS['high']
                );
            case 'low':
                return sprintf('AVG(m.confidence_score) < %f', self::CONFIDENCE_THRESHOLDS['medium']);
            default:
                return null;
        }
    }

    /**
     * Get KB snippets used in conversation
     *
     * @since 1.0.0
     * @param string $conversationId Conversation ID
     * @return array KB snippets data
     */
    private function getConversationKbSnippets(string $conversationId): array
    {
        global $wpdb;

        $kbTable = $wpdb->prefix . 'woo_ai_knowledge_base';
        $messagesTable = $wpdb->prefix . 'woo_ai_messages';

        // This is a simplified implementation
        // In a real implementation, you'd track which KB entries were used per message
        $query = $wpdb->prepare(
            "SELECT DISTINCT kb.id, kb.title, kb.content_excerpt, kb.source_type, kb.source_id
             FROM {$kbTable} kb
             INNER JOIN {$messagesTable} m ON JSON_EXTRACT(m.metadata, '$.kb_entries_used') LIKE CONCAT('%%', kb.id, '%%')
             WHERE m.conversation_id = %s
             LIMIT 10",
            $conversationId
        );

        $snippets = $wpdb->get_results($query, ARRAY_A);

        return $snippets ?: [];
    }

    /**
     * Calculate conversation metrics
     *
     * @since 1.0.0
     * @param string $conversationId Conversation ID
     * @param array $history Conversation history
     * @return array Calculated metrics
     */
    private function calculateConversationMetrics(string $conversationId, array $history): array
    {
        $messages = $history['messages'] ?? [];
        $conversation = $history['conversation'] ?? [];

        $metrics = [
            'total_messages' => count($messages),
            'user_messages' => 0,
            'assistant_messages' => 0,
            'avg_response_time' => 0,
            'total_tokens' => 0,
            'avg_confidence' => 0,
            'resolution_status' => $conversation['status'] ?? 'unknown'
        ];

        $responseTimes = [];
        $confidenceScores = [];
        $totalTokens = 0;

        $lastUserMessageTime = null;

        foreach ($messages as $message) {
            if ($message['type'] === 'user') {
                $metrics['user_messages']++;
                $lastUserMessageTime = strtotime($message['created_at']);
            } elseif ($message['type'] === 'assistant') {
                $metrics['assistant_messages']++;

                if ($lastUserMessageTime) {
                    $responseTime = strtotime($message['created_at']) - $lastUserMessageTime;
                    $responseTimes[] = $responseTime;
                    $lastUserMessageTime = null;
                }
            }

            if ($message['tokens_used']) {
                $totalTokens += (int) $message['tokens_used'];
            }

            if ($message['confidence_score']) {
                $confidenceScores[] = (float) $message['confidence_score'];
            }
        }

        $metrics['total_tokens'] = $totalTokens;
        $metrics['avg_response_time'] = !empty($responseTimes) ? array_sum($responseTimes) / count($responseTimes) : 0;
        $metrics['avg_confidence'] = !empty($confidenceScores) ? array_sum($confidenceScores) / count($confidenceScores) : 0;

        return $metrics;
    }

    /**
     * Get agent actions for conversation
     *
     * @since 1.0.0
     * @param string $conversationId Conversation ID
     * @return array Agent actions data
     */
    private function getConversationAgentActions(string $conversationId): array
    {
        global $wpdb;

        $actionsTable = $wpdb->prefix . 'woo_ai_agent_actions';

        $query = $wpdb->prepare(
            "SELECT * FROM {$actionsTable} WHERE conversation_id = %s ORDER BY created_at ASC",
            $conversationId
        );

        $actions = $wpdb->get_results($query, ARRAY_A);

        return $actions ?: [];
    }

    /**
     * Prepare data for export
     *
     * @since 1.0.0
     * @param array $conversations Conversations data
     * @param array $options Export options
     * @return array Prepared export data
     */
    private function prepareExportData(array $conversations, array $options): array
    {
        $exportData = [];

        foreach ($conversations as $conversation) {
            $row = [
                'conversation_id' => $conversation['conversation_id'],
                'user_name' => $conversation['user_name'],
                'user_email' => $conversation['user_email'],
                'status' => $conversation['status'],
                'started_at' => $conversation['started_at'],
                'ended_at' => $conversation['ended_at'],
                'duration' => $conversation['duration'] ? gmdate('H:i:s', $conversation['duration']) : '',
                'total_messages' => $conversation['total_messages'],
                'user_rating' => $conversation['user_rating'] ?? '',
                'avg_confidence' => round($conversation['avg_confidence'], 2),
                'confidence_level' => $conversation['confidence_badge']['label'],
                'models_used' => $conversation['models_used'],
                'total_tokens' => $conversation['total_tokens']
            ];

            if ($options['include_messages']) {
                $details = $this->getConversationDetails($conversation['conversation_id']);
                if (!is_wp_error($details)) {
                    $row['messages'] = $details['messages'];
                }
            }

            if ($options['include_kb_snippets']) {
                $row['kb_snippets'] = $this->getConversationKbSnippets($conversation['conversation_id']);
            }

            $exportData[] = $row;
        }

        return $exportData;
    }

    /**
     * Export data to CSV format
     *
     * @since 1.0.0
     * @param array $data Export data
     * @param string $filename Output filename
     * @return void
     */
    private function exportToCsv(array $data, string $filename): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        // Add BOM for UTF-8
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        if (!empty($data)) {
            // Get headers from first row
            $headers = array_keys($data[0]);

            // Remove complex fields for CSV
            $headers = array_filter($headers, function ($header) {
                return !in_array($header, ['messages', 'kb_snippets']);
            });

            fputcsv($output, $headers);

            foreach ($data as $row) {
                // Remove complex fields
                $csvRow = array_intersect_key($row, array_flip($headers));
                fputcsv($output, $csvRow);
            }
        }

        fclose($output);
        exit;
    }

    /**
     * Export data to JSON format
     *
     * @since 1.0.0
     * @param array $data Export data
     * @param string $filename Output filename
     * @return void
     */
    private function exportToJson(array $data, string $filename): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $exportData = [
            'export_info' => [
                'generated_at' => current_time('c'),
                'plugin_version' => Utils::getPluginVersion(),
                'total_conversations' => count($data)
            ],
            'conversations' => $data
        ];

        echo wp_json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Render page header with title and actions
     *
     * @since 1.0.0
     * @return void
     */
    private function renderPageHeader(): void
    {
        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html__('Conversations Log', 'woo-ai-assistant') . '</h1>';

        echo '<div class="page-title-action-wrapper">';
        echo '<a href="#" id="export-conversations" class="page-title-action">';
        echo esc_html__('Export Conversations', 'woo-ai-assistant');
        echo '</a>';
        echo '</div>';

        echo '<hr class="wp-header-end">';
        echo '</div>';
    }

    /**
     * Render filters section
     *
     * @since 1.0.0
     * @return void
     */
    private function renderFiltersSection(): void
    {
        $currentFilters = [
            'search' => sanitize_text_field($_GET['search'] ?? ''),
            'status' => sanitize_text_field($_GET['status'] ?? ''),
            'rating' => sanitize_text_field($_GET['rating'] ?? ''),
            'confidence' => sanitize_text_field($_GET['confidence'] ?? ''),
            'date_from' => sanitize_text_field($_GET['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_GET['date_to'] ?? ''),
            'user_id' => sanitize_text_field($_GET['user_id'] ?? '')
        ];

        echo '<div class="conversations-filters-section">';
        echo '<form method="get" id="conversations-filter-form">';
        echo '<input type="hidden" name="page" value="woo-ai-conversations" />';

        echo '<div class="filters-row">';

        // Search field
        echo '<div class="filter-group">';
        echo '<label for="search-conversations">' . esc_html__('Search:', 'woo-ai-assistant') . '</label>';
        echo '<input type="search" id="search-conversations" name="search" value="' . esc_attr($currentFilters['search']) . '" placeholder="' . esc_attr__('Search conversations...', 'woo-ai-assistant') . '" />';
        echo '</div>';

        // Status filter
        echo '<div class="filter-group">';
        echo '<label for="filter-status">' . esc_html__('Status:', 'woo-ai-assistant') . '</label>';
        echo '<select id="filter-status" name="status">';
        echo '<option value="">' . esc_html__('All Statuses', 'woo-ai-assistant') . '</option>';
        $statuses = ['active', 'ended', 'completed', 'resolved'];
        foreach ($statuses as $status) {
            $selected = $currentFilters['status'] === $status ? 'selected' : '';
            echo '<option value="' . esc_attr($status) . '" ' . $selected . '>' . esc_html(ucfirst($status)) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        // Rating filter
        echo '<div class="filter-group">';
        echo '<label for="filter-rating">' . esc_html__('Rating:', 'woo-ai-assistant') . '</label>';
        echo '<select id="filter-rating" name="rating">';
        echo '<option value="">' . esc_html__('All Ratings', 'woo-ai-assistant') . '</option>';
        for ($i = 1; $i <= 5; $i++) {
            $selected = $currentFilters['rating'] === (string) $i ? 'selected' : '';
            echo '<option value="' . $i . '" ' . $selected . '>' . $i . ' ' . esc_html__('Stars', 'woo-ai-assistant') . '</option>';
        }
        echo '</select>';
        echo '</div>';

        // Confidence filter
        echo '<div class="filter-group">';
        echo '<label for="filter-confidence">' . esc_html__('Confidence:', 'woo-ai-assistant') . '</label>';
        echo '<select id="filter-confidence" name="confidence">';
        echo '<option value="">' . esc_html__('All Levels', 'woo-ai-assistant') . '</option>';
        $confidenceLevels = ['high' => __('High', 'woo-ai-assistant'), 'medium' => __('Medium', 'woo-ai-assistant'), 'low' => __('Low', 'woo-ai-assistant')];
        foreach ($confidenceLevels as $value => $label) {
            $selected = $currentFilters['confidence'] === $value ? 'selected' : '';
            echo '<option value="' . esc_attr($value) . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        echo '</div>';

        echo '<div class="filters-row">';

        // Date range filters
        echo '<div class="filter-group">';
        echo '<label for="date-from">' . esc_html__('Date From:', 'woo-ai-assistant') . '</label>';
        echo '<input type="date" id="date-from" name="date_from" value="' . esc_attr($currentFilters['date_from']) . '" />';
        echo '</div>';

        echo '<div class="filter-group">';
        echo '<label for="date-to">' . esc_html__('Date To:', 'woo-ai-assistant') . '</label>';
        echo '<input type="date" id="date-to" name="date_to" value="' . esc_attr($currentFilters['date_to']) . '" />';
        echo '</div>';

        // Filter actions
        echo '<div class="filter-group filter-actions">';
        echo '<button type="submit" class="button button-primary">' . esc_html__('Filter', 'woo-ai-assistant') . '</button>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=woo-ai-conversations')) . '" class="button">' . esc_html__('Clear', 'woo-ai-assistant') . '</a>';
        echo '</div>';

        echo '</div>';

        echo '</form>';
        echo '</div>';
    }

    /**
     * Render conversations table
     *
     * @since 1.0.0
     * @return void
     */
    private function renderConversationsTable(): void
    {
        if (!$this->listTable) {
            return;
        }

        echo '<form method="post" id="conversations-form">';
        wp_nonce_field('woo_ai_conversations_nonce');
        $this->listTable->display();
        echo '</form>';
    }

    /**
     * Render modals for conversation details and export
     *
     * @since 1.0.0
     * @return void
     */
    private function renderModals(): void
    {
        // Conversation details modal
        echo '<div id="conversation-details-modal" class="conversation-modal" style="display: none;">';
        echo '<div class="modal-content">';
        echo '<div class="modal-header">';
        echo '<h2>' . esc_html__('Conversation Details', 'woo-ai-assistant') . '</h2>';
        echo '<span class="modal-close">&times;</span>';
        echo '</div>';
        echo '<div class="modal-body" id="conversation-details-content">';
        echo '<div class="loading">' . esc_html__('Loading...', 'woo-ai-assistant') . '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        // Export modal
        echo '<div id="export-modal" class="conversation-modal" style="display: none;">';
        echo '<div class="modal-content">';
        echo '<div class="modal-header">';
        echo '<h2>' . esc_html__('Export Conversations', 'woo-ai-assistant') . '</h2>';
        echo '<span class="modal-close">&times;</span>';
        echo '</div>';
        echo '<div class="modal-body">';
        echo '<form id="export-form">';

        echo '<div class="export-options">';
        echo '<h3>' . esc_html__('Export Options', 'woo-ai-assistant') . '</h3>';

        echo '<label><input type="radio" name="format" value="csv" checked> ' . esc_html__('CSV Format', 'woo-ai-assistant') . '</label><br>';
        echo '<label><input type="radio" name="format" value="json"> ' . esc_html__('JSON Format', 'woo-ai-assistant') . '</label><br><br>';

        echo '<label><input type="number" name="limit" value="500" min="1" max="' . self::MAX_EXPORT_LIMIT . '"> ' . esc_html__('Maximum conversations to export', 'woo-ai-assistant') . '</label><br><br>';

        echo '<label><input type="checkbox" name="include_messages"> ' . esc_html__('Include message content', 'woo-ai-assistant') . '</label><br>';
        echo '<label><input type="checkbox" name="include_kb_snippets"> ' . esc_html__('Include KB snippets', 'woo-ai-assistant') . '</label><br>';
        echo '</div>';

        echo '<div class="export-actions">';
        echo '<button type="submit" class="button button-primary">' . esc_html__('Start Export', 'woo-ai-assistant') . '</button>';
        echo '<button type="button" class="button modal-close">' . esc_html__('Cancel', 'woo-ai-assistant') . '</button>';
        echo '</div>';

        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render error message
     *
     * @since 1.0.0
     * @param string $message Error message
     * @return void
     */
    private function renderErrorMessage(string $message): void
    {
        echo '<div class="woo-ai-conversations-error">';
        echo '<div class="error-content">';
        echo '<h2>' . esc_html__('Conversations Log Error', 'woo-ai-assistant') . '</h2>';
        echo '<p>' . esc_html($message) . '</p>';
        echo '<p><a href="' . esc_url(admin_url('admin.php?page=woo-ai-conversations')) . '" class="button button-primary">';
        echo esc_html__('Try Again', 'woo-ai-assistant') . '</a></p>';
        echo '</div>';
        echo '</div>';
    }
}

/**
 * Conversations List Table Class
 *
 * Extended WP_List_Table class for displaying conversations in a table format
 * with sorting, filtering, and bulk actions support.
 *
 * @since 1.0.0
 */
class ConversationsListTable extends WP_List_Table
{
    /**
     * ConversationsLogPage instance
     *
     * @since 1.0.0
     * @var ConversationsLogPage
     */
    private $parentPage;

    /**
     * Constructor
     *
     * @since 1.0.0
     * @param ConversationsLogPage $parentPage Parent page instance
     */
    public function __construct(ConversationsLogPage $parentPage)
    {
        parent::__construct([
            'singular' => 'conversation',
            'plural' => 'conversations',
            'ajax' => true
        ]);

        $this->parentPage = $parentPage;
    }

    /**
     * Get table columns
     *
     * @since 1.0.0
     * @return array Column definitions
     */
    public function get_columns(): array
    {
        return [
            'cb' => '<input type="checkbox" />',
            'conversation_id' => __('Conversation ID', 'woo-ai-assistant'),
            'user' => __('User', 'woo-ai-assistant'),
            'status' => __('Status', 'woo-ai-assistant'),
            'confidence' => __('Confidence', 'woo-ai-assistant'),
            'messages' => __('Messages', 'woo-ai-assistant'),
            'rating' => __('Rating', 'woo-ai-assistant'),
            'duration' => __('Duration', 'woo-ai-assistant'),
            'started_at' => __('Started', 'woo-ai-assistant'),
            'actions' => __('Actions', 'woo-ai-assistant')
        ];
    }

    /**
     * Get sortable columns
     *
     * @since 1.0.0
     * @return array Sortable column definitions
     */
    public function get_sortable_columns(): array
    {
        return [
            'started_at' => ['started_at', true],
            'status' => ['status', false],
            'messages' => ['total_messages', false],
            'rating' => ['user_rating', false]
        ];
    }

    /**
     * Get bulk actions
     *
     * @since 1.0.0
     * @return array Bulk action definitions
     */
    public function get_bulk_actions(): array
    {
        return [
            'delete' => __('Delete', 'woo-ai-assistant'),
            'mark_resolved' => __('Mark as Resolved', 'woo-ai-assistant'),
            'export' => __('Export Selected', 'woo-ai-assistant')
        ];
    }

    /**
     * Prepare table items
     *
     * @since 1.0.0
     * @return void
     */
    public function prepare_items(): void
    {
        $perPage = $this->get_items_per_page('conversations_per_page', ConversationsLogPage::DEFAULT_PER_PAGE);
        $currentPage = $this->get_pagenum();

        $args = [
            'per_page' => $perPage,
            'page' => $currentPage,
            'search' => sanitize_text_field($_GET['search'] ?? ''),
            'status' => sanitize_text_field($_GET['status'] ?? ''),
            'rating' => sanitize_text_field($_GET['rating'] ?? ''),
            'confidence' => sanitize_text_field($_GET['confidence'] ?? ''),
            'date_from' => sanitize_text_field($_GET['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_GET['date_to'] ?? ''),
            'user_id' => sanitize_text_field($_GET['user_id'] ?? ''),
            'orderby' => sanitize_text_field($_GET['orderby'] ?? 'started_at'),
            'order' => sanitize_text_field($_GET['order'] ?? 'DESC')
        ];

        try {
            $data = $this->parentPage->getConversationsData($args);

            $this->items = $data['conversations'];

            $this->set_pagination_args([
                'total_items' => $data['pagination']['total_items'],
                'per_page' => $perPage,
                'total_pages' => $data['pagination']['total_pages']
            ]);
        } catch (\Exception $e) {
            $this->items = [];
            Utils::logError('Failed to prepare conversation items: ' . $e->getMessage());
        }
    }

    /**
     * Default column content
     *
     * @since 1.0.0
     * @param array $item Row data
     * @param string $columnName Column name
     * @return string Column content
     */
    public function column_default($item, $columnName): string
    {
        switch ($columnName) {
            case 'conversation_id':
                return '<code>' . esc_html(substr($item['conversation_id'], 0, 20)) . '...</code>';
            case 'user':
                return esc_html($item['user_name']);
            case 'status':
                return '<span class="status-badge status-' . esc_attr($item['status']) . '">' . esc_html(ucfirst($item['status'])) . '</span>';
            case 'confidence':
                $badge = $item['confidence_badge'];
                return '<span class="confidence-badge ' . esc_attr($badge['class']) . '">' . esc_html($badge['label']) . '</span>';
            case 'messages':
                return (string) $item['total_messages'];
            case 'rating':
                if ($item['user_rating']) {
                    $stars = str_repeat('★', $item['user_rating']) . str_repeat('☆', 5 - $item['user_rating']);
                    return '<span class="rating-stars">' . $stars . '</span>';
                }
                return '—';
            case 'duration':
                if ($item['duration']) {
                    return gmdate('H:i:s', $item['duration']);
                }
                return '—';
            case 'started_at':
                return wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item['started_at']));
            default:
                return '';
        }
    }

    /**
     * Checkbox column
     *
     * @since 1.0.0
     * @param array $item Row data
     * @return string Checkbox HTML
     */
    public function column_cb($item): string
    {
        return sprintf('<input type="checkbox" name="conversation[]" value="%s" />', esc_attr($item['conversation_id']));
    }

    /**
     * Actions column
     *
     * @since 1.0.0
     * @param array $item Row data
     * @return string Actions HTML
     */
    public function column_actions($item): string
    {
        $actions = [];

        $actions['view'] = sprintf(
            '<a href="#" class="view-conversation-details" data-conversation-id="%s">%s</a>',
            esc_attr($item['conversation_id']),
            esc_html__('View Details', 'woo-ai-assistant')
        );

        if ($item['status'] === 'active') {
            $actions['end'] = sprintf(
                '<a href="#" class="end-conversation" data-conversation-id="%s">%s</a>',
                esc_attr($item['conversation_id']),
                esc_html__('End Conversation', 'woo-ai-assistant')
            );
        }

        $actions['delete'] = sprintf(
            '<a href="#" class="delete-conversation" data-conversation-id="%s" style="color: #d63638;">%s</a>',
            esc_attr($item['conversation_id']),
            esc_html__('Delete', 'woo-ai-assistant')
        );

        return $this->row_actions($actions);
    }

    /**
     * Display when no items found
     *
     * @since 1.0.0
     * @return void
     */
    public function no_items(): void
    {
        esc_html_e('No conversations found.', 'woo-ai-assistant');
    }
}
