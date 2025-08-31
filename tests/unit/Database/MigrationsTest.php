<?php

/**
 * Tests for Database Migrations Class
 *
 * Comprehensive unit tests for the Migrations class that handles database schema
 * migrations, version tracking, rollback capabilities, and migration safety.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Unit\Database
 * @since 1.0.0
 * @author Claude Code Assistant
 */

namespace WooAiAssistant\Tests\Unit\Database;

use WooAiAssistant\Tests\Unit\WooAiBaseTestCase;
use WooAiAssistant\Database\Migrations;

/**
 * Class MigrationsTest
 *
 * Test cases for the Migrations class.
 * Verifies migration execution, version tracking, rollback functionality, and safety mechanisms.
 *
 * @since 1.0.0
 */
class MigrationsTest extends WooAiBaseTestCase
{
    /**
     * Migrations instance
     *
     * @var Migrations
     */
    private $migrations;

    /**
     * Test migrations directory path
     *
     * @var string
     */
    private $testMigrationsPath;

    /**
     * Original version data backup
     *
     * @var array
     */
    private $originalVersionData;

    /**
     * WordPress database instance
     *
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Set up test environment
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        global $wpdb;
        $this->wpdb = $wpdb;

        $this->migrations = Migrations::getInstance();
        $this->testMigrationsPath = WOO_AI_ASSISTANT_PATH . 'migrations/';

        // Create test migrations directory if it doesn't exist
        if (!file_exists($this->testMigrationsPath)) {
            wp_mkdir_p($this->testMigrationsPath);
        }

        // Create test version.json file
        $this->createTestVersionFile();

        // Backup original version data
        try {
            $this->originalVersionData = $this->getPropertyValue($this->migrations, 'versionData');
        } catch (\Exception $e) {
            // If version data doesn't exist, create minimal structure
            $this->originalVersionData = [
                'current_version' => '0',
                'target_version' => '1.0.0',
                'migrations' => [],
                'migration_lock' => false,
                'migration_lock_time' => null,
                'backup_before_migration' => true,
                'rollback_history' => []
            ];
        }
    }

    /**
     * Create test version.json file
     *
     * @return void
     */
    private function createTestVersionFile(): void
    {
        $versionData = [
            'current_version' => '0',
            'target_version' => '1.0.0',
            'backup_before_migration' => true,
            'migration_lock' => false,
            'migration_lock_time' => null,
            'last_migration_check' => null,
            'rollback_history' => [],
            'migrations' => [
                'create_conversations_table' => [
                    'version' => '0.1',
                    'file' => 'test_001_create_conversations_table.sql',
                    'rollback_file' => 'test_001_rollback_conversations_table.sql',
                    'description' => 'Create conversations table',
                    'applied' => false,
                    'applied_at' => null,
                    'checksum' => null,
                    'dependencies' => []
                ],
                'create_knowledge_base_table' => [
                    'version' => '0.2',
                    'file' => 'test_002_create_knowledge_base_table.sql',
                    'rollback_file' => 'test_002_rollback_knowledge_base_table.sql',
                    'description' => 'Create knowledge base table',
                    'applied' => false,
                    'applied_at' => null,
                    'checksum' => null,
                    'dependencies' => ['create_conversations_table']
                ]
            ]
        ];

        file_put_contents($this->testMigrationsPath . 'version.json', json_encode($versionData, JSON_PRETTY_PRINT));

        // Create test SQL migration files
        $this->createTestMigrationFiles();
    }

    /**
     * Create test migration SQL files
     *
     * @return void
     */
    private function createTestMigrationFiles(): void
    {
        // Create conversations table migration
        $conversationsSql = "CREATE TABLE IF NOT EXISTS {prefix}woo_ai_conversations (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            session_id varchar(255) NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY session_id (session_id)
        ) {charset_collate};";

        file_put_contents($this->testMigrationsPath . 'test_001_create_conversations_table.sql', $conversationsSql);

        // Create conversations table rollback
        $conversationsRollback = "DROP TABLE IF EXISTS {prefix}woo_ai_conversations;";
        file_put_contents($this->testMigrationsPath . 'test_001_rollback_conversations_table.sql', $conversationsRollback);

        // Create knowledge base table migration
        $knowledgeBaseSql = "CREATE TABLE IF NOT EXISTS {prefix}woo_ai_knowledge_base (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            content_type varchar(50) NOT NULL,
            content_id bigint(20) unsigned NOT NULL,
            title text NOT NULL,
            content longtext NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY content_type_id (content_type, content_id)
        ) {charset_collate};";

        file_put_contents($this->testMigrationsPath . 'test_002_create_knowledge_base_table.sql', $knowledgeBaseSql);

        // Create knowledge base table rollback
        $knowledgeBaseRollback = "DROP TABLE IF EXISTS {prefix}woo_ai_knowledge_base;";
        file_put_contents($this->testMigrationsPath . 'test_002_rollback_knowledge_base_table.sql', $knowledgeBaseRollback);
    }

    /**
     * Test Migrations singleton pattern
     *
     * Verifies that Migrations class follows singleton pattern correctly.
     *
     * @return void
     */
    public function test_getInstance_should_return_singleton_instance(): void
    {
        $instance1 = Migrations::getInstance();
        $instance2 = Migrations::getInstance();

        $this->assertInstanceOf(Migrations::class, $instance1);
        $this->assertSame($instance1, $instance2, 'getInstance should return the same instance (singleton pattern)');
    }

    /**
     * Test version data loading
     *
     * Verifies that version data is loaded correctly from JSON file.
     *
     * @return void
     */
    public function test_loadVersionData_should_load_version_file_correctly(): void
    {
        // Force reload version data
        $this->invokeMethod($this->migrations, 'loadVersionData');

        $versionData = $this->getPropertyValue($this->migrations, 'versionData');

        $this->assertIsArray($versionData, 'Version data should be an array');
        $this->assertArrayHasKey('current_version', $versionData, 'Should contain current version');
        $this->assertArrayHasKey('target_version', $versionData, 'Should contain target version');
        $this->assertArrayHasKey('migrations', $versionData, 'Should contain migrations');

        $this->assertEquals('0', $versionData['current_version'], 'Current version should be 0');
        $this->assertEquals('1.0.0', $versionData['target_version'], 'Target version should be 1.0.0');
        $this->assertIsArray($versionData['migrations'], 'Migrations should be an array');
    }

    /**
     * Test current version retrieval
     *
     * Verifies that getCurrentVersion returns correct version.
     *
     * @return void
     */
    public function test_getCurrentVersion_should_return_current_version(): void
    {
        $currentVersion = $this->migrations->getCurrentVersion();

        $this->assertIsString($currentVersion, 'Current version should be string');
        $this->assertEquals('0', $currentVersion, 'Current version should be 0');
    }

    /**
     * Test target version retrieval
     *
     * Verifies that getTargetVersion returns correct version.
     *
     * @return void
     */
    public function test_getTargetVersion_should_return_target_version(): void
    {
        $targetVersion = $this->migrations->getTargetVersion();

        $this->assertIsString($targetVersion, 'Target version should be string');
        $this->assertEquals('1.0.0', $targetVersion, 'Target version should be 1.0.0');
    }

    /**
     * Test migration applied check
     *
     * Verifies that isMigrationApplied correctly checks migration status.
     *
     * @return void
     */
    public function test_isMigrationApplied_should_return_correct_status(): void
    {
        // Check unapplied migration
        $isApplied = $this->migrations->isMigrationApplied('create_conversations_table');
        $this->assertFalse($isApplied, 'Migration should not be applied initially');

        // Mock applied migration
        $versionData = $this->getPropertyValue($this->migrations, 'versionData');
        $versionData['migrations']['create_conversations_table']['applied'] = true;
        $this->setPropertyValue($this->migrations, 'versionData', $versionData);

        $isApplied = $this->migrations->isMigrationApplied('create_conversations_table');
        $this->assertTrue($isApplied, 'Migration should be applied after setting flag');

        // Check non-existent migration
        $isApplied = $this->migrations->isMigrationApplied('nonexistent_migration');
        $this->assertFalse($isApplied, 'Non-existent migration should return false');
    }

    /**
     * Test migration status retrieval
     *
     * Verifies that getStatus returns comprehensive status information.
     *
     * @return void
     */
    public function test_getStatus_should_return_comprehensive_status(): void
    {
        $status = $this->migrations->getStatus();

        $this->assertIsArray($status, 'Status should be an array');
        
        $expectedKeys = [
            'current_version', 'target_version', 'pending_migrations', 
            'applied_migrations', 'is_locked', 'lock_time', 'last_check'
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $status, "Status should contain {$key}");
        }

        $this->assertEquals('0', $status['current_version'], 'Should return correct current version');
        $this->assertEquals('1.0.0', $status['target_version'], 'Should return correct target version');
        $this->assertIsArray($status['pending_migrations'], 'Pending migrations should be array');
        $this->assertIsArray($status['applied_migrations'], 'Applied migrations should be array');
        $this->assertIsBool($status['is_locked'], 'Lock status should be boolean');
    }

    /**
     * Test migration lock acquisition
     *
     * Verifies that migration lock can be acquired and prevents concurrent migrations.
     *
     * @return void
     */
    public function test_acquireLock_should_prevent_concurrent_migrations(): void
    {
        // First acquisition should succeed
        $lockAcquired = $this->invokeMethod($this->migrations, 'acquireLock');
        $this->assertTrue($lockAcquired, 'First lock acquisition should succeed');

        // Second acquisition should fail (lock already held)
        $lockAcquired2 = $this->invokeMethod($this->migrations, 'acquireLock');
        $this->assertFalse($lockAcquired2, 'Second lock acquisition should fail');

        // Release lock
        $this->invokeMethod($this->migrations, 'releaseLock');

        // Third acquisition should succeed (lock released)
        $lockAcquired3 = $this->invokeMethod($this->migrations, 'acquireLock');
        $this->assertTrue($lockAcquired3, 'Lock acquisition after release should succeed');
    }

    /**
     * Test lock timeout handling
     *
     * Verifies that expired locks can be re-acquired.
     *
     * @return void
     */
    public function test_acquireLock_should_handle_expired_locks(): void
    {
        // Manually set expired lock
        $versionData = $this->getPropertyValue($this->migrations, 'versionData');
        $versionData['migration_lock'] = true;
        $versionData['migration_lock_time'] = date('Y-m-d H:i:s', time() - 7200); // 2 hours ago
        $this->setPropertyValue($this->migrations, 'versionData', $versionData);

        // Should be able to acquire expired lock
        $lockAcquired = $this->invokeMethod($this->migrations, 'acquireLock');
        $this->assertTrue($lockAcquired, 'Should acquire expired lock');
    }

    /**
     * Test lock clearing
     *
     * Verifies that clearLock removes stuck locks.
     *
     * @return void
     */
    public function test_clearLock_should_remove_stuck_locks(): void
    {
        // Set lock
        $this->invokeMethod($this->migrations, 'acquireLock');

        // Clear lock
        $result = $this->migrations->clearLock();
        $this->assertTrue($result, 'Lock clearing should succeed');

        // Should be able to acquire lock again
        $lockAcquired = $this->invokeMethod($this->migrations, 'acquireLock');
        $this->assertTrue($lockAcquired, 'Should acquire lock after clearing');
    }

    /**
     * Test placeholder replacement in SQL
     *
     * Verifies that SQL placeholders are replaced correctly.
     *
     * @return void
     */
    public function test_replacePlaceholders_should_replace_sql_placeholders(): void
    {
        $sql = "CREATE TABLE {prefix}test_table (id INT) {charset_collate};";
        
        $replacedSql = $this->invokeMethod($this->migrations, 'replacePlaceholders', [$sql]);

        $this->assertStringContains($this->wpdb->prefix, $replacedSql, 'Should replace {prefix} placeholder');
        $this->assertStringContains($this->wpdb->get_charset_collate(), $replacedSql, 'Should replace {charset_collate} placeholder');
        $this->assertStringNotContains('{prefix}', $replacedSql, 'Should not contain unprocessed placeholders');
        $this->assertStringNotContains('{charset_collate}', $replacedSql, 'Should not contain unprocessed placeholders');
    }

    /**
     * Test SQL statement splitting
     *
     * Verifies that SQL is split into individual statements correctly.
     *
     * @return void
     */
    public function test_splitSqlStatements_should_split_statements_correctly(): void
    {
        $sql = "
            CREATE TABLE test1 (id INT);
            -- This is a comment
            CREATE TABLE test2 (id INT);
            /* Multi-line
               comment */
            INSERT INTO test1 VALUES (1);
        ";

        $statements = $this->invokeMethod($this->migrations, 'splitSqlStatements', [$sql]);

        $this->assertIsArray($statements, 'Should return array of statements');
        $this->assertCount(3, $statements, 'Should return 3 statements (comments removed)');
        $this->assertStringContains('CREATE TABLE test1', $statements[0], 'First statement should be CREATE TABLE test1');
        $this->assertStringContains('CREATE TABLE test2', $statements[1], 'Second statement should be CREATE TABLE test2');
        $this->assertStringContains('INSERT INTO test1', $statements[2], 'Third statement should be INSERT');
    }

    /**
     * Test migrations to apply calculation
     *
     * Verifies that getMigrationsToApply returns correct migrations.
     *
     * @return void
     */
    public function test_getMigrationsToApply_should_return_correct_migrations(): void
    {
        $migrationsToApply = $this->invokeMethod($this->migrations, 'getMigrationsToApply', ['0', '1.0.0', false]);

        $this->assertIsArray($migrationsToApply, 'Should return array of migrations');
        $this->assertCount(2, $migrationsToApply, 'Should return 2 migrations');
        $this->assertEquals('create_conversations_table', $migrationsToApply[0], 'First migration should be conversations table');
        $this->assertEquals('create_knowledge_base_table', $migrationsToApply[1], 'Second migration should be knowledge base table');
    }

    /**
     * Test migration execution
     *
     * Verifies that applyMigration executes migration correctly.
     *
     * @return void
     */
    public function test_applyMigration_should_execute_migration_successfully(): void
    {
        // Apply first migration
        $result = $this->invokeMethod($this->migrations, 'applyMigration', ['create_conversations_table']);

        $this->assertIsArray($result, 'Result should be array');
        $this->assertArrayHasKey('success', $result, 'Result should contain success key');
        $this->assertTrue($result['success'], 'Migration should succeed');

        // Check that migration was marked as applied
        $versionData = $this->getPropertyValue($this->migrations, 'versionData');
        $this->assertTrue($versionData['migrations']['create_conversations_table']['applied'], 'Migration should be marked as applied');
        $this->assertNotNull($versionData['migrations']['create_conversations_table']['applied_at'], 'Applied at should be set');
        $this->assertNotNull($versionData['migrations']['create_conversations_table']['checksum'], 'Checksum should be calculated');

        // Verify table was created
        $tableExists = $this->wpdb->get_var("SHOW TABLES LIKE '{$this->wpdb->prefix}woo_ai_conversations'");
        $this->assertEquals("{$this->wpdb->prefix}woo_ai_conversations", $tableExists, 'Table should be created');
    }

    /**
     * Test migration with missing dependencies
     *
     * Verifies that migrations with unmet dependencies fail appropriately.
     *
     * @return void
     */
    public function test_applyMigration_should_fail_with_unmet_dependencies(): void
    {
        // Try to apply second migration without first one
        $result = $this->invokeMethod($this->migrations, 'applyMigration', ['create_knowledge_base_table']);

        $this->assertIsArray($result, 'Result should be array');
        $this->assertArrayHasKey('success', $result, 'Result should contain success key');
        $this->assertFalse($result['success'], 'Migration should fail with unmet dependencies');
        $this->assertStringContains('depends on', $result['error'], 'Error should mention dependency');
    }

    /**
     * Test migration with missing file
     *
     * Verifies that migrations with missing SQL files fail appropriately.
     *
     * @return void
     */
    public function test_applyMigration_should_fail_with_missing_file(): void
    {
        // Add migration with non-existent file
        $versionData = $this->getPropertyValue($this->migrations, 'versionData');
        $versionData['migrations']['missing_file_migration'] = [
            'version' => '0.3',
            'file' => 'nonexistent.sql',
            'applied' => false,
            'dependencies' => []
        ];
        $this->setPropertyValue($this->migrations, 'versionData', $versionData);

        $result = $this->invokeMethod($this->migrations, 'applyMigration', ['missing_file_migration']);

        $this->assertFalse($result['success'], 'Migration should fail with missing file');
        $this->assertStringContains('file not found', $result['error'], 'Error should mention missing file');
    }

    /**
     * Test complete migration run
     *
     * Verifies that runMigrations executes all pending migrations.
     *
     * @return void
     */
    public function test_runMigrations_should_execute_all_pending_migrations(): void
    {
        $result = $this->migrations->runMigrations(['backup' => false]); // Skip backup for test

        $this->assertIsArray($result, 'Result should be array');
        $this->assertArrayHasKey('success', $result, 'Result should contain success key');
        $this->assertArrayHasKey('applied_migrations', $result, 'Result should contain applied migrations');
        $this->assertArrayHasKey('errors', $result, 'Result should contain errors array');

        if ($result['success']) {
            $this->assertCount(2, $result['applied_migrations'], 'Should apply 2 migrations');
            $this->assertContains('create_conversations_table', $result['applied_migrations'], 'Should apply conversations migration');
            $this->assertContains('create_knowledge_base_table', $result['applied_migrations'], 'Should apply knowledge base migration');

            // Check version was updated
            $this->assertEquals('1.0.0', $this->migrations->getCurrentVersion(), 'Current version should be updated');
        }
    }

    /**
     * Test migration run with no pending migrations
     *
     * Verifies that runMigrations handles case with no pending migrations.
     *
     * @return void
     */
    public function test_runMigrations_should_handle_no_pending_migrations(): void
    {
        // Mark all migrations as applied
        $versionData = $this->getPropertyValue($this->migrations, 'versionData');
        foreach ($versionData['migrations'] as $id => $migration) {
            $versionData['migrations'][$id]['applied'] = true;
        }
        $versionData['current_version'] = '1.0.0';
        $this->setPropertyValue($this->migrations, 'versionData', $versionData);

        $result = $this->migrations->runMigrations();

        $this->assertTrue($result['success'], 'Should succeed with no pending migrations');
        $this->assertStringContains('No migrations to apply', $result['message'], 'Should indicate no migrations');
        $this->assertEmpty($result['applied_migrations'], 'Should not apply any migrations');
    }

    /**
     * Test migration rollback
     *
     * Verifies that rollbackMigration reverses migration correctly.
     *
     * @return void
     */
    public function test_rollbackMigration_should_reverse_migration(): void
    {
        // First apply migration
        $applyResult = $this->invokeMethod($this->migrations, 'applyMigration', ['create_conversations_table']);
        $this->assertTrue($applyResult['success'], 'Migration should apply successfully');

        // Then rollback
        $rollbackResult = $this->migrations->rollbackMigration('create_conversations_table');

        $this->assertIsArray($rollbackResult, 'Rollback result should be array');
        $this->assertTrue($rollbackResult['success'], 'Rollback should succeed');

        // Check that migration was marked as not applied
        $versionData = $this->getPropertyValue($this->migrations, 'versionData');
        $this->assertFalse($versionData['migrations']['create_conversations_table']['applied'], 'Migration should be marked as not applied');

        // Check rollback history
        $this->assertNotEmpty($versionData['rollback_history'], 'Rollback history should be recorded');
        $this->assertEquals('create_conversations_table', $versionData['rollback_history'][0]['migration_id'], 'Rollback history should contain migration ID');

        // Verify table was dropped
        $tableExists = $this->wpdb->get_var("SHOW TABLES LIKE '{$this->wpdb->prefix}woo_ai_conversations'");
        $this->assertNull($tableExists, 'Table should be dropped after rollback');
    }

    /**
     * Test rollback of unapplied migration
     *
     * Verifies that rolling back unapplied migration is handled gracefully.
     *
     * @return void
     */
    public function test_rollbackMigration_should_handle_unapplied_migration(): void
    {
        $result = $this->migrations->rollbackMigration('create_conversations_table');

        $this->assertTrue($result['success'], 'Should succeed for unapplied migration');
        $this->assertStringContains('is not applied', $result['message'], 'Should indicate migration is not applied');
    }

    /**
     * Test rollback with missing rollback file
     *
     * Verifies that rollback fails appropriately when rollback script is missing.
     *
     * @return void
     */
    public function test_rollbackMigration_should_fail_with_missing_rollback_file(): void
    {
        // First apply migration
        $this->invokeMethod($this->migrations, 'applyMigration', ['create_conversations_table']);

        // Remove rollback file reference
        $versionData = $this->getPropertyValue($this->migrations, 'versionData');
        $versionData['migrations']['create_conversations_table']['rollback_file'] = '';
        $this->setPropertyValue($this->migrations, 'versionData', $versionData);

        $result = $this->migrations->rollbackMigration('create_conversations_table');

        $this->assertFalse($result['success'], 'Rollback should fail without rollback script');
        $this->assertStringContains('No rollback script', $result['error'], 'Error should mention missing rollback script');
    }

    /**
     * Test database table dropping
     *
     * Verifies that dropAllTables removes all plugin tables.
     *
     * @return void
     */
    public function test_dropAllTables_should_remove_all_plugin_tables(): void
    {
        // First create some tables by running migrations
        $this->migrations->runMigrations(['backup' => false]);

        // Then drop all tables
        $result = $this->migrations->dropAllTables();

        $this->assertTrue($result, 'Drop all tables should succeed');

        // Verify tables were dropped
        $conversationsTable = $this->wpdb->get_var("SHOW TABLES LIKE '{$this->wpdb->prefix}woo_ai_conversations'");
        $this->assertNull($conversationsTable, 'Conversations table should be dropped');

        $knowledgeBaseTable = $this->wpdb->get_var("SHOW TABLES LIKE '{$this->wpdb->prefix}woo_ai_knowledge_base'");
        $this->assertNull($knowledgeBaseTable, 'Knowledge base table should be dropped');

        // Check version was reset
        $this->assertEquals('0', $this->migrations->getCurrentVersion(), 'Current version should be reset to 0');
    }

    /**
     * Test version reset
     *
     * Verifies that resetToVersion resets database to specific version.
     *
     * @return void
     */
    public function test_resetToVersion_should_reset_to_specific_version(): void
    {
        // Set current version higher
        $versionData = $this->getPropertyValue($this->migrations, 'versionData');
        $versionData['current_version'] = '1.0.0';
        $versionData['migrations']['create_conversations_table']['applied'] = true;
        $versionData['migrations']['create_knowledge_base_table']['applied'] = true;
        $this->setPropertyValue($this->migrations, 'versionData', $versionData);

        // Reset to lower version
        $result = $this->migrations->resetToVersion('0.1');

        $this->assertIsArray($result, 'Reset result should be array');
        $this->assertTrue($result['success'], 'Reset should succeed');

        // Check that version was updated
        $this->assertEquals('0.1', $this->migrations->getCurrentVersion(), 'Current version should be reset');

        // Check that higher version migrations were marked as unapplied
        $versionData = $this->getPropertyValue($this->migrations, 'versionData');
        $this->assertFalse($versionData['migrations']['create_knowledge_base_table']['applied'], 'Higher version migration should be marked as unapplied');
    }

    /**
     * Test SQL execution error handling
     *
     * Verifies that executeMigrationSql handles SQL errors correctly.
     *
     * @return void
     */
    public function test_executeMigrationSql_should_handle_sql_errors(): void
    {
        // Invalid SQL that should cause error
        $invalidSql = "CREATE TABLE invalid_syntax ( invalid );";

        $result = $this->invokeMethod($this->migrations, 'executeMigrationSql', [$invalidSql]);

        $this->assertIsArray($result, 'Result should be array');
        $this->assertFalse($result['success'], 'Should fail with invalid SQL');
        $this->assertStringContains('Database error', $result['error'], 'Error should mention database error');
    }

    /**
     * Test class name follows PascalCase convention
     *
     * Verifies that the Migrations class follows PascalCase naming convention.
     *
     * @return void
     */
    public function test_migrations_class_name_should_follow_pascal_case_convention(): void
    {
        $this->assertClassFollowsPascalCase(Migrations::class);
    }

    /**
     * Test method names follow camelCase convention
     *
     * Verifies that all public methods follow camelCase naming convention.
     *
     * @return void
     */
    public function test_migrations_public_methods_should_follow_camel_case_convention(): void
    {
        $publicMethods = [
            'getInstance',
            'runMigrations',
            'rollbackMigration',
            'getCurrentVersion',
            'getTargetVersion',
            'isMigrationApplied',
            'getStatus',
            'clearLock',
            'resetToVersion',
            'dropAllTables'
        ];

        foreach ($publicMethods as $methodName) {
            $this->assertMethodFollowsCamelCase($this->migrations, $methodName);
        }
    }

    /**
     * Test private method accessibility through reflection
     *
     * Verifies that private methods can be accessed for testing.
     *
     * @return void
     */
    public function test_private_methods_should_be_accessible_through_reflection(): void
    {
        $privateMethods = [
            'loadVersionData',
            'saveVersionData',
            'applyMigration',
            'executeMigrationSql',
            'splitSqlStatements',
            'replacePlaceholders',
            'getMigrationsToApply',
            'acquireLock',
            'releaseLock',
            'createBackup'
        ];

        foreach ($privateMethods as $methodName) {
            $method = $this->getReflectionMethod($this->migrations, $methodName);
            $this->assertTrue($method->isPrivate(), "Method {$methodName} should be private");
        }
    }

    /**
     * Test memory usage remains reasonable
     *
     * Verifies that the migrations system doesn't consume excessive memory.
     *
     * @return void
     */
    public function test_migrations_memory_usage_should_be_reasonable(): void
    {
        $initialMemory = memory_get_usage();

        // Perform multiple migration operations
        for ($i = 0; $i < 10; $i++) {
            $this->migrations->getCurrentVersion();
            $this->migrations->getTargetVersion();
            $this->migrations->getStatus();
            $this->migrations->isMigrationApplied('create_conversations_table');
        }

        $finalMemory = memory_get_usage();
        $memoryIncrease = $finalMemory - $initialMemory;

        // Memory increase should be less than 1MB for these operations
        $this->assertLessThan(1048576, $memoryIncrease, 'Memory increase should be less than 1MB for repeated operations');
    }

    /**
     * Test error handling in version file loading
     *
     * Verifies that Migrations handles version file errors gracefully.
     *
     * @return void
     */
    public function test_migrations_should_handle_version_file_errors_gracefully(): void
    {
        // Test with corrupted version file
        file_put_contents($this->testMigrationsPath . 'version.json', 'invalid json');

        try {
            $this->invokeMethod($this->migrations, 'loadVersionData');
            $this->fail('Should throw exception for invalid JSON');
        } catch (\RuntimeException $e) {
            $this->assertStringContains('Invalid JSON', $e->getMessage(), 'Should throw JSON error');
        }

        // Test with missing version file
        unlink($this->testMigrationsPath . 'version.json');

        try {
            $this->invokeMethod($this->migrations, 'loadVersionData');
            $this->fail('Should throw exception for missing file');
        } catch (\RuntimeException $e) {
            $this->assertStringContains('version file not found', $e->getMessage(), 'Should throw file not found error');
        }
    }

    /**
     * Clean up test data after each test
     *
     * @return void
     */
    protected function cleanUpTestData(): void
    {
        // Drop any test tables that were created
        $tables = [
            $this->wpdb->prefix . 'woo_ai_conversations',
            $this->wpdb->prefix . 'woo_ai_knowledge_base'
        ];

        foreach ($tables as $table) {
            $this->wpdb->query("DROP TABLE IF EXISTS `{$table}`");
        }

        // Clean up test files
        if (file_exists($this->testMigrationsPath)) {
            $files = glob($this->testMigrationsPath . 'test_*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }

            if (file_exists($this->testMigrationsPath . 'version.json')) {
                unlink($this->testMigrationsPath . 'version.json');
            }
        }

        // Restore original version data if possible
        if ($this->originalVersionData) {
            try {
                $this->setPropertyValue($this->migrations, 'versionData', $this->originalVersionData);
            } catch (\Exception $e) {
                // Ignore restoration errors
            }
        }

        parent::cleanUpTestData();
    }

    /**
     * Tear down test environment
     *
     * @return void
     */
    public function tearDown(): void
    {
        $this->cleanUpTestData();
        parent::tearDown();
    }
}