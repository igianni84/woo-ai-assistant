<?php

/**
 * Default Message Setup Class
 *
 * Configures initial conversation messages, welcome messages, fallback responses,
 * and default triggers for immediate functionality after plugin activation.
 * Implements the zero-config philosophy by providing ready-to-use conversation flows.
 *
 * @package WooAiAssistant
 * @subpackage Setup
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Setup;

use WooAiAssistant\Common\Utils;
use WooAiAssistant\Common\Traits\Singleton;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class DefaultMessageSetup
 *
 * Sets up default conversation messages and triggers for immediate
 * functionality after plugin activation.
 *
 * @since 1.0.0
 */
class DefaultMessageSetup
{
    use Singleton;

    /**
     * Default welcome messages
     *
     * @since 1.0.0
     * @var array
     */
    private array $welcomeMessages = [];

    /**
     * Default fallback responses
     *
     * @since 1.0.0
     * @var array
     */
    private array $fallbackResponses = [];

    /**
     * Default conversation starters
     *
     * @since 1.0.0
     * @var array
     */
    private array $conversationStarters = [];

    /**
     * Default help responses
     *
     * @since 1.0.0
     * @var array
     */
    private array $helpResponses = [];

    /**
     * Default proactive triggers
     *
     * @since 1.0.0
     * @var array
     */
    private array $proactiveTriggers = [];

    /**
     * Store information for personalization
     *
     * @since 1.0.0
     * @var array
     */
    private array $storeInfo = [];

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    private function __construct()
    {
        $this->initializeStoreInfo();
        $this->setupDefaultMessages();
    }

