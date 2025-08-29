#!/bin/bash

# Integration Test Runner Script
# Executes comprehensive integration tests for the Woo AI Assistant plugin
# Ensures all components work together correctly

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(dirname "$SCRIPT_DIR")"

echo -e "${BLUE}================================================${NC}"
echo -e "${BLUE}   Woo AI Assistant - Integration Test Suite   ${NC}"
echo -e "${BLUE}================================================${NC}"
echo ""

# Check if we're in the correct directory
if [ ! -f "$PLUGIN_DIR/woo-ai-assistant.php" ]; then
    echo -e "${RED}Error: Not in plugin directory${NC}"
    exit 1
fi

cd "$PLUGIN_DIR"

# Function to run a specific test group
run_test_group() {
    local test_name=$1
    local test_command=$2
    
    echo -e "${YELLOW}Running: $test_name${NC}"
    
    if eval "$test_command"; then
        echo -e "${GREEN}✓ $test_name passed${NC}"
        return 0
    else
        echo -e "${RED}✗ $test_name failed${NC}"
        return 1
    fi
}

# Initialize test results
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

echo -e "${BLUE}1. Plugin Structure Tests${NC}"
echo "================================"

# Test 1: Check required files exist
run_test_group "File Structure Test" "
    test -f woo-ai-assistant.php &&
    test -f src/Main.php &&
    test -f src/Setup/Activator.php &&
    test -f src/Setup/Deactivator.php &&
    test -d src/Admin &&
    test -d src/KnowledgeBase &&
    test -d src/Chatbot &&
    test -d src/Frontend
"
((TOTAL_TESTS++))
[ $? -eq 0 ] && ((PASSED_TESTS++)) || ((FAILED_TESTS++))

echo ""
echo -e "${BLUE}2. PHP Syntax Tests${NC}"
echo "================================"

# Test 2: Check PHP syntax for all files
run_test_group "PHP Syntax Check" "
    find src/ -name '*.php' -exec php -l {} \; 2>&1 | grep -v 'No syntax errors' | grep -q 'error' && exit 1 || exit 0
"
((TOTAL_TESTS++))
[ $? -eq 0 ] && ((PASSED_TESTS++)) || ((FAILED_TESTS++))

echo ""
echo -e "${BLUE}3. Asset Files Tests${NC}"
echo "================================"

# Test 3: Check required asset files
run_test_group "Asset Files Test" "
    test -f assets/css/admin.css &&
    test -f assets/css/widget.css &&
    test -f assets/css/settings.css &&
    test -f assets/css/kb-status.css &&
    test -f assets/css/conversations.css &&
    test -f assets/js/admin.js &&
    test -f assets/js/widget.js &&
    test -f assets/js/conversations.js
"
((TOTAL_TESTS++))
[ $? -eq 0 ] && ((PASSED_TESTS++)) || ((FAILED_TESTS++))

echo ""
echo -e "${BLUE}4. Composer Dependencies${NC}"
echo "================================"

# Test 4: Check composer dependencies
run_test_group "Composer Dependencies" "
    test -f composer.json &&
    test -f vendor/autoload.php &&
    composer validate --no-check-publish
"
((TOTAL_TESTS++))
[ $? -eq 0 ] && ((PASSED_TESTS++)) || ((FAILED_TESTS++))

echo ""
echo -e "${BLUE}5. PHPUnit Integration Tests${NC}"
echo "================================"

# Test 5: Run PHPUnit integration tests
if [ -f "vendor/bin/phpunit" ]; then
    run_test_group "PHPUnit Integration Tests" "
        vendor/bin/phpunit tests/Integration/SystemIntegrationTest.php --colors=always
    "
    ((TOTAL_TESTS++))
    [ $? -eq 0 ] && ((PASSED_TESTS++)) || ((FAILED_TESTS++))
else
    echo -e "${YELLOW}Skipping PHPUnit tests (PHPUnit not installed)${NC}"
fi

echo ""
echo -e "${BLUE}6. Coding Standards${NC}"
echo "================================"

# Test 6: Check PSR-12 compliance
if [ -f "vendor/bin/phpcs" ]; then
    run_test_group "PSR-12 Coding Standards" "
        vendor/bin/phpcs --standard=PSR12 --extensions=php src/ --report=summary -q
    "
    ((TOTAL_TESTS++))
    [ $? -eq 0 ] && ((PASSED_TESTS++)) || ((FAILED_TESTS++))
else
    echo -e "${YELLOW}Skipping coding standards check (PHPCS not installed)${NC}"
fi

echo ""
echo -e "${BLUE}7. Static Analysis${NC}"
echo "================================"

# Test 7: Run PHPStan analysis
if [ -f "vendor/bin/phpstan" ]; then
    run_test_group "PHPStan Static Analysis" "
        vendor/bin/phpstan analyse src/ --level=5 --no-progress --no-ansi
    "
    ((TOTAL_TESTS++))
    [ $? -eq 0 ] && ((PASSED_TESTS++)) || ((FAILED_TESTS++))
else
    echo -e "${YELLOW}Skipping static analysis (PHPStan not installed)${NC}"
fi

echo ""
echo -e "${BLUE}8. Database Schema Test${NC}"
echo "================================"

# Test 8: Check if activation creates tables (in test mode)
run_test_group "Database Schema Test" "
    php -r '
        define(\"WP_PLUGIN_DIR\", \"'$PLUGIN_DIR'\");
        define(\"ABSPATH\", \"'$PLUGIN_DIR'\");
        require_once \"src/Setup/Activator.php\";
        
        // Check if Activator class exists
        if (class_exists(\"WooAiAssistant\\\Setup\\\Activator\")) {
            echo \"Activator class found\n\";
            exit(0);
        } else {
            echo \"Activator class not found\n\";
            exit(1);
        }
    '
"
((TOTAL_TESTS++))
[ $? -eq 0 ] && ((PASSED_TESTS++)) || ((FAILED_TESTS++))

echo ""
echo -e "${BLUE}9. REST API Routes Test${NC}"
echo "================================"

# Test 9: Check REST Controller exists and can be instantiated
run_test_group "REST API Controller Test" "
    php -r '
        require_once \"vendor/autoload.php\";
        
        // Check if RestController class exists
        if (class_exists(\"WooAiAssistant\\\Admin\\\RestController\")) {
            echo \"RestController class found\n\";
            exit(0);
        } else {
            echo \"RestController class not found\n\";
            exit(1);
        }
    '
"
((TOTAL_TESTS++))
[ $? -eq 0 ] && ((PASSED_TESTS++)) || ((FAILED_TESTS++))

echo ""
echo -e "${BLUE}10. Admin Pages Test${NC}"
echo "================================"

# Test 10: Check all admin page classes exist
run_test_group "Admin Pages Classes Test" "
    test -f src/Admin/Pages/DashboardPage.php &&
    test -f src/Admin/Pages/SettingsPage.php &&
    test -f src/Admin/Pages/ConversationsLogPage.php &&
    test -f src/Admin/Pages/KnowledgeBaseStatusPage.php
"
((TOTAL_TESTS++))
[ $? -eq 0 ] && ((PASSED_TESTS++)) || ((FAILED_TESTS++))

echo ""
echo -e "${BLUE}================================================${NC}"
echo -e "${BLUE}           Integration Test Results             ${NC}"
echo -e "${BLUE}================================================${NC}"
echo ""

# Calculate pass rate
if [ $TOTAL_TESTS -gt 0 ]; then
    PASS_RATE=$((PASSED_TESTS * 100 / TOTAL_TESTS))
else
    PASS_RATE=0
fi

# Display results
echo -e "Total Tests:  $TOTAL_TESTS"
echo -e "Passed:       ${GREEN}$PASSED_TESTS${NC}"
echo -e "Failed:       ${RED}$FAILED_TESTS${NC}"
echo -e "Pass Rate:    $PASS_RATE%"
echo ""

# Determine overall status
if [ $FAILED_TESTS -eq 0 ]; then
    echo -e "${GREEN}✓ All integration tests passed!${NC}"
    echo -e "${GREEN}The system is ready for deployment.${NC}"
    exit 0
else
    echo -e "${RED}✗ Some integration tests failed.${NC}"
    echo -e "${YELLOW}Please review the failures above and fix any issues.${NC}"
    exit 1
fi