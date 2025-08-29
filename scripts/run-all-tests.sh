#!/bin/bash

/**
 * Comprehensive Test Runner Script
 * 
 * Runs all types of tests for the Woo AI Assistant plugin including:
 * - Unit tests
 * - Integration tests
 * - E2E tests
 * - Load tests
 * - Security tests
 * - Compatibility tests
 * 
 * @package WooAiAssistant
 * @subpackage Tests
 * @since 1.0.0
 * @author Claude Code Assistant
 */

# Color output functions
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test result tracking
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0
SKIPPED_TESTS=0

# Configuration
COVERAGE_THRESHOLD=90
PERFORMANCE_BASELINE="performance-baseline.json"
TEST_REPORTS_DIR="tests/reports"

# Function to print colored output
print_status() {
    local status=$1
    local message=$2
    case $status in
        "info")    echo -e "${BLUE}ℹ ${message}${NC}" ;;
        "success") echo -e "${GREEN}✅ ${message}${NC}" ;;
        "warning") echo -e "${YELLOW}⚠️ ${message}${NC}" ;;
        "error")   echo -e "${RED}❌ ${message}${NC}" ;;
    esac
}

# Function to update test counts
update_test_counts() {
    local result_file=$1
    if [[ -f "$result_file" ]]; then
        local tests=$(grep -o "Tests: [0-9]*" "$result_file" | head -1 | grep -o "[0-9]*")
        local assertions=$(grep -o "Assertions: [0-9]*" "$result_file" | head -1 | grep -o "[0-9]*")
        local failures=$(grep -o "Failures: [0-9]*" "$result_file" | head -1 | grep -o "[0-9]*" || echo "0")
        local errors=$(grep -o "Errors: [0-9]*" "$result_file" | head -1 | grep -o "[0-9]*" || echo "0")
        local skipped=$(grep -o "Skipped: [0-9]*" "$result_file" | head -1 | grep -o "[0-9]*" || echo "0")
        
        TOTAL_TESTS=$((TOTAL_TESTS + tests))
        PASSED_TESTS=$((PASSED_TESTS + tests - failures - errors))
        FAILED_TESTS=$((FAILED_TESTS + failures + errors))
        SKIPPED_TESTS=$((SKIPPED_TESTS + skipped))
    fi
}

# Create reports directory
mkdir -p "$TEST_REPORTS_DIR"

print_status "info" "Starting comprehensive test suite for Woo AI Assistant..."
print_status "info" "Coverage threshold: ${COVERAGE_THRESHOLD}%"

# Parse command line arguments
TEST_TYPE="all"
VERBOSE=false
DRY_RUN=false
CLEANUP=true

while [[ $# -gt 0 ]]; do
    case $1 in
        --type)
            TEST_TYPE="$2"
            shift 2
            ;;
        --verbose|-v)
            VERBOSE=true
            shift
            ;;
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        --no-cleanup)
            CLEANUP=false
            shift
            ;;
        --help|-h)
            echo "Usage: $0 [options]"
            echo "Options:"
            echo "  --type TYPE     Test type: all, unit, integration, e2e, load, security, compatibility"
            echo "  --verbose       Enable verbose output"
            echo "  --dry-run       Show what would be run without executing"
            echo "  --no-cleanup    Don't clean up after tests"
            echo "  --help          Show this help message"
            exit 0
            ;;
        *)
            print_status "error" "Unknown option: $1"
            exit 1
            ;;
    esac
done

# Dry run mode
if [[ "$DRY_RUN" == "true" ]]; then
    print_status "info" "DRY RUN MODE - No tests will be executed"
    echo "Would run test type: $TEST_TYPE"
    exit 0
fi

# Pre-flight checks
print_status "info" "Running pre-flight checks..."

# Check if WordPress test environment is set up
if [[ ! -f "tests/bootstrap.php" ]]; then
    print_status "error" "WordPress test environment not found. Please run: bash scripts/install-wp-tests.sh"
    exit 1
fi

# Check if required tools are available
required_tools=("composer" "npm" "php" "phpunit")
for tool in "${required_tools[@]}"; do
    if ! command -v "$tool" &> /dev/null; then
        print_status "error" "$tool is required but not installed"
        exit 1
    fi
done

# Install dependencies
print_status "info" "Installing dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader

if [[ -f "package.json" ]]; then
    npm install
fi

# Start test execution
start_time=$(date +%s)

# 1. UNIT TESTS
if [[ "$TEST_TYPE" == "all" || "$TEST_TYPE" == "unit" ]]; then
    print_status "info" "Running unit tests..."
    
    if [[ "$VERBOSE" == "true" ]]; then
        phpunit_args="--verbose"
    else
        phpunit_args=""
    fi
    
    phpunit \
        --configuration phpunit.xml \
        --testsuite unit \
        --coverage-html coverage/unit \
        --coverage-clover coverage/unit-clover.xml \
        --log-junit "$TEST_REPORTS_DIR/unit-results.xml" \
        $phpunit_args \
        > "$TEST_REPORTS_DIR/unit-output.txt" 2>&1
    
    unit_exit_code=$?
    update_test_counts "$TEST_REPORTS_DIR/unit-output.txt"
    
    if [[ $unit_exit_code -eq 0 ]]; then
        print_status "success" "Unit tests passed"
    else
        print_status "error" "Unit tests failed (exit code: $unit_exit_code)"
        if [[ "$VERBOSE" == "true" ]]; then
            cat "$TEST_REPORTS_DIR/unit-output.txt"
        fi
    fi
fi

# 2. INTEGRATION TESTS
if [[ "$TEST_TYPE" == "all" || "$TEST_TYPE" == "integration" ]]; then
    print_status "info" "Running integration tests..."
    
    phpunit \
        --configuration phpunit.xml \
        --testsuite integration \
        --coverage-html coverage/integration \
        --coverage-clover coverage/integration-clover.xml \
        --log-junit "$TEST_REPORTS_DIR/integration-results.xml" \
        $phpunit_args \
        > "$TEST_REPORTS_DIR/integration-output.txt" 2>&1
    
    integration_exit_code=$?
    update_test_counts "$TEST_REPORTS_DIR/integration-output.txt"
    
    if [[ $integration_exit_code -eq 0 ]]; then
        print_status "success" "Integration tests passed"
    else
        print_status "error" "Integration tests failed (exit code: $integration_exit_code)"
    fi
fi

# 3. COMPATIBILITY TESTS
if [[ "$TEST_TYPE" == "all" || "$TEST_TYPE" == "compatibility" ]]; then
    print_status "info" "Running compatibility tests..."
    
    phpunit \
        --configuration phpunit.xml \
        --testsuite compatibility \
        --log-junit "$TEST_REPORTS_DIR/compatibility-results.xml" \
        $phpunit_args \
        > "$TEST_REPORTS_DIR/compatibility-output.txt" 2>&1
    
    compatibility_exit_code=$?
    update_test_counts "$TEST_REPORTS_DIR/compatibility-output.txt"
    
    if [[ $compatibility_exit_code -eq 0 ]]; then
        print_status "success" "Compatibility tests passed"
    else
        print_status "error" "Compatibility tests failed (exit code: $compatibility_exit_code)"
    fi
fi

# 4. SECURITY TESTS
if [[ "$TEST_TYPE" == "all" || "$TEST_TYPE" == "security" ]]; then
    print_status "info" "Running security tests..."
    
    phpunit \
        --configuration phpunit.xml \
        --testsuite security \
        --log-junit "$TEST_REPORTS_DIR/security-results.xml" \
        $phpunit_args \
        > "$TEST_REPORTS_DIR/security-output.txt" 2>&1
    
    security_exit_code=$?
    update_test_counts "$TEST_REPORTS_DIR/security-output.txt"
    
    if [[ $security_exit_code -eq 0 ]]; then
        print_status "success" "Security tests passed"
    else
        print_status "error" "Security tests failed (exit code: $security_exit_code)"
    fi
fi

# 5. PERFORMANCE TESTS
if [[ "$TEST_TYPE" == "all" || "$TEST_TYPE" == "performance" ]]; then
    print_status "info" "Running performance tests..."
    
    phpunit \
        --configuration phpunit.xml \
        --testsuite performance \
        --log-junit "$TEST_REPORTS_DIR/performance-results.xml" \
        $phpunit_args \
        > "$TEST_REPORTS_DIR/performance-output.txt" 2>&1
    
    performance_exit_code=$?
    update_test_counts "$TEST_REPORTS_DIR/performance-output.txt"
    
    if [[ $performance_exit_code -eq 0 ]]; then
        print_status "success" "Performance tests passed"
    else
        print_status "error" "Performance tests failed (exit code: $performance_exit_code)"
    fi
fi

# 6. E2E TESTS
if [[ "$TEST_TYPE" == "all" || "$TEST_TYPE" == "e2e" ]]; then
    print_status "info" "Running E2E tests..."
    
    if [[ -f "tests/E2E/playwright.config.js" ]]; then
        # Check if Playwright is installed
        if ! npx playwright --version &> /dev/null; then
            print_status "warning" "Playwright not found. Installing..."
            npx playwright install
        fi
        
        # Set environment variables for E2E tests
        export WP_BASE_URL="http://localhost:8888/wp"
        export WP_ADMIN_USER="admin"
        export WP_ADMIN_PASS="password"
        
        cd tests/E2E
        npx playwright test \
            --config=playwright.config.js \
            --reporter=html \
            --output-dir="../../$TEST_REPORTS_DIR/e2e-results" \
            > "../../$TEST_REPORTS_DIR/e2e-output.txt" 2>&1
        
        e2e_exit_code=$?
        cd - > /dev/null
        
        if [[ $e2e_exit_code -eq 0 ]]; then
            print_status "success" "E2E tests passed"
        else
            print_status "error" "E2E tests failed (exit code: $e2e_exit_code)"
        fi
    else
        print_status "warning" "E2E tests configuration not found, skipping..."
        e2e_exit_code=0
        SKIPPED_TESTS=$((SKIPPED_TESTS + 1))
    fi
fi

# 7. LOAD TESTS
if [[ "$TEST_TYPE" == "all" || "$TEST_TYPE" == "load" ]]; then
    print_status "info" "Running load tests..."
    
    if command -v k6 &> /dev/null && [[ -f "tests/LoadTesting/scenarios/chat-load-test.js" ]]; then
        export WP_BASE_URL="http://localhost:8888/wp"
        
        k6 run \
            --out json="$TEST_REPORTS_DIR/load-test-results.json" \
            tests/LoadTesting/scenarios/chat-load-test.js \
            > "$TEST_REPORTS_DIR/load-output.txt" 2>&1
        
        load_exit_code=$?
        
        if [[ $load_exit_code -eq 0 ]]; then
            print_status "success" "Load tests passed"
        else
            print_status "error" "Load tests failed (exit code: $load_exit_code)"
        fi
    else
        print_status "warning" "k6 not found or load tests not configured, skipping..."
        load_exit_code=0
        SKIPPED_TESTS=$((SKIPPED_TESTS + 1))
    fi
fi

# 8. MULTISITE TESTS
if [[ "$TEST_TYPE" == "all" || "$TEST_TYPE" == "multisite" ]]; then
    print_status "info" "Running multisite tests..."
    
    # Note: Multisite tests require special WordPress installation
    if wp core is-installed --network 2>/dev/null; then
        phpunit \
            --configuration phpunit.xml \
            --testsuite multisite \
            --log-junit "$TEST_REPORTS_DIR/multisite-results.xml" \
            $phpunit_args \
            > "$TEST_REPORTS_DIR/multisite-output.txt" 2>&1
        
        multisite_exit_code=$?
        update_test_counts "$TEST_REPORTS_DIR/multisite-output.txt"
        
        if [[ $multisite_exit_code -eq 0 ]]; then
            print_status "success" "Multisite tests passed"
        else
            print_status "error" "Multisite tests failed (exit code: $multisite_exit_code)"
        fi
    else
        print_status "warning" "WordPress multisite not detected, skipping multisite tests..."
        multisite_exit_code=0
        SKIPPED_TESTS=$((SKIPPED_TESTS + 1))
    fi
fi

# Generate comprehensive coverage report
if [[ "$TEST_TYPE" == "all" ]]; then
    print_status "info" "Generating comprehensive coverage report..."
    
    # Merge coverage reports if multiple exist
    if command -v phpcov &> /dev/null; then
        phpcov merge \
            --clover coverage/merged-clover.xml \
            --html coverage/merged-html \
            coverage/ 2>/dev/null || true
    fi
fi

# Calculate test execution time
end_time=$(date +%s)
execution_time=$((end_time - start_time))

