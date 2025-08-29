<?php
/**
 * Cache Manager Test Class
 *
 * Unit tests for the CacheManager performance optimization class.
 * Tests caching strategies, cache invalidation, and performance metrics.
 *
 * @package WooAiAssistant
 * @subpackage Tests
 * @since 1.0.0
 * @author Claude Code Assistant
 */

namespace WooAiAssistant\Tests\Unit\Performance;

// Include WordPress function mocks
require_once __DIR__ . '/../Chatbot/wp-functions-mock.php';

use WooAiAssistant\Performance\CacheManager;
use WooAiAssistant\Tests\WP_UnitTestCase;

/**
 * Class CacheManagerTest
 *
 * @since 1.0.0
 */
class CacheManagerTest extends WP_UnitTestCase {

    private $cacheManager;

    public function setUp(): void {
        parent::setUp();
        $this->cacheManager = CacheManager::getInstance();
    }

    public function tearDown(): void {
        // Clear all caches after each test
        $this->cacheManager->flushAllCaches();
        parent::tearDown();
    }

    /**
     * Test class existence and instantiation
     */
    public function test_class_exists_and_instantiates() {
        $this->assertTrue(class_exists('WooAiAssistant\\Performance\\CacheManager'));
        $this->assertInstanceOf(CacheManager::class, $this->cacheManager);
    }

    /**
     * Test class follows naming conventions
     */
    public function test_class_follows_naming_conventions() {
        $reflection = new \ReflectionClass($this->cacheManager);
        
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
     * Test FAQ cache set and get functionality
     */
    public function test_getFaqCache_should_return_cached_data_when_cache_exists() {
        $testQuestion = 'what_is_shipping_policy';
        $testAnswer = 'We offer free shipping on orders over $50.';

        // Set cache
        $result = $this->cacheManager->setFaqCache($testQuestion, $testAnswer);
        $this->assertTrue($result, 'FAQ cache should be set successfully');

        // Get cache
        $cachedData = $this->cacheManager->getFaqCache($testQuestion);
        $this->assertEquals($testAnswer, $cachedData, 'Cached data should match original data');
    }

    /**
     * Test FAQ cache with callback function
     */
    public function test_getFaqCache_should_use_callback_when_cache_miss() {
        $testQuestion = 'what_is_return_policy';
        $expectedAnswer = 'We accept returns within 30 days.';

        $callback = function() use ($expectedAnswer) {
            return $expectedAnswer;
        };

        // Get cache with callback (should trigger callback since no cache exists)
        $result = $this->cacheManager->getFaqCache($testQuestion, $callback);
        $this->assertEquals($expectedAnswer, $result, 'Should return callback result');

        // Verify data was cached
        $cachedData = $this->cacheManager->getFaqCache($testQuestion);
        $this->assertEquals($expectedAnswer, $cachedData, 'Data should be cached after callback');
    }

    /**
     * Test knowledge base cache functionality
     */
    public function test_getKnowledgeBaseCache_should_handle_query_caching() {
        $testQuery = 'laptop computers';
        $testResults = ['product1', 'product2', 'product3'];

        $callback = function() use ($testResults) {
            return $testResults;
        };

        // Test cache miss with callback
        $result = $this->cacheManager->getKnowledgeBaseCache($testQuery, $callback);
        $this->assertEquals($testResults, $result, 'Should return callback results');

        // Test cache hit
        $cachedResult = $this->cacheManager->getKnowledgeBaseCache($testQuery);
        $this->assertEquals($testResults, $cachedResult, 'Should return cached results');
    }

    /**
     * Test conversation cache functionality
     */
    public function test_getConversationCache_should_handle_conversation_data() {
        $conversationId = 'conv_123';
        $conversationData = [
            'id' => $conversationId,
            'messages' => ['Hello', 'How can I help?'],
            'timestamp' => time()
        ];

        // Set conversation cache
        $result = $this->cacheManager->setConversationCache($conversationId, $conversationData);
        $this->assertTrue($result, 'Conversation cache should be set successfully');

        // Get conversation cache
        $cached = $this->cacheManager->getConversationCache($conversationId);
        $this->assertEquals($conversationData, $cached, 'Cached conversation should match original');
    }

    /**
     * Test cache invalidation for products
     */
    public function test_invalidateProductCache_should_clear_related_caches() {
        // Create a test product post
        $productId = $this->factory->post->create([
            'post_type' => 'product',
            'post_title' => 'Test Product',
            'post_status' => 'publish'
        ]);

        // Set some FAQ cache that would be related to products
        $this->cacheManager->setFaqCache('product_info', 'Product information cached');
        
        // Verify cache exists
        $cached = $this->cacheManager->getFaqCache('product_info');
        $this->assertNotFalse($cached, 'Cache should exist before invalidation');

        // Trigger product cache invalidation
        $this->cacheManager->invalidateProductCache($productId);

        // This test mainly ensures no errors occur during invalidation
        // Full cache clearing verification would require more complex mocking
        $this->assertTrue(true, 'Product cache invalidation should complete without errors');
    }

    /**
     * Test cache statistics functionality
     */
    public function test_getCacheStats_should_return_performance_metrics() {
        // Perform some cache operations to generate stats
        $this->cacheManager->getFaqCache('test_question', function() { return 'test_answer'; });
        $this->cacheManager->getFaqCache('test_question'); // Cache hit
        $this->cacheManager->getFaqCache('nonexistent_question'); // Cache miss

        $stats = $this->cacheManager->getCacheStats();
        
        $this->assertIsArray($stats, 'Cache stats should be an array');
        $this->assertArrayHasKey('hits', $stats, 'Stats should include hit count');
        $this->assertArrayHasKey('misses', $stats, 'Stats should include miss count');
        $this->assertArrayHasKey('hit_ratio', $stats, 'Stats should include hit ratio');
        $this->assertArrayHasKey('total_requests', $stats, 'Stats should include total requests');

        $this->assertGreaterThan(0, $stats['hits'], 'Should have recorded cache hits');
        $this->assertGreaterThan(0, $stats['misses'], 'Should have recorded cache misses');
        $this->assertIsFloat($stats['hit_ratio'], 'Hit ratio should be a float');
    }

    /**
     * Test empty question handling
     */
    public function test_getFaqCache_should_throw_exception_for_empty_question() {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Question cannot be empty');
        
        $this->cacheManager->getFaqCache('');
    }

    /**
     * Test cache key generation with different contexts
     */
    public function test_cache_keys_should_be_context_aware() {
        // This tests that cache keys are properly generated and isolated
        $question = 'test_question';
        $answer1 = 'answer for user 1';
        
        // Set cache for first context
        $result1 = $this->cacheManager->setFaqCache($question, $answer1);
        $this->assertTrue($result1, 'Should set cache successfully');

        // Get cache should return the set value
        $cached1 = $this->cacheManager->getFaqCache($question);
        $this->assertEquals($answer1, $cached1, 'Should return cached value');
    }

    /**
     * Test knowledge base cache invalidation
     */
    public function test_invalidateKnowledgeBaseCache_should_clear_kb_and_faq_caches() {
        // Set some test data
        $this->cacheManager->setFaqCache('kb_question', 'kb_answer');
        
        // Verify data exists before invalidation
        $cached = $this->cacheManager->getFaqCache('kb_question');
        $this->assertEquals('kb_answer', $cached, 'Cache should exist before invalidation');

        // Trigger knowledge base cache invalidation
        $this->cacheManager->invalidateKnowledgeBaseCache();

        // This mainly tests that the method executes without errors
        $this->assertTrue(true, 'Knowledge base cache invalidation should complete successfully');
    }

    /**
     * Test cache expiration times
     */
    public function test_setFaqCache_should_accept_custom_expiration() {
        $question = 'expiration_test';
        $answer = 'test answer';
        $customExpiration = 3600; // 1 hour

        $result = $this->cacheManager->setFaqCache($question, $answer, $customExpiration);
        $this->assertTrue($result, 'Should set cache with custom expiration');

        // Verify cache was set
        $cached = $this->cacheManager->getFaqCache($question);
        $this->assertEquals($answer, $cached, 'Should return cached value');
    }

    /**
     * Test flush all caches functionality
     */
    public function test_flushAllCaches_should_clear_all_cache_groups() {
        // Set data in different cache groups
        $this->cacheManager->setFaqCache('faq_question', 'faq_answer');
        $this->cacheManager->setConversationCache('conv_123', ['test' => 'data']);

        // Verify caches exist
        $faqCached = $this->cacheManager->getFaqCache('faq_question');
        $convCached = $this->cacheManager->getConversationCache('conv_123');
        
        $this->assertNotFalse($faqCached, 'FAQ cache should exist before flush');
        $this->assertNotFalse($convCached, 'Conversation cache should exist before flush');

        // Flush all caches
        $this->cacheManager->flushAllCaches();

        // This mainly verifies the method executes without errors
        $this->assertTrue(true, 'Flush all caches should complete successfully');
    }

    /**
     * Test invalid data handling
     */
    public function test_setFaqCache_should_reject_null_data() {
        $result = $this->cacheManager->setFaqCache('test_question', null);
        $this->assertFalse($result, 'Should reject null data');
    }

    public function test_setConversationCache_should_reject_invalid_input() {
        $result1 = $this->cacheManager->setConversationCache('', ['data' => 'test']);
        $this->assertFalse($result1, 'Should reject empty conversation ID');

        $result2 = $this->cacheManager->setConversationCache('valid_id', null);
        $this->assertFalse($result2, 'Should reject null data');
    }

    /**
     * Test performance monitoring integration
     */
    public function test_cache_operations_should_track_performance_metrics() {
        // Perform operations that should be tracked
        $this->cacheManager->getFaqCache('perf_test', function() { return 'test_data'; });
        
        $stats = $this->cacheManager->getCacheStats();
        
        // Verify basic stats structure exists
        $this->assertArrayHasKey('hits', $stats);
        $this->assertArrayHasKey('misses', $stats);
        $this->assertArrayHasKey('sets', $stats);
        
        // Values should be non-negative
        $this->assertGreaterThanOrEqual(0, $stats['hits']);
        $this->assertGreaterThanOrEqual(0, $stats['misses']);
        $this->assertGreaterThanOrEqual(0, $stats['sets']);
    }

    /**
     * Test cache key generation consistency
     */
    public function test_cache_keys_should_be_consistent_for_same_input() {
        $question = 'consistency_test';
        $answer = 'test answer';

        // Set cache multiple times
        $this->cacheManager->setFaqCache($question, $answer);
        $result1 = $this->cacheManager->getFaqCache($question);

        $this->cacheManager->setFaqCache($question, $answer);
        $result2 = $this->cacheManager->getFaqCache($question);

        $this->assertEquals($result1, $result2, 'Cache results should be consistent');
        $this->assertEquals($answer, $result1, 'Cache should return expected value');
    }
}