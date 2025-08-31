<?php

/**
 * Knowledge Base Manager Class
 *
 * Main orchestrator that integrates Scanner, Indexer, VectorManager, and AIManager
 * to provide a unified interface for all Knowledge Base operations. Handles the
 * complete content lifecycle from scanning through AI response generation.
 *
 * @package WooAiAssistant
 * @subpackage KnowledgeBase
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\KnowledgeBase;

use WooAiAssistant\Common\Traits\Singleton;
use WooAiAssistant\Common\Logger;
use WooAiAssistant\Common\Cache;
use WooAiAssistant\Common\Utils;
use WooAiAssistant\Config\ApiConfiguration;
use Exception;
use WP_Error;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Manager
 *
 * Main orchestrator for all Knowledge Base operations.
 *
 * @since 1.0.0
 */
class Manager
{
    use Singleton;

    /**
     * Scanner instance for content extraction
     *
     * @var Scanner
     */
    private Scanner $scanner;

    /**
     * Indexer instance for content processing
     *
     * @var Indexer
     */
    private Indexer $indexer;

    /**
     * Vector manager for embeddings
     *
     * @var VectorManager
     */
    private VectorManager $vectorManager;

    /**
     * AI manager for response generation
     *
     * @var AIManager
     */
    private AIManager $aiManager;

    /**
     * Prompt builder for advanced prompts
     *
     * @var PromptBuilder
     */
    private PromptBuilder $promptBuilder;

    /**
     * Embedding generator
     *
     * @var EmbeddingGenerator
     */
    private EmbeddingGenerator $embeddingGenerator;

    /**
     * Chunking strategy
     *
     * @var ChunkingStrategy
     */
    private ChunkingStrategy $chunkingStrategy;

    /**
     * Current processing status
     *
     * @var array
     */
    private array $processingStatus = [
        'is_running' => false,
        'current_operation' => '',
        'progress' => 0,
        'total_items' => 0,
        'processed_items' => 0,
        'errors' => [],
        'started_at' => null,
        'estimated_completion' => null
    ];

    /**
     * Cron hooks and schedules
     *
     * @var array
     */
    private array $cronHooks = [
        'woo_ai_kb_full_sync' => 'daily',
        'woo_ai_kb_incremental_sync' => 'hourly',
        'woo_ai_kb_health_check' => 'twicedaily',
        'woo_ai_kb_cleanup' => 'weekly'
    ];

    /**
     * Initialize the manager with all required components
     *
     * @since 1.0.0
     */
    protected function init(): void
    {
        try {
            // Initialize all component instances
            $this->scanner = Scanner::getInstance();
            $this->indexer = Indexer::getInstance();
            $this->vectorManager = VectorManager::getInstance();
            $this->aiManager = AIManager::getInstance();
            $this->promptBuilder = PromptBuilder::getInstance();
            $this->embeddingGenerator = EmbeddingGenerator::getInstance();
            $this->chunkingStrategy = ChunkingStrategy::getInstance();

            // Setup cron schedules
            $this->setupCronSchedules();

            Logger::info('Knowledge Base Manager initialized successfully');

        } catch (Exception $e) {
            Logger::error('Failed to initialize Knowledge Base Manager: ' . $e->getMessage());
            throw new Exception('Manager initialization failed: ' . $e->getMessage());
        }
    }

