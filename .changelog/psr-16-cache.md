### Added

- `CacheInterface` extends `Psr\SimpleCache\CacheInterface` (PSR-16), so any library that takes a PSR-16 cache can use Føhn's cache. `studiometa/foehn` now requires `psr/simple-cache` `^3`
- `CacheInterface::clear()`, `getMultiple()`, `setMultiple()` and `deleteMultiple()`. `clear()` removes only the entries of Føhn's cache, also with a persistent object cache
- `TaggedCache` implements PSR-16 too: `get()`, `has()`, `clear()` (flushes its tags) and the `*Multiple()` methods
- An invalid key (empty, or with one of `{}()/\@:`) throws `Studiometa\Foehn\Cache\InvalidArgumentException`, which implements `Psr\SimpleCache\InvalidArgumentException`

### Changed

- **Breaking:** a TTL of `0` or less deletes the entry, as PSR-16 requires. It no longer stores the value with no expiration. Pass `null` (or no TTL) to `set()` and `remember()` to store a value with no expiration
- **Breaking:** `CacheInterface::set()` and `remember()` take `null|int|\DateInterval` as TTL, and the TTL of `set()` defaults to `null`. A class that implements `CacheInterface` must implement the PSR-16 methods and these signatures
- **Breaking:** `TaggedCache::put()` is renamed `set()`. Replace `$cache->tags([...])->put(...)` with `$cache->tags([...])->set(...)`
- **Breaking:** `TransientCache` names its transients `<prefix><version>_<key>`, and stores each value wrapped in an array, so that `false` and `null` are read back as they were stored. Entries written by an earlier release are not read anymore: they expire, or `wp transient delete --all` removes them
- `flushTag()` and `flushTags()` count the entries that were in the cache. A key whose entry had already expired is not counted
- Each `TransientCache` prefix keeps its tag mapping in its own `<prefix>cache_tags` option. The default prefix still uses `foehn_cache_tags`

### Removed

- **Breaking:** `CacheInterface::forget()` and `TaggedCache::forget()` are removed. Use `delete()`
- **Breaking:** `CacheInterface::forever()`, `rememberForever()` and the same methods of `TaggedCache` are removed. Use `set($key, $value)` and `remember($key, null, $callback)`
- **Breaking:** the `Studiometa\Foehn\config()` helper and `Kernel::getConfig()` are removed. They only read the legacy `Kernel::boot($path, [...])` array. Read a setting from its typed config class instead: inject it in a constructor, or call `app(TimberConfig::class)` (or `FoehnConfig`, `PageCacheConfig`, …)
