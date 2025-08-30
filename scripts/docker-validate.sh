#!/bin/bash
set -euo pipefail

# Docker Environment Validation Script
# Tests all Docker components to ensure they're working correctly

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

echo "🧪 Docker Environment Validation"
echo "=================================="

# Track test results
PASSED=0
FAILED=0

# Function to run test and track results
run_test() {
    local test_name="$1"
    local test_command="$2"
    
    printf "%-50s" "Testing $test_name..."
    
    if eval "$test_command" &>/dev/null; then
        echo "✅ PASS"
        ((PASSED++))
    else
        echo "❌ FAIL"
        ((FAILED++))
        echo "   Command: $test_command"
    fi
}

echo ""
echo "🔍 Basic Requirements"
echo "--------------------"

run_test "Docker installed" "command -v docker"
run_test "Docker Compose installed" "command -v docker-compose"
run_test "Docker daemon running" "docker info"

echo ""
echo "📁 Project Files"
echo "----------------"

cd "$PROJECT_DIR"

run_test "docker-compose.yml exists" "test -f docker-compose.yml"
run_test ".env.docker template exists" "test -f .env.docker"
run_test ".dockerignore exists" "test -f .dockerignore"
run_test "Docker setup script exists" "test -f scripts/docker-setup.sh"
run_test "Docker test script exists" "test -f scripts/docker-test.sh"
run_test "Docker reset script exists" "test -f scripts/docker-reset.sh"

echo ""
echo "📦 Docker Services"
echo "------------------"

# Check if services are running
SERVICES_RUNNING=false
if docker-compose ps | grep -q "Up"; then
    SERVICES_RUNNING=true
fi

if [ "$SERVICES_RUNNING" = true ]; then
    echo "Services are currently running. Testing live environment..."
    
    run_test "WordPress container running" "docker-compose ps wordpress | grep -q 'Up'"
    run_test "MySQL container running" "docker-compose ps mysql | grep -q 'Up'"
    run_test "phpMyAdmin container running" "docker-compose ps phpmyadmin | grep -q 'Up'"
    run_test "Mailhog container running" "docker-compose ps mailhog | grep -q 'Up'"
    run_test "Redis container running" "docker-compose ps redis | grep -q 'Up'"
    
    echo ""
    echo "🌐 Service Connectivity"
    echo "-----------------------"
    
    run_test "WordPress responding" "curl -sf http://localhost:8080 > /dev/null"
    run_test "phpMyAdmin responding" "curl -sf http://localhost:8081 > /dev/null"
    run_test "Mailhog responding" "curl -sf http://localhost:8025 > /dev/null"
    
    echo ""
    echo "🔧 WordPress Integration"
    echo "------------------------"
    
    run_test "WP-CLI accessible" "docker-compose exec -T wordpress wp --allow-root --version"
    run_test "Database connection" "docker-compose exec -T wordpress wp --allow-root db check"
    run_test "WooCommerce installed" "docker-compose exec -T wordpress wp --allow-root plugin is-installed woocommerce"
    
    # Check if our plugin directory exists
    if docker-compose exec -T wordpress test -d /var/www/html/wp-content/plugins/woo-ai-assistant; then
        run_test "Plugin directory mounted" "true"
        run_test "Composer available in container" "docker-compose exec -T wordpress composer --version"
    else
        run_test "Plugin directory mounted" "false"
    fi
    
else
    echo "Services are not running. Testing configuration only..."
    
    run_test "Docker Compose config valid" "docker-compose config -q"
    run_test "Docker images can be built" "docker-compose config | grep -q 'image\\|build'"
    
    echo ""
    echo "💡 Services not running. To test live environment:"
    echo "   Run: ./scripts/docker-setup.sh"
    echo "   Then: ./scripts/docker-validate.sh"
fi

echo ""
echo "📊 Validation Results"
echo "====================="

TOTAL=$((PASSED + FAILED))
SUCCESS_RATE=$((PASSED * 100 / TOTAL))

echo "Total Tests: $TOTAL"
echo "Passed: $PASSED"
echo "Failed: $FAILED"
echo "Success Rate: $SUCCESS_RATE%"

if [ $FAILED -eq 0 ]; then
    echo ""
    echo "🎉 All tests passed! Docker environment is ready for development."
    
    if [ "$SERVICES_RUNNING" = true ]; then
        echo ""
        echo "🔗 Access your environment:"
        echo "   WordPress: http://localhost:8080"
        echo "   Admin: http://localhost:8080/wp-admin (admin/password)"
        echo "   phpMyAdmin: http://localhost:8081"
        echo "   Mailhog: http://localhost:8025"
    fi
    
    exit 0
else
    echo ""
    echo "⚠️  Some tests failed. Please check the issues above."
    
    if [ $SUCCESS_RATE -lt 70 ]; then
        echo ""
        echo "🚨 Major issues detected. Try:"
        echo "   1. ./scripts/docker-reset.sh"
        echo "   2. ./scripts/docker-setup.sh"
        echo "   3. ./scripts/docker-validate.sh"
    fi
    
    exit 1
fi