#!/bin/bash

# Quality Assurance Summary Script for Woo AI Assistant
#
# This script provides an overview of all available quality checks
# and helps developers understand the verification process.
#
# @package WooAiAssistant
# @since 1.0.0

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
BOLD='\033[1m'
NC='\033[0m' # No Color

# Script directory and project root
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

echo -e "${BOLD}${BLUE}🛠️  Woo AI Assistant - Quality Assurance Summary${NC}"
echo "=================================================="
echo ""

echo -e "${BOLD}📋 Available Quality Checks:${NC}"
echo ""

echo -e "${GREEN}1. 🔍 Mandatory Verification${NC} (${YELLOW}./scripts/mandatory-verification.sh${NC})"
echo "   ├── File path verification"
echo "   ├── Coding standards compliance" 
echo "   ├── Unit tests execution (>90% coverage)"
echo "   ├── Security vulnerability checks"
echo "   ├── Performance validation"
echo "   ├── WordPress integration verification"
echo "   └── Documentation quality checks"
echo ""

echo -e "${GREEN}2. 📁 File Path Verification${NC} (${YELLOW}./scripts/verify-paths.sh${NC})"
echo "   ├── Directory structure validation"
echo "   ├── PSR-4 namespace verification"
echo "   ├── PHP include/require path checks"
echo "   └── Asset file existence verification"
echo ""

echo -e "${GREEN}3. 📝 Standards Verification${NC} (${YELLOW}php scripts/verify-standards.php${NC})"
echo "   ├── Class naming (PascalCase)"
echo "   ├── Method naming (camelCase)"
echo "   ├── Variable naming (camelCase)"
echo "   ├── Constant naming (UPPER_SNAKE_CASE)"
echo "   ├── Database naming (woo_ai_ prefix, snake_case)"
echo "   ├── WordPress hook naming (woo_ai_assistant_ prefix)"
echo "   └── DocBlock documentation verification"
echo ""

echo -e "${GREEN}4. 🔒 Git Hooks${NC} (${YELLOW}./scripts/setup-git-hooks.sh${NC})"
echo "   ├── Pre-commit: Full quality gate verification"
echo "   ├── Pre-push: Final verification before push"
echo "   └── Commit-msg: Conventional commit format validation"
echo ""

echo -e "${GREEN}5. 🏗️  Development Tools${NC}"
echo "   ├── ${YELLOW}./scripts/seed-data.sh${NC} - Create test data"
echo "   ├── ${YELLOW}./scripts/generate-docs.sh${NC} - Generate documentation"
echo "   └── ${YELLOW}composer run verify-all${NC} - Run all composer checks"
echo ""

echo -e "${BOLD}🚀 How to Use:${NC}"
echo ""

echo -e "${BLUE}For Task Completion:${NC}"
echo "  ${YELLOW}./scripts/mandatory-verification.sh${NC}"
echo "  ↳ Run this before marking ANY task as completed"
echo ""

echo -e "${BLUE}For Development:${NC}"
echo "  ${YELLOW}composer run quality${NC}        ← Run all quality checks"
echo "  ${YELLOW}composer run test${NC}           ← Run unit tests"
echo "  ${YELLOW}composer run phpcs${NC}          ← Check coding standards"
echo "  ${YELLOW}composer run docs${NC}           ← Generate documentation"
echo ""

echo -e "${BLUE}For Debugging Issues:${NC}"
echo "  ${YELLOW}./scripts/verify-paths.sh${NC}      ← Check file paths"
echo "  ${YELLOW}php scripts/verify-standards.php${NC} ← Check naming conventions"
echo "  ${YELLOW}composer run phpstan${NC}           ← Static analysis"
echo ""

echo -e "${BOLD}⚡ Quick Start:${NC}"
echo ""
echo "1. ${GREEN}Install Git hooks:${NC} ${YELLOW}./scripts/setup-git-hooks.sh${NC}"
echo "2. ${GREEN}Create test data:${NC} ${YELLOW}./scripts/seed-data.sh${NC}"
echo "3. ${GREEN}Run verification:${NC} ${YELLOW}./scripts/mandatory-verification.sh${NC}"
echo "4. ${GREEN}Make changes and commit:${NC} ${YELLOW}git commit -m \"feat: your changes\"${NC}"
echo "   ↳ Git hooks will automatically run quality checks"
echo ""

echo -e "${BOLD}📊 Quality Gate Requirements:${NC}"
echo ""
echo "✅ All file paths must exist and be accessible"
echo "✅ PHP naming conventions must be followed exactly"
echo "✅ Unit tests must pass with >90% coverage"
echo "✅ No security vulnerabilities allowed"
echo "✅ WordPress integration must be compliant"
echo "✅ Documentation must be comprehensive"
echo "✅ Commit messages must follow conventional format"
echo ""

echo -e "${BOLD}🔧 Configuration Files:${NC}"
echo ""
echo "📄 ${YELLOW}composer.json${NC}     ← All quality check commands"
echo "📄 ${YELLOW}phpdoc.xml${NC}       ← API documentation configuration"  
echo "📄 ${YELLOW}phpunit.xml${NC}      ← Unit testing configuration"
echo "📄 ${YELLOW}.git/hooks/${NC}      ← Git hooks for automatic verification"
echo ""

# Check current status
echo -e "${BOLD}🔍 Current Status:${NC}"
echo ""

# Check if hooks are installed
if [[ -x "$PROJECT_ROOT/.git/hooks/pre-commit" ]]; then
    echo -e "${GREEN}✅ Git hooks installed${NC}"
else
    echo -e "${RED}❌ Git hooks not installed${NC} - Run: ${YELLOW}./scripts/setup-git-hooks.sh${NC}"
fi

# Check if composer dependencies exist
if [[ -f "$PROJECT_ROOT/vendor/autoload.php" ]]; then
    echo -e "${GREEN}✅ Composer dependencies installed${NC}"
else
    echo -e "${RED}❌ Composer dependencies missing${NC} - Run: ${YELLOW}composer install${NC}"
fi

# Check if npm dependencies exist
if [[ -d "$PROJECT_ROOT/node_modules" ]]; then
    echo -e "${GREEN}✅ NPM dependencies installed${NC}"
else
    echo -e "${YELLOW}⚠️  NPM dependencies missing${NC} - Run: ${YELLOW}npm install${NC}"
fi

echo ""

echo -e "${BOLD}💡 Pro Tips:${NC}"
echo ""
echo "• Use ${YELLOW}composer run verify-all${NC} to run comprehensive checks"
echo "• Git hooks will prevent commits if quality gates fail"
echo "• Use ${YELLOW}git commit --no-verify${NC} to skip hooks (not recommended)"
echo "• All scripts support ${YELLOW}--help${NC} flag for detailed usage"
echo "• Run ${YELLOW}./scripts/mandatory-verification.sh${NC} before completing tasks"
echo ""

echo -e "${GREEN}🎉 Quality assurance system ready!${NC}"
echo ""