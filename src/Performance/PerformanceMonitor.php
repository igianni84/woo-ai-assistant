<?php

/**
 * Performance Monitor Class
 *
 * Comprehensive performance monitoring and profiling system for the Woo AI Assistant plugin.
 * Tracks response times, memory usage, query performance, and provides alerts and
 * benchmarking capabilities.
 *
 * @package WooAiAssistant
 * @subpackage Performance
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Performance;

use WooAiAssistant\Common\Traits\Singleton;
use WooAiAssistant\Common\Utils;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class PerformanceMonitor
 *
 * Provides comprehensive performance monitoring including response time tracking,
 * memory profiling, query optimization monitoring, and automated alerts for
 * performance regressions.
 *
 * @since 1.0.0
 */
class PerformanceMonitor
{
    use Singleton;

    /**
     * Performance thresholds
     */
    const FAQ_RESPONSE_THRESHOLD = 0.3; // 300ms
    const QUERY_SLOW_THRESHOLD = 0.1;   // 100ms
    const MEMORY_ALERT_THRESHOLD = 128; // 128MB
    const CPU_ALERT_THRESHOLD = 80;     // 80% CPU usage

    /**
     * Performance metrics storage
     *
     * @var array
     */
    private $performanceMetrics = [];

    /**
     * Active benchmarks
     *
     * @var array
     */
    private $activeBenchmarks = [];

    /**
     * Alert configurations
     *
     * @var array
     */
    private $alertConfig = [
        'email_notifications' => false,
        'log_alerts' => true,
        'alert_threshold_multiplier' => 1.5
    ];

    /**
     * Performance data storage key
     */
    const PERFORMANCE_DATA_KEY = 'woo_ai_performance_data';

    /**
     * Monitoring enabled flag
     *
     * @var bool
     */
    private $monitoringEnabled = false;

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    protected function __construct()
    {
        $this->monitoringEnabled = defined('WOO_AI_ASSISTANT_DEBUG') && WOO_AI_ASSISTANT_DEBUG;
        $this->initializeHooks();
        $this->loadPerformanceData();
    }

    /**
     * Initialize WordPress hooks and monitoring
     *
     * @since 1.0.0
     * @return void
     */
    private function initializeHooks(): void
    {
        if (!$this->monitoringEnabled) {
            return;
        }

        // WordPress performance monitoring
        add_action('init', [$this, 'startRequestMonitoring']);
        add_action('wp_footer', [$this, 'endRequestMonitoring']);
        add_action('admin_footer', [$this, 'endRequestMonitoring']);

        // Plugin-specific monitoring
        add_action('woo_ai_assistant_before_chat_response', [$this, 'startChatBenchmark']);
        add_action('woo_ai_assistant_after_chat_response', [$this, 'endChatBenchmark']);
        add_action('woo_ai_assistant_before_kb_query', [$this, 'startKnowledgeBaseBenchmark']);
        add_action('woo_ai_assistant_after_kb_query', [$this, 'endKnowledgeBaseBenchmark']);

        // Scheduled performance analysis
        add_action('woo_ai_assistant_performance_analysis', [$this, 'runPerformanceAnalysis']);

        // Memory monitoring
        add_action('shutdown', [$this, 'recordMemoryUsage']);

        // Setup scheduled events
        if (!wp_next_scheduled('woo_ai_assistant_performance_analysis')) {
            wp_schedule_event(time(), 'hourly', 'woo_ai_assistant_performance_analysis');
        }
    }

    /**
     * Start monitoring request performance
     *
     * @since 1.0.0
     * @return void
     */
    public function startRequestMonitoring(): void
    {
        if (!$this->monitoringEnabled) {
            return;
        }

        $this->startBenchmark('request', [
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'user_id' => get_current_user_id(),
            'memory_start' => memory_get_usage(true),
            'peak_memory_start' => memory_get_peak_usage(true)
        ]);
    }

    /**
     * End request monitoring and record metrics
     *
     * @since 1.0.0
     * @return void
     */
    public function endRequestMonitoring(): void
    {
        if (!$this->monitoringEnabled) {
            return;
        }

        $metrics = $this->endBenchmark('request');

        if ($metrics) {
            $this->recordRequestMetric($metrics);
            $this->checkPerformanceAlerts($metrics);
        }
    }

