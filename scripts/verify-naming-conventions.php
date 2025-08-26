<?php
/**
 * Naming Conventions Verification Script
 * 
 * Verifies that all PHP classes and methods follow the naming conventions
 * defined in CLAUDE.md for Task 2.5 quality gates.
 * 
 * @package WooAiAssistant
 * @since 1.0.0
 */

$errors = [];
$warnings = [];
$files_checked = 0;

/**
 * Recursively scan directory for PHP files
 */
function scanPHPFiles($dir) {
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
    
    return $files;
}

/**
 * Verify class naming follows PascalCase
 */
function verifyClassNames($filePath, $content) {
    global $errors;
    
    preg_match_all('/class\s+([a-zA-Z_][a-zA-Z0-9_]*)/m', $content, $matches);
    
    foreach ($matches[1] as $className) {
        // Skip anonymous classes and test classes ending with 'Test'
        if ($className === 'class' || strpos($className, '$') !== false) continue;
        
        if (!preg_match('/^[A-Z][a-zA-Z0-9]*$/', $className)) {
            $errors[] = "❌ Class '{$className}' in {$filePath} does not follow PascalCase naming";
        }
    }
}

/**
 * Verify method naming follows camelCase
 */
function verifyMethodNames($filePath, $content) {
    global $errors, $warnings;
    
    preg_match_all('/(?:public|private|protected)\s+(?:static\s+)?function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/m', $content, $matches);
    
    foreach ($matches[1] as $methodName) {
        // Skip magic methods and constructors
        if (strpos($methodName, '__') === 0) continue;
        if ($methodName === 'setUp' || $methodName === 'tearDown') continue; // PHPUnit methods
        
        if (!preg_match('/^[a-z][a-zA-Z0-9]*$/', $methodName)) {
            $errors[] = "❌ Method '{$methodName}' in {$filePath} does not follow camelCase naming";
        }
        
        // Check for descriptive naming
        if (strlen($methodName) < 3) {
            $warnings[] = "⚠️  Method '{$methodName}' in {$filePath} has a very short name - consider more descriptive naming";
        }
    }
}

/**
 * Verify constants follow UPPER_SNAKE_CASE
 */
function verifyConstants($filePath, $content) {
    global $errors;
    
    preg_match_all('/const\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*=/m', $content, $matches);
    
    foreach ($matches[1] as $constantName) {
        if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $constantName)) {
            $errors[] = "❌ Constant '{$constantName}' in {$filePath} does not follow UPPER_SNAKE_CASE naming";
        }
    }
}

/**
 * Verify namespace structure follows PSR-4
 */
function verifyNamespace($filePath, $content) {
    global $errors, $warnings;
    
    preg_match('/namespace\s+([a-zA-Z\\\\][a-zA-Z0-9\\\\]*);/', $content, $matches);
    
    if (empty($matches[1])) {
        $warnings[] = "⚠️  No namespace found in {$filePath}";
        return;
    }
    
    $namespace = $matches[1];
    $expectedPrefix = 'WooAiAssistant';
    
    if (strpos($namespace, $expectedPrefix) !== 0) {
        $errors[] = "❌ Namespace '{$namespace}' in {$filePath} should start with '{$expectedPrefix}'";
    }
}

/**
 * Main verification function
 */
function verifyFile($filePath) {
    global $files_checked;
    
    $content = file_get_contents($filePath);
    if ($content === false) {
        global $errors;
        $errors[] = "❌ Could not read file: {$filePath}";
        return;
    }
    
    verifyClassNames($filePath, $content);
    verifyMethodNames($filePath, $content);
    verifyConstants($filePath, $content);
    verifyNamespace($filePath, $content);
    
    $files_checked++;
}

// Main execution
echo "\n🔍 NAMING CONVENTIONS VERIFICATION\n";
echo "================================\n\n";

$srcDir = __DIR__ . '/../src';
$testDir = __DIR__ . '/../tests';

echo "Scanning source files...\n";
$sourceFiles = scanPHPFiles($srcDir);

echo "Scanning test files...\n"; 
$testFiles = scanPHPFiles($testDir);

$allFiles = array_merge($sourceFiles, $testFiles);

echo "Found " . count($allFiles) . " PHP files to check\n\n";

foreach ($allFiles as $file) {
    echo "Checking: " . basename($file) . "\n";
    verifyFile($file);
}

echo "\n📊 VERIFICATION RESULTS\n";
echo "======================\n";
echo "Files checked: {$files_checked}\n";
echo "Errors found: " . count($errors) . "\n";
echo "Warnings found: " . count($warnings) . "\n\n";

if (!empty($errors)) {
    echo "🚨 ERRORS:\n";
    foreach ($errors as $error) {
        echo $error . "\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "⚠️  WARNINGS:\n";
    foreach ($warnings as $warning) {
        echo $warning . "\n";
    }
    echo "\n";
}

if (empty($errors)) {
    echo "✅ All naming conventions verified successfully!\n";
    exit(0);
} else {
    echo "❌ Naming convention violations found - please fix before completing task\n";
    exit(1);
}