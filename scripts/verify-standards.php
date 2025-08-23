<?php
/**
 * Standards Verification Script
 * 
 * Automated verification of PHP coding standards for Woo AI Assistant Plugin.
 * This script must be run before completing any task to ensure compliance.
 * 
 * @package WooAiAssistant
 * @subpackage Scripts
 * @since 1.0.0
 * @author Claude Code Assistant
 */

// Prevent direct access
if (!defined('ABSPATH') && php_sapi_name() === 'cli') {
    // Allow CLI access for development
    define('WOO_AI_ASSISTANT_VERIFY_MODE', true);
}

/**
 * Standards Verification Class
 */
class StandardsVerifier {
    
    private $errors = [];
    private $warnings = [];
    private $sourceDir;
    
    public function __construct($sourceDir = null) {
        $this->sourceDir = $sourceDir ?: dirname(__DIR__) . '/src';
    }
    
    /**
     * Run all verification checks
     * 
     * @return bool True if all checks pass
     */
    public function runAllChecks(): bool {
        echo "🚀 Running Standards Verification...\n\n";
        
        $this->verifyFileExists();
        $this->verifyClassNaming();
        $this->verifyMethodNaming();
        $this->verifyVariableNaming();
        $this->verifyConstantNaming();
        $this->verifyDatabaseNaming();
        $this->verifyHookNaming();
        $this->verifyDocBlocks();
        
        return $this->displayResults();
    }
    
    /**
     * Verify all referenced files exist
     */
    private function verifyFileExists(): void {
        echo "📁 Checking file existence...\n";
        
        if (!is_dir($this->sourceDir)) {
            $this->addError("Source directory does not exist: {$this->sourceDir}");
            return;
        }
        
        $files = $this->getAllPHPFiles();
        
        foreach ($files as $file) {
            if (!file_exists($file) || !is_readable($file)) {
                $this->addError("File does not exist or is not readable: $file");
                continue;
            }
            
            $this->verifyRequireIncludes($file);
        }
        
        echo "✅ File existence check completed\n\n";
    }
    
    /**
     * Verify class naming follows PascalCase
     */
    private function verifyClassNaming(): void {
        echo "🏗️  Checking class naming conventions...\n";
        
        $files = $this->getAllPHPFiles();
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            // More specific regex to match actual class declarations
            preg_match_all('/(?:^|\s)class\s+([A-Za-z_][A-Za-z0-9_]*)\s*(?:extends|implements|\{)/m', $content, $matches);
            
            foreach ($matches[1] as $className) {
                if (!preg_match('/^[A-Z][a-zA-Z0-9]*$/', $className)) {
                    $this->addError("Class '$className' in $file does not follow PascalCase convention");
                }
            }
        }
        
        echo "✅ Class naming check completed\n\n";
    }
    
    /**
     * Verify method naming follows camelCase
     */
    private function verifyMethodNaming(): void {
        echo "⚙️  Checking method naming conventions...\n";
        
        $files = $this->getAllPHPFiles();
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            preg_match_all('/(?:public|private|protected)\s+function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/m', $content, $matches);
            
            foreach ($matches[1] as $methodName) {
                // Skip magic methods and constructors
                if (strpos($methodName, '__') === 0) continue;
                
                if (!preg_match('/^[a-z][a-zA-Z0-9]*$/', $methodName)) {
                    $this->addError("Method '$methodName' in $file does not follow camelCase convention");
                }
            }
        }
        
        echo "✅ Method naming check completed\n\n";
    }
    
    /**
     * Verify variable naming follows camelCase
     */
    private function verifyVariableNaming(): void {
        echo "📝 Checking variable naming conventions...\n";
        
        $files = $this->getAllPHPFiles();
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            
            // Pattern to match snake_case variables but exclude valid WordPress/PHP patterns
            preg_match_all('/\$([a-z]+_[a-z_]+[a-z])\b/', $content, $matches);
            
            foreach ($matches[1] as $varName) {
                // Skip valid WordPress/database patterns
                $skipPatterns = [
                    '/^wp_/',           // WordPress globals like $wp_version
                    '/^wpdb$/',         // $wpdb global
                    '/.*_dir$/',        // Directory variables like upload_dir
                    '/.*_url$/',        // URL variables
                    '/.*_table.*/',     // Database table variables
                    '/charset_collate/', // WordPress db charset
                    '/.*_hooks?$/',     // Hook arrays
                    '/.*_options?$/',   // Options arrays
                    '/.*_stats?$/',     // Stats arrays
                    '/.*_time$/',       // Time variables
                    '/.*_count$/',      // Count variables
                ];
                
                $shouldSkip = false;
                foreach ($skipPatterns as $pattern) {
                    if (preg_match($pattern, $varName)) {
                        $shouldSkip = true;
                        break;
                    }
                }
                
                if (!$shouldSkip) {
                    $this->addWarning("Variable '\$$varName' in $file should use camelCase convention");
                }
            }
        }
        
        echo "✅ Variable naming check completed\n\n";
    }
    
    /**
     * Verify constant naming follows UPPER_SNAKE_CASE
     */
    private function verifyConstantNaming(): void {
        echo "🔧 Checking constant naming conventions...\n";
        
        $files = $this->getAllPHPFiles();
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            preg_match_all('/const\s+([A-Z_][A-Z0-9_]*)\s*=/m', $content, $matches);
            
            foreach ($matches[1] as $constantName) {
                if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $constantName)) {
                    $this->addError("Constant '$constantName' in $file does not follow UPPER_SNAKE_CASE convention");
                }
            }
        }
        
        echo "✅ Constant naming check completed\n\n";
    }
    
    /**
     * Verify database table and column naming
     */
    private function verifyDatabaseNaming(): void {
        echo "🗄️  Checking database naming conventions...\n";
        
        $files = $this->getAllPHPFiles();
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            
            // Check for table name patterns
            if (preg_match_all('/[\'"]([a-z_]+woo_ai_[a-z_]+)[\'"]/m', $content, $matches)) {
                foreach ($matches[1] as $tableName) {
                    if (!preg_match('/^[a-z_]+woo_ai_[a-z_]+$/', $tableName)) {
                        $this->addError("Table name '$tableName' in $file should use woo_ai_ prefix with snake_case");
                    }
                }
            }
        }
        
        echo "✅ Database naming check completed\n\n";
    }
    
    /**
     * Verify WordPress hook naming
     */
    private function verifyHookNaming(): void {
        echo "🔗 Checking WordPress hook naming conventions...\n";
        
        $files = $this->getAllPHPFiles();
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            
            // Check for hook patterns
            preg_match_all('/(do_action|apply_filters)\s*\(\s*[\'"]([^\'"]+)[\'"]/m', $content, $matches);
            
            foreach ($matches[2] as $hookName) {
                if (strpos($hookName, 'woo_ai_assistant') !== 0) {
                    $this->addWarning("Hook '$hookName' in $file should start with 'woo_ai_assistant_'");
                }
                
                if (strpos($hookName, '-') !== false) {
                    $this->addError("Hook '$hookName' in $file should use underscores, not hyphens");
                }
            }
        }
        
        echo "✅ Hook naming check completed\n\n";
    }
    
    /**
     * Verify DocBlock documentation
     */
    private function verifyDocBlocks(): void {
        echo "📚 Checking DocBlock documentation...\n";
        
        $files = $this->getAllPHPFiles();
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            
            // Check for public methods without DocBlocks
            preg_match_all('/public\s+function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\([^{]*\{/m', $content, $matches, PREG_OFFSET_CAPTURE);
            
            foreach ($matches[1] as $match) {
                $methodName = $match[0];
                $position = $match[1];
                
                // Skip magic methods
                if (strpos($methodName, '__') === 0) continue;
                
                // Check if there's a DocBlock before this method
                $beforeMethod = substr($content, 0, $position);
                if (!preg_match('/\/\*\*.*?\*\//s', $beforeMethod)) {
                    $this->addWarning("Public method '$methodName' in $file is missing DocBlock documentation");
                }
            }
        }
        
        echo "✅ DocBlock documentation check completed\n\n";
    }
    
    /**
     * Verify require/include file paths
     */
    private function verifyRequireIncludes(string $file): void {
        $content = file_get_contents($file);
        preg_match_all('/(require|include)(?:_once)?\s*\(?[\'"](.*?)[\'"]/', $content, $matches);
        
        foreach ($matches[2] as $includePath) {
            if (!file_exists($includePath) && !file_exists(dirname($file) . '/' . $includePath)) {
                $this->addError("Required file '$includePath' not found (referenced in $file)");
            }
        }
    }
    
    /**
     * Get all PHP files in source directory
     */
    private function getAllPHPFiles(): array {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->sourceDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        
        return $files;
    }
    
    /**
     * Add error to the collection
     */
    private function addError(string $message): void {
        $this->errors[] = $message;
    }
    
    /**
     * Add warning to the collection
     */
    private function addWarning(string $message): void {
        $this->warnings[] = $message;
    }
    
    /**
     * Display verification results
     */
    private function displayResults(): bool {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "📊 STANDARDS VERIFICATION RESULTS\n";
        echo str_repeat("=", 60) . "\n\n";
        
        if (empty($this->errors) && empty($this->warnings)) {
            echo "🎉 ALL CHECKS PASSED!\n";
            echo "✅ No errors found\n";
            echo "✅ No warnings found\n";
            echo "\n🚀 Code is ready for task completion.\n\n";
            return true;
        }
        
        if (!empty($this->errors)) {
            echo "❌ ERRORS FOUND (" . count($this->errors) . "):\n";
            foreach ($this->errors as $i => $error) {
                echo sprintf("%2d. %s\n", $i + 1, $error);
            }
            echo "\n";
        }
        
        if (!empty($this->warnings)) {
            echo "⚠️  WARNINGS FOUND (" . count($this->warnings) . "):\n";
            foreach ($this->warnings as $i => $warning) {
                echo sprintf("%2d. %s\n", $i + 1, $warning);
            }
            echo "\n";
        }
        
        if (!empty($this->errors)) {
            echo "🚫 VERIFICATION FAILED - Fix errors before proceeding\n\n";
            return false;
        }
        
        echo "⚠️  VERIFICATION PASSED WITH WARNINGS - Review warnings before proceeding\n\n";
        return true;
    }
}

// Run verification if called directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $verifier = new StandardsVerifier();
    $success = $verifier->runAllChecks();
    exit($success ? 0 : 1);
}