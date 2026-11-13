# Context Providers

Context providers automatically inject data into specific templates. Use `#[AsContextProvider]` to define providers.

A provider matches a **view name**: the name of the Twig file passed to `ViewEngine::render()`, such as `pages/single` or `blocks/hero`. It does not match the WordPress template name that a [template controller](./template-controllers) claims, such as `single-product`. A controller that handles `single-product` can render `pages/single-product` or `pages/single`, and a provider for `pages/single-product` only runs when that view is the one rendered.

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

### One View

```php
#[AsContextProvider('pages/single')]
final class SingleContextProvider implements ContextProviderInterface
{
    public function provide(TemplateContext $context): TemplateContext
    {
        return $context->with('related_posts', $this->getRelatedPosts($context->post));
    }
}
```

### A View for One Post Type

The starter's `SingleController` renders the first view that exists of `pages/single-{post-type}-{slug}`, `pages/single-{post-type}` and `pages/single`. A provider for products names the product view:

```php
#[AsContextProvider('pages/single-product')]
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

When a project has no `pages/single-product.twig`, the controller renders `pages/single` and this provider does not run. Name both views to cover both cases: `#[AsContextProvider(['pages/single-product', 'pages/single'])]`.

### Wildcard Patterns

```php
// Every single view: pages/single, pages/single-product, …
#[AsContextProvider('pages/single*')]
final class AllSinglesContextProvider implements ContextProviderInterface {}

// Every archive view
#[AsContextProvider('pages/archive*')]
final class AllArchivesContextProvider implements ContextProviderInterface {}

// Every view, blocks and patterns included
#[AsContextProvider('*')]
final class GlobalContextProvider implements ContextProviderInterface {}
```

`*` matches any characters, `/` included. Blocks (`blocks/<name>`), patterns, settings pages and [sections](./section-rendering) are rendered through `ViewEngine::render()` too, so a `*` provider runs for them as well. A Twig file included with `{% include %}` is not rendered on its own: it gets the context of the file that includes it.

### Multiple Views

```php
#[AsContextProvider(['pages/home', 'pages/front-page'])]
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

### See What Applies

`wp foehn inspect views` lists each Twig file of the theme with the providers its view name matches, in the order they run, and the keys they add. See [Template context](./cli-commands#template-context).

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
use Studiometa\Foehn\Helpers\WP;

#[AsContextProvider('pages/archive*')]
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

#[AsContextProvider('pages/search')]
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
use Studiometa\Foehn\Helpers\WP;

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

#[AsContextProvider('pages/archive*')]
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
