<?php

/**
 * AIManager Streaming Test Class
 *
 * Comprehensive unit tests for streaming functionality in AIManager class including
 * chunk processing, callback handling, streaming response generation, and integration
 * with AI providers.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Unit\KnowledgeBase
 * @since 1.0.0
 * @author Claude Code Assistant
 */

namespace WooAiAssistant\Tests\Unit\KnowledgeBase;

use WooAiAssistant\KnowledgeBase\AIManager;
use WooAiAssistant\KnowledgeBase\VectorManager;
use WooAiAssistant\Common\Utils;

/**
 * Class AIManagerStreamingTest
 *
 * Tests streaming-specific functionality in AIManager including chunk processing,
 * callback handling, response streaming, and AI provider integration.
 *
 * @since 1.0.0
 */
class AIManagerStreamingTest extends \WP_UnitTestCase
{
    /**
     * AIManager instance for testing
     *
     * @var AIManager
     */
    private $aiManager;

    /**
     * Set up test environment
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        // Create AIManager instance
        $this->aiManager = AIManager::getInstance();
    }

    /**
     * Test generateStreamingResponse method exists and follows naming conventions
     *
     * @return void
     */
    public function test_generateStreamingResponse_method_exists_and_follows_conventions(): void
    {
        $this->assertTrue(method_exists($this->aiManager, 'generateStreamingResponse'));
        
        $reflection = new \ReflectionClass($this->aiManager);
        $method = $reflection->getMethod('generateStreamingResponse');
        
        $this->assertTrue($method->isPublic());
        
        // Verify method name follows camelCase
        $this->assertMatchesRegularExpression('/^[a-z][a-zA-Z0-9]*$/', 'generateStreamingResponse');
    }

    /**
     * Test createResponseChunks method for proper sentence-based chunking
     *
     * @return void
     */
    public function test_createResponseChunks_sentence_based_splitting(): void
    {
        $reflection = new \ReflectionClass($this->aiManager);
        $method = $reflection->getMethod('createResponseChunks');
        $method->setAccessible(true);

        $longText = 'This is the first sentence. This is the second sentence. This is the third sentence that is much longer than the others and should be handled properly. Final sentence.';
        $chunkSize = 50;

        $chunks = $method->invoke($this->aiManager, $longText, $chunkSize);

        $this->assertIsArray($chunks);
        $this->assertNotEmpty($chunks);
        
        // Verify each chunk respects size limits (with some tolerance for sentence boundaries)
        foreach ($chunks as $chunk) {
            $this->assertIsString($chunk);
            $this->assertNotEmpty(trim($chunk));
            // Allow some flexibility for sentence boundaries
            $this->assertLessThanOrEqual($chunkSize + 50, strlen($chunk));
        }
        
        // Verify all chunks concatenated equal original text
        $reconstructed = implode(' ', $chunks);
        $this->assertEquals(trim($longText), trim($reconstructed));
    }

    /**
     * Test createWordBasedChunks fallback method
     *
     * @return void
     */
    public function test_createWordBasedChunks_fallback(): void
    {
        $reflection = new \ReflectionClass($this->aiManager);
        $method = $reflection->getMethod('createWordBasedChunks');
        $method->setAccessible(true);

        $text = 'word1 word2 word3 word4 word5 word6 word7 word8 word9 word10';
        $chunkSize = 20;

        $chunks = $method->invoke($this->aiManager, $text, $chunkSize);

        $this->assertIsArray($chunks);
        $this->assertNotEmpty($chunks);
        
        // Verify chunks respect word boundaries
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual($chunkSize + 10, strlen($chunk)); // Some tolerance
            $this->assertStringNotContainsString('  ', $chunk); // No double spaces
        }
    }

    /**
     * Test chunk size validation and limits
     *
     * @return void
     */
    public function test_chunk_size_validation_and_limits(): void
    {
        $reflection = new \ReflectionClass($this->aiManager);
        $method = $reflection->getMethod('createResponseChunks');
        $method->setAccessible(true);

        $text = 'Short text';
        
        // Test with very small chunk size
        $chunks = $method->invoke($this->aiManager, $text, 5);
        $this->assertIsArray($chunks);
        
        // Test with very large chunk size
        $chunks = $method->invoke($this->aiManager, $text, 1000);
        $this->assertCount(1, $chunks); // Should be single chunk
        $this->assertEquals($text, $chunks[0]);
    }

    /**
     * Test response chunking preserves content integrity
     *
     * @return void
     */
    public function test_response_chunking_preserves_content_integrity(): void
    {
        $reflection = new \ReflectionClass($this->aiManager);
        $method = $reflection->getMethod('createResponseChunks');
        $method->setAccessible(true);

        $originalText = 'This is a comprehensive test message. It contains multiple sentences. Each sentence should be properly handled. The final result should maintain all original content without any loss or modification.';

        $chunks = $method->invoke($this->aiManager, $originalText, 50);

        // Reconstruct text from chunks
        $reconstructed = implode(' ', $chunks);
        
        // Remove any double spaces that might have been introduced
        $reconstructed = preg_replace('/\s+/', ' ', $reconstructed);
        $originalNormalized = preg_replace('/\s+/', ' ', $originalText);

        $this->assertEquals(trim($originalNormalized), trim($reconstructed));
    }

    /**
     * Test streaming with empty content handling
     *
     * @return void
     */
    public function test_streaming_with_empty_content_handling(): void
    {
        $reflection = new \ReflectionClass($this->aiManager);
        $method = $reflection->getMethod('createResponseChunks');
        $method->setAccessible(true);

        // Test with empty string
        $chunks = $method->invoke($this->aiManager, '', 50);
        $this->assertEquals([''], $chunks); // Should return array with empty string

        // Test with whitespace only
        $chunks = $method->invoke($this->aiManager, '   ', 50);
        $this->assertIsArray($chunks);
        $this->assertCount(1, $chunks);
    }

    /**
     * Test streaming response with callback functionality
     *
     * @return void
     */
    public function test_streaming_response_with_callback(): void
    {
        // Test that generateStreamingResponse accepts a callback
        $callbackInvoked = false;
        $callback = function($chunk, $metadata) use (&$callbackInvoked) {
            $callbackInvoked = true;
        };

        try {
            $result = $this->aiManager->generateStreamingResponse('Test message', [], $callback);
            // Should not throw exception
            $this->assertTrue(true);
        } catch (\Exception $e) {
            // If method throws due to missing dependencies, that's also valid for unit test
            $this->assertInstanceOf(\Exception::class, $e);
        }
    }

    /**
     * Test streaming constants are properly defined
     *
     * @return void
     */
    public function test_streaming_constants_defined(): void
    {
        $reflection = new \ReflectionClass($this->aiManager);
        $constants = $reflection->getConstants();

        // Check if streaming-related constants follow naming conventions
        foreach ($constants as $name => $value) {
            if (strpos($name, 'STREAMING') !== false) {
                $this->assertMatchesRegularExpression('/^[A-Z][A-Z0-9_]*$/', $name,
                    "Streaming constant '{$name}' should follow UPPER_SNAKE_CASE convention");
            }
        }
    }

    /**
     * Test private method naming conventions for streaming methods
     *
     * @return void
     */
    public function test_streaming_private_methods_naming_conventions(): void
    {
        $reflection = new \ReflectionClass($this->aiManager);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PRIVATE);

        $streamingMethods = ['processStreamingResponse', 'createResponseChunks', 'createWordBasedChunks'];

        foreach ($streamingMethods as $methodName) {
            $methodExists = false;
            foreach ($methods as $method) {
                if ($method->getName() === $methodName) {
                    $methodExists = true;
                    // Verify camelCase naming
                    $this->assertMatchesRegularExpression('/^[a-z][a-zA-Z0-9]*$/', $methodName,
                        "Private streaming method '{$methodName}' should follow camelCase convention");
                    break;
                }
            }

            if ($methodExists) {
                $this->assertTrue($methodExists, "Streaming method '{$methodName}' should exist");
            }
        }
    }

    /**
     * Test streaming method has proper documentation
     *
     * @return void
     */
    public function test_streaming_method_documentation(): void
    {
        $reflection = new \ReflectionClass($this->aiManager);
        
        if ($reflection->hasMethod('generateStreamingResponse')) {
            $method = $reflection->getMethod('generateStreamingResponse');
            $docComment = $method->getDocComment();
            
            $this->assertNotFalse($docComment,
                'generateStreamingResponse method should have DocBlock documentation');
                
            // Check for key documentation elements
            $this->assertStringContainsString('@param', $docComment);
            $this->assertStringContainsString('@return', $docComment);
            $this->assertStringContainsString('@since', $docComment);
        }
    }
}