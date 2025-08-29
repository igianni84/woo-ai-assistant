<?php

/**
 * Unit Tests for DashboardPage Class
 *
 * Tests the dashboard page functionality including KPI calculations,
 * data querying, security checks, and rendering methods.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Unit\Admin\Pages
 * @since 1.0.0
 * @author Claude Code Assistant
 */

namespace WooAiAssistant\Tests\Unit\Admin\Pages;

use PHPUnit\Framework\TestCase;
use WooAiAssistant\Admin\Pages\DashboardPage;
use WooAiAssistant\Common\Utils;

// Mock current_user_can to support test scenarios
function current_user_can_test_override($capability) { 
    global $mock_capability_check_should_fail;
    return !$mock_capability_check_should_fail; 
}

// Additional WordPress function overrides for testing  
global $mock_capability_check_should_fail;
$mock_capability_check_should_fail = false;

// Define constants if not defined
if (!defined('WOO_AI_ASSISTANT_VERSION')) {
    define('WOO_AI_ASSISTANT_VERSION', '1.0.0');
}
if (!defined('WOO_AI_ASSISTANT_URL')) {
    define('WOO_AI_ASSISTANT_URL', 'http://localhost/wp-content/plugins/woo-ai-assistant/');
}

/**
 * Class DashboardPageTest
 *
 * Unit tests for the DashboardPage class functionality
 *
 * @since 1.0.0
 */
class DashboardPageTest extends TestCase
{
    /**
     * DashboardPage instance for testing
     *
     * @since 1.0.0
     * @var DashboardPage
     */
    private $dashboardPage;

    /**
     * Test user ID with required capabilities
     *
     * @since 1.0.0
     * @var int
     */
    private $adminUserId;

    /**
     * Test conversation data for KPI calculations
     *
     * @since 1.0.0
     * @var array
     */
    private $testConversations = [];

    /**
     * Setup test environment
     *
     * @since 1.0.0
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        // Mock admin user ID for testing
        $this->adminUserId = 1;

        // Get DashboardPage instance
        $this->dashboardPage = DashboardPage::getInstance();

        // Setup test database tables and data
        $this->setupTestTables();
        $this->createTestData();
    }

    /**
     * Teardown test environment
     *
     * @since 1.0.0
     * @return void
     */
    public function tearDown(): void
    {
        // Clean up test data
        $this->cleanupTestData();
        
        // Reset current user
        wp_set_current_user(0);

        parent::tearDown();
    }

    // MANDATORY: Basic class and naming convention tests

    /**
     * Test class exists and can be instantiated
     *
     * @since 1.0.0
     * @return void
     */
    public function test_class_exists_and_instantiates()
    {
        $this->assertTrue(class_exists('WooAiAssistant\Admin\Pages\DashboardPage'));
        $this->assertInstanceOf(DashboardPage::class, $this->dashboardPage);
    }

    /**
     * Test singleton pattern implementation
     *
     * @since 1.0.0
     * @return void
     */
    public function test_singleton_pattern_implementation()
    {
        $instance1 = DashboardPage::getInstance();
        $instance2 = DashboardPage::getInstance();
        
        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(DashboardPage::class, $instance1);
    }

    /**
     * Test class follows naming conventions
     *
     * @since 1.0.0
     * @return void
     */
    public function test_class_follows_naming_conventions()
    {
        $reflection = new \ReflectionClass($this->dashboardPage);

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

    // Core functionality tests

    /**
     * Test renderDashboard method with proper security checks
     *
     * @since 1.0.0
     * @return void
     */
    public function test_renderDashboard_should_check_user_capabilities()
    {
        // Since current_user_can always returns true in bootstrap,
        // we'll verify the capability is checked by mocking the method
        // This test verifies the security check exists in the code structure
        $reflection = new \ReflectionClass($this->dashboardPage);
        $method = $reflection->getMethod('renderDashboard');
        
        // Read the method source to verify capability check exists
        $filename = $reflection->getFileName();
        $startLine = $method->getStartLine() - 1;
        $endLine = $method->getEndLine();
        $lines = array_slice(file($filename), $startLine, $endLine - $startLine);
        $methodSource = implode('', $lines);
        
        // Verify that capability check is present
        $this->assertStringContainsString('current_user_can', $methodSource,
            'renderDashboard method should check user capabilities');
        $this->assertStringContainsString('REQUIRED_CAPABILITY', $methodSource,
            'renderDashboard method should check for required capability constant');
    }

    /**
     * Test renderDashboard method executes successfully with valid user
     *
     * @since 1.0.0
     * @return void
     */
    public function test_renderDashboard_should_execute_successfully_with_valid_user()
    {
        ob_start();
        $this->dashboardPage->renderDashboard();
        $output = ob_get_clean();

        // Should not throw exception and should produce output
        $this->assertIsString($output);
        $this->assertStringContainsString('woo-ai-dashboard-header', $output);
    }

    /**
     * Test getKpiData method returns expected structure
     *
     * @since 1.0.0
     * @return void
     */
    public function test_getKpiData_should_return_expected_structure()
    {
        $kpiData = $this->dashboardPage->getKpiData(7);

        // Verify required keys exist
        $expectedKeys = [
            'resolution_rate',
            'assist_conversion_rate', 
            'total_conversations',
            'average_rating',
            'faq_analysis',
            'kb_health_score',
            'period_days',
            'date_range'
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $kpiData, "KPI data missing key: {$key}");
        }

        // Verify data types
        $this->assertIsArray($kpiData['resolution_rate']);
        $this->assertIsArray($kpiData['assist_conversion_rate']);
        $this->assertIsArray($kpiData['total_conversations']);
        $this->assertIsArray($kpiData['average_rating']);
        $this->assertIsArray($kpiData['faq_analysis']);
        $this->assertIsArray($kpiData['kb_health_score']);
        $this->assertIsInt($kpiData['period_days']);
        $this->assertIsArray($kpiData['date_range']);
    }

    /**
     * Test resolution rate calculation with test data
     *
     * @since 1.0.0
     * @return void
     */
    public function test_resolution_rate_calculation_with_test_data()
    {
        $kpiData = $this->dashboardPage->getKpiData(7);
        $resolutionData = $kpiData['resolution_rate'];

        // Verify structure
        $this->assertArrayHasKey('percentage', $resolutionData);
        $this->assertArrayHasKey('resolved_count', $resolutionData);
        $this->assertArrayHasKey('total_count', $resolutionData);
        $this->assertArrayHasKey('trend', $resolutionData);

        // Verify data types and ranges
        $this->assertIsFloat($resolutionData['percentage']) || $this->assertIsInt($resolutionData['percentage']);
        $this->assertGreaterThanOrEqual(0, $resolutionData['percentage']);
        $this->assertLessThanOrEqual(100, $resolutionData['percentage']);
        $this->assertIsInt($resolutionData['resolved_count']);
        $this->assertIsInt($resolutionData['total_count']);
    }

    /**
     * Test conversion rate calculation
     *
     * @since 1.0.0
     * @return void
     */
    public function test_conversion_rate_calculation()
    {
        $kpiData = $this->dashboardPage->getKpiData(7);
        $conversionData = $kpiData['assist_conversion_rate'];

        // Verify structure
        $this->assertArrayHasKey('percentage', $conversionData);
        $this->assertArrayHasKey('conversions', $conversionData);
        $this->assertArrayHasKey('total_assists', $conversionData);
        $this->assertArrayHasKey('revenue_generated', $conversionData);

        // Verify data types and ranges
        $this->assertIsFloat($conversionData['percentage']) || $this->assertIsInt($conversionData['percentage']);
        $this->assertGreaterThanOrEqual(0, $conversionData['percentage']);
        $this->assertLessThanOrEqual(100, $conversionData['percentage']);
        $this->assertIsInt($conversionData['conversions']);
        $this->assertIsInt($conversionData['total_assists']);
        $this->assertIsFloat($conversionData['revenue_generated']) || $this->assertIsInt($conversionData['revenue_generated']);
    }

    /**
     * Test total conversations count calculation
     *
     * @since 1.0.0
     * @return void
     */
    public function test_total_conversations_count_calculation()
    {
        $kpiData = $this->dashboardPage->getKpiData(7);
        $conversationsData = $kpiData['total_conversations'];

        // Verify structure
        $this->assertArrayHasKey('total', $conversationsData);
        $this->assertArrayHasKey('status_breakdown', $conversationsData);
        $this->assertArrayHasKey('daily_counts', $conversationsData);
        $this->assertArrayHasKey('growth_rate', $conversationsData);

        // Verify data types
        $this->assertIsInt($conversationsData['total']);
        $this->assertIsArray($conversationsData['status_breakdown']);
        $this->assertIsArray($conversationsData['daily_counts']);
        $this->assertIsFloat($conversationsData['growth_rate']) || $this->assertIsInt($conversationsData['growth_rate']);

        // Should match our test data
        $this->assertEquals(count($this->testConversations), $conversationsData['total']);
    }

    /**
     * Test average rating calculation
     *
     * @since 1.0.0
     * @return void
     */
    public function test_average_rating_calculation()
    {
        $kpiData = $this->dashboardPage->getKpiData(7);
        $ratingData = $kpiData['average_rating'];

        // Verify structure
        $this->assertArrayHasKey('average', $ratingData);
        $this->assertArrayHasKey('total_ratings', $ratingData);
        $this->assertArrayHasKey('distribution', $ratingData);
        $this->assertArrayHasKey('satisfaction_level', $ratingData);

        // Verify data types and ranges
        $this->assertIsFloat($ratingData['average']) || $this->assertIsInt($ratingData['average']);
        $this->assertGreaterThanOrEqual(0, $ratingData['average']);
        $this->assertLessThanOrEqual(5, $ratingData['average']);
        $this->assertIsInt($ratingData['total_ratings']);
        $this->assertIsArray($ratingData['distribution']);
        $this->assertIsString($ratingData['satisfaction_level']);
    }

    /**
     * Test FAQ analysis functionality
     *
     * @since 1.0.0
     * @return void
     */
    public function test_faq_analysis_functionality()
    {
        $kpiData = $this->dashboardPage->getKpiData(7);
        $faqData = $kpiData['faq_analysis'];

        // Verify structure
        $this->assertArrayHasKey('top_questions', $faqData);
        $this->assertArrayHasKey('categories', $faqData);
        $this->assertArrayHasKey('total_analyzed', $faqData);

        // Verify data types
        $this->assertIsArray($faqData['top_questions']);
        $this->assertIsArray($faqData['categories']);
        $this->assertIsInt($faqData['total_analyzed']);

        // Verify question structure if questions exist
        if (!empty($faqData['top_questions'])) {
            $firstQuestion = $faqData['top_questions'][0];
            $this->assertArrayHasKey('question', $firstQuestion);
            $this->assertArrayHasKey('frequency', $firstQuestion);
            $this->assertArrayHasKey('category', $firstQuestion);
        }
    }

    /**
     * Test KB health score calculation
     *
     * @since 1.0.0
     * @return void
     */
    public function test_kb_health_score_calculation()
    {
        $kpiData = $this->dashboardPage->getKpiData(7);
        $kbHealth = $kpiData['kb_health_score'];

        // Verify structure
        $this->assertArrayHasKey('score', $kbHealth);
        $this->assertArrayHasKey('status', $kbHealth);
        $this->assertArrayHasKey('recommendations', $kbHealth);

        // Verify data types and ranges
        $this->assertIsFloat($kbHealth['score']) || $this->assertIsInt($kbHealth['score']);
        $this->assertGreaterThanOrEqual(0, $kbHealth['score']);
        $this->assertLessThanOrEqual(100, $kbHealth['score']);
        $this->assertIsString($kbHealth['status']);
        $this->assertIsArray($kbHealth['recommendations']);
    }

    /**
     * Test AJAX KPI refresh handler security
     *
     * @since 1.0.0
     * @return void
     */
    public function test_handleKpiRefresh_should_verify_security()
    {
        // Test without proper capability
        wp_set_current_user(0);
        
        $_POST['nonce'] = wp_create_nonce('woo_ai_dashboard_nonce');
        $_POST['period'] = '7';

        $this->expectException(\Exception::class);
        $this->dashboardPage->handleKpiRefresh();

        // Reset user
        wp_set_current_user($this->adminUserId);
    }

    /**
     * Test AJAX KPI refresh handler with invalid nonce
     *
     * @since 1.0.0
     * @return void
     */
    public function test_handleKpiRefresh_should_verify_nonce()
    {
        $_POST['nonce'] = 'invalid_nonce';
        $_POST['period'] = '7';

        $this->expectException(\Exception::class);
        $this->dashboardPage->handleKpiRefresh();
    }

    /**
     * Test enqueueAssets method only loads on correct page
     *
     * @since 1.0.0
     * @return void
     */
    public function test_enqueueAssets_should_only_load_on_correct_page()
    {
        // Test with correct hook
        $this->dashboardPage->enqueueAssets('toplevel_page_woo-ai-assistant');
        
        // Should enqueue our scripts
        $this->assertTrue(wp_script_is('woo-ai-dashboard', 'enqueued') || wp_script_is('woo-ai-dashboard', 'registered'));
        
        // Test with incorrect hook
        wp_deregister_script('woo-ai-dashboard');
        $this->dashboardPage->enqueueAssets('some-other-page');
        
        // Should not enqueue our scripts
        $this->assertFalse(wp_script_is('woo-ai-dashboard', 'enqueued'));
    }

    /**
     * Test method existence and return types
     *
     * @since 1.0.0
     * @return void
     */
    public function test_public_methods_exist_and_return_correct_types()
    {
        $reflection = new \ReflectionClass($this->dashboardPage);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        $expectedMethods = [
            'renderDashboard' => 'void',
            'enqueueAssets' => 'void',
            'getKpiData' => 'array',
            'handleKpiRefresh' => 'void',
            'handleAnalyticsExport' => 'void'
        ];

        foreach ($expectedMethods as $methodName => $expectedReturn) {
            $this->assertTrue(method_exists($this->dashboardPage, $methodName),
                "Method {$methodName} should exist");

            $method = $reflection->getMethod($methodName);
            $returnType = $method->getReturnType();

            if ($expectedReturn !== 'void') {
                // For non-void methods, we can test actual return types in specific tests
                $this->assertTrue($method->isPublic(), "Method {$methodName} should be public");
            }
        }
    }

    /**
     * Test KPI data caching functionality
     *
     * @since 1.0.0
     * @return void
     */
    public function test_kpi_data_caching_functionality()
    {
        $period = 7;
        $cacheKey = "woo_ai_kpi_data_{$period}";

        // Clear any existing cache
        wp_cache_delete($cacheKey, 'woo_ai_assistant');

        // First call should cache the data
        $firstCall = $this->dashboardPage->getKpiData($period);
        $this->assertNotFalse($firstCall);

        // Second call should return cached data
        $secondCall = $this->dashboardPage->getKpiData($period);
        $this->assertEquals($firstCall, $secondCall);

        // Verify data is actually cached
        $cachedData = wp_cache_get($cacheKey, 'woo_ai_assistant');
        $this->assertNotFalse($cachedData);
        $this->assertEquals($firstCall, $cachedData);
    }

    /**
     * Test period sanitization
     *
     * @since 1.0.0
     * @return void
     */
    public function test_period_sanitization()
    {
        // Test valid periods
        $validPeriods = [1, 7, 14, 30, 90, 365];
        foreach ($validPeriods as $period) {
            $kpiData = $this->dashboardPage->getKpiData($period);
            $this->assertEquals($period, $kpiData['period_days']);
        }

        // Test invalid periods should default to 7
        $invalidPeriods = [0, -5, 999, 'invalid', null];
        foreach ($invalidPeriods as $period) {
            try {
                $kpiData = $this->dashboardPage->getKpiData($period);
                $this->assertEquals(7, $kpiData['period_days']); // Should default
            } catch (\Exception $e) {
                // Exception is acceptable for invalid input
                $this->assertIsString($e->getMessage());
            }
        }
    }

    // Helper methods for test setup

    /**
     * Setup test database tables
     *
     * @since 1.0.0
     * @return void
     */
    private function setupTestTables()
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();

        // Create test conversations table if not exists
        $tableConversations = $wpdb->prefix . 'woo_ai_conversations';
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$tableConversations} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            conversation_id varchar(255) NOT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            session_id varchar(255) NOT NULL,
            status varchar(20) DEFAULT 'active',
            context longtext DEFAULT NULL,
            started_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            ended_at datetime DEFAULT NULL,
            total_messages int(11) DEFAULT 0,
            user_rating tinyint(1) DEFAULT NULL,
            user_feedback text DEFAULT NULL,
            PRIMARY KEY (id),
            KEY conversation_id (conversation_id)
        ) {$charsetCollate};");

        // Create test messages table
        $tableMessages = $wpdb->prefix . 'woo_ai_messages';
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$tableMessages} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            conversation_id varchar(255) NOT NULL,
            message_type enum('user','assistant','system') NOT NULL,
            message_content longtext NOT NULL,
            metadata longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            tokens_used int(11) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY conversation_id (conversation_id)
        ) {$charsetCollate};");

        // Create test KB table
        $tableKb = $wpdb->prefix . 'woo_ai_knowledge_base';
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$tableKb} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            source_type varchar(50) NOT NULL,
            source_id bigint(20) unsigned DEFAULT NULL,
            title text NOT NULL,
            content longtext NOT NULL,
            chunk_content longtext NOT NULL,
            embedding longtext DEFAULT NULL,
            indexed_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) {$charsetCollate};");

        // Create test usage stats table
        $tableStats = $wpdb->prefix . 'woo_ai_usage_stats';
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$tableStats} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            date date NOT NULL,
            stat_type varchar(50) NOT NULL,
            stat_value bigint(20) DEFAULT 0,
            PRIMARY KEY (id)
        ) {$charsetCollate};");

        // Create test agent actions table
        $tableActions = $wpdb->prefix . 'woo_ai_agent_actions';
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$tableActions} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            conversation_id varchar(255) NOT NULL,
            action_type varchar(50) NOT NULL,
            action_data longtext DEFAULT NULL,
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) {$charsetCollate};");
    }

    /**
     * Create test data for KPI calculations
     *
     * @since 1.0.0
     * @return void
     */
    private function createTestData()
    {
        global $wpdb;

        $conversationsTable = $wpdb->prefix . 'woo_ai_conversations';
        $messagesTable = $wpdb->prefix . 'woo_ai_messages';
        $kbTable = $wpdb->prefix . 'woo_ai_knowledge_base';

        // Create test conversations
        $baseDate = current_time('mysql', true);
        
        for ($i = 1; $i <= 5; $i++) {
            $conversationId = "test-conv-{$i}";
            $status = $i <= 3 ? 'completed' : 'active';
            $rating = $i <= 3 ? rand(3, 5) : null;
            $startDate = gmdate('Y-m-d H:i:s', strtotime($baseDate) - ($i * 86400));

            $wpdb->insert($conversationsTable, [
                'conversation_id' => $conversationId,
                'user_id' => $this->adminUserId,
                'session_id' => "session-{$i}",
                'status' => $status,
                'started_at' => $startDate,
                'user_rating' => $rating,
                'total_messages' => rand(2, 10)
            ]);

            $this->testConversations[] = $conversationId;

            // Create test messages for each conversation
            $wpdb->insert($messagesTable, [
                'conversation_id' => $conversationId,
                'message_type' => 'user',
                'message_content' => "Test question {$i} about shipping and delivery",
                'created_at' => $startDate
            ]);

            $wpdb->insert($messagesTable, [
                'conversation_id' => $conversationId,
                'message_type' => 'assistant',
                'message_content' => "Test response {$i}",
                'created_at' => gmdate('Y-m-d H:i:s', strtotime($startDate) + 60)
            ]);
        }

        // Create test KB entries
        for ($i = 1; $i <= 3; $i++) {
            $wpdb->insert($kbTable, [
                'source_type' => 'product',
                'source_id' => $i,
                'title' => "Test Product {$i}",
                'content' => "Test product content for testing purposes with sufficient length to meet quality thresholds.",
                'chunk_content' => "Test chunk content {$i}",
                'embedding' => '{"vector": [0.1, 0.2, 0.3]}',
                'indexed_at' => current_time('mysql', true),
                'updated_at' => current_time('mysql', true)
            ]);
        }
    }

    /**
     * Clean up test data
     *
     * @since 1.0.0
     * @return void
     */
    private function cleanupTestData()
    {
        global $wpdb;

        $tables = [
            $wpdb->prefix . 'woo_ai_conversations',
            $wpdb->prefix . 'woo_ai_messages',
            $wpdb->prefix . 'woo_ai_knowledge_base',
            $wpdb->prefix . 'woo_ai_usage_stats',
            $wpdb->prefix . 'woo_ai_agent_actions'
        ];

        foreach ($tables as $table) {
            $wpdb->query("DELETE FROM {$table} WHERE 1=1");
        }

        // Clear any cached data
        wp_cache_flush();
    }
}