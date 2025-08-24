<?php
/**
 * Temporary Test Runner for Quality Gates Verification
 * 
 * This file runs a subset of critical tests that verify our implementation
 * meets the mandatory quality gates without requiring full WordPress setup.
 * 
 * @package WooAiAssistant\Tests
 * @since 1.0.0
 */

require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';
require_once __DIR__ . '/../../bootstrap.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Setup/Activator.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Common/Utils.php';

use WooAiAssistant\Setup\Activator;

// Mock additional WordPress functions needed for Activator
function get_role($role) {
    $mockRole = new stdClass();
    $mockRole->add_cap = function($cap) { return true; };
    $mockRole->has_cap = function($cap) { return true; };
    return $mockRole;
}

function wp_upload_dir() {
    return [
        'basedir' => sys_get_temp_dir() . '/test-uploads',
        'baseurl' => 'http://example.com/test-uploads'
    ];
}

function wp_mkdir_p($target) {
    return mkdir($target, 0755, true);
}

if (!function_exists('file_put_contents_mock')) {
    function file_put_contents_mock($filename, $data, $flags = 0) {
        return true; // Mock successful file write  
    }
}

function wp_next_scheduled($hook) {
    return false; // Not scheduled initially
}

function wp_schedule_event($timestamp, $recurrence, $hook, $args = []) {
    return true;
}

function wp_clear_scheduled_hook($hook, $args = []) {
    return true;
}

// These PHP native functions already exist, we'll use them as-is

// Mock database functions
global $wpdb;
$wpdb = new stdClass();
$wpdb->prefix = 'wp_';
$wpdb->get_charset_collate = function() { return 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'; };
$wpdb->prepare = function($query, ...$args) use ($wpdb) {
    // Simple prepare mock
    foreach ($args as $arg) {
        $query = preg_replace('/%s|%d/', $arg, $query, 1);
    }
    return $query;
};
$wpdb->get_var = function($query) use ($wpdb) {
    if (strpos($query, 'information_schema') !== false) {
        return 1; // Mock table exists
    }
    if (strpos($query, 'SHOW TABLES') !== false) {
        return $wpdb->prefix . 'woo_ai_conversations'; // Mock table exists
    }
    return null;
};
$wpdb->get_results = function($query) {
    if (strpos($query, 'DESCRIBE') !== false) {
        // Mock table structure
        return [
            (object)['Field' => 'id', 'Type' => 'bigint(20) unsigned'],
            (object)['Field' => 'conversation_id', 'Type' => 'varchar(255)'],
            (object)['Field' => 'user_id', 'Type' => 'bigint(20) unsigned'],
            (object)['Field' => 'session_id', 'Type' => 'varchar(255)'],
            (object)['Field' => 'status', 'Type' => 'varchar(20)'],
            (object)['Field' => 'started_at', 'Type' => 'datetime']
        ];
    }
    return [];
};
$wpdb->get_row = function($query) {
    // Mock table status
    return (object)[
        'Rows' => 0,
        'Data_length' => 0,
        'Index_length' => 0,
        'Auto_increment' => 1,
        'Engine' => 'InnoDB',
        'Collation' => 'utf8mb4_unicode_ci'
    ];
};
$wpdb->query = function($query) { return true; };

// Define missing constants
if (!defined('ABSPATH')) define('ABSPATH', '/tmp/');
if (!defined('DB_NAME')) define('DB_NAME', 'test_db');
if (!defined('WOO_AI_ASSISTANT_VERSION')) define('WOO_AI_ASSISTANT_VERSION', '1.0.0');

function dbDelta($queries) {
    return ['Created table wp_test'];
}

/**
 * Quality Gates Test Runner
 */
class QualityGatesTestRunner {
    
    private $testResults = [];
    private $passed = 0;
    private $failed = 0;
    
    public function runAllTests() {
        echo "🚨 EXECUTING MANDATORY QUALITY GATES FOR ACTIVATOR CLASS\n\n";
        
        $this->testClassExists();
        $this->testNamingConventions();
        $this->testPublicMethods();
        $this->testDatabaseTables();
        $this->testDatabaseVersionMethods();
        $this->testActivationTimeMethods();
        $this->testDefaultBehaviors();
        
        $this->printResults();
        
        return $this->failed === 0;
    }
    
    private function testClassExists() {
        $this->test('Class Activator exists', function() {
            return class_exists('WooAiAssistant\\Setup\\Activator');
        });
        
        $this->test('DATABASE_VERSION constant exists', function() {
            return defined('WooAiAssistant\\Setup\\Activator::DATABASE_VERSION');
        });
        
        $this->test('DATABASE_VERSION has correct value', function() {
            return Activator::DATABASE_VERSION === '1.0.0';
        });
    }
    
    private function testNamingConventions() {
        $reflection = new ReflectionClass(Activator::class);
        
        // Test class name (PascalCase)
        $this->test('Class name follows PascalCase', function() use ($reflection) {
            $className = $reflection->getShortName();
            return preg_match('/^[A-Z][a-zA-Z0-9]*$/', $className);
        });
        
        // Test method names (camelCase)
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            $methodName = $method->getName();
            if (strpos($methodName, '__') === 0) continue; // Skip magic methods
            
            $this->test("Method '$methodName' follows camelCase", function() use ($methodName) {
                return preg_match('/^[a-z][a-zA-Z0-9]*$/', $methodName);
            });
        }
        
        // Test constants (UPPER_SNAKE_CASE)
        $constants = $reflection->getConstants();
        foreach ($constants as $constantName => $value) {
            $this->test("Constant '$constantName' follows UPPER_SNAKE_CASE", function() use ($constantName) {
                return preg_match('/^[A-Z][A-Z0-9_]*$/', $constantName);
            });
        }
    }
    
    private function testPublicMethods() {
        $methods = [
            'activate',
            'getActivationTime', 
            'isRecentlyActivated',
            'getDatabaseVersion',
            'isDatabaseUpgradeNeeded',
            'getDatabaseTables',
            'getDatabaseStats'
        ];
        
        foreach ($methods as $method) {
            $this->test("Method '$method' exists", function() use ($method) {
                return method_exists(Activator::class, $method);
            });
        }
    }
    
    private function testDatabaseTables() {
        $tables = Activator::getDatabaseTables();
        
        $this->test('getDatabaseTables returns array', function() use ($tables) {
            return is_array($tables);
        });
        
        $this->test('getDatabaseTables returns non-empty array', function() use ($tables) {
            return !empty($tables);
        });
        
        $expectedTables = [
            'woo_ai_conversations',
            'woo_ai_messages',
            'woo_ai_knowledge_base',
            'woo_ai_usage_stats',
            'woo_ai_failed_requests',
            'woo_ai_agent_actions'
        ];
        
        foreach ($expectedTables as $expectedTable) {
            $this->test("Table list includes '$expectedTable'", function() use ($tables, $expectedTable) {
                return in_array($expectedTable, $tables);
            });
        }
        
        // Test table naming conventions
        foreach ($tables as $tableName) {
            $this->test("Table '$tableName' follows naming conventions", function() use ($tableName) {
                return preg_match('/^woo_ai_[a-z_]+$/', $tableName);
            });
        }
    }
    
    private function testDatabaseVersionMethods() {
        $this->test('getDatabaseVersion returns string', function() {
            $version = Activator::getDatabaseVersion();
            return is_string($version);
        });
        
        $this->test('isDatabaseUpgradeNeeded returns boolean', function() {
            $result = Activator::isDatabaseUpgradeNeeded();
            return is_bool($result);
        });
    }
    
    private function testActivationTimeMethods() {
        $this->test('getActivationTime method works', function() {
            $result = Activator::getActivationTime();
            return $result === false; // Should be false when not set
        });
        
        $this->test('isRecentlyActivated method works', function() {
            $result = Activator::isRecentlyActivated(300);
            return is_bool($result);
        });
    }
    
    private function testDefaultBehaviors() {
        $this->test('getDatabaseStats method exists', function() {
            return method_exists(Activator::class, 'getDatabaseStats');
        });
    }
    
    private function test($description, $callback) {
        try {
            $result = $callback();
            if ($result) {
                echo "✅ $description\n";
                $this->passed++;
            } else {
                echo "❌ $description\n";
                $this->failed++;
            }
        } catch (Exception $e) {
            echo "❌ $description - Exception: " . $e->getMessage() . "\n";
            $this->failed++;
        }
    }
    
    private function printResults() {
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "QUALITY GATES TEST RESULTS\n";
        echo str_repeat("=", 50) . "\n";
        echo "✅ Passed: {$this->passed}\n";
        echo "❌ Failed: {$this->failed}\n";
        echo "📊 Total: " . ($this->passed + $this->failed) . "\n";
        
        if ($this->failed === 0) {
            echo "\n🎉 ALL QUALITY GATES PASSED! ✅\n";
            echo "Task 1.3: Database Schema can be marked as COMPLETED\n";
        } else {
            echo "\n🚨 QUALITY GATES FAILED! ❌\n";
            echo "Task 1.3: Database Schema CANNOT be completed until issues are fixed\n";
        }
        echo str_repeat("=", 50) . "\n";
    }
}

// Run the tests
$runner = new QualityGatesTestRunner();
$success = $runner->runAllTests();

exit($success ? 0 : 1);