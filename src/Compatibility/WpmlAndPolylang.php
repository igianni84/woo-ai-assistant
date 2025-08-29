<?php

/**
 * Multilingual Support Class
 *
 * Handles automatic detection and integration with popular WordPress multilingual plugins
 * including WPML, Polylang, and TranslatePress. Provides zero-config multilingual support
 * for the Knowledge Base system and chat functionality.
 *
 * @package WooAiAssistant
 * @subpackage Compatibility
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Compatibility;

use WooAiAssistant\Common\Utils;
use WooAiAssistant\Common\Traits\Singleton;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WpmlAndPolylang
 *
 * Comprehensive multilingual support system that automatically detects installed
 * multilingual plugins and provides seamless language handling for the Knowledge Base,
 * chat functionality, and all plugin features.
 *
 * @since 1.0.0
 */
class WpmlAndPolylang
{
    use Singleton;

    /**
     * Detected multilingual plugin
     *
     * @since 1.0.0
     * @var string|null
     */
    private ?string $detectedPlugin = null;

    /**
     * Current language code
     *
     * @since 1.0.0
     * @var string
     */
    private string $currentLanguage = 'en';

    /**
     * Default language code
     *
     * @since 1.0.0
     * @var string
     */
    private string $defaultLanguage = 'en';

    /**
     * Available languages
     *
     * @since 1.0.0
     * @var array
     */
    private array $availableLanguages = [];

    /**
     * Language cache
     *
     * @since 1.0.0
     * @var array
     */
    private array $languageCache = [];

    /**
     * Supported multilingual plugins
     *
     * @since 1.0.0
     * @var array
     */
    private array $supportedPlugins = [
        'wpml' => 'WPML Multilingual CMS',
        'polylang' => 'Polylang',
        'translatepress' => 'TranslatePress'
    ];

    /**
     * Constructor
     *
     * Initializes the multilingual support system by detecting active plugins
     * and setting up language configuration.
     *
     * @since 1.0.0
     */
    private function __construct()
    {
        $this->detectMultilingualPlugin();
        $this->initializeLanguageSettings();
        $this->setupHooks();
    }

    /**
     * Detect active multilingual plugin
     *
     * Automatically detects which multilingual plugin is active and sets up
     * appropriate integration hooks and methods.
     *
     * @since 1.0.0
     * @return void
     */
    private function detectMultilingualPlugin(): void
    {
        try {
            // Check for WPML
            if ($this->isWpmlActive()) {
                $this->detectedPlugin = 'wpml';
                Utils::logDebug('WPML detected and activated for multilingual support');
                return;
            }

            // Check for Polylang
            if ($this->isPolylangActive()) {
                $this->detectedPlugin = 'polylang';
                Utils::logDebug('Polylang detected and activated for multilingual support');
                return;
            }

            // Check for TranslatePress
            if ($this->isTranslatePressActive()) {
                $this->detectedPlugin = 'translatepress';
                Utils::logDebug('TranslatePress detected and activated for multilingual support');
                return;
            }

            // No multilingual plugin detected
            $this->detectedPlugin = null;
            Utils::logDebug('No multilingual plugin detected - using default language support');
        } catch (\Exception $e) {
            Utils::logError('Error detecting multilingual plugin: ' . $e->getMessage());
            $this->detectedPlugin = null;
        }
    }

    /**
     * Check if WPML is active
     *
     * @since 1.0.0
     * @return bool True if WPML is active and functional
     */
    private function isWpmlActive(): bool
    {
        return defined('ICL_SITEPRESS_VERSION') &&
               class_exists('SitePress') &&
               function_exists('icl_get_languages');
    }

    /**
     * Check if Polylang is active
     *
     * @since 1.0.0
     * @return bool True if Polylang is active and functional
     */
    private function isPolylangActive(): bool
    {
        return function_exists('pll_current_language') &&
               function_exists('pll_get_post_language') &&
               class_exists('Polylang');
    }

    /**
     * Check if TranslatePress is active
     *
     * @since 1.0.0
     * @return bool True if TranslatePress is active and functional
     */
    private function isTranslatePressActive(): bool
    {
        return class_exists('TRP_Translate_Press') &&
               function_exists('trp_get_current_language');
    }

