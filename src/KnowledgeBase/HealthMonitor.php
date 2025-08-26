<?php

/**
 * Knowledge Base Health Monitor Class
 *
 * Monitors the health, performance, and statistics of the Knowledge Base system.
 * Provides comprehensive monitoring, alerting, and reporting capabilities.
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
 * Class HealthMonitor
 *
 * Comprehensive monitoring system for the Knowledge Base that tracks performance,
 * health metrics, usage statistics, and provides alerting capabilities.
 *
 * @since 1.0.0
 */
class HealthMonitor
{
    use Singleton;

    /**
     * Health status constants
     *
     * @since 1.0.0
     */
    const STATUS_HEALTHY = 'healthy';
    const STATUS_WARNING = 'warning';
    const STATUS_CRITICAL = 'critical';
    const STATUS_UNKNOWN = 'unknown';

    /**
     * Metric collection intervals
     *
     * @since 1.0.0
     * @var array
     */
    private array $collectionIntervals = [
        'real_time' => 60,      // 1 minute
        'short_term' => 300,    // 5 minutes
        'medium_term' => 1800,  // 30 minutes
        'long_term' => 3600     // 1 hour
    ];

    /**
     * Health thresholds
     *
     * @since 1.0.0
     * @var array
     */
    private array $healthThresholds = [
        'response_time' => [
            'warning' => 2.0,   // seconds
            'critical' => 5.0   // seconds
        ],
        'error_rate' => [
            'warning' => 0.05,  // 5%
            'critical' => 0.15  // 15%
        ],
        'kb_coverage' => [
            'warning' => 0.7,   // 70%
            'critical' => 0.5   // 50%
        ],
        'memory_usage' => [
            'warning' => 0.8,   // 80% of limit
            'critical' => 0.95  // 95% of limit
        ]
    ];

    /**
     * Current health status
     *
     * @since 1.0.0
     * @var array
     */
    private array $currentHealth = [];

    /**
     * Performance metrics
     *
     * @since 1.0.0
     * @var array
     */
    private array $performanceMetrics = [];

    /**
     * Usage statistics
     *
     * @since 1.0.0
     * @var array
     */
    private array $usageStatistics = [];

    /**
     * Alert history
     *
     * @since 1.0.0
     * @var array
     */
    private array $alertHistory = [];

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    private function __construct()
    {
        $this->initializeMonitoring();
        $this->setupHooks();
    }

    /**
     * Initialize monitoring system
     *
     * @since 1.0.0
     * @return void
     */
    private function initializeMonitoring(): void
    {
        $this->loadHealthStatus();
        $this->loadPerformanceMetrics();
        $this->loadUsageStatistics();
        $this->loadAlertHistory();
    }

    /**
     * Setup WordPress hooks
     *
     * @since 1.0.0
     * @return void
     */
    private function setupHooks(): void
    {
        // Schedule regular health checks
        add_action('init', [$this, 'scheduleHealthChecks']);

        // Hook into KB operations for real-time monitoring
        add_action('woo_ai_assistant_kb_query_start', [$this, 'startPerformanceTracking']);
        add_action('woo_ai_assistant_kb_query_end', [$this, 'endPerformanceTracking']);
        add_action('woo_ai_assistant_kb_error', [$this, 'recordError']);

        // Daily/hourly statistics updates
        add_action('woo_ai_assistant_hourly_stats', [$this, 'updateHourlyStatistics']);
        add_action('woo_ai_assistant_daily_stats', [$this, 'updateDailyStatistics']);

        Utils::logDebug('HealthMonitor hooks registered');
    }

    /**
     * Schedule health check tasks
     *
     * @since 1.0.0
     * @return void
     */
    public function scheduleHealthChecks(): void
    {
        if (!wp_next_scheduled('woo_ai_assistant_health_check')) {
            wp_schedule_event(time(), 'every_15_minutes', 'woo_ai_assistant_health_check');
        }

        if (!wp_next_scheduled('woo_ai_assistant_hourly_stats')) {
            wp_schedule_event(time(), 'hourly', 'woo_ai_assistant_hourly_stats');
        }

        if (!wp_next_scheduled('woo_ai_assistant_daily_stats')) {
            wp_schedule_event(strtotime('03:30:00'), 'daily', 'woo_ai_assistant_daily_stats');
        }

        // Register the health check handler
        add_action('woo_ai_assistant_health_check', [$this, 'performHealthCheck']);
    }

    /**
     * Perform comprehensive health check
     *
     * @since 1.0.0
     * @return array Health status
     */
    public function performHealthCheck(): array
    {
        try {
            Utils::logDebug('Starting comprehensive health check');

            $health = [
                'timestamp' => current_time('c'),
                'overall_status' => self::STATUS_HEALTHY,
                'components' => [],
                'metrics' => [],
                'alerts' => [],
                'recommendations' => []
            ];

            // Check KB components
            $health['components'] = $this->checkKBComponents();

            // Check database health
            $health['database'] = $this->checkDatabaseHealth();

            // Check performance metrics
            $health['performance'] = $this->checkPerformanceMetrics();

            // Check resource usage
            $health['resources'] = $this->checkResourceUsage();

            // Check KB coverage and quality
            $health['coverage'] = $this->checkKBCoverage();

            // Generate overall status
            $health['overall_status'] = $this->calculateOverallStatus($health);

            // Generate alerts if needed
            $health['alerts'] = $this->generateAlerts($health);

            // Generate recommendations
            $health['recommendations'] = $this->generateRecommendations($health);

            // Store health status
            $this->currentHealth = $health;
            $this->saveHealthStatus();

            Utils::logDebug('Health check completed', ['status' => $health['overall_status']]);

            return $health;
        } catch (Exception $e) {
            Utils::logError('Health check failed: ' . $e->getMessage());
            return [
                'timestamp' => current_time('c'),
                'overall_status' => self::STATUS_CRITICAL,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check KB components health
     *
     * @since 1.0.0
     * @return array Component statuses
     */
    private function checkKBComponents(): array
    {
        $main = \WooAiAssistant\Main::getInstance();
        $components = [
            'scanner' => $main->getComponent('kb_scanner'),
            'indexer' => $main->getComponent('kb_indexer'),
            'vector_manager' => $main->getComponent('kb_vector_manager'),
            'ai_manager' => $main->getComponent('kb_ai_manager')
        ];

        $status = [];

        foreach ($components as $name => $component) {
            $status[$name] = [
                'loaded' => $component !== null,
                'status' => $component ? self::STATUS_HEALTHY : self::STATUS_CRITICAL,
                'last_activity' => $this->getComponentLastActivity($name),
                'error_count' => $this->getComponentErrorCount($name)
            ];
        }

        return $status;
    }

    /**
     * Check database health
     *
     * @since 1.0.0
     * @return array Database health info
     */
    private function checkDatabaseHealth(): array
    {
        global $wpdb;

        $health = [
            'connection' => false,
            'tables' => [],
            'performance' => [],
            'status' => self::STATUS_UNKNOWN
        ];

        try {
            // Check database connection using a simple query
            $health['connection'] = ($wpdb->get_var("SELECT 1") === '1');

            if (!$health['connection']) {
                $health['status'] = self::STATUS_CRITICAL;
                return $health;
            }

            // Check required tables
            $requiredTables = [
                'woo_ai_conversations',
                'woo_ai_messages',
                'woo_ai_knowledge_base',
                'woo_ai_usage_stats'
            ];

            foreach ($requiredTables as $table) {
                $fullTableName = $wpdb->prefix . $table;
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
                    DB_NAME,
                    $fullTableName
                ));

                $health['tables'][$table] = [
                    'exists' => $exists > 0,
                    'row_count' => $exists ? $wpdb->get_var("SELECT COUNT(*) FROM {$fullTableName}") : 0
                ];
            }

            // Check database performance
            $health['performance'] = $this->checkDatabasePerformance();

            // Calculate overall database status
            $allTablesExist = array_reduce($health['tables'], function ($carry, $table) {
                return $carry && $table['exists'];
            }, true);

            $health['status'] = $allTablesExist ? self::STATUS_HEALTHY : self::STATUS_CRITICAL;
        } catch (Exception $e) {
            $health['status'] = self::STATUS_CRITICAL;
            $health['error'] = $e->getMessage();
        }

        return $health;
    }

    /**
     * Check database performance
     *
     * @since 1.0.0
     * @return array Performance metrics
     */
    private function checkDatabasePerformance(): array
    {
        global $wpdb;

        $performance = [];

        try {
            // Query performance test
            $start = microtime(true);
            $wpdb->get_results("SELECT COUNT(*) FROM {$wpdb->prefix}woo_ai_knowledge_base LIMIT 1");
            $performance['kb_query_time'] = microtime(true) - $start;

            // Check slow query log status
            $slowQueryLog = $wpdb->get_var("SHOW VARIABLES LIKE 'slow_query_log'");
            $performance['slow_query_log_enabled'] = $slowQueryLog === 'ON';

            // Check table sizes
            $tableStats = $wpdb->get_results($wpdb->prepare("
                SELECT table_name, table_rows, data_length, index_length
                FROM information_schema.tables 
                WHERE table_schema = %s AND table_name LIKE %s
            ", DB_NAME, $wpdb->prefix . 'woo_ai_%'));

            $performance['table_stats'] = $tableStats;
        } catch (Exception $e) {
            $performance['error'] = $e->getMessage();
        }

        return $performance;
    }

    /**
     * Check performance metrics
     *
     * @since 1.0.0
     * @return array Performance status
     */
    private function checkPerformanceMetrics(): array
    {
        $metrics = $this->getPerformanceMetrics();

        $performance = [
            'avg_response_time' => $metrics['avg_response_time'] ?? 0,
            'error_rate' => $metrics['error_rate'] ?? 0,
            'throughput' => $metrics['throughput'] ?? 0,
            'status' => self::STATUS_HEALTHY
        ];

        // Check thresholds
        if ($performance['avg_response_time'] > $this->healthThresholds['response_time']['critical']) {
            $performance['status'] = self::STATUS_CRITICAL;
        } elseif ($performance['avg_response_time'] > $this->healthThresholds['response_time']['warning']) {
            $performance['status'] = self::STATUS_WARNING;
        }

        if ($performance['error_rate'] > $this->healthThresholds['error_rate']['critical']) {
            $performance['status'] = self::STATUS_CRITICAL;
        } elseif ($performance['error_rate'] > $this->healthThresholds['error_rate']['warning']) {
            $performance['status'] = self::STATUS_WARNING;
        }

        return $performance;
    }

    /**
     * Check resource usage
     *
     * @since 1.0.0
     * @return array Resource usage info
     */
    private function checkResourceUsage(): array
    {
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
        $currentMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);

        $resources = [
            'memory' => [
                'limit' => $memoryLimit,
                'current' => $currentMemory,
                'peak' => $peakMemory,
                'usage_percentage' => $memoryLimit > 0 ? ($currentMemory / $memoryLimit) : 0
            ],
            'disk_space' => $this->checkDiskSpace(),
            'status' => self::STATUS_HEALTHY
        ];

        // Check memory usage thresholds
        if ($resources['memory']['usage_percentage'] > $this->healthThresholds['memory_usage']['critical']) {
            $resources['status'] = self::STATUS_CRITICAL;
        } elseif ($resources['memory']['usage_percentage'] > $this->healthThresholds['memory_usage']['warning']) {
            $resources['status'] = self::STATUS_WARNING;
        }

        return $resources;
    }

    /**
     * Check KB coverage and quality
     *
     * @since 1.0.0
     * @return array Coverage metrics
     */
    private function checkKBCoverage(): array
    {
        global $wpdb;

        $coverage = [
            'total_products' => 0,
            'indexed_products' => 0,
            'total_pages' => 0,
            'indexed_pages' => 0,
            'coverage_score' => 0,
            'status' => self::STATUS_HEALTHY
        ];

        try {
            // Count total products
            $coverage['total_products'] = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->posts} 
                WHERE post_type = 'product' AND post_status = 'publish'
            ");

            // Count indexed products
            $coverage['indexed_products'] = $wpdb->get_var("
                SELECT COUNT(DISTINCT source_id) FROM {$wpdb->prefix}woo_ai_knowledge_base 
                WHERE content_type = 'product'
            ");

            // Count total pages
            $coverage['total_pages'] = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->posts} 
                WHERE post_type IN ('page', 'post') AND post_status = 'publish'
            ");

            // Count indexed pages
            $coverage['indexed_pages'] = $wpdb->get_var("
                SELECT COUNT(DISTINCT source_id) FROM {$wpdb->prefix}woo_ai_knowledge_base 
                WHERE content_type IN ('page', 'post')
            ");

            // Calculate coverage score
            $totalContent = $coverage['total_products'] + $coverage['total_pages'];
            $indexedContent = $coverage['indexed_products'] + $coverage['indexed_pages'];

            $coverage['coverage_score'] = $totalContent > 0 ? ($indexedContent / $totalContent) : 1;

            // Set status based on coverage
            if ($coverage['coverage_score'] < $this->healthThresholds['kb_coverage']['critical']) {
                $coverage['status'] = self::STATUS_CRITICAL;
            } elseif ($coverage['coverage_score'] < $this->healthThresholds['kb_coverage']['warning']) {
                $coverage['status'] = self::STATUS_WARNING;
            }
        } catch (Exception $e) {
            $coverage['error'] = $e->getMessage();
            $coverage['status'] = self::STATUS_CRITICAL;
        }

        return $coverage;
    }

    /**
     * Calculate overall system status
     *
     * @since 1.0.0
     * @param array $healthData Health check data
     * @return string Overall status
     */
    private function calculateOverallStatus(array $healthData): string
    {
        $statuses = [];

        // Collect all component statuses
        if (isset($healthData['components'])) {
            foreach ($healthData['components'] as $component) {
                $statuses[] = $component['status'];
            }
        }

        // Add system statuses
        $systemChecks = ['database', 'performance', 'resources', 'coverage'];
        foreach ($systemChecks as $check) {
            if (isset($healthData[$check]['status'])) {
                $statuses[] = $healthData[$check]['status'];
            }
        }

        // Determine overall status (worst case wins)
        if (in_array(self::STATUS_CRITICAL, $statuses)) {
            return self::STATUS_CRITICAL;
        } elseif (in_array(self::STATUS_WARNING, $statuses)) {
            return self::STATUS_WARNING;
        } elseif (in_array(self::STATUS_UNKNOWN, $statuses)) {
            return self::STATUS_UNKNOWN;
        }

        return self::STATUS_HEALTHY;
    }

    /**
     * Generate alerts based on health status
     *
     * @since 1.0.0
     * @param array $healthData Health check data
     * @return array Alerts
     */
    private function generateAlerts(array $healthData): array
    {
        $alerts = [];

        // Check for critical component failures
        if (isset($healthData['components'])) {
            foreach ($healthData['components'] as $name => $component) {
                if ($component['status'] === self::STATUS_CRITICAL) {
                    $alerts[] = [
                        'level' => 'critical',
                        'type' => 'component_failure',
                        'message' => "Knowledge Base component '{$name}' is not functioning",
                        'timestamp' => current_time('c')
                    ];
                }
            }
        }

        // Check performance alerts
        if (
            isset($healthData['performance']['status']) &&
            $healthData['performance']['status'] !== self::STATUS_HEALTHY
        ) {
            $alerts[] = [
                'level' => $healthData['performance']['status'],
                'type' => 'performance_degradation',
                'message' => 'Knowledge Base performance is degraded',
                'data' => $healthData['performance'],
                'timestamp' => current_time('c')
            ];
        }

        // Check resource alerts
        if (
            isset($healthData['resources']['status']) &&
            $healthData['resources']['status'] !== self::STATUS_HEALTHY
        ) {
            $alerts[] = [
                'level' => $healthData['resources']['status'],
                'type' => 'resource_usage',
                'message' => 'High resource usage detected',
                'data' => $healthData['resources'],
                'timestamp' => current_time('c')
            ];
        }

        // Store alerts
        $this->storeAlerts($alerts);

        return $alerts;
    }

    /**
     * Generate recommendations
     *
     * @since 1.0.0
     * @param array $healthData Health check data
     * @return array Recommendations
     */
    private function generateRecommendations(array $healthData): array
    {
        $recommendations = [];

        // Coverage recommendations
        if (
            isset($healthData['coverage']['coverage_score']) &&
            $healthData['coverage']['coverage_score'] < 0.8
        ) {
            $recommendations[] = [
                'type' => 'indexing',
                'priority' => 'high',
                'message' => 'Consider running a full re-index to improve knowledge base coverage',
                'action' => 'trigger_full_reindex'
            ];
        }

        // Performance recommendations
        if (
            isset($healthData['performance']['avg_response_time']) &&
            $healthData['performance']['avg_response_time'] > 2.0
        ) {
            $recommendations[] = [
                'type' => 'performance',
                'priority' => 'medium',
                'message' => 'Response times are slow. Consider optimizing database queries or caching',
                'action' => 'optimize_performance'
            ];
        }

        // Resource recommendations
        if (
            isset($healthData['resources']['memory']['usage_percentage']) &&
            $healthData['resources']['memory']['usage_percentage'] > 0.7
        ) {
            $recommendations[] = [
                'type' => 'resources',
                'priority' => 'medium',
                'message' => 'Memory usage is high. Consider increasing PHP memory limit',
                'action' => 'increase_memory_limit'
            ];
        }

        return $recommendations;
    }

    /**
     * Start performance tracking
     *
     * @since 1.0.0
     * @param string $operation Operation name
     * @return string Tracking ID
     */
    public function startPerformanceTracking(string $operation): string
    {
        $trackingId = uniqid($operation . '_', true);

        $this->performanceMetrics['active_operations'][$trackingId] = [
            'operation' => $operation,
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true)
        ];

        return $trackingId;
    }

    /**
     * End performance tracking
     *
     * @since 1.0.0
     * @param string $trackingId Tracking ID
     * @return array Performance data
     */
    public function endPerformanceTracking(string $trackingId): array
    {
        if (!isset($this->performanceMetrics['active_operations'][$trackingId])) {
            return [];
        }

        $operation = $this->performanceMetrics['active_operations'][$trackingId];

        $performanceData = [
            'operation' => $operation['operation'],
            'duration' => microtime(true) - $operation['start_time'],
            'memory_used' => memory_get_usage(true) - $operation['start_memory'],
            'peak_memory' => memory_get_peak_usage(true),
            'timestamp' => current_time('c')
        ];

        // Store the performance data
        $this->storePerformanceData($performanceData);

        // Clean up active operation
        unset($this->performanceMetrics['active_operations'][$trackingId]);

        return $performanceData;
    }

    /**
     * Record error
     *
     * @since 1.0.0
     * @param array $errorData Error information
     * @return void
     */
    public function recordError(array $errorData): void
    {
        $error = [
            'component' => $errorData['component'] ?? 'unknown',
            'operation' => $errorData['operation'] ?? 'unknown',
            'message' => $errorData['message'] ?? '',
            'severity' => $errorData['severity'] ?? 'error',
            'timestamp' => current_time('c'),
            'context' => $errorData['context'] ?? []
        ];

        $this->storeError($error);

        // Update error counters
        $this->updateErrorCounters($error);
    }

    /**
     * Update hourly statistics
     *
     * @since 1.0.0
     * @return void
     */
    public function updateHourlyStatistics(): void
    {
        try {
            global $wpdb;

            $hour = current_time('Y-m-d H:00:00');

            $stats = [
                'timestamp' => $hour,
                'conversations' => $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}woo_ai_conversations WHERE created_at >= %s AND created_at < %s",
                    $hour,
                    date('Y-m-d H:i:s', strtotime($hour . ' +1 hour'))
                )),
                'messages' => $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}woo_ai_messages WHERE created_at >= %s AND created_at < %s",
                    $hour,
                    date('Y-m-d H:i:s', strtotime($hour . ' +1 hour'))
                )),
                'avg_confidence' => $wpdb->get_var($wpdb->prepare(
                    "SELECT AVG(confidence) FROM {$wpdb->prefix}woo_ai_conversations WHERE created_at >= %s AND created_at < %s AND confidence > 0",
                    $hour,
                    date('Y-m-d H:i:s', strtotime($hour . ' +1 hour'))
                )),
                'error_count' => count($this->getErrorsSince($hour))
            ];

            $this->storeHourlyStats($stats);

            Utils::logDebug('Hourly statistics updated', $stats);
        } catch (Exception $e) {
            Utils::logError('Failed to update hourly statistics: ' . $e->getMessage());
        }
    }

    /**
     * Update daily statistics
     *
     * @since 1.0.0
     * @return void
     */
    public function updateDailyStatistics(): void
    {
        try {
            $this->generateDailyReport();
            $this->cleanupOldMetrics();

            Utils::logDebug('Daily statistics updated');
        } catch (Exception $e) {
            Utils::logError('Failed to update daily statistics: ' . $e->getMessage());
        }
    }

    /**
     * Get current health status
     *
     * @since 1.0.0
     * @return array Health status
     */
    public function getHealthStatus(): array
    {
        return $this->currentHealth;
    }

    /**
     * Get performance metrics
     *
     * @since 1.0.0
     * @param string $timeframe Timeframe (hour, day, week)
     * @return array Performance metrics
     */
    public function getPerformanceMetrics(string $timeframe = 'day'): array
    {
        $metrics = get_option('woo_ai_assistant_performance_metrics_' . $timeframe, []);

        return [
            'avg_response_time' => $metrics['avg_response_time'] ?? 0,
            'error_rate' => $metrics['error_rate'] ?? 0,
            'throughput' => $metrics['throughput'] ?? 0,
            'success_rate' => $metrics['success_rate'] ?? 0,
            'last_updated' => $metrics['last_updated'] ?? null
        ];
    }

    /**
     * Get usage statistics
     *
     * @since 1.0.0
     * @param string $period Period (today, week, month)
     * @return array Usage statistics
     */
    public function getUsageStatistics(string $period = 'today'): array
    {
        return get_option('woo_ai_assistant_usage_stats_' . $period, []);
    }

    /**
     * Get component last activity
     *
     * @since 1.0.0
     * @param string $component Component name
     * @return string|null Last activity timestamp
     */
    private function getComponentLastActivity(string $component): ?string
    {
        return get_option('woo_ai_assistant_' . $component . '_last_activity');
    }

    /**
     * Get component error count
     *
     * @since 1.0.0
     * @param string $component Component name
     * @return int Error count
     */
    private function getComponentErrorCount(string $component): int
    {
        return (int) get_option('woo_ai_assistant_' . $component . '_error_count', 0);
    }

    /**
     * Parse memory limit string
     *
     * @since 1.0.0
     * @param string $limit Memory limit string
     * @return int Memory limit in bytes
     */
    private function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $value = (int) $limit;

        switch ($last) {
            case 'g':
                $value *= 1024;
                // fall through
            case 'm':
                $value *= 1024;
                // fall through
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    /**
     * Check disk space
     *
     * @since 1.0.0
     * @return array Disk space info
     */
    private function checkDiskSpace(): array
    {
        $uploadDir = wp_upload_dir();
        $path = $uploadDir['basedir'];

        $freeBytes = disk_free_space($path);
        $totalBytes = disk_total_space($path);

        return [
            'free_bytes' => $freeBytes,
            'total_bytes' => $totalBytes,
            'usage_percentage' => function_exists('disk_free_space') && function_exists('disk_total_space') && $totalBytes > 0 ?
                1 - ($freeBytes / $totalBytes) : 0
        ];
    }

    /**
     * Store performance data
     *
     * @since 1.0.0
     * @param array $data Performance data
     * @return void
     */
    private function storePerformanceData(array $data): void
    {
        // Store in transient for recent data
        $recentData = get_transient('woo_ai_recent_performance') ?: [];
        $recentData[] = $data;

        // Keep only last 100 entries
        if (count($recentData) > 100) {
            $recentData = array_slice($recentData, -100);
        }

        set_transient('woo_ai_recent_performance', $recentData, HOUR_IN_SECONDS);
    }

    /**
     * Store error
     *
     * @since 1.0.0
     * @param array $error Error data
     * @return void
     */
    private function storeError(array $error): void
    {
        $errors = get_option('woo_ai_assistant_recent_errors', []);
        $errors[] = $error;

        // Keep only last 50 errors
        if (count($errors) > 50) {
            $errors = array_slice($errors, -50);
        }

        update_option('woo_ai_assistant_recent_errors', $errors);
    }

    /**
     * Store alerts
     *
     * @since 1.0.0
     * @param array $alerts Alert data
     * @return void
     */
    private function storeAlerts(array $alerts): void
    {
        if (empty($alerts)) {
            return;
        }

        $existingAlerts = get_option('woo_ai_assistant_active_alerts', []);
        $existingAlerts = array_merge($existingAlerts, $alerts);

        // Remove duplicate alerts and keep only recent ones
        $existingAlerts = array_unique($existingAlerts, SORT_REGULAR);
        $existingAlerts = array_slice($existingAlerts, -20);

        update_option('woo_ai_assistant_active_alerts', $existingAlerts);
    }

    /**
     * Update error counters
     *
     * @since 1.0.0
     * @param array $error Error data
     * @return void
     */
    private function updateErrorCounters(array $error): void
    {
        $component = $error['component'];
        $currentCount = $this->getComponentErrorCount($component);
        update_option('woo_ai_assistant_' . $component . '_error_count', $currentCount + 1);
    }

    /**
     * Get errors since timestamp
     *
     * @since 1.0.0
     * @param string $since Timestamp
     * @return array Errors
     */
    private function getErrorsSince(string $since): array
    {
        $errors = get_option('woo_ai_assistant_recent_errors', []);

        return array_filter($errors, function ($error) use ($since) {
            return $error['timestamp'] >= $since;
        });
    }

    /**
     * Store hourly statistics
     *
     * @since 1.0.0
     * @param array $stats Statistics
     * @return void
     */
    private function storeHourlyStats(array $stats): void
    {
        $hourlyStats = get_option('woo_ai_assistant_hourly_stats', []);
        $hourlyStats[$stats['timestamp']] = $stats;

        // Keep only last 168 hours (7 days)
        if (count($hourlyStats) > 168) {
            $hourlyStats = array_slice($hourlyStats, -168, null, true);
        }

        update_option('woo_ai_assistant_hourly_stats', $hourlyStats);
    }

    /**
     * Generate daily report
     *
     * @since 1.0.0
     * @return void
     */
    private function generateDailyReport(): void
    {
        $report = [
            'date' => current_time('Y-m-d'),
            'health_summary' => $this->currentHealth,
            'performance_summary' => $this->getPerformanceMetrics('day'),
            'usage_summary' => $this->getUsageStatistics('today'),
            'alerts_summary' => get_option('woo_ai_assistant_active_alerts', [])
        ];

        update_option('woo_ai_assistant_daily_report', $report);
    }

    /**
     * Cleanup old metrics
     *
     * @since 1.0.0
     * @return void
     */
    private function cleanupOldMetrics(): void
    {
        // Clean up old hourly stats (older than 30 days)
        $cutoff = date('Y-m-d H:i:s', strtotime('-30 days'));
        $hourlyStats = get_option('woo_ai_assistant_hourly_stats', []);

        foreach ($hourlyStats as $timestamp => $stats) {
            if ($timestamp < $cutoff) {
                unset($hourlyStats[$timestamp]);
            }
        }

        update_option('woo_ai_assistant_hourly_stats', $hourlyStats);

        // Clean up old errors (older than 7 days)
        $errorCutoff = date('Y-m-d H:i:s', strtotime('-7 days'));
        $errors = get_option('woo_ai_assistant_recent_errors', []);

        $errors = array_filter($errors, function ($error) use ($errorCutoff) {
            return $error['timestamp'] >= $errorCutoff;
        });

        update_option('woo_ai_assistant_recent_errors', array_values($errors));
    }

    /**
     * Load health status
     *
     * @since 1.0.0
     * @return void
     */
    private function loadHealthStatus(): void
    {
        $this->currentHealth = get_option('woo_ai_assistant_current_health', []);
    }

    /**
     * Save health status
     *
     * @since 1.0.0
     * @return void
     */
    private function saveHealthStatus(): void
    {
        update_option('woo_ai_assistant_current_health', $this->currentHealth);
    }

    /**
     * Load performance metrics
     *
     * @since 1.0.0
     * @return void
     */
    private function loadPerformanceMetrics(): void
    {
        $this->performanceMetrics = get_transient('woo_ai_performance_metrics') ?: [
            'active_operations' => []
        ];
    }

    /**
     * Load usage statistics
     *
     * @since 1.0.0
     * @return void
     */
    private function loadUsageStatistics(): void
    {
        $this->usageStatistics = get_option('woo_ai_assistant_usage_statistics', []);
    }

    /**
     * Load alert history
     *
     * @since 1.0.0
     * @return void
     */
    private function loadAlertHistory(): void
    {
        $this->alertHistory = get_option('woo_ai_assistant_alert_history', []);
    }
}
