<?php

/**
 * Rate Limiter Class
 *
 * Provides comprehensive rate limiting functionality to prevent abuse and
 * ensure fair usage of plugin resources. Implements IP-based and user-based
 * rate limiting using WordPress transients for storage.
 *
 * @package WooAiAssistant
 * @subpackage Security
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Security;

use WooAiAssistant\Common\Traits\Singleton;
use WooAiAssistant\Common\Utils;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class RateLimiter
 *
 * Advanced rate limiting system that provides flexible rate limiting based on
 * IP addresses, user accounts, and specific actions. Supports different time
 * windows, burst allowances, and graceful degradation.
 *
 * @since 1.0.0
 */
class RateLimiter
{
    use Singleton;

    /**
     * Rate limiting rules configuration
     *
     * @since 1.0.0
     * @var array
     */
    private array $rateLimits = [
        'default' => [
            'requests_per_minute' => 60,
            'requests_per_hour' => 1000,
            'requests_per_day' => 5000,
            'burst_allowance' => 10,
        ],
        'chat_send' => [
            'requests_per_minute' => 30,
            'requests_per_hour' => 500,
            'requests_per_day' => 2000,
            'burst_allowance' => 5,
        ],
        'api_call' => [
            'requests_per_minute' => 20,
            'requests_per_hour' => 300,
            'requests_per_day' => 1000,
            'burst_allowance' => 3,
        ],
        'search' => [
            'requests_per_minute' => 100,
            'requests_per_hour' => 2000,
            'requests_per_day' => 10000,
            'burst_allowance' => 20,
        ],
        'admin' => [
            'requests_per_minute' => 200,
            'requests_per_hour' => 5000,
            'requests_per_day' => 20000,
            'burst_allowance' => 50,
        ],
    ];

    /**
     * Rate limiting storage keys prefix
     *
     * @since 1.0.0
     * @var string
     */
    private string $keyPrefix = 'woo_ai_rate_limit_';

    /**
     * Time windows for rate limiting (in seconds)
     *
     * @since 1.0.0
     * @var array
     */
    private array $timeWindows = [
        'minute' => 60,
        'hour' => 3600,
        'day' => 86400,
    ];

    /**
     * Rate limiting statistics
     *
     * @since 1.0.0
     * @var array
     */
    private array $statistics = [
        'total_requests' => 0,
        'blocked_requests' => 0,
        'burst_requests' => 0,
        'ip_blocks' => 0,
        'user_blocks' => 0,
    ];

    /**
     * Whitelisted IPs and users
     *
     * @since 1.0.0
     * @var array
     */
    private array $whitelist = [
        'ips' => [],
        'users' => [],
    ];

    /**
     * Constructor
     *
     * Initializes the rate limiter with default settings and loads
     * configuration from WordPress options.
     *
     * @since 1.0.0
     */
    private function __construct()
    {
        $this->loadConfiguration();
        $this->loadStatistics();
        $this->setupHooks();
    }

    /**
     * Setup WordPress hooks
     *
     * Registers hooks for cleanup and statistics saving.
     *
     * @since 1.0.0
     * @return void
     */
    private function setupHooks(): void
    {
        // Cleanup expired rate limit data
        add_action('wp_scheduled_delete', [$this, 'cleanupExpiredData']);

        // Save statistics periodically
        add_action('shutdown', [$this, 'saveStatistics']);

        // Handle rate limit exceeded actions
        add_action('woo_ai_assistant_rate_limit_exceeded', [$this, 'handleRateLimitExceeded'], 10, 3);
    }

    /**
     * Check rate limit for request
     *
     * Checks if a request should be allowed based on rate limiting rules
     * for the specified action and identifier.
     *
     * @since 1.0.0
     * @param string $action Action being performed
     * @param string $identifier Identifier (IP or user ID)
     * @param string $type Type of identifier ('ip' or 'user')
     * @return array Rate limit check result
     *
     * @example
     * ```php
     * $rateLimiter = RateLimiter::getInstance();
     * $result = $rateLimiter->checkRateLimit('chat_send', '192.168.1.1', 'ip');
     * if (!$result['allowed']) {
     *     // Handle rate limit exceeded
     * }
     * ```
     */
    public function checkRateLimit(string $action, string $identifier, string $type = 'ip'): array
    {
        // Update statistics
        $this->statistics['total_requests']++;

        // Check whitelist first
        if ($this->isWhitelisted($identifier, $type)) {
            return [
                'allowed' => true,
                'reason' => 'whitelisted',
                'remaining' => PHP_INT_MAX,
                'reset_time' => 0,
                'burst_used' => false,
            ];
        }

        // Get rate limits for action
        $limits = $this->getRateLimitsForAction($action);

        // Check each time window
        $result = $this->checkTimeWindows($action, $identifier, $type, $limits);

        // If blocked, update statistics and trigger action
        if (!$result['allowed']) {
            $this->statistics['blocked_requests']++;

            if ($type === 'ip') {
                $this->statistics['ip_blocks']++;
            } else {
                $this->statistics['user_blocks']++;
            }

            // Trigger rate limit exceeded action
            do_action('woo_ai_assistant_rate_limit_exceeded', $action, $identifier, $type);

            Utils::logError('Rate limit exceeded', [
                'action' => $action,
                'identifier' => $identifier,
                'type' => $type,
                'reason' => $result['reason'],
            ]);
        }

        return $result;
    }

    /**
     * Record request
     *
     * Records a request for rate limiting tracking after it has been
     * allowed to proceed.
     *
     * @since 1.0.0
     * @param string $action Action being performed
     * @param string $identifier Identifier (IP or user ID)
     * @param string $type Type of identifier ('ip' or 'user')
     * @param bool $burstUsed Whether burst allowance was used
     * @return void
     */
    public function recordRequest(string $action, string $identifier, string $type = 'ip', bool $burstUsed = false): void
    {
        $limits = $this->getRateLimitsForAction($action);

        // Record request in each time window
        foreach ($this->timeWindows as $window => $duration) {
            $key = $this->generateKey($action, $identifier, $type, $window);
            $current = (int) get_transient($key);

            set_transient($key, $current + 1, $duration);
        }

        // Record burst usage if applicable
        if ($burstUsed) {
            $this->statistics['burst_requests']++;
            $burstKey = $this->generateBurstKey($action, $identifier, $type);
            $currentBurst = (int) get_transient($burstKey);
            set_transient($burstKey, $currentBurst + 1, $this->timeWindows['minute']);
        }

        Utils::logDebug('Request recorded for rate limiting', [
            'action' => $action,
            'identifier' => $identifier,
            'type' => $type,
            'burst_used' => $burstUsed,
        ]);
    }

    /**
     * Check if IP or user is rate limited
     *
     * Quick check to see if an identifier is currently being rate limited.
     *
     * @since 1.0.0
     * @param string $identifier Identifier to check
     * @param string $type Type of identifier ('ip' or 'user')
     * @param string $action Specific action to check (optional)
     * @return bool True if currently rate limited
     */
    public function isRateLimited(string $identifier, string $type = 'ip', string $action = 'default'): bool
    {
        $result = $this->checkRateLimit($action, $identifier, $type);
        return !$result['allowed'];
    }

    /**
     * Get remaining requests
     *
     * Returns the number of requests remaining in the current time window.
     *
     * @since 1.0.0
     * @param string $action Action to check
     * @param string $identifier Identifier to check
     * @param string $type Type of identifier ('ip' or 'user')
     * @return array Remaining requests for each time window
     */
    public function getRemainingRequests(string $action, string $identifier, string $type = 'ip'): array
    {
        $limits = $this->getRateLimitsForAction($action);
        $remaining = [];

        foreach ($this->timeWindows as $window => $duration) {
            $key = $this->generateKey($action, $identifier, $type, $window);
            $current = (int) get_transient($key);
            $limit = $limits['requests_per_' . $window] ?? PHP_INT_MAX;

            $remaining[$window] = max(0, $limit - $current);
        }

        return $remaining;
    }

    /**
     * Add to whitelist
     *
     * Adds an IP address or user ID to the whitelist to bypass rate limiting.
     *
     * @since 1.0.0
     * @param string $identifier IP address or user ID
     * @param string $type Type of identifier ('ip' or 'user')
     * @return bool True if successfully added
     */
    public function addToWhitelist(string $identifier, string $type = 'ip'): bool
    {
        if (!in_array($type, ['ip', 'user'], true)) {
            return false;
        }

        if (!in_array($identifier, $this->whitelist[$type . 's'], true)) {
            $this->whitelist[$type . 's'][] = $identifier;
            $this->saveWhitelist();

            Utils::logDebug("Added {$identifier} to {$type} whitelist");
            return true;
        }

        return false;
    }

    /**
     * Remove from whitelist
     *
     * Removes an IP address or user ID from the whitelist.
     *
     * @since 1.0.0
     * @param string $identifier IP address or user ID
     * @param string $type Type of identifier ('ip' or 'user')
     * @return bool True if successfully removed
     */
    public function removeFromWhitelist(string $identifier, string $type = 'ip'): bool
    {
        if (!in_array($type, ['ip', 'user'], true)) {
            return false;
        }

        $key = array_search($identifier, $this->whitelist[$type . 's'], true);
        if ($key !== false) {
            unset($this->whitelist[$type . 's'][$key]);
            $this->whitelist[$type . 's'] = array_values($this->whitelist[$type . 's']);
            $this->saveWhitelist();

            Utils::logDebug("Removed {$identifier} from {$type} whitelist");
            return true;
        }

        return false;
    }

    /**
     * Clear rate limits
     *
     * Clears rate limiting data for a specific identifier or action.
     *
     * @since 1.0.0
     * @param string $identifier Identifier to clear (optional)
     * @param string $type Type of identifier ('ip' or 'user')
     * @param string $action Specific action to clear (optional)
     * @return int Number of entries cleared
     */
    public function clearRateLimits(string $identifier = '', string $type = 'ip', string $action = ''): int
    {
        global $wpdb;

        $cleared = 0;

        if (!empty($identifier) && !empty($action)) {
            // Clear specific identifier and action
            foreach ($this->timeWindows as $window => $duration) {
                $key = $this->generateKey($action, $identifier, $type, $window);
                if (delete_transient($key)) {
                    $cleared++;
                }
            }

            // Clear burst data
            $burstKey = $this->generateBurstKey($action, $identifier, $type);
            if (delete_transient($burstKey)) {
                $cleared++;
            }
        } elseif (!empty($identifier)) {
            // Clear all actions for identifier
            $pattern = $this->keyPrefix . $type . '_' . md5($identifier) . '_%';
            $keys = $wpdb->get_col($wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                $pattern
            ));

            foreach ($keys as $key) {
                $transientKey = str_replace('_transient_', '', $key);
                if (delete_transient($transientKey)) {
                    $cleared++;
                }
            }
        } else {
            // Clear all rate limit data
            $pattern = $this->keyPrefix . '%';
            $keys = $wpdb->get_col($wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                $pattern
            ));

            foreach ($keys as $key) {
                $transientKey = str_replace('_transient_', '', $key);
                if (delete_transient($transientKey)) {
                    $cleared++;
                }
            }
        }

        Utils::logDebug("Cleared {$cleared} rate limit entries");
        return $cleared;
    }

    /**
     * Configure rate limits
     *
     * Updates rate limiting configuration for specific actions.
     *
     * @since 1.0.0
     * @param array $config New rate limit configuration
     * @return void
     */
    public function configureRateLimits(array $config): void
    {
        foreach ($config as $action => $limits) {
            if (is_array($limits)) {
                $this->rateLimits[$action] = array_merge(
                    $this->rateLimits[$action] ?? $this->rateLimits['default'],
                    $limits
                );
            }
        }

        $this->saveConfiguration();
        Utils::logDebug('Rate limits configuration updated', $config);
    }

    /**
     * Handle rate limit exceeded
     *
     * Default handler for rate limit exceeded events.
     *
     * @since 1.0.0
     * @param string $action Action that exceeded limit
     * @param string $identifier Identifier that exceeded limit
     * @param string $type Type of identifier
     * @return void
     */
    public function handleRateLimitExceeded(string $action, string $identifier, string $type): void
    {
        // Log the incident
        Utils::logError('Rate limit exceeded - automatic handling', [
            'action' => $action,
            'identifier' => $identifier,
            'type' => $type,
            'timestamp' => time(),
        ]);

        // Add temporary rate limit increase for persistent offenders
        $offenderKey = "rate_limit_offender_{$type}_" . md5($identifier);
        $offenses = (int) get_transient($offenderKey);
        set_transient($offenderKey, $offenses + 1, HOUR_IN_SECONDS);

        // If too many offenses, add to temporary blacklist
        if ($offenses >= 5) {
            $blacklistKey = "rate_limit_blacklist_{$type}_" . md5($identifier);
            set_transient($blacklistKey, true, HOUR_IN_SECONDS * 24);

            Utils::logError('Identifier added to temporary blacklist', [
                'identifier' => $identifier,
                'type' => $type,
                'offenses' => $offenses + 1,
            ]);
        }
    }

    /**
     * Check time windows
     *
     * Checks rate limits across all configured time windows.
     *
     * @since 1.0.0
     * @param string $action Action being performed
     * @param string $identifier Identifier to check
     * @param string $type Type of identifier
     * @param array $limits Rate limit configuration
     * @return array Check result
     */
    private function checkTimeWindows(string $action, string $identifier, string $type, array $limits): array
    {
        // Check if temporarily blacklisted
        $blacklistKey = "rate_limit_blacklist_{$type}_" . md5($identifier);
        if (get_transient($blacklistKey)) {
            return [
                'allowed' => false,
                'reason' => 'temporarily_blacklisted',
                'remaining' => 0,
                'reset_time' => time() + HOUR_IN_SECONDS * 24,
                'burst_used' => false,
            ];
        }

        foreach ($this->timeWindows as $window => $duration) {
            $key = $this->generateKey($action, $identifier, $type, $window);
            $current = (int) get_transient($key);
            $limit = $limits['requests_per_' . $window] ?? PHP_INT_MAX;

            if ($current >= $limit) {
                // Check if burst allowance can be used
                if (isset($limits['burst_allowance']) && $window === 'minute') {
                    $burstKey = $this->generateBurstKey($action, $identifier, $type);
                    $burstUsed = (int) get_transient($burstKey);

                    if ($burstUsed < $limits['burst_allowance']) {
                        return [
                            'allowed' => true,
                            'reason' => 'burst_allowance',
                            'remaining' => $limits['burst_allowance'] - $burstUsed - 1,
                            'reset_time' => time() + $duration,
                            'burst_used' => true,
                        ];
                    }
                }

                return [
                    'allowed' => false,
                    'reason' => "limit_exceeded_{$window}",
                    'remaining' => 0,
                    'reset_time' => time() + $duration,
                    'current_usage' => $current,
                    'limit' => $limit,
                    'burst_used' => false,
                ];
            }
        }

        // Calculate minimum remaining across all windows
        $minRemaining = PHP_INT_MAX;
        $resetTime = time();

        foreach ($this->timeWindows as $window => $duration) {
            $key = $this->generateKey($action, $identifier, $type, $window);
            $current = (int) get_transient($key);
            $limit = $limits['requests_per_' . $window] ?? PHP_INT_MAX;
            $remaining = $limit - $current - 1; // -1 for the current request

            if ($remaining < $minRemaining) {
                $minRemaining = $remaining;
                $resetTime = time() + $duration;
            }
        }

        return [
            'allowed' => true,
            'reason' => 'within_limits',
            'remaining' => max(0, $minRemaining),
            'reset_time' => $resetTime,
            'burst_used' => false,
        ];
    }

    /**
     * Generate storage key
     *
     * Creates a unique key for storing rate limit data in transients.
     *
     * @since 1.0.0
     * @param string $action Action name
     * @param string $identifier Identifier
     * @param string $type Type of identifier
     * @param string $window Time window
     * @return string Storage key
     */
    private function generateKey(string $action, string $identifier, string $type, string $window): string
    {
        return $this->keyPrefix . $type . '_' . md5($identifier) . '_' . $action . '_' . $window;
    }

    /**
     * Generate burst storage key
     *
     * Creates a unique key for storing burst allowance data.
     *
     * @since 1.0.0
     * @param string $action Action name
     * @param string $identifier Identifier
     * @param string $type Type of identifier
     * @return string Burst storage key
     */
    private function generateBurstKey(string $action, string $identifier, string $type): string
    {
        return $this->keyPrefix . 'burst_' . $type . '_' . md5($identifier) . '_' . $action;
    }

    /**
     * Get rate limits for action
     *
     * Returns rate limit configuration for the specified action.
     *
     * @since 1.0.0
     * @param string $action Action name
     * @return array Rate limit configuration
     */
    private function getRateLimitsForAction(string $action): array
    {
        return $this->rateLimits[$action] ?? $this->rateLimits['default'];
    }

    /**
     * Check if identifier is whitelisted
     *
     * Checks if an IP or user is in the whitelist.
     *
     * @since 1.0.0
     * @param string $identifier Identifier to check
     * @param string $type Type of identifier
     * @return bool True if whitelisted
     */
    private function isWhitelisted(string $identifier, string $type): bool
    {
        return in_array($identifier, $this->whitelist[$type . 's'] ?? [], true);
    }

    /**
     * Load configuration from database
     *
     * Loads rate limiting configuration from WordPress options.
     *
     * @since 1.0.0
     * @return void
     */
    private function loadConfiguration(): void
    {
        $config = get_option('woo_ai_assistant_rate_limits', []);
        if (!empty($config)) {
            $this->rateLimits = array_merge($this->rateLimits, $config);
        }

        $whitelist = get_option('woo_ai_assistant_rate_limit_whitelist', []);
        if (!empty($whitelist)) {
            $this->whitelist = array_merge($this->whitelist, $whitelist);
        }
    }

    /**
     * Save configuration to database
     *
     * Saves current rate limiting configuration to WordPress options.
     *
     * @since 1.0.0
     * @return void
     */
    private function saveConfiguration(): void
    {
        update_option('woo_ai_assistant_rate_limits', $this->rateLimits);
    }

    /**
     * Save whitelist to database
     *
     * Saves current whitelist to WordPress options.
     *
     * @since 1.0.0
     * @return void
     */
    private function saveWhitelist(): void
    {
        update_option('woo_ai_assistant_rate_limit_whitelist', $this->whitelist);
    }

    /**
     * Load statistics from database
     *
     * Loads rate limiting statistics from WordPress options.
     *
     * @since 1.0.0
     * @return void
     */
    private function loadStatistics(): void
    {
        $stats = get_option('woo_ai_assistant_rate_limit_stats', []);
        $this->statistics = array_merge($this->statistics, $stats);
    }

    /**
     * Save statistics to database
     *
     * Saves current statistics to WordPress options.
     *
     * @since 1.0.0
     * @return void
     */
    public function saveStatistics(): void
    {
        update_option('woo_ai_assistant_rate_limit_stats', $this->statistics);
    }

    /**
     * Cleanup expired data
     *
     * Removes expired rate limit data to prevent database bloat.
     *
     * @since 1.0.0
     * @return void
     */
    public function cleanupExpiredData(): void
    {
        // WordPress transients automatically clean up expired data,
        // but we can add additional cleanup logic here if needed

        global $wpdb;

        // Clean up expired transients with our prefix
        $expired = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} 
             WHERE option_name LIKE %s 
             AND option_name LIKE %s
             AND option_value < %d",
            '_transient_timeout_' . $this->keyPrefix . '%',
            '%' . $this->keyPrefix . '%',
            time()
        ));

        $cleaned = 0;
        foreach ($expired as $transientTimeout) {
            $transientName = str_replace('_transient_timeout_', '', $transientTimeout);
            if (delete_transient($transientName)) {
                $cleaned++;
            }
        }

        if ($cleaned > 0) {
            Utils::logDebug("Cleaned up {$cleaned} expired rate limit entries");
        }
    }

    /**
     * Get rate limiting statistics
     *
     * Returns comprehensive statistics about rate limiting operations.
     *
     * @since 1.0.0
     * @return array Rate limiting statistics
     */
    public function getStatistics(): array
    {
        return [
            'statistics' => $this->statistics,
            'configuration' => [
                'rate_limits' => $this->rateLimits,
                'time_windows' => $this->timeWindows,
            ],
            'whitelist' => $this->whitelist,
            'active_limits' => $this->getActiveLimitsCount(),
        ];
    }

    /**
     * Get count of active rate limits
     *
     * Returns the number of currently active rate limit entries.
     *
     * @since 1.0.0
     * @return int Number of active rate limit entries
     */
    private function getActiveLimitsCount(): int
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->options} 
             WHERE option_name LIKE %s",
            '_transient_' . $this->keyPrefix . '%'
        ));
    }

    /**
     * Reset all statistics
     *
     * Resets all rate limiting statistics to zero.
     *
     * @since 1.0.0
     * @return void
     */
    public function resetStatistics(): void
    {
        $this->statistics = [
            'total_requests' => 0,
            'blocked_requests' => 0,
            'burst_requests' => 0,
            'ip_blocks' => 0,
            'user_blocks' => 0,
        ];

        $this->saveStatistics();
    }
}
