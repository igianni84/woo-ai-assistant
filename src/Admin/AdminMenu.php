<?php

/**
 * Admin Menu Class
 *
 * Manages the WordPress admin menu interface for the Woo AI Assistant plugin.
 * Registers menu items, handles page routing, and manages admin navigation.
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
use WooAiAssistant\Admin\Pages\DashboardPage;
use WooAiAssistant\Admin\Pages\SettingsPage;
use WooAiAssistant\Admin\Pages\ConversationsLogPage;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AdminMenu
 *
 * Handles WordPress admin menu registration and page management.
 *
 * @since 1.0.0
 */
class AdminMenu
{
    use Singleton;

    /**
     * Menu slug for the main page
     *
     * @var string
     */
    private const MENU_SLUG = 'woo-ai-assistant';

    /**
     * Admin page instances
     *
     * @var array
     */
    private array $pages = [];

    /**
     * Assets manager instance
     *
     * @var Assets
     */
    private Assets $assets;

    /**
     * Initialize the admin menu
     *
     * @return void
     */
    protected function init(): void
    {
        // Initialize assets manager
        $this->assets = Assets::getInstance();

        // Hook into WordPress admin menu system
        add_action('admin_menu', [$this, 'registerMenu']);

        // Initialize admin pages
        $this->initializePages();

        // Register admin-specific hooks
        add_action('admin_init', [$this, 'handleAdminInit']);

        // Add admin body classes
        add_filter('admin_body_class', [$this, 'addAdminBodyClasses']);

        Logger::debug('AdminMenu initialized');
    }

    /**
     * Initialize admin page instances
     *
     * @return void
     */
    private function initializePages(): void
    {
        $this->pages = [
            'dashboard' => DashboardPage::getInstance(),
            'settings' => SettingsPage::getInstance(),
            'conversations' => ConversationsLogPage::getInstance()
        ];

        Logger::debug('Admin pages initialized', [
            'page_count' => count($this->pages)
        ]);
    }

    /**
     * Register WordPress admin menu
     *
     * Creates the main menu item and submenus for the plugin.
     *
     * @return void
     */
    public function registerMenu(): void
    {
        // Check user capabilities
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        // Add main menu page
        add_menu_page(
            __('Woo AI Assistant', 'woo-ai-assistant'),           // Page title
            __('AI Assistant', 'woo-ai-assistant'),               // Menu title
            'manage_woocommerce',                                 // Capability
            self::MENU_SLUG,                                      // Menu slug
            [$this, 'renderDashboardPage'],                       // Function
            $this->getMenuIcon(),                                 // Icon
            25                                                    // Position (after WooCommerce)
        );

        // Add Dashboard submenu (will replace the main menu item)
        add_submenu_page(
            self::MENU_SLUG,
            __('Dashboard', 'woo-ai-assistant'),
            __('Dashboard', 'woo-ai-assistant'),
            'manage_woocommerce',
            self::MENU_SLUG,
            [$this, 'renderDashboardPage']
        );

        // Add Settings submenu
        add_submenu_page(
            self::MENU_SLUG,
            __('Settings', 'woo-ai-assistant'),
            __('Settings', 'woo-ai-assistant'),
            'manage_woocommerce',
            self::MENU_SLUG . '-settings',
            [$this, 'renderSettingsPage']
        );

        // Add Conversations Log submenu
        add_submenu_page(
            self::MENU_SLUG,
            __('Conversations Log', 'woo-ai-assistant'),
            __('Conversations', 'woo-ai-assistant'),
            'manage_woocommerce',
            self::MENU_SLUG . '-conversations',
            [$this, 'renderConversationsPage']
        );

        // Hook for after menu registration
        do_action('woo_ai_assistant_admin_menu_registered');

        Logger::debug('WordPress admin menu registered');
    }

    /**
     * Get menu icon (base64 encoded SVG)
     *
     * @return string Base64 encoded SVG icon
     */
    private function getMenuIcon(): string
    {
        // Simple robot/AI icon - base64 encoded SVG
        return 'data:image/svg+xml;base64,' . base64_encode('
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C13.1 2 14 2.9 14 4C14 5.1 13.1 6 12 6C10.9 6 10 5.1 10 4C10 2.9 10.9 2 12 2M21 9V7L17 7C16.4 4.6 14.4 3 12 3C9.6 3 7.6 4.6 7 7L3 7V9H7C7 11.7 9.3 14 12 14S17 11.7 17 9H21M7.5 18C7.5 17.2 8.2 16.5 9 16.5S10.5 17.2 10.5 18 9.8 19.5 9 19.5 7.5 18.8 7.5 18M13.5 18C13.5 17.2 14.2 16.5 15 16.5S16.5 17.2 16.5 18 15.8 19.5 15 19.5 13.5 18.8 13.5 18M12 20.5C11.2 20.5 10.5 21.2 10.5 22H13.5C13.5 21.2 12.8 20.5 12 20.5Z"/>
            </svg>
        ');
    }

    /**
     * Handle admin initialization
     *
     * @return void
     */
    public function handleAdminInit(): void
    {
        // Register settings for all admin pages
        foreach ($this->pages as $page) {
            if (method_exists($page, 'registerSettings')) {
                $page->registerSettings();
            }
        }

        // Hook for admin initialization
        do_action('woo_ai_assistant_admin_init');

        Logger::debug('Admin initialization completed');
    }

    /**
     * Add admin body classes for styling
     *
     * @param string $classes Existing body classes
     * @return string Modified body classes
     */
    public function addAdminBodyClasses(string $classes): string
    {
        $screen = get_current_screen();

        if (!$screen || strpos($screen->id, 'woo-ai-assistant') === false) {
            return $classes;
        }

        $classes .= ' woo-ai-assistant-admin';

        // Add specific page classes
        if (strpos($screen->id, 'settings') !== false) {
            $classes .= ' woo-ai-assistant-settings';
        } elseif (strpos($screen->id, 'conversations') !== false) {
            $classes .= ' woo-ai-assistant-conversations';
        } else {
            $classes .= ' woo-ai-assistant-dashboard';
        }

        return $classes;
    }

    /**
     * Render Dashboard page
     *
     * @return void
     */
    public function renderDashboardPage(): void
    {
        $this->renderPage('dashboard');
    }

    /**
     * Render Settings page
     *
     * @return void
     */
    public function renderSettingsPage(): void
    {
        $this->renderPage('settings');
    }

    /**
     * Render Conversations page
     *
     * @return void
     */
    public function renderConversationsPage(): void
    {
        $this->renderPage('conversations');
    }

    /**
     * Render a specific page
     *
     * @param string $pageType Page type to render
     * @return void
     */
    private function renderPage(string $pageType): void
    {
        if (!isset($this->pages[$pageType])) {
            wp_die(__('Invalid page requested.', 'woo-ai-assistant'));
            return;
        }

        // Check capabilities
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'woo-ai-assistant'));
            return;
        }

        // Load admin assets
        $this->assets->enqueueAdminAssets();

        // Render the page
        $this->pages[$pageType]->render();

        Logger::debug("Admin page rendered: {$pageType}");
    }

    /**
     * Get menu slug
     *
     * @return string Menu slug
     */
    public function getMenuSlug(): string
    {
        return self::MENU_SLUG;
    }

    /**
     * Get page instance
     *
     * @param string $pageType Page type
     * @return object|null Page instance or null if not found
     */
    public function getPage(string $pageType): ?object
    {
        return $this->pages[$pageType] ?? null;
    }

    /**
     * Get all page instances
     *
     * @return array All page instances
     */
    public function getPages(): array
    {
        return $this->pages;
    }

    /**
     * Check if current page is plugin admin page
     *
     * @return bool True if on plugin admin page
     */
    public function isPluginAdminPage(): bool
    {
        $screen = get_current_screen();
        return $screen && strpos($screen->id, self::MENU_SLUG) !== false;
    }

    /**
     * Get current page type
     *
     * @return string|null Current page type or null
     */
    public function getCurrentPageType(): ?string
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
}
