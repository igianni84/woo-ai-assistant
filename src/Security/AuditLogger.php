<?php

/**
 * Audit Logger Class
 *
 * Provides comprehensive security event logging with database storage,
 * retention policies, and detailed audit trail capabilities. Tracks
 * security events, failed attempts, and suspicious activities.
 *
 * @package WooAiAssistant
 * @subpackage Security
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Security;

use WooAiAssistant\Common\Traits\Singleton;
use WooAiAssistant\Common\Utils;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AuditLogger
 *
 * Advanced security audit logging system that provides comprehensive tracking
 * of security events, threat attempts, and system activities. Includes
 * automatic retention management, event correlation, and reporting capabilities.
 *
 * @since 1.0.0
 */
class AuditLogger
{
    use Singleton;

    /**
     * Database table name for audit logs
     *
     * @since 1.0.0
     * @var string
     */
    private string $tableName;

    /**
     * Event severity levels
     *
     * @since 1.0.0
     * @var array
     */
    private array $severityLevels = [
        'info' => 1,
        'notice' => 2,
        'warning' => 3,
        'error' => 4,
        'critical' => 5,
        'alert' => 6,
        'emergency' => 7,
    ];

    /**
     * Event categories
     *
     * @since 1.0.0
     * @var array
     */
    private array $eventCategories = [
        'authentication' => 'Authentication Events',
        'authorization' => 'Authorization Events',
        'input_validation' => 'Input Validation Events',
        'csrf' => 'CSRF Protection Events',
        'rate_limiting' => 'Rate Limiting Events',
        'prompt_injection' => 'Prompt Injection Events',
        'data_access' => 'Data Access Events',
        'configuration' => 'Configuration Changes',
        'system' => 'System Events',
        'security_scan' => 'Security Scan Events',
    ];

    /**
     * Retention policies (in days)
     *
     * @since 1.0.0
     * @var array
     */
    private array $retentionPolicies = [
        'info' => 30,
        'notice' => 60,
        'warning' => 90,
        'error' => 180,
        'critical' => 365,
        'alert' => 730,
        'emergency' => 1095, // 3 years
    ];

    /**
     * Logging statistics
     *
     * @since 1.0.0
     * @var array
     */
    private array $statistics = [
        'total_events' => 0,
        'events_by_severity' => [],
        'events_by_category' => [],
        'retention_cleanups' => 0,
        'storage_size_mb' => 0,
    ];

    /**
     * Batch logging buffer
     *
     * @since 1.0.0
     * @var array
     */
    private array $logBuffer = [];

    /**
     * Maximum batch size
     *
     * @since 1.0.0
     * @var int
     */
    private int $maxBatchSize = 100;

    /**
     * Constructor
     *
     * Initializes the audit logger with database table creation and
     * sets up retention management.
     *
     * @since 1.0.0
     */
    private function __construct()
    {
        global $wpdb;
        $this->tableName = $wpdb->prefix . 'woo_ai_audit_log';

        $this->createDatabase();
        $this->loadStatistics();
        $this->setupHooks();
        $this->initializeStatistics();
    }

    /**
     * Setup WordPress hooks
     *
     * Registers hooks for automatic logging, cleanup, and statistics.
     *
     * @since 1.0.0
     * @return void
     */
    private function setupHooks(): void
    {
        // Automatic cleanup hooks
        add_action('wp_scheduled_delete', [$this, 'cleanupExpiredLogs']);
        add_action('woo_ai_assistant_daily_maintenance', [$this, 'performDailyMaintenance']);

        // Batch logging flush
        add_action('shutdown', [$this, 'flushLogBuffer']);
        add_action('wp_footer', [$this, 'flushLogBuffer']);
        add_action('admin_footer', [$this, 'flushLogBuffer']);

        // Statistics updates
        add_action('shutdown', [$this, 'saveStatistics']);

        // Security event hooks
        add_action('woo_ai_assistant_csrf_failure', [$this, 'logCsrfFailure'], 10, 1);
        add_action('woo_ai_assistant_rate_limit_exceeded', [$this, 'logRateLimitExceeded'], 10, 3);
        add_action('woo_ai_assistant_prompt_threat_detected', [$this, 'logPromptThreat'], 10, 3);
        add_action('woo_ai_assistant_critical_threat_detected', [$this, 'logCriticalThreat'], 10, 3);
    }

    /**
     * Log security event
     *
     * Primary method for logging security events with comprehensive
     * context and metadata capture.
     *
     * @since 1.0.0
     * @param string $eventType Type of event
     * @param string $category Event category
     * @param string $severity Severity level
     * @param string $message Event message
     * @param array $context Additional context data
     * @param array $metadata Event metadata
     * @return int|false Event ID on success, false on failure
     *
     * @example
     * ```php
     * $logger = AuditLogger::getInstance();
     * $logger->logSecurityEvent(
     *     'login_failure',
     *     'authentication',
     *     'warning',
     *     'Failed login attempt detected',
     *     ['username' => 'admin', 'ip' => '192.168.1.1']
     * );
     * ```
     */
    public function logSecurityEvent(string $eventType, string $category, string $severity, string $message, array $context = [], array $metadata = [])
    {
        // Validate severity
        if (!isset($this->severityLevels[$severity])) {
            $severity = 'info';
        }

        // Validate category
        if (!isset($this->eventCategories[$category])) {
            $category = 'system';
        }

        // Prepare event data
        $eventData = [
            'event_type' => sanitize_text_field($eventType),
            'category' => $category,
            'severity' => $severity,
            'severity_level' => $this->severityLevels[$severity],
            'message' => sanitize_textarea_field($message),
            'context' => wp_json_encode($context),
            'metadata' => wp_json_encode($metadata),
            'user_id' => get_current_user_id(),
            'ip_address' => Utils::getClientIpAddress(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'timestamp' => current_time('mysql', true),
            'created_at' => current_time('mysql'),
        ];

        // Add to batch buffer for performance
        $this->logBuffer[] = $eventData;

        // Update statistics
        $this->statistics['total_events']++;
        $this->statistics['events_by_severity'][$severity] =
            ($this->statistics['events_by_severity'][$severity] ?? 0) + 1;
        $this->statistics['events_by_category'][$category] =
            ($this->statistics['events_by_category'][$category] ?? 0) + 1;

        // Flush buffer if it's full
        if (count($this->logBuffer) >= $this->maxBatchSize) {
            return $this->flushLogBuffer();
        }

        // For high-severity events, flush immediately
        if ($this->severityLevels[$severity] >= $this->severityLevels['critical']) {
            return $this->flushLogBuffer();
        }

        return true;
    }

    /**
     * Flush log buffer
     *
     * Writes all buffered log entries to the database in a single operation
     * for improved performance.
     *
     * @since 1.0.0
     * @return int|false Number of events logged or false on failure
     */
    public function flushLogBuffer()
    {
        if (empty($this->logBuffer)) {
            return 0;
        }

        global $wpdb;

        try {
            $values = [];
            $placeholders = [];

            foreach ($this->logBuffer as $event) {
                $placeholders[] = '(%s, %s, %s, %d, %s, %s, %s, %d, %s, %s, %s, %s, %s, %s)';
                $values = array_merge($values, [
                    $event['event_type'],
                    $event['category'],
                    $event['severity'],
                    $event['severity_level'],
                    $event['message'],
                    $event['context'],
                    $event['metadata'],
                    $event['user_id'],
                    $event['ip_address'],
                    $event['user_agent'],
                    $event['request_uri'],
                    $event['request_method'],
                    $event['timestamp'],
                    $event['created_at'],
                ]);
            }

            $sql = "INSERT INTO {$this->tableName} 
                    (event_type, category, severity, severity_level, message, context, metadata, 
                     user_id, ip_address, user_agent, request_uri, request_method, timestamp, created_at) 
                    VALUES " . implode(', ', $placeholders);

            $result = $wpdb->query($wpdb->prepare($sql, $values));

            if ($result !== false) {
                $eventCount = count($this->logBuffer);
                $this->logBuffer = []; // Clear buffer

                Utils::logDebug("Flushed {$eventCount} security events to audit log");
                return $eventCount;
            }

            return false;
        } catch (\Exception $e) {
            Utils::logError('Failed to flush audit log buffer: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Query security events
     *
     * Advanced query interface for retrieving security events with
     * filtering, sorting, and pagination capabilities.
     *
     * @since 1.0.0
     * @param array $filters Query filters
     * @param int $limit Number of results to return
     * @param int $offset Results offset for pagination
     * @param string $orderBy Column to order by
     * @param string $order Sort direction (ASC or DESC)
     * @return array Query results with events and metadata
     */
    public function querySecurityEvents(array $filters = [], int $limit = 100, int $offset = 0, string $orderBy = 'created_at', string $order = 'DESC'): array
    {
        global $wpdb;

        // Sanitize inputs
        $limit = absint($limit);
        $offset = absint($offset);
        $orderBy = sanitize_sql_orderby($orderBy) ?: 'created_at';
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        // Build WHERE clause
        $whereConditions = [];
        $whereValues = [];

        if (!empty($filters['severity'])) {
            $severities = (array) $filters['severity'];
            $placeholders = array_fill(0, count($severities), '%s');
            $whereConditions[] = "severity IN (" . implode(',', $placeholders) . ")";
            $whereValues = array_merge($whereValues, $severities);
        }

        if (!empty($filters['category'])) {
            $categories = (array) $filters['category'];
            $placeholders = array_fill(0, count($categories), '%s');
            $whereConditions[] = "category IN (" . implode(',', $placeholders) . ")";
            $whereValues = array_merge($whereValues, $categories);
        }

        if (!empty($filters['event_type'])) {
            $whereConditions[] = "event_type = %s";
            $whereValues[] = $filters['event_type'];
        }

        if (!empty($filters['user_id'])) {
            $whereConditions[] = "user_id = %d";
            $whereValues[] = absint($filters['user_id']);
        }

        if (!empty($filters['ip_address'])) {
            $whereConditions[] = "ip_address = %s";
            $whereValues[] = $filters['ip_address'];
        }

        if (!empty($filters['date_from'])) {
            $whereConditions[] = "created_at >= %s";
            $whereValues[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $whereConditions[] = "created_at <= %s";
            $whereValues[] = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $whereConditions[] = "(message LIKE %s OR event_type LIKE %s)";
            $searchTerm = '%' . esc_like($filters['search']) . '%';
            $whereValues[] = $searchTerm;
            $whereValues[] = $searchTerm;
        }

        // Build final query
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $sql = "SELECT * FROM {$this->tableName} {$whereClause} ORDER BY {$orderBy} {$order} LIMIT %d OFFSET %d";
        $whereValues[] = $limit;
        $whereValues[] = $offset;

        $events = $wpdb->get_results($wpdb->prepare($sql, $whereValues), ARRAY_A);

        // Get total count for pagination
        $countSql = "SELECT COUNT(*) FROM {$this->tableName} {$whereClause}";
        $countValues = array_slice($whereValues, 0, -2); // Remove limit and offset
        $totalCount = (int) $wpdb->get_var($wpdb->prepare($countSql, $countValues));

        // Decode JSON fields
        foreach ($events as &$event) {
            $event['context'] = json_decode($event['context'], true) ?: [];
            $event['metadata'] = json_decode($event['metadata'], true) ?: [];
        }

        return [
            'events' => $events,
            'total_count' => $totalCount,
            'current_page' => floor($offset / $limit) + 1,
            'total_pages' => ceil($totalCount / $limit),
            'has_more' => ($offset + $limit) < $totalCount,
        ];
    }

    /**
     * Log CSRF failure
     *
     * Automatic handler for CSRF protection failures.
     *
     * @since 1.0.0
     * @param array $failureData CSRF failure data
     * @return void
     */
    public function logCsrfFailure(array $failureData): void
    {
        $this->logSecurityEvent(
            'csrf_failure',
            'csrf',
            'warning',
            'CSRF token verification failed',
            $failureData,
            [
                'protection_type' => 'nonce_verification',
                'automatic_log' => true,
            ]
        );
    }

    /**
     * Log rate limit exceeded
     *
     * Automatic handler for rate limit violations.
     *
     * @since 1.0.0
     * @param string $action Action that exceeded limit
     * @param string $identifier Identifier that exceeded limit
     * @param string $type Type of identifier (ip/user)
     * @return void
     */
    public function logRateLimitExceeded(string $action, string $identifier, string $type): void
    {
        $severity = ($type === 'ip') ? 'warning' : 'notice';

        $this->logSecurityEvent(
            'rate_limit_exceeded',
            'rate_limiting',
            $severity,
            "Rate limit exceeded for {$type}: {$identifier}",
            [
                'action' => $action,
                'identifier' => $identifier,
                'identifier_type' => $type,
            ],
            [
                'protection_type' => 'rate_limiting',
                'automatic_log' => true,
            ]
        );
    }

    /**
     * Log prompt threat
     *
     * Automatic handler for prompt injection threats.
     *
     * @since 1.0.0
     * @param string $prompt Original prompt
     * @param array $analysisResult Threat analysis result
     * @param array $context Additional context
     * @return void
     */
    public function logPromptThreat(string $prompt, array $analysisResult, array $context): void
    {
        $severity = $analysisResult['should_block'] ? 'error' : 'warning';

        $this->logSecurityEvent(
            'prompt_injection_detected',
            'prompt_injection',
            $severity,
            "Prompt injection threat detected: {$analysisResult['risk_label']}",
            [
                'prompt_length' => strlen($prompt),
                'risk_level' => $analysisResult['risk_label'],
                'confidence' => $analysisResult['confidence'],
                'threats_detected' => $analysisResult['threats_detected'],
                'blocked' => $analysisResult['should_block'],
                'context' => $context,
            ],
            [
                'protection_type' => 'prompt_defense',
                'automatic_log' => true,
            ]
        );
    }

    /**
     * Log critical threat
     *
     * Automatic handler for critical security threats.
     *
     * @since 1.0.0
     * @param string $prompt Original prompt
     * @param array $analysisResult Threat analysis result
     * @param array $context Additional context
     * @return void
     */
    public function logCriticalThreat(string $prompt, array $analysisResult, array $context): void
    {
        $this->logSecurityEvent(
            'critical_threat_detected',
            'prompt_injection',
            'critical',
            "Critical security threat detected and blocked",
            [
                'prompt_length' => strlen($prompt),
                'risk_level' => $analysisResult['risk_label'],
                'confidence' => $analysisResult['confidence'],
                'threats_detected' => $analysisResult['threats_detected'],
                'context' => $context,
            ],
            [
                'protection_type' => 'prompt_defense',
                'automatic_log' => true,
                'requires_attention' => true,
            ]
        );
    }

    /**
     * Generate security report
     *
     * Generates comprehensive security reports for specified time periods.
     *
     * @since 1.0.0
     * @param string $period Report period (day, week, month, custom)
     * @param array $options Report options
     * @return array Security report data
     */
    public function generateSecurityReport(string $period = 'week', array $options = []): array
    {
        // Calculate date range
        $dateRange = $this->calculateDateRange($period, $options);

        // Get events in range
        $filters = [
            'date_from' => $dateRange['from'],
            'date_to' => $dateRange['to'],
        ];

        $eventsResult = $this->querySecurityEvents($filters, 10000); // Get all events in range
        $events = $eventsResult['events'];

        // Generate report data
        $report = [
            'period' => $period,
            'date_range' => $dateRange,
            'summary' => [
                'total_events' => count($events),
                'unique_ips' => count(array_unique(array_column($events, 'ip_address'))),
                'unique_users' => count(array_unique(array_filter(array_column($events, 'user_id')))),
            ],
            'events_by_severity' => [],
            'events_by_category' => [],
            'events_by_day' => [],
            'top_threats' => [],
            'suspicious_ips' => [],
            'recommendations' => [],
        ];

        // Analyze events
        foreach ($events as $event) {
            // Group by severity
            $report['events_by_severity'][$event['severity']] =
                ($report['events_by_severity'][$event['severity']] ?? 0) + 1;

            // Group by category
            $report['events_by_category'][$event['category']] =
                ($report['events_by_category'][$event['category']] ?? 0) + 1;

            // Group by day
            $day = date('Y-m-d', strtotime($event['created_at']));
            $report['events_by_day'][$day] =
                ($report['events_by_day'][$day] ?? 0) + 1;

            // Track suspicious IPs
            if ($event['severity_level'] >= $this->severityLevels['warning']) {
                $report['suspicious_ips'][$event['ip_address']] =
                    ($report['suspicious_ips'][$event['ip_address']] ?? 0) + 1;
            }
        }

        // Sort and limit results
        arsort($report['events_by_severity']);
        arsort($report['events_by_category']);
        arsort($report['suspicious_ips']);
        ksort($report['events_by_day']);

        // Limit suspicious IPs to top 10
        $report['suspicious_ips'] = array_slice($report['suspicious_ips'], 0, 10, true);

        // Generate recommendations
        $report['recommendations'] = $this->generateSecurityRecommendations($report);

        return $report;
    }

    /**
     * Cleanup expired logs
     *
     * Removes old log entries based on retention policies.
     *
     * @since 1.0.0
     * @return int Number of logs deleted
     */
    public function cleanupExpiredLogs(): int
    {
        global $wpdb;

        $totalDeleted = 0;

        foreach ($this->retentionPolicies as $severity => $retentionDays) {
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));

            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$this->tableName} WHERE severity = %s AND created_at < %s",
                $severity,
                $cutoffDate
            ));

            if ($deleted !== false) {
                $totalDeleted += $deleted;
            }
        }

        if ($totalDeleted > 0) {
            $this->statistics['retention_cleanups']++;
            Utils::logDebug("Cleaned up {$totalDeleted} expired audit log entries");
        }

        return $totalDeleted;
    }

    /**
     * Perform daily maintenance
     *
     * Performs daily maintenance tasks including cleanup and statistics updates.
     *
     * @since 1.0.0
     * @return void
     */
    public function performDailyMaintenance(): void
    {
        // Cleanup expired logs
        $this->cleanupExpiredLogs();

        // Update storage size statistics
        $this->updateStorageStatistics();

        // Optimize table if needed
        $this->optimizeDatabase();

        Utils::logDebug('Audit logger daily maintenance completed');
    }

    /**
     * Create database table
     *
     * Creates the audit log database table if it doesn't exist.
     *
     * @since 1.0.0
     * @return void
     */
    private function createDatabase(): void
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->tableName} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(100) NOT NULL,
            category varchar(50) NOT NULL,
            severity varchar(20) NOT NULL,
            severity_level tinyint(1) NOT NULL,
            message text NOT NULL,
            context longtext,
            metadata longtext,
            user_id bigint(20) unsigned DEFAULT 0,
            ip_address varchar(45) DEFAULT '',
            user_agent text DEFAULT '',
            request_uri text DEFAULT '',
            request_method varchar(10) DEFAULT '',
            timestamp datetime NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY idx_severity (severity),
            KEY idx_category (category),
            KEY idx_event_type (event_type),
            KEY idx_user_id (user_id),
            KEY idx_ip_address (ip_address),
            KEY idx_created_at (created_at),
            KEY idx_severity_level (severity_level)
        ) {$charsetCollate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Calculate date range for reports
     *
     * Calculates from/to dates based on period specification.
     *
     * @since 1.0.0
     * @param string $period Period specification
     * @param array $options Additional options
     * @return array Date range with 'from' and 'to' keys
     */
    private function calculateDateRange(string $period, array $options = []): array
    {
        $now = current_time('timestamp');

        switch ($period) {
            case 'day':
                $from = date('Y-m-d 00:00:00', $now);
                $to = date('Y-m-d 23:59:59', $now);
                break;

            case 'week':
                $from = date('Y-m-d 00:00:00', strtotime('monday this week', $now));
                $to = date('Y-m-d 23:59:59', strtotime('sunday this week', $now));
                break;

            case 'month':
                $from = date('Y-m-01 00:00:00', $now);
                $to = date('Y-m-t 23:59:59', $now);
                break;

            case 'custom':
                $from = $options['date_from'] ?? date('Y-m-d 00:00:00', strtotime('-7 days'));
                $to = $options['date_to'] ?? date('Y-m-d 23:59:59');
                break;

            default:
                $from = date('Y-m-d 00:00:00', strtotime('-7 days'));
                $to = date('Y-m-d 23:59:59');
        }

        return [
            'from' => $from,
            'to' => $to,
            'period' => $period,
        ];
    }

    /**
     * Generate security recommendations
     *
     * Analyzes security report data to generate actionable recommendations.
     *
     * @since 1.0.0
     * @param array $reportData Security report data
     * @return array Array of recommendations
     */
    private function generateSecurityRecommendations(array $reportData): array
    {
        $recommendations = [];

        // Check for high-severity events
        $criticalEvents = $reportData['events_by_severity']['critical'] ?? 0;
        $alertEvents = $reportData['events_by_severity']['alert'] ?? 0;

        if ($criticalEvents > 0) {
            $recommendations[] = [
                'level' => 'critical',
                'message' => "Critical security events detected ({$criticalEvents}). Immediate investigation required.",
                'action' => 'investigate_critical_events',
            ];
        }

        if ($alertEvents > 0) {
            $recommendations[] = [
                'level' => 'high',
                'message' => "Alert-level security events detected ({$alertEvents}). Review recommended.",
                'action' => 'review_alert_events',
            ];
        }

        // Check for suspicious IPs
        if (!empty($reportData['suspicious_ips'])) {
            $topIp = array_key_first($reportData['suspicious_ips']);
            $eventCount = $reportData['suspicious_ips'][$topIp];

            if ($eventCount > 10) {
                $recommendations[] = [
                    'level' => 'medium',
                    'message' => "IP address {$topIp} has {$eventCount} security events. Consider blocking.",
                    'action' => 'review_ip_blocking',
                ];
            }
        }

        // Check for prompt injection attempts
        $promptEvents = $reportData['events_by_category']['prompt_injection'] ?? 0;
        if ($promptEvents > 5) {
            $recommendations[] = [
                'level' => 'medium',
                'message' => "Multiple prompt injection attempts detected ({$promptEvents}). Review AI input filtering.",
                'action' => 'strengthen_prompt_defense',
            ];
        }

        // Check for rate limiting events
        $rateLimitEvents = $reportData['events_by_category']['rate_limiting'] ?? 0;
        if ($rateLimitEvents > 50) {
            $recommendations[] = [
                'level' => 'low',
                'message' => "High number of rate limiting events ({$rateLimitEvents}). Consider adjusting limits.",
                'action' => 'review_rate_limits',
            ];
        }

        return $recommendations;
    }

    /**
     * Update storage statistics
     *
     * Updates database storage size statistics.
     *
     * @since 1.0.0
     * @return void
     */
    private function updateStorageStatistics(): void
    {
        global $wpdb;

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb,
                table_rows AS row_count
             FROM information_schema.TABLES 
             WHERE table_schema = %s AND table_name = %s",
            DB_NAME,
            $this->tableName
        ));

        if ($result) {
            $this->statistics['storage_size_mb'] = (float) $result->size_mb;
            $this->statistics['table_rows'] = (int) $result->row_count;
        }
    }

    /**
     * Optimize database table
     *
     * Optimizes the audit log table for better performance.
     *
     * @since 1.0.0
     * @return void
     */
    private function optimizeDatabase(): void
    {
        global $wpdb;

        // Only optimize if table is large
        if (($this->statistics['table_rows'] ?? 0) > 10000) {
            $wpdb->query("OPTIMIZE TABLE {$this->tableName}");
            Utils::logDebug('Audit log table optimized');
        }
    }

    /**
     * Initialize statistics
     *
     * Initializes statistics arrays with zero values.
     *
     * @since 1.0.0
     * @return void
     */
    private function initializeStatistics(): void
    {
        foreach ($this->severityLevels as $severity => $level) {
            if (!isset($this->statistics['events_by_severity'][$severity])) {
                $this->statistics['events_by_severity'][$severity] = 0;
            }
        }

        foreach ($this->eventCategories as $category => $name) {
            if (!isset($this->statistics['events_by_category'][$category])) {
                $this->statistics['events_by_category'][$category] = 0;
            }
        }
    }

    /**
     * Load statistics from database
     *
     * Loads audit logging statistics from WordPress options.
     *
     * @since 1.0.0
     * @return void
     */
    private function loadStatistics(): void
    {
        $stats = get_option('woo_ai_assistant_audit_stats', []);
        $this->statistics = array_merge($this->statistics, $stats);
    }

    /**
     * Save statistics to database
     *
     * Saves current statistics to WordPress options.
     *
     * @since 1.0.0
     * @return void
     */
    public function saveStatistics(): void
    {
        update_option('woo_ai_assistant_audit_stats', $this->statistics);
    }

    /**
     * Reset all statistics
     *
     * Resets all audit logging statistics to zero.
     *
     * @since 1.0.0
     * @return void
     */
    public function resetStatistics(): void
    {
        $this->statistics = [
            'total_events' => 0,
            'events_by_severity' => [],
            'events_by_category' => [],
            'retention_cleanups' => 0,
            'storage_size_mb' => 0,
        ];

        $this->initializeStatistics();
        $this->saveStatistics();
    }

    /**
     * Get audit statistics
     *
     * Returns comprehensive audit logging statistics.
     *
     * @since 1.0.0
     * @return array Audit logging statistics
     */
    public function getStatistics(): array
    {
        return [
            'logging_stats' => $this->statistics,
            'configuration' => [
                'severity_levels' => $this->severityLevels,
                'event_categories' => $this->eventCategories,
                'retention_policies' => $this->retentionPolicies,
            ],
            'database' => [
                'table_name' => $this->tableName,
                'buffer_size' => count($this->logBuffer),
                'max_batch_size' => $this->maxBatchSize,
            ],
        ];
    }

    /**
     * Configure retention policies
     *
     * Updates log retention policies for different severity levels.
     *
     * @since 1.0.0
     * @param array $policies New retention policies
     * @return void
     */
    public function configureRetentionPolicies(array $policies): void
    {
        foreach ($policies as $severity => $days) {
            if (isset($this->severityLevels[$severity]) && is_numeric($days) && $days > 0) {
                $this->retentionPolicies[$severity] = absint($days);
            }
        }

        update_option('woo_ai_assistant_audit_retention_policies', $this->retentionPolicies);
        Utils::logDebug('Audit log retention policies updated', $policies);
    }

    /**
     * Export security logs
     *
     * Exports security logs to various formats for external analysis.
     *
     * @since 1.0.0
     * @param array $filters Export filters
     * @param string $format Export format (csv, json, xml)
     * @return array Export result with data or file path
     */
    public function exportSecurityLogs(array $filters = [], string $format = 'csv'): array
    {
        $result = $this->querySecurityEvents($filters, 10000); // Export up to 10k events
        $events = $result['events'];

        switch ($format) {
            case 'csv':
                return $this->exportToCsv($events);
            case 'json':
                return $this->exportToJson($events);
            case 'xml':
                return $this->exportToXml($events);
            default:
                throw new \InvalidArgumentException('Unsupported export format');
        }
    }

    /**
     * Export to CSV format
     *
     * @since 1.0.0
     * @param array $events Events to export
     * @return array Export result
     */
    private function exportToCsv(array $events): array
    {
        $csvData = [];

        // Header row
        $csvData[] = [
            'ID', 'Event Type', 'Category', 'Severity', 'Message',
            'User ID', 'IP Address', 'User Agent', 'Request URI',
            'Request Method', 'Timestamp', 'Created At'
        ];

        // Data rows
        foreach ($events as $event) {
            $csvData[] = [
                $event['id'],
                $event['event_type'],
                $event['category'],
                $event['severity'],
                $event['message'],
                $event['user_id'],
                $event['ip_address'],
                $event['user_agent'],
                $event['request_uri'],
                $event['request_method'],
                $event['timestamp'],
                $event['created_at'],
            ];
        }

        return [
            'format' => 'csv',
            'data' => $csvData,
            'count' => count($events),
        ];
    }

    /**
     * Export to JSON format
     *
     * @since 1.0.0
     * @param array $events Events to export
     * @return array Export result
     */
    private function exportToJson(array $events): array
    {
        return [
            'format' => 'json',
            'data' => [
                'export_date' => current_time('mysql'),
                'event_count' => count($events),
                'events' => $events,
            ],
            'count' => count($events),
        ];
    }

    /**
     * Export to XML format
     *
     * @since 1.0.0
     * @param array $events Events to export
     * @return array Export result
     */
    private function exportToXml(array $events): array
    {
        $xml = new \SimpleXMLElement('<security_audit_log/>');
        $xml->addAttribute('export_date', current_time('mysql'));
        $xml->addAttribute('event_count', count($events));

        foreach ($events as $event) {
            $eventNode = $xml->addChild('event');
            foreach ($event as $key => $value) {
                if (is_array($value)) {
                    $value = wp_json_encode($value);
                }
                $eventNode->addChild($key, htmlspecialchars($value));
            }
        }

        return [
            'format' => 'xml',
            'data' => $xml->asXML(),
            'count' => count($events),
        ];
    }
}
