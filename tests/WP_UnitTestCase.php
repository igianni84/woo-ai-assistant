<?php
/**
 * Base WP_UnitTestCase Class for WordPress Plugin Testing
 *
 * Provides a WordPress-compatible testing environment for plugin unit tests.
 *
 * @package WooAiAssistant\Tests
 * @since 1.0.0
 */

namespace WooAiAssistant\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Class WP_UnitTestCase
 * 
 * Base test case class that provides WordPress-specific testing functionality
 * and mock factory for creating test data.
 * 
 * @since 1.0.0
 */
abstract class WP_UnitTestCase extends TestCase {

    /**
     * WordPress factory for creating test data
     *
     * @since 1.0.0
     * @var object
     */
    protected $factory;

    /**
     * Set up test environment
     *
     * @since 1.0.0
     * @return void
     */
    public function setUp(): void {
        parent::setUp();
        
        $this->factory = $this->createFactory();
        
        // Set up WordPress globals and environment
        $this->setUpWordPressEnvironment();
    }

    /**
     * Tear down test environment
     *
     * @since 1.0.0
     * @return void
     */
    public function tearDown(): void {
        // Clean up any test data
        $this->cleanUpTestData();
        
        parent::tearDown();
    }

    /**
     * Create mock factory for test data generation
     *
     * @since 1.0.0
     * @return object Mock factory object
     */
    private function createFactory(): object {
        return new class {
            public $post;
            public $category;
            public $tag;
            public $user;
            
            public function __construct() {
                $this->post = new class {
                    public function create($args = []) {
                        $defaults = [
                            'post_title' => 'Test Post',
                            'post_content' => 'Test content',
                            'post_status' => 'publish',
                            'post_type' => 'post'
                        ];
                        
                        $post_data = array_merge($defaults, $args);
                        
                        // Generate a mock post ID
                        return rand(1000, 9999);
                    }
                };
                
                $this->category = new class {
                    public function create($args = []) {
                        $defaults = [
                            'name' => 'Test Category',
                            'slug' => 'test-category'
                        ];
                        
                        $cat_data = array_merge($defaults, $args);
                        
                        return rand(1, 100);
                    }
                };
                
                $this->tag = new class {
                    public function create($args = []) {
                        $defaults = [
                            'name' => 'Test Tag',
                            'slug' => 'test-tag'
                        ];
                        
                        $tag_data = array_merge($defaults, $args);
                        
                        return rand(1, 100);
                    }
                };
                
                $this->user = new class {
                    public function create($args = []) {
                        $defaults = [
                            'user_login' => 'testuser',
                            'user_email' => 'test@example.com',
                            'role' => 'subscriber'
                        ];
                        
                        $user_data = array_merge($defaults, $args);
                        
                        return rand(1, 1000);
                    }
                };
            }
        };
    }

    /**
     * Set up WordPress environment for testing
     *
     * @since 1.0.0
     * @return void
     */
    private function setUpWordPressEnvironment(): void {
        // WordPress environment is set up in bootstrap.php
        // This method can be used for test-specific environment setup
    }

    /**
     * Clean up test data after each test
     *
     * @since 1.0.0
     * @return void
     */
    private function cleanUpTestData(): void {
        // Clean up any test-specific data
        // This can be overridden in child classes for specific cleanup
    }

    /**
     * Assert that a WordPress hook exists
     *
     * @since 1.0.0
     * @param string $hook Hook name to check
     * @param callable|null $callback Optional callback to check for
     * @return void
     */
    protected function assertHookExists(string $hook, $callback = null): void {
        if ($callback) {
            $this->assertTrue(has_action($hook, $callback), "Hook '{$hook}' with callback should exist");
        } else {
            $this->assertTrue(has_action($hook), "Hook '{$hook}' should exist");
        }
    }

    /**
     * Assert that a class follows naming conventions
     *
     * @since 1.0.0
     * @param string $className Class name to check
     * @return void
     */
    protected function assertClassFollowsNamingConventions(string $className): void {
        $this->assertMatchesRegularExpression(
            '/^[A-Z][a-zA-Z0-9]*$/', 
            $className, 
            "Class name '{$className}' must be PascalCase"
        );
    }

    /**
     * Assert that a method follows naming conventions
     *
     * @since 1.0.0
     * @param string $methodName Method name to check
     * @return void
     */
    protected function assertMethodFollowsNamingConventions(string $methodName): void {
        // Skip magic methods
        if (strpos($methodName, '__') === 0) {
            return;
        }
        
        $this->assertMatchesRegularExpression(
            '/^[a-z][a-zA-Z0-9]*$/', 
            $methodName,
            "Method '{$methodName}' must be camelCase"
        );
    }
}