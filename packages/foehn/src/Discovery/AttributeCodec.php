<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use InvalidArgumentException;
use ReflectionClass;

/**
 * Encodes and rebuilds Foehn attribute instances for the discovery cache.
 *
 * Every Foehn attribute is a readonly class whose constructor promotes all of its
 * parameters, so the constructor signature already is the serialization schema:
 * reading each promoted property yields the exact arguments that rebuild the
 * instance. Discoveries therefore store attribute instances and never a flattened
 * copy of their fields.
 *
 * The encoded form is a plain array, safe for `var_export()` and for the `require`
 * that reads the cache file back. Enum values survive the round trip because
 * `var_export()` writes them as `\Fully\Qualified\Enum::Case`.
 */
final class AttributeCodec
{
    /**
     * Key marking an encoded attribute inside a cached discovery item.
     */
    private const MARKER = '__attribute';

    /**
     * Encode an attribute instance into a cacheable array.
     *
     * @return array{__attribute: class-string, args: array<string, mixed>}
     * @throws InvalidArgumentException If the attribute cannot be rebuilt from its properties
     */
    public static function encode(object $attribute): array
    {
        return [
            self::MARKER => $attribute::class,
            'args' => self::readConstructorArguments($attribute),
        ];
    }

    /**
     * Rebuild an attribute instance from its encoded form.
     *
     * @param array{__attribute: class-string, args: array<string, mixed>} $data
     * @throws InvalidArgumentException If the encoded class no longer exists
     */
    public static function decode(array $data): object
    {
        $class = $data[self::MARKER];

        if (!class_exists($class)) {
            throw new InvalidArgumentException(sprintf('Cached attribute class "%s" does not exist.', $class));
        }

        // Named arguments, so a parameter added since the cache was written keeps its
        // default instead of shifting every later value one position to the left.
        return new ReflectionClass($class)->newInstance(...$data['args']);
    }

    /**
     * Check whether a cached value is an encoded attribute.
     */
    public static function isEncoded(mixed $value): bool
    {
        return is_array($value) && array_key_exists(self::MARKER, $value);
    }

    /**
     * Read the constructor arguments of an attribute from its promoted properties.
     *
     * A parameter that is not promoted has no property to read back, so the instance
     * could not be rebuilt: that is a defect in the attribute, not a cache miss, and
     * it fails here rather than producing a cache file that cannot be restored.
     *
     * @return array<string, mixed>
     * @throws InvalidArgumentException If a constructor parameter is not promoted
     */
    private static function readConstructorArguments(object $attribute): array
    {
        $reflection = new ReflectionClass($attribute);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return [];
        }

        $args = [];

        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();

            if (!$parameter->isPromoted()) {
                throw new InvalidArgumentException(sprintf(
                    'Attribute %s cannot be cached: constructor parameter $%s is not promoted, '
                    . 'so its value cannot be read back to rebuild the instance.',
                    $attribute::class,
                    $name,
                ));
            }

            $args[$name] = $reflection->getProperty($name)->getValue($attribute);
        }

        return $args;
    }
}
