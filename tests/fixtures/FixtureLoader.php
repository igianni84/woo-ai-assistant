<?php

/**
 * Fixture Loader Utility Class
 *
 * Provides utilities for loading test fixtures including sample products,
 * users, and plugin configurations for testing purposes.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Fixtures
 * @since 1.0.0
 * @author Claude Code Assistant
 */

namespace WooAiAssistant\Tests\Fixtures;

/**
 * Class FixtureLoader
 *
 * Utility class for loading test fixtures and creating test data.
 *
 * @since 1.0.0
 */
class FixtureLoader
{
    /**
     * Fixtures directory path
     *
     * @var string
     */
    private static $fixturesDir;

    /**
     * Initialize fixtures directory
     *
     * @return void
     */
    private static function initFixturesDir(): void
    {
        if (!self::$fixturesDir) {
            self::$fixturesDir = __DIR__;
        }
    }

    /**
     * Load JSON fixture file
     *
     * @param string $filename Fixture filename without extension
     * @return array Fixture data
     * @throws \Exception If fixture file doesn't exist or is invalid
     */
    public static function loadJsonFixture(string $filename): array
    {
        self::initFixturesDir();

        $filepath = self::$fixturesDir . '/' . $filename . '.json';

        if (!file_exists($filepath)) {
            throw new \Exception("Fixture file not found: {$filepath}");
        }

        $content = file_get_contents($filepath);

        if ($content === false) {
            throw new \Exception("Could not read fixture file: {$filepath}");
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON in fixture file: {$filepath} - " . json_last_error_msg());
        }

        return $data;
    }

    /**
     * Create test products from fixture data
     *
     * @param array $product_data Product data array (optional, loads from fixture if not provided)
     * @return array Array of created product IDs
     */
    public static function createTestProducts(array $product_data = []): array
    {
        if (empty($product_data)) {
            $product_data = self::loadJsonFixture('sample-products');
        }

        $created_products = [];

        foreach ($product_data as $product) {
            $created_product = self::createSingleProduct($product);
            if ($created_product) {
                $created_products[] = $created_product->get_id();
            }
        }

        return $created_products;
    }

    /**
     * Create a single product from data
     *
     * @param array $product_data Product data
     * @return \WC_Product|null Created product or null on failure
     */
    private static function createSingleProduct(array $product_data): ?\WC_Product
    {
        if (!class_exists('WC_Product_Simple')) {
            return null;
        }

        $product = new \WC_Product_Simple();

        // Set basic product data
        $product->set_name($product_data['name'] ?? 'Test Product');
        $product->set_slug($product_data['slug'] ?? 'test-product');
        $product->set_regular_price($product_data['regular_price'] ?? '29.99');

        if (isset($product_data['sale_price'])) {
            $product->set_sale_price($product_data['sale_price']);
        }

        $product->set_short_description($product_data['short_description'] ?? '');
        $product->set_description($product_data['description'] ?? '');
        $product->set_status($product_data['status'] ?? 'publish');
        $product->set_featured($product_data['featured'] ?? false);

        // Stock management
        if (isset($product_data['manage_stock'])) {
            $product->set_manage_stock($product_data['manage_stock']);
            if (isset($product_data['stock_quantity'])) {
                $product->set_stock_quantity($product_data['stock_quantity']);
            }
        }

        $product_id = $product->save();

        if (!$product_id) {
            return null;
        }

        // Set categories
        if (isset($product_data['categories'])) {
            self::setProductCategories($product_id, $product_data['categories']);
        }

        // Set tags
        if (isset($product_data['tags'])) {
            self::setProductTags($product_id, $product_data['tags']);
        }

        // Set attributes
        if (isset($product_data['attributes'])) {
            self::setProductAttributes($product_id, $product_data['attributes']);
        }

        return wc_get_product($product_id);
    }

    /**
     * Set product categories
     *
     * @param int $product_id Product ID
     * @param array $categories Category names
     * @return void
     */
    private static function setProductCategories(int $product_id, array $categories): void
    {
        $category_ids = [];

        foreach ($categories as $category_name) {
            $term = get_term_by('name', $category_name, 'product_cat');

            if (!$term) {
                $term_data = wp_insert_term($category_name, 'product_cat');
                if (!is_wp_error($term_data)) {
                    $category_ids[] = $term_data['term_id'];
                }
            } else {
                $category_ids[] = $term->term_id;
            }
        }

        if (!empty($category_ids)) {
            wp_set_object_terms($product_id, $category_ids, 'product_cat');
        }
    }

