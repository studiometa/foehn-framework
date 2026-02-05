# Template Controllers

Template controllers provide full control over template rendering. Use `#[AsTemplateController]` to handle specific WordPress templates.

## Basic Template Controller

```php
<?php
// app/Views/Controllers/SingleController.php

namespace App\Views\Controllers;

use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;

#[AsTemplateController('single')]
final class SingleController implements TemplateControllerInterface
{
    public function __construct(
        private readonly ViewEngineInterface $view,
    ) {}

    public function handle(): ?string
    {
        $post = \Timber\Timber::get_post();

        return $this->view->render('single', [
            'post' => $post,
            'related' => $this->getRelatedPosts($post),
        ]);
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

## Template Matching

### WordPress Template Hierarchy

Template names follow the [WordPress template hierarchy](https://developer.wordpress.org/themes/basics/template-hierarchy/):

```php
// Home page
#[AsTemplateController('home')]
final class HomeController implements TemplateControllerInterface {}

// Front page
#[AsTemplateController('front-page')]
final class FrontPageController implements TemplateControllerInterface {}

// Single post
#[AsTemplateController('single')]
final class SingleController implements TemplateControllerInterface {}

// Single product
#[AsTemplateController('single-product')]
final class SingleProductController implements TemplateControllerInterface {}

// Archive
#[AsTemplateController('archive')]
final class ArchiveController implements TemplateControllerInterface {}

// Category archive
#[AsTemplateController('category')]
final class CategoryController implements TemplateControllerInterface {}

// 404 page
#[AsTemplateController('404')]
final class NotFoundController implements TemplateControllerInterface {}
```

### Wildcard Patterns

```php
// All single templates
#[AsTemplateController('single-*')]
final class AllSinglesController implements TemplateControllerInterface {}

// All archive templates
#[AsTemplateController('archive-*')]
final class AllArchivesController implements TemplateControllerInterface {}
```

### Multiple Templates

```php
#[AsTemplateController(['home', 'front-page'])]
final class HomeController implements TemplateControllerInterface {}
```

## Returning Null

Return `null` to let WordPress handle the template normally:

```php
#[AsTemplateController('single')]
final class SingleController implements TemplateControllerInterface
{
    public function handle(): ?string
    {
        $post = \Timber\Timber::get_post();

        // Only handle published posts
        if ($post->post_status !== 'publish') {
            return null; // Let WordPress handle it
        }

        return $this->view->render('single', ['post' => $post]);
    }
}
```

## Real-World Examples

### Home Page Controller

```php
<?php

namespace App\Views\Controllers;

use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;

#[AsTemplateController('front-page')]
final class HomeController implements TemplateControllerInterface
{
    public function __construct(
        private readonly ViewEngineInterface $view,
    ) {}

    public function handle(): ?string
    {
        return $this->view->render('pages/home', [
            'hero' => $this->getHeroData(),
            'featured_products' => $this->getFeaturedProducts(),
            'testimonials' => $this->getTestimonials(),
            'latest_posts' => $this->getLatestPosts(),
        ]);
    }

    private function getHeroData(): array
    {
        return [
            'title' => get_field('hero_title', 'option'),
            'subtitle' => get_field('hero_subtitle', 'option'),
            'cta' => get_field('hero_cta', 'option'),
        ];
    }

    private function getFeaturedProducts(): array
    {
        return \Timber\Timber::get_posts([
            'post_type' => 'product',
            'posts_per_page' => 4,
            'meta_key' => 'featured',
            'meta_value' => '1',
        ]);
    }

    private function getTestimonials(): array
    {
        return \Timber\Timber::get_posts([
            'post_type' => 'testimonial',
            'posts_per_page' => 6,
            'orderby' => 'rand',
        ]);
    }

    private function getLatestPosts(): array
    {
        return \Timber\Timber::get_posts([
            'post_type' => 'post',
            'posts_per_page' => 3,
        ]);
    }
}
```

### Product Archive Controller

```php
<?php

namespace App\Views\Controllers;

use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;

#[AsTemplateController('archive-product')]
final class ProductArchiveController implements TemplateControllerInterface
{
    public function __construct(
        private readonly ViewEngineInterface $view,
    ) {}

    public function handle(): ?string
    {
        return $this->view->render('archive-product', [
            'products' => \Timber\Timber::get_posts(),
            'categories' => $this->getCategories(),
            'filters' => $this->getActiveFilters(),
            'pagination' => \Timber\Timber::get_pagination(),
        ]);
    }

    private function getCategories(): array
    {
        return \Timber\Timber::get_terms([
            'taxonomy' => 'product_category',
            'hide_empty' => true,
        ]);
    }

    private function getActiveFilters(): array
    {
        return [
            'category' => $_GET['category'] ?? null,
            'sort' => $_GET['sort'] ?? 'date',
            'order' => $_GET['order'] ?? 'desc',
        ];
    }
}
```

### Search Controller

```php
<?php

namespace App\Views\Controllers;

use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;

#[AsTemplateController('search')]
final class SearchController implements TemplateControllerInterface
{
    public function __construct(
        private readonly ViewEngineInterface $view,
    ) {}

    public function handle(): ?string
    {
        global $wp_query;

        return $this->view->render('search', [
            'query' => get_search_query(),
            'results' => \Timber\Timber::get_posts(),
            'found' => $wp_query->found_posts,
            'pagination' => \Timber\Timber::get_pagination(),
        ]);
    }
}
```

## Context Providers vs Template Controllers

| Feature      | Context Provider             | Template Controller          |
| ------------ | ---------------------------- | ---------------------------- |
| **Purpose**  | Add data to existing context | Full control over rendering  |
| **Returns**  | Modified context array       | Rendered HTML string or null |
| **Multiple** | Can stack multiple providers | One controller per template  |
| **Use case** | Shared data (menus, etc.)    | Complex page logic           |

Use **Context Providers** for:

- Adding shared data to multiple templates
- Injecting navigation, footer data
- Simple context enrichment

Use **Template Controllers** for:

- Complex business logic
- Custom template resolution
- Full rendering control

## Organizing Controllers

```
app/Views/Controllers/
├── HomeController.php
├── SingleController.php
├── ArchiveController.php
├── ProductController.php
├── SearchController.php
└── NotFoundController.php
```

## See Also

- [Context Providers](./context-providers)
- [API Reference: #[AsTemplateController]](/api/as-template-controller)
- [API Reference: TemplateControllerInterface](/api/template-controller-interface)
