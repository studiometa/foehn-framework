# ContextProviderInterface

Interface for context providers that add data to templates.

## Signature

```php
<?php

namespace Studiometa\Foehn\Contracts;

use Studiometa\Foehn\Views\TemplateContext;

interface ContextProviderInterface
{
    /**
     * Provide additional data for the view context.
     *
     * @param TemplateContext $context Current template context
     * @return TemplateContext Modified context with additional data
     */
    public function provide(TemplateContext $context): TemplateContext;
}
```

## Methods

### provide(TemplateContext $context)

Receives the current template context and returns a modified context with additional data. The context is immutable - use `with()`, `merge()`, or `withDto()` to add data.

```php
public function provide(TemplateContext $context): TemplateContext
{
    // Add new data (immutable)
    return $context
        ->with('site_name', get_bloginfo('name'))
        ->with('related_posts', $this->getRelatedPosts($context->post));
}
```

## TemplateContext API

The `TemplateContext` object provides typed access and immutable updates:

```php
// Typed properties
$context->post;   // ?Post
$context->posts;  // ?PostCollectionInterface
$context->site;   // Site
$context->user;   // ?User

// Post type casting
$product = $context->post(Product::class); // ?Product

// Dynamic keys
$context->get('key');           // mixed
$context->get('key', 'default'); // with default
$context->has('key');           // bool

// Immutable updates
$context = $context->with('key', $value);
$context = $context->merge(['a' => 1, 'b' => 2]);
$context = $context->withDto($myDto);
```

## Usage

```php
<?php

namespace App\ContextProviders;

use Studiometa\Foehn\Attributes\AsContextProvider;
use Studiometa\Foehn\Contracts\ContextProviderInterface;
use Studiometa\Foehn\Views\TemplateContext;

#[AsContextProvider('*')]
final class GlobalContextProvider implements ContextProviderInterface
{
    public function provide(TemplateContext $context): TemplateContext
    {
        return $context
            ->with('current_year', date('Y'))
            ->with('is_home', is_front_page());
    }
}
```

### With Dependency Injection

```php
<?php

namespace App\ContextProviders;

use App\Services\CartService;
use Studiometa\Foehn\Attributes\AsContextProvider;
use Studiometa\Foehn\Contracts\ContextProviderInterface;
use Studiometa\Foehn\Views\TemplateContext;

#[AsContextProvider('*')]
final class CartContextProvider implements ContextProviderInterface
{
    public function __construct(
        private readonly CartService $cart,
    ) {}

    public function provide(TemplateContext $context): TemplateContext
    {
        return $context->with('cart', [
            'count' => $this->cart->getItemCount(),
            'total' => $this->cart->getTotal(),
        ]);
    }
}
```

### With Post Type Casting

```php
use App\Models\Product;

#[AsContextProvider('single-product')]
final class ProductContextProvider implements ContextProviderInterface
{
    public function provide(TemplateContext $context): TemplateContext
    {
        $product = $context->post(Product::class);

        if (!$product) {
            return $context;
        }

        return $context
            ->with('related', $product->relatedProducts(4))
            ->with('categories', $product->categories());
    }
}
```

### With Typed DTO

```php
use Studiometa\Foehn\Contracts\Arrayable;
use Studiometa\Foehn\Concerns\HasToArray;

final readonly class ArchiveData implements Arrayable
{
    use HasToArray;

    public function __construct(
        public int $foundPosts,
        public int $currentPage,
    ) {}
}

#[AsContextProvider('archive-*')]
final class ArchiveContextProvider implements ContextProviderInterface
{
    public function provide(TemplateContext $context): TemplateContext
    {
        return $context->withDto(new ArchiveData(
            foundPosts: WP::query()->found_posts,
            currentPage: max(1, get_query_var('paged')),
        ));
    }
}
```

## Related

- [Guide: Context Providers](/guide/context-providers)
- [TemplateContext](/guide/template-controllers#templatecontext)
- [`#[AsContextProvider]`](./as-context-provider)
