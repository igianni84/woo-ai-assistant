<?php

/**
 * CSRF Protection Class
 *
 * Provides comprehensive Cross-Site Request Forgery (CSRF) protection using
 * WordPress nonces. Handles nonce generation, verification, and automatic
 * refresh for AJAX requests and form submissions.
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
 * Class CsrfProtection
 *
 * Comprehensive CSRF protection system that leverages WordPress nonces for
 * secure form and AJAX request handling. Provides automatic nonce generation,
 * verification, and refresh mechanisms for enhanced security.
 *
 * @since 1.0.0
 */
class CsrfProtection
{
    use Singleton;

    /**
     * Default nonce action prefix
     *
     * @since 1.0.0
     * @var string
     */
    private string $noncePrefix = 'woo_ai_assistant_';

    /**
     * Nonce lifetime in seconds (WordPress default is 24 hours)
     *
     * @since 1.0.0
     * @var int
     */
    private int $nonceLifetime = DAY_IN_SECONDS;

    /**
     * Active nonces cache
     *
     * @since 1.0.0
     * @var array
     */
    private array $noncesCache = [];

    /**
     * CSRF protection statistics
     *
     * @since 1.0.0
     * @var array
     */
    private array $statistics = [
        'nonces_generated' => 0,
        'nonces_verified' => 0,
        'verification_failures' => 0,
        'automatic_refreshes' => 0,
    ];

    /**
     * Constructor
     *
     * Initializes CSRF protection with WordPress hooks for automatic
     * nonce handling and refresh mechanisms.
     *
     * @since 1.0.0
     */
    private function __construct()
    {
        $this->setupHooks();
        $this->loadStatistics();
    }

    /**
     * Setup WordPress hooks
     *
     * Registers hooks for automatic nonce refresh, AJAX handling,
     * and cleanup operations.
     *
     * @since 1.0.0
     * @return void
     */
    private function setupHooks(): void
    {
        // AJAX nonce refresh endpoint
        add_action('wp_ajax_woo_ai_assistant_refresh_nonce', [$this, 'handleNonceRefresh']);
        add_action('wp_ajax_nopriv_woo_ai_assistant_refresh_nonce', [$this, 'handleNonceRefresh']);

        // Enqueue scripts for automatic refresh
        add_action('wp_enqueue_scripts', [$this, 'enqueueNonceRefreshScript']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueNonceRefreshScript']);

        // Cleanup expired nonces
        add_action('wp_scheduled_delete', [$this, 'cleanupExpiredNonces']);

        // Statistics update hook
        add_action('shutdown', [$this, 'saveStatistics']);
    }

    /**
     * Generate nonce for action
     *
     * Creates a WordPress nonce for the specified action with optional
     * context-specific parameters.
     *
     * @since 1.0.0
     * @param string $action Action name for the nonce
     * @param array $context Additional context for nonce generation
     * @return string Generated nonce token
     *
     * @example
     * ```php
     * $csrf = CsrfProtection::getInstance();
     * $nonce = $csrf->generateNonce('chat_send', ['user_id' => get_current_user_id()]);
     * ```
     */
    public function generateNonce(string $action, array $context = []): string
    {
        // Sanitize action name
        $action = sanitize_key($action);

        if (empty($action)) {
            throw new \InvalidArgumentException('Action name cannot be empty');
        }

        // Create full action name with prefix
        $fullAction = $this->noncePrefix . $action;

        // Add context to action if provided
        if (!empty($context)) {
            $contextHash = md5(wp_json_encode($context));
            $fullAction .= '_' . substr($contextHash, 0, 8);
        }

        // Generate WordPress nonce
        $nonce = wp_create_nonce($fullAction);

        // Cache nonce information
        $this->noncesCache[$nonce] = [
            'action' => $fullAction,
            'context' => $context,
            'created' => time(),
            'expires' => time() + $this->nonceLifetime,
        ];

        // Update statistics
        $this->statistics['nonces_generated']++;

        Utils::logDebug('CSRF nonce generated', [
            'action' => $action,
            'full_action' => $fullAction,
            'nonce_length' => strlen($nonce),
        ]);

        return $nonce;
    }

    /**
     * Verify nonce
     *
     * Verifies a WordPress nonce against the specified action with
     * comprehensive error handling and logging.
     *
     * @since 1.0.0
     * @param string $nonce Nonce to verify
     * @param string $action Action name the nonce was created for
     * @param array $context Optional context for verification
     * @return bool True if nonce is valid, false otherwise
     *
     * @example
     * ```php
     * $csrf = CsrfProtection::getInstance();
     * $isValid = $csrf->verifyNonce($_POST['nonce'], 'chat_send');
     * ```
     */
    public function verifyNonce(string $nonce, string $action, array $context = []): bool
    {
        // Sanitize inputs
        $nonce = sanitize_text_field($nonce);
        $action = sanitize_key($action);

        if (empty($nonce) || empty($action)) {
            $this->statistics['verification_failures']++;
            Utils::logError('CSRF verification failed: empty nonce or action');
            return false;
        }

        // Create full action name with prefix
        $fullAction = $this->noncePrefix . $action;

        // Add context to action if provided
        if (!empty($context)) {
            $contextHash = md5(wp_json_encode($context));
            $fullAction .= '_' . substr($contextHash, 0, 8);
        }

        // Verify WordPress nonce
        $verification = wp_verify_nonce($nonce, $fullAction);

        if ($verification === false) {
            $this->statistics['verification_failures']++;
            Utils::logError('CSRF verification failed', [
                'action' => $action,
                'full_action' => $fullAction,
                'nonce_length' => strlen($nonce),
                'ip' => Utils::getClientIpAddress(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            ]);

            // Trigger security event
            do_action('woo_ai_assistant_csrf_failure', [
                'action' => $action,
                'nonce' => $nonce,
                'ip' => Utils::getClientIpAddress(),
                'timestamp' => time(),
            ]);

            return false;
        }

        // Update statistics
        $this->statistics['nonces_verified']++;

        // Clean up verified nonce from cache
        unset($this->noncesCache[$nonce]);

        Utils::logDebug('CSRF nonce verified successfully', [
            'action' => $action,
            'verification_result' => $verification,
        ]);

        return true;
    }

    /**
     * Create nonce field
     *
     * Creates an HTML hidden input field with nonce for forms.
     *
     * @since 1.0.0
     * @param string $action Action name for the nonce
     * @param string $name Field name (default: 'nonce')
     * @param bool $referer Whether to include referer field
     * @param bool $echo Whether to echo the field or return it
     * @return string HTML field string if not echoed
     */
    public function createNonceField(string $action, string $name = 'nonce', bool $referer = true, bool $echo = true): string
    {
        $nonce = $this->generateNonce($action);

        $field = sprintf(
            '<input type="hidden" name="%s" value="%s" />',
            esc_attr($name),
            esc_attr($nonce)
        );

        if ($referer) {
            $field .= wp_referer_field(false);
        }

        if ($echo) {
            echo $field;
            return '';
        }

        return $field;
    }

    /**
     * Create nonce URL
     *
     * Adds nonce parameter to URL for GET request protection.
     *
     * @since 1.0.0
     * @param string $url Base URL
     * @param string $action Action name for the nonce
     * @param string $name Parameter name (default: 'nonce')
     * @return string URL with nonce parameter
     */
    public function createNonceUrl(string $url, string $action, string $name = 'nonce'): string
    {
        $nonce = $this->generateNonce($action);

        return add_query_arg([
            $name => $nonce,
        ], $url);
    }

    /**
     * Verify request nonce
     *
     * Automatically verifies nonce from various request sources
     * (POST, GET, headers) for comprehensive protection.
     *
     * @since 1.0.0
     * @param string $action Action name to verify against
     * @param string $name Parameter name to look for
     * @return bool True if request nonce is valid
     */
    public function verifyRequestNonce(string $action, string $name = 'nonce'): bool
    {
        $nonce = null;

        // Try to get nonce from various sources
        if (isset($_POST[$name])) {
            $nonce = $_POST[$name];
        } elseif (isset($_GET[$name])) {
            $nonce = $_GET[$name];
        } elseif (isset($_REQUEST[$name])) {
            $nonce = $_REQUEST[$name];
        } else {
            // Try to get from headers (for AJAX requests)
            $headers = getallheaders();
            if ($headers) {
                $headerName = 'X-WP-Nonce';
                if (isset($headers[$headerName])) {
                    $nonce = $headers[$headerName];
                }
            }
        }

        if ($nonce === null) {
            Utils::logError('CSRF protection: No nonce found in request', [
                'action' => $action,
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'Unknown',
            ]);
            return false;
        }

        return $this->verifyNonce($nonce, $action);
    }

    /**
     * Handle AJAX nonce refresh
     *
     * AJAX endpoint for automatic nonce refresh when nonces are close
     * to expiration.
     *
     * @since 1.0.0
     * @return void
     */
    public function handleNonceRefresh(): void
    {
        // Verify the refresh request itself
        if (!$this->verifyRequestNonce('refresh_nonce')) {
            wp_send_json_error([
                'message' => 'Invalid refresh request',
                'code' => 'invalid_refresh_nonce',
            ]);
        }

        $action = sanitize_text_field($_POST['action_name'] ?? '');

        if (empty($action)) {
            wp_send_json_error([
                'message' => 'Action name required',
                'code' => 'missing_action',
            ]);
        }

        // Generate new nonce
        try {
            $newNonce = $this->generateNonce($action);

            $this->statistics['automatic_refreshes']++;

            wp_send_json_success([
                'nonce' => $newNonce,
                'action' => $action,
                'expires_in' => $this->nonceLifetime,
            ]);
        } catch (\Exception $e) {
            Utils::logError('Nonce refresh failed: ' . $e->getMessage());

            wp_send_json_error([
                'message' => 'Failed to generate new nonce',
                'code' => 'generation_failed',
            ]);
        }
    }

    /**
     * Enqueue nonce refresh script
     *
     * Enqueues JavaScript for automatic nonce refresh functionality.
     *
     * @since 1.0.0
     * @return void
     */
    public function enqueueNonceRefreshScript(): void
    {
        $script = "
        window.WooAiCsrf = {
            refreshNonce: function(action, callback) {
                jQuery.post(ajaxurl, {
                    action: 'woo_ai_assistant_refresh_nonce',
                    action_name: action,
                    nonce: woo_ai_csrf.refresh_nonce
                }, function(response) {
                    if (response.success) {
                        callback(null, response.data.nonce);
                    } else {
                        callback(response.data.message || 'Refresh failed', null);
                    }
                });
            },
            
            autoRefresh: function(action, element) {
                var self = this;
                var refreshInterval = " . ($this->nonceLifetime * 1000 * 0.8) . "; // 80% of lifetime
                
                setInterval(function() {
                    self.refreshNonce(action, function(error, newNonce) {
                        if (!error && element) {
                            if (element.tagName === 'INPUT') {
                                element.value = newNonce;
                            } else {
                                element.setAttribute('data-nonce', newNonce);
                            }
                        }
                    });
                }, refreshInterval);
            }
        };
        ";

        wp_add_inline_script('jquery', $script);

        // Localize script with refresh nonce
        wp_localize_script('jquery', 'woo_ai_csrf', [
            'refresh_nonce' => $this->generateNonce('refresh_nonce'),
            'ajax_url' => admin_url('admin-ajax.php'),
        ]);
    }

    /**
     * Cleanup expired nonces
     *
     * Removes expired nonces from cache to prevent memory issues.
     *
     * @since 1.0.0
     * @return void
     */
    public function cleanupExpiredNonces(): void
    {
        $currentTime = time();
        $cleanedUp = 0;

        foreach ($this->noncesCache as $nonce => $data) {
            if ($data['expires'] < $currentTime) {
                unset($this->noncesCache[$nonce]);
                $cleanedUp++;
            }
        }

        if ($cleanedUp > 0) {
            Utils::logDebug("Cleaned up {$cleanedUp} expired nonces");
        }
    }

    /**
     * Get nonce for JavaScript
     *
     * Returns nonce specifically formatted for JavaScript consumption
     * with additional metadata.
     *
     * @since 1.0.0
     * @param string $action Action name for the nonce
     * @return array Nonce data for JavaScript
     */
    public function getNonceForJs(string $action): array
    {
        $nonce = $this->generateNonce($action);

        return [
            'nonce' => $nonce,
            'action' => $action,
            'expires_in' => $this->nonceLifetime,
            'refresh_url' => admin_url('admin-ajax.php'),
            'refresh_action' => 'woo_ai_assistant_refresh_nonce',
        ];
    }

    /**
     * Validate multiple nonces
     *
     * Validates multiple nonces in a single operation for bulk operations.
     *
     * @since 1.0.0
     * @param array $nonces Associative array of action => nonce pairs
     * @return array Validation results for each nonce
     */
    public function validateMultipleNonces(array $nonces): array
    {
        $results = [];

        foreach ($nonces as $action => $nonce) {
            $results[$action] = [
                'valid' => $this->verifyNonce($nonce, $action),
                'action' => $action,
                'verified_at' => time(),
            ];
        }

        return $results;
    }

    /**
     * Check nonce age
     *
     * Determines how old a nonce is and whether it's close to expiration.
     *
     * @since 1.0.0
     * @param string $nonce Nonce to check
     * @return array Age information including seconds remaining
     */
    public function checkNonceAge(string $nonce): array
    {
        if (!isset($this->noncesCache[$nonce])) {
            return [
                'found' => false,
                'age' => null,
                'expires_in' => null,
                'needs_refresh' => true,
            ];
        }

        $data = $this->noncesCache[$nonce];
        $currentTime = time();
        $age = $currentTime - $data['created'];
        $expiresIn = $data['expires'] - $currentTime;
        $needsRefresh = $expiresIn < ($this->nonceLifetime * 0.2); // Refresh when 20% lifetime remains

        return [
            'found' => true,
            'age' => $age,
            'expires_in' => $expiresIn,
            'needs_refresh' => $needsRefresh,
            'created' => $data['created'],
            'expires' => $data['expires'],
        ];
    }

    /**
     * Load statistics from database
     *
     * Loads CSRF protection statistics from WordPress options.
     *
     * @since 1.0.0
     * @return void
     */
    private function loadStatistics(): void
    {
        $savedStats = get_option('woo_ai_assistant_csrf_stats', []);
        $this->statistics = array_merge($this->statistics, $savedStats);
    }

    /**
     * Save statistics to database
     *
     * Saves current CSRF protection statistics to WordPress options.
     *
     * @since 1.0.0
     * @return void
     */
    public function saveStatistics(): void
    {
        update_option('woo_ai_assistant_csrf_stats', $this->statistics);
    }

    /**
     * Get protection statistics
     *
     * Returns comprehensive statistics about CSRF protection operations.
     *
     * @since 1.0.0
     * @return array CSRF protection statistics
     */
    public function getStatistics(): array
    {
        return [
            'nonces' => $this->statistics,
            'cache' => [
                'active_nonces' => count($this->noncesCache),
                'memory_usage' => memory_get_usage(),
            ],
            'settings' => [
                'nonce_lifetime' => $this->nonceLifetime,
                'nonce_prefix' => $this->noncePrefix,
            ],
        ];
    }

    /**
     * Reset statistics
     *
     * Resets all CSRF protection statistics to zero.
     *
     * @since 1.0.0
     * @return void
     */
    public function resetStatistics(): void
    {
        $this->statistics = [
            'nonces_generated' => 0,
            'nonces_verified' => 0,
            'verification_failures' => 0,
            'automatic_refreshes' => 0,
        ];

        $this->saveStatistics();
    }

    /**
     * Configure nonce settings
     *
     * Allows customization of nonce behavior and lifetime.
     *
     * @since 1.0.0
     * @param array $settings Configuration settings
     * @return void
     */
    public function configureSettings(array $settings): void
    {
        if (isset($settings['lifetime'])) {
            $this->nonceLifetime = absint($settings['lifetime']);
        }

        if (isset($settings['prefix'])) {
            $this->noncePrefix = sanitize_key($settings['prefix']) . '_';
        }

        Utils::logDebug('CSRF settings updated', $settings);
    }
}
