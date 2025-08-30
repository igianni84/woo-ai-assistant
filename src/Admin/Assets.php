<?php

/**
 * Admin Assets Manager Class
 *
 * Handles loading of CSS and JavaScript assets for the admin interface.
 * Manages asset enqueuing, dependencies, and conditional loading.
 *
 * @package WooAiAssistant
 * @subpackage Admin
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Admin;

use WooAiAssistant\Common\Traits\Singleton;
use WooAiAssistant\Common\Logger;
use WooAiAssistant\Common\Utils;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Assets
 *
 * Manages admin assets loading and optimization.
 *
 * @since 1.0.0
 */
class Assets
{
    use Singleton;

    /**
     * Asset version for cache busting
     *
     * @var string
     */
    private string $version;

    /**
     * Plugin URL for asset paths
     *
     * @var string
     */
    private string $pluginUrl;

    /**
     * Whether assets have been enqueued
     *
     * @var bool
     */
    private bool $assetsEnqueued = false;

    /**
     * Initialize the assets manager
     *
     * @return void
     */
    protected function init(): void
    {
        $this->version = Utils::getVersion();
        $this->pluginUrl = Utils::getPluginUrl();

        // Hook into WordPress admin asset system
        add_action('admin_enqueue_scripts', [$this, 'conditionallyEnqueueAssets']);

        // Register assets early
        add_action('admin_init', [$this, 'registerAssets'], 5);

        // Add inline styles for critical CSS
        add_action('admin_head', [$this, 'addInlineStyles']);

        Logger::debug('Assets manager initialized');
    }

    /**
     * Register all admin assets
     *
     * @return void
     */
    public function registerAssets(): void
    {
        // Register admin CSS
        wp_register_style(
            'woo-ai-assistant-admin',
            $this->pluginUrl . 'assets/css/admin.css',
            ['wp-components'],
            $this->version,
            'all'
        );

        // Register admin JavaScript (basic jQuery version for Task 1.1)
        wp_register_script(
            'woo-ai-assistant-admin',
            $this->pluginUrl . 'assets/js/admin-basic.js',
            ['jquery'],
            $this->version,
            true
        );

        // Register settings-specific assets
        wp_register_style(
            'woo-ai-assistant-settings',
            $this->pluginUrl . 'assets/css/admin-settings.css',
            ['woo-ai-assistant-admin'],
            $this->version,
            'all'
        );

        wp_register_script(
            'woo-ai-assistant-settings',
            $this->pluginUrl . 'assets/js/admin-settings.js',
            ['woo-ai-assistant-admin'],
            $this->version,
            true
        );

        // Register dashboard-specific assets
        wp_register_style(
            'woo-ai-assistant-dashboard',
            $this->pluginUrl . 'assets/css/admin-dashboard.css',
            ['woo-ai-assistant-admin'],
            $this->version,
            'all'
        );

        wp_register_script(
            'woo-ai-assistant-dashboard',
            $this->pluginUrl . 'assets/js/admin-dashboard.js',
            ['woo-ai-assistant-admin', 'chart-js'],
            $this->version,
            true
        );

        // Register conversations-specific assets
        wp_register_style(
            'woo-ai-assistant-conversations',
            $this->pluginUrl . 'assets/css/admin-conversations.css',
            ['woo-ai-assistant-admin'],
            $this->version,
            'all'
        );

        wp_register_script(
            'woo-ai-assistant-conversations',
            $this->pluginUrl . 'assets/js/admin-conversations.js',
            ['woo-ai-assistant-admin'],
            $this->version,
            true
        );

        // Register third-party dependencies if not already loaded
        if (!wp_script_is('chart-js', 'registered')) {
            wp_register_script(
                'chart-js',
                'https://cdn.jsdelivr.net/npm/chart.js',
                [],
                '3.9.1',
                true
            );
        }

        Logger::debug('Admin assets registered');
    }

    /**
     * Conditionally enqueue assets based on current admin page
     *
     * @param string $hookSuffix Current admin page hook suffix
     * @return void
     */
    public function conditionallyEnqueueAssets(string $hookSuffix): void
    {
        // Only load on plugin admin pages
        if (!$this->isPluginAdminPage()) {
            return;
        }

        $this->enqueueAdminAssets();

        Logger::debug('Admin assets conditionally enqueued', [
            'hook_suffix' => $hookSuffix
        ]);
    }

    /**
     * Enqueue admin assets
     *
     * @return void
     */
    public function enqueueAdminAssets(): void
    {
        if ($this->assetsEnqueued) {
            return;
        }

        // Enqueue common admin assets
        wp_enqueue_style('woo-ai-assistant-admin');
        wp_enqueue_script('woo-ai-assistant-admin');

        // Enqueue page-specific assets based on current page
        $currentPage = $this->getCurrentPageType();

        switch ($currentPage) {
            case 'settings':
                wp_enqueue_style('woo-ai-assistant-settings');
                wp_enqueue_script('woo-ai-assistant-settings');
                break;

            case 'dashboard':
                wp_enqueue_style('woo-ai-assistant-dashboard');
                wp_enqueue_script('woo-ai-assistant-dashboard');
                break;

            case 'conversations':
                wp_enqueue_style('woo-ai-assistant-conversations');
                wp_enqueue_script('woo-ai-assistant-conversations');
                break;
        }

        // Localize script with admin data
        wp_localize_script('woo-ai-assistant-admin', 'wooAiAssistantAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('woo-ai-assistant/v1/'),
            'nonce' => wp_create_nonce('woo_ai_assistant_admin'),
            'pluginUrl' => $this->pluginUrl,
            'version' => $this->version,
            'currentPage' => $currentPage,
            'developmentMode' => Utils::isDevelopmentMode(),
            'strings' => $this->getLocalizedStrings(),
        ]);

        $this->assetsEnqueued = true;

        Logger::debug('Admin assets enqueued', [
            'current_page' => $currentPage
        ]);
    }

    /**
     * Get localized strings for JavaScript
     *
     * @return array Localized strings
     */
    private function getLocalizedStrings(): array
    {
        return [
            'loading' => __('Loading...', 'woo-ai-assistant'),
            'saving' => __('Saving...', 'woo-ai-assistant'),
            'saved' => __('Saved!', 'woo-ai-assistant'),
            'error' => __('Error occurred', 'woo-ai-assistant'),
            'confirm' => __('Are you sure?', 'woo-ai-assistant'),
            'success' => __('Success!', 'woo-ai-assistant'),
            'failed' => __('Failed', 'woo-ai-assistant'),
            'cancel' => __('Cancel', 'woo-ai-assistant'),
            'ok' => __('OK', 'woo-ai-assistant'),
            'delete' => __('Delete', 'woo-ai-assistant'),
            'edit' => __('Edit', 'woo-ai-assistant'),
            'view' => __('View', 'woo-ai-assistant'),
            'refresh' => __('Refresh', 'woo-ai-assistant'),
        ];
    }

    /**
     * Add inline critical CSS to admin head
     *
     * @return void
     */
    public function addInlineStyles(): void
    {
        if (!$this->isPluginAdminPage()) {
            return;
        }

        // Critical CSS for immediate rendering
        ?>
        <style id="woo-ai-assistant-critical">
            .woo-ai-assistant-admin .wrap {
                margin-top: 20px;
            }
            .woo-ai-assistant-loading {
                display: inline-block;
                width: 20px;
                height: 20px;
                border: 3px solid rgba(0,0,0,.1);
                border-radius: 50%;
                border-top-color: #2271b1;
                animation: spin 1s ease-in-out infinite;
            }
            @keyframes spin {
                to { transform: rotate(360deg); }
            }
            .woo-ai-assistant-notice {
                margin: 5px 0 15px;
            }
        </style>
        <?php
    }

    /**
     * Check if current page is a plugin admin page
     *
     * @return bool True if on plugin admin page
     */
    private function isPluginAdminPage(): bool
    {
        $screen = get_current_screen();
        return $screen && strpos($screen->id, 'woo-ai-assistant') !== false;
    }

    /**
     * Get current page type
     *
     * @return string|null Current page type
     */
    private function getCurrentPageType(): ?string
    {
        if (!$this->isPluginAdminPage()) {
            return null;
        }

        $screen = get_current_screen();

        if (strpos($screen->id, 'settings') !== false) {
            return 'settings';
        } elseif (strpos($screen->id, 'conversations') !== false) {
            return 'conversations';
        } else {
            return 'dashboard';
        }
    }

    /**
     * Get asset version
     *
     * @return string Asset version
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Get plugin URL
     *
     * @return string Plugin URL
     */
    public function getPluginUrl(): string
    {
        return $this->pluginUrl;
    }

    /**
     * Check if assets are enqueued
     *
     * @return bool Assets enqueued status
     */
    public function areAssetsEnqueued(): bool
    {
        return $this->assetsEnqueued;
    }

    /**
     * Force enqueue assets (for manual loading)
     *
     * @return void
     */
    public function forceEnqueueAssets(): void
    {
        $this->assetsEnqueued = false;
        $this->enqueueAdminAssets();
    }

    /**
     * Enqueue specific asset by handle
     *
     * @param string $handle Asset handle
     * @param string $type Asset type ('style' or 'script')
     * @return bool Success status
     */
    public function enqueueAsset(string $handle, string $type = 'style'): bool
    {
        if ($type === 'style') {
            wp_enqueue_style($handle);
            return wp_style_is($handle, 'enqueued');
        } elseif ($type === 'script') {
            wp_enqueue_script($handle);
            return wp_script_is($handle, 'enqueued');
        }

        return false;
    }
}