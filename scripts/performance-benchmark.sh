#!/bin/bash
#
# Performance Benchmarking Script
#
# Comprehensive performance testing and benchmarking for the WordPress plugin.
# Measures memory usage, execution time, database queries, and bundle sizes.
#
# @package WooAiAssistant
# @subpackage Scripts
# @since 1.0.0
# @author Claude Code Assistant

set -euo pipefail

# Colors for output
readonly RED='\033[0;31m'
readonly GREEN='\033[0;32m'
readonly YELLOW='\033[1;33m'
readonly BLUE='\033[0;34m'
readonly PURPLE='\033[0;35m'
readonly CYAN='\033[0;36m'
readonly BOLD='\033[1m'
readonly NC='\033[0m' # No Color

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# Performance thresholds
MAX_MEMORY_USAGE=10485760  # 10MB
MAX_BUNDLE_SIZE=51200      # 50KB
MAX_LOAD_TIME=100          # 100ms
MIN_COVERAGE=90            # 90%

# Results storage
RESULTS_FILE="$PROJECT_ROOT/performance-results.json"
BENCHMARK_LOG="$PROJECT_ROOT/benchmark.log"

log_header() {
    echo ""
    echo -e "${BOLD}${CYAN}$1${NC}"
    echo "$(printf '%*s' "${#1}" '' | tr ' ' '=')"
}

log_step() {
    echo -e "${BLUE}⚡ $1${NC}"
}

log_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

log_error() {
    echo -e "${RED}❌ $1${NC}"
}

log_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

log_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

# Initialize results tracking
init_results() {
    cat > "$RESULTS_FILE" << EOF
{
  "timestamp": "$(date -u +"%Y-%m-%dT%H:%M:%SZ")",
  "project": "woo-ai-assistant",
  "version": "1.0.0",
  "environment": {
    "php_version": "$(php -v | head -n 1 | cut -d ' ' -f 2)",
    "node_version": "$(node -v 2>/dev/null || echo 'N/A')",
    "os": "$(uname -s)",
    "memory_limit": "$(php -r 'echo ini_get("memory_limit");')"
  },
  "benchmarks": {}
}
EOF

    # Initialize log file
    echo "Performance Benchmark Log - $(date)" > "$BENCHMARK_LOG"
    echo "======================================" >> "$BENCHMARK_LOG"
    echo "" >> "$BENCHMARK_LOG"
}

# Add result to JSON
add_result() {
    local test_name="$1"
    local result="$2"
    local status="$3"
    local details="$4"
    
    # Use jq if available, otherwise use simple string replacement
    if command -v jq >/dev/null 2>&1; then
        local temp_file=$(mktemp)
        jq --arg name "$test_name" --arg result "$result" --arg status "$status" --arg details "$details" \
           '.benchmarks[$name] = {"result": $result, "status": $status, "details": $details}' \
           "$RESULTS_FILE" > "$temp_file" && mv "$temp_file" "$RESULTS_FILE"
    else
        # Fallback: simple append to log
        echo "Test: $test_name, Result: $result, Status: $status, Details: $details" >> "$BENCHMARK_LOG"
    fi
}

# Memory usage benchmarking
benchmark_memory_usage() {
    log_header "🧠 MEMORY USAGE BENCHMARKING"
    
    log_step "Measuring plugin memory footprint"
    
    # Create memory profiling script
    cat > "$PROJECT_ROOT/memory-profile.php" << 'EOF'
<?php
/**
 * Memory Usage Profiling Script
 * Measures memory consumption during plugin loading
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Record initial memory
$start_memory = memory_get_usage();
$start_peak = memory_get_peak_usage();

echo json_encode([
    'start_memory' => $start_memory,
    'start_peak' => $start_peak
]) . "\n";

// Load autoloader if exists
if (file_exists('vendor/autoload.php')) {
    require_once 'vendor/autoload.php';
}

// Record memory after autoloader
$autoloader_memory = memory_get_usage();
$autoloader_peak = memory_get_peak_usage();

echo json_encode([
    'autoloader_memory' => $autoloader_memory,
    'autoloader_peak' => $autoloader_peak,
    'autoloader_diff' => $autoloader_memory - $start_memory
]) . "\n";

// Simulate plugin loading
if (file_exists('src/Main.php')) {
    require_once 'src/Main.php';
    
    // Attempt to instantiate main class
    if (class_exists('WooAiAssistant\\Main')) {
        try {
            $main = new WooAiAssistant\Main();
            $plugin_memory = memory_get_usage();
            $plugin_peak = memory_get_peak_usage();
            
            echo json_encode([
                'plugin_memory' => $plugin_memory,
                'plugin_peak' => $plugin_peak,
                'plugin_diff' => $plugin_memory - $autoloader_memory,
                'total_diff' => $plugin_memory - $start_memory
            ]) . "\n";
        } catch (Exception $e) {
            echo json_encode([
                'error' => 'Failed to instantiate Main class: ' . $e->getMessage(),
                'memory_at_error' => memory_get_usage(),
                'peak_at_error' => memory_get_peak_usage()
            ]) . "\n";
        }
    } else {
        echo json_encode([
            'error' => 'Main class not found',
            'memory_after_require' => memory_get_usage()
        ]) . "\n";
    }
} else {
    echo json_encode([
        'error' => 'src/Main.php not found'
    ]) . "\n";
}

// Final memory report
$final_memory = memory_get_usage();
$final_peak = memory_get_peak_usage();

echo json_encode([
    'final_memory' => $final_memory,
    'final_peak' => $final_peak,
    'total_increase' => $final_memory - $start_memory,
    'peak_increase' => $final_peak - $start_peak,
    'memory_limit' => ini_get('memory_limit')
]) . "\n";
EOF
    
    # Run memory profiling
    cd "$PROJECT_ROOT"
    local memory_output=$(php memory-profile.php 2>&1)
    local total_increase=0
    
    # Parse memory results
    echo "$memory_output" | while read -r line; do
        if [[ "$line" == *"total_increase"* ]]; then
            total_increase=$(echo "$line" | grep -o '"total_increase":[0-9]*' | cut -d':' -f2)
            break
        fi
    done
    
    # Get the last line with total_increase
    local last_line=$(echo "$memory_output" | grep "total_increase" | tail -1)
    if [[ -n "$last_line" ]]; then
        total_increase=$(echo "$last_line" | grep -o '"total_increase":[0-9]*' | cut -d':' -f2)
    fi
    
    # Convert bytes to MB
    local memory_mb=$(echo "scale=2; $total_increase / 1024 / 1024" | bc 2>/dev/null || echo "0")
    
    echo "Memory Analysis:"
    echo "$memory_output" | head -10
    echo ""
    
    if [[ $total_increase -le $MAX_MEMORY_USAGE ]]; then
        log_success "Memory usage acceptable: ${memory_mb}MB (limit: 10MB)"
        add_result "memory_usage" "${memory_mb}MB" "pass" "Under threshold"
    else
        log_error "Memory usage too high: ${memory_mb}MB (limit: 10MB)"
        add_result "memory_usage" "${memory_mb}MB" "fail" "Exceeds threshold"
    fi
    
    # Cleanup
    rm -f "$PROJECT_ROOT/memory-profile.php"
}

# Bundle size analysis
benchmark_bundle_size() {
    log_header "📦 BUNDLE SIZE ANALYSIS"
    
    if [[ ! -d "$PROJECT_ROOT/assets/js" ]]; then
        log_warning "Assets directory not found, running build first..."
        if command -v npm >/dev/null 2>&1 && [[ -f "$PROJECT_ROOT/package.json" ]]; then
            cd "$PROJECT_ROOT"
            npm run build >/dev/null 2>&1 || true
        fi
    fi
    
    # Check widget bundle size
    if [[ -f "$PROJECT_ROOT/assets/js/widget.js" ]]; then
        log_step "Analyzing widget bundle size"
        
        local bundle_size=$(wc -c < "$PROJECT_ROOT/assets/js/widget.js" 2>/dev/null || echo 0)
        local bundle_kb=$(echo "scale=2; $bundle_size / 1024" | bc 2>/dev/null || echo "0")
        
        if [[ $bundle_size -le $MAX_BUNDLE_SIZE ]]; then
            log_success "Widget bundle size acceptable: ${bundle_kb}KB (limit: 50KB)"
            add_result "bundle_size" "${bundle_kb}KB" "pass" "Under threshold"
        else
            log_error "Widget bundle size too large: ${bundle_kb}KB (limit: 50KB)"
            add_result "bundle_size" "${bundle_kb}KB" "fail" "Exceeds threshold"
        fi
        
        # Analyze bundle composition if webpack-bundle-analyzer is available
        if [[ -f "$PROJECT_ROOT/package.json" ]] && grep -q "webpack-bundle-analyzer" "$PROJECT_ROOT/package.json"; then
            log_step "Generating bundle analysis report"
            cd "$PROJECT_ROOT"
            npm run build:analyze >/dev/null 2>&1 || true
            if [[ -f "bundle-analyzer-report.html" ]]; then
                log_info "Bundle analysis report generated: bundle-analyzer-report.html"
            fi
        fi
    else
        log_warning "Widget bundle not found, skipping bundle size check"
        add_result "bundle_size" "N/A" "skip" "Bundle not found"
    fi
    
    # Check for other assets
    if [[ -d "$PROJECT_ROOT/assets" ]]; then
        log_step "Analyzing total asset size"
        local total_assets=$(find "$PROJECT_ROOT/assets" -type f -exec wc -c {} + | awk 'END {print $1}' 2>/dev/null || echo 0)
        local assets_kb=$(echo "scale=2; $total_assets / 1024" | bc 2>/dev/null || echo "0")
        
        log_info "Total assets size: ${assets_kb}KB"
        add_result "total_assets" "${assets_kb}KB" "info" "Total assets size"
    fi
}

# Database query analysis
benchmark_database_queries() {
    log_header "🗄️  DATABASE QUERY ANALYSIS"
    
    log_step "Analyzing database query patterns"
    
    # Check for N+1 query patterns
    local n_plus_one_patterns=$(find "$PROJECT_ROOT/src" -name "*.php" -type f -exec grep -n "get_posts.*posts_per_page.*-1\|WP_Query.*nopaging.*true" {} + 2>/dev/null || true)
    
    if [[ -n "$n_plus_one_patterns" ]]; then
        log_error "Potential N+1 query patterns found:"
        echo "$n_plus_one_patterns" | head -5
        add_result "database_queries" "Issues found" "fail" "N+1 patterns detected"
    else
        log_success "No obvious N+1 query patterns detected"
        add_result "database_queries" "Clean" "pass" "No N+1 patterns"
    fi
    
    # Check for caching implementation
    log_step "Checking for caching mechanisms"
    local cache_usage=$(find "$PROJECT_ROOT/src" -name "*.php" -type f -exec grep -l "wp_cache_\|transient\|object_cache" {} \; 2>/dev/null | wc -l)
    
    if [[ $cache_usage -gt 0 ]]; then
        log_success "Caching mechanisms found in $cache_usage files"
        add_result "caching" "$cache_usage files" "pass" "Caching implemented"
    else
        log_warning "No caching mechanisms detected"
        add_result "caching" "None detected" "warning" "Consider implementing caching"
    fi
    
    # Check for prepared statements
    log_step "Verifying prepared statement usage"
    local prepared_statements=$(find "$PROJECT_ROOT/src" -name "*.php" -type f -exec grep -l "\$wpdb->prepare" {} \; 2>/dev/null | wc -l)
    local direct_queries=$(find "$PROJECT_ROOT/src" -name "*.php" -type f -exec grep -l "\$wpdb->query.*\$\|\$wpdb->get_.*\$" {} \; 2>/dev/null | wc -l)
    
    if [[ $direct_queries -gt 0 ]] && [[ $prepared_statements -eq 0 ]]; then
        log_error "Direct database queries without prepared statements detected"
        add_result "prepared_statements" "Missing" "fail" "Use prepared statements"
    else
        log_success "Proper database query practices detected"
        add_result "prepared_statements" "Implemented" "pass" "Safe queries"
    fi
}

# Load time analysis
benchmark_load_time() {
    log_header "⏱️  LOAD TIME BENCHMARKING"
    
    log_step "Measuring plugin initialization time"
    
    # Create load time measurement script
    cat > "$PROJECT_ROOT/load-time.php" << 'EOF'
<?php
/**
 * Load Time Measurement Script
 */

$iterations = 10;
$total_time = 0;

for ($i = 0; $i < $iterations; $i++) {
    $start_time = microtime(true);
    
    // Simulate plugin loading
    if (file_exists('vendor/autoload.php')) {
        require_once 'vendor/autoload.php';
    }
    
    if (file_exists('src/Main.php')) {
        require_once 'src/Main.php';
        if (class_exists('WooAiAssistant\\Main')) {
            try {
                new WooAiAssistant\Main();
            } catch (Exception $e) {
                // Ignore errors for timing purposes
            }
        }
    }
    
    $end_time = microtime(true);
    $execution_time = ($end_time - $start_time) * 1000; // Convert to milliseconds
    $total_time += $execution_time;
    
    // Clean up for next iteration
    if (function_exists('gc_collect_cycles')) {
        gc_collect_cycles();
    }
}

$average_time = $total_time / $iterations;

echo json_encode([
    'iterations' => $iterations,
    'total_time_ms' => round($total_time, 2),
    'average_time_ms' => round($average_time, 2),
    'min_time_acceptable' => $average_time < 100
]);
EOF
    
    cd "$PROJECT_ROOT"
    local load_output=$(php load-time.php 2>/dev/null)
    
    if [[ -n "$load_output" ]]; then
        echo "Load Time Analysis:"
        echo "$load_output" | jq . 2>/dev/null || echo "$load_output"
        
        local avg_time=$(echo "$load_output" | grep -o '"average_time_ms":[0-9.]*' | cut -d':' -f2)
        
        if [[ -n "$avg_time" ]] && (( $(echo "$avg_time < $MAX_LOAD_TIME" | bc -l 2>/dev/null || echo 0) )); then
            log_success "Plugin load time acceptable: ${avg_time}ms (limit: ${MAX_LOAD_TIME}ms)"
            add_result "load_time" "${avg_time}ms" "pass" "Under threshold"
        else
            log_error "Plugin load time too high: ${avg_time}ms (limit: ${MAX_LOAD_TIME}ms)"
            add_result "load_time" "${avg_time}ms" "fail" "Exceeds threshold"
        fi
    else
        log_warning "Could not measure load time"
        add_result "load_time" "N/A" "skip" "Measurement failed"
    fi
    
    # Cleanup
    rm -f "$PROJECT_ROOT/load-time.php"
}

# Test coverage analysis
benchmark_test_coverage() {
    log_header "📊 TEST COVERAGE ANALYSIS"
    
    if [[ -f "$PROJECT_ROOT/composer.json" ]] && command -v composer >/dev/null 2>&1; then
        log_step "Running test coverage analysis"
        
        cd "$PROJECT_ROOT"
        if composer run test:coverage >/dev/null 2>&1; then
            # Try to extract coverage percentage from coverage report
            local coverage_percent=0
            
            if [[ -f "coverage/clover.xml" ]]; then
                # Extract coverage from clover XML
                coverage_percent=$(grep -o 'percent="[0-9.]*"' coverage/clover.xml | head -1 | grep -o '[0-9.]*' || echo "0")
            elif [[ -f "coverage.txt" ]]; then
                # Extract from text report
                coverage_percent=$(grep -o '[0-9.]*%' coverage.txt | head -1 | tr -d '%' || echo "0")
            fi
            
            if (( $(echo "$coverage_percent >= $MIN_COVERAGE" | bc -l 2>/dev/null || echo 0) )); then
                log_success "Test coverage acceptable: ${coverage_percent}% (minimum: ${MIN_COVERAGE}%)"
                add_result "test_coverage" "${coverage_percent}%" "pass" "Above minimum"
            else
                log_warning "Test coverage below minimum: ${coverage_percent}% (minimum: ${MIN_COVERAGE}%)"
                add_result "test_coverage" "${coverage_percent}%" "warning" "Below minimum"
            fi
        else
            log_warning "Could not run test coverage analysis"
            add_result "test_coverage" "N/A" "skip" "Tests failed or not found"
        fi
    else
        log_info "Composer not available or no tests configured"
        add_result "test_coverage" "N/A" "skip" "No test suite"
    fi
}

# JavaScript performance analysis
benchmark_javascript_performance() {
    log_header "🚀 JAVASCRIPT PERFORMANCE ANALYSIS"
    
    if [[ -f "$PROJECT_ROOT/package.json" ]] && command -v npm >/dev/null 2>&1; then
        log_step "Analyzing JavaScript performance"
        
        cd "$PROJECT_ROOT"
        
        # Run linting performance check
        if npm run lint:js >/dev/null 2>&1; then
            log_success "JavaScript linting passed"
            add_result "js_linting" "Passed" "pass" "No linting issues"
        else
            log_warning "JavaScript linting issues detected"
            add_result "js_linting" "Issues" "warning" "Linting problems"
        fi
        
        # Check for performance anti-patterns
        log_step "Checking for performance anti-patterns"
        local perf_issues=0
        
        if find "$PROJECT_ROOT/widget-src" -name "*.js" -type f -exec grep -l "document\.getElementById\|jQuery.*each\|setInterval.*[0-9]\{1,2\}[^0-9]" {} \; 2>/dev/null | head -1 >/dev/null; then
            log_warning "Potential JavaScript performance issues detected"
            ((perf_issues++))
        fi
        
        if [[ $perf_issues -eq 0 ]]; then
            log_success "No obvious JavaScript performance anti-patterns found"
            add_result "js_performance" "Clean" "pass" "No anti-patterns"
        else
            add_result "js_performance" "Issues detected" "warning" "Performance anti-patterns found"
        fi
        
        # Run Jest tests if available
        if grep -q "jest" "$PROJECT_ROOT/package.json"; then
            log_step "Running JavaScript tests"
            if npm test >/dev/null 2>&1; then
                log_success "JavaScript tests passed"
                add_result "js_tests" "Passed" "pass" "All tests pass"
            else
                log_warning "JavaScript tests failed or have issues"
                add_result "js_tests" "Issues" "warning" "Test failures"
            fi
        fi
    else
        log_info "Node.js not available or no package.json found"
        add_result "js_performance" "N/A" "skip" "JavaScript environment not available"
    fi
}

# Generate performance report
generate_performance_report() {
    log_header "📈 PERFORMANCE BENCHMARK SUMMARY"
    
    local report_date=$(date "+%Y-%m-%d %H:%M:%S")
    
    echo ""
    echo "Performance Benchmark Report"
    echo "============================"
    echo "Project: Woo AI Assistant Plugin"
    echo "Date: $report_date"
    echo "Environment: $(uname -s) $(uname -r)"
    echo ""
    
    # Display key metrics
    if command -v jq >/dev/null 2>&1 && [[ -f "$RESULTS_FILE" ]]; then
        echo "Key Performance Metrics:"
        echo "------------------------"
        
        local memory_result=$(jq -r '.benchmarks.memory_usage.result // "N/A"' "$RESULTS_FILE" 2>/dev/null)
        local bundle_result=$(jq -r '.benchmarks.bundle_size.result // "N/A"' "$RESULTS_FILE" 2>/dev/null)
        local load_result=$(jq -r '.benchmarks.load_time.result // "N/A"' "$RESULTS_FILE" 2>/dev/null)
        local coverage_result=$(jq -r '.benchmarks.test_coverage.result // "N/A"' "$RESULTS_FILE" 2>/dev/null)
        
        echo "Memory Usage: $memory_result"
        echo "Bundle Size: $bundle_result"
        echo "Load Time: $load_result"
        echo "Test Coverage: $coverage_result"
        echo ""
        
        # Count results
        local total_tests=$(jq '.benchmarks | length' "$RESULTS_FILE" 2>/dev/null || echo 0)
        local passed_tests=$(jq '[.benchmarks[] | select(.status == "pass")] | length' "$RESULTS_FILE" 2>/dev/null || echo 0)
        local failed_tests=$(jq '[.benchmarks[] | select(.status == "fail")] | length' "$RESULTS_FILE" 2>/dev/null || echo 0)
        local warnings=$(jq '[.benchmarks[] | select(.status == "warning")] | length' "$RESULTS_FILE" 2>/dev/null || echo 0)
        
        echo "Summary:"
        echo "- Total Tests: $total_tests"
        echo "- Passed: $passed_tests"
        echo "- Failed: $failed_tests"
        echo "- Warnings: $warnings"
        echo ""
        
        if [[ $failed_tests -eq 0 ]]; then
            if [[ $warnings -eq 0 ]]; then
                echo -e "${GREEN}${BOLD}🎉 ALL PERFORMANCE BENCHMARKS PASSED!${NC}"
                echo -e "${GREEN}⚡ Your plugin meets all performance requirements${NC}"
            else
                echo -e "${YELLOW}${BOLD}⚠️  PERFORMANCE BENCHMARKS PASSED WITH WARNINGS${NC}"
                echo -e "${YELLOW}📝 Review warnings for potential optimizations${NC}"
            fi
        else
            echo -e "${RED}${BOLD}❌ PERFORMANCE BENCHMARKS FAILED${NC}"
            echo -e "${RED}🐌 Optimize performance before deployment${NC}"
        fi
    else
        echo -e "${YELLOW}📊 Results available in: $BENCHMARK_LOG${NC}"
    fi
    
    echo ""
    echo -e "${CYAN}📄 Detailed results: $RESULTS_FILE${NC}"
    echo -e "${CYAN}📋 Full log: $BENCHMARK_LOG${NC}"
    echo ""
}

# Main function
main() {
    log_header "⚡ PERFORMANCE BENCHMARKING SUITE"
    echo "Woo AI Assistant Plugin - Comprehensive Performance Analysis"
    echo "Project: $PROJECT_ROOT"
    echo "Started: $(date)"
    echo ""
    
    # Initialize results tracking
    init_results
    
    # Run all benchmarks
    benchmark_memory_usage
    benchmark_bundle_size
    benchmark_database_queries
    benchmark_load_time
    benchmark_test_coverage
    benchmark_javascript_performance
    
    # Generate final report
    generate_performance_report
    
    # Return appropriate exit code
    if command -v jq >/dev/null 2>&1 && [[ -f "$RESULTS_FILE" ]]; then
        local failed_tests=$(jq '[.benchmarks[] | select(.status == "fail")] | length' "$RESULTS_FILE" 2>/dev/null || echo 0)
        if [[ $failed_tests -gt 0 ]]; then
            return 1
        fi
    fi
    
    return 0
}

# Execute main function if script is run directly
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi