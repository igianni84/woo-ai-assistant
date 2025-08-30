# Woo AI Assistant - Scripts Directory

This directory contains utility scripts for development and deployment.

## Available Scripts

### wp-cli-mamp.sh
**Purpose:** Helper script for using WP-CLI with MAMP environment  
**Usage:** `./scripts/wp-cli-mamp.sh [wp-cli-commands]`

This script automatically configures the PATH to include MAMP's MySQL binary, solving the common "mysql: command not found" error when using WP-CLI with MAMP.

**Examples:**
```bash
# List plugins
./scripts/wp-cli-mamp.sh plugin list

# Check database connection
./scripts/wp-cli-mamp.sh db query "SELECT 1"

# Activate the plugin
./scripts/wp-cli-mamp.sh plugin activate woo-ai-assistant

# Run any WP-CLI command
./scripts/wp-cli-mamp.sh user list
```

### verify-paths.sh
**Purpose:** Verifies that required file paths exist  
**Usage:** `bash scripts/verify-paths.sh`

### verify-standards.php
**Purpose:** Checks PHP naming conventions and coding standards  
**Usage:** `php scripts/verify-standards.php`

### quality-gates-enforcer.sh
**Purpose:** Main enforcement script for quality gates  
**Usage:** `bash scripts/quality-gates-enforcer.sh`

This is a MANDATORY script that must pass before marking any task as completed.

### Docker Scripts (Coming Soon)
- `docker-setup.sh` - Automated Docker environment setup
- `docker-test.sh` - Run tests in Docker environment
- `docker-reset.sh` - Complete environment reset
- `deploy.sh` - Deployment script

## MAMP Environment Notes

When working with MAMP, remember:
1. Always use `127.0.0.1:8889` instead of `localhost:8889` in wp-config.php
2. Use the `wp-cli-mamp.sh` wrapper script for all WP-CLI commands
3. MySQL binary is located at `/Applications/MAMP/Library/bin/mysql80/bin/mysql`

## Quality Gates

Before marking any task as completed, you MUST run:
```bash
composer run quality-gates-enforce
```

The output must show: `QUALITY_GATES_STATUS=PASSED`