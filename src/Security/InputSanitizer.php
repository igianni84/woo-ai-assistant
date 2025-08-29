<?php

/**
 * Input Sanitizer Class
 *
 * Provides comprehensive input sanitization for all user inputs to prevent
 * XSS, SQL injection, and other security vulnerabilities. Uses WordPress
 * built-in sanitization functions for maximum compatibility and security.
 *
 * @package WooAiAssistant
 * @subpackage Security
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Security;

use WooAiAssistant\Common\Traits\Singleton;
use WooAiAssistant\Common\Utils;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class InputSanitizer
 *
 * Comprehensive input sanitization system that handles different types of user
 * input with appropriate WordPress sanitization functions. Provides methods for
 * sanitizing text, email, URLs, HTML content, and complex data structures.
 *
 * @since 1.0.0
 */
class InputSanitizer
{
    use Singleton;

    /**
     * Allowed HTML tags for rich content
     *
     * @since 1.0.0
     * @var array
     */
    private array $allowedHtmlTags;

    /**
     * Sanitization rules cache
     *
     * @since 1.0.0
     * @var array
     */
    private array $rulesCache = [];

    /**
     * Maximum string length limits
     *
     * @since 1.0.0
     * @var array
     */
    private array $lengthLimits = [
        'text' => 1000,
        'textarea' => 10000,
        'email' => 254,
        'url' => 2000,
        'slug' => 200,
        'title' => 255,
        'message' => 5000,
        'prompt' => 8000,
    ];

    /**
     * Constructor
     *
     * Initializes the input sanitizer with allowed HTML tags and sets up
     * sanitization rules for different input types.
     *
     * @since 1.0.0
     */
    private function __construct()
    {
        $this->initializeAllowedHtmlTags();
        $this->setupSanitizationRules();
    }

    /**
     * Initialize allowed HTML tags
     *
     * Sets up the allowed HTML tags for rich content sanitization based on
     * WordPress standards with additional security restrictions.
     *
     * @since 1.0.0
     * @return void
     */
    private function initializeAllowedHtmlTags(): void
    {
        $this->allowedHtmlTags = [
            'a' => [
                'href' => true,
                'title' => true,
                'target' => true,
                'rel' => true,
            ],
            'p' => [],
            'br' => [],
            'strong' => [],
            'b' => [],
            'em' => [],
            'i' => [],
            'ul' => [],
            'ol' => [],
            'li' => [],
            'h1' => [],
            'h2' => [],
            'h3' => [],
            'h4' => [],
            'h5' => [],
            'h6' => [],
            'blockquote' => [],
            'code' => [],
            'pre' => [],
        ];

        /**
         * Filter allowed HTML tags for sanitization
         *
         * Allows customization of which HTML tags are permitted in sanitized content.
         *
         * @since 1.0.0
         * @param array $allowedHtmlTags Array of allowed HTML tags and attributes
         */
        $this->allowedHtmlTags = apply_filters('woo_ai_assistant_allowed_html_tags', $this->allowedHtmlTags);
    }

    /**
     * Setup sanitization rules
     *
     * Defines sanitization rules for different types of input to ensure
     * consistent and secure data processing.
     *
     * @since 1.0.0
     * @return void
     */
    private function setupSanitizationRules(): void
    {
        $this->rulesCache = [
            'text' => 'sanitizeText',
            'textarea' => 'sanitizeTextarea',
            'email' => 'sanitizeEmail',
            'url' => 'sanitizeUrl',
            'html' => 'sanitizeHtml',
            'slug' => 'sanitizeSlug',
            'title' => 'sanitizeTitle',
            'message' => 'sanitizeMessage',
            'prompt' => 'sanitizePrompt',
            'json' => 'sanitizeJson',
            'array' => 'sanitizeArray',
            'int' => 'sanitizeInteger',
            'float' => 'sanitizeFloat',
            'boolean' => 'sanitizeBoolean',
        ];
    }

    /**
     * Sanitize input based on type
     *
     * Main sanitization method that routes input to appropriate sanitization
     * function based on the specified type.
     *
     * @since 1.0.0
     * @param mixed $input The input to sanitize
     * @param string $type The type of sanitization to apply
     * @param array $options Additional options for sanitization
     * @return mixed Sanitized input
     *
     * @throws \InvalidArgumentException When sanitization type is invalid
     *
     * @example
     * ```php
     * $sanitizer = InputSanitizer::getInstance();
     * $cleanText = $sanitizer->sanitize($_POST['message'], 'text');
     * $cleanEmail = $sanitizer->sanitize($_POST['email'], 'email');
     * ```
     */
    public function sanitize($input, string $type, array $options = [])
    {
        // Validate sanitization type
        if (!isset($this->rulesCache[$type])) {
            throw new \InvalidArgumentException("Invalid sanitization type: {$type}");
        }

        // Handle null input
        if ($input === null) {
            return null;
        }

        // Get sanitization method
        $method = $this->rulesCache[$type];

        // Apply sanitization
        try {
            $sanitized = $this->{$method}($input, $options);

            // Log sanitization if debugging is enabled
            if (defined('WOO_AI_ASSISTANT_DEBUG') && WOO_AI_ASSISTANT_DEBUG) {
                Utils::logDebug("Input sanitized", [
                    'type' => $type,
                    'original_length' => is_string($input) ? strlen($input) : 'N/A',
                    'sanitized_length' => is_string($sanitized) ? strlen($sanitized) : 'N/A',
                ]);
            }

            return $sanitized;
        } catch (\Exception $e) {
            Utils::logError('Input sanitization failed: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Sanitize text input
     *
     * Sanitizes plain text input removing HTML tags and special characters.
     *
     * @since 1.0.0
     * @param mixed $input Input to sanitize
     * @param array $options Sanitization options
     * @return string Sanitized text
     */
    private function sanitizeText($input, array $options = []): string
    {
        if (!is_string($input)) {
            $input = (string) $input;
        }

        // Apply WordPress sanitization
        $sanitized = sanitize_text_field($input);

        // Apply length limit
        $maxLength = $options['max_length'] ?? $this->lengthLimits['text'];
        if (strlen($sanitized) > $maxLength) {
            $sanitized = substr($sanitized, 0, $maxLength);
        }

        return $sanitized;
    }

    /**
     * Sanitize textarea input
     *
     * Sanitizes multiline text input preserving line breaks but removing HTML.
     *
     * @since 1.0.0
     * @param mixed $input Input to sanitize
     * @param array $options Sanitization options
     * @return string Sanitized textarea content
     */
    private function sanitizeTextarea($input, array $options = []): string
    {
        if (!is_string($input)) {
            $input = (string) $input;
        }

        // Apply WordPress sanitization
        $sanitized = sanitize_textarea_field($input);

        // Apply length limit
        $maxLength = $options['max_length'] ?? $this->lengthLimits['textarea'];
        if (strlen($sanitized) > $maxLength) {
            $sanitized = substr($sanitized, 0, $maxLength);
        }

        return $sanitized;
    }

    /**
     * Sanitize email input
     *
     * Validates and sanitizes email addresses using WordPress email validation.
     *
     * @since 1.0.0
     * @param mixed $input Input to sanitize
     * @param array $options Sanitization options
     * @return string Sanitized email or empty string if invalid
     */
    private function sanitizeEmail($input, array $options = []): string
    {
        if (!is_string($input)) {
            return '';
        }

        // Apply WordPress email sanitization
        $sanitized = sanitize_email($input);

        // Validate email format
        if (!is_email($sanitized)) {
            return '';
        }

        // Apply length limit
        $maxLength = $options['max_length'] ?? $this->lengthLimits['email'];
        if (strlen($sanitized) > $maxLength) {
            return '';
        }

        return $sanitized;
    }

    /**
     * Sanitize URL input
     *
     * Sanitizes URLs using WordPress URL validation and sanitization.
     *
     * @since 1.0.0
     * @param mixed $input Input to sanitize
     * @param array $options Sanitization options
     * @return string Sanitized URL or empty string if invalid
     */
    private function sanitizeUrl($input, array $options = []): string
    {
        if (!is_string($input)) {
            return '';
        }

        // Apply WordPress URL sanitization
        $sanitized = esc_url_raw($input);

        // Apply length limit
        $maxLength = $options['max_length'] ?? $this->lengthLimits['url'];
        if (strlen($sanitized) > $maxLength) {
            return '';
        }

        // Additional protocol validation if specified
        if (isset($options['allowed_protocols'])) {
            $allowedProtocols = (array) $options['allowed_protocols'];
            $protocol = parse_url($sanitized, PHP_URL_SCHEME);

            if (!in_array($protocol, $allowedProtocols, true)) {
                return '';
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize HTML input
     *
     * Sanitizes HTML content using WordPress kses with predefined allowed tags.
     *
     * @since 1.0.0
     * @param mixed $input Input to sanitize
     * @param array $options Sanitization options
     * @return string Sanitized HTML content
     */
    private function sanitizeHtml($input, array $options = []): string
    {
        if (!is_string($input)) {
            $input = (string) $input;
        }

        // Use custom allowed tags if provided
        $allowedTags = $options['allowed_tags'] ?? $this->allowedHtmlTags;

        // Apply WordPress HTML sanitization
        $sanitized = wp_kses($input, $allowedTags);

        // Apply length limit if specified
        if (isset($options['max_length'])) {
            if (strlen($sanitized) > $options['max_length']) {
                $sanitized = substr($sanitized, 0, $options['max_length']);
                // Ensure we don't break HTML tags
                $sanitized = force_balance_tags($sanitized);
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize slug input
     *
     * Sanitizes input for use as URL slugs or identifiers.
     *
     * @since 1.0.0
     * @param mixed $input Input to sanitize
     * @param array $options Sanitization options
     * @return string Sanitized slug
     */
    private function sanitizeSlug($input, array $options = []): string
    {
        if (!is_string($input)) {
            $input = (string) $input;
        }

        // Apply WordPress slug sanitization
        $sanitized = sanitize_title($input);

        // Apply length limit
        $maxLength = $options['max_length'] ?? $this->lengthLimits['slug'];
        if (strlen($sanitized) > $maxLength) {
            $sanitized = substr($sanitized, 0, $maxLength);
        }

        return $sanitized;
    }

    /**
     * Sanitize title input
     *
     * Sanitizes titles and headings preserving basic formatting.
     *
     * @since 1.0.0
     * @param mixed $input Input to sanitize
     * @param array $options Sanitization options
     * @return string Sanitized title
     */
    private function sanitizeTitle($input, array $options = []): string
    {
        if (!is_string($input)) {
            $input = (string) $input;
        }

        // Apply WordPress title sanitization
        $sanitized = sanitize_text_field($input);

        // Apply length limit
        $maxLength = $options['max_length'] ?? $this->lengthLimits['title'];
        if (strlen($sanitized) > $maxLength) {
            $sanitized = substr($sanitized, 0, $maxLength);
        }

        return $sanitized;
    }

    /**
     * Sanitize message input
     *
     * Sanitizes chat messages and user communications with special handling
     * for formatting and length limits.
     *
     * @since 1.0.0
     * @param mixed $input Input to sanitize
     * @param array $options Sanitization options
     * @return string Sanitized message
     */
    private function sanitizeMessage($input, array $options = []): string
    {
        if (!is_string($input)) {
            $input = (string) $input;
        }

        // Apply textarea sanitization to preserve line breaks
        $sanitized = sanitize_textarea_field($input);

        // Apply length limit
        $maxLength = $options['max_length'] ?? $this->lengthLimits['message'];
        if (strlen($sanitized) > $maxLength) {
            $sanitized = substr($sanitized, 0, $maxLength);
        }

        // Additional filtering for chat messages
        $sanitized = $this->filterSuspiciousPatterns($sanitized);

        return $sanitized;
    }

    /**
     * Sanitize AI prompt input
     *
     * Sanitizes input intended for AI processing with special attention to
     * potential prompt injection attempts.
     *
     * @since 1.0.0
     * @param mixed $input Input to sanitize
     * @param array $options Sanitization options
     * @return string Sanitized prompt
     */
    private function sanitizePrompt($input, array $options = []): string
    {
        if (!is_string($input)) {
            $input = (string) $input;
        }

        // Apply textarea sanitization
        $sanitized = sanitize_textarea_field($input);

        // Apply length limit
        $maxLength = $options['max_length'] ?? $this->lengthLimits['prompt'];
        if (strlen($sanitized) > $maxLength) {
            $sanitized = substr($sanitized, 0, $maxLength);
        }

        // Additional filtering for AI prompts
        $sanitized = $this->filterPromptInjection($sanitized);

        return $sanitized;
    }

    /**
     * Sanitize JSON input
     *
     * Validates and sanitizes JSON data ensuring it's properly formatted.
     *
     * @since 1.0.0
     * @param mixed $input Input to sanitize
     * @param array $options Sanitization options
     * @return string|array Sanitized JSON (string or decoded array)
     */
    private function sanitizeJson($input, array $options = [])
    {
        if (is_array($input)) {
            // If already an array, sanitize recursively
            return $this->sanitizeArray($input, $options);
        }

        if (!is_string($input)) {
            return '';
        }

        // Attempt to decode JSON
        $decoded = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return '';
        }

        // Sanitize the decoded data
        $sanitized = $this->sanitizeArray($decoded, $options);

        // Return array or re-encode based on options
        if (isset($options['return_array']) && $options['return_array']) {
            return $sanitized;
        }

        return wp_json_encode($sanitized);
    }

    /**
     * Sanitize array input
     *
     * Recursively sanitizes array data applying appropriate sanitization
     * to each element based on its type.
     *
     * @since 1.0.0
     * @param mixed $input Input to sanitize
     * @param array $options Sanitization options
     * @return array Sanitized array
     */
    private function sanitizeArray($input, array $options = []): array
    {
        if (!is_array($input)) {
            return [];
        }

        $sanitized = [];
        $defaultType = $options['element_type'] ?? 'text';
        $maxElements = $options['max_elements'] ?? 100;
        $count = 0;

        foreach ($input as $key => $value) {
            // Limit array size
            if ($count >= $maxElements) {
                break;
            }

            // Sanitize key
            $sanitizedKey = sanitize_key($key);

            // Recursively sanitize value
            if (is_array($value)) {
                $sanitized[$sanitizedKey] = $this->sanitizeArray($value, $options);
            } else {
                // Determine sanitization type for this element
                $type = $options['types'][$key] ?? $defaultType;
                $sanitized[$sanitizedKey] = $this->sanitize($value, $type, $options);
            }

            $count++;
        }

        return $sanitized;
    }

    /**
     * Sanitize integer input
     *
     * Converts and validates integer input with optional range validation.
     *
     * @since 1.0.0
     * @param mixed $input Input to sanitize
     * @param array $options Sanitization options
     * @return int Sanitized integer
     */
    private function sanitizeInteger($input, array $options = []): int
    {
        // Convert to integer
        $sanitized = absint($input);

        // Apply range validation if specified
        if (isset($options['min']) && $sanitized < $options['min']) {
            $sanitized = $options['min'];
        }

        if (isset($options['max']) && $sanitized > $options['max']) {
            $sanitized = $options['max'];
        }

        return $sanitized;
    }

    /**
     * Sanitize float input
     *
     * Converts and validates float input with optional range validation.
     *
     * @since 1.0.0
     * @param mixed $input Input to sanitize
     * @param array $options Sanitization options
     * @return float Sanitized float
     */
    private function sanitizeFloat($input, array $options = []): float
    {
        // Convert to float
        $sanitized = (float) filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

        // Apply range validation if specified
        if (isset($options['min']) && $sanitized < $options['min']) {
            $sanitized = $options['min'];
        }

        if (isset($options['max']) && $sanitized > $options['max']) {
            $sanitized = $options['max'];
        }

        return $sanitized;
    }

    /**
     * Sanitize boolean input
     *
     * Converts various input types to boolean values.
     *
     * @since 1.0.0
     * @param mixed $input Input to sanitize
     * @param array $options Sanitization options
     * @return bool Sanitized boolean
     */
    private function sanitizeBoolean($input, array $options = []): bool
    {
        if (is_bool($input)) {
            return $input;
        }

        if (is_string($input)) {
            $input = strtolower(trim($input));
            return in_array($input, ['true', '1', 'yes', 'on'], true);
        }

        return (bool) $input;
    }

    /**
     * Filter suspicious patterns
     *
     * Filters out potentially malicious patterns from user input.
     *
     * @since 1.0.0
     * @param string $input Input to filter
     * @return string Filtered input
     */
    private function filterSuspiciousPatterns(string $input): string
    {
        // Patterns that might indicate malicious intent
        $suspiciousPatterns = [
            '/javascript:/i',
            '/vbscript:/i',
            '/data:/i',
            '/onload\s*=/i',
            '/onerror\s*=/i',
            '/onclick\s*=/i',
            '/<script/i',
            '/<\/script>/i',
            '/eval\s*\(/i',
            '/document\.cookie/i',
            '/window\.location/i',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            $input = preg_replace($pattern, '', $input);
        }

        return $input;
    }

    /**
     * Filter prompt injection attempts
     *
     * Filters potential prompt injection attacks from AI input.
     *
     * @since 1.0.0
     * @param string $input Input to filter
     * @return string Filtered input
     */
    private function filterPromptInjection(string $input): string
    {
        // Common prompt injection patterns
        $injectionPatterns = [
            '/ignore\s+previous\s+instructions/i',
            '/forget\s+previous\s+instructions/i',
            '/system\s*:/i',
            '/assistant\s*:/i',
            '/you\s+are\s+now/i',
            '/pretend\s+to\s+be/i',
            '/act\s+as\s+if/i',
            '/override\s+your/i',
            '/disable\s+safety/i',
        ];

        foreach ($injectionPatterns as $pattern) {
            $input = preg_replace($pattern, '[FILTERED]', $input);
        }

        return $input;
    }

    /**
     * Bulk sanitize multiple inputs
     *
     * Sanitizes multiple inputs at once using specified rules for each field.
     *
     * @since 1.0.0
     * @param array $inputs Associative array of inputs to sanitize
     * @param array $rules Sanitization rules for each field
     * @return array Sanitized inputs
     *
     * @example
     * ```php
     * $inputs = ['name' => $_POST['name'], 'email' => $_POST['email']];
     * $rules = ['name' => 'text', 'email' => 'email'];
     * $clean = $sanitizer->bulkSanitize($inputs, $rules);
     * ```
     */
    public function bulkSanitize(array $inputs, array $rules): array
    {
        $sanitized = [];

        foreach ($inputs as $field => $value) {
            $type = $rules[$field] ?? 'text';
            $options = is_array($type) ? $type['options'] ?? [] : [];
            $actualType = is_array($type) ? $type['type'] : $type;

            $sanitized[$field] = $this->sanitize($value, $actualType, $options);
        }

        return $sanitized;
    }

    /**
     * Validate input constraints
     *
     * Validates input against specified constraints and returns validation results.
     *
     * @since 1.0.0
     * @param mixed $input Input to validate
     * @param array $constraints Validation constraints
     * @return array Validation results with success status and errors
     */
    public function validateConstraints($input, array $constraints): array
    {
        $errors = [];

        // Required field validation
        if (isset($constraints['required']) && $constraints['required'] && empty($input)) {
            $errors[] = 'This field is required';
        }

        // String length validation
        if (is_string($input)) {
            if (isset($constraints['min_length']) && strlen($input) < $constraints['min_length']) {
                $errors[] = "Minimum length is {$constraints['min_length']} characters";
            }

            if (isset($constraints['max_length']) && strlen($input) > $constraints['max_length']) {
                $errors[] = "Maximum length is {$constraints['max_length']} characters";
            }
        }

        // Pattern validation
        if (isset($constraints['pattern']) && is_string($input) && !preg_match($constraints['pattern'], $input)) {
            $errors[] = $constraints['pattern_message'] ?? 'Input does not match required format';
        }

        // Custom validation function
        if (isset($constraints['custom']) && is_callable($constraints['custom'])) {
            $customResult = call_user_func($constraints['custom'], $input);
            if ($customResult !== true) {
                $errors[] = is_string($customResult) ? $customResult : 'Custom validation failed';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Get sanitization statistics
     *
     * Returns statistics about sanitization operations performed.
     *
     * @since 1.0.0
     * @return array Sanitization statistics
     */
    public function getStatistics(): array
    {
        return [
            'available_types' => array_keys($this->rulesCache),
            'length_limits' => $this->lengthLimits,
            'allowed_html_tags' => array_keys($this->allowedHtmlTags),
        ];
    }
}
