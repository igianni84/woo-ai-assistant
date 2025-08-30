<?php

/**
 * Activator Class
 *
 * Handles plugin activation logic including initial setup,
 * database table creation, and default configuration.
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
 * Class Activator
 *
 * @since 1.0.0
 */
class Activator
{
    /**
     * Plugin activation handler
     *
     * Performs all necessary setup tasks when the plugin is activated.
     * This includes checking requirements, creating database tables,
     * setting default options, and scheduling cron jobs.
     *
     * @return void
     */
    public static function activate(): void
    {
        // Log activation start
        Logger::info('Woo AI Assistant activation started', [
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'timestamp' => current_time('mysql')
        ]);

        try {
            // Check system requirements
            self::checkRequirements();

            // Set plugin activation timestamp
            self::setActivationTimestamp();

            // Create database tables (placeholder for future task)
            self::createDatabaseTables();

            // Set default options
            self::setDefaultOptions();

            // Schedule cron jobs
            self::scheduleCronJobs();

            // Flush rewrite rules
            self::flushRewriteRules();

            // Clear caches
            self::clearCaches();

            // Set activation flag
            self::setActivationFlag();

            Logger::info('Woo AI Assistant activation completed successfully');
        } catch (\Exception $e) {
            Logger::error('Plugin activation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Clean up partial activation
            self::cleanupFailedActivation();

            // Re-throw the exception to prevent activation
            throw $e;
        }
    }

    /**
     * Check system requirements
     *
     * @throws \Exception If requirements are not met
     * @return void
     */
    private static function checkRequirements(): void
    {
        // Check PHP version
        if (version_compare(PHP_VERSION, '8.1', '<')) {
            throw new \Exception(
                sprintf(
                    __('Woo AI Assistant requires PHP 8.1 or higher. Current version: %s', 'woo-ai-assistant'),
                    PHP_VERSION
                )
            );
        }

        // Check WordPress version
        global $wp_version;
        if (version_compare($wp_version, '6.0', '<')) {
            throw new \Exception(
                sprintf(
                    __('Woo AI Assistant requires WordPress 6.0 or higher. Current version: %s', 'woo-ai-assistant'),
                    $wp_version
                )
            );
        }

        // Check WooCommerce
        if (!Utils::isWooCommerceActive()) {
            throw new \Exception(
                __('Woo AI Assistant requires WooCommerce to be installed and activated.', 'woo-ai-assistant')
            );
        }

        // Check required PHP extensions
        $requiredExtensions = ['json', 'mbstring'];
        foreach ($requiredExtensions as $extension) {
            if (!extension_loaded($extension)) {
                throw new \Exception(
                    sprintf(
                        __('Woo AI Assistant requires the %s PHP extension to be installed.', 'woo-ai-assistant'),
                        $extension
                    )
                );
            }
        }

        Logger::debug('System requirements check passed');
    }

    /**
     * Set plugin activation timestamp
     *
     * @return void
     */
    private static function setActivationTimestamp(): void
    {
        $timestamp = current_time('timestamp');
        update_option('woo_ai_assistant_activated_at', $timestamp);

        Logger::debug('Activation timestamp set', ['timestamp' => $timestamp]);
    }

    /**
     * Create database tables
     *
     * This is a placeholder for Task 1.3 where actual database schema will be implemented.
     *
     * @return void
     */
    private static function createDatabaseTables(): void
    {
        // TODO: Implement database table creation in Task 1.3
        // Tables to create:
        // - woo_ai_conversations
        // - woo_ai_messages
        // - woo_ai_knowledge_base
        // - woo_ai_settings
        // - woo_ai_analytics
        // - woo_ai_action_logs

        Logger::debug('Database table creation placeholder - will be implemented in Task 1.3');

        // Set database version for future migrations
        update_option('woo_ai_assistant_db_version', '1.0.0');
    }

    /**
     * Set default plugin options
     *
     * @return void
     */
    private static function setDefaultOptions(): void
    {
        $defaultOptions = [
            'woo_ai_assistant_version' => Utils::getVersion(),
            'woo_ai_assistant_enabled' => true,
            'woo_ai_assistant_first_activation' => true,
            'woo_ai_assistant_widget_enabled' => true,
            'woo_ai_assistant_debug_mode' => Utils::isDevelopmentMode(),
            'woo_ai_assistant_cache_enabled' => true,
            'woo_ai_assistant_max_conversations_per_month' => 100, // Free plan default
            'woo_ai_assistant_auto_index' => true,
            'woo_ai_assistant_proactive_triggers' => false,
        ];

        foreach ($defaultOptions as $option => $value) {
            // Only set if option doesn't exist (don't override existing settings)
            if (get_option($option) === false) {
                update_option($option, $value);
            }
        }

        Logger::debug('Default options set', ['options_count' => count($defaultOptions)]);
    }

    /**
     * Schedule cron jobs
     *
     * @return void
     */
    private static function scheduleCronJobs(): void
    {
        // Schedule knowledge base indexing (daily)
        if (!wp_next_scheduled('woo_ai_assistant_daily_index')) {
            wp_schedule_event(time(), 'daily', 'woo_ai_assistant_daily_index');
        }

        // Schedule analytics cleanup (weekly)
        if (!wp_next_scheduled('woo_ai_assistant_cleanup_analytics')) {
            wp_schedule_event(time(), 'weekly', 'woo_ai_assistant_cleanup_analytics');
        }

        // Schedule cache cleanup (daily)
        if (!wp_next_scheduled('woo_ai_assistant_cleanup_cache')) {
            wp_schedule_event(time(), 'daily', 'woo_ai_assistant_cleanup_cache');
        }

        Logger::debug('Cron jobs scheduled');
    }

    /**
     * Flush WordPress rewrite rules
     *
     * @return void
     */
    private static function flushRewriteRules(): void
    {
        // Flush rewrite rules to ensure any custom endpoints work
        flush_rewrite_rules();

        Logger::debug('Rewrite rules flushed');
    }

    /**
     * Clear all caches
     *
     * @return void
     */
    private static function clearCaches(): void
    {
        // Clear plugin caches
        Cache::flushAll();

        // Clear WordPress object cache if available
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        // Clear opcache if available
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        Logger::debug('Caches cleared');
    }

    /**
     * Set activation completion flag
     *
     * @return void
     */
    private static function setActivationFlag(): void
    {
        update_option('woo_ai_assistant_activation_complete', true);

        // Trigger action for other components
        do_action('woo_ai_assistant_activated');

        Logger::debug('Activation flag set');
    }

    /**
     * Cleanup failed activation
     *
     * Removes any partially created data if activation fails.
     *
     * @return void
     */
    private static function cleanupFailedActivation(): void
    {
        Logger::warning('Cleaning up failed activation');

        // Remove activation timestamp
        delete_option('woo_ai_assistant_activated_at');

        // Remove activation flag
        delete_option('woo_ai_assistant_activation_complete');

        // Clear scheduled cron jobs
        wp_clear_scheduled_hook('woo_ai_assistant_daily_index');
        wp_clear_scheduled_hook('woo_ai_assistant_cleanup_analytics');
        wp_clear_scheduled_hook('woo_ai_assistant_cleanup_cache');

        // Clear caches
        Cache::flushAll();

        Logger::info('Failed activation cleanup completed');
    }

    /**
     * Check if plugin is activated
     *
     * @return bool True if plugin is properly activated
     */
    public static function isActivated(): bool
    {
        return (bool) get_option('woo_ai_assistant_activation_complete', false);
    }

    /**
     * Get activation timestamp
     *
     * @return int|false Activation timestamp or false if not set
     */
    public static function getActivationTimestamp(): int|false
    {
        return get_option('woo_ai_assistant_activated_at', false);
    }

    /**
     * Check if this is first activation
     *
     * @return bool True if this is first activation
     */
    public static function isFirstActivation(): bool
    {
        return (bool) get_option('woo_ai_assistant_first_activation', false);
    }

    /**
     * Mark first activation as complete
     *
     * @return void
     */
    public static function completeFirstActivation(): void
    {
        update_option('woo_ai_assistant_first_activation', false);
        Logger::debug('First activation marked as complete');
    }
}
