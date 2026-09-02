# Admin Cache Controls

Føhn adds one admin page and one admin-bar menu, so the two questions that come up at the worst moment can be answered without a shell: **what state is this installation actually in**, and **clear this page now**.

Both are on by default. There is nothing to add to `foehn.config.php`: a project that had to switch on the page it would read the cache's state on has no way to find out that it needed to.

## The Føhn page

A top-level **Føhn** entry in the admin menu, visible to users with `manage_options`. It reports:

| Row                   | What it says                                                                                 |
| --------------------- | -------------------------------------------------------------------------------------------- |
| `WP_ENVIRONMENT_TYPE` | The environment as `Helpers\Env` resolves it, not as a file declares it.                     |
| `WP_DEBUG`            | Whether debug mode is on.                                                                    |
| Configured            | The `enabled` flag from your [`PageCacheConfig`](/api/page-cache-config).                    |
| Effective             | **Active**, **Disabled**, or **enabled but unavailable in this environment**.                |
| Cache path            | Where responses are stored.                                                                  |
| TTL                   | The configured lifetime, or "purge-driven" when it is `0`.                                   |
| Cached responses      | How many stored response bodies exist right now.                                             |
| Section responses     | How many of those are [section](/guide/section-rendering) fragments rather than whole pages. |
| Total size            | What the cache directory holds on disk, headers sidecars included.                           |
| Last full purge       | When the cache was last emptied in full. Per-URL and per-section clears are not recorded.    |
| Last real run         | When the container's WP-Cron runner last succeeded, or **Never**.                            |

The two environment rows are labelled with the constant names rather than with prose on purpose. When somebody reports that "it thinks it is staging", you both have to be looking at the same name.

The **Effective** row is worth reading before anything else. `enabled: true` in an environment your config does not list writes nothing and serves nothing, and a page that reported only "enabled" would send you hunting a broken cache when what you have is a missing environment name.

**Last real run is Never until the container's cron runner is in place.** That is the honest answer for a site with no real cron, and it is the same answer a site whose runner has broken gives — which is the reason the row exists.

## The two buttons on the page

- **Clear the whole page cache** — every stored response, pages and fragments alike.
- **Clear the section cache only** — the variants keyed by `foehn_sections`, leaving whole pages and unrelated keyed variants where they are.

Both are plain `POST` forms. **This page is the no-JavaScript path**: it works with scripting off, which is what makes it the one to rely on when something else on the site is broken.

## The admin-bar menu

A **Føhn Cache** node for `manage_options`, with three items:

- **Clear whole cache**
- **Clear section cache**
- **Clear this page** — the current post, its cached 404, its keyed query variants and its section fragments.

The third item appears **only** when WordPress itself supplies the post: a singular front-end request, or a post edit screen. Anywhere else it is absent rather than disabled, because an item that appears everywhere and works somewhere is one nobody learns to trust. It is also absent for a post no visitor could have been served — a draft has no cached page to clear.

The node's own link goes to the Føhn page and nothing else. **The items do not mutate anything through their `href`**: each one submits a hidden, nonce-protected form rendered in the footer, through a few lines of inline script. A link that cleared a cache would be cleared by every prefetching browser, every link checker and every crawler holding a logged-in cookie, and no nonce fixes that — the browser is following the link on the user's behalf. With scripting off the items do nothing, which is the correct failure for a control that must not fire by accident; the page's buttons are still there.

## What the handlers require

Every mutation goes through one of three `admin_post_` handlers, and each of them refuses a request that fails any of the following:

1. **The request must be a `POST`.** `admin-post.php` fires `admin_post_{action}` for a `GET` too, so this is a real check and not decoration.
2. **The user must have `manage_options`.**
3. **The nonce must have been minted for that action.** Each action has its own nonce action string, so the token on "clear everything" cannot authorise "clear this post".
4. **A post id, when one is present, must resolve to a real, publicly viewable post.**

A refused request answers `403` and says nothing about which check failed. A successful one redirects — through `wp_safe_redirect()`, to a referrer WordPress itself validated or to the Føhn page — carrying only a fixed result code and an integer count.

**The browser posts an id and never a URL or a path.** The permalink is resolved server-side with `get_permalink()`, so there is no parameter for a caller to point somewhere else. That is by construction rather than by validation: a handler that accepted a filesystem path would be a remote deletion endpoint one bug away from working.

Deletion itself is [`PageCache\Invalidator`](/guide/page-cache#invalidation) and nothing else, so a button and an automatic purge always agree about which files a page owns. A clear takes the static page cache and only that — the discovery cache, transformed images, application transients and PHP's OPcache have their own lifecycles.

## They keep working with caching off

Every control stays available while `enabled` is `false`, and the page says so. A release that had the cache on leaves files behind, and the project switching it off is exactly the one who needs them gone.

## Reaching the same operations elsewhere

| Where         | How                                                                                        |
| ------------- | ------------------------------------------------------------------------------------------ |
| WP-CLI        | `wp foehn cache:clear [--url=<url>]`, `wp foehn cache:status`                              |
| Your own code | `do_action('foehn/page_cache/purge_post', $postId)`, `do_action('foehn/page_cache/flush')` |

See [Page Cache](/guide/page-cache) for what is stored, how invalidation is triggered, and how to install the fast path.
