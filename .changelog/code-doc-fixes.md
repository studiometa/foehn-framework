### Fixed

- `render()` and `renderFirst()` of the view engine no longer stop with a `TypeError` when an array context holds a `post`, `posts`, `site` or `user` value of another type, such as the `user => false` Timber gives to an anonymous visitor: such a value is now dropped, as in template controllers.
- `wp help foehn cache:clear` now gives a deploy line that works: `wp foehn cache:config --server=nginx --write`. `cache:config` stops without `--server`.
- The docblock of `FoehnConfig::$debug` now says it defaults to `false`; only `FoehnConfig::fromArray()` falls back to `WP_DEBUG`.
- The docblock of `QueryFiltersHook` now enables it in the `hooks` list of `app/foehn.config.php`, not with the old `Kernel::boot()` array.
