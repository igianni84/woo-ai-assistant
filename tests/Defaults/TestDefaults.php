<?php
/**
 * Test Defaults Configuration
 *
 * Provides consistent default values and configurations for all tests
 * to ensure predictable test behavior and reduce test interdependencies.
 *
 * @package WooAiAssistant\Tests\Defaults
 * @since 1.0.0
 */

namespace WooAiAssistant\Tests\Defaults;

/**
 * Test Defaults Class
 *
 * @since 1.0.0
 */
class TestDefaults
{
    /**
     * Default plugin options for testing
     */
    public const PLUGIN_OPTIONS = [
        'woo_ai_assistant_enabled' => true,
        'woo_ai_assistant_debug_mode' => true,
        'woo_ai_assistant_api_key' => 'test-api-key-12345',
        'woo_ai_assistant_welcome_message' => 'Hi! Welcome to Test Store Name! How can I help you today?',
        'woo_ai_assistant_auto_index' => true,
        'woo_ai_assistant_rate_limiting_enabled' => false,
        'woo_ai_assistant_license_key' => 'test-license-key-67890',
        'woo_ai_assistant_chunk_size' => 1000,
        'woo_ai_assistant_max_chunks_per_source' => 50,
        'woo_ai_assistant_embedding_model' => 'text-embedding-3-small',
        'woo_ai_assistant_chat_model' => 'google/gemini-2.5-flash',
        'woo_ai_assistant_features_enabled' => [
            'chat' => true,
            'knowledge_base' => true,
            'analytics' => true,
            'advanced_features' => true,
            'wishlist_integration' => true,
            'coupon_generation' => true
        ]
    ];
    
    /**
     * Default WordPress options for testing
     */
    public const WORDPRESS_OPTIONS = [
        'blogname' => 'Test Store Name',
        'blogdescription' => 'Your Test WooCommerce Store',
        'admin_email' => 'admin@teststore.com',
        'date_format' => 'F j, Y',
        'time_format' => 'g:i a',
        'timezone_string' => 'UTC'
    ];
    
    /**
     * Default WooCommerce options for testing
     */
    public const WOOCOMMERCE_OPTIONS = [
        'woocommerce_store_address' => '123 Test Store Street',
        'woocommerce_store_address_2' => 'Suite 456',
        'woocommerce_store_city' => 'Test City',
        'woocommerce_default_country' => 'US:CA',
        'woocommerce_store_postcode' => '90210',
        'woocommerce_currency' => 'USD',
        'woocommerce_currency_pos' => 'left',
        'woocommerce_price_thousand_sep' => ',',
        'woocommerce_price_decimal_sep' => '.',
        'woocommerce_price_num_decimals' => 2,
        'woocommerce_tax_enabled' => 'yes',
        'woocommerce_calc_taxes' => 'yes',
        'woocommerce_prices_include_tax' => 'no'
    ];
    
    /**
     * Default test user data
     */
    public const TEST_USERS = [
        'administrator' => [
            'user_login' => 'test_admin',
            'user_email' => 'admin@teststore.com',
            'user_pass' => 'test_password_123',
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'role' => 'administrator'
        ],
        'shop_manager' => [
            'user_login' => 'test_shop_manager',
            'user_email' => 'manager@teststore.com',
            'user_pass' => 'test_password_123',
            'first_name' => 'Test',
            'last_name' => 'Manager',
            'role' => 'shop_manager'
        ],
        'customer' => [
            'user_login' => 'test_customer',
            'user_email' => 'customer@example.com',
            'user_pass' => 'test_password_123',
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'role' => 'customer',
            'billing_first_name' => 'Test',
            'billing_last_name' => 'Customer',
            'billing_address_1' => '789 Customer Ave',
            'billing_city' => 'Test City',
            'billing_state' => 'CA',
            'billing_postcode' => '90210',
            'billing_country' => 'US'
        ],
        'subscriber' => [
            'user_login' => 'test_subscriber',
            'user_email' => 'subscriber@example.com',
            'user_pass' => 'test_password_123',
            'first_name' => 'Test',
            'last_name' => 'Subscriber',
            'role' => 'subscriber'
        ]
    ];
    
    /**
     * Default test product data
     */
    public const TEST_PRODUCTS = [
        'simple' => [
            'name' => 'Test Simple Product',
            'description' => 'This is a test simple product for unit testing purposes. It has all the standard features you would expect.',
            'short_description' => 'A test product for unit testing.',
            'regular_price' => '19.99',
            'sale_price' => '',
            'sku' => 'TEST-SIMPLE-001',
            'manage_stock' => false,
            'stock_status' => 'instock',
            'weight' => '1',
            'length' => '10',
            'width' => '8',
            'height' => '2',
            'category_ids' => [1],
            'tag_ids' => [1],
            'type' => 'simple',
            'status' => 'publish',
            'featured' => false
        ],
        'variable' => [
            'name' => 'Test Variable Product',
            'description' => 'This is a test variable product with multiple variations for comprehensive testing.',
            'short_description' => 'A variable test product.',
            'sku' => 'TEST-VARIABLE-001',
            'manage_stock' => false,
            'stock_status' => 'instock',
            'category_ids' => [1],
            'tag_ids' => [2],
            'type' => 'variable',
            'status' => 'publish',
            'variations' => [
                [
                    'attributes' => ['size' => 'small', 'color' => 'red'],
                    'regular_price' => '24.99',
                    'sku' => 'TEST-VAR-SMALL-RED'
                ],
                [
                    'attributes' => ['size' => 'large', 'color' => 'blue'],
                    'regular_price' => '29.99',
                    'sku' => 'TEST-VAR-LARGE-BLUE'
                ]
            ]
        ],
        'external' => [
            'name' => 'Test External Product',
            'description' => 'This is a test external/affiliate product that links to another site.',
            'short_description' => 'An external test product.',
            'regular_price' => '49.99',
            'sku' => 'TEST-EXTERNAL-001',
            'product_url' => 'https://example.com/external-product',
            'button_text' => 'Buy on External Site',
            'type' => 'external',
            'status' => 'publish'
        ]
    ];
    
    /**
     * Default conversation test data
     */
    public const TEST_CONVERSATION = [
        'conversation_id' => 'conv_test_12345',
        'status' => 'active',
        'context' => [
            'page' => 'shop',
            'user_type' => 'customer',
            'session_data' => [
                'cart_items' => 0,
                'viewed_products' => []
            ]
        ],
        'messages' => [
            [
                'sender' => 'user',
                'message' => 'Hello, I need help finding a product',
                'timestamp' => '2023-01-01 10:00:00'
            ],
            [
                'sender' => 'ai',
                'message' => 'Hello! I\'d be happy to help you find the perfect product. What are you looking for?',
                'timestamp' => '2023-01-01 10:00:05',
                'tokens_used' => 25,
                'model' => 'google/gemini-2.5-flash'
            ]
        ]
    ];
    
    /**
     * Default knowledge base test data
     */
    public const TEST_KNOWLEDGE_BASE = [
        [
            'source_type' => 'product',
            'source_id' => 123,
            'content' => 'Test Simple Product - This is a test simple product for unit testing purposes. Features include high quality materials and excellent customer support.',
            'metadata' => [
                'title' => 'Test Simple Product',
                'price' => '$19.99',
                'sku' => 'TEST-SIMPLE-001',
                'category' => 'Electronics'
            ]
        ],
        [
            'source_type' => 'page',
            'source_id' => 456,
            'content' => 'About Us - We are a test store dedicated to providing quality products and excellent customer service. Founded in 2023, we specialize in electronics and gadgets.',
            'metadata' => [
                'title' => 'About Us',
                'page_type' => 'static',
                'last_modified' => '2023-01-01'
            ]
        ],
        [
            'source_type' => 'category',
            'source_id' => 1,
            'content' => 'Electronics Category - Browse our wide selection of electronic products including smartphones, tablets, laptops, and accessories.',
            'metadata' => [
                'title' => 'Electronics',
                'product_count' => 25,
                'featured' => true
            ]
        ]
    ];
    
    /**
     * Default API response templates
     */
    public const API_RESPONSES = [
        'chat_success' => [
            'success' => true,
            'data' => [
                'response' => 'This is a mock AI response for testing purposes.',
                'conversation_id' => 'conv_test_12345',
                'tokens_used' => 50,
                'model' => 'google/gemini-2.5-flash',
                'context_used' => true
            ]
        ],
        'chat_error' => [
            'success' => false,
            'data' => [
                'code' => 'ai_error',
                'message' => 'Mock AI service error for testing'
            ]
        ],
        'action_success' => [
            'success' => true,
            'data' => [
                'action' => 'add_to_cart',
                'result' => 'Product added to cart successfully',
                'cart_count' => 1,
                'cart_total' => '$19.99'
            ]
        ],
        'action_error' => [
            'success' => false,
            'data' => [
                'code' => 'invalid_product',
                'message' => 'Product not found or unavailable'
            ]
        ]
    ];
    
    /**
     * Default rate limit settings for testing (all disabled)
     */
    public const RATE_LIMITS = [
        'chat_requests' => [
            'limit' => 999999,
            'window' => 3600,
            'enabled' => false
        ],
        'api_requests' => [
            'limit' => 999999,
            'window' => 3600,
            'enabled' => false
        ],
        'knowledge_base_queries' => [
            'limit' => 999999,
            'window' => 3600,
            'enabled' => false
        ]
    ];
    
    /**
     * Default license configuration for testing
     */
    public const LICENSE_CONFIG = [
        'valid' => true,
        'license_type' => 'premium',
        'expires_at' => '2025-12-31',
        'features' => [
            'chat' => true,
            'knowledge_base' => true,
            'analytics' => true,
            'advanced_features' => true,
            'unlimited_conversations' => true,
            'priority_support' => true
        ],
        'limits' => [
            'conversations_per_month' => 999999,
            'knowledge_base_chunks' => 999999,
            'api_requests_per_day' => 999999,
            'concurrent_users' => 999999
        ]
    ];
    
    /**
     * Get all default options combined
     */
    public static function getAllOptions(): array
    {
        return array_merge(
            self::PLUGIN_OPTIONS,
            self::WORDPRESS_OPTIONS,
            self::WOOCOMMERCE_OPTIONS
        );
    }
    
    /**
     * Get default test user by role
     */
    public static function getTestUser(string $role): array
    {
        return self::TEST_USERS[$role] ?? self::TEST_USERS['customer'];
    }
    
    /**
     * Get default test product by type
     */
    public static function getTestProduct(string $type = 'simple'): array
    {
        return self::TEST_PRODUCTS[$type] ?? self::TEST_PRODUCTS['simple'];
    }
    
    /**
     * Get default API response by type
     */
    public static function getApiResponse(string $type = 'chat_success'): array
    {
        return self::API_RESPONSES[$type] ?? self::API_RESPONSES['chat_success'];
    }
    
    /**
     * Get mock WordPress environment variables
     */
    public static function getWordPressEnvironment(): array
    {
        return [
            'HTTP_HOST' => 'teststore.local',
            'REQUEST_URI' => '/test',
            'REQUEST_METHOD' => 'POST',
            'SERVER_NAME' => 'teststore.local',
            'SERVER_PORT' => '80',
            'HTTPS' => '',
            'SCRIPT_NAME' => '/index.php',
            'QUERY_STRING' => '',
            'DOCUMENT_ROOT' => '/var/www/html',
            'REMOTE_ADDR' => '127.0.0.1',
            'REMOTE_HOST' => 'localhost',
            'USER_AGENT' => 'PHPUnit Test Suite'
        ];
    }
    
    /**
     * Get mock WooCommerce store configuration
     */
    public static function getStoreConfiguration(): array
    {
        return [
            'store_info' => [
                'name' => self::WORDPRESS_OPTIONS['blogname'],
                'description' => self::WORDPRESS_OPTIONS['blogdescription'],
                'address' => self::WOOCOMMERCE_OPTIONS['woocommerce_store_address'],
                'city' => self::WOOCOMMERCE_OPTIONS['woocommerce_store_city'],
                'country' => 'US',
                'currency' => self::WOOCOMMERCE_OPTIONS['woocommerce_currency']
            ],
            'shipping_zones' => [
                [
                    'name' => 'Test Shipping Zone',
                    'locations' => ['US:CA'],
                    'methods' => [
                        [
                            'method_id' => 'free_shipping',
                            'title' => 'Free Shipping',
                            'cost' => '0'
                        ]
                    ]
                ]
            ],
            'payment_gateways' => [
                'bacs' => [
                    'enabled' => 'yes',
                    'title' => 'Direct Bank Transfer'
                ],
                'cheque' => [
                    'enabled' => 'yes',
                    'title' => 'Check Payments'
                ],
                'cod' => [
                    'enabled' => 'yes',
                    'title' => 'Cash on Delivery'
                ]
            ]
        ];
    }
    
    /**
     * Get expected default welcome message with store name
     */
    public static function getExpectedWelcomeMessage(): string
    {
        $store_name = self::WORDPRESS_OPTIONS['blogname'];
        return "Hi! Welcome to {$store_name}! How can I help you today?";
    }
}