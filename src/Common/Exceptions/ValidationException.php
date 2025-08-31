<?php

/**
 * Validation Exception Class
 *
 * Exception for validation errors including input validation,
 * data format errors, and constraint violations.
 *
 * @package WooAiAssistant
 * @subpackage Common\Exceptions
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Common\Exceptions;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ValidationException
 *
 * Exception for validation errors.
 *
 * @since 1.0.0
 */
class ValidationException extends WooAiException
{
    /**
     * Field name that failed validation
     *
     * @var string
     */
    protected string $field;

    /**
     * Validation rule that failed
     *
     * @var string
     */
    protected string $rule;

    /**
     * Invalid value
     *
     * @var mixed
     */
    protected $value;

    /**
     * Constructor
     *
     * @param string $message Error message
     * @param string $field Field name
     * @param string $rule Validation rule
     * @param mixed $value Invalid value
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message = '',
        string $field = '',
        string $rule = '',
        $value = null,
        ?\Throwable $previous = null
    ) {
        $this->field = $field;
        $this->rule = $rule;
        $this->value = $value;

        $context = [
            'field' => $field,
            'rule' => $rule,
            'value_type' => gettype($value),
            'value_length' => is_string($value) ? strlen($value) : null
        ];

        // Only include value in context if it's safe to log
        if ($this->isSafeToLogValue($field, $value)) {
            $context['value'] = $value;
        }

        parent::__construct($message, 'VALIDATION_ERROR', $context, $previous);
    }

    /**
     * Get field name
     *
     * @return string Field name
     */
    public function getField(): string
    {
        return $this->field;
    }

    /**
     * Get validation rule
     *
     * @return string Validation rule
     */
    public function getRule(): string
    {
        return $this->rule;
    }

    /**
     * Get invalid value (safe for display)
     *
     * @return mixed Invalid value
     */
    public function getValue()
    {
        if ($this->isSafeToLogValue($this->field, $this->value)) {
            return $this->value;
        }

        return '[REDACTED]';
    }

    /**
     * Check if value is safe to include in logs
     *
     * @param string $field Field name
     * @param mixed $value Field value
     * @return bool True if safe to log
     */
    private function isSafeToLogValue(string $field, $value): bool
    {
        // Don't log sensitive field values
        $sensitiveFields = [
            'password',
            'api_key',
            'token',
            'secret',
            'license_key',
            'credit_card',
            'ssn',
            'email', // Might be PII
            'phone'  // Might be PII
        ];

        foreach ($sensitiveFields as $sensitiveField) {
            if (stripos($field, $sensitiveField) !== false) {
                return false;
            }
        }

        // Don't log very long values
        if (is_string($value) && strlen($value) > 100) {
            return false;
        }

        // Don't log complex objects
        if (is_object($value) || is_resource($value)) {
            return false;
        }

        return true;
    }
}
