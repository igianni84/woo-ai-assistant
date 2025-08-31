<?php

/**
 * Tests for Knowledge Base Scanner Class
 *
 * Comprehensive unit tests for the Scanner class that handles content scanning
 * and indexing for the AI knowledge base. Tests all scanning methods, caching,
 * error handling, and integration with WooCommerce.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Unit\KnowledgeBase
 * @since 1.0.0
 * @author Claude Code Assistant
 */

namespace WooAiAssistant\Tests\Unit\KnowledgeBase;

use WooAiAssistant\Tests\Unit\WooAiBaseTestCase;
use WooAiAssistant\KnowledgeBase\Scanner;
use WooAiAssistant\Common\Utils;
use WooAiAssistant\Common\Cache;
use WP_Query;
use Exception;

/**
 * Class ScannerTest
 *
 * Test cases for the Knowledge Base Scanner class.
 * Verifies content scanning, processing, caching, and error handling.
 *
 * @since 1.0.0
 */
class ScannerTest extends WooAiBaseTestCase
{
    /**
     * Scanner instance
     *
     * @var Scanner
     */
    private $scanner;

    /**
     * Mock WooCommerce products for testing
     *
     * @var array
     */
    private $mockProducts = [];

    /**
     * Mock pages for testing
     *
     * @var array
     */
    private $mockPages = [];

    /**
     * Set up test environment
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        
        if (!Utils::isWooCommerceActive()) {
            $this->markTestSkipped('WooCommerce is required for Scanner tests');
        }

        $this->scanner = Scanner::getInstance();
        $this->createMockContent();
    }

    /**
     * Create mock content for testing
     *
     * @return void
     */
    protected function createMockContent(): void
    {
        // Create test products
        $this->mockProducts[] = $this->createTestProduct([
            'name' => 'Test Product 1',
            'description' => 'This is a test product for scanning',
            'short_description' => 'Short description',
            'status' => 'publish',
            'price' => '29.99',
            'sku' => 'TEST-001'
        ]);

        $this->mockProducts[] = $this->createTestProduct([
            'name' => 'Test Product 2',
            'description' => 'Another test product with different content',
            'short_description' => 'Another short description',
            'status' => 'publish',
            'price' => '49.99',
            'sku' => 'TEST-002',
            'type' => 'variable'
        ]);

        // Create test pages
        $this->mockPages[] = $this->factory->post->create([
            'post_type' => 'page',
            'post_title' => 'Test Page 1',
            'post_content' => 'This is test page content for scanning',
            'post_status' => 'publish'
        ]);

        $this->mockPages[] = $this->factory->post->create([
            'post_type' => 'page',
            'post_title' => 'Privacy Policy',
            'post_content' => 'Privacy policy content for testing',
            'post_status' => 'publish'
        ]);
    }

    /**
     * Test scanner singleton pattern
     *
     * Verifies that Scanner class follows singleton pattern correctly.
     *
     * @return void
     */
    public function test_getInstance_should_return_singleton_instance(): void
    {
        $instance1 = Scanner::getInstance();
        $instance2 = Scanner::getInstance();

        $this->assertInstanceOf(Scanner::class, $instance1);
        $this->assertSame($instance1, $instance2, 'getInstance should return the same instance (singleton pattern)');
    }

    /**
     * Test successful product scanning
     *
     * Verifies that scanProducts method returns correct product data structure.
     *
     * @return void
     */
    public function test_scanProducts_should_return_array_when_products_exist(): void
    {
        $products = $this->scanner->scanProducts(['limit' => 10]);

        $this->assertIsArray($products, 'scanProducts should return an array');
        $this->assertGreaterThan(0, count($products), 'Should find at least one product');

        // Check structure of first product
        if (!empty($products)) {
            $product = $products[0];
            $expectedKeys = ['id', 'type', 'title', 'content', 'url', 'metadata', 'language', 'last_modified'];
            
            foreach ($expectedKeys as $key) {
                $this->assertArrayHasKey($key, $product, "Product should contain '{$key}' key");
            }

            $this->assertEquals('product', $product['type'], 'Product type should be "product"');
            $this->assertIsInt($product['id'], 'Product ID should be integer');
            $this->assertIsString($product['title'], 'Product title should be string');
            $this->assertIsString($product['content'], 'Product content should be string');
            $this->assertIsArray($product['metadata'], 'Product metadata should be array');
        }
    }

    /**
     * Test product scanning with custom arguments
     *
     * Verifies that scanProducts respects limit and other arguments.
     *
     * @return void
     */
    public function test_scanProducts_should_respect_limit_argument(): void
    {
        $products = $this->scanner->scanProducts(['limit' => 1]);

        $this->assertIsArray($products, 'scanProducts should return an array');
        $this->assertLessThanOrEqual(1, count($products), 'Should respect limit argument');
    }

    /**
     * Test product scanning with WooCommerce inactive
     *
     * Verifies that scanProducts throws exception when WooCommerce is not active.
     *
     * @return void
     */
    public function test_scanProducts_should_throw_exception_when_woocommerce_inactive(): void
    {
        // Mock WooCommerce as inactive
        add_filter('woo_ai_assistant_is_woocommerce_active', '__return_false');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('WooCommerce is not active');

        $this->scanner->scanProducts();

        remove_filter('woo_ai_assistant_is_woocommerce_active', '__return_false');
    }

    /**
     * Test product scanning with force refresh
     *
     * Verifies that force_refresh bypasses cache.
     *
     * @return void
     */
    public function test_scanProducts_should_bypass_cache_when_force_refresh_true(): void
    {
        // First scan to populate cache
        $this->scanner->scanProducts(['limit' => 1]);

        // Mock cache to return different data
        $cache = Cache::getInstance();
        $cacheKey = $this->invokeMethod($this->scanner, 'generateCacheKey', ['products', ['limit' => 1, 'force_refresh' => false, 'include_ids' => [], 'exclude_ids' => []]]);
        $cache->set($cacheKey, ['cached_result' => true], 3600);

        // Scan without force refresh (should return cached data)
        $cachedResult = $this->scanner->scanProducts(['limit' => 1]);
        $this->assertArrayHasKey('cached_result', $cachedResult[0] ?? [], 'Should return cached data without force refresh');

        // Scan with force refresh (should bypass cache)
        $freshResult = $this->scanner->scanProducts(['limit' => 1, 'force_refresh' => true]);
        $this->assertArrayNotHasKey('cached_result', $freshResult[0] ?? [], 'Should bypass cache with force refresh');
    }

    /**
     * Test page scanning functionality
     *
     * Verifies that scanPages method returns correct page data.
     *
     * @return void
     */
    public function test_scanPages_should_return_array_when_pages_exist(): void
    {
        $pages = $this->scanner->scanPages([
            'include_wc_pages' => false,
            'include_legal_pages' => false,
            'custom_page_ids' => $this->mockPages
        ]);

        $this->assertIsArray($pages, 'scanPages should return an array');
        $this->assertGreaterThan(0, count($pages), 'Should find at least one page');

        // Check structure of first page
        if (!empty($pages)) {
            $page = $pages[0];
            $expectedKeys = ['id', 'type', 'title', 'content', 'url', 'metadata', 'language', 'last_modified'];
            
            foreach ($expectedKeys as $key) {
                $this->assertArrayHasKey($page, $key, "Page should contain '{$key}' key");
            }

            $this->assertEquals('page', $page['type'], 'Page type should be "page"');
            $this->assertIsInt($page['id'], 'Page ID should be integer');
        }
    }

