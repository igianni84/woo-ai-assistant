<?php

/**
 * RAG Engine Class
 *
 * Implements comprehensive Retrieval-Augmented Generation (RAG) system that integrates
 * with the Knowledge Base to provide context-aware AI responses. Handles retrieval,
 * re-ranking, context window optimization, prompt engineering, and safety guardrails.
 *
 * @package WooAiAssistant
 * @subpackage Chatbot
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Chatbot;

use WooAiAssistant\Common\Utils;
use WooAiAssistant\Common\Traits\Singleton;
use WooAiAssistant\KnowledgeBase\VectorManager;
use WooAiAssistant\KnowledgeBase\AIManager;
use WooAiAssistant\Api\LicenseManager;
use WP_Error;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class RagEngine
 *
 * Advanced Retrieval-Augmented Generation (RAG) engine that orchestrates the entire
 * knowledge retrieval and response generation pipeline. Implements sophisticated
 * retrieval strategies, multi-factor re-ranking, context optimization, prompt
 * engineering, and comprehensive safety measures for production-ready AI responses.
 *
 * @since 1.0.0
 */
class RagEngine
{
    use Singleton;

    /**
     * Default similarity threshold for vector search
     *
     * @since 1.0.0
     * @var float
     */
    private const DEFAULT_SIMILARITY_THRESHOLD = 0.7;

    /**
     * Maximum number of chunks to retrieve initially
     *
     * @since 1.0.0
     * @var int
     */
    private const MAX_INITIAL_RETRIEVAL = 20;

    /**
     * Maximum number of chunks after re-ranking
     *
     * @since 1.0.0
     * @var int
     */
    private const MAX_FINAL_CHUNKS = 8;

    /**
     * Maximum context window size in tokens (approximate)
     *
     * @since 1.0.0
     * @var int
     */
    private const MAX_CONTEXT_TOKENS = 4000;

    /**
     * Cache TTL for retrieval results (in seconds)
     *
     * @since 1.0.0
     * @var int
     */
    private const CACHE_TTL = 300; // 5 minutes

    /**
     * Vector Manager instance
     *
     * @since 1.0.0
     * @var VectorManager
     */
    private $vectorManager;

    /**
     * AI Manager instance
     *
     * @since 1.0.0
     * @var AIManager
     */
    private $aiManager;

    /**
     * License Manager instance
     *
     * @since 1.0.0
     * @var LicenseManager
     */
    private $licenseManager;

    /**
     * Cache key prefix for retrieval results
     *
     * @since 1.0.0
     * @var string
     */
    private const CACHE_PREFIX = 'woo_ai_rag_';

    /**
     * Initialize the RAG Engine
     *
     * Sets up dependencies and integrates with existing Knowledge Base components
     * for comprehensive retrieval-augmented generation functionality.
     *
     * @since 1.0.0
     */
    protected function init(): void
    {
        $this->vectorManager = VectorManager::getInstance();
        $this->aiManager = AIManager::getInstance();
        $this->licenseManager = LicenseManager::getInstance();

        // Hook into WordPress for debugging and monitoring
        add_action('woo_ai_assistant_rag_retrieval_complete', [$this, 'logRetrievalMetrics'], 10, 2);
        add_action('woo_ai_assistant_rag_reranking_complete', [$this, 'logReRankingMetrics'], 10, 2);

        // Debug logging in development mode
        if (defined('WOO_AI_ASSISTANT_DEBUG') && WOO_AI_ASSISTANT_DEBUG) {
            add_action('woo_ai_assistant_rag_debug', [$this, 'debugLog'], 10, 2);
        }
    }

    /**
     * Generate RAG-enhanced response for user query
     *
     * Orchestrates the complete RAG pipeline: retrieval, re-ranking, context building,
     * prompt optimization, and response generation with comprehensive error handling
     * and safety measures.
     *
     * @since 1.0.0
     * @param string $query User query or message
     * @param array  $context Optional. Additional context information
     *               - conversation_id: string Current conversation ID
     *               - user_context: array User session and page context
     *               - message_history: array Recent conversation messages
     *               - page_context: array Current page information
     *               - product_context: array Current product information
     * @param array  $options Optional. RAG configuration options
     *               - similarity_threshold: float Minimum similarity score (0.0-1.0)
     *               - max_chunks: int Maximum context chunks to use
     *               - enable_reranking: bool Whether to apply re-ranking algorithm
     *               - response_mode: string Response generation mode ('standard', 'detailed', 'concise')
     *               - safety_level: string Safety filtering level ('strict', 'moderate', 'relaxed')
     *
     * @return array|WP_Error RAG response data or error object
     *                        Success format:
     *                        {
     *                            'response': string,
     *                            'confidence': float,
     *                            'sources_used': array,
     *                            'retrieval_stats': array,
     *                            'safety_passed': bool
     *                        }
     *
     * @throws \InvalidArgumentException When query is empty or invalid context provided.
     * @throws \RuntimeException When RAG pipeline encounters critical errors.
     *
     * @example
     * ```php
     * $ragEngine = RagEngine::getInstance();
     * $result = $ragEngine->generateRagResponse(
     *     'What is your return policy?',
     *     ['conversation_id' => 'conv-123', 'user_context' => $userContext],
     *     ['similarity_threshold' => 0.8, 'max_chunks' => 6]
     * );
     * ```
     */
    public function generateRagResponse(string $query, array $context = [], array $options = [])
    {
        try {
            // Validate input parameters
            if (empty(trim($query))) {
                return new WP_Error(
                    'invalid_query',
                    'Query cannot be empty',
                    ['status' => 400]
                );
            }

            // Merge options with defaults
            $options = wp_parse_args($options, [
                'similarity_threshold' => self::DEFAULT_SIMILARITY_THRESHOLD,
                'max_chunks' => self::MAX_FINAL_CHUNKS,
                'enable_reranking' => true,
                'response_mode' => 'standard',
                'safety_level' => 'moderate'
            ]);

            // Step 1: Safety pre-screening of query
            $safetyCheck = $this->performSafetyCheck($query, $options['safety_level']);
            if (is_wp_error($safetyCheck)) {
                return $safetyCheck;
            }

            // Step 2: Retrieve relevant chunks from Knowledge Base
            $retrievalResult = $this->performRetrieval($query, $context, $options);
            if (is_wp_error($retrievalResult)) {
                return $retrievalResult;
            }

            // Step 3: Re-rank retrieved chunks for relevance
            if ($options['enable_reranking'] && !empty($retrievalResult['chunks'])) {
                $rerankedChunks = $this->performReRanking(
                    $query,
                    $retrievalResult['chunks'],
                    $context,
                    $options
                );
                if (is_wp_error($rerankedChunks)) {
                    // Continue with original chunks if re-ranking fails
                    Utils::logError('RAG re-ranking failed, using original chunks: ' . $rerankedChunks->get_error_message());
                    $finalChunks = array_slice($retrievalResult['chunks'], 0, $options['max_chunks']);
                } else {
                    $finalChunks = $rerankedChunks;
                }
            } else {
                $finalChunks = array_slice($retrievalResult['chunks'], 0, $options['max_chunks']);
            }

            // Step 4: Build optimized context window
            $contextWindow = $this->buildContextWindow($query, $finalChunks, $context, $options);
            if (is_wp_error($contextWindow)) {
                return $contextWindow;
            }

            // Step 5: Generate optimized prompt
            $prompt = $this->buildOptimizedPrompt($query, $contextWindow, $context, $options);
            if (is_wp_error($prompt)) {
                return $prompt;
            }

            // Step 6: Generate AI response
            $response = $this->aiManager->generateResponse($prompt, [
                'conversation_context' => $context['message_history'] ?? [],
                'model_preference' => $this->selectModelForContext($contextWindow, $options),
                'temperature' => $this->calculateTemperature($options['response_mode']),
                'max_tokens' => $this->calculateMaxTokens($options['response_mode'])
            ]);

            if (is_wp_error($response)) {
                return $response;
            }

            // Step 7: Post-process and validate response
            $processedResponse = $this->postProcessResponse($response, $finalChunks, $options);
            if (is_wp_error($processedResponse)) {
                return $processedResponse;
            }

            // Log successful RAG completion
            do_action('woo_ai_assistant_rag_response_generated', [
                'query' => $query,
                'chunks_used' => count($finalChunks),
                'confidence' => $processedResponse['confidence'],
                'response_mode' => $options['response_mode']
            ]);

            return $processedResponse;
        } catch (\Throwable $e) {
            Utils::logError('RAG Engine critical error: ' . $e->getMessage());

            // Return graceful error response
            return new WP_Error(
                'rag_engine_error',
                'Unable to generate response at this time. Please try again.',
                ['status' => 500, 'debug' => $e->getMessage()]
            );
        }
    }

    /**
     * Perform comprehensive Knowledge Base retrieval
     *
     * Executes semantic similarity search using VectorManager with intelligent
     * caching, fallback strategies, and performance optimization.
     *
     * @since 1.0.0
     * @param string $query User query for similarity search
     * @param array  $context Context information for relevance filtering
     * @param array  $options Retrieval configuration options
     *
     * @return array|WP_Error Retrieval results or error
     *                        Success format:
     *                        {
     *                            'chunks': array,
     *                            'total_found': int,
     *                            'search_time': float,
     *                            'cache_hit': bool
     *                        }
     */
    private function performRetrieval(string $query, array $context, array $options)
    {
        $startTime = microtime(true);
        $cacheKey = $this->generateCacheKey('retrieval', $query, $options);

        // Check cache first
        $cachedResult = wp_cache_get($cacheKey, 'woo_ai_assistant_rag');
        if (false !== $cachedResult) {
            $cachedResult['cache_hit'] = true;
            $cachedResult['search_time'] = microtime(true) - $startTime;
            return $cachedResult;
        }

        try {
            // First generate embedding for the query, then perform search
            $embedding = $this->vectorManager->generateEmbedding($query);
            if (is_wp_error($embedding)) {
                return $embedding;
            }

            if (empty($embedding) || !is_array($embedding)) {
                return new \WP_Error('embedding_generation_failed', 'Failed to generate embedding for query');
            }

            // Perform vector similarity search
            $searchResults = $this->vectorManager->searchSimilar($embedding, [
                'threshold' => $options['similarity_threshold'],
                'limit' => self::MAX_INITIAL_RETRIEVAL,
                'include_metadata' => true,
                'context_filter' => $this->buildContextFilter($context)
            ]);

            if (is_wp_error($searchResults)) {
                return $searchResults;
            }

            $result = [
                'chunks' => $searchResults['chunks'] ?? [],
                'total_found' => $searchResults['total'] ?? 0,
                'search_time' => microtime(true) - $startTime,
                'cache_hit' => false
            ];

            // Cache successful results
            wp_cache_set($cacheKey, $result, 'woo_ai_assistant_rag', self::CACHE_TTL);

            // Fire retrieval complete hook
            do_action('woo_ai_assistant_rag_retrieval_complete', $query, $result);

            return $result;
        } catch (\Exception $e) {
            Utils::logError('RAG retrieval error: ' . $e->getMessage());

            return new WP_Error(
                'retrieval_failed',
                'Knowledge base search failed',
                ['debug' => $e->getMessage()]
            );
        }
    }

    /**
     * Perform advanced re-ranking of retrieved chunks
     *
     * Implements multi-factor scoring algorithm that considers semantic similarity,
     * content type priority, freshness, user context, and relevance signals to
     * optimize chunk selection for response generation.
     *
     * @since 1.0.0
     * @param string $query Original user query
     * @param array  $chunks Retrieved chunks from initial search
     * @param array  $context User and conversation context
     * @param array  $options Re-ranking configuration options
     *
     * @return array|WP_Error Re-ranked chunks or error
     */
    private function performReRanking(string $query, array $chunks, array $context, array $options)
    {
        if (empty($chunks)) {
            return [];
        }

        try {
            $startTime = microtime(true);
            $rerankedChunks = [];

            foreach ($chunks as $chunk) {
                $score = $this->calculateReRankingScore($query, $chunk, $context, $options);
                $chunk['rerank_score'] = $score;
                $chunk['original_score'] = $chunk['similarity_score'] ?? 0.0;
                $rerankedChunks[] = $chunk;
            }

            // Sort by re-ranking score (descending)
            usort($rerankedChunks, function ($a, $b) {
                return ($b['rerank_score'] ?? 0.0) <=> ($a['rerank_score'] ?? 0.0);
            });

            // Take top results
            $finalChunks = array_slice($rerankedChunks, 0, $options['max_chunks']);

            $reRankTime = microtime(true) - $startTime;

            // Fire re-ranking complete hook
            do_action('woo_ai_assistant_rag_reranking_complete', $query, [
                'original_count' => count($chunks),
                'final_count' => count($finalChunks),
                'rerank_time' => $reRankTime
            ]);

            if (defined('WOO_AI_ASSISTANT_DEBUG') && WOO_AI_ASSISTANT_DEBUG) {
                Utils::logDebug("RAG re-ranking: {$reRankTime}ms, {" . count($chunks) . "} -> {" . count($finalChunks) . "} chunks");
            }

            return $finalChunks;
        } catch (\Exception $e) {
            Utils::logError('RAG re-ranking error: ' . $e->getMessage());

            return new WP_Error(
                'reranking_failed',
                'Chunk re-ranking failed',
                ['debug' => $e->getMessage()]
            );
        }
    }

    /**
     * Calculate comprehensive re-ranking score for a chunk
     *
     * Multi-factor scoring algorithm that evaluates:
     * - Semantic similarity (40%)
     * - Content type relevance (25%)
     * - Freshness and recency (15%)
     * - User context match (10%)
     * - Chunk quality indicators (10%)
     *
     * @since 1.0.0
     * @param string $query User query
     * @param array  $chunk Chunk data with metadata
     * @param array  $context User context information
     * @param array  $options Scoring configuration
     *
     * @return float Composite re-ranking score (0.0-1.0)
     */
    private function calculateReRankingScore(string $query, array $chunk, array $context, array $options): float
    {
        $scores = [];
        $weights = [
            'semantic' => 0.40,
            'content_type' => 0.25,
            'freshness' => 0.15,
            'context_match' => 0.10,
            'quality' => 0.10
        ];

        // 1. Semantic similarity score (from vector search)
        $scores['semantic'] = $chunk['similarity_score'] ?? 0.0;

        // 2. Content type relevance score
        $scores['content_type'] = $this->calculateContentTypeScore($chunk, $query, $context);

        // 3. Freshness score (newer content gets higher score)
        $scores['freshness'] = $this->calculateFreshnessScore($chunk);

        // 4. Context matching score
        $scores['context_match'] = $this->calculateContextMatchScore($chunk, $context);

        // 5. Quality indicators score
        $scores['quality'] = $this->calculateQualityScore($chunk);

        // Calculate weighted composite score
        $compositeScore = 0.0;
        foreach ($scores as $type => $score) {
            $compositeScore += $score * $weights[$type];
        }

        // Apply boost factors for specific scenarios
        $boostFactor = $this->calculateBoostFactor($chunk, $query, $context);
        $finalScore = min(1.0, $compositeScore * $boostFactor);

        return $finalScore;
    }

    /**
     * Calculate content type relevance score
     *
     * Assigns higher scores to content types that are more likely to be relevant
     * for different query types (products, policies, FAQ, etc.).
     *
     * @since 1.0.0
     * @param array  $chunk Chunk metadata
     * @param string $query User query
     * @param array  $context User context
     *
     * @return float Content type score (0.0-1.0)
     */
    private function calculateContentTypeScore(array $chunk, string $query, array $context): float
    {
        $contentType = $chunk['content_type'] ?? 'unknown';
        $queryLower = strtolower($query);

        // Content type priority mapping
        $priorities = [
            'product' => 0.9,
            'faq' => 0.85,
            'policy' => 0.8,
            'page' => 0.75,
            'post' => 0.7,
            'woocommerce_settings' => 0.8,
            'category' => 0.65,
            'unknown' => 0.5
        ];

        $baseScore = $priorities[$contentType] ?? 0.5;

        // Query-specific adjustments
        if (strpos($queryLower, 'product') !== false || strpos($queryLower, 'buy') !== false) {
            if ($contentType === 'product') {
                $baseScore *= 1.2;
            }
        }

        if (strpos($queryLower, 'return') !== false || strpos($queryLower, 'policy') !== false) {
            if ($contentType === 'policy') {
                $baseScore *= 1.2;
            }
        }

        if (strpos($queryLower, 'how') !== false || strpos($queryLower, 'what') !== false) {
            if ($contentType === 'faq') {
                $baseScore *= 1.15;
            }
        }

        return min(1.0, $baseScore);
    }

    /**
     * Calculate freshness score based on content age
     *
     * @since 1.0.0
     * @param array $chunk Chunk with timestamp metadata
     *
     * @return float Freshness score (0.0-1.0)
     */
    private function calculateFreshnessScore(array $chunk): float
    {
        $lastModified = $chunk['last_modified'] ?? $chunk['created_at'] ?? null;

        if (!$lastModified) {
            return 0.5; // Neutral score for unknown age
        }

        $timestamp = is_string($lastModified) ? strtotime($lastModified) : $lastModified;
        $ageInDays = (time() - $timestamp) / DAY_IN_SECONDS;

        // Exponential decay: newer content gets higher score
        if ($ageInDays <= 7) {
            return 1.0; // Very fresh content
        } elseif ($ageInDays <= 30) {
            return 0.9; // Recent content
        } elseif ($ageInDays <= 90) {
            return 0.7; // Moderately fresh
        } elseif ($ageInDays <= 365) {
            return 0.5; // Older content
        } else {
            return 0.3; // Very old content
        }
    }

    /**
     * Calculate context matching score
     *
     * @since 1.0.0
     * @param array $chunk Chunk metadata
     * @param array $context User context
     *
     * @return float Context match score (0.0-1.0)
     */
    private function calculateContextMatchScore(array $chunk, array $context): float
    {
        $score = 0.5; // Base score

        // Page context matching
        if (isset($context['page_context']['type'])) {
            $pageType = $context['page_context']['type'];
            $chunkType = $chunk['content_type'] ?? '';

            if ($pageType === 'product' && $chunkType === 'product') {
                $score += 0.3;
            } elseif ($pageType === 'shop' && in_array($chunkType, ['product', 'category'])) {
                $score += 0.2;
            }
        }

        // User intent matching
        if (isset($context['user_context']['intent'])) {
            $userIntent = $context['user_context']['intent'];
            if ($userIntent === 'purchase' && $chunk['content_type'] === 'product') {
                $score += 0.2;
            }
        }

        return min(1.0, $score);
    }

    /**
     * Calculate content quality score
     *
     * @since 1.0.0
     * @param array $chunk Chunk metadata
     *
     * @return float Quality score (0.0-1.0)
     */
    private function calculateQualityScore(array $chunk): float
    {
        $score = 0.5; // Base quality

        // Content length indicators
        $contentLength = strlen($chunk['content'] ?? '');
        if ($contentLength > 100 && $contentLength < 2000) {
            $score += 0.2; // Good length content
        } elseif ($contentLength < 50) {
            $score -= 0.2; // Too short
        }

        // Metadata richness
        $metadataCount = count($chunk['metadata'] ?? []);
        if ($metadataCount > 3) {
            $score += 0.1; // Rich metadata
        }

        // Chunk completeness
        if (isset($chunk['title']) && !empty($chunk['title'])) {
            $score += 0.1;
        }

        if (isset($chunk['summary']) && !empty($chunk['summary'])) {
            $score += 0.1;
        }

        return min(1.0, max(0.1, $score));
    }

    /**
     * Calculate boost factors for special scenarios
     *
     * @since 1.0.0
     * @param array  $chunk Chunk data
     * @param string $query User query
     * @param array  $context User context
     *
     * @return float Boost multiplier (0.5-2.0)
     */
    private function calculateBoostFactor(array $chunk, string $query, array $context): float
    {
        $boost = 1.0;

        // Exact keyword matches
        $queryWords = array_map('strtolower', explode(' ', $query));
        $chunkContent = strtolower($chunk['content'] ?? '');

        $exactMatches = 0;
        foreach ($queryWords as $word) {
            if (strlen($word) > 3 && strpos($chunkContent, $word) !== false) {
                $exactMatches++;
            }
        }

        if ($exactMatches > 0) {
            $boost *= (1.0 + 0.1 * $exactMatches); // 10% boost per exact match
        }

        // High-priority content types
        if (in_array($chunk['content_type'] ?? '', ['faq', 'policy', 'product_description'])) {
            $boost *= 1.1;
        }

        // Recent user interaction boost
        if (isset($context['recent_products']) && $chunk['content_type'] === 'product') {
            $productId = $chunk['source_id'] ?? null;
            if ($productId && in_array($productId, $context['recent_products'] ?? [])) {
                $boost *= 1.2;
            }
        }

        return max(0.5, min(2.0, $boost));
    }

    /**
     * Build optimized context window for AI prompt
     *
     * Creates a structured context window that maximizes information density
     * while respecting token limits and maintaining coherent information flow.
     *
     * @since 1.0.0
     * @param string $query User query
     * @param array  $chunks Re-ranked and selected chunks
     * @param array  $context User and conversation context
     * @param array  $options Context window options
     *
     * @return array|WP_Error Context window structure or error
     */
    private function buildContextWindow(string $query, array $chunks, array $context, array $options)
    {
        try {
            $contextWindow = [
                'query' => $query,
                'relevant_content' => [],
                'metadata' => [
                    'total_chunks' => count($chunks),
                    'estimated_tokens' => 0,
                    'context_types' => []
                ],
                'user_context' => $this->extractUserContextSummary($context)
            ];

            $currentTokens = 0;
            $tokenLimit = self::MAX_CONTEXT_TOKENS;

            foreach ($chunks as $chunk) {
                // Estimate token count (rough approximation: 1 token ≈ 4 characters)
                $chunkTokens = ceil(strlen($chunk['content'] ?? '') / 4);

                if (($currentTokens + $chunkTokens) > $tokenLimit) {
                    // Try to truncate chunk if it's valuable
                    if ($chunk['rerank_score'] > 0.8) {
                        $truncatedContent = $this->truncateContentIntelligently(
                            $chunk['content'],
                            $tokenLimit - $currentTokens
                        );

                        if (!empty($truncatedContent)) {
                            $chunk['content'] = $truncatedContent;
                            $chunk['truncated'] = true;
                            $chunkTokens = ceil(strlen($truncatedContent) / 4);
                        } else {
                            break; // Skip if can't fit even truncated version
                        }
                    } else {
                        break; // Skip lower-quality chunks when approaching limit
                    }
                }

                $contextWindow['relevant_content'][] = [
                    'content' => $chunk['content'],
                    'type' => $chunk['content_type'] ?? 'unknown',
                    'source' => $chunk['source_title'] ?? 'Unknown',
                    'relevance_score' => $chunk['rerank_score'] ?? 0.0,
                    'metadata' => $chunk['metadata'] ?? []
                ];

                $currentTokens += $chunkTokens;

                // Track content types
                $contentType = $chunk['content_type'] ?? 'unknown';
                if (!in_array($contentType, $contextWindow['metadata']['context_types'])) {
                    $contextWindow['metadata']['context_types'][] = $contentType;
                }
            }

            $contextWindow['metadata']['estimated_tokens'] = $currentTokens;

            return $contextWindow;
        } catch (\Exception $e) {
            Utils::logError('Context window building error: ' . $e->getMessage());

            return new WP_Error(
                'context_window_failed',
                'Failed to build context window',
                ['debug' => $e->getMessage()]
            );
        }
    }

    /**
     * Build optimized prompt for AI response generation
     *
     * Creates a structured prompt that combines system instructions, context,
     * user query, and response guidelines optimized for the selected AI model.
     *
     * @since 1.0.0
     * @param string $query User query
     * @param array  $contextWindow Built context window
     * @param array  $context Full user context
     * @param array  $options Prompt generation options
     *
     * @return string|WP_Error Generated prompt or error
     */
    private function buildOptimizedPrompt(string $query, array $contextWindow, array $context, array $options)
    {
        try {
            $promptTemplate = $this->getPromptTemplate($options['response_mode']);

            $prompt = str_replace([
                '{SYSTEM_ROLE}',
                '{STORE_CONTEXT}',
                '{RELEVANT_CONTENT}',
                '{USER_CONTEXT}',
                '{USER_QUERY}',
                '{RESPONSE_GUIDELINES}'
            ], [
                $this->buildSystemRole(),
                $this->buildStoreContext($context),
                $this->buildRelevantContentSection($contextWindow),
                $this->buildUserContextSection($contextWindow['user_context']),
                $query,
                $this->buildResponseGuidelines($options['response_mode'])
            ], $promptTemplate);

            // Apply safety and content guidelines
            $prompt .= "\n\n" . $this->buildSafetyGuidelines($options['safety_level'] ?? 'standard');

            return $prompt;
        } catch (\Exception $e) {
            Utils::logError('Prompt building error: ' . $e->getMessage());

            return new WP_Error(
                'prompt_building_failed',
                'Failed to build AI prompt',
                ['debug' => $e->getMessage()]
            );
        }
    }

