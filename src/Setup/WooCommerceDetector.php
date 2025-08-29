<?php

/**
 * WooCommerce Settings Detector Class
 *
 * Automatically detects and extracts WooCommerce store configuration including
 * shipping zones, payment methods, tax settings, and store policies to populate
 * the knowledge base immediately upon activation.
 *
 * @package WooAiAssistant
 * @subpackage Setup
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Setup;

use WooAiAssistant\Common\Utils;
use WooAiAssistant\Common\Traits\Singleton;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WooCommerceDetector
 *
 * Detects and extracts WooCommerce store configuration for automatic
 * knowledge base population during zero-config setup.
 *
 * @since 1.0.0
 */
class WooCommerceDetector
{
    use Singleton;

    /**
     * Detected store information
     *
     * @since 1.0.0
     * @var array
     */
    private array $storeInfo = [];

    /**
     * Shipping configuration
     *
     * @since 1.0.0
     * @var array
     */
    private array $shippingConfig = [];

    /**
     * Payment configuration
     *
     * @since 1.0.0
     * @var array
     */
    private array $paymentConfig = [];

    /**
     * Tax configuration
     *
     * @since 1.0.0
     * @var array
     */
    private array $taxConfig = [];

    /**
     * Store policies
     *
     * @since 1.0.0
     * @var array
     */
    private array $policies = [];

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    private function __construct()
    {
        // Constructor will be called by singleton trait
    }

    /**
     * Extract all WooCommerce settings and store information
     *
     * This is the main method that gathers all store configuration
     * to populate the knowledge base during auto-installation.
     *
     * @since 1.0.0
     * @return array Complete store configuration
     */
    public function extractAllSettings(): array
    {
        try {
            Utils::logDebug('Starting WooCommerce settings extraction');

            // Check if WooCommerce is active
            if (!Utils::isWooCommerceActive()) {
                Utils::logDebug('WooCommerce is not active - skipping settings extraction');
                return [];
            }

            // Extract different types of settings
            $this->extractStoreInformation();
            $this->extractShippingConfiguration();
            $this->extractPaymentConfiguration();
            $this->extractTaxConfiguration();
            $this->extractStorePolicies();

            // Compile all extracted data
            $allSettings = [
                'store_info' => [
                    'title' => __('Store Information', 'woo-ai-assistant'),
                    'content' => $this->formatStoreInformation(),
                    'url' => admin_url('admin.php?page=wc-settings'),
                    'metadata' => $this->storeInfo
                ],
                'shipping' => [
                    'title' => __('Shipping Information', 'woo-ai-assistant'),
                    'content' => $this->formatShippingInformation(),
                    'url' => admin_url('admin.php?page=wc-settings&tab=shipping'),
                    'metadata' => $this->shippingConfig
                ],
                'payment' => [
                    'title' => __('Payment Methods', 'woo-ai-assistant'),
                    'content' => $this->formatPaymentInformation(),
                    'url' => admin_url('admin.php?page=wc-settings&tab=checkout'),
                    'metadata' => $this->paymentConfig
                ],
                'tax' => [
                    'title' => __('Tax Configuration', 'woo-ai-assistant'),
                    'content' => $this->formatTaxInformation(),
                    'url' => admin_url('admin.php?page=wc-settings&tab=tax'),
                    'metadata' => $this->taxConfig
                ],
                'policies' => [
                    'title' => __('Store Policies', 'woo-ai-assistant'),
                    'content' => $this->formatStorePolicies(),
                    'url' => admin_url('admin.php?page=wc-settings&tab=advanced'),
                    'metadata' => $this->policies
                ]
            ];

            Utils::logDebug('WooCommerce settings extraction completed');
            return $allSettings;
        } catch (\Exception $e) {
            Utils::logError('Failed to extract WooCommerce settings: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Extract basic store information
     *
     * @since 1.0.0
     * @return void
     */
    private function extractStoreInformation(): void
    {
        try {
            $this->storeInfo = [
                'store_name' => get_option('blogname', ''),
                'store_description' => get_option('blogdescription', ''),
                'store_url' => \home_url(),
                'admin_email' => get_option('admin_email', ''),
                'wc_version' => defined('WC_VERSION') ? WC_VERSION : 'Unknown',
                'base_location' => WC()->countries->get_base_country(),
                'base_address' => [
                    'address_1' => get_option('woocommerce_store_address'),
                    'address_2' => get_option('woocommerce_store_address_2'),
                    'city' => get_option('woocommerce_store_city'),
                    'postcode' => get_option('woocommerce_store_postcode')
                ],
                'currency' => get_woocommerce_currency(),
                'currency_symbol' => get_woocommerce_currency_symbol(),
                'weight_unit' => get_option('woocommerce_weight_unit'),
                'dimension_unit' => get_option('woocommerce_dimension_unit'),
                'timezone' => wp_timezone_string(),
                'date_format' => get_option('date_format'),
                'time_format' => get_option('time_format')
            ];

            Utils::logDebug('Store information extracted', [
                'store_name' => $this->storeInfo['store_name'],
                'currency' => $this->storeInfo['currency'],
                'base_location' => $this->storeInfo['base_location']
            ]);
        } catch (\Exception $e) {
            Utils::logError('Failed to extract store information: ' . $e->getMessage());
        }
    }

    /**
     * Extract shipping configuration
     *
     * @since 1.0.0
     * @return void
     */
    private function extractShippingConfiguration(): void
    {
        try {
            // Get shipping zones
            $shippingZones = \WC_Shipping_Zones::get_zones();
            $shippingZones[] = \WC_Shipping_Zones::get_zone(0); // Add "Rest of the World" zone

            $this->shippingConfig = [
                'enabled' => wc_shipping_enabled(),
                'calculation_method' => get_option('woocommerce_shipping_cost_requires_address'),
                'zones' => []
            ];

            foreach ($shippingZones as $zoneData) {
                if (is_array($zoneData) && isset($zoneData['zone_id'])) {
                    $zone = \WC_Shipping_Zones::get_zone($zoneData['zone_id']);
                } else {
                    $zone = $zoneData;
                }

                $zoneInfo = [
                    'id' => $zone->get_id(),
                    'name' => $zone->get_zone_name(),
                    'order' => $zone->get_zone_order(),
                    'locations' => [],
                    'methods' => []
                ];

                // Get zone locations
                $zoneLocations = $zone->get_zone_locations();
                foreach ($zoneLocations as $location) {
                    $zoneInfo['locations'][] = [
                        'code' => $location->code,
                        'type' => $location->type
                    ];
                }

                // Get shipping methods
                $shippingMethods = $zone->get_shipping_methods();
                foreach ($shippingMethods as $method) {
                    $methodInfo = [
                        'id' => $method->get_id(),
                        'method_id' => $method->get_method_id(),
                        'title' => $method->get_method_title(),
                        'enabled' => $method->is_enabled(),
                        'settings' => []
                    ];

                    // Get method settings
                    if (method_exists($method, 'get_instance_form_fields')) {
                        $formFields = $method->get_instance_form_fields();
                        foreach ($formFields as $key => $field) {
                            if (isset($method->instance_settings[$key])) {
                                $methodInfo['settings'][$key] = $method->instance_settings[$key];
                            }
                        }
                    }

                    $zoneInfo['methods'][] = $methodInfo;
                }

                $this->shippingConfig['zones'][] = $zoneInfo;
            }

            Utils::logDebug('Shipping configuration extracted', [
                'enabled' => $this->shippingConfig['enabled'],
                'zones_count' => count($this->shippingConfig['zones'])
            ]);
        } catch (\Exception $e) {
            Utils::logError('Failed to extract shipping configuration: ' . $e->getMessage());
            $this->shippingConfig = ['enabled' => false, 'zones' => []];
        }
    }

    /**
     * Extract payment gateway configuration
     *
     * @since 1.0.0
     * @return void
     */
    private function extractPaymentConfiguration(): void
    {
        try {
            $paymentGateways = WC()->payment_gateways->payment_gateways();

            $this->paymentConfig = [
                'available_gateways' => [],
                'default_gateway' => get_option('woocommerce_default_gateway'),
                'currency' => get_woocommerce_currency()
            ];

            foreach ($paymentGateways as $gatewayId => $gateway) {
                if ($gateway->is_available()) {
                    $gatewayInfo = [
                        'id' => $gatewayId,
                        'title' => $gateway->get_title(),
                        'method_title' => $gateway->get_method_title(),
                        'enabled' => $gateway->is_enabled(),
                        'description' => $gateway->get_method_description(),
                        'supports' => $gateway->supports ?? [],
                        'settings' => []
                    ];

                    // Get non-sensitive settings
                    $safeSettings = [
                        'title', 'description', 'instructions',
                        'enable_for_methods', 'enable_for_virtual'
                    ];

                    foreach ($safeSettings as $settingKey) {
                        if (isset($gateway->settings[$settingKey])) {
                            $gatewayInfo['settings'][$settingKey] = $gateway->settings[$settingKey];
                        }
                    }

                    $this->paymentConfig['available_gateways'][] = $gatewayInfo;
                }
            }

            Utils::logDebug('Payment configuration extracted', [
                'gateways_count' => count($this->paymentConfig['available_gateways']),
                'default_gateway' => $this->paymentConfig['default_gateway']
            ]);
        } catch (\Exception $e) {
            Utils::logError('Failed to extract payment configuration: ' . $e->getMessage());
            $this->paymentConfig = ['available_gateways' => [], 'default_gateway' => ''];
        }
    }

    /**
     * Extract tax configuration
     *
     * @since 1.0.0
     * @return void
     */
    private function extractTaxConfiguration(): void
    {
        try {
            $this->taxConfig = [
                'enabled' => wc_tax_enabled(),
                'prices_include_tax' => wc_prices_include_tax(),
                'calculate_tax_based_on' => get_option('woocommerce_tax_based_on'),
                'shipping_tax_class' => get_option('woocommerce_shipping_tax_class'),
                'tax_round_at_subtotal' => get_option('woocommerce_tax_round_at_subtotal'),
                'tax_display_shop' => get_option('woocommerce_tax_display_shop'),
                'tax_display_cart' => get_option('woocommerce_tax_display_cart'),
                'tax_classes' => []
            ];

            if (wc_tax_enabled()) {
                // Get tax classes
                $taxClasses = WC_Tax::get_tax_classes();
                $standardClass = [
                    'slug' => '',
                    'name' => __('Standard rate', 'woocommerce')
                ];
                $this->taxConfig['tax_classes'][] = $standardClass;

                foreach ($taxClasses as $class) {
                    $this->taxConfig['tax_classes'][] = [
                        'slug' => sanitize_title($class),
                        'name' => $class
                    ];
                }

                // Get tax rates for standard class (most common)
                $taxRates = WC_Tax::get_rates_for_tax_class('');
                $this->taxConfig['standard_rates'] = [];

                foreach ($taxRates as $rate) {
                    $this->taxConfig['standard_rates'][] = [
                        'country' => $rate->tax_rate_country,
                        'state' => $rate->tax_rate_state,
                        'rate' => $rate->tax_rate,
                        'name' => $rate->tax_rate_name,
                        'priority' => $rate->tax_rate_priority,
                        'compound' => $rate->tax_rate_compound,
                        'shipping' => $rate->tax_rate_shipping
                    ];
                }
            }

            Utils::logDebug('Tax configuration extracted', [
                'enabled' => $this->taxConfig['enabled'],
                'classes_count' => count($this->taxConfig['tax_classes']),
                'rates_count' => count($this->taxConfig['standard_rates'] ?? [])
            ]);
        } catch (\Exception $e) {
            Utils::logError('Failed to extract tax configuration: ' . $e->getMessage());
            $this->taxConfig = ['enabled' => false, 'tax_classes' => []];
        }
    }

    /**
     * Extract store policies and important pages
     *
     * @since 1.0.0
     * @return void
     */
    private function extractStorePolicies(): void
    {
        try {
            $this->policies = [
                'terms_page_id' => wc_terms_and_conditions_page_id(),
                'privacy_page_id' => wc_privacy_policy_page_id(),
                'return_policy_page_id' => get_option('woocommerce_return_policy_page_id'),
                'shipping_policy_page_id' => get_option('woocommerce_shipping_policy_page_id'),
                'pages' => []
            ];

            // Extract content from policy pages
            $policyPages = [
                'terms' => $this->policies['terms_page_id'],
                'privacy' => $this->policies['privacy_page_id'],
                'returns' => $this->policies['return_policy_page_id'],
                'shipping' => $this->policies['shipping_policy_page_id']
            ];

            foreach ($policyPages as $policyType => $pageId) {
                if ($pageId) {
                    $page = get_post($pageId);
                    if ($page && $page->post_status === 'publish') {
                        $this->policies['pages'][$policyType] = [
                            'id' => $pageId,
                            'title' => $page->post_title,
                            'content' => wp_strip_all_tags($page->post_content),
                            'url' => get_permalink($pageId),
                            'last_modified' => $page->post_modified
                        ];
                    }
                }
            }

            // Add WooCommerce-specific settings
            $this->policies['account_settings'] = [
                'allow_registration' => get_option('woocommerce_enable_myaccount_registration'),
                'generate_username' => get_option('woocommerce_registration_generate_username'),
                'generate_password' => get_option('woocommerce_registration_generate_password')
            ];

            Utils::logDebug('Store policies extracted', [
                'policy_pages_count' => count($this->policies['pages']),
                'account_registration' => $this->policies['account_settings']['allow_registration']
            ]);
        } catch (\Exception $e) {
            Utils::logError('Failed to extract store policies: ' . $e->getMessage());
            $this->policies = ['pages' => [], 'account_settings' => []];
        }
    }

    /**
     * Format store information for knowledge base
     *
     * @since 1.0.0
     * @return string Formatted store information
     */
    private function formatStoreInformation(): string
    {
        if (empty($this->storeInfo)) {
            return '';
        }

        $content = [];

        // Basic store info
        if (!empty($this->storeInfo['store_name'])) {
            $content[] = "Store Name: {$this->storeInfo['store_name']}";
        }

        if (!empty($this->storeInfo['store_description'])) {
            $content[] = "Description: {$this->storeInfo['store_description']}";
        }

        // Location and currency
        if (!empty($this->storeInfo['base_location'])) {
            $countryName = WC()->countries->countries[$this->storeInfo['base_location']] ?? $this->storeInfo['base_location'];
            $content[] = "Store Location: {$countryName}";
        }

        if (!empty($this->storeInfo['currency'])) {
            $content[] = "Currency: {$this->storeInfo['currency']} ({$this->storeInfo['currency_symbol']})";
        }

        // Address
        $addressParts = array_filter([
            $this->storeInfo['base_address']['address_1'],
            $this->storeInfo['base_address']['address_2'],
            $this->storeInfo['base_address']['city'],
            $this->storeInfo['base_address']['postcode']
        ]);

        if (!empty($addressParts)) {
            $content[] = "Store Address: " . implode(', ', $addressParts);
        }

        // Units
        if (!empty($this->storeInfo['weight_unit'])) {
            $content[] = "Weight Unit: {$this->storeInfo['weight_unit']}";
        }

        if (!empty($this->storeInfo['dimension_unit'])) {
            $content[] = "Dimension Unit: {$this->storeInfo['dimension_unit']}";
        }

        return implode("\n", $content);
    }

    /**
     * Format shipping information for knowledge base
     *
     * @since 1.0.0
     * @return string Formatted shipping information
     */
    private function formatShippingInformation(): string
    {
        if (empty($this->shippingConfig) || !$this->shippingConfig['enabled']) {
            return "Shipping is currently disabled for this store.";
        }

        $content = ["Shipping is enabled for this store.\n"];

        foreach ($this->shippingConfig['zones'] as $zone) {
            $zoneContent = ["Shipping Zone: {$zone['name']}"];

            // Zone locations
            if (!empty($zone['locations'])) {
                $locations = [];
                foreach ($zone['locations'] as $location) {
                    if ($location['type'] === 'country') {
                        $countryName = WC()->countries->countries[$location['code']] ?? $location['code'];
                        $locations[] = $countryName;
                    } else {
                        $locations[] = $location['code'];
                    }
                }
                if (!empty($locations)) {
                    $zoneContent[] = "  Locations: " . implode(', ', $locations);
                }
            }

            // Shipping methods
            if (!empty($zone['methods'])) {
                $zoneContent[] = "  Available shipping methods:";
                foreach ($zone['methods'] as $method) {
                    if ($method['enabled']) {
                        $methodLine = "    - {$method['title']}";
                        if (!empty($method['settings']['cost'])) {
                            $methodLine .= " (Cost: {$method['settings']['cost']})";
                        }
                        $zoneContent[] = $methodLine;
                    }
                }
            }

            $content[] = implode("\n", $zoneContent);
        }

        return implode("\n\n", $content);
    }

    /**
     * Format payment information for knowledge base
     *
     * @since 1.0.0
     * @return string Formatted payment information
     */
    private function formatPaymentInformation(): string
    {
        if (empty($this->paymentConfig['available_gateways'])) {
            return "No payment methods are currently configured.";
        }

        $content = ["The following payment methods are available:\n"];

        foreach ($this->paymentConfig['available_gateways'] as $gateway) {
            if ($gateway['enabled']) {
                $gatewayInfo = [];
                $gatewayInfo[] = "Payment Method: {$gateway['title']}";

                if (!empty($gateway['description'])) {
                    $gatewayInfo[] = "  Description: {$gateway['description']}";
                }

                if (!empty($gateway['settings']['instructions'])) {
                    $gatewayInfo[] = "  Instructions: {$gateway['settings']['instructions']}";
                }

                $content[] = implode("\n", $gatewayInfo);
            }
        }

        if (!empty($this->paymentConfig['default_gateway'])) {
            $content[] = "\nDefault payment method: {$this->paymentConfig['default_gateway']}";
        }

        return implode("\n\n", $content);
    }

    /**
     * Format tax information for knowledge base
     *
     * @since 1.0.0
     * @return string Formatted tax information
     */
    private function formatTaxInformation(): string
    {
        if (!$this->taxConfig['enabled']) {
            return "Tax calculation is disabled for this store.";
        }

        $content = [];
        $content[] = "Tax calculation is enabled for this store.";

        if ($this->taxConfig['prices_include_tax']) {
            $content[] = "Product prices include tax.";
        } else {
            $content[] = "Product prices exclude tax.";
        }

        $taxBasedOn = $this->taxConfig['calculate_tax_based_on'];
        switch ($taxBasedOn) {
            case 'shipping':
                $content[] = "Tax is calculated based on customer shipping address.";
                break;
            case 'billing':
                $content[] = "Tax is calculated based on customer billing address.";
                break;
            case 'base':
                $content[] = "Tax is calculated based on store base address.";
                break;
        }

        if (!empty($this->taxConfig['standard_rates'])) {
            $content[] = "\nStandard tax rates:";
            foreach ($this->taxConfig['standard_rates'] as $rate) {
                $rateInfo = "- {$rate['name']}: {$rate['rate']}%";
                if (!empty($rate['country'])) {
                    $countryName = WC()->countries->countries[$rate['country']] ?? $rate['country'];
                    $rateInfo .= " ({$countryName}";
                    if (!empty($rate['state'])) {
                        $rateInfo .= ", {$rate['state']}";
                    }
                    $rateInfo .= ")";
                }
                $content[] = $rateInfo;
            }
        }

        return implode("\n", $content);
    }

    /**
     * Format store policies for knowledge base
     *
     * @since 1.0.0
     * @return string Formatted store policies
     */
    private function formatStorePolicies(): string
    {
        $content = [];

        if (!empty($this->policies['pages'])) {
            $content[] = "Store policies and important information:\n";

            foreach ($this->policies['pages'] as $policyType => $pageData) {
                $policyTitle = ucfirst(str_replace('_', ' ', $policyType)) . ' Policy';
                $content[] = "{$policyTitle}:";
                $content[] = "  Page: {$pageData['title']}";
                $content[] = "  URL: {$pageData['url']}";

                // Include a brief excerpt of the policy content
                if (!empty($pageData['content'])) {
                    $excerpt = wp_trim_words($pageData['content'], 50, '...');
                    $content[] = "  Summary: {$excerpt}";
                }
                $content[] = "";
            }
        }

        // Account settings
        if (!empty($this->policies['account_settings'])) {
            $content[] = "Customer Account Information:";

            if ($this->policies['account_settings']['allow_registration']) {
                $content[] = "- Customer registration is enabled";

                if ($this->policies['account_settings']['generate_username']) {
                    $content[] = "- Usernames are automatically generated";
                }

                if ($this->policies['account_settings']['generate_password']) {
                    $content[] = "- Passwords are automatically generated";
                }
            } else {
                $content[] = "- Customer registration is disabled";
            }
        }

        return implode("\n", $content) ?: "No store policies have been configured.";
    }

    /**
     * Get specific setting type
     *
     * @since 1.0.0
     * @param string $settingType Type of setting to retrieve
     * @return array|null Setting data or null if not found
     */
    public function getSetting(string $settingType): ?array
    {
        $allSettings = $this->extractAllSettings();
        return $allSettings[$settingType] ?? null;
    }

    /**
     * Get store information only
     *
     * @since 1.0.0
     * @return array Store information data
     */
    public function getStoreInformation(): array
    {
        $this->extractStoreInformation();
        return $this->storeInfo;
    }

    /**
     * Get shipping configuration only
     *
     * @since 1.0.0
     * @return array Shipping configuration data
     */
    public function getShippingConfiguration(): array
    {
        $this->extractShippingConfiguration();
        return $this->shippingConfig;
    }

    /**
     * Get payment configuration only
     *
     * @since 1.0.0
     * @return array Payment configuration data
     */
    public function getPaymentConfiguration(): array
    {
        $this->extractPaymentConfiguration();
        return $this->paymentConfig;
    }

    /**
     * Get tax configuration only
     *
     * @since 1.0.0
     * @return array Tax configuration data
     */
    public function getTaxConfiguration(): array
    {
        $this->extractTaxConfiguration();
        return $this->taxConfig;
    }

    /**
     * Get store policies only
     *
     * @since 1.0.0
     * @return array Store policies data
     */
    public function getStorePolicies(): array
    {
        $this->extractStorePolicies();
        return $this->policies;
    }

    /**
     * Check if WooCommerce store is properly configured
     *
     * @since 1.0.0
     * @return array Configuration status with recommendations
     */
    public function getConfigurationStatus(): array
    {
        $this->extractAllSettings();

        $status = [
            'overall_status' => 'complete',
            'checks' => [],
            'recommendations' => []
        ];

        // Check store information
        if (empty($this->storeInfo['store_name'])) {
            $status['checks']['store_name'] = 'missing';
            $status['recommendations'][] = 'Set a store name in WordPress General Settings';
            $status['overall_status'] = 'incomplete';
        } else {
            $status['checks']['store_name'] = 'configured';
        }

        // Check address
        $addressComplete = !empty($this->storeInfo['base_address']['address_1']) &&
                          !empty($this->storeInfo['base_address']['city']);
        if (!$addressComplete) {
            $status['checks']['address'] = 'incomplete';
            $status['recommendations'][] = 'Complete store address in WooCommerce > Settings > General';
            $status['overall_status'] = 'incomplete';
        } else {
            $status['checks']['address'] = 'configured';
        }

        // Check shipping
        if (!$this->shippingConfig['enabled'] || empty($this->shippingConfig['zones'])) {
            $status['checks']['shipping'] = 'not_configured';
            $status['recommendations'][] = 'Configure shipping zones and methods';
            if ($status['overall_status'] === 'complete') {
                $status['overall_status'] = 'basic';
            }
        } else {
            $status['checks']['shipping'] = 'configured';
        }

        // Check payment methods
        $enabledGateways = array_filter($this->paymentConfig['available_gateways'], function ($gateway) {
            return $gateway['enabled'];
        });

        if (empty($enabledGateways)) {
            $status['checks']['payment'] = 'not_configured';
            $status['recommendations'][] = 'Enable at least one payment method';
            $status['overall_status'] = 'incomplete';
        } else {
            $status['checks']['payment'] = 'configured';
        }

        return $status;
    }

    /**
     * Get detection statistics
     *
     * @since 1.0.0
     * @return array Statistics about detected settings
     */
    public function getDetectionStatistics(): array
    {
        $this->extractAllSettings();

        return [
            'store_configured' => !empty($this->storeInfo['store_name']),
            'shipping_zones_count' => count($this->shippingConfig['zones'] ?? []),
            'payment_methods_count' => count($this->paymentConfig['available_gateways'] ?? []),
            'tax_enabled' => $this->taxConfig['enabled'] ?? false,
            'policy_pages_count' => count($this->policies['pages'] ?? []),
            'last_detection' => current_time('mysql')
        ];
    }
}
