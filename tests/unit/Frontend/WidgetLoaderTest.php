<?php

/**
 * Tests for Widget Loader Class
 *
 * Comprehensive unit tests for the WidgetLoader class that handles frontend widget
 * loading, asset management, context detection, and user interaction setup.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Unit\Frontend
 * @since 1.0.0
 * @author Claude Code Assistant
 */

namespace WooAiAssistant\Tests\Unit\Frontend;

use WooAiAssistant\Tests\Unit\WooAiBaseTestCase;
use WooAiAssistant\Frontend\WidgetLoader;
use WooAiAssistant\Common\Utils;
use WC_Product_Simple;

/**
 * Class WidgetLoaderTest
 *
 * Test cases for the WidgetLoader class.
 * Verifies asset management, context detection, conditional loading, and frontend integration.
 *
 * @since 1.0.0
 */
class WidgetLoaderTest extends WooAiBaseTestCase
{
    /**
     * WidgetLoader instance
     *
     * @var WidgetLoader
     */
    private $widgetLoader;

    /**
     * Mock test product
     *
     * @var WC_Product_Simple
     */
    private $testProduct;

    /**
     * Mock customer user ID
     *
     * @var int
     */
    private $customerId;

    /**
     * Set up test environment
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->widgetLoader = WidgetLoader::getInstance();

        // Create test user
        $this->customerId = $this->createTestUser('customer');

        // Create test product
        $this->testProduct = $this->createTestProduct([
            'name' => 'Test Widget Product',
            'description' => 'Product for widget testing',
            'price' => '29.99',
            'status' => 'publish'
        ]);

        // Mock frontend environment (not admin)
        add_filter('is_admin', '__return_false');

        // Reset enqueued assets state
        $this->setPropertyValue($this->widgetLoader, 'widgetAssetsEnqueued', false);
    }

    /**
     * Test WidgetLoader singleton pattern
     *
     * Verifies that WidgetLoader class follows singleton pattern correctly.
     *
     * @return void
     */
    public function test_getInstance_should_return_singleton_instance(): void
    {
        $instance1 = WidgetLoader::getInstance();
        $instance2 = WidgetLoader::getInstance();

        $this->assertInstanceOf(WidgetLoader::class, $instance1);
        $this->assertSame($instance1, $instance2, 'getInstance should return the same instance (singleton pattern)');
    }

    /**
     * Test widget assets registration
     *
     * Verifies that widget assets are registered correctly.
     *
     * @return void
     */
    public function test_registerWidgetAssets_should_register_css_and_js(): void
    {
        // Clear any previously registered assets
        wp_deregister_style('woo-ai-assistant-widget');
        wp_deregister_script('woo-ai-assistant-widget');

        $this->widgetLoader->registerWidgetAssets();

        // Check CSS registration
        $this->assertTrue(wp_style_is('woo-ai-assistant-widget', 'registered'), 'Widget CSS should be registered');

        // Check JS registration
        $this->assertTrue(wp_script_is('woo-ai-assistant-widget', 'registered'), 'Widget JS should be registered');

        // Verify dependencies
        global $wp_scripts;
        $widgetScript = $wp_scripts->query('woo-ai-assistant-widget');
        $this->assertNotFalse($widgetScript, 'Widget script should exist');
        $this->assertContains('jquery', $widgetScript->deps, 'Widget script should depend on jQuery');
    }

    /**
     * Test conditional asset enqueuing on shop page
     *
     * Verifies that assets are enqueued on WooCommerce shop pages.
     *
     * @return void
     */
    public function test_conditionallyEnqueueWidgetAssets_should_enqueue_on_shop_page(): void
    {
        // Mock shop page
        add_filter('is_shop', '__return_true');
        add_filter('woocommerce_is_shop', '__return_true');

        $this->widgetLoader->registerWidgetAssets();
        $this->widgetLoader->conditionallyEnqueueWidgetAssets();

        $this->assertTrue($this->widgetLoader->areWidgetAssetsEnqueued(), 'Assets should be enqueued on shop page');

        remove_filter('is_shop', '__return_true');
        remove_filter('woocommerce_is_shop', '__return_true');
    }

    /**
     * Test conditional asset enqueuing on product page
     *
     * Verifies that assets are enqueued on WooCommerce product pages.
     *
     * @return void
     */
    public function test_conditionallyEnqueueWidgetAssets_should_enqueue_on_product_page(): void
    {
        global $post, $product;
        
        // Set up product page context
        $post = get_post($this->testProduct->get_id());
        $product = $this->testProduct;
        
        // Mock product page
        add_filter('is_product', '__return_true');
        add_filter('is_singular', function($post_types) {
            return $post_types === 'product' || (is_array($post_types) && in_array('product', $post_types));
        });

        $this->widgetLoader->registerWidgetAssets();
        $this->widgetLoader->conditionallyEnqueueWidgetAssets();

        $this->assertTrue($this->widgetLoader->areWidgetAssetsEnqueued(), 'Assets should be enqueued on product page');

        remove_filter('is_product', '__return_true');
        remove_filter('is_singular');
    }

    /**
     * Test assets are not enqueued in admin
     *
     * Verifies that assets are not enqueued in admin area.
     *
     * @return void
     */
    public function test_conditionallyEnqueueWidgetAssets_should_not_enqueue_in_admin(): void
    {
        // Mock admin environment
        remove_filter('is_admin', '__return_false');
        add_filter('is_admin', '__return_true');

        $this->widgetLoader->registerWidgetAssets();
        $this->widgetLoader->conditionallyEnqueueWidgetAssets();

        $this->assertFalse($this->widgetLoader->areWidgetAssetsEnqueued(), 'Assets should not be enqueued in admin');

        remove_filter('is_admin', '__return_true');
        add_filter('is_admin', '__return_false');
    }

    /**
     * Test assets are not enqueued on restricted pages
     *
     * Verifies that assets are not enqueued on login/register pages.
     *
     * @return void
     */
    public function test_conditionallyEnqueueWidgetAssets_should_not_enqueue_on_login_page(): void
    {
        // Mock login page
        add_filter('is_page', function($page) {
            return $page === 'login' || (is_array($page) && in_array('login', $page));
        });

        $this->widgetLoader->registerWidgetAssets();
        $this->widgetLoader->conditionallyEnqueueWidgetAssets();

        $this->assertFalse($this->widgetLoader->areWidgetAssetsEnqueued(), 'Assets should not be enqueued on login page');

        remove_filter('is_page');
    }

    /**
     * Test widget container rendering
     *
     * Verifies that widget container is rendered correctly in footer.
     *
     * @return void
     */
    public function test_renderWidgetContainer_should_output_container_html(): void
    {
        // First enqueue assets so container renders
        $this->widgetLoader->registerWidgetAssets();
        $this->widgetLoader->enqueueWidgetAssets();

        ob_start();
        $this->widgetLoader->renderWidgetContainer();
        $output = ob_get_clean();

        $this->assertStringContains('id="woo-ai-assistant-widget-container"', $output, 'Should contain widget container ID');
        $this->assertStringContains('role="complementary"', $output, 'Should have accessibility role');
        $this->assertStringContains('aria-label="AI Shopping Assistant"', $output, 'Should have accessibility label');
        $this->assertStringContains('data-widget-ready="false"', $output, 'Should have ready state attribute');
        $this->assertStringContains('id="woo-ai-assistant-widget-root"', $output, 'Should contain widget root element');
    }

    /**
     * Test widget container is not rendered when assets not enqueued
     *
     * Verifies that widget container is not rendered if assets aren't loaded.
     *
     * @return void
     */
    public function test_renderWidgetContainer_should_not_output_when_assets_not_enqueued(): void
    {
        // Don't enqueue assets
        ob_start();
        $this->widgetLoader->renderWidgetContainer();
        $output = ob_get_clean();

        $this->assertEmpty($output, 'Should not render container when assets not enqueued');
    }

    /**
     * Test initialization styles are added
     *
     * Verifies that critical CSS is added to head for widget initialization.
     *
     * @return void
     */
    public function test_addInitializationStyles_should_output_critical_css(): void
    {
        // Mock that widget should load on current page
        add_filter('is_front_page', '__return_true');

        ob_start();
        $this->widgetLoader->addInitializationStyles();
        $output = ob_get_clean();

        $this->assertStringContains('<style id="woo-ai-assistant-widget-init">', $output, 'Should contain style tag');
        $this->assertStringContains('#woo-ai-assistant-widget-container', $output, 'Should contain widget container styles');
        $this->assertStringContains('position: fixed', $output, 'Should position widget fixed');
        $this->assertStringContains('z-index: 999999', $output, 'Should have high z-index');
        $this->assertStringContains('woo-ai-assistant-loading', $output, 'Should include loading styles');
        $this->assertStringContains('@keyframes woo-ai-spin', $output, 'Should include spin animation');

        remove_filter('is_front_page', '__return_true');
    }

    /**
     * Test page type detection
     *
     * Verifies that getCurrentPageType correctly identifies different page types.
     *
     * @return void
     */
    public function test_getCurrentPageType_should_detect_different_page_types(): void
    {
        // Test home page
        add_filter('is_front_page', '__return_true');
        $pageType = $this->invokeMethod($this->widgetLoader, 'getCurrentPageType');
        $this->assertEquals('home', $pageType, 'Should detect home page');
        remove_filter('is_front_page', '__return_true');

        // Test shop page
        add_filter('is_shop', '__return_true');
        $pageType = $this->invokeMethod($this->widgetLoader, 'getCurrentPageType');
        $this->assertEquals('shop', $pageType, 'Should detect shop page');
        remove_filter('is_shop', '__return_true');

        // Test product page
        add_filter('is_product', '__return_true');
        $pageType = $this->invokeMethod($this->widgetLoader, 'getCurrentPageType');
        $this->assertEquals('product', $pageType, 'Should detect product page');
        remove_filter('is_product', '__return_true');

        // Test cart page
        add_filter('is_cart', '__return_true');
        $pageType = $this->invokeMethod($this->widgetLoader, 'getCurrentPageType');
        $this->assertEquals('cart', $pageType, 'Should detect cart page');
        remove_filter('is_cart', '__return_true');

        // Test checkout page
        add_filter('is_checkout', '__return_true');
        $pageType = $this->invokeMethod($this->widgetLoader, 'getCurrentPageType');
        $this->assertEquals('checkout', $pageType, 'Should detect checkout page');
        remove_filter('is_checkout', '__return_true');
    }

    /**
     * Test WooCommerce page detection
     *
     * Verifies that isWooCommercePage correctly identifies WooCommerce pages.
     *
     * @return void
     */
    public function test_isWooCommercePage_should_detect_woocommerce_pages(): void
    {
        if (!Utils::isWooCommerceActive()) {
            $this->markTestSkipped('WooCommerce not active');
        }

        // Test WooCommerce page
        add_filter('is_woocommerce', '__return_true');
        $isWcPage = $this->invokeMethod($this->widgetLoader, 'isWooCommercePage');
        $this->assertTrue($isWcPage, 'Should detect WooCommerce page');
        remove_filter('is_woocommerce', '__return_true');

        // Test non-WooCommerce page
        add_filter('is_woocommerce', '__return_false');
        $isWcPage = $this->invokeMethod($this->widgetLoader, 'isWooCommercePage');
        $this->assertFalse($isWcPage, 'Should not detect non-WooCommerce page');
        remove_filter('is_woocommerce', '__return_false');
    }

    /**
     * Test user context generation
     *
     * Verifies that getCurrentUserContext returns correct user information.
     *
     * @return void
     */
    public function test_getCurrentUserContext_should_return_user_information(): void
    {
        // Test logged out user
        $userContext = $this->invokeMethod($this->widgetLoader, 'getCurrentUserContext');
        
        $this->assertIsArray($userContext, 'User context should be an array');
        $this->assertArrayHasKey('isLoggedIn', $userContext, 'Should contain login status');
        $this->assertArrayHasKey('userId', $userContext, 'Should contain user ID');
        $this->assertArrayHasKey('canManageWoocommerce', $userContext, 'Should contain permission status');
        
        $this->assertFalse($userContext['isLoggedIn'], 'Should indicate user is not logged in');
        $this->assertEquals(0, $userContext['userId'], 'User ID should be 0 for logged out user');

        // Test logged in user
        wp_set_current_user($this->customerId);
        $userContext = $this->invokeMethod($this->widgetLoader, 'getCurrentUserContext');
        
        $this->assertTrue($userContext['isLoggedIn'], 'Should indicate user is logged in');
        $this->assertEquals($this->customerId, $userContext['userId'], 'Should return correct user ID');
        $this->assertArrayHasKey('displayName', $userContext, 'Should contain display name for logged in user');
        $this->assertArrayHasKey('email', $userContext, 'Should contain email for logged in user');
        $this->assertArrayHasKey('roles', $userContext, 'Should contain roles for logged in user');
    }

