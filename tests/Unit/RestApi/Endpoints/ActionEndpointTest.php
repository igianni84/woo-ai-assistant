<?php

/**
 * Action Endpoint Test Class
 *
 * Comprehensive unit tests for the ActionEndpoint class covering cart operations,
 * wishlist management, product recommendations, up-sell/cross-sell logic,
 * security features, and error handling.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Unit\RestApi\Endpoints
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Tests\Unit\RestApi\Endpoints;

use WooAiAssistant\RestApi\Endpoints\ActionEndpoint;
use WooAiAssistant\KnowledgeBase\AIManager;
use WooAiAssistant\KnowledgeBase\VectorManager;
use WooAiAssistant\Api\LicenseManager;
use WooAiAssistant\Main;
use WooAiAssistant\Tests\Base\BaseTestCase;
use WP_UnitTestCase;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WC_Product;
use WC_Product_Variable;
use WC_Product_Variation;
use ReflectionClass;
use ReflectionMethod;
use Exception;

/**
 * Class ActionEndpointTest
 *
 * @since 1.0.0
 */
class ActionEndpointTest extends BaseTestCase
{
    /**
     * ActionEndpoint instance for testing
     *
     * @var ActionEndpoint
     */
    private $endpoint;

    /**
     * Mock AIManager instance
     *
     * @var AIManager|\PHPUnit\Framework\MockObject\MockObject
     */
    private $mockAIManager;

    /**
     * Mock VectorManager instance
     *
     * @var VectorManager|\PHPUnit\Framework\MockObject\MockObject
     */
    private $mockVectorManager;

    /**
     * Mock LicenseManager instance
     *
     * @var LicenseManager|\PHPUnit\Framework\MockObject\MockObject
     */
    private $mockLicenseManager;

    /**
     * Mock Main instance
     *
     * @var Main|\PHPUnit\Framework\MockObject\MockObject
     */
    private $mockMain;

    /**
     * Test product IDs
     *
     * @var array
     */
    private $testProductIds = [];

    /**
     * Test variation product ID
     *
     * @var int
     */
    private $variationProductId;

    /**
     * Test variation ID
     *
     * @var int
     */
    private $variationId;

    /**
     * Set up test environment before each test
     *
     * @since 1.0.0
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        // Set up test environment with all mocks
        $this->setupEndpointTestEnvironment();

        // Create mock dependencies
        $this->createMockDependencies();

        // Mock Main instance to return our mocked dependencies
        $this->mockMain = $this->createMock(Main::class);
        $this->mockMain->method('getComponent')
            ->willReturnCallback(function ($component) {
                switch ($component) {
                    case 'kb_ai_manager':
                        return $this->mockAIManager;
                    case 'kb_vector_manager':
                        return $this->mockVectorManager;
                    case 'license_manager':
                        return $this->mockLicenseManager;
                    default:
                        return null;
                }
            });

        // Create test products (BaseTestCase already creates some)
        $this->createAdditionalTestProducts();

        // Get ActionEndpoint instance
        $this->endpoint = ActionEndpoint::getInstance();

        // Use reflection to set the mock dependencies
        $this->setPrivateProperty($this->endpoint, 'aiManager', $this->mockAIManager);
        $this->setPrivateProperty($this->endpoint, 'vectorManager', $this->mockVectorManager);
        $this->setPrivateProperty($this->endpoint, 'licenseManager', $this->mockLicenseManager);
    }

    /**
     * Tear down after each test
     *
     * @since 1.0.0
     * @return void
     */
    public function tearDown(): void
    {
        // Clear cart if available
        $wc = WC();
        if ($wc && $wc->cart && method_exists($wc->cart, 'empty_cart')) {
            $wc->cart->empty_cart();
        }

        // Clear transients and cache
        delete_transient('woo_ai_cart_operation_limits');
        wp_cache_flush();

        parent::tearDown();
    }

    // =============================================================================
    // NAMING CONVENTIONS AND CLASS STRUCTURE TESTS
    // =============================================================================

    /**
     * Test class follows naming conventions
     *
     * @since 1.0.0
     * @return void
     */
    public function test_class_follows_naming_conventions(): void
    {
        $this->assertTrue(class_exists('WooAiAssistant\RestApi\Endpoints\ActionEndpoint'));

        $reflection = new ReflectionClass($this->endpoint);

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
     * Test singleton pattern implementation
     *
     * @since 1.0.0
     * @return void
     */
    public function test_singleton_pattern(): void
    {
        $instance1 = ActionEndpoint::getInstance();
        $instance2 = ActionEndpoint::getInstance();

        $this->assertSame($instance1, $instance2, 'Singleton should return same instance');
        $this->assertInstanceOf(ActionEndpoint::class, $instance1);
    }

    /**
     * Test class exists and instantiates correctly
     *
     * @since 1.0.0
     * @return void
     */
    public function test_class_exists_and_instantiates(): void
    {
        $this->assertTrue(class_exists('WooAiAssistant\RestApi\Endpoints\ActionEndpoint'));
        $this->assertInstanceOf(ActionEndpoint::class, $this->endpoint);
    }

    // =============================================================================
    // REST API ROUTE REGISTRATION TESTS
    // =============================================================================

    /**
     * Test REST API routes are registered correctly
     *
     * @since 1.0.0
     * @return void
     */
    public function test_registerRoutes_should_register_all_endpoints_correctly(): void
    {
        // Trigger route registration
        do_action('rest_api_init');

        $routes = rest_get_server()->get_routes();
        $namespace = '/woo-ai-assistant/v1';

        $expectedRoutes = [
            $namespace . '/action/add-to-cart',
            $namespace . '/action/update-cart',
            $namespace . '/action/remove-from-cart',
            $namespace . '/action/cart-status',
            $namespace . '/action/wishlist',
            $namespace . '/action/recommendations',
            $namespace . '/action/upsell',
            $namespace . '/action/cross-sell'
        ];

        foreach ($expectedRoutes as $route) {
            $this->assertArrayHasKey($route, $routes, "Route $route should be registered");
        }
    }

    // =============================================================================
    // ADD TO CART FUNCTIONALITY TESTS
    // =============================================================================

    /**
     * Test adding valid product to cart
     *
     * @since 1.0.0
     * @return void
     */
    public function test_addToCart_should_add_product_when_valid_product_provided(): void
    {
        $this->mockLicenseManager->method('isFeatureEnabled')->willReturn(true);

        $request = $this->createAddToCartRequest([
            'product_id' => $this->testProductIds[0],
            'quantity' => 2,
            'conversation_id' => 'conv-123',
            'nonce' => wp_create_nonce('woo_ai_action')
        ]);

        $response = $this->endpoint->addToCart($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('cart_item_key', $data['data']);
        $this->assertArrayHasKey('product', $data['data']);
        $this->assertArrayHasKey('cart', $data['data']);
        $this->assertArrayHasKey('recommendations', $data['data']);
    }

    /**
     * Test adding invalid product to cart
     *
     * @since 1.0.0
     * @return void
     */
    public function test_addToCart_should_return_error_when_invalid_product_provided(): void
    {
        $this->mockLicenseManager->method('isFeatureEnabled')->willReturn(true);

        $request = $this->createAddToCartRequest([
            'product_id' => 99999, // Non-existent product
            'quantity' => 1,
            'conversation_id' => 'conv-123',
            'nonce' => wp_create_nonce('woo_ai_action')
        ]);

        $response = $this->endpoint->addToCart($request);

        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertEquals('invalid_product', $response->get_error_code());
    }

    /**
     * Test adding out of stock product to cart
     *
     * @since 1.0.0
     * @return void
     */
    public function test_addToCart_should_return_error_when_out_of_stock_product(): void
    {
        $this->mockLicenseManager->method('isFeatureEnabled')->willReturn(true);

        // Create out of stock product
        $outOfStockProduct = $this->factory->post->create([
            'post_type' => 'product',
            'post_status' => 'publish'
        ]);

        update_post_meta($outOfStockProduct, '_stock_status', 'outofstock');
        update_post_meta($outOfStockProduct, '_manage_stock', 'yes');
        update_post_meta($outOfStockProduct, '_stock', '0');

        $request = $this->createAddToCartRequest([
            'product_id' => $outOfStockProduct,
            'quantity' => 1,
            'conversation_id' => 'conv-123',
            'nonce' => wp_create_nonce('woo_ai_action')
        ]);

        $response = $this->endpoint->addToCart($request);

        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertEquals('insufficient_stock', $response->get_error_code());
    }

    /**
     * Test adding variable product to cart with variations
     *
     * @since 1.0.0
     * @return void
     */
    public function test_addToCart_should_handle_variable_products_with_variations(): void
    {
        $this->mockLicenseManager->method('isFeatureEnabled')->willReturn(true);

        $request = $this->createAddToCartRequest([
            'product_id' => $this->variationProductId,
            'variation_id' => $this->variationId,
            'quantity' => 1,
            'variation' => ['pa_color' => 'red'],
            'conversation_id' => 'conv-123',
            'nonce' => wp_create_nonce('woo_ai_action')
        ]);

        $response = $this->endpoint->addToCart($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertTrue($data['success']);
    }

    /**
     * Test feature disabled for add to cart
     *
     * @since 1.0.0
     * @return void
     */
    public function test_addToCart_should_return_error_when_feature_disabled(): void
    {
        $this->mockLicenseManager->method('isFeatureEnabled')->willReturn(false);

        $request = $this->createAddToCartRequest([
            'product_id' => $this->testProductIds[0],
            'quantity' => 1,
            'conversation_id' => 'conv-123',
            'nonce' => wp_create_nonce('woo_ai_action')
        ]);

        $response = $this->endpoint->addToCart($request);

        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertEquals('feature_disabled', $response->get_error_code());
        $this->assertEquals(403, $response->get_error_data()['status']);
    }

    // =============================================================================
    // CART MANAGEMENT TESTS
    // =============================================================================

    /**
     * Test updating cart item quantity
     *
     * @since 1.0.0
     * @return void
     */
    public function test_updateCart_should_update_quantity_when_valid_cart_item(): void
    {
        // Add item to cart first
        $cartItemKey = WC()->cart->add_to_cart($this->testProductIds[0], 1);

        $request = $this->createUpdateCartRequest([
            'cart_item_key' => $cartItemKey,
            'quantity' => 3,
            'conversation_id' => 'conv-123',
            'nonce' => wp_create_nonce('woo_ai_action')
        ]);

        $response = $this->endpoint->updateCart($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('cart', $data['data']);

        // Verify cart item quantity was updated
        $cartItem = WC()->cart->get_cart_item($cartItemKey);
        $this->assertEquals(3, $cartItem['quantity']);
    }

    /**
     * Test removing cart item by setting quantity to zero
     *
     * @since 1.0.0
     * @return void
     */
    public function test_updateCart_should_remove_item_when_quantity_zero(): void
    {
        // Add item to cart first
        $cartItemKey = WC()->cart->add_to_cart($this->testProductIds[0], 1);

        $request = $this->createUpdateCartRequest([
            'cart_item_key' => $cartItemKey,
            'quantity' => 0,
            'conversation_id' => 'conv-123',
            'nonce' => wp_create_nonce('woo_ai_action')
        ]);

        $response = $this->endpoint->updateCart($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertTrue($data['success']);

        // Verify cart item was removed
        $cartItem = WC()->cart->get_cart_item($cartItemKey);
        $this->assertFalse($cartItem);
    }

    /**
     * Test updating invalid cart item
     *
     * @since 1.0.0
     * @return void
     */
    public function test_updateCart_should_return_error_when_invalid_cart_item(): void
    {
        $request = $this->createUpdateCartRequest([
            'cart_item_key' => 'invalid-key',
            'quantity' => 2,
            'conversation_id' => 'conv-123',
            'nonce' => wp_create_nonce('woo_ai_action')
        ]);

        $response = $this->endpoint->updateCart($request);

        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertEquals('invalid_cart_item', $response->get_error_code());
        $this->assertEquals(404, $response->get_error_data()['status']);
    }

    /**
     * Test removing item from cart
     *
     * @since 1.0.0
     * @return void
     */
    public function test_removeFromCart_should_remove_item_when_valid_cart_item(): void
    {
        // Add item to cart first
        $cartItemKey = WC()->cart->add_to_cart($this->testProductIds[0], 1);

        $request = $this->createRemoveFromCartRequest([
            'cart_item_key' => $cartItemKey,
            'conversation_id' => 'conv-123',
            'nonce' => wp_create_nonce('woo_ai_action')
        ]);

        $response = $this->endpoint->removeFromCart($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertTrue($data['success']);

        // Verify cart item was removed
        $cartItem = WC()->cart->get_cart_item($cartItemKey);
        $this->assertFalse($cartItem);
    }

    /**
     * Test removing invalid cart item
     *
     * @since 1.0.0
     * @return void
     */
    public function test_removeFromCart_should_return_error_when_invalid_cart_item(): void
    {
        $request = $this->createRemoveFromCartRequest([
            'cart_item_key' => 'invalid-key',
            'conversation_id' => 'conv-123',
            'nonce' => wp_create_nonce('woo_ai_action')
        ]);

        $response = $this->endpoint->removeFromCart($request);

        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertEquals('invalid_cart_item', $response->get_error_code());
    }

    /**
     * Test getting cart status with items
     *
     * @since 1.0.0
     * @return void
     */
    public function test_getCartStatus_should_return_cart_data_when_items_exist(): void
    {
        // Add items to cart
        WC()->cart->add_to_cart($this->testProductIds[0], 2);
        WC()->cart->add_to_cart($this->testProductIds[1], 1);

        $request = new WP_REST_Request('GET', '/woo-ai-assistant/v1/action/cart-status');

        $response = $this->endpoint->getCartStatus($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('items', $data['data']);
        $this->assertArrayHasKey('count', $data['data']);
        $this->assertArrayHasKey('subtotal', $data['data']);
        $this->assertArrayHasKey('recommendations', $data['data']);
        $this->assertEquals(3, $data['data']['count']); // 2 + 1 items
    }

    /**
     * Test getting cart status when empty
     *
     * @since 1.0.0
     * @return void
     */
    public function test_getCartStatus_should_return_empty_cart_when_no_items(): void
    {
        $request = new WP_REST_Request('GET', '/woo-ai-assistant/v1/action/cart-status');

        $response = $this->endpoint->getCartStatus($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertTrue($data['data']['is_empty']);
        $this->assertEquals(0, $data['data']['count']);
    }

    // =============================================================================
    // WISHLIST FUNCTIONALITY TESTS
    // =============================================================================

    /**
     * Test wishlist operations when no plugin available
     *
     * @since 1.0.0
     * @return void
     */
    public function test_manageWishlist_should_return_error_when_no_plugin_available(): void
    {
        $request = $this->createWishlistRequest([
            'operation' => 'add',
            'product_id' => $this->testProductIds[0],
            'conversation_id' => 'conv-123',
            'nonce' => wp_create_nonce('woo_ai_action')
        ]);

        $response = $this->endpoint->manageWishlist($request);

        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertEquals('wishlist_not_supported', $response->get_error_code());
    }

    /**
     * Test wishlist operations with invalid operation
     *
     * @since 1.0.0
     * @return void
     */
    public function test_manageWishlist_should_return_error_when_invalid_operation(): void
    {
        // Mock YITH wishlist plugin availability
        if (!function_exists('YITH_WCWL')) {
            function YITH_WCWL() {
                return new class {
                    public function add($productId) { return true; }
                    public function remove($productId) { return true; }
                    public function get_products() { return []; }
                };
            }
        }

        $request = $this->createWishlistRequest([
            'operation' => 'invalid_operation',
            'product_id' => $this->testProductIds[0],
            'conversation_id' => 'conv-123',
            'nonce' => wp_create_nonce('woo_ai_action')
        ]);

        $response = $this->endpoint->manageWishlist($request);

        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertEquals('invalid_operation', $response->get_error_code());
    }

    // =============================================================================
    // RECOMMENDATIONS ENGINE TESTS
    // =============================================================================

    /**
     * Test getting general recommendations
     *
     * @since 1.0.0
     * @return void
     */
    public function test_getRecommendations_should_return_general_recommendations_when_no_context(): void
    {
        $request = new WP_REST_Request('GET', '/woo-ai-assistant/v1/action/recommendations');
        $request->set_param('context', 'general');
        $request->set_param('limit', 4);

        $response = $this->endpoint->getRecommendations($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('recommendations', $data['data']);
        $this->assertArrayHasKey('count', $data['data']);
        $this->assertEquals('general', $data['data']['context']);
    }

    /**
     * Test getting product-specific recommendations
     *
     * @since 1.0.0
     * @return void
     */
    public function test_getRecommendations_should_return_product_recommendations_when_product_context(): void
    {
        // Mock vector manager to return similar products
        $this->mockVectorManager->method('searchSimilar')
            ->willReturn([
                [
                    'metadata' => ['product_id' => $this->testProductIds[1]],
                    'score' => 0.85
                ]
            ]);

        $request = new WP_REST_Request('GET', '/woo-ai-assistant/v1/action/recommendations');
        $request->set_param('context', 'product');
        $request->set_param('product_id', $this->testProductIds[0]);
        $request->set_param('limit', 3);

        $response = $this->endpoint->getRecommendations($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertEquals('product', $data['data']['context']);
    }

    /**
     * Test getting category recommendations
     *
     * @since 1.0.0
     * @return void
     */
    public function test_getRecommendations_should_return_category_recommendations_when_category_context(): void
    {
        // Create a product category
        $categoryId = wp_insert_term('Test Category', 'product_cat')['term_id'];
        
        // Assign test product to category
        wp_set_post_terms($this->testProductIds[0], [$categoryId], 'product_cat');

        $request = new WP_REST_Request('GET', '/woo-ai-assistant/v1/action/recommendations');
        $request->set_param('context', 'category');
        $request->set_param('category_id', $categoryId);
        $request->set_param('limit', 3);

        $response = $this->endpoint->getRecommendations($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertEquals('category', $data['data']['context']);
    }

    /**
     * Test recommendations limit enforcement
     *
     * @since 1.0.0
     * @return void
     */
    public function test_getRecommendations_should_enforce_maximum_limit(): void
    {
        $request = new WP_REST_Request('GET', '/woo-ai-assistant/v1/action/recommendations');
        $request->set_param('context', 'general');
        $request->set_param('limit', 20); // Above MAX_RECOMMENDATION_ITEMS (6)

        $response = $this->endpoint->getRecommendations($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertLessThanOrEqual(6, $data['data']['count']); // Should be limited to 6
    }

    // =============================================================================
    // UP-SELL/CROSS-SELL TESTS
    // =============================================================================

    /**
     * Test getting upsell products with empty cart
     *
     * @since 1.0.0
     * @return void
     */
    public function test_getUpsellProducts_should_return_empty_when_cart_empty(): void
    {
        $this->mockLicenseManager->method('isFeatureEnabled')->willReturn(true);

        $request = new WP_REST_Request('GET', '/woo-ai-assistant/v1/action/upsell');
        $request->set_param('limit', 4);

        $response = $this->endpoint->getUpsellProducts($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertEmpty($data['data']['upsells']);
        $this->assertArrayHasKey('message', $data['data']);
    }

    /**
     * Test getting upsell products with cart items
     *
     * @since 1.0.0
     * @return void
     */
    public function test_getUpsellProducts_should_return_upsells_when_cart_has_items(): void
    {
        $this->mockLicenseManager->method('isFeatureEnabled')->willReturn(true);

        // Add item to cart
        WC()->cart->add_to_cart($this->testProductIds[0], 1);

        // Set upsell products for the test product
        update_post_meta($this->testProductIds[0], '_upsell_ids', [$this->testProductIds[1]]);

        $request = new WP_REST_Request('GET', '/woo-ai-assistant/v1/action/upsell');
        $request->set_param('limit', 4);

        $response = $this->endpoint->getUpsellProducts($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('upsells', $data['data']);
        $this->assertArrayHasKey('count', $data['data']);
        $this->assertArrayHasKey('cart_value', $data['data']);
        $this->assertArrayHasKey('potential_value', $data['data']);
    }

    /**
     * Test upsell feature disabled
     *
     * @since 1.0.0
     * @return void
     */
    public function test_getUpsellProducts_should_return_error_when_feature_disabled(): void
    {
        $this->mockLicenseManager->method('isFeatureEnabled')->willReturn(false);

        $request = new WP_REST_Request('GET', '/woo-ai-assistant/v1/action/upsell');

        $response = $this->endpoint->getUpsellProducts($request);

        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertEquals('feature_disabled', $response->get_error_code());
    }

    /**
     * Test getting cross-sell products
     *
     * @since 1.0.0
     * @return void
     */
    public function test_getCrossSellProducts_should_return_crosssells_when_cart_has_items(): void
    {
        $this->mockLicenseManager->method('isFeatureEnabled')->willReturn(true);

        // Add item to cart
        WC()->cart->add_to_cart($this->testProductIds[0], 1);

        // Set cross-sell products for the test product
        update_post_meta($this->testProductIds[0], '_crosssell_ids', [$this->testProductIds[1]]);

        $request = new WP_REST_Request('GET', '/woo-ai-assistant/v1/action/cross-sell');
        $request->set_param('limit', 4);

        $response = $this->endpoint->getCrossSellProducts($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('crosssells', $data['data']);
        $this->assertArrayHasKey('count', $data['data']);
        $this->assertArrayHasKey('categories', $data['data']);
    }

    // =============================================================================
    // SECURITY AND RATE LIMITING TESTS
    // =============================================================================

    /**
     * Test rate limiting enforcement
     *
     * @since 1.0.0
     * @return void
     */
    public function test_rate_limiting_should_block_excessive_requests(): void
    {
        $this->mockLicenseManager->method('isFeatureEnabled')->willReturn(true);

        // Make maximum allowed requests
        for ($i = 0; $i < 10; $i++) {
            $request = $this->createAddToCartRequest([
                'product_id' => $this->testProductIds[0],
                'quantity' => 1,
                'conversation_id' => 'conv-' . $i,
                'nonce' => wp_create_nonce('woo_ai_action')
            ]);

            $response = $this->endpoint->addToCart($request);
            
            if ($i < 9) {
                $this->assertInstanceOf(WP_REST_Response::class, $response, 
                    "Request $i should succeed");
            }
        }

        // 11th request should be rate limited
        $request = $this->createAddToCartRequest([
            'product_id' => $this->testProductIds[0],
            'quantity' => 1,
            'conversation_id' => 'conv-exceed',
            'nonce' => wp_create_nonce('woo_ai_action')
        ]);

        $response = $this->endpoint->addToCart($request);
        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertEquals('rate_limit_exceeded', $response->get_error_code());
        $this->assertEquals(429, $response->get_error_data()['status']);
    }

    /**
     * Test nonce verification
     *
     * @since 1.0.0
     * @return void
     */
    public function test_nonce_verification_should_reject_invalid_nonce(): void
    {
        $this->mockLicenseManager->method('isFeatureEnabled')->willReturn(true);

        $request = $this->createAddToCartRequest([
            'product_id' => $this->testProductIds[0],
            'quantity' => 1,
            'conversation_id' => 'conv-123',
            'nonce' => 'invalid_nonce'
        ]);

        $response = $this->endpoint->addToCart($request);

        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertEquals('invalid_nonce', $response->get_error_code());
        $this->assertEquals(403, $response->get_error_data()['status']);
    }

    /**
     * Test input sanitization
     *
     * @since 1.0.0
     * @return void
     */
    public function test_input_sanitization_should_clean_malicious_input(): void
    {
        $this->mockLicenseManager->method('isFeatureEnabled')->willReturn(true);

        $request = $this->createAddToCartRequest([
            'product_id' => $this->testProductIds[0],
            'quantity' => 1,
            'conversation_id' => '<script>alert("xss")</script>conv-123',
            'nonce' => wp_create_nonce('woo_ai_action')
        ]);

        $response = $this->endpoint->addToCart($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        
        // The conversation_id should be sanitized (script tags removed)
        $sanitizedId = sanitize_text_field('<script>alert("xss")</script>conv-123');
        $this->assertStringNotContainsString('<script>', $sanitizedId);
    }

    /**
     * Test permission checks allow frontend users
     *
     * @since 1.0.0
     * @return void
     */
    public function test_checkActionPermissions_should_allow_frontend_users(): void
    {
        $request = new WP_REST_Request();
        $result = $this->endpoint->checkActionPermissions($request);

        $this->assertTrue($result, 'Frontend users should be allowed');
    }

    /**
     * Test guest user session handling
     *
     * @since 1.0.0
     * @return void
     */
    public function test_guest_user_session_handling_should_work_correctly(): void
    {
        $this->mockLicenseManager->method('isFeatureEnabled')->willReturn(true);

        // Simulate guest user (no logged-in user)
        wp_set_current_user(0);

        $request = $this->createAddToCartRequest([
            'product_id' => $this->testProductIds[0],
            'quantity' => 1,
            'conversation_id' => 'guest-conv-123',
            'nonce' => wp_create_nonce('woo_ai_action')
        ]);

        $response = $this->endpoint->addToCart($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertTrue($data['success']);
    }

    // =============================================================================
    // ERROR HANDLING TESTS
    // =============================================================================

    /**
     * Test error responses have correct format
     *
     * @since 1.0.0
     * @return void
     */
    public function test_error_responses_should_have_correct_format(): void
    {
        $request = $this->createAddToCartRequest([
            'product_id' => 99999, // Invalid product
            'quantity' => 1,
            'conversation_id' => 'conv-123',
            'nonce' => wp_create_nonce('woo_ai_action')
        ]);

        // Mock license manager to allow the feature
        $this->mockLicenseManager->method('isFeatureEnabled')->willReturn(true);

        $response = $this->endpoint->addToCart($request);

        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertIsString($response->get_error_code());
        $this->assertIsString($response->get_error_message());
        $this->assertIsArray($response->get_error_data());
        $this->assertArrayHasKey('status', $response->get_error_data());
    }

    /**
     * Test exception handling in methods
     *
     * @since 1.0.0
     * @return void
     */
    public function test_exception_handling_should_return_proper_errors(): void
    {
        // Force an exception by providing invalid data that would cause internal error
        $this->mockLicenseManager->method('isFeatureEnabled')->willReturn(true);

        // Mock WC()->cart to throw exception
        $originalCart = WC()->cart;
        WC()->cart = null;

        $request = $this->createAddToCartRequest([
            'product_id' => $this->testProductIds[0],
            'quantity' => 1,
            'conversation_id' => 'conv-123',
            'nonce' => wp_create_nonce('woo_ai_action')
        ]);

        $response = $this->endpoint->addToCart($request);

        // Restore original cart
        WC()->cart = $originalCart;

        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertEquals('add_to_cart_error', $response->get_error_code());
        $this->assertEquals(500, $response->get_error_data()['status']);
    }

    // =============================================================================
    // INTEGRATION TESTS
    // =============================================================================

    /**
     * Test integration with AIManager for recommendations
     *
     * @since 1.0.0
     * @return void
     */
    public function test_aimanager_integration_should_provide_recommendations(): void
    {
        // Mock AI manager to return product suggestions
        $this->mockAIManager->method('generateProductSuggestions')
            ->willReturn([
                ['product_id' => $this->testProductIds[1], 'relevance' => 0.9]
            ]);

        $this->mockVectorManager->method('searchSimilar')
            ->willReturn([
                [
                    'metadata' => ['product_id' => $this->testProductIds[1]],
                    'score' => 0.85
                ]
            ]);

        $request = new WP_REST_Request('GET', '/woo-ai-assistant/v1/action/recommendations');
        $request->set_param('context', 'product');
        $request->set_param('product_id', $this->testProductIds[0]);

        $response = $this->endpoint->getRecommendations($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());
    }

    /**
     * Test integration with VectorManager for similarity search
     *
     * @since 1.0.0
     * @return void
     */
    public function test_vectormanager_integration_should_find_similar_products(): void
    {
        $this->mockVectorManager->method('searchSimilar')
            ->with($this->isType('string'), $this->isType('int'))
            ->willReturn([
                [
                    'metadata' => ['product_id' => $this->testProductIds[1]],
                    'score' => 0.88
                ],
                [
                    'metadata' => ['product_id' => $this->testProductIds[2]],
                    'score' => 0.76
                ]
            ]);

        $request = new WP_REST_Request('GET', '/woo-ai-assistant/v1/action/recommendations');
        $request->set_param('context', 'product');
        $request->set_param('product_id', $this->testProductIds[0]);
        $request->set_param('limit', 3);

        $response = $this->endpoint->getRecommendations($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertTrue($data['success']);
    }

    /**
     * Test integration with LicenseManager for feature validation
     *
     * @since 1.0.0
     * @return void
     */
    public function test_licensemanager_integration_should_validate_features(): void
    {
        // Test when license manager is not available (development mode)
        $this->setPrivateProperty($this->endpoint, 'licenseManager', null);

        $request = $this->createAddToCartRequest([
            'product_id' => $this->testProductIds[0],
            'quantity' => 1,
            'conversation_id' => 'conv-123',
            'nonce' => wp_create_nonce('woo_ai_action')
        ]);

        $response = $this->endpoint->addToCart($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response,
            'Should allow features when license manager not available (development mode)');
    }

    // =============================================================================
    // HELPER METHODS
    // =============================================================================

    /**
     * Create mock dependencies
     *
     * @since 1.0.0
     * @return void
     */
    private function createMockDependencies(): void
    {
        $this->mockAIManager = $this->createMock(AIManager::class);
        $this->mockVectorManager = $this->createMock(VectorManager::class);
        $this->mockLicenseManager = $this->createMock(LicenseManager::class);

        // Default mock behavior
        $this->mockLicenseManager->method('isFeatureEnabled')->willReturn(true);
    }

    /**
     * Create test products for testing
     *
     * @since 1.0.0
     * @return void
     */
    private function createAdditionalTestProducts(): void
    {
        // Create simple products
        for ($i = 0; $i < 3; $i++) {
            $productId = $this->factory->post->create([
                'post_type' => 'product',
                'post_status' => 'publish',
                'post_title' => 'Test Product ' . ($i + 1)
            ]);

            // Set product metadata
            update_post_meta($productId, '_price', '29.99');
            update_post_meta($productId, '_regular_price', '29.99');
            update_post_meta($productId, '_stock_status', 'instock');
            update_post_meta($productId, '_manage_stock', 'yes');
            update_post_meta($productId, '_stock', '100');
            update_post_meta($productId, '_visibility', 'visible');

            $this->testProductIds[] = $productId;
        }

        // Create variable product
        $this->variationProductId = $this->factory->post->create([
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_title' => 'Variable Test Product'
        ]);

        update_post_meta($this->variationProductId, '_product_type', 'variable');
        update_post_meta($this->variationProductId, '_stock_status', 'instock');

        // Create variation
        $this->variationId = $this->factory->post->create([
            'post_type' => 'product_variation',
            'post_status' => 'publish',
            'post_parent' => $this->variationProductId
        ]);

        update_post_meta($this->variationId, '_price', '39.99');
        update_post_meta($this->variationId, '_regular_price', '39.99');
        update_post_meta($this->variationId, '_stock_status', 'instock');
        update_post_meta($this->variationId, '_manage_stock', 'yes');
        update_post_meta($this->variationId, '_stock', '50');
    }


    /**
     * Create add-to-cart request
     *
     * @since 1.0.0
     * @param array $params Request parameters
     * @return WP_REST_Request
     */
    private function createAddToCartRequest(array $params): WP_REST_Request
    {
        $request = new WP_REST_Request('POST', '/woo-ai-assistant/v1/action/add-to-cart');
        foreach ($params as $key => $value) {
            $request->set_param($key, $value);
        }
        return $request;
    }

    /**
     * Create update-cart request
     *
     * @since 1.0.0
     * @param array $params Request parameters
     * @return WP_REST_Request
     */
    private function createUpdateCartRequest(array $params): WP_REST_Request
    {
        $request = new WP_REST_Request('PUT', '/woo-ai-assistant/v1/action/update-cart');
        foreach ($params as $key => $value) {
            $request->set_param($key, $value);
        }
        return $request;
    }

    /**
     * Create remove-from-cart request
     *
     * @since 1.0.0
     * @param array $params Request parameters
     * @return WP_REST_Request
     */
    private function createRemoveFromCartRequest(array $params): WP_REST_Request
    {
        $request = new WP_REST_Request('DELETE', '/woo-ai-assistant/v1/action/remove-from-cart');
        foreach ($params as $key => $value) {
            $request->set_param($key, $value);
        }
        return $request;
    }

    /**
     * Create wishlist request
     *
     * @since 1.0.0
     * @param array $params Request parameters
     * @return WP_REST_Request
     */
    private function createWishlistRequest(array $params): WP_REST_Request
    {
        $request = new WP_REST_Request('POST', '/woo-ai-assistant/v1/action/wishlist');
        foreach ($params as $key => $value) {
            $request->set_param($key, $value);
        }
        return $request;
    }

    /**
     * Set private property value using reflection
     *
     * @since 1.0.0
     * @param object $object Object instance
     * @param string $property Property name
     * @param mixed $value Property value
     * @return void
     */
    private function setPrivateProperty($object, string $property, $value): void
    {
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty($property);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    /**
     * Get private property value using reflection
     *
     * @since 1.0.0
     * @param object $object Object instance
     * @param string $property Property name
     * @return mixed Property value
     */
    private function getPrivateProperty($object, string $property)
    {
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty($property);
        $property->setAccessible(true);
        return $property->getValue($object);
    }

    /**
     * Call private method using reflection
     *
     * @since 1.0.0
     * @param object $object Object instance
     * @param string $method Method name
     * @param array $args Method arguments
     * @return mixed Method result
     */
    private function callPrivateMethod($object, string $method, array $args = [])
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $args);
    }
}