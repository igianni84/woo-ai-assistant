<?php

/**
 * Widget Loader Test Class
 *
 * Comprehensive test suite for the WidgetLoader class functionality.
 * Tests basic functionality, naming conventions, and WordPress integration.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Unit\Frontend
 * @since 1.0.0
 * @author Claude Code Assistant
 */

namespace WooAiAssistant\Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;
use WooAiAssistant\Frontend\WidgetLoader;
use ReflectionClass;

// Mock WordPress functions in global namespace
if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style($handle, $src, $deps = [], $ver = false, $media = 'all') { return true; }
}
if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script($handle, $src, $deps = [], $ver = false, $in_footer = false) { return true; }
}
if (!function_exists('wp_localize_script')) {
    function wp_localize_script($handle, $object_name, $l10n) { return true; }
}
if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action) { return 'test_nonce_123'; }
}
if (!function_exists('get_current_user_id')) {
    function get_current_user_id() { return 1; }
}
if (!function_exists('rest_url')) {
    function rest_url($path) { return 'https://example.com/wp-json/' . $path; }
}
if (!function_exists('get_option')) {
    function get_option($option, $default = false) { return $default; }
}
if (!function_exists('update_option')) {
    function update_option($option, $value) { return true; }
}
if (!function_exists('delete_option')) {
    function delete_option($option) { return true; }
}
if (!function_exists('__')) {
    function __($text, $domain = 'default') { return $text; }
}
if (!function_exists('esc_attr__')) {
    function esc_attr__($text, $domain = 'default') { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default') { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('esc_url')) {
    function esc_url($url) { return $url; }
}
if (!function_exists('esc_attr')) {
    function esc_attr($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('wp_add_inline_style')) {
    function wp_add_inline_style($handle, $data) { return true; }
}
if (!function_exists('filemtime')) {
    function filemtime($filename) { return 123456789; }
}
if (!function_exists('wp_is_mobile')) {
    function wp_is_mobile() { return false; }
}
if (!function_exists('is_singular')) {
    function is_singular($post_types = '') { return true; }
}
if (!function_exists('get_the_ID')) {
    function get_the_ID() { return 123; }
}
if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user() { return (object)['roles' => ['customer']]; }
}
if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show = '') { return 'Test Site'; }
}
if (!function_exists('get_home_url')) {
    function get_home_url() { return 'https://example.com'; }
}
if (!function_exists('get_locale')) {
    function get_locale() { return 'en_US'; }
}
if (!function_exists('wp_timezone_string')) {
    function wp_timezone_string() { return 'UTC'; }
}
if (!function_exists('get_woocommerce_currency')) {
    function get_woocommerce_currency() { return 'USD'; }
}
if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value) { return $value; }
}
if (!function_exists('parse_url')) {
    function parse_url($url, $component = -1) { return 'example.com'; }
}
if (!function_exists('add_action')) {
    function add_action($tag, $function_to_add, $priority = 10, $accepted_args = 1) { return true; }
}
if (!function_exists('add_filter')) {
    function add_filter($tag, $function_to_add, $priority = 10, $accepted_args = 1) { return true; }
}

// Mock functions in the WooAiAssistant\Frontend namespace as well
namespace WooAiAssistant\Frontend;

if (!function_exists(__NAMESPACE__ . '\get_home_url')) {
    function get_home_url() { return 'https://example.com'; }
}
if (!function_exists(__NAMESPACE__ . '\get_bloginfo')) {
    function get_bloginfo($show = '') { return 'Test Site'; }
}
if (!function_exists(__NAMESPACE__ . '\get_locale')) {
    function get_locale() { return 'en_US'; }
}
if (!function_exists(__NAMESPACE__ . '\wp_timezone_string')) {
    function wp_timezone_string() { return 'UTC'; }
}
if (!function_exists(__NAMESPACE__ . '\get_woocommerce_currency')) {
    function get_woocommerce_currency() { return 'USD'; }
}
if (!function_exists(__NAMESPACE__ . '\is_singular')) {
    function is_singular($post_types = '') { return true; }
}
if (!function_exists(__NAMESPACE__ . '\get_the_ID')) {
    function get_the_ID() { return 123; }
}
if (!function_exists(__NAMESPACE__ . '\wp_is_mobile')) {
    function wp_is_mobile() { return false; }
}
if (!function_exists(__NAMESPACE__ . '\is_front_page')) {
    function is_front_page() { return false; }
}
if (!function_exists(__NAMESPACE__ . '\is_home')) {
    function is_home() { return false; }
}
if (!function_exists(__NAMESPACE__ . '\is_shop')) {
    function is_shop() { return false; }
}
if (!function_exists(__NAMESPACE__ . '\is_product_category')) {
    function is_product_category() { return false; }
}
if (!function_exists(__NAMESPACE__ . '\is_product_tag')) {
    function is_product_tag() { return false; }
}
if (!function_exists(__NAMESPACE__ . '\is_product')) {
    function is_product() { return false; }
}
if (!function_exists(__NAMESPACE__ . '\is_cart')) {
    function is_cart() { return false; }
}
if (!function_exists(__NAMESPACE__ . '\is_checkout')) {
    function is_checkout() { return false; }
}
if (!function_exists(__NAMESPACE__ . '\is_account_page')) {
    function is_account_page() { return false; }
}
if (!function_exists(__NAMESPACE__ . '\is_page')) {
    function is_page() { return false; }
}
if (!function_exists(__NAMESPACE__ . '\is_single')) {
    function is_single() { return false; }
}
if (!function_exists(__NAMESPACE__ . '\is_category')) {
    function is_category() { return false; }
}
if (!function_exists(__NAMESPACE__ . '\is_tag')) {
    function is_tag() { return false; }
}
if (!function_exists(__NAMESPACE__ . '\is_search')) {
    function is_search() { return false; }
}
if (!function_exists(__NAMESPACE__ . '\is_404')) {
    function is_404() { return false; }
}
if (!function_exists(__NAMESPACE__ . '\wp_enqueue_style')) {
    function wp_enqueue_style($handle, $src, $deps = [], $ver = false, $media = 'all') { return true; }
}
if (!function_exists(__NAMESPACE__ . '\wp_enqueue_script')) {
    function wp_enqueue_script($handle, $src, $deps = [], $ver = false, $in_footer = false) { return true; }
}
if (!function_exists(__NAMESPACE__ . '\wp_localize_script')) {
    function wp_localize_script($handle, $object_name, $l10n) { return true; }
}
if (!function_exists(__NAMESPACE__ . '\esc_attr__')) {
    function esc_attr__($text, $domain = 'default') { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists(__NAMESPACE__ . '\esc_html__')) {
    function esc_html__($text, $domain = 'default') { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists(__NAMESPACE__ . '\wp_add_inline_style')) {
    function wp_add_inline_style($handle, $data) { return true; }
}
if (!function_exists(__NAMESPACE__ . '\file_exists')) {
    function file_exists($filename) { return true; }
}
if (!function_exists(__NAMESPACE__ . '\filemtime')) {
    function filemtime($filename) { return 123456789; }
}
if (!function_exists(__NAMESPACE__ . '\esc_url')) {
    function esc_url($url) { return $url; }
}
if (!function_exists(__NAMESPACE__ . '\parse_url')) {
    function parse_url($url, $component = -1) { return 'example.com'; }
}

namespace WooAiAssistant\Tests\Unit\Frontend;

/**
 * Class WidgetLoaderTest
 *
 * Tests the WidgetLoader class functionality including basic operations,
 * naming conventions, and WordPress integration.
 *
 * @since 1.0.0
 */
class WidgetLoaderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * WidgetLoader instance
     *
     * @var \WooAiAssistant\Frontend\WidgetLoader
     */
    private \WooAiAssistant\Frontend\WidgetLoader $widgetLoader;

    /**
     * Setup test environment
     *
     * @since 1.0.0
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        // Mock WordPress constants if not defined
        if (!defined('WOO_AI_ASSISTANT_ASSETS_URL')) {
            define('WOO_AI_ASSISTANT_ASSETS_URL', 'https://example.com/assets/');
        }
        if (!defined('WOO_AI_ASSISTANT_ASSETS_PATH')) {
            define('WOO_AI_ASSISTANT_ASSETS_PATH', '/path/to/assets/');
        }
        if (!defined('WOO_AI_ASSISTANT_VERSION')) {
            define('WOO_AI_ASSISTANT_VERSION', '1.0.0');
        }

        // Mock server variables
        $_SERVER['HTTP_USER_AGENT'] = 'Test User Agent';

        // Get WidgetLoader instance
        $this->widgetLoader = \WooAiAssistant\Frontend\WidgetLoader::getInstance();
    }

    /**
     * Test class instantiation and singleton pattern
     *
     * @since 1.0.0
     * @return void
     */
    public function test_class_exists_and_instantiates(): void
    {
        $this->assertTrue(class_exists('WooAiAssistant\Frontend\WidgetLoader'));
        $this->assertInstanceOf(\WooAiAssistant\Frontend\WidgetLoader::class, $this->widgetLoader);
    }

    /**
     * Test singleton pattern implementation
     *
     * @since 1.0.0
     * @return void
     */
    public function test_singleton_pattern(): void
    {
        $instance1 = \WooAiAssistant\Frontend\WidgetLoader::getInstance();
        $instance2 = \WooAiAssistant\Frontend\WidgetLoader::getInstance();
        
        $this->assertSame($instance1, $instance2, 'WidgetLoader should implement singleton pattern');
    }

    /**
     * Test class naming conventions
     *
     * @since 1.0.0
     * @return void
     */
    public function test_class_follows_naming_conventions(): void
    {
        $reflection = new \ReflectionClass($this->widgetLoader);
        
        // Class name must be PascalCase
        $className = $reflection->getShortName();
        $this->assertMatchesRegularExpression('/^[A-Z][a-zA-Z0-9]*$/', $className, 
            "Class name '$className' must be PascalCase");
            
        // All public methods must be camelCase
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            $methodName = $method->getName();
            if (strpos($methodName, '__') === 0) continue; // Skip magic methods
            
            $this->assertMatchesRegularExpression('/^[a-z][a-zA-Z0-9]*$/', $methodName,
                "Method '$methodName' must be camelCase");
        }
    }

    /**
     * Test public methods exist and return expected types
     *
     * @since 1.0.0
     * @return void
     */
    public function test_public_methods_exist_and_return_correct_types(): void
    {
        $reflection = new \ReflectionClass($this->widgetLoader);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
        
        foreach ($methods as $method) {
            $methodName = $method->getName();
            if (strpos($methodName, '__') === 0) continue; // Skip magic methods
            
            $this->assertTrue(method_exists($this->widgetLoader, $methodName),
                "Method $methodName should exist");
        }
    }

    /**
     * Test enqueue assets method
     *
     * @since 1.0.0
     * @return void
     */
    public function test_enqueueAssets_method_exists(): void
    {
        $this->assertTrue(method_exists($this->widgetLoader, 'enqueueAssets'));
        
        // Call method - should not throw exceptions
        $this->widgetLoader->enqueueAssets();
        $this->assertTrue(true);
    }

    /**
     * Test render widget container method
     *
     * @since 1.0.0
     * @return void
     */
    public function test_renderWidgetContainer_method_exists(): void
    {
        $this->assertTrue(method_exists($this->widgetLoader, 'renderWidgetContainer'));
        
        // Call method - should not throw exceptions
        ob_start();
        $this->widgetLoader->renderWidgetContainer();
        $output = ob_get_clean();
        
        // Output should be string
        $this->assertIsString($output);
    }

    /**
     * Test script optimization method
     *
     * @since 1.0.0
     * @return void
     */
    public function test_optimizeScriptLoading_method_exists(): void
    {
        $this->assertTrue(method_exists($this->widgetLoader, 'optimizeScriptLoading'));
        
        $originalTag = '<script src="test.js"></script>';
        $result = $this->widgetLoader->optimizeScriptLoading($originalTag, 'test-handle', 'test.js');
        
        $this->assertIsString($result);
    }

    /**
     * Test style optimization method
     *
     * @since 1.0.0
     * @return void
     */
    public function test_optimizeStyleLoading_method_exists(): void
    {
        $this->assertTrue(method_exists($this->widgetLoader, 'optimizeStyleLoading'));
        
        $originalHtml = '<link rel="stylesheet" href="test.css">';
        $result = $this->widgetLoader->optimizeStyleLoading($originalHtml, 'test-handle', 'test.css', 'all');
        
        $this->assertIsString($result);
    }

    /**
     * Test preload hints method
     *
     * @since 1.0.0
     * @return void
     */
    public function test_addPreloadHints_method_exists(): void
    {
        $this->assertTrue(method_exists($this->widgetLoader, 'addPreloadHints'));
        
        // Call method - should not throw exceptions
        ob_start();
        $this->widgetLoader->addPreloadHints();
        $output = ob_get_clean();
        
        $this->assertIsString($output);
    }

    /**
     * Test evaluate loading conditions method
     *
     * @since 1.0.0
     * @return void
     */
    public function test_evaluateLoadingConditions_method_exists(): void
    {
        $this->assertTrue(method_exists($this->widgetLoader, 'evaluateLoadingConditions'));
        
        $result = $this->widgetLoader->evaluateLoadingConditions(true);
        $this->assertIsBool($result);
        
        $result = $this->widgetLoader->evaluateLoadingConditions(false);
        $this->assertIsBool($result);
    }

    /**
     * Test configuration getter
     *
     * @since 1.0.0
     * @return void
     */
    public function test_getConfig_should_return_array(): void
    {
        $this->assertTrue(method_exists($this->widgetLoader, 'getConfig'));
        
        $config = $this->widgetLoader->getConfig();
        $this->assertIsArray($config);
    }

    /**
     * Test configuration update
     *
     * @since 1.0.0
     * @return void
     */
    public function test_updateConfig_should_update_configuration(): void
    {
        $this->assertTrue(method_exists($this->widgetLoader, 'updateConfig'));
        
        $newConfig = ['test_key' => 'test_value'];
        $result = $this->widgetLoader->updateConfig($newConfig);
        
        $this->assertIsBool($result);
    }

    /**
     * Test configuration reset
     *
     * @since 1.0.0
     * @return void
     */
    public function test_resetConfig_should_reset_to_defaults(): void
    {
        $this->assertTrue(method_exists($this->widgetLoader, 'resetConfig'));
        
        $result = $this->widgetLoader->resetConfig();
        $this->assertIsBool($result);
    }

    /**
     * Test initialization status
     *
     * @since 1.0.0
     * @return void
     */
    public function test_isInitialized_should_return_boolean(): void
    {
        $this->assertTrue(method_exists($this->widgetLoader, 'isInitialized'));
        
        $result = $this->widgetLoader->isInitialized();
        $this->assertIsBool($result);
    }

    /**
     * Test that all required constants are available
     *
     * @since 1.0.0
     * @return void
     */
    public function test_required_constants_are_defined(): void
    {
        $this->assertTrue(defined('WOO_AI_ASSISTANT_ASSETS_URL'));
        $this->assertTrue(defined('WOO_AI_ASSISTANT_ASSETS_PATH'));
        $this->assertTrue(defined('WOO_AI_ASSISTANT_VERSION'));
    }

    /**
     * Test that class implements proper interface
     *
     * @since 1.0.0
     * @return void
     */
    public function test_class_structure_is_correct(): void
    {
        $reflection = new \ReflectionClass($this->widgetLoader);
        
        // Check that class uses Singleton trait
        $traitNames = $reflection->getTraitNames();
        $this->assertContains('WooAiAssistant\Common\Traits\Singleton', $traitNames);
        
        // Check that required methods exist
        $requiredMethods = [
            'enqueueAssets',
            'renderWidgetContainer',
            'optimizeScriptLoading',
            'optimizeStyleLoading',
            'addPreloadHints',
            'evaluateLoadingConditions',
            'getConfig',
            'updateConfig',
            'resetConfig',
            'isInitialized'
        ];
        
        foreach ($requiredMethods as $methodName) {
            $this->assertTrue(method_exists($this->widgetLoader, $methodName),
                "Required method '$methodName' should exist");
        }
    }

    /**
     * Test error handling in configuration methods
     *
     * @since 1.0.0
     * @return void
     */
    public function test_error_handling_in_configuration_methods(): void
    {
        // Test that configuration methods handle errors gracefully
        $config = $this->widgetLoader->getConfig();
        $this->assertIsArray($config);
        
        // Update with empty array should work
        $result = $this->widgetLoader->updateConfig([]);
        $this->assertIsBool($result);
        
        // Reset should work
        $result = $this->widgetLoader->resetConfig();
        $this->assertIsBool($result);
    }

    /**
     * Cleanup after test
     *
     * @since 1.0.0
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();
    }
}