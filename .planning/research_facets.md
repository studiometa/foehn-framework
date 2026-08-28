# Facets: what exists, and what the page cache still needs

Two questions, asked on 2026-08-27 against [wordpress-project!715](https://gitlab.studiometa.dev/packages/wordpress/wordpress-project/-/merge_requests/715), which proposes facet filtering for the wp-toolkit theme. Can Føhn already do this, and what has to be built for a facet to survive the page cache.

The answer to the first is mostly yes, and the proposal should not be ported — this document is deliberately adversarial about why.

The requirement that decides everything here: **a GET request carrying facets must be cacheable.** A facet system whose every URL bypasses the cache is the opposite of what it is for.

The second requirement as first stated — that a facet form's parameters reach the page cache configuration without anybody maintaining a second list — was built and then dropped. It cost a `require` of one config file inside another, and tying two concerns that have no reason to know each other was worse than naming a filter twice. See "The cache side, which is done".

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

## The cache side, which is done

Shipped in #167. A filtered URL is now a cached URL, in both spellings a form can produce:

| URL                          | Served by                                  |
| ---------------------------- | ------------------------------------------ |
| `?genre=rock,jazz`           | nginx, ~0.9 ms                             |
| `?genre[]=rock&genre[]=jazz` | the drop-in, ~2.8 ms, out of the same file |
| `?s=chaise`                  | nginx, once `s` is named with a pattern    |

Three things came out differently from what this document first proposed, and the differences are the interesting part.

**Ordering was not the hard problem it looked like.** The worry was that `?genre=rock,jazz` and `?genre=jazz,rock` are one page and must be one file. They are not one file, and that is fine: two orders store the same HTML twice, which is wasted disk rather than a wrong answer. Sorting would have been the obvious fix and the wrong one, because nginx cannot sort and a sorted key is one only PHP could compute — the two readers would then part company on the first URL that arrived unsorted. A form emits its checkboxes in document order, so one spelling occurs in practice.

**Deriving the cache config from the filter config was built and then removed.** It worked, but it put a `require` of one config file inside another and tied two concerns that have no reason to know each other. `cacheQueryArgs` takes a list of allowed values instead, so a project states what it knows and the pattern is compiled from it:

```php
cacheQueryArgs: [
    'genre' => '^[a-z0-9-]+(?:,[a-z0-9-]+)*$',
    'posts_per_page' => [12, 24, 48],
],
```

The cost is a filter named in two files, and it is stated in both guides: a filter added to one and not the other is a bypass, which reads as a slow page rather than as an error.

**Search needed no new switch.** Naming `s` in `cacheQueryArgs` is the opt-in, and the pattern that has to come with it is what bounds the key space.

One defect is worth remembering because it is the shape of defect this area produces. The value charset was spelled in three places; widening two of them left `CacheKey::FILENAME_PATTERN` refusing `index__genre=rock,jazz&.html`, so every multi-value request bypassed while reporting `path` — a message about the URL, for a filename the cache would not write. No unit test caught it, because they covered `QueryKey` and never `CacheKey`. The end-to-end suite did. Derive, do not repeat, and keep the smoke test as the oracle.

## What is left

### 1. A bound on the key space

Not done, and now the first thing. Facets are combinatorial: three facets with ten values each is on the order of a thousand stored pages, each a full render, and a crawler that walks the links produces all of them. `cacheNotFound` carries an explicit warning that turning it on "wants a bound on the entry count"; keyed arguments have no equivalent and are a far larger space.

Options, cheapest first: cap how many keyed arguments may be present at once and bypass beyond it; cap entries per path; or key only the combinations the UI can produce.

This matters more now than when it was written, because search can be keyed: a keyword is unbounded input, and the pattern is currently the only thing standing between a search box and a directory with a file per phrase.

### 2. The bracketed form on the fast path

Tracked in #168. A checkbox group posts `name="genre[]"` and cannot post anything else without JavaScript, so the slower of the two paths is the one a plain facet form uses. A prototype that keys it in nginx is verified and matches PHP exactly; the risk is in the guards, not the join.

Optional. Both forms are cached either way.

**Partial rendering versus caching.** Section requests return `Cache-Control: private, no-store` and bypass the full-page cache, and a project cannot key `foehn_sections`. So Section Rendering and a cached facet response are mutually exclusive as things stand. Either the facet UI uses `Frame`'s full-page fetch — larger response, fully cacheable, no new server feature — or sections are made cacheable, which means teaching the drop-in and the generated nginx rules to key a fragment. The first is the cheap answer and probably the right one for filtering; the second is a real feature with a much wider blast radius.

Now that filter URLs are cached, this leans further towards `Frame`: a full-page fetch of a filtered URL is a cache hit served without PHP, while the same filter through a section is a render every time. The larger response is bytes off a warm file; the section is a cold render. Whichever way it goes, it should be decided before a facet UI is written, because it decides what the templates look like.

**Filtered counts are out of scope.** The proposal's `home.php` derives the available terms from the result set, which is the part that makes a filter a facet. Doing it correctly means counting over the whole filtered set on every request — expensive, and `WP_Query` is not the tool. `docs/guide/query-filters.md` already points at FacetWP or Algolia for this, and that judgement should stand until someone has a requirement that pays for a search engine.

## Where a facet UI would start

Nothing in the framework is missing for basic filtering any more, and nothing demonstrates it either: neither the starter nor the demo has a filter form. The gap is now an example rather than a feature.

The smallest end-to-end version, in order:

1. **A filter form in the demo**, on the projects index, filtering by the categories the portfolio already has. A plain `method="get"` form using native names, the `query_*` helpers for state and URLs, and `cacheQueryArgs` naming the same arguments. It would be the first place the whole path is exercised together, and the smoke test could then assert that a filtered URL is a cache hit.
2. **The partial-render decision**, applied to that form. Everything above works with a full page load; enhancing it is the step that forces the `Frame`-versus-sections answer.
3. **The key-space bound**, before any of this is recommended to a real project with more than a handful of terms.

Filtered counts stay out until someone has a requirement that pays for a search engine.

## Summary

Nothing in the proposal needs to be ported. The filtering it adds already exists in `QueryFiltersHook` in a safer form, its client-side half is worth keeping as it is, and its parameter naming is the one part that must not survive — not because it is unidiomatic, but because it is unkeyable.

That last objection is now spent: since #167 a filtered URL is a cached URL, in both spellings, and the framework's own documented filter format is no longer the one shape its own cache refused. What is left is a bound on how many of those files a site may accumulate, and an example that shows the whole thing working.
