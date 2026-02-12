<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Contracts;

use Studiometa\Foehn\Cache\TaggedCache;

/**
 * Cache interface using WordPress transients as backend.
 */
interface CacheInterface
{
    /**
     * Get a value from cache.
     *
     * @template T
     * @param string $key Cache key
     * @param T $default Default value if not found
     * @return T|mixed
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Store a value in cache.
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live in seconds (0 = no expiration)
     * @return bool True on success
     */
    public function set(string $key, mixed $value, int $ttl = 0): bool;

    /**
     * Check if a key exists in cache.
     */
    public function has(string $key): bool;

    /**
     * Remove a value from cache.
     */
    public function forget(string $key): bool;

    /**
     * Get a value from cache, or compute and store it.
     *
     * @template T
     * @param string $key Cache key
     * @param int $ttl Time to live in seconds
     * @param callable(): T $callback Function to compute value if not cached
     * @return T
     */
    public function remember(string $key, int $ttl, callable $callback): mixed;

    /**
     * Get a value from cache, or compute and store it forever.
     *
     * @template T
     * @param string $key Cache key
     * @param callable(): T $callback Function to compute value if not cached
     * @return T
     */
    public function rememberForever(string $key, callable $callback): mixed;

    /**
     * Store a value forever (no expiration).
     */
    public function forever(string $key, mixed $value): bool;

    /**
     * Increment a numeric value.
     *
     * @param string $key Cache key
     * @param int $amount Amount to increment by
     * @return int New value
     */
    public function increment(string $key, int $amount = 1): int;

    /**
     * Decrement a numeric value.
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
