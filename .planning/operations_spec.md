# Operational safeguards and cache controls

A focused set of production safeguards and operator controls built on Føhn's page cache, generated WordPress configuration, and Docker runtime.

This specification covers:

1. unified page-cache invalidation;
2. non-production indexing protection;
3. real WP-Cron heartbeat reporting;
4. a Føhn admin dashboard and admin-bar cache controls.

Deployment and update verification use these features but are specified in [diagnostics-command-spec.md](diagnostics-command-spec.md).

## 1. Decisions

- Føhn owns page-cache invalidation through one service. WordPress hooks, WP-CLI, and admin actions must not implement filesystem deletion separately.
- Non-production sites are non-indexable by environment policy. The policy does not write a persistent WordPress option that can leak into production.
- The production Docker runtime runs WordPress cron. A successful run records a heartbeat that deployment verification can inspect.
- Føhn provides a small operational admin page. It is not a settings framework and does not add client branding.
- Cache mutations require an authorized user, a nonce, and a POST request.
- `WP_ENVIRONMENT_TYPE` and `WP_DEBUG` are the resolved sources of environment information. The framework does not read `.env` directly at runtime because production can inject environment variables without a file.
- Multisite is outside this iteration, as it is for the current page cache.

## 2. Shared environment resolution

`Studiometa\Foehn\Helpers\Env` becomes the canonical environment seam for indexing protection, page-cache eligibility, the admin dashboard, and production verification.

Resolution order:

1. `wp_get_environment_type()` when WordPress provides it;
2. the `WP_ENVIRONMENT_TYPE` constant;
3. the `WP_ENVIRONMENT_TYPE` environment variable;
4. `production`.

`Env::isDebug()` returns the resolved boolean value of `WP_DEBUG`. `PageCacheConfig::environment()` delegates to `Env` so cache and operational features cannot interpret the environment differently.

The dashboard labels these values as `WP_ENVIRONMENT_TYPE` and `WP_DEBUG`.

## 3. Unified page-cache invalidation

### Purpose

All runtime callers use one page-cache invalidation service. The service gives WP-CLI and the admin interface the same behavior as automatic WordPress invalidation.

This is intentionally about the static page cache. Discovery cache, transformed-image storage, transient application data, and PHP OPcache have different lifecycles and are not cleared by normal content actions.

### Interface

Add `packages/foehn/src/PageCache/Invalidator.php` with operations equivalent to:

```php
final readonly class Invalidator
{
    public function flush(): int;

    public function flushSections(): int;

    public function forgetUrl(string $url, bool $paginated = false): int;
}
```

The returned count has one documented meaning across every operation: deleted cached response bodies. Header sidecars are deleted with their body but do not increase the count.

`Invalidator` owns URL validation and `CacheKey` creation. `Store` retains secure filesystem mechanics.

### Section and current-page behavior

A section-cache entry is a static page-cache variant whose canonical query contains `foehn_sections`.

`Store::flushSections()`:

- walks only inside the configured cache root;
- removes all section response bodies and their header sidecars;
- preserves normal full-page entries and unrelated keyed-query variants;
- prunes empty directories;
- works even when page caching is currently disabled, so stale files remain removable.

Clearing one URL removes its normal page, status variants, keyed-query variants, and all `foehn_sections` variants. The existing `Store::forget()` `index*.html` invariant must remain covered by a regression test.

### Callers

- `PageCache\Purger` keeps responsibility for WordPress event targeting and shutdown batching, then delegates deletion to `Invalidator`.
- `PageCacheClearCommand` delegates full and URL clearing to `Invalidator`.
- Admin dashboard and admin-bar handlers delegate every mutation to `Invalidator`.
- The installer can still remove generated cache files without booting WordPress during Composer operations.

Register `Invalidator` as a container singleton whether the page cache is enabled or disabled.

## 4. Non-production indexing protection

Add an indexing protection module that is inert when `Env::isProduction()` is true.

For all other environments it must:

- force `noindex` and `nofollow` through `wp_robots`;
- send `X-Robots-Tag: noindex, nofollow` through `send_headers`;
- return `Disallow: /` through `robots_txt`;
- disable core sitemaps through `wp_sitemaps_enabled`.

The module must remove contradictory `index` and `follow` values from the WordPress robots array. It must not mutate `blog_public`; deployment configuration remains the source of the policy and cannot leave a staging value in the production database.

The HTML directive is important because a cached non-production page can bypass PHP on later requests. `robots.txt` is advisory and is not the primary protection.

Production verification checks that WordPress indexing is enabled and that this module is inactive. It cannot detect a header added by an external CDN or web server; deployment infrastructure must inspect the public HTTP response when that guarantee is required.

## 5. Real WP-Cron heartbeat

The Docker image already installs a real periodic runner through:

- `docker/wordpress/entrypoint.d/85-wp-cron.sh`;
- `docker/wordpress/bin/foehn-cron`.

After `wp cron event run --due-now` succeeds, including when zero events were due, the runner updates this non-autoloaded WordPress option:

```text
foehn_cron_last_run = <Unix timestamp>
```

The update runs as the same application user and against the same WordPress path as the cron command. Heartbeat persistence is part of success: if the option update fails, the runner fails.

The heartbeat must not advance when:

- WordPress is unavailable;
- the database is unavailable;
- event execution fails;
- the overlap lock is not acquired.

Production verification checks:

- `DISABLE_WP_CRON` is enabled;
- the real-cron configuration is enabled;
- the heartbeat exists and is numeric;
- its age is acceptable for the configured cadence and scheduling jitter;
- scheduled events are not significantly overdue.

Scale-to-zero deployments cannot promise a fresh in-container heartbeat. They need an external scheduler that runs the same WP-CLI command and records the same option.

## 6. Føhn admin dashboard

Add a small top-level Føhn operational page. Do not implement it through `#[AsSettingsPage]` because it owns no settings.

The page displays:

- resolved `WP_ENVIRONMENT_TYPE`;
- resolved `WP_DEBUG` status;
- page-cache configured state;
- effective state: active, disabled, or enabled but unavailable in this environment;
- cache path;
- TTL;
- cached response count;
- section response count;
- total cache size;
- last full purge when this information is available without adding a new persistent event log;
- last real-cron heartbeat.

It provides POST buttons to:

- clear the whole page cache;
- clear all section-cache entries.

Use escaped WordPress-native markup. Do not add a dashboard framework or duplicate WP-CLI formatting code.

## 7. Admin-bar cache controls

For users with `manage_options`, add a Føhn Cache admin-bar node with:

- Clear whole cache;
- Clear section cache;
- Clear current page/post cache.

The current-item action appears only when WordPress supplies a trustworthy post ID from a singular front-end request or a post edit screen. The request posts the ID, and the handler resolves the permalink again. It never accepts a cache path or arbitrary URL from the browser.

Admin-bar links must not mutate state through GET. Render nonce-protected hidden forms and submit them through a small script. The dashboard remains the no-JavaScript path.

## 8. Mutation security

Central cache action handlers must:

1. reject non-POST requests;
2. require `manage_options`;
3. validate an action-specific nonce;
4. sanitize and validate the post ID when present;
5. call `Invalidator` only;
6. redirect through `wp_safe_redirect()` to a fixed dashboard fallback or a validated referrer;
7. expose only a fixed result code and deletion count after redirect.

Controls can remain available while caching is disabled because an earlier release can have left files behind.

## 9. Verification seams

The production verification profile defined in [diagnostics-command-spec.md](diagnostics-command-spec.md) consumes stable state from this module:

- resolved environment and debug mode;
- WordPress indexing state and indexing-protection activation;
- generated WordPress salts;
- `DISABLE_WP_CRON` and real-cron configuration;
- `foehn_cron_last_run`;
- overdue cron events;
- page-cache configuration and writable storage.

The operational modules do not depend on WP-CLI verification classes.

## 10. Delivery

### Phase 1 — invalidation foundation

- Canonicalize environment resolution.
- Add `Store::flushSections()` and `Invalidator`.
- Refactor `Purger` and `PageCacheClearCommand`.
- Add filesystem and caller regression tests.

### Phase 2 — indexing protection

- Add and register the protection module.
- Add unit tests and a real non-production smoke assertion.
- Document environment behavior.

### Phase 3 — admin operations

- Implement secured POST handlers first.
- Add the dashboard.
- Add admin-bar forms and controls.
- Add authenticated smoke coverage.

### Phase 4 — cron heartbeat

- Extend the existing Docker runner.
- Extend Docker end-to-end tests.
- Document external scheduler and scale-to-zero requirements.

### Phase 5 — verification

- Implement the shared verification command and production profile.
- Implement the update diagnostics profile.
- Add deployment and CI examples.

## 11. Tests

Unit and integration coverage must include:

- section-only clearing preserves full pages and unrelated query variants;
- URL clearing removes all section variants and sidecars;
- every runtime deletion caller uses `Invalidator`;
- invalid URLs cannot escape the cache root;
- non-production pages, robots output, and sitemap state are protected;
- production output is unchanged;
- dashboard values match resolved configuration and cache storage;
- GET, unauthorized, and invalid-nonce mutations fail;
- current-post clearing resolves the server-side permalink;
- successful real cron records a recent, non-autoloaded heartbeat;
- failed and skipped cron runs do not advance the heartbeat;
- cron and cache writes do not create root-owned WordPress files.

Use the existing unit, starter smoke, installer, and Docker end-to-end suites. Add no dependency.

## 12. Acceptance

The work is complete when:

- every runtime page-cache deletion path uses `Invalidator`;
- operators can separately clear all pages, all sections, or one current page with all its variants;
- every mutation is POST-only, capability-checked, nonce-protected, and safely redirected;
- the dashboard accurately reports cache, environment, debug, and cron state;
- every non-production rendered page is `noindex, nofollow`, while production is unchanged;
- the production Docker cron records a truthful heartbeat after successful execution only;
- production verification fails on unsafe indexing, salts, debug, cron, or cache configuration;
- update verification writes a deterministic diagnostics report for CI;
- all unit, smoke, installer, and Docker tests pass.

## 13. Out of scope

- mail transport;
- TinyMCE or classic-editor configuration;
- multilingual support;
- client login branding;
- a generic admin dashboard framework;
- public custom health-check discovery;
- CDN or web-server header inspection from WP-CLI;
- clearing discovery cache, transformed images, application transients, or OPcache during content invalidation;
- multisite.
