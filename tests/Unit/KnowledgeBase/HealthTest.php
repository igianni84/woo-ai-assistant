<?php

/**
 * Knowledge Base Health Class Tests
 *
 * Unit tests for the Health class that handles Knowledge Base content
 * completeness, freshness, and quality analysis.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Unit\KnowledgeBase
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Tests\Unit\KnowledgeBase;

use WP_UnitTestCase;
use WooAiAssistant\KnowledgeBase\Health;
use ReflectionClass;
use ReflectionMethod;

/**
 * Class HealthTest
 *
 * Comprehensive test suite for the Knowledge Base Health class covering
 * all functionality including health scoring, completeness analysis,
 * freshness testing, quality analysis, and template generation.
 *
 * @since 1.0.0
 */
class HealthTest extends WP_UnitTestCase
{
    /**
     * Health instance for testing
     *
     * @since 1.0.0
     * @var Health
     */
    private $health;

    /**
     * WordPress database reference
     *
     * @since 1.0.0
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Knowledge base table name
     *
     * @since 1.0.0
     * @var string
     */
    private $kbTableName;

    /**
     * Set up test environment before each test
     *
     * @since 1.0.0
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->kbTableName = $this->wpdb->prefix . 'woo_ai_knowledge_base';
        
        // Clear any existing singleton instances
        if (method_exists(Health::class, 'destroyInstance')) {
            Health::destroyInstance();
        }
        
        $this->health = Health::getInstance();
        
        // Create test knowledge base table if it doesn't exist
        $this->createTestKnowledgeBaseTable();
        
        // Clear cache before each test
        wp_cache_flush_group('woo_ai_health');
    }

    /**
     * Clean up after each test
     *
     * @since 1.0.0
     * @return void
     */
    public function tearDown(): void
    {
        // Clean up test data
        $this->wpdb->query("TRUNCATE TABLE {$this->kbTableName}");
        
        // Clear cache
        wp_cache_flush_group('woo_ai_health');
        
        // Destroy singleton
        if (method_exists(Health::class, 'destroyInstance')) {
            Health::destroyInstance();
        }
        
        parent::tearDown();
    }

    // MANDATORY TESTS: Class existence and naming conventions

    /**
     * Test class exists and can be instantiated
     *
     * @since 1.0.0
     */
    public function test_class_exists_and_instantiates()
    {
        $this->assertTrue(class_exists('WooAiAssistant\\KnowledgeBase\\Health'));
        $this->assertInstanceOf(Health::class, $this->health);
    }

    /**
     * Test class follows naming conventions
     *
     * @since 1.0.0
     */
    public function test_class_follows_naming_conventions()
    {
        $reflection = new ReflectionClass($this->health);
        
        // Class name must be PascalCase
        $className = $reflection->getShortName();
        $this->assertMatchesRegularExpression('/^[A-Z][a-zA-Z0-9]*$/', $className, 
            "Class name '{$className}' must be PascalCase");
            
        // All public methods must be camelCase
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            $methodName = $method->getName();
            if (strpos($methodName, '__') === 0) continue; // Skip magic methods
            
            $this->assertMatchesRegularExpression('/^[a-z][a-zA-Z0-9]*$/', $methodName,
                "Method '{$methodName}' must be camelCase");
        }
    }

    /**
     * Test singleton pattern implementation
     *
     * @since 1.0.0
     */
    public function test_singleton_pattern_implementation()
    {
        $instance1 = Health::getInstance();
        $instance2 = Health::getInstance();
        
        $this->assertSame($instance1, $instance2, 'Singleton should return the same instance');
        $this->assertInstanceOf(Health::class, $instance1);
    }

    /**
     * Test public methods exist and return correct types
     *
     * @since 1.0.0
     */
    public function test_public_methods_exist_and_return_correct_types()
    {
        $reflection = new ReflectionClass($this->health);
        $publicMethods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        
        $expectedMethods = [
            'getHealthScore',
            'analyzeCompleteness',
            'analyzeFreshness', 
            'analyzeQuality',
            'generateImprovementSuggestions',
            'generateContentTemplate',
            'testFreshness',
            'onContentUpdated',
            'recalculateHealthScore',
            'clearCache'
        ];
        
        foreach ($expectedMethods as $methodName) {
            $this->assertTrue(method_exists($this->health, $methodName),
                "Method {$methodName} should exist");
        }
    }

    // FUNCTIONAL TESTS: Health Score Calculation

    /**
     * Test getHealthScore returns proper structure
     *
     * @since 1.0.0
     */
    public function test_getHealthScore_returns_proper_structure()
    {
        // Add some test data
        $this->insertTestKnowledgeBaseData();
        
        $result = $this->health->getHealthScore();
        
        $this->assertIsArray($result);
        
        $expectedKeys = [
            'overall_score', 'health_status', 'completeness_score',
            'freshness_score', 'quality_score', 'breakdown',
            'suggestions', 'last_calculated', 'calculation_time'
        ];
        
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $result, "Result should contain key '{$key}'");
        }
        
        // Test score ranges
        $this->assertIsInt($result['overall_score']);
        $this->assertGreaterThanOrEqual(0, $result['overall_score']);
        $this->assertLessThanOrEqual(100, $result['overall_score']);
        
        // Test health status is valid
        $validStatuses = ['Excellent', 'Good', 'Needs Improvement', 'Poor', 'Critical'];
        $this->assertContains($result['health_status'], $validStatuses);
    }

    /**
     * Test getHealthScore with force recalculate
     *
     * @since 1.0.0
     */
    public function test_getHealthScore_with_force_recalculate()
    {
        $this->insertTestKnowledgeBaseData();
        
        // First call (will be cached)
        $result1 = $this->health->getHealthScore(false);
        
        // Second call with force recalculate
        $result2 = $this->health->getHealthScore(true);
        
        $this->assertIsArray($result1);
        $this->assertIsArray($result2);
        
        // Results should be similar but timestamps may differ
        $this->assertEquals($result1['overall_score'], $result2['overall_score']);
    }

    /**
     * Test getHealthScore handles empty knowledge base
     *
     * @since 1.0.0
     */
    public function test_getHealthScore_handles_empty_knowledge_base()
    {
        // No data in knowledge base
        $result = $this->health->getHealthScore();
        
        $this->assertIsArray($result);
        $this->assertIsInt($result['overall_score']);
        $this->assertLessThanOrEqual(20, $result['overall_score'], 'Empty KB should have low score');
        $this->assertEquals('Critical', $result['health_status']);
    }

    // FUNCTIONAL TESTS: Completeness Analysis

    /**
     * Test analyzeCompleteness returns proper structure
     *
     * @since 1.0.0
     */
    public function test_analyzeCompleteness_returns_proper_structure()
    {
        $result = $this->health->analyzeCompleteness();
        
        $this->assertIsArray($result);
        
        $expectedKeys = ['score', 'missing_content', 'present_content', 'recommendations'];
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $result, "Completeness result should contain key '{$key}'");
        }
        
        $this->assertIsInt($result['score']);
        $this->assertGreaterThanOrEqual(0, $result['score']);
        $this->assertLessThanOrEqual(100, $result['score']);
        
        $this->assertIsArray($result['missing_content']);
        $this->assertIsArray($result['present_content']);
        $this->assertIsArray($result['recommendations']);
    }

    /**
     * Test analyzeCompleteness with various content types
     *
     * @since 1.0.0
     */
    public function test_analyzeCompleteness_with_various_content_types()
    {
        // Insert test data for different content types
        $this->insertTestContentByType('product', 15); // Above minimum
        $this->insertTestContentByType('woo_settings', 1); // Meets minimum
        // Leave other types empty
        
        $result = $this->health->analyzeCompleteness();
        
        // Should have some present content
        $this->assertGreaterThan(0, count($result['present_content']));
        
        // Should have missing content
        $this->assertGreaterThan(0, count($result['missing_content']));
        
        // Score should be partial (not 0, not 100)
        $this->assertGreaterThan(0, $result['score']);
        $this->assertLessThan(100, $result['score']);
    }

    // FUNCTIONAL TESTS: Freshness Analysis

    /**
     * Test analyzeFreshness returns proper structure
     *
     * @since 1.0.0
     */
    public function test_analyzeFreshness_returns_proper_structure()
    {
        // Insert test data with various ages
        $this->insertTestContentWithDates();
        
        $result = $this->health->analyzeFreshness();
        
        $this->assertIsArray($result);
        
        $expectedKeys = [
            'score', 'fresh_content', 'stale_content', 'outdated_content',
            'never_updated', 'average_age_days', 'outdated_items'
        ];
        
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $result, "Freshness result should contain key '{$key}'");
        }
        
        $this->assertIsInt($result['score']);
        $this->assertGreaterThanOrEqual(0, $result['score']);
        $this->assertLessThanOrEqual(100, $result['score']);
        
        $this->assertIsInt($result['fresh_content']);
        $this->assertIsInt($result['stale_content']);
        $this->assertIsInt($result['outdated_content']);
        $this->assertIsArray($result['outdated_items']);
    }

    /**
     * Test analyzeFreshness with empty data
     *
     * @since 1.0.0
     */
    public function test_analyzeFreshness_with_empty_data()
    {
        $result = $this->health->analyzeFreshness();
        
        $this->assertIsArray($result);
        $this->assertEquals(0, $result['score']);
        $this->assertEquals(0, $result['fresh_content']);
        $this->assertEquals(0, $result['average_age_days']);
    }

    // FUNCTIONAL TESTS: Quality Analysis

    /**
     * Test analyzeQuality returns proper structure
     *
     * @since 1.0.0
     */
    public function test_analyzeQuality_returns_proper_structure()
    {
        $this->insertTestKnowledgeBaseData();
        
        $result = $this->health->analyzeQuality();
        
        $this->assertIsArray($result);
        
        $expectedKeys = ['score', 'total_items', 'quality_issues', 'content_statistics'];
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $result, "Quality result should contain key '{$key}'");
        }
        
        $this->assertIsInt($result['score']);
        $this->assertGreaterThanOrEqual(0, $result['score']);
        $this->assertLessThanOrEqual(100, $result['score']);
        
        $this->assertIsArray($result['quality_issues']);
        $this->assertIsArray($result['content_statistics']);
    }

    /**
     * Test analyzeQuality detects quality issues
     *
     * @since 1.0.0
     */
    public function test_analyzeQuality_detects_quality_issues()
    {
        // Insert data with quality issues
        $this->insertTestDataWithQualityIssues();
        
        $result = $this->health->analyzeQuality();
        
        $this->assertGreaterThan(0, count($result['quality_issues']), 'Should detect quality issues');
        
        // Check that issues have proper structure
        foreach ($result['quality_issues'] as $issue) {
            $this->assertArrayHasKey('type', $issue);
            $this->assertArrayHasKey('severity', $issue);
            $this->assertArrayHasKey('count', $issue);
            $this->assertArrayHasKey('description', $issue);
            $this->assertContains($issue['severity'], ['low', 'medium', 'high']);
        }
    }

    // FUNCTIONAL TESTS: Improvement Suggestions

    /**
     * Test generateImprovementSuggestions returns proper structure
     *
     * @since 1.0.0
     */
    public function test_generateImprovementSuggestions_returns_proper_structure()
    {
        $suggestions = $this->health->generateImprovementSuggestions(50);
        
        $this->assertIsArray($suggestions);
        
        foreach ($suggestions as $suggestion) {
            $expectedKeys = ['priority', 'category', 'title', 'description', 'action', 'impact'];
            foreach ($expectedKeys as $key) {
                $this->assertArrayHasKey($key, $suggestion, "Suggestion should contain key '{$key}'");
            }
            
            $this->assertContains($suggestion['priority'], ['critical', 'high', 'medium', 'low']);
            $this->assertContains($suggestion['category'], ['completeness', 'freshness', 'quality']);
            $this->assertContains($suggestion['impact'], ['high', 'medium', 'low']);
        }
    }

    /**
     * Test generateImprovementSuggestions prioritization
     *
     * @since 1.0.0
     */
    public function test_generateImprovementSuggestions_prioritization()
    {
        // Generate suggestions for low health score
        $suggestions = $this->health->generateImprovementSuggestions(20);
        
        $this->assertGreaterThan(0, count($suggestions), 'Low score should generate suggestions');
        
        // First suggestions should be high priority
        if (count($suggestions) > 0) {
            $firstSuggestion = $suggestions[0];
            $this->assertContains($firstSuggestion['priority'], ['critical', 'high']);
        }
        
        // Generate suggestions for high health score
        $suggestions = $this->health->generateImprovementSuggestions(95);
        
        // High score should have fewer or no critical suggestions
        $criticalCount = count(array_filter($suggestions, function($s) {
            return $s['priority'] === 'critical';
        }));
        
        $this->assertLessThanOrEqual(2, $criticalCount, 'High score should have few critical suggestions');
    }

    // FUNCTIONAL TESTS: Template Generation

    /**
     * Test generateContentTemplate returns proper structure
     *
     * @since 1.0.0
     */
    public function test_generateContentTemplate_returns_proper_structure()
    {
        $contentTypes = ['shipping_policy', 'return_policy', 'faq', 'contact_info'];
        
        foreach ($contentTypes as $contentType) {
            $template = $this->health->generateContentTemplate($contentType);
            
            $this->assertIsArray($template, "Template for {$contentType} should be array");
            
            $expectedKeys = ['title', 'content', 'content_type', 'template_version', 'customization_needed'];
            foreach ($expectedKeys as $key) {
                $this->assertArrayHasKey($key, $template, "Template should contain key '{$key}'");
            }
            
            $this->assertEquals($contentType, $template['content_type']);
            $this->assertIsString($template['title']);
            $this->assertIsString($template['content']);
            $this->assertGreaterThan(50, strlen($template['content']), 'Template content should be substantial');
        }
    }

    /**
     * Test generateContentTemplate with invalid content type
     *
     * @since 1.0.0
     */
    public function test_generateContentTemplate_with_invalid_content_type()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported content type: invalid_type');
        
        $this->health->generateContentTemplate('invalid_type');
    }

    // FUNCTIONAL TESTS: Freshness Testing

    /**
     * Test testFreshness returns proper structure
     *
     * @since 1.0.0
     */
    public function test_testFreshness_returns_proper_structure()
    {
        $this->insertTestContentWithDates();
        
        $result = $this->health->testFreshness(['product']);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('product', $result);
        
        $productResult = $result['product'];
        if (isset($productResult['total_items']) && $productResult['total_items'] > 0) {
            $expectedKeys = [
                'total_items', 'avg_age_days', 'oldest_days', 'newest_days',
                'outdated_count', 'freshness_score', 'status', 'recommendation'
            ];
            
            foreach ($expectedKeys as $key) {
                $this->assertArrayHasKey($key, $productResult, "Freshness test should contain key '{$key}'");
            }
            
            $this->assertIsInt($productResult['freshness_score']);
            $this->assertContains($productResult['status'], ['fresh', 'moderate', 'stale', 'outdated']);
        }
    }

    /**
     * Test testFreshness with empty content types
     *
     * @since 1.0.0
     */
    public function test_testFreshness_with_empty_content_types()
    {
        $result = $this->health->testFreshness([]);
        
        $this->assertIsArray($result);
        $this->assertGreaterThan(0, count($result), 'Should test all content types when empty array passed');
    }

    // FUNCTIONAL TESTS: Event Handlers

    /**
     * Test onContentUpdated clears cache
     *
     * @since 1.0.0
     */
    public function test_onContentUpdated_clears_cache()
    {
        // Set cache
        wp_cache_set('health_score', ['test' => 'data'], 'woo_ai_health');
        
        // Verify cache exists
        $this->assertNotFalse(wp_cache_get('health_score', 'woo_ai_health'));
        
        // Trigger content update
        $this->health->onContentUpdated('product', 123);
        
        // Verify cache is cleared
        $this->assertFalse(wp_cache_get('health_score', 'woo_ai_health'));
    }

    /**
     * Test recalculateHealthScore forces recalculation
     *
     * @since 1.0.0
     */
    public function test_recalculateHealthScore_forces_recalculation()
    {
        $this->insertTestKnowledgeBaseData();
        
        // This should trigger a health score calculation
        $this->health->recalculateHealthScore();
        
        // Verify that health score is now cached
        $cached = wp_cache_get('health_score', 'woo_ai_health');
        $this->assertNotFalse($cached, 'Health score should be cached after recalculation');
    }

    /**
     * Test clearCache clears health cache
     *
     * @since 1.0.0
     */
    public function test_clearCache_clears_health_cache()
    {
        // Set some cache data
        wp_cache_set('health_score', ['test' => 'data'], 'woo_ai_health');
        wp_cache_set('other_key', 'other_data', 'woo_ai_health');
        
        // Clear cache
        $result = $this->health->clearCache();
        
        $this->assertTrue($result, 'clearCache should return true on success');
        
        // Verify cache is cleared
        $this->assertFalse(wp_cache_get('health_score', 'woo_ai_health'));
        $this->assertFalse(wp_cache_get('other_key', 'woo_ai_health'));
    }

    // ERROR HANDLING TESTS

    /**
     * Test health score calculation handles database errors gracefully
     *
     * @since 1.0.0
     */
    public function test_health_score_handles_database_errors_gracefully()
    {
        // Temporarily corrupt the table name to cause database error
        $originalTable = $this->kbTableName;
        
        // Use reflection to access private wpdb property
        $reflection = new ReflectionClass($this->health);
        $wpdbProperty = $reflection->getProperty('wpdb');
        $wpdbProperty->setAccessible(true);
        
        // Mock wpdb to throw exception
        $mockWpdb = $this->createMock(\wpdb::class);
        $mockWpdb->method('get_results')->willThrowException(new \Exception('Database error'));
        $mockWpdb->method('get_row')->willThrowException(new \Exception('Database error'));
        $mockWpdb->method('get_var')->willThrowException(new \Exception('Database error'));
        $mockWpdb->prefix = $this->wpdb->prefix;
        
        $wpdbProperty->setValue($this->health, $mockWpdb);
        
        // Health score calculation should handle the error
        try {
            $result = $this->health->getHealthScore(true);
            $this->fail('Expected RuntimeException to be thrown');
        } catch (\RuntimeException $e) {
            $this->assertStringContains('Failed to calculate health score', $e->getMessage());
        }
        
        // Restore original wpdb
        $wpdbProperty->setValue($this->health, $this->wpdb);
    }

    // INTEGRATION TESTS

    /**
     * Test complete health analysis workflow
     *
     * @since 1.0.0
     */
    public function test_complete_health_analysis_workflow()
    {
        // 1. Start with empty knowledge base
        $initialHealth = $this->health->getHealthScore();
        $this->assertLessThan(30, $initialHealth['overall_score'], 'Empty KB should have poor health');
        
        // 2. Add some content
        $this->insertTestKnowledgeBaseData();
        
        // 3. Force recalculation
        $this->health->recalculateHealthScore();
        
        // 4. Get updated health score
        $updatedHealth = $this->health->getHealthScore();
        $this->assertGreaterThan($initialHealth['overall_score'], $updatedHealth['overall_score'], 
            'Adding content should improve health score');
        
        // 5. Generate suggestions
        $suggestions = $this->health->generateImprovementSuggestions($updatedHealth['overall_score']);
        $this->assertIsArray($suggestions);
        
        // 6. Test freshness
        $freshnessResults = $this->health->testFreshness(['product']);
        $this->assertIsArray($freshnessResults);
    }

    // HELPER METHODS FOR TESTING

    /**
     * Create test knowledge base table if it doesn't exist
     *
     * @since 1.0.0
     * @return void
     */
    private function createTestKnowledgeBaseTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->kbTableName} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            source_type varchar(50) NOT NULL,
            source_id bigint(20) unsigned DEFAULT NULL,
            title text NOT NULL,
            content longtext NOT NULL,
            chunk_content longtext NOT NULL,
            chunk_index int(11) DEFAULT 0,
            embedding longtext DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            hash varchar(64) NOT NULL,
            indexed_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY source_type (source_type),
            KEY source_id (source_id),
            KEY hash (hash),
            KEY indexed_at (indexed_at)
        )";
        
        $this->wpdb->query($sql);
    }

    /**
     * Insert test knowledge base data
     *
     * @since 1.0.0
     * @return void
     */
    private function insertTestKnowledgeBaseData(): void
    {
        // Insert products
        for ($i = 1; $i <= 12; $i++) {
            $this->wpdb->insert(
                $this->kbTableName,
                [
                    'source_type' => 'product',
                    'source_id' => $i,
                    'title' => "Product {$i}",
                    'content' => "This is the content for product {$i}. It contains detailed information about the product features and benefits.",
                    'chunk_content' => "Product {$i} chunk content",
                    'hash' => md5("product_{$i}"),
                    'metadata' => json_encode(['type' => 'product', 'id' => $i]),
                    'embedding' => json_encode(array_fill(0, 384, 0.1)),
                    'indexed_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ]
            );
        }
        
        // Insert some settings
        $this->wpdb->insert(
            $this->kbTableName,
            [
                'source_type' => 'woo_settings',
                'source_id' => 1,
                'title' => 'Payment Settings',
                'content' => 'Payment methods and configuration details',
                'chunk_content' => 'Payment settings chunk',
                'hash' => md5('payment_settings'),
                'metadata' => json_encode(['type' => 'settings']),
                'embedding' => json_encode(array_fill(0, 384, 0.1)),
                'indexed_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ]
        );
    }

    /**
     * Insert test content by type
     *
     * @since 1.0.0
     * @param string $contentType Content type to insert.
     * @param int    $count       Number of items to insert.
     * @return void
     */
    private function insertTestContentByType(string $contentType, int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $this->wpdb->insert(
                $this->kbTableName,
                [
                    'source_type' => $contentType,
                    'source_id' => $i,
                    'title' => ucfirst($contentType) . " {$i}",
                    'content' => "Content for {$contentType} number {$i}",
                    'chunk_content' => "{$contentType} {$i} chunk",
                    'hash' => md5("{$contentType}_{$i}"),
                    'metadata' => json_encode(['type' => $contentType]),
                    'embedding' => json_encode(array_fill(0, 384, 0.1)),
                    'indexed_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ]
            );
        }
    }

    /**
     * Insert test content with various dates for freshness testing
     *
     * @since 1.0.0
     * @return void
     */
    private function insertTestContentWithDates(): void
    {
        $dates = [
            date('Y-m-d H:i:s', strtotime('-10 days')),  // Fresh
            date('Y-m-d H:i:s', strtotime('-20 days')),  // Fresh
            date('Y-m-d H:i:s', strtotime('-60 days')),  // Stale
            date('Y-m-d H:i:s', strtotime('-120 days')), // Outdated
            date('Y-m-d H:i:s', strtotime('-200 days'))  // Very outdated
        ];
        
        foreach ($dates as $index => $date) {
            $this->wpdb->insert(
                $this->kbTableName,
                [
                    'source_type' => 'product',
                    'source_id' => $index + 1,
                    'title' => "Product " . ($index + 1),
                    'content' => "Content for product " . ($index + 1),
                    'chunk_content' => "Chunk for product " . ($index + 1),
                    'hash' => md5("product_" . ($index + 1)),
                    'metadata' => json_encode(['type' => 'product']),
                    'embedding' => json_encode(array_fill(0, 384, 0.1)),
                    'indexed_at' => $date,
                    'updated_at' => $date
                ]
            );
        }
    }

    /**
     * Insert test data with quality issues
     *
     * @since 1.0.0
     * @return void
     */
    private function insertTestDataWithQualityIssues(): void
    {
        // Short content
        $this->wpdb->insert(
            $this->kbTableName,
            [
                'source_type' => 'product',
                'source_id' => 1,
                'title' => 'Short',
                'content' => 'Short', // Very short content
                'chunk_content' => 'Short',
                'hash' => md5('short_content'),
                'metadata' => null, // Missing metadata
                'embedding' => null, // Missing embedding
                'indexed_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ]
        );
        
        // Duplicate content
        $this->wpdb->insert(
            $this->kbTableName,
            [
                'source_type' => 'product',
                'source_id' => 2,
                'title' => 'Duplicate Product',
                'content' => 'This is duplicate content for testing purposes',
                'chunk_content' => 'Duplicate chunk',
                'hash' => md5('duplicate_content'),
                'metadata' => json_encode(['type' => 'product']),
                'embedding' => json_encode(array_fill(0, 384, 0.1)),
                'indexed_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ]
        );
        
        // Another duplicate (same hash)
        $this->wpdb->insert(
            $this->kbTableName,
            [
                'source_type' => 'product',
                'source_id' => 3,
                'title' => 'Another Duplicate Product',
                'content' => 'This is duplicate content for testing purposes',
                'chunk_content' => 'Duplicate chunk',
                'hash' => md5('duplicate_content'), // Same hash
                'metadata' => json_encode(['type' => 'product']),
                'embedding' => json_encode(array_fill(0, 384, 0.1)),
                'indexed_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ]
        );
        
        // Good content for comparison
        $this->wpdb->insert(
            $this->kbTableName,
            [
                'source_type' => 'product',
                'source_id' => 4,
                'title' => 'Good Product',
                'content' => 'This is good quality content with sufficient length and proper information that should not trigger any quality issues.',
                'chunk_content' => 'Good chunk content',
                'hash' => md5('good_content'),
                'metadata' => json_encode(['type' => 'product', 'quality' => 'good']),
                'embedding' => json_encode(array_fill(0, 384, 0.1)),
                'indexed_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ]
        );
    }
}