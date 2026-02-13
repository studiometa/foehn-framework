<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Cache;

use Studiometa\Foehn\Contracts\CacheInterface;

/**
 * Tagged cache wrapper for grouping cache keys by tags.
 *
 * Allows flushing multiple cache keys at once by tag.
 * Tag-to-key mappings are stored in a WordPress option.
 */
final class TaggedCache
{
    /**
     * Option name for storing tag → keys mapping.
     */
    private const string TAGS_OPTION = 'foehn_cache_tags';

    /**
     * @param CacheInterface $cache The underlying cache instance
     * @param list<string> $tags Tags to associate with cached keys
     */
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly array $tags,
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
        $value = $this->cache->get($key);

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
     */
    public function put(string $key, mixed $value, int $ttl = 0): bool
    {
        $this->registerKeyWithTags($key);

        return $this->cache->set($key, $value, $ttl);
    }

    /**
     * Store a value forever (no expiration).
     */
    public function forever(string $key, mixed $value): bool
    {
        return $this->put($key, $value, 0);
    }

    /**
     * Remove a value from cache and its tag associations.
     */
    public function forget(string $key): bool
    {
        $this->unregisterKeyFromTags($key);

        return $this->cache->forget($key);
    }

    /**
     * Flush all cache keys associated with a tag.
     *
     * @param CacheInterface $cache The cache instance to use for deletion
     * @param string $tag Tag to flush
     * @return int Number of keys flushed
     */
    public static function flush(CacheInterface $cache, string $tag): int
    {
        $mapping = self::getTagsMapping();

        if (($mapping[$tag] ?? null) === null) {
            return 0;
        }

        $keys = $mapping[$tag];
        $flushed = 0;

        foreach ($keys as $key) {
            if (!$cache->forget($key)) {
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
     * Get the current tag → keys mapping.
     *
     * @return array<string, list<string>>
     */
    public static function getTagsMapping(): array
    {
        $mapping = get_option(self::TAGS_OPTION, []);

        return is_array($mapping) ? $mapping : [];
    }

    /**
     * Clear all tag mappings.
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
            if (($mapping[$tag] ?? null) === null) {
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

            if (empty($mapping[$tag])) {
                unset($mapping[$tag]);
            }
        }

        self::saveTagsMapping($mapping);
    }

    /**
     * Remove keys from all tags after a flush.
     *
     * @param list<string> $keysToRemove Keys to remove
     * @param array<string, list<string>> $mapping Current mapping
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
     * @param array<string, list<string>> $mapping
     */
    private static function saveTagsMapping(array $mapping): void
    {
        update_option(self::TAGS_OPTION, $mapping, false);
    }
}
