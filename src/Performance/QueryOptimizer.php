<?php

/**
 * Query Optimizer Class
 *
 * Handles database query optimization, indexing strategies, and query logging
 * for the Woo AI Assistant plugin. Implements query caching, prepared statement
 * optimization, and performance monitoring.
 *
 * @package WooAiAssistant
 * @subpackage Performance
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Performance;

use WooAiAssistant\Common\Traits\Singleton;
use WooAiAssistant\Performance\PerformanceMonitor;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class QueryOptimizer
 *
 * Provides database query optimization, logging, and performance monitoring
 * for all plugin database operations. Implements intelligent query caching,
 * index management, and slow query detection.
 *
 * @since 1.0.0
 */
class QueryOptimizer
{
    use Singleton;

    /**
     * Query log for performance monitoring
     *
     * @var array
     */
    private $queryLog = [];

    /**
     * Slow query threshold in seconds
     */
    const SLOW_QUERY_THRESHOLD = 0.1; // 100ms

    /**
     * Query cache TTL in seconds
     */
    const QUERY_CACHE_TTL = 300; // 5 minutes

    /**
     * Performance monitoring enabled flag
     *
     * @var bool
     */
    private $performanceMonitoringEnabled = false;

    /**
     * Database indexes for plugin tables
     *
     * @var array
     */
    private $databaseIndexes = [
        'woo_ai_conversations' => [
            'idx_user_created' => ['user_id', 'created_at'],
            'idx_status' => ['status'],
            'idx_created_at' => ['created_at'],
            'idx_session_id' => ['session_id']
        ],
        'woo_ai_knowledge_base' => [
            'idx_content_type' => ['content_type'],
            'idx_post_id' => ['post_id'],
            'idx_updated_at' => ['updated_at'],
            'idx_status' => ['status']
        ],
        'woo_ai_ratings' => [
            'idx_conversation_id' => ['conversation_id'],
            'idx_rating_created' => ['rating', 'created_at'],
            'idx_user_id' => ['user_id']
        ]
    ];

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    protected function __construct()
    {
        $this->performanceMonitoringEnabled = defined('WOO_AI_ASSISTANT_DEBUG') && WOO_AI_ASSISTANT_DEBUG;
        $this->initializeHooks();
    }

    /**
     * Initialize WordPress hooks and monitoring
     *
     * @since 1.0.0
     * @return void
     */
    private function initializeHooks(): void
    {
        if ($this->performanceMonitoringEnabled) {
            // Hook into WordPress query logging
            add_filter('query', [$this, 'logQuery'], 10, 1);
            add_action('wp_footer', [$this, 'outputQueryStats']);
            add_action('admin_footer', [$this, 'outputQueryStats']);
        }

        // Performance optimization hooks
        add_action('init', [$this, 'optimizeQueries']);
        add_action('wp_loaded', [$this, 'ensureDatabaseIndexes']);

        // Query caching hooks
        add_filter('posts_request', [$this, 'cacheProductQueries'], 10, 2);
        add_filter('woocommerce_product_data_store_cpt_get_products_query', [$this, 'optimizeProductQuery'], 10, 2);
    }

    /**
     * Execute optimized query with caching and monitoring
     *
     * Executes database queries with automatic caching, performance monitoring,
     * and optimization features. Implements prepared statements for security
     * and query result caching for performance.
     *
     * @since 1.0.0
     * @param string $query The SQL query to execute
     * @param array $params Optional query parameters for prepared statements
     * @param int|null $cacheTime Optional cache time in seconds
     * @param string|null $cacheKey Optional custom cache key
     *
     * @return mixed Query results or false on failure
     *
     * @throws \InvalidArgumentException When query is empty.
     * @throws \RuntimeException When database error occurs.
     *
     * @example
     * ```php
     * $optimizer = QueryOptimizer::getInstance();
     * $results = $optimizer->executeOptimizedQuery(
     *     "SELECT * FROM {$wpdb->prefix}woo_ai_conversations WHERE user_id = %d",
     *     [123],
     *     300, // 5 minutes cache
     *     'user_conversations_123'
     * );
     * ```
     */
    public function executeOptimizedQuery(string $query, array $params = [], ?int $cacheTime = null, ?string $cacheKey = null): mixed
    {
        if (empty($query)) {
            throw new \InvalidArgumentException('Query cannot be empty');
        }

        global $wpdb;

        // Generate cache key if not provided
        if ($cacheKey === null) {
            $cacheKey = 'woo_ai_query_' . md5($query . serialize($params));
        }

        $cacheTime = $cacheTime ?? self::QUERY_CACHE_TTL;

        // Try to get from cache first
        $cached = wp_cache_get($cacheKey, 'woo_ai_queries');
        if ($cached !== false) {
            return $cached;
        }

        $startTime = microtime(true);

        try {
            // Prepare and execute query
            if (!empty($params)) {
                $preparedQuery = $wpdb->prepare($query, ...$params);
            } else {
                $preparedQuery = $query;
            }

            // Execute query based on type
            if (stripos($query, 'SELECT') === 0) {
                $results = $wpdb->get_results($preparedQuery);
            } elseif (stripos($query, 'INSERT') === 0 || stripos($query, 'UPDATE') === 0 || stripos($query, 'DELETE') === 0) {
                $results = $wpdb->query($preparedQuery);
            } else {
                $results = $wpdb->query($preparedQuery);
            }

            $executionTime = microtime(true) - $startTime;

            // Log query performance
            $this->logQueryPerformance($preparedQuery, $executionTime, $results);

            // Check for database errors
            if ($wpdb->last_error) {
                throw new \RuntimeException('Database error: ' . $wpdb->last_error);
            }

            // Cache successful results for SELECT queries
            if ($results !== false && stripos($query, 'SELECT') === 0) {
                wp_cache_set($cacheKey, $results, 'woo_ai_queries', $cacheTime);
            }

            return $results;
        } catch (\Exception $e) {
            $executionTime = microtime(true) - $startTime;
            $this->logQueryError($query, $e->getMessage(), $executionTime);
            throw new \RuntimeException('Query execution failed: ' . $e->getMessage());
        }
    }

    /**
     * Get conversation history with optimized query
     *
     * @since 1.0.0
     * @param int $userId The user ID
     * @param int $limit Maximum number of conversations to retrieve
     * @param int $offset Offset for pagination
     *
     * @return array Array of conversation data
     */
    public function getConversationHistory(int $userId, int $limit = 20, int $offset = 0): array
    {
        if ($userId <= 0) {
            return [];
        }

        global $wpdb;
        $tableName = $wpdb->prefix . 'woo_ai_conversations';

        $query = "
            SELECT 
                id,
                message,
                response,
                created_at,
                status,
                rating
            FROM {$tableName} 
            WHERE user_id = %d 
            ORDER BY created_at DESC 
            LIMIT %d OFFSET %d
        ";

        $cacheKey = "user_conversations_{$userId}_{$limit}_{$offset}";

        $result = $this->executeOptimizedQuery(
            $query,
            [$userId, $limit, $offset],
            self::QUERY_CACHE_TTL,
            $cacheKey
        );

        return is_array($result) ? $result : [];
    }

    /**
     * Get knowledge base content with optimized filtering
     *
     * @since 1.0.0
     * @param string $contentType Optional content type filter
     * @param int $limit Maximum number of results
     *
     * @return array Array of knowledge base entries
     */
    public function getKnowledgeBaseContent(string $contentType = '', int $limit = 50): array
    {
        global $wpdb;
        $tableName = $wpdb->prefix . 'woo_ai_knowledge_base';

        $whereClause = $contentType ? "WHERE content_type = %s" : "";
        $params = $contentType ? [$contentType, $limit] : [$limit];

        $query = "
            SELECT 
                id,
                content_type,
                title,
                content,
                embedding,
                post_id,
                updated_at
            FROM {$tableName} 
            {$whereClause}
            ORDER BY updated_at DESC 
            LIMIT %d
        ";

        $cacheKey = "kb_content_{$contentType}_{$limit}";

        $result = $this->executeOptimizedQuery(
            $query,
            $params,
            self::QUERY_CACHE_TTL * 2, // Longer cache for KB content
            $cacheKey
        );

        return is_array($result) ? $result : [];
    }

    /**
     * Search conversations by content with full-text optimization
     *
     * @since 1.0.0
     * @param string $searchTerm The search term
     * @param int $userId Optional user ID filter
     * @param int $limit Maximum results
     *
     * @return array Array of matching conversations
     */
    public function searchConversations(string $searchTerm, int $userId = 0, int $limit = 20): array
    {
        if (empty($searchTerm)) {
            return [];
        }

        global $wpdb;
        $tableName = $wpdb->prefix . 'woo_ai_conversations';

        $userFilter = $userId > 0 ? "AND user_id = %d" : "";
        $params = $userId > 0 ? [$searchTerm, $searchTerm, $userId, $limit] : [$searchTerm, $searchTerm, $limit];

        $query = "
            SELECT 
                id,
                user_id,
                message,
                response,
                created_at,
                status,
                MATCH(message, response) AGAINST(%s IN NATURAL LANGUAGE MODE) as relevance
            FROM {$tableName} 
            WHERE MATCH(message, response) AGAINST(%s IN NATURAL LANGUAGE MODE)
            {$userFilter}
            ORDER BY relevance DESC, created_at DESC
            LIMIT %d
        ";

        $cacheKey = "search_conversations_" . md5($searchTerm . $userId . $limit);

        $result = $this->executeOptimizedQuery(
            $query,
            $params,
            150, // Shorter cache for search results
            $cacheKey
        );

        return is_array($result) ? $result : [];
    }

    /**
     * Get conversation statistics with optimized aggregation
     *
     * @since 1.0.0
     * @param int $days Number of days to analyze
     *
     * @return array Statistics data
     */
    public function getConversationStatistics(int $days = 30): array
    {
        global $wpdb;
        $tableName = $wpdb->prefix . 'woo_ai_conversations';

        $query = "
            SELECT 
                COUNT(*) as total_conversations,
                COUNT(DISTINCT user_id) as unique_users,
                AVG(CASE WHEN rating > 0 THEN rating END) as avg_rating,
                COUNT(CASE WHEN rating > 0 THEN 1 END) as rated_conversations,
                DATE(created_at) as conversation_date
            FROM {$tableName} 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY DATE(created_at)
            ORDER BY conversation_date DESC
        ";

        $cacheKey = "conversation_stats_{$days}";

        $result = $this->executeOptimizedQuery(
            $query,
            [$days],
            HOUR_IN_SECONDS, // Cache for 1 hour
            $cacheKey
        );

        return is_array($result) ? $result : [];
    }

    /**
     * Optimize product queries for better performance
     *
     * @since 1.0.0
     * @param string $query The original query
     * @param \WP_Query $wpQuery The WP_Query object
     *
     * @return string Optimized query
     */
    public function optimizeProductQuery(string $query, $wpQuery): string
    {
        // Add query hints for better MySQL performance
        if (strpos($query, 'wp_posts') !== false && isset($wpQuery->query_vars['post_type']) && $wpQuery->query_vars['post_type'] === 'product') {
            // Add index hints for product queries
            $query = str_replace(
                'FROM wp_posts',
                'FROM wp_posts USE INDEX (type_status_date)',
                $query
            );
        }

        return $query;
    }

    /**
     * Cache expensive product queries
     *
     * @since 1.0.0
     * @param string $query The SQL query
     * @param \WP_Query $wpQuery The WP_Query object
     *
     * @return string Original or cached query
     */
    public function cacheProductQueries(string $query, $wpQuery): string
    {
        // Only cache product queries
        if (!isset($wpQuery->query_vars['post_type']) || $wpQuery->query_vars['post_type'] !== 'product') {
            return $query;
        }

        $cacheKey = 'woo_ai_product_query_' . md5($query);
        $cached = wp_cache_get($cacheKey, 'woo_ai_product_queries');

        if ($cached !== false) {
            return $cached;
        }

        // Cache the query for future use
        wp_cache_set($cacheKey, $query, 'woo_ai_product_queries', HOUR_IN_SECONDS);

        return $query;
    }

    /**
     * Ensure database indexes exist for optimal performance
     *
     * @since 1.0.0
     * @return void
     */
    public function ensureDatabaseIndexes(): void
    {
        global $wpdb;

        foreach ($this->databaseIndexes as $tableName => $indexes) {
            $fullTableName = $wpdb->prefix . $tableName;

            // Check if table exists
            $tableExists = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $fullTableName
            ));

            if (!$tableExists) {
                continue;
            }

