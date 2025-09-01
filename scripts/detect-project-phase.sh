#!/bin/bash

# Phase Detection Script for Progressive GitHub Actions
# Detects current project phase based on existing files and returns phase-specific configuration

set -e

# Default values
CURRENT_PHASE=0
COMPLETED_PHASES=()
COVERAGE_REQUIREMENT=30
PHP_TEST_GROUP="phase0"
JS_TEST_PATH="foundation"

echo "🔍 Detecting current project phase based on existing files..."

# Phase 0: Foundation - Check basic structure
if [[ -f "woo-ai-assistant.php" && -f "composer.json" && -f "package.json" && -d "src" ]]; then
    CURRENT_PHASE=0
    COMPLETED_PHASES+=("phase0")
    echo "✅ Phase 0 (Foundation) - Basic structure exists"
fi

# Phase 1: Core Infrastructure - Check admin and REST API
if [[ -f "src/Main.php" && -f "src/Admin/AdminMenu.php" && -f "src/RestApi/RestController.php" ]]; then
    CURRENT_PHASE=1
    COMPLETED_PHASES+=("phase1")
    COVERAGE_REQUIREMENT=50
    PHP_TEST_GROUP="phase0,phase1"
    JS_TEST_PATH="(foundation|core)"
    echo "✅ Phase 1 (Core Infrastructure) - Admin and REST API exists"
fi

# Phase 2: Knowledge Base - Check KB components
if [[ -f "src/KnowledgeBase/Scanner.php" && -f "src/KnowledgeBase/Indexer.php" && -f "src/KnowledgeBase/VectorManager.php" ]]; then
    CURRENT_PHASE=2
    COMPLETED_PHASES+=("phase2")
    COVERAGE_REQUIREMENT=70
    PHP_TEST_GROUP="phase0,phase1,phase2"
    JS_TEST_PATH="(foundation|core|knowledge)"
    echo "✅ Phase 2 (Knowledge Base) - KB components exist"
fi

# Phase 3: Server Integration - Check API clients
if [[ -f "src/Api/IntermediateServerClient.php" && -f "src/Api/LicenseManager.php" ]]; then
    CURRENT_PHASE=3
    COMPLETED_PHASES+=("phase3")
    COVERAGE_REQUIREMENT=75
    PHP_TEST_GROUP="phase0,phase1,phase2,phase3"
    JS_TEST_PATH="(foundation|core|knowledge|api)"
    echo "✅ Phase 3 (Server Integration) - API clients exist"
fi

# Phase 4: Widget Frontend - Check React components
if [[ -f "widget-src/src/App.js" && -f "widget-src/src/components/ChatWindow.js" && -f "src/Frontend/WidgetLoader.php" ]]; then
    CURRENT_PHASE=4
    COMPLETED_PHASES+=("phase4")
    COVERAGE_REQUIREMENT=80
    PHP_TEST_GROUP="phase0,phase1,phase2,phase3,phase4"
    JS_TEST_PATH="(foundation|core|knowledge|api|widget)"
    echo "✅ Phase 4 (Widget Frontend) - React components exist"
fi

# Phase 5: Chat Logic - Check conversation handling
if [[ -f "src/Chatbot/ConversationHandler.php" && -f "src/RestApi/Endpoints/ChatEndpoint.php" ]]; then
    CURRENT_PHASE=5
    COMPLETED_PHASES+=("phase5")
    COVERAGE_REQUIREMENT=85
    PHP_TEST_GROUP="phase0,phase1,phase2,phase3,phase4,phase5"
    JS_TEST_PATH="(foundation|core|knowledge|api|widget|chat)"
    echo "✅ Phase 5 (Chat Logic) - Conversation handling exists"
fi

# Phase 6: Advanced Features - Check coupon handling
if [[ -f "src/Chatbot/CouponHandler.php" ]]; then
    CURRENT_PHASE=6
    COMPLETED_PHASES+=("phase6")
    COVERAGE_REQUIREMENT=87
    PHP_TEST_GROUP="phase0,phase1,phase2,phase3,phase4,phase5,phase6"
    JS_TEST_PATH="(foundation|core|knowledge|api|widget|chat|features)"
    echo "✅ Phase 6 (Advanced Features) - Coupon handling exists"
fi

# Check for additional Phase 6 components
PHASE6_COMPLETE=true
if [[ ! -f "src/Chatbot/ProactiveTriggers.php" ]]; then
    PHASE6_COMPLETE=false
fi
if [[ ! -f "src/Chatbot/Handoff.php" ]]; then
    PHASE6_COMPLETE=false
fi

# Phase 7: Analytics - Check admin pages
if [[ $PHASE6_COMPLETE == true && -f "src/Admin/Pages/DashboardPage.php" ]]; then
    CURRENT_PHASE=7
    COMPLETED_PHASES+=("phase7")
    COVERAGE_REQUIREMENT=89
    PHP_TEST_GROUP="phase0,phase1,phase2,phase3,phase4,phase5,phase6,phase7"
    echo "✅ Phase 7 (Analytics) - Admin dashboard exists"
fi

# Phase 8: Optimization - Check performance components
if [[ $CURRENT_PHASE -eq 7 ]]; then
    # Look for optimization indicators
    if [[ -f "tests/performance/" || -f "tests/security/" ]]; then
        CURRENT_PHASE=8
        COMPLETED_PHASES+=("phase8")
        COVERAGE_REQUIREMENT=90
        PHP_TEST_GROUP="phase0,phase1,phase2,phase3,phase4,phase5,phase6,phase7,phase8"
        echo "✅ Phase 8 (Optimization) - Performance tests exist"
    fi
fi

# Output results in format that GitHub Actions can consume
echo ""
echo "📊 Project Phase Detection Results:"
echo "=================================="
echo "CURRENT_PHASE=$CURRENT_PHASE"
echo "COMPLETED_PHASES=${COMPLETED_PHASES[*]}"
echo "COVERAGE_REQUIREMENT=$COVERAGE_REQUIREMENT"
echo "PHP_TEST_GROUP=$PHP_TEST_GROUP"
echo "JS_TEST_PATH=$JS_TEST_PATH"

# Export as environment variables for GitHub Actions
if [[ -n "$GITHUB_ENV" ]]; then
    {
        echo "CURRENT_PHASE=$CURRENT_PHASE"
        echo "COMPLETED_PHASES=${COMPLETED_PHASES[*]}"
        echo "COVERAGE_REQUIREMENT=$COVERAGE_REQUIREMENT"
        echo "PHP_TEST_GROUP=$PHP_TEST_GROUP"
        echo "JS_TEST_PATH=$JS_TEST_PATH"
        echo "PHASE_DETECTION_COMPLETE=true"
    } >> "$GITHUB_ENV"
fi

# Output phase-specific test configuration
echo ""
echo "🧪 Phase-Specific Test Configuration:"
echo "===================================="

case $CURRENT_PHASE in
    0)
        echo "- Run foundation tests only"
        echo "- Skip integration tests (components don't exist yet)"
        echo "- Skip React tests (components don't exist yet)"
        echo "- Coverage target: 30%"
        ;;
    1)
        echo "- Run foundation + core infrastructure tests"
        echo "- Skip Knowledge Base tests (not implemented yet)"
        echo "- Skip React tests (components don't exist yet)"
        echo "- Coverage target: 50%"
        ;;
    2)
        echo "- Run foundation + core + Knowledge Base tests"
        echo "- Mock external APIs (not connected yet)"
        echo "- Skip React tests (components don't exist yet)"
        echo "- Coverage target: 70%"
        ;;
    3)
        echo "- Run foundation + core + KB + server integration tests"
        echo "- Use mocked API clients"
        echo "- Skip React tests (components don't exist yet)"
        echo "- Coverage target: 75%"
        ;;
    4)
        echo "- Run foundation + core + KB + server + React tests"
        echo "- Include widget loader tests"
        echo "- Skip chat integration (not connected yet)"
        echo "- Coverage target: 80%"
        ;;
    5)
        echo "- Run full test suite including chat logic"
        echo "- Include end-to-end chat flow tests"
        echo "- Coverage target: 85%"
        ;;
    *)
        echo "- Run all available tests for Phase $CURRENT_PHASE"
        echo "- Include advanced feature tests"
        echo "- Coverage target: ${COVERAGE_REQUIREMENT}%"
        ;;
esac

echo ""
echo "✅ Phase detection complete. Current phase: $CURRENT_PHASE"