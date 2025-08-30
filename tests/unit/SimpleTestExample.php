<?php
/**
 * Simple Test Example
 *
 * Basic test example that doesn't require WordPress test suite.
 * This demonstrates the testing setup and can be run immediately.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Unit
 * @since 1.0.0
 * @author Claude Code Assistant
 */

namespace WooAiAssistant\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Class SimpleTestExample
 *
 * Basic test example for immediate verification of testing setup.
 *
 * @since 1.0.0
 */
class SimpleTestExample extends TestCase
{
    /**
     * Test that PHPUnit is working
     *
     * @return void
     */
    public function test_phpunit_should_be_working(): void
    {
        $this->assertTrue(true, 'PHPUnit should be working');
        $this->assertEquals(2, 1 + 1, 'Basic math should work');
    }

    /**
     * Test that testing constants are defined
     *
     * @return void
     */
    public function test_testing_constants_should_be_defined(): void
    {
        $this->assertTrue(defined('WOO_AI_ASSISTANT_TESTING'), 'Testing constant should be defined');
        $this->assertTrue(WOO_AI_ASSISTANT_TESTING, 'Testing mode should be active');
    }

    /**
     * Test plugin directory constant
     *
     * @return void
     */
    public function test_plugin_directory_should_be_defined(): void
    {
        $this->assertTrue(defined('WOO_AI_ASSISTANT_PLUGIN_DIR'), 'Plugin directory constant should be defined');
        $this->assertDirectoryExists(WOO_AI_ASSISTANT_PLUGIN_DIR, 'Plugin directory should exist');
    }

    /**
     * Test plugin file constant
     *
     * @return void
     */
    public function test_plugin_file_should_exist(): void
    {
        $this->assertTrue(defined('WOO_AI_ASSISTANT_PLUGIN_FILE'), 'Plugin file constant should be defined');
        $this->assertFileExists(WOO_AI_ASSISTANT_PLUGIN_FILE, 'Plugin main file should exist');
    }

    /**
     * Test that fixture files exist
     *
     * @return void
     */
    public function test_fixture_files_should_exist(): void
    {
        $fixturesDir = WOO_AI_ASSISTANT_PLUGIN_DIR . '/tests/fixtures';
        
        $this->assertDirectoryExists($fixturesDir, 'Fixtures directory should exist');
        
        $fixtureFiles = [
            'sample-products.json',
            'sample-users.json', 
            'plugin-configurations.json'
        ];
        
        foreach ($fixtureFiles as $file) {
            $filePath = $fixturesDir . '/' . $file;
            $this->assertFileExists($filePath, "Fixture file should exist: {$file}");
        }
    }

    /**
     * Test JSON fixtures are valid
     *
     * @return void
     */
    public function test_fixture_json_should_be_valid(): void
    {
        $fixturesDir = WOO_AI_ASSISTANT_PLUGIN_DIR . '/tests/fixtures';
        
        $jsonFiles = [
            'sample-products.json',
            'sample-users.json',
            'plugin-configurations.json'
        ];
        
        foreach ($jsonFiles as $jsonFile) {
            $filePath = $fixturesDir . '/' . $jsonFile;
            $content = file_get_contents($filePath);
            
            $this->assertIsString($content, "Should be able to read fixture: {$jsonFile}");
            
            $data = json_decode($content, true);
            $this->assertNotNull($data, "JSON should be valid in fixture: {$jsonFile}");
            $this->assertEquals(JSON_ERROR_NONE, json_last_error(), "JSON should have no errors in fixture: {$jsonFile}");
        }
    }

    /**
     * Test test directory structure exists
     *
     * @return void
     */
    public function test_directory_structure_should_exist(): void
    {
        $baseDir = WOO_AI_ASSISTANT_PLUGIN_DIR;
        
        $requiredDirs = [
            '/tests',
            '/tests/unit',
            '/tests/integration',
            '/tests/fixtures',
            '/tests/logs',
            '/tests/tmp',
            '/tests/unit/Common',
            '/tests/unit/Config'
        ];
        
        foreach ($requiredDirs as $dir) {
            $fullPath = $baseDir . $dir;
            $this->assertDirectoryExists($fullPath, "Required directory should exist: {$dir}");
        }
    }

    /**
     * Test that key test files exist
     *
     * @return void
     */
    public function test_key_test_files_should_exist(): void
    {
        $baseDir = WOO_AI_ASSISTANT_PLUGIN_DIR;
        
        $testFiles = [
            '/tests/bootstrap.php',
            '/tests/unit/WooAiBaseTestCase.php',
            '/tests/integration/WooAiIntegrationTestCase.php',
            '/tests/fixtures/FixtureLoader.php',
            '/phpunit.xml'
        ];
        
        foreach ($testFiles as $file) {
            $fullPath = $baseDir . $file;
            $this->assertFileExists($fullPath, "Test file should exist: {$file}");
        }
    }

    /**
     * Test composer configuration includes test dependencies
     *
     * @return void
     */
    public function test_composer_should_have_test_dependencies(): void
    {
        $composerFile = WOO_AI_ASSISTANT_PLUGIN_DIR . '/composer.json';
        $this->assertFileExists($composerFile, 'composer.json should exist');
        
        $content = file_get_contents($composerFile);
        $composer = json_decode($content, true);
        
        $this->assertArrayHasKey('require-dev', $composer, 'composer.json should have require-dev section');
        
        $requiredDevPackages = [
            'phpunit/phpunit',
            'mockery/mockery',
            'squizlabs/php_codesniffer'
        ];
        
        foreach ($requiredDevPackages as $package) {
            $this->assertArrayHasKey($package, $composer['require-dev'], "Should have dev dependency: {$package}");
        }
    }

    /**
     * Test autoloader configuration
     *
     * @return void
     */
    public function test_autoloader_should_be_configured(): void
    {
        $composerFile = WOO_AI_ASSISTANT_PLUGIN_DIR . '/composer.json';
        $content = file_get_contents($composerFile);
        $composer = json_decode($content, true);
        
        $this->assertArrayHasKey('autoload', $composer, 'Should have autoload section');
        $this->assertArrayHasKey('autoload-dev', $composer, 'Should have autoload-dev section');
        
        $this->assertArrayHasKey('psr-4', $composer['autoload'], 'Should use PSR-4 autoloading');
        $this->assertArrayHasKey('psr-4', $composer['autoload-dev'], 'Should use PSR-4 for dev autoloading');
        
        $this->assertArrayHasKey('WooAiAssistant\\', $composer['autoload']['psr-4'], 'Should have plugin namespace');
        $this->assertArrayHasKey('WooAiAssistant\\Tests\\', $composer['autoload-dev']['psr-4'], 'Should have test namespace');
    }

    /**
     * Test memory and performance constraints
     *
     * @return void
     */
    public function test_performance_setup_should_be_reasonable(): void
    {
        $startMemory = memory_get_usage();
        $startTime = microtime(true);
        
        // Simulate some test operations
        for ($i = 0; $i < 1000; $i++) {
            $data = ['test' => 'data', 'number' => $i];
            $json = json_encode($data);
            $decoded = json_decode($json, true);
            $this->assertEquals($data, $decoded);
        }
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        
        $executionTime = $endTime - $startTime;
        $memoryUsage = $endMemory - $startMemory;
        
        $this->assertLessThan(2.0, $executionTime, 'Simple operations should be fast');
        $this->assertLessThan(1048576, $memoryUsage, 'Memory usage should be reasonable'); // < 1MB
    }
}