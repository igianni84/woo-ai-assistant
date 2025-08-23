#!/bin/bash
#
# Security Vulnerability Scanner
#
# Comprehensive security scanning for WordPress plugin development.
# Detects potential security vulnerabilities and best practice violations.
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

# Counters
TOTAL_CHECKS=0
PASSED_CHECKS=0
FAILED_CHECKS=0
WARNINGS=0

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
    ((PASSED_CHECKS++))
}

log_error() {
    echo -e "${RED}❌ $1${NC}"
    ((FAILED_CHECKS++))
}

log_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
    ((WARNINGS++))
}

log_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

run_check() {
    local check_name="$1"
    local command="$2"
    local description="$3"
    
    ((TOTAL_CHECKS++))
    
    log_step "$description"
    
    if eval "$command" > /dev/null 2>&1; then
        log_success "$check_name passed"
        return 0
    else
        log_error "$check_name failed"
        return 1
    fi
}

# Check for dangerous PHP functions
check_dangerous_functions() {
    log_header "🚨 DANGEROUS PHP FUNCTIONS SCAN"
    
    local dangerous_functions=("eval" "exec" "system" "shell_exec" "passthru" "file_get_contents" "file_put_contents" "fopen" "fwrite")
    local found_issues=false
    
    for func in "${dangerous_functions[@]}"; do
        log_step "Checking for dangerous function: $func()"
        
        if find "$PROJECT_ROOT/src" -name "*.php" -type f -exec grep -l "${func}(" {} \; 2>/dev/null | head -1 >/dev/null; then
            local files=$(find "$PROJECT_ROOT/src" -name "*.php" -type f -exec grep -l "${func}(" {} \; 2>/dev/null)
            log_error "Found dangerous function ${func}() in files:"
            echo "$files" | while read -r file; do
                echo -e "${RED}  - $file${NC}"
                grep -n "${func}(" "$file" | head -3 | while read -r line; do
                    echo -e "${RED}    $line${NC}"
                done
            done
            found_issues=true
        else
            log_success "No ${func}() usage found"
        fi
        ((TOTAL_CHECKS++))
    done
    
    if [ "$found_issues" = true ]; then
        ((FAILED_CHECKS++))
        return 1
    else
        ((PASSED_CHECKS++))
        return 0
    fi
}

# Check for hardcoded secrets
check_hardcoded_secrets() {
    log_header "🔐 HARDCODED SECRETS SCAN"
    
    local secret_patterns=(
        "password.*=.*['\"][^'\"]{3,}['\"]"
        "api_key.*=.*['\"][^'\"]{10,}['\"]"
        "secret.*=.*['\"][^'\"]{5,}['\"]"
        "token.*=.*['\"][^'\"]{10,}['\"]"
        "auth.*=.*['\"][^'\"]{5,}['\"]"
    )
    
    local found_secrets=false
    
    for pattern in "${secret_patterns[@]}"; do
        local pattern_name=$(echo "$pattern" | cut -d'*' -f1)
        log_step "Scanning for hardcoded ${pattern_name}s"
        
        if find "$PROJECT_ROOT" -name "*.php" -not -path "*/vendor/*" -not -path "*/node_modules/*" -type f -exec grep -l "$pattern" {} \; 2>/dev/null | head -1 >/dev/null; then
            local files=$(find "$PROJECT_ROOT" -name "*.php" -not -path "*/vendor/*" -not -path "*/node_modules/*" -type f -exec grep -l "$pattern" {} \; 2>/dev/null)
            log_error "Found potential hardcoded ${pattern_name}s in:"
            echo "$files" | while read -r file; do
                echo -e "${RED}  - $file${NC}"
            done
            found_secrets=true
        else
            log_success "No hardcoded ${pattern_name}s found"
        fi
        ((TOTAL_CHECKS++))
    done
    
    if [ "$found_secrets" = true ]; then
        ((FAILED_CHECKS++))
        return 1
    else
        ((PASSED_CHECKS++))
        return 0
    fi
}

# Check WordPress security best practices
check_wordpress_security() {
    log_header "🛡️  WORDPRESS SECURITY BEST PRACTICES"
    
    # Check for nonce verification
    log_step "Checking for nonce verification in forms"
    local files_with_post=$(find "$PROJECT_ROOT/src" -name "*.php" -type f -exec grep -l "\$_POST\|\$_GET\|\$_REQUEST" {} \; 2>/dev/null || true)
    
    if [[ -n "$files_with_post" ]]; then
        local missing_nonce=false
        while read -r file; do
            if ! grep -q "wp_verify_nonce\|wp_create_nonce\|check_admin_referer" "$file"; then
                if [ "$missing_nonce" = false ]; then
                    log_error "Files handling user input without nonce verification:"
                    missing_nonce=true
                fi
                echo -e "${RED}  - $file${NC}"
            fi
        done <<< "$files_with_post"
        
        if [ "$missing_nonce" = true ]; then
            ((FAILED_CHECKS++))
        else
            log_success "All form handling files use nonce verification"
            ((PASSED_CHECKS++))
        fi
    else
        log_success "No direct user input handling found"
        ((PASSED_CHECKS++))
    fi
    ((TOTAL_CHECKS++))
    
    # Check for capability checks in admin files
    log_step "Checking for capability checks in admin functions"
    local admin_files=$(find "$PROJECT_ROOT/src/Admin" -name "*.php" -type f 2>/dev/null || true)
    
    if [[ -n "$admin_files" ]]; then
        local missing_caps=false
        while read -r file; do
            if ! grep -q "current_user_can\|user_can\|is_admin\|wp_die" "$file"; then
                if [ "$missing_caps" = false ]; then
                    log_warning "Admin files that may need capability checks:"
                    missing_caps=true
                fi
                echo -e "${YELLOW}  - $file${NC}"
            fi
        done <<< "$admin_files"
        
        if [ "$missing_caps" = true ]; then
            ((WARNINGS++))
        else
            log_success "All admin files have capability checks"
            ((PASSED_CHECKS++))
        fi
    else
        log_success "No admin files found"
        ((PASSED_CHECKS++))
    fi
    ((TOTAL_CHECKS++))
    
    # Check for input sanitization
    log_step "Checking for input sanitization functions"
    local sanitization_functions="sanitize_text_field\|sanitize_textarea_field\|sanitize_email\|wp_kses\|esc_html\|esc_attr"
    
    if find "$PROJECT_ROOT/src" -name "*.php" -type f -exec grep -l "$sanitization_functions" {} \; 2>/dev/null | head -1 >/dev/null; then
        log_success "Input sanitization functions found"
        ((PASSED_CHECKS++))
    else
        log_warning "No input sanitization functions detected"
        ((WARNINGS++))
    fi
    ((TOTAL_CHECKS++))
    
    # Check for direct file access prevention
    log_step "Checking for direct file access prevention"
    local php_files_without_protection=$(find "$PROJECT_ROOT/src" -name "*.php" -type f -exec grep -L "ABSPATH\|defined.*WP_DEBUG" {} \; 2>/dev/null || true)
    
    if [[ -n "$php_files_without_protection" ]]; then
        log_warning "PHP files without direct access protection:"
        echo "$php_files_without_protection" | while read -r file; do
            echo -e "${YELLOW}  - $file${NC}"
        done
        ((WARNINGS++))
    else
        log_success "All PHP files have direct access protection"
        ((PASSED_CHECKS++))
    fi
    ((TOTAL_CHECKS++))
}

# Check for SQL injection vulnerabilities
check_sql_injection() {
    log_header "💉 SQL INJECTION VULNERABILITY SCAN"
    
    # Check for direct $wpdb queries without prepare
    log_step "Checking for unsafe database queries"
    local unsafe_queries=$(find "$PROJECT_ROOT/src" -name "*.php" -type f -exec grep -n "\$wpdb->query.*\$\|\$wpdb->get_.*\$" {} + 2>/dev/null || true)
    
    if [[ -n "$unsafe_queries" ]]; then
        log_error "Potential SQL injection vulnerabilities found:"
        echo "$unsafe_queries" | while read -r line; do
            echo -e "${RED}  $line${NC}"
        done
        ((FAILED_CHECKS++))
    else
        log_success "No unsafe database queries detected"
        ((PASSED_CHECKS++))
    fi
    ((TOTAL_CHECKS++))
    
    # Check for proper prepare() usage
    log_step "Checking for prepared statement usage"
    if find "$PROJECT_ROOT/src" -name "*.php" -type f -exec grep -l "\$wpdb->prepare" {} \; 2>/dev/null | head -1 >/dev/null; then
        log_success "Prepared statements are being used"
        ((PASSED_CHECKS++))
    else
        log_warning "No prepared statements found (may be okay if no direct DB queries)"
        ((WARNINGS++))
    fi
    ((TOTAL_CHECKS++))
}

# Check for XSS vulnerabilities
check_xss_vulnerabilities() {
    log_header "🕷️  XSS VULNERABILITY SCAN"
    
    # Check for unescaped output
    log_step "Checking for potential XSS vulnerabilities"
    local potential_xss=$(find "$PROJECT_ROOT/src" -name "*.php" -type f -exec grep -n "echo.*\$\|print.*\$" {} + 2>/dev/null | grep -v "esc_html\|esc_attr\|wp_kses" || true)
    
    if [[ -n "$potential_xss" ]]; then
        log_warning "Potential XSS vulnerabilities (unescaped output):"
        echo "$potential_xss" | head -10 | while read -r line; do
            echo -e "${YELLOW}  $line${NC}"
        done
        if [[ $(echo "$potential_xss" | wc -l) -gt 10 ]]; then
            echo -e "${YELLOW}  ... and $(( $(echo "$potential_xss" | wc -l) - 10 )) more${NC}"
        fi
        ((WARNINGS++))
    else
        log_success "No obvious XSS vulnerabilities found"
        ((PASSED_CHECKS++))
    fi
    ((TOTAL_CHECKS++))
    
    # Check for output escaping functions
    log_step "Checking for output escaping function usage"
    local escaping_functions="esc_html\|esc_attr\|esc_url\|wp_kses"
    
    if find "$PROJECT_ROOT/src" -name "*.php" -type f -exec grep -l "$escaping_functions" {} \; 2>/dev/null | head -1 >/dev/null; then
        log_success "Output escaping functions are being used"
        ((PASSED_CHECKS++))
    else
        log_warning "No output escaping functions detected"
        ((WARNINGS++))
    fi
    ((TOTAL_CHECKS++))
}

# Check file permissions and structure
check_file_permissions() {
    log_header "📁 FILE PERMISSIONS & STRUCTURE SCAN"
    
    # Check for files with overly permissive permissions
    log_step "Checking file permissions"
    local world_writable=$(find "$PROJECT_ROOT" -type f -perm -002 -not -path "*/node_modules/*" -not -path "*/vendor/*" 2>/dev/null || true)
    
    if [[ -n "$world_writable" ]]; then
        log_warning "World-writable files found:"
        echo "$world_writable" | while read -r file; do
            echo -e "${YELLOW}  - $file${NC}"
        done
        ((WARNINGS++))
    else
        log_success "No world-writable files found"
        ((PASSED_CHECKS++))
    fi
    ((TOTAL_CHECKS++))
    
    # Check for sensitive files in wrong locations
    log_step "Checking for sensitive files in public locations"
    local sensitive_patterns=("*.env" "*.log" "*config*.php" "*secret*" "*private*")
    local found_sensitive=false
    
    for pattern in "${sensitive_patterns[@]}"; do
        local found_files=$(find "$PROJECT_ROOT" -name "$pattern" -not -path "*/vendor/*" -not -path "*/node_modules/*" -not -path "*/.git/*" 2>/dev/null || true)
        if [[ -n "$found_files" ]]; then
            if [ "$found_sensitive" = false ]; then
                log_warning "Potentially sensitive files found:"
                found_sensitive=true
            fi
            echo "$found_files" | while read -r file; do
                echo -e "${YELLOW}  - $file${NC}"
            done
        fi
    done
    
    if [ "$found_sensitive" = true ]; then
        ((WARNINGS++))
    else
        log_success "No sensitive files in public locations"
        ((PASSED_CHECKS++))
    fi
    ((TOTAL_CHECKS++))
}

# Check for dependency vulnerabilities
check_dependency_vulnerabilities() {
    log_header "📦 DEPENDENCY VULNERABILITY SCAN"
    
    # Check Composer dependencies
    if [[ -f "$PROJECT_ROOT/composer.lock" ]]; then
        log_step "Checking Composer dependencies for security advisories"
        if command -v composer >/dev/null 2>&1; then
            if composer audit --working-dir="$PROJECT_ROOT" --locked >/dev/null 2>&1; then
                log_success "No known vulnerabilities in Composer dependencies"
                ((PASSED_CHECKS++))
            else
                log_error "Security vulnerabilities found in Composer dependencies"
                composer audit --working-dir="$PROJECT_ROOT" --locked 2>/dev/null || true
                ((FAILED_CHECKS++))
            fi
        else
            log_warning "Composer not available, skipping dependency check"
            ((WARNINGS++))
        fi
    else
        log_info "No composer.lock file found, skipping Composer security check"
        ((PASSED_CHECKS++))
    fi
    ((TOTAL_CHECKS++))
    
    # Check npm dependencies
    if [[ -f "$PROJECT_ROOT/package-lock.json" ]]; then
        log_step "Checking npm dependencies for security vulnerabilities"
        if command -v npm >/dev/null 2>&1; then
            if npm audit --audit-level=moderate --prefix="$PROJECT_ROOT" >/dev/null 2>&1; then
                log_success "No known vulnerabilities in npm dependencies"
                ((PASSED_CHECKS++))
            else
                log_error "Security vulnerabilities found in npm dependencies"
                npm audit --audit-level=moderate --prefix="$PROJECT_ROOT" 2>/dev/null || true
                ((FAILED_CHECKS++))
            fi
        else
            log_warning "npm not available, skipping dependency check"
            ((WARNINGS++))
        fi
    else
        log_info "No package-lock.json file found, skipping npm security check"
        ((PASSED_CHECKS++))
    fi
    ((TOTAL_CHECKS++))
}

# Generate security report
generate_security_report() {
    log_header "📊 SECURITY SCAN SUMMARY"
    
    local scan_date=$(date "+%Y-%m-%d %H:%M:%S")
    local report_file="$PROJECT_ROOT/security-report.txt"
    
    echo "Woo AI Assistant - Security Scan Report" > "$report_file"
    echo "=======================================" >> "$report_file"
    echo "Scan Date: $scan_date" >> "$report_file"
    echo "Project Root: $PROJECT_ROOT" >> "$report_file"
    echo "" >> "$report_file"
    echo "SUMMARY:" >> "$report_file"
    echo "- Total Checks: $TOTAL_CHECKS" >> "$report_file"
    echo "- Passed: $PASSED_CHECKS" >> "$report_file"
    echo "- Failed: $FAILED_CHECKS" >> "$report_file"
    echo "- Warnings: $WARNINGS" >> "$report_file"
    echo "" >> "$report_file"
    
    local pass_rate=0
    if [[ $TOTAL_CHECKS -gt 0 ]]; then
        pass_rate=$(( PASSED_CHECKS * 100 / TOTAL_CHECKS ))
    fi
    
    echo ""
    echo "Scan Date: $scan_date"
    echo "Total Checks: $TOTAL_CHECKS"
    echo "Passed: $PASSED_CHECKS"
    echo "Failed: $FAILED_CHECKS"
    echo "Warnings: $WARNINGS"
    echo "Pass Rate: ${pass_rate}%"
    echo ""
    
    if [[ $FAILED_CHECKS -eq 0 ]]; then
        if [[ $WARNINGS -eq 0 ]]; then
            echo -e "${GREEN}${BOLD}🎉 SECURITY SCAN PASSED!${NC}"
            echo -e "${GREEN}✅ No security vulnerabilities detected${NC}"
            echo -e "${GREEN}🔒 Your code follows security best practices${NC}"
        else
            echo -e "${YELLOW}${BOLD}⚠️  SECURITY SCAN PASSED WITH WARNINGS${NC}"
            echo -e "${YELLOW}📝 Review warnings and consider improvements${NC}"
            echo -e "${YELLOW}🔒 Overall security posture is acceptable${NC}"
        fi
        echo ""
        echo -e "${CYAN}📄 Detailed report saved to: $report_file${NC}"
        return 0
    else
        echo -e "${RED}${BOLD}❌ SECURITY SCAN FAILED${NC}"
        echo -e "${RED}🚨 Security vulnerabilities detected${NC}"
        echo -e "${RED}🔧 Fix all security issues before deployment${NC}"
        echo ""
        echo -e "${CYAN}📄 Detailed report saved to: $report_file${NC}"
        return 1
    fi
}

# Main function
main() {
    log_header "🔐 SECURITY VULNERABILITY SCANNER"
    echo "Woo AI Assistant Plugin - Comprehensive Security Analysis"
    echo "Project: $PROJECT_ROOT"
    echo "Started: $(date)"
    echo ""
    
    # Run all security checks
    check_dangerous_functions
    check_hardcoded_secrets
    check_wordpress_security
    check_sql_injection
    check_xss_vulnerabilities
    check_file_permissions
    check_dependency_vulnerabilities
    
    # Generate final report
    generate_security_report
}

# Execute main function if script is run directly
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi