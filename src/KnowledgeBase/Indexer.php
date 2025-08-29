<?php

/**
 * Knowledge Base Indexer Class
 *
 * Handles intelligent text chunking, content optimization, and database indexing
 * of scanned content for the AI-powered knowledge base with duplicate detection
 * and batch processing capabilities.
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
 * Class Indexer
 *
 * Comprehensive content indexing system that performs intelligent text chunking,
 * duplicate detection, content optimization for AI consumption, and efficient
 * database storage with full metadata support and batch processing capabilities.
 *
 * @since 1.0.0
 */
class Indexer
{
    use Singleton;

    /**
     * Default chunk size in characters for text splitting
     *
     * @since 1.0.0
     * @var int
     */
    private const DEFAULT_CHUNK_SIZE = 1000;

    /**
     * Maximum overlap between chunks in characters
     *
     * @since 1.0.0
     * @var int
     */
    private const DEFAULT_CHUNK_OVERLAP = 200;

    /**
     * Minimum meaningful chunk size to prevent tiny chunks
     *
     * @since 1.0.0
     * @var int
     */
    private const MIN_CHUNK_SIZE = 100;

    /**
     * Maximum chunk size to prevent oversized chunks
     *
     * @since 1.0.0
     * @var int
     */
    private const MAX_CHUNK_SIZE = 2000;

    /**
     * Default batch size for processing large datasets
     *
     * @since 1.0.0
     * @var int
     */
    private const DEFAULT_BATCH_SIZE = 100;

    /**
     * Cache group for indexer operations
     *
     * @since 1.0.0
     * @var string
     */
    private const CACHE_GROUP = 'woo_ai_indexer';

    /**
     * Cache TTL for indexer operations (2 hours)
     *
     * @since 1.0.0
     * @var int
     */
    private const CACHE_TTL = 7200;

    /**
     * Sentence boundary markers for intelligent splitting
     *
     * @since 1.0.0
     * @var array
     */
    private array $sentenceMarkers = ['.', '!', '?', ':', ';'];

    /**
     * Word boundary markers for chunk splitting
     *
     * @since 1.0.0
     * @var array
     */
    private array $wordBoundaries = [' ', "\n", "\t", "\r"];

    /**
     * Stop words to remove during content optimization
     *
     * @since 1.0.0
     * @var array
     */
    private array $stopWords = [
        'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from',
        'has', 'he', 'in', 'is', 'it', 'its', 'of', 'on', 'that', 'the',
        'to', 'was', 'were', 'will', 'with', 'the', 'this', 'but', 'they',
        'have', 'had', 'what', 'said', 'each', 'which', 'their', 'time',
        'if', 'up', 'out', 'many', 'then', 'them', 'these', 'so', 'some'
    ];

    /**
     * Current indexing statistics
     *
     * @since 1.0.0
     * @var array
     */
    private array $indexingStats = [];

    /**
     * Content hash cache for duplicate detection
     *
     * @since 1.0.0
     * @var array
     */
    private array $hashCache = [];

    /**
     * Database connection reference
     *
     * @since 1.0.0
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    private function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->initializeIndexer();
    }

    /**
     * Initialize indexer settings and hooks
     *
     * @since 1.0.0
     * @return void
     */
    private function initializeIndexer(): void
    {
        // Hook into content updates for automatic reindexing
        add_action('woo_ai_assistant_content_updated', [$this, 'onContentUpdated'], 10, 2);
        add_action('woo_ai_assistant_bulk_reindex', [$this, 'processBulkReindex'], 10, 1);

        // Initialize statistics
        $this->resetStats();

        Utils::logDebug('Indexer initialized with content update hooks');
    }

