<?php
/**
 * Test Runner Script
 *
 * Simple script to run PHPUnit tests with proper WordPress environment setup.
 * Can be used as an alternative to composer scripts for direct test execution.
 *
 * @package WooAiAssistant
 * @subpackage Tests
 * @since 1.0.0
 * @author Claude Code Assistant
 */

// Prevent direct access
if (!defined('ABSPATH') && php_sapi_name() !== 'cli') {
    exit('This script can only be run from command line or WordPress environment');
}

// Set script parameters
$scriptDir = __DIR__;
$pluginDir = dirname($scriptDir);

echo "=== Woo AI Assistant Test Runner ===\n";
echo "Plugin Directory: {$pluginDir}\n";
echo "Test Directory: {$scriptDir}\n";

// Check if PHPUnit is available
$phpunitPaths = [
    $pluginDir . '/vendor/bin/phpunit',
    'phpunit', // Global installation
];

$phpunit = null;
foreach ($phpunitPaths as $path) {
    if (file_exists($path) || shell_exec("which {$path}")) {
        $phpunit = $path;
        break;
    }
}

if (!$phpunit) {
    echo "ERROR: PHPUnit not found. Please install via composer:\n";
    echo "  composer install\n";
    exit(1);
}

echo "PHPUnit Found: {$phpunit}\n";

// Check configuration file
$configFile = $pluginDir . '/phpunit.xml';
if (!file_exists($configFile)) {
    echo "ERROR: PHPUnit configuration not found at: {$configFile}\n";
    exit(1);
}

echo "Configuration: {$configFile}\n";

// Check bootstrap file
$bootstrapFile = $scriptDir . '/bootstrap.php';
if (!file_exists($bootstrapFile)) {
    echo "ERROR: Bootstrap file not found at: {$bootstrapFile}\n";
    exit(1);
}

echo "Bootstrap: {$bootstrapFile}\n";

// Parse command line arguments
$testSuite = $argv[1] ?? 'all';
$coverage = in_array('--coverage', $argv);
$verbose = in_array('--verbose', $argv) || in_array('-v', $argv);

echo "Test Suite: {$testSuite}\n";
echo "Coverage: " . ($coverage ? 'Yes' : 'No') . "\n";
echo "Verbose: " . ($verbose ? 'Yes' : 'No') . "\n";
echo "\n";

// Build PHPUnit command
$command = "cd {$pluginDir} && {$phpunit}";

// Add configuration
$command .= " --configuration {$configFile}";

// Add test suite filter
if ($testSuite !== 'all') {
    switch (strtolower($testSuite)) {
        case 'unit':
            $command .= ' --testsuite "Unit Tests"';
            break;
        case 'integration':
            $command .= ' --testsuite "Integration Tests"';
            break;
        default:
            $command .= " --filter {$testSuite}";
            break;
    }
}

// Add coverage
if ($coverage) {
    $command .= ' --coverage-html coverage/html --coverage-text';
}

// Add verbosity
if ($verbose) {
    $command .= ' --verbose';
}

echo "Executing: {$command}\n";
echo "==========================================\n";

// Create necessary directories
$dirs = [
    $pluginDir . '/coverage',
    $pluginDir . '/coverage/html',
    $scriptDir . '/logs',
    $scriptDir . '/tmp'
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Execute tests
$startTime = microtime(true);
passthru($command, $exitCode);
$endTime = microtime(true);

$duration = round($endTime - $startTime, 2);

echo "\n==========================================\n";
echo "Test execution completed in {$duration} seconds\n";
echo "Exit code: {$exitCode}\n";

if ($exitCode === 0) {
    echo "✅ All tests passed!\n";
} else {
    echo "❌ Some tests failed. Check output above.\n";
}

// Show coverage information if generated
if ($coverage && file_exists($pluginDir . '/coverage/html/index.html')) {
    echo "\nCoverage report generated at: {$pluginDir}/coverage/html/index.html\n";
}

exit($exitCode);