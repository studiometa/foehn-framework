<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Helpers;

use Exception;

/**
 * Exception thrown when validation fails.
 */
final class ValidationException extends Exception
{
    /**
     * @param array<string, string[]> $errors Validation errors
     */
    public function __construct(
        private readonly array $errors,
    ) {
        $firstError = $this->getFirstError();
        parent::__construct($firstError ?? 'Validation failed.');
    }

    /**
     * Get all validation errors.
     *
     * @return array<string, string[]>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Get the first error message.
     */
    public function getFirstError(): ?string
    {
        foreach ($this->errors as $fieldErrors) {
            if (!isset($fieldErrors[0])) {
                continue;
            }

            return $fieldErrors[0];
        }

        return null;
    }

    /**
     * Get errors as a flat array.
     *
     * @return string[]
     */
    public function getMessages(): array
    {
        $messages = [];

        foreach ($this->errors as $fieldErrors) {
            foreach ($fieldErrors as $error) {
                $messages[] = $error;
            }
        }

        return $messages;
    }
}
