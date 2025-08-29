<?php

/**
 * CDN Integration Class
 *
 * Handles Content Delivery Network integration for the Woo AI Assistant plugin.
 * Provides asset optimization, CDN URL rewriting, and intelligent resource
 * delivery for improved performance.
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
 * Class CDNIntegration
 *
 * Provides CDN integration capabilities including asset URL rewriting,
 * cache-busting strategies, and intelligent resource delivery optimization
 * for the widget and static assets.
 *
 * @since 1.0.0
 */
class CDNIntegration
{
    use Singleton;

    /**
     * CDN configuration
     *
     * @var array
     */
    private $cdnConfig = [
        'enabled' => false,
        'base_url' => '',
        'zones' => [
            'static' => '',
            'images' => '',
            'scripts' => '',
            'styles' => ''
        ],
        'cache_busting' => true,
        'minification' => true,
        'compression' => true
    ];

    /**
     * Supported file types for CDN delivery
     *
     * @var array
     */
    private $supportedFileTypes = [
        'images' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
        'scripts' => ['js'],
        'styles' => ['css'],
        'fonts' => ['woff', 'woff2', 'ttf', 'otf', 'eot'],
        'documents' => ['pdf', 'doc', 'docx']
    ];

    /**
     * Asset optimization settings
     *
     * @var array
     */
    private $optimizationSettings = [
        'widget_bundle_compression' => true,
        'lazy_loading' => true,
        'preload_critical_assets' => true,
        'async_non_critical' => true
    ];

    /**
     * Performance metrics
     *
     * @var array
     */
    private $performanceMetrics = [];

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    protected function __construct()
    {
        $this->loadConfiguration();
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
        if (!$this->cdnConfig['enabled']) {
            return;
        }

        // Asset URL rewriting
        add_filter('script_loader_src', [$this, 'rewriteAssetUrl'], 10, 2);
        add_filter('style_loader_src', [$this, 'rewriteAssetUrl'], 10, 2);
        add_filter('wp_get_attachment_url', [$this, 'rewriteAttachmentUrl'], 10, 2);

        // Widget-specific optimizations
        add_action('wp_enqueue_scripts', [$this, 'optimizeWidgetAssets'], 5);
        add_filter('woo_ai_assistant_widget_script_url', [$this, 'optimizeWidgetScriptUrl']);
        add_filter('woo_ai_assistant_widget_style_url', [$this, 'optimizeWidgetStyleUrl']);

        // Performance optimizations
        add_action('wp_head', [$this, 'addResourceHints'], 2);
        add_action('wp_head', [$this, 'preloadCriticalAssets'], 3);

        // Cache busting
        add_filter('woo_ai_assistant_asset_version', [$this, 'generateCacheBustingVersion']);

        // Bundle optimization
        add_action('init', [$this, 'initializeBundleOptimization']);
    }

    /**
     * Configure CDN settings
     *
     * Configures CDN integration with support for multiple zones and
     * optimization strategies. Validates configuration and sets up
     * appropriate fallbacks.
     *
     * @since 1.0.0
     * @param array $config CDN configuration array
     * @param array $config['enabled'] Whether CDN is enabled
     * @param string $config['base_url'] Base CDN URL
     * @param array $config['zones'] Zone-specific URLs for different asset types
     * @param bool $config['cache_busting'] Enable cache-busting strategy
     *
     * @return bool True if configuration is valid and applied
     *
     * @throws \InvalidArgumentException When configuration is invalid.
     *
     * @example
     * ```php
     * $cdn = CDNIntegration::getInstance();
     * $cdn->configureCDN([
     *     'enabled' => true,
     *     'base_url' => 'https://cdn.example.com',
     *     'zones' => [
     *         'static' => 'https://static.cdn.example.com',
     *         'images' => 'https://images.cdn.example.com'
     *     ],
     *     'cache_busting' => true
     * ]);
     * ```
     */
    public function configureCDN(array $config): bool
    {
        // Validate required configuration
        if (empty($config['base_url']) && empty($config['zones'])) {
            throw new \InvalidArgumentException('CDN configuration must include base_url or zone URLs');
        }

        // Validate URLs
        foreach ($config['zones'] ?? [] as $zone => $url) {
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                throw new \InvalidArgumentException("Invalid URL for zone '{$zone}': {$url}");
            }
        }