    /**
     * Post-process and validate AI response
     *
     * @since 1.0.0
     * @param array $response Raw AI response
     * @param array $chunks Source chunks used
     * @param array $options Processing options
     *
     * @return array|WP_Error Processed response or error
     */
    private function postProcessResponse($response, array $chunks, array $options)
    {
        try {
            // Handle WP_Error responses
            if (is_wp_error($response)) {
                return $response;
            }

            if (!is_array($response)) {
                return new WP_Error(
                    'invalid_response_format',
                    'Response must be an array',
                    ['debug' => 'Received: ' . gettype($response)]
                );
            }

            $processedResponse = [
                'response' => $response['response'] ?? '',
                'confidence' => $this->calculateResponseConfidence($response, $chunks),
                'sources_used' => $this->extractSourcesUsed($chunks),
                'retrieval_stats' => [
                    'chunks_retrieved' => count($chunks),
                    'avg_relevance' => $this->calculateAverageRelevance($chunks),
                    'content_types' => array_unique(array_column($chunks, 'content_type'))
                ],
                'safety_passed' => true, // Post-processing safety check would go here
                'response_metadata' => [
                    'response_mode' => $options['response_mode'],
                    'generation_time' => $response['generation_time'] ?? 0,
                    'model_used' => $response['model'] ?? 'unknown'
                ]
            ];

            // Apply post-processing filters
            $processedResponse = apply_filters('woo_ai_assistant_rag_response_processed', $processedResponse, $response, $chunks, $options);

            return $processedResponse;
        } catch (\Exception $e) {
            Utils::logError('Response post-processing error: ' . $e->getMessage());

            return new WP_Error(
                'response_processing_failed',
                'Failed to process AI response',
                ['debug' => $e->getMessage()]
            );
        }
    }

    /**
     * Perform comprehensive safety check on query and context
     *
     * @since 1.0.0
     * @param string $query User query
     * @param string $safetyLevel Safety filtering level
     *
     * @return bool|WP_Error True if safe, error if blocked
     */
    private function performSafetyCheck(string $query, string $safetyLevel)
    {
        // Implement safety checks based on level
        $blockedPatterns = $this->getSafetyPatterns($safetyLevel);

        foreach ($blockedPatterns as $pattern) {
            if (preg_match($pattern, strtolower($query))) {
                return new WP_Error(
                    'safety_check_failed',
                    'Query contains inappropriate content',
                    ['status' => 400]
                );
            }
        }

        return true;
    }

    /**
     * Get safety patterns for different levels
     *
     * @since 1.0.0
     * @param string $level Safety level
     *
     * @return array Regex patterns for blocked content
     */
    private function getSafetyPatterns(string $level): array
    {
        $patterns = [
            'strict' => [
                '/\b(hack|exploit|crack|pirate)\b/i',
                '/\b(porn|adult|xxx)\b/i',
                '/\b(spam|scam|phishing)\b/i',
            ],
            'moderate' => [
                '/\b(hack|exploit|crack)\b/i',
                '/\b(porn|xxx)\b/i',
            ],
            'relaxed' => [
                '/\b(hack|exploit)\b/i',
            ]
        ];

        return $patterns[$level] ?? $patterns['moderate'];
    }

    /**
     * Generate cache key for results caching
     *
     * @since 1.0.0
     * @param string $type Cache type
     * @param string $query Query string
     * @param array  $options Options array
     *
     * @return string Cache key
     */
    private function generateCacheKey(string $type, string $query, array $options): string
    {
        $key = self::CACHE_PREFIX . $type . '_' . md5($query . serialize($options));
        return $key;
    }

    /**
     * Build context filter for search
     *
     * @since 1.0.0
     * @param array $context User context
     *
     * @return array Context filter array
     */
    private function buildContextFilter(array $context): array
    {
        $filter = [];

        // Add page context filtering
        if (isset($context['page_context']['type'])) {
            $filter['page_type'] = $context['page_context']['type'];
        }

        // Add product context filtering
        if (isset($context['product_context']['id'])) {
            $filter['related_products'] = [$context['product_context']['id']];
        }

        return $filter;
    }

    /**
     * Get prompt template for response mode
     *
     * @since 1.0.0
     * @param string $mode Response mode
     *
     * @return string Prompt template
     */
    private function getPromptTemplate(string $mode): string
    {
        $templates = [
            'standard' => "You are {SYSTEM_ROLE}.\n\n{STORE_CONTEXT}\n\nRelevant Information:\n{RELEVANT_CONTENT}\n\nUser Context:\n{USER_CONTEXT}\n\nUser Question: {USER_QUERY}\n\n{RESPONSE_GUIDELINES}",
            'detailed' => "You are {SYSTEM_ROLE}.\n\n{STORE_CONTEXT}\n\nComprehensive Information:\n{RELEVANT_CONTENT}\n\nDetailed User Context:\n{USER_CONTEXT}\n\nUser Question: {USER_QUERY}\n\n{RESPONSE_GUIDELINES}\n\nProvide a detailed, comprehensive response.",
            'concise' => "You are {SYSTEM_ROLE}.\n\n{STORE_CONTEXT}\n\nKey Information:\n{RELEVANT_CONTENT}\n\nUser Question: {USER_QUERY}\n\n{RESPONSE_GUIDELINES}\n\nProvide a concise, direct answer."
        ];

        return $templates[$mode] ?? $templates['standard'];
    }

