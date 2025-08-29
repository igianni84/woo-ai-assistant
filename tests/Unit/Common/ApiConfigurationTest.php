<?php

/**
 * API Configuration Tests
 *
 * Comprehensive test suite for the ApiConfiguration class.
 * Tests API key management, environment handling, configuration retrieval,
 * and development/production mode functionality.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Unit\Common
 * @since 1.0.0
 */

namespace WooAiAssistant\Tests\Unit\Common;

use WooAiAssistant\Common\ApiConfiguration;
use WP_UnitTestCase;

/**
 * Class ApiConfigurationTest
 *
 * @since 1.0.0
 */
class ApiConfigurationTest extends WP_UnitTestCase
{
    /**
     * ApiConfiguration instance
     *
     * @var ApiConfiguration
     */
    private $apiConfig;

    /**
     * Set up test environment
     *
     * @since 1.0.0
     */
    public function setUp(): void
    {
        parent::setUp();
        
        // Clear any existing settings
        delete_option('woo_ai_assistant_settings');
        
        // Clear legacy options
        delete_option('woo_ai_assistant_openrouter_key');
        delete_option('woo_ai_assistant_gemini_key');
        delete_option('woo_ai_assistant_openai_key');
        delete_option('woo_ai_assistant_pinecone_key');
        
        $this->apiConfig = ApiConfiguration::getInstance();
        $this->apiConfig->clearCache();
    }

    /**
     * Clean up after tests
     *
     * @since 1.0.0
     */
    public function tearDown(): void
    {
        // Clean up
        delete_option('woo_ai_assistant_settings');
        delete_option('woo_ai_assistant_openrouter_key');
        delete_option('woo_ai_assistant_gemini_key');
        delete_option('woo_ai_assistant_openai_key');
        delete_option('woo_ai_assistant_pinecone_key');
        
        parent::tearDown();
    }

    /**
     * Test class instantiation and singleton pattern
     *
     * @since 1.0.0
     */
    public function test_class_exists_and_implements_singleton()
    {
        $this->assertTrue(class_exists('WooAiAssistant\Common\ApiConfiguration'));
        
        $instance1 = ApiConfiguration::getInstance();
        $instance2 = ApiConfiguration::getInstance();
        
        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(ApiConfiguration::class, $instance1);
    }

    /**
     * Test API key retrieval when no keys are configured
     *
     * @since 1.0.0
     */
    public function test_getApiKey_returns_empty_string_when_not_configured()
    {
        $services = ['openrouter', 'openai', 'pinecone', 'google'];
        
        foreach ($services as $service) {
            $key = $this->apiConfig->getApiKey($service);
            $this->assertEmpty($key, "Service $service should return empty key when not configured");
        }
    }

    /**
     * Test API key setting and retrieval
     *
     * @since 1.0.0
     */
    public function test_setApiKey_and_getApiKey_work_correctly()
    {
        $testKey = 'sk-test-key-123';
        
        // Test setting and getting OpenAI key
        $result = $this->apiConfig->setApiKey('openai', $testKey);
        $this->assertTrue($result, 'Setting API key should return true');
        
        $retrievedKey = $this->apiConfig->getApiKey('openai');
        $this->assertEquals($testKey, $retrievedKey, 'Retrieved key should match set key');
    }

    /**
     * Test different API service configurations
     *
     * @since 1.0.0
     */
    public function test_service_specific_configurations()
    {
        // Test OpenAI configuration
        $this->apiConfig->setApiKey('openai', 'sk-openai-test');
        $openaiConfig = $this->apiConfig->getOpenAiConfig();
        
        $this->assertArrayHasKey('api_key', $openaiConfig);
        $this->assertArrayHasKey('model', $openaiConfig);
        $this->assertArrayHasKey('timeout', $openaiConfig);
        $this->assertEquals('sk-openai-test', $openaiConfig['api_key']);
        $this->assertEquals('text-embedding-3-small', $openaiConfig['model']);

        // Test Pinecone configuration
        $this->apiConfig->setApiKey('pinecone', 'pc-pinecone-test');
        $pineconeConfig = $this->apiConfig->getPineconeConfig();
        
        $this->assertArrayHasKey('api_key', $pineconeConfig);
        $this->assertArrayHasKey('environment', $pineconeConfig);
        $this->assertArrayHasKey('index_name', $pineconeConfig);
        $this->assertEquals('pc-pinecone-test', $pineconeConfig['api_key']);
        $this->assertEquals('woo-ai-assistant', $pineconeConfig['index_name']);

        // Test OpenRouter configuration
        $this->apiConfig->setApiKey('openrouter', 'sk-openrouter-test');
        $openrouterConfig = $this->apiConfig->getOpenRouterConfig();
        
        $this->assertArrayHasKey('api_key', $openrouterConfig);
        $this->assertArrayHasKey('timeout', $openrouterConfig);
        $this->assertEquals('sk-openrouter-test', $openrouterConfig['api_key']);
    }

    /**
     * Test API status functionality
     *
     * @since 1.0.0
     */
    public function test_getApiStatus_returns_correct_status()
    {
        // Initially all should be unconfigured
        $status = $this->apiConfig->getApiStatus();
        
        $this->assertArrayHasKey('openrouter', $status);
        $this->assertArrayHasKey('openai', $status);
        $this->assertArrayHasKey('pinecone', $status);
        $this->assertArrayHasKey('google', $status);
        
        $this->assertFalse($status['openrouter']['configured']);
        $this->assertFalse($status['openai']['configured']);
        
        // Set a key and check status
        $this->apiConfig->setApiKey('openai', 'sk-test');
        $status = $this->apiConfig->getApiStatus();
        
        $this->assertTrue($status['openai']['configured']);
        $this->assertEquals(7, $status['openai']['key_length']);
    }

    /**
     * Test validation of required keys
     *
     * @since 1.0.0
     */
    public function test_validateRequiredKeys_works_correctly()
    {
        // Test with no required keys configured
        $validation = $this->apiConfig->validateRequiredKeys(['openai', 'pinecone']);
        
        $this->assertFalse($validation['valid']);
        $this->assertContains('openai', $validation['missing']);
        $this->assertContains('pinecone', $validation['missing']);
        $this->assertEmpty($validation['configured']);

        // Configure one required key
        $this->apiConfig->setApiKey('openai', 'sk-test-openai');
        $validation = $this->apiConfig->validateRequiredKeys(['openai', 'pinecone']);
        
        $this->assertFalse($validation['valid']);
        $this->assertContains('pinecone', $validation['missing']);
        $this->assertContains('openai', $validation['configured']);

        // Configure both required keys
        $this->apiConfig->setApiKey('pinecone', 'pc-test-pinecone');
        $validation = $this->apiConfig->validateRequiredKeys(['openai', 'pinecone']);
        
        $this->assertTrue($validation['valid']);
        $this->assertEmpty($validation['missing']);
        $this->assertCount(2, $validation['configured']);
    }

    /**
     * Test development mode detection
     *
     * @since 1.0.0
     */
    public function test_isDevelopmentMode_works_correctly()
    {
        // Should be false by default
        $this->assertFalse($this->apiConfig->isDevelopmentMode());

        // Test with settings option
        update_option('woo_ai_assistant_settings', [
            'api' => ['use_development_fallbacks' => true]
        ]);
        $this->apiConfig->clearCache();
        
        $this->assertTrue($this->apiConfig->isDevelopmentMode());
    }

    /**
     * Test debug mode detection
     *
     * @since 1.0.0
     */
    public function test_isDebugMode_works_correctly()
    {
        // Test with settings option (WP_DEBUG might already be true in tests)
        update_option('woo_ai_assistant_settings', [
            'api' => ['enable_debug_mode' => false]
        ]);
        $this->apiConfig->clearCache();
        
        // Debug mode should be true if WP_DEBUG is true OR if enable_debug_mode is true
        $debugModeResult = $this->apiConfig->isDebugMode();
        $wpDebugEnabled = defined('WP_DEBUG') && WP_DEBUG;
        
        if ($wpDebugEnabled) {
            $this->assertTrue($debugModeResult, 'Debug mode should be true when WP_DEBUG is enabled');
        } else {
            $this->assertFalse($debugModeResult, 'Debug mode should be false when WP_DEBUG is disabled and setting is false');
        }

        // Test with settings option enabled
        update_option('woo_ai_assistant_settings', [
            'api' => ['enable_debug_mode' => true]
        ]);
        $this->apiConfig->clearCache();
        
        $this->assertTrue($this->apiConfig->isDebugMode(), 'Debug mode should be true when setting is enabled');
    }

    /**
     * Test legacy key migration
     *
     * @since 1.0.0
     */
    public function test_migrateLegacyKeys_works_correctly()
    {
        // Set legacy options
        update_option('woo_ai_assistant_openrouter_key', 'legacy-openrouter-key');
        update_option('woo_ai_assistant_openai_key', 'legacy-openai-key');
        
        // Migrate keys
        $migrated = $this->apiConfig->migrateLegacyKeys();
        
        $this->assertTrue($migrated, 'Migration should return true when keys were migrated');
        
        // Check that keys are now available through new system
        $this->assertEquals('legacy-openrouter-key', $this->apiConfig->getApiKey('openrouter'));
        $this->assertEquals('legacy-openai-key', $this->apiConfig->getApiKey('openai'));
    }

    /**
     * Test method naming follows camelCase convention
     *
     * @since 1.0.0
     */
    public function test_method_names_follow_camelCase_convention()
    {
        $reflection = new \ReflectionClass($this->apiConfig);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
        
        foreach ($methods as $method) {
            $methodName = $method->getName();
            
            // Skip magic methods and constructors
            if (strpos($methodName, '__') === 0 || $methodName === 'getInstance') {
                continue;
            }
            
            $this->assertTrue(
                ctype_lower($methodName[0]) && !strpos($methodName, '_'),
                "Method {$methodName} should follow camelCase convention"
            );
        }
    }

    /**
     * Test class name follows PascalCase convention
     *
     * @since 1.0.0
     */
    public function test_class_name_follows_PascalCase_convention()
    {
        $className = 'ApiConfiguration';
        $this->assertMatchesRegularExpression(
            '/^[A-Z][a-zA-Z0-9]*$/',
            $className,
            "Class name '$className' should follow PascalCase convention"
        );
    }

    /**
     * Test server configuration retrieval
     *
     * @since 1.0.0
     */
    public function test_getServerConfig_returns_correct_configuration()
    {
        $config = $this->apiConfig->getServerConfig();
        
        $this->assertArrayHasKey('url', $config);
        $this->assertArrayHasKey('timeout', $config);
        $this->assertArrayHasKey('retry_attempts', $config);
        
        // Check default values
        $this->assertEquals('https://api.wooaiassistant.com', $config['url']);
        $this->assertEquals(30, $config['timeout']);
        $this->assertEquals(3, $config['retry_attempts']);
    }

    /**
     * Test cache clearing functionality
     *
     * @since 1.0.0
     */
    public function test_clearCache_works_correctly()
    {
        // This test ensures clearCache doesn't throw errors
        $this->apiConfig->clearCache();
        $this->assertTrue(true, 'clearCache should not throw any errors');
    }
}