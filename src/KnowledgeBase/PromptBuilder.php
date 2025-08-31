<?php

/**
 * Prompt Builder Class
 *
 * Handles prompt construction and optimization for AI interactions. Builds context-aware
 * prompts for different query types, implements prompt templates for common scenarios,
 * optimizes context window usage, and provides guardrails against prompt injection.
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
use WooAiAssistant\Common\Sanitizer;
use WooAiAssistant\Config\ApiConfiguration;
use Exception;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class PromptBuilder
 *
 * Advanced prompt construction with context optimization and safety features.
 *
 * @since 1.0.0
 */
class PromptBuilder
{
    use Singleton;

    /**
     * API configuration instance
     *
     * @var ApiConfiguration
     */
    private ApiConfiguration $apiConfig;

    /**
     * Maximum prompt length in tokens (approximate)
     *
     * @var int
     */
    private int $maxPromptTokens = 6000;

    /**
     * Token estimation ratio (characters per token)
     *
     * @var float
     */
    private float $tokenRatio = 4.0;

    /**
     * System prompt templates
     *
     * @var array
     */
    private array $systemPrompts = [];

    /**
     * Query type patterns for classification
     *
     * @var array
     */
    private array $queryPatterns = [
        'product_inquiry' => [
            'patterns' => ['/product|item|buy|purchase|price|cost|available/i'],
            'keywords' => ['product', 'item', 'buy', 'purchase', 'price', 'cost', 'available', 'stock']
        ],
        'shipping_inquiry' => [
            'patterns' => ['/ship|deliver|transport|send|mail/i'],
            'keywords' => ['shipping', 'delivery', 'transport', 'courier', 'mail', 'postal']
        ],
        'return_policy' => [
            'patterns' => ['/return|refund|exchange|warranty|guarantee/i'],
            'keywords' => ['return', 'refund', 'exchange', 'warranty', 'guarantee', 'policy']
        ],
        'payment_inquiry' => [
            'patterns' => ['/pay|payment|card|checkout|billing/i'],
            'keywords' => ['payment', 'card', 'checkout', 'billing', 'invoice', 'transaction']
        ],
        'support_request' => [
            'patterns' => ['/help|support|problem|issue|trouble/i'],
            'keywords' => ['help', 'support', 'problem', 'issue', 'trouble', 'assistance']
        ],
        'general_inquiry' => [
            'patterns' => ['/.*?/'],
            'keywords' => []
        ]
    ];

    /**
     * Prompt injection detection patterns
     *
     * @var array
     */
    private array $injectionPatterns = [
        '/ignore\s+previous\s+instructions/i',
        '/system\s*:\s*you\s+are/i',
        '/assistant\s*:\s*i\s+am/i',
        '/pretend\s+to\s+be/i',
        '/act\s+as\s+if/i',
        '/forget\s+everything/i',
        '/new\s+instructions/i'
    ];

    /**
     * Multilingual support configuration
     *
     * @var array
     */
    private array $languageConfig = [
        'en' => ['name' => 'English', 'code' => 'en-US'],
        'es' => ['name' => 'Spanish', 'code' => 'es-ES'],
        'fr' => ['name' => 'French', 'code' => 'fr-FR'],
        'de' => ['name' => 'German', 'code' => 'de-DE'],
        'it' => ['name' => 'Italian', 'code' => 'it-IT'],
        'pt' => ['name' => 'Portuguese', 'code' => 'pt-BR'],
        'nl' => ['name' => 'Dutch', 'code' => 'nl-NL']
    ];

    /**
     * Prompt construction statistics
     *
     * @var array
     */
    private array $stats = [
        'total_prompts_built' => 0,
        'rag_prompts' => 0,
        'fallback_prompts' => 0,
        'template_prompts' => 0,
        'injection_attempts_blocked' => 0,
        'context_truncations' => 0,
        'multilingual_prompts' => 0
    ];

    /**
     * Initialize the prompt builder
     *
     * @return void
     */
    protected function init(): void
    {
        $this->apiConfig = ApiConfiguration::getInstance();

        // Load system prompts
        $this->loadSystemPrompts();

        // Reset statistics
        $this->resetStatistics();

        Logger::debug('Prompt Builder initialized', [
            'max_prompt_tokens' => $this->maxPromptTokens,
            'token_ratio' => $this->tokenRatio,
            'supported_languages' => array_keys($this->languageConfig),
            'query_types' => array_keys($this->queryPatterns)
        ]);
    }

    /**
     * Build RAG (Retrieval-Augmented Generation) prompt
     *
     * Creates a contextual prompt that includes relevant knowledge base chunks,
     * conversation history, and user context for optimal AI response generation.
     *
     * @since 1.0.0
     * @param string $userQuery User's query text
     * @param array  $args Optional. Prompt building arguments.
     * @param array  $args['context_chunks'] Knowledge base chunks to include. Default empty.
     * @param array  $args['conversation_history'] Previous conversation messages. Default empty.
     * @param string $args['user_context'] Current user context (page, product, etc.). Default empty.
     * @param int    $args['max_context_length'] Maximum context length in tokens. Default 6000.
     * @param string $args['language'] Response language. Default 'en'.
     * @param string $args['tone'] Response tone (formal, casual, friendly). Default 'friendly'.
     *
     * @return array Prompt data with messages, token count, and metadata.
     *               Contains 'messages', 'token_count', 'context_used', 'query_type'.
     *
     * @throws Exception When prompt building fails.
     *
     * @example
     * ```php
     * $promptBuilder = PromptBuilder::getInstance();
     * $prompt = $promptBuilder->buildRagPrompt('What is your shipping policy?', [
     *     'context_chunks' => $knowledgeChunks,
     *     'conversation_history' => $previousMessages,
     *     'user_context' => 'checkout_page',
     *     'language' => 'en',
     *     'tone' => 'helpful'
     * ]);
     * ```
     */
    public function buildRagPrompt(string $userQuery, array $args = []): array
    {
        try {
            // Parse arguments
            $defaults = [
                'context_chunks' => [],
                'conversation_history' => [],
                'user_context' => '',
                'max_context_length' => $this->maxPromptTokens,
                'language' => $this->detectLanguage($userQuery),
                'tone' => 'friendly'
            ];

            $args = wp_parse_args($args, $defaults);

            Logger::debug('Building RAG prompt', [
                'query_length' => strlen($userQuery),
                'context_chunks' => count($args['context_chunks']),
                'conversation_history' => count($args['conversation_history']),
                'language' => $args['language'],
                'user_context' => $args['user_context']
            ]);

            // Security check: detect and prevent prompt injection
            if ($this->detectPromptInjection($userQuery)) {
                $this->stats['injection_attempts_blocked']++;
                throw new Exception('Potentially malicious prompt detected');
            }

            // Classify query type
            $queryType = $this->classifyQueryType($userQuery);

            // Build system prompt
            $systemPrompt = $this->buildSystemPrompt($queryType, $args);

            // Build context section
            $contextSection = $this->buildContextSection($args['context_chunks'], $args['max_context_length']);

            // Build conversation history section
            $historySection = $this->buildConversationHistory($args['conversation_history'], $args['max_context_length']);

            // Build user context section
            $userContextSection = $this->buildUserContextSection($args['user_context']);

            // Build final user message
            $userMessage = $this->buildUserMessage($userQuery, $queryType, $args);

            // Assemble messages
            $messages = [];

            // Add system message
            $messages[] = [
                'role' => 'system',
                'content' => $systemPrompt
            ];

            // Add conversation history
            if (!empty($historySection)) {
                $messages = array_merge($messages, $historySection);
            }

            // Add current user message with context
            $currentMessage = $userMessage;
            if (!empty($contextSection)) {
                $currentMessage .= "\n\n" . $contextSection;
            }
            if (!empty($userContextSection)) {
                $currentMessage .= "\n\n" . $userContextSection;
            }

            $messages[] = [
                'role' => 'user',
                'content' => $currentMessage
            ];

            // Calculate total token count
            $tokenCount = $this->estimateTokenCount($messages);

            // Truncate if necessary
            if ($tokenCount > $args['max_context_length']) {
                $messages = $this->truncateMessages($messages, $args['max_context_length']);
                $tokenCount = $this->estimateTokenCount($messages);
                $this->stats['context_truncations']++;
            }

            $this->stats['total_prompts_built']++;
            $this->stats['rag_prompts']++;

            if ($args['language'] !== 'en') {
                $this->stats['multilingual_prompts']++;
            }

            $result = [
                'messages' => $messages,
                'token_count' => $tokenCount,
                'context_used' => count($args['context_chunks']),
                'query_type' => $queryType,
                'language' => $args['language'],
                'tone' => $args['tone'],
                'user_context' => $args['user_context'],
                'timestamp' => current_time('mysql')
            ];

            Logger::debug('RAG prompt built successfully', [
                'token_count' => $tokenCount,
                'messages_count' => count($messages),
                'query_type' => $queryType,
                'context_chunks_used' => count($args['context_chunks'])
            ]);

            return $result;
        } catch (Exception $e) {
            Logger::error('RAG prompt building failed', [
                'query_length' => strlen($userQuery),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Build template-based prompt for common scenarios
     *
     * Uses pre-defined templates for frequently asked questions and common scenarios
     * to provide consistent and optimized responses.
     *
     * @since 1.0.0
     * @param string $templateType Template type (product_info, shipping_info, etc.)
     * @param array  $templateData Data to fill template placeholders
     * @param array  $args Optional additional arguments
     *
     * @return array Template prompt data
     *
     * @throws Exception When template is not found or invalid
     */
    public function buildTemplatePrompt(string $templateType, array $templateData = [], array $args = []): array
    {
        try {
            if (!isset($this->systemPrompts['templates'][$templateType])) {
                throw new Exception("Template type '{$templateType}' not found");
            }

            $template = $this->systemPrompts['templates'][$templateType];
            $language = $args['language'] ?? 'en';

            // Replace placeholders in template
            $systemPrompt = $this->replacePlaceholders($template['system'], $templateData);
            $userPrompt = $this->replacePlaceholders($template['user'], $templateData);

            // Add language-specific instructions
            if ($language !== 'en') {
                $systemPrompt .= "\n\nIMPORTANT: Respond in {$this->languageConfig[$language]['name']} language.";
            }

            $messages = [
                [
                    'role' => 'system',
                    'content' => $systemPrompt
                ],
                [
                    'role' => 'user',
                    'content' => $userPrompt
                ]
            ];

            $this->stats['total_prompts_built']++;
            $this->stats['template_prompts']++;

            return [
                'messages' => $messages,
                'token_count' => $this->estimateTokenCount($messages),
                'template_type' => $templateType,
                'language' => $language,
                'timestamp' => current_time('mysql')
            ];
        } catch (Exception $e) {
            Logger::error('Template prompt building failed', [
                'template_type' => $templateType,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Build fallback response prompt
     *
     * Creates a graceful fallback response when AI processing fails or
     * when no relevant context is available.
     *
     * @since 1.0.0
     * @param string $originalQuery Original user query
     * @param array  $args Fallback arguments
     * @param string $args['error'] Error message that triggered fallback. Default empty.
     * @param string $args['user_context'] User context. Default empty.
     * @param string $args['language'] Response language. Default 'en'.
     *
     * @return array Fallback response data
     */
    public function buildFallbackResponse(string $originalQuery, array $args = []): array
    {
        $defaults = [
            'error' => '',
            'user_context' => '',
            'language' => $this->detectLanguage($originalQuery)
        ];

        $args = wp_parse_args($args, $defaults);

        // Classify the original query to provide appropriate fallback
        $queryType = $this->classifyQueryType($originalQuery);

        // Select appropriate fallback template
        $fallbackTemplate = $this->getFallbackTemplate($queryType, $args);

        $this->stats['total_prompts_built']++;
        $this->stats['fallback_prompts']++;

        Logger::info('Built fallback response', [
            'query_type' => $queryType,
            'language' => $args['language'],
            'error' => !empty($args['error'])
        ]);

        return [
            'content' => $fallbackTemplate,
            'type' => 'fallback',
            'query_type' => $queryType,
            'language' => $args['language'],
            'timestamp' => current_time('mysql')
        ];
    }

    /**
     * Optimize context window usage
     *
     * Intelligently selects and prioritizes context chunks to maximize
     * relevance while staying within token limits.
     *
     * @since 1.0.0
     * @param array $contextChunks Available context chunks
     * @param int   $maxTokens Maximum tokens for context
     *
     * @return array Optimized context chunks
     */
    public function optimizeContextWindow(array $contextChunks, int $maxTokens): array
    {
        if (empty($contextChunks)) {
            return [];
        }

        // Sort chunks by relevance score (if available)
        usort($contextChunks, function ($a, $b) {
            $scoreA = $a['score'] ?? 0;
            $scoreB = $b['score'] ?? 0;
            return $scoreB <=> $scoreA;
        });

        $optimizedChunks = [];
        $totalTokens = 0;

        foreach ($contextChunks as $chunk) {
            $chunkTokens = $this->estimateTokenCount($chunk['content'] ?? '');

            if ($totalTokens + $chunkTokens <= $maxTokens) {
                $optimizedChunks[] = $chunk;
                $totalTokens += $chunkTokens;
            } else {
                // Try to fit truncated version of high-score chunks
                if ($chunk['score'] >= 0.8 && count($optimizedChunks) < 3) {
                    $remainingTokens = $maxTokens - $totalTokens;
                    $truncatedContent = $this->truncateContent(
                        $chunk['content'] ?? '',
                        $remainingTokens
                    );

                    if (!empty($truncatedContent)) {
                        $chunk['content'] = $truncatedContent;
                        $chunk['truncated'] = true;
                        $optimizedChunks[] = $chunk;
                        break;
                    }
                }
                break;
            }
        }

        Logger::debug('Context window optimized', [
            'original_chunks' => count($contextChunks),
            'optimized_chunks' => count($optimizedChunks),
            'total_tokens' => $totalTokens,
            'max_tokens' => $maxTokens
        ]);

        return $optimizedChunks;
    }

    /**
     * Get prompt building statistics
     *
     * @return array Statistics about prompt construction
     */
    public function getStatistics(): array
    {
        return [
            'session_stats' => $this->stats,
            'configuration' => [
                'max_prompt_tokens' => $this->maxPromptTokens,
                'token_ratio' => $this->tokenRatio,
                'supported_languages' => count($this->languageConfig),
                'query_types' => count($this->queryPatterns),
                'system_prompts' => count($this->systemPrompts)
            ],
            'language_support' => $this->languageConfig,
            'query_patterns' => array_keys($this->queryPatterns)
        ];
    }

    /**
     * Load system prompt templates
     */
    private function loadSystemPrompts(): void
    {
        $this->systemPrompts = [
            'base' => "You are a helpful AI assistant for a WooCommerce store. Your role is to assist customers with their questions about products, orders, shipping, returns, and general store policies. Be friendly, informative, and concise in your responses.\n\nKey guidelines:\n- Always be polite and professional\n- Provide accurate information based on the knowledge base\n- If you don't know something, admit it and suggest alternatives\n- Focus on being helpful and solving customer problems\n- Use the provided context to give specific, relevant answers",

            'rag' => "You are an AI customer service assistant for a WooCommerce store. Use the provided knowledge base context to answer customer questions accurately and helpfully.\n\nIMPORTANT INSTRUCTIONS:\n- Base your answers primarily on the provided context\n- If the context doesn't contain relevant information, say so clearly\n- Be conversational but professional\n- Provide specific details when available (prices, specifications, policies)\n- If asked about products not in the context, suggest browsing the store or contacting support\n- Always aim to be helpful and resolve the customer's needs",

            'templates' => [
                'product_info' => [
                    'system' => "You are a product specialist for a WooCommerce store. Provide detailed, accurate information about products based on the provided data. Focus on features, benefits, pricing, and availability.",
                    'user' => "Please provide information about {{product_name}}. Include details about {{specific_aspects}} if available."
                ],
                'shipping_info' => [
                    'system' => "You are a shipping specialist for a WooCommerce store. Provide clear, accurate information about shipping options, costs, and delivery times.",
                    'user' => "Please explain the shipping options for {{location}}. Include costs and estimated delivery times."
                ],
                'return_policy' => [
                    'system' => "You are a customer service specialist for a WooCommerce store. Explain return and refund policies clearly and helpfully.",
                    'user' => "Please explain the return policy for {{product_category}}. Include timeframes and any conditions."
                ]
            ]
        ];

        // Apply filters to allow customization
        $this->systemPrompts = apply_filters('woo_ai_assistant_system_prompts', $this->systemPrompts);
    }

    /**
     * Classify query type based on patterns and keywords
     *
     * @param string $query User query
     * @return string Query type
     */
    private function classifyQueryType(string $query): string
    {
        $query = strtolower($query);

        foreach ($this->queryPatterns as $type => $config) {
            // Check patterns
            foreach ($config['patterns'] as $pattern) {
                if (preg_match($pattern, $query)) {
                    return $type;
                }
            }

            // Check keywords
            foreach ($config['keywords'] as $keyword) {
                if (strpos($query, $keyword) !== false) {
                    return $type;
                }
            }
        }

        return 'general_inquiry';
    }

    /**
     * Detect language of user query
     *
     * @param string $query User query
     * @return string Language code
     */
    private function detectLanguage(string $query): string
    {
        // Simple language detection based on common words
        $languageIndicators = [
            'es' => ['el', 'la', 'que', 'de', 'y', 'para', 'con', 'por', 'una', 'como'],
            'fr' => ['le', 'de', 'et', 'à', 'un', 'il', 'être', 'que', 'ce', 'avec'],
            'de' => ['der', 'die', 'und', 'in', 'den', 'von', 'zu', 'das', 'mit', 'für'],
            'it' => ['il', 'di', 'che', 'e', 'un', 'per', 'in', 'con', 'del', 'la'],
            'pt' => ['o', 'de', 'que', 'e', 'do', 'da', 'em', 'um', 'para', 'com'],
            'nl' => ['de', 'en', 'van', 'het', 'in', 'op', 'dat', 'met', 'voor', 'een']
        ];

        $queryLower = strtolower($query);
        $scores = [];

        foreach ($languageIndicators as $lang => $indicators) {
            $score = 0;
            foreach ($indicators as $indicator) {
                $score += substr_count($queryLower, ' ' . $indicator . ' ');
            }
            $scores[$lang] = $score;
        }

        // Get language with highest score
        arsort($scores);
        $detectedLang = array_key_first($scores);

        return ($scores[$detectedLang] > 0) ? $detectedLang : 'en';
    }

    /**
     * Detect potential prompt injection attempts
     *
     * @param string $query User query
     * @return bool True if injection detected
     */
    private function detectPromptInjection(string $query): bool
    {
        foreach ($this->injectionPatterns as $pattern) {
            if (preg_match($pattern, $query)) {
                Logger::warning('Potential prompt injection detected', [
                    'pattern' => $pattern,
                    'query_preview' => substr($query, 0, 100)
                ]);
                return true;
            }
        }

        return false;
    }

    /**
     * Build system prompt for specific query type
     *
     * @param string $queryType Query type
     * @param array  $args Prompt arguments
     * @return string System prompt
     */
    private function buildSystemPrompt(string $queryType, array $args): string
    {
        $basePrompt = $this->systemPrompts['rag'];

        // Add language-specific instructions
        if ($args['language'] !== 'en') {
            $languageName = $this->languageConfig[$args['language']]['name'] ?? $args['language'];
            $basePrompt .= "\n\nIMPORTANT: Always respond in {$languageName} language.";
        }

        // Add tone instructions
        if ($args['tone'] !== 'friendly') {
            $toneInstructions = [
                'formal' => 'Use a formal, professional tone.',
                'casual' => 'Use a casual, relaxed tone.',
                'technical' => 'Provide technical details when appropriate.'
            ];

            if (isset($toneInstructions[$args['tone']])) {
                $basePrompt .= "\n\nTONE: " . $toneInstructions[$args['tone']];
            }
        }

        // Add query-specific instructions
        $queryInstructions = [
            'product_inquiry' => 'Focus on product features, pricing, availability, and specifications.',
            'shipping_inquiry' => 'Provide clear shipping options, costs, and delivery timeframes.',
            'return_policy' => 'Explain return processes, timeframes, and conditions clearly.',
            'payment_inquiry' => 'Address payment methods, security, and billing processes.',
            'support_request' => 'Focus on problem-solving and providing helpful solutions.'
        ];

        if (isset($queryInstructions[$queryType])) {
            $basePrompt .= "\n\nFOCUS: " . $queryInstructions[$queryType];
        }

        return $basePrompt;
    }

    /**
     * Build context section from knowledge base chunks
     *
     * @param array $contextChunks Knowledge base chunks
     * @param int   $maxTokens Maximum tokens for context
     * @return string Context section
     */
    private function buildContextSection(array $contextChunks, int $maxTokens): string
    {
        if (empty($contextChunks)) {
            return '';
        }

        $optimizedChunks = $this->optimizeContextWindow($contextChunks, $maxTokens * 0.6); // Use 60% of tokens for KB context

        if (empty($optimizedChunks)) {
            return '';
        }

        $contextText = "KNOWLEDGE BASE CONTEXT:\n";

        foreach ($optimizedChunks as $index => $chunk) {
            $contextText .= "\n[Context " . ($index + 1) . "]\n";
            $contextText .= $chunk['content'] ?? '';

            if (isset($chunk['metadata']['title'])) {
                $contextText .= "\n(Source: " . $chunk['metadata']['title'] . ")";
            }

            if ($chunk['truncated'] ?? false) {
                $contextText .= "\n[...truncated...]";
            }

            $contextText .= "\n";
        }

        return $contextText;
    }

    /**
     * Build conversation history section
     *
     * @param array $conversationHistory Previous messages
     * @param int   $maxTokens Maximum tokens for history
     * @return array Formatted history messages
     */
    private function buildConversationHistory(array $conversationHistory, int $maxTokens): array
    {
        if (empty($conversationHistory)) {
            return [];
        }

        $historyTokenLimit = $maxTokens * 0.3; // Use 30% of tokens for history
        $formattedHistory = [];
        $totalTokens = 0;

        // Process most recent messages first
        $reversedHistory = array_reverse($conversationHistory);

        foreach ($reversedHistory as $message) {
            if (!isset($message['role']) || !isset($message['content'])) {
                continue;
            }

            $messageTokens = $this->estimateTokenCount($message['content']);

            if ($totalTokens + $messageTokens > $historyTokenLimit) {
                break;
            }

            $formattedHistory[] = [
                'role' => $message['role'],
                'content' => Sanitizer::sanitizeText($message['content'])
            ];

            $totalTokens += $messageTokens;
        }

        return array_reverse($formattedHistory);
    }

    /**
     * Build user context section
     *
     * @param string $userContext User context information
     * @return string Context section
     */
    private function buildUserContextSection(string $userContext): string
    {
        if (empty($userContext)) {
            return '';
        }

        $contextMap = [
            'product_page' => 'The customer is currently viewing a product page.',
            'cart_page' => 'The customer is currently on the shopping cart page.',
            'checkout_page' => 'The customer is currently on the checkout page.',
            'account_page' => 'The customer is currently on their account page.',
            'shop_page' => 'The customer is currently browsing the shop.',
            'category_page' => 'The customer is currently viewing a product category.'
        ];

        $contextText = $contextMap[$userContext] ?? "Current context: {$userContext}";

        return "CURRENT CONTEXT:\n{$contextText}\n";
    }

    /**
     * Build user message with query type context
     *
     * @param string $userQuery Original query
     * @param string $queryType Classified query type
     * @param array  $args Additional arguments
     * @return string Enhanced user message
     */
    private function buildUserMessage(string $userQuery, string $queryType, array $args): string
    {
        $sanitizedQuery = Sanitizer::sanitizeText($userQuery);

        return "CUSTOMER QUERY ({$queryType}):\n{$sanitizedQuery}";
    }

    /**
     * Get fallback template based on query type
     *
     * @param string $queryType Query type
     * @param array  $args Fallback arguments
     * @return string Fallback response template
     */
    private function getFallbackTemplate(string $queryType, array $args): string
    {
        $language = $args['language'] ?? 'en';

        $fallbackTemplates = [
            'en' => [
                'product_inquiry' => "I apologize, but I'm having trouble accessing product information at the moment. Please browse our store directly or contact our support team for assistance with specific product questions.",
                'shipping_inquiry' => "I'm sorry, I cannot access shipping information right now. Please check our shipping policy page or contact customer service for current shipping options and rates.",
                'return_policy' => "I apologize, but I cannot access return policy information at the moment. Please visit our return policy page or contact our support team for assistance with returns or exchanges.",
                'payment_inquiry' => "I'm sorry, I cannot provide payment information right now. Please contact our support team for assistance with payment methods and billing questions.",
                'support_request' => "I apologize, but I'm experiencing technical difficulties. Please contact our customer support team directly for immediate assistance with your issue.",
                'general_inquiry' => "I apologize, but I'm having trouble processing your request at the moment. Please try again later or contact our support team for personalized assistance."
            ],
            // Add more languages as needed
        ];

        $templates = $fallbackTemplates[$language] ?? $fallbackTemplates['en'];
        return $templates[$queryType] ?? $templates['general_inquiry'];
    }

    /**
     * Replace placeholders in template strings
     *
     * @param string $template Template string
     * @param array  $data Data to replace placeholders
     * @return string Processed template
     */
    private function replacePlaceholders(string $template, array $data): string
    {
        foreach ($data as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $template = str_replace($placeholder, $value, $template);
        }

        return $template;
    }

    /**
     * Estimate token count for text
     *
     * @param string|array $content Content to estimate
     * @return int Estimated token count
     */
    private function estimateTokenCount($content): int
    {
        if (is_array($content)) {
            $totalTokens = 0;
            foreach ($content as $message) {
                if (isset($message['content'])) {
                    $totalTokens += ceil(strlen($message['content']) / $this->tokenRatio);
                }
            }
            return $totalTokens;
        }

        return ceil(strlen($content) / $this->tokenRatio);
    }

    /**
     * Truncate messages to fit token limit
     *
     * @param array $messages Messages array
     * @param int   $maxTokens Maximum tokens
     * @return array Truncated messages
     */
    private function truncateMessages(array $messages, int $maxTokens): array
    {
        $totalTokens = 0;
        $truncatedMessages = [];

        // Keep system message and most recent user message
        $systemMessage = null;
        $userMessage = null;

        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                $systemMessage = $message;
            } elseif ($message['role'] === 'user') {
                $userMessage = $message;
            }
        }

        if ($systemMessage) {
            $truncatedMessages[] = $systemMessage;
            $totalTokens += $this->estimateTokenCount($systemMessage['content']);
        }

        // Add conversation history if space allows
        foreach ($messages as $message) {
            if ($message['role'] === 'assistant' || ($message['role'] === 'user' && $message !== $userMessage)) {
                $messageTokens = $this->estimateTokenCount($message['content']);

                if ($totalTokens + $messageTokens <= $maxTokens - 200) { // Reserve 200 tokens for final user message
                    $truncatedMessages[] = $message;
                    $totalTokens += $messageTokens;
                }
            }
        }

        // Add final user message
        if ($userMessage) {
            $truncatedMessages[] = $userMessage;
        }

        return $truncatedMessages;
    }

    /**
     * Truncate content to fit token limit
     *
     * @param string $content Content to truncate
     * @param int    $maxTokens Maximum tokens
     * @return string Truncated content
     */
    private function truncateContent(string $content, int $maxTokens): string
    {
        $maxChars = $maxTokens * $this->tokenRatio;

        if (strlen($content) <= $maxChars) {
            return $content;
        }

        $truncated = substr($content, 0, $maxChars);

        // Try to break at word boundary
        $lastSpace = strrpos($truncated, ' ');
        if ($lastSpace !== false && $lastSpace > $maxChars * 0.8) {
            $truncated = substr($truncated, 0, $lastSpace);
        }

        return $truncated . '...';
    }

    /**
     * Reset statistics
     */
    private function resetStatistics(): void
    {
        $this->stats = [
            'total_prompts_built' => 0,
            'rag_prompts' => 0,
            'fallback_prompts' => 0,
            'template_prompts' => 0,
            'injection_attempts_blocked' => 0,
            'context_truncations' => 0,
            'multilingual_prompts' => 0
        ];
    }

    /**
     * Set maximum prompt tokens
     *
     * @param int $maxTokens Maximum prompt tokens
     * @throws Exception When tokens count is invalid
     */
    public function setMaxPromptTokens(int $maxTokens): void
    {
        if ($maxTokens < 1000 || $maxTokens > 30000) {
            throw new Exception('Max prompt tokens must be between 1000 and 30000');
        }

        $this->maxPromptTokens = $maxTokens;
        Logger::debug('Prompt Builder max tokens updated', ['new_max' => $maxTokens]);
    }

    /**
     * Set token estimation ratio
     *
     * @param float $ratio Characters per token ratio
     * @throws Exception When ratio is invalid
     */
    public function setTokenRatio(float $ratio): void
    {
        if ($ratio < 2.0 || $ratio > 8.0) {
            throw new Exception('Token ratio must be between 2.0 and 8.0');
        }

        $this->tokenRatio = $ratio;
        Logger::debug('Prompt Builder token ratio updated', ['new_ratio' => $ratio]);
    }
}
