<?php

/**
 * Tests for DevelopmentConfig Class
 *
 * Unit tests for the development configuration management class.
 * Tests environment detection, API key loading, and development mode features.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Unit\Config
 * @since 1.0.0
 * @author Claude Code Assistant
 */

namespace WooAiAssistant\Tests\Unit\Config;

use WooAiAssistant\Config\DevelopmentConfig;
use WooAiAssistant\Tests\Unit\WooAiBaseTestCase;

/**
 * Class DevelopmentConfigTest
 *
 * Test cases for the DevelopmentConfig class.
 *
 * @since 1.0.0
 */
class DevelopmentConfigTest extends WooAiBaseTestCase
{
    /**
     * DevelopmentConfig instance
     *
     * @var DevelopmentConfig
     */
    private $developmentConfig;

    /**
     * Set up test environment
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->developmentConfig = new DevelopmentConfig();
    }

    /**
     * Test development mode detection
     *
     * @return void
     */
    public function test_isDevelopmentMode_should_return_true_in_test_environment(): void
    {
        $isDevelopmentMode = $this->developmentConfig->isDevelopmentMode();
        $this->assertTrue($isDevelopmentMode, 'Development mode should be active in test environment');
    }

    /**
     * Test license validation bypass in development mode
     *
     * @return void
     */
    public function test_isValidLicense_should_return_true_in_development_mode(): void
    {
        $isValidLicense = $this->developmentConfig->isValidLicense('any-license-key');
        $this->assertTrue($isValidLicense, 'Any license should be valid in development mode');
    }

    /**
     * Test getting development API keys
     *
     * @return void
     */
    public function test_getApiKey_should_return_development_keys(): void
    {
        // Test with environment variable simulation
        $_ENV['OPENROUTER_API_KEY'] = 'test_openrouter_key';

        $apiKey = $this->developmentConfig->getApiKey('openrouter');
        $this->assertEquals('test_openrouter_key', $apiKey, 'Should return development API key');

        // Clean up
        unset($_ENV['OPENROUTER_API_KEY']);
    }

    /**
     * Test development features access
     *
     * @return void
     */
    public function test_hasFeature_should_grant_unlimited_access_in_development(): void
    {
        $hasAdvancedFeatures = $this->developmentConfig->hasFeature('advanced_analytics');
        $this->assertTrue($hasAdvancedFeatures, 'Development mode should grant access to all features');

        $hasProFeatures = $this->developmentConfig->hasFeature('pro_features');
        $this->assertTrue($hasProFeatures, 'Development mode should grant access to pro features');
    }

    /**
     * Test class name follows PascalCase convention
     *
     * @return void
     */
    public function test_class_name_should_follow_pascal_case_convention(): void
    {
        $this->assertClassFollowsPascalCase(DevelopmentConfig::class);
    }

    /**
     * Test method names follow camelCase convention
     *
     * @return void
     */
    public function test_public_methods_should_follow_camel_case_convention(): void
    {
        $publicMethods = ['isDevelopmentMode', 'isValidLicense', 'getApiKey', 'hasFeature'];

        foreach ($publicMethods as $methodName) {
            $this->assertMethodFollowsCamelCase($this->developmentConfig, $methodName);
        }
    }
}
