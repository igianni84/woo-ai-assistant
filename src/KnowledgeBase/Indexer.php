<?php

/**
 * Knowledge Base Indexer Class
 *
 * Orchestrates the indexing process for the AI knowledge base. Processes content
 * from the Scanner, applies chunking strategies, manages database storage,
 * and provides batch processing capabilities for optimal performance.
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
use WooAiAssistant\Common\Sanitizer;
use WooAiAssistant\Common\Utils;
use Exception;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Indexer
 *
 * Comprehensive content indexing with batch processing and caching.
 *
 * @since 1.0.0
 */
class Indexer
{
    use Singleton;

    /**
     * Database table name for knowledge base storage
     *
     * @var string
     */
    private string $tableName;

    /**
     * Batch size for database operations
     *
     * @var int
     */
    private int $batchSize = 25;

    /**
     * Cache TTL for indexed content (in seconds)
     *
     * @var int
     */
    private int $cacheTtl = 3600; // 1 hour

    /**
     * Maximum number of chunks to process per request
     *
     * @var int
     */
    private int $maxChunksPerRequest = 100;

    /**
     * ChunkingStrategy instance
     *
     * @var ChunkingStrategy
     */
    private ChunkingStrategy $chunkingStrategy;

    /**
     * Scanner instance for content retrieval
     *
     * @var Scanner
     */
    private Scanner $scanner;

    /**
     * WordPress database instance
     *
     * @var \wpdb
     */
    private \wpdb $wpdb;

    /**
     * Indexing statistics
     *
     * @var array
     */
    private array $stats = [
        'total_processed' => 0,
        'successful_inserts' => 0,
        'failed_inserts' => 0,
        'updated_chunks' => 0,
        'processing_time' => 0,
        'memory_peak' => 0
    ];

    /**
     * Content type processing status
     *
     * @var array
     */
    private array $processingStatus = [
        'current_content_type' => null,
        'current_content_id' => null,
        'batch_progress' => 0,
        'total_items' => 0
    ];

    /**
     * Initialize the indexer
     *
     * @return void
     * @throws Exception When required dependencies are not available
     */
    protected function init(): void
    {
        global $wpdb;

        $this->wpdb = $wpdb;
        $this->tableName = $wpdb->prefix . 'woo_ai_knowledge_base';

        // Initialize dependencies
        $this->chunkingStrategy = ChunkingStrategy::getInstance();
        $this->scanner = Scanner::getInstance();

        // Verify database table exists
        $this->verifyDatabaseTable();

        // Reset statistics
        $this->resetStatistics();

        Logger::debug('Knowledge Base Indexer initialized', [
            'table_name' => $this->tableName,
            'batch_size' => $this->batchSize,
            'cache_ttl' => $this->cacheTtl,
            'max_chunks_per_request' => $this->maxChunksPerRequest
        ]);
    }

    /**
     * Index all content from scanner with batch processing
     *
     * Main indexing method that orchestrates the complete indexing process
     * for all content types, with progress tracking and error handling.
     *
     * @since 1.0.0
     * @param array $args Optional. Indexing arguments.
     * @param array $args['content_types'] Content types to index. Default all supported types.
     * @param bool  $args['force_reindex'] Whether to reindex existing content. Default false.
     * @param bool  $args['cleanup_orphans'] Whether to remove orphaned chunks. Default true.
     * @param int   $args['batch_size'] Override default batch size. Default 25.
     * @param int   $args['max_execution_time'] Maximum execution time in seconds. Default 300.
     *
     * @return array Indexing results with statistics and any errors.
     *               Contains 'success' boolean, 'statistics', 'errors', and 'processing_summary'.
     *
     * @throws Exception When indexing process fails critically.
     *
     * @example
     * ```php
     * $indexer = Indexer::getInstance();
     * $result = $indexer->indexAllContent([
     *     'content_types' => ['product', 'page'],
     *     'force_reindex' => true
     * ]);
     * if ($result['success']) {
     *     echo "Indexed {$result['statistics']['successful_inserts']} chunks";
     * }
     * ```
     */
    public function indexAllContent(array $args = []): array
    {
        $startTime = microtime(true);
        $this->resetStatistics();

        try {
            // Parse arguments
            $defaults = [
                'content_types' => ['product', 'page', 'post', 'woocommerce_settings', 'product_cat', 'product_tag'],
                'force_reindex' => false,
                'cleanup_orphans' => true,
                'batch_size' => $this->batchSize,
                'max_execution_time' => 300
            ];

            $args = wp_parse_args($args, $defaults);
            $this->batchSize = $args['batch_size'];

            // Set maximum execution time
            if (function_exists('set_time_limit')) {
                set_time_limit($args['max_execution_time']);
            }

            Logger::info('Starting comprehensive content indexing', [
                'content_types' => $args['content_types'],
                'force_reindex' => $args['force_reindex'],
                'batch_size' => $this->batchSize,
                'max_execution_time' => $args['max_execution_time']
            ]);

            $errors = [];
            $processingSummary = [];
            $totalIndexed = 0;

            // Process each content type
            foreach ($args['content_types'] as $contentType) {
                try {
                    $this->processingStatus['current_content_type'] = $contentType;

                    Logger::info("Starting indexing for content type: {$contentType}");

                    $typeResult = $this->indexContentType($contentType, $args['force_reindex']);

                    $processingSummary[$contentType] = $typeResult;
                    $totalIndexed += $typeResult['chunks_processed'];

                    Logger::info("Completed indexing for content type: {$contentType}", [
                        'chunks_processed' => $typeResult['chunks_processed'],
                        'items_processed' => $typeResult['items_processed']
                    ]);
                } catch (Exception $e) {
                    $errors[] = [
                        'content_type' => $contentType,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ];

                    Logger::error("Failed to index content type: {$contentType}", [
                        'error' => $e->getMessage()
                    ]);

                    // Continue with other content types
                    continue;
                }
            }

            // Cleanup orphaned chunks if requested
            if ($args['cleanup_orphans']) {
                try {
                    $cleanupResult = $this->cleanupOrphanedChunks();
                    Logger::info('Orphaned chunks cleanup completed', $cleanupResult);
                } catch (Exception $e) {
                    $errors[] = [
                        'operation' => 'cleanup_orphans',
                        'error' => $e->getMessage()
                    ];
                }
            }

            // Update statistics
            $this->stats['processing_time'] = microtime(true) - $startTime;
            $this->stats['memory_peak'] = memory_get_peak_usage(true);

            // Determine overall success
            $success = $totalIndexed > 0 || empty($errors);

            Logger::info('Comprehensive content indexing completed', [
                'total_indexed' => $totalIndexed,
                'processing_time' => round($this->stats['processing_time'], 2),
                'memory_peak_mb' => round($this->stats['memory_peak'] / 1024 / 1024, 2),
                'errors_count' => count($errors),
                'success' => $success
            ]);

            return [
                'success' => $success,
                'statistics' => $this->stats,
                'processing_summary' => $processingSummary,
                'errors' => $errors,
                'timestamp' => current_time('mysql')
            ];
        } catch (Exception $e) {
            $this->stats['processing_time'] = microtime(true) - $startTime;

            Logger::error('Comprehensive content indexing failed', [
                'error' => $e->getMessage(),
                'processing_time' => round($this->stats['processing_time'], 2),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Index specific content type with batch processing
     *
     * Processes all content of a specific type, applying chunking and storing
     * in the knowledge base with intelligent batching for performance.
     *
     * @since 1.0.0
     * @param string $contentType Content type to index
     * @param bool   $forceReindex Whether to reindex existing content
     *
     * @return array Processing results for the content type
     *
     * @throws Exception When content type processing fails
     */
    public function indexContentType(string $contentType, bool $forceReindex = false): array
    {
        try {
            Logger::debug("Starting indexing for content type: {$contentType}", [
                'force_reindex' => $forceReindex
            ]);

            // Get content from scanner
            $scannerContent = $this->getScannerContent($contentType);

            if (empty($scannerContent)) {
                Logger::warning("No content found for type: {$contentType}");
                return [
                    'items_processed' => 0,
                    'chunks_processed' => 0,
                    'chunks_inserted' => 0,
                    'chunks_updated' => 0,
                    'processing_time' => 0
                ];
            }

            $startTime = microtime(true);
            $itemsProcessed = 0;
            $chunksProcessed = 0;
            $chunksInserted = 0;
            $chunksUpdated = 0;

            // Update processing status
            $this->processingStatus['total_items'] = count($scannerContent);
            $this->processingStatus['batch_progress'] = 0;

            // Process content in batches
            $contentBatches = array_chunk($scannerContent, $this->batchSize);

            foreach ($contentBatches as $batchIndex => $contentBatch) {
                try {
                    $batchResult = $this->processBatch($contentBatch, $contentType, $forceReindex);

                    $itemsProcessed += $batchResult['items_processed'];
                    $chunksProcessed += $batchResult['chunks_processed'];
                    $chunksInserted += $batchResult['chunks_inserted'];
                    $chunksUpdated += $batchResult['chunks_updated'];

                    $this->processingStatus['batch_progress'] = ($batchIndex + 1) / count($contentBatches);

                    Logger::debug("Batch {$batchIndex} processed for {$contentType}", [
                        'items_in_batch' => count($contentBatch),
                        'chunks_processed' => $batchResult['chunks_processed']
                    ]);
                } catch (Exception $e) {
                    Logger::error("Batch processing failed for {$contentType}", [
                        'batch_index' => $batchIndex,
                        'error' => $e->getMessage()
                    ]);
                    // Continue with next batch
                    continue;
                }
            }

            $processingTime = microtime(true) - $startTime;

            return [
                'items_processed' => $itemsProcessed,
                'chunks_processed' => $chunksProcessed,
                'chunks_inserted' => $chunksInserted,
                'chunks_updated' => $chunksUpdated,
                'processing_time' => $processingTime
            ];
        } catch (Exception $e) {
            Logger::error("Content type indexing failed: {$contentType}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Index single content item with chunking
     *
     * Processes a single content item, applies chunking strategy,
     * and stores chunks in the knowledge base.
     *
     * @since 1.0.0
     * @param array $contentData Content data from scanner
     * @param bool  $forceReindex Whether to reindex if content exists
     *
     * @return array Processing results for the item
     *
     * @throws Exception When content processing fails
     */
    public function indexSingleItem(array $contentData, bool $forceReindex = false): array
    {
        try {
            $this->validateContentData($contentData);

            $contentId = $contentData['id'];
            $contentType = $contentData['type'];
            $content = $contentData['content'];

            Logger::debug("Indexing single item", [
                'content_id' => $contentId,
                'content_type' => $contentType,
                'content_length' => strlen($content),
                'force_reindex' => $forceReindex
            ]);

            // Check if content already exists and is up-to-date
            if (!$forceReindex && $this->isContentUpToDate($contentId, $contentType, $contentData['last_modified'] ?? null)) {
                Logger::debug("Content is up-to-date, skipping", [
                    'content_id' => $contentId,
                    'content_type' => $contentType
                ]);
                return [
                    'chunks_processed' => 0,
                    'chunks_inserted' => 0,
                    'chunks_updated' => 0,
                    'skipped' => true
                ];
            }

            // Remove existing chunks for this content if reindexing
            if ($forceReindex) {
                $this->removeContentChunks($contentId, $contentType);
            }

            // Prepare metadata for chunks
            $baseMetadata = [
                'title' => $contentData['title'] ?? '',
                'url' => $contentData['url'] ?? '',
                'language' => $contentData['language'] ?? 'en',
                'last_modified' => $contentData['last_modified'] ?? current_time('mysql'),
                'source_metadata' => $contentData['metadata'] ?? []
            ];

            // Apply chunking strategy
            $chunks = $this->chunkingStrategy->chunkContent($content, $contentType, [
                'metadata' => $baseMetadata
            ]);

            if (empty($chunks)) {
                Logger::warning("No chunks generated for content", [
                    'content_id' => $contentId,
                    'content_type' => $contentType
                ]);
                return [
                    'chunks_processed' => 0,
                    'chunks_inserted' => 0,
                    'chunks_updated' => 0
                ];
            }

            // Store chunks in database
            $insertResult = $this->storeChunks($chunks, $contentId, $contentType, $baseMetadata);

            Logger::info("Single item indexing completed", [
                'content_id' => $contentId,
                'content_type' => $contentType,
                'chunks_created' => count($chunks),
                'chunks_inserted' => $insertResult['inserted'],
                'chunks_updated' => $insertResult['updated']
            ]);

            return [
                'chunks_processed' => count($chunks),
                'chunks_inserted' => $insertResult['inserted'],
                'chunks_updated' => $insertResult['updated'],
                'skipped' => false
            ];
        } catch (Exception $e) {
            Logger::error('Single item indexing failed', [
                'content_id' => $contentData['id'] ?? 'unknown',
                'content_type' => $contentData['type'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Remove content from knowledge base
     *
     * Removes all chunks associated with specific content from the knowledge base.
     * Used when content is deleted or needs to be completely reindexed.
     *
     * @since 1.0.0
     * @param int|string $contentId Content identifier
     * @param string     $contentType Content type
     *
     * @return bool True if removal was successful, false otherwise
     */
    public function removeContent($contentId, string $contentType): bool
    {
        try {
            $result = $this->removeContentChunks($contentId, $contentType);

            Logger::info('Content removed from knowledge base', [
                'content_id' => $contentId,
                'content_type' => $contentType,
                'chunks_removed' => $result
            ]);

            return $result > 0;
        } catch (Exception $e) {
            Logger::error('Failed to remove content from knowledge base', [
                'content_id' => $contentId,
                'content_type' => $contentType,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get knowledge base statistics
     *
     * @return array Statistics about indexed content and performance
     */
    public function getStatistics(): array
    {
        global $wpdb;

        try {
            // Get chunk statistics by content type
            $chunkStats = $wpdb->get_results($wpdb->prepare(
                "SELECT 
                    content_type,
                    COUNT(*) as total_chunks,
                    COUNT(DISTINCT content_id) as unique_content_items,
                    AVG(word_count) as avg_word_count,
                    MAX(updated_at) as last_updated
                FROM {$this->tableName} 
                WHERE is_active = %d
                GROUP BY content_type",
                1
            ), ARRAY_A);

            // Get total statistics
            $totalStats = $wpdb->get_row($wpdb->prepare(
                "SELECT 
                    COUNT(*) as total_chunks,
                    SUM(word_count) as total_words,
                    COUNT(DISTINCT content_type) as content_types,
                    COUNT(DISTINCT content_id) as total_content_items,
                    AVG(word_count) as avg_chunk_size,
                    MAX(updated_at) as last_indexing
                FROM {$this->tableName} 
                WHERE is_active = %d",
                1
            ), ARRAY_A);

            return [
                'current_indexing_stats' => $this->stats,
                'processing_status' => $this->processingStatus,
                'database_statistics' => [
                    'by_content_type' => $chunkStats ?: [],
                    'totals' => $totalStats ?: []
                ],
                'configuration' => [
                    'batch_size' => $this->batchSize,
                    'cache_ttl' => $this->cacheTtl,
                    'max_chunks_per_request' => $this->maxChunksPerRequest,
                    'table_name' => $this->tableName
                ],
                'chunking_strategy_stats' => $this->chunkingStrategy->getStatistics()
            ];
        } catch (Exception $e) {
            Logger::error('Failed to retrieve indexer statistics', [
                'error' => $e->getMessage()
            ]);
            return [
                'error' => 'Failed to retrieve statistics',
                'current_indexing_stats' => $this->stats
            ];
        }
    }

    /**
     * Get scanner content for specific content type
     *
     * @param string $contentType Content type to scan
     * @return array Scanner content data
     */
    private function getScannerContent(string $contentType): array
    {
        try {
            switch ($contentType) {
                case 'product':
                    return $this->scanner->scanProducts();
                case 'page':
                    return $this->scanner->scanPages();
                case 'post':
                    return $this->scanner->scanPosts();
                case 'woocommerce_settings':
                    return $this->scanner->scanWooCommerceSettings();
                case 'product_cat':
                case 'product_tag':
                    $categories = $this->scanner->scanCategories();
                    // Filter by specific taxonomy
                    return array_filter($categories, function ($item) use ($contentType) {
                        return $item['type'] === $contentType;
                    });
                default:
                    Logger::warning("Unsupported content type: {$contentType}");
                    return [];
            }
        } catch (Exception $e) {
            Logger::error("Failed to get scanner content for type: {$contentType}", [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Process batch of content items
     *
     * @param array  $contentBatch Batch of content items
     * @param string $contentType Content type
     * @param bool   $forceReindex Whether to force reindexing
     * @return array Batch processing results
     */
    private function processBatch(array $contentBatch, string $contentType, bool $forceReindex): array
    {
        $itemsProcessed = 0;
        $chunksProcessed = 0;
        $chunksInserted = 0;
        $chunksUpdated = 0;

        foreach ($contentBatch as $contentItem) {
            try {
                $result = $this->indexSingleItem($contentItem, $forceReindex);

                if (!$result['skipped']) {
                    $itemsProcessed++;
                    $chunksProcessed += $result['chunks_processed'];
                    $chunksInserted += $result['chunks_inserted'];
                    $chunksUpdated += $result['chunks_updated'];
                }
            } catch (Exception $e) {
                Logger::warning("Failed to process item in batch", [
                    'content_id' => $contentItem['id'] ?? 'unknown',
                    'content_type' => $contentType,
                    'error' => $e->getMessage()
                ]);
                // Continue with next item
                continue;
            }
        }

        // Update global statistics
        $this->stats['total_processed'] += $itemsProcessed;
        $this->stats['successful_inserts'] += $chunksInserted;
        $this->stats['updated_chunks'] += $chunksUpdated;

        return [
            'items_processed' => $itemsProcessed,
            'chunks_processed' => $chunksProcessed,
            'chunks_inserted' => $chunksInserted,
            'chunks_updated' => $chunksUpdated
        ];
    }

    /**
     * Store chunks in database with batch insert
     *
     * @param array  $chunks Processed chunks
     * @param int    $contentId Content identifier
     * @param string $contentType Content type
     * @param array  $baseMetadata Base metadata for chunks
     * @return array Storage results
     */
    private function storeChunks(array $chunks, int $contentId, string $contentType, array $baseMetadata): array
    {
        try {
            $inserted = 0;
            $updated = 0;
            $values = [];

            foreach ($chunks as $chunk) {
                $chunkMetadata = array_merge($baseMetadata, $chunk['metadata']);

                $values[] = $this->wpdb->prepare(
                    "(%s, %d, %s, %s, %s, %s, %d, %d, %d, %s, %d)",
                    $contentType,
                    $contentId,
                    $chunk['text'],
                    null, // embedding (will be populated in Task 2.3)
                    wp_json_encode($chunkMetadata),
                    $chunk['chunk_hash'],
                    $chunk['chunk_index'],
                    $chunk['total_chunks'],
                    $chunk['word_count'],
                    null, // embedding_model (will be populated in Task 2.3)
                    1 // is_active
                );
            }

            if (!empty($values)) {
                $sql = "INSERT INTO {$this->tableName} 
                       (content_type, content_id, chunk_text, embedding, metadata, chunk_hash, chunk_index, total_chunks, word_count, embedding_model, is_active) 
                       VALUES " . implode(',', $values) . "
                       ON DUPLICATE KEY UPDATE
                       chunk_text = VALUES(chunk_text),
                       metadata = VALUES(metadata),
                       chunk_index = VALUES(chunk_index),
                       total_chunks = VALUES(total_chunks),
                       word_count = VALUES(word_count),
                       updated_at = CURRENT_TIMESTAMP,
                       is_active = 1";

                $result = $this->wpdb->query($sql);

                if ($result === false) {
                    throw new Exception("Database insert failed: " . $this->wpdb->last_error);
                }

                $inserted = $this->wpdb->rows_affected;

                Logger::debug("Chunks stored in database", [
                    'content_id' => $contentId,
                    'content_type' => $contentType,
                    'chunks_count' => count($chunks),
                    'rows_affected' => $inserted
                ]);
            }

            return [
                'inserted' => $inserted,
                'updated' => $updated
            ];
        } catch (Exception $e) {
            Logger::error('Failed to store chunks in database', [
                'content_id' => $contentId,
                'content_type' => $contentType,
                'chunks_count' => count($chunks),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Remove chunks for specific content
     *
     * @param int    $contentId Content identifier
     * @param string $contentType Content type
     * @return int Number of removed chunks
     */
    private function removeContentChunks(int $contentId, string $contentType): int
    {
        $result = $this->wpdb->delete(
            $this->tableName,
            [
                'content_id' => $contentId,
                'content_type' => $contentType
            ],
            ['%d', '%s']
        );

        if ($result === false) {
            throw new Exception("Failed to remove chunks: " . $this->wpdb->last_error);
        }

        return $result;
    }

    /**
     * Check if content is up-to-date in knowledge base
     *
     * @param int         $contentId Content identifier
     * @param string      $contentType Content type
     * @param string|null $lastModified Last modified timestamp
     * @return bool True if up-to-date, false if needs reindexing
     */
    private function isContentUpToDate(int $contentId, string $contentType, ?string $lastModified): bool
    {
        if (!$lastModified) {
            return false;
        }

        $existingChunk = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT updated_at FROM {$this->tableName} 
             WHERE content_id = %d AND content_type = %s AND is_active = 1 
             LIMIT 1",
            $contentId,
            $contentType
        ));

        if (!$existingChunk) {
            return false;
        }

        return strtotime($existingChunk->updated_at) >= strtotime($lastModified);
    }

    /**
     * Cleanup orphaned chunks
     *
     * @return array Cleanup results
     */
    private function cleanupOrphanedChunks(): array
    {
        // This will be enhanced when we have better content tracking
        // For now, just mark inactive chunks older than 30 days as candidates for deletion

        $result = $this->wpdb->query($this->wpdb->prepare(
            "UPDATE {$this->tableName} 
             SET is_active = 0 
             WHERE updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY) 
             AND is_active = 1"
        ));

        return [
            'orphaned_chunks_marked' => $result ?: 0
        ];
    }

    /**
     * Validate content data structure
     *
     * @param array $contentData Content data to validate
     * @throws Exception When content data is invalid
     */
    private function validateContentData(array $contentData): void
    {
        $requiredFields = ['id', 'type', 'content'];

        foreach ($requiredFields as $field) {
            if (!isset($contentData[$field]) || empty($contentData[$field])) {
                throw new Exception("Missing or empty required field: {$field}");
            }
        }

        if (!is_numeric($contentData['id'])) {
            throw new Exception("Content ID must be numeric");
        }

        if (strlen($contentData['content']) < 10) {
            throw new Exception("Content too short for meaningful indexing");
        }
    }

    /**
     * Verify database table exists
     *
     * @throws Exception When table doesn't exist
     */
    private function verifyDatabaseTable(): void
    {
        $tableExists = $this->wpdb->get_var($this->wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $this->tableName
        ));

        if (!$tableExists) {
            throw new Exception("Knowledge base table does not exist: {$this->tableName}");
        }
    }

    /**
     * Reset indexing statistics
     */
    private function resetStatistics(): void
    {
        $this->stats = [
            'total_processed' => 0,
            'successful_inserts' => 0,
            'failed_inserts' => 0,
            'updated_chunks' => 0,
            'processing_time' => 0,
            'memory_peak' => 0
        ];

        $this->processingStatus = [
            'current_content_type' => null,
            'current_content_id' => null,
            'batch_progress' => 0,
            'total_items' => 0
        ];
    }

    /**
     * Set batch size for processing
     *
     * @param int $size Batch size (must be positive and reasonable)
     * @throws Exception When batch size is invalid
     */
    public function setBatchSize(int $size): void
    {
        if ($size <= 0 || $size > 100) {
            throw new Exception('Batch size must be between 1 and 100');
        }

        $this->batchSize = $size;
        Logger::debug('Indexer batch size updated', ['new_size' => $size]);
    }

    /**
     * Set cache TTL
     *
     * @param int $ttl Cache time-to-live in seconds
     * @throws Exception When TTL is invalid
     */
    public function setCacheTtl(int $ttl): void
    {
        if ($ttl < 0) {
            throw new Exception('Cache TTL must be non-negative');
        }

        $this->cacheTtl = $ttl;
        Logger::debug('Indexer cache TTL updated', ['new_ttl' => $ttl]);
    }

    /**
     * Get current processing status
     *
     * @return array Current processing status
     */
    public function getProcessingStatus(): array
    {
        return $this->processingStatus;
    }

    /**
     * Check if indexer is currently processing
     *
     * @return bool True if processing is active
     */
    public function isProcessing(): bool
    {
        return !empty($this->processingStatus['current_content_type']);
    }
}
