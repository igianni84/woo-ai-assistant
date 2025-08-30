<?php

/**
 * Logger Class
 *
 * Centralized logging system for debugging and monitoring.
 * Provides different log levels and context-aware logging.
 *
 * @package WooAiAssistant
 * @subpackage Common
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Common;

use WooAiAssistant\Common\Traits\Singleton;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Logger
 *
 * @since 1.0.0
 */
class Logger
{
    use Singleton;

    /**
     * Log levels
     */
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_CRITICAL = 'critical';

    /**
     * Whether logging is enabled
     *
     * @var bool
     */
    private bool $loggingEnabled = false;

    /**
     * Current log level threshold
     *
     * @var string
     */
    private string $logLevel = self::LEVEL_ERROR;

    /**
     * Log level priority mapping
     *
     * @var array
     */
    private array $levelPriority = [
        self::LEVEL_DEBUG => 0,
        self::LEVEL_INFO => 1,
        self::LEVEL_WARNING => 2,
        self::LEVEL_ERROR => 3,
        self::LEVEL_CRITICAL => 4,
    ];

    /**
     * Initialize logger
     *
     * @return void
     */
    protected function init(): void
    {
        $this->loggingEnabled = $this->isLoggingEnabled();
        $this->logLevel = $this->getConfiguredLogLevel();
    }

    /**
     * Check if logging is enabled
     *
     * @return bool
     */
    private function isLoggingEnabled(): bool
    {
        return (defined('WOO_AI_ASSISTANT_DEBUG') && WOO_AI_ASSISTANT_DEBUG) ||
               (defined('WP_DEBUG') && WP_DEBUG) ||
               Utils::isDevelopmentMode();
    }

    /**
     * Get configured log level
     *
     * @return string
     */
    private function getConfiguredLogLevel(): string
    {
        $level = defined('WOO_AI_ASSISTANT_LOG_LEVEL') ? WOO_AI_ASSISTANT_LOG_LEVEL : self::LEVEL_ERROR;

        if (!isset($this->levelPriority[$level])) {
            $level = self::LEVEL_ERROR;
        }

        return $level;
    }

    /**
     * Check if message should be logged based on level
     *
     * @param string $level Log level
     * @return bool
     */
    private function shouldLog(string $level): bool
    {
        if (!$this->loggingEnabled) {
            return false;
        }

        if (!isset($this->levelPriority[$level])) {
            return false;
        }

        return $this->levelPriority[$level] >= $this->levelPriority[$this->logLevel];
    }

    /**
     * Log a debug message
     *
     * @param string $message Message to log
     * @param array $context Optional context data
     * @return void
     */
    public static function debug(string $message, array $context = []): void
    {
        self::getInstance()->log(self::LEVEL_DEBUG, $message, $context);
    }

    /**
     * Log an info message
     *
     * @param string $message Message to log
     * @param array $context Optional context data
     * @return void
     */
    public static function info(string $message, array $context = []): void
    {
        self::getInstance()->log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Log a warning message
     *
     * @param string $message Message to log
     * @param array $context Optional context data
     * @return void
     */
    public static function warning(string $message, array $context = []): void
    {
        self::getInstance()->log(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * Log an error message
     *
     * @param string $message Message to log
     * @param array $context Optional context data
     * @return void
     */
    public static function error(string $message, array $context = []): void
    {
        self::getInstance()->log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Log a critical message
     *
     * @param string $message Message to log
     * @param array $context Optional context data
     * @return void
     */
    public static function critical(string $message, array $context = []): void
    {
        self::getInstance()->log(self::LEVEL_CRITICAL, $message, $context);
    }

    /**
     * Log a message with specified level
     *
     * @param string $level Log level
     * @param string $message Message to log
     * @param array $context Optional context data
     * @return void
     */
    public function log(string $level, string $message, array $context = []): void
    {
        if (!$this->shouldLog($level)) {
            return;
        }

        $formattedMessage = $this->formatMessage($level, $message, $context);

        // Write to WordPress error log
        error_log($formattedMessage);

        // Trigger action for custom log handlers
        do_action('woo_ai_assistant_log_message', $level, $message, $context);
    }

    /**
     * Format log message
     *
     * @param string $level Log level
     * @param string $message Message to log
     * @param array $context Optional context data
     * @return string Formatted message
     */
    private function formatMessage(string $level, string $message, array $context = []): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $levelUpper = strtoupper($level);

        $formatted = "[{$timestamp}] [WOO_AI_ASSISTANT] [{$levelUpper}] {$message}";

        // Add context information if provided
        if (!empty($context)) {
            $contextString = $this->formatContext($context);
            $formatted .= " Context: {$contextString}";
        }

        return $formatted;
    }

    /**
     * Format context array as string
     *
     * @param array $context Context data
     * @return string Formatted context string
     */
    private function formatContext(array $context): string
    {
        $formatted = [];

        foreach ($context as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value);
            } elseif (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif (is_null($value)) {
                $value = 'null';
            }

            $formatted[] = "{$key}={$value}";
        }

        return implode(', ', $formatted);
    }

    /**
     * Enable logging
     *
     * @return void
     */
    public function enable(): void
    {
        $this->loggingEnabled = true;
    }

    /**
     * Disable logging
     *
     * @return void
     */
    public function disable(): void
    {
        $this->loggingEnabled = false;
    }

    /**
     * Set log level threshold
     *
     * @param string $level Log level
     * @return void
     */
    public function setLogLevel(string $level): void
    {
        if (isset($this->levelPriority[$level])) {
            $this->logLevel = $level;
        }
    }

    /**
     * Check if logging is currently enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->loggingEnabled;
    }

    /**
     * Get current log level
     *
     * @return string
     */
    public function getLogLevel(): string
    {
        return $this->logLevel;
    }

    /**
     * Clear log files (if implemented with file logging)
     *
     * @return bool Success status
     */
    public function clearLogs(): bool
    {
        // For now, we only log to WordPress error log
        // This method can be extended to clear custom log files in the future

        do_action('woo_ai_assistant_clear_logs');

        return true;
    }
}
