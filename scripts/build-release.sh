#!/bin/bash

# Build Release Script for Woo AI Assistant Plugin
# 
# This script handles the complete build process for creating a production-ready
# release package of the plugin, including frontend compilation, code verification,
# and package creation.
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

# Function to get plugin version from main plugin file
get_plugin_version() {
    grep "Version:" "$PLUGIN_DIR/$PLUGIN_SLUG.php" | sed 's/.*Version: *\([0-9.]*\).*/\1/'
}

# Function to clean build directory
clean_build_dir() {
    print_status "Cleaning build directory..."
    if [ -d "$BUILD_DIR" ]; then
        rm -rf "$BUILD_DIR"
    fi
    mkdir -p "$BUILD_DIR"
    mkdir -p "$RELEASE_DIR"
}

# Function to verify environment
verify_environment() {
    print_status "Verifying build environment..."
    
    # Check if we're in the plugin directory
    if [ ! -f "$PLUGIN_DIR/$PLUGIN_SLUG.php" ]; then
        print_error "Main plugin file not found. Are you in the plugin directory?"
        exit 1
    fi
    
    # Check for required commands
    local required_commands=("node" "npm" "php" "composer")
    for cmd in "${required_commands[@]}"; do
        if ! command_exists "$cmd"; then
            print_error "Required command '$cmd' not found. Please install it first."
            exit 1
        fi
    done
    
    print_success "Environment verification completed"
}

# Function to install dependencies
install_dependencies() {
    print_status "Installing dependencies..."
    
    # Install PHP dependencies (production only)
    if [ -f "$PLUGIN_DIR/composer.json" ]; then
        print_status "Installing PHP dependencies..."
        cd "$PLUGIN_DIR"
        composer install --no-dev --optimize-autoloader --no-interaction
        cd - >/dev/null
    fi
    
    # Install Node.js dependencies
    if [ -f "$PLUGIN_DIR/package.json" ]; then
        print_status "Installing Node.js dependencies..."
        cd "$PLUGIN_DIR"
        npm ci --production=false
        cd - >/dev/null
    fi
    
    print_success "Dependencies installed successfully"
}

# Function to run quality gates
run_quality_gates() {
    print_status "Running quality gates..."
    
    cd "$PLUGIN_DIR"
    
    # Run PHP CodeSniffer
    if [ -f "vendor/bin/phpcs" ]; then
        print_status "Running PHP CodeSniffer..."
        vendor/bin/phpcs --standard=phpcs.xml src/ || {
            print_error "PHP CodeSniffer found issues. Please fix them before building."
            exit 1
        }
    fi
    
    # Run PHPStan
    if [ -f "vendor/bin/phpstan" ]; then
        print_status "Running PHPStan analysis..."
        vendor/bin/phpstan analyse --configuration=phpstan.neon || {
            print_error "PHPStan found issues. Please fix them before building."
            exit 1
        }
    fi
    
    # Run unit tests
    if [ -f "vendor/bin/phpunit" ]; then
        print_status "Running PHP unit tests..."
        vendor/bin/phpunit --configuration=phpunit.xml || {
            print_error "Unit tests failed. Please fix them before building."
            exit 1
        }
    fi
    
    # Run JavaScript tests
    if [ -f "$PLUGIN_DIR/package.json" ]; then
        print_status "Running JavaScript tests..."
        npm test -- --watchAll=false || {
            print_error "JavaScript tests failed. Please fix them before building."
            exit 1
        }
    fi
    
    cd - >/dev/null
    print_success "All quality gates passed"
}

# Function to build frontend assets
build_frontend() {
    print_status "Building frontend assets..."
    
    if [ -f "$PLUGIN_DIR/package.json" ]; then
        cd "$PLUGIN_DIR"
        
        # Build production assets
        print_status "Compiling JavaScript and CSS..."
        npm run build
        
        # Check if build was successful
        if [ ! -f "assets/js/widget.min.js" ]; then
            print_error "Frontend build failed - widget.min.js not found"
            exit 1
        fi
        
        if [ ! -f "assets/css/widget.min.css" ]; then
            print_error "Frontend build failed - widget.min.css not found"
            exit 1
        fi
        
        cd - >/dev/null
        print_success "Frontend assets built successfully"
    else
        print_warning "No package.json found, skipping frontend build"
    fi
}

# Function to generate documentation
generate_documentation() {
    print_status "Generating documentation..."
    
    if [ -f "$PLUGIN_DIR/scripts/generate-docs.sh" ]; then
        cd "$PLUGIN_DIR"
        bash scripts/generate-docs.sh
        cd - >/dev/null
        print_success "Documentation generated successfully"
    else
        print_warning "Documentation generation script not found, skipping"
    fi
}

# Function to create build manifest
create_build_manifest() {
    local version="$1"
    local build_date=$(date -u +"%Y-%m-%d %H:%M:%S UTC")
    local git_commit=""
    
    # Get git commit if available
    if command_exists git && [ -d "$PLUGIN_DIR/.git" ]; then
        git_commit=$(git rev-parse --short HEAD 2>/dev/null || echo "unknown")
    fi
    
    print_status "Creating build manifest..."
    
    cat > "$BUILD_DIR/build-manifest.json" << EOF
{
  "plugin_name": "Woo AI Assistant",
  "plugin_slug": "$PLUGIN_SLUG",
  "version": "$version",
  "build_date": "$build_date",
  "git_commit": "$git_commit",
  "php_version": "$(php --version | head -n1 | cut -d' ' -f2)",
  "node_version": "$(node --version)",
  "npm_version": "$(npm --version)",
  "build_environment": "production",
  "quality_gates_passed": true
}
EOF
    
    print_success "Build manifest created"
}

# Function to validate plugin structure
validate_plugin_structure() {
    print_status "Validating plugin structure..."
    
    # Check required files
    local required_files=(
        "$PLUGIN_SLUG.php"
        "src/Main.php"
        "assets/js/widget.min.js"
        "assets/css/widget.min.css"
    )
    
    for file in "${required_files[@]}"; do
        if [ ! -f "$PLUGIN_DIR/$file" ]; then
            print_error "Required file missing: $file"
            exit 1
        fi
    done
    
    # Check that PHP files can be parsed
    find "$PLUGIN_DIR/src" -name "*.php" -exec php -l {} \; >/dev/null || {
        print_error "PHP syntax errors found in source files"
        exit 1
    }
    
    print_success "Plugin structure validation completed"
}

# Function to create release package
create_package() {
    local version="$1"
    
    print_status "Creating release package..."
    
    # Use the package script to create the ZIP file
    if [ -f "$PLUGIN_DIR/scripts/package-plugin.sh" ]; then
        cd "$PLUGIN_DIR"
        bash scripts/package-plugin.sh "$version"
        cd - >/dev/null
    else
        print_error "Package script not found"
        exit 1
    fi
    
    print_success "Release package created successfully"
}

# Main build function
main() {
    local version
    local build_type="stable"
    local skip_tests=false
    
    # Parse command line arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            -v|--version)
                version="$2"
                shift 2
                ;;
            --dev)
                build_type="development"
                shift
                ;;
            --skip-tests)
                skip_tests=true
                shift
                ;;
            -h|--help)
                echo "Usage: $0 [OPTIONS]"
                echo "Build a release package for the Woo AI Assistant plugin"
                echo ""
                echo "Options:"
                echo "  -v, --version VERSION    Specify version number"
                echo "  --dev                    Create development build"
                echo "  --skip-tests            Skip test execution"
                echo "  -h, --help              Show this help message"
                exit 0
                ;;
            *)
                print_error "Unknown option: $1"
                echo "Use -h or --help for usage information"
                exit 1
                ;;
        esac
    done
    
    # Get version from plugin file if not specified
    if [ -z "$version" ]; then
        version=$(get_plugin_version)
        if [ -z "$version" ]; then
            print_error "Could not determine plugin version"
            exit 1
        fi
    fi
    
    print_status "Starting build process for version $version ($build_type build)"
    
    # Execute build steps
    clean_build_dir
    verify_environment
    validate_plugin_structure
    
    if [ "$build_type" = "stable" ]; then
        install_dependencies
        
        if [ "$skip_tests" = false ]; then
            run_quality_gates
        else
            print_warning "Skipping tests as requested"
        fi
        
        build_frontend
        generate_documentation
    fi
    
    create_build_manifest "$version"
    create_package "$version"
    
    # Display build summary
    echo ""
    print_success "Build completed successfully!"
    echo "Version: $version"
    echo "Build Type: $build_type"
    echo "Package Location: $RELEASE_DIR"
    echo "Build Manifest: $BUILD_DIR/build-manifest.json"
    
    # List created files
    if [ -d "$RELEASE_DIR" ]; then
        echo ""
        print_status "Created files:"
        ls -la "$RELEASE_DIR/"
    fi
}

# Run main function with all arguments
main "$@"