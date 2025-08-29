<?php
/**
 * Test syntax and basic plugin loading
 */

// Check PHP syntax only
$file = __DIR__ . '/woo-ai-assistant.php';

// Parse check
$output = shell_exec("php -l $file 2>&1");
echo "=== PHP Syntax Check ===\n";
echo $output . "\n";

// Check for common issues
$content = file_get_contents($file);

// Count opening and closing braces
$opening_braces = substr_count($content, '{');
$closing_braces = substr_count($content, '}');

echo "=== Brace Count Check ===\n";
echo "Opening braces: $opening_braces\n";
echo "Closing braces: $closing_braces\n";

if ($opening_braces === $closing_braces) {
    echo "✅ Braces are balanced!\n";
} else {
    echo "❌ Braces are NOT balanced! Difference: " . abs($opening_braces - $closing_braces) . "\n";
}

// Check for other files
echo "\n=== Checking Other Critical Files ===\n";
$critical_files = [
    'src/Main.php',
    'src/Frontend/WidgetLoader.php',
    'src/Common/DevelopmentConfig.php',
    'src/Common/ApiConfiguration.php'
];

foreach ($critical_files as $file) {
    $fullPath = __DIR__ . '/' . $file;
    if (file_exists($fullPath)) {
        $result = shell_exec("php -l $fullPath 2>&1");
        if (strpos($result, 'No syntax errors') !== false) {
            echo "✅ $file - OK\n";
        } else {
            echo "❌ $file - ERROR\n$result\n";
        }
    } else {
        echo "⚠️  $file - File not found\n";
    }
}

echo "\n=== Summary ===\n";
echo "All syntax checks completed. If you see all ✅, the plugin should load correctly.\n";
echo "Try visiting: http://localhost:8888/wp/wp-admin/plugins.php to activate the plugin.\n";