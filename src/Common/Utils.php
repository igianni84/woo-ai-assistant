<?php

/**
 * Utility Helper Class
 *
 * Provides common utility functions used throughout the plugin.
 * Contains helper methods for WordPress operations, data validation,
 * formatting, and other common tasks.
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
 * Provides static utility methods for common operations throughout the plugin.
 *
 * @since 1.0.0
 */
class Utils
{
    /**
     * Get plugin version
     *
     * @since 1.0.0
     * @return string The plugin version
     */
    public static function getPluginVersion(): string
    {
        return WOO_AI_ASSISTANT_VERSION;
    }

    /**
     * Get plugin path
     *
     * @since 1.0.0
     * @param string $path Optional. Additional path to append
     * @return string The plugin path
     */
    public static function getPluginPath(string $path = ''): string
    {
        return WOO_AI_ASSISTANT_PLUGIN_DIR_PATH . ltrim($path, '/');
    }

    /**
     * Get plugin URL
     *
     * @since 1.0.0
     * @param string $path Optional. Additional path to append
     * @return string The plugin URL
     */
    public static function getPluginUrl(string $path = ''): string
    {
        return WOO_AI_ASSISTANT_PLUGIN_DIR_URL . ltrim($path, '/');
    }

    /**
     * Get assets URL
     *
     * @since 1.0.0
     * @param string $path Optional. Additional path to append
     * @return string The assets URL
     */
    public static function getAssetsUrl(string $path = ''): string
    {
        return WOO_AI_ASSISTANT_ASSETS_URL . ltrim($path, '/');
    }

    /**
     * Check if debug mode is enabled
     *
     * @since 1.0.0
     * @return bool True if debug mode is enabled
     */
    public static function isDebugMode(): bool
    {
        return defined('WOO_AI_ASSISTANT_DEBUG') && WOO_AI_ASSISTANT_DEBUG;
    }

    /**
     * Log debug message
     *
     * Only logs when debug mode is enabled.
     *
     * @since 1.0.0
     * @param string $message The message to log
     * @param string|array $context Context data (string for level, array for context data)
     * @param string $level The log level when $context is an array (info, warning, error)
     * @return void
     */
    public static function logDebug(string $message, $context = 'info', string $level = 'info'): void
    {
        if (self::isDebugMode()) {
            // Handle backward compatibility: if context is string, treat as level
            if (is_string($context)) {
                $logLevel = $context;
                $contextData = null;
            } else {
                // Context is array, use provided level
                $logLevel = $level;
                $contextData = $context;
            }

            $prefix = strtoupper($logLevel);
            $logMessage = "Woo AI Assistant [{$prefix}]: {$message}";

            // Add context data if provided
            if (is_array($contextData) && !empty($contextData)) {
                $logMessage .= ' | Context: ' . json_encode($contextData);
            }

            error_log($logMessage);
        }
    }

    /**
     * Log error message
     *
     * Logs error messages regardless of debug mode.
     *
     * @since 1.0.0
     * @param string $message The error message to log
     * @param array $context Optional. Context data for the error
     * @return void
     */
    public static function logError(string $message, array $context = []): void
    {
        $logMessage = "Woo AI Assistant [ERROR]: {$message}";

        // Add context data if provided
        if (!empty($context)) {
            $logMessage .= ' | Context: ' . json_encode($context);
        }

        error_log($logMessage);
    }

    /**
     * Sanitize text field
     *
     * Wrapper around WordPress sanitize_text_field with additional validation.
     *
     * @since 1.0.0
     * @param mixed $value The value to sanitize
     * @param int $maxLength Optional. Maximum allowed length
     * @return string Sanitized text
     */
    public static function sanitizeTextField($value, int $maxLength = 255): string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return '';
        }

        $sanitized = sanitize_text_field((string) $value);

        if ($maxLength > 0 && strlen($sanitized) > $maxLength) {
            $sanitized = substr($sanitized, 0, $maxLength);
        }

        return $sanitized;
    }

    /**
     * Sanitize textarea field
     *
     * Wrapper around WordPress sanitize_textarea_field with additional validation.
     *
     * @since 1.0.0
     * @param mixed $value The value to sanitize
     * @param int $maxLength Optional. Maximum allowed length
     * @return string Sanitized textarea content
     */
    public static function sanitizeTextareaField($value, int $maxLength = 5000): string
    {
        if (!is_string($value)) {
            return '';
        }

        $sanitized = sanitize_textarea_field($value);

        if ($maxLength > 0 && strlen($sanitized) > $maxLength) {
            $sanitized = substr($sanitized, 0, $maxLength);
        }

        return $sanitized;
    }

    /**
     * Validate email address
     *
     * @since 1.0.0
     * @param mixed $email The email to validate
     * @return string|false Sanitized email or false if invalid
     */
    public static function validateEmail($email)
    {
        if (!is_string($email)) {
            return false;
        }

        $sanitized = sanitize_email($email);

        return is_email($sanitized) ? $sanitized : false;
    }

    /**
     * Validate positive integer
     *
     * @since 1.0.0
     * @param mixed $value The value to validate
     * @param int $min Optional. Minimum allowed value
     * @param int $max Optional. Maximum allowed value
     * @return int|false Valid integer or false if invalid
     */
    public static function validatePositiveInt($value, int $min = 1, int $max = PHP_INT_MAX)
    {
        if (!is_numeric($value)) {
            return false;
        }

        $int = (int) $value;

        if ($int < $min || $int > $max) {
            return false;
        }

        return $int;
    }

    /**
     * Check if WooCommerce is active and available
     *
     * @since 1.0.0
     * @return bool True if WooCommerce is available
     */
    public static function isWooCommerceActive(): bool
    {
        return class_exists('WooCommerce') && function_exists('WC');
    }

    /**
     * Get current user capabilities
     *
     * @since 1.0.0
     * @return array Array of user capabilities
     */
    public static function getCurrentUserCapabilities(): array
    {
        $user = wp_get_current_user();

        if (!$user->exists()) {
            return [];
        }

        return array_keys($user->allcaps);
    }

    /**
     * Check if current user has required capability
     *
     * @since 1.0.0
     * @param string $capability The capability to check
     * @return bool True if user has capability
     */
    public static function currentUserCan(string $capability): bool
    {
        return current_user_can($capability);
    }

    /**
     * Generate nonce for action
     *
     * @since 1.0.0
     * @param string $action The action name
     * @return string Generated nonce
     */
    public static function createNonce(string $action): string
    {
        return wp_create_nonce($action);
    }

    /**
     * Verify nonce for action
     *
     * @since 1.0.0
     * @param string $nonce The nonce to verify
     * @param string $action The action name
     * @return bool True if nonce is valid
     */
    public static function verifyNonce(string $nonce, string $action): bool
    {
        return wp_verify_nonce($nonce, $action) !== false;
    }

    /**
     * Get formatted date string
     *
     * @since 1.0.0
     * @param string|int|null $date Optional. Date to format (timestamp or string)
     * @param string $format Optional. Date format (default WordPress format)
     * @return string Formatted date
     */
    public static function formatDate($date = null, string $format = ''): string
    {
        if (null === $date) {
            $date = time();
        }

        if (is_string($date)) {
            $date = strtotime($date);
        }

        if (false === $date) {
            return '';
        }

        if (empty($format)) {
            $format = get_option('date_format') . ' ' . get_option('time_format');
        }

        return wp_date($format, $date);
    }

    /**
     * Convert array to JSON with error handling
     *
     * @since 1.0.0
     * @param mixed $data Data to convert to JSON
     * @param int $flags JSON encode flags
     * @return string|false JSON string or false on error
     */
    public static function toJson($data, int $flags = JSON_UNESCAPED_UNICODE)
    {
        $json = json_encode($data, $flags);

        if (JSON_ERROR_NONE !== json_last_error()) {
            self::logDebug('JSON encode error: ' . json_last_error_msg(), 'error');
            return false;
        }

        return $json;
    }

    /**
     * Convert JSON to array with error handling
     *
     * @since 1.0.0
     * @param string $json JSON string to decode
     * @param bool $assoc Return associative array
     * @return mixed|false Decoded data or false on error
     */
    public static function fromJson(string $json, bool $assoc = true)
    {
        $data = json_decode($json, $assoc);

        if (JSON_ERROR_NONE !== json_last_error()) {
            self::logDebug('JSON decode error: ' . json_last_error_msg(), 'error');
            return false;
        }

        return $data;
    }

    /**
     * Get memory usage information
     *
     * @since 1.0.0
     * @return array Memory usage statistics
     */
    public static function getMemoryUsage(): array
    {
        return [
            'current' => memory_get_usage(true),
            'current_formatted' => self::formatBytes(memory_get_usage(true)),
            'peak' => memory_get_peak_usage(true),
            'peak_formatted' => self::formatBytes(memory_get_peak_usage(true)),
            'limit' => ini_get('memory_limit')
        ];
    }

    /**
     * Format bytes to human readable format
     *
     * @since 1.0.0
     * @param int $bytes Number of bytes
     * @param int $precision Decimal places
     * @return string Formatted size
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
     * Generate unique ID
     *
     * @since 1.0.0
     * @param string $prefix Optional. Prefix for the ID
     * @return string Unique ID
     */
    public static function generateUniqueId(string $prefix = ''): string
    {
        $id = uniqid($prefix, true);
        return str_replace('.', '_', $id);
    }

    /**
     * Check if string is JSON
     *
     * @since 1.0.0
     * @param string $string String to check
     * @return bool True if string is valid JSON
     */
    public static function isJson(string $string): bool
    {
        json_decode($string);
        return JSON_ERROR_NONE === json_last_error();
    }

    /**
     * Truncate string to specified length
     *
     * @since 1.0.0
     * @param string $string String to truncate
     * @param int $length Maximum length
     * @param string $suffix Suffix to append if truncated
     * @return string Truncated string
     */
    public static function truncateString(string $string, int $length, string $suffix = '...'): string
    {
        if (strlen($string) <= $length) {
            return $string;
        }

        return substr($string, 0, $length - strlen($suffix)) . $suffix;
    }

    /**
     * Get client IP address
     *
     * Attempts to determine the real IP address of the client,
     * accounting for proxy servers and CDNs.
     *
     * @since 1.0.0
     * @return string Client IP address
     */
    public static function getClientIpAddress(): string
    {
        // Check for IP from shared internet
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Check for IP passed from proxy
            // Can contain multiple IPs, get the first one
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            // Check for IP from remote address
            return $_SERVER['REMOTE_ADDR'];
        }

        return '127.0.0.1';
    }
}
