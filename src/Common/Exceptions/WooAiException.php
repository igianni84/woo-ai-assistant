<?php

/**
 * Base Woo AI Assistant Exception
 *
 * Base exception class for all plugin-specific exceptions.
 * Provides additional context and logging capabilities.
 *
 * @package WooAiAssistant
 * @subpackage Common\Exceptions
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Common\Exceptions;

use Exception;
use WooAiAssistant\Common\Logger;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WooAiException
 *
 * Base exception for all plugin errors.
 *
 * @since 1.0.0
 */
class WooAiException extends Exception
{
    /**
     * Additional context data
     *
     * @var array
     */
    protected array $context;

    /**
     * Error code mapping
     *
     * @var array
     */
    protected static array $errorCodes = [
        'GENERIC_ERROR' => 1000,
        'API_ERROR' => 2000,
        'VALIDATION_ERROR' => 3000,
        'LICENSE_ERROR' => 4000,
        'RATE_LIMIT_ERROR' => 5000
    ];

    /**
     * Constructor
     *
     * @param string $message Error message
     * @param string $errorCode Error code constant
     * @param array $context Additional context data
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message = '',
        string $errorCode = 'GENERIC_ERROR',
        array $context = [],
        ?\Throwable $previous = null
    ) {
        $code = static::$errorCodes[$errorCode] ?? static::$errorCodes['GENERIC_ERROR'];
        
        parent::__construct($message, $code, $previous);
        
        $this->context = $context;
        
        // Log the exception
        $this->logException();
    }

    /**
     * Get additional context data
     *
     * @return array Context data
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Get error code name
     *
     * @return string Error code name
     */
    public function getErrorCodeName(): string
    {
        foreach (static::$errorCodes as $name => $code) {
            if ($code === $this->getCode()) {
                return $name;
            }
        }
        
        return 'UNKNOWN_ERROR';
    }

    /**
     * Get formatted error message with context
     *
     * @return string Formatted error message
     */
    public function getFormattedMessage(): string
    {
        $message = $this->getMessage();
        
        if (!empty($this->context)) {
            $contextString = json_encode($this->context, JSON_PRETTY_PRINT);
            $message .= "\nContext: " . $contextString;
        }
        
        return $message;
    }

    /**
     * Log the exception
     *
     * @return void
     */
    protected function logException(): void
    {
        Logger::error('WooAI Exception: ' . $this->getMessage(), [
            'exception_class' => get_class($this),
            'error_code' => $this->getErrorCodeName(),
            'code' => $this->getCode(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'context' => $this->context,
            'trace' => $this->getTraceAsString()
        ]);
    }

    /**
     * Convert exception to array for API responses
     *
     * @param bool $includeTrace Whether to include stack trace
     * @return array Exception data array
     */
    public function toArray(bool $includeTrace = false): array
    {
        $data = [
            'error' => true,
            'message' => $this->getMessage(),
            'error_code' => $this->getErrorCodeName(),
            'code' => $this->getCode(),
            'context' => $this->context
        ];
        
        if ($includeTrace && defined('WOO_AI_ASSISTANT_DEBUG') && WOO_AI_ASSISTANT_DEBUG) {
            $data['trace'] = $this->getTrace();
        }
        
        return $data;
    }
}