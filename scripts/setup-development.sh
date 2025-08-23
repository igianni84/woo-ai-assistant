#!/bin/bash
#
# Development Environment Setup Script
#
# Comprehensive setup script that configures the entire development environment
# for the Woo AI Assistant plugin with all quality gates and tools.
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
readonly PURPLE='\033[0;35m'
readonly CYAN='\033[0;36m'
readonly BOLD='\033[1m'
readonly NC='\033[0m' # No Color

# Script directory and project root
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# Setup flags
SKIP_DEPENDENCIES=false
SKIP_GIT_HOOKS=false  
SKIP_COMPOSER=false
SKIP_NPM=false
FORCE_REINSTALL=false

# Logging functions
log_header() {
    echo ""
    echo -e "${BOLD}${CYAN}$1${NC}"
    echo "$(printf '%*s' "${#1}" '' | tr ' ' '=')"
}

log_step() {
    echo -e "${BLUE}🔧 $1${NC}"
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

log_info() {
    echo -e "${PURPLE}ℹ️  $1${NC}"
}

# Help function
show_help() {
    echo "Woo AI Assistant Development Setup Script"
    echo ""
    echo "Usage: $0 [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  -h, --help              Show this help message"
    echo "  --skip-dependencies     Skip dependency installation"
    echo "  --skip-git-hooks        Skip Git hooks setup"
    echo "  --skip-composer         Skip Composer setup"
    echo "  --skip-npm              Skip NPM setup"
    echo "  --force                 Force reinstall all dependencies"
    echo ""
    echo "Examples:"
    echo "  $0                      Full setup (recommended)"
    echo "  $0 --skip-dependencies  Setup without installing dependencies"
    echo "  $0 --force              Force clean reinstall of everything"
    echo ""
}

# Parse command line arguments
parse_arguments() {
    while [[ $# -gt 0 ]]; do
        case $1 in
            -h|--help)
                show_help
                exit 0
                ;;
            --skip-dependencies)
                SKIP_DEPENDENCIES=true
                shift
                ;;
            --skip-git-hooks)
                SKIP_GIT_HOOKS=true
                shift
                ;;
            --skip-composer)
                SKIP_COMPOSER=true
                shift
                ;;
            --skip-npm)
                SKIP_NPM=true
                shift
                ;;
            --force)
                FORCE_REINSTALL=true
                shift
                ;;
            *)
                log_error "Unknown option: $1"
                show_help
                exit 1
                ;;
        esac
    done
}

# Check prerequisites
check_prerequisites() {
    log_header "🔍 Checking Prerequisites"
    
    # Check if we're in the right directory
    if [[ ! -f "$PROJECT_ROOT/woo-ai-assistant.php" ]]; then
        log_error "Not in Woo AI Assistant plugin directory"
        exit 1
    fi
    
    log_success "Plugin directory confirmed"
    
    # Check PHP version
    if command -v php >/dev/null 2>&1; then
        PHP_VERSION=$(php -r "echo PHP_VERSION;")
        log_info "PHP version: $PHP_VERSION"
        
        if php -r "exit(version_compare(PHP_VERSION, '8.2.0', '<') ? 1 : 0);"; then
            log_error "PHP 8.2.0 or higher required. Current: $PHP_VERSION"
            exit 1
        fi
        log_success "PHP version meets requirements"
    else
        log_error "PHP not found in PATH"
        exit 1
    fi
    
    # Check Composer
    if command -v composer >/dev/null 2>&1; then
        COMPOSER_VERSION=$(composer --version --no-ansi | head -1)
        log_info "Found: $COMPOSER_VERSION"
        log_success "Composer available"
    else
        log_error "Composer not found. Please install Composer first."
        exit 1
    fi
    
    # Check Node.js and NPM
    if command -v node >/dev/null 2>&1; then
        NODE_VERSION=$(node --version)
        log_info "Node.js version: $NODE_VERSION"
        
        if ! node -e "process.exit(process.version.match(/^v(\d+)/)[1] >= 18 ? 0 : 1)"; then
            log_error "Node.js 18.0.0 or higher required. Current: $NODE_VERSION"
            exit 1
        fi
        log_success "Node.js version meets requirements"
    else
        log_error "Node.js not found. Please install Node.js 18+ first."
        exit 1
    fi
    
    if command -v npm >/dev/null 2>&1; then
        NPM_VERSION=$(npm --version)
        log_info "NPM version: $NPM_VERSION"
        log_success "NPM available"
    else
        log_error "NPM not found"
        exit 1
    fi
    
    # Check Git
    if command -v git >/dev/null 2>&1; then
        if [[ -d "$PROJECT_ROOT/.git" ]]; then
            log_success "Git repository detected"
        else
            log_warning "Not a Git repository - some features will be skipped"
        fi
    else
        log_warning "Git not found - version control features will be skipped"
    fi
}

