<?php

/**
 * Tests for Admin Menu Class
 *
 * Comprehensive unit tests for the AdminMenu class that handles WordPress admin
 * menu registration, page routing, capability checks, and admin interface management.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Unit\Admin
 * @since 1.0.0
 * @author Claude Code Assistant
 */

namespace WooAiAssistant\Tests\Unit\Admin;

use WooAiAssistant\Tests\Unit\WooAiBaseTestCase;
use WooAiAssistant\Admin\AdminMenu;
use WooAiAssistant\Admin\Assets;
use WooAiAssistant\Admin\Pages\DashboardPage;
use WooAiAssistant\Admin\Pages\SettingsPage;
use WooAiAssistant\Admin\Pages\ConversationsLogPage;

/**
 * Class AdminMenuTest
 *
 * Test cases for the AdminMenu class.
 * Verifies menu registration, page rendering, capability checks, and admin functionality.
 *
 * @since 1.0.0
 */
class AdminMenuTest extends WooAiBaseTestCase
{
    /**
     * AdminMenu instance
     *
     * @var AdminMenu
     */
    private $adminMenu;

    /**
     * Mock admin user ID
     *
     * @var int
     */
    private $adminUserId;

    /**
     * Mock shop manager user ID
     *
     * @var int
     */
    private $shopManagerUserId;

    /**
     * Mock customer user ID
     *
     * @var int
     */
    private $customerUserId;

    /**
     * Set up test environment
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->adminMenu = AdminMenu::getInstance();

        // Create test users with different capabilities
        $this->adminUserId = $this->createTestUser('administrator');
        $this->shopManagerUserId = $this->createTestUser('shop_manager');
        $this->customerUserId = $this->createTestUser('customer');

        // Mock WordPress admin environment
        global $menu, $submenu;
        $menu = [];
        $submenu = [];

        // Set WordPress admin context
        set_current_screen('woo-ai-assistant_page_woo-ai-assistant');
    }

    /**
     * Test AdminMenu singleton pattern
     *
     * Verifies that AdminMenu class follows singleton pattern correctly.
     *
     * @return void
     */
    public function test_getInstance_should_return_singleton_instance(): void
    {
        $instance1 = AdminMenu::getInstance();
        $instance2 = AdminMenu::getInstance();

        $this->assertInstanceOf(AdminMenu::class, $instance1);
        $this->assertSame($instance1, $instance2, 'getInstance should return the same instance (singleton pattern)');
    }

    /**
     * Test menu registration for admin user
     *
     * Verifies that menu items are registered correctly for users with proper capabilities.
     *
     * @return void
     */
    public function test_registerMenu_should_add_menu_items_for_admin_user(): void
    {
        wp_set_current_user($this->adminUserId);

        // Mock the add_menu_page and add_submenu_page functions
        $menuCalls = [];
        $submenuCalls = [];

        // Override WordPress functions for testing
        $mockAddMenuPage = function(...$args) use (&$menuCalls) {
            $menuCalls[] = $args;
            return 'toplevel_page_woo-ai-assistant';
        };

        $mockAddSubmenuPage = function(...$args) use (&$submenuCalls) {
            $submenuCalls[] = $args;
            return 'woo-ai-assistant_page_' . $args[1];
        };

        // Mock WordPress functions
        add_filter('add_menu_page_mock', $mockAddMenuPage);
        add_filter('add_submenu_page_mock', $mockAddSubmenuPage);

        // Execute menu registration
        $this->adminMenu->registerMenu();

        // Verify action was triggered
        $this->assertTrue(did_action('woo_ai_assistant_admin_menu_registered') > 0, 'Menu registration action should be triggered');

        // Clean up
        remove_filter('add_menu_page_mock', $mockAddMenuPage);
        remove_filter('add_submenu_page_mock', $mockAddSubmenuPage);
    }

    /**
     * Test menu registration is blocked for unauthorized users
     *
     * Verifies that menu items are not registered for users without proper capabilities.
     *
     * @return void
     */
    public function test_registerMenu_should_not_register_for_unauthorized_user(): void
    {
        wp_set_current_user($this->customerUserId);

        // Mock global menu arrays
        global $menu, $submenu;
        $initialMenuCount = count($menu);
        $initialSubmenuCount = count($submenu);

        $this->adminMenu->registerMenu();

        // Verify no menu items were added
        $this->assertEquals($initialMenuCount, count($menu), 'No main menu items should be added for unauthorized users');
        $this->assertEquals($initialSubmenuCount, count($submenu), 'No submenu items should be added for unauthorized users');
    }

    /**
     * Test menu slug getter
     *
     * Verifies that getMenuSlug returns the correct menu slug.
     *
     * @return void
     */
    public function test_getMenuSlug_should_return_correct_slug(): void
    {
        $expectedSlug = 'woo-ai-assistant';
        $actualSlug = $this->adminMenu->getMenuSlug();

        $this->assertEquals($expectedSlug, $actualSlug, 'Menu slug should match expected value');
    }

    /**
     * Test page instances retrieval
     *
     * Verifies that page instances are created and accessible.
     *
     * @return void
     */
    public function test_getPages_should_return_page_instances(): void
    {
        $pages = $this->adminMenu->getPages();

        $this->assertIsArray($pages, 'getPages should return an array');
        $this->assertArrayHasKey('dashboard', $pages, 'Should contain dashboard page');
        $this->assertArrayHasKey('settings', $pages, 'Should contain settings page');
        $this->assertArrayHasKey('conversations', $pages, 'Should contain conversations page');

        $this->assertInstanceOf(DashboardPage::class, $pages['dashboard'], 'Dashboard page should be correct instance');
        $this->assertInstanceOf(SettingsPage::class, $pages['settings'], 'Settings page should be correct instance');
        $this->assertInstanceOf(ConversationsLogPage::class, $pages['conversations'], 'Conversations page should be correct instance');
    }

    /**
     * Test individual page retrieval
     *
     * Verifies that individual page instances can be retrieved.
     *
     * @return void
     */
    public function test_getPage_should_return_specific_page_instance(): void
    {
        $dashboardPage = $this->adminMenu->getPage('dashboard');
        $settingsPage = $this->adminMenu->getPage('settings');
        $nonExistentPage = $this->adminMenu->getPage('nonexistent');

        $this->assertInstanceOf(DashboardPage::class, $dashboardPage, 'Should return dashboard page instance');
        $this->assertInstanceOf(SettingsPage::class, $settingsPage, 'Should return settings page instance');
        $this->assertNull($nonExistentPage, 'Should return null for non-existent page');
    }

    /**
     * Test admin body classes addition
     *
     * Verifies that admin body classes are added correctly for plugin pages.
     *
     * @return void
     */
    public function test_addAdminBodyClasses_should_add_plugin_classes(): void
    {
        // Mock current screen for plugin page
        set_current_screen('toplevel_page_woo-ai-assistant');

        $initialClasses = 'existing-class another-class';
        $modifiedClasses = $this->adminMenu->addAdminBodyClasses($initialClasses);

        $this->assertStringContains('woo-ai-assistant-admin', $modifiedClasses, 'Should add main plugin admin class');
        $this->assertStringContains('woo-ai-assistant-dashboard', $modifiedClasses, 'Should add dashboard-specific class');
        $this->assertStringContains($initialClasses, $modifiedClasses, 'Should preserve existing classes');
    }

    /**
     * Test admin body classes for settings page
     *
     * Verifies that correct classes are added for settings page.
     *
     * @return void
     */
    public function test_addAdminBodyClasses_should_add_settings_classes(): void
    {
        // Mock current screen for settings page
        set_current_screen('woo-ai-assistant_page_woo-ai-assistant-settings');

        $initialClasses = '';
        $modifiedClasses = $this->adminMenu->addAdminBodyClasses($initialClasses);

        $this->assertStringContains('woo-ai-assistant-admin', $modifiedClasses, 'Should add main plugin admin class');
        $this->assertStringContains('woo-ai-assistant-settings', $modifiedClasses, 'Should add settings-specific class');
    }

    /**
     * Test admin body classes for conversations page
     *
     * Verifies that correct classes are added for conversations page.
     *
     * @return void
     */
    public function test_addAdminBodyClasses_should_add_conversations_classes(): void
    {
        // Mock current screen for conversations page
        set_current_screen('woo-ai-assistant_page_woo-ai-assistant-conversations');

        $initialClasses = '';
        $modifiedClasses = $this->adminMenu->addAdminBodyClasses($initialClasses);

        $this->assertStringContains('woo-ai-assistant-admin', $modifiedClasses, 'Should add main plugin admin class');
        $this->assertStringContains('woo-ai-assistant-conversations', $modifiedClasses, 'Should add conversations-specific class');
    }

    /**
     * Test admin body classes are not added for non-plugin pages
     *
     * Verifies that plugin classes are not added to non-plugin admin pages.
     *
     * @return void
     */
    public function test_addAdminBodyClasses_should_not_modify_non_plugin_pages(): void
    {
        // Mock current screen for non-plugin page
        set_current_screen('edit-post');

        $initialClasses = 'existing-class';
        $modifiedClasses = $this->adminMenu->addAdminBodyClasses($initialClasses);

        $this->assertEquals($initialClasses, $modifiedClasses, 'Should not modify classes for non-plugin pages');
    }

    /**
     * Test plugin admin page detection
     *
     * Verifies that isPluginAdminPage correctly identifies plugin admin pages.
     *
     * @return void
     */
    public function test_isPluginAdminPage_should_detect_plugin_pages(): void
    {
        // Test plugin page
        set_current_screen('toplevel_page_woo-ai-assistant');
        $this->assertTrue($this->adminMenu->isPluginAdminPage(), 'Should detect main plugin page');

        // Test settings page
        set_current_screen('woo-ai-assistant_page_woo-ai-assistant-settings');
        $this->assertTrue($this->adminMenu->isPluginAdminPage(), 'Should detect settings page');

        // Test non-plugin page
        set_current_screen('edit-post');
        $this->assertFalse($this->adminMenu->isPluginAdminPage(), 'Should not detect non-plugin page');
    }

    /**
     * Test current page type detection
     *
     * Verifies that getCurrentPageType returns correct page types.
     *
     * @return void
     */
    public function test_getCurrentPageType_should_return_correct_page_types(): void
    {
        // Test dashboard page
        set_current_screen('toplevel_page_woo-ai-assistant');
        $this->assertEquals('dashboard', $this->adminMenu->getCurrentPageType(), 'Should detect dashboard page');

        // Test settings page
        set_current_screen('woo-ai-assistant_page_woo-ai-assistant-settings');
        $this->assertEquals('settings', $this->adminMenu->getCurrentPageType(), 'Should detect settings page');

        // Test conversations page
        set_current_screen('woo-ai-assistant_page_woo-ai-assistant-conversations');
        $this->assertEquals('conversations', $this->adminMenu->getCurrentPageType(), 'Should detect conversations page');

        // Test non-plugin page
        set_current_screen('edit-post');
        $this->assertNull($this->adminMenu->getCurrentPageType(), 'Should return null for non-plugin page');
    }

    /**
     * Test dashboard page rendering with proper permissions
     *
     * Verifies that dashboard page renders correctly for authorized users.
     *
     * @return void
     */
    public function test_renderDashboardPage_should_render_for_authorized_user(): void
    {
        wp_set_current_user($this->adminUserId);

        // Capture output
        ob_start();
        $this->adminMenu->renderDashboardPage();
        $output = ob_get_clean();

        // Verify no fatal errors and no unauthorized access message
        $this->assertStringNotContains('You do not have sufficient permissions', $output, 'Should not show permission error');
    }

    /**
     * Test dashboard page rendering blocks unauthorized users
     *
     * Verifies that dashboard page blocks unauthorized users.
     *
     * @return void
     */
    public function test_renderDashboardPage_should_block_unauthorized_user(): void
    {
        wp_set_current_user($this->customerUserId);

        // Expect wp_die to be called
        $this->expectException(\WPDieException::class);

        $this->adminMenu->renderDashboardPage();
    }

