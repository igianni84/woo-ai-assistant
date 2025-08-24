#!/bin/bash
#
# Quality Gates Enforcer Script
#
# This script physically blocks task completion until all quality gates pass.
# Creates a .quality-gates-status file that must contain "PASSED" for completion.
#
# @package WooAiAssistant
# @subpackage Scripts
# @since 1.0.0
# @author Claude Code Assistant

set -euo pipefail

# Colors for output
readonly RED='\033[0;31m'
readonly GREEN='\033[0;32m'
readonly YELLOW='\033[1;33m'
readonly BLUE='\033[0;34m'
readonly BOLD='\033[1m'
readonly NC='\033[0m' # No Color

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
STATUS_FILE="$PROJECT_ROOT/.quality-gates-status"

# Functions
log_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

log_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

log_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

log_error() {
    echo -e "${RED}❌ $1${NC}"
}

log_header() {
    echo -e "\n${BOLD}${BLUE}$1${NC}"
    echo "$(printf '%*s' "${#1}" '' | tr ' ' '=')"
}

# Set status to BLOCKED
block_completion() {
    local reason="$1"
    echo "QUALITY_GATES_STATUS=FAILED" > "$STATUS_FILE"
    echo "REASON=$reason" >> "$STATUS_FILE"
    echo "BLOCKED_AT=$(date '+%Y-%m-%d %H:%M:%S')" >> "$STATUS_FILE"
    
    log_error "TASK COMPLETION BLOCKED: $reason"
    log_error "Fix all quality gate failures before attempting completion"
    log_error "Status file: $STATUS_FILE"
    return 1
}

# Set status to PASSED
allow_completion() {
    echo "QUALITY_GATES_STATUS=PASSED" > "$STATUS_FILE"
    echo "VERIFIED_AT=$(date '+%Y-%m-%d %H:%M:%S')" >> "$STATUS_FILE"
    echo "ALL_GATES_PASSED=true" >> "$STATUS_FILE"
    
    log_success "ALL QUALITY GATES PASSED - TASK COMPLETION ALLOWED"
    log_success "Status file: $STATUS_FILE"
    return 0
}

# Check if completion is currently allowed
check_completion_status() {
    if [[ ! -f "$STATUS_FILE" ]]; then
        log_warning "Quality gates status file not found - completion BLOCKED"
        return 1
    fi
    
    if grep -q "QUALITY_GATES_STATUS=PASSED" "$STATUS_FILE"; then
        log_success "Task completion is currently ALLOWED"
        return 0
    else
        log_error "Task completion is currently BLOCKED"
        if [[ -f "$STATUS_FILE" ]]; then
            echo "Current status file contents:"
            cat "$STATUS_FILE"
        fi
        return 1
    fi
}

# Run all quality gates
run_quality_gates() {
    log_header "🔍 RUNNING QUALITY GATES ENFORCEMENT"
    
    local gates_passed=0
    local total_gates=5
    
    cd "$PROJECT_ROOT"
    
    # Gate 1: Path Verification
    log_info "Running Gate 1/5: Path Verification"
    if bash scripts/verify-paths.sh; then
        log_success "Gate 1/5: Path Verification PASSED"
        ((gates_passed++))
    else
        block_completion "Path verification failed"
        return 1
    fi
    
    # Gate 2: Standards Verification
    log_info "Running Gate 2/5: Standards Verification"
    if php scripts/verify-standards.php; then
        log_success "Gate 2/5: Standards Verification PASSED"
        ((gates_passed++))
    else
        block_completion "Standards verification failed"
        return 1
    fi
    
    # Gate 3: Code Style (PHPCS)
    log_info "Running Gate 3/5: Code Style Verification"
    if composer run phpcs --quiet; then
        log_success "Gate 3/5: Code Style PASSED"
        ((gates_passed++))
    else
        block_completion "Code style (PHPCS) verification failed"
        return 1
    fi
    
    # Gate 4: Static Analysis (PHPStan)
    log_info "Running Gate 4/5: Static Analysis"
    if composer run phpstan --quiet; then
        log_success "Gate 4/5: Static Analysis PASSED"
        ((gates_passed++))
    else
        block_completion "Static analysis (PHPStan) failed"
        return 1
    fi
    
    # Gate 5: Unit Tests
    log_info "Running Gate 5/5: Unit Tests"
    if composer run test --quiet; then
        log_success "Gate 5/5: Unit Tests PASSED"
        ((gates_passed++))
    else
        block_completion "Unit tests failed"
        return 1
    fi
    
    # All gates passed
    if [[ $gates_passed -eq $total_gates ]]; then
        allow_completion
        
        log_header "🎉 ALL QUALITY GATES PASSED"
        echo -e "${GREEN}${BOLD}✅ Path Verification: PASSED${NC}"
        echo -e "${GREEN}${BOLD}✅ Standards Verification: PASSED${NC}"
        echo -e "${GREEN}${BOLD}✅ Code Style (PHPCS): PASSED${NC}"
        echo -e "${GREEN}${BOLD}✅ Static Analysis (PHPStan): PASSED${NC}"
        echo -e "${GREEN}${BOLD}✅ Unit Tests: PASSED${NC}"
        echo ""
        echo -e "${GREEN}${BOLD}🚀 TASK COMPLETION IS NOW ALLOWED${NC}"
        
        return 0
    else
        block_completion "Only $gates_passed/$total_gates quality gates passed"
        return 1
    fi
}

# Main execution
main() {
    local command="${1:-run}"
    
    case "$command" in
        "run"|"enforce")
            run_quality_gates
            ;;
        "check"|"status")
            check_completion_status
            ;;
        "block")
            local reason="${2:-Manual block}"
            block_completion "$reason"
            ;;
        "allow")
            allow_completion
            ;;
        "reset")
            if [[ -f "$STATUS_FILE" ]]; then
                rm "$STATUS_FILE"
                log_info "Quality gates status reset"
            fi
            ;;
        *)
            echo "Usage: $0 [run|check|block|allow|reset]"
            echo ""
            echo "Commands:"
            echo "  run     - Run all quality gates and set status (default)"
            echo "  check   - Check current completion status"
            echo "  block   - Manually block task completion"
            echo "  allow   - Manually allow task completion (USE WITH CAUTION)"
            echo "  reset   - Reset status file"
            exit 1
            ;;
    esac
}

# Execute main function if script is run directly
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi