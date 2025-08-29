<?php

/**
 * Knowledge Base Indexing Processor Class
 *
 * Handles background processing of knowledge base indexing operations.
 * Processes scheduled indexing batches, generates embeddings, stores vectors
 * in Pinecone, and tracks progress throughout the indexing pipeline.
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
use WooAiAssistant\Common\ApiConfiguration;
use WooAiAssistant\Common\DevelopmentConfig;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class IndexingProcessor
 *
 * Manages the complete indexing pipeline from content scanning to vector storage.
 * Handles background processing via WordPress cron, integrates with VectorManager
 * for embedding generation, and manages Pinecone storage operations.
 *
 * @since 1.0.0
 */
class IndexingProcessor
{
    use Singleton;

    /**
     * WordPress database instance
     *
     * @since 1.0.0
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Scanner instance for content extraction
     *
     * @since 1.0.0
     * @var Scanner
     */
    private $scanner;

    /**
     * Indexer instance for content chunking
     *
     * @since 1.0.0
     * @var Indexer
     */
    private $indexer;

    /**
     * VectorManager instance for embeddings
     *
     * @since 1.0.0
     * @var VectorManager
     */
    private $vectorManager;

    /**
     * API configuration instance
     *
     * @since 1.0.0
     * @var ApiConfiguration
     */
    private $apiConfig;

    /**
     * Maximum items to process per batch
     *
     * @since 1.0.0
     * @var int
     */
    private const BATCH_SIZE = 10;

    /**
     * Maximum processing time per batch (seconds)
     *
     * @since 1.0.0
     * @var int
     */
    private const MAX_EXECUTION_TIME = 25;

    /**
     * Pinecone API endpoint
     *
     * @since 1.0.0
     * @var string
     */
    private $pineconeEndpoint;

    /**
     * Pinecone API key
     *
     * @since 1.0.0
     * @var string
     */
    private $pineconeApiKey;

    /**
     * Pinecone index name
     *
     * @since 1.0.0
     * @var string
     */
    private $pineconeIndex;

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    protected function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;

        // Initialize dependencies
        $this->scanner = Scanner::getInstance();
        $this->indexer = Indexer::getInstance();
        $this->vectorManager = VectorManager::getInstance();
        $this->apiConfig = ApiConfiguration::getInstance();

        // Get Pinecone configuration
        $pineconeConfig = $this->apiConfig->getPineconeConfig();
        $this->pineconeApiKey = $pineconeConfig['api_key'] ?? '';
        $this->pineconeIndex = $pineconeConfig['index_name'] ?? 'woo-ai-assistant';

        // Use direct host if provided, otherwise construct from environment (legacy)
        if (!empty($pineconeConfig['host'])) {
            $this->pineconeEndpoint = $pineconeConfig['host'];
        } else {
            // Legacy: Construct Pinecone endpoint from environment
            $environment = $pineconeConfig['environment'] ?? '';
            if (!empty($environment) && !empty($this->pineconeIndex)) {
                $this->pineconeEndpoint = "https://{$this->pineconeIndex}-{$environment}.svc.pinecone.io";
            }
        }
        
        Utils::logDebug('Pinecone configuration loaded', [
            'has_api_key' => !empty($this->pineconeApiKey),
            'index' => $this->pineconeIndex,
            'endpoint' => $this->pineconeEndpoint
        ]);

        // Register WordPress hooks
        $this->registerHooks();
    }

    /**
     * Register WordPress hooks
     *
     * @since 1.0.0
     * @return void
     */
    private function registerHooks(): void
    {
        // Register the scheduled event handler
        add_action('woo_ai_process_indexing_batch', [$this, 'processBatch']);

        // Register cleanup on plugin deactivation
        add_action('woo_ai_assistant_deactivate', [$this, 'cleanupScheduledEvents']);
    }

    /**
     * Start the indexing process
     *
     * @since 1.0.0
     * @param string $contentType Type of content to index ('all', 'products', 'pages', 'posts')
     * @return array Result with status and message
     */
    public function startIndexing(string $contentType = 'all'): array
    {
        try {
            Utils::logDebug('Starting indexing process for content type: ' . $contentType);

            // Set initial status
            update_option('woo_ai_kb_indexing_status', 'preparing');
            update_option('woo_ai_kb_indexing_progress', 0);
            update_option('woo_ai_kb_indexing_content_type', $contentType);
            update_option('woo_ai_kb_indexing_start_time', time());

            // Clear any previous errors
            delete_option('woo_ai_kb_indexing_error');

            // Scan content based on type
            $content = $this->scanContent($contentType);

            if (empty($content)) {
                update_option('woo_ai_kb_indexing_status', 'completed');
                update_option('woo_ai_kb_indexing_progress', 100);
                return [
                    'success' => true,
                    'message' => 'No content found to index'
                ];
            }

            // Store content IDs for batch processing
            $contentIds = array_map(function ($item) {
                return [
                    'type' => $item['type'] ?? 'unknown',
                    'id' => $item['id'] ?? 0,
                    'title' => $item['title'] ?? '',
                    'content' => $item['content'] ?? ''
                ];
            }, $content);

            update_option('woo_ai_kb_indexing_queue', $contentIds);
            update_option('woo_ai_kb_indexing_total', count($contentIds));
            update_option('woo_ai_kb_indexing_processed', 0);
            update_option('woo_ai_kb_indexing_status', 'running');

            // In development mode, process first batch immediately
            $isDevelopment = defined('WOO_AI_ASSISTANT_DEBUG') && WOO_AI_ASSISTANT_DEBUG;
            if ($isDevelopment) {
                Utils::logDebug('Development mode: Processing first batch immediately');
                // Process first batch synchronously
                $this->processBatch();
            } else {
                // Schedule the first batch for production
                wp_schedule_single_event(time() + 1, 'woo_ai_process_indexing_batch');
            }

            Utils::logDebug('Indexing started with ' . count($contentIds) . ' items in queue');

            return [
                'success' => true,
                'message' => sprintf('Started indexing %d items', count($contentIds))
            ];
        } catch (\Exception $e) {
            Utils::logError('Failed to start indexing: ' . $e->getMessage());

            update_option('woo_ai_kb_indexing_status', 'failed');
            update_option('woo_ai_kb_indexing_error', $e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to start indexing: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Process a batch of items
     *
     * @since 1.0.0
     * @return void
     */
    public function processBatch(): void
    {
        $startTime = time();

        try {
            Utils::logDebug('Processing indexing batch');

            // Get current queue
            $queue = get_option('woo_ai_kb_indexing_queue', []);

            if (empty($queue)) {
                $this->completeIndexing();
                return;
            }

            // Get batch to process
            $batch = array_splice($queue, 0, self::BATCH_SIZE);
            $processedCount = 0;
            $errors = [];

            foreach ($batch as $item) {
                // Check execution time
                if ((time() - $startTime) > self::MAX_EXECUTION_TIME) {
                    Utils::logDebug('Batch processing time limit reached, scheduling next batch');
                    break;
                }

                try {
                    $this->processItem($item);
                    $processedCount++;
                } catch (\Exception $e) {
                    $errors[] = sprintf(
                        'Failed to process %s #%d: %s',
                        $item['type'],
                        $item['id'],
                        $e->getMessage()
                    );
                    Utils::logError('Item processing failed: ' . $e->getMessage());
                }
            }

            // Update queue and progress
            update_option('woo_ai_kb_indexing_queue', $queue);

            $total = get_option('woo_ai_kb_indexing_total', 1);
            $processed = get_option('woo_ai_kb_indexing_processed', 0) + $processedCount;
            update_option('woo_ai_kb_indexing_processed', $processed);

            $progress = min(100, round(($processed / $total) * 100));
            update_option('woo_ai_kb_indexing_progress', $progress);

            // Log any errors
            if (!empty($errors)) {
                $existingErrors = get_option('woo_ai_kb_indexing_errors', []);
                update_option('woo_ai_kb_indexing_errors', array_merge($existingErrors, $errors));
            }

            Utils::logDebug(sprintf('Batch processed: %d items, Progress: %d%%', $processedCount, $progress));

            // Schedule next batch if there are more items
            if (!empty($queue)) {
                $isDevelopment = defined('WOO_AI_ASSISTANT_DEBUG') && WOO_AI_ASSISTANT_DEBUG;
                if ($isDevelopment) {
                    // In development, process next batch immediately with a small delay to avoid timeout
                    if ($progress < 100) {
                        Utils::logDebug('Development mode: Processing next batch immediately');
                        // Add a small sleep to prevent overwhelming the system
                        sleep(1);
                        $this->processBatch();
                    }
                } else {
                    // In production, schedule the next batch
                    wp_schedule_single_event(time() + 2, 'woo_ai_process_indexing_batch');
                }
            } else {
                $this->completeIndexing();
            }
        } catch (\Exception $e) {
            Utils::logError('Batch processing failed: ' . $e->getMessage());
            update_option('woo_ai_kb_indexing_status', 'failed');
            update_option('woo_ai_kb_indexing_error', $e->getMessage());
        }
    }

    /**
     * Process a single content item
     *
     * @since 1.0.0
     * @param array $item Content item to process
     * @return void
     * @throws \Exception If processing fails
     */
    private function processItem(array $item): void
    {
        Utils::logDebug('Processing item: ' . $item['type'] . ' #' . $item['id']);

        // Step 1: Create chunks using Indexer
        $chunks = $this->indexer->createChunks($item['content']);

        if (empty($chunks)) {
            Utils::logDebug('No chunks created for item: ' . $item['id']);
            return;
        }

        // Step 2: Store chunks in database
        $tableName = $this->wpdb->prefix . 'woo_ai_knowledge_base';

        foreach ($chunks as $chunkIndex => $chunk) {
            // Step 3: Generate embedding for chunk
            Utils::logDebug('Generating embedding for chunk', [
                'item_id' => $item['id'],
                'chunk_index' => $chunkIndex,
                'chunk_length' => strlen($chunk['content'])
            ]);
            
            $embedding = $this->vectorManager->generateEmbedding($chunk['content']);

            if (empty($embedding)) {
                Utils::logError('Failed to generate embedding for chunk');
                continue;
            }
            
            Utils::logDebug('Embedding generated successfully', [
                'dimensions' => count($embedding),
                'chunk_index' => $chunkIndex
            ]);

            // Step 4: Store in local database with embedding
            $result = $this->wpdb->replace(
                $tableName,
                [
                    'source_type' => $item['type'],
                    'source_id' => $item['id'],
                    'title' => $item['title'],
                    'content' => $item['content'],
                    'chunk_content' => $chunk['content'],
                    'chunk_index' => $chunkIndex,
                    'embedding' => json_encode($embedding),
                    'metadata' => json_encode([
                        'word_count' => $chunk['word_count'] ?? 0,
                        'indexed_at' => current_time('mysql')
                    ]),
                    'indexed_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ],
                ['%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
            );

            if ($result === false) {
                throw new \Exception('Failed to store chunk in database: ' . $this->wpdb->last_error);
            }

            // Step 5: Send to Pinecone (if configured and not in development mode)
            if ($this->shouldUsePinecone()) {
                $this->sendToPinecone(
                    $item['type'] . '_' . $item['id'] . '_' . $chunkIndex,
                    $embedding,
                    [
                        'type' => $item['type'],
                        'source_id' => $item['id'],
                        'title' => $item['title'],
                        'content' => $chunk['content'],
                        'chunk_index' => $chunkIndex
                    ]
                );
            }
        }

        Utils::logDebug('Item processed successfully with ' . count($chunks) . ' chunks');
    }

    /**
     * Send vector to Pinecone
     *
     * @since 1.0.0
     * @param string $id Vector ID
     * @param array $vector Embedding vector
     * @param array $metadata Vector metadata
     * @return bool Success status
     */
    private function sendToPinecone(string $id, array $vector, array $metadata): bool
    {
        try {
            Utils::logDebug('Attempting to send vector to Pinecone', [
                'id' => $id,
                'vector_dimensions' => count($vector),
                'has_endpoint' => !empty($this->pineconeEndpoint),
                'has_api_key' => !empty($this->pineconeApiKey),
                'endpoint' => $this->pineconeEndpoint
            ]);
            
            if (empty($this->pineconeEndpoint) || empty($this->pineconeApiKey)) {
                Utils::logDebug('Pinecone not configured, skipping vector storage');
                return false;
            }

            $url = $this->pineconeEndpoint . '/vectors/upsert';

            $data = [
                'vectors' => [
                    [
                        'id' => $id,
                        'values' => $vector,
                        'metadata' => $metadata
                    ]
                ]
            ];

            $response = wp_remote_post($url, [
                'headers' => [
                    'Api-Key' => $this->pineconeApiKey,
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode($data),
                'timeout' => 30
            ]);

            if (is_wp_error($response)) {
                throw new \Exception('Pinecone request failed: ' . $response->get_error_message());
            }

            $responseCode = wp_remote_retrieve_response_code($response);
            if ($responseCode !== 200) {
                $body = wp_remote_retrieve_body($response);
                throw new \Exception('Pinecone returned error ' . $responseCode . ': ' . $body);
            }

            Utils::logDebug('Vector stored in Pinecone successfully: ' . $id);
            return true;
        } catch (\Exception $e) {
            Utils::logError('Failed to send to Pinecone: ' . $e->getMessage());
            // Don't throw - continue processing even if Pinecone fails
            return false;
        }
    }

    /**
     * Check if Pinecone should be used
     *
     * @since 1.0.0
     * @return bool
     */
    private function shouldUsePinecone(): bool
    {
        // Check if in development mode
        $devConfig = DevelopmentConfig::getInstance();
        if ($devConfig->isDevelopmentMode() && !$devConfig->shouldUsePinecone()) {
            Utils::logDebug('Development mode: Pinecone disabled');
            return false;
        }

        // Check if Pinecone is configured
        if (empty($this->pineconeApiKey) || empty($this->pineconeEndpoint)) {
            Utils::logDebug('Pinecone not configured');
            return false;
        }

        return true;
    }

    /**
     * Scan content based on type
     *
     * @since 1.0.0
     * @param string $contentType Content type to scan
     * @return array Scanned content
     */
    private function scanContent(string $contentType): array
    {
        $content = [];

        switch ($contentType) {
            case 'products':
                $content = $this->scanner->scanProducts(['limit' => 1000]);
                break;

            case 'pages':
                $content = $this->scanner->scanPages(['limit' => 1000]);
                break;

            case 'posts':
                $content = $this->scanner->scanPosts(['limit' => 1000]);
                break;

            case 'woo_settings':
                $content = $this->scanner->scanWooSettings();
                break;

            case 'all':
            default:
                $content = array_merge(
                    $this->scanner->scanProducts(['limit' => 500]),
                    $this->scanner->scanPages(['limit' => 500]),
                    $this->scanner->scanPosts(['limit' => 500]),
                    $this->scanner->scanWooSettings()
                );
                break;
        }

        return $content;
    }

    /**
     * Complete the indexing process
     *
     * @since 1.0.0
     * @return void
     */
    private function completeIndexing(): void
    {
        $endTime = time();
        $startTime = get_option('woo_ai_kb_indexing_start_time', $endTime);
        $duration = $endTime - $startTime;

        $processed = get_option('woo_ai_kb_indexing_processed', 0);
        $errors = get_option('woo_ai_kb_indexing_errors', []);

        // Update status
        update_option('woo_ai_kb_indexing_status', 'completed');
        update_option('woo_ai_kb_indexing_progress', 100);
        update_option('woo_ai_kb_indexing_end_time', $endTime);
        update_option('woo_ai_kb_indexing_duration', $duration);

        // Log completion
        $message = sprintf(
            'Indexing completed: %d items processed in %d seconds',
            $processed,
            $duration
        );

        if (!empty($errors)) {
            $message .= sprintf(' with %d errors', count($errors));
        }

        Utils::logDebug($message);

        // Clean up temporary options
        delete_option('woo_ai_kb_indexing_queue');
        delete_option('woo_ai_kb_indexing_content_type');

        // Store completion in activity log
        $this->logActivity('indexing_complete', [
            'items_processed' => $processed,
            'duration' => $duration,
            'errors' => count($errors)
        ]);
    }

    /**
     * Log activity to database
     *
     * @since 1.0.0
     * @param string $action Action performed
     * @param array $details Additional details
     * @return void
     */
    private function logActivity(string $action, array $details = []): void
    {
        $tableName = $this->wpdb->prefix . 'woo_ai_usage_stats';

        $this->wpdb->insert(
            $tableName,
            [
                'user_id' => get_current_user_id(),
                'conversation_id' => 0,
                'action' => $action,
                'tokens_used' => 0,
                'cost' => 0,
                'details' => json_encode($details),
                'created_at' => current_time('mysql')
            ],
            ['%d', '%d', '%s', '%d', '%f', '%s', '%s']
        );
    }

    /**
     * Get current indexing status
     *
     * @since 1.0.0
     * @return array Status information
     */
    public function getIndexingStatus(): array
    {
        $status = get_option('woo_ai_kb_indexing_status', 'idle');
        $progress = get_option('woo_ai_kb_indexing_progress', 0);
        $total = get_option('woo_ai_kb_indexing_total', 0);
        $processed = get_option('woo_ai_kb_indexing_processed', 0);
        $errors = get_option('woo_ai_kb_indexing_errors', []);

        return [
            'status' => $status,
            'progress' => $progress,
            'total' => $total,
            'processed' => $processed,
            'errors' => $errors,
            'message' => $this->getStatusMessage($status, $progress)
        ];
    }

    /**
     * Get status message
     *
     * @since 1.0.0
     * @param string $status Current status
     * @param int $progress Current progress
     * @return string Status message
     */
    private function getStatusMessage(string $status, int $progress): string
    {
        switch ($status) {
            case 'preparing':
                return __('Preparing content for indexing...', 'woo-ai-assistant');
            case 'running':
                return sprintf(__('Indexing in progress... %d%% complete', 'woo-ai-assistant'), $progress);
            case 'completed':
                return __('Indexing completed successfully', 'woo-ai-assistant');
            case 'failed':
                $error = get_option('woo_ai_kb_indexing_error', '');
                return __('Indexing failed: ', 'woo-ai-assistant') . $error;
            default:
                return __('Ready to index', 'woo-ai-assistant');
        }
    }

    /**
     * Cancel ongoing indexing
     *
     * @since 1.0.0
     * @return void
     */
    public function cancelIndexing(): void
    {
        // Clear scheduled events
        wp_clear_scheduled_hook('woo_ai_process_indexing_batch');

        // Reset status
        update_option('woo_ai_kb_indexing_status', 'cancelled');
        update_option('woo_ai_kb_indexing_progress', 0);

        // Clear queue
        delete_option('woo_ai_kb_indexing_queue');
        delete_option('woo_ai_kb_indexing_total');
        delete_option('woo_ai_kb_indexing_processed');

        Utils::logDebug('Indexing cancelled by user');
    }

    /**
     * Clean up scheduled events on deactivation
     *
     * @since 1.0.0
     * @return void
     */
    public function cleanupScheduledEvents(): void
    {
        wp_clear_scheduled_hook('woo_ai_process_indexing_batch');
    }
}
