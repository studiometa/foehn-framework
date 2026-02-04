# #[AsTimberModel]

Register a Timber class map for a post type or taxonomy without registering the type itself.

## Signature

```php
<?php

namespace Studiometa\Foehn\Attributes;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsTimberModel
{
    /**
     * @param string $name Post type or taxonomy slug to map to
     */
    public function __construct(
        public string $name,
    );
}
```

## Use Case

Use `#[AsTimberModel]` when you want to add custom methods to a Timber model for an **existing** post type or taxonomy, without re-registering it.

Common scenarios:

- Extending built-in types like `post`, `page`, or `attachment`
- Adding methods to post types registered by plugins (WooCommerce, etc.)
- Mapping taxonomies like `category` or `post_tag`

For **custom** post types and taxonomies, use [`#[AsPostType]`](./as-post-type) or [`#[AsTaxonomy]`](./as-taxonomy) instead — they handle both registration and Timber mapping.

## Examples

### Extending Posts

```php
<?php

namespace App\Models;

use Studiometa\Foehn\Attributes\AsTimberModel;
use Timber\Post;

#[AsTimberModel(name: 'post')]
final class Article extends Post
{
    /**
     * Get the estimated reading time in minutes.
     */
    public function readingTime(): int
    {
        $wordCount = str_word_count(wp_strip_all_tags($this->content()));
        return max(1, (int) ceil($wordCount / 200));
    }

    /**
     * Check if the post has a featured video.
     */
    public function hasFeaturedVideo(): bool
    {
        return !empty($this->meta('featured_video_url'));
    }
}
```

### Extending Pages

```php
<?php

namespace App\Models;

use Studiometa\Foehn\Attributes\AsTimberModel;
use Timber\Post;

#[AsTimberModel(name: 'page')]
final class Page extends Post
{
    /**
     * Get child pages.
     *
     * @return Page[]
     */
    public function children(): array
    {
        return \Timber\Timber::get_posts([
            'post_type' => 'page',
            'post_parent' => $this->ID,
            'orderby' => 'menu_order',
            'order' => 'ASC',
        ]);
    }

    /**
     * Check if this is a parent page.
     */
    public function hasChildren(): bool
    {
        return count($this->children()) > 0;
    }
}
```

### Extending Categories

```php
<?php

namespace App\Models;

use Studiometa\Foehn\Attributes\AsTimberModel;
use Timber\Term;

#[AsTimberModel(name: 'category')]
final class Category extends Term
{
    /**
     * Get the category icon from ACF.
     */
    public function icon(): ?string
    {
        return $this->meta('icon') ?: null;
    }

    /**
     * Get the category color from ACF.
     */
    public function color(): string
    {
        return $this->meta('color') ?: '#333333';
    }
}
```

### Extending WooCommerce Products

```php
<?php

namespace App\Models;

use Studiometa\Foehn\Attributes\AsTimberModel;
use Timber\Post;

#[AsTimberModel(name: 'product')]
final class Product extends Post
{
    /**
     * Get the WooCommerce product object.
     */
    public function wcProduct(): ?\WC_Product
    {
        return wc_get_product($this->ID) ?: null;
    }

    /**
     * Get formatted price.
     */
    public function formattedPrice(): string
    {
        $product = $this->wcProduct();
        return $product ? $product->get_price_html() : '';
    }
}
```

## Usage in Templates

Once mapped, Timber automatically uses your class:

```twig
{# single.twig #}
<article>
    <h1>{{ post.title }}</h1>
    <p class="reading-time">{{ post.readingTime }} min read</p>

    {% if post.hasFeaturedVideo %}
        {# Show video player #}
    {% endif %}

    {{ post.content }}
</article>
```

```twig
{# archive.twig #}
{% for post in posts %}
    {% set categories = post.terms('category') %}
    {% for category in categories %}
        <span style="color: {{ category.color }}">
            {{ category.icon }} {{ category.name }}
        </span>
    {% endfor %}
{% endfor %}
```

## Parameters

| Parameter | Type     | Required | Description                       |
| --------- | -------- | -------- | --------------------------------- |
| `name`    | `string` | Yes      | Post type or taxonomy slug to map |

## Requirements

The class must extend one of:

- `Timber\Post` for post types
- `Timber\Term` for taxonomies

## See Also

- [Guide: Post Types](/guide/post-types) — Custom post types with `#[AsPostType]`
- [Guide: Taxonomies](/guide/taxonomies) — Custom taxonomies with `#[AsTaxonomy]`
- [Timber Documentation](https://timber.github.io/docs/v2/guides/class-maps/)
