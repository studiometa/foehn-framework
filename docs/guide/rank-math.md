# Rank Math

[Rank Math SEO](https://wordpress.org/plugins/seo-by-rank-math/) is an SEO plugin. Føhn does not require it. When a site activates it, Føhn adapts some of its own features to it. Each feature on this page has an effect only when Rank Math is active.

## What Føhn does automatically

| Feature                     | Effect with Rank Math                                                                                     | Details                                                  |
| --------------------------- | --------------------------------------------------------------------------------------------------------- | -------------------------------------------------------- |
| Non-production indexing     | Outside production, the robots meta tag that Rank Math prints says `noindex, nofollow`.                   | [Security](/guide/security#non-production-indexing)      |
| Database export             | `wp foehn db:export` keeps the redirections, and exports the 404 log and the analytics as structure only. | [Database Export](/guide/database-export#plugin-presets) |
| Page cache warm-up          | Without `warmSitemap`, the warm-up reads the sitemap index of Rank Math.                                  | [Sitemap warm-up](#sitemap-warm-up)                      |
| Page cache and redirections | A redirection that is created, changed or turned on purges the stored page of its source.                 | [Redirections](#redirections)                            |

## Sitemap warm-up

Rank Math turns off the core WordPress sitemap and publishes its own sitemap index (`/sitemap_index.xml`). When its sitemap module is on and `PageCacheConfig::$warmSitemap` is not set, `wp foehn cache:warm` and the warm-up after a full flush read the sitemap index of Rank Math. Føhn asks Rank Math for its URL, so a changed sitemap slug is followed.

A `warmSitemap` that the project sets is always used first:

```php
return new PageCacheConfig(
    enabled: true,
    warmSitemap: '/custom-sitemap.xml',
);
```

See [Warming](/guide/page-cache#warming).

## Redirections

Rank Math redirects in PHP. A page that the [page cache](/guide/page-cache) stored before the redirection existed is served by nginx or by the drop-in, and PHP does not run. Without a purge, the redirection has no effect until the stored page expires.

With the redirections module of Rank Math on, Føhn purges the stored page of each source when a redirection is created, changed, turned on or restored from the trash. This includes the redirections that Rank Math creates when a slug changes, and the redirections of an import.

| Source of the redirection                                           | What Føhn purges                                                                  |
| ------------------------------------------------------------------- | --------------------------------------------------------------------------------- |
| Exact                                                               | The stored page of that path, with its query-string variants                      |
| Contains, starts with, ends with, regex, or exact that ignores case | The whole cache, because the cache cannot list the paths that the pattern matches |

A redirection that is deleted or turned off needs no purge. The cache never stores a redirect, so the next request renders the page and the cache stores it.

**How Føhn finds the change.** Rank Math does not fire an action for each change, so Føhn watches the database queries of the request. When a query writes the redirections table, Føhn reads on `shutdown` the active redirections that were updated during the request. Requests that do not write a redirection read nothing.
