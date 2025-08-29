<?php

/**
 * Knowledge Base Cron Manager Class
 *
 * Handles automated indexing, maintenance, and background processing tasks
 * for the Knowledge Base system using WordPress cron jobs.
 *
 * @package WooAiAssistant
 * @subpackage KnowledgeBase
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\KnowledgeBase;

use WooAiAssistant\Common\Utils;
use WooAiAssistant\Common\Traits\Singleton;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class CronManager
 *
 * Manages all automated background tasks for the Knowledge Base system,
 * including scheduled indexing, maintenance, cleanup, and health monitoring.
 *
 * @since 1.0.0
 */
class CronManager
{
    use Singleton;

    /**
     * Cron job hooks and their schedules
     *
     * @since 1.0.0
     * @var array
     */
    private array $cronJobs = [
        'woo_ai_assistant_initial_index' => 'once',
        'woo_ai_assistant_hourly_sync' => 'hourly',
        'woo_ai_assistant_daily_maintenance' => 'daily',
        'woo_ai_assistant_weekly_cleanup' => 'weekly',
        'woo_ai_assistant_reindex_settings' => 'once'
    ];

    /**
     * Default batch sizes for different operations
     *
     * @since 1.0.0
     * @var array
     */
    private array $batchSizes = [
        'products' => 50,
        'pages' => 25,
        'posts' => 30,
        'categories' => 100,
        'maintenance' => 100
    ];

    /**
     * Processing status
     *
     * @since 1.0.0
     * @var array
     */
    private array $processingStatus = [];

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    private function __construct()
    {
        $this->setupHooks();
        $this->initializeProcessingStatus();
    }

    /**
     * Setup WordPress hooks
     *
     * @since 1.0.0
     * @return void
     */
    private function setupHooks(): void
    {
        // Register cron schedules
        add_filter('cron_schedules', [$this, 'addCustomCronSchedules']);

        // Register cron job handlers
        foreach ($this->cronJobs as $hook => $schedule) {
            add_action($hook, [$this, 'handleCronJob']);
        }

        // Plugin activation/deactivation hooks
        add_action('woo_ai_assistant_activated', [$this, 'scheduleInitialJobs']);
        add_action('woo_ai_assistant_deactivated', [$this, 'clearScheduledJobs']);

        // Real-time update hooks for immediate processing
        add_action('woocommerce_update_product', [$this, 'scheduleProductUpdate'], 10, 1);
        add_action('save_post', [$this, 'schedulePostUpdate'], 10, 3);
        add_action('woocommerce_settings_saved', [$this, 'scheduleSettingsReindex'], 10);

        Utils::logDebug('CronManager hooks registered');
    }

    /**
     * Initialize processing status
     *
     * @since 1.0.0
     * @return void
     */
    private function initializeProcessingStatus(): void
    {
        $this->processingStatus = get_option('woo_ai_assistant_cron_status', [
            'last_full_index' => null,
            'last_maintenance' => null,
            'current_operations' => [],
            'errors' => []
        ]);
    }

    /**
     * Add custom cron schedules
     *
     * @since 1.0.0
     * @param array $schedules Existing cron schedules
     * @return array Modified schedules
     */
    public function addCustomCronSchedules(array $schedules): array
    {
        $schedules['every_15_minutes'] = [
            'interval' => 15 * MINUTE_IN_SECONDS,
            'display' => __('Every 15 Minutes', 'woo-ai-assistant')
        ];

        $schedules['twice_daily'] = [
            'interval' => 12 * HOUR_IN_SECONDS,
            'display' => __('Twice Daily', 'woo-ai-assistant')
        ];

        return $schedules;
    }

    /**
     * Schedule initial jobs on plugin activation
     *
     * @since 1.0.0
     * @return void
     */
    public function scheduleInitialJobs(): void
    {
        try {
            Utils::logDebug('Scheduling initial Knowledge Base jobs');

            // Schedule initial indexing (delayed by 2 minutes to ensure plugin is fully loaded)
            if (!wp_next_scheduled('woo_ai_assistant_initial_index')) {
                wp_schedule_single_event(time() + 120, 'woo_ai_assistant_initial_index');
                Utils::logDebug('Initial index job scheduled');
            }

            // Schedule recurring jobs
            $this->scheduleRecurringJobs();

            // Update status
            $this->processingStatus['scheduled_at'] = current_time('mysql');
            $this->updateProcessingStatus();
        } catch (Exception $e) {
            Utils::logError('Failed to schedule initial jobs: ' . $e->getMessage());
        }
    }

    /**
     * Schedule recurring jobs
     *
     * @since 1.0.0
     * @return void
     */
    private function scheduleRecurringJobs(): void
    {
        // Hourly sync for incremental updates
        if (!wp_next_scheduled('woo_ai_assistant_hourly_sync')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', 'woo_ai_assistant_hourly_sync');
        }

        // Daily maintenance
        if (!wp_next_scheduled('woo_ai_assistant_daily_maintenance')) {
            $dailyTime = strtotime('02:00:00'); // 2 AM local time
            if ($dailyTime <= time()) {
                $dailyTime += DAY_IN_SECONDS; // Next day if time has passed
            }
            wp_schedule_event($dailyTime, 'daily', 'woo_ai_assistant_daily_maintenance');
        }

        // Weekly cleanup
        if (!wp_next_scheduled('woo_ai_assistant_weekly_cleanup')) {
            $weeklyTime = strtotime('Sunday 03:00:00'); // Sunday 3 AM
            if ($weeklyTime <= time()) {
                $weeklyTime += WEEK_IN_SECONDS; // Next week if time has passed
            }
            wp_schedule_event($weeklyTime, 'weekly', 'woo_ai_assistant_weekly_cleanup');
        }

        Utils::logDebug('Recurring jobs scheduled');
    }

    /**
     * Clear all scheduled jobs
     *
     * @since 1.0.0
     * @return void
     */
    public function clearScheduledJobs(): void
    {
        foreach ($this->cronJobs as $hook => $schedule) {
            $timestamp = wp_next_scheduled($hook);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
                Utils::logDebug("Cleared scheduled job: {$hook}");
            }
        }

        // Clear any single events that might be pending
        wp_clear_scheduled_hook('woo_ai_assistant_initial_index');
        wp_clear_scheduled_hook('woo_ai_assistant_reindex_settings');

        Utils::logDebug('All scheduled jobs cleared');
    }

    /**
     * Handle cron job execution
     *
     * @since 1.0.0
     * @param string $hook Cron job hook name
     * @return void
     */
    public function handleCronJob(string $hook = ''): void
    {
        if (empty($hook)) {
            $hook = current_action();
        }

        try {
            Utils::logDebug("Executing cron job: {$hook}");
            $this->markOperationStart($hook);

            switch ($hook) {
                case 'woo_ai_assistant_initial_index':
                    $this->performInitialIndexing();
                    break;

                case 'woo_ai_assistant_hourly_sync':
                    $this->performHourlySync();
                    break;

                case 'woo_ai_assistant_daily_maintenance':
                    $this->performDailyMaintenance();
                    break;

                case 'woo_ai_assistant_weekly_cleanup':
                    $this->performWeeklyCleanup();
                    break;

                case 'woo_ai_assistant_reindex_settings':
                    $this->reindexSettings();
                    break;

                default:
                    Utils::logError("Unknown cron job: {$hook}");
                    return;
            }

            $this->markOperationComplete($hook);
            Utils::logDebug("Cron job completed successfully: {$hook}");
        } catch (Exception $e) {
            $this->markOperationError($hook, $e->getMessage());
            Utils::logError("Cron job failed ({$hook}): " . $e->getMessage());
        }
    }

    /**
     * Perform initial indexing of all content
     *
     * @since 1.0.0
     * @return void
     */
    private function performInitialIndexing(): void
    {
        Utils::logDebug('Starting initial Knowledge Base indexing');

        $main = \WooAiAssistant\Main::getInstance();
        $scanner = $main->getComponent('kb_scanner');
        $indexer = $main->getComponent('kb_indexer');

        if (!$scanner || !$indexer) {
            throw new \Exception('Required KB components not available');
        }

        $totalProcessed = 0;
        $startTime = microtime(true);

        // Index products in batches
        $productOffset = 0;
        do {
            $products = $scanner->processBatch('products', $productOffset, $this->batchSizes['products']);
            if (!empty($products)) {
                $indexer->indexContent($products);
                $totalProcessed += count($products);
                $productOffset += $this->batchSizes['products'];

                // Prevent memory exhaustion
                if (function_exists('wp_cache_flush')) {
                    wp_cache_flush();
                }
            }
        } while (!empty($products));

        // Index pages in batches
        $pageOffset = 0;
        do {
            $pages = $scanner->processBatch('pages', $pageOffset, $this->batchSizes['pages']);
            if (!empty($pages)) {
                $indexer->indexContent($pages);
                $totalProcessed += count($pages);
                $pageOffset += $this->batchSizes['pages'];
            }
        } while (!empty($pages));

        // Index WooCommerce settings
        $wooSettings = $scanner->scanWooSettings();
        if (!empty($wooSettings)) {
            $indexer->indexContent($wooSettings);
            $totalProcessed += count($wooSettings);
        }

        $executionTime = microtime(true) - $startTime;

        $this->processingStatus['last_full_index'] = current_time('mysql');
        $this->processingStatus['last_full_index_stats'] = [
            'total_processed' => $totalProcessed,
            'execution_time' => round($executionTime, 2),
            'memory_peak' => memory_get_peak_usage(true)
        ];

        Utils::logDebug("Initial indexing completed: {$totalProcessed} items in {$executionTime}s");
    }

    /**
     * Perform hourly synchronization
     *
     * @since 1.0.0
     * @return void
     */
    private function performHourlySync(): void
    {
        Utils::logDebug('Starting hourly sync');

        $main = \WooAiAssistant\Main::getInstance();
        $scanner = $main->getComponent('kb_scanner');
        $indexer = $main->getComponent('kb_indexer');

        if (!$scanner || !$indexer) {
            throw new \Exception('Required KB components not available');
        }

        // Get recently modified content (last 2 hours to ensure we don't miss anything)
        $since = date('Y-m-d H:i:s', strtotime('-2 hours'));

        // Check for updated products
        $updatedProducts = $this->getRecentlyUpdatedProducts($since);
        if (!empty($updatedProducts)) {
            $productData = $scanner->scanProducts(['post__in' => $updatedProducts]);
            if (!empty($productData)) {
                $indexer->indexContent($productData, ['update_existing' => true]);
                Utils::logDebug('Synced ' . count($productData) . ' updated products');
            }
        }

        // Check for updated pages/posts
        $updatedPosts = $this->getRecentlyUpdatedPosts($since);
        if (!empty($updatedPosts)) {
            $postData = $scanner->scanPages(['post__in' => $updatedPosts]);
            if (!empty($postData)) {
                $indexer->indexContent($postData, ['update_existing' => true]);
                Utils::logDebug('Synced ' . count($postData) . ' updated posts/pages');
            }
        }

        Utils::logDebug('Hourly sync completed');
    }

    /**
     * Perform daily maintenance tasks
     *
     * @since 1.0.0
     * @return void
     */
    private function performDailyMaintenance(): void
    {
        Utils::logDebug('Starting daily maintenance');

        $main = \WooAiAssistant\Main::getInstance();

        // Perform health check
        if (method_exists($main, 'performDailyMaintenance')) {
            $main->performDailyMaintenance();
        }

        // Cleanup expired cache entries
        $this->cleanupExpiredCache();

        // Update processing statistics
        $this->updateProcessingStatistics();

        // Check for orphaned KB entries
        $this->cleanupOrphanedKBEntries();

        $this->processingStatus['last_maintenance'] = current_time('mysql');

        Utils::logDebug('Daily maintenance completed');
    }

    /**
     * Perform weekly cleanup tasks
     *
     * @since 1.0.0
     * @return void
     */
    private function performWeeklyCleanup(): void
    {
        Utils::logDebug('Starting weekly cleanup');

        // Clean up old conversation data
        $this->cleanupOldConversations();

        // Optimize database tables
        $this->optimizeDatabaseTables();

        // Clean up temporary files
        $this->cleanupTemporaryFiles();

        // Reset error logs if they're getting too large
        $this->rotateErrorLogs();

        Utils::logDebug('Weekly cleanup completed');
    }

    /**
     * Reindex WooCommerce settings
     *
     * @since 1.0.0
     * @return void
     */
    private function reindexSettings(): void
    {
        Utils::logDebug('Reindexing WooCommerce settings');

        $main = \WooAiAssistant\Main::getInstance();
        $scanner = $main->getComponent('kb_scanner');
        $indexer = $main->getComponent('kb_indexer');

        if (!$scanner || !$indexer) {
            throw new \Exception('Required KB components not available');
        }

        // Remove old settings from KB
        $indexer->removeContent('woo_settings');

        // Scan and reindex current settings
        $wooSettings = $scanner->scanWooSettings();
        if (!empty($wooSettings)) {
            $indexer->indexContent($wooSettings);
            Utils::logDebug('Reindexed ' . count($wooSettings) . ' WooCommerce settings');
        }
    }

    /**
     * Schedule product update for processing
     *
     * @since 1.0.0
     * @param int|\WC_Product $product Product ID or object
     * @return void
     */
    public function scheduleProductUpdate($product): void
    {
        $productId = is_object($product) ? $product->get_id() : $product;

        // Don't schedule if we're already processing this product
        if ($this->isProductBeingProcessed($productId)) {
            return;
        }

        // Schedule for processing in the next hourly sync
        $pendingUpdates = get_transient('woo_ai_pending_product_updates') ?: [];
        $pendingUpdates[] = $productId;
        set_transient('woo_ai_pending_product_updates', array_unique($pendingUpdates), HOUR_IN_SECONDS);

        Utils::logDebug("Scheduled product update for processing: {$productId}");
    }

    /**
     * Schedule post update for processing
     *
     * @since 1.0.0
     * @param int $postId Post ID
     * @param \WP_Post $post Post object
     * @param bool $update Whether this is an update
     * @return void
     */
    public function schedulePostUpdate(int $postId, \WP_Post $post, bool $update): void
    {
        // Only process public posts and pages
        if (!in_array($post->post_type, ['post', 'page']) || $post->post_status !== 'publish') {
            return;
        }

        $pendingUpdates = get_transient('woo_ai_pending_post_updates') ?: [];
        $pendingUpdates[] = $postId;
        set_transient('woo_ai_pending_post_updates', array_unique($pendingUpdates), HOUR_IN_SECONDS);

        Utils::logDebug("Scheduled post update for processing: {$postId}");
    }

    /**
     * Schedule settings reindexing
     *
     * @since 1.0.0
     * @return void
     */
    public function scheduleSettingsReindex(): void
    {
        if (!wp_next_scheduled('woo_ai_assistant_reindex_settings')) {
            wp_schedule_single_event(time() + 300, 'woo_ai_assistant_reindex_settings'); // 5 minutes delay
            Utils::logDebug('Scheduled settings reindexing');
        }
    }

    /**
     * Get recently updated products
     *
     * @since 1.0.0
     * @param string $since DateTime string
     * @return array Product IDs
     */
    private function getRecentlyUpdatedProducts(string $since): array
    {
        $args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'date_query' => [
                [
                    'after' => $since,
                    'column' => 'post_modified'
                ]
            ],
            'fields' => 'ids',
            'posts_per_page' => $this->batchSizes['products']
        ];

        return \get_posts($args);
    }

    /**
     * Get recently updated posts/pages
     *
     * @since 1.0.0
     * @param string $since DateTime string
     * @return array Post IDs
     */
    private function getRecentlyUpdatedPosts(string $since): array
    {
        $args = [
            'post_type' => ['post', 'page'],
            'post_status' => 'publish',
            'date_query' => [
                [
                    'after' => $since,
                    'column' => 'post_modified'
                ]
            ],
            'fields' => 'ids',
            'posts_per_page' => $this->batchSizes['pages']
        ];

        return \get_posts($args);
    }

    /**
     * Check if product is currently being processed
     *
     * @since 1.0.0
     * @param int $productId Product ID
     * @return bool
     */
    private function isProductBeingProcessed(int $productId): bool
    {
        $processing = get_transient('woo_ai_processing_products') ?: [];
        return in_array($productId, $processing);
    }

    /**
     * Cleanup expired cache entries
     *
     * @since 1.0.0
     * @return void
     */
    private function cleanupExpiredCache(): void
    {
        // Clear expired transients related to our plugin
        global $wpdb;

        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_timeout_woo_ai_%' 
             AND option_value < UNIX_TIMESTAMP()"
        );

        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_woo_ai_%'
             AND option_name NOT IN (
                 SELECT CONCAT('_transient_', SUBSTR(option_name, 20))
                 FROM {$wpdb->options} t2 
                 WHERE t2.option_name LIKE '_transient_timeout_woo_ai_%'
             )"
        );

        Utils::logDebug('Expired cache entries cleaned up');
    }

    /**
     * Update processing statistics
     *
     * @since 1.0.0
     * @return void
     */
    private function updateProcessingStatistics(): void
    {
        global $wpdb;

        $stats = [
            'conversations_today' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}woo_ai_conversations WHERE DATE(created_at) = %s",
                current_time('Y-m-d')
            )),
            'kb_entries_total' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}woo_ai_knowledge_base"),
            'avg_response_time' => $wpdb->get_var("SELECT AVG(response_time) FROM {$wpdb->prefix}woo_ai_conversations WHERE response_time > 0"),
            'last_updated' => current_time('mysql')
        ];

        update_option('woo_ai_assistant_daily_stats', $stats);
    }

    /**
     * Cleanup orphaned KB entries
     *
     * @since 1.0.0
     * @return void
     */
    private function cleanupOrphanedKBEntries(): void
    {
        global $wpdb;

        // Remove KB entries for deleted posts/products
        $orphaned = $wpdb->query("
            DELETE kb FROM {$wpdb->prefix}woo_ai_knowledge_base kb
            LEFT JOIN {$wpdb->posts} p ON kb.source_id = p.ID
            WHERE kb.content_type IN ('product', 'post', 'page') AND p.ID IS NULL
        ");

        if ($orphaned > 0) {
            Utils::logDebug("Cleaned up {$orphaned} orphaned KB entries");
        }
    }

    /**
     * Cleanup old conversations
     *
     * @since 1.0.0
     * @return void
     */
    private function cleanupOldConversations(): void
    {
        global $wpdb;

        $retentionDays = apply_filters('woo_ai_assistant_conversation_retention_days', 90);
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}woo_ai_conversations WHERE created_at < %s AND status = 'completed'",
            $cutoffDate
        ));

        if ($deleted > 0) {
            Utils::logDebug("Cleaned up {$deleted} old conversations");
        }
    }

    /**
     * Optimize database tables
     *
     * @since 1.0.0
     * @return void
     */
    private function optimizeDatabaseTables(): void
    {
        global $wpdb;

        $tables = [
            $wpdb->prefix . 'woo_ai_conversations',
            $wpdb->prefix . 'woo_ai_messages',
            $wpdb->prefix . 'woo_ai_knowledge_base',
            $wpdb->prefix . 'woo_ai_usage_stats'
        ];

        foreach ($tables as $table) {
            $wpdb->query("OPTIMIZE TABLE {$table}");
        }

        Utils::logDebug('Database tables optimized');
    }

    /**
     * Cleanup temporary files
     *
     * @since 1.0.0
     * @return void
     */
    private function cleanupTemporaryFiles(): void
    {
        $uploadDir = wp_upload_dir();
        $tempDir = $uploadDir['basedir'] . '/woo-ai-assistant/temp/';

        if (is_dir($tempDir)) {
            $files = glob($tempDir . '*');
            $weekAgo = time() - WEEK_IN_SECONDS;

            foreach ($files as $file) {
                if (is_file($file) && filemtime($file) < $weekAgo) {
                    unlink($file);
                }
            }

            Utils::logDebug('Temporary files cleaned up');
        }
    }

    /**
     * Rotate error logs
     *
     * @since 1.0.0
     * @return void
     */
    private function rotateErrorLogs(): void
    {
        $maxErrors = apply_filters('woo_ai_assistant_max_error_entries', 1000);

        if (count($this->processingStatus['errors']) > $maxErrors) {
            $this->processingStatus['errors'] = array_slice($this->processingStatus['errors'], -$maxErrors / 2);
            Utils::logDebug('Error log rotated');
        }
    }

    /**
     * Mark operation start
     *
     * @since 1.0.0
     * @param string $operation Operation name
     * @return void
     */
    private function markOperationStart(string $operation): void
    {
        $this->processingStatus['current_operations'][$operation] = [
            'started_at' => current_time('mysql'),
            'status' => 'running'
        ];
        $this->updateProcessingStatus();
    }

    /**
     * Mark operation complete
     *
     * @since 1.0.0
     * @param string $operation Operation name
     * @return void
     */
    private function markOperationComplete(string $operation): void
    {
        if (isset($this->processingStatus['current_operations'][$operation])) {
            $this->processingStatus['current_operations'][$operation]['status'] = 'completed';
            $this->processingStatus['current_operations'][$operation]['completed_at'] = current_time('mysql');
        }
        $this->updateProcessingStatus();
    }

    /**
     * Mark operation error
     *
     * @since 1.0.0
     * @param string $operation Operation name
     * @param string $error Error message
     * @return void
     */
    private function markOperationError(string $operation, string $error): void
    {
        if (isset($this->processingStatus['current_operations'][$operation])) {
            $this->processingStatus['current_operations'][$operation]['status'] = 'error';
            $this->processingStatus['current_operations'][$operation]['error'] = $error;
            $this->processingStatus['current_operations'][$operation]['failed_at'] = current_time('mysql');
        }

        $this->processingStatus['errors'][] = [
            'operation' => $operation,
            'error' => $error,
            'timestamp' => current_time('mysql')
        ];

        $this->updateProcessingStatus();
    }

    /**
     * Update processing status in database
     *
     * @since 1.0.0
     * @return void
     */
    private function updateProcessingStatus(): void
    {
        update_option('woo_ai_assistant_cron_status', $this->processingStatus);
    }

    /**
     * Get processing status
     *
     * @since 1.0.0
     * @return array Processing status
     */
    public function getProcessingStatus(): array
    {
        return $this->processingStatus;
    }

    /**
     * Get next scheduled jobs
     *
     * @since 1.0.0
     * @return array Scheduled jobs
     */
    public function getScheduledJobs(): array
    {
        $scheduled = [];

        foreach ($this->cronJobs as $hook => $schedule) {
            $timestamp = wp_next_scheduled($hook);
            $scheduled[$hook] = [
                'schedule' => $schedule,
                'next_run' => $timestamp ? date('Y-m-d H:i:s', $timestamp) : null,
                'next_run_timestamp' => $timestamp
            ];
        }

        return $scheduled;
    }

    /**
     * Manually trigger a specific job
     *
     * @since 1.0.0
     * @param string $jobName Job name
     * @return bool Success status
     */
    public function triggerJob(string $jobName): bool
    {
        if (!array_key_exists($jobName, $this->cronJobs)) {
            return false;
        }

        try {
            $this->handleCronJob($jobName);
            return true;
        } catch (Exception $e) {
            Utils::logError("Manual job trigger failed ({$jobName}): " . $e->getMessage());
            return false;
        }
    }
}
