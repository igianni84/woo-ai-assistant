<?php

/**
 * Knowledge Base Status Page Class
 *
 * Displays the current status of the Knowledge Base including indexed content,
 * vector storage statistics, health checks, and indexing operations.
 *
 * @package WooAiAssistant
 * @subpackage Admin\Pages
 * @since 1.0.0
 * @author Claude Code Assistant
 */

namespace WooAiAssistant\Admin\Pages;

use WooAiAssistant\Common\Utils;
use WooAiAssistant\Common\Traits\Singleton;
use WooAiAssistant\KnowledgeBase\Scanner;
use WooAiAssistant\KnowledgeBase\Indexer;
use WooAiAssistant\KnowledgeBase\VectorManager;
use WooAiAssistant\KnowledgeBase\IndexingProcessor;
use WooAiAssistant\KnowledgeBase\AIManager;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class KnowledgeBaseStatusPage
 *
 * Provides comprehensive Knowledge Base monitoring and management interface.
 *
 * @since 1.0.0
 */
class KnowledgeBaseStatusPage
{
    use Singleton;

    /**
     * Required capability for page access
     *
     * @since 1.0.0
     * @var string
     */
    private const REQUIRED_CAPABILITY = 'manage_woocommerce';

    /**
     * Page slug
     *
     * @since 1.0.0
     * @var string
     */
    private const PAGE_SLUG = 'woo-ai-assistant-knowledge-base';

