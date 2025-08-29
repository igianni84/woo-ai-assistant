#!/bin/bash

# Deploy to WordPress.org Script for Woo AI Assistant Plugin
# 
# This script handles the complete deployment process to WordPress.org,
# including building, testing, SVN preparation, and deployment.
#
# @package WooAiAssistant
# @subpackage Scripts
# @since 1.0.0
# @author Claude Code Assistant

set -e  # Exit on any error

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Plugin information
PLUGIN_SLUG="woo-ai-assistant"
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Function to get plugin version
get_plugin_version() {
    if [ -f "$PLUGIN_DIR/$PLUGIN_SLUG.php" ]; then
        grep "Version:" "$PLUGIN_DIR/$PLUGIN_SLUG.php" | sed 's/.*Version: *\([0-9.]*\).*/\1/'
    else
        echo ""
    fi
}

# Function to validate environment
validate_environment() {
    print_status "Validating deployment environment..."
    
    # Check required commands
    local required_commands=("git" "svn" "php" "node" "npm")
    for cmd in "${required_commands[@]}"; do
        if ! command_exists "$cmd"; then
            print_error "Required command '$cmd' not found. Please install it first."
            exit 1
        fi
    done
    
    # Check WordPress.org credentials
    if [ -z "$WP_ORG_USERNAME" ]; then
        print_error "WordPress.org username not set. Please set WP_ORG_USERNAME environment variable."
        exit 1
    fi
    
    # Check Git repository status
    if [ -d "$PLUGIN_DIR/.git" ]; then
        cd "$PLUGIN_DIR"
        
        # Check if working directory is clean
        if ! git diff-index --quiet HEAD --; then
            print_error "Git working directory is not clean. Please commit or stash changes first."
            print_status "Uncommitted changes:"
            git status --porcelain
            exit 1
        fi
        
        # Check if we're on the correct branch (main or master)
        local current_branch=$(git rev-parse --abbrev-ref HEAD)
        if [[ "$current_branch" != "main" && "$current_branch" != "master" ]]; then
            print_warning "Not on main/master branch (current: $current_branch)"
            echo -n "Continue with deployment from this branch? [y/N]: "
            read -r confirm
            if [[ ! "$confirm" =~ ^[Yy]$ ]]; then
                print_status "Deployment cancelled"
                exit 1
            fi
        fi
        
        cd - >/dev/null
    fi
    
    print_success "Environment validation completed"
}

# Function to run pre-deployment checks
run_pre_deployment_checks() {
    print_status "Running pre-deployment checks..."
    
    cd "$PLUGIN_DIR"
    
    # Run quality gates
    if [ -f "scripts/mandatory-verification.sh" ]; then
        print_status "Running mandatory verification..."
        bash scripts/mandatory-verification.sh || {
            print_error "Mandatory verification failed. Please fix issues before deployment."
            exit 1
        }
    fi
    
    # Run additional tests
    if [ -f "scripts/run-all-tests.sh" ]; then
        print_status "Running all tests..."
        bash scripts/run-all-tests.sh || {
            print_error "Tests failed. Please fix issues before deployment."
            exit 1
        }
    fi
    
    # Check for security issues
    if [ -f "scripts/security-scan.sh" ]; then
        print_status "Running security scan..."
        bash scripts/security-scan.sh || {
            print_error "Security scan failed. Please fix issues before deployment."
            exit 1
        }
    fi
    
    cd - >/dev/null
    print_success "Pre-deployment checks completed"
}

# Function to build release
build_release() {
    local version="$1"
    
    print_status "Building release for version $version..."
    
    if [ -f "$PLUGIN_DIR/scripts/build-release.sh" ]; then
        cd "$PLUGIN_DIR"
        bash scripts/build-release.sh --version "$version"
        cd - >/dev/null
    else
        print_error "Build script not found: scripts/build-release.sh"
        exit 1
    fi
    
    # Verify build was successful
    local package_path="$PLUGIN_DIR/build/releases/$PLUGIN_SLUG-$version.zip"
    if [ ! -f "$package_path" ]; then
        print_error "Build failed - package not found: $package_path"
        exit 1
    fi
    
    print_success "Release build completed"
}

# Function to prepare SVN
prepare_svn() {
    local version="$1"
    
    print_status "Preparing SVN for version $version..."
    
    if [ -f "$PLUGIN_DIR/scripts/prepare-svn.sh" ]; then
        cd "$PLUGIN_DIR"
        bash scripts/prepare-svn.sh prepare "$version"
        cd - >/dev/null
    else
        print_error "SVN prepare script not found: scripts/prepare-svn.sh"
        exit 1
    fi
    
    print_success "SVN preparation completed"
}

# Function to create git tag
create_git_tag() {
    local version="$1"
    local push_tag="$2"
    
    if [ ! -d "$PLUGIN_DIR/.git" ]; then
        print_warning "Not a git repository, skipping tag creation"
        return 0
    fi
    
    print_status "Creating git tag for version $version..."
    
    cd "$PLUGIN_DIR"
    
    local tag_name="v$version"
    
    # Check if tag already exists
    if git tag -l | grep -q "^$tag_name$"; then
        print_warning "Git tag $tag_name already exists"
    else
        # Create annotated tag
        git tag -a "$tag_name" -m "Release version $version"
        print_success "Git tag $tag_name created"
        
        # Push tag if requested
        if [ "$push_tag" = true ]; then
            git push origin "$tag_name"
            print_success "Git tag pushed to origin"
        fi
    fi
    
    cd - >/dev/null
}

# Function to deploy to WordPress.org
deploy_to_wordpress_org() {
    local version="$1"
    local commit_message="$2"
    local auto_confirm="$3"
    
    print_status "Deploying version $version to WordPress.org..."
    
    # Final confirmation
    if [ "$auto_confirm" != true ]; then
        echo ""
        print_warning "You are about to deploy version $version to WordPress.org"
        print_status "This will make the plugin available to all WordPress users"
        echo -n "Are you sure you want to continue? [y/N]: "
        read -r confirm
        if [[ ! "$confirm" =~ ^[Yy]$ ]]; then
            print_status "Deployment cancelled"
            exit 1
        fi
    fi
    
    # Commit to SVN
    if [ -f "$PLUGIN_DIR/scripts/prepare-svn.sh" ]; then
        cd "$PLUGIN_DIR"
        
        local commit_args="--no-confirm"
        if [ -n "$commit_message" ]; then
            commit_args="$commit_args -m '$commit_message'"
        fi
        
        bash scripts/prepare-svn.sh commit "$version" $commit_args
        cd - >/dev/null
    else
        print_error "SVN prepare script not found"
        exit 1
    fi
    
    print_success "Deployment to WordPress.org completed!"
}

# Function to post-deployment tasks
run_post_deployment_tasks() {
    local version="$1"
    
    print_status "Running post-deployment tasks..."
    
    # Create GitHub release if in git repository
    if [ -d "$PLUGIN_DIR/.git" ] && command_exists gh; then
        print_status "Creating GitHub release..."
        cd "$PLUGIN_DIR"
        
        local release_notes=""
        if [ -f "CHANGELOG.md" ]; then
            # Extract changelog for this version
            release_notes=$(sed -n "/## \[$version\]/,/## \[/p" CHANGELOG.md | head -n -1 | tail -n +2)
        fi
        
        if [ -n "$release_notes" ]; then
            gh release create "v$version" --title "Release $version" --notes "$release_notes"
        else
            gh release create "v$version" --title "Release $version" --notes "Release version $version"
        fi
        
        print_success "GitHub release created"
        cd - >/dev/null
    fi
    
    # Send notifications (placeholder - implement as needed)
    print_status "Deployment completed successfully!"
    print_status "Plugin version $version is now available on WordPress.org"
    
    # Display useful links
    echo ""
    print_status "Useful links:"
    echo "  Plugin page: https://wordpress.org/plugins/$PLUGIN_SLUG/"
    echo "  Stats: https://wordpress.org/plugins/$PLUGIN_SLUG/stats/"
    echo "  Support: https://wordpress.org/support/plugin/$PLUGIN_SLUG/"
}

# Function to rollback deployment
rollback_deployment() {
    local version="$1"
    
    print_error "Deployment failed! Starting rollback procedures..."
    
    # Remove SVN tag if it was created
    local svn_dir="$PLUGIN_DIR/build/svn"
    if [ -d "$svn_dir/tags/$version" ]; then
        print_status "Removing SVN tag $version..."
        cd "$svn_dir"
        svn rm "tags/$version"
        svn commit -m "Rollback: Remove failed release $version" --username="$WP_ORG_USERNAME"
        cd - >/dev/null
    fi
    
    # Remove git tag if it was created
    if [ -d "$PLUGIN_DIR/.git" ]; then
        cd "$PLUGIN_DIR"
        local tag_name="v$version"
        if git tag -l | grep -q "^$tag_name$"; then
            print_status "Removing git tag $tag_name..."
            git tag -d "$tag_name"
            
            # Remove from remote if it was pushed
            if git ls-remote --tags origin | grep -q "refs/tags/$tag_name"; then
                git push --delete origin "$tag_name"
            fi
        fi
        cd - >/dev/null
    fi
    
    print_status "Rollback completed"
    exit 1
}

# Function to display help
display_help() {
    echo "Deploy to WordPress.org Script"
    echo ""
    echo "Usage: $0 [VERSION] [OPTIONS]"
    echo ""
    echo "Arguments:"
    echo "  VERSION               Plugin version to deploy (optional, auto-detected)"
    echo ""
    echo "Options:"
    echo "  --skip-build         Skip build process (use existing package)"
    echo "  --skip-tests         Skip pre-deployment tests"
    echo "  --push-git-tag       Push git tag to remote repository"
    echo "  --auto-confirm       Skip confirmation prompts"
    echo "  -m MESSAGE           Custom commit message for SVN"
    echo "  --dry-run           Show what would be done without deploying"
    echo "  -h, --help          Show this help message"
    echo ""
    echo "Environment Variables:"
    echo "  WP_ORG_USERNAME     WordPress.org username (required)"
    echo "  WP_ORG_PASSWORD     WordPress.org password (optional)"
    echo ""
    echo "Examples:"
    echo "  $0                          # Deploy current version"
    echo "  $0 1.2.0                    # Deploy specific version"
    echo "  $0 --push-git-tag           # Deploy and push git tag"
    echo "  $0 --auto-confirm --dry-run # Show deployment plan"
    echo ""
    echo "Pre-requisites:"
    echo "  1. Plugin must pass all quality gates"
    echo "  2. WordPress.org SVN access must be configured"
    echo "  3. Git working directory must be clean"
    echo "  4. All tests must pass"
    echo ""
}

# Main function
main() {
    local version=""
    local skip_build=false
    local skip_tests=false
    local push_git_tag=false
    local auto_confirm=false
    local commit_message=""
    local dry_run=false
    
    # Parse arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            --skip-build)
                skip_build=true
                shift
                ;;
            --skip-tests)
                skip_tests=true
                shift
                ;;
            --push-git-tag)
                push_git_tag=true
                shift
                ;;
            --auto-confirm)
                auto_confirm=true
                shift
                ;;
            -m)
                commit_message="$2"
                shift 2
                ;;
            --dry-run)
                dry_run=true
                shift
                ;;
            -h|--help)
                display_help
                exit 0
                ;;
            -*)
                print_error "Unknown option: $1"
                display_help
                exit 1
                ;;
            *)
                if [ -z "$version" ]; then
                    version="$1"
                else
                    print_error "Multiple versions specified"
                    display_help
                    exit 1
                fi
                shift
                ;;
        esac
    done
    
    # Get version from plugin file if not specified
    if [ -z "$version" ]; then
        version=$(get_plugin_version)
        if [ -z "$version" ]; then
            print_error "Could not determine plugin version"
            display_help
            exit 1
        fi
        print_status "Auto-detected version: $version"
    fi
    
    # Validate version format
    if ! [[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[a-zA-Z0-9]+)?$ ]]; then
        print_error "Invalid version format: $version"
        exit 1
    fi
    
    if [ "$dry_run" = true ]; then
        print_status "DRY RUN: Deployment plan for version $version"
        print_status "1. Validate environment"
        print_status "2. Run pre-deployment checks $([ "$skip_tests" = true ] && echo "(SKIPPED)" || echo "")"
        print_status "3. Build release $([ "$skip_build" = true ] && echo "(SKIPPED)" || echo "")"
        print_status "4. Prepare SVN"
        print_status "5. Create git tag $([ "$push_git_tag" = true ] && echo "and push" || echo "")"
        print_status "6. Deploy to WordPress.org"
        print_status "7. Run post-deployment tasks"
        exit 0
    fi
    
    print_status "Starting deployment of $PLUGIN_SLUG version $version"
    
    # Set up error handling for rollback
    trap 'rollback_deployment "$version"' ERR
    
    # Execute deployment steps
    validate_environment
    
    if [ "$skip_tests" != true ]; then
        run_pre_deployment_checks
    else
        print_warning "Skipping pre-deployment tests"
    fi
    
    if [ "$skip_build" != true ]; then
        build_release "$version"
    else
        print_warning "Skipping build process"
    fi
    
    prepare_svn "$version"
    create_git_tag "$version" "$push_git_tag"
    deploy_to_wordpress_org "$version" "$commit_message" "$auto_confirm"
    
    # Disable error trap for post-deployment
    trap - ERR
    
    run_post_deployment_tasks "$version"
    
    print_success "Deployment completed successfully!"
    echo ""
    print_status "Version $version is now live on WordPress.org!"
}

# Run main function with all arguments
main "$@"