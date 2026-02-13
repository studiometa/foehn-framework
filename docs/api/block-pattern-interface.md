# BlockPatternInterface

Optional interface for block patterns with dynamic content.

## Signature

```php
<?php

namespace Studiometa\Foehn\Contracts;

interface BlockPatternInterface
{
    /**
     * Compose data for the pattern template.
     *
     * May return a plain array or an Arrayable DTO.
     *
     * @return array<string, mixed>|Arrayable Context variables for the template
     */
    public function compose(): array|Arrayable;
}
```

## Methods

### compose()

Return data that will be passed to the pattern's Twig template. Can return either a plain array or an `Arrayable` DTO.

```php
public function compose(): array
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
    public function compose(): array
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
{% verbatim %}{# patterns/latest-posts.twig #}
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
<!-- /wp:columns -->{% endverbatim %}
```

### With Arrayable DTO

```php
use Studiometa\Foehn\Contracts\Arrayable;
use Studiometa\Foehn\Concerns\HasToArray;

final readonly class FeaturedContext implements Arrayable
{
    use HasToArray;

    public function __construct(
        public array $products,
        public string $heading,
    ) {}
}

#[AsBlockPattern(name: 'theme/featured-products', title: 'Featured Products')]
final class FeaturedProducts implements BlockPatternInterface
{
    public function __construct(
        private readonly ProductService $products,
    ) {}

    public function compose(): FeaturedContext
    {
        return new FeaturedContext(
            products: $this->products->getFeatured(4),
            heading: __('Featured', 'theme'),
        );
    }
}
```

## Related

- [Guide: Block Patterns](/guide/block-patterns)
- [Guide: Arrayable DTOs](/guide/arrayable-dtos)
- [`#[AsBlockPattern]`](./as-block-pattern)
