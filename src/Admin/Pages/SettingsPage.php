<?php

/**
 * Settings Page Class
 *
 * Handles the settings page in the admin interface.
 * Manages plugin configuration, API settings, and preferences.
 *
 * @package WooAiAssistant
 * @subpackage Admin\Pages
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Admin\Pages;

use WooAiAssistant\Common\Traits\Singleton;
use WooAiAssistant\Common\Logger;
use WooAiAssistant\Common\Utils;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SettingsPage
 *
 * Manages the settings admin page rendering and functionality.
 *
 * @since 1.0.0
 */
class SettingsPage
{
    use Singleton;

    /**
     * Page slug
     *
     * @var string
     */
    private string $pageSlug = 'woo-ai-assistant-settings';

    /**
     * Settings group name
     *
     * @var string
     */
    private string $settingsGroup = 'woo_ai_assistant_settings';

    /**
     * Option name for storing settings
     *
     * @var string
     */
    private string $optionName = 'woo_ai_assistant_options';

    /**
     * Initialize the settings page
     *
     * @return void
     */
    protected function init(): void
    {
        // Hook for processing form submissions
        add_action('admin_post_woo_ai_assistant_save_settings', [$this, 'handleFormSubmission']);

        Logger::debug('SettingsPage initialized');
    }

    /**
     * Register settings for this page
     *
     * @return void
     */
    public function registerSettings(): void
    {
        // Register the settings group
        register_setting($this->settingsGroup, $this->optionName, [
            'sanitize_callback' => [$this, 'sanitizeSettings'],
        ]);

        // General Settings Section
        add_settings_section(
            'general_settings',
            __('General Settings', 'woo-ai-assistant'),
            [$this, 'renderGeneralSectionDescription'],
            $this->pageSlug
        );

        // License Settings Section
        add_settings_section(
            'license_settings',
            __('License & API Settings', 'woo-ai-assistant'),
            [$this, 'renderLicenseSectionDescription'],
            $this->pageSlug
        );

        // Chat Behavior Section
        add_settings_section(
            'chat_behavior',
            __('Chat Behavior', 'woo-ai-assistant'),
            [$this, 'renderChatBehaviorSectionDescription'],
            $this->pageSlug
        );

        // Add settings fields
        $this->addSettingsFields();

        Logger::debug('Settings page registered');
    }

    /**
     * Add settings fields
     *
     * @return void
     */
    private function addSettingsFields(): void
    {
        // General Settings Fields
        add_settings_field(
            'enable_chat_widget',
            __('Enable Chat Widget', 'woo-ai-assistant'),
            [$this, 'renderCheckboxField'],
            $this->pageSlug,
            'general_settings',
            ['field_name' => 'enable_chat_widget', 'default' => true]
        );

        add_settings_field(
            'widget_position',
            __('Widget Position', 'woo-ai-assistant'),
            [$this, 'renderSelectField'],
            $this->pageSlug,
            'general_settings',
            [
                'field_name' => 'widget_position',
                'options' => [
                    'bottom-right' => __('Bottom Right', 'woo-ai-assistant'),
                    'bottom-left' => __('Bottom Left', 'woo-ai-assistant'),
                    'top-right' => __('Top Right', 'woo-ai-assistant'),
                    'top-left' => __('Top Left', 'woo-ai-assistant'),
                ],
                'default' => 'bottom-right'
            ]
        );

        // License Settings Fields
        add_settings_field(
            'license_key',
            __('License Key', 'woo-ai-assistant'),
            [$this, 'renderTextInputField'],
            $this->pageSlug,
            'license_settings',
            ['field_name' => 'license_key', 'type' => 'password']
        );

        // Chat Behavior Fields
        add_settings_field(
            'welcome_message',
            __('Welcome Message', 'woo-ai-assistant'),
            [$this, 'renderTextareaField'],
            $this->pageSlug,
            'chat_behavior',
            [
                'field_name' => 'welcome_message',
                'default' => __('Hi! How can I help you today?', 'woo-ai-assistant'),
                'rows' => 3
            ]
        );

        add_settings_field(
            'chat_language',
            __('Chat Language', 'woo-ai-assistant'),
            [$this, 'renderSelectField'],
            $this->pageSlug,
            'chat_behavior',
            [
                'field_name' => 'chat_language',
                'options' => [
                    'en' => __('English', 'woo-ai-assistant'),
                    'es' => __('Spanish', 'woo-ai-assistant'),
                    'fr' => __('French', 'woo-ai-assistant'),
                    'de' => __('German', 'woo-ai-assistant'),
                    'it' => __('Italian', 'woo-ai-assistant'),
                ],
                'default' => 'en'
            ]
        );

        add_settings_field(
            'max_conversation_length',
            __('Max Conversation Length', 'woo-ai-assistant'),
            [$this, 'renderNumberField'],
            $this->pageSlug,
            'chat_behavior',
            [
                'field_name' => 'max_conversation_length',
                'default' => 50,
                'min' => 10,
                'max' => 200,
                'description' => __('Maximum number of messages per conversation', 'woo-ai-assistant')
            ]
        );
    }

    /**
     * Render the settings page
     *
     * @return void
     */
    public function render(): void
    {
        // Security check
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'woo-ai-assistant'));
            return;
        }

        $current_settings = $this->getCurrentSettings();

        ?>
        <div class="wrap woo-ai-assistant-settings">
            <h1 class="wp-heading-inline">
                <?php esc_html_e('Woo AI Assistant Settings', 'woo-ai-assistant'); ?>
            </h1>

            <?php settings_errors(); ?>
            <?php $this->renderDevelopmentNotice(); ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php
                wp_nonce_field('woo_ai_assistant_settings_nonce', 'woo_ai_assistant_settings_nonce');
                ?>
                <input type="hidden" name="action" value="woo_ai_assistant_save_settings">

                <?php do_settings_sections($this->pageSlug); ?>

                <div class="woo-ai-assistant-settings-actions">
                    <?php submit_button(__('Save Settings', 'woo-ai-assistant'), 'primary', 'submit', false); ?>
                    <button type="button" class="button button-secondary" id="reset-to-defaults">
                        <?php esc_html_e('Reset to Defaults', 'woo-ai-assistant'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="test-connection">
                        <?php esc_html_e('Test Connection', 'woo-ai-assistant'); ?>
                    </button>
                </div>
            </form>

            <!-- Advanced Settings (Collapsible) -->
            <div class="woo-ai-assistant-advanced-settings">
                <h2 class="toggle-advanced">
                    <?php esc_html_e('Advanced Settings', 'woo-ai-assistant'); ?>
                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                </h2>
                <div class="advanced-settings-content" style="display: none;">
                    <div class="woo-ai-assistant-card">
                        <div class="card-body">
                            <h3><?php esc_html_e('Debug & Development', 'woo-ai-assistant'); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Debug Mode', 'woo-ai-assistant'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="debug_mode" value="1" <?php checked(Utils::isDevelopmentMode()); ?> />
                                            <?php esc_html_e('Enable debug logging', 'woo-ai-assistant'); ?>
                                        </label>
                                        <p class="description"><?php esc_html_e('Enable detailed logging for troubleshooting.', 'woo-ai-assistant'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Cache TTL', 'woo-ai-assistant'); ?></th>
                                    <td>
                                        <input type="number" name="cache_ttl" value="3600" min="60" max="86400" />
                                        <p class="description"><?php esc_html_e('Cache time-to-live in seconds.', 'woo-ai-assistant'); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Toggle advanced settings
            $('.toggle-advanced').click(function() {
                $('.advanced-settings-content').slideToggle();
                $(this).find('.dashicons').toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
            });

            // Reset to defaults
            $('#reset-to-defaults').click(function() {
                if (confirm('<?php esc_js_e('Are you sure you want to reset all settings to defaults?', 'woo-ai-assistant'); ?>')) {
                    // TODO: Implement reset functionality
                    alert('<?php esc_js_e('Reset functionality will be implemented in future updates.', 'woo-ai-assistant'); ?>');
                }
            });

            // Test connection
            $('#test-connection').click(function() {
                $(this).prop('disabled', true).text('<?php esc_js_e('Testing...', 'woo-ai-assistant'); ?>');
                
                // TODO: Implement connection test
                setTimeout(function() {
                    $('#test-connection').prop('disabled', false).text('<?php esc_js_e('Test Connection', 'woo-ai-assistant'); ?>');
                    alert('<?php esc_js_e('Connection test will be implemented in future updates.', 'woo-ai-assistant'); ?>');
                }, 2000);
            });
        });
        </script>
        <?php

        Logger::debug('Settings page rendered');
    }

    /**
     * Render development notice if in development mode
     *
     * @return void
     */
    private function renderDevelopmentNotice(): void
    {
        if (!Utils::isDevelopmentMode()) {
            return;
        }

        ?>
        <div class="notice notice-info woo-ai-assistant-notice">
            <p>
                <strong><?php esc_html_e('Development Mode Active', 'woo-ai-assistant'); ?></strong><br>
                <?php esc_html_e('Settings may behave differently in development mode. License validation is bypassed.', 'woo-ai-assistant'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Render section descriptions
     */
    public function renderGeneralSectionDescription(): void
    {
        echo '<p>' . esc_html__('Configure basic plugin settings and chat widget behavior.', 'woo-ai-assistant') . '</p>';
    }

    public function renderLicenseSectionDescription(): void
    {
        echo '<p>' . esc_html__('Enter your license key to activate premium features and API access.', 'woo-ai-assistant') . '</p>';
    }

    public function renderChatBehaviorSectionDescription(): void
    {
        echo '<p>' . esc_html__('Customize how the AI chatbot interacts with your customers.', 'woo-ai-assistant') . '</p>';
    }

    /**
     * Render different types of form fields
     */
    public function renderCheckboxField($args): void
    {
        $value = $this->getSettingValue($args['field_name'], $args['default'] ?? false);
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr($args['field_name']); ?>" value="1" <?php checked($value); ?> />
            <?php echo esc_html($args['label'] ?? __('Enable this option', 'woo-ai-assistant')); ?>
        </label>
        <?php if (!empty($args['description'])) : ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif;
    }

    public function renderSelectField($args): void
    {
        $value = $this->getSettingValue($args['field_name'], $args['default'] ?? '');
        ?>
        <select name="<?php echo esc_attr($args['field_name']); ?>">
            <?php foreach ($args['options'] as $option_value => $option_label) : ?>
                <option value="<?php echo esc_attr($option_value); ?>" <?php selected($value, $option_value); ?>>
                    <?php echo esc_html($option_label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (!empty($args['description'])) : ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif;
    }

    public function renderTextInputField($args): void
    {
        $value = $this->getSettingValue($args['field_name'], $args['default'] ?? '');
        $type = $args['type'] ?? 'text';
        ?>
        <input type="<?php echo esc_attr($type); ?>" name="<?php echo esc_attr($args['field_name']); ?>" 
               value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <?php if (!empty($args['description'])) : ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif;
    }

    public function renderTextareaField($args): void
    {
        $value = $this->getSettingValue($args['field_name'], $args['default'] ?? '');
        $rows = $args['rows'] ?? 4;
        ?>
        <textarea name="<?php echo esc_attr($args['field_name']); ?>" rows="<?php echo esc_attr($rows); ?>" 
                  class="large-text"><?php echo esc_textarea($value); ?></textarea>
        <?php if (!empty($args['description'])) : ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif;
    }

    public function renderNumberField($args): void
    {
        $value = $this->getSettingValue($args['field_name'], $args['default'] ?? 0);
        ?>
        <input type="number" name="<?php echo esc_attr($args['field_name']); ?>" 
               value="<?php echo esc_attr($value); ?>" 
               min="<?php echo esc_attr($args['min'] ?? 0); ?>" 
               max="<?php echo esc_attr($args['max'] ?? 9999); ?>" />
        <?php if (!empty($args['description'])) : ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif;
    }

    /**
     * Handle form submission
     *
     * @return void
     */
    public function handleFormSubmission(): void
    {
        // Security checks
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to save settings.', 'woo-ai-assistant'));
            return;
        }

        if (!wp_verify_nonce($_POST['woo_ai_assistant_settings_nonce'], 'woo_ai_assistant_settings_nonce')) {
            wp_die(__('Security check failed.', 'woo-ai-assistant'));
            return;
        }

        // Sanitize and save settings
        $settings = $this->sanitizeSettings($_POST);
        update_option($this->optionName, $settings);

        // Add success message
        add_settings_error(
            $this->optionName,
            'settings-saved',
            __('Settings saved successfully.', 'woo-ai-assistant'),
            'success'
        );

        // Redirect back to settings page
        wp_redirect(admin_url('admin.php?page=' . $this->pageSlug));
        exit;
    }

    /**
     * Sanitize settings before saving
     *
     * @param array $input Raw input data
     * @return array Sanitized settings
     */
    public function sanitizeSettings(array $input): array
    {
        $sanitized = [];

        // Sanitize each field based on type
        $sanitized['enable_chat_widget'] = !empty($input['enable_chat_widget']);
        $sanitized['widget_position'] = sanitize_text_field($input['widget_position'] ?? 'bottom-right');
        $sanitized['license_key'] = sanitize_text_field($input['license_key'] ?? '');
        $sanitized['welcome_message'] = sanitize_textarea_field($input['welcome_message'] ?? '');
        $sanitized['chat_language'] = sanitize_text_field($input['chat_language'] ?? 'en');
        $sanitized['max_conversation_length'] = absint($input['max_conversation_length'] ?? 50);

        Logger::debug('Settings sanitized', ['sanitized_count' => count($sanitized)]);

        return $sanitized;
    }

    /**
     * Get current settings
     *
     * @return array Current settings
     */
    private function getCurrentSettings(): array
    {
        return get_option($this->optionName, $this->getDefaultSettings());
    }

    /**
     * Get default settings
     *
     * @return array Default settings
     */
    private function getDefaultSettings(): array
    {
        return [
            'enable_chat_widget' => true,
            'widget_position' => 'bottom-right',
            'license_key' => '',
            'welcome_message' => __('Hi! How can I help you today?', 'woo-ai-assistant'),
            'chat_language' => 'en',
            'max_conversation_length' => 50,
        ];
    }

    /**
     * Get specific setting value
     *
     * @param string $field_name Setting field name
     * @param mixed $default Default value
     * @return mixed Setting value
     */
    private function getSettingValue(string $field_name, $default = null)
    {
        $settings = $this->getCurrentSettings();
        return $settings[$field_name] ?? $default;
    }

    /**
     * Get page slug
     *
     * @return string Page slug
     */
    public function getPageSlug(): string
    {
        return $this->pageSlug;
    }
}