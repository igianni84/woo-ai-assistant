#!/bin/bash
#
# Git Hooks Setup Script
#
# This script installs mandatory Git hooks that enforce quality gates
# before commits and pushes are allowed.
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
GIT_HOOKS_DIR="$PROJECT_ROOT/.git/hooks"

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

# Check if we're in a git repository
check_git_repo() {
    if [[ ! -d "$PROJECT_ROOT/.git" ]]; then
        log_error "Not a git repository. Please run this script from within a git repository."
        exit 1
    fi
    log_success "Git repository detected"
}

# Install pre-commit hook
install_pre_commit_hook() {
    log_info "Installing pre-commit hook..."
    
    cat > "$GIT_HOOKS_DIR/pre-commit" << 'EOF'
#!/bin/bash
#
# Pre-commit hook for Woo AI Assistant
# Runs mandatory quality gates before allowing commits
#

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
BOLD='\033[1m'
NC='\033[0m'

echo -e "${BLUE}${BOLD}🔒 Pre-commit Quality Gates${NC}"
echo "Running mandatory verification before commit..."
echo ""

# Get the project root (where the .git directory is)
PROJECT_ROOT="$(git rev-parse --show-toplevel)"
SCRIPTS_DIR="$PROJECT_ROOT/scripts"

# Check if mandatory verification script exists
if [[ ! -f "$SCRIPTS_DIR/mandatory-verification.sh" ]]; then
    echo -e "${RED}❌ Mandatory verification script not found!${NC}"
    echo "Expected: $SCRIPTS_DIR/mandatory-verification.sh"
    exit 1
fi

# Make sure the script is executable
chmod +x "$SCRIPTS_DIR/mandatory-verification.sh"

# Run the mandatory verification
echo -e "${BLUE}🔍 Running comprehensive quality checks...${NC}"
if "$SCRIPTS_DIR/mandatory-verification.sh"; then
    echo ""
    echo -e "${GREEN}${BOLD}✅ All quality gates passed!${NC}"
    echo -e "${GREEN}🚀 Commit approved${NC}"
    exit 0
else
    echo ""
    echo -e "${RED}${BOLD}❌ Quality gates failed!${NC}"
    echo -e "${RED}🚫 Commit blocked${NC}"
    echo ""
    echo -e "${YELLOW}Next steps:${NC}"
    echo "1. Fix all issues reported above"
    echo "2. Run: ./scripts/mandatory-verification.sh"
    echo "3. Try your commit again"
    echo ""
    exit 1
fi
EOF

    chmod +x "$GIT_HOOKS_DIR/pre-commit"
    log_success "Pre-commit hook installed"
}

# Install pre-push hook  
install_pre_push_hook() {
    log_info "Installing pre-push hook..."
    
    cat > "$GIT_HOOKS_DIR/pre-push" << 'EOF'
#!/bin/bash
#
# Pre-push hook for Woo AI Assistant
# Runs final verification before pushing to remote
#

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
BOLD='\033[1m'
NC='\033[0m'

echo -e "${BLUE}${BOLD}🚀 Pre-push Quality Gates${NC}"
echo "Running final verification before push..."
echo ""

# Get the project root
PROJECT_ROOT="$(git rev-parse --show-toplevel)"
SCRIPTS_DIR="$PROJECT_ROOT/scripts"

# Run quick verification focusing on critical issues
echo -e "${BLUE}🔍 Running final verification...${NC}"
if "$SCRIPTS_DIR/mandatory-verification.sh" --quick; then
    echo ""
    echo -e "${GREEN}${BOLD}✅ Final verification passed!${NC}"
    echo -e "${GREEN}🚀 Push approved${NC}"
    exit 0
else
    echo ""
    echo -e "${RED}${BOLD}❌ Final verification failed!${NC}"
    echo -e "${RED}🚫 Push blocked${NC}"
    echo ""
    echo "Please fix issues before pushing to remote repository."
    exit 1
fi
EOF

    chmod +x "$GIT_HOOKS_DIR/pre-push"
    log_success "Pre-push hook installed"
}

# Install commit-msg hook for conventional commits
install_commit_msg_hook() {
    log_info "Installing commit-msg hook..."
    
    cat > "$GIT_HOOKS_DIR/commit-msg" << 'EOF'
#!/bin/bash
#
# Commit message hook for Woo AI Assistant
# Validates commit message format follows conventional commits
#

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

commit_regex='^(feat|fix|docs|style|refactor|test|chore|perf|ci|build)(\(.+\))?: .{1,50}'

error_msg="
${RED}❌ Invalid commit message format${NC}

Commit message should follow conventional commits format:
${GREEN}type(scope): description${NC}

Types: feat, fix, docs, style, refactor, test, chore, perf, ci, build
Scope: optional, e.g., (kb), (chat), (admin)
Description: concise description under 50 chars

Examples:
✅ ${GREEN}feat(kb): implement product content scanning${NC}
✅ ${GREEN}fix(chat): resolve conversation handler memory leak${NC}
✅ ${GREEN}docs: update API documentation${NC}
✅ ${GREEN}test(kb): add unit tests for scanner class${NC}

Your commit message:
${YELLOW}$(cat $1)${NC}
"

if ! grep -qE "$commit_regex" "$1"; then
    echo -e "$error_msg" >&2
    exit 1
fi

echo -e "${GREEN}✅ Commit message format valid${NC}"
EOF

    chmod +x "$GIT_HOOKS_DIR/commit-msg"
    log_success "Commit-msg hook installed"
}

# Create backup of existing hooks
backup_existing_hooks() {
    log_info "Backing up existing hooks..."
    
    local backup_dir="$GIT_HOOKS_DIR/backup-$(date +%Y%m%d-%H%M%S)"
    
    if ls "$GIT_HOOKS_DIR"/* >/dev/null 2>&1; then
        mkdir -p "$backup_dir"
        cp "$GIT_HOOKS_DIR"/* "$backup_dir/" 2>/dev/null || true
        log_success "Existing hooks backed up to: $backup_dir"
    else
        log_info "No existing hooks to backup"
    fi
}

# Test hooks installation
test_hooks() {
    log_info "Testing hook installation..."
    
    # Test pre-commit hook exists and is executable
    if [[ -x "$GIT_HOOKS_DIR/pre-commit" ]]; then
        log_success "Pre-commit hook is executable"
    else
        log_error "Pre-commit hook installation failed"
        return 1
    fi
    
    # Test pre-push hook exists and is executable
    if [[ -x "$GIT_HOOKS_DIR/pre-push" ]]; then
        log_success "Pre-push hook is executable"
    else
        log_error "Pre-push hook installation failed"
        return 1
    fi
    
    # Test commit-msg hook exists and is executable
    if [[ -x "$GIT_HOOKS_DIR/commit-msg" ]]; then
        log_success "Commit-msg hook is executable"
    else
        log_error "Commit-msg hook installation failed"
        return 1
    fi
}

# Display usage instructions
display_instructions() {
    log_header "📚 Git Hooks Installation Complete"
    
    echo ""
    echo -e "${GREEN}✅ Successfully installed quality gate enforcement!${NC}"
    echo ""
    echo -e "${BOLD}What happens now:${NC}"
    echo -e "${BLUE}📝 Commits${NC} - Pre-commit hook runs full verification"
    echo -e "${BLUE}🚀 Pushes${NC} - Pre-push hook runs final checks"  
    echo -e "${BLUE}💬 Messages${NC} - Commit messages must follow conventional format"
    echo ""
    echo -e "${BOLD}How to use:${NC}"
    echo -e "${GREEN}git commit -m \"feat(kb): add product scanner\"${NC} - Will run all quality gates"
    echo -e "${GREEN}git push origin feature-branch${NC} - Will run final verification"
    echo ""
    echo -e "${BOLD}Manual verification:${NC}"
    echo -e "${YELLOW}./scripts/mandatory-verification.sh${NC} - Run quality gates manually"
    echo -e "${YELLOW}composer run verify-all${NC} - Run through composer (after setup)"
    echo ""
    echo -e "${BOLD}Disable temporarily (if needed):${NC}"
    echo -e "${YELLOW}git commit --no-verify${NC} - Skip hooks (not recommended!)"
    echo ""
}

# Main function
main() {
    log_header "🔧 Git Hooks Setup for Woo AI Assistant"
    echo "This will install mandatory quality gate enforcement"
    echo ""
    
    check_git_repo
    backup_existing_hooks
    install_pre_commit_hook
    install_pre_push_hook
    install_commit_msg_hook
    test_hooks
    display_instructions
    
    echo -e "${GREEN}${BOLD}🎉 Git hooks setup completed successfully!${NC}"
    echo ""
}

# Execute main function
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi