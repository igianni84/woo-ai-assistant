<?php

/**
 * Unit Tests for KnowledgeBase Indexer Class
 *
 * Comprehensive test suite covering all functionality of the Indexer class
 * including text chunking, duplicate detection, content optimization,
 * database operations, and integration with WordPress/WooCommerce.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Unit\KnowledgeBase
 * @since 1.0.0
 * @author Claude Code Assistant
 */

use WooAiAssistant\KnowledgeBase\Indexer;
use WooAiAssistant\KnowledgeBase\Scanner;
use WooAiAssistant\Common\Utils;

/**
 * Class IndexerTest
 *
 * Unit tests for the Indexer class covering all public methods,
 * edge cases, error handling, naming conventions, and WordPress integration.
 *
 * @since 1.0.0
 */
class IndexerTest extends WP_UnitTestCase
{
    /**
     * Indexer instance for testing
     *
     * @since 1.0.0
     * @var Indexer
     */
    private $indexer;

    /**
     * Sample content data for testing
     *
     * @since 1.0.0
     * @var array
     */
    private $sampleContentData;

    /**
     * Database table name for knowledge base
     *
     * @since 1.0.0
     * @var string
     */
    private $tableName;

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
        $this->tableName = $wpdb->prefix . 'woo_ai_knowledge_base';
        
        $this->indexer = Indexer::getInstance();
        
        // Prepare sample content data
        $this->sampleContentData = [
            [
                'id' => 1,
                'title' => 'Test Product',
                'content' => 'This is a test product with detailed description. It has multiple features and benefits. The product is available in different sizes and colors. We offer worldwide shipping and a 30-day return policy.',
                'type' => 'product',
                'url' => 'https://example.com/product-1',
                'metadata' => [
                    'product_type' => 'simple',
                    'price' => '29.99',
                    'categories' => ['electronics', 'gadgets']
                ]
            ],
            [
                'id' => 2,
                'title' => 'Another Product',
                'content' => 'Another product with unique features. High quality materials and craftsmanship. Perfect for daily use and professional applications.',
                'type' => 'product',
                'url' => 'https://example.com/product-2',
                'metadata' => [
                    'product_type' => 'variable',
                    'price' => '49.99'
                ]
            ]
        ];
    }

    /**
     * Clean up after each test
     *
     * @since 1.0.0
     * @return void
     */
    public function tearDown(): void
    {
        // Clear test data from database
        global $wpdb;
        $wpdb->query("DELETE FROM {$this->tableName} WHERE source_type = 'test'");
        
        // Clear cache
        $this->indexer->clearCache();
        
        parent::tearDown();
    }

    /**
     * Test class existence and basic instantiation
     *
     * @since 1.0.0
     * @return void
     */
    public function test_class_exists_and_instantiates(): void
    {
        $this->assertTrue(class_exists('WooAiAssistant\\KnowledgeBase\\Indexer'));
        $this->assertInstanceOf(Indexer::class, $this->indexer);
    }

    /**
     * Test singleton pattern implementation
     *
     * @since 1.0.0
     * @return void
     */
    public function test_singleton_pattern_works_correctly(): void
    {
        $instance1 = Indexer::getInstance();
        $instance2 = Indexer::getInstance();
        
        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(Indexer::class, $instance1);
    }

    /**
     * Test naming conventions compliance for all methods
     *
     * @since 1.0.0
     * @return void
     */
    public function test_class_and_methods_follow_naming_conventions(): void
    {
        $reflection = new ReflectionClass($this->indexer);
        
        // Test class name follows PascalCase
        $className = $reflection->getShortName();
        $this->assertMatchesRegularExpression('/^[A-Z][a-zA-Z0-9]*$/', $className,
            "Class name '$className' should follow PascalCase convention");
        
        // Test all public methods follow camelCase
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            $methodName = $method->getName();
            
            // Skip magic methods and constructor
            if (strpos($methodName, '__') === 0) {
                continue;
            }
            
            $this->assertMatchesRegularExpression('/^[a-z][a-zA-Z0-9]*$/', $methodName,
                "Method '$methodName' should follow camelCase convention");
        }
    }

    /**
     * Test constants follow naming conventions
     *
     * @since 1.0.0
     * @return void
     */
    public function test_constants_follow_naming_conventions(): void
    {
        $reflection = new ReflectionClass($this->indexer);
        $constants = $reflection->getConstants();
        
        foreach ($constants as $name => $value) {
            $this->assertMatchesRegularExpression('/^[A-Z][A-Z0-9_]*$/', $name,
                "Constant '$name' should follow UPPER_SNAKE_CASE convention");
        }
    }

    /**
     * Test processContent with valid content data
     *
     * @since 1.0.0
     * @return void
     */
    public function test_processContent_should_return_success_stats_when_valid_data_provided(): void
    {
        $result = $this->indexer->processContent($this->sampleContentData, [
            'chunk_size' => 200,
            'optimize_for_ai' => true
        ]);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('total_processed', $result);
        $this->assertArrayHasKey('chunks_created', $result);
        $this->assertArrayHasKey('processing_time', $result);
        $this->assertEquals(2, $result['total_processed']);
        $this->assertGreaterThan(0, $result['chunks_created']);
    }

    /**
     * Test processContent throws exception with empty data
     *
     * @since 1.0.0
     * @return void
     */
    public function test_processContent_should_throw_exception_when_content_data_empty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Content data cannot be empty');
        
        $this->indexer->processContent([]);
    }

    /**
     * Test processContent with invalid chunk size
     *
     * @since 1.0.0
     * @return void
     */
    public function test_processContent_should_throw_exception_when_chunk_size_invalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Chunk size must be between');
        
        $this->indexer->processContent($this->sampleContentData, [
            'chunk_size' => 50 // Below minimum
        ]);
    }

    /**
     * Test createChunks with basic content
     *
     * @since 1.0.0
     * @return void
     */
    public function test_createChunks_should_return_proper_chunks_when_content_provided(): void
    {
        $content = 'This is the first sentence. This is the second sentence. This is a longer third sentence with more details.';
        
        $chunks = $this->indexer->createChunks($content, 150, 10, true);
        
        $this->assertIsArray($chunks);
        $this->assertGreaterThanOrEqual(1, count($chunks));
        
        foreach ($chunks as $index => $chunk) {
            $this->assertArrayHasKey('content', $chunk);
            $this->assertArrayHasKey('index', $chunk);
            $this->assertArrayHasKey('word_count', $chunk);
            $this->assertEquals($index, $chunk['index']);
            $this->assertGreaterThan(0, $chunk['word_count']);
        }
    }

    /**
     * Test createChunks with empty content
     *
     * @since 1.0.0
     * @return void
     */
    public function test_createChunks_should_throw_exception_when_content_empty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Content cannot be empty');
        
        $this->indexer->createChunks('', 100);
    }

    /**
     * Test createChunks with invalid parameters
     *
     * @since 1.0.0
     * @return void
     */
    public function test_createChunks_should_throw_exception_when_chunk_size_invalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid chunk size');
        
        $this->indexer->createChunks('Some content', 10); // Below minimum
    }

    /**
     * Test createChunks preserves sentence boundaries
     *
     * @since 1.0.0
     * @return void
     */
    public function test_createChunks_should_preserve_sentence_boundaries_when_requested(): void
    {
        $content = 'First sentence. Second sentence. Third sentence. Fourth sentence.';
        
        $chunks = $this->indexer->createChunks($content, 130, 5, true);
        
        foreach ($chunks as $chunk) {
            // Each chunk should end with proper punctuation or be complete
            $lastChar = substr(trim($chunk['content']), -1);
            $this->assertTrue(
                in_array($lastChar, ['.', '!', '?']) || strlen($chunk['content']) === strlen($content),
                'Chunk should preserve sentence boundaries'
            );
        }
    }

    /**
     * Test createChunks with small content returns single chunk
     *
     * @since 1.0.0
     * @return void
     */
    public function test_createChunks_should_return_single_chunk_when_content_smaller_than_chunk_size(): void
    {
        $content = 'Short content.';
        
        $chunks = $this->indexer->createChunks($content, 1000);
        
        $this->assertCount(1, $chunks);
        $this->assertEquals($content, $chunks[0]['content']);
        $this->assertEquals(0, $chunks[0]['index']);
    }

    /**
     * Test storeChunks with valid data
     *
     * @since 1.0.0
     * @return void
     */
    public function test_storeChunks_should_store_chunks_successfully_when_valid_data_provided(): void
    {
        $chunks = [
            [
                'content' => 'First chunk content',
                'index' => 0,
                'start_pos' => 0,
                'end_pos' => 19,
                'word_count' => 3,
                'sentence_count' => 1
            ],
            [
                'content' => 'Second chunk content',
                'index' => 1,
                'start_pos' => 15,
                'end_pos' => 35,
                'word_count' => 3,
                'sentence_count' => 1
            ]
        ];

        $sourceData = [
            'id' => 999,
            'title' => 'Test Content',
            'content' => 'Full test content here',
            'type' => 'test',
            'metadata' => ['test' => true]
        ];

        $result = $this->indexer->storeChunks($chunks, $sourceData, [], 'test_hash_123');
        
        $this->assertArrayHasKey('stored', $result);
        $this->assertArrayHasKey('skipped', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertEquals(2, $result['stored']);
        $this->assertEquals(0, $result['errors']);
    }

    /**
     * Test storeChunks with empty chunks array
     *
     * @since 1.0.0
     * @return void
     */
    public function test_storeChunks_should_return_zero_stats_when_chunks_empty(): void
    {
        $result = $this->indexer->storeChunks([], ['id' => 1, 'type' => 'test']);
        
        $this->assertEquals(0, $result['stored']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEquals(0, $result['errors']);
    }

    /**
     * Test removeDuplicates functionality
     *
     * @since 1.0.0
     * @return void
     */
    public function test_removeDuplicates_should_identify_duplicates_correctly(): void
    {
        $contentWithDuplicates = [
            ['id' => 1, 'content' => 'Unique content one'],
            ['id' => 2, 'content' => 'Duplicate content'],
            ['id' => 3, 'content' => 'Duplicate content'], // Same as id 2
            ['id' => 4, 'content' => 'Another unique content']
        ];
        
        $result = $this->indexer->removeDuplicates($contentWithDuplicates);
        
        $this->assertEquals(4, $result['original_count']);
        $this->assertEquals(1, $result['duplicates_found']);
        $this->assertCount(3, $result['unique_items']);
    }

    /**
     * Test optimizeForAi with default options
     *
     * @since 1.0.0
     * @return void
     */
    public function test_optimizeForAi_should_return_optimized_content_when_valid_content_provided(): void
    {
        $content = 'This  is   a test   content  with    excessive    whitespace!!!';
        
        $optimized = $this->indexer->optimizeForAi($content);
        
        $this->assertNotEquals($content, $optimized);
        $this->assertStringNotContainsString('   ', $optimized); // No triple spaces
        $this->assertStringNotContainsString('!!!', $optimized); // Excessive punctuation reduced
    }

    /**
     * Test optimizeForAi with keyword enhancement
     *
     * @since 1.0.0
     * @return void
     */
    public function test_optimizeForAi_should_enhance_keywords_when_requested(): void
    {
        $content = 'The price is $29.99 and shipping is free.';
        
        $optimized = $this->indexer->optimizeForAi($content, ['enhance_keywords' => true]);
        
        $this->assertStringContainsString('product price', $optimized);
        $this->assertStringContainsString('shipping and delivery', $optimized);
    }

    /**
     * Test getIndexingStats returns valid statistics
     *
     * @since 1.0.0
     * @return void
     */
    public function test_getIndexingStats_should_return_array_with_statistics(): void
    {
        // Process some content first to generate stats
        $this->indexer->processContent($this->sampleContentData);
        
        $stats = $this->indexer->getIndexingStats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('start_time', $stats);
        $this->assertArrayHasKey('end_time', $stats);
        $this->assertArrayHasKey('results', $stats);
    }

    /**
     * Test clearCache functionality
     *
     * @since 1.0.0
     * @return void
     */
    public function test_clearCache_should_return_true_when_cache_cleared(): void
    {
        $result = $this->indexer->clearCache();
        $this->assertTrue($result);
        
        $specificResult = $this->indexer->clearCache('specific_key');
        $this->assertTrue($specificResult);
    }

    /**
     * Test getChunkStats returns database statistics
     *
     * @since 1.0.0
     * @return void
     */
    public function test_getChunkStats_should_return_database_statistics(): void
    {
        // First store some test chunks
        $chunks = [
            [
                'content' => 'Test chunk for stats',
                'index' => 0,
                'start_pos' => 0,
                'end_pos' => 20,
                'word_count' => 4,
                'sentence_count' => 1
            ]
        ];

        $sourceData = [
            'id' => 888,
            'title' => 'Stats Test',
            'content' => 'Test content for statistics',
            'type' => 'test_stats'
        ];

        $this->indexer->storeChunks($chunks, $sourceData);
        
        $stats = $this->indexer->getChunkStats('test_stats');
        
        $this->assertIsArray($stats);
        if (!empty($stats)) {
            $this->assertArrayHasKey('source_type', $stats[0]);
            $this->assertArrayHasKey('total_chunks', $stats[0]);
            $this->assertArrayHasKey('avg_chunk_size', $stats[0]);
        }
    }

    /**
     * Test content update hook functionality
     *
     * @since 1.0.0
     * @return void
     */
    public function test_onContentUpdated_should_handle_content_updates(): void
    {
        // This method should not throw exceptions
        $this->indexer->onContentUpdated('product', 123);
        
        // Verify that the hook was processed (no exceptions thrown)
        $this->assertTrue(true);
    }

    /**
     * Test bulk reindex functionality
     *
     * @since 1.0.0
     * @return void
     */
    public function test_processBulkReindex_should_handle_bulk_operations(): void
    {
        $options = [
            'content_types' => ['products'],
            'chunk_size' => 500,
            'force_update' => true
        ];
        
        // This should not throw exceptions even if no content available
        $this->indexer->processBulkReindex($options);
        
        $this->assertTrue(true);
    }

    /**
     * Test error handling with malformed content data
     *
     * @since 1.0.0
     * @return void
     */
    public function test_processContent_should_handle_malformed_data_gracefully(): void
    {
        $malformedData = [
            ['id' => 1], // Missing content
            ['content' => ''], // Empty content
            ['id' => 2, 'content' => 'Valid content', 'type' => 'test']
        ];
        
        $result = $this->indexer->processContent($malformedData);
        
        $this->assertArrayHasKey('errors', $result);
        $this->assertGreaterThan(0, $result['errors']);
        $this->assertEquals(1, $result['total_processed']); // Only valid item processed
    }

    /**
     * Test memory efficiency with large content
     *
     * @since 1.0.0
     * @return void
     */
    public function test_processContent_should_handle_large_datasets_efficiently(): void
    {
        // Create large dataset
        $largeDataset = [];
        for ($i = 0; $i < 20; $i++) {
            $largeDataset[] = [
                'id' => $i,
                'title' => "Product $i",
                'content' => str_repeat("This is product $i with detailed description. ", 5),
                'type' => 'product'
            ];
        }
        
        $memoryBefore = memory_get_usage();
        
        $result = $this->indexer->processContent($largeDataset, [
            'batch_size' => 5,
            'chunk_size' => 100
        ]);
        
        $memoryAfter = memory_get_usage();
        $memoryUsed = $memoryAfter - $memoryBefore;
        
        $this->assertEquals(20, $result['total_processed']);
        $this->assertEquals(4, $result['batches_processed']);
        
        // Memory usage should be reasonable (less than 80MB for this test - more realistic for processing)
        $this->assertLessThan(80 * 1024 * 1024, $memoryUsed);
    }

    /**
     * Test WordPress integration - hooks and actions
     *
     * @since 1.0.0
     * @return void
     */
    public function test_wordpress_integration_hooks_registered(): void
    {
        // Check if WordPress hooks are properly registered
        $this->assertTrue(has_action('woo_ai_assistant_content_updated'));
        $this->assertTrue(has_action('woo_ai_assistant_bulk_reindex'));
    }

    /**
     * Test database table interaction
     *
     * @since 1.0.0
     * @return void
     */
    public function test_database_table_operations_work_correctly(): void
    {
        global $wpdb;
        
        // Verify table exists
        $tableExists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $this->tableName
        ));
        
        $this->assertNotNull($tableExists, 'Knowledge base table should exist');
        
        // Test basic insert operation
        $testData = [
            'source_type' => 'test',
            'source_id' => 999999,
            'title' => 'Test Entry',
            'content' => 'Test content',
            'chunk_content' => 'Test chunk',
            'chunk_index' => 0,
            'hash' => 'test_hash',
            'metadata' => '{"test": true}'
        ];
        
        $result = $wpdb->insert($this->tableName, $testData);
        $this->assertNotFalse($result, 'Should be able to insert test data');
        
        // Clean up
        $wpdb->delete($this->tableName, ['source_id' => 999999]);
    }

    /**
     * Test edge case: very long content
     *
     * @since 1.0.0
     * @return void
     */
    public function test_createChunks_should_handle_very_long_content(): void
    {
        // Create content longer than 10,000 characters
        $longContent = str_repeat('This is a sentence with multiple words. ', 500);
        
        $chunks = $this->indexer->createChunks($longContent, 800, 100);
        
        $this->assertIsArray($chunks);
        $this->assertGreaterThan(10, count($chunks));
        
        // Verify all chunks are within size limits
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(1000, strlen($chunk['content']));
            $this->assertGreaterThanOrEqual(100, strlen($chunk['content']));
        }
    }

    /**
     * Test edge case: content with special characters
     *
     * @since 1.0.0
     * @return void
     */
    public function test_optimizeForAi_should_handle_special_characters(): void
    {
        $specialContent = 'Content with émojis 😀 and spéciál chäracteŕs! Price: €29.99 & more...';
        
        $optimized = $this->indexer->optimizeForAi($specialContent);
        
        $this->assertIsString($optimized);
        $this->assertNotEmpty($optimized);
        $this->assertStringContainsString('€29.99', $optimized); // Should preserve currency symbols
    }

    /**
     * Test performance with realistic e-commerce content
     *
     * @since 1.0.0
     * @return void
     */
    public function test_performance_with_realistic_ecommerce_content(): void
    {
        $ecommerceContent = [
            [
                'id' => 1001,
                'title' => 'Wireless Bluetooth Headphones',
                'content' => 'Premium wireless Bluetooth headphones with noise cancellation. Features: 30-hour battery life, quick charge, comfortable over-ear design. Perfect for music lovers and professionals. Available in black, white, and blue colors. Price: $149.99. Free shipping on orders over $50. 30-day return policy. Compatible with all devices.',
                'type' => 'product',
                'metadata' => ['price' => 149.99, 'category' => 'electronics']
            ],
            [
                'id' => 1002,
                'title' => 'Organic Cotton T-Shirt',
                'content' => '100% organic cotton t-shirt made from sustainable materials. Soft, breathable, and comfortable for everyday wear. Available sizes: XS to XXL. Colors: white, black, navy, gray. Machine washable. Fair trade certified. Price starts at $29.99. Buy 2 get 1 free promotion currently active.',
                'type' => 'product',
                'metadata' => ['price' => 29.99, 'category' => 'clothing']
            ]
        ];
        
        $startTime = microtime(true);
        
        $result = $this->indexer->processContent($ecommerceContent, [
            'chunk_size' => 300,
            'optimize_for_ai' => true,
            'remove_duplicates' => true
        ]);
        
        $endTime = microtime(true);
        $processingTime = $endTime - $startTime;
        
        $this->assertEquals(2, $result['total_processed']);
        $this->assertLessThan(5, $processingTime); // Should complete in under 5 seconds
        $this->assertGreaterThan(0, $result['chunks_created']);
    }

    /**
     * Test all public methods exist and are callable
     *
     * @since 1.0.0
     * @return void
     */
    public function test_all_required_public_methods_exist(): void
    {
        $requiredMethods = [
            'processContent',
            'createChunks',
            'storeChunks',
            'removeDuplicates',
            'optimizeForAi',
            'onContentUpdated',
            'processBulkReindex',
            'getIndexingStats',
            'clearCache',
            'getChunkStats'
        ];
        
        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                method_exists($this->indexer, $method),
                "Method '$method' should exist in Indexer class"
            );
            
            $this->assertTrue(
                is_callable([$this->indexer, $method]),
                "Method '$method' should be callable"
            );
        }
    }
}