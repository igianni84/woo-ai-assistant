<?php

/**
 * Plugin Activator Class
 *
 * Handles plugin activation tasks including database setup, option initialization,
 * capability setup, and initial configuration.
 *
 * @package WooAiAssistant
 * @subpackage Setup
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Setup;

use WooAiAssistant\Common\Utils;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Activator
 *
 * Handles all tasks that need to be performed when the plugin is activated.
 * This includes database setup, default options, and initial configuration.
 *
 * @since 1.0.0
 */
class Activator
{
    /**
     * Current database version
     *
     * @since 1.0.0
     * @var string
     */
    const DATABASE_VERSION = '1.0.0';

    /**
     * Plugin activation handler
     *
     * Performs all necessary tasks when the plugin is activated.
     * This method is called by the activation hook in the main plugin file.
     *
     * @since 1.0.0
     * @return void
     * @throws \Exception If activation fails
     */
    public static function activate(): void
    {
        try {
            Utils::logDebug('Starting plugin activation process');

            // Check system requirements
            self::checkSystemRequirements();

            // Handle database creation or upgrade
            self::handleDatabaseSetup();

            // Set default options
            self::setDefaultOptions();

            // Setup user capabilities
            self::setupCapabilities();

            // Create necessary directories
            self::createDirectories();

            // Setup cron jobs
            self::setupCronJobs();

            // Set activation timestamp and version
            update_option('woo_ai_assistant_activated_at', time());
            update_option('woo_ai_assistant_version', WOO_AI_ASSISTANT_VERSION);
            update_option('woo_ai_assistant_db_version', self::DATABASE_VERSION);

            // Flush rewrite rules
            flush_rewrite_rules();

            // Trigger auto-indexing for immediate functionality (zero-config)
            self::triggerAutoInstallation();

            Utils::logDebug('Plugin activation completed successfully');
        } catch (\Exception $e) {
            Utils::logDebug('Plugin activation failed: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }

    /**
     * Check system requirements
     *
     * @since 1.0.0
     * @return void
     * @throws \Exception If requirements are not met
     */
    private static function checkSystemRequirements(): void
    {
        // Check PHP version
        if (version_compare(PHP_VERSION, '8.2', '<')) {
            throw new \Exception(
                sprintf(
                    'Woo AI Assistant requires PHP 8.2 or higher. Current version: %s',
                    PHP_VERSION
                )
            );
        }

        // Check WordPress version
        global $wp_version;
        if (version_compare($wp_version, '6.0', '<')) {
            throw new \Exception(
                sprintf(
                    'Woo AI Assistant requires WordPress 6.0 or higher. Current version: %s',
                    $wp_version
                )
            );
        }

        // Check if WooCommerce is active
        if (!Utils::isWooCommerceActive()) {
            throw new \Exception('Woo AI Assistant requires WooCommerce to be installed and active.');
        }

        // Check WooCommerce version
        if (defined('WC_VERSION') && version_compare(WC_VERSION, '7.0', '<')) {
            throw new \Exception(
                sprintf(
                    'Woo AI Assistant requires WooCommerce 7.0 or higher. Current version: %s',
                    WC_VERSION
                )
            );
        }

        Utils::logDebug('System requirements check passed');
    }

    /**
     * Handle database setup and upgrades
     *
     * Determines whether to create tables or upgrade existing ones
     * based on current database version.
     *
     * @since 1.0.0
     * @return void
     */
    private static function handleDatabaseSetup(): void
    {
        $currentDbVersion = get_option('woo_ai_assistant_db_version', '0.0.0');

        if (version_compare($currentDbVersion, self::DATABASE_VERSION, '<')) {
            Utils::logDebug("Database upgrade needed: {$currentDbVersion} -> " . self::DATABASE_VERSION);
            self::createDatabaseTables();
            self::upgradeDatabaseSchema($currentDbVersion);
            update_option('woo_ai_assistant_db_version', self::DATABASE_VERSION);
            Utils::logDebug('Database upgrade completed');
        } else {
            Utils::logDebug('Database schema is up to date');
        }
    }

    /**
     * Create database tables
     *
     * Creates all necessary database tables for the plugin.
     * Uses dbDelta for proper table creation and updates.
     *
     * @since 1.0.0
     * @return void
     */
    private static function createDatabaseTables(): void
    {
        global $wpdb;

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charsetCollate = $wpdb->get_charset_collate();

        // Conversations table
        $tableConversations = $wpdb->prefix . 'woo_ai_conversations';
        $sqlConversations = "CREATE TABLE $tableConversations (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            conversation_id varchar(255) NOT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            session_id varchar(255) NOT NULL,
            status varchar(20) DEFAULT 'active',
            context longtext DEFAULT NULL,
            started_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            ended_at datetime DEFAULT NULL,
            total_messages int(11) DEFAULT 0,
            user_rating tinyint(1) DEFAULT NULL,
            user_feedback text DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY conversation_id (conversation_id),
            KEY user_id (user_id),
            KEY session_id (session_id),
            KEY status (status),
            KEY started_at (started_at)
        ) $charsetCollate;";

        // Messages table
        $tableMessages = $wpdb->prefix . 'woo_ai_messages';
        $sqlMessages = "CREATE TABLE $tableMessages (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            conversation_id varchar(255) NOT NULL,
            message_type enum('user','assistant','system') NOT NULL,
            message_content longtext NOT NULL,
            metadata longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            tokens_used int(11) DEFAULT NULL,
            model_used varchar(100) DEFAULT NULL,
            confidence_score decimal(3,2) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY conversation_id (conversation_id),
            KEY message_type (message_type),
            KEY created_at (created_at),
            FOREIGN KEY (conversation_id) REFERENCES $tableConversations(conversation_id) ON DELETE CASCADE
        ) $charsetCollate;";

        // Knowledge base table
        $tableKb = $wpdb->prefix . 'woo_ai_knowledge_base';
        $sqlKb = "CREATE TABLE $tableKb (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            source_type varchar(50) NOT NULL,
            source_id bigint(20) unsigned DEFAULT NULL,
            title text NOT NULL,
            content longtext NOT NULL,
            chunk_content longtext NOT NULL,
            chunk_index int(11) DEFAULT 0,
            embedding longtext DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            hash varchar(64) NOT NULL,
            indexed_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY source_type (source_type),
            KEY source_id (source_id),
            KEY hash (hash),
            KEY indexed_at (indexed_at),
            FULLTEXT KEY content_search (title, content, chunk_content)
        ) $charsetCollate;";

        // Usage statistics table
        $tableStats = $wpdb->prefix . 'woo_ai_usage_stats';
        $sqlStats = "CREATE TABLE $tableStats (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            date date NOT NULL,
            stat_type varchar(50) NOT NULL,
            stat_value bigint(20) DEFAULT 0,
            metadata longtext DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY date_type (date, stat_type),
            KEY date (date),
            KEY stat_type (stat_type)
        ) $charsetCollate;";

        // Failed requests table (for debugging and monitoring)
        $tableFailed = $wpdb->prefix . 'woo_ai_failed_requests';
        $sqlFailed = "CREATE TABLE $tableFailed (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            request_type varchar(50) NOT NULL,
            request_data longtext DEFAULT NULL,
            error_message text DEFAULT NULL,
            error_code varchar(20) DEFAULT NULL,
            retry_count int(11) DEFAULT 0,
            failed_at datetime DEFAULT CURRENT_TIMESTAMP,
            resolved_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY request_type (request_type),
            KEY failed_at (failed_at),
            KEY resolved_at (resolved_at)
        ) $charsetCollate;";

        // Agent actions table (for advanced features)
        $tableActions = $wpdb->prefix . 'woo_ai_agent_actions';
        $sqlActions = "CREATE TABLE $tableActions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            conversation_id varchar(255) NOT NULL,
            action_type varchar(50) NOT NULL,
            action_data longtext DEFAULT NULL,
            status varchar(20) DEFAULT 'pending',
            executed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            result longtext DEFAULT NULL,
            PRIMARY KEY (id),
            KEY conversation_id (conversation_id),
            KEY action_type (action_type),
            KEY status (status),
            KEY executed_at (executed_at)
        ) $charsetCollate;";

        // Execute table creation with error handling
        try {
            $results = [];
            $results[] = dbDelta($sqlConversations);
            $results[] = dbDelta($sqlMessages);
            $results[] = dbDelta($sqlKb);
            $results[] = dbDelta($sqlStats);
            $results[] = dbDelta($sqlFailed);
            $results[] = dbDelta($sqlActions);

            // Verify tables were created
            self::verifyTablesExist();

            // Log successful table creation
            Utils::logDebug('Database tables created successfully');

            // Log detailed results if debug enabled
            if (defined('WOO_AI_ASSISTANT_DEBUG') && WOO_AI_ASSISTANT_DEBUG) {
                Utils::logDebug('dbDelta results: ' . print_r($results, true));
            }
        } catch (\Exception $e) {
            Utils::logDebug('Database table creation failed: ' . $e->getMessage(), 'error');
            throw new \Exception('Failed to create database tables: ' . $e->getMessage());
        }
    }

    /**
     * Upgrade database schema between versions
     *
     * Handles incremental upgrades from older database versions.
     *
     * @since 1.0.0
     * @param string $fromVersion The version being upgraded from
     * @return void
     */
    private static function upgradeDatabaseSchema(string $fromVersion): void
    {
        global $wpdb;

        Utils::logDebug("Upgrading database schema from version {$fromVersion}");

        // Future version upgrades will be handled here
        // Example:
        // if (version_compare($fromVersion, '1.1.0', '<')) {
        //     // Upgrade logic for version 1.1.0
        // }

        // For now, just log that no upgrades are needed
        if ($fromVersion === '0.0.0') {
            Utils::logDebug('Fresh installation - no schema upgrades needed');
        } else {
            Utils::logDebug('No schema upgrades required for this version');
        }
    }

    /**
     * Verify that all required tables exist
     *
     * @since 1.0.0
     * @return void
     * @throws \Exception If any required table is missing
     */
    private static function verifyTablesExist(): void
    {
        global $wpdb;

        $requiredTables = [
            'woo_ai_conversations',
            'woo_ai_messages',
            'woo_ai_knowledge_base',
            'woo_ai_usage_stats',
            'woo_ai_failed_requests',
            'woo_ai_agent_actions'
        ];

        $missingTables = [];

        foreach ($requiredTables as $tableName) {
            $fullTableName = $wpdb->prefix . $tableName;
            $tableExists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
                DB_NAME,
                $fullTableName
            ));

            if (!$tableExists) {
                $missingTables[] = $fullTableName;
            }
        }

        if (!empty($missingTables)) {
            throw new \Exception('Failed to create required tables: ' . implode(', ', $missingTables));
        }

        Utils::logDebug('All required database tables verified');
    }

    /**
     * Set default plugin options
     *
     * @since 1.0.0
     * @return void
     */
    private static function setDefaultOptions(): void
    {
        $defaultOptions = [
            // General settings
            'woo_ai_assistant_enabled' => 'yes',
            'woo_ai_assistant_widget_position' => 'bottom-right',
            'woo_ai_assistant_widget_color' => '#0073aa',
            'woo_ai_assistant_welcome_message' => __('Hi! How can I help you today?', 'woo-ai-assistant'),

            // AI settings
            'woo_ai_assistant_ai_model' => 'gemini-2.5-flash',
            'woo_ai_assistant_max_tokens' => 500,
            'woo_ai_assistant_temperature' => 0.7,

            // Knowledge base settings
            'woo_ai_assistant_auto_index' => 'yes',
            'woo_ai_assistant_index_products' => 'yes',
            'woo_ai_assistant_index_pages' => 'yes',
            'woo_ai_assistant_index_posts' => 'no',

            // Usage limits (free plan defaults)
            'woo_ai_assistant_monthly_limit' => 25,
            'woo_ai_assistant_current_usage' => 0,
            'woo_ai_assistant_reset_date' => date('Y-m-01'),

            // Feature flags
            'woo_ai_assistant_proactive_triggers' => 'no',
            'woo_ai_assistant_coupon_generation' => 'no',
            'woo_ai_assistant_cart_actions' => 'no',

            // Advanced settings
            'woo_ai_assistant_debug_mode' => defined('WOO_AI_ASSISTANT_DEBUG') && WOO_AI_ASSISTANT_DEBUG ? 'yes' : 'no',
            'woo_ai_assistant_cache_ttl' => 3600,
            'woo_ai_assistant_conversation_timeout' => 1800,

            // Database and performance settings
            'woo_ai_assistant_cleanup_frequency' => 'weekly',
            'woo_ai_assistant_max_conversation_age' => 90, // days
            'woo_ai_assistant_max_failed_requests' => 1000,

            // Security settings
            'woo_ai_assistant_rate_limit_per_hour' => 100,
            'woo_ai_assistant_max_message_length' => 2000,
            'woo_ai_assistant_allowed_user_roles' => 'all'
        ];

        foreach ($defaultOptions as $optionName => $defaultValue) {
            add_option($optionName, $defaultValue);
        }

        Utils::logDebug('Default options set successfully');
    }

    /**
     * Setup user capabilities
     *
     * @since 1.0.0
     * @return void
     */
    private static function setupCapabilities(): void
    {
        // Get administrator role
        $adminRole = get_role('administrator');

        if ($adminRole) {
            // Add custom capabilities
            $adminRole->add_cap('manage_woo_ai_assistant');
            $adminRole->add_cap('view_woo_ai_conversations');
            $adminRole->add_cap('export_woo_ai_data');
        }

        // Get shop manager role
        $shopManagerRole = get_role('shop_manager');

        if ($shopManagerRole) {
            $shopManagerRole->add_cap('view_woo_ai_conversations');
        }

        Utils::logDebug('User capabilities set up successfully');
    }

    /**
     * Create necessary directories
     *
     * @since 1.0.0
     * @return void
     */
    private static function createDirectories(): void
    {
        $uploadDir = wp_upload_dir();
        $pluginUploadDir = $uploadDir['basedir'] . '/woo-ai-assistant';

        // Create main upload directory
        if (!file_exists($pluginUploadDir)) {
            wp_mkdir_p($pluginUploadDir);
        }

        // Create logs directory
        $logsDir = $pluginUploadDir . '/logs';
        if (!file_exists($logsDir)) {
            wp_mkdir_p($logsDir);
        }

        // Create cache directory
        $cacheDir = $pluginUploadDir . '/cache';
        if (!file_exists($cacheDir)) {
            wp_mkdir_p($cacheDir);
        }

        // Create .htaccess file to protect directories
        $htaccessContent = "Order deny,allow\nDeny from all\n";
        file_put_contents($pluginUploadDir . '/.htaccess', $htaccessContent);

        Utils::logDebug('Plugin directories created successfully');
    }

    /**
     * Setup scheduled cron jobs
     *
     * @since 1.0.0
     * @return void
     */
    private static function setupCronJobs(): void
    {
        // Daily cleanup job
        if (!wp_next_scheduled('woo_ai_assistant_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'woo_ai_assistant_daily_cleanup');
        }

        // Weekly statistics compilation
        if (!wp_next_scheduled('woo_ai_assistant_weekly_stats')) {
            wp_schedule_event(time(), 'weekly', 'woo_ai_assistant_weekly_stats');
        }

        // Knowledge base reindexing (monthly)
        if (!wp_next_scheduled('woo_ai_assistant_kb_reindex')) {
            wp_schedule_event(time(), 'monthly', 'woo_ai_assistant_kb_reindex');
        }

        // Usage statistics reset (monthly)
        if (!wp_next_scheduled('woo_ai_assistant_usage_reset')) {
            $nextMonth = strtotime('first day of next month 00:00:00');
            wp_schedule_event($nextMonth, 'monthly', 'woo_ai_assistant_usage_reset');
        }

        // Health check (hourly)
        if (!wp_next_scheduled('woo_ai_assistant_health_check')) {
            wp_schedule_event(time(), 'hourly', 'woo_ai_assistant_health_check');
        }

        Utils::logDebug('Cron jobs scheduled successfully');
    }

    /**
     * Get activation timestamp
     *
     * @since 1.0.0
     * @return int|false Activation timestamp or false if not found
     */
    public static function getActivationTime()
    {
        return get_option('woo_ai_assistant_activated_at', false);
    }

    /**
     * Check if plugin was recently activated
     *
     * @since 1.0.0
     * @param int $seconds Seconds to consider as "recent"
     * @return bool True if recently activated
     */
    public static function isRecentlyActivated(int $seconds = 300): bool
    {
        $activationTime = self::getActivationTime();

        if (false === $activationTime) {
            return false;
        }

        return (time() - $activationTime) <= $seconds;
    }

    /**
     * Get current database version
     *
     * @since 1.0.0
     * @return string Current database version
     */
    public static function getDatabaseVersion(): string
    {
        return get_option('woo_ai_assistant_db_version', '0.0.0');
    }

    /**
     * Check if database needs upgrading
     *
     * @since 1.0.0
     * @return bool True if database upgrade is needed
     */
    public static function isDatabaseUpgradeNeeded(): bool
    {
        $currentVersion = self::getDatabaseVersion();
        return version_compare($currentVersion, self::DATABASE_VERSION, '<');
    }

    /**
     * Get list of all plugin database tables
     *
     * @since 1.0.0
     * @return array Array of table names (without prefix)
     */
    public static function getDatabaseTables(): array
    {
        return [
            'woo_ai_conversations',
            'woo_ai_messages',
            'woo_ai_knowledge_base',
            'woo_ai_usage_stats',
            'woo_ai_failed_requests',
            'woo_ai_agent_actions'
        ];
    }

    /**
     * Get database statistics
     *
     * @since 1.0.0
     * @return array Database statistics including table sizes
     */
    public static function getDatabaseStats(): array
    {
        global $wpdb;

        $stats = [];
        $tables = self::getDatabaseTables();

        foreach ($tables as $tableName) {
            $fullTableName = $wpdb->prefix . $tableName;

            $tableStatus = $wpdb->get_row($wpdb->prepare(
                "SHOW TABLE STATUS WHERE Name = %s",
                $fullTableName
            ));

            if ($tableStatus) {
                $stats[$tableName] = [
                    'rows' => $tableStatus->Rows ?? 0,
                    'data_length' => $tableStatus->Data_length ?? 0,
                    'index_length' => $tableStatus->Index_length ?? 0,
                    'auto_increment' => $tableStatus->Auto_increment ?? null,
                    'engine' => $tableStatus->Engine ?? 'Unknown',
                    'collation' => $tableStatus->Collation ?? 'Unknown'
                ];
            } else {
                $stats[$tableName] = ['status' => 'missing'];
            }
        }

        return $stats;
    }

    /**
     * Trigger auto-installation for immediate functionality
     *
     * Implements the zero-config philosophy by automatically setting up
     * the plugin for immediate use after activation.
     *
     * @since 1.0.0
     * @return void
     */
    private static function triggerAutoInstallation(): void
    {
        try {
            Utils::logDebug('Starting auto-installation process');

            // Set flag for auto-indexing
            update_option('woo_ai_assistant_needs_auto_install', true);

            // Schedule immediate auto-indexing if possible, otherwise background
            if (self::canRunImmediateAutoInstall()) {
                // Run auto-installation immediately
                self::runImmediateAutoInstall();
            } else {
                // Schedule for background processing
                self::scheduleBackgroundAutoInstall();
            }

            Utils::logDebug('Auto-installation process initiated');
        } catch (\Exception $e) {
            Utils::logError('Failed to trigger auto-installation: ' . $e->getMessage());
            // Don't throw - we don't want activation to fail because of auto-install issues
        }
    }

    /**
     * Check if we can run immediate auto-installation
     *
     * @since 1.0.0
     * @return bool True if immediate auto-install is possible
     */
    private static function canRunImmediateAutoInstall(): bool
    {
        // Check if we're not in CLI or AJAX context
        if (defined('WP_CLI') && WP_CLI) {
            return false;
        }

        if (wp_doing_ajax()) {
            return false;
        }

        // Check memory availability (need at least 32MB free)
        $memoryLimit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
        $memoryUsage = memory_get_usage(true);
        $availableMemory = $memoryLimit - $memoryUsage;

        if ($availableMemory < 33554432) { // 32MB
            return false;
        }

        // Check execution time (need at least 60 seconds)
        $maxExecutionTime = ini_get('max_execution_time');
        if ($maxExecutionTime > 0 && $maxExecutionTime < 60) {
            return false;
        }

        return true;
    }

    /**
     * Run immediate auto-installation
     *
     * @since 1.0.0
     * @return void
     */
    private static function runImmediateAutoInstall(): void
    {
        try {
            Utils::logDebug('Running immediate auto-installation');

            // We need to temporarily bootstrap the plugin to access components
            // This is safe during activation as WordPress is fully loaded
            if (class_exists('WooAiAssistant\Setup\AutoIndexer')) {
                $autoIndexer = \WooAiAssistant\Setup\AutoIndexer::getInstance();
                $results = $autoIndexer->triggerAutoIndexing(true);

                if ($results && !isset($results['error'])) {
                    Utils::logDebug('Immediate auto-installation completed', $results);
                    update_option('woo_ai_assistant_needs_auto_install', false);
                } else {
                    throw new \Exception('Auto-indexing failed: ' . ($results['error'] ?? 'Unknown error'));
                }
            } else {
                throw new \Exception('AutoIndexer class not available during activation');
            }
        } catch (\Exception $e) {
            Utils::logError('Immediate auto-installation failed: ' . $e->getMessage());
            // Fall back to scheduled auto-install
            self::scheduleBackgroundAutoInstall();
        }
    }

    /**
     * Schedule background auto-installation
     *
     * @since 1.0.0
     * @return void
     */
    private static function scheduleBackgroundAutoInstall(): void
    {
        // Cancel any existing auto-install events
        wp_clear_scheduled_hook('woo_ai_assistant_auto_install');

        // Schedule auto-installation for 5 minutes after activation
        wp_schedule_single_event(time() + 300, 'woo_ai_assistant_auto_install');

        Utils::logDebug('Auto-installation scheduled for background processing');
        update_option('woo_ai_assistant_auto_install_scheduled', time());
    }

    /**
     * Check if auto-installation is needed
     *
     * @since 1.0.0
     * @return bool True if auto-installation is needed
     */
    public static function needsAutoInstallation(): bool
    {
        return (bool) get_option('woo_ai_assistant_needs_auto_install', false);
    }

    /**
     * Check if auto-installation was completed
     *
     * @since 1.0.0
     * @return bool True if auto-installation was completed
     */
    public static function isAutoInstallationComplete(): bool
    {
        return !self::needsAutoInstallation() &&
               get_option('woo_ai_assistant_last_auto_index', false) !== false;
    }

    /**
     * Get auto-installation status
     *
     * @since 1.0.0
     * @return array Auto-installation status information
     */
    public static function getAutoInstallationStatus(): array
    {
        return [
            'needs_auto_install' => self::needsAutoInstallation(),
            'is_complete' => self::isAutoInstallationComplete(),
            'scheduled_at' => get_option('woo_ai_assistant_auto_install_scheduled', false),
            'last_completed' => get_option('woo_ai_assistant_last_auto_index', false),
            'activation_time' => self::getActivationTime()
        ];
    }
}
