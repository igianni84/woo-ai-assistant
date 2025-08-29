<?php

/**
 * Knowledge Base Scanner Class
 *
 * Handles scanning and indexing of WooCommerce products, site content,
 * settings, and policies for the AI-powered knowledge base.
 *
 * @package WooAiAssistant
 * @subpackage KnowledgeBase
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\KnowledgeBase;

use WooAiAssistant\Common\Utils;
use WooAiAssistant\Common\Traits\Singleton;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Scanner
 *
 * Comprehensive content scanning system that extracts all relevant information
 * from WooCommerce products, site content, settings, and policies to build
 * the knowledge base for the AI assistant.
 *
 * @since 1.0.0
 */
class Scanner
{
    use Singleton;

    /**
     * Default batch size for content processing
     *
     * @since 1.0.0
     * @var int
     */
    const DEFAULT_BATCH_SIZE = 100;

    /**
     * Maximum content length per item
     *
     * @since 1.0.0
     * @var int
     */
    const MAX_CONTENT_LENGTH = 10000;

    /**
     * Cache group for scanner operations
     *
     * @since 1.0.0
     * @var string
     */
    const CACHE_GROUP = 'woo_ai_scanner';

    /**
     * Cache TTL for scanner operations (1 hour)
     *
     * @since 1.0.0
     * @var int
     */
    const CACHE_TTL = 3600;

    /**
     * Supported content types
     *
     * @since 1.0.0
     * @var array
     */
    private array $supportedContentTypes = [
        'products',
        'pages',
        'posts',
        'categories',
        'tags',
        'woo_settings',
        'policies',
        'custom_posts'
    ];

    /**
     * Last scan statistics
     *
     * @since 1.0.0
     * @var array
     */
    private array $lastScanStats = [];

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    private function __construct()
    {
        // Initialize scanner
        $this->initializeScanner();
    }

    /**
     * Initialize scanner settings and hooks
     *
     * @since 1.0.0
     * @return void
     */
    private function initializeScanner(): void
    {
        // Hook into WooCommerce product updates
        add_action('woocommerce_product_set_stock', [$this, 'onProductUpdated'], 10, 1);
        add_action('woocommerce_update_product', [$this, 'onProductUpdated'], 10, 1);
        add_action('save_post', [$this, 'onPostSaved'], 10, 3);

        Utils::logDebug('Scanner initialized with WooCommerce hooks');
    }

    /**
     * Scan WooCommerce products and extract content for knowledge base indexing
     *
     * This method retrieves all published products, processes their content including
     * titles, descriptions, categories, and custom attributes, then prepares them
     * for embedding generation.
     *
     * @since 1.0.0
     * @param array $args Optional. Arguments for product query filtering.
     * @param int   $args['limit'] Maximum number of products to scan. Default 100.
     * @param int   $args['offset'] Number of products to skip. Default 0.
     * @param bool  $args['force_update'] Whether to rescan existing products. Default false.
     * @param array $args['include_ids'] Specific product IDs to scan. Default empty.
     * @param array $args['exclude_ids'] Product IDs to exclude from scan. Default empty.
     *
     * @return array Array of product data formatted for indexing.
     *               Each element contains 'id', 'title', 'content', 'type', 'url', 'metadata'.
     *
     * @throws \InvalidArgumentException When limit is not a positive integer.
     * @throws \RuntimeException When WooCommerce is not active.
     *
     * @example
     * ```php
     * $scanner = Scanner::getInstance();
     * $products = $scanner->scanProducts(['limit' => 50, 'force_update' => true]);
     * foreach ($products as $product) {
     *     echo "Product: {$product['title']} - {$product['url']}\n";
     * }
     * ```
     */
    public function scanProducts(array $args = []): array
    {
        // Validate WooCommerce is active
        if (!Utils::isWooCommerceActive()) {
            throw new \RuntimeException('WooCommerce is not active');
        }

        // Parse and validate arguments
        $args = wp_parse_args($args, [
            'limit' => self::DEFAULT_BATCH_SIZE,
            'offset' => 0,
            'force_update' => false,
            'include_ids' => [],
            'exclude_ids' => [],
            'language' => null // Language filtering for multilingual support
        ]);

        // Apply multilingual filtering if available
        $args = apply_filters('woo_ai_assistant_kb_query_args', $args, $args['language']);

        // Validate limit
        if (!is_int($args['limit']) || $args['limit'] < 1) {
            throw new \InvalidArgumentException('Limit must be a positive integer');
        }

        Utils::logDebug('Starting product scan', [
            'limit' => $args['limit'],
            'offset' => $args['offset'],
            'force_update' => $args['force_update']
        ]);

        $results = [];
        $scanStats = [
            'scanned' => 0,
            'skipped' => 0,
            'errors' => 0,
            'start_time' => microtime(true)
        ];

        try {
            // Build WP_Query arguments
            $queryArgs = [
                'post_type' => 'product',
                'post_status' => 'publish',
                'posts_per_page' => $args['limit'],
                'offset' => $args['offset'],
                'fields' => 'ids',
                'no_found_rows' => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false
            ];

            // Handle include/exclude IDs
            if (!empty($args['include_ids'])) {
                $queryArgs['post__in'] = array_map('absint', $args['include_ids']);
            }
            if (!empty($args['exclude_ids'])) {
                $queryArgs['post__not_in'] = array_map('absint', $args['exclude_ids']);
            }

            $productQuery = new \WP_Query($queryArgs);
            $productIds = $productQuery->posts;

            Utils::logDebug('Found products for scanning', ['count' => count($productIds)]);

            foreach ($productIds as $productId) {
                try {
                    $productData = $this->extractProductData($productId, $args['force_update']);
                    if ($productData) {
                        $results[] = $productData;
                        $scanStats['scanned']++;
                    } else {
                        $scanStats['skipped']++;
                    }
                } catch (\Exception $e) {
                    Utils::logError('Error scanning product', [
                        'product_id' => $productId,
                        'error' => $e->getMessage()
                    ]);
                    $scanStats['errors']++;
                }
            }
        } catch (\Exception $e) {
            Utils::logError('Product scanning failed', ['error' => $e->getMessage()]);
            throw $e;
        }

        $scanStats['end_time'] = microtime(true);
        $scanStats['duration'] = $scanStats['end_time'] - $scanStats['start_time'];
        $this->lastScanStats['products'] = $scanStats;

        Utils::logDebug('Product scan completed', $scanStats);

        return $results;
    }

    /**
     * Scan pages and posts for content extraction
     *
     * @since 1.0.0
     * @param array $args Optional. Arguments for content query filtering.
     * @param int   $args['limit'] Maximum number of items to scan. Default 100.
     * @param int   $args['offset'] Number of items to skip. Default 0.
     * @param array $args['post_types'] Post types to scan. Default ['page', 'post'].
     * @param bool  $args['force_update'] Whether to rescan existing content. Default false.
     *
     * @return array Array of content data formatted for indexing.
     *
     * @throws \InvalidArgumentException When arguments are invalid.
     */
    public function scanPages(array $args = []): array
    {
        $args = wp_parse_args($args, [
            'limit' => self::DEFAULT_BATCH_SIZE,
            'offset' => 0,
            'post_types' => ['page', 'post'],
            'force_update' => false,
            'language' => null // Language filtering for multilingual support
        ]);

        // Apply multilingual filtering if available
        $args = apply_filters('woo_ai_assistant_kb_query_args', $args, $args['language']);

        if (!is_int($args['limit']) || $args['limit'] < 1) {
            throw new \InvalidArgumentException('Limit must be a positive integer');
        }

        Utils::logDebug('Starting page/post scan', $args);

        $results = [];
        $scanStats = [
            'scanned' => 0,
            'skipped' => 0,
            'errors' => 0,
            'start_time' => microtime(true)
        ];

        try {
            $queryArgs = [
                'post_type' => $args['post_types'],
                'post_status' => 'publish',
                'posts_per_page' => $args['limit'],
                'offset' => $args['offset'],
                'fields' => 'ids',
                'no_found_rows' => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false
            ];

            $contentQuery = new \WP_Query($queryArgs);
            $contentIds = $contentQuery->posts;

            foreach ($contentIds as $contentId) {
                try {
                    $contentData = $this->extractPageContent($contentId, $args['force_update']);
                    if ($contentData) {
                        $results[] = $contentData;
                        $scanStats['scanned']++;
                    } else {
                        $scanStats['skipped']++;
                    }
                } catch (\Exception $e) {
                    Utils::logError('Error scanning content', [
                        'content_id' => $contentId,
                        'error' => $e->getMessage()
                    ]);
                    $scanStats['errors']++;
                }
            }
        } catch (\Exception $e) {
            Utils::logError('Page/post scanning failed', ['error' => $e->getMessage()]);
            throw $e;
        }

        $scanStats['end_time'] = microtime(true);
        $scanStats['duration'] = $scanStats['end_time'] - $scanStats['start_time'];
        $this->lastScanStats['pages'] = $scanStats;

        Utils::logDebug('Page/post scan completed', $scanStats);

        return $results;
    }

    /**
     * Extract WooCommerce settings for knowledge base
     *
     * @since 1.0.0
     * @return array Array of WooCommerce settings data.
     *
     * @throws \RuntimeException When WooCommerce is not active.
     */
    public function scanWooSettings(): array
    {
        if (!Utils::isWooCommerceActive()) {
            throw new \RuntimeException('WooCommerce is not active');
        }

        Utils::logDebug('Starting WooCommerce settings scan');

        $results = [];
        $scanStats = [
            'sections_scanned' => 0,
            'settings_extracted' => 0,
            'start_time' => microtime(true)
        ];

        try {
            // Extract store information
            $storeInfo = $this->extractStoreInformation();
            if ($storeInfo) {
                $results[] = $storeInfo;
                $scanStats['sections_scanned']++;
            }

            // Extract shipping settings
            $shippingSettings = $this->extractShippingSettings();
            if ($shippingSettings) {
                $results[] = $shippingSettings;
                $scanStats['sections_scanned']++;
            }

            // Extract payment settings
            $paymentSettings = $this->extractPaymentSettings();
            if ($paymentSettings) {
                $results[] = $paymentSettings;
                $scanStats['sections_scanned']++;
            }

            // Extract tax settings
            $taxSettings = $this->extractTaxSettings();
            if ($taxSettings) {
                $results[] = $taxSettings;
                $scanStats['sections_scanned']++;
            }

            $scanStats['settings_extracted'] = count($results);
        } catch (\Exception $e) {
            Utils::logError('WooCommerce settings scanning failed', ['error' => $e->getMessage()]);
            throw $e;
        }

        $scanStats['end_time'] = microtime(true);
        $scanStats['duration'] = $scanStats['end_time'] - $scanStats['start_time'];
        $this->lastScanStats['woo_settings'] = $scanStats;

        Utils::logDebug('WooCommerce settings scan completed', $scanStats);

        return $results;
    }

    /**
     * Scan categories and tags
     *
     * @since 1.0.0
     * @param array $args Optional. Arguments for taxonomy scanning.
     * @param array $args['taxonomies'] Taxonomies to scan. Default ['product_cat', 'product_tag'].
     * @param bool  $args['include_hierarchy'] Whether to include hierarchy info. Default true.
     *
     * @return array Array of taxonomy data.
     */
    public function scanCategories(array $args = []): array
    {
        $args = wp_parse_args($args, [
            'taxonomies' => ['product_cat', 'product_tag'],
            'include_hierarchy' => true
        ]);

        Utils::logDebug('Starting categories/tags scan', $args);

        $results = [];
        $scanStats = [
            'terms_scanned' => 0,
            'taxonomies_processed' => 0,
            'start_time' => microtime(true)
        ];

        try {
            foreach ($args['taxonomies'] as $taxonomy) {
                if (!taxonomy_exists($taxonomy)) {
                    Utils::logDebug("Taxonomy {$taxonomy} does not exist, skipping");
                    continue;
                }

                $terms = get_terms([
                    'taxonomy' => $taxonomy,
                    'hide_empty' => false,
                    'number' => 0
                ]);

                if (is_wp_error($terms)) {
                    Utils::logError("Error getting terms for taxonomy {$taxonomy}", [
                        'error' => $terms->get_error_message()
                    ]);
                    continue;
                }

                foreach ($terms as $term) {
                    $termData = $this->extractTermData($term, $args['include_hierarchy']);
                    if ($termData) {
                        $results[] = $termData;
                        $scanStats['terms_scanned']++;
                    }
                }

                $scanStats['taxonomies_processed']++;
            }
        } catch (\Exception $e) {
            Utils::logError('Categories/tags scanning failed', ['error' => $e->getMessage()]);
            throw $e;
        }

        $scanStats['end_time'] = microtime(true);
        $scanStats['duration'] = $scanStats['end_time'] - $scanStats['start_time'];
        $this->lastScanStats['categories'] = $scanStats;

        Utils::logDebug('Categories/tags scan completed', $scanStats);

        return $results;
    }

    /**
     * Process content in batches for large sites with safety mechanisms
     *
     * @since 1.0.0
     * @param string $contentType Type of content to process.
     * @param int    $offset      Starting offset for batch processing.
     * @param int    $limit       Number of items to process per batch.
     *
     * @return array Batch processing results with pagination info.
     *
     * @throws \InvalidArgumentException When content type is not supported.
     */
    public function processBatch(string $contentType, int $offset = 0, int $limit = 100): array
    {
        if (!in_array($contentType, $this->supportedContentTypes, true)) {
            throw new \InvalidArgumentException("Unsupported content type: {$contentType}");
        }

        // EMERGENCY FIX: Add safety limits for batch processing
        $batchStartTime = microtime(true);
        $maxBatchTime = 30; // Maximum 30 seconds per batch
        $maxOffset = 10000; // Maximum offset to prevent runaway pagination
        $maxLimit = 500; // Maximum limit per batch

        // Sanitize and limit parameters
        $offset = max(0, min($offset, $maxOffset));
        $limit = max(1, min($limit, $maxLimit));

        Utils::logDebug('Processing batch with safety limits', [
            'content_type' => $contentType,
            'offset' => $offset,
            'limit' => $limit,
            'max_time' => $maxBatchTime . 's'
        ]);

        $results = [];
        $hasMore = false;

        try {
            // EMERGENCY FIX: Timeout check before processing
            if ((microtime(true) - $batchStartTime) > $maxBatchTime) {
                throw new \RuntimeException("Batch processing timeout reached before execution");
            }

            switch ($contentType) {
                case 'products':
                    $results = $this->scanProducts(['offset' => $offset, 'limit' => $limit]);
                    break;

                case 'pages':
                case 'posts':
                    $postTypes = $contentType === 'pages' ? ['page'] : ['post'];
                    $results = $this->scanPages([
                        'offset' => $offset,
                        'limit' => $limit,
                        'post_types' => $postTypes
                    ]);
                    break;

                case 'categories':
                case 'tags':
                    $taxonomies = $contentType === 'categories' ? ['product_cat'] : ['product_tag'];
                    $results = $this->scanCategories(['taxonomies' => $taxonomies]);
                    break;

                case 'woo_settings':
                    $results = $this->scanWooSettings();
                    break;

                default:
                    $results = $this->scanCustomContent($contentType, $offset, $limit);
                    break;
            }

            // EMERGENCY FIX: Final timeout check
            if ((microtime(true) - $batchStartTime) > $maxBatchTime) {
                Utils::logError("Batch processing completed but exceeded time limit");
                $results = array_slice($results, 0, min(count($results), 50)); // Limit results if timeout
            }

            // Check if there are more items to process (but enforce limits)
            $hasMore = count($results) === $limit && $offset < $maxOffset;
        } catch (\Exception $e) {
            Utils::logError('Batch processing failed with emergency safety', [
                'content_type' => $contentType,
                'offset' => $offset,
                'limit' => $limit,
                'processing_time' => (microtime(true) - $batchStartTime),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }

        $processingTime = microtime(true) - $batchStartTime;
        Utils::logDebug("Batch processing completed - items: " . count($results) . ", time: {$processingTime}s");

        return [
            'data' => $results,
            'pagination' => [
                'offset' => $offset,
                'limit' => $limit,
                'current_batch_size' => count($results),
                'has_more' => $hasMore,
                'next_offset' => $hasMore ? min($offset + $limit, $maxOffset) : null,
                'safety_limits_applied' => true
            ],
            'stats' => $this->getLastScanStats($contentType)
        ];
    }

    /**
     * Extract comprehensive product data
     *
     * @since 1.0.0
     * @param int  $productId   Product ID to extract data from.
     * @param bool $forceUpdate Whether to force update cached data.
     *
     * @return array|null Product data array or null if product is invalid.
     */
    private function extractProductData(int $productId, bool $forceUpdate = false): ?array
    {
        if (!$forceUpdate) {
            $cached = wp_cache_get("product_{$productId}", self::CACHE_GROUP);
            if ($cached !== false) {
                return $cached;
            }
        }

        $product = wc_get_product($productId);
        if (!$product || !$product->exists()) {
            return null;
        }

        try {
            $productData = [
                'id' => $productId,
                'title' => $this->sanitizeContent($product->get_name()),
                'content' => $this->buildProductContent($product),
                'type' => 'product',
                'url' => get_permalink($productId),
                'language' => apply_filters('woo_ai_assistant_kb_content_language', null, $productId, 'product'),
                'metadata' => [
                    'product_type' => $product->get_type(),
                    'sku' => $product->get_sku(),
                    'price' => $product->get_price(),
                    'regular_price' => $product->get_regular_price(),
                    'sale_price' => $product->get_sale_price(),
                    'stock_status' => $product->get_stock_status(),
                    'stock_quantity' => $product->managing_stock() ? $product->get_stock_quantity() : null,
                    'categories' => $this->getProductCategories($product),
                    'tags' => $this->getProductTags($product),
                    'attributes' => $this->getProductAttributes($product),
                    'variations' => $this->getProductVariations($product),
                    'language_code' => apply_filters('woo_ai_assistant_kb_content_language', null, $productId, 'product'),
                    'last_updated' => current_time('mysql')
                ]
            ];

            wp_cache_set("product_{$productId}", $productData, self::CACHE_GROUP, self::CACHE_TTL);

            return $productData;
        } catch (\Exception $e) {
            Utils::logError('Error extracting product data', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Build comprehensive product content for indexing
     *
     * @since 1.0.0
     * @param \WC_Product $product Product object.
     *
     * @return string Formatted product content.
     */
    private function buildProductContent(\WC_Product $product): string
    {
        $content = [];

        // Product name
        $content[] = 'Product: ' . $product->get_name();

        // Short description
        if ($product->get_short_description()) {
            $content[] = 'Summary: ' . strip_tags($product->get_short_description());
        }

        // Full description
        if ($product->get_description()) {
            $content[] = 'Description: ' . strip_tags($product->get_description());
        }

        // Price information
        if ($product->get_price()) {
            $content[] = 'Price: ' . wc_price($product->get_price());
            if ($product->is_on_sale()) {
                $content[] = 'Regular Price: ' . wc_price($product->get_regular_price());
                $content[] = 'Sale Price: ' . wc_price($product->get_sale_price());
            }
        }

        // Stock information
        if ($product->managing_stock()) {
            $content[] = 'Stock: ' . $product->get_stock_quantity() . ' available';
        } else {
            $content[] = 'Stock Status: ' . ucfirst($product->get_stock_status());
        }

        // Categories
        $categories = $this->getProductCategories($product);
        if (!empty($categories)) {
            $content[] = 'Categories: ' . implode(', ', $categories);
        }

        // Tags
        $tags = $this->getProductTags($product);
        if (!empty($tags)) {
            $content[] = 'Tags: ' . implode(', ', $tags);
        }

        // Attributes
        $attributes = $this->getProductAttributes($product);
        foreach ($attributes as $name => $value) {
            $content[] = "{$name}: {$value}";
        }

        $fullContent = implode("\n", $content);
        return $this->truncateContent($fullContent);
    }

    /**
     * Extract page/post content data
     *
     * @since 1.0.0
     * @param int  $postId      Post ID to extract data from.
     * @param bool $forceUpdate Whether to force update cached data.
     *
     * @return array|null Content data array or null if post is invalid.
     */
    private function extractPageContent(int $postId, bool $forceUpdate = false): ?array
    {
        if (!$forceUpdate) {
            $cached = wp_cache_get("content_{$postId}", self::CACHE_GROUP);
            if ($cached !== false) {
                return $cached;
            }
        }

        $post = get_post($postId);
        if (!$post || $post->post_status !== 'publish') {
            return null;
        }

        try {
            $contentData = [
                'id' => $postId,
                'title' => $this->sanitizeContent($post->post_title),
                'content' => $this->sanitizeContent(strip_tags($post->post_content)),
                'type' => $post->post_type,
                'url' => get_permalink($postId),
                'metadata' => [
                    'post_type' => $post->post_type,
                    'post_date' => $post->post_date,
                    'post_modified' => $post->post_modified,
                    'author' => \get_the_author_meta('display_name', $post->post_author),
                    'excerpt' => $post->post_excerpt ? strip_tags($post->post_excerpt) : '',
                    'categories' => $this->getPostCategories($post),
                    'tags' => $this->getPostTags($post),
                    'last_updated' => current_time('mysql')
                ]
            ];

            $contentData['content'] = $this->truncateContent($contentData['content']);

            wp_cache_set("content_{$postId}", $contentData, self::CACHE_GROUP, self::CACHE_TTL);

            return $contentData;
        } catch (\Exception $e) {
            Utils::logError('Error extracting page content', [
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Extract store information
     *
     * @since 1.0.0
     * @return array Store information data.
     */
    private function extractStoreInformation(): array
    {
        $storeData = [
            'id' => 'store_info',
            'title' => 'Store Information',
            'content' => $this->buildStoreInfoContent(),
            'type' => 'woo_settings',
            'url' => admin_url('admin.php?page=wc-settings'),
            'metadata' => [
                'setting_type' => 'store_info',
                'last_updated' => current_time('mysql')
            ]
        ];

        return $storeData;
    }

    /**
     * Build store information content
     *
     * @since 1.0.0
     * @return string Store information content.
     */
    private function buildStoreInfoContent(): string
    {
        $content = [];

        $content[] = 'Store Name: ' . get_option('blogname');
        $content[] = 'Store Description: ' . get_option('blogdescription');

        // WooCommerce specific settings
        $content[] = 'Store Address: ' . WC()->countries->get_base_address();
        $content[] = 'Store City: ' . WC()->countries->get_base_city();
        $content[] = 'Store Country: ' . WC()->countries->get_base_country();
        $content[] = 'Store Postcode: ' . WC()->countries->get_base_postcode();
        $content[] = 'Store Currency: ' . get_woocommerce_currency();
        $content[] = 'Currency Symbol: ' . get_woocommerce_currency_symbol();

        return implode("\n", array_filter($content));
    }

    /**
     * Extract shipping settings
     *
     * @since 1.0.0
     * @return array Shipping settings data.
     */
    private function extractShippingSettings(): array
    {
        $shippingData = [
            'id' => 'shipping_settings',
            'title' => 'Shipping Information',
            'content' => $this->buildShippingContent(),
            'type' => 'woo_settings',
            'url' => admin_url('admin.php?page=wc-settings&tab=shipping'),
            'metadata' => [
                'setting_type' => 'shipping',
                'last_updated' => current_time('mysql')
            ]
        ];

        return $shippingData;
    }

    /**
     * Build shipping content
     *
     * @since 1.0.0
     * @return string Shipping content.
     */
    private function buildShippingContent(): string
    {
        $content = [];

        // Shipping zones
        $shippingZones = \WC_Shipping_Zones::get_zones();
        foreach ($shippingZones as $zone) {
            $content[] = 'Shipping Zone: ' . $zone['zone_name'];
            foreach ($zone['shipping_methods'] as $method) {
                $content[] = '  Method: ' . $method->get_title() . ' - ' . $method->get_option('cost', 'Free');
            }
        }

        // Default shipping options
        $content[] = 'Free Shipping Minimum: ' . get_option('woocommerce_free_shipping_requires');

        return implode("\n", array_filter($content));
    }

    /**
     * Extract payment settings
     *
     * @since 1.0.0
     * @return array Payment settings data.
     */
    private function extractPaymentSettings(): array
    {
        $paymentData = [
            'id' => 'payment_settings',
            'title' => 'Payment Information',
            'content' => $this->buildPaymentContent(),
            'type' => 'woo_settings',
            'url' => admin_url('admin.php?page=wc-settings&tab=checkout'),
            'metadata' => [
                'setting_type' => 'payment',
                'last_updated' => current_time('mysql')
            ]
        ];

        return $paymentData;
    }

    /**
     * Build payment content
     *
     * @since 1.0.0
     * @return string Payment content.
     */
    private function buildPaymentContent(): string
    {
        $content = [];

        $paymentGateways = WC()->payment_gateways()->get_available_payment_gateways();

        foreach ($paymentGateways as $gateway) {
            if ($gateway->enabled === 'yes') {
                $content[] = 'Payment Method: ' . $gateway->get_title();
                if ($gateway->get_description()) {
                    $content[] = '  Description: ' . strip_tags($gateway->get_description());
                }
            }
        }

        return implode("\n", array_filter($content));
    }

    /**
     * Extract tax settings
     *
     * @since 1.0.0
     * @return array Tax settings data.
     */
    private function extractTaxSettings(): array
    {
        $taxData = [
            'id' => 'tax_settings',
            'title' => 'Tax Information',
            'content' => $this->buildTaxContent(),
            'type' => 'woo_settings',
            'url' => admin_url('admin.php?page=wc-settings&tab=tax'),
            'metadata' => [
                'setting_type' => 'tax',
                'last_updated' => current_time('mysql')
            ]
        ];

        return $taxData;
    }

    /**
     * Build tax content
     *
     * @since 1.0.0
     * @return string Tax content.
     */
    private function buildTaxContent(): string
    {
        $content = [];

        if (wc_tax_enabled()) {
            $content[] = 'Tax Status: Enabled';
            $content[] = 'Prices Include Tax: ' . (wc_prices_include_tax() ? 'Yes' : 'No');
            $content[] = 'Tax Display in Shop: ' . get_option('woocommerce_tax_display_shop');
            $content[] = 'Tax Display in Cart: ' . get_option('woocommerce_tax_display_cart');
        } else {
            $content[] = 'Tax Status: Disabled';
        }

        return implode("\n", array_filter($content));
    }

    /**
     * Extract term data for categories/tags
     *
     * @since 1.0.0
     * @param \WP_Term $term            Term object.
     * @param bool     $includeHierarchy Whether to include hierarchy information.
     *
     * @return array Term data array.
     */
    private function extractTermData(\WP_Term $term, bool $includeHierarchy = true): array
    {
        $termData = [
            'id' => $term->term_id,
            'title' => $this->sanitizeContent($term->name),
            'content' => $this->sanitizeContent($term->description ?: $term->name),
            'type' => 'taxonomy_term',
            'url' => get_term_link($term),
            'metadata' => [
                'taxonomy' => $term->taxonomy,
                'slug' => $term->slug,
                'count' => $term->count,
                'parent' => $term->parent,
                'last_updated' => current_time('mysql')
            ]
        ];

        if ($includeHierarchy && $term->parent) {
            $parent = get_term($term->parent, $term->taxonomy);
            if ($parent && !is_wp_error($parent)) {
                $termData['metadata']['parent_name'] = $parent->name;
            }
        }

        return $termData;
    }

    /**
     * Scan custom content types
     *
     * @since 1.0.0
     * @param string $contentType Content type to scan.
     * @param int    $offset      Starting offset.
     * @param int    $limit       Number of items to process.
     *
     * @return array Custom content data.
     */
    private function scanCustomContent(string $contentType, int $offset, int $limit): array
    {
        // This method can be extended to handle custom post types
        // For now, return empty array
        Utils::logDebug("Custom content type '{$contentType}' scanning not implemented yet");
        return [];
    }

    /**
     * Get product categories
     *
     * @since 1.0.0
     * @param \WC_Product $product Product object.
     *
     * @return array Array of category names.
     */
    private function getProductCategories(\WC_Product $product): array
    {
        $categoryIds = $product->get_category_ids();
        $categories = [];

        foreach ($categoryIds as $categoryId) {
            $term = get_term($categoryId, 'product_cat');
            if ($term && !is_wp_error($term)) {
                $categories[] = $term->name;
            }
        }

        return $categories;
    }

    /**
     * Get product tags
     *
     * @since 1.0.0
     * @param \WC_Product $product Product object.
     *
     * @return array Array of tag names.
     */
    private function getProductTags(\WC_Product $product): array
    {
        $tagIds = $product->get_tag_ids();
        $tags = [];

        foreach ($tagIds as $tagId) {
            $term = get_term($tagId, 'product_tag');
            if ($term && !is_wp_error($term)) {
                $tags[] = $term->name;
            }
        }

        return $tags;
    }

    /**
     * Get product attributes
     *
     * @since 1.0.0
     * @param \WC_Product $product Product object.
     *
     * @return array Array of attributes.
     */
    private function getProductAttributes(\WC_Product $product): array
    {
        $attributes = [];
        $productAttributes = $product->get_attributes();

        foreach ($productAttributes as $attribute) {
            if ($attribute->get_visible()) {
                $name = wc_attribute_label($attribute->get_name());

                if ($attribute->is_taxonomy()) {
                    $values = wc_get_product_terms($product->get_id(), $attribute->get_name(), ['fields' => 'names']);
                    $attributes[$name] = implode(', ', $values);
                } else {
                    $attributes[$name] = $attribute->get_options()[0] ?? '';
                }
            }
        }

        return $attributes;
    }

    /**
     * Get product variations
     *
     * @since 1.0.0
     * @param \WC_Product $product Product object.
     *
     * @return array Array of variations.
     */
    private function getProductVariations(\WC_Product $product): array
    {
        $variations = [];

        if ($product->is_type('variable')) {
            $variableProduct = new \WC_Product_Variable($product->get_id());
            $variationIds = $variableProduct->get_children();

            foreach ($variationIds as $variationId) {
                $variation = wc_get_product($variationId);
                if ($variation) {
                    $variations[] = [
                        'id' => $variationId,
                        'sku' => $variation->get_sku(),
                        'price' => $variation->get_price(),
                        'stock_status' => $variation->get_stock_status(),
                        'attributes' => $variation->get_variation_attributes()
                    ];
                }
            }
        }

        return $variations;
    }

    /**
     * Get post categories
     *
     * @since 1.0.0
     * @param \WP_Post $post Post object.
     *
     * @return array Array of category names.
     */
    private function getPostCategories(\WP_Post $post): array
    {
        $categories = get_the_category($post->ID);
        return array_map(function ($cat) {
            return $cat->name;
        }, $categories);
    }

    /**
     * Get post tags
     *
     * @since 1.0.0
     * @param \WP_Post $post Post object.
     *
     * @return array Array of tag names.
     */
    private function getPostTags(\WP_Post $post): array
    {
        $tags = get_the_tags($post->ID);
        return $tags ? array_map(function ($tag) {
            return $tag->name;
        }, $tags) : [];
    }

    /**
     * Sanitize content for storage
     *
     * @since 1.0.0
     * @param string $content Raw content to sanitize.
     *
     * @return string Sanitized content.
     */
    private function sanitizeContent(string $content): string
    {
        // Remove HTML tags and decode entities
        $content = html_entity_decode(strip_tags($content), ENT_QUOTES, 'UTF-8');

        // Remove extra whitespace
        $content = preg_replace('/\s+/', ' ', $content);

        // Trim and return
        return trim($content);
    }

    /**
     * Truncate content to maximum length
     *
     * @since 1.0.0
     * @param string $content Content to truncate.
     *
     * @return string Truncated content.
     */
    private function truncateContent(string $content): string
    {
        if (strlen($content) > self::MAX_CONTENT_LENGTH) {
            $content = substr($content, 0, self::MAX_CONTENT_LENGTH);

            // Truncate at last complete word
            $lastSpace = strrpos($content, ' ');
            if ($lastSpace !== false) {
                $content = substr($content, 0, $lastSpace);
            }

            $content .= '...';
        }

        return $content;
    }

    /**
     * Hook handler for product updates
     *
     * @since 1.0.0
     * @param int|\WC_Product $product Product ID or product object.
     *
     * @return void
     */
    public function onProductUpdated($product): void
    {
        $productId = is_numeric($product) ? absint($product) : $product->get_id();

        // Clear cache for updated product
        wp_cache_delete("product_{$productId}", self::CACHE_GROUP);

        Utils::logDebug('Product updated, cache cleared', ['product_id' => $productId]);
    }

    /**
     * Hook handler for post saves
     *
     * @since 1.0.0
     * @param int      $postId Post ID.
     * @param \WP_Post $post   Post object.
     * @param bool     $update Whether this is an existing post being updated.
     *
     * @return void
     */
    public function onPostSaved(int $postId, \WP_Post $post, bool $update): void
    {
        // Only handle published posts
        if ($post->post_status !== 'publish') {
            return;
        }

        // Clear cache for updated content
        wp_cache_delete("content_{$postId}", self::CACHE_GROUP);

        Utils::logDebug('Post updated, cache cleared', [
            'post_id' => $postId,
            'post_type' => $post->post_type
        ]);
    }

    /**
     * Get supported content types
     *
     * @since 1.0.0
     * @return array Array of supported content types.
     */
    public function getSupportedContentTypes(): array
    {
        return $this->supportedContentTypes;
    }

    /**
     * Get last scan statistics
     *
     * @since 1.0.0
     * @param string|null $contentType Specific content type or null for all.
     *
     * @return array Scan statistics.
     */
    public function getLastScanStats(?string $contentType = null): array
    {
        if ($contentType) {
            return $this->lastScanStats[$contentType] ?? [];
        }

        return $this->lastScanStats;
    }

    /**
     * Clear scanner cache
     *
     * @since 1.0.0
     * @param string|null $cacheKey Specific cache key or null for all.
     *
     * @return bool True on success.
     */
    public function clearCache(?string $cacheKey = null): bool
    {
        if ($cacheKey) {
            return wp_cache_delete($cacheKey, self::CACHE_GROUP);
        }

        return wp_cache_flush_group(self::CACHE_GROUP);
    }
}
