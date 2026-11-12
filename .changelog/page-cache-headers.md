### Added

- `PageCacheConfig` takes `cacheControl`, the whole `Cache-Control` value of a page-cache hit. The drop-in sends it and `wp foehn cache:config --server=nginx` writes it, in place of `public, max-age=<browserMaxAge>, must-revalidate`. A site that migrates from WP Rocket can keep `no-cache, no-store, must-revalidate`
- `PageCacheConfig` takes `responseHeaders`, static headers that the drop-in and the generated nginx snippet send on every hit. List there the headers your server sets at server level, such as the `X-Frame-Options`, `X-XSS-Protection` and `X-Content-Type-Options` of the Laravel Forge template: the cache location of the nginx snippet has its own `add_header` lines, so nginx drops the server-level ones on a hit. See [The headers of a hit](https://studiometa.github.io/foehn-framework/guide/page-cache#the-headers-of-a-hit)

### Changed

- The `# policy:` hash of the generated nginx and Apache snippets includes `cacheControl` and `responseHeaders`, so `wp foehn cache:status` reports an installed snippet as outdated until you run `wp foehn cache:config --write` again
