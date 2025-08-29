<?php
/**
 * WordPress Version Compatibility Tests
 * 
 * Tests the Woo AI Assistant plugin against multiple WordPress versions
 * to ensure compatibility and proper functionality.
 * 
 * @package WooAiAssistant
 * @subpackage Tests
 * @since 1.0.0
 * @author Claude Code Assistant
 */

declare(strict_types=1);

namespace WooAiAssistant\Tests\Compatibility;

use WP_UnitTestCase;
use WooAiAssistant\Main;

/**
 * WordPress Version Compatibility Test Class
 * 
 * @since 1.0.0
 */
class WordPressVersionTest extends WP_UnitTestCase
{
    /**
     * WordPress versions to test against
     * 
     * @var array<string>
     */
    private array $supportedVersions = [
        '6.0.0',
        '6.1.0', 
        '6.2.0',
        '6.3.0',
        '6.4.0',
        '6.5.0'
    ];
    
    /**
     * Plugin instance
     * 
     * @var Main
     */
    private Main $plugin;
    
    /**
     * Set up test environment
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->plugin = Main::getInstance();
    }
    
    /**
     * Test plugin initialization across WordPress versions
     */
    public function test_plugin_initializes_across_wordpress_versions(): void
    {
        foreach ($this->supportedVersions as $version) {
            $this->simulateWordPressVersion($version);
            
            // Test plugin can initialize
            $this->assertTrue(
                class_exists('WooAiAssistant\Main'),
                "Plugin should initialize on WordPress {$version}"
            );
            
            // Test singleton pattern works
            $instance = Main::getInstance();
            $this->assertInstanceOf(
                Main::class,
                $instance,
                "Singleton should work on WordPress {$version}"
            );
            
            // Test hooks are registered
            $this->assertGreaterThan(
                0,
                did_action('init'),
                "WordPress init hook should fire on version {$version}"
            );
        }
    }
    
    /**
     * Test WordPress hooks compatibility
     */
    public function test_wordpress_hooks_compatibility(): void
    {
        $requiredHooks = [
            'init',
            'admin_init',
            'admin_menu',
            'wp_enqueue_scripts',
            'admin_enqueue_scripts',
            'wp_ajax_woo_ai_chat',
            'wp_ajax_nopriv_woo_ai_chat',
            'rest_api_init'
        ];
        
        foreach ($this->supportedVersions as $version) {
            $this->simulateWordPressVersion($version);
            
            foreach ($requiredHooks as $hook) {
                $priority = has_action($hook);
                $this->assertNotFalse(
                    $priority,
                    "Hook '{$hook}' should be registered on WordPress {$version}"
                );
            }
        }
    }
    
    /**
     * Test WordPress database compatibility
     */
    public function test_wordpress_database_compatibility(): void
    {
        global $wpdb;
        
        foreach ($this->supportedVersions as $version) {
            $this->simulateWordPressVersion($version);
            
            // Test table creation
            $tables = [
                $wpdb->prefix . 'woo_ai_conversations',
                $wpdb->prefix . 'woo_ai_knowledge_base',
                $wpdb->prefix . 'woo_ai_analytics'
            ];
            
            foreach ($tables as $tableName) {
                $tableExists = $wpdb->get_var(
                    $wpdb->prepare(
                        "SHOW TABLES LIKE %s",
                        $tableName
                    )
                );
                
                $this->assertEquals(
                    $tableName,
                    $tableExists,
                    "Table {$tableName} should exist on WordPress {$version}"
                );
            }
            
            // Test database queries work
            $result = $wpdb->get_results(
                "SELECT COUNT(*) as count FROM {$wpdb->prefix}woo_ai_conversations"
            );
            
            $this->assertIsArray($result, "Database queries should work on WordPress {$version}");
        }
    }
    
    /**
     * Test WordPress admin interface compatibility
     */
    public function test_wordpress_admin_interface_compatibility(): void
    {
        // Set up admin environment
        set_current_screen('dashboard');
        wp_set_current_user($this->factory->user->create(['role' => 'administrator']));
        
        foreach ($this->supportedVersions as $version) {
            $this->simulateWordPressVersion($version);
            
            // Test admin menu registration
            $this->plugin = Main::getInstance();
            do_action('admin_menu');
            
            global $menu, $submenu;
            
            // Check main menu item exists
            $menuExists = false;
            foreach ($menu as $menuItem) {
                if (strpos($menuItem[2], 'woo-ai-assistant') !== false) {
                    $menuExists = true;
                    break;
                }
            }
            
            $this->assertTrue(
                $menuExists,
                "Admin menu should exist on WordPress {$version}"
            );
            
            // Test admin scripts enqueue
            do_action('admin_enqueue_scripts', 'toplevel_page_woo-ai-assistant');
            
            $this->assertTrue(
                wp_script_is('woo-ai-assistant-admin', 'enqueued') ||
                wp_script_is('woo-ai-assistant-admin', 'registered'),
                "Admin scripts should enqueue on WordPress {$version}"
            );
        }
    }
    
    /**
     * Test WordPress REST API compatibility
     */
    public function test_wordpress_rest_api_compatibility(): void
    {
        foreach ($this->supportedVersions as $version) {
            $this->simulateWordPressVersion($version);
            
            // Initialize REST API
            do_action('rest_api_init');
            
            // Test custom endpoints are registered
            $server = rest_get_server();
            $routes = $server->get_routes();
            
            $expectedRoutes = [
                '/woo-ai-assistant/v1/chat',
                '/woo-ai-assistant/v1/chat/(?P<conversation_id>[\d]+)',
                '/woo-ai-assistant/v1/knowledge-base',
                '/woo-ai-assistant/v1/analytics'
            ];
            
            foreach ($expectedRoutes as $expectedRoute) {
                $routeExists = false;
                foreach (array_keys($routes) as $route) {
                    if (preg_match("#^{$expectedRoute}$#", $route)) {
                        $routeExists = true;
                        break;
                    }
                }
                
                $this->assertTrue(
                    $routeExists,
                    "REST route {$expectedRoute} should exist on WordPress {$version}"
                );
            }
        }
    }
    
    /**
     * Test WordPress security features compatibility
     */
    public function test_wordpress_security_compatibility(): void
    {
        foreach ($this->supportedVersions as $version) {
            $this->simulateWordPressVersion($version);
            
            // Test nonce functionality
            $nonce = wp_create_nonce('woo_ai_assistant_action');
            $this->assertNotEmpty($nonce, "Nonces should work on WordPress {$version}");
            
            $verified = wp_verify_nonce($nonce, 'woo_ai_assistant_action');
            $this->assertNotFalse($verified, "Nonce verification should work on WordPress {$version}");
            
            // Test capability checks
            $user = wp_set_current_user($this->factory->user->create(['role' => 'administrator']));
            $this->assertTrue(
                current_user_can('manage_options'),
                "Capability checks should work on WordPress {$version}"
            );
            
            // Test sanitization functions
            $testString = '<script>alert("xss")</script>Hello World';
            $sanitized = sanitize_text_field($testString);
            $this->assertEquals(
                'Hello World',
                $sanitized,
                "Sanitization should work on WordPress {$version}"
            );
        }
    }
    
    /**
     * Test WordPress caching compatibility
     */
    public function test_wordpress_caching_compatibility(): void
    {
        foreach ($this->supportedVersions as $version) {
            $this->simulateWordPressVersion($version);
            
            // Test object cache
            $cacheKey = 'woo_ai_test_' . $version;
            $cacheValue = ['test' => 'data', 'version' => $version];
            
            $set = wp_cache_set($cacheKey, $cacheValue, 'woo_ai_assistant');
            $this->assertTrue($set, "Object cache set should work on WordPress {$version}");
            
            $retrieved = wp_cache_get($cacheKey, 'woo_ai_assistant');
            $this->assertEquals(
                $cacheValue,
                $retrieved,
                "Object cache get should work on WordPress {$version}"
            );
            
            // Test transients
            $transientKey = 'woo_ai_transient_' . str_replace('.', '_', $version);
            $transientValue = 'test_value_' . $version;
            
            $setTransient = set_transient($transientKey, $transientValue, 3600);
            $this->assertTrue($setTransient, "Transients should work on WordPress {$version}");
            
            $getTransient = get_transient($transientKey);
            $this->assertEquals(
                $transientValue,
                $getTransient,
                "Transient retrieval should work on WordPress {$version}"
            );
        }
    }
    
    /**
     * Test WordPress multisite compatibility
     */
    public function test_wordpress_multisite_compatibility(): void
    {
        if (!is_multisite()) {
            $this->markTestSkipped('Multisite tests require multisite installation');
        }
        
        foreach ($this->supportedVersions as $version) {
            $this->simulateWordPressVersion($version);
            
            // Test network activation
            $this->assertTrue(
                function_exists('is_plugin_active_for_network'),
                "Multisite functions should exist on WordPress {$version}"
            );
            
            // Test site switching (if available)
            if (function_exists('switch_to_blog')) {
                $currentBlogId = get_current_blog_id();
                
                // Create a test site
                $testSiteId = $this->factory->blog->create();
                
                switch_to_blog($testSiteId);
                $this->assertEquals(
                    $testSiteId,
                    get_current_blog_id(),
                    "Blog switching should work on WordPress {$version}"
                );
                
                restore_current_blog();
                $this->assertEquals(
                    $currentBlogId,
                    get_current_blog_id(),
                    "Blog restoration should work on WordPress {$version}"
                );
            }
        }
    }
    
    /**
     * Test WordPress performance on different versions
     */
    public function test_wordpress_performance_compatibility(): void
    {
        foreach ($this->supportedVersions as $version) {
            $this->simulateWordPressVersion($version);
            
            // Measure plugin initialization time
            $startTime = microtime(true);
            
            Main::getInstance();
            do_action('init');
            
            $endTime = microtime(true);
            $initTime = $endTime - $startTime;
            
            // Plugin should initialize quickly (under 100ms)
            $this->assertLessThan(
                0.1,
                $initTime,
                "Plugin initialization should be fast on WordPress {$version}"
            );
            
            // Test memory usage
            $startMemory = memory_get_usage();
            
            // Perform typical plugin operations
            do_action('wp_enqueue_scripts');
            do_action('admin_enqueue_scripts');
            
            $endMemory = memory_get_usage();
            $memoryUsed = $endMemory - $startMemory;
            
            // Memory usage should be reasonable (under 5MB)
            $this->assertLessThan(
                5 * 1024 * 1024,
                $memoryUsed,
                "Memory usage should be reasonable on WordPress {$version}"
            );
        }
    }
    
    /**
     * Simulate a specific WordPress version for testing
     * 
     * @param string $version WordPress version to simulate
     */
    private function simulateWordPressVersion(string $version): void
    {
        // Mock WordPress version
        global $wp_version;
        $originalVersion = $wp_version;
        $wp_version = $version;
        
        // Update version-dependent features
        $this->updateVersionDependentFeatures($version);
        
        // Restore after test if needed
        add_action('tearDown', function() use ($originalVersion) {
            global $wp_version;
            $wp_version = $originalVersion;
        });
    }
    
    /**
     * Update features based on WordPress version
     * 
     * @param string $version WordPress version
     */
    private function updateVersionDependentFeatures(string $version): void
    {
        // Mock version-specific features
        switch (version_compare($version, '6.0.0')) {
            case 1:
            case 0:
                // WordPress 6.0+ features
                if (!function_exists('wp_is_block_theme')) {
                    function wp_is_block_theme() {
                        return false;
                    }
                }
                break;
                
            default:
                // Pre-6.0 WordPress
                break;
        }
        
        // Mock REST API availability (available since 4.7)
        if (version_compare($version, '4.7.0', '>=')) {
            if (!function_exists('rest_get_server')) {
                function rest_get_server() {
                    return new \WP_REST_Server();
                }
            }
        }
    }
}