<?php
/**
 * Uninstall Script
 *
 * This file is executed when the plugin is deleted from WordPress admin.
 * It performs complete cleanup of all plugin data including database tables,
 * options, user meta, transients, and uploaded files.
 *
 * @package WooAiAssistant
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

// Exit if accessed directly or if uninstall is not called from WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Additional security check
if (!current_user_can('delete_plugins')) {
    exit;
}

// Define plugin constants for uninstall process
define('WOO_AI_ASSISTANT_UNINSTALLING', true);
define('WOO_AI_ASSISTANT_VERSION', '1.0.0');
define('WOO_AI_ASSISTANT_PATH', plugin_dir_path(__FILE__));

/**
 * Log uninstall process
 *
 * @param string $message Log message
 * @param array $context Optional context data
 */
function woo_ai_assistant_uninstall_log($message, $context = []) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $contextString = '';
        if (!empty($context)) {
            $contextString = ' Context: ' . json_encode($context);
        }
        error_log('[WOO_AI_ASSISTANT_UNINSTALL] ' . $message . $contextString);
    }
}

/**
 * Main uninstall function
 */
function woo_ai_assistant_uninstall() {
    global $wpdb;
    
    woo_ai_assistant_uninstall_log('Starting plugin uninstall process');
    
    try {
        // Check if user confirmed data deletion
        $delete_data = get_option('woo_ai_assistant_delete_data_on_uninstall', true);
        
        if (!$delete_data) {
            woo_ai_assistant_uninstall_log('Data deletion skipped by user preference');
            return;
        }
        
        // Remove database tables
        woo_ai_assistant_remove_database_tables();
        
        // Remove all plugin options
        woo_ai_assistant_remove_options();
        
        // Remove user meta data
        woo_ai_assistant_remove_user_meta();
        
        // Remove transients
        woo_ai_assistant_remove_transients();
        
        // Clear scheduled events
        woo_ai_assistant_clear_scheduled_events();
        
        // Remove uploaded files
        woo_ai_assistant_remove_uploaded_files();
        
        // Clear caches
        woo_ai_assistant_clear_caches();
        
        // Remove custom capabilities
        woo_ai_assistant_remove_capabilities();
        
        // Flush rewrite rules one final time
        flush_rewrite_rules();
        
        woo_ai_assistant_uninstall_log('Plugin uninstall completed successfully');
        
    } catch (Exception $e) {
        woo_ai_assistant_uninstall_log('Uninstall process encountered an error', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        
        // Continue with uninstall even if there are errors
        // We don't want to leave the plugin in a broken state
    }
}

/**
 * Remove plugin database tables
 */
function woo_ai_assistant_remove_database_tables() {
    global $wpdb;
    
    // List of tables to remove (will be implemented in Task 1.3)
    $tables = [
        $wpdb->prefix . 'woo_ai_conversations',
        $wpdb->prefix . 'woo_ai_messages',
        $wpdb->prefix . 'woo_ai_knowledge_base',
        $wpdb->prefix . 'woo_ai_settings',
        $wpdb->prefix . 'woo_ai_analytics',
        $wpdb->prefix . 'woo_ai_action_logs',
    ];
    
    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
        woo_ai_assistant_uninstall_log("Dropped table: {$table}");
    }
    
    // Remove custom database version option
    delete_option('woo_ai_assistant_db_version');
}

/**
 * Remove all plugin options
 */
function woo_ai_assistant_remove_options() {
    global $wpdb;
    
    // Get all plugin options
    $plugin_options = $wpdb->get_col(
        "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'woo_ai_assistant_%'"
    );
    
    foreach ($plugin_options as $option) {
        delete_option($option);
    }
    
    // Also check for any site options (multisite)
    if (is_multisite()) {
        $site_options = $wpdb->get_col(
            "SELECT meta_key FROM {$wpdb->sitemeta} WHERE meta_key LIKE 'woo_ai_assistant_%'"
        );
        
        foreach ($site_options as $option) {
            delete_site_option($option);
        }
    }
    
    woo_ai_assistant_uninstall_log('Plugin options removed', [
        'options_count' => count($plugin_options),
        'site_options_count' => is_multisite() ? count($site_options) : 0
    ]);
}

/**
 * Remove user meta data
 */
function woo_ai_assistant_remove_user_meta() {
    global $wpdb;
    
    $meta_keys = [
        'woo_ai_assistant_preferences',
        'woo_ai_assistant_conversation_history',
        'woo_ai_assistant_current_conversation',
        'woo_ai_assistant_widget_state',
        'woo_ai_assistant_session_data',
        'woo_ai_assistant_usage_stats',
    ];
    
    foreach ($meta_keys as $meta_key) {
        $deleted = $wpdb->delete(
            $wpdb->usermeta,
            ['meta_key' => $meta_key],
            ['%s']
        );
        
        if ($deleted > 0) {
            woo_ai_assistant_uninstall_log("Removed user meta: {$meta_key}", [
                'affected_rows' => $deleted
            ]);
        }
    }
}

/**
 * Remove plugin transients
 */
function woo_ai_assistant_remove_transients() {
    global $wpdb;
    
    // Remove regular transients
    $transients = $wpdb->get_col(
        "SELECT option_name FROM {$wpdb->options} 
         WHERE option_name LIKE '_transient_woo_ai_%' 
         OR option_name LIKE '_transient_timeout_woo_ai_%'"
    );
    
    foreach ($transients as $transient) {
        delete_option($transient);
    }
    
    // Remove site transients (multisite)
    if (is_multisite()) {
        $site_transients = $wpdb->get_col(
            "SELECT meta_key FROM {$wpdb->sitemeta} 
             WHERE meta_key LIKE '_site_transient_woo_ai_%' 
             OR meta_key LIKE '_site_transient_timeout_woo_ai_%'"
        );
        
        foreach ($site_transients as $transient) {
            delete_site_option($transient);
        }
    }
    
    woo_ai_assistant_uninstall_log('Transients removed', [
        'transients_count' => count($transients),
        'site_transients_count' => is_multisite() ? count($site_transients) : 0
    ]);
}

/**
 * Clear scheduled events
 */
function woo_ai_assistant_clear_scheduled_events() {
    $cron_hooks = [
        'woo_ai_assistant_daily_index',
        'woo_ai_assistant_cleanup_analytics',
        'woo_ai_assistant_cleanup_cache',
    ];
    
    foreach ($cron_hooks as $hook) {
        wp_clear_scheduled_hook($hook);
    }
    
    woo_ai_assistant_uninstall_log('Scheduled events cleared');
}

/**
 * Remove uploaded files and directories
 */
function woo_ai_assistant_remove_uploaded_files() {
    $upload_dir = wp_upload_dir();
    $plugin_upload_dir = $upload_dir['basedir'] . '/woo-ai-assistant/';
    
    if (is_dir($plugin_upload_dir)) {
        woo_ai_assistant_remove_directory($plugin_upload_dir);
        woo_ai_assistant_uninstall_log('Uploaded files removed', [
            'directory' => $plugin_upload_dir
        ]);
    }
}

/**
 * Recursively remove directory and all contents
 *
 * @param string $dir Directory path
 * @return bool Success status
 */
function woo_ai_assistant_remove_directory($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), ['.', '..']);
    
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        
        if (is_dir($path)) {
            woo_ai_assistant_remove_directory($path);
        } else {
            unlink($path);
        }
    }
    
    return rmdir($dir);
}

/**
 * Clear all caches
 */
function woo_ai_assistant_clear_caches() {
    // Clear WordPress object cache
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
    
    // Clear opcache if available
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }
    
    woo_ai_assistant_uninstall_log('Caches cleared');
}

/**
 * Remove custom user capabilities
 */
function woo_ai_assistant_remove_capabilities() {
    $capabilities = [
        'woo_ai_assistant_manage',
        'woo_ai_assistant_view_analytics',
        'woo_ai_assistant_export_data',
    ];
    
    $roles = ['administrator', 'shop_manager', 'editor'];
    
    foreach ($roles as $role_name) {
        $role = get_role($role_name);
        
        if ($role) {
            foreach ($capabilities as $capability) {
                $role->remove_cap($capability);
            }
        }
    }
    
    woo_ai_assistant_uninstall_log('Custom capabilities removed');
}

/**
 * Check if we should preserve data
 *
 * @return bool True if data should be preserved
 */
function woo_ai_assistant_should_preserve_data() {
    // Check if user has explicitly chosen to preserve data
    $preserve_data = get_option('woo_ai_assistant_preserve_data_on_uninstall', false);
    
    if ($preserve_data) {
        woo_ai_assistant_uninstall_log('Data preservation requested by user');
        return true;
    }
    
    // Check if there are multiple sites using the plugin (multisite)
    if (is_multisite()) {
        global $wpdb;
        
        $active_sites = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} 
             WHERE option_name = 'active_plugins' 
             AND option_value LIKE '%woo-ai-assistant%'"
        );
        
        if ($active_sites > 1) {
            woo_ai_assistant_uninstall_log('Plugin active on multiple sites, preserving data');
            return true;
        }
    }
    
    return false;
}

// Execute uninstall
woo_ai_assistant_uninstall();

// Final cleanup - remove any remaining traces
unset($GLOBALS['woo_ai_assistant_uninstall_log']);

woo_ai_assistant_uninstall_log('Uninstall script execution completed');