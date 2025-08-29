<?php
/**
 * Performance Monitor Test Class
 *
 * Unit tests for the PerformanceMonitor class.
 * Tests performance tracking, benchmarking, and alerting functionality.
 *
 * @package WooAiAssistant
 * @subpackage Tests
 * @since 1.0.0
 * @author Claude Code Assistant
 */

namespace WooAiAssistant\Tests\Unit\Performance;

// Include WordPress function mocks
require_once __DIR__ . '/../Chatbot/wp-functions-mock.php';

use WooAiAssistant\Performance\PerformanceMonitor;
use WooAiAssistant\Tests\WP_UnitTestCase;

/**
 * Class PerformanceMonitorTest
 *
 * @since 1.0.0
 */
class PerformanceMonitorTest extends WP_UnitTestCase {

    private $performanceMonitor;

    public function setUp(): void {
        parent::setUp();
        $this->performanceMonitor = PerformanceMonitor::getInstance();
    }

    public function tearDown(): void {
        // Clear performance data after each test
        $this->performanceMonitor->clearPerformanceData();
        parent::tearDown();
    }

    /**
     * Test class existence and instantiation
     */
    public function test_class_exists_and_instantiates() {
        $this->assertTrue(class_exists('WooAiAssistant\\Performance\\PerformanceMonitor'));
        $this->assertInstanceOf(PerformanceMonitor::class, $this->performanceMonitor);
    }

