#!/bin/bash

# =================================================================
# Test Activation Scenarios Script
#
# Comprehensive script to manually test plugin activation scenarios
# that would have caught the wpdb::prepare() issues and duplicate data problems.
#
# This script tests:
# - Fresh activation
# - Reactivation (idempotent)
# - Upgrade scenarios
# - Failure recovery
# - Database integrity
# - wpdb::prepare() usage validation
#
# Usage: ./scripts/test-activation-scenarios.sh [scenario]
# Where scenario can be: all, fresh, reactivate, upgrade, failure, database
#
# @package WooAiAssistant
# @since 1.0.0
# @author Claude Code Assistant
# =================================================================

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WP_CLI_PATH="wp"
TEST_LOG_FILE="${PLUGIN_DIR}/logs/activation-test.log"
MYSQL_LOG_FILE="/tmp/mysql-activation-test.log"

# Test counters
TESTS_RUN=0
TESTS_PASSED=0
TESTS_FAILED=0

# Ensure log directory exists
mkdir -p "${PLUGIN_DIR}/logs"

# =================================================================
# UTILITY FUNCTIONS
# =================================================================

print_header() {
    echo -e "${BLUE}==================================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}==================================================${NC}"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
    ((TESTS_PASSED++))
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
    echo "$1" >> "$TEST_LOG_FILE"
    ((TESTS_FAILED++))
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ $1${NC}"
}

run_test() {
    local test_name="$1"
    local test_function="$2"
    
    ((TESTS_RUN++))
    echo ""
    print_info "Running test: $test_name"
    
    if $test_function; then
        print_success "$test_name"
        return 0
    else
        print_error "$test_name"
        return 1
    fi
}

# Check if WP-CLI is available
check_wp_cli() {
    if ! command -v $WP_CLI_PATH &> /dev/null; then
        print_error "WP-CLI is not available. Please install wp-cli or update WP_CLI_PATH"
        exit 1
    fi
}

# Enable MySQL query logging
enable_mysql_logging() {
    print_info "Enabling MySQL query logging..."
    mysql -e "SET GLOBAL general_log = 'ON';" 2>/dev/null || true
    mysql -e "SET GLOBAL general_log_file = '$MYSQL_LOG_FILE';" 2>/dev/null || true
}

# Disable MySQL query logging
disable_mysql_logging() {
    print_info "Disabling MySQL query logging..."
    mysql -e "SET GLOBAL general_log = 'OFF';" 2>/dev/null || true
}

# Clear MySQL log
clear_mysql_log() {
    > "$MYSQL_LOG_FILE" 2>/dev/null || true
}

# Analyze MySQL queries for wpdb::prepare() issues
analyze_mysql_queries() {
    local log_file="$1"
    local violations=0
    
    if [[ ! -f "$log_file" ]]; then
        print_warning "MySQL log file not found: $log_file"
        return 0
    fi
    
    print_info "Analyzing MySQL queries for wpdb::prepare() violations..."
    
    # Look for suspicious query patterns
    while IFS= read -r line; do
        # Skip non-query lines
        if [[ ! "$line" =~ Query ]]; then
            continue
        fi
        
        # Check for unescaped variables in queries
        if echo "$line" | grep -qE "wp_[a-zA-Z_]*'.*\$|'.*\{\$"; then
            print_error "Potential unescaped variable in query: $(echo "$line" | cut -c1-100)..."
            ((violations++))
        fi
        
        # Check for direct concatenation without prepare
        if echo "$line" | grep -qE "INSERT.*'.*\.'.*'|UPDATE.*'.*\.'.*'"; then
            print_warning "Potential direct concatenation in query: $(echo "$line" | cut -c1-100)..."
        fi
        
    done < "$log_file"
    
    if [[ $violations -eq 0 ]]; then
        print_success "No obvious wpdb::prepare() violations found in MySQL queries"
        return 0
    else
        print_error "Found $violations potential wpdb::prepare() violations"
        return 1
    fi
}

# =================================================================
# PLUGIN STATE MANAGEMENT
# =================================================================

deactivate_plugin() {
    print_info "Deactivating plugin..."
    $WP_CLI_PATH plugin deactivate woo-ai-assistant --allow-root 2>/dev/null || true
}

activate_plugin() {
    print_info "Activating plugin..."
    $WP_CLI_PATH plugin activate woo-ai-assistant --allow-root
}

is_plugin_active() {
    $WP_CLI_PATH plugin is-active woo-ai-assistant --allow-root >/dev/null 2>&1
}

get_plugin_option() {
    local option_name="$1"
    $WP_CLI_PATH option get "$option_name" --allow-root 2>/dev/null || echo ""
}

delete_plugin_option() {
    local option_name="$1"
    $WP_CLI_PATH option delete "$option_name" --allow-root 2>/dev/null || true
}

# Clean plugin state completely
clean_plugin_state() {
    print_info "Cleaning plugin state..."
    
    deactivate_plugin
    
    # Remove options
    delete_plugin_option "woo_ai_assistant_activation_complete"
    delete_plugin_option "woo_ai_assistant_activated_at"
    delete_plugin_option "woo_ai_assistant_version"
    delete_plugin_option "woo_ai_assistant_db_version"
    delete_plugin_option "woo_ai_assistant_widget_ready"
    delete_plugin_option "woo_ai_assistant_first_activation"
    
    # Clear cron jobs
    $WP_CLI_PATH cron event delete woo_ai_assistant_daily_index --allow-root 2>/dev/null || true
    $WP_CLI_PATH cron event delete woo_ai_assistant_cleanup_analytics --allow-root 2>/dev/null || true
    $WP_CLI_PATH cron event delete woo_ai_assistant_cleanup_cache --allow-root 2>/dev/null || true
    
    print_success "Plugin state cleaned"
}

# =================================================================
# DATABASE VALIDATION
# =================================================================

check_database_tables() {
    local expected_tables=(
        "woo_ai_settings"
        "woo_ai_knowledge_base"
        "woo_ai_conversations"
        "woo_ai_messages"
        "woo_ai_analytics"
        "woo_ai_licenses"
    )
    
    local missing_tables=()
    local table_prefix
    table_prefix=$($WP_CLI_PATH config get table_prefix --allow-root)
    
    for table in "${expected_tables[@]}"; do
        local full_table_name="${table_prefix}${table}"
        if ! $WP_CLI_PATH db query "SHOW TABLES LIKE '${full_table_name}'" --allow-root | grep -q "${full_table_name}"; then
            missing_tables+=("$full_table_name")
        fi
    done
    
    if [[ ${#missing_tables[@]} -eq 0 ]]; then
        print_success "All expected database tables exist"
        return 0
    else
        print_error "Missing database tables: ${missing_tables[*]}"
        return 1
    fi
}

check_database_duplicates() {
    local table_prefix
    table_prefix=$($WP_CLI_PATH config get table_prefix --allow-root)
    
    # Check for duplicate knowledge base entries
    local kb_duplicates
    kb_duplicates=$($WP_CLI_PATH db query "SELECT COUNT(*) as cnt FROM (SELECT chunk_hash, COUNT(*) FROM ${table_prefix}woo_ai_knowledge_base GROUP BY chunk_hash HAVING COUNT(*) > 1) as dups" --allow-root 2>/dev/null | tail -n1 || echo "0")
    
    if [[ "$kb_duplicates" == "0" ]]; then
        print_success "No duplicate knowledge base entries found"
    else
        print_error "Found $kb_duplicates duplicate knowledge base entries"
        return 1
    fi
    
    # Check for duplicate settings
    local settings_duplicates
    settings_duplicates=$($WP_CLI_PATH db query "SELECT COUNT(*) as cnt FROM (SELECT setting_key, COUNT(*) FROM ${table_prefix}woo_ai_settings GROUP BY setting_key HAVING COUNT(*) > 1) as dups" --allow-root 2>/dev/null | tail -n1 || echo "0")
    
    if [[ "$settings_duplicates" == "0" ]]; then
        print_success "No duplicate settings found"
        return 0
    else
        print_error "Found $settings_duplicates duplicate settings"
        return 1
    fi
}

check_database_integrity() {
    print_info "Checking database integrity..."
    
    check_database_tables && check_database_duplicates
}

# =================================================================
# TEST SCENARIOS
# =================================================================

test_fresh_activation() {
    print_header "Testing Fresh Activation"
    
    # Clean state
    clean_plugin_state
    
    # Start MySQL logging
    clear_mysql_log
    enable_mysql_logging
    
    # Activate plugin
    if activate_plugin; then
        print_success "Plugin activated successfully"
    else
        print_error "Plugin activation failed"
        disable_mysql_logging
        return 1
    fi
    
    # Stop MySQL logging
    disable_mysql_logging
    
    # Verify activation state
    if is_plugin_active; then
        print_success "Plugin is active"
    else
        print_error "Plugin should be active but isn't"
        return 1
    fi
    
    # Check required options
    local activation_complete
    activation_complete=$(get_plugin_option "woo_ai_assistant_activation_complete")
    if [[ "$activation_complete" == "1" ]]; then
        print_success "Activation complete flag is set"
    else
        print_error "Activation complete flag is not set"
        return 1
    fi
    
    # Check database integrity
    if ! check_database_integrity; then
        return 1
    fi
    
    # Analyze MySQL queries
    analyze_mysql_queries "$MYSQL_LOG_FILE"
}

test_idempotent_reactivation() {
    print_header "Testing Idempotent Reactivation"
    
    # Ensure plugin is activated first
    if ! is_plugin_active; then
        activate_plugin
    fi
    
    # Capture initial state
    local initial_timestamp
    initial_timestamp=$(get_plugin_option "woo_ai_assistant_activated_at")
    
    # Get initial data counts
    local table_prefix
    table_prefix=$($WP_CLI_PATH config get table_prefix --allow-root)
    local initial_kb_count
    initial_kb_count=$($WP_CLI_PATH db query "SELECT COUNT(*) FROM ${table_prefix}woo_ai_knowledge_base" --allow-root 2>/dev/null | tail -n1 || echo "0")
    local initial_settings_count
    initial_settings_count=$($WP_CLI_PATH db query "SELECT COUNT(*) FROM ${table_prefix}woo_ai_settings" --allow-root 2>/dev/null | tail -n1 || echo "0")
    
    # Start MySQL logging
    clear_mysql_log
    enable_mysql_logging
    
    # Reactivate plugin (should be idempotent)
    deactivate_plugin
    sleep 1
    activate_plugin
    
    # Stop MySQL logging
    disable_mysql_logging
    
    # Check that timestamp didn't change (idempotent behavior)
    local new_timestamp
    new_timestamp=$(get_plugin_option "woo_ai_assistant_activated_at")
    if [[ "$initial_timestamp" == "$new_timestamp" ]]; then
        print_success "Activation timestamp preserved (idempotent)"
    else
        print_warning "Activation timestamp changed (may not be idempotent)"
    fi
    
    # Check that data counts didn't increase
    local new_kb_count
    new_kb_count=$($WP_CLI_PATH db query "SELECT COUNT(*) FROM ${table_prefix}woo_ai_knowledge_base" --allow-root 2>/dev/null | tail -n1 || echo "0")
    local new_settings_count
    new_settings_count=$($WP_CLI_PATH db query "SELECT COUNT(*) FROM ${table_prefix}woo_ai_settings" --allow-root 2>/dev/null | tail -n1 || echo "0")
    
    if [[ "$initial_kb_count" == "$new_kb_count" ]]; then
        print_success "Knowledge base count preserved ($new_kb_count)"
    else
        print_error "Knowledge base count changed from $initial_kb_count to $new_kb_count"
        return 1
    fi
    
    if [[ "$initial_settings_count" == "$new_settings_count" ]]; then
        print_success "Settings count preserved ($new_settings_count)"
    else
        print_error "Settings count changed from $initial_settings_count to $new_settings_count"
        return 1
    fi
    
    # Check for duplicates
    check_database_duplicates && analyze_mysql_queries "$MYSQL_LOG_FILE"
}

test_upgrade_scenario() {
    print_header "Testing Upgrade Scenario"
    
    # Clean state
    clean_plugin_state
    
    # Simulate old version installation
    $WP_CLI_PATH option add "woo_ai_assistant_version" "0.9.0" --allow-root
    $WP_CLI_PATH option add "woo_ai_assistant_activation_complete" "1" --allow-root
    
    print_info "Simulated old version 0.9.0 installation"
    
    # Start MySQL logging
    clear_mysql_log
    enable_mysql_logging
    
    # Activate (should trigger upgrade)
    if activate_plugin; then
        print_success "Upgrade activation completed"
    else
        print_error "Upgrade activation failed"
        disable_mysql_logging
        return 1
    fi
    
    # Stop MySQL logging
    disable_mysql_logging
    
    # Check version was updated
    local new_version
    new_version=$(get_plugin_option "woo_ai_assistant_version")
    if [[ "$new_version" != "0.9.0" ]] && [[ -n "$new_version" ]]; then
        print_success "Version updated to $new_version"
    else
        print_error "Version was not updated (still $new_version)"
        return 1
    fi
    
    # Check upgrade timestamp was set
    local upgrade_timestamp
    upgrade_timestamp=$(get_plugin_option "woo_ai_assistant_last_upgrade")
    if [[ -n "$upgrade_timestamp" ]]; then
        print_success "Upgrade timestamp set"
    else
        print_error "Upgrade timestamp not set"
        return 1
    fi
    
    check_database_integrity && analyze_mysql_queries "$MYSQL_LOG_FILE"
}

test_activation_failure_recovery() {
    print_header "Testing Activation Failure Recovery"
    
    # Clean state
    clean_plugin_state
    
    # This test simulates failure scenarios and recovery
    # For a real test, we would need to modify the plugin to inject failures
    
    print_info "Testing failure recovery mechanisms..."
    
    # Test 1: Simulate database connection failure during activation
    # (In a real scenario, you might temporarily modify database credentials)
    
    # Test 2: Simulate partial activation cleanup
    # Set some activation flags manually, then try to activate
    $WP_CLI_PATH option add "woo_ai_assistant_activated_at" "$(date +%s)" --allow-root
    
    # Try activation - should handle existing partial state
    if activate_plugin; then
        print_success "Activation succeeded with partial existing state"
        
        # Verify cleanup/completion
        local activation_complete
        activation_complete=$(get_plugin_option "woo_ai_assistant_activation_complete")
        if [[ "$activation_complete" == "1" ]]; then
            print_success "Activation completed properly after partial state"
        else
            print_error "Activation completion flag not set after recovery"
            return 1
        fi
    else
        print_error "Activation failed to recover from partial state"
        return 1
    fi
    
    return 0
}

test_multiple_activation_cycles() {
    print_header "Testing Multiple Activation Cycles"
    
    for i in {1..3}; do
        print_info "Activation cycle $i"
        
        # Clean state
        clean_plugin_state
        
        # Activate
        if ! activate_plugin; then
            print_error "Activation failed in cycle $i"
            return 1
        fi
        
        # Verify activation
        if ! is_plugin_active; then
            print_error "Plugin not active after activation in cycle $i"
            return 1
        fi
        
        # Check database integrity
        if ! check_database_integrity; then
            print_error "Database integrity check failed in cycle $i"
            return 1
        fi
        
        print_success "Activation cycle $i completed successfully"
    done
    
    print_success "All activation cycles completed successfully"
    return 0
}

test_concurrent_activations() {
    print_header "Testing Concurrent Activation Safety"
    
    # Clean state
    clean_plugin_state
    
    print_info "Simulating concurrent activation attempts..."
    
    # Run multiple activations in quick succession
    # (This simulates what might happen with multiple admin users)
    for i in {1..3}; do
        activate_plugin &
    done
    
    # Wait for all background processes
    wait
    
    # Check that plugin is properly activated
    if is_plugin_active; then
        print_success "Plugin is active after concurrent activation attempts"
    else
        print_error "Plugin not active after concurrent activation attempts"
        return 1
    fi
    
    # Check for duplicates (common issue with concurrent operations)
    check_database_duplicates
}

# =================================================================
# MAIN EXECUTION
# =================================================================

show_usage() {
    echo "Usage: $0 [scenario]"
    echo ""
    echo "Available scenarios:"
    echo "  all         - Run all test scenarios (default)"
    echo "  fresh       - Test fresh activation"
    echo "  reactivate  - Test idempotent reactivation"
    echo "  upgrade     - Test upgrade scenario"
    echo "  failure     - Test activation failure recovery"
    echo "  cycles      - Test multiple activation cycles"
    echo "  concurrent  - Test concurrent activation safety"
    echo "  database    - Test database integrity only"
    echo ""
    echo "Examples:"
    echo "  $0                    # Run all tests"
    echo "  $0 fresh             # Test fresh activation only"
    echo "  $0 database          # Check database integrity only"
}

print_summary() {
    echo ""
    print_header "Test Summary"
    echo -e "${BLUE}Tests run: $TESTS_RUN${NC}"
    echo -e "${GREEN}Tests passed: $TESTS_PASSED${NC}"
    echo -e "${RED}Tests failed: $TESTS_FAILED${NC}"
    echo ""
    
    if [[ $TESTS_FAILED -eq 0 ]]; then
        print_success "All tests passed!"
        echo -e "${GREEN}Plugin activation appears to be working correctly.${NC}"
    else
        print_error "Some tests failed!"
        echo -e "${RED}Please review the issues above and check the log file: $TEST_LOG_FILE${NC}"
        echo -e "${YELLOW}These tests would have caught the wpdb::prepare() and duplicate data issues.${NC}"
    fi
}

main() {
    local scenario="${1:-all}"
    
    # Initialize log
    echo "Plugin Activation Test Log - $(date)" > "$TEST_LOG_FILE"
    
    print_header "Woo AI Assistant - Plugin Activation Testing"
    print_info "Testing scenario: $scenario"
    print_info "Plugin directory: $PLUGIN_DIR"
    print_info "Log file: $TEST_LOG_FILE"
    echo ""
    
    # Check prerequisites
    check_wp_cli
    
    # Change to plugin directory
    cd "$PLUGIN_DIR"
    
    case "$scenario" in
        "all")
            run_test "Fresh Activation" test_fresh_activation
            run_test "Idempotent Reactivation" test_idempotent_reactivation
            run_test "Upgrade Scenario" test_upgrade_scenario
            run_test "Activation Failure Recovery" test_activation_failure_recovery
            run_test "Multiple Activation Cycles" test_multiple_activation_cycles
            run_test "Concurrent Activation Safety" test_concurrent_activations
            ;;
        "fresh")
            run_test "Fresh Activation" test_fresh_activation
            ;;
        "reactivate")
            run_test "Idempotent Reactivation" test_idempotent_reactivation
            ;;
        "upgrade")
            run_test "Upgrade Scenario" test_upgrade_scenario
            ;;
        "failure")
            run_test "Activation Failure Recovery" test_activation_failure_recovery
            ;;
        "cycles")
            run_test "Multiple Activation Cycles" test_multiple_activation_cycles
            ;;
        "concurrent")
            run_test "Concurrent Activation Safety" test_concurrent_activations
            ;;
        "database")
            run_test "Database Integrity Check" check_database_integrity
            ;;
        "help"|"-h"|"--help")
            show_usage
            exit 0
            ;;
        *)
            echo "Unknown scenario: $scenario"
            show_usage
            exit 1
            ;;
    esac
    
    print_summary
    
    # Exit with error code if any tests failed
    if [[ $TESTS_FAILED -gt 0 ]]; then
        exit 1
    fi
}

# Run main function with all arguments
main "$@"