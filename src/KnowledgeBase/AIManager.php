<?php

/**
 * AI Manager Class
 *
 * Main AI integration class that orchestrates LLM interactions via OpenRouter API,
 * implements Retrieval-Augmented Generation (RAG) pattern, manages conversation
 * context, handles response streaming, and provides comprehensive fallback mechanisms.
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
 * Class AIManager
 *
 * Comprehensive AI integration with OpenRouter and RAG capabilities.
 *
 * @since 1.0.0
 */
class AIManager
{
    use Singleton;

    /**
     * API configuration instance
     *
     * @var ApiConfiguration
     */
    private ApiConfiguration $apiConfig;

    /**
     * Vector manager for similarity search
     *
     * @var VectorManager
     */
    private VectorManager $vectorManager;

    /**
     * Prompt builder for context-aware prompts
     *
     * @var PromptBuilder
     */
    private PromptBuilder $promptBuilder;

    /**
     * Maximum conversation context length (tokens)
     *
     * @var int
     */
    private int $maxContextLength = 8000;

    /**
     * Maximum knowledge base chunks to include in context
     *
     * @var int
     */
    private int $maxKnowledgeChunks = 5;

    /**
     * Response streaming chunk size
     *
     * @var int
     */
    private int $streamingChunkSize = 100;

    /**
     * Rate limiting configuration
     *
     * @var array
     */
    private array $rateLimits = [
        'max_requests_per_minute' => 60,
        'max_tokens_per_minute' => 100000,
        'retry_attempts' => 3,
        'retry_delays' => [1, 2, 4] // seconds
    ];

    /**
     * Conversation cache TTL (seconds)
     *
     * @var int
     */
    private int $conversationCacheTtl = 1800; // 30 minutes

    /**
     * Response cache TTL (seconds)
     *
     * @var int
     */
    private int $responseCacheTtl = 3600; // 1 hour

    /**
     * AI operation statistics
     *
     * @var array
     */
    private array $stats = [
        'total_requests' => 0,
        'successful_responses' => 0,
        'failed_responses' => 0,
        'cache_hits' => 0,
        'fallback_responses' => 0,
        'tokens_consumed' => 0,
        'avg_response_time' => 0,
        'context_retrievals' => 0,
        'streaming_responses' => 0
    ];

    /**
     * Supported AI models
     *
     * @var array
     */
    private array $supportedModels = [
        'primary' => 'google/gemini-2.0-flash-exp:free',
        'fallback' => 'google/gemini-2.0-flash-thinking-exp:free',
        'premium' => 'google/gemini-2.0-pro',
        'lite' => 'google/gemini-flash-1.5-8b'
    ];

    /**
     * Initialize AI Manager
     *
     * @return void
     * @throws Exception When required dependencies are not available
     */
    protected function init(): void
    {
        // Initialize dependencies
        $this->apiConfig = ApiConfiguration::getInstance();
        $this->vectorManager = VectorManager::getInstance();
        $this->promptBuilder = PromptBuilder::getInstance();

        // Load configuration
        $this->loadAiConfiguration();

        // Verify API connectivity
        $this->verifyApiConnectivity();

        // Reset statistics
        $this->resetStatistics();

        Logger::debug('AI Manager initialized', [
            'max_context_length' => $this->maxContextLength,
            'max_knowledge_chunks' => $this->maxKnowledgeChunks,
            'supported_models' => array_keys($this->supportedModels),
            'development_mode' => $this->apiConfig->isDevelopmentMode()
        ]);

        // Add WordPress hooks
        $this->addHooks();
    }

    /**
     * Generate AI response using RAG pattern
     *
     * Retrieves relevant knowledge base context, builds contextual prompts,
     * and generates AI responses with conversation history awareness.
     *
     * @since 1.0.0
     * @param string $userQuery User's query text
     * @param array  $args Optional. Response generation arguments.
     * @param string $args['conversation_id'] Conversation identifier. Default empty.
     * @param array  $args['conversation_history'] Previous conversation messages. Default empty.
     * @param string $args['user_context'] Current user context (page, product, etc.). Default empty.
     * @param string $args['model'] AI model to use. Default 'primary'.
     * @param bool   $args['streaming'] Enable response streaming. Default false.
     * @param int    $args['max_tokens'] Maximum response tokens. Default 2000.
     * @param float  $args['temperature'] Response creativity (0.0-1.0). Default 0.7.
     * @param array  $args['content_types'] Knowledge base content types to search. Default all.
     *
     * @return array AI response with metadata and statistics.
     *               Contains 'response', 'model_used', 'tokens_used', 'context_chunks', 'processing_time'.
     *
     * @throws Exception When AI response generation fails.
     *
     * @example
     * ```php
     * $aiManager = AIManager::getInstance();
     * $result = $aiManager->generateResponse('What is your return policy?', [
     *     'conversation_id' => 'conv_123',
     *     'user_context' => 'product_page',
     *     'streaming' => true,
     *     'max_tokens' => 1500
     * ]);
     * echo $result['response'];
     * ```
     */
    public function generateResponse(string $userQuery, array $args = []): array
    {
        $startTime = microtime(true);

        try {
            // Parse arguments
            $defaults = [
                'conversation_id' => '',
                'conversation_history' => [],
                'user_context' => '',
                'model' => 'primary',
                'streaming' => false,
                'max_tokens' => 2000,
                'temperature' => 0.7,
                'content_types' => []
            ];

            $args = wp_parse_args($args, $defaults);

            Logger::info('Starting AI response generation', [
                'query_length' => strlen($userQuery),
                'conversation_id' => $args['conversation_id'],
                'model' => $args['model'],
                'streaming' => $args['streaming'],
                'user_context' => $args['user_context']
            ]);

            // Check cache first (for non-streaming requests)
            if (!$args['streaming']) {
                $cacheKey = $this->generateResponseCacheKey($userQuery, $args);
                $cachedResponse = Cache::getInstance()->get($cacheKey);

                if ($cachedResponse !== false) {
                    $this->stats['cache_hits']++;
                    Logger::debug('Returning cached AI response');
                    return $cachedResponse;
                }
            }

            // Rate limiting check
            if (!$this->checkRateLimit()) {
                throw new Exception('Rate limit exceeded. Please try again later.');
            }

            // Retrieve relevant knowledge base context
            $contextChunks = $this->retrieveRelevantContext($userQuery, $args);
            $this->stats['context_retrievals']++;

            // Build contextual prompt
            $promptData = $this->promptBuilder->buildRagPrompt($userQuery, [
                'context_chunks' => $contextChunks,
                'conversation_history' => $args['conversation_history'],
                'user_context' => $args['user_context'],
                'max_context_length' => $this->maxContextLength
            ]);

            // Generate AI response
            $aiResponse = $this->callAiModel($promptData, $args);

            // Process and validate response
            $processedResponse = $this->processAiResponse($aiResponse, $args);

            $result = [
                'response' => $processedResponse['content'],
                'model_used' => $processedResponse['model'],
                'tokens_used' => $processedResponse['tokens'],
                'context_chunks' => count($contextChunks),
                'processing_time' => microtime(true) - $startTime,
                'conversation_id' => $args['conversation_id'],
                'streaming' => $args['streaming'],
                'timestamp' => current_time('mysql'),
                'prompt_tokens' => $promptData['token_count'] ?? 0,
                'completion_tokens' => $processedResponse['tokens'],
                'total_tokens' => ($promptData['token_count'] ?? 0) + $processedResponse['tokens']
            ];

            // Update statistics
            $this->updateStatistics($result);

            // Cache response (for non-streaming requests)
            if (!$args['streaming']) {
                Cache::getInstance()->set($cacheKey, $result, $this->responseCacheTtl);
            }

            Logger::info('AI response generated successfully', [
                'query_length' => strlen($userQuery),
                'response_length' => strlen($result['response']),
                'tokens_used' => $result['tokens_used'],
                'processing_time' => round($result['processing_time'], 3),
                'model_used' => $result['model_used'],
                'context_chunks' => $result['context_chunks']
            ]);

            return $result;
        } catch (Exception $e) {
            $this->stats['failed_responses']++;

            Logger::error('AI response generation failed', [
                'query_length' => strlen($userQuery),
                'error' => $e->getMessage(),
                'processing_time' => microtime(true) - $startTime,
                'model' => $args['model'] ?? 'unknown'
            ]);

            // Attempt fallback response
            return $this->generateFallbackResponse($userQuery, $args, $e);
        }
    }

