### Added

- `Hooks\Security\DisableAuthorSitemap`, an opt-in hook, removes the author sitemaps, which list user slugs: the core one (`/wp-sitemap-users-1.xml`) and the one of Rank Math (`/author-sitemap.xml`, which then answers 404, also from the cache of Rank Math). Add it to `hooks` in `foehn.config.php`. The starter names it in its list of opt-in hooks
