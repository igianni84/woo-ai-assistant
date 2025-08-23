<?php
/**
 * Plugin Deactivator Class
 *
 * Handles plugin deactivation tasks including cleanup of temporary data,
 * scheduled events, and user sessions.
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
 * Class Deactivator
 * 
 * Handles all tasks that need to be performed when the plugin is deactivated.
 * This includes cleanup of temporary data, clearing caches, and stopping
 * scheduled events.
 * 
 * @since 1.0.0
 */
class Deactivator {

    /**
     * Plugin deactivation handler
     *
     * Performs all necessary cleanup tasks when the plugin is deactivated.
     * This method is called by the deactivation hook in the main plugin file.
     *
     * @since 1.0.0
     * @return void
     */
    public static function deactivate(): void {
        try {
            Utils::logDebug('Starting plugin deactivation process');

            // Clear scheduled cron jobs
            self::clearScheduledEvents();

            // Clear temporary data and cache
            self::clearTemporaryData();

            // Update active conversations status
            self::updateActiveConversations();

            // Clean up transients
            self::cleanupTransients();

            // Clear rewrite rules
            flush_rewrite_rules();

            // Set deactivation timestamp
            update_option('woo_ai_assistant_deactivated_at', time());

            Utils::logDebug('Plugin deactivation completed successfully');

        } catch (\Exception $e) {
            Utils::logDebug('Plugin deactivation error: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * Clear all scheduled cron events
     *
     * @since 1.0.0
     * @return void
     */
    private static function clearScheduledEvents(): void {
        // List of all cron hooks used by the plugin
        $cron_hooks = [
            'woo_ai_assistant_daily_cleanup',
            'woo_ai_assistant_weekly_stats',
            'woo_ai_assistant_kb_reindex',
            'woo_ai_assistant_usage_reset',
            'woo_ai_assistant_health_check',
        ];

        foreach ($cron_hooks as $hook) {
            // Get all scheduled events for this hook
            $scheduled_events = wp_get_scheduled_event($hook);
            
            if ($scheduled_events) {
                // Clear all instances of this scheduled event
                wp_clear_scheduled_hook($hook);
                Utils::logDebug("Cleared scheduled event: {$hook}");
            }
        }

        Utils::logDebug('All scheduled events cleared');
    }

    /**
     * Clear temporary data and cache
     *
     * @since 1.0.0
     * @return void
     */
    private static function clearTemporaryData(): void {
        // Clear WordPress object cache
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        // Clear plugin-specific cache directory
        $upload_dir = wp_upload_dir();
        $cache_dir = $upload_dir['basedir'] . '/woo-ai-assistant/cache';
        
        if (is_dir($cache_dir)) {
            self::recursiveDelete($cache_dir, false); // Delete contents but keep directory
        }

        // Clear temporary embeddings and processing files
        $temp_dir = $upload_dir['basedir'] . '/woo-ai-assistant/temp';
        
        if (is_dir($temp_dir)) {
            self::recursiveDelete($temp_dir, false);
        }

        Utils::logDebug('Temporary data and cache cleared');
    }

    /**
     * Update status of active conversations
     *
     * @since 1.0.0
     * @return void
     */
    private static function updateActiveConversations(): void {
        global $wpdb;

        $table_conversations = $wpdb->prefix . 'woo_ai_conversations';

        // Update all active conversations to paused status
        $updated = $wpdb->update(
            $table_conversations,
            [
                'status' => 'paused',
                'updated_at' => current_time('mysql'),
            ],
            [
                'status' => 'active',
            ],
            ['%s', '%s'],
            ['%s']
        );

        if (false !== $updated) {
            Utils::logDebug("Updated {$updated} active conversations to paused status");
        }
    }

    /**
     * Clean up plugin-specific transients
     *
     * @since 1.0.0
     * @return void
     */
    private static function cleanupTransients(): void {
        global $wpdb;

        // Delete all transients that start with our plugin prefix
        $plugin_transients = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} 
                WHERE option_name LIKE %s 
                OR option_name LIKE %s",
                '_transient_woo_ai_%',
                '_transient_timeout_woo_ai_%'
            )
        );

        $deleted_count = 0;
        foreach ($plugin_transients as $transient) {
            if (delete_option($transient->option_name)) {
                $deleted_count++;
            }
        }

        Utils::logDebug("Cleaned up {$deleted_count} plugin transients");
    }

    /**
     * Recursively delete directory contents
     *
     * @since 1.0.0
     * @param string $dir Directory path
     * @param bool $deleteDir Whether to delete the directory itself
     * @return bool True if successful
     */
    private static function recursiveDelete(string $dir, bool $deleteDir = true): bool {
        if (!is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            
            if (is_dir($path)) {
                self::recursiveDelete($path);
            } else {
                unlink($path);
            }
        }

        return $deleteDir ? rmdir($dir) : true;
    }

    /**
     * Get deactivation timestamp
     *
     * @since 1.0.0
     * @return int|false Deactivation timestamp or false if not found
     */
    public static function getDeactivationTime() {
        return get_option('woo_ai_assistant_deactivated_at', false);
    }

    /**
     * Check if plugin was recently deactivated
     *
     * @since 1.0.0
     * @param int $seconds Seconds to consider as "recent"
     * @return bool True if recently deactivated
     */
    public static function isRecentlyDeactivated(int $seconds = 300): bool {
        $deactivation_time = self::getDeactivationTime();
        
        if (false === $deactivation_time) {
            return false;
        }

        return (time() - $deactivation_time) <= $seconds;
    }

    /**
     * Clean up user sessions
     *
     * @since 1.0.0
     * @return void
     */
    private static function cleanupUserSessions(): void {
        // Remove any active user sessions related to the chatbot
        global $wpdb;

        $table_conversations = $wpdb->prefix . 'woo_ai_conversations';

        // Update conversation metadata to indicate plugin was deactivated
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table_conversations} 
                SET context = JSON_SET(
                    COALESCE(context, '{}'), 
                    '$.plugin_deactivated_at', %s
                )
                WHERE status = 'active'",
                current_time('mysql')
            )
        );

        Utils::logDebug('User sessions cleaned up');
    }

    /**
     * Log deactivation reason (if provided)
     *
     * @since 1.0.0
     * @param string $reason Reason for deactivation
     * @return void
     */
    public static function logDeactivationReason(string $reason = ''): void {
        if (!empty($reason)) {
            update_option('woo_ai_assistant_deactivation_reason', sanitize_text_field($reason));
            Utils::logDebug("Deactivation reason logged: {$reason}");
        }
    }

    /**
     * Prepare for reactivation
     *
     * Performs tasks to ensure smooth reactivation of the plugin.
     *
     * @since 1.0.0
     * @return void
     */
    public static function prepareForReactivation(): void {
        // Set a flag to indicate the plugin is being prepared for reactivation
        update_option('woo_ai_assistant_reactivation_prepared', time());
        
        // Clear any error flags
        delete_option('woo_ai_assistant_activation_error');
        delete_option('woo_ai_assistant_fatal_error');

        Utils::logDebug('Plugin prepared for reactivation');
    }
}