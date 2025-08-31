<?php

/**
 * Vector Manager Class
 *
 * Orchestrates vector operations with Pinecone, manages vector storage and retrieval,
 * implements similarity search algorithms, and provides efficient batch processing
 * for embedding management in the AI knowledge base.
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
use WooAiAssistant\Config\ApiConfiguration;
use Exception;
use WP_Error;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class VectorManager
 *
 * Comprehensive vector management with Pinecone integration.
 *
 * @since 1.0.0
 */
class VectorManager
{
    use Singleton;

    /**
     * API configuration instance
     *
     * @var ApiConfiguration
     */
    private ApiConfiguration $apiConfig;

    /**
     * EmbeddingGenerator instance
     *
     * @var EmbeddingGenerator
     */
    private EmbeddingGenerator $embeddingGenerator;

    /**
     * Database table name for knowledge base storage
     *
     * @var string
     */
    private string $tableName;

    /**
     * WordPress database instance
     *
     * @var \wpdb
     */
    private \wpdb $wpdb;

    /**
     * Pinecone index name
     *
     * @var string
     */
    private string $pineconeIndex = '';

    /**
     * Pinecone environment
     *
     * @var string
     */
    private string $pineconeEnvironment = '';

    /**
     * Vector dimension (for OpenAI text-embedding-3-small)
     *
     * @var int
     */
    private int $vectorDimension = 1536;

    /**
     * Batch size for vector operations
     *
     * @var int
     */
    private int $batchSize = 100;

    /**
     * Cache TTL for vector operations (in seconds)
     *
     * @var int
     */
    private int $cacheTtl = 3600;

    /**
     * Similarity search threshold (0.0 to 1.0)
     *
     * @var float
     */
    private float $similarityThreshold = 0.7;

    /**
     * Maximum number of similar results to return
     *
     * @var int
     */
    private int $maxSimilarResults = 10;

    /**
     * Rate limiting settings
     *
     * @var array
     */
    private array $rateLimits = [
        'max_requests_per_minute' => 60,
        'max_vectors_per_request' => 100,
        'retry_delays' => [1, 2, 4, 8] // exponential backoff in seconds
    ];

    /**
     * Vector operation statistics
     *
     * @var array
     */
    private array $stats = [
        'total_vectors_processed' => 0,
        'successful_upserts' => 0,
        'failed_upserts' => 0,
        'total_queries' => 0,
        'cache_hits' => 0,
        'api_calls' => 0,
        'processing_time' => 0
    ];

