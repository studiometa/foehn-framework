<?php

declare(strict_types=1);

namespace Studiometa\Foehn\PostTypes;

use RuntimeException;

/**
 * Static registry mapping post type model classes to their post type names.
 *
 * This registry is populated by PostTypeDiscovery during boot and allows
 * the QueriesPostType trait to resolve the post type name without requiring
 * the developer to redeclare it.
 */
final class PostTypeRegistry
{
    /** @var array<class-string, string> */
    private static array $map = [];

    /**
     * Register a class-to-post-type mapping.
     *
     * @param class-string $className The model class name
     * @param string $postType The WordPress post type slug
     */
    public static function register(string $className, string $postType): void
    {
        self::$map[$className] = $postType;
    }

    /**
     * Get the post type name for a given class.
     *
     * @param class-string $className The model class name
     * @throws RuntimeException If the class is not registered
     */
    public static function get(string $className): string
    {
        if ((self::$map[$className] ?? null) === null) {
            throw new RuntimeException(sprintf(
                'Class %s is not registered in PostTypeRegistry. Ensure it has the #[AsPostType] attribute and discovery has run.',
                $className,
            ));
        }

        return self::$map[$className];
    }

    /**
     * Check if a class is registered.
     *
     * @param class-string $className The model class name
     */
    public static function has(string $className): bool
    {
        return (self::$map[$className] ?? null) !== null;
    }

    /**
     * Get all registered mappings.
     *
     * @return array<class-string, string>
     */
    public static function all(): array
    {
        return self::$map;
    }

    /**
     * Clear the registry (useful for testing).
     */
    public static function clear(): void
    {
        self::$map = [];
    }
}
