<?php

/**
 * Database Schema Definition
 *
 * Defines database table structures and provides schema validation utilities
 * for the Woo AI Assistant plugin.
 *
 * @package WooAiAssistant
 * @subpackage Database
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Database;

use WooAiAssistant\Common\Utils;
use WooAiAssistant\Common\Logger;
use WooAiAssistant\Common\Traits\Singleton;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Schema
 *
 * Manages database schema definitions, validation, and structure verification
 * for all plugin tables.
 *
 * @since 1.0.0
 */
class Schema
{
    use Singleton;

    /**
     * WordPress database instance
     *
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Plugin table definitions
     *
     * @var array
     */
    private $tableDefinitions = [];

    /**
     * Database view definitions
     *
     * @var array
     */
    private $viewDefinitions = [];

    /**
     * Initialize schema handler
     *
     * @since 1.0.0
     */
    protected function init(): void
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->initializeTableDefinitions();
        $this->initializeViewDefinitions();
    }

    /**
     * Initialize table structure definitions
     *
     * @since 1.0.0
     * @return void
     */
    private function initializeTableDefinitions(): void
    {
        $charset_collate = $this->wpdb->get_charset_collate();

        $this->tableDefinitions = [
            'woo_ai_conversations' => [
                'name' => $this->wpdb->prefix . 'woo_ai_conversations',
                'structure' => "
                    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    user_id bigint(20) unsigned DEFAULT NULL,
                    session_id varchar(64) NOT NULL,
                    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    status varchar(20) NOT NULL DEFAULT 'active',
                    rating tinyint(1) unsigned DEFAULT NULL,
                    context_data longtext DEFAULT NULL,
                    user_ip varchar(45) DEFAULT NULL,
                    user_agent varchar(500) DEFAULT NULL,
                    total_messages int(10) unsigned NOT NULL DEFAULT 0,
                    handoff_requested tinyint(1) NOT NULL DEFAULT 0,
                    handoff_email varchar(100) DEFAULT NULL,
                    PRIMARY KEY (id),
                    UNIQUE KEY session_id (session_id),
                    KEY idx_user_id (user_id),
                    KEY idx_status (status),
                    KEY idx_created_at (created_at),
                    KEY idx_rating (rating),
                    KEY idx_handoff (handoff_requested)
                ",
                'charset_collate' => $charset_collate,
                'description' => 'Tracks user conversations with the AI assistant',
                'version' => '1.0.0'
            ],

            'woo_ai_messages' => [
                'name' => $this->wpdb->prefix . 'woo_ai_messages',
                'structure' => "
                    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    conversation_id bigint(20) unsigned NOT NULL,
                    role enum('user', 'assistant', 'system') NOT NULL,
                    content longtext NOT NULL,
                    metadata longtext DEFAULT NULL,
                    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    tokens_used int(10) unsigned DEFAULT NULL,
                    processing_time_ms int(10) unsigned DEFAULT NULL,
                    model_used varchar(50) DEFAULT NULL,
                    temperature decimal(3,2) DEFAULT NULL,
                    error_message text DEFAULT NULL,
                    PRIMARY KEY (id),
                    KEY idx_conversation_id (conversation_id),
                    KEY idx_role (role),
                    KEY idx_created_at (created_at),
                    KEY idx_tokens_used (tokens_used)
                ",
                'charset_collate' => $charset_collate,
                'foreign_keys' => [
                    'conversation_id' => [
                        'table' => $this->wpdb->prefix . 'woo_ai_conversations',
                        'column' => 'id',
                        'on_delete' => 'CASCADE'
                    ]
                ],
                'description' => 'Stores individual messages within conversations',
                'version' => '1.0.0'
            ],

            'woo_ai_knowledge_base' => [
                'name' => $this->wpdb->prefix . 'woo_ai_knowledge_base',
                'structure' => "
                    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    content_type varchar(50) NOT NULL,
                    content_id bigint(20) unsigned DEFAULT NULL,
                    chunk_text longtext NOT NULL,
                    embedding longtext DEFAULT NULL,
                    metadata longtext DEFAULT NULL,
                    updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    chunk_hash varchar(64) NOT NULL,
                    chunk_index int(10) unsigned NOT NULL DEFAULT 0,
                    total_chunks int(10) unsigned NOT NULL DEFAULT 1,
                    word_count int(10) unsigned DEFAULT NULL,
                    embedding_model varchar(50) DEFAULT NULL,
                    is_active tinyint(1) NOT NULL DEFAULT 1,
                    PRIMARY KEY (id),
                    UNIQUE KEY chunk_hash (chunk_hash),
                    KEY idx_content_type (content_type),
                    KEY idx_content_id (content_id),
                    KEY idx_updated_at (updated_at),
                    KEY idx_is_active (is_active),
                    KEY idx_content_lookup (content_type, content_id, is_active)
                ",
                'charset_collate' => $charset_collate,
                'description' => 'Stores indexed content chunks with embeddings for RAG',
                'version' => '1.0.0'
            ],

            'woo_ai_settings' => [
                'name' => $this->wpdb->prefix . 'woo_ai_settings',
                'structure' => "
                    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    setting_key varchar(255) NOT NULL,
                    setting_value longtext DEFAULT NULL,
                    autoload tinyint(1) NOT NULL DEFAULT 1,
                    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    setting_group varchar(50) DEFAULT 'general',
                    is_sensitive tinyint(1) NOT NULL DEFAULT 0,
                    validation_rule varchar(255) DEFAULT NULL,
                    PRIMARY KEY (id),
                    UNIQUE KEY setting_key (setting_key),
                    KEY idx_autoload (autoload),
                    KEY idx_setting_group (setting_group),
                    KEY idx_is_sensitive (is_sensitive)
                ",
                'charset_collate' => $charset_collate,
                'description' => 'Plugin configuration and settings storage',
                'version' => '1.0.0'
            ],

            'woo_ai_analytics' => [
                'name' => $this->wpdb->prefix . 'woo_ai_analytics',
                'structure' => "
                    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    metric_type varchar(50) NOT NULL,
                    metric_value decimal(15,4) NOT NULL,
                    context longtext DEFAULT NULL,
                    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    user_id bigint(20) unsigned DEFAULT NULL,
                    conversation_id bigint(20) unsigned DEFAULT NULL,
                    session_id varchar(64) DEFAULT NULL,
                    additional_data longtext DEFAULT NULL,
                    source varchar(50) DEFAULT 'plugin',
                    PRIMARY KEY (id),
                    KEY idx_metric_type (metric_type),
                    KEY idx_created_at (created_at),
                    KEY idx_user_id (user_id),
                    KEY idx_conversation_id (conversation_id),
                    KEY idx_session_id (session_id),
                    KEY idx_metrics_lookup (metric_type, created_at)
                ",
                'charset_collate' => $charset_collate,
                'foreign_keys' => [
                    'conversation_id' => [
                        'table' => $this->wpdb->prefix . 'woo_ai_conversations',
                        'column' => 'id',
                        'on_delete' => 'SET NULL'
                    ]
                ],
                'description' => 'Performance metrics and usage statistics',
                'version' => '1.0.0'
            ],

            'woo_ai_action_logs' => [
                'name' => $this->wpdb->prefix . 'woo_ai_action_logs',
                'structure' => "
                    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    action_type varchar(50) NOT NULL,
                    user_id bigint(20) unsigned DEFAULT NULL,
                    details longtext DEFAULT NULL,
                    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    conversation_id bigint(20) unsigned DEFAULT NULL,
                    success tinyint(1) NOT NULL DEFAULT 1,
                    error_message text DEFAULT NULL,
                    ip_address varchar(45) DEFAULT NULL,
                    user_agent varchar(500) DEFAULT NULL,
                    execution_time_ms int(10) unsigned DEFAULT NULL,
                    severity enum('info', 'warning', 'error', 'critical') NOT NULL DEFAULT 'info',
                    PRIMARY KEY (id),
                    KEY idx_action_type (action_type),
                    KEY idx_user_id (user_id),
                    KEY idx_created_at (created_at),
                    KEY idx_conversation_id (conversation_id),
                    KEY idx_success (success),
                    KEY idx_severity (severity),
                    KEY idx_audit_lookup (action_type, created_at, success)
                ",
                'charset_collate' => $charset_collate,
                'foreign_keys' => [
                    'conversation_id' => [
                        'table' => $this->wpdb->prefix . 'woo_ai_conversations',
                        'column' => 'id',
                        'on_delete' => 'SET NULL'
                    ]
                ],
                'description' => 'Audit trail for all actions performed by the assistant',
                'version' => '1.0.0'
            ]
        ];
    }

    /**
     * Initialize database view definitions
     *
     * @since 1.0.0
     * @return void
     */
    private function initializeViewDefinitions(): void
    {
        $this->viewDefinitions = [
            'woo_ai_conversation_summary' => [
                'name' => $this->wpdb->prefix . 'woo_ai_conversation_summary',
                'definition' => "
                    SELECT 
                        c.id,
                        c.user_id,
                        c.session_id,
                        c.created_at,
                        c.updated_at,
                        c.status,
                        c.rating,
                        c.total_messages,
                        c.handoff_requested,
                        COALESCE(u.display_name, 'Guest') as user_name,
                        (SELECT content FROM {$this->wpdb->prefix}woo_ai_messages WHERE conversation_id = c.id AND role = 'user' ORDER BY created_at ASC LIMIT 1) as first_message,
                        (SELECT created_at FROM {$this->wpdb->prefix}woo_ai_messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_activity
                    FROM {$this->wpdb->prefix}woo_ai_conversations c
                    LEFT JOIN {$this->wpdb->users} u ON c.user_id = u.ID
                ",
                'description' => 'Recent conversations with message counts and user information',
                'version' => '1.0.0'
            ],

            'woo_ai_kb_summary' => [
                'name' => $this->wpdb->prefix . 'woo_ai_kb_summary',
                'definition' => "
                    SELECT 
                        content_type,
                        COUNT(*) as total_chunks,
                        SUM(word_count) as total_words,
                        MAX(updated_at) as last_updated,
                        COUNT(DISTINCT content_id) as unique_content_items
                    FROM {$this->wpdb->prefix}woo_ai_knowledge_base 
                    WHERE is_active = 1
                    GROUP BY content_type
                ",
                'description' => 'Knowledge base content summary statistics',
                'version' => '1.0.0'
            ]
        ];
    }

    /**
     * Get table definition by name
     *
     * @since 1.0.0
     * @param string $tableName Table name (without prefix)
     *
     * @return array|null Table definition or null if not found
     */
    public function getTableDefinition(string $tableName): ?array
    {
        return $this->tableDefinitions[$tableName] ?? null;
    }

    /**
     * Get all table definitions
     *
     * @since 1.0.0
     * @return array All table definitions
     */
    public function getAllTableDefinitions(): array
    {
        return $this->tableDefinitions;
    }

    /**
     * Get view definition by name
     *
     * @since 1.0.0
     * @param string $viewName View name (without prefix)
     *
     * @return array|null View definition or null if not found
     */
    public function getViewDefinition(string $viewName): ?array
    {
        return $this->viewDefinitions[$viewName] ?? null;
    }

    /**
     * Get all view definitions
     *
     * @since 1.0.0
     * @return array All view definitions
     */
    public function getAllViewDefinitions(): array
    {
        return $this->viewDefinitions;
    }

    /**
     * Create table using WordPress dbDelta
     *
     * @since 1.0.0
     * @param string $tableName Table name (without prefix)
     *
     * @return array Result array with success status and details
     */
    public function createTable(string $tableName): array
    {
        if (!isset($this->tableDefinitions[$tableName])) {
            return [
                'success' => false,
                'error' => "Table definition not found: {$tableName}"
            ];
        }

        $table = $this->tableDefinitions[$tableName];

        // Build CREATE TABLE statement for dbDelta
        $sql = "CREATE TABLE {$table['name']} (\n{$table['structure']}\n) {$table['charset_collate']};";

        // Include WordPress upgrade functionality
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }

        Logger::info("Creating table: {$table['name']}");

        $result = dbDelta($sql);

        if (empty($result)) {
            return [
                'success' => false,
                'error' => "Failed to create table: {$tableName}"
            ];
        }

        Logger::info("Table created successfully: {$table['name']}");

        return [
            'success' => true,
            'message' => "Table {$tableName} created successfully",
            'result' => $result
        ];
    }

    /**
     * Create database view
     *
     * @since 1.0.0
     * @param string $viewName View name (without prefix)
     *
     * @return array Result array with success status and details
     */
    public function createView(string $viewName): array
    {
        if (!isset($this->viewDefinitions[$viewName])) {
            return [
                'success' => false,
                'error' => "View definition not found: {$viewName}"
            ];
        }

        $view = $this->viewDefinitions[$viewName];

        // Drop existing view first
        $dropSql = "DROP VIEW IF EXISTS `{$view['name']}`";
        $this->wpdb->query($dropSql);

        // Create view
        $createSql = "CREATE VIEW `{$view['name']}` AS {$view['definition']}";

        Logger::info("Creating view: {$view['name']}");

        $result = $this->wpdb->query($createSql);

        if ($result === false) {
            return [
                'success' => false,
                'error' => "Failed to create view: {$viewName}. Error: " . $this->wpdb->last_error
            ];
        }

        Logger::info("View created successfully: {$view['name']}");

        return [
            'success' => true,
            'message' => "View {$viewName} created successfully"
        ];
    }

    /**
     * Validate database schema
     *
     * @since 1.0.0
     * @param array $options Optional. Validation options.
     * @param bool  $options['check_tables'] Whether to check table existence.
     * @param bool  $options['check_columns'] Whether to check column structure.
     * @param bool  $options['check_indexes'] Whether to check index existence.
     * @param bool  $options['check_views'] Whether to check view existence.
     *
     * @return array Validation result with detailed information
     *               Keys: 'valid', 'errors', 'warnings', 'table_status', 'view_status'
     */
    public function validateSchema(array $options = []): array
    {
        $checkTables = $options['check_tables'] ?? true;
        $checkColumns = $options['check_columns'] ?? true;
        $checkIndexes = $options['check_indexes'] ?? true;
        $checkViews = $options['check_views'] ?? true;

        $result = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'table_status' => [],
            'view_status' => []
        ];

        Logger::info('Starting database schema validation');

        // Check tables
        if ($checkTables) {
            foreach ($this->tableDefinitions as $tableName => $definition) {
                $fullTableName = $definition['name'];
                $tableStatus = $this->validateTable($fullTableName, $definition, $checkColumns, $checkIndexes);

                $result['table_status'][$tableName] = $tableStatus;

                if (!$tableStatus['exists']) {
                    $result['valid'] = false;
                    $result['errors'][] = "Table missing: {$fullTableName}";
                } elseif (!empty($tableStatus['column_errors'])) {
                    $result['valid'] = false;
                    $result['errors'] = array_merge($result['errors'], $tableStatus['column_errors']);
                } elseif (!empty($tableStatus['index_errors'])) {
                    $result['warnings'] = array_merge($result['warnings'], $tableStatus['index_errors']);
                }
            }
        }

        // Check views
        if ($checkViews) {
            foreach ($this->viewDefinitions as $viewName => $definition) {
                $fullViewName = $definition['name'];
                $viewExists = $this->viewExists($fullViewName);

                $result['view_status'][$viewName] = [
                    'exists' => $viewExists,
                    'name' => $fullViewName
                ];

                if (!$viewExists) {
                    $result['warnings'][] = "View missing: {$fullViewName}";
                }
            }
        }

        $errorCount = count($result['errors']);
        $warningCount = count($result['warnings']);

        Logger::info("Schema validation completed. Errors: {$errorCount}, Warnings: {$warningCount}");

        return $result;
    }

    /**
     * Validate individual table structure
     *
     * @since 1.0.0
     * @param string $tableName Full table name with prefix
     * @param array  $definition Table definition
     * @param bool   $checkColumns Whether to check columns
     * @param bool   $checkIndexes Whether to check indexes
     *
     * @return array Table validation status
     */
    private function validateTable(string $tableName, array $definition, bool $checkColumns = true, bool $checkIndexes = true): array
    {
        $status = [
            'exists' => false,
            'column_errors' => [],
            'index_errors' => [],
            'columns' => [],
            'indexes' => []
        ];

        // Check if table exists
        $tableExists = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
            DB_NAME,
            $tableName
        ));

        $status['exists'] = (bool) $tableExists;

        if (!$status['exists']) {
            return $status;
        }

        // Check columns if requested
        if ($checkColumns) {
            $columns = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT 
                 FROM information_schema.columns 
                 WHERE table_schema = %s AND table_name = %s",
                DB_NAME,
                $tableName
            ), ARRAY_A);

            $status['columns'] = $columns;

            // Basic column existence check (detailed validation would require parsing the structure)
            $columnNames = array_column($columns, 'COLUMN_NAME');
            $requiredColumns = $this->extractRequiredColumns($definition['structure']);

            foreach ($requiredColumns as $required) {
                if (!in_array($required, $columnNames)) {
                    $status['column_errors'][] = "Missing column '{$required}' in table {$tableName}";
                }
            }
        }

        // Check indexes if requested
        if ($checkIndexes) {
            $indexes = $this->wpdb->get_results($this->wpdb->prepare(
                "SHOW INDEX FROM `{$tableName}`"
            ), ARRAY_A);

            $status['indexes'] = $indexes;

            // Check for primary key
            $hasPrimaryKey = false;
            foreach ($indexes as $index) {
                if ($index['Key_name'] === 'PRIMARY') {
                    $hasPrimaryKey = true;
                    break;
                }
            }

            if (!$hasPrimaryKey) {
                $status['index_errors'][] = "Missing PRIMARY KEY in table {$tableName}";
            }
        }

        return $status;
    }

    /**
     * Extract required column names from table structure
     *
     * @since 1.0.0
     * @param string $structure Table structure definition
     *
     * @return array Array of column names
     */
    private function extractRequiredColumns(string $structure): array
    {
        $columns = [];
        $lines = explode("\n", $structure);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, 'PRIMARY KEY') === 0 || strpos($line, 'KEY ') === 0 || strpos($line, 'UNIQUE KEY') === 0) {
                continue;
            }

            // Extract column name (first word before space)
            if (preg_match('/^(\w+)\s/', $line, $matches)) {
                $columns[] = $matches[1];
            }
        }

        return $columns;
    }

    /**
     * Check if a view exists
     *
     * @since 1.0.0
     * @param string $viewName Full view name with prefix
     *
     * @return bool True if view exists
     */
    private function viewExists(string $viewName): bool
    {
        $result = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.views WHERE table_schema = %s AND table_name = %s",
            DB_NAME,
            $viewName
        ));

        return (bool) $result;
    }

    /**
     * Get database table sizes and row counts
     *
     * @since 1.0.0
     * @return array Table statistics
     */
    public function getTableStats(): array
    {
        $stats = [];

        foreach ($this->tableDefinitions as $tableName => $definition) {
            $fullTableName = $definition['name'];

            $tableInfo = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT 
                    table_rows,
                    data_length,
                    index_length,
                    data_length + index_length AS total_size
                FROM information_schema.tables 
                WHERE table_schema = %s AND table_name = %s",
                DB_NAME,
                $fullTableName
            ), ARRAY_A);

            if ($tableInfo) {
                $stats[$tableName] = [
                    'table_name' => $fullTableName,
                    'rows' => (int) $tableInfo['table_rows'],
                    'data_size' => (int) $tableInfo['data_length'],
                    'index_size' => (int) $tableInfo['index_length'],
                    'total_size' => (int) $tableInfo['total_size'],
                    'data_size_formatted' => size_format($tableInfo['data_length']),
                    'total_size_formatted' => size_format($tableInfo['total_size'])
                ];
            }
        }

        return $stats;
    }

    /**
     * Optimize all plugin tables
     *
     * @since 1.0.0
     * @return array Optimization results
     */
    public function optimizeTables(): array
    {
        $results = [];

        foreach ($this->tableDefinitions as $tableName => $definition) {
            $fullTableName = $definition['name'];

            Logger::info("Optimizing table: {$fullTableName}");

            $result = $this->wpdb->query("OPTIMIZE TABLE `{$fullTableName}`");

            $results[$tableName] = [
                'table' => $fullTableName,
                'success' => $result !== false,
                'error' => $result === false ? $this->wpdb->last_error : null
            ];
        }

        return $results;
    }

    /**
     * Get schema version information
     *
     * @since 1.0.0
     * @return array Schema version details
     */
    public function getSchemaInfo(): array
    {
        return [
            'plugin_version' => WOO_AI_ASSISTANT_VERSION,
            'database_version' => $this->getCurrentDatabaseVersion(),
            'table_count' => count($this->tableDefinitions),
            'view_count' => count($this->viewDefinitions),
            'tables' => array_keys($this->tableDefinitions),
            'views' => array_keys($this->viewDefinitions),
            'last_validated' => get_option('woo_ai_schema_last_validated'),
            'validation_status' => get_option('woo_ai_schema_validation_status', 'unknown')
        ];
    }

    /**
     * Get current database version from settings
     *
     * @since 1.0.0
     * @return string Database version
     */
    private function getCurrentDatabaseVersion(): string
    {
        $version = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT setting_value FROM {$this->wpdb->prefix}woo_ai_settings WHERE setting_key = %s",
            'migration_001_completed'
        ));

        return $version ? '1.0.0' : '0.0.0';
    }

    /**
     * Clean up orphaned records across all tables
     *
     * @since 1.0.0
     * @return array Cleanup results
     */
    public function cleanupOrphanedRecords(): array
    {
        $results = [];

        Logger::info('Starting orphaned records cleanup');

        // Clean up messages with no conversation
        $orphanedMessages = $this->wpdb->query("
            DELETE m FROM {$this->wpdb->prefix}woo_ai_messages m 
            LEFT JOIN {$this->wpdb->prefix}woo_ai_conversations c ON m.conversation_id = c.id 
            WHERE c.id IS NULL
        ");

        $results['messages'] = [
            'deleted' => $orphanedMessages !== false ? $orphanedMessages : 0,
            'success' => $orphanedMessages !== false
        ];

        // Clean up analytics with no conversation
        $orphanedAnalytics = $this->wpdb->query("
            DELETE a FROM {$this->wpdb->prefix}woo_ai_analytics a 
            LEFT JOIN {$this->wpdb->prefix}woo_ai_conversations c ON a.conversation_id = c.id 
            WHERE a.conversation_id IS NOT NULL AND c.id IS NULL
        ");

        $results['analytics'] = [
            'deleted' => $orphanedAnalytics !== false ? $orphanedAnalytics : 0,
            'success' => $orphanedAnalytics !== false
        ];

        // Clean up action logs with no conversation
        $orphanedLogs = $this->wpdb->query("
            DELETE l FROM {$this->wpdb->prefix}woo_ai_action_logs l 
            LEFT JOIN {$this->wpdb->prefix}woo_ai_conversations c ON l.conversation_id = c.id 
            WHERE l.conversation_id IS NOT NULL AND c.id IS NULL
        ");

        $results['action_logs'] = [
            'deleted' => $orphanedLogs !== false ? $orphanedLogs : 0,
            'success' => $orphanedLogs !== false
        ];

        $totalDeleted = $results['messages']['deleted'] + $results['analytics']['deleted'] + $results['action_logs']['deleted'];

        Logger::info("Cleanup completed. Total orphaned records removed: {$totalDeleted}");

        return $results;
    }

    /**
     * Add missing indexes to existing tables
     *
     * This method can be called to add performance indexes that might be
     * missing from older installations or after schema updates.
     *
     * @since 1.0.0
     * @return array Results of index creation
     */
    public function addMissingIndexes(): array
    {
        $results = [];

        Logger::info('Adding missing database indexes');

        // Additional performance indexes that can be added post-installation
        $additionalIndexes = [
            'woo_ai_conversations' => [
                'idx_user_status' => 'ADD INDEX `idx_user_status` (`user_id`, `status`)',
                'idx_created_rating' => 'ADD INDEX `idx_created_rating` (`created_at`, `rating`)',
                'idx_session_status' => 'ADD INDEX `idx_session_status` (`session_id`, `status`)'
            ],
            'woo_ai_messages' => [
                'idx_conv_role_created' => 'ADD INDEX `idx_conv_role_created` (`conversation_id`, `role`, `created_at`)',
                'idx_model_tokens' => 'ADD INDEX `idx_model_tokens` (`model_used`, `tokens_used`)',
                'idx_processing_time' => 'ADD INDEX `idx_processing_time` (`processing_time_ms`)'
            ],
            'woo_ai_knowledge_base' => [
                'idx_type_active_updated' => 'ADD INDEX `idx_type_active_updated` (`content_type`, `is_active`, `updated_at`)',
                'idx_embedding_model' => 'ADD INDEX `idx_embedding_model` (`embedding_model`)',
                'idx_word_count' => 'ADD INDEX `idx_word_count` (`word_count`)'
            ],
            'woo_ai_analytics' => [
                'idx_type_value' => 'ADD INDEX `idx_type_value` (`metric_type`, `metric_value`)',
                'idx_user_created' => 'ADD INDEX `idx_user_created` (`user_id`, `created_at`)',
                'idx_source_type' => 'ADD INDEX `idx_source_type` (`source`, `metric_type`)'
            ],
            'woo_ai_action_logs' => [
                'idx_type_success_created' => 'ADD INDEX `idx_type_success_created` (`action_type`, `success`, `created_at`)',
                'idx_severity_created' => 'ADD INDEX `idx_severity_created` (`severity`, `created_at`)',
                'idx_execution_time' => 'ADD INDEX `idx_execution_time` (`execution_time_ms`)'
            ]
        ];

        foreach ($additionalIndexes as $tableName => $indexes) {
            $fullTableName = $this->wpdb->prefix . $tableName;
            $results[$tableName] = [];

            foreach ($indexes as $indexName => $sql) {
                // Check if index already exists
                $existingIndexes = $this->wpdb->get_results(
                    "SHOW INDEX FROM `{$fullTableName}` WHERE Key_name = '{$indexName}'",
                    ARRAY_A
                );

                if (empty($existingIndexes)) {
                    $alterSql = "ALTER TABLE `{$fullTableName}` {$sql}";
                    $result = $this->wpdb->query($alterSql);

                    $results[$tableName][$indexName] = [
                        'success' => $result !== false,
                        'error' => $result === false ? $this->wpdb->last_error : null
                    ];

                    if ($result !== false) {
                        Logger::info("Added index {$indexName} to {$fullTableName}");
                    } else {
                        Logger::error("Failed to add index {$indexName} to {$fullTableName}: " . $this->wpdb->last_error);
                    }
                } else {
                    $results[$tableName][$indexName] = [
                        'success' => true,
                        'skipped' => true,
                        'reason' => 'Index already exists'
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Create foreign key constraints if supported
     *
     * MySQL/MariaDB with InnoDB storage engine supports foreign keys.
     * This method adds referential integrity constraints.
     *
     * @since 1.0.0
     * @return array Results of constraint creation
     */
    public function createForeignKeyConstraints(): array
    {
        $results = [];

        Logger::info('Creating foreign key constraints');

        // Check if storage engine supports foreign keys
        $storageEngine = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT ENGINE FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
                DB_NAME,
                $this->wpdb->prefix . 'woo_ai_conversations'
            )
        );

        if (strtolower($storageEngine) !== 'innodb') {
            Logger::warning('Foreign key constraints require InnoDB storage engine');
            return ['error' => 'Storage engine does not support foreign keys'];
        }

        $constraints = [
            'woo_ai_messages' => [
                'fk_messages_conversation' => [
                    'sql' => "ADD CONSTRAINT `fk_messages_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `{$this->wpdb->prefix}woo_ai_conversations` (`id`) ON DELETE CASCADE",
                    'column' => 'conversation_id'
                ]
            ],
            'woo_ai_analytics' => [
                'fk_analytics_conversation' => [
                    'sql' => "ADD CONSTRAINT `fk_analytics_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `{$this->wpdb->prefix}woo_ai_conversations` (`id`) ON DELETE SET NULL",
                    'column' => 'conversation_id'
                ]
            ],
            'woo_ai_action_logs' => [
                'fk_logs_conversation' => [
                    'sql' => "ADD CONSTRAINT `fk_logs_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `{$this->wpdb->prefix}woo_ai_conversations` (`id`) ON DELETE SET NULL",
                    'column' => 'conversation_id'
                ]
            ]
        ];

        foreach ($constraints as $tableName => $tableConstraints) {
            $fullTableName = $this->wpdb->prefix . $tableName;
            $results[$tableName] = [];

            foreach ($tableConstraints as $constraintName => $constraint) {
                // Check if constraint already exists
                $existing = $this->wpdb->get_var($this->wpdb->prepare(
                    "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE 
                     WHERE CONSTRAINT_SCHEMA = %s 
                     AND TABLE_NAME = %s 
                     AND CONSTRAINT_NAME = %s",
                    DB_NAME,
                    $fullTableName,
                    $constraintName
                ));

                if (!$existing) {
                    $alterSql = "ALTER TABLE `{$fullTableName}` {$constraint['sql']}";
                    $result = $this->wpdb->query($alterSql);

                    $results[$tableName][$constraintName] = [
                        'success' => $result !== false,
                        'error' => $result === false ? $this->wpdb->last_error : null
                    ];

                    if ($result !== false) {
                        Logger::info("Created foreign key constraint {$constraintName} on {$fullTableName}");
                    } else {
                        Logger::error("Failed to create constraint {$constraintName}: " . $this->wpdb->last_error);
                    }
                } else {
                    $results[$tableName][$constraintName] = [
                        'success' => true,
                        'skipped' => true,
                        'reason' => 'Constraint already exists'
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Analyze table performance and suggest optimizations
     *
     * @since 1.0.0
     * @return array Performance analysis results
     */
    public function analyzePerformance(): array
    {
        Logger::info('Analyzing database performance');

        $analysis = [
            'table_stats' => $this->getTableStats(),
            'slow_queries' => $this->identifySlowQueries(),
            'index_usage' => $this->analyzeIndexUsage(),
            'recommendations' => []
        ];

        // Generate recommendations based on analysis
        $analysis['recommendations'] = $this->generateOptimizationRecommendations($analysis);

        return $analysis;
    }

    /**
     * Identify potentially slow queries
     *
     * @since 1.0.0
     * @return array Slow query patterns
     */
    private function identifySlowQueries(): array
    {
        // Common query patterns that might be slow
        $patterns = [
            'large_table_full_scan' => [
                'description' => 'Full table scan on large tables',
                'tables' => []
            ],
            'missing_indexes' => [
                'description' => 'Queries that could benefit from additional indexes',
                'queries' => []
            ],
            'complex_joins' => [
                'description' => 'Complex multi-table joins',
                'joins' => []
            ]
        ];

        // Check table sizes
        foreach ($this->tableDefinitions as $tableName => $definition) {
            $stats = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT table_rows, avg_row_length FROM information_schema.tables 
                 WHERE table_schema = %s AND table_name = %s",
                DB_NAME,
                $definition['name']
            ), ARRAY_A);

            if ($stats && $stats['table_rows'] > 10000) {
                $patterns['large_table_full_scan']['tables'][] = [
                    'table' => $tableName,
                    'rows' => $stats['table_rows'],
                    'avg_row_length' => $stats['avg_row_length']
                ];
            }
        }

        return $patterns;
    }

    /**
     * Analyze index usage patterns
     *
     * @since 1.0.0
     * @return array Index usage analysis
     */
    private function analyzeIndexUsage(): array
    {
        $usage = [];

        foreach ($this->tableDefinitions as $tableName => $definition) {
            $fullTableName = $definition['name'];

            // Get index information
            $indexes = $this->wpdb->get_results(
                "SHOW INDEX FROM `{$fullTableName}`",
                ARRAY_A
            );

            $usage[$tableName] = [
                'total_indexes' => count($indexes),
                'indexes' => $indexes,
                'recommendations' => []
            ];

            // Analyze for unused indexes (this would require query log analysis in production)
            // For now, just mark potential optimization opportunities
            $duplicateGroups = [];
            foreach ($indexes as $index) {
                $column = $index['Column_name'];
                if (!isset($duplicateGroups[$column])) {
                    $duplicateGroups[$column] = [];
                }
                $duplicateGroups[$column][] = $index['Key_name'];
            }

            foreach ($duplicateGroups as $column => $indexNames) {
                if (count($indexNames) > 1) {
                    $usage[$tableName]['recommendations'][] = [
                        'type' => 'duplicate_indexes',
                        'column' => $column,
                        'indexes' => $indexNames,
                        'suggestion' => 'Consider consolidating duplicate indexes on column ' . $column
                    ];
                }
            }
        }

        return $usage;
    }

    /**
     * Generate optimization recommendations
     *
     * @since 1.0.0
     * @param array $analysis Performance analysis data
     * @return array Optimization recommendations
     */
    private function generateOptimizationRecommendations(array $analysis): array
    {
        $recommendations = [];

        // Table size recommendations
        foreach ($analysis['table_stats'] as $tableName => $stats) {
            if ($stats['rows'] > 100000) {
                $recommendations[] = [
                    'type' => 'large_table',
                    'table' => $tableName,
                    'priority' => 'high',
                    'description' => "Table {$tableName} has {$stats['rows']} rows. Consider archiving old data.",
                    'suggestion' => 'Implement data retention policies or table partitioning'
                ];
            }

            if ($stats['total_size'] > 100 * 1024 * 1024) { // 100MB
                $recommendations[] = [
                    'type' => 'table_size',
                    'table' => $tableName,
                    'priority' => 'medium',
                    'description' => "Table {$tableName} is {$stats['total_size_formatted']}. Monitor growth.",
                    'suggestion' => 'Consider data compression or archiving strategies'
                ];
            }
        }

        // Index recommendations
        foreach ($analysis['index_usage'] as $tableName => $usage) {
            foreach ($usage['recommendations'] as $rec) {
                $recommendations[] = array_merge($rec, [
                    'table' => $tableName,
                    'priority' => 'medium'
                ]);
            }
        }

        // General recommendations
        $recommendations[] = [
            'type' => 'maintenance',
            'priority' => 'low',
            'description' => 'Regular database maintenance',
            'suggestion' => 'Schedule regular OPTIMIZE TABLE operations and orphaned record cleanup'
        ];

        return $recommendations;
    }

    /**
     * Export schema definition as SQL
     *
     * @since 1.0.0
     * @param array $options Export options
     * @return string SQL export
     */
    public function exportSchema(array $options = []): string
    {
        $includeData = $options['include_data'] ?? false;
        $includeViews = $options['include_views'] ?? true;

        $sql = "-- Woo AI Assistant Database Schema Export\n";
        $sql .= "-- Generated: " . current_time('mysql') . "\n";
        $sql .= "-- Plugin Version: " . WOO_AI_ASSISTANT_VERSION . "\n\n";

        $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

        // Export table structures
        foreach ($this->tableDefinitions as $tableName => $definition) {
            $sql .= "-- Table: {$tableName}\n";
            $sql .= "DROP TABLE IF EXISTS `{$definition['name']}`;\n";
            $sql .= "CREATE TABLE `{$definition['name']}` (\n";
            $sql .= "  {$definition['structure']}\n";
            $sql .= ") ENGINE=InnoDB {$definition['charset_collate']};\n\n";

            // Include data if requested
            if ($includeData) {
                $data = $this->wpdb->get_results("SELECT * FROM `{$definition['name']}`", ARRAY_A);
                if (!empty($data)) {
                    $sql .= "-- Data for table {$tableName}\n";
                    foreach ($data as $row) {
                        $values = array_map(function ($value) {
                            return $value === null ? 'NULL' : "'" . esc_sql($value) . "'";
                        }, array_values($row));
                        $sql .= "INSERT INTO `{$definition['name']}` VALUES (" . implode(', ', $values) . ");\n";
                    }
                    $sql .= "\n";
                }
            }
        }

        // Export views if requested
        if ($includeViews) {
            foreach ($this->viewDefinitions as $viewName => $definition) {
                $sql .= "-- View: {$viewName}\n";
                $sql .= "DROP VIEW IF EXISTS `{$definition['name']}`;\n";
                $sql .= "CREATE VIEW `{$definition['name']}` AS {$definition['definition']};\n\n";
            }
        }

        $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

        return $sql;
    }
}
