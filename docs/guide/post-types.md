# Post Types

Føhn uses `#[AsPostType]` to register custom post types with Timber integration.

## Basic Post Type

```php
<?php
// app/Models/Product.php

namespace App\Models;

use Studiometa\Foehn\Attributes\AsPostType;
use Studiometa\Foehn\Models\Post;

#[AsPostType(
    name: 'product',
    singular: 'Product',
    plural: 'Products',
)]
final class Product extends Post
{
}
```

This registers a post type with sensible defaults, maps it in Timber's classmap, and provides fluent query methods via the `QueriesPostType` trait.

::: tip Base Models
Extend `Studiometa\Foehn\Models\Post` instead of `Timber\Post` to get built-in query methods like `Product::query()`, `Product::all()`, `Product::find()`, etc. See [Querying Posts](./querying-posts) for details.
:::

## Full Configuration

```php
<?php

namespace App\Models;

use Studiometa\Foehn\Attributes\AsPostType;
use Studiometa\Foehn\Models\Post;

#[AsPostType(
    name: 'product',
    singular: 'Product',
    plural: 'Products',
    public: true,
    hasArchive: true,
    showInRest: true,
    menuIcon: 'dashicons-cart',
    supports: ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
    taxonomies: ['product_category', 'product_tag'],
    rewriteSlug: 'shop',
)]
final class Product extends Post
{
}
```

## Custom Methods

Add business logic directly to your post type class:

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
    /**
     * Get the product price.
     */
    public function price(): ?float
    {
        $price = $this->meta('price');
        return $price ? (float) $price : null;
    }

    /**
     * Get the formatted price.
     */
    public function formattedPrice(): string
    {
        $price = $this->price();
        return $price ? sprintf('$%.2f', $price) : 'Price on request';
    }

    /**
     * Check if the product is on sale.
     */
    public function isOnSale(): bool
    {
        return (bool) $this->meta('on_sale');
    }

    /**
     * Get the sale price if on sale.
     */
    public function salePrice(): ?float
    {
        if (!$this->isOnSale()) {
            return null;
        }

        $salePrice = $this->meta('sale_price');
        return $salePrice ? (float) $salePrice : null;
    }

    /**
     * Get related products using the fluent query builder.
     *
     * @return list<self>
     */
    public function relatedProducts(int $limit = 4): array
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

Your custom methods are available in Twig templates:

```twig
{# templates/single-product.twig #}
{% extends 'base.twig' %}

{% block content %}
<article class="product">
    <h1>{{ post.title }}</h1>

    {% if post.thumbnail %}
        <img src="{{ post.thumbnail.src('large') }}" alt="{{ post.title }}">
    {% endif %}

    <div class="product-price">
        {% if post.isOnSale %}
            <span class="original-price">{{ post.formattedPrice }}</span>
            <span class="sale-price">${{ post.salePrice|number_format(2) }}</span>
        {% else %}
            <span class="price">{{ post.formattedPrice }}</span>
        {% endif %}
    </div>

    <div class="product-content">
        {{ post.content }}
    </div>

    {% set related = post.relatedProducts(4) %}
    {% if related %}
        <section class="related-products">
            <h2>Related Products</h2>
            <div class="grid">
                {% for product in related %}
                    {% include 'partials/product-card.twig' with { product: product } %}
                {% endfor %}
            </div>
        </section>
    {% endif %}
</article>
{% endblock %}
```

## Advanced Configuration

For complex post types, implement `ConfiguresPostType` interface:

```php
<?php

namespace App\Models;

use Studiometa\Foehn\Attributes\AsPostType;
use Studiometa\Foehn\Contracts\ConfiguresPostType;
use Studiometa\Foehn\Models\Post;
use Studiometa\Foehn\PostTypes\PostTypeBuilder;

#[AsPostType(name: 'event', singular: 'Event', plural: 'Events')]
final class Event extends Post implements ConfiguresPostType
{
    /**
     * Customize the post type via the builder.
     */
    public static function configurePostType(PostTypeBuilder $builder): PostTypeBuilder
    {
        return $builder
            ->setCapabilityType('event')
            ->setMapMetaCap(true)
            ->setLabels([
                'menu_name' => 'Calendar',
                'all_items' => 'All Events',
            ]);
    }
}
```

## Multiple Post Types

Each post type is a separate class:

```
app/Models/
├── Product.php
├── Event.php
├── Team.php
├── Testimonial.php
└── Portfolio.php
```

## Attribute Parameters

| Parameter      | Type                    | Default                            | Description                                        |
| -------------- | ----------------------- | ---------------------------------- | -------------------------------------------------- |
| `name`         | `string`                | _required_                         | Post type slug                                     |
| `singular`     | `?string`               | `null`                             | Singular label                                     |
| `plural`       | `?string`               | `null`                             | Plural label                                       |
| `public`       | `bool`                  | `true`                             | Public visibility                                  |
| `hasArchive`   | `bool`                  | `false`                            | Enable archive pages                               |
| `showInRest`   | `bool`                  | `true`                             | REST API & Gutenberg support                       |
| `menuIcon`     | `?string`               | `null`                             | Dashicon or URL                                    |
| `supports`     | `string[]`              | `['title', 'editor', 'thumbnail']` | Supported features                                 |
| `taxonomies`   | `string[]`              | `[]`                               | Associated taxonomies                              |
| `rewriteSlug`  | `?string`               | `null`                             | Custom URL slug (shorthand for `rewrite`)          |
| `hierarchical` | `bool`                  | `false`                            | Whether hierarchical (like pages)                  |
| `menuPosition` | `?int`                  | `null`                             | Position in the admin menu                         |
| `labels`       | `array<string, string>` | `[]`                               | Custom labels (merged with auto-generated ones)    |
| `rewrite`      | `array\|false\|null`    | `null`                             | Full rewrite config, `false` to disable, or `null` |

## See Also

- [Querying Posts](./querying-posts)
- [Taxonomies](./taxonomies)
- [API Reference: #[AsPostType]](/api/as-post-type)
