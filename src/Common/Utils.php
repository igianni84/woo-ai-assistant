<?php

/**
 * Utils Class
 *
 * Helper functions and utilities used throughout the plugin.
 * Provides common functionality for string manipulation, validation,
 * and WordPress integration.
 *
 * @package WooAiAssistant
 * @subpackage Common
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Common;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Utils
 *
 * @since 1.0.0
 */
class Utils
{
    /**
     * Check if WooCommerce is active and available
     *
     * @return bool True if WooCommerce is active, false otherwise
     */
    public static function isWooCommerceActive(): bool
    {
        return class_exists('WooCommerce');
    }

    /**
     * Check if we're in development mode
     *
     * @return bool True if in development mode, false otherwise
     */
    public static function isDevelopmentMode(): bool
    {
        if (defined('WOO_AI_DEVELOPMENT_MODE')) {
            return (bool) WOO_AI_DEVELOPMENT_MODE;
        }

        // Auto-detect development environment
        $devIndicators = [
            'localhost',
            '127.0.0.1',
            '.local',
            '.dev',
            '.test'
        ];

        $serverName = $_SERVER['SERVER_NAME'] ?? '';
        foreach ($devIndicators as $indicator) {
            if (strpos($serverName, $indicator) !== false) {
                return true;
            }
        }

        return defined('WP_DEBUG') && WP_DEBUG;
    }

    /**
     * Get plugin version
     *
     * @return string Plugin version
     */
    public static function getVersion(): string
    {
        return defined('WOO_AI_ASSISTANT_VERSION') ? WOO_AI_ASSISTANT_VERSION : '1.0.0';
    }

    /**
     * Get plugin path
     *
     * @param string $path Optional path to append
     * @return string Full path to plugin directory or specific file
     */
    public static function getPluginPath(string $path = ''): string
    {
        $pluginPath = defined('WOO_AI_ASSISTANT_PATH') ? WOO_AI_ASSISTANT_PATH : plugin_dir_path(__FILE__);
        return $path ? $pluginPath . ltrim($path, '/') : $pluginPath;
    }

    /**
     * Get plugin URL
     *
     * @param string $path Optional path to append
     * @return string Full URL to plugin directory or specific file
     */
    public static function getPluginUrl(string $path = ''): string
    {
        $pluginUrl = defined('WOO_AI_ASSISTANT_URL') ? WOO_AI_ASSISTANT_URL : plugin_dir_url(__FILE__);
        return $path ? $pluginUrl . ltrim($path, '/') : $pluginUrl;
    }

    /**
     * Sanitize and validate email address
     *
     * @param string $email Email to validate
     * @return string|false Sanitized email or false if invalid
     */
    public static function sanitizeEmail(string $email): string|false
    {
        $email = sanitize_email($email);
        return is_email($email) ? $email : false;
    }

    /**
     * Generate a secure nonce for AJAX requests
     *
     * @param string $action Action name for nonce
     * @return string Generated nonce
     */
    public static function generateNonce(string $action): string
    {
        return wp_create_nonce('woo_ai_assistant_' . $action);
    }

    /**
     * Verify nonce for security
     *
     * @param string $nonce Nonce to verify
     * @param string $action Action name used to create nonce
     * @return bool True if nonce is valid, false otherwise
     */
    public static function verifyNonce(string $nonce, string $action): bool
    {
        return wp_verify_nonce($nonce, 'woo_ai_assistant_' . $action);
    }

    /**
     * Get current user ID with fallback
     *
     * @return int User ID or 0 if not logged in
     */
    public static function getCurrentUserId(): int
    {
        return get_current_user_id();
    }

    /**
     * Check if current user has required capability
     *
     * @param string $capability Capability to check
     * @return bool True if user has capability, false otherwise
     */
    public static function currentUserCan(string $capability): bool
    {
        return current_user_can($capability);
    }

    /**
     * Format bytes to human readable format
     *
     * @param int $bytes Number of bytes
     * @param int $precision Decimal precision
     * @return string Formatted size string
     */
    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Clean and truncate text to specified length
     *
     * @param string $text Text to clean and truncate
     * @param int $length Maximum length
     * @param string $suffix Suffix to append if truncated
     * @return string Cleaned and truncated text
     */
    public static function cleanText(string $text, int $length = 0, string $suffix = '...'): string
    {
        // Remove HTML tags and decode entities
        $text = wp_strip_all_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        // Remove extra whitespace
        $text = preg_replace('/\s+/', ' ', trim($text));

        // Truncate if length specified
        if ($length > 0 && strlen($text) > $length) {
            $text = substr($text, 0, $length - strlen($suffix)) . $suffix;
        }

        return $text;
    }

    /**
     * Check if string is JSON
     *
     * @param string $string String to check
     * @return bool True if valid JSON, false otherwise
     */
    public static function isJson(string $string): bool
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Generate unique identifier
     *
     * @param string $prefix Optional prefix for the ID
     * @return string Unique identifier
     */
    public static function generateUniqueId(string $prefix = ''): string
    {
        $uniqueId = uniqid('', true);
        return $prefix ? $prefix . '_' . $uniqueId : $uniqueId;
    }

    /**
     * Get WooCommerce version
     *
     * @return string WooCommerce version or '0.0.0' if not available
     * @since 1.0.0
     */
    public static function getWooCommerceVersion(): string
    {
        if (!self::isWooCommerceActive()) {
            return '0.0.0';
        }

        if (defined('WC_VERSION')) {
            return WC_VERSION;
        }

        // Fallback: try to get from WooCommerce class
        if (class_exists('WooCommerce') && isset(WC()->version)) {
            return WC()->version;
        }

        return '0.0.0';
    }

    /**
     * Get WordPress timezone string
     *
     * @return string Timezone string
     */
    public static function getTimezone(): string
    {
        $timezone = get_option('timezone_string');

        if (empty($timezone)) {
            $offset = get_option('gmt_offset');
            $timezone = timezone_name_from_abbr('', $offset * 3600, 0);

            if (false === $timezone) {
                $timezone = 'UTC';
            }
        }

        return $timezone;
    }

    /**
     * Log debug information if debug mode is enabled
     *
     * @param mixed $data Data to log
     * @param string $context Optional context for the log entry
     * @return void
     */
    public static function debugLog($data, string $context = ''): void
    {
        if (defined('WOO_AI_ASSISTANT_DEBUG') && WOO_AI_ASSISTANT_DEBUG) {
            $message = '[Woo AI Assistant]';

            if ($context) {
                $message .= ' [' . $context . ']';
            }

            if (is_string($data)) {
                $message .= ' ' . $data;
            } else {
                $message .= ' ' . print_r($data, true);
            }

            error_log($message);
        }
    }

    /**
     * Get user IP address with proxy support
     *
     * @return string User IP address
     */
    public static function getUserIp(): string
    {
        $ipKeys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) && !empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);

                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Get user agent string
     *
     * @return string User agent
     */
    public static function getUserAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    }

    /**
     * Ensure WooCommerce cart is initialized
     *
     * @return bool True if cart is available, false otherwise
     */
    public static function ensureWooCommerceCart(): bool
    {
        if (!self::isWooCommerceActive()) {
            return false;
        }

        // Initialize WooCommerce if not already done
        if (!did_action('woocommerce_init')) {
            WC();
        }

        // Initialize cart if not already done
        if (is_null(WC()->cart)) {
            wc_load_cart();
        }

        return !is_null(WC()->cart);
    }

    /**
     * Check if cart functionality is available for current request
     *
     * @return bool True if cart can be used, false otherwise
     */
    public static function canUseCart(): bool
    {
        if (!self::isWooCommerceActive()) {
            return false;
        }

        // Don't use cart in admin (unless it's AJAX)
        if (is_admin() && !wp_doing_ajax()) {
            return false;
        }

        // Ensure WooCommerce is initialized
        return self::ensureWooCommerceCart();
    }
}