# Setup Composer dependencies
setup_composer() {
    if [[ "$SKIP_COMPOSER" == true ]]; then
        log_info "Skipping Composer setup"
        return
    fi
    
    log_header "📦 Setting up PHP Dependencies"
    
    cd "$PROJECT_ROOT"
    
    if [[ "$FORCE_REINSTALL" == true && -d "vendor" ]]; then
        log_step "Removing existing vendor directory"
        rm -rf vendor
        log_success "Vendor directory removed"
    fi
    
    if [[ ! "$SKIP_DEPENDENCIES" == true ]]; then
        log_step "Installing Composer dependencies"
        if composer install --no-interaction --optimize-autoloader --dev; then
            log_success "Composer dependencies installed"
        else
            log_error "Failed to install Composer dependencies"
            exit 1
        fi
    fi
    
    # Setup PHP CodeSniffer
    log_step "Configuring PHP CodeSniffer"
    if composer run setup-phpcs; then
        log_success "PHP CodeSniffer configured"
    else
        log_warning "PHP CodeSniffer configuration failed"
    fi
}

# Setup NPM dependencies
setup_npm() {
    if [[ "$SKIP_NPM" == true ]]; then
        log_info "Skipping NPM setup"
        return
    fi
    
    log_header "⚛️  Setting up JavaScript Dependencies"
    
    cd "$PROJECT_ROOT"
    
    if [[ "$FORCE_REINSTALL" == true && -d "node_modules" ]]; then
        log_step "Removing existing node_modules directory"
        rm -rf node_modules
        log_success "Node modules removed"
    fi
    
    if [[ ! "$SKIP_DEPENDENCIES" == true ]]; then
        log_step "Installing NPM dependencies"
        if npm ci; then
            log_success "NPM dependencies installed"
        else
            log_warning "npm ci failed, trying npm install"
            if npm install; then
                log_success "NPM dependencies installed"
            else
                log_error "Failed to install NPM dependencies"
                exit 1
            fi
        fi
    fi
}

# Setup Git hooks
setup_git_hooks() {
    if [[ "$SKIP_GIT_HOOKS" == true ]]; then
        log_info "Skipping Git hooks setup"
        return
    fi
    
    if [[ ! -d "$PROJECT_ROOT/.git" ]]; then
        log_warning "Not a Git repository - skipping hooks setup"
        return
    fi
    
    log_header "🔒 Setting up Git Hooks"
    
    if [[ -x "$SCRIPT_DIR/setup-git-hooks.sh" ]]; then
        log_step "Installing Git hooks"
        if "$SCRIPT_DIR/setup-git-hooks.sh"; then
            log_success "Git hooks installed"
        else
            log_warning "Git hooks installation failed"
        fi
    else
        log_warning "Git hooks setup script not found or not executable"
    fi
}

# Create additional configuration files
setup_configuration_files() {
    log_header "⚙️  Setting up Configuration Files"
    
    cd "$PROJECT_ROOT"
    
    # Create phpunit.xml if it doesn't exist
    if [[ ! -f "phpunit.xml" ]]; then
        log_step "Creating phpunit.xml"
        cat > phpunit.xml << 'EOF'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/10.0/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true"
         cacheDirectory=".phpunit.cache"
         failOnWarning="true"
         failOnRisky="true"
         beStrictAboutChangesToGlobalState="true"
         beStrictAboutOutputDuringTests="true"
         beStrictAboutTestsThatDoNotTestAnything="true"
         beStrictAboutCoverage="true">
    
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>
    
    <coverage>
        <include>
            <directory suffix=".php">src</directory>
        </include>
        <report>
            <html outputDirectory="coverage/html"/>
            <clover outputFile="coverage/clover.xml"/>
            <text outputFile="coverage/coverage.txt"/>
        </report>
    </coverage>
    
    <logging>
        <junit outputFile="coverage/junit.xml"/>
    </logging>
</phpunit>
EOF
        log_success "phpunit.xml created"
    else
        log_info "phpunit.xml already exists"
    fi
    
    # Create Jest setup file
    if [[ ! -f "jest.setup.js" ]]; then
        log_step "Creating jest.setup.js"
        cat > jest.setup.js << 'EOF'
import '@testing-library/jest-dom';

// Mock WordPress globals
global.wp = {
  i18n: {
    __: jest.fn(str => str),
    _x: jest.fn(str => str),
    _n: jest.fn((single, plural, number) => number === 1 ? single : plural),
    sprintf: jest.fn((format, ...args) => format),
  },
  hooks: {
    addAction: jest.fn(),
    addFilter: jest.fn(),
    doAction: jest.fn(),
    applyFilters: jest.fn((hook, value) => value),
  },
};

// Mock environment variables
process.env.NODE_ENV = 'test';
EOF
        log_success "jest.setup.js created"
    else
        log_info "jest.setup.js already exists"
    fi
    
    # Create .editorconfig
    if [[ ! -f ".editorconfig" ]]; then
        log_step "Creating .editorconfig"
        cat > .editorconfig << 'EOF'
root = true

[*]
charset = utf-8
end_of_line = lf
insert_final_newline = true
trim_trailing_whitespace = true
indent_style = space
indent_size = 2

[*.php]
indent_style = tab
indent_size = 4

[*.{js,jsx,ts,tsx,json,css,scss,sass}]
indent_style = space
indent_size = 2

[*.md]
trim_trailing_whitespace = false

[Makefile]
indent_style = tab
EOF
        log_success ".editorconfig created"
    else
        log_info ".editorconfig already exists"
    fi
}

# Verify installation
verify_installation() {
    log_header "🔍 Verifying Installation"
    
    cd "$PROJECT_ROOT"
    
    # Check if scripts are executable
    local scripts=("mandatory-verification.sh" "verify-standards.php" "verify-paths.sh" "setup-git-hooks.sh")
    for script in "${scripts[@]}"; do
        if [[ -f "scripts/$script" ]]; then
            if [[ -x "scripts/$script" || "$script" == *.php ]]; then
                log_success "Script $script is ready"
            else
                log_step "Making $script executable"
                chmod +x "scripts/$script"
                log_success "Script $script made executable"
            fi
        else
            log_warning "Script $script not found"
        fi
    done
    
    # Test Composer scripts
    if [[ "$SKIP_COMPOSER" != true ]]; then
        log_step "Testing Composer scripts"
        if composer run verify-standards --dry-run >/dev/null 2>&1; then
            log_success "Composer scripts configured correctly"
        else
            log_warning "Some Composer scripts may not be available yet"
        fi
    fi
    
    # Test NPM scripts  
    if [[ "$SKIP_NPM" != true ]]; then
        log_step "Testing NPM scripts"
        if npm run lint --dry-run >/dev/null 2>&1; then
            log_success "NPM scripts configured correctly"
        else
            log_warning "Some NPM scripts may not be available yet"
        fi
    fi
    
    # Test Git hooks
    if [[ -d ".git" && "$SKIP_GIT_HOOKS" != true ]]; then
        if [[ -x ".git/hooks/pre-commit" ]]; then
            log_success "Git hooks installed and executable"
        else
            log_warning "Git hooks may not be properly installed"
        fi
    fi
}

# Display final instructions
show_final_instructions() {
    log_header "🎉 Setup Complete!"
    
    echo ""
    echo -e "${GREEN}${BOLD}Development environment is ready!${NC}"
    echo ""
    echo -e "${BOLD}🚀 Available Commands:${NC}"
    echo ""
    echo -e "${CYAN}Quality Gates:${NC}"
    echo -e "${YELLOW}  composer run verify-all${NC}           # Run all verification checks"
    echo -e "${YELLOW}  ./scripts/mandatory-verification.sh${NC} # Run mandatory verification"
    echo -e "${YELLOW}  npm run verify-all${NC}               # Run frontend verification"
    echo ""
    echo -e "${CYAN}Development:${NC}"
    echo -e "${YELLOW}  npm run watch${NC}                    # Start development build"
    echo -e "${YELLOW}  npm run build${NC}                    # Build for production"
    echo -e "${YELLOW}  composer run test${NC}                # Run PHP unit tests"
    echo -e "${YELLOW}  npm test${NC}                         # Run React tests"
    echo ""
    echo -e "${CYAN}Code Quality:${NC}"
    echo -e "${YELLOW}  composer run phpcs${NC}               # Check PHP code standards"
    echo -e "${YELLOW}  composer run phpcbf${NC}              # Fix PHP code standards"
    echo -e "${YELLOW}  npm run lint${NC}                     # Lint JavaScript/React"
    echo -e "${YELLOW}  npm run lint:fix${NC}                 # Fix JavaScript/React issues"
    echo ""
    echo -e "${CYAN}VSCode Integration:${NC}"
    echo -e "${YELLOW}  Ctrl+Shift+P > Tasks: Run Task${NC}   # Access quality gate tasks"
    echo -e "${YELLOW}  F5${NC}                               # Debug PHP with Xdebug"
    echo ""
    echo -e "${BOLD}📝 Next Steps:${NC}"
    echo "1. Open project in VSCode to install recommended extensions"
    echo "2. Configure Xdebug in MAMP if you want PHP debugging"
    echo "3. Run quality gates before making commits"
    echo "4. Check ROADMAP.md to see development tasks"
    echo ""
    echo -e "${GREEN}Happy coding! 🎯${NC}"
    echo ""
}

# Main setup function
main() {
    parse_arguments "$@"
    
    log_header "🚀 Woo AI Assistant Development Setup"
    echo "Setting up comprehensive development environment..."
    echo ""
    
    if [[ "$FORCE_REINSTALL" == true ]]; then
        log_info "Force reinstall mode enabled"
    fi
    
    check_prerequisites
    setup_composer
    setup_npm
    setup_git_hooks
    setup_configuration_files
    verify_installation
    show_final_instructions
    
    log_success "Setup completed successfully!"
}

# Execute main function if script is run directly
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi