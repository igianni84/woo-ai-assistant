<?php

/**
 * Development Configuration Class
 *
 * Handles development environment detection, loads API keys from .env file,
 * bypasses license validation in development mode, and enables debug features.
 *
 * @package WooAiAssistant
 * @subpackage Config
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Config;

use WooAiAssistant\Common\Traits\Singleton;
use WooAiAssistant\Common\Logger;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class DevelopmentConfig
 *
 * Manages development environment configuration and settings.
 *
 * @since 1.0.0
 */
class DevelopmentConfig
{
    use Singleton;

    /**
     * Environment variables cache
     *
     * @var array
     */
    private array $envVars = [];

    /**
     * Development environment indicators
     *
     * @var array
     */
    private array $devIndicators = [
        'localhost',
        '127.0.0.1',
        '.local',
        '.dev',
        '.test',
        'staging',
        'development'
    ];

    /**
     * Development environment status
     *
     * @var bool|null
     */
    private ?bool $isDevelopment = null;

    /**
     * Initialize development configuration
     *
     * @return void
     */
    protected function init(): void
    {
        $this->loadEnvironmentVariables();

        if ($this->isDevelopmentEnvironment()) {
            $this->enableDevelopmentFeatures();
        }

        Logger::debug('Development configuration initialized', [
            'is_development' => $this->isDevelopmentEnvironment(),
            'env_vars_loaded' => count($this->envVars)
        ]);
    }

    /**
     * Detect if we're running in a development environment
     *
     * @return bool True if in development environment, false otherwise
     */
    public function isDevelopmentEnvironment(): bool
    {
        if ($this->isDevelopment !== null) {
            return $this->isDevelopment;
        }

        // Check explicit development mode setting
        if ($this->getEnvironmentVariable('WOO_AI_DEVELOPMENT_MODE') === 'true') {
            $this->isDevelopment = true;
            return true;
        }

        // Check WordPress debug constants
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->isDevelopment = true;
            return true;
        }

        // Check server name indicators
        $serverName = strtolower($_SERVER['SERVER_NAME'] ?? '');
        $httpHost = strtolower($_SERVER['HTTP_HOST'] ?? '');

        foreach ($this->devIndicators as $indicator) {
            if (strpos($serverName, $indicator) !== false || strpos($httpHost, $indicator) !== false) {
                $this->isDevelopment = true;
                return true;
            }
        }

        // Check for development server ports
        $serverPort = $_SERVER['SERVER_PORT'] ?? '';
        $devPorts = ['3000', '8080', '8888', '8000', '4200', '5000'];

        if (in_array($serverPort, $devPorts, true)) {
            $this->isDevelopment = true;
            return true;
        }

        // Check for Docker environment
        if ($this->isDockerEnvironment()) {
            $this->isDevelopment = true;
            return true;
        }

        // Check for MAMP/XAMPP/WAMP
        if ($this->isLocalServerEnvironment()) {
            $this->isDevelopment = true;
            return true;
        }

        $this->isDevelopment = false;
        return false;
    }

    /**
     * Check if running in Docker environment
     *
     * @return bool True if Docker environment detected
     */
    public function isDockerEnvironment(): bool
    {
        // Check for Docker-specific environment variables
        $dockerIndicators = [
            'DOCKER_COMPOSE_PROJECT_NAME',
            'COMPOSE_PROJECT_NAME'
        ];

        foreach ($dockerIndicators as $indicator) {
            if (!empty($_ENV[$indicator]) || !empty($_SERVER[$indicator])) {
                return true;
            }
        }

        // Check for Docker-specific file systems
        if (file_exists('/.dockerenv')) {
            return true;
        }

        // Check for Docker hostname patterns
        $hostname = gethostname();
        if ($hostname && (strlen($hostname) === 12 || strpos($hostname, 'docker') !== false)) {
            return true;
        }

        return false;
    }

    /**
     * Check if running in local server environment (MAMP, XAMPP, WAMP)
     *
     * @return bool True if local server environment detected
     */
    public function isLocalServerEnvironment(): bool
    {
        $serverSoftware = strtolower($_SERVER['SERVER_SOFTWARE'] ?? '');

        $localServerIndicators = [
            'mamp',
            'xampp',
            'wamp',
            'laragon'
        ];

        foreach ($localServerIndicators as $indicator) {
            if (strpos($serverSoftware, $indicator) !== false) {
                return true;
            }
        }

        // Check common local development paths
        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $localPaths = [
            '/Applications/MAMP',
            '/xampp/',
            '/wamp/',
            '/laragon/'
        ];

        foreach ($localPaths as $path) {
            if (strpos($documentRoot, $path) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Load environment variables from .env file
     *
     * @return void
     */
    private function loadEnvironmentVariables(): void
    {
        $pluginPath = defined('WOO_AI_ASSISTANT_PATH') ? WOO_AI_ASSISTANT_PATH : plugin_dir_path(__FILE__);
        $envFile = $pluginPath . '.env';

        if (!file_exists($envFile)) {
            Logger::debug('No .env file found', ['path' => $envFile]);
            return;
        }

        try {
            $envContent = file_get_contents($envFile);
            if ($envContent === false) {
                Logger::warning('Failed to read .env file');
                return;
            }

            $this->parseEnvironmentFile($envContent);
            Logger::debug('.env file loaded successfully', [
                'variables_count' => count($this->envVars)
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to load .env file', [
                'error' => $e->getMessage(),
                'file' => $envFile
            ]);
        }
    }

    /**
     * Parse environment file content
     *
     * @param string $content Environment file content
     * @return void
     */
    private function parseEnvironmentFile(string $content): void
    {
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines and comments
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }

            // Parse key=value pairs
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Remove quotes from value
                if (
                    (strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
                    (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)
                ) {
                    $value = substr($value, 1, -1);
                }

                $this->envVars[$key] = $value;

                // Set environment variable if not already set
                if (!isset($_ENV[$key])) {
                    $_ENV[$key] = $value;
                    putenv("{$key}={$value}");
                }
            }
        }
    }

    /**
     * Get environment variable with fallbacks
     *
     * @param string $key Environment variable key
     * @param string $default Default value if not found
     * @return string Environment variable value or default
     */
    public function getEnvironmentVariable(string $key, string $default = ''): string
    {
        // Check cached env vars first
        if (isset($this->envVars[$key])) {
            return $this->envVars[$key];
        }

        // Check $_ENV
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }

        // Check $_SERVER
        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }

        // Check getenv()
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        return $default;
    }

    /**
     * Check if license validation should be bypassed in development
     *
     * @return bool True if license validation should be bypassed
     */
    public function shouldBypassLicenseValidation(): bool
    {
        if (!$this->isDevelopmentEnvironment()) {
            return false;
        }

        // Check if explicitly disabled
        if ($this->getEnvironmentVariable('WOO_AI_BYPASS_LICENSE_VALIDATION') === 'false') {
            return false;
        }

        return true;
    }

    /**
     * Get development license key
     *
     * @return string Development license key
     */
    public function getDevelopmentLicenseKey(): string
    {
        return $this->getEnvironmentVariable('WOO_AI_DEVELOPMENT_LICENSE_KEY', 'dev-license-12345');
    }

    /**
     * Check if debug features should be enabled
     *
     * @return bool True if debug features should be enabled
     */
    public function shouldEnableDebugFeatures(): bool
    {
        if (!$this->isDevelopmentEnvironment()) {
            return false;
        }

        return $this->getEnvironmentVariable('WOO_AI_DEBUG', 'true') === 'true';
    }

    /**
     * Check if enhanced debug logging should be enabled
     *
     * @return bool True if enhanced debug logging should be enabled
     */
    public function shouldEnableEnhancedLogging(): bool
    {
        if (!$this->isDevelopmentEnvironment()) {
            return false;
        }

        return $this->getEnvironmentVariable('WOO_AI_ENHANCED_DEBUG', 'false') === 'true';
    }

    /**
     * Get API key from environment variables
     *
     * @param string $service Service name (openrouter, openai, pinecone, etc.)
     * @return string API key or empty string if not found
     */
    public function getApiKey(string $service): string
    {
        if (!$this->isDevelopmentEnvironment()) {
            return '';
        }

        $keyMap = [
            'openrouter' => 'OPENROUTER_API_KEY',
            'openai' => 'OPENAI_API_KEY',
            'pinecone' => 'PINECONE_API_KEY',
            'google' => 'GOOGLE_API_KEY',
            'stripe_secret' => 'STRIPE_SECRET_KEY',
            'stripe_publishable' => 'STRIPE_PUBLISHABLE_KEY'
        ];

        $envKey = $keyMap[$service] ?? strtoupper($service) . '_API_KEY';
        return $this->getEnvironmentVariable($envKey);
    }

    /**
     * Get all development configuration
     *
     * @return array Development configuration array
     */
    public function getDevelopmentConfig(): array
    {
        if (!$this->isDevelopmentEnvironment()) {
            return [];
        }

        return [
            'is_development' => true,
            'bypass_license_validation' => $this->shouldBypassLicenseValidation(),
            'development_license_key' => $this->getDevelopmentLicenseKey(),
            'debug_enabled' => $this->shouldEnableDebugFeatures(),
            'enhanced_logging' => $this->shouldEnableEnhancedLogging(),
            'docker_environment' => $this->isDockerEnvironment(),
            'local_server_environment' => $this->isLocalServerEnvironment(),
            'api_keys_available' => [
                'openrouter' => !empty($this->getApiKey('openrouter')),
                'openai' => !empty($this->getApiKey('openai')),
                'pinecone' => !empty($this->getApiKey('pinecone')),
                'google' => !empty($this->getApiKey('google'))
            ]
        ];
    }

    /**
     * Enable development features
     *
     * @return void
     */
    private function enableDevelopmentFeatures(): void
    {
        // Enable WordPress debugging if not already enabled
        if (!defined('WP_DEBUG')) {
            define('WP_DEBUG', true);
        }

        if (!defined('WP_DEBUG_LOG')) {
            define('WP_DEBUG_LOG', true);
        }

        // Set development constants
        if (!defined('WOO_AI_ASSISTANT_DEBUG')) {
            define('WOO_AI_ASSISTANT_DEBUG', $this->shouldEnableDebugFeatures());
        }

        Logger::debug('Development features enabled');
    }

    /**
     * Get environment variables for security logging (without sensitive values)
     *
     * @return array Safe environment variables for logging
     */
    public function getSafeEnvironmentInfo(): array
    {
        return [
            'is_development' => $this->isDevelopmentEnvironment(),
            'is_docker' => $this->isDockerEnvironment(),
            'is_local_server' => $this->isLocalServerEnvironment(),
            'server_name' => $_SERVER['SERVER_NAME'] ?? '',
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? '',
            'server_port' => $_SERVER['SERVER_PORT'] ?? '',
            'wp_debug' => defined('WP_DEBUG') ? WP_DEBUG : false,
            'env_file_exists' => file_exists(plugin_dir_path(__FILE__) . '../.env'),
            'has_openrouter_key' => !empty($this->getApiKey('openrouter')),
            'has_openai_key' => !empty($this->getApiKey('openai')),
            'has_pinecone_key' => !empty($this->getApiKey('pinecone'))
        ];
    }

    /**
     * Reload environment variables (useful for testing or config changes)
     *
     * @return bool True if reload successful
     */
    public function reloadEnvironmentVariables(): bool
    {
        try {
            // Clear cached state
            $this->isDevelopment = null;
            $this->envVars = [];

            // Reload environment variables
            $this->loadEnvironmentVariables();

            Logger::info('Environment variables reloaded successfully', [
                'variables_loaded' => count($this->envVars),
                'is_development' => $this->isDevelopmentEnvironment()
            ]);

            return true;
        } catch (\Exception $e) {
            Logger::error('Failed to reload environment variables', [
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Validate development environment configuration
     *
     * @return array Validation results
     */
    public function validateDevelopmentConfiguration(): array
    {
        $results = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'recommendations' => []
        ];

        if (!$this->isDevelopmentEnvironment()) {
            $results['warnings'][] = 'Not running in development environment';
            return $results;
        }

        // Check environment file
        $pluginPath = defined('WOO_AI_ASSISTANT_PATH') ? WOO_AI_ASSISTANT_PATH : plugin_dir_path(__FILE__);
        $envFile = $pluginPath . '.env';

        if (!file_exists($envFile)) {
            $results['warnings'][] = 'No .env file found - using system environment variables only';
            $results['recommendations'][] = 'Create a .env file from .env.example for better development experience';
        } else {
            if (!is_readable($envFile)) {
                $results['errors'][] = '.env file exists but is not readable';
                $results['valid'] = false;
            }
        }

        // Check API keys
        $apiKeysStatus = $this->getAllApiKeysStatus();
        $hasAnyApiKey = false;

        foreach ($apiKeysStatus as $service => $status) {
            if ($status['configured']) {
                $hasAnyApiKey = true;
                if (!$status['valid_format']) {
                    $results['warnings'][] = "API key for {$service} has invalid format";
                }
            }
        }

        if (!$hasAnyApiKey) {
            $results['warnings'][] = 'No API keys configured - some features may not work';
            $results['recommendations'][] = 'Configure at least OpenRouter and OpenAI API keys for full functionality';
        }

        // Check WordPress debug settings
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            $results['recommendations'][] = 'Enable WP_DEBUG for better development experience';
        }

        if (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
            $results['recommendations'][] = 'Enable WP_DEBUG_LOG to see debug messages in log files';
        }

        // Check server configuration
        $serverType = $this->getEnvironmentType();
        if ($serverType === 'production') {
            $results['warnings'][] = 'Environment detected as production despite development mode being enabled';
            $results['recommendations'][] = 'Verify development environment detection is working correctly';
        }

        return $results;
    }
}
