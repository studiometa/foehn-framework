# Template Controllers

Template controllers provide full control over template rendering. Use `#[AsTemplateController]` to handle specific WordPress templates.

## Basic Template Controller

```php
<?php
// app/Controllers/SingleController.php

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

## TemplateContext

The `handle()` method receives a typed `TemplateContext` object that provides:

- **Typed properties** for Timber globals (`post`, `posts`, `site`, `user`)
- **Safe casting** for custom post types
- **Immutable updates** via `with()` and `merge()`
- **DTO support** for type-safe custom data

### Typed Properties

```php
public function handle(TemplateContext $context): ?string
{
    // Typed access to Timber globals
    $post = $context->post;     // ?Post
    $posts = $context->posts;   // ?PostCollectionInterface
    $site = $context->site;     // Site
    $user = $context->user;     // ?User

    return $this->view->render('single', $context);
}
```

### Custom Post Type Casting

Use `post()` method to safely cast to your custom post type:

```php
use App\Models\Product;

public function handle(TemplateContext $context): ?string
{
    // Returns ?Product with full IDE support
    $product = $context->post(Product::class);

    if ($product === null) {
        return null; // Let WordPress handle it
    }

    // Full autocomplete for Product methods
    $context = $context->with('price', $product->formattedPrice());

    return $this->view->render('single-product', $context);
}
```

### Typed Posts Collection

Use `posts()` method to validate the collection contains your expected post type:

```php
use App\Models\Product;

#[AsTemplateController('archive-product')]
final class ProductArchiveController implements TemplateControllerInterface
{
    public function handle(TemplateContext $context): ?string
    {
        // Returns ?PostCollectionInterface<Product>
        $products = $context->posts(Product::class);

        if ($products === null) {
            return null;
        }

        // All items in $products are Product instances
        foreach ($products as $product) {
            $product->formattedPrice(); // IDE support
        }

        return $this->view->render('archive-product', $context);
    }
}
```

### Adding Data (Immutable)

The context is immutable. Use `with()` or `merge()` to add data:

```php
public function handle(TemplateContext $context): ?string
{
    // Single key
    $context = $context->with('featured', $this->getFeatured());

    // Multiple keys
    $context = $context->merge([
        'categories' => $this->getCategories(),
        'tags' => $this->getTags(),
    ]);

    // Chained
    $context = $context
        ->with('hero', $this->getHeroData())
        ->with('testimonials', $this->getTestimonials());

    return $this->view->render('home', $context);
}
```

### Dynamic Keys

Access dynamic keys (from context providers, etc.) via `get()` or array syntax:

```php
public function handle(TemplateContext $context): ?string
{
    // Via get() method
    $pagination = $context->get('pagination');
    $customData = $context->get('custom_key', 'default');

    // Via ArrayAccess
    $pagination = $context['pagination'];

    // Check existence
    if ($context->has('pagination')) {
        // ...
    }

    return $this->view->render('archive', $context);
}
```

### Typed DTOs

For complex page data, use typed DTOs with `withDto()`:

```php
use Studiometa\Foehn\Contracts\Arrayable;
use Studiometa\Foehn\Concerns\HasToArray;

final readonly class ProductPageData implements Arrayable
{
    use HasToArray;

    public function __construct(
        public PostCollection $related,
        public array $categories,
        public ?float $averageRating,
    ) {}
}

public function handle(TemplateContext $context): ?string
{
    $product = $context->post(Product::class);

    $pageData = new ProductPageData(
        related: $product->related(4),
        categories: $product->categories(),
        averageRating: $product->averageRating(),
    );

    // DTO properties are flattened for Twig access
    $context = $context->withDto($pageData);

    // Later, retrieve the typed DTO if needed
    $data = $context->dto(ProductPageData::class);
    $data->averageRating; // ?float with IDE support

    return $this->view->render('single-product', $context);
}
```

In Twig, DTO properties are directly accessible:

```twig
<h1>{{ post.title }}</h1>
<p>Rating: {{ averageRating }}</p>

{% for item in related %}
    <a href="{{ item.link }}">{{ item.title }}</a>
{% endfor %}
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
    public function handle(TemplateContext $context): ?string
    {
        $post = $context->post;

        // Only handle published posts
        if ($post?->post_status !== 'publish') {
            return null; // Let WordPress handle it
        }

        return $this->view->render('single', $context);
    }
}
```

## Real-World Examples

### Home Page Controller

```php
<?php

namespace App\Controllers;

use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Studiometa\Foehn\Views\TemplateContext;

#[AsTemplateController('front-page')]
final class HomeController implements TemplateControllerInterface
{
    public function __construct(
        private readonly ViewEngineInterface $view,
    ) {}

    public function handle(TemplateContext $context): ?string
    {
        $context = $context
            ->with('hero', $this->getHeroData())
            ->with('featured_products', $this->getFeaturedProducts())
            ->with('testimonials', $this->getTestimonials())
            ->with('latest_posts', $this->getLatestPosts());

        return $this->view->render('pages/home', $context);
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

### Archive Controller with Pagination

```php
<?php

namespace App\Controllers;

use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Studiometa\Foehn\Views\TemplateContext;

#[AsTemplateController(['archive', 'archive-*', 'category', 'tag'])]
final class ArchiveController implements TemplateControllerInterface
{
    public function __construct(
        private readonly ViewEngineInterface $view,
    ) {}

    public function handle(TemplateContext $context): ?string
    {
        if ($context->posts) {
            $context = $context->with('pagination', $context->posts->pagination([
                'mid_size' => 2,
                'end_size' => 1,
            ]));
        }

        $context = $context
            ->with('archive_title', get_the_archive_title())
            ->with('archive_description', get_the_archive_description());

        return $this->view->render('pages/archive', $context);
    }
}
```

### Search Controller

```php
<?php

namespace App\Controllers;

use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Studiometa\Foehn\Helpers\WP;
use Studiometa\Foehn\Views\TemplateContext;

#[AsTemplateController('search')]
final class SearchController implements TemplateControllerInterface
{
    public function __construct(
        private readonly ViewEngineInterface $view,
    ) {}

    public function handle(TemplateContext $context): ?string
    {
        $context = $context
            ->with('search_query', get_search_query())
            ->with('found_posts', WP::query()->found_posts);

        if ($context->posts) {
            $context = $context->with('pagination', $context->posts->pagination());
        }

        return $this->view->render('pages/search', $context);
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
app/Controllers/
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