        if (!empty($config['base_url']) && !filter_var($config['base_url'], FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException("Invalid base URL: {$config['base_url']}");
        }

        // Merge with existing configuration
        $this->cdnConfig = array_merge($this->cdnConfig, $config);

        // Enable CDN if configuration is valid
        $this->cdnConfig['enabled'] = true;

        // Save configuration
        $this->saveConfiguration();

        // Reinitialize hooks if needed
        if (!did_action('init')) {
            $this->initializeHooks();
        }

        return true;
    }

    /**
     * Optimize widget assets for CDN delivery and performance
     *
     * @since 1.0.0
     * @return void
     */
    public function optimizeWidgetAssets(): void
    {
        // Only optimize on pages where the widget is loaded
        if (!$this->shouldLoadWidgetAssets()) {
            return;
        }

        // Optimize widget script loading
        $this->optimizeWidgetScript();

        // Optimize widget styles
        $this->optimizeWidgetStyles();

        // Add lazy loading for non-critical assets
        if ($this->optimizationSettings['lazy_loading']) {
            $this->setupLazyLoading();
        }
    }

    /**
     * Optimize widget script URL and loading strategy
     *
     * @since 1.0.0
     * @param string $url Original script URL
     *
     * @return string Optimized script URL
     */
    public function optimizeWidgetScriptUrl(string $url): string
    {
        if (!$this->cdnConfig['enabled']) {
            return $url;
        }

        // Convert to CDN URL
        $cdnUrl = $this->convertToCdnUrl($url, 'scripts');

        // Add cache-busting parameter
        if ($this->cdnConfig['cache_busting']) {
            $cdnUrl = $this->addCacheBusting($cdnUrl);
        }

        // Record performance metric
        $this->recordAssetOptimization('script', $url, $cdnUrl);

        return $cdnUrl;
    }

    /**
     * Optimize widget style URL and loading strategy
     *
     * @since 1.0.0
     * @param string $url Original style URL
     *
     * @return string Optimized style URL
     */
    public function optimizeWidgetStyleUrl(string $url): string
    {
        if (!$this->cdnConfig['enabled']) {
            return $url;
        }

        // Convert to CDN URL
        $cdnUrl = $this->convertToCdnUrl($url, 'styles');

        // Add cache-busting parameter
        if ($this->cdnConfig['cache_busting']) {
            $cdnUrl = $this->addCacheBusting($cdnUrl);
        }

        // Record performance metric
        $this->recordAssetOptimization('style', $url, $cdnUrl);

        return $cdnUrl;
    }

    /**
     * Rewrite asset URLs to use CDN
     *
     * @since 1.0.0
     * @param string $src Original asset URL
     * @param string $handle Asset handle (optional)
     *
     * @return string CDN-optimized URL
     */
    public function rewriteAssetUrl(string $src, string $handle = ''): string
    {
        if (!$this->cdnConfig['enabled'] || empty($src)) {
            return $src;
        }

        // Skip external URLs
        if ($this->isExternalUrl($src)) {
            return $src;
        }

        // Skip admin assets (unless specifically configured)
        if (is_admin() && !apply_filters('woo_ai_assistant_cdn_admin_assets', false)) {
            return $src;
        }

        // Determine asset type
        $assetType = $this->detectAssetType($src);

        // Convert to CDN URL
        $cdnUrl = $this->convertToCdnUrl($src, $assetType);

        // Add cache-busting if enabled
        if ($this->cdnConfig['cache_busting']) {
            $cdnUrl = $this->addCacheBusting($cdnUrl);
        }

        return $cdnUrl;
    }

    /**
     * Rewrite attachment URLs to use CDN
     *
     * @since 1.0.0
     * @param string $url Original attachment URL
     * @param int $attachmentId Attachment ID
     *
     * @return string CDN-optimized URL
     */
    public function rewriteAttachmentUrl(string $url, int $attachmentId): string
    {
        if (!$this->cdnConfig['enabled'] || empty($url)) {
            return $url;
        }

        // Get attachment type
        $mimeType = get_post_mime_type($attachmentId);
        $assetType = $this->getAssetTypeFromMime($mimeType);

        // Convert to CDN URL
        $cdnUrl = $this->convertToCdnUrl($url, $assetType);

        return $cdnUrl;
    }

    /**
     * Add resource hints for better performance
     *
     * @since 1.0.0
     * @return void
     */
    public function addResourceHints(): void
    {
        if (!$this->cdnConfig['enabled']) {
            return;
        }

        // Add DNS prefetch for CDN domains
        $cdnDomains = $this->getCdnDomains();
        foreach ($cdnDomains as $domain) {
            echo "<link rel='dns-prefetch' href='{$domain}' />\n";
        }

        // Add preconnect for primary CDN
        if (!empty($this->cdnConfig['base_url'])) {
            echo "<link rel='preconnect' href='{$this->cdnConfig['base_url']}' crossorigin />\n";
        }

        // Add prefetch for widget assets if needed
        if ($this->shouldLoadWidgetAssets() && $this->optimizationSettings['preload_critical_assets']) {
            $this->addWidgetResourceHints();
        }
    }

    /**
     * Preload critical assets for better performance
     *
     * @since 1.0.0
     * @return void
     */
    public function preloadCriticalAssets(): void
    {
        if (!$this->cdnConfig['enabled'] || !$this->optimizationSettings['preload_critical_assets']) {
            return;
        }

        // Only preload on pages where widget is active
        if (!$this->shouldLoadWidgetAssets()) {
            return;
        }

        // Preload critical widget assets
        $criticalAssets = $this->getCriticalWidgetAssets();

        foreach ($criticalAssets as $asset) {
            $cdnUrl = $this->convertToCdnUrl($asset['url'], $asset['type']);
            $asType = $this->getPreloadAsAttribute($asset['type']);

            echo "<link rel='preload' href='{$cdnUrl}' as='{$asType}' />\n";
        }
    }

    /**
     * Initialize bundle optimization for widget assets
     *
     * @since 1.0.0
     * @return void
     */
    public function initializeBundleOptimization(): void
    {
        if (!$this->optimizationSettings['widget_bundle_compression']) {
            return;
        }

        // Enable gzip compression for widget assets
        add_filter('woo_ai_assistant_enable_gzip', '__return_true');

        // Implement code splitting hooks
        add_action('wp_enqueue_scripts', [$this, 'implementCodeSplitting'], 20);

        // Bundle optimization for production
        if (!defined('WOO_AI_ASSISTANT_DEBUG') || !WOO_AI_ASSISTANT_DEBUG) {
            add_filter('woo_ai_assistant_minify_assets', '__return_true');
        }
    }

    /**
     * Implement code splitting for widget bundles
     *
     * @since 1.0.0
     * @return void
     */
    public function implementCodeSplitting(): void
    {
        if (!$this->shouldLoadWidgetAssets()) {
            return;
        }

        // Load core widget chunk immediately
        $this->enqueueWidgetChunk('core', [
            'strategy' => 'immediate',
            'priority' => 'high'
        ]);

        // Load feature chunks on demand
        $this->enqueueWidgetChunk('chat', [
            'strategy' => 'lazy',
            'trigger' => 'widget_open'
        ]);

        $this->enqueueWidgetChunk('products', [
            'strategy' => 'lazy',
            'trigger' => 'product_query'
        ]);
    }

