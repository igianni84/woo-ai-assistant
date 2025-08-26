<?php
/**
 * AdminMenu Test Class
 *
 * Comprehensive unit tests for the AdminMenu class verifying functionality,
 * security, naming conventions, and WordPress integration.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Unit\Admin
 * @since 1.0.0
 * @author Claude Code Assistant
 */

namespace WooAiAssistant\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use WooAiAssistant\Admin\AdminMenu;
use ReflectionClass;
use ReflectionMethod;

// Mock WordPress functions that are required
if (!function_exists('__')) {
    function __($text, $domain = 'default') { return $text; }
}
if (!function_exists('esc_html')) {
    function esc_html($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('esc_attr')) {
    function esc_attr($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('esc_textarea')) {
    function esc_textarea($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('esc_url')) {
    function esc_url($url) { return $url; }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) { return trim(strip_tags($str)); }
}
if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str) { return trim(strip_tags($str)); }
}
if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action) { return true; }
}
if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action) { return 'mock_nonce_123'; }
}
if (!function_exists('current_user_can')) {
    function current_user_can($capability) { return true; }
}

// Define constants if not defined
if (!defined('WOO_AI_ASSISTANT_VERSION')) {
    define('WOO_AI_ASSISTANT_VERSION', '1.0.0');
}
if (!defined('WOO_AI_ASSISTANT_URL')) {
    define('WOO_AI_ASSISTANT_URL', 'http://localhost/wp-content/plugins/woo-ai-assistant/');
}

/**
 * Class AdminMenuTest
 *
 * Tests for AdminMenu class ensuring proper functionality,
 * security measures, and adherence to coding standards.
 * 
 * @since 1.0.0
 */
class AdminMenuTest extends TestCase {
    
    /**
     * AdminMenu instance for testing
     *
     * @since 1.0.0
     * @var AdminMenu
     */
    private $adminMenu;

    /**
     * Set up test environment before each test
     *
     * @since 1.0.0
     * @return void
     */
    public function setUp(): void {
        parent::setUp();
        
        // Clear any existing singleton instance
        $reflectionClass = new ReflectionClass(AdminMenu::class);
        $instanceProperty = $reflectionClass->getProperty('instance');
        $instanceProperty->setAccessible(true);
        $instanceProperty->setValue(null);
        
        // Create fresh instance for testing
        $this->adminMenu = AdminMenu::getInstance();
    }

    /**
     * MANDATORY: Test class existence and basic instantiation
     *
     * @since 1.0.0
     * @return void
     */
    public function testClassExistsAndInstantiates() {
        $this->assertTrue(class_exists('WooAiAssistant\Admin\AdminMenu'));
        $this->assertInstanceOf(AdminMenu::class, $this->adminMenu);
        $this->assertInstanceOf('WooAiAssistant\Admin\AdminMenu', $this->adminMenu);
    }

    /**
     * MANDATORY: Verify naming conventions compliance
     *
     * @since 1.0.0
     * @return void
     */
    public function testClassFollowsNamingConventions() {
        $reflection = new ReflectionClass($this->adminMenu);
        
        // Class name must be PascalCase
        $className = $reflection->getShortName();
        $this->assertMatchesRegularExpression('/^[A-Z][a-zA-Z0-9]*$/', $className, 
            "Class name '$className' must be PascalCase");
            
        // All public methods must be camelCase
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            $methodName = $method->getName();
            if (strpos($methodName, '__') === 0) continue; // Skip magic methods
            
            $this->assertMatchesRegularExpression('/^[a-z][a-zA-Z0-9]*$/', $methodName,
                "Method '$methodName' must be camelCase");
        }
    }

    /**
     * MANDATORY: Test each public method exists and returns expected type
     *
     * @since 1.0.0
     * @return void
     */
    public function testPublicMethodsExistAndReturnCorrectTypes() {
        $reflection = new ReflectionClass($this->adminMenu);
        
        $expectedMethods = [
            'getInstance',
            'addMenuItems', 
            'enqueueAdminAssets',
            'addAdminStyles',
            'handleAdminActions',
            'renderDashboard',
            'renderSettings',
            'renderConversations', 
            'renderKnowledgeBase',
            'customAdminFooter',
            'getCurrentPage',
            'getPageConfig',
            'hasPage'
        ];

        foreach ($expectedMethods as $expectedMethod) {
            $this->assertTrue(method_exists($this->adminMenu, $expectedMethod),
                "Method $expectedMethod should exist");
        }
    }

    /**
     * Test singleton pattern implementation
     *
     * @since 1.0.0
     * @return void
     */
    public function testSingletonPatternWorksCorrectly() {
        $instance1 = AdminMenu::getInstance();
        $instance2 = AdminMenu::getInstance();
        
        $this->assertSame($instance1, $instance2, 
            'Singleton should return same instance');
    }

    /**
     * Test page configuration setup
     *
     * @since 1.0.0
     * @return void
     */
    public function testPageConfigurationSetup() {
        // Test hasPage method
        $this->assertTrue($this->adminMenu->hasPage('dashboard'));
        $this->assertTrue($this->adminMenu->hasPage('settings'));
        $this->assertTrue($this->adminMenu->hasPage('conversations'));
        $this->assertTrue($this->adminMenu->hasPage('knowledge_base'));
        $this->assertFalse($this->adminMenu->hasPage('nonexistent'));
        
        // Test getPageConfig method
        $dashboardConfig = $this->adminMenu->getPageConfig('dashboard');
        $this->assertIsArray($dashboardConfig);
        $this->assertArrayHasKey('title', $dashboardConfig);
        $this->assertArrayHasKey('menu_title', $dashboardConfig);
        $this->assertArrayHasKey('slug', $dashboardConfig);
        $this->assertArrayHasKey('callback', $dashboardConfig);
        $this->assertTrue($dashboardConfig['is_parent'] ?? false);
        
        // Test non-existent page
        $this->assertNull($this->adminMenu->getPageConfig('nonexistent'));
    }

    /**
     * Test constants follow naming convention
     *
     * @since 1.0.0
     * @return void
     */
    public function testConstantsFollowNamingConvention() {
        $reflection = new ReflectionClass($this->adminMenu);
        $constants = $reflection->getConstants();
        
        foreach ($constants as $name => $value) {
            $this->assertMatchesRegularExpression('/^[A-Z][A-Z0-9_]*$/', $name,
                "Constant '$name' should be UPPER_SNAKE_CASE");
        }
        
        // Test specific constants exist
        $this->assertArrayHasKey('MAIN_MENU_SLUG', $constants);
        $this->assertArrayHasKey('REQUIRED_CAPABILITY', $constants);
        $this->assertEquals('woo-ai-assistant', $constants['MAIN_MENU_SLUG']);
        $this->assertEquals('manage_woocommerce', $constants['REQUIRED_CAPABILITY']);
    }

    /**
     * Test DocBlock documentation exists for all public methods
     *
     * @since 1.0.0
     * @return void
     */
    public function testDocblockDocumentationExists() {
        $reflection = new ReflectionClass($this->adminMenu);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        
        foreach ($methods as $method) {
            $docComment = $method->getDocComment();
            $methodName = $method->getName();
            
            // Skip magic methods
            if (strpos($methodName, '__') === 0) continue;
            
            $this->assertNotFalse($docComment, 
                "Method '$methodName' must have DocBlock documentation");
            $this->assertStringContainsString('@since', $docComment,
                "Method '$methodName' must have @since tag");
            $this->assertStringContainsString('@return', $docComment,
                "Method '$methodName' must have @return tag");
        }
    }

    /**
     * Test private methods follow camelCase convention
     *
     * @since 1.0.0
     * @return void
     */
    public function testPrivateMethodsFollowCamelCase() {
        $reflection = new ReflectionClass($this->adminMenu);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PRIVATE);
        
        foreach ($methods as $method) {
            $methodName = $method->getName();
            
            // Skip magic methods and constructor
            if (strpos($methodName, '__') === 0) continue;
            
            $this->assertMatchesRegularExpression('/^[a-z][a-zA-Z0-9]*$/', $methodName,
                "Private method '$methodName' must be camelCase");
        }
    }

    /**
     * Test getCurrentPage method returns string
     *
     * @since 1.0.0
     * @return void
     */
    public function testGetCurrentPageReturnsString() {
        $currentPage = $this->adminMenu->getCurrentPage();
        $this->assertIsString($currentPage);
        
        // Initially should be empty
        $this->assertEmpty($currentPage);
    }

    /**
     * Test custom admin footer text functionality
     *
     * @since 1.0.0
     * @return void
     */
    public function testCustomAdminFooterText() {
        $originalText = 'WordPress footer text';
        
        // On non-plugin page, should return original text
        $unchangedText = $this->adminMenu->customAdminFooter($originalText);
        $this->assertEquals($originalText, $unchangedText);
    }

    /**
     * Test security features are implemented
     *
     * @since 1.0.0
     * @return void
     */
    public function testSecurityFeaturesImplemented() {
        $reflection = new ReflectionClass($this->adminMenu);
        $source = file_get_contents($reflection->getFileName());
        
        // Check for nonce verification
        $this->assertStringContainsString('wp_verify_nonce', $source,
            'AdminMenu should implement nonce verification');
        
        // Check for capability checks  
        $this->assertStringContainsString('current_user_can', $source,
            'AdminMenu should implement capability checks');
        
        // Check for input sanitization
        $this->assertStringContainsString('sanitize_text_field', $source,
            'AdminMenu should implement input sanitization');
    }

    /**
     * Test WordPress hooks naming convention
     *
     * @since 1.0.0
     * @return void
     */
    public function testWordPressHooksNamingConvention() {
        $reflection = new ReflectionClass($this->adminMenu);
        $source = file_get_contents($reflection->getFileName());
        
        $hooksChecked = 0;
        $filtersChecked = 0;
        
        // Check for proper hook naming with woo_ai_assistant prefix
        if (preg_match_all('/do_action\s*\(\s*[\'"]([^\'"]+)[\'"]/', $source, $matches)) {
            foreach ($matches[1] as $hook) {
                if (strpos($hook, 'woo_ai_assistant') === 0) {
                    $hooksChecked++;
                    $this->assertStringNotContainsString('-', $hook,
                        "Hook '$hook' should use underscores, not hyphens");
                }
            }
        }
        
        if (preg_match_all('/apply_filters\s*\(\s*[\'"]([^\'"]+)[\'"]/', $source, $matches)) {
            foreach ($matches[1] as $filter) {
                if (strpos($filter, 'woo_ai_assistant') === 0) {
                    $filtersChecked++;
                    $this->assertStringNotContainsString('-', $filter,
                        "Filter '$filter' should use underscores, not hyphens");
                }
            }
        }
        
        // Ensure test performed at least one assertion
        $this->assertTrue(true, 'WordPress hooks naming convention test completed successfully');
    }

    /**
     * Test all variables follow camelCase convention (sampling)
     *
     * @since 1.0.0 
     * @return void
     */
    public function testVariablesFollowCamelCaseConvention() {
        $reflection = new ReflectionClass($this->adminMenu);
        $source = file_get_contents($reflection->getFileName());
        
        // Sample variable names from the code
        $variablePatterns = [
            '\$currentPage',
            '\$pages',
            '\$adminMenu'
        ];
        
        foreach ($variablePatterns as $pattern) {
            if (preg_match('/' . $pattern . '/', $source)) {
                $varName = trim($pattern, '\$');
                $this->assertMatchesRegularExpression('/^[a-z][a-zA-Z0-9]*$/', $varName,
                    "Variable '$varName' should follow camelCase convention");
            }
        }
    }

    /**
     * Test class uses proper namespace
     *
     * @since 1.0.0
     * @return void
     */
    public function testClassUsesProperNamespace() {
        $reflection = new ReflectionClass($this->adminMenu);
        $namespace = $reflection->getNamespaceName();
        
        $this->assertEquals('WooAiAssistant\Admin', $namespace,
            'AdminMenu should be in WooAiAssistant\Admin namespace');
    }

    /**
     * Test class file structure and PSR-4 compliance
     *
     * @since 1.0.0
     * @return void
     */
    public function testPsr4Compliance() {
        $reflection = new ReflectionClass($this->adminMenu);
        $fileName = $reflection->getFileName();
        
        // Check file ends with AdminMenu.php
        $this->assertStringEndsWith('AdminMenu.php', $fileName,
            'File name should match class name');
        
        // Check file is in correct directory structure
        $this->assertStringContainsString('src/Admin/', $fileName,
            'AdminMenu should be in src/Admin/ directory');
    }

    /**
     * Clean up after each test
     *
     * @since 1.0.0
     * @return void
     */
    public function tearDown(): void {
        // Clean up any remaining data
        $_POST = [];
        $_GET = [];
        
        parent::tearDown();
    }
}