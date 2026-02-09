<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Helpers;

/**
 * Tagged cache wrapper for grouping cache keys by tags.
 *
 * Allows flushing multiple cache keys at once by tag.
 *
 * Usage:
 * ```php
 * use Studiometa\Foehn\Helpers\Cache;
 *
 * // Store with tags
 * Cache::tags(['products', 'shop'])->remember('products_list', 3600, fn() => get_products());
 *
 * // Invalidate by tag (in hook class)
 * Cache::flushTag('products'); // Clears all keys tagged with 'products'
 * ```
 */
final class TaggedCache
{
    /**
     * Option name for storing tag → keys mapping.
     */
    private const string TAGS_OPTION = 'foehn_cache_tags';

    /**
     * @param array<string> $tags Tags to associate with cached keys
     */
    public function __construct(
        private array $tags,
    ) {}

    /**
     * Get a value from cache, or compute and store it.
     *
     * @template T
     * @param string $key Cache key
     * @param int $ttl Time to live in seconds
     * @param callable(): T $callback Function to compute value if not cached
     * @return T
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $value = Cache::get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->put($key, $value, $ttl);

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
    public function rememberForever(string $key, callable $callback): mixed
    {
        return $this->remember($key, 0, $callback);
    }

    /**
     * Store a value in cache with tags.
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live in seconds (0 = no expiration)
     * @return bool True on success
     */
    public function put(string $key, mixed $value, int $ttl = 0): bool
    {
        $this->registerKeyWithTags($key);

        return Cache::set($key, $value, $ttl);
    }

    /**
     * Store a value forever (no expiration).
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @return bool True on success
     */
    public function forever(string $key, mixed $value): bool
    {
        return $this->put($key, $value, 0);
    }

    /**
     * Remove a value from cache and its tag associations.
     *
     * @param string $key Cache key
     * @return bool True on success
     */
    public function forget(string $key): bool
    {
        $this->unregisterKeyFromTags($key);

        return Cache::forget($key);
    }

    /**
     * Get the current tag → keys mapping.
     *
     * @return array<string, array<string>>
     */
    public static function getTagsMapping(): array
    {
        $mapping = get_option(self::TAGS_OPTION, []);

        return is_array($mapping) ? $mapping : [];
    }

    /**
     * Flush all cache keys associated with a tag.
     *
     * @param string $tag Tag to flush
     * @return int Number of keys flushed
     */
    public static function flushTag(string $tag): int
    {
        $mapping = self::getTagsMapping();

        if (!isset($mapping[$tag])) {
            return 0;
        }

        $keys = $mapping[$tag];
        $flushed = 0;

        foreach ($keys as $key) {
            if (!Cache::forget($key)) {
                continue;
            }

            $flushed++;
        }

        // Remove the tag from mapping
        unset($mapping[$tag]);
        self::saveTagsMapping($mapping);

        // Also remove this tag's keys from other tags
        self::cleanupOrphanedKeys($keys, $mapping);

        return $flushed;
    }

    /**
     * Flush all cache keys associated with multiple tags.
     *
     * @param array<string> $tags Tags to flush
     * @return int Total number of keys flushed
     */
    public static function flushTags(array $tags): int
    {
        $flushed = 0;

        foreach ($tags as $tag) {
            $flushed += self::flushTag($tag);
        }

        return $flushed;
    }

    /**
     * Clear all tag mappings (useful for testing).
     */
    public static function clearTagsMapping(): void
    {
        delete_option(self::TAGS_OPTION);
    }

    /**
     * Register a key with its tags in the mapping.
     */
    private function registerKeyWithTags(string $key): void
    {
        $mapping = self::getTagsMapping();

        foreach ($this->tags as $tag) {
            if (!isset($mapping[$tag])) {
                $mapping[$tag] = [];
            }

            if (!in_array($key, $mapping[$tag], true)) {
                $mapping[$tag][] = $key;
            }
        }

        self::saveTagsMapping($mapping);
    }

    /**
     * Unregister a key from all tags.
     */
    private function unregisterKeyFromTags(string $key): void
    {
        $mapping = self::getTagsMapping();

        foreach ($mapping as $tag => $keys) {
            $mapping[$tag] = array_values(array_filter($keys, static fn(string $k) => $k !== $key));

            // Remove empty tags
            if (empty($mapping[$tag])) {
                unset($mapping[$tag]);
            }
        }

        self::saveTagsMapping($mapping);
    }

    /**
     * Remove keys from all tags after a flush.
     *
     * @param array<string> $keysToRemove Keys to remove
     * @param array<string, array<string>> $mapping Current mapping
     */
    private static function cleanupOrphanedKeys(array $keysToRemove, array $mapping): void
    {
        $updated = false;

        foreach ($mapping as $tag => $keys) {
            $filtered = array_values(array_filter(
                $keys,
                static fn(string $key) => !in_array($key, $keysToRemove, true),
            ));

            if (count($filtered) !== count($keys)) {
                $mapping[$tag] = $filtered;
                $updated = true;

                if (empty($mapping[$tag])) {
                    unset($mapping[$tag]);
                }
            }
        }

        if ($updated) {
            self::saveTagsMapping($mapping);
        }
    }

    /**
     * Save the tag → keys mapping.
     *
     * @param array<string, array<string>> $mapping
     */
    private static function saveTagsMapping(array $mapping): void
    {
        update_option(self::TAGS_OPTION, $mapping, false);
    }
}
