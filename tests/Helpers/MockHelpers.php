<?php
/**
 * Mock Helpers Trait
 *
 * Provides consistent mocking methods for external dependencies
 * and services used throughout the test suite.
 *
 * @package WooAiAssistant\Tests\Helpers
 * @since 1.0.0
 */

namespace WooAiAssistant\Tests\Helpers;

/**
 * Mock Helpers Trait
 *
 * @since 1.0.0
 */
trait MockHelpers
{
    /**
     * Disable rate limiting for current test
     */
    protected function disableRateLimiting(): void
    {
        // Add specific filter to completely bypass rate limiting
        add_filter('woo_ai_assistant_bypass_rate_limit', '__return_true', 999);
        
        // Mock the rate limiter service to always allow requests
        add_filter('woo_ai_assistant_rate_limiter_check', function($allowed, $identifier, $limit, $window) {
            return true; // Always allow
        }, 999, 4);
        
        // Override rate limit status checks
        add_filter('woo_ai_assistant_rate_limit_status', function($status) {
            return [
                'allowed' => true,
                'remaining' => 999999,
                'reset_time' => time() + 3600
            ];
        }, 999);
    }
    
    /**
     * Mock external HTTP requests
     */
    protected function mockExternalRequests(): void
    {
        // Remove any existing HTTP mocks first
        remove_all_filters('pre_http_request');
        
        // Add comprehensive HTTP mocking
        add_filter('pre_http_request', function($preempt, $parsed_args, $url) {
            // Allow local WordPress requests
            if ($this->isLocalRequest($url)) {
                return false;
            }
            
            return $this->getMockHttpResponse($url, $parsed_args);
        }, 1, 3);
    }
    
    /**
     * Check if request is to local WordPress instance
     */
    private function isLocalRequest(string $url): bool
    {
        return strpos($url, 'localhost') !== false || 
               strpos($url, '127.0.0.1') !== false || 
               strpos($url, 'example.com') !== false;
    }
    
    /**
     * Get mock HTTP response based on URL
     */
    private function getMockHttpResponse(string $url, array $parsed_args): array
    {
        // OpenRouter API
        if (strpos($url, 'openrouter.ai') !== false) {
            return $this->getMockAIResponse($parsed_args);
        }
        
        // Pinecone Vector DB
        if (strpos($url, 'pinecone.io') !== false) {
            return $this->getMockVectorResponse($parsed_args);
        }
        
        // Stripe API
        if (strpos($url, 'api.stripe.com') !== false) {
            return $this->getMockStripeResponse($parsed_args);
        }
        
        // Intermediate Server
        if (strpos($url, 'api.woo-ai-assistant.com') !== false || 
            strpos($url, 'localhost:3000') !== false) {
            return $this->getMockIntermediateResponse($parsed_args);
        }
        
        // Default successful response
        return [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode(['success' => true, 'mock' => true]),
            'response' => ['code' => 200, 'message' => 'OK']
        ];
    }
    
    /**
     * Get mock AI API response
     */
    private function getMockAIResponse(array $args): array
    {
        $body = json_decode($args['body'] ?? '{}', true);
        $prompt = $body['messages'][0]['content'] ?? 'test prompt';
        
        return [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'id' => 'mock-' . uniqid(),
                'object' => 'chat.completion',
                'created' => time(),
                'model' => 'google/gemini-2.5-flash',
                'choices' => [
                    [
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => "Mock AI response for: " . substr($prompt, 0, 50)
                        ],
                        'finish_reason' => 'stop'
                    ]
                ],
                'usage' => [
                    'prompt_tokens' => 25,
                    'completion_tokens' => 25,
                    'total_tokens' => 50
                ]
            ]),
            'response' => ['code' => 200, 'message' => 'OK']
        ];
    }
    
    /**
     * Get mock vector database response
     */
    private function getMockVectorResponse(array $args): array
    {
        return [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'matches' => [
                    [
                        'id' => 'mock-vector-1',
                        'score' => 0.85,
                        'metadata' => [
                            'content' => 'Mock knowledge base content',
                            'source_type' => 'product',
                            'source_id' => '123'
                        ]
                    ]
                ]
            ]),
            'response' => ['code' => 200, 'message' => 'OK']
        ];
    }
    
    /**
     * Get mock Stripe API response
     */
    private function getMockStripeResponse(array $args): array
    {
        return [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'id' => 'pi_mock_' . uniqid(),
                'object' => 'payment_intent',
                'status' => 'succeeded',
                'amount' => 2000,
                'currency' => 'usd'
            ]),
            'response' => ['code' => 200, 'message' => 'OK']
        ];
    }
    
    /**
     * Get mock intermediate server response
     */
    private function getMockIntermediateResponse(array $args): array
    {
        return [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'success' => true,
                'data' => [
                    'response' => 'Mock server response',
                    'tokens_used' => 50
                ],
                'license_valid' => true,
                'features_enabled' => ['chat', 'knowledge_base', 'analytics']
            ]),
            'response' => ['code' => 200, 'message' => 'OK']
        ];
    }
    
    /**
     * Mock WooCommerce cart operations
     */
    protected function mockWooCommerceCart(): void
    {
        // Ensure WC is available
        if (!function_exists('WC')) {
            return;
        }
        
        // Reset cart to known state
        WC()->cart->empty_cart();
        
        // Add some test products to cart for testing
        $this->addTestProductsToCart();
    }
    
    /**
     * Add test products to cart
     */
    private function addTestProductsToCart(): void
    {
        // Create test products if they don't exist
        $product_ids = $this->createTestWooCommerceProducts();
        
        foreach ($product_ids as $product_id) {
            WC()->cart->add_to_cart($product_id, 1);
        }
    }
    
    /**
     * Create test WooCommerce products
     */
    private function createTestWooCommerceProducts(): array
    {
        $product_ids = [];
        
        // Simple product
        $product_ids[] = $this->factory->post->create([
            'post_type' => 'product',
            'post_title' => 'Test Product 1',
            'post_content' => 'Test product description',
            'post_status' => 'publish'
        ]);
        
        // Variable product  
        $product_ids[] = $this->factory->post->create([
            'post_type' => 'product',
            'post_title' => 'Test Variable Product',
            'post_content' => 'Test variable product description',
            'post_status' => 'publish'
        ]);
        
        return $product_ids;
    }
    
    /**
     * Mock conversation data
     */
    protected function mockConversationData(): array
    {
        $conversation_id = 'conv_' . uniqid();
        $user_id = $this->factory->user->create(['role' => 'customer']);
        $session_id = 'test_session_' . wp_generate_uuid4();
        
        // Store in mock conversation mapping
        global $conversation_session_map;
        $conversation_session_map[$conversation_id] = $session_id;
        
        return [
            'conversation_id' => $conversation_id,
            'user_id' => $user_id,
            'session_id' => $session_id,
            'status' => 'active',
            'created_at' => current_time('mysql'),
            'context' => json_encode([
                'page' => 'shop',
                'user_type' => 'customer'
            ])
        ];
    }
    
    /**
     * Mock knowledge base data
     */
    protected function mockKnowledgeBaseData(): array
    {
        return [
            [
                'chunk_id' => 'chunk_' . uniqid(),
                'source_type' => 'product',
                'source_id' => 123,
                'content' => 'Test product knowledge base content',
                'content_hash' => md5('test content'),
                'chunk_size' => 100,
                'metadata' => json_encode([
                    'title' => 'Test Product',
                    'category' => 'electronics'
                ]),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ]
        ];
    }
    
    /**
     * Setup test user with specific capabilities
     */
    protected function setupTestUserWithCapabilities(array $capabilities): int
    {
        $user_id = $this->factory->user->create([
            'role' => 'customer',
            'user_login' => 'testuser_' . uniqid(),
            'user_email' => 'test_' . uniqid() . '@example.com'
        ]);
        
        $user = get_user_by('ID', $user_id);
        foreach ($capabilities as $cap) {
            $user->add_cap($cap);
        }
        
        return $user_id;
    }
    
    /**
     * Mock WordPress nonce verification
     */
    protected function mockNonceVerification(bool $should_pass = true): void
    {
        add_filter('wp_verify_nonce', function($result, $nonce, $action) use ($should_pass) {
            if ($should_pass) {
                return true;
            }
            
            // Check for specific test nonce values
            if ($nonce === 'invalid-nonce' || $nonce === 'wrong-nonce') {
                return false;
            }
            
            return $result;
        }, 999, 3);
    }
    
    /**
     * Reset all mocks and filters
     */
    protected function resetAllMocks(): void
    {
        // Remove all test-related filters
        remove_all_filters('woo_ai_assistant_bypass_rate_limit');
        remove_all_filters('woo_ai_assistant_rate_limiter_check');
        remove_all_filters('woo_ai_assistant_rate_limit_status');
        remove_all_filters('pre_http_request');
        remove_all_filters('wp_verify_nonce');
        
        // Clear global test data
        global $conversation_session_map, $mock_options;
        $conversation_session_map = [];
        
        // Reset WooCommerce cart if available
        if (function_exists('WC') && WC()->cart) {
            WC()->cart->empty_cart();
        }
        
        // Clear WordPress caches
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }
    
    /**
     * Assert that no rate limiting errors occur
     */
    protected function assertNoRateLimitingErrors($response): void
    {
        if (is_array($response) && isset($response['code'])) {
            $this->assertNotEquals('rate_limit_exceeded', $response['code'], 
                'Rate limiting should be disabled in tests');
        }
        
        if (is_object($response) && method_exists($response, 'get_data')) {
            $data = $response->get_data();
            if (isset($data['code'])) {
                $this->assertNotEquals('rate_limit_exceeded', $data['code'], 
                    'Rate limiting should be disabled in tests');
            }
        }
    }
    
    /**
     * Create mock REST request
     */
    protected function createMockRestRequest(string $method = 'POST', array $params = []): \WP_REST_Request
    {
        $request = new \WP_REST_Request($method);
        
        foreach ($params as $key => $value) {
            $request->set_param($key, $value);
        }
        
        return $request;
    }
    
    /**
     * Setup test environment for specific endpoint testing
     */
    protected function setupEndpointTestEnvironment(): void
    {
        // Disable rate limiting
        $this->disableRateLimiting();
        
        // Mock external requests
        $this->mockExternalRequests();
        
        // Setup WooCommerce
        $this->mockWooCommerceCart();
        
        // Mock nonce verification to pass by default
        $this->mockNonceVerification(true);
        
        // Set current user for capability checks
        wp_set_current_user($this->factory->user->create(['role' => 'customer']));
    }
}