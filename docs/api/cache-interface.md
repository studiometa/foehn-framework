# CacheInterface

Injectable cache service backed by WordPress transients.

## Signature

```php
<?php

namespace Studiometa\Foehn\Contracts;

use Studiometa\Foehn\Cache\TaggedCache;

interface CacheInterface
{
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value, int $ttl = 0): bool;
    public function has(string $key): bool;
    public function forget(string $key): bool;
    public function remember(string $key, int $ttl, callable $callback): mixed;
    public function rememberForever(string $key, callable $callback): mixed;
    public function forever(string $key, mixed $value): bool;
    public function increment(string $key, int $amount = 1): int;
    public function decrement(string $key, int $amount = 1): int;
    public function tags(array $tags): TaggedCache;
    public function flushTag(string $tag): int;
    public function flushTags(array $tags): int;
}
```

## Obtaining an Instance

`CacheInterface` is registered as a singleton in the DI container. The default implementation is `TransientCache`.

### Via Constructor Injection (Recommended)

```php
use Studiometa\Foehn\Contracts\CacheInterface;

final readonly class ProductService
{
    public function __construct(
        private CacheInterface $cache,
    ) {}
}
```

### Via the Container

```php
use Studiometa\Foehn\Contracts\CacheInterface;
use function Studiometa\Foehn\app;

$cache = app(CacheInterface::class);
```

## Methods

### get()

Retrieve a value from cache.

```php
$value = $cache->get('my_key');
$value = $cache->get('my_key', 'default');
```

### set()

Store a value in cache.

```php
$cache->set('my_key', $value, 3600); // TTL in seconds
$cache->set('my_key', $value);       // No expiration (TTL = 0)
```

### has()

Check if a key exists in cache.

```php
if ($cache->has('my_key')) {
    // ...
}
```

### forget()

Remove a value from cache.

```php
$cache->forget('my_key');
```

### remember()

Get from cache, or compute and store the value.

```php
$posts = $cache->remember('recent_posts', 3600, function () {
    return get_posts(['numberposts' => 10]);
});
```

### rememberForever()

Same as `remember()` but with no expiration.

```php
$settings = $cache->rememberForever('site_settings', function () {
    return get_option('my_settings');
});
```

### forever()

Store a value with no expiration.

```php
$cache->forever('my_key', $value);
```

### increment() / decrement()

Modify numeric values.

```php
$cache->increment('counter');       // +1
$cache->increment('counter', 5);    // +5
$cache->decrement('counter');       // -1
$cache->decrement('counter', 3);    // -3
```

### tags()

Create a tagged cache instance for batch invalidation.

```php
$cache->tags(['products'])->remember('list', 3600, fn() => get_products());
$cache->tags(['products', 'featured'])->set('featured_list', $data, 3600);
```

### flushTag() / flushTags()

Flush all cache keys associated with tag(s).

```php
$flushed = $cache->flushTag('products');
$flushed = $cache->flushTags(['products', 'categories']);
```

Returns the number of keys flushed.

## Implementation: TransientCache

The default `TransientCache` implementation:

- Uses WordPress transients as storage backend
- Prefixes all keys with `foehn_` to avoid collisions
- Integrates automatically with persistent object cache (Redis, Memcached) when configured
- Stores tag-to-key mappings in the `foehn_cache_tags` WordPress option

## Related

- [Guide: Caching](/guide/caching)
- [Kernel](./kernel) — Registers `CacheInterface` singleton
