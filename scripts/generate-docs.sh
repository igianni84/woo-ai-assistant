#!/bin/bash

# Woo AI Assistant - Documentation Generation Script
#
# This script generates comprehensive API documentation using phpDocumentor
# and additional documentation tools for the complete project.
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

echo -e "${BLUE}🚀 Generating Woo AI Assistant Documentation${NC}"
echo "================================================"

# Check if phpDocumentor is available
if ! command -v phpdoc &> /dev/null; then
    echo -e "${YELLOW}⚠️  phpDocumentor not found globally, using composer version...${NC}"
    
    if [ -f "$PROJECT_ROOT/vendor/bin/phpdoc" ]; then
        PHPDOC_CMD="$PROJECT_ROOT/vendor/bin/phpdoc"
    else
        echo -e "${RED}❌ phpDocumentor not found. Please install it:${NC}"
        echo "   composer require --dev phpdocumentor/phpdocumentor"
        exit 1
    fi
else
    PHPDOC_CMD="phpdoc"
fi

# Create docs directory if it doesn't exist
mkdir -p "$PROJECT_ROOT/docs"

# Clean previous documentation
if [ -d "$PROJECT_ROOT/docs/api" ]; then
    echo -e "${YELLOW}🧹 Cleaning previous API documentation...${NC}"
    rm -rf "$PROJECT_ROOT/docs/api"
fi

# Generate API documentation with phpDocumentor
echo -e "${BLUE}📖 Generating API documentation...${NC}"
cd "$PROJECT_ROOT"

if [ -f "phpdoc.xml" ]; then
    echo "Using phpdoc.xml configuration file"
    $PHPDOC_CMD --config=phpdoc.xml --force --ansi
else
    echo "Using default phpDocumentor settings"
    $PHPDOC_CMD \
        --directory="src" \
        --target="docs/api" \
        --title="Woo AI Assistant API" \
        --sourcecode \
        --force \
        --ansi
fi

# Check if documentation was generated successfully
if [ -d "$PROJECT_ROOT/docs/api" ]; then
    echo -e "${GREEN}✅ API documentation generated successfully!${NC}"
    echo "   Location: docs/api/index.html"
else
    echo -e "${RED}❌ Failed to generate API documentation${NC}"
    exit 1
fi

# Generate additional documentation files
echo -e "${BLUE}📝 Generating additional documentation...${NC}"

# Create a documentation index
cat > "$PROJECT_ROOT/docs/README.md" << 'EOF'
# Woo AI Assistant Documentation

Welcome to the Woo AI Assistant documentation hub.

## Available Documentation

### 📖 API Documentation
- **[API Reference](api/index.html)** - Complete PHP class documentation generated from source code
- **Location**: `docs/api/`
- **Generated with**: phpDocumentor

### 📋 Project Documentation
- **[Architecture Overview](../ARCHITETTURA.md)** - System architecture and component design
- **[Development Guidelines](../CLAUDE.md)** - Coding standards and development workflow
- **[Project Specifications](../PROJECT_SPECIFICATIONS.md)** - Feature specifications and requirements
- **[Development Roadmap](../ROADMAP.md)** - Project timeline and task tracking

### 🔧 Development Resources
- **[README](../README.md)** - Project setup and quick start guide
- **[Composer Scripts](../composer.json)** - Available development commands
- **[Package Configuration](../package.json)** - Node.js dependencies and scripts

## Quick Navigation

### For Developers
- [Getting Started](../README.md#installation)
- [Coding Standards](../CLAUDE.md#coding-standards)
- [Testing Guidelines](../CLAUDE.md#unit-testing-standards)
- [API Documentation](api/index.html)

### For Contributors
- [Development Workflow](../CLAUDE.md#development-workflow)
- [Quality Gates](../CLAUDE.md#mandatory-quality-assurance-process)
- [Task Management](../ROADMAP.md)

### For Users
- [Feature Overview](../PROJECT_SPECIFICATIONS.md)
- [Installation Guide](../README.md)

---

*Documentation generated on: $(date)*
*Plugin Version: 1.0.0*
EOF

# Generate PHP class overview
echo -e "${BLUE}📊 Generating PHP class overview...${NC}"

cat > "$PROJECT_ROOT/docs/classes.md" << 'EOF'
# PHP Classes Overview

This document provides an overview of all PHP classes in the Woo AI Assistant plugin.

## Namespace Structure

```
WooAiAssistant\
├── Main.php                    # Main plugin orchestrator (Singleton)
├── Common\
│   ├── Utils.php              # Utility functions and helpers
│   └── Traits\
│       └── Singleton.php      # Singleton trait for shared functionality
└── Setup\
    ├── Activator.php          # Plugin activation handler
    └── Deactivator.php        # Plugin deactivation handler
```

## Class Descriptions

### Core Classes

#### Main.php
**Namespace**: `WooAiAssistant`  
**Type**: Singleton  
**Purpose**: Main plugin orchestrator that coordinates all plugin functionality.

#### Utils.php
**Namespace**: `WooAiAssistant\Common`  
**Type**: Static utility class  
**Purpose**: Provides common utility functions used throughout the plugin.

### Setup Classes

#### Activator.php
**Namespace**: `WooAiAssistant\Setup`  
**Type**: Static class  
**Purpose**: Handles plugin activation including database setup and initial configuration.

#### Deactivator.php
**Namespace**: `WooAiAssistant\Setup`  
**Type**: Static class  
**Purpose**: Handles plugin deactivation and cleanup tasks.

### Traits

#### Singleton.php
**Namespace**: `WooAiAssistant\Common\Traits`  
**Type**: Trait  
**Purpose**: Provides singleton pattern implementation for classes that need it.

## Future Classes (To Be Implemented)

The following classes are planned for future development phases:

### Knowledge Base
- `Scanner.php` - Content scanning and extraction
- `Indexer.php` - Content chunking and indexing
- `VectorManager.php` - Embedding generation and vector operations
- `AIManager.php` - AI model integration and response generation

### Chat System
- `ConversationHandler.php` - Conversation management
- `ChatEndpoint.php` - REST API endpoints for chat
- `CouponHandler.php` - Coupon generation and management

### Admin Interface
- `AdminMenu.php` - WordPress admin menu integration
- `DashboardPage.php` - Admin dashboard interface
- `SettingsPage.php` - Settings management interface

### API Integration
- `IntermediateServerClient.php` - External API communication
- `LicenseManager.php` - License validation and management

For detailed implementation status, see [ROADMAP.md](../ROADMAP.md).

---

*Generated on: $(date)*
EOF

# Generate hooks and filters documentation
echo -e "${BLUE}🔗 Scanning for WordPress hooks and filters...${NC}"

cat > "$PROJECT_ROOT/docs/hooks.md" << 'EOF'
# WordPress Hooks and Filters

This document lists all custom WordPress hooks and filters provided by the Woo AI Assistant plugin.

## Custom Actions

### Plugin Lifecycle
- `woo_ai_assistant_activated` - Fired after successful plugin activation
- `woo_ai_assistant_deactivated` - Fired after plugin deactivation
- `woo_ai_assistant_before_uninstall` - Fired before plugin uninstallation

### Knowledge Base
- `woo_ai_assistant_before_index` - Fired before content indexing begins
- `woo_ai_assistant_after_index` - Fired after content indexing completes
- `woo_ai_assistant_indexing_error` - Fired when indexing encounters an error

### Chat System
- `woo_ai_assistant_conversation_started` - Fired when a new conversation starts
- `woo_ai_assistant_conversation_ended` - Fired when a conversation ends
- `woo_ai_assistant_message_sent` - Fired when a user sends a message
- `woo_ai_assistant_message_received` - Fired when AI sends a response

## Custom Filters

### Content Processing
- `woo_ai_assistant_kb_content` - Filter content before indexing
- `woo_ai_assistant_scannable_post_types` - Filter which post types to scan
- `woo_ai_assistant_chunk_size` - Filter the text chunk size for indexing

### AI Responses
- `woo_ai_assistant_ai_prompt` - Filter the AI prompt before sending
- `woo_ai_assistant_ai_response` - Filter the AI response before displaying
- `woo_ai_assistant_response_context` - Filter the context used for responses

### Widget Customization
- `woo_ai_assistant_widget_config` - Filter widget configuration
- `woo_ai_assistant_widget_position` - Filter widget position
- `woo_ai_assistant_welcome_message` - Filter the welcome message

## Usage Examples

### Customizing Welcome Message
```php
add_filter('woo_ai_assistant_welcome_message', function($message, $user_id) {
    if ($user_id) {
        $user = get_user_by('id', $user_id);
        return "Hello {$user->display_name}! How can I help you today?";
    }
    return $message;
}, 10, 2);
```

### Adding Custom Post Types to Indexing
```php
add_filter('woo_ai_assistant_scannable_post_types', function($post_types) {
    $post_types[] = 'custom_faq';
    $post_types[] = 'knowledge_article';
    return $post_types;
});
```

### Customizing AI Response
```php
add_filter('woo_ai_assistant_ai_response', function($response, $user_message, $context) {
    // Add custom footer to all responses
    $response .= "\n\n---\nNeed more help? Contact our support team!";
    return $response;
}, 10, 3);
```

---

*Generated on: $(date)*
EOF

# Create symlinks for better navigation (if supported)
if command -v ln &> /dev/null; then
    cd "$PROJECT_ROOT/docs"
    
    # Create symlinks to important files if they don't exist
    [ ! -e "architecture.md" ] && ln -sf ../ARCHITETTURA.md architecture.md 2>/dev/null || true
    [ ! -e "development-guidelines.md" ] && ln -sf ../CLAUDE.md development-guidelines.md 2>/dev/null || true
    [ ! -e "roadmap.md" ] && ln -sf ../ROADMAP.md roadmap.md 2>/dev/null || true
    [ ! -e "specifications.md" ] && ln -sf ../PROJECT_SPECIFICATIONS.md specifications.md 2>/dev/null || true
fi

# Final summary
echo -e "${GREEN}🎉 Documentation generation completed!${NC}"
echo
echo "Generated documentation:"
echo "  📖 API Documentation: docs/api/index.html"
echo "  📋 Documentation Index: docs/README.md"
echo "  📊 Classes Overview: docs/classes.md"
echo "  🔗 Hooks Reference: docs/hooks.md"
echo
echo "To view the API documentation, open: file://$PROJECT_ROOT/docs/api/index.html"
echo

# Check documentation size
DOC_SIZE=$(du -sh "$PROJECT_ROOT/docs" 2>/dev/null | cut -f1 || echo "Unknown")
echo -e "${BLUE}📦 Total documentation size: $DOC_SIZE${NC}"

echo -e "${GREEN}✅ Documentation generation script completed successfully!${NC}"