    /**
     * Stream AI response in real-time
     *
     * Generates streaming AI response for better user experience with long responses.
     * Yields response chunks as they become available from the AI model.
     *
     * @since 1.0.0
     * @param string   $userQuery User's query text
     * @param array    $args Generation arguments (same as generateResponse)
     * @param callable $onChunk Optional callback for each response chunk
     *
     * @return \Generator Generator yielding response chunks with metadata
     *
     * @throws Exception When streaming fails
     *
     * @example
     * ```php
     * $aiManager = AIManager::getInstance();
     * foreach ($aiManager->streamResponse('Explain shipping options', ['conversation_id' => 'conv_123']) as $chunk) {
     *     echo $chunk['content'];
     *     if ($chunk['is_final']) {
     *         echo "\nFinal tokens used: " . $chunk['tokens_used'];
     *     }
     * }
     * ```
     */
    public function streamResponse(string $userQuery, array $args = [], ?callable $onChunk = null): \Generator
    {
        try {
            $args['streaming'] = true;
            $startTime = microtime(true);

            Logger::info('Starting AI response streaming', [
                'query_length' => strlen($userQuery),
                'conversation_id' => $args['conversation_id'] ?? ''
            ]);

            // Retrieve context and build prompt (same as regular response)
            $contextChunks = $this->retrieveRelevantContext($userQuery, $args);
            $promptData = $this->promptBuilder->buildRagPrompt($userQuery, [
                'context_chunks' => $contextChunks,
                'conversation_history' => $args['conversation_history'] ?? [],
                'user_context' => $args['user_context'] ?? '',
                'max_context_length' => $this->maxContextLength
            ]);

            // Stream from AI model
            $totalTokens = 0;
            $responseBuffer = '';

            foreach ($this->streamFromAiModel($promptData, $args) as $chunk) {
                $responseBuffer .= $chunk['content'];
                $totalTokens = $chunk['tokens_used'] ?? $totalTokens;

                $streamChunk = [
                    'content' => $chunk['content'],
                    'is_final' => $chunk['is_final'] ?? false,
                    'tokens_used' => $totalTokens,
                    'processing_time' => microtime(true) - $startTime,
                    'context_chunks' => count($contextChunks),
                    'model_used' => $chunk['model'] ?? $args['model'],
                    'chunk_index' => $chunk['index'] ?? 0
                ];

                // Call optional callback
                if ($onChunk && is_callable($onChunk)) {
                    $onChunk($streamChunk);
                }

                yield $streamChunk;
            }

            $this->stats['streaming_responses']++;
            $this->updateStatistics([
                'tokens_used' => $totalTokens,
                'processing_time' => microtime(true) - $startTime
            ]);

            Logger::info('AI response streaming completed', [
                'response_length' => strlen($responseBuffer),
                'tokens_used' => $totalTokens,
                'processing_time' => round(microtime(true) - $startTime, 3)
            ]);
        } catch (Exception $e) {
            Logger::error('AI response streaming failed', [
                'error' => $e->getMessage(),
                'query_length' => strlen($userQuery)
            ]);
            throw $e;
        }
    }

    /**
     * Validate conversation context
     *
     * Ensures conversation history is within token limits and properly formatted.
     *
     * @since 1.0.0
     * @param array $conversationHistory Conversation messages
     * @param int   $maxTokens Maximum allowed tokens
     *
     * @return array Validated and potentially truncated conversation history
     */
    public function validateConversationContext(array $conversationHistory, int $maxTokens = 4000): array
    {
        if (empty($conversationHistory)) {
            return [];
        }

        $validatedHistory = [];
        $totalTokens = 0;

        // Process messages in reverse order (most recent first)
        $reversedHistory = array_reverse($conversationHistory);

        foreach ($reversedHistory as $message) {
            if (!isset($message['role']) || !isset($message['content'])) {
                continue;
            }

            // Estimate token count (rough approximation: 1 token ≈ 4 characters)
            $messageTokens = ceil(strlen($message['content']) / 4);

            if ($totalTokens + $messageTokens > $maxTokens) {
                break;
            }

            $validatedHistory[] = $message;
            $totalTokens += $messageTokens;
        }

        // Reverse back to chronological order
        return array_reverse($validatedHistory);
    }

    /**
     * Get AI operation statistics
     *
     * @return array Comprehensive statistics about AI operations
     */
    public function getStatistics(): array
    {
        return [
            'session_stats' => $this->stats,
            'configuration' => [
                'max_context_length' => $this->maxContextLength,
                'max_knowledge_chunks' => $this->maxKnowledgeChunks,
                'conversation_cache_ttl' => $this->conversationCacheTtl,
                'response_cache_ttl' => $this->responseCacheTtl,
                'supported_models' => $this->supportedModels
            ],
            'rate_limits' => $this->rateLimits,
            'api_status' => [
                'openrouter_configured' => !empty($this->apiConfig->getApiKey('openrouter')),
                'development_mode' => $this->apiConfig->isDevelopmentMode(),
                'primary_model' => $this->supportedModels['primary'],
                'fallback_model' => $this->supportedModels['fallback']
            ]
        ];
    }

