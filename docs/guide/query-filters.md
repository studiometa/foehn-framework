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

Føhn's query filters extend this with:

1. **Security allowlist** for custom taxonomies and private query vars
2. **Twig helpers** for building filter UIs

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

```twig
{# Generate hidden inputs to preserve current filters #}
{{ query_hidden_inputs() | raw }}
{{ query_hidden_inputs(exclude=['s']) | raw }}
```

## Template Examples

### Checkbox Multi-Select

```twig
<form method="get">
  <fieldset>
    <legend>Genre</legend>
    {% for term in terms('genre') %}
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
  {% for term in terms('genre') %}
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
      {% for term in terms('category') %}
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

## Static Counts

For basic counts, use WordPress taxonomy term counts:

```twig
{% for term in terms('genre') %}
  {{ term.name }} ({{ term.count }})
{% endfor %}
```

::: warning
`term.count` shows the total posts in that term, not filtered by current query. For filtered counts (faceted search), consider using [FacetWP](https://facetwp.com/) or [Algolia](https://www.algolia.com/).
:::

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

## See Also

- [Hooks Guide](/guide/hooks) - Enable QueryFiltersHook
- [Twig Extensions](/guide/twig-extensions) - Other built-in helpers
