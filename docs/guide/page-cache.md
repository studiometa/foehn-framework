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

| Class    | Rule                                                                                                                                                                                                                                                          |
| -------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Request  | method is `GET`; no `$_POST`; `Host` matches `WP_HOME`; the path validates; every query arg is ignored or a valid non-repeated keyed arg; none of `bypassCookies`; not under `excludedPaths`; no `.maintenance` file; not WP-CLI, REST, AJAX, cron or XML-RPC |
| Context  | not admin, feed, trackback, robots, embed, preview, customizer preview or search; nobody logged in; no password-protected post; `DONOTCACHEPAGE` undefined                                                                                                    |
| Response | status 200 (or 404 with `cacheNotFound`); `Content-Type` is `text/html`; no `Location` header                                                                                                                                                                 |
| Body     | at least 255 bytes; ends with `</html>`, so a render that died mid-template is never frozen; contains none of `excludeWhenBodyContains`                                                                                                                       |

A [section response](/guide/section-rendering) is a fragment rather than a document, so the body rule reads differently for it: no minimum length, and it has to end with the `</div>` every section is wrapped in. Everything else on this list applies to it unchanged.

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

Request order does not matter, and that is the interesting part: no reader sorts the query string. The configuration sorts `cacheQueryArgs` by name, then every reader asks for each value in that normalized order. nginx can do this because `$arg_page` does not care where `page` appeared, so both spellings of a URL land on one file. The order used in the configuration file has no effect.

Each name carries the pattern its value must match, because the value becomes part of a filename — a list without patterns gets `^[A-Za-z0-9_.,\-]{1,64}$`. A value your pattern rejects is a bypass, never a guess: `?page=abc` goes to PHP rather than quietly serving page one. Your pattern can only narrow the characters a filename may hold, never widen them.

Two more rules keep the readers honest. `?page=` counts as no query at all, and a **repeated bare name** bypasses — nginx reads the first `page=` and PHP the last, so `?page=1&page=2` has no answer both would give.

### Filters with more than one value

A filter has two spellings, and both are cached:

```
?genre=rock,jazz            ─┐
                             ├─→  …/index__genre=rock,jazz&.html
?genre[]=rock&genre[]=jazz  ─┘
```

The comma form is what [the query filters](/guide/query-filters) emit and what `$arg_genre` can read, so nginx serves it. The bracketed form is what a checkbox group posts, and nginx cannot read it at all — a variable name may not hold brackets, and there is no `$arg_genre[]`.

So nginx **declines** it rather than guessing: a bracketed name is not a name it was told about, the request goes to PHP, and the drop-in joins the members and serves the file the comma form wrote. Same file, a couple of milliseconds slower. What never happens is nginx reading `$arg_genre`, finding it empty and serving the unfiltered page to someone who asked for a filtered one.

Two consequences worth knowing:

- **The members are joined in request order and never sorted**, so `?genre[]=jazz&genre[]=rock` is a second file holding the same HTML. Sorting is the obvious fix and the wrong one: nginx cannot sort, so a sorted key is one only PHP could compute, and the two readers would part company on the first URL that arrived unsorted. A form emits its checkboxes in document order, so in practice one spelling occurs.
- **A member may not contain a comma.** `?genre[]=rock,jazz` asks for one term whose slug has a comma in it; `?genre=rock,jazz` asks for two terms. Joining the first would key it where the second lives, so it bypasses instead.
- **The comma stays a comma, and Føhn has to insist on that.** WordPress rebuilds the query string of a paginated URL with its values encoded, so `/archive/page/2/?genre=rock,jazz` is answered with a 301 to `?genre=rock%2Cjazz` — and nginx keys the raw query string, so it would then look for `index__genre=rock%2Cjazz&.html` while the recorder wrote `index__genre=rock,jazz&.html`. Two readers, two filenames, and a cache that quietly stops being read. So `PageCache\CanonicalRedirect` cancels a canonical redirect whose only change is that encoding, and keeps the literal comma in one that does something else. It is registered in every environment, because the URLs are the same in every environment.

### Listing values instead of writing a pattern

Most arguments have a handful of legal values, and a project knows them. Say so, and the pattern is compiled for you:

```php
cacheQueryArgs: [
    'page',                                     // any value the charset allows
    'lang' => ['fr', 'en'],                     // only these two
    'posts_per_page' => [12, 24, 48],
    'genre' => '^[a-z0-9-]+(?:,[a-z0-9-]+)*$',  // a pattern, when a list will not do
],
```

`?lang=de` is then a bypass, not a stored file. Values are quoted, so `1.5` matches `1.5` and not `165`, and an empty list matches nothing at all — "these values are allowed" with none named is a bypass rather than a free pass.

Naming a filter here is a separate step from declaring it in [query filters](/guide/query-filters), and deliberately so: the two files stay independent, and the cache never keys an argument because something else declared it. The cost is that a filter you add later is a bypass until you name it here — and a bypass reads as a slow page rather than as an error, so add the two together.

### Caching search results

`?s=` bypasses by default, and the reason is the key space rather than the query: a search takes any string a visitor can type, so keying it is one stored file per phrase anybody ever searches for, and a crawler can write them all.

Naming `s` in `cacheQueryArgs` is the opt-in, and it cannot be given without the pattern that bounds it:

```php
cacheQueryArgs: ['s' => '^[A-Za-z0-9-]{2,32}$'],
```

`s` then behaves like any other keyed arg — nginx unrolls it, the value lands in the filename, and a phrase the pattern refuses is served by WordPress exactly as every search is today. Bound it deliberately: the pattern is the only thing standing between a search box and a directory with a file per phrase.

### Caching section responses

`foehn_sections` is keyed on every configuration, with no setting to turn on. A [section request](/guide/section-rendering) asks for the HTML of named regions of a page instead of the whole document, so it is a different response for the same URL and gets a file of its own:

```
/products/?foehn_sections=listing,pagination  →  …/products/index__foehn_sections=listing,pagination&.html
```

That is what makes filtering and paginating in place cheap: the second visitor to ask for the same selection is served off a file, by nginx, with no PHP at all.

The key space is bounded without anybody bounding it. The grammar comes from the parser — lowercase `[a-z0-9-]` names, comma-separated, at most five — and a name no template declared is a 404 while a name outside the grammar is a 400. Only 200s are stored, so the files that can exist are the section combinations your templates actually declare.

Two things are not yours to change. A project **cannot ignore** `foehn_sections`: an ignored one would key a section request onto the whole page's file, so one visitor would ask for a fragment and be handed a page. And a project **cannot give it a pattern** — a widened one would key values the parser refuses.

A section response always carries `X-Robots-Tag: noindex, nofollow`, cached or not: a fragment indexed on its own is a search result that leads to half a page. The drop-in replays it from the stored headers; the nginx snippet cannot read those, so it derives the same header from `$arg_foehn_sections` instead. Apache never serves a section request at all — `foehn_sections` is a keyed arg, and mod_rewrite cannot key one — so it falls through to the drop-in, which does replay it.

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

Named here so nothing is assumed: device, scheme and consent variants; pre-compressed `.gz`/`.br`; cached feeds, sitemaps and REST responses; multisite; WooCommerce and any other cart-bearing page — exclude cart, checkout and account paths.

## Invalidation

Purging is what decides whether the feature is safe, so it is event-driven rather than left to the TTL.

Everything that deletes a stored page at runtime goes through one service, `PageCache\Invalidator`, with three operations: clear everything, clear the section-cache entries only, clear one URL with every variant it owns. The WordPress hooks below, `wp foehn cache:clear` and the admin controls are all callers of it. Two of them used to build a cache key from a URL with their own `parse_url()`, which is how a WP-CLI clear and an automatic purge come to disagree about which files one URL owns — and the one that disagrees quietly is the one that leaves a stale page up.

Every operation reports the same number: **stored response bodies deleted**. An entry is a body plus, usually, a headers sidecar, and the sidecar goes with its body without being counted.

Clearing one URL takes its page, its cached 404, its keyed query variants and its section fragments. Clearing the section cache takes only the variants keyed by `foehn_sections` — the whole pages and the other keyed variants sitting in the same directory are left alone.

Invalidation keeps working while `enabled` is `false`. A release that had the cache on leaves files behind, and the project switching it off is exactly the one who needs them gone.

It is the static page cache and nothing else. The discovery cache, transformed images, application transients and PHP's OPcache have their own lifecycles, and a content edit is not a reason to clear any of them.

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

## What a stored entry holds

A stored entry is a body, its status, and the headers the response set for itself:

```
example.com/blog/index.html                 the body
example.com/blog/index.html.headers         what that response sent, minus what the cache owns
example.com/blog/index--404.html            a 404 for the same URL, when `cacheNotFound` is on
```

### The status

A body cannot say what status it was sent with, so the name does. A 404 is stored as `index--404.html`, and the drop-in serves it as a 404.

**nginx never serves one.** The generated snippet only ever builds `index.html` and `index__variant.html`, so it does not find the 404, the request reaches PHP, and PHP answers with the right status. nginx cannot set a status on a static response without `error_page`, and this way it does not have to. The cost is that cached 404s are as fast as the drop-in, not as fast as nginx — which is the right trade for a page nobody should be reaching often.

A keyed variant can never be mistaken for a 404: a variant always ends with `&`, because each keyed argument appends one.

### The headers

The response's own headers — a `Link:` preload, a page-specific `Content-Security-Policy`, an `X-Robots-Tag` — are stored beside the body and replayed on a hit. Without that, they appeared on the miss and vanished on every hit, so the same URL answered differently depending on cache state.

Some are never stored:

| Not stored                                                                                        | Why                                                                                                                                                                              |
| ------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Set-Cookie`                                                                                      | A cookie belongs to the visitor it was minted for. Replaying one would hand that session to everybody who asked for the page next. This is a security boundary, not a preference |
| `Cache-Control`, `ETag`, `Last-Modified`, `Content-Length`, `Content-Type`, `Vary`, `Date`, `Age` | The cache computes these for the file it is actually sending; a recorded copy would contradict it                                                                                |

Every line is validated when written **and** when read, because the file sits on a disk between those two moments.

**Only the drop-in replays them.** nginx's static file module has no notion of an embedded header block, and `proxy_cache`'s stored format is nginx's own — written and read by the same module, not an interchange format. So the fast path sends the headers the snippet derives from your configuration, and the drop-in sends those plus what was recorded. If a page depends on a header it sets itself, that page is faster to exclude than to reason about.

The one header the snippet derives rather than leaves behind is the `X-Robots-Tag` of a section response, because a cached fragment that lost its `noindex` would be indexed as if it were a page.

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
| `wp foehn cache:clear [--url=<url>]`           | Empty the cache, or drop one URL along with its `page/**` subtree. Reports pages, not files.                |
| `wp foehn cache:status`                        | Whether it is on, whether it is on _here_, where it writes, what is in it, and which readers are installed. |
| `wp foehn cache:config --server=nginx\|apache` | Print the server config, or write it with `--write`.                                                        |
| `wp foehn cache:warm [--sync] [--limit=<n>]`   | Request every URL in the sitemap. Queued through Action Scheduler unless `--sync`.                          |

The same operations are available from the browser, on the Føhn admin page and in the admin bar — see [Admin Cache Controls](/guide/admin-cache-controls).

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
