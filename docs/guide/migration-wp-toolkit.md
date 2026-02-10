# Migration from wp-toolkit

This guide helps you migrate from `studiometa/wp-toolkit` to `studiometa/foehn`. It covers every common pattern with before/after examples, common pitfalls, and a step-by-step checklist.

## Overview

Føhn replaces wp-toolkit's Manager pattern with attribute-based auto-discovery powered by Tempest Framework.

### Concept Mapping

| wp-toolkit                           | Føhn                                  | Notes                                      |
| ------------------------------------ | ------------------------------------- | ------------------------------------------ |
| `ThemeManager::init()`               | `Kernel::boot()`                      | Single entry point                         |
| `PostTypeManager`                    | `#[AsPostType]` on Timber\Post class  | Auto-registers Timber classmap             |
| `TaxonomyManager`                    | `#[AsTaxonomy]` on Timber\Term class  | Auto-registers Timber classmap             |
| `BlockManager`                       | `#[AsAcfBlock]` + `AcfBlockInterface` | Fields, compose, render in one class       |
| `ManagerInterface::run()`            | Attribute + auto-discovery            | No manual registration needed              |
| Manual `add_action()`/`add_filter()` | `#[AsAction]`/`#[AsFilter]`           | On class methods                           |
| Manual `register_nav_menus()`        | `#[AsMenu]`                           | Auto-adds to Timber context                |
| Manual `add_image_size()`            | `#[AsImageSize]`                      | Auto-enables `post-thumbnails`             |
| Repository classes                   | Use Timber directly                   | Timber is the data layer                   |
| `timber/context` filter              | `#[AsContextProvider]`                | Scoped to specific templates               |
| Manual `template_include` filter     | `#[AsTemplateController]`             | WordPress template hierarchy support       |
| Manual `register_rest_route()`       | `#[AsRestRoute]`                      | DI + permission management                 |
| Manual `add_shortcode()`             | `#[AsShortcode]`                      | DI support                                 |
| Manual `register_block_pattern()`    | `#[AsBlockPattern]`                   | Twig templates for patterns                |
| Manual Twig extension registration   | `#[AsTwigExtension]`                  | Priority-based ordering                    |
| No equivalent                        | `#[AsBlock]`                           | Native Gutenberg + Interactivity API       |
| No equivalent                        | `#[AsCliCommand]`                      | WP-CLI commands with DI                    |
| No equivalent                        | `#[AsAcfFieldGroup]`                   | Standalone ACF field groups                |
| No equivalent                        | `#[AsAcfOptionsPage]`                  | ACF options pages with fields              |

### What's Removed

These wp-toolkit patterns are intentionally not carried over:

| Deprecated Pattern     | Replacement                                      |
| ---------------------- | ------------------------------------------------ |
| Repository classes     | Use `Timber::get_posts()` / `Timber::get_post()` directly |
| `BaseModel` classes    | Extend `Timber\Post` or `Timber\Term` directly   |
| Manual service locator | Tempest's DI container (constructor injection)   |
| `$theme->register()`  | Auto-discovery (no registration needed)          |

## Step 1: Install Føhn

```bash
composer require studiometa/foehn
composer remove studiometa/wp-toolkit
```

> **Important**: Keep both packages installed temporarily if you need a gradual migration.

## Step 2: Update Theme Bootstrap

**Before (wp-toolkit):**

```php
<?php
// functions.php

use Studiometa\WPToolkit\Managers\ThemeManager;

$theme = new ThemeManager();
$theme->init();
```

**After (foehn):**

```php
<?php
// functions.php

require_once __DIR__ . '/vendor/autoload.php';

use Studiometa\Foehn\Kernel;

Kernel::boot(__DIR__ . '/app');
```

That's it — all your classes in `app/` are now auto-discovered.

## Step 3: Migrate Post Types

**Before (wp-toolkit):**

```php
<?php
// app/PostTypes/ProductPostType.php

namespace App\PostTypes;

use Studiometa\WPToolkit\Managers\PostTypeManager;

class ProductPostType extends PostTypeManager
{
    public static string $post_type = 'product';

    public function run(): void
    {
        register_post_type(self::$post_type, [
            'label' => 'Products',
            'public' => true,
            'has_archive' => true,
            'menu_icon' => 'dashicons-cart',
            'supports' => ['title', 'editor', 'thumbnail'],
            'show_in_rest' => true,
        ]);
    }
}
```

