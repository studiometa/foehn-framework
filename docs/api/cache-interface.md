# CacheInterface

Injectable cache service backed by WordPress transients. It is a [PSR-16](https://www.php-fig.org/psr/psr-16/) cache: it extends `Psr\SimpleCache\CacheInterface`, so you can give it to any library that takes a PSR-16 cache.

## Signature

```php
<?php

namespace Studiometa\Foehn\Contracts;

use DateInterval;
use Psr\SimpleCache\CacheInterface as SimpleCacheInterface;
use Studiometa\Foehn\Cache\TaggedCache;

interface CacheInterface extends SimpleCacheInterface
{
    // From PSR-16
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool;
    public function delete(string $key): bool;
    public function clear(): bool;
    public function getMultiple(iterable $keys, mixed $default = null): iterable;
    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool;
    public function deleteMultiple(iterable $keys): bool;
    public function has(string $key): bool;

    // Føhn helpers
    public function remember(string $key, null|int|DateInterval $ttl, callable $callback): mixed;
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

### In a PSR-16 Consumer

A library that type-hints `Psr\SimpleCache\CacheInterface` takes Føhn's cache as it is:

```php
use Psr\SimpleCache\CacheInterface;

final readonly class ExchangeRates
{
    public function __construct(
        private CacheInterface $cache,
    ) {}
}

new ExchangeRates(app(\Studiometa\Foehn\Contracts\CacheInterface::class));
```

## Keys and TTL

A key is a non-empty string. It must not contain the characters PSR-16 reserves: `{}()/\@:`. An invalid key, or a key that is not a string in `getMultiple()`, `setMultiple()` or `deleteMultiple()`, throws `Studiometa\Foehn\Cache\InvalidArgumentException`, which implements `Psr\SimpleCache\InvalidArgumentException`.

The TTL is a number of seconds or a `DateInterval`:

| TTL                          | Result                                    |
| ---------------------------- | ----------------------------------------- |
| `null` (the default)         | The entry has no expiration               |
| A positive number of seconds | The entry expires after that time         |
| `0` or a negative number     | The entry is deleted, as PSR-16 requires  |
| `DateInterval`               | Converted to seconds, with the same rules |

## Methods

### get()

Retrieve a value from cache. A miss returns the default.

```php
$value = $cache->get('my_key');
$value = $cache->get('my_key', 'default');
```

The cache returns each value as it was stored, `false` and `null` included.

### set()

Store a value in cache.

```php
$cache->set('my_key', $value, 3600);                    // TTL in seconds
$cache->set('my_key', $value, new DateInterval('P1D')); // TTL as an interval
$cache->set('my_key', $value);                          // No expiration
```

### has()

Check if a key exists in cache.

```php
if ($cache->has('my_key')) {
    // ...
}
```

### delete()

Remove a value from cache. It returns `true` when the key is not in the cache.

```php
$cache->delete('my_key');
```

### clear()

Remove every entry of this cache, and its tag mappings. Entries of other prefixes, and other transients, stay.

```php
$cache->clear();
```

### getMultiple() / setMultiple() / deleteMultiple()

Read, store or remove several entries. Each method takes an array or any other iterable.

```php
$cache->setMultiple(['a' => 1, 'b' => 2], 3600);
$values = $cache->getMultiple(['a', 'b', 'c'], 'default'); // ['a' => 1, 'b' => 2, 'c' => 'default']
$cache->deleteMultiple(['a', 'b']);
```

### remember()

Get from cache, or compute and store the value. A `null` TTL stores the value with no expiration.

```php
$posts = $cache->remember('recent_posts', 3600, function () {
    return get_posts(['numberposts' => 10]);
});

$settings = $cache->remember('site_settings', null, fn() => get_option('my_settings'));
```

### increment() / decrement()

Modify numeric values. The entry has no expiration.

```php
$cache->increment('counter');       // +1
$cache->increment('counter', 5);    // +5
$cache->decrement('counter');       // -1
$cache->decrement('counter', 3);    // -3
```

### tags()

Create a tagged cache instance for batch invalidation. `TaggedCache` is a PSR-16 cache too: it shares the keys of the cache, records each key it writes under its tags, and its `clear()` flushes its tags. It also has `remember()`.

```php
$cache->tags(['products'])->remember('list', 3600, fn() => get_products());
$cache->tags(['products', 'featured'])->set('featured_list', $data, 3600);
$cache->tags(['products'])->delete('list');
$cache->tags(['products'])->clear(); // Same as $cache->flushTag('products')
```

The returned `TaggedCache` has `put()`, `forever()`, `remember()`, `rememberForever()` and `forget()`. It has no `set()`.

### flushTag() / flushTags()

Flush all cache keys associated with tag(s).

```php
$flushed = $cache->flushTag('products');
$flushed = $cache->flushTags(['products', 'categories']);
```

Returns the number of entries flushed.

## Implementation: TransientCache

The default `TransientCache` implementation:

- Uses WordPress transients as storage backend
- Prefixes all keys with `foehn_` and a version number, for example `foehn_1_products`
- Integrates automatically with persistent object cache (Redis, Memcached) when configured
- Stores tag-to-key mappings in the `foehn_cache_tags` WordPress option
- Stores the version in the `foehn_cache_version` WordPress option

`clear()` increments the version, so the entries of the previous version are never read again. This works also with a persistent object cache, where the transients are not in `wp_options`. Without a persistent object cache, `clear()` also deletes the transients of the prefix from `wp_options`.

## Related

- [Guide: Caching](/guide/caching)
- [Kernel](./kernel) — Registers `CacheInterface` singleton
