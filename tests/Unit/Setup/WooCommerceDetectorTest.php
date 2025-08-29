<?php

/**
 * WooCommerceDetector Unit Tests
 *
 * Comprehensive unit tests for the WooCommerceDetector class to ensure
 * proper detection and extraction of WooCommerce settings and configuration.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Unit\Setup
 * @since 1.0.0
 */

namespace WooAiAssistant\Tests\Unit\Setup;

use WooAiAssistant\Setup\WooCommerceDetector;
use WooAiAssistant\Tests\WP_UnitTestCase;

/**
 * Class WooCommerceDetectorTest
 *
 * @since 1.0.0
 */
class WooCommerceDetectorTest extends WP_UnitTestCase
{
    /**
     * WooCommerceDetector instance for testing
     *
     * @var WooCommerceDetector
     */
    private $detector;

    /**
     * Set up test environment
     *
     * @since 1.0.0
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->detector = WooCommerceDetector::getInstance();
    }

    /**
     * Test class existence and instantiation
     *
     * @since 1.0.0
     */
    public function test_class_exists_and_instantiates()
    {
        $this->assertTrue(class_exists('WooAiAssistant\Setup\WooCommerceDetector'));
        $this->assertInstanceOf(WooCommerceDetector::class, $this->detector);
    }

    /**
     * Test singleton pattern implementation
     *
     * @since 1.0.0
     */
    public function test_singleton_pattern()
    {
        $instance1 = WooCommerceDetector::getInstance();
        $instance2 = WooCommerceDetector::getInstance();
        
        $this->assertSame($instance1, $instance2);
    }

    /**
     * Test naming conventions compliance
     *
     * @since 1.0.0
     */
    public function test_class_follows_naming_conventions()
    {
        $reflection = new \ReflectionClass($this->detector);
        
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
     * Test extractAllSettings returns proper structure
     *
     * @since 1.0.0
     */
    public function test_extractAllSettings_returns_proper_structure()
    {
        // Mock WooCommerce as inactive first
        $this->mockWooCommerceActive(false);
        $result = $this->detector->extractAllSettings();
        $this->assertIsArray($result);
        $this->assertEmpty($result); // Should be empty when WooCommerce is inactive
        
        // Mock WooCommerce as active
        $this->mockWooCommerceActive(true);
        $result = $this->detector->extractAllSettings();
        
        $this->assertIsArray($result);
        
        // Check required sections exist
        $expectedSections = ['store_info', 'shipping', 'payment', 'tax', 'policies'];
        foreach ($expectedSections as $section) {
            $this->assertArrayHasKey($section, $result);
            $this->assertIsArray($result[$section]);
            $this->assertArrayHasKey('title', $result[$section]);
            $this->assertArrayHasKey('content', $result[$section]);
            $this->assertArrayHasKey('url', $result[$section]);
            $this->assertArrayHasKey('metadata', $result[$section]);
        }
    }

    /**
     * Test store information extraction
     *
     * @since 1.0.0
     */
    public function test_getStoreInformation_extracts_basic_data()
    {
        // Set up test data
        update_option('blogname', 'Test Store');
        update_option('blogdescription', 'A test store for testing');
        update_option('admin_email', 'admin@teststore.com');
        
        $storeInfo = $this->detector->getStoreInformation();
        
        $this->assertIsArray($storeInfo);
        $this->assertEquals('Test Store', $storeInfo['store_name']);
        $this->assertEquals('A test store for testing', $storeInfo['store_description']);
        $this->assertEquals('admin@teststore.com', $storeInfo['admin_email']);
        $this->assertArrayHasKey('store_url', $storeInfo);
        $this->assertArrayHasKey('base_location', $storeInfo);
        $this->assertArrayHasKey('currency', $storeInfo);
        $this->assertArrayHasKey('base_address', $storeInfo);
    }

    /**
     * Test shipping configuration structure
     *
     * @since 1.0.0
     */
    public function test_getShippingConfiguration_returns_proper_structure()
    {
        $this->mockWooCommerceActive(true);
        $shippingConfig = $this->detector->getShippingConfiguration();
        
        $this->assertIsArray($shippingConfig);
        $this->assertArrayHasKey('enabled', $shippingConfig);
        $this->assertArrayHasKey('zones', $shippingConfig);
        $this->assertIsBool($shippingConfig['enabled']);
        $this->assertIsArray($shippingConfig['zones']);
    }

    /**
     * Test payment configuration structure
     *
     * @since 1.0.0
     */
    public function test_getPaymentConfiguration_returns_proper_structure()
    {
        $this->mockWooCommerceActive(true);
        $paymentConfig = $this->detector->getPaymentConfiguration();
        
        $this->assertIsArray($paymentConfig);
        $this->assertArrayHasKey('available_gateways', $paymentConfig);
        $this->assertArrayHasKey('default_gateway', $paymentConfig);
        $this->assertArrayHasKey('currency', $paymentConfig);
        $this->assertIsArray($paymentConfig['available_gateways']);
    }

    /**
     * Test tax configuration structure
     *
     * @since 1.0.0
     */
    public function test_getTaxConfiguration_returns_proper_structure()
    {
        $this->mockWooCommerceActive(true);
        $taxConfig = $this->detector->getTaxConfiguration();
        
        $this->assertIsArray($taxConfig);
        $this->assertArrayHasKey('enabled', $taxConfig);
        $this->assertArrayHasKey('tax_classes', $taxConfig);
        $this->assertIsBool($taxConfig['enabled']);
        $this->assertIsArray($taxConfig['tax_classes']);
    }

    /**
     * Test store policies structure
     *
     * @since 1.0.0
     */
    public function test_getStorePolicies_returns_proper_structure()
    {
        $policies = $this->detector->getStorePolicies();
        
        $this->assertIsArray($policies);
        $this->assertArrayHasKey('pages', $policies);
        $this->assertArrayHasKey('account_settings', $policies);
        $this->assertIsArray($policies['pages']);
        $this->assertIsArray($policies['account_settings']);
    }

    /**
     * Test getting specific setting type
     *
     * @since 1.0.0
     */
    public function test_getSetting_returns_specific_section()
    {
        $this->mockWooCommerceActive(true);
        
        $storeInfo = $this->detector->getSetting('store_info');
        $this->assertIsArray($storeInfo);
        $this->assertArrayHasKey('title', $storeInfo);
        $this->assertArrayHasKey('content', $storeInfo);
        
        $shipping = $this->detector->getSetting('shipping');
        $this->assertIsArray($shipping);
        $this->assertArrayHasKey('title', $shipping);
        
        $nonexistent = $this->detector->getSetting('nonexistent');
        $this->assertNull($nonexistent);
    }

    /**
     * Test configuration status check
     *
     * @since 1.0.0
     */
    public function test_getConfigurationStatus_returns_proper_structure()
    {
        $status = $this->detector->getConfigurationStatus();
        
        $this->assertIsArray($status);
        $this->assertArrayHasKey('overall_status', $status);
        $this->assertArrayHasKey('checks', $status);
        $this->assertArrayHasKey('recommendations', $status);
        
        $this->assertIsString($status['overall_status']);
        $this->assertIsArray($status['checks']);
        $this->assertIsArray($status['recommendations']);
        
        // Status should be one of the expected values
        $validStatuses = ['complete', 'basic', 'incomplete'];
        $this->assertContains($status['overall_status'], $validStatuses);
    }

    /**
     * Test configuration status with minimal setup
     *
     * @since 1.0.0
     */
    public function test_getConfigurationStatus_with_minimal_setup()
    {
        // Clear store name to simulate incomplete setup
        update_option('blogname', '');
        
        $status = $this->detector->getConfigurationStatus();
        
        $this->assertEquals('incomplete', $status['overall_status']);
        $this->assertArrayHasKey('store_name', $status['checks']);
        $this->assertEquals('missing', $status['checks']['store_name']);
        $this->assertNotEmpty($status['recommendations']);
    }

    /**
     * Test configuration status with complete setup
     *
     * @since 1.0.0
     */
    public function test_getConfigurationStatus_with_complete_setup()
    {
        // Set up basic store information
        update_option('blogname', 'Complete Test Store');
        update_option('woocommerce_store_address', '123 Test Street');
        update_option('woocommerce_store_city', 'Test City');
        
        $this->mockWooCommerceActive(true);
        
        $status = $this->detector->getConfigurationStatus();
        
        // Should not be incomplete
        $this->assertNotEquals('incomplete', $status['overall_status']);
        $this->assertArrayHasKey('store_name', $status['checks']);
        $this->assertEquals('configured', $status['checks']['store_name']);
        $this->assertArrayHasKey('address', $status['checks']);
        $this->assertEquals('configured', $status['checks']['address']);
    }

    /**
     * Test detection statistics
     *
     * @since 1.0.0
     */
    public function test_getDetectionStatistics_returns_proper_structure()
    {
        $stats = $this->detector->getDetectionStatistics();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('store_configured', $stats);
        $this->assertArrayHasKey('shipping_zones_count', $stats);
        $this->assertArrayHasKey('payment_methods_count', $stats);
        $this->assertArrayHasKey('tax_enabled', $stats);
        $this->assertArrayHasKey('policy_pages_count', $stats);
        $this->assertArrayHasKey('last_detection', $stats);
        
        $this->assertIsBool($stats['store_configured']);
        $this->assertIsInt($stats['shipping_zones_count']);
        $this->assertIsInt($stats['payment_methods_count']);
        $this->assertIsBool($stats['tax_enabled']);
        $this->assertIsInt($stats['policy_pages_count']);
        $this->assertIsString($stats['last_detection']);
    }

    /**
     * Test formatted store information output
     *
     * @since 1.0.0
     */
    public function test_formatStoreInformation_produces_readable_output()
    {
        // Set up test data
        update_option('blogname', 'Test Store');
        update_option('blogdescription', 'A great test store');
        
        $reflection = new \ReflectionClass($this->detector);
        
        // Extract store info first
        $extractMethod = $reflection->getMethod('extractStoreInformation');
        $extractMethod->setAccessible(true);
        $extractMethod->invoke($this->detector);
        
        // Get formatted output
        $formatMethod = $reflection->getMethod('formatStoreInformation');
        $formatMethod->setAccessible(true);
        $result = $formatMethod->invoke($this->detector);
        
        $this->assertIsString($result);
        $this->assertStringContainsString('Test Store', $result);
        $this->assertStringContainsString('A great test store', $result);
    }

    /**
     * Test formatted shipping information output
     *
     * @since 1.0.0
     */
    public function test_formatShippingInformation_handles_disabled_shipping()
    {
        $reflection = new \ReflectionClass($this->detector);
        
        // Set up disabled shipping
        $shippingProperty = $reflection->getProperty('shippingConfig');
        $shippingProperty->setAccessible(true);
        $shippingProperty->setValue($this->detector, ['enabled' => false]);
        
        $formatMethod = $reflection->getMethod('formatShippingInformation');
        $formatMethod->setAccessible(true);
        $result = $formatMethod->invoke($this->detector);
        
        $this->assertIsString($result);
        $this->assertStringContainsString('disabled', $result);
    }

    /**
     * Test formatted payment information output
     *
     * @since 1.0.0
     */
    public function test_formatPaymentInformation_handles_no_gateways()
    {
        $reflection = new \ReflectionClass($this->detector);
        
        // Set up no payment gateways
        $paymentProperty = $reflection->getProperty('paymentConfig');
        $paymentProperty->setAccessible(true);
        $paymentProperty->setValue($this->detector, ['available_gateways' => []]);
        
        $formatMethod = $reflection->getMethod('formatPaymentInformation');
        $formatMethod->setAccessible(true);
        $result = $formatMethod->invoke($this->detector);
        
        $this->assertIsString($result);
        $this->assertStringContainsString('No payment methods', $result);
    }

    /**
     * Test formatted tax information output
     *
     * @since 1.0.0
     */
    public function test_formatTaxInformation_handles_disabled_tax()
    {
        $reflection = new \ReflectionClass($this->detector);
        
        // Set up disabled tax
        $taxProperty = $reflection->getProperty('taxConfig');
        $taxProperty->setAccessible(true);
        $taxProperty->setValue($this->detector, ['enabled' => false]);
        
        $formatMethod = $reflection->getMethod('formatTaxInformation');
        $formatMethod->setAccessible(true);
        $result = $formatMethod->invoke($this->detector);
        
        $this->assertIsString($result);
        $this->assertStringContainsString('disabled', $result);
    }

    /**
     * Test formatted store policies output
     *
     * @since 1.0.0
     */
    public function test_formatStorePolicies_handles_no_policies()
    {
        $reflection = new \ReflectionClass($this->detector);
        
        // Set up no policies
        $policiesProperty = $reflection->getProperty('policies');
        $policiesProperty->setAccessible(true);
        $policiesProperty->setValue($this->detector, ['pages' => [], 'account_settings' => []]);
        
        $formatMethod = $reflection->getMethod('formatStorePolicies');
        $formatMethod->setAccessible(true);
        $result = $formatMethod->invoke($this->detector);
        
        $this->assertIsString($result);
        $this->assertStringContainsString('No store policies', $result);
    }

    /**
     * Test error handling in extraction methods
     *
     * @since 1.0.0
     */
    public function test_extraction_methods_handle_errors_gracefully()
    {
        // Test that methods don't throw exceptions even with missing WooCommerce
        $this->mockWooCommerceActive(false);
        
        // These should not throw exceptions
        $storeInfo = $this->detector->getStoreInformation();
        $this->assertIsArray($storeInfo);
        
        $shippingConfig = $this->detector->getShippingConfiguration();
        $this->assertIsArray($shippingConfig);
        
        $paymentConfig = $this->detector->getPaymentConfiguration();
        $this->assertIsArray($paymentConfig);
        
        $taxConfig = $this->detector->getTaxConfiguration();
        $this->assertIsArray($taxConfig);
        
        $policies = $this->detector->getStorePolicies();
        $this->assertIsArray($policies);
    }

    /**
     * Test private properties are properly initialized
     *
     * @since 1.0.0
     */
    public function test_private_properties_are_properly_initialized()
    {
        $reflection = new \ReflectionClass($this->detector);
        
        $expectedProperties = [
            'storeInfo',
            'shippingConfig',
            'paymentConfig',
            'taxConfig',
            'policies'
        ];
        
        foreach ($expectedProperties as $propertyName) {
            $this->assertTrue($reflection->hasProperty($propertyName));
            $property = $reflection->getProperty($propertyName);
            $this->assertTrue($property->isPrivate());
        }
    }

    /**
     * Test method visibility
     *
     * @since 1.0.0
     */
    public function test_method_visibility_is_correct()
    {
        $reflection = new \ReflectionClass($this->detector);
        
        // Public methods
        $publicMethods = [
            'extractAllSettings',
            'getSetting',
            'getStoreInformation',
            'getShippingConfiguration',
            'getPaymentConfiguration',
            'getTaxConfiguration',
            'getStorePolicies',
            'getConfigurationStatus',
            'getDetectionStatistics'
        ];
        
        foreach ($publicMethods as $methodName) {
            $this->assertTrue($reflection->hasMethod($methodName));
            $method = $reflection->getMethod($methodName);
            $this->assertTrue($method->isPublic());
        }
        
        // Private methods (formatting methods)
        $privateMethods = [
            'extractStoreInformation',
            'extractShippingConfiguration',
            'extractPaymentConfiguration',
            'extractTaxConfiguration',
            'extractStorePolicies',
            'formatStoreInformation',
            'formatShippingInformation',
            'formatPaymentInformation',
            'formatTaxInformation',
            'formatStorePolicies'
        ];
        
        foreach ($privateMethods as $methodName) {
            $this->assertTrue($reflection->hasMethod($methodName));
            $method = $reflection->getMethod($methodName);
            $this->assertTrue($method->isPrivate());
        }
    }

    /**
     * Mock WooCommerce active status
     *
     * @param bool $active Whether WooCommerce should be considered active
     */
    private function mockWooCommerceActive(bool $active): void
    {
        static $functions_defined = false;
        
        if ($active) {
            if (!defined('WC_VERSION')) {
                define('WC_VERSION', '7.0.0');
            }
            
            if (!$functions_defined) {
                if (!function_exists('wc_shipping_enabled')) {
                    function wc_shipping_enabled() { return true; }
                }
                if (!function_exists('wc_tax_enabled')) {
                    function wc_tax_enabled() { return false; }
                }
                if (!function_exists('wc_prices_include_tax')) {
                    function wc_prices_include_tax() { return false; }
                }
                if (!function_exists('get_woocommerce_currency')) {
                    function get_woocommerce_currency() { return 'USD'; }
                }
                if (!function_exists('get_woocommerce_currency_symbol')) {
                    function get_woocommerce_currency_symbol() { return '$'; }
                }
                if (!function_exists('wc_terms_and_conditions_page_id')) {
                    function wc_terms_and_conditions_page_id() { return 0; }
                }
                if (!function_exists('wc_privacy_policy_page_id')) {
                    function wc_privacy_policy_page_id() { return 0; }
                }
                $functions_defined = true;
            }
        }
    }

    /**
     * Tear down test environment
     *
     * @since 1.0.0
     */
    public function tearDown(): void
    {
        // Clean up options
        delete_option('blogname');
        delete_option('blogdescription');
        delete_option('admin_email');
        delete_option('woocommerce_store_address');
        delete_option('woocommerce_store_city');
        
        parent::tearDown();
    }
}