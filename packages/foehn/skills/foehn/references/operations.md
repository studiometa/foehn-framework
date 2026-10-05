# Operations

Config files, opt-in hooks, the discovery cache, the page cache, query filters, images, the object cache, verification and security keys.

Docs: [Configuration](https://studiometa.github.io/foehn-framework/guide/configuration.html), [Hooks](https://studiometa.github.io/foehn-framework/guide/hooks.html#built-in-hooks), [Discovery cache](https://studiometa.github.io/foehn-framework/guide/discovery-cache.html), [Page cache](https://studiometa.github.io/foehn-framework/guide/page-cache.html), [Query filters](https://studiometa.github.io/foehn-framework/guide/query-filters.html), [Images](https://studiometa.github.io/foehn-framework/guide/images.html), [Caching](https://studiometa.github.io/foehn-framework/guide/caching.html), [Verification](https://studiometa.github.io/foehn-framework/guide/verification.html), [Security](https://studiometa.github.io/foehn-framework/guide/security.html).

## Config files

Each `theme/app/<name>.config.php` returns one object. Føhn registers it in the container under its class and its interfaces.

| File                       | Class (`Studiometa\Foehn\Config\…`) | Main arguments                                                                                                                                                                                                     |
| -------------------------- | ----------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `foehn.config.php`         | `FoehnConfig`                       | `discoveryCacheStrategy` (`DiscoveryCacheStrategy::NONE`), `discoveryCachePath`, `hooks` (`[]`), `imageTransformer` (`null`), `debug` (`false`)                                                                    |
| `page-cache.config.php`    | `PageCacheConfig`                   | `enabled` (`false`), `ttl`, `environments` (`['production']`), `cacheQueryArgs`, `excludedPaths`, `excludeWhenBodyContains`, `ignoredQueryArgs`, `bypassCookies`, `cacheNotFound`, `browserMaxAge`, `debugHeaders` |
| `query-filters.config.php` | `QueryFiltersConfig`                | `taxonomies`, `publicVars`                                                                                                                                                                                         |
| `rest.config.php`          | `RestConfig`                        | `defaultCapability` (`'edit_posts'`; `null` = any logged-in user)                                                                                                                                                  |
| `timber.config.php`        | `TimberConfig`                      | `templatesDir` (`['templates']`)                                                                                                                                                                                   |

The file name is free, but follow these names. `DiscoveryCacheStrategy` is `Tempest\Discovery\DiscoveryCacheStrategy` (`FULL`, `PARTIAL`, `NONE`).

Environment files: `foehn.local.config.php`, `foehn.dev.config.php`, `foehn.staging.config.php`, `foehn.production.config.php` (by `wp_get_environment_type()`). The environment file **replaces** the plain file. It does not merge into it. Repeat every argument you need.

Read configuration in code with constructor injection (`public function __construct(private PageCacheConfig $config)`).

## Opt-in framework hooks

These classes are in the framework, but discovery skips them until `FoehnConfig::hooks` names them.

| Class                                     | Effect                                                                                                                                        |
| ----------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------- |
| `Hooks\Cleanup\CleanContent`              | Removes empty `<p>` and archive title prefixes                                                                                                |
| `Hooks\Cleanup\CleanHeadTags`             | Removes wlwmanifest, RSD, shortlink and REST discovery links                                                                                  |
| `Hooks\Cleanup\CleanImageSizes`           | Removes `medium_large`, `1536x1536`, `2048x2048`                                                                                              |
| `Hooks\Cleanup\DisableBlockStyles`        | Dequeues core block styles. Do not use it when editors write content with blocks.                                                             |
| `Hooks\Cleanup\DisableEmoji`              | Removes emoji scripts and styles                                                                                                              |
| `Hooks\Cleanup\DisableFeeds`              | Removes the feed links from `wp_head`. The feeds still answer.                                                                                |
| `Hooks\Cleanup\DisableGlobalStyles`       | Removes the inline `theme.json` styles. The starter enables it because it ships no `theme.json`. Remove it if you adopt `theme.json` presets. |
| `Hooks\Cleanup\DisableOembed`             | Removes the oEmbed discovery links from `wp_head`                                                                                             |
| `Hooks\Security\DisableFileEditor`        | Defines `DISALLOW_FILE_EDIT`, which turns off the theme and plugin editors                                                                    |
| `Hooks\Security\DisableVersionDisclosure` | Removes the WordPress version                                                                                                                 |
| `Hooks\Security\DisableXmlRpc`            | Turns off XML-RPC                                                                                                                             |
| `Hooks\Security\GenericLoginErrors`       | One login error message for every failure                                                                                                     |
| `Hooks\Security\RestApiAuth`              | Removes the `/wp/v2/users` endpoints for anonymous requests. Other endpoints stay public.                                                     |
| `Hooks\Security\SecurityHeaders`          | Sends `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` and `Permissions-Policy`                                                 |
| `Hooks\QueryFiltersHook`                  | Applies `query-filters.config.php`                                                                                                            |
| `Hooks\YouTubeNoCookieHooks`              | Rewrites YouTube embeds to youtube-nocookie.com                                                                                               |
| `Hooks\StudiometaUi`                      | Registers the `@ui` and `@svg` Twig namespaces of `studiometa/ui`                                                                             |
| `Hooks\S3UploadsEndpoint`                 | Points `humanmade/s3-uploads` at a non-AWS endpoint                                                                                           |
| `Images\ImageCacheHooks`                  | Forgets an image's transforms when the image changes                                                                                          |

All are under `Studiometa\Foehn\`. Do not copy their code into the theme. Add the class to `hooks`.

Indexing protection is not opt-in: outside `production`, Føhn always adds `noindex, nofollow`, an `X-Robots-Tag` header, a `Disallow: /` robots.txt and turns off core sitemaps.

## Discovery cache

- `NONE`: scan on every request. `PARTIAL`: cache vendor packages, scan the app. `FULL`: cache everything.
- With `FULL` (the starter default), a new or changed attribute is ignored until the cache is cleared: `wp foehn discovery:clear`. The next request writes a new cache. `wp foehn discovery:generate` writes it now.
- `composer install` clears the discovery cache and the page cache (both under `wp-content/cache/foehn/`, the default location), so a deploy refreshes them. A custom `discoveryCachePath` or `PageCacheConfig::path` is not cleared.
- `wp foehn discovery:list` shows every item, its location, and whether the location was restored from the cache. `wp foehn discovery:status` shows the cache state per location.
- Cache files: `wp-content/cache/foehn/discovery/` by default.

## Page cache

Static HTML files for anonymous `GET` requests, served by nginx or Apache (generated config) or by the `advanced-cache.php` drop-in.

```php
// app/page-cache.config.php
use Studiometa\Foehn\Config\PageCacheConfig;

return new PageCacheConfig(
    enabled: true,
    ttl: 8 * HOUR_IN_SECONDS,
    environments: ['production'],
    cacheQueryArgs: [
        'project_category' => '^[a-z0-9-]+(?:,[a-z0-9-]+)*$', // a pattern
        'orderby' => ['date', 'title'],                     // only these values
        'posts_per_page' => [3, 6, 12],
    ],
    excludedPaths: ['/contact'],
);
```

- A query argument that is not ignored and not in `cacheQueryArgs` makes the request bypass the cache. A filter added in `query-filters.config.php` must also be named in `cacheQueryArgs`, or every filtered URL is uncached.
- `foehn_sections` is always keyed. Do not add it.
- Content changes purge the affected URLs. Template and code changes do not. A deploy that runs `composer install` clears the whole page cache. Otherwise run `wp foehn cache:clear` (or `--url=<url>` for one URL).
- Nonces in a cached page expire after 12 to 24 hours. Put pages with forms that post in `excludedPaths`, or use `excludeWhenBodyContains`.
- Logged-in users, previews and password-protected posts are never cached. Search results are not cached unless `s` is in `cacheQueryArgs`.
- `wp foehn cache:config --server=nginx|apache --write` writes the server config. Run it again after each change to `page-cache*.config.php`. `wp foehn cache:status` reports a stale snippet. `wp foehn cache:warm` fills the cache from the sitemap.
- To try the cache locally, add `app/page-cache.local.config.php` with `environments: ['local']`.

## Query filters

WordPress already reads public taxonomy query vars (`?genre=rock`, `?genre[]=rock&genre[]=jazz`) and `orderby`, `order`, `s`. Use `QueryFiltersConfig` only for operators (`__and`, `__not_in`, `__exists`), private vars such as `posts_per_page`, or non-public taxonomies.

```php
// app/query-filters.config.php
use Studiometa\Foehn\Config\QueryFiltersConfig;

return new QueryFiltersConfig(
    taxonomies: ['genre' => ['in', 'not_in']],
    publicVars: ['posts_per_page' => [3, 6, 12]], // a value outside the list is refused
);
```

Add `QueryFiltersHook::class` to `FoehnConfig::hooks`. In Twig, build the filter UI with `query_get()`, `query_url_toggle()`, `query_hidden_inputs()` and `facet()`. A `pre_get_posts` action that sets defaults must run after the hook (`priority: 20`) and must not overwrite a value the visitor set.

## Images

`#[AsImageSize]` registers sizes generated at upload. For arbitrary sizes and formats, use `image_url()` in Twig:

```twig
<img src="{{ image_url(post.thumbnail, { w: 800, h: 600, fit: 'crop', fm: 'webp' }) }}" alt="{{ post.thumbnail.alt }}" />
```

With no transformer, `image_url()` returns the source URL. To transform, `composer require league/glide` and set `imageTransformer: \Studiometa\Foehn\Images\GlideTransformer::class` in `FoehnConfig`. Add `ImageCacheHooks::class` to `hooks`. Sizes snap to a step (100 px by default, maximum 2600). The server config to serve cached transforms is in the images guide.

## Object cache

Inject `Studiometa\Foehn\Contracts\CacheInterface` (a transient-backed implementation):

```php
$projects = $this->cache->remember('featured_projects', HOUR_IN_SECONDS, fn() => Project::query()->limit(3)->get());

$this->cache->tags(['projects'])->remember('project_count', DAY_IN_SECONDS, fn() => Project::count());
$this->cache->flushTag('projects'); // for example in a save_post action
```

Other methods: `get`, `set`, `has`, `forget`, `rememberForever`, `forever`, `increment`, `decrement`, `flushTags`.

## Environment helpers

- `Studiometa\Foehn\Helpers\Env`: `Env::get()`, `Env::is('staging')`, `Env::isProduction()`, `Env::isStaging()`, `Env::isDevelopment()`, `Env::isLocal()`, `Env::isDebug()`.
- `Studiometa\Foehn\Helpers\WP`: `WP::query()` (the main `WP_Query`), `WP::post()`, `WP::user()`, `WP::db()`, `WP::withPost($post, $callback)`. Use these instead of the `global` keyword.

## Verification and security keys

```bash
wp foehn verify --profile=updates --output=build/foehn-verification.json   # CI, after a WordPress or plugin update
wp foehn verify --profile=production                                       # deploy script
```

Exit status: `0` pass, `1` something to fix, `2` the gate could not run.

`wp foehn salts:generate` writes the eight WordPress keys to `.env` when they are missing. `--force` rotates them and logs out every user. The generated `wp-config.php` refuses a production request without keys.
