<?php

/**
 * Validator Class
 *
 * Provides comprehensive input validation functionality for REST API requests
 * and form submissions. Follows WordPress validation patterns and standards.
 *
 * @package WooAiAssistant
 * @subpackage Common
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Common;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Validator
 *
 * Handles validation of input data with comprehensive rule support.
 *
 * @since 1.0.0
 */
class Validator
{
    /**
     * Validation errors
     *
     * @var array
     */
    private array $errors = [];

    /**
     * Validate data against rules
     *
     * @param array $data Data to validate
     * @param array $rules Validation rules
     * @return bool|array True if valid, array of errors if invalid
     */
    public function validate(array $data, array $rules)
    {
        $this->errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            $this->validateField($field, $value, $fieldRules);
        }

        return empty($this->errors) ? true : $this->errors;
    }

    /**
     * Validate a single field
     *
     * @param string $field Field name
     * @param mixed $value Field value
     * @param array|string $rules Validation rules
     * @return void
     */
    private function validateField(string $field, $value, $rules): void
    {
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }

        foreach ($rules as $rule) {
            $this->applyRule($field, $value, $rule);
        }
    }

    /**
     * Apply validation rule
     *
     * @param string $field Field name
     * @param mixed $value Field value
     * @param string $rule Validation rule
     * @return void
     */
    private function applyRule(string $field, $value, string $rule): void
    {
        $ruleParts = explode(':', $rule, 2);
        $ruleName = $ruleParts[0];
        $parameters = isset($ruleParts[1]) ? explode(',', $ruleParts[1]) : [];

        switch ($ruleName) {
            case 'required':
                if (!$this->validateRequired($value)) {
                    $this->addError($field, 'required', 'The %s field is required.');
                }
                break;

            case 'string':
                if ($value !== null && !is_string($value)) {
                    $this->addError($field, 'string', 'The %s field must be a string.');
                }
                break;

            case 'integer':
                if ($value !== null && !$this->validateInteger($value)) {
                    $this->addError($field, 'integer', 'The %s field must be an integer.');
                }
                break;

            case 'numeric':
                if ($value !== null && !is_numeric($value)) {
                    $this->addError($field, 'numeric', 'The %s field must be numeric.');
                }
                break;

            case 'email':
                if ($value !== null && !$this->validateEmail($value)) {
                    $this->addError($field, 'email', 'The %s field must be a valid email address.');
                }
                break;

            case 'url':
                if ($value !== null && !$this->validateUrl($value)) {
                    $this->addError($field, 'url', 'The %s field must be a valid URL.');
                }
                break;

            case 'min':
                if (isset($parameters[0]) && !$this->validateMin($value, $parameters[0])) {
                    $this->addError($field, 'min', "The %s field must be at least {$parameters[0]} characters.");
                }
                break;

            case 'max':
                if (isset($parameters[0]) && !$this->validateMax($value, $parameters[0])) {
                    $this->addError($field, 'max', "The %s field may not be greater than {$parameters[0]} characters.");
                }
                break;

            case 'between':
                if (isset($parameters[0], $parameters[1]) && !$this->validateBetween($value, $parameters[0], $parameters[1])) {
                    $this->addError($field, 'between', "The %s field must be between {$parameters[0]} and {$parameters[1]} characters.");
                }
                break;

            case 'in':
                if (!$this->validateIn($value, $parameters)) {
                    $this->addError($field, 'in', 'The selected %s is invalid.');
                }
                break;

            case 'not_in':
                if (!$this->validateNotIn($value, $parameters)) {
                    $this->addError($field, 'not_in', 'The selected %s is invalid.');
                }
                break;

            case 'regex':
                if (isset($parameters[0]) && !$this->validateRegex($value, $parameters[0])) {
                    $this->addError($field, 'regex', 'The %s field format is invalid.');
                }
                break;

            case 'alpha':
                if ($value !== null && !$this->validateAlpha($value)) {
                    $this->addError($field, 'alpha', 'The %s field may only contain letters.');
                }
                break;

            case 'alpha_numeric':
                if ($value !== null && !$this->validateAlphaNumeric($value)) {
                    $this->addError($field, 'alpha_numeric', 'The %s field may only contain letters and numbers.');
                }
                break;

            case 'alpha_dash':
                if ($value !== null && !$this->validateAlphaDash($value)) {
                    $this->addError($field, 'alpha_dash', 'The %s field may only contain letters, numbers, dashes and underscores.');
                }
                break;

            case 'boolean':
                if ($value !== null && !$this->validateBoolean($value)) {
                    $this->addError($field, 'boolean', 'The %s field must be true or false.');
                }
                break;

            case 'array':
                if ($value !== null && !is_array($value)) {
                    $this->addError($field, 'array', 'The %s field must be an array.');
                }
                break;

            case 'json':
                if ($value !== null && !$this->validateJson($value)) {
                    $this->addError($field, 'json', 'The %s field must be valid JSON.');
                }
                break;

            case 'uuid':
                if ($value !== null && !$this->validateUuid($value)) {
                    $this->addError($field, 'uuid', 'The %s field must be a valid UUID.');
                }
                break;

            case 'ip':
                if ($value !== null && !$this->validateIp($value)) {
                    $this->addError($field, 'ip', 'The %s field must be a valid IP address.');
                }
                break;

            case 'date':
                if ($value !== null && !$this->validateDate($value)) {
                    $this->addError($field, 'date', 'The %s field is not a valid date.');
                }
                break;

            case 'date_format':
                if (isset($parameters[0]) && !$this->validateDateFormat($value, $parameters[0])) {
                    $this->addError($field, 'date_format', "The %s field does not match the format {$parameters[0]}.");
                }
                break;

            case 'exists':
                if (isset($parameters[0], $parameters[1]) && !$this->validateExists($value, $parameters[0], $parameters[1])) {
                    $this->addError($field, 'exists', 'The selected %s is invalid.');
                }
                break;
        }
    }

    /**
     * Validate required field
     *
     * @param mixed $value Field value
     * @return bool True if valid
     */
    private function validateRequired($value): bool
    {
        if (is_null($value)) {
            return false;
        }

        if (is_string($value) && trim($value) === '') {
            return false;
        }

        if (is_array($value) && empty($value)) {
            return false;
        }

        return true;
    }

    /**
     * Validate integer
     *
     * @param mixed $value Field value
     * @return bool True if valid
     */
    private function validateInteger($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    /**
     * Validate email
     *
     * @param mixed $value Field value
     * @return bool True if valid
     */
    private function validateEmail($value): bool
    {
        return is_email($value);
    }

    /**
     * Validate URL
     *
     * @param mixed $value Field value
     * @return bool True if valid
     */
    private function validateUrl($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Validate minimum length
     *
     * @param mixed $value Field value
     * @param int $min Minimum length
     * @return bool True if valid
     */
    private function validateMin($value, int $min): bool
    {
        if (is_string($value)) {
            return strlen($value) >= $min;
        }

        if (is_numeric($value)) {
            return $value >= $min;
        }

        if (is_array($value)) {
            return count($value) >= $min;
        }

        return false;
    }

    /**
     * Validate maximum length
     *
     * @param mixed $value Field value
     * @param int $max Maximum length
     * @return bool True if valid
     */
    private function validateMax($value, int $max): bool
    {
        if (is_string($value)) {
            return strlen($value) <= $max;
        }

        if (is_numeric($value)) {
            return $value <= $max;
        }

        if (is_array($value)) {
            return count($value) <= $max;
        }

        return false;
    }

    /**
     * Validate between range
     *
     * @param mixed $value Field value
     * @param int $min Minimum value
     * @param int $max Maximum value
     * @return bool True if valid
     */
    private function validateBetween($value, int $min, int $max): bool
    {
        return $this->validateMin($value, $min) && $this->validateMax($value, $max);
    }

    /**
     * Validate value is in list
     *
     * @param mixed $value Field value
     * @param array $options Valid options
     * @return bool True if valid
     */
    private function validateIn($value, array $options): bool
    {
        return in_array($value, $options, true);
    }

    /**
     * Validate value is not in list
     *
     * @param mixed $value Field value
     * @param array $options Invalid options
     * @return bool True if valid
     */
    private function validateNotIn($value, array $options): bool
    {
        return !in_array($value, $options, true);
    }

    /**
     * Validate regex pattern
     *
     * @param mixed $value Field value
     * @param string $pattern Regex pattern
     * @return bool True if valid
     */
    private function validateRegex($value, string $pattern): bool
    {
        return is_string($value) && preg_match($pattern, $value);
    }

    /**
     * Validate alphabetic characters only
     *
     * @param mixed $value Field value
     * @return bool True if valid
     */
    private function validateAlpha($value): bool
    {
        return is_string($value) && preg_match('/^[a-zA-Z]+$/', $value);
    }

    /**
     * Validate alphanumeric characters only
     *
     * @param mixed $value Field value
     * @return bool True if valid
     */
    private function validateAlphaNumeric($value): bool
    {
        return is_string($value) && preg_match('/^[a-zA-Z0-9]+$/', $value);
    }

    /**
     * Validate alphanumeric, dash, and underscore
     *
     * @param mixed $value Field value
     * @return bool True if valid
     */
    private function validateAlphaDash($value): bool
    {
        return is_string($value) && preg_match('/^[a-zA-Z0-9_-]+$/', $value);
    }

    /**
     * Validate boolean value
     *
     * @param mixed $value Field value
     * @return bool True if valid
     */
    private function validateBoolean($value): bool
    {
        $acceptable = [true, false, 0, 1, '0', '1'];
        return in_array($value, $acceptable, true);
    }

    /**
     * Validate JSON string
     *
     * @param mixed $value Field value
     * @return bool True if valid
     */
    private function validateJson($value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Validate UUID format
     *
     * @param mixed $value Field value
     * @return bool True if valid
     */
    private function validateUuid($value): bool
    {
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
        return is_string($value) && preg_match($pattern, $value);
    }

    /**
     * Validate IP address
     *
     * @param mixed $value Field value
     * @return bool True if valid
     */
    private function validateIp($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Validate date
     *
     * @param mixed $value Field value
     * @return bool True if valid
     */
    private function validateDate($value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $timestamp = strtotime($value);
        return $timestamp !== false && checkdate(
            date('m', $timestamp),
            date('d', $timestamp),
            date('Y', $timestamp)
        );
    }

    /**
     * Validate date format
     *
     * @param mixed $value Field value
     * @param string $format Date format
     * @return bool True if valid
     */
    private function validateDateFormat($value, string $format): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $parsed = date_parse_from_format($format, $value);
        return $parsed['error_count'] === 0 && $parsed['warning_count'] === 0;
    }

    /**
     * Validate value exists in database
     *
     * @param mixed $value Field value
     * @param string $table Database table
     * @param string $column Database column
     * @return bool True if valid
     */
    private function validateExists($value, string $table, string $column): bool
    {
        global $wpdb;

        if ($value === null) {
            return false;
        }

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}{$table} WHERE {$column} = %s",
            $value
        ));

        return $count > 0;
    }

    /**
     * Add validation error
     *
     * @param string $field Field name
     * @param string $rule Rule name
     * @param string $message Error message
     * @return void
     */
    private function addError(string $field, string $rule, string $message): void
    {
        $this->errors[$field][] = [
            'rule' => $rule,
            'message' => sprintf($message, str_replace('_', ' ', $field))
        ];
    }

    /**
     * Get validation errors
     *
     * @return array Validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Check if there are validation errors
     *
     * @return bool True if has errors
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Clear validation errors
     *
     * @return void
     */
    public function clearErrors(): void
    {
        $this->errors = [];
    }

    /**
     * Get first error for field
     *
     * @param string $field Field name
     * @return string|null First error message
     */
    public function getFirstError(string $field): ?string
    {
        if (isset($this->errors[$field][0])) {
            return $this->errors[$field][0]['message'];
        }

        return null;
    }

    /**
     * Get all errors for field
     *
     * @param string $field Field name
     * @return array Field errors
     */
    public function getFieldErrors(string $field): array
    {
        return $this->errors[$field] ?? [];
    }
}
