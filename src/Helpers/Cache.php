<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Helpers;

/**
 * Cache helper using WordPress transients.
 *
 * Provides a simple API for caching data with automatic serialization.
 *
 * Usage:
 * ```php
 * use Studiometa\Foehn\Helpers\Cache;
 *
 * // Get/set with expiration
 * Cache::set('key', $value, 3600);
 * $value = Cache::get('key');
 *
 * // Remember pattern (get or compute and cache)
 * $posts = Cache::remember('recent_posts', 3600, fn() => get_posts(['numberposts' => 10]));
 *
 * // Delete
 * Cache::forget('key');
 * ```
 */
final class Cache
{
    /**
     * Default cache prefix.
     */
    private static string $prefix = 'foehn_';

    /**
     * Get a value from cache.
     *
     * @template T
     * @param string $key Cache key
     * @param T $default Default value if not found
     * @return T|mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = get_transient(self::prefixKey($key));

        if ($value === false) {
            return $default;
        }

        return $value;
    }

    /**
     * Store a value in cache.
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live in seconds (0 = no expiration)
     * @return bool True on success
     */
    public static function set(string $key, mixed $value, int $ttl = 0): bool
    {
        return set_transient(self::prefixKey($key), $value, $ttl);
    }

    /**
     * Check if a key exists in cache.
     *
     * @param string $key Cache key
     * @return bool
     */
    public static function has(string $key): bool
    {
        return get_transient(self::prefixKey($key)) !== false;
    }

    /**
     * Get a value from cache, or compute and store it.
     *
     * @template T
     * @param string $key Cache key
     * @param int $ttl Time to live in seconds
     * @param callable(): T $callback Function to compute value if not cached
     * @return T
     */
    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        $value = self::get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        self::set($key, $value, $ttl);

        return $value;
    }

    /**
     * Get a value from cache, or compute and store it forever.
     *
     * @template T
     * @param string $key Cache key
     * @param callable(): T $callback Function to compute value if not cached
     * @return T
     */
    public static function rememberForever(string $key, callable $callback): mixed
    {
        return self::remember($key, 0, $callback);
    }

    /**
     * Remove a value from cache.
     *
     * @param string $key Cache key
     * @return bool True on success
     */
    public static function forget(string $key): bool
    {
        return delete_transient(self::prefixKey($key));
    }

    /**
     * Create a tagged cache instance.
     *
     * @param array<string> $tags Tags to associate with cached keys
     * @return TaggedCache
     */
    public static function tags(array $tags): TaggedCache
    {
        return new TaggedCache($tags);
    }

    /**
     * Flush all cache keys associated with a tag.
     *
     * @param string $tag Tag to flush
     * @return int Number of keys flushed
     */
    public static function flushTag(string $tag): int
    {
        return TaggedCache::flushTag($tag);
    }

    /**
     * Flush all cache keys associated with multiple tags.
     *
     * @param array<string> $tags Tags to flush
     * @return int Total number of keys flushed
     */
    public static function flushTags(array $tags): int
    {
        return TaggedCache::flushTags($tags);
    }

    /**
     * Store a value forever (no expiration).
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @return bool True on success
     */
    public static function forever(string $key, mixed $value): bool
    {
        return self::set($key, $value, 0);
    }

    /**
     * Increment a numeric value.
     *
     * @param string $key Cache key
     * @param int $amount Amount to increment by
     * @return int New value
     */
    public static function increment(string $key, int $amount = 1): int
    {
        $value = (int) self::get($key, 0) + $amount;
        self::forever($key, $value);

        return $value;
    }

    /**
     * Decrement a numeric value.
     *
     * @param string $key Cache key
     * @param int $amount Amount to decrement by
     * @return int New value
     */
    public static function decrement(string $key, int $amount = 1): int
    {
        return self::increment($key, -$amount);
    }

    /**
     * Set the cache key prefix.
     *
     * @param string $prefix Prefix to use
     */
    public static function setPrefix(string $prefix): void
    {
        self::$prefix = $prefix;
    }

    /**
     * Get the current prefix.
     */
    public static function getPrefix(): string
    {
        return self::$prefix;
    }

    /**
     * Add prefix to cache key.
     */
    private static function prefixKey(string $key): string
    {
        return self::$prefix . $key;
    }
}
