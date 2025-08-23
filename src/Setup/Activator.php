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
class Activator {

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
    public static function activate(): void {
        try {
            Utils::logDebug('Starting plugin activation process');

            // Check system requirements
            self::checkSystemRequirements();

            // Create database tables
            self::createDatabaseTables();

            // Set default options
            self::setDefaultOptions();

            // Setup user capabilities
            self::setupCapabilities();

            // Create necessary directories
            self::createDirectories();

            // Set activation timestamp
            update_option('woo_ai_assistant_activated_at', time());
            update_option('woo_ai_assistant_version', WOO_AI_ASSISTANT_VERSION);

            // Flush rewrite rules
            flush_rewrite_rules();

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
    private static function checkSystemRequirements(): void {
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
     * Create database tables
     *
     * Creates all necessary database tables for the plugin.
     * Uses dbDelta for proper table creation and updates.
     *
     * @since 1.0.0
     * @return void
     */
    private static function createDatabaseTables(): void {
        global $wpdb;

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charset_collate = $wpdb->get_charset_collate();

        // Conversations table
        $table_conversations = $wpdb->prefix . 'woo_ai_conversations';
        $sql_conversations = "CREATE TABLE $table_conversations (
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
        ) $charset_collate;";

        // Messages table
        $table_messages = $wpdb->prefix . 'woo_ai_messages';
        $sql_messages = "CREATE TABLE $table_messages (
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
            FOREIGN KEY (conversation_id) REFERENCES $table_conversations(conversation_id) ON DELETE CASCADE
        ) $charset_collate;";

        // Knowledge base table
        $table_kb = $wpdb->prefix . 'woo_ai_knowledge_base';
        $sql_kb = "CREATE TABLE $table_kb (
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
        ) $charset_collate;";

        // Usage statistics table
        $table_stats = $wpdb->prefix . 'woo_ai_usage_stats';
        $sql_stats = "CREATE TABLE $table_stats (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            date date NOT NULL,
            stat_type varchar(50) NOT NULL,
            stat_value bigint(20) DEFAULT 0,
            metadata longtext DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY date_type (date, stat_type),
            KEY date (date),
            KEY stat_type (stat_type)
        ) $charset_collate;";

        // Failed requests table (for debugging and monitoring)
        $table_failed = $wpdb->prefix . 'woo_ai_failed_requests';
        $sql_failed = "CREATE TABLE $table_failed (
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
        ) $charset_collate;";

        // Agent actions table (for advanced features)
        $table_actions = $wpdb->prefix . 'woo_ai_agent_actions';
        $sql_actions = "CREATE TABLE $table_actions (
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
        ) $charset_collate;";

        // Execute table creation
        dbDelta($sql_conversations);
        dbDelta($sql_messages);
        dbDelta($sql_kb);
        dbDelta($sql_stats);
        dbDelta($sql_failed);
        dbDelta($sql_actions);

        Utils::logDebug('Database tables created successfully');
    }

    /**
     * Set default plugin options
     *
     * @since 1.0.0
     * @return void
     */
    private static function setDefaultOptions(): void {
        $default_options = [
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
            'woo_ai_assistant_debug_mode' => WOO_AI_ASSISTANT_DEBUG ? 'yes' : 'no',
            'woo_ai_assistant_cache_ttl' => 3600,
            'woo_ai_assistant_conversation_timeout' => 1800,
        ];

        foreach ($default_options as $option_name => $default_value) {
            add_option($option_name, $default_value);
        }

        Utils::logDebug('Default options set successfully');
    }

    /**
     * Setup user capabilities
     *
     * @since 1.0.0
     * @return void
     */
    private static function setupCapabilities(): void {
        // Get administrator role
        $admin_role = get_role('administrator');
        
        if ($admin_role) {
            // Add custom capabilities
            $admin_role->add_cap('manage_woo_ai_assistant');
            $admin_role->add_cap('view_woo_ai_conversations');
            $admin_role->add_cap('export_woo_ai_data');
        }

        // Get shop manager role
        $shop_manager_role = get_role('shop_manager');
        
        if ($shop_manager_role) {
            $shop_manager_role->add_cap('view_woo_ai_conversations');
        }

        Utils::logDebug('User capabilities set up successfully');
    }

    /**
     * Create necessary directories
     *
     * @since 1.0.0
     * @return void
     */
    private static function createDirectories(): void {
        $upload_dir = wp_upload_dir();
        $plugin_upload_dir = $upload_dir['basedir'] . '/woo-ai-assistant';

        // Create main upload directory
        if (!file_exists($plugin_upload_dir)) {
            wp_mkdir_p($plugin_upload_dir);
        }

        // Create logs directory
        $logs_dir = $plugin_upload_dir . '/logs';
        if (!file_exists($logs_dir)) {
            wp_mkdir_p($logs_dir);
        }

        // Create cache directory
        $cache_dir = $plugin_upload_dir . '/cache';
        if (!file_exists($cache_dir)) {
            wp_mkdir_p($cache_dir);
        }

        // Create .htaccess file to protect directories
        $htaccess_content = "Order deny,allow\nDeny from all\n";
        file_put_contents($plugin_upload_dir . '/.htaccess', $htaccess_content);

        Utils::logDebug('Plugin directories created successfully');
    }

    /**
     * Get activation timestamp
     *
     * @since 1.0.0
     * @return int|false Activation timestamp or false if not found
     */
    public static function getActivationTime() {
        return get_option('woo_ai_assistant_activated_at', false);
    }

    /**
     * Check if plugin was recently activated
     *
     * @since 1.0.0
     * @param int $seconds Seconds to consider as "recent"
     * @return bool True if recently activated
     */
    public static function isRecentlyActivated(int $seconds = 300): bool {
        $activation_time = self::getActivationTime();
        
        if (false === $activation_time) {
            return false;
        }

        return (time() - $activation_time) <= $seconds;
    }
}