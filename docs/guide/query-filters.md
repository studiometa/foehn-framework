# Query Filters

Føhn provides URL-based query filtering for archives, extending WordPress native query handling with security and Twig convenience helpers.

## Overview

WordPress already handles many URL parameters natively:

| Parameter       | Example               | Notes            |
| --------------- | --------------------- | ---------------- |
| `cat`           | `?cat=5`              | Category by ID   |
| `category_name` | `?category_name=news` | Category by slug |
| `tag`           | `?tag=featured`       | Tag by slug      |
| `author`        | `?author=1`           | Author by ID     |
| `s`             | `?s=keyword`          | Search           |
| `orderby`       | `?orderby=date`       | Sort field       |
| `order`         | `?order=DESC`         | Sort direction   |

A **registered public taxonomy is already in that list.** `register_taxonomy()` gives it a query var, so `?genre=rock` filters an archive with no configuration at all — and `WP_Query` reads both `?genre=rock,jazz` and the `?genre[]=rock&genre[]=jazz` a checkbox group posts, sorting the terms and building one `IN` clause from either. Most filter UIs need nothing on this page.

Føhn's query filters extend this with:

1. **Security allowlist** for custom taxonomies and private query vars
2. **Operators** — `__and`, `__not_in`, `__exists` — which WordPress does not parse itself
3. **Twig helpers** for building filter UIs

Reach for them when you need an operator, when you want to expose a private var such as `posts_per_page`, or when the taxonomy is not public.

## QueryFiltersConfig

Create a configuration file to define which custom taxonomies and query vars should be exposed:

```php
<?php
// app/query-filters.config.php

use Studiometa\Foehn\Config\QueryFiltersConfig;

return new QueryFiltersConfig(
    // Custom taxonomies with allowed operators
    taxonomies: [
        'genre' => ['in', 'not_in', 'and'],
        'product_cat' => ['in'],
    ],
    // Private vars to make public (with allowed values)
    publicVars: [
        'posts_per_page' => [12, 24, 48],
    ],
);
```

### Taxonomy Operators

| Operator | URL Format              | Description                          |
| -------- | ----------------------- | ------------------------------------ |
| `in`     | `?genre=rock`           | Posts in ANY of the specified terms  |
| `not_in` | `?genre__not_in=pop`    | Exclude posts in specified terms     |
| `and`    | `?genre__and=rock,jazz` | Posts in ALL specified terms         |
| `exists` | `?genre__exists=1`      | Posts that have any term in taxonomy |

### URL Format

URLs follow WordPress conventions:

```
?genre=rock                    # IN (default)
?genre=rock,jazz               # Multiple values (comma-separated)
?genre__not_in=classical       # NOT IN operator
?genre__and=rock,jazz          # AND operator
?posts_per_page=24             # Whitelisted value
```

## Enabling Query Filters

Add the `QueryFiltersHook` to your configuration:

```php
<?php
// functions.php

use Studiometa\Foehn\Kernel;
use Studiometa\Foehn\Hooks\QueryFiltersHook;

Kernel::boot(__DIR__ . '/app', [
    'hooks' => [
        QueryFiltersHook::class,
    ],
]);
```

## Twig Helpers

Føhn provides `query_*` Twig functions for building filter UIs. These are available automatically (no configuration needed).

### Reading Parameters

```twig
{# Get a parameter value #}
{{ query_get('category') }}
{{ query_get('page', 1) }}              {# with default #}

{# Check if parameter exists #}
{{ query_has('category') }}
{{ query_has('category', 'news') }}     {# has specific value #}

{# Check if value is in array parameter #}
{{ query_contains('tags', 'php') }}

{# Get all parameters #}
{{ query_all() }}
```

### Building URLs

```twig
{# Add/modify parameters #}
{{ query_url({category: 'news'}) }}
{{ query_url({category: 'news', page: 2}) }}

{# Remove parameters #}
{{ query_url_without('category') }}
{{ query_url_without(['category', 'page']) }}

{# Toggle a value (add if missing, remove if present) #}
{{ query_url_toggle('tags', 'php') }}

{# Clear all parameters #}
{{ query_url_clear() }}
```

### Form Helper

When building a form that controls only some filters (e.g., a sort dropdown), you need to preserve the other active filters. Without this, submitting the form would lose all other query parameters.

`query_hidden_inputs()` generates `<input type="hidden">` elements for all current query parameters, so they're included when the form is submitted.

```twig
{# Current URL: /blog?category=news&tag=featured&orderby=date #}

<form method="get">
  {{ query_hidden_inputs(exclude=['orderby']) | raw }}
  {# Outputs:
     <input type="hidden" name="category" value="news">
     <input type="hidden" name="tag" value="featured">
  #}

  <select name="orderby" onchange="this.form.submit()">
    <option value="date">Date</option>
    <option value="title">Title</option>
  </select>
</form>
{# Submitting with "title" goes to: /blog?category=news&tag=featured&orderby=title #}
```

The `exclude` parameter lets you omit parameters that your form controls directly (to avoid duplicates).

## Template Examples

### Checkbox Multi-Select

```twig
<form method="get">
  <fieldset>
    <legend>Genre</legend>
    {% for term in get_terms('genre') %}
      <label>
        <input
          type="checkbox"
          name="genre[]"
          value="{{ term.slug }}"
          {{ query_contains('genre', term.slug) ? 'checked' }}
        >
        {{ term.name }} ({{ term.count }})
      </label>
    {% endfor %}
  </fieldset>
  <button type="submit">Filter</button>
</form>
```

### Link Toggle Filter

```twig
<ul class="filter-tags">
  {% for term in get_terms('genre') %}
    <li>
      <a
        href="{{ query_url_toggle('genre', term.slug) }}"
        class="{{ query_contains('genre', term.slug) ? 'active' }}"
      >
        {{ term.name }}
        {% if query_contains('genre', term.slug) %}<span>×</span>{% endif %}
      </a>
    </li>
  {% endfor %}
</ul>
```

### Select Dropdown

```twig
<form method="get">
  {# Preserve all current filters except 'orderby' which is controlled by the select #}
  {{ query_hidden_inputs(exclude=['orderby']) | raw }}

  <label>
    Sort by
    <select name="orderby" onchange="this.form.submit()">
      <option value="date" {{ query_get('orderby', 'date') == 'date' ? 'selected' }}>Date</option>
      <option value="title" {{ query_get('orderby') == 'title' ? 'selected' }}>Title</option>
      <option value="menu_order" {{ query_get('orderby') == 'menu_order' ? 'selected' }}>Custom Order</option>
    </select>
  </label>
</form>
```

### Per-Page Selector

```twig
<div class="per-page">
  Show:
  {% for count in [12, 24, 48] %}
    <a
      href="{{ query_url({posts_per_page: count}) }}"
      class="{{ query_get('posts_per_page', 12) == count ? 'active' }}"
    >{{ count }}</a>
  {% endfor %}
</div>
```

### Active Filters Summary

```twig
{% set filters = query_all() %}
{% if filters is not empty %}
  <div class="active-filters">
    <span>Active filters:</span>
    {% for key, value in filters %}
      {% for v in value is iterable ? value : [value] %}
        <a href="{{ query_url_toggle(key, v) }}" class="tag">
          {{ key }}: {{ v }} ×
        </a>
      {% endfor %}
    {% endfor %}
    <a href="{{ query_url_clear() }}" class="clear-all">Clear all</a>
  </div>
{% endif %}
```

### Combined Search and Filters

```twig
<form method="get" class="archive-filters">
  {# Search field #}
  <div class="search-field">
    <input type="search" name="s" value="{{ query_get('s') }}" placeholder="Search...">
  </div>

  {# Category filter #}
  <div class="filter-group">
    <label for="category">Category</label>
    <select name="category" id="category">
      <option value="">All categories</option>
      {% for term in get_terms('category') %}
        <option
          value="{{ term.slug }}"
          {{ query_get('category') == term.slug ? 'selected' }}
        >{{ term.name }}</option>
      {% endfor %}
    </select>
  </div>

  {# Sort order #}
  <div class="filter-group">
    <label for="orderby">Sort by</label>
    <select name="orderby" id="orderby">
      <option value="date" {{ query_get('orderby', 'date') == 'date' ? 'selected' }}>Newest</option>
      <option value="title" {{ query_get('orderby') == 'title' ? 'selected' }}>Title</option>
    </select>
  </div>

  <button type="submit">Apply filters</button>
</form>
```

## Facets: options that know their own count

A filter lists every term. A facet says how many results each term would give, and which ones would give none — the difference between a control a visitor can use and one they have to guess at.

`facet()` returns those options for a taxonomy:

```twig
{% for option in facet('project_category') %}
  <label>
    <input
      type="checkbox"
      name="project_category[]"
      value="{{ option.term.slug }}"
      {{ option.active ? 'checked' }}
      {{ option.isEmpty and not option.active ? 'disabled' }} />
    {{ option.term.name }}
    {% if option.count is not null %}({{ option.count }}){% endif %}
  </label>
{% endfor %}
```

Each option carries a `term` (a plain `WP_Term`), a `count`, and `active`. `isEmpty` is true when choosing it would give an empty page.

### Each facet is counted with its own filter lifted

This is the part that is easy to get wrong, and getting it wrong is worse than having no counts. Counts for `project_category` come from the current query **minus its own `project_category` constraint**, while every other filter on the page still applies.

Leave the filter in and picking "still life" makes every other series report zero, so a visitor who wants two series can never pick the second one. The control silently stops working.

### The whole filtered set, not the current page

A tempting shortcut is to derive the options from the posts already on the page:

```php
// Don't: this counts the ten posts on screen.
$ids = array_map(fn($post) => $post->id, $posts);
$terms = wp_get_object_terms($ids, 'project_category');
```

The list of filters then changes as the visitor pages through the results, and a term that appears only on page three is invisible on page one. `facet()` queries the filtered set instead.

### What it costs

