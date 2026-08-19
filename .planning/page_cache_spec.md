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

## 2. Directory layout and cache key

```
{WP_CONTENT_DIR}/cache/foehn/pages/{host}/{path}/index.html
```

`{host}` — the request host, **validated against the `WP_HOME` host on write**. A cache path built from an unchecked `Host` header is a cache-poisoning primitive; a request whose host does not match is a bypass, not a write. The host stays in the path so the read side (which cannot query WordPress) can build the same path, and so multisite fits later without a layout change.

`{path}` — the URI without its query string, **decoded once**, then validated. Decoded, because nginx's `$uri` is decoded and the three readers must agree on the filename; French slugs make this load-bearing (`/%C3%A0-propos/` and `/à-propos/` must resolve to one file). Validation, in order:

- reject any of `..`, `\`, `%`, a control character, or a null byte in the decoded value
- reject a path that is not valid UTF-8
- collapse repeated slashes, drop the trailing slash
- reject a segment longer than 200 bytes, or a total longer than 512
- after building the absolute path, require `realpath()` of its parent to sit inside the cache root

On write, `/foo` and `/foo/` both map to `…/foo/index.html`. On read, two candidates are tried (`${uri}index.html` and `${uri}/index.html`) so both permalink styles hit without a redirect round-trip.

The `index.html` filename reserves a variant slot (`index-{variant}.html`) for device or consent variants. **No variants ship in v1.**

### Query strings

Args listed in `ignoredQueryArgs` are stripped before keying, so `?utm_source=…` still hits the cache. **Any remaining query string is a bypass.** Keyed query args are deliberately out of scope: nginx cannot compute a hash, so supporting them would mean the drop-in and the server snippet disagreeing about which file to read — the exact failure this design refuses. Pagination is unaffected, because WordPress paginates on `/page/2/` paths.

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
        /** Cache 404s (own TTL bucket, always short). */
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

`wp foehn cache:config --server=nginx [--write]` renders a `location /` block from the live config. Branching uses the `error_page 418` idiom, because nginx has no `else`:

```nginx
# Generated by `wp foehn cache:config --server=nginx`. Do not edit.
location / {
    error_page 418 = @foehn_miss;
    recursive_error_pages on;

    if ($request_method != GET)                        { return 418; }
    if ($args != "")                                   { return 418; }   # after the ignore map
    if ($http_cookie ~* "(wordpress_logged_in_|comment_author_|wp-postpass_)") { return 418; }
    if (-f "$document_root/.maintenance")              { return 418; }

    try_files "/wp-content/cache/foehn/pages/$host${uri}index.html"
              "/wp-content/cache/foehn/pages/$host${uri}/index.html"
              $uri $uri/ /index.php$is_args$args;
}

location @foehn_miss {
    try_files $uri $uri/ /index.php$is_args$args;
}

location ~ ^/wp-content/cache/foehn/.*\.html$ {
    internal;
    add_header X-Foehn-Cache HIT;
    add_header X-Foehn-Cache-Via nginx;
    add_header Cache-Control "public, max-age=0, must-revalidate";
    add_header Vary "Cookie, Accept-Encoding";
}

# Nothing under the cache directory is ever executable.
location ~ ^/wp-content/cache/.*\.php$ { deny all; }
```

Two consequences to document rather than hide:

- The block **replaces** the site's `location /`. Two `location /` blocks is an nginx startup error, so this is an include inside `server{}` and the stock WordPress one must go. For the starter that means taking over `.ddev/nginx_full/nginx-site.conf` (removing the `#ddev-generated` marker and losing ddev's future edits to it). That is the price of having the nginx path be the one that runs locally and in the smoke test, and it is worth paying.
- nginx `try_files` cannot check a file's age, so **TTL is not enforced on this path**. Expiry belongs to the sweep (§7). With `ttl` set, the sweep interval is the real staleness bound.

`ignoredQueryArgs` is rendered as a `map $args $foehn_args` block that blanks a query string made only of ignored args, in the manner of rocket-nginx.

### 5.2 Apache

`--server=apache` renders a marker-delimited block for `web/.htaccess`. The rewrite matches on `$1` from the pattern, not `%{REQUEST_URI}`, because `$1` is the decoded path — the same string nginx and PHP key on:

```apache
# BEGIN Foehn Page Cache — generated, do not edit
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    RewriteCond %{REQUEST_METHOD} =GET
    RewriteCond %{QUERY_STRING} ^$
    RewriteCond %{HTTP:Cookie} !(wordpress_logged_in_|comment_author_|wp-postpass_) [NC]
    RewriteCond %{DOCUMENT_ROOT}/.maintenance !-f
    RewriteCond %{DOCUMENT_ROOT}/wp-content/cache/foehn/pages/%{HTTP_HOST}/$1/index.html -f
    RewriteRule ^(.*?)/?$ /wp-content/cache/foehn/pages/%{HTTP_HOST}/$1/index.html [L]
</IfModule>
# END Foehn Page Cache
```

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

**Targeted** — `purgePost(int $id)` resolves: the permalink; the front page and the posts page; the post type archive; every term archive of the post's terms; the author archive; the month archive. If a post carries more than 50 terms, it falls back to a full flush rather than walking a long tail.

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
│   ├── Store.php             # atomic put/get/forget/flush/sweep/stats
│   ├── Recorder.php          # template_redirect + ob_start, write side
│   ├── Purger.php            # targets, batching, shutdown flush
│   ├── Sweeper.php           # #[AsCron] TTL sweep
│   ├── Server.php            # pre-WordPress serve, used by the drop-in
│   └── ServerConfig/{NginxSnippet.php,ApacheSnippet.php}
└── Console/Commands/PageCache{Clear,Status,Config,Warm}Command.php

packages/installer/src/WebRootGenerator.php   # + drop-in, + WP_CACHE, + clearPageCache()
packages/starter/theme/app/page-cache.config.php
packages/starter/.ddev/nginx_full/nginx-site.conf   # taken over
docs/guide/page-cache.md
docs/api/page-cache-config.md
```

`Bypass` is one class with two entry points — `forRequest()` (superglobals only, safe before WordPress) and `forResponse()` (calls `forRequest()`, then adds the WordPress-aware checks). One implementation, one test suite, no drift between the writer and the drop-in.

## 10. Tests

Unit, against the existing WP function stubs: `CacheKey` (accented paths, traversal attempts, trailing slashes, over-long segments, host mismatch), `Bypass` (every row of the §4 table), `Store` (atomicity, permissions, sweep, containment), `Purger` (target resolution per post type, the 50-term fallback, shutdown batching), `NginxSnippet`/`ApacheSnippet` (rendering from config).

Smoke, extending `packages/starter/tests/smoke/run.sh` — this is what keeps the feature honest, because unit tests cannot prove a web server reads what PHP wrote:

1. request the homepage twice → `MISS` then `HIT`, and `X-Foehn-Cache-Via: nginx`
2. the file exists at the expected path
3. request with a `wordpress_logged_in_` cookie → `BYPASS`, no file written
4. request an accented permalink → `HIT` on the second call
5. `wp post update` → the file is gone, next request is a `MISS`
6. `?utm_source=x` → `HIT`; `?foo=bar` → `BYPASS`

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