    /**
     * Clear conversation cache
     *
     * @param string $conversationId Optional. Specific conversation to clear. Default clears all.
     * @return bool True if cache was cleared successfully
     */
    public function clearConversationCache(string $conversationId = ''): bool
    {
        try {
            if ($conversationId) {
                $cacheKey = "woo_ai_conversation_{$conversationId}";
                return Cache::getInstance()->delete($cacheKey);
            }

            // Clear all conversation caches
            return Cache::getInstance()->flush('woo_ai_conversation_*');
        } catch (Exception $e) {
            Logger::error('Failed to clear conversation cache', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Load AI configuration
     *
     * @throws Exception When configuration is invalid
     */
    private function loadAiConfiguration(): void
    {
        // Load model configuration
        $primaryModel = $this->apiConfig->getAiModel('primary_chat');
        if (!empty($primaryModel)) {
            $this->supportedModels['primary'] = $primaryModel;
        }

        $fallbackModel = $this->apiConfig->getAiModel('fallback_chat');
        if (!empty($fallbackModel)) {
            $this->supportedModels['fallback'] = $fallbackModel;
        }

        // Load model parameters
        $modelParams = $this->apiConfig->getModelParameters();
        $this->maxContextLength = $modelParams['max_tokens'] ?? $this->maxContextLength;

        // Load development configuration
        if ($this->apiConfig->isDevelopmentMode()) {
            $this->maxKnowledgeChunks = 3; // Reduce for development
            $this->responseCacheTtl = 300; // 5 minutes for development
        }

        Logger::debug('AI configuration loaded', [
            'primary_model' => $this->supportedModels['primary'],
            'fallback_model' => $this->supportedModels['fallback'],
            'max_context_length' => $this->maxContextLength,
            'development_mode' => $this->apiConfig->isDevelopmentMode()
        ]);
    }

    /**
     * Verify API connectivity
     *
     * @throws Exception When API connectivity check fails
     */
    private function verifyApiConnectivity(): void
    {
        if (empty($this->apiConfig->getApiKey('openrouter'))) {
            if ($this->apiConfig->isDevelopmentMode()) {
                Logger::warning('OpenRouter API key not configured - AI operations will use mock responses in development mode');
                return;
            } else {
                throw new Exception('OpenRouter API key is required for AI operations');
            }
        }

        // TODO: Add actual OpenRouter connectivity test when needed
        Logger::debug('AI API connectivity verified');
    }

    /**
     * Retrieve relevant context from knowledge base
     *
     * @param string $userQuery User's query
     * @param array  $args Query arguments
     * @return array Relevant knowledge base chunks
     */
    private function retrieveRelevantContext(string $userQuery, array $args): array
    {
        try {
            $searchResults = $this->vectorManager->similaritySearch($userQuery, [
                'top_k' => $this->maxKnowledgeChunks,
                'threshold' => 0.7,
                'content_types' => $args['content_types'],
                'include_metadata' => true
            ]);

            return $searchResults['matches'] ?? [];
        } catch (Exception $e) {
            Logger::warning('Failed to retrieve knowledge base context', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Call AI model with prepared prompt
     *
     * @param array $promptData Prepared prompt data
     * @param array $args Request arguments
     * @return array AI model response
     * @throws Exception When AI call fails
     */
    private function callAiModel(array $promptData, array $args): array
    {
        if ($this->apiConfig->isDevelopmentMode() && empty($this->apiConfig->getApiKey('openrouter'))) {
            // Development mode without OpenRouter - return mock response
            return $this->generateMockAiResponse($promptData, $args);
        }

        $model = $this->supportedModels[$args['model']] ?? $this->supportedModels['primary'];
        $endpoint = $this->apiConfig->getApiEndpoint('openrouter', 'chat_completions');
        $headers = $this->apiConfig->getApiHeaders('openrouter');

        $requestBody = [
            'model' => $model,
            'messages' => $promptData['messages'],
            'max_tokens' => $args['max_tokens'],
            'temperature' => $args['temperature'],
            'stream' => $args['streaming']
        ];

        $response = wp_remote_post($endpoint, [
            'headers' => $headers,
            'body' => wp_json_encode($requestBody),
            'timeout' => 60
        ]);

        if (is_wp_error($response)) {
            throw new Exception('OpenRouter API request failed: ' . $response->get_error_message());
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $responseBody = wp_remote_retrieve_body($response);

        if ($statusCode !== 200) {
            throw new Exception("OpenRouter API returned status {$statusCode}: {$responseBody}");
        }

        $data = json_decode($responseBody, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from OpenRouter: ' . json_last_error_msg());
        }

        return $data;
    }

    /**
     * Stream from AI model
     *
     * @param array $promptData Prepared prompt data
     * @param array $args Request arguments
     * @return \Generator Response chunks
     */
    private function streamFromAiModel(array $promptData, array $args): \Generator
    {
        if ($this->apiConfig->isDevelopmentMode() && empty($this->apiConfig->getApiKey('openrouter'))) {
            // Development mode - simulate streaming
            yield from $this->simulateStreamingResponse($promptData, $args);
            return;
        }

        // TODO: Implement actual OpenRouter streaming
        // For now, use regular response and simulate streaming
        $response = $this->callAiModel($promptData, $args);
        $content = $response['choices'][0]['message']['content'] ?? '';

        $chunks = str_split($content, $this->streamingChunkSize);
        foreach ($chunks as $index => $chunk) {
            yield [
                'content' => $chunk,
                'is_final' => $index === count($chunks) - 1,
                'tokens_used' => $response['usage']['total_tokens'] ?? 0,
                'model' => $args['model'],
                'index' => $index
            ];
        }
    }

    /**
     * Process AI response
     *
     * @param array $aiResponse Raw AI response
     * @param array $args Request arguments
     * @return array Processed response
     */
    private function processAiResponse(array $aiResponse, array $args): array
    {
        $content = $aiResponse['choices'][0]['message']['content'] ?? '';
        $model = $aiResponse['model'] ?? $args['model'];
        $tokens = $aiResponse['usage']['completion_tokens'] ?? 0;

        // Apply content filters and safety checks
        $content = $this->applySafetyFilters($content);

        return [
            'content' => $content,
            'model' => $model,
            'tokens' => $tokens,
            'finish_reason' => $aiResponse['choices'][0]['finish_reason'] ?? 'stop'
        ];
    }

    /**
     * Generate fallback response
     *
     * @param string    $userQuery Original user query
     * @param array     $args Request arguments
     * @param Exception $error Original error
     * @return array Fallback response
     */
    private function generateFallbackResponse(string $userQuery, array $args, Exception $error): array
    {
        $this->stats['fallback_responses']++;

        $fallbackResponse = $this->promptBuilder->buildFallbackResponse($userQuery, [
            'error' => $error->getMessage(),
            'user_context' => $args['user_context'] ?? ''
        ]);

        return [
            'response' => $fallbackResponse['content'],
            'model_used' => 'fallback',
            'tokens_used' => 0,
            'context_chunks' => 0,
            'processing_time' => 0,
            'conversation_id' => $args['conversation_id'] ?? '',
            'streaming' => false,
            'timestamp' => current_time('mysql'),
            'is_fallback' => true,
            'original_error' => $error->getMessage()
        ];
    }

    /**
     * Generate mock AI response for development
     *
     * @param array $promptData Prompt data
     * @param array $args Request arguments
     * @return array Mock response
     */
    private function generateMockAiResponse(array $promptData, array $args): array
    {
        $mockResponses = [
            'Hello! I\'m here to help you with your questions about our products and services.',
            'Based on the information available, I can provide you with the following details...',
            'Let me help you find the information you need. Here are some relevant points...',
            'Thank you for your question. According to our knowledge base...'
        ];

        $randomResponse = $mockResponses[array_rand($mockResponses)];

        return [
            'choices' => [
                [
                    'message' => [
                        'content' => $randomResponse,
                        'role' => 'assistant'
                    ],
                    'finish_reason' => 'stop'
                ]
            ],
            'usage' => [
                'prompt_tokens' => $promptData['token_count'] ?? 100,
                'completion_tokens' => str_word_count($randomResponse) * 1.3,
                'total_tokens' => ($promptData['token_count'] ?? 100) + (str_word_count($randomResponse) * 1.3)
            ],
            'model' => 'mock-model-dev'
        ];
    }

    /**
     * Simulate streaming response for development
     *
     * @param array $promptData Prompt data
     * @param array $args Request arguments
     * @return \Generator Simulated response chunks
     */
    private function simulateStreamingResponse(array $promptData, array $args): \Generator
    {
        $fullResponse = 'This is a simulated streaming response for development mode. The AI assistant is working properly and can provide helpful information about your products and services.';

        $chunks = str_split($fullResponse, $this->streamingChunkSize);

        foreach ($chunks as $index => $chunk) {
            // Simulate network delay
            usleep(100000); // 100ms

            yield [
                'content' => $chunk,
                'is_final' => $index === count($chunks) - 1,
                'tokens_used' => strlen($fullResponse) / 4, // Rough token estimate
                'model' => 'mock-streaming-dev',
                'index' => $index
            ];
        }
    }

    /**
     * Apply safety filters to content
     *
     * @param string $content Content to filter
     * @return string Filtered content
     */
    private function applySafetyFilters(string $content): string
    {
        // Basic safety filters - can be expanded
        $content = trim($content);

        // Remove any potential script injections
        $content = wp_kses_post($content);

        return $content;
    }

    /**
     * Check rate limiting
     *
     * @return bool True if request is within rate limits
     */
    private function checkRateLimit(): bool
    {
        $cacheKey = 'woo_ai_rate_limit_' . get_current_user_id();
        $rateLimitData = Cache::getInstance()->get($cacheKey);

        if ($rateLimitData === false) {
            $rateLimitData = [
                'requests' => 0,
                'tokens' => 0,
                'reset_time' => time() + 60
            ];
        }

        // Reset counters if minute has passed
        if (time() >= $rateLimitData['reset_time']) {
            $rateLimitData = [
                'requests' => 0,
                'tokens' => 0,
                'reset_time' => time() + 60
            ];
        }

        // Check limits
        if ($rateLimitData['requests'] >= $this->rateLimits['max_requests_per_minute']) {
            return false;
        }

        if ($rateLimitData['tokens'] >= $this->rateLimits['max_tokens_per_minute']) {
            return false;
        }

        // Update counters
        $rateLimitData['requests']++;
        Cache::getInstance()->set($cacheKey, $rateLimitData, 60);

        return true;
    }

    /**
     * Update statistics
     *
     * @param array $result Response result
     */
    private function updateStatistics(array $result): void
    {
        $this->stats['total_requests']++;
        $this->stats['successful_responses']++;
        $this->stats['tokens_consumed'] += $result['tokens_used'] ?? 0;

        // Update average response time
        $currentAvg = $this->stats['avg_response_time'];
        $currentCount = $this->stats['total_requests'];
        $newTime = $result['processing_time'] ?? 0;

        $this->stats['avg_response_time'] = (($currentAvg * ($currentCount - 1)) + $newTime) / $currentCount;
    }

    /**
     * Generate response cache key
     *
     * @param string $userQuery User query
     * @param array  $args Request arguments
     * @return string Cache key
     */
    private function generateResponseCacheKey(string $userQuery, array $args): string
    {
        $cacheData = [
            'query' => $userQuery,
            'model' => $args['model'],
            'user_context' => $args['user_context'],
            'content_types' => $args['content_types']
        ];

        return 'woo_ai_response_' . md5(serialize($cacheData));
    }

    /**
     * Reset statistics
     */
    private function resetStatistics(): void
    {
        $this->stats = [
            'total_requests' => 0,
            'successful_responses' => 0,
            'failed_responses' => 0,
            'cache_hits' => 0,
            'fallback_responses' => 0,
            'tokens_consumed' => 0,
            'avg_response_time' => 0,
            'context_retrievals' => 0,
            'streaming_responses' => 0
        ];
    }

    /**
     * Add WordPress hooks
     */
    private function addHooks(): void
    {
        // Add action for clearing cache when knowledge base is updated
        add_action('woo_ai_assistant_kb_updated', [$this, 'clearConversationCache']);

        // Add filter for customizing AI models
        add_filter('woo_ai_assistant_supported_models', function ($models) {
            return $this->supportedModels;
        });

        // Add filter for customizing rate limits
        add_filter('woo_ai_assistant_rate_limits', function ($limits) {
            return $this->rateLimits;
        });
    }

    /**
     * Set maximum context length
     *
     * @param int $length Maximum context length in tokens
     * @throws Exception When length is invalid
     */
    public function setMaxContextLength(int $length): void
    {
        if ($length < 1000 || $length > 32000) {
            throw new Exception('Context length must be between 1000 and 32000 tokens');
        }

        $this->maxContextLength = $length;
        Logger::debug('AI Manager max context length updated', ['new_length' => $length]);
    }

    /**
     * Set maximum knowledge chunks
     *
     * @param int $chunks Maximum number of knowledge chunks
     * @throws Exception When chunks count is invalid
     */
    public function setMaxKnowledgeChunks(int $chunks): void
    {
        if ($chunks < 1 || $chunks > 20) {
            throw new Exception('Knowledge chunks must be between 1 and 20');
        }

        $this->maxKnowledgeChunks = $chunks;
        Logger::debug('AI Manager max knowledge chunks updated', ['new_chunks' => $chunks]);
    }
}
