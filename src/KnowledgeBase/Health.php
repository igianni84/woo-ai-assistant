<?php

/**
 * Knowledge Base Health Class
 *
 * Handles analysis of Knowledge Base content completeness, freshness, and quality.
 * Provides improvement suggestions and template generation for missing content.
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
 * Class Health
 *
 * Comprehensive Knowledge Base health analysis system that evaluates content
 * completeness, identifies missing essential content, checks freshness,
 * provides improvement suggestions, and generates templates for missing content.
 *
 * @since 1.0.0
 */
class Health
{
    use Singleton;

    /**
     * Health score ranges
     *
     * @since 1.0.0
     * @var int
     */
    const HEALTH_EXCELLENT = 90;
    const HEALTH_GOOD = 70;
    const HEALTH_NEEDS_IMPROVEMENT = 50;
    const HEALTH_POOR = 30;

    /**
     * Content freshness thresholds in days
     *
     * @since 1.0.0
     * @var int
     */
    const FRESH_THRESHOLD = 30;
    const STALE_THRESHOLD = 90;
    const OUTDATED_THRESHOLD = 180;

    /**
     * Minimum content requirements for health score calculation
     *
     * @since 1.0.0
     * @var int
     */
    const MIN_PRODUCTS_REQUIRED = 10;
    const MIN_PAGES_REQUIRED = 5;
    const MIN_POLICIES_REQUIRED = 3;

    /**
     * Cache group for health operations
     *
     * @since 1.0.0
     * @var string
     */
    const CACHE_GROUP = 'woo_ai_health';

    /**
     * Cache TTL for health operations (1 hour)
     *
     * @since 1.0.0
     * @var int
     */
    const CACHE_TTL = 3600;

    /**
     * Essential content types that should exist in a complete Knowledge Base
     *
     * @since 1.0.0
     * @var array
     */
    private array $essentialContentTypes = [
        'products' => [
            'weight' => 40,
            'min_required' => 10,
            'description' => 'Product information and descriptions'
        ],
        'shipping_policy' => [
            'weight' => 15,
            'min_required' => 1,
            'description' => 'Shipping and delivery information'
        ],
        'return_policy' => [
            'weight' => 15,
            'min_required' => 1,
            'description' => 'Return and refund policies'
        ],
        'faq' => [
            'weight' => 10,
            'min_required' => 5,
            'description' => 'Frequently Asked Questions'
        ],
        'contact_info' => [
            'weight' => 5,
            'min_required' => 1,
            'description' => 'Contact and support information'
        ],
        'payment_info' => [
            'weight' => 5,
            'min_required' => 1,
            'description' => 'Payment methods and policies'
        ],
        'about' => [
            'weight' => 5,
            'min_required' => 1,
            'description' => 'About us and company information'
        ],
        'privacy_policy' => [
            'weight' => 3,
            'min_required' => 1,
            'description' => 'Privacy and data protection policy'
        ],
        'terms_conditions' => [
            'weight' => 2,
            'min_required' => 1,
            'description' => 'Terms and conditions'
        ]
    ];

    /**
     * Current health analysis results
     *
     * @since 1.0.0
     * @var array
     */
    private array $healthAnalysis = [];

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
        $this->initializeHealth();
    }

    /**
     * Initialize health monitoring system
     *
     * @since 1.0.0
     * @return void
     */
    private function initializeHealth(): void
    {
        // Hook into content updates for health recalculation
        add_action('woo_ai_assistant_content_indexed', [$this, 'onContentUpdated'], 10, 2);
        add_action('woo_ai_assistant_bulk_reindex_complete', [$this, 'recalculateHealthScore'], 10);

        Utils::logDebug('Health monitoring system initialized');
    }

    /**
     * Get comprehensive health score for the Knowledge Base
     *
     * Analyzes content completeness, freshness, and quality to provide
     * an overall health score from 0-100 along with detailed breakdown.
     *
     * @since 1.0.0
     * @param bool $forceRecalculate Whether to force recalculation instead of using cache. Default false.
     *
     * @return array Comprehensive health score data including:
     *               - overall_score: Overall health score (0-100)
     *               - health_status: Text description of health status
     *               - completeness_score: Content completeness score
     *               - freshness_score: Content freshness score
     *               - quality_score: Content quality score
     *               - breakdown: Detailed breakdown by content type
     *               - suggestions: Array of improvement suggestions
     *               - last_calculated: Timestamp of last calculation
     *
     * @throws \RuntimeException When health calculation fails.
     *
     * @example
     * ```php
     * $health = Health::getInstance();
     * $score = $health->getHealthScore();
     * echo "KB Health: {$score['overall_score']}/100 - {$score['health_status']}";
     * ```
     */
    public function getHealthScore(bool $forceRecalculate = false): array
    {
        if (!$forceRecalculate) {
            $cached = \wp_cache_get('health_score', self::CACHE_GROUP);
            if ($cached !== false) {
                return $cached;
            }
        }

        Utils::logDebug('Calculating Knowledge Base health score');

        try {
            // Analyze content completeness
            $completenessScore = $this->analyzeCompleteness();

            // Analyze content freshness
            $freshnessScore = $this->analyzeFreshness();

            // Analyze content quality
            $qualityScore = $this->analyzeQuality();

            // Calculate overall score (weighted average)
            $overallScore = round(
                ($completenessScore['score'] * 0.5) +
                ($freshnessScore['score'] * 0.3) +
                ($qualityScore['score'] * 0.2)
            );

            $healthData = [
                'overall_score' => $overallScore,
                'health_status' => $this->getHealthStatusText($overallScore),
                'completeness_score' => $completenessScore,
                'freshness_score' => $freshnessScore,
                'quality_score' => $qualityScore,
                'breakdown' => $this->getDetailedBreakdown(),
                'suggestions' => $this->generateImprovementSuggestions($overallScore),
                'last_calculated' => current_time('mysql'),
                'calculation_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']
            ];

            // Cache the results
            \wp_cache_set('health_score', $healthData, self::CACHE_GROUP, self::CACHE_TTL);
            $this->healthAnalysis = $healthData;

            Utils::logDebug('Health score calculation completed', [
                'overall_score' => $overallScore,
                'status' => $healthData['health_status']
            ]);

            return $healthData;
        } catch (\Exception $e) {
            Utils::logError('Health score calculation failed', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Failed to calculate health score: ' . $e->getMessage());
        }
    }

    /**
     * Analyze content completeness by checking for essential content types
     *
     * @since 1.0.0
     * @return array Completeness analysis results with score and missing content.
     */
    public function analyzeCompleteness(): array
    {
        Utils::logDebug('Analyzing Knowledge Base completeness');

        $tableName = $this->wpdb->prefix . 'woo_ai_knowledge_base';
        $completenessData = [
            'score' => 0,
            'missing_content' => [],
            'present_content' => [],
            'recommendations' => []
        ];

        $totalWeight = 0;
        $achievedWeight = 0;

        foreach ($this->essentialContentTypes as $contentType => $config) {
            $totalWeight += $config['weight'];

            $count = $this->getContentCount($contentType);
            $isComplete = $count >= $config['min_required'];

            if ($isComplete) {
                $achievedWeight += $config['weight'];
                $completenessData['present_content'][] = [
                    'type' => $contentType,
                    'count' => $count,
                    'description' => $config['description'],
                    'weight' => $config['weight']
                ];
            } else {
                $missing = $config['min_required'] - $count;
                $completenessData['missing_content'][] = [
                    'type' => $contentType,
                    'current_count' => $count,
                    'required_count' => $config['min_required'],
                    'missing_count' => $missing,
                    'description' => $config['description'],
                    'weight' => $config['weight'],
                    'impact' => $this->calculateMissingContentImpact($config['weight'], $missing)
                ];
            }
        }

        $completenessData['score'] = $totalWeight > 0 ? round(($achievedWeight / $totalWeight) * 100) : 0;
        $completenessData['recommendations'] = $this->generateCompletenessRecommendations($completenessData['missing_content']);

        return $completenessData;
    }

    /**
     * Analyze content freshness by checking when content was last updated
     *
     * @since 1.0.0
     * @return array Freshness analysis results with score and outdated content.
     */
    public function analyzeFreshness(): array
    {
        Utils::logDebug('Analyzing Knowledge Base freshness');

        $tableName = $this->wpdb->prefix . 'woo_ai_knowledge_base';
        $now = current_time('timestamp');

        $freshnessData = [
            'score' => 0,
            'fresh_content' => 0,
            'stale_content' => 0,
            'outdated_content' => 0,
            'never_updated' => 0,
            'average_age_days' => 0,
            'outdated_items' => []
        ];

        // Get content age statistics
        $sql = "SELECT 
                    source_type,
                    source_id,
                    title,
                    DATEDIFF(NOW(), updated_at) as age_days,
                    updated_at,
                    indexed_at
                FROM {$tableName} 
                WHERE updated_at IS NOT NULL
                ORDER BY updated_at DESC";

        $results = $this->wpdb->get_results($sql);

        if (empty($results)) {
            return array_merge($freshnessData, ['score' => 0]);
        }

        $totalItems = count($results);
        $totalAge = 0;

        foreach ($results as $item) {
            $ageDays = intval($item->age_days);
            $totalAge += $ageDays;

            if ($ageDays <= self::FRESH_THRESHOLD) {
                $freshnessData['fresh_content']++;
            } elseif ($ageDays <= self::STALE_THRESHOLD) {
                $freshnessData['stale_content']++;
            } elseif ($ageDays <= self::OUTDATED_THRESHOLD) {
                $freshnessData['outdated_content']++;
                $freshnessData['outdated_items'][] = [
                    'type' => $item->source_type,
                    'id' => $item->source_id,
                    'title' => $item->title,
                    'age_days' => $ageDays,
                    'last_updated' => $item->updated_at,
                    'priority' => $this->calculateUpdatePriority($item->source_type, $ageDays)
                ];
            } else {
                $freshnessData['never_updated']++;
                $freshnessData['outdated_items'][] = [
                    'type' => $item->source_type,
                    'id' => $item->source_id,
                    'title' => $item->title,
                    'age_days' => $ageDays,
                    'last_updated' => $item->updated_at,
                    'priority' => 'high'
                ];
            }
        }

        $freshnessData['average_age_days'] = round($totalAge / $totalItems, 1);

        // Calculate freshness score (higher percentage of fresh content = higher score)
        $freshPercent = ($freshnessData['fresh_content'] / $totalItems) * 100;
        $stalePercent = ($freshnessData['stale_content'] / $totalItems) * 100;

        // Fresh content = full points, stale content = half points, outdated = no points
        $freshnessData['score'] = round($freshPercent + ($stalePercent * 0.5));

        return $freshnessData;
    }

    /**
     * Analyze content quality by checking content length, duplicates, and metadata
     *
     * @since 1.0.0
     * @return array Quality analysis results with score and quality issues.
     */
    public function analyzeQuality(): array
    {
        Utils::logDebug('Analyzing Knowledge Base quality');

        $tableName = $this->wpdb->prefix . 'woo_ai_knowledge_base';

        $qualityData = [
            'score' => 0,
            'total_items' => 0,
            'quality_issues' => [],
            'content_statistics' => []
        ];

        // Get basic content statistics
        $sql = "SELECT 
                    COUNT(*) as total_items,
                    AVG(CHAR_LENGTH(content)) as avg_content_length,
                    AVG(CHAR_LENGTH(chunk_content)) as avg_chunk_length,
                    COUNT(DISTINCT hash) as unique_hashes,
                    COUNT(*) - COUNT(DISTINCT hash) as duplicate_count
                FROM {$tableName}";

        $stats = $this->wpdb->get_row($sql);

        if (!$stats || $stats->total_items == 0) {
            return array_merge($qualityData, ['score' => 0]);
        }

        $qualityData['total_items'] = intval($stats->total_items);
        $qualityData['content_statistics'] = [
            'total_items' => intval($stats->total_items),
            'avg_content_length' => round($stats->avg_content_length, 1),
            'avg_chunk_length' => round($stats->avg_chunk_length, 1),
            'unique_items' => intval($stats->unique_hashes),
            'duplicate_items' => intval($stats->duplicate_count)
        ];

        $qualityScore = 100;

        // Check for quality issues
        $issues = [];

        // Issue 1: Very short content (less than 50 characters)
        $shortContentSql = "SELECT COUNT(*) as count FROM {$tableName} WHERE CHAR_LENGTH(content) < 50";
        $shortCount = intval($this->wpdb->get_var($shortContentSql));
        if ($shortCount > 0) {
            $shortPercent = ($shortCount / $stats->total_items) * 100;
            $issues[] = [
                'type' => 'short_content',
                'severity' => $shortPercent > 20 ? 'high' : ($shortPercent > 10 ? 'medium' : 'low'),
                'count' => $shortCount,
                'percentage' => round($shortPercent, 1),
                'description' => 'Content items with very short text (less than 50 characters)',
                'impact' => 'May not provide sufficient information for AI responses'
            ];
            $qualityScore -= min(20, $shortPercent);
        }

        // Issue 2: Missing metadata
        $missingMetaSql = "SELECT COUNT(*) as count FROM {$tableName} WHERE metadata IS NULL OR metadata = ''";
        $missingMetaCount = intval($this->wpdb->get_var($missingMetaSql));
        if ($missingMetaCount > 0) {
            $metaPercent = ($missingMetaCount / $stats->total_items) * 100;
            $issues[] = [
                'type' => 'missing_metadata',
                'severity' => $metaPercent > 30 ? 'medium' : 'low',
                'count' => $missingMetaCount,
                'percentage' => round($metaPercent, 1),
                'description' => 'Content items without metadata',
                'impact' => 'Reduces context and search accuracy'
            ];
            $qualityScore -= min(15, $metaPercent * 0.5);
        }

        // Issue 3: High duplicate content
        if ($stats->duplicate_count > 0) {
            $duplicatePercent = ($stats->duplicate_count / $stats->total_items) * 100;
            $issues[] = [
                'type' => 'duplicate_content',
                'severity' => $duplicatePercent > 10 ? 'high' : ($duplicatePercent > 5 ? 'medium' : 'low'),
                'count' => intval($stats->duplicate_count),
                'percentage' => round($duplicatePercent, 1),
                'description' => 'Duplicate or near-duplicate content items',
                'impact' => 'Wastes storage and may confuse AI responses'
            ];
            $qualityScore -= min(25, $duplicatePercent * 2);
        }

        // Issue 4: Empty embeddings
        $emptyEmbeddingsSql = "SELECT COUNT(*) as count FROM {$tableName} WHERE embedding IS NULL OR embedding = ''";
        $emptyEmbeddingsCount = intval($this->wpdb->get_var($emptyEmbeddingsSql));
        if ($emptyEmbeddingsCount > 0) {
            $embeddingPercent = ($emptyEmbeddingsCount / $stats->total_items) * 100;
            $issues[] = [
                'type' => 'missing_embeddings',
                'severity' => $embeddingPercent > 20 ? 'high' : ($embeddingPercent > 10 ? 'medium' : 'low'),
                'count' => $emptyEmbeddingsCount,
                'percentage' => round($embeddingPercent, 1),
                'description' => 'Content items without vector embeddings',
                'impact' => 'Cannot be found by AI semantic search'
            ];
            $qualityScore -= min(30, $embeddingPercent * 1.5);
        }

        $qualityData['score'] = max(0, round($qualityScore));
        $qualityData['quality_issues'] = $issues;

        return $qualityData;
    }

    /**
     * Generate specific improvement suggestions based on health analysis
     *
     * @since 1.0.0
     * @param int $overallScore Current overall health score.
     *
     * @return array Array of prioritized improvement suggestions.
     */
    public function generateImprovementSuggestions(int $overallScore): array
    {
        $suggestions = [];

        // Get latest health analysis data
        $completeness = $this->analyzeCompleteness();
        $freshness = $this->analyzeFreshness();
        $quality = $this->analyzeQuality();

        // Priority 1: Address critical missing content
        foreach ($completeness['missing_content'] as $missing) {
            if ($missing['weight'] >= 15) { // High-weight content
                $suggestions[] = [
                    'priority' => 'critical',
                    'category' => 'completeness',
                    'title' => 'Add ' . ucwords(str_replace('_', ' ', $missing['type'])),
                    'description' => $missing['description'] . ' is missing from your knowledge base',
                    'action' => 'create_content',
                    'content_type' => $missing['type'],
                    'impact' => 'high',
                    'effort' => $this->estimateEffort($missing['type']),
                    'template_available' => $this->hasTemplate($missing['type'])
                ];
            }
        }

        // Priority 2: Update outdated critical content
        if ($freshness['outdated_content'] > 0) {
            $highPriorityOutdated = array_filter($freshness['outdated_items'], function ($item) {
                return $item['priority'] === 'high';
            });

            foreach (array_slice($highPriorityOutdated, 0, 3) as $outdated) {
                $suggestions[] = [
                    'priority' => 'high',
                    'category' => 'freshness',
                    'title' => 'Update ' . $outdated['title'],
                    'description' => "Content is {$outdated['age_days']} days old and may be outdated",
                    'action' => 'update_content',
                    'content_type' => $outdated['type'],
                    'content_id' => $outdated['id'],
                    'impact' => 'medium',
                    'effort' => 'low'
                ];
            }
        }

        // Priority 3: Fix quality issues
        foreach ($quality['quality_issues'] as $issue) {
            if ($issue['severity'] === 'high') {
                $suggestions[] = [
                    'priority' => 'high',
                    'category' => 'quality',
                    'title' => 'Fix ' . ucwords(str_replace('_', ' ', $issue['type'])),
                    'description' => $issue['description'] . ' (' . $issue['count'] . ' items affected)',
                    'action' => 'fix_quality',
                    'issue_type' => $issue['type'],
                    'impact' => $issue['severity'],
                    'effort' => $this->estimateQualityFixEffort($issue['type'])
                ];
            }
        }

        // Priority 4: Add missing moderate-priority content
        foreach ($completeness['missing_content'] as $missing) {
            if ($missing['weight'] < 15 && $missing['weight'] >= 5) {
                $suggestions[] = [
                    'priority' => 'medium',
                    'category' => 'completeness',
                    'title' => 'Add ' . ucwords(str_replace('_', ' ', $missing['type'])),
                    'description' => $missing['description'] . ' would improve customer self-service',
                    'action' => 'create_content',
                    'content_type' => $missing['type'],
                    'impact' => 'medium',
                    'effort' => $this->estimateEffort($missing['type']),
                    'template_available' => $this->hasTemplate($missing['type'])
                ];
            }
        }

        // Sort suggestions by priority and impact
        usort($suggestions, function ($a, $b) {
            $priorityOrder = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
            $impactOrder = ['high' => 0, 'medium' => 1, 'low' => 2];

            $priorityCompare = $priorityOrder[$a['priority']] <=> $priorityOrder[$b['priority']];
            if ($priorityCompare !== 0) {
                return $priorityCompare;
            }

            return $impactOrder[$a['impact']] <=> $impactOrder[$b['impact']];
        });

        return array_slice($suggestions, 0, 10); // Return top 10 suggestions
    }

    /**
     * Generate content templates for missing content types
     *
     * @since 1.0.0
     * @param string $contentType Type of content to generate template for.
     *
     * @return array|null Template data or null if no template available.
     *
     * @throws \InvalidArgumentException When content type is not supported.
     */
    public function generateContentTemplate(string $contentType): ?array
    {
        if (!isset($this->essentialContentTypes[$contentType])) {
            throw new \InvalidArgumentException("Unsupported content type: {$contentType}");
        }

        Utils::logDebug('Generating content template', ['content_type' => $contentType]);

        $templates = [
            'shipping_policy' => $this->generateShippingPolicyTemplate(),
            'return_policy' => $this->generateReturnPolicyTemplate(),
            'faq' => $this->generateFAQTemplate(),
            'contact_info' => $this->generateContactInfoTemplate(),
            'payment_info' => $this->generatePaymentInfoTemplate(),
            'about' => $this->generateAboutTemplate(),
            'privacy_policy' => $this->generatePrivacyPolicyTemplate(),
            'terms_conditions' => $this->generateTermsConditionsTemplate()
        ];

        return $templates[$contentType] ?? null;
    }

    /**
     * Test content freshness for specific content types
     *
     * @since 1.0.0
     * @param array $contentTypes Array of content types to test. Empty array tests all types.
     *
     * @return array Freshness test results by content type.
     */
    public function testFreshness(array $contentTypes = []): array
    {
        Utils::logDebug('Testing content freshness', ['content_types' => $contentTypes]);

        $tableName = $this->wpdb->prefix . 'woo_ai_knowledge_base';
        $results = [];

        if (empty($contentTypes)) {
            $contentTypes = array_keys($this->essentialContentTypes);
        }

        foreach ($contentTypes as $contentType) {
            $sql = $this->wpdb->prepare(
                "
                SELECT 
                    COUNT(*) as total_items,
                    AVG(DATEDIFF(NOW(), updated_at)) as avg_age_days,
                    MAX(DATEDIFF(NOW(), updated_at)) as oldest_days,
                    MIN(DATEDIFF(NOW(), updated_at)) as newest_days,
                    COUNT(CASE WHEN DATEDIFF(NOW(), updated_at) > %d THEN 1 END) as outdated_count
                FROM {$tableName} 
                WHERE source_type = %s 
                AND updated_at IS NOT NULL",
                self::OUTDATED_THRESHOLD,
                $contentType
            );

            $stats = $this->wpdb->get_row($sql);

            if ($stats && $stats->total_items > 0) {
                $freshnessScore = $this->calculateContentTypeFreshnessScore($stats);

                $results[$contentType] = [
                    'total_items' => intval($stats->total_items),
                    'avg_age_days' => round($stats->avg_age_days, 1),
                    'oldest_days' => intval($stats->oldest_days),
                    'newest_days' => intval($stats->newest_days),
                    'outdated_count' => intval($stats->outdated_count),
                    'freshness_score' => $freshnessScore,
                    'status' => $this->getFreshnessStatus($freshnessScore),
                    'recommendation' => $this->getFreshnessRecommendation($contentType, $freshnessScore)
                ];
            } else {
                $results[$contentType] = [
                    'total_items' => 0,
                    'status' => 'missing',
                    'recommendation' => 'Add ' . ucwords(str_replace('_', ' ', $contentType)) . ' content'
                ];
            }
        }

        return $results;
    }

    /**
     * Get detailed breakdown of health analysis by content type
     *
     * @since 1.0.0
     * @return array Detailed breakdown of health metrics.
     */
    private function getDetailedBreakdown(): array
    {
        $tableName = $this->wpdb->prefix . 'woo_ai_knowledge_base';
        $breakdown = [];

        foreach ($this->essentialContentTypes as $contentType => $config) {
            $sql = $this->wpdb->prepare(
                "
                SELECT 
                    COUNT(*) as count,
                    AVG(CHAR_LENGTH(content)) as avg_length,
                    MAX(updated_at) as last_updated,
                    COUNT(CASE WHEN DATEDIFF(NOW(), updated_at) > %d THEN 1 END) as outdated_count
                FROM {$tableName} 
                WHERE source_type = %s",
                self::STALE_THRESHOLD,
                $contentType
            );

            $stats = $this->wpdb->get_row($sql);

            $breakdown[$contentType] = [
                'count' => intval($stats->count ?? 0),
                'required' => $config['min_required'],
                'avg_length' => round($stats->avg_length ?? 0, 1),
                'last_updated' => $stats->last_updated,
                'outdated_count' => intval($stats->outdated_count ?? 0),
                'completeness' => min(100, round((intval($stats->count ?? 0) / $config['min_required']) * 100)),
                'weight' => $config['weight'],
                'description' => $config['description']
            ];
        }

        return $breakdown;
    }

    /**
     * Get count of content items by type
     *
     * @since 1.0.0
     * @param string $contentType Content type to count.
     *
     * @return int Number of content items.
     */
    private function getContentCount(string $contentType): int
    {
        $tableName = $this->wpdb->prefix . 'woo_ai_knowledge_base';

        // Map content types to actual source types in database
        $sourceTypeMapping = [
            'products' => 'product',
            'shipping_policy' => ['page', 'post'],
            'return_policy' => ['page', 'post'],
            'faq' => ['page', 'post'],
            'contact_info' => ['page', 'post'],
            'payment_info' => 'woo_settings',
            'about' => ['page', 'post'],
            'privacy_policy' => ['page', 'post'],
            'terms_conditions' => ['page', 'post']
        ];

        $sourceType = $sourceTypeMapping[$contentType] ?? $contentType;

        if (is_array($sourceType)) {
            // For page/post types, we need to check content for keywords
            $keywords = $this->getContentTypeKeywords($contentType);
            $keywordConditions = array_map(function ($keyword) {
                return "title LIKE '%" . \esc_sql($keyword) . "%' OR content LIKE '%" . \esc_sql($keyword) . "%'";
            }, $keywords);

            $keywordSql = implode(' OR ', $keywordConditions);
            $sourceTypeConditions = "'" . implode("', '", $sourceType) . "'";

            $sql = "SELECT COUNT(DISTINCT source_id) FROM {$tableName} 
                    WHERE source_type IN ({$sourceTypeConditions}) 
                    AND ({$keywordSql})";
        } else {
            $sql = $this->wpdb->prepare(
                "SELECT COUNT(DISTINCT source_id) FROM {$tableName} WHERE source_type = %s",
                $sourceType
            );
        }

        return intval($this->wpdb->get_var($sql));
    }

    /**
     * Get keywords for identifying content types
     *
     * @since 1.0.0
     * @param string $contentType Content type.
     *
     * @return array Array of keywords.
     */
    private function getContentTypeKeywords(string $contentType): array
    {
        $keywords = [
            'shipping_policy' => ['shipping', 'delivery', 'dispatch', 'freight'],
            'return_policy' => ['return', 'refund', 'exchange', 'warranty'],
            'faq' => ['faq', 'frequently asked', 'questions', 'help'],
            'contact_info' => ['contact', 'support', 'phone', 'email', 'address'],
            'about' => ['about us', 'about', 'company', 'history', 'mission'],
            'privacy_policy' => ['privacy', 'data protection', 'gdpr', 'personal information'],
            'terms_conditions' => ['terms', 'conditions', 'terms of service', 'legal']
        ];

        return $keywords[$contentType] ?? [];
    }

    /**
     * Calculate missing content impact on overall health
     *
     * @since 1.0.0
     * @param int $weight Content type weight.
     * @param int $missing Number of missing items.
     *
     * @return string Impact level.
     */
    private function calculateMissingContentImpact(int $weight, int $missing): string
    {
        $impactScore = $weight * min($missing, 5); // Cap impact calculation

        if ($impactScore >= 50) {
            return 'critical';
        }
        if ($impactScore >= 25) {
            return 'high';
        }
        if ($impactScore >= 10) {
            return 'medium';
        }
        return 'low';
    }

    /**
     * Generate completeness recommendations
     *
     * @since 1.0.0
     * @param array $missingContent Array of missing content data.
     *
     * @return array Array of recommendations.
     */
    private function generateCompletenessRecommendations(array $missingContent): array
    {
        $recommendations = [];

        foreach ($missingContent as $missing) {
            $priority = $missing['weight'] >= 15 ? 'high' : ($missing['weight'] >= 10 ? 'medium' : 'low');

            $recommendations[] = [
                'content_type' => $missing['type'],
                'priority' => $priority,
                'action' => "Create {$missing['description']}",
                'items_needed' => $missing['missing_count'],
                'estimated_time' => $this->estimateCreationTime($missing['type'], $missing['missing_count'])
            ];
        }

        return $recommendations;
    }

    /**
     * Calculate update priority for content
     *
     * @since 1.0.0
     * @param string $contentType Content type.
     * @param int    $ageDays     Age in days.
     *
     * @return string Priority level.
     */
    private function calculateUpdatePriority(string $contentType, int $ageDays): string
    {
        $highPriorityTypes = ['products', 'shipping_policy', 'return_policy'];
        $isHighPriority = in_array($contentType, $highPriorityTypes, true);

        if ($ageDays > self::OUTDATED_THRESHOLD && $isHighPriority) {
            return 'high';
        }
        if ($ageDays > self::STALE_THRESHOLD) {
            return 'medium';
        }
        return 'low';
    }

    /**
     * Calculate freshness score for a content type
     *
     * @since 1.0.0
     * @param object $stats Content statistics.
     *
     * @return int Freshness score (0-100).
     */
    private function calculateContentTypeFreshnessScore($stats): int
    {
        $avgAge = $stats->avg_age_days;
        $outdatedRatio = $stats->outdated_count / $stats->total_items;

        $baseScore = 100;

        // Penalize based on average age
        if ($avgAge > self::OUTDATED_THRESHOLD) {
            $baseScore -= 50;
        } elseif ($avgAge > self::STALE_THRESHOLD) {
            $baseScore -= 30;
        } elseif ($avgAge > self::FRESH_THRESHOLD) {
            $baseScore -= 15;
        }

        // Additional penalty for high ratio of outdated content
        $baseScore -= round($outdatedRatio * 30);

        return max(0, $baseScore);
    }

    /**
     * Get health status text from score
     *
     * @since 1.0.0
     * @param int $score Health score.
     *
     * @return string Health status text.
     */
    private function getHealthStatusText(int $score): string
    {
        if ($score >= self::HEALTH_EXCELLENT) {
            return 'Excellent';
        }
        if ($score >= self::HEALTH_GOOD) {
            return 'Good';
        }
        if ($score >= self::HEALTH_NEEDS_IMPROVEMENT) {
            return 'Needs Improvement';
        }
        if ($score >= self::HEALTH_POOR) {
            return 'Poor';
        }
        return 'Critical';
    }

    /**
     * Get freshness status from score
     *
     * @since 1.0.0
     * @param int $score Freshness score.
     *
     * @return string Freshness status.
     */
    private function getFreshnessStatus(int $score): string
    {
        if ($score >= 80) {
            return 'fresh';
        }
        if ($score >= 60) {
            return 'moderate';
        }
        if ($score >= 40) {
            return 'stale';
        }
        return 'outdated';
    }

    /**
     * Get freshness recommendation
     *
     * @since 1.0.0
     * @param string $contentType Content type.
     * @param int    $score       Freshness score.
     *
     * @return string Recommendation text.
     */
    private function getFreshnessRecommendation(string $contentType, int $score): string
    {
        $contentName = ucwords(str_replace('_', ' ', $contentType));

        if ($score >= 80) {
            return "{$contentName} content is up to date";
        }
        if ($score >= 60) {
            return "Consider reviewing {$contentName} content for updates";
        }
        if ($score >= 40) {
            return "{$contentName} content should be updated soon";
        }
        return "{$contentName} content needs immediate attention";
    }

    /**
     * Estimate effort required for content creation
     *
     * @since 1.0.0
     * @param string $contentType Content type.
     *
     * @return string Effort level.
     */
    private function estimateEffort(string $contentType): string
    {
        $highEffort = ['about', 'privacy_policy', 'terms_conditions'];
        $mediumEffort = ['shipping_policy', 'return_policy', 'faq'];

        if (in_array($contentType, $highEffort, true)) {
            return 'high';
        }
        if (in_array($contentType, $mediumEffort, true)) {
            return 'medium';
        }
        return 'low';
    }

    /**
     * Estimate effort required for quality fixes
     *
     * @since 1.0.0
     * @param string $issueType Quality issue type.
     *
     * @return string Effort level.
     */
    private function estimateQualityFixEffort(string $issueType): string
    {
        $efforts = [
            'duplicate_content' => 'medium',
            'missing_embeddings' => 'low',
            'missing_metadata' => 'low',
            'short_content' => 'high'
        ];

        return $efforts[$issueType] ?? 'medium';
    }

    /**
     * Check if template is available for content type
     *
     * @since 1.0.0
     * @param string $contentType Content type.
     *
     * @return bool True if template is available.
     */
    private function hasTemplate(string $contentType): bool
    {
        $availableTemplates = [
            'shipping_policy', 'return_policy', 'faq', 'contact_info',
            'payment_info', 'about', 'privacy_policy', 'terms_conditions'
        ];

        return in_array($contentType, $availableTemplates, true);
    }

    /**
     * Estimate time required for content creation
     *
     * @since 1.0.0
     * @param string $contentType Content type.
     * @param int    $itemsNeeded Number of items needed.
     *
     * @return string Time estimate.
     */
    private function estimateCreationTime(string $contentType, int $itemsNeeded): string
    {
        $timePerItem = [
            'products' => 15, // minutes
            'shipping_policy' => 60,
            'return_policy' => 45,
            'faq' => 20,
            'contact_info' => 30,
            'payment_info' => 30,
            'about' => 90,
            'privacy_policy' => 120,
            'terms_conditions' => 120
        ];

        $minutes = ($timePerItem[$contentType] ?? 30) * $itemsNeeded;

        if ($minutes < 60) {
            return "{$minutes} minutes";
        }
        $hours = round($minutes / 60, 1);
        return "{$hours} hours";
    }

    // Template generation methods

    /**
     * Generate shipping policy template
     *
     * @since 1.0.0
     * @return array Template data.
     */
    private function generateShippingPolicyTemplate(): array
    {
        return [
            'title' => 'Shipping Information',
            'content' => "# Shipping Information\n\n## Shipping Methods\n\n[List your available shipping methods and costs]\n\n## Processing Time\n\n[Describe your order processing time]\n\n## Delivery Times\n\n[Provide estimated delivery times for different regions]\n\n## Shipping Costs\n\n[Detail your shipping cost structure]\n\n## International Shipping\n\n[Information about international shipping if available]\n\n## Order Tracking\n\n[Explain how customers can track their orders]",
            'content_type' => 'shipping_policy',
            'template_version' => '1.0',
            'customization_needed' => [
                'shipping_methods' => 'Add your specific shipping methods and carriers',
                'processing_time' => 'Update with your actual processing time',
                'delivery_regions' => 'Customize for your delivery areas',
                'shipping_costs' => 'Update with your shipping rates'
            ]
        ];
    }

    /**
     * Generate return policy template
     *
     * @since 1.0.0
     * @return array Template data.
     */
    private function generateReturnPolicyTemplate(): array
    {
        return [
            'title' => 'Return & Refund Policy',
            'content' => "# Return & Refund Policy\n\n## Return Period\n\n[Specify your return period, e.g., 30 days]\n\n## Return Conditions\n\n[List conditions for returns - unused, original packaging, etc.]\n\n## Return Process\n\n[Step-by-step return process]\n\n## Refund Timeline\n\n[How long refunds take to process]\n\n## Return Shipping\n\n[Who pays for return shipping]\n\n## Exchanges\n\n[Information about exchanges]\n\n## Non-Returnable Items\n\n[List items that cannot be returned]",
            'content_type' => 'return_policy',
            'template_version' => '1.0',
            'customization_needed' => [
                'return_period' => 'Set your return period (e.g., 14, 30, 60 days)',
                'return_conditions' => 'Define your specific return conditions',
                'refund_timeline' => 'Update with your actual refund processing time',
                'non_returnable_items' => 'List items that cannot be returned'
            ]
        ];
    }

    /**
     * Generate FAQ template
     *
     * @since 1.0.0
     * @return array Template data.
     */
    private function generateFAQTemplate(): array
    {
        return [
            'title' => 'Frequently Asked Questions',
            'content' => "# Frequently Asked Questions\n\n## Ordering\n\n**Q: How do I place an order?**\nA: [Explain your ordering process]\n\n**Q: Can I modify or cancel my order?**\nA: [Explain modification/cancellation policy]\n\n## Shipping\n\n**Q: How long does shipping take?**\nA: [Provide shipping timeframes]\n\n**Q: Do you ship internationally?**\nA: [International shipping information]\n\n## Returns\n\n**Q: How do I return an item?**\nA: [Return process explanation]\n\n**Q: When will I receive my refund?**\nA: [Refund timeline]\n\n## Payment\n\n**Q: What payment methods do you accept?**\nA: [List accepted payment methods]\n\n**Q: Is my payment information secure?**\nA: [Security information]",
            'content_type' => 'faq',
            'template_version' => '1.0',
            'customization_needed' => [
                'ordering_process' => 'Explain your specific ordering process',
                'shipping_info' => 'Update with your shipping policies',
                'return_process' => 'Match with your return policy',
                'payment_methods' => 'List your accepted payment methods'
            ]
        ];
    }

    /**
     * Generate contact info template
     *
     * @since 1.0.0
     * @return array Template data.
     */
    private function generateContactInfoTemplate(): array
    {
        return [
            'title' => 'Contact Information',
            'content' => "# Contact Us\n\n## Customer Service\n\n**Email:** [your-email@domain.com]\n**Phone:** [your-phone-number]\n**Hours:** [your-business-hours]\n\n## Mailing Address\n\n[Your business name]\n[Street address]\n[City, State, ZIP]\n[Country]\n\n## Support\n\nFor quick support, you can:\n- Use our live chat feature\n- Send us an email\n- Call during business hours\n\n## Response Time\n\nWe typically respond to:\n- Emails within 24 hours\n- Phone calls immediately during business hours\n- Live chat within minutes",
            'content_type' => 'contact_info',
            'template_version' => '1.0',
            'customization_needed' => [
                'contact_details' => 'Add your actual contact information',
                'business_hours' => 'Update with your actual business hours',
                'response_times' => 'Set realistic response time expectations',
                'support_channels' => 'List your available support channels'
            ]
        ];
    }

    /**
     * Generate payment info template
     *
     * @since 1.0.0
     * @return array Template data.
     */
    private function generatePaymentInfoTemplate(): array
    {
        return [
            'title' => 'Payment Information',
            'content' => "# Payment Information\n\n## Accepted Payment Methods\n\n[List your accepted payment methods, e.g.:\n- Credit Cards (Visa, MasterCard, American Express)\n- PayPal\n- Bank Transfer\n- Other methods]\n\n## Payment Security\n\n[Information about payment security measures]\n\n## Payment Terms\n\n[Payment terms for different customer types]\n\n## Currency\n\n[Currencies you accept]\n\n## Billing\n\n[Billing information and invoice details]",
            'content_type' => 'payment_info',
            'template_version' => '1.0',
            'customization_needed' => [
                'payment_methods' => 'List your actual accepted payment methods',
                'security_info' => 'Describe your payment security measures',
                'currencies' => 'Specify accepted currencies',
                'billing_terms' => 'Define your billing terms'
            ]
        ];
    }

    /**
     * Generate about template
     *
     * @since 1.0.0
     * @return array Template data.
     */
    private function generateAboutTemplate(): array
    {
        return [
            'title' => 'About Us',
            'content' => "# About [Your Company Name]\n\n## Our Story\n\n[Tell your company's story - when founded, why, by whom]\n\n## Our Mission\n\n[Your company mission statement]\n\n## Our Values\n\n[List your core values]\n\n## Our Team\n\n[Information about your team]\n\n## Our Products/Services\n\n[Brief overview of what you offer]\n\n## Why Choose Us\n\n[Unique selling propositions]\n\n## Contact Us\n\n[How customers can reach you]",
            'content_type' => 'about',
            'template_version' => '1.0',
            'customization_needed' => [
                'company_story' => 'Write your unique company story',
                'mission_values' => 'Define your mission and values',
                'team_info' => 'Add information about your team',
                'unique_selling_points' => 'Highlight what makes you different'
            ]
        ];
    }

    /**
     * Generate privacy policy template
     *
     * @since 1.0.0
     * @return array Template data.
     */
    private function generatePrivacyPolicyTemplate(): array
    {
        return [
            'title' => 'Privacy Policy',
            'content' => "# Privacy Policy\n\n*Last updated: [DATE]*\n\n## Information We Collect\n\n[Describe what information you collect]\n\n## How We Use Information\n\n[Explain how you use collected information]\n\n## Information Sharing\n\n[When and how you share information]\n\n## Data Security\n\n[Your data security measures]\n\n## Cookies\n\n[Cookie usage policy]\n\n## Your Rights\n\n[User rights regarding their data]\n\n## Contact Information\n\n[How users can contact you about privacy concerns]\n\n**Note: This is a template. Please consult with a legal professional to ensure compliance with applicable privacy laws.**",
            'content_type' => 'privacy_policy',
            'template_version' => '1.0',
            'customization_needed' => [
                'legal_review' => 'IMPORTANT: Have this reviewed by a legal professional',
                'data_collection' => 'Specify exactly what data you collect',
                'data_usage' => 'Explain your data usage practices',
                'compliance' => 'Ensure compliance with GDPR, CCPA, etc.'
            ]
        ];
    }

    /**
     * Generate terms and conditions template
     *
     * @since 1.0.0
     * @return array Template data.
     */
    private function generateTermsConditionsTemplate(): array
    {
        return [
            'title' => 'Terms and Conditions',
            'content' => "# Terms and Conditions\n\n*Last updated: [DATE]*\n\n## Agreement to Terms\n\n[Agreement statement]\n\n## Use License\n\n[License terms for using your site/services]\n\n## Disclaimer\n\n[Disclaimers about your products/services]\n\n## Limitations\n\n[Limitations of liability]\n\n## Accuracy of Materials\n\n[Accuracy disclaimer for website materials]\n\n## Links\n\n[Policy on external links]\n\n## Modifications\n\n[How you handle modifications to terms]\n\n## Governing Law\n\n[Which jurisdiction's laws govern]\n\n**Note: This is a template. Please consult with a legal professional to ensure these terms are appropriate for your business.**",
            'content_type' => 'terms_conditions',
            'template_version' => '1.0',
            'customization_needed' => [
                'legal_review' => 'IMPORTANT: Have this reviewed by a legal professional',
                'jurisdiction' => 'Specify the governing jurisdiction',
                'business_specific' => 'Customize for your specific business model',
                'liability_limits' => 'Set appropriate liability limitations'
            ]
        ];
    }

    /**
     * Hook handler for content updates
     *
     * @since 1.0.0
     * @param string $contentType Content type that was updated.
     * @param int    $contentId   Content ID that was updated.
     *
     * @return void
     */
    public function onContentUpdated(string $contentType, int $contentId): void
    {
        // Clear health cache when content is updated
        \wp_cache_delete('health_score', self::CACHE_GROUP);

        Utils::logDebug('Health cache cleared due to content update', [
            'content_type' => $contentType,
            'content_id' => $contentId
        ]);
    }

    /**
     * Recalculate health score (triggered after bulk operations)
     *
     * @since 1.0.0
     * @return void
     */
    public function recalculateHealthScore(): void
    {
        Utils::logDebug('Recalculating health score after bulk operation');

        // Force recalculation
        $this->getHealthScore(true);
    }

    /**
     * Clear health cache
     *
     * @since 1.0.0
     * @return bool True on success.
     */
    public function clearCache(): bool
    {
        return \wp_cache_flush_group(self::CACHE_GROUP);
    }
}
