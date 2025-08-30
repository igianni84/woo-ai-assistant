<?php

/**
 * Dashboard Page Class
 *
 * Handles the main dashboard page in the admin interface.
 * Displays plugin overview, statistics, and quick actions.
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
 * Class DashboardPage
 *
 * Manages the dashboard admin page rendering and functionality.
 *
 * @since 1.0.0
 */
class DashboardPage
{
    use Singleton;

    /**
     * Page slug
     *
     * @var string
     */
    private string $pageSlug = 'woo-ai-assistant';

    /**
     * Initialize the dashboard page
     *
     * @return void
     */
    protected function init(): void
    {
        // Page will be initialized when needed
        Logger::debug('DashboardPage initialized');
    }

    /**
     * Render the dashboard page
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

        $plugin_info = $this->getPluginInfo();
        $stats = $this->getDashboardStats();

        ?>
        <div class="wrap woo-ai-assistant-dashboard">
            <h1 class="wp-heading-inline">
                <?php esc_html_e('Woo AI Assistant Dashboard', 'woo-ai-assistant'); ?>
            </h1>
            
            <?php $this->renderDevelopmentNotice(); ?>
            
            <div class="woo-ai-assistant-dashboard-content">
                
                <!-- Welcome Card -->
                <div class="woo-ai-assistant-card welcome-card">
                    <div class="card-header">
                        <h2><?php esc_html_e('Welcome to Woo AI Assistant', 'woo-ai-assistant'); ?></h2>
                    </div>
                    <div class="card-body">
                        <p><?php esc_html_e('AI-powered chatbot for WooCommerce with zero-config knowledge base and 24/7 customer support.', 'woo-ai-assistant'); ?></p>
                        <div class="welcome-actions">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=woo-ai-assistant-settings')); ?>" class="button button-primary">
                                <?php esc_html_e('Configure Settings', 'woo-ai-assistant'); ?>
                            </a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=woo-ai-assistant-conversations')); ?>" class="button button-secondary">
                                <?php esc_html_e('View Conversations', 'woo-ai-assistant'); ?>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Stats Overview -->
                <div class="woo-ai-assistant-stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">📊</div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo esc_html($stats['total_conversations']); ?></div>
                            <div class="stat-label"><?php esc_html_e('Total Conversations', 'woo-ai-assistant'); ?></div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">💬</div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo esc_html($stats['today_conversations']); ?></div>
                            <div class="stat-label"><?php esc_html_e('Today\'s Conversations', 'woo-ai-assistant'); ?></div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">📚</div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo esc_html($stats['kb_items']); ?></div>
                            <div class="stat-label"><?php esc_html_e('Knowledge Base Items', 'woo-ai-assistant'); ?></div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">⭐</div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo esc_html($stats['avg_rating']); ?>%</div>
                            <div class="stat-label"><?php esc_html_e('Customer Satisfaction', 'woo-ai-assistant'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="woo-ai-assistant-card quick-actions-card">
                    <div class="card-header">
                        <h3><?php esc_html_e('Quick Actions', 'woo-ai-assistant'); ?></h3>
                    </div>
                    <div class="card-body">
                        <div class="quick-actions-grid">
                            <div class="quick-action">
                                <button type="button" class="quick-action-btn" data-action="scan-knowledge-base">
                                    <span class="action-icon">🔍</span>
                                    <span class="action-label"><?php esc_html_e('Scan Knowledge Base', 'woo-ai-assistant'); ?></span>
                                </button>
                                <p class="action-description"><?php esc_html_e('Update the AI knowledge base with latest products and content.', 'woo-ai-assistant'); ?></p>
                            </div>

                            <div class="quick-action">
                                <button type="button" class="quick-action-btn" data-action="test-chat">
                                    <span class="action-icon">🧪</span>
                                    <span class="action-label"><?php esc_html_e('Test Chat Widget', 'woo-ai-assistant'); ?></span>
                                </button>
                                <p class="action-description"><?php esc_html_e('Test the chat widget functionality on your site.', 'woo-ai-assistant'); ?></p>
                            </div>

                            <div class="quick-action">
                                <button type="button" class="quick-action-btn" data-action="export-conversations">
                                    <span class="action-icon">📥</span>
                                    <span class="action-label"><?php esc_html_e('Export Data', 'woo-ai-assistant'); ?></span>
                                </button>
                                <p class="action-description"><?php esc_html_e('Export conversations and analytics data.', 'woo-ai-assistant'); ?></p>
                            </div>

                            <div class="quick-action">
                                <a href="<?php echo esc_url(admin_url('admin.php?page=woo-ai-assistant-settings')); ?>" class="quick-action-btn">
                                    <span class="action-icon">⚙️</span>
                                    <span class="action-label"><?php esc_html_e('Plugin Settings', 'woo-ai-assistant'); ?></span>
                                </a>
                                <p class="action-description"><?php esc_html_e('Configure plugin settings and preferences.', 'woo-ai-assistant'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Status -->
                <div class="woo-ai-assistant-card system-status-card">
                    <div class="card-header">
                        <h3><?php esc_html_e('System Status', 'woo-ai-assistant'); ?></h3>
                    </div>
                    <div class="card-body">
                        <div class="status-grid">
                            <div class="status-item">
                                <span class="status-label"><?php esc_html_e('Plugin Version:', 'woo-ai-assistant'); ?></span>
                                <span class="status-value"><?php echo esc_html($plugin_info['version']); ?></span>
                            </div>
                            
                            <div class="status-item">
                                <span class="status-label"><?php esc_html_e('WooCommerce:', 'woo-ai-assistant'); ?></span>
                                <span class="status-value status-<?php echo $plugin_info['woocommerce_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $plugin_info['woocommerce_active'] ? esc_html__('Active', 'woo-ai-assistant') : esc_html__('Inactive', 'woo-ai-assistant'); ?>
                                </span>
                            </div>
                            
                            <div class="status-item">
                                <span class="status-label"><?php esc_html_e('Development Mode:', 'woo-ai-assistant'); ?></span>
                                <span class="status-value status-<?php echo $plugin_info['development_mode'] ? 'development' : 'production'; ?>">
                                    <?php echo $plugin_info['development_mode'] ? esc_html__('Yes', 'woo-ai-assistant') : esc_html__('No', 'woo-ai-assistant'); ?>
                                </span>
                            </div>
                            
                            <div class="status-item">
                                <span class="status-label"><?php esc_html_e('Cache Status:', 'woo-ai-assistant'); ?></span>
                                <span class="status-value status-<?php echo $plugin_info['cache_enabled'] ? 'enabled' : 'disabled'; ?>">
                                    <?php echo $plugin_info['cache_enabled'] ? esc_html__('Enabled', 'woo-ai-assistant') : esc_html__('Disabled', 'woo-ai-assistant'); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <?php

        Logger::debug('Dashboard page rendered');
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
                <?php esc_html_e('The plugin is running in development mode. Debug logging is enabled and some features may behave differently.', 'woo-ai-assistant'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Get plugin information for display
     *
     * @return array Plugin information
     */
    private function getPluginInfo(): array
    {
        return [
            'version' => Utils::getVersion(),
            'woocommerce_active' => Utils::isWooCommerceActive(),
            'development_mode' => Utils::isDevelopmentMode(),
            'cache_enabled' => true, // Will be replaced with actual cache status
        ];
    }

    /**
     * Get dashboard statistics
     *
     * @return array Dashboard statistics
     */
    private function getDashboardStats(): array
    {
        // TODO: These will be replaced with actual database queries in future tasks
        return [
            'total_conversations' => 0,
            'today_conversations' => 0,
            'kb_items' => 0,
            'avg_rating' => 0,
        ];
    }

    /**
     * Register settings for this page
     *
     * @return void
     */
    public function registerSettings(): void
    {
        // Dashboard doesn't have specific settings
        // This method is called by AdminMenu but can be empty for dashboard

        Logger::debug('Dashboard page settings registered');
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

    /**
     * Handle AJAX requests for dashboard actions
     *
     * @return void
     */
    public function handleAjaxActions(): void
    {
        // TODO: This will be implemented when AJAX actions are needed

        Logger::debug('Dashboard AJAX actions placeholder');
    }
}