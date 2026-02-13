# Querying Posts

Føhn provides a fluent `PostQueryBuilder` and a `QueriesPostType` trait for type-safe, null-safe post queries integrated with Timber.

## Base Models

Føhn ships with base model classes that include query support:

```php
use Studiometa\Foehn\Models\Post;  // For 'post' type
use Studiometa\Foehn\Models\Page;  // For 'page' type
```

Both extend `Timber\Post` and use the `QueriesPostType` trait. Your custom post type models should extend `Post`:

```php
<?php

namespace App\Models;

use Studiometa\Foehn\Attributes\AsPostType;
use Studiometa\Foehn\Models\Post;

#[AsPostType(
    name: 'product',
    singular: 'Product',
    plural: 'Products',
    hasArchive: true,
    menuIcon: 'dashicons-cart',
)]
final class Product extends Post
{
    public function price(): ?float
    {
        return $this->meta('price') ? (float) $this->meta('price') : null;
    }
}
```

## Quick Query Methods

The `QueriesPostType` trait provides convenient static methods:

```php
// Get all published products
$products = Product::all();
$products = Product::all(limit: 10);

// Find by ID
$product = Product::find(42);

// Get the first matching post
$product = Product::first();
$product = Product::first(['meta_key' => 'featured', 'meta_value' => '1']);

// Count posts
$count = Product::count();

// Check existence
if (Product::exists()) {
    // At least one product exists
}
```

## Fluent Query Builder

For complex queries, use `query()` which returns a `PostQueryBuilder`:

```php
$products = Product::query()
    ->limit(10)
    ->orderBy('date', 'DESC')
    ->get();
```

### Pagination

```php
// Limit results
Product::query()->limit(10)->get();

// Offset results
Product::query()->limit(10)->offset(20)->get();

// Pagination (uses WordPress paged parameter)
Product::query()->limit(10)->page(2)->get();
```

### Ordering

```php
// By WordPress field
Product::query()->orderBy('title', 'ASC')->get();
Product::query()->orderBy('menu_order', 'ASC')->get();
Product::query()->orderBy('rand')->get();

// By meta field
Product::query()->orderByMeta('price', 'ASC', numeric: true)->get();
Product::query()->orderByMeta('last_name', 'ASC')->get();
```

### Filtering by Status

```php
// Single status
Product::query()->status('draft')->get();

// Multiple statuses
Product::query()->status(['publish', 'pending'])->get();
```

### Filtering by IDs

```php
// Include only specific IDs
Product::query()->include(1, 2, 3)->get();

// Exclude specific IDs
Product::query()->exclude(42)->get();
```

### Taxonomy Filters

```php
// Single taxonomy filter
Product::query()
    ->whereTax('product_category', 'featured')
    ->get();

// By term ID
Product::query()
    ->whereTax('product_category', 5, field: 'term_id')
    ->get();

// Multiple taxonomy filters (AND)
Product::query()
    ->whereTax('product_category', 'featured')
    ->whereTax('product_tag', 'new')
    ->get();

// Multiple taxonomy filters (OR)
Product::query()
    ->whereTax('product_category', 'featured')
    ->whereTax('product_tag', 'new')
    ->taxRelation('OR')
    ->get();

// Exclusion
Product::query()
    ->whereTax('product_category', 'archived', operator: 'NOT IN')
    ->get();
```

### Meta Filters

```php
// Simple comparison
Product::query()
    ->whereMeta('featured', '1')
    ->get();

// Numeric comparison
Product::query()
    ->whereMeta('price', 100, '>=', type: 'NUMERIC')
    ->get();

// Range (BETWEEN)
Product::query()
    ->whereMeta('price', [50, 200], 'BETWEEN', type: 'NUMERIC')
    ->get();

// Multiple meta conditions (AND by default)
Product::query()
    ->whereMeta('featured', '1')
    ->whereMeta('price', 100, '>=', type: 'NUMERIC')
    ->get();

// OR relation
Product::query()
    ->whereMeta('featured', '1')
    ->whereMeta('on_sale', '1')
    ->metaRelation('OR')
    ->get();
```

### Search

```php
Product::query()
    ->search('organic coffee')
    ->get();
```

### Author

```php
Product::query()
    ->byAuthor(1)
    ->get();
```

### Date Filters

```php
// Posts from the last month
Product::query()
    ->dateQuery([
        'after' => '1 month ago',
    ])
    ->get();

// Posts from a specific year
Product::query()
    ->dateQuery([
        'year' => 2026,
    ])
    ->get();
```

### Parent Filters (Hierarchical Types)

```php
use Studiometa\Foehn\Models\Page;

// Top-level pages only
Page::query()->parent(0)->get();

// Children of a specific page
Page::query()->parent(42)->get();

// Children of multiple parents
Page::query()->parentIn(10, 20, 30)->get();

// Exclude children of specific parents
Page::query()->parentNotIn(99)->get();
```

### Escape Hatch

For any `WP_Query` parameter not covered by the builder:

```php
// Set a single parameter
Product::query()
    ->set('cache_results', false)
    ->get();

// Merge multiple parameters
Product::query()
    ->merge([
        'no_found_rows' => true,
        'update_post_meta_cache' => false,
    ])
    ->get();
```

## Null-Safety

All filtering methods are **null-safe** — they silently skip empty or null values. This makes it safe to pass user input directly:

```php
$category = $request->get('category');  // might be null or ''
$search = $request->get('q');           // might be null or ''

$products = Product::query()
    ->whereTax('product_category', $category)  // Skipped if $category is null/empty
    ->search($search ?? '')                     // Skipped if empty string
    ->limit(12)
    ->page($request->get('page') ?? 1)
    ->get();
```

## Execution Methods

| Method            | Return               | Description                                          |
| ----------------- | -------------------- | ---------------------------------------------------- |
| `get()`           | `list<\Timber\Post>` | Execute query and return all matching posts          |
| `first()`         | `\Timber\Post\|null` | Return the first matching post                       |
| `count()`         | `int`                | Count matching posts (efficient, uses `fields: ids`) |
| `exists()`        | `bool`               | Check if any matching posts exist                    |
| `getParameters()` | `array`              | Get raw `WP_Query` parameters (for debugging)        |

## Debugging

Inspect the generated `WP_Query` parameters:

```php
$query = Product::query()
    ->limit(10)
    ->whereTax('product_category', 'featured')
    ->orderBy('date', 'DESC');

dump($query->getParameters());
// [
//     'post_type' => 'product',
//     'post_status' => 'publish',
//     'posts_per_page' => 10,
//     'tax_query' => [...],
//     'orderby' => 'date',
//     'order' => 'DESC',
// ]
```

## Combining with Custom Methods

Add domain-specific query methods to your model:

```php
#[AsPostType(name: 'product', singular: 'Product', plural: 'Products')]
final class Product extends Post
{
    /**
     * Get featured products.
     *
     * @return list<self>
     */
    public static function featured(int $limit = 4): array
    {
        return static::query()
            ->whereMeta('featured', '1')
            ->limit($limit)
            ->orderBy('date', 'DESC')
            ->get();
    }

    /**
     * Get products in a price range.
     *
     * @return list<self>
     */
    public static function byPriceRange(float $min, float $max): array
    {
        return static::query()
            ->whereMeta('price', [$min, $max], 'BETWEEN', type: 'NUMERIC')
            ->orderByMeta('price', 'ASC', numeric: true)
            ->get();
    }

    /**
     * Get related products in the same category.
     *
     * @return list<self>
     */
    public function related(int $limit = 4): array
    {
        $categories = $this->terms('product_category');

        if (empty($categories)) {
            return [];
        }

        return static::query()
            ->whereTax('product_category', wp_list_pluck($categories, 'term_id'), field: 'term_id')
            ->exclude($this->ID)
            ->limit($limit)
            ->get();
    }
}
```

## Using in Templates

```twig
{% verbatim %}{# In a template controller #}
{% for product in products %}
    <article>
        <h2>{{ product.title }}</h2>
        <p class="price">{{ product.price|number_format(2) }} €</p>
    </article>
{% endfor %}{% endverbatim %}
```

## Related

- [Post Types](/guide/post-types) — Registering custom post types
- [Template Controllers](/guide/template-controllers) — Using queries in controllers
- [API Reference: #[AsPostType]](/api/as-post-type)