    /**
     * Initialize language settings
     *
     * Sets up current language, default language, and available languages
     * based on the detected multilingual plugin.
     *
     * @since 1.0.0
     * @return void
     */
    private function initializeLanguageSettings(): void
    {
        try {
            switch ($this->detectedPlugin) {
                case 'wpml':
                    $this->initializeWpmlSettings();
                    break;

                case 'polylang':
                    $this->initializePolylangSettings();
                    break;

                case 'translatepress':
                    $this->initializeTranslatePressSettings();
                    break;

                default:
                    $this->initializeDefaultSettings();
                    break;
            }

            // Cache the language settings
            $this->cacheLanguageSettings();

            Utils::logDebug('Language settings initialized', [
                'plugin' => $this->detectedPlugin,
                'current_language' => $this->currentLanguage,
                'default_language' => $this->defaultLanguage,
                'available_languages' => count($this->availableLanguages)
            ]);
        } catch (\Exception $e) {
            Utils::logError('Error initializing language settings: ' . $e->getMessage());
            $this->initializeDefaultSettings();
        }
    }

    /**
     * Initialize WPML language settings
     *
     * @since 1.0.0
     * @return void
     */
    private function initializeWpmlSettings(): void
    {
        if (!$this->isWpmlActive()) {
            return;
        }

        global $sitepress;

        // Get current language
        $this->currentLanguage = $sitepress->get_current_language() ?: 'en';

        // Get default language
        $this->defaultLanguage = $sitepress->get_default_language() ?: 'en';

        // Get all active languages
        $wpmlLanguages = $sitepress->get_active_languages();
        $this->availableLanguages = [];

        foreach ($wpmlLanguages as $langCode => $language) {
            $this->availableLanguages[$langCode] = [
                'code' => $langCode,
                'name' => $language['display_name'],
                'native_name' => $language['native_name'],
                'flag' => $language['country_flag_url'] ?? '',
                'is_default' => $langCode === $this->defaultLanguage
            ];
        }
    }

    /**
     * Initialize Polylang language settings
     *
     * @since 1.0.0
     * @return void
     */
    private function initializePolylangSettings(): void
    {
        if (!$this->isPolylangActive()) {
            return;
        }

        // Get current language
        $this->currentLanguage = pll_current_language() ?: 'en';

        // Get default language
        $this->defaultLanguage = pll_default_language() ?: 'en';

        // Get all languages
        $polylangLanguages = pll_languages_list(['fields' => '']);
        $this->availableLanguages = [];

        foreach ($polylangLanguages as $language) {
            $langCode = $language->slug;
            $this->availableLanguages[$langCode] = [
                'code' => $langCode,
                'name' => $language->name,
                'native_name' => $language->name,
                'flag' => $language->flag ?? '',
                'is_default' => $langCode === $this->defaultLanguage
            ];
        }
    }

    /**
     * Initialize TranslatePress language settings
     *
     * @since 1.0.0
     * @return void
     */
    private function initializeTranslatePressSettings(): void
    {
        if (!$this->isTranslatePressActive()) {
            return;
        }

        // Get current language
        $this->currentLanguage = trp_get_current_language() ?: 'en';

        // Get TranslatePress settings
        $trpSettings = get_option('trp_settings', []);
        $this->defaultLanguage = $trpSettings['default-language'] ?? 'en';

        // Get all published languages
        $publishedLanguages = $trpSettings['publish-languages'] ?? [$this->defaultLanguage];
        $this->availableLanguages = [];

        foreach ($publishedLanguages as $langCode) {
            $this->availableLanguages[$langCode] = [
                'code' => $langCode,
                'name' => $this->getLanguageNameByCode($langCode),
                'native_name' => $this->getLanguageNameByCode($langCode),
                'flag' => '',
                'is_default' => $langCode === $this->defaultLanguage
            ];
        }
    }

    /**
     * Initialize default language settings
     *
     * Used when no multilingual plugin is detected.
     *
     * @since 1.0.0
     * @return void
     */
    private function initializeDefaultSettings(): void
    {
        // Use WordPress locale as default
        $wpLocale = get_locale();
        $languageCode = substr($wpLocale, 0, 2);

        $this->currentLanguage = $languageCode;
        $this->defaultLanguage = $languageCode;
        $this->availableLanguages = [
            $languageCode => [
                'code' => $languageCode,
                'name' => $this->getLanguageNameByCode($languageCode),
                'native_name' => $this->getLanguageNameByCode($languageCode),
                'flag' => '',
                'is_default' => true
            ]
        ];
    }

    /**
     * Get language name by code
     *
     * @since 1.0.0
     * @param string $code Language code
     * @return string Language name
     */
    private function getLanguageNameByCode(string $code): string
    {
        $languages = [
            'en' => 'English',
            'es' => 'Spanish',
            'fr' => 'French',
            'de' => 'German',
            'it' => 'Italian',
            'pt' => 'Portuguese',
            'ru' => 'Russian',
            'ja' => 'Japanese',
            'ko' => 'Korean',
            'zh' => 'Chinese',
            'ar' => 'Arabic',
            'hi' => 'Hindi',
            'nl' => 'Dutch',
            'sv' => 'Swedish',
            'no' => 'Norwegian',
            'da' => 'Danish',
            'fi' => 'Finnish',
            'pl' => 'Polish',
            'cs' => 'Czech',
            'hu' => 'Hungarian'
        ];

        return $languages[$code] ?? ucfirst($code);
    }

    /**
     * Setup WordPress hooks
     *
     * @since 1.0.0
     * @return void
     */
    private function setupHooks(): void
    {
        // Language switching hooks
        add_action('wp_loaded', [$this, 'maybeUpdateCurrentLanguage'], 5);

        // REST API language context hooks
        add_filter('woo_ai_assistant_rest_request_context', [$this, 'addLanguageContext'], 10, 2);

        // Knowledge Base language filtering hooks
        add_filter('woo_ai_assistant_kb_query_args', [$this, 'filterKnowledgeBaseByLanguage'], 10, 2);
        add_filter('woo_ai_assistant_kb_content_language', [$this, 'getContentLanguage'], 10, 3);

        // Cache language-specific keys
        add_filter('woo_ai_assistant_cache_key', [$this, 'addLanguageToCache'], 10, 2);

        // Plugin-specific hooks
        if ($this->detectedPlugin === 'wpml') {
            add_action('wpml_language_has_switched', [$this, 'onLanguageSwitch'], 10, 3);
        } elseif ($this->detectedPlugin === 'polylang') {
            add_action('pll_language_defined', [$this, 'onPolylangLanguageDefined'], 10, 2);
        } elseif ($this->detectedPlugin === 'translatepress') {
            add_action('trp_language_switched', [$this, 'onTranslatePressLanguageSwitch'], 10, 2);
        }

        Utils::logDebug('Multilingual hooks registered for plugin: ' . ($this->detectedPlugin ?? 'none'));
    }

    /**
     * Maybe update current language
     *
     * Updates current language if it has changed during the request.
     *
     * @since 1.0.0
     * @return void
     */
    public function maybeUpdateCurrentLanguage(): void
    {
        $previousLanguage = $this->currentLanguage;
        $newLanguage = $this->getCurrentLanguage();

        if ($newLanguage !== $previousLanguage) {
            $this->currentLanguage = $newLanguage;

            /**
             * Language changed action
             *
             * Fired when the current language changes during a request.
             *
             * @since 1.0.0
             * @param string $newLanguage New language code
             * @param string $previousLanguage Previous language code
             */
            do_action('woo_ai_assistant_language_changed', $newLanguage, $previousLanguage);

            Utils::logDebug("Language changed from {$previousLanguage} to {$newLanguage}");
        }
    }

    /**
     * Get current language code
     *
     * @since 1.0.0
     * @return string Current language code
     */
    public function getCurrentLanguage(): string
    {
        // Check cache first
        $cacheKey = 'current_language_' . $this->detectedPlugin;
        if (isset($this->languageCache[$cacheKey])) {
            return $this->languageCache[$cacheKey];
        }

        $language = 'en'; // Default fallback

        try {
            switch ($this->detectedPlugin) {
                case 'wpml':
                    if ($this->isWpmlActive()) {
                        global $sitepress;
                        $language = $sitepress->get_current_language() ?: $this->defaultLanguage;
                    }
                    break;

                case 'polylang':
                    if ($this->isPolylangActive()) {
                        $language = pll_current_language() ?: $this->defaultLanguage;
                    }
                    break;

                case 'translatepress':
                    if ($this->isTranslatePressActive()) {
                        $language = trp_get_current_language() ?: $this->defaultLanguage;
                    }
                    break;

                default:
                    $language = $this->currentLanguage;
                    break;
            }
        } catch (\Exception $e) {
            Utils::logError('Error getting current language: ' . $e->getMessage());
            $language = $this->defaultLanguage;
        }

        // Cache the result
        $this->languageCache[$cacheKey] = $language;

        return $language;
    }

    /**
     * Get default language code
     *
     * @since 1.0.0
     * @return string Default language code
     */
    public function getDefaultLanguage(): string
    {
        return $this->defaultLanguage;
    }

    /**
     * Get all available languages
     *
     * @since 1.0.0
     * @return array Array of available languages
     */
    public function getAvailableLanguages(): array
    {
        return $this->availableLanguages;
    }

    /**
     * Check if multilingual support is active
     *
     * @since 1.0.0
     * @return bool True if a multilingual plugin is detected and active
     */
    public function isMultilingualActive(): bool
    {
        return $this->detectedPlugin !== null;
    }

    /**
     * Get detected multilingual plugin
     *
     * @since 1.0.0
     * @return string|null Plugin name or null if none detected
     */
    public function getDetectedPlugin(): ?string
    {
        return $this->detectedPlugin;
    }

    /**
     * Get content language
     *
     * Determines the language of a specific piece of content (post, product, etc.).
     *
     * @since 1.0.0
     * @param int|string $contentId Content ID
     * @param string $contentType Content type (post, product, etc.)
     * @return string Language code
     */
    public function getContentLanguage($contentId, string $contentType = 'post'): string
    {
        $cacheKey = "content_lang_{$contentType}_{$contentId}";
        if (isset($this->languageCache[$cacheKey])) {
            return $this->languageCache[$cacheKey];
        }

        $language = $this->defaultLanguage;

        try {
            switch ($this->detectedPlugin) {
                case 'wpml':
                    if ($this->isWpmlActive()) {
                        $language = apply_filters('wpml_element_language_code', null, [
                            'element_id' => $contentId,
                            'element_type' => $contentType === 'product' ? 'post_product' : 'post_' . $contentType
                        ]);
                        $language = $language ?: $this->defaultLanguage;
                    }
                    break;

                case 'polylang':
                    if ($this->isPolylangActive()) {
                        if ($contentType === 'post' || $contentType === 'product') {
                            $language = pll_get_post_language($contentId) ?: $this->defaultLanguage;
                        }
                    }
                    break;

                case 'translatepress':
                    // TranslatePress doesn't assign languages to individual content
                    // It translates the same content in different languages
                    $language = $this->getCurrentLanguage();
                    break;

                default:
                    $language = $this->defaultLanguage;
                    break;
            }
        } catch (\Exception $e) {
            Utils::logError("Error getting content language for {$contentType} {$contentId}: " . $e->getMessage());
            $language = $this->defaultLanguage;
        }

        // Cache the result
        $this->languageCache[$cacheKey] = $language;

        return $language;
    }

    /**
     * Get translations of content
     *
     * Gets all translation IDs for a given content item.
     *
     * @since 1.0.0
     * @param int $contentId Content ID
     * @param string $contentType Content type
     * @return array Array of translation IDs keyed by language code
     */
    public function getContentTranslations(int $contentId, string $contentType = 'post'): array
    {
        $translations = [];

        try {
            switch ($this->detectedPlugin) {
                case 'wpml':
                    if ($this->isWpmlActive()) {
                        $tridId = apply_filters('wpml_element_trid', null, $contentId, 'post_' . $contentType);
                        if ($tridId) {
                            $translations = apply_filters('wpml_get_element_translations', [], $tridId, 'post_' . $contentType);
                            $formattedTranslations = [];
                            foreach ($translations as $language => $translation) {
                                if (isset($translation->element_id)) {
                                    $formattedTranslations[$language] = (int) $translation->element_id;
                                }
                            }
                            $translations = $formattedTranslations;
                        }
                    }
                    break;

                case 'polylang':
                    if ($this->isPolylangActive()) {
                        $translations = pll_get_post_translations($contentId) ?: [];
                    }
                    break;

                case 'translatepress':
                    // TranslatePress uses the same ID for all languages
                    $translations[$this->getCurrentLanguage()] = $contentId;
                    break;
            }
        } catch (\Exception $e) {
            Utils::logError("Error getting translations for {$contentType} {$contentId}: " . $e->getMessage());
        }

        return $translations;
    }

    /**
     * Add language context to REST API requests
     *
     * @since 1.0.0
     * @param array $context Request context
     * @param \WP_REST_Request $request REST request object
     * @return array Modified context with language information
     */
    public function addLanguageContext(array $context, \WP_REST_Request $request): array
    {
        $context['language'] = [
            'current' => $this->getCurrentLanguage(),
            'default' => $this->getDefaultLanguage(),
            'available' => $this->getAvailableLanguages(),
            'plugin' => $this->getDetectedPlugin(),
            'is_multilingual' => $this->isMultilingualActive()
        ];

        return $context;
    }

    /**
     * Filter Knowledge Base queries by language
     *
     * @since 1.0.0
     * @param array $queryArgs Query arguments
     * @param string $language Target language (optional)
     * @return array Modified query arguments
     */
    public function filterKnowledgeBaseByLanguage(array $queryArgs, ?string $language = null): array
    {
        if (!$this->isMultilingualActive()) {
            return $queryArgs;
        }

        $targetLanguage = $language ?: $this->getCurrentLanguage();

        // Add language-specific meta query or modify existing query
        // This will be used by the Knowledge Base Scanner and Indexer
        if (!isset($queryArgs['meta_query'])) {
            $queryArgs['meta_query'] = [];
        }

        $queryArgs['meta_query'][] = [
            'key' => '_woo_ai_assistant_language',
            'value' => $targetLanguage,
            'compare' => '='
        ];

        // Also add relation if there are multiple meta queries
        if (count($queryArgs['meta_query']) > 1) {
            $queryArgs['meta_query']['relation'] = 'AND';
        }

        return $queryArgs;
    }

    /**
     * Add language to cache keys
     *
     * @since 1.0.0
     * @param string $cacheKey Original cache key
     * @param array $context Cache context
     * @return string Modified cache key with language
     */
    public function addLanguageToCache(string $cacheKey, array $context = []): string
    {
        if ($this->isMultilingualActive()) {
            $language = $context['language'] ?? $this->getCurrentLanguage();
            $cacheKey = $cacheKey . '_lang_' . $language;
        }

        return $cacheKey;
    }

    /**
     * Handle WPML language switch
     *
     * @since 1.0.0
     * @param string $newLanguage New language code
     * @param string $oldLanguage Old language code
     * @param array $context Additional context
     * @return void
     */
    public function onLanguageSwitch(string $newLanguage, string $oldLanguage, array $context = []): void
    {
        $this->currentLanguage = $newLanguage;
        $this->clearLanguageCache();

        /**
         * WPML language switched action
         *
         * @since 1.0.0
         * @param string $newLanguage New language code
         * @param string $oldLanguage Old language code
         */
        do_action('woo_ai_assistant_wpml_language_switched', $newLanguage, $oldLanguage);

        Utils::logDebug("WPML language switched from {$oldLanguage} to {$newLanguage}");
    }

    /**
     * Handle Polylang language defined
     *
     * @since 1.0.0
     * @param string $language Language code
     * @param \PLL_Model $model Polylang model
     * @return void
     */
    public function onPolylangLanguageDefined(string $language, $model = null): void
    {
        $this->currentLanguage = $language;
        $this->clearLanguageCache();

        /**
         * Polylang language defined action
         *
         * @since 1.0.0
         * @param string $language Language code
         */
        do_action('woo_ai_assistant_polylang_language_defined', $language);

        Utils::logDebug("Polylang language defined: {$language}");
    }

    /**
     * Handle TranslatePress language switch
     *
     * @since 1.0.0
     * @param string $newLanguage New language code
     * @param string $oldLanguage Old language code
     * @return void
     */
    public function onTranslatePressLanguageSwitch(string $newLanguage, string $oldLanguage): void
    {
        $this->currentLanguage = $newLanguage;
        $this->clearLanguageCache();

        /**
         * TranslatePress language switched action
         *
         * @since 1.0.0
         * @param string $newLanguage New language code
         * @param string $oldLanguage Old language code
         */
        do_action('woo_ai_assistant_translatepress_language_switched', $newLanguage, $oldLanguage);

        Utils::logDebug("TranslatePress language switched from {$oldLanguage} to {$newLanguage}");
    }

    /**
     * Get language-specific URL
     *
     * @since 1.0.0
     * @param string $url Original URL
     * @param string $language Target language
     * @return string Language-specific URL
     */
    public function getLanguageUrl(string $url, string $language): string
    {
        try {
            switch ($this->detectedPlugin) {
                case 'wpml':
                    if ($this->isWpmlActive()) {
                        $url = apply_filters('wpml_permalink', $url, $language);
                    }
                    break;

                case 'polylang':
                    if ($this->isPolylangActive() && function_exists('pll_get_post_language')) {
                        // Polylang URL modification would require the specific post ID
                        // This is a simplified implementation
                        $url = add_query_arg('lang', $language, $url);
                    }
                    break;

                case 'translatepress':
                    if ($this->isTranslatePressActive()) {
                        $url = apply_filters('trp_get_url_for_language', $url, $language);
                    }
                    break;
            }
        } catch (\Exception $e) {
            Utils::logError("Error getting language URL: " . $e->getMessage());
        }

        return $url;
    }

    /**
     * Cache language settings
     *
     * @since 1.0.0
     * @return void
     */
    private function cacheLanguageSettings(): void
    {
        $settings = [
            'current_language' => $this->currentLanguage,
            'default_language' => $this->defaultLanguage,
            'available_languages' => $this->availableLanguages,
            'detected_plugin' => $this->detectedPlugin,
            'cached_at' => time()
        ];

        wp_cache_set('woo_ai_assistant_language_settings', $settings, 'woo_ai_assistant', HOUR_IN_SECONDS);
    }

    /**
     * Clear language cache
     *
     * @since 1.0.0
     * @return void
     */
    private function clearLanguageCache(): void
    {
        $this->languageCache = [];
        wp_cache_delete('woo_ai_assistant_language_settings', 'woo_ai_assistant');

        /**
         * Language cache cleared action
         *
         * @since 1.0.0
         */
        do_action('woo_ai_assistant_language_cache_cleared');
    }

    /**
     * Get fallback content
     *
     * Returns content in the default language if current language version is not available.
     *
     * @since 1.0.0
     * @param int $contentId Content ID
     * @param string $contentType Content type
     * @param string $language Target language
     * @return int|null Fallback content ID or null if not found
     */
    public function getFallbackContent(int $contentId, string $contentType = 'post', ?string $language = null): ?int
    {
        if (!$this->isMultilingualActive()) {
            return $contentId;
        }

        $targetLanguage = $language ?: $this->getCurrentLanguage();

        // If requesting default language, return original
        if ($targetLanguage === $this->getDefaultLanguage()) {
            return $contentId;
        }

        try {
            $translations = $this->getContentTranslations($contentId, $contentType);

            // Try to get content in target language
            if (isset($translations[$targetLanguage])) {
                return (int) $translations[$targetLanguage];
            }

            // Fallback to default language
            if (isset($translations[$this->getDefaultLanguage()])) {
                Utils::logDebug("Using fallback language {$this->getDefaultLanguage()} for {$contentType} {$contentId}");
                return (int) $translations[$this->getDefaultLanguage()];
            }

            // If no translation found, return original
            return $contentId;
        } catch (\Exception $e) {
            Utils::logError("Error getting fallback content: " . $e->getMessage());
            return $contentId;
        }
    }

    /**
     * Is current language the default language
     *
     * @since 1.0.0
     * @return bool True if current language is the default
     */
    public function isDefaultLanguage(): bool
    {
        return $this->getCurrentLanguage() === $this->getDefaultLanguage();
    }

    /**
     * Get multilingual plugin info
     *
     * @since 1.0.0
     * @return array Plugin information
     */
    public function getPluginInfo(): array
    {
        return [
            'detected' => $this->detectedPlugin,
            'name' => $this->supportedPlugins[$this->detectedPlugin] ?? 'None',
            'is_active' => $this->isMultilingualActive(),
            'current_language' => $this->getCurrentLanguage(),
            'default_language' => $this->getDefaultLanguage(),
            'available_languages' => count($this->getAvailableLanguages()),
            'supported_plugins' => $this->supportedPlugins
        ];
    }
}
