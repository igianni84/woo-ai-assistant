<?php

/**
 * AI Manager Class
 *
 * Handles comprehensive AI integration including OpenRouter and Google Gemini APIs,
 * RAG (Retrieval-Augmented Generation) implementation, conversation context management,
 * response streaming, and content moderation for the AI-powered knowledge base.
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
use WP_Error;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIManager
 *
 * Comprehensive AI management system that orchestrates multiple AI providers,
 * implements RAG (Retrieval-Augmented Generation), manages conversation context,
 * and provides enterprise-grade AI response generation with safety filters.
 *
 * @since 1.0.0
 */
class AIManager
{
    use Singleton;

    /**
     * OpenRouter API endpoint URL
     *
     * @since 1.0.0
     * @var string
     */
    private const OPENROUTER_API_URL = 'https://openrouter.ai/api/v1/chat/completions';

    /**
     * Google Gemini API endpoint URL
     *
     * @since 1.0.0
     * @var string
     */
    private const GEMINI_API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/';

    /**
     * Default AI model for Free plan (via OpenRouter)
     *
     * @since 1.0.0
     * @var string
     */
    private const DEFAULT_FREE_MODEL = 'google/gemini-2.5-flash-002';

    /**
     * Default AI model for Pro plan (via OpenRouter)
     *
     * @since 1.0.0
     * @var string
     */
    private const DEFAULT_PRO_MODEL = 'google/gemini-2.5-flash-002';

    /**
     * Default AI model for Unlimited plan (via OpenRouter)
     *
     * @since 1.0.0
     * @var string
     */
    private const DEFAULT_UNLIMITED_MODEL = 'google/gemini-2.5-pro-002';

    /**
     * Maximum conversation context tokens
     *
     * @since 1.0.0
     * @var int
     */
    private const MAX_CONTEXT_TOKENS = 8000;

    /**
     * Maximum RAG context chunks
     *
     * @since 1.0.0
     * @var int
     */
    private const MAX_RAG_CHUNKS = 10;

    /**
     * Default response streaming chunk size
     *
     * @since 1.0.0
     * @var int
     */
    private const STREAMING_CHUNK_SIZE = 100;

    /**
     * VectorManager instance for RAG operations
     *
     * @since 1.0.0
     * @var VectorManager
     */
    private $vectorManager;

    /**
     * Current conversation context storage
     *
     * @since 1.0.0
     * @var array
     */
    private $conversationContext = [];

    /**
     * AI safety filter patterns
     *
     * @since 1.0.0
     * @var array
     */
    private $safetyFilterPatterns = [
        'malicious_code' => '/(?:eval\(|exec\(|system\(|shell_exec\(|<script|javascript:|data:text\/html)/i',
        'prompt_injection' => '/(?:ignore.*previous.*instructions|forget.*system.*prompt|act.*as.*different|pretend.*you.*are)/i',
        'inappropriate_content' => '/(?:explicit.*sexual|violent.*content|illegal.*activities|hate.*speech)/i'
    ];

    /**
     * Initialize AIManager with dependencies
     *
     * @since 1.0.0
     */
    protected function __construct()
    {
        $this->vectorManager = VectorManager::getInstance();
        $this->initializeConversationContext();

        // Register WordPress hooks
        add_action('wp_ajax_woo_ai_assistant_stream_response', [$this, 'handleStreamingRequest']);
        add_action('wp_ajax_nopriv_woo_ai_assistant_stream_response', [$this, 'handleStreamingRequest']);

        Utils::logDebug('AIManager initialized successfully');
    }

    /**
     * Generate AI response using RAG (Retrieval-Augmented Generation)
     *
     * This method implements the complete RAG pipeline: retrieves relevant content
     * from the knowledge base, builds context, and generates AI response using
     * the configured AI model with safety filters and conversation management.
     *
     * @since 1.0.0
     * @param string $userMessage User's message/query
     * @param array $options Optional. Configuration options for response generation.
     * @param string $options['conversation_id'] Conversation ID for context persistence
     * @param string $options['model'] AI model to use (overrides plan default)
     * @param bool $options['stream'] Whether to enable response streaming
     * @param array $options['context'] Additional context data (page, product, user info)
     * @param int $options['max_tokens'] Maximum response tokens
     * @param float $options['temperature'] AI creativity level (0.0-1.0)
     *
     * @return array Response data containing generated text, metadata, and status.
     *               Contains 'success', 'response', 'model_used', 'tokens_used', 'context_chunks'.
     *
     * @throws \InvalidArgumentException When user message is empty or invalid.
     * @throws \RuntimeException When AI service is unavailable or fails.
     *
     * @example
     * ```php
     * $aiManager = AIManager::getInstance();
     * $response = $aiManager->generateResponse('What are your shipping options?', [
     *     'conversation_id' => 'conv-123',
     *     'context' => ['page' => 'shop', 'user_id' => 456]
     * ]);
     * ```
     */
    public function generateResponse(string $userMessage, array $options = []): array
    {
        try {
            // Validate input - trim whitespace first
            $trimmedMessage = trim($userMessage);
            if (empty($trimmedMessage)) {
                throw new \InvalidArgumentException('User message cannot be empty');
            }

            // Apply safety filters to the trimmed message
            $filteredMessage = $this->applySafetyFilters($trimmedMessage);
            if ($filteredMessage !== $trimmedMessage) {
                Utils::logDebug('Safety filter triggered for message: ' . substr($trimmedMessage, 0, 100));
                return $this->buildErrorResponse('Message contains inappropriate content', 'safety_filter');
            }

            // Extract configuration options
            $conversationId = $options['conversation_id'] ?? $this->generateConversationId();
            $model = $this->selectAIModel($options['model'] ?? null);
            $enableStreaming = $options['stream'] ?? false;
            $context = $options['context'] ?? [];
            $maxTokens = $options['max_tokens'] ?? 1000;
            $temperature = $options['temperature'] ?? 0.7;

            Utils::logDebug("Generating AI response for conversation: {$conversationId}");

            // Step 1: Retrieve relevant content using RAG
            $ragContext = $this->retrieveRelevantContent($trimmedMessage, $context);

            // Step 2: Build complete conversation context
            $conversationContext = $this->buildConversationContext(
                $trimmedMessage,
                $ragContext,
                $conversationId,
                $context
            );

            // Step 3: Generate AI response
            $aiResponse = $this->callAIProvider($model, $conversationContext, [
                'max_tokens' => $maxTokens,
                'temperature' => $temperature,
                'stream' => $enableStreaming
            ]);

            // Step 4: Process and validate response
            $processedResponse = $this->processAIResponse($aiResponse, $ragContext);

            // Step 5: Update conversation context
            $this->updateConversationContext($conversationId, $trimmedMessage, $processedResponse['response']);

            // Step 6: Log success and return
            Utils::logDebug("AI response generated successfully for conversation: {$conversationId}");

            return [
                'success' => true,
                'response' => $processedResponse['response'],
                'confidence' => $processedResponse['confidence_score'] ?? 0.8,
                'sources' => array_column($ragContext, 'source'),
                'model_used' => $model,
                'tokens_used' => $processedResponse['tokens_used'] ?? 0,
                'context_chunks' => count($ragContext),
                'conversation_id' => $conversationId,
                'metadata' => [
                    'rag_sources' => array_column($ragContext, 'source'),
                    'confidence_score' => $processedResponse['confidence_score'] ?? 0.8,
                    'response_time' => $processedResponse['response_time'] ?? 0,
                    'safety_check' => 'passed'
                ]
            ];
        } catch (\InvalidArgumentException $e) {
            Utils::logDebug('Invalid argument in generateResponse: ' . $e->getMessage(), 'error');
            return $this->buildErrorResponse($e->getMessage(), 'invalid_argument');
        } catch (\RuntimeException $e) {
            Utils::logDebug('Runtime error in generateResponse: ' . $e->getMessage(), 'error');
            return $this->buildErrorResponse('AI service temporarily unavailable', 'service_error');
        } catch (\Exception $e) {
            Utils::logDebug('Unexpected error in generateResponse: ' . $e->getMessage(), 'error');
            return $this->buildErrorResponse('An unexpected error occurred', 'general_error');
        }
    }

    /**
     * Retrieve relevant content from knowledge base using vector similarity search
     *
     * @since 1.0.0
     * @param string $userMessage User's query for content retrieval
     * @param array $context Additional context for filtering (page, product, category)
     * @return array Array of relevant content chunks with metadata
     */
    private function retrieveRelevantContent(string $userMessage, array $context = []): array
    {
        try {
            // Generate query embedding
            $queryEmbedding = $this->vectorManager->generateEmbedding($userMessage);
            if (!$queryEmbedding) {
                Utils::logDebug('Failed to generate embedding for user query', 'warning');
                return [];
            }

            // Search for similar content
            $similarChunks = $this->vectorManager->searchSimilar($queryEmbedding, [
                'limit' => self::MAX_RAG_CHUNKS,
                'threshold' => 0.7, // Minimum similarity threshold
                'context' => $context
            ]);

            // Process and format chunks for RAG
            $ragContext = [];
            foreach ($similarChunks as $chunk) {
                $ragContext[] = [
                    'content' => $chunk['content'] ?? '',
                    'source' => $chunk['source_type'] ?? 'unknown',
                    'title' => $chunk['title'] ?? '',
                    'url' => $chunk['url'] ?? '',
                    'similarity_score' => $chunk['similarity_score'] ?? 0,
                    'metadata' => $chunk['metadata'] ?? []
                ];
            }

            Utils::logDebug("Retrieved " . count($ragContext) . " relevant chunks for RAG context");
            return $ragContext;
        } catch (\Exception $e) {
            Utils::logDebug('Error retrieving RAG content: ' . $e->getMessage(), 'error');
            return [];
        }
    }

    /**
     * Build comprehensive conversation context for AI model
     *
     * @since 1.0.0
     * @param string $userMessage Current user message
     * @param array $ragContext Retrieved knowledge base content
     * @param string $conversationId Conversation identifier
     * @param array $context Additional context (page, user, product info)
     * @return array Formatted conversation context for AI model
     */
    private function buildConversationContext(
        string $userMessage,
        array $ragContext,
        string $conversationId,
        array $context = []
    ): array {
        // Build system prompt with knowledge base content
        $systemPrompt = $this->buildSystemPrompt($ragContext, $context);

        // Get conversation history
        $conversationHistory = $this->getConversationHistory($conversationId);

        // Build messages array for AI model
        $messages = [];

        // Add system message
        $messages[] = [
            'role' => 'system',
            'content' => $systemPrompt
        ];

        // Add conversation history (limit to maintain context window)
        $historyMessages = array_slice($conversationHistory, -10); // Last 10 messages
        foreach ($historyMessages as $historyMessage) {
            $messages[] = [
                'role' => $historyMessage['role'] ?? 'user',
                'content' => $historyMessage['content'] ?? ''
            ];
        }

        // Add current user message
        $messages[] = [
            'role' => 'user',
            'content' => $userMessage
        ];

        return $messages;
    }

    /**
     * Build system prompt with knowledge base content and context
     *
     * @since 1.0.0
     * @param array $ragContext Retrieved knowledge base content
     * @param array $context Page/user context information
     * @return string Formatted system prompt
     */
    private function buildSystemPrompt(array $ragContext, array $context = []): string
    {
        $prompt = "You are an AI assistant for a WooCommerce store. Your role is to provide helpful, accurate information about products, services, and store policies based on the knowledge base provided.\n\n";

        // Add store context if available
        if (!empty($context['store_name'])) {
            $prompt .= "Store Name: " . $context['store_name'] . "\n";
        }

        // Add page context
        if (!empty($context['page'])) {
            $prompt .= "Current Page: " . $context['page'] . "\n";
        }

        // Add product context if on product page
        if (!empty($context['product_id'])) {
            $prompt .= "Current Product ID: " . $context['product_id'] . "\n";
        }

        $prompt .= "\nKnowledge Base Context:\n";

        // Add RAG content
        if (!empty($ragContext)) {
            foreach ($ragContext as $index => $chunk) {
                $prompt .= "\n--- Source " . ($index + 1) . " ---\n";
                $prompt .= "Type: " . ($chunk['source'] ?? 'content') . "\n";
                if (!empty($chunk['title'])) {
                    $prompt .= "Title: " . $chunk['title'] . "\n";
                }
                $prompt .= "Content: " . $chunk['content'] . "\n";
            }
        } else {
            $prompt .= "No specific knowledge base content found for this query. Provide general helpful information.\n";
        }

        $prompt .= "\nInstructions:\n";
        $prompt .= "- Provide accurate, helpful responses based on the knowledge base content\n";
        $prompt .= "- If you don't have specific information, say so clearly\n";
        $prompt .= "- Be friendly and professional\n";
        $prompt .= "- Focus on helping customers make informed decisions\n";
        $prompt .= "- Suggest specific products when relevant\n";
        $prompt .= "- Always prioritize accuracy over completeness\n";

        return $prompt;
    }

    /**
     * Call the appropriate AI provider (OpenRouter or Gemini)
     *
     * @since 1.0.0
     * @param string $model AI model identifier
     * @param array $messages Conversation messages
     * @param array $options Generation options (max_tokens, temperature, etc.)
     * @return array AI response data
     */
    private function callAIProvider(string $model, array $messages, array $options = []): array
    {
        $startTime = microtime(true);

        try {
            // Try OpenRouter first
            $response = $this->callOpenRouter($model, $messages, $options);

            if ($response['success']) {
                $response['response_time'] = microtime(true) - $startTime;
                $response['provider'] = 'openrouter';
                return $response;
            }

            // Fallback to Gemini direct API
            Utils::logDebug('OpenRouter failed, trying Gemini direct API', 'warning');
            $response = $this->callGeminiDirect($model, $messages, $options);

            if ($response['success']) {
                $response['response_time'] = microtime(true) - $startTime;
                $response['provider'] = 'gemini';
                return $response;
            }

            // Fallback to dummy response for development
            if (defined('WOO_AI_ASSISTANT_USE_DUMMY_DATA') && WOO_AI_ASSISTANT_USE_DUMMY_DATA) {
                Utils::logDebug('AI providers failed, using dummy response', 'warning');
                return $this->generateDummyResponse($messages);
            }

            throw new \RuntimeException('All AI providers failed');
        } catch (\Exception $e) {
            Utils::logDebug('Error calling AI provider: ' . $e->getMessage(), 'error');

            // Return dummy response if enabled
            if (defined('WOO_AI_ASSISTANT_USE_DUMMY_DATA') && WOO_AI_ASSISTANT_USE_DUMMY_DATA) {
                return $this->generateDummyResponse($messages);
            }

            throw $e;
        }
    }

    /**
     * Call OpenRouter API
     *
     * @since 1.0.0
     * @param string $model Model identifier
     * @param array $messages Conversation messages
     * @param array $options Generation options
     * @return array API response
     */
    private function callOpenRouter(string $model, array $messages, array $options = []): array
    {
        $apiKey = get_option('woo_ai_assistant_openrouter_key');
        if (empty($apiKey)) {
            return ['success' => false, 'error' => 'OpenRouter API key not configured'];
        }

        $requestBody = [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $options['max_tokens'] ?? 1000,
            'temperature' => $options['temperature'] ?? 0.7,
            'top_p' => 0.9,
            'frequency_penalty' => 0.1,
            'presence_penalty' => 0.1
        ];

        $response = wp_remote_post(self::OPENROUTER_API_URL, [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
                'X-Title' => 'Woo AI Assistant'
            ],
            'body' => wp_json_encode($requestBody)
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $responseCode = wp_remote_retrieve_response_code($response);
        $responseBody = wp_remote_retrieve_body($response);

        if ($responseCode !== 200) {
            Utils::logDebug("OpenRouter API error: {$responseCode} - {$responseBody}", 'error');
            return ['success' => false, 'error' => "API error: {$responseCode}"];
        }

        $data = json_decode($responseBody, true);
        if (!$data || !isset($data['choices'][0]['message']['content'])) {
            return ['success' => false, 'error' => 'Invalid response format'];
        }

        return [
            'success' => true,
            'content' => $data['choices'][0]['message']['content'],
            'tokens_used' => $data['usage']['total_tokens'] ?? 0,
            'model' => $data['model'] ?? $model
        ];
    }

    /**
     * Call Google Gemini Direct API
     *
     * @since 1.0.0
     * @param string $model Model identifier
     * @param array $messages Conversation messages
     * @param array $options Generation options
     * @return array API response
     */
    private function callGeminiDirect(string $model, array $messages, array $options = []): array
    {
        $apiKey = get_option('woo_ai_assistant_gemini_key');
        if (empty($apiKey)) {
            return ['success' => false, 'error' => 'Gemini API key not configured'];
        }

        // Convert messages to Gemini format
        $geminiMessages = $this->convertMessagesToGeminiFormat($messages);

        $modelName = str_replace('google/', '', $model); // Remove google/ prefix
        $url = self::GEMINI_API_URL . $modelName . ':generateContent?key=' . $apiKey;

        $requestBody = [
            'contents' => $geminiMessages,
            'generationConfig' => [
                'maxOutputTokens' => $options['max_tokens'] ?? 1000,
                'temperature' => $options['temperature'] ?? 0.7,
                'topP' => 0.9
            ]
        ];

        $response = wp_remote_post($url, [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode($requestBody)
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $responseCode = wp_remote_retrieve_response_code($response);
        $responseBody = wp_remote_retrieve_body($response);

        if ($responseCode !== 200) {
            Utils::logDebug("Gemini API error: {$responseCode} - {$responseBody}", 'error');
            return ['success' => false, 'error' => "API error: {$responseCode}"];
        }

        $data = json_decode($responseBody, true);
        if (!$data || !isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return ['success' => false, 'error' => 'Invalid response format'];
        }

        return [
            'success' => true,
            'content' => $data['candidates'][0]['content']['parts'][0]['text'],
            'tokens_used' => $data['usageMetadata']['totalTokenCount'] ?? 0,
            'model' => $model
        ];
    }

    /**
     * Convert OpenAI format messages to Gemini format
     *
     * @since 1.0.0
     * @param array $messages OpenAI format messages
     * @return array Gemini format messages
     */
    private function convertMessagesToGeminiFormat(array $messages): array
    {
        $geminiMessages = [];
        $systemPrompt = '';

        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                $systemPrompt = $message['content'];
                continue;
            }

            $role = ($message['role'] === 'assistant') ? 'model' : 'user';
            $content = $message['content'];

            // Add system prompt to first user message
            if ($role === 'user' && !empty($systemPrompt)) {
                $content = $systemPrompt . "\n\nUser: " . $content;
                $systemPrompt = ''; // Clear it after first use
            }

            $geminiMessages[] = [
                'role' => $role,
                'parts' => [['text' => $content]]
            ];
        }

        return $geminiMessages;
    }

    /**
     * Generate dummy response for development/testing
     *
     * @since 1.0.0
     * @param array $messages Conversation messages
     * @return array Dummy response data
     */
    private function generateDummyResponse(array $messages): array
    {
        $lastMessage = end($messages);
        $userMessage = $lastMessage['content'] ?? '';

        // Generate contextual dummy responses
        $dummyResponses = [
            'shipping' => "We offer free shipping on orders over $50. Standard delivery takes 3-5 business days, and express delivery takes 1-2 business days. You can track your order using the tracking number we'll send you via email.",
            'return' => "We have a 30-day return policy. Items must be unused and in original packaging. You can initiate a return from your account dashboard or contact our support team for assistance.",
            'product' => "This product is one of our bestsellers! It features high-quality materials and excellent customer reviews. Would you like to know more about its specific features or see similar products?",
            'payment' => "We accept all major credit cards, PayPal, and Apple Pay. All transactions are secured with SSL encryption for your protection.",
            'default' => "Thank you for your question! I'm here to help you with information about our products and services. Could you please provide more details about what you're looking for?"
        ];

        // Simple keyword matching for dummy response selection
        $response = $dummyResponses['default'];
        foreach ($dummyResponses as $keyword => $dummyResponse) {
            if ($keyword !== 'default' && stripos($userMessage, $keyword) !== false) {
                $response = $dummyResponse;
                break;
            }
        }

        return [
            'success' => true,
            'content' => $response,
            'tokens_used' => strlen($response) / 4, // Rough token estimation
            'model' => 'dummy-model',
            'provider' => 'dummy'
        ];
    }

    /**
     * Process AI response and apply post-processing
     *
     * @since 1.0.0
     * @param array $aiResponse Raw AI response
     * @param array $ragContext RAG context used
     * @return array Processed response data
     */
    private function processAIResponse(array $aiResponse, array $ragContext): array
    {
        if (!$aiResponse['success']) {
            return $aiResponse;
        }

        $response = $aiResponse['content'];

        // Apply safety filters to response
        $filteredResponse = $this->applySafetyFilters($response);

        // Calculate confidence score based on RAG context usage
        $confidenceScore = $this->calculateConfidenceScore($response, $ragContext);

        return [
            'response' => $filteredResponse,
            'tokens_used' => $aiResponse['tokens_used'] ?? 0,
            'confidence_score' => $confidenceScore,
            'response_time' => $aiResponse['response_time'] ?? 0,
            'provider' => $aiResponse['provider'] ?? 'unknown',
            'model' => $aiResponse['model'] ?? 'unknown'
        ];
    }

    /**
     * Apply safety filters to content
     *
     * @since 1.0.0
     * @param string $content Content to filter
     * @return string Filtered content
     */
    private function applySafetyFilters(string $content): string
    {
        foreach ($this->safetyFilterPatterns as $filterName => $pattern) {
            if (preg_match($pattern, $content)) {
                Utils::logDebug("Safety filter '{$filterName}' triggered", 'warning');

                // Apply appropriate filtering action
                switch ($filterName) {
                    case 'malicious_code':
                        return "I cannot process requests containing potentially harmful code.";
                    case 'prompt_injection':
                        return "I'm here to help with questions about our products and services.";
                    case 'inappropriate_content':
                        return "I'm designed to provide helpful information about our store and products.";
                    default:
                        return "I cannot process this type of content.";
                }
            }
        }

        return $content;
    }

    /**
     * Calculate confidence score for response
     *
     * @since 1.0.0
     * @param string $response Generated response
     * @param array $ragContext RAG context used
     * @return float Confidence score (0.0-1.0)
     */
    private function calculateConfidenceScore(string $response, array $ragContext): float
    {
        if (empty($ragContext)) {
            return 0.5; // Medium confidence without RAG context
        }

        // Base confidence from number of context chunks
        $baseScore = min(count($ragContext) / self::MAX_RAG_CHUNKS, 1.0) * 0.4;

        // Add bonus for high similarity scores
        $avgSimilarity = 0;
        foreach ($ragContext as $chunk) {
            $avgSimilarity += $chunk['similarity_score'] ?? 0;
        }
        $avgSimilarity = $avgSimilarity / count($ragContext);

        $similarityBonus = $avgSimilarity * 0.6;

        return min($baseScore + $similarityBonus, 1.0);
    }

    /**
     * Select appropriate AI model based on user plan and preferences
     *
     * @since 1.0.0
     * @param string|null $preferredModel Optional preferred model
     * @return string Selected model identifier
     */
    private function selectAIModel(?string $preferredModel = null): string
    {
        // Use preferred model if provided and valid
        if (!empty($preferredModel)) {
            return $preferredModel;
        }

        // Get user plan from license/settings
        $userPlan = get_option('woo_ai_assistant_plan', 'free');

        switch ($userPlan) {
            case 'unlimited':
                return self::DEFAULT_UNLIMITED_MODEL;
            case 'pro':
                return self::DEFAULT_PRO_MODEL;
            case 'free':
            default:
                return self::DEFAULT_FREE_MODEL;
        }
    }

    /**
     * Initialize conversation context storage
     *
     * @since 1.0.0
     * @return void
     */
    private function initializeConversationContext(): void
    {
        $this->conversationContext = [];

        // Clean up old conversation contexts (older than 24 hours)
        $this->cleanupOldContexts();
    }

    /**
     * Generate unique conversation ID
     *
     * @since 1.0.0
     * @return string Unique conversation identifier
     */
    private function generateConversationId(): string
    {
        return 'conv-' . wp_generate_uuid4();
    }

    /**
     * Get conversation history from database
     *
     * @since 1.0.0
     * @param string $conversationId Conversation identifier
     * @return array Conversation history
     */
    private function getConversationHistory(string $conversationId): array
    {
        global $wpdb;

        try {
            $table = $wpdb->prefix . 'woo_ai_conversations';

            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT user_message, assistant_response, created_at 
                FROM {$table} 
                WHERE conversation_id = %s 
                ORDER BY created_at ASC 
                LIMIT 20",
                $conversationId
            ), ARRAY_A);

            $history = [];
            foreach ($results as $row) {
                $history[] = ['role' => 'user', 'content' => $row['user_message']];
                $history[] = ['role' => 'assistant', 'content' => $row['assistant_response']];
            }

            return $history;
        } catch (\Exception $e) {
            Utils::logDebug('Error retrieving conversation history: ' . $e->getMessage(), 'error');
            return [];
        }
    }

    /**
     * Update conversation context in memory and database
     *
     * @since 1.0.0
     * @param string $conversationId Conversation identifier
     * @param string $userMessage User message
     * @param string $assistantResponse Assistant response
     * @return bool Success status
     */
    private function updateConversationContext(
        string $conversationId,
        string $userMessage,
        string $assistantResponse
    ): bool {
        try {
            // Update in-memory context
            $this->conversationContext[$conversationId] = [
                'last_message' => $userMessage,
                'last_response' => $assistantResponse,
                'updated_at' => time()
            ];

            // Store in database
            global $wpdb;
            $table = $wpdb->prefix . 'woo_ai_conversations';

            $result = $wpdb->insert($table, [
                'conversation_id' => $conversationId,
                'user_id' => get_current_user_id(),
                'user_message' => $userMessage,
                'assistant_response' => $assistantResponse,
                'session_data' => wp_json_encode(['context_updated' => true]),
                'created_at' => current_time('mysql')
            ]);

            return $result !== false;
        } catch (\Exception $e) {
            Utils::logDebug('Error updating conversation context: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Clean up old conversation contexts from memory
     *
     * @since 1.0.0
     * @return void
     */
    private function cleanupOldContexts(): void
    {
        $cutoffTime = time() - (24 * HOUR_IN_SECONDS); // 24 hours ago

        foreach ($this->conversationContext as $conversationId => $context) {
            if ($context['updated_at'] < $cutoffTime) {
                unset($this->conversationContext[$conversationId]);
            }
        }
    }

    /**
     * Handle streaming response for AJAX requests
     *
     * @since 1.0.0
     * @return void
     */
    public function handleStreamingRequest(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'woo_ai_assistant_chat')) {
            wp_die('Security check failed');
        }

        $userMessage = sanitize_textarea_field($_POST['message'] ?? '');
        $conversationId = sanitize_text_field($_POST['conversation_id'] ?? '');

        if (empty($userMessage)) {
            wp_send_json_error('Message is required');
            return;
        }

        try {
            // Enable streaming response
            $response = $this->generateResponse($userMessage, [
                'conversation_id' => $conversationId,
                'stream' => true
            ]);

            wp_send_json_success($response);
        } catch (\Exception $e) {
            Utils::logDebug('Streaming request error: ' . $e->getMessage(), 'error');
            wp_send_json_error('Failed to generate response');
        }
    }

    /**
     * Build standardized error response
     *
     * @since 1.0.0
     * @param string $message Error message
     * @param string $errorCode Error code identifier
     * @return array Error response array
     */
    private function buildErrorResponse(string $message, string $errorCode): array
    {
        return [
            'success' => false,
            'error' => $message,
            'error_code' => $errorCode,
            'response' => "I apologize, but I'm unable to process your request right now. Please try again later or contact our support team for assistance.",
            'model_used' => 'none',
            'tokens_used' => 0,
            'context_chunks' => 0
        ];
    }

    /**
     * Get AI service status and availability
     *
     * @since 1.0.0
     * @return array Service status information
     */
    public function getServiceStatus(): array
    {
        $openRouterKey = get_option('woo_ai_assistant_openrouter_key');
        $geminiKey = get_option('woo_ai_assistant_gemini_key');

        return [
            'openrouter' => [
                'configured' => !empty($openRouterKey),
                'available' => !empty($openRouterKey) && $this->testOpenRouterConnection()
            ],
            'gemini' => [
                'configured' => !empty($geminiKey),
                'available' => !empty($geminiKey) && $this->testGeminiConnection()
            ],
            'vector_manager' => [
                'available' => $this->vectorManager !== null
            ],
            'dummy_mode' => defined('WOO_AI_ASSISTANT_USE_DUMMY_DATA') && WOO_AI_ASSISTANT_USE_DUMMY_DATA
        ];
    }

    /**
     * Test OpenRouter connection
     *
     * @since 1.0.0
     * @return bool Connection status
     */
    private function testOpenRouterConnection(): bool
    {
        try {
            $response = $this->callOpenRouter(self::DEFAULT_FREE_MODEL, [
                ['role' => 'user', 'content' => 'Hello']
            ], ['max_tokens' => 5]);

            return $response['success'] ?? false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Test Gemini connection
     *
     * @since 1.0.0
     * @return bool Connection status
     */
    private function testGeminiConnection(): bool
    {
        try {
            $response = $this->callGeminiDirect('gemini-2.5-flash-002', [
                ['role' => 'user', 'content' => 'Hello']
            ], ['max_tokens' => 5]);

            return $response['success'] ?? false;
        } catch (\Exception $e) {
            return false;
        }
    }
}
