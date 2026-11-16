<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Cache;

use Psr\SimpleCache\InvalidArgumentException as PsrInvalidArgumentException;

/**
 * Thrown for a cache key PSR-16 does not allow, or for a list of keys or values that is not valid.
 */
final class InvalidArgumentException extends \InvalidArgumentException implements PsrInvalidArgumentException
{
    /**
     * The characters PSR-16 reserves: a key must not contain them.
     */
    private const string RESERVED = '{}()/\\@:';

    /**
     * Check that a key is a non-empty string without reserved characters.
     *
     * @throws self
     */
    public static function assertValidKey(mixed $key): string
    {
        if (!is_string($key)) {
            throw new self(sprintf('A cache key must be a string, %s given.', get_debug_type($key)));
        }

        if ($key === '') {
            throw new self('A cache key must not be empty.');
        }

        if (strpbrk($key, self::RESERVED) !== false) {
            throw new self(sprintf('The cache key "%s" contains a reserved character (%s).', $key, self::RESERVED));
        }

        return $key;
    }
}
