<?php

/**
 * Embedding Generator Class
 *
 * Generates embeddings via OpenAI text-embedding-3-small model with comprehensive
 * batch processing, caching, error handling, and fallback mechanisms. Optimized
 * for efficiency with rate limiting and cost management.
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
 * Class EmbeddingGenerator
 *
 * High-performance embedding generation with OpenAI integration.
 *
 * @since 1.0.0
 */
class EmbeddingGenerator
{
    use Singleton;

    /**
     * API configuration instance
     *
     * @var ApiConfiguration
     */
    private ApiConfiguration $apiConfig;

    /**
     * Default embedding model
     *
     * @var string
     */
    private string $embeddingModel = 'text-embedding-3-small';

    /**
     * Model dimensions (for text-embedding-3-small)
     *
     * @var int
     */
    private int $modelDimensions = 1536;

    /**
     * Maximum batch size for embedding requests
     *
     * @var int
     */
    private int $maxBatchSize = 100;

    /**
     * Cache TTL for embeddings (in seconds)
     *
     * @var int
     */
    private int $cacheTtl = 86400; // 24 hours

    /**
     * Rate limiting settings
     *
     * @var array
     */
    private array $rateLimits = [
        'max_requests_per_minute' => 3000,
        'max_tokens_per_minute' => 1000000,
        'retry_delays' => [1, 2, 4, 8, 16] // exponential backoff in seconds
    ];

    /**
     * Request timeout in seconds
     *
     * @var int
     */
    private int $requestTimeout = 30;

    /**
     * Embedding generation statistics
     *
     * @var array
     */
    private array $stats = [
        'total_texts_processed' => 0,
        'total_embeddings_generated' => 0,
        'cache_hits' => 0,
        'cache_misses' => 0,
        'api_requests' => 0,
        'failed_requests' => 0,
        'total_tokens_used' => 0,
        'processing_time' => 0,
        'average_request_time' => 0
    ];

    /**
     * Fallback embedding for error cases
     *
     * @var array|null
     */
    private ?array $fallbackEmbedding = null;

    /**
     * Initialize the embedding generator
     *
     * @return void
     * @throws Exception When required dependencies are not available
     */
    protected function init(): void
    {
        $this->apiConfig = ApiConfiguration::getInstance();

        // Load configuration
        $this->loadConfiguration();

        // Initialize fallback embedding
        $this->initializeFallbackEmbedding();

        // Reset statistics
        $this->resetStatistics();

        Logger::debug('Embedding Generator initialized', [
            'model' => $this->embeddingModel,
            'dimensions' => $this->modelDimensions,
            'max_batch_size' => $this->maxBatchSize,
            'cache_ttl' => $this->cacheTtl,
            'development_mode' => $this->apiConfig->isDevelopmentMode()
        ]);
    }

    /**
     * Generate embedding for a single text
     *
     * Generates a vector embedding for the provided text using OpenAI's
     * text-embedding-3-small model. Implements caching and fallback handling.
     *
     * @since 1.0.0
     * @param string $text Text to embed
     * @param array  $args Optional. Embedding arguments.
     * @param bool   $args['use_cache'] Whether to use caching. Default true.
     * @param string $args['model'] Override embedding model. Default text-embedding-3-small.
     * @param int    $args['dimensions'] Reduce dimensions if supported. Default 1536.
     *
     * @return array Vector embedding as array of floats.
     *               Returns fallback embedding on failure.
     *
     * @throws Exception When embedding generation fails and no fallback is available.
     *
     * @example
     * ```php
     * $generator = EmbeddingGenerator::getInstance();
     * $embedding = $generator->generateEmbedding('This is a sample text');
     * echo "Embedding dimensions: " . count($embedding);
     * ```
     */
    public function generateEmbedding(string $text, array $args = []): array
    {
        try {
            // Parse arguments
            $defaults = [
                'use_cache' => true,
                'model' => $this->embeddingModel,
                'dimensions' => $this->modelDimensions
            ];

            $args = wp_parse_args($args, $defaults);

            Logger::debug('Generating single embedding', [
                'text_length' => strlen($text),
                'use_cache' => $args['use_cache'],
                'model' => $args['model']
            ]);

            // Validate and sanitize text
            $text = $this->sanitizeText($text);

            if (empty($text)) {
                Logger::warning('Empty text provided for embedding generation');
                return $this->getFallbackEmbedding();
            }

            // Check cache first
            if ($args['use_cache']) {
                $cacheKey = $this->generateCacheKey($text, $args);
                $cachedEmbedding = Cache::getInstance()->get($cacheKey);

                if ($cachedEmbedding !== false) {
                    $this->stats['cache_hits']++;
                    Logger::debug('Returning cached embedding');
                    return $cachedEmbedding;
                }

                $this->stats['cache_misses']++;
            }

            // Generate embedding via API
            $embeddings = $this->generateBatchEmbeddings([$text], $args);

            if (empty($embeddings) || !isset($embeddings[0])) {
                throw new Exception('Failed to generate embedding');
            }

            $embedding = $embeddings[0];

            // Cache the result
            if ($args['use_cache']) {
                Cache::getInstance()->set($cacheKey, $embedding, $this->cacheTtl);
            }

            $this->stats['total_texts_processed']++;
            $this->stats['total_embeddings_generated']++;

            Logger::info('Single embedding generated successfully', [
                'text_length' => strlen($text),
                'embedding_dimensions' => count($embedding)
            ]);

            return $embedding;
        } catch (Exception $e) {
            Logger::error('Single embedding generation failed', [
                'text_length' => strlen($text),
                'error' => $e->getMessage()
            ]);

            // Return fallback embedding
            return $this->getFallbackEmbedding();
        }
    }

    /**
     * Generate embeddings for multiple texts in batch
     *
     * Efficiently generates embeddings for multiple texts in a single API request,
     * with automatic batching for large datasets and comprehensive error handling.
     *
     * @since 1.0.0
     * @param array $texts Array of texts to embed
     * @param array $args Optional. Embedding arguments.
     * @param bool  $args['use_cache'] Whether to use caching. Default true.
     * @param string $args['model'] Override embedding model. Default text-embedding-3-small.
     * @param int   $args['batch_size'] Override batch size. Default 100.
     * @param bool  $args['preserve_order'] Whether to preserve input order. Default true.
     *
     * @return array Array of vector embeddings in the same order as input texts.
     *               Failed embeddings are replaced with fallback embeddings.
     *
     * @throws Exception When batch embedding generation fails critically.
     *
     * @example
     * ```php
     * $generator = EmbeddingGenerator::getInstance();
     * $texts = ['First text', 'Second text', 'Third text'];
     * $embeddings = $generator->generateBatchEmbeddings($texts);
     * echo "Generated " . count($embeddings) . " embeddings";
     * ```
     */
    public function generateBatchEmbeddings(array $texts, array $args = []): array
    {
        $startTime = microtime(true);

        try {
            // Parse arguments
            $defaults = [
                'use_cache' => true,
                'model' => $this->embeddingModel,
                'batch_size' => $this->maxBatchSize,
                'preserve_order' => true
            ];

            $args = wp_parse_args($args, $defaults);
            $batchSize = min($args['batch_size'], $this->maxBatchSize);

            Logger::info('Starting batch embedding generation', [
                'texts_count' => count($texts),
                'batch_size' => $batchSize,
                'use_cache' => $args['use_cache'],
                'model' => $args['model']
            ]);

            if (empty($texts)) {
                return [];
            }

            // Sanitize texts
            $sanitizedTexts = array_map([$this, 'sanitizeText'], $texts);
            $sanitizedTexts = array_filter($sanitizedTexts); // Remove empty texts

            if (empty($sanitizedTexts)) {
                Logger::warning('All texts were empty after sanitization');
                return array_fill(0, count($texts), $this->getFallbackEmbedding());
            }

            // Check cache for all texts if enabled
            $cachedEmbeddings = [];
            $uncachedTexts = [];
            $uncachedIndices = [];

            if ($args['use_cache']) {
                foreach ($sanitizedTexts as $index => $text) {
                    $cacheKey = $this->generateCacheKey($text, $args);
                    $cachedEmbedding = Cache::getInstance()->get($cacheKey);

                    if ($cachedEmbedding !== false) {
                        $cachedEmbeddings[$index] = $cachedEmbedding;
                        $this->stats['cache_hits']++;
                    } else {
                        $uncachedTexts[] = $text;
                        $uncachedIndices[] = $index;
                        $this->stats['cache_misses']++;
                    }
                }

                Logger::debug('Cache analysis completed', [
                    'cache_hits' => count($cachedEmbeddings),
                    'cache_misses' => count($uncachedTexts),
                    'cache_hit_rate' => count($cachedEmbeddings) / count($sanitizedTexts) * 100
                ]);
            } else {
                $uncachedTexts = $sanitizedTexts;
                $uncachedIndices = array_keys($sanitizedTexts);
            }

            $allEmbeddings = $cachedEmbeddings;

            // Process uncached texts in batches
            if (!empty($uncachedTexts)) {
                $textBatches = array_chunk($uncachedTexts, $batchSize, true);
                $indexBatches = array_chunk($uncachedIndices, $batchSize, true);

                foreach ($textBatches as $batchIndex => $textBatch) {
                    $indexBatch = $indexBatches[$batchIndex];

                    try {
                        Logger::debug("Processing batch " . ($batchIndex + 1) . "/" . count($textBatches), [
                            'texts_in_batch' => count($textBatch)
                        ]);

                        $batchEmbeddings = $this->requestEmbeddingsFromApi($textBatch, $args);

                        // Map batch results to original indices
                        foreach ($textBatch as $batchPos => $text) {
                            $originalIndex = $indexBatch[$batchPos];

                            if (isset($batchEmbeddings[$batchPos])) {
                                $embedding = $batchEmbeddings[$batchPos];
                                $allEmbeddings[$originalIndex] = $embedding;

                                // Cache the result
                                if ($args['use_cache']) {
                                    $cacheKey = $this->generateCacheKey($text, $args);
                                    Cache::getInstance()->set($cacheKey, $embedding, $this->cacheTtl);
                                }
                            } else {
                                Logger::warning('Missing embedding in batch response', [
                                    'batch_index' => $batchIndex,
                                    'batch_position' => $batchPos,
                                    'original_index' => $originalIndex
                                ]);
                                $allEmbeddings[$originalIndex] = $this->getFallbackEmbedding();
                            }
                        }

                        // Rate limiting - small delay between batches
                        if ($batchIndex < count($textBatches) - 1) {
                            sleep(1);
                        }
                    } catch (Exception $e) {
                        Logger::error("Batch {$batchIndex} embedding generation failed", [
                            'error' => $e->getMessage(),
                            'texts_in_batch' => count($textBatch)
                        ]);

                        // Fill failed batch with fallback embeddings
                        foreach ($indexBatch as $originalIndex) {
                            $allEmbeddings[$originalIndex] = $this->getFallbackEmbedding();
                        }
                    }
                }
            }

            // Ensure we have embeddings for all original texts
            $finalEmbeddings = [];
            for ($i = 0; $i < count($texts); $i++) {
                if (isset($allEmbeddings[$i])) {
                    $finalEmbeddings[$i] = $allEmbeddings[$i];
                } else {
                    $finalEmbeddings[$i] = $this->getFallbackEmbedding();
                }
            }

            // Preserve original order if requested
            if ($args['preserve_order']) {
                ksort($finalEmbeddings);
                $finalEmbeddings = array_values($finalEmbeddings);
            }

            // Update statistics
            $processingTime = microtime(true) - $startTime;
            $this->stats['total_texts_processed'] += count($texts);
            $this->stats['total_embeddings_generated'] += count($finalEmbeddings);
            $this->stats['processing_time'] += $processingTime;

            Logger::info('Batch embedding generation completed', [
                'input_texts' => count($texts),
                'output_embeddings' => count($finalEmbeddings),
                'processing_time' => round($processingTime, 3),
                'cache_hit_rate' => count($cachedEmbeddings) > 0 ? round(count($cachedEmbeddings) / count($texts) * 100, 2) : 0
            ]);

            return $finalEmbeddings;
        } catch (Exception $e) {
            $processingTime = microtime(true) - $startTime;
            $this->stats['processing_time'] += $processingTime;

            Logger::error('Batch embedding generation failed', [
                'texts_count' => count($texts),
                'error' => $e->getMessage(),
                'processing_time' => round($processingTime, 3),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Check if embeddings are available (API key configured)
     *
     * @return bool True if embedding generation is available, false otherwise
     */
    public function isAvailable(): bool
    {
        if ($this->apiConfig->isDevelopmentMode()) {
            $hasDevKey = !empty($this->apiConfig->getApiKey('openai'));
            $useDummyData = defined('WOO_AI_USE_DUMMY_EMBEDDINGS') && WOO_AI_USE_DUMMY_EMBEDDINGS;

            return $hasDevKey || $useDummyData;
        }

        return !empty($this->apiConfig->getApiKey('openai'));
    }

    /**
     * Get embedding generation statistics
     *
     * @return array Comprehensive statistics about embedding operations
     */
    public function getStatistics(): array
    {
        try {
            // Calculate derived statistics
            $avgRequestTime = $this->stats['api_requests'] > 0
                ? $this->stats['processing_time'] / $this->stats['api_requests']
                : 0;

            $cacheHitRate = ($this->stats['cache_hits'] + $this->stats['cache_misses']) > 0
                ? ($this->stats['cache_hits'] / ($this->stats['cache_hits'] + $this->stats['cache_misses'])) * 100
                : 0;

            $successRate = $this->stats['total_texts_processed'] > 0
                ? (($this->stats['total_texts_processed'] - $this->stats['failed_requests']) / $this->stats['total_texts_processed']) * 100
                : 0;

            return [
                'current_session_stats' => array_merge($this->stats, [
                    'average_request_time' => round($avgRequestTime, 3),
                    'cache_hit_rate_percent' => round($cacheHitRate, 2),
                    'success_rate_percent' => round($successRate, 2)
                ]),
                'configuration' => [
                    'embedding_model' => $this->embeddingModel,
                    'model_dimensions' => $this->modelDimensions,
                    'max_batch_size' => $this->maxBatchSize,
                    'cache_ttl' => $this->cacheTtl,
                    'request_timeout' => $this->requestTimeout,
                    'is_available' => $this->isAvailable(),
                    'development_mode' => $this->apiConfig->isDevelopmentMode()
                ],
                'rate_limits' => $this->rateLimits,
                'api_status' => [
                    'openai_configured' => !empty($this->apiConfig->getApiKey('openai')),
                    'last_successful_request' => $this->stats['api_requests'] > 0 ? current_time('mysql') : null
                ]
            ];
        } catch (Exception $e) {
            Logger::error('Failed to retrieve embedding statistics', [
                'error' => $e->getMessage()
            ]);
            return [
                'error' => 'Failed to retrieve statistics',
                'current_session_stats' => $this->stats
            ];
        }
    }

    /**
     * Clear embedding cache
     *
     * @return bool True if cache was cleared successfully
     */
    public function clearCache(): bool
    {
        try {
            $cache = Cache::getInstance();

            // Clear all embedding-related cache entries
            // This is a simplified approach - in production, you might want more targeted clearing
            $cache->flush();

            Logger::info('Embedding cache cleared');
            return true;
        } catch (Exception $e) {
            Logger::error('Failed to clear embedding cache', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Load configuration from API settings
     */
    private function loadConfiguration(): void
    {
        // Load embedding model
        $configuredModel = $this->apiConfig->getAiModel('embedding');
        if (!empty($configuredModel)) {
            $this->embeddingModel = $configuredModel;
        }

        // Load other configuration from environment or options
        if ($this->apiConfig->isDevelopmentMode()) {
            $devBatchSize = get_option('woo_ai_dev_max_batch_size', $this->maxBatchSize);
            $this->maxBatchSize = min((int) $devBatchSize, 100);

            $devCacheTtl = get_option('woo_ai_dev_cache_ttl', $this->cacheTtl);
            $this->cacheTtl = max((int) $devCacheTtl, 60); // Minimum 1 minute
        }

        Logger::debug('Embedding configuration loaded', [
            'model' => $this->embeddingModel,
            'max_batch_size' => $this->maxBatchSize,
            'cache_ttl' => $this->cacheTtl
        ]);
    }

    /**
     * Initialize fallback embedding
     */
    private function initializeFallbackEmbedding(): void
    {
        // Create a zero vector with correct dimensions
        $this->fallbackEmbedding = array_fill(0, $this->modelDimensions, 0.0);

        Logger::debug('Fallback embedding initialized', [
            'dimensions' => count($this->fallbackEmbedding)
        ]);
    }

    /**
     * Get fallback embedding for error cases
     *
     * @return array Fallback embedding vector
     */
    private function getFallbackEmbedding(): array
    {
        return $this->fallbackEmbedding ?? array_fill(0, $this->modelDimensions, 0.0);
    }

    /**
     * Request embeddings from OpenAI API
     *
     * @param array $texts Texts to embed
     * @param array $args Embedding arguments
     * @return array Array of embedding vectors
     */
    private function requestEmbeddingsFromApi(array $texts, array $args): array
    {
        $startTime = microtime(true);

        try {
            // Check if we're in development mode without API key - return dummy embeddings
            if ($this->apiConfig->isDevelopmentMode() && empty($this->apiConfig->getApiKey('openai'))) {
                return $this->generateDummyEmbeddings($texts);
            }

            $endpoint = $this->apiConfig->getApiEndpoint('openai', 'embeddings');
            $headers = $this->apiConfig->getApiHeaders('openai');

            $requestBody = [
                'input' => array_values($texts), // Reset array indices
                'model' => $args['model'] ?? $this->embeddingModel
            ];

            // Add dimensions parameter if supported and different from default
            if (isset($args['dimensions']) && $args['dimensions'] !== $this->modelDimensions) {
                $requestBody['dimensions'] = $args['dimensions'];
            }

            Logger::debug('Making OpenAI embeddings API request', [
                'texts_count' => count($texts),
                'model' => $requestBody['model'],
                'endpoint' => $this->apiConfig->isDevelopmentMode() ? $endpoint : '[REDACTED]'
            ]);

            $response = wp_remote_post($endpoint, [
                'headers' => $headers,
                'body' => wp_json_encode($requestBody),
                'timeout' => $this->requestTimeout
            ]);

            if (is_wp_error($response)) {
                throw new Exception('OpenAI API request failed: ' . $response->get_error_message());
            }

            $statusCode = wp_remote_retrieve_response_code($response);
            $responseBody = wp_remote_retrieve_body($response);

            if ($statusCode !== 200) {
                $this->handleApiError($statusCode, $responseBody);
                return array_fill(0, count($texts), $this->getFallbackEmbedding());
            }

            $data = json_decode($responseBody, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON response from OpenAI: ' . json_last_error_msg());
            }

            if (!isset($data['data']) || !is_array($data['data'])) {
                throw new Exception('Invalid response structure from OpenAI API');
            }

            // Extract embeddings from response
            $embeddings = [];
            foreach ($data['data'] as $item) {
                if (isset($item['embedding']) && is_array($item['embedding'])) {
                    $embeddings[] = $item['embedding'];
                } else {
                    $embeddings[] = $this->getFallbackEmbedding();
                }
            }

            // Update statistics
            $requestTime = microtime(true) - $startTime;
            $this->stats['api_requests']++;
            $this->stats['processing_time'] += $requestTime;
            $this->stats['total_tokens_used'] += $data['usage']['total_tokens'] ?? 0;

            Logger::info('OpenAI embeddings API request completed', [
                'texts_count' => count($texts),
                'embeddings_returned' => count($embeddings),
                'tokens_used' => $data['usage']['total_tokens'] ?? 0,
                'request_time' => round($requestTime, 3)
            ]);

            return $embeddings;
        } catch (Exception $e) {
            $this->stats['failed_requests']++;

            Logger::error('OpenAI embeddings API request failed', [
                'texts_count' => count($texts),
                'error' => $e->getMessage(),
                'request_time' => round(microtime(true) - $startTime, 3)
            ]);

            throw $e;
        }
    }

    /**
     * Generate dummy embeddings for development mode
     *
     * @param array $texts Texts to generate embeddings for
     * @return array Array of dummy embedding vectors
     */
    private function generateDummyEmbeddings(array $texts): array
    {
        Logger::debug('Generating dummy embeddings for development mode', [
            'texts_count' => count($texts)
        ]);

        $embeddings = [];
        foreach ($texts as $text) {
            // Create a simple hash-based "embedding" for consistency
            $hash = md5($text);
            $embedding = [];

            for ($i = 0; $i < $this->modelDimensions; $i++) {
                $charIndex = $i % strlen($hash);
                $charValue = ord($hash[$charIndex]);
                $embedding[] = ($charValue - 128) / 128.0; // Normalize to [-1, 1]
            }

            $embeddings[] = $embedding;
        }

        return $embeddings;
    }

    /**
     * Handle API errors with appropriate logging and fallback
     *
     * @param int    $statusCode HTTP status code
     * @param string $responseBody Response body
     * @throws Exception When error handling fails
     */
    private function handleApiError(int $statusCode, string $responseBody): void
    {
        $errorData = json_decode($responseBody, true);
        $errorMessage = 'Unknown API error';
        $errorType = 'unknown';

        if (isset($errorData['error']['message'])) {
            $errorMessage = $errorData['error']['message'];
            $errorType = $errorData['error']['type'] ?? 'api_error';
        }

        Logger::error('OpenAI API error', [
            'status_code' => $statusCode,
            'error_type' => $errorType,
            'error_message' => $errorMessage,
            'response_body' => $responseBody
        ]);

        // Handle specific error types
        switch ($statusCode) {
            case 401:
                throw new Exception('OpenAI API authentication failed. Check API key.');

            case 429:
                Logger::warning('OpenAI API rate limit exceeded, will retry with fallback');
                break;

            case 500:
            case 502:
            case 503:
                Logger::warning('OpenAI API server error, will retry with fallback');
                break;

            default:
                throw new Exception("OpenAI API error ({$statusCode}): {$errorMessage}");
        }
    }

    /**
     * Sanitize text for embedding generation
     *
     * @param string $text Text to sanitize
     * @return string Sanitized text
     */
    private function sanitizeText(string $text): string
    {
        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        // Trim whitespace
        $text = trim($text);

        // Limit length to prevent API errors (OpenAI has token limits)
        if (strlen($text) > 8000) { // Conservative limit
            $text = substr($text, 0, 8000) . '...';
        }

        return $text;
    }

    /**
     * Generate cache key for text embedding
     *
     * @param string $text Text to cache
     * @param array  $args Embedding arguments
     * @return string Cache key
     */
    private function generateCacheKey(string $text, array $args): string
    {
        $keyData = [
            'text' => $text,
            'model' => $args['model'] ?? $this->embeddingModel,
            'dimensions' => $args['dimensions'] ?? $this->modelDimensions
        ];

        return 'woo_ai_embedding_' . md5(serialize($keyData));
    }

    /**
     * Reset statistics
     */
    private function resetStatistics(): void
    {
        $this->stats = [
            'total_texts_processed' => 0,
            'total_embeddings_generated' => 0,
            'cache_hits' => 0,
            'cache_misses' => 0,
            'api_requests' => 0,
            'failed_requests' => 0,
            'total_tokens_used' => 0,
            'processing_time' => 0,
            'average_request_time' => 0
        ];
    }

    /**
     * Set embedding model
     *
     * @param string $model Embedding model name
     * @throws Exception When model is invalid
     */
    public function setEmbeddingModel(string $model): void
    {
        if (empty($model)) {
            throw new Exception('Embedding model cannot be empty');
        }

        $this->embeddingModel = $model;
        Logger::debug('Embedding model updated', ['new_model' => $model]);
    }

    /**
     * Set maximum batch size
     *
     * @param int $size Maximum batch size (must be positive and reasonable)
     * @throws Exception When batch size is invalid
     */
    public function setMaxBatchSize(int $size): void
    {
        if ($size <= 0 || $size > 2048) {
            throw new Exception('Batch size must be between 1 and 2048');
        }

        $this->maxBatchSize = $size;
        Logger::debug('Embedding batch size updated', ['new_size' => $size]);
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
        Logger::debug('Embedding cache TTL updated', ['new_ttl' => $ttl]);
    }

    /**
     * Set request timeout
     *
     * @param int $timeout Timeout in seconds
     * @throws Exception When timeout is invalid
     */
    public function setRequestTimeout(int $timeout): void
    {
        if ($timeout <= 0 || $timeout > 300) {
            throw new Exception('Request timeout must be between 1 and 300 seconds');
        }

        $this->requestTimeout = $timeout;
        Logger::debug('Embedding request timeout updated', ['new_timeout' => $timeout]);
    }
}
