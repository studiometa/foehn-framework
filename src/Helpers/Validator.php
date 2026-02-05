<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Helpers;

/**
 * Simple validation helper.
 *
 * Provides Laravel-style validation rules for validating data arrays.
 *
 * Usage:
 * ```php
 * use Studiometa\Foehn\Helpers\Validator;
 *
 * $validator = Validator::make($data, [
 *     'email' => 'required|email',
 *     'name' => 'required|min:2|max:100',
 *     'age' => 'numeric|min:18',
 *     'website' => 'url',
 * ]);
 *
 * if ($validator->fails()) {
 *     $errors = $validator->errors();
 * }
 *
 * $validated = $validator->validated();
 * ```
 */
final class Validator
{
    /** @var array<string, mixed> */
    private array $data;

    /** @var array<string, string> */
    private array $rules;

    /** @var array<string, string[]> */
    private array $errors = [];

    /** @var array<string, mixed> */
    private array $validated = [];

    private bool $hasRun = false;

    /**
     * @param array<string, mixed> $data Data to validate
     * @param array<string, string> $rules Validation rules
     */
    private function __construct(array $data, array $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
    }

    /**
     * Create a new validator instance.
     *
     * @param array<string, mixed> $data Data to validate
     * @param array<string, string> $rules Validation rules (field => 'rule1|rule2')
     * @return self
     */
    public static function make(array $data, array $rules): self
    {
        return new self($data, $rules);
    }

    /**
     * Validate data and throw exception on failure.
     *
     * @param array<string, mixed> $data Data to validate
     * @param array<string, string> $rules Validation rules
     * @return array<string, mixed> Validated data
     * @throws ValidationException
     */
    public static function validate(array $data, array $rules): array
    {
        $validator = self::make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator->errors());
        }

        return $validator->validated();
    }

    /**
     * Run validation and check if it failed.
     *
     * @return bool True if validation failed
     */
    public function fails(): bool
    {
        $this->runValidation();

        return $this->errors !== [];
    }

    /**
     * Run validation and check if it passed.
     *
     * @return bool True if validation passed
     */
    public function passes(): bool
    {
        return !$this->fails();
    }

    /**
     * Get validation errors.
     *
     * @return array<string, string[]> Errors grouped by field
     */
    public function errors(): array
    {
        $this->runValidation();

        return $this->errors;
    }

    /**
     * Get first error for a field.
     *
     * @param string $field Field name
     * @return string|null First error message or null
     */
    public function firstError(string $field): ?string
    {
        $this->runValidation();

        return $this->errors[$field][0] ?? null;
    }

    /**
     * Get all validated data.
     *
     * @return array<string, mixed>
     */
    public function validated(): array
    {
        $this->runValidation();

        return $this->validated;
    }

    /**
     * Run the validation rules.
     */
    private function runValidation(): void
    {
        if ($this->hasRun) {
            return;
        }

        $this->hasRun = true;

        foreach ($this->rules as $field => $ruleString) {
            $rules = explode('|', $ruleString);
            $value = $this->data[$field] ?? null;
            $isRequired = in_array('required', $rules, true);
            $isEmpty = $value === null || $value === '';

            // Skip optional empty fields
            if ($isEmpty && !$isRequired) {
                continue;
            }

            $fieldPassed = true;

            foreach ($rules as $rule) {
                $error = $this->validateRule($field, $value, $rule);

                if ($error !== null) {
                    $this->errors[$field][] = $error;
                    $fieldPassed = false;
                }
            }

            if ($fieldPassed && !$isEmpty) {
                $this->validated[$field] = $value;
            }
        }
    }

    /**
     * Validate a single rule.
     *
     * @param string $field Field name
     * @param mixed $value Field value
     * @param string $rule Rule (e.g., 'required', 'min:5')
     * @return string|null Error message or null if valid
     */
    private function validateRule(string $field, mixed $value, string $rule): ?string
    {
        $parts = explode(':', $rule, 2);
        $ruleName = $parts[0];
        $parameter = $parts[1] ?? null;

        return match ($ruleName) {
            'required' => $this->validateRequired($field, $value),
            'email' => $this->validateEmail($field, $value),
            'url' => $this->validateUrl($field, $value),
            'numeric' => $this->validateNumeric($field, $value),
            'integer' => $this->validateInteger($field, $value),
            'string' => $this->validateString($field, $value),
            'array' => $this->validateArray($field, $value),
            'boolean' => $this->validateBoolean($field, $value),
            'min' => $this->validateMin($field, $value, $parameter),
            'max' => $this->validateMax($field, $value, $parameter),
            'between' => $this->validateBetween($field, $value, $parameter),
            'in' => $this->validateIn($field, $value, $parameter),
            'regex' => $this->validateRegex($field, $value, $parameter),
            'confirmed' => $this->validateConfirmed($field, $value),
            'nullable' => null, // Always passes, allows null
            default => null,
        };
    }

    private function validateRequired(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return "The {$field} field is required.";
        }

        return null;
    }

    private function validateEmail(string $field, mixed $value): ?string
    {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return "The {$field} must be a valid email address.";
        }

        return null;
    }

    private function validateUrl(string $field, mixed $value): ?string
    {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
            return "The {$field} must be a valid URL.";
        }

        return null;
    }

    private function validateNumeric(string $field, mixed $value): ?string
    {
        if ($value !== null && $value !== '' && !is_numeric($value)) {
            return "The {$field} must be a number.";
        }

        return null;
    }

    private function validateInteger(string $field, mixed $value): ?string
    {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_INT)) {
            return "The {$field} must be an integer.";
        }

        return null;
    }

    private function validateString(string $field, mixed $value): ?string
    {
        if ($value !== null && !is_string($value)) {
            return "The {$field} must be a string.";
        }

        return null;
    }

    private function validateArray(string $field, mixed $value): ?string
    {
        if ($value !== null && !is_array($value)) {
            return "The {$field} must be an array.";
        }

        return null;
    }

    private function validateBoolean(string $field, mixed $value): ?string
    {
        if ($value !== null && !is_bool($value) && !in_array($value, [0, 1, '0', '1', true, false], true)) {
            return "The {$field} must be true or false.";
        }

        return null;
    }

    private function validateMin(string $field, mixed $value, ?string $parameter): ?string
    {
        if ($parameter === null || $value === null || $value === '') {
            return null;
        }

        $min = (int) $parameter;

        if (is_string($value) && mb_strlen($value) < $min) {
            return "The {$field} must be at least {$min} characters.";
        }

        if (is_numeric($value) && $value < $min) {
            return "The {$field} must be at least {$min}.";
        }

        if (is_array($value) && count($value) < $min) {
            return "The {$field} must have at least {$min} items.";
        }

        return null;
    }

    private function validateMax(string $field, mixed $value, ?string $parameter): ?string
    {
        if ($parameter === null || $value === null || $value === '') {
            return null;
        }

        $max = (int) $parameter;

        if (is_string($value) && mb_strlen($value) > $max) {
            return "The {$field} must not exceed {$max} characters.";
        }

        if (is_numeric($value) && $value > $max) {
            return "The {$field} must not exceed {$max}.";
        }

        if (is_array($value) && count($value) > $max) {
            return "The {$field} must not have more than {$max} items.";
        }

        return null;
    }

    private function validateBetween(string $field, mixed $value, ?string $parameter): ?string
    {
        if ($parameter === null || $value === null || $value === '') {
            return null;
        }

        [$min, $max] = explode(',', $parameter);

        $minError = $this->validateMin($field, $value, $min);
        $maxError = $this->validateMax($field, $value, $max);

        if ($minError !== null || $maxError !== null) {
            if (is_string($value)) {
                return "The {$field} must be between {$min} and {$max} characters.";
            }

            if (is_numeric($value)) {
                return "The {$field} must be between {$min} and {$max}.";
            }

            return "The {$field} must have between {$min} and {$max} items.";
        }

        return null;
    }

    private function validateIn(string $field, mixed $value, ?string $parameter): ?string
    {
        if ($parameter === null || $value === null || $value === '') {
            return null;
        }

        $allowed = explode(',', $parameter);

        if (!in_array($value, $allowed, true)) {
            return "The {$field} must be one of: " . implode(', ', $allowed) . '.';
        }

        return null;
    }

    private function validateRegex(string $field, mixed $value, ?string $parameter): ?string
    {
        if ($parameter === null || $value === null || $value === '') {
            return null;
        }

        if (!preg_match($parameter, (string) $value)) {
            return "The {$field} format is invalid.";
        }

        return null;
    }

    private function validateConfirmed(string $field, mixed $value): ?string
    {
        $confirmationField = $field . '_confirmation';
        $confirmationValue = $this->data[$confirmationField] ?? null;

        if ($value !== $confirmationValue) {
            return "The {$field} confirmation does not match.";
        }

        return null;
    }
}
