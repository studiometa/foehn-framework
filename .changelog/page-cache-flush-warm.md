### Added

- `PageCacheConfig::$warmSitemap` accepts a path on the site, such as `'/sitemap_index.xml'`. Føhn reads it from `home_url()`, so one configuration works on every host. A full URL is still read as it is

### Fixed

- With `warmAfterPurge` on, every full flush of the page cache now queues the warm-up of the sitemap: `wp foehn cache:clear` and the **Clear** button of the Føhn dashboard and of the admin bar too, not only a flush that a WordPress event started. A deploy that runs `wp foehn cache:clear` now warms the site again

### Changed

- **Breaking:** the constructor of `Studiometa\Foehn\PageCache\Invalidator` takes a third argument, the `JobDispatcher`. Code that builds an `Invalidator` itself must pass it; code that gets it from the container changes nothing
