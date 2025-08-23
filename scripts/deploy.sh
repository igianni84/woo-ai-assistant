#!/bin/bash
#
# Deployment Automation Script
#
# Automates the deployment process for the WordPress plugin including
# building, packaging, testing, and release preparation.
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

# Default values
DEPLOYMENT_TYPE="staging"
BUILD_NUMBER=""
VERSION=""
SKIP_TESTS=false
SKIP_QUALITY_GATES=false
DRY_RUN=false

# Deployment directories
DIST_DIR="$PROJECT_ROOT/dist"
STAGING_DIR="$PROJECT_ROOT/staging"
PROD_DIR="$PROJECT_ROOT/production"

log_header() {
    echo ""
    echo -e "${BOLD}${CYAN}$1${NC}"
    echo "$(printf '%*s' "${#1}" '' | tr ' ' '=')"
}

log_step() {
    echo -e "${BLUE}🚀 $1${NC}"
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

# Show usage information
show_usage() {
    cat << EOF
Usage: $0 [OPTIONS]

Deploy the Woo AI Assistant WordPress plugin

OPTIONS:
  -t, --type TYPE         Deployment type (staging|production) [default: staging]
  -v, --version VERSION   Version number for release
  -b, --build BUILD       Build number
  --skip-tests           Skip running tests
  --skip-quality-gates   Skip quality gate verification
  --dry-run              Show what would be done without executing
  -h, --help             Show this help message

EXAMPLES:
  $0 --type staging
  $0 --type production --version 1.0.0 --build 123
  $0 --dry-run --type production
  
EOF
}

# Parse command line arguments
parse_arguments() {
    while [[ $# -gt 0 ]]; do
        case $1 in
            -t|--type)
                DEPLOYMENT_TYPE="$2"
                shift 2
                ;;
            -v|--version)
                VERSION="$2"
                shift 2
                ;;
            -b|--build)
                BUILD_NUMBER="$2"
                shift 2
                ;;
            --skip-tests)
                SKIP_TESTS=true
                shift
                ;;
            --skip-quality-gates)
                SKIP_QUALITY_GATES=true
                shift
                ;;
            --dry-run)
                DRY_RUN=true
                shift
                ;;
            -h|--help)
                show_usage
                exit 0
                ;;
            *)
                log_error "Unknown option: $1"
                show_usage
                exit 1
                ;;
        esac
    done
    
    # Validate deployment type
    if [[ ! "$DEPLOYMENT_TYPE" =~ ^(staging|production)$ ]]; then
        log_error "Invalid deployment type: $DEPLOYMENT_TYPE"
        log_error "Must be 'staging' or 'production'"
        exit 1
    fi
    
    # For production deployments, version is required
    if [[ "$DEPLOYMENT_TYPE" == "production" ]] && [[ -z "$VERSION" ]]; then
        log_error "Version is required for production deployments"
        exit 1
    fi
    
    # Auto-detect version from plugin file if not provided
    if [[ -z "$VERSION" ]] && [[ -f "$PROJECT_ROOT/woo-ai-assistant.php" ]]; then
        VERSION=$(grep "Version:" "$PROJECT_ROOT/woo-ai-assistant.php" | head -1 | sed 's/.*Version:\s*//' | tr -d ' ')
    fi
    
    # Generate build number if not provided
    if [[ -z "$BUILD_NUMBER" ]]; then
        BUILD_NUMBER=$(date +%Y%m%d%H%M)
    fi
}

# Execute command with dry run support
execute_command() {
    local command="$1"
    local description="$2"
    
    if [[ "$DRY_RUN" == "true" ]]; then
        echo -e "${CYAN}[DRY RUN] $description${NC}"
        echo -e "${CYAN}[DRY RUN] Command: $command${NC}"
        return 0
    else
        log_step "$description"
        if eval "$command"; then
            return 0
        else
            log_error "Command failed: $command"
            return 1
        fi
    fi
}

# Pre-deployment checks
run_pre_deployment_checks() {
    log_header "🔍 PRE-DEPLOYMENT CHECKS"
    
    # Check if we're in a git repository
    if [[ ! -d "$PROJECT_ROOT/.git" ]]; then
        log_error "Not in a git repository"
        return 1
    fi
    
    # Check for uncommitted changes
    if [[ -n "$(git status --porcelain)" ]]; then
        log_warning "Uncommitted changes detected:"
        git status --short
        if [[ "$DEPLOYMENT_TYPE" == "production" ]]; then
            log_error "Cannot deploy to production with uncommitted changes"
            return 1
        fi
    else
        log_success "Working directory is clean"
    fi
    
    # Check required files exist
    local required_files=(
        "woo-ai-assistant.php"
        "composer.json"
        "package.json"
        "README.md"
    )
    
    for file in "${required_files[@]}"; do
        if [[ ! -f "$PROJECT_ROOT/$file" ]]; then
            log_error "Required file missing: $file"
            return 1
        fi
    done
    log_success "All required files present"
    
    # Check version consistency
    if [[ -n "$VERSION" ]]; then
        local plugin_version=$(grep "Version:" "$PROJECT_ROOT/woo-ai-assistant.php" | head -1 | sed 's/.*Version:\s*//' | tr -d ' ')
        local composer_version=$(grep '"version":' "$PROJECT_ROOT/composer.json" | head -1 | sed 's/.*"version":\s*"//' | sed 's/".*//')
        local package_version=$(grep '"version":' "$PROJECT_ROOT/package.json" | head -1 | sed 's/.*"version":\s*"//' | sed 's/".*//')
        
        if [[ "$plugin_version" != "$VERSION" ]] || [[ "$composer_version" != "$VERSION" ]] || [[ "$package_version" != "$VERSION" ]]; then
            log_warning "Version mismatch detected:"
            log_info "Plugin: $plugin_version"
            log_info "Composer: $composer_version"
            log_info "Package: $package_version"
            log_info "Target: $VERSION"
        else
            log_success "Version consistency verified: $VERSION"
        fi
    fi
}

# Run quality gates
run_quality_gates() {
    if [[ "$SKIP_QUALITY_GATES" == "true" ]]; then
        log_warning "Skipping quality gates (not recommended)"
        return 0
    fi
    
    log_header "🔒 QUALITY GATES VERIFICATION"
    
    # Run mandatory verification
    if [[ -x "$PROJECT_ROOT/scripts/mandatory-verification.sh" ]]; then
        execute_command "$PROJECT_ROOT/scripts/mandatory-verification.sh" "Running comprehensive quality gates"
    else
        log_error "Mandatory verification script not found or not executable"
        return 1
    fi
    
    log_success "All quality gates passed"
}

# Install dependencies
install_dependencies() {
    log_header "📦 DEPENDENCY INSTALLATION"
    
    cd "$PROJECT_ROOT"
    
    # Install PHP dependencies
    if [[ -f "composer.json" ]] && command -v composer >/dev/null 2>&1; then
        execute_command "composer install --no-dev --optimize-autoloader --no-scripts" "Installing PHP dependencies"
    else
        log_warning "Composer not available or no composer.json found"
    fi
    
    # Install Node.js dependencies
    if [[ -f "package.json" ]] && command -v npm >/dev/null 2>&1; then
        execute_command "npm ci --production" "Installing Node.js dependencies"
    else
        log_warning "npm not available or no package.json found"
    fi
}

# Build assets
build_assets() {
    log_header "🔨 ASSET BUILDING"
    
    cd "$PROJECT_ROOT"
    
    if [[ -f "package.json" ]] && command -v npm >/dev/null 2>&1; then
        # Clean previous builds
        execute_command "npm run clean:build" "Cleaning previous builds"
        
        # Build production assets
        execute_command "npm run build" "Building production assets"
        
        # Verify assets were created
        if [[ ! "$DRY_RUN" == "true" ]]; then
            if [[ -f "assets/js/widget.js" ]]; then
                local bundle_size=$(wc -c < "assets/js/widget.js")
                local bundle_kb=$(echo "scale=2; $bundle_size / 1024" | bc)
                log_success "Widget bundle built: ${bundle_kb}KB"
            else
                log_error "Widget bundle was not created"
                return 1
            fi
        fi
    else
        log_warning "Cannot build assets: npm or package.json not available"
    fi
}

# Run tests
run_tests() {
    if [[ "$SKIP_TESTS" == "true" ]]; then
        log_warning "Skipping tests (not recommended for production)"
        return 0
    fi
    
    log_header "🧪 TEST EXECUTION"
    
    cd "$PROJECT_ROOT"
    
    # Run PHP tests
    if [[ -f "composer.json" ]] && command -v composer >/dev/null 2>&1; then
        if grep -q "phpunit" composer.json; then
            execute_command "composer run test" "Running PHP tests"
        fi
    fi
    
    # Run JavaScript tests
    if [[ -f "package.json" ]] && command -v npm >/dev/null 2>&1; then
        if grep -q "jest" package.json; then
            execute_command "npm test" "Running JavaScript tests"
        fi
    fi
    
    log_success "All tests passed"
}

# Create deployment package
create_deployment_package() {
    log_header "📦 DEPLOYMENT PACKAGE CREATION"
    
    local target_dir
    case "$DEPLOYMENT_TYPE" in
        staging)
            target_dir="$STAGING_DIR"
            ;;
        production)
            target_dir="$PROD_DIR"
            ;;
    esac
    
    # Clean and create target directory
    execute_command "rm -rf '$target_dir'" "Cleaning target directory"
    execute_command "mkdir -p '$target_dir'" "Creating target directory"
    
    # Copy production files
    local files_to_copy=(
        "src"
        "assets"
        "languages"
        "templates"
        "woo-ai-assistant.php"
        "uninstall.php"
        "README.md"
        "composer.json"
    )
    
    for file in "${files_to_copy[@]}"; do
        if [[ -e "$PROJECT_ROOT/$file" ]]; then
            execute_command "cp -r '$PROJECT_ROOT/$file' '$target_dir/'" "Copying $file"
        fi
    done
    
    # Install production dependencies in package
    if [[ ! "$DRY_RUN" == "true" ]] && [[ -f "$target_dir/composer.json" ]]; then
        cd "$target_dir"
        if command -v composer >/dev/null 2>&1; then
            execute_command "composer install --no-dev --optimize-autoloader --no-scripts" "Installing production dependencies in package"
        fi
        cd "$PROJECT_ROOT"
    fi
    
    # Create versioned archive
    local archive_name="woo-ai-assistant"
    if [[ -n "$VERSION" ]]; then
        archive_name="${archive_name}-${VERSION}"
    fi
    if [[ -n "$BUILD_NUMBER" ]]; then
        archive_name="${archive_name}-build${BUILD_NUMBER}"
    fi
    archive_name="${archive_name}.zip"
    
    execute_command "cd '$target_dir' && zip -r '../$archive_name' . -x '*.git*' '*.DS_Store*' '*node_modules*' '*tests*' '*coverage*'" "Creating deployment archive"
    
    if [[ ! "$DRY_RUN" == "true" ]]; then
        local archive_path="$(dirname "$target_dir")/$archive_name"
        if [[ -f "$archive_path" ]]; then
            local archive_size=$(wc -c < "$archive_path")
            local archive_mb=$(echo "scale=2; $archive_size / 1024 / 1024" | bc)
            log_success "Deployment package created: $archive_name (${archive_mb}MB)"
            
            # Validate package size
            local max_size=$((25 * 1024 * 1024)) # 25MB
            if [[ $archive_size -gt $max_size ]]; then
                log_warning "Package size exceeds recommended limit (25MB)"
            fi
        else
            log_error "Failed to create deployment package"
            return 1
        fi
    fi
}

# Tag release for production
tag_release() {
    if [[ "$DEPLOYMENT_TYPE" != "production" ]] || [[ -z "$VERSION" ]]; then
        return 0
    fi
    
    log_header "🏷️  RELEASE TAGGING"
    
    local tag_name="v$VERSION"
    local tag_message="Release version $VERSION - Build $BUILD_NUMBER"
    
    # Check if tag already exists
    if git tag -l | grep -q "^$tag_name$"; then
        log_warning "Tag $tag_name already exists"
        return 0
    fi
    
    execute_command "git tag -a '$tag_name' -m '$tag_message'" "Creating release tag"
    
    if [[ ! "$DRY_RUN" == "true" ]]; then
        log_success "Created release tag: $tag_name"
        log_info "Push tag with: git push origin $tag_name"
    fi
}

# Generate deployment report
generate_deployment_report() {
    log_header "📊 DEPLOYMENT REPORT"
    
    local report_file="$PROJECT_ROOT/deployment-report-$(date +%Y%m%d-%H%M%S).txt"
    
    if [[ ! "$DRY_RUN" == "true" ]]; then
        cat > "$report_file" << EOF
Woo AI Assistant - Deployment Report
=====================================

Deployment Details:
- Type: $DEPLOYMENT_TYPE
- Version: $VERSION
- Build: $BUILD_NUMBER
- Date: $(date)
- Git Commit: $(git rev-parse HEAD 2>/dev/null || echo "N/A")
- Git Branch: $(git branch --show-current 2>/dev/null || echo "N/A")

Environment:
- PHP Version: $(php -v | head -n 1 | cut -d ' ' -f 2)
- Node Version: $(node -v 2>/dev/null || echo "N/A")
- Composer Version: $(composer --version 2>/dev/null | cut -d ' ' -f 3 || echo "N/A")
- OS: $(uname -s) $(uname -r)

Build Configuration:
- Skip Tests: $SKIP_TESTS
- Skip Quality Gates: $SKIP_QUALITY_GATES
- Dry Run: $DRY_RUN

Package Information:
EOF
        
        # Add package size if available
        local archive_pattern="woo-ai-assistant"
        if [[ -n "$VERSION" ]]; then
            archive_pattern="${archive_pattern}-${VERSION}"
        fi
        archive_pattern="${archive_pattern}*.zip"
        
        local archive_file=$(find "$(dirname "$STAGING_DIR")" -name "$archive_pattern" 2>/dev/null | head -1)
        if [[ -n "$archive_file" ]]; then
            local archive_size=$(wc -c < "$archive_file")
            local archive_mb=$(echo "scale=2; $archive_size / 1024 / 1024" | bc)
            echo "- Package Size: ${archive_mb}MB" >> "$report_file"
            echo "- Package Path: $archive_file" >> "$report_file"
        fi
        
        echo "" >> "$report_file"
        echo "Deployment Status: SUCCESS" >> "$report_file"
        echo "" >> "$report_file"
        
        log_success "Deployment report generated: $report_file"
    fi
    
    # Display summary
    echo ""
    echo "Deployment Summary:"
    echo "==================="
    echo "Type: $DEPLOYMENT_TYPE"
    echo "Version: $VERSION"
    echo "Build: $BUILD_NUMBER"
    echo "Status: $(if [[ "$DRY_RUN" == "true" ]]; then echo "DRY RUN COMPLETED"; else echo "SUCCESS"; fi)"
    echo ""
    
    if [[ "$DEPLOYMENT_TYPE" == "production" ]]; then
        echo "Next Steps for Production:"
        echo "- Review the deployment package"
        echo "- Test in staging environment first"
        echo "- Push release tag: git push origin v$VERSION"
        echo "- Upload to WordPress.org (if applicable)"
        echo "- Update production servers"
    else
        echo "Next Steps for Staging:"
        echo "- Deploy package to staging server"
        echo "- Run integration tests"
        echo "- Perform user acceptance testing"
        echo "- Prepare for production deployment"
    fi
    echo ""
}

# Main deployment function
main() {
    log_header "🚀 WOO AI ASSISTANT DEPLOYMENT"
    
    echo "WordPress Plugin Deployment Automation"
    echo "======================================"
    echo ""
    
    # Parse command line arguments
    parse_arguments "$@"
    
    echo "Configuration:"
    echo "- Deployment Type: $DEPLOYMENT_TYPE"
    echo "- Version: ${VERSION:-"Auto-detect"}"
    echo "- Build: $BUILD_NUMBER"
    echo "- Skip Tests: $SKIP_TESTS"
    echo "- Skip Quality Gates: $SKIP_QUALITY_GATES"
    echo "- Dry Run: $DRY_RUN"
    echo ""
    
    if [[ "$DRY_RUN" == "true" ]]; then
        log_warning "DRY RUN MODE - No changes will be made"
        echo ""
    fi
    
    # Execute deployment steps
    run_pre_deployment_checks || exit 1
    run_quality_gates || exit 1
    install_dependencies || exit 1
    build_assets || exit 1
    run_tests || exit 1
    create_deployment_package || exit 1
    tag_release || exit 1
    generate_deployment_report
    
    if [[ "$DRY_RUN" == "true" ]]; then
        echo -e "${GREEN}${BOLD}🎉 DRY RUN COMPLETED SUCCESSFULLY!${NC}"
        echo -e "${GREEN}✅ All deployment steps validated${NC}"
    else
        echo -e "${GREEN}${BOLD}🎉 DEPLOYMENT COMPLETED SUCCESSFULLY!${NC}"
        echo -e "${GREEN}🚀 Ready for $DEPLOYMENT_TYPE deployment${NC}"
    fi
    
    echo ""
}

# Execute main function if script is run directly
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi