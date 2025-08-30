# Database Migrations

This directory contains database migration files for the Woo AI Assistant plugin.

## Migration System Overview

The migration system provides a safe, versioned approach to database schema changes with automatic rollback capabilities and comprehensive tracking.

## Files Structure

```
migrations/
├── README.md                 # This documentation file
├── version.json             # Migration version tracking and metadata
├── 001_initial_schema.sql   # Initial database schema creation
└── (future migration files)
```

## Migration Files Naming Convention

Migration files follow the pattern: `{version}_descriptive_name.sql`

- **Version**: 3-digit zero-padded number (001, 002, 003, etc.)
- **Name**: Snake_case descriptive name
- **Extension**: Always `.sql`

Examples:
- `001_initial_schema.sql`
- `002_add_conversation_tags.sql`
- `003_optimize_indexes.sql`

## Database Tables Created

### Core Tables (Migration 001)

#### 1. `woo_ai_conversations`
Tracks user conversations with the AI assistant.

**Key Columns:**
- `id` - Primary key
- `user_id` - WordPress user ID (nullable for guests)
- `session_id` - Unique session identifier
- `status` - Conversation status (active, completed, abandoned)
- `rating` - User rating (1-5 stars)
- `handoff_requested` - Whether human handoff was requested

#### 2. `woo_ai_messages`
Stores individual messages within conversations.

**Key Columns:**
- `conversation_id` - Foreign key to conversations table
- `role` - Message role (user, assistant, system)
- `content` - Message content
- `tokens_used` - AI tokens consumed
- `model_used` - AI model used for response

#### 3. `woo_ai_knowledge_base`
Stores indexed content chunks with embeddings for RAG.

**Key Columns:**
- `content_type` - Type of content (product, page, post, etc.)
- `content_id` - WordPress object ID
- `chunk_text` - Text content chunk
- `embedding` - Vector embedding data
- `chunk_hash` - Unique hash for deduplication

#### 4. `woo_ai_settings`
Plugin configuration and settings storage.

**Key Columns:**
- `setting_key` - Unique setting identifier
- `setting_value` - Setting value (JSON supported)
- `setting_group` - Logical grouping
- `is_sensitive` - Whether setting contains sensitive data

#### 5. `woo_ai_analytics`
Performance metrics and usage statistics.

**Key Columns:**
- `metric_type` - Type of metric being tracked
- `metric_value` - Numeric metric value
- `context` - Additional context data
- `conversation_id` - Associated conversation (optional)

#### 6. `woo_ai_action_logs`
Audit trail for all actions performed by the assistant.

**Key Columns:**
- `action_type` - Type of action performed
- `details` - Action details (JSON)
- `success` - Whether action succeeded
- `severity` - Log severity level

### Database Views

#### `woo_ai_conversation_summary`
Provides summary information about conversations including user names and message counts.

#### `woo_ai_kb_summary`
Aggregates knowledge base statistics by content type.

## Migration Execution

### Automatic Migration (Recommended)

Migrations run automatically during plugin activation when `auto_migrate_on_activation` is enabled in `version.json`.

### Manual Migration

```php
use WooAiAssistant\Database\Migrations;

$migrations = new Migrations();
$result = $migrations->runMigrations();

if ($result['success']) {
    echo "Migrations completed successfully";
} else {
    echo "Migration failed: " . $result['error'];
}
```

### Rollback Migration

```php
$migrations = new Migrations();
$result = $migrations->rollbackMigration('001');
```

## Migration Safety Features

### 1. Version Tracking
- Each migration is tracked in `version.json`
- Applied migrations are marked with timestamp
- Current database version is maintained

### 2. Automatic Backup
- Database backup created before migration (if enabled)
- Rollback capability to previous version
- Transaction-based execution for atomicity

### 3. Dependency Management
- Migration dependencies are enforced
- Prevents running migrations out of order
- Validates prerequisites before execution

### 4. Checksum Verification
- Migration file integrity verification
- Prevents execution of modified files
- Ensures consistency across environments

### 5. Lock Mechanism
- Prevents concurrent migration execution
- Automatic lock timeout and cleanup
- Safe for multi-server environments

