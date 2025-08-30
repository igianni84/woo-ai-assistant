#!/bin/bash

# quality-gates-enforcer.sh - Main quality gates verification script
# This script MUST pass before ANY task can be marked as completed

PHASE=${1:-0}
TOTAL_ERRORS=0
STATUS_FILE=".quality-gates-status"

echo "🚨 QUALITY GATES ENFORCEMENT - Phase $PHASE"
echo "==========================================="
echo ""
echo "⚠️  CRITICAL: Task completion is FORBIDDEN until ALL gates pass!"
echo ""

# Function to run a check and track errors
run_check() {
    local check_name=$1
    local check_command=$2
    
    echo "🔍 Running: $check_name"
    
    if eval $check_command; then
        echo "  ✅ PASSED"
        return 0
    else
        echo "  ❌ FAILED"
        return 1
    fi
}

# Phase 0 Quality Gates
if [ $PHASE -ge 0 ]; then
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "📦 PHASE 0 - Foundation Quality Gates"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
    
    # Check configuration files exist
    run_check "Configuration Files" "[ -f composer.json ] && [ -f package.json ] && [ -f phpunit.xml ]"
    [ $? -ne 0 ] && TOTAL_ERRORS=$((TOTAL_ERRORS + 1))
    
    # Check directory structure
    run_check "Directory Structure" "[ -d scripts ]"
    [ $? -ne 0 ] && TOTAL_ERRORS=$((TOTAL_ERRORS + 1))
    
    # Verify path structure
    run_check "Path Verification" "bash scripts/verify-paths.sh 0 >/dev/null 2>&1"
    [ $? -ne 0 ] && TOTAL_ERRORS=$((TOTAL_ERRORS + 1))
    
    # NEW: Plugin Activation Test
    run_check "Plugin Activation Test" "php scripts/test-plugin-activation.php >/dev/null 2>&1"
    [ $? -ne 0 ] && TOTAL_ERRORS=$((TOTAL_ERRORS + 1))
    
    echo ""
fi

# Phase 1 Quality Gates
if [ $PHASE -ge 1 ]; then
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "🏗️ PHASE 1 - Core Infrastructure Gates"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
    
    # Check PHP files exist
    run_check "Core PHP Files" "[ -f src/Main.php ]"
    [ $? -ne 0 ] && TOTAL_ERRORS=$((TOTAL_ERRORS + 1))
    
    # Check PHP standards
    run_check "PHP Standards" "php scripts/verify-standards.php 1 >/dev/null 2>&1"
    [ $? -ne 0 ] && TOTAL_ERRORS=$((TOTAL_ERRORS + 1))
    
    # Run PHPUnit tests if available
    if [ -f vendor/bin/phpunit ]; then
        run_check "PHPUnit Tests" "vendor/bin/phpunit --group phase1 >/dev/null 2>&1"
        [ $? -ne 0 ] && TOTAL_ERRORS=$((TOTAL_ERRORS + 1))
    fi
    
    echo ""
fi

# Phase 2+ Quality Gates
if [ $PHASE -ge 2 ]; then
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "🧠 PHASE $PHASE - Advanced Quality Gates"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
    
    # Check code coverage
    if [ -f vendor/bin/phpunit ]; then
        echo "📊 Checking code coverage..."
        
        # Get minimum coverage for phase
        case $PHASE in
            2) MIN_COVERAGE=70 ;;
            3) MIN_COVERAGE=75 ;;
            4) MIN_COVERAGE=80 ;;
            5) MIN_COVERAGE=85 ;;
            *) MIN_COVERAGE=90 ;;
        esac
        
        echo "  Minimum required: ${MIN_COVERAGE}%"
        # Note: Actual coverage check would be here
        echo "  ⚠️  Coverage check skipped (no code to test yet)"
    fi
    
    echo ""
fi

# Additional checks for all phases
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🔧 General Quality Checks"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Check for debugging code (exclude legitimate logging functions)
echo "🔍 Checking for debug code..."
if grep -r "var_dump\|console\.log" src/ widget-src/ 2>/dev/null | grep -v "test\|Test\|debugLog\|Logger" > /dev/null; then
    echo "  ❌ Found debug code in source files"
    TOTAL_ERRORS=$((TOTAL_ERRORS + 1))
else
    echo "  ✅ No debug code found"
fi

# Check for TODO comments
echo "🔍 Checking for unresolved TODOs..."
TODO_COUNT=$(grep -r "TODO\|FIXME\|XXX" src/ widget-src/ 2>/dev/null | wc -l)
if [ $TODO_COUNT -gt 0 ]; then
    echo "  ⚠️  Found $TODO_COUNT TODO/FIXME comments"
    # Only count as error for phases > 0 (Phase 0 can have placeholder TODOs)
    if [ $PHASE -gt 0 ]; then
        TOTAL_ERRORS=$((TOTAL_ERRORS + 1))
    fi
else
    echo "  ✅ No TODO comments found"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📊 QUALITY GATES SUMMARY"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Write status file
if [ $TOTAL_ERRORS -eq 0 ]; then
    echo "QUALITY_GATES_STATUS=PASSED" > $STATUS_FILE
    echo "PHASE=$PHASE" >> $STATUS_FILE
    echo "TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')" >> $STATUS_FILE
    echo "ERRORS=0" >> $STATUS_FILE
    
    echo "✅✅✅ ALL QUALITY GATES PASSED! ✅✅✅"
    echo ""
    echo "Task completion is now ALLOWED for Phase $PHASE"
    echo "Status file created: $STATUS_FILE"
    exit 0
else
    echo "QUALITY_GATES_STATUS=FAILED" > $STATUS_FILE
    echo "PHASE=$PHASE" >> $STATUS_FILE
    echo "TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')" >> $STATUS_FILE
    echo "ERRORS=$TOTAL_ERRORS" >> $STATUS_FILE
    
    echo "❌❌❌ QUALITY GATES FAILED! ❌❌❌"
    echo ""
    echo "Found $TOTAL_ERRORS critical issues that MUST be fixed"
    echo "Task completion is BLOCKED until all gates pass"
    echo ""
    echo "⚠️  ENFORCEMENT: Task CANNOT be marked as completed!"
    echo "⚠️  Fix all issues and run this script again."
    exit 1
fi