    /**
     * Start FAQ/chat response benchmark
     *
     * @since 1.0.0
     * @param string $conversationId Optional conversation identifier
     * @return void
     */
    public function startChatBenchmark(string $conversationId = ''): void
    {
        if (!$this->monitoringEnabled) {
            return;
        }

        $this->startBenchmark('chat_response', [
            'conversation_id' => $conversationId,
            'memory_start' => memory_get_usage(true)
        ]);
    }

    /**
     * End chat response benchmark
     *
     * @since 1.0.0
     * @param array $responseData Optional response data for analysis
     * @return void
     */
    public function endChatBenchmark(array $responseData = []): void
    {
        if (!$this->monitoringEnabled) {
            return;
        }

        $metrics = $this->endBenchmark('chat_response');

        if ($metrics) {
            $metrics['response_data'] = $responseData;
            $this->recordChatMetric($metrics);

            // Check FAQ response time threshold
            if ($metrics['execution_time'] > self::FAQ_RESPONSE_THRESHOLD) {
                $this->triggerAlert('slow_faq_response', $metrics);
            }
        }
    }

    /**
     * Start knowledge base query benchmark
     *
     * @since 1.0.0
     * @param string $query The knowledge base query
     * @return void
     */
    public function startKnowledgeBaseBenchmark(string $query): void
    {
        if (!$this->monitoringEnabled) {
            return;
        }

        $this->startBenchmark('kb_query', [
            'query' => $query,
            'query_hash' => md5($query),
            'memory_start' => memory_get_usage(true)
        ]);
    }

    /**
     * End knowledge base benchmark
     *
     * @since 1.0.0
     * @param array $results Optional query results for analysis
     * @return void
     */
    public function endKnowledgeBaseBenchmark(array $results = []): void
    {
        if (!$this->monitoringEnabled) {
            return;
        }

        $metrics = $this->endBenchmark('kb_query');

        if ($metrics) {
            $metrics['results_count'] = count($results);
            $this->recordKnowledgeBaseMetric($metrics);
        }
    }

    /**
     * Start a performance benchmark
     *
     * Starts timing and resource monitoring for a specific operation.
     * Supports nested benchmarks and automatic resource tracking.
     *
     * @since 1.0.0
     * @param string $benchmarkId Unique identifier for the benchmark
     * @param array $metadata Optional metadata to store with benchmark
     *
     * @return bool True if benchmark started successfully
     *
     * @throws \InvalidArgumentException When benchmark ID is empty.
     *
     * @example
     * ```php
     * $monitor = PerformanceMonitor::getInstance();
     * $monitor->startBenchmark('product_search', [
     *     'search_term' => 'laptop',
     *     'filters' => ['category' => 'electronics']
     * ]);
     * ```
     */
    public function startBenchmark(string $benchmarkId, array $metadata = []): bool
    {
        if (empty($benchmarkId)) {
            throw new \InvalidArgumentException('Benchmark ID cannot be empty');
        }

        if (!$this->monitoringEnabled) {
            return false;
        }

        $this->activeBenchmarks[$benchmarkId] = [
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
            'start_peak_memory' => memory_get_peak_usage(true),
            'metadata' => $metadata,
            'nested_benchmarks' => []
        ];

        return true;
    }

    /**
     * End a performance benchmark and return metrics
     *
     * @since 1.0.0
     * @param string $benchmarkId The benchmark identifier
     *
     * @return array|false Benchmark metrics or false if not found
     */
    public function endBenchmark(string $benchmarkId): array|false
    {
        if (!$this->monitoringEnabled || !isset($this->activeBenchmarks[$benchmarkId])) {
            return false;
        }

        $benchmark = $this->activeBenchmarks[$benchmarkId];
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        $endPeakMemory = memory_get_peak_usage(true);

        $metrics = [
            'benchmark_id' => $benchmarkId,
            'start_time' => $benchmark['start_time'],
            'end_time' => $endTime,
            'execution_time' => $endTime - $benchmark['start_time'],
            'memory_usage' => $endMemory - $benchmark['start_memory'],
            'peak_memory_usage' => $endPeakMemory - $benchmark['start_peak_memory'],
            'memory_start' => $benchmark['start_memory'],
            'memory_end' => $endMemory,
            'peak_memory_start' => $benchmark['start_peak_memory'],
            'peak_memory_end' => $endPeakMemory,
            'metadata' => $benchmark['metadata'],
            'timestamp' => current_time('mysql'),
            'nested_benchmarks' => $benchmark['nested_benchmarks']
        ];

        // Remove from active benchmarks
        unset($this->activeBenchmarks[$benchmarkId]);

        return $metrics;
    }

    /**
     * Record query performance metric
     *
     * @since 1.0.0
     * @param array $queryData Query performance data
     *
     * @return void
     */
    public function recordQueryMetric(array $queryData): void
    {
        if (!$this->monitoringEnabled) {
            return;
        }

        $this->performanceMetrics['queries'][] = [
            'query' => $queryData['query'] ?? '',
            'execution_time' => $queryData['execution_time'] ?? 0,
            'results_count' => $queryData['results_count'] ?? 0,
            'is_slow' => $queryData['is_slow'] ?? false,
            'timestamp' => current_time('mysql')
        ];

        // Limit stored metrics to prevent memory issues
        if (count($this->performanceMetrics['queries']) > 1000) {
            $this->performanceMetrics['queries'] = array_slice(
                $this->performanceMetrics['queries'],
                -500
            );
        }
    }

    /**
     * Record request performance metrics
     *
     * @since 1.0.0
     * @param array $metrics Request performance metrics
     *
     * @return void
     */
    private function recordRequestMetric(array $metrics): void
    {
        $this->performanceMetrics['requests'][] = [
            'url' => $metrics['metadata']['url'] ?? '',
            'method' => $metrics['metadata']['method'] ?? 'GET',
            'execution_time' => $metrics['execution_time'],
            'memory_usage' => $metrics['memory_usage'],
            'peak_memory_usage' => $metrics['peak_memory_usage'],
            'user_id' => $metrics['metadata']['user_id'] ?? 0,
            'timestamp' => $metrics['timestamp']
        ];

        // Maintain rolling window of metrics
        if (count($this->performanceMetrics['requests']) > 500) {
            $this->performanceMetrics['requests'] = array_slice(
                $this->performanceMetrics['requests'],
                -250
            );
        }
    }

    /**
     * Record chat/FAQ response metrics
     *
     * @since 1.0.0
     * @param array $metrics Chat performance metrics
     *
     * @return void
     */
    private function recordChatMetric(array $metrics): void
    {
        $this->performanceMetrics['chat_responses'][] = [
            'conversation_id' => $metrics['metadata']['conversation_id'] ?? '',
            'execution_time' => $metrics['execution_time'],
            'memory_usage' => $metrics['memory_usage'],
            'meets_threshold' => $metrics['execution_time'] <= self::FAQ_RESPONSE_THRESHOLD,
            'response_type' => $this->detectResponseType($metrics['response_data'] ?? []),
            'timestamp' => $metrics['timestamp']
        ];

        // Maintain metrics window
        if (count($this->performanceMetrics['chat_responses']) > 200) {
            $this->performanceMetrics['chat_responses'] = array_slice(
                $this->performanceMetrics['chat_responses'],
                -100
            );
        }
    }

    /**
     * Record knowledge base query metrics
     *
     * @since 1.0.0
     * @param array $metrics Knowledge base performance metrics
     *
     * @return void
     */
    private function recordKnowledgeBaseMetric(array $metrics): void
    {
        $this->performanceMetrics['kb_queries'][] = [
            'query_hash' => $metrics['metadata']['query_hash'] ?? '',
            'execution_time' => $metrics['execution_time'],
            'results_count' => $metrics['results_count'],
            'memory_usage' => $metrics['memory_usage'],
            'timestamp' => $metrics['timestamp']
        ];

        if (count($this->performanceMetrics['kb_queries']) > 200) {
            $this->performanceMetrics['kb_queries'] = array_slice(
                $this->performanceMetrics['kb_queries'],
                -100
            );
        }
    }

    /**
     * Record memory usage snapshot
     *
     * @since 1.0.0
     * @return void
     */
    public function recordMemoryUsage(): void
    {
        if (!$this->monitoringEnabled) {
            return;
        }

        $memoryUsage = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);

        $this->performanceMetrics['memory_snapshots'][] = [
            'current_usage' => $memoryUsage,
            'peak_usage' => $peakMemory,
            'usage_mb' => round($memoryUsage / 1024 / 1024, 2),
            'peak_mb' => round($peakMemory / 1024 / 1024, 2),
            'timestamp' => current_time('mysql')
        ];

        // Check memory alerts
        if ($memoryUsage > (self::MEMORY_ALERT_THRESHOLD * 1024 * 1024)) {
            $this->triggerAlert('high_memory_usage', [
                'current_usage_mb' => round($memoryUsage / 1024 / 1024, 2),
                'threshold_mb' => self::MEMORY_ALERT_THRESHOLD
            ]);
        }

        // Keep only recent snapshots
        if (count($this->performanceMetrics['memory_snapshots']) > 100) {
            $this->performanceMetrics['memory_snapshots'] = array_slice(
                $this->performanceMetrics['memory_snapshots'],
                -50
            );
        }
    }

    /**
     * Run comprehensive performance analysis
     *
     * @since 1.0.0
     * @return void
     */
    public function runPerformanceAnalysis(): void
    {
        if (!$this->monitoringEnabled) {
            return;
        }

        $analysis = $this->generatePerformanceReport();

        // Store analysis results
        update_option('woo_ai_performance_analysis', [
            'analysis' => $analysis,
            'generated_at' => current_time('mysql')
        ]);

        // Check for performance regressions
        $this->checkPerformanceRegressions($analysis);

        // Persist current metrics
        $this->savePerformanceData();
    }

    /**
     * Generate comprehensive performance report
     *
     * @since 1.0.0
     * @return array Performance analysis report
     */
    public function generatePerformanceReport(): array
    {
        $report = [
            'generated_at' => current_time('mysql'),
            'monitoring_period' => $this->getMonitoringPeriod(),
            'faq_performance' => $this->analyzeFaqPerformance(),
            'query_performance' => $this->analyzeQueryPerformance(),
            'memory_analysis' => $this->analyzeMemoryUsage(),
            'request_analysis' => $this->analyzeRequestPerformance(),
            'recommendations' => $this->generateRecommendations()
        ];

        return $report;
    }

    /**
     * Analyze FAQ/chat response performance
     *
     * @since 1.0.0
     * @return array FAQ performance analysis
     */
    private function analyzeFaqPerformance(): array
    {
        $chatResponses = $this->performanceMetrics['chat_responses'] ?? [];

        if (empty($chatResponses)) {
            return ['status' => 'no_data'];
        }

        $executionTimes = array_column($chatResponses, 'execution_time');
        $meetingThreshold = array_filter($chatResponses, function ($response) {
            return $response['meets_threshold'];
        });

        return [
            'total_responses' => count($chatResponses),
            'avg_response_time' => round(array_sum($executionTimes) / count($executionTimes), 4),
            'max_response_time' => max($executionTimes),
            'min_response_time' => min($executionTimes),
            'threshold_compliance' => round((count($meetingThreshold) / count($chatResponses)) * 100, 2),
            'p95_response_time' => $this->calculatePercentile($executionTimes, 95),
            'p99_response_time' => $this->calculatePercentile($executionTimes, 99),
            'slow_responses' => count(array_filter($executionTimes, function ($time) {
                return $time > self::FAQ_RESPONSE_THRESHOLD;
            }))
        ];
    }

    /**
     * Analyze database query performance
     *
     * @since 1.0.0
     * @return array Query performance analysis
     */
    private function analyzeQueryPerformance(): array
    {
        $queries = $this->performanceMetrics['queries'] ?? [];

        if (empty($queries)) {
            return ['status' => 'no_data'];
        }

        $executionTimes = array_column($queries, 'execution_time');
        $slowQueries = array_filter($queries, function ($query) {
            return $query['is_slow'];
        });

        return [
            'total_queries' => count($queries),
            'avg_query_time' => round(array_sum($executionTimes) / count($executionTimes), 4),
            'slow_queries_count' => count($slowQueries),
            'slow_query_percentage' => round((count($slowQueries) / count($queries)) * 100, 2),
            'p95_query_time' => $this->calculatePercentile($executionTimes, 95),
            'slowest_query_time' => max($executionTimes)
        ];
    }

    /**
     * Analyze memory usage patterns
     *
     * @since 1.0.0
     * @return array Memory usage analysis
     */
    private function analyzeMemoryUsage(): array
    {
        $snapshots = $this->performanceMetrics['memory_snapshots'] ?? [];

        if (empty($snapshots)) {
            return ['status' => 'no_data'];
        }

        $usageMb = array_column($snapshots, 'usage_mb');
        $peakMb = array_column($snapshots, 'peak_mb');

        return [
            'avg_memory_usage_mb' => round(array_sum($usageMb) / count($usageMb), 2),
            'max_memory_usage_mb' => max($usageMb),
            'avg_peak_memory_mb' => round(array_sum($peakMb) / count($peakMb), 2),
            'max_peak_memory_mb' => max($peakMb),
            'memory_alerts' => count(array_filter($usageMb, function ($usage) {
                return $usage > self::MEMORY_ALERT_THRESHOLD;
            })),
            'memory_efficiency' => $this->calculateMemoryEfficiency($snapshots)
        ];
    }

    /**
     * Analyze request performance patterns
     *
     * @since 1.0.0
     * @return array Request performance analysis
     */
    private function analyzeRequestPerformance(): array
    {
        $requests = $this->performanceMetrics['requests'] ?? [];

        if (empty($requests)) {
            return ['status' => 'no_data'];
        }

        $executionTimes = array_column($requests, 'execution_time');

        return [
            'total_requests' => count($requests),
            'avg_request_time' => round(array_sum($executionTimes) / count($executionTimes), 4),
            'p95_request_time' => $this->calculatePercentile($executionTimes, 95),
            'slowest_request_time' => max($executionTimes),
            'request_types' => $this->analyzeRequestTypes($requests)
        ];
    }

    /**
     * Generate performance recommendations
     *
     * @since 1.0.0
     * @return array Performance optimization recommendations
     */
    private function generateRecommendations(): array
    {
        $recommendations = [];

        // FAQ performance recommendations
        $faqAnalysis = $this->analyzeFaqPerformance();
        if (isset($faqAnalysis['threshold_compliance']) && $faqAnalysis['threshold_compliance'] < 90) {
            $recommendations[] = [
                'type' => 'faq_optimization',
                'priority' => 'high',
                'message' => 'FAQ response time compliance is below 90%. Consider implementing more aggressive caching.',
                'compliance' => $faqAnalysis['threshold_compliance'] . '%'
            ];
        }

        // Query performance recommendations
        $queryAnalysis = $this->analyzeQueryPerformance();
        if (isset($queryAnalysis['slow_query_percentage']) && $queryAnalysis['slow_query_percentage'] > 10) {
            $recommendations[] = [
                'type' => 'query_optimization',
                'priority' => 'high',
                'message' => 'High percentage of slow queries detected. Review database indexes and query optimization.',
                'percentage' => $queryAnalysis['slow_query_percentage'] . '%'
            ];
        }

        // Memory usage recommendations
        $memoryAnalysis = $this->analyzeMemoryUsage();
        if (isset($memoryAnalysis['max_memory_usage_mb']) && $memoryAnalysis['max_memory_usage_mb'] > self::MEMORY_ALERT_THRESHOLD) {
            $recommendations[] = [
                'type' => 'memory_optimization',
                'priority' => 'medium',
                'message' => 'High memory usage detected. Consider implementing memory optimization strategies.',
                'max_usage' => $memoryAnalysis['max_memory_usage_mb'] . 'MB'
            ];
        }

        return $recommendations;
    }

    /**
     * Check for performance alerts and trigger notifications
     *
     * @since 1.0.0
     * @param array $metrics Performance metrics to check
     *
     * @return void
     */
    private function checkPerformanceAlerts(array $metrics): void
    {
        // Check response time alerts
        if (isset($metrics['execution_time']) && $metrics['execution_time'] > self::FAQ_RESPONSE_THRESHOLD * 2) {
            $this->triggerAlert('critical_response_time', $metrics);
        }

        // Check memory alerts
        if (isset($metrics['memory_usage']) && $metrics['memory_usage'] > (64 * 1024 * 1024)) { // 64MB
            $this->triggerAlert('high_memory_request', $metrics);
        }
    }

    /**
     * Check for performance regressions
     *
     * @since 1.0.0
     * @param array $currentAnalysis Current performance analysis
     *
     * @return void
     */
    private function checkPerformanceRegressions(array $currentAnalysis): void
    {
        $previousAnalysis = get_option('woo_ai_performance_analysis');

        if (!$previousAnalysis || !isset($previousAnalysis['analysis'])) {
            return;
        }

        $previous = $previousAnalysis['analysis'];

        // Check FAQ performance regression
        if (isset($current['faq_performance']['avg_response_time'], $previous['faq_performance']['avg_response_time'])) {
            $current = $currentAnalysis;
            $regressionThreshold = 1.2; // 20% increase

            if ($current['faq_performance']['avg_response_time'] > ($previous['faq_performance']['avg_response_time'] * $regressionThreshold)) {
                $this->triggerAlert('performance_regression', [
                    'type' => 'faq_response_time',
                    'current' => $current['faq_performance']['avg_response_time'],
                    'previous' => $previous['faq_performance']['avg_response_time'],
                    'increase_percentage' => round((($current['faq_performance']['avg_response_time'] / $previous['faq_performance']['avg_response_time']) - 1) * 100, 2)
                ]);
            }
        }
    }

    /**
     * Trigger performance alert
     *
     * @since 1.0.0
     * @param string $alertType Type of alert
     * @param array $data Alert data
     *
     * @return void
     */
    private function triggerAlert(string $alertType, array $data): void
    {
        $alert = [
            'type' => $alertType,
            'timestamp' => current_time('mysql'),
            'data' => $data,
            'severity' => $this->getAlertSeverity($alertType)
        ];

        // Log alert
        if ($this->alertConfig['log_alerts']) {
            error_log('Woo AI Assistant Performance Alert: ' . json_encode($alert));
        }

        // Send email notification if configured
        if ($this->alertConfig['email_notifications']) {
            $this->sendAlertEmail($alert);
        }

        // Store alert
        $this->storeAlert($alert);

        // Hook for custom alert handling
        do_action('woo_ai_assistant_performance_alert', $alert);
    }

    /**
     * Get alert severity level
     *
     * @since 1.0.0
     * @param string $alertType Alert type
     *
     * @return string Severity level
     */
    private function getAlertSeverity(string $alertType): string
    {
        $severityMap = [
            'slow_faq_response' => 'warning',
            'critical_response_time' => 'critical',
            'high_memory_usage' => 'warning',
            'high_memory_request' => 'critical',
            'performance_regression' => 'high'
        ];

        return $severityMap[$alertType] ?? 'low';
    }

    /**
     * Store alert in database
     *
     * @since 1.0.0
     * @param array $alert Alert data
     *
     * @return void
     */
    private function storeAlert(array $alert): void
    {
        $alerts = get_option('woo_ai_performance_alerts', []);
        $alerts[] = $alert;

        // Keep only recent alerts (last 100)
        if (count($alerts) > 100) {
            $alerts = array_slice($alerts, -100);
        }

        update_option('woo_ai_performance_alerts', $alerts);
    }

    /**
     * Send alert email notification
     *
     * @since 1.0.0
     * @param array $alert Alert data
     *
     * @return void
     */
    private function sendAlertEmail(array $alert): void
    {
        $adminEmail = get_option('admin_email');
        if (!$adminEmail) {
            return;
        }

        $subject = sprintf('[Woo AI Assistant] Performance Alert: %s', ucwords(str_replace('_', ' ', $alert['type'])));

        $message = sprintf(
            "A performance alert has been triggered for your Woo AI Assistant plugin.\n\n" .
            "Alert Type: %s\n" .
            "Severity: %s\n" .
            "Timestamp: %s\n" .
            "Details: %s\n\n" .
            "Please check your WordPress dashboard for more information.",
            $alert['type'],
            $alert['severity'],
            $alert['timestamp'],
            json_encode($alert['data'], JSON_PRETTY_PRINT)
        );

        wp_mail($adminEmail, $subject, $message);
    }

    /**
     * Calculate percentile from array of values
     *
     * @since 1.0.0
     * @param array $values Array of numeric values
     * @param int $percentile Percentile to calculate (0-100)
     *
     * @return float Calculated percentile value
     */
    private function calculatePercentile(array $values, int $percentile): float
    {
        if (empty($values)) {
            return 0;
        }

        sort($values);
        $index = ($percentile / 100) * (count($values) - 1);

        if (floor($index) == $index) {
            return $values[$index];
        }

        $lower = $values[floor($index)];
        $upper = $values[ceil($index)];
        $fraction = $index - floor($index);

        return $lower + ($fraction * ($upper - $lower));
    }

    /**
     * Calculate memory efficiency score
     *
     * @since 1.0.0
     * @param array $snapshots Memory usage snapshots
     *
     * @return float Efficiency score (0-100)
     */
    private function calculateMemoryEfficiency(array $snapshots): float
    {
        if (empty($snapshots)) {
            return 0;
        }

        $totalSnapshots = count($snapshots);
        $efficientSnapshots = 0;

        foreach ($snapshots as $snapshot) {
            if ($snapshot['usage_mb'] < self::MEMORY_ALERT_THRESHOLD) {
                $efficientSnapshots++;
            }
        }

        return round(($efficientSnapshots / $totalSnapshots) * 100, 2);
    }

    /**
     * Analyze request types and their performance
     *
     * @since 1.0.0
     * @param array $requests Request data
     *
     * @return array Request type analysis
     */
    private function analyzeRequestTypes(array $requests): array
    {
        $types = [];

        foreach ($requests as $request) {
            $method = $request['method'];
            if (!isset($types[$method])) {
                $types[$method] = [
                    'count' => 0,
                    'total_time' => 0,
                    'avg_time' => 0
                ];
            }

            $types[$method]['count']++;
            $types[$method]['total_time'] += $request['execution_time'];
            $types[$method]['avg_time'] = $types[$method]['total_time'] / $types[$method]['count'];
        }

        return $types;
    }

    /**
     * Detect response type from response data
     *
     * @since 1.0.0
     * @param array $responseData Response data to analyze
     *
     * @return string Detected response type
     */
    private function detectResponseType(array $responseData): string
    {
        if (empty($responseData)) {
            return 'unknown';
        }

        // Simple heuristic to detect response type
        if (isset($responseData['product_recommendations'])) {
            return 'product_recommendation';
        } elseif (isset($responseData['faq_answer'])) {
            return 'faq_response';
        } elseif (isset($responseData['general_response'])) {
            return 'general_chat';
        }

        return 'mixed';
    }

    /**
     * Get monitoring period information
     *
     * @since 1.0.0
     * @return array Monitoring period details
     */
    private function getMonitoringPeriod(): array
    {
        $allMetrics = array_merge(
            $this->performanceMetrics['requests'] ?? [],
            $this->performanceMetrics['chat_responses'] ?? [],
            $this->performanceMetrics['queries'] ?? []
        );

        if (empty($allMetrics)) {
            return ['status' => 'no_data'];
        }

        $timestamps = array_column($allMetrics, 'timestamp');
        sort($timestamps);

        return [
            'start_time' => $timestamps[0] ?? null,
            'end_time' => end($timestamps) ?? null,
            'total_data_points' => count($allMetrics)
        ];
    }

    /**
     * Load performance data from storage
     *
     * @since 1.0.0
     * @return void
     */
    private function loadPerformanceData(): void
    {
        $data = get_option(self::PERFORMANCE_DATA_KEY, []);
        $this->performanceMetrics = $data;
    }

    /**
     * Save performance data to storage
     *
     * @since 1.0.0
     * @return void
     */
    private function savePerformanceData(): void
    {
        update_option(self::PERFORMANCE_DATA_KEY, $this->performanceMetrics);
    }

    /**
     * Get current performance statistics
     *
     * @since 1.0.0
     * @return array Current performance statistics
     */
    public function getPerformanceStats(): array
    {
        return [
            'monitoring_enabled' => $this->monitoringEnabled,
            'active_benchmarks' => count($this->activeBenchmarks),
            'stored_requests' => count($this->performanceMetrics['requests'] ?? []),
            'stored_chat_responses' => count($this->performanceMetrics['chat_responses'] ?? []),
            'stored_queries' => count($this->performanceMetrics['queries'] ?? []),
            'memory_snapshots' => count($this->performanceMetrics['memory_snapshots'] ?? []),
            'last_analysis' => get_option('woo_ai_performance_analysis')['generated_at'] ?? 'Never'
        ];
    }

    /**
     * Clear all performance data
     *
     * @since 1.0.0
     * @return bool True on success
     */
    public function clearPerformanceData(): bool
    {
        $this->performanceMetrics = [];
        delete_option(self::PERFORMANCE_DATA_KEY);
        delete_option('woo_ai_performance_analysis');
        delete_option('woo_ai_performance_alerts');

        return true;
    }
}