    /**
     * Perform a complete knowledge base rebuild
     *
     * Scans all content, creates chunks, generates embeddings, and stores vectors.
     * This is a comprehensive operation that can take significant time.
     *
     * @since 1.0.0
     * @param array $args Optional arguments for the rebuild process.
     * @param bool  $args['force_rebuild'] Whether to rebuild existing content. Default false.
     * @param array $args['content_types'] Specific content types to rebuild. Default all.
     * @param int   $args['batch_size'] Batch size for processing. Default 50.
     * @param bool  $args['background'] Whether to run in background. Default false.
     * 
     * @return array Results of the rebuild operation.
     * 
     * @throws Exception When rebuild operation fails.
     * 
     * @example
     * ```php
     * $manager = Manager::getInstance();
     * $results = $manager->rebuildKnowledgeBase([
     *     'force_rebuild' => true,
     *     'content_types' => ['product', 'page'],
     *     'batch_size' => 25
     * ]);
     * ```
     */
    public function rebuildKnowledgeBase(array $args = []): array
    {
        $defaults = [
            'force_rebuild' => false,
            'content_types' => ['product', 'page', 'post', 'woocommerce_settings', 'category'],
            'batch_size' => 50,
            'background' => false
        ];

        $args = wp_parse_args($args, $defaults);

        try {
            // Start processing
            $this->startOperation('full_rebuild', $args);

            Logger::info('Starting complete knowledge base rebuild', $args);

            $results = [
                'success' => true,
                'content_types_processed' => [],
                'total_items' => 0,
                'total_chunks' => 0,
                'total_embeddings' => 0,
                'processing_time' => 0,
                'errors' => []
            ];

            $startTime = microtime(true);

            // Process each content type
            foreach ($args['content_types'] as $contentType) {
                try {
                    $typeResult = $this->processContentType($contentType, $args);
                    $results['content_types_processed'][$contentType] = $typeResult;
                    $results['total_items'] += $typeResult['items_processed'];
                    $results['total_chunks'] += $typeResult['chunks_created'];
                    $results['total_embeddings'] += $typeResult['embeddings_generated'];

                } catch (Exception $e) {
                    $error = "Failed to process {$contentType}: " . $e->getMessage();
                    Logger::error($error);
                    $results['errors'][] = $error;
                }

                // Update progress
                $this->updateProgress($contentType, $args['content_types']);
            }

            $results['processing_time'] = round(microtime(true) - $startTime, 2);

            // Complete the operation
            $this->completeOperation($results);

            // Clear relevant caches
            $this->clearProcessingCaches();

            Logger::info('Knowledge base rebuild completed', $results);

            return $results;

        } catch (Exception $e) {
            $this->failOperation($e->getMessage());
            Logger::error('Knowledge base rebuild failed: ' . $e->getMessage());
            throw new Exception('Rebuild failed: ' . $e->getMessage());
        }
    }

    /**
     * Perform incremental knowledge base updates
     *
     * Updates only content that has changed since the last sync.
     * Much faster than full rebuild for regular maintenance.
     *
     * @since 1.0.0
     * @param array $args Optional arguments for incremental update.
     * @param string $args['since'] Date/time to check for changes. Default last 24 hours.
     * @param array  $args['content_types'] Content types to check. Default all.
     * @param int    $args['batch_size'] Processing batch size. Default 25.
     * 
     * @return array Results of the incremental update.
     * 
     * @throws Exception When incremental update fails.
     */
    public function incrementalUpdate(array $args = []): array
    {
        $defaults = [
            'since' => date('Y-m-d H:i:s', strtotime('-24 hours')),
            'content_types' => ['product', 'page', 'post'],
            'batch_size' => 25
        ];

        $args = wp_parse_args($args, $defaults);

        try {
            $this->startOperation('incremental_update', $args);

            Logger::info('Starting incremental knowledge base update', $args);

            $results = [
                'success' => true,
                'items_updated' => 0,
                'items_removed' => 0,
                'chunks_updated' => 0,
                'processing_time' => 0,
                'content_types' => []
            ];

            $startTime = microtime(true);

            // Get changed content for each type
            foreach ($args['content_types'] as $contentType) {
                $typeResult = $this->processIncrementalContentType($contentType, $args);
                $results['content_types'][$contentType] = $typeResult;
                $results['items_updated'] += $typeResult['updated'];
                $results['items_removed'] += $typeResult['removed'];
                $results['chunks_updated'] += $typeResult['chunks'];
            }

            $results['processing_time'] = round(microtime(true) - $startTime, 2);

            $this->completeOperation($results);

            Logger::info('Incremental update completed', $results);

            return $results;

        } catch (Exception $e) {
            $this->failOperation($e->getMessage());
            Logger::error('Incremental update failed: ' . $e->getMessage());
            throw new Exception('Incremental update failed: ' . $e->getMessage());
        }
    }

    /**
     * Generate AI response using the complete RAG pipeline
     *
     * Processes user query through similarity search, context retrieval,
     * and AI generation to provide informed responses.
     *
     * @since 1.0.0
     * @param string $userQuery The user's question or query.
     * @param array  $args Optional arguments for response generation.
     * @param string $args['conversation_id'] ID for conversation context. Default empty.
     * @param array  $args['conversation_history'] Previous messages. Default empty.
     * @param array  $args['user_context'] User/page context data. Default empty.
     * @param bool   $args['stream'] Whether to stream the response. Default false.
     * @param int    $args['max_chunks'] Maximum knowledge chunks to use. Default 5.
     * @param float  $args['similarity_threshold'] Minimum similarity score. Default 0.7.
     * 
     * @return array The AI response with metadata.
     * 
     * @throws Exception When response generation fails.
     * 
     * @example
     * ```php
     * $manager = Manager::getInstance();
     * $response = $manager->generateResponse("What's your return policy?", [
     *     'conversation_id' => 'conv-123',
     *     'user_context' => ['page' => 'product', 'product_id' => 456]
     * ]);
     * ```
     */
    public function generateResponse(string $userQuery, array $args = []): array
    {
        $defaults = [
            'conversation_id' => '',
            'conversation_history' => [],
            'user_context' => [],
            'stream' => false,
            'max_chunks' => 5,
            'similarity_threshold' => 0.7
        ];

        $args = wp_parse_args($args, $defaults);

        try {
            Logger::info('Generating AI response for query', [
                'query_length' => strlen($userQuery),
                'conversation_id' => $args['conversation_id']
            ]);

            // Step 1: Perform similarity search to find relevant knowledge
            $knowledgeChunks = $this->vectorManager->similaritySearch($userQuery, [
                'limit' => $args['max_chunks'],
                'similarity_threshold' => $args['similarity_threshold'],
                'include_metadata' => true
            ]);

            // Step 2: Build the RAG prompt with context
            $promptData = $this->promptBuilder->buildRagPrompt($userQuery, [
                'knowledge_chunks' => $knowledgeChunks,
                'conversation_history' => $args['conversation_history'],
                'user_context' => $args['user_context']
            ]);

            // Step 3: Generate AI response
            $response = $this->aiManager->generateResponse($userQuery, [
                'prompt_data' => $promptData,
                'conversation_id' => $args['conversation_id'],
                'stream' => $args['stream'],
                'temperature' => 0.7
            ]);

            // Add metadata about the knowledge base search
            $response['knowledge_base'] = [
                'chunks_used' => count($knowledgeChunks),
                'similarity_scores' => array_column($knowledgeChunks, 'similarity_score'),
                'content_sources' => array_unique(array_column($knowledgeChunks, 'content_type'))
            ];

            Logger::info('AI response generated successfully', [
                'response_length' => strlen($response['response'] ?? ''),
                'chunks_used' => $response['knowledge_base']['chunks_used']
            ]);

            return $response;

        } catch (Exception $e) {
            Logger::error('Failed to generate AI response: ' . $e->getMessage());

            // Return fallback response
            return $this->promptBuilder->buildFallbackResponse($userQuery, [
                'error' => $e->getMessage(),
                'user_context' => $args['user_context']
            ]);
        }
    }

    /**
     * Get comprehensive knowledge base statistics
     *
     * Aggregates statistics from all components to provide a complete
     * overview of the knowledge base status and performance.
     *
     * @since 1.0.0
     * @param bool $includeDetailed Whether to include detailed component stats. Default false.
     * 
     * @return array Complete statistics array.
     */
    public function getStatistics(bool $includeDetailed = false): array
    {
        try {
            $stats = [
                'overview' => [
                    'total_content_items' => 0,
                    'total_chunks' => 0,
                    'total_embeddings' => 0,
                    'last_full_sync' => get_option('woo_ai_kb_last_full_sync', 'Never'),
                    'last_incremental_sync' => get_option('woo_ai_kb_last_incremental_sync', 'Never'),
                    'knowledge_base_version' => get_option('woo_ai_kb_version', '1.0.0')
                ],
                'content_types' => [],
                'performance' => [
                    'avg_response_time' => 0,
                    'cache_hit_rate' => 0,
                    'api_success_rate' => 0
                ],
                'processing_status' => $this->processingStatus
            ];

            // Get statistics from each component
            $scannerStats = $this->scanner->getStatistics();
            $indexerStats = $this->indexer->getStatistics();
            $vectorStats = $this->vectorManager->getVectorStatistics();
            $aiStats = $this->aiManager->getStatistics();

            // Aggregate content statistics
            $stats['overview']['total_content_items'] = $scannerStats['total_items'] ?? 0;
            $stats['overview']['total_chunks'] = $indexerStats['total_chunks'] ?? 0;
            $stats['overview']['total_embeddings'] = $vectorStats['total_vectors'] ?? 0;

            // Content type breakdown
            if (isset($scannerStats['content_types'])) {
                foreach ($scannerStats['content_types'] as $type => $count) {
                    $stats['content_types'][$type] = [
                        'items' => $count,
                        'chunks' => $indexerStats['content_types'][$type]['chunks'] ?? 0,
                        'embeddings' => $vectorStats['content_types'][$type] ?? 0
                    ];
                }
            }

            // Performance metrics
            $stats['performance']['avg_response_time'] = $aiStats['avg_response_time'] ?? 0;
            $stats['performance']['cache_hit_rate'] = $this->calculateCacheHitRate();
            $stats['performance']['api_success_rate'] = $this->calculateApiSuccessRate();

            // Include detailed stats if requested
            if ($includeDetailed) {
                $stats['detailed'] = [
                    'scanner' => $scannerStats,
                    'indexer' => $indexerStats,
                    'vector_manager' => $vectorStats,
                    'ai_manager' => $aiStats,
                    'embedding_generator' => $this->embeddingGenerator->getStatistics(),
                    'prompt_builder' => $this->promptBuilder->getStatistics(),
                    'chunking_strategy' => $this->chunkingStrategy->getStatistics()
                ];
            }

            return $stats;

        } catch (Exception $e) {
            Logger::error('Failed to get KB statistics: ' . $e->getMessage());
            return ['error' => 'Failed to retrieve statistics'];
        }
    }

    /**
     * Check if the knowledge base is currently processing
     *
     * @since 1.0.0
     * @return bool True if processing is active.
     */
    public function isProcessing(): bool
    {
        return $this->processingStatus['is_running'] || $this->indexer->isProcessing();
    }

    /**
     * Get current processing status with detailed information
     *
     * @since 1.0.0
     * @return array Current processing status.
     */
    public function getProcessingStatus(): array
    {
        // Merge with indexer status for complete picture
        $indexerStatus = $this->indexer->getProcessingStatus();
        
        return array_merge($this->processingStatus, [
            'indexer_status' => $indexerStatus,
            'queue_size' => $this->getProcessingQueueSize(),
            'estimated_time_remaining' => $this->estimateTimeRemaining()
        ]);
    }

    /**
     * Clear all knowledge base data
     *
     * Removes all indexed content, embeddings, and cached data.
     * This is a destructive operation that cannot be undone.
     *
     * @since 1.0.0
     * @param bool $confirm Confirmation flag to prevent accidental deletion.
     * 
     * @return bool True if cleared successfully.
     * 
     * @throws Exception When clearing fails.
     */
    public function clearKnowledgeBase(bool $confirm = false): bool
    {
        if (!$confirm) {
            throw new Exception('Clearing knowledge base requires explicit confirmation');
        }

        try {
            Logger::warning('Clearing entire knowledge base - this cannot be undone');

            // Clear vector storage
            $this->vectorManager->deleteVectors('*', '*');

            // Clear indexed chunks
            global $wpdb;
            $tableName = $wpdb->prefix . 'woo_ai_knowledge_base';
            $wpdb->query("TRUNCATE TABLE {$tableName}");

            // Clear all caches
            $this->clearAllCaches();

            // Reset processing status
            $this->resetProcessingStatus();

            // Update last cleared timestamp
            update_option('woo_ai_kb_last_cleared', current_time('mysql'));

            Logger::info('Knowledge base cleared successfully');
            
            return true;

        } catch (Exception $e) {
            Logger::error('Failed to clear knowledge base: ' . $e->getMessage());
            throw new Exception('Clear operation failed: ' . $e->getMessage());
        }
    }

    /**
     * Setup WordPress cron schedules for automated operations
     *
     * @since 1.0.0
     */
    private function setupCronSchedules(): void
    {
        // Register custom cron intervals if they don't exist
        add_filter('cron_schedules', function($schedules) {
            if (!isset($schedules['twice_daily'])) {
                $schedules['twice_daily'] = [
                    'interval' => 12 * HOUR_IN_SECONDS,
                    'display' => __('Twice Daily', 'woo-ai-assistant')
                ];
            }
            return $schedules;
        });

        // Schedule cron events if not already scheduled
        foreach ($this->cronHooks as $hook => $interval) {
            if (!wp_next_scheduled($hook)) {
                wp_schedule_event(time(), $interval, $hook);
                Logger::info("Scheduled cron event: {$hook} ({$interval})");
            }
        }
    }

    /**
     * Process a single content type during rebuild
     *
     * @since 1.0.0
     * @param string $contentType The content type to process.
     * @param array  $args Processing arguments.
     * 
     * @return array Processing results for this content type.
     */
    private function processContentType(string $contentType, array $args): array
    {
        $results = [
            'content_type' => $contentType,
            'items_processed' => 0,
            'chunks_created' => 0,
            'embeddings_generated' => 0,
            'processing_time' => 0,
            'errors' => []
        ];

        $startTime = microtime(true);

        try {
            // Scan content
            $content = $this->scanContentType($contentType, $args);
            $results['items_processed'] = count($content);

            if (empty($content)) {
                Logger::info("No content found for type: {$contentType}");
                return $results;
            }

            // Process in batches
            $batches = array_chunk($content, $args['batch_size']);
            
            foreach ($batches as $batch) {
                // Index the batch
                foreach ($batch as $item) {
                    try {
                        $indexResult = $this->indexer->indexSingleItem($item, $args['force_rebuild']);
                        $results['chunks_created'] += $indexResult['chunks_created'];
                        $results['embeddings_generated'] += $indexResult['embeddings_generated'];

                    } catch (Exception $e) {
                        $error = "Failed to index item {$item['id']}: " . $e->getMessage();
                        $results['errors'][] = $error;
                        Logger::error($error);
                    }
                }
            }

            $results['processing_time'] = round(microtime(true) - $startTime, 2);

            Logger::info("Processed content type: {$contentType}", $results);

        } catch (Exception $e) {
            $results['errors'][] = $e->getMessage();
            Logger::error("Failed to process content type {$contentType}: " . $e->getMessage());
        }

        return $results;
    }

    /**
     * Process incremental updates for a content type
     *
     * @since 1.0.0
     * @param string $contentType The content type to check.
     * @param array  $args Processing arguments.
     * 
     * @return array Results of the incremental processing.
     */
    private function processIncrementalContentType(string $contentType, array $args): array
    {
        $results = [
            'updated' => 0,
            'removed' => 0,
            'chunks' => 0
        ];

        try {
            // Get content modified since last sync
            $modifiedContent = $this->scanContentType($contentType, [
                'modified_since' => $args['since'],
                'batch_size' => $args['batch_size']
            ]);

            // Process modified content
            foreach ($modifiedContent as $item) {
                $indexResult = $this->indexer->indexSingleItem($item, true);
                $results['updated']++;
                $results['chunks'] += $indexResult['chunks_created'];
            }

            // Check for deleted content and remove from KB
            $deletedIds = $this->findDeletedContent($contentType, $args['since']);
            foreach ($deletedIds as $deletedId) {
                $this->indexer->removeContent($deletedId, $contentType);
                $results['removed']++;
            }

        } catch (Exception $e) {
            Logger::error("Incremental processing failed for {$contentType}: " . $e->getMessage());
        }

        return $results;
    }

    /**
     * Scan content by type with proper method mapping
     *
     * @since 1.0.0
     * @param string $contentType The content type to scan.
     * @param array  $args Scanning arguments.
     * 
     * @return array Scanned content items.
     */
    private function scanContentType(string $contentType, array $args): array
    {
        switch ($contentType) {
            case 'product':
                return $this->scanner->scanProducts($args);
            case 'page':
                return $this->scanner->scanPages($args);
            case 'post':
                return $this->scanner->scanPosts($args);
            case 'woocommerce_settings':
                return $this->scanner->scanWooCommerceSettings($args);
            case 'category':
                return $this->scanner->scanCategories($args);
            default:
                Logger::warning("Unknown content type: {$contentType}");
                return [];
        }
    }

    /**
     * Find content that has been deleted since last sync
     *
     * @since 1.0.0
     * @param string $contentType The content type to check.
     * @param string $since Date threshold for checking deletions.
     * 
     * @return array Array of deleted content IDs.
     */
    private function findDeletedContent(string $contentType, string $since): array
    {
        global $wpdb;
        
        try {
            // Get current content IDs
            $currentContent = $this->scanContentType($contentType, ['fields' => 'ids']);
            $currentIds = array_column($currentContent, 'id');

            // Get previously indexed content IDs for this type
            $kbTable = $wpdb->prefix . 'woo_ai_knowledge_base';
            $indexedIds = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT content_id FROM {$kbTable} WHERE content_type = %s",
                $contentType
            ));

            // Find deleted content (in KB but not in current scan)
            return array_diff($indexedIds, $currentIds);

        } catch (Exception $e) {
            Logger::error("Failed to find deleted content for {$contentType}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Start a processing operation
     *
     * @since 1.0.0
     * @param string $operation The operation name.
     * @param array  $args Operation arguments.
     */
    private function startOperation(string $operation, array $args): void
    {
        $this->processingStatus = [
            'is_running' => true,
            'current_operation' => $operation,
            'progress' => 0,
            'total_items' => $this->estimateTotalItems($args),
            'processed_items' => 0,
            'errors' => [],
            'started_at' => current_time('mysql'),
            'estimated_completion' => null
        ];

        // Store in database for persistence
        update_option('woo_ai_kb_processing_status', $this->processingStatus);
    }

    /**
     * Update processing progress
     *
     * @since 1.0.0
     * @param string $currentItem Current item being processed.
     * @param array  $totalItems Total items to process.
     */
    private function updateProgress(string $currentItem, array $totalItems): void
    {
        $this->processingStatus['processed_items']++;
        $this->processingStatus['progress'] = round(
            ($this->processingStatus['processed_items'] / count($totalItems)) * 100,
            2
        );

        // Update estimated completion time
        $this->processingStatus['estimated_completion'] = $this->estimateTimeRemaining();

        // Update database every 10 items to avoid too many writes
        if ($this->processingStatus['processed_items'] % 10 === 0) {
            update_option('woo_ai_kb_processing_status', $this->processingStatus);
        }
    }

    /**
     * Complete a processing operation
     *
     * @since 1.0.0
     * @param array $results Operation results.
     */
    private function completeOperation(array $results): void
    {
        $this->processingStatus = [
            'is_running' => false,
            'current_operation' => '',
            'progress' => 100,
            'total_items' => $this->processingStatus['total_items'],
            'processed_items' => $this->processingStatus['processed_items'],
            'errors' => $this->processingStatus['errors'],
            'started_at' => $this->processingStatus['started_at'],
            'completed_at' => current_time('mysql'),
            'results' => $results
        ];

        update_option('woo_ai_kb_processing_status', $this->processingStatus);
    }

    /**
     * Mark operation as failed
     *
     * @since 1.0.0
     * @param string $error Error message.
     */
    private function failOperation(string $error): void
    {
        $this->processingStatus['is_running'] = false;
        $this->processingStatus['errors'][] = $error;
        $this->processingStatus['failed_at'] = current_time('mysql');

        update_option('woo_ai_kb_processing_status', $this->processingStatus);
    }

    /**
     * Reset processing status
     *
     * @since 1.0.0
     */
    private function resetProcessingStatus(): void
    {
        $this->processingStatus = [
            'is_running' => false,
            'current_operation' => '',
            'progress' => 0,
            'total_items' => 0,
            'processed_items' => 0,
            'errors' => [],
            'started_at' => null,
            'estimated_completion' => null
        ];

        delete_option('woo_ai_kb_processing_status');
    }

    /**
     * Estimate total items for processing
     *
     * @since 1.0.0
     * @param array $args Processing arguments.
     * 
     * @return int Estimated total items.
     */
    private function estimateTotalItems(array $args): int
    {
        $total = 0;
        $contentTypes = $args['content_types'] ?? [];

        foreach ($contentTypes as $type) {
            switch ($type) {
                case 'product':
                    $total += wp_count_posts('product')->publish ?? 0;
                    break;
                case 'page':
                    $total += wp_count_posts('page')->publish ?? 0;
                    break;
                case 'post':
                    $total += wp_count_posts('post')->publish ?? 0;
                    break;
                case 'category':
                    $total += wp_count_terms('product_cat');
                    break;
                default:
                    $total += 10; // Default estimate
            }
        }

        return $total;
    }

    /**
     * Estimate time remaining for current operation
     *
     * @since 1.0.0
     * @return string|null Estimated completion time or null.
     */
    private function estimateTimeRemaining(): ?string
    {
        if (!$this->processingStatus['is_running'] || !$this->processingStatus['started_at']) {
            return null;
        }

        $startTime = strtotime($this->processingStatus['started_at']);
        $elapsed = time() - $startTime;
        $processed = $this->processingStatus['processed_items'];
        $total = $this->processingStatus['total_items'];

        if ($processed === 0 || $total === 0) {
            return null;
        }

        $avgTimePerItem = $elapsed / $processed;
        $remaining = $total - $processed;
        $estimatedSeconds = $remaining * $avgTimePerItem;

        return date('Y-m-d H:i:s', time() + $estimatedSeconds);
    }

    /**
     * Get processing queue size
     *
     * @since 1.0.0
     * @return int Queue size.
     */
    private function getProcessingQueueSize(): int
    {
        // This would typically check a job queue
        // For now, return simple calculation
        return max(0, $this->processingStatus['total_items'] - $this->processingStatus['processed_items']);
    }

    /**
     * Calculate cache hit rate across components
     *
     * @since 1.0.0
     * @return float Cache hit rate as percentage.
     */
    private function calculateCacheHitRate(): float
    {
        try {
            $cacheStats = Cache::getInstance()->getStatistics();
            $hits = $cacheStats['hits'] ?? 0;
            $misses = $cacheStats['misses'] ?? 0;
            $total = $hits + $misses;

            return $total > 0 ? round(($hits / $total) * 100, 2) : 0.0;

        } catch (Exception $e) {
            return 0.0;
        }
    }

    /**
     * Calculate API success rate across all services
     *
     * @since 1.0.0
     * @return float API success rate as percentage.
     */
    private function calculateApiSuccessRate(): float
    {
        try {
            $apiConfig = ApiConfiguration::getInstance();
            $stats = $apiConfig->getApiStatistics();

            $totalRequests = array_sum(array_column($stats, 'total_requests'));
            $successfulRequests = array_sum(array_column($stats, 'successful_requests'));

            return $totalRequests > 0 ? round(($successfulRequests / $totalRequests) * 100, 2) : 100.0;

        } catch (Exception $e) {
            return 0.0;
        }
    }

    /**
     * Clear processing-related caches
     *
     * @since 1.0.0
     */
    private function clearProcessingCaches(): void
    {
        try {
            $cache = Cache::getInstance();
            $cache->clearGroup('woo_ai_kb');
            $cache->clearGroup('woo_ai_scanner');
            $cache->clearGroup('woo_ai_indexer');
            $cache->clearGroup('woo_ai_vectors');

            Logger::info('Processing caches cleared');

        } catch (Exception $e) {
            Logger::error('Failed to clear processing caches: ' . $e->getMessage());
        }
    }

    /**
     * Clear all knowledge base related caches
     *
     * @since 1.0.0
     */
    private function clearAllCaches(): void
    {
        try {
            // Clear component caches
            $this->scanner->clearCache();
            $this->indexer->clearCache();
            $this->vectorManager->clearCache();
            $this->aiManager->clearConversationCache();
            $this->embeddingGenerator->clearCache();

            // Clear WordPress object cache
            wp_cache_flush();

            Logger::info('All knowledge base caches cleared');

        } catch (Exception $e) {
            Logger::error('Failed to clear all caches: ' . $e->getMessage());
        }
    }
}