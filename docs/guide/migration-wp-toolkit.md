# Migration from wp-toolkit

This guide helps you migrate from `studiometa/wp-toolkit` to `studiometa/wp-tempest`.

## Overview

WP Tempest replaces wp-toolkit's Manager pattern with attribute-based auto-discovery. The main changes:

| wp-toolkit             | wp-tempest                      |
| ---------------------- | ------------------------------- |
| `Manager` classes      | PHP 8 attributes                |
| Manual registration    | Auto-discovery                  |
| `ThemeManager::init()` | `Kernel::boot()`                |
| `PostTypeManager`      | `#[AsPostType]`                 |
| `TaxonomyManager`      | `#[AsTaxonomy]`                 |
| `BlockManager`         | `#[AsAcfBlock]` or `#[AsBlock]` |
| `ManagerInterface`     | Specific interfaces per feature |

## Step 1: Install wp-tempest

```bash
composer require studiometa/wp-tempest
composer remove studiometa/wp-toolkit
```

## Step 2: Update Theme Bootstrap

**Before (wp-toolkit):**

```php
<?php
// functions.php

use Studiometa\WPToolkit\Managers\ThemeManager;

$theme = new ThemeManager();
$theme->init();
```

**After (wp-tempest):**

```php
<?php
// functions.php

use Studiometa\WPTempest\Kernel;

Kernel::boot(__DIR__ . '/app');
```

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

**After (wp-tempest):**

```php
<?php
// app/Models/Product.php

namespace App\Models;

use Studiometa\WPTempest\Attributes\AsPostType;
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
    // Custom methods can be added here
    public function price(): ?float
    {
        return $this->meta('price') ? (float) $this->meta('price') : null;
    }
}
```

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

**After (wp-tempest):**

```php
<?php
// app/Models/ProductCategory.php

namespace App\Models;

use Studiometa\WPTempest\Attributes\AsTaxonomy;

#[AsTaxonomy(
    name: 'product_category',
    postTypes: ['product'],
    singular: 'Category',
    plural: 'Categories',
    hierarchical: true,
    showInRest: true,
)]
final class ProductCategory {}
```

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

**After (wp-tempest):**

```php
<?php
// app/Blocks/Hero/HeroBlock.php

namespace App\Blocks\Hero;

use Studiometa\WPTempest\Attributes\AsAcfBlock;
use Studiometa\WPTempest\Contracts\AcfBlockInterface;
use Studiometa\WPTempest\Contracts\ViewEngineInterface;
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

## Step 6: Migrate Hooks

**Before (wp-toolkit):**

```php
<?php
// functions.php or scattered across files

add_action('after_setup_theme', function () {
    add_theme_support('post-thumbnails');
});

add_filter('excerpt_length', fn () => 30);
```

**After (wp-tempest):**

```php
<?php
// app/Hooks/ThemeHooks.php

namespace App\Hooks;

use Studiometa\WPTempest\Attributes\AsAction;
use Studiometa\WPTempest\Attributes\AsFilter;

final class ThemeHooks
{
    #[AsAction('after_setup_theme')]
    public function setupTheme(): void
    {
        add_theme_support('post-thumbnails');
    }

    #[AsFilter('excerpt_length')]
    public function excerptLength(): int
    {
        return 30;
    }
}
```

## Step 7: Update Directory Structure

**Before (wp-toolkit):**

```
theme/
├── app/
│   ├── Blocks/
│   │   └── HeroBlock.php
│   ├── PostTypes/
│   │   └── ProductPostType.php
│   └── Taxonomies/
│       └── ProductCategoryTaxonomy.php
└── views/
    └── blocks/
        └── hero.twig
```

**After (wp-tempest):**

```
theme/
├── app/
│   ├── Blocks/
│   │   └── Hero/
│   │       └── HeroBlock.php
│   ├── Hooks/
│   │   └── ThemeHooks.php
│   ├── Models/
│   │   ├── Product.php
│   │   └── ProductCategory.php
│   └── Views/
│       ├── Composers/
│       └── Controllers/
├── views/
│   └── blocks/
│       └── hero.twig
└── functions.php
```

## Key Differences

### 1. No More Managers

wp-toolkit's Manager pattern is replaced by attributes. No need to:

- Extend base classes
- Implement `run()` methods
- Manually register with WordPress

### 2. Automatic Discovery

wp-tempest automatically discovers and registers:

- Post types
- Taxonomies
- Hooks
- Blocks
- View composers
- REST routes
- CLI commands

### 3. Dependency Injection

wp-tempest uses Tempest's DI container:

```php
// Inject services in constructors
public function __construct(
    private readonly ViewEngineInterface $view,
    private readonly MyService $service,
) {}
```

### 4. Timber Integration

Post type classes extend `Timber\Post` directly and are auto-registered in Timber's classmap.

### 5. New Features

wp-tempest adds features not in wp-toolkit:

- Native Gutenberg blocks with Interactivity API
- Block patterns with Twig
- Template controllers
- View composers
- REST API attributes
- CLI commands

## Checklist

- [ ] Install wp-tempest, remove wp-toolkit
- [ ] Update `functions.php` bootstrap
- [ ] Migrate post types to `#[AsPostType]`
- [ ] Migrate taxonomies to `#[AsTaxonomy]`
- [ ] Migrate ACF blocks to `#[AsAcfBlock]`
- [ ] Consolidate hooks into hook classes
- [ ] Update namespace paths if needed
- [ ] Test all functionality
- [ ] Remove unused Manager base classes

## Need Help?

If you encounter issues during migration:

1. Check the [Guide](/guide/getting-started) for detailed documentation
2. Review the [API Reference](/api/) for attribute parameters
3. Open an issue on [GitHub](https://github.com/studiometa/wp-tempest/issues)
