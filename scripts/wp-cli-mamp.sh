#!/bin/bash
# WP-CLI Helper Script for MAMP Environment
# This script configures the environment for WP-CLI to work with MAMP

# Add MAMP MySQL to PATH
export PATH="/Applications/MAMP/Library/bin/mysql80/bin:$PATH"

# Execute WP-CLI with all arguments passed to this script
wp "$@"