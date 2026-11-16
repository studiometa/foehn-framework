<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Contracts;

use DateInterval;
use Psr\SimpleCache\CacheInterface as SimpleCacheInterface;
use Studiometa\Foehn\Cache\TaggedCache;

/**
 * Føhn's cache: a PSR-16 cache, with helpers to compute values, count and group keys by tag.
 *
 * A `null` TTL stores the value with no expiration. A TTL of zero or less deletes the entry.
 */
interface CacheInterface extends SimpleCacheInterface
{
    /**
     * Get a value from cache, or compute and store it.
     *
     * @template T
     * @param string $key Cache key
     * @param null|int|DateInterval $ttl Time to live (`null` = no expiration)
     * @param callable(): T $callback Function to compute value if not cached
     * @return T
     */
    public function remember(string $key, int|DateInterval|null $ttl, callable $callback): mixed;

    /**
     * Increment a numeric value. The entry has no expiration.
     *
     * @param string $key Cache key
     * @param int $amount Amount to increment by
     * @return int New value
     */
    public function increment(string $key, int $amount = 1): int;

    /**
     * Decrement a numeric value. The entry has no expiration.
     *
     * @param string $key Cache key
     * @param int $amount Amount to decrement by
     * @return int New value
     */
    public function decrement(string $key, int $amount = 1): int;

    /**
     * Create a tagged cache instance.
     *
     * @param list<string> $tags Tags to associate with cached keys
     */
    public function tags(array $tags): TaggedCache;

    /**
     * Flush all cache keys associated with a tag.
     *
     * @param string $tag Tag to flush
     * @return int Number of keys flushed
     */
    public function flushTag(string $tag): int;

    /**
     * Flush all cache keys associated with multiple tags.
     *
     * @param list<string> $tags Tags to flush
     * @return int Total number of keys flushed
     */
    public function flushTags(array $tags): int;
}
