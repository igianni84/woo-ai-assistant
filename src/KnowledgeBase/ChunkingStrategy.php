<?php

/**
 * Chunking Strategy Class
 *
 * Implements advanced text chunking algorithms for the AI knowledge base.
 * Provides intelligent text segmentation with configurable chunk sizes,
 * overlap handling, and context preservation for optimal embedding generation.
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
use Exception;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ChunkingStrategy
 *
 * Advanced text chunking with context preservation and token-aware segmentation.
 *
 * @since 1.0.0
 */
class ChunkingStrategy
{
    use Singleton;

    /**
     * Default chunk size in tokens
     *
     * @var int
     */
    private int $defaultChunkSize = 1000;

    /**
     * Default overlap size in tokens
     *
     * @var int
     */
    private int $defaultOverlapSize = 100;

    /**
     * Minimum chunk size threshold
     *
     * @var int
     */
    private int $minChunkSize = 50;

    /**
     * Maximum chunk size limit
     *
     * @var int
     */
    private int $maxChunkSize = 2000;

    /**
     * Approximate tokens per character ratio
     * Used for rough token estimation (actual tokenization would require OpenAI tiktoken)
     *
     * @var float
     */
    private float $tokensPerChar = 0.25;

    /**
     * Content type specific chunking configurations
     *
     * @var array
     */
    private array $contentTypeConfig = [
        'product' => [
            'chunk_size' => 800,
            'overlap_size' => 80,
            'preserve_structure' => true,
            'priority_sections' => ['title', 'description', 'attributes', 'categories']
        ],
        'page' => [
            'chunk_size' => 1000,
            'overlap_size' => 100,
            'preserve_structure' => true,
            'priority_sections' => ['title', 'content', 'metadata']
        ],
        'post' => [
            'chunk_size' => 1200,
            'overlap_size' => 120,
            'preserve_structure' => false,
            'priority_sections' => ['title', 'content', 'tags', 'categories']
        ],
        'woocommerce_settings' => [
            'chunk_size' => 600,
            'overlap_size' => 60,
            'preserve_structure' => true,
            'priority_sections' => ['title', 'content']
        ],
        'product_cat' => [
            'chunk_size' => 400,
            'overlap_size' => 40,
            'preserve_structure' => false,
            'priority_sections' => ['title', 'content']
        ],
        'product_tag' => [
            'chunk_size' => 300,
            'overlap_size' => 30,
            'preserve_structure' => false,
            'priority_sections' => ['title', 'content']
        ]
    ];

    /**
     * Sentence boundary markers for intelligent splitting
     *
     * @var array
     */
    private array $sentenceBoundaries = ['.', '!', '?', '.\n', '!\n', '?\n'];

    /**
     * Paragraph boundary markers
     *
     * @var array
     */
    private array $paragraphBoundaries = ["\n\n", "\r\n\r\n", "\n\r\n\r"];

    /**
     * HTML elements that should be treated as logical boundaries
     *
     * @var array
     */
    private array $htmlBoundaries = ['</p>', '</div>', '</h1>', '</h2>', '</h3>', '</h4>', '</h5>', '</h6>', '</li>'];

    /**
     * Initialize the chunking strategy
     *
     * @return void
     */
    protected function init(): void
    {
        Logger::debug('ChunkingStrategy initialized', [
            'default_chunk_size' => $this->defaultChunkSize,
            'default_overlap_size' => $this->defaultOverlapSize,
            'content_types_configured' => count($this->contentTypeConfig)
        ]);
    }

    /**
     * Chunk text content with intelligent boundary detection
     *
     * Main chunking method that processes text content based on content type
     * and applies appropriate chunking strategy with overlap handling.
     *
     * @since 1.0.0
     * @param string $content Text content to chunk
     * @param string $contentType Type of content (product, page, post, etc.)
     * @param array  $args Optional. Additional chunking arguments.
     * @param int    $args['chunk_size'] Override default chunk size.
     * @param int    $args['overlap_size'] Override default overlap size.
     * @param bool   $args['preserve_html'] Whether to preserve HTML structure.
     * @param array  $args['metadata'] Additional metadata to include.
     *
     * @return array Array of chunks with metadata.
     *               Each chunk contains: 'text', 'chunk_index', 'token_count', 'word_count', 'metadata'.
     *
     * @throws Exception When content is empty or chunking fails.
     *
     * @example
     * ```php
     * $strategy = ChunkingStrategy::getInstance();
     * $chunks = $strategy->chunkContent($productDescription, 'product', ['chunk_size' => 800]);
     * foreach ($chunks as $chunk) {
     *     // Process each chunk for embedding generation
     * }
     * ```
     */
    public function chunkContent(string $content, string $contentType, array $args = []): array
    {
        try {
            if (empty(trim($content))) {
                throw new Exception('Content cannot be empty');
            }

            // Get configuration for content type
            $config = $this->getContentTypeConfig($contentType);

            // Parse arguments with defaults
            $chunkSize = $args['chunk_size'] ?? $config['chunk_size'];
            $overlapSize = $args['overlap_size'] ?? $config['overlap_size'];
            $preserveHtml = $args['preserve_html'] ?? false;
            $metadata = $args['metadata'] ?? [];

            // Validate chunk size parameters
            $this->validateChunkParameters($chunkSize, $overlapSize);

            Logger::debug('Starting content chunking', [
                'content_type' => $contentType,
                'content_length' => strlen($content),
                'chunk_size' => $chunkSize,
                'overlap_size' => $overlapSize,
                'preserve_html' => $preserveHtml
            ]);

            // Preprocess content
            $processedContent = $this->preprocessContent($content, $preserveHtml);

            // Apply content-type specific chunking
            $chunks = $this->applyChunkingStrategy($processedContent, $contentType, $chunkSize, $overlapSize);

            // Post-process chunks with metadata
            $finalChunks = $this->postProcessChunks($chunks, $contentType, $metadata);

            Logger::info('Content chunking completed', [
                'content_type' => $contentType,
                'original_length' => strlen($content),
                'chunks_created' => count($finalChunks),
                'avg_chunk_size' => count($finalChunks) > 0 ? array_sum(array_column($finalChunks, 'word_count')) / count($finalChunks) : 0
            ]);

            return $finalChunks;
        } catch (Exception $e) {
            Logger::error('Content chunking failed', [
                'content_type' => $contentType,
                'content_length' => strlen($content ?? ''),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Chunk structured content with section awareness
     *
     * Specialized chunking for structured content that maintains logical
     * section boundaries while respecting token limits.
     *
     * @since 1.0.0
     * @param array  $structuredContent Array of content sections with keys and values
     * @param string $contentType Content type identifier
     * @param array  $args Optional chunking arguments
     *
     * @return array Array of chunks with preserved structure information
     *
     * @throws Exception When structured content is invalid
     */
    public function chunkStructuredContent(array $structuredContent, string $contentType, array $args = []): array
    {
        try {
            if (empty($structuredContent)) {
                throw new Exception('Structured content cannot be empty');
            }

            $config = $this->getContentTypeConfig($contentType);
            $chunkSize = $args['chunk_size'] ?? $config['chunk_size'];
            $overlapSize = $args['overlap_size'] ?? $config['overlap_size'];

            Logger::debug('Starting structured content chunking', [
                'content_type' => $contentType,
                'sections_count' => count($structuredContent),
                'chunk_size' => $chunkSize
            ]);

            $chunks = [];
            $currentChunk = '';
            $currentChunkTokens = 0;
            $chunkIndex = 0;
            $prioritySections = $config['priority_sections'] ?? [];

            // Process priority sections first
            foreach ($prioritySections as $prioritySection) {
                if (isset($structuredContent[$prioritySection])) {
                    $sectionContent = $this->formatSection($prioritySection, $structuredContent[$prioritySection]);
                    $sectionTokens = $this->estimateTokenCount($sectionContent);

                    // If section alone exceeds chunk size, split it
                    if ($sectionTokens > $chunkSize) {
                        if (!empty($currentChunk)) {
                            $chunks[] = $this->createChunkObject(trim($currentChunk), $chunkIndex++, $contentType, [
                                'sections' => $this->extractSectionsFromChunk($currentChunk),
                                'is_complete_section' => false
                            ]);
                            $currentChunk = '';
                            $currentChunkTokens = 0;
                        }

                        // Split large section
                        $sectionChunks = $this->splitLargeSection($sectionContent, $chunkSize, $overlapSize);
                        foreach ($sectionChunks as $sectionChunk) {
                            $chunks[] = $this->createChunkObject($sectionChunk, $chunkIndex++, $contentType, [
                                'primary_section' => $prioritySection,
                                'is_section_fragment' => count($sectionChunks) > 1
                            ]);
                        }
                    } else {
                        // Check if adding this section would exceed chunk size
                        if ($currentChunkTokens + $sectionTokens > $chunkSize && !empty($currentChunk)) {
                            $chunks[] = $this->createChunkObject(trim($currentChunk), $chunkIndex++, $contentType, [
                                'sections' => $this->extractSectionsFromChunk($currentChunk)
                            ]);
                            $currentChunk = $this->applyOverlap($currentChunk, $overlapSize);
                            $currentChunkTokens = $this->estimateTokenCount($currentChunk);
                        }

                        $currentChunk .= ($currentChunk ? ' ' : '') . $sectionContent;
                        $currentChunkTokens += $sectionTokens;
                    }

                    unset($structuredContent[$prioritySection]);
                }
            }

            // Process remaining sections
            foreach ($structuredContent as $sectionKey => $sectionValue) {
                $sectionContent = $this->formatSection($sectionKey, $sectionValue);
                $sectionTokens = $this->estimateTokenCount($sectionContent);

                if ($currentChunkTokens + $sectionTokens > $chunkSize && !empty($currentChunk)) {
                    $chunks[] = $this->createChunkObject(trim($currentChunk), $chunkIndex++, $contentType, [
                        'sections' => $this->extractSectionsFromChunk($currentChunk)
                    ]);
                    $currentChunk = $this->applyOverlap($currentChunk, $overlapSize);
                    $currentChunkTokens = $this->estimateTokenCount($currentChunk);
                }

                $currentChunk .= ($currentChunk ? ' ' : '') . $sectionContent;
                $currentChunkTokens += $sectionTokens;
            }

            // Add final chunk
            if (!empty(trim($currentChunk))) {
                $chunks[] = $this->createChunkObject(trim($currentChunk), $chunkIndex, $contentType, [
                    'sections' => $this->extractSectionsFromChunk($currentChunk)
                ]);
            }

            Logger::info('Structured content chunking completed', [
                'content_type' => $contentType,
                'chunks_created' => count($chunks)
            ]);

            return $chunks;
        } catch (Exception $e) {
            Logger::error('Structured content chunking failed', [
                'content_type' => $contentType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Estimate token count for text content
     *
     * Provides rough token estimation based on character count and content analysis.
     * This is an approximation - actual tokenization requires OpenAI's tiktoken library.
     *
     * @since 1.0.0
     * @param string $text Text to analyze
     * @param string $model Optional. Model type for token estimation. Default 'gpt-3.5-turbo'.
     *
     * @return int Estimated token count
     */
    public function estimateTokenCount(string $text, string $model = 'gpt-3.5-turbo'): int
    {
        if (empty($text)) {
            return 0;
        }

        // Basic estimation based on character count
        $baseTokens = ceil(strlen($text) * $this->tokensPerChar);

        // Adjust for different content characteristics
        $adjustments = 0;

        // Penalty for repetitive content
        $uniqueWords = count(array_unique(str_word_count(strtolower($text), 1)));
        $totalWords = str_word_count($text);
        if ($totalWords > 0) {
            $repetitionRatio = $uniqueWords / $totalWords;
            if ($repetitionRatio < 0.5) {
                $adjustments -= ceil($baseTokens * 0.1); // 10% reduction for repetitive content
            }
        }

        // Bonus for technical/specific content (more tokens per character)
        if (preg_match('/\$\d+|\d+%|SKU:|Category:|Tag:/', $text)) {
            $adjustments += ceil($baseTokens * 0.15); // 15% increase for structured content
        }

        // Adjustment for HTML content (tags take more tokens)
        if (preg_match('/<[^>]+>/', $text)) {
            $adjustments += ceil($baseTokens * 0.2); // 20% increase for HTML
        }

        $estimatedTokens = max(1, $baseTokens + $adjustments);

        Logger::debug('Token count estimated', [
            'text_length' => strlen($text),
            'base_tokens' => $baseTokens,
            'adjustments' => $adjustments,
            'final_estimate' => $estimatedTokens
        ]);

        return $estimatedTokens;
    }

    /**
     * Calculate optimal chunk size for specific content
     *
     * Analyzes content characteristics to suggest optimal chunk size
     * for best embedding performance and retrieval accuracy.
     *
     * @since 1.0.0
     * @param string $content Content to analyze
     * @param string $contentType Content type
     *
     * @return int Recommended chunk size in tokens
     */
    public function calculateOptimalChunkSize(string $content, string $contentType): int
    {
        $contentLength = strlen($content);
        $wordCount = str_word_count($content);
        $config = $this->getContentTypeConfig($contentType);
        $baseChunkSize = $config['chunk_size'];

        // Adjust based on content length
        if ($contentLength < 500) {
            // Very short content - use smaller chunks to avoid over-padding
            $recommendedSize = min($baseChunkSize, 300);
        } elseif ($contentLength > 10000) {
            // Very long content - use larger chunks for efficiency
            $recommendedSize = min($this->maxChunkSize, $baseChunkSize * 1.5);
        } else {
            $recommendedSize = $baseChunkSize;
        }

        // Adjust for content structure
        $paragraphCount = substr_count($content, "\n\n") + substr_count($content, "</p>");
        if ($paragraphCount > 10) {
            // Highly structured content - slightly larger chunks
            $recommendedSize *= 1.2;
        }

        // Ensure within bounds
        $recommendedSize = max($this->minChunkSize, min($this->maxChunkSize, $recommendedSize));

        Logger::debug('Optimal chunk size calculated', [
            'content_type' => $contentType,
            'content_length' => $contentLength,
            'word_count' => $wordCount,
            'paragraph_count' => $paragraphCount,
            'base_chunk_size' => $baseChunkSize,
            'recommended_size' => $recommendedSize
        ]);

        return intval($recommendedSize);
    }

    /**
     * Get configuration for specific content type
     *
     * @param string $contentType Content type
     * @return array Configuration array
     */
    private function getContentTypeConfig(string $contentType): array
    {
        return $this->contentTypeConfig[$contentType] ?? [
            'chunk_size' => $this->defaultChunkSize,
            'overlap_size' => $this->defaultOverlapSize,
            'preserve_structure' => false,
            'priority_sections' => []
        ];
    }

    /**
     * Validate chunk parameters
     *
     * @param int $chunkSize Chunk size to validate
     * @param int $overlapSize Overlap size to validate
     * @throws Exception When parameters are invalid
     */
    private function validateChunkParameters(int $chunkSize, int $overlapSize): void
    {
        if ($chunkSize < $this->minChunkSize || $chunkSize > $this->maxChunkSize) {
            throw new Exception("Chunk size must be between {$this->minChunkSize} and {$this->maxChunkSize} tokens");
        }

        if ($overlapSize < 0 || $overlapSize >= $chunkSize) {
            throw new Exception("Overlap size must be between 0 and chunk size");
        }

        if ($overlapSize > ($chunkSize * 0.5)) {
            throw new Exception("Overlap size should not exceed 50% of chunk size");
        }
    }

    /**
     * Preprocess content before chunking
     *
     * @param string $content Raw content
     * @param bool $preserveHtml Whether to preserve HTML structure
     * @return string Preprocessed content
     */
    private function preprocessContent(string $content, bool $preserveHtml = false): string
    {
        // Sanitize content
        $processed = Sanitizer::sanitizeText($content);

        if (!$preserveHtml) {
            // Strip HTML tags but preserve some structure
            $processed = strip_tags($processed);
        } else {
            // Convert some HTML elements to plain text boundaries
            $processed = str_replace($this->htmlBoundaries, "\n", $processed);
        }

        // Normalize whitespace
        $processed = preg_replace('/\s+/', ' ', $processed);
        $processed = str_replace(["\r\n", "\r"], "\n", $processed);

        // Preserve paragraph breaks
        $processed = preg_replace('/\n\s*\n/', "\n\n", $processed);

        return trim($processed);
    }

    /**
     * Apply chunking strategy based on content type
     *
     * @param string $content Preprocessed content
     * @param string $contentType Content type
     * @param int $chunkSize Target chunk size in tokens
     * @param int $overlapSize Overlap size in tokens
     * @return array Raw chunks
     */
    private function applyChunkingStrategy(string $content, string $contentType, int $chunkSize, int $overlapSize): array
    {
        $chunks = [];
        $contentLength = strlen($content);
        $estimatedTokens = $this->estimateTokenCount($content);

        // If content is smaller than chunk size, return as single chunk
        if ($estimatedTokens <= $chunkSize) {
            return [$content];
        }

        // Calculate character-based chunk size (approximation)
        $chunkSizeChars = ceil($chunkSize / $this->tokensPerChar);
        $overlapSizeChars = ceil($overlapSize / $this->tokensPerChar);

        $position = 0;
        $lastOverlapText = '';

        while ($position < $contentLength) {
            $endPosition = min($position + $chunkSizeChars, $contentLength);

            // Find good boundary near the end position
            $boundaryPosition = $this->findOptimalBoundary($content, $position, $endPosition);

            // Extract chunk
            $chunkText = substr($content, $position, $boundaryPosition - $position);

            // Add overlap from previous chunk
            if (!empty($lastOverlapText) && $position > 0) {
                $chunkText = $lastOverlapText . ' ' . $chunkText;
            }

            $chunks[] = trim($chunkText);

            // Calculate overlap for next chunk
            $lastOverlapText = $this->extractOverlap($chunkText, $overlapSizeChars);

            $position = $boundaryPosition;
        }

        return array_filter($chunks, function ($chunk) {
            return !empty(trim($chunk));
        });
    }

    /**
     * Find optimal boundary for chunk splitting
     *
     * @param string $content Full content
     * @param int $startPos Start position
     * @param int $endPos Desired end position
     * @return int Actual boundary position
     */
    private function findOptimalBoundary(string $content, int $startPos, int $endPos): int
    {
        if ($endPos >= strlen($content)) {
            return strlen($content);
        }

        // Look for paragraph boundaries first
        for ($i = $endPos; $i > $startPos; $i--) {
            if (substr($content, $i, 2) === "\n\n") {
                return $i;
            }
        }

        // Look for sentence boundaries
        for ($i = $endPos; $i > $startPos; $i--) {
            $char = substr($content, $i, 1);
            if (in_array($char, ['.', '!', '?']) && $i + 1 < strlen($content) && substr($content, $i + 1, 1) === ' ') {
                return $i + 1;
            }
        }

        // Look for word boundaries
        for ($i = $endPos; $i > $startPos; $i--) {
            if (substr($content, $i, 1) === ' ') {
                return $i;
            }
        }

        // Fallback to hard cut
        return $endPos;
    }

    /**
     * Extract overlap text from the end of a chunk
     *
     * @param string $chunkText Chunk text
     * @param int $overlapSizeChars Overlap size in characters
     * @return string Overlap text
     */
    private function extractOverlap(string $chunkText, int $overlapSizeChars): string
    {
        if (strlen($chunkText) <= $overlapSizeChars) {
            return $chunkText;
        }

        $overlapText = substr($chunkText, -$overlapSizeChars);

        // Find word boundary
        $spacePos = strpos($overlapText, ' ');
        if ($spacePos !== false) {
            $overlapText = substr($overlapText, $spacePos + 1);
        }

        return $overlapText;
    }

    /**
     * Apply overlap to chunk text
     *
     * @param string $chunkText Current chunk text
     * @param int $overlapSize Overlap size in tokens
     * @return string Overlap text for next chunk
     */
    private function applyOverlap(string $chunkText, int $overlapSize): string
    {
        $overlapChars = ceil($overlapSize / $this->tokensPerChar);
        return $this->extractOverlap($chunkText, $overlapChars);
    }

    /**
     * Post-process chunks with metadata
     *
     * @param array $chunks Raw chunks
     * @param string $contentType Content type
     * @param array $metadata Base metadata
     * @return array Processed chunks with full metadata
     */
    private function postProcessChunks(array $chunks, string $contentType, array $metadata = []): array
    {
        $processedChunks = [];
        $totalChunks = count($chunks);

        foreach ($chunks as $index => $chunkText) {
            $processedChunks[] = $this->createChunkObject($chunkText, $index, $contentType, $metadata, $totalChunks);
        }

        return $processedChunks;
    }

    /**
     * Create standardized chunk object
     *
     * @param string $text Chunk text
     * @param int $index Chunk index
     * @param string $contentType Content type
     * @param array $metadata Additional metadata
     * @param int $totalChunks Total number of chunks (optional)
     * @return array Chunk object
     */
    private function createChunkObject(string $text, int $index, string $contentType, array $metadata = [], int $totalChunks = null): array
    {
        $wordCount = str_word_count($text);
        $tokenCount = $this->estimateTokenCount($text);

        return [
            'text' => trim($text),
            'chunk_index' => $index,
            'total_chunks' => $totalChunks ?? ($index + 1),
            'token_count' => $tokenCount,
            'word_count' => $wordCount,
            'char_count' => strlen($text),
            'content_type' => $contentType,
            'chunk_hash' => hash('sha256', $text . $index . $contentType),
            'metadata' => array_merge([
                'created_at' => current_time('mysql'),
                'chunking_strategy' => 'intelligent_boundary',
                'chunk_quality_score' => $this->calculateChunkQuality($text, $contentType)
            ], $metadata)
        ];
    }

    /**
     * Calculate chunk quality score
     *
     * @param string $text Chunk text
     * @param string $contentType Content type
     * @return float Quality score (0.0 to 1.0)
     */
    private function calculateChunkQuality(string $text, string $contentType): float
    {
        $score = 1.0;
        $textLength = strlen($text);

        // Penalize very short chunks
        if ($textLength < 100) {
            $score *= 0.6;
        }

        // Penalize chunks that end mid-sentence
        $lastChar = substr(trim($text), -1);
        if (!in_array($lastChar, ['.', '!', '?', ':'])) {
            $score *= 0.8;
        }

        // Bonus for complete sentences
        $sentenceCount = preg_match_all('/[.!?]+/', $text);
        if ($sentenceCount > 0) {
            $score *= 1.1;
        }

        return min(1.0, $score);
    }

    /**
     * Format section content for chunking
     *
     * @param string $sectionKey Section identifier
     * @param mixed $sectionValue Section content
     * @return string Formatted section text
     */
    private function formatSection(string $sectionKey, $sectionValue): string
    {
        $formattedKey = ucfirst(str_replace('_', ' ', $sectionKey));

        if (is_array($sectionValue)) {
            $content = implode(', ', array_filter($sectionValue));
        } else {
            $content = (string) $sectionValue;
        }

        return $formattedKey . ': ' . $content;
    }

    /**
     * Extract section information from chunk text
     *
     * @param string $chunkText Chunk text
     * @return array Array of sections found in chunk
     */
    private function extractSectionsFromChunk(string $chunkText): array
    {
        $sections = [];

        // Simple pattern matching for formatted sections
        if (preg_match_all('/([A-Z][a-z\s]+):\s*([^:]+?)(?=[A-Z][a-z\s]+:|$)/s', $chunkText, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $sections[] = strtolower(str_replace(' ', '_', trim($match[1])));
            }
        }

        return array_unique($sections);
    }

    /**
     * Split large section into smaller chunks
     *
     * @param string $sectionContent Large section content
     * @param int $chunkSize Target chunk size
     * @param int $overlapSize Overlap size
     * @return array Array of section chunks
     */
    private function splitLargeSection(string $sectionContent, int $chunkSize, int $overlapSize): array
    {
        // Use the main chunking algorithm for large sections
        return $this->applyChunkingStrategy($sectionContent, 'section', $chunkSize, $overlapSize);
    }

    /**
     * Get current chunking strategy statistics
     *
     * @return array Statistics about the chunking strategy configuration
     */
    public function getStatistics(): array
    {
        return [
            'default_chunk_size' => $this->defaultChunkSize,
            'default_overlap_size' => $this->defaultOverlapSize,
            'min_chunk_size' => $this->minChunkSize,
            'max_chunk_size' => $this->maxChunkSize,
            'tokens_per_char_ratio' => $this->tokensPerChar,
            'supported_content_types' => array_keys($this->contentTypeConfig),
            'content_type_configs' => $this->contentTypeConfig,
            'boundary_markers' => [
                'sentences' => count($this->sentenceBoundaries),
                'paragraphs' => count($this->paragraphBoundaries),
                'html_elements' => count($this->htmlBoundaries)
            ]
        ];
    }

    /**
     * Update content type configuration
     *
     * @param string $contentType Content type to configure
     * @param array $config New configuration
     * @return void
     * @throws Exception When configuration is invalid
     */
    public function updateContentTypeConfig(string $contentType, array $config): void
    {
        $requiredKeys = ['chunk_size', 'overlap_size'];
        foreach ($requiredKeys as $key) {
            if (!isset($config[$key])) {
                throw new Exception("Missing required configuration key: {$key}");
            }
        }

        $this->validateChunkParameters($config['chunk_size'], $config['overlap_size']);

        $this->contentTypeConfig[$contentType] = array_merge(
            $this->getContentTypeConfig($contentType),
            $config
        );

        Logger::info('Content type configuration updated', [
            'content_type' => $contentType,
            'new_config' => $config
        ]);
    }
}