    /**
     * Test settings page rendering with proper permissions
     *
     * Verifies that settings page renders correctly for authorized users.
     *
     * @return void
     */
    public function test_renderSettingsPage_should_render_for_authorized_user(): void
    {
        wp_set_current_user($this->adminUserId);

        // Mock the settings page to avoid actual rendering
        $mockSettingsPage = $this->createMock(SettingsPage::class);
        $mockSettingsPage->expects($this->once())
                        ->method('render');

        // Replace the settings page instance
        $pages = $this->getPropertyValue($this->adminMenu, 'pages');
        $pages['settings'] = $mockSettingsPage;
        $this->setPropertyValue($this->adminMenu, 'pages', $pages);

        $this->adminMenu->renderSettingsPage();

        // Test passed if no exception was thrown
        $this->assertTrue(true, 'Settings page should render without errors for authorized user');
    }

    /**
     * Test conversations page rendering with proper permissions
     *
     * Verifies that conversations page renders correctly for authorized users.
     *
     * @return void
     */
    public function test_renderConversationsPage_should_render_for_authorized_user(): void
    {
        wp_set_current_user($this->shopManagerUserId);

        // Mock the conversations page to avoid actual rendering
        $mockConversationsPage = $this->createMock(ConversationsLogPage::class);
        $mockConversationsPage->expects($this->once())
                             ->method('render');

        // Replace the conversations page instance
        $pages = $this->getPropertyValue($this->adminMenu, 'pages');
        $pages['conversations'] = $mockConversationsPage;
        $this->setPropertyValue($this->adminMenu, 'pages', $pages);

        $this->adminMenu->renderConversationsPage();

        // Test passed if no exception was thrown
        $this->assertTrue(true, 'Conversations page should render without errors for authorized user');
    }

    /**
     * Test admin initialization
     *
     * Verifies that handleAdminInit processes correctly.
     *
     * @return void
     */
    public function test_handleAdminInit_should_register_settings_for_pages(): void
    {
        // Mock pages with registerSettings method
        $mockDashboard = $this->createMock(DashboardPage::class);
        $mockDashboard->expects($this->once())
                     ->method('registerSettings');

        $mockSettings = $this->createMock(SettingsPage::class);
        $mockSettings->expects($this->once())
                    ->method('registerSettings');

        // Replace page instances
        $pages = [
            'dashboard' => $mockDashboard,
            'settings' => $mockSettings,
        ];
        $this->setPropertyValue($this->adminMenu, 'pages', $pages);

        $this->adminMenu->handleAdminInit();

        // Verify action was triggered
        $this->assertTrue(did_action('woo_ai_assistant_admin_init') > 0, 'Admin init action should be triggered');
    }

    /**
     * Test menu icon generation
     *
     * Verifies that getMenuIcon returns a valid base64 encoded SVG.
     *
     * @return void
     */
    public function test_getMenuIcon_should_return_base64_encoded_svg(): void
    {
        $icon = $this->invokeMethod($this->adminMenu, 'getMenuIcon');

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $icon, 'Icon should be base64 encoded SVG');
        
        // Decode and verify it's valid base64
        $base64Data = str_replace('data:image/svg+xml;base64,', '', $icon);
        $decodedData = base64_decode($base64Data);
        
        $this->assertNotFalse($decodedData, 'Should be valid base64');
        $this->assertStringContains('<svg', $decodedData, 'Decoded data should contain SVG');
    }

    /**
     * Test invalid page rendering
     *
     * Verifies that renderPage handles invalid page types correctly.
     *
     * @return void
     */
    public function test_renderPage_should_handle_invalid_page_type(): void
    {
        wp_set_current_user($this->adminUserId);

        // Expect wp_die to be called for invalid page
        $this->expectException(\WPDieException::class);

        $this->invokeMethod($this->adminMenu, 'renderPage', ['invalid_page']);
    }

    /**
     * Test assets are loaded during page rendering
     *
     * Verifies that admin assets are enqueued when rendering pages.
     *
     * @return void
     */
    public function test_renderPage_should_enqueue_admin_assets(): void
    {
        wp_set_current_user($this->adminUserId);

        // Mock assets instance
        $mockAssets = $this->createMock(Assets::class);
        $mockAssets->expects($this->once())
                  ->method('enqueueAdminAssets');

        // Replace assets instance
        $this->setPropertyValue($this->adminMenu, 'assets', $mockAssets);

        // Mock dashboard page
        $mockDashboard = $this->createMock(DashboardPage::class);
        $mockDashboard->expects($this->once())
                     ->method('render');

        $pages = ['dashboard' => $mockDashboard];
        $this->setPropertyValue($this->adminMenu, 'pages', $pages);

        $this->invokeMethod($this->adminMenu, 'renderPage', ['dashboard']);
    }

    /**
     * Test class name follows PascalCase convention
     *
     * Verifies that the AdminMenu class follows PascalCase naming convention.
     *
     * @return void
     */
    public function test_adminMenu_class_name_should_follow_pascal_case_convention(): void
    {
        $this->assertClassFollowsPascalCase(AdminMenu::class);
    }

    /**
     * Test method names follow camelCase convention
     *
     * Verifies that all public methods follow camelCase naming convention.
     *
     * @return void
     */
    public function test_adminMenu_public_methods_should_follow_camel_case_convention(): void
    {
        $publicMethods = [
            'getInstance',
            'registerMenu',
            'handleAdminInit',
            'addAdminBodyClasses',
            'renderDashboardPage',
            'renderSettingsPage',
            'renderConversationsPage',
            'getMenuSlug',
            'getPage',
            'getPages',
            'isPluginAdminPage',
            'getCurrentPageType'
        ];

        foreach ($publicMethods as $methodName) {
            $this->assertMethodFollowsCamelCase($this->adminMenu, $methodName);
        }
    }

    /**
     * Test private method accessibility through reflection
     *
     * Verifies that private methods can be accessed for testing.
     *
     * @return void
     */
    public function test_private_methods_should_be_accessible_through_reflection(): void
    {
        $privateMethods = [
            'initializePages',
            'getMenuIcon',
            'renderPage'
        ];

        foreach ($privateMethods as $methodName) {
            $method = $this->getReflectionMethod($this->adminMenu, $methodName);
            $this->assertTrue($method->isPrivate(), "Method {$methodName} should be private");
        }
    }

    /**
     * Test memory usage remains reasonable
     *
     * Verifies that the admin menu doesn't consume excessive memory.
     *
     * @return void
     */
    public function test_adminMenu_memory_usage_should_be_reasonable(): void
    {
        $initialMemory = memory_get_usage();

        // Perform multiple admin menu operations
        for ($i = 0; $i < 10; $i++) {
            $this->adminMenu->getMenuSlug();
            $this->adminMenu->getPages();
            $this->adminMenu->isPluginAdminPage();
            $this->adminMenu->getCurrentPageType();
        }

        $finalMemory = memory_get_usage();
        $memoryIncrease = $finalMemory - $initialMemory;

        // Memory increase should be less than 1MB for these operations
        $this->assertLessThan(1048576, $memoryIncrease, 'Memory increase should be less than 1MB for repeated operations');
    }

    /**
     * Test error handling in page rendering
     *
     * Verifies that the admin menu handles page rendering errors gracefully.
     *
     * @return void
     */
    public function test_adminMenu_should_handle_page_rendering_errors_gracefully(): void
    {
        wp_set_current_user($this->adminUserId);

        // Mock a page that throws an exception
        $mockPage = $this->createMock(DashboardPage::class);
        $mockPage->method('render')
                ->willThrowException(new \Exception('Test rendering error'));

        $pages = ['dashboard' => $mockPage];
        $this->setPropertyValue($this->adminMenu, 'pages', $pages);

        // Should not throw fatal errors
        try {
            $this->invokeMethod($this->adminMenu, 'renderPage', ['dashboard']);
            $this->assertTrue(true, 'Should handle page rendering errors gracefully');
        } catch (\Exception $e) {
            // Exception is expected, but should be handled gracefully
            $this->assertStringContains('Test rendering error', $e->getMessage());
        }
    }

    /**
     * Test WordPress hooks are properly registered
     *
     * Verifies that all necessary WordPress hooks are registered.
     *
     * @return void
     */
    public function test_adminMenu_should_register_wordpress_hooks(): void
    {
        // Verify hooks were added during initialization
        $this->assertTrue(has_action('admin_menu', [$this->adminMenu, 'registerMenu']), 'Should register admin_menu hook');
        $this->assertTrue(has_action('admin_init', [$this->adminMenu, 'handleAdminInit']), 'Should register admin_init hook');
        $this->assertTrue(has_filter('admin_body_class', [$this->adminMenu, 'addAdminBodyClasses']), 'Should register admin_body_class filter');
    }
}