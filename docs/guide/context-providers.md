# Context Providers

Context providers automatically inject data into specific templates. Use `#[AsContextProvider]` to define providers.

## Basic Context Provider

```php
<?php
// app/ContextProviders/HeaderContextProvider.php

namespace App\ContextProviders;

use Studiometa\Foehn\Attributes\AsContextProvider;
use Studiometa\Foehn\Contracts\ContextProviderInterface;
use Studiometa\Foehn\Views\TemplateContext;

#[AsContextProvider('*')]
final class HeaderContextProvider implements ContextProviderInterface
{
    public function provide(TemplateContext $context): TemplateContext
    {
        return $context
            ->with('site_name', get_bloginfo('name'))
            ->with('primary_menu', \Timber\Timber::get_menu('primary'));
    }
}
```

## TemplateContext

The `provide()` method receives a typed `TemplateContext` object - the same one used in template controllers. This provides:

- **Typed access** to Timber globals (`$context->post`, `$context->site`, etc.)
- **Immutable updates** via `with()`, `merge()`, `withDto()`
- **Post type casting** via `$context->post(Product::class)`

See [Template Controllers](./template-controllers#templatecontext) for full `TemplateContext` documentation.

## Template Matching

### Single Template

```php
#[AsContextProvider('single')]
final class SingleContextProvider implements ContextProviderInterface
{
    public function provide(TemplateContext $context): TemplateContext
    {
        return $context->with('related_posts', $this->getRelatedPosts($context->post));
    }
}
```

### Specific Template

```php
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
            ->with('categories', $product->terms('product_category'))
            ->with('related', $product->relatedProducts(4));
    }
}
```

### Wildcard Patterns

```php
// All single templates (single-*, single-post, single-product, etc.)
#[AsContextProvider('single-*')]
final class AllSinglesContextProvider implements ContextProviderInterface {}

// All archive templates
#[AsContextProvider('archive-*')]
final class AllArchivesContextProvider implements ContextProviderInterface {}

// Global (all templates)
#[AsContextProvider('*')]
final class GlobalContextProvider implements ContextProviderInterface {}
```

### Multiple Templates

```php
#[AsContextProvider(['home', 'front-page'])]
final class HomeContextProvider implements ContextProviderInterface
{
    public function provide(TemplateContext $context): TemplateContext
    {
        return $context->with('featured_posts', \Timber\Timber::get_posts([
            'posts_per_page' => 3,
            'meta_key' => 'featured',
            'meta_value' => '1',
        ]));
    }
}
```

## Priority

Control execution order with priority (lower runs first):

```php
// Runs first
#[AsContextProvider('*', priority: 5)]
final class BaseContextProvider implements ContextProviderInterface {}

// Runs second (default)
#[AsContextProvider('*', priority: 10)]
final class DefaultContextProvider implements ContextProviderInterface {}

// Runs last
#[AsContextProvider('*', priority: 20)]
final class FinalContextProvider implements ContextProviderInterface {}
```

## Dependency Injection

Context providers support constructor injection:

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

## Real-World Examples

### Global Provider

```php
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

::: tip Menus are auto-injected
When using `#[AsMenu]` attributes, menus are automatically added to the context under the `menus` key. You don't need to add them manually in a context provider.
:::

### Archive Provider

```php
#[AsContextProvider('archive-*')]
final class ArchiveContextProvider implements ContextProviderInterface
{
    public function provide(TemplateContext $context): TemplateContext
    {
        if (!$context->posts) {
            return $context;
        }

        return $context
            ->with('pagination', $context->posts->pagination())
            ->with('found_posts', WP::query()->found_posts)
            ->with('current_page', max(1, get_query_var('paged')))
            ->with('total_pages', WP::query()->max_num_pages);
    }
}
```

### Search Provider

```php
use Studiometa\Foehn\Helpers\WP;

#[AsContextProvider('search')]
final class SearchContextProvider implements ContextProviderInterface
{
    public function provide(TemplateContext $context): TemplateContext
    {
        return $context
            ->with('search_query', get_search_query())
            ->with('result_count', WP::query()->found_posts);
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
        public int $totalPages,
        public ?object $pagination,
    ) {}
}

#[AsContextProvider('archive-*')]
final class ArchiveContextProvider implements ContextProviderInterface
{
    public function provide(TemplateContext $context): TemplateContext
    {
        if (!$context->posts) {
            return $context;
        }

        return $context->withDto(new ArchiveData(
            foundPosts: WP::query()->found_posts,
            currentPage: max(1, get_query_var('paged')),
            totalPages: WP::query()->max_num_pages,
            pagination: $context->posts->pagination(),
        ));
    }
}
```

## Organizing Providers

```
app/ContextProviders/
├── GlobalContextProvider.php        # Site-wide data
├── ArchiveContextProvider.php       # Archive pages
├── SingleContextProvider.php        # Single posts
├── ProductContextProvider.php       # Product-specific
└── CartContextProvider.php          # Cart data
```

## See Also

- [Template Controllers](./template-controllers)
- [API Reference: #[AsContextProvider]](/api/as-context-provider)
- [API Reference: ContextProviderInterface](/api/context-provider-interface)
