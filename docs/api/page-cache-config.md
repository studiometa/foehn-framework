# PageCacheConfig

Configuration for the [static page cache](/guide/page-cache).

This object is the feature's single source of truth. Four readers serve the cache — the `advanced-cache.php` drop-in, a generated nginx snippet, a generated `.htaccess` block, and the writer inside WordPress — and three of them cannot ask WordPress anything. They all derive from this object instead: the drop-in requires the config file, and `wp foehn cache:config` renders the server snippets from it. Three hand-written copies of "is this request cacheable" that drift apart is the defect the design exists to avoid.

## Signature

```php
<?php

namespace Studiometa\Foehn\Config;

final readonly class PageCacheConfig
{
    public function __construct(
        public bool $enabled = false,
        public ?string $path = null,
        public int $ttl = 0,
        public int $browserMaxAge = 0,
        public array $environments = ['production'],
        public array $bypassCookies = ['wordpress_logged_in_', 'comment_author_', 'wp-postpass_'],
        public array $ignoredQueryArgs = [
            'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id',
            'gclid', 'fbclid', 'msclkid', 'mc_cid', 'mc_eid', '_ga', 'ref',
        ],
        public array $excludedPaths = [],
        public array $excludeWhenBodyContains = [],
        public bool $cacheNotFound = false,
        public ?bool $debugHeaders = null,
    );
}
```

## Properties

| Property                  | Type           | Default                  | Description                                                                 |
| ------------------------- | -------------- | ------------------------ | --------------------------------------------------------------------------- |
| `enabled`                 | `bool`         | `false`                  | Master switch. Nothing is written or served while this is false.            |
| `path`                    | `string\|null` | `null`                   | Cache root. Defaults to `WP_CONTENT_DIR . '/cache/foehn/pages'`.            |
| `ttl`                     | `int`          | `0`                      | Seconds a stored page stays servable. `0` = until something purges it.      |
| `browserMaxAge`           | `int`          | `0`                      | `max-age` sent to the browser for cached HTML.                              |
| `environments`            | `list<string>` | `['production']`         | Environments where caching is allowed at all.                               |
| `bypassCookies`           | `list<string>` | three WordPress prefixes | A request carrying one of these cookie prefixes is never served or written. |
| `ignoredQueryArgs`        | `list<string>` | thirteen tracking args   | Stripped before the filename is computed, so tracking links still hit.      |
| `excludedPaths`           | `list<string>` | `[]`                     | URL path prefixes never cached.                                             |
| `excludeWhenBodyContains` | `list<string>` | `[]`                     | Response bodies containing one of these substrings are not stored.          |
| `cacheNotFound`           | `bool`         | `false`                  | Store 404s as well as 200s.                                                 |
| `debugHeaders`            | `bool\|null`   | `null`                   | Emit the `X-Foehn-Cache` headers. `null` follows `WP_DEBUG`.                |

## Usage

```php
<?php
// app/page-cache.config.php

use Studiometa\Foehn\Config\PageCacheConfig;

return new PageCacheConfig(
    enabled: true,
    ttl: 8 * HOUR_IN_SECONDS,
);
```

Environment-suffixed files work as they do for every other config object, and the environment's own file wins over the plain one beside it — which is how a project enables the cache in production without enabling it on a developer's machine:

```php
<?php
// app/page-cache.production.config.php

return new PageCacheConfig(enabled: true, ttl: 8 * HOUR_IN_SECONDS);
```

## Notes on individual options

### `enabled` and `environments`

Both guards apply. `enabled: true` with the default `environments` does nothing outside production, which `wp foehn cache:status` says out loud rather than leaving you to guess:

```
Enabled: Yes
Environment: local (allowed: production)
...
Warning: The page cache is enabled but inert in the 'local' environment.
```

### `path`

A path outside the document root is a legitimate choice, and the drop-in serves it happily — but no web server can reach a file by name from outside its own root, so `cache:config` refuses to generate a snippet and says why rather than emitting a broken one.

### `ttl`

Enforced exactly by the drop-in, and by an hourly `#[AsCron]` sweep on the nginx and Apache paths, because neither `try_files` nor `mod_rewrite` can look at a file's age. With a `ttl` set, the sweep interval is the real bound on staleness.

### `browserMaxAge`

`0` plus `must-revalidate` means a purge takes effect immediately, because the browser asks every time and gets a `304` when nothing changed. Raising it trades that away: a visitor holds the old page for as long as you allow, and no server-side purge can reach them.

### `bypassCookies`

Prefixes, matched against cookie **names**. The three defaults are how WordPress marks a visitor as not-anonymous: logged in, a returning commenter, and someone who has entered a post password. Add your own for a plugin that personalises a page from a cookie.

### `ignoredQueryArgs`

Arg **names**, matched exactly — `utm_source` does not match `utm_sourcex`. Order does not matter: `?utm_source=a&utm_medium=b` and `?utm_medium=b&utm_source=a` read the same file, in all three readers.

Only add an arg here when it genuinely does not change the page. `page` and `lang` must never be in this list.

### `excludedPaths`

Prefixes, so `'/contact/'` also excludes `/contact/thanks/`. The place to put anything that has to be rendered fresh: a nonce-bearing form, a cart, an account page.

### `excludeWhenBodyContains`

Substrings of the rendered HTML, checked before the page is stored. Catches what `excludedPaths` cannot, because it does not need to know which URLs a plugin puts a form on:

```php
excludeWhenBodyContains: ['name="_wpnonce"'],
```

### `debugHeaders`

Left at `null` this follows `WP_DEBUG`, which is the right default: the headers are how the feature is debugged, and there is nothing secret in them. Set it to `true` in production while you are diagnosing something.

## Methods

| Method                                    | Returns  | Description                                                                |
| ----------------------------------------- | -------- | -------------------------------------------------------------------------- |
| `getPath()`                               | `string` | The resolved cache root.                                                   |
| `wantsDebugHeaders()`                     | `bool`   | Whether the `X-Foehn-Cache` headers are emitted.                           |
| `allowsEnvironment(?string $environment)` | `bool`   | Whether caching is allowed in an environment. Defaults to the current one. |
| `PageCacheConfig::environment()`          | `string` | The environment, resolved without needing WordPress loaded.                |

`environment()` is static and deliberately does not depend on WordPress being booted: the drop-in has to answer the same question before `wp-settings.php` has finished. It prefers `wp_get_environment_type()`, falls back to the `WP_ENVIRONMENT_TYPE` constant, then the environment variable, then `production` — as WordPress does.

## See also

- [Static Page Cache guide](/guide/page-cache) — how it works, what is never cached, and the nonce caveat
- [Caching](/guide/caching) — the object cache and transients, which are a different thing
- [Discovery Cache](/guide/discovery-cache) — the other cache under `wp-content/cache/foehn/`
