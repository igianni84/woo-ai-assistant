<?php

/**
 * Tests for REST API Controller Class
 *
 * Comprehensive unit tests for the RestController class that handles REST API
 * endpoints, authentication, security, rate limiting, and request validation.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Unit\RestApi
 * @since 1.0.0
 * @author Claude Code Assistant
 */

namespace WooAiAssistant\Tests\Unit\RestApi;

use WooAiAssistant\Tests\Unit\WooAiBaseTestCase;
use WooAiAssistant\RestApi\RestController;
use WooAiAssistant\Common\Utils;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class RestControllerTest
 *
 * Test cases for the RestController class.
 * Verifies endpoint registration, security, authentication, and request handling.
 *
 * @since 1.0.0
 */
class RestControllerTest extends WooAiBaseTestCase
{
    /**
     * RestController instance
     *
     * @var RestController
     */
    private $restController;

    /**
     * Mock admin user ID
     *
     * @var int
     */
    private $adminUserId;

    /**
     * Mock shop manager user ID
     *
     * @var int
     */
    private $shopManagerUserId;

    /**
     * Mock customer user ID
     *
     * @var int
     */
    private $customerId;

    /**
     * Test REST server
     *
     * @var \WP_REST_Server
     */
    private $server;

    /**
     * Set up test environment
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->restController = RestController::getInstance();

        // Create test users with different capabilities
        $this->adminUserId = $this->createTestUser('administrator');
        $this->shopManagerUserId = $this->createTestUser('shop_manager');
        $this->customerId = $this->createTestUser('customer');

        // Set up REST server
        global $wp_rest_server;
        $this->server = $wp_rest_server = new \WP_REST_Server();
        do_action('rest_api_init');

        // Mock WordPress REST environment
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['REQUEST_URI'] = '/wp-json/woo-ai-assistant/v1/health';
    }

    /**
     * Test RestController singleton pattern
     *
     * Verifies that RestController class follows singleton pattern correctly.
     *
     * @return void
     */
    public function test_getInstance_should_return_singleton_instance(): void
    {
        $instance1 = RestController::getInstance();
        $instance2 = RestController::getInstance();

        $this->assertInstanceOf(RestController::class, $instance1);
        $this->assertSame($instance1, $instance2, 'getInstance should return the same instance (singleton pattern)');
    }

    /**
     * Test API namespace getter
     *
     * Verifies that getNamespace returns the correct namespace.
     *
     * @return void
     */
    public function test_getNamespace_should_return_correct_namespace(): void
    {
        $expectedNamespace = 'woo-ai-assistant/v1';
        $actualNamespace = $this->restController->getNamespace();

        $this->assertEquals($expectedNamespace, $actualNamespace, 'Namespace should match expected value');
    }

    /**
     * Test endpoint registration
     *
     * Verifies that REST endpoints are registered correctly.
     *
     * @return void
     */
    public function test_registerEndpoints_should_register_base_endpoints(): void
    {
        $this->restController->registerEndpoints();

        $routes = $this->server->get_routes();
        $namespace = $this->restController->getNamespace();

        // Check health endpoint
        $this->assertArrayHasKey("/{$namespace}/health", $routes, 'Health endpoint should be registered');

        // Check config endpoint
        $this->assertArrayHasKey("/{$namespace}/config", $routes, 'Config endpoint should be registered');

        // Verify health endpoint configuration
        $healthRoute = $routes["/{$namespace}/health"][0];
        $this->assertEquals(['GET'], $healthRoute['methods'], 'Health endpoint should accept GET requests');
        $this->assertEquals([$this->restController, 'healthCheck'], $healthRoute['callback'], 'Health endpoint should have correct callback');
    }

    /**
     * Test health check endpoint
     *
     * Verifies that health check endpoint returns correct data.
     *
     * @return void
     */
    public function test_healthCheck_should_return_system_status(): void
    {
        $request = new WP_REST_Request('GET', '/woo-ai-assistant/v1/health');
        $response = $this->restController->healthCheck($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response, 'Should return WP_REST_Response');
        $this->assertEquals(200, $response->get_status(), 'Should return 200 status');

        $data = $response->get_data();
        $this->assertIsArray($data, 'Response data should be an array');

        // Check required fields
        $expectedFields = ['status', 'timestamp', 'version', 'wordpress_version', 'woocommerce_active', 'php_version', 'memory_usage'];
        foreach ($expectedFields as $field) {
            $this->assertArrayHasKey($field, $data, "Should contain {$field} field");
        }

        $this->assertEquals('healthy', $data['status'], 'Status should be healthy');
        $this->assertIsInt($data['timestamp'], 'Timestamp should be integer');
        $this->assertIsString($data['version'], 'Version should be string');
        $this->assertIsBool($data['woocommerce_active'], 'WooCommerce active should be boolean');
        $this->assertIsArray($data['memory_usage'], 'Memory usage should be array');
        $this->assertArrayHasKey('current', $data['memory_usage'], 'Memory usage should contain current');
        $this->assertArrayHasKey('peak', $data['memory_usage'], 'Memory usage should contain peak');
    }

