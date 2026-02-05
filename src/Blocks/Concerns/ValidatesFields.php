<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Blocks\Concerns;

use InvalidArgumentException;
use Stringable;

/**
 * Trait for validating ACF block fields.
 *
 * Provides common validation methods for use in block `compose()` methods.
 * For more advanced validation, consider using `webmozart/assert` or `respect/validation`.
 *
 * @example
 * ```php
 * use Studiometa\Foehn\Blocks\Concerns\ValidatesFields;
 *
 * final readonly class HeroBlock implements AcfBlockInterface
 * {
 *     use ValidatesFields;
 *
 *     public function compose(array $block, array $fields): array
 *     {
 *         $this->validateRequired($fields, ['title']);
 *
 *         return [
 *             'title' => $this->sanitizeField($fields['title'], 'string'),
 *             'count' => $this->sanitizeField($fields['count'] ?? 0, 'int'),
 *         ];
 *     }
 * }
 * ```
 */
trait ValidatesFields
{
    /**
     * Validate that required fields are present and not empty.
     *
     * @param array<string, mixed> $fields Field values
     * @param list<string> $required List of required field names
     * @throws InvalidArgumentException If a required field is missing or empty
     */
    protected function validateRequired(array $fields, array $required): void
    {
        foreach ($required as $fieldName) {
            if (!array_key_exists($fieldName, $fields)) {
                throw new InvalidArgumentException(sprintf('Required field "%s" is missing.', $fieldName));
            }

            if ($fields[$fieldName] === '' || $fields[$fieldName] === [] || $fields[$fieldName] === null) {
                throw new InvalidArgumentException(sprintf('Required field "%s" cannot be empty.', $fieldName));
            }
        }
    }

    /**
     * Validate that a value matches the expected type.
     *
     * @param mixed $value Value to validate
     * @param string $expectedType Expected type: 'string', 'int', 'float', 'bool', 'array', 'object', or a class name
     * @return bool True if valid
     */
    protected function validateType(mixed $value, string $expectedType): bool
    {
        if ($value === null) {
            return false;
        }

        return match ($expectedType) {
            'string' => is_string($value),
            'int', 'integer' => is_int($value),
            'float', 'double' => is_float($value) || is_int($value),
            'bool', 'boolean' => is_bool($value),
            'array' => is_array($value),
            'object' => is_object($value),
            'numeric' => is_numeric($value),
            'scalar' => is_scalar($value),
            'callable' => is_callable($value),
            'iterable' => is_iterable($value),
            default => $value instanceof $expectedType,
        };
    }

    /**
     * Sanitize and coerce a field value to the expected type.
     *
     * @param mixed $value Value to sanitize
     * @param string $type Target type: 'string', 'int', 'float', 'bool', 'array', 'html', 'email', 'url'
     * @return mixed Sanitized value
     */
    protected function sanitizeField(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return match ($type) {
                'string', 'html', 'email', 'url' => '',
                'int', 'integer' => 0,
                'float', 'double' => 0.0,
                'bool', 'boolean' => false,
                'array' => [],
                default => null,
            };
        }

        return match ($type) {
            'string' => $this->sanitizeString($value),
            'int', 'integer' => $this->sanitizeInt($value),
            'float', 'double' => $this->sanitizeFloat($value),
            'bool', 'boolean' => $this->sanitizeBool($value),
            'array' => $this->sanitizeArray($value),
            'html' => $this->sanitizeHtml($value),
            'email' => $this->sanitizeEmail($value),
            'url' => $this->sanitizeUrl($value),
            default => $value,
        };
    }

    /**
     * Validate fields against a schema and return sanitized values.
     *
     * @param array<string, mixed> $fields Field values
     * @param array<string, array{type: string, required?: bool, default?: mixed}> $schema Validation schema
     * @return array<string, mixed> Sanitized field values
     * @throws InvalidArgumentException If validation fails
     *
     * @example
     * ```php
     * $validated = $this->validateFields($fields, [
     *     'title' => ['type' => 'string', 'required' => true],
     *     'count' => ['type' => 'int', 'default' => 0],
     *     'image' => ['type' => 'array'],
     * ]);
     * ```
     */
    protected function validateFields(array $fields, array $schema): array
    {
        $validated = [];

        foreach ($schema as $fieldName => $rules) {
            $type = $rules['type'];
            $required = $rules['required'] ?? false;
            $default = $rules['default'] ?? null;

            // Check if field exists and has a meaningful value
            $value = $fields[$fieldName] ?? null;
            $hasValue = $value !== null && $value !== '' && $value !== [];

            // Handle required fields
            if ($required && !$hasValue) {
                throw new InvalidArgumentException(sprintf('Required field "%s" is missing or empty.', $fieldName));
            }

            // Use default if no value
            if (!$hasValue) {
                $validated[$fieldName] = $default ?? $this->sanitizeField(null, $type);
                continue;
            }

            // Validate type if specified and value is not being coerced
            $value = $fields[$fieldName];

            // Sanitize and store
            $validated[$fieldName] = $this->sanitizeField($value, $type);
        }

        return $validated;
    }

    /**
     * Sanitize value to string.
     */
    private function sanitizeString(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        if ($value instanceof Stringable) {
            return trim((string) $value);
        }

        return '';
    }

    /**
     * Sanitize value to integer.
     */
    private function sanitizeInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        return 0;
    }

    /**
     * Sanitize value to float.
     */
    private function sanitizeFloat(mixed $value): float
    {
        if (is_float($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_bool($value)) {
            return $value ? 1.0 : 0.0;
        }

        return 0.0;
    }

    /**
     * Sanitize value to boolean.
     */
    private function sanitizeBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $lower = strtolower($value);
            return in_array($lower, ['true', '1', 'yes', 'on'], true);
        }

        return (bool) $value;
    }

    /**
     * Sanitize value to array.
     */
    private function sanitizeArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_iterable($value)) {
            return iterator_to_array($value);
        }

        return [];
    }

    /**
     * Sanitize HTML content (allows safe tags).
     */
    private function sanitizeHtml(mixed $value): string
    {
        $string = $this->sanitizeString($value);

        if (function_exists('wp_kses_post')) {
            return wp_kses_post($string);
        }

        // Fallback: strip dangerous tags but keep basic formatting
        return strip_tags($string, '<p><br><a><strong><em><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><pre><code>');
    }

    /**
     * Sanitize email address.
     */
    private function sanitizeEmail(mixed $value): string
    {
        $string = $this->sanitizeString($value);

        if (function_exists('sanitize_email')) {
            return sanitize_email($string);
        }

        $filtered = filter_var($string, FILTER_SANITIZE_EMAIL);

        return $filtered !== false ? $filtered : '';
    }

    /**
     * Sanitize URL.
     */
    private function sanitizeUrl(mixed $value): string
    {
        $string = $this->sanitizeString($value);

        if (function_exists('esc_url_raw')) {
            return esc_url_raw($string);
        }

        $filtered = filter_var($string, FILTER_SANITIZE_URL);

        return $filtered !== false ? $filtered : '';
    }
}