            // Create indexes if they don't exist
            foreach ($indexes as $indexName => $columns) {
                $this->createIndexIfNotExists($fullTableName, $indexName, $columns);
            }
        }

        // Create full-text indexes for search functionality
        $this->createFullTextIndexes();
    }

    /**
     * Create database index if it doesn't exist
     *
     * @since 1.0.0
     * @param string $tableName The table name
     * @param string $indexName The index name
     * @param array $columns The columns to index
     *
     * @return void
     */
    private function createIndexIfNotExists(string $tableName, string $indexName, array $columns): void
    {
        global $wpdb;

        // Check if index exists
        $indexExists = $wpdb->get_var($wpdb->prepare(
            "SHOW INDEX FROM `{$tableName}` WHERE Key_name = %s",
            $indexName
        ));

        if (!$indexExists) {
            $columnList = implode(', ', array_map(function ($col) {
                return "`{$col}`";
            }, $columns));

            $sql = "CREATE INDEX `{$indexName}` ON `{$tableName}` ({$columnList})";

            $result = $wpdb->query($sql);

            if ($result === false) {
                error_log("Woo AI Assistant: Failed to create index {$indexName} on {$tableName}: " . $wpdb->last_error);
            } else {
                error_log("Woo AI Assistant: Created index {$indexName} on {$tableName}");
            }
        }
    }

    /**
     * Create full-text indexes for search functionality
     *
     * @since 1.0.0
     * @return void
     */
    private function createFullTextIndexes(): void
    {
        global $wpdb;

        $conversationsTable = $wpdb->prefix . 'woo_ai_conversations';

        // Check if full-text index exists for conversations
        $ftIndexExists = $wpdb->get_var($wpdb->prepare(
            "SHOW INDEX FROM `{$conversationsTable}` WHERE Key_name = %s AND Index_type = 'FULLTEXT'",
            'ft_message_response'
        ));

        if (!$ftIndexExists) {
            $sql = "CREATE FULLTEXT INDEX `ft_message_response` ON `{$conversationsTable}` (`message`, `response`)";
            $result = $wpdb->query($sql);

            if ($result === false) {
                error_log("Woo AI Assistant: Failed to create full-text index on conversations: " . $wpdb->last_error);
            }
        }
    }

    /**
     * Optimize general database queries
     *
     * @since 1.0.0
     * @return void
     */
    public function optimizeQueries(): void
    {
        // Set MySQL query cache settings for better performance
        if ($this->performanceMonitoringEnabled) {
            global $wpdb;
            $wpdb->query("SET SESSION query_cache_type = ON");
            $wpdb->query("SET SESSION query_cache_size = 67108864"); // 64MB
        }
    }

    /**
     * Log query for performance monitoring
     *
     * @since 1.0.0
     * @param string $query The executed query
     *
     * @return string The original query
     */
    public function logQuery(string $query): string
    {
        if (!$this->performanceMonitoringEnabled) {
            return $query;
        }

        $startTime = microtime(true);

        // Store query start time for measuring execution time
        $this->queryLog[] = [
            'query' => $query,
            'start_time' => $startTime,
            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)
        ];

        return $query;
    }

    /**
     * Log query performance metrics
     *
     * @since 1.0.0
     * @param string $query The executed query
     * @param float $executionTime Execution time in seconds
     * @param mixed $results Query results
     *
     * @return void
     */
    private function logQueryPerformance(string $query, float $executionTime, $results): void
    {
        if (!$this->performanceMonitoringEnabled) {
            return;
        }

        $isSlowQuery = $executionTime > self::SLOW_QUERY_THRESHOLD;

        $logData = [
            'query' => $query,
            'execution_time' => $executionTime,
            'is_slow' => $isSlowQuery,
            'timestamp' => current_time('mysql'),
            'results_count' => is_array($results) ? count($results) : ($results !== false ? 1 : 0)
        ];

        // Log slow queries separately
        if ($isSlowQuery) {
            error_log('Woo AI Assistant Slow Query: ' . json_encode($logData));
        }

        // Store in performance monitor if available
        if (class_exists('\WooAiAssistant\Performance\PerformanceMonitor')) {
            PerformanceMonitor::getInstance()->recordQueryMetric($logData);
        }
    }

    /**
     * Log query errors
     *
     * @since 1.0.0
     * @param string $query The failed query
     * @param string $error The error message
     * @param float $executionTime Execution time before failure
     *
     * @return void
     */
    private function logQueryError(string $query, string $error, float $executionTime): void
    {
        $errorData = [
            'query' => $query,
            'error' => $error,
            'execution_time' => $executionTime,
            'timestamp' => current_time('mysql')
        ];

        error_log('Woo AI Assistant Query Error: ' . json_encode($errorData));
    }

    /**
     * Get query performance statistics
     *
     * @since 1.0.0
     * @return array Performance statistics
     */
    public function getQueryStats(): array
    {
        if (!$this->performanceMonitoringEnabled) {
            return ['monitoring_disabled' => true];
        }

        global $wpdb;

        return [
            'total_queries' => count($this->queryLog),
            'slow_queries' => count(array_filter($this->queryLog, function ($log) {
                return isset($log['execution_time']) && $log['execution_time'] > self::SLOW_QUERY_THRESHOLD;
            })),
            'wpdb_queries' => $wpdb->num_queries ?? 0,
            'slow_query_threshold' => self::SLOW_QUERY_THRESHOLD,
            'cache_ttl' => self::QUERY_CACHE_TTL
        ];
    }

    /**
     * Output query statistics for debugging
     *
     * @since 1.0.0
     * @return void
     */
    public function outputQueryStats(): void
    {
        if (!$this->performanceMonitoringEnabled || !current_user_can('manage_options')) {
            return;
        }

        $stats = $this->getQueryStats();

        echo "<!-- Woo AI Assistant Query Stats: " . json_encode($stats) . " -->";
    }

    /**
     * Clear query cache for specific cache key or group
     *
     * @since 1.0.0
     * @param string|null $cacheKey Optional specific cache key to clear
     *
     * @return bool True on success
     */
    public function clearQueryCache(?string $cacheKey = null): bool
    {
        if ($cacheKey) {
            return wp_cache_delete($cacheKey, 'woo_ai_queries');
        }

        // Clear entire query cache group
        wp_cache_flush_group('woo_ai_queries');
        wp_cache_flush_group('woo_ai_product_queries');

        return true;
    }
}
