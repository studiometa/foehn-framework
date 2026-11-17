### Added

- With the redirections module of Rank Math on, a redirection that is created, changed or turned on purges the stored page of its source. A source that is a pattern (contains, starts with, ends with, regex, or exact that ignores case) empties the whole page cache. Before, a page stored before the redirection hid it
- A new docs page, Rank Math, lists what Føhn does when Rank Math is active

### Changed

- Without `PageCacheConfig::$warmSitemap`, the page cache warm-up reads the sitemap index of Rank Math when its sitemap module is on. Before, it read the core sitemap, which Rank Math turns off, and warmed only the home page. A project that set `warmSitemap: '/sitemap_index.xml'` for Rank Math can remove it
