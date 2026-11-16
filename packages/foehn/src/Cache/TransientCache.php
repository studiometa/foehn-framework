<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Cache;

use DateInterval;
use Studiometa\Foehn\Contracts\CacheInterface;
use Studiometa\Foehn\Helpers\WP;

/**
 * PSR-16 cache backed by WordPress transients.
 *
 * Transients integrate with WordPress object cache (Redis/Memcached)
 * when configured, making this suitable for production use.
 *
 * Each value is stored wrapped in an array, so that `false` — which `get_transient()`
 * returns for a miss — and `null` can be cached and read back as they were stored.
 *
 * `clear()` empties only the entries of this cache. Every transient name holds a version,
 * kept in the `{prefix}cache_version` option, and `clear()` increments it: the entries of
 * the previous version are never read again. This works wherever the transients are
 * stored — a persistent object cache keeps them outside of `wp_options`, where they cannot
 * be listed, and evicts the old ones itself. Without an object cache, `clear()` also deletes
 * the rows of the prefix from `wp_options`, so entries with no expiration do not stay there.
 */
final class TransientCache implements CacheInterface
{
    public function __construct(
        private readonly string $prefix = 'foehn_',
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $entry = get_transient($this->transient($key));

        if (!is_array($entry) || !array_key_exists('value', $entry)) {
            return $default;
        }

        return $entry['value'];
    }

    public function set(string $key, mixed $value, int|DateInterval|null $ttl = null): bool
    {
        if (Ttl::isExpired($ttl)) {
            return $this->delete($key);
        }

        $transient = $this->transient($key);
        $entry = ['value' => $value];

        if (set_transient($transient, $entry, Ttl::seconds($ttl) ?? 0)) {
            return true;
        }

        // WordPress also answers false when the stored value did not change.
        return serialize(get_transient($transient)) === serialize($entry);
    }

    public function delete(string $key): bool
    {
        $transient = $this->transient($key);

        // `delete_transient()` answers false for a missing entry, which is not a failure.
        return delete_transient($transient) || get_transient($transient) === false;
    }

    public function clear(): bool
    {
        $cleared = update_option($this->versionOption(), $this->version() + 1, true);
        delete_option($this->tagsOption());

        if (!wp_using_ext_object_cache()) {
            $db = WP::db();
            $db->query($db->prepare(
                "DELETE FROM {$db->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $db->esc_like('_transient_' . $this->prefix) . '%',
                $db->esc_like('_transient_timeout_' . $this->prefix) . '%',
            ));
        }

        return $cleared;
    }

    public function has(string $key): bool
    {
        $entry = get_transient($this->transient($key));

        return is_array($entry) && array_key_exists('value', $entry);
    }

    /**
     * @param iterable<mixed> $keys
     * @return array<string, mixed>
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $values = [];

        foreach ($keys as $key) {
            $key = InvalidArgumentException::assertValidKey($key);
            $values[$key] = $this->get($key, $default);
        }

        return $values;
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

    public function remember(string $key, int|DateInterval|null $ttl, callable $callback): mixed
    {
        $miss = new \stdClass();
        $value = $this->get($key, $miss);

        if ($value !== $miss) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    public function increment(string $key, int $amount = 1): int
    {
        $value = (int) $this->get($key, 0) + $amount;
        $this->set($key, $value);

        return $value;
    }

    public function decrement(string $key, int $amount = 1): int
    {
        return $this->increment($key, -$amount);
    }

    public function tags(array $tags): TaggedCache
    {
        return new TaggedCache($this, $tags, $this->tagsOption());
    }

    public function flushTag(string $tag): int
    {
        return TaggedCache::flush($this, $tag, $this->tagsOption());
    }

    public function flushTags(array $tags): int
    {
        $flushed = 0;

        foreach ($tags as $tag) {
            $flushed += $this->flushTag($tag);
        }

        return $flushed;
    }

    /**
     * Get the prefix used for cache keys.
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * The transient name of a key: the prefix, the current version, then the key.
     *
     * @throws InvalidArgumentException When the key is not a valid PSR-16 key
     */
    private function transient(string $key): string
    {
        return $this->prefix . $this->version() . '_' . InvalidArgumentException::assertValidKey($key);
    }

    private function version(): int
    {
        return (int) get_option($this->versionOption(), 1);
    }

    private function versionOption(): string
    {
        return $this->prefix . 'cache_version';
    }

    private function tagsOption(): string
    {
        return $this->prefix . 'cache_tags';
    }
}
