<?php

/**
 * Sanitizer Class
 *
 * Provides comprehensive data sanitization functionality for input processing.
 * Uses WordPress sanitization functions and provides additional security measures.
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
 * Class Sanitizer
 *
 * Handles sanitization of input data using WordPress sanitization functions
 * and additional security measures for specific data types.
 *
 * @since 1.0.0
 */
class Sanitizer
{
    /**
     * Sanitize an array of data
     *
     * @param array $data Data to sanitize
     * @param array $rules Optional sanitization rules
     * @return array Sanitized data
     */
    public function sanitizeArray(array $data, array $rules = []): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            $sanitized[$key] = $this->sanitizeValue(
                $value,
                $rules[$key] ?? 'text'
            );
        }

        return $sanitized;
    }

    /**
     * Sanitize a single value based on type
     *
     * @param mixed $value Value to sanitize
     * @param string $type Sanitization type
     * @return mixed Sanitized value
     */
    public function sanitizeValue($value, string $type)
    {
        if (is_null($value)) {
            return null;
        }

        switch ($type) {
            case 'text':
            case 'string':
                return $this->sanitizeText($value);

            case 'textarea':
                return $this->sanitizeTextarea($value);

            case 'email':
                return $this->sanitizeEmail($value);

            case 'url':
                return $this->sanitizeUrl($value);

            case 'int':
            case 'integer':
                return $this->sanitizeInt($value);

            case 'float':
            case 'decimal':
                return $this->sanitizeFloat($value);

            case 'boolean':
            case 'bool':
                return $this->sanitizeBoolean($value);

            case 'array':
                return $this->sanitizeArrayValue($value);

            case 'json':
                return $this->sanitizeJson($value);

            case 'html':
                return $this->sanitizeHtml($value);

            case 'slug':
                return $this->sanitizeSlug($value);

            case 'filename':
                return $this->sanitizeFilename($value);

            case 'key':
                return $this->sanitizeKey($value);

            case 'user':
                return $this->sanitizeUser($value);

            case 'sql_orderby':
                return $this->sanitizeSqlOrderby($value);

            case 'hex_color':
                return $this->sanitizeHexColor($value);

            case 'mime_type':
                return $this->sanitizeMimeType($value);

            case 'class':
                return $this->sanitizeHtmlClass($value);

            case 'id':
                return $this->sanitizeHtmlId($value);

            case 'meta':
                return $this->sanitizeMeta($value);

            case 'option':
                return $this->sanitizeOption($value);

            case 'title':
                return $this->sanitizeTitle($value);

            case 'post_title':
                return $this->sanitizePostTitle($value);

            case 'trackback_url':
                return $this->sanitizeTrackbackUrl($value);

            default:
                return $this->sanitizeText($value);
        }
    }

    /**
     * Sanitize text field
     *
     * @param mixed $value Value to sanitize
     * @return string Sanitized text
     */
    public function sanitizeText($value): string
    {
        return sanitize_text_field($value);
    }

    /**
     * Sanitize textarea content
     *
     * @param mixed $value Value to sanitize
     * @return string Sanitized textarea content
     */
    public function sanitizeTextarea($value): string
    {
        return sanitize_textarea_field($value);
    }

    /**
     * Sanitize email address
     *
     * @param mixed $value Value to sanitize
     * @return string Sanitized email
     */
    public function sanitizeEmail($value): string
    {
        return sanitize_email($value);
    }

    /**
     * Sanitize URL
     *
     * @param mixed $value Value to sanitize
     * @return string Sanitized URL
     */
    public function sanitizeUrl($value): string
    {
        return esc_url_raw($value);
    }

    /**
     * Sanitize integer
     *
     * @param mixed $value Value to sanitize
     * @return int Sanitized integer
     */
    public function sanitizeInt($value): int
    {
        return (int) $value;
    }

    /**
     * Sanitize float
     *
     * @param mixed $value Value to sanitize
     * @return float Sanitized float
     */
    public function sanitizeFloat($value): float
    {
        return (float) $value;
    }

    /**
     * Sanitize boolean
     *
     * @param mixed $value Value to sanitize
     * @return bool Sanitized boolean
     */
    public function sanitizeBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['true', '1', 'yes', 'on'], true);
        }

        return (bool) $value;
    }

    /**
     * Sanitize array value
     *
     * @param mixed $value Value to sanitize
     * @return array Sanitized array
     */
    public function sanitizeArrayValue($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_map([$this, 'sanitizeText'], $value);
    }

    /**
     * Sanitize JSON string
     *
     * @param mixed $value Value to sanitize
     * @return string Sanitized JSON
     */
    public function sanitizeJson($value): string
    {
        if (!is_string($value)) {
            return '';
        }

        // Decode and re-encode to ensure valid JSON
        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return '';
        }

        return wp_json_encode($decoded);
    }

    /**
     * Sanitize HTML content
     *
     * @param mixed $value Value to sanitize
     * @param array $allowedTags Allowed HTML tags
     * @return string Sanitized HTML
     */
    public function sanitizeHtml($value, array $allowedTags = []): string
    {
        if (empty($allowedTags)) {
            // Default allowed tags for content
            $allowedTags = [
                'p' => [],
                'br' => [],
                'strong' => [],
                'b' => [],
                'em' => [],
                'i' => [],
                'u' => [],
                'a' => [
                    'href' => [],
                    'title' => [],
                    'target' => []
                ],
                'ul' => [],
                'ol' => [],
                'li' => [],
                'blockquote' => [],
                'code' => []
            ];
        }

        return wp_kses($value, $allowedTags);
    }

    /**
     * Sanitize slug
     *
     * @param mixed $value Value to sanitize
     * @return string Sanitized slug
     */
    public function sanitizeSlug($value): string
    {
        return sanitize_title($value);
    }

    /**
     * Sanitize filename
     *
     * @param mixed $value Value to sanitize
     * @return string Sanitized filename
     */
    public function sanitizeFilename($value): string
    {
        return sanitize_file_name($value);
    }

    /**
     * Sanitize key
     *
     * @param mixed $value Value to sanitize
     * @return string Sanitized key
     */
    public function sanitizeKey($value): string
    {
        return sanitize_key($value);
    }

    /**
     * Sanitize user input
     *
     * @param mixed $value Value to sanitize
     * @return string Sanitized user input
     */
    public function sanitizeUser($value): string
    {
        return sanitize_user($value);
    }

    /**
     * Sanitize SQL ORDER BY clause
     *
     * @param mixed $value Value to sanitize
     * @return string Sanitized ORDER BY clause
     */
    public function sanitizeSqlOrderby($value): string
    {
        return sanitize_sql_orderby($value);
    }

    /**
     * Sanitize hex color
     *
     * @param mixed $value Value to sanitize
     * @return string Sanitized hex color
     */
    public function sanitizeHexColor($value): string
    {
        if (!is_string($value) || empty($value)) {
            return '';
        }

        // Remove # if present
        $hex = ltrim($value, '#');

        // Validate hex color format
        if (preg_match('/^[a-fA-F0-9]{6}$/', $hex)) {
            return '#' . $hex;
        }

        // Support 3-digit hex colors
        if (preg_match('/^[a-fA-F0-9]{3}$/', $hex)) {
            return '#' . $hex;
        }

        return '';
    }

    /**
     * Sanitize MIME type
     *
     * @param mixed $value Value to sanitize
     * @return string Sanitized MIME type
     */
    public function sanitizeMimeType($value): string
    {
        return sanitize_mime_type($value);
    }

    /**
     * Sanitize HTML class name
     *
     * @param mixed $value Value to sanitize
     * @return string Sanitized class name
     */
    public function sanitizeHtmlClass($value): string
    {
        return sanitize_html_class($value);
    }

    /**
     * Sanitize HTML ID
     *
     * @param mixed $value Value to sanitize
     * @return string Sanitized HTML ID
     */
    public function sanitizeHtmlId($value): string
    {
        return sanitize_key($value);
    }

    /**
     * Sanitize meta value
     *
     * @param mixed $value Value to sanitize
     * @return mixed Sanitized meta value
     */
    public function sanitizeMeta($value)
    {
        return sanitize_meta('', '', $value, '');
    }

    /**
     * Sanitize option value
     *
     * @param mixed $value Value to sanitize
     * @return mixed Sanitized option value
     */
    public function sanitizeOption($value)
    {
        return sanitize_option('', $value);
    }

    /**
     * Sanitize title
     *
     * @param mixed $value Value to sanitize
     * @return string Sanitized title
     */
    public function sanitizeTitle($value): string
    {
        return sanitize_title($value);
    }

    /**
     * Sanitize post title
     *
     * @param mixed $value Value to sanitize
     * @return string Sanitized post title
     */
    public function sanitizePostTitle($value): string
    {
        return wp_strip_all_tags($value);
    }

    /**
     * Sanitize trackback URL
     *
     * @param mixed $value Value to sanitize
     * @return string Sanitized trackback URL
     */
    public function sanitizeTrackbackUrl($value): string
    {
        return esc_url_raw($value);
    }

    /**
     * Deep sanitize array recursively
     *
     * @param array $data Data to sanitize
     * @param string $type Sanitization type
     * @return array Sanitized array
     */
    public function deepSanitizeArray(array $data, string $type = 'text'): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            $sanitizedKey = $this->sanitizeKey($key);

            if (is_array($value)) {
                $sanitized[$sanitizedKey] = $this->deepSanitizeArray($value, $type);
            } else {
                $sanitized[$sanitizedKey] = $this->sanitizeValue($value, $type);
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize conversation message
     *
     * @param mixed $value Message content
     * @return string Sanitized message
     */
    public function sanitizeMessage($value): string
    {
        if (!is_string($value)) {
            return '';
        }

        // Remove excessive whitespace
        $value = preg_replace('/\s+/', ' ', $value);
        $value = trim($value);

        // Basic HTML sanitization
        $value = wp_strip_all_tags($value);

        // Limit length
        if (strlen($value) > 4000) {
            $value = substr($value, 0, 4000);
        }

        return $value;
    }

    /**
     * Sanitize conversation ID
     *
     * @param mixed $value Conversation ID
     * @return string Sanitized conversation ID
     */
    public function sanitizeConversationId($value): string
    {
        if (!is_string($value)) {
            return '';
        }

        // Only allow alphanumeric characters and hyphens
        $value = preg_replace('/[^a-zA-Z0-9-]/', '', $value);

        return substr($value, 0, 50);
    }

    /**
     * Sanitize product context
     *
     * @param array $context Product context data
     * @return array Sanitized context
     */
    public function sanitizeProductContext(array $context): array
    {
        return [
            'product_id' => isset($context['product_id']) ? $this->sanitizeInt($context['product_id']) : 0,
            'page' => isset($context['page']) ? $this->sanitizeText($context['page']) : '',
            'category_id' => isset($context['category_id']) ? $this->sanitizeInt($context['category_id']) : 0,
            'user_agent' => isset($context['user_agent']) ? $this->sanitizeText($context['user_agent']) : '',
            'referrer' => isset($context['referrer']) ? $this->sanitizeUrl($context['referrer']) : '',
        ];
    }

    /**
     * Sanitize feedback data
     *
     * @param array $feedback Feedback data
     * @return array Sanitized feedback
     */
    public function sanitizeFeedback(array $feedback): array
    {
        return [
            'type' => isset($feedback['type']) ? $this->sanitizeText($feedback['type']) : '',
            'message' => isset($feedback['message']) ? $this->sanitizeTextarea($feedback['message']) : '',
            'rating' => isset($feedback['rating']) ? max(1, min(5, $this->sanitizeInt($feedback['rating']))) : 0,
            'email' => isset($feedback['email']) ? $this->sanitizeEmail($feedback['email']) : '',
            'categories' => isset($feedback['categories']) && is_array($feedback['categories'])
                ? array_map([$this, 'sanitizeText'], $feedback['categories']) : []
        ];
    }

    /**
     * Remove dangerous content from user input
     *
     * @param mixed $value Input value
     * @return string Safe content
     */
    public function removeDangerousContent($value): string
    {
        if (!is_string($value)) {
            return '';
        }

        // Remove potential script tags and dangerous content
        $dangerous_patterns = [
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
            '/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/mi',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload=/i',
            '/onerror=/i',
            '/onclick=/i',
            '/onmouseover=/i'
        ];

        foreach ($dangerous_patterns as $pattern) {
            $value = preg_replace($pattern, '', $value);
        }

        return $value;
    }

    /**
     * Batch sanitize multiple values
     *
     * @param array $data Data to sanitize
     * @param array $types Sanitization types for each key
     * @return array Sanitized data
     */
    public function batchSanitize(array $data, array $types): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            $type = $types[$key] ?? 'text';
            $sanitized[$key] = $this->sanitizeValue($value, $type);
        }

        return $sanitized;
    }
}