    /**
     * Index content for knowledge base (wrapper method for CronManager compatibility)
     *
     * This method provides a simplified interface for indexing content, primarily
     * used by the CronManager for automated processing tasks. It wraps the more
     * comprehensive processContent method with sensible defaults.
     *
     * @since 1.0.0
     * @param array $contentData Array of content items to index.
     * @param array $options Optional. Processing options.
     * @param bool  $options['update_existing'] Whether to update existing content. Default false.
     *
     * @return void
     *
     * @throws \InvalidArgumentException When content data format is invalid.
     * @throws \RuntimeException When indexing operations fail.
     *
     * @example
     * ```php
     * $indexer = Indexer::getInstance();
     * $indexer->indexContent($products, ['update_existing' => true]);
     * ```
     */
    public function indexContent(array $contentData, array $options = []): array
    {
        if (empty($contentData)) {
            Utils::logDebug('Empty content data provided to indexContent, skipping');
            return [
                'total_processed' => 0,
                'batches_processed' => 0,
                'errors' => [],
                'success' => true
            ];
        }

        // Set default options for cron processing
        $defaultOptions = [
            'chunk_size' => self::DEFAULT_CHUNK_SIZE,
            'overlap' => self::DEFAULT_CHUNK_OVERLAP,
            'optimize_for_ai' => true,
            'remove_duplicates' => true,
            'preserve_sentences' => true,
            'batch_size' => self::DEFAULT_BATCH_SIZE,
            'force_update' => $options['update_existing'] ?? false
        ];

        $processOptions = wp_parse_args($options, $defaultOptions);

        try {
            Utils::logDebug('Starting content indexing via indexContent method - items: ' . count($contentData));

            $result = $this->processContent($contentData, $processOptions);

            Utils::logDebug('Content indexing completed successfully', [
                'total_processed' => $result['total_processed'],
                'chunks_created' => $result['chunks_created'],
                'duplicates_found' => $result['duplicates_found'],
                'errors' => $result['errors']
            ]);

            // Log any errors for monitoring
            if ($result['errors'] > 0) {
                Utils::logError('Content indexing completed with errors: ' . $result['errors'] . ' out of ' . count($contentData) . ' items failed');
            }

            return $result;
        } catch (\Exception $e) {
            Utils::logError('Content indexing failed: ' . $e->getMessage());
            throw new \RuntimeException('Failed to index content: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Remove content from knowledge base by type or ID
     *
     * This method allows removal of specific content from the knowledge base,
     * typically used during reindexing operations or when content is deleted.
     *
     * @since 1.0.0
     * @param string $contentType Type of content to remove (e.g., 'woo_settings', 'product', 'page').
     * @param int|null $sourceId Optional. Specific source ID to remove. If null, removes all of the type.
     *
     * @return int Number of items removed.
     *
     * @throws \RuntimeException When database operations fail.
     */
    public function removeContent(string $contentType, ?int $sourceId = null): int
    {
        $tableName = $this->wpdb->prefix . 'woo_ai_knowledge_base';

        if ($sourceId !== null) {
            // Remove specific item
            $result = $this->wpdb->delete(
                $tableName,
                [
                    'source_type' => $contentType,
                    'source_id' => $sourceId
                ],
                ['%s', '%d']
            );
        } else {
            // Remove all items of this type
            $result = $this->wpdb->delete(
                $tableName,
                ['source_type' => $contentType],
                ['%s']
            );
        }

        if ($result === false) {
            throw new \RuntimeException('Failed to remove content from knowledge base: ' . $this->wpdb->last_error);
        }

        $removedCount = $result;
        Utils::logDebug("Removed {$removedCount} items from knowledge base", [
            'content_type' => $contentType,
            'source_id' => $sourceId
        ]);

        return $removedCount;
    }

    /**
     * Process content from Scanner and create optimized chunks for knowledge base
     *
     * This method takes raw content data from the Scanner, applies intelligent
     * text chunking with sentence boundary awareness, performs duplicate detection,
     * optimizes content for AI consumption, and stores chunks in the database
     * with comprehensive metadata.
     *
     * @since 1.0.0
     * @param array $contentData Array of content items from Scanner.
     * @param array $options Optional. Processing options and configuration.
     * @param int   $options['chunk_size'] Size of text chunks in characters. Default 1000.
     * @param int   $options['overlap'] Overlap between chunks in characters. Default 200.
     * @param bool  $options['optimize_for_ai'] Whether to optimize content for AI. Default true.
     * @param bool  $options['remove_duplicates'] Whether to detect and remove duplicates. Default true.
     * @param bool  $options['preserve_sentences'] Whether to preserve sentence boundaries. Default true.
     * @param int   $options['batch_size'] Number of items to process per batch. Default 100.
     * @param bool  $options['force_update'] Whether to force reindex existing content. Default false.
     *
     * @return array Processing results with statistics and status information.
     *               Contains 'total_processed', 'chunks_created', 'duplicates_found', 'errors'.
     *
     * @throws \InvalidArgumentException When content data format is invalid.
     * @throws \RuntimeException When database operations fail.
     *
     * @example
     * ```php
     * $indexer = Indexer::getInstance();
     * $scanner = Scanner::getInstance();
     * $products = $scanner->scanProducts(['limit' => 50]);
     *
     * $result = $indexer->processContent($products, [
     *     'chunk_size' => 800,
     *     'optimize_for_ai' => true,
     *     'remove_duplicates' => true
     * ]);
     *
     * echo "Processed: {$result['total_processed']} items";
     * echo "Created: {$result['chunks_created']} chunks";
     * ```
     */
    public function processContent(array $contentData, array $options = []): array
    {
        // Validate input data
        if (empty($contentData)) {
            throw new \InvalidArgumentException('Content data cannot be empty');
        }

        // Parse and validate options
        $options = wp_parse_args($options, [
            'chunk_size' => self::DEFAULT_CHUNK_SIZE,
            'overlap' => self::DEFAULT_CHUNK_OVERLAP,
            'optimize_for_ai' => true,
            'remove_duplicates' => true,
            'preserve_sentences' => true,
            'batch_size' => self::DEFAULT_BATCH_SIZE,
            'force_update' => false
        ]);

        // Validate chunk size
        if ($options['chunk_size'] < self::MIN_CHUNK_SIZE || $options['chunk_size'] > self::MAX_CHUNK_SIZE) {
            throw new \InvalidArgumentException(
                "Chunk size must be between " . self::MIN_CHUNK_SIZE . " and " . self::MAX_CHUNK_SIZE
            );
        }

        Utils::logDebug('Starting content processing - items: ' . count($contentData) . ', chunk_size: ' . $options['chunk_size']);

        $this->resetStats();
        $this->indexingStats['start_time'] = microtime(true);

        $results = [
            'total_processed' => 0,
            'chunks_created' => 0,
            'duplicates_found' => 0,
            'errors' => 0,
            'batches_processed' => 0,
            'processing_time' => 0
        ];

        try {
            // Process content in batches with safety limits
            $batches = array_chunk($contentData, $options['batch_size']);
            $maxBatches = min(count($batches), 100); // Limit to 100 batches maximum
            $totalStartTime = microtime(true);
            $maxTotalTime = 180; // Maximum 3 minutes total processing time

            Utils::logDebug("Processing {$maxBatches} batches with safety limits (max time: {$maxTotalTime}s)");

            foreach ($batches as $batchIndex => $batch) {
                // EMERGENCY FIX: Global timeout check
                if ((microtime(true) - $totalStartTime) > $maxTotalTime) {
                    Utils::logError("Global processing timeout reached after {$maxTotalTime} seconds - stopping to prevent crash");
                    $results['timeout_reached'] = true;
                    break;
                }

                // EMERGENCY FIX: Limit number of batches processed
                if ($batchIndex >= $maxBatches) {
                    Utils::logError("Maximum batch limit reached ({$maxBatches}) - stopping to prevent infinite processing");
                    $results['max_batches_reached'] = true;
                    break;
                }

                $batchResult = $this->processBatch($batch, $options, $batchIndex);

                // Aggregate results
                $results['total_processed'] += $batchResult['processed'];
                $results['chunks_created'] += $batchResult['chunks_created'];
                $results['duplicates_found'] += $batchResult['duplicates_found'];
                $results['errors'] += $batchResult['errors'];
                $results['batches_processed']++;

                // Check for batch-level errors and timeouts
                if (isset($batchResult['timeout_reached']) && $batchResult['timeout_reached']) {
                    Utils::logError("Batch timeout detected - stopping further processing");
                    break;
                }

                // Memory management - clear processed data
                unset($batch);

                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }

                // Small delay to prevent overwhelming the system
                if ($batchIndex % 10 === 0) {
                    usleep(100000); // 0.1 second delay every 10 batches
                }
            }
        } catch (\Exception $e) {
            Utils::logDebug('Content processing failed - error: ' . $e->getMessage() . ', processed: ' . $results['total_processed'], 'error');
            throw new \RuntimeException('Content processing failed: ' . $e->getMessage(), 0, $e);
        }

        // Calculate final statistics
        $this->indexingStats['end_time'] = microtime(true);
        $results['processing_time'] = $this->indexingStats['end_time'] - $this->indexingStats['start_time'];
        $this->indexingStats['results'] = $results;

        Utils::logDebug('Content processing completed - processed: ' . $results['total_processed'] . ', chunks: ' . $results['chunks_created']);

        return $results;
    }

    /**
     * Create intelligent text chunks with sentence boundary preservation
     *
     * Implements advanced chunking algorithm that respects sentence boundaries,
     * maintains semantic coherence, and provides configurable overlap for
     * better context preservation in AI retrieval.
     *
     * @since 1.0.0
     * @param string $content The raw content to chunk.
     * @param int    $chunkSize Target size for each chunk in characters.
     * @param int    $overlap Number of characters to overlap between chunks.
     * @param bool   $preserveSentences Whether to preserve sentence boundaries.
     *
     * @return array Array of chunks with metadata.
     *               Each chunk contains 'content', 'index', 'start_pos', 'end_pos', 'word_count'.
     *
     * @throws \InvalidArgumentException When content is empty or parameters are invalid.
     */
    public function createChunks(
        string $content,
        int $chunkSize = self::DEFAULT_CHUNK_SIZE,
        int $overlap = self::DEFAULT_CHUNK_OVERLAP,
        bool $preserveSentences = true
    ): array {
        if (empty(trim($content))) {
            throw new \InvalidArgumentException('Content cannot be empty');
        }

        if ($chunkSize < self::MIN_CHUNK_SIZE || $chunkSize > self::MAX_CHUNK_SIZE) {
            throw new \InvalidArgumentException('Invalid chunk size');
        }

        Utils::logDebug('Creating chunks - content length: ' . strlen($content) . ', chunk size: ' . $chunkSize);

        // Clean and normalize content
        $content = $this->normalizeContent($content);
        $contentLength = strlen($content);

        // If content is smaller than chunk size, return single chunk
        if ($contentLength <= $chunkSize) {
            return [[
                'content' => $content,
                'index' => 0,
                'start_pos' => 0,
                'end_pos' => $contentLength,
                'word_count' => str_word_count($content),
                'sentence_count' => $this->countSentences($content)
            ]];
        }

        $chunks = [];
        $chunkIndex = 0;
        $position = 0;

        // EMERGENCY FIX: Add safety limits to prevent infinite loops
        $maxChunks = 1000; // Maximum number of chunks per content
        $maxIterations = 2000; // Maximum iterations to prevent infinite loops
        $iterationCount = 0;
        $startTime = microtime(true);
        $maxTime = 15; // Maximum 15 seconds for chunk creation

        while ($position < $contentLength && $chunkIndex < $maxChunks && $iterationCount < $maxIterations) {
            $iterationCount++;

            // EMERGENCY FIX: Timeout check
            if ((microtime(true) - $startTime) > $maxTime) {
                Utils::logError("Chunk creation timeout reached after {$maxTime} seconds - stopping to prevent crash");
                break;
            }

            // Calculate chunk boundaries
            $endPosition = min($position + $chunkSize, $contentLength);

            // Extract chunk content
            $chunkContent = substr($content, $position, $endPosition - $position);

            // Preserve sentence boundaries if requested
            if ($preserveSentences && $endPosition < $contentLength) {
                $chunkContent = $this->adjustChunkBoundaries($chunkContent, $content, $position, $chunkSize);
                $endPosition = $position + strlen($chunkContent);
            }

            // Skip if chunk is too small
            if (strlen(trim($chunkContent)) < self::MIN_CHUNK_SIZE) {
                $position = $endPosition;
                continue;
            }

            $chunks[] = [
                'content' => trim($chunkContent),
                'index' => $chunkIndex,
                'start_pos' => $position,
                'end_pos' => $endPosition,
                'word_count' => str_word_count($chunkContent),
                'sentence_count' => $this->countSentences($chunkContent)
            ];

            $chunkIndex++;

            // Calculate next position with overlap
            $nextPosition = $endPosition - $overlap;

            // EMERGENCY FIX: Ensure position always advances to prevent infinite loops
            if ($nextPosition <= $position) {
                $nextPosition = $position + max(1, $chunkSize / 10); // Force advancement
            }

            $position = $nextPosition;
        }

        // Log if we hit safety limits
        if ($chunkIndex >= $maxChunks) {
            Utils::logError("Maximum chunk limit reached ({$maxChunks}) - content may be truncated");
        }
        if ($iterationCount >= $maxIterations) {
            Utils::logError("Maximum iterations reached ({$maxIterations}) - stopping to prevent infinite loop");
        }

        Utils::logDebug('Chunks created successfully - total: ' . count($chunks));

        return $chunks;
    }

    /**
     * Store chunks in database with comprehensive metadata and indexing
     *
     * @since 1.0.0
     * @param array  $chunks Array of text chunks to store.
     * @param array  $sourceData Original content metadata from Scanner.
     * @param array  $options Processing options used during chunking.
     * @param string $contentHash Hash of the original content for duplicate detection.
     *
     * @return array Storage results with statistics.
     *
     * @throws \RuntimeException When database operations fail.
     */
    public function storeChunks(array $chunks, array $sourceData, array $options = [], string $contentHash = ''): array
    {
        if (empty($chunks)) {
            return ['stored' => 0, 'skipped' => 0, 'errors' => 0];
        }

        if (!$contentHash) {
            $contentHash = $this->generateContentHash($sourceData['content'] ?? '');
        }

        Utils::logDebug('Storing chunks in database - count: ' . count($chunks) . ', type: ' . ($sourceData['type'] ?? 'unknown'));

        $results = ['stored' => 0, 'skipped' => 0, 'errors' => 0];
        $tableName = $this->wpdb->prefix . 'woo_ai_knowledge_base';

        // Check for existing content hash to prevent duplicates
        if ($this->isDuplicateContent($contentHash, (int)($sourceData['id'] ?? 0))) {
            Utils::logDebug('Duplicate content detected, skipping storage for hash: ' . $contentHash);
            $results['skipped'] = count($chunks);
            return $results;
        }

        // Begin transaction for atomic operation
        $this->wpdb->query('START TRANSACTION');

        try {
            foreach ($chunks as $chunk) {
                // Prepare chunk metadata
                $metadata = wp_json_encode([
                    'source_metadata' => $sourceData['metadata'] ?? [],
                    'chunk_stats' => [
                        'word_count' => $chunk['word_count'],
                        'sentence_count' => $chunk['sentence_count'],
                        'start_pos' => $chunk['start_pos'],
                        'end_pos' => $chunk['end_pos']
                    ],
                    'processing_options' => $options,
                    'indexed_at' => current_time('mysql'),
                    'content_version' => '1.0'
                ]);

                // Insert chunk into database
                $inserted = $this->wpdb->insert(
                    $tableName,
                    [
                        'source_type' => $sourceData['type'] ?? 'unknown',
                        'source_id' => absint($sourceData['id'] ?? 0),
                        'title' => $this->sanitizeTitle($sourceData['title'] ?? ''),
                        'content' => $sourceData['content'] ?? '',
                        'chunk_content' => $chunk['content'],
                        'chunk_index' => $chunk['index'],
                        'embedding' => null, // Will be populated by VectorManager
                        'metadata' => $metadata,
                        'hash' => $contentHash,
                        'indexed_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    ],
                    [
                        '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s'
                    ]
                );

                if ($inserted === false) {
                    throw new \RuntimeException(
                        'Failed to insert chunk: ' . $this->wpdb->last_error
                    );
                }

                $results['stored']++;
            }

            // Commit transaction
            $this->wpdb->query('COMMIT');

            Utils::logDebug('Chunks stored successfully', $results);
        } catch (\Exception $e) {
            // Rollback transaction on error
            $this->wpdb->query('ROLLBACK');

            Utils::logDebug('Chunk storage failed - error: ' . $e->getMessage() . ', hash: ' . $contentHash, 'error');

            $results['errors'] = count($chunks);
            throw new \RuntimeException('Chunk storage failed: ' . $e->getMessage(), 0, $e);
        }

        return $results;
    }

    /**
     * Detect and remove duplicate content using content hashing
     *
     * @since 1.0.0
     * @param array $contentItems Array of content items to deduplicate.
     * @param bool  $removeFromDb Whether to remove duplicates from database.
     *
     * @return array Deduplication results with statistics.
     */
    public function removeDuplicates(array $contentItems, bool $removeFromDb = false): array
    {
        Utils::logDebug('Starting duplicate detection - items: ' . count($contentItems));

        $results = [
            'original_count' => count($contentItems),
            'duplicates_found' => 0,
            'unique_items' => [],
            'duplicate_hashes' => [],
            'removed_from_db' => 0
        ];

        $seenHashes = [];

        foreach ($contentItems as $item) {
            $content = $item['content'] ?? '';
            $contentHash = $this->generateContentHash($content);

            if (isset($seenHashes[$contentHash])) {
                // Duplicate found
                $results['duplicates_found']++;
                $results['duplicate_hashes'][] = $contentHash;

                if ($removeFromDb) {
                    $removed = $this->removeDuplicateFromDatabase($contentHash, $item['id'] ?? 0);
                    $results['removed_from_db'] += $removed;
                }
            } else {
                // Unique content
                $seenHashes[$contentHash] = true;
                $item['_content_hash'] = $contentHash;
                $results['unique_items'][] = $item;
            }
        }

        Utils::logDebug('Duplicate detection completed - duplicates: ' . $results['duplicates_found'] . ', unique: ' . count($results['unique_items']));

        return $results;
    }

    /**
     * Optimize content specifically for AI consumption and retrieval
     *
     * @since 1.0.0
     * @param string $content Raw content to optimize.
     * @param array  $options Optimization options.
     * @param bool   $options['remove_stop_words'] Whether to remove stop words. Default false.
     * @param bool   $options['enhance_keywords'] Whether to enhance important keywords. Default true.
     * @param bool   $options['normalize_whitespace'] Whether to normalize whitespace. Default true.
     * @param bool   $options['preserve_structure'] Whether to preserve content structure. Default true.
     *
     * @return string Optimized content ready for AI processing.
     */
    public function optimizeForAi(string $content, array $options = []): string
    {
        $options = wp_parse_args($options, [
            'remove_stop_words' => false,
            'enhance_keywords' => true,
            'normalize_whitespace' => true,
            'preserve_structure' => true
        ]);

        // Normalize whitespace
        if ($options['normalize_whitespace']) {
            $content = $this->normalizeContent($content);
        }

        // Remove excessive punctuation while preserving structure
        if ($options['preserve_structure']) {
            $content = preg_replace('/([.!?]){2,}/', '$1', $content);
            $content = preg_replace('/\s*([,;:])\s*/', '$1 ', $content);
        }

        // Enhance keywords (for e-commerce context)
        if ($options['enhance_keywords']) {
            $content = $this->enhanceKeywords($content);
        }

        // Remove stop words (optional, as it may hurt context)
        if ($options['remove_stop_words']) {
            $content = $this->removeStopWords($content);
        }

        return trim($content);
    }

    /**
     * Process content batch for large-scale operations with safety mechanisms
     *
     * @since 1.0.0
     * @param array $batch Array of content items to process in this batch.
     * @param array $options Processing options.
     * @param int   $batchIndex Current batch index for logging.
     *
     * @return array Batch processing results.
     */
    private function processBatch(array $batch, array $options, int $batchIndex): array
    {
        // EMERGENCY FIX: Add timeout and safety mechanisms to prevent infinite loops
        $batchStartTime = microtime(true);
        $maxExecutionTime = 20; // Maximum 20 seconds per batch
        $maxIterations = 500; // Maximum iterations per batch
        $currentIteration = 0;

        Utils::logDebug('Processing batch ' . $batchIndex . ' - items: ' . count($batch) . ' (with safety limits)');

        $batchResults = [
            'processed' => 0,
            'chunks_created' => 0,
            'duplicates_found' => 0,
            'errors' => 0,
            'timeout_reached' => false,
            'max_iterations_reached' => false
        ];

        foreach ($batch as $item) {
            // EMERGENCY FIX: Safety check for timeout and max iterations
            $currentIteration++;
            $currentTime = microtime(true);

            if (($currentTime - $batchStartTime) > $maxExecutionTime) {
                Utils::logError("Batch processing timeout reached after {$maxExecutionTime} seconds - stopping to prevent crash");
                $batchResults['timeout_reached'] = true;
                break;
            }

            if ($currentIteration > $maxIterations) {
                Utils::logError("Maximum iterations ({$maxIterations}) reached - stopping to prevent infinite loop");
                $batchResults['max_iterations_reached'] = true;
                break;
            }

            try {
                // Validate item structure
                if (!isset($item['content']) || empty($item['content'])) {
                    $batchResults['errors']++;
                    continue;
                }

                // Generate content hash for duplicate detection
                $contentHash = $this->generateContentHash($item['content']);

                // Check for duplicates if requested
                if ($options['remove_duplicates'] && $this->isDuplicateContent($contentHash, (int)($item['id'] ?? 0))) {
                    $batchResults['duplicates_found']++;
                    continue;
                }

                // Optimize content for AI if requested
                $content = $item['content'];
                if ($options['optimize_for_ai']) {
                    $content = $this->optimizeForAi($content, $options);
                }

                // Create chunks with additional safety check
                if (strlen($content) > 50000) { // Skip extremely large content
                    Utils::logError("Content too large (" . strlen($content) . " chars) - skipping to prevent memory issues");
                    $batchResults['errors']++;
                    continue;
                }

                // Create chunks
                $chunks = $this->createChunks(
                    $content,
                    $options['chunk_size'],
                    $options['overlap'],
                    $options['preserve_sentences']
                );

                // Store chunks in database
                $storeResult = $this->storeChunks($chunks, $item, $options, $contentHash);
                $batchResults['chunks_created'] += $storeResult['stored'];

                if ($storeResult['errors'] > 0) {
                    $batchResults['errors'] += $storeResult['errors'];
                } else {
                    $batchResults['processed']++;
                }

                // Memory cleanup every 50 items
                if ($currentIteration % 50 === 0 && function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            } catch (\Exception $e) {
                Utils::logError('Error processing batch item - id: ' . ($item['id'] ?? 'unknown') . ', error: ' . $e->getMessage());
                $batchResults['errors']++;
            }
        }

        $processingTime = microtime(true) - $batchStartTime;
        Utils::logDebug("Batch {$batchIndex} completed - processed: {$batchResults['processed']}, errors: {$batchResults['errors']}, time: {$processingTime}s");

        return $batchResults;
    }

    /**
     * Adjust chunk boundaries to preserve sentence integrity
     *
     * @since 1.0.0
     * @param string $chunkContent Current chunk content.
     * @param string $fullContent Full content being chunked.
     * @param int    $position Current position in full content.
     * @param int    $chunkSize Target chunk size.
     *
     * @return string Adjusted chunk content with preserved sentences.
     */
    private function adjustChunkBoundaries(
        string $chunkContent,
        string $fullContent,
        int $position,
        int $chunkSize
    ): string {
        $chunkLength = strlen($chunkContent);

        // Find the last sentence boundary within the chunk
        $lastSentenceEnd = 0;
        foreach ($this->sentenceMarkers as $marker) {
            $pos = strrpos($chunkContent, $marker);
            if ($pos !== false && $pos > $lastSentenceEnd) {
                $lastSentenceEnd = $pos + 1;
            }
        }

        // If we found a good sentence boundary, use it
        if ($lastSentenceEnd > strlen($chunkContent) * 0.5) {
            return substr($chunkContent, 0, $lastSentenceEnd);
        }

        // Otherwise, find the last word boundary
        foreach ($this->wordBoundaries as $boundary) {
            $pos = strrpos($chunkContent, $boundary);
            if ($pos !== false && $pos > strlen($chunkContent) * 0.8) {
                return substr($chunkContent, 0, $pos);
            }
        }

        // Fallback to original chunk if no good boundary found
        return $chunkContent;
    }

    /**
     * Normalize content for consistent processing
     *
     * @since 1.0.0
     * @param string $content Raw content to normalize.
     *
     * @return string Normalized content.
     */
    private function normalizeContent(string $content): string
    {
        // Remove HTML tags if any remain
        $content = strip_tags($content);

        // Decode HTML entities
        $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');

        // Normalize whitespace
        $content = preg_replace('/\s+/', ' ', $content);

        // Remove excessive punctuation
        $content = preg_replace('/([.!?]){3,}/', '$1$1', $content);

        return trim($content);
    }

    /**
     * Count sentences in text content
     *
     * @since 1.0.0
     * @param string $content Content to count sentences in.
     *
     * @return int Number of sentences found.
     */
    private function countSentences(string $content): int
    {
        $count = 0;
        foreach ($this->sentenceMarkers as $marker) {
            $count += substr_count($content, $marker);
        }
        return max(1, $count); // At least 1 sentence
    }

    /**
     * Generate content hash for duplicate detection
     *
     * @since 1.0.0
     * @param string $content Content to hash.
     *
     * @return string SHA-256 hash of normalized content.
     */
    private function generateContentHash(string $content): string
    {
        // Normalize content for consistent hashing
        $normalizedContent = strtolower(trim($this->normalizeContent($content)));
        return hash('sha256', $normalizedContent);
    }

    /**
     * Check if content is duplicate based on hash
     *
     * @since 1.0.0
     * @param string $contentHash Hash of content to check.
     * @param int    $sourceId Source ID to exclude from duplicate check.
     *
     * @return bool True if content is duplicate.
     */
    private function isDuplicateContent(string $contentHash, int $sourceId = 0): bool
    {
        // Check cache first
        $cacheKey = "duplicate_{$contentHash}";
        $cached = wp_cache_get($cacheKey, self::CACHE_GROUP);
        if ($cached !== false) {
            return $cached;
        }

        $tableName = $this->wpdb->prefix . 'woo_ai_knowledge_base';

        $query = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$tableName} WHERE hash = %s AND source_id != %d",
            $contentHash,
            $sourceId
        );

        $count = $this->wpdb->get_var($query);
        $isDuplicate = $count > 0;

        // Cache result
        wp_cache_set($cacheKey, $isDuplicate, self::CACHE_GROUP, self::CACHE_TTL);

        return $isDuplicate;
    }

    /**
     * Remove duplicate content from database
     *
     * @since 1.0.0
     * @param string $contentHash Hash of content to remove.
     * @param int    $excludeId ID to exclude from removal.
     *
     * @return int Number of records removed.
     */
    private function removeDuplicateFromDatabase(string $contentHash, int $excludeId = 0): int
    {
        $tableName = $this->wpdb->prefix . 'woo_ai_knowledge_base';

        $result = $this->wpdb->delete(
            $tableName,
            [
                'hash' => $contentHash,
                'source_id' => $excludeId
            ],
            ['%s', '%d']
        );

        return $result !== false ? $result : 0;
    }

    /**
     * Enhance keywords for better AI understanding
     *
     * @since 1.0.0
     * @param string $content Content to enhance.
     *
     * @return string Content with enhanced keywords.
     */
    private function enhanceKeywords(string $content): string
    {
        // Define important e-commerce terms that should be emphasized
        $importantTerms = [
            'price' => 'product price',
            'shipping' => 'shipping and delivery',
            'return' => 'return policy',
            'warranty' => 'product warranty',
            'size' => 'product size',
            'color' => 'product color',
            'stock' => 'product availability',
            'discount' => 'discount and offers',
            'payment' => 'payment methods'
        ];

        foreach ($importantTerms as $term => $replacement) {
            $content = preg_replace(
                '/\b' . preg_quote($term, '/') . '\b/i',
                $replacement,
                $content
            );
        }

        return $content;
    }

    /**
     * Remove stop words from content (optional optimization)
     *
     * @since 1.0.0
     * @param string $content Content to process.
     *
     * @return string Content with stop words removed.
     */
    private function removeStopWords(string $content): string
    {
        $words = explode(' ', strtolower($content));
        $filteredWords = array_filter($words, function ($word) {
            return !in_array(trim($word), $this->stopWords, true);
        });

        return implode(' ', $filteredWords);
    }

    /**
     * Sanitize title for database storage
     *
     * @since 1.0.0
     * @param string $title Raw title to sanitize.
     *
     * @return string Sanitized title.
     */
    private function sanitizeTitle(string $title): string
    {
        return sanitize_text_field(wp_trim_words($title, 20));
    }

    /**
     * Reset indexing statistics
     *
     * @since 1.0.0
     * @return void
     */
    private function resetStats(): void
    {
        $this->indexingStats = [
            'start_time' => 0,
            'end_time' => 0,
            'results' => []
        ];
    }

    /**
     * Handle content update hook
     *
     * @since 1.0.0
     * @param string $contentType Type of content updated.
     * @param int    $contentId   ID of updated content.
     *
     * @return void
     */
    public function onContentUpdated(string $contentType, int $contentId): void
    {
        Utils::logDebug('Content updated, triggering reindex - type: ' . $contentType . ', id: ' . $contentId);

        // Clear related cache
        wp_cache_delete("duplicate_check_{$contentId}", self::CACHE_GROUP);

        // Trigger background reindexing if needed
        wp_schedule_single_event(time() + 60, 'woo_ai_assistant_reindex_content', [
            $contentType,
            $contentId
        ]);
    }

    /**
     * Process bulk reindexing operation
     *
     * @since 1.0.0
     * @param array $options Reindexing options.
     *
     * @return void
     */
    public function processBulkReindex(array $options = []): void
    {
        Utils::logDebug('Starting bulk reindex operation');

        try {
            // Initialize Scanner to get fresh content
            $scanner = Scanner::getInstance();

            // Get content based on options
            $contentTypes = $options['content_types'] ?? ['products', 'pages'];

            foreach ($contentTypes as $contentType) {
                switch ($contentType) {
                    case 'products':
                        $content = $scanner->scanProducts(['force_update' => true]);
                        break;
                    case 'pages':
                        $content = $scanner->scanPages(['force_update' => true]);
                        break;
                    default:
                        continue 2;
                }

                if (!empty($content)) {
                    $this->processContent($content, $options);
                }
            }
        } catch (\Exception $e) {
            Utils::logDebug('Bulk reindex failed - error: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * Get indexing statistics from last operation
     *
     * @since 1.0.0
     * @return array Current indexing statistics.
     */
    public function getIndexingStats(): array
    {
        return $this->indexingStats;
    }

    /**
     * Clear indexer cache
     *
     * @since 1.0.0
     * @param string|null $cacheKey Specific cache key or null for all.
     *
     * @return bool True on success.
     */
    public function clearCache(?string $cacheKey = null): bool
    {
        if ($cacheKey) {
            return wp_cache_delete($cacheKey, self::CACHE_GROUP);
        }

        return wp_cache_flush_group(self::CACHE_GROUP);
    }

    /**
     * Get chunk statistics from database
     *
     * @since 1.0.0
     * @param string|null $sourceType Filter by source type or null for all.
     *
     * @return array Statistics about stored chunks.
     */
    public function getChunkStats(?string $sourceType = null): array
    {
        $tableName = $this->wpdb->prefix . 'woo_ai_knowledge_base';

        $whereClause = '';
        $params = [];

        if ($sourceType) {
            $whereClause = 'WHERE source_type = %s';
            $params[] = $sourceType;
        }

        $query = "SELECT 
                    source_type,
                    COUNT(*) as total_chunks,
                    AVG(LENGTH(chunk_content)) as avg_chunk_size,
                    MIN(LENGTH(chunk_content)) as min_chunk_size,
                    MAX(LENGTH(chunk_content)) as max_chunk_size,
                    COUNT(DISTINCT source_id) as unique_sources
                  FROM {$tableName} 
                  {$whereClause}
                  GROUP BY source_type";

        if (!empty($params)) {
            $query = $this->wpdb->prepare($query, ...$params);
        }

        return $this->wpdb->get_results($query, ARRAY_A) ?: [];
    }
}
