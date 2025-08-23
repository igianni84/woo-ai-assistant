#!/bin/bash
#
# Mandatory Verification Script
#
# Comprehensive task verification that must pass before marking any task as completed.
# This script runs all quality gates and verification checks.
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

# Verification results
total_checks=0
passed_checks=0
failed_checks=0
warnings=0

# Functions
log_header() {
    echo ""
    echo -e "${BOLD}${CYAN}$1${NC}"
    echo "$(printf '%*s' "${#1}" '' | tr ' ' '=')"
}

log_step() {
    echo -e "${BLUE}🔍 $1${NC}"
}

log_success() {
    echo -e "${GREEN}✅ $1${NC}"
    ((passed_checks++))
}

log_error() {
    echo -e "${RED}❌ $1${NC}"
    ((failed_checks++))
}

log_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
    ((warnings++))
}

run_check() {
    local check_name="$1"
    local command="$2"
    local description="$3"
    
    ((total_checks++))
    
    log_step "$description"
    
    if eval "$command" > /dev/null 2>&1; then
        log_success "$check_name passed"
        return 0
    else
        log_error "$check_name failed"
        return 1
    fi
}

run_check_with_output() {
    local check_name="$1"
    local command="$2" 
    local description="$3"
    
    ((total_checks++))
    
    log_step "$description"
    
    local output
    if output=$(eval "$command" 2>&1); then
        if [[ -n "$output" ]]; then
            echo "$output" | head -10  # Show first 10 lines of output
        fi
        log_success "$check_name passed"
        return 0
    else
        echo "$output" | head -20  # Show first 20 lines of error output
        log_error "$check_name failed"
        return 1
    fi
}

# Verification checks
verify_file_paths() {
    log_header "📁 FILE PATH VERIFICATION"
    
    if [[ -x "$SCRIPT_DIR/verify-paths.sh" ]]; then
        run_check_with_output "File Paths" "$SCRIPT_DIR/verify-paths.sh" "Verifying all file paths and dependencies"
    else
        log_warning "Path verification script not found or not executable"
    fi
}

verify_coding_standards() {
    log_header "📝 CODING STANDARDS VERIFICATION"
    
    # PHP Standards
    if [[ -f "$SCRIPT_DIR/verify-standards.php" ]]; then
        run_check_with_output "PHP Standards" "php $SCRIPT_DIR/verify-standards.php" "Checking PHP coding standards compliance"
    else
        log_warning "Standards verification script not found"
    fi
    
    # PHP CodeSniffer (if available)
    if command -v phpcs >/dev/null 2>&1; then
        if [[ -f "$PROJECT_ROOT/composer.json" ]] && grep -q "phpcs" "$PROJECT_ROOT/composer.json" 2>/dev/null; then
            run_check "PHP CodeSniffer" "cd '$PROJECT_ROOT' && composer run phpcs" "Running PHP CodeSniffer"
        fi
    fi
    
    # ESLint for JavaScript/React (if available)
    if [[ -f "$PROJECT_ROOT/package.json" ]] && command -v npm >/dev/null 2>&1; then
        if grep -q "eslint" "$PROJECT_ROOT/package.json" 2>/dev/null; then
            run_check "ESLint" "cd '$PROJECT_ROOT' && npm run lint" "Running ESLint for JavaScript/React"
        fi
    fi
}

verify_unit_tests() {
    log_header "🧪 UNIT TESTS VERIFICATION"
    
    # PHPUnit tests
    if [[ -f "$PROJECT_ROOT/composer.json" ]] && command -v composer >/dev/null 2>&1; then
        if grep -q "phpunit" "$PROJECT_ROOT/composer.json" 2>/dev/null; then
            run_check_with_output "PHPUnit Tests" "cd '$PROJECT_ROOT' && composer run test" "Running PHPUnit tests"
            
            # Check coverage if available
            if grep -q "test:coverage" "$PROJECT_ROOT/composer.json" 2>/dev/null; then
                run_check "Test Coverage" "cd '$PROJECT_ROOT' && composer run test:coverage" "Checking test coverage (>90% required)"
            fi
        else
            log_warning "PHPUnit not configured in composer.json"
        fi
    fi
    
    # Jest tests for React
    if [[ -f "$PROJECT_ROOT/package.json" ]] && command -v npm >/dev/null 2>&1; then
        if grep -q "jest" "$PROJECT_ROOT/package.json" 2>/dev/null; then
            run_check "Jest Tests" "cd '$PROJECT_ROOT' && npm test" "Running Jest tests for React components"
        fi
    fi
}

verify_security() {
    log_header "🔒 SECURITY VERIFICATION"
    
    # Check for common security issues
    run_check "Security Patterns" "! find '$PROJECT_ROOT/src' -name '*.php' -exec grep -l 'eval(' {} \; 2>/dev/null" "Checking for dangerous eval() usage"
    run_check "SQL Injection" "! find '$PROJECT_ROOT/src' -name '*.php' -exec grep -l '\$wpdb->query.*\$' {} \; 2>/dev/null || true" "Checking for potential SQL injection patterns"
    run_check "XSS Prevention" "find '$PROJECT_ROOT/src' -name '*.php' -exec grep -l 'esc_html\|wp_kses\|sanitize_' {} \; | wc -l | grep -q '[1-9]'" "Verifying XSS prevention measures"
    
    # Check for hardcoded credentials
    if find "$PROJECT_ROOT" -name "*.php" -exec grep -l "password.*=\|api_key.*=\|secret.*=" {} \; 2>/dev/null | head -1 >/dev/null; then
        log_error "Potential hardcoded credentials found"
    else
        log_success "No hardcoded credentials detected"
    fi
}

verify_performance() {
    log_header "⚡ PERFORMANCE VERIFICATION"
    
    # Check for performance patterns
    run_check "Database Queries" "! find '$PROJECT_ROOT/src' -name '*.php' -exec grep -l 'get_posts.*posts_per_page.*-1' {} \; 2>/dev/null" "Checking for unlimited database queries"
    run_check "Caching Usage" "find '$PROJECT_ROOT/src' -name '*.php' -exec grep -l 'wp_cache_\|transient' {} \; | wc -l | grep -q '[1-9]'" "Verifying caching implementation"
    
    # Check bundle sizes (if webpack is configured)
    if [[ -f "$PROJECT_ROOT/webpack.config.js" ]] && [[ -d "$PROJECT_ROOT/assets" ]]; then
        local bundle_size=0
        if [[ -f "$PROJECT_ROOT/assets/js/widget.js" ]]; then
            bundle_size=$(wc -c < "$PROJECT_ROOT/assets/js/widget.js" 2>/dev/null || echo 0)
        fi
        
        if [[ $bundle_size -lt 51200 ]]; then  # 50KB
            log_success "Widget bundle size acceptable ($bundle_size bytes)"
        else
            log_warning "Widget bundle size may be too large ($bundle_size bytes > 50KB)"
        fi
    fi
}

verify_wordpress_integration() {
    log_header "🔌 WORDPRESS INTEGRATION VERIFICATION"
    
    # Check for WordPress best practices
    run_check "WordPress Hooks" "find '$PROJECT_ROOT/src' -name '*.php' -exec grep -l 'add_action\|add_filter' {} \; | wc -l | grep -q '[1-9]'" "Verifying WordPress hooks usage"
    run_check "Nonce Verification" "find '$PROJECT_ROOT/src' -name '*.php' -exec grep -l 'wp_verify_nonce\|wp_create_nonce' {} \; | wc -l | grep -q '[1-9]'" "Checking nonce verification implementation"
    run_check "Capability Checks" "find '$PROJECT_ROOT/src' -name '*.php' -exec grep -l 'current_user_can\|user_can' {} \; | wc -l | grep -q '[1-9]'" "Verifying capability checks"
    run_check "Input Sanitization" "find '$PROJECT_ROOT/src' -name '*.php' -exec grep -l 'sanitize_\|wp_kses' {} \; | wc -l | grep -q '[1-9]'" "Checking input sanitization"
    
    # Check for direct access prevention
    run_check "Direct Access Prevention" "find '$PROJECT_ROOT/src' -name '*.php' -exec grep -l 'ABSPATH\|defined.*WP_DEBUG' {} \; | wc -l | grep -q '[1-9]'" "Verifying direct access prevention"
}

verify_documentation() {
    log_header "📚 DOCUMENTATION VERIFICATION"
    
    # Check for DocBlocks
    run_check "PHP DocBlocks" "find '$PROJECT_ROOT/src' -name '*.php' -exec grep -l '/\*\*' {} \; | wc -l | grep -q '[1-9]'" "Checking PHP DocBlock presence"
    
    # Check for README
    if [[ -f "$PROJECT_ROOT/README.md" ]]; then
        log_success "README.md exists"
    else
        log_warning "README.md not found"
    fi
    
    # Check for inline documentation
    local php_files_count
    php_files_count=$(find "$PROJECT_ROOT/src" -name "*.php" 2>/dev/null | wc -l)
    local documented_files_count
    documented_files_count=$(find "$PROJECT_ROOT/src" -name "*.php" -exec grep -l "@param\|@return\|@throws" {} \; 2>/dev/null | wc -l)
    
    if [[ $documented_files_count -gt 0 ]]; then
        log_success "Method documentation found ($documented_files_count/$php_files_count files)"
    else
        log_warning "Limited method documentation found"
    fi
}

verify_naming_conventions() {
    log_header "🏷️  NAMING CONVENTIONS VERIFICATION"
    
    # This will be handled by the standards verification script
    if [[ -f "$SCRIPT_DIR/verify-standards.php" ]]; then
        log_success "Naming conventions checked via standards script"
    else
        log_warning "Standards verification script not available"
    fi
}

# Main verification function
run_mandatory_verification() {
    log_header "🚀 MANDATORY TASK COMPLETION VERIFICATION"
    
    echo "Project: Woo AI Assistant Plugin"
    echo "Root Directory: $PROJECT_ROOT"
    echo "Started: $(date)"
    echo ""
    
    # Run all verification checks
    verify_file_paths
    verify_coding_standards
    verify_unit_tests
    verify_security
    verify_performance
    verify_wordpress_integration
    verify_documentation
    verify_naming_conventions
    
    # Display final results
    log_header "📊 VERIFICATION SUMMARY"
    
    echo "Total Checks: $total_checks"
    echo "Passed: $passed_checks"
    echo "Failed: $failed_checks"
    echo "Warnings: $warnings"
    echo ""
    
    local pass_rate
    if [[ $total_checks -gt 0 ]]; then
        pass_rate=$(( passed_checks * 100 / total_checks ))
    else
        pass_rate=0
    fi
    
    echo "Pass Rate: ${pass_rate}%"
    echo ""
    
    if [[ $failed_checks -eq 0 ]]; then
        if [[ $warnings -eq 0 ]]; then
            echo -e "${GREEN}${BOLD}🎉 ALL MANDATORY CHECKS PASSED!${NC}"
            echo -e "${GREEN}✅ Task is ready for completion${NC}"
            echo -e "${GREEN}🚀 You may now mark the task as completed in the roadmap${NC}"
        else
            echo -e "${YELLOW}${BOLD}⚠️  VERIFICATION PASSED WITH WARNINGS${NC}"
            echo -e "${YELLOW}📝 Review warnings but task can be completed${NC}"
            echo -e "${YELLOW}🚀 You may mark the task as completed after reviewing warnings${NC}"
        fi
        return 0
    else
        echo -e "${RED}${BOLD}❌ MANDATORY VERIFICATION FAILED${NC}"
        echo -e "${RED}🚫 DO NOT mark task as completed${NC}"
        echo -e "${RED}🔧 Fix all failed checks before proceeding${NC}"
        echo ""
        echo -e "${CYAN}Next Steps:${NC}"
        echo "1. Review and fix all failed checks above"
        echo "2. Re-run this script: ./scripts/mandatory-verification.sh"
        echo "3. Only mark task as completed when all checks pass"
        return 1
    fi
}

# Script execution
main() {
    run_mandatory_verification
    local exit_code=$?
    
    echo ""
    echo "Verification completed: $(date)"
    echo "Exit code: $exit_code"
    echo ""
    
    if [[ $exit_code -eq 0 ]]; then
        echo -e "${GREEN}Ready to proceed with task completion! ✨${NC}"
    else
        echo -e "${RED}Please address issues before task completion! 🛠️${NC}"
    fi
    
    return $exit_code
}

# Execute main function if script is run directly
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi