<?php

/**
 * Deactivator Class
 *
 * Handles plugin deactivation logic including cleanup of temporary data,
 * scheduled events, and transients. Does not remove user data or settings.
 *
 * @package WooAiAssistant
 * @subpackage Setup
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Setup;

use WooAiAssistant\Common\Utils;
use WooAiAssistant\Common\Logger;
use WooAiAssistant\Common\Cache;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Deactivator
 *
 * @since 1.0.0
 */
class Deactivator
{
    /**
     * Plugin deactivation handler
     *
     * Performs cleanup tasks when the plugin is deactivated.
     * This includes clearing scheduled events, temporary data,
     * and caches, but preserves user data and settings.
     *
     * @return void
     */
    public static function deactivate(): void
    {
        // Log deactivation start
        Logger::info('Woo AI Assistant deactivation started', [
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'timestamp' => current_time('mysql')
        ]);

        try {
            // Set deactivation timestamp
            self::setDeactivationTimestamp();

            // Clear scheduled cron jobs
            self::clearScheduledEvents();

            // Clear temporary data and caches
            self::clearTemporaryData();

            // Flush rewrite rules
            self::flushRewriteRules();

            // Update deactivation flag
            self::setDeactivationFlag();

            // Trigger deactivation action
            do_action('woo_ai_assistant_deactivated');

            Logger::info('Woo AI Assistant deactivation completed successfully');
        } catch (\Exception $e) {
            Logger::error('Plugin deactivation encountered an error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Continue with deactivation even if there are errors
            // We don't want to prevent deactivation
        }
    }

    /**
     * Set plugin deactivation timestamp
     *
     * @return void
     */
    private static function setDeactivationTimestamp(): void
    {
        $timestamp = current_time('timestamp');
        update_option('woo_ai_assistant_deactivated_at', $timestamp);

        Logger::debug('Deactivation timestamp set', ['timestamp' => $timestamp]);
    }

    /**
     * Clear all scheduled cron events
     *
     * @return void
     */
    private static function clearScheduledEvents(): void
    {
        $cronHooks = [
            'woo_ai_assistant_daily_index',
            'woo_ai_assistant_cleanup_analytics',
            'woo_ai_assistant_cleanup_cache',
        ];

        foreach ($cronHooks as $hook) {
            $timestamp = wp_next_scheduled($hook);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
                Logger::debug("Unscheduled cron event: {$hook}");
            }
        }

        // Clear all occurrences of our hooks
        foreach ($cronHooks as $hook) {
            wp_clear_scheduled_hook($hook);
        }

        Logger::debug('All scheduled events cleared');
    }

    /**
     * Clear temporary data and caches
     *
     * @return void
     */
    private static function clearTemporaryData(): void
    {
        // Clear plugin caches
        Cache::flushAll();

        // Clear transients
        self::clearTransients();

        // Clear any temporary user meta
        self::clearTemporaryUserMeta();

        // Clear temporary options (keep settings)
        self::clearTemporaryOptions();

        Logger::debug('Temporary data cleared');
    }

    /**
     * Clear plugin-related transients
     *
     * @return void
     */
    private static function clearTransients(): void
    {
        global $wpdb;

        // Get all transients that start with our prefix
        $transientPrefix = '_transient_woo_ai_';
        $timeoutPrefix = '_transient_timeout_woo_ai_';

        $transients = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $transientPrefix . '%',
                $timeoutPrefix . '%'
            )
        );

        foreach ($transients as $transient) {
            delete_option($transient);
        }

        if (!empty($transients)) {
            Logger::debug('Plugin transients cleared', [
                'count' => count($transients)
            ]);
        }
    }

    /**
     * Clear temporary user meta data
     *
     * @return void
     */
    private static function clearTemporaryUserMeta(): void
    {
        global $wpdb;

        // Clear temporary user meta (conversation states, etc.)
        $tempMetaKeys = [
            'woo_ai_assistant_current_conversation',
            'woo_ai_assistant_widget_state',
            'woo_ai_assistant_session_data'
        ];

        foreach ($tempMetaKeys as $metaKey) {
            $wpdb->delete(
                $wpdb->usermeta,
                ['meta_key' => $metaKey],
                ['%s']
            );
        }

        Logger::debug('Temporary user meta cleared');
    }

    /**
     * Clear temporary options (keep user settings)
     *
     * @return void
     */
    private static function clearTemporaryOptions(): void
    {
        $temporaryOptions = [
            'woo_ai_assistant_cache_stats',
            'woo_ai_assistant_last_index_run',
            'woo_ai_assistant_api_call_count',
            'woo_ai_assistant_daily_stats',
            'woo_ai_assistant_error_log',
        ];

        foreach ($temporaryOptions as $option) {
            delete_option($option);
        }

        Logger::debug('Temporary options cleared', [
            'count' => count($temporaryOptions)
        ]);
    }

    /**
     * Flush WordPress rewrite rules
     *
     * @return void
     */
    private static function flushRewriteRules(): void
    {
        // Flush rewrite rules to clean up any custom endpoints
        flush_rewrite_rules();

        Logger::debug('Rewrite rules flushed');
    }

    /**
     * Set deactivation completion flag
     *
     * @return void
     */
    private static function setDeactivationFlag(): void
    {
        // Remove activation flag
        delete_option('woo_ai_assistant_activation_complete');

        // Set deactivation flag
        update_option('woo_ai_assistant_deactivation_complete', true);

        Logger::debug('Deactivation flag set');
    }

    /**
     * Force cleanup (for emergency situations)
     *
     * This method performs a more aggressive cleanup and should only
     * be used in emergency situations or during development.
     *
     * @return void
     */
    public static function forceCleanup(): void
    {
        Logger::warning('Force cleanup initiated');

        // Clear all plugin-related options (except core settings)
        global $wpdb;

        $preserveOptions = [
            'woo_ai_assistant_settings',
            'woo_ai_assistant_license_key',
            'woo_ai_assistant_api_keys',
        ];

        $optionsToDelete = $wpdb->get_col(
            "SELECT option_name FROM {$wpdb->options} 
             WHERE option_name LIKE 'woo_ai_assistant_%' 
             AND option_name NOT IN ('" . implode("', '", $preserveOptions) . "')"
        );

        foreach ($optionsToDelete as $option) {
            delete_option($option);
        }

        // Clear all caches aggressively
        Cache::flushAll();

        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        Logger::warning('Force cleanup completed', [
            'options_deleted' => count($optionsToDelete)
        ]);
    }

    /**
     * Check if plugin was properly deactivated
     *
     * @return bool True if plugin was properly deactivated
     */
    public static function wasDeactivated(): bool
    {
        return (bool) get_option('woo_ai_assistant_deactivation_complete', false);
    }

    /**
     * Get deactivation timestamp
     *
     * @return int|false Deactivation timestamp or false if not set
     */
    public static function getDeactivationTimestamp(): int|false
    {
        return get_option('woo_ai_assistant_deactivated_at', false);
    }

    /**
     * Reset deactivation status
     *
     * Used when plugin is reactivated to clear deactivation flags.
     *
     * @return void
     */
    public static function resetDeactivationStatus(): void
    {
        delete_option('woo_ai_assistant_deactivation_complete');
        delete_option('woo_ai_assistant_deactivated_at');

        Logger::debug('Deactivation status reset');
    }

    /**
     * Get cleanup statistics
     *
     * @return array Cleanup statistics
     */
    public static function getCleanupStats(): array
    {
        global $wpdb;

        // Count remaining plugin-related data
        $optionsCount = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'woo_ai_assistant_%'"
        );

        $userMetaCount = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key LIKE 'woo_ai_assistant_%'"
        );

        $transientsCount = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_woo_ai_%' 
             OR option_name LIKE '_transient_timeout_woo_ai_%'"
        );

        return [
            'remaining_options' => (int) $optionsCount,
            'remaining_user_meta' => (int) $userMetaCount,
            'remaining_transients' => (int) $transientsCount,
            'deactivated_at' => self::getDeactivationTimestamp(),
            'was_properly_deactivated' => self::wasDeactivated(),
        ];
    }
}
