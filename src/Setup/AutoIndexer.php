<?php

/**
 * Auto Indexer Class
 *
 * Handles automatic content indexing immediately after plugin activation to implement
 * the zero-config philosophy. Ensures the knowledge base is populated with content
 * from the moment the plugin is activated.
 *
 * @package WooAiAssistant
 * @subpackage Setup
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Setup;

use WooAiAssistant\Common\Utils;
use WooAiAssistant\Common\Traits\Singleton;
use WooAiAssistant\KnowledgeBase\Scanner;
use WooAiAssistant\KnowledgeBase\Indexer;
use WooAiAssistant\KnowledgeBase\VectorManager;
use WooAiAssistant\Setup\WooCommerceDetector;
use WooAiAssistant\Setup\DefaultMessageSetup;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AutoIndexer
 *
 * Manages automatic knowledge base population on plugin activation.
 * Implements zero-config philosophy by ensuring immediate functionality.
 *
 * @since 1.0.0
 */
class AutoIndexer
{
    use Singleton;

    /**
     * Maximum products to index in initial run
     *
     * @since 1.0.0
     * @var int
     */
    const MAX_INITIAL_PRODUCTS = 50;

    /**
     * Maximum pages to index in initial run
     *
     * @since 1.0.0
     * @var int
     */
    const MAX_INITIAL_PAGES = 20;

    /**
     * Batch size for processing content chunks
     *
     * @since 1.0.0
     * @var int
     */
    const BATCH_SIZE = 10;

    /**
     * Time limit for auto-indexing process (seconds)
     *
     * @since 1.0.0
     * @var int
     */
    const TIME_LIMIT = 120;

    /**
     * Auto-indexing status
     *
     * @since 1.0.0
     * @var array
     */
    private array $indexingStatus = [];

    /**
     * Start time for indexing process
     *
     * @since 1.0.0
     * @var int
     */
    private int $startTime;

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    private function __construct()
    {
        $this->startTime = time();
        $this->initializeStatus();
        $this->setupHooks();
    }

    /**
     * Initialize indexing status
     *
     * @since 1.0.0
     * @return void
     */
    private function initializeStatus(): void
    {
        $this->indexingStatus = [
            'started_at' => current_time('mysql'),
            'status' => 'starting',
            'progress' => 0,
            'total_items' => 0,
            'processed_items' => 0,
            'products_indexed' => 0,
            'pages_indexed' => 0,
            'settings_indexed' => 0,
            'errors' => [],
            'last_updated' => current_time('mysql')
        ];
    }

    /**
     * Setup WordPress hooks for auto-indexing
     *
     * @since 1.0.0
     * @return void
     */
    private function setupHooks(): void
    {
        // Handle initial auto-indexing on activation
        add_action('woo_ai_assistant_auto_index', [$this, 'performAutoIndexing']);

        // Handle background indexing completion
        add_action('woo_ai_assistant_auto_index_complete', [$this, 'onAutoIndexingComplete']);

        // Cleanup on plugin deactivation
        add_action('deactivate_' . plugin_basename(WOO_AI_ASSISTANT_PLUGIN_FILE), [$this, 'cleanup']);
    }

    /**
     * Trigger auto-indexing on plugin activation
     *
     * This method is called immediately after plugin activation to start
     * the automatic knowledge base population process.
     *
     * @since 1.0.0
     * @param bool $immediate Whether to run immediately or schedule for background
     * @return array|bool Auto-indexing result or true if scheduled
     */
    public function triggerAutoIndexing(bool $immediate = false)
    {
        try {
            Utils::logDebug('Auto-indexing triggered');

            // Check if auto-indexing is enabled
            if (!$this->isAutoIndexingEnabled()) {
                Utils::logDebug('Auto-indexing is disabled');
                return false;
            }

            // Check if already running or completed recently
            if ($this->isRecentlyCompleted() && !$immediate) {
                Utils::logDebug('Auto-indexing was recently completed - skipping');
                return false;
            }

            // Set status to starting
            $this->updateStatus('starting', 'Auto-indexing process initiated');

            if ($immediate || $this->canRunImmediately()) {
                return $this->performAutoIndexing();
            } else {
                // Schedule for background processing
                $this->scheduleBackgroundIndexing();
                return true;
            }
        } catch (\Exception $e) {
            Utils::logError('Failed to trigger auto-indexing: ' . $e->getMessage());
            $this->updateStatus('failed', $e->getMessage());
            return false;
        }
    }

