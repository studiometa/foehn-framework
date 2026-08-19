# Research: Static Page Cache

Date: 2026-08-19. Question: should Føhn ship a simple static page cache in the style of WP Rocket, where PHP writes HTML files and the web server (`.htaccess` / nginx) serves them directly?

## Verdict

**Yes, worth building — with a staged scope.**

The mechanism is small: write the response to a file, let the server serve that file. The difficulty is not the mechanism, it is the exclusion rules. Every real-world bug in this class of feature is the same bug: the wrong HTML served to the wrong visitor (someone else's logged-in header, a dead nonce, a stale cart). So the plan below puts the correctness surface first and the performance work last.

Føhn is a better home for this than a plugin, for three reasons that are specific to this codebase: purge rules are exactly what `#[AsAction]`/`#[AsFilter]` discovery is for; configuration belongs in a versioned `app/page-cache.config.php` next to the other config objects; and the "clear on `composer install`, refill on the next request" pattern already proven by the discovery cache applies unchanged.

## How the reference implementations do it

Both mainstream approaches were read rather than recalled.

**Cache Enabler / nginx** ([pothi/wordpress-nginx](https://github.com/pothi/wordpress-nginx/blob/main/globals/cache-enabler.conf)) uses the `error_page 418` trick to get branching inside `location /`, since nginx has no real `if/else`: each bypass condition `return 418`, which `error_page 418 = @cachemiss` routes to a named location that falls through to `index.php`. On the happy path, one `try_files` line does the work:

```nginx
try_files "/wp-content/cache/cache-enabler/$host${uri}index.html" $uri $uri/ /index.php$is_args$args;
```

Bypasses: `POST`; `?s=`, `?p=`, `?preview=true`, `amp`; cookies matching `wordpress_logged_in_`, `comment_author_`, `wp-postpass_`. Optional mobile branch via `error_page 419` to a `-mobile.html` variant.

**WP Rocket / rocket-nginx** ([SatelliteWP/rocket-nginx](https://github.com/SatelliteWP/rocket-nginx)) is the same idea with more variants and a debug story worth copying: a `$rocket_bypass` flag plus a `$rocket_reason` string, both surfaced as `X-Rocket-Nginx-Serving-Static: HIT|MISS|BYPASS` and `X-Rocket-Nginx-Reason` headers. It also handles pre-compressed files (`index.html_gzip` served with `gzip off; add_header Content-Encoding gzip`), an HTTPS-scheme suffix in the filename, a `.maintenance` check, and `Vary: Accept-Encoding, Cookie`.

Note what neither can do: nginx `try_files` cannot check a file's age, so **TTL cannot be enforced in the server config**. Expiry has to be a PHP-side sweep. WP Rocket also does not write nginx config itself — on nginx it serves through the `advanced-cache.php` drop-in instead, which is why rocket-nginx exists as a third-party project.

## Where this plugs into Føhn

| Need                      | Existing hook                                                                                                                                                                                                                                 |
| ------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Capture the response      | `template_redirect` → `ob_start()` with a flush callback. Føhn's `TemplateControllerDiscovery` sits on `template_include` (priority 5), inside that buffer, so nothing needs to change there.                                                 |
| Serve before WP boots     | `WP_CACHE = true` in the generated `wp-config.php` + a `wp-content/advanced-cache.php` drop-in, both produced by `WebRootGenerator`.                                                                                                          |
| Purge rules               | `#[AsAction]` on `save_post`, `deleted_post`, `wp_trash_post`, `comment_post`, `transition_comment_status`, `edited_term`, `wp_update_nav_menu`, `switch_theme`, `update_option_permalink_structure`, ACF options save (`AcfOptionsService`). |
| Deferred / scheduled work | `#[AsJob]` + `ActionSchedulerJobDispatcher` for warming; `#[AsCron]` for the TTL sweep.                                                                                                                                                       |
| Configuration             | `app/page-cache.config.php` returning a `PageCacheConfig`, same convention as `FoehnConfig`/`TimberConfig`/`RestConfig`.                                                                                                                      |
| CLI                       | `#[AsCliCommand]` — `wp foehn cache:clear`, `cache:status`, `cache:warm`, `cache:config`.                                                                                                                                                     |
| Clear on deploy           | `WebRootGenerator::clearDiscoveryCache()` already does this for discovery; extend it to the page cache directory.                                                                                                                             |
| Server config             | `.ddev/nginx_full/nginx-site.conf` for local; a generated snippet + docs for production. The starter currently ships **no** `.htaccess`.                                                                                                      |

## Design

### Cache key and layout

```
web/wp-content/cache/foehn/pages/{host}/{path}/index{-variant}.html[.gz|.br]
```

- `{host}` **must** be validated against the site host, not taken from the `Host` header. A cache path built from an attacker-controlled header is a cache-poisoning primitive. Compare to `WP_HOME` and refuse to write otherwise.
- `{path}` is normalised and restricted to a safe character set. Reject `..`, encoded slashes, and anything that leaves the cache root; verify the final `realpath()` is still inside it.
- Query strings: skip the cache by default. Allowlist the args that are genuinely part of the page identity (`page`, pagination) and strip tracking args (`utm_*`, `fbclid`, `gclid`, `mc_cid`) before keying, as Cache Enabler does.
- Variants only when a project needs them: scheme (behind a proxy that serves both), device, consent-cookie state. Every variant multiplies both the directory size and the number of ways to get it wrong — off by default.

### Write path

At buffer flush, cache only when **all** hold: `GET`; HTTP 200; `Content-Type: text/html`; not admin/login/cron/REST/AJAX/feed/sitemap/robots; `is_user_logged_in()` false and no `wordpress_logged_in_*` / `comment_author_*` / `wp-postpass_*` cookie; not a search, preview, password-protected post, or 404 (or a separate short-TTL bucket for 404s); `DONOTCACHEPAGE` not defined; no PHP error emitted; body over a sanity-minimum length. Write to a temp file and `rename()` so a reader never sees a partial page. Append a `<!-- foehn cache: {iso8601} -->` marker for debugging.

### Serve path, in order of cheapness

1. **nginx** — generated snippet, `try_files` on the cache file with the bypass conditions above. ~1 ms, PHP never starts.
2. **Apache** — generated `web/.htaccess` with `RewriteCond %{DOCUMENT_ROOT}...-f`, `REQUEST_METHOD =GET`, and the cookie/query bypasses.
3. **`advanced-cache.php`** — the portable fallback: reads the file and exits before WordPress loads. Slower than the server path (a few ms of PHP boot) but 10–20× faster than a full render, and it works on any host with no server access. Ship this first; it is what makes the feature useful without a sysadmin.

Emit `X-Foehn-Cache: HIT|MISS|BYPASS` plus a reason on every path. Without it, this feature is undebuggable in production.

### Purge

Two granularities: targeted (the post URL, its archives and terms, the home page, feeds, and any page whose query the post could appear in — in practice "the post plus the front page plus its archives") and full. Purge is an `unlink`/`rmdir` of a subtree, so it is cheap and needs no bookkeeping. A full purge on deploy, then let traffic or `cache:warm` refill.

TTL is a `#[AsCron]` sweep deleting files older than the configured age, because the server config cannot check age.

### The correctness list

This is the part that decides whether the feature is safe. Each item is either handled or documented as unsupported:

- **Nonces.** A cached page freezes `wp_nonce_field()` output; nonces expire in 12–24 h and the form then fails. Comment forms, search-with-nonce, and any front-end AJAX form are affected. Options: exclude pages containing a nonce, or fetch nonces client-side. Must be an explicit, documented decision.
- **Logged-in and personalised output.** Cookie bypass covers login. Anything personalised without a cookie (geo-IP, A/B test, consent banner state) either becomes a variant or the page is excluded.
- **Interactivity API / dynamic blocks.** Server-rendered dynamic blocks (recent posts, counters) go stale with the page. Either they purge with their data source or they move client-side.
- **WooCommerce / carts / forms.** Out of scope for v1; document that cart, checkout, and account pages must be excluded.
- **`.maintenance` file** and `WP_ENVIRONMENT_TYPE !== 'production'` disable caching.
- **Redirects and non-200s** are never cached.
- **Multisite** is out of scope for v1 (the `{host}` segment already leaves room for it).
- **Cache directory hardening.** Related finding worth acting on regardless of this feature: `wp-content/cache/foehn/` sits under the docroot and the discovery cache writes `.php` files there via `PhpFilesAdapter`, while the ddev nginx config only denies `.php` under `uploads`/`files`. A writable directory with executable PHP under the docroot should be denied outright — one `location` / `<Files>` rule in the shipped server config.

### Phasing

1. `PageCacheConfig`, capture + write, `advanced-cache.php` drop-in, purge hooks, `cache:clear`/`cache:status`, `X-Foehn-Cache` headers, exclusion rules with tests. Fully working, no server config needed.
2. Generated nginx snippet and `.htaccess`, plus a `cache:config` command that prints them and a deployment doc page (`docs/guide/page-cache.md`).
3. Pre-compressed `.gz`/`.br`, `cache:warm` (crawl the sitemap; the existing `RenderApi` can help), optional variants, TTL sweep.

Extend `tests/smoke/run.sh` in step 1: request the homepage twice, assert `MISS` then `HIT`, assert the file exists, then `save_post` and assert it is gone. That test is what keeps this feature honest.

Rough effort: phase 1 is 3–5 days including the exclusion tests; phase 2 is 1–2 days; phase 3 is 2–3 days.

## Alternatives considered

| Option                                                                 | Verdict                                                                                                                                                                                                                                                                                                                     |
| ---------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| nginx `fastcgi_cache` + purge module (EasyEngine / nginx-helper style) | Fastest and least PHP code, but needs `fastcgi_cache_path` in the server config on every host, a purge module for invalidation, and the cache is invisible to the application. Not portable enough for a framework default; document it as an option for teams that own their nginx.                                        |
| Cloudflare / Varnish full-page edge cache                              | Complementary, not a substitute — it does not help the origin's first hit, and purge is an API call per project. Worth documenting alongside; the same purge hooks can drive it later.                                                                                                                                      |
| An existing plugin (WP Rocket, Cache Enabler, WP Super Cache, Surge)   | Fine for a single site, and the honest baseline to compare against. Rejected as the framework default: config lives in the database rather than in code, purge rules cannot reach Føhn's own render path, and the deploy story is manual. Cache Enabler and rocket-nginx remain the reference implementations to copy from. |
| Do nothing                                                             | Defensible if TTFB is already low, thanks to the discovery cache and an object cache. But a static hit is ~1 ms against ~100 ms rendered, and it is what carries a traffic spike. The gain is real.                                                                                                                         |

## Interaction with the responsive image research

Two useful couplings, both pointing the same way:

- The page cache means on-demand image generation is paid only on a cache miss, which removes the main objection to generating variants at render time.
- The same "server serves a generated file directly" mechanism covers both features; generated image variants live in `uploads/foehn/` and must **not** be purged by a page-cache clear. Keep the two directories and the two clear commands separate.