One query for the post ids and one grouped count per facet, at render time. The number of terms does not change the cost — a single `GROUP BY` covers all of them — but the size of the result set does.

On a page in the [page cache](/guide/page-cache) that is paid **once per stored page rather than once per visitor**, which is what makes it affordable. On an uncached page it is paid every time.

Above `Facets::MAX_COUNTED_POSTS` (2000) results, options come back with `count` set to `null` rather than slowly. `null` is not zero: nothing was counted, so nothing is reported as a dead end. Templates check `option.count is not null` before printing.

## Unfiltered counts

`term.count` is WordPress's own total for a term, across the whole site:

```twig
{% for term in get_terms('genre') %}
  {{ term.name }} ({{ term.count }})
{% endfor %}
```

It ignores the current query, so on a filtered archive it will disagree with what the page shows. Use it for a tag cloud or a menu; use `facet()` above for anything a visitor filters with.

A search engine — [FacetWP](https://facetwp.com/), [Algolia](https://www.algolia.com/) — is still the answer when facets have to span post types, weight relevance, or count over sets larger than `Facets::MAX_COUNTED_POSTS`.

## API Reference

### QueryFiltersConfig

```php
new QueryFiltersConfig(
    // Map of taxonomy slug to allowed operators
    taxonomies: [
        'genre' => ['in', 'not_in', 'and', 'exists'],
    ],
    // Map of private vars to allowed values (or true for any value)
    publicVars: [
        'posts_per_page' => [12, 24, 48],
        'custom_var' => true,  // any value allowed
    ],
);
```

### Twig Functions

| Function                       | Description                                       |
| ------------------------------ | ------------------------------------------------- |
| `query_get(key, default)`      | Get parameter value                               |
| `query_has(key, value?)`       | Check if parameter exists (optionally with value) |
| `query_contains(key, value)`   | Check if value is in array parameter              |
| `query_all()`                  | Get all non-empty parameters                      |
| `query_url(params)`            | Build URL with added/modified parameters          |
| `query_url_without(keys)`      | Build URL without specified parameters            |
| `query_url_toggle(key, value)` | Build URL with value toggled                      |
| `query_url_clear()`            | Build URL with all parameters removed             |
| `query_hidden_inputs(exclude)` | Generate hidden inputs for form                   |

## A worked example

The demo's projects archive is the whole path in one page — `packages/demo/theme/templates/pages/archive-project.twig`:

```twig
<form
  method="get"
  action="{{ fn('get_post_type_archive_link', 'project') }}"
  data-component="Fetch Action"
  data-option-history
  data-on:change="Fetch.fetch()">
  {% for term in series %}
    <label>
      <input
        type="checkbox"
        name="project_category[]"
        value="{{ term.slug }}"
        {{ query_contains('project_category', term.slug) ? 'checked' }} />
      {{ term.title }}
    </label>
  {% endfor %}
  <button type="submit">Filter</button>
</form>
```

Three things about it are the point:

**It uses no framework feature to filter.** `project_category` is the taxonomy's own query var, `orderby` is a public one, and there is no config file, no hook and no custom parameter. `query_contains()` reads the state back, and that is all Føhn contributes.

**It works without JavaScript.** The form submits, the page reloads filtered, the checkboxes come back checked. `Fetch` from `@studiometa/ui` enhances that: it fetches the same URL and swaps the elements whose `id` it finds in the response.

**The enhanced request is still cached.** `Fetch` is given no `data-option-src`, so it fetches the whole filtered page — which the page cache serves without starting PHP — and keeps the one element it needs. Pointing it at a [section](/guide/section-rendering) URL instead is also cached, and sends fewer bytes; the trade is that each filter combination then occupies two stored files rather than one, because the page and the fragment are separate responses.

## Caching filtered pages

Filter URLs are cacheable, but only the ones the cache has been told about. Name them in the page cache configuration, with the values each one takes:

```php
// app/page-cache.config.php
return new PageCacheConfig(
    enabled: true,
    cacheQueryArgs: [
        'genre' => '^[a-z0-9-]+(?:,[a-z0-9-]+)*$',
        'posts_per_page' => [12, 24, 48],
    ],
);
```

The two configurations stay independent — the cache does not read this file, and this file knows nothing about the cache. So a filter added here is a bypass until it is named there: the page still renders, it just never comes from a file. Add the two together.

Once named, both `?genre=rock,jazz` and `?genre[]=rock&genre[]=jazz` are cached — see [Static Page Cache](/guide/page-cache) for which of the two nginx serves and which the drop-in does.

Two filters are worth planning around. `s` bypasses unless you key it deliberately, so a keyword field in a filter form makes every response uncacheable until you do. And a `publicVars` entry of `true` is never derived, because a keyed argument with unbounded values is an unbounded number of files.

## See Also

- [Static Page Cache](/guide/page-cache) - Keying filter URLs so filtered pages are cached
- [Hooks Guide](/guide/hooks) - Enable QueryFiltersHook
- [Twig Extensions](/guide/twig-extensions) - Other built-in helpers