    /**
     * Constructor
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
        // Admin assets
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);

        // AJAX handlers
        add_action('wp_ajax_woo_ai_kb_refresh_status', [$this, 'handleRefreshStatus']);
        add_action('wp_ajax_woo_ai_kb_start_indexing', [$this, 'handleStartIndexing']);
        add_action('wp_ajax_woo_ai_kb_clear_index', [$this, 'handleClearIndex']);
        add_action('wp_ajax_woo_ai_kb_check_indexing_status', [$this, 'handleCheckIndexingStatus']);
    }

    /**
     * Render the Knowledge Base Status page
     *
     * @since 1.0.0
     * @return void
     */
    public function renderPage(): void
    {
        if (!current_user_can(self::REQUIRED_CAPABILITY)) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'woo-ai-assistant'));
        }

        // Check nonce for any POST actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'woo_ai_kb_action')) {
                wp_die(__('Security check failed.', 'woo-ai-assistant'));
            }
        }

        $kbStats = $this->getKnowledgeBaseStats();
        ?>
        <div class="wrap woo-ai-assistant-kb-status">
            <h1><?php esc_html_e('Knowledge Base Status', 'woo-ai-assistant'); ?></h1>
            
            <?php $this->renderStatusSummary($kbStats); ?>
            <?php $this->renderIndexedContent($kbStats); ?>
            <?php $this->renderHealthChecks($kbStats); ?>
            <?php $this->renderIndexingOperations(); ?>
            <?php $this->renderRecentActivity($kbStats); ?>
        </div>
        <?php
    }

    /**
     * Render status summary section
     *
     * @since 1.0.0
     * @param array $stats Knowledge base statistics
     * @return void
     */
    private function renderStatusSummary(array $stats): void
    {
        $healthScore = $stats['health_score'] ?? 0;
        $healthClass = $healthScore >= 80 ? 'good' : ($healthScore >= 60 ? 'warning' : 'error');
        ?>
        <div class="kb-status-summary">
            <h2><?php esc_html_e('Status Summary', 'woo-ai-assistant'); ?></h2>
            
            <div class="status-cards">
                <div class="status-card health-score <?php echo esc_attr($healthClass); ?>">
                    <div class="card-label"><?php esc_html_e('Health Score', 'woo-ai-assistant'); ?></div>
                    <div class="card-value"><?php echo esc_html($healthScore); ?>%</div>
                    <div class="card-detail"><?php echo esc_html($this->getHealthDescription($healthScore)); ?></div>
                </div>

                <div class="status-card">
                    <div class="card-label"><?php esc_html_e('Total Documents', 'woo-ai-assistant'); ?></div>
                    <div class="card-value"><?php echo esc_html(number_format($stats['total_documents'] ?? 0)); ?></div>
                    <div class="card-detail">
                        <?php
                        printf(
                            __('%d Products, %d Pages, %d Posts', 'woo-ai-assistant'),
                            $stats['products_count'] ?? 0,
                            $stats['pages_count'] ?? 0,
                            $stats['posts_count'] ?? 0
                        );
                        ?>
                    </div>
                </div>

                <div class="status-card">
                    <div class="card-label"><?php esc_html_e('Index Size', 'woo-ai-assistant'); ?></div>
                    <div class="card-value"><?php echo esc_html($this->formatBytes($stats['index_size'] ?? 0)); ?></div>
                    <div class="card-detail">
                        <?php
                        printf(
                            __('%d Chunks, %d Vectors', 'woo-ai-assistant'),
                            $stats['total_chunks'] ?? 0,
                            $stats['total_vectors'] ?? 0
                        );
                        ?>
                    </div>
                </div>

                <div class="status-card">
                    <div class="card-label"><?php esc_html_e('Last Update', 'woo-ai-assistant'); ?></div>
                    <div class="card-value">
                        <?php
                        if (!empty($stats['last_update'])) {
                            echo esc_html(human_time_diff(strtotime($stats['last_update'])) . ' ago');
                        } else {
                            esc_html_e('Never', 'woo-ai-assistant');
                        }
                        ?>
                    </div>
                    <div class="card-detail">
                        <?php
                        if (!empty($stats['next_scheduled_update'])) {
                            printf(
                                __('Next update in %s', 'woo-ai-assistant'),
                                human_time_diff(current_time('timestamp'), $stats['next_scheduled_update'])
                            );
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render indexed content section
     *
     * @since 1.0.0
     * @param array $stats Knowledge base statistics
     * @return void
     */
    private function renderIndexedContent(array $stats): void
    {
        ?>
        <div class="kb-indexed-content">
            <h2><?php esc_html_e('Indexed Content', 'woo-ai-assistant'); ?></h2>
            
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Content Type', 'woo-ai-assistant'); ?></th>
                        <th><?php esc_html_e('Count', 'woo-ai-assistant'); ?></th>
                        <th><?php esc_html_e('Indexed', 'woo-ai-assistant'); ?></th>
                        <th><?php esc_html_e('Coverage', 'woo-ai-assistant'); ?></th>
                        <th><?php esc_html_e('Last Scan', 'woo-ai-assistant'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->getContentTypes() as $type => $label) : ?>
                        <?php
                        $total = $stats[$type . '_total'] ?? 0;
                        $indexed = $stats[$type . '_indexed'] ?? 0;
                        $coverage = $total > 0 ? round(($indexed / $total) * 100) : 0;
                        $lastScan = $stats[$type . '_last_scan'] ?? null;
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($label); ?></strong></td>
                            <td><?php echo esc_html(number_format($total)); ?></td>
                            <td><?php echo esc_html(number_format($indexed)); ?></td>
                            <td>
                                <div class="coverage-bar">
                                    <div class="coverage-fill" style="width: <?php echo esc_attr($coverage); ?>%"></div>
                                    <span class="coverage-text"><?php echo esc_html($coverage); ?>%</span>
                                </div>
                            </td>
                            <td>
                                <?php
                                if ($lastScan) {
                                    echo esc_html(human_time_diff(strtotime($lastScan)) . ' ago');
                                } else {
                                    esc_html_e('Never', 'woo-ai-assistant');
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render health checks section
     *
     * @since 1.0.0
     * @param array $stats Knowledge base statistics
     * @return void
     */
    private function renderHealthChecks(array $stats): void
    {
        $healthChecks = $this->performHealthChecks($stats);
        ?>
        <div class="kb-health-checks">
            <h2><?php esc_html_e('Health Checks', 'woo-ai-assistant'); ?></h2>
            
            <div class="health-checks-grid">
                <?php foreach ($healthChecks as $check) : ?>
                    <div class="health-check-item <?php echo esc_attr($check['status']); ?>">
                        <span class="check-icon dashicons dashicons-<?php echo esc_attr($check['icon']); ?>"></span>
                        <div class="check-content">
                            <div class="check-title"><?php echo esc_html($check['title']); ?></div>
                            <div class="check-message"><?php echo esc_html($check['message']); ?></div>
                            <?php if (!empty($check['action'])) : ?>
                                <div class="check-action">
                                    <?php echo wp_kses_post($check['action']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render indexing operations section
     *
     * @since 1.0.0
     * @return void
     */
    private function renderIndexingOperations(): void
    {
        ?>
        <div class="kb-indexing-operations">
            <h2><?php esc_html_e('Indexing Operations', 'woo-ai-assistant'); ?></h2>
            
            <div class="operations-buttons">
                <button type="button" class="button button-primary" id="start-indexing">
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e('Start Full Index', 'woo-ai-assistant'); ?>
                </button>
                
                <button type="button" class="button" id="refresh-status">
                    <span class="dashicons dashicons-image-rotate"></span>
                    <?php esc_html_e('Refresh Status', 'woo-ai-assistant'); ?>
                </button>
                
                <button type="button" class="button button-link-delete" id="clear-index">
                    <span class="dashicons dashicons-trash"></span>
                    <?php esc_html_e('Clear All Index', 'woo-ai-assistant'); ?>
                </button>
            </div>
            
            <div id="indexing-progress" style="display: none;">
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
                <div class="progress-text"></div>
            </div>
            
            <div id="operation-messages"></div>
        </div>
        <?php
    }

    /**
     * Render recent activity section
     *
     * @since 1.0.0
     * @param array $stats Knowledge base statistics
     * @return void
     */
    private function renderRecentActivity(array $stats): void
    {
        $activities = $stats['recent_activities'] ?? [];
        ?>
        <div class="kb-recent-activity">
            <h2><?php esc_html_e('Recent Activity', 'woo-ai-assistant'); ?></h2>
            
            <?php if (empty($activities)) : ?>
                <p><?php esc_html_e('No recent activity to display.', 'woo-ai-assistant'); ?></p>
            <?php else : ?>
                <ul class="activity-list">
                    <?php foreach ($activities as $activity) : ?>
                        <li class="activity-item">
                            <span class="activity-time">
                                <?php echo esc_html(human_time_diff(strtotime($activity['timestamp'])) . ' ago'); ?>
                            </span>
                            <span class="activity-action <?php echo esc_attr($activity['type']); ?>">
                                <?php echo esc_html($activity['message']); ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Enqueue admin assets
     *
     * @since 1.0.0
     * @param string $hook Current admin page hook
     * @return void
     */
    public function enqueueAssets(string $hook): void
    {
        // Check if we're on our Knowledge Base page
        if (!str_contains($hook, 'woo-ai-assistant-knowledge-base')) {
            return;
        }

        // Enqueue styles - Use proper plugin URL constant
        wp_enqueue_style(
            'woo-ai-kb-status',
            WOO_AI_ASSISTANT_ASSETS_URL . 'css/kb-status.css',
            [],
            WOO_AI_ASSISTANT_VERSION
        );

        // Enqueue scripts - Use proper plugin URL constant
        wp_enqueue_script(
            'woo-ai-kb-status',
            WOO_AI_ASSISTANT_ASSETS_URL . 'js/kb-status.js',
            ['jquery'],
            WOO_AI_ASSISTANT_VERSION,
            true
        );

        // Localize script with proper nonce and AJAX URL
        wp_localize_script('woo-ai-kb-status', 'wooAiKbStatus', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('woo_ai_kb_status'),
            'strings' => [
                'confirmClear' => __('Are you sure you want to clear all indexed content? This action cannot be undone.', 'woo-ai-assistant'),
                'indexingInProgress' => __('Indexing in progress...', 'woo-ai-assistant'),
                'refreshing' => __('Refreshing status...', 'woo-ai-assistant'),
            ]
        ]);
    }

    /**
     * Get Knowledge Base statistics
     *
     * @since 1.0.0
     * @return array Statistics array
     */
    private function getKnowledgeBaseStats(): array
    {
        global $wpdb;

        $stats = [
            'health_score' => 0, // Will be calculated at the end to avoid recursion
            'total_documents' => 0,
            'products_count' => 0,
            'pages_count' => 0,
            'posts_count' => 0,
            'total_chunks' => 0,
            'total_vectors' => 0,
            'index_size' => 0,
            'last_update' => null,
            'next_scheduled_update' => null,
            'recent_activities' => [],
            'products_total' => 0,
            'pages_total' => 0,
            'posts_total' => 0,
            'products_indexed' => 0,
            'pages_indexed' => 0,
            'posts_indexed' => 0
        ];

        // Get content counts
        $stats['products_total'] = wp_count_posts('product')->publish ?? 0;
        $stats['pages_total'] = wp_count_posts('page')->publish ?? 0;
        $stats['posts_total'] = wp_count_posts('post')->publish ?? 0;

        // Get indexed counts from database
        $tableName = $wpdb->prefix . 'woo_ai_knowledge_base';
        if ($wpdb->get_var("SHOW TABLES LIKE '$tableName'") === $tableName) {
            $stats['products_indexed'] = $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM $tableName WHERE source_type = %s", 'product')
            ) ?? 0;

            $stats['pages_indexed'] = $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM $tableName WHERE source_type = %s", 'page')
            ) ?? 0;

            $stats['posts_indexed'] = $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM $tableName WHERE source_type = %s", 'post')
            ) ?? 0;

            $stats['total_chunks'] = $wpdb->get_var("SELECT COUNT(*) FROM $tableName") ?? 0;
            $stats['total_vectors'] = $stats['total_chunks']; // Assuming 1:1 mapping

            // Get last update time
            $stats['last_update'] = $wpdb->get_var("SELECT MAX(indexed_at) FROM $tableName");

            // Calculate index size (approximate) - optimized to avoid timeout
            // Skip for now to avoid timeout issues with large tables
            $stats['index_size'] = 0; // Temporarily disabled

            // Alternative: get approximate size from row count
            $avgRowSize = 5000; // Approximate average size per row in bytes
            $stats['index_size'] = $stats['total_chunks'] * $avgRowSize;
        }

        $stats['total_documents'] = $stats['products_indexed'] + $stats['pages_indexed'] + $stats['posts_indexed'];

        // Get next scheduled update
        $timestamp = wp_next_scheduled('woo_ai_assistant_index_content');
        if ($timestamp) {
            $stats['next_scheduled_update'] = $timestamp;
        }

        // Get recent activities
        $stats['recent_activities'] = $this->getRecentActivities();

        // Calculate health score AFTER getting all stats to avoid recursion
        $stats['health_score'] = $this->calculateHealthScore($stats);

        return $stats;
    }

    /**
     * Calculate health score
     *
     * @since 1.0.0
     * @return int Health score percentage
     */
    private function calculateHealthScore(?array $stats = null): int
    {
        $score = 100;
        $checks = $this->performHealthChecks($stats);

        foreach ($checks as $check) {
            if ($check['status'] === 'error') {
                $score -= 20;
            } elseif ($check['status'] === 'warning') {
                $score -= 10;
            }
        }

        return max(0, $score);
    }

    /**
     * Perform health checks
     *
     * @since 1.0.0
     * @param array|null $stats Optional stats to avoid recursion
     * @return array Health check results
     */
    private function performHealthChecks(?array $stats = null): array
    {
        $checks = [];

        // Check database tables
        global $wpdb;
        $tableName = $wpdb->prefix . 'woo_ai_knowledge_base';
        if ($wpdb->get_var("SHOW TABLES LIKE '$tableName'") === $tableName) {
            $checks[] = [
                'title' => __('Database Tables', 'woo-ai-assistant'),
                'message' => __('Knowledge base tables are properly configured', 'woo-ai-assistant'),
                'status' => 'success',
                'icon' => 'yes-alt'
            ];
        } else {
            $checks[] = [
                'title' => __('Database Tables', 'woo-ai-assistant'),
                'message' => __('Knowledge base tables are missing', 'woo-ai-assistant'),
                'status' => 'error',
                'icon' => 'dismiss',
                'action' => '<a href="#" class="button button-small">Create Tables</a>'
            ];
        }

        // Check API configuration using ApiConfiguration
        $apiConfig = \WooAiAssistant\Common\ApiConfiguration::getInstance();
        $openRouterKey = $apiConfig->getApiKey('openrouter');
        $openAiKey = $apiConfig->getApiKey('openai');
        $pineconeKey = $apiConfig->getApiKey('pinecone');

        $allKeysConfigured = !empty($openRouterKey) && !empty($openAiKey) && !empty($pineconeKey);

        if ($allKeysConfigured) {
            $checks[] = [
                'title' => __('API Configuration', 'woo-ai-assistant'),
                'message' => __('All API keys are configured', 'woo-ai-assistant'),
                'status' => 'success',
                'icon' => 'yes-alt'
            ];
        } else {
            $missingKeys = [];
            if (empty($openRouterKey)) {
                $missingKeys[] = 'OpenRouter';
            }
            if (empty($openAiKey)) {
                $missingKeys[] = 'OpenAI';
            }
            if (empty($pineconeKey)) {
                $missingKeys[] = 'Pinecone';
            }

            $checks[] = [
                'title' => __('API Configuration', 'woo-ai-assistant'),
                'message' => sprintf(__('Missing API keys: %s', 'woo-ai-assistant'), implode(', ', $missingKeys)),
                'status' => 'warning',
                'icon' => 'warning',
                'action' => '<a href="' . admin_url('admin.php?page=woo-ai-assistant-settings#api') . '">Configure API</a>'
            ];
        }

        // Check indexing status - only if stats are provided to avoid recursion
        if ($stats !== null) {
            $productsTotal = $stats['products_total'] ?? 0;
            $pagesTotal = $stats['pages_total'] ?? 0;
            $postsTotal = $stats['posts_total'] ?? 0;
            $totalContent = $productsTotal + $pagesTotal + $postsTotal;

            $productsIndexed = $stats['products_indexed'] ?? 0;
            $pagesIndexed = $stats['pages_indexed'] ?? 0;
            $postsIndexed = $stats['posts_indexed'] ?? 0;
            $totalIndexed = $productsIndexed + $pagesIndexed + $postsIndexed;

            $coverage = $totalContent > 0 ? ($totalIndexed / $totalContent) * 100 : 0;

            if ($coverage >= 80) {
                $checks[] = [
                    'title' => __('Content Coverage', 'woo-ai-assistant'),
                    'message' => sprintf(__('%d%% of content is indexed', 'woo-ai-assistant'), round($coverage)),
                    'status' => 'success',
                    'icon' => 'yes-alt'
                ];
            } elseif ($coverage >= 50) {
                $checks[] = [
                    'title' => __('Content Coverage', 'woo-ai-assistant'),
                    'message' => sprintf(__('Only %d%% of content is indexed', 'woo-ai-assistant'), round($coverage)),
                    'status' => 'warning',
                    'icon' => 'warning'
                ];
            } else {
                $checks[] = [
                    'title' => __('Content Coverage', 'woo-ai-assistant'),
                    'message' => sprintf(__('Low coverage: %d%% indexed', 'woo-ai-assistant'), round($coverage)),
                    'status' => 'error',
                    'icon' => 'dismiss'
                ];
            }
        }

        // Check cron job
        if (wp_next_scheduled('woo_ai_assistant_index_content')) {
            $checks[] = [
                'title' => __('Automatic Indexing', 'woo-ai-assistant'),
                'message' => __('Scheduled indexing is active', 'woo-ai-assistant'),
                'status' => 'success',
                'icon' => 'yes-alt'
            ];
        } else {
            $checks[] = [
                'title' => __('Automatic Indexing', 'woo-ai-assistant'),
                'message' => __('Scheduled indexing is not configured', 'woo-ai-assistant'),
                'status' => 'warning',
                'icon' => 'warning'
            ];
        }

        return $checks;
    }

    /**
     * Get content types
     *
     * @since 1.0.0
     * @return array Content types with labels
     */
    private function getContentTypes(): array
    {
        return [
            'products' => __('Products', 'woo-ai-assistant'),
            'pages' => __('Pages', 'woo-ai-assistant'),
            'posts' => __('Posts', 'woo-ai-assistant')
        ];
    }

    /**
     * Get health description based on score
     *
     * @since 1.0.0
     * @param int $score Health score
     * @return string Health description
     */
    private function getHealthDescription(int $score): string
    {
        if ($score >= 80) {
            return __('Excellent', 'woo-ai-assistant');
        } elseif ($score >= 60) {
            return __('Good', 'woo-ai-assistant');
        } elseif ($score >= 40) {
            return __('Fair', 'woo-ai-assistant');
        } else {
            return __('Needs Attention', 'woo-ai-assistant');
        }
    }

    /**
     * Format bytes to human readable
     *
     * @since 1.0.0
     * @param int $bytes Number of bytes
     * @return string Formatted string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unit = 0;

        while ($bytes >= 1024 && $unit < count($units) - 1) {
            $bytes /= 1024;
            $unit++;
        }

        return round($bytes, 2) . ' ' . $units[$unit];
    }

    /**
     * Get recent activities
     *
     * @since 1.0.0
     * @return array Recent activities
     */
    private function getRecentActivities(): array
    {
        global $wpdb;
        $activities = [];

        // Check for recent indexing activities
        $lastIndexTime = get_option('woo_ai_kb_indexing_start_time');
        $lastIndexStatus = get_option('woo_ai_kb_indexing_status', 'idle');
        if ($lastIndexTime) {
            $message = __('Indexing started', 'woo-ai-assistant');
            if ($lastIndexStatus === 'completed') {
                $message = __('Indexing completed successfully', 'woo-ai-assistant');
            } elseif ($lastIndexStatus === 'failed') {
                $message = __('Indexing failed', 'woo-ai-assistant');
            } elseif ($lastIndexStatus === 'running') {
                $progress = get_option('woo_ai_kb_indexing_progress', 0);
                $message = sprintf(__('Indexing in progress (%d%%)', 'woo-ai-assistant'), $progress);
            }

            $activities[] = [
                'timestamp' => $lastIndexTime,
                'type' => 'indexing',
                'message' => $message
            ];
        }

        // Get recent knowledge base entries
        $tableName = $wpdb->prefix . 'woo_ai_knowledge_base';
        if ($wpdb->get_var("SHOW TABLES LIKE '$tableName'") === $tableName) {
            $recentEntries = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT COUNT(*) as count, MAX(indexed_at) as last_indexed 
                     FROM {$tableName} 
                     WHERE indexed_at > %s",
                    date('Y-m-d H:i:s', strtotime('-7 days'))
                )
            );

            if (!empty($recentEntries) && $recentEntries[0]->count > 0) {
                $activities[] = [
                    'timestamp' => $recentEntries[0]->last_indexed,
                    'type' => 'scan',
                    'message' => sprintf(__('Knowledge base updated: %d items indexed', 'woo-ai-assistant'), $recentEntries[0]->count)
                ];
            }
        }

        // Check for plugin activation
        $activationTime = get_option('woo_ai_assistant_activated_at');
        if ($activationTime && strtotime($activationTime) > strtotime('-7 days')) {
            $activities[] = [
                'timestamp' => $activationTime,
                'type' => 'activation',
                'message' => __('Plugin activated', 'woo-ai-assistant')
            ];
        }

        // If no real activities, show informative message
        if (empty($activities)) {
            $activities[] = [
                'timestamp' => current_time('mysql'),
                'type' => 'info',
                'message' => __('No recent activity. Click "Start Full Index" to begin indexing.', 'woo-ai-assistant')
            ];
        }

        // Sort by timestamp (most recent first)
        usort($activities, function ($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });

        // Limit to 10 most recent activities
        return array_slice($activities, 0, 10);
    }

    /**
     * Handle refresh status AJAX request
     *
     * @since 1.0.0
     * @return void
     */
    public function handleRefreshStatus(): void
    {
        check_ajax_referer('woo_ai_kb_status', 'nonce');

        if (!current_user_can(self::REQUIRED_CAPABILITY)) {
            wp_send_json_error(__('Insufficient permissions', 'woo-ai-assistant'));
        }

        $stats = $this->getKnowledgeBaseStats();
        wp_send_json_success($stats);
    }

    /**
     * Handle start indexing AJAX request
     *
     * @since 1.0.0
     * @return void
     */
    public function handleStartIndexing(): void
    {
        check_ajax_referer('woo_ai_kb_status', 'nonce');

        if (!current_user_can(self::REQUIRED_CAPABILITY)) {
            wp_send_json_error(__('Insufficient permissions', 'woo-ai-assistant'));
        }

        try {
            // Use the new IndexingProcessor
            $processor = IndexingProcessor::getInstance();

            // Start indexing all content
            $result = $processor->startIndexing('all');

            if ($result['success']) {
                wp_send_json_success([
                    'message' => $result['message']
                ]);
            } else {
                wp_send_json_error($result['message']);
            }
        } catch (\Exception $e) {
            update_option('woo_ai_kb_indexing_status', 'failed');
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Handle check indexing status AJAX request
     *
     * @since 1.0.0
     * @return void
     */
    public function handleCheckIndexingStatus(): void
    {
        check_ajax_referer('woo_ai_kb_status', 'nonce');

        if (!current_user_can(self::REQUIRED_CAPABILITY)) {
            wp_send_json_error(__('Insufficient permissions', 'woo-ai-assistant'));
        }

        // Use the new IndexingProcessor to get status
        $processor = IndexingProcessor::getInstance();
        $statusInfo = $processor->getIndexingStatus();

        // If completed, reset status for next run
        if ($statusInfo['status'] === 'completed') {
            update_option('woo_ai_kb_indexing_status', 'idle');
        }

        wp_send_json_success([
            'status' => $statusInfo['status'],
            'progress' => $statusInfo['progress'],
            'message' => $statusInfo['message'],
            'total' => $statusInfo['total'],
            'processed' => $statusInfo['processed'],
            'errors' => $statusInfo['errors']
        ]);
    }

    /**
     * Handle clear index AJAX request
     *
     * @since 1.0.0
     * @return void
     */
    public function handleClearIndex(): void
    {
        check_ajax_referer('woo_ai_kb_status', 'nonce');

        if (!current_user_can(self::REQUIRED_CAPABILITY)) {
            wp_send_json_error(__('Insufficient permissions', 'woo-ai-assistant'));
        }

        global $wpdb;
        $tableName = $wpdb->prefix . 'woo_ai_knowledge_base';

        // Clear the knowledge base table
        $wpdb->query("TRUNCATE TABLE $tableName");

        wp_send_json_success([
            'message' => __('Knowledge base index cleared successfully', 'woo-ai-assistant')
        ]);
    }
}