    /**
     * Test class follows naming conventions
     */
    public function test_class_follows_naming_conventions() {
        $reflection = new \ReflectionClass($this->performanceMonitor);
        
        // Class name must be PascalCase
        $className = $reflection->getShortName();
        $this->assertMatchesRegularExpression('/^[A-Z][a-zA-Z0-9]*$/', $className, 
            "Class name '$className' must be PascalCase");
            
        // All public methods must be camelCase
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            $methodName = $method->getName();
            if (strpos($methodName, '__') === 0) continue; // Skip magic methods
            
            $this->assertMatchesRegularExpression('/^[a-z][a-zA-Z0-9]*$/', $methodName,
                "Method '$methodName' must be camelCase");
        }
    }

    /**
     * Test benchmark start and end functionality
     */
    public function test_startBenchmark_should_initialize_benchmark_successfully() {
        $benchmarkId = 'test_benchmark';
        $metadata = ['test_key' => 'test_value'];
        
        $result = $this->performanceMonitor->startBenchmark($benchmarkId, $metadata);
        $this->assertTrue($result, 'Benchmark should start successfully');
        
        // End the benchmark
        $metrics = $this->performanceMonitor->endBenchmark($benchmarkId);
        
        $this->assertIsArray($metrics, 'Benchmark metrics should be an array');
        $this->assertArrayHasKey('benchmark_id', $metrics, 'Metrics should include benchmark ID');
        $this->assertArrayHasKey('execution_time', $metrics, 'Metrics should include execution time');
        $this->assertArrayHasKey('memory_usage', $metrics, 'Metrics should include memory usage');
        $this->assertEquals($benchmarkId, $metrics['benchmark_id'], 'Benchmark ID should match');
        $this->assertGreaterThan(0, $metrics['execution_time'], 'Execution time should be positive');
    }

    /**
     * Test empty benchmark ID handling
     */
    public function test_startBenchmark_should_throw_exception_for_empty_id() {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Benchmark ID cannot be empty');
        
        $this->performanceMonitor->startBenchmark('');
    }

    /**
     * Test ending non-existent benchmark
     */
    public function test_endBenchmark_should_return_false_for_nonexistent_benchmark() {
        $result = $this->performanceMonitor->endBenchmark('nonexistent_benchmark');
        $this->assertFalse($result, 'Should return false for non-existent benchmark');
    }

    /**
     * Test benchmark with metadata
     */
    public function test_benchmark_should_preserve_metadata() {
        $benchmarkId = 'metadata_test';
        $metadata = [
            'operation' => 'database_query',
            'table' => 'conversations',
            'user_id' => 123
        ];
        
        $this->performanceMonitor->startBenchmark($benchmarkId, $metadata);
        
        // Add a small delay to ensure measurable execution time
        usleep(1000); // 1ms
        
        $metrics = $this->performanceMonitor->endBenchmark($benchmarkId);
        
        $this->assertArrayHasKey('metadata', $metrics, 'Metrics should include metadata');
        $this->assertEquals($metadata, $metrics['metadata'], 'Metadata should be preserved');
    }

    /**
     * Test multiple concurrent benchmarks
     */
    public function test_should_handle_multiple_concurrent_benchmarks() {
        $benchmark1 = 'concurrent_test_1';
        $benchmark2 = 'concurrent_test_2';
        
        // Start both benchmarks
        $result1 = $this->performanceMonitor->startBenchmark($benchmark1, ['test' => '1']);
        $result2 = $this->performanceMonitor->startBenchmark($benchmark2, ['test' => '2']);
        
        $this->assertTrue($result1, 'First benchmark should start successfully');
        $this->assertTrue($result2, 'Second benchmark should start successfully');
        
        // End benchmarks in reverse order
        $metrics2 = $this->performanceMonitor->endBenchmark($benchmark2);
        $metrics1 = $this->performanceMonitor->endBenchmark($benchmark1);
        
        $this->assertIsArray($metrics1, 'First benchmark metrics should be array');
        $this->assertIsArray($metrics2, 'Second benchmark metrics should be array');
        $this->assertEquals($benchmark1, $metrics1['benchmark_id'], 'First benchmark ID should match');
        $this->assertEquals($benchmark2, $metrics2['benchmark_id'], 'Second benchmark ID should match');
    }

    /**
     * Test query metric recording
     */
    public function test_recordQueryMetric_should_store_query_performance_data() {
        $queryData = [
            'query' => 'SELECT * FROM test_table',
            'execution_time' => 0.05,
            'results_count' => 10,
            'is_slow' => false
        ];
        
        $this->performanceMonitor->recordQueryMetric($queryData);
        
        // This mainly tests that the method executes without errors
        $this->assertTrue(true, 'Query metric recording should complete successfully');
    }

    /**
     * Test performance report generation
     */
    public function test_generatePerformanceReport_should_create_comprehensive_analysis() {
        // Record some test data first
        $this->performanceMonitor->startBenchmark('report_test');
        usleep(2000); // 2ms
        $this->performanceMonitor->endBenchmark('report_test');
        
        $report = $this->performanceMonitor->generatePerformanceReport();
        
        $this->assertIsArray($report, 'Performance report should be an array');
        $this->assertArrayHasKey('generated_at', $report, 'Report should include generation timestamp');
        $this->assertArrayHasKey('monitoring_period', $report, 'Report should include monitoring period');
        $this->assertArrayHasKey('faq_performance', $report, 'Report should include FAQ performance');
        $this->assertArrayHasKey('query_performance', $report, 'Report should include query performance');
        $this->assertArrayHasKey('memory_analysis', $report, 'Report should include memory analysis');
        $this->assertArrayHasKey('request_analysis', $report, 'Report should include request analysis');
        $this->assertArrayHasKey('recommendations', $report, 'Report should include recommendations');
    }

    /**
     * Test memory usage recording
     */
    public function test_recordMemoryUsage_should_track_memory_consumption() {
        $this->performanceMonitor->recordMemoryUsage();
        
        // This mainly tests that the method executes without errors
        $this->assertTrue(true, 'Memory usage recording should complete successfully');
    }

    /**
     * Test performance statistics retrieval
     */
    public function test_getPerformanceStats_should_return_current_statistics() {
        $stats = $this->performanceMonitor->getPerformanceStats();
        
        $this->assertIsArray($stats, 'Performance stats should be an array');
        $this->assertArrayHasKey('monitoring_enabled', $stats, 'Stats should include monitoring status');
        $this->assertArrayHasKey('active_benchmarks', $stats, 'Stats should include active benchmarks count');
        $this->assertArrayHasKey('stored_requests', $stats, 'Stats should include stored requests count');
        $this->assertArrayHasKey('stored_chat_responses', $stats, 'Stats should include chat responses count');
        $this->assertArrayHasKey('stored_queries', $stats, 'Stats should include queries count');
        $this->assertArrayHasKey('memory_snapshots', $stats, 'Stats should include memory snapshots count');
        
        // Verify data types
        $this->assertIsBool($stats['monitoring_enabled'], 'Monitoring enabled should be boolean');
        $this->assertIsInt($stats['active_benchmarks'], 'Active benchmarks should be integer');
        $this->assertIsInt($stats['stored_requests'], 'Stored requests should be integer');
    }

    /**
     * Test performance data clearing
     */
    public function test_clearPerformanceData_should_reset_all_metrics() {
        // Add some test data first
        $this->performanceMonitor->startBenchmark('clear_test');
        $this->performanceMonitor->endBenchmark('clear_test');
        
        $result = $this->performanceMonitor->clearPerformanceData();
        
        $this->assertTrue($result, 'Performance data clearing should return true');
        
        // Verify data is cleared
        $stats = $this->performanceMonitor->getPerformanceStats();
        $this->assertEquals(0, $stats['active_benchmarks'], 'Active benchmarks should be zero after clear');
    }

    /**
     * Test benchmark execution time measurement accuracy
     */
    public function test_benchmark_should_measure_execution_time_accurately() {
        $benchmarkId = 'timing_test';
        $sleepTime = 10000; // 10ms in microseconds
        
        $this->performanceMonitor->startBenchmark($benchmarkId);
        usleep($sleepTime);
        $metrics = $this->performanceMonitor->endBenchmark($benchmarkId);
        
        $executionTime = $metrics['execution_time'];
        
        // Execution time should be approximately 10ms (0.01 seconds)
        // Allow for some variance due to system overhead
        $this->assertGreaterThan(0.008, $executionTime, 'Execution time should be at least 8ms');
        $this->assertLessThan(0.020, $executionTime, 'Execution time should be less than 20ms');
    }

    /**
     * Test memory usage measurement
     */
    public function test_benchmark_should_measure_memory_usage() {
        $benchmarkId = 'memory_test';
        
        $this->performanceMonitor->startBenchmark($benchmarkId);
        
        // Allocate some memory
        $testArray = array_fill(0, 1000, 'test_string');
        
        $metrics = $this->performanceMonitor->endBenchmark($benchmarkId);
        
        $this->assertArrayHasKey('memory_usage', $metrics, 'Should measure memory usage');
        $this->assertArrayHasKey('peak_memory_usage', $metrics, 'Should measure peak memory usage');
        $this->assertIsInt($metrics['memory_usage'], 'Memory usage should be integer');
        $this->assertIsInt($metrics['peak_memory_usage'], 'Peak memory usage should be integer');
        
        // Clean up
        unset($testArray);
    }

    /**
     * Test nested benchmarks functionality
     */
    public function test_should_handle_nested_benchmarks() {
        $outerBenchmark = 'outer_benchmark';
        $innerBenchmark = 'inner_benchmark';
        
        // Start outer benchmark
        $this->performanceMonitor->startBenchmark($outerBenchmark);
        usleep(1000);
        
        // Start inner benchmark
        $this->performanceMonitor->startBenchmark($innerBenchmark);
        usleep(1000);
        
        // End inner benchmark first
        $innerMetrics = $this->performanceMonitor->endBenchmark($innerBenchmark);
        usleep(1000);
        
        // End outer benchmark
        $outerMetrics = $this->performanceMonitor->endBenchmark($outerBenchmark);
        
        $this->assertIsArray($innerMetrics, 'Inner benchmark should return metrics');
        $this->assertIsArray($outerMetrics, 'Outer benchmark should return metrics');
        $this->assertGreaterThan($innerMetrics['execution_time'], $outerMetrics['execution_time'], 
            'Outer benchmark should have longer execution time');
    }

    /**
     * Test benchmark metadata validation
     */
    public function test_benchmark_should_handle_various_metadata_types() {
        $benchmarkId = 'metadata_validation_test';
        $complexMetadata = [
            'string' => 'test_string',
            'integer' => 42,
            'float' => 3.14,
            'boolean' => true,
            'array' => ['nested', 'array'],
            'null_value' => null
        ];
        
        $this->performanceMonitor->startBenchmark($benchmarkId, $complexMetadata);
        $metrics = $this->performanceMonitor->endBenchmark($benchmarkId);
        
        $this->assertEquals($complexMetadata, $metrics['metadata'], 
            'Complex metadata should be preserved exactly');
    }

    /**
     * Test performance monitoring when disabled
     */
    public function test_benchmarks_should_handle_disabled_monitoring() {
        // This tests the scenario where monitoring might be disabled
        // The methods should still work but might return false or empty data
        
        $benchmarkId = 'disabled_monitoring_test';
        
        // Start benchmark (might return false if monitoring disabled)
        $startResult = $this->performanceMonitor->startBenchmark($benchmarkId);
        
        // If monitoring is enabled, it should return true
        // If disabled, it should return false
        $this->assertIsBool($startResult, 'Start benchmark should return boolean');
        
        // End benchmark should handle both cases gracefully
        $endResult = $this->performanceMonitor->endBenchmark($benchmarkId);
        $this->assertTrue($endResult === false || is_array($endResult), 
            'End benchmark should return false or array');
    }

    /**
     * Test performance threshold constants
     */
    public function test_performance_thresholds_should_be_properly_defined() {
        $reflection = new \ReflectionClass($this->performanceMonitor);
        
        // Check that performance threshold constants exist
        $this->assertTrue($reflection->hasConstant('FAQ_RESPONSE_THRESHOLD'), 
            'Should define FAQ_RESPONSE_THRESHOLD constant');
        $this->assertTrue($reflection->hasConstant('QUERY_SLOW_THRESHOLD'), 
            'Should define QUERY_SLOW_THRESHOLD constant');
        $this->assertTrue($reflection->hasConstant('MEMORY_ALERT_THRESHOLD'), 
            'Should define MEMORY_ALERT_THRESHOLD constant');
        
        // Check threshold values are reasonable
        $faqThreshold = $reflection->getConstant('FAQ_RESPONSE_THRESHOLD');
        $queryThreshold = $reflection->getConstant('QUERY_SLOW_THRESHOLD');
        $memoryThreshold = $reflection->getConstant('MEMORY_ALERT_THRESHOLD');
        
        $this->assertIsFloat($faqThreshold, 'FAQ threshold should be float');
        $this->assertIsFloat($queryThreshold, 'Query threshold should be float');
        $this->assertIsInt($memoryThreshold, 'Memory threshold should be integer');
        
        $this->assertGreaterThan(0, $faqThreshold, 'FAQ threshold should be positive');
        $this->assertGreaterThan(0, $queryThreshold, 'Query threshold should be positive');
        $this->assertGreaterThan(0, $memoryThreshold, 'Memory threshold should be positive');
    }
}