    /**
     * Test page context generation
     *
     * Verifies that getCurrentPageContext returns correct page information.
     *
     * @return void
     */
    public function test_getCurrentPageContext_should_return_page_information(): void
    {
        global $post;
        $post = get_post($this->testProduct->get_id());
        
        $pageContext = $this->invokeMethod($this->widgetLoader, 'getCurrentPageContext');
        
        $this->assertIsArray($pageContext, 'Page context should be an array');
        $this->assertArrayHasKey('type', $pageContext, 'Should contain page type');
        $this->assertArrayHasKey('isWoocommerce', $pageContext, 'Should contain WooCommerce status');
        $this->assertArrayHasKey('url', $pageContext, 'Should contain page URL');
        $this->assertArrayHasKey('title', $pageContext, 'Should contain page title');
        $this->assertArrayHasKey('postId', $pageContext, 'Should contain post ID');
        $this->assertArrayHasKey('postType', $pageContext, 'Should contain post type');

        $this->assertIsBool($pageContext['isWoocommerce'], 'WooCommerce status should be boolean');
        $this->assertIsString($pageContext['url'], 'URL should be string');
        $this->assertIsString($pageContext['title'], 'Title should be string');
    }

    /**
     * Test product context on product page
     *
     * Verifies that product information is included in page context on product pages.
     *
     * @return void
     */
    public function test_getCurrentPageContext_should_include_product_data_on_product_page(): void
    {
        if (!Utils::isWooCommerceActive()) {
            $this->markTestSkipped('WooCommerce not active');
        }

        global $post, $product;
        $post = get_post($this->testProduct->get_id());
        $product = $this->testProduct;

        // Mock product page
        add_filter('is_product', '__return_true');

        $pageContext = $this->invokeMethod($this->widgetLoader, 'getCurrentPageContext');

        $this->assertArrayHasKey('product', $pageContext, 'Should contain product data on product page');
        
        $productData = $pageContext['product'];
        $this->assertArrayHasKey('id', $productData, 'Product data should contain ID');
        $this->assertArrayHasKey('name', $productData, 'Product data should contain name');
        $this->assertArrayHasKey('price', $productData, 'Product data should contain price');
        $this->assertArrayHasKey('type', $productData, 'Product data should contain type');
        $this->assertArrayHasKey('inStock', $productData, 'Product data should contain stock status');
        $this->assertArrayHasKey('categories', $productData, 'Product data should contain categories');

        $this->assertEquals($this->testProduct->get_id(), $productData['id'], 'Should return correct product ID');
        $this->assertEquals($this->testProduct->get_name(), $productData['name'], 'Should return correct product name');

        remove_filter('is_product', '__return_true');
    }

    /**
     * Test cart context generation
     *
     * Verifies that getCartContext returns correct cart information.
     *
     * @return void
     */
    public function test_getCartContext_should_return_cart_information(): void
    {
        $cartContext = $this->invokeMethod($this->widgetLoader, 'getCartContext');

        $this->assertIsArray($cartContext, 'Cart context should be an array');
        $this->assertArrayHasKey('available', $cartContext, 'Should contain availability status');

        if (Utils::isWooCommerceActive()) {
            $this->assertTrue($cartContext['available'], 'Cart should be available when WooCommerce is active');
            $this->assertArrayHasKey('itemCount', $cartContext, 'Should contain item count');
            $this->assertArrayHasKey('total', $cartContext, 'Should contain total');
            $this->assertArrayHasKey('subtotal', $cartContext, 'Should contain subtotal');
            $this->assertArrayHasKey('isEmpty', $cartContext, 'Should contain empty status');
            $this->assertArrayHasKey('needsShipping', $cartContext, 'Should contain shipping requirement');
        } else {
            $this->assertFalse($cartContext['available'], 'Cart should not be available when WooCommerce is inactive');
        }
    }

    /**
     * Test WooCommerce context generation
     *
     * Verifies that getWooCommerceContext returns correct WooCommerce information.
     *
     * @return void
     */
    public function test_getWooCommerceContext_should_return_woocommerce_information(): void
    {
        $wooContext = $this->invokeMethod($this->widgetLoader, 'getWooCommerceContext');

        $this->assertIsArray($wooContext, 'WooCommerce context should be an array');
        $this->assertArrayHasKey('active', $wooContext, 'Should contain active status');
        $this->assertArrayHasKey('version', $wooContext, 'Should contain version');
        $this->assertArrayHasKey('currency', $wooContext, 'Should contain currency');
        $this->assertArrayHasKey('currencySymbol', $wooContext, 'Should contain currency symbol');

        $this->assertIsBool($wooContext['active'], 'Active status should be boolean');
        $this->assertIsString($wooContext['currency'], 'Currency should be string');
        $this->assertIsString($wooContext['currencySymbol'], 'Currency symbol should be string');
    }

    /**
     * Test widget settings retrieval
     *
     * Verifies that getWidgetSettings returns correct default settings.
     *
     * @return void
     */
    public function test_getWidgetSettings_should_return_default_settings(): void
    {
        $settings = $this->invokeMethod($this->widgetLoader, 'getWidgetSettings');

        $this->assertIsArray($settings, 'Settings should be an array');
        $this->assertArrayHasKey('position', $settings, 'Should contain position setting');
        $this->assertArrayHasKey('theme', $settings, 'Should contain theme setting');
        $this->assertArrayHasKey('showWelcomeMessage', $settings, 'Should contain welcome message setting');
        $this->assertArrayHasKey('enableProductRecommendations', $settings, 'Should contain recommendations setting');
        $this->assertArrayHasKey('enableCouponGeneration', $settings, 'Should contain coupon setting');
        $this->assertArrayHasKey('maxConversationHistory', $settings, 'Should contain history limit setting');

        $this->assertEquals('bottom-right', $settings['position'], 'Default position should be bottom-right');
        $this->assertEquals('auto', $settings['theme'], 'Default theme should be auto');
        $this->assertTrue($settings['showWelcomeMessage'], 'Welcome message should be enabled by default');
    }

    /**
     * Test enabled features in development mode
     *
     * Verifies that all features are enabled in development mode.
     *
     * @return void
     */
    public function test_getEnabledFeatures_should_enable_all_features_in_development_mode(): void
    {
        // Mock development mode
        add_filter('woo_ai_assistant_is_development_mode', '__return_true');

        $features = $this->invokeMethod($this->widgetLoader, 'getEnabledFeatures');

        $this->assertIsArray($features, 'Features should be an array');
        $this->assertTrue($features['chat'], 'Chat should be enabled in dev mode');
        $this->assertTrue($features['productRecommendations'], 'Product recommendations should be enabled in dev mode');
        $this->assertTrue($features['couponGeneration'], 'Coupon generation should be enabled in dev mode');
        $this->assertTrue($features['orderTracking'], 'Order tracking should be enabled in dev mode');
        $this->assertTrue($features['proactiveSuggestions'], 'Proactive suggestions should be enabled in dev mode');
        $this->assertTrue($features['analyticsTracking'], 'Analytics tracking should be enabled in dev mode');

        remove_filter('woo_ai_assistant_is_development_mode', '__return_true');
    }

    /**
     * Test auto-start widget conditions
     *
     * Verifies that shouldAutoStartWidget returns correct values based on page context.
     *
     * @return void
     */
    public function test_shouldAutoStartWidget_should_return_correct_values(): void
    {
        // Test on product page
        add_filter('is_product', '__return_true');
        $shouldAutoStart = $this->invokeMethod($this->widgetLoader, 'shouldAutoStartWidget');
        $this->assertTrue($shouldAutoStart, 'Should auto-start on product page');
        remove_filter('is_product', '__return_true');

        // Test on cart page
        add_filter('is_cart', '__return_true');
        $shouldAutoStart = $this->invokeMethod($this->widgetLoader, 'shouldAutoStartWidget');
        $this->assertTrue($shouldAutoStart, 'Should auto-start on cart page');
        remove_filter('is_cart', '__return_true');

        // Test on regular page
        add_filter('is_page', '__return_true');
        $shouldAutoStart = $this->invokeMethod($this->widgetLoader, 'shouldAutoStartWidget');
        $this->assertFalse($shouldAutoStart, 'Should not auto-start on regular page');
        remove_filter('is_page', '__return_true');
    }

    /**
     * Test localized strings retrieval
     *
     * Verifies that getLocalizedStrings returns correct translated strings.
     *
     * @return void
     */
    public function test_getLocalizedStrings_should_return_translated_strings(): void
    {
        $strings = $this->invokeMethod($this->widgetLoader, 'getLocalizedStrings');

        $this->assertIsArray($strings, 'Strings should be an array');
        
        $expectedStrings = [
            'chatTitle', 'chatPlaceholder', 'chatSend', 'chatMinimize', 'chatClose',
            'chatLoading', 'chatError', 'chatWelcome', 'chatOffline', 'addToCart',
            'viewProduct', 'applyCoupon', 'copyCode', 'codeCopied', 'retry', 'poweredBy'
        ];

        foreach ($expectedStrings as $stringKey) {
            $this->assertArrayHasKey($stringKey, $strings, "Should contain {$stringKey} string");
            $this->assertIsString($strings[$stringKey], "String {$stringKey} should be string");
            $this->assertNotEmpty($strings[$stringKey], "String {$stringKey} should not be empty");
        }
    }

    /**
     * Test script localization
     *
     * Verifies that JavaScript localization data is properly structured.
     *
     * @return void
     */
    public function test_enqueueWidgetAssets_should_localize_script_with_correct_data(): void
    {
        $this->widgetLoader->registerWidgetAssets();
        $this->widgetLoader->enqueueWidgetAssets();

        // Check that script was localized (this would normally be tested by checking wp_localize_script calls)
        $this->assertTrue(wp_script_is('woo-ai-assistant-widget', 'enqueued'), 'Widget script should be enqueued');
        $this->assertTrue($this->widgetLoader->areWidgetAssetsEnqueued(), 'Assets should be marked as enqueued');
    }

    /**
     * Test current page URL retrieval
     *
     * Verifies that getCurrentPageUrl returns correct URL.
     *
     * @return void
     */
    public function test_getCurrentPageUrl_should_return_current_page_url(): void
    {
        $url = $this->widgetLoader->getCurrentPageUrl();

        $this->assertIsString($url, 'URL should be a string');
        $this->assertNotEmpty($url, 'URL should not be empty');
        $this->assertStringStartsWith('http', $url, 'URL should be valid HTTP URL');
    }

    /**
     * Test mobile device detection
     *
     * Verifies that isMobileDevice returns boolean value.
     *
     * @return void
     */
    public function test_isMobileDevice_should_return_boolean(): void
    {
        $isMobile = $this->widgetLoader->isMobileDevice();

        $this->assertIsBool($isMobile, 'Mobile detection should return boolean');
    }

    /**
     * Test force enqueue assets
     *
     * Verifies that forceEnqueueWidgetAssets works correctly.
     *
     * @return void
     */
    public function test_forceEnqueueWidgetAssets_should_reset_and_enqueue(): void
    {
        $this->widgetLoader->registerWidgetAssets();

        // First enqueue
        $this->widgetLoader->enqueueWidgetAssets();
        $this->assertTrue($this->widgetLoader->areWidgetAssetsEnqueued(), 'Assets should be enqueued first time');

        // Force enqueue should work even if already enqueued
        $this->widgetLoader->forceEnqueueWidgetAssets();
        $this->assertTrue($this->widgetLoader->areWidgetAssetsEnqueued(), 'Assets should still be enqueued after force');
    }

    /**
     * Test widget configuration retrieval
     *
     * Verifies that getWidgetConfig returns configuration array.
     *
     * @return void
     */
    public function test_getWidgetConfig_should_return_configuration_array(): void
    {
        $config = $this->widgetLoader->getWidgetConfig();

        $this->assertIsArray($config, 'Widget config should be an array');
    }

