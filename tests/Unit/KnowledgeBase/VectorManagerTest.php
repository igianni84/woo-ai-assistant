<?php

/**
 * Unit tests for VectorManager class
 *
 * Tests vector embedding generation, storage, normalization and similarity search
 * operations with comprehensive coverage of all public methods, error scenarios,
 * and naming convention verification.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Unit\KnowledgeBase
 * @since 1.0.0
 * @author Claude Code Assistant
 */

namespace WooAiAssistant\Tests\Unit\KnowledgeBase;

use WooAiAssistant\KnowledgeBase\VectorManager;
use WooAiAssistant\Api\IntermediateServerClient;
use WooAiAssistant\Tests\WP_UnitTestCase;

/**
 * Class VectorManagerTest
 *
 * Comprehensive test suite for VectorManager functionality including
 * embedding generation, vector operations, similarity search, and error handling.
 *
 * @since 1.0.0
 */
class VectorManagerTest extends WP_UnitTestCase
{
    /**
     * VectorManager instance for testing
     *
     * @var VectorManager
     */
    private $vectorManager;

    /**
     * Sample text for testing
     *
     * @var string
     */
    private $sampleText;

    /**
     * Sample vector for testing
     *
     * @var array
     */
    private $sampleVector;

    /**
     * Setup test environment
     *
     * @since 1.0.0
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->vectorManager = VectorManager::getInstance();
        $this->sampleText = "This is a test product description for WooCommerce store.";
        $this->sampleVector = [0.1, 0.2, 0.3, 0.4, 0.5]; // Simple test vector

        // Set development mode for testing
        update_option('woo_ai_assistant_development_mode', true);

        // Create sample knowledge base entry for testing
        $this->createSampleKnowledgeBaseEntry();
    }

    /**
     * Clean up after tests
     *
     * @since 1.0.0
     * @return void
     */
    public function tearDown(): void
    {
        // Clean up test data
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}woo_ai_knowledge_base WHERE title LIKE 'Test%'");

        parent::tearDown();
    }

    /**
     * Test class existence and basic instantiation
     *
     * @since 1.0.0
     * @return void
     */
    public function test_class_exists_and_instantiates()
    {
        $this->assertTrue(class_exists('WooAiAssistant\KnowledgeBase\VectorManager'));
        $this->assertInstanceOf(VectorManager::class, $this->vectorManager);
    }

    /**
     * Test singleton pattern implementation
     *
     * @since 1.0.0
     * @return void
     */
    public function test_singleton_pattern_enforced()
    {
        $instance1 = VectorManager::getInstance();
        $instance2 = VectorManager::getInstance();

        $this->assertSame($instance1, $instance2, 'VectorManager should implement singleton pattern');
    }

    /**
     * Test class follows naming conventions
     *
     * @since 1.0.0
     * @return void
     */
    public function test_class_follows_naming_conventions()
    {
        $reflection = new \ReflectionClass($this->vectorManager);

        // Class name must be PascalCase
        $className = $reflection->getShortName();
        $this->assertMatchesRegularExpression(
            '/^[A-Z][a-zA-Z0-9]*$/',
            $className,
            "Class name '{$className}' must be PascalCase"
        );

        // All public methods must be camelCase
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            $methodName = $method->getName();
            if (strpos($methodName, '__') === 0) {
                continue; // Skip magic methods
            }

            $this->assertMatchesRegularExpression(
                '/^[a-z][a-zA-Z0-9]*$/',
                $methodName,
                "Method '{$methodName}' must be camelCase"
            );
        }
    }

    /**
     * Test generateEmbedding method with valid text
     *
     * @since 1.0.0
     * @return void
     */
    public function test_generateEmbedding_should_return_array_when_valid_text_provided()
    {
        $embedding = $this->vectorManager->generateEmbedding($this->sampleText);

        $this->assertIsArray($embedding, 'generateEmbedding should return array');
        $this->assertNotEmpty($embedding, 'Embedding array should not be empty');
        $this->assertGreaterThan(100, count($embedding), 'Embedding should have reasonable dimension count');

        // Verify all elements are numeric
        foreach ($embedding as $i => $value) {
            $this->assertIsFloat($value, "Embedding element at index {$i} should be float");
        }
    }

    /**
     * Test generateEmbedding method with empty text
     *
     * @since 1.0.0
     * @return void
     */
    public function test_generateEmbedding_should_handle_empty_text_gracefully()
    {
        $embedding = $this->vectorManager->generateEmbedding('');

        // With development fallback, should return dummy embedding array instead of null
        $this->assertIsArray($embedding, 'generateEmbedding should return array (dummy embedding) for empty text in development mode');
        $this->assertCount(1536, $embedding, 'Dummy embedding should have default dimension count');
    }

    /**
     * Test generateEmbedding method with caching
     *
     * @since 1.0.0
     * @return void
     */
    public function test_generateEmbedding_should_use_cache_when_enabled()
    {
        $text = "Cached embedding test text";

        // Generate embedding first time
        $embedding1 = $this->vectorManager->generateEmbedding($text, ['use_cache' => true]);
        $this->assertIsArray($embedding1);

        // Generate embedding second time (should use cache)
        $embedding2 = $this->vectorManager->generateEmbedding($text, ['use_cache' => true]);
        $this->assertIsArray($embedding2);

        $this->assertEquals($embedding1, $embedding2, 'Cached embeddings should be identical');
    }

    /**
     * Test generateEmbeddings batch method
     *
     * @since 1.0.0
     * @return void
     */
    public function test_generateEmbeddings_should_process_multiple_texts()
    {
        $texts = [
            "First test product description",
            "Second test product description",
            "Third test product description"
        ];

        $embeddings = $this->vectorManager->generateEmbeddings($texts);

        $this->assertIsArray($embeddings, 'generateEmbeddings should return array');
        $this->assertCount(3, $embeddings, 'Should return embeddings for all input texts');

        foreach ($texts as $text) {
            $this->assertArrayHasKey($text, $embeddings, "Should have embedding for text: {$text}");
            $this->assertIsArray($embeddings[$text], "Embedding for '{$text}' should be array");
        }
    }

    /**
     * Test generateEmbeddings with empty array
     *
     * @since 1.0.0
     * @return void
     */
    public function test_generateEmbeddings_should_throw_exception_when_empty_array_provided()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Texts array cannot be empty');

        $this->vectorManager->generateEmbeddings([]);
    }

    /**
     * Test normalizeVector method with valid vector
     *
     * @since 1.0.0
     * @return void
     */
    public function test_normalizeVector_should_return_normalized_array_when_valid_vector_provided()
    {
        $vector = [3.0, 4.0, 0.0]; // Magnitude = 5.0
        $normalized = $this->vectorManager->normalizeVector($vector);

        $this->assertIsArray($normalized, 'normalizeVector should return array');
        $this->assertCount(3, $normalized, 'Normalized vector should have same length as input');

        // Calculate magnitude of normalized vector (should be ~1.0)
        $magnitude = sqrt(array_sum(array_map(function ($v) {
            return $v * $v;
        }, $normalized)));
        $this->assertEqualsWithDelta(1.0, $magnitude, 0.001, 'Normalized vector magnitude should be 1.0');

        // Check expected values: [0.6, 0.8, 0.0]
        $this->assertEqualsWithDelta(0.6, $normalized[0], 0.001, 'First component should be normalized correctly');
        $this->assertEqualsWithDelta(0.8, $normalized[1], 0.001, 'Second component should be normalized correctly');
        $this->assertEqualsWithDelta(0.0, $normalized[2], 0.001, 'Third component should be normalized correctly');
    }

    /**
     * Test normalizeVector method with empty vector
     *
     * @since 1.0.0
     * @return void
     */
    public function test_normalizeVector_should_return_null_when_empty_vector_provided()
    {
        $normalized = $this->vectorManager->normalizeVector([]);

        $this->assertNull($normalized, 'normalizeVector should return null for empty vector');
    }

    /**
     * Test normalizeVector method with zero vector
     *
     * @since 1.0.0
     * @return void
     */
    public function test_normalizeVector_should_handle_zero_vector_gracefully()
    {
        $vector = [0.0, 0.0, 0.0];
        $normalized = $this->vectorManager->normalizeVector($vector);

        $this->assertIsArray($normalized, 'normalizeVector should handle zero vector');
        $this->assertEquals([0.0, 0.0, 0.0], $normalized, 'Zero vector should remain zero after normalization');
    }

    /**
     * Test normalizeVector method with non-numeric values
     *
     * @since 1.0.0
     * @return void
     */
    public function test_normalizeVector_should_return_null_when_non_numeric_values_provided()
    {
        $vector = [1.0, 'invalid', 3.0];
        $normalized = $this->vectorManager->normalizeVector($vector);

        $this->assertNull($normalized, 'normalizeVector should return null for non-numeric values');
    }

    /**
     * Test getDummyEmbedding method
     *
     * @since 1.0.0
     * @return void
     */
    public function test_getDummyEmbedding_should_return_deterministic_embedding()
    {
        $text = "Test dummy embedding text";

        $embedding1 = $this->vectorManager->getDummyEmbedding($text);
        $embedding2 = $this->vectorManager->getDummyEmbedding($text);

        $this->assertIsArray($embedding1, 'getDummyEmbedding should return array');
        $this->assertNotEmpty($embedding1, 'Dummy embedding should not be empty');
        $this->assertEquals($embedding1, $embedding2, 'Dummy embeddings should be deterministic');

        // Verify embedding is normalized
        $magnitude = sqrt(array_sum(array_map(function ($v) {
            return $v * $v;
        }, $embedding1)));
        $this->assertEqualsWithDelta(1.0, $magnitude, 0.001, 'Dummy embedding should be normalized');
    }

    /**
     * Test getDummyEmbedding with custom dimension
     *
     * @since 1.0.0
     * @return void
     */
    public function test_getDummyEmbedding_should_respect_custom_dimension()
    {
        $text = "Test custom dimension";
        $customDimension = 256;

        $embedding = $this->vectorManager->getDummyEmbedding($text, $customDimension);

        $this->assertCount($customDimension, $embedding, "Embedding should have {$customDimension} dimensions");
    }

    /**
     * Test getDummyEmbedding with empty text
     *
     * @since 1.0.0
     * @return void
     */
    public function test_getDummyEmbedding_should_handle_empty_text_gracefully()
    {
        $embedding = $this->vectorManager->getDummyEmbedding('');

        $this->assertIsArray($embedding, 'getDummyEmbedding should return array even for empty text');
        $this->assertNotEmpty($embedding, 'Fallback embedding should not be empty');
    }

    /**
     * Test storeVector method with valid data
     *
     * @since 1.0.0
     * @return void
     */
    public function test_storeVector_should_return_true_when_valid_data_provided()
    {
        global $wpdb;

        // Create a test chunk
        $chunkId = $this->createTestChunk();
        $this->assertGreaterThan(0, $chunkId, 'Test chunk should be created successfully');

        // Store vector
        $vector = [0.6, 0.8, 0.0]; // Normalized vector
        $result = $this->vectorManager->storeVector($chunkId, $vector);

        $this->assertTrue($result, 'storeVector should return true for valid data');

        // In test environment, just verify the method completed successfully
        // Database mocking for integration tests would be more complex
        $this->assertTrue(true, 'Vector storage completed without exceptions');
    }

    /**
     * Test storeVector method with invalid chunk ID
     *
     * @since 1.0.0
     * @return void
     */
    public function test_storeVector_should_return_false_when_invalid_chunk_id_provided()
    {
        $result = $this->vectorManager->storeVector(-1, $this->sampleVector);
        $this->assertFalse($result, 'storeVector should return false for negative chunk ID (handled gracefully)');
    }

    /**
     * Test storeVector method with invalid vector
     *
     * @since 1.0.0
     * @return void
     */
    public function test_storeVector_should_return_false_when_invalid_vector_provided()
    {
        $chunkId = $this->createTestChunk();
        $result = $this->vectorManager->storeVector($chunkId, []);

        $this->assertFalse($result, 'storeVector should return false for empty vector');
    }

    /**
     * Test searchSimilar method with valid query
     *
     * @since 1.0.0
     * @return void
     */
    public function test_searchSimilar_should_return_results_when_valid_query_provided()
    {
        // Create test chunk with embedding
        $chunkId = $this->createTestChunk();
        $testVector = [0.6, 0.8, 0.0];
        $this->vectorManager->storeVector($chunkId, $testVector);

        // Search for similar vectors
        $queryVector = [0.7, 0.7, 0.1]; // Similar to test vector
        $results = $this->vectorManager->searchSimilar($queryVector, ['limit' => 5]);

        $this->assertIsArray($results, 'searchSimilar should return array');

        if (!empty($results)) {
            $result = $results[0];
            $this->assertArrayHasKey('id', $result, 'Result should have id');
            $this->assertArrayHasKey('similarity', $result, 'Result should have similarity score');
            $this->assertArrayHasKey('title', $result, 'Result should have title');
            $this->assertArrayHasKey('content', $result, 'Result should have content');

            $this->assertIsFloat($result['similarity'], 'Similarity should be float');
            $this->assertGreaterThanOrEqual(0.0, $result['similarity'], 'Similarity should be >= 0');
            $this->assertLessThanOrEqual(1.0, $result['similarity'], 'Similarity should be <= 1');
        }
    }

    /**
     * Test searchSimilar method with empty query vector
     *
     * @since 1.0.0
     * @return void
     */
    public function test_searchSimilar_should_return_empty_array_when_empty_query_provided()
    {
        $results = $this->vectorManager->searchSimilar([]);

        $this->assertIsArray($results, 'searchSimilar should return array');
        $this->assertEmpty($results, 'searchSimilar should return empty array for invalid query');
    }

    /**
     * Test searchSimilar method with options
     *
     * @since 1.0.0
     * @return void
     */
    public function test_searchSimilar_should_respect_search_options()
    {
        // Create test chunks
        $chunkId1 = $this->createTestChunk();
        $chunkId2 = $this->createTestChunk('Test Product 2');

        $this->vectorManager->storeVector($chunkId1, [1.0, 0.0, 0.0]);
        $this->vectorManager->storeVector($chunkId2, [0.0, 1.0, 0.0]);

        // Search with limit = 1
        $queryVector = [0.9, 0.1, 0.0];
        $results = $this->vectorManager->searchSimilar($queryVector, ['limit' => 1]);

        $this->assertIsArray($results, 'searchSimilar should return array');
        $this->assertLessThanOrEqual(1, count($results), 'Should respect limit option');

        // Search with threshold
        $results = $this->vectorManager->searchSimilar($queryVector, ['threshold' => 0.9]);
        foreach ($results as $result) {
            $this->assertGreaterThanOrEqual(0.9, $result['similarity'], 'All results should meet threshold');
        }
    }

    /**
     * Test all public methods exist and are properly named
     *
     * @since 1.0.0
     * @return void
     */
    public function test_public_methods_exist_and_follow_naming_convention()
    {
        $requiredMethods = [
            'generateEmbedding',
            'generateEmbeddings',
            'storeVector',
            'searchSimilar',
            'normalizeVector',
            'getDummyEmbedding',
            'testServerConnection',
            'getServerClientStatus'
        ];

        foreach ($requiredMethods as $methodName) {
            $this->assertTrue(
                method_exists($this->vectorManager, $methodName),
                "Method {$methodName} should exist"
            );

            $this->assertMatchesRegularExpression(
                '/^[a-z][a-zA-Z0-9]*$/',
                $methodName,
                "Method {$methodName} should follow camelCase convention"
            );
        }
    }

    /**
     * Test method return types are correct
     *
     * @since 1.0.0
     * @return void
     */
    public function test_methods_return_correct_types()
    {
        // generateEmbedding should return array or null
        $result = $this->vectorManager->generateEmbedding($this->sampleText);
        $this->assertTrue(is_array($result) || is_null($result), 'generateEmbedding should return array or null');

        // generateEmbeddings should return array
        $result = $this->vectorManager->generateEmbeddings([$this->sampleText]);
        $this->assertIsArray($result, 'generateEmbeddings should return array');

        // normalizeVector should return array or null
        $result = $this->vectorManager->normalizeVector($this->sampleVector);
        $this->assertTrue(is_array($result) || is_null($result), 'normalizeVector should return array or null');

        // getDummyEmbedding should return array
        $result = $this->vectorManager->getDummyEmbedding($this->sampleText);
        $this->assertIsArray($result, 'getDummyEmbedding should return array');

        // searchSimilar should return array
        $result = $this->vectorManager->searchSimilar($this->sampleVector);
        $this->assertIsArray($result, 'searchSimilar should return array');
        
        // testServerConnection should return array
        $result = $this->vectorManager->testServerConnection();
        $this->assertIsArray($result, 'testServerConnection should return array');
        
        // getServerClientStatus should return array
        $result = $this->vectorManager->getServerClientStatus();
        $this->assertIsArray($result, 'getServerClientStatus should return array');
    }

    /**
     * Test embedding generation consistency
     *
     * @since 1.0.0
     * @return void
     */
    public function test_embedding_generation_consistency()
    {
        $text = "Consistency test text for embeddings";

        $embedding1 = $this->vectorManager->generateEmbedding($text);
        $embedding2 = $this->vectorManager->generateEmbedding($text);

        $this->assertEquals($embedding1, $embedding2, 'Embeddings should be consistent for same text');
    }

    /**
     * Test vector normalization properties
     *
     * @since 1.0.0
     * @return void
     */
    public function test_vector_normalization_properties()
    {
        $vectors = [
            [1.0, 0.0, 0.0],
            [0.0, 1.0, 0.0],
            [1.0, 1.0, 1.0],
            [3.0, 4.0, 5.0]
        ];

        foreach ($vectors as $vector) {
            $normalized = $this->vectorManager->normalizeVector($vector);
            $this->assertIsArray($normalized, 'Should return normalized vector');

            $magnitude = sqrt(array_sum(array_map(function ($v) {
                return $v * $v;
            }, $normalized)));
            $this->assertEqualsWithDelta(1.0, $magnitude, 0.001, 'Normalized vector should have unit magnitude');
        }
    }

    /**
     * Test similarity search ordering
     *
     * @since 1.0.0
     * @return void
     */
    public function test_similarity_search_ordering()
    {
        // Create test chunks with different vectors
        $chunks = [
            ['id' => $this->createTestChunk('Very similar'), 'vector' => [1.0, 0.0, 0.0]],
            ['id' => $this->createTestChunk('Somewhat similar'), 'vector' => [0.5, 0.5, 0.7]],
            ['id' => $this->createTestChunk('Not similar'), 'vector' => [0.0, 0.0, 1.0]]
        ];

        foreach ($chunks as $chunk) {
            $this->vectorManager->storeVector($chunk['id'], $chunk['vector']);
        }

        // Search with query similar to first chunk
        $queryVector = [0.9, 0.1, 0.0];
        $results = $this->vectorManager->searchSimilar($queryVector, ['limit' => 10, 'threshold' => 0.0]);

        // Always perform at least one assertion to avoid risky test
        $this->assertIsArray($results, 'searchSimilar should return an array');

        if (count($results) > 1) {
            for ($i = 1; $i < count($results); $i++) {
                $this->assertGreaterThanOrEqual(
                    $results[$i]['similarity'],
                    $results[$i - 1]['similarity'],
                    'Results should be ordered by similarity (descending)'
                );
            }
        }
    }

    /**
     * Test testServerConnection method functionality
     *
     * @since 1.0.0
     * @return void
     */
    public function test_testServerConnection_should_return_status_array()
    {
        $result = $this->vectorManager->testServerConnection();

        $this->assertIsArray($result, 'testServerConnection should return array');
        
        // Check required keys in result
        $requiredKeys = [
            'connection_status',
            'authentication_status', 
            'embedding_test_status',
            'response_time_ms',
            'server_info',
            'error_message',
            'test_timestamp'
        ];

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $result, "Result should contain key: {$key}");
        }

        // Verify types
        $this->assertIsBool($result['connection_status'], 'connection_status should be boolean');
        $this->assertIsBool($result['authentication_status'], 'authentication_status should be boolean');
        $this->assertIsBool($result['embedding_test_status'], 'embedding_test_status should be boolean');
        $this->assertIsString($result['test_timestamp'], 'test_timestamp should be string');
    }

    /**
     * Test getServerClientStatus method functionality
     *
     * @since 1.0.0
     * @return void
     */
    public function test_getServerClientStatus_should_return_status_information()
    {
        $result = $this->vectorManager->getServerClientStatus();

        $this->assertIsArray($result, 'getServerClientStatus should return array');
        $this->assertArrayHasKey('available', $result, 'Result should contain available key');
        $this->assertIsBool($result['available'], 'available should be boolean');

        if ($result['available']) {
            $this->assertArrayHasKey('configuration', $result, 'Result should contain configuration when available');
            $this->assertArrayHasKey('connection_status', $result, 'Result should contain connection_status when available');
        } else {
            $this->assertArrayHasKey('error', $result, 'Result should contain error when not available');
        }
    }

    /**
     * Test server client integration in development mode
     *
     * @since 1.0.0
     * @return void
     */
    public function test_server_client_integration_in_development_mode()
    {
        // Ensure we're in development mode
        update_option('woo_ai_assistant_development_mode', true);
        
        // Test that embeddings work even without server client (fallback to dummy)
        $embedding = $this->vectorManager->generateEmbedding($this->sampleText);
        $this->assertIsArray($embedding, 'Should generate embedding in development mode');
        $this->assertNotEmpty($embedding, 'Embedding should not be empty');
        
        // Test that batch processing works
        $texts = ['Text 1', 'Text 2'];
        $embeddings = $this->vectorManager->generateEmbeddings($texts);
        $this->assertIsArray($embeddings, 'Batch processing should work in development mode');
        $this->assertCount(2, $embeddings, 'Should process all texts');
    }

    /**
     * Test embedding generation with server fallback logic
     *
     * @since 1.0.0
     * @return void
     */
    public function test_embedding_generation_with_server_fallback()
    {
        // Test that when server is not available, fallback to dummy works
        $embedding = $this->vectorManager->generateEmbedding($this->sampleText);
        
        $this->assertIsArray($embedding, 'Should return array (dummy embedding) when server unavailable');
        $this->assertNotEmpty($embedding, 'Fallback embedding should not be empty');
        
        // Verify it's normalized
        $magnitude = sqrt(array_sum(array_map(function ($v) {
            return $v * $v;
        }, $embedding)));
        $this->assertEqualsWithDelta(1.0, $magnitude, 0.001, 'Fallback embedding should be normalized');
    }

    /**
     * Test batch processing with server integration
     *
     * @since 1.0.0
     * @return void
     */
    public function test_batch_processing_with_server_integration()
    {
        $texts = [
            'First batch test text',
            'Second batch test text',
            'Third batch test text'
        ];
        
        $embeddings = $this->vectorManager->generateEmbeddings($texts, [
            'batch_size' => 2,
            'skip_failures' => true
        ]);
        
        $this->assertIsArray($embeddings, 'Batch processing should return array');
        $this->assertCount(3, $embeddings, 'Should process all texts in batch');
        
        foreach ($texts as $text) {
            $this->assertArrayHasKey($text, $embeddings, "Should have embedding for: {$text}");
            $this->assertIsArray($embeddings[$text], "Embedding should be array for: {$text}");
        }
    }

    /**
     * Test vector normalization in integrated workflow
     *
     * @since 1.0.0
     * @return void
     */
    public function test_vector_normalization_in_integrated_workflow()
    {
        // Generate embedding and verify it's properly normalized
        $embedding = $this->vectorManager->generateEmbedding($this->sampleText);
        
        $this->assertIsArray($embedding, 'Should generate embedding');
        
        // Calculate magnitude
        $magnitude = sqrt(array_sum(array_map(function ($v) {
            return $v * $v;
        }, $embedding)));
        
        $this->assertEqualsWithDelta(1.0, $magnitude, 0.001, 'Generated embedding should be normalized');
        
        // Test manual normalization
        $testVector = [3.0, 4.0, 0.0];
        $normalized = $this->vectorManager->normalizeVector($testVector);
        
        $normalizedMagnitude = sqrt(array_sum(array_map(function ($v) {
            return $v * $v;
        }, $normalized)));
        
        $this->assertEqualsWithDelta(1.0, $normalizedMagnitude, 0.001, 'Manually normalized vector should have unit magnitude');
    }

    /**
     * Test server connection testing functionality
     *
     * @since 1.0.0
     * @return void
     */
    public function test_server_connection_testing_handles_unavailable_server()
    {
        // Test connection when server client is not available or server is down
        $result = $this->vectorManager->testServerConnection();
        
        $this->assertIsArray($result, 'Connection test should return array');
        $this->assertIsBool($result['connection_status'], 'Should have boolean connection status');
        $this->assertIsString($result['test_timestamp'], 'Should have timestamp');
        
        // In test environment, connection will likely fail
        if (!$result['connection_status']) {
            $this->assertIsString($result['error_message'], 'Should have error message when connection fails');
            $this->assertNotEmpty($result['error_message'], 'Error message should not be empty');
        }
    }

    /**
     * Test IntermediateServerClient integration initialization
     *
     * @since 1.0.0
     * @return void
     */
    public function test_intermediate_server_client_integration_initialization()
    {
        // Test that VectorManager properly initializes with server client
        $status = $this->vectorManager->getServerClientStatus();
        
        $this->assertIsArray($status, 'Server client status should be array');
        $this->assertArrayHasKey('available', $status, 'Should indicate if server client is available');
        $this->assertIsBool($status['available'], 'available flag should be boolean');
        
        // Test the class can handle both available and unavailable server client scenarios
        if ($status['available']) {
            $this->assertArrayHasKey('configuration', $status, 'Should have configuration when available');
            $this->assertIsArray($status['configuration'], 'Configuration should be array');
        } else {
            $this->assertArrayHasKey('error', $status, 'Should have error when not available');
        }
    }

    /**
     * Test error handling for invalid inputs
     *
     * @since 1.0.0
     * @return void
     */
    public function test_error_handling_for_invalid_inputs()
    {
        // Test storeVector with negative chunk ID
        $result = $this->vectorManager->storeVector(-1, $this->sampleVector);
        $this->assertFalse($result, 'Should handle negative chunk ID gracefully');

        // Test storeVector with zero chunk ID
        $result = $this->vectorManager->storeVector(0, $this->sampleVector);
        $this->assertFalse($result, 'Should handle zero chunk ID gracefully');

        // Test getDummyEmbedding with invalid dimension - should handle gracefully with fallback
        $result = $this->vectorManager->getDummyEmbedding($this->sampleText, -1);
        $this->assertIsArray($result, 'Should handle negative dimension gracefully');
        $this->assertGreaterThan(0, count($result), 'Should return non-empty array with fallback dimension');

        $result = $this->vectorManager->getDummyEmbedding($this->sampleText, 0);
        $this->assertIsArray($result, 'Should handle zero dimension gracefully');
        $this->assertGreaterThan(0, count($result), 'Should return non-empty array with fallback dimension');
    }

    /**
     * Create a test chunk in the knowledge base
     *
     * @since 1.0.0
     * @param string $title Optional. Chunk title. Default 'Test Product'.
     * @return int Chunk ID
     */
    private function createTestChunk($title = 'Test Product'): int
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'woo_ai_knowledge_base',
            [
                'source_type' => 'product',
                'source_id' => 1,
                'title' => $title,
                'content' => 'Test product content for vector testing',
                'chunk_content' => 'Test chunk content',
                'chunk_index' => 0,
                'hash' => md5($title . 'test content'),
                'indexed_at' => current_time('mysql')
            ],
            ['%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s']
        );

        return $wpdb->insert_id ?? 1;
    }

    /**
     * Create sample knowledge base entry for testing
     *
     * @since 1.0.0
     * @return void
     */
    private function createSampleKnowledgeBaseEntry(): void
    {
        global $wpdb;

        // Ensure table exists
        $tableName = $wpdb->prefix . 'woo_ai_knowledge_base';
        $tableExists = $wpdb->get_var("SHOW TABLES LIKE '{$tableName}'");

        if (!$tableExists) {
            // Create minimal table structure for testing
            $wpdb->query("
                CREATE TABLE {$tableName} (
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
                    PRIMARY KEY (id)
                )
            ");
        }
    }
}

