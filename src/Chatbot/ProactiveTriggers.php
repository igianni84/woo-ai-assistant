<?php

/**
 * Proactive Triggers Class
 *
 * Manages proactive engagement triggers for the AI chatbot, including
 * exit-intent detection, inactivity monitoring, scroll-depth tracking,
 * and page-specific trigger rules.
 *
 * @package WooAiAssistant
 * @subpackage Chatbot
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Chatbot;

use WooAiAssistant\Common\Utils;
use WooAiAssistant\Common\Traits\Singleton;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ProactiveTriggers
 *
 * Implements intelligent proactive engagement triggers to enhance user experience
 * and increase conversion rates through strategic chatbot interventions.
 *
 * @since 1.0.0
 */
class ProactiveTriggers
{
    use Singleton;

    /**
     * Default inactivity timeout in milliseconds
     *
     * @since 1.0.0
     * @var int
     */
    const DEFAULT_INACTIVITY_TIMEOUT = 30000; // 30 seconds

    /**
     * Default scroll depth threshold percentage
     *
     * @since 1.0.0
     * @var int
     */
    const DEFAULT_SCROLL_THRESHOLD = 75; // 75% of page

    /**
     * Exit intent detection sensitivity levels
     *
     * @since 1.0.0
     * @var array
     */
    const EXIT_INTENT_SENSITIVITY = [
        'low' => ['threshold' => 50, 'velocity' => 5],
        'medium' => ['threshold' => 30, 'velocity' => 8],
        'high' => ['threshold' => 20, 'velocity' => 12]
    ];

    /**
     * Trigger types available
     *
     * @since 1.0.0
     * @var array
     */
    const TRIGGER_TYPES = [
        'exit_intent',
        'inactivity',
        'scroll_depth',
        'time_spent',
        'page_specific',
        'cart_abandonment',
        'product_interest'
    ];

    /**
     * Plugin settings cache
     *
     * @since 1.0.0
     * @var array
     */
    private array $settings = [];

    /**
     * Trigger statistics
     *
     * @since 1.0.0
     * @var array
     */
    private array $stats = [];

    /**
     * Active page rules
     *
     * @since 1.0.0
     * @var array
     */
    private array $pageRules = [];

    /**
     * Constructor
     *
     * Initializes the proactive triggers system and sets up necessary hooks.
     *
     * @since 1.0.0
     */
    private function __construct()
    {
        $this->loadSettings();
        $this->setupHooks();
        $this->initializePageRules();
    }

    /**
     * Setup WordPress hooks
     *
     * @since 1.0.0
     * @return void
     */
    private function setupHooks(): void
    {
        // REST API endpoints
        add_action('rest_api_init', [$this, 'registerRestRoutes']);

        // Frontend scripts and localization
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontendScripts']);

        // Admin hooks
        if (is_admin()) {
            add_action('admin_init', [$this, 'adminInit']);
        }

        // WooCommerce specific hooks
        add_action('woocommerce_add_to_cart', [$this, 'onAddToCart'], 10, 6);
        add_action('woocommerce_cart_item_removed', [$this, 'onCartItemRemoved'], 10, 2);
        add_action('woocommerce_checkout_process', [$this, 'onCheckoutProcess']);

        // Custom hooks for extensibility
        add_action('woo_ai_assistant_trigger_fired', [$this, 'recordTriggerEvent'], 10, 3);
        add_filter('woo_ai_assistant_trigger_message', [$this, 'customizeTriggerMessage'], 10, 3);

        Utils::logDebug('ProactiveTriggers hooks initialized');
    }

    /**
     * Register REST API routes
     *
     * @since 1.0.0
     * @return void
     */
    public function registerRestRoutes(): void
    {
        // Trigger event endpoint
        register_rest_route('woo-ai-assistant/v1', '/trigger/fire', [
            'methods' => 'POST',
            'callback' => [$this, 'handleTriggerEvent'],
            'permission_callback' => [$this, 'checkTriggerPermission'],
            'args' => $this->getTriggerEventArgs()
        ]);

        // Get trigger configuration
        register_rest_route('woo-ai-assistant/v1', '/trigger/config', [
            'methods' => 'GET',
            'callback' => [$this, 'getTriggerConfig'],
            'permission_callback' => [$this, 'checkTriggerPermission']
        ]);

        // Update trigger settings (admin only)
        register_rest_route('woo-ai-assistant/v1', '/trigger/settings', [
            'methods' => 'POST',
            'callback' => [$this, 'updateTriggerSettings'],
            'permission_callback' => [$this, 'checkAdminPermission'],
            'args' => $this->getTriggerSettingsArgs()
        ]);

        // Get trigger statistics (admin only)
        register_rest_route('woo-ai-assistant/v1', '/trigger/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'getTriggerStats'],
            'permission_callback' => [$this, 'checkAdminPermission']
        ]);
    }

    /**
     * Check trigger permission
     *
     * @since 1.0.0
     * @param WP_REST_Request $request REST request object
     * @return bool|WP_Error True if permission granted
     */
    public function checkTriggerPermission(WP_REST_Request $request)
    {
        // Allow frontend trigger events for all users
        if (
            $request->get_route() === '/woo-ai-assistant/v1/trigger/fire' ||
            $request->get_route() === '/woo-ai-assistant/v1/trigger/config'
        ) {
            // Verify nonce for security
            $nonce = $request->get_header('X-WP-Nonce');
            if (!wp_verify_nonce($nonce, 'wp_rest')) {
                return new WP_Error(
                    'rest_forbidden',
                    __('Invalid nonce for trigger request.', 'woo-ai-assistant'),
                    ['status' => 403]
                );
            }

            return true;
        }

        return false;
    }

    /**
     * Check admin permission
     *
     * @since 1.0.0
     * @param WP_REST_Request $request REST request object
     * @return bool|WP_Error True if permission granted
     */
    public function checkAdminPermission(WP_REST_Request $request)
    {
        if (!current_user_can('manage_woocommerce')) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to access trigger settings.', 'woo-ai-assistant'),
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Enqueue frontend scripts for trigger detection
     *
     * @since 1.0.0
     * @return void
     */
    public function enqueueFrontendScripts(): void
    {
        if (is_admin()) {
            return;
        }

        // Check if triggers are enabled
        if (!$this->areTrigggersEnabled()) {
            return;
        }

        // Enqueue trigger detection script
        wp_enqueue_script(
            'woo-ai-assistant-triggers',
            WOO_AI_ASSISTANT_PLUGIN_URL . 'assets/js/triggers.min.js',
            ['jquery'],
            WOO_AI_ASSISTANT_VERSION,
            true
        );

        // Localize script with trigger configuration
        wp_localize_script('woo-ai-assistant-triggers', 'wooAiTriggers', [
            'config' => $this->getFrontendTriggerConfig(),
            'nonce' => wp_create_nonce('wp_rest'),
            'restUrl' => rest_url('woo-ai-assistant/v1/trigger/'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'userId' => get_current_user_id(),
            'sessionId' => $this->getSessionId(),
            'pageInfo' => $this->getCurrentPageInfo()
        ]);
    }

    /**
     * Handle trigger event from frontend
     *
     * @since 1.0.0
     * @param WP_REST_Request $request REST request object
     * @return WP_REST_Response|WP_Error Response or error
     */
    public function handleTriggerEvent(WP_REST_Request $request)
    {
        try {
            $triggerType = sanitize_text_field($request->get_param('trigger_type'));
            $triggerData = $request->get_param('trigger_data');
            $pageContext = $request->get_param('page_context');

            // Validate trigger type
            if (!in_array($triggerType, self::TRIGGER_TYPES, true)) {
                return new WP_Error(
                    'invalid_trigger_type',
                    __('Invalid trigger type specified.', 'woo-ai-assistant'),
                    ['status' => 400]
                );
            }

            // Process the trigger
            $response = $this->processTriggerEvent($triggerType, $triggerData, $pageContext);

            // Record trigger event for analytics
            $this->recordTriggerEvent($triggerType, $triggerData, $pageContext);

            return new WP_REST_Response([
                'success' => true,
                'trigger_type' => $triggerType,
                'response' => $response,
                'timestamp' => current_time('c')
            ], 200);
        } catch (Exception $e) {
            Utils::logError('Trigger event processing failed: ' . $e->getMessage());

            return new WP_Error(
                'trigger_processing_failed',
                __('Failed to process trigger event.', 'woo-ai-assistant'),
                ['status' => 500]
            );
        }
    }

    /**
     * Get trigger configuration for frontend
     *
     * @since 1.0.0
     * @param WP_REST_Request $request REST request object
     * @return WP_REST_Response Response with configuration
     */
    public function getTriggerConfig(WP_REST_Request $request)
    {
        $config = $this->getFrontendTriggerConfig();

        return new WP_REST_Response([
            'success' => true,
            'config' => $config,
            'page_rules' => $this->getPageSpecificRules(),
            'user_context' => $this->getUserContext()
        ], 200);
    }

    /**
     * Process individual trigger event
     *
     * @since 1.0.0
     * @param string $triggerType Type of trigger
     * @param array $triggerData Trigger-specific data
     * @param array $pageContext Current page context
     * @return array Response data
     * @throws Exception When trigger processing fails
     */
    private function processTriggerEvent(string $triggerType, array $triggerData, array $pageContext): array
    {
        switch ($triggerType) {
            case 'exit_intent':
                return $this->processExitIntentTrigger($triggerData, $pageContext);

            case 'inactivity':
                return $this->processInactivityTrigger($triggerData, $pageContext);

            case 'scroll_depth':
                return $this->processScrollDepthTrigger($triggerData, $pageContext);

            case 'time_spent':
                return $this->processTimeSpentTrigger($triggerData, $pageContext);

            case 'page_specific':
                return $this->processPageSpecificTrigger($triggerData, $pageContext);

            case 'cart_abandonment':
                return $this->processCartAbandonmentTrigger($triggerData, $pageContext);

            case 'product_interest':
                return $this->processProductInterestTrigger($triggerData, $pageContext);

            default:
                throw new Exception("Unknown trigger type: {$triggerType}");
        }
    }

    /**
     * Process exit intent trigger
     *
     * @since 1.0.0
     * @param array $triggerData Trigger data
     * @param array $pageContext Page context
     * @return array Response data
     */
    private function processExitIntentTrigger(array $triggerData, array $pageContext): array
    {
        $sensitivity = $this->settings['exit_intent_sensitivity'] ?? 'medium';
        $config = self::EXIT_INTENT_SENSITIVITY[$sensitivity];

        // Validate exit intent based on mouse movement
        $mouseY = intval($triggerData['mouse_y'] ?? 0);
        $velocity = intval($triggerData['velocity'] ?? 0);

        if ($mouseY <= $config['threshold'] && $velocity >= $config['velocity']) {
            $message = $this->generateTriggerMessage('exit_intent', $pageContext);

            return [
                'should_trigger' => true,
                'message' => $message,
                'trigger_id' => $this->generateTriggerId('exit_intent'),
                'priority' => 'high'
            ];
        }

        return ['should_trigger' => false];
    }

    /**
     * Process inactivity trigger
     *
     * @since 1.0.0
     * @param array $triggerData Trigger data
     * @param array $pageContext Page context
     * @return array Response data
     */
    private function processInactivityTrigger(array $triggerData, array $pageContext): array
    {
        $timeout = $this->settings['inactivity_timeout'] ?? self::DEFAULT_INACTIVITY_TIMEOUT;
        $inactiveTime = intval($triggerData['inactive_time'] ?? 0);

        if ($inactiveTime >= $timeout) {
            $message = $this->generateTriggerMessage('inactivity', $pageContext);

            return [
                'should_trigger' => true,
                'message' => $message,
                'trigger_id' => $this->generateTriggerId('inactivity'),
                'priority' => 'medium'
            ];
        }

        return ['should_trigger' => false];
    }

    /**
     * Process scroll depth trigger
     *
     * @since 1.0.0
     * @param array $triggerData Trigger data
     * @param array $pageContext Page context
     * @return array Response data
     */
    private function processScrollDepthTrigger(array $triggerData, array $pageContext): array
    {
        $threshold = $this->settings['scroll_threshold'] ?? self::DEFAULT_SCROLL_THRESHOLD;
        $scrollPercent = intval($triggerData['scroll_percent'] ?? 0);

        if ($scrollPercent >= $threshold) {
            $message = $this->generateTriggerMessage('scroll_depth', $pageContext);

            return [
                'should_trigger' => true,
                'message' => $message,
                'trigger_id' => $this->generateTriggerId('scroll_depth'),
                'priority' => 'low'
            ];
        }

        return ['should_trigger' => false];
    }

    /**
     * Process time spent trigger
     *
     * @since 1.0.0
     * @param array $triggerData Trigger data
     * @param array $pageContext Page context
     * @return array Response data
     */
    private function processTimeSpentTrigger(array $triggerData, array $pageContext): array
    {
        $minTimeSpent = $this->settings['min_time_spent'] ?? 120000; // 2 minutes
        $timeSpent = intval($triggerData['time_spent'] ?? 0);

        if ($timeSpent >= $minTimeSpent) {
            $message = $this->generateTriggerMessage('time_spent', $pageContext);

            return [
                'should_trigger' => true,
                'message' => $message,
                'trigger_id' => $this->generateTriggerId('time_spent'),
                'priority' => 'medium'
            ];
        }

        return ['should_trigger' => false];
    }

    /**
     * Process page-specific trigger
     *
     * @since 1.0.0
     * @param array $triggerData Trigger data
     * @param array $pageContext Page context
     * @return array Response data
     */
    private function processPageSpecificTrigger(array $triggerData, array $pageContext): array
    {
        $pageType = $pageContext['page_type'] ?? 'unknown';
        $rules = $this->pageRules[$pageType] ?? [];

        if (empty($rules) || !$rules['enabled']) {
            return ['should_trigger' => false];
        }

        // Check page-specific conditions
        $shouldTrigger = $this->evaluatePageRule($rules, $triggerData, $pageContext);

        if ($shouldTrigger) {
            $message = $this->generateTriggerMessage('page_specific', $pageContext, $rules);

            return [
                'should_trigger' => true,
                'message' => $message,
                'trigger_id' => $this->generateTriggerId('page_specific'),
                'priority' => $rules['priority'] ?? 'medium'
            ];
        }

        return ['should_trigger' => false];
    }

    /**
     * Process cart abandonment trigger
     *
     * @since 1.0.0
     * @param array $triggerData Trigger data
     * @param array $pageContext Page context
     * @return array Response data
     */
    private function processCartAbandonmentTrigger(array $triggerData, array $pageContext): array
    {
        if (!Utils::isWooCommerceActive() || !WC()->cart) {
            return ['should_trigger' => false];
        }

        $cartItemCount = WC()->cart->get_cart_contents_count();
        $cartValue = WC()->cart->get_cart_contents_total();

        // Only trigger if cart has items and meets minimum value
        $minCartValue = $this->settings['min_cart_value'] ?? 50;

        if ($cartItemCount > 0 && $cartValue >= $minCartValue) {
            $message = $this->generateTriggerMessage('cart_abandonment', $pageContext, [
                'cart_count' => $cartItemCount,
                'cart_value' => $cartValue
            ]);

            return [
                'should_trigger' => true,
                'message' => $message,
                'trigger_id' => $this->generateTriggerId('cart_abandonment'),
                'priority' => 'high'
            ];
        }

        return ['should_trigger' => false];
    }

    /**
     * Process product interest trigger
     *
     * @since 1.0.0
     * @param array $triggerData Trigger data
     * @param array $pageContext Page context
     * @return array Response data
     */
    private function processProductInterestTrigger(array $triggerData, array $pageContext): array
    {
        if ($pageContext['page_type'] !== 'product') {
            return ['should_trigger' => false];
        }

        $productId = intval($pageContext['product_id'] ?? 0);
        $timeOnProduct = intval($triggerData['time_on_product'] ?? 0);
        $minTime = $this->settings['product_interest_time'] ?? 90000; // 1.5 minutes

        if ($timeOnProduct >= $minTime && $productId > 0) {
            $product = wc_get_product($productId);

            if ($product) {
                $message = $this->generateTriggerMessage('product_interest', $pageContext, [
                    'product' => $product
                ]);

                return [
                    'should_trigger' => true,
                    'message' => $message,
                    'trigger_id' => $this->generateTriggerId('product_interest'),
                    'priority' => 'medium',
                    'product_id' => $productId
                ];
            }
        }

        return ['should_trigger' => false];
    }

    /**
     * Generate contextual trigger message
     *
     * @since 1.0.0
     * @param string $triggerType Type of trigger
     * @param array $pageContext Page context
     * @param array $extraData Additional data for message customization
     * @return string Generated message
     */
    private function generateTriggerMessage(string $triggerType, array $pageContext, array $extraData = []): string
    {
        $messages = $this->settings['trigger_messages'] ?? [];
        $pageType = $pageContext['page_type'] ?? 'general';

        // Get base message for trigger type
        $baseMessage = $messages[$triggerType][$pageType] ?? $messages[$triggerType]['general'] ?? '';

        // Apply filters for customization
        $message = apply_filters(
            'woo_ai_assistant_trigger_message',
            $baseMessage,
            $triggerType,
            $pageContext,
            $extraData
        );

        // Replace placeholders
        $message = $this->replacePlaceholders($message, $pageContext, $extraData);

        return $message;
    }

    /**
     * Replace message placeholders with actual values
     *
     * @since 1.0.0
     * @param string $message Message with placeholders
     * @param array $pageContext Page context
     * @param array $extraData Extra data
     * @return string Message with replaced placeholders
     */
    private function replacePlaceholders(string $message, array $pageContext, array $extraData): string
    {
        $placeholders = [
            '{user_name}' => wp_get_current_user()->display_name ?: __('there', 'woo-ai-assistant'),
            '{page_title}' => get_the_title($pageContext['page_id'] ?? 0),
            '{site_name}' => get_bloginfo('name'),
            '{cart_count}' => $extraData['cart_count'] ?? 0,
            '{cart_value}' => wc_price($extraData['cart_value'] ?? 0)
        ];

        // Product-specific placeholders
        if (isset($extraData['product']) && $extraData['product'] instanceof \WC_Product) {
            $product = $extraData['product'];
            $placeholders['{product_name}'] = $product->get_name();
            $placeholders['{product_price}'] = wc_price($product->get_price());
        }

        return str_replace(array_keys($placeholders), array_values($placeholders), $message);
    }

    /**
     * Record trigger event for analytics
     *
     * @since 1.0.0
     * @param string $triggerType Type of trigger
     * @param array $triggerData Trigger data
     * @param array $pageContext Page context
     * @return void
     */
    public function recordTriggerEvent(string $triggerType, array $triggerData, array $pageContext): void
    {
        global $wpdb;

        try {
            $tableName = $wpdb->prefix . 'woo_ai_trigger_events';

            // Check if table exists, create if not
            $this->ensureTriggerEventsTable();

            $wpdb->insert(
                $tableName,
                [
                    'trigger_type' => $triggerType,
                    'trigger_data' => wp_json_encode($triggerData),
                    'page_context' => wp_json_encode($pageContext),
                    'user_id' => get_current_user_id(),
                    'session_id' => $this->getSessionId(),
                    'ip_address' => Utils::getUserIpAddress(),
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'created_at' => current_time('mysql')
                ],
                [
                    '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s'
                ]
            );

            // Update stats
            $this->updateTriggerStats($triggerType);
        } catch (Exception $e) {
            Utils::logError('Failed to record trigger event: ' . $e->getMessage());
        }
    }

    /**
     * Load plugin settings
     *
     * @since 1.0.0
     * @return void
     */
    private function loadSettings(): void
    {
        $defaults = [
            'enabled' => true,
            'exit_intent_enabled' => true,
            'exit_intent_sensitivity' => 'medium',
            'inactivity_enabled' => true,
            'inactivity_timeout' => self::DEFAULT_INACTIVITY_TIMEOUT,
            'scroll_depth_enabled' => true,
            'scroll_threshold' => self::DEFAULT_SCROLL_THRESHOLD,
            'time_spent_enabled' => false,
            'min_time_spent' => 120000,
            'page_specific_enabled' => true,
            'cart_abandonment_enabled' => true,
            'min_cart_value' => 50,
            'product_interest_enabled' => true,
            'product_interest_time' => 90000,
            'trigger_messages' => $this->getDefaultMessages()
        ];

        $this->settings = wp_parse_args(
            get_option('woo_ai_assistant_proactive_triggers', []),
            $defaults
        );
    }

    /**
     * Get default trigger messages
     *
     * @since 1.0.0
     * @return array Default messages
     */
    private function getDefaultMessages(): array
    {
        return [
            'exit_intent' => [
                'general' => __('Wait! Before you go, do you need help finding anything?', 'woo-ai-assistant'),
                'product' => __('Having second thoughts about this product? Let me help you decide!', 'woo-ai-assistant'),
                'cart' => __('Don\'t forget your items! Need help completing your order?', 'woo-ai-assistant'),
                'checkout' => __('Having trouble with checkout? I\'m here to help!', 'woo-ai-assistant')
            ],
            'inactivity' => [
                'general' => __('Hi {user_name}! Can I help you find something specific?', 'woo-ai-assistant'),
                'product' => __('Still looking at this product? I can answer any questions you have!', 'woo-ai-assistant'),
                'shop' => __('Need help browsing our products? I can help you find exactly what you\'re looking for!', 'woo-ai-assistant')
            ],
            'scroll_depth' => [
                'general' => __('You\'ve seen a lot! Any questions about what you\'ve found?', 'woo-ai-assistant'),
                'product' => __('You\'ve read through the details! Ready to make a decision or have questions?', 'woo-ai-assistant')
            ],
            'time_spent' => [
                'general' => __('You\'ve been browsing for a while. Can I help you find what you need?', 'woo-ai-assistant')
            ],
            'page_specific' => [
                'general' => __('Hi! I\'m here to help with any questions about {site_name}.', 'woo-ai-assistant')
            ],
            'cart_abandonment' => [
                'general' => __('You have {cart_count} items in your cart worth {cart_value}. Need help completing your order?', 'woo-ai-assistant')
            ],
            'product_interest' => [
                'general' => __('Interested in {product_name}? I can answer any questions or help you find similar products!', 'woo-ai-assistant')
            ]
        ];
    }

    /**
     * Initialize page-specific rules
     *
     * @since 1.0.0
     * @return void
     */
    private function initializePageRules(): void
    {
        $this->pageRules = apply_filters('woo_ai_assistant_page_rules', [
            'product' => [
                'enabled' => true,
                'priority' => 'high',
                'conditions' => [
                    'time_on_page' => 60000, // 1 minute
                    'scroll_percent' => 50
                ]
            ],
            'cart' => [
                'enabled' => true,
                'priority' => 'high',
                'conditions' => [
                    'min_items' => 1,
                    'time_on_page' => 30000 // 30 seconds
                ]
            ],
            'checkout' => [
                'enabled' => true,
                'priority' => 'critical',
                'conditions' => [
                    'time_on_page' => 45000 // 45 seconds
                ]
            ],
            'shop' => [
                'enabled' => true,
                'priority' => 'medium',
                'conditions' => [
                    'scroll_percent' => 75,
                    'time_on_page' => 90000 // 1.5 minutes
                ]
            ]
        ]);
    }

    /**
     * Get frontend trigger configuration
     *
     * @since 1.0.0
     * @return array Configuration for frontend
     */
    private function getFrontendTriggerConfig(): array
    {
        if (!$this->areTrigggersEnabled()) {
            return ['enabled' => false];
        }

        return [
            'enabled' => true,
            'exit_intent' => [
                'enabled' => $this->settings['exit_intent_enabled'],
                'sensitivity' => $this->settings['exit_intent_sensitivity']
            ],
            'inactivity' => [
                'enabled' => $this->settings['inactivity_enabled'],
                'timeout' => $this->settings['inactivity_timeout']
            ],
            'scroll_depth' => [
                'enabled' => $this->settings['scroll_depth_enabled'],
                'threshold' => $this->settings['scroll_threshold']
            ],
            'time_spent' => [
                'enabled' => $this->settings['time_spent_enabled'],
                'min_time' => $this->settings['min_time_spent']
            ],
            'page_specific' => [
                'enabled' => $this->settings['page_specific_enabled']
            ],
            'cart_abandonment' => [
                'enabled' => $this->settings['cart_abandonment_enabled'] && Utils::isWooCommerceActive(),
                'min_value' => $this->settings['min_cart_value']
            ],
            'product_interest' => [
                'enabled' => $this->settings['product_interest_enabled'] && Utils::isWooCommerceActive(),
                'time_threshold' => $this->settings['product_interest_time']
            ]
        ];
    }

    /**
     * Get current page information
     *
     * @since 1.0.0
     * @return array Page information
     */
    private function getCurrentPageInfo(): array
    {
        global $post;

        $pageInfo = [
            'page_id' => $post ? $post->ID : 0,
            'page_type' => 'general',
            'page_title' => wp_get_document_title(),
            'page_url' => home_url(add_query_arg(null, null))
        ];

        // Determine page type
        if (is_product()) {
            $pageInfo['page_type'] = 'product';
            $pageInfo['product_id'] = get_the_ID();
        } elseif (is_cart()) {
            $pageInfo['page_type'] = 'cart';
        } elseif (is_checkout()) {
            $pageInfo['page_type'] = 'checkout';
        } elseif (is_shop()) {
            $pageInfo['page_type'] = 'shop';
        } elseif (is_product_category()) {
            $pageInfo['page_type'] = 'category';
        } elseif (is_account_page()) {
            $pageInfo['page_type'] = 'account';
        }

        return $pageInfo;
    }

    /**
     * Get session ID
     *
     * @since 1.0.0
     * @return string Session identifier
     */
    private function getSessionId(): string
    {
        if (!session_id()) {
            session_start();
        }

        return session_id() ?: wp_generate_uuid4();
    }

    /**
     * Check if triggers are enabled
     *
     * @since 1.0.0
     * @return bool True if enabled
     */
    private function areTrigggersEnabled(): bool
    {
        // Check global setting
        if (!$this->settings['enabled']) {
            return false;
        }

        // Check user plan restrictions
        $licenseManager = \WooAiAssistant\Main::getInstance()->getComponent('license_manager');
        if ($licenseManager && method_exists($licenseManager, 'hasFeature')) {
            return $licenseManager->hasFeature('proactive_triggers');
        }

        return true;
    }

    /**
     * Get REST API arguments for trigger events
     *
     * @since 1.0.0
     * @return array Arguments schema
     */
    private function getTriggerEventArgs(): array
    {
        return [
            'trigger_type' => [
                'required' => true,
                'type' => 'string',
                'enum' => self::TRIGGER_TYPES,
                'description' => __('Type of trigger event', 'woo-ai-assistant')
            ],
            'trigger_data' => [
                'required' => true,
                'type' => 'object',
                'description' => __('Trigger-specific data', 'woo-ai-assistant')
            ],
            'page_context' => [
                'required' => true,
                'type' => 'object',
                'description' => __('Current page context', 'woo-ai-assistant')
            ]
        ];
    }

    /**
     * Get REST API arguments for trigger settings
     *
     * @since 1.0.0
     * @return array Arguments schema
     */
    private function getTriggerSettingsArgs(): array
    {
        return [
            'enabled' => [
                'type' => 'boolean',
                'description' => __('Enable/disable proactive triggers', 'woo-ai-assistant')
            ],
            'exit_intent_enabled' => [
                'type' => 'boolean',
                'description' => __('Enable exit intent detection', 'woo-ai-assistant')
            ],
            'inactivity_timeout' => [
                'type' => 'integer',
                'minimum' => 5000,
                'maximum' => 300000,
                'description' => __('Inactivity timeout in milliseconds', 'woo-ai-assistant')
            ],
            'scroll_threshold' => [
                'type' => 'integer',
                'minimum' => 10,
                'maximum' => 100,
                'description' => __('Scroll depth percentage threshold', 'woo-ai-assistant')
            ]
        ];
    }

    /**
     * Get page-specific rules for current context
     *
     * @since 1.0.0
     * @return array Page rules
     */
    private function getPageSpecificRules(): array
    {
        $pageInfo = $this->getCurrentPageInfo();
        $pageType = $pageInfo['page_type'];

        return $this->pageRules[$pageType] ?? [];
    }

    /**
     * Get user context information
     *
     * @since 1.0.0
     * @return array User context
     */
    private function getUserContext(): array
    {
        $user = wp_get_current_user();

        $context = [
            'is_logged_in' => $user->ID > 0,
            'user_id' => $user->ID,
            'display_name' => $user->display_name,
            'is_returning' => false,
            'has_cart' => false,
            'cart_count' => 0
        ];

        // WooCommerce-specific context
        if (Utils::isWooCommerceActive() && WC()->cart) {
            $context['has_cart'] = WC()->cart->get_cart_contents_count() > 0;
            $context['cart_count'] = WC()->cart->get_cart_contents_count();
        }

        // Check if returning visitor
        if (isset($_COOKIE['woo_ai_returning_visitor'])) {
            $context['is_returning'] = true;
        } else {
            // Set returning visitor cookie
            setcookie('woo_ai_returning_visitor', '1', time() + (30 * DAY_IN_SECONDS), '/');
        }

        return $context;
    }

    /**
     * Evaluate page rule conditions
     *
     * @since 1.0.0
     * @param array $rule Page rule configuration
     * @param array $triggerData Trigger data
     * @param array $pageContext Page context
     * @return bool True if conditions are met
     */
    private function evaluatePageRule(array $rule, array $triggerData, array $pageContext): bool
    {
        if (!isset($rule['conditions']) || !is_array($rule['conditions'])) {
            return true;
        }

        foreach ($rule['conditions'] as $condition => $threshold) {
            switch ($condition) {
                case 'time_on_page':
                    if (($triggerData['time_on_page'] ?? 0) < $threshold) {
                        return false;
                    }
                    break;

                case 'scroll_percent':
                    if (($triggerData['scroll_percent'] ?? 0) < $threshold) {
                        return false;
                    }
                    break;

                case 'min_items':
                    if (Utils::isWooCommerceActive() && WC()->cart) {
                        if (WC()->cart->get_cart_contents_count() < $threshold) {
                            return false;
                        }
                    }
                    break;
            }
        }

        return true;
    }

    /**
     * Generate unique trigger ID
     *
     * @since 1.0.0
     * @param string $triggerType Trigger type
     * @return string Unique trigger ID
     */
    private function generateTriggerId(string $triggerType): string
    {
        return 'trigger_' . $triggerType . '_' . time() . '_' . wp_rand(1000, 9999);
    }

    /**
     * Update trigger statistics
     *
     * @since 1.0.0
     * @param string $triggerType Trigger type
     * @return void
     */
    private function updateTriggerStats(string $triggerType): void
    {
        $stats = get_option('woo_ai_assistant_trigger_stats', []);

        if (!isset($stats[$triggerType])) {
            $stats[$triggerType] = [
                'total_triggers' => 0,
                'last_triggered' => null
            ];
        }

        $stats[$triggerType]['total_triggers']++;
        $stats[$triggerType]['last_triggered'] = current_time('mysql');
        $stats['last_updated'] = current_time('mysql');

        update_option('woo_ai_assistant_trigger_stats', $stats);
    }

    /**
     * Ensure trigger events table exists
     *
     * @since 1.0.0
     * @return void
     */
    private function ensureTriggerEventsTable(): void
    {
        global $wpdb;

        $tableName = $wpdb->prefix . 'woo_ai_trigger_events';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$tableName}'") != $tableName) {
            $charset = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE {$tableName} (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                trigger_type varchar(50) NOT NULL,
                trigger_data longtext,
                page_context longtext,
                user_id bigint(20) UNSIGNED DEFAULT 0,
                session_id varchar(100) DEFAULT '',
                ip_address varchar(45) DEFAULT '',
                user_agent text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY trigger_type (trigger_type),
                KEY user_id (user_id),
                KEY session_id (session_id),
                KEY created_at (created_at)
            ) {$charset};";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    /**
     * Admin initialization
     *
     * @since 1.0.0
     * @return void
     */
    public function adminInit(): void
    {
        // Admin-specific initialization for trigger management
        Utils::logDebug('ProactiveTriggers admin initialization');
    }

    /**
     * Handle add to cart event
     *
     * @since 1.0.0
     * @param string $cartItemKey Cart item key
     * @param int $productId Product ID
     * @param int $quantity Quantity
     * @param int $variationId Variation ID
     * @param array $variation Variation data
     * @param array $cartItemData Cart item data
     * @return void
     */
    public function onAddToCart(string $cartItemKey, int $productId, int $quantity, int $variationId, array $variation, array $cartItemData): void
    {
        // This could trigger a follow-up engagement
        do_action('woo_ai_assistant_cart_updated', 'add', $productId, $quantity);
    }

    /**
     * Handle cart item removed event
     *
     * @since 1.0.0
     * @param string $cartItemKey Removed cart item key
     * @param \WC_Cart $cart Cart object
     * @return void
     */
    public function onCartItemRemoved(string $cartItemKey, \WC_Cart $cart): void
    {
        // This could trigger a retention attempt
        do_action('woo_ai_assistant_cart_updated', 'remove', $cartItemKey);
    }

    /**
     * Handle checkout process
     *
     * @since 1.0.0
     * @return void
     */
    public function onCheckoutProcess(): void
    {
        // This could trigger checkout assistance
        do_action('woo_ai_assistant_checkout_started');
    }

    /**
     * Customize trigger message filter callback
     *
     * @since 1.0.0
     * @param string $message Original message
     * @param string $triggerType Trigger type
     * @param array $pageContext Page context
     * @param array $extraData Extra data
     * @return string Customized message
     */
    public function customizeTriggerMessage(string $message, string $triggerType, array $pageContext, array $extraData = []): string
    {
        // Allow external customization of trigger messages
        return $message;
    }

    /**
     * Get trigger statistics (admin endpoint)
     *
     * @since 1.0.0
     * @param WP_REST_Request $request REST request
     * @return WP_REST_Response Response with stats
     */
    public function getTriggerStats(WP_REST_Request $request)
    {
        $stats = get_option('woo_ai_assistant_trigger_stats', []);

        return new WP_REST_Response([
            'success' => true,
            'stats' => $stats,
            'summary' => $this->generateStatsSummary($stats)
        ], 200);
    }

    /**
     * Update trigger settings (admin endpoint)
     *
     * @since 1.0.0
     * @param WP_REST_Request $request REST request
     * @return WP_REST_Response Response
     */
    public function updateTriggerSettings(WP_REST_Request $request)
    {
        $newSettings = $request->get_params();

        // Sanitize and validate settings
        $sanitizedSettings = $this->sanitizeSettings($newSettings);

        // Update settings
        $this->settings = wp_parse_args($sanitizedSettings, $this->settings);
        update_option('woo_ai_assistant_proactive_triggers', $this->settings);

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Trigger settings updated successfully.', 'woo-ai-assistant'),
            'settings' => $this->settings
        ], 200);
    }

    /**
     * Sanitize trigger settings
     *
     * @since 1.0.0
     * @param array $settings Raw settings
     * @return array Sanitized settings
     */
    private function sanitizeSettings(array $settings): array
    {
        $sanitized = [];

        // Boolean settings
        $boolSettings = [
            'enabled', 'exit_intent_enabled', 'inactivity_enabled',
            'scroll_depth_enabled', 'time_spent_enabled',
            'page_specific_enabled', 'cart_abandonment_enabled',
            'product_interest_enabled'
        ];

        foreach ($boolSettings as $key) {
            if (isset($settings[$key])) {
                $sanitized[$key] = (bool) $settings[$key];
            }
        }

        // Integer settings with bounds
        $intSettings = [
            'inactivity_timeout' => [5000, 300000],
            'scroll_threshold' => [10, 100],
            'min_time_spent' => [30000, 600000],
            'min_cart_value' => [0, 10000],
            'product_interest_time' => [30000, 300000]
        ];

        foreach ($intSettings as $key => $bounds) {
            if (isset($settings[$key])) {
                $value = intval($settings[$key]);
                $sanitized[$key] = max($bounds[0], min($bounds[1], $value));
            }
        }

        // String settings
        if (isset($settings['exit_intent_sensitivity'])) {
            $validSensitivities = ['low', 'medium', 'high'];
            if (in_array($settings['exit_intent_sensitivity'], $validSensitivities, true)) {
                $sanitized['exit_intent_sensitivity'] = $settings['exit_intent_sensitivity'];
            }
        }

        return $sanitized;
    }

    /**
     * Generate statistics summary
     *
     * @since 1.0.0
     * @param array $stats Raw statistics
     * @return array Statistics summary
     */
    private function generateStatsSummary(array $stats): array
    {
        $totalTriggers = 0;
        $mostActive = null;
        $mostActiveCount = 0;

        foreach ($stats as $triggerType => $data) {
            if ($triggerType === 'last_updated') {
                continue;
            }

            $count = $data['total_triggers'] ?? 0;
            $totalTriggers += $count;

            if ($count > $mostActiveCount) {
                $mostActiveCount = $count;
                $mostActive = $triggerType;
            }
        }

        return [
            'total_triggers' => $totalTriggers,
            'most_active_trigger' => $mostActive,
            'most_active_count' => $mostActiveCount,
            'last_updated' => $stats['last_updated'] ?? null
        ];
    }
}