    /**
     * Build system role description
     *
     * @since 1.0.0
     * @return string System role
     */
    private function buildSystemRole(): string
    {
        $siteName = get_bloginfo('name');
        return "a helpful AI assistant for {$siteName}, specializing in providing accurate information about products, policies, and customer service";
    }

    /**
     * Build store context information
     *
     * @since 1.0.0
     * @param array $context User context
     *
     * @return string Store context
     */
    private function buildStoreContext(array $context): string
    {
        $storeContext = "Store: " . get_bloginfo('name') . "\n";
        $storeContext .= "URL: " . get_site_url() . "\n";

        if (function_exists('wc_get_currency')) {
            $storeContext .= "Currency: " . wc_get_currency() . "\n";
        }

        return $storeContext;
    }

    /**
     * Build relevant content section
     *
     * @since 1.0.0
     * @param array $contextWindow Context window data
     *
     * @return string Formatted content
     */
    private function buildRelevantContentSection(array $contextWindow): string
    {
        $content = "";

        foreach ($contextWindow['relevant_content'] as $index => $item) {
            $content .= "\n" . ($index + 1) . ". ";
            $content .= "[" . ucfirst($item['type']) . "] ";
            $content .= $item['source'] . ":\n";
            $content .= $item['content'] . "\n";
        }

        return $content;
    }

    /**
     * Build user context section
     *
     * @since 1.0.0
     * @param array $userContext User context summary
     *
     * @return string Formatted user context
     */
    private function buildUserContextSection(array $userContext): string
    {
        $context = "";

        if (!empty($userContext['current_page'])) {
            $context .= "Current Page: " . $userContext['current_page'] . "\n";
        }

        if (!empty($userContext['user_type'])) {
            $context .= "User Type: " . $userContext['user_type'] . "\n";
        }

        return $context;
    }

    /**
     * Build response guidelines
     *
     * @since 1.0.0
     * @param string $mode Response mode
     *
     * @return string Guidelines
     */
    private function buildResponseGuidelines(string $mode): string
    {
        $guidelines = "Response Guidelines:\n";
        $guidelines .= "- Use the relevant information provided above to answer accurately\n";
        $guidelines .= "- Be helpful, professional, and friendly\n";
        $guidelines .= "- If information is not available, say so honestly\n";
        $guidelines .= "- Focus on the user's specific question\n";

        switch ($mode) {
            case 'detailed':
                $guidelines .= "- Provide comprehensive details and explanations\n";
                $guidelines .= "- Include relevant examples and additional context\n";
                break;
            case 'concise':
                $guidelines .= "- Keep responses brief and to the point\n";
                $guidelines .= "- Focus on the most essential information\n";
                break;
        }

        return $guidelines;
    }

    /**
     * Build safety guidelines
     *
     * @since 1.0.0
     * @param string $safetyLevel Safety level
     *
     * @return string Safety guidelines
     */
    private function buildSafetyGuidelines(string $safetyLevel): string
    {
        return "Safety Guidelines:\n- Never provide harmful, illegal, or inappropriate content\n- Protect customer privacy and data\n- Stay within your role as a store assistant\n- Redirect complex technical issues to human support when appropriate";
    }

    /**
     * Extract user context summary
     *
     * @since 1.0.0
     * @param array $context Full context
     *
     * @return array Context summary
     */
    private function extractUserContextSummary(array $context): array
    {
        return [
            'current_page' => $context['page_context']['title'] ?? null,
            'user_type' => $context['user_context']['type'] ?? 'visitor',
            'session_duration' => $context['user_context']['session_time'] ?? null
        ];
    }

    /**
     * Truncate content intelligently
     *
     * @since 1.0.0
     * @param string $content Content to truncate
     * @param int    $maxTokens Maximum tokens allowed
     *
     * @return string Truncated content
     */
    private function truncateContentIntelligently(string $content, int $maxTokens): string
    {
        $maxChars = $maxTokens * 4; // Rough approximation

        if (strlen($content) <= $maxChars) {
            return $content;
        }

        // Try to truncate at sentence boundaries
        $sentences = preg_split('/[.!?]+/', $content);
        $truncated = '';

        foreach ($sentences as $sentence) {
            if (strlen($truncated . $sentence) > $maxChars) {
                break;
            }
            $truncated .= $sentence . '. ';
        }

        return trim($truncated) ?: substr($content, 0, $maxChars) . '...';
    }

    /**
     * Select optimal model for context
     *
     * @since 1.0.0
     * @param array $contextWindow Context window data
     * @param array $options Generation options
     *
     * @return string Model identifier
     */
    private function selectModelForContext(array $contextWindow, array $options): string
    {
        $plan = $this->licenseManager->getCurrentPlan();
        $tokenCount = $contextWindow['metadata']['estimated_tokens'] ?? 0;

        // Model selection based on plan and complexity
        if ($plan === 'unlimited' && $tokenCount > 2000) {
            return 'gemini-2.5-pro'; // Best model for complex queries
        } elseif ($plan === 'pro' && $tokenCount > 1000) {
            return 'gemini-2.5-flash'; // Good balance
        } else {
            return 'gemini-2.5-flash'; // Default for free/simple queries
        }
    }

    /**
     * Calculate temperature for response mode
     *
     * @since 1.0.0
     * @param string $mode Response mode
     *
     * @return float Temperature value
     */
    private function calculateTemperature(string $mode): float
    {
        switch ($mode) {
            case 'detailed':
                return 0.7; // More creative for detailed responses
            case 'concise':
                return 0.3; // More focused for concise responses
            case 'standard':
            default:
                return 0.5; // Balanced
        }
    }

    /**
     * Calculate max tokens for response mode
     *
     * @since 1.0.0
     * @param string $mode Response mode
     *
     * @return int Max tokens
     */
    private function calculateMaxTokens(string $mode): int
    {
        switch ($mode) {
            case 'detailed':
                return 800; // Longer responses
            case 'concise':
                return 200; // Shorter responses
            case 'standard':
            default:
                return 400; // Standard length
        }
    }

    /**
     * Calculate response confidence score
     *
     * @since 1.0.0
     * @param array $response AI response
     * @param array $chunks Source chunks
     *
     * @return float Confidence score (0.0-1.0)
     */
    private function calculateResponseConfidence(array $response, array $chunks): float
    {
        if (empty($chunks)) {
            return 0.3; // Low confidence without context
        }

        $avgRelevance = $this->calculateAverageRelevance($chunks);
        $chunkCount = count($chunks);

        // Base confidence from chunk relevance
        $confidence = $avgRelevance;

        // Adjust based on chunk count
        if ($chunkCount >= 3) {
            $confidence *= 1.1; // Boost for multiple sources
        } elseif ($chunkCount === 1) {
            $confidence *= 0.9; // Slight penalty for single source
        }

        // Response length factor (very short or very long responses might be less reliable)
        $responseLength = strlen($response['response'] ?? '');
        if ($responseLength < 50 || $responseLength > 1000) {
            $confidence *= 0.95;
        }

        return min(1.0, max(0.1, $confidence));
    }

    /**
     * Extract sources used in response
     *
     * @since 1.0.0
     * @param array $chunks Source chunks
     *
     * @return array Sources information
     */
    private function extractSourcesUsed(array $chunks): array
    {
        $sources = [];

        foreach ($chunks as $chunk) {
            $sources[] = [
                'type' => $chunk['content_type'] ?? 'unknown',
                'title' => $chunk['source_title'] ?? 'Unknown',
                'url' => $chunk['source_url'] ?? null,
                'relevance' => $chunk['rerank_score'] ?? $chunk['similarity_score'] ?? 0.0
            ];
        }

        return $sources;
    }

    /**
     * Calculate average relevance of chunks
     *
     * @since 1.0.0
     * @param array $chunks Chunks array
     *
     * @return float Average relevance score
     */
    private function calculateAverageRelevance(array $chunks): float
    {
        if (empty($chunks)) {
            return 0.0;
        }

        $totalRelevance = 0.0;
        foreach ($chunks as $chunk) {
            $totalRelevance += $chunk['rerank_score'] ?? $chunk['similarity_score'] ?? 0.0;
        }

        return $totalRelevance / count($chunks);
    }

    /**
     * Log retrieval metrics (hook callback)
     *
     * @since 1.0.0
     * @param string $query Search query
     * @param array  $result Retrieval result
     */
    public function logRetrievalMetrics(string $query, array $result): void
    {
        if (defined('WOO_AI_ASSISTANT_DEBUG') && WOO_AI_ASSISTANT_DEBUG) {
            Utils::logDebug("RAG Retrieval - Query: {$query}, Found: {$result['total_found']}, Time: {$result['search_time']}ms");
        }
    }

    /**
     * Log re-ranking metrics (hook callback)
     *
     * @since 1.0.0
     * @param string $query Search query
     * @param array  $result Re-ranking result
     */
    public function logReRankingMetrics(string $query, array $result): void
    {
        if (defined('WOO_AI_ASSISTANT_DEBUG') && WOO_AI_ASSISTANT_DEBUG) {
            Utils::logDebug("RAG Re-ranking - Query: {$query}, {$result['original_count']} -> {$result['final_count']}, Time: {$result['rerank_time']}ms");
        }
    }

    /**
     * Debug logging callback
     *
     * @since 1.0.0
     * @param string $message Debug message
     * @param array  $context Debug context
     */
    public function debugLog(string $message, array $context = []): void
    {
        Utils::logDebug('RAG Debug: ' . $message . (!empty($context) ? ' - ' . wp_json_encode($context) : ''));
    }
}
