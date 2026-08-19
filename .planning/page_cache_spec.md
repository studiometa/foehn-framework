# Page Cache Spec: foehn

Companion to `.planning/research_page_cache.md`, which argued the case. This document specifies what to build.

## 1. Overview

A full-page cache: PHP writes the rendered HTML of anonymous `GET` requests to a file, and the request that follows is served from that file — by nginx or Apache when the server config is installed, by a WordPress drop-in otherwise.

Three properties define the feature:

1. **Correctness before speed.** The failure mode of a page cache is not slowness, it is serving the wrong HTML to the wrong visitor. Every rule below exists to make that impossible, and the eligibility rules are the part that gets the tests.
2. **One source of truth.** The bypass conditions, the cache key and the directory layout are described once, in a config object, and every consumer — the writer, the drop-in, the nginx snippet, the `.htaccess` block — is derived from it. Three implementations of "is this request cacheable" that drift apart is the defect this design is built to avoid.
3. **No new dependency.** Filesystem, `ob_start`, and hooks Føhn already owns.

### Decisions taken

| Decision             | Choice                                                                                                    |
| -------------------- | --------------------------------------------------------------------------------------------------------- |
| Serve paths in v1    | drop-in **and** generated nginx snippet **and** generated `.htaccess` block                               |
| Nonce-bearing pages  | Cached like any other page. The caveat is documented, and `excludeWhenBodyContains` is the opt-out.       |
| Invalidation         | Targeted purge, full-flush triggers, configurable TTL, cron sweep, and a manual flush command — all four. |
| Default state        | Disabled. Enabled per environment through `page-cache.production.config.php`.                             |
| Where the code lives | `studiometa/foehn` core (`src/PageCache/`), plus installer changes for the drop-in.                       |
| Query args           | Marketing args ignored; an allowlist keyed in canonical order; anything else bypasses.                    |

## 2. Directory layout and cache key

```
{WP_CONTENT_DIR}/cache/foehn/pages/{host}/{path}/index.html
```

`{host}` — the request host, **validated against the `WP_HOME` host on write**. A cache path built from an unchecked `Host` header is a cache-poisoning primitive; a request whose host does not match is a bypass, not a write. The host stays in the path so the read side (which cannot query WordPress) can build the same path, and so multisite fits later without a layout change.

`{path}` — the URI without its query string, **decoded once**, then validated. Decoded, because nginx's `$uri` is decoded and the three readers must agree on the filename; French slugs make this load-bearing (`/%C3%A0-propos/` and `/à-propos/` must resolve to one file).

Decoding is also what makes **invalidation** work, which is the higher-value reason. WordPress stores a non-ASCII slug with **lowercase** percent escapes — `utf8_uri_encode()` builds them with `dechex()`, and `sanitize_title_with_dashes()` lowercases — while a browser sends uppercase ones. So `get_permalink()` / `get_term_link()` / `get_author_posts_url()` hand the purger a _different spelling_ of the URL than the one the recorder was asked for. This was wp-super-cache bugs #1080 and #1081: the purge looked for a directory that did not exist, and category, tag and author archives served stale pages after every edit. Decoding collapses both spellings onto one file — but only if **exactly one** implementation does the decoding, which is why `Purger` resolves every URL through `CacheKey` and never through string handling of its own.

Validation, in order:

- reject a path that is not valid UTF-8
- reject the **whole path** on any character outside the allowlist `A-Za-z0-9 / _ . - ~` plus any non-ASCII codepoint. Never sanitize a bad path into a good one: `/x/:8080/evil` is a real probe, and a cache that rewrites a probe into a valid filename has agreed to store the attacker's page. This subsumes the earlier per-character rules — a space, `:`, `<`, `>`, a quote, `%`, `\`, a control character and the null byte all fail it
- reject `..` anywhere, and any segment that is only dots
- collapse repeated slashes, drop the trailing slash
- normalise `/index.php` to `/`, so the front controller is not a second cache entry for the home page
- reject a segment longer than 200 bytes, or a total longer than 512
- after building the absolute path, require `realpath()` of its parent to sit inside the cache root
- immediately before writing, require the assembled filename to match `/^index(__[A-Za-z0-9=&_.-]+)?\.html$/`. Redundant by construction, and checked anyway: the string that reaches a filename has passed through more hands than the one that was validated

**Case is not folded.** `$uri` preserves case and no nginx `map` can lowercase it, so lowercasing in PHP would be a permanent miss on the fast path. `/Blog/` and `/blog/` are two entries, which is wasteful and correct. The one place this bites is a case-insensitive filesystem — a macOS host running ddev — where the two do collide on one file; a development-environment caveat for the guide, not something to code around.

The trailing slash is dropped before the path is built, so `/foo` and `/foo/` map to one file and both permalink styles hit without depending on a canonical redirect. PHP trims it; nginx tries two candidate filenames instead, because it cannot trim `$uri` — see §5.1 for why a regex capture is not an option.

The `index__{variant}.html` slot holds the keyed query args (see below). Device and consent variants would use the same slot; neither ships in v1.

### Query strings

Three classes of argument, and every argument in a request falls into exactly one:

| Class         | Config             | Effect                                                                        |
| ------------- | ------------------ | ----------------------------------------------------------------------------- |
| **Ignored**   | `ignoredQueryArgs` | Dropped from the key. `?utm_source=newsletter` hits the same file as no args. |
| **Keyed**     | `cacheQueryArgs`   | Part of the filename, in canonical order.                                     |
| Anything else | —                  | Bypass. PHP decides.                                                          |

```
{path}/index.html                        # no query, or ignored args only
{path}/index__lang=fr&page=2&.html       # ?page=2&lang=fr — and ?lang=fr&page=2
```

**Order cannot matter, and no reader can sort.** The way out is to never read the query string left to right: every reader walks `cacheQueryArgs` in one fixed order — the order `PageCacheConfig::getCacheQueryArgs()` sorts them into — and asks for each name's value in turn. nginx can do that, because `$arg_page` is independent of where `page` appeared. The canonical order is therefore a property of the configuration rather than of the request, and the generated snippet is that same list unrolled. The trailing `&` is kept on the last pair as well: nginx cannot trim a variable, so the format keeps the separator instead of asking two readers to agree about removing it.

Each keyed arg carries the pattern its value must match, because the value becomes part of a filename. A project's pattern can only narrow the charset a filename may hold, never widen it. A present-but-invalid value is a bypass, never a sanitised guess.

```php
cacheQueryArgs: ['page' => '^[0-9]{1,6}$', 'lang' => '^[a-z]{2}$'],
cacheQueryArgs: ['page', 'lang'],   // shorthand: ^[A-Za-z0-9_.\-]{1,64}$
```

Three rules exist purely so the readers cannot disagree:

- **An empty value counts as absent.** `?page=` keys as no query at all.
- **A repeated keyed arg is a bypass.** nginx's `$arg_page` is the first `page=`, PHP's `$_GET['page']` the last. `?page=1&page=2` has no answer both would give, so neither gives one — including `?page=&page=2`, where nginx would otherwise read the empty one and serve the unpaginated page.
- **A present-but-invalid value bypasses rather than falling back.** Expressing this in nginx takes a sentinel (§5.1); without it, `?page=abc` builds no variant, falls back to `index.html` and serves page one. That was a real bug, found by the end-to-end suite and not by any unit test.

Keyed args are left empty by default. Every name is one the generated snippets must unroll, and WordPress paginates on `/page/2/` paths rather than `?page=2` — they are for filtered archives, `lang`, print views.

## 3. Configuration

`app/page-cache.config.php`, loaded by the existing `ConfigLoader` and therefore environment-suffixable (`page-cache.production.config.php`).

```php
use Studiometa\Foehn\Config\PageCacheConfig;

return new PageCacheConfig(
    enabled: true,
    ttl: 8 * HOUR_IN_SECONDS,
);
```

```php
final readonly class PageCacheConfig
{
    public function __construct(
        /** Master switch. Off by default: a cache nobody asked for is a bug. */
        public bool $enabled = false,
        /** Cache root. Defaults to WP_CONTENT_DIR . '/cache/foehn/pages'. */
        public ?string $path = null,
        /** Seconds a file stays servable. 0 = until something purges it. */
        public int $ttl = 0,
        /** max-age sent to the browser for cached HTML. 0 + must-revalidate keeps purges instant. */
        public int $browserMaxAge = 0,
        /** Environments where caching is allowed at all. */
        public array $environments = ['production'],
        /** A request carrying one of these cookie prefixes is never served or written. */
        public array $bypassCookies = ['wordpress_logged_in_', 'comment_author_', 'wp-postpass_'],
        /** Stripped before keying, so tracking parameters still hit. */
        public array $ignoredQueryArgs = [
            'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id',
            'gclid', 'fbclid', 'msclkid', 'mc_cid', 'mc_eid', '_ga', 'ref',
        ],
        /** URL path prefixes never cached. */
        public array $excludedPaths = [],
        /** Response bodies containing one of these substrings are not cached. */
        public array $excludeWhenBodyContains = [],
        /**
         * Cache 404s (own TTL bucket, always short). Off by default is also what stops
         * an attacker filling the disk with directories for millions of distinct 404
         * URLs; turning it on needs a bound on the entry count.
         */
        public bool $cacheNotFound = false,
        /** Emit X-Foehn-Cache headers. Defaults to WP_DEBUG. */
        public ?bool $debugHeaders = null,
    ) {}
}
```

Registration is a single switch: `Kernel::bootstrap()` wires the recorder and the purger when `enabled` is true and `wp_get_environment_type()` is in `environments`. Nothing to add to `FoehnConfig::hooks` — the framework's `#[AsAction]` classes are opt-in by design (`HookDiscovery::isPackageLocation()`), so page-cache hooks are wired directly by the kernel, the way `enqueue_block_editor_assets` already is.

## 4. Write path

`Recorder::register()` adds `template_redirect` at priority 0 and calls `ob_start($this->onFlush(...))`. Føhn's `TemplateControllerDiscovery` runs on `template_include` at priority 5, inside that buffer, so nothing about rendering changes.

`onFlush(string $body): string` returns the body untouched and, when the response is eligible, writes it. Eligibility — **all** must hold:

| Class    | Condition                                                                                                                                                                                                                                                                                                         |
| -------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Request  | method is `GET`; `$_POST` empty; host matches `WP_HOME`; path validates; no query string after stripping; no `bypassCookies` prefix present; path not in `excludedPaths`                                                                                                                                          |
| Context  | not `is_admin()`, `wp_doing_ajax()`, `wp_doing_cron()`, `REST_REQUEST`, `WP_CLI`; not `is_feed()`, `is_trackback()`, `is_robots()`, `is_embed()`, `is_preview()`, `is_customize_preview()`, `is_search()`; not `is_user_logged_in()`; not `post_password_required()`; no `.maintenance` file; environment allowed |
| Response | status 200 (or 404 with `cacheNotFound`); `Content-Type` is `text/html`; no `Location` header; `DONOTCACHEPAGE` undefined                                                                                                                                                                                         |
| Body     | length ≥ 255; ends with `</html>` (a fatal mid-render never gets stored); contains none of `excludeWhenBodyContains`                                                                                                                                                                                              |

Writing is atomic: a sibling `index.html.{uniqid}.tmp`, `chmod 0644`, then `rename()`. A reader never sees a partial page. Directories are created `0755`.

A trailing `<!-- foehn cache: {ISO 8601} -->` comment is appended to both the stored file and the live response, so the two are byte-identical and "which render is this?" is answerable from the browser.

### Nonces

Per the decision above, pages containing nonces are cached. The consequence, to be stated plainly in the guide: a nonce embedded in a cached page expires with its 12–24 h window, and a form submitted after that window fails until the page is re-rendered. A project whose contact page is a nonce-bearing form should exclude it:

```php
excludedPaths: ['/contact/'],
// or, plugin-wide:
excludeWhenBodyContains: ['name="_wpnonce"'],
```

Hydrating nonces client-side stays a later upgrade, to be taken up if real traffic shows the exclusions cost too much.

## 5. Read paths

Three readers, same layout, ordered by cost. All three emit `X-Foehn-Cache: HIT|MISS|BYPASS`, `X-Foehn-Cache-Reason: <slug>` and `X-Foehn-Cache-Via: nginx|apache|php` when debug headers are on. Without those headers this feature is undebuggable in production, and the smoke test has nothing to assert.

### 5.1 nginx

`wp foehn cache:config --server=nginx [--write]` renders **server-level statements plus one `location`** — no `location /`, so the include composes with whatever front controller block the site already has. This is the shape of `.ddev/nginx/prod-wp-rocket.conf` in `packages/wordpress/wordpress-project`, which has run unchanged in ddev and in production for years: a bypass flag decided by a sequence of `set`/`if`, then one `rewrite … last` at the stored file.

```nginx
set $foehn_bypass 1;

# $uri, not $request_uri: decoded and already free of the query string, which is the
# string PHP keys on. Interpolated whole, never through a regex capture — see below.
# The keyed args, in the configuration's canonical order. Six statements per arg where
# PHP needs two: nginx has no `and`, so "present *and* invalid" is spelled with a
# sentinel, and the statement order is the logic.
set $foehn_q "";
set $foehn_arg_page "empty";
if ($arg_page != "")                            { set $foehn_arg_page "invalid"; }
if ($arg_page ~ "^[0-9]{1,6}$")                 { set $foehn_arg_page "valid"; }
if ($arg_page ~ "[^A-Za-z0-9_.\-]|^.{65,}$")    { set $foehn_arg_page "invalid"; }
if ($foehn_arg_page = "valid")   { set $foehn_q "${foehn_q}page=$arg_page&"; }
if ($foehn_arg_page = "invalid") { set $foehn_bypass 0; }

set $foehn_variant "";
if ($foehn_q != "") { set $foehn_variant "__$foehn_q"; }
set $foehn_url "/wp-content/cache/foehn/pages/$host${uri}index$foehn_variant.html";
if (!-f "$document_root$foehn_url") {
    set $foehn_url "/wp-content/cache/foehn/pages/$host${uri}/index$foehn_variant.html";
}

if (!-f "$document_root$foehn_url")  { set $foehn_bypass 0; }
if ($request_method != GET)          { set $foehn_bypass 0; }
if ($args !~ "<known args>")         { set $foehn_bypass 0; }
if ($args ~ "(?:^|&)page=[^&]*&(?:.*&)?page=") { set $foehn_bypass 0; }
if ($http_cookie ~* "(wordpress_logged_in_|comment_author_|wp-postpass_)") { set $foehn_bypass 0; }
if (-f "$document_root/wp/.maintenance") { set $foehn_bypass 0; }

if ($foehn_bypass = 1) { rewrite ^ "$foehn_url" last; }

# Reached only through that rewrite. `internal` keeps stored files unrequestable from
# outside, and `^~` beats every regex location, so a .php written here is unreachable
# as well as unexecutable.
location ^~ /wp-content/cache/foehn/ {
    internal;
    etag on;
    add_header X-Foehn-Cache HIT;
    add_header X-Foehn-Cache-Via nginx;
    add_header Cache-Control "public, max-age=0, must-revalidate";
    add_header Vary "Cookie, Accept-Encoding";
}
```

Five details that are load-bearing rather than stylistic:

- **A regex capture on `$uri` comes back percent-encoded**, even though `$uri` itself is decoded. Deriving the path with `if ($uri ~ "^(.*?)/?$")` reads as the obvious way to drop a trailing slash, and it silently misses every non-ASCII URL: the page is stored under its decoded name and looked up under its encoded one, so nginx never serves an accented permalink and the drop-in quietly covers for it. `$uri` is interpolated whole, and the trailing slash costs a second `-f` test instead.
- **`set` inside `if` is why this is server-level.** Building a filename needs `set`, and inside a `location` a matched `if` continues in an implicit location that inherits no content handler — a `try_files` there is silently skipped, so every request carrying `?utm_source=` falls through to PHP while still answering `HIT`. That was the first version of this file, and the end-to-end suite is what caught it. At server level, `set` under `if` is ordinary rewrite-module behaviour.
- **The headers live in the cache location, not at server level.** PHP emits its own `MISS`/`BYPASS` with a reason; two writers of one header name produce a response carrying both.
- **`.maintenance` is at `$document_root/wp/.maintenance`**, because WordPress writes it to `ABSPATH` and core lives in `web/wp/`. The path is derived from `ABSPATH`, not hard-coded. A stock rocket-nginx config gets this wrong; `prod-wp-rocket.conf` does not.
- **A keyed arg's value is held to a charset floor** as well as to the project's pattern, since it lands in a filename.
- nginx cannot check a file's age, so **TTL is not enforced here**. Expiry belongs to the sweep (§7); with `ttl` set, the sweep interval is the real staleness bound.

### 5.2 Apache

`--server=apache` renders a marker-delimited block for `web/.htaccess`. The rewrite matches on `$1` from the pattern, not `%{REQUEST_URI}`, because `$1` is the decoded path — the same string nginx and PHP key on:

```apache
# BEGIN Foehn Page Cache — generated, do not edit
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    RewriteCond %{REQUEST_METHOD} =GET
    # Empty, or made only of ignored args. One generated alternation.
    RewriteCond %{QUERY_STRING} ^(?:(?:utm_source|gclid|…)(?:=[^&]*)?(?:&|$))*$
    RewriteCond %{HTTP:Cookie} !(wordpress_logged_in_|comment_author_|wp-postpass_) [NC]
    RewriteCond %{DOCUMENT_ROOT}/wp/.maintenance !-f
    RewriteCond %{DOCUMENT_ROOT}/wp-content/cache/foehn/pages/%{HTTP_HOST}/$1/index.html -f
    RewriteRule ^(.*?)/?$ /wp-content/cache/foehn/pages/%{HTTP_HOST}/$1/index.html [L]
</IfModule>
# END Foehn Page Cache
```

**Keyed query args are not served on this path**, and its guard is never widened to include them. Assembling a canonical filename from `%{QUERY_STRING}` in mod_rewrite would take a rule per permutation, and serving the unkeyed `index.html` for `?page=2` would hand a visitor page one. So Apache covers the no-args and ignored-args cases — the overwhelming majority — and a request carrying a keyed arg falls through to PHP, where the drop-in serves the right file a few milliseconds later. `cache:status` reports this whenever `cacheQueryArgs` is set and the Apache block is installed, so it cannot be mistaken for a cache bug.

The generated file also carries WordPress's own permalink block (the starter ships no `.htaccess` today and `DISALLOW_FILE_MODS` stops WordPress writing one), `Options -Indexes`, and a `<FilesMatch>` denying `.php` under the cache directory.

### 5.3 The drop-in

`web/wp-content/advanced-cache.php`, generated by the installer, loaded by `wp-settings.php` when `WP_CACHE` is true. Two facts make it thin: the generated `wp-config.php` has already required `vendor/autoload.php`, and it has already defined `WP_HOME` and `WP_CONTENT_DIR`. So the drop-in resolves its config and delegates:

```php
<?php
// Generated by foehn-installer. DO NOT EDIT.
$config = require '{{ configPath }}';   // app/page-cache*.config.php, absolute

if ($config instanceof Studiometa\Foehn\Config\PageCacheConfig) {
    Studiometa\Foehn\PageCache\Server::serve($config);
}
```

`Server::serve()` runs the request-side rules of §4, resolves the file, checks `filemtime() + ttl`, then sends `Content-Type`, `Last-Modified`, `ETag`, honours `If-Modified-Since`/`If-None-Match` with a `304`, `readfile()`s and exits. On any bypass or miss it returns and WordPress boots normally. Cost on a hit: autoloader plus two `stat()`s, single-digit milliseconds against a full render.

`WP_CACHE` is defined unconditionally by the generator — a true `WP_CACHE` with no drop-in is inert, and with a drop-in whose config says `enabled: false` it returns immediately.

## 6. Invalidation

`Purger` collects targets in a set during the request and acts once on `shutdown`, so a bulk edit or an import does not run the same recursive delete forty times. During `wp_importing`, purges are skipped and a full flush is queued instead.

Purging a URL deletes `index*.html` in its directory. Purging an archive URL also deletes its `page/**` subtree, because `/blog/page/2/` is stale whenever `/blog/` is.

**Targeted** — `purgePost(int $id)` resolves: the permalink; the front page and the posts page; the post type archive; every term archive of the post's terms; the author archive; the month archive; every **ancestor** (`get_post_ancestors()` — a parent page listing its children goes stale when a child is renamed); and both **adjacent posts** (`get_adjacent_post()` — a single template that renders previous/next links goes stale on both sides of an insertion). If a post carries more than 50 terms, it falls back to a full flush rather than walking a long tail.

The resolved list passes through a `foehn/page_cache/purge_urls` filter, receiving the URLs and the `WP_Post`, before anything is queued. A project can add the page whose staleness only it knows about, or drop one it knows is safe. WP Rocket exposes this as a settings field; a filter is the Føhn idiom.

Hooks: `save_post`, `deleted_post`, `wp_trash_post`, `untrash_post`, `attachment_updated`, `comment_post`, `transition_comment_status`, `edited_term`, `delete_term`.

**Full flush** — `switch_theme`, `customize_save_after`, `wp_update_nav_menu`, `activated_plugin`, `deactivated_plugin`, `update_option_permalink_structure`, and `updated_option` for an allowlist (`home`, `siteurl`, `blogname`, `blogdescription`, `page_on_front`, `page_for_posts`, `show_on_front`, `posts_per_page`). ACF options pages flush through `acf/save_post` when the post id is `options`.

Escape hatches: the `foehn/page_cache/purge_post` and `foehn/page_cache/flush` actions for third-party code, and `wp foehn cache:clear`.

Deploy: `WebRootGenerator` clears the page cache on `composer install`, exactly as it already clears the discovery cache. The documented deploy sequence becomes `composer install && wp foehn cache:config --write && wp foehn cache:clear`.

## 7. TTL and the sweep

`Sweeper`, `#[AsCron(CronInterval::Hourly)]`, deletes files older than `ttl` and prunes the directories left empty. It returns immediately when `ttl` is 0 or the cache is disabled. Framework `#[AsCron]` classes are not location-gated, so this schedules itself; the guard inside is what keeps it inert on sites that never enabled the feature.

The drop-in enforces `ttl` per request as well. nginx and Apache cannot, which is exactly why the sweep exists rather than being an optimisation.

## 8. CLI

| Command                                        | Behaviour                                                                                                                         |
| ---------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------- |
| `wp foehn cache:clear [--url=<url>]`           | Full flush, or one URL.                                                                                                           |
| `wp foehn cache:status`                        | Enabled/disabled, resolved path, file count, bytes, oldest and newest entry, which read paths are installed.                      |
| `wp foehn cache:config --server=nginx\|apache` | Prints the snippet. `--write` writes `config/nginx/foehn-page-cache.conf` / the `.htaccess` block in place.                       |
| `wp foehn cache:warm [--sync]`                 | Walks the core sitemap and requests each URL cookie-free with `X-Foehn-Warm: 1`; batched through `JobDispatcher` unless `--sync`. |

`cache:config` is a WP-CLI command, not an installer step, because the snippets bake in policy (cookies, ignored args) that only the loaded config knows — while the installer knows paths only. That split is what keeps the four readers consistent.

## 9. Files

```
packages/foehn/src/
├── Config/PageCacheConfig.php
├── PageCache/
│   ├── Bypass.php            # shared eligibility rules + BypassReason enum
│   ├── CacheKey.php          # request → validated relative path
│   ├── CacheDirectory.php    # containment, tree deletion, upward pruning
│   ├── DebugHeaders.php      # X-Foehn-Cache / -Reason / -Via
│   ├── Store.php             # atomic put/get/forget/flush/sweep/stats
│   ├── Recorder.php          # template_redirect + ob_start, write side
│   ├── PurgeTargets.php      # which URLs one change makes stale
│   ├── Purger.php            # batching, shutdown flush
│   ├── Sweeper.php           # #[AsCron] TTL sweep
│   ├── Server.php            # pre-WordPress serve, used by the drop-in
│   └── ServerConfig/{NginxSnippet.php,ApacheSnippet.php}
└── Console/Commands/PageCache{Clear,Status,Config,Warm}Command.php

packages/installer/src/WebRootGenerator.php   # + drop-in, + WP_CACHE, + clearPageCache()
packages/starter/theme/app/page-cache.config.php
packages/starter/tests/smoke/                       # Pest suite; generates its own nginx include
docs/guide/page-cache.md
docs/api/page-cache-config.md
```

`Bypass` is one class with three entry points, ordered by what the caller is allowed to know: `forRequest()` (superglobals and the filesystem only, safe before WordPress), `forContext()` (calls `forRequest()`, then adds the WordPress-aware checks) and `forResponse()` (calls `forContext()`, then adds status, headers and body). One implementation, one test suite, no drift between the writer and the drop-in.

The middle one exists because the writer needs an answer _before_ `ob_start()`: wrapping a feed or a REST response in an output buffer it will then throw away is a behaviour change this feature has no business making.

## 10. Tests

Unit, against the existing WP function stubs: `CacheKey` (accented paths in both percent-escape cases, the character allowlist, traversal attempts, trailing slashes, over-long segments, `/index.php`, case preservation, the filename pattern, host mismatch), `Bypass` (every row of the §4 table), `CacheDirectory` (containment: the root itself, a path that does not exist yet, a stripped trailing slash, a sibling directory sharing a prefix, a symlink escape, an empty string), `Store` (atomicity, permissions, sweep), `Purger` (target resolution per post type, ancestors, adjacent posts, the `purge_urls` filter, the 50-term fallback, shutdown batching), `NginxSnippet`/`ApacheSnippet` (characterization: the condition list, each cookie prefix, the maintenance path, and the absence of any user-agent rule — string-generated config is easy to break silently).

Smoke, extending `packages/starter/tests/smoke/run.sh` — this is what keeps the feature honest, because unit tests cannot prove a web server reads what PHP wrote:

1. request the homepage twice → `MISS` then `HIT`, and `X-Foehn-Cache-Via: nginx`
2. the file exists at the expected path
3. request with a `wordpress_logged_in_` cookie → `BYPASS`, no file written
4. request an accented permalink → `HIT` on the second call
5. `wp post update` → the file is gone, next request is a `MISS`
6. `?utm_source=x` → `HIT`; `?foo=bar` → `BYPASS`
7. two ignored args in either order → the same file, through nginx and through the drop-in
8. the drop-in serves with the nginx include removed → `HIT` with `X-Foehn-Cache-Via: php`

Every hit is asserted **by body identity as well as by header**: the `<!-- foehn cache: {ISO 8601} -->` marker makes two renders differ, so a real hit means the two bodies are byte-identical and a bypass means they are not. A correct `HIT` header on a freshly rendered body is a failure mode headers alone do not catch.

## 11. Out of scope for v1

Named here so nobody assumes otherwise: keyed query args; device, scheme and consent variants; pre-compressed `.gz`/`.br`; cached feeds, sitemaps and REST responses; multisite; WooCommerce and other cart-bearing pages; client-side nonce hydration; CDN purge integration (the `foehn/page_cache/*` actions are the seam for it).

## 12. Risks

- **A purge rule nobody wrote.** Any page whose content depends on a post in a way §6 does not model goes stale. The TTL and sweep are the safety net; that is precisely why they are in v1 rather than deferred.
- **nginx `if` semantics.** `if` inside `location` is famously sharp-edged. The snippet stays within the documented-safe forms (`return`, `try_files`) and is generated, never hand-edited, so a project cannot half-modify it.
- **Config drift between the four readers.** Mitigated by generation from one config object plus the smoke test asserting `Via: nginx`. It remains the thing most likely to bite, so `cache:status` reports which readers are installed and whether their snippets match the current config hash.
- **Taking over `nginx-site.conf`** costs ddev's future updates to that file. Accepted deliberately, in exchange for testing the fast path locally.

## 13. Phases

1. `PageCacheConfig`, `Bypass`, `CacheKey`, `Store`, `Recorder`, `Purger`, debug headers, `cache:clear` / `cache:status`, unit tests. The cache fills and invalidates correctly; nothing serves it yet, so a bug here cannot reach a visitor.
2. `Server`, the installer drop-in and `WP_CACHE`, with per-request TTL. End to end on any host, no server config needed.
3. `Sweeper`, `NginxSnippet`, `ApacheSnippet`, `cache:config`, the starter's `nginx-site.conf` takeover, the smoke test, `docs/guide/page-cache.md`. The fast path, shipped together with the sweep that bounds its staleness.
4. `cache:warm`, and the API doc page.

Invalidation lands in phase 1 on purpose: a cache that serves stale pages is worse than no cache, so the purge rules exist before anything reads a file. Estimate: 4–5 days, 1–2 days, 2–3 days, 1 day.
