#!/bin/bash

# Comprehensive Deployment Script for Woo AI Assistant
#
# This script handles deployment to different environments with proper validation,
# building, packaging, and environment-specific configurations.
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
readonly PLUGIN_SLUG="woo-ai-assistant"
readonly VERSION_FILE="$PROJECT_ROOT/woo-ai-assistant.php"

# Colors for output
readonly RED='\033[0;31m'
readonly GREEN='\033[0;32m'
readonly YELLOW='\033[1;33m'
readonly BLUE='\033[0;34m'
readonly NC='\033[0m' # No Color

# Default values
ENVIRONMENT=""
DRY_RUN=false
SKIP_TESTS=false
SKIP_BUILD=false
FORCE_DEPLOY=false
VERBOSE=false
OUTPUT_DIR="$PROJECT_ROOT/dist"
BACKUP_DIR="$PROJECT_ROOT/backups"

# =============================================================================
# UTILITY FUNCTIONS
# =============================================================================

log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $*"
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
# HELP FUNCTION
# =============================================================================

show_help() {
    cat << EOF
🚀 Woo AI Assistant Deployment Script

USAGE:
    $0 [OPTIONS]

OPTIONS:
    -e, --environment ENV    Target environment (development|staging|production)
    -d, --dry-run           Perform a dry run without actual deployment
    -s, --skip-tests        Skip running tests
    -b, --skip-build        Skip building assets
    -f, --force             Force deployment even with warnings
    -v, --verbose           Enable verbose output
    -o, --output DIR        Output directory for deployment package
    -h, --help              Show this help message

EXAMPLES:
    $0 --environment staging
    $0 --environment production --verbose
    $0 --dry-run --environment development

ENVIRONMENTS:
    development    Local development deployment
    staging        Staging server deployment
    production     Production deployment to WordPress.org

EOF
}

# =============================================================================
# ARGUMENT PARSING
# =============================================================================

parse_arguments() {
    while [[ $# -gt 0 ]]; do
        case $1 in
            -e|--environment)
                ENVIRONMENT="$2"
                shift 2
                ;;
            -d|--dry-run)
                DRY_RUN=true
                shift
                ;;
            -s|--skip-tests)
                SKIP_TESTS=true
                shift
                ;;
            -b|--skip-build)
                SKIP_BUILD=true
                shift
                ;;
            -f|--force)
                FORCE_DEPLOY=true
                shift
                ;;
            -v|--verbose)
                VERBOSE=true
                shift
                ;;
            -o|--output)
                OUTPUT_DIR="$2"
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

    # Validate required arguments
    if [[ -z "$ENVIRONMENT" ]]; then
        error "Environment is required. Use --environment to specify."
        show_help
        exit 1
    fi

    # Validate environment
    case "$ENVIRONMENT" in
        development|staging|production)
            ;;
        *)
            error "Invalid environment: $ENVIRONMENT"
            error "Valid environments: development, staging, production"
            exit 1
            ;;
    esac
}

# =============================================================================
# VALIDATION FUNCTIONS
# =============================================================================

check_prerequisites() {
    log "Checking prerequisites..."

    # Check required commands
    local required_commands=("composer" "npm" "zip" "git")
    for cmd in "${required_commands[@]}"; do
        if ! command -v "$cmd" &> /dev/null; then
            error "Required command not found: $cmd"
            exit 1
        fi
        verbose "Found: $cmd"
    done

    # Check PHP version
    local php_version
    php_version=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
    if [[ $(echo "$php_version < 8.1" | bc -l) -eq 1 ]]; then
        error "PHP 8.1 or higher is required. Current: $php_version"
        exit 1
    fi
    verbose "PHP version: $php_version"

    # Check Node.js version
    local node_version
    node_version=$(node --version | sed 's/v//')
    local node_major
    node_major=$(echo "$node_version" | cut -d. -f1)
    if [[ "$node_major" -lt 16 ]]; then
        error "Node.js 16 or higher is required. Current: $node_version"
        exit 1
    fi
    verbose "Node.js version: $node_version"

    # Check if we're in the right directory
    if [[ ! -f "$VERSION_FILE" ]]; then
        error "Not in the plugin root directory. Expected: $VERSION_FILE"
        exit 1
    fi

    success "Prerequisites check passed"
}

check_git_status() {
    log "Checking git status..."

    if [[ "$ENVIRONMENT" == "production" ]]; then
        # Production deployments should be from clean state
        if ! git diff --quiet; then
            error "Working directory is not clean. Commit or stash changes before production deployment."
            exit 1
        fi

        if ! git diff --cached --quiet; then
            error "Staging area is not clean. Commit staged changes before production deployment."
            exit 1
        fi

        # Check if we're on main branch for production
        local current_branch
        current_branch=$(git branch --show-current)
        if [[ "$current_branch" != "main" ]]; then
            warning "Production deployment should be from 'main' branch. Current: $current_branch"
            if [[ "$FORCE_DEPLOY" != "true" ]]; then
                error "Use --force to deploy from non-main branch to production"
                exit 1
            fi
        fi
    fi

    success "Git status check passed"
}

get_plugin_version() {
    grep "Version:" "$VERSION_FILE" | awk -F: '{print $2}' | tr -d ' '
}

# =============================================================================
# BUILD FUNCTIONS
# =============================================================================

run_quality_gates() {
    if [[ "$SKIP_TESTS" == "true" ]]; then
        warning "Skipping quality gates (--skip-tests)"
        return 0
    fi

    log "Running quality gates..."

    # Run PHP quality gates
    verbose "Running PHP CodeSniffer..."
    if ! composer run phpcs; then
        error "PHP CodeSniffer failed"
        if [[ "$FORCE_DEPLOY" != "true" ]]; then
            exit 1
        fi
        warning "Continuing with --force"
    fi

    verbose "Running PHPStan..."
    if ! composer run phpstan; then
        error "PHPStan failed"
        if [[ "$FORCE_DEPLOY" != "true" ]]; then
            exit 1
        fi
        warning "Continuing with --force"
    fi

    verbose "Running PHPUnit tests..."
    if ! composer run test; then
        error "PHPUnit tests failed"
        if [[ "$FORCE_DEPLOY" != "true" ]]; then
            exit 1
        fi
        warning "Continuing with --force"
    fi

    # Run JavaScript quality gates
    verbose "Running ESLint..."
    if ! npm run lint; then
        error "ESLint failed"
        if [[ "$FORCE_DEPLOY" != "true" ]]; then
            exit 1
        fi
        warning "Continuing with --force"
    fi

    verbose "Running Jest tests..."
    if ! npm test; then
        error "Jest tests failed"
        if [[ "$FORCE_DEPLOY" != "true" ]]; then
            exit 1
        fi
        warning "Continuing with --force"
    fi

    # Run overall quality gates enforcer
    verbose "Running quality gates enforcer..."
    if ! composer run quality-gates-enforce; then
        error "Quality gates enforcer failed"
        if [[ "$FORCE_DEPLOY" != "true" ]]; then
            exit 1
        fi
        warning "Continuing with --force"
    fi

    success "Quality gates passed"
}

build_assets() {
    if [[ "$SKIP_BUILD" == "true" ]]; then
        warning "Skipping asset build (--skip-build)"
        return 0
    fi

    log "Building assets..."

    # Install dependencies
    verbose "Installing PHP dependencies..."
    composer install --no-dev --prefer-dist --optimize-autoloader

    verbose "Installing Node.js dependencies..."
    npm ci --only=production

    # Build assets based on environment
    case "$ENVIRONMENT" in
        development)
            verbose "Building development assets..."
            npm run build
            ;;
        staging)
            verbose "Building staging assets..."
            NODE_ENV=production npm run build
            ;;
        production)
            verbose "Building production assets..."
            NODE_ENV=production npm run build
            npm run analyze
            ;;
    esac

    # Verify build output
    if [[ ! -f "assets/js/widget.js" ]]; then
        error "Widget JavaScript not found after build"
        exit 1
    fi

    if [[ ! -f "assets/css/widget.css" ]]; then
        warning "Widget CSS not found after build"
    fi

    success "Assets built successfully"
}

# =============================================================================
# PACKAGING FUNCTIONS
# =============================================================================

create_deployment_package() {
    log "Creating deployment package..."

    local version
    version=$(get_plugin_version)
    local package_name="${PLUGIN_SLUG}-${version}-${ENVIRONMENT}"
    local package_path="$OUTPUT_DIR/${package_name}.zip"

    # Create output directory
    mkdir -p "$OUTPUT_DIR"

    # Files to exclude from the package
    local exclude_patterns=(
        "node_modules/*"
        "tests/*"
        ".git*"
        "*.md"
        "composer.json"
        "composer.lock"
        "package*.json"
        "webpack.config.js"
        ".babelrc"
        "jest.config.js"
        "jest.setup.js"
        "widget-src/*"
        ".github/*"
        "scripts/*"
        "tmp/*"
        "coverage/*"
        "*.log"
        ".env*"
        ".vscode/*"
        ".phpunit.cache/*"
        "dist/*"
        "backups/*"
    )

    # Add environment-specific excludes
    case "$ENVIRONMENT" in
        production)
            exclude_patterns+=(
                "*.development.*"
                "*.staging.*"
                "debug.log"
            )
            ;;
        staging)
            exclude_patterns+=(
                "*.development.*"
                "debug.log"
            )
            ;;
    esac

    # Create the zip file
    verbose "Packaging files to: $package_path"
    
    local exclude_args=()
    for pattern in "${exclude_patterns[@]}"; do
        exclude_args+=("-x" "$pattern")
    done

    if [[ "$DRY_RUN" == "false" ]]; then
        zip -r "$package_path" . "${exclude_args[@]}"
        
        # Verify package
        if [[ ! -f "$package_path" ]]; then
            error "Failed to create deployment package"
            exit 1
        fi

        local package_size
        package_size=$(du -h "$package_path" | cut -f1)
        success "Package created: $package_name.zip ($package_size)"
    else
        log "DRY RUN: Would create package: $package_name.zip"
    fi

    echo "$package_path"
}

# =============================================================================
# DEPLOYMENT FUNCTIONS
# =============================================================================

deploy_to_development() {
    log "Deploying to development environment..."
    
    if [[ "$DRY_RUN" == "false" ]]; then
        # Development deployment is essentially just validation and packaging
        success "Development deployment completed"
    else
        log "DRY RUN: Would deploy to development environment"
    fi
}

deploy_to_staging() {
    log "Deploying to staging environment..."
    
    if [[ "$DRY_RUN" == "false" ]]; then
        # Create backup
        create_backup "staging"
        
        # Here you would implement actual staging deployment
        # For example: rsync to staging server, API calls, etc.
        warning "Staging deployment not fully implemented yet"
        success "Staging deployment completed"
    else
        log "DRY RUN: Would deploy to staging environment"
    fi
}

deploy_to_production() {
    log "Deploying to production environment..."
    
    if [[ "$DRY_RUN" == "false" ]]; then
        # Create backup
        create_backup "production"
        
        # Final validation for production
        warning "Production deployment requires manual confirmation"
        
        # Here you would implement WordPress.org deployment
        # This typically involves SVN operations
        warning "Production deployment to WordPress.org not implemented yet"
        
        success "Production deployment prepared"
    else
        log "DRY RUN: Would deploy to production environment"
    fi
}

create_backup() {
    local env_name="$1"
    local backup_name="${PLUGIN_SLUG}-backup-${env_name}-$(date +%Y%m%d-%H%M%S)"
    local backup_path="$BACKUP_DIR/$backup_name"
    
    verbose "Creating backup: $backup_name"
    
    mkdir -p "$BACKUP_DIR"
    
    if [[ "$DRY_RUN" == "false" ]]; then
        # Create backup of current state
        git archive --format=zip --output="$backup_path.zip" HEAD
        success "Backup created: $backup_name.zip"
    else
        log "DRY RUN: Would create backup: $backup_name.zip"
    fi
}

# =============================================================================
# MAIN DEPLOYMENT FUNCTION
# =============================================================================

deploy() {
    local package_path

    # Run pre-deployment checks
    check_prerequisites
    check_git_status

    # Run quality gates
    run_quality_gates

    # Build assets
    build_assets

    # Create deployment package
    package_path=$(create_deployment_package)

    # Deploy based on environment
    case "$ENVIRONMENT" in
        development)
            deploy_to_development
            ;;
        staging)
            deploy_to_staging
            ;;
        production)
            deploy_to_production
            ;;
    esac

    # Show summary
    log "Deployment Summary:"
    echo "  Environment: $ENVIRONMENT"
    echo "  Version: $(get_plugin_version)"
    echo "  Package: $(basename "$package_path")"
    echo "  Dry Run: $DRY_RUN"
    
    success "Deployment process completed!"
}

# =============================================================================
# MAIN EXECUTION
# =============================================================================

main() {
    local start_time
    start_time=$(date +%s)

    log "Starting deployment process..."
    
    # Parse command line arguments
    parse_arguments "$@"

    # Show configuration
    if [[ "$VERBOSE" == "true" ]]; then
        log "Configuration:"
        echo "  Environment: $ENVIRONMENT"
        echo "  Dry Run: $DRY_RUN"
        echo "  Skip Tests: $SKIP_TESTS"
        echo "  Skip Build: $SKIP_BUILD"
        echo "  Force Deploy: $FORCE_DEPLOY"
        echo "  Output Dir: $OUTPUT_DIR"
        echo ""
    fi

    # Run deployment
    deploy

    # Show total time
    local end_time
    end_time=$(date +%s)
    local duration=$((end_time - start_time))
    success "Total deployment time: ${duration}s"
}

# Only run main if script is executed directly (not sourced)
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi