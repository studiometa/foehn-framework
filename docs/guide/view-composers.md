# View Composers

View composers automatically inject data into specific templates. Use `#[AsViewComposer]` to define composers.

## Basic View Composer

```php
<?php
// app/Views/Composers/HeaderComposer.php

namespace App\Views\Composers;

use Studiometa\WPTempest\Attributes\AsViewComposer;
use Studiometa\WPTempest\Contracts\ViewComposerInterface;

#[AsViewComposer('*')]
final class HeaderComposer implements ViewComposerInterface
{
    public function compose(array $context): array
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
#[AsViewComposer('single')]
final class SingleComposer implements ViewComposerInterface
{
    public function compose(array $context): array
    {
        $context['related_posts'] = $this->getRelatedPosts($context['post']);
        return $context;
    }
}
```

### Specific Template

```php
#[AsViewComposer('single-product')]
final class ProductComposer implements ViewComposerInterface
{
    public function compose(array $context): array
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
#[AsViewComposer('single-*')]
final class AllSinglesComposer implements ViewComposerInterface {}

// All archive templates
#[AsViewComposer('archive-*')]
final class AllArchivesComposer implements ViewComposerInterface {}

// Global (all templates)
#[AsViewComposer('*')]
final class GlobalComposer implements ViewComposerInterface {}
```

### Multiple Templates

```php
#[AsViewComposer(['home', 'front-page'])]
final class HomeComposer implements ViewComposerInterface
{
    public function compose(array $context): array
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
#[AsViewComposer('*', priority: 5)]
final class BaseComposer implements ViewComposerInterface {}

// Runs second (default)
#[AsViewComposer('*', priority: 10)]
final class DefaultComposer implements ViewComposerInterface {}

// Runs last
#[AsViewComposer('*', priority: 20)]
final class FinalComposer implements ViewComposerInterface {}
```

## Dependency Injection

Composers support constructor injection:

```php
<?php

namespace App\Views\Composers;

use App\Services\CartService;
use Studiometa\WPTempest\Attributes\AsViewComposer;
use Studiometa\WPTempest\Contracts\ViewComposerInterface;

#[AsViewComposer('*')]
final class CartComposer implements ViewComposerInterface
{
    public function __construct(
        private readonly CartService $cart,
    ) {}

    public function compose(array $context): array
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

### Navigation Composer

```php
#[AsViewComposer('*')]
final class NavigationComposer implements ViewComposerInterface
{
    public function compose(array $context): array
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

### Archive Composer

```php
#[AsViewComposer('archive-*')]
final class ArchiveComposer implements ViewComposerInterface
{
    public function compose(array $context): array
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

### Search Composer

```php
#[AsViewComposer('search')]
final class SearchComposer implements ViewComposerInterface
{
    public function compose(array $context): array
    {
        $context['search_query'] = get_search_query();
        $context['result_count'] = $GLOBALS['wp_query']->found_posts;

        return $context;
    }
}
```

## Organizing Composers

```
app/Views/Composers/
├── GlobalComposer.php        # Site-wide data
├── NavigationComposer.php    # Menus
├── ArchiveComposer.php       # Archive pages
├── SingleComposer.php        # Single posts
├── ProductComposer.php       # Product-specific
└── CartComposer.php          # Cart data
```

## See Also

- [Template Controllers](./template-controllers)
- [API Reference: #[AsViewComposer]](/api/as-view-composer)
- [API Reference: ViewComposerInterface](/api/view-composer-interface)