    /**
     * Test WooCommerce settings scanning
     *
     * Verifies that scanWooCommerceSettings returns settings data.
     *
     * @return void
     */
    public function test_scanWooCommerceSettings_should_return_settings_array(): void
    {
        $settings = $this->scanner->scanWooCommerceSettings();

        $this->assertIsArray($settings, 'scanWooCommerceSettings should return an array');

        // Check that we have at least general settings
        $generalSettings = array_filter($settings, function($setting) {
            return ($setting['id'] ?? '') === 'wc_general_settings';
        });

        $this->assertNotEmpty($generalSettings, 'Should include general WooCommerce settings');

        // Check structure of settings
        if (!empty($settings)) {
            $setting = $settings[0];
            $expectedKeys = ['id', 'type', 'title', 'content', 'url', 'metadata', 'language', 'last_modified'];
            
            foreach ($expectedKeys as $key) {
                $this->assertArrayHasKey($key, $setting, "Setting should contain '{$key}' key");
            }

            $this->assertEquals('woocommerce_settings', $setting['type'], 'Setting type should be "woocommerce_settings"');
        }
    }

    /**
     * Test category scanning functionality
     *
     * Verifies that scanCategories returns taxonomy data.
     *
     * @return void
     */
    public function test_scanCategories_should_return_taxonomy_array(): void
    {
        // Create test category
        $category = wp_insert_term('Test Category', 'product_cat');
        if (!is_wp_error($category)) {
            $categories = $this->scanner->scanCategories();

            $this->assertIsArray($categories, 'scanCategories should return an array');

            // Check structure if categories exist
            if (!empty($categories)) {
                $cat = $categories[0];
                $expectedKeys = ['id', 'type', 'title', 'content', 'url', 'metadata', 'language', 'last_modified'];
                
                foreach ($expectedKeys as $key) {
                    $this->assertArrayHasKey($key, $cat, "Category should contain '{$key}' key");
                }

                $this->assertContains($cat['type'], ['product_cat', 'product_tag'], 'Category type should be taxonomy type');
            }

            // Clean up test category
            wp_delete_term($category['term_id'], 'product_cat');
        }
    }

    /**
     * Test comprehensive scanning (scanAll)
     *
     * Verifies that scanAll orchestrates all scanning methods correctly.
     *
     * @return void
     */
    public function test_scanAll_should_return_comprehensive_results(): void
    {
        $result = $this->scanner->scanAll([
            'include_products' => true,
            'include_pages' => true,
            'include_posts' => false,
            'include_settings' => true,
            'include_categories' => true
        ]);

        $this->assertIsArray($result, 'scanAll should return an array');
        $this->assertArrayHasKey('success', $result, 'Result should contain success key');
        $this->assertArrayHasKey('data', $result, 'Result should contain data key');
        $this->assertArrayHasKey('summary', $result, 'Result should contain summary key');
        $this->assertArrayHasKey('errors', $result, 'Result should contain errors key');
        $this->assertArrayHasKey('duration', $result, 'Result should contain duration key');

        $this->assertIsBool($result['success'], 'Success should be boolean');
        $this->assertIsArray($result['data'], 'Data should be array');
        $this->assertIsArray($result['summary'], 'Summary should be array');
        $this->assertIsArray($result['errors'], 'Errors should be array');
        $this->assertIsFloat($result['duration'], 'Duration should be float');

        // Check summary structure
        $expectedSummaryKeys = ['products', 'pages', 'posts', 'settings', 'categories'];
        foreach ($expectedSummaryKeys as $key) {
            $this->assertArrayHasKey($key, $result['summary'], "Summary should contain '{$key}' key");
            $this->assertIsInt($result['summary'][$key], "Summary '{$key}' should be integer");
        }
    }

    /**
     * Test scanAll error handling
     *
     * Verifies that scanAll handles errors gracefully and continues processing.
     *
     * @return void
     */
    public function test_scanAll_should_handle_errors_gracefully(): void
    {
        // Mock WooCommerce as inactive to trigger product scan error
        add_filter('woo_ai_assistant_is_woocommerce_active', '__return_false');

        $result = $this->scanner->scanAll();

        $this->assertIsArray($result, 'scanAll should return array even with errors');
        $this->assertArrayHasKey('errors', $result, 'Result should contain errors');
        $this->assertGreaterThan(0, count($result['errors']), 'Should have recorded errors');

        // Clean up
        remove_filter('woo_ai_assistant_is_woocommerce_active', '__return_false');
    }

    /**
     * Test scanner statistics
     *
     * Verifies that getStatistics returns configuration and status information.
     *
     * @return void
     */
    public function test_getStatistics_should_return_scanner_info(): void
    {
        $stats = $this->scanner->getStatistics();

        $this->assertIsArray($stats, 'getStatistics should return array');
        
        $expectedKeys = [
            'batch_size',
            'cache_ttl', 
            'supported_content_types',
            'multilingual_support',
            'current_language',
            'woocommerce_active'
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $stats, "Statistics should contain '{$key}' key");
        }

        $this->assertIsInt($stats['batch_size'], 'Batch size should be integer');
        $this->assertIsInt($stats['cache_ttl'], 'Cache TTL should be integer');
        $this->assertIsArray($stats['supported_content_types'], 'Supported content types should be array');
        $this->assertIsBool($stats['multilingual_support'], 'Multilingual support should be boolean');
        $this->assertIsString($stats['current_language'], 'Current language should be string');
        $this->assertIsBool($stats['woocommerce_active'], 'WooCommerce active should be boolean');
    }

    /**
     * Test batch size configuration
     *
     * Verifies that setBatchSize updates the batch size correctly.
     *
     * @return void
     */
    public function test_setBatchSize_should_update_batch_size(): void
    {
        $originalBatchSize = $this->getPropertyValue($this->scanner, 'batchSize');
        $newBatchSize = 25;

        $this->scanner->setBatchSize($newBatchSize);
        $updatedBatchSize = $this->getPropertyValue($this->scanner, 'batchSize');

        $this->assertEquals($newBatchSize, $updatedBatchSize, 'setBatchSize should update batch size');

        // Restore original batch size
        $this->scanner->setBatchSize($originalBatchSize);
    }

    /**
     * Test invalid batch size
     *
     * Verifies that setBatchSize throws exception for invalid values.
     *
     * @return void
     */
    public function test_setBatchSize_should_throw_exception_for_invalid_size(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Batch size must be a positive integer');

        $this->scanner->setBatchSize(0);
    }

    /**
     * Test cache TTL configuration
     *
     * Verifies that setCacheTtl updates the cache TTL correctly.
     *
     * @return void
     */
    public function test_setCacheTtl_should_update_cache_ttl(): void
    {
        $originalTtl = $this->getPropertyValue($this->scanner, 'cacheTtl');
        $newTtl = 7200;

        $this->scanner->setCacheTtl($newTtl);
        $updatedTtl = $this->getPropertyValue($this->scanner, 'cacheTtl');

        $this->assertEquals($newTtl, $updatedTtl, 'setCacheTtl should update cache TTL');

        // Restore original TTL
        $this->scanner->setCacheTtl($originalTtl);
    }

    /**
     * Test invalid cache TTL
     *
     * Verifies that setCacheTtl throws exception for negative values.
     *
     * @return void
     */
    public function test_setCacheTtl_should_throw_exception_for_negative_ttl(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cache TTL must be non-negative');

        $this->scanner->setCacheTtl(-1);
    }

    /**
     * Test class name follows PascalCase convention
     *
     * Verifies that the Scanner class follows PascalCase naming convention.
     *
     * @return void
     */
    public function test_scanner_class_name_should_follow_pascal_case_convention(): void
    {
        $this->assertClassFollowsPascalCase(Scanner::class);
    }

    /**
     * Test method names follow camelCase convention
     *
     * Verifies that all public methods follow camelCase naming convention.
     *
     * @return void
     */
    public function test_scanner_public_methods_should_follow_camel_case_convention(): void
    {
        $publicMethods = [
            'getInstance',
            'scanProducts',
            'scanPages', 
            'scanPosts',
            'scanWooCommerceSettings',
            'scanCategories',
            'scanAll',
            'getStatistics',
            'setBatchSize',
            'setCacheTtl'
        ];

        foreach ($publicMethods as $methodName) {
            $this->assertMethodFollowsCamelCase($this->scanner, $methodName);
        }
    }

    /**
     * Test private method accessibility through reflection
     *
     * Verifies that private methods can be accessed for testing and follow naming conventions.
     *
     * @return void
     */
    public function test_private_methods_should_be_accessible_through_reflection(): void
    {
        $privateMethods = [
            'processProduct',
            'processPage',
            'processPost',
            'detectMultilingualSupport',
            'getCurrentLanguage',
            'generateCacheKey'
        ];

        foreach ($privateMethods as $methodName) {
            $method = $this->getReflectionMethod($this->scanner, $methodName);
            $this->assertTrue($method->isPrivate(), "Method {$methodName} should be private");
        }
    }

    /**
     * Test memory usage remains reasonable
     *
     * Verifies that the scanner doesn't consume excessive memory during operations.
     *
     * @return void
     */
    public function test_scanner_memory_usage_should_be_reasonable(): void
    {
        $initialMemory = memory_get_usage();

        // Perform multiple scanning operations
        for ($i = 0; $i < 10; $i++) {
            $this->scanner->scanProducts(['limit' => 1]);
            $this->scanner->getStatistics();
        }

        $finalMemory = memory_get_usage();
        $memoryIncrease = $finalMemory - $initialMemory;

        // Memory increase should be less than 2MB for these operations
        $this->assertLessThan(2097152, $memoryIncrease, 'Memory increase should be less than 2MB for repeated operations');
    }

    /**
     * Test caching functionality
     *
     * Verifies that scanner properly caches results and respects cache settings.
     *
     * @return void
     */
    public function test_scanner_should_properly_cache_results(): void
    {
        $cache = Cache::getInstance();
        
        // Clear any existing cache
        $cache->flush();

        // First scan should hit the database
        $startTime = microtime(true);
        $firstResult = $this->scanner->scanProducts(['limit' => 1]);
        $firstDuration = microtime(true) - $startTime;

        // Second scan should be faster (cached)
        $startTime = microtime(true);
        $secondResult = $this->scanner->scanProducts(['limit' => 1]);
        $secondDuration = microtime(true) - $startTime;

        // Results should be identical
        $this->assertEquals($firstResult, $secondResult, 'Cached results should match original results');
        
        // Cached result should be significantly faster (allowing for some variance)
        $this->assertLessThanOrEqual($firstDuration, $secondDuration, 'Cached request should not be slower than original');
    }

    /**
     * Test error handling in product processing
     *
     * Verifies that the scanner handles individual product processing errors gracefully.
     *
     * @return void
     */
    public function test_scanner_should_handle_product_processing_errors_gracefully(): void
    {
        // Create a product that might cause processing issues
        $problematicProduct = $this->createTestProduct([
            'name' => '', // Empty name might cause issues
            'description' => '',
            'status' => 'publish'
        ]);

        $result = $this->scanner->scanProducts(['limit' => 10]);

        // Should still return an array even with problematic products
        $this->assertIsArray($result, 'Should return array even with problematic products');
        
        // Should not throw fatal errors
        $this->assertTrue(true, 'Scanner should handle problematic products without fatal errors');
    }

    /**
     * Clean up test data after each test
     *
     * @return void
     */
    protected function cleanUpTestData(): void
    {
        // Clean up mock products
        foreach ($this->mockProducts as $product) {
            if ($product && $product->get_id()) {
                wp_delete_post($product->get_id(), true);
            }
        }
        $this->mockProducts = [];

        // Clean up mock pages  
        foreach ($this->mockPages as $pageId) {
            wp_delete_post($pageId, true);
        }
        $this->mockPages = [];

        parent::cleanUpTestData();
    }
}