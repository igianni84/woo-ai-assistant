<?php

/**
 * Conversations Log Page Class
 *
 * Handles the conversations log page in the admin interface.
 * Displays chat history, analytics, and conversation management tools.
 *
 * @package WooAiAssistant
 * @subpackage Admin\Pages
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Admin\Pages;

use WooAiAssistant\Common\Traits\Singleton;
use WooAiAssistant\Common\Logger;
use WooAiAssistant\Common\Utils;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ConversationsLogPage
 *
 * Manages the conversations log admin page rendering and functionality.
 *
 * @since 1.0.0
 */
class ConversationsLogPage
{
    use Singleton;

    /**
     * Page slug
     *
     * @var string
     */
    private string $pageSlug = 'woo-ai-assistant-conversations';

    /**
     * Items per page for pagination
     *
     * @var int
     */
    private int $itemsPerPage = 20;

    /**
     * Initialize the conversations log page
     *
     * @return void
     */
    protected function init(): void
    {
        // Hook for handling bulk actions
        add_action('admin_post_woo_ai_assistant_bulk_conversations', [$this, 'handleBulkActions']);

        Logger::debug('ConversationsLogPage initialized');
    }

    /**
     * Render the conversations log page
     *
     * @return void
     */
    public function render(): void
    {
        // Security check
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'woo-ai-assistant'));
            return;
        }

        $conversations = $this->getConversations();
        $total_conversations = $this->getTotalConversationsCount();
        $current_page = $this->getCurrentPage();
        $total_pages = ceil($total_conversations / $this->itemsPerPage);

        ?>
        <div class="wrap woo-ai-assistant-conversations">
            <h1 class="wp-heading-inline">
                <?php esc_html_e('Conversations Log', 'woo-ai-assistant'); ?>
            </h1>
            
            <a href="#" class="page-title-action" id="export-conversations">
                <?php esc_html_e('Export Data', 'woo-ai-assistant'); ?>
            </a>

            <?php $this->renderFilters(); ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('woo_ai_assistant_bulk_conversations', 'woo_ai_assistant_bulk_nonce'); ?>
                <input type="hidden" name="action" value="woo_ai_assistant_bulk_conversations">

                <?php $this->renderBulkActions(); ?>

                <div class="conversations-table-container">
                    <?php if (empty($conversations)) : ?>
                        <?php $this->renderEmptyState(); ?>
                    <?php else : ?>
                        <?php $this->renderConversationsTable($conversations); ?>
                    <?php endif; ?>
                </div>

                <?php if ($total_pages > 1) : ?>
                    <?php $this->renderPagination($current_page, $total_pages); ?>
                <?php endif; ?>
            </form>

            <!-- Conversation Details Modal -->
            <div id="conversation-modal" class="woo-ai-assistant-modal" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2><?php esc_html_e('Conversation Details', 'woo-ai-assistant'); ?></h2>
                        <button type="button" class="modal-close">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div id="conversation-details">
                            <!-- Content will be loaded via AJAX -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php

        Logger::debug('Conversations log page rendered', [
            'total_conversations' => $total_conversations,
            'current_page' => $current_page
        ]);
    }

    /**
     * Render filter controls
     *
     * @return void
     */
    private function renderFilters(): void
    {
        $current_status = $_GET['status'] ?? 'all';
        $current_date_range = $_GET['date_range'] ?? '30_days';
        $search_query = $_GET['search'] ?? '';

        ?>
        <div class="woo-ai-assistant-filters">
            <div class="alignleft actions">
                <label for="filter-status" class="screen-reader-text"><?php esc_html_e('Filter by status', 'woo-ai-assistant'); ?></label>
                <select name="status" id="filter-status">
                    <option value="all" <?php selected($current_status, 'all'); ?>><?php esc_html_e('All Statuses', 'woo-ai-assistant'); ?></option>
                    <option value="active" <?php selected($current_status, 'active'); ?>><?php esc_html_e('Active', 'woo-ai-assistant'); ?></option>
                    <option value="completed" <?php selected($current_status, 'completed'); ?>><?php esc_html_e('Completed', 'woo-ai-assistant'); ?></option>
                    <option value="abandoned" <?php selected($current_status, 'abandoned'); ?>><?php esc_html_e('Abandoned', 'woo-ai-assistant'); ?></option>
                </select>

                <label for="filter-date-range" class="screen-reader-text"><?php esc_html_e('Filter by date', 'woo-ai-assistant'); ?></label>
                <select name="date_range" id="filter-date-range">
                    <option value="all" <?php selected($current_date_range, 'all'); ?>><?php esc_html_e('All Time', 'woo-ai-assistant'); ?></option>
                    <option value="today" <?php selected($current_date_range, 'today'); ?>><?php esc_html_e('Today', 'woo-ai-assistant'); ?></option>
                    <option value="7_days" <?php selected($current_date_range, '7_days'); ?>><?php esc_html_e('Last 7 Days', 'woo-ai-assistant'); ?></option>
                    <option value="30_days" <?php selected($current_date_range, '30_days'); ?>><?php esc_html_e('Last 30 Days', 'woo-ai-assistant'); ?></option>
                    <option value="90_days" <?php selected($current_date_range, '90_days'); ?>><?php esc_html_e('Last 90 Days', 'woo-ai-assistant'); ?></option>
                </select>

                <input type="submit" name="filter_action" id="post-query-submit" class="button" value="<?php esc_attr_e('Filter', 'woo-ai-assistant'); ?>">
            </div>

            <div class="alignright actions">
                <label for="search-conversations" class="screen-reader-text"><?php esc_html_e('Search conversations', 'woo-ai-assistant'); ?></label>
                <input type="search" id="search-conversations" name="search" value="<?php echo esc_attr($search_query); ?>" placeholder="<?php esc_attr_e('Search conversations...', 'woo-ai-assistant'); ?>">
                <input type="submit" id="search-submit" class="button" value="<?php esc_attr_e('Search', 'woo-ai-assistant'); ?>">
            </div>
        </div>
        <?php
    }

    /**
     * Render bulk actions dropdown
     *
     * @return void
     */
    private function renderBulkActions(): void
    {
        ?>
        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <label for="bulk-action-selector-top" class="screen-reader-text"><?php esc_html_e('Select bulk action', 'woo-ai-assistant'); ?></label>
                <select name="action" id="bulk-action-selector-top">
                    <option value="-1"><?php esc_html_e('Bulk Actions', 'woo-ai-assistant'); ?></option>
                    <option value="export"><?php esc_html_e('Export', 'woo-ai-assistant'); ?></option>
                    <option value="delete"><?php esc_html_e('Delete', 'woo-ai-assistant'); ?></option>
                    <option value="mark_resolved"><?php esc_html_e('Mark as Resolved', 'woo-ai-assistant'); ?></option>
                </select>
                <input type="submit" id="doaction" class="button action" value="<?php esc_attr_e('Apply', 'woo-ai-assistant'); ?>">
            </div>
        </div>
        <?php
    }

    /**
     * Render conversations table
     *
     * @param array $conversations Conversations data
     * @return void
     */
    private function renderConversationsTable(array $conversations): void
    {
        ?>
        <table class="wp-list-table widefat fixed striped conversations">
            <thead>
                <tr>
                    <td id="cb" class="manage-column column-cb check-column">
                        <label class="screen-reader-text" for="cb-select-all-1"><?php esc_html_e('Select All', 'woo-ai-assistant'); ?></label>
                        <input id="cb-select-all-1" type="checkbox">
                    </td>
                    <th scope="col" class="manage-column column-conversation-id sortable">
                        <a href="#"><span><?php esc_html_e('ID', 'woo-ai-assistant'); ?></span></a>
                    </th>
                    <th scope="col" class="manage-column column-customer">
                        <?php esc_html_e('Customer', 'woo-ai-assistant'); ?>
                    </th>
                    <th scope="col" class="manage-column column-messages">
                        <?php esc_html_e('Messages', 'woo-ai-assistant'); ?>
                    </th>
                    <th scope="col" class="manage-column column-status">
                        <?php esc_html_e('Status', 'woo-ai-assistant'); ?>
                    </th>
                    <th scope="col" class="manage-column column-rating">
                        <?php esc_html_e('Rating', 'woo-ai-assistant'); ?>
                    </th>
                    <th scope="col" class="manage-column column-started sortable">
                        <a href="#"><span><?php esc_html_e('Started', 'woo-ai-assistant'); ?></span></a>
                    </th>
                    <th scope="col" class="manage-column column-actions">
                        <?php esc_html_e('Actions', 'woo-ai-assistant'); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($conversations as $conversation) : ?>
                    <tr>
                        <th scope="row" class="check-column">
                            <input type="checkbox" name="conversation_ids[]" value="<?php echo esc_attr($conversation['id']); ?>">
                        </th>
                        <td class="conversation-id column-conversation-id">
                            <strong><a href="#" class="conversation-link" data-conversation-id="<?php echo esc_attr($conversation['id']); ?>">
                                #<?php echo esc_html($conversation['id']); ?>
                            </a></strong>
                        </td>
                        <td class="customer column-customer">
                            <div class="customer-info">
                                <?php if (!empty($conversation['customer_name'])) : ?>
                                    <strong><?php echo esc_html($conversation['customer_name']); ?></strong><br>
                                <?php endif; ?>
                                <span class="customer-email"><?php echo esc_html($conversation['customer_email'] ?? __('Anonymous', 'woo-ai-assistant')); ?></span>
                            </div>
                        </td>
                        <td class="messages column-messages">
                            <span class="message-count"><?php echo esc_html($conversation['message_count']); ?></span>
                            <span class="messages-label"><?php esc_html_e('messages', 'woo-ai-assistant'); ?></span>
                        </td>
                        <td class="status column-status">
                            <span class="status-badge status-<?php echo esc_attr($conversation['status']); ?>">
                                <?php echo esc_html(ucfirst($conversation['status'])); ?>
                            </span>
                        </td>
                        <td class="rating column-rating">
                            <?php if (!empty($conversation['rating'])) : ?>
                                <div class="rating-stars">
                                    <?php for ($i = 1; $i <= 5; $i++) : ?>
                                        <span class="star <?php echo $i <= $conversation['rating'] ? 'filled' : ''; ?>">★</span>
                                    <?php endfor; ?>
                                </div>
                            <?php else : ?>
                                <span class="no-rating">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="started column-started">
                            <abbr title="<?php echo esc_attr($conversation['started_at']); ?>">
                                <?php echo esc_html(human_time_diff(strtotime($conversation['started_at']), current_time('timestamp')) . ' ago'); ?>
                            </abbr>
                        </td>
                        <td class="actions column-actions">
                            <button type="button" class="button button-small view-conversation" data-conversation-id="<?php echo esc_attr($conversation['id']); ?>">
                                <?php esc_html_e('View', 'woo-ai-assistant'); ?>
                            </button>
                            <button type="button" class="button button-small export-conversation" data-conversation-id="<?php echo esc_attr($conversation['id']); ?>">
                                <?php esc_html_e('Export', 'woo-ai-assistant'); ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render empty state when no conversations exist
     *
     * @return void
     */
    private function renderEmptyState(): void
    {
        ?>
        <div class="woo-ai-assistant-empty-state">
            <div class="empty-state-icon">💬</div>
            <h3><?php esc_html_e('No conversations yet', 'woo-ai-assistant'); ?></h3>
            <p><?php esc_html_e('When customers start chatting with your AI assistant, their conversations will appear here.', 'woo-ai-assistant'); ?></p>
            <div class="empty-state-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=woo-ai-assistant-settings')); ?>" class="button button-primary">
                    <?php esc_html_e('Configure Chat Widget', 'woo-ai-assistant'); ?>
                </a>
                <button type="button" class="button button-secondary" id="test-chat-widget">
                    <?php esc_html_e('Test Chat Widget', 'woo-ai-assistant'); ?>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Render pagination
     *
     * @param int $current_page Current page number
     * @param int $total_pages Total pages
     * @return void
     */
    private function renderPagination(int $current_page, int $total_pages): void
    {
        ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <span class="displaying-num">
                    <?php printf(_n('%s item', '%s items', $this->getTotalConversationsCount(), 'woo-ai-assistant'), number_format_i18n($this->getTotalConversationsCount())); ?>
                </span>
                <?php
                $pagination_args = [
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => '&lsaquo;',
                    'next_text' => '&rsaquo;',
                    'total' => $total_pages,
                    'current' => $current_page,
                    'show_all' => false,
                    'type' => 'plain',
                ];

                echo paginate_links($pagination_args);
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Get conversations data (placeholder)
     *
     * @return array Conversations data
     */
    private function getConversations(): array
    {
        // TODO: This will be replaced with actual database queries in future tasks
        return [
            [
                'id' => 1,
                'customer_name' => 'John Doe',
                'customer_email' => 'john@example.com',
                'message_count' => 12,
                'status' => 'completed',
                'rating' => 5,
                'started_at' => '2024-08-30 10:30:00',
            ],
            [
                'id' => 2,
                'customer_name' => '',
                'customer_email' => '',
                'message_count' => 3,
                'status' => 'abandoned',
                'rating' => null,
                'started_at' => '2024-08-30 09:15:00',
            ],
        ];
    }

    /**
     * Get total conversations count
     *
     * @return int Total count
     */
    private function getTotalConversationsCount(): int
    {
        // TODO: This will be replaced with actual database query in future tasks
        return 2;
    }

    /**
     * Get current page number
     *
     * @return int Current page
     */
    private function getCurrentPage(): int
    {
        return absint($_GET['paged'] ?? 1);
    }

    /**
     * Handle bulk actions
     *
     * @return void
     */
    public function handleBulkActions(): void
    {
        // Security checks
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions.', 'woo-ai-assistant'));
            return;
        }

        if (!wp_verify_nonce($_POST['woo_ai_assistant_bulk_nonce'], 'woo_ai_assistant_bulk_conversations')) {
            wp_die(__('Security check failed.', 'woo-ai-assistant'));
            return;
        }

        $action = sanitize_text_field($_POST['action'] ?? '');
        $conversation_ids = array_map('absint', $_POST['conversation_ids'] ?? []);

        if (empty($conversation_ids)) {
            wp_redirect(admin_url('admin.php?page=' . $this->pageSlug));
            exit;
        }

        switch ($action) {
            case 'export':
                $this->exportConversations($conversation_ids);
                break;
            case 'delete':
                $this->deleteConversations($conversation_ids);
                break;
            case 'mark_resolved':
                $this->markConversationsResolved($conversation_ids);
                break;
        }

        wp_redirect(admin_url('admin.php?page=' . $this->pageSlug));
        exit;
    }

    /**
     * Export conversations (placeholder)
     *
     * @param array $conversation_ids Conversation IDs
     * @return void
     */
    private function exportConversations(array $conversation_ids): void
    {
        // TODO: Implement conversation export functionality
        Logger::debug('Export conversations requested', ['ids' => $conversation_ids]);
    }

    /**
     * Delete conversations (placeholder)
     *
     * @param array $conversation_ids Conversation IDs
     * @return void
     */
    private function deleteConversations(array $conversation_ids): void
    {
        // TODO: Implement conversation deletion functionality
        Logger::debug('Delete conversations requested', ['ids' => $conversation_ids]);
    }

    /**
     * Mark conversations as resolved (placeholder)
     *
     * @param array $conversation_ids Conversation IDs
     * @return void
     */
    private function markConversationsResolved(array $conversation_ids): void
    {
        // TODO: Implement conversation resolution functionality
        Logger::debug('Mark conversations resolved requested', ['ids' => $conversation_ids]);
    }

    /**
     * Register settings for this page
     *
     * @return void
     */
    public function registerSettings(): void
    {
        // Conversations log page doesn't have specific settings
        // This method is called by AdminMenu but can be empty for conversations

        Logger::debug('Conversations log page settings registered');
    }

    /**
     * Get page slug
     *
     * @return string Page slug
     */
    public function getPageSlug(): string
    {
        return $this->pageSlug;
    }
}