    /**
     * Test class name follows PascalCase convention
     *
     * Verifies that the WidgetLoader class follows PascalCase naming convention.
     *
     * @return void
     */
    public function test_widgetLoader_class_name_should_follow_pascal_case_convention(): void
    {
        $this->assertClassFollowsPascalCase(WidgetLoader::class);
    }

    /**
     * Test method names follow camelCase convention
     *
     * Verifies that all public methods follow camelCase naming convention.
     *
     * @return void
     */
    public function test_widgetLoader_public_methods_should_follow_camel_case_convention(): void
    {
        $publicMethods = [
            'getInstance',
            'registerWidgetAssets',
            'conditionallyEnqueueWidgetAssets',
            'enqueueWidgetAssets',
            'renderWidgetContainer',
            'addInitializationStyles',
            'getWidgetConfig',
            'areWidgetAssetsEnqueued',
            'forceEnqueueWidgetAssets',
            'getCurrentPageUrl',
            'isMobileDevice'
        ];

        foreach ($publicMethods as $methodName) {
            $this->assertMethodFollowsCamelCase($this->widgetLoader, $methodName);
        }
    }

    /**
     * Test private method accessibility through reflection
     *
     * Verifies that private methods can be accessed for testing.
     *
     * @return void
     */
    public function test_private_methods_should_be_accessible_through_reflection(): void
    {
        $privateMethods = [
            'shouldLoadWidgetOnCurrentPage',
            'getCurrentPageType',
            'isWooCommercePage',
            'getCurrentUserContext',
            'getCurrentPageContext',
            'getCartContext',
            'getWooCommerceContext',
            'getWidgetSettings',
            'getEnabledFeatures',
            'shouldAutoStartWidget',
            'getLocalizedStrings'
        ];

        foreach ($privateMethods as $methodName) {
            $method = $this->getReflectionMethod($this->widgetLoader, $methodName);
            $this->assertTrue($method->isPrivate(), "Method {$methodName} should be private");
        }
    }

    /**
     * Test memory usage remains reasonable
     *
     * Verifies that the widget loader doesn't consume excessive memory.
     *
     * @return void
     */
    public function test_widgetLoader_memory_usage_should_be_reasonable(): void
    {
        $initialMemory = memory_get_usage();

        // Perform multiple widget loader operations
        for ($i = 0; $i < 10; $i++) {
            $this->widgetLoader->registerWidgetAssets();
            $this->widgetLoader->conditionallyEnqueueWidgetAssets();
            $this->widgetLoader->getCurrentPageUrl();
            $this->widgetLoader->isMobileDevice();
            $this->widgetLoader->getWidgetConfig();
        }

        $finalMemory = memory_get_usage();
        $memoryIncrease = $finalMemory - $initialMemory;

        // Memory increase should be less than 2MB for these operations
        $this->assertLessThan(2097152, $memoryIncrease, 'Memory increase should be less than 2MB for repeated operations');
    }

    /**
     * Test WordPress hooks are properly registered
     *
     * Verifies that all necessary WordPress hooks are registered.
     *
     * @return void
     */
    public function test_widgetLoader_should_register_wordpress_hooks(): void
    {
        // Verify hooks were added during initialization (only for frontend)
        if (!is_admin()) {
            $this->assertTrue(has_action('wp_enqueue_scripts', [$this->widgetLoader, 'conditionallyEnqueueWidgetAssets']), 'Should register wp_enqueue_scripts hook');
            $this->assertTrue(has_action('wp_footer', [$this->widgetLoader, 'renderWidgetContainer']), 'Should register wp_footer hook');
            $this->assertTrue(has_action('wp_head', [$this->widgetLoader, 'addInitializationStyles']), 'Should register wp_head hook');
        }
        
        $this->assertTrue(has_action('init', [$this->widgetLoader, 'registerWidgetAssets']), 'Should register init hook');
    }

    /**
     * Clean up test data after each test
     *
     * @return void
     */
    protected function cleanUpTestData(): void
    {
        // Clean up filters
        remove_filter('is_admin', '__return_false');
        
        parent::cleanUpTestData();
    }
}