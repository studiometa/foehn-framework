### Fixed

- A full flush of the page cache (`wp foehn cache:clear`, the dashboard, the admin bar) no longer queues the sitemap warm-up in an environment that `PageCacheConfig::$environments` does not list. Before, with `warmAfterPurge` on, it queued `WarmSitemap` jobs where the cache is off
