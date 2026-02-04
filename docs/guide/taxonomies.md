# Taxonomies

WP Tempest uses `#[AsTaxonomy]` to register custom taxonomies.

## Basic Taxonomy

```php
<?php
// app/Models/ProductCategory.php

namespace App\Models;

use Studiometa\WPTempest\Attributes\AsTaxonomy;

#[AsTaxonomy(
    name: 'product_category',
    postTypes: ['product'],
    singular: 'Category',
    plural: 'Categories',
    hierarchical: true,
)]
final class ProductCategory
{
}
```

## Full Configuration

```php
<?php

namespace App\Models;

use Studiometa\WPTempest\Attributes\AsTaxonomy;

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
final class ProductCategory
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
final class ProductCategory {}
```

### Flat (like Tags)

```php
#[AsTaxonomy(
    name: 'product_tag',
    postTypes: ['product'],
    hierarchical: false,
)]
final class ProductTag {}
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
final class Location {}
```

## Advanced Configuration

For complex taxonomies, implement `ConfiguresTaxonomy` interface:

```php
<?php

namespace App\Models;

use Studiometa\WPTempest\Attributes\AsTaxonomy;
use Studiometa\WPTempest\Contracts\ConfiguresTaxonomy;

#[AsTaxonomy(
    name: 'skill',
    postTypes: ['team'],
    singular: 'Skill',
    plural: 'Skills',
)]
final class Skill implements ConfiguresTaxonomy
{
    /**
     * Customize the taxonomy arguments.
     */
    public static function taxonomyArgs(array $args): array
    {
        // Custom capabilities
        $args['capabilities'] = [
            'manage_terms' => 'manage_skills',
            'edit_terms' => 'edit_skills',
            'delete_terms' => 'delete_skills',
            'assign_terms' => 'assign_skills',
        ];

        // Custom labels
        $args['labels']['add_new_item'] = 'Add New Skill';
        $args['labels']['search_items'] = 'Search Skills';

        return $args;
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

Group post types and taxonomies together:

```
app/Models/
├── Product.php           # Post type
├── ProductCategory.php   # Taxonomy for products
├── ProductTag.php        # Taxonomy for products
├── Event.php             # Post type
├── EventType.php         # Taxonomy for events
└── Location.php          # Shared taxonomy
```

## Attribute Parameters

| Parameter         | Type       | Default    | Description                    |
| ----------------- | ---------- | ---------- | ------------------------------ |
| `name`            | `string`   | _required_ | Taxonomy slug                  |
| `postTypes`       | `string[]` | `[]`       | Associated post types          |
| `singular`        | `?string`  | `null`     | Singular label                 |
| `plural`          | `?string`  | `null`     | Plural label                   |
| `public`          | `bool`     | `true`     | Public visibility              |
| `hierarchical`    | `bool`     | `false`    | Hierarchical (like categories) |
| `showInRest`      | `bool`     | `true`     | REST API & Gutenberg support   |
| `showAdminColumn` | `bool`     | `true`     | Show in admin list             |
| `rewriteSlug`     | `?string`  | `null`     | Custom URL slug                |

## See Also

- [Post Types](./post-types)
- [API Reference: #[AsTaxonomy]](/api/as-taxonomy)
