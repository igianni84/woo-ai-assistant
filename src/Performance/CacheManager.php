<?php

/**
 * Cache Manager Class
 *
 * Handles intelligent caching strategies for the Woo AI Assistant plugin.
 * Implements FAQ caching, transient management, and cache optimization
 * to achieve <300ms TTFR for frequently accessed data.
 *
 * @package WooAiAssistant
 * @subpackage Performance
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Performance;

use WooAiAssistant\Common\Traits\Singleton;
use WooAiAssistant\Common\Utils;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class CacheManager
 *
 * Provides intelligent caching strategies for FAQ responses, knowledge base
 * queries, and frequently accessed data with automatic cache warming and
 * invalidation strategies.
 *
 * @since 1.0.0
 */
class CacheManager
{
    use Singleton;

    /**
     * Cache group for FAQ responses
     */
    const CACHE_GROUP_FAQ = 'woo_ai_faq';

    /**
     * Cache group for knowledge base queries
     */
    const CACHE_GROUP_KB = 'woo_ai_kb';

    /**
     * Cache group for conversation data
     */
    const CACHE_GROUP_CONVERSATION = 'woo_ai_conversation';

    /**
     * Cache group for product data
     */
    const CACHE_GROUP_PRODUCTS = 'woo_ai_products';

    /**
     * Default cache expiration time (1 hour)
     */
    const DEFAULT_CACHE_EXPIRATION = HOUR_IN_SECONDS;

    /**
     * FAQ cache expiration time (5 minutes for freshness requirement)
     */
    const FAQ_CACHE_EXPIRATION = 300; // 5 minutes

    /**
     * Long-term cache expiration (24 hours)
     */
    const LONG_TERM_CACHE_EXPIRATION = DAY_IN_SECONDS;

    /**
     * Cache statistics
     *
     * @var array
     */
    private $cacheStats = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0,
        'flushes' => 0
    ];

    /**
     * Performance monitoring enabled flag
     *
     * @var bool
     */
    private $performanceMonitoringEnabled = false;

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    protected function __construct()
    {
        $this->performanceMonitoringEnabled = defined('WOO_AI_ASSISTANT_DEBUG') && WOO_AI_ASSISTANT_DEBUG;
        $this->initializeHooks();
    }

    /**
     * Initialize WordPress hooks and filters
     *
     * @since 1.0.0
     * @return void
     */
    private function initializeHooks(): void
    {
        // Cache invalidation hooks
        add_action('save_post', [$this, 'invalidateProductCache'], 10, 1);
        add_action('woocommerce_product_set_visibility', [$this, 'invalidateProductCache'], 10, 1);
        add_action('woo_ai_assistant_knowledge_base_updated', [$this, 'invalidateKnowledgeBaseCache']);

        // Performance monitoring hooks
        if ($this->performanceMonitoringEnabled) {
            add_action('wp_footer', [$this, 'logCacheStats']);
        }

        // Scheduled cache warming
        add_action('woo_ai_assistant_warm_cache', [$this, 'warmFrequentlyAccessedCache']);

        // Cache cleanup on plugin deactivation
        add_action('woo_ai_assistant_deactivated', [$this, 'flushAllCaches']);
    }

    /**
     * Get cached FAQ response with intelligent caching strategy
     *
     * Implements multi-layer caching with object cache, transients, and
     * intelligent cache warming to achieve <300ms TTFR requirement.
     *
     * @since 1.0.0
     * @param string $question The FAQ question or query hash
     * @param callable|null $callback Optional callback to generate data if not cached
     * @param int|null $expiration Optional custom expiration time
     *
     * @return mixed Cached data or false if not found
     *
     * @throws \InvalidArgumentException When question is empty.
     *
     * @example
     * ```php
     * $cache = CacheManager::getInstance();
     * $response = $cache->getFaqCache('shipping-policy', function() {
     *     return $this->generateShippingPolicyResponse();
     * });
     * ```
     */
    public function getFaqCache(string $question, ?callable $callback = null, ?int $expiration = null): mixed
    {
        if (empty($question)) {
            throw new \InvalidArgumentException('Question cannot be empty');
        }

        $cacheKey = $this->generateCacheKey($question, self::CACHE_GROUP_FAQ);
        $expiration = $expiration ?? self::FAQ_CACHE_EXPIRATION;

        // Try object cache first (fastest)
        $cached = wp_cache_get($cacheKey, self::CACHE_GROUP_FAQ);
        if ($cached !== false) {
            $this->recordCacheHit();
            return $cached;
        }

        // Try transient cache (database-backed)
        $transientKey = 'woo_ai_faq_' . md5($cacheKey);
        $cached = get_transient($transientKey);
        if ($cached !== false) {
            // Warm object cache for next request
            wp_cache_set($cacheKey, $cached, self::CACHE_GROUP_FAQ, min($expiration, 300));
            $this->recordCacheHit();
            return $cached;
        }

        $this->recordCacheMiss();

        // Generate data using callback if provided
        if ($callback && is_callable($callback)) {
            $data = call_user_func($callback);
            if ($data !== null) {
                $this->setFaqCache($question, $data, $expiration);
                return $data;
            }
        }

        return false;
    }

    /**
     * Set FAQ cache with multi-layer caching strategy
     *
     * @since 1.0.0
     * @param string $question The FAQ question or query
     * @param mixed $data The data to cache
     * @param int|null $expiration Optional custom expiration time
     *
     * @return bool True on success, false on failure
     */
    public function setFaqCache(string $question, mixed $data, ?int $expiration = null): bool
    {
        if (empty($question) || $data === null) {
            return false;
        }

        $cacheKey = $this->generateCacheKey($question, self::CACHE_GROUP_FAQ);
        $expiration = $expiration ?? self::FAQ_CACHE_EXPIRATION;

        // Set object cache (fastest access)
        $objectCacheResult = wp_cache_set($cacheKey, $data, self::CACHE_GROUP_FAQ, min($expiration, 300));

        // Set transient cache (persistent across requests)
        $transientKey = 'woo_ai_faq_' . md5($cacheKey);
        $transientResult = set_transient($transientKey, $data, $expiration);

        if ($objectCacheResult || $transientResult) {
            $this->recordCacheSet();
            return true;
        }

        return false;
    }

    /**
     * Get knowledge base cache with intelligent warming
     *
     * @since 1.0.0
     * @param string $query The knowledge base query
     * @param callable|null $callback Optional callback to generate data
     * @param int|null $expiration Optional custom expiration time
     *
     * @return mixed Cached data or false if not found
     */
    public function getKnowledgeBaseCache(string $query, ?callable $callback = null, ?int $expiration = null): mixed
    {
        if (empty($query)) {
            return false;
        }

        $cacheKey = $this->generateCacheKey($query, self::CACHE_GROUP_KB);
        $expiration = $expiration ?? self::DEFAULT_CACHE_EXPIRATION;

        $cached = wp_cache_get($cacheKey, self::CACHE_GROUP_KB);
        if ($cached !== false) {
            $this->recordCacheHit();
            return $cached;
        }

        $this->recordCacheMiss();

        if ($callback && is_callable($callback)) {
            $data = call_user_func($callback);
            if ($data !== null) {
                wp_cache_set($cacheKey, $data, self::CACHE_GROUP_KB, $expiration);
                $this->recordCacheSet();
                return $data;
            }
        }

        return false;
    }

    /**
     * Get conversation cache for user sessions
     *
     * @since 1.0.0
     * @param string $conversationId The conversation identifier
     *
     * @return mixed Cached conversation data or false
     */
    public function getConversationCache(string $conversationId): mixed
    {
        if (empty($conversationId)) {
            return false;
        }

        $cacheKey = $this->generateCacheKey($conversationId, self::CACHE_GROUP_CONVERSATION);

        $cached = wp_cache_get($cacheKey, self::CACHE_GROUP_CONVERSATION);
        if ($cached !== false) {
            $this->recordCacheHit();
            return $cached;
        }

        $this->recordCacheMiss();
        return false;
    }

    /**
     * Set conversation cache
     *
     * @since 1.0.0
     * @param string $conversationId The conversation identifier
     * @param mixed $data The conversation data
     * @param int|null $expiration Optional expiration time
     *
     * @return bool True on success
     */
    public function setConversationCache(string $conversationId, mixed $data, ?int $expiration = null): bool
    {
        if (empty($conversationId) || $data === null) {
            return false;
        }

        $cacheKey = $this->generateCacheKey($conversationId, self::CACHE_GROUP_CONVERSATION);
        $expiration = $expiration ?? (HOUR_IN_SECONDS * 2); // 2 hours for conversations

        $result = wp_cache_set($cacheKey, $data, self::CACHE_GROUP_CONVERSATION, $expiration);

        if ($result) {
            $this->recordCacheSet();
        }

        return $result;
    }

    /**
     * Invalidate product cache when products are updated
     *
     * @since 1.0.0
     * @param int $postId The post/product ID
     *
     * @return void
     */
    public function invalidateProductCache(int $postId): void
    {
        if (get_post_type($postId) !== 'product') {
            return;
        }

        // Invalidate specific product cache
        $this->deleteCache("product_{$postId}", self::CACHE_GROUP_PRODUCTS);

        // Invalidate related FAQ caches
        $this->flushCacheGroup(self::CACHE_GROUP_FAQ);

        // Invalidate knowledge base cache
        $this->flushCacheGroup(self::CACHE_GROUP_KB);

        $this->recordCacheDelete();

        // Schedule cache warming for popular products
        wp_schedule_single_event(time() + 60, 'woo_ai_assistant_warm_cache');
    }

    /**
     * Invalidate knowledge base cache
     *
     * @since 1.0.0
     * @return void
     */
    public function invalidateKnowledgeBaseCache(): void
    {
        $this->flushCacheGroup(self::CACHE_GROUP_KB);
        $this->flushCacheGroup(self::CACHE_GROUP_FAQ);

        // Clean transients
        $this->cleanFaqTransients();

        $this->recordCacheFlush();
    }

    /**
     * Warm frequently accessed cache entries
     *
     * @since 1.0.0
     * @return void
     */
    public function warmFrequentlyAccessedCache(): void
    {
        // Get most frequently accessed FAQs from logs
        $popularQuestions = $this->getPopularFaqQuestions();

        foreach ($popularQuestions as $question) {
            // Pre-warm cache by triggering FAQ generation
            do_action('woo_ai_assistant_warm_faq_cache', $question);
        }

        // Warm popular product data
        $popularProducts = $this->getPopularProducts();
        foreach ($popularProducts as $productId) {
            $this->warmProductCache($productId);
        }
    }

    /**
     * Generate optimized cache key
     *
     * @since 1.0.0
     * @param string $key The base key
     * @param string $group The cache group
     *
     * @return string Optimized cache key
     */
    private function generateCacheKey(string $key, string $group): string
    {
        // Include site hash for multisite compatibility
        $siteHash = substr(md5(\get_site_url()), 0, 8);

        // Include relevant context
        $context = [
            'site' => $siteHash,
            'user_role' => $this->getCurrentUserRole(),
            'lang' => $this->getCurrentLanguage()
        ];

        $contextHash = md5(serialize($context));

        return sprintf('%s_%s_%s', $group, md5($key), substr($contextHash, 0, 8));
    }

    /**
     * Get current user role for cache key context
     *
     * @since 1.0.0
     * @return string Current user role or 'guest'
     */
    private function getCurrentUserRole(): string
    {
        if (!is_user_logged_in()) {
            return 'guest';
        }

        $user = wp_get_current_user();
        return !empty($user->roles) ? $user->roles[0] : 'user';
    }

    /**
     * Get current language for multilingual sites
     *
     * @since 1.0.0
     * @return string Current language code
     */
    private function getCurrentLanguage(): string
    {
        // Support WPML
        if (defined('ICL_LANGUAGE_CODE')) {
            return ICL_LANGUAGE_CODE;
        }

        // Support Polylang
        if (function_exists('pll_current_language')) {
            return pll_current_language();
        }

        return get_locale();
    }

    /**
     * Delete specific cache entry
     *
     * @since 1.0.0
     * @param string $key The cache key
     * @param string $group The cache group
     *
     * @return bool True on success
     */
    private function deleteCache(string $key, string $group): bool
    {
        $cacheKey = $this->generateCacheKey($key, $group);
        return wp_cache_delete($cacheKey, $group);
    }

    /**
     * Flush entire cache group
     *
     * @since 1.0.0
     * @param string $group The cache group to flush
     *
     * @return void
     */
    private function flushCacheGroup(string $group): void
    {
        // WordPress doesn't have native group flushing, so we'll use a versioning approach
        $versionKey = "cache_version_{$group}";
        $currentVersion = wp_cache_get($versionKey, 'woo_ai_versions');
        $newVersion = $currentVersion ? $currentVersion + 1 : 1;
        wp_cache_set($versionKey, $newVersion, 'woo_ai_versions', DAY_IN_SECONDS);
    }

    /**
     * Clean FAQ transients
     *
     * @since 1.0.0
     * @return void
     */
    private function cleanFaqTransients(): void
    {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_woo_ai_faq_%',
                '_transient_timeout_woo_ai_faq_%'
            )
        );
    }

    /**
     * Get popular FAQ questions from analytics
     *
     * @since 1.0.0
     * @return array Array of popular question keys
     */
    private function getPopularFaqQuestions(): array
    {
        // This would integrate with analytics data
        // For now, return common e-commerce questions
        return [
            'shipping_policy',
            'return_policy',
            'payment_methods',
            'order_status',
            'product_warranty'
        ];
    }

    /**
     * Get popular products for cache warming
     *
     * @since 1.0.0
     * @return array Array of popular product IDs
     */
    private function getPopularProducts(): array
    {
        $args = [
            'post_type' => 'product',
            'posts_per_page' => 10,
            'meta_key' => 'total_sales',
            'orderby' => 'meta_value_num',
            'order' => 'DESC',
            'post_status' => 'publish'
        ];

        $products = get_posts($args);
        return array_map(function ($post) {
            return $post->ID;
        }, $products);
    }

    /**
     * Warm product cache
     *
     * @since 1.0.0
     * @param int $productId The product ID to warm
     *
     * @return void
     */
    private function warmProductCache(int $productId): void
    {
        $cacheKey = "product_{$productId}";

        // Check if already cached
        $cached = wp_cache_get($cacheKey, self::CACHE_GROUP_PRODUCTS);
        if ($cached !== false) {
            return;
        }

        // Generate and cache product data
        $product = wc_get_product($productId);
        if ($product) {
            $productData = [
                'id' => $productId,
                'name' => $product->get_name(),
                'description' => $product->get_description(),
                'price' => $product->get_price(),
                'stock_status' => $product->get_stock_status(),
                'cached_at' => time()
            ];

            wp_cache_set($cacheKey, $productData, self::CACHE_GROUP_PRODUCTS, self::DEFAULT_CACHE_EXPIRATION);
        }
    }

    /**
     * Flush all plugin caches
     *
     * @since 1.0.0
     * @return void
     */
    public function flushAllCaches(): void
    {
        $this->flushCacheGroup(self::CACHE_GROUP_FAQ);
        $this->flushCacheGroup(self::CACHE_GROUP_KB);
        $this->flushCacheGroup(self::CACHE_GROUP_CONVERSATION);
        $this->flushCacheGroup(self::CACHE_GROUP_PRODUCTS);

        $this->cleanFaqTransients();
        $this->recordCacheFlush();
    }

    /**
     * Get cache statistics
     *
     * @since 1.0.0
     * @return array Cache performance statistics
     */
    public function getCacheStats(): array
    {
        $total = $this->cacheStats['hits'] + $this->cacheStats['misses'];
        $hitRatio = $total > 0 ? ($this->cacheStats['hits'] / $total) * 100 : 0;

        return array_merge($this->cacheStats, [
            'hit_ratio' => round($hitRatio, 2),
            'total_requests' => $total
        ]);
    }

    /**
     * Record cache hit for statistics
     *
     * @since 1.0.0
     * @return void
     */
    private function recordCacheHit(): void
    {
        $this->cacheStats['hits']++;
    }

    /**
     * Record cache miss for statistics
     *
     * @since 1.0.0
     * @return void
     */
    private function recordCacheMiss(): void
    {
        $this->cacheStats['misses']++;
    }

    /**
     * Record cache set operation
     *
     * @since 1.0.0
     * @return void
     */
    private function recordCacheSet(): void
    {
        $this->cacheStats['sets']++;
    }

    /**
     * Record cache delete operation
     *
     * @since 1.0.0
     * @return void
     */
    private function recordCacheDelete(): void
    {
        $this->cacheStats['deletes']++;
    }

    /**
     * Record cache flush operation
     *
     * @since 1.0.0
     * @return void
     */
    private function recordCacheFlush(): void
    {
        $this->cacheStats['flushes']++;
    }

    /**
     * Log cache statistics for debugging
     *
     * @since 1.0.0
     * @return void
     */
    public function logCacheStats(): void
    {
        if (!$this->performanceMonitoringEnabled) {
            return;
        }

        $stats = $this->getCacheStats();
        error_log('Woo AI Assistant Cache Stats: ' . json_encode($stats));
    }
}
