<?php

/**
 * License Manager Test Class
 *
 * Comprehensive unit tests for the LicenseManager class covering all functionality
 * including license validation, plan management, usage tracking, grace periods,
 * and feature enforcement.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Unit\Api
 * @since 1.0.0
 * @author Claude Code Assistant
 */

namespace WooAiAssistant\Tests\Unit\Api;

use WooAiAssistant\Tests\WP_UnitTestCase;
use WooAiAssistant\Api\LicenseManager;
use WooAiAssistant\Api\IntermediateServerClient;
use WP_Error;

/**
 * Class LicenseManagerTest
 *
 * Tests the LicenseManager functionality including license validation,
 * plan management, usage tracking, and feature enforcement.
 *
 * @since 1.0.0
 */
class LicenseManagerTest extends WP_UnitTestCase
{
    /**
     * License Manager instance
     *
     * @since 1.0.0
     * @var LicenseManager
     */
    private LicenseManager $licenseManager;

    /**
     * Mock server client
     *
     * @since 1.0.0
     * @var IntermediateServerClient
     */
    private $mockServerClient;

    /**
     * Setup test environment
     *
     * @since 1.0.0
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        // Clean up options before each test
        delete_option('woo_ai_assistant_license_data');
        delete_option('woo_ai_assistant_usage_data');

        $this->licenseManager = LicenseManager::getInstance();
        $this->mockServerClient = $this->createMock(IntermediateServerClient::class);
    }

    /**
     * Teardown test environment
     *
     * @since 1.0.0
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();
        
        // Clean up options after each test
        delete_option('woo_ai_assistant_license_data');
        delete_option('woo_ai_assistant_usage_data');
        delete_transient('woo_ai_assistant_rate_limits');
    }

    // ===== BASIC CLASS TESTS =====

    /**
     * Test class exists and can be instantiated
     *
     * @since 1.0.0
     * @return void
     */
    public function test_class_exists_and_instantiates(): void
    {
        $this->assertTrue(class_exists('WooAiAssistant\Api\LicenseManager'));
        $this->assertInstanceOf(LicenseManager::class, $this->licenseManager);
    }

    /**
     * Test singleton pattern implementation
     *
     * @since 1.0.0
     * @return void
     */
    public function test_singleton_pattern(): void
    {
        $instance1 = LicenseManager::getInstance();
        $instance2 = LicenseManager::getInstance();
        
        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(LicenseManager::class, $instance1);
    }

    /**
     * Test naming conventions compliance
     *
     * @since 1.0.0
     * @return void
     */
    public function test_naming_conventions(): void
    {
        $reflection = new \ReflectionClass($this->licenseManager);
        
        // Class name should be PascalCase
        $className = $reflection->getShortName();
        $this->assertMatchesRegularExpression('/^[A-Z][a-zA-Z0-9]*$/', $className,
            "Class name '$className' should be PascalCase");
        
        // Public methods should be camelCase
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            $methodName = $method->getName();
            if (strpos($methodName, '__') === 0) continue; // Skip magic methods
            
            $this->assertMatchesRegularExpression('/^[a-z][a-zA-Z0-9]*$/', $methodName,
                "Method '$methodName' should be camelCase");
        }
    }

    // ===== LICENSE VALIDATION TESTS =====

    /**
     * Test Free plan validation always returns valid
     *
     * @since 1.0.0
     * @return void
     */
    public function test_validateLicense_should_return_valid_for_free_plan(): void
    {
        $result = $this->licenseManager->validateLicense();
        
        $this->assertTrue($result['valid']);
        $this->assertEquals(LicenseManager::STATUS_ACTIVE, $result['status']);
        $this->assertEquals(LicenseManager::PLAN_FREE, $result['plan']);
        $this->assertEquals('Free plan is always valid', $result['message']);
    }

    /**
     * Test license validation with valid key
     *
     * @since 1.0.0
     * @return void
     */
    public function test_validateLicense_should_succeed_with_valid_key(): void
    {
        // Set a pro license key
        $this->setLicenseData([
            'plan' => LicenseManager::PLAN_PRO,
            'license_key' => 'valid-license-key',
            'status' => LicenseManager::STATUS_ACTIVE
        ]);

        // Mock successful server response
        $this->mockServerClient->method('sendRequest')
            ->willReturn([
                'valid' => true,
                'status' => LicenseManager::STATUS_ACTIVE,
                'plan' => ['type' => LicenseManager::PLAN_PRO],
                'expires_at' => '2025-12-31 23:59:59'
            ]);

        $result = $this->licenseManager->validateLicense(true);
        
        $this->assertTrue($result['valid']);
        $this->assertEquals(LicenseManager::STATUS_ACTIVE, $result['status']);
    }

    /**
     * Test license validation with server error
     *
     * @since 1.0.0
     * @return void
     */
    public function test_validateLicense_should_handle_server_error(): void
    {
        $this->setLicenseData([
            'plan' => LicenseManager::PLAN_PRO,
            'license_key' => 'invalid-key',
            'status' => LicenseManager::STATUS_ACTIVE
        ]);

        // Mock server error
        $this->mockServerClient->method('sendRequest')
            ->willReturn(new WP_Error('server_error', 'Server unreachable'));

        $result = $this->licenseManager->validateLicense(true);
        
        $this->assertFalse($result['valid']);
        $this->assertTrue($result['error']);
        $this->assertStringContains('Server unreachable', $result['message']);
    }

    /**
     * Test recent validation caching
     *
     * @since 1.0.0
     * @return void
     */
    public function test_validateLicense_should_use_cache_when_recently_validated(): void
    {
        $this->setLicenseData([
            'plan' => LicenseManager::PLAN_PRO,
            'license_key' => 'test-key',
            'status' => LicenseManager::STATUS_ACTIVE,
            'last_validated' => current_time('mysql') // Just validated
        ]);

        $result = $this->licenseManager->validateLicense(false);
        
        $this->assertTrue($result['valid']);
        $this->assertTrue($result['cached']);
    }

    // ===== PLAN MANAGEMENT TESTS =====

    /**
     * Test getting plan configuration for Free plan
     *
     * @since 1.0.0
     * @return void
     */
    public function test_getPlanConfiguration_should_return_free_plan_config(): void
    {
        $config = $this->licenseManager->getPlanConfiguration();
        
        $this->assertEquals('Free Plan', $config['name']);
        $this->assertEquals(0, $config['price']);
        $this->assertEquals(30, $config['conversations_per_month']);
        $this->assertEquals(30, $config['items_indexable']);
        $this->assertEquals('gemini-2.5-flash', $config['ai_model']);
        $this->assertArrayHasKey('features', $config);
    }

    /**
     * Test getting all available plans
     *
     * @since 1.0.0
     * @return void
     */
    public function test_getAvailablePlans_should_return_all_plans(): void
    {
        $plans = $this->licenseManager->getAvailablePlans();
        
        $this->assertArrayHasKey(LicenseManager::PLAN_FREE, $plans);
        $this->assertArrayHasKey(LicenseManager::PLAN_PRO, $plans);
        $this->assertArrayHasKey(LicenseManager::PLAN_UNLIMITED, $plans);
        
        $this->assertEquals('Free Plan', $plans[LicenseManager::PLAN_FREE]['name']);
        $this->assertEquals('Pro Plan', $plans[LicenseManager::PLAN_PRO]['name']);
        $this->assertEquals('Unlimited Plan', $plans[LicenseManager::PLAN_UNLIMITED]['name']);
    }

    // ===== FEATURE ENFORCEMENT TESTS =====

    /**
     * Test Free plan feature restrictions
     *
     * @since 1.0.0
     * @return void
     */
    public function test_isFeatureEnabled_should_restrict_features_for_free_plan(): void
    {
        // Free plan should have basic chat only
        $this->assertTrue($this->licenseManager->isFeatureEnabled(LicenseManager::FEATURE_BASIC_CHAT));
        $this->assertFalse($this->licenseManager->isFeatureEnabled(LicenseManager::FEATURE_ADD_TO_CART));
        $this->assertFalse($this->licenseManager->isFeatureEnabled(LicenseManager::FEATURE_AUTO_COUPON));
        $this->assertFalse($this->licenseManager->isFeatureEnabled(LicenseManager::FEATURE_WHITE_LABEL));
        $this->assertFalse($this->licenseManager->isFeatureEnabled(LicenseManager::FEATURE_ADVANCED_AI));
    }

    /**
     * Test Pro plan feature availability
     *
     * @since 1.0.0
     * @return void
     */
    public function test_isFeatureEnabled_should_enable_pro_features_for_pro_plan(): void
    {
        $this->setLicenseData([
            'plan' => LicenseManager::PLAN_PRO,
            'status' => LicenseManager::STATUS_ACTIVE
        ]);

        // Recreate instance with Pro plan
        $this->licenseManager = LicenseManager::getInstance();
        
        $this->assertTrue($this->licenseManager->isFeatureEnabled(LicenseManager::FEATURE_BASIC_CHAT));
        $this->assertTrue($this->licenseManager->isFeatureEnabled(LicenseManager::FEATURE_PROACTIVE_TRIGGERS));
        $this->assertTrue($this->licenseManager->isFeatureEnabled(LicenseManager::FEATURE_CUSTOM_MESSAGES));
        $this->assertFalse($this->licenseManager->isFeatureEnabled(LicenseManager::FEATURE_ADD_TO_CART));
        $this->assertFalse($this->licenseManager->isFeatureEnabled(LicenseManager::FEATURE_WHITE_LABEL));
    }

    /**
     * Test Unlimited plan feature availability
     *
     * @since 1.0.0
     * @return void
     */
    public function test_isFeatureEnabled_should_enable_all_features_for_unlimited_plan(): void
    {
        $this->setLicenseData([
            'plan' => LicenseManager::PLAN_UNLIMITED,
            'status' => LicenseManager::STATUS_ACTIVE
        ]);

        // Recreate instance with Unlimited plan
        $this->licenseManager = LicenseManager::getInstance();
        
        $this->assertTrue($this->licenseManager->isFeatureEnabled(LicenseManager::FEATURE_BASIC_CHAT));
        $this->assertTrue($this->licenseManager->isFeatureEnabled(LicenseManager::FEATURE_ADD_TO_CART));
        $this->assertTrue($this->licenseManager->isFeatureEnabled(LicenseManager::FEATURE_AUTO_COUPON));
        $this->assertTrue($this->licenseManager->isFeatureEnabled(LicenseManager::FEATURE_WHITE_LABEL));
        $this->assertTrue($this->licenseManager->isFeatureEnabled(LicenseManager::FEATURE_ADVANCED_AI));
    }

    // ===== USAGE TRACKING TESTS =====

    /**
     * Test conversation usage tracking
     *
     * @since 1.0.0
     * @return void
     */
    public function test_trackConversationUsage_should_increment_counter(): void
    {
        $initialStats = $this->licenseManager->getUsageStatistics();
        $this->assertEquals(0, $initialStats['conversations']['used']);
        
        $result = $this->licenseManager->trackConversationUsage(123);
        
        $this->assertTrue($result);
        
        $updatedStats = $this->licenseManager->getUsageStatistics();
        $this->assertEquals(1, $updatedStats['conversations']['used']);
    }

    /**
     * Test conversation usage limit enforcement
     *
     * @since 1.0.0
     * @return void
     */
    public function test_trackConversationUsage_should_enforce_limits(): void
    {
        // Set usage to limit
        $this->setUsageData([
            'conversations_count' => 30 // Free plan limit
        ]);

        // Try to track one more conversation
        $result = $this->licenseManager->trackConversationUsage(124);
        
        $this->assertFalse($result); // Should be blocked
    }

    /**
     * Test indexing usage tracking
     *
     * @since 1.0.0
     * @return void
     */
    public function test_trackIndexingUsage_should_increment_counter(): void
    {
        $initialStats = $this->licenseManager->getUsageStatistics();
        $this->assertEquals(0, $initialStats['items_indexed']['used']);
        
        $result = $this->licenseManager->trackIndexingUsage('product', 5);
        
        $this->assertTrue($result);
        
        $updatedStats = $this->licenseManager->getUsageStatistics();
        $this->assertEquals(5, $updatedStats['items_indexed']['used']);
    }

    /**
     * Test check usage limit for conversations
     *
     * @since 1.0.0
     * @return void
     */
    public function test_checkUsageLimit_should_validate_conversation_limits(): void
    {
        // Within limit
        $this->assertTrue($this->licenseManager->checkUsageLimit('conversations', 1));
        
        // Set to limit
        $this->setUsageData(['conversations_count' => 29]);
        $this->assertTrue($this->licenseManager->checkUsageLimit('conversations', 1));
        
        // Over limit
        $this->setUsageData(['conversations_count' => 30]);
        $this->assertFalse($this->licenseManager->checkUsageLimit('conversations', 1));
    }

    // ===== GRACE PERIOD TESTS =====

    /**
     * Test grace period detection
     *
     * @since 1.0.0
     * @return void
     */
    public function test_isInGracePeriod_should_detect_grace_period_status(): void
    {
        // Not in grace period initially
        $this->assertFalse($this->licenseManager->isInGracePeriod());
        
        // Set in grace period
        $this->setLicenseData([
            'status' => LicenseManager::STATUS_GRACE_PERIOD,
            'grace_period_started' => current_time('mysql')
        ]);
        
        $this->licenseManager = LicenseManager::getInstance();
        $this->assertTrue($this->licenseManager->isInGracePeriod());
    }

    /**
     * Test feature limitations during grace period
     *
     * @since 1.0.0
     * @return void
     */
    public function test_isFeatureEnabled_should_limit_features_during_grace_period(): void
    {
        $this->setLicenseData([
            'plan' => LicenseManager::PLAN_PRO,
            'status' => LicenseManager::STATUS_GRACE_PERIOD,
            'grace_period_started' => current_time('mysql')
        ]);

        $this->licenseManager = LicenseManager::getInstance();
        
        // Only basic features should be available in grace period
        $this->assertTrue($this->licenseManager->isFeatureEnabled(LicenseManager::FEATURE_BASIC_CHAT));
        $this->assertFalse($this->licenseManager->isFeatureEnabled(LicenseManager::FEATURE_ADD_TO_CART));
        $this->assertFalse($this->licenseManager->isFeatureEnabled(LicenseManager::FEATURE_AUTO_COUPON));
    }

    // ===== LICENSE KEY MANAGEMENT TESTS =====

    /**
     * Test setting valid license key
     *
     * @since 1.0.0
     * @return void
     */
    public function test_setLicenseKey_should_validate_and_store_key(): void
    {
        // Mock successful validation
        $this->mockServerClient->method('sendRequest')
            ->willReturn([
                'valid' => true,
                'status' => LicenseManager::STATUS_ACTIVE,
                'plan' => ['type' => LicenseManager::PLAN_PRO]
            ]);

        $result = $this->licenseManager->setLicenseKey('valid-license-key');
        
        $this->assertTrue($result['valid']);
    }

    /**
     * Test setting empty license key
     *
     * @since 1.0.0
     * @return void
     */
    public function test_setLicenseKey_should_reject_empty_key(): void
    {
        $result = $this->licenseManager->setLicenseKey('');
        
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('cannot be empty', $result['message']);
    }

    /**
     * Test clearing license key
     *
     * @since 1.0.0
     * @return void
     */
    public function test_clearLicenseKey_should_revert_to_free_plan(): void
    {
        // Set Pro plan first
        $this->setLicenseData([
            'plan' => LicenseManager::PLAN_PRO,
            'license_key' => 'test-key',
            'status' => LicenseManager::STATUS_ACTIVE
        ]);

        $result = $this->licenseManager->clearLicenseKey();
        
        $this->assertTrue($result);
        
        $status = $this->licenseManager->getLicenseStatus();
        $this->assertEquals(LicenseManager::PLAN_FREE, $status['plan']);
        $this->assertEquals('', $status['license_key']);
    }

    // ===== BRANDING AND AI MODEL TESTS =====

    /**
     * Test branding configuration for different plans
     *
     * @since 1.0.0
     * @return void
     */
    public function test_getBrandingConfig_should_return_correct_branding(): void
    {
        // Free plan - should show branding
        $branding = $this->licenseManager->getBrandingConfig();
        $this->assertTrue($branding['show_branding']);
        $this->assertFalse($branding['white_label']);
        $this->assertEquals('Powered by Woo AI Assistant', $branding['branding_text']);
        
        // Unlimited plan - should be white label
        $this->setLicenseData([
            'plan' => LicenseManager::PLAN_UNLIMITED,
            'status' => LicenseManager::STATUS_ACTIVE
        ]);
        
        $this->licenseManager = LicenseManager::getInstance();
        $branding = $this->licenseManager->getBrandingConfig();
        $this->assertFalse($branding['show_branding']);
        $this->assertTrue($branding['white_label']);
        $this->assertEquals('', $branding['branding_text']);
    }

    /**
     * Test AI model selection based on plan
     *
     * @since 1.0.0
     * @return void
     */
    public function test_getCurrentAiModel_should_return_correct_model_for_plan(): void
    {
        // Free plan - Flash model
        $this->assertEquals('gemini-2.5-flash', $this->licenseManager->getCurrentAiModel());
        
        // Pro plan - Flash model
        $this->setLicenseData([
            'plan' => LicenseManager::PLAN_PRO,
            'status' => LicenseManager::STATUS_ACTIVE
        ]);
        $this->licenseManager = LicenseManager::getInstance();
        $this->assertEquals('gemini-2.5-flash', $this->licenseManager->getCurrentAiModel());
        
        // Unlimited plan - Pro model
        $this->setLicenseData([
            'plan' => LicenseManager::PLAN_UNLIMITED,
            'status' => LicenseManager::STATUS_ACTIVE
        ]);
        $this->licenseManager = LicenseManager::getInstance();
        $this->assertEquals('gemini-2.5-pro', $this->licenseManager->getCurrentAiModel());
    }

    // ===== UPGRADE AND URL TESTS =====

    /**
     * Test upgrade availability detection
     *
     * @since 1.0.0
     * @return void
     */
    public function test_canUpgrade_should_detect_upgrade_availability(): void
    {
        // Free plan can upgrade
        $this->assertTrue($this->licenseManager->canUpgrade());
        
        // Pro plan can upgrade
        $this->setLicenseData(['plan' => LicenseManager::PLAN_PRO]);
        $this->licenseManager = LicenseManager::getInstance();
        $this->assertTrue($this->licenseManager->canUpgrade());
        
        // Unlimited plan cannot upgrade
        $this->setLicenseData(['plan' => LicenseManager::PLAN_UNLIMITED]);
        $this->licenseManager = LicenseManager::getInstance();
        $this->assertFalse($this->licenseManager->canUpgrade());
    }

    /**
     * Test upgrade URL generation
     *
     * @since 1.0.0
     * @return void
     */
    public function test_getUpgradeUrl_should_generate_correct_url(): void
    {
        $url = $this->licenseManager->getUpgradeUrl();
        
        $this->assertStringContainsString('current_plan=free', $url);
        $this->assertStringStartsWith('http', $url);
    }

    // ===== UTILITY METHODS =====

    /**
     * Set license data for testing
     *
     * @since 1.0.0
     * @param array $data License data
     * @return void
     */
    private function setLicenseData(array $data): void
    {
        $defaultData = [
            'plan' => LicenseManager::PLAN_FREE,
            'status' => LicenseManager::STATUS_ACTIVE,
            'license_key' => '',
            'expires_at' => null,
            'last_validated' => null,
            'validation_errors' => [],
            'grace_period_started' => null
        ];
        
        $licenseData = array_merge($defaultData, $data);
        update_option('woo_ai_assistant_license_data', $licenseData);
    }

    /**
     * Set usage data for testing
     *
     * @since 1.0.0
     * @param array $data Usage data
     * @return void
     */
    private function setUsageData(array $data): void
    {
        $currentMonth = date('Y-m');
        $defaultData = [
            'current_month' => $currentMonth,
            'conversations_count' => 0,
            'items_indexed' => 0,
            'monthly_reset_date' => date('Y-m-01'),
            'last_updated' => current_time('mysql'),
            'daily_usage' => [],
            'feature_usage' => []
        ];
        
        $usageData = array_merge($defaultData, $data);
        update_option('woo_ai_assistant_usage_data', $usageData);
    }

    // ===== ERROR HANDLING TESTS =====

    /**
     * Test exception handling in license validation
     *
     * @since 1.0.0
     * @return void
     */
    public function test_validateLicense_should_handle_exceptions_gracefully(): void
    {
        $this->setLicenseData([
            'plan' => LicenseManager::PLAN_PRO,
            'license_key' => 'test-key'
        ]);

        // Mock server client to throw exception
        $this->mockServerClient->method('sendRequest')
            ->will($this->throwException(new \Exception('Network error')));

        $result = $this->licenseManager->validateLicense(true);
        
        $this->assertFalse($result['valid']);
        $this->assertEquals(LicenseManager::STATUS_UNKNOWN, $result['status']);
        $this->assertStringContains('Network error', $result['message']);
    }

    /**
     * Test monthly usage reset functionality
     *
     * @since 1.0.0
     * @return void
     */
    public function test_monthly_usage_should_reset_automatically(): void
    {
        // Set usage data from previous month
        $previousMonth = date('Y-m', strtotime('-1 month'));
        $this->setUsageData([
            'current_month' => $previousMonth,
            'conversations_count' => 25,
            'items_indexed' => 15
        ]);

        // Create new instance which should trigger reset
        $this->licenseManager = LicenseManager::getInstance();
        
        $stats = $this->licenseManager->getUsageStatistics();
        $this->assertEquals(0, $stats['conversations']['used']);
        $this->assertEquals(0, $stats['items_indexed']['used']);
        $this->assertEquals(date('Y-m'), $stats['current_month']);
    }

    // ===== INTEGRATION TESTS =====

    /**
     * Test license status structure
     *
     * @since 1.0.0
     * @return void
     */
    public function test_getLicenseStatus_should_return_complete_status(): void
    {
        $status = $this->licenseManager->getLicenseStatus();
        
        $this->assertArrayHasKey('plan', $status);
        $this->assertArrayHasKey('plan_name', $status);
        $this->assertArrayHasKey('status', $status);
        $this->assertArrayHasKey('license_key', $status);
        $this->assertArrayHasKey('expires_at', $status);
        $this->assertArrayHasKey('features', $status);
        $this->assertArrayHasKey('usage', $status);
        $this->assertArrayHasKey('validation_errors', $status);
    }

    /**
     * Test usage statistics structure
     *
     * @since 1.0.0
     * @return void
     */
    public function test_getUsageStatistics_should_return_complete_stats(): void
    {
        $stats = $this->licenseManager->getUsageStatistics();
        
        $this->assertArrayHasKey('conversations', $stats);
        $this->assertArrayHasKey('items_indexed', $stats);
        $this->assertArrayHasKey('current_month', $stats);
        $this->assertArrayHasKey('reset_date', $stats);
        
        $this->assertArrayHasKey('used', $stats['conversations']);
        $this->assertArrayHasKey('limit', $stats['conversations']);
        $this->assertArrayHasKey('percentage', $stats['conversations']);
    }
}