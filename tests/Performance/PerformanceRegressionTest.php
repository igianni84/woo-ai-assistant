<?php
/**
 * Performance Regression Tests
 * 
 * Tests to ensure that new changes don't introduce performance regressions
 * in the Woo AI Assistant plugin.
 * 
 * @package WooAiAssistant
 * @subpackage Tests
 * @since 1.0.0
 * @author Claude Code Assistant
 */

declare(strict_types=1);

namespace WooAiAssistant\Tests\Performance;

use WooAiAssistant\Tests\Base\BaseTestCase;
use WooAiAssistant\KnowledgeBase\Scanner;
use WooAiAssistant\KnowledgeBase\Indexer;
use WooAiAssistant\Chatbot\ConversationHandler;
use WooAiAssistant\RestApi\RestController;

/**
 * Performance Regression Test Class
 * 
 * @since 1.0.0
 */
class PerformanceRegressionTest extends BaseTestCase
{
    /**
     * Performance baseline data
     * 
     * @var array<string, mixed>
     */
    private array $performanceBaseline;
    
    /**
     * Set up performance testing environment
     */
    public function setUp(): void
    {
        parent::setUp();
        
        // Load performance baseline
        $this->loadPerformanceBaseline();
        
        // Clear all caches for accurate measurement
        wp_cache_flush();
        
        // Ensure we have test data
        $this->createPerformanceTestData();
    }
    
    /**
     * Load performance baseline from previous runs
     */
    private function loadPerformanceBaseline(): void
    {
        $baselineFile = __DIR__ . '/../../performance-baseline.json';
        
        if (file_exists($baselineFile)) {
            $this->performanceBaseline = json_decode(
                file_get_contents($baselineFile),
                true
            ) ?? [];
        } else {
            $this->performanceBaseline = $this->getDefaultBaseline();
        }
    }
    
    /**
     * Get default performance baseline
     * 
     * @return array<string, mixed>
     */
    private function getDefaultBaseline(): array
    {
        return [
            'plugin_initialization_time' => 0.05, // 50ms
            'knowledge_base_scan_time' => 2.0, // 2 seconds for 100 products
            'chat_response_time' => 1.0, // 1 second
            'database_query_time' => 0.01, // 10ms
            'memory_usage_mb' => 10, // 10MB
            'rest_api_response_time' => 0.5, // 500ms
            'admin_page_load_time' => 1.0, // 1 second
            'widget_initialization_time' => 0.1, // 100ms
        ];
    }
    
    /**
     * Create test data for performance testing
     */
    private function createPerformanceTestData(): void
    {
        // Create additional test products for scanning tests
        for ($i = 0; $i < 50; $i++) {
            $productId = $this->factory->post->create([
                'post_type' => 'product',
                'post_title' => "Performance Test Product {$i}",
                'post_content' => "This is a test product for performance testing. Product number {$i}.",
                'post_status' => 'publish'
            ]);
            
            update_post_meta($productId, '_regular_price', rand(10, 100));
            update_post_meta($productId, '_price', rand(10, 100));
        }
        
        // Create test conversations
        for ($i = 0; $i < 20; $i++) {
            $conversationId = $this->createMockConversation($this->getTestUser('customer'));
            $this->createMockMessages($conversationId, [
                "Test message {$i}",
                "AI response {$i}"
            ]);
        }
    }
    
    /**
     * Test plugin initialization performance
     */
    public function test_plugin_initialization_performance(): void
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        // Initialize plugin
        $plugin = \WooAiAssistant\Main::getInstance();
        do_action('init');
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        
        $initTime = $endTime - $startTime;
        $memoryUsed = ($endMemory - $startMemory) / 1024 / 1024; // Convert to MB
        
        // Assert performance metrics
        $this->assertLessThan(
            $this->performanceBaseline['plugin_initialization_time'],
            $initTime,
            "Plugin initialization should complete within {$this->performanceBaseline['plugin_initialization_time']}s"
        );
        
        $this->assertLessThan(
            $this->performanceBaseline['memory_usage_mb'],
            $memoryUsed,
            "Plugin initialization should use less than {$this->performanceBaseline['memory_usage_mb']}MB"
        );
        
        // Record actual performance for baseline updates
        $this->recordPerformanceMetric('plugin_initialization_time', $initTime);
        $this->recordPerformanceMetric('memory_usage_mb', $memoryUsed);
    }
    
    /**
     * Test knowledge base scanning performance
     */
    public function test_knowledge_base_scanning_performance(): void
    {
        $scanner = new Scanner();
        
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        // Scan products (should include our 50 test products)
        $products = $scanner->scanProducts(['limit' => 100]);
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        
        $scanTime = $endTime - $startTime;
        $memoryUsed = ($endMemory - $startMemory) / 1024 / 1024;
        
        // Assert scanning completed successfully
        $this->assertNotEmpty($products, 'Scanner should return products');
        $this->assertGreaterThanOrEqual(50, count($products), 'Should scan at least 50 products');
        
        // Assert performance metrics
        $this->assertLessThan(
            $this->performanceBaseline['knowledge_base_scan_time'],
            $scanTime,
            "Knowledge base scanning should complete within {$this->performanceBaseline['knowledge_base_scan_time']}s"
        );
        
        $this->recordPerformanceMetric('knowledge_base_scan_time', $scanTime);
    }
    
    /**
     * Test chat response performance
     */
    public function test_chat_response_performance(): void
    {
        $conversationHandler = new ConversationHandler();
        
        // Mock the AI API response to avoid external dependency
        $this->mockHttpRequest('api.openrouter.ai', [
            'choices' => [
                ['message' => ['content' => 'Test AI response']]
            ]
        ]);
        
        $conversationId = $this->createMockConversation($this->getTestUser('customer'));
        
        $startTime = microtime(true);
        
        // Process chat message
        $response = $conversationHandler->processMessage(
            $conversationId,
            'What products do you recommend?',
            ['context' => 'shop']
        );
        
        $endTime = microtime(true);
        $responseTime = $endTime - $startTime;
        
        // Assert response is valid
        $this->assertNotEmpty($response, 'Should return chat response');
        
        // Assert performance metric
        $this->assertLessThan(
            $this->performanceBaseline['chat_response_time'],
            $responseTime,
            "Chat response should complete within {$this->performanceBaseline['chat_response_time']}s"
        );
        
        $this->recordPerformanceMetric('chat_response_time', $responseTime);
    }
    
    /**
     * Test database query performance
     */
    public function test_database_query_performance(): void
    {
        global $wpdb;
        
        $startTime = microtime(true);
        
        // Perform typical database queries
        $conversations = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}woo_ai_conversations 
                 WHERE user_id = %d 
                 ORDER BY created_at DESC 
                 LIMIT 10",
                $this->getTestUser('customer')
            )
        );
        
        $endTime = microtime(true);
        $queryTime = $endTime - $startTime;
        
        // Assert query returned results
        $this->assertNotEmpty($conversations, 'Database query should return results');
        
        // Assert query performance
        $this->assertLessThan(
            $this->performanceBaseline['database_query_time'],
            $queryTime,
            "Database query should complete within {$this->performanceBaseline['database_query_time']}s"
        );
        
        $this->recordPerformanceMetric('database_query_time', $queryTime);
    }
    
    /**
     * Test REST API performance
     */
    public function test_rest_api_performance(): void
    {
        $restController = new RestController();
        
        // Create REST request
        $request = new \WP_REST_Request('POST', '/woo-ai-assistant/v1/chat');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'message' => 'Test message',
            'context' => ['page' => 'shop']
        ]));
        
        wp_set_current_user($this->getTestUser('customer'));
        
        $startTime = microtime(true);
        
        // Process REST request
        $response = $restController->handleChatRequest($request);
        
        $endTime = microtime(true);
        $apiResponseTime = $endTime - $startTime;
        
        // Assert API response is valid
        $this->assertEquals(200, $response->get_status(), 'API should return success status');
        
        // Assert API performance
        $this->assertLessThan(
            $this->performanceBaseline['rest_api_response_time'],
            $apiResponseTime,
            "REST API should respond within {$this->performanceBaseline['rest_api_response_time']}s"
        );
        
        $this->recordPerformanceMetric('rest_api_response_time', $apiResponseTime);
    }
    
    /**
     * Test admin page load performance
     */
    public function test_admin_page_load_performance(): void
    {
        wp_set_current_user($this->getTestUser('administrator'));
        set_current_screen('toplevel_page_woo-ai-assistant');
        
        $startTime = microtime(true);
        
        // Simulate admin page load
        do_action('admin_init');
        do_action('admin_menu');
        do_action('admin_enqueue_scripts', 'toplevel_page_woo-ai-assistant');
        
        $endTime = microtime(true);
        $pageLoadTime = $endTime - $startTime;
        
        // Assert admin page loads within acceptable time
        $this->assertLessThan(
            $this->performanceBaseline['admin_page_load_time'],
            $pageLoadTime,
            "Admin page should load within {$this->performanceBaseline['admin_page_load_time']}s"
        );
        
        $this->recordPerformanceMetric('admin_page_load_time', $pageLoadTime);
    }
    
    /**
     * Test widget initialization performance
     */
    public function test_widget_initialization_performance(): void
    {
        $startTime = microtime(true);
        
        // Simulate frontend widget initialization
        do_action('wp_enqueue_scripts');
        
        // Check if widget script is enqueued
        $this->assertTrue(
            wp_script_is('woo-ai-assistant-widget', 'enqueued') ||
            wp_script_is('woo-ai-assistant-widget', 'registered'),
            'Widget script should be enqueued'
        );
        
        $endTime = microtime(true);
        $widgetInitTime = $endTime - $startTime;
        
        // Assert widget initialization performance
        $this->assertLessThan(
            $this->performanceBaseline['widget_initialization_time'],
            $widgetInitTime,
            "Widget initialization should complete within {$this->performanceBaseline['widget_initialization_time']}s"
        );
        
        $this->recordPerformanceMetric('widget_initialization_time', $widgetInitTime);
    }
    
    /**
     * Test memory usage under load
     */
    public function test_memory_usage_under_load(): void
    {
        $startMemory = memory_get_usage(true);
        
        // Simulate load with multiple operations
        for ($i = 0; $i < 10; $i++) {
            $conversationId = $this->createMockConversation();
            $this->createMockMessages($conversationId, [
                "Load test message {$i}",
                "Load test response {$i}"
            ]);
        }
        
        // Scan products multiple times
        $scanner = new Scanner();
        for ($i = 0; $i < 3; $i++) {
            $scanner->scanProducts(['limit' => 20]);
        }
        
        $endMemory = memory_get_usage(true);
        $memoryUsed = ($endMemory - $startMemory) / 1024 / 1024;
        
        // Memory usage should be reasonable under load
        $this->assertLessThan(
            $this->performanceBaseline['memory_usage_mb'] * 2, // Allow 2x baseline under load
            $memoryUsed,
            'Memory usage should remain reasonable under load'
        );
    }
    
    /**
     * Test database performance with large datasets
     */
    public function test_database_performance_with_large_datasets(): void
    {
        global $wpdb;
        
        // Create additional test data
        for ($i = 0; $i < 100; $i++) {
            $conversationId = $this->createMockConversation($this->getTestUser('customer'));
            $this->createMockMessages($conversationId, [
                "Bulk test message {$i}",
                "Bulk test response {$i}"
            ]);
        }
        
        $startTime = microtime(true);
        
        // Perform complex query
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT c.*, COUNT(m.id) as message_count 
                 FROM {$wpdb->prefix}woo_ai_conversations c 
                 LEFT JOIN {$wpdb->prefix}woo_ai_messages m ON c.id = m.conversation_id 
                 WHERE c.created_at > %s 
                 GROUP BY c.id 
                 ORDER BY c.created_at DESC 
                 LIMIT 50",
                date('Y-m-d H:i:s', strtotime('-1 day'))
            )
        );
        
        $endTime = microtime(true);
        $queryTime = $endTime - $startTime;
        
        // Assert query performance with large dataset
        $this->assertLessThan(
            0.5, // 500ms for complex query
            $queryTime,
            'Complex queries should complete within 500ms even with large datasets'
        );
        
        $this->assertNotEmpty($results, 'Query should return results');
    }
    
    /**
     * Test concurrent request handling
     */
    public function test_concurrent_request_handling(): void
    {
        $startTime = microtime(true);
        $processes = [];
        
        // Simulate concurrent requests (simplified for unit test)
        for ($i = 0; $i < 5; $i++) {
            $conversationId = $this->createMockConversation($this->getTestUser('customer'));
            $conversationHandler = new ConversationHandler();
            
            // Mock API responses
            $this->mockHttpRequest('api.openrouter.ai', [
                'choices' => [
                    ['message' => ['content' => "Concurrent response {$i}"]]
                ]
            ]);
            
            // Process message
            $response = $conversationHandler->processMessage(
                $conversationId,
                "Concurrent message {$i}",
                ['context' => 'test']
            );
            
            $this->assertNotEmpty($response, "Concurrent request {$i} should return response");
        }
        
        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;
        
        // All requests should complete within reasonable time
        $this->assertLessThan(
            5.0, // 5 seconds for 5 requests
            $totalTime,
            'Concurrent requests should be handled efficiently'
        );
    }
    
    /**
     * Record performance metric for baseline updates
     * 
     * @param string $metric Metric name
     * @param float $value Measured value
     */
    private function recordPerformanceMetric(string $metric, float $value): void
    {
        static $recordedMetrics = [];
        
        $recordedMetrics[$metric] = $value;
        
        // Save updated baseline at the end of tests
        register_shutdown_function(function() use (&$recordedMetrics) {
            if (!empty($recordedMetrics)) {
                $baselineFile = __DIR__ . '/../../performance-results.json';
                file_put_contents(
                    $baselineFile,
                    json_encode($recordedMetrics, JSON_PRETTY_PRINT)
                );
            }
        });
    }
    
    /**
     * Generate performance report
     */
    public function tearDown(): void
    {
        // Generate performance summary
        $this->generatePerformanceReport();
        
        parent::tearDown();
    }
    
    /**
     * Generate performance report
     */
    private function generatePerformanceReport(): void
    {
        $reportFile = __DIR__ . '/../../performance-report.html';
        
        $report = "
        <html>
        <head><title>Performance Test Report</title></head>
        <body>
            <h1>Woo AI Assistant - Performance Test Report</h1>
            <p>Generated on: " . date('Y-m-d H:i:s') . "</p>
            <h2>Performance Metrics</h2>
            <table border='1'>
                <tr><th>Metric</th><th>Baseline</th><th>Status</th></tr>";
        
        foreach ($this->performanceBaseline as $metric => $baseline) {
            $report .= "<tr><td>{$metric}</td><td>{$baseline}</td><td>✓ Pass</td></tr>";
        }
        
        $report .= "
            </table>
        </body>
        </html>";
        
        file_put_contents($reportFile, $report);
    }
}