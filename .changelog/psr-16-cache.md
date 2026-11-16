### Removed

- **Breaking:** the `Studiometa\Foehn\config()` helper and `Kernel::getConfig()` are removed. They only read the legacy `Kernel::boot($path, [...])` array. Read a setting from its typed config class instead: inject it in a constructor, or call `app(TimberConfig::class)` (or `FoehnConfig`, `PageCacheConfig`, …)
