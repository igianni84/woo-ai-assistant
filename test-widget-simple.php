<?php
/**
 * Simple Widget Loading Test
 * 
 * Tests if the widget loader is working without full WordPress context
 */

// Error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Woo AI Assistant Widget Simple Test</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:#28a745;} .error{color:#dc3545;} .warning{color:#ffc107;} .info{color:#17a2b8;}</style>";

// 1. Check if constants are defined (from main plugin file)
echo "<h2>1. Constants Check</h2>";
if (defined('WOO_AI_ASSISTANT_VERSION')) {
    echo "<p class='success'>✓ WOO_AI_ASSISTANT_VERSION: " . WOO_AI_ASSISTANT_VERSION . "</p>";
} else {
    echo "<p class='error'>✗ WOO_AI_ASSISTANT_VERSION not defined</p>";
}

if (defined('WOO_AI_ASSISTANT_ASSETS_URL')) {
    echo "<p class='success'>✓ WOO_AI_ASSISTANT_ASSETS_URL: " . WOO_AI_ASSISTANT_ASSETS_URL . "</p>";
} else {
    echo "<p class='error'>✗ WOO_AI_ASSISTANT_ASSETS_URL not defined</p>";
}

if (defined('WOO_AI_DEVELOPMENT_MODE')) {
    echo "<p class='success'>✓ WOO_AI_DEVELOPMENT_MODE: " . (WOO_AI_DEVELOPMENT_MODE ? 'TRUE' : 'FALSE') . "</p>";
} else {
    echo "<p class='warning'>! WOO_AI_DEVELOPMENT_MODE not defined</p>";
}

// 2. Check asset files directly
echo "<h2>2. Asset Files Check</h2>";
$pluginDir = dirname(__FILE__);
$assetsDir = $pluginDir . '/assets';

echo "<p class='info'>Plugin Directory: " . $pluginDir . "</p>";
echo "<p class='info'>Assets Directory: " . $assetsDir . "</p>";

$requiredFiles = [
    '/assets/css/widget.min.css',
    '/assets/js/widget.min.js'
];

foreach ($requiredFiles as $file) {
    $fullPath = $pluginDir . $file;
    if (file_exists($fullPath)) {
        $size = filesize($fullPath);
        echo "<p class='success'>✓ {$file} exists (" . number_format($size) . " bytes)</p>";
    } else {
        echo "<p class='error'>✗ {$file} missing</p>";
    }
}

// 3. Test if plugin main file exists and is readable
echo "<h2>3. Plugin Main File Check</h2>";
$mainFile = $pluginDir . '/woo-ai-assistant.php';
if (file_exists($mainFile)) {
    echo "<p class='success'>✓ Main plugin file exists</p>";
    if (is_readable($mainFile)) {
        echo "<p class='success'>✓ Main plugin file is readable</p>";
    } else {
        echo "<p class='error'>✗ Main plugin file is not readable</p>";
    }
} else {
    echo "<p class='error'>✗ Main plugin file missing</p>";
}

// 4. Test if WidgetLoader class file exists
echo "<h2>4. WidgetLoader Class File Check</h2>";
$widgetLoaderFile = $pluginDir . '/src/Frontend/WidgetLoader.php';
if (file_exists($widgetLoaderFile)) {
    echo "<p class='success'>✓ WidgetLoader.php exists</p>";
    if (is_readable($widgetLoaderFile)) {
        echo "<p class='success'>✓ WidgetLoader.php is readable</p>";
    } else {
        echo "<p class='error'>✗ WidgetLoader.php is not readable</p>";
    }
} else {
    echo "<p class='error'>✗ WidgetLoader.php missing</p>";
}

// 5. Simple WordPress hook test (if WordPress is loaded)
echo "<h2>5. WordPress Integration Test</h2>";
if (function_exists('wp_enqueue_script')) {
    echo "<p class='success'>✓ WordPress functions available</p>";
    
    if (function_exists('is_plugin_active')) {
        $pluginPath = 'woo-ai-assistant/woo-ai-assistant.php';
        if (is_plugin_active($pluginPath)) {
            echo "<p class='success'>✓ Plugin is active</p>";
        } else {
            echo "<p class='warning'>! Plugin is not active</p>";
        }
    } else {
        echo "<p class='warning'>! is_plugin_active function not available</p>";
    }
} else {
    echo "<p class='warning'>! WordPress functions not available (this is normal for direct file access)</p>";
}

// 6. Development environment detection
echo "<h2>6. Development Environment Detection</h2>";
$devIndicators = [
    'localhost' => isset($_SERVER['SERVER_NAME']) && strpos($_SERVER['SERVER_NAME'], 'localhost') !== false,
    '127.0.0.1' => isset($_SERVER['SERVER_NAME']) && strpos($_SERVER['SERVER_NAME'], '127.0.0.1') !== false,
    'MAMP' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'MAMP') !== false,
    'WP_DEBUG' => defined('WP_DEBUG') && WP_DEBUG
];

foreach ($devIndicators as $indicator => $detected) {
    if ($detected) {
        echo "<p class='success'>✓ {$indicator} detected</p>";
    } else {
        echo "<p class='info'>- {$indicator} not detected</p>";
    }
}

// 7. Memory and PHP info
echo "<h2>7. System Information</h2>";
echo "<p class='info'>PHP Version: " . phpversion() . "</p>";
echo "<p class='info'>Memory Limit: " . ini_get('memory_limit') . "</p>";
echo "<p class='info'>Max Execution Time: " . ini_get('max_execution_time') . " seconds</p>";
echo "<p class='info'>Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "</p>";

echo "<h2>Test Complete</h2>";
echo "<p class='info'>If you see issues above, they indicate specific problems that need to be resolved.</p>";
?>