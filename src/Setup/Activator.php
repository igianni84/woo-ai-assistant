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
use WooAiAssistant\Database\Migrations;
use WooAiAssistant\Database\Schema;

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

            // Detect if this is an upgrade
            $isUpgrade = self::detectUpgrade();

            // Set plugin activation timestamp
            self::setActivationTimestamp();

            // Run database migrations
            self::runDatabaseMigrations();

            // Set default options
            self::setDefaultOptions();

            // Run installer for initial data population
            if (!$isUpgrade) {
                self::runInitialInstallation();
            } else {
                self::runUpgrade();
            }

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
     * Run database migrations
     *
     * Executes all pending database migrations using the migration system.
     * Creates the 6 core tables and any associated views or indexes.
     *
     * @throws \Exception If migration fails
     * @return void
     */
    private static function runDatabaseMigrations(): void
    {
        Logger::info('Starting database migrations during plugin activation');

        try {
            // Initialize migrations handler
            $migrations = Migrations::getInstance();

            // Run all pending migrations
            $result = $migrations->runMigrations([
                'backup' => false, // No backup needed during initial activation
                'force' => false   // Don't force re-application of existing migrations
            ]);

            if (!$result['success']) {
                $errors = implode(', ', $result['errors']);
                throw new \Exception("Database migration failed: {$errors}");
            }

            $appliedCount = count($result['applied_migrations']);
            Logger::info("Database migrations completed successfully", [
                'applied_migrations' => $appliedCount,
                'migrations' => $result['applied_migrations']
            ]);

            // Validate schema after migration
            $schema = Schema::getInstance();
            $validation = $schema->validateSchema();

            if (!$validation['valid']) {
                $errors = implode(', ', $validation['errors']);
                Logger::warning("Database schema validation warnings after migration", [
                    'errors' => $validation['errors'],
                    'warnings' => $validation['warnings']
                ]);

                // Don't throw exception for warnings, but log them
                if (!empty($validation['errors'])) {
                    throw new \Exception("Database schema validation failed: {$errors}");
                }
            }

            Logger::info('Database schema validation passed');

            // Set database version for tracking
            update_option('woo_ai_assistant_db_version', '1.0.0');
            update_option('woo_ai_assistant_schema_validated_at', current_time('mysql'));
        } catch (\Exception $e) {
            Logger::error('Database migration failed during activation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Clean up any partially created tables
            self::cleanupFailedMigration();

            throw $e;
        }
    }

    /**
     * Clean up failed migration
     *
     * Removes any partially created database structures if migration fails.
     *
     * @return void
     */
    private static function cleanupFailedMigration(): void
    {
        Logger::warning('Cleaning up failed database migration');

        try {
            $migrations = Migrations::getInstance();

            // Get migration status to see what was applied
            $status = $migrations->getStatus();

            // If any migrations were applied during this activation, we could rollback
            // For now, we'll just log the issue and let manual cleanup handle it
            Logger::info('Migration status during cleanup', [
                'current_version' => $status['current_version'],
                'applied_migrations' => $status['applied_migrations']
            ]);
        } catch (\Exception $e) {
            Logger::error('Error during migration cleanup', [
                'error' => $e->getMessage()
            ]);
        }

        // Remove database version option
        delete_option('woo_ai_assistant_db_version');
        delete_option('woo_ai_assistant_schema_validated_at');
    }

    /**
     * Set default plugin options
     *
     * @return void
     */
    private static function setDefaultOptions(): void
    {
        Logger::info('Setting default plugin options');

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
            'woo_ai_assistant_ai_model' => 'gemini-2.0-flash-exp', // Default AI model
            'woo_ai_assistant_ai_temperature' => 0.7,
            'woo_ai_assistant_ai_max_tokens' => 1000,
            'woo_ai_assistant_widget_theme' => 'modern',
            'woo_ai_assistant_widget_position' => 'bottom-right',
            'woo_ai_assistant_enable_analytics' => true,
            'woo_ai_assistant_kb_sync_interval' => 24, // hours
            'woo_ai_assistant_conversation_timeout' => 30, // minutes
            'woo_ai_assistant_max_message_length' => 2000,
        ];

        $newOptions = 0;
        foreach ($defaultOptions as $option => $value) {
            // Only set if option doesn't exist (don't override existing settings)
            if (get_option($option) === false) {
                update_option($option, $value);
                $newOptions++;
            }
        }

        Logger::debug('Default options processed', [
            'total_options' => count($defaultOptions),
            'new_options' => $newOptions,
            'existing_options' => count($defaultOptions) - $newOptions
        ]);

        // Initialize license and plan settings for development mode
        if (Utils::isDevelopmentMode()) {
            self::initializeDevelopmentSettings();
        }

        // Trigger hook for other components to set their defaults
        do_action('woo_ai_assistant_default_options_set');
    }

    /**
     * Initialize development-specific settings
     *
     * @return void
     */
    private static function initializeDevelopmentSettings(): void
    {
        Logger::info('Initializing development settings');

        $devOptions = [
            'woo_ai_assistant_license_key' => 'dev-license-' . wp_generate_uuid4(),
            'woo_ai_assistant_license_status' => 'valid',
            'woo_ai_assistant_license_plan' => 'unlimited',
            'woo_ai_assistant_development_mode' => true,
            'woo_ai_assistant_use_dummy_data' => false,
            'woo_ai_assistant_enhanced_logging' => true,
        ];

        foreach ($devOptions as $option => $value) {
            if (get_option($option) === false) {
                update_option($option, $value);
            }
        }

        Logger::debug('Development settings initialized');
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

        // Clean up failed migration first
        self::cleanupFailedMigration();

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

    /**
     * Detect if this is a plugin upgrade
     *
     * @return bool True if this is an upgrade
     */
    private static function detectUpgrade(): bool
    {
        $installedVersion = get_option('woo_ai_assistant_version', null);
        $currentVersion = Utils::getVersion();

        if ($installedVersion === null) {
            // Fresh installation
            Logger::info('Fresh plugin installation detected');
            return false;
        }

        if (version_compare($installedVersion, $currentVersion, '<')) {
            // Upgrade detected
            Logger::info('Plugin upgrade detected', [
                'from_version' => $installedVersion,
                'to_version' => $currentVersion
            ]);
            return true;
        }

        // Same version or downgrade (treat as re-activation)
        Logger::info('Plugin re-activation detected', [
            'current_version' => $currentVersion,
            'installed_version' => $installedVersion
        ]);
        return false;
    }

    /**
     * Run initial installation tasks
     *
     * Sets up the plugin for first-time use with zero configuration required.
     *
     * @throws \Exception If installation fails
     * @return void
     */
    private static function runInitialInstallation(): void
    {
        Logger::info('Running initial installation tasks');

        try {
            // Import installer class
            $installerClass = '\\WooAiAssistant\\Setup\\Installer';

            if (!class_exists($installerClass)) {
                throw new \Exception('Installer class not found: ' . $installerClass);
            }

            // Run installation
            $installer = new $installerClass();
            $result = $installer->install();

            if (!$result['success']) {
                throw new \Exception('Installation failed: ' . implode(', ', $result['errors']));
            }

            Logger::info('Initial installation completed successfully', [
                'installed_components' => $result['installed'] ?? []
            ]);

            // Mark as first-time setup
            update_option('woo_ai_assistant_first_time_setup', true);
        } catch (\Exception $e) {
            Logger::error('Initial installation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Run upgrade tasks
     *
     * Handles version-specific upgrade logic and data migration.
     *
     * @throws \Exception If upgrade fails
     * @return void
     */
    private static function runUpgrade(): void
    {
        $fromVersion = get_option('woo_ai_assistant_version', '0.0.0');
        $toVersion = Utils::getVersion();

        Logger::info('Running upgrade tasks', [
            'from_version' => $fromVersion,
            'to_version' => $toVersion
        ]);

        try {
            // Version-specific upgrade logic
            if (version_compare($fromVersion, '1.0.0', '<')) {
                self::upgradeToV100();
            }

            // Update version number
            update_option('woo_ai_assistant_version', $toVersion);
            update_option('woo_ai_assistant_last_upgrade', current_time('mysql'));

            Logger::info('Upgrade completed successfully', [
                'upgraded_to' => $toVersion
            ]);

            // Clear first-time setup flag if set
            delete_option('woo_ai_assistant_first_time_setup');

            // Trigger upgrade hook
            do_action('woo_ai_assistant_upgraded', $fromVersion, $toVersion);
        } catch (\Exception $e) {
            Logger::error('Upgrade failed', [
                'from_version' => $fromVersion,
                'to_version' => $toVersion,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Upgrade to version 1.0.0
     *
     * @return void
     */
    private static function upgradeToV100(): void
    {
        Logger::info('Running v1.0.0 upgrade tasks');

        // Clear any legacy cache
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        // Update any legacy options
        $legacyOptions = [
            'woo_ai_max_conversations' => 'woo_ai_assistant_max_conversations_per_month',
            'woo_ai_enable_widget' => 'woo_ai_assistant_widget_enabled',
            'woo_ai_enable_debug' => 'woo_ai_assistant_debug_mode',
        ];

        foreach ($legacyOptions as $old => $new) {
            $value = get_option($old, null);
            if ($value !== null && get_option($new, false) === false) {
                update_option($new, $value);
                delete_option($old);
            }
        }

        Logger::info('v1.0.0 upgrade tasks completed');
    }

    /**
     * Get upgrade history
     *
     * @return array Array of upgrade records
     */
    public static function getUpgradeHistory(): array
    {
        return get_option('woo_ai_assistant_upgrade_history', []);
    }

    /**
     * Record upgrade in history
     *
     * @param string $fromVersion Version upgraded from
     * @param string $toVersion Version upgraded to
     * @return void
     */
    public static function recordUpgrade(string $fromVersion, string $toVersion): void
    {
        $history = self::getUpgradeHistory();

        $history[] = [
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
            'upgraded_at' => current_time('mysql'),
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION
        ];

        // Keep only last 10 upgrade records
        if (count($history) > 10) {
            $history = array_slice($history, -10);
        }

        update_option('woo_ai_assistant_upgrade_history', $history);
    }
}
