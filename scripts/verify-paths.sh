#!/bin/bash
#
# File Path Verification Script
#
# Verifies that all referenced files exist and paths are correct.
# This script must pass before marking any task as completed.
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
readonly BOLD='\033[1m'
readonly NC='\033[0m' # No Color

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
SRC_DIR="$PROJECT_ROOT/src"

# Counters
errors=0
warnings=0

# Functions
log_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

log_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

log_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
    ((warnings++))
}

log_error() {
    echo -e "${RED}❌ $1${NC}"
    ((errors++))
}

log_header() {
    echo -e "\n${BOLD}${BLUE}$1${NC}"
    echo "$(printf '%*s' "${#1}" '' | tr ' ' '=')"
}

# Check if directory exists
check_directory() {
    local dir="$1"
    local description="$2"
    
    if [[ ! -d "$dir" ]]; then
        log_error "$description directory not found: $dir"
        return 1
    fi
    
    log_success "$description directory exists: $dir"
    return 0
}

# Check if file exists
check_file() {
    local file="$1"
    local description="$2"
    
    if [[ ! -f "$file" ]]; then
        log_error "$description file not found: $file"
        return 1
    fi
    
    if [[ ! -r "$file" ]]; then
        log_error "$description file not readable: $file"
        return 1
    fi
    
    log_success "$description file exists: $(basename "$file")"
    return 0
}

# Check require/include statements in PHP files
check_php_includes() {
    local file="$1"
    
    if [[ ! -f "$file" ]]; then
        return 0
    fi
    
    # Extract actual require/include statements (not comments or variable names)
    grep -n "^\s*\(require\|include\)\(_once\)\?\s*[(\s]*['\"]" "$file" | while IFS=: read -r line_num line_content; do
        # Extract the file path from the line
        if [[ $line_content =~ (require|include)(_once)?[[:space:]]*[\(]?[[:space:]]*[\'\"](.*?)[\'\"] ]]; then
            local included_path="${BASH_REMATCH[3]}"
            local full_path
            
            # Handle different path formats
            if [[ "$included_path" == /* ]]; then
                # Absolute path
                full_path="$included_path"
            elif [[ "$included_path" == __DIR__* ]] || [[ $line_content =~ ABSPATH ]]; then
                # __DIR__ relative path or ABSPATH - skip validation (runtime dependent)
                continue
            else
                # Relative path
                full_path="$(dirname "$file")/$included_path"
            fi
            
            if [[ ! -f "$full_path" ]]; then
                log_error "Required file not found: $included_path (referenced in $(basename "$file"):$line_num)"
            fi
        fi
    done
}

# Check PSR-4 namespace structure
check_psr4_structure() {
    log_info "Checking PSR-4 namespace structure..."
    
    if [[ ! -d "$SRC_DIR" ]]; then
        log_error "Source directory not found: $SRC_DIR"
        return 1
    fi
    
    find "$SRC_DIR" -name "*.php" | while read -r file; do
        # Extract namespace from file
        local namespace_line
        namespace_line=$(head -20 "$file" | grep "^namespace " | head -1)
        
        if [[ -z "$namespace_line" ]]; then
            log_warning "No namespace found in $(basename "$file")"
            continue
        fi
        
        # Extract namespace
        local namespace
        namespace=$(echo "$namespace_line" | sed 's/namespace //; s/;//' | tr -d '\r\n')
        
        # Extract class name from file
        local class_name
        class_name=$(basename "$file" .php)
        
        # Check if namespace matches directory structure
        local expected_namespace="WooAiAssistant"
        local relative_path
        relative_path=$(dirname "${file#$SRC_DIR/}")
        
        if [[ "$relative_path" != "." ]]; then
            expected_namespace="$expected_namespace\\$(echo "$relative_path" | tr '/' '\\')"
        fi
        
        if [[ "$namespace" != "$expected_namespace" ]]; then
            log_warning "Namespace mismatch in $class_name: expected '$expected_namespace', found '$namespace'"
        fi
    done
}

# Check WordPress and WooCommerce file references
check_wp_wc_references() {
    log_info "Checking WordPress/WooCommerce file references..."
    
    find "$SRC_DIR" -name "*.php" | while read -r file; do
        # Check for direct file includes to WP/WC core (which might break)
        # Skip legitimate WordPress core references
        if grep -q "wp-includes\|wp-admin\|wp-content" "$file" 2>/dev/null; then
            # Allow legitimate WordPress upgrade.php references (required for dbDelta)
            if grep -q "wp-admin/includes/upgrade.php" "$file"; then
                log_info "Legitimate WordPress upgrade.php reference found in $(basename "$file")"
            # Allow legitimate WordPress script dependencies
            elif grep -q "\['wp-admin', 'dashicons'\]" "$file"; then
                log_info "Legitimate WordPress script dependency found in $(basename "$file")"
            elif grep -q "\['wp-admin'\]" "$file"; then
                log_info "Legitimate WordPress script dependency found in $(basename "$file")"
            else
                log_warning "Direct WordPress core file reference found in $(basename "$file")"
            fi
        fi
        
        if grep -q "woocommerce/includes\|woocommerce/templates" "$file" 2>/dev/null; then
            log_warning "Direct WooCommerce core file reference found in $(basename "$file")"
        fi
    done
}

# Check template files exist
check_template_files() {
    log_info "Checking template files..."
    
    local templates_dir="$PROJECT_ROOT/templates"
    
    if [[ -d "$templates_dir" ]]; then
        find "$templates_dir" -name "*.php" | while read -r template; do
            check_file "$template" "Template"
            
            # Check if template has proper header
            if ! head -10 "$template" | grep -q "Template\|@package" 2>/dev/null; then
                log_warning "Template file missing proper header: $(basename "$template")"
            fi
        done
    fi
}

# Check asset files exist
check_asset_files() {
    log_info "Checking asset files..."
    
    local assets_dir="$PROJECT_ROOT/assets"
    
    if [[ -d "$assets_dir" ]]; then
        # Check CSS files
        if [[ -d "$assets_dir/css" ]]; then
            for css_file in "$assets_dir/css"/*.css; do
                if [[ -f "$css_file" ]]; then
                    check_file "$css_file" "CSS asset"
                fi
            done
        fi
        
        # Check JS files
        if [[ -d "$assets_dir/js" ]]; then
            for js_file in "$assets_dir/js"/*.js; do
                if [[ -f "$js_file" ]]; then
                    check_file "$js_file" "JS asset"
                fi
            done
        fi
        
        # Check image files
        if [[ -d "$assets_dir/images" ]]; then
            for img_file in "$assets_dir/images"/*; do
                if [[ -f "$img_file" ]]; then
                    check_file "$img_file" "Image asset"
                fi
            done
        fi
    fi
}

# Check React component files
check_react_files() {
    log_info "Checking React component files..."
    
    local widget_src_dir="$PROJECT_ROOT/widget-src"
    
    if [[ -d "$widget_src_dir" ]]; then
        find "$widget_src_dir" -name "*.js" -o -name "*.jsx" -o -name "*.ts" -o -name "*.tsx" | while read -r react_file; do
            check_file "$react_file" "React component"
            
            # Check for proper import/export syntax
            if ! grep -q "import\|export" "$react_file" 2>/dev/null; then
                log_warning "React file may be missing imports/exports: $(basename "$react_file")"
            fi
        done
    fi
}

# Main verification function
run_verification() {
    log_header "🔍 FILE PATH VERIFICATION"
    
    echo "Project Root: $PROJECT_ROOT"
    echo "Source Directory: $SRC_DIR"
    echo ""
    
    # Check main directories
    log_header "📁 Directory Structure"
    check_directory "$SRC_DIR" "Source"
    check_directory "$PROJECT_ROOT/assets" "Assets" || log_warning "Assets directory not found (may not be created yet)"
    check_directory "$PROJECT_ROOT/widget-src" "Widget source" || log_warning "Widget source directory not found (may not be created yet)"
    
    # Check main plugin file
    log_header "🔌 Main Plugin Files"
    check_file "$PROJECT_ROOT/woo-ai-assistant.php" "Main plugin"
    check_file "$PROJECT_ROOT/uninstall.php" "Uninstall script" || log_warning "Uninstall script not found (may not be created yet)"
    check_file "$PROJECT_ROOT/composer.json" "Composer config" || log_warning "Composer config not found (may not be created yet)"
    
    # Check PHP source files
    if [[ -d "$SRC_DIR" ]]; then
        log_header "🔍 PHP Include/Require Verification"
        
        # Count PHP files with actual include/require statements (not comments)
        php_files_with_includes=0
        if find "$SRC_DIR" -name "*.php" -exec grep -l "^\s*\(require\|include\)\(_once\)\?\s*[(\s]*['\"]" {} \; 2>/dev/null | head -1 >/dev/null; then
            find "$SRC_DIR" -name "*.php" | while read -r php_file; do
                if grep -q "^\s*\(require\|include\)\(_once\)\?\s*[(\s]*['\"]" "$php_file" 2>/dev/null; then
                    check_php_includes "$php_file"
                    ((php_files_with_includes++))
                fi
            done
            
            if [[ $php_files_with_includes -eq 0 ]]; then
                log_info "No PHP files with include/require statements found (acceptable during initial development)"
            fi
        else
            log_info "No PHP files with include/require statements found (acceptable during initial development)"
        fi
        
        log_header "📦 PSR-4 Structure Verification"
        check_psr4_structure
    fi
    
    # Check other file types
    check_wp_wc_references
    check_template_files
    check_asset_files
    check_react_files
    
    # Display results
    log_header "📊 VERIFICATION RESULTS"
    
    if [[ $errors -eq 0 && $warnings -eq 0 ]]; then
        echo -e "${GREEN}${BOLD}🎉 ALL CHECKS PASSED!${NC}"
        echo -e "${GREEN}✅ No errors found${NC}"
        echo -e "${GREEN}✅ No warnings found${NC}"
        echo ""
        echo -e "${GREEN}🚀 File paths are ready for task completion.${NC}"
        return 0
    fi
    
    if [[ $errors -gt 0 ]]; then
        echo -e "${RED}${BOLD}❌ VERIFICATION FAILED${NC}"
        echo -e "${RED}Errors found: $errors${NC}"
    fi
    
    if [[ $warnings -gt 0 ]]; then
        echo -e "${YELLOW}⚠️  Warnings found: $warnings${NC}"
    fi
    
    if [[ $errors -eq 0 ]]; then
        echo -e "${YELLOW}⚠️  VERIFICATION PASSED WITH WARNINGS${NC}"
        echo -e "${YELLOW}Review warnings before proceeding.${NC}"
        return 0
    else
        echo -e "${RED}🚫 Fix errors before proceeding with task completion.${NC}"
        return 1
    fi
}

# Script execution
main() {
    run_verification
    local exit_code=$?
    
    echo ""
    echo "Script completed with exit code: $exit_code"
    echo ""
    
    return $exit_code
}

# Execute main function if script is run directly
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi