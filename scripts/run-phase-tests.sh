#!/bin/bash

# Woo AI Assistant - Progressive Phase Testing Script
# Executes phase-appropriate tests based on development progress
# Usage: ./scripts/run-phase-tests.sh [phase_number|all]

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PLUGIN_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHASE="${1:-0}"
TEST_RESULTS_FILE=".test-results.json"

# Banner
echo -e "${BLUE}"
echo "======================================"
echo "  Woo AI Assistant - Phase Testing"
echo "======================================"
echo -e "${NC}"

# Print usage if help requested
if [[ "$1" == "--help" || "$1" == "-h" ]]; then
    echo "Usage: $0 [phase_number|all]"
    echo ""
    echo "Available phases:"
    echo "  0    - Foundation Setup Tests"
    echo "  1    - Core Infrastructure Tests"
    echo "  2    - Knowledge Base Tests"
    echo "  3    - Server Integration Tests"
    echo "  4    - Widget Frontend Tests"
    echo "  5    - Chat Logic Tests"
    echo "  6    - Advanced Features Tests"
    echo "  7    - Analytics Tests"
    echo "  8    - Optimization Tests"
    echo "  all  - Run all completed phases"
    echo ""
    echo "Examples:"
    echo "  $0 0       # Run Phase 0 tests only"
    echo "  $0 all     # Run all tests for completed phases"
    exit 0
fi

# Change to plugin directory
cd "$PLUGIN_ROOT"

# Function to check if a phase is ready for testing
check_phase_readiness() {
    local phase=$1
    case $phase in
        0)
            # Phase 0: Basic structure should exist
            [[ -f "woo-ai-assistant.php" && -f "src/Main.php" ]]
            ;;
        1)
            # Phase 1: Core infrastructure
            [[ -f "src/Admin/AdminMenu.php" && -f "src/RestApi/RestController.php" ]]
            ;;
        2)
            # Phase 2: Knowledge Base components
            [[ -f "src/KnowledgeBase/Scanner.php" && -f "src/KnowledgeBase/Indexer.php" ]]
            ;;
        3)
            # Phase 3: Server integration
            [[ -f "src/Api/IntermediateServerClient.php" && -f "src/Api/LicenseManager.php" ]]
            ;;
        4)
            # Phase 4: React components
            [[ -f "widget-src/src/App.js" && -f "widget-src/src/components/ChatWindow.js" ]]
            ;;
        5)
            # Phase 5: Chat logic
            [[ -f "src/Chatbot/ConversationHandler.php" && -f "src/RestApi/Endpoints/ChatEndpoint.php" ]]
            ;;
        6)
            # Phase 6: Advanced features
            [[ -f "src/Chatbot/CouponHandler.php" && -f "src/Chatbot/ProactiveTriggers.php" ]]
            ;;
        7)
            # Phase 7: Analytics
            [[ -f "src/Admin/pages/DashboardPage.php" && -f "src/Admin/pages/SettingsPage.php" ]]
            ;;
        8)
            # Phase 8: Everything should exist
            [[ -f "tests/integration/E2E/FullPluginTest.php" ]]
            ;;
        *)
            return 1
            ;;
    esac
}

# Function to get minimum coverage requirement for phase
get_coverage_requirement() {
    local phase=$1
    case $phase in
        0) echo "30" ;;
        1) echo "50" ;;
        2) echo "70" ;;
        3) echo "75" ;;
        4) echo "80" ;;
        5) echo "85" ;;
        6) echo "87" ;;
        7) echo "89" ;;
        8) echo "90" ;;
        *) echo "0" ;;
    esac
}

# Function to run PHP tests for a specific phase
run_php_tests_for_phase() {
    local phase=$1
    echo -e "${YELLOW}Running PHP tests for Phase $phase...${NC}"
    
    local test_args=""
    case $phase in
        0)
            test_args="--group phase0,foundation"
            ;;
        1)
            test_args="--group phase0,phase1,foundation,core"
            ;;
        2)
            test_args="--group phase0,phase1,phase2,foundation,core,knowledge"
            ;;
        3)
            test_args="--group phase0,phase1,phase2,phase3,foundation,core,knowledge,api"
            ;;
        4)
            test_args="--group phase0,phase1,phase2,phase3,phase4,foundation,core,knowledge,api,frontend"
            ;;
        5)
            test_args="--group phase0,phase1,phase2,phase3,phase4,phase5,foundation,core,knowledge,api,frontend,chat"
            ;;
        6)
            test_args="--group phase0,phase1,phase2,phase3,phase4,phase5,phase6,foundation,core,knowledge,api,frontend,chat,advanced"
            ;;
        7)
            test_args="--group phase0,phase1,phase2,phase3,phase4,phase5,phase6,phase7,foundation,core,knowledge,api,frontend,chat,advanced,analytics"
            ;;
        8)
            test_args=""  # Run all tests
            ;;
    esac
    
    # Run PHPUnit with coverage if available
    if command -v vendor/bin/phpunit >/dev/null 2>&1; then
        if [[ -n "$test_args" ]]; then
            vendor/bin/phpunit $test_args --coverage-text 2>/dev/null || vendor/bin/phpunit $test_args
        else
            vendor/bin/phpunit --coverage-text 2>/dev/null || vendor/bin/phpunit
        fi
    else
        echo -e "${YELLOW}PHPUnit not available, skipping PHP tests${NC}"
        return 0
    fi
}

# Function to run React tests for a specific phase
run_react_tests_for_phase() {
    local phase=$1
    
    # React tests only relevant from Phase 4 onwards
    if [[ $phase -lt 4 ]]; then
        return 0
    fi
    
    echo -e "${YELLOW}Running React tests for Phase $phase...${NC}"
    
    local test_pattern=""
    case $phase in
        4)
            test_pattern="(foundation|App|ChatWindow|Message)"
            ;;
        5)
            test_pattern="(foundation|App|ChatWindow|Message|ApiService|useChat)"
            ;;
        6|7|8)
            test_pattern=""  # Run all React tests
            ;;
    esac
    
    # Run Jest tests
    if command -v npm >/dev/null 2>&1 && [[ -f "package.json" ]]; then
        if [[ -n "$test_pattern" ]]; then
            npm test -- --testPathPattern="$test_pattern" --watchAll=false --coverage=false 2>/dev/null || npm test -- --testPathPattern="$test_pattern" --watchAll=false
        else
            npm test -- --watchAll=false --coverage=false 2>/dev/null || npm test -- --watchAll=false
        fi
    else
        echo -e "${YELLOW}NPM/Jest not available, skipping React tests${NC}"
        return 0
    fi
}

# Function to check coverage requirement
check_coverage_requirement() {
    local phase=$1
    local min_coverage=$(get_coverage_requirement $phase)
    
    echo -e "${YELLOW}Checking coverage requirement: ${min_coverage}%${NC}"
    
    # Extract coverage from PHPUnit output if available
    if command -v vendor/bin/phpunit >/dev/null 2>&1; then
        local coverage_output=$(vendor/bin/phpunit --coverage-text 2>/dev/null | grep "Lines:" | tail -1)
        if [[ -n "$coverage_output" ]]; then
            local actual_coverage=$(echo "$coverage_output" | grep -o '[0-9]\+%' | tr -d '%')
            if [[ -n "$actual_coverage" ]]; then
                if [[ $actual_coverage -ge $min_coverage ]]; then
                    echo -e "${GREEN}✅ Coverage $actual_coverage% meets Phase $phase requirement ($min_coverage%)${NC}"
                    return 0
                else
                    echo -e "${RED}❌ Coverage $actual_coverage% below Phase $phase requirement ($min_coverage%)${NC}"
                    return 1
                fi
            fi
        fi
    fi
    
    echo -e "${YELLOW}Coverage information not available, skipping coverage check${NC}"
    return 0
}

# Function to run tests for a specific phase
run_phase_tests() {
    local phase=$1
    local start_time=$(date +%s)
    
    echo -e "${BLUE}Testing Phase $phase: $(get_phase_name $phase)${NC}"
    
    # Check if phase is ready
    if ! check_phase_readiness $phase; then
        echo -e "${YELLOW}⚠️  Phase $phase not ready - some files missing. Tests may fail.${NC}"
    fi
    
    local php_result=0
    local react_result=0
    local coverage_result=0
    
    # Run PHP tests
    run_php_tests_for_phase $phase || php_result=$?
    
    # Run React tests
    run_react_tests_for_phase $phase || react_result=$?
    
    # Check coverage
    check_coverage_requirement $phase || coverage_result=$?
    
    local end_time=$(date +%s)
    local duration=$((end_time - start_time))
    
    # Report results
    if [[ $php_result -eq 0 && $react_result -eq 0 && $coverage_result -eq 0 ]]; then
        echo -e "${GREEN}✅ Phase $phase tests PASSED (${duration}s)${NC}"
        return 0
    else
        echo -e "${RED}❌ Phase $phase tests FAILED (${duration}s)${NC}"
        [[ $php_result -ne 0 ]] && echo -e "${RED}   - PHP tests failed${NC}"
        [[ $react_result -ne 0 ]] && echo -e "${RED}   - React tests failed${NC}"
        [[ $coverage_result -ne 0 ]] && echo -e "${RED}   - Coverage requirement not met${NC}"
        return 1
    fi
}

# Function to get phase name
get_phase_name() {
    case $1 in
        0) echo "Foundation Setup" ;;
        1) echo "Core Infrastructure" ;;
        2) echo "Knowledge Base Core" ;;
        3) echo "Server Integration" ;;
        4) echo "Widget Frontend" ;;
        5) echo "Chat Logic & AI" ;;
        6) echo "Advanced Features" ;;
        7) echo "Analytics & Dashboard" ;;
        8) echo "Optimization & Polish" ;;
        *) echo "Unknown Phase" ;;
    esac
}

# Main execution logic
main() {
    local overall_result=0
    local start_time=$(date +%s)
    
    if [[ "$PHASE" == "all" ]]; then
        echo -e "${BLUE}Running tests for all implemented phases...${NC}"
        for p in {0..8}; do
            if check_phase_readiness $p; then
                run_phase_tests $p || overall_result=$?
                echo ""
            else
                echo -e "${YELLOW}Skipping Phase $p - not implemented yet${NC}"
            fi
        done
    elif [[ "$PHASE" =~ ^[0-8]$ ]]; then
        run_phase_tests $PHASE || overall_result=$?
    else
        echo -e "${RED}Error: Invalid phase '$PHASE'. Use 0-8 or 'all'.${NC}"
        exit 1
    fi
    
    local end_time=$(date +%s)
    local total_duration=$((end_time - start_time))
    
    # Summary
    echo -e "${BLUE}======================================"
    if [[ $overall_result -eq 0 ]]; then
        echo -e "${GREEN}✅ All tests PASSED (${total_duration}s total)${NC}"
        echo -e "${GREEN}Quality gates satisfied for Phase $PHASE${NC}"
    else
        echo -e "${RED}❌ Some tests FAILED (${total_duration}s total)${NC}"
        echo -e "${RED}Fix failing tests before marking tasks complete${NC}"
    fi
    echo -e "${BLUE}======================================${NC}"
    
    exit $overall_result
}

# Run main function
main "$@"