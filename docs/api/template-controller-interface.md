# TemplateControllerInterface

Interface for template controllers that handle full template rendering.

## Signature

```php
<?php

namespace Studiometa\Foehn\Contracts;

use Studiometa\Foehn\Views\TemplateContext;

interface TemplateControllerInterface
{
    /**
     * Handle the template request.
     *
     * This method is called when WordPress would render a matching template.
     * It should return the rendered HTML or null to let WordPress handle it.
     *
     * @param TemplateContext $context Typed Timber context with post, site, user, etc.
     * @return string|null Rendered HTML or null to pass through
     */
    public function handle(TemplateContext $context): ?string;
}
```

## Methods

### handle(TemplateContext $context)

Handle the template request. The `$context` parameter is a typed object providing access to Timber globals and custom data. Return rendered HTML or `null` to let WordPress handle it normally.

```php
public function handle(TemplateContext $context): ?string
{
    $post = $context->post;

    // Return null to pass through to WordPress
    if (!$post) {
        return null;
    }

    return $this->view->render('single', $context);
}
```

## TemplateContext API

The `TemplateContext` object provides:

### Typed Properties

```php
$context->post;   // ?Post - current post
$context->posts;  // ?PostCollectionInterface - posts collection
$context->site;   // Site - site info
$context->user;   // ?User - current user
```

### Post Type Casting

```php
// Cast post to specific type with IDE support
$product = $context->post(Product::class); // ?Product

// Validate posts collection type
$products = $context->posts(Product::class); // ?PostCollectionInterface<Product>
```

### Dynamic Keys

```php
// Get dynamic keys
$context->get('key');              // mixed
$context->get('key', 'default');   // with default
$context->has('key');              // bool
$context['key'];                   // ArrayAccess
```

### Immutable Updates

```php
// Add single key
$context = $context->with('key', $value);

// Merge array or Arrayable
$context = $context->merge(['a' => 1, 'b' => 2]);

// Add typed DTO
$context = $context->withDto($myDto);
$context->dto(MyDto::class); // ?MyDto
```

### Conversion

```php
// Convert to array for ViewEngine
$context->toArray(); // array<string, mixed>
```

## Usage

```php
<?php

namespace App\Controllers;

use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Studiometa\Foehn\Views\TemplateContext;

#[AsTemplateController('single')]
final class SingleController implements TemplateControllerInterface
{
    public function __construct(
        private readonly ViewEngineInterface $view,
    ) {}

    public function handle(TemplateContext $context): ?string
    {
        $post = $context->post;

        $context = $context->with('related', $this->getRelatedPosts($post));

        return $this->view->render('single', $context);
    }

    private function getRelatedPosts($post): array
    {
        return \Timber\Timber::get_posts([
            'post_type' => $post->post_type,
            'posts_per_page' => 3,
            'post__not_in' => [$post->ID],
        ]);
    }
}
```

### Custom Post Type

```php
use App\Models\Product;

#[AsTemplateController('single-product')]
final class SingleProductController implements TemplateControllerInterface
{
    public function handle(TemplateContext $context): ?string
    {
        $product = $context->post(Product::class);

        if ($product === null) {
            return null;
        }

        $context = $context
            ->with('price', $product->formattedPrice())
            ->with('related', $product->relatedProducts(4));

        return $this->view->render('single-product', $context);
    }
}
```

### With Typed DTO

```php
use Studiometa\Foehn\Contracts\Arrayable;
use Studiometa\Foehn\Concerns\HasToArray;

final readonly class ProductPageData implements Arrayable
{
    use HasToArray;

    public function __construct(
        public array $related,
        public ?float $averageRating,
    ) {}
}

#[AsTemplateController('single-product')]
final class SingleProductController implements TemplateControllerInterface
{
    public function handle(TemplateContext $context): ?string
    {
        $product = $context->post(Product::class);

        if ($product === null) {
            return null;
        }

        $context = $context->withDto(new ProductPageData(
            related: $product->relatedProducts(4),
            averageRating: $product->averageRating(),
        ));

        return $this->view->render('single-product', $context);
    }
}
```

## Related

- [Guide: Template Controllers](/guide/template-controllers)
- [`#[AsTemplateController]`](./as-template-controller)