**After (foehn):**

```php
<?php
// app/Models/Product.php

namespace App\Models;

use Studiometa\Foehn\Attributes\AsPostType;
use Timber\Post;

#[AsPostType(
    name: 'product',
    singular: 'Product',
    plural: 'Products',
    public: true,
    hasArchive: true,
    menuIcon: 'dashicons-cart',
    supports: ['title', 'editor', 'thumbnail'],
    showInRest: true,
)]
final class Product extends Post
{
    public function price(): ?float
    {
        return $this->meta('price') ? (float) $this->meta('price') : null;
    }
}
```

**Key changes:**
- Class extends `Timber\Post` directly (auto-registered in Timber's classmap)
- No manual `register_post_type()` call
- Labels are auto-generated from `singular`/`plural`
- Custom methods are available in Twig: `{{ post.price }}`

## Step 4: Migrate Taxonomies

**Before (wp-toolkit):**

```php
<?php

namespace App\Taxonomies;

use Studiometa\WPToolkit\Managers\TaxonomyManager;

class ProductCategoryTaxonomy extends TaxonomyManager
{
    public static string $taxonomy = 'product_category';

    public function run(): void
    {
        register_taxonomy(self::$taxonomy, 'product', [
            'label' => 'Categories',
            'hierarchical' => true,
            'show_in_rest' => true,
        ]);
    }
}
```

**After (foehn):**

```php
<?php
// app/Taxonomies/ProductCategory.php

namespace App\Taxonomies;

use Studiometa\Foehn\Attributes\AsTaxonomy;
use Timber\Term;

#[AsTaxonomy(
    name: 'product_category',
    postTypes: ['product'],
    singular: 'Category',
    plural: 'Categories',
    hierarchical: true,
    showInRest: true,
)]
final class ProductCategory extends Term
{
    public function icon(): ?string
    {
        return $this->meta('category_icon');
    }
}
```

**Key changes:**
- Class extends `Timber\Term` (auto-registered in Timber's classmap)
- `postTypes` links the taxonomy to post types declaratively

## Step 5: Migrate ACF Blocks

**Before (wp-toolkit):**

```php
<?php
// app/Blocks/HeroBlock.php

namespace App\Blocks;

use Studiometa\WPToolkit\Managers\BlockManager;
use StoutLogic\AcfBuilder\FieldsBuilder;

class HeroBlock extends BlockManager
{
    public static string $name = 'hero';
    public static string $title = 'Hero';

    public function fields(): FieldsBuilder
    {
        return (new FieldsBuilder('hero'))
            ->addText('title')
            ->addWysiwyg('content');
    }

    public function data(array $block): array
    {
        return [
            'title' => get_field('title'),
            'content' => get_field('content'),
        ];
    }
}
```

**After (foehn):**

```php
<?php
// app/Blocks/Hero/HeroBlock.php

namespace App\Blocks\Hero;

use Studiometa\Foehn\Attributes\AsAcfBlock;
use Studiometa\Foehn\Contracts\AcfBlockInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use StoutLogic\AcfBuilder\FieldsBuilder;

#[AsAcfBlock(
    name: 'hero',
    title: 'Hero',
    category: 'layout',
)]
final readonly class HeroBlock implements AcfBlockInterface
{
    public function __construct(
        private ViewEngineInterface $view,
    ) {}

    public static function fields(): FieldsBuilder
    {
        return (new FieldsBuilder('hero'))
            ->addText('title')
            ->addWysiwyg('content');
    }

    public function compose(array $block, array $fields): array
    {
        return [
            'title' => $fields['title'] ?? '',
            'content' => $fields['content'] ?? '',
        ];
    }

    public function render(array $context, bool $isPreview = false): string
    {
        return $this->view->render('blocks/hero', $context);
    }
}
```

**Key changes:**
- `data()` is split into `compose()` (prepare data) and `render()` (output HTML)
- Fields are passed to `compose()` directly — no more `get_field()` calls
- Constructor injection for services (ViewEngine, etc.)
- ACF fields are automatically transformed to Timber objects (configurable via `AcfConfig`)

## Step 6: Migrate Hooks

**Before (wp-toolkit):**

```php
<?php
// functions.php or scattered across multiple files

add_action('after_setup_theme', function () {
    add_theme_support('post-thumbnails');
    add_theme_support('title-tag');
    add_theme_support('html5', ['search-form', 'comment-form']);
});

add_filter('excerpt_length', fn () => 30);
add_filter('excerpt_more', fn () => '…');

// In another file...
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('theme-style', get_stylesheet_uri());
});
```

**After (foehn):**

```php
<?php
// app/Hooks/ThemeHooks.php

namespace App\Hooks;

use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Attributes\AsFilter;

final class ThemeHooks
{
    #[AsAction('after_setup_theme')]
    public function setupTheme(): void
    {
        add_theme_support('post-thumbnails');
        add_theme_support('title-tag');
        add_theme_support('html5', ['search-form', 'comment-form']);
    }

    #[AsFilter('excerpt_length')]
    public function excerptLength(): int
    {
        return 30;
    }

    #[AsFilter('excerpt_more')]
    public function excerptMore(): string
    {
        return '…';
    }
}
```

```php
<?php
// app/Hooks/AssetHooks.php

namespace App\Hooks;

use Studiometa\Foehn\Attributes\AsAction;

final class AssetHooks
{
    #[AsAction('wp_enqueue_scripts')]
    public function enqueueAssets(): void
    {
        wp_enqueue_style('theme-style', get_stylesheet_uri());
    }
}
```

**Key changes:**
- Hooks are organized in dedicated classes by concern
- Priority and accepted args can be set via attribute parameters: `#[AsAction('init', priority: 5)]`
- Hook classes support constructor injection

## Step 7: Migrate Menus

**Before (wp-toolkit):**

```php
add_action('after_setup_theme', function () {
    register_nav_menus([
        'primary' => 'Main Navigation',
        'footer'  => 'Footer Navigation',
    ]);
});

// And in Timber context:
add_filter('timber/context', function ($context) {
    $context['menu'] = Timber::get_menu('primary');
    return $context;
});
```

**After (foehn):**

```php
<?php
// app/Menus/PrimaryMenu.php

namespace App\Menus;

use Studiometa\Foehn\Attributes\AsMenu;

#[AsMenu(location: 'primary', description: 'Main Navigation')]
final class PrimaryMenu {}
```

```php
<?php
// app/Menus/FooterMenu.php

namespace App\Menus;

use Studiometa\Foehn\Attributes\AsMenu;

#[AsMenu(location: 'footer', description: 'Footer Navigation')]
final class FooterMenu {}
```

Menus are automatically registered and added to the Timber context under `{{ menus.primary }}` and `{{ menus.footer }}`.

## Step 8: Migrate Template Routing

**Before (wp-toolkit):**

```php
// In functions.php or a dedicated file
add_filter('template_include', function ($template) {
    if (is_singular('product')) {
        $product = Timber::get_post();
        $context = Timber::context();
        $context['product'] = $product;
        Timber::render('pages/single-product.twig', $context);
        return '';
    }
    return $template;
});
```

**After (foehn):**

```php
<?php
// app/Controllers/ProductController.php

namespace App\Controllers;

use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Timber\Timber;

#[AsTemplateController(templates: ['single-product'])]
final readonly class ProductController implements TemplateControllerInterface
{
    public function __construct(
        private ViewEngineInterface $view,
    ) {}

    public function handle(): ?string
    {
        $product = Timber::get_post();

        return $this->view->render('pages/single-product', [
            'product' => $product,
        ]);
    }
}
```

## Step 9: Migrate Context Providers

**Before (wp-toolkit):**

```php
add_filter('timber/context', function ($context) {
    $context['site_settings'] = get_fields('options');
    $context['current_year'] = date('Y');
    return $context;
});
```

**After (foehn):**

```php
<?php
// app/ContextProviders/GlobalContextProvider.php

namespace App\ContextProviders;

use Studiometa\Foehn\Attributes\AsContextProvider;
use Studiometa\Foehn\Contracts\ContextProviderInterface;

#[AsContextProvider(templates: ['*'])]
final class GlobalContextProvider implements ContextProviderInterface
{
    public function provide(array $context): array
    {
        $context['site_settings'] = function_exists('get_fields')
            ? get_fields('options')
            : [];
        $context['current_year'] = date('Y');

        return $context;
    }
}
```

Use `templates: ['*']` for global data, or scope to specific templates: `templates: ['pages/single-*']`.

## Step 10: Migrate REST Endpoints

**Before (wp-toolkit):**

```php
add_action('rest_api_init', function () {
    register_rest_route('theme/v1', '/products', [
        'methods'  => 'GET',
        'callback' => function (WP_REST_Request $request) {
            $posts = Timber::get_posts(['post_type' => 'product']);
            return array_map(fn($p) => ['id' => $p->ID, 'title' => $p->title], $posts);
        },
        'permission_callback' => '__return_true',
    ]);
});
```

**After (foehn):**

```php
<?php
// app/Rest/ProductsEndpoint.php

namespace App\Rest;

use Studiometa\Foehn\Attributes\AsRestRoute;
use Timber\Timber;
use WP_REST_Request;

final class ProductsEndpoint
{
    #[AsRestRoute(
        namespace: 'theme/v1',
        route: '/products',
        method: 'GET',
        permission: 'public',
    )]
    public function list(WP_REST_Request $request): array
    {
        $posts = Timber::get_posts(['post_type' => 'product']);

        return array_map(
            fn($p) => ['id' => $p->ID, 'title' => $p->title],
            $posts,
        );
    }
}
```

## Step 11: Migrate Repository Classes

wp-toolkit projects often use Repository classes for data access. These are no longer needed — use Timber directly.

**Before (wp-toolkit):**

```php
<?php

namespace App\Repositories;

class ProductRepository
{
    public function findAll(): array
    {
        return Timber::get_posts(['post_type' => 'product', 'posts_per_page' => -1]);
    }

    public function findByCategory(string $category): array
    {
        return Timber::get_posts([
            'post_type' => 'product',
            'tax_query' => [['taxonomy' => 'product_category', 'field' => 'slug', 'terms' => $category]],
        ]);
    }
}
```

**After (foehn):**

If you still want to encapsulate queries, use a plain service class with DI:

```php
<?php
// app/Services/ProductService.php

namespace App\Services;

use App\Models\Product;
use Timber\Timber;

final class ProductService
{
    /** @return Product[] */
    public function findAll(): array
    {
        return Timber::get_posts(['post_type' => 'product', 'posts_per_page' => -1]);
    }

    /** @return Product[] */
    public function findByCategory(string $category): array
    {
        return Timber::get_posts([
            'post_type' => 'product',
            'tax_query' => [['taxonomy' => 'product_category', 'field' => 'slug', 'terms' => $category]],
        ]);
    }
}
```

The service is automatically injectable via Tempest's DI container:

```php
public function __construct(
    private readonly ProductService $products,
) {}
```

## Step 12: Update Directory Structure

**Before (wp-toolkit):**

```
theme/
├── app/
│   ├── Blocks/
│   │   └── HeroBlock.php
│   ├── PostTypes/
│   │   └── ProductPostType.php
│   ├── Repositories/
│   │   └── ProductRepository.php
│   └── Taxonomies/
│       └── ProductCategoryTaxonomy.php
├── views/
│   └── blocks/
│       └── hero.twig
└── functions.php
```

**After (foehn):**

```
theme/
├── app/
│   ├── Blocks/
│   │   └── Hero/
│   │       └── HeroBlock.php
│   ├── Controllers/
│   │   ├── HomeController.php
│   │   └── ProductController.php
│   ├── ContextProviders/
│   │   └── GlobalContextProvider.php
│   ├── Hooks/
│   │   ├── ThemeHooks.php
│   │   └── AssetHooks.php
│   ├── Models/
│   │   └── Product.php
│   ├── Rest/
│   │   └── ProductsEndpoint.php
│   ├── Services/
│   │   └── ProductService.php
│   ├── Taxonomies/
│   │   └── ProductCategory.php
│   └── foehn.config.php
├── templates/
│   └── blocks/
│       └── hero.twig
└── functions.php
```

## Common Pitfalls

### 1. Taxonomy class must extend Timber\Term

```php
// ❌ Won't work
#[AsTaxonomy(name: 'genre', singular: 'Genre', plural: 'Genres')]
final class Genre {}

// ✅ Correct
#[AsTaxonomy(name: 'genre', singular: 'Genre', plural: 'Genres')]
final class Genre extends \Timber\Term {}
```

### 2. Post type class must extend Timber\Post

```php
// ❌ Won't work
#[AsPostType(name: 'product', singular: 'Product', plural: 'Products')]
final class Product {}

// ✅ Correct
#[AsPostType(name: 'product', singular: 'Product', plural: 'Products')]
final class Product extends \Timber\Post {}
```

### 3. ACF block fields method must be static

```php
// ❌ Won't work
public function fields(): FieldsBuilder { ... }

// ✅ Correct
public static function fields(): FieldsBuilder { ... }
```

### 4. Hook methods must be public

```php
// ❌ Won't be discovered
#[AsAction('init')]
private function onInit(): void { ... }

// ✅ Correct
#[AsAction('init')]
public function onInit(): void { ... }
```

### 5. Abstract classes and traits are skipped

Discovery only inspects concrete classes. If you have base classes with attributes, the attributes on concrete subclasses will be discovered, but the base class itself is skipped.

### 6. Config files must return an instance

```php
// ❌ Won't work
return [
    'templatesDir' => ['views'],
];

// ✅ Correct
return new TimberConfig(
    templatesDir: ['views'],
);
```

### 7. Don't mix old and new registration

If you keep manual `register_post_type()` calls alongside `#[AsPostType]`, you'll get duplicate registrations. Remove the old code when migrating.

### 8. Timber classmap is automatic

With wp-toolkit, you might have manually configured Timber's classmap:

```php
// ❌ No longer needed
add_filter('timber/post/classmap', function ($map) {
    $map['product'] = Product::class;
    return $map;
});
```

Føhn registers the classmap automatically when using `#[AsPostType]` or `#[AsTimberModel]`.

## Migration Checklist

### Phase 1: Setup
- [ ] Install `studiometa/foehn`
- [ ] Update `functions.php` to use `Kernel::boot()`
- [ ] Create `app/foehn.config.php` if needed
- [ ] Verify autoloading works

### Phase 2: Content Types
- [ ] Migrate post types to `#[AsPostType]` on `Timber\Post` subclasses
- [ ] Migrate taxonomies to `#[AsTaxonomy]` on `Timber\Term` subclasses
- [ ] Remove old PostType/Taxonomy Manager classes
- [ ] Remove manual Timber classmap filters

### Phase 3: Blocks
- [ ] Migrate ACF blocks to `#[AsAcfBlock]` + `AcfBlockInterface`
- [ ] Update `data()` → `compose()` + `render()`
- [ ] Remove `get_field()` calls (fields are passed to `compose()`)
- [ ] Verify block templates still render correctly

### Phase 4: Hooks & Features
- [ ] Consolidate scattered hooks into hook classes
- [ ] Migrate menus to `#[AsMenu]`
- [ ] Migrate image sizes to `#[AsImageSize]` (if applicable)
- [ ] Migrate shortcodes to `#[AsShortcode]` (if applicable)

### Phase 5: Views & Templates
- [ ] Migrate `timber/context` filters to `#[AsContextProvider]`
- [ ] Migrate template routing to `#[AsTemplateController]`
- [ ] Update Twig templates for new context variables (e.g. `{{ menus.primary }}`)

### Phase 6: API & Services
- [ ] Migrate REST endpoints to `#[AsRestRoute]`
- [ ] Convert Repository classes to Service classes (or remove them)
- [ ] Use constructor injection instead of service locators

### Phase 7: Cleanup
- [ ] Remove `studiometa/wp-toolkit` dependency
- [ ] Remove unused Manager base classes
- [ ] Remove manual registration code from `functions.php`
- [ ] Run tests
- [ ] Verify all functionality in browser

## Need Help?

If you encounter issues during migration:

1. Check the [Guide](/guide/getting-started) for detailed documentation
2. Review the [API Reference](/api/) for attribute parameters
3. See [Theme Conventions](/guide/theme-conventions) for directory structure
4. See [Configuration](/guide/configuration) for config file setup
5. Open an issue on [GitHub](https://github.com/studiometa/foehn-framework/issues)
