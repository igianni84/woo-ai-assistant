#!/bin/bash

# Woo AI Assistant - Development Seed Data Script
#
# This script creates sample data for development and testing purposes.
# Run this script to populate your development environment with realistic
# test data including conversations, products, knowledge base entries, and more.
#
# @package WooAiAssistant
# @since 1.0.0

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Script directory and project root
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

echo -e "${BLUE}🌱 Woo AI Assistant - Development Seed Data${NC}"
echo "=============================================="

# Check if we're in a WordPress environment
if [ ! -f "$PROJECT_ROOT/../../wp-config.php" ] && [ ! -f "$PROJECT_ROOT/../../../wp-config.php" ]; then
    echo -e "${RED}❌ WordPress installation not found.${NC}"
    echo "This script must be run from within a WordPress plugin directory."
    exit 1
fi

# Check if WP-CLI is available
if command -v wp &> /dev/null; then
    echo -e "${GREEN}✅ WP-CLI found, using WP-CLI method${NC}"
    USE_WPCLI=true
else
    echo -e "${YELLOW}⚠️  WP-CLI not found, using direct PHP execution${NC}"
    USE_WPCLI=false
fi

# Function to run with WP-CLI
run_with_wpcli() {
    local action=$1
    cd "$PROJECT_ROOT/../.." # Go to WordPress root
    
    if [ "$action" = "cleanup" ]; then
        echo -e "${YELLOW}🧹 Cleaning up existing seed data...${NC}"
        wp eval-file "$PROJECT_ROOT/scripts/seed-development-data.php" --quiet
    else
        echo -e "${BLUE}🌱 Creating development seed data...${NC}"
        wp eval-file "$PROJECT_ROOT/scripts/seed-development-data.php" --quiet
    fi
}

# Function to run with direct PHP
run_with_php() {
    local action=$1
    cd "$PROJECT_ROOT/../.." # Go to WordPress root
    
    # Create a temporary PHP script to bootstrap WordPress
    cat > /tmp/woo_ai_seed_bootstrap.php << 'EOF'
<?php
// Bootstrap WordPress
require_once 'wp-config.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';

// Load the seed data script
$seed_script = dirname(__FILE__) . '/wp-content/plugins/woo-ai-assistant/scripts/seed-development-data.php';
if (file_exists($seed_script)) {
    include $seed_script;
    
    $seeder = new WooAiAssistantSeedData();
    
    if (isset($argv[1]) && $argv[1] === 'cleanup') {
        $seeder->cleanup();
    } else {
        $seeder->createAll();
    }
} else {
    echo "❌ Seed data script not found at: $seed_script\n";
    exit(1);
}
EOF

    if [ "$action" = "cleanup" ]; then
        echo -e "${YELLOW}🧹 Cleaning up existing seed data...${NC}"
        php /tmp/woo_ai_seed_bootstrap.php cleanup
    else
        echo -e "${BLUE}🌱 Creating development seed data...${NC}"
        php /tmp/woo_ai_seed_bootstrap.php
    fi
    
    # Clean up temporary file
    rm -f /tmp/woo_ai_seed_bootstrap.php
}

# Parse command line arguments
ACTION="create"
FORCE=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --cleanup)
            ACTION="cleanup"
            shift
            ;;
        --force)
            FORCE=true
            shift
            ;;
        --help|-h)
            echo "Usage: $0 [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --cleanup    Remove all seed data instead of creating it"
            echo "  --force      Force execution without confirmation prompts"
            echo "  --help, -h   Show this help message"
            echo ""
            echo "Examples:"
            echo "  $0                 # Create seed data"
            echo "  $0 --cleanup       # Remove seed data"
            echo "  $0 --force         # Create seed data without prompts"
            exit 0
            ;;
        *)
            echo -e "${RED}❌ Unknown option: $1${NC}"
            echo "Use --help for usage information."
            exit 1
            ;;
    esac
done

# Confirmation prompt unless --force is used
if [ "$FORCE" = false ]; then
    if [ "$ACTION" = "cleanup" ]; then
        echo -e "${YELLOW}⚠️  This will remove all seed data including:${NC}"
        echo "   - Sample products and pages"
        echo "   - Test conversations and messages"
        echo "   - Knowledge base entries"
        echo "   - Usage statistics"
        echo ""
        read -p "Are you sure you want to continue? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            echo -e "${BLUE}ℹ️  Operation cancelled.${NC}"
            exit 0
        fi
    else
        echo -e "${BLUE}ℹ️  This will create sample data for development including:${NC}"
        echo "   - Sample products with detailed descriptions"
        echo "   - Test pages and FAQ posts"
        echo "   - Sample conversations with AI responses"
        echo "   - Knowledge base entries for testing"
        echo "   - Usage statistics for dashboard testing"
        echo ""
        read -p "Continue? (Y/n): " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Nn]$ ]]; then
            echo -e "${BLUE}ℹ️  Operation cancelled.${NC}"
            exit 0
        fi
    fi
fi

# Execute the appropriate method
if [ "$USE_WPCLI" = true ]; then
    run_with_wpcli "$ACTION"
else
    run_with_php "$ACTION"
fi

echo ""
if [ "$ACTION" = "cleanup" ]; then
    echo -e "${GREEN}✅ Seed data cleanup completed successfully!${NC}"
else
    echo -e "${GREEN}✅ Development seed data created successfully!${NC}"
    echo ""
    echo -e "${BLUE}📋 Next Steps:${NC}"
    echo "1. Visit your WordPress admin area"
    echo "2. Navigate to the AI Assistant menu"
    echo "3. Check the dashboard for sample analytics"
    echo "4. Test the chat widget on your frontend"
    echo "5. Review conversation logs and knowledge base entries"
    echo ""
    echo -e "${YELLOW}💡 Pro Tip:${NC} Run with --cleanup to remove all seed data when done testing."
fi

echo ""
echo -e "${BLUE}🚀 Happy developing!${NC}"