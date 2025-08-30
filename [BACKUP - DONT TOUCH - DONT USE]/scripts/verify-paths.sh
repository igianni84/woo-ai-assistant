#!/bin/bash

# verify-paths.sh - Progressive path verification based on current phase
# Usage: ./scripts/verify-paths.sh [phase]

PHASE=${1:-0}
ERRORS=0

echo "🔍 Verifying paths for Phase $PHASE..."
echo "=================================="

# Phase 0 - Foundation paths
if [ $PHASE -ge 0 ]; then
    echo ""
    echo "📁 Phase 0 - Foundation Structure:"
    
    # Check main plugin file
    if [ -f "woo-ai-assistant.php" ]; then
        echo "✅ Main plugin file exists"
    else
        echo "❌ Main plugin file missing"
        ERRORS=$((ERRORS + 1))
    fi
    
    # Check configuration files
    for file in composer.json package.json phpunit.xml webpack.config.js jest.config.js; do
        if [ -f "$file" ]; then
            echo "✅ $file exists"
        else
            echo "❌ $file missing"
            ERRORS=$((ERRORS + 1))
        fi
    done
    
    # Check directories
    for dir in src widget-src tests scripts; do
        if [ -d "$dir" ]; then
            echo "✅ $dir/ directory exists"
        else
            echo "⚠️  $dir/ directory missing (will be created in Phase $PHASE)"
        fi
    done
fi

# Phase 1 - Core Infrastructure
if [ $PHASE -ge 1 ]; then
    echo ""
    echo "📁 Phase 1 - Core Infrastructure:"
    
    # Check core PHP files
    if [ -f "src/Main.php" ]; then
        echo "✅ src/Main.php exists"
    else
        echo "❌ src/Main.php missing"
        ERRORS=$((ERRORS + 1))
    fi
    
    for file in "src/Setup/Activator.php" "src/Setup/Deactivator.php" "src/Admin/AdminMenu.php"; do
        if [ -f "$file" ]; then
            echo "✅ $file exists"
        else
            echo "❌ $file missing"
            ERRORS=$((ERRORS + 1))
        fi
    done
fi

# Phase 2 - Knowledge Base
if [ $PHASE -ge 2 ]; then
    echo ""
    echo "📁 Phase 2 - Knowledge Base:"
    
    for file in "src/KnowledgeBase/Scanner.php" "src/KnowledgeBase/Indexer.php" "src/KnowledgeBase/ChunkingStrategy.php"; do
        if [ -f "$file" ]; then
            echo "✅ $file exists"
        else
            echo "❌ $file missing"
            ERRORS=$((ERRORS + 1))
        fi
    done
fi

# Phase 3 - Server Integration
if [ $PHASE -ge 3 ]; then
    echo ""
    echo "📁 Phase 3 - Server Integration:"
    
    for file in "src/Api/IntermediateServerClient.php" "src/Api/LicenseManager.php"; do
        if [ -f "$file" ]; then
            echo "✅ $file exists"
        else
            echo "❌ $file missing"
            ERRORS=$((ERRORS + 1))
        fi
    done
fi

# Phase 4 - Widget Frontend
if [ $PHASE -ge 4 ]; then
    echo ""
    echo "📁 Phase 4 - Widget Frontend:"
    
    for file in "widget-src/src/App.js" "widget-src/src/index.js" "widget-src/src/components/ChatWindow.js"; do
        if [ -f "$file" ]; then
            echo "✅ $file exists"
        else
            echo "❌ $file missing"
            ERRORS=$((ERRORS + 1))
        fi
    done
fi

echo ""
echo "=================================="

if [ $ERRORS -eq 0 ]; then
    echo "✅ All required paths for Phase $PHASE verified successfully!"
    exit 0
else
    echo "❌ Found $ERRORS missing files/directories for Phase $PHASE"
    echo "   Run the appropriate task to create missing files."
    exit 1
fi