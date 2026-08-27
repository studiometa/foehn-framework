# Facets: what exists, and what the page cache still needs

Two questions, asked on 2026-08-27 against [wordpress-project!715](https://gitlab.studiometa.dev/packages/wordpress/wordpress-project/-/merge_requests/715), which proposes facet filtering for the wp-toolkit theme. Can Føhn already do this, and what has to be built for a facet to survive the page cache.

The answer to the first is mostly yes, and the proposal should not be ported — this document is deliberately adversarial about why.

The requirement that decides everything here: **a GET request carrying facets must be cacheable**, and the parameters a facet form emits must reach the page cache configuration without anybody maintaining a second list by hand.

## What the proposal does

`FacetsManager` reads a `facets` array from the request — GET or POST — and copies every key it finds into the main query on `pre_get_posts`:

```php
foreach ( $this->facets as $query_var => $value ) {
    $query->query_vars[ $query_var ] = $value;
}
```

Form fields are named after the WP_Query vars they set: `facets[s]`, `facets[tag_id]`, `facets[category__and][]`. A Twig function `facets_get()` reads the values back so the form can show its own state. On the client, `@studiometa/ui`'s `Frame`, `FrameForm`, `FrameTarget` and `FrameAnchor` submit the form, fetch the page and morph three regions — the filters, the listing and the pagination — with history support.

## What it gets right, and should be kept

**The form is a real GET form.** Without JavaScript it submits and the page reloads with the filters applied. `Frame` is an enhancement over a working document, not a replacement for one. This is the right shape and the reason the rest of this document is about the server, not the browser.

**Pagination is a morph target.** Filtering and paging are the same operation seen twice, and treating them as one region each avoids the usual bug where paging drops the filters.

**Filter state is read back from the request, not from a client-side store.** One source of truth, and it survives a shared link.

## Why it cannot ship as it is

### 1. It is an unauthenticated query-var injection

Nothing constrains which keys reach `query_vars`. Every one of these is a public URL:

| URL                                             | What it does                                                                                                                   |
| ----------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------ |
| `?facets[posts_per_page]=-1`                    | Renders every post in the archive. On a site with real content this is a denial of service that costs the attacker one request |
| `?facets[orderby]=rand`                         | `ORDER BY RAND()` on every hit — a filesort over the whole result set, and a response no cache can ever reuse                  |
| `?facets[meta_query][0][key]=…&…[compare]=LIKE` | Arbitrary `postmeta` queries. Unindexed scans, and a probe that leaks the value of meta the site never renders                 |
| `?facets[post__in][]=…`                         | Turns the archive into an oracle for which IDs exist                                                                           |
| `?facets[suppress_filters]=1`                   | Disables `posts_where` and its neighbours on the main query — including any access control another plugin installed there      |

WordPress protects the post status itself, so this is not a direct route to drafts. It is still a hole that only closes by deciding, somewhere, which vars a visitor may set — which is exactly what an allowlist is.

**The proposal has to be inverted: name the filters that exist, not the ones that are forbidden.**

### 2. Its parameter names cannot be cached, at all

This is the finding that matters most, because it is not a matter of degree.

`facets[tag_id]` reaches the wire as `facets%5Btag_id%5D`. `QueryKey` never decodes a query string — deliberately, because nginx does not decode `$args` either and two readers disagreeing about what `%75tm_source` means is worse than both treating it as unknown. And `PageCacheConfig::getCacheQueryArgs()` will only key a name matching `^[A-Za-z0-9_-]{1,32}$`. A bracketed name cannot pass that, and nginx has no `$arg_facets[tag_id]` to read it with even if it did.

So **every faceted request under this naming is an unknown query argument, and an unknown query argument is a cache bypass.** Not a miss — a bypass, on every request, for every visitor.

Flattening the names to `?tag_id=5` clears that wall and reveals two more:

- **Multi-value facets bypass too.** `QueryKey::VALUE_CHARACTER_CLASS` is `A-Za-z0-9_.\-`, with no comma, and a project pattern "can only narrow this, never widen it". So `?genre__and=rock,jazz` — the format `docs/guide/query-filters.md` documents — is refused by the cache. The array form `?genre[]=rock&genre[]=jazz` fails twice: `[` is invalid in a keyed name, and a repeated keyed name is an explicit bypass, because nginx reads the first occurrence and PHP the last.
- **A keyword field bypasses everything.** `Bypass` maps `is_search` to `BypassReason::Search` unconditionally. `facets[s]`, the first field in the proposal's form, makes every filtered response uncacheable no matter what else is fixed.

Føhn's own documented filter URL format is therefore exactly the format its page cache refuses. That contradiction is the first thing to fix, and nothing else on this list matters until it is.

### 3. It re-implements what WordPress already parses

`facets[s]`, `facets[tag_id]`, `facets[author]` and `facets[cat]` are WP_Query vars in a costume. WordPress already parses `?s=`, `?tag_id=`, `?cat=` and `?category__and[]=` with no code at all, and `?genre__and=` with an allowlist entry.

The wrapper buys nothing and costs the canonical URL, `is_search()`, `paginate_links()`, whatever an SEO plugin does with an archive, and the cache. `facets_get()` exists only because the wrapper hid the values from every convention that would otherwise have found them.

## Bugs in the example itself

Independent of the design, the diff carries defects that a reviewer should not let through:

1. **The filters apply to every query on the page.** `FacetsManager::add_facets_to_query()` has no `is_main_query()` and no `is_admin()` guard, so a related-posts query, a menu query and the admin list table all get the visitor's facets applied. Føhn's `QueryFiltersHook` guards both.
2. **`pre_get_posts` is registered with `add_filter()`** and the callback returns `$query`. It is an action. This works only because `WP_Query` is an object and the mutation happens by reference; the return value is discarded. It reads as a filter chain that does not exist.
3. **The "Last" pagination button links to the first page.** `has_last_page` is computed from `pagination.pages|last`, and then the href is `pagination.pages|first.link`.
4. **`home.php` computes its terms twice.** `Timber::get_terms('category')` and `Timber::get_terms('tag')` are both overwritten a few lines later. The second is also wrong on its own terms: the taxonomy is `post_tag`, not `tag`.
5. **The facet options come from the current page of results.** `$post_ids` is the paginated set, so the available filters change as the visitor pages through them, and show only the terms that happen to appear on the page they are on. Facets are a property of the whole filtered set.
6. **`illuminate/collections` is added as a dependency** for two `map()` calls that `array_map()` already does.

## What Føhn already has

| Proposal                                        | Føhn today                                                                                                                                                                   |
| ----------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `facets[s]`, `facets[tag_id]`, `facets[author]` | Native WP vars. No code, no configuration                                                                                                                                    |
| `facets[category__and][]`                       | `QueryFiltersConfig(taxonomies: ['genre' => ['in', 'not_in', 'and', 'exists']])` → `?genre__and=rock,jazz`                                                                   |
| `facets[posts_per_page]`                        | `publicVars: ['posts_per_page' => [12, 24, 48]]`, where an invalid value resets instead of applying                                                                          |
| `facets_get('s')`                               | `query_get()`, `query_has()`, `query_contains()`, `query_all()`, and `query_url()`, `query_url_toggle()`, `query_url_without()`, `query_hidden_inputs()` for building the UI |
| `Frame` + `FrameTarget`                         | Either keep `Frame`, which fetches the whole page and needs no server feature, or use Section Rendering                                                                      |

**For basic filtering, no new framework feature is needed.** Drop the `facets[…]` wrapper, use flat native names, and `QueryFiltersHook` covers the custom taxonomies.

## What has to be built

In order. The first one blocks the rest.

### 1. A multi-value encoding the cache can key

Without it, any facet with more than one selected value is uncacheable, and the framework's documented URL format stays at odds with its own cache.

The narrow fix is to allow `,` in `QueryKey::VALUE_CHARACTER_CLASS`. A comma is filename-safe, nginx holds it in `$arg_genre__and` without trouble, and it keeps one canonical spelling of a multi-value facet instead of inventing a second. What it costs is a widening of the charset that "can only narrow, never widen" was written to prevent, so it belongs in the class rather than in a project pattern — projects still narrow from there.

Ordering matters as much as the charset: `?genre=rock,jazz` and `?genre=jazz,rock` are the same query and must be the same file, so the values need sorting into a canonical order — and nginx cannot sort. Either the filter helpers always emit sorted values and an unsorted one is a bypass, or the cache keys the raw string and accepts two files for one result set. **This is the open design question in this item, not the charset.**

### 2. Derive the keyed query args from the declared filters

The requirement that facet parameters reach the page cache configuration by themselves. `QueryFiltersConfig` and `PageCacheConfig` are independent objects today, and `cacheQueryArgs` is hand-written — so the two lists drift, and a filter that was added last week is a bypass nobody notices, because a bypass looks like a slow page and not like an error.

The architecture already suits it: the nginx and `.htaccess` snippets are generated from `PageCacheConfig` by `wp foehn cache:config`, and the generated file carries a hash of the configuration. So this is composition at configuration time, not work at request time.

The allowlist also supplies the pattern for free, which is the part worth having: `posts_per_page: [12, 24, 48]` derives `^(12|24|48)$`, and a taxonomy filter derives the slug charset. The cache then refuses exactly the values the filter would have rejected, and the two cannot disagree about what a valid request is.

### 3. A bound on the key space

Facets are combinatorial. Three facets with ten values each is on the order of a thousand stored pages, each a full render, and a crawler that walks the links will produce all of them. `cacheNotFound` carries an explicit warning that turning it on "wants a bound on the entry count"; keyed facet arguments have no equivalent and are a far larger space.

Options, cheapest first: cap how many keyed arguments may be present at once and bypass beyond it; cap entries per path; or key only the combinations the UI can actually produce.

### 4. Search, only if the keyword field must be cached

`is_search` is an unconditional bypass today. Making it optional means an opt-in flag and a tight value pattern, and accepting that a keyword is an unbounded key space — item 3 becomes a precondition rather than a nicety.

If the keyword field can live outside the cached path instead, skip this entirely.

## Two decisions to take before any of it

**Partial rendering versus caching.** Section requests return `Cache-Control: private, no-store` and bypass the full-page cache, and a project cannot key `foehn_sections`. So Section Rendering and a cached facet response are mutually exclusive as things stand. Either the facet UI uses `Frame`'s full-page fetch — larger response, fully cacheable, no new server feature — or sections are made cacheable, which means teaching the drop-in and the generated nginx rules to key a fragment. The first is the cheap answer and probably the right one for filtering; the second is a real feature with a much wider blast radius.

**Filtered counts are out of scope.** The proposal's `home.php` derives the available terms from the result set, which is the part that makes a filter a facet. Doing it correctly means counting over the whole filtered set on every request — expensive, and `WP_Query` is not the tool. `docs/guide/query-filters.md` already points at FacetWP or Algolia for this, and that judgement should stand until someone has a requirement that pays for a search engine.

## Summary

Nothing in the proposal needs to be ported. The filtering it adds already exists in `QueryFiltersHook` in a safer form, its client-side half is worth keeping as it is, and its parameter naming is the one part that must not survive — not because it is unidiomatic, but because it is unkeyable, and a facet system that cannot be cached is the opposite of what this is for.
