#!/bin/bash
#
# Mandatory Verification Script
# Basic quality gates for pre-commit hook
#

set -e

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${YELLOW}Running basic quality checks...${NC}"

# Check for sensitive data
if grep -r "sk_live_" --include="*.php" --include="*.js" . 2>/dev/null; then
    echo "❌ Found live API keys in code!"
    exit 1
fi

# Check PHP syntax (if PHP files exist)
if ls src/*.php 1> /dev/null 2>&1; then
    for file in src/*.php; do
        if [ -f "$file" ]; then
            php -l "$file" > /dev/null 2>&1 || {
                echo "❌ PHP syntax error in $file"
                exit 1
            }
        fi
    done
fi

echo -e "${GREEN}✅ Basic quality checks passed${NC}"
exit 0