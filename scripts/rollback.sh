#!/bin/bash

# Rollback Script for Woo AI Assistant Plugin
# 
# This script handles emergency rollback procedures for failed deployments,
# including WordPress.org SVN rollback, git reversion, and local environment recovery.
#
# @package WooAiAssistant
# @subpackage Scripts
# @since 1.0.0
# @author Claude Code Assistant

set -e  # Exit on any error (will be disabled for rollback operations)

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Plugin information
PLUGIN_SLUG="woo-ai-assistant"
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SVN_DIR="$PLUGIN_DIR/build/svn"
BACKUP_DIR="$PLUGIN_DIR/build/rollback-backups"

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

# Function to create backup before rollback
create_rollback_backup() {
    local timestamp=$(date +"%Y%m%d_%H%M%S")
    local backup_path="$BACKUP_DIR/pre_rollback_$timestamp"
    
    print_status "Creating backup before rollback..."
    
    mkdir -p "$backup_path"
    
    # Backup current plugin state
    if [ -d "$PLUGIN_DIR" ]; then
        cp -R "$PLUGIN_DIR" "$backup_path/plugin" 2>/dev/null || true
    fi
    
    # Backup SVN state
    if [ -d "$SVN_DIR" ]; then
        cp -R "$SVN_DIR" "$backup_path/svn" 2>/dev/null || true
    fi
    
    print_success "Backup created at: $backup_path"
    echo "$backup_path" > "$PLUGIN_DIR/.rollback_backup_path"
}

# Function to get plugin version
get_plugin_version() {
    if [ -f "$PLUGIN_DIR/$PLUGIN_SLUG.php" ]; then
        grep "Version:" "$PLUGIN_DIR/$PLUGIN_SLUG.php" | sed 's/.*Version: *\([0-9.]*\).*/\1/'
    else
        echo ""
    fi
}

# Function to get available versions (from git tags)
get_available_versions() {
    if [ -d "$PLUGIN_DIR/.git" ]; then
        cd "$PLUGIN_DIR"
        git tag -l --sort=-version:refname | grep -E '^v?[0-9]+\.[0-9]+\.[0-9]+' | head -10
        cd - >/dev/null
    else
        print_warning "Not a git repository, cannot list available versions"
    fi
}

# Function to rollback git to specific version
rollback_git() {
    local target_version="$1"
    local force_rollback="$2"
    
    if [ ! -d "$PLUGIN_DIR/.git" ]; then
        print_error "Not a git repository, cannot rollback git"
        return 1
    fi
    
    print_status "Rolling back git to version $target_version..."
    
    cd "$PLUGIN_DIR"
    
    # Disable exit on error for rollback operations
    set +e
    
    # Check if working directory is clean
    if ! git diff-index --quiet HEAD -- && [ "$force_rollback" != "true" ]; then
        print_error "Working directory is not clean. Use --force to ignore uncommitted changes."
        git status --porcelain
        cd - >/dev/null
        set -e
        return 1
    fi
    
    # Find the tag/commit for the target version
    local target_ref=""
    if git tag -l | grep -q "^v$target_version$"; then
        target_ref="v$target_version"
    elif git tag -l | grep -q "^$target_version$"; then
        target_ref="$target_version"
    else
        print_error "Version $target_version not found in git tags"
        cd - >/dev/null
        set -e
        return 1
    fi
    
    # Create a rollback branch
    local rollback_branch="rollback-to-$target_version-$(date +%s)"
    git checkout -b "$rollback_branch" 2>/dev/null || {
        print_warning "Failed to create rollback branch, continuing with current branch"
    }
    
    # Reset to target version
    if git reset --hard "$target_ref"; then
        print_success "Git rolled back to $target_version"
    else
        print_error "Failed to rollback git to $target_version"
        cd - >/dev/null
        set -e
        return 1
    fi
    
    cd - >/dev/null
    set -e
    return 0
}

# Function to rollback SVN (WordPress.org)
rollback_svn() {
    local target_version="$1"
    local commit_rollback="$2"
    
    if [ ! -d "$SVN_DIR" ]; then
        print_error "SVN directory not found at: $SVN_DIR"
        print_status "Run 'scripts/prepare-svn.sh init' to initialize SVN"
        return 1
    fi
    
    print_status "Rolling back WordPress.org SVN to version $target_version..."
    
    cd "$SVN_DIR"
    
    # Disable exit on error for rollback operations
    set +e
    
    # Check if target version exists
    if [ ! -d "tags/$target_version" ]; then
        print_error "Version $target_version not found in SVN tags"
        print_status "Available versions:"
        ls -1 tags/ 2>/dev/null || print_warning "No tagged versions found"
        cd - >/dev/null
        set -e
        return 1
    fi
    
    # Copy target version to trunk
    print_status "Copying version $target_version to trunk..."
    rm -rf trunk/*
    cp -R "tags/$target_version/"* trunk/
    
    # Add changes to SVN
    svn add trunk/* 2>/dev/null || true
    
    if [ "$commit_rollback" = "true" ]; then
        # Commit rollback
        local commit_message="Emergency rollback to version $target_version"
        
        if [ -n "$WP_ORG_USERNAME" ]; then
            print_status "Committing rollback to WordPress.org..."
            
            if [ -n "$WP_ORG_PASSWORD" ]; then
                svn commit -m "$commit_message" --username="$WP_ORG_USERNAME" --password="$WP_ORG_PASSWORD" --non-interactive
            else
                svn commit -m "$commit_message" --username="$WP_ORG_USERNAME"
            fi
            
            if [ $? -eq 0 ]; then
                print_success "Rollback committed to WordPress.org"
            else
                print_error "Failed to commit rollback to WordPress.org"
                cd - >/dev/null
                set -e
                return 1
            fi
        else
            print_error "WordPress.org username not set. Set WP_ORG_USERNAME environment variable."
            cd - >/dev/null
            set -e
            return 1
        fi
    else
        print_status "Rollback prepared in SVN (not committed yet)"
        print_status "Review changes and run with --commit to deploy rollback"
    fi
    
    cd - >/dev/null
    set -e
    return 0
}

# Function to remove failed release tags
remove_failed_tags() {
    local failed_version="$1"
    local remove_git="$2"
    local remove_svn="$3"
    
    print_status "Removing failed release tags for version $failed_version..."
    
    # Remove git tag
    if [ "$remove_git" = "true" ] && [ -d "$PLUGIN_DIR/.git" ]; then
        cd "$PLUGIN_DIR"
        set +e
        
        local git_tag=""
        if git tag -l | grep -q "^v$failed_version$"; then
            git_tag="v$failed_version"
        elif git tag -l | grep -q "^$failed_version$"; then
            git_tag="$failed_version"
        fi
        
        if [ -n "$git_tag" ]; then
            # Remove local tag
            git tag -d "$git_tag"
            print_success "Removed local git tag: $git_tag"
            
            # Remove remote tag if it exists
            if git ls-remote --tags origin | grep -q "refs/tags/$git_tag"; then
                git push --delete origin "$git_tag" 2>/dev/null && {
                    print_success "Removed remote git tag: $git_tag"
                } || {
                    print_warning "Failed to remove remote git tag"
                }
            fi
        else
            print_warning "Git tag for version $failed_version not found"
        fi
        
        cd - >/dev/null
        set -e
    fi
    
    # Remove SVN tag
    if [ "$remove_svn" = "true" ] && [ -d "$SVN_DIR" ]; then
        cd "$SVN_DIR"
        set +e
        
        if [ -d "tags/$failed_version" ]; then
            svn rm "tags/$failed_version"
            
            if [ -n "$WP_ORG_USERNAME" ]; then
                local commit_message="Remove failed release tag $failed_version"
                
                if [ -n "$WP_ORG_PASSWORD" ]; then
                    svn commit -m "$commit_message" --username="$WP_ORG_USERNAME" --password="$WP_ORG_PASSWORD" --non-interactive
                else
                    svn commit -m "$commit_message" --username="$WP_ORG_USERNAME"
                fi
                
                if [ $? -eq 0 ]; then
                    print_success "Removed SVN tag: $failed_version"
                else
                    print_error "Failed to remove SVN tag"
                fi
            else
                print_warning "SVN tag removal prepared but not committed (set WP_ORG_USERNAME)"
            fi
        else
            print_warning "SVN tag for version $failed_version not found"
        fi
        
        cd - >/dev/null
        set -e
    fi
}

# Function to validate rollback target
validate_rollback_target() {
    local target_version="$1"
    
    if [ -z "$target_version" ]; then
        print_error "Target version not specified"
        return 1
    fi
    
    # Validate version format
    if ! echo "$target_version" | grep -qE '^v?[0-9]+\.[0-9]+\.[0-9]+'; then
        print_error "Invalid version format: $target_version"
        return 1
    fi
    
    # Check if target version exists in git
    if [ -d "$PLUGIN_DIR/.git" ]; then
        cd "$PLUGIN_DIR"
        
        local found=false
        if git tag -l | grep -q "^v$target_version$"; then
            found=true
        elif git tag -l | grep -q "^$target_version$"; then
            found=true
        fi
        
        if [ "$found" = false ]; then
            print_error "Version $target_version not found in git repository"
            print_status "Available versions:"
            get_available_versions
            cd - >/dev/null
            return 1
        fi
        
        cd - >/dev/null
    fi
    
    return 0
}

# Function to show rollback status
show_rollback_status() {
    print_status "Rollback Status Summary"
    echo "========================"
    
    # Current version
    local current_version=$(get_plugin_version)
    echo "Current Plugin Version: ${current_version:-"Unknown"}"
    
    # Git status
    if [ -d "$PLUGIN_DIR/.git" ]; then
        cd "$PLUGIN_DIR"
        local git_branch=$(git rev-parse --abbrev-ref HEAD)
        local git_commit=$(git rev-parse --short HEAD)
        echo "Git Branch: $git_branch"
        echo "Git Commit: $git_commit"
        
        if git diff-index --quiet HEAD --; then
            echo "Git Status: Clean"
        else
            echo "Git Status: Dirty (uncommitted changes)"
        fi
        cd - >/dev/null
    else
        echo "Git: Not a repository"
    fi
    
    # SVN status
    if [ -d "$SVN_DIR" ]; then
        cd "$SVN_DIR"
        echo "SVN Status:"
        svn status | head -10
        cd - >/dev/null
    else
        echo "SVN: Not initialized"
    fi
    
    # Available backups
    if [ -d "$BACKUP_DIR" ]; then
        local backup_count=$(find "$BACKUP_DIR" -maxdepth 1 -type d -name "pre_rollback_*" 2>/dev/null | wc -l)
        echo "Available Backups: $backup_count"
        
        if [ "$backup_count" -gt 0 ]; then
            echo "Recent Backups:"
            find "$BACKUP_DIR" -maxdepth 1 -type d -name "pre_rollback_*" 2>/dev/null | sort -r | head -3 | sed 's|.*/||'
        fi
    else
        echo "Backups: None"
    fi
}

# Function to display help
display_help() {
    echo "Rollback Script for Woo AI Assistant Plugin"
    echo ""
    echo "Usage: $0 [COMMAND] [VERSION] [OPTIONS]"
    echo ""
    echo "Commands:"
    echo "  git VERSION              Rollback git repository to specified version"
    echo "  svn VERSION              Rollback WordPress.org SVN to specified version"
    echo "  full VERSION             Rollback both git and SVN to specified version"
    echo "  remove-tags VERSION      Remove failed release tags"
    echo "  status                   Show current rollback status"
    echo "  list-versions            List available versions for rollback"
    echo ""
    echo "Options:"
    echo "  --commit                 Commit SVN changes immediately"
    echo "  --force                  Force rollback ignoring uncommitted changes"
    echo "  --remove-git-tags        Remove git tags when using remove-tags"
    echo "  --remove-svn-tags        Remove SVN tags when using remove-tags"
    echo "  --backup                 Create backup before rollback"
    echo "  -h, --help              Show this help message"
    echo ""
    echo "Environment Variables:"
    echo "  WP_ORG_USERNAME         WordPress.org username (required for SVN operations)"
    echo "  WP_ORG_PASSWORD         WordPress.org password (optional)"
    echo ""
    echo "Examples:"
    echo "  $0 status                                    # Show rollback status"
    echo "  $0 list-versions                            # List available versions"
    echo "  $0 git 1.1.0 --backup                      # Rollback git with backup"
    echo "  $0 svn 1.1.0 --commit                      # Rollback and commit SVN"
    echo "  $0 full 1.1.0 --commit --backup            # Full rollback with commit and backup"
    echo "  $0 remove-tags 1.2.0 --remove-git-tags     # Remove failed release tags"
    echo ""
    echo "⚠️  IMPORTANT NOTES:"
    echo "  - Always create backups before performing rollbacks"
    echo "  - Test rollbacks in staging environment first"
    echo "  - SVN rollbacks affect live WordPress.org plugin"
    echo "  - Coordinate with team before rolling back public releases"
    echo ""
}

# Main function
main() {
    local command="$1"
    local target_version="$2"
    local commit_rollback=false
    local force_rollback=false
    local create_backup=false
    local remove_git_tags=false
    local remove_svn_tags=false
    
    # Parse options
    shift 2 2>/dev/null || true
    while [[ $# -gt 0 ]]; do
        case $1 in
            --commit)
                commit_rollback=true
                shift
                ;;
            --force)
                force_rollback=true
                shift
                ;;
            --backup)
                create_backup=true
                shift
                ;;
            --remove-git-tags)
                remove_git_tags=true
                shift
                ;;
            --remove-svn-tags)
                remove_svn_tags=true
                shift
                ;;
            -h|--help)
                display_help
                exit 0
                ;;
            *)
                print_error "Unknown option: $1"
                display_help
                exit 1
                ;;
        esac
    done
    
    case "$command" in
        git)
            if ! validate_rollback_target "$target_version"; then
                exit 1
            fi
            
            if [ "$create_backup" = true ]; then
                create_rollback_backup
            fi
            
            rollback_git "$target_version" "$force_rollback"
            ;;
            
        svn)
            if ! validate_rollback_target "$target_version"; then
                exit 1
            fi
            
            if [ "$create_backup" = true ]; then
                create_rollback_backup
            fi
            
            rollback_svn "$target_version" "$commit_rollback"
            ;;
            
        full)
            if ! validate_rollback_target "$target_version"; then
                exit 1
            fi
            
            if [ "$create_backup" = true ]; then
                create_rollback_backup
            fi
            
            print_status "Performing full rollback to version $target_version"
            
            # Rollback git first
            if rollback_git "$target_version" "$force_rollback"; then
                print_success "Git rollback completed"
            else
                print_error "Git rollback failed, aborting full rollback"
                exit 1
            fi
            
            # Then rollback SVN
            if rollback_svn "$target_version" "$commit_rollback"; then
                print_success "SVN rollback completed"
            else
                print_error "SVN rollback failed"
                exit 1
            fi
            
            print_success "Full rollback to version $target_version completed"
            ;;
            
        remove-tags)
            if [ -z "$target_version" ]; then
                print_error "Version required for remove-tags command"
                exit 1
            fi
            
            if [ "$create_backup" = true ]; then
                create_rollback_backup
            fi
            
            remove_failed_tags "$target_version" "$remove_git_tags" "$remove_svn_tags"
            ;;
            
        status)
            show_rollback_status
            ;;
            
        list-versions)
            print_status "Available versions for rollback:"
            get_available_versions
            ;;
            
        *)
            if [ -n "$command" ]; then
                print_error "Unknown command: $command"
            fi
            display_help
            exit 1
            ;;
    esac
}

# Handle interrupts gracefully
trap 'print_error "Rollback interrupted"; exit 130' INT TERM

# Run main function with all arguments
main "$@"