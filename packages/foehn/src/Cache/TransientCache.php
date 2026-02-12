<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Cache;

use Studiometa\Foehn\Contracts\CacheInterface;

/**
 * Cache implementation backed by WordPress transients.
 *
 * Transients integrate with WordPress object cache (Redis/Memcached)
 * when configured, making this suitable for production use.
 */
final class TransientCache implements CacheInterface
{
    public function __construct(
        private readonly string $prefix = 'foehn_',
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $value = get_transient($this->prefixKey($key));

        if ($value === false) {
            return $default;
        }

        return $value;
    }

    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        return set_transient($this->prefixKey($key), $value, $ttl);
    }

    public function has(string $key): bool
    {
        return get_transient($this->prefixKey($key)) !== false;
    }

    public function forget(string $key): bool
    {
        return delete_transient($this->prefixKey($key));
    }

    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    public function rememberForever(string $key, callable $callback): mixed
    {
        return $this->remember($key, 0, $callback);
    }

    public function forever(string $key, mixed $value): bool
    {
        return $this->set($key, $value, 0);
    }

    public function increment(string $key, int $amount = 1): int
    {
        $value = (int) $this->get($key, 0) + $amount;
        $this->forever($key, $value);

        return $value;
    }

    public function decrement(string $key, int $amount = 1): int
    {
        return $this->increment($key, -$amount);
    }

    public function tags(array $tags): TaggedCache
    {
        return new TaggedCache($this, $tags);
    }

    public function flushTag(string $tag): int
    {
        return TaggedCache::flush($this, $tag);
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

    private function prefixKey(string $key): string
    {
        return $this->prefix . $key;
    }
}
