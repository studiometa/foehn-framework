# Taxonomies

Føhn uses `#[AsTaxonomy]` to register custom taxonomies.

## Basic Taxonomy

```php
<?php
// app/Taxonomies/ProductCategory.php

namespace App\Taxonomies;

use Studiometa\Foehn\Attributes\AsTaxonomy;
use Timber\Term;

#[AsTaxonomy(
    name: 'product_category',
    postTypes: ['product'],
    singular: 'Category',
    plural: 'Categories',
    hierarchical: true,
)]
final class ProductCategory extends Term
{
}
```

## Full Configuration

```php
<?php

namespace App\Taxonomies;

use Studiometa\Foehn\Attributes\AsTaxonomy;
use Timber\Term;

#[AsTaxonomy(
    name: 'product_category',
    postTypes: ['product'],
    singular: 'Product Category',
    plural: 'Product Categories',
    public: true,
    hierarchical: true,
    showInRest: true,
    showAdminColumn: true,
    rewriteSlug: 'shop/category',
)]
final class ProductCategory extends Term
{
}
```

## Hierarchical vs Flat

### Hierarchical (like Categories)

```php
#[AsTaxonomy(
    name: 'product_category',
    postTypes: ['product'],
    hierarchical: true,
)]
final class ProductCategory extends Term {}
```

### Flat (like Tags)

```php
#[AsTaxonomy(
    name: 'product_tag',
    postTypes: ['product'],
    hierarchical: false,
)]
final class ProductTag extends Term {}
```

## Multiple Post Types

A taxonomy can be attached to multiple post types:

```php
#[AsTaxonomy(
    name: 'location',
    postTypes: ['event', 'team', 'office'],
    singular: 'Location',
    plural: 'Locations',
)]
final class Location extends Term {}
```

## Advanced Configuration

For complex taxonomies, implement `ConfiguresTaxonomy` interface:

```php
<?php

namespace App\Taxonomies;

use Studiometa\Foehn\Attributes\AsTaxonomy;
use Studiometa\Foehn\Contracts\ConfiguresTaxonomy;
use Studiometa\Foehn\PostTypes\TaxonomyBuilder;
use Timber\Term;

#[AsTaxonomy(
    name: 'skill',
    postTypes: ['team'],
    singular: 'Skill',
    plural: 'Skills',
)]
final class Skill extends Term implements ConfiguresTaxonomy
{
    /**
     * Customize the taxonomy via the builder.
     */
    public static function configureTaxonomy(TaxonomyBuilder $builder): TaxonomyBuilder
    {
        return $builder
            ->setCapabilities([
                'manage_terms' => 'manage_skills',
                'edit_terms' => 'edit_skills',
                'delete_terms' => 'delete_skills',
                'assign_terms' => 'assign_skills',
            ])
            ->setLabels([
                'add_new_item' => 'Add New Skill',
                'search_items' => 'Search Skills',
            ]);
    }
}
```

## Using in Templates

```twig
{# Display product categories #}
{% set categories = post.terms('product_category') %}

{% if categories %}
<ul class="categories">
    {% for category in categories %}
        <li>
            <a href="{{ category.link }}">{{ category.name }}</a>
        </li>
    {% endfor %}
</ul>
{% endif %}
```

## Query by Taxonomy

```php
// Get products in a category
$products = Timber::get_posts([
    'post_type' => 'product',
    'tax_query' => [
        [
            'taxonomy' => 'product_category',
            'field' => 'slug',
            'terms' => 'electronics',
        ],
    ],
]);
```

## Organizing Files

Post types and taxonomies live in separate directories:

```
app/
├── Models/               # Custom post types
│   ├── Product.php
│   └── Event.php
└── Taxonomies/           # Custom taxonomies
    ├── ProductCategory.php
    ├── ProductTag.php
    ├── EventType.php
    └── Location.php
```

## Attribute Parameters

| Parameter         | Type                    | Default    | Description                                        |
| ----------------- | ----------------------- | ---------- | -------------------------------------------------- |
| `name`            | `string`                | _required_ | Taxonomy slug                                      |
| `postTypes`       | `string[]`              | `[]`       | Associated post types                              |
| `singular`        | `?string`               | `null`     | Singular label                                     |
| `plural`          | `?string`               | `null`     | Plural label                                       |
| `public`          | `bool`                  | `true`     | Public visibility                                  |
| `hierarchical`    | `bool`                  | `false`    | Hierarchical (like categories)                     |
| `showInRest`      | `bool`                  | `true`     | REST API & Gutenberg support                       |
| `showAdminColumn` | `bool`                  | `true`     | Show in admin list                                 |
| `rewriteSlug`     | `?string`               | `null`     | Custom URL slug (shorthand for `rewrite`)          |
| `labels`          | `array<string, string>` | `[]`       | Custom labels (merged with auto-generated ones)    |
| `rewrite`         | `array\|false\|null`    | `null`     | Full rewrite config, `false` to disable, or `null` |

## See Also

- [Post Types](./post-types)
- [API Reference: #[AsTaxonomy]](/api/as-taxonomy)
