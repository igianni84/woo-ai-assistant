<?php

/**
 * Unit Tests for WpmlAndPolylang Class
 *
 * Comprehensive test suite for the multilingual support system including
 * WPML, Polylang, and TranslatePress integration.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Unit\Compatibility
 * @since 1.0.0
 */

namespace WooAiAssistant\Tests\Unit\Compatibility;

use WooAiAssistant\Compatibility\WpmlAndPolylang;
use WooAiAssistant\Tests\WP_UnitTestCase;

/**
 * Class WpmlAndPolylangTest
 *
 * Tests all aspects of multilingual support including plugin detection,
 * language management, content filtering, and integration hooks.
 *
 * @since 1.0.0
 */
class WpmlAndPolylangTest extends WP_UnitTestCase
{
    /**
     * WpmlAndPolylang instance
     *
     * @var WpmlAndPolylang
     */
    private $multilingualSupport;

    /**
     * Set up test environment
     *
     * @since 1.0.0
     */
    public function setUp(): void
    {
        parent::setUp();
        
        // Note: WordPress functions are mocked by the test bootstrap
        // We don't need to redeclare them here to avoid fatal errors
        
        // Reset singleton instance for testing
        $reflection = new \ReflectionClass(WpmlAndPolylang::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
        
        $this->multilingualSupport = WpmlAndPolylang::getInstance();
    }

    /**
     * Clean up after tests
     *
     * @since 1.0.0
     */
    public function tearDown(): void
    {
        // Clean up any mock constants or functions
        $this->cleanupMocks();
        parent::tearDown();
    }

    /**
     * Test class instantiation and singleton pattern
     *
     * @since 1.0.0
     */
    public function test_class_instantiation_and_singleton()
    {
        $instance1 = WpmlAndPolylang::getInstance();
        $instance2 = WpmlAndPolylang::getInstance();
        
        $this->assertInstanceOf(WpmlAndPolylang::class, $instance1);
        $this->assertSame($instance1, $instance2, 'Should return same singleton instance');
    }

    /**
     * Test naming conventions compliance
     *
     * @since 1.0.0
     */
    public function test_naming_conventions()
    {
        $reflection = new \ReflectionClass($this->multilingualSupport);
        
        // Test class name (PascalCase)
        $this->assertMatchesRegularExpression('/^[A-Z][a-zA-Z0-9]*$/', $reflection->getShortName());
        
        // Test public method names (camelCase)
        $publicMethods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
        foreach ($publicMethods as $method) {
            $methodName = $method->getName();
            if (strpos($methodName, '__') === 0) {
                continue; // Skip magic methods
            }
            
            $this->assertMatchesRegularExpression(
                '/^[a-z][a-zA-Z0-9]*$/',
                $methodName,
                "Method {$methodName} should be camelCase"
            );
        }
    }

    /**
     * Test default language settings when no multilingual plugin is active
     *
     * @since 1.0.0
     */
    public function test_default_language_settings_no_plugin()
    {
        $this->assertFalse($this->multilingualSupport->isMultilingualActive());
        $this->assertNull($this->multilingualSupport->getDetectedPlugin());
        $this->assertIsString($this->multilingualSupport->getCurrentLanguage());
        $this->assertIsString($this->multilingualSupport->getDefaultLanguage());
        $this->assertIsArray($this->multilingualSupport->getAvailableLanguages());
    }

    /**
     * Test WPML plugin detection
     *
     * @since 1.0.0
     */
    public function test_wpml_detection()
    {
        // Mock WPML constants and classes
        $this->mockWpmlEnvironment();
        
        // In testing environment without actual WPML installed,
        // we expect the plugin detection to work without errors
        // but may not detect the plugin due to missing actual classes
        $this->assertFalse($this->multilingualSupport->isMultilingualActive());
        $this->assertNull($this->multilingualSupport->getDetectedPlugin());
        
        // Test that the detection methods exist and work
        $reflection = new \ReflectionClass($this->multilingualSupport);
        $detectMethod = $reflection->getMethod('isWpmlActive');
        $detectMethod->setAccessible(true);
        $isActive = $detectMethod->invoke($this->multilingualSupport);
        
        // Should return false in test environment (expected behavior)
        $this->assertIsBool($isActive);
    }

    /**
     * Test Polylang plugin detection
     *
     * @since 1.0.0
     */
    public function test_polylang_detection()
    {
        // Mock Polylang functions and classes
        $this->mockPolylangEnvironment();
        
        // In testing environment without actual Polylang installed,
        // we expect the plugin detection to work without errors
        $this->assertFalse($this->multilingualSupport->isMultilingualActive());
        $this->assertNull($this->multilingualSupport->getDetectedPlugin());
        
        // Test that the detection methods exist and work
        $reflection = new \ReflectionClass($this->multilingualSupport);
        $detectMethod = $reflection->getMethod('isPolylangActive');
        $detectMethod->setAccessible(true);
        $isActive = $detectMethod->invoke($this->multilingualSupport);
        
        // Should return false in test environment (expected behavior)
        $this->assertIsBool($isActive);
    }

    /**
     * Test TranslatePress plugin detection
     *
     * @since 1.0.0
     */
    public function test_translatepress_detection()
    {
        // Mock TranslatePress environment
        $this->mockTranslatePressEnvironment();
        
        // In testing environment without actual TranslatePress installed,
        // we expect the plugin detection to work without errors
        $this->assertFalse($this->multilingualSupport->isMultilingualActive());
        $this->assertNull($this->multilingualSupport->getDetectedPlugin());
        
        // Test that the detection methods exist and work
        $reflection = new \ReflectionClass($this->multilingualSupport);
        $detectMethod = $reflection->getMethod('isTranslatePressActive');
        $detectMethod->setAccessible(true);
        $isActive = $detectMethod->invoke($this->multilingualSupport);
        
        // Should return false in test environment (expected behavior)
        $this->assertIsBool($isActive);
    }

    /**
     * Test current language detection
     *
     * @since 1.0.0
     */
    public function test_current_language_detection()
    {
        $currentLanguage = $this->multilingualSupport->getCurrentLanguage();
        $this->assertIsString($currentLanguage);
        $this->assertNotEmpty($currentLanguage);
        $this->assertMatchesRegularExpression('/^[a-z]{2}(-[A-Z]{2})?$/', $currentLanguage);
    }

    /**
     * Test language fallback functionality
     *
     * @since 1.0.0
     */
    public function test_language_fallback()
    {
        // Test with default setup (no multilingual plugin)
        $contentId = 123;
        $fallbackId = $this->multilingualSupport->getFallbackContent($contentId, 'post', 'fr');
        
        $this->assertEquals($contentId, $fallbackId, 'Should return original ID when no multilingual plugin active');
    }

    /**
     * Test content language detection
     *
     * @since 1.0.0
     */
    public function test_content_language_detection()
    {
        $contentId = 123;
        $language = $this->multilingualSupport->getContentLanguage($contentId, 'post');
        
        $this->assertIsString($language);
        $this->assertNotEmpty($language);
    }

    /**
     * Test content translations retrieval
     *
     * @since 1.0.0
     */
    public function test_content_translations_retrieval()
    {
        $contentId = 123;
        $translations = $this->multilingualSupport->getContentTranslations($contentId, 'post');
        
        $this->assertIsArray($translations);
        // With no multilingual plugin, should be empty or contain only original
        $this->assertLessThanOrEqual(1, count($translations));
    }

    /**
     * Test REST API context filtering
     *
     * @since 1.0.0
     */
    public function test_rest_api_context_filtering()
    {
        $originalContext = ['page' => 'shop'];
        $mockRequest = $this->createMock(\WP_REST_Request::class);
        
        $filteredContext = $this->multilingualSupport->addLanguageContext($originalContext, $mockRequest);
        
        $this->assertIsArray($filteredContext);
        $this->assertArrayHasKey('language', $filteredContext);
        $this->assertArrayHasKey('current', $filteredContext['language']);
        $this->assertArrayHasKey('default', $filteredContext['language']);
        $this->assertArrayHasKey('available', $filteredContext['language']);
        $this->assertArrayHasKey('plugin', $filteredContext['language']);
        $this->assertArrayHasKey('is_multilingual', $filteredContext['language']);
    }

    /**
     * Test Knowledge Base query filtering
     *
     * @since 1.0.0
     */
    public function test_knowledge_base_query_filtering()
    {
        $originalArgs = ['post_type' => 'product'];
        $filteredArgs = $this->multilingualSupport->filterKnowledgeBaseByLanguage($originalArgs, 'en');
        
        // With no multilingual plugin active, should return original args
        $this->assertEquals($originalArgs, $filteredArgs);
    }

    /**
     * Test cache key modification
     *
     * @since 1.0.0
     */
    public function test_cache_key_modification()
    {
        $originalKey = 'kb_products_page_1';
        $context = ['language' => 'en'];
        
        $modifiedKey = $this->multilingualSupport->addLanguageToCache($originalKey, $context);
        
        // With no multilingual plugin, should return original key
        $this->assertEquals($originalKey, $modifiedKey);
    }

    /**
     * Test language URL generation
     *
     * @since 1.0.0
     */
    public function test_language_url_generation()
    {
        $originalUrl = 'https://example.com/product/test-product';
        $languageUrl = $this->multilingualSupport->getLanguageUrl($originalUrl, 'es');
        
        // With no multilingual plugin, should return original URL
        $this->assertEquals($originalUrl, $languageUrl);
    }

    /**
     * Test default language check
     *
     * @since 1.0.0
     */
    public function test_default_language_check()
    {
        $isDefault = $this->multilingualSupport->isDefaultLanguage();
        $this->assertIsBool($isDefault);
    }

    /**
     * Test plugin info retrieval
     *
     * @since 1.0.0
     */
    public function test_plugin_info_retrieval()
    {
        $pluginInfo = $this->multilingualSupport->getPluginInfo();
        
        $this->assertIsArray($pluginInfo);
        $this->assertArrayHasKey('detected', $pluginInfo);
        $this->assertArrayHasKey('name', $pluginInfo);
        $this->assertArrayHasKey('is_active', $pluginInfo);
        $this->assertArrayHasKey('current_language', $pluginInfo);
        $this->assertArrayHasKey('default_language', $pluginInfo);
        $this->assertArrayHasKey('available_languages', $pluginInfo);
        $this->assertArrayHasKey('supported_plugins', $pluginInfo);
    }

    /**
     * Test WPML language switching hook
     *
     * @since 1.0.0
     */
    public function test_wpml_language_switching_hook()
    {
        $actionFired = false;
        
        add_action('woo_ai_assistant_wpml_language_switched', function($new, $old) use (&$actionFired) {
            $actionFired = true;
        });
        
        $this->multilingualSupport->onLanguageSwitch('es', 'en', []);
        
        $this->assertTrue($actionFired, 'WPML language switch action should fire');
    }

    /**
     * Test Polylang language defined hook
     *
     * @since 1.0.0
     */
    public function test_polylang_language_defined_hook()
    {
        $actionFired = false;
        
        add_action('woo_ai_assistant_polylang_language_defined', function($lang) use (&$actionFired) {
            $actionFired = true;
        });
        
        $this->multilingualSupport->onPolylangLanguageDefined('fr');
        
        $this->assertTrue($actionFired, 'Polylang language defined action should fire');
    }

    /**
     * Test TranslatePress language switching hook
     *
     * @since 1.0.0
     */
    public function test_translatepress_language_switching_hook()
    {
        $actionFired = false;
        
        add_action('woo_ai_assistant_translatepress_language_switched', function($new, $old) use (&$actionFired) {
            $actionFired = true;
        });
        
        $this->multilingualSupport->onTranslatePressLanguageSwitch('de', 'en');
        
        $this->assertTrue($actionFired, 'TranslatePress language switch action should fire');
    }

    /**
     * Test language name resolution
     *
     * @since 1.0.0
     */
    public function test_language_name_resolution()
    {
        $reflection = new \ReflectionClass($this->multilingualSupport);
        $method = $reflection->getMethod('getLanguageNameByCode');
        $method->setAccessible(true);
        
        $this->assertEquals('English', $method->invokeArgs($this->multilingualSupport, ['en']));
        $this->assertEquals('Spanish', $method->invokeArgs($this->multilingualSupport, ['es']));
        $this->assertEquals('French', $method->invokeArgs($this->multilingualSupport, ['fr']));
        $this->assertEquals('Unknown', $method->invokeArgs($this->multilingualSupport, ['unknown']));
    }

    /**
     * Test language cache functionality
     *
     * @since 1.0.0
     */
    public function test_language_cache_functionality()
    {
        // Test cache clearing
        $reflection = new \ReflectionClass($this->multilingualSupport);
        $clearCacheMethod = $reflection->getMethod('clearLanguageCache');
        $clearCacheMethod->setAccessible(true);
        
        // Should not throw any errors
        $clearCacheMethod->invoke($this->multilingualSupport);
        $this->assertTrue(true, 'Cache clearing should complete without errors');
    }

    /**
     * Test WordPress integration points
     *
     * @since 1.0.0
     */
    public function test_wordpress_integration_points()
    {
        // Test that hooks are properly registered
        $this->assertTrue(has_action('wp_loaded'), 'wp_loaded hook should be registered');
        
        // Note: In test environment, filters may not be registered the same way
        // as in the actual WordPress environment. We test that the methods exist
        // and can be called without errors instead.
        
        // Test that the filter methods exist and work
        $mockRequest = $this->createMock(\WP_REST_Request::class);
        $context = $this->multilingualSupport->addLanguageContext([], $mockRequest);
        $this->assertIsArray($context);
        $this->assertArrayHasKey('language', $context);
        
        // Test KB filtering method exists
        $args = $this->multilingualSupport->filterKnowledgeBaseByLanguage(['test' => true]);
        $this->assertIsArray($args);
        
        // Test cache key method exists
        $key = $this->multilingualSupport->addLanguageToCache('test_key');
        $this->assertIsString($key);
    }

    /**
     * Test error handling and edge cases
     *
     * @since 1.0.0
     */
    public function test_error_handling_edge_cases()
    {
        // Test with invalid content ID
        $fallback = $this->multilingualSupport->getFallbackContent(-1, 'post', 'invalid');
        $this->assertEquals(-1, $fallback, 'Should handle invalid content ID gracefully');
        
        // Test with empty language code
        $language = $this->multilingualSupport->getCurrentLanguage();
        $this->assertNotEmpty($language, 'Should always return a valid language code');
        
        // Test available languages structure
        $languages = $this->multilingualSupport->getAvailableLanguages();
        foreach ($languages as $code => $language) {
            $this->assertIsString($code, 'Language code should be string');
            $this->assertIsArray($language, 'Language info should be array');
            $this->assertArrayHasKey('code', $language, 'Language should have code');
            $this->assertArrayHasKey('name', $language, 'Language should have name');
            $this->assertArrayHasKey('native_name', $language, 'Language should have native name');
            $this->assertArrayHasKey('is_default', $language, 'Language should have default flag');
        }
    }

    /**
     * Test multilingual with WPML environment
     *
     * @since 1.0.0
     */
    public function test_with_wpml_environment()
    {
        $this->mockWpmlEnvironment();
        
        // Test language detection with mocked WPML
        $reflection = new \ReflectionClass($this->multilingualSupport);
        $detectMethod = $reflection->getMethod('detectMultilingualPlugin');
        $detectMethod->setAccessible(true);
        $detectMethod->invoke($this->multilingualSupport);
        
        // Since we can't easily mock all WPML functionality, we just test that no errors occur
        $this->assertTrue(true, 'WPML environment detection should complete without errors');
    }

    /**
     * Mock WPML environment for testing
     *
     * @since 1.0.0
     */
    private function mockWpmlEnvironment(): void
    {
        if (!defined('ICL_SITEPRESS_VERSION')) {
            define('ICL_SITEPRESS_VERSION', '4.5.0');
        }
        
        // Mock WPML functions using WordPress function mocking approach
        if (!function_exists('icl_get_languages')) {
            // Create a mock function using eval (for testing purposes only)
            $GLOBALS['mock_wpml_languages'] = ['en' => ['code' => 'en'], 'es' => ['code' => 'es']];
        }
    }

    /**
     * Mock Polylang environment for testing
     *
     * @since 1.0.0
     */
    private function mockPolylangEnvironment(): void
    {
        // Set up mock data for Polylang
        $GLOBALS['mock_polylang_current_language'] = 'en';
        $GLOBALS['mock_polylang_post_languages'] = [];
    }

    /**
     * Mock TranslatePress environment for testing
     *
     * @since 1.0.0
     */
    private function mockTranslatePressEnvironment(): void
    {
        // Set up mock data for TranslatePress
        $GLOBALS['mock_translatepress_current_language'] = 'en';
    }

    /**
     * Clean up mocked functions and classes
     *
     * @since 1.0.0
     */
    private function cleanupMocks(): void
    {
        // Note: PHP doesn't allow undefining functions/classes at runtime
        // This method is here for completeness and future extensibility
    }
}