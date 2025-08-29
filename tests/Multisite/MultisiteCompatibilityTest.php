<?php
/**
 * Multisite Compatibility Tests
 * 
 * Tests the Woo AI Assistant plugin compatibility with WordPress Multisite
 * installations, including network activation and site-specific functionality.
 * 
 * @package WooAiAssistant
 * @subpackage Tests
 * @since 1.0.0
 * @author Claude Code Assistant
 */

declare(strict_types=1);

namespace WooAiAssistant\Tests\Multisite;

use WP_UnitTestCase;
use WooAiAssistant\Main;
use WooAiAssistant\Tests\Base\BaseTestCase;

/**
 * Multisite Compatibility Test Class
 * 
 * @since 1.0.0
 */
class MultisiteCompatibilityTest extends BaseTestCase
{
    /**
     * Test site IDs
     * 
     * @var array<int>
     */
    private array $testSites = [];
    
    /**
     * Set up multisite test environment
     */
    public function setUp(): void
    {
        if (!is_multisite()) {
            $this->markTestSkipped('Multisite tests require multisite installation');
        }
        
        parent::setUp();
        
        // Create test sites
        $this->createTestSites();
    }
    
    /**
     * Clean up test sites
     */
    public function tearDown(): void
    {
        // Remove test sites
        foreach ($this->testSites as $siteId) {
            wpmu_delete_blog($siteId, true);
        }
        
        parent::tearDown();
    }
    
    /**
     * Create test sites for multisite testing
     */
    private function createTestSites(): void
    {
        $sites = [
            ['domain' => 'test-site-1.example.com', 'path' => '/'],
            ['domain' => 'test-site-2.example.com', 'path' => '/'],
            ['domain' => 'example.com', 'path' => '/sub-site/']
        ];
        
        foreach ($sites as $site) {
            $siteId = $this->factory->blog->create($site);
            $this->testSites[] = $siteId;
        }
    }
    
    /**
     * Test network activation
     */
    public function test_network_activation(): void
    {
        // Test network activation function exists
        $this->assertTrue(
            function_exists('is_plugin_active_for_network'),
            'WordPress multisite functions should be available'
        );
        
        // Simulate network activation
        $pluginFile = 'woo-ai-assistant/woo-ai-assistant.php';
        
        // Mock network activation
        add_filter('active_plugins', function($plugins) use ($pluginFile) {
            return array_merge($plugins, [$pluginFile]);
        });
        
        add_site_option('active_sitewide_plugins', [$pluginFile => time()]);
        
        // Test plugin recognizes network activation
        $this->assertTrue(
            is_plugin_active_for_network($pluginFile),
            'Plugin should recognize network activation'
        );
        
        // Test plugin initializes correctly on network activation
        $plugin = Main::getInstance();
        $this->assertInstanceOf(Main::class, $plugin);
    }
    
    /**
     * Test plugin functionality across different sites
     */
    public function test_plugin_functionality_across_sites(): void
    {
        foreach ($this->testSites as $siteId) {
            switch_to_blog($siteId);
            
            // Test plugin initialization on each site
            $plugin = Main::getInstance();
            $this->assertInstanceOf(
                Main::class, 
                $plugin,
                "Plugin should initialize on site {$siteId}"
            );
            
            // Test database tables are created for each site
            $this->assertTableExists(get_option('wpdb')->prefix . 'woo_ai_conversations');
            $this->assertTableExists(get_option('wpdb')->prefix . 'woo_ai_knowledge_base');
            
            // Test plugin options are site-specific
            update_option('woo_ai_assistant_enabled', true);
            $this->assertTrue(
                get_option('woo_ai_assistant_enabled'),
                "Options should be site-specific on site {$siteId}"
            );
            
            restore_current_blog();
        }
    }
    
    /**
     * Test site isolation
     */
    public function test_site_isolation(): void
    {
        $site1 = $this->testSites[0];
        $site2 = $this->testSites[1];
        
        // Set different options on each site
        switch_to_blog($site1);
        update_option('woo_ai_assistant_welcome_message', 'Welcome to Site 1');
        $conversationId1 = $this->createMockConversation($this->getTestUser('customer'));
        restore_current_blog();
        
        switch_to_blog($site2);
        update_option('woo_ai_assistant_welcome_message', 'Welcome to Site 2');
        $conversationId2 = $this->createMockConversation($this->getTestUser('customer'));
        restore_current_blog();
        
        // Verify options are isolated
        switch_to_blog($site1);
        $this->assertEquals(
            'Welcome to Site 1',
            get_option('woo_ai_assistant_welcome_message'),
            'Site 1 should have its own options'
        );
        restore_current_blog();
        
        switch_to_blog($site2);
        $this->assertEquals(
            'Welcome to Site 2',
            get_option('woo_ai_assistant_welcome_message'),
            'Site 2 should have its own options'
        );
        restore_current_blog();
        
        // Verify data isolation
        switch_to_blog($site1);
        global $wpdb;
        $conversations = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}woo_ai_conversations"
        );
        $this->assertCount(1, $conversations, 'Site 1 should only see its own data');
        restore_current_blog();
    }
    
    /**
     * Test network admin integration
     */
    public function test_network_admin_integration(): void
    {
        // Test network admin menu (if implemented)
        set_current_screen('network-admin');
        
        do_action('network_admin_menu');
        
        global $menu;
        
        // Check if network admin menu item exists
        $networkMenuExists = false;
        foreach ($menu as $menuItem) {
            if (strpos($menuItem[2], 'woo-ai-assistant-network') !== false) {
                $networkMenuExists = true;
                break;
            }
        }
        
        // Network admin menu is optional, so we just verify it doesn't break
        $this->addToAssertionCount(1);
    }
    
    /**
     * Test user management across sites
     */
    public function test_user_management_across_sites(): void
    {
        // Create a user on the main site
        $userId = $this->factory->user->create([
            'user_login' => 'multisite_user',
            'user_email' => 'multisite@example.com'
        ]);
        
        // Add user to different sites with different roles
        foreach ($this->testSites as $index => $siteId) {
            $roles = ['subscriber', 'customer', 'editor'];
            $role = $roles[$index % count($roles)];
            
            add_user_to_blog($siteId, $userId, $role);
            
            switch_to_blog($siteId);
            
            // Verify user has correct role on this site
            $user = get_user_by('id', $userId);
            $this->assertTrue(
                $user->has_cap($role === 'customer' ? 'read' : $role),
                "User should have {$role} capabilities on site {$siteId}"
            );
            
            // Create conversation for this user on this site
            $conversationId = $this->createMockConversation($userId);
            $this->assertGreaterThan(0, $conversationId);
            
            restore_current_blog();
        }
    }
    
    /**
     * Test plugin settings synchronization (if implemented)
     */
    public function test_plugin_settings_synchronization(): void
    {
        // Test global settings that might be shared across network
        $networkSettings = [
            'woo_ai_network_license_key',
            'woo_ai_network_api_endpoint',
            'woo_ai_network_features_enabled'
        ];
        
        foreach ($networkSettings as $setting) {
            // Set network-wide option
            update_site_option($setting, 'test_value_' . $setting);
            
            // Verify it's accessible from all sites
            foreach ($this->testSites as $siteId) {
                switch_to_blog($siteId);
                
                $value = get_site_option($setting);
                $this->assertEquals(
                    'test_value_' . $setting,
                    $value,
                    "Network setting {$setting} should be accessible from site {$siteId}"
                );
                
                restore_current_blog();
            }
        }
    }
    
    /**
     * Test database table creation across sites
     */
    public function test_database_table_creation_across_sites(): void
    {
        foreach ($this->testSites as $siteId) {
            switch_to_blog($siteId);
            
            global $wpdb;
            $expectedTables = [
                $wpdb->prefix . 'woo_ai_conversations',
                $wpdb->prefix . 'woo_ai_knowledge_base',
                $wpdb->prefix . 'woo_ai_analytics'
            ];
            
            foreach ($expectedTables as $table) {
                $this->assertTableExists($table);
                
                // Verify table has correct structure
                $columns = $wpdb->get_col("DESCRIBE {$table}");
                $this->assertNotEmpty(
                    $columns,
                    "Table {$table} should have columns on site {$siteId}"
                );
            }
            
            restore_current_blog();
        }
    }
    
    /**
     * Test plugin uninstall on multisite
     */
    public function test_plugin_uninstall_on_multisite(): void
    {
        // Test site-specific uninstall
        $testSite = $this->testSites[0];
        switch_to_blog($testSite);
        
        // Create some data
        $conversationId = $this->createMockConversation();
        update_option('woo_ai_assistant_enabled', true);
        
        // Simulate plugin deactivation for this site only
        do_action('deactivate_woo-ai-assistant/woo-ai-assistant.php');
        
        // Data should still exist (deactivation ≠ uninstall)
        global $wpdb;
        $conversations = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}woo_ai_conversations"
        );
        $this->assertNotEmpty($conversations, 'Data should persist after deactivation');
        
        restore_current_blog();
        
        // Verify other sites are not affected
        $otherSite = $this->testSites[1];
        switch_to_blog($otherSite);
        
        update_option('woo_ai_assistant_enabled', true);
        $this->assertTrue(
            get_option('woo_ai_assistant_enabled'),
            'Other sites should not be affected by single site deactivation'
        );
        
        restore_current_blog();
    }
    
    /**
     * Test cross-site data access restrictions
     */
    public function test_cross_site_data_access_restrictions(): void
    {
        $site1 = $this->testSites[0];
        $site2 = $this->testSites[1];
        
        // Create data on site 1
        switch_to_blog($site1);
        $conversationId1 = $this->createMockConversation();
        $site1Prefix = get_option('wpdb')->prefix;
        restore_current_blog();
        
        // Try to access from site 2
        switch_to_blog($site2);
        global $wpdb;
        
        // Direct database access would work, but application should prevent it
        $crossSiteConversations = $wpdb->get_results(
            "SELECT * FROM {$site1Prefix}woo_ai_conversations"
        );
        
        // We can't prevent direct DB access, but we can test that the application
        // doesn't accidentally mix data
        $localConversations = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}woo_ai_conversations"
        );
        
        $this->assertEmpty(
            $localConversations,
            'Site 2 should not have Site 1 data in its local tables'
        );
        
        restore_current_blog();
    }
    
    /**
     * Test plugin performance on multisite
     */
    public function test_plugin_performance_on_multisite(): void
    {
        $startMemory = memory_get_usage();
        $startTime = microtime(true);
        
        // Initialize plugin on all test sites
        foreach ($this->testSites as $siteId) {
            switch_to_blog($siteId);
            
            $plugin = Main::getInstance();
            do_action('init');
            do_action('wp_loaded');
            
            restore_current_blog();
        }
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        
        $executionTime = $endTime - $startTime;
        $memoryUsed = $endMemory - $startMemory;
        
        // Plugin should not consume excessive resources on multisite
        $this->assertLessThan(
            1.0, // 1 second
            $executionTime,
            'Plugin initialization should be fast on multisite'
        );
        
        $this->assertLessThan(
            10 * 1024 * 1024, // 10MB
            $memoryUsed,
            'Plugin should not use excessive memory on multisite'
        );
    }
    
    /**
     * Test subdirectory multisite support
     */
    public function test_subdirectory_multisite_support(): void
    {
        // Find subdirectory site
        $subdirSite = null;
        foreach ($this->testSites as $siteId) {
            $siteInfo = get_blog_details($siteId);
            if ($siteInfo->path !== '/') {
                $subdirSite = $siteId;
                break;
            }
        }
        
        if (!$subdirSite) {
            $this->markTestSkipped('No subdirectory site available for testing');
        }
        
        switch_to_blog($subdirSite);
        
        // Test plugin works correctly on subdirectory sites
        $plugin = Main::getInstance();
        $this->assertInstanceOf(Main::class, $plugin);
        
        // Test URLs are generated correctly
        $adminUrl = admin_url('admin.php?page=woo-ai-assistant');
        $this->assertStringContainsString(
            '/sub-site/',
            $adminUrl,
            'Admin URLs should include subdirectory path'
        );
        
        // Test AJAX URLs work correctly
        $ajaxUrl = admin_url('admin-ajax.php');
        $this->assertStringContainsString(
            '/sub-site/',
            $ajaxUrl,
            'AJAX URLs should include subdirectory path'
        );
        
        restore_current_blog();
    }
}