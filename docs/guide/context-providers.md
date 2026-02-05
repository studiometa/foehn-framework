# Context Providers

Context providers automatically inject data into specific templates. Use `#[AsContextProvider]` to define providers.

## Basic Context Provider

```php
<?php
// app/ContextProviders/HeaderContextProvider.php

namespace App\ContextProviders;

use Studiometa\Foehn\Attributes\AsContextProvider;
use Studiometa\Foehn\Contracts\ContextProviderInterface;

#[AsContextProvider('*')]
final class HeaderContextProvider implements ContextProviderInterface
{
    public function provide(array $context): array
    {
        $context['site_name'] = get_bloginfo('name');
        $context['primary_menu'] = \Timber\Timber::get_menu('primary');

        return $context;
    }
}
```

## Template Matching

### Single Template

```php
#[AsContextProvider('single')]
final class SingleContextProvider implements ContextProviderInterface
{
    public function provide(array $context): array
    {
        $context['related_posts'] = $this->getRelatedPosts($context['post']);
        return $context;
    }
}
```

### Specific Template

```php
#[AsContextProvider('single-product')]
final class ProductContextProvider implements ContextProviderInterface
{
    public function provide(array $context): array
    {
        $context['categories'] = $context['post']->terms('product_category');
        $context['related'] = $context['post']->relatedProducts(4);
        return $context;
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
    public function provide(array $context): array
    {
        $context['featured_posts'] = \Timber\Timber::get_posts([
            'posts_per_page' => 3,
            'meta_key' => 'featured',
            'meta_value' => '1',
        ]);

        return $context;
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

#[AsContextProvider('*')]
final class CartContextProvider implements ContextProviderInterface
{
    public function __construct(
        private readonly CartService $cart,
    ) {}

    public function provide(array $context): array
    {
        $context['cart'] = [
            'count' => $this->cart->getItemCount(),
            'total' => $this->cart->getTotal(),
        ];

        return $context;
    }
}
```

## Real-World Examples

### Navigation Provider

```php
#[AsContextProvider('*')]
final class NavigationContextProvider implements ContextProviderInterface
{
    public function provide(array $context): array
    {
        $context['menus'] = [
            'primary' => \Timber\Timber::get_menu('primary'),
            'footer' => \Timber\Timber::get_menu('footer'),
            'social' => \Timber\Timber::get_menu('social'),
        ];

        return $context;
    }
}
```

### Archive Provider

```php
#[AsContextProvider('archive-*')]
final class ArchiveContextProvider implements ContextProviderInterface
{
    public function provide(array $context): array
    {
        global $wp_query;

        $context['pagination'] = \Timber\Timber::get_pagination();
        $context['found_posts'] = $wp_query->found_posts;
        $context['current_page'] = max(1, get_query_var('paged'));
        $context['total_pages'] = $wp_query->max_num_pages;

        return $context;
    }
}
```

### Search Provider

```php
#[AsContextProvider('search')]
final class SearchContextProvider implements ContextProviderInterface
{
    public function provide(array $context): array
    {
        $context['search_query'] = get_search_query();
        $context['result_count'] = $GLOBALS['wp_query']->found_posts;

        return $context;
    }
}
```

## Organizing Providers

```
app/ContextProviders/
├── GlobalContextProvider.php        # Site-wide data
├── NavigationContextProvider.php    # Menus
├── ArchiveContextProvider.php       # Archive pages
├── SingleContextProvider.php        # Single posts
├── ProductContextProvider.php       # Product-specific
└── CartContextProvider.php          # Cart data
```

## See Also

- [Template Controllers](./template-controllers)
- [API Reference: #[AsContextProvider]](/api/as-context-provider)
- [API Reference: ContextProviderInterface](/api/context-provider-interface)