    /**
     * Setup initial configuration immediately after plugin activation
     *
     * This is the main method called during auto-installation to configure
     * all default messages and triggers for immediate functionality.
     *
     * @since 1.0.0
     * @return array Configuration results
     */
    public function setupInitialConfiguration(): array
    {
        try {
            Utils::logDebug('Setting up initial message configuration');

            $results = [
                'welcome_messages' => 0,
                'fallback_responses' => 0,
                'conversation_starters' => 0,
                'help_responses' => 0,
                'proactive_triggers' => 0,
                'status' => 'success'
            ];

            // Configure welcome messages
            $results['welcome_messages'] = $this->configureWelcomeMessages();

            // Configure fallback responses
            $results['fallback_responses'] = $this->configureFallbackResponses();

            // Configure conversation starters
            $results['conversation_starters'] = $this->configureConversationStarters();

            // Configure help responses
            $results['help_responses'] = $this->configureHelpResponses();

            // Configure proactive triggers (if enabled)
            $results['proactive_triggers'] = $this->configureProactiveTriggers();

            // Set up default conversation flow
            $this->setupConversationFlow();

            // Save configuration timestamp
            update_option('woo_ai_assistant_default_messages_configured_at', time());

            Utils::logDebug('Initial message configuration completed', $results);
            return $results;
        } catch (\Exception $e) {
            Utils::logError('Failed to setup initial message configuration: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Initialize store information for message personalization
     *
     * @since 1.0.0
     * @return void
     */
    private function initializeStoreInfo(): void
    {
        $this->storeInfo = [
            'name' => get_option('blogname', 'our store'),
            'url' => home_url(),
            'currency' => get_woocommerce_currency(),
            'currency_symbol' => get_woocommerce_currency_symbol(),
            'is_woocommerce_active' => Utils::isWooCommerceActive()
        ];
    }

    /**
     * Setup all default message templates
     *
     * @since 1.0.0
     * @return void
     */
    private function setupDefaultMessages(): void
    {
        $this->setupWelcomeMessages();
        $this->setupFallbackResponses();
        $this->setupConversationStarters();
        $this->setupHelpResponses();
        $this->setupProactiveTriggers();
    }

    /**
     * Setup default welcome messages
     *
     * @since 1.0.0
     * @return void
     */
    private function setupWelcomeMessages(): void
    {
        $storeName = $this->storeInfo['name'];

        $this->welcomeMessages = [
            'standard' => [
                'text' => sprintf(
                    __('Hi! Welcome to %s! How can I help you today?', 'woo-ai-assistant'),
                    $storeName
                ),
                'context' => 'general',
                'priority' => 10
            ],
            'product_page' => [
                'text' => __('Hi! I can help you learn more about this product or find something else you\'re looking for. What would you like to know?', 'woo-ai-assistant'),
                'context' => 'product',
                'priority' => 20
            ],
            'shop_page' => [
                'text' => __('Hello! I\'m here to help you find the perfect product. What are you looking for today?', 'woo-ai-assistant'),
                'context' => 'shop',
                'priority' => 20
            ],
            'cart_page' => [
                'text' => __('Hi there! I can help you with your cart, answer questions about shipping, or help you find additional items. How can I assist?', 'woo-ai-assistant'),
                'context' => 'cart',
                'priority' => 20
            ],
            'checkout_page' => [
                'text' => __('Hello! Having any issues with checkout? I\'m here to help with payment questions, shipping options, or anything else you need.', 'woo-ai-assistant'),
                'context' => 'checkout',
                'priority' => 20
            ],
            'account_page' => [
                'text' => __('Hi! I can help you with your account, orders, or any questions you might have. What do you need assistance with?', 'woo-ai-assistant'),
                'context' => 'account',
                'priority' => 20
            ]
        ];
    }

    /**
     * Setup default fallback responses
     *
     * @since 1.0.0
     * @return void
     */
    private function setupFallbackResponses(): void
    {
        $this->fallbackResponses = [
            'no_match_general' => [
                'text' => __('I\'m not sure about that specific question, but I\'d be happy to help you find what you\'re looking for. Could you try asking in a different way?', 'woo-ai-assistant'),
                'triggers' => ['no_knowledge_match', 'low_confidence'],
                'context' => 'general'
            ],
            'no_match_product' => [
                'text' => __('I don\'t have specific information about that, but I can help you with product details, pricing, availability, or similar products. What would you like to know?', 'woo-ai-assistant'),
                'triggers' => ['no_knowledge_match', 'low_confidence'],
                'context' => 'product'
            ],
            'technical_error' => [
                'text' => __('I\'m experiencing a temporary issue. Please try asking your question again, and I\'ll do my best to help you.', 'woo-ai-assistant'),
                'triggers' => ['api_error', 'technical_failure'],
                'context' => 'error'
            ],
            'too_complex' => [
                'text' => __('That\'s a detailed question! While I try to help with most things, you might want to contact our support team for more specific assistance. Can I help you with anything else in the meantime?', 'woo-ai-assistant'),
                'triggers' => ['complex_query', 'requires_human'],
                'context' => 'escalation'
            ],
            'inappropriate_content' => [
                'text' => __('I\'m here to help with shopping and product questions. Let\'s keep our conversation focused on how I can assist you with your shopping needs.', 'woo-ai-assistant'),
                'triggers' => ['content_filter', 'inappropriate'],
                'context' => 'moderation'
            ]
        ];
    }

    /**
     * Setup default conversation starters
     *
     * @since 1.0.0
     * @return void
     */
    private function setupConversationStarters(): void
    {
        $this->conversationStarters = [
            'browse_products' => [
                'text' => __('Browse our products', 'woo-ai-assistant'),
                'action' => 'browse_catalog',
                'icon' => '🛍️',
                'priority' => 10
            ],
            'find_product' => [
                'text' => __('Help me find something specific', 'woo-ai-assistant'),
                'action' => 'product_search',
                'icon' => '🔍',
                'priority' => 20
            ],
            'shipping_info' => [
                'text' => __('Shipping & delivery info', 'woo-ai-assistant'),
                'action' => 'shipping_help',
                'icon' => '🚚',
                'priority' => 30
            ],
            'order_status' => [
                'text' => __('Check my order status', 'woo-ai-assistant'),
                'action' => 'order_inquiry',
                'icon' => '📦',
                'priority' => 40
            ],
            'return_policy' => [
                'text' => __('Returns & exchanges', 'woo-ai-assistant'),
                'action' => 'return_help',
                'icon' => '↩️',
                'priority' => 50
            ],
            'contact_support' => [
                'text' => __('Contact customer support', 'woo-ai-assistant'),
                'action' => 'human_handoff',
                'icon' => '💬',
                'priority' => 60
            ]
        ];
    }

    /**
     * Setup default help responses
     *
     * @since 1.0.0
     * @return void
     */
    private function setupHelpResponses(): void
    {
        $storeName = $this->storeInfo['name'];

        $this->helpResponses = [
            'general_help' => [
                'text' => sprintf(
                    __('I\'m the AI assistant for %s. I can help you with:\n\n• Finding products\n• Product information and specs\n• Shipping and delivery\n• Order status and tracking\n• Returns and exchanges\n• Store policies\n\nJust ask me anything you\'d like to know!', 'woo-ai-assistant'),
                    $storeName
                ),
                'triggers' => ['help', 'what_can_you_do', 'capabilities']
            ],
            'product_help' => [
                'text' => __('I can help you with this product by providing:\n\n• Detailed specifications\n• Pricing and availability\n• Customer reviews and ratings\n• Compatible accessories\n• Similar product recommendations\n• Add to cart assistance\n\nWhat would you like to know?', 'woo-ai-assistant'),
                'context' => 'product',
                'triggers' => ['product_help', 'product_info']
            ],
            'shopping_help' => [
                'text' => __('I can assist you with shopping by:\n\n• Helping you find specific products\n• Comparing different options\n• Explaining product features\n• Checking availability and stock\n• Applying discount codes\n• Guiding you through checkout\n\nHow can I help you shop today?', 'woo-ai-assistant'),
                'context' => 'shopping',
                'triggers' => ['shopping_help', 'how_to_shop']
            ],
            'order_help' => [
                'text' => __('For order-related assistance, I can help with:\n\n• Tracking your order status\n• Explaining delivery timeframes\n• Modifying orders (if still processing)\n• Understanding our shipping policies\n• Returns and exchanges\n\nWhat do you need help with regarding your order?', 'woo-ai-assistant'),
                'context' => 'orders',
                'triggers' => ['order_help', 'tracking', 'delivery']
            ]
        ];
    }

    /**
     * Setup default proactive triggers
     *
     * @since 1.0.0
     * @return void
     */
    private function setupProactiveTriggers(): void
    {
        $this->proactiveTriggers = [
            'cart_abandonment' => [
                'trigger' => 'cart_inactive_2min',
                'message' => __('I noticed you have items in your cart. Need help with anything before you check out?', 'woo-ai-assistant'),
                'enabled' => false, // Disabled by default
                'delay' => 120,
                'context' => 'cart'
            ],
            'browse_long' => [
                'trigger' => 'browse_5min',
                'message' => __('Finding everything you need? I\'m here if you have any questions!', 'woo-ai-assistant'),
                'enabled' => false, // Disabled by default
                'delay' => 300,
                'context' => 'browsing'
            ],
            'product_view_long' => [
                'trigger' => 'product_view_2min',
                'message' => __('Interested in this product? I can answer any questions you might have about it.', 'woo-ai-assistant'),
                'enabled' => false, // Disabled by default
                'delay' => 120,
                'context' => 'product'
            ],
            'checkout_hesitation' => [
                'trigger' => 'checkout_inactive_1min',
                'message' => __('Having any issues with checkout? I can help with payment options, shipping questions, or anything else.', 'woo-ai-assistant'),
                'enabled' => false, // Disabled by default
                'delay' => 60,
                'context' => 'checkout'
            ],
            'return_visitor' => [
                'trigger' => 'return_visitor',
                'message' => __('Welcome back! I\'m here if you need help finding anything.', 'woo-ai-assistant'),
                'enabled' => false, // Disabled by default
                'delay' => 5,
                'context' => 'general'
            ]
        ];
    }

    /**
     * Configure welcome messages in WordPress options
     *
     * @since 1.0.0
     * @return int Number of welcome messages configured
     */
    private function configureWelcomeMessages(): int
    {
        try {
            // Set the default welcome message
            $defaultWelcome = $this->welcomeMessages['standard']['text'];
            update_option('woo_ai_assistant_welcome_message', $defaultWelcome);

            // Store all welcome message variants
            update_option('woo_ai_assistant_welcome_messages', $this->welcomeMessages);

            Utils::logDebug('Welcome messages configured', [
                'total_messages' => count($this->welcomeMessages),
                'default_message' => $defaultWelcome
            ]);

            return count($this->welcomeMessages);
        } catch (\Exception $e) {
            Utils::logError('Failed to configure welcome messages: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Configure fallback responses in WordPress options
     *
     * @since 1.0.0
     * @return int Number of fallback responses configured
     */
    private function configureFallbackResponses(): int
    {
        try {
            update_option('woo_ai_assistant_fallback_responses', $this->fallbackResponses);

            // Set the default fallback response
            $defaultFallback = $this->fallbackResponses['no_match_general']['text'];
            update_option('woo_ai_assistant_default_fallback', $defaultFallback);

            Utils::logDebug('Fallback responses configured', [
                'total_responses' => count($this->fallbackResponses)
            ]);

            return count($this->fallbackResponses);
        } catch (\Exception $e) {
            Utils::logError('Failed to configure fallback responses: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Configure conversation starters in WordPress options
     *
     * @since 1.0.0
     * @return int Number of conversation starters configured
     */
    private function configureConversationStarters(): int
    {
        try {
            update_option('woo_ai_assistant_conversation_starters', $this->conversationStarters);
            update_option('woo_ai_assistant_show_conversation_starters', 'yes');

            Utils::logDebug('Conversation starters configured', [
                'total_starters' => count($this->conversationStarters)
            ]);

            return count($this->conversationStarters);
        } catch (\Exception $e) {
            Utils::logError('Failed to configure conversation starters: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Configure help responses in WordPress options
     *
     * @since 1.0.0
     * @return int Number of help responses configured
     */
    private function configureHelpResponses(): int
    {
        try {
            update_option('woo_ai_assistant_help_responses', $this->helpResponses);

            Utils::logDebug('Help responses configured', [
                'total_responses' => count($this->helpResponses)
            ]);

            return count($this->helpResponses);
        } catch (\Exception $e) {
            Utils::logError('Failed to configure help responses: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Configure proactive triggers (disabled by default for zero-friction)
     *
     * @since 1.0.0
     * @return int Number of proactive triggers configured
     */
    private function configureProactiveTriggers(): int
    {
        try {
            // Store trigger templates but keep them disabled by default
            update_option('woo_ai_assistant_proactive_triggers_templates', $this->proactiveTriggers);
            update_option('woo_ai_assistant_proactive_triggers_enabled', 'no');

            Utils::logDebug('Proactive triggers configured (disabled)', [
                'total_triggers' => count($this->proactiveTriggers)
            ]);

            return count($this->proactiveTriggers);
        } catch (\Exception $e) {
            Utils::logError('Failed to configure proactive triggers: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Setup conversation flow patterns
     *
     * @since 1.0.0
     * @return void
     */
    private function setupConversationFlow(): void
    {
        try {
            $conversationFlow = [
                'greeting_patterns' => [
                    'hello', 'hi', 'hey', 'good morning', 'good afternoon', 'good evening'
                ],
                'thanks_patterns' => [
                    'thank', 'thanks', 'appreciate', 'grateful'
                ],
                'goodbye_patterns' => [
                    'bye', 'goodbye', 'see you', 'talk to you later', 'have a good day'
                ],
                'help_patterns' => [
                    'help', 'assist', 'support', 'what can you do', 'how do you work'
                ],
                'product_patterns' => [
                    'product', 'item', 'buy', 'purchase', 'price', 'cost', 'availability'
                ],
                'shipping_patterns' => [
                    'shipping', 'delivery', 'when will it arrive', 'how long', 'tracking'
                ],
                'return_patterns' => [
                    'return', 'refund', 'exchange', 'not satisfied', 'defective'
                ]
            ];

            update_option('woo_ai_assistant_conversation_flow', $conversationFlow);

            // Set up response confidence thresholds
            $responseSettings = [
                'high_confidence_threshold' => 0.8,
                'medium_confidence_threshold' => 0.6,
                'low_confidence_threshold' => 0.4,
                'fallback_threshold' => 0.4,
                'escalation_threshold' => 0.3
            ];

            update_option('woo_ai_assistant_response_settings', $responseSettings);

            Utils::logDebug('Conversation flow patterns configured');
        } catch (\Exception $e) {
            Utils::logError('Failed to setup conversation flow: ' . $e->getMessage());
        }
    }

    /**
     * Get welcome message for specific context
     *
     * @since 1.0.0
     * @param string $context Context (product, shop, cart, etc.)
     * @return string Welcome message
     */
    public function getWelcomeMessage(string $context = 'general'): string
    {
        $messages = get_option('woo_ai_assistant_welcome_messages', $this->welcomeMessages);

        // Try to get context-specific message
        foreach ($messages as $key => $message) {
            if (($message['context'] ?? 'general') === $context) {
                return $message['text'];
            }
        }

        // Fall back to standard message
        return $messages['standard']['text'] ??
               __('Hi! How can I help you today?', 'woo-ai-assistant');
    }

    /**
     * Get fallback response for specific trigger
     *
     * @since 1.0.0
     * @param string $trigger Trigger type
     * @param string $context Context
     * @return string Fallback response
     */
    public function getFallbackResponse(string $trigger = 'no_match_general', string $context = 'general'): string
    {
        $responses = get_option('woo_ai_assistant_fallback_responses', $this->fallbackResponses);

        // Try to find exact match
        if (isset($responses[$trigger])) {
            return $responses[$trigger]['text'];
        }

        // Try to find by context
        foreach ($responses as $response) {
            if (($response['context'] ?? 'general') === $context) {
                return $response['text'];
            }
        }

        // Default fallback
        return $responses['no_match_general']['text'] ??
               __('I\'m not sure about that. Could you try asking in a different way?', 'woo-ai-assistant');
    }

    /**
     * Get conversation starters for display
     *
     * @since 1.0.0
     * @param int $limit Maximum number of starters to return
     * @return array Array of conversation starters
     */
    public function getConversationStarters(int $limit = 6): array
    {
        if (get_option('woo_ai_assistant_show_conversation_starters', 'yes') !== 'yes') {
            return [];
        }

        $starters = get_option('woo_ai_assistant_conversation_starters', $this->conversationStarters);

        // Sort by priority
        uasort($starters, function ($a, $b) {
            return ($a['priority'] ?? 50) <=> ($b['priority'] ?? 50);
        });

        return array_slice($starters, 0, $limit, true);
    }

    /**
     * Check if default messages are configured
     *
     * @since 1.0.0
     * @return bool True if messages are configured
     */
    public function isConfigured(): bool
    {
        return (bool) get_option('woo_ai_assistant_default_messages_configured_at', false);
    }

    /**
     * Get configuration timestamp
     *
     * @since 1.0.0
     * @return int|false Configuration timestamp or false if not configured
     */
    public function getConfigurationTime()
    {
        return get_option('woo_ai_assistant_default_messages_configured_at', false);
    }

    /**
     * Reset all default messages to original state
     *
     * @since 1.0.0
     * @return bool True if reset successfully
     */
    public function resetToDefaults(): bool
    {
        try {
            // Re-setup all default messages
            $this->setupDefaultMessages();

            // Clear configured flag to force reconfiguration
            delete_option('woo_ai_assistant_default_messages_configured_at');

            // Reconfigure with fresh data
            $this->setupInitialConfiguration();

            Utils::logDebug('Default messages reset to original configuration');
            return true;
        } catch (\Exception $e) {
            Utils::logError('Failed to reset default messages: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update welcome message
     *
     * @since 1.0.0
     * @param string $message New welcome message
     * @param string $context Context for the message
     * @return bool True if updated successfully
     */
    public function updateWelcomeMessage(string $message, string $context = 'standard'): bool
    {
        try {
            $messages = get_option('woo_ai_assistant_welcome_messages', []);

            $messages[$context] = [
                'text' => $message,
                'context' => $context,
                'priority' => $messages[$context]['priority'] ?? 10,
                'updated_at' => current_time('mysql')
            ];

            update_option('woo_ai_assistant_welcome_messages', $messages);

            // Update main welcome message if it's the standard one
            if ($context === 'standard') {
                update_option('woo_ai_assistant_welcome_message', $message);
            }

            Utils::logDebug("Welcome message updated for context: {$context}");
            return true;
        } catch (\Exception $e) {
            Utils::logError('Failed to update welcome message: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get message setup statistics
     *
     * @since 1.0.0
     * @return array Statistics about configured messages
     */
    public function getSetupStatistics(): array
    {
        return [
            'is_configured' => $this->isConfigured(),
            'configured_at' => $this->getConfigurationTime(),
            'welcome_messages_count' => count(get_option('woo_ai_assistant_welcome_messages', [])),
            'fallback_responses_count' => count(get_option('woo_ai_assistant_fallback_responses', [])),
            'conversation_starters_count' => count(get_option('woo_ai_assistant_conversation_starters', [])),
            'help_responses_count' => count(get_option('woo_ai_assistant_help_responses', [])),
            'proactive_triggers_enabled' => get_option('woo_ai_assistant_proactive_triggers_enabled', 'no') === 'yes',
            'conversation_starters_enabled' => get_option('woo_ai_assistant_show_conversation_starters', 'yes') === 'yes'
        ];
    }
}
