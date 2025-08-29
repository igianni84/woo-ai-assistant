<?php
/**
 * Enhanced Test Bootstrap
 *
 * Provides comprehensive test environment setup with rate limiting disabled,
 * external service mocking, and proper test isolation.
 *
 * @package WooAiAssistant\Tests
 * @since 1.0.0
 */

namespace WooAiAssistant\Tests;

/**
 * Enhanced Test Bootstrap Class
 */
class TestBootstrap
{
    /**
     * Initialize test environment
     */
    public static function initialize(): void
    {
        // Set critical test environment constants
        self::setTestConstants();
        
        // Disable rate limiting for all tests
        self::disableRateLimiting();
        
        // Set up mock external services
        self::setupExternalServiceMocks();
        
        // Configure test-specific settings
        self::configureTestSettings();
        
        // Set up WordPress test environment
        self::setupWordPressEnvironment();
    }
    
    /**
     * Set test environment constants
     */
    private static function setTestConstants(): void
    {
        // Core testing constants
        if (!defined('WOO_AI_ASSISTANT_TESTING')) {
            define('WOO_AI_ASSISTANT_TESTING', true);
        }
        
        // Disable rate limiting completely in tests
        if (!defined('WOO_AI_ASSISTANT_DISABLE_RATE_LIMITING')) {
            define('WOO_AI_ASSISTANT_DISABLE_RATE_LIMITING', true);
        }
        
        // Enable mock mode for external services
        if (!defined('WOO_AI_ASSISTANT_MOCK_MODE')) {
            define('WOO_AI_ASSISTANT_MOCK_MODE', true);
        }
        
        // Disable real HTTP requests
        if (!defined('WOO_AI_ASSISTANT_MOCK_HTTP')) {
            define('WOO_AI_ASSISTANT_MOCK_HTTP', true);
        }
        
        // Disable license validation
        if (!defined('WOO_AI_ASSISTANT_SKIP_LICENSE_CHECK')) {
            define('WOO_AI_ASSISTANT_SKIP_LICENSE_CHECK', true);
        }
        
        // Use mock AI responses
        if (!defined('WOO_AI_ASSISTANT_USE_MOCK_AI')) {
            define('WOO_AI_ASSISTANT_USE_MOCK_AI', true);
        }
        
        // Set test timeout to prevent hanging
        if (!defined('WOO_AI_ASSISTANT_TEST_TIMEOUT')) {
            define('WOO_AI_ASSISTANT_TEST_TIMEOUT', 5);
        }
    }
    
    /**
     * Completely disable rate limiting for tests
     */
    private static function disableRateLimiting(): void
    {
        // Add filter to bypass all rate limiting
        add_filter('woo_ai_assistant_rate_limit_check', '__return_true');
        
        // Override rate limiter with mock that always allows requests
        add_filter('woo_ai_assistant_rate_limiter_instance', function() {
            return new class {
                public function checkRateLimit($identifier, $limit, $window): bool {
                    return true; // Always allow
                }
                
                public function isWhitelisted($identifier): bool {
                    return true; // Everything is whitelisted in tests
                }
                
                public function addToWhitelist($identifier): bool {
                    return true;
                }
                
                public function removeFromWhitelist($identifier): bool {
                    return true;
                }
                
                public function getRemainingRequests($identifier, $limit, $window): int {
                    return 999999; // Unlimited
                }
                
                public function resetRateLimit($identifier): bool {
                    return true;
                }
            };
        });
    }
    
    /**
     * Set up external service mocks
     */
    private static function setupExternalServiceMocks(): void
    {
        // Mock all HTTP requests
        add_filter('pre_http_request', [self::class, 'mockHttpRequests'], 1, 3);
        
        // Mock IntermediateServerClient
        add_filter('woo_ai_assistant_intermediate_server_client', function() {
            return self::getMockIntermediateServerClient();
        });
        
        // Mock AIManager
        add_filter('woo_ai_assistant_ai_manager', function() {
            return self::getMockAIManager();
        });
        
        // Mock LicenseManager
        add_filter('woo_ai_assistant_license_manager', function() {
            return self::getMockLicenseManager();
        });
    }
    
    /**
     * Mock HTTP requests
     */
    public static function mockHttpRequests($preempt, $parsed_args, $url)
    {
        // Allow real requests only to localhost WordPress test instance
        if (strpos($url, 'localhost') !== false && 
            (strpos($url, ':8888') !== false || strpos($url, 'wp-admin') !== false)) {
            return false; // Don't mock local WordPress requests
        }
        
        // Mock OpenRouter API responses
        if (strpos($url, 'openrouter.ai') !== false) {
            return self::getMockOpenRouterResponse();
        }
        
        // Mock Pinecone API responses
        if (strpos($url, 'pinecone.io') !== false) {
            return self::getMockPineconeResponse();
        }
        
        // Mock Stripe API responses
        if (strpos($url, 'api.stripe.com') !== false) {
            return self::getMockStripeResponse();
        }
        
        // Mock intermediate server responses
        if (strpos($url, 'api.woo-ai-assistant.com') !== false || 
            strpos($url, 'localhost:3000') !== false) {
            return self::getMockIntermediateServerResponse();
        }
        
        // Default mock response for any other external requests
        return [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'success' => true,
                'data' => 'Mock response for testing',
                'mock' => true
            ]),
            'response' => [
                'code' => 200,
                'message' => 'OK'
            ]
        ];
    }
    
    /**
     * Get mock OpenRouter API response
     */
    private static function getMockOpenRouterResponse(): array
    {
        return [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'id' => 'mock-completion-' . time(),
                'object' => 'chat.completion',
                'created' => time(),
                'model' => 'google/gemini-2.5-flash',
                'choices' => [
                    [
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'This is a mock AI response for testing purposes. The system is working correctly.'
                        ],
                        'finish_reason' => 'stop'
                    ]
                ],
                'usage' => [
                    'prompt_tokens' => 50,
                    'completion_tokens' => 20,
                    'total_tokens' => 70
                ]
            ]),
            'response' => ['code' => 200, 'message' => 'OK']
        ];
    }
    
    /**
     * Get mock Pinecone API response
     */
    private static function getMockPineconeResponse(): array
    {
        return [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'matches' => [
                    [
                        'id' => 'mock-vector-1',
                        'score' => 0.85,
                        'metadata' => [
                            'content' => 'Mock knowledge base content for testing',
                            'source_type' => 'product',
                            'source_id' => '123'
                        ]
                    ]
                ],
                'namespace' => 'test'
            ]),
            'response' => ['code' => 200, 'message' => 'OK']
        ];
    }
    
    /**
     * Get mock Stripe API response
     */
    private static function getMockStripeResponse(): array
    {
        return [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'id' => 'mock-payment-intent',
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
    private static function getMockIntermediateServerResponse(): array
    {
        return [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'success' => true,
                'data' => [
                    'response' => 'Mock intermediate server response',
                    'tokens_used' => 50,
                    'model' => 'mock-model'
                ],
                'license_valid' => true,
                'features_enabled' => ['chat', 'knowledge_base', 'analytics']
            ]),
            'response' => ['code' => 200, 'message' => 'OK']
        ];
    }
    
    /**
     * Get mock IntermediateServerClient
     */
    private static function getMockIntermediateServerClient(): object
    {
        return new class {
            public function sendChatRequest($request): array {
                return [
                    'success' => true,
                    'response' => 'Mock AI response for testing',
                    'tokens_used' => 50,
                    'model' => 'mock-model'
                ];
            }
            
            public function validateLicense(): array {
                return [
                    'valid' => true,
                    'license_type' => 'premium',
                    'expires_at' => date('Y-m-d', strtotime('+1 year')),
                    'features' => ['chat', 'knowledge_base', 'analytics', 'advanced_features']
                ];
            }
            
            public function uploadChunks($chunks): array {
                return [
                    'success' => true,
                    'chunks_processed' => count($chunks),
                    'vectors_created' => count($chunks)
                ];
            }
            
            public function queryKnowledgeBase($query, $limit = 5): array {
                return [
                    'success' => true,
                    'matches' => [
                        [
                            'content' => 'Mock knowledge base result for: ' . $query,
                            'score' => 0.85,
                            'metadata' => ['source_type' => 'product', 'source_id' => '123']
                        ]
                    ]
                ];
            }
            
            public function isHealthy(): bool {
                return true;
            }
        };
    }
    
    /**
     * Get mock AIManager
     */
    private static function getMockAIManager(): object
    {
        return new class {
            public function generateResponse($prompt, $context = []): array {
                return [
                    'response' => 'Mock AI response: ' . substr($prompt, 0, 50) . '...',
                    'tokens_used' => 50,
                    'model' => 'mock-model',
                    'context_used' => count($context)
                ];
            }
            
            public function streamResponse($prompt, $context = []): \Generator {
                $words = explode(' ', 'Mock AI streaming response for testing purposes');
                foreach ($words as $word) {
                    yield ['delta' => ['content' => $word . ' ']];
                }
                yield ['finish_reason' => 'stop'];
            }
            
            public function generateEmbeddings($texts): array {
                $embeddings = [];
                foreach ($texts as $index => $text) {
                    // Generate fake embeddings (384 dimensions for text-embedding-3-small)
                    $embedding = [];
                    for ($i = 0; $i < 384; $i++) {
                        $embedding[] = mt_rand(-100, 100) / 100.0;
                    }
                    $embeddings[] = $embedding;
                }
                return $embeddings;
            }
        };
    }
    
    /**
     * Get mock LicenseManager
     */
    private static function getMockLicenseManager(): object
    {
        return new class {
            public function validateLicense(): bool {
                return true; // Always valid in tests
            }
            
            public function isFeatureEnabled($feature): bool {
                return true; // All features enabled in tests
            }
            
            public function getLicenseInfo(): array {
                return [
                    'valid' => true,
                    'license_type' => 'premium',
                    'expires_at' => date('Y-m-d', strtotime('+1 year')),
                    'features' => ['chat', 'knowledge_base', 'analytics', 'advanced_features'],
                    'limits' => [
                        'conversations_per_month' => 999999,
                        'knowledge_base_chunks' => 999999,
                        'api_requests_per_day' => 999999
                    ]
                ];
            }
            
            public function checkUsageLimits(): array {
                return [
                    'within_limits' => true,
                    'usage' => [
                        'conversations_this_month' => 0,
                        'knowledge_base_chunks' => 0,
                        'api_requests_today' => 0
                    ]
                ];
            }
        };
    }
    
    /**
     * Configure test-specific settings
     */
    private static function configureTestSettings(): void
    {
        // Set up mock options for testing
        global $mock_options;
        
        $testOptions = [
            'woo_ai_assistant_enabled' => true,
            'woo_ai_assistant_debug_mode' => true,
            'woo_ai_assistant_api_key' => 'test-api-key',
            'woo_ai_assistant_welcome_message' => 'Hi! Welcome to our store! How can I help you today?',
            'woo_ai_assistant_auto_index' => true,
            'woo_ai_assistant_rate_limiting_enabled' => false,
            'woo_ai_assistant_license_key' => 'test-license-key',
            'woo_ai_assistant_features_enabled' => [
                'chat' => true,
                'knowledge_base' => true,
                'analytics' => true,
                'advanced_features' => true
            ]
        ];
        
        foreach ($testOptions as $option => $value) {
            $mock_options[$option] = $value;
        }
        
        // Set up store information for default message tests
        $mock_options['blogname'] = 'Test Store Name';
        $mock_options['blogdescription'] = 'Test Store Description';
        $mock_options['woocommerce_store_address'] = 'Test Store Address';
        $mock_options['woocommerce_store_city'] = 'Test City';
        $mock_options['woocommerce_default_country'] = 'US:CA';
    }
    
    /**
     * Set up WordPress test environment
     */
    private static function setupWordPressEnvironment(): void
    {
        // Ensure WordPress is in test mode
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['REQUEST_URI'] = '/test';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        
        // Set up proper session handling
        if (!session_id() && session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        
        // Initialize WooCommerce in test mode (check if functions exist first)
        if (function_exists('WC') && class_exists('WC_Cart') && class_exists('WC_Session_Handler')) {
            try {
                // Get WooCommerce instance
                $wc = WC();
                
                // Use reflection to check and set properties to avoid deprecation warnings
                $reflection = new \ReflectionObject($wc);
                
                // Check and set cart property
                if (!$reflection->hasProperty('cart') || !$wc->cart) {
                    // For PHP 8.2+, we need to properly initialize the property
                    // WooCommerce should already have these properties defined
                    if (method_exists($wc, 'initialize_cart')) {
                        $wc->initialize_cart();
                    } else {
                        // Fallback: Use setter if available
                        if (property_exists($wc, 'cart')) {
                            $wc->cart = new \WC_Cart();
                        }
                    }
                }
                
                // Check and set session property
                if (!$reflection->hasProperty('session') || !$wc->session) {
                    // For PHP 8.2+, we need to properly initialize the property
                    // WooCommerce should already have these properties defined
                    if (method_exists($wc, 'initialize_session')) {
                        $wc->initialize_session();
                    } else {
                        // Fallback: Use setter if available
                        if (property_exists($wc, 'session')) {
                            $wc->session = new \WC_Session_Handler();
                            $wc->session->init();
                        }
                    }
                }
            } catch (\Exception $e) {
                // Ignore WooCommerce initialization errors in tests
                error_log('WooCommerce test initialization failed: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Clean up test environment after tests
     */
    public static function cleanup(): void
    {
        // Clear all filters and actions added by this bootstrap
        remove_all_filters('woo_ai_assistant_rate_limit_check');
        remove_all_filters('woo_ai_assistant_rate_limiter_instance');
        remove_all_filters('pre_http_request');
        remove_all_filters('woo_ai_assistant_intermediate_server_client');
        remove_all_filters('woo_ai_assistant_ai_manager');
        remove_all_filters('woo_ai_assistant_license_manager');
        
        // Clear global test data
        global $mock_options, $mock_transients, $mock_actions, $conversation_session_map;
        $mock_options = [];
        $mock_transients = [];
        $mock_actions = [];
        $conversation_session_map = [];
        
        // Clear WordPress caches
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }
}

// Initialize test bootstrap
TestBootstrap::initialize();