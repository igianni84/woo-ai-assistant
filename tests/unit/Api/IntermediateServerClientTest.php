<?php

/**
 * Tests for Intermediate Server Client Class
 *
 * Comprehensive unit tests for the IntermediateServerClient class that handles
 * secure API communication with the intermediate server. Tests both development
 * and production modes, API calls, license validation, error handling, and rate limiting.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Unit\Api
 * @since 1.0.0
 * @author Claude Code Assistant
 */

namespace WooAiAssistant\Tests\Unit\Api;

use WooAiAssistant\Tests\Unit\WooAiBaseTestCase;
use WooAiAssistant\Api\IntermediateServerClient;
use WooAiAssistant\Config\DevelopmentConfig;
use WooAiAssistant\Config\ApiConfiguration;
use WooAiAssistant\Common\Exceptions\ApiException;
use WooAiAssistant\Common\Exceptions\ValidationException;
use WooAiAssistant\Common\Cache;
use Exception;

/**
 * Class IntermediateServerClientTest
 *
 * Test cases for the Intermediate Server Client class.
 * Verifies API communication, license validation, development mode handling, and error management.
 *
 * @since 1.0.0
 */
class IntermediateServerClientTest extends WooAiBaseTestCase
{
    /**
     * IntermediateServerClient instance
     *
     * @var IntermediateServerClient
     */
    private $client;

    /**
     * Development configuration instance
     *
     * @var DevelopmentConfig
     */
    private $developmentConfig;

    /**
     * API configuration instance
     *
     * @var ApiConfiguration
     */
    private $apiConfiguration;

    /**
     * Cache instance for testing
     *
     * @var Cache
     */
    private $cache;

    /**
     * Set up test environment
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->client = IntermediateServerClient::getInstance();
        $this->developmentConfig = DevelopmentConfig::getInstance();
        $this->apiConfiguration = ApiConfiguration::getInstance();
        $this->cache = Cache::getInstance();

        // Ensure development mode is active for most tests
        add_filter('woo_ai_assistant_is_development_mode', '__return_true');

        // Set up mock configurations
        $this->setupMockConfigurations();
    }

    /**
     * Set up mock configurations for testing
     *
     * @return void
     */
    private function setupMockConfigurations(): void
    {
        // Mock development config
        add_filter('woo_ai_assistant_development_config', function() {
            return [
                'bypass_license_validation' => true,
                'bypass_intermediate_server' => true,
                'api_timeout' => 10,
                'retry_attempts' => 2,
                'enhanced_logging' => true
            ];
        });

        // Mock API configuration
        add_filter('woo_ai_assistant_api_config', function() {
            return [
                'intermediate_server' => [
                    'enabled' => false,
                    'url' => 'https://api.example.com',
                    'primary_url' => 'https://api.example.com',
                    'fallback_urls' => ['https://api2.example.com']
                ],
                'license' => [
                    'key' => 'test-license-key-123',
                    'status' => 'active'
                ],
                'models' => [
                    'primary_chat' => 'gemini-2.5-flash',
                    'embedding' => 'text-embedding-3-small'
                ]
            ];
        });
    }

    /**
     * Test client singleton pattern
     *
     * Verifies that IntermediateServerClient class follows singleton pattern correctly.
     *
     * @return void
     */
    public function test_getInstance_should_return_singleton_instance(): void
    {
        $instance1 = IntermediateServerClient::getInstance();
        $instance2 = IntermediateServerClient::getInstance();

        $this->assertInstanceOf(IntermediateServerClient::class, $instance1);
        $this->assertSame($instance1, $instance2, 'getInstance should return the same instance (singleton pattern)');
    }

    /**
     * Test chat completion in development mode
     *
     * Verifies that chatCompletion works correctly in development mode with bypass.
     *
     * @return void
     */
    public function test_chatCompletion_should_work_in_development_mode(): void
    {
        $messages = [
            ['role' => 'user', 'content' => 'Hello, how can you help me?']
        ];

        $options = [
            'model' => 'gemini-2.5-flash',
            'temperature' => 0.7,
            'max_tokens' => 1000
        ];

        // Mock the direct API call response
        add_filter('woo_ai_assistant_mock_direct_api_response', function() {
            return [
                'direct_api_call' => true,
                'service' => 'openrouter',
                'endpoint' => 'chat',
                'message' => 'Direct API call completed (development mode)',
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Hello! I can help you with product information and support.'
                        ]
                    ]
                ]
            ];
        });

        $response = $this->client->chatCompletion($messages, $options);

        $this->assertIsArray($response, 'chatCompletion should return array');
        $this->assertArrayHasKey('direct_api_call', $response, 'Response should indicate direct API call');
        $this->assertTrue($response['direct_api_call'], 'Should use direct API call in development mode');
        $this->assertEquals('openrouter', $response['service'], 'Should use OpenRouter service');

        remove_filter('woo_ai_assistant_mock_direct_api_response');
    }

    /**
     * Test chat completion with invalid messages
     *
     * Verifies that chatCompletion validates message structure correctly.
     *
     * @return void
     */
    public function test_chatCompletion_should_validate_messages(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Messages array cannot be empty');

        $this->client->chatCompletion([]);
    }

    /**
     * Test chat completion with invalid message structure
     *
     * Verifies that chatCompletion validates individual message structure.
     *
     * @return void
     */
    public function test_chatCompletion_should_validate_message_structure(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Message at index 0 must have role and content');

        $invalidMessages = [
            ['role' => 'user']  // Missing content
        ];

        $this->client->chatCompletion($invalidMessages);
    }

    /**
     * Test chat completion with invalid role
     *
     * Verifies that chatCompletion validates message roles.
     *
     * @return void
     */
    public function test_chatCompletion_should_validate_message_role(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid role at index 0');

        $invalidMessages = [
            ['role' => 'invalid_role', 'content' => 'Test message']
        ];

        $this->client->chatCompletion($invalidMessages);
    }

    /**
     * Test embeddings generation in development mode
     *
     * Verifies that generateEmbeddings works correctly in development mode.
     *
     * @return void
     */
    public function test_generateEmbeddings_should_work_in_development_mode(): void
    {
        $texts = [
            'Test text for embedding generation',
            'Another text to embed'
        ];

        // Mock direct API response
        add_filter('woo_ai_assistant_mock_direct_api_response', function() {
            return [
                'direct_api_call' => true,
                'service' => 'openai',
                'endpoint' => 'embeddings',
                'message' => 'Direct API call completed (development mode)',
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)],
                    ['embedding' => array_fill(0, 1536, 0.2)]
                ]
            ];
        });

        $response = $this->client->generateEmbeddings($texts);

        $this->assertIsArray($response, 'generateEmbeddings should return array');
        $this->assertArrayHasKey('direct_api_call', $response, 'Response should indicate direct API call');
        $this->assertTrue($response['direct_api_call'], 'Should use direct API call in development mode');
        $this->assertEquals('openai', $response['service'], 'Should use OpenAI service');

        remove_filter('woo_ai_assistant_mock_direct_api_response');
    }

    /**
     * Test embeddings generation with empty texts
     *
     * Verifies that generateEmbeddings validates input correctly.
     *
     * @return void
     */
    public function test_generateEmbeddings_should_validate_empty_texts(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Texts array cannot be empty');

        $this->client->generateEmbeddings([]);
    }

    /**
     * Test embeddings generation with invalid text type
     *
     * Verifies that generateEmbeddings validates text types.
     *
     * @return void
     */
    public function test_generateEmbeddings_should_validate_text_types(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Text at index 0 must be string');

        $this->client->generateEmbeddings([123, 'valid text']);
    }

    /**
     * Test embeddings generation with text too long
     *
     * Verifies that generateEmbeddings validates text length.
     *
     * @return void
     */
    public function test_generateEmbeddings_should_validate_text_length(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Text at index 0 exceeds maximum length');

        $longText = str_repeat('a', 9000);  // Exceeds 8000 character limit
        $this->client->generateEmbeddings([$longText]);
    }

    /**
     * Test license validation in development mode
     *
     * Verifies that validateLicense bypasses validation in development mode.
     *
     * @return void
     */
    public function test_validateLicense_should_bypass_in_development_mode(): void
    {
        $licenseKey = 'test-license-key-123';

        $response = $this->client->validateLicense($licenseKey);

        $this->assertIsArray($response, 'validateLicense should return array');
        $this->assertArrayHasKey('valid', $response, 'Response should contain valid key');
        $this->assertArrayHasKey('development_bypass', $response, 'Response should indicate development bypass');
        
        $this->assertTrue($response['valid'], 'License should be valid in development mode');
        $this->assertTrue($response['development_bypass'], 'Should indicate development bypass');
        $this->assertEquals('unlimited', $response['plan'], 'Should have unlimited plan in development');
        $this->assertEquals(-1, $response['usage']['limit'], 'Should have unlimited usage');
    }

    /**
     * Test license validation with empty key
     *
     * Verifies that validateLicense validates license key input.
     *
     * @return void
     */
    public function test_validateLicense_should_validate_empty_key(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('License key cannot be empty');

        $this->client->validateLicense('');
    }

    /**
     * Test license usage in development mode
     *
     * Verifies that getLicenseUsage returns development bypass data.
     *
     * @return void
     */
    public function test_getLicenseUsage_should_return_development_data(): void
    {
        $licenseKey = 'test-license-key-123';

        $response = $this->client->getLicenseUsage($licenseKey);

        $this->assertIsArray($response, 'getLicenseUsage should return array');
        $this->assertArrayHasKey('conversations', $response, 'Response should contain conversations');
        $this->assertArrayHasKey('limit', $response, 'Response should contain limit');
        $this->assertArrayHasKey('development_bypass', $response, 'Response should indicate development bypass');

        $this->assertEquals(0, $response['conversations'], 'Should have zero conversations in development');
        $this->assertEquals(-1, $response['limit'], 'Should have unlimited limit');
        $this->assertTrue($response['development_bypass'], 'Should indicate development bypass');
    }

    /**
     * Test health check functionality
     *
     * Verifies that healthCheck returns proper status information.
     *
     * @return void
     */
    public function test_healthCheck_should_return_status_info(): void
    {
        // Mock a successful health check response
        add_filter('pre_http_request', function($preempt, $parsed_args, $url) {
            if (strpos($url, '/api/v1/health') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'status' => 'healthy',
                        'timestamp' => time(),
                        'version' => '1.0.0'
                    ])
                ];
            }
            return false;
        }, 10, 3);

        $response = $this->client->healthCheck();

        $this->assertIsArray($response, 'healthCheck should return array');

        // Remove the filter
        remove_all_filters('pre_http_request');
    }

    /**
     * Test rate limit status functionality
     *
     * Verifies that getRateLimitStatus returns correct rate limiting information.
     *
     * @return void
     */
    public function test_getRateLimitStatus_should_return_rate_limit_info(): void
    {
        $endpoint = 'chat';
        $status = $this->client->getRateLimitStatus($endpoint);

        $this->assertIsArray($status, 'getRateLimitStatus should return array');
        
        $expectedKeys = [
            'endpoint',
            'current',
            'limit',
            'remaining',
            'reset_time',
            'reset_in_seconds'
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $status, "Rate limit status should contain '{$key}' key");
        }

        $this->assertEquals($endpoint, $status['endpoint'], 'Should return correct endpoint');
        $this->assertIsInt($status['current'], 'Current should be integer');
        $this->assertIsInt($status['limit'], 'Limit should be integer');
        $this->assertIsInt($status['remaining'], 'Remaining should be integer');
        $this->assertGreaterThanOrEqual(0, $status['remaining'], 'Remaining should be non-negative');
    }

    /**
     * Test rate limit reset functionality
     *
     * Verifies that resetRateLimits clears rate limiting data.
     *
     * @return void
     */
    public function test_resetRateLimits_should_clear_rate_limits(): void
    {
        $endpoint = 'chat';
        
        // Set some rate limit data
        $cacheKey = "rate_limit_{$endpoint}";
        $this->cache->set($cacheKey, 50, 3600);

        // Verify rate limit is set
        $statusBefore = $this->client->getRateLimitStatus($endpoint);
        $this->assertGreaterThan(0, $statusBefore['current'], 'Should have rate limit data before reset');

        // Reset rate limits
        $result = $this->client->resetRateLimits($endpoint);
        $this->assertTrue($result, 'resetRateLimits should return true');

        // Verify rate limit is cleared
        $statusAfter = $this->client->getRateLimitStatus($endpoint);
        $this->assertEquals(0, $statusAfter['current'], 'Should have cleared rate limit data after reset');
    }

    /**
     * Test client status information
     *
     * Verifies that getClientStatus returns comprehensive status data.
     *
     * @return void
     */
    public function test_getClientStatus_should_return_comprehensive_info(): void
    {
        $status = $this->client->getClientStatus();

        $this->assertIsArray($status, 'getClientStatus should return array');

        $expectedKeys = [
            'client_initialized',
            'environment',
            'configuration',
            'rate_limits',
            'server_connectivity',
            'timestamp'
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $status, "Client status should contain '{$key}' key");
        }

        $this->assertTrue($status['client_initialized'], 'Client should be initialized');
        $this->assertIsArray($status['environment'], 'Environment should be array');
        $this->assertIsArray($status['configuration'], 'Configuration should be array');
        $this->assertIsArray($status['rate_limits'], 'Rate limits should be array');
        $this->assertIsInt($status['timestamp'], 'Timestamp should be integer');

        // Check environment structure
        $environment = $status['environment'];
        $envKeys = ['type', 'is_development', 'bypass_intermediate_server', 'bypass_license_validation'];
        foreach ($envKeys as $key) {
            $this->assertArrayHasKey($key, $environment, "Environment should contain '{$key}' key");
        }

        $this->assertIsBool($environment['is_development'], 'is_development should be boolean');
        $this->assertTrue($environment['is_development'], 'Should be in development mode');
        $this->assertTrue($environment['bypass_intermediate_server'], 'Should bypass intermediate server in development');
        $this->assertTrue($environment['bypass_license_validation'], 'Should bypass license validation in development');
    }

    /**
     * Test client configuration validation
     *
     * Verifies that validateClientConfiguration checks all configurations.
     *
     * @return void
     */
    public function test_validateClientConfiguration_should_check_all_configs(): void
    {
        $validation = $this->client->validateClientConfiguration();

        $this->assertIsArray($validation, 'validateClientConfiguration should return array');

        $expectedKeys = [
            'valid',
            'errors',
            'warnings',
            'recommendations'
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $validation, "Validation should contain '{$key}' key");
        }

        $this->assertIsBool($validation['valid'], 'valid should be boolean');
        $this->assertIsArray($validation['errors'], 'errors should be array');
        $this->assertIsArray($validation['warnings'], 'warnings should be array');
        $this->assertIsArray($validation['recommendations'], 'recommendations should be array');
    }

    /**
     * Test optimized request options
     *
     * Verifies that getOptimizedRequestOptions returns proper configuration.
     *
     * @return void
     */
    public function test_getOptimizedRequestOptions_should_return_optimized_config(): void
    {
        $options = $this->client->getOptimizedRequestOptions();

        $this->assertIsArray($options, 'getOptimizedRequestOptions should return array');

        $expectedKeys = [
            'timeout',
            'retry_attempts',
            'cache_ttl',
            'skip_ssl_verify',
            'detailed_errors',
            'enhanced_logging'
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $options, "Optimized options should contain '{$key}' key");
        }

        $this->assertIsInt($options['timeout'], 'timeout should be integer');
        $this->assertIsInt($options['retry_attempts'], 'retry_attempts should be integer');
        $this->assertIsInt($options['cache_ttl'], 'cache_ttl should be integer');
        $this->assertIsBool($options['skip_ssl_verify'], 'skip_ssl_verify should be boolean');
        $this->assertIsBool($options['detailed_errors'], 'detailed_errors should be boolean');
        $this->assertIsBool($options['enhanced_logging'], 'enhanced_logging should be boolean');
    }

    /**
     * Test class name follows PascalCase convention
     *
     * Verifies that the IntermediateServerClient class follows PascalCase naming convention.
     *
     * @return void
     */
    public function test_client_class_name_should_follow_pascal_case_convention(): void
    {
        $this->assertClassFollowsPascalCase(IntermediateServerClient::class);
    }

    /**
     * Test method names follow camelCase convention
     *
     * Verifies that all public methods follow camelCase naming convention.
     *
     * @return void
     */
    public function test_client_public_methods_should_follow_camel_case_convention(): void
    {
        $publicMethods = [
            'getInstance',
            'chatCompletion',
            'generateEmbeddings',
            'validateLicense',
            'getLicenseUsage',
            'healthCheck',
            'getRateLimitStatus',
            'resetRateLimits',
            'getClientStatus',
            'validateClientConfiguration',
            'getOptimizedRequestOptions'
        ];

        foreach ($publicMethods as $methodName) {
            $this->assertMethodFollowsCamelCase($this->client, $methodName);
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
            'makeRequest',
            'executeHttpRequest',
            'buildUrl',
            'buildHeaders',
            'prepareRequestData',
            'generateRequestSignature',
            'checkRateLimit',
            'updateRateLimit',
            'calculateRetryDelay',
            'validateMessages',
            'shouldBypassIntermediateServer',
            'shouldBypassLicenseValidation'
        ];

        foreach ($privateMethods as $methodName) {
            $method = $this->getReflectionMethod($this->client, $methodName);
            $this->assertTrue($method->isPrivate() || $method->isProtected(), "Method {$methodName} should be private or protected");
        }
    }

    /**
     * Test request signature generation
     *
     * Verifies that request signatures are generated correctly.
     *
     * @return void
     */
    public function test_generateRequestSignature_should_create_valid_signature(): void
    {
        $endpoint = 'chat';
        $data = ['test' => 'data'];

        $signature = $this->invokeMethod($this->client, 'generateRequestSignature', [$endpoint, $data]);

        $this->assertIsString($signature, 'generateRequestSignature should return string');
        $this->assertEquals(64, strlen($signature), 'Signature should be 64 characters (SHA256 hex)');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $signature, 'Signature should be valid SHA256 hex');
    }

    /**
     * Test URL building functionality
     *
     * Verifies that buildUrl creates correct URLs for endpoints.
     *
     * @return void
     */
    public function test_buildUrl_should_create_correct_urls(): void
    {
        $endpoint = 'chat';
        $url = $this->invokeMethod($this->client, 'buildUrl', [$endpoint]);

        $this->assertIsString($url, 'buildUrl should return string');
        $this->assertStringContainsString('/api/v1/chat/completions', $url, 'URL should contain correct endpoint path');
        $this->assertStringStartsWith('https://', $url, 'URL should use HTTPS');
    }

    /**
     * Test invalid endpoint handling
     *
     * Verifies that buildUrl validates endpoint names.
     *
     * @return void
     */
    public function test_buildUrl_should_validate_endpoint(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Unknown endpoint: invalid_endpoint');

        $this->invokeMethod($this->client, 'buildUrl', ['invalid_endpoint']);
    }

    /**
     * Test memory usage during operations
     *
     * Verifies that client operations don't consume excessive memory.
     *
     * @return void
     */
    public function test_client_memory_usage_should_be_reasonable(): void
    {
        $initialMemory = memory_get_usage();

        // Perform multiple operations
        for ($i = 0; $i < 10; $i++) {
            $this->client->getClientStatus();
            $this->client->getRateLimitStatus('chat');
            $this->client->validateLicense('test-key');
        }

        $finalMemory = memory_get_usage();
        $memoryIncrease = $finalMemory - $initialMemory;

        // Memory increase should be reasonable
        $this->assertLessThan(1048576, $memoryIncrease, 'Memory increase should be less than 1MB for repeated operations');
    }

    /**
     * Clean up test data
     *
     * @return void
     */
    protected function cleanUpTestData(): void
    {
        // Clear cache
        $this->cache->flush();

        parent::cleanUpTestData();
    }

    /**
     * Tear down test environment
     *
     * @return void
     */
    public function tearDown(): void
    {
        // Clean up filters
        remove_filter('woo_ai_assistant_is_development_mode');
        remove_all_filters('woo_ai_assistant_development_config');
        remove_all_filters('woo_ai_assistant_api_config');
        remove_all_filters('woo_ai_assistant_mock_direct_api_response');
        remove_all_filters('pre_http_request');

        parent::tearDown();
    }
}