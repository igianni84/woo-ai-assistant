<?php

/**
 * Cache Class
 *
 * Centralized caching layer for performance optimization.
 * Provides wrapper methods for WordPress caching functions
 * with plugin-specific cache groups and TTL management.
 *
 * @package WooAiAssistant
 * @subpackage Common
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Common;

use WooAiAssistant\Common\Traits\Singleton;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Cache
 *
 * @since 1.0.0
 */
class Cache
{
    use Singleton;

    /**
     * Default cache group for the plugin
     */
    const DEFAULT_CACHE_GROUP = 'woo_ai_assistant';

    /**
     * Cache groups for different data types
     */
    const GROUP_KNOWLEDGE_BASE = 'woo_ai_kb';
    const GROUP_CONVERSATIONS = 'woo_ai_conv';
    const GROUP_EMBEDDINGS = 'woo_ai_embed';
    const GROUP_API_RESPONSES = 'woo_ai_api';
    const GROUP_SETTINGS = 'woo_ai_settings';

    /**
     * Default TTL values (in seconds)
     */
    const TTL_SHORT = 300;      // 5 minutes
    const TTL_MEDIUM = 1800;    // 30 minutes
    const TTL_LONG = 3600;      // 1 hour
    const TTL_VERY_LONG = 86400; // 24 hours

    /**
     * Whether caching is enabled
     *
     * @var bool
     */
    private bool $cachingEnabled = true;

    /**
     * Default TTL for cache entries
     *
     * @var int
     */
    private int $defaultTtl = self::TTL_MEDIUM;

    /**
     * Initialize cache
     *
     * @return void
     */
    protected function init(): void
    {
        $this->cachingEnabled = $this->isCachingEnabled();
        $this->defaultTtl = $this->getDefaultTtl();
    }

    /**
     * Check if caching should be enabled
     *
     * @return bool
     */
    private function isCachingEnabled(): bool
    {
        // Disable caching in development mode if configured
        if (defined('WOO_AI_ASSISTANT_DISABLE_CACHE') && WOO_AI_ASSISTANT_DISABLE_CACHE) {
            return false;
        }

        // Check if object cache is available
        return wp_using_ext_object_cache() || function_exists('wp_cache_set');
    }

    /**
     * Get default TTL from configuration
     *
     * @return int
     */
    private function getDefaultTtl(): int
    {
        if (defined('WOO_AI_ASSISTANT_CACHE_TTL')) {
            return (int) WOO_AI_ASSISTANT_CACHE_TTL;
        }

        // Use shorter TTL in development mode
        if (Utils::isDevelopmentMode()) {
            return self::TTL_SHORT;
        }

        return self::TTL_MEDIUM;
    }

    /**
     * Get cached data
     *
     * @param string $key Cache key
     * @param string $group Cache group
     * @return mixed Cached data or false if not found
     */
    public static function get(string $key, string $group = self::DEFAULT_CACHE_GROUP): mixed
    {
        $instance = self::getInstance();

        if (!$instance->cachingEnabled) {
            return false;
        }

        $prefixedKey = $instance->getPrefixedKey($key);
        $result = wp_cache_get($prefixedKey, $group);

        Logger::debug("Cache GET: {$prefixedKey} from group {$group}", [
            'found' => $result !== false
        ]);

        return $result;
    }

    /**
     * Set cached data
     *
     * @param string $key Cache key
     * @param mixed $data Data to cache
     * @param string $group Cache group
     * @param int $ttl Time to live in seconds
     * @return bool Success status
     */
    public static function set(string $key, mixed $data, string $group = self::DEFAULT_CACHE_GROUP, int $ttl = null): bool
    {
        $instance = self::getInstance();

        if (!$instance->cachingEnabled) {
            return false;
        }

        if ($ttl === null) {
            $ttl = $instance->defaultTtl;
        }

        $prefixedKey = $instance->getPrefixedKey($key);
        $result = wp_cache_set($prefixedKey, $data, $group, $ttl);

        Logger::debug("Cache SET: {$prefixedKey} in group {$group} for {$ttl}s", [
            'success' => $result,
            'data_type' => gettype($data)
        ]);

        return $result;
    }

    /**
     * Delete cached data
     *
     * @param string $key Cache key
     * @param string $group Cache group
     * @return bool Success status
     */
    public static function delete(string $key, string $group = self::DEFAULT_CACHE_GROUP): bool
    {
        $instance = self::getInstance();

        if (!$instance->cachingEnabled) {
            return false;
        }

        $prefixedKey = $instance->getPrefixedKey($key);
        $result = wp_cache_delete($prefixedKey, $group);

        Logger::debug("Cache DELETE: {$prefixedKey} from group {$group}", [
            'success' => $result
        ]);

        return $result;
    }

    /**
     * Get data with callback fallback
     *
     * If data is not in cache, execute callback and cache the result.
     *
     * @param string $key Cache key
     * @param callable $callback Callback to generate data if not cached
     * @param string $group Cache group
     * @param int $ttl Time to live in seconds
     * @return mixed Cached or generated data
     */
    public static function remember(string $key, callable $callback, string $group = self::DEFAULT_CACHE_GROUP, int $ttl = null): mixed
    {
        $data = self::get($key, $group);

        if ($data !== false) {
            return $data;
        }

        $data = $callback();

        if ($data !== null && $data !== false) {
            self::set($key, $data, $group, $ttl);
        }

        return $data;
    }

    /**
     * Flush cache group
     *
     * @param string $group Cache group to flush
     * @return bool Success status
     */
    public static function flushGroup(string $group): bool
    {
        $instance = self::getInstance();

        if (!$instance->cachingEnabled) {
            return false;
        }

        Logger::info("Flushing cache group: {$group}");

        // WordPress doesn't have a built-in group flush, so we increment the group key
        $groupKey = "{$group}_invalidation_key";
        $currentKey = wp_cache_get($groupKey, 'woo_ai_cache_keys') ?: 0;
        $newKey = $currentKey + 1;

        return wp_cache_set($groupKey, $newKey, 'woo_ai_cache_keys', self::TTL_VERY_LONG);
    }

    /**
     * Flush all plugin cache
     *
     * @return bool Success status
     */
    public static function flushAll(): bool
    {
        $groups = [
            self::DEFAULT_CACHE_GROUP,
            self::GROUP_KNOWLEDGE_BASE,
            self::GROUP_CONVERSATIONS,
            self::GROUP_EMBEDDINGS,
            self::GROUP_API_RESPONSES,
            self::GROUP_SETTINGS,
        ];

        $success = true;
        foreach ($groups as $group) {
            $success = self::flushGroup($group) && $success;
        }

        Logger::info("Flushed all plugin cache groups", [
            'success' => $success
        ]);

        return $success;
    }

    /**
     * Get cache statistics
     *
     * @return array Cache statistics
     */
    public static function getStats(): array
    {
        $instance = self::getInstance();

        return [
            'enabled' => $instance->cachingEnabled,
            'external_cache' => wp_using_ext_object_cache(),
            'default_ttl' => $instance->defaultTtl,
            'groups' => [
                self::DEFAULT_CACHE_GROUP,
                self::GROUP_KNOWLEDGE_BASE,
                self::GROUP_CONVERSATIONS,
                self::GROUP_EMBEDDINGS,
                self::GROUP_API_RESPONSES,
                self::GROUP_SETTINGS,
            ]
        ];
    }

    /**
     * Enable caching
     *
     * @return void
     */
    public function enable(): void
    {
        $this->cachingEnabled = true;
        Logger::info("Cache enabled");
    }

    /**
     * Disable caching
     *
     * @return void
     */
    public function disable(): void
    {
        $this->cachingEnabled = false;
        Logger::info("Cache disabled");
    }

    /**
     * Check if caching is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->cachingEnabled;
    }

    /**
     * Set default TTL
     *
     * @param int $ttl Time to live in seconds
     * @return void
     */
    public function setDefaultTtl(int $ttl): void
    {
        $this->defaultTtl = max(0, $ttl);
    }

    /**
     * Get current default TTL
     *
     * @return int
     */
    public function getCurrentDefaultTtl(): int
    {
        return $this->defaultTtl;
    }

    /**
     * Get prefixed cache key with invalidation support
     *
     * @param string $key Original cache key
     * @param string $group Cache group
     * @return string Prefixed cache key
     */
    private function getPrefixedKey(string $key, string $group = self::DEFAULT_CACHE_GROUP): string
    {
        $prefix = 'woo_ai_' . Utils::getVersion() . '_';

        // Add group invalidation key for group-level cache busting
        $groupKey = "{$group}_invalidation_key";
        $groupInvalidation = wp_cache_get($groupKey, 'woo_ai_cache_keys') ?: 0;

        return $prefix . $groupInvalidation . '_' . $key;
    }

    /**
     * Generate cache key from array of parameters
     *
     * @param array $params Parameters to include in key
     * @return string Generated cache key
     */
    public static function generateKey(array $params): string
    {
        // Sort array to ensure consistent key generation
        ksort($params);

        // Create hash from serialized parameters
        return md5(serialize($params));
    }
}