    /**
     * Setup lazy loading for non-critical assets
     *
     * @since 1.0.0
     * @return void
     */
    private function setupLazyLoading(): void
    {
        // Add intersection observer for lazy loading
        $lazyLoadScript = "
        <script>
        (function() {
            if ('IntersectionObserver' in window) {
                const lazyAssets = document.querySelectorAll('[data-woo-ai-lazy]');
                const assetObserver = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const element = entry.target;
                            if (element.dataset.src) {
                                element.src = element.dataset.src;
                                element.removeAttribute('data-src');
                            }
                            if (element.dataset.href) {
                                element.href = element.dataset.href;
                                element.removeAttribute('data-href');
                            }
                            assetObserver.unobserve(element);
                        }
                    });
                });
                lazyAssets.forEach(asset => assetObserver.observe(asset));
            }
        })();
        </script>";

        add_action('wp_footer', function () use ($lazyLoadScript) {
            echo $lazyLoadScript;
        });
    }

    /**
     * Convert regular URL to CDN URL
     *
     * @since 1.0.0
     * @param string $url Original URL
     * @param string $assetType Asset type for zone selection
     *
     * @return string CDN URL
     */
    private function convertToCdnUrl(string $url, string $assetType): string
    {
        // Get appropriate CDN URL for asset type
        $cdnBaseUrl = $this->getCdnUrlForAssetType($assetType);

        if (empty($cdnBaseUrl)) {
            return $url; // Fallback to original URL
        }

        // Parse original URL
        $parsedUrl = parse_url($url);

        // Skip if not a local asset
        $siteHost = parse_url(home_url(), PHP_URL_HOST);
        if (isset($parsedUrl['host']) && $parsedUrl['host'] !== $siteHost) {
            return $url;
        }

        // Build CDN URL
        $cdnParsed = parse_url($cdnBaseUrl);
        $cdnUrl = $cdnParsed['scheme'] . '://' . $cdnParsed['host'];

        // Add CDN path if exists
        if (isset($cdnParsed['path'])) {
            $cdnUrl .= rtrim($cdnParsed['path'], '/');
        }

        // Add original path
        $cdnUrl .= $parsedUrl['path'];

        // Add query string if exists
        if (isset($parsedUrl['query'])) {
            $cdnUrl .= '?' . $parsedUrl['query'];
        }

        return $cdnUrl;
    }

    /**
     * Get CDN URL for specific asset type
     *
     * @since 1.0.0
     * @param string $assetType Asset type
     *
     * @return string CDN URL or empty string
     */
    private function getCdnUrlForAssetType(string $assetType): string
    {
        // Check zone-specific URL first
        if (!empty($this->cdnConfig['zones'][$assetType])) {
            return $this->cdnConfig['zones'][$assetType];
        }

        // Fallback to base URL
        return $this->cdnConfig['base_url'] ?? '';
    }

    /**
     * Detect asset type from URL
     *
     * @since 1.0.0
     * @param string $url Asset URL
     *
     * @return string Asset type
     */
    private function detectAssetType(string $url): string
    {
        $extension = strtolower(pathinfo($url, PATHINFO_EXTENSION));

        foreach ($this->supportedFileTypes as $type => $extensions) {
            if (in_array($extension, $extensions)) {
                return $type === 'images' ? 'images' :
                       ($type === 'scripts' ? 'scripts' :
                       ($type === 'styles' ? 'styles' : 'static'));
            }
        }

        return 'static';
    }

    /**
     * Get asset type from MIME type
     *
     * @since 1.0.0
     * @param string $mimeType MIME type
     *
     * @return string Asset type
     */
    private function getAssetTypeFromMime(string $mimeType): string
    {
        if (strpos($mimeType, 'image/') === 0) {
            return 'images';
        } elseif (
            strpos($mimeType, 'application/javascript') === 0 ||
                  strpos($mimeType, 'text/javascript') === 0
        ) {
            return 'scripts';
        } elseif (strpos($mimeType, 'text/css') === 0) {
            return 'styles';
        } elseif (
            strpos($mimeType, 'font/') === 0 ||
                  strpos($mimeType, 'application/font') === 0
        ) {
            return 'fonts';
        }

        return 'static';
    }

    /**
     * Add cache-busting parameter to URL
     *
     * @since 1.0.0
     * @param string $url Original URL
     *
     * @return string URL with cache-busting parameter
     */
    private function addCacheBusting(string $url): string
    {
        $version = $this->generateCacheBustingVersion();
        $separator = strpos($url, '?') !== false ? '&' : '?';

        return $url . $separator . 'v=' . $version;
    }

    /**
     * Generate cache-busting version
     *
     * @since 1.0.0
     * @param string $asset Optional asset identifier
     *
     * @return string Version string
     */
    public function generateCacheBustingVersion(string $asset = ''): string
    {
        // Use plugin version for consistency
        $pluginData = get_file_data(WOO_AI_ASSISTANT_PLUGIN_FILE ?? '', ['Version' => 'Version']);
        $baseVersion = $pluginData['Version'] ?? '1.0.0';

        // Add build timestamp for development
        if (defined('WOO_AI_ASSISTANT_DEBUG') && WOO_AI_ASSISTANT_DEBUG) {
            $buildTime = filemtime(WOO_AI_ASSISTANT_PLUGIN_FILE ?? '');
            return $baseVersion . '.' . $buildTime;
        }

        return $baseVersion;
    }

    /**
     * Check if URL is external
     *
     * @since 1.0.0
     * @param string $url URL to check
     *
     * @return bool True if external
     */
    private function isExternalUrl(string $url): bool
    {
        if (strpos($url, '//') === 0 || strpos($url, 'http') === 0) {
            $host = parse_url($url, PHP_URL_HOST);
            $siteHost = parse_url(home_url(), PHP_URL_HOST);
            return $host !== $siteHost;
        }

        return false;
    }

    /**
     * Check if widget assets should be loaded
     *
     * @since 1.0.0
     * @return bool True if widget should be loaded
     */
    private function shouldLoadWidgetAssets(): bool
    {
        // Skip in admin (unless specifically enabled)
        if (is_admin() && !apply_filters('woo_ai_assistant_load_admin_widget', false)) {
            return false;
        }

        // Skip for REST API requests
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return false;
        }

        // Check if widget is enabled for current page
        return apply_filters('woo_ai_assistant_load_widget', true);
    }

    /**
     * Optimize widget script loading
     *
     * @since 1.0.0
     * @return void
     */
    private function optimizeWidgetScript(): void
    {
        // Defer non-critical scripts
        add_filter('script_loader_tag', function ($tag, $handle) {
            if (
                strpos($handle, 'woo-ai-assistant') === 0 &&
                !in_array($handle, ['woo-ai-assistant-core'])
            ) {
                return str_replace('<script ', '<script defer ', $tag);
            }
            return $tag;
        }, 10, 2);
    }

    /**
     * Optimize widget styles
     *
     * @since 1.0.0
     * @return void
     */
    private function optimizeWidgetStyles(): void
    {
        // Load critical styles inline for performance
        add_action('wp_head', [$this, 'inlineCriticalStyles'], 1);

        // Defer non-critical styles
        add_filter('style_loader_tag', function ($html, $handle) {
            if (strpos($handle, 'woo-ai-assistant-theme') === 0) {
                return str_replace("rel='stylesheet'", "rel='preload' as='style' onload=\"this.onload=null;this.rel='stylesheet'\"", $html);
            }
            return $html;
        }, 10, 2);
    }

    /**
     * Inline critical styles for performance
     *
     * @since 1.0.0
     * @return void
     */
    public function inlineCriticalStyles(): void
    {
        $criticalCss = "
        .woo-ai-assistant-widget{position:fixed;bottom:20px;right:20px;z-index:999999}
        .woo-ai-assistant-trigger{width:60px;height:60px;border-radius:50%;cursor:pointer}
        .woo-ai-assistant-chat{display:none;width:350px;height:500px;border-radius:10px;box-shadow:0 5px 25px rgba(0,0,0,0.15)}
        ";

        echo "<style id='woo-ai-assistant-critical-css'>{$criticalCss}</style>\n";
    }

    /**
     * Get CDN domains for DNS prefetch
     *
     * @since 1.0.0
     * @return array Array of CDN domains
     */
    private function getCdnDomains(): array
    {
        $domains = [];

        if (!empty($this->cdnConfig['base_url'])) {
            $domains[] = parse_url($this->cdnConfig['base_url'], PHP_URL_SCHEME) . '://' .
                        parse_url($this->cdnConfig['base_url'], PHP_URL_HOST);
        }

        foreach ($this->cdnConfig['zones'] as $url) {
            if (!empty($url)) {
                $domain = parse_url($url, PHP_URL_SCHEME) . '://' .
                         parse_url($url, PHP_URL_HOST);
                if (!in_array($domain, $domains)) {
                    $domains[] = $domain;
                }
            }
        }

        return $domains;
    }

    /**
     * Add widget-specific resource hints
     *
     * @since 1.0.0
     * @return void
     */
    private function addWidgetResourceHints(): void
    {
        // This would be populated with actual widget asset URLs
        $widgetAssets = [
            'script' => '/assets/js/widget.min.js',
            'style' => '/assets/css/widget.min.css'
        ];

        foreach ($widgetAssets as $type => $path) {
            $cdnUrl = $this->convertToCdnUrl(plugins_url($path, WOO_AI_ASSISTANT_PLUGIN_FILE), $type);
            $as = $type === 'script' ? 'script' : 'style';
            echo "<link rel='prefetch' href='{$cdnUrl}' as='{$as}' />\n";
        }
    }

    /**
     * Get critical widget assets for preloading
     *
     * @since 1.0.0
     * @return array Array of critical assets
     */
    private function getCriticalWidgetAssets(): array
    {
        return [
            [
                'url' => plugins_url('assets/js/widget-core.min.js', WOO_AI_ASSISTANT_PLUGIN_FILE),
                'type' => 'scripts'
            ],
            [
                'url' => plugins_url('assets/css/widget-core.min.css', WOO_AI_ASSISTANT_PLUGIN_FILE),
                'type' => 'styles'
            ]
        ];
    }

    /**
     * Get preload 'as' attribute for asset type
     *
     * @since 1.0.0
     * @param string $assetType Asset type
     *
     * @return string Preload 'as' attribute value
     */
    private function getPreloadAsAttribute(string $assetType): string
    {
        $mapping = [
            'scripts' => 'script',
            'styles' => 'style',
            'images' => 'image',
            'fonts' => 'font'
        ];

        return $mapping[$assetType] ?? 'fetch';
    }

    /**
     * Enqueue widget chunk with optimization strategy
     *
     * @since 1.0.0
     * @param string $chunk Chunk name
     * @param array $options Loading options
     *
     * @return void
     */
    private function enqueueWidgetChunk(string $chunk, array $options): void
    {
        $chunkFile = "widget-{$chunk}.min.js";
        $chunkUrl = plugins_url("assets/js/{$chunkFile}", WOO_AI_ASSISTANT_PLUGIN_FILE);
        $handle = "woo-ai-assistant-{$chunk}";

        if ($options['strategy'] === 'immediate') {
            wp_enqueue_script($handle, $chunkUrl, [], $this->generateCacheBustingVersion(), true);
        } else {
            // Register for lazy loading
            wp_register_script($handle, $chunkUrl, [], $this->generateCacheBustingVersion(), true);

            // Add lazy loading attributes
            add_filter('script_loader_tag', function ($tag, $scriptHandle) use ($handle, $options) {
                if ($scriptHandle === $handle) {
                    $tag = str_replace('<script ', "<script data-woo-ai-lazy data-trigger='{$options['trigger']}' ", $tag);
                }
                return $tag;
            }, 10, 2);
        }
    }

    /**
     * Record asset optimization metrics
     *
     * @since 1.0.0
     * @param string $assetType Asset type
     * @param string $originalUrl Original URL
     * @param string $optimizedUrl Optimized URL
     *
     * @return void
     */
    private function recordAssetOptimization(string $assetType, string $originalUrl, string $optimizedUrl): void
    {
        if (!defined('WOO_AI_ASSISTANT_DEBUG') || !WOO_AI_ASSISTANT_DEBUG) {
            return;
        }

        $this->performanceMetrics['asset_optimizations'][] = [
            'type' => $assetType,
            'original_url' => $originalUrl,
            'optimized_url' => $optimizedUrl,
            'timestamp' => current_time('mysql')
        ];
    }

    /**
     * Load CDN configuration from options
     *
     * @since 1.0.0
     * @return void
     */
    private function loadConfiguration(): void
    {
        $config = get_option('woo_ai_assistant_cdn_config', []);
        if (!empty($config)) {
            $this->cdnConfig = array_merge($this->cdnConfig, $config);
        }
    }

    /**
     * Save CDN configuration to options
     *
     * @since 1.0.0
     * @return void
     */
    private function saveConfiguration(): void
    {
        update_option('woo_ai_assistant_cdn_config', $this->cdnConfig);
    }

    /**
     * Get current CDN configuration
     *
     * @since 1.0.0
     * @return array CDN configuration
     */
    public function getConfiguration(): array
    {
        return $this->cdnConfig;
    }

    /**
     * Get CDN performance metrics
     *
     * @since 1.0.0
     * @return array Performance metrics
     */
    public function getPerformanceMetrics(): array
    {
        return $this->performanceMetrics;
    }

    /**
     * Disable CDN integration
     *
     * @since 1.0.0
     * @return bool True on success
     */
    public function disableCDN(): bool
    {
        $this->cdnConfig['enabled'] = false;
        $this->saveConfiguration();

        return true;
    }

    /**
     * Test CDN connectivity and performance
     *
     * @since 1.0.0
     * @return array Test results
     */
    public function testCDNConnectivity(): array
    {
        if (!$this->cdnConfig['enabled']) {
            return ['status' => 'disabled'];
        }

        $results = [
            'base_url' => $this->testUrlConnectivity($this->cdnConfig['base_url']),
            'zones' => []
        ];

        foreach ($this->cdnConfig['zones'] as $zone => $url) {
            if (!empty($url)) {
                $results['zones'][$zone] = $this->testUrlConnectivity($url);
            }
        }

        return $results;
    }

    /**
     * Test URL connectivity
     *
     * @since 1.0.0
     * @param string $url URL to test
     *
     * @return array Test results
     */
    private function testUrlConnectivity(string $url): array
    {
        if (empty($url)) {
            return ['status' => 'empty_url'];
        }

        $startTime = microtime(true);
        $response = wp_remote_head($url, ['timeout' => 5]);
        $responseTime = microtime(true) - $startTime;

        if (is_wp_error($response)) {
            return [
                'status' => 'error',
                'error' => $response->get_error_message(),
                'response_time' => $responseTime
            ];
        }

        return [
            'status' => 'success',
            'response_code' => wp_remote_retrieve_response_code($response),
            'response_time' => $responseTime
        ];
    }
}