    /**
     * Check if auto-indexing is enabled
     *
     * @since 1.0.0
     * @return bool True if auto-indexing is enabled
     */
    private function isAutoIndexingEnabled(): bool
    {
        return get_option('woo_ai_assistant_auto_index', 'yes') === 'yes';
    }

    /**
     * Check if auto-indexing was recently completed
     *
     * @since 1.0.0
     * @param int $hours Hours to consider as "recent"
     * @return bool True if recently completed
     */
    private function isRecentlyCompleted(int $hours = 24): bool
    {
        $lastCompleted = get_option('woo_ai_assistant_last_auto_index', 0);
        return $lastCompleted && (time() - $lastCompleted) < ($hours * HOUR_IN_SECONDS);
    }

    /**
     * Check if we can run auto-indexing immediately
     *
     * @since 1.0.0
     * @return bool True if can run immediately
     */
    private function canRunImmediately(): bool
    {
        // Check if we're in admin and not during AJAX request
        if (!is_admin() || wp_doing_ajax()) {
            return false;
        }

        // Check server resources
        return $this->hasAdequateResources();
    }

    /**
     * Check if server has adequate resources for immediate indexing
     *
     * @since 1.0.0
     * @return bool True if resources are adequate
     */
    private function hasAdequateResources(): bool
    {
        // Check memory limit
        $memoryLimit = \wp_convert_hr_to_bytes(ini_get('memory_limit'));
        $memoryUsage = memory_get_usage(true);
        $availableMemory = $memoryLimit - $memoryUsage;

        // Need at least 64MB available
        if ($availableMemory < 67108864) {
            Utils::logDebug('Insufficient memory for immediate auto-indexing');
            return false;
        }

        // Check execution time
        $maxExecutionTime = ini_get('max_execution_time');
        if ($maxExecutionTime > 0 && $maxExecutionTime < self::TIME_LIMIT) {
            Utils::logDebug('Insufficient execution time for immediate auto-indexing');
            return false;
        }

        return true;
    }

    /**
     * Schedule background auto-indexing
     *
     * @since 1.0.0
     * @return void
     */
    private function scheduleBackgroundIndexing(): void
    {
        // Cancel any existing scheduled event
        wp_clear_scheduled_hook('woo_ai_assistant_auto_index');

        // Schedule new event for 2 minutes from now
        wp_schedule_single_event(time() + 120, 'woo_ai_assistant_auto_index');

        Utils::logDebug('Auto-indexing scheduled for background processing');
        $this->updateStatus('scheduled', 'Auto-indexing scheduled for background processing');
    }

    /**
     * Perform the actual auto-indexing process
     *
     * @since 1.0.0
     * @return array Indexing results
     */
    public function performAutoIndexing(): array
    {
        try {
            Utils::logDebug('Starting auto-indexing process');
            $this->updateStatus('running', 'Auto-indexing process started');

            // Initialize components
            if (!$this->initializeComponents()) {
                throw new \Exception('Failed to initialize required components');
            }

            $results = [
                'products' => 0,
                'pages' => 0,
                'settings' => 0,
                'total_chunks' => 0,
                'errors' => []
            ];

            // Step 1: Index WooCommerce products
            if ($this->shouldIndexProducts()) {
                $results['products'] = $this->indexProducts();
            }

            // Step 2: Index important pages
            if ($this->shouldIndexPages()) {
                $results['pages'] = $this->indexPages();
            }

            // Step 3: Index WooCommerce settings
            if ($this->shouldIndexSettings()) {
                $results['settings'] = $this->indexWooCommerceSettings();
            }

            // Step 4: Process chunks and generate embeddings
            $results['total_chunks'] = $this->processChunks();

            // Update completion status
            $this->completeAutoIndexing($results);

            Utils::logDebug('Auto-indexing process completed', $results);
            return $results;
        } catch (\Exception $e) {
            Utils::logError('Auto-indexing failed: ' . $e->getMessage());
            $this->updateStatus('failed', $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Initialize required components
     *
     * @since 1.0.0
     * @return bool True if all components initialized successfully
     */
    private function initializeComponents(): bool
    {
        try {
            // We'll use the components loaded by Main.php
            // Check if required classes exist
            if (!class_exists('WooAiAssistant\KnowledgeBase\Scanner')) {
                throw new \Exception('Scanner class not available');
            }

            if (!class_exists('WooAiAssistant\KnowledgeBase\Indexer')) {
                throw new \Exception('Indexer class not available');
            }

            return true;
        } catch (\Exception $e) {
            Utils::logError('Failed to initialize auto-indexing components: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if products should be indexed
     *
     * @since 1.0.0
     * @return bool True if products should be indexed
     */
    private function shouldIndexProducts(): bool
    {
        return Utils::isWooCommerceActive() &&
               get_option('woo_ai_assistant_index_products', 'yes') === 'yes';
    }

    /**
     * Check if pages should be indexed
     *
     * @since 1.0.0
     * @return bool True if pages should be indexed
     */
    private function shouldIndexPages(): bool
    {
        return get_option('woo_ai_assistant_index_pages', 'yes') === 'yes';
    }

    /**
     * Check if settings should be indexed
     *
     * @since 1.0.0
     * @return bool True if settings should be indexed
     */
    private function shouldIndexSettings(): bool
    {
        return Utils::isWooCommerceActive();
    }

    /**
     * Index WooCommerce products
     *
     * @since 1.0.0
     * @return int Number of products indexed
     */
    private function indexProducts(): int
    {
        try {
            $scanner = Scanner::getInstance();
            $indexer = Indexer::getInstance();

            // Get products to index
            $products = $scanner->scanProducts([
                'limit' => self::MAX_INITIAL_PRODUCTS,
                'status' => ['publish'],
                'type' => ['simple', 'variable']
            ]);

            $indexedCount = 0;
            $processedCount = 0;

            foreach ($products as $product) {
                // Check time limit
                if ($this->hasExceededTimeLimit()) {
                    Utils::logDebug("Time limit exceeded during product indexing. Processed: {$processedCount}");
                    break;
                }

                try {
                    // Index the product
                    $chunks = $indexer->indexContent([
                        'source_type' => 'product',
                        'source_id' => $product['id'],
                        'title' => $product['title'],
                        'content' => $product['content'],
                        'url' => $product['url'],
                        'metadata' => $product['metadata'] ?? []
                    ]);

                    if ($chunks > 0) {
                        $indexedCount++;
                    }

                    $processedCount++;
                    $this->updateProgress($processedCount, count($products), 'products');
                } catch (\Exception $e) {
                    Utils::logError("Failed to index product {$product['id']}: " . $e->getMessage());
                    $this->indexingStatus['errors'][] = "Product {$product['id']}: " . $e->getMessage();
                }
            }

            Utils::logDebug("Indexed {$indexedCount} products out of {$processedCount} processed");
            return $indexedCount;
        } catch (\Exception $e) {
            Utils::logError('Failed to index products: ' . $e->getMessage());
            $this->indexingStatus['errors'][] = 'Products: ' . $e->getMessage();
            return 0;
        }
    }

    /**
     * Index important pages
     *
     * @since 1.0.0
     * @return int Number of pages indexed
     */
    private function indexPages(): int
    {
        try {
            $scanner = Scanner::getInstance();
            $indexer = Indexer::getInstance();

            // Get pages to index (focus on important ones)
            $pages = $scanner->scanPages([
                'limit' => self::MAX_INITIAL_PAGES,
                'include_types' => ['page'],
                'prioritize' => ['privacy-policy', 'terms', 'shipping', 'returns', 'faq', 'about']
            ]);

            $indexedCount = 0;
            $processedCount = 0;

            foreach ($pages as $page) {
                // Check time limit
                if ($this->hasExceededTimeLimit()) {
                    Utils::logDebug("Time limit exceeded during page indexing. Processed: {$processedCount}");
                    break;
                }

                try {
                    // Index the page
                    $chunks = $indexer->indexContent([
                        'source_type' => 'page',
                        'source_id' => $page['id'],
                        'title' => $page['title'],
                        'content' => $page['content'],
                        'url' => $page['url'],
                        'metadata' => $page['metadata'] ?? []
                    ]);

                    if ($chunks > 0) {
                        $indexedCount++;
                    }

                    $processedCount++;
                    $this->updateProgress($processedCount, count($pages), 'pages');
                } catch (\Exception $e) {
                    Utils::logError("Failed to index page {$page['id']}: " . $e->getMessage());
                    $this->indexingStatus['errors'][] = "Page {$page['id']}: " . $e->getMessage();
                }
            }

            Utils::logDebug("Indexed {$indexedCount} pages out of {$processedCount} processed");
            return $indexedCount;
        } catch (\Exception $e) {
            Utils::logError('Failed to index pages: ' . $e->getMessage());
            $this->indexingStatus['errors'][] = 'Pages: ' . $e->getMessage();
            return 0;
        }
    }

    /**
     * Index WooCommerce settings
     *
     * @since 1.0.0
     * @return int Number of settings sections indexed
     */
    private function indexWooCommerceSettings(): int
    {
        try {
            $detector = WooCommerceDetector::getInstance();
            $indexer = Indexer::getInstance();

            // Get WooCommerce settings
            $settings = $detector->extractAllSettings();
            $indexedCount = 0;

            foreach ($settings as $settingType => $settingData) {
                // Check time limit
                if ($this->hasExceededTimeLimit()) {
                    Utils::logDebug("Time limit exceeded during settings indexing");
                    break;
                }

                try {
                    if (!empty($settingData['content'])) {
                        $chunks = $indexer->indexContent([
                            'source_type' => 'wc_settings',
                            'source_id' => $settingType,
                            'title' => $settingData['title'],
                            'content' => $settingData['content'],
                            'url' => $settingData['url'] ?? '',
                            'metadata' => $settingData['metadata'] ?? []
                        ]);

                        if ($chunks > 0) {
                            $indexedCount++;
                        }
                    }
                } catch (\Exception $e) {
                    Utils::logError("Failed to index setting {$settingType}: " . $e->getMessage());
                    $this->indexingStatus['errors'][] = "Setting {$settingType}: " . $e->getMessage();
                }
            }

            Utils::logDebug("Indexed {$indexedCount} WooCommerce settings sections");
            return $indexedCount;
        } catch (\Exception $e) {
            Utils::logError('Failed to index WooCommerce settings: ' . $e->getMessage());
            $this->indexingStatus['errors'][] = 'Settings: ' . $e->getMessage();
            return 0;
        }
    }

    /**
     * Process chunks and generate embeddings
     *
     * @since 1.0.0
     * @return int Number of chunks processed
     */
    private function processChunks(): int
    {
        try {
            $vectorManager = VectorManager::getInstance();

            // Get unprocessed chunks
            $chunks = $this->getUnprocessedChunks();
            $processedCount = 0;

            // Process in batches to avoid memory/time issues
            $batches = array_chunk($chunks, self::BATCH_SIZE);

            foreach ($batches as $batch) {
                // Check time limit
                if ($this->hasExceededTimeLimit()) {
                    Utils::logDebug("Time limit exceeded during chunk processing. Processed: {$processedCount}");
                    break;
                }

                try {
                    // Process batch of chunks
                    $batchContent = array_map(function ($chunk) {
                        return [
                            'id' => $chunk['id'],
                            'content' => $chunk['chunk_content']
                        ];
                    }, $batch);

                    $results = $vectorManager->generateEmbeddingsBatch($batchContent);

                    if ($results) {
                        $processedCount += count($batch);
                    }
                } catch (\Exception $e) {
                    Utils::logError("Failed to process chunk batch: " . $e->getMessage());
                    $this->indexingStatus['errors'][] = 'Chunk processing: ' . $e->getMessage();
                }
            }

            Utils::logDebug("Processed {$processedCount} chunks for embeddings");
            return $processedCount;
        } catch (\Exception $e) {
            Utils::logError('Failed to process chunks: ' . $e->getMessage());
            $this->indexingStatus['errors'][] = 'Chunks: ' . $e->getMessage();
            return 0;
        }
    }

    /**
     * Get unprocessed chunks from database
     *
     * @since 1.0.0
     * @return array Array of unprocessed chunks
     */
    private function getUnprocessedChunks(): array
    {
        global $wpdb;

        $tableName = $wpdb->prefix . 'woo_ai_knowledge_base';

        $chunks = $wpdb->get_results(
            "SELECT id, chunk_content FROM {$tableName} 
             WHERE embedding IS NULL OR embedding = '' 
             ORDER BY indexed_at DESC 
             LIMIT 100",
            ARRAY_A
        );

        return $chunks ?: [];
    }

    /**
     * Check if time limit has been exceeded
     *
     * @since 1.0.0
     * @return bool True if time limit exceeded
     */
    private function hasExceededTimeLimit(): bool
    {
        return (time() - $this->startTime) >= self::TIME_LIMIT;
    }

    /**
     * Update indexing progress
     *
     * @since 1.0.0
     * @param int $processed Number of items processed
     * @param int $total Total number of items
     * @param string $type Type of content being processed
     * @return void
     */
    private function updateProgress(int $processed, int $total, string $type): void
    {
        $this->indexingStatus['processed_items'] = $processed;
        $this->indexingStatus['total_items'] = $total;
        $this->indexingStatus['progress'] = $total > 0 ? ($processed / $total) * 100 : 0;
        $this->indexingStatus['last_updated'] = current_time('mysql');

        // Update specific counters
        switch ($type) {
            case 'products':
                $this->indexingStatus['products_indexed'] = $processed;
                break;
            case 'pages':
                $this->indexingStatus['pages_indexed'] = $processed;
                break;
            case 'settings':
                $this->indexingStatus['settings_indexed'] = $processed;
                break;
        }

        // Save status to database every 10 items or on first update
        if ($processed % 10 === 0 || $processed === 1) {
            \update_option('woo_ai_assistant_auto_index_status', $this->indexingStatus);
        }
    }

    /**
     * Update indexing status
     *
     * @since 1.0.0
     * @param string $status Status message
     * @param string $message Additional message
     * @return void
     */
    private function updateStatus(string $status, string $message = ''): void
    {
        $this->indexingStatus['status'] = $status;
        $this->indexingStatus['last_updated'] = current_time('mysql');

        if (!empty($message)) {
            $this->indexingStatus['message'] = $message;
        }

        update_option('woo_ai_assistant_auto_index_status', $this->indexingStatus);
    }

    /**
     * Complete auto-indexing process
     *
     * @since 1.0.0
     * @param array $results Indexing results
     * @return void
     */
    private function completeAutoIndexing(array $results): void
    {
        $this->indexingStatus['status'] = 'completed';
        $this->indexingStatus['completed_at'] = current_time('mysql');
        $this->indexingStatus['results'] = $results;
        $this->indexingStatus['progress'] = 100;

        // Save final status
        update_option('woo_ai_assistant_auto_index_status', $this->indexingStatus);
        update_option('woo_ai_assistant_last_auto_index', time());

        // Trigger completion action
        do_action('woo_ai_assistant_auto_index_complete', $results, $this->indexingStatus);

        Utils::logDebug('Auto-indexing completed successfully', $results);
    }

    /**
     * Handle auto-indexing completion
     *
     * @since 1.0.0
     * @param array $results Indexing results
     * @param array $status Final status
     * @return void
     */
    public function onAutoIndexingComplete(array $results, array $status): void
    {
        try {
            // Set up default messages and triggers
            $messageSetup = DefaultMessageSetup::getInstance();
            $messageSetup->setupInitialConfiguration();

            // Clear any unnecessary temporary data
            $this->cleanupTemporaryData();

            Utils::logDebug('Post auto-indexing setup completed');
        } catch (\Exception $e) {
            Utils::logError('Failed to complete post auto-indexing setup: ' . $e->getMessage());
        }
    }

    /**
     * Get current auto-indexing status
     *
     * @since 1.0.0
     * @return array Current status information
     */
    public function getStatus(): array
    {
        return get_option('woo_ai_assistant_auto_index_status', [
            'status' => 'not_started',
            'message' => 'Auto-indexing has not been started yet'
        ]);
    }

    /**
     * Check if auto-indexing is currently running
     *
     * @since 1.0.0
     * @return bool True if auto-indexing is running
     */
    public function isRunning(): bool
    {
        $status = $this->getStatus();
        return in_array($status['status'] ?? '', ['starting', 'scheduled', 'running']);
    }

    /**
     * Check if auto-indexing has completed successfully
     *
     * @since 1.0.0
     * @return bool True if completed successfully
     */
    public function hasCompleted(): bool
    {
        $status = $this->getStatus();
        return ($status['status'] ?? '') === 'completed';
    }

    /**
     * Reset auto-indexing status
     *
     * @since 1.0.0
     * @return void
     */
    public function resetStatus(): void
    {
        delete_option('woo_ai_assistant_auto_index_status');
        delete_option('woo_ai_assistant_last_auto_index');

        Utils::logDebug('Auto-indexing status reset');
    }

    /**
     * Clean up temporary data
     *
     * @since 1.0.0
     * @return void
     */
    private function cleanupTemporaryData(): void
    {
        // Clean up any temporary options or transients
        delete_transient('woo_ai_assistant_temp_indexing_data');

        // Clear any temporary files
        $uploadDir = wp_upload_dir();
        $tempDir = $uploadDir['basedir'] . '/woo-ai-assistant/temp';

        if (is_dir($tempDir)) {
            $files = glob($tempDir . '/auto_index_*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    /**
     * Cleanup on plugin deactivation
     *
     * @since 1.0.0
     * @return void
     */
    public function cleanup(): void
    {
        // Cancel any scheduled auto-indexing events
        \wp_clear_scheduled_hook('woo_ai_assistant_auto_index');
        \wp_clear_scheduled_hook('woo_ai_assistant_auto_index_complete');

        $this->cleanupTemporaryData();

        Utils::logDebug('Auto-indexing cleanup completed');
    }

    /**
     * Get auto-indexing statistics
     *
     * @since 1.0.0
     * @return array Statistics about auto-indexing
     */
    public function getStatistics(): array
    {
        $status = $this->getStatus();

        return [
            'last_run' => $status['completed_at'] ?? null,
            'last_status' => $status['status'] ?? 'unknown',
            'products_indexed' => $status['products_indexed'] ?? 0,
            'pages_indexed' => $status['pages_indexed'] ?? 0,
            'settings_indexed' => $status['settings_indexed'] ?? 0,
            'total_errors' => count($status['errors'] ?? []),
            'is_enabled' => $this->isAutoIndexingEnabled()
        ];
    }
}
