#!/bin/bash

# Prepare SVN Script for WordPress.org Plugin Repository
# 
# This script prepares the plugin for submission to WordPress.org by setting up
# and managing the SVN repository structure, copying files, and handling assets.
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
SVN_DIR="$PLUGIN_DIR/build/svn"
SVN_URL="https://plugins.svn.wordpress.org/$PLUGIN_SLUG"

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

# Function to validate WordPress.org credentials
validate_credentials() {
    if [ -z "$WP_ORG_USERNAME" ]; then
        print_error "WordPress.org username not set. Please set WP_ORG_USERNAME environment variable."
        print_status "You can set it by running: export WP_ORG_USERNAME=your-username"
        exit 1
    fi
    
    if [ -z "$WP_ORG_PASSWORD" ]; then
        print_warning "WordPress.org password not set. You'll need to enter it manually during SVN operations."
        print_status "For automated deployment, set WP_ORG_PASSWORD environment variable."
    fi
}

# Function to initialize SVN repository
init_svn_repo() {
    print_status "Initializing SVN repository..."
    
    # Remove existing SVN directory
    if [ -d "$SVN_DIR" ]; then
        print_status "Removing existing SVN directory"
        rm -rf "$SVN_DIR"
    fi
    
    # Create SVN directory
    mkdir -p "$SVN_DIR"
    
    # Check if plugin exists on WordPress.org
    if svn ls "$SVN_URL" >/dev/null 2>&1; then
        print_status "Plugin exists on WordPress.org, checking out repository..."
        svn co "$SVN_URL" "$SVN_DIR" --username="$WP_ORG_USERNAME"
    else
        print_status "Plugin doesn't exist on WordPress.org yet, creating initial structure..."
        mkdir -p "$SVN_DIR"
        cd "$SVN_DIR"
        
        # Create standard WordPress.org plugin structure
        mkdir -p trunk tags assets
        
        # Initialize as SVN working copy (will be added later when plugin is approved)
        print_warning "This appears to be a new plugin. You'll need to request SVN access from WordPress.org first."
    fi
    
    print_success "SVN repository initialized"
}

# Function to copy plugin files to SVN trunk
copy_plugin_files() {
    local version="$1"
    
    print_status "Copying plugin files to SVN trunk..."
    
    # Clear trunk directory
    if [ -d "$SVN_DIR/trunk" ]; then
        rm -rf "$SVN_DIR/trunk"/*
    else
        mkdir -p "$SVN_DIR/trunk"
    fi
    
    # Check if we have a pre-built package
    local package_path="$PLUGIN_DIR/build/releases/$PLUGIN_SLUG-$version.zip"
    
    if [ -f "$package_path" ]; then
        print_status "Using pre-built package: $package_path"
        
        # Extract package to temporary directory
        local temp_dir="$SVN_DIR/temp-extract"
        mkdir -p "$temp_dir"
        
        if command_exists unzip; then
            unzip -q "$package_path" -d "$temp_dir"
            cp -R "$temp_dir/$PLUGIN_SLUG/"* "$SVN_DIR/trunk/"
            rm -rf "$temp_dir"
        else
            print_error "unzip command not found. Cannot extract package."
            exit 1
        fi
    else
        print_status "No pre-built package found, copying files directly..."
        
        # Copy files respecting .distignore
        if [ -f "$PLUGIN_DIR/.distignore" ]; then
            # Use rsync to copy files while excluding patterns from .distignore
            local rsync_excludes=()
            while IFS= read -r line; do
                # Skip empty lines and comments
                if [[ -n "$line" && ! "$line" =~ ^[[:space:]]*# ]]; then
                    # Remove leading/trailing whitespace
                    line=$(echo "$line" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')
                    if [[ -n "$line" ]]; then
                        rsync_excludes+=("--exclude=$line")
                    fi
                fi
            done < "$PLUGIN_DIR/.distignore"
            
            if command_exists rsync; then
                rsync -av "${rsync_excludes[@]}" "$PLUGIN_DIR/" "$SVN_DIR/trunk/"
            else
                print_warning "rsync not found, copying all files"
                cp -R "$PLUGIN_DIR/"* "$SVN_DIR/trunk/"
            fi
        else
            print_warning "No .distignore file found, copying all files"
            cp -R "$PLUGIN_DIR/"* "$SVN_DIR/trunk/"
        fi
    fi
    
    print_success "Plugin files copied to SVN trunk"
}

# Function to copy assets to SVN assets directory
copy_assets() {
    print_status "Copying plugin assets..."
    
    local assets_source="$PLUGIN_DIR/wordpress-org-assets"
    
    if [ -d "$assets_source" ]; then
        # Clear assets directory
        if [ -d "$SVN_DIR/assets" ]; then
            rm -rf "$SVN_DIR/assets"/*
        else
            mkdir -p "$SVN_DIR/assets"
        fi
        
        cp -R "$assets_source/"* "$SVN_DIR/assets/"
        print_success "Plugin assets copied"
    else
        print_warning "No wordpress-org-assets directory found at: $assets_source"
        print_status "Create this directory and add the following assets:"
        print_status "  - banner-772x250.png (plugin banner)"
        print_status "  - banner-1544x500.png (high-res plugin banner)"
        print_status "  - icon-128x128.png (plugin icon)"
        print_status "  - icon-256x256.png (high-res plugin icon)"
        print_status "  - screenshot-1.png (first screenshot)"
        print_status "  - screenshot-2.png (additional screenshots)"
        
        # Create placeholder directory structure
        mkdir -p "$SVN_DIR/assets"
    fi
}

# Function to create version tag
create_version_tag() {
    local version="$1"
    
    print_status "Creating version tag: $version"
    
    # Check if tag already exists
    if [ -d "$SVN_DIR/tags/$version" ]; then
        print_warning "Tag $version already exists"
        return 0
    fi
    
    # Create tag directory
    mkdir -p "$SVN_DIR/tags/$version"
    
    # Copy trunk to tag
    cp -R "$SVN_DIR/trunk/"* "$SVN_DIR/tags/$version/"
    
    print_success "Version tag $version created"
}

# Function to validate plugin files
validate_plugin_files() {
    print_status "Validating plugin files..."
    
    # Check that main plugin file exists
    if [ ! -f "$SVN_DIR/trunk/$PLUGIN_SLUG.php" ]; then
        print_error "Main plugin file not found in trunk"
        exit 1
    fi
    
    # Check that readme.txt exists
    if [ ! -f "$SVN_DIR/trunk/readme.txt" ]; then
        print_error "readme.txt not found. WordPress.org requires a readme.txt file."
        print_status "Please create a readme.txt file following WordPress.org standards:"
        print_status "https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/"
        exit 1
    fi
    
    # Validate readme.txt format
    if ! grep -q "^=== .* ===$" "$SVN_DIR/trunk/readme.txt"; then
        print_error "Invalid readme.txt format. Missing plugin name header."
        exit 1
    fi
    
    # Check PHP syntax in all PHP files
    find "$SVN_DIR/trunk" -name "*.php" -exec php -l {} \; >/dev/null 2>&1 || {
        print_error "PHP syntax errors found in plugin files"
        exit 1
    }
    
    # Check for common WordPress.org compliance issues
    if grep -r "file_get_contents" "$SVN_DIR/trunk" --include="*.php" >/dev/null 2>&1; then
        print_warning "Found file_get_contents() usage. Consider using wp_remote_get() for HTTP requests."
    fi
    
    if grep -r "curl_" "$SVN_DIR/trunk" --include="*.php" >/dev/null 2>&1; then
        print_warning "Found cURL usage. Consider using wp_remote_get() for HTTP requests."
    fi
    
    print_success "Plugin validation completed"
}

# Function to show SVN status
show_svn_status() {
    print_status "SVN status:"
    cd "$SVN_DIR"
    svn status
    cd - >/dev/null
}

# Function to commit changes
commit_changes() {
    local version="$1"
    local message="$2"
    
    if [ -z "$message" ]; then
        message="Update to version $version"
    fi
    
    print_status "Committing changes to SVN..."
    
    cd "$SVN_DIR"
    
    # Add new files
    svn add --force trunk/ tags/ assets/ 2>/dev/null || true
    
    # Show what will be committed
    print_status "Changes to be committed:"
    svn status
    
    # Ask for confirmation unless --no-confirm is passed
    if [[ ! "$*" =~ --no-confirm ]]; then
        echo -n "Proceed with commit? [y/N]: "
        read -r confirm
        if [[ ! "$confirm" =~ ^[Yy]$ ]]; then
            print_status "Commit cancelled"
            cd - >/dev/null
            return 1
        fi
    fi
    
    # Commit changes
    if [ -n "$WP_ORG_PASSWORD" ]; then
        svn commit -m "$message" --username="$WP_ORG_USERNAME" --password="$WP_ORG_PASSWORD" --non-interactive
    else
        svn commit -m "$message" --username="$WP_ORG_USERNAME"
    fi
    
    cd - >/dev/null
    print_success "Changes committed to WordPress.org SVN"
}

# Function to display help
display_help() {
    echo "Prepare SVN Script for WordPress.org Plugin Repository"
    echo ""
    echo "Usage: $0 [COMMAND] [OPTIONS]"
    echo ""
    echo "Commands:"
    echo "  init                   Initialize SVN repository"
    echo "  prepare VERSION        Prepare plugin for version release"
    echo "  commit VERSION         Commit prepared changes to WordPress.org"
    echo "  status                 Show SVN status"
    echo ""
    echo "Options:"
    echo "  --no-confirm          Skip confirmation prompts"
    echo "  -m MESSAGE            Custom commit message"
    echo "  -h, --help           Show this help message"
    echo ""
    echo "Environment Variables:"
    echo "  WP_ORG_USERNAME       WordPress.org username (required)"
    echo "  WP_ORG_PASSWORD       WordPress.org password (optional)"
    echo ""
    echo "Examples:"
    echo "  $0 init"
    echo "  $0 prepare 1.0.0"
    echo "  $0 commit 1.0.0 -m 'Initial release'"
    echo ""
}

# Main function
main() {
    local command="$1"
    local version="$2"
    local commit_message=""
    local no_confirm=false
    
    # Parse options
    shift 2
    while [[ $# -gt 0 ]]; do
        case $1 in
            --no-confirm)
                no_confirm=true
                shift
                ;;
            -m)
                commit_message="$2"
                shift 2
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
    
    # Validate required commands
    if ! command_exists svn; then
        print_error "Subversion (svn) is not installed or not in PATH"
        print_status "Please install Subversion and try again"
        exit 1
    fi
    
    case "$command" in
        init)
            validate_credentials
            init_svn_repo
            ;;
            
        prepare)
            if [ -z "$version" ]; then
                version=$(get_plugin_version)
                if [ -z "$version" ]; then
                    print_error "Version not specified and could not be determined from plugin file"
                    display_help
                    exit 1
                fi
                print_status "Using version from plugin file: $version"
            fi
            
            validate_credentials
            
            # Initialize if needed
            if [ ! -d "$SVN_DIR" ]; then
                init_svn_repo
            fi
            
            copy_plugin_files "$version"
            copy_assets
            create_version_tag "$version"
            validate_plugin_files
            show_svn_status
            
            print_success "Plugin prepared for version $version"
            print_status "Review the changes and run: $0 commit $version"
            ;;
            
        commit)
            if [ -z "$version" ]; then
                print_error "Version required for commit command"
                display_help
                exit 1
            fi
            
            if [ ! -d "$SVN_DIR" ]; then
                print_error "SVN directory not found. Run 'prepare' command first."
                exit 1
            fi
            
            validate_credentials
            
            # Pass no-confirm flag if set
            local commit_args=""
            if [ "$no_confirm" = true ]; then
                commit_args="--no-confirm"
            fi
            
            commit_changes "$version" "$commit_message" $commit_args
            ;;
            
        status)
            if [ ! -d "$SVN_DIR" ]; then
                print_error "SVN directory not found. Run 'init' command first."
                exit 1
            fi
            show_svn_status
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

# Run main function with all arguments
main "$@"