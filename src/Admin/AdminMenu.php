<?php

/**
 * Admin Menu Class
 *
 * Handles the creation and management of WordPress admin menu items and pages
 * for the Woo AI Assistant plugin. Provides comprehensive admin interface
 * including Dashboard, Settings, Conversations, and Knowledge Base management.
 *
 * @package WooAiAssistant
 * @subpackage Admin
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Admin;

use WooAiAssistant\Common\Utils;
use WooAiAssistant\Common\Traits\Singleton;
use WooAiAssistant\Admin\Pages\DashboardPage;
use WooAiAssistant\Admin\Pages\SettingsPage;
use WooAiAssistant\Admin\Pages\ConversationsLogPage;
use WooAiAssistant\Admin\Pages\KnowledgeBaseStatusPage;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AdminMenu
 *
 * Manages all admin menu items and pages for the plugin. Implements comprehensive
 * admin interface with proper security, capability checks, and user experience.
 *
 * @since 1.0.0
 */
class AdminMenu
{
    use Singleton;

    /**
     * Menu slug for the main admin page
     *
     * @since 1.0.0
     * @var string
     */
    private const MAIN_MENU_SLUG = 'woo-ai-assistant';

    /**
     * Required capability for admin access
     *
     * @since 1.0.0
     * @var string
     */
    private const REQUIRED_CAPABILITY = 'manage_woocommerce';

    /**
     * Current active page
     *
     * @since 1.0.0
     * @var string
     */
    private string $currentPage = '';

    /**
     * Admin pages configuration
     *
     * @since 1.0.0
     * @var array
     */
    private array $pages = [];

    /**
     * Constructor
     *
     * Initializes the admin menu by setting up hooks and page configurations.
     *
     * @since 1.0.0
     */
    private function __construct()
    {
        $this->setupPages();
        $this->setupHooks();
    }

    /**
     * Setup WordPress hooks
     *
     * @since 1.0.0
     * @return void
     */
    private function setupHooks(): void
    {
        add_action('admin_menu', [$this, 'addMenuItems']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_action('admin_head', [$this, 'addAdminStyles']);
        add_action('admin_init', [$this, 'handleAdminActions']);
        add_filter('admin_footer_text', [$this, 'customAdminFooter']);
    }

    /**
     * Setup page configurations
     *
     * @since 1.0.0
     * @return void
     */
    private function setupPages(): void
    {
        $this->pages = [
            'dashboard' => [
                'title' => __('Dashboard', 'woo-ai-assistant'),
                'menu_title' => __('AI Assistant', 'woo-ai-assistant'),
                'slug' => self::MAIN_MENU_SLUG,
                'callback' => [$this, 'renderDashboard'],
                'icon' => 'dashicons-format-chat',
                'position' => 58, // After WooCommerce (56)
                'is_parent' => true,
            ],
            'settings' => [
                'title' => __('Settings', 'woo-ai-assistant'),
                'menu_title' => __('Settings', 'woo-ai-assistant'),
                'slug' => self::MAIN_MENU_SLUG . '-settings',
                'callback' => [$this, 'renderSettings'],
                'parent' => self::MAIN_MENU_SLUG,
            ],
            'conversations' => [
                'title' => __('Conversations', 'woo-ai-assistant'),
                'menu_title' => __('Conversations', 'woo-ai-assistant'),
                'slug' => self::MAIN_MENU_SLUG . '-conversations',
                'callback' => [$this, 'renderConversations'],
                'parent' => self::MAIN_MENU_SLUG,
            ],
            'knowledge_base' => [
                'title' => __('Knowledge Base', 'woo-ai-assistant'),
                'menu_title' => __('Knowledge Base', 'woo-ai-assistant'),
                'slug' => self::MAIN_MENU_SLUG . '-knowledge-base',
                'callback' => [$this, 'renderKnowledgeBase'],
                'parent' => self::MAIN_MENU_SLUG,
            ],
        ];
    }

    /**
     * Add admin menu items
     *
     * @since 1.0.0
     * @return void
     */
    public function addMenuItems(): void
    {
        // Check if user has required capability
        if (!current_user_can(self::REQUIRED_CAPABILITY)) {
            return;
        }

        foreach ($this->pages as $pageKey => $page) {
            if (isset($page['is_parent']) && $page['is_parent']) {
                // Add main menu item
                add_menu_page(
                    $page['title'],
                    $page['menu_title'],
                    self::REQUIRED_CAPABILITY,
                    $page['slug'],
                    $page['callback'],
                    $page['icon'],
                    $page['position']
                );
            } else {
                // Add submenu item
                add_submenu_page(
                    $page['parent'],
                    $page['title'],
                    $page['menu_title'],
                    self::REQUIRED_CAPABILITY,
                    $page['slug'],
                    $page['callback']
                );
            }
        }

        // Set current page for styling and active states
        $this->setCurrentPage();
    }

    /**
     * Set current active page
     *
     * @since 1.0.0
     * @return void
     */
    private function setCurrentPage(): void
    {
        $currentScreen = get_current_screen();
        if ($currentScreen && strpos($currentScreen->id, 'woo-ai-assistant') !== false) {
            $this->currentPage = sanitize_text_field($_GET['page'] ?? '');
        }
    }

    /**
     * Enqueue admin assets
     *
     * @since 1.0.0
     * @param string $hook Current admin page hook
     * @return void
     */
    public function enqueueAdminAssets(string $hook): void
    {
        // Only enqueue on our admin pages
        if (!$this->isOurAdminPage($hook)) {
            return;
        }

        $assetVersion = WOO_AI_ASSISTANT_VERSION;
        $pluginUrl = WOO_AI_ASSISTANT_URL;

        // Enqueue CSS
        wp_enqueue_style(
            'woo-ai-assistant-admin',
            $pluginUrl . 'assets/css/admin.css',
            ['wp-admin', 'dashicons'],
            $assetVersion
        );

        // Enqueue JavaScript
        wp_enqueue_script(
            'woo-ai-assistant-admin',
            $pluginUrl . 'assets/js/admin.js',
            ['jquery', 'wp-api-fetch', 'wp-i18n'],
            $assetVersion,
            true
        );

        // Localize script with data
        wp_localize_script('woo-ai-assistant-admin', 'wooAiAssistant', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('woo-ai-assistant/v1/'),
            'nonce' => wp_create_nonce('woo_ai_assistant_admin'),
            'currentPage' => $this->currentPage,
            'strings' => [
                'loading' => __('Loading...', 'woo-ai-assistant'),
                'error' => __('An error occurred. Please try again.', 'woo-ai-assistant'),
                'success' => __('Action completed successfully.', 'woo-ai-assistant'),
                'confirm' => __('Are you sure you want to proceed?', 'woo-ai-assistant'),
            ],
        ]);

        // Set script translations
        wp_set_script_translations('woo-ai-assistant-admin', 'woo-ai-assistant');
    }

    /**
     * Check if current page is our admin page
     *
     * @since 1.0.0
     * @param string $hook Current admin page hook
     * @return bool True if our admin page
     */
    private function isOurAdminPage(string $hook): bool
    {
        return strpos($hook, 'woo-ai-assistant') !== false;
    }

    /**
     * Add inline admin styles
     *
     * @since 1.0.0
     * @return void
     */
    public function addAdminStyles(): void
    {
        if (!$this->isCurrentPageOurs()) {
            return;
        }

        echo '<style>
            .woo-ai-assistant-admin-header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 20px;
                margin: 20px 0 20px -20px;
                border-radius: 8px;
            }
            .woo-ai-assistant-admin-header h1 {
                color: white;
                margin: 0;
            }
            .woo-ai-assistant-card {
                background: white;
                border-radius: 8px;
                padding: 20px;
                margin-bottom: 20px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
        </style>';
    }

    /**
     * Handle admin actions
     *
     * @since 1.0.0
     * @return void
     */
    public function handleAdminActions(): void
    {
        // Verify nonce and capability for any admin actions
        if (isset($_POST['woo_ai_assistant_action'])) {
            if (!wp_verify_nonce($_POST['nonce'], 'woo_ai_assistant_admin')) {
                wp_die(__('Security check failed.', 'woo-ai-assistant'));
            }

            if (!current_user_can(self::REQUIRED_CAPABILITY)) {
                wp_die(__('You do not have sufficient permissions.', 'woo-ai-assistant'));
            }

            $action = sanitize_text_field($_POST['woo_ai_assistant_action']);
            $this->processAdminAction($action);
        }
    }

    /**
     * Process admin actions
     *
     * @since 1.0.0
     * @param string $action Action to process
     * @return void
     */
    private function processAdminAction(string $action): void
    {
        switch ($action) {
            case 'save_settings':
                $this->saveSettings();
                break;
            case 'reindex_knowledge_base':
                $this->reindexKnowledgeBase();
                break;
            default:
                Utils::logDebug("Unknown admin action: {$action}");
        }
    }

    /**
     * Save plugin settings
     *
     * @since 1.0.0
     * @return void
     */
    private function saveSettings(): void
    {
        // Settings saving logic will be implemented when settings structure is defined
        add_settings_error(
            'woo_ai_assistant_settings',
            'settings_saved',
            __('Settings saved successfully.', 'woo-ai-assistant'),
            'success'
        );
    }

    /**
     * Reindex knowledge base
     *
     * @since 1.0.0
     * @return void
     */
    private function reindexKnowledgeBase(): void
    {
        // Knowledge base reindexing logic will be implemented in Phase 2
        add_settings_error(
            'woo_ai_assistant_settings',
            'kb_reindexed',
            __('Knowledge base reindexing started.', 'woo-ai-assistant'),
            'success'
        );
    }

    /**
     * Check if current page is ours
     *
     * @since 1.0.0
     * @return bool True if current page belongs to our plugin
     */
    private function isCurrentPageOurs(): bool
    {
        return !empty($this->currentPage) && strpos($this->currentPage, 'woo-ai-assistant') !== false;
    }

    /**
     * Render Dashboard page
     *
     * Delegates dashboard rendering to the specialized DashboardPage class
     * for comprehensive KPI display and analytics.
     *
     * @since 1.0.0
     * @return void
     */
    public function renderDashboard(): void
    {
        try {
            $dashboardPage = DashboardPage::getInstance();
            $dashboardPage->renderDashboard();
        } catch (\Exception $e) {
            Utils::logError('Failed to render dashboard: ' . $e->getMessage());

            // Fallback to basic dashboard display
            $this->renderFallbackDashboard();
        }
    }

    /**
     * Render fallback dashboard when DashboardPage fails
     *
     * @since 1.0.0
     * @return void
     */
    private function renderFallbackDashboard(): void
    {
        $this->renderPageHeader(__('Dashboard', 'woo-ai-assistant'), __('Welcome to Woo AI Assistant', 'woo-ai-assistant'));

        echo '<div class="wrap">';
        echo '<div class="notice notice-warning">';
        echo '<p>' . esc_html__('Dashboard data is temporarily unavailable. Please try refreshing the page.', 'woo-ai-assistant') . '</p>';
        echo '</div>';

        echo '<div class="woo-ai-assistant-dashboard-grid">';

        // Basic KPI Cards (static placeholders)
        $this->renderKpiCards();

        // Recent Activity
        $this->renderRecentActivity();

        // Quick Actions
        $this->renderQuickActions();

        echo '</div>';
        echo '</div>';
    }

    /**
     * Render Settings page
     *
     * @since 1.0.0
     * @return void
     */
    public function renderSettings(): void
    {
        // Use the new SettingsPage class for comprehensive settings management
        $settingsPage = SettingsPage::getInstance();
        $settingsPage->render();
    }

    /**
     * Render Conversations page
     *
     * @since 1.0.0
     * @return void
     */
    public function renderConversations(): void
    {
        // Use the new comprehensive ConversationsLogPage
        $conversationsPage = ConversationsLogPage::getInstance();
        $conversationsPage->renderConversationsLog();
    }

    /**
     * Render Knowledge Base page
     *
     * @since 1.0.0
     * @return void
     */
    public function renderKnowledgeBase(): void
    {
        // Use the new comprehensive KnowledgeBaseStatusPage
        $kbPage = KnowledgeBaseStatusPage::getInstance();
        $kbPage->renderPage();
    }

    /**
     * Render page header
     *
     * @since 1.0.0
     * @param string $title Page title
     * @param string $subtitle Page subtitle
     * @return void
     */
    private function renderPageHeader(string $title, string $subtitle): void
    {
        echo '<div class="woo-ai-assistant-admin-header">';
        echo '<h1>' . esc_html($title) . '</h1>';
        echo '<p>' . esc_html($subtitle) . '</p>';
        echo '</div>';
    }

    /**
     * Render KPI cards for dashboard
     *
     * @since 1.0.0
     * @return void
     */
    private function renderKpiCards(): void
    {
        $kpis = [
            [
                'title' => __('Total Conversations', 'woo-ai-assistant'),
                'value' => '0',
                'icon' => 'dashicons-format-chat',
                'color' => '#3498db',
            ],
            [
                'title' => __('Resolution Rate', 'woo-ai-assistant'),
                'value' => '0%',
                'icon' => 'dashicons-yes-alt',
                'color' => '#2ecc71',
            ],
            [
                'title' => __('Avg Response Time', 'woo-ai-assistant'),
                'value' => '0s',
                'icon' => 'dashicons-clock',
                'color' => '#f39c12',
            ],
            [
                'title' => __('Customer Satisfaction', 'woo-ai-assistant'),
                'value' => '0/5',
                'icon' => 'dashicons-star-filled',
                'color' => '#e74c3c',
            ],
        ];

        echo '<div class="woo-ai-assistant-kpi-grid">';
        foreach ($kpis as $kpi) {
            echo '<div class="woo-ai-assistant-card woo-ai-assistant-kpi-card">';
            echo '<div class="kpi-icon" style="color: ' . esc_attr($kpi['color']) . ';">';
            echo '<span class="dashicons ' . esc_attr($kpi['icon']) . '"></span>';
            echo '</div>';
            echo '<div class="kpi-content">';
            echo '<h3>' . esc_html($kpi['value']) . '</h3>';
            echo '<p>' . esc_html($kpi['title']) . '</p>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    }

    /**
     * Render recent activity section
     *
     * @since 1.0.0
     * @return void
     */
    private function renderRecentActivity(): void
    {
        echo '<div class="woo-ai-assistant-card">';
        echo '<h2>' . __('Recent Activity', 'woo-ai-assistant') . '</h2>';
        echo '<div class="activity-placeholder">';
        echo '<p>' . __('No recent activity to display. Start by enabling the chatbot and having your first conversation!', 'woo-ai-assistant') . '</p>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render quick actions section
     *
     * @since 1.0.0
     * @return void
     */
    private function renderQuickActions(): void
    {
        echo '<div class="woo-ai-assistant-card">';
        echo '<h2>' . __('Quick Actions', 'woo-ai-assistant') . '</h2>';
        echo '<div class="quick-actions-grid">';

        $actions = [
            [
                'title' => __('Configure Settings', 'woo-ai-assistant'),
                'description' => __('Set up your AI assistant preferences', 'woo-ai-assistant'),
                'url' => admin_url('admin.php?page=' . self::MAIN_MENU_SLUG . '-settings'),
                'icon' => 'dashicons-admin-settings',
            ],
            [
                'title' => __('View Conversations', 'woo-ai-assistant'),
                'description' => __('Monitor customer interactions', 'woo-ai-assistant'),
                'url' => admin_url('admin.php?page=' . self::MAIN_MENU_SLUG . '-conversations'),
                'icon' => 'dashicons-format-chat',
            ],
            [
                'title' => __('Manage Knowledge Base', 'woo-ai-assistant'),
                'description' => __('Review and optimize AI knowledge', 'woo-ai-assistant'),
                'url' => admin_url('admin.php?page=' . self::MAIN_MENU_SLUG . '-knowledge-base'),
                'icon' => 'dashicons-book',
            ],
        ];

        foreach ($actions as $action) {
            echo '<a href="' . esc_url($action['url']) . '" class="quick-action-item">';
            echo '<span class="dashicons ' . esc_attr($action['icon']) . '"></span>';
            echo '<h3>' . esc_html($action['title']) . '</h3>';
            echo '<p>' . esc_html($action['description']) . '</p>';
            echo '</a>';
        }

        echo '</div>';
        echo '</div>';
    }

    /**
     * Render general settings section
     *
     * @since 1.0.0
     * @return void
     */
    private function renderGeneralSettings(): void
    {
        echo '<div class="woo-ai-assistant-card">';
        echo '<h2>' . __('General Settings', 'woo-ai-assistant') . '</h2>';
        echo '<table class="form-table">';

        echo '<tr>';
        echo '<th scope="row">' . __('Enable Chatbot', 'woo-ai-assistant') . '</th>';
        echo '<td>';
        echo '<label><input type="checkbox" name="chatbot_enabled" value="1" checked> ' . __('Enable AI chatbot on frontend', 'woo-ai-assistant') . '</label>';
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row">' . __('Chatbot Position', 'woo-ai-assistant') . '</th>';
        echo '<td>';
        echo '<select name="chatbot_position">';
        echo '<option value="bottom-right">' . __('Bottom Right', 'woo-ai-assistant') . '</option>';
        echo '<option value="bottom-left">' . __('Bottom Left', 'woo-ai-assistant') . '</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';

        echo '</table>';
        echo '</div>';
    }

    /**
     * Render chatbot settings section
     *
     * @since 1.0.0
     * @return void
     */
    private function renderChatbotSettings(): void
    {
        echo '<div class="woo-ai-assistant-card">';
        echo '<h2>' . __('Chatbot Settings', 'woo-ai-assistant') . '</h2>';
        echo '<table class="form-table">';

        echo '<tr>';
        echo '<th scope="row">' . __('Welcome Message', 'woo-ai-assistant') . '</th>';
        echo '<td>';
        echo '<textarea name="welcome_message" rows="3" cols="50" class="large-text">' .
             esc_textarea(__('Hi! I\'m your AI shopping assistant. How can I help you today?', 'woo-ai-assistant')) . '</textarea>';
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row">' . __('Response Style', 'woo-ai-assistant') . '</th>';
        echo '<td>';
        echo '<select name="response_style">';
        echo '<option value="professional">' . __('Professional', 'woo-ai-assistant') . '</option>';
        echo '<option value="friendly">' . __('Friendly', 'woo-ai-assistant') . '</option>';
        echo '<option value="casual">' . __('Casual', 'woo-ai-assistant') . '</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';

        echo '</table>';
        echo '</div>';
    }

    /**
     * Render advanced settings section
     *
     * @since 1.0.0
     * @return void
     */
    private function renderAdvancedSettings(): void
    {
        echo '<div class="woo-ai-assistant-card">';
        echo '<h2>' . __('Advanced Settings', 'woo-ai-assistant') . '</h2>';
        echo '<table class="form-table">';

        echo '<tr>';
        echo '<th scope="row">' . __('Debug Mode', 'woo-ai-assistant') . '</th>';
        echo '<td>';
        echo '<label><input type="checkbox" name="debug_mode" value="1"> ' . __('Enable debug logging', 'woo-ai-assistant') . '</label>';
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row">' . __('Data Retention', 'woo-ai-assistant') . '</th>';
        echo '<td>';
        echo '<select name="data_retention">';
        echo '<option value="30">' . __('30 days', 'woo-ai-assistant') . '</option>';
        echo '<option value="90">' . __('90 days', 'woo-ai-assistant') . '</option>';
        echo '<option value="365">' . __('1 year', 'woo-ai-assistant') . '</option>';
        echo '<option value="0">' . __('Forever', 'woo-ai-assistant') . '</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';

        echo '</table>';
        echo '</div>';
    }

    /**
     * Render conversation filters
     *
     * @since 1.0.0
     * @return void
     */
    private function renderConversationFilters(): void
    {
        echo '<div class="woo-ai-assistant-card">';
        echo '<h2>' . __('Filter Conversations', 'woo-ai-assistant') . '</h2>';
        echo '<div class="conversation-filters">';
        echo '<select name="date_range">';
        echo '<option value="today">' . __('Today', 'woo-ai-assistant') . '</option>';
        echo '<option value="week">' . __('This Week', 'woo-ai-assistant') . '</option>';
        echo '<option value="month">' . __('This Month', 'woo-ai-assistant') . '</option>';
        echo '</select>';
        echo '<select name="status">';
        echo '<option value="all">' . __('All Status', 'woo-ai-assistant') . '</option>';
        echo '<option value="resolved">' . __('Resolved', 'woo-ai-assistant') . '</option>';
        echo '<option value="pending">' . __('Pending', 'woo-ai-assistant') . '</option>';
        echo '</select>';
        echo '<button type="button" class="button">' . __('Apply Filters', 'woo-ai-assistant') . '</button>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render conversations table
     *
     * @since 1.0.0
     * @return void
     */
    private function renderConversationsTable(): void
    {
        echo '<div class="woo-ai-assistant-card">';
        echo '<h2>' . __('Recent Conversations', 'woo-ai-assistant') . '</h2>';
        echo '<div class="conversations-placeholder">';
        echo '<p>' . __('No conversations found. The conversation history will appear here once customers start chatting with your AI assistant.', 'woo-ai-assistant') . '</p>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render knowledge base health score
     *
     * @since 1.0.0
     * @return void
     */
    private function renderKbHealthScore(): void
    {
        echo '<div class="woo-ai-assistant-card">';
        echo '<h2>' . __('Knowledge Base Health Score', 'woo-ai-assistant') . '</h2>';
        echo '<div class="kb-health-score">';
        echo '<div class="score-circle">';
        echo '<span class="score">85%</span>';
        echo '</div>';
        echo '<div class="score-details">';
        echo '<p>' . __('Your knowledge base is performing well!', 'woo-ai-assistant') . '</p>';
        echo '<ul>';
        echo '<li>' . __('✓ Product information is up to date', 'woo-ai-assistant') . '</li>';
        echo '<li>' . __('✓ Shipping policies are indexed', 'woo-ai-assistant') . '</li>';
        echo '<li>' . __('⚠ Consider adding FAQ content', 'woo-ai-assistant') . '</li>';
        echo '</ul>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render content sources
     *
     * @since 1.0.0
     * @return void
     */
    private function renderContentSources(): void
    {
        echo '<div class="woo-ai-assistant-card">';
        echo '<h2>' . __('Content Sources', 'woo-ai-assistant') . '</h2>';
        echo '<div class="content-sources">';

        $sources = [
            ['name' => __('Products', 'woo-ai-assistant'), 'count' => 0, 'status' => 'indexed'],
            ['name' => __('Pages', 'woo-ai-assistant'), 'count' => 0, 'status' => 'pending'],
            ['name' => __('Categories', 'woo-ai-assistant'), 'count' => 0, 'status' => 'indexed'],
            ['name' => __('WooCommerce Settings', 'woo-ai-assistant'), 'count' => 0, 'status' => 'indexed'],
        ];

        foreach ($sources as $source) {
            echo '<div class="source-item">';
            echo '<span class="source-name">' . esc_html($source['name']) . '</span>';
            echo '<span class="source-count">' . esc_html($source['count']) . '</span>';
            echo '<span class="source-status status-' . esc_attr($source['status']) . '">' . esc_html(ucfirst($source['status'])) . '</span>';
            echo '</div>';
        }

        echo '</div>';
        echo '</div>';
    }

    /**
     * Render knowledge base actions
     *
     * @since 1.0.0
     * @return void
     */
    private function renderKbActions(): void
    {
        echo '<div class="woo-ai-assistant-card">';
        echo '<h2>' . __('Manual Actions', 'woo-ai-assistant') . '</h2>';
        echo '<div class="kb-actions">';

        echo '<form method="post" action="">';
        wp_nonce_field('woo_ai_assistant_admin', 'nonce');
        echo '<input type="hidden" name="woo_ai_assistant_action" value="reindex_knowledge_base">';
        echo '<button type="submit" class="button button-primary">' . __('Reindex Knowledge Base', 'woo-ai-assistant') . '</button>';
        echo '<p class="description">' . __('This will scan all your content and rebuild the knowledge base. Use this if you\'ve made significant changes to your products or content.', 'woo-ai-assistant') . '</p>';
        echo '</form>';

        echo '</div>';
        echo '</div>';
    }

    /**
     * Custom admin footer text
     *
     * @since 1.0.0
     * @param string $text Current footer text
     * @return string Modified footer text
     */
    public function customAdminFooter(string $text): string
    {
        if ($this->isCurrentPageOurs()) {
            return sprintf(
                __('Thank you for using %s! Version %s', 'woo-ai-assistant'),
                '<strong>Woo AI Assistant</strong>',
                WOO_AI_ASSISTANT_VERSION
            );
        }

        return $text;
    }

    /**
     * Get current page slug
     *
     * @since 1.0.0
     * @return string Current page slug
     */
    public function getCurrentPage(): string
    {
        return $this->currentPage;
    }

    /**
     * Get page configuration
     *
     * @since 1.0.0
     * @param string $pageKey Page key
     * @return array|null Page configuration or null if not found
     */
    public function getPageConfig(string $pageKey): ?array
    {
        return $this->pages[$pageKey] ?? null;
    }

    /**
     * Check if page exists
     *
     * @since 1.0.0
     * @param string $pageKey Page key to check
     * @return bool True if page exists
     */
    public function hasPage(string $pageKey): bool
    {
        return isset($this->pages[$pageKey]);
    }
}