    /**
     * Initialize the vector manager
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
        $this->apiConfig = ApiConfiguration::getInstance();
        $this->embeddingGenerator = EmbeddingGenerator::getInstance();

        // Load Pinecone configuration
        $this->loadPineconeConfiguration();

        // Verify vector database connection
        $this->verifyVectorDatabaseConnection();

        // Reset statistics
        $this->resetStatistics();

        Logger::debug('Vector Manager initialized', [
            'pinecone_index' => $this->pineconeIndex,
            'pinecone_environment' => $this->pineconeEnvironment,
            'vector_dimension' => $this->vectorDimension,
            'batch_size' => $this->batchSize,
            'similarity_threshold' => $this->similarityThreshold
        ]);
    }

    /**
     * Store vectors in Pinecone and update local database
     *
     * Processes chunks from the database, generates embeddings, stores them in Pinecone,
     * and updates the local database with vector information.
     *
     * @since 1.0.0
     * @param array $args Optional. Vector storage arguments.
     * @param array $args['content_types'] Content types to process. Default all.
     * @param bool  $args['force_regenerate'] Whether to regenerate existing embeddings. Default false.
     * @param int   $args['batch_size'] Override default batch size. Default 100.
     * @param int   $args['limit'] Maximum number of chunks to process. Default unlimited.
     *
     * @return array Storage results with statistics and any errors.
     *               Contains 'success' boolean, 'statistics', and 'errors'.
     *
     * @throws Exception When vector storage process fails critically.
     *
     * @example
     * ```php
     * $vectorManager = VectorManager::getInstance();
     * $result = $vectorManager->storeVectors([
     *     'content_types' => ['product', 'page'],
     *     'force_regenerate' => true,
     *     'limit' => 500
     * ]);
     * if ($result['success']) {
     *     echo "Processed {$result['statistics']['successful_upserts']} vectors";
     * }
     * ```
     */
    public function storeVectors(array $args = []): array
    {
        $startTime = microtime(true);
        $this->resetStatistics();

        try {
            // Parse arguments
            $defaults = [
                'content_types' => [],
                'force_regenerate' => false,
                'batch_size' => $this->batchSize,
                'limit' => 0
            ];

            $args = wp_parse_args($args, $defaults);
            $this->batchSize = min($args['batch_size'], $this->rateLimits['max_vectors_per_request']);

            Logger::info('Starting vector storage process', [
                'content_types' => $args['content_types'],
                'force_regenerate' => $args['force_regenerate'],
                'batch_size' => $this->batchSize,
                'limit' => $args['limit']
            ]);

            $errors = [];
            $totalProcessed = 0;

            // Get chunks that need vector processing
            $chunks = $this->getChunksForVectorProcessing($args);

            if (empty($chunks)) {
                Logger::info('No chunks found for vector processing');
                return [
                    'success' => true,
                    'statistics' => $this->stats,
                    'errors' => []
                ];
            }

            Logger::info("Found {$chunks['total']} chunks for processing", [
                'batches' => ceil($chunks['total'] / $this->batchSize)
            ]);

            // Process chunks in batches
            $batchNumber = 0;
            while ($totalProcessed < $chunks['total'] && ($args['limit'] === 0 || $totalProcessed < $args['limit'])) {
                $batchNumber++;
                $offset = $totalProcessed;
                $batchLimit = min($this->batchSize, $chunks['total'] - $totalProcessed);

                if ($args['limit'] > 0) {
                    $batchLimit = min($batchLimit, $args['limit'] - $totalProcessed);
                }

                try {
                    $batchChunks = $this->getChunksBatch($args, $offset, $batchLimit);
                    
                    if (empty($batchChunks)) {
                        Logger::warning("No chunks returned for batch {$batchNumber}");
                        break;
                    }

                    $batchResult = $this->processBatchVectors($batchChunks, $batchNumber);

                    $totalProcessed += count($batchChunks);
                    
                    Logger::info("Batch {$batchNumber} completed", [
                        'chunks_processed' => count($batchChunks),
                        'successful_upserts' => $batchResult['successful'],
                        'failed_upserts' => $batchResult['failed'],
                        'total_processed' => $totalProcessed,
                        'remaining' => $chunks['total'] - $totalProcessed
                    ]);

                    // Add batch errors to overall errors
                    if (!empty($batchResult['errors'])) {
                        $errors = array_merge($errors, $batchResult['errors']);
                    }

                    // Rate limiting - small delay between batches
                    if ($totalProcessed < $chunks['total']) {
                        sleep(1);
                    }

                } catch (Exception $e) {
                    $errors[] = [
                        'batch' => $batchNumber,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ];

                    Logger::error("Batch {$batchNumber} failed", [
                        'error' => $e->getMessage()
                    ]);

                    // Continue with next batch
                    $totalProcessed += $batchLimit;
                }
            }

            // Update statistics
            $this->stats['processing_time'] = microtime(true) - $startTime;

            // Determine overall success
            $success = $totalProcessed > 0 && count($errors) < ($totalProcessed / 2);

            Logger::info('Vector storage process completed', [
                'total_processed' => $totalProcessed,
                'successful_upserts' => $this->stats['successful_upserts'],
                'failed_upserts' => $this->stats['failed_upserts'],
                'processing_time' => round($this->stats['processing_time'], 2),
                'errors_count' => count($errors),
                'success' => $success
            ]);

            return [
                'success' => $success,
                'statistics' => $this->stats,
                'errors' => $errors,
                'timestamp' => current_time('mysql')
            ];

        } catch (Exception $e) {
            $this->stats['processing_time'] = microtime(true) - $startTime;

            Logger::error('Vector storage process failed', [
                'error' => $e->getMessage(),
                'processing_time' => round($this->stats['processing_time'], 2),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Perform similarity search using vector embeddings
     *
     * Generates embedding for the query text and searches for similar vectors
     * in Pinecone, returning relevant knowledge base chunks.
     *
     * @since 1.0.0
     * @param string $query Search query text
     * @param array  $args Optional. Search arguments.
     * @param int    $args['top_k'] Maximum number of results to return. Default 10.
     * @param float  $args['threshold'] Minimum similarity score (0.0-1.0). Default 0.7.
     * @param array  $args['content_types'] Content types to filter. Default all.
     * @param bool   $args['include_metadata'] Whether to include full metadata. Default true.
     *
     * @return array Search results with similarity scores and metadata.
     *               Each result contains 'id', 'score', 'content', 'metadata'.
     *
     * @throws Exception When similarity search fails.
     *
     * @example
     * ```php
     * $vectorManager = VectorManager::getInstance();
     * $results = $vectorManager->similaritySearch('shipping policy', [
     *     'top_k' => 5,
     *     'threshold' => 0.8,
     *     'content_types' => ['page', 'woocommerce_settings']
     * ]);
     * foreach ($results['matches'] as $match) {
     *     echo "Score: {$match['score']}, Content: {$match['content']}";
     * }
     * ```
     */
    public function similaritySearch(string $query, array $args = []): array
    {
        try {
            // Parse arguments
            $defaults = [
                'top_k' => $this->maxSimilarResults,
                'threshold' => $this->similarityThreshold,
                'content_types' => [],
                'include_metadata' => true
            ];

            $args = wp_parse_args($args, $defaults);

            Logger::debug('Starting similarity search', [
                'query_length' => strlen($query),
                'top_k' => $args['top_k'],
                'threshold' => $args['threshold'],
                'content_types' => $args['content_types']
            ]);

            // Check cache first
            $cacheKey = $this->generateSearchCacheKey($query, $args);
            $cachedResults = Cache::getInstance()->get($cacheKey);

            if ($cachedResults !== false) {
                $this->stats['cache_hits']++;
                Logger::debug('Returning cached similarity search results');
                return $cachedResults;
            }

            // Generate embedding for query
            $queryEmbedding = $this->embeddingGenerator->generateEmbedding($query);

            if (empty($queryEmbedding)) {
                throw new Exception('Failed to generate query embedding');
            }

            // Search in Pinecone
            $pineconeResults = $this->searchVectorsInPinecone($queryEmbedding, $args);

            // Process and enrich results with local data
            $enrichedResults = $this->enrichSearchResults($pineconeResults, $args);

            // Filter by threshold
            $filteredResults = array_filter($enrichedResults['matches'], function ($match) use ($args) {
                return $match['score'] >= $args['threshold'];
            });

            // Sort by score descending
            usort($filteredResults, function ($a, $b) {
                return $b['score'] <=> $a['score'];
            });

            // Limit results
            $limitedResults = array_slice($filteredResults, 0, $args['top_k']);

            $results = [
                'matches' => $limitedResults,
                'total_matches' => count($limitedResults),
                'query' => $query,
                'processing_time' => $enrichedResults['processing_time'] ?? 0,
                'threshold_applied' => $args['threshold'],
                'timestamp' => current_time('mysql')
            ];

            // Cache results
            Cache::getInstance()->set($cacheKey, $results, $this->cacheTtl);

            $this->stats['total_queries']++;

            Logger::info('Similarity search completed', [
                'query_length' => strlen($query),
                'matches_found' => count($limitedResults),
                'threshold_applied' => $args['threshold'],
                'processing_time' => round($results['processing_time'], 3)
            ]);

            return $results;

        } catch (Exception $e) {
            Logger::error('Similarity search failed', [
                'query_length' => strlen($query),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Delete vectors from Pinecone and update local database
     *
     * Removes vector embeddings for specific content from Pinecone and marks
     * the corresponding chunks as no longer having embeddings.
     *
     * @since 1.0.0
     * @param int|string $contentId Content identifier
     * @param string     $contentType Content type
     *
     * @return bool True if deletion was successful, false otherwise
     */
    public function deleteVectors($contentId, string $contentType): bool
    {
        try {
            Logger::info('Starting vector deletion', [
                'content_id' => $contentId,
                'content_type' => $contentType
            ]);

            // Get chunk IDs that need vector deletion
            $chunkIds = $this->getChunkIdsForContent($contentId, $contentType);

            if (empty($chunkIds)) {
                Logger::info('No vectors found for deletion', [
                    'content_id' => $contentId,
                    'content_type' => $contentType
                ]);
                return true;
            }

            // Delete from Pinecone
            $pineconeResult = $this->deleteVectorsFromPinecone($chunkIds);

            // Update local database
            $localResult = $this->clearEmbeddingsInDatabase($contentId, $contentType);

            $success = $pineconeResult && $localResult;

            Logger::info('Vector deletion completed', [
                'content_id' => $contentId,
                'content_type' => $contentType,
                'chunks_affected' => count($chunkIds),
                'pinecone_success' => $pineconeResult,
                'database_success' => $localResult,
                'overall_success' => $success
            ]);

            return $success;

        } catch (Exception $e) {
            Logger::error('Vector deletion failed', [
                'content_id' => $contentId,
                'content_type' => $contentType,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get vector storage statistics and health information
     *
     * @return array Statistics about vector operations and system health
     */
    public function getVectorStatistics(): array
    {
        try {
            global $wpdb;

            // Get database statistics
            $dbStats = $wpdb->get_row($wpdb->prepare(
                "SELECT 
                    COUNT(*) as total_chunks,
                    COUNT(CASE WHEN embedding IS NOT NULL THEN 1 END) as chunks_with_embeddings,
                    COUNT(CASE WHEN embedding IS NULL THEN 1 END) as chunks_without_embeddings,
                    AVG(CASE WHEN embedding IS NOT NULL THEN word_count END) as avg_embedded_chunk_size,
                    MAX(updated_at) as last_embedding_update
                FROM {$this->tableName} 
                WHERE is_active = %d",
                1
            ), ARRAY_A);

            // Calculate embedding coverage
            $totalChunks = (int) ($dbStats['total_chunks'] ?? 0);
            $embeddedChunks = (int) ($dbStats['chunks_with_embeddings'] ?? 0);
            $embeddingCoverage = $totalChunks > 0 ? ($embeddedChunks / $totalChunks) * 100 : 0;

            // Get embedding statistics by content type
            $embeddingsByType = $wpdb->get_results($wpdb->prepare(
                "SELECT 
                    content_type,
                    COUNT(*) as total_chunks,
                    COUNT(CASE WHEN embedding IS NOT NULL THEN 1 END) as embedded_chunks,
                    COUNT(CASE WHEN embedding IS NULL THEN 1 END) as pending_chunks
                FROM {$this->tableName} 
                WHERE is_active = %d 
                GROUP BY content_type",
                1
            ), ARRAY_A);

            return [
                'current_session_stats' => $this->stats,
                'database_statistics' => [
                    'total_chunks' => $totalChunks,
                    'embedded_chunks' => $embeddedChunks,
                    'pending_chunks' => (int) ($dbStats['chunks_without_embeddings'] ?? 0),
                    'embedding_coverage_percent' => round($embeddingCoverage, 2),
                    'average_embedded_chunk_size' => (int) ($dbStats['avg_embedded_chunk_size'] ?? 0),
                    'last_embedding_update' => $dbStats['last_embedding_update'] ?? null,
                    'by_content_type' => $embeddingsByType ?: []
                ],
                'configuration' => [
                    'vector_dimension' => $this->vectorDimension,
                    'batch_size' => $this->batchSize,
                    'similarity_threshold' => $this->similarityThreshold,
                    'max_similar_results' => $this->maxSimilarResults,
                    'cache_ttl' => $this->cacheTtl,
                    'pinecone_index' => $this->pineconeIndex,
                    'pinecone_environment' => $this->pineconeEnvironment
                ],
                'api_status' => [
                    'pinecone_configured' => !empty($this->apiConfig->getApiKey('pinecone')),
                    'openai_configured' => !empty($this->apiConfig->getApiKey('openai')),
                    'development_mode' => $this->apiConfig->isDevelopmentMode()
                ],
                'rate_limits' => $this->rateLimits,
                'embedding_generator_stats' => $this->embeddingGenerator->getStatistics()
            ];

        } catch (Exception $e) {
            Logger::error('Failed to retrieve vector statistics', [
                'error' => $e->getMessage()
            ]);
            return [
                'error' => 'Failed to retrieve statistics',
                'current_session_stats' => $this->stats
            ];
        }
    }

    /**
     * Load Pinecone configuration from API settings
     *
     * @throws Exception When Pinecone configuration is invalid
     */
    private function loadPineconeConfiguration(): void
    {
        // Get configuration based on development/production mode
        if ($this->apiConfig->isDevelopmentMode()) {
            // Development mode - use environment variables
            $this->pineconeIndex = $this->apiConfig->getApiKey('pinecone_index') ?: 'woo-ai-assistant-dev';
            $this->pineconeEnvironment = $this->apiConfig->getApiKey('pinecone_environment') ?: 'development';
        } else {
            // Production mode - use WordPress options
            $this->pineconeIndex = get_option('woo_ai_assistant_pinecone_index', 'woo-ai-assistant');
            $this->pineconeEnvironment = get_option('woo_ai_assistant_pinecone_environment', 'production');
        }

        if (empty($this->pineconeIndex)) {
            throw new Exception('Pinecone index name is required');
        }

        Logger::debug('Pinecone configuration loaded', [
            'index' => $this->pineconeIndex,
            'environment' => $this->pineconeEnvironment,
            'development_mode' => $this->apiConfig->isDevelopmentMode()
        ]);
    }

    /**
     * Verify vector database connection
     *
     * @throws Exception When connection verification fails
     */
    private function verifyVectorDatabaseConnection(): void
    {
        if (empty($this->apiConfig->getApiKey('pinecone'))) {
            if ($this->apiConfig->isDevelopmentMode()) {
                Logger::warning('Pinecone API key not configured - vector operations will be simulated in development mode');
                return;
            } else {
                throw new Exception('Pinecone API key is required for vector operations');
            }
        }

        // TODO: Add actual Pinecone connection test when API is available
        Logger::debug('Vector database connection verified');
    }

    /**
     * Get chunks that need vector processing
     *
     * @param array $args Processing arguments
     * @return array Chunks count information
     */
    private function getChunksForVectorProcessing(array $args): array
    {
        $whereConditions = ['is_active = 1'];
        $params = [];

        // Add content type filter
        if (!empty($args['content_types'])) {
            $placeholders = str_repeat('%s,', count($args['content_types']) - 1) . '%s';
            $whereConditions[] = "content_type IN ({$placeholders})";
            $params = array_merge($params, $args['content_types']);
        }

        // Add embedding filter
        if (!$args['force_regenerate']) {
            $whereConditions[] = '(embedding IS NULL OR embedding = "")';
        }

        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

        $totalQuery = "SELECT COUNT(*) FROM {$this->tableName} {$whereClause}";
        $total = $this->wpdb->get_var($this->wpdb->prepare($totalQuery, ...$params));

        return [
            'total' => (int) $total,
            'conditions' => $whereConditions,
            'params' => $params
        ];
    }

    /**
     * Get a batch of chunks for processing
     *
     * @param array $args Processing arguments
     * @param int   $offset Batch offset
     * @param int   $limit Batch size
     * @return array Batch of chunks
     */
    private function getChunksBatch(array $args, int $offset, int $limit): array
    {
        $whereConditions = ['is_active = 1'];
        $params = [];

        // Add content type filter
        if (!empty($args['content_types'])) {
            $placeholders = str_repeat('%s,', count($args['content_types']) - 1) . '%s';
            $whereConditions[] = "content_type IN ({$placeholders})";
            $params = array_merge($params, $args['content_types']);
        }

        // Add embedding filter
        if (!$args['force_regenerate']) {
            $whereConditions[] = '(embedding IS NULL OR embedding = "")';
        }

        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

        $query = "SELECT id, content_type, content_id, chunk_text, chunk_index, metadata 
                  FROM {$this->tableName} {$whereClause} 
                  ORDER BY id ASC 
                  LIMIT %d OFFSET %d";

        $params[] = $limit;
        $params[] = $offset;

        return $this->wpdb->get_results($this->wpdb->prepare($query, ...$params), ARRAY_A);
    }

    /**
     * Process a batch of vectors
     *
     * @param array $chunks Chunks to process
     * @param int   $batchNumber Batch number for logging
     * @return array Batch processing results
     */
    private function processBatchVectors(array $chunks, int $batchNumber): array
    {
        $successful = 0;
        $failed = 0;
        $errors = [];

        try {
            // Generate embeddings for all chunks in batch
            $textsToEmbed = array_column($chunks, 'chunk_text');
            $embeddings = $this->embeddingGenerator->generateBatchEmbeddings($textsToEmbed);

            if (count($embeddings) !== count($chunks)) {
                throw new Exception("Embedding count mismatch: expected " . count($chunks) . ", got " . count($embeddings));
            }

            // Prepare vectors for Pinecone
            $vectors = [];
            foreach ($chunks as $index => $chunk) {
                $vectors[] = [
                    'id' => $this->generateVectorId($chunk),
                    'values' => $embeddings[$index],
                    'metadata' => $this->prepareVectorMetadata($chunk)
                ];
            }

            // Upsert to Pinecone
            $upsertResult = $this->upsertVectorsToPinecone($vectors);

            if ($upsertResult['success']) {
                // Update local database with embeddings
                $this->updateEmbeddingsInDatabase($chunks, $embeddings);
                $successful = count($chunks);
            } else {
                $failed = count($chunks);
                $errors[] = [
                    'type' => 'pinecone_upsert',
                    'message' => $upsertResult['error'] ?? 'Unknown Pinecone error',
                    'batch' => $batchNumber
                ];
            }

        } catch (Exception $e) {
            $failed = count($chunks);
            $errors[] = [
                'type' => 'batch_processing',
                'message' => $e->getMessage(),
                'batch' => $batchNumber
            ];

            Logger::error("Batch {$batchNumber} processing failed", [
                'error' => $e->getMessage(),
                'chunks_count' => count($chunks)
            ]);
        }

        // Update statistics
        $this->stats['total_vectors_processed'] += count($chunks);
        $this->stats['successful_upserts'] += $successful;
        $this->stats['failed_upserts'] += $failed;

        return [
            'successful' => $successful,
            'failed' => $failed,
            'errors' => $errors
        ];
    }

    /**
     * Search vectors in Pinecone
     *
     * @param array $queryEmbedding Query embedding vector
     * @param array $args Search arguments
     * @return array Pinecone search results
     */
    private function searchVectorsInPinecone(array $queryEmbedding, array $args): array
    {
        try {
            if ($this->apiConfig->isDevelopmentMode() && empty($this->apiConfig->getApiKey('pinecone'))) {
                // Development mode without Pinecone - return mock results
                return $this->generateMockSearchResults($args['top_k']);
            }

            $endpoint = $this->apiConfig->getApiEndpoint('pinecone', 'query', [
                'index' => $this->pineconeIndex
            ]);

            $headers = $this->apiConfig->getApiHeaders('pinecone');

            $requestBody = [
                'vector' => $queryEmbedding,
                'topK' => $args['top_k'],
                'includeValues' => false,
                'includeMetadata' => true
            ];

            // Add content type filter if specified
            if (!empty($args['content_types'])) {
                $requestBody['filter'] = [
                    'content_type' => ['$in' => $args['content_types']]
                ];
            }

            $response = wp_remote_post($endpoint, [
                'headers' => $headers,
                'body' => wp_json_encode($requestBody),
                'timeout' => 30
            ]);

            if (is_wp_error($response)) {
                throw new Exception('Pinecone API request failed: ' . $response->get_error_message());
            }

            $statusCode = wp_remote_retrieve_response_code($response);
            $responseBody = wp_remote_retrieve_body($response);

            if ($statusCode !== 200) {
                throw new Exception("Pinecone API returned status {$statusCode}: {$responseBody}");
            }

            $data = json_decode($responseBody, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON response from Pinecone: ' . json_last_error_msg());
            }

            $this->stats['api_calls']++;

            return $data;

        } catch (Exception $e) {
            Logger::error('Pinecone vector search failed', [
                'error' => $e->getMessage(),
                'query_vector_length' => count($queryEmbedding)
            ]);
            throw $e;
        }
    }

    /**
     * Upsert vectors to Pinecone
     *
     * @param array $vectors Vectors to upsert
     * @return array Upsert results
     */
    private function upsertVectorsToPinecone(array $vectors): array
    {
        try {
            if ($this->apiConfig->isDevelopmentMode() && empty($this->apiConfig->getApiKey('pinecone'))) {
                // Development mode without Pinecone - simulate success
                Logger::debug('Simulating Pinecone upsert in development mode', [
                    'vectors_count' => count($vectors)
                ]);
                return ['success' => true, 'upserted_count' => count($vectors)];
            }

            $endpoint = $this->apiConfig->getApiEndpoint('pinecone', 'upsert', [
                'index' => $this->pineconeIndex
            ]);

            $headers = $this->apiConfig->getApiHeaders('pinecone');

            $requestBody = [
                'vectors' => $vectors
            ];

            $response = wp_remote_post($endpoint, [
                'headers' => $headers,
                'body' => wp_json_encode($requestBody),
                'timeout' => 60
            ]);

            if (is_wp_error($response)) {
                throw new Exception('Pinecone upsert request failed: ' . $response->get_error_message());
            }

            $statusCode = wp_remote_retrieve_response_code($response);
            $responseBody = wp_remote_retrieve_body($response);

            if ($statusCode !== 200) {
                throw new Exception("Pinecone upsert returned status {$statusCode}: {$responseBody}");
            }

            $data = json_decode($responseBody, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON response from Pinecone upsert: ' . json_last_error_msg());
            }

            $this->stats['api_calls']++;

            return ['success' => true, 'upserted_count' => $data['upsertedCount'] ?? count($vectors)];

        } catch (Exception $e) {
            Logger::error('Pinecone vector upsert failed', [
                'error' => $e->getMessage(),
                'vectors_count' => count($vectors)
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Delete vectors from Pinecone
     *
     * @param array $vectorIds Vector IDs to delete
     * @return bool True if successful, false otherwise
     */
    private function deleteVectorsFromPinecone(array $vectorIds): bool
    {
        try {
            if ($this->apiConfig->isDevelopmentMode() && empty($this->apiConfig->getApiKey('pinecone'))) {
                // Development mode without Pinecone - simulate success
                Logger::debug('Simulating Pinecone deletion in development mode', [
                    'vector_ids_count' => count($vectorIds)
                ]);
                return true;
            }

            $endpoint = $this->apiConfig->getApiEndpoint('pinecone', 'delete', [
                'index' => $this->pineconeIndex
            ]);

            $headers = $this->apiConfig->getApiHeaders('pinecone');

            $requestBody = [
                'ids' => $vectorIds
            ];

            $response = wp_remote_request($endpoint, [
                'method' => 'DELETE',
                'headers' => $headers,
                'body' => wp_json_encode($requestBody),
                'timeout' => 30
            ]);

            if (is_wp_error($response)) {
                throw new Exception('Pinecone delete request failed: ' . $response->get_error_message());
            }

            $statusCode = wp_remote_retrieve_response_code($response);

            if ($statusCode !== 200) {
                $responseBody = wp_remote_retrieve_body($response);
                throw new Exception("Pinecone delete returned status {$statusCode}: {$responseBody}");
            }

            $this->stats['api_calls']++;

            return true;

        } catch (Exception $e) {
            Logger::error('Pinecone vector deletion failed', [
                'error' => $e->getMessage(),
                'vector_ids_count' => count($vectorIds)
            ]);
            return false;
        }
    }

    /**
     * Update embeddings in database
     *
     * @param array $chunks Processed chunks
     * @param array $embeddings Generated embeddings
     * @return bool True if successful, false otherwise
     */
    private function updateEmbeddingsInDatabase(array $chunks, array $embeddings): bool
    {
        try {
            $values = [];

            foreach ($chunks as $index => $chunk) {
                $embeddingJson = wp_json_encode($embeddings[$index]);
                $chunkId = $chunk['id'];

                $values[] = $this->wpdb->prepare(
                    "(%d, %s, %s)",
                    $chunkId,
                    $embeddingJson,
                    'text-embedding-3-small'
                );
            }

            if (empty($values)) {
                return false;
            }

            $sql = "INSERT INTO {$this->tableName} (id, embedding, embedding_model) 
                    VALUES " . implode(',', $values) . "
                    ON DUPLICATE KEY UPDATE
                    embedding = VALUES(embedding),
                    embedding_model = VALUES(embedding_model),
                    updated_at = CURRENT_TIMESTAMP";

            $result = $this->wpdb->query($sql);

            if ($result === false) {
                throw new Exception("Database update failed: " . $this->wpdb->last_error);
            }

            Logger::debug('Embeddings updated in database', [
                'chunks_updated' => count($chunks),
                'rows_affected' => $this->wpdb->rows_affected
            ]);

            return true;

        } catch (Exception $e) {
            Logger::error('Failed to update embeddings in database', [
                'chunks_count' => count($chunks),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Clear embeddings in database
     *
     * @param int    $contentId Content identifier
     * @param string $contentType Content type
     * @return bool True if successful, false otherwise
     */
    private function clearEmbeddingsInDatabase($contentId, string $contentType): bool
    {
        try {
            $result = $this->wpdb->update(
                $this->tableName,
                [
                    'embedding' => null,
                    'embedding_model' => null
                ],
                [
                    'content_id' => $contentId,
                    'content_type' => $contentType
                ],
                ['%s', '%s'],
                ['%d', '%s']
            );

            if ($result === false) {
                throw new Exception("Database update failed: " . $this->wpdb->last_error);
            }

            return true;

        } catch (Exception $e) {
            Logger::error('Failed to clear embeddings in database', [
                'content_id' => $contentId,
                'content_type' => $contentType,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get chunk IDs for specific content
     *
     * @param int    $contentId Content identifier
     * @param string $contentType Content type
     * @return array Array of chunk IDs
     */
    private function getChunkIdsForContent($contentId, string $contentType): array
    {
        $results = $this->wpdb->get_col($this->wpdb->prepare(
            "SELECT id FROM {$this->tableName} 
             WHERE content_id = %d AND content_type = %s AND is_active = 1",
            $contentId,
            $contentType
        ));

        return array_map('intval', $results);
    }

    /**
     * Enrich search results with local database data
     *
     * @param array $pineconeResults Pinecone search results
     * @param array $args Search arguments
     * @return array Enriched results
     */
    private function enrichSearchResults(array $pineconeResults, array $args): array
    {
        $startTime = microtime(true);
        $enrichedMatches = [];

        if (empty($pineconeResults['matches'])) {
            return [
                'matches' => [],
                'processing_time' => microtime(true) - $startTime
            ];
        }

        // Get vector IDs from Pinecone results
        $vectorIds = array_map(function ($match) {
            return $this->extractChunkIdFromVectorId($match['id']);
        }, $pineconeResults['matches']);

        // Get local data for these chunks
        $placeholders = str_repeat('%d,', count($vectorIds) - 1) . '%d';
        $localChunks = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT id, content_type, content_id, chunk_text, metadata, chunk_index 
             FROM {$this->tableName} 
             WHERE id IN ({$placeholders}) AND is_active = 1",
            ...$vectorIds
        ), ARRAY_A);

        // Create lookup array
        $chunksById = [];
        foreach ($localChunks as $chunk) {
            $chunksById[$chunk['id']] = $chunk;
        }

        // Enrich Pinecone results with local data
        foreach ($pineconeResults['matches'] as $match) {
            $chunkId = $this->extractChunkIdFromVectorId($match['id']);
            
            if (isset($chunksById[$chunkId])) {
                $chunk = $chunksById[$chunkId];
                
                $enrichedMatch = [
                    'id' => $chunkId,
                    'vector_id' => $match['id'],
                    'score' => $match['score'],
                    'content' => $chunk['chunk_text'],
                    'content_type' => $chunk['content_type'],
                    'content_id' => $chunk['content_id'],
                    'chunk_index' => $chunk['chunk_index']
                ];

                // Include metadata if requested
                if ($args['include_metadata']) {
                    $enrichedMatch['metadata'] = json_decode($chunk['metadata'], true);
                    $enrichedMatch['pinecone_metadata'] = $match['metadata'] ?? [];
                }

                $enrichedMatches[] = $enrichedMatch;
            }
        }

        return [
            'matches' => $enrichedMatches,
            'processing_time' => microtime(true) - $startTime
        ];
    }

    /**
     * Generate vector ID for Pinecone
     *
     * @param array $chunk Chunk data
     * @return string Vector ID
     */
    private function generateVectorId(array $chunk): string
    {
        return "kb_chunk_{$chunk['id']}_{$chunk['content_type']}_{$chunk['content_id']}_{$chunk['chunk_index']}";
    }

    /**
     * Extract chunk ID from vector ID
     *
     * @param string $vectorId Vector ID
     * @return int Chunk ID
     */
    private function extractChunkIdFromVectorId(string $vectorId): int
    {
        $parts = explode('_', $vectorId);
        return isset($parts[2]) ? (int) $parts[2] : 0;
    }

    /**
     * Prepare vector metadata for Pinecone
     *
     * @param array $chunk Chunk data
     * @return array Vector metadata
     */
    private function prepareVectorMetadata(array $chunk): array
    {
        $metadata = json_decode($chunk['metadata'] ?? '{}', true);

        return [
            'content_type' => $chunk['content_type'],
            'content_id' => (int) $chunk['content_id'],
            'chunk_index' => (int) $chunk['chunk_index'],
            'title' => $metadata['title'] ?? '',
            'url' => $metadata['url'] ?? '',
            'language' => $metadata['language'] ?? 'en'
        ];
    }

    /**
     * Generate search cache key
     *
     * @param string $query Search query
     * @param array  $args Search arguments
     * @return string Cache key
     */
    private function generateSearchCacheKey(string $query, array $args): string
    {
        return 'woo_ai_vector_search_' . md5($query . serialize($args));
    }

    /**
     * Generate mock search results for development mode
     *
     * @param int $topK Number of results to generate
     * @return array Mock search results
     */
    private function generateMockSearchResults(int $topK): array
    {
        $mockMatches = [];
        
        for ($i = 1; $i <= min($topK, 5); $i++) {
            $mockMatches[] = [
                'id' => "mock_vector_{$i}",
                'score' => 0.95 - ($i * 0.1),
                'metadata' => [
                    'content_type' => 'product',
                    'content_id' => $i,
                    'chunk_index' => 0,
                    'title' => "Mock Product {$i}",
                    'url' => home_url("/product-{$i}/"),
                    'language' => 'en'
                ]
            ];
        }

        return ['matches' => $mockMatches];
    }

    /**
     * Reset statistics
     */
    private function resetStatistics(): void
    {
        $this->stats = [
            'total_vectors_processed' => 0,
            'successful_upserts' => 0,
            'failed_upserts' => 0,
            'total_queries' => 0,
            'cache_hits' => 0,
            'api_calls' => 0,
            'processing_time' => 0
        ];
    }

    /**
     * Set batch size for vector operations
     *
     * @param int $size Batch size (must be positive and within rate limits)
     * @throws Exception When batch size is invalid
     */
    public function setBatchSize(int $size): void
    {
        if ($size <= 0 || $size > $this->rateLimits['max_vectors_per_request']) {
            throw new Exception("Batch size must be between 1 and {$this->rateLimits['max_vectors_per_request']}");
        }

        $this->batchSize = $size;
        Logger::debug('Vector Manager batch size updated', ['new_size' => $size]);
    }

    /**
     * Set similarity threshold
     *
     * @param float $threshold Similarity threshold (0.0 to 1.0)
     * @throws Exception When threshold is invalid
     */
    public function setSimilarityThreshold(float $threshold): void
    {
        if ($threshold < 0.0 || $threshold > 1.0) {
            throw new Exception('Similarity threshold must be between 0.0 and 1.0');
        }

        $this->similarityThreshold = $threshold;
        Logger::debug('Vector Manager similarity threshold updated', ['new_threshold' => $threshold]);
    }

    /**
     * Set maximum similar results
     *
     * @param int $maxResults Maximum number of results (must be positive)
     * @throws Exception When max results is invalid
     */
    public function setMaxSimilarResults(int $maxResults): void
    {
        if ($maxResults <= 0 || $maxResults > 100) {
            throw new Exception('Maximum similar results must be between 1 and 100');
        }

        $this->maxSimilarResults = $maxResults;
        Logger::debug('Vector Manager max similar results updated', ['new_max' => $maxResults]);
    }
}