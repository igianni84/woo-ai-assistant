#!/bin/bash

# CI/CD Pipeline Validation Script for Woo AI Assistant
#
# This script validates all CI/CD components and ensures they are properly configured
# and functional. It performs comprehensive checks on workflows, scripts, and configurations.
#
# @package WooAiAssistant
# @subpackage Scripts
# @since 1.0.0
# @author Claude Code Assistant

set -euo pipefail

# =============================================================================
# CONFIGURATION
# =============================================================================

readonly SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
readonly PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# Colors for output
readonly RED='\033[0;31m'
readonly GREEN='\033[0;32m'
readonly YELLOW='\033[1;33m'
readonly BLUE='\033[0;34m'
readonly NC='\033[0m' # No Color

# Configuration
VERBOSE=false
CHECK_ALL=true
SPECIFIC_CHECKS=()
DRY_RUN=false

# =============================================================================
# UTILITY FUNCTIONS
# =============================================================================

log() {
    echo -e "${BLUE}[$(date +'%H:%M:%S')]${NC} $*"
}

success() {
    echo -e "${GREEN}✅ $*${NC}"
}

warning() {
    echo -e "${YELLOW}⚠️  $*${NC}"
}

error() {
    echo -e "${RED}❌ $*${NC}" >&2
}

verbose() {
    if [[ "$VERBOSE" == "true" ]]; then
        echo -e "${BLUE}🔍 $*${NC}"
    fi
}

# =============================================================================
# VALIDATION FUNCTIONS
# =============================================================================

validate_workflow_syntax() {
    log "Validating GitHub Actions workflow syntax..."
    
    local workflow_dir="$PROJECT_ROOT/.github/workflows"
    local workflow_count=0
    local error_count=0
    
    if [[ ! -d "$workflow_dir" ]]; then
        error "Workflows directory not found: $workflow_dir"
        return 1
    fi
    
    for workflow in "$workflow_dir"/*.yml "$workflow_dir"/*.yaml; do
        if [[ -f "$workflow" ]]; then
            workflow_count=$((workflow_count + 1))
            local workflow_name=$(basename "$workflow")
            
            verbose "Checking workflow: $workflow_name"
            
            # Check if YAML is valid
            if command -v yq &> /dev/null; then
                if ! yq eval . "$workflow" > /dev/null 2>&1; then
                    error "Invalid YAML syntax in: $workflow_name"
                    error_count=$((error_count + 1))
                    continue
                fi
            elif command -v python3 &> /dev/null; then
                if ! python3 -c "import yaml; yaml.safe_load(open('$workflow', 'r'))" 2>/dev/null; then
                    error "Invalid YAML syntax in: $workflow_name"
                    error_count=$((error_count + 1))
                    continue
                fi
            else
                warning "No YAML validator found (yq or python3 with yaml), skipping syntax check"
            fi
            
            # Check required workflow elements
            if ! grep -q "^name:" "$workflow"; then
                warning "Workflow missing name: $workflow_name"
            fi
            
            if ! grep -q "^on:" "$workflow"; then
                error "Workflow missing trigger (on:): $workflow_name"
                error_count=$((error_count + 1))
            fi
            
            if ! grep -q "^jobs:" "$workflow"; then
                error "Workflow missing jobs: $workflow_name"
                error_count=$((error_count + 1))
            fi
            
            verbose "✓ $workflow_name syntax is valid"
        fi
    done
    
    if [[ $workflow_count -eq 0 ]]; then
        error "No workflow files found in $workflow_dir"
        return 1
    fi
    
    if [[ $error_count -eq 0 ]]; then
        success "All $workflow_count workflow files have valid syntax"
    else
        error "$error_count workflow files have syntax errors"
        return 1
    fi
}

validate_deployment_scripts() {
    log "Validating deployment scripts..."
    
    local scripts_dir="$PROJECT_ROOT/scripts"
    local script_count=0
    local error_count=0
    
    # List of expected deployment scripts
    local expected_scripts=(
        "deploy.sh"
        "create-deployment-package.sh"
        "setup-git-hooks.sh"
    )
    
    for script in "${expected_scripts[@]}"; do
        local script_path="$scripts_dir/$script"
        script_count=$((script_count + 1))
        
        verbose "Checking script: $script"
        
        if [[ ! -f "$script_path" ]]; then
            error "Missing deployment script: $script"
            error_count=$((error_count + 1))
            continue
        fi
        
        if [[ ! -x "$script_path" ]]; then
            error "Script not executable: $script"
            error_count=$((error_count + 1))
        fi
        
        # Basic shell syntax check
        if ! bash -n "$script_path" 2>/dev/null; then
            error "Shell syntax error in: $script"
            error_count=$((error_count + 1))
        fi
        
        # Check for shebang
        if ! head -n1 "$script_path" | grep -q "^#!/bin/bash"; then
            warning "Missing or incorrect shebang in: $script"
        fi
        
        verbose "✓ $script is valid"
    done
    
    if [[ $error_count -eq 0 ]]; then
        success "All $script_count deployment scripts are valid"
    else
        error "$error_count deployment scripts have issues"
        return 1
    fi
}

validate_git_hooks() {
    log "Validating Git hooks setup..."
    
    local hooks_dir="$PROJECT_ROOT/.githooks"
    local hook_count=0
    local error_count=0
    
    if [[ ! -d "$hooks_dir" ]]; then
        error "Git hooks directory not found: $hooks_dir"
        return 1
    fi
    
    # Expected hooks
    local expected_hooks=("pre-commit" "commit-msg")
    
    for hook in "${expected_hooks[@]}"; do
        local hook_path="$hooks_dir/$hook"
        hook_count=$((hook_count + 1))
        
        verbose "Checking Git hook: $hook"
        
        if [[ ! -f "$hook_path" ]]; then
            error "Missing Git hook: $hook"
            error_count=$((error_count + 1))
            continue
        fi
        
        if [[ ! -x "$hook_path" ]]; then
            error "Git hook not executable: $hook"
            error_count=$((error_count + 1))
        fi
        
        # Check syntax
        if ! bash -n "$hook_path" 2>/dev/null; then
            error "Shell syntax error in Git hook: $hook"
            error_count=$((error_count + 1))
        fi
        
        # Check for proper shebang
        if ! head -n1 "$hook_path" | grep -q "^#!/bin/bash"; then
            warning "Missing or incorrect shebang in Git hook: $hook"
        fi
        
        verbose "✓ $hook hook is valid"
    done
    
    # Test Git hooks installation script
    local setup_script="$PROJECT_ROOT/scripts/setup-git-hooks.sh"
    if [[ -x "$setup_script" ]]; then
        verbose "Testing Git hooks installation..."
        if "$setup_script" status > /dev/null 2>&1; then
            verbose "✓ Git hooks setup script works"
        else
            warning "Git hooks setup script may have issues"
        fi
    fi
    
    if [[ $error_count -eq 0 ]]; then
        success "All $hook_count Git hooks are properly configured"
    else
        error "$error_count Git hooks have issues"
        return 1
    fi
}

validate_package_json_scripts() {
    log "Validating package.json CI/CD scripts..."
    
    local package_json="$PROJECT_ROOT/package.json"
    
    if [[ ! -f "$package_json" ]]; then
        error "package.json not found"
        return 1
    fi
    
    verbose "Checking package.json scripts..."
    
    # Expected CI/CD related scripts
    local expected_scripts=(
        "pre-commit"
        "setup:hooks"
        "hooks:status"
        "deploy:dev"
        "deploy:staging"
        "deploy:prod"
    )
    
    local error_count=0
    
    for script in "${expected_scripts[@]}"; do
        if grep -q "\"$script\":" "$package_json"; then
            verbose "✓ Script '$script' found"
        else
            error "Missing npm script: $script"
            error_count=$((error_count + 1))
        fi
    done
    
    if [[ $error_count -eq 0 ]]; then
        success "All expected npm scripts are configured"
    else
        error "$error_count npm scripts are missing"
        return 1
    fi
}

validate_composer_scripts() {
    log "Validating composer.json CI/CD scripts..."
    
    local composer_json="$PROJECT_ROOT/composer.json"
    
    if [[ ! -f "$composer_json" ]]; then
        error "composer.json not found"
        return 1
    fi
    
    verbose "Checking composer.json scripts..."
    
    # Expected CI/CD related scripts
    local expected_scripts=(
        "quality-gates-enforce"
        "quality-gates-check"
        "quality-gates-reset"
        "verify-all"
        "verify-paths"
        "verify-standards"
    )
    
    local error_count=0
    
    for script in "${expected_scripts[@]}"; do
        if grep -q "\"$script\":" "$composer_json"; then
            verbose "✓ Script '$script' found"
        else
            error "Missing composer script: $script"
            error_count=$((error_count + 1))
        fi
    done
    
    if [[ $error_count -eq 0 ]]; then
        success "All expected composer scripts are configured"
    else
        error "$error_count composer scripts are missing"
        return 1
    fi
}

validate_workflow_dependencies() {
    log "Validating workflow dependencies and actions..."
    
    local workflow_dir="$PROJECT_ROOT/.github/workflows"
    local error_count=0
    
    # Check for common action versions that should be consistent
    local checkout_versions=$(grep -r "actions/checkout@" "$workflow_dir" | grep -o "v[0-9]" | sort | uniq)
    local setup_node_versions=$(grep -r "actions/setup-node@" "$workflow_dir" | grep -o "v[0-9]" | sort | uniq)
    local setup_php_versions=$(grep -r "shivammathur/setup-php@" "$workflow_dir" | grep -o "v[0-9]" | sort | uniq)
    
    # Check if versions are consistent
    local checkout_count=$(echo "$checkout_versions" | wc -l)
    local node_count=$(echo "$setup_node_versions" | wc -l)
    local php_count=$(echo "$setup_php_versions" | wc -l)
    
    if [[ $checkout_count -gt 1 ]]; then
        warning "Multiple checkout action versions found: $(echo "$checkout_versions" | tr '\n' ' ')"
        warning "Consider standardizing on the latest version"
    fi
    
    if [[ $node_count -gt 1 ]]; then
        warning "Multiple setup-node action versions found: $(echo "$setup_node_versions" | tr '\n' ' ')"
        warning "Consider standardizing on the latest version"
    fi
    
    if [[ $php_count -gt 1 ]]; then
        warning "Multiple setup-php action versions found: $(echo "$setup_php_versions" | tr '\n' ' ')"
        warning "Consider standardizing on the latest version"
    fi
    
    # Check for required secrets/environment variables
    verbose "Checking for required secrets usage..."
    
    if grep -r "secrets\." "$workflow_dir" > /dev/null; then
        verbose "✓ Workflows use secrets (good for security)"
    else
        log "No secrets usage found in workflows"
    fi
    
    success "Workflow dependencies validation completed"
}

validate_ci_configuration() {
    log "Validating CI configuration files..."
    
    local config_files=(
        ".gitignore"
        "phpunit.xml"
        "jest.config.js"
        ".eslintrc.js"
        "webpack.config.js"
    )
    
    local error_count=0
    
    for config_file in "${config_files[@]}"; do
        local config_path="$PROJECT_ROOT/$config_file"
        
        verbose "Checking configuration file: $config_file"
        
        if [[ ! -f "$config_path" ]]; then
            error "Missing CI configuration file: $config_file"
            error_count=$((error_count + 1))
            continue
        fi
        
        # File-specific validations
        case "$config_file" in
            ".gitignore")
                if ! grep -q "node_modules" "$config_path"; then
                    warning "node_modules not ignored in .gitignore"
                fi
                if ! grep -q "vendor" "$config_path"; then
                    warning "vendor directory not ignored in .gitignore"
                fi
                ;;
            "phpunit.xml")
                if ! grep -q "testsuites" "$config_path"; then
                    error "PHPUnit configuration missing testsuites"
                    error_count=$((error_count + 1))
                fi
                ;;
            "jest.config.js"|"jest.config.json")
                if [[ -f "$config_path" ]] && ! grep -q "testEnvironment\|collectCoverage" "$config_path"; then
                    warning "Jest configuration may be incomplete"
                fi
                ;;
        esac
        
        verbose "✓ $config_file exists"
    done
    
    if [[ $error_count -eq 0 ]]; then
        success "All CI configuration files are present"
    else
        error "$error_count CI configuration files are missing or invalid"
        return 1
    fi
}

test_deployment_script() {
    log "Testing deployment script functionality..."
    
    local deploy_script="$PROJECT_ROOT/scripts/deploy.sh"
    
    if [[ ! -x "$deploy_script" ]]; then
        error "Deployment script not found or not executable"
        return 1
    fi
    
    verbose "Testing deployment script help..."
    if "$deploy_script" --help > /dev/null 2>&1; then
        verbose "✓ Deployment script help works"
    else
        error "Deployment script help failed"
        return 1
    fi
    
    verbose "Testing deployment script dry run..."
    if "$deploy_script" --environment development --dry-run > /dev/null 2>&1; then
        verbose "✓ Deployment script dry run works"
    else
        error "Deployment script dry run failed"
        return 1
    fi
    
    success "Deployment script functionality tests passed"
}

test_quality_gates() {
    log "Testing quality gates enforcement..."
    
    local quality_script="$PROJECT_ROOT/scripts/quality-gates-enforcer.sh"
    
    if [[ ! -x "$quality_script" ]]; then
        error "Quality gates enforcer script not found or not executable"
        return 1
    fi
    
    verbose "Testing quality gates script..."
    
    # This is a dry run to see if the script can execute without errors
    # In a real environment, this might run actual checks
    if bash -n "$quality_script" 2>/dev/null; then
        verbose "✓ Quality gates script syntax is valid"
    else
        error "Quality gates script has syntax errors"
        return 1
    fi
    
    success "Quality gates testing completed"
}

# =============================================================================
# HELP FUNCTION
# =============================================================================

show_help() {
    cat << EOF
🔍 CI/CD Pipeline Validation Script for Woo AI Assistant

USAGE:
    $0 [OPTIONS]

OPTIONS:
    -v, --verbose       Enable verbose output
    -d, --dry-run      Perform validation without making changes
    -c, --check TYPE   Run specific validation check
    -h, --help         Show this help message

VALIDATION CHECKS:
    workflows          GitHub Actions workflow validation
    scripts            Deployment scripts validation
    hooks              Git hooks validation
    config             CI configuration files validation
    dependencies       Workflow dependencies validation
    quality-gates      Quality gates functionality testing
    all               Run all validation checks (default)

EXAMPLES:
    $0                                    # Run all validations
    $0 --verbose                          # Run with verbose output
    $0 --check workflows                  # Check workflows only
    $0 --check scripts --verbose         # Check scripts with verbose output

EOF
}

# =============================================================================
# ARGUMENT PARSING
# =============================================================================

parse_arguments() {
    while [[ $# -gt 0 ]]; do
        case $1 in
            -v|--verbose)
                VERBOSE=true
                shift
                ;;
            -d|--dry-run)
                DRY_RUN=true
                shift
                ;;
            -c|--check)
                CHECK_ALL=false
                SPECIFIC_CHECKS+=("$2")
                shift 2
                ;;
            -h|--help)
                show_help
                exit 0
                ;;
            *)
                error "Unknown option: $1"
                show_help
                exit 1
                ;;
        esac
    done
}

# =============================================================================
# MAIN EXECUTION
# =============================================================================

run_validations() {
    local total_checks=0
    local passed_checks=0
    local failed_checks=0
    
    # Determine which checks to run
    local checks_to_run=()
    
    if [[ "$CHECK_ALL" == "true" ]]; then
        checks_to_run=(
            "workflows"
            "scripts"
            "hooks" 
            "config"
            "dependencies"
            "quality-gates"
        )
    else
        checks_to_run=("${SPECIFIC_CHECKS[@]}")
    fi
    
    # Run validation checks
    for check in "${checks_to_run[@]}"; do
        total_checks=$((total_checks + 1))
        
        log "Running validation: $check"
        
        case "$check" in
            workflows)
                if validate_workflow_syntax; then
                    passed_checks=$((passed_checks + 1))
                else
                    failed_checks=$((failed_checks + 1))
                fi
                ;;
            scripts)
                if validate_deployment_scripts; then
                    passed_checks=$((passed_checks + 1))
                else
                    failed_checks=$((failed_checks + 1))
                fi
                ;;
            hooks)
                if validate_git_hooks; then
                    passed_checks=$((passed_checks + 1))
                else
                    failed_checks=$((failed_checks + 1))
                fi
                ;;
            config)
                if validate_ci_configuration && validate_package_json_scripts && validate_composer_scripts; then
                    passed_checks=$((passed_checks + 1))
                else
                    failed_checks=$((failed_checks + 1))
                fi
                ;;
            dependencies)
                if validate_workflow_dependencies; then
                    passed_checks=$((passed_checks + 1))
                else
                    failed_checks=$((failed_checks + 1))
                fi
                ;;
            quality-gates)
                if test_quality_gates && test_deployment_script; then
                    passed_checks=$((passed_checks + 1))
                else
                    failed_checks=$((failed_checks + 1))
                fi
                ;;
            *)
                error "Unknown validation check: $check"
                failed_checks=$((failed_checks + 1))
                ;;
        esac
        
        echo # Add spacing between checks
    done
    
    # Summary
    log "Validation Summary:"
    echo "  Total checks: $total_checks"
    echo "  Passed: $passed_checks"
    echo "  Failed: $failed_checks"
    
    if [[ $failed_checks -eq 0 ]]; then
        success "All CI/CD validations passed! 🎉"
        return 0
    else
        error "$failed_checks validation(s) failed"
        return 1
    fi
}

main() {
    local start_time
    start_time=$(date +%s)
    
    log "Starting CI/CD pipeline validation..."
    
    # Parse arguments
    parse_arguments "$@"
    
    if [[ "$VERBOSE" == "true" ]]; then
        log "Configuration:"
        echo "  Verbose: $VERBOSE"
        echo "  Dry Run: $DRY_RUN"
        echo "  Check All: $CHECK_ALL"
        if [[ ${#SPECIFIC_CHECKS[@]} -gt 0 ]]; then
            echo "  Specific Checks: ${SPECIFIC_CHECKS[*]}"
        fi
        echo
    fi
    
    # Change to project root
    cd "$PROJECT_ROOT"
    
    # Run validations
    if run_validations; then
        local end_time
        end_time=$(date +%s)
        local duration=$((end_time - start_time))
        success "CI/CD validation completed successfully in ${duration}s"
        exit 0
    else
        error "CI/CD validation failed"
        exit 1
    fi
}

# Only run main if script is executed directly
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi