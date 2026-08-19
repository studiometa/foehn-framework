# Static Page Cache

Føhn can write the rendered HTML of an anonymous `GET` to a file and let the next request for that URL be served straight from it — by nginx or Apache when the generated server config is installed, and by a WordPress drop-in otherwise. A static hit costs around a millisecond against roughly a hundred for a render, and it is what carries a traffic spike.

::: warning The failure mode is not slowness
The way a page cache goes wrong is by serving the wrong HTML to the wrong visitor: someone else's logged-in header, a dead nonce, yesterday's price. Every rule on this page exists to make that impossible, and the [nonce caveat](#nonces) is a decision you have to make rather than one the framework makes for you.
:::

## Enabling it

The cache is off until you ask for it, and allowed in `production` only by default. Both are deliberate: a cache nobody configured is a bug, and caching on your own machine means a template edit does not show up until something purges the page.

```php
<?php
// app/page-cache.config.php

use Studiometa\Foehn\Config\PageCacheConfig;

return new PageCacheConfig(
    enabled: true,
    ttl: 8 * HOUR_IN_SECONDS,
);
```

Config files can be named for an environment, and the environment's own file wins over the plain one beside it. That is how you enable the cache somewhere without enabling it everywhere:

```php
<?php
// app/page-cache.production.config.php

return new PageCacheConfig(enabled: true, ttl: 8 * HOUR_IN_SECONDS);
```

Every option is listed in the [`PageCacheConfig` reference](/api/page-cache-config).

## How a request is answered

```
                     ┌── nginx ──────────► stored file       ~1 ms, PHP never starts
request ─── which ───┼── Apache ─────────► stored file       ~1 ms, PHP never starts
             reader  └── advanced-cache.php ► stored file    a few ms, WordPress never loads
                          │
                          └── miss or bypass ► WordPress renders, and the response is stored
```

All three readers compute the same filename for the same request, and all three apply the same eligibility rules — because there is one implementation of both, in `Studiometa\Foehn\PageCache\CacheKey` and `Studiometa\Foehn\PageCache\Bypass`, and the server snippets are generated from the loaded configuration rather than written by hand.

Stored pages live under:

```
web/wp-content/cache/foehn/pages/{host}/{path}/index.html
```

The host is in the path because the read side cannot ask WordPress anything, and it is **validated against `WP_HOME` before a file is written** — a cache path built from an unchecked `Host` header is a cache-poisoning primitive.

## Installing the fast path

The drop-in works everywhere with no setup: the installer writes `web/wp-content/advanced-cache.php` and defines `WP_CACHE` on every `composer install`. To skip PHP entirely, generate the server config:

```bash
# Look at it first
wp foehn cache:config --server=nginx

# Write config/nginx/foehn-page-cache.conf
wp foehn cache:config --server=nginx --write
```

Then include the file inside your `server { }` block and reload nginx:

```nginx
server {
    # ... your existing configuration, including a stock `location / { }`
    include /path/to/project/config/nginx/foehn-page-cache.conf;
}
```

It **composes** with what is already there — nothing has to be removed. The generated block is a regex `location`, and nginx tests those before falling back to the longest prefix match, so it wins over a stock `location /` without the two ever colliding. The `.php` exclusion is inside its own pattern, so the include is safe to place anywhere in the block.

For Apache:

```bash
wp foehn cache:config --server=apache --write
```

That merges a marker-delimited block into `web/.htaccess`, leaving your own rules and WordPress's permalink block where they are. On a first run it adds the permalink block too: the starter ships no `.htaccess` and `DISALLOW_FILE_MODS` stops WordPress writing one, so installing the cache rules alone would break every URL on the site.

Re-run `cache:config` whenever you change `page-cache*.config.php`. The generated snippets carry a `# policy:` hash, and `wp foehn cache:status` tells you when an installed one no longer matches:

```
Read paths:
  ✓ drop-in (advanced-cache.php)
  ! nginx (/srv/example/config/nginx/foehn-page-cache.conf) — generated from a different config, re-run cache:config
  · apache
```

## What is never cached

A request has to pass all of these. When one fails, the response carries the reason (see [debugging](#debugging)).

| Class    | Rule                                                                                                                                                                                                                                         |
| -------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Request  | method is `GET`; no `$_POST`; `Host` matches `WP_HOME`; the path validates; no query string left after the ignored args; none of `bypassCookies`; not under `excludedPaths`; no `.maintenance` file; not WP-CLI, REST, AJAX, cron or XML-RPC |
| Context  | not admin, feed, trackback, robots, embed, preview, customizer preview or search; nobody logged in; no password-protected post; `DONOTCACHEPAGE` undefined                                                                                   |
| Response | status 200 (or 404 with `cacheNotFound`); `Content-Type` is `text/html`; no `Location` header                                                                                                                                                |
| Body     | at least 255 bytes; ends with `</html>`, so a render that died mid-template is never frozen; contains none of `excludeWhenBodyContains`                                                                                                      |

### Query strings

Every argument in a request falls into exactly one of three classes.

**Ignored** — `ignoredQueryArgs`, the `utm_*` family, `gclid`, `fbclid`, `mc_cid` and friends. Dropped before the filename is computed, so a link out of a newsletter hits the same file as a bare URL.

**Keyed** — `cacheQueryArgs`, empty by default. These change which page is being asked for, so they go **into** the filename rather than being dropped:

```php
// app/page-cache.config.php
return new PageCacheConfig(
    enabled: true,
    cacheQueryArgs: ['page' => '^[0-9]{1,6}$', 'lang' => '^[a-z]{2}$'],
);
```

```
?page=2&lang=fr   ─┐
                   ├─→  …/index__lang=fr&page=2&.html
?lang=fr&page=2   ─┘
```

Order does not matter, and that is the interesting part: no reader sorts the query string. Each of them walks your `cacheQueryArgs` in one fixed order and asks for each name's value in turn, which nginx can do because `$arg_page` does not care where `page` appeared. Rename or reorder nothing and both spellings of a URL land on one file.

Each name carries the pattern its value must match, because the value becomes part of a filename — a list without patterns gets `^[A-Za-z0-9_.\-]{1,64}$`. A value your pattern rejects is a bypass, never a guess: `?page=abc` goes to PHP rather than quietly serving page one. Your pattern can only narrow the characters a filename may hold, never widen them.

Two more rules keep the readers honest. `?page=` counts as no query at all, and a **repeated** keyed arg bypasses — nginx reads the first `page=` and PHP the last, so `?page=1&page=2` has no answer both would give.

**Anything else** is a bypass. Add a name to `cacheQueryArgs` only when it changes the page, and to `ignoredQueryArgs` only when it does not.

Run `wp foehn cache:config --server=nginx --write` after changing either list: the snippet has the argument names compiled into it, and `wp foehn cache:status` will tell you when an installed snippet was generated from a different policy.

One asymmetry to know about: **Apache does not serve keyed args.** mod_rewrite cannot assemble the filename, so a request carrying one falls through to PHP, where the drop-in serves the right file a few milliseconds later. Correct, just not the fast path — `cache:status` says so when it applies.

### Nonces

**Pages containing nonces are cached like any other page.** State this to yourself plainly: a nonce frozen into a stored page expires with its 12–24 hour window, and a form submitted after that window fails until the page is re-rendered. Comment forms and front-end AJAX forms are affected.

Exclude them:

```php
return new PageCacheConfig(
    enabled: true,
    // By path…
    excludedPaths: ['/contact/', '/account/'],
    // …or by what the page contains, which catches a plugin's forms too.
    excludeWhenBodyContains: ['name="_wpnonce"'],
);
```

Hydrating nonces client-side is a later upgrade, to be taken up if real traffic shows the exclusions cost too much.

### Not supported in v1

Named here so nothing is assumed: keyed query args; device, scheme and consent variants; pre-compressed `.gz`/`.br`; cached feeds, sitemaps and REST responses; multisite; WooCommerce and any other cart-bearing page — exclude cart, checkout and account paths.

## Invalidation

Purging is what decides whether the feature is safe, so it is event-driven rather than left to the TTL.

Editing a post queues its permalink, the front page, the posts page, its post type archive, its author and month archives, every term archive it appears in, its ancestors (a parent page lists its children) and both adjacent posts (a single template renders previous/next links). Targets accumulate during the request and are acted on once, on `shutdown`, so a bulk edit does not run the same recursive delete forty times.

Hooked for you: `save_post`, `deleted_post`, `before_delete_post`, `wp_trash_post`, `untrash_post`, `attachment_updated`, `comment_post`, `transition_comment_status`, `edited_term`, `delete_term`.

The whole cache is flushed on `switch_theme`, `customize_save_after`, `wp_update_nav_menu`, `activated_plugin`, `deactivated_plugin`, a permalink structure change, an ACF options save, and an update to `home`, `siteurl`, `blogname`, `blogdescription`, `page_on_front`, `page_for_posts`, `show_on_front` or `posts_per_page`.

### Reaching it from your own code

```php
// Add a page only you know goes stale when a post changes.
add_filter('foehn/page_cache/purge_urls', function (array $urls, ?WP_Post $post): array {
    $urls[] = home_url('/our-numbers/');

    return $urls;
}, 10, 2);

// Or purge directly, from anywhere, with no class reference.
do_action('foehn/page_cache/purge_post', $postId);
do_action('foehn/page_cache/flush');
```

These actions are also the seam for a CDN purge integration.

### The gaps, stated

- **A renamed term leaves its old archive cached.** `edited_term` fires after the change, so only the new URL can be purged. The old one goes stale until the TTL sweep reaches it.
- **A page whose content depends on a post in a way the rules do not model goes stale.** The TTL and the sweep are the safety net, which is why they ship in v1 rather than later.
- **`.maintenance` is only seen by the PHP readers on a WordPress in a subdirectory.** Core writes the file next to `wp-settings.php`, and the server snippets can only test the document root above it. Maintenance windows are seconds long and plugin activation flushes the cache, so this is a note rather than a hazard.

## TTL and the sweep

`ttl` is the number of seconds a stored page stays servable, and `0` means "until something purges it".

Neither nginx's `try_files` nor `mod_rewrite` can look at a file's age, so on those two paths the TTL is enforced by an hourly sweep rather than per request. **With a `ttl` set, the sweep interval is the real bound on how stale a served page can be.** The drop-in enforces it exactly, because it is PHP.

## Deploying

```bash
composer install && wp foehn cache:config --write && wp foehn cache:clear
```

`composer install` already removes `wp-content/cache/foehn/` through the installer plugin, so `cache:clear` is belt and braces on a deploy that ran it. Traffic refills the cache; `wp foehn cache:warm` fills it up front if you would rather the first visitor did not pay for a render.

## Commands

| Command                                        | What it does                                                                                                |
| ---------------------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| `wp foehn cache:clear [--url=<url>]`           | Empty the cache, or drop one URL along with its `page/**` subtree.                                          |
| `wp foehn cache:status`                        | Whether it is on, whether it is on _here_, where it writes, what is in it, and which readers are installed. |
| `wp foehn cache:config --server=nginx\|apache` | Print the server config, or write it with `--write`.                                                        |
| `wp foehn cache:warm [--sync] [--limit=<n>]`   | Request every URL in the sitemap. Queued through Action Scheduler unless `--sync`.                          |

## Debugging

With `WP_DEBUG` on — or `debugHeaders: true` — every response says what happened and which reader decided it:

```
X-Foehn-Cache: HIT
X-Foehn-Cache-Via: nginx
```

```
X-Foehn-Cache: BYPASS
X-Foehn-Cache-Reason: cookie
X-Foehn-Cache-Via: php
```

`X-Foehn-Cache-Via` is the one to watch. A broken nginx snippet hides behind a working drop-in indefinitely, because both answer `HIT`; the only difference is the reader. If you expected `nginx` and see `php`, the include is not being matched.

Reasons are the machine-readable version of the table above: `disabled`, `environment`, `method`, `post-data`, `host`, `path`, `query-string`, `cookie`, `excluded-path`, `maintenance`, `cli`, `ajax`, `cron`, `rest`, `xmlrpc`, `do-not-cache`, `admin`, `feed`, `trackback`, `robots`, `embed`, `preview`, `customize-preview`, `search`, `logged-in`, `password-required`, `status`, `content-type`, `redirect`, `body-too-short`, `body-incomplete`, `body-excluded`, `expired`, `not-cached`.

Every stored page also ends with a marker, so "is this the cached one?" is answerable from a browser:

```html
<!-- foehn cache: 2026-08-19T14:48:19+00:00 -->
```

The stored file and the live response are byte-identical, marker included.

### Two things that will confuse you locally

- **Case is not folded.** nginx's `$uri` preserves case and no `map` can lowercase it, so lowercasing in PHP would be a permanent miss on the fast path. `/Blog/` and `/blog/` are two entries. On a case-insensitive filesystem — a macOS host running ddev — those two do collide on one file, which is a development-environment quirk and not what production does.
- **A template edit does not invalidate anything.** Purging is driven by content changes. This is why the cache is production-only by default; if you enable it locally, `wp foehn cache:clear` is the reload button.

## Hardening that comes with it

The generated snippets make everything under `wp-content/cache/` unreachable from outside — including any `.php` file written there, which the discovery cache does. That is worth having whether or not you use the page cache, and it is one of the reasons to install the server config rather than relying on the drop-in alone.

`cacheNotFound` is off by default, and not only because a cached 404 is rarely worth having: with it on, a crawler asking for a million made-up URLs writes a million directories. Turning it on wants a bound on the entry count that v1 does not provide.
