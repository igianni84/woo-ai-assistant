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
}