    /**
     * Set product tags
     *
     * @param int $product_id Product ID
     * @param array $tags Tag names
     * @return void
     */
    private static function setProductTags(int $product_id, array $tags): void
    {
        wp_set_object_terms($product_id, $tags, 'product_tag');
    }

    /**
     * Set product attributes
     *
     * @param int $product_id Product ID
     * @param array $attributes Attributes data
     * @return void
     */
    private static function setProductAttributes(int $product_id, array $attributes): void
    {
        $product_attributes = [];

        foreach ($attributes as $attribute_name => $attribute_value) {
            $attribute = new \WC_Product_Attribute();
            $attribute->set_name($attribute_name);
            $attribute->set_options(is_array($attribute_value) ? $attribute_value : [$attribute_value]);
            $attribute->set_visible(true);
            $attribute->set_variation(false);

            $product_attributes[] = $attribute;
        }

        if (!empty($product_attributes)) {
            $product = wc_get_product($product_id);
            $product->set_attributes($product_attributes);
            $product->save();
        }
    }

    /**
     * Create test users from fixture data
     *
     * @param array $user_data User data array (optional, loads from fixture if not provided)
     * @return array Array of created user IDs
     */
    public static function createTestUsers(array $user_data = []): array
    {
        if (empty($user_data)) {
            $user_data = self::loadJsonFixture('sample-users');
        }

        $created_users = [];

        foreach ($user_data as $user) {
            $user_id = self::createSingleUser($user);
            if ($user_id && !is_wp_error($user_id)) {
                $created_users[] = $user_id;
            }
        }

        return $created_users;
    }

    /**
     * Create a single user from data
     *
     * @param array $user_data User data
     * @return int|WP_Error User ID on success, WP_Error on failure
     */
    private static function createSingleUser(array $user_data)
    {
        $user_id = wp_insert_user([
            'user_login' => $user_data['user_login'],
            'user_email' => $user_data['user_email'],
            'user_pass' => $user_data['user_pass'],
            'first_name' => $user_data['first_name'] ?? '',
            'last_name' => $user_data['last_name'] ?? '',
            'role' => $user_data['role'] ?? 'customer'
        ]);

        // Add user meta
        if (!is_wp_error($user_id) && isset($user_data['meta'])) {
            foreach ($user_data['meta'] as $meta_key => $meta_value) {
                update_user_meta($user_id, $meta_key, $meta_value);
            }
        }

        return $user_id;
    }

    /**
     * Load plugin configuration from fixture
     *
     * @param string $config_type Configuration type (development_config, production_config, etc.)
     * @return array Configuration data
     */
    public static function loadPluginConfig(string $config_type = 'development_config'): array
    {
        $all_configs = self::loadJsonFixture('plugin-configurations');

        if (isset($all_configs[$config_type])) {
            return $all_configs[$config_type];
        }

        return $all_configs;
    }

    /**
     * Apply plugin configuration for testing
     *
     * @param string $config_type Configuration type
     * @return void
     */
    public static function applyPluginConfig(string $config_type = 'development_config'): void
    {
        $config = self::loadPluginConfig($config_type);

        // Apply configuration as WordPress options
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                // Handle nested configuration
                foreach ($value as $sub_key => $sub_value) {
                    update_option("woo_ai_assistant_{$key}_{$sub_key}", $sub_value);
                }
            } else {
                update_option("woo_ai_assistant_{$key}", $value);
            }
        }
    }

    /**
     * Clean up all test data
     *
     * @param array $product_ids Product IDs to clean up
     * @param array $user_ids User IDs to clean up
     * @return void
     */
    public static function cleanupTestData(array $product_ids = [], array $user_ids = []): void
    {
        // Clean up products
        foreach ($product_ids as $product_id) {
            wp_delete_post($product_id, true);
        }

        // Clean up users (but keep admin user)
        foreach ($user_ids as $user_id) {
            if ($user_id > 1) { // Don't delete admin user
                wp_delete_user($user_id);
            }
        }

        // Clean up options
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'woo_ai_assistant_test_%'");
    }

    /**
     * Create WooCommerce order for testing
     *
     * @param int $customer_id Customer user ID
     * @param array $products Array of product IDs
     * @return int Order ID
     */
    public static function createTestOrder(int $customer_id, array $products = []): int
    {
        if (!class_exists('WC_Order')) {
            return 0;
        }

        $order = new \WC_Order();
        $order->set_customer_id($customer_id);
        $order->set_status('pending');

        // Add products to order
        foreach ($products as $product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                $item = new \WC_Order_Item_Product();
                $item->set_product($product);
                $item->set_quantity(1);
                $item->set_total($product->get_price());
                $order->add_item($item);
            }
        }

        $order->calculate_totals();

        return $order->save();
    }
}
