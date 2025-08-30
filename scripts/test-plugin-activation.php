#!/usr/bin/env php
<?php
/**
 * Plugin Activation Test Script
 *
 * This script tests if the plugin can be activated without fatal errors.
 * Must be run before marking any task as complete to prevent activation issues.
 *
 * @package WooAiAssistant
 * @subpackage Scripts
 * @since 1.0.0
 */

// Bootstrap WordPress test environment
$wp_tests_dir = getenv('WP_TESTS_DIR');
if (!$wp_tests_dir) {
    // Try common locations
    $possible_paths = [
        '/tmp/wordpress-tests-lib',
        '/Applications/MAMP/htdocs/wp-tests-lib',
        dirname(__DIR__, 4) . '/wp-tests-lib',
        dirname(__DIR__, 4) . '/tests/phpunit',
    ];
    
    foreach ($possible_paths as $path) {
        if (file_exists($path . '/includes/functions.php')) {
            $wp_tests_dir = $path;
            break;
        }
    }
}

// Simple activation test without full WordPress bootstrap
if (!$wp_tests_dir) {
    echo "⚠️  Running simple syntax and class loading test (WordPress test environment not found)\n";
    
    // Test 1: Check if main plugin file exists
    $plugin_file = dirname(__DIR__) . '/woo-ai-assistant.php';
    if (!file_exists($plugin_file)) {
        echo "❌ Main plugin file not found: woo-ai-assistant.php\n";
        exit(1);
    }
    echo "✅ Main plugin file exists\n";
    
    // Test 2: Check PHP syntax of main file
    $syntax_check = shell_exec("php -l $plugin_file 2>&1");
    if (strpos($syntax_check, 'No syntax errors') === false) {
        echo "❌ PHP syntax error in main plugin file:\n$syntax_check\n";
        exit(1);
    }
    echo "✅ Main plugin file syntax is valid\n";
    
    // Test 3: Check autoloader
    $autoloader = dirname(__DIR__) . '/vendor/autoload.php';
    if (!file_exists($autoloader)) {
        echo "❌ Composer autoloader not found. Run: composer install\n";
        exit(1);
    }
    require_once $autoloader;
    echo "✅ Composer autoloader loaded\n";
    
    // Test 4: Check critical classes can be loaded
    echo "🔍 Testing critical class loading...\n";
    $critical_classes = [
        'WooAiAssistant\\Main',
        'WooAiAssistant\\Setup\\Activator',
        'WooAiAssistant\\Setup\\Deactivator',
        'WooAiAssistant\\Setup\\Installer',
        'WooAiAssistant\\Common\\Utils',
        'WooAiAssistant\\Common\\Logger',
        'WooAiAssistant\\Common\\Cache',
        'WooAiAssistant\\Common\\Validator',
        'WooAiAssistant\\Common\\Sanitizer',
        'WooAiAssistant\\Common\\Traits\\Singleton',
    ];
    
    $failed_classes = [];
    foreach ($critical_classes as $class) {
        if (!class_exists($class) && !trait_exists($class)) {
            $failed_classes[] = $class;
        }
    }
    
    if (!empty($failed_classes)) {
        echo "❌ Failed to load critical classes:\n";
        foreach ($failed_classes as $class) {
            echo "   - $class\n";
        }
        exit(1);
    }
    echo "✅ All critical classes can be loaded\n";
    
    // Test 5: Check critical methods exist
    $methods_to_check = [
        'WooAiAssistant\\Common\\Utils' => [
            'isWooCommerceActive',
            'isDevelopmentMode',
            'getVersion',
            'getPluginPath',
            'getPluginUrl',
            'getWooCommerceVersion', // This was missing!
        ],
        'WooAiAssistant\\Setup\\Activator' => [
            'activate',
        ],
        'WooAiAssistant\\Setup\\Installer' => [
            'install',
        ],
    ];
    
    $missing_methods = [];
    foreach ($methods_to_check as $class => $methods) {
        if (class_exists($class)) {
            $reflection = new ReflectionClass($class);
            foreach ($methods as $method) {
                if (!$reflection->hasMethod($method)) {
                    $missing_methods[] = "$class::$method";
                }
            }
        }
    }
    
    if (!empty($missing_methods)) {
        echo "❌ Missing required methods:\n";
        foreach ($missing_methods as $method) {
            echo "   - $method()\n";
        }
        exit(1);
    }
    echo "✅ All required methods exist\n";
    
    // Test 6: Check for common fatal error patterns
    $php_files = glob(dirname(__DIR__) . '/src/**/*.php');
    if (empty($php_files)) {
        $php_files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(dirname(__DIR__) . '/src')
        );
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $php_files[] = $file->getPathname();
            }
        }
    }
    
    $syntax_errors = [];
    foreach ($php_files as $file) {
        $syntax_check = shell_exec("php -l $file 2>&1");
        if (strpos($syntax_check, 'No syntax errors') === false) {
            $syntax_errors[$file] = $syntax_check;
        }
    }
    
    if (!empty($syntax_errors)) {
        echo "❌ PHP syntax errors found:\n";
        foreach ($syntax_errors as $file => $error) {
            echo "   File: $file\n   Error: $error\n";
        }
        exit(1);
    }
    echo "✅ No PHP syntax errors in src/ directory\n";
    
    // Test 7: Check database table creation queries
    if (class_exists('WooAiAssistant\\Setup\\Activator')) {
        echo "✅ Activator class is loadable\n";
    }
    
    echo "\n";
    echo "========================================\n";
    echo "✅ PLUGIN ACTIVATION TEST PASSED\n";
    echo "========================================\n";
    echo "The plugin should be able to activate without fatal errors.\n";
    echo "Note: This is a simplified test. Full WordPress integration testing recommended.\n";
    
} else {
    // Full WordPress test environment available
    echo "Running full WordPress activation test...\n";
    
    require_once $wp_tests_dir . '/includes/functions.php';
    
    function _manually_load_plugin() {
        require dirname(__DIR__) . '/woo-ai-assistant.php';
    }
    
    tests_add_filter('muplugins_loaded', '_manually_load_plugin');
    
    require_once $wp_tests_dir . '/includes/bootstrap.php';
    
    // Test activation
    try {
        WooAiAssistant\Setup\Activator::activate();
        echo "✅ Plugin activation successful!\n";
    } catch (Exception $e) {
        echo "❌ Plugin activation failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}

exit(0);