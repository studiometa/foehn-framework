### Added

- `PageCacheConfig::$warmAfterPurge` queues the warm-up of the pages that a purge removed, through Action Scheduler: one `WarmUrl` job for each purged URL after a content change, and one job that warms every sitemap URL after a full flush. Off by default.
- `PageCacheConfig::$warmSitemap` names the sitemap or sitemap index that `wp foehn cache:warm` and the warm-up after a flush read, for example `https://example.com/sitemap_index.xml` on a site where Rank Math or Yoast turn the core sitemap off.
- `PageCacheConfig::$warmExclude` lists regular expressions, matched anywhere in the full URL, of URLs that are never warmed.

### Changed

- `wp foehn cache:warm` no longer requests URLs that the page cache does not store: another host, a query string that the cache does not key, or a path under `excludedPaths`.
