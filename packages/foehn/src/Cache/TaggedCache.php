<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Cache;

use DateInterval;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException as PsrInvalidArgumentException;

/**
 * Tagged cache wrapper for grouping cache keys by tags.
 *
 * A PSR-16 cache over another one: keys are shared with the wrapped cache, and each key
 * written here is recorded under the tags, so that flushing a tag deletes all of them.
 * `clear()` flushes the tags of this instance only. Tag-to-key mappings are stored in a
 * WordPress option.
 */
final class TaggedCache implements CacheInterface
{
    /**
     * Option name for storing tag → keys mapping, for the default cache prefix.
     */
    private const string TAGS_OPTION = 'foehn_cache_tags';

    /**
     * @param CacheInterface $cache The underlying cache instance
     * @param list<string> $tags Tags to associate with cached keys
     * @param string $option Option that stores the tag → keys mapping
     */
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly array $tags,
        private readonly string $option = self::TAGS_OPTION,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->cache->get($key, $default);
    }

    /**
     * Store a value in cache with tags. A TTL of zero or less deletes the entry.
     */
    public function set(string $key, mixed $value, int|DateInterval|null $ttl = null): bool
    {
        if (Ttl::isExpired($ttl)) {
            return $this->delete($key);
        }

        $this->registerKeyWithTags(InvalidArgumentException::assertValidKey($key));

        return $this->cache->set($key, $value, $ttl);
    }

    /**
     * Remove a value from cache and its tag associations.
     */
    public function delete(string $key): bool
    {
        $deleted = $this->cache->delete($key);
        $this->unregisterKeyFromTags($key);

        return $deleted;
    }

    /**
     * Flush the tags of this instance.
     */
    public function clear(): bool
    {
        foreach ($this->tags as $tag) {
            self::flush($this->cache, $tag, $this->option);
        }

        return true;
    }

    public function has(string $key): bool
    {
        return $this->cache->has($key);
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        return $this->cache->getMultiple($keys, $default);
    }

    /**
     * @param iterable<mixed, mixed> $values
     */
    public function setMultiple(iterable $values, int|DateInterval|null $ttl = null): bool
    {
        $stored = true;

        foreach ($values as $key => $value) {
            // PHP turns a numeric string array key into an integer.
            $key = InvalidArgumentException::assertValidKey(is_int($key) ? (string) $key : $key);
            $stored = $this->set($key, $value, $ttl) && $stored;
        }

        return $stored;
    }

    /**
     * @param iterable<mixed> $keys
     */
    public function deleteMultiple(iterable $keys): bool
    {
        $deleted = true;

        foreach ($keys as $key) {
            $deleted = $this->delete(InvalidArgumentException::assertValidKey($key)) && $deleted;
        }

        return $deleted;
    }

    /**
     * Get a value from cache, or compute and store it.
     *
     * @template T
     * @param string $key Cache key
     * @param null|int|DateInterval $ttl Time to live (`null` = no expiration)
     * @param callable(): T $callback Function to compute value if not cached
     * @return T
     */
    public function remember(string $key, int|DateInterval|null $ttl, callable $callback): mixed
    {
        $miss = new \stdClass();
        $value = $this->cache->get($key, $miss);

        if ($value !== $miss) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * Flush all cache keys associated with a tag.
     *
     * @param CacheInterface $cache The cache instance to use for deletion
     * @param string $tag Tag to flush
     * @param string $option Option that stores the tag → keys mapping
     * @return int Number of entries flushed
     */
    public static function flush(CacheInterface $cache, string $tag, string $option = self::TAGS_OPTION): int
    {
        $mapping = self::getTagsMapping($option);

        if (($mapping[$tag] ?? null) === null) {
            return 0;
        }

        $keys = $mapping[$tag];
        $flushed = 0;

        foreach ($keys as $key) {
            try {
                if (!$cache->has($key) || !$cache->delete($key)) {
                    continue;
                }
            } catch (PsrInvalidArgumentException) {
                // A mapping written before keys were checked can hold a key PSR-16 does not
                // allow. No entry can exist under it: drop it with the tag.
                continue;
            }

            $flushed++;
        }

        // Remove the tag from mapping
        unset($mapping[$tag]);
        self::saveTagsMapping($option, $mapping);

        // Also remove this tag's keys from other tags
        self::cleanupOrphanedKeys($option, $keys, $mapping);

        return $flushed;
    }

    /**
     * Get the current tag → keys mapping.
     *
     * @return array<string, list<string>>
     */
    public static function getTagsMapping(string $option = self::TAGS_OPTION): array
    {
        $mapping = get_option($option, []);

        return is_array($mapping) ? $mapping : [];
    }

    /**
     * Clear all tag mappings.
     */
    public static function clearTagsMapping(string $option = self::TAGS_OPTION): void
    {
        delete_option($option);
    }

    /**
     * Register a key with its tags in the mapping.
     */
    private function registerKeyWithTags(string $key): void
    {
        $mapping = self::getTagsMapping($this->option);

        foreach ($this->tags as $tag) {
            if (($mapping[$tag] ?? null) === null) {
                $mapping[$tag] = [];
            }

            if (!in_array($key, $mapping[$tag], true)) {
                $mapping[$tag][] = $key;
            }
        }

        self::saveTagsMapping($this->option, $mapping);
    }

    /**
     * Unregister a key from all tags.
     */
    private function unregisterKeyFromTags(string $key): void
    {
        $mapping = self::getTagsMapping($this->option);

        foreach ($mapping as $tag => $keys) {
            $mapping[$tag] = array_values(array_filter($keys, static fn(string $k) => $k !== $key));

            if (empty($mapping[$tag])) {
                unset($mapping[$tag]);
            }
        }

        self::saveTagsMapping($this->option, $mapping);
    }

    /**
     * Remove keys from all tags after a flush.
     *
     * @param string $option Option that stores the mapping
     * @param list<string> $keysToRemove Keys to remove
     * @param array<string, list<string>> $mapping Current mapping
     */
    private static function cleanupOrphanedKeys(string $option, array $keysToRemove, array $mapping): void
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
            self::saveTagsMapping($option, $mapping);
        }
    }

    /**
     * Save the tag → keys mapping.
     *
     * @param array<string, list<string>> $mapping
     */
    private static function saveTagsMapping(string $option, array $mapping): void
    {
        update_option($option, $mapping, false);
    }
}
