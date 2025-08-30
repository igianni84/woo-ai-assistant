<?php

/**
 * Plugin Installer Class
 *
 * Handles first-time installation setup including initial data population,
 * zero-config knowledge base indexing, and welcome content creation.
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
use WooAiAssistant\Database\Schema;
use WooAiAssistant\Database\Migrations;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Installer
 *
 * Provides zero-configuration first-time setup for the Woo AI Assistant plugin.
 * This class implements the "zero-friction" philosophy by setting up everything
 * needed for the plugin to work immediately after activation.
 *
 * @since 1.0.0
 */
class Installer
{
    /**
     * Installation result data
     *
     * @var array
     */
    private $installationResult = [
        'success' => true,
        'installed' => [],
        'errors' => [],
        'warnings' => []
    ];

    /**
     * Run complete installation process
     *
     * Executes all first-time setup tasks including:
     * - Initial settings population
     * - Sample data creation
     * - Knowledge base initial indexing
     * - Welcome content setup
     *
     * @return array Installation result with success status and details
     */
    public function install(): array
    {
        Logger::info('Starting zero-config installation process');

        try {
            // Step 1: Populate initial settings in the database
            $this->populateInitialSettings();

            // Step 2: Create sample knowledge base entries
            $this->createSampleKnowledgeBase();

            // Step 3: Setup default widget configuration
            $this->setupDefaultWidgetConfiguration();

            // Step 4: Initialize analytics tracking
            $this->initializeAnalytics();

            // Step 5: Schedule initial knowledge base indexing
            $this->scheduleInitialIndexing();

            // Step 6: Create welcome conversation template
            $this->createWelcomeConversation();

            // Step 7: Setup default AI prompts and responses
            $this->setupDefaultAIPrompts();

            // Step 8: Validate installation
            $this->validateInstallation();

            Logger::info('Zero-config installation completed successfully', [
                'installed_components' => $this->installationResult['installed']
            ]);
        } catch (\Exception $e) {
            $this->installationResult['success'] = false;
            $this->installationResult['errors'][] = $e->getMessage();

            Logger::error('Installation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return $this->installationResult;
    }

    /**
     * Populate initial settings in the woo_ai_settings table
     *
     * These settings provide immediate functionality without requiring
     * any configuration from the user.
     *
     * @throws \Exception If database operations fail
     * @return void
     */
    private function populateInitialSettings(): void
    {
        Logger::info('Populating initial settings');

        global $wpdb;
        $settingsTable = $wpdb->prefix . 'woo_ai_settings';

        // Core functional settings for zero-config operation
        $initialSettings = [
            // System settings
            'plugin_version' => WOO_AI_ASSISTANT_VERSION,
            'installation_date' => current_time('mysql'),
            'installation_id' => wp_generate_uuid4(),

            // Widget configuration - immediately functional
            'widget_enabled' => '1',
            'widget_position' => 'bottom-right',
            'widget_theme' => 'modern',
            'widget_color_primary' => '#007cba',
            'widget_color_secondary' => '#ffffff',
            'widget_welcome_message' => 'Hi! How can I help you today?',
            'widget_placeholder_text' => 'Type your message here...',

            // AI configuration with sensible defaults
            'ai_model' => 'gemini-2.0-flash-exp',
            'ai_temperature' => '0.7',
            'ai_max_tokens' => '1000',
            'ai_response_style' => 'helpful',
            'ai_personality' => 'friendly',

            // Conversation limits for free tier
            'max_conversations_per_session' => '10',
            'conversation_timeout_minutes' => '30',
            'max_message_length' => '2000',
            'enable_conversation_history' => '1',

            // Knowledge base settings
            'kb_auto_sync_enabled' => '1',
            'kb_sync_interval_hours' => '24',
            'kb_include_products' => '1',
            'kb_include_pages' => '1',
            'kb_include_posts' => '1',
            'kb_chunk_size' => '1000',
            'kb_overlap_size' => '100',

            // Analytics and logging
            'enable_analytics' => '1',
            'enable_logging' => '1',
            'analytics_retention_days' => '90',
            'log_retention_days' => '30',

            // Feature flags - progressive enablement
            'proactive_triggers_enabled' => '1',
            'coupon_generation_enabled' => '1',
            'product_recommendations_enabled' => '1',
            'order_tracking_enabled' => '1',

            // Performance settings
            'cache_enabled' => '1',
            'cache_ttl_seconds' => '3600',
            'rate_limit_enabled' => '1',
            'rate_limit_per_minute' => '10',

            // Privacy and compliance
            'gdpr_compliance_enabled' => '1',
            'data_retention_enabled' => '1',
            'anonymous_analytics' => '1',
        ];

        $insertedCount = 0;
        $skippedCount = 0;

        foreach ($initialSettings as $key => $value) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT setting_value FROM {$settingsTable} WHERE setting_key = %s",
                $key
            ));

            if ($existing === null) {
                $result = $wpdb->insert(
                    $settingsTable,
                    [
                        'setting_key' => $key,
                        'setting_value' => $value,
                        'setting_group' => $this->getSettingGroup($key),
                        'is_sensitive' => $this->isSensitiveSetting($key) ? 1 : 0,
                        'autoload' => $this->shouldAutoloadSetting($key) ? 1 : 0,
                    ],
                    ['%s', '%s', '%s', '%d', '%d']
                );

                if ($result === false) {
                    throw new \Exception("Failed to insert setting: {$key}. Error: " . $wpdb->last_error);
                }

                $insertedCount++;
            } else {
                $skippedCount++;
            }
        }

        Logger::info('Initial settings populated', [
            'inserted' => $insertedCount,
            'skipped' => $skippedCount,
            'total' => count($initialSettings)
        ]);

        $this->installationResult['installed'][] = "Initial settings ({$insertedCount} new)";
    }

    /**
     * Create sample knowledge base entries for immediate functionality
     *
     * @throws \Exception If knowledge base creation fails
     * @return void
     */
    private function createSampleKnowledgeBase(): void
    {
        Logger::info('Creating sample knowledge base entries');

        global $wpdb;
        $kbTable = $wpdb->prefix . 'woo_ai_knowledge_base';

        // Sample knowledge base entries that work immediately
        $sampleEntries = [
            [
                'content_type' => 'faq',
                'chunk_text' => 'Our store offers a wide range of high-quality products. We specialize in providing excellent customer service and fast shipping. All orders are processed within 24 hours.',
                'metadata' => json_encode(['topic' => 'general', 'priority' => 'high']),
                'word_count' => 25,
                'is_active' => 1
            ],
            [
                'content_type' => 'policy',
                'chunk_text' => 'We offer a 30-day return policy on all items. Items must be in original condition with tags attached. Return shipping is free for defective items.',
                'metadata' => json_encode(['topic' => 'returns', 'priority' => 'high']),
                'word_count' => 22,
                'is_active' => 1
            ],
            [
                'content_type' => 'shipping',
                'chunk_text' => 'We provide free standard shipping on orders over $50. Express shipping options are available. International shipping is available to most countries.',
                'metadata' => json_encode(['topic' => 'shipping', 'priority' => 'high']),
                'word_count' => 21,
                'is_active' => 1
            ],
            [
                'content_type' => 'support',
                'chunk_text' => 'Our customer support team is available Monday through Friday, 9 AM to 5 PM. You can contact us via email, phone, or live chat for immediate assistance.',
                'metadata' => json_encode(['topic' => 'contact', 'priority' => 'high']),
                'word_count' => 26,
                'is_active' => 1
            ]
        ];

        $insertedCount = 0;

        foreach ($sampleEntries as $entry) {
            $entry['chunk_hash'] = md5($entry['chunk_text']);
            $entry['updated_at'] = current_time('mysql');
            $entry['embedding_model'] = 'initial-setup';

            $result = $wpdb->insert($kbTable, $entry);

            if ($result === false) {
                throw new \Exception("Failed to create sample KB entry. Error: " . $wpdb->last_error);
            }

            $insertedCount++;
        }

        Logger::info('Sample knowledge base entries created', [
            'entries_created' => $insertedCount
        ]);

        $this->installationResult['installed'][] = "Sample knowledge base ({$insertedCount} entries)";
    }

    /**
     * Setup default widget configuration for immediate use
     *
     * @return void
     */
    private function setupDefaultWidgetConfiguration(): void
    {
        Logger::info('Setting up default widget configuration');

        // Widget appears immediately after installation
        update_option('woo_ai_assistant_widget_ready', true);
        update_option('woo_ai_assistant_widget_first_load', true);

        // Default welcome messages for different pages
        $welcomeMessages = [
            'product' => 'Looking for details about this product? I can help!',
            'shop' => 'Need help finding the perfect product? Ask me anything!',
            'cart' => 'Questions about your cart or checkout? I\'m here to help!',
            'checkout' => 'Need assistance with your order? Let me know!',
            'account' => 'Questions about your account or orders? Just ask!',
            'default' => 'Hi! How can I help you today?'
        ];

        update_option('woo_ai_assistant_welcome_messages', $welcomeMessages);

        Logger::info('Default widget configuration completed');
        $this->installationResult['installed'][] = 'Widget configuration';
    }

    /**
     * Initialize analytics tracking
     *
     * @return void
     */
    private function initializeAnalytics(): void
    {
        Logger::info('Initializing analytics tracking');

        global $wpdb;
        $analyticsTable = $wpdb->prefix . 'woo_ai_analytics';

        // Record installation analytics
        $initialMetrics = [
            [
                'metric_type' => 'installation_completed',
                'metric_value' => 1,
                'context' => json_encode([
                    'wp_version' => get_bloginfo('version'),
                    'php_version' => PHP_VERSION,
                    'plugin_version' => WOO_AI_ASSISTANT_VERSION,
                    'woocommerce_version' => Utils::getWooCommerceVersion(),
                    'installation_date' => current_time('mysql')
                ]),
                'source' => 'installer'
            ]
        ];

        foreach ($initialMetrics as $metric) {
            $metric['created_at'] = current_time('mysql');
            $wpdb->insert($analyticsTable, $metric);
        }

        Logger::info('Analytics tracking initialized');
        $this->installationResult['installed'][] = 'Analytics tracking';
    }

    /**
     * Schedule initial knowledge base indexing
     *
     * @return void
     */
    private function scheduleInitialIndexing(): void
    {
        Logger::info('Scheduling initial knowledge base indexing');

        // Schedule immediate indexing (next cron run)
        if (!wp_next_scheduled('woo_ai_assistant_initial_indexing')) {
            wp_schedule_single_event(time() + 60, 'woo_ai_assistant_initial_indexing');
        }

        // Schedule regular daily indexing
        if (!wp_next_scheduled('woo_ai_assistant_daily_index')) {
            wp_schedule_event(time() + 3600, 'daily', 'woo_ai_assistant_daily_index');
        }

        update_option('woo_ai_assistant_indexing_scheduled', true);

        Logger::info('Initial indexing scheduled');
        $this->installationResult['installed'][] = 'Knowledge base indexing schedule';
    }

    /**
     * Create welcome conversation template
     *
     * @return void
     */
    private function createWelcomeConversation(): void
    {
        Logger::info('Creating welcome conversation template');

        global $wpdb;
        $conversationTable = $wpdb->prefix . 'woo_ai_conversations';
        $messagesTable = $wpdb->prefix . 'woo_ai_messages';

        // Create a template conversation for demonstration
        $welcomeConversation = [
            'user_id' => null, // System conversation
            'session_id' => 'welcome-template-' . wp_generate_uuid4(),
            'status' => 'template',
            'context_data' => json_encode(['type' => 'welcome_template']),
            'total_messages' => 2
        ];

        $conversationId = $wpdb->insert($conversationTable, $welcomeConversation);

        if ($conversationId === false) {
            Logger::warning('Failed to create welcome conversation template');
            return;
        }

        $conversationId = $wpdb->insert_id;

        // Add sample messages
        $welcomeMessages = [
            [
                'conversation_id' => $conversationId,
                'role' => 'assistant',
                'content' => 'Welcome to our store! I\'m your AI shopping assistant. I can help you find products, answer questions about shipping and returns, and assist with your orders. What would you like to know?',
                'metadata' => json_encode(['type' => 'welcome', 'template' => true])
            ],
            [
                'conversation_id' => $conversationId,
                'role' => 'user',
                'content' => 'How does the AI assistant work?',
                'metadata' => json_encode(['type' => 'example', 'template' => true])
            ]
        ];

        foreach ($welcomeMessages as $message) {
            $message['created_at'] = current_time('mysql');
            $wpdb->insert($messagesTable, $message);
        }

        update_option('woo_ai_assistant_welcome_conversation_id', $conversationId);

        Logger::info('Welcome conversation template created');
        $this->installationResult['installed'][] = 'Welcome conversation template';
    }

    /**
     * Setup default AI prompts and responses
     *
     * @return void
     */
    private function setupDefaultAIPrompts(): void
    {
        Logger::info('Setting up default AI prompts');

        $defaultPrompts = [
            'system_prompt' => 'You are a helpful AI shopping assistant for a WooCommerce store. You help customers find products, answer questions about orders, shipping, returns, and provide excellent customer service. Be friendly, concise, and accurate.',
            'greeting_prompt' => 'Greet the customer warmly and ask how you can help them today.',
            'product_inquiry_prompt' => 'Help the customer find the right product based on their needs and preferences.',
            'order_support_prompt' => 'Assist the customer with order-related questions including status, tracking, and modifications.',
            'general_support_prompt' => 'Provide helpful information about store policies, shipping, returns, and general questions.',
            'fallback_prompt' => 'If you cannot answer a question, politely explain your limitations and suggest contacting customer support.'
        ];

        update_option('woo_ai_assistant_default_prompts', $defaultPrompts);

        // Default response templates for common scenarios
        $responseTemplates = [
            'product_not_found' => 'I couldn\'t find a product matching your request. Let me help you search our catalog or you can browse our categories.',
            'out_of_stock' => 'This product is currently out of stock. Would you like me to suggest similar items or notify you when it\'s available?',
            'pricing_inquiry' => 'The current price is {price}. This includes any applicable discounts. Shipping costs will be calculated at checkout.',
            'shipping_info' => 'We offer free standard shipping on orders over $50. Express shipping options are available at checkout.',
            'return_policy' => 'We have a 30-day return policy. Items must be in original condition. Return shipping is free for defective items.',
            'contact_support' => 'For more detailed assistance, please contact our support team at [contact information].'
        ];

        update_option('woo_ai_assistant_response_templates', $responseTemplates);

        Logger::info('Default AI prompts configured');
        $this->installationResult['installed'][] = 'AI prompts and response templates';
    }

    /**
     * Validate installation completeness
     *
     * @throws \Exception If validation fails
     * @return void
     */
    private function validateInstallation(): void
    {
        Logger::info('Validating installation completeness');

        $validationChecks = [
            'database_tables' => $this->validateDatabaseTables(),
            'initial_settings' => $this->validateInitialSettings(),
            'widget_config' => $this->validateWidgetConfiguration(),
            'knowledge_base' => $this->validateKnowledgeBase(),
            'analytics' => $this->validateAnalytics()
        ];

        $failedChecks = [];
        foreach ($validationChecks as $check => $passed) {
            if (!$passed) {
                $failedChecks[] = $check;
            }
        }

        if (!empty($failedChecks)) {
            $error = 'Installation validation failed: ' . implode(', ', $failedChecks);
            throw new \Exception($error);
        }

        Logger::info('Installation validation passed');
        $this->installationResult['installed'][] = 'Installation validation';
    }

    /**
     * Validate database tables exist and have data
     *
     * @return bool True if validation passes
     */
    private function validateDatabaseTables(): bool
    {
        try {
            $schema = Schema::getInstance();
            $validation = $schema->validateSchema();
            return $validation['valid'];
        } catch (\Exception $e) {
            Logger::error('Database table validation failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate initial settings were created
     *
     * @return bool True if validation passes
     */
    private function validateInitialSettings(): bool
    {
        global $wpdb;
        $settingsTable = $wpdb->prefix . 'woo_ai_settings';

        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$settingsTable}");
        return $count > 20; // Should have at least 20+ initial settings
    }

    /**
     * Validate widget configuration
     *
     * @return bool True if validation passes
     */
    private function validateWidgetConfiguration(): bool
    {
        return get_option('woo_ai_assistant_widget_ready', false) === true;
    }

    /**
     * Validate knowledge base has sample entries
     *
     * @return bool True if validation passes
     */
    private function validateKnowledgeBase(): bool
    {
        global $wpdb;
        $kbTable = $wpdb->prefix . 'woo_ai_knowledge_base';

        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$kbTable} WHERE is_active = 1");
        return $count > 0;
    }

    /**
     * Validate analytics tracking is initialized
     *
     * @return bool True if validation passes
     */
    private function validateAnalytics(): bool
    {
        global $wpdb;
        $analyticsTable = $wpdb->prefix . 'woo_ai_analytics';

        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$analyticsTable}");
        return $count > 0;
    }

    /**
     * Get setting group for categorization
     *
     * @param string $settingKey Setting key
     * @return string Setting group
     */
    private function getSettingGroup(string $settingKey): string
    {
        $groups = [
            'widget_' => 'widget',
            'ai_' => 'ai',
            'kb_' => 'knowledge_base',
            'max_' => 'limits',
            'enable_' => 'features',
            'cache_' => 'performance',
            'rate_limit_' => 'performance',
            'gdpr_' => 'privacy',
            'data_retention_' => 'privacy',
            'anonymous_' => 'privacy'
        ];

        foreach ($groups as $prefix => $group) {
            if (strpos($settingKey, $prefix) === 0) {
                return $group;
            }
        }

        return 'general';
    }

    /**
     * Check if setting contains sensitive data
     *
     * @param string $settingKey Setting key
     * @return bool True if sensitive
     */
    private function isSensitiveSetting(string $settingKey): bool
    {
        $sensitivePatterns = ['key', 'secret', 'token', 'password', 'license'];

        foreach ($sensitivePatterns as $pattern) {
            if (strpos(strtolower($settingKey), $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if setting should be autoloaded
     *
     * @param string $settingKey Setting key
     * @return bool True if should autoload
     */
    private function shouldAutoloadSetting(string $settingKey): bool
    {
        // Don't autoload large or rarely used settings
        $noAutoload = [
            'installation_id',
            'analytics_retention_days',
            'log_retention_days'
        ];

        return !in_array($settingKey, $noAutoload);
    }

    /**
     * Get installation summary
     *
     * @return array Installation summary data
     */
    public function getInstallationSummary(): array
    {
        return [
            'installation_date' => get_option('woo_ai_settings_installation_date'),
            'plugin_version' => WOO_AI_ASSISTANT_VERSION,
            'components_installed' => $this->installationResult['installed'] ?? [],
            'widget_ready' => get_option('woo_ai_assistant_widget_ready', false),
            'kb_entries' => $this->getKnowledgeBaseEntryCount(),
            'settings_count' => $this->getSettingsCount()
        ];
    }

    /**
     * Get knowledge base entry count
     *
     * @return int Number of KB entries
     */
    private function getKnowledgeBaseEntryCount(): int
    {
        global $wpdb;
        $kbTable = $wpdb->prefix . 'woo_ai_knowledge_base';

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$kbTable} WHERE is_active = 1");
    }

    /**
     * Get settings count
     *
     * @return int Number of settings
     */
    private function getSettingsCount(): int
    {
        global $wpdb;
        $settingsTable = $wpdb->prefix . 'woo_ai_settings';

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$settingsTable}");
    }
}