## Development Guidelines

### Writing New Migrations

1. **Create Sequential File**
   ```
   migrations/002_your_migration_name.sql
   ```

2. **Update version.json**
   ```json
   {
     "002": {
       "version": "2",
       "name": "your_migration_name",
       "description": "Description of changes",
       "file": "002_your_migration_name.sql",
       "dependencies": ["001"]
     }
   }
   ```

3. **Use WordPress dbDelta Syntax**
   ```sql
   CREATE TABLE IF NOT EXISTS `{prefix}new_table` (
     `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
     PRIMARY KEY (`id`)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
   ```

### Best Practices

1. **Always Use IF NOT EXISTS**
   - Makes migrations idempotent
   - Safe to run multiple times

2. **Include Proper Indexes**
   - Add indexes for foreign keys
   - Consider query patterns for performance

3. **Use Transactions**
   - Wrap related changes in transactions
   - Ensure atomicity of complex migrations

4. **Test Rollback Scenarios**
   - Provide rollback scripts when possible
   - Test rollback before deploying

5. **Document Changes**
   - Add comments explaining complex changes
   - Update this README for new tables/views

## Error Handling

### Common Migration Errors

1. **Table Already Exists**
   - Use `IF NOT EXISTS` clause
   - Check for existing data before modifications

2. **Foreign Key Constraints**
   - Ensure referenced tables exist first
   - Use proper constraint names

3. **Index Conflicts**
   - Check for existing indexes
   - Use unique index names

4. **Data Type Mismatches**
   - Validate data compatibility
   - Provide data transformation scripts

### Recovery Procedures

1. **Migration Stuck in Progress**
   ```php
   $migrations = new Migrations();
   $migrations->clearLock();
   ```

2. **Corrupted Migration State**
   ```php
   // Reset to specific version
   $migrations->resetToVersion('001');
   ```

3. **Complete Database Reset**
   ```php
   // WARNING: This will destroy all data
   $migrations->dropAllTables();
   $migrations->runMigrations();
   ```

## Testing Migrations

### Unit Testing
```php
class MigrationsTest extends WP_UnitTestCase {
    public function testInitialSchemaMigration() {
        $migrations = new Migrations();
        $result = $migrations->runMigrations();
        
        $this->assertTrue($result['success']);
        $this->assertTableExists('woo_ai_conversations');
    }
}
```

### Integration Testing
```bash
# Run migration on test database
wp woo-ai migrate --env=test

# Verify table structure
wp woo-ai verify-schema --env=test
```

## Production Deployment

### Pre-Deployment Checklist

- [ ] Migration files tested in staging environment
- [ ] Rollback procedures documented and tested  
- [ ] Database backup strategy confirmed
- [ ] Migration execution time estimated
- [ ] Dependency requirements verified

### Deployment Steps

1. **Backup Production Database**
2. **Upload Migration Files**
3. **Test Migration in Staging**
4. **Execute Migration in Production**
5. **Verify Migration Success**
6. **Monitor Application Performance**

## Monitoring and Maintenance

### Migration Logs
Check WordPress debug.log for migration execution details:
```
[30-Aug-2025 12:00:00 UTC] Woo AI Assistant: Starting migration 001
[30-Aug-2025 12:00:05 UTC] Woo AI Assistant: Migration 001 completed successfully
```

### Performance Impact
- Monitor database size growth
- Check query performance after schema changes
- Optimize indexes based on usage patterns

### Regular Maintenance
- Archive old conversation data
- Cleanup orphaned records
- Optimize database tables periodically

## Support and Troubleshooting

For migration-related issues:

1. **Enable Debug Logging**
   ```php
   define('WOO_AI_ASSISTANT_DEBUG', true);
   ```

2. **Check Migration Status**
   ```php
   $migrations = new Migrations();
   $status = $migrations->getStatus();
   ```

3. **Verify Database State**
   ```php
   $schema = new Schema();
   $validation = $schema->validateSchema();
   ```

4. **Contact Support**
   - Include migration logs
   - Provide database schema export
   - Specify WordPress/WooCommerce versions