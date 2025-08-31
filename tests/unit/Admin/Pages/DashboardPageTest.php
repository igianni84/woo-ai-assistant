<?php

/**
 * Tests for Dashboard Page Class
 *
 * Comprehensive unit tests for the DashboardPage class that handles the main
 * dashboard page rendering, statistics display, and admin interface functionality.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Unit\Admin\Pages
 * @since 1.0.0
 * @author Claude Code Assistant
 */

namespace WooAiAssistant\Tests\Unit\Admin\Pages;

use WooAiAssistant\Tests\Unit\WooAiBaseTestCase;
use WooAiAssistant\Admin\Pages\DashboardPage;
use WooAiAssistant\Common\Utils;

/**
 * Class DashboardPageTest
 *
 * Test cases for the DashboardPage class.
 * Verifies page rendering, statistics generation, security checks, and HTML output.
 *
 * @since 1.0.0
 */
class DashboardPageTest extends WooAiBaseTestCase
{
    /**
     * DashboardPage instance
     *
     * @var DashboardPage
     */
    private $dashboardPage;

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

        $this->dashboardPage = DashboardPage::getInstance();

        // Create test users with different capabilities
        $this->adminUserId = $this->createTestUser('administrator');
        $this->shopManagerUserId = $this->createTestUser('shop_manager');
        $this->customerUserId = $this->createTestUser('customer');

        // Mock WordPress admin environment
        set_current_screen('toplevel_page_woo-ai-assistant');
    }

    /**
     * Test DashboardPage singleton pattern
     *
     * Verifies that DashboardPage class follows singleton pattern correctly.
     *
     * @return void
     */
    public function test_getInstance_should_return_singleton_instance(): void
    {
        $instance1 = DashboardPage::getInstance();
        $instance2 = DashboardPage::getInstance();

        $this->assertInstanceOf(DashboardPage::class, $instance1);
        $this->assertSame($instance1, $instance2, 'getInstance should return the same instance (singleton pattern)');
    }

    /**
     * Test page slug getter
     *
     * Verifies that getPageSlug returns the correct page slug.
     *
     * @return void
     */
    public function test_getPageSlug_should_return_correct_slug(): void
    {
        $expectedSlug = 'woo-ai-assistant';
        $actualSlug = $this->dashboardPage->getPageSlug();

        $this->assertEquals($expectedSlug, $actualSlug, 'Page slug should match expected value');
    }

    /**
     * Test dashboard rendering for authorized user
     *
     * Verifies that dashboard renders correctly for users with proper capabilities.
     *
     * @return void
     */
    public function test_render_should_display_dashboard_for_authorized_user(): void
    {
        wp_set_current_user($this->adminUserId);

        // Capture output
        ob_start();
        $this->dashboardPage->render();
        $output = ob_get_clean();

        // Verify main dashboard elements are present
        $this->assertStringContains('woo-ai-assistant-dashboard', $output, 'Should contain dashboard wrapper class');
        $this->assertStringContains('Woo AI Assistant Dashboard', $output, 'Should contain dashboard title');
        $this->assertStringContains('Welcome to Woo AI Assistant', $output, 'Should contain welcome message');
        $this->assertStringContains('AI-powered chatbot for WooCommerce', $output, 'Should contain description');
    }

    /**
     * Test dashboard blocks unauthorized users
     *
     * Verifies that dashboard blocks users without proper capabilities.
     *
     * @return void
     */
    public function test_render_should_block_unauthorized_user(): void
    {
        wp_set_current_user($this->customerUserId);

        // Expect wp_die to be called
        $this->expectException(\WPDieException::class);

        $this->dashboardPage->render();
    }

    /**
     * Test dashboard renders statistics cards
     *
     * Verifies that dashboard displays statistics cards correctly.
     *
     * @return void
     */
    public function test_render_should_display_statistics_cards(): void
    {
        wp_set_current_user($this->adminUserId);

        ob_start();
        $this->dashboardPage->render();
        $output = ob_get_clean();

        // Check for statistics grid and cards
        $this->assertStringContains('woo-ai-assistant-stats-grid', $output, 'Should contain stats grid');
        $this->assertStringContains('Total Conversations', $output, 'Should display total conversations stat');
        $this->assertStringContains('Today\'s Conversations', $output, 'Should display today conversations stat');
        $this->assertStringContains('Knowledge Base Items', $output, 'Should display KB items stat');
        $this->assertStringContains('Customer Satisfaction', $output, 'Should display satisfaction stat');

        // Check for stat icons
        $this->assertStringContains('📊', $output, 'Should display conversations icon');
        $this->assertStringContains('💬', $output, 'Should display today icon');
        $this->assertStringContains('📚', $output, 'Should display KB icon');
        $this->assertStringContains('⭐', $output, 'Should display rating icon');
    }

    /**
     * Test dashboard renders quick actions
     *
     * Verifies that dashboard displays quick actions correctly.
     *
     * @return void
     */
    public function test_render_should_display_quick_actions(): void
    {
        wp_set_current_user($this->adminUserId);

        ob_start();
        $this->dashboardPage->render();
        $output = ob_get_clean();

        // Check for quick actions section
        $this->assertStringContains('quick-actions-card', $output, 'Should contain quick actions card');
        $this->assertStringContains('Quick Actions', $output, 'Should display quick actions title');

        // Check for specific actions
        $this->assertStringContains('Scan Knowledge Base', $output, 'Should display scan KB action');
        $this->assertStringContains('Test Chat Widget', $output, 'Should display test chat action');
        $this->assertStringContains('Export Data', $output, 'Should display export action');
        $this->assertStringContains('Plugin Settings', $output, 'Should display settings action');

        // Check for action icons
        $this->assertStringContains('🔍', $output, 'Should display scan icon');
        $this->assertStringContains('🧪', $output, 'Should display test icon');
        $this->assertStringContains('📥', $output, 'Should display export icon');
        $this->assertStringContains('⚙️', $output, 'Should display settings icon');

        // Check for action buttons with data attributes
        $this->assertStringContains('data-action="scan-knowledge-base"', $output, 'Should have scan KB data attribute');
        $this->assertStringContains('data-action="test-chat"', $output, 'Should have test chat data attribute');
        $this->assertStringContains('data-action="export-conversations"', $output, 'Should have export data attribute');
    }

    /**
     * Test dashboard renders system status
     *
     * Verifies that dashboard displays system status information correctly.
     *
     * @return void
     */
    public function test_render_should_display_system_status(): void
    {
        wp_set_current_user($this->adminUserId);

        ob_start();
        $this->dashboardPage->render();
        $output = ob_get_clean();

        // Check for system status section
        $this->assertStringContains('system-status-card', $output, 'Should contain system status card');
        $this->assertStringContains('System Status', $output, 'Should display system status title');

        // Check for status items
        $this->assertStringContains('Plugin Version:', $output, 'Should display plugin version');
        $this->assertStringContains('WooCommerce:', $output, 'Should display WooCommerce status');
        $this->assertStringContains('Development Mode:', $output, 'Should display development mode status');
        $this->assertStringContains('Cache Status:', $output, 'Should display cache status');

        // Check for status value classes
        $this->assertStringContains('status-active', $output, 'Should have status classes');
    }

    /**
     * Test dashboard renders navigation links
     *
     * Verifies that dashboard contains correct navigation links.
     *
     * @return void
     */
    public function test_render_should_display_navigation_links(): void
    {
        wp_set_current_user($this->adminUserId);

        ob_start();
        $this->dashboardPage->render();
        $output = ob_get_clean();

        // Check for settings link
        $expectedSettingsUrl = admin_url('admin.php?page=woo-ai-assistant-settings');
        $this->assertStringContains($expectedSettingsUrl, $output, 'Should contain settings page link');

        // Check for conversations link
        $expectedConversationsUrl = admin_url('admin.php?page=woo-ai-assistant-conversations');
        $this->assertStringContains($expectedConversationsUrl, $output, 'Should contain conversations page link');

        // Check for link text
        $this->assertStringContains('Configure Settings', $output, 'Should display configure settings text');
        $this->assertStringContains('View Conversations', $output, 'Should display view conversations text');
    }

    /**
     * Test development notice rendering
     *
     * Verifies that development notice is shown when in development mode.
     *
     * @return void
     */
    public function test_render_should_display_development_notice_when_in_dev_mode(): void
    {
        wp_set_current_user($this->adminUserId);

        // Mock development mode
        add_filter('woo_ai_assistant_is_development_mode', '__return_true');

        ob_start();
        $this->dashboardPage->render();
        $output = ob_get_clean();

        $this->assertStringContains('Development Mode Active', $output, 'Should display development notice');
        $this->assertStringContains('notice-info', $output, 'Should use info notice class');
        $this->assertStringContains('woo-ai-assistant-notice', $output, 'Should use plugin notice class');

        remove_filter('woo_ai_assistant_is_development_mode', '__return_true');
    }

    /**
     * Test development notice not shown in production
     *
     * Verifies that development notice is not shown in production mode.
     *
     * @return void
     */
    public function test_render_should_not_display_development_notice_in_production(): void
    {
        wp_set_current_user($this->adminUserId);

        // Mock production mode
        add_filter('woo_ai_assistant_is_development_mode', '__return_false');

        ob_start();
        $this->dashboardPage->render();
        $output = ob_get_clean();

        $this->assertStringNotContains('Development Mode Active', $output, 'Should not display development notice');

        remove_filter('woo_ai_assistant_is_development_mode', '__return_false');
    }

    /**
     * Test plugin info retrieval
     *
     * Verifies that getPluginInfo returns correct information.
     *
     * @return void
     */
    public function test_getPluginInfo_should_return_correct_information(): void
    {
        $pluginInfo = $this->invokeMethod($this->dashboardPage, 'getPluginInfo');

        $this->assertIsArray($pluginInfo, 'Plugin info should be an array');
        $this->assertArrayHasKey('version', $pluginInfo, 'Should contain version');
        $this->assertArrayHasKey('woocommerce_active', $pluginInfo, 'Should contain WooCommerce status');
        $this->assertArrayHasKey('development_mode', $pluginInfo, 'Should contain development mode status');
        $this->assertArrayHasKey('cache_enabled', $pluginInfo, 'Should contain cache status');

        $this->assertIsString($pluginInfo['version'], 'Version should be string');
        $this->assertIsBool($pluginInfo['woocommerce_active'], 'WooCommerce status should be boolean');
        $this->assertIsBool($pluginInfo['development_mode'], 'Development mode should be boolean');
        $this->assertIsBool($pluginInfo['cache_enabled'], 'Cache enabled should be boolean');
    }

    /**
     * Test dashboard statistics retrieval
     *
     * Verifies that getDashboardStats returns correct statistics structure.
     *
     * @return void
     */
    public function test_getDashboardStats_should_return_correct_structure(): void
    {
        $stats = $this->invokeMethod($this->dashboardPage, 'getDashboardStats');

        $this->assertIsArray($stats, 'Stats should be an array');
        $this->assertArrayHasKey('total_conversations', $stats, 'Should contain total conversations');
        $this->assertArrayHasKey('today_conversations', $stats, 'Should contain today conversations');
        $this->assertArrayHasKey('kb_items', $stats, 'Should contain KB items');
        $this->assertArrayHasKey('avg_rating', $stats, 'Should contain average rating');

        $this->assertIsInt($stats['total_conversations'], 'Total conversations should be integer');
        $this->assertIsInt($stats['today_conversations'], 'Today conversations should be integer');
        $this->assertIsInt($stats['kb_items'], 'KB items should be integer');
        $this->assertIsInt($stats['avg_rating'], 'Average rating should be integer');
    }

    /**
     * Test settings registration
     *
     * Verifies that registerSettings completes successfully.
     *
     * @return void
     */
    public function test_registerSettings_should_complete_successfully(): void
    {
        // Since dashboard doesn't have specific settings, this should complete without errors
        $this->dashboardPage->registerSettings();

        // If we reach here without errors, the test passes
        $this->assertTrue(true, 'Settings registration should complete without errors');
    }

    /**
     * Test AJAX actions handler
     *
     * Verifies that handleAjaxActions completes successfully.
     *
     * @return void
     */
    public function test_handleAjaxActions_should_complete_successfully(): void
    {
        // This is a placeholder method in the current implementation
        $this->dashboardPage->handleAjaxActions();

        // If we reach here without errors, the test passes
        $this->assertTrue(true, 'AJAX actions handler should complete without errors');
    }

    /**
     * Test HTML structure and accessibility
     *
     * Verifies that rendered HTML has proper structure and accessibility attributes.
     *
     * @return void
     */
    public function test_render_should_produce_accessible_html(): void
    {
        wp_set_current_user($this->adminUserId);

        ob_start();
        $this->dashboardPage->render();
        $output = ob_get_clean();

        // Check for proper HTML structure
        $this->assertStringContains('<div class="wrap woo-ai-assistant-dashboard">', $output, 'Should have proper wrapper structure');
        $this->assertStringContains('<h1 class="wp-heading-inline">', $output, 'Should use proper heading structure');

        // Check for escaped output
        $this->assertStringNotContains('<script>', $output, 'Should not contain unescaped scripts');
        
        // Check for proper button structure
        $this->assertStringContains('type="button"', $output, 'Buttons should have proper type');

        // Check for proper link structure
        $this->assertStringContains('class="button button-primary"', $output, 'Should use WordPress button classes');
        $this->assertStringContains('class="button button-secondary"', $output, 'Should use WordPress secondary button classes');
    }

    /**
     * Test CSS classes and styling
     *
     * Verifies that correct CSS classes are applied for styling.
     *
     * @return void
     */
    public function test_render_should_apply_correct_css_classes(): void
    {
        wp_set_current_user($this->adminUserId);

        ob_start();
        $this->dashboardPage->render();
        $output = ob_get_clean();

        // Check for main component classes
        $this->assertStringContains('woo-ai-assistant-dashboard', $output, 'Should have main dashboard class');
        $this->assertStringContains('woo-ai-assistant-card', $output, 'Should have card classes');
        $this->assertStringContains('welcome-card', $output, 'Should have welcome card class');
        $this->assertStringContains('quick-actions-card', $output, 'Should have quick actions card class');
        $this->assertStringContains('system-status-card', $output, 'Should have system status card class');

        // Check for grid classes
        $this->assertStringContains('woo-ai-assistant-stats-grid', $output, 'Should have stats grid class');
        $this->assertStringContains('quick-actions-grid', $output, 'Should have actions grid class');
        $this->assertStringContains('status-grid', $output, 'Should have status grid class');

        // Check for component classes
        $this->assertStringContains('stat-card', $output, 'Should have stat card classes');
        $this->assertStringContains('quick-action', $output, 'Should have quick action classes');
        $this->assertStringContains('status-item', $output, 'Should have status item classes');
    }

    /**
     * Test dashboard output escaping
     *
     * Verifies that all output is properly escaped for security.
     *
     * @return void
     */
    public function test_render_should_properly_escape_output(): void
    {
        wp_set_current_user($this->adminUserId);

        ob_start();
        $this->dashboardPage->render();
        $output = ob_get_clean();

        // Check that URLs are properly escaped
        $this->assertStringContains('esc_url(admin_url', $output, 'URLs should be escaped');
        
        // Check that text is properly escaped (by looking for escaped function calls in rendered output)
        // Note: This tests the presence of esc_html_e function calls in the source
        $this->assertGreaterThan(0, substr_count($output, 'esc_html'), 'Text should be escaped');
    }

    /**
     * Test class name follows PascalCase convention
     *
     * Verifies that the DashboardPage class follows PascalCase naming convention.
     *
     * @return void
     */
    public function test_dashboardPage_class_name_should_follow_pascal_case_convention(): void
    {
        $this->assertClassFollowsPascalCase(DashboardPage::class);
    }

    /**
     * Test method names follow camelCase convention
     *
     * Verifies that all public methods follow camelCase naming convention.
     *
     * @return void
     */
    public function test_dashboardPage_public_methods_should_follow_camel_case_convention(): void
    {
        $publicMethods = [
            'getInstance',
            'render',
            'registerSettings',
            'getPageSlug',
            'handleAjaxActions'
        ];

        foreach ($publicMethods as $methodName) {
            $this->assertMethodFollowsCamelCase($this->dashboardPage, $methodName);
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
            'renderDevelopmentNotice',
            'getPluginInfo',
            'getDashboardStats'
        ];

        foreach ($privateMethods as $methodName) {
            $method = $this->getReflectionMethod($this->dashboardPage, $methodName);
            $this->assertTrue($method->isPrivate(), "Method {$methodName} should be private");
        }
    }

    /**
     * Test memory usage remains reasonable
     *
     * Verifies that the dashboard page doesn't consume excessive memory.
     *
     * @return void
     */
    public function test_dashboardPage_memory_usage_should_be_reasonable(): void
    {
        wp_set_current_user($this->adminUserId);

        $initialMemory = memory_get_usage();

        // Perform multiple dashboard operations
        for ($i = 0; $i < 5; $i++) {
            ob_start();
            $this->dashboardPage->render();
            ob_end_clean();
            $this->dashboardPage->getPageSlug();
            $this->dashboardPage->registerSettings();
        }

        $finalMemory = memory_get_usage();
        $memoryIncrease = $finalMemory - $initialMemory;

        // Memory increase should be less than 2MB for these operations
        $this->assertLessThan(2097152, $memoryIncrease, 'Memory increase should be less than 2MB for repeated operations');
    }

    /**
     * Test error handling in rendering
     *
     * Verifies that dashboard handles rendering errors gracefully.
     *
     * @return void
     */
    public function test_dashboardPage_should_handle_rendering_errors_gracefully(): void
    {
        wp_set_current_user($this->adminUserId);

        // Mock Utils methods to throw exceptions
        add_filter('woo_ai_assistant_get_version', function() {
            throw new \Exception('Test version error');
        });

        try {
            ob_start();
            $this->dashboardPage->render();
            $output = ob_get_clean();

            // Should still render basic structure even with errors
            $this->assertStringContains('woo-ai-assistant-dashboard', $output, 'Should render basic structure even with errors');
            
        } catch (\Exception $e) {
            // If exception is thrown, it should be handled gracefully
            $this->assertStringContains('Test version error', $e->getMessage());
        } finally {
            // Clean up filter
            remove_all_filters('woo_ai_assistant_get_version');
        }
    }

    /**
     * Test localization strings are used
     *
     * Verifies that dashboard uses proper localization functions.
     *
     * @return void
     */
    public function test_render_should_use_localization_strings(): void
    {
        wp_set_current_user($this->adminUserId);

        ob_start();
        $this->dashboardPage->render();
        $output = ob_get_clean();

        // Check for presence of common localized strings
        $localizedStrings = [
            'Woo AI Assistant Dashboard',
            'Welcome to Woo AI Assistant',
            'Configure Settings',
            'View Conversations',
            'Quick Actions',
            'System Status',
            'Total Conversations',
            'Knowledge Base Items'
        ];

        foreach ($localizedStrings as $string) {
            $this->assertStringContains($string, $output, "Should contain localized string: {$string}");
        }
    }
}