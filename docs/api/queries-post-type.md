# QueriesPostType

Trait providing fluent query methods for post type models. Used by the base `Post` and `Page` models.

## Signature

```php
<?php

namespace Studiometa\Foehn\Concerns;

trait QueriesPostType
{
    public static function query(): PostQueryBuilder;
    public static function all(int $limit = -1): array;
    public static function find(int $id): ?static;
    public static function first(array $args = []): ?static;
    public static function count(array $args = []): int;
    public static function exists(array $args = []): bool;
}
```

## Requirements

The class using this trait must:

1. Be registered via `#[AsPostType]` or `#[AsTimberModel]` (so the `PostTypeRegistry` knows the mapping)
2. Extend `Timber\Post` (or `Studiometa\Foehn\Models\Post`)

## Methods

### query()

Returns a new `PostQueryBuilder` for fluent query construction:

```php
$products = Product::query()
    ->limit(10)
    ->whereTax('category', 'featured')
    ->orderBy('date', 'DESC')
    ->get();
```

### all()

Get all published posts of this type:

```php
$all = Product::all();
$ten = Product::all(limit: 10);
```

### find()

Find a post by ID:

```php
$product = Product::find(42);
```

### first()

Get the first matching post:

```php
$product = Product::first();
$product = Product::first(['meta_key' => 'featured', 'meta_value' => '1']);
```

### count()

Count matching posts:

```php
$total = Product::count();
$featured = Product::count(['meta_key' => 'featured', 'meta_value' => '1']);
```

### exists()

Check if any matching posts exist:

```php
if (Product::exists()) {
    // At least one product exists
}
```

## Base Models

Føhn ships with two base models that already include this trait:

| Class                          | Post Type | Description                   |
| ------------------------------ | --------- | ----------------------------- |
| `Studiometa\Foehn\Models\Post` | `post`    | Base model for all post types |
| `Studiometa\Foehn\Models\Page` | `page`    | Base model for pages          |

Custom post type models should extend `Post`:

```php
use Studiometa\Foehn\Attributes\AsPostType;
use Studiometa\Foehn\Models\Post;

#[AsPostType(name: 'product', singular: 'Product', plural: 'Products')]
final class Product extends Post
{
    // Query methods are inherited
}
```

## Related

- [Guide: Querying Posts](/guide/querying-posts)
- [PostQueryBuilder](./post-query-builder)
- [Guide: Post Types](/guide/post-types)
