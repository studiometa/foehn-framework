# BlockPatternInterface

Optional interface for block patterns with dynamic content.

## Signature

```php
<?php

namespace Studiometa\Foehn\Contracts;

interface BlockPatternInterface
{
    /**
     * Provide dynamic context for the pattern template.
     *
     * @return array<string, mixed> Template context
     */
    public function context(): array;
}
```

## Methods

### context()

Return data that will be passed to the pattern's Twig template.

```php
public function context(): array
{
    return [
        'posts' => \Timber\Timber::get_posts([
            'posts_per_page' => 3,
        ]),
        'categories' => \Timber\Timber::get_terms('category'),
    ];
}
```

## Usage

This interface is **optional**. Simple patterns don't need it:

```php
// Static pattern - no interface needed
#[AsBlockPattern(
    name: 'theme/hero',
    title: 'Hero Section',
    categories: ['featured'],
)]
final class HeroPattern {}
```

For dynamic content, implement the interface:

```php
<?php

namespace App\Patterns;

use Studiometa\Foehn\Attributes\AsBlockPattern;
use Studiometa\Foehn\Contracts\BlockPatternInterface;

#[AsBlockPattern(
    name: 'theme/latest-posts',
    title: 'Latest Posts Grid',
    categories: ['posts'],
)]
final class LatestPosts implements BlockPatternInterface
{
    public function context(): array
    {
        return [
            'posts' => \Timber\Timber::get_posts([
                'post_type' => 'post',
                'posts_per_page' => 3,
            ]),
        ];
    }
}
```

### Template

```twig
{# patterns/latest-posts.twig #}
<!-- wp:columns -->
<div class="wp-block-columns">
    {% for post in posts %}
    <!-- wp:column -->
    <div class="wp-block-column">
        <!-- wp:heading {"level":3} -->
        <h3>{{ post.title }}</h3>
        <!-- /wp:heading -->

        <!-- wp:paragraph -->
        <p>{{ post.excerpt }}</p>
        <!-- /wp:paragraph -->
    </div>
    <!-- /wp:column -->
    {% endfor %}
</div>
<!-- /wp:columns -->
```

### With Dependencies

```php
<?php

namespace App\Patterns;

use App\Services\ProductService;
use Studiometa\Foehn\Attributes\AsBlockPattern;
use Studiometa\Foehn\Contracts\BlockPatternInterface;

#[AsBlockPattern(
    name: 'theme/featured-products',
    title: 'Featured Products',
    categories: ['products'],
)]
final class FeaturedProducts implements BlockPatternInterface
{
    public function __construct(
        private readonly ProductService $products,
    ) {}

    public function context(): array
    {
        return [
            'products' => $this->products->getFeatured(4),
        ];
    }
}
```

## Related

- [Guide: Block Patterns](/guide/block-patterns)
- [`#[AsBlockPattern]`](./as-block-pattern)
