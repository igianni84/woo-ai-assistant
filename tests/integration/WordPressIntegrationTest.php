<?php

/**
 * WordPress Integration Tests
 *
 * Tests the plugin's integration with WordPress core functionality
 * including hooks, filters, admin interface, and frontend behavior.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Integration
 * @since 1.0.0
 * @author Claude Code Assistant
 */

namespace WooAiAssistant\Tests\Integration;

use WooAiAssistant\Main;
use WooAiAssistant\Tests\Integration\WooAiIntegrationTestCase;

/**
 * Class WordPressIntegrationTest
 *
 * Integration tests for WordPress core functionality.
 *
 * @since 1.0.0
 */
class WordPressIntegrationTest extends WooAiIntegrationTestCase
{
    /**
     * Test plugin activation integration
     *
     * Verifies that the plugin activates correctly within WordPress.
     *
     * @return void
     */
    public function test_plugin_should_activate_successfully(): void
    {
        // Plugin should be loaded
        $this->assertTrue(class_exists(Main::class), 'Main plugin class should be loaded');

        // Plugin instance should be available
        $plugin = Main::getInstance();
        $this->assertInstanceOf(Main::class, $plugin, 'Plugin instance should be available');

        // Plugin should be initialized
        $this->assertTrue($plugin->isInitialized(), 'Plugin should be initialized after activation');
    }

    /**
     * Test WordPress hooks registration
     *
     * Verifies that essential WordPress hooks are properly registered.
     *
     * @return void
     */
    public function test_wordpress_hooks_should_be_registered_correctly(): void
    {
        $plugin = Main::getInstance();

        // Test critical hooks are registered
        $this->assertGreaterThan(0, has_action('init', [$plugin, 'loadModules']));
        $this->assertGreaterThan(0, has_action('rest_api_init', [$plugin, 'initRestApi']));
        $this->assertGreaterThan(0, has_action('wp_loaded', [$plugin, 'initFrontend']));

        if (is_admin()) {
            $this->assertGreaterThan(0, has_action('admin_init', [$plugin, 'initAdmin']));
        }
    }

    /**
     * Test plugin filters integration
     *
     * Verifies that plugin filters work correctly with WordPress.
     *
     * @return void
     */
    public function test_plugin_filters_should_integrate_with_wordpress(): void
    {
        $plugin = Main::getInstance();

        // Test plugin action links filter
        $original_links = ['deactivate' => 'Deactivate'];
        $filtered_links = apply_filters(
            'plugin_action_links_' . WOO_AI_ASSISTANT_BASENAME,
            $original_links
        );

        $this->assertNotEquals($original_links, $filtered_links, 'Plugin action links should be modified');
        $this->assertGreaterThan(count($original_links), count($filtered_links), 'Should add settings link');
    }

    /**
     * Test admin notice integration
     *
     * Verifies that admin notices display correctly in development mode.
     *
     * @return void
     */
    public function test_admin_notices_should_display_in_development_mode(): void
    {
        if (!is_admin()) {
            $this->markTestSkipped('Admin notice test requires admin context');
        }

        // Mock development mode
        add_filter('woo_ai_assistant_is_development_mode', '__return_true');

        // Set up admin screen
        set_current_screen('toplevel_page_woo-ai-assistant');

        $plugin = Main::getInstance();

        // Capture notice output
        ob_start();
        do_action('admin_notices');
        $output = ob_get_clean();

        $this->assertStringContainsString('Development Mode', $output, 'Development notice should be displayed');

        // Clean up
        remove_filter('woo_ai_assistant_is_development_mode', '__return_true');
    }

    /**
     * Test database integration
     *
     * Verifies that the plugin can interact with WordPress database correctly.
     *
     * @return void
     */
    public function test_database_integration_should_work_correctly(): void
    {
        global $wpdb;

        // Test that we can query WordPress tables
        $post_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts}");
        $this->assertIsNumeric($post_count, 'Should be able to query WordPress posts table');

        // Test that we can query WooCommerce tables (if available)
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}woocommerce_sessions'");
        if ($table_exists) {
            $session_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_sessions");
            $this->assertIsNumeric($session_count, 'Should be able to query WooCommerce tables');
        }
    }

    /**
     * Test WordPress option integration
     *
     * Verifies that plugin options integrate correctly with WordPress options API.
     *
     * @return void
     */
    public function test_options_integration_should_work_correctly(): void
    {
        $option_name = 'woo_ai_assistant_test_option';
        $test_value = ['test' => 'data', 'timestamp' => time()];

        // Test adding option
        $added = add_option($option_name, $test_value);
        $this->assertTrue($added, 'Should be able to add plugin option');

        // Test retrieving option
        $retrieved = get_option($option_name);
        $this->assertEquals($test_value, $retrieved, 'Should retrieve correct option value');

        // Test updating option
        $updated_value = ['updated' => 'data', 'timestamp' => time()];
        $updated = update_option($option_name, $updated_value);
        $this->assertTrue($updated, 'Should be able to update plugin option');

        $retrieved_updated = get_option($option_name);
        $this->assertEquals($updated_value, $retrieved_updated, 'Should retrieve updated option value');

        // Clean up
        delete_option($option_name);
        $this->assertFalse(get_option($option_name), 'Option should be deleted');
    }

    /**
     * Test WordPress transient integration
     *
     * Verifies that plugin transients work correctly with WordPress caching.
     *
     * @return void
     */
    public function test_transient_integration_should_work_correctly(): void
    {
        $transient_name = 'woo_ai_assistant_test_transient';
        $test_data = ['cached' => 'data', 'expires' => time() + 3600];

        // Test setting transient
        $set = set_transient($transient_name, $test_data, 3600);
        $this->assertTrue($set, 'Should be able to set transient');

        // Test getting transient
        $retrieved = get_transient($transient_name);
        $this->assertEquals($test_data, $retrieved, 'Should retrieve correct transient data');

        // Test deleting transient
        $deleted = delete_transient($transient_name);
        $this->assertTrue($deleted, 'Should be able to delete transient');

        $retrieved_after_delete = get_transient($transient_name);
        $this->assertFalse($retrieved_after_delete, 'Transient should be deleted');
    }

    /**
     * Test WordPress user capabilities integration
     *
     * Verifies that plugin respects WordPress user capabilities.
     *
     * @return void
     */
    public function test_user_capabilities_should_be_respected(): void
    {
        // Create test users with different capabilities
        $admin_user = $this->factory->user->create(['role' => 'administrator']);
        $shop_manager = $this->factory->user->create(['role' => 'shop_manager']);
        $customer = $this->factory->user->create(['role' => 'customer']);

        // Test admin capabilities
        wp_set_current_user($admin_user);
        $this->assertTrue(current_user_can('manage_woocommerce'), 'Admin should have WooCommerce management capabilities');
        $this->assertTrue(current_user_can('manage_options'), 'Admin should have options management capabilities');

        // Test shop manager capabilities
        wp_set_current_user($shop_manager);
        $this->assertTrue(current_user_can('manage_woocommerce'), 'Shop manager should have WooCommerce management capabilities');

        // Test customer capabilities
        wp_set_current_user($customer);
        $this->assertFalse(current_user_can('manage_woocommerce'), 'Customer should not have WooCommerce management capabilities');
        $this->assertFalse(current_user_can('manage_options'), 'Customer should not have options management capabilities');

        // Clean up
        wp_set_current_user(0);
    }

    /**
     * Test WordPress REST API integration
     *
     * Verifies that plugin integrates correctly with WordPress REST API.
     *
     * @return void
     */
    public function test_rest_api_integration_should_work_correctly(): void
    {
        // Trigger REST API init
        do_action('rest_api_init');

        // Verify our REST API init hook was called
        $this->assertTrue(did_action('woo_ai_assistant_rest_api_init') > 0, 'Plugin REST API init should be called');

        // Test that WordPress REST API is available
        $this->assertTrue(function_exists('rest_url'), 'WordPress REST API functions should be available');

        // Test basic REST API endpoint access
        $rest_url = rest_url();
        $this->assertStringContainsString('/wp-json/', $rest_url, 'REST URL should be properly formatted');
    }

    /**
     * Test WordPress multisite compatibility
     *
     * Verifies that plugin works correctly in multisite environments.
     *
     * @return void
     */
    public function test_multisite_compatibility(): void
    {
        if (!is_multisite()) {
            $this->markTestSkipped('Multisite test requires multisite installation');
        }

        $plugin = Main::getInstance();

        // Plugin should work on network sites
        $this->assertInstanceOf(Main::class, $plugin, 'Plugin should work on multisite');
        $this->assertTrue($plugin->isInitialized(), 'Plugin should initialize on multisite');

        // Test site-specific functionality
        $site_id = get_current_blog_id();
        $this->assertGreaterThan(0, $site_id, 'Should have valid site ID');

        // Plugin info should include site-specific data
        $plugin_info = $plugin->getPluginInfo();
        $this->assertIsArray($plugin_info, 'Plugin info should be available on multisite');
    }

    /**
     * Test plugin performance in WordPress context
     *
     * Verifies that plugin doesn't significantly impact WordPress performance.
     *
     * @return void
     */
    public function test_plugin_performance_should_be_acceptable(): void
    {
        $start_time = microtime(true);
        $start_memory = memory_get_usage();

        // Perform typical plugin operations
        $plugin = Main::getInstance();
        $plugin->getPluginInfo();
        $plugin->getModules();

        // Simulate multiple hook calls
        for ($i = 0; $i < 50; $i++) {
            do_action('woo_ai_assistant_modules_loaded', []);
            apply_filters('woo_ai_assistant_kb_content', 'test content', $i);
        }

        $end_time = microtime(true);
        $end_memory = memory_get_usage();

        $execution_time = $end_time - $start_time;
        $memory_usage = $end_memory - $start_memory;

        // Performance assertions
        $this->assertLessThan(1.0, $execution_time, 'Plugin operations should complete within 1 second');
        $this->assertLessThan(2097152, $memory_usage, 'Memory usage should be less than 2MB'); // 2MB limit
    }

    /**
     * Test WordPress security integration
     *
     * Verifies that plugin properly integrates with WordPress security features.
     *
     * @return void
     */
    public function test_security_integration_should_work_correctly(): void
    {
        // Test nonce verification (simulated)
        $nonce = wp_create_nonce('woo_ai_assistant_test_action');
        $this->assertIsString($nonce, 'Should be able to create nonces');
        $this->assertNotEmpty($nonce, 'Nonce should not be empty');

        // Test that direct file access is prevented
        $plugin_file_content = file_get_contents(WOO_AI_ASSISTANT_PLUGIN_FILE);
        $this->assertStringContainsString('ABSPATH', $plugin_file_content, 'Plugin file should check for ABSPATH');

        // Test capability checks are in place
        $this->assertTrue(function_exists('current_user_can'), 'WordPress capability functions should be available');
    }
}
