<?php
/**
 * Plugin Uninstall Script
 *
 * This file is executed when the plugin is deleted from WordPress admin.
 * It handles complete removal of all plugin data including database tables,
 * options, user meta, transients, and uploaded files.
 *
 * @package WooAiAssistant
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

// Exit if uninstall not called from WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Uninstall Woo AI Assistant Plugin
 *
 * This function handles complete removal of all plugin data.
 * It should only be called when the user explicitly deletes the plugin.
 */
function woo_ai_assistant_uninstall() {
    // Security check - ensure this is a legitimate uninstall
    if (!current_user_can('activate_plugins')) {
        return;
    }

    // Optional: Create backup if user requested it
    woo_ai_assistant_backup_data();
    
    // Check if user has confirmed uninstall or skip confirmation in certain cases
    $confirmed = get_option('woo_ai_assistant_confirm_uninstall', true); // Default to true for WordPress.org standard behavior
    if (!$confirmed && !defined('WOO_AI_ASSISTANT_FORCE_UNINSTALL')) {
        // Log the uninstall attempt
        error_log('Woo AI Assistant: Uninstall attempted but not confirmed');
        return;
    }

    try {
        // Log uninstall start
        error_log('Woo AI Assistant: Starting complete plugin uninstall');

        // Remove database tables
        woo_ai_assistant_drop_tables();

        // Remove all plugin options
        woo_ai_assistant_remove_options();

        // Remove user meta data
        woo_ai_assistant_remove_user_meta();

        // Remove transients and cached data
        woo_ai_assistant_remove_transients();

        // Remove user capabilities
        woo_ai_assistant_remove_capabilities();

        // Remove uploaded files and directories
        woo_ai_assistant_remove_files();

        // Remove scheduled cron jobs
        woo_ai_assistant_remove_cron_jobs();

        // Handle multisite cleanup
        woo_ai_assistant_multisite_cleanup();

        // Clean up temporary files and cache directories  
        woo_ai_assistant_cleanup_temp_files();

        // Clear rewrite rules
        flush_rewrite_rules();

        // Log successful uninstall
        error_log('Woo AI Assistant: Plugin uninstalled successfully');

    } catch (Exception $e) {
        error_log('Woo AI Assistant: Uninstall error - ' . $e->getMessage());
    }
}

/**
 * Drop all plugin database tables
 *
 * @since 1.0.0
 */
function woo_ai_assistant_drop_tables() {
    global $wpdb;

    // List of all plugin tables
    $tables = [
        $wpdb->prefix . 'woo_ai_conversations',
        $wpdb->prefix . 'woo_ai_messages',
        $wpdb->prefix . 'woo_ai_knowledge_base',
        $wpdb->prefix . 'woo_ai_usage_stats',
        $wpdb->prefix . 'woo_ai_failed_requests',
        $wpdb->prefix . 'woo_ai_agent_actions',
    ];

    // Drop each table
    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
    }

    error_log('Woo AI Assistant: Database tables removed');
}

/**
 * Remove all plugin options
 *
 * @since 1.0.0
 */
function woo_ai_assistant_remove_options() {
    global $wpdb;

    // Remove all options that start with our plugin prefix
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
         WHERE option_name LIKE 'woo_ai_assistant_%' 
         OR option_name LIKE '_woo_ai_assistant_%'"
    );

    // Remove specific options that might not follow the prefix pattern
    $specific_options = [
        'woo_ai_assistant_version',
        'woo_ai_assistant_activated_at',
        'woo_ai_assistant_deactivated_at',
        'woo_ai_assistant_confirm_uninstall',
    ];

    foreach ($specific_options as $option) {
        delete_option($option);
    }

    error_log('Woo AI Assistant: Plugin options removed');
}

/**
 * Remove user meta data
 *
 * @since 1.0.0
 */
function woo_ai_assistant_remove_user_meta() {
    global $wpdb;

    // Remove all user meta with our plugin prefix
    $wpdb->query(
        "DELETE FROM {$wpdb->usermeta} 
         WHERE meta_key LIKE 'woo_ai_assistant_%' 
         OR meta_key LIKE '_woo_ai_assistant_%'"
    );

    error_log('Woo AI Assistant: User meta data removed');
}

/**
 * Remove transients and cached data
 *
 * @since 1.0.0
 */
function woo_ai_assistant_remove_transients() {
    global $wpdb;

    // Remove all transients related to our plugin
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
         WHERE option_name LIKE '_transient_woo_ai_%' 
         OR option_name LIKE '_transient_timeout_woo_ai_%'"
    );

    // Clear object cache if available
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }

    error_log('Woo AI Assistant: Transients and cache removed');
}

/**
 * Remove user capabilities
 *
 * @since 1.0.0
 */
function woo_ai_assistant_remove_capabilities() {
    // Get all roles
    $roles = wp_roles();
    
    if (!$roles) {
        return;
    }

    // Plugin capabilities to remove
    $capabilities = [
        'manage_woo_ai_assistant',
        'view_woo_ai_conversations',
        'export_woo_ai_data',
    ];

    // Remove capabilities from all roles
    foreach ($roles->roles as $role_name => $role_info) {
        $role = get_role($role_name);
        
        if ($role) {
            foreach ($capabilities as $capability) {
                $role->remove_cap($capability);
            }
        }
    }

    error_log('Woo AI Assistant: User capabilities removed');
}

/**
 * Remove uploaded files and directories
 *
 * @since 1.0.0
 */
function woo_ai_assistant_remove_files() {
    $upload_dir = wp_upload_dir();
    $plugin_dir = $upload_dir['basedir'] . '/woo-ai-assistant';

    if (is_dir($plugin_dir)) {
        woo_ai_assistant_recursive_delete($plugin_dir);
        error_log('Woo AI Assistant: Plugin files and directories removed');
    }
}

/**
 * Recursively delete directory and all contents
 *
 * @since 1.0.0
 * @param string $dir Directory path to delete
 * @return bool True if successful
 */
function woo_ai_assistant_recursive_delete($dir) {
    if (!is_dir($dir)) {
        return false;
    }

    $files = array_diff(scandir($dir), ['.', '..']);
    
    foreach ($files as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        
        if (is_dir($path)) {
            woo_ai_assistant_recursive_delete($path);
        } else {
            unlink($path);
        }
    }

    return rmdir($dir);
}

/**
 * Remove scheduled cron jobs
 *
 * @since 1.0.0
 */
function woo_ai_assistant_remove_cron_jobs() {
    // List of all cron hooks used by the plugin
    $cron_hooks = [
        'woo_ai_assistant_daily_cleanup',
        'woo_ai_assistant_weekly_stats',
        'woo_ai_assistant_kb_reindex',
        'woo_ai_assistant_usage_reset',
        'woo_ai_assistant_health_check',
    ];

    foreach ($cron_hooks as $hook) {
        wp_clear_scheduled_hook($hook);
    }

    error_log('Woo AI Assistant: Scheduled cron jobs removed');
}

/**
 * Backup plugin data before uninstall (optional)
 *
 * @since 1.0.0
 */
function woo_ai_assistant_backup_data() {
    $backup_option = get_option('woo_ai_assistant_backup_on_uninstall', false);
    
    if (!$backup_option) {
        return;
    }

    global $wpdb;

    $backup_data = [];

    // Backup conversations
    $conversations = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}woo_ai_conversations",
        ARRAY_A
    );
    $backup_data['conversations'] = $conversations;

    // Backup messages
    $messages = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}woo_ai_messages",
        ARRAY_A
    );
    $backup_data['messages'] = $messages;

    // Backup knowledge base
    $kb_data = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}woo_ai_knowledge_base",
        ARRAY_A
    );
    $backup_data['knowledge_base'] = $kb_data;

    // Backup settings
    $settings = $wpdb->get_results(
        "SELECT option_name, option_value FROM {$wpdb->options} 
         WHERE option_name LIKE 'woo_ai_assistant_%'",
        ARRAY_A
    );
    $backup_data['settings'] = $settings;

    // Save backup to file
    $upload_dir = wp_upload_dir();
    $backup_file = $upload_dir['basedir'] . '/woo-ai-assistant-backup-' . date('Y-m-d-H-i-s') . '.json';
    
    file_put_contents($backup_file, json_encode($backup_data, JSON_PRETTY_PRINT));
    
    error_log("Woo AI Assistant: Data backed up to {$backup_file}");
}

/**
 * Handle multisite cleanup
 *
 * @since 1.0.0
 */
function woo_ai_assistant_multisite_cleanup() {
    if (!is_multisite()) {
        return;
    }

    global $wpdb;

    // Get all blog IDs
    $blog_ids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs}");

    foreach ($blog_ids as $blog_id) {
        switch_to_blog($blog_id);
        
        // Remove options for this blog
        woo_ai_assistant_remove_options();
        
        // Remove user meta for this blog
        woo_ai_assistant_remove_user_meta();
        
        // Remove transients for this blog
        woo_ai_assistant_remove_transients();
        
        restore_current_blog();
    }

    // Remove network-wide options if any
    delete_site_option('woo_ai_assistant_network_settings');
    delete_site_option('woo_ai_assistant_network_license');

    error_log('Woo AI Assistant: Multisite cleanup completed');
}

/**
 * Clean up temporary files and cache directories
 *
 * @since 1.0.0
 */
function woo_ai_assistant_cleanup_temp_files() {
    // Clean up WordPress cache
    if (function_exists('wp_cache_flush_group')) {
        wp_cache_flush_group('woo_ai_assistant');
    }

    // Clean up any temporary files in wp-content/cache
    $cache_dirs = [
        WP_CONTENT_DIR . '/cache/woo-ai-assistant',
        WP_CONTENT_DIR . '/uploads/woo-ai-assistant/temp',
    ];

    foreach ($cache_dirs as $cache_dir) {
        if (is_dir($cache_dir)) {
            woo_ai_assistant_recursive_delete($cache_dir);
        }
    }

    // Clean up any .htaccess modifications (if we made any)
    $htaccess_file = ABSPATH . '.htaccess';
    if (file_exists($htaccess_file)) {
        $htaccess_content = file_get_contents($htaccess_file);
        
        // Remove any rules we may have added
        $pattern = '/# BEGIN Woo AI Assistant.*?# END Woo AI Assistant\s*\n?/s';
        $cleaned_content = preg_replace($pattern, '', $htaccess_content);
        
        if ($cleaned_content !== $htaccess_content) {
            file_put_contents($htaccess_file, $cleaned_content);
            error_log('Woo AI Assistant: Cleaned .htaccess rules');
        }
    }

    error_log('Woo AI Assistant: Temporary files and cache cleaned up');
}

/**
 * Additional cleanup for development environments
 *
 * @since 1.0.0
 */
function woo_ai_assistant_dev_cleanup() {
    if (!defined('WOO_AI_ASSISTANT_DEBUG') || !WOO_AI_ASSISTANT_DEBUG) {
        return;
    }

    // Remove debug log files
    $debug_files = [
        WP_CONTENT_DIR . '/debug.log',
        WP_CONTENT_DIR . '/uploads/woo-ai-assistant/logs/debug.log',
    ];

    foreach ($debug_files as $log_file) {
        if (file_exists($log_file)) {
            $content = file_get_contents($log_file);
            // Remove only our plugin's debug entries
            $cleaned_content = preg_replace('/.*Woo AI Assistant:.*\n?/', '', $content);
            file_put_contents($log_file, $cleaned_content);
        }
    }

    error_log('Woo AI Assistant: Development cleanup completed');
}

// Execute the uninstall process
woo_ai_assistant_uninstall();