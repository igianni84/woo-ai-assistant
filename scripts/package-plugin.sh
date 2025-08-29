#!/bin/bash

# Package Plugin Script for Woo AI Assistant
# 
# This script creates a distribution-ready ZIP package of the plugin,
# excluding development files and directories as specified in .distignore.
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
BUILD_DIR="$PLUGIN_DIR/build"
RELEASE_DIR="$BUILD_DIR/releases"
TEMP_DIR="$BUILD_DIR/temp"

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

# Function to get plugin version from main plugin file
get_plugin_version() {
    if [ -f "$PLUGIN_DIR/$PLUGIN_SLUG.php" ]; then
        grep "Version:" "$PLUGIN_DIR/$PLUGIN_SLUG.php" | sed 's/.*Version: *\([0-9.]*\).*/\1/'
    else
        echo ""
    fi
}

# Function to create .distignore if it doesn't exist
create_distignore() {
    if [ ! -f "$PLUGIN_DIR/.distignore" ]; then
        print_warning ".distignore file not found, creating default one"
        
        cat > "$PLUGIN_DIR/.distignore" << 'EOF'
# Default .distignore for WordPress plugin
node_modules/
.git/
.github/
.vscode/
.idea/
tests/
coverage/
.phpunit.cache/
tmp/
scripts/
docs/
*.log
*.cache
.DS_Store
.env*
package*.json
composer.json
composer.lock
webpack.config.js
babel.config.js
phpunit.xml
phpcs.xml
phpstan.neon
.distignore
.gitignore
EOF
        print_status "Default .distignore file created"
    fi
}

# Function to copy files respecting .distignore
copy_files() {
    local src_dir="$1"
    local dest_dir="$2"
    local distignore_file="$3"
    
    print_status "Copying plugin files (excluding .distignore patterns)..."
    
    # Create destination directory
    mkdir -p "$dest_dir"
    
    # If no .distignore file, copy everything
    if [ ! -f "$distignore_file" ]; then
        print_warning "No .distignore file found, copying all files"
        cp -R "$src_dir/." "$dest_dir/"
        return
    fi
    
    # Read .distignore patterns into an array
    local ignore_patterns=()
    while IFS= read -r line; do
        # Skip empty lines and comments
        if [[ -n "$line" && ! "$line" =~ ^[[:space:]]*# ]]; then
            # Remove leading/trailing whitespace
            line=$(echo "$line" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')
            if [[ -n "$line" ]]; then
                ignore_patterns+=("$line")
            fi
        fi
    done < "$distignore_file"
    
    # Build rsync exclude options
    local rsync_excludes=()
    for pattern in "${ignore_patterns[@]}"; do
        rsync_excludes+=("--exclude=$pattern")
    done
    
    # Copy files using rsync with exclusions
    if command -v rsync >/dev/null 2>&1; then
        rsync -av "${rsync_excludes[@]}" "$src_dir/" "$dest_dir/"
    else
        # Fallback: use find with exclusions (more complex)
        print_warning "rsync not found, using find as fallback"
        
        # Start with copying everything
        cp -R "$src_dir/." "$dest_dir/"
        
        # Remove excluded patterns
        for pattern in "${ignore_patterns[@]}"; do
            # Handle different pattern types
            if [[ "$pattern" == *"/" ]]; then
                # Directory pattern
                find "$dest_dir" -type d -name "${pattern%/}" -exec rm -rf {} + 2>/dev/null || true
            else
                # File pattern
                find "$dest_dir" -name "$pattern" -exec rm -rf {} + 2>/dev/null || true
            fi
        done
    fi
}

# Function to validate package contents
validate_package() {
    local package_dir="$1"
    
    print_status "Validating package contents..."
    
    # Check that main plugin file exists
    if [ ! -f "$package_dir/$PLUGIN_SLUG.php" ]; then
        print_error "Main plugin file missing from package"
        return 1
    fi
    
    # Check that essential directories exist
    local essential_dirs=("src" "assets")
    for dir in "${essential_dirs[@]}"; do
        if [ ! -d "$package_dir/$dir" ]; then
            print_error "Essential directory missing from package: $dir"
            return 1
        fi
    done
    
    # Check that no development files are included
    local dev_files=("node_modules" ".git" "tests" "package.json" "composer.json")
    for file in "${dev_files[@]}"; do
        if [ -e "$package_dir/$file" ]; then
            print_error "Development file/directory found in package: $file"
            return 1
        fi
    done
    
    # Check PHP syntax in all PHP files
    find "$package_dir" -name "*.php" -exec php -l {} \; >/dev/null 2>&1 || {
        print_error "PHP syntax errors found in package"
        return 1
    }
    
    print_success "Package validation completed"
    return 0
}

# Function to create ZIP package
create_zip() {
    local package_dir="$1"
    local version="$2"
    local zip_filename="$PLUGIN_SLUG-$version.zip"
    local zip_path="$RELEASE_DIR/$zip_filename"
    
    print_status "Creating ZIP package: $zip_filename"
    
    # Ensure release directory exists
    mkdir -p "$RELEASE_DIR"
    
    # Remove existing ZIP if it exists
    if [ -f "$zip_path" ]; then
        rm -f "$zip_path"
    fi
    
    # Create ZIP file
    cd "$BUILD_DIR"
    
    if command -v zip >/dev/null 2>&1; then
        # Use zip command
        zip -rq "$zip_path" "$PLUGIN_SLUG" -x "*.DS_Store*" "*__MACOSX*"
    else
        # Fallback to tar + gzip (though WordPress expects zip)
        print_warning "zip command not found, creating tar.gz instead"
        tar -czf "$RELEASE_DIR/$PLUGIN_SLUG-$version.tar.gz" "$PLUGIN_SLUG"
    fi
    
    cd - >/dev/null
    
    if [ -f "$zip_path" ]; then
        print_success "ZIP package created: $zip_path"
        
        # Display package info
        if command -v zip >/dev/null 2>&1; then
            local file_count=$(zip -sf "$zip_path" | wc -l)
            local file_size=$(ls -lh "$zip_path" | awk '{print $5}')
            print_status "Package contains $file_count files, size: $file_size"
        fi
        
        return 0
    else
        print_error "Failed to create ZIP package"
        return 1
    fi
}

# Function to create checksum file
create_checksums() {
    local zip_path="$1"
    
    if [ -f "$zip_path" ]; then
        print_status "Creating checksums..."
        
        cd "$RELEASE_DIR"
        
        # Create SHA256 checksum
        if command -v sha256sum >/dev/null 2>&1; then
            sha256sum "$(basename "$zip_path")" > "$(basename "$zip_path").sha256"
        elif command -v shasum >/dev/null 2>&1; then
            shasum -a 256 "$(basename "$zip_path")" > "$(basename "$zip_path").sha256"
        fi
        
        # Create MD5 checksum
        if command -v md5sum >/dev/null 2>&1; then
            md5sum "$(basename "$zip_path")" > "$(basename "$zip_path").md5"
        elif command -v md5 >/dev/null 2>&1; then
            md5 "$(basename "$zip_path")" > "$(basename "$zip_path").md5"
        fi
        
        cd - >/dev/null
        
        print_success "Checksums created"
    fi
}

# Function to display package summary
display_summary() {
    local version="$1"
    local zip_path="$2"
    
    echo ""
    print_success "Package created successfully!"
    echo "Plugin: $PLUGIN_SLUG"
    echo "Version: $version"
    echo "Package: $zip_path"
    
    if [ -f "$zip_path" ]; then
        echo "Size: $(ls -lh "$zip_path" | awk '{print $5}')"
        
        # Show checksums if available
        if [ -f "$zip_path.sha256" ]; then
            echo "SHA256: $(cat "$zip_path.sha256" | awk '{print $1}')"
        fi
        
        if [ -f "$zip_path.md5" ]; then
            echo "MD5: $(cat "$zip_path.md5" | awk '{print $1}')"
        fi
    fi
    
    echo ""
    print_status "Package is ready for distribution!"
}

# Main packaging function
main() {
    local version="$1"
    local force=false
    
    # Parse additional arguments
    shift
    while [[ $# -gt 0 ]]; do
        case $1 in
            --force)
                force=true
                shift
                ;;
            -h|--help)
                echo "Usage: $0 VERSION [OPTIONS]"
                echo "Create a distribution package for the plugin"
                echo ""
                echo "Arguments:"
                echo "  VERSION                 Plugin version number"
                echo ""
                echo "Options:"
                echo "  --force                Force overwrite existing package"
                echo "  -h, --help             Show this help message"
                exit 0
                ;;
            *)
                print_error "Unknown option: $1"
                exit 1
                ;;
        esac
    done
    
    # Get version from plugin file if not specified
    if [ -z "$version" ]; then
        version=$(get_plugin_version)
        if [ -z "$version" ]; then
            print_error "Could not determine plugin version. Please specify version as argument."
            echo "Usage: $0 VERSION"
            exit 1
        fi
        print_status "Using version from plugin file: $version"
    fi
    
    # Check if package already exists
    local zip_path="$RELEASE_DIR/$PLUGIN_SLUG-$version.zip"
    if [ -f "$zip_path" ] && [ "$force" = false ]; then
        print_error "Package already exists: $zip_path"
        print_status "Use --force to overwrite existing package"
        exit 1
    fi
    
    print_status "Creating package for $PLUGIN_SLUG version $version"
    
    # Ensure directories exist
    mkdir -p "$BUILD_DIR"
    mkdir -p "$RELEASE_DIR"
    mkdir -p "$TEMP_DIR"
    
    # Create package directory
    local package_dir="$BUILD_DIR/$PLUGIN_SLUG"
    
    # Remove existing package directory
    if [ -d "$package_dir" ]; then
        rm -rf "$package_dir"
    fi
    
    # Create .distignore if needed
    create_distignore
    
    # Copy files respecting .distignore
    copy_files "$PLUGIN_DIR" "$package_dir" "$PLUGIN_DIR/.distignore"
    
    # Validate package
    if ! validate_package "$package_dir"; then
        print_error "Package validation failed"
        exit 1
    fi
    
    # Create ZIP package
    if ! create_zip "$package_dir" "$version"; then
        print_error "Failed to create package"
        exit 1
    fi
    
    # Create checksums
    create_checksums "$zip_path"
    
    # Display summary
    display_summary "$version" "$zip_path"
    
    # Cleanup temporary files
    if [ -d "$TEMP_DIR" ]; then
        rm -rf "$TEMP_DIR"
    fi
}

# Validate arguments
if [ $# -eq 0 ]; then
    version=$(get_plugin_version)
    if [ -z "$version" ]; then
        print_error "No version specified and could not determine from plugin file"
        echo "Usage: $0 VERSION [OPTIONS]"
        exit 1
    fi
    main "$version"
else
    main "$@"
fi