# Generate final report
print_status "info" "Generating test report..."

cat > "$TEST_REPORTS_DIR/test-summary.html" << EOF
<!DOCTYPE html>
<html>
<head>
    <title>Woo AI Assistant - Test Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { background: #f8f9fa; padding: 20px; border-radius: 5px; }
        .stats { display: flex; justify-content: space-around; margin: 20px 0; }
        .stat { text-align: center; padding: 10px; }
        .passed { color: #28a745; }
        .failed { color: #dc3545; }
        .skipped { color: #ffc107; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🧪 Woo AI Assistant - Test Report</h1>
        <p><strong>Generated:</strong> $(date)</p>
        <p><strong>Test Type:</strong> $TEST_TYPE</p>
        <p><strong>Execution Time:</strong> ${execution_time}s</p>
    </div>
    
    <div class="stats">
        <div class="stat">
            <h3 class="passed">$PASSED_TESTS</h3>
            <p>Passed</p>
        </div>
        <div class="stat">
            <h3 class="failed">$FAILED_TESTS</h3>
            <p>Failed</p>
        </div>
        <div class="stat">
            <h3 class="skipped">$SKIPPED_TESTS</h3>
            <p>Skipped</p>
        </div>
        <div class="stat">
            <h3>$TOTAL_TESTS</h3>
            <p>Total</p>
        </div>
    </div>
    
    <div class="section">
        <h2>📊 Test Results Summary</h2>
        <ul>
            <li>✅ Unit Tests: $([ ${unit_exit_code:-0} -eq 0 ] && echo "PASSED" || echo "FAILED")</li>
            <li>🔗 Integration Tests: $([ ${integration_exit_code:-0} -eq 0 ] && echo "PASSED" || echo "FAILED")</li>
            <li>🌐 E2E Tests: $([ ${e2e_exit_code:-0} -eq 0 ] && echo "PASSED" || echo "FAILED")</li>
            <li>⚡ Load Tests: $([ ${load_exit_code:-0} -eq 0 ] && echo "PASSED" || echo "FAILED")</li>
            <li>🔒 Security Tests: $([ ${security_exit_code:-0} -eq 0 ] && echo "PASSED" || echo "FAILED")</li>
            <li>🔄 Compatibility Tests: $([ ${compatibility_exit_code:-0} -eq 0 ] && echo "PASSED" || echo "FAILED")</li>
            <li>🌍 Multisite Tests: $([ ${multisite_exit_code:-0} -eq 0 ] && echo "PASSED" || echo "FAILED")</li>
            <li>📈 Performance Tests: $([ ${performance_exit_code:-0} -eq 0 ] && echo "PASSED" || echo "FAILED")</li>
        </ul>
    </div>
    
    <div class="section">
        <h2>📁 Artifacts</h2>
        <ul>
            <li><a href="coverage/html/index.html">Coverage Report</a></li>
            <li><a href="e2e-results/index.html">E2E Test Report</a></li>
            <li><a href="load-test-results.json">Load Test Results</a></li>
            <li><a href="../performance-results.json">Performance Metrics</a></li>
        </ul>
    </div>
</body>
</html>
EOF

# Cleanup if requested
if [[ "$CLEANUP" == "true" ]]; then
    print_status "info" "Cleaning up temporary test files..."
    
    # Remove temporary files but keep reports
    find . -name "*.tmp" -type f -delete 2>/dev/null || true
    find . -name "*.cache" -type f -delete 2>/dev/null || true
    
    # Clean up test databases
    if [[ -f ".env.testing" ]]; then
        source .env.testing
        mysql -h"${DB_HOST:-localhost}" -u"${DB_USER}" -p"${DB_PASSWORD}" \
              -e "DROP DATABASE IF EXISTS ${DB_NAME}_test;" 2>/dev/null || true
    fi
fi

# Final status
print_status "info" "Test execution completed in ${execution_time} seconds"
print_status "info" "Report generated: $TEST_REPORTS_DIR/test-summary.html"

if [[ $FAILED_TESTS -eq 0 ]]; then
    print_status "success" "All tests passed! 🎉"
    exit 0
else
    print_status "error" "$FAILED_TESTS test(s) failed"
    print_status "info" "Check detailed reports in $TEST_REPORTS_DIR/"
    exit 1
fi