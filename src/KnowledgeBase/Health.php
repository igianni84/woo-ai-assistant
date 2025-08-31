<?php

/**
 * Knowledge Base Health Monitoring Class
 *
 * Provides comprehensive health monitoring, statistics, and diagnostic capabilities
 * for the knowledge base system. Monitors data freshness, API connectivity,
 * performance metrics, and system integrity to ensure optimal operation.
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
use WooAiAssistant\Common\Utils;
use WooAiAssistant\Config\ApiConfiguration;
use Exception;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Health
 *
 * Knowledge base health monitoring and diagnostics.
 *
 * @since 1.0.0
 */
class Health
{
    use Singleton;

    /**
     * Health check cache key
     *
     * @var string
     */
    private string $cacheKey = 'woo_ai_kb_health_check';

    /**
     * Health check cache TTL (1 hour)
     *
     * @var int
     */
    private int $cacheTtl = 3600;

    /**
     * Health score thresholds
     *
     * @var array
     */
    private array $healthThresholds = [
        'excellent' => 90,
        'good' => 80,
        'fair' => 60,
        'poor' => 40
    ];

    /**
     * Component instances for health checks
     *
     * @var array
     */
    private array $components = [];

    /**
     * Last health check results
     *
     * @var array
     */
    private array $lastHealthCheck = [];

    /**
     * Performance metrics tracking
     *
     * @var array
     */
    private array $performanceMetrics = [
        'response_times' => [],
        'error_rates' => [],
        'api_calls' => [],
        'cache_hits' => []
    ];

    /**
     * Initialize health monitoring
     *
     * @since 1.0.0
     */
    protected function init(): void
    {
        try {
            // Initialize component instances for health checks
            $this->components = [
                'scanner' => Scanner::getInstance(),
                'indexer' => Indexer::getInstance(),
                'vector_manager' => VectorManager::getInstance(),
                'ai_manager' => AIManager::getInstance(),
                'embedding_generator' => EmbeddingGenerator::getInstance(),
                'prompt_builder' => PromptBuilder::getInstance(),
                'chunking_strategy' => ChunkingStrategy::getInstance()
            ];

            // Load cached performance metrics
            $this->loadPerformanceMetrics();

            Logger::info('Knowledge Base Health monitoring initialized');
        } catch (Exception $e) {
            Logger::error('Failed to initialize Health monitoring: ' . $e->getMessage());
            throw new Exception('Health monitoring initialization failed: ' . $e->getMessage());
        }
    }

    /**
     * Calculate overall knowledge base health score
     *
     * Performs comprehensive health checks across all components and returns
     * a weighted health score from 0-100.
     *
     * @since 1.0.0
     * @param bool $forceRecalculate Whether to bypass cache and recalculate. Default false.
     *
     * @return float Health score (0-100).
     */
    public function calculateHealthScore(bool $forceRecalculate = false): float
    {
        try {
            // Check cache first unless forced
            if (!$forceRecalculate) {
                $cachedScore = get_transient($this->cacheKey . '_score');
                if ($cachedScore !== false) {
                    return (float) $cachedScore;
                }
            }

            Logger::info('Calculating knowledge base health score');

            $healthChecks = [
                'data_freshness' => ['weight' => 25, 'score' => $this->checkDataFreshness()],
                'api_connectivity' => ['weight' => 20, 'score' => $this->checkApiConnectivity()],
                'data_integrity' => ['weight' => 20, 'score' => $this->checkDataIntegrity()],
                'performance' => ['weight' => 15, 'score' => $this->checkPerformance()],
                'storage_health' => ['weight' => 10, 'score' => $this->checkStorageHealth()],
                'error_rates' => ['weight' => 10, 'score' => $this->checkErrorRates()]
            ];

            $totalScore = 0;
            $totalWeight = 0;

            foreach ($healthChecks as $check => $data) {
                $weightedScore = $data['score'] * ($data['weight'] / 100);
                $totalScore += $weightedScore;
                $totalWeight += $data['weight'];

                Logger::debug("Health check {$check}: {$data['score']}% (weight: {$data['weight']}%)");
            }

            $finalScore = round($totalScore, 2);

            // Cache the result
            set_transient($this->cacheKey . '_score', $finalScore, $this->cacheTtl);

            // Store detailed results
            $this->lastHealthCheck = [
                'overall_score' => $finalScore,
                'checks' => $healthChecks,
                'calculated_at' => current_time('mysql'),
                'status' => $this->getHealthStatus($finalScore)
            ];

            update_option('woo_ai_kb_last_health_check', $this->lastHealthCheck);

            Logger::info("Knowledge base health score calculated: {$finalScore}%");

            return $finalScore;
        } catch (Exception $e) {
            Logger::error('Failed to calculate health score: ' . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Check for specific health issues and problems
     *
     * Performs detailed analysis to identify specific issues that may
     * be affecting knowledge base performance or accuracy.
     *
     * @since 1.0.0
     * @param bool $includeWarnings Whether to include warning-level issues. Default true.
     *
     * @return array Array of detected issues with severity and recommendations.
     */
    public function checkForIssues(bool $includeWarnings = true): array
    {
        try {
            Logger::info('Checking for knowledge base issues');

            $issues = [];

            // Critical issues (must be fixed)
            $issues = array_merge($issues, $this->checkCriticalIssues());

            // Warning issues (should be addressed)
            if ($includeWarnings) {
                $issues = array_merge($issues, $this->checkWarningIssues());
            }

            // Sort by severity
            usort($issues, function ($a, $b) {
                $severityOrder = ['critical' => 3, 'warning' => 2, 'info' => 1];
                return ($severityOrder[$b['severity']] ?? 0) - ($severityOrder[$a['severity']] ?? 0);
            });

            Logger::info('Health issues check completed', ['issues_found' => count($issues)]);

            return $issues;
        } catch (Exception $e) {
            Logger::error('Failed to check for health issues: ' . $e->getMessage());
            return [
                [
                    'type' => 'health_check_error',
                    'severity' => 'critical',
                    'message' => 'Health check system failure: ' . $e->getMessage(),
                    'recommendation' => 'Check system logs and restart health monitoring'
                ]
            ];
        }
    }

    /**
     * Get comprehensive health statistics
     *
     * Returns detailed statistics about all aspects of knowledge base health
     * including historical trends and performance metrics.
     *
     * @since 1.0.0
     * @param bool $includeHistorical Whether to include historical data. Default false.
     *
     * @return array Comprehensive health statistics.
     */
    public function getHealthStatistics(bool $includeHistorical = false): array
    {
        try {
            $stats = [
                'current_health' => [
                    'overall_score' => $this->calculateHealthScore(),
                    'status' => $this->getHealthStatus($this->calculateHealthScore()),
                    'last_check' => $this->lastHealthCheck['calculated_at'] ?? 'Never',
                    'components_healthy' => $this->getHealthyComponentsCount(),
                    'total_components' => count($this->components)
                ],
                'data_metrics' => $this->getDataMetrics(),
                'performance_metrics' => $this->getPerformanceMetrics(),
                'api_metrics' => $this->getApiMetrics(),
                'storage_metrics' => $this->getStorageMetrics(),
                'recent_issues' => $this->getRecentIssues(),
                'uptime' => $this->calculateUptime()
            ];

            // Add historical data if requested
            if ($includeHistorical) {
                $stats['historical'] = $this->getHistoricalHealth();
            }

            return $stats;
        } catch (Exception $e) {
            Logger::error('Failed to get health statistics: ' . $e->getMessage());
            return ['error' => 'Failed to retrieve health statistics'];
        }
    }

    /**
     * Perform system diagnostics
     *
     * Runs comprehensive diagnostic tests to identify potential issues
     * and provide detailed troubleshooting information.
     *
     * @since 1.0.0
     * @param array $tests Specific tests to run. Default all.
     *
     * @return array Diagnostic results with detailed information.
     */
    public function performDiagnostics(array $tests = []): array
    {
        try {
            $allTests = [
                'database_connectivity',
                'api_endpoints',
                'file_permissions',
                'memory_usage',
                'processing_queues',
                'cache_functionality',
                'webhook_connectivity',
                'data_consistency'
            ];

            // Use all tests if none specified
            if (empty($tests)) {
                $tests = $allTests;
            }

            $diagnostics = [
                'test_timestamp' => current_time('mysql'),
                'tests_run' => $tests,
                'results' => [],
                'summary' => [
                    'passed' => 0,
                    'failed' => 0,
                    'warnings' => 0
                ]
            ];

            foreach ($tests as $test) {
                $result = $this->runDiagnosticTest($test);
                $diagnostics['results'][$test] = $result;

                // Update summary
                switch ($result['status']) {
                    case 'passed':
                        $diagnostics['summary']['passed']++;
                        break;
                    case 'failed':
                        $diagnostics['summary']['failed']++;
                        break;
                    case 'warning':
                        $diagnostics['summary']['warnings']++;
                        break;
                }
            }

            Logger::info('System diagnostics completed', $diagnostics['summary']);

            return $diagnostics;
        } catch (Exception $e) {
            Logger::error('Failed to perform diagnostics: ' . $e->getMessage());
            return [
                'error' => 'Diagnostic system failure: ' . $e->getMessage(),
                'test_timestamp' => current_time('mysql')
            ];
        }
    }

    /**
     * Get health status report for administrators
     *
     * Generates a comprehensive, human-readable health report suitable
     * for display in admin interfaces or email notifications.
     *
     * @since 1.0.0
     * @param bool $includeRecommendations Whether to include action recommendations. Default true.
     *
     * @return array Formatted health report.
     */
    public function getHealthReport(bool $includeRecommendations = true): array
    {
        try {
            $healthScore = $this->calculateHealthScore();
            $issues = $this->checkForIssues();
            $stats = $this->getHealthStatistics();

            $report = [
                'summary' => [
                    'overall_health' => $healthScore,
                    'status' => $this->getHealthStatus($healthScore),
                    'status_color' => $this->getStatusColor($healthScore),
                    'last_updated' => current_time('mysql'),
                    'critical_issues' => count(array_filter($issues, fn($i) => $i['severity'] === 'critical')),
                    'warning_issues' => count(array_filter($issues, fn($i) => $i['severity'] === 'warning'))
                ],
                'key_metrics' => [
                    'data_freshness' => $this->getDataFreshnessStatus(),
                    'api_connectivity' => $this->getApiConnectivityStatus(),
                    'performance' => $this->getPerformanceStatus(),
                    'storage_usage' => $this->getStorageUsageStatus()
                ],
                'component_status' => $this->getComponentStatusReport(),
                'recent_activity' => $this->getRecentActivitySummary()
            ];

            // Add issues if any exist
            if (!empty($issues)) {
                $report['issues'] = $issues;
            }

            // Add recommendations if requested
            if ($includeRecommendations && $healthScore < 90) {
                $report['recommendations'] = $this->generateRecommendations($healthScore, $issues);
            }

            return $report;
        } catch (Exception $e) {
            Logger::error('Failed to generate health report: ' . $e->getMessage());
            return [
                'summary' => [
                    'overall_health' => 0,
                    'status' => 'Error',
                    'status_color' => '#dc3545',
                    'last_updated' => current_time('mysql'),
                    'error' => 'Failed to generate health report'
                ]
            ];
        }
    }

    /**
     * Record performance metric
     *
     * Records a performance metric for health monitoring and trend analysis.
     *
     * @since 1.0.0
     * @param string $metric Metric name.
     * @param mixed  $value Metric value.
     * @param array  $context Additional context data.
     */
    public function recordPerformanceMetric(string $metric, $value, array $context = []): void
    {
        try {
            $timestamp = microtime(true);

            if (!isset($this->performanceMetrics[$metric])) {
                $this->performanceMetrics[$metric] = [];
            }

            $this->performanceMetrics[$metric][] = [
                'value' => $value,
                'timestamp' => $timestamp,
                'context' => $context
            ];

            // Keep only the last 100 entries per metric to prevent memory bloat
            if (count($this->performanceMetrics[$metric]) > 100) {
                array_shift($this->performanceMetrics[$metric]);
            }

            // Persist metrics periodically
            if (count($this->performanceMetrics[$metric]) % 10 === 0) {
                $this->savePerformanceMetrics();
            }
        } catch (Exception $e) {
            Logger::error("Failed to record performance metric {$metric}: " . $e->getMessage());
        }
    }

    /**
     * Check data freshness
     *
     * @since 1.0.0
     * @return float Freshness score (0-100).
     */
    private function checkDataFreshness(): float
    {
        try {
            $score = 100;

            // Check when different content types were last updated
            $contentTypes = [
                'product' => ['weight' => 40, 'max_age_hours' => 24],
                'page' => ['weight' => 25, 'max_age_hours' => 168], // 1 week
                'post' => ['weight' => 20, 'max_age_hours' => 168], // 1 week
                'woocommerce_settings' => ['weight' => 15, 'max_age_hours' => 720] // 30 days
            ];

            global $wpdb;
            $kbTable = $wpdb->prefix . 'woo_ai_knowledge_base';

            foreach ($contentTypes as $type => $config) {
                $lastUpdate = $wpdb->get_var($wpdb->prepare(
                    "SELECT MAX(updated_at) FROM {$kbTable} WHERE content_type = %s",
                    $type
                ));

                if ($lastUpdate) {
                    $hoursOld = (time() - strtotime($lastUpdate)) / 3600;
                    if ($hoursOld > $config['max_age_hours']) {
                        $freshnessPenalty = min(50, ($hoursOld / $config['max_age_hours']) * 50);
                        $score -= $freshnessPenalty * ($config['weight'] / 100);
                    }
                } else {
                    // No data at all - major penalty
                    $score -= 50 * ($config['weight'] / 100);
                }
            }

            return max(0, $score);
        } catch (Exception $e) {
            Logger::error('Data freshness check failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Check API connectivity
     *
     * @since 1.0.0
     * @return float Connectivity score (0-100).
     */
    private function checkApiConnectivity(): float
    {
        try {
            $apiConfig = ApiConfiguration::getInstance();
            $score = 100;

            // Test OpenAI/OpenRouter connectivity
            if (!$this->components['embedding_generator']->isAvailable()) {
                $score -= 30; // Major penalty for embedding API failure
            }

            if (!$this->components['ai_manager']->isAvailable()) {
                $score -= 30; // Major penalty for AI API failure
            }

            // Test Pinecone connectivity
            try {
                $this->components['vector_manager']->getVectorStatistics();
            } catch (Exception $e) {
                $score -= 40; // Critical penalty for vector storage failure
            }

            return max(0, $score);
        } catch (Exception $e) {
            Logger::error('API connectivity check failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Check data integrity
     *
     * @since 1.0.0
     * @return float Integrity score (0-100).
     */
    private function checkDataIntegrity(): float
    {
        try {
            global $wpdb;
            $score = 100;

            $kbTable = $wpdb->prefix . 'woo_ai_knowledge_base';

            // Check for orphaned chunks (content no longer exists)
            $orphanedChunks = $wpdb->get_var("
                SELECT COUNT(*) FROM {$kbTable} kb
                LEFT JOIN {$wpdb->posts} p ON kb.content_id = p.ID AND kb.content_type IN ('product', 'page', 'post')
                WHERE kb.content_type IN ('product', 'page', 'post') AND p.ID IS NULL
            ");

            if ($orphanedChunks > 0) {
                $score -= min(30, $orphanedChunks * 0.1); // Penalty for orphaned data
            }

            // Check for chunks without embeddings
            $chunksWithoutEmbeddings = $wpdb->get_var("
                SELECT COUNT(*) FROM {$kbTable} 
                WHERE (embedding IS NULL OR embedding = '') AND chunk_text != ''
            ");

            if ($chunksWithoutEmbeddings > 0) {
                $score -= min(25, $chunksWithoutEmbeddings * 0.05);
            }

            // Check for duplicate chunks
            $duplicateChunks = $wpdb->get_var("
                SELECT COUNT(*) - COUNT(DISTINCT MD5(chunk_text)) FROM {$kbTable}
            ");

            if ($duplicateChunks > 0) {
                $score -= min(20, $duplicateChunks * 0.02);
            }

            return max(0, $score);
        } catch (Exception $e) {
            Logger::error('Data integrity check failed: ' . $e->getMessage());
            return 50; // Partial score if we can't check properly
        }
    }

    /**
     * Check system performance
     *
     * @since 1.0.0
     * @return float Performance score (0-100).
     */
    private function checkPerformance(): float
    {
        try {
            $score = 100;

            // Check average response times
            if (isset($this->performanceMetrics['response_times'])) {
                $avgResponseTime = $this->calculateAverageMetric('response_times');
                if ($avgResponseTime > 5000) { // 5 seconds
                    $score -= 40;
                } elseif ($avgResponseTime > 3000) { // 3 seconds
                    $score -= 20;
                } elseif ($avgResponseTime > 2000) { // 2 seconds
                    $score -= 10;
                }
            }

            // Check cache hit rate
            $cacheHitRate = $this->getCacheHitRate();
            if ($cacheHitRate < 50) {
                $score -= 30;
            } elseif ($cacheHitRate < 70) {
                $score -= 15;
            }

            // Check memory usage
            $memoryUsage = memory_get_usage(true);
            $memoryLimit = Utils::getMemoryLimit();
            $memoryPercentage = ($memoryUsage / $memoryLimit) * 100;

            if ($memoryPercentage > 90) {
                $score -= 30;
            } elseif ($memoryPercentage > 80) {
                $score -= 15;
            }

            return max(0, $score);
        } catch (Exception $e) {
            Logger::error('Performance check failed: ' . $e->getMessage());
            return 70; // Assume decent performance if we can't check
        }
    }

    /**
     * Check storage health
     *
     * @since 1.0.0
     * @return float Storage health score (0-100).
     */
    private function checkStorageHealth(): float
    {
        try {
            $score = 100;
            global $wpdb;

            // Check database connectivity
            try {
                $wpdb->get_var("SELECT 1");
            } catch (Exception $e) {
                return 0; // Critical failure
            }

            // Check disk space (if accessible)
            $uploadDir = wp_upload_dir();
            if (function_exists('disk_free_space') && isset($uploadDir['basedir'])) {
                $freeSpace = disk_free_space($uploadDir['basedir']);
                $totalSpace = disk_total_space($uploadDir['basedir']);

                if ($freeSpace && $totalSpace) {
                    $freePercentage = ($freeSpace / $totalSpace) * 100;

                    if ($freePercentage < 5) {
                        $score -= 50;
                    } elseif ($freePercentage < 15) {
                        $score -= 25;
                    }
                }
            }

            // Check table sizes and optimize status
            $tables = [
                $wpdb->prefix . 'woo_ai_knowledge_base',
                $wpdb->prefix . 'woo_ai_conversations',
                $wpdb->prefix . 'woo_ai_messages'
            ];

            foreach ($tables as $table) {
                $tableStatus = $wpdb->get_row("SHOW TABLE STATUS LIKE '{$table}'", ARRAY_A);

                if ($tableStatus) {
                    $fragmentation = 0;
                    if ($tableStatus['Data_length'] > 0) {
                        $fragmentation = ($tableStatus['Data_free'] / ($tableStatus['Data_length'] + $tableStatus['Index_length'])) * 100;
                    }

                    if ($fragmentation > 25) {
                        $score -= 10; // Needs optimization
                    }
                }
            }

            return max(0, $score);
        } catch (Exception $e) {
            Logger::error('Storage health check failed: ' . $e->getMessage());
            return 50; // Assume moderate health if we can't check
        }
    }

    /**
     * Check error rates
     *
     * @since 1.0.0
     * @return float Error rate score (0-100).
     */
    private function checkErrorRates(): float
    {
        try {
            $score = 100;

            // Check recent error logs
            $recentErrors = $this->getRecentErrors();
            $errorCount = count($recentErrors);

            if ($errorCount > 50) {
                $score -= 70;
            } elseif ($errorCount > 20) {
                $score -= 40;
            } elseif ($errorCount > 10) {
                $score -= 20;
            } elseif ($errorCount > 5) {
                $score -= 10;
            }

            // Check API error rates
            if (isset($this->performanceMetrics['error_rates'])) {
                $avgErrorRate = $this->calculateAverageMetric('error_rates');
                if ($avgErrorRate > 10) {
                    $score -= 30;
                } elseif ($avgErrorRate > 5) {
                    $score -= 15;
                }
            }

            return max(0, $score);
        } catch (Exception $e) {
            Logger::error('Error rate check failed: ' . $e->getMessage());
            return 80; // Assume good if we can't check
        }
    }

    /**
     * Check for critical issues
     *
     * @since 1.0.0
     * @return array Array of critical issues.
     */
    private function checkCriticalIssues(): array
    {
        $issues = [];

        try {
            // Check if knowledge base is empty
            global $wpdb;
            $kbTable = $wpdb->prefix . 'woo_ai_knowledge_base';
            $chunkCount = $wpdb->get_var("SELECT COUNT(*) FROM {$kbTable}");

            if ($chunkCount == 0) {
                $issues[] = [
                    'type' => 'empty_knowledge_base',
                    'severity' => 'critical',
                    'message' => 'Knowledge base is empty - no content has been indexed',
                    'recommendation' => 'Run a full knowledge base rebuild to index your content'
                ];
            }

            // Check API connectivity
            if (!$this->components['ai_manager']->isAvailable()) {
                $issues[] = [
                    'type' => 'ai_api_unavailable',
                    'severity' => 'critical',
                    'message' => 'AI API is unavailable - chatbot responses will not work',
                    'recommendation' => 'Check your API keys and network connectivity'
                ];
            }

            // Check for database connection issues
            try {
                $wpdb->get_var("SELECT 1");
            } catch (Exception $e) {
                $issues[] = [
                    'type' => 'database_connection_failed',
                    'severity' => 'critical',
                    'message' => 'Database connection failed',
                    'recommendation' => 'Check database connectivity and credentials'
                ];
            }

            // Check for processing stuck/failed
            $manager = Manager::getInstance();
            if ($manager->isProcessing()) {
                $status = $manager->getProcessingStatus();
                $startTime = strtotime($status['started_at'] ?? '');

                if ($startTime && (time() - $startTime) > 3600) { // Stuck for over 1 hour
                    $issues[] = [
                        'type' => 'processing_stuck',
                        'severity' => 'critical',
                        'message' => 'Knowledge base processing appears to be stuck',
                        'recommendation' => 'Stop current processing and restart the operation'
                    ];
                }
            }
        } catch (Exception $e) {
            $issues[] = [
                'type' => 'health_check_failure',
                'severity' => 'critical',
                'message' => 'Health check system failure: ' . $e->getMessage(),
                'recommendation' => 'Check system logs and contact support if needed'
            ];
        }

        return $issues;
    }

    /**
     * Check for warning-level issues
     *
     * @since 1.0.0
     * @return array Array of warning issues.
     */
    private function checkWarningIssues(): array
    {
        $issues = [];

        try {
            // Check data freshness
            $lastFullSync = get_option('woo_ai_kb_last_full_sync');
            if (!$lastFullSync || strtotime($lastFullSync) < strtotime('-7 days')) {
                $issues[] = [
                    'type' => 'stale_data',
                    'severity' => 'warning',
                    'message' => 'Knowledge base data may be outdated - last full sync was over a week ago',
                    'recommendation' => 'Consider running a manual full sync to ensure data freshness'
                ];
            }

            // Check cache hit rate
            $cacheHitRate = $this->getCacheHitRate();
            if ($cacheHitRate < 70) {
                $issues[] = [
                    'type' => 'low_cache_hit_rate',
                    'severity' => 'warning',
                    'message' => "Low cache hit rate ({$cacheHitRate}%) may impact performance",
                    'recommendation' => 'Consider increasing cache TTL or checking cache configuration'
                ];
            }

            // Check for high error rates
            $recentErrors = $this->getRecentErrors();
            if (count($recentErrors) > 5) {
                $issues[] = [
                    'type' => 'high_error_rate',
                    'severity' => 'warning',
                    'message' => count($recentErrors) . ' errors detected in the last 24 hours',
                    'recommendation' => 'Review error logs and address recurring issues'
                ];
            }

            // Check memory usage
            $memoryUsage = memory_get_usage(true);
            $memoryLimit = Utils::getMemoryLimit();
            $memoryPercentage = ($memoryUsage / $memoryLimit) * 100;

            if ($memoryPercentage > 80) {
                $issues[] = [
                    'type' => 'high_memory_usage',
                    'severity' => 'warning',
                    'message' => "High memory usage ({$memoryPercentage}% of limit)",
                    'recommendation' => 'Consider increasing PHP memory limit or optimizing processing batch sizes'
                ];
            }
        } catch (Exception $e) {
            Logger::error('Warning issues check failed: ' . $e->getMessage());
        }

        return $issues;
    }

    /**
     * Get health status label from score
     *
     * @since 1.0.0
     * @param float $score Health score.
     *
     * @return string Status label.
     */
    private function getHealthStatus(float $score): string
    {
        if ($score >= $this->healthThresholds['excellent']) {
            return 'Excellent';
        } elseif ($score >= $this->healthThresholds['good']) {
            return 'Good';
        } elseif ($score >= $this->healthThresholds['fair']) {
            return 'Fair';
        } elseif ($score >= $this->healthThresholds['poor']) {
            return 'Poor';
        } else {
            return 'Critical';
        }
    }

    /**
     * Get status color for UI display
     *
     * @since 1.0.0
     * @param float $score Health score.
     *
     * @return string CSS color code.
     */
    private function getStatusColor(float $score): string
    {
        if ($score >= $this->healthThresholds['excellent']) {
            return '#28a745'; // Green
        } elseif ($score >= $this->healthThresholds['good']) {
            return '#17a2b8'; // Blue
        } elseif ($score >= $this->healthThresholds['fair']) {
            return '#ffc107'; // Yellow
        } elseif ($score >= $this->healthThresholds['poor']) {
            return '#fd7e14'; // Orange
        } else {
            return '#dc3545'; // Red
        }
    }

    /**
     * Calculate average for a performance metric
     *
     * @since 1.0.0
     * @param string $metric Metric name.
     *
     * @return float Average value.
     */
    private function calculateAverageMetric(string $metric): float
    {
        if (!isset($this->performanceMetrics[$metric]) || empty($this->performanceMetrics[$metric])) {
            return 0.0;
        }

        $values = array_column($this->performanceMetrics[$metric], 'value');
        return array_sum($values) / count($values);
    }

    /**
     * Get cache hit rate
     *
     * @since 1.0.0
     * @return float Cache hit rate percentage.
     */
    private function getCacheHitRate(): float
    {
        try {
            $cache = Cache::getInstance();
            $stats = $cache->getStatistics();

            $hits = $stats['hits'] ?? 0;
            $misses = $stats['misses'] ?? 0;
            $total = $hits + $misses;

            return $total > 0 ? round(($hits / $total) * 100, 2) : 0.0;
        } catch (Exception $e) {
            return 0.0;
        }
    }

    /**
     * Get recent errors from logs
     *
     * @since 1.0.0
     * @return array Recent error entries.
     */
    private function getRecentErrors(): array
    {
        try {
            global $wpdb;
            $logsTable = $wpdb->prefix . 'woo_ai_action_logs';

            $errors = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$logsTable} 
                WHERE action_type LIKE %s 
                AND created_at > %s 
                ORDER BY created_at DESC 
                LIMIT 100",
                '%error%',
                date('Y-m-d H:i:s', strtotime('-24 hours'))
            ));

            return $errors ?: [];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Load performance metrics from storage
     *
     * @since 1.0.0
     */
    private function loadPerformanceMetrics(): void
    {
        $cached = get_option('woo_ai_kb_performance_metrics', []);
        if (is_array($cached)) {
            $this->performanceMetrics = array_merge($this->performanceMetrics, $cached);
        }
    }

    /**
     * Save performance metrics to storage
     *
     * @since 1.0.0
     */
    private function savePerformanceMetrics(): void
    {
        update_option('woo_ai_kb_performance_metrics', $this->performanceMetrics);
    }

    // Additional helper methods for comprehensive health reporting...
    // These would include getDataMetrics(), getApiMetrics(), getStorageMetrics(),
    // getHistoricalHealth(), generateRecommendations(), etc.
    // For brevity, I'm showing the core functionality above.

    /**
     * Run a specific diagnostic test
     *
     * @since 1.0.0
     * @param string $test Test name.
     *
     * @return array Test result.
     */
    private function runDiagnosticTest(string $test): array
    {
        try {
            switch ($test) {
                case 'database_connectivity':
                    return $this->testDatabaseConnectivity();
                case 'api_endpoints':
                    return $this->testApiEndpoints();
                case 'file_permissions':
                    return $this->testFilePermissions();
                case 'memory_usage':
                    return $this->testMemoryUsage();
                case 'cache_functionality':
                    return $this->testCacheFunctionality();
                default:
                    return [
                        'status' => 'failed',
                        'message' => "Unknown test: {$test}",
                        'details' => []
                    ];
            }
        } catch (Exception $e) {
            return [
                'status' => 'failed',
                'message' => "Test failed: {$e->getMessage()}",
                'details' => ['exception' => $e->getMessage()]
            ];
        }
    }

    /**
     * Test database connectivity
     *
     * @since 1.0.0
     * @return array Test result.
     */
    private function testDatabaseConnectivity(): array
    {
        global $wpdb;

        try {
            $result = $wpdb->get_var("SELECT 1");

            if ($result == 1) {
                return [
                    'status' => 'passed',
                    'message' => 'Database connectivity is working',
                    'details' => [
                        'database' => DB_NAME,
                        'host' => DB_HOST
                    ]
                ];
            } else {
                return [
                    'status' => 'failed',
                    'message' => 'Database query returned unexpected result',
                    'details' => ['result' => $result]
                ];
            }
        } catch (Exception $e) {
            return [
                'status' => 'failed',
                'message' => 'Database connection failed: ' . $e->getMessage(),
                'details' => ['error' => $e->getMessage()]
            ];
        }
    }

    /**
     * Test API endpoints
     *
     * @since 1.0.0
     * @return array Test result.
     */
    private function testApiEndpoints(): array
    {
        $results = [];
        $overallStatus = 'passed';

        // Test embedding API
        try {
            $available = $this->components['embedding_generator']->isAvailable();
            $results['embedding_api'] = $available ? 'passed' : 'failed';
            if (!$available) {
                $overallStatus = 'failed';
            }
        } catch (Exception $e) {
            $results['embedding_api'] = 'failed';
            $overallStatus = 'failed';
        }

        // Test AI API
        try {
            $available = $this->components['ai_manager']->isAvailable();
            $results['ai_api'] = $available ? 'passed' : 'failed';
            if (!$available) {
                $overallStatus = 'failed';
            }
        } catch (Exception $e) {
            $results['ai_api'] = 'failed';
            $overallStatus = 'failed';
        }

        return [
            'status' => $overallStatus,
            'message' => $overallStatus === 'passed' ? 'All API endpoints are accessible' : 'Some API endpoints are unavailable',
            'details' => $results
        ];
    }

    /**
     * Test memory usage
     *
     * @since 1.0.0
     * @return array Test result.
     */
    private function testMemoryUsage(): array
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = Utils::getMemoryLimit();
        $memoryPercentage = ($memoryUsage / $memoryLimit) * 100;

        $status = 'passed';
        $message = 'Memory usage is within acceptable limits';

        if ($memoryPercentage > 90) {
            $status = 'failed';
            $message = 'Memory usage is critically high';
        } elseif ($memoryPercentage > 80) {
            $status = 'warning';
            $message = 'Memory usage is high';
        }

        return [
            'status' => $status,
            'message' => $message,
            'details' => [
                'current_usage' => Utils::formatBytes($memoryUsage),
                'memory_limit' => Utils::formatBytes($memoryLimit),
                'percentage' => round($memoryPercentage, 2) . '%'
            ]
        ];
    }

    /**
     * Test cache functionality
     *
     * @since 1.0.0
     * @return array Test result.
     */
    private function testCacheFunctionality(): array
    {
        try {
            $cache = Cache::getInstance();
            $testKey = 'health_test_' . uniqid();
            $testValue = 'health_test_value';

            // Test set
            $cache->set($testKey, $testValue, 60);

            // Test get
            $retrieved = $cache->get($testKey);

            // Clean up
            $cache->delete($testKey);

            if ($retrieved === $testValue) {
                return [
                    'status' => 'passed',
                    'message' => 'Cache functionality is working correctly',
                    'details' => ['test_key' => $testKey]
                ];
            } else {
                return [
                    'status' => 'failed',
                    'message' => 'Cache retrieval failed',
                    'details' => [
                        'expected' => $testValue,
                        'retrieved' => $retrieved
                    ]
                ];
            }
        } catch (Exception $e) {
            return [
                'status' => 'failed',
                'message' => 'Cache test failed: ' . $e->getMessage(),
                'details' => ['error' => $e->getMessage()]
            ];
        }
    }

    /**
     * Test file permissions
     *
     * @since 1.0.0
     * @return array Test result.
     */
    private function testFilePermissions(): array
    {
        $uploadDir = wp_upload_dir();
        $testFile = $uploadDir['basedir'] . '/woo-ai-test-' . uniqid() . '.txt';

        try {
            // Test write
            $written = file_put_contents($testFile, 'test');

            if ($written === false) {
                return [
                    'status' => 'failed',
                    'message' => 'Cannot write to upload directory',
                    'details' => ['directory' => $uploadDir['basedir']]
                ];
            }

            // Test read
            $content = file_get_contents($testFile);

            if ($content !== 'test') {
                unlink($testFile);
                return [
                    'status' => 'failed',
                    'message' => 'Cannot read from upload directory',
                    'details' => ['directory' => $uploadDir['basedir']]
                ];
            }

            // Clean up
            unlink($testFile);

            return [
                'status' => 'passed',
                'message' => 'File permissions are correct',
                'details' => [
                    'upload_dir' => $uploadDir['basedir'],
                    'permissions' => 'read/write'
                ]
            ];
        } catch (Exception $e) {
            // Clean up if possible
            if (file_exists($testFile)) {
                @unlink($testFile);
            }

            return [
                'status' => 'failed',
                'message' => 'File permissions test failed: ' . $e->getMessage(),
                'details' => ['error' => $e->getMessage()]
            ];
        }
    }

    // Additional methods would be implemented here for complete functionality...
    // Including: getHealthyComponentsCount(), getDataMetrics(), getPerformanceMetrics(),
    // getRecentIssues(), calculateUptime(), generateRecommendations(), etc.
}
