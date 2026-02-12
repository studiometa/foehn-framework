<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Concerns;

use Studiometa\Foehn\PostTypes\PostTypeRegistry;
use Studiometa\Foehn\Query\PostQueryBuilder;
use Timber\Timber;

/**
 * Provides fluent query methods for post type models.
 *
 * Use this trait on classes extending Timber\Post that are registered
 * via #[AsPostType] or #[AsTimberModel] attributes.
 *
 * @example
 * ```php
 * #[AsPostType(name: 'product', singular: 'Product', plural: 'Products')]
 * final class Product extends \Studiometa\Foehn\Models\Post
 * {
 *     // Query methods are available automatically via the parent class
 * }
 *
 * // Usage:
 * Product::query()->limit(10)->whereTax('category', 'featured')->get();
 * Product::all();
 * Product::find(42);
 * Product::first(['meta_key' => 'featured', 'meta_value' => '1']);
 * Product::count();
 * ```
 */
trait QueriesPostType
{
    /**
     * Create a new query builder for this post type.
     *
     * @return PostQueryBuilder<static>
     */
    public static function query(): PostQueryBuilder
    {
        return new PostQueryBuilder(PostTypeRegistry::get(static::class), static::class);
    }

    /**
     * Get all published posts of this type.
     *
     * @param int $limit Maximum number of posts (-1 for all)
     * @return list<static>
     */
    public static function all(int $limit = -1): array
    {
        return static::query()->limit($limit)->get();
    }

    /**
     * Find a post by ID.
     *
     * @return static|null
     */
    public static function find(int $id): ?static
    {
        /** @var static|null */
        return Timber::get_post($id);
    }

    /**
     * Get the first post matching optional criteria.
     *
     * @param array<string, mixed> $args Additional WP_Query args
     * @return static|null
     */
    public static function first(array $args = []): ?static
    {
        return static::query()->merge($args)->first();
    }

    /**
     * Count posts of this type.
     *
     * @param array<string, mixed> $args Additional WP_Query args
     */
    public static function count(array $args = []): int
    {
        return static::query()->merge($args)->count();
    }

    /**
     * Check if any posts of this type exist.
     *
     * @param array<string, mixed> $args Additional WP_Query args
     */
    public static function exists(array $args = []): bool
    {
        return static::query()->merge($args)->exists();
    }
}
