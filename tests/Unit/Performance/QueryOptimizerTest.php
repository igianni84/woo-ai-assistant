<?php
/**
 * Query Optimizer Test Class
 *
 * Unit tests for the QueryOptimizer performance class.
 * Tests database query optimization, logging, and performance monitoring.
 *
 * @package WooAiAssistant
 * @subpackage Tests
 * @since 1.0.0
 * @author Claude Code Assistant
 */

namespace WooAiAssistant\Tests\Unit\Performance;

// Include WordPress function mocks
require_once __DIR__ . '/../Chatbot/wp-functions-mock.php';

use WooAiAssistant\Performance\QueryOptimizer;
use WooAiAssistant\Tests\WP_UnitTestCase;

/**
 * Class QueryOptimizerTest
 *
 * @since 1.0.0
 */
class QueryOptimizerTest extends WP_UnitTestCase {

    private $queryOptimizer;
    private $wpdb;

    public function setUp(): void {
        parent::setUp();
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->queryOptimizer = QueryOptimizer::getInstance();
        
        // Create test tables if they don't exist
        $this->createTestTables();
    }

    public function tearDown(): void {
        // Clean up test data
        $this->cleanupTestData();
        parent::tearDown();
    }

    /**
     * Create test database tables
     */
    private function createTestTables(): void {
        // Create test conversation table
        $conversationsTable = $this->wpdb->prefix . 'woo_ai_conversations';
        $this->wpdb->query("
            CREATE TABLE IF NOT EXISTS {$conversationsTable} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL DEFAULT 0,
                message text NOT NULL,
                response text NOT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                status varchar(20) NOT NULL DEFAULT 'active',
                rating tinyint(1) DEFAULT NULL,
                session_id varchar(255) DEFAULT NULL,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Create test knowledge base table
        $kbTable = $this->wpdb->prefix . 'woo_ai_knowledge_base';
        $this->wpdb->query("
            CREATE TABLE IF NOT EXISTS {$kbTable} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                content_type varchar(50) NOT NULL,
                title text NOT NULL,
                content longtext NOT NULL,
                embedding longtext DEFAULT NULL,
                post_id bigint(20) unsigned DEFAULT NULL,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                status varchar(20) NOT NULL DEFAULT 'active',
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    /**
     * Clean up test data
     */
    private function cleanupTestData(): void {
        $conversationsTable = $this->wpdb->prefix . 'woo_ai_conversations';
        $kbTable = $this->wpdb->prefix . 'woo_ai_knowledge_base';
        
        $this->wpdb->query("DELETE FROM {$conversationsTable} WHERE message LIKE '%test_%'");
        $this->wpdb->query("DELETE FROM {$kbTable} WHERE title LIKE '%test_%'");
    }

    /**
     * Test class existence and instantiation
     */
    public function test_class_exists_and_instantiates() {
        $this->assertTrue(class_exists('WooAiAssistant\\Performance\\QueryOptimizer'));
        $this->assertInstanceOf(QueryOptimizer::class, $this->queryOptimizer);
    }

    /**
     * Test class follows naming conventions
     */
    public function test_class_follows_naming_conventions() {
        $reflection = new \ReflectionClass($this->queryOptimizer);
        
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
     * Test optimized query execution
     */
    public function test_executeOptimizedQuery_should_execute_select_query_successfully() {
        // Insert test data
        $userId = 123;
        $message = 'test_message_select';
        $response = 'test_response_select';
        
        $conversationsTable = $this->wpdb->prefix . 'woo_ai_conversations';
        $this->wpdb->insert($conversationsTable, [
            'user_id' => $userId,
            'message' => $message,
            'response' => $response,
            'created_at' => current_time('mysql'),
            'status' => 'active'
        ]);

        // Test optimized select query
        $query = "SELECT * FROM {$conversationsTable} WHERE user_id = %d AND message = %s";
        $params = [$userId, $message];
        
        $results = $this->queryOptimizer->executeOptimizedQuery($query, $params);
        
        $this->assertIsArray($results, 'Query results should be an array');
        $this->assertCount(1, $results, 'Should return one result');
        $this->assertEquals($message, $results[0]->message, 'Message should match');
        $this->assertEquals($response, $results[0]->response, 'Response should match');
    }

    /**
     * Test query with empty query string
     */
    public function test_executeOptimizedQuery_should_throw_exception_for_empty_query() {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Query cannot be empty');
        
        $this->queryOptimizer->executeOptimizedQuery('');
    }

    /**
     * Test conversation history retrieval
     */
    public function test_getConversationHistory_should_return_user_conversations() {
        $userId = 456;
        $conversationsTable = $this->wpdb->prefix . 'woo_ai_conversations';
        
        // Insert test conversations
        $testConversations = [
            ['message' => 'test_message_1', 'response' => 'test_response_1'],
            ['message' => 'test_message_2', 'response' => 'test_response_2'],
            ['message' => 'test_message_3', 'response' => 'test_response_3']
        ];

        foreach ($testConversations as $conv) {
            $this->wpdb->insert($conversationsTable, [
                'user_id' => $userId,
                'message' => $conv['message'],
                'response' => $conv['response'],
                'created_at' => current_time('mysql'),
                'status' => 'active'
            ]);
        }

        // Test conversation history retrieval
        $history = $this->queryOptimizer->getConversationHistory($userId, 10, 0);
        
        $this->assertIsArray($history, 'Conversation history should be an array');
        $this->assertCount(3, $history, 'Should return 3 conversations');
        $this->assertEquals('test_message_3', $history[0]->message, 'Should be ordered by created_at DESC');
    }

    /**
     * Test conversation history with invalid user ID
     */
    public function test_getConversationHistory_should_return_empty_for_invalid_user() {
        $invalidUserId = 0;
        $history = $this->queryOptimizer->getConversationHistory($invalidUserId);
        
        $this->assertIsArray($history, 'Should return array even for invalid user');
        $this->assertEmpty($history, 'Should return empty array for invalid user');
    }

    /**
     * Test knowledge base content retrieval
     */
    public function test_getKnowledgeBaseContent_should_return_kb_entries() {
        $kbTable = $this->wpdb->prefix . 'woo_ai_knowledge_base';
        
        // Insert test KB entries
        $testEntries = [
            ['type' => 'product', 'title' => 'test_product_1', 'content' => 'test_content_1'],
            ['type' => 'product', 'title' => 'test_product_2', 'content' => 'test_content_2'],
            ['type' => 'faq', 'title' => 'test_faq_1', 'content' => 'test_faq_content_1']
        ];

        foreach ($testEntries as $entry) {
            $this->wpdb->insert($kbTable, [
                'content_type' => $entry['type'],
                'title' => $entry['title'],
                'content' => $entry['content'],
                'updated_at' => current_time('mysql'),
                'status' => 'active'
            ]);
        }

        // Test knowledge base content retrieval
        $allContent = $this->queryOptimizer->getKnowledgeBaseContent('', 10);
        $this->assertIsArray($allContent, 'KB content should be an array');
        $this->assertCount(3, $allContent, 'Should return all 3 entries');

        // Test filtered content
        $productContent = $this->queryOptimizer->getKnowledgeBaseContent('product', 10);
        $this->assertIsArray($productContent, 'Filtered KB content should be an array');
        $this->assertCount(2, $productContent, 'Should return 2 product entries');
    }

    /**
     * Test conversation statistics
     */
    public function test_getConversationStatistics_should_return_analytics_data() {
        $conversationsTable = $this->wpdb->prefix . 'woo_ai_conversations';
        
        // Insert test data with ratings
        $testData = [
            ['user_id' => 100, 'message' => 'test_stats_1', 'response' => 'response_1', 'rating' => 5],
            ['user_id' => 101, 'message' => 'test_stats_2', 'response' => 'response_2', 'rating' => 4],
            ['user_id' => 100, 'message' => 'test_stats_3', 'response' => 'response_3', 'rating' => null]
        ];

        foreach ($testData as $data) {
            $this->wpdb->insert($conversationsTable, [
                'user_id' => $data['user_id'],
                'message' => $data['message'],
                'response' => $data['response'],
                'rating' => $data['rating'],
                'created_at' => current_time('mysql'),
                'status' => 'active'
            ]);
        }

        // Test conversation statistics
        $stats = $this->queryOptimizer->getConversationStatistics(30);
        
        $this->assertIsArray($stats, 'Statistics should be an array');
        if (!empty($stats)) {
            $this->assertArrayHasKey('total_conversations', $stats[0], 'Should include total conversations');
            $this->assertArrayHasKey('unique_users', $stats[0], 'Should include unique users');
            $this->assertArrayHasKey('avg_rating', $stats[0], 'Should include average rating');
        }
    }

    /**
     * Test database indexes creation
     */
    public function test_ensureDatabaseIndexes_should_create_indexes_without_error() {
        // This mainly tests that the method executes without throwing errors
        $this->queryOptimizer->ensureDatabaseIndexes();
        
        // The method should complete without exceptions
        $this->assertTrue(true, 'Database index creation should complete successfully');
    }

    /**
     * Test query statistics
     */
    public function test_getQueryStats_should_return_performance_metrics() {
        // Execute some queries to generate stats
        $this->queryOptimizer->getConversationHistory(999); // This should be cached/tracked
        
        $stats = $this->queryOptimizer->getQueryStats();
        
        $this->assertIsArray($stats, 'Query stats should be an array');
        
        // Check for expected keys
        if (!isset($stats['monitoring_disabled'])) {
            $this->assertArrayHasKey('total_queries', $stats, 'Should include total queries count');
            $this->assertArrayHasKey('slow_queries', $stats, 'Should include slow queries count');
            $this->assertArrayHasKey('slow_query_threshold', $stats, 'Should include threshold');
        }
    }

    /**
     * Test query cache clearing
     */
    public function test_clearQueryCache_should_clear_cached_queries() {
        // This method should execute without errors
        $result = $this->queryOptimizer->clearQueryCache();
        $this->assertTrue($result, 'Query cache clearing should return true');

        // Test clearing specific cache key
        $result2 = $this->queryOptimizer->clearQueryCache('specific_key');
        $this->assertTrue($result2, 'Specific query cache clearing should return true');
    }

    /**
     * Test prepared statement parameter handling
     */
    public function test_executeOptimizedQuery_should_handle_prepared_statements_correctly() {
        $conversationsTable = $this->wpdb->prefix . 'woo_ai_conversations';
        
        // Insert test data with special characters
        $specialMessage = "test message with 'quotes' and \"double quotes\"";
        $userId = 789;
        
        $this->wpdb->insert($conversationsTable, [
            'user_id' => $userId,
            'message' => $specialMessage,
            'response' => 'test response',
            'created_at' => current_time('mysql'),
            'status' => 'active'
        ]);

        // Test prepared statement with special characters
        $query = "SELECT * FROM {$conversationsTable} WHERE user_id = %d AND message = %s";
        $params = [$userId, $specialMessage];
        
        $results = $this->queryOptimizer->executeOptimizedQuery($query, $params);
        
        $this->assertIsArray($results, 'Should handle special characters in prepared statements');
        $this->assertCount(1, $results, 'Should return one result');
        $this->assertEquals($specialMessage, $results[0]->message, 'Special characters should be preserved');
    }

    /**
     * Test query caching functionality
     */
    public function test_executeOptimizedQuery_should_cache_select_results() {
        $conversationsTable = $this->wpdb->prefix . 'woo_ai_conversations';
        $userId = 999;
        $cacheKey = 'test_cache_key';
        
        // Insert test data
        $this->wpdb->insert($conversationsTable, [
            'user_id' => $userId,
            'message' => 'test_cache_message',
            'response' => 'test_cache_response',
            'created_at' => current_time('mysql'),
            'status' => 'active'
        ]);

        $query = "SELECT * FROM {$conversationsTable} WHERE user_id = %d";
        $params = [$userId];
        
        // First execution - should cache the result
        $results1 = $this->queryOptimizer->executeOptimizedQuery($query, $params, 300, $cacheKey);
        $this->assertIsArray($results1, 'First execution should return array');
        
        // Second execution - should use cached result
        $results2 = $this->queryOptimizer->executeOptimizedQuery($query, $params, 300, $cacheKey);
        $this->assertIsArray($results2, 'Second execution should return cached array');
        
        // Results should be the same
        $this->assertEquals($results1, $results2, 'Cached results should match original results');
    }

    /**
     * Test search functionality with full-text search
     */
    public function test_searchConversations_should_handle_empty_search_term() {
        $results = $this->queryOptimizer->searchConversations('');
        $this->assertIsArray($results, 'Should return array for empty search term');
        $this->assertEmpty($results, 'Should return empty array for empty search term');
    }

    /**
     * Test conversation pagination
     */
    public function test_getConversationHistory_should_handle_pagination() {
        $userId = 555;
        $conversationsTable = $this->wpdb->prefix . 'woo_ai_conversations';
        
        // Insert 5 test conversations
        for ($i = 1; $i <= 5; $i++) {
            $this->wpdb->insert($conversationsTable, [
                'user_id' => $userId,
                'message' => "test_pagination_message_$i",
                'response' => "test_pagination_response_$i",
                'created_at' => current_time('mysql'),
                'status' => 'active'
            ]);
        }

        // Test first page (limit 2)
        $page1 = $this->queryOptimizer->getConversationHistory($userId, 2, 0);
        $this->assertIsArray($page1, 'First page should be an array');
        $this->assertCount(2, $page1, 'First page should have 2 results');

        // Test second page (limit 2, offset 2)
        $page2 = $this->queryOptimizer->getConversationHistory($userId, 2, 2);
        $this->assertIsArray($page2, 'Second page should be an array');
        $this->assertCount(2, $page2, 'Second page should have 2 results');

        // Results should be different
        $this->assertNotEquals($page1[0]->id, $page2[0]->id, 'Pages should contain different results');
    }

    /**
     * Test error handling in optimized queries
     */
    public function test_executeOptimizedQuery_should_handle_database_errors_gracefully() {
        // Test with invalid table name to trigger database error
        $invalidQuery = "SELECT * FROM non_existent_table WHERE id = %d";
        $params = [1];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Query execution failed');
        
        $this->queryOptimizer->executeOptimizedQuery($invalidQuery, $params);
    }
}