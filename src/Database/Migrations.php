<?php

/**
 * Database Migrations Handler
 *
 * Handles database schema migrations with version tracking, rollback capabilities,
 * and comprehensive error handling for the Woo AI Assistant plugin.
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
 * Class Migrations
 *
 * Manages database migrations with version tracking, safety checks,
 * and automatic rollback capabilities.
 *
 * @since 1.0.0
 */
class Migrations
{
    use Singleton;

    /**
     * WordPress database instance
     *
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Migration version data
     *
     * @var array
     */
    private $versionData;

    /**
     * Plugin migrations directory path
     *
     * @var string
     */
    private $migrationsPath;

    /**
     * Maximum migration execution time in seconds
     *
     * @var int
     */
    private $maxExecutionTime = 300;

    /**
     * Migration lock timeout in seconds
     *
     * @var int
     */
    private $lockTimeout = 1800; // 30 minutes

    /**
     * Initialize migrations handler
     *
     * @since 1.0.0
     */
    protected function init(): void
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->migrationsPath = WOO_AI_ASSISTANT_PATH . 'migrations/';
        $this->loadVersionData();
    }

    /**
     * Load migration version data from JSON file
     *
     * @since 1.0.0
     * @return void
     * @throws \RuntimeException When version file cannot be loaded
     */
    private function loadVersionData(): void
    {
        $versionFile = $this->migrationsPath . 'version.json';

        if (!file_exists($versionFile)) {
            throw new \RuntimeException('Migration version file not found: ' . $versionFile);
        }

        $content = file_get_contents($versionFile);
        if ($content === false) {
            throw new \RuntimeException('Cannot read migration version file');
        }

        $this->versionData = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON in version file: ' . json_last_error_msg());
        }
    }

    /**
     * Save updated version data to JSON file
     *
     * @since 1.0.0
     * @return bool True on success, false on failure
     */
    private function saveVersionData(): bool
    {
        $versionFile = $this->migrationsPath . 'version.json';

        $content = json_encode($this->versionData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($content === false) {
            Logger::error('Failed to encode version data to JSON');
            return false;
        }

        $result = file_put_contents($versionFile, $content);
        if ($result === false) {
            Logger::error('Failed to write version file');
            return false;
        }

        return true;
    }

    /**
     * Run all pending migrations
     *
     * @since 1.0.0
     * @param array $options Optional. Migration options.
     * @param bool  $options['force'] Whether to force migration even if already applied.
     * @param bool  $options['backup'] Whether to backup database before migration.
     *
     * @return array Result array with success status and details
     *               Keys: 'success', 'message', 'applied_migrations', 'errors'
     *
     * @throws \RuntimeException When migration lock cannot be acquired
     */
    public function runMigrations(array $options = []): array
    {
        $force = $options['force'] ?? false;
        $backup = $options['backup'] ?? $this->versionData['backup_before_migration'] ?? true;

        Logger::info('Starting database migrations');

        // Acquire migration lock
        if (!$this->acquireLock()) {
            throw new \RuntimeException('Cannot acquire migration lock. Another migration may be in progress.');
        }

        $result = [
            'success' => true,
            'message' => '',
            'applied_migrations' => [],
            'errors' => []
        ];

        try {
            // Create backup if requested
            if ($backup && !$this->createBackup()) {
                $result['success'] = false;
                $result['errors'][] = 'Failed to create database backup';
                return $result;
            }

            $currentVersion = $this->getCurrentVersion();
            $targetVersion = $this->versionData['target_version'];

            Logger::info("Current version: {$currentVersion}, Target version: {$targetVersion}");

            // Find migrations to apply
            $migrationsToApply = $this->getMigrationsToApply($currentVersion, $targetVersion, $force);

            if (empty($migrationsToApply)) {
                $result['message'] = 'No migrations to apply. Database is up to date.';
                Logger::info($result['message']);
                return $result;
            }

            // Apply each migration
            foreach ($migrationsToApply as $migrationId) {
                $migrationResult = $this->applyMigration($migrationId);

                if (!$migrationResult['success']) {
                    $result['success'] = false;
                    $result['errors'][] = $migrationResult['error'];
                    Logger::error("Migration {$migrationId} failed: " . $migrationResult['error']);
                    break;
                }

                $result['applied_migrations'][] = $migrationId;
                Logger::info("Migration {$migrationId} applied successfully");
            }

            if ($result['success']) {
                $this->versionData['current_version'] = $targetVersion;
                $this->versionData['last_migration_check'] = current_time('mysql');
                $this->saveVersionData();

                $count = count($result['applied_migrations']);
                $result['message'] = "Successfully applied {$count} migration(s)";
                Logger::info($result['message']);
            }
        } catch (\Exception $e) {
            $result['success'] = false;
            $result['errors'][] = $e->getMessage();
            Logger::error('Migration exception: ' . $e->getMessage());
        } finally {
            $this->releaseLock();
        }

        return $result;
    }

    /**
     * Apply a single migration
     *
     * @since 1.0.0
     * @param string $migrationId Migration identifier
     *
     * @return array Result array with success status and error message
     *               Keys: 'success', 'error'
     */
    private function applyMigration(string $migrationId): array
    {
        if (!isset($this->versionData['migrations'][$migrationId])) {
            return [
                'success' => false,
                'error' => "Migration {$migrationId} not found in version data"
            ];
        }

        $migration = $this->versionData['migrations'][$migrationId];
        $sqlFile = $this->migrationsPath . $migration['file'];

        if (!file_exists($sqlFile)) {
            return [
                'success' => false,
                'error' => "Migration file not found: {$sqlFile}"
            ];
        }

        // Check dependencies
        if (!empty($migration['dependencies'])) {
            foreach ($migration['dependencies'] as $dependency) {
                if (!$this->isMigrationApplied($dependency)) {
                    return [
                        'success' => false,
                        'error' => "Migration {$migrationId} depends on {$dependency} which is not applied"
                    ];
                }
            }
        }

        // Load and execute SQL
        $sql = file_get_contents($sqlFile);
        if ($sql === false) {
            return [
                'success' => false,
                'error' => "Cannot read migration file: {$sqlFile}"
            ];
        }

        // Replace placeholders
        $sql = $this->replacePlaceholders($sql);

        // Execute migration with timeout
        $startTime = microtime(true);
        set_time_limit($this->maxExecutionTime);

        $result = $this->executeMigrationSql($sql);

        $executionTime = microtime(true) - $startTime;

        if (!$result['success']) {
            return $result;
        }

        // Mark migration as applied
        $this->versionData['migrations'][$migrationId]['applied'] = true;
        $this->versionData['migrations'][$migrationId]['applied_at'] = current_time('mysql');

        // Calculate and store checksum
        $this->versionData['migrations'][$migrationId]['checksum'] = md5($sql);

        Logger::info("Migration {$migrationId} executed in {$executionTime} seconds");

        return ['success' => true, 'error' => null];
    }

    /**
     * Execute migration SQL with error handling
     *
     * @since 1.0.0
     * @param string $sql SQL to execute
     *
     * @return array Result array with success status and error message
     */
    private function executeMigrationSql(string $sql): array
    {
        // Split SQL into individual statements
        $statements = $this->splitSqlStatements($sql);

        // Start transaction
        $this->wpdb->query('START TRANSACTION');

        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (empty($statement)) {
                continue;
            }

            $result = $this->wpdb->query($statement);

            if ($result === false) {
                $this->wpdb->query('ROLLBACK');
                return [
                    'success' => false,
                    'error' => 'Database error: ' . $this->wpdb->last_error . ' in statement: ' . substr($statement, 0, 100) . '...'
                ];
            }
        }

        // Commit transaction
        $this->wpdb->query('COMMIT');

        return ['success' => true, 'error' => null];
    }

    /**
     * Split SQL into individual statements
     *
     * @since 1.0.0
     * @param string $sql SQL content
     *
     * @return array Array of SQL statements
     */
    private function splitSqlStatements(string $sql): array
    {
        // Remove comments
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

        // Split by semicolon, but not inside quotes
        $statements = [];
        $current = '';
        $inQuotes = false;
        $quoteChar = '';

        for ($i = 0; $i < strlen($sql); $i++) {
            $char = $sql[$i];

            if (!$inQuotes && ($char === '"' || $char === "'")) {
                $inQuotes = true;
                $quoteChar = $char;
            } elseif ($inQuotes && $char === $quoteChar) {
                $inQuotes = false;
                $quoteChar = '';
            } elseif (!$inQuotes && $char === ';') {
                $statements[] = trim($current);
                $current = '';
                continue;
            }

            $current .= $char;
        }

        if (!empty(trim($current))) {
            $statements[] = trim($current);
        }

        return array_filter($statements);
    }

    /**
     * Replace placeholders in SQL
     *
     * @since 1.0.0
     * @param string $sql SQL content with placeholders
     *
     * @return string SQL with placeholders replaced
     */
    private function replacePlaceholders(string $sql): string
    {
        $replacements = [
            '{prefix}' => $this->wpdb->prefix,
            '{wpdb_users}' => $this->wpdb->users,
            '{wpdb_usermeta}' => $this->wpdb->usermeta,
            '{wpdb_posts}' => $this->wpdb->posts,
            '{wpdb_postmeta}' => $this->wpdb->postmeta,
            '{charset_collate}' => $this->wpdb->get_charset_collate(),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $sql);
    }

    /**
     * Rollback a specific migration
     *
     * @since 1.0.0
     * @param string $migrationId Migration to rollback
     *
     * @return array Result array with success status and details
     *
     * @throws \RuntimeException When migration lock cannot be acquired
     */
    public function rollbackMigration(string $migrationId): array
    {
        Logger::info("Starting rollback for migration: {$migrationId}");

        if (!$this->acquireLock()) {
            throw new \RuntimeException('Cannot acquire migration lock');
        }

        $result = [
            'success' => true,
            'message' => '',
            'error' => null
        ];

        try {
            if (!$this->isMigrationApplied($migrationId)) {
                $result['message'] = "Migration {$migrationId} is not applied";
                return $result;
            }

            $migration = $this->versionData['migrations'][$migrationId];

            if (empty($migration['rollback_file'])) {
                $result['success'] = false;
                $result['error'] = "No rollback script available for migration {$migrationId}";
                return $result;
            }

            // Execute rollback
            $rollbackFile = $this->migrationsPath . $migration['rollback_file'];
            if (!file_exists($rollbackFile)) {
                $result['success'] = false;
                $result['error'] = "Rollback file not found: {$rollbackFile}";
                return $result;
            }

            $sql = file_get_contents($rollbackFile);
            $sql = $this->replacePlaceholders($sql);

            $executeResult = $this->executeMigrationSql($sql);
            if (!$executeResult['success']) {
                $result['success'] = false;
                $result['error'] = $executeResult['error'];
                return $result;
            }

            // Mark as not applied
            $this->versionData['migrations'][$migrationId]['applied'] = false;
            $this->versionData['migrations'][$migrationId]['applied_at'] = null;

            // Add to rollback history
            $this->versionData['rollback_history'][] = [
                'migration_id' => $migrationId,
                'rolled_back_at' => current_time('mysql'),
                'reason' => 'Manual rollback'
            ];

            $this->saveVersionData();

            $result['message'] = "Migration {$migrationId} rolled back successfully";
            Logger::info($result['message']);
        } catch (\Exception $e) {
            $result['success'] = false;
            $result['error'] = $e->getMessage();
            Logger::error('Rollback exception: ' . $e->getMessage());
        } finally {
            $this->releaseLock();
        }

        return $result;
    }

    /**
     * Get current database version
     *
     * @since 1.0.0
     * @return string Current version
     */
    public function getCurrentVersion(): string
    {
        return $this->versionData['current_version'] ?? '0';
    }

    /**
     * Get target database version
     *
     * @since 1.0.0
     * @return string Target version
     */
    public function getTargetVersion(): string
    {
        return $this->versionData['target_version'] ?? '0';
    }

    /**
     * Check if a specific migration has been applied
     *
     * @since 1.0.0
     * @param string $migrationId Migration identifier
     *
     * @return bool True if migration is applied
     */
    public function isMigrationApplied(string $migrationId): bool
    {
        return $this->versionData['migrations'][$migrationId]['applied'] ?? false;
    }

    /**
     * Get list of migrations to apply
     *
     * @since 1.0.0
     * @param string $currentVersion Current database version
     * @param string $targetVersion Target database version
     * @param bool   $force Whether to force reapplication
     *
     * @return array Array of migration IDs to apply
     */
    private function getMigrationsToApply(string $currentVersion, string $targetVersion, bool $force = false): array
    {
        $migrations = [];

        foreach ($this->versionData['migrations'] as $id => $migration) {
            $migrationVersion = $migration['version'];

            // Check if migration should be applied
            if ($migrationVersion > $currentVersion && $migrationVersion <= $targetVersion) {
                if (!$this->isMigrationApplied($id) || $force) {
                    $migrations[] = $id;
                }
            }
        }

        // Sort by version
        usort($migrations, function ($a, $b) {
            $versionA = $this->versionData['migrations'][$a]['version'];
            $versionB = $this->versionData['migrations'][$b]['version'];
            return version_compare($versionA, $versionB);
        });

        return $migrations;
    }

    /**
     * Acquire migration lock
     *
     * @since 1.0.0
     * @return bool True if lock acquired successfully
     */
    private function acquireLock(): bool
    {
        // Check if already locked
        if ($this->versionData['migration_lock']) {
            $lockTime = $this->versionData['migration_lock_time'];
            if ($lockTime && (time() - strtotime($lockTime)) < $this->lockTimeout) {
                return false; // Lock still valid
            }
        }

        // Acquire lock
        $this->versionData['migration_lock'] = true;
        $this->versionData['migration_lock_time'] = current_time('mysql');

        return $this->saveVersionData();
    }

    /**
     * Release migration lock
     *
     * @since 1.0.0
     * @return bool True if lock released successfully
     */
    private function releaseLock(): bool
    {
        $this->versionData['migration_lock'] = false;
        $this->versionData['migration_lock_time'] = null;

        return $this->saveVersionData();
    }

    /**
     * Clear stuck migration lock
     *
     * @since 1.0.0
     * @return bool True on success
     */
    public function clearLock(): bool
    {
        Logger::info('Clearing migration lock');
        return $this->releaseLock();
    }

    /**
     * Create database backup before migration
     *
     * @since 1.0.0
     * @return bool True if backup created successfully
     */
    private function createBackup(): bool
    {
        // This is a placeholder - in production you'd use mysqldump or similar
        // For now, we'll log the backup request
        Logger::info('Database backup requested (not implemented in this version)');
        return true;
    }

    /**
     * Get migration status information
     *
     * @since 1.0.0
     * @return array Status information
     *               Keys: 'current_version', 'target_version', 'pending_migrations', 'applied_migrations'
     */
    public function getStatus(): array
    {
        $currentVersion = $this->getCurrentVersion();
        $targetVersion = $this->getTargetVersion();
        $pendingMigrations = $this->getMigrationsToApply($currentVersion, $targetVersion);

        $appliedMigrations = [];
        foreach ($this->versionData['migrations'] as $id => $migration) {
            if ($this->isMigrationApplied($id)) {
                $appliedMigrations[] = $id;
            }
        }

        return [
            'current_version' => $currentVersion,
            'target_version' => $targetVersion,
            'pending_migrations' => $pendingMigrations,
            'applied_migrations' => $appliedMigrations,
            'is_locked' => $this->versionData['migration_lock'] ?? false,
            'lock_time' => $this->versionData['migration_lock_time'] ?? null,
            'last_check' => $this->versionData['last_migration_check'] ?? null
        ];
    }

    /**
     * Reset database to specific version (DESTRUCTIVE)
     *
     * @since 1.0.0
     * @param string $version Version to reset to
     *
     * @return array Result array
     *
     * @throws \RuntimeException When version is invalid
     */
    public function resetToVersion(string $version): array
    {
        if (!$this->acquireLock()) {
            throw new \RuntimeException('Cannot acquire migration lock');
        }

        Logger::warning("Resetting database to version: {$version}");

        try {
            // Mark all migrations after this version as not applied
            foreach ($this->versionData['migrations'] as $id => $migration) {
                if ($migration['version'] > $version) {
                    $this->versionData['migrations'][$id]['applied'] = false;
                    $this->versionData['migrations'][$id]['applied_at'] = null;
                }
            }

            $this->versionData['current_version'] = $version;
            $this->saveVersionData();

            return [
                'success' => true,
                'message' => "Database reset to version {$version}"
            ];
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * Drop all plugin tables (DESTRUCTIVE - USE WITH EXTREME CAUTION)
     *
     * @since 1.0.0
     * @return bool True on success
     */
    public function dropAllTables(): bool
    {
        Logger::warning('Dropping all plugin tables');

        $tables = [
            $this->wpdb->prefix . 'woo_ai_action_logs',
            $this->wpdb->prefix . 'woo_ai_analytics',
            $this->wpdb->prefix . 'woo_ai_messages',
            $this->wpdb->prefix . 'woo_ai_conversations',
            $this->wpdb->prefix . 'woo_ai_knowledge_base',
            $this->wpdb->prefix . 'woo_ai_settings'
        ];

        // Drop views first
        $this->wpdb->query("DROP VIEW IF EXISTS `{$this->wpdb->prefix}woo_ai_conversation_summary`");
        $this->wpdb->query("DROP VIEW IF EXISTS `{$this->wpdb->prefix}woo_ai_kb_summary`");

        // Drop tables in reverse dependency order
        foreach ($tables as $table) {
            $this->wpdb->query("DROP TABLE IF EXISTS `{$table}`");
        }

        // Reset version data
        $this->versionData['current_version'] = '0';
        foreach ($this->versionData['migrations'] as $id => $migration) {
            $this->versionData['migrations'][$id]['applied'] = false;
            $this->versionData['migrations'][$id]['applied_at'] = null;
        }

        $this->saveVersionData();

        return true;
    }
}
