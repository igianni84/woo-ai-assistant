<?php
/**
 * Tests for Main Plugin Class
 *
 * Comprehensive unit tests for the Main singleton orchestrator class.
 * Tests initialization, module loading, hook registration, and plugin lifecycle.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Unit
 * @since 1.0.0
 * @author Claude Code Assistant
 */

namespace WooAiAssistant\Tests\Unit;

use WooAiAssistant\Main;
use WooAiAssistant\Common\Utils;
use WooAiAssistant\Tests\Unit\WooAiBaseTestCase;

/**
 * Class MainTest
 *
 * Test cases for the Main plugin orchestrator class.
 * Verifies singleton pattern, initialization, module management,
 * and WordPress integration.
 *
 * @since 1.0.0
 */
class MainTest extends WooAiBaseTestCase
{
    /**
     * Main plugin instance
     *
     * @var Main
     */
    private $main;

    /**
     * Set up test environment
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->main = Main::getInstance();
    }

    /**
     * Test singleton pattern implementation
     *
     * Verifies that Main class follows singleton pattern correctly
     * and returns the same instance on multiple calls.
     *
     * @return void
     */
    public function test_getInstance_should_return_singleton_instance(): void
    {
        $instance1 = Main::getInstance();
        $instance2 = Main::getInstance();

        $this->assertInstanceOf(Main::class, $instance1);
        $this->assertSame($instance1, $instance2, 'getInstance should return the same instance (singleton pattern)');
    }

    /**
     * Test plugin initialization status
     *
     * Verifies that the plugin correctly tracks its initialization status.
     *
     * @return void
     */
    public function test_isInitialized_should_return_true_after_initialization(): void
    {
        $this->assertTrue($this->main->isInitialized(), 'Plugin should be initialized after instantiation');
    }

    /**
     * Test module registration functionality
     *
     * Verifies that modules can be registered and retrieved correctly.
     *
     * @return void
     */
    public function test_registerModule_should_store_module_instance(): void
    {
        $mockModule = new \stdClass();
        $mockModule->name = 'TestModule';

        $this->main->registerModule('test_module', $mockModule);

        $registeredModule = $this->main->getModule('test_module');
        $this->assertSame($mockModule, $registeredModule, 'Registered module should be retrievable');
    }

    /**
     * Test getting non-existent module
     *
     * Verifies that getting a non-existent module returns null.
     *
     * @return void
     */
    public function test_getModule_should_return_null_for_nonexistent_module(): void
    {
        $result = $this->main->getModule('nonexistent_module');
        $this->assertNull($result, 'Getting non-existent module should return null');
    }

    /**
     * Test getting all registered modules
     *
     * Verifies that getModules returns an array of all registered modules.
     *
     * @return void
     */
    public function test_getModules_should_return_array_of_registered_modules(): void
    {
        $mockModule1 = new \stdClass();
        $mockModule2 = new \stdClass();

        $this->main->registerModule('module1', $mockModule1);
        $this->main->registerModule('module2', $mockModule2);

        $modules = $this->main->getModules();

        $this->assertIsArray($modules, 'getModules should return an array');
        $this->assertCount(2, $modules, 'Should have 2 registered modules');
        $this->assertArrayHasKey('module1', $modules, 'Should contain module1');
        $this->assertArrayHasKey('module2', $modules, 'Should contain module2');
        $this->assertSame($mockModule1, $modules['module1'], 'Module1 should match registered instance');
        $this->assertSame($mockModule2, $modules['module2'], 'Module2 should match registered instance');
    }

    /**
     * Test plugin information retrieval
     *
     * Verifies that getPluginInfo returns correct plugin information.
     *
     * @return void
     */
    public function test_getPluginInfo_should_return_complete_plugin_information(): void
    {
        $pluginInfo = $this->main->getPluginInfo();

        $this->assertIsArray($pluginInfo, 'Plugin info should be an array');
        
        $expectedKeys = [
            'name',
            'version', 
            'path',
            'url',
            'development_mode',
            'woocommerce_active',
            'modules_loaded',
            'cache_enabled',
            'logging_enabled'
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $pluginInfo, "Plugin info should contain '{$key}' key");
        }

        $this->assertEquals('Woo AI Assistant', $pluginInfo['name'], 'Plugin name should be correct');
        $this->assertTrue($pluginInfo['development_mode'], 'Development mode should be active in tests');
        $this->assertIsString($pluginInfo['version'], 'Version should be a string');
        $this->assertIsString($pluginInfo['path'], 'Path should be a string');
        $this->assertIsString($pluginInfo['url'], 'URL should be a string');
        $this->assertIsBool($pluginInfo['woocommerce_active'], 'WooCommerce active should be boolean');
        $this->assertIsInt($pluginInfo['modules_loaded'], 'Modules loaded should be an integer');
    }

    /**
     * Test plugin action links modification
     *
     * Verifies that plugin action links are correctly modified.
     *
     * @return void
     */
    public function test_addPluginActionLinks_should_add_settings_link(): void
    {
        $originalLinks = ['deactivate' => 'Deactivate'];
        
        $modifiedLinks = $this->main->addPluginActionLinks($originalLinks);

        $this->assertIsArray($modifiedLinks, 'Modified links should be an array');
        $this->assertCount(2, $modifiedLinks, 'Should have 2 links (original + settings)');
        $this->assertContains('Settings', implode(' ', $modifiedLinks), 'Should contain Settings link');
    }

    /**
     * Test plugin row meta links modification
     *
     * Verifies that plugin row meta links are correctly modified for our plugin.
     *
     * @return void
     */
    public function test_addPluginRowMeta_should_add_meta_links_for_our_plugin(): void
    {
        $originalLinks = ['Version 1.0.0'];
        $pluginFile = WOO_AI_ASSISTANT_BASENAME;

        $modifiedLinks = $this->main->addPluginRowMeta($originalLinks, $pluginFile);

        $this->assertIsArray($modifiedLinks, 'Modified links should be an array');
        $this->assertGreaterThan(count($originalLinks), count($modifiedLinks), 'Should have more links than original');
        
        $linkText = implode(' ', $modifiedLinks);
        $this->assertStringContainsString('GitHub', $linkText, 'Should contain GitHub link');
        $this->assertStringContainsString('Support', $linkText, 'Should contain Support link');
        $this->assertStringContainsString('Documentation', $linkText, 'Should contain Documentation link');
    }

    /**
     * Test plugin row meta links for other plugins
     *
     * Verifies that plugin row meta links are not modified for other plugins.
     *
     * @return void
     */
    public function test_addPluginRowMeta_should_not_modify_other_plugins(): void
    {
        $originalLinks = ['Version 1.0.0'];
        $otherPluginFile = 'other-plugin/other-plugin.php';

        $modifiedLinks = $this->main->addPluginRowMeta($originalLinks, $otherPluginFile);

        $this->assertEquals($originalLinks, $modifiedLinks, 'Should not modify links for other plugins');
    }

    /**
     * Test development notice display
     *
     * Verifies that development notice is displayed correctly.
     *
     * @return void
     */
    public function test_showDevelopmentNotice_should_output_development_notice(): void
    {
        // Mock the current screen
        $GLOBALS['current_screen'] = (object) [
            'id' => 'toplevel_page_woo-ai-assistant'
        ];

        ob_start();
        $this->main->showDevelopmentNotice();
        $output = ob_get_clean();

        $this->assertStringContainsString('Development Mode', $output, 'Should contain development mode text');
        $this->assertStringContainsString('notice notice-info', $output, 'Should have correct CSS classes');

        // Clean up
        unset($GLOBALS['current_screen']);
    }

    /**
     * Test class name follows PascalCase convention
     *
     * Verifies that the Main class follows PascalCase naming convention.
     *
     * @return void
     */
    public function test_main_class_name_should_follow_pascal_case_convention(): void
    {
        $this->assertClassFollowsPascalCase(Main::class);
    }

    /**
     * Test method names follow camelCase convention
     *
     * Verifies that all public methods follow camelCase naming convention.
     *
     * @return void
     */
    public function test_main_public_methods_should_follow_camel_case_convention(): void
    {
        $publicMethods = [
            'getInstance',
            'loadModules',
            'initRestApi',
            'initFrontend', 
            'initAdmin',
            'addPluginActionLinks',
            'addPluginRowMeta',
            'showDevelopmentNotice',
            'getModules',
            'registerModule',
            'getModule',
            'isInitialized',
            'getPluginInfo',
            'shutdown'
        ];

        foreach ($publicMethods as $methodName) {
            $this->assertMethodFollowsCamelCase($this->main, $methodName);
        }
    }

    /**
     * Test that required hooks are registered
     *
     * Verifies that essential WordPress hooks are properly registered.
     *
     * @return void
     */
    public function test_required_hooks_should_be_registered(): void
    {
        // Test that init hook is registered for loadModules
        $this->assertGreaterThan(
            0,
            has_action('init', [$this->main, 'loadModules']),
            'loadModules should be hooked to init action'
        );

        // Test that rest_api_init hook is registered
        $this->assertGreaterThan(
            0,
            has_action('rest_api_init', [$this->main, 'initRestApi']),
            'initRestApi should be hooked to rest_api_init action'
        );

        // Test that wp_loaded hook is registered
        $this->assertGreaterThan(
            0,
            has_action('wp_loaded', [$this->main, 'initFrontend']),
            'initFrontend should be hooked to wp_loaded action'
        );
    }

    /**
     * Test WordPress hooks fire correctly
     *
     * Verifies that custom plugin hooks are fired at appropriate times.
     *
     * @return void
     */
    public function test_plugin_hooks_should_fire_correctly(): void
    {
        $moduleLoadedFired = false;
        $restApiInitFired = false;

        // Hook into our custom actions
        add_action('woo_ai_assistant_modules_loaded', function() use (&$moduleLoadedFired) {
            $moduleLoadedFired = true;
        });

        add_action('woo_ai_assistant_rest_api_init', function() use (&$restApiInitFired) {
            $restApiInitFired = true;
        });

        // Trigger the methods that should fire these hooks
        $this->main->loadModules();
        $this->main->initRestApi();

        $this->assertTrue($moduleLoadedFired, 'woo_ai_assistant_modules_loaded action should be fired');
        $this->assertTrue($restApiInitFired, 'woo_ai_assistant_rest_api_init action should be fired');
    }

    /**
     * Test error handling when WooCommerce is not active
     *
     * Verifies that the plugin handles missing WooCommerce gracefully.
     *
     * @return void
     */
    public function test_loadModules_should_handle_missing_woocommerce(): void
    {
        // Mock WooCommerce as inactive
        add_filter('woo_ai_assistant_is_woocommerce_active', '__return_false');

        // Capture any output or errors
        ob_start();
        $this->main->loadModules();
        ob_get_clean();

        // The method should complete without fatal errors
        $this->assertTrue(true, 'loadModules should handle missing WooCommerce without fatal errors');

        // Clean up
        remove_filter('woo_ai_assistant_is_woocommerce_active', '__return_false');
    }

    /**
     * Test shutdown method
     *
     * Verifies that shutdown method executes without errors.
     *
     * @return void
     */
    public function test_shutdown_should_execute_without_errors(): void
    {
        $shutdownFired = false;

        // Hook into shutdown action
        add_action('woo_ai_assistant_shutdown', function() use (&$shutdownFired) {
            $shutdownFired = true;
        });

        // Execute shutdown
        $this->main->shutdown();

        $this->assertTrue($shutdownFired, 'woo_ai_assistant_shutdown action should be fired during shutdown');
    }

    /**
     * Test plugin constants are defined
     *
     * Verifies that required plugin constants are properly defined.
     *
     * @return void
     */
    public function test_plugin_constants_should_be_defined(): void
    {
        $this->assertTrue(defined('WOO_AI_ASSISTANT_PLUGIN_DIR'), 'WOO_AI_ASSISTANT_PLUGIN_DIR should be defined');
        $this->assertTrue(defined('WOO_AI_ASSISTANT_PLUGIN_FILE'), 'WOO_AI_ASSISTANT_PLUGIN_FILE should be defined');
        $this->assertTrue(defined('WOO_AI_ASSISTANT_BASENAME'), 'WOO_AI_ASSISTANT_BASENAME should be defined');

        // Verify constant naming follows SCREAMING_SNAKE_CASE
        $this->assertConstantFollowsScreamingSnakeCase('WOO_AI_ASSISTANT_PLUGIN_DIR');
        $this->assertConstantFollowsScreamingSnakeCase('WOO_AI_ASSISTANT_PLUGIN_FILE');
        $this->assertConstantFollowsScreamingSnakeCase('WOO_AI_ASSISTANT_BASENAME');
    }

    /**
     * Test memory usage remains reasonable
     *
     * Verifies that the plugin doesn't consume excessive memory.
     *
     * @return void
     */
    public function test_plugin_memory_usage_should_be_reasonable(): void
    {
        $initialMemory = memory_get_usage();
        
        // Perform multiple operations
        for ($i = 0; $i < 100; $i++) {
            $this->main->getPluginInfo();
            $this->main->getModules();
        }

        $finalMemory = memory_get_usage();
        $memoryIncrease = $finalMemory - $initialMemory;

        // Memory increase should be less than 1MB for these operations
        $this->assertLessThan(1048576, $memoryIncrease, 'Memory increase should be less than 1MB');
    }
}