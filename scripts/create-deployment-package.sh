#!/bin/bash

# Create Deployment Package Script for Woo AI Assistant
#
# Simple script to create a deployment-ready package without full deployment process.
# Used by CI/CD when only packaging is needed.
#
# @package WooAiAssistant
# @subpackage Scripts
# @since 1.0.0
# @author Claude Code Assistant

set -euo pipefail

readonly SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
readonly PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
readonly PLUGIN_SLUG="woo-ai-assistant"

# Get plugin version
get_plugin_version() {
    grep "Version:" "$PROJECT_ROOT/woo-ai-assistant.php" | awk -F: '{print $2}' | tr -d ' '
}

# Main packaging function
create_package() {
    local environment="${1:-production}"
    local version
    version=$(get_plugin_version)
    local package_name="${PLUGIN_SLUG}-${version}-${environment}"
    local package_path="$PROJECT_ROOT/${package_name}.zip"

    echo "📦 Creating deployment package: $package_name.zip"

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
        "${PLUGIN_SLUG}-*-*.zip"
    )

    # Create the zip file
    local exclude_args=()
    for pattern in "${exclude_patterns[@]}"; do
        exclude_args+=("-x" "$pattern")
    done

    cd "$PROJECT_ROOT"
    zip -r "$package_path" . "${exclude_args[@]}"

    if [[ -f "$package_path" ]]; then
        local package_size
        package_size=$(du -h "$package_path" | cut -f1)
        echo "✅ Package created successfully: $package_name.zip ($package_size)"
        echo "📍 Location: $package_path"
    else
        echo "❌ Failed to create package"
        exit 1
    fi
}

# Help function
show_help() {
    cat << EOF
📦 Woo AI Assistant Package Creator

USAGE:
    $0 [ENVIRONMENT]

ARGUMENTS:
    ENVIRONMENT    Target environment (development|staging|production) [default: production]

EXAMPLES:
    $0                    # Create production package
    $0 staging            # Create staging package
    $0 development        # Create development package

EOF
}

# Main execution
main() {
    case "${1:-}" in
        -h|--help|help)
            show_help
            exit 0
            ;;
        ""|production|staging|development)
            create_package "${1:-production}"
            ;;
        *)
            echo "❌ Invalid environment: $1"
            echo "Valid environments: development, staging, production"
            exit 1
            ;;
    esac
}

# Run main function
main "$@"