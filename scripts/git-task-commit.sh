#!/bin/bash
#
# Git Task Commit Script
# Automatically commits and pushes code after successful quality gates
#
# Usage: ./scripts/git-task-commit.sh <task-number> <task-description>
# Example: ./scripts/git-task-commit.sh "0.1" "Plugin Skeleton"
#

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Arguments
TASK_NUMBER=$1
TASK_DESCRIPTION=$2

# Validation
if [ -z "$TASK_NUMBER" ] || [ -z "$TASK_DESCRIPTION" ]; then
    echo -e "${RED}❌ Error: Missing arguments${NC}"
    echo "Usage: $0 <task-number> <task-description>"
    echo "Example: $0 '0.1' 'Plugin Skeleton'"
    exit 1
fi

echo -e "${YELLOW}🔄 Starting Git Task Commit for Task ${TASK_NUMBER}: ${TASK_DESCRIPTION}${NC}"

# Step 1: Check quality gates status
echo -e "\n${YELLOW}📋 Step 1: Checking quality gates status...${NC}"
if [ ! -f ".quality-gates-status" ]; then
    echo -e "${RED}❌ Quality gates status file not found!${NC}"
    echo "Please run: composer run quality-gates-enforce"
    exit 1
fi

GATES_STATUS=$(grep "QUALITY_GATES_STATUS" .quality-gates-status | cut -d'=' -f2)
if [ "$GATES_STATUS" != "PASSED" ]; then
    echo -e "${RED}❌ Quality gates have not passed!${NC}"
    echo "Current status: $GATES_STATUS"
    echo "Please run: composer run quality-gates-enforce"
    exit 1
fi
echo -e "${GREEN}✅ Quality gates passed${NC}"

# Step 2: Check for uncommitted changes
echo -e "\n${YELLOW}📋 Step 2: Checking for uncommitted changes...${NC}"
if [ -z "$(git status --porcelain)" ]; then
    echo -e "${YELLOW}⚠️  No changes to commit${NC}"
    exit 0
fi

# Step 3: Stage changes (excluding ignored files)
echo -e "\n${YELLOW}📋 Step 3: Staging changes...${NC}"

# Add all changes except deleted files first
git add -A

# Show what will be committed
echo -e "\n${YELLOW}Files to be committed:${NC}"
git status --short

# Step 4: Create commit message
echo -e "\n${YELLOW}📋 Step 4: Creating commit...${NC}"

COMMIT_MESSAGE="feat(task-${TASK_NUMBER}): ${TASK_DESCRIPTION}

- Task ${TASK_NUMBER} completed successfully
- All quality gates passed
- Tests verified

Task specifications from ROADMAP.md
Developed following CLAUDE.md standards

🤖 Generated with Claude Code

Co-Authored-By: Claude <noreply@anthropic.com>"

# Create the commit
git commit -m "$COMMIT_MESSAGE"
echo -e "${GREEN}✅ Commit created${NC}"

# Step 5: Push to remote
echo -e "\n${YELLOW}📋 Step 5: Pushing to GitHub...${NC}"

# Check if we're on main branch
CURRENT_BRANCH=$(git branch --show-current)
if [ "$CURRENT_BRANCH" != "main" ]; then
    echo -e "${YELLOW}⚠️  Not on main branch. Current branch: $CURRENT_BRANCH${NC}"
    read -p "Do you want to push to $CURRENT_BRANCH? (y/n): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo -e "${YELLOW}Push cancelled. Commit is saved locally.${NC}"
        exit 0
    fi
fi

# Push to remote
if git push origin "$CURRENT_BRANCH"; then
    echo -e "${GREEN}✅ Successfully pushed to GitHub${NC}"
    echo -e "${GREEN}🎉 Task ${TASK_NUMBER} committed and pushed successfully!${NC}"
    
    # Show the commit hash
    COMMIT_HASH=$(git rev-parse HEAD)
    echo -e "\n${GREEN}Commit: $COMMIT_HASH${NC}"
    echo -e "${GREEN}Branch: $CURRENT_BRANCH${NC}"
    echo -e "${GREEN}Remote: https://github.com/igianni84/woo-ai-assistant/commit/$COMMIT_HASH${NC}"
else
    echo -e "${RED}❌ Push failed. Commit is saved locally.${NC}"
    echo "You can try pushing manually with: git push origin $CURRENT_BRANCH"
    exit 1
fi

# Step 6: Update ROADMAP.md status (optional notification)
echo -e "\n${YELLOW}📋 Remember to update ROADMAP.md:${NC}"
echo -e "- Mark Task ${TASK_NUMBER} as 'completed'"
echo -e "- Update progress percentages"
echo -e "- Add completion date"

echo -e "\n${GREEN}🚀 Task ${TASK_NUMBER} workflow completed!${NC}"