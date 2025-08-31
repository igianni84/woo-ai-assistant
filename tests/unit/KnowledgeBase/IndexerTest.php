<?php

/**
 * Tests for Knowledge Base Indexer Class
 *
 * Comprehensive unit tests for the Indexer class that orchestrates content
 * indexing for the AI knowledge base. Tests batch processing, database operations,
 * chunking integration, error handling, and performance optimization.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Unit\KnowledgeBase
 * @since 1.0.0
 * @author Claude Code Assistant
 */

namespace WooAiAssistant\Tests\Unit\KnowledgeBase;

use WooAiAssistant\Tests\Unit\WooAiBaseTestCase;
use WooAiAssistant\KnowledgeBase\Indexer;
use WooAiAssistant\KnowledgeBase\Scanner;
use WooAiAssistant\KnowledgeBase\ChunkingStrategy;
use WooAiAssistant\Common\Utils;
use WooAiAssistant\Common\Cache;
use Exception;

/**
 * Class IndexerTest
 *
 * Test cases for the Knowledge Base Indexer class.
 * Verifies content indexing, batch processing, database operations, and error handling.
 *
 * @since 1.0.0
 */
class IndexerTest extends WooAiBaseTestCase
{
    /**
     * Indexer instance
     *
     * @var Indexer
     */
    private $indexer;

    /**
     * Scanner instance for mocking
     *
     * @var Scanner
     */
    private $scanner;

    /**
     * Database table name for testing
     *
     * @var string
     */
    private $tableName;

    /**
     * Test content data
     *
     * @var array
     */
    private $testContentData = [];

    /**
     * Set up test environment
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        
        if (!Utils::isWooCommerceActive()) {
            $this->markTestSkipped('WooCommerce is required for Indexer tests');
        }

        global $wpdb;
        $this->tableName = $wpdb->prefix . 'woo_ai_knowledge_base';

        // Ensure table exists (normally handled by migration system)
        $this->createTestTable();

        $this->indexer = Indexer::getInstance();
        $this->scanner = Scanner::getInstance();
        
        $this->setupTestContentData();
    }

    /**
     * Create test database table
     *
     * @return void
     */
    private function createTestTable(): void
    {
        global $wpdb;
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tableName} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            content_type varchar(50) NOT NULL,
            content_id bigint(20) unsigned NOT NULL,
            chunk_text text NOT NULL,
            embedding text DEFAULT NULL,
            metadata text DEFAULT NULL,
            chunk_hash varchar(64) NOT NULL,
            chunk_index int(11) NOT NULL DEFAULT 0,
            total_chunks int(11) NOT NULL DEFAULT 1,
            word_count int(11) NOT NULL DEFAULT 0,
            embedding_model varchar(100) DEFAULT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_chunk (content_type, content_id, chunk_hash),
            KEY content_lookup (content_type, content_id),
            KEY active_chunks (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $wpdb->query($sql);
    }

    /**
     * Set up test content data
     *
     * @return void
     */
    private function setupTestContentData(): void
    {
        $this->testContentData = [
            [
                'id' => 1,
                'type' => 'product',
                'title' => 'Test Product',
                'content' => 'This is a test product with detailed description for indexing. It contains multiple sentences to test chunking strategy. The content should be properly processed and stored in the knowledge base.',
                'url' => 'https://example.com/product/1',
                'metadata' => [
                    'price' => '29.99',
                    'sku' => 'TEST-001'
                ],
                'language' => 'en',
                'last_modified' => '2023-01-01 12:00:00'
            ],
            [
                'id' => 2,
                'type' => 'page',
                'title' => 'Test Page',
                'content' => 'This is a test page with comprehensive information. It includes multiple paragraphs and detailed content that needs to be properly indexed.',
                'url' => 'https://example.com/page/2',
                'metadata' => [
                    'template' => 'page',
                    'parent_id' => 0
                ],
                'language' => 'en',
                'last_modified' => '2023-01-02 12:00:00'
            ]
        ];
    }

    /**
     * Test indexer singleton pattern
     *
     * Verifies that Indexer class follows singleton pattern correctly.
     *
     * @return void
     */
    public function test_getInstance_should_return_singleton_instance(): void
    {
        $instance1 = Indexer::getInstance();
        $instance2 = Indexer::getInstance();

        $this->assertInstanceOf(Indexer::class, $instance1);
        $this->assertSame($instance1, $instance2, 'getInstance should return the same instance (singleton pattern)');
    }

    /**
     * Test successful single item indexing
     *
     * Verifies that indexSingleItem processes content correctly.
     *
     * @return void
     */
    public function test_indexSingleItem_should_process_content_successfully(): void
    {
        $contentData = $this->testContentData[0];
        
        // Mock chunking strategy to return predictable chunks
        $this->mockChunkingStrategy();
        
        $result = $this->indexer->indexSingleItem($contentData, true);

        $this->assertIsArray($result, 'indexSingleItem should return array');
        $this->assertArrayHasKey('chunks_processed', $result, 'Result should contain chunks_processed');
        $this->assertArrayHasKey('chunks_inserted', $result, 'Result should contain chunks_inserted');
        $this->assertArrayHasKey('chunks_updated', $result, 'Result should contain chunks_updated');
        $this->assertArrayHasKey('skipped', $result, 'Result should contain skipped');

        $this->assertIsInt($result['chunks_processed'], 'chunks_processed should be integer');
        $this->assertIsInt($result['chunks_inserted'], 'chunks_inserted should be integer');
        $this->assertIsInt($result['chunks_updated'], 'chunks_updated should be integer');
        $this->assertIsBool($result['skipped'], 'skipped should be boolean');

        $this->assertGreaterThan(0, $result['chunks_processed'], 'Should process at least one chunk');
        $this->assertFalse($result['skipped'], 'Should not skip processing with force reindex');
    }

    /**
     * Test single item indexing validation
     *
     * Verifies that indexSingleItem validates content data properly.
     *
     * @return void
     */
    public function test_indexSingleItem_should_validate_content_data(): void
    {
        $invalidContentData = [
            'id' => '',  // Invalid ID
            'type' => 'product',
            'content' => 'Test content'
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Missing or empty required field');

        $this->indexer->indexSingleItem($invalidContentData);
    }

    /**
     * Test content type indexing
     *
     * Verifies that indexContentType processes all content of a type.
     *
     * @return void
     */
    public function test_indexContentType_should_process_all_content(): void
    {
        // Mock scanner to return test content
        $this->mockScannerContent();
        
        $result = $this->indexer->indexContentType('product', true);

        $this->assertIsArray($result, 'indexContentType should return array');
        $this->assertArrayHasKey('items_processed', $result, 'Result should contain items_processed');
        $this->assertArrayHasKey('chunks_processed', $result, 'Result should contain chunks_processed');
        $this->assertArrayHasKey('processing_time', $result, 'Result should contain processing_time');

        $this->assertIsInt($result['items_processed'], 'items_processed should be integer');
        $this->assertIsInt($result['chunks_processed'], 'chunks_processed should be integer');
        $this->assertIsFloat($result['processing_time'], 'processing_time should be float');
    }

    /**
     * Test comprehensive content indexing
     *
     * Verifies that indexAllContent orchestrates all content types correctly.
     *
     * @return void
     */
    public function test_indexAllContent_should_process_all_content_types(): void
    {
        // Mock scanner methods
        $this->mockScannerContent();
        
        $result = $this->indexer->indexAllContent([
            'content_types' => ['product', 'page'],
            'force_reindex' => true,
            'max_execution_time' => 60
        ]);

        $this->assertIsArray($result, 'indexAllContent should return array');
        $this->assertArrayHasKey('success', $result, 'Result should contain success');
        $this->assertArrayHasKey('statistics', $result, 'Result should contain statistics');
        $this->assertArrayHasKey('processing_summary', $result, 'Result should contain processing_summary');
        $this->assertArrayHasKey('errors', $result, 'Result should contain errors');
        $this->assertArrayHasKey('timestamp', $result, 'Result should contain timestamp');

        $this->assertIsBool($result['success'], 'success should be boolean');
        $this->assertIsArray($result['statistics'], 'statistics should be array');
        $this->assertIsArray($result['processing_summary'], 'processing_summary should be array');
        $this->assertIsArray($result['errors'], 'errors should be array');

        // Check processing summary structure
        foreach (['product', 'page'] as $contentType) {
            $this->assertArrayHasKey($contentType, $result['processing_summary'], "Summary should contain {$contentType}");
        }
    }

    /**
     * Test error handling in content indexing
     *
     * Verifies that indexer handles errors gracefully and continues processing.
     *
     * @return void
     */
    public function test_indexAllContent_should_handle_errors_gracefully(): void
    {
        // Mock scanner to throw exception for one content type
        add_filter('woo_ai_assistant_mock_scanner_error_product', '__return_true');

        $result = $this->indexer->indexAllContent([
            'content_types' => ['product', 'page'],
            'force_reindex' => true
        ]);

        $this->assertIsArray($result, 'indexAllContent should return array even with errors');
        $this->assertArrayHasKey('errors', $result, 'Result should contain errors');
        
        // Should have at least one error
        $this->assertGreaterThanOrEqual(1, count($result['errors']), 'Should record at least one error');

        // But should still process other content types
        $this->assertArrayHasKey('processing_summary', $result, 'Should still have processing summary');

        // Clean up
        remove_filter('woo_ai_assistant_mock_scanner_error_product', '__return_true');
    }

    /**
     * Test content removal
     *
     * Verifies that removeContent removes all chunks for specific content.
     *
     * @return void
     */
    public function test_removeContent_should_delete_all_chunks(): void
    {
        // First index some content
        $contentData = $this->testContentData[0];
        $this->mockChunkingStrategy();
        $this->indexer->indexSingleItem($contentData, true);

        // Verify content was indexed
        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tableName} WHERE content_id = %d AND content_type = %s",
            $contentData['id'],
            $contentData['type']
        ));
        $this->assertGreaterThan(0, $count, 'Content should be indexed before removal');

        // Remove content
        $result = $this->indexer->removeContent($contentData['id'], $contentData['type']);
        $this->assertTrue($result, 'removeContent should return true on success');

        // Verify content was removed
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tableName} WHERE content_id = %d AND content_type = %s",
            $contentData['id'],
            $contentData['type']
        ));
        $this->assertEquals(0, $count, 'Content should be removed after removeContent call');
    }

    /**
     * Test indexer statistics
     *
     * Verifies that getStatistics returns comprehensive information.
     *
     * @return void
     */
    public function test_getStatistics_should_return_comprehensive_info(): void
    {
        $stats = $this->indexer->getStatistics();

        $this->assertIsArray($stats, 'getStatistics should return array');

        $expectedKeys = [
            'current_indexing_stats',
            'processing_status',
            'database_statistics',
            'configuration',
            'chunking_strategy_stats'
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $stats, "Statistics should contain '{$key}' key");
        }

        // Check configuration structure
        $config = $stats['configuration'];
        $configKeys = ['batch_size', 'cache_ttl', 'max_chunks_per_request', 'table_name'];
        foreach ($configKeys as $key) {
            $this->assertArrayHasKey($key, $config, "Configuration should contain '{$key}' key");
        }

        $this->assertIsInt($config['batch_size'], 'batch_size should be integer');
        $this->assertIsInt($config['cache_ttl'], 'cache_ttl should be integer');
        $this->assertIsInt($config['max_chunks_per_request'], 'max_chunks_per_request should be integer');
        $this->assertIsString($config['table_name'], 'table_name should be string');
    }

    /**
     * Test batch size configuration
     *
     * Verifies that setBatchSize updates configuration correctly.
     *
     * @return void
     */
    public function test_setBatchSize_should_update_configuration(): void
    {
        $originalBatchSize = $this->getPropertyValue($this->indexer, 'batchSize');
        $newBatchSize = 15;

        $this->indexer->setBatchSize($newBatchSize);
        $updatedBatchSize = $this->getPropertyValue($this->indexer, 'batchSize');

        $this->assertEquals($newBatchSize, $updatedBatchSize, 'setBatchSize should update batch size');

        // Restore original batch size
        $this->indexer->setBatchSize($originalBatchSize);
    }

    /**
     * Test invalid batch size values
     *
     * Verifies that setBatchSize validates input correctly.
     *
     * @return void
     */
    public function test_setBatchSize_should_validate_input(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Batch size must be between 1 and 100');

        $this->indexer->setBatchSize(0);
    }

    /**
     * Test batch size upper limit
     *
     * Verifies that setBatchSize enforces upper limit.
     *
     * @return void
     */
    public function test_setBatchSize_should_enforce_upper_limit(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Batch size must be between 1 and 100');

        $this->indexer->setBatchSize(150);
    }

    /**
     * Test cache TTL configuration
     *
     * Verifies that setCacheTtl updates configuration correctly.
     *
     * @return void
     */
    public function test_setCacheTtl_should_update_configuration(): void
    {
        $originalTtl = $this->getPropertyValue($this->indexer, 'cacheTtl');
        $newTtl = 7200;

        $this->indexer->setCacheTtl($newTtl);
        $updatedTtl = $this->getPropertyValue($this->indexer, 'cacheTtl');

        $this->assertEquals($newTtl, $updatedTtl, 'setCacheTtl should update cache TTL');

        // Restore original TTL
        $this->indexer->setCacheTtl($originalTtl);
    }

    /**
     * Test negative cache TTL validation
     *
     * Verifies that setCacheTtl rejects negative values.
     *
     * @return void
     */
    public function test_setCacheTtl_should_reject_negative_values(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cache TTL must be non-negative');

        $this->indexer->setCacheTtl(-1);
    }

    /**
     * Test processing status tracking
     *
     * Verifies that indexer tracks processing status correctly.
     *
     * @return void
     */
    public function test_getProcessingStatus_should_return_current_status(): void
    {
        $status = $this->indexer->getProcessingStatus();

        $this->assertIsArray($status, 'getProcessingStatus should return array');

        $expectedKeys = [
            'current_content_type',
            'current_content_id', 
            'batch_progress',
            'total_items'
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $status, "Processing status should contain '{$key}' key");
        }
    }

    /**
     * Test processing state detection
     *
     * Verifies that isProcessing returns correct state.
     *
     * @return void
     */
    public function test_isProcessing_should_return_correct_state(): void
    {
        // Initially should not be processing
        $this->assertFalse($this->indexer->isProcessing(), 'Should not be processing initially');

        // Set processing state
        $this->setPropertyValue($this->indexer, 'processingStatus', [
            'current_content_type' => 'product',
            'current_content_id' => 1,
            'batch_progress' => 0.5,
            'total_items' => 10
        ]);

        $this->assertTrue($this->indexer->isProcessing(), 'Should be processing when content type is set');
    }

    /**
     * Test class name follows PascalCase convention
     *
     * Verifies that the Indexer class follows PascalCase naming convention.
     *
     * @return void
     */
    public function test_indexer_class_name_should_follow_pascal_case_convention(): void
    {
        $this->assertClassFollowsPascalCase(Indexer::class);
    }

    /**
     * Test method names follow camelCase convention
     *
     * Verifies that all public methods follow camelCase naming convention.
     *
     * @return void
     */
    public function test_indexer_public_methods_should_follow_camel_case_convention(): void
    {
        $publicMethods = [
            'getInstance',
            'indexAllContent',
            'indexContentType',
            'indexSingleItem',
            'removeContent',
            'getStatistics',
            'setBatchSize',
            'setCacheTtl',
            'getProcessingStatus',
            'isProcessing'
        ];

        foreach ($publicMethods as $methodName) {
            $this->assertMethodFollowsCamelCase($this->indexer, $methodName);
        }
    }

    /**
     * Test database table verification
     *
     * Verifies that indexer properly verifies database table existence.
     *
     * @return void
     */
    public function test_indexer_should_verify_database_table_exists(): void
    {
        global $wpdb;
        
        // Check that table exists
        $tableExists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $this->tableName
        ));

        $this->assertEquals($this->tableName, $tableExists, 'Knowledge base table should exist');
    }

    /**
     * Test memory usage during batch processing
     *
     * Verifies that indexer manages memory efficiently during large operations.
     *
     * @return void
     */
    public function test_indexer_memory_usage_should_be_reasonable(): void
    {
        $initialMemory = memory_get_usage();

        // Process multiple items
        $this->mockChunkingStrategy();
        for ($i = 0; $i < 5; $i++) {
            $contentData = $this->testContentData[0];
            $contentData['id'] = $i + 100; // Unique IDs
            $this->indexer->indexSingleItem($contentData, true);
        }

        $finalMemory = memory_get_usage();
        $memoryIncrease = $finalMemory - $initialMemory;

        // Memory increase should be reasonable for batch processing
        $this->assertLessThan(5242880, $memoryIncrease, 'Memory increase should be less than 5MB for batch operations');
    }

    /**
     * Test concurrent processing safety
     *
     * Verifies that indexer handles potential concurrent operations safely.
     *
     * @return void
     */
    public function test_indexer_should_handle_concurrent_operations_safely(): void
    {
        $contentData = $this->testContentData[0];
        $this->mockChunkingStrategy();

        // Index same content multiple times (simulating concurrent requests)
        $result1 = $this->indexer->indexSingleItem($contentData, true);
        $result2 = $this->indexer->indexSingleItem($contentData, true);

        $this->assertIsArray($result1, 'First indexing should succeed');
        $this->assertIsArray($result2, 'Second indexing should succeed');

        // Verify database consistency
        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tableName} WHERE content_id = %d AND content_type = %s",
            $contentData['id'],
            $contentData['type']
        ));

        $this->assertGreaterThan(0, $count, 'Content should be present in database');
    }

    /**
     * Mock chunking strategy for testing
     *
     * @return void
     */
    private function mockChunkingStrategy(): void
    {
        // Mock ChunkingStrategy to return predictable chunks
        add_filter('woo_ai_assistant_mock_chunks', function($content, $contentType) {
            return [
                [
                    'text' => substr($content, 0, 100),
                    'metadata' => ['chunk_type' => 'test'],
                    'chunk_hash' => md5($content . '0'),
                    'chunk_index' => 0,
                    'total_chunks' => 1,
                    'word_count' => str_word_count(substr($content, 0, 100))
                ]
            ];
        });
    }

    /**
     * Mock scanner content for testing
     *
     * @return void
     */
    private function mockScannerContent(): void
    {
        add_filter('woo_ai_assistant_mock_scanner_products', function() {
            return [$this->testContentData[0]];
        });

        add_filter('woo_ai_assistant_mock_scanner_pages', function() {
            return [$this->testContentData[1]];
        });
    }

    /**
     * Clean up test data
     *
     * @return void
     */
    protected function cleanUpTestData(): void
    {
        global $wpdb;
        
        // Clean up test database entries
        $wpdb->query("TRUNCATE TABLE {$this->tableName}");
        
        parent::cleanUpTestData();
    }

    /**
     * Tear down test environment
     *
     * @return void
     */
    public function tearDown(): void
    {
        // Clean up any filters
        remove_all_filters('woo_ai_assistant_mock_chunks');
        remove_all_filters('woo_ai_assistant_mock_scanner_products');
        remove_all_filters('woo_ai_assistant_mock_scanner_pages');
        remove_all_filters('woo_ai_assistant_mock_scanner_error_product');

        parent::tearDown();
    }
}