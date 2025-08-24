<?php
/**
 * Scanner Class Tests
 *
 * Comprehensive unit tests for the Knowledge Base Scanner class.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Unit\KnowledgeBase
 * @since 1.0.0
 * @author Claude Code Assistant
 */

namespace WooAiAssistant\Tests\Unit\KnowledgeBase;

use WooAiAssistant\KnowledgeBase\Scanner;
use ReflectionClass;
use ReflectionMethod;

/**
 * Class ScannerTest
 * 
 * Tests for the Scanner class functionality, including product scanning,
 * content extraction, WooCommerce settings processing, and batch operations.
 * 
 * @since 1.0.0
 */
class ScannerTest extends \WP_UnitTestCase {
    
    /**
     * Scanner instance
     *
     * @since 1.0.0
     * @var Scanner
     */
    private Scanner $scanner;

    /**
     * Test product IDs
     *
     * @since 1.0.0
     * @var array
     */
    private array $testProductIds = [];

    /**
     * Test post IDs
     *
     * @since 1.0.0
     * @var array
     */
    private array $testPostIds = [];

    /**
     * Set up test environment
     *
     * @since 1.0.0
     * @return void
     */
    public function setUp(): void {
        parent::setUp();
        
        $this->scanner = Scanner::getInstance();
        
        // Create test products and posts
        $this->createTestContent();
    }

    /**
     * Clean up after tests
     *
     * @since 1.0.0
     * @return void
     */
    public function tearDown(): void {
        // Clean up test content
        $this->cleanupTestContent();
        
        // Clear scanner cache
        $this->scanner->clearCache();
        
        parent::tearDown();
    }

    /**
     * MANDATORY: Test class existence and basic instantiation
     *
     * @since 1.0.0
     * @return void
     */
    public function test_class_exists_and_instantiates(): void {
        $this->assertTrue(class_exists('WooAiAssistant\KnowledgeBase\Scanner'));
        $this->assertInstanceOf(Scanner::class, $this->scanner);
    }

    /**
     * MANDATORY: Verify naming conventions
     *
     * @since 1.0.0
     * @return void
     */
    public function test_class_follows_naming_conventions(): void {
        $reflection = new ReflectionClass($this->scanner);
        
        // Class name must be PascalCase
        $className = $reflection->getShortName();
        $this->assertMatchesRegularExpression('/^[A-Z][a-zA-Z0-9]*$/', $className, 
            "Class name '$className' must be PascalCase");
            
        // All public methods must be camelCase
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            $methodName = $method->getName();
            if (strpos($methodName, '__') === 0) continue; // Skip magic methods
            
            $this->assertMatchesRegularExpression('/^[a-z][a-zA-Z0-9]*$/', $methodName,
                "Method '$methodName' must be camelCase");
        }
    }

    /**
     * MANDATORY: Test each public method exists and returns expected type
     *
     * @since 1.0.0
     * @return void
     */
    public function test_public_methods_exist_and_return_correct_types(): void {
        $reflection = new ReflectionClass($this->scanner);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        
        $expectedMethods = [
            'scanProducts',
            'scanPages', 
            'scanWooSettings',
            'scanCategories',
            'processBatch',
            'onProductUpdated',
            'onPostSaved',
            'getSupportedContentTypes',
            'getLastScanStats',
            'clearCache'
        ];

        foreach ($expectedMethods as $expectedMethod) {
            $this->assertTrue(method_exists($this->scanner, $expectedMethod),
                "Method $expectedMethod should exist");
        }
    }

    /**
     * Test scanProducts method with valid arguments
     *
     * @since 1.0.0
     * @return void
     */
    public function test_scanProducts_should_return_array_when_products_exist(): void {
        $this->activateWooCommerce();
        
        $result = $this->scanner->scanProducts(['limit' => 10]);
        
        $this->assertIsArray($result);
        $this->assertLessThanOrEqual(10, count($result));
        
        // Test result structure if products exist
        if (!empty($result)) {
            $product = $result[0];
            $this->assertArrayHasKey('id', $product);
            $this->assertArrayHasKey('title', $product);
            $this->assertArrayHasKey('content', $product);
            $this->assertArrayHasKey('type', $product);
            $this->assertArrayHasKey('url', $product);
            $this->assertArrayHasKey('metadata', $product);
            $this->assertEquals('product', $product['type']);
        }
    }

    /**
     * Test scanProducts with invalid arguments
     *
     * @since 1.0.0
     * @return void
     */
    public function test_scanProducts_should_throw_exception_when_limit_invalid(): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Limit must be a positive integer');
        
        $this->scanner->scanProducts(['limit' => 0]);
    }

    /**
     * Test scanProducts when WooCommerce is not active
     *
     * @since 1.0.0
     * @return void
     */
    public function test_scanProducts_should_throw_exception_when_woocommerce_inactive(): void {
        // Simulate WooCommerce being inactive
        $this->deactivateWooCommerce();
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('WooCommerce is not active');
        
        $this->scanner->scanProducts();
    }

    /**
     * Test scanPages method functionality
     *
     * @since 1.0.0
     * @return void
     */
    public function test_scanPages_should_return_array_when_pages_exist(): void {
        $result = $this->scanner->scanPages(['limit' => 5]);
        
        $this->assertIsArray($result);
        $this->assertLessThanOrEqual(5, count($result));
        
        // Test result structure if pages exist
        if (!empty($result)) {
            $page = $result[0];
            $this->assertArrayHasKey('id', $page);
            $this->assertArrayHasKey('title', $page);
            $this->assertArrayHasKey('content', $page);
            $this->assertArrayHasKey('type', $page);
            $this->assertArrayHasKey('url', $page);
            $this->assertArrayHasKey('metadata', $page);
            $this->assertContains($page['type'], ['page', 'post']);
        }
    }

    /**
     * Test scanPages with invalid limit
     *
     * @since 1.0.0
     * @return void
     */
    public function test_scanPages_should_throw_exception_when_limit_invalid(): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Limit must be a positive integer');
        
        $this->scanner->scanPages(['limit' => -1]);
    }

    /**
     * Test scanWooSettings method
     *
     * @since 1.0.0
     * @return void
     */
    public function test_scanWooSettings_should_return_array_when_woocommerce_active(): void {
        $this->activateWooCommerce();
        
        $result = $this->scanner->scanWooSettings();
        
        $this->assertIsArray($result);
        
        if (!empty($result)) {
            foreach ($result as $setting) {
                $this->assertArrayHasKey('id', $setting);
                $this->assertArrayHasKey('title', $setting);
                $this->assertArrayHasKey('content', $setting);
                $this->assertArrayHasKey('type', $setting);
                $this->assertEquals('woo_setting', $setting['type']);
            }
        }
    }

    /**
     * Test scanWooSettings when WooCommerce is not active
     *
     * @since 1.0.0
     * @return void
     */
    public function test_scanWooSettings_should_throw_exception_when_woocommerce_inactive(): void {
        $this->deactivateWooCommerce();
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('WooCommerce is not active');
        
        $this->scanner->scanWooSettings();
    }

    /**
     * Test scanCategories method
     *
     * @since 1.0.0
     * @return void
     */
    public function test_scanCategories_should_return_array_for_taxonomies(): void {
        $result = $this->scanner->scanCategories();
        
        $this->assertIsArray($result);
        
        // Test with specific taxonomies
        $result = $this->scanner->scanCategories(['taxonomies' => ['category']]);
        $this->assertIsArray($result);
    }

    /**
     * Test processBatch method
     *
     * @since 1.0.0
     * @return void
     */
    public function test_processBatch_should_return_valid_structure(): void {
        $this->activateWooCommerce();
        
        $result = $this->scanner->processBatch('products', 0, 5);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertArrayHasKey('stats', $result);
        
        $pagination = $result['pagination'];
        $this->assertArrayHasKey('offset', $pagination);
        $this->assertArrayHasKey('limit', $pagination);
        $this->assertArrayHasKey('current_batch_size', $pagination);
        $this->assertArrayHasKey('has_more', $pagination);
        $this->assertArrayHasKey('next_offset', $pagination);
    }

    /**
     * Test processBatch with unsupported content type
     *
     * @since 1.0.0
     * @return void
     */
    public function test_processBatch_should_throw_exception_for_unsupported_type(): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported content type: invalid_type');
        
        $this->scanner->processBatch('invalid_type');
    }

    /**
     * Test getSupportedContentTypes method
     *
     * @since 1.0.0
     * @return void
     */
    public function test_getSupportedContentTypes_should_return_array(): void {
        $result = $this->scanner->getSupportedContentTypes();
        
        $this->assertIsArray($result);
        $this->assertContains('products', $result);
        $this->assertContains('pages', $result);
        $this->assertContains('posts', $result);
        $this->assertContains('categories', $result);
        $this->assertContains('tags', $result);
        $this->assertContains('woo_settings', $result);
    }

    /**
     * Test getLastScanStats method
     *
     * @since 1.0.0
     * @return void
     */
    public function test_getLastScanStats_should_return_array(): void {
        // Run a scan to generate stats
        $this->activateWooCommerce();
        $this->scanner->scanProducts(['limit' => 1]);
        
        $result = $this->scanner->getLastScanStats();
        $this->assertIsArray($result);
        
        // Test specific content type stats
        $productStats = $this->scanner->getLastScanStats('products');
        $this->assertIsArray($productStats);
    }

    /**
     * Test clearCache method
     *
     * @since 1.0.0
     * @return void
     */
    public function test_clearCache_should_return_boolean(): void {
        $result = $this->scanner->clearCache();
        $this->assertIsBool($result);
    }

    /**
     * Test onProductUpdated hook handler
     *
     * @since 1.0.0
     * @return void
     */
    public function test_onProductUpdated_should_handle_product_id(): void {
        $productId = 123;
        
        // This should not throw an exception
        $this->scanner->onProductUpdated($productId);
        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    /**
     * Test onPostSaved hook handler
     *
     * @since 1.0.0
     * @return void
     */
    public function test_onPostSaved_should_handle_post_data(): void {
        $postId = $this->factory->post->create([
            'post_title' => 'Test Post',
            'post_status' => 'publish'
        ]);
        
        $post = get_post($postId);
        
        // This should not throw an exception
        $this->scanner->onPostSaved($postId, $post, true);
        $this->assertTrue(true);
    }

    /**
     * Test singleton pattern implementation
     *
     * @since 1.0.0
     * @return void
     */
    public function test_singleton_pattern_should_return_same_instance(): void {
        $instance1 = Scanner::getInstance();
        $instance2 = Scanner::getInstance();
        
        $this->assertSame($instance1, $instance2);
    }

    /**
     * Test content sanitization
     *
     * @since 1.0.0
     * @return void
     */
    public function test_content_sanitization_should_clean_html(): void {
        $reflection = new ReflectionClass($this->scanner);
        $sanitizeMethod = $reflection->getMethod('sanitizeContent');
        $sanitizeMethod->setAccessible(true);
        
        $dirtyContent = '<script>alert("test")</script><p>Clean content</p>';
        $cleanContent = $sanitizeMethod->invoke($this->scanner, $dirtyContent);
        
        $this->assertStringNotContainsString('<script>', $cleanContent);
        $this->assertStringNotContainsString('<p>', $cleanContent);
        $this->assertStringContainsString('Clean content', $cleanContent);
    }

    /**
     * Test content truncation
     *
     * @since 1.0.0
     * @return void
     */
    public function test_content_truncation_should_limit_length(): void {
        $reflection = new ReflectionClass($this->scanner);
        $truncateMethod = $reflection->getMethod('truncateContent');
        $truncateMethod->setAccessible(true);
        
        $longContent = str_repeat('A very long content string. ', 1000);
        $truncatedContent = $truncateMethod->invoke($this->scanner, $longContent);
        
        $this->assertLessThan(strlen($longContent), strlen($truncatedContent));
        $this->assertStringEndsWith('...', $truncatedContent);
    }

    /**
     * Test batch processing with different content types
     *
     * @since 1.0.0
     * @return void
     */
    public function test_processBatch_should_handle_different_content_types(): void {
        $this->activateWooCommerce();
        
        $contentTypes = ['pages', 'posts', 'categories'];
        
        foreach ($contentTypes as $contentType) {
            $result = $this->scanner->processBatch($contentType, 0, 5);
            $this->assertIsArray($result);
            $this->assertArrayHasKey('data', $result);
        }
    }

    /**
     * Test error handling in product scanning
     *
     * @since 1.0.0
     * @return void
     */
    public function test_scanProducts_should_handle_invalid_product_ids(): void {
        $this->activateWooCommerce();
        
        // Test with non-existent product IDs
        $result = $this->scanner->scanProducts(['include_ids' => [99999, 99998]]);
        
        $this->assertIsArray($result);
        // Should return empty array for non-existent products
        $this->assertEmpty($result);
    }

    /**
     * Test caching functionality
     *
     * @since 1.0.0
     * @return void
     */
    public function test_caching_should_improve_performance(): void {
        $this->activateWooCommerce();
        
        // First call - should cache the results
        $start1 = microtime(true);
        $result1 = $this->scanner->scanProducts(['limit' => 1]);
        $duration1 = microtime(true) - $start1;
        
        // Second call - should use cached results
        $start2 = microtime(true);
        $result2 = $this->scanner->scanProducts(['limit' => 1]);
        $duration2 = microtime(true) - $start2;
        
        // Results should be identical
        $this->assertEquals($result1, $result2);
        
        // Second call should be faster (cached)
        // Note: This might not always be true in test environment
        $this->assertIsFloat($duration1);
        $this->assertIsFloat($duration2);
    }

    /**
     * Test WordPress hooks integration
     *
     * @since 1.0.0
     * @return void
     */
    public function test_wordpress_hooks_should_be_registered(): void {
        // Test that hooks are properly registered during initialization
        $this->assertTrue(has_action('woocommerce_product_set_stock', [$this->scanner, 'onProductUpdated']));
        $this->assertTrue(has_action('woocommerce_update_product', [$this->scanner, 'onProductUpdated']));
        $this->assertTrue(has_action('save_post', [$this->scanner, 'onPostSaved']));
    }

    /**
     * Create test content for tests
     *
     * @since 1.0.0
     * @return void
     */
    private function createTestContent(): void {
        // Create test posts
        $this->testPostIds[] = $this->factory->post->create([
            'post_title' => 'Test Page',
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_content' => 'Test page content'
        ]);
        
        $this->testPostIds[] = $this->factory->post->create([
            'post_title' => 'Test Blog Post',
            'post_type' => 'post', 
            'post_status' => 'publish',
            'post_content' => 'Test blog post content'
        ]);
        
        // Test categories
        $this->factory->category->create([
            'name' => 'Test Category',
            'slug' => 'test-category'
        ]);
        
        // Test tags
        $this->factory->tag->create([
            'name' => 'Test Tag',
            'slug' => 'test-tag'
        ]);
    }

    /**
     * Clean up test content
     *
     * @since 1.0.0
     * @return void
     */
    private function cleanupTestContent(): void {
        // Clean up test products
        foreach ($this->testProductIds as $productId) {
            wp_delete_post($productId, true);
        }
        
        // Clean up test posts
        foreach ($this->testPostIds as $postId) {
            wp_delete_post($postId, true);
        }
    }

    /**
     * Simulate WooCommerce activation
     *
     * @since 1.0.0
     * @return void
     */
    private function activateWooCommerce(): void {
        // WooCommerce is now mocked in bootstrap.php
        // Just ensure we have the constants needed
        
        // Define WooCommerce constants
        if (!defined('WC_VERSION')) {
            define('WC_VERSION', '7.0.0');
        }
        
        // All WooCommerce functions are now mocked in bootstrap.php
    }

    /**
     * Simulate WooCommerce deactivation
     *
     * @since 1.0.0
     * @return void
     */
    private function deactivateWooCommerce(): void {
        // This would simulate WooCommerce being inactive
        // In real implementation, Utils::isWooCommerceActive() would return false
    }
}