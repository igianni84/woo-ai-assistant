<?php

/**
 * Admin Settings Page Class
 *
 * Handles the comprehensive settings page for the Woo AI Assistant plugin.
 * Manages widget customization, coupon rules, proactive triggers, API keys,
 * and all configurable options following the zero-config philosophy with
 * smart defaults while allowing advanced customization.
 *
 * @package WooAiAssistant
 * @subpackage Admin\Pages
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Admin\Pages;

use WooAiAssistant\Common\Utils;
use WooAiAssistant\Common\Traits\Singleton;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SettingsPage
 *
 * Manages the comprehensive settings interface for the AI Assistant plugin.
 * Provides configuration for widget appearance, behavior, coupon management,
 * proactive triggers, and API integrations.
 *
 * @since 1.0.0
 */
class SettingsPage
{
    use Singleton;

    /**
     * Required capability for settings access
     *
     * @since 1.0.0
     * @var string
     */
    private const REQUIRED_CAPABILITY = 'manage_woocommerce';

    /**
     * Settings option name in database
     *
     * @since 1.0.0
     * @var string
     */
    private const OPTION_NAME = 'woo_ai_assistant_settings';

    /**
     * Default settings configuration
     *
     * @since 1.0.0
     * @var array
     */
    private array $defaultSettings = [];

    /**
     * Current settings values
     *
     * @since 1.0.0
     * @var array
     */
    private array $settings = [];

    /**
     * Constructor
     *
     * Initializes the settings page, loads current settings, and sets up hooks.
     *
     * @since 1.0.0
     */
    private function __construct()
    {
        $this->setupDefaults();
        $this->loadSettings();
        $this->setupHooks();
    }

    /**
     * Setup default settings values
     *
     * Defines smart defaults following the zero-config philosophy.
     *
     * @since 1.0.0
     * @return void
     */
    private function setupDefaults(): void
    {
        $this->defaultSettings = [
            // General Settings
            'general' => [
                'enabled' => true,
                'position' => 'bottom-right',
                'initial_state' => 'minimized',
                'show_on_mobile' => true,
                'delay_seconds' => 3,
                'auto_open' => false,
            ],

            // Widget Appearance
            'appearance' => [
                'primary_color' => '#7F54B3',
                'text_color' => '#333333',
                'background_color' => '#FFFFFF',
                'header_text' => __('Hi! How can I help you today?', 'woo-ai-assistant'),
                'placeholder_text' => __('Type your message...', 'woo-ai-assistant'),
                'powered_by_text' => true,
                'custom_css' => '',
                'avatar_url' => '',
                'widget_size' => 'medium',
                'font_family' => 'inherit',
            ],

            // Behavior Settings
            'behavior' => [
                'welcome_message' => __("Hello! I'm your AI shopping assistant. How can I help you find what you're looking for today?", 'woo-ai-assistant'),
                'offline_message' => __("I'm currently offline. Please leave a message and we'll get back to you soon.", 'woo-ai-assistant'),
                'typing_indicator' => true,
                'sound_notifications' => false,
                'conversation_timeout' => 30, // minutes
                'max_message_length' => 500,
                'allowed_file_types' => [],
                'language' => 'auto', // Auto-detect from WordPress
            ],

            // Proactive Triggers
            'triggers' => [
                'exit_intent' => [
                    'enabled' => true,
                    'message' => __("Wait! Before you go, can I help you find something?", 'woo-ai-assistant'),
                    'delay' => 0,
                    'show_once_per_session' => true,
                ],
                'time_on_page' => [
                    'enabled' => false,
                    'seconds' => 30,
                    'message' => __("Need help finding something? I'm here to assist!", 'woo-ai-assistant'),
                ],
                'scroll_percentage' => [
                    'enabled' => false,
                    'percentage' => 50,
                    'message' => __("Hi! I noticed you're browsing. Can I help you with anything?", 'woo-ai-assistant'),
                ],
                'cart_abandonment' => [
                    'enabled' => true,
                    'idle_minutes' => 5,
                    'message' => __("I see you have items in your cart. Would you like help completing your purchase?", 'woo-ai-assistant'),
                ],
                'product_page' => [
                    'enabled' => true,
                    'delay_seconds' => 10,
                    'message' => __("Questions about this product? I can help with details, sizing, or recommendations!", 'woo-ai-assistant'),
                ],
            ],

            // Coupon Management
            'coupons' => [
                'allow_auto_generation' => false,
                'max_discount_percentage' => 10,
                'min_cart_value' => 50,
                'validity_days' => 7,
                'usage_limit' => 1,
                'exclude_sale_items' => true,
                'allowed_categories' => [],
                'excluded_products' => [],
                'require_email' => true,
                'max_coupons_per_user' => 1,
                'max_coupons_per_month' => 100,
                'coupon_prefix' => 'AI',
                'notification_email' => get_option('admin_email'),
            ],

            // API Configuration
            'api' => [
                'openrouter_key' => '',
                'google_api_key' => '',
                'openai_api_key' => '',
                'pinecone_api_key' => '',
                'pinecone_environment' => '',
                'pinecone_index_name' => 'woo-ai-assistant',
                'intermediate_server_url' => 'https://api.wooaiassistant.com',
                'license_key' => '',
                'webhook_url' => '',
                'enable_debug_mode' => false,
                'log_api_calls' => false,
                'timeout_seconds' => 30,
                'retry_attempts' => 3,
                'use_development_fallbacks' => false,
            ],

            // Knowledge Base Settings
            'knowledge_base' => [
                'auto_index' => true,
                'index_interval' => 'hourly',
                'excluded_categories' => [],
                'excluded_tags' => [],
                'excluded_pages' => [],
                'include_reviews' => true,
                'include_faqs' => true,
                'max_chunk_size' => 1000,
                'overlap_size' => 200,
                'embedding_model' => 'text-embedding-3-small',
            ],

            // Privacy & Compliance
            'privacy' => [
                'require_consent' => true,
                'consent_text' => __('I agree to the use of AI assistance for this conversation.', 'woo-ai-assistant'),
                'data_retention_days' => 90,
                'anonymize_data' => true,
                'gdpr_compliant' => true,
                'show_privacy_link' => true,
                'privacy_policy_url' => get_privacy_policy_url(),
                'delete_on_uninstall' => true,
            ],

            // Advanced Settings
            'advanced' => [
                'cache_responses' => true,
                'cache_duration' => 3600,
                'rate_limit_enabled' => true,
                'rate_limit_requests' => 100,
                'rate_limit_window' => 3600,
                'max_conversations_per_ip' => 10,
                'blocked_ips' => [],
                'allowed_domains' => [],
                'custom_js' => '',
                'enable_analytics' => true,
                'track_conversions' => true,
            ],
        ];
    }

    /**
     * Load current settings from database
     *
     * @since 1.0.0
     * @return void
     */
    private function loadSettings(): void
    {
        $saved = get_option(self::OPTION_NAME, []);
        // EMERGENCY FIX: wp_parse_args_recursive doesn't exist - use wp_parse_args instead
        $this->settings = wp_parse_args($saved, $this->defaultSettings);
    }

    /**
     * Setup WordPress hooks
     *
     * @since 1.0.0
     * @return void
     */
    private function setupHooks(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('wp_ajax_woo_ai_save_settings', [$this, 'handleSettingsSave']);
        add_action('wp_ajax_woo_ai_reset_settings', [$this, 'handleSettingsReset']);
        add_action('wp_ajax_woo_ai_test_api_connection', [$this, 'handleApiTest']);
        add_action('wp_ajax_woo_ai_generate_license', [$this, 'handleLicenseGeneration']);
        add_action('wp_ajax_woo_ai_trigger_indexing', [$this, 'handleTriggerIndexing']);
    }

    /**
     * Render the complete settings page
     *
     * Main entry point for rendering the settings interface with all
     * configuration sections and options.
     *
     * @since 1.0.0
     * @return void
     */
    public function render(): void
    {
        // Check capability
        if (!current_user_can(self::REQUIRED_CAPABILITY)) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'woo-ai-assistant'));
        }

        ?>
        <div class="wrap woo-ai-settings">
            <h1><?php echo esc_html__('AI Assistant Settings', 'woo-ai-assistant'); ?></h1>
            
            <?php $this->renderNotices(); ?>
            
            <form method="post" id="woo-ai-settings-form">
                <?php wp_nonce_field('woo_ai_settings_save', 'woo_ai_settings_nonce'); ?>
                
                <nav class="nav-tab-wrapper woo-nav-tab-wrapper">
                    <a href="#general" class="nav-tab nav-tab-active" data-tab="general">
                        <?php esc_html_e('General', 'woo-ai-assistant'); ?>
                    </a>
                    <a href="#appearance" class="nav-tab" data-tab="appearance">
                        <?php esc_html_e('Appearance', 'woo-ai-assistant'); ?>
                    </a>
                    <a href="#behavior" class="nav-tab" data-tab="behavior">
                        <?php esc_html_e('Behavior', 'woo-ai-assistant'); ?>
                    </a>
                    <a href="#triggers" class="nav-tab" data-tab="triggers">
                        <?php esc_html_e('Proactive Triggers', 'woo-ai-assistant'); ?>
                    </a>
                    <a href="#coupons" class="nav-tab" data-tab="coupons">
                        <?php esc_html_e('Coupon Rules', 'woo-ai-assistant'); ?>
                    </a>
                    <a href="#api" class="nav-tab" data-tab="api">
                        <?php esc_html_e('API & License', 'woo-ai-assistant'); ?>
                    </a>
                    <a href="#knowledge" class="nav-tab" data-tab="knowledge">
                        <?php esc_html_e('Knowledge Base', 'woo-ai-assistant'); ?>
                    </a>
                    <a href="#privacy" class="nav-tab" data-tab="privacy">
                        <?php esc_html_e('Privacy', 'woo-ai-assistant'); ?>
                    </a>
                    <a href="#advanced" class="nav-tab" data-tab="advanced">
                        <?php esc_html_e('Advanced', 'woo-ai-assistant'); ?>
                    </a>
                </nav>
                
                <div class="tab-content">
                    <?php
                    $this->renderGeneralSettings();
                    $this->renderAppearanceSettings();
                    $this->renderBehaviorSettings();
                    $this->renderTriggersSettings();
                    $this->renderCouponsSettings();
                    $this->renderApiSettings();
                    $this->renderKnowledgeBaseSettings();
                    $this->renderPrivacySettings();
                    $this->renderAdvancedSettings();
                    ?>
                </div>
                
                <p class="submit">
                    <?php submit_button(__('Save Settings', 'woo-ai-assistant'), 'primary', 'submit', false); ?>
                    <button type="button" class="button button-secondary" id="reset-settings">
                        <?php esc_html_e('Reset to Defaults', 'woo-ai-assistant'); ?>
                    </button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Render admin notices
     *
     * @since 1.0.0
     * @return void
     */
    private function renderNotices(): void
    {
        // Check for saved notice
        if ($notice = get_transient('woo_ai_settings_notice')) {
            $type = $notice['type'] ?? 'info';
            $message = $notice['message'] ?? '';

            if ($message) {
                printf(
                    '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                    esc_attr($type),
                    esc_html($message)
                );
            }

            delete_transient('woo_ai_settings_notice');
        }

        // License status notice
        $license = $this->settings['api']['license_key'] ?? '';
        if (empty($license)) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <?php
                    printf(
                        __('No license key configured. The AI Assistant is running in Free mode with limited features. %s', 'woo-ai-assistant'),
                        '<a href="#api">' . __('Configure License', 'woo-ai-assistant') . '</a>'
                    );
                    ?>
                </p>
            </div>
            <?php
        }
    }

    /**
     * Render General Settings section
     *
     * @since 1.0.0
     * @return void
     */
    private function renderGeneralSettings(): void
    {
        $general = $this->settings['general'];
        ?>
        <div id="general" class="tab-panel active">
            <h2><?php esc_html_e('General Settings', 'woo-ai-assistant'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="enabled"><?php esc_html_e('Enable AI Assistant', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="settings[general][enabled]" id="enabled" value="1" 
                                <?php checked($general['enabled'], true); ?>>
                            <?php esc_html_e('Enable the AI chat widget on your site', 'woo-ai-assistant'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="position"><?php esc_html_e('Widget Position', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <select name="settings[general][position]" id="position">
                            <option value="bottom-right" <?php selected($general['position'], 'bottom-right'); ?>>
                                <?php esc_html_e('Bottom Right', 'woo-ai-assistant'); ?>
                            </option>
                            <option value="bottom-left" <?php selected($general['position'], 'bottom-left'); ?>>
                                <?php esc_html_e('Bottom Left', 'woo-ai-assistant'); ?>
                            </option>
                            <option value="top-right" <?php selected($general['position'], 'top-right'); ?>>
                                <?php esc_html_e('Top Right', 'woo-ai-assistant'); ?>
                            </option>
                            <option value="top-left" <?php selected($general['position'], 'top-left'); ?>>
                                <?php esc_html_e('Top Left', 'woo-ai-assistant'); ?>
                            </option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="initial_state"><?php esc_html_e('Initial State', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <select name="settings[general][initial_state]" id="initial_state">
                            <option value="minimized" <?php selected($general['initial_state'], 'minimized'); ?>>
                                <?php esc_html_e('Minimized', 'woo-ai-assistant'); ?>
                            </option>
                            <option value="expanded" <?php selected($general['initial_state'], 'expanded'); ?>>
                                <?php esc_html_e('Expanded', 'woo-ai-assistant'); ?>
                            </option>
                            <option value="hidden" <?php selected($general['initial_state'], 'hidden'); ?>>
                                <?php esc_html_e('Hidden', 'woo-ai-assistant'); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php esc_html_e('How the widget should appear when the page loads.', 'woo-ai-assistant'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="show_on_mobile"><?php esc_html_e('Show on Mobile', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="settings[general][show_on_mobile]" id="show_on_mobile" value="1"
                                <?php checked($general['show_on_mobile'], true); ?>>
                            <?php esc_html_e('Display widget on mobile devices', 'woo-ai-assistant'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="delay_seconds"><?php esc_html_e('Display Delay', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="settings[general][delay_seconds]" id="delay_seconds" 
                            value="<?php echo esc_attr($general['delay_seconds']); ?>" min="0" max="60">
                        <p class="description">
                            <?php esc_html_e('Seconds to wait before showing the widget after page load.', 'woo-ai-assistant'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="auto_open"><?php esc_html_e('Auto Open', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="settings[general][auto_open]" id="auto_open" value="1"
                                <?php checked($general['auto_open'], true); ?>>
                            <?php esc_html_e('Automatically open chat window for first-time visitors', 'woo-ai-assistant'); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Render Appearance Settings section
     *
     * @since 1.0.0
     * @return void
     */
    private function renderAppearanceSettings(): void
    {
        $appearance = $this->settings['appearance'];
        ?>
        <div id="appearance" class="tab-panel">
            <h2><?php esc_html_e('Appearance Settings', 'woo-ai-assistant'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="primary_color"><?php esc_html_e('Primary Color', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="settings[appearance][primary_color]" id="primary_color" 
                            value="<?php echo esc_attr($appearance['primary_color']); ?>" class="color-picker">
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="text_color"><?php esc_html_e('Text Color', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="settings[appearance][text_color]" id="text_color" 
                            value="<?php echo esc_attr($appearance['text_color']); ?>" class="color-picker">
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="background_color"><?php esc_html_e('Background Color', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="settings[appearance][background_color]" id="background_color" 
                            value="<?php echo esc_attr($appearance['background_color']); ?>" class="color-picker">
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="header_text"><?php esc_html_e('Header Text', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="settings[appearance][header_text]" id="header_text" 
                            value="<?php echo esc_attr($appearance['header_text']); ?>" class="regular-text">
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="placeholder_text"><?php esc_html_e('Placeholder Text', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="settings[appearance][placeholder_text]" id="placeholder_text" 
                            value="<?php echo esc_attr($appearance['placeholder_text']); ?>" class="regular-text">
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="avatar_url"><?php esc_html_e('Avatar Image', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="url" name="settings[appearance][avatar_url]" id="avatar_url" 
                            value="<?php echo esc_attr($appearance['avatar_url']); ?>" class="regular-text">
                        <button type="button" class="button" id="upload-avatar">
                            <?php esc_html_e('Upload Image', 'woo-ai-assistant'); ?>
                        </button>
                        <p class="description">
                            <?php esc_html_e('URL to the avatar image for the AI assistant.', 'woo-ai-assistant'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="widget_size"><?php esc_html_e('Widget Size', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <select name="settings[appearance][widget_size]" id="widget_size">
                            <option value="small" <?php selected($appearance['widget_size'], 'small'); ?>>
                                <?php esc_html_e('Small', 'woo-ai-assistant'); ?>
                            </option>
                            <option value="medium" <?php selected($appearance['widget_size'], 'medium'); ?>>
                                <?php esc_html_e('Medium', 'woo-ai-assistant'); ?>
                            </option>
                            <option value="large" <?php selected($appearance['widget_size'], 'large'); ?>>
                                <?php esc_html_e('Large', 'woo-ai-assistant'); ?>
                            </option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="powered_by_text"><?php esc_html_e('Show Powered By', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="settings[appearance][powered_by_text]" id="powered_by_text" value="1"
                                <?php checked($appearance['powered_by_text'], true); ?>>
                            <?php esc_html_e('Show "Powered by Woo AI Assistant" text', 'woo-ai-assistant'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="custom_css"><?php esc_html_e('Custom CSS', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <textarea name="settings[appearance][custom_css]" id="custom_css" rows="10" class="large-text code">
        <?php echo esc_textarea($appearance['custom_css']); ?>
                        </textarea>
                        <p class="description">
                            <?php esc_html_e('Add custom CSS to style the chat widget.', 'woo-ai-assistant'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Render Behavior Settings section
     *
     * @since 1.0.0
     * @return void
     */
    private function renderBehaviorSettings(): void
    {
        $behavior = $this->settings['behavior'];
        ?>
        <div id="behavior" class="tab-panel">
            <h2><?php esc_html_e('Behavior Settings', 'woo-ai-assistant'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="welcome_message"><?php esc_html_e('Welcome Message', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <textarea name="settings[behavior][welcome_message]" id="welcome_message" rows="3" class="large-text">
        <?php echo esc_textarea($behavior['welcome_message']); ?>
                        </textarea>
                        <p class="description">
                            <?php esc_html_e('The first message visitors see when opening the chat.', 'woo-ai-assistant'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="offline_message"><?php esc_html_e('Offline Message', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <textarea name="settings[behavior][offline_message]" id="offline_message" rows="3" class="large-text">
        <?php echo esc_textarea($behavior['offline_message']); ?>
                        </textarea>
                        <p class="description">
                            <?php esc_html_e('Message shown when the AI assistant is unavailable.', 'woo-ai-assistant'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="typing_indicator"><?php esc_html_e('Typing Indicator', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="settings[behavior][typing_indicator]" id="typing_indicator" value="1"
                                <?php checked($behavior['typing_indicator'], true); ?>>
                            <?php esc_html_e('Show typing indicator when AI is responding', 'woo-ai-assistant'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="sound_notifications"><?php esc_html_e('Sound Notifications', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="settings[behavior][sound_notifications]" id="sound_notifications" value="1"
                                <?php checked($behavior['sound_notifications'], true); ?>>
                            <?php esc_html_e('Play sound for new messages', 'woo-ai-assistant'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="conversation_timeout"><?php esc_html_e('Conversation Timeout', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="settings[behavior][conversation_timeout]" id="conversation_timeout" 
                            value="<?php echo esc_attr($behavior['conversation_timeout']); ?>" min="5" max="120">
                        <span><?php esc_html_e('minutes', 'woo-ai-assistant'); ?></span>
                        <p class="description">
                            <?php esc_html_e('Time of inactivity before conversation is considered ended.', 'woo-ai-assistant'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="max_message_length"><?php esc_html_e('Max Message Length', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="settings[behavior][max_message_length]" id="max_message_length" 
                            value="<?php echo esc_attr($behavior['max_message_length']); ?>" min="100" max="5000">
                        <span><?php esc_html_e('characters', 'woo-ai-assistant'); ?></span>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="language"><?php esc_html_e('Language', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <select name="settings[behavior][language]" id="language">
                            <option value="auto" <?php selected($behavior['language'], 'auto'); ?>>
                                <?php esc_html_e('Auto-detect from WordPress', 'woo-ai-assistant'); ?>
                            </option>
                            <?php
                            $languages = [
                                'en' => 'English',
                                'it' => 'Italiano',
                                'es' => 'Español',
                                'fr' => 'Français',
                                'de' => 'Deutsch',
                                'pt' => 'Português',
                            ];
                            foreach ($languages as $code => $name) {
                                printf(
                                    '<option value="%s" %s>%s</option>',
                                    esc_attr($code),
                                    selected($behavior['language'], $code, false),
                                    esc_html($name)
                                );
                            }
                            ?>
                        </select>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Render Proactive Triggers Settings section
     *
     * @since 1.0.0
     * @return void
     */
    private function renderTriggersSettings(): void
    {
        $triggers = $this->settings['triggers'];
        ?>
        <div id="triggers" class="tab-panel">
            <h2><?php esc_html_e('Proactive Triggers', 'woo-ai-assistant'); ?></h2>
            <p class="description">
                <?php esc_html_e('Configure when and how the AI assistant proactively engages with visitors.', 'woo-ai-assistant'); ?>
            </p>
            
            <!-- Exit Intent Trigger -->
            <div class="trigger-section">
                <h3><?php esc_html_e('Exit Intent', 'woo-ai-assistant'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="exit_intent_enabled"><?php esc_html_e('Enable', 'woo-ai-assistant'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="settings[triggers][exit_intent][enabled]" 
                                    id="exit_intent_enabled" value="1"
                                    <?php checked($triggers['exit_intent']['enabled'], true); ?>>
                                <?php esc_html_e('Trigger when user moves cursor to leave page', 'woo-ai-assistant'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="exit_intent_message"><?php esc_html_e('Message', 'woo-ai-assistant'); ?></label>
                        </th>
                        <td>
                            <textarea name="settings[triggers][exit_intent][message]" id="exit_intent_message" 
                                rows="2" class="large-text">
        <?php echo esc_textarea($triggers['exit_intent']['message']); ?>
                            </textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="exit_intent_once"><?php esc_html_e('Show Once', 'woo-ai-assistant'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="settings[triggers][exit_intent][show_once_per_session]" 
                                    id="exit_intent_once" value="1"
                                    <?php checked($triggers['exit_intent']['show_once_per_session'], true); ?>>
                                <?php esc_html_e('Show only once per session', 'woo-ai-assistant'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Time on Page Trigger -->
            <div class="trigger-section">
                <h3><?php esc_html_e('Time on Page', 'woo-ai-assistant'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="time_on_page_enabled"><?php esc_html_e('Enable', 'woo-ai-assistant'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="settings[triggers][time_on_page][enabled]" 
                                    id="time_on_page_enabled" value="1"
                                    <?php checked($triggers['time_on_page']['enabled'], true); ?>>
                                <?php esc_html_e('Trigger after specified time on page', 'woo-ai-assistant'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="time_on_page_seconds"><?php esc_html_e('Delay', 'woo-ai-assistant'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="settings[triggers][time_on_page][seconds]" 
                                id="time_on_page_seconds" 
                                value="<?php echo esc_attr($triggers['time_on_page']['seconds']); ?>" 
                                min="5" max="300">
                            <span><?php esc_html_e('seconds', 'woo-ai-assistant'); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="time_on_page_message"><?php esc_html_e('Message', 'woo-ai-assistant'); ?></label>
                        </th>
                        <td>
                            <textarea name="settings[triggers][time_on_page][message]" id="time_on_page_message" 
                                rows="2" class="large-text">
        <?php echo esc_textarea($triggers['time_on_page']['message']); ?>
                            </textarea>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Cart Abandonment Trigger -->
            <div class="trigger-section">
                <h3><?php esc_html_e('Cart Abandonment', 'woo-ai-assistant'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="cart_abandonment_enabled"><?php esc_html_e('Enable', 'woo-ai-assistant'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="settings[triggers][cart_abandonment][enabled]" 
                                    id="cart_abandonment_enabled" value="1"
                                    <?php checked($triggers['cart_abandonment']['enabled'], true); ?>>
                                <?php esc_html_e('Trigger when cart is idle', 'woo-ai-assistant'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="cart_abandonment_idle"><?php esc_html_e('Idle Time', 'woo-ai-assistant'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="settings[triggers][cart_abandonment][idle_minutes]" 
                                id="cart_abandonment_idle" 
                                value="<?php echo esc_attr($triggers['cart_abandonment']['idle_minutes']); ?>" 
                                min="1" max="60">
                            <span><?php esc_html_e('minutes', 'woo-ai-assistant'); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="cart_abandonment_message"><?php esc_html_e('Message', 'woo-ai-assistant'); ?></label>
                        </th>
                        <td>
                            <textarea name="settings[triggers][cart_abandonment][message]" 
                                id="cart_abandonment_message" rows="2" class="large-text">
        <?php echo esc_textarea($triggers['cart_abandonment']['message']); ?>
                            </textarea>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Product Page Trigger -->
            <div class="trigger-section">
                <h3><?php esc_html_e('Product Page', 'woo-ai-assistant'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="product_page_enabled"><?php esc_html_e('Enable', 'woo-ai-assistant'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="settings[triggers][product_page][enabled]" 
                                    id="product_page_enabled" value="1"
                                    <?php checked($triggers['product_page']['enabled'], true); ?>>
                                <?php esc_html_e('Trigger on product pages', 'woo-ai-assistant'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="product_page_delay"><?php esc_html_e('Delay', 'woo-ai-assistant'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="settings[triggers][product_page][delay_seconds]" 
                                id="product_page_delay" 
                                value="<?php echo esc_attr($triggers['product_page']['delay_seconds']); ?>" 
                                min="0" max="60">
                            <span><?php esc_html_e('seconds', 'woo-ai-assistant'); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="product_page_message"><?php esc_html_e('Message', 'woo-ai-assistant'); ?></label>
                        </th>
                        <td>
                            <textarea name="settings[triggers][product_page][message]" 
                                id="product_page_message" rows="2" class="large-text">
        <?php echo esc_textarea($triggers['product_page']['message']); ?>
                            </textarea>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Render Coupon Rules Settings section
     *
     * @since 1.0.0
     * @return void
     */
    private function renderCouponsSettings(): void
    {
        $coupons = $this->settings['coupons'];
        ?>
        <div id="coupons" class="tab-panel">
            <h2><?php esc_html_e('Coupon Rules Management', 'woo-ai-assistant'); ?></h2>
            <div class="notice notice-info inline">
                <p>
                    <?php esc_html_e('Configure rules and restrictions for AI-generated coupons. Available in Pro and Unlimited plans.', 'woo-ai-assistant'); ?>
                </p>
            </div>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="allow_auto_generation"><?php esc_html_e('Auto-Generation', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="settings[coupons][allow_auto_generation]" 
                                id="allow_auto_generation" value="1"
                                <?php checked($coupons['allow_auto_generation'], true); ?>>
                            <?php esc_html_e('Allow AI to automatically generate coupons', 'woo-ai-assistant'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('Requires Pro or Unlimited plan.', 'woo-ai-assistant'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="max_discount_percentage"><?php esc_html_e('Max Discount', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="settings[coupons][max_discount_percentage]" 
                            id="max_discount_percentage" 
                            value="<?php echo esc_attr($coupons['max_discount_percentage']); ?>" 
                            min="1" max="100">
                        <span>%</span>
                        <p class="description">
                            <?php esc_html_e('Maximum discount percentage AI can offer.', 'woo-ai-assistant'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="min_cart_value"><?php esc_html_e('Minimum Cart Value', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="settings[coupons][min_cart_value]" 
                            id="min_cart_value" 
                            value="<?php echo esc_attr($coupons['min_cart_value']); ?>" 
                            min="0" step="0.01">
                        <span><?php echo esc_html(get_woocommerce_currency_symbol()); ?></span>
                        <p class="description">
                            <?php esc_html_e('Minimum cart value required for coupon generation.', 'woo-ai-assistant'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="validity_days"><?php esc_html_e('Validity Period', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="settings[coupons][validity_days]" 
                            id="validity_days" 
                            value="<?php echo esc_attr($coupons['validity_days']); ?>" 
                            min="1" max="365">
                        <span><?php esc_html_e('days', 'woo-ai-assistant'); ?></span>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="usage_limit"><?php esc_html_e('Usage Limit', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="settings[coupons][usage_limit]" 
                            id="usage_limit" 
                            value="<?php echo esc_attr($coupons['usage_limit']); ?>" 
                            min="1" max="100">
                        <p class="description">
                            <?php esc_html_e('How many times each coupon can be used.', 'woo-ai-assistant'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="exclude_sale_items"><?php esc_html_e('Exclude Sale Items', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="settings[coupons][exclude_sale_items]" 
                                id="exclude_sale_items" value="1"
                                <?php checked($coupons['exclude_sale_items'], true); ?>>
                            <?php esc_html_e('Coupons cannot be used on sale items', 'woo-ai-assistant'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="require_email"><?php esc_html_e('Require Email', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="settings[coupons][require_email]" 
                                id="require_email" value="1"
                                <?php checked($coupons['require_email'], true); ?>>
                            <?php esc_html_e('Require email address before generating coupon', 'woo-ai-assistant'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="max_coupons_per_user"><?php esc_html_e('Per User Limit', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="settings[coupons][max_coupons_per_user]" 
                            id="max_coupons_per_user" 
                            value="<?php echo esc_attr($coupons['max_coupons_per_user']); ?>" 
                            min="1" max="10">
                        <p class="description">
                            <?php esc_html_e('Maximum coupons per user per month.', 'woo-ai-assistant'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="max_coupons_per_month"><?php esc_html_e('Monthly Limit', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="settings[coupons][max_coupons_per_month]" 
                            id="max_coupons_per_month" 
                            value="<?php echo esc_attr($coupons['max_coupons_per_month']); ?>" 
                            min="1" max="10000">
                        <p class="description">
                            <?php esc_html_e('Total coupons that can be generated per month.', 'woo-ai-assistant'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="coupon_prefix"><?php esc_html_e('Coupon Prefix', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="settings[coupons][coupon_prefix]" 
                            id="coupon_prefix" 
                            value="<?php echo esc_attr($coupons['coupon_prefix']); ?>" 
                            maxlength="10" class="small-text">
                        <p class="description">
                            <?php esc_html_e('Prefix for generated coupon codes (e.g., AI-XXXXX).', 'woo-ai-assistant'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="notification_email"><?php esc_html_e('Notification Email', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="email" name="settings[coupons][notification_email]" 
                            id="notification_email" 
                            value="<?php echo esc_attr($coupons['notification_email']); ?>" 
                            class="regular-text">
                        <p class="description">
                            <?php esc_html_e('Email address for coupon generation notifications.', 'woo-ai-assistant'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Render API & License Settings section
     *
     * @since 1.0.0
     * @return void
     */
    private function renderApiSettings(): void
    {
        $api = $this->settings['api'];

        // Check if we're in development mode
        $developmentConfig = null;
        $isDevelopmentMode = false;
        if (class_exists('WooAiAssistant\\Common\\DevelopmentConfig')) {
            $developmentConfig = \WooAiAssistant\Common\DevelopmentConfig::getInstance();
            $isDevelopmentMode = $developmentConfig->isDevelopmentMode();
        }
        ?>
        <div id="api" class="tab-panel">
            <h2><?php esc_html_e('API & License Configuration', 'woo-ai-assistant'); ?></h2>
            
            <?php if ($isDevelopmentMode) : ?>
            <div class="notice notice-info inline">
                <p>
                    <strong><?php esc_html_e('🚀 Development Mode Active', 'woo-ai-assistant'); ?></strong><br>
                    <?php esc_html_e('API keys are being loaded from your .env file. The fields below show the configured status.', 'woo-ai-assistant'); ?>
                </p>
                <p>
                    <?php
                    $apiStatus = [];
                    if ($developmentConfig) {
                        $apiStatus[] = !empty($developmentConfig->getApiKey('openrouter')) ? '✅ OpenRouter' : '❌ OpenRouter';
                        $apiStatus[] = !empty($developmentConfig->getApiKey('openai')) ? '✅ OpenAI' : '❌ OpenAI';
                        $apiStatus[] = !empty($developmentConfig->getApiKey('pinecone')) ? '✅ Pinecone' : '❌ Pinecone';
                        $apiStatus[] = !empty($developmentConfig->getApiKey('google')) ? '✅ Google' : '❌ Google';
                    }
                    echo '<strong>' . esc_html__('API Keys Status:', 'woo-ai-assistant') . '</strong> ' . implode(' | ', $apiStatus);
                    ?>
                </p>
            </div>
            <?php endif; ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="license_key"><?php esc_html_e('License Key', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="settings[api][license_key]" 
                            id="license_key" 
                            value="<?php echo esc_attr($api['license_key']); ?>" 
                            class="regular-text">
                        <button type="button" class="button" id="verify-license">
                            <?php esc_html_e('Verify License', 'woo-ai-assistant'); ?>
                        </button>
                        <p class="description">
                            <?php esc_html_e('Enter your license key to unlock Pro/Unlimited features.', 'woo-ai-assistant'); ?>
                        </p>
                        <div id="license-status" class="license-status"></div>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="openrouter_key"><?php esc_html_e('OpenRouter API Key', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="password" name="settings[api][openrouter_key]" 
                            id="openrouter_key" 
                            value="<?php echo esc_attr($api['openrouter_key']); ?>" 
                            class="regular-text">
                        <button type="button" class="button toggle-visibility" data-target="openrouter_key">
                            <?php esc_html_e('Show', 'woo-ai-assistant'); ?>
                        </button>
                        <button type="button" class="button test-api-button" data-api="openrouter" data-key-field="openrouter_key">
                            <?php esc_html_e('Test Key', 'woo-ai-assistant'); ?>
                        </button>
                        <span class="api-status" id="openrouter-status"></span>
                        <p class="description">
                            <?php
                            printf(
                                __('Optional. Get your key from %s', 'woo-ai-assistant'),
                                '<a href="https://openrouter.ai/keys" target="_blank">OpenRouter</a>'
                            );
                            ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="google_api_key"><?php esc_html_e('Google API Key', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="password" name="settings[api][google_api_key]" 
                            id="google_api_key" 
                            value="<?php echo esc_attr($api['google_api_key']); ?>" 
                            class="regular-text">
                        <button type="button" class="button toggle-visibility" data-target="google_api_key">
                            <?php esc_html_e('Show', 'woo-ai-assistant'); ?>
                        </button>
                        <button type="button" class="button test-api-button" data-api="google" data-key-field="google_api_key">
                            <?php esc_html_e('Test Key', 'woo-ai-assistant'); ?>
                        </button>
                        <span class="api-status" id="google-status"></span>
                        <p class="description">
                            <?php
                            printf(
                                __('Optional. For direct Gemini API access. Get from %s', 'woo-ai-assistant'),
                                '<a href="https://makersuite.google.com/app/apikey" target="_blank">Google AI Studio</a>'
                            );
                            ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="openai_api_key"><?php esc_html_e('OpenAI API Key', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="password" name="settings[api][openai_api_key]" 
                            id="openai_api_key" 
                            value="<?php echo esc_attr($api['openai_api_key'] ?? ''); ?>" 
                            class="regular-text">
                        <button type="button" class="button toggle-visibility" data-target="openai_api_key">
                            <?php esc_html_e('Show', 'woo-ai-assistant'); ?>
                        </button>
                        <button type="button" class="button test-api-button" data-api="openai" data-key-field="openai_api_key">
                            <?php esc_html_e('Test Key', 'woo-ai-assistant'); ?>
                        </button>
                        <span class="api-status" id="openai-status"></span>
                        <p class="description">
                            <?php
                            printf(
                                __('Required for embeddings. Get your key from %s', 'woo-ai-assistant'),
                                '<a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a>'
                            );
                            ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="pinecone_api_key"><?php esc_html_e('Pinecone API Key', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="password" name="settings[api][pinecone_api_key]" 
                            id="pinecone_api_key" 
                            value="<?php echo esc_attr($api['pinecone_api_key'] ?? ''); ?>" 
                            class="regular-text">
                        <button type="button" class="button toggle-visibility" data-target="pinecone_api_key">
                            <?php esc_html_e('Show', 'woo-ai-assistant'); ?>
                        </button>
                        <button type="button" class="button test-api-button" data-api="pinecone" data-key-field="pinecone_api_key">
                            <?php esc_html_e('Test Key', 'woo-ai-assistant'); ?>
                        </button>
                        <span class="api-status" id="pinecone-status"></span>
                        <p class="description">
                            <?php
                            printf(
                                __('Required for vector storage. Get your key from %s', 'woo-ai-assistant'),
                                '<a href="https://app.pinecone.io/organizations" target="_blank">Pinecone Console</a>'
                            );
                            ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="pinecone_environment"><?php esc_html_e('Pinecone Environment', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="settings[api][pinecone_environment]" 
                            id="pinecone_environment" 
                            value="<?php echo esc_attr($api['pinecone_environment'] ?? ''); ?>" 
                            class="regular-text"
                            placeholder="us-east-1-aws">
                        <p class="description">
                            <?php esc_html_e('Your Pinecone environment (e.g., us-east-1-aws)', 'woo-ai-assistant'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="pinecone_index_name"><?php esc_html_e('Pinecone Index Name', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="settings[api][pinecone_index_name]" 
                            id="pinecone_index_name" 
                            value="<?php echo esc_attr($api['pinecone_index_name'] ?? 'woo-ai-assistant'); ?>" 
                            class="regular-text">
                        <p class="description">
                            <?php esc_html_e('Name of your Pinecone index for storing vectors', 'woo-ai-assistant'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="intermediate_server_url"><?php esc_html_e('Server URL', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="url" name="settings[api][intermediate_server_url]" 
                            id="intermediate_server_url" 
                            value="<?php echo esc_attr($api['intermediate_server_url']); ?>" 
                            class="regular-text">
                        <button type="button" class="button" id="test-connection">
                            <?php esc_html_e('Test Connection', 'woo-ai-assistant'); ?>
                        </button>
                        <p class="description">
                            <?php esc_html_e('Intermediate server URL for AI processing.', 'woo-ai-assistant'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="webhook_url"><?php esc_html_e('Webhook URL', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="url" name="settings[api][webhook_url]" 
                            id="webhook_url" 
                            value="<?php echo esc_attr($api['webhook_url']); ?>" 
                            class="regular-text">
                        <p class="description">
                            <?php esc_html_e('Optional. URL to receive conversation events.', 'woo-ai-assistant'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="enable_debug_mode"><?php esc_html_e('Debug Mode', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="settings[api][enable_debug_mode]" 
                                id="enable_debug_mode" value="1"
                                <?php checked($api['enable_debug_mode'], true); ?>>
                            <?php esc_html_e('Enable debug logging for API calls', 'woo-ai-assistant'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('Warning: May expose sensitive data in logs.', 'woo-ai-assistant'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="use_development_fallbacks"><?php esc_html_e('Development Mode', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="settings[api][use_development_fallbacks]" 
                                id="use_development_fallbacks" value="1"
                                <?php checked($api['use_development_fallbacks'] ?? false, true); ?>>
                            <?php esc_html_e('Use development fallbacks when API keys are missing', 'woo-ai-assistant'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('Enable this for local development. Uses dummy responses when API keys are not configured.', 'woo-ai-assistant'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="timeout_seconds"><?php esc_html_e('API Timeout', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="settings[api][timeout_seconds]" 
                            id="timeout_seconds" 
                            value="<?php echo esc_attr($api['timeout_seconds']); ?>" 
                            min="5" max="120">
                        <span><?php esc_html_e('seconds', 'woo-ai-assistant'); ?></span>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="retry_attempts"><?php esc_html_e('Retry Attempts', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="settings[api][retry_attempts]" 
                            id="retry_attempts" 
                            value="<?php echo esc_attr($api['retry_attempts']); ?>" 
                            min="0" max="5">
                        <p class="description">
                            <?php esc_html_e('Number of retry attempts for failed API calls.', 'woo-ai-assistant'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Render Knowledge Base Settings section
     *
     * @since 1.0.0
     * @return void
     */
    private function renderKnowledgeBaseSettings(): void
    {
        $kb = $this->settings['knowledge_base'];
        ?>
        <div id="knowledge" class="tab-panel">
            <h2><?php esc_html_e('Knowledge Base Settings', 'woo-ai-assistant'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="auto_index"><?php esc_html_e('Auto-Index', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="settings[knowledge_base][auto_index]" 
                                id="auto_index" value="1"
                                <?php checked($kb['auto_index'], true); ?>>
                            <?php esc_html_e('Automatically index new and updated content', 'woo-ai-assistant'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="index_interval"><?php esc_html_e('Index Interval', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <select name="settings[knowledge_base][index_interval]" id="index_interval">
                            <option value="hourly" <?php selected($kb['index_interval'], 'hourly'); ?>>
                                <?php esc_html_e('Hourly', 'woo-ai-assistant'); ?>
                            </option>
                            <option value="twicedaily" <?php selected($kb['index_interval'], 'twicedaily'); ?>>
                                <?php esc_html_e('Twice Daily', 'woo-ai-assistant'); ?>
                            </option>
                            <option value="daily" <?php selected($kb['index_interval'], 'daily'); ?>>
                                <?php esc_html_e('Daily', 'woo-ai-assistant'); ?>
                            </option>
                            <option value="weekly" <?php selected($kb['index_interval'], 'weekly'); ?>>
                                <?php esc_html_e('Weekly', 'woo-ai-assistant'); ?>
                            </option>
                        </select>
                        <button type="button" class="button" id="index-now">
                            <?php esc_html_e('Index Now', 'woo-ai-assistant'); ?>
                        </button>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="include_reviews"><?php esc_html_e('Include Reviews', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="settings[knowledge_base][include_reviews]" 
                                id="include_reviews" value="1"
                                <?php checked($kb['include_reviews'], true); ?>>
                            <?php esc_html_e('Include product reviews in knowledge base', 'woo-ai-assistant'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="include_faqs"><?php esc_html_e('Include FAQs', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="settings[knowledge_base][include_faqs]" 
                                id="include_faqs" value="1"
                                <?php checked($kb['include_faqs'], true); ?>>
                            <?php esc_html_e('Include FAQ pages in knowledge base', 'woo-ai-assistant'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="max_chunk_size"><?php esc_html_e('Chunk Size', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="settings[knowledge_base][max_chunk_size]" 
                            id="max_chunk_size" 
                            value="<?php echo esc_attr($kb['max_chunk_size']); ?>" 
                            min="200" max="4000">
                        <span><?php esc_html_e('characters', 'woo-ai-assistant'); ?></span>
                        <p class="description">
                            <?php esc_html_e('Maximum size of text chunks for indexing.', 'woo-ai-assistant'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="overlap_size"><?php esc_html_e('Overlap Size', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="settings[knowledge_base][overlap_size]" 
                            id="overlap_size" 
                            value="<?php echo esc_attr($kb['overlap_size']); ?>" 
                            min="0" max="500">
                        <span><?php esc_html_e('characters', 'woo-ai-assistant'); ?></span>
                        <p class="description">
                            <?php esc_html_e('Overlap between chunks for better context.', 'woo-ai-assistant'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="embedding_model"><?php esc_html_e('Embedding Model', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <select name="settings[knowledge_base][embedding_model]" id="embedding_model">
                            <option value="text-embedding-3-small" <?php selected($kb['embedding_model'], 'text-embedding-3-small'); ?>>
                                text-embedding-3-small (Recommended)
                            </option>
                            <option value="text-embedding-3-large" <?php selected($kb['embedding_model'], 'text-embedding-3-large'); ?>>
                                text-embedding-3-large (Higher accuracy)
                            </option>
                            <option value="text-embedding-ada-002" <?php selected($kb['embedding_model'], 'text-embedding-ada-002'); ?>>
                                text-embedding-ada-002 (Legacy)
                            </option>
                        </select>
                    </td>
                </tr>
            </table>
            
            <div class="kb-status">
                <h3><?php esc_html_e('Knowledge Base Status', 'woo-ai-assistant'); ?></h3>
                <div id="kb-stats" class="kb-stats-container">
                    <div class="stat-card">
                        <span class="stat-label"><?php esc_html_e('Total Documents', 'woo-ai-assistant'); ?></span>
                        <span class="stat-value" id="kb-total-docs">-</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-label"><?php esc_html_e('Total Chunks', 'woo-ai-assistant'); ?></span>
                        <span class="stat-value" id="kb-total-chunks">-</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-label"><?php esc_html_e('Last Index', 'woo-ai-assistant'); ?></span>
                        <span class="stat-value" id="kb-last-index">-</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-label"><?php esc_html_e('Health Score', 'woo-ai-assistant'); ?></span>
                        <span class="stat-value" id="kb-health-score">-</span>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render Privacy Settings section
     *
     * @since 1.0.0
     * @return void
     */
    private function renderPrivacySettings(): void
    {
        $privacy = $this->settings['privacy'];
        ?>
        <div id="privacy" class="tab-panel">
            <h2><?php esc_html_e('Privacy & Compliance', 'woo-ai-assistant'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="require_consent"><?php esc_html_e('Require Consent', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="settings[privacy][require_consent]" 
                                id="require_consent" value="1"
                                <?php checked($privacy['require_consent'], true); ?>>
                            <?php esc_html_e('Require user consent before starting conversation', 'woo-ai-assistant'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="consent_text"><?php esc_html_e('Consent Text', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <textarea name="settings[privacy][consent_text]" id="consent_text" 
                            rows="3" class="large-text">
        <?php echo esc_textarea($privacy['consent_text']); ?>
                        </textarea>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="data_retention_days"><?php esc_html_e('Data Retention', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="settings[privacy][data_retention_days]" 
                            id="data_retention_days" 
                            value="<?php echo esc_attr($privacy['data_retention_days']); ?>" 
                            min="1" max="365">
                        <span><?php esc_html_e('days', 'woo-ai-assistant'); ?></span>
                        <p class="description">
                            <?php esc_html_e('How long to keep conversation data.', 'woo-ai-assistant'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="anonymize_data"><?php esc_html_e('Anonymize Data', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="settings[privacy][anonymize_data]" 
                                id="anonymize_data" value="1"
                                <?php checked($privacy['anonymize_data'], true); ?>>
                            <?php esc_html_e('Anonymize personal data in logs', 'woo-ai-assistant'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="gdpr_compliant"><?php esc_html_e('GDPR Mode', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="settings[privacy][gdpr_compliant]" 
                                id="gdpr_compliant" value="1"
                                <?php checked($privacy['gdpr_compliant'], true); ?>>
                            <?php esc_html_e('Enable GDPR compliance features', 'woo-ai-assistant'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="show_privacy_link"><?php esc_html_e('Privacy Link', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="settings[privacy][show_privacy_link]" 
                                id="show_privacy_link" value="1"
                                <?php checked($privacy['show_privacy_link'], true); ?>>
                            <?php esc_html_e('Show privacy policy link in widget', 'woo-ai-assistant'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="privacy_policy_url"><?php esc_html_e('Privacy Policy URL', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="url" name="settings[privacy][privacy_policy_url]" 
                            id="privacy_policy_url" 
                            value="<?php echo esc_attr($privacy['privacy_policy_url']); ?>" 
                            class="regular-text">
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="delete_on_uninstall"><?php esc_html_e('Delete on Uninstall', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="settings[privacy][delete_on_uninstall]" 
                                id="delete_on_uninstall" value="1"
                                <?php checked($privacy['delete_on_uninstall'], true); ?>>
                            <?php esc_html_e('Delete all data when plugin is uninstalled', 'woo-ai-assistant'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('Warning: This action cannot be undone.', 'woo-ai-assistant'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Render Advanced Settings section
     *
     * @since 1.0.0
     * @return void
     */
    private function renderAdvancedSettings(): void
    {
        $advanced = $this->settings['advanced'];
        ?>
        <div id="advanced" class="tab-panel">
            <h2><?php esc_html_e('Advanced Settings', 'woo-ai-assistant'); ?></h2>
            <div class="notice notice-warning inline">
                <p>
                    <?php esc_html_e('Warning: These settings are for advanced users. Incorrect configuration may affect performance.', 'woo-ai-assistant'); ?>
                </p>
            </div>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="cache_responses"><?php esc_html_e('Cache Responses', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="settings[advanced][cache_responses]" 
                                id="cache_responses" value="1"
                                <?php checked($advanced['cache_responses'], true); ?>>
                            <?php esc_html_e('Cache AI responses for faster performance', 'woo-ai-assistant'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="cache_duration"><?php esc_html_e('Cache Duration', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="settings[advanced][cache_duration]" 
                            id="cache_duration" 
                            value="<?php echo esc_attr($advanced['cache_duration']); ?>" 
                            min="60" max="86400">
                        <span><?php esc_html_e('seconds', 'woo-ai-assistant'); ?></span>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="rate_limit_enabled"><?php esc_html_e('Rate Limiting', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="settings[advanced][rate_limit_enabled]" 
                                id="rate_limit_enabled" value="1"
                                <?php checked($advanced['rate_limit_enabled'], true); ?>>
                            <?php esc_html_e('Enable rate limiting to prevent abuse', 'woo-ai-assistant'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="rate_limit_requests"><?php esc_html_e('Request Limit', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="settings[advanced][rate_limit_requests]" 
                            id="rate_limit_requests" 
                            value="<?php echo esc_attr($advanced['rate_limit_requests']); ?>" 
                            min="10" max="1000">
                        <span><?php esc_html_e('requests per window', 'woo-ai-assistant'); ?></span>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="max_conversations_per_ip"><?php esc_html_e('Conversations per IP', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="settings[advanced][max_conversations_per_ip]" 
                            id="max_conversations_per_ip" 
                            value="<?php echo esc_attr($advanced['max_conversations_per_ip']); ?>" 
                            min="1" max="100">
                        <p class="description">
                            <?php esc_html_e('Maximum concurrent conversations per IP address.', 'woo-ai-assistant'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="enable_analytics"><?php esc_html_e('Analytics', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="settings[advanced][enable_analytics]" 
                                id="enable_analytics" value="1"
                                <?php checked($advanced['enable_analytics'], true); ?>>
                            <?php esc_html_e('Enable conversation analytics tracking', 'woo-ai-assistant'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="track_conversions"><?php esc_html_e('Track Conversions', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="settings[advanced][track_conversions]" 
                                id="track_conversions" value="1"
                                <?php checked($advanced['track_conversions'], true); ?>>
                            <?php esc_html_e('Track sales assisted by AI chat', 'woo-ai-assistant'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="custom_js"><?php esc_html_e('Custom JavaScript', 'woo-ai-assistant'); ?></label>
                    </th>
                    <td>
                        <textarea name="settings[advanced][custom_js]" id="custom_js" 
                            rows="10" class="large-text code">
        <?php echo esc_textarea($advanced['custom_js']); ?>
                        </textarea>
                        <p class="description">
                            <?php esc_html_e('Advanced: Add custom JavaScript code for widget customization.', 'woo-ai-assistant'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Enqueue admin assets for settings page
     *
     * @since 1.0.0
     * @param string $hookSuffix Current admin page hook
     * @return void
     */
    public function enqueueAssets(string $hookSuffix): void
    {
        // Only enqueue on our settings page
        if (strpos($hookSuffix, 'woo-ai-assistant-settings') === false) {
            return;
        }

        // Enqueue color picker
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');

        // Enqueue media uploader
        wp_enqueue_media();

        // Enqueue custom settings script
        wp_enqueue_script(
            'woo-ai-settings',
            WOO_AI_ASSISTANT_ASSETS_URL . 'js/settings.js',
            ['jquery', 'wp-color-picker'],
            WOO_AI_ASSISTANT_VERSION,
            true
        );

        // Localize script
        wp_localize_script('woo-ai-settings', 'wooAiSettings', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('woo_ai_settings'),
            'strings' => [
                'save_success' => __('Settings saved successfully.', 'woo-ai-assistant'),
                'save_error' => __('Error saving settings. Please try again.', 'woo-ai-assistant'),
                'reset_confirm' => __('Are you sure you want to reset all settings to defaults?', 'woo-ai-assistant'),
                'reset_success' => __('Settings reset to defaults.', 'woo-ai-assistant'),
                'license_valid' => __('License is valid!', 'woo-ai-assistant'),
                'license_invalid' => __('Invalid license key.', 'woo-ai-assistant'),
                'connection_success' => __('Connection successful!', 'woo-ai-assistant'),
                'connection_failed' => __('Connection failed. Please check your settings.', 'woo-ai-assistant'),
                'indexing' => __('Indexing in progress...', 'woo-ai-assistant'),
                'index_complete' => __('Indexing completed successfully.', 'woo-ai-assistant'),
            ],
        ]);

        // Enqueue custom settings styles
        wp_enqueue_style(
            'woo-ai-settings',
            WOO_AI_ASSISTANT_ASSETS_URL . 'css/settings.css',
            ['wp-admin'],
            WOO_AI_ASSISTANT_VERSION
        );
    }

    /**
     * Handle settings save via AJAX
     *
     * @since 1.0.0
     * @return void
     */
    public function handleSettingsSave(): void
    {
        // Verify nonce
        if (!check_ajax_referer('woo_ai_settings', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security verification failed.', 'woo-ai-assistant')]);
        }

        // Check capability
        if (!current_user_can(self::REQUIRED_CAPABILITY)) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'woo-ai-assistant')]);
        }

        // Get and sanitize settings
        $settings = isset($_POST['settings']) ? $this->sanitizeSettings($_POST['settings']) : [];

        // Save settings
        $result = update_option(self::OPTION_NAME, $settings);

        if ($result) {
            // Clear any caches
            wp_cache_flush();

            // Trigger settings updated action
            do_action('woo_ai_assistant_settings_updated', $settings);

            wp_send_json_success([
                'message' => __('Settings saved successfully.', 'woo-ai-assistant'),
            ]);
        } else {
            wp_send_json_error([
                'message' => __('No changes to save or error saving settings.', 'woo-ai-assistant'),
            ]);
        }
    }

    /**
     * Sanitize settings array
     *
     * @since 1.0.0
     * @param array $settings Raw settings data
     * @return array Sanitized settings
     */
    private function sanitizeSettings(array $settings): array
    {
        $sanitized = [];

        // General settings
        if (isset($settings['general'])) {
            $sanitized['general'] = [
                'enabled' => !empty($settings['general']['enabled']),
                'position' => sanitize_text_field($settings['general']['position'] ?? 'bottom-right'),
                'initial_state' => sanitize_text_field($settings['general']['initial_state'] ?? 'minimized'),
                'show_on_mobile' => !empty($settings['general']['show_on_mobile']),
                'delay_seconds' => absint($settings['general']['delay_seconds'] ?? 3),
                'auto_open' => !empty($settings['general']['auto_open']),
            ];
        }

        // Appearance settings
        if (isset($settings['appearance'])) {
            $sanitized['appearance'] = [
                'primary_color' => sanitize_hex_color($settings['appearance']['primary_color'] ?? '#7F54B3'),
                'text_color' => sanitize_hex_color($settings['appearance']['text_color'] ?? '#333333'),
                'background_color' => sanitize_hex_color($settings['appearance']['background_color'] ?? '#FFFFFF'),
                'header_text' => sanitize_text_field($settings['appearance']['header_text'] ?? ''),
                'placeholder_text' => sanitize_text_field($settings['appearance']['placeholder_text'] ?? ''),
                'powered_by_text' => !empty($settings['appearance']['powered_by_text']),
                'custom_css' => wp_strip_all_tags($settings['appearance']['custom_css'] ?? ''),
                'avatar_url' => esc_url_raw($settings['appearance']['avatar_url'] ?? ''),
                'widget_size' => sanitize_text_field($settings['appearance']['widget_size'] ?? 'medium'),
                'font_family' => sanitize_text_field($settings['appearance']['font_family'] ?? 'inherit'),
            ];
        }

        // Behavior settings
        if (isset($settings['behavior'])) {
            $sanitized['behavior'] = [
                'welcome_message' => sanitize_textarea_field($settings['behavior']['welcome_message'] ?? ''),
                'offline_message' => sanitize_textarea_field($settings['behavior']['offline_message'] ?? ''),
                'typing_indicator' => !empty($settings['behavior']['typing_indicator']),
                'sound_notifications' => !empty($settings['behavior']['sound_notifications']),
                'conversation_timeout' => absint($settings['behavior']['conversation_timeout'] ?? 30),
                'max_message_length' => absint($settings['behavior']['max_message_length'] ?? 500),
                'allowed_file_types' => array_map('sanitize_text_field', $settings['behavior']['allowed_file_types'] ?? []),
                'language' => sanitize_text_field($settings['behavior']['language'] ?? 'auto'),
            ];
        }

        // Trigger settings
        if (isset($settings['triggers'])) {
            foreach (['exit_intent', 'time_on_page', 'scroll_percentage', 'cart_abandonment', 'product_page'] as $trigger) {
                if (isset($settings['triggers'][$trigger])) {
                    $sanitized['triggers'][$trigger] = $this->sanitizeTriggerSettings($settings['triggers'][$trigger]);
                }
            }
        }

        // Coupon settings
        if (isset($settings['coupons'])) {
            $sanitized['coupons'] = [
                'allow_auto_generation' => !empty($settings['coupons']['allow_auto_generation']),
                'max_discount_percentage' => absint($settings['coupons']['max_discount_percentage'] ?? 10),
                'min_cart_value' => floatval($settings['coupons']['min_cart_value'] ?? 50),
                'validity_days' => absint($settings['coupons']['validity_days'] ?? 7),
                'usage_limit' => absint($settings['coupons']['usage_limit'] ?? 1),
                'exclude_sale_items' => !empty($settings['coupons']['exclude_sale_items']),
                'allowed_categories' => array_map('absint', $settings['coupons']['allowed_categories'] ?? []),
                'excluded_products' => array_map('absint', $settings['coupons']['excluded_products'] ?? []),
                'require_email' => !empty($settings['coupons']['require_email']),
                'max_coupons_per_user' => absint($settings['coupons']['max_coupons_per_user'] ?? 1),
                'max_coupons_per_month' => absint($settings['coupons']['max_coupons_per_month'] ?? 100),
                'coupon_prefix' => sanitize_text_field($settings['coupons']['coupon_prefix'] ?? 'AI'),
                'notification_email' => sanitize_email($settings['coupons']['notification_email'] ?? ''),
            ];
        }

        // API settings (sensitive data handling)
        if (isset($settings['api'])) {
            $sanitized['api'] = [
                'openrouter_key' => sanitize_text_field($settings['api']['openrouter_key'] ?? ''),
                'google_api_key' => sanitize_text_field($settings['api']['google_api_key'] ?? ''),
                'openai_api_key' => sanitize_text_field($settings['api']['openai_api_key'] ?? ''),
                'pinecone_api_key' => sanitize_text_field($settings['api']['pinecone_api_key'] ?? ''),
                'pinecone_environment' => sanitize_text_field($settings['api']['pinecone_environment'] ?? ''),
                'pinecone_index_name' => sanitize_text_field($settings['api']['pinecone_index_name'] ?? 'woo-ai-assistant'),
                'intermediate_server_url' => esc_url_raw($settings['api']['intermediate_server_url'] ?? ''),
                'license_key' => sanitize_text_field($settings['api']['license_key'] ?? ''),
                'webhook_url' => esc_url_raw($settings['api']['webhook_url'] ?? ''),
                'enable_debug_mode' => !empty($settings['api']['enable_debug_mode']),
                'log_api_calls' => !empty($settings['api']['log_api_calls']),
                'timeout_seconds' => absint($settings['api']['timeout_seconds'] ?? 30),
                'retry_attempts' => absint($settings['api']['retry_attempts'] ?? 3),
                'use_development_fallbacks' => !empty($settings['api']['use_development_fallbacks']),
            ];
        }

        // Knowledge Base settings
        if (isset($settings['knowledge_base'])) {
            $sanitized['knowledge_base'] = [
                'auto_index' => !empty($settings['knowledge_base']['auto_index']),
                'index_interval' => sanitize_text_field($settings['knowledge_base']['index_interval'] ?? 'hourly'),
                'excluded_categories' => array_map('absint', $settings['knowledge_base']['excluded_categories'] ?? []),
                'excluded_tags' => array_map('absint', $settings['knowledge_base']['excluded_tags'] ?? []),
                'excluded_pages' => array_map('absint', $settings['knowledge_base']['excluded_pages'] ?? []),
                'include_reviews' => !empty($settings['knowledge_base']['include_reviews']),
                'include_faqs' => !empty($settings['knowledge_base']['include_faqs']),
                'max_chunk_size' => absint($settings['knowledge_base']['max_chunk_size'] ?? 1000),
                'overlap_size' => absint($settings['knowledge_base']['overlap_size'] ?? 200),
                'embedding_model' => sanitize_text_field($settings['knowledge_base']['embedding_model'] ?? 'text-embedding-3-small'),
            ];
        }

        // Privacy settings
        if (isset($settings['privacy'])) {
            $sanitized['privacy'] = [
                'require_consent' => !empty($settings['privacy']['require_consent']),
                'consent_text' => sanitize_textarea_field($settings['privacy']['consent_text'] ?? ''),
                'data_retention_days' => absint($settings['privacy']['data_retention_days'] ?? 90),
                'anonymize_data' => !empty($settings['privacy']['anonymize_data']),
                'gdpr_compliant' => !empty($settings['privacy']['gdpr_compliant']),
                'show_privacy_link' => !empty($settings['privacy']['show_privacy_link']),
                'privacy_policy_url' => esc_url_raw($settings['privacy']['privacy_policy_url'] ?? ''),
                'delete_on_uninstall' => !empty($settings['privacy']['delete_on_uninstall']),
            ];
        }

        // Advanced settings
        if (isset($settings['advanced'])) {
            $sanitized['advanced'] = [
                'cache_responses' => !empty($settings['advanced']['cache_responses']),
                'cache_duration' => absint($settings['advanced']['cache_duration'] ?? 3600),
                'rate_limit_enabled' => !empty($settings['advanced']['rate_limit_enabled']),
                'rate_limit_requests' => absint($settings['advanced']['rate_limit_requests'] ?? 100),
                'rate_limit_window' => absint($settings['advanced']['rate_limit_window'] ?? 3600),
                'max_conversations_per_ip' => absint($settings['advanced']['max_conversations_per_ip'] ?? 10),
                'blocked_ips' => array_map('sanitize_text_field', $settings['advanced']['blocked_ips'] ?? []),
                'allowed_domains' => array_map('sanitize_text_field', $settings['advanced']['allowed_domains'] ?? []),
                'custom_js' => wp_strip_all_tags($settings['advanced']['custom_js'] ?? ''),
                'enable_analytics' => !empty($settings['advanced']['enable_analytics']),
                'track_conversions' => !empty($settings['advanced']['track_conversions']),
            ];
        }

        return $sanitized;
    }

    /**
     * Sanitize trigger settings
     *
     * @since 1.0.0
     * @param array $trigger Trigger settings
     * @return array Sanitized trigger settings
     */
    private function sanitizeTriggerSettings(array $trigger): array
    {
        return [
            'enabled' => !empty($trigger['enabled']),
            'message' => sanitize_textarea_field($trigger['message'] ?? ''),
            'delay' => absint($trigger['delay'] ?? 0),
            'seconds' => absint($trigger['seconds'] ?? 30),
            'percentage' => absint($trigger['percentage'] ?? 50),
            'idle_minutes' => absint($trigger['idle_minutes'] ?? 5),
            'delay_seconds' => absint($trigger['delay_seconds'] ?? 10),
            'show_once_per_session' => !empty($trigger['show_once_per_session']),
        ];
    }

    /**
     * Handle settings reset via AJAX
     *
     * @since 1.0.0
     * @return void
     */
    public function handleSettingsReset(): void
    {
        // Verify nonce
        if (!check_ajax_referer('woo_ai_settings', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security verification failed.', 'woo-ai-assistant')]);
        }

        // Check capability
        if (!current_user_can(self::REQUIRED_CAPABILITY)) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'woo-ai-assistant')]);
        }

        // Reset to defaults
        $result = update_option(self::OPTION_NAME, $this->defaultSettings);

        if ($result) {
            wp_send_json_success([
                'message' => __('Settings reset to defaults.', 'woo-ai-assistant'),
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Error resetting settings.', 'woo-ai-assistant'),
            ]);
        }
    }

    /**
     * Handle API connection test via AJAX
     *
     * @since 1.0.0
     * @return void
     */
    public function handleApiTest(): void
    {
        // Verify nonce
        if (!check_ajax_referer('woo_ai_settings', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security verification failed.', 'woo-ai-assistant')]);
        }

        // Check capability
        if (!current_user_can(self::REQUIRED_CAPABILITY)) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'woo-ai-assistant')]);
        }

        $apiType = sanitize_text_field($_POST['api_type'] ?? 'server');
        $apiKey = sanitize_text_field($_POST['api_key'] ?? '');
        $apiUrl = sanitize_text_field($_POST['api_url'] ?? '');

        switch ($apiType) {
            case 'openai':
                $this->testOpenAiConnection($apiKey);
                break;
            case 'pinecone':
                $this->testPineconeConnection($apiKey);
                break;
            case 'openrouter':
                $this->testOpenRouterConnection($apiKey);
                break;
            case 'google':
                $this->testGoogleApiConnection($apiKey);
                break;
            case 'server':
            default:
                $this->testServerConnection($apiUrl);
                break;
        }
    }

    /**
     * Test OpenAI API connection
     *
     * @since 1.0.0
     * @param string $apiKey OpenAI API key
     * @return void
     */
    private function testOpenAiConnection(string $apiKey): void
    {
        if (empty($apiKey)) {
            wp_send_json_error(['message' => __('OpenAI API key is required.', 'woo-ai-assistant')]);
        }

        $response = wp_remote_get('https://api.openai.com/v1/models', [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Accept' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error([
                'message' => sprintf(
                    __('OpenAI connection failed: %s', 'woo-ai-assistant'),
                    $response->get_error_message()
                ),
            ]);
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($statusCode === 200) {
            $data = json_decode($body, true);
            $modelCount = isset($data['data']) ? count($data['data']) : 0;
            wp_send_json_success([
                'message' => sprintf(
                    __('OpenAI connection successful! Found %d available models.', 'woo-ai-assistant'),
                    $modelCount
                ),
            ]);
        } elseif ($statusCode === 401) {
            wp_send_json_error(['message' => __('OpenAI API key is invalid.', 'woo-ai-assistant')]);
        } else {
            wp_send_json_error([
                'message' => sprintf(
                    __('OpenAI connection failed with status code: %d', 'woo-ai-assistant'),
                    $statusCode
                ),
            ]);
        }
    }

    /**
     * Test Pinecone API connection
     *
     * @since 1.0.0
     * @param string $apiKey Pinecone API key
     * @return void
     */
    private function testPineconeConnection(string $apiKey): void
    {
        if (empty($apiKey)) {
            wp_send_json_error(['message' => __('Pinecone API key is required.', 'woo-ai-assistant')]);
        }

        $response = wp_remote_get('https://controller.pinecone.io/actions/whoami', [
            'timeout' => 15,
            'headers' => [
                'Api-Key' => $apiKey,
                'Accept' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error([
                'message' => sprintf(
                    __('Pinecone connection failed: %s', 'woo-ai-assistant'),
                    $response->get_error_message()
                ),
            ]);
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($statusCode === 200) {
            $data = json_decode($body, true);
            $userLabel = $data['user_label'] ?? 'Unknown';
            wp_send_json_success([
                'message' => sprintf(
                    __('Pinecone connection successful! User: %s', 'woo-ai-assistant'),
                    $userLabel
                ),
            ]);
        } elseif ($statusCode === 403) {
            wp_send_json_error(['message' => __('Pinecone API key is invalid.', 'woo-ai-assistant')]);
        } else {
            wp_send_json_error([
                'message' => sprintf(
                    __('Pinecone connection failed with status code: %d', 'woo-ai-assistant'),
                    $statusCode
                ),
            ]);
        }
    }

    /**
     * Test OpenRouter API connection
     *
     * @since 1.0.0
     * @param string $apiKey OpenRouter API key
     * @return void
     */
    private function testOpenRouterConnection(string $apiKey): void
    {
        if (empty($apiKey)) {
            wp_send_json_error(['message' => __('OpenRouter API key is required.', 'woo-ai-assistant')]);
        }

        $response = wp_remote_get('https://openrouter.ai/api/v1/auth/key', [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Accept' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error([
                'message' => sprintf(
                    __('OpenRouter connection failed: %s', 'woo-ai-assistant'),
                    $response->get_error_message()
                ),
            ]);
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($statusCode === 200) {
            $data = json_decode($body, true);
            $label = $data['data']['label'] ?? 'API Key';
            wp_send_json_success([
                'message' => sprintf(
                    __('OpenRouter connection successful! Key: %s', 'woo-ai-assistant'),
                    $label
                ),
            ]);
        } elseif ($statusCode === 401) {
            wp_send_json_error(['message' => __('OpenRouter API key is invalid.', 'woo-ai-assistant')]);
        } else {
            wp_send_json_error([
                'message' => sprintf(
                    __('OpenRouter connection failed with status code: %d', 'woo-ai-assistant'),
                    $statusCode
                ),
            ]);
        }
    }

    /**
     * Test Google Gemini API connection
     *
     * @since 1.0.0
     * @param string $apiKey Google API key
     * @return void
     */
    private function testGoogleApiConnection(string $apiKey): void
    {
        if (empty($apiKey)) {
            wp_send_json_error(['message' => __('Google API key is required.', 'woo-ai-assistant')]);
        }

        // Test with a simple model list request
        $response = wp_remote_get('https://generativelanguage.googleapis.com/v1beta/models?key=' . $apiKey, [
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error([
                'message' => sprintf(
                    __('Google API connection failed: %s', 'woo-ai-assistant'),
                    $response->get_error_message()
                ),
            ]);
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($statusCode === 200) {
            $data = json_decode($body, true);
            $modelCount = isset($data['models']) ? count($data['models']) : 0;
            wp_send_json_success([
                'message' => sprintf(
                    __('Google API connection successful! Found %d available models.', 'woo-ai-assistant'),
                    $modelCount
                ),
            ]);
        } elseif ($statusCode === 400) {
            wp_send_json_error(['message' => __('Google API key is invalid.', 'woo-ai-assistant')]);
        } else {
            wp_send_json_error([
                'message' => sprintf(
                    __('Google API connection failed with status code: %d', 'woo-ai-assistant'),
                    $statusCode
                ),
            ]);
        }
    }

    /**
     * Test intermediate server connection
     *
     * @since 1.0.0
     * @param string $apiUrl Server URL
     * @return void
     */
    private function testServerConnection(string $apiUrl): void
    {
        if (empty($apiUrl)) {
            wp_send_json_error(['message' => __('API URL is required.', 'woo-ai-assistant')]);
        }

        // Test connection
        $response = wp_remote_get($apiUrl . '/health', [
            'timeout' => 10,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error([
                'message' => sprintf(
                    __('Server connection failed: %s', 'woo-ai-assistant'),
                    $response->get_error_message()
                ),
            ]);
        }

        $statusCode = wp_remote_retrieve_response_code($response);

        if ($statusCode === 200) {
            wp_send_json_success([
                'message' => __('Server connection successful!', 'woo-ai-assistant'),
            ]);
        } else {
            wp_send_json_error([
                'message' => sprintf(
                    __('Server connection failed with status code: %d', 'woo-ai-assistant'),
                    $statusCode
                ),
            ]);
        }
    }

    /**
     * Handle license generation via AJAX
     *
     * @since 1.0.0
     * @return void
     */
    public function handleLicenseGeneration(): void
    {
        // Verify nonce
        if (!check_ajax_referer('woo_ai_settings', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security verification failed.', 'woo-ai-assistant')]);
        }

        // Check capability
        if (!current_user_can(self::REQUIRED_CAPABILITY)) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'woo-ai-assistant')]);
        }

        // Placeholder for license generation
        // In production, this would connect to your licensing server
        wp_send_json_success([
            'message' => __('License generation is not available in development mode.', 'woo-ai-assistant'),
        ]);
    }

    /**
     * Get current settings
     *
     * @since 1.0.0
     * @param string|null $section Optional section to retrieve
     * @return array Settings array
     */
    public function getSettings(?string $section = null): array
    {
        if ($section && isset($this->settings[$section])) {
            return $this->settings[$section];
        }

        return $this->settings;
    }

    /**
     * Get single setting value
     *
     * @since 1.0.0
     * @param string $key Dot notation key (e.g., 'general.enabled')
     * @param mixed $default Default value if not found
     * @return mixed Setting value
     */
    public function getSetting(string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->settings;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Handle AJAX request to trigger knowledge base indexing
     *
     * @since 1.0.0
     * @return void
     */
    public function handleTriggerIndexing(): void
    {
        // Verify nonce
        if (!check_ajax_referer('woo_ai_settings', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security verification failed.', 'woo-ai-assistant')]);
        }

        // Check capability
        if (!current_user_can(self::REQUIRED_CAPABILITY)) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'woo-ai-assistant')]);
        }

        try {
            // Get the KnowledgeBase components
            $scanner = null;
            $indexer = null;

            if (class_exists('WooAiAssistant\\KnowledgeBase\\Scanner')) {
                $scanner = \WooAiAssistant\KnowledgeBase\Scanner::getInstance();
            }

            if (class_exists('WooAiAssistant\\KnowledgeBase\\Indexer')) {
                $indexer = \WooAiAssistant\KnowledgeBase\Indexer::getInstance();
            }

            if (!$scanner || !$indexer) {
                wp_send_json_error(['message' => __('Knowledge Base components not available.', 'woo-ai-assistant')]);
                return;
            }

            // Start indexing process
            $results = [
                'products' => 0,
                'pages' => 0,
                'posts' => 0,
                'chunks' => 0
            ];

            // Scan products
            $products = $scanner->scanProducts(['limit' => 100]);
            $results['products'] = count($products);

            // Index products
            foreach ($products as $product) {
                $chunks = $indexer->processContent(
                    $product['content'] ?? '',
                    $product['id'] ?? 0,
                    'product',
                    $product
                );
                $results['chunks'] += count($chunks);
            }

            // Scan pages
            $pages = $scanner->scanPages(['limit' => 50]);
            $results['pages'] = count($pages);

            // Index pages
            foreach ($pages as $page) {
                $chunks = $indexer->processContent(
                    $page['content'] ?? '',
                    $page['id'] ?? 0,
                    'page',
                    $page
                );
                $results['chunks'] += count($chunks);
            }

            // Update last index time
            update_option('woo_ai_assistant_last_index_time', current_time('mysql'));

            wp_send_json_success([
                'message' => sprintf(
                    __('Indexing completed successfully! Processed %d products, %d pages, created %d chunks.', 'woo-ai-assistant'),
                    $results['products'],
                    $results['pages'],
                    $results['chunks']
                ),
                'results' => $results
            ]);
        } catch (\Exception $e) {
            Utils::logError('Knowledge Base indexing failed: ' . $e->getMessage());
            wp_send_json_error([
                'message' => sprintf(
                    __('Indexing failed: %s', 'woo-ai-assistant'),
                    $e->getMessage()
                )
            ]);
        }
    }
}