<?php

/**
 * Database Schema Validator
 *
 * Validates that the database schema matches requirements and provides
 * detailed reporting on table structures, relationships, and compliance.
 *
 * @package WooAiAssistant
 * @subpackage Database
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Database;

use WooAiAssistant\Common\Logger;
use WooAiAssistant\Common\Utils;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SchemaValidator
 *
 * Provides comprehensive validation of database schema against Task 1.3 requirements.
 *
 * @since 1.0.0
 */
class SchemaValidator
{
    /**
     * WordPress database instance
     *
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Required table specifications from Task 1.3
     *
     * @var array
     */
    private $requiredTables = [
        'woo_ai_conversations' => [
            'required_columns' => [
                'id' => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                'user_id' => 'bigint(20) unsigned DEFAULT NULL',
                'session_id' => 'varchar(64) NOT NULL',
                'created_at' => 'datetime NOT NULL DEFAULT CURRENT_TIMESTAMP',
                'updated_at' => 'datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                'status' => 'varchar(20) NOT NULL DEFAULT \'active\'',
                'rating' => 'tinyint(1) unsigned DEFAULT NULL'
            ],
            'optional_columns' => [
                'context_data', 'user_ip', 'user_agent', 'total_messages',
                'handoff_requested', 'handoff_email'
            ],
            'required_indexes' => ['PRIMARY KEY (id)', 'UNIQUE KEY session_id (session_id)'],
            'purpose' => 'Tracks user conversations with the AI assistant'
        ],

        'woo_ai_messages' => [
            'required_columns' => [
                'id' => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                'conversation_id' => 'bigint(20) unsigned NOT NULL',
                'role' => 'enum(\'user\', \'assistant\', \'system\') NOT NULL',
                'content' => 'longtext NOT NULL',
                'metadata' => 'longtext DEFAULT NULL',
                'created_at' => 'datetime NOT NULL DEFAULT CURRENT_TIMESTAMP'
            ],
            'optional_columns' => [
                'tokens_used', 'processing_time_ms', 'model_used',
                'temperature', 'error_message'
            ],
            'required_indexes' => ['PRIMARY KEY (id)'],
            'foreign_keys' => ['conversation_id REFERENCES woo_ai_conversations(id) ON DELETE CASCADE'],
            'purpose' => 'Stores individual messages within conversations'
        ],

        'woo_ai_knowledge_base' => [
            'required_columns' => [
                'id' => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                'content_type' => 'varchar(50) NOT NULL',
                'content_id' => 'bigint(20) unsigned DEFAULT NULL',
                'chunk_text' => 'longtext NOT NULL',
                'embedding' => 'longtext DEFAULT NULL',
                'metadata' => 'longtext DEFAULT NULL',
                'updated_at' => 'datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
            ],
            'optional_columns' => [
                'chunk_hash', 'chunk_index', 'total_chunks', 'word_count',
                'embedding_model', 'is_active'
            ],
            'required_indexes' => ['PRIMARY KEY (id)'],
            'purpose' => 'Stores indexed content chunks with embeddings for RAG'
        ],

        'woo_ai_settings' => [
            'required_columns' => [
                'id' => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                'setting_key' => 'varchar(255) NOT NULL',
                'setting_value' => 'longtext DEFAULT NULL',
                'autoload' => 'tinyint(1) NOT NULL DEFAULT 1'
            ],
            'optional_columns' => [
                'created_at', 'updated_at', 'setting_group',
                'is_sensitive', 'validation_rule'
            ],
            'required_indexes' => ['PRIMARY KEY (id)', 'UNIQUE KEY setting_key (setting_key)'],
            'purpose' => 'Plugin configuration and settings storage'
        ],

        'woo_ai_analytics' => [
            'required_columns' => [
                'id' => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                'metric_type' => 'varchar(50) NOT NULL',
                'metric_value' => 'decimal(15,4) NOT NULL',
                'context' => 'longtext DEFAULT NULL',
                'created_at' => 'datetime NOT NULL DEFAULT CURRENT_TIMESTAMP'
            ],
            'optional_columns' => [
                'user_id', 'conversation_id', 'session_id',
                'additional_data', 'source'
            ],
            'required_indexes' => ['PRIMARY KEY (id)'],
            'purpose' => 'Performance metrics and usage statistics'
        ],

        'woo_ai_action_logs' => [
            'required_columns' => [
                'id' => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                'action_type' => 'varchar(50) NOT NULL',
                'user_id' => 'bigint(20) unsigned DEFAULT NULL',
                'details' => 'longtext DEFAULT NULL',
                'created_at' => 'datetime NOT NULL DEFAULT CURRENT_TIMESTAMP'
            ],
            'optional_columns' => [
                'conversation_id', 'success', 'error_message', 'ip_address',
                'user_agent', 'execution_time_ms', 'severity'
            ],
            'required_indexes' => ['PRIMARY KEY (id)'],
            'purpose' => 'Audit trail for all actions performed by the assistant'
        ]
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Validate complete database schema against Task 1.3 requirements
     *
     * @return array Comprehensive validation results
     */
    public function validateCompleteSchema(): array
    {
        Logger::info('Starting comprehensive schema validation for Task 1.3');

        $validation = [
            'overall_valid' => true,
            'timestamp' => current_time('mysql'),
            'plugin_version' => WOO_AI_ASSISTANT_VERSION,
            'tables' => [],
            'summary' => [
                'total_tables' => count($this->requiredTables),
                'valid_tables' => 0,
                'missing_tables' => 0,
                'invalid_tables' => 0,
                'warnings' => 0,
                'errors' => 0
            ],
            'recommendations' => [],
            'compliance_score' => 0
        ];

        foreach ($this->requiredTables as $tableName => $requirements) {
            Logger::debug("Validating table: {$tableName}");

            $tableValidation = $this->validateTable($tableName, $requirements);
            $validation['tables'][$tableName] = $tableValidation;

            // Update summary
            if ($tableValidation['exists']) {
                if ($tableValidation['valid']) {
                    $validation['summary']['valid_tables']++;
                } else {
                    $validation['summary']['invalid_tables']++;
                    $validation['overall_valid'] = false;
                }
            } else {
                $validation['summary']['missing_tables']++;
                $validation['overall_valid'] = false;
            }

            $validation['summary']['errors'] += count($tableValidation['errors']);
            $validation['summary']['warnings'] += count($tableValidation['warnings']);
        }

        // Calculate compliance score
        $validation['compliance_score'] = $this->calculateComplianceScore($validation);

        // Generate recommendations
        $validation['recommendations'] = $this->generateRecommendations($validation);

        Logger::info('Schema validation completed', [
            'overall_valid' => $validation['overall_valid'],
            'compliance_score' => $validation['compliance_score'],
            'valid_tables' => $validation['summary']['valid_tables'],
            'total_errors' => $validation['summary']['errors']
        ]);

        return $validation;
    }

    /**
     * Validate individual table against requirements
     *
     * @param string $tableName Table name without prefix
     * @param array  $requirements Table requirements
     * @return array Table validation results
     */
    private function validateTable(string $tableName, array $requirements): array
    {
        $fullTableName = $this->wpdb->prefix . $tableName;

        $validation = [
            'exists' => false,
            'valid' => false,
            'table_name' => $fullTableName,
            'purpose' => $requirements['purpose'],
            'columns' => [
                'required_present' => [],
                'required_missing' => [],
                'optional_present' => [],
                'extra_columns' => []
            ],
            'indexes' => [
                'present' => [],
                'missing' => []
            ],
            'foreign_keys' => [
                'present' => [],
                'missing' => []
            ],
            'errors' => [],
            'warnings' => [],
            'compliance_percentage' => 0
        ];

        // Check if table exists
        $tableExists = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
            DB_NAME,
            $fullTableName
        ));

        if (!$tableExists) {
            $validation['errors'][] = "Table {$fullTableName} does not exist";
            return $validation;
        }

        $validation['exists'] = true;

        // Get actual table structure
        $actualColumns = $this->getTableColumns($fullTableName);
        $actualIndexes = $this->getTableIndexes($fullTableName);

        // Validate required columns
        foreach ($requirements['required_columns'] as $columnName => $columnSpec) {
            if (isset($actualColumns[$columnName])) {
                $validation['columns']['required_present'][] = $columnName;

                // Basic type validation (simplified)
                if (!$this->validateColumnType($actualColumns[$columnName], $columnSpec)) {
                    $validation['warnings'][] = "Column {$columnName} type may not match specification";
                }
            } else {
                $validation['columns']['required_missing'][] = $columnName;
                $validation['errors'][] = "Required column {$columnName} is missing";
            }
        }

        // Check optional columns
        if (isset($requirements['optional_columns'])) {
            foreach ($requirements['optional_columns'] as $optionalColumn) {
                if (isset($actualColumns[$optionalColumn])) {
                    $validation['columns']['optional_present'][] = $optionalColumn;
                }
            }
        }

        // Identify extra columns
        $requiredColumns = array_keys($requirements['required_columns']);
        $optionalColumns = $requirements['optional_columns'] ?? [];
        $allExpectedColumns = array_merge($requiredColumns, $optionalColumns);

        foreach ($actualColumns as $columnName => $columnInfo) {
            if (!in_array($columnName, $allExpectedColumns)) {
                $validation['columns']['extra_columns'][] = $columnName;
            }
        }

        // Validate indexes
        if (isset($requirements['required_indexes'])) {
            foreach ($requirements['required_indexes'] as $requiredIndex) {
                if ($this->hasRequiredIndex($actualIndexes, $requiredIndex)) {
                    $validation['indexes']['present'][] = $requiredIndex;
                } else {
                    $validation['indexes']['missing'][] = $requiredIndex;
                    $validation['errors'][] = "Missing required index: {$requiredIndex}";
                }
            }
        }

        // Validate foreign keys
        if (isset($requirements['foreign_keys'])) {
            $actualForeignKeys = $this->getTableForeignKeys($fullTableName);

            foreach ($requirements['foreign_keys'] as $requiredFK) {
                if ($this->hasForeignKey($actualForeignKeys, $requiredFK)) {
                    $validation['foreign_keys']['present'][] = $requiredFK;
                } else {
                    $validation['foreign_keys']['missing'][] = $requiredFK;
                    $validation['warnings'][] = "Missing foreign key constraint: {$requiredFK}";
                }
            }
        }

        // Calculate compliance
        $totalRequired = count($requirements['required_columns']) + count($requirements['required_indexes'] ?? []);
        $totalPresent = count($validation['columns']['required_present']) + count($validation['indexes']['present']);
        $validation['compliance_percentage'] = $totalRequired > 0 ? round(($totalPresent / $totalRequired) * 100, 2) : 100;

        // Determine if table is valid
        $validation['valid'] = empty($validation['errors']);

        return $validation;
    }

    /**
     * Get table column information
     *
     * @param string $tableName Full table name
     * @return array Column information indexed by column name
     */
    private function getTableColumns(string $tableName): array
    {
        $columns = [];

        $results = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
             FROM information_schema.columns 
             WHERE table_schema = %s AND table_name = %s
             ORDER BY ORDINAL_POSITION",
            DB_NAME,
            $tableName
        ), ARRAY_A);

        foreach ($results as $column) {
            $columns[$column['COLUMN_NAME']] = $column;
        }

        return $columns;
    }

    /**
     * Get table index information
     *
     * @param string $tableName Full table name
     * @return array Index information
     */
    private function getTableIndexes(string $tableName): array
    {
        return $this->wpdb->get_results(
            "SHOW INDEX FROM `{$tableName}`",
            ARRAY_A
        );
    }

    /**
     * Get table foreign key information
     *
     * @param string $tableName Full table name
     * @return array Foreign key information
     */
    private function getTableForeignKeys(string $tableName): array
    {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
             FROM information_schema.KEY_COLUMN_USAGE 
             WHERE CONSTRAINT_SCHEMA = %s 
             AND TABLE_NAME = %s 
             AND REFERENCED_TABLE_NAME IS NOT NULL",
            DB_NAME,
            $tableName
        ), ARRAY_A);
    }

    /**
     * Validate column type against specification
     *
     * @param array  $actualColumn Actual column information
     * @param string $expectedSpec Expected column specification
     * @return bool True if column type matches
     */
    private function validateColumnType(array $actualColumn, string $expectedSpec): bool
    {
        // Simplified type validation - in production, this would be more comprehensive
        $actualType = strtolower($actualColumn['COLUMN_TYPE']);
        $expectedType = strtolower($expectedSpec);

        // Extract base type for comparison
        if (
            preg_match('/^(\w+)/', $actualType, $actualMatches) &&
            preg_match('/^(\w+)/', $expectedType, $expectedMatches)
        ) {
            return $actualMatches[1] === $expectedMatches[1];
        }

        return false;
    }

    /**
     * Check if required index exists
     *
     * @param array  $actualIndexes Actual table indexes
     * @param string $requiredIndex Required index specification
     * @return bool True if index exists
     */
    private function hasRequiredIndex(array $actualIndexes, string $requiredIndex): bool
    {
        // Extract index type and column from specification
        if (strpos($requiredIndex, 'PRIMARY KEY') !== false) {
            foreach ($actualIndexes as $index) {
                if ($index['Key_name'] === 'PRIMARY') {
                    return true;
                }
            }
        } elseif (strpos($requiredIndex, 'UNIQUE KEY') !== false) {
            // Extract key name from specification like "UNIQUE KEY session_id (session_id)"
            if (preg_match('/UNIQUE KEY (\w+)/', $requiredIndex, $matches)) {
                $keyName = $matches[1];
                foreach ($actualIndexes as $index) {
                    if ($index['Key_name'] === $keyName && $index['Non_unique'] == 0) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Check if foreign key exists
     *
     * @param array  $actualFKs Actual foreign keys
     * @param string $requiredFK Required foreign key specification
     * @return bool True if foreign key exists
     */
    private function hasForeignKey(array $actualFKs, string $requiredFK): bool
    {
        // Extract column name from FK specification
        if (preg_match('/(\w+) REFERENCES/', $requiredFK, $matches)) {
            $columnName = $matches[1];
            foreach ($actualFKs as $fk) {
                if ($fk['COLUMN_NAME'] === $columnName) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Calculate overall compliance score
     *
     * @param array $validation Validation results
     * @return float Compliance score (0-100)
     */
    private function calculateComplianceScore(array $validation): float
    {
        $totalTables = $validation['summary']['total_tables'];
        $validTables = $validation['summary']['valid_tables'];

        if ($totalTables === 0) {
            return 0;
        }

        $baseScore = ($validTables / $totalTables) * 100;

        // Deduct points for errors and warnings
        $errorPenalty = $validation['summary']['errors'] * 5; // 5 points per error
        $warningPenalty = $validation['summary']['warnings'] * 2; // 2 points per warning

        $finalScore = max(0, $baseScore - $errorPenalty - $warningPenalty);

        return round($finalScore, 2);
    }

    /**
     * Generate recommendations based on validation results
     *
     * @param array $validation Validation results
     * @return array Recommendations
     */
    private function generateRecommendations(array $validation): array
    {
        $recommendations = [];

        if ($validation['summary']['missing_tables'] > 0) {
            $recommendations[] = [
                'priority' => 'critical',
                'type' => 'missing_tables',
                'description' => 'Some required tables are missing from the database',
                'action' => 'Run database migration to create missing tables'
            ];
        }

        if ($validation['summary']['errors'] > 0) {
            $recommendations[] = [
                'priority' => 'high',
                'type' => 'schema_errors',
                'description' => "Found {$validation['summary']['errors']} schema errors",
                'action' => 'Review and fix table structure issues'
            ];
        }

        if ($validation['summary']['warnings'] > 0) {
            $recommendations[] = [
                'priority' => 'medium',
                'type' => 'schema_warnings',
                'description' => "Found {$validation['summary']['warnings']} schema warnings",
                'action' => 'Consider adding missing optional features like foreign keys'
            ];
        }

        if ($validation['compliance_score'] < 95) {
            $recommendations[] = [
                'priority' => 'medium',
                'type' => 'compliance',
                'description' => "Schema compliance is {$validation['compliance_score']}%",
                'action' => 'Improve schema compliance to meet Task 1.3 requirements'
            ];
        }

        return $recommendations;
    }

    /**
     * Generate detailed validation report
     *
     * @param array $validation Validation results
     * @return string Human-readable report
     */
    public function generateReport(array $validation): string
    {
        $report = "=== Woo AI Assistant Database Schema Validation Report ===\n\n";
        $report .= "Generated: {$validation['timestamp']}\n";
        $report .= "Plugin Version: {$validation['plugin_version']}\n";
        $report .= "Overall Valid: " . ($validation['overall_valid'] ? 'YES' : 'NO') . "\n";
        $report .= "Compliance Score: {$validation['compliance_score']}%\n\n";

        $report .= "=== SUMMARY ===\n";
        $report .= "Total Tables: {$validation['summary']['total_tables']}\n";
        $report .= "Valid Tables: {$validation['summary']['valid_tables']}\n";
        $report .= "Missing Tables: {$validation['summary']['missing_tables']}\n";
        $report .= "Invalid Tables: {$validation['summary']['invalid_tables']}\n";
        $report .= "Total Errors: {$validation['summary']['errors']}\n";
        $report .= "Total Warnings: {$validation['summary']['warnings']}\n\n";

        $report .= "=== TABLE DETAILS ===\n";
        foreach ($validation['tables'] as $tableName => $tableInfo) {
            $report .= "\n--- {$tableName} ---\n";
            $report .= "Exists: " . ($tableInfo['exists'] ? 'YES' : 'NO') . "\n";
            $report .= "Valid: " . ($tableInfo['valid'] ? 'YES' : 'NO') . "\n";
            $report .= "Purpose: {$tableInfo['purpose']}\n";
            $report .= "Compliance: {$tableInfo['compliance_percentage']}%\n";

            if (!empty($tableInfo['errors'])) {
                $report .= "Errors:\n";
                foreach ($tableInfo['errors'] as $error) {
                    $report .= "  - {$error}\n";
                }
            }

            if (!empty($tableInfo['warnings'])) {
                $report .= "Warnings:\n";
                foreach ($tableInfo['warnings'] as $warning) {
                    $report .= "  - {$warning}\n";
                }
            }
        }

        if (!empty($validation['recommendations'])) {
            $report .= "\n=== RECOMMENDATIONS ===\n";
            foreach ($validation['recommendations'] as $rec) {
                $report .= "\n[{$rec['priority']}] {$rec['description']}\n";
                $report .= "Action: {$rec['action']}\n";
            }
        }

        return $report;
    }
}
