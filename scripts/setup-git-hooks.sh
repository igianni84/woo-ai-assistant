#!/bin/bash

# Git Hooks Setup Script for Woo AI Assistant
#
# This script sets up git hooks for local development to ensure code quality.
# It can install, uninstall, or check the status of git hooks.
#
# @package WooAiAssistant
# @subpackage Scripts
# @since 1.0.0
# @author Claude Code Assistant

set -euo pipefail

readonly SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
readonly PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
readonly HOOKS_DIR="$PROJECT_ROOT/.githooks"
readonly GIT_HOOKS_DIR="$PROJECT_ROOT/.git/hooks"

# Colors for output
readonly RED='\033[0;31m'
readonly GREEN='\033[0;32m'
readonly YELLOW='\033[1;33m'
readonly BLUE='\033[0;34m'
readonly NC='\033[0m' # No Color

log() {
    echo -e "${BLUE}[git-hooks]${NC} $*"
}

success() {
    echo -e "${GREEN}✅ $*${NC}"
}

warning() {
    echo -e "${YELLOW}⚠️  $*${NC}"
}

error() {
    echo -e "${RED}❌ $*${NC}" >&2
}

# =============================================================================
# FUNCTIONS
# =============================================================================

show_help() {
    cat << EOF
🔗 Git Hooks Setup for Woo AI Assistant

USAGE:
    $0 [COMMAND]

COMMANDS:
    install     Install git hooks (default)
    uninstall   Remove git hooks
    status      Check git hooks status
    reinstall   Uninstall and reinstall hooks
    help        Show this help message

EXAMPLES:
    $0              # Install hooks
    $0 install      # Install hooks
    $0 uninstall    # Remove hooks
    $0 status       # Check status

HOOKS:
    pre-commit      Runs code quality checks before commit
    commit-msg      Validates commit message format

EOF
}

check_prerequisites() {
    # Check if we're in a git repository
    if [[ ! -d "$PROJECT_ROOT/.git" ]]; then
        error "Not in a git repository"
        exit 1
    fi

    # Check if hooks directory exists
    if [[ ! -d "$HOOKS_DIR" ]]; then
        error "Hooks directory not found: $HOOKS_DIR"
        exit 1
    fi

    # Create git hooks directory if it doesn't exist
    if [[ ! -d "$GIT_HOOKS_DIR" ]]; then
        mkdir -p "$GIT_HOOKS_DIR"
        log "Created git hooks directory: $GIT_HOOKS_DIR"
    fi
}

install_hooks() {
    log "Installing git hooks..."

    check_prerequisites

    local hooks=("pre-commit" "commit-msg")
    local installed_count=0

    for hook in "${hooks[@]}"; do
        local source_hook="$HOOKS_DIR/$hook"
        local target_hook="$GIT_HOOKS_DIR/$hook"

        if [[ -f "$source_hook" ]]; then
            # Check if hook already exists
            if [[ -f "$target_hook" ]]; then
                # Check if it's already our hook
                if cmp -s "$source_hook" "$target_hook"; then
                    log "$hook hook is already installed and up-to-date"
                else
                    # Backup existing hook
                    local backup_file="$target_hook.backup.$(date +%s)"
                    cp "$target_hook" "$backup_file"
                    warning "Backed up existing $hook hook to: $(basename "$backup_file")"
                    
                    # Install our hook
                    cp "$source_hook" "$target_hook"
                    chmod +x "$target_hook"
                    success "Installed $hook hook (with backup)"
                    installed_count=$((installed_count + 1))
                fi
            else
                # Install new hook
                cp "$source_hook" "$target_hook"
                chmod +x "$target_hook"
                success "Installed $hook hook"
                installed_count=$((installed_count + 1))
            fi
        else
            warning "$hook hook not found in $HOOKS_DIR"
        fi
    done

    if [[ $installed_count -gt 0 ]]; then
        echo ""
        success "Git hooks installation completed!"
        echo ""
        echo "🔍 What the hooks do:"
        echo "  pre-commit  - Runs PHP/JS linting, syntax checks, and quick tests"
        echo "  commit-msg  - Validates commit message format (conventional commits)"
        echo ""
        echo "💡 To skip hooks temporarily:"
        echo "  git commit --no-verify -m \"your message\""
        echo ""
        echo "🔧 To uninstall hooks:"
        echo "  $0 uninstall"
    else
        log "All hooks are already installed and up-to-date"
    fi
}

uninstall_hooks() {
    log "Uninstalling git hooks..."

    local hooks=("pre-commit" "commit-msg")
    local removed_count=0

    for hook in "${hooks[@]}"; do
        local target_hook="$GIT_HOOKS_DIR/$hook"

        if [[ -f "$target_hook" ]]; then
            # Check if this is our hook by looking for our signature
            if grep -q "Woo AI Assistant" "$target_hook" 2>/dev/null; then
                rm "$target_hook"
                success "Removed $hook hook"
                removed_count=$((removed_count + 1))
            else
                warning "$hook hook exists but doesn't appear to be ours - leaving it alone"
            fi
        else
            log "$hook hook not installed"
        fi
    done

    if [[ $removed_count -gt 0 ]]; then
        success "Git hooks uninstallation completed!"
    else
        log "No hooks to uninstall"
    fi
}

check_status() {
    log "Checking git hooks status..."

    local hooks=("pre-commit" "commit-msg")
    local installed_count=0
    local total_count=${#hooks[@]}

    echo ""
    echo "Git Hooks Status:"
    echo "=================="

    for hook in "${hooks[@]}"; do
        local target_hook="$GIT_HOOKS_DIR/$hook"
        local source_hook="$HOOKS_DIR/$hook"

        printf "%-12s: " "$hook"

        if [[ -f "$target_hook" ]]; then
            if [[ -f "$source_hook" ]] && cmp -s "$source_hook" "$target_hook"; then
                echo -e "${GREEN}✅ Installed (up-to-date)${NC}"
                installed_count=$((installed_count + 1))
            elif grep -q "Woo AI Assistant" "$target_hook" 2>/dev/null; then
                echo -e "${YELLOW}⚠️  Installed (outdated)${NC}"
            else
                echo -e "${YELLOW}⚠️  Different hook installed${NC}"
            fi
        else
            echo -e "${RED}❌ Not installed${NC}"
        fi
    done

    echo ""
    echo "Summary: $installed_count/$total_count hooks properly installed"

    if [[ $installed_count -eq $total_count ]]; then
        success "All hooks are properly installed!"
    elif [[ $installed_count -gt 0 ]]; then
        warning "Some hooks need attention"
        echo "Run '$0 install' to install missing hooks"
    else
        warning "No hooks are installed"
        echo "Run '$0 install' to install all hooks"
    fi

    # Show additional info
    echo ""
    echo "Available commands:"
    echo "  $0 install    - Install or update hooks"
    echo "  $0 uninstall  - Remove all hooks"
    echo "  $0 reinstall  - Remove and reinstall hooks"
}

reinstall_hooks() {
    log "Reinstalling git hooks..."
    uninstall_hooks
    echo ""
    install_hooks
}

# =============================================================================
# MAIN EXECUTION
# =============================================================================

main() {
    case "${1:-install}" in
        install)
            install_hooks
            ;;
        uninstall)
            uninstall_hooks
            ;;
        status)
            check_status
            ;;
        reinstall)
            reinstall_hooks
            ;;
        help|--help|-h)
            show_help
            ;;
        *)
            error "Unknown command: $1"
            echo ""
            show_help
            exit 1
            ;;
    esac
}

# Run main function
main "$@"