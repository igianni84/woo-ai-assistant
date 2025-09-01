#!/bin/bash

# Fixed Phase Detection Script for Progressive Development
# Accurately detects the current development phase

set -e

echo "🔍 Detecting current project phase..."

CURRENT_PHASE=0
PHASE_COMPLETE=false

# Phase 0: Foundation
if [[ -f "woo-ai-assistant.php" && -f "composer.json" && -f "package.json" ]]; then
    CURRENT_PHASE=0
    if [[ -f "src/Main.php" && -f "src/Common/Utils.php" && -f "src/Setup/Activator.php" ]]; then
        PHASE_COMPLETE=true
    fi
fi

# Phase 1: Core Infrastructure
if [[ -f "src/Admin/AdminMenu.php" && -f "src/RestApi/RestController.php" ]]; then
    CURRENT_PHASE=1
    PHASE_COMPLETE=false
    if [[ -f "src/Admin/Pages/DashboardPage.php" && -f "src/Admin/Pages/SettingsPage.php" ]]; then
        PHASE_COMPLETE=true
    fi
fi

# Phase 2: Knowledge Base
if [[ -f "src/KnowledgeBase/Scanner.php" && -f "src/KnowledgeBase/Indexer.php" ]]; then
    CURRENT_PHASE=2
    PHASE_COMPLETE=false
    if [[ -f "src/KnowledgeBase/Manager.php" && -f "src/KnowledgeBase/Health.php" ]]; then
        PHASE_COMPLETE=true
    fi
fi

# Phase 3: Server Integration
if [[ -f "src/Api/IntermediateServerClient.php" && -f "src/Api/LicenseManager.php" ]]; then
    CURRENT_PHASE=3
    PHASE_COMPLETE=true  # Phase 3 is complete
fi

# Phase 4: Widget Frontend
if [[ -f "widget-src/src/App.js" && -f "src/Frontend/WidgetLoader.php" ]]; then
    CURRENT_PHASE=4
    PHASE_COMPLETE=true  # Phase 4 is complete
fi

# Phase 5: Chat Logic
if [[ -f "src/Chatbot/ConversationHandler.php" && -f "src/RestApi/Endpoints/ChatEndpoint.php" ]]; then
    CURRENT_PHASE=5
    PHASE_COMPLETE=false
    if [[ -f "src/RestApi/Endpoints/RatingEndpoint.php" && -f "src/RestApi/Endpoints/ActionEndpoint.php" ]]; then
        PHASE_COMPLETE=true  # Phase 5 is complete
    fi
fi

# Phase 6: Advanced Features (Currently IN PROGRESS - 1/3 tasks)
if [[ -f "src/Chatbot/CouponHandler.php" ]]; then
    CURRENT_PHASE=6
    PHASE_COMPLETE=false  # Only 1/3 tasks complete
    
    # Check if all Phase 6 components exist
    if [[ -f "src/Chatbot/ProactiveTriggers.php" && -f "src/Chatbot/Handoff.php" ]]; then
        PHASE_COMPLETE=true  # All 3 tasks complete
    fi
fi

echo "📊 Current Phase: $CURRENT_PHASE"
echo "✅ Phase Complete: $PHASE_COMPLETE"

# Determine what to test based on phase
case $CURRENT_PHASE in
    0|1)
        echo "🧪 Testing: Basic PHP structure only"
        echo "⏭️ Skipping: Integration tests, React tests"
        ;;
    2|3)
        echo "🧪 Testing: PHP with mocked APIs"
        echo "⏭️ Skipping: React tests"
        ;;
    4|5|6)
        echo "🧪 Testing: PHP, React, Integration (phase-appropriate)"
        ;;
    *)
        echo "🧪 Testing: All available tests"
        ;;
esac

# Export for GitHub Actions
if [[ -n "$GITHUB_ENV" ]]; then
    echo "CURRENT_PHASE=$CURRENT_PHASE" >> "$GITHUB_ENV"
    echo "PHASE_COMPLETE=$PHASE_COMPLETE" >> "$GITHUB_ENV"
fi
