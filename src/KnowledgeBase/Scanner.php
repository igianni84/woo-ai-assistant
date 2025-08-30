<?php

/**
 * Knowledge Base Scanner Class
 *
 * Handles comprehensive scanning and indexing of WooCommerce products, pages, posts,
 * settings, and other site content for the AI-powered knowledge base. Implements
 * batch processing, caching, and multi-language support for optimal performance.
 *
 * @package WooAiAssistant
 * @subpackage KnowledgeBase
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\KnowledgeBase;

use WooAiAssistant\Common\Traits\Singleton;
use WooAiAssistant\Common\Utils;
use WooAiAssistant\Common\Logger;
use WooAiAssistant\Common\Cache;
use WooAiAssistant\Common\Sanitizer;
use WP_Query;
use WP_Error;
use Exception;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Scanner
 *
 * Comprehensive content scanner for building AI knowledge base.
 *
 * @since 1.0.0
 */
class Scanner
{
    use Singleton;

    /**
     * Batch size for processing large datasets
     *
     * @var int
     */
    private int $batchSize = 50;

    /**
     * Cache TTL for scan results (in seconds)
     *
     * @var int
     */
    private int $cacheTtl = 3600; // 1 hour

    /**
     * Supported content types for scanning
     *
     * @var array
     */
    private array $supportedContentTypes = [
        'product',
        'page',
        'post',
        'product_cat',
        'product_tag',
        'woocommerce_settings'
    ];

    /**
     * Multi-language support status
     *
     * @var bool
     */
    private bool $isMultilingual = false;

    /**
     * Current language for scanning
     *
     * @var string
     */
    private string $currentLanguage = 'en';

    /**
     * Initialize the scanner
     *
     * @return void
     */
    protected function init(): void
    {
        $this->detectMultilingualSupport();

        Logger::debug('Knowledge Base Scanner initialized', [
            'batch_size' => $this->batchSize,
            'cache_ttl' => $this->cacheTtl,
            'multilingual' => $this->isMultilingual,
            'current_language' => $this->currentLanguage
        ]);
    }

    /**
     * Scan all WooCommerce products with variations, attributes, and categories
     *
     * Processes all published WooCommerce products including variable products
     * with their variations, custom attributes, categories, and metadata.
     *
     * @since 1.0.0
     * @param array $args Optional. Arguments for product scanning.
     * @param int   $args['limit'] Maximum number of products to scan per batch. Default 50.
     * @param bool  $args['force_refresh'] Whether to bypass cache. Default false.
     * @param array $args['include_ids'] Specific product IDs to scan. Default empty.
     * @param array $args['exclude_ids'] Product IDs to exclude. Default empty.
     *
     * @return array Array of product data formatted for indexing.
     *               Each element contains 'id', 'type', 'title', 'content', 'url', 'metadata', 'language', 'last_modified'.
     *
     * @throws Exception When WooCommerce is not active or scanning fails.
     *
     * @example
     * ```php
     * $scanner = Scanner::getInstance();
     * $products = $scanner->scanProducts(['limit' => 100, 'force_refresh' => true]);
     * ```
     */
    public function scanProducts(array $args = []): array
    {
        try {
            if (!Utils::isWooCommerceActive()) {
                throw new Exception('WooCommerce is not active');
            }

            $defaults = [
                'limit' => $this->batchSize,
                'force_refresh' => false,
                'include_ids' => [],
                'exclude_ids' => []
            ];

            $args = wp_parse_args($args, $defaults);

            Logger::info('Starting product scan', [
                'limit' => $args['limit'],
                'force_refresh' => $args['force_refresh'],
                'include_count' => count($args['include_ids']),
                'exclude_count' => count($args['exclude_ids'])
            ]);

            // Check cache first
            $cacheKey = $this->generateCacheKey('products', $args);
            if (!$args['force_refresh'] && $cached = Cache::getInstance()->get($cacheKey)) {
                Logger::debug('Returning cached product scan results');
                return $cached;
            }

            $products = [];
            $processed = 0;
            $errors = 0;

            // Build query arguments
            $queryArgs = [
                'post_type' => 'product',
                'post_status' => 'publish',
                'posts_per_page' => $args['limit'],
                'meta_query' => [
                    [
                        'key' => '_visibility',
                        'value' => ['hidden', 'search'],
                        'compare' => 'NOT IN'
                    ]
                ]
            ];

            if (!empty($args['include_ids'])) {
                $queryArgs['post__in'] = $args['include_ids'];
            }

            if (!empty($args['exclude_ids'])) {
                $queryArgs['post__not_in'] = $args['exclude_ids'];
            }

            $query = new WP_Query($queryArgs);

            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();

                    try {
                        $productData = $this->processProduct(get_post());
                        if ($productData) {
                            $products[] = $productData;
                        }
                        $processed++;
                    } catch (Exception $e) {
                        $errors++;
                        Logger::warning('Failed to process product', [
                            'product_id' => get_the_ID(),
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            wp_reset_postdata();

            Logger::info('Product scan completed', [
                'processed' => $processed,
                'successful' => count($products),
                'errors' => $errors
            ]);

            // Cache results
            Cache::getInstance()->set($cacheKey, $products, $this->cacheTtl);

            return $products;
        } catch (Exception $e) {
            Logger::error('Product scanning failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Scan important pages (shipping, returns, terms, privacy, etc.)
     *
     * Scans static pages that contain important information for customer support,
     * including WooCommerce-specific pages like cart, checkout, my account.
     *
     * @since 1.0.0
     * @param array $args Optional. Arguments for page scanning.
     * @param bool  $args['include_wc_pages'] Include WooCommerce pages. Default true.
     * @param bool  $args['include_legal_pages'] Include privacy, terms pages. Default true.
     * @param array $args['custom_page_ids'] Additional page IDs to include. Default empty.
     *
     * @return array Array of page data formatted for indexing.
     *
     * @throws Exception When page scanning fails.
     */
    public function scanPages(array $args = []): array
    {
        try {
            $defaults = [
                'include_wc_pages' => true,
                'include_legal_pages' => true,
                'custom_page_ids' => []
            ];

            $args = wp_parse_args($args, $defaults);

            Logger::info('Starting page scan', $args);

            $cacheKey = $this->generateCacheKey('pages', $args);
            if ($cached = Cache::getInstance()->get($cacheKey)) {
                Logger::debug('Returning cached page scan results');
                return $cached;
            }

            $pages = [];
            $pageIds = [];

            // Get WooCommerce pages
            if ($args['include_wc_pages'] && Utils::isWooCommerceActive()) {
                $wcPageIds = [
                    wc_get_page_id('shop'),
                    wc_get_page_id('cart'),
                    wc_get_page_id('checkout'),
                    wc_get_page_id('myaccount'),
                    wc_get_page_id('terms')
                ];
                $pageIds = array_merge($pageIds, array_filter($wcPageIds));
            }

            // Get legal/policy pages
            if ($args['include_legal_pages']) {
                $legalPageIds = [
                    get_option('wp_page_for_privacy_policy'),
                    $this->findPageBySlug('terms-of-service'),
                    $this->findPageBySlug('refund-policy'),
                    $this->findPageBySlug('shipping-policy'),
                    $this->findPageBySlug('return-policy')
                ];
                $pageIds = array_merge($pageIds, array_filter($legalPageIds));
            }

            // Add custom page IDs
            if (!empty($args['custom_page_ids'])) {
                $pageIds = array_merge($pageIds, $args['custom_page_ids']);
            }

            // Remove duplicates and get unique IDs
            $pageIds = array_unique(array_filter($pageIds));

            foreach ($pageIds as $pageId) {
                try {
                    $pageData = $this->processPage($pageId);
                    if ($pageData) {
                        $pages[] = $pageData;
                    }
                } catch (Exception $e) {
                    Logger::warning('Failed to process page', [
                        'page_id' => $pageId,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Logger::info('Page scan completed', [
                'total_pages' => count($pageIds),
                'successful' => count($pages)
            ]);

            // Cache results
            Cache::getInstance()->set($cacheKey, $pages, $this->cacheTtl);

            return $pages;
        } catch (Exception $e) {
            Logger::error('Page scanning failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Scan blog posts and FAQs
     *
     * Scans published blog posts and FAQ entries that can provide context
     * for customer questions and support scenarios.
     *
     * @since 1.0.0
     * @param array $args Optional. Arguments for post scanning.
     * @param int   $args['limit'] Maximum number of posts to scan. Default 100.
     * @param array $args['post_types'] Post types to scan. Default ['post'].
     * @param array $args['categories'] Category IDs to filter. Default empty.
     *
     * @return array Array of post data formatted for indexing.
     *
     * @throws Exception When post scanning fails.
     */
    public function scanPosts(array $args = []): array
    {
        try {
            $defaults = [
                'limit' => 100,
                'post_types' => ['post'],
                'categories' => []
            ];

            $args = wp_parse_args($args, $defaults);

            Logger::info('Starting post scan', $args);

            $cacheKey = $this->generateCacheKey('posts', $args);
            if ($cached = Cache::getInstance()->get($cacheKey)) {
                Logger::debug('Returning cached post scan results');
                return $cached;
            }

            $posts = [];

            $queryArgs = [
                'post_type' => $args['post_types'],
                'post_status' => 'publish',
                'posts_per_page' => $args['limit'],
                'orderby' => 'date',
                'order' => 'DESC'
            ];

            if (!empty($args['categories'])) {
                $queryArgs['category__in'] = $args['categories'];
            }

            $query = new WP_Query($queryArgs);

            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();

                    try {
                        $postData = $this->processPost(get_post());
                        if ($postData) {
                            $posts[] = $postData;
                        }
                    } catch (Exception $e) {
                        Logger::warning('Failed to process post', [
                            'post_id' => get_the_ID(),
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            wp_reset_postdata();

            Logger::info('Post scan completed', [
                'successful' => count($posts)
            ]);

            // Cache results
            Cache::getInstance()->set($cacheKey, $posts, $this->cacheTtl);

            return $posts;
        } catch (Exception $e) {
            Logger::error('Post scanning failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Scan WooCommerce settings and configuration
     *
     * Extracts important WooCommerce settings including shipping zones,
     * payment methods, tax settings, and general store configuration.
     *
     * @since 1.0.0
     * @param array $args Optional. Arguments for settings scanning.
     * @param bool  $args['include_shipping'] Include shipping settings. Default true.
     * @param bool  $args['include_payment'] Include payment method settings. Default true.
     * @param bool  $args['include_tax'] Include tax settings. Default true.
     *
     * @return array Array of settings data formatted for indexing.
     *
     * @throws Exception When WooCommerce is not active or settings scanning fails.
     */
    public function scanWooCommerceSettings(array $args = []): array
    {
        try {
            if (!Utils::isWooCommerceActive()) {
                throw new Exception('WooCommerce is not active');
            }

            $defaults = [
                'include_shipping' => true,
                'include_payment' => true,
                'include_tax' => true
            ];

            $args = wp_parse_args($args, $defaults);

            Logger::info('Starting WooCommerce settings scan', $args);

            $cacheKey = $this->generateCacheKey('wc_settings', $args);
            if ($cached = Cache::getInstance()->get($cacheKey)) {
                Logger::debug('Returning cached WooCommerce settings scan results');
                return $cached;
            }

            $settings = [];

            // General store settings
            $settings[] = $this->processGeneralSettings();

            // Shipping settings
            if ($args['include_shipping']) {
                $settings = array_merge($settings, $this->processShippingSettings());
            }

            // Payment method settings
            if ($args['include_payment']) {
                $settings = array_merge($settings, $this->processPaymentSettings());
            }

            // Tax settings
            if ($args['include_tax']) {
                $settings[] = $this->processTaxSettings();
            }

            Logger::info('WooCommerce settings scan completed', [
                'settings_count' => count($settings)
            ]);

            // Cache results
            Cache::getInstance()->set($cacheKey, $settings, $this->cacheTtl);

            return array_filter($settings);
        } catch (Exception $e) {
            Logger::error('WooCommerce settings scanning failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Scan product categories and tags
     *
     * Processes all product categories and tags to understand the store's
     * product organization and taxonomy structure.
     *
     * @since 1.0.0
     * @param array $args Optional. Arguments for category scanning.
     * @param bool  $args['include_categories'] Include product categories. Default true.
     * @param bool  $args['include_tags'] Include product tags. Default true.
     *
     * @return array Array of taxonomy data formatted for indexing.
     *
     * @throws Exception When WooCommerce is not active or taxonomy scanning fails.
     */
    public function scanCategories(array $args = []): array
    {
        try {
            if (!Utils::isWooCommerceActive()) {
                throw new Exception('WooCommerce is not active');
            }

            $defaults = [
                'include_categories' => true,
                'include_tags' => true
            ];

            $args = wp_parse_args($args, $defaults);

            Logger::info('Starting category/taxonomy scan', $args);

            $cacheKey = $this->generateCacheKey('categories', $args);
            if ($cached = Cache::getInstance()->get($cacheKey)) {
                Logger::debug('Returning cached category scan results');
                return $cached;
            }

            $taxonomies = [];

            // Product categories
            if ($args['include_categories']) {
                $categories = get_terms([
                    'taxonomy' => 'product_cat',
                    'hide_empty' => false,
                    'number' => 200
                ]);

                if (!is_wp_error($categories)) {
                    foreach ($categories as $category) {
                        $taxonomies[] = $this->processTaxonomy($category, 'product_cat');
                    }
                }
            }

            // Product tags
            if ($args['include_tags']) {
                $tags = get_terms([
                    'taxonomy' => 'product_tag',
                    'hide_empty' => false,
                    'number' => 200
                ]);

                if (!is_wp_error($tags)) {
                    foreach ($tags as $tag) {
                        $taxonomies[] = $this->processTaxonomy($tag, 'product_tag');
                    }
                }
            }

            Logger::info('Category/taxonomy scan completed', [
                'taxonomies_count' => count($taxonomies)
            ]);

            // Cache results
            Cache::getInstance()->set($cacheKey, $taxonomies, $this->cacheTtl);

            return $taxonomies;
        } catch (Exception $e) {
            Logger::error('Category/taxonomy scanning failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Orchestrate all scanning methods
     *
     * Runs all scanning methods in the correct order to build a comprehensive
     * knowledge base. Handles errors gracefully to ensure partial success.
     *
     * @since 1.0.0
     * @param array $args Optional. Arguments for full scanning.
     * @param bool  $args['include_products'] Include product scanning. Default true.
     * @param bool  $args['include_pages'] Include page scanning. Default true.
     * @param bool  $args['include_posts'] Include post scanning. Default true.
     * @param bool  $args['include_settings'] Include WooCommerce settings. Default true.
     * @param bool  $args['include_categories'] Include categories/tags. Default true.
     * @param bool  $args['force_refresh'] Force refresh of all cached data. Default false.
     *
     * @return array Comprehensive scan results with all content types.
     *               Contains 'success' boolean, 'data' array, and 'errors' array.
     *
     * @example
     * ```php
     * $scanner = Scanner::getInstance();
     * $result = $scanner->scanAll(['force_refresh' => true]);
     * if ($result['success']) {
     *     // Process $result['data']
     * }
     * ```
     */
    public function scanAll(array $args = []): array
    {
        $startTime = microtime(true);

        $defaults = [
            'include_products' => true,
            'include_pages' => true,
            'include_posts' => true,
            'include_settings' => true,
            'include_categories' => true,
            'force_refresh' => false
        ];

        $args = wp_parse_args($args, $defaults);

        Logger::info('Starting comprehensive knowledge base scan', $args);

        $allData = [];
        $errors = [];
        $summary = [
            'products' => 0,
            'pages' => 0,
            'posts' => 0,
            'settings' => 0,
            'categories' => 0
        ];

        // Products
        if ($args['include_products']) {
            try {
                $products = $this->scanProducts(['force_refresh' => $args['force_refresh']]);
                $allData = array_merge($allData, $products);
                $summary['products'] = count($products);
                Logger::info("Product scan completed: {$summary['products']} items");
            } catch (Exception $e) {
                $errors[] = ['type' => 'products', 'error' => $e->getMessage()];
                Logger::error('Product scan failed during scanAll', ['error' => $e->getMessage()]);
            }
        }

        // Pages
        if ($args['include_pages']) {
            try {
                $pages = $this->scanPages();
                $allData = array_merge($allData, $pages);
                $summary['pages'] = count($pages);
                Logger::info("Page scan completed: {$summary['pages']} items");
            } catch (Exception $e) {
                $errors[] = ['type' => 'pages', 'error' => $e->getMessage()];
                Logger::error('Page scan failed during scanAll', ['error' => $e->getMessage()]);
            }
        }

        // Posts
        if ($args['include_posts']) {
            try {
                $posts = $this->scanPosts();
                $allData = array_merge($allData, $posts);
                $summary['posts'] = count($posts);
                Logger::info("Post scan completed: {$summary['posts']} items");
            } catch (Exception $e) {
                $errors[] = ['type' => 'posts', 'error' => $e->getMessage()];
                Logger::error('Post scan failed during scanAll', ['error' => $e->getMessage()]);
            }
        }

        // WooCommerce settings
        if ($args['include_settings']) {
            try {
                $settings = $this->scanWooCommerceSettings();
                $allData = array_merge($allData, $settings);
                $summary['settings'] = count($settings);
                Logger::info("Settings scan completed: {$summary['settings']} items");
            } catch (Exception $e) {
                $errors[] = ['type' => 'settings', 'error' => $e->getMessage()];
                Logger::error('Settings scan failed during scanAll', ['error' => $e->getMessage()]);
            }
        }

        // Categories and tags
        if ($args['include_categories']) {
            try {
                $categories = $this->scanCategories();
                $allData = array_merge($allData, $categories);
                $summary['categories'] = count($categories);
                Logger::info("Category scan completed: {$summary['categories']} items");
            } catch (Exception $e) {
                $errors[] = ['type' => 'categories', 'error' => $e->getMessage()];
                Logger::error('Category scan failed during scanAll', ['error' => $e->getMessage()]);
            }
        }

        $duration = round(microtime(true) - $startTime, 2);
        $totalItems = array_sum($summary);
        $success = empty($errors) || $totalItems > 0;

        Logger::info('Comprehensive scan completed', [
            'duration_seconds' => $duration,
            'total_items' => $totalItems,
            'summary' => $summary,
            'errors_count' => count($errors),
            'success' => $success
        ]);

        return [
            'success' => $success,
            'data' => $allData,
            'summary' => $summary,
            'errors' => $errors,
            'duration' => $duration,
            'timestamp' => current_time('mysql')
        ];
    }

    /**
     * Process a single product for indexing
     *
     * @param \WP_Post $post Product post object
     * @return array|null Processed product data or null on failure
     */
    private function processProduct(\WP_Post $post): ?array
    {
        $product = wc_get_product($post->ID);
        if (!$product) {
            return null;
        }

        $content = [];
        $content[] = Sanitizer::sanitizeText($product->get_name());
        $content[] = Sanitizer::sanitizeText($product->get_short_description());
        $content[] = Sanitizer::sanitizeText($product->get_description());

        // Add product attributes
        $attributes = $product->get_attributes();
        foreach ($attributes as $attribute) {
            if ($attribute->is_taxonomy()) {
                $terms = wp_get_post_terms($post->ID, $attribute->get_name());
                if (!is_wp_error($terms)) {
                    foreach ($terms as $term) {
                        $content[] = $attribute->get_name() . ': ' . $term->name;
                    }
                }
            } else {
                $content[] = $attribute->get_name() . ': ' . implode(', ', $attribute->get_options());
            }
        }

        // Add categories
        $categories = wp_get_post_terms($post->ID, 'product_cat');
        if (!is_wp_error($categories)) {
            foreach ($categories as $category) {
                $content[] = 'Category: ' . $category->name;
            }
        }

        // Add tags
        $tags = wp_get_post_terms($post->ID, 'product_tag');
        if (!is_wp_error($tags)) {
            foreach ($tags as $tag) {
                $content[] = 'Tag: ' . $tag->name;
            }
        }

        $metadata = [
            'price' => $product->get_price(),
            'regular_price' => $product->get_regular_price(),
            'sale_price' => $product->get_sale_price(),
            'stock_status' => $product->get_stock_status(),
            'stock_quantity' => $product->get_stock_quantity(),
            'sku' => $product->get_sku(),
            'type' => $product->get_type(),
            'virtual' => $product->is_virtual(),
            'downloadable' => $product->is_downloadable(),
            'featured' => $product->is_featured()
        ];

        // Add variation data for variable products
        if ($product->is_type('variable')) {
            $variations = $product->get_children();
            $variationData = [];
            foreach ($variations as $variationId) {
                $variation = wc_get_product($variationId);
                if ($variation) {
                    $variationData[] = [
                        'id' => $variationId,
                        'price' => $variation->get_price(),
                        'attributes' => $variation->get_variation_attributes()
                    ];
                }
            }
            $metadata['variations'] = $variationData;
        }

        return [
            'id' => $post->ID,
            'type' => 'product',
            'title' => Sanitizer::sanitizeText($product->get_name()),
            'content' => implode(' ', array_filter($content)),
            'url' => get_permalink($post->ID),
            'metadata' => $metadata,
            'language' => $this->getCurrentLanguage(),
            'last_modified' => get_post_modified_time('Y-m-d H:i:s', false, $post->ID)
        ];
    }

    /**
     * Process a single page for indexing
     *
     * @param int $pageId Page ID
     * @return array|null Processed page data or null on failure
     */
    private function processPage(int $pageId): ?array
    {
        $page = get_post($pageId);
        if (!$page || $page->post_status !== 'publish') {
            return null;
        }

        $content = [];
        $content[] = Sanitizer::sanitizeText($page->post_title);
        $content[] = Sanitizer::sanitizeText($page->post_content);
        $content[] = Sanitizer::sanitizeText($page->post_excerpt);

        // Add custom fields
        $customFields = get_post_meta($pageId);
        foreach ($customFields as $key => $values) {
            if (strpos($key, '_') !== 0) { // Skip private fields
                $content[] = $key . ': ' . implode(', ', array_map([Sanitizer::class, 'sanitizeText'], $values));
            }
        }

        return [
            'id' => $pageId,
            'type' => 'page',
            'title' => Sanitizer::sanitizeText($page->post_title),
            'content' => implode(' ', array_filter($content)),
            'url' => get_permalink($pageId),
            'metadata' => [
                'template' => get_page_template_slug($pageId),
                'parent_id' => $page->post_parent,
                'menu_order' => $page->menu_order
            ],
            'language' => $this->getCurrentLanguage(),
            'last_modified' => $page->post_modified
        ];
    }

    /**
     * Process a single post for indexing
     *
     * @param \WP_Post $post Post object
     * @return array|null Processed post data or null on failure
     */
    private function processPost(\WP_Post $post): ?array
    {
        $content = [];
        $content[] = Sanitizer::sanitizeText($post->post_title);
        $content[] = Sanitizer::sanitizeText($post->post_content);
        $content[] = Sanitizer::sanitizeText($post->post_excerpt);

        // Add categories
        $categories = get_the_category($post->ID);
        foreach ($categories as $category) {
            $content[] = 'Category: ' . $category->name;
        }

        // Add tags
        $tags = get_the_tags($post->ID);
        if ($tags) {
            foreach ($tags as $tag) {
                $content[] = 'Tag: ' . $tag->name;
            }
        }

        return [
            'id' => $post->ID,
            'type' => 'post',
            'title' => Sanitizer::sanitizeText($post->post_title),
            'content' => implode(' ', array_filter($content)),
            'url' => get_permalink($post->ID),
            'metadata' => [
                'author_id' => $post->post_author,
                'author_name' => get_the_author_meta('display_name', $post->post_author),
                'comment_count' => $post->comment_count,
                'post_type' => $post->post_type
            ],
            'language' => $this->getCurrentLanguage(),
            'last_modified' => $post->post_modified
        ];
    }

    /**
     * Process general WooCommerce settings
     *
     * @return array Processed general settings data
     */
    private function processGeneralSettings(): array
    {
        $settings = [
            'store_address' => get_option('woocommerce_store_address'),
            'store_address_2' => get_option('woocommerce_store_address_2'),
            'store_city' => get_option('woocommerce_store_city'),
            'default_country' => get_option('woocommerce_default_country'),
            'store_postcode' => get_option('woocommerce_store_postcode'),
            'currency' => get_option('woocommerce_currency'),
            'currency_pos' => get_option('woocommerce_currency_pos'),
            'price_thousand_sep' => get_option('woocommerce_price_thousand_sep'),
            'price_decimal_sep' => get_option('woocommerce_price_decimal_sep'),
            'price_num_decimals' => get_option('woocommerce_price_num_decimals')
        ];

        $content = [];
        foreach ($settings as $key => $value) {
            if (!empty($value)) {
                $content[] = ucfirst(str_replace('_', ' ', $key)) . ': ' . $value;
            }
        }

        return [
            'id' => 'wc_general_settings',
            'type' => 'woocommerce_settings',
            'title' => 'WooCommerce General Settings',
            'content' => implode(' ', $content),
            'url' => admin_url('admin.php?page=wc-settings'),
            'metadata' => $settings,
            'language' => $this->getCurrentLanguage(),
            'last_modified' => current_time('Y-m-d H:i:s')
        ];
    }

    /**
     * Process shipping settings and zones
     *
     * @return array Array of processed shipping data
     */
    private function processShippingSettings(): array
    {
        $shippingData = [];

        // Get shipping zones
        $zones = WC_Shipping_Zones::get_zones();

        foreach ($zones as $zone) {
            $methods = [];
            foreach ($zone['shipping_methods'] as $method) {
                $methods[] = $method->get_method_title() . ' (' . $method->get_title() . ')';
            }

            $shippingData[] = [
                'id' => 'shipping_zone_' . $zone['id'],
                'type' => 'woocommerce_settings',
                'title' => 'Shipping Zone: ' . $zone['zone_name'],
                'content' => 'Shipping zone ' . $zone['zone_name'] . ' serves: ' . implode(', ', $zone['formatted_zone_location']) . '. Available methods: ' . implode(', ', $methods),
                'url' => admin_url('admin.php?page=wc-settings&tab=shipping&zone_id=' . $zone['id']),
                'metadata' => [
                    'zone_id' => $zone['id'],
                    'zone_name' => $zone['zone_name'],
                    'locations' => $zone['zone_locations'],
                    'methods' => $methods
                ],
                'language' => $this->getCurrentLanguage(),
                'last_modified' => current_time('Y-m-d H:i:s')
            ];
        }

        return $shippingData;
    }

    /**
     * Process payment method settings
     *
     * @return array Array of processed payment data
     */
    private function processPaymentSettings(): array
    {
        $paymentData = [];
        $gateways = WC()->payment_gateways()->get_available_payment_gateways();

        foreach ($gateways as $gateway) {
            $paymentData[] = [
                'id' => 'payment_' . $gateway->id,
                'type' => 'woocommerce_settings',
                'title' => 'Payment Method: ' . $gateway->get_title(),
                'content' => 'Payment method ' . $gateway->get_title() . '. Description: ' . strip_tags($gateway->get_description()),
                'url' => admin_url('admin.php?page=wc-settings&tab=checkout&section=' . $gateway->id),
                'metadata' => [
                    'gateway_id' => $gateway->id,
                    'enabled' => $gateway->enabled,
                    'title' => $gateway->get_title(),
                    'description' => strip_tags($gateway->get_description()),
                    'supports' => $gateway->supports
                ],
                'language' => $this->getCurrentLanguage(),
                'last_modified' => current_time('Y-m-d H:i:s')
            ];
        }

        return $paymentData;
    }

    /**
     * Process tax settings
     *
     * @return array Processed tax settings data
     */
    private function processTaxSettings(): array
    {
        $taxSettings = [
            'prices_include_tax' => get_option('woocommerce_prices_include_tax'),
            'tax_based_on' => get_option('woocommerce_tax_based_on'),
            'shipping_tax_class' => get_option('woocommerce_shipping_tax_class'),
            'tax_round_at_subtotal' => get_option('woocommerce_tax_round_at_subtotal'),
            'tax_display_shop' => get_option('woocommerce_tax_display_shop'),
            'tax_display_cart' => get_option('woocommerce_tax_display_cart')
        ];

        $content = [];
        foreach ($taxSettings as $key => $value) {
            if (!empty($value)) {
                $content[] = ucfirst(str_replace('_', ' ', $key)) . ': ' . $value;
            }
        }

        return [
            'id' => 'wc_tax_settings',
            'type' => 'woocommerce_settings',
            'title' => 'WooCommerce Tax Settings',
            'content' => implode(' ', $content),
            'url' => admin_url('admin.php?page=wc-settings&tab=tax'),
            'metadata' => $taxSettings,
            'language' => $this->getCurrentLanguage(),
            'last_modified' => current_time('Y-m-d H:i:s')
        ];
    }

    /**
     * Process taxonomy term for indexing
     *
     * @param \WP_Term $term Term object
     * @param string $taxonomy Taxonomy name
     * @return array Processed taxonomy data
     */
    private function processTaxonomy(\WP_Term $term, string $taxonomy): array
    {
        $content = [];
        $content[] = $term->name;
        $content[] = $term->description;

        return [
            'id' => $taxonomy . '_' . $term->term_id,
            'type' => $taxonomy,
            'title' => Sanitizer::sanitizeText($term->name),
            'content' => implode(' ', array_filter($content)),
            'url' => get_term_link($term),
            'metadata' => [
                'term_id' => $term->term_id,
                'taxonomy' => $taxonomy,
                'slug' => $term->slug,
                'parent' => $term->parent,
                'count' => $term->count
            ],
            'language' => $this->getCurrentLanguage(),
            'last_modified' => current_time('Y-m-d H:i:s')
        ];
    }

    /**
     * Find page by slug
     *
     * @param string $slug Page slug
     * @return int|null Page ID or null if not found
     */
    private function findPageBySlug(string $slug): ?int
    {
        $page = get_page_by_path($slug);
        return $page ? $page->ID : null;
    }

    /**
     * Detect multilingual plugin support
     *
     * @return void
     */
    private function detectMultilingualSupport(): void
    {
        if (function_exists('icl_get_languages') || function_exists('pll_get_post_language')) {
            $this->isMultilingual = true;
            $this->currentLanguage = $this->getCurrentLanguage();
        }
    }

    /**
     * Get current language
     *
     * @return string Language code
     */
    private function getCurrentLanguage(): string
    {
        if (function_exists('icl_get_languages')) {
            // WPML
            return ICL_LANGUAGE_CODE;
        } elseif (function_exists('pll_current_language')) {
            // Polylang
            return pll_current_language() ?: 'en';
        }

        return 'en';
    }

    /**
     * Generate cache key for scan results
     *
     * @param string $type Scan type
     * @param array $args Scan arguments
     * @return string Cache key
     */
    private function generateCacheKey(string $type, array $args): string
    {
        return 'woo_ai_kb_scan_' . $type . '_' . md5(serialize($args) . $this->currentLanguage);
    }

    /**
     * Get scanner statistics
     *
     * @return array Scanner statistics and configuration
     */
    public function getStatistics(): array
    {
        return [
            'batch_size' => $this->batchSize,
            'cache_ttl' => $this->cacheTtl,
            'supported_content_types' => $this->supportedContentTypes,
            'multilingual_support' => $this->isMultilingual,
            'current_language' => $this->currentLanguage,
            'woocommerce_active' => Utils::isWooCommerceActive()
        ];
    }

    /**
     * Set batch size for processing
     *
     * @param int $size Batch size (must be positive)
     * @return void
     * @throws Exception When batch size is invalid
     */
    public function setBatchSize(int $size): void
    {
        if ($size <= 0) {
            throw new Exception('Batch size must be a positive integer');
        }

        $this->batchSize = $size;
        Logger::debug('Batch size updated', ['new_size' => $size]);
    }

    /**
     * Set cache TTL
     *
     * @param int $ttl Cache time-to-live in seconds
     * @return void
     * @throws Exception When TTL is invalid
     */
    public function setCacheTtl(int $ttl): void
    {
        if ($ttl < 0) {
            throw new Exception('Cache TTL must be non-negative');
        }

        $this->cacheTtl = $ttl;
        Logger::debug('Cache TTL updated', ['new_ttl' => $ttl]);
    }
}