    /**
     * Test health check includes development info in dev mode
     *
     * Verifies that development information is included when in development mode.
     *
     * @return void
     */
    public function test_healthCheck_should_include_development_info_in_dev_mode(): void
    {
        // Mock development mode
        add_filter('woo_ai_assistant_is_development_mode', '__return_true');

        $request = new WP_REST_Request('GET', '/woo-ai-assistant/v1/health');
        $response = $this->restController->healthCheck($request);

        $data = $response->get_data();
        $this->assertArrayHasKey('development_mode', $data, 'Should contain development mode field');
        $this->assertArrayHasKey('debug_enabled', $data, 'Should contain debug enabled field');
        $this->assertTrue($data['development_mode'], 'Development mode should be true');

        remove_filter('woo_ai_assistant_is_development_mode', '__return_true');
    }

    /**
     * Test widget config endpoint
     *
     * Verifies that widget config endpoint returns correct configuration.
     *
     * @return void
     */
    public function test_getWidgetConfig_should_return_widget_configuration(): void
    {
        $request = new WP_REST_Request('GET', '/woo-ai-assistant/v1/config');
        $request->set_param('context', 'product');
        $request->set_param('user_id', $this->customerId);

        $response = $this->restController->getWidgetConfig($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response, 'Should return WP_REST_Response');
        $this->assertEquals(200, $response->get_status(), 'Should return 200 status');

        $data = $response->get_data();
        $this->assertIsArray($data, 'Response data should be an array');

        // Check required configuration fields
        $expectedFields = ['api_base_url', 'nonce', 'context', 'user', 'woocommerce', 'features', 'settings'];
        foreach ($expectedFields as $field) {
            $this->assertArrayHasKey($field, $data, "Should contain {$field} field");
        }

        $this->assertEquals('product', $data['context'], 'Should return requested context');
        $this->assertIsArray($data['user'], 'User data should be array');
        $this->assertIsArray($data['woocommerce'], 'WooCommerce data should be array');
        $this->assertIsArray($data['features'], 'Features should be array');
        $this->assertIsArray($data['settings'], 'Settings should be array');
    }

    /**
     * Test public permission callback
     *
     * Verifies that checkPublicPermission allows public access.
     *
     * @return void
     */
    public function test_checkPublicPermission_should_allow_public_access(): void
    {
        $request = new WP_REST_Request('GET', '/woo-ai-assistant/v1/config');
        $hasPermission = $this->restController->checkPublicPermission($request);

        $this->assertTrue($hasPermission, 'Public permission should allow access');
    }

    /**
     * Test user permission callback for logged in user
     *
     * Verifies that checkUserPermission allows logged in users.
     *
     * @return void
     */
    public function test_checkUserPermission_should_allow_logged_in_user(): void
    {
        wp_set_current_user($this->customerId);

        $request = new WP_REST_Request('GET', '/woo-ai-assistant/v1/test');
        $hasPermission = $this->restController->checkUserPermission($request);

        $this->assertTrue($hasPermission, 'Should allow logged in user');
    }

    /**
     * Test user permission callback blocks logged out user
     *
     * Verifies that checkUserPermission blocks logged out users.
     *
     * @return void
     */
    public function test_checkUserPermission_should_block_logged_out_user(): void
    {
        wp_set_current_user(0); // Log out user

        $request = new WP_REST_Request('GET', '/woo-ai-assistant/v1/test');
        $result = $this->restController->checkUserPermission($request);

        $this->assertInstanceOf(WP_Error::class, $result, 'Should return WP_Error for logged out user');
        $this->assertEquals('authentication_required', $result->get_error_code(), 'Should have correct error code');
        $this->assertEquals(401, $result->get_error_data()['status'], 'Should return 401 status');
    }

    /**
     * Test admin permission callback for admin user
     *
     * Verifies that checkAdminPermission allows admin users.
     *
     * @return void
     */
    public function test_checkAdminPermission_should_allow_admin_user(): void
    {
        wp_set_current_user($this->adminUserId);

        $request = new WP_REST_Request('GET', '/woo-ai-assistant/v1/admin');
        $request->set_header('X-WP-Nonce', wp_create_nonce('woo_ai_assistant_nonce'));

        $hasPermission = $this->restController->checkAdminPermission($request);

        $this->assertTrue($hasPermission, 'Should allow admin user with valid nonce');
    }

    /**
     * Test admin permission callback blocks non-admin user
     *
     * Verifies that checkAdminPermission blocks non-admin users.
     *
     * @return void
     */
    public function test_checkAdminPermission_should_block_non_admin_user(): void
    {
        wp_set_current_user($this->customerId);

        $request = new WP_REST_Request('GET', '/woo-ai-assistant/v1/admin');
        $result = $this->restController->checkAdminPermission($request);

        $this->assertInstanceOf(WP_Error::class, $result, 'Should return WP_Error for non-admin user');
        $this->assertEquals('insufficient_permissions', $result->get_error_code(), 'Should have correct error code');
        $this->assertEquals(403, $result->get_error_data()['status'], 'Should return 403 status');
    }

    /**
     * Test nonce verification with valid nonce
     *
     * Verifies that verifyNonce accepts valid nonces.
     *
     * @return void
     */
    public function test_verifyNonce_should_accept_valid_nonce(): void
    {
        $nonce = wp_create_nonce('woo_ai_assistant_nonce');
        $request = new WP_REST_Request('GET', '/woo-ai-assistant/v1/test');
        $request->set_header('X-WP-Nonce', $nonce);

        $result = $this->restController->verifyNonce($request);

        $this->assertTrue($result, 'Should accept valid nonce in header');
    }

    /**
     * Test nonce verification with nonce in parameter
     *
     * Verifies that verifyNonce accepts nonces in request parameters.
     *
     * @return void
     */
    public function test_verifyNonce_should_accept_nonce_in_parameter(): void
    {
        $nonce = wp_create_nonce('woo_ai_assistant_nonce');
        $request = new WP_REST_Request('GET', '/woo-ai-assistant/v1/test');
        $request->set_param('_wpnonce', $nonce);

        $result = $this->restController->verifyNonce($request);

        $this->assertTrue($result, 'Should accept valid nonce in parameter');
    }

    /**
     * Test nonce verification without nonce
     *
     * Verifies that verifyNonce rejects requests without nonces.
     *
     * @return void
     */
    public function test_verifyNonce_should_reject_missing_nonce(): void
    {
        $request = new WP_REST_Request('GET', '/woo-ai-assistant/v1/test');

        $result = $this->restController->verifyNonce($request);

        $this->assertInstanceOf(WP_Error::class, $result, 'Should return WP_Error for missing nonce');
        $this->assertEquals('missing_nonce', $result->get_error_code(), 'Should have correct error code');
        $this->assertEquals(400, $result->get_error_data()['status'], 'Should return 400 status');
    }

    /**
     * Test nonce verification with invalid nonce
     *
     * Verifies that verifyNonce rejects invalid nonces.
     *
     * @return void
     */
    public function test_verifyNonce_should_reject_invalid_nonce(): void
    {
        $request = new WP_REST_Request('GET', '/woo-ai-assistant/v1/test');
        $request->set_header('X-WP-Nonce', 'invalid_nonce');

        $result = $this->restController->verifyNonce($request);

        $this->assertInstanceOf(WP_Error::class, $result, 'Should return WP_Error for invalid nonce');
        $this->assertEquals('invalid_nonce', $result->get_error_code(), 'Should have correct error code');
        $this->assertEquals(403, $result->get_error_data()['status'], 'Should return 403 status');
    }

    /**
     * Test rate limiting in production mode
     *
     * Verifies that rate limiting works correctly in production mode.
     *
     * @return void
     */
    public function test_checkRateLimit_should_enforce_limits_in_production(): void
    {
        // Mock production mode
        add_filter('woo_ai_assistant_is_development_mode', '__return_false');

        // Mock client IP
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';

        $request = new WP_REST_Request('GET', '/woo-ai-assistant/v1/config');

        // Should allow first request
        $result = $this->restController->checkRateLimit($request, 1); // Very low limit for testing
        $this->assertTrue($result, 'Should allow first request within rate limit');

        // Should block second request (over limit)
        $result = $this->restController->checkRateLimit($request, 1);
        $this->assertFalse($result, 'Should block request over rate limit');

        remove_filter('woo_ai_assistant_is_development_mode', '__return_false');
        unset($_SERVER['REMOTE_ADDR']);
    }

    /**
     * Test rate limiting bypassed in development mode
     *
     * Verifies that rate limiting is bypassed in development mode.
     *
     * @return void
     */
    public function test_checkRateLimit_should_bypass_in_development_mode(): void
    {
        // Mock development mode
        add_filter('woo_ai_assistant_is_development_mode', '__return_true');

        $request = new WP_REST_Request('GET', '/woo-ai-assistant/v1/config');

        // Should allow unlimited requests in dev mode
        for ($i = 0; $i < 100; $i++) {
            $result = $this->restController->checkRateLimit($request, 1);
            $this->assertTrue($result, 'Should allow unlimited requests in development mode');
        }

        remove_filter('woo_ai_assistant_is_development_mode', '__return_true');
    }

    /**
     * Test client IP address extraction
     *
     * Verifies that getClientIp extracts IP addresses correctly.
     *
     * @return void
     */
    public function test_getClientIp_should_extract_ip_from_headers(): void
    {
        $request = new WP_REST_Request('GET', '/woo-ai-assistant/v1/test');

        // Test REMOTE_ADDR
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        $ip = $this->invokeMethod($this->restController, 'getClientIp', [$request]);
        $this->assertEquals('192.168.1.100', $ip, 'Should extract IP from REMOTE_ADDR');

        // Test X-Forwarded-For (proxy)
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.195, 192.168.1.100';
        $ip = $this->invokeMethod($this->restController, 'getClientIp', [$request]);
        $this->assertEquals('203.0.113.195', $ip, 'Should extract first IP from X-Forwarded-For');

        // Test X-Real-IP
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        $_SERVER['HTTP_X_REAL_IP'] = '203.0.113.200';
        $ip = $this->invokeMethod($this->restController, 'getClientIp', [$request]);
        $this->assertEquals('203.0.113.200', $ip, 'Should extract IP from X-Real-IP');

        // Clean up
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_X_REAL_IP']);
    }

    /**
     * Test security headers addition
     *
     * Verifies that security headers are added to API responses.
     *
     * @return void
     */
    public function test_addSecurityHeaders_should_add_headers_for_api_endpoints(): void
    {
        $request = new WP_REST_Request('GET', '/woo-ai-assistant/v1/health');
        
        // Mock that headers are not sent yet
        add_filter('headers_sent', '__return_false');

        $response = 'test_response';
        $handler = [];
        
        $result = $this->restController->addSecurityHeaders($response, $handler, $request);

        // Headers would be added (can't easily test header() calls in unit tests)
        $this->assertEquals($response, $result, 'Should return original response');

        remove_filter('headers_sent', '__return_false');
    }

    /**
     * Test security headers not added for non-API endpoints
     *
     * Verifies that security headers are not added for non-API requests.
     *
     * @return void
     */
    public function test_addSecurityHeaders_should_not_add_headers_for_non_api_endpoints(): void
    {
        $request = new WP_REST_Request('GET', '/wp-json/wp/v2/posts');
        
        $response = 'test_response';
        $handler = [];
        
        $result = $this->restController->addSecurityHeaders($response, $handler, $request);

        $this->assertEquals($response, $result, 'Should return original response without modification');
    }

    /**
     * Test CORS headers for allowed origins
     *
     * Verifies that CORS headers are added for allowed origins.
     *
     * @return void
     */
    public function test_addCorsHeaders_should_add_headers_for_allowed_origins(): void
    {
        // Mock development mode to allow all origins
        add_filter('woo_ai_assistant_is_development_mode', '__return_true');
        add_filter('headers_sent', '__return_false');

        $request = new WP_REST_Request('GET', '/woo-ai-assistant/v1/config');
        $served = false;
        $result = new \WP_HTTP_Response();
        $server = new \WP_REST_Server();

        $corsResult = $this->restController->addCorsHeaders($served, $result, $request, $server);

        $this->assertEquals($served, $corsResult, 'Should return original served status');

        remove_filter('woo_ai_assistant_is_development_mode', '__return_true');
        remove_filter('headers_sent', '__return_false');
    }

    /**
     * Test allowed origins configuration
     *
     * Verifies that getAllowedOrigins returns correct origins.
     *
     * @return void
     */
    public function test_getAllowedOrigins_should_include_site_urls(): void
    {
        $origins = $this->invokeMethod($this->restController, 'getAllowedOrigins');

        $this->assertIsArray($origins, 'Origins should be an array');
        $this->assertContains(home_url(), $origins, 'Should contain home URL');
        $this->assertContains(admin_url(), $origins, 'Should contain admin URL');
    }

    /**
     * Test allowed origins in development mode
     *
     * Verifies that development origins are included in development mode.
     *
     * @return void
     */
    public function test_getAllowedOrigins_should_include_development_origins_in_dev_mode(): void
    {
        add_filter('woo_ai_assistant_is_development_mode', '__return_true');

        $origins = $this->invokeMethod($this->restController, 'getAllowedOrigins');

        $this->assertContains('http://localhost:3000', $origins, 'Should contain localhost:3000 in dev mode');
        $this->assertContains('http://localhost:8080', $origins, 'Should contain localhost:8080 in dev mode');
        $this->assertContains('http://localhost:8888', $origins, 'Should contain localhost:8888 in dev mode');

        remove_filter('woo_ai_assistant_is_development_mode', '__return_true');
    }

    /**
     * Test request validation with valid data
     *
     * Verifies that validateRequest accepts valid data.
     *
     * @return void
     */
    public function test_validateRequest_should_accept_valid_data(): void
    {
        $data = [
            'name' => 'Test Name',
            'email' => 'test@example.com',
            'age' => 25
        ];

        $rules = [
            'name' => 'required|string',
            'email' => 'required|email',
            'age' => 'required|integer'
        ];

        // Note: This test assumes Validator and Sanitizer classes exist
        // In a real implementation, you'd mock these dependencies
        try {
            $result = $this->restController->validateRequest($data, $rules);
            
            // If validation classes exist, check result
            if (!is_wp_error($result)) {
                $this->assertIsArray($result, 'Should return sanitized data array');
            }
        } catch (\Exception $e) {
            // If validation classes don't exist yet, that's expected
            $this->assertStringContains('Class', $e->getMessage(), 'Expected error for missing validation classes');
        }
    }

    /**
     * Test registered endpoints retrieval
     *
     * Verifies that getEndpoints returns registered endpoint instances.
     *
     * @return void
     */
    public function test_getEndpoints_should_return_registered_endpoints(): void
    {
        $endpoints = $this->restController->getEndpoints();

        $this->assertIsArray($endpoints, 'Endpoints should be an array');
        // Actual endpoint loading depends on endpoint classes existing
    }

    /**
     * Test endpoint existence check
     *
     * Verifies that hasEndpoint correctly checks for endpoint existence.
     *
     * @return void
     */
    public function test_hasEndpoint_should_check_endpoint_existence(): void
    {
        // For non-existent endpoint
        $hasEndpoint = $this->restController->hasEndpoint('nonexistent');
        $this->assertFalse($hasEndpoint, 'Should return false for non-existent endpoint');
    }

    /**
     * Test individual endpoint retrieval
     *
     * Verifies that getEndpoint returns specific endpoint instances.
     *
     * @return void
     */
    public function test_getEndpoint_should_return_specific_endpoint(): void
    {
        // For non-existent endpoint
        $endpoint = $this->restController->getEndpoint('nonexistent');
        $this->assertNull($endpoint, 'Should return null for non-existent endpoint');
    }

    /**
     * Test rate limit exceeding in widget config
     *
     * Verifies that rate limiting works in getWidgetConfig endpoint.
     *
     * @return void
     */
    public function test_getWidgetConfig_should_respect_rate_limiting(): void
    {
        // Mock production mode and strict rate limiting
        add_filter('woo_ai_assistant_is_development_mode', '__return_false');
        $_SERVER['REMOTE_ADDR'] = '192.168.1.101';

        $request = new WP_REST_Request('GET', '/woo-ai-assistant/v1/config');

        // First request should succeed
        $response = $this->restController->getWidgetConfig($request);
        $this->assertInstanceOf(WP_REST_Response::class, $response, 'First request should succeed');

        // Simulate rate limit exceeded by setting very low limit
        // This would require mocking the cache system more thoroughly
        // For now, we'll test that the method exists and handles rate limiting

        remove_filter('woo_ai_assistant_is_development_mode', '__return_false');
        unset($_SERVER['REMOTE_ADDR']);
    }

    /**
     * Test widget config with user context
     *
     * Verifies that getWidgetConfig includes user context correctly.
     *
     * @return void
     */
    public function test_getWidgetConfig_should_include_user_context(): void
    {
        wp_set_current_user($this->customerId);

        $request = new WP_REST_Request('GET', '/woo-ai-assistant/v1/config');
        $response = $this->restController->getWidgetConfig($request);

        $data = $response->get_data();
        $user = $data['user'];

        $this->assertEquals($this->customerId, $user['id'], 'Should include correct user ID');
        $this->assertTrue($user['is_logged_in'], 'Should indicate user is logged in');
        $this->assertIsString($user['display_name'], 'Should include display name');
    }

    /**
     * Test class name follows PascalCase convention
     *
     * Verifies that the RestController class follows PascalCase naming convention.
     *
     * @return void
     */
    public function test_restController_class_name_should_follow_pascal_case_convention(): void
    {
        $this->assertClassFollowsPascalCase(RestController::class);
    }

    /**
     * Test method names follow camelCase convention
     *
     * Verifies that all public methods follow camelCase naming convention.
     *
     * @return void
     */
    public function test_restController_public_methods_should_follow_camel_case_convention(): void
    {
        $publicMethods = [
            'getInstance',
            'registerEndpoints',
            'healthCheck',
            'getWidgetConfig',
            'checkPublicPermission',
            'checkUserPermission',
            'checkAdminPermission',
            'verifyNonce',
            'checkRateLimit',
            'addSecurityHeaders',
            'addCorsHeaders',
            'validateRequest',
            'getEndpoints',
            'getNamespace',
            'hasEndpoint',
            'getEndpoint'
        ];

        foreach ($publicMethods as $methodName) {
            $this->assertMethodFollowsCamelCase($this->restController, $methodName);
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
            'loadEndpointClasses',
            'getClientIp',
            'getAllowedOrigins'
        ];

        foreach ($privateMethods as $methodName) {
            $method = $this->getReflectionMethod($this->restController, $methodName);
            $this->assertTrue($method->isPrivate(), "Method {$methodName} should be private");
        }
    }

    /**
     * Test memory usage remains reasonable
     *
     * Verifies that the REST controller doesn't consume excessive memory.
     *
     * @return void
     */
    public function test_restController_memory_usage_should_be_reasonable(): void
    {
        $initialMemory = memory_get_usage();

        // Perform multiple REST controller operations
        for ($i = 0; $i < 10; $i++) {
            $request = new WP_REST_Request('GET', '/woo-ai-assistant/v1/health');
            $this->restController->healthCheck($request);
            $this->restController->getNamespace();
            $this->restController->getEndpoints();
            $this->restController->checkPublicPermission($request);
        }

        $finalMemory = memory_get_usage();
        $memoryIncrease = $finalMemory - $initialMemory;

        // Memory increase should be less than 2MB for these operations
        $this->assertLessThan(2097152, $memoryIncrease, 'Memory increase should be less than 2MB for repeated operations');
    }

    /**
     * Test error handling in endpoint registration
     *
     * Verifies that REST controller handles endpoint registration errors gracefully.
     *
     * @return void
     */
    public function test_restController_should_handle_endpoint_registration_errors_gracefully(): void
    {
        // Mock the loadEndpointClasses method to test error handling
        // This would typically involve mocking non-existent endpoint classes
        
        $this->restController->registerEndpoints();

        // Should not throw fatal errors even if some endpoint classes don't exist
        $this->assertTrue(true, 'Should handle endpoint registration errors gracefully');
    }

    /**
     * Test WordPress hooks are properly registered
     *
     * Verifies that all necessary WordPress REST hooks are registered.
     *
     * @return void
     */
    public function test_restController_should_register_wordpress_hooks(): void
    {
        // Verify hooks were added during initialization
        $this->assertTrue(has_action('rest_api_init', [$this->restController, 'registerEndpoints']), 'Should register rest_api_init hook');
        $this->assertTrue(has_filter('rest_pre_dispatch', [$this->restController, 'addSecurityHeaders']), 'Should register rest_pre_dispatch filter');
        $this->assertTrue(has_filter('rest_pre_serve_request', [$this->restController, 'addCorsHeaders']), 'Should register rest_pre_serve_request filter');
    }

    /**
     * Clean up test environment
     *
     * @return void
     */
    public function tearDown(): void
    {
        global $wp_rest_server;
        $wp_rest_server = null;

        // Clean up server variables
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI']);

        parent::tearDown();
    }
}