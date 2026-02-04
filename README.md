# WP Tempest

A modern WordPress framework powered by [Tempest](https://github.com/tempestphp/tempest-framework), featuring attribute-based auto-discovery for hooks, post types, blocks, and more.

[![PHP Version](https://img.shields.io/badge/php-%5E8.4-blue)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

## Features

- 🚀 **Zero configuration** - Auto-discovery of components via PHP 8 attributes
- 🎯 **Modern DX** - Type-safe, IDE-friendly, testable
- 🔌 **WordPress native** - Works with Timber, ACF, Gutenberg, and FSE
- ⚡ **Minimal boilerplate** - One line to boot your theme

## Requirements

- PHP 8.4+
- WordPress 6.4+
- Composer

## Installation

```bash
composer require studiometa/wp-tempest
```

## Quick Start

### 1. Boot the kernel

```php
<?php
// functions.php

use Studiometa\WPTempest\Kernel;

Kernel::boot(__DIR__ . '/app');
```

### 2. Create a post type

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
)]
final class Product extends Post
{
    public function price(): ?float
    {
        return $this->meta('price') ? (float) $this->meta('price') : null;
    }
}
```

### 3. Register hooks

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
        add_theme_support('title-tag');
    }

    #[AsFilter('excerpt_length')]
    public function excerptLength(): int
    {
        return 30;
    }
}
```

### 4. Create an ACF block

```php
<?php
// app/Blocks/Hero/HeroBlock.php

namespace App\Blocks\Hero;

use Studiometa\WPTempest\Attributes\AsAcfBlock;
use Studiometa\WPTempest\Contracts\AcfBlockInterface;
use StoutLogic\AcfBuilder\FieldsBuilder;

#[AsAcfBlock(
    name: 'hero',
    title: 'Hero Banner',
    category: 'layout',
    icon: 'cover-image',
)]
final readonly class HeroBlock implements AcfBlockInterface
{
    public function __construct(
        private ViewEngineInterface $view,
    ) {}

    public static function fields(): FieldsBuilder
    {
        return (new FieldsBuilder('hero'))
            ->addWysiwyg('content')
            ->addImage('background');
    }

    public function compose(array $block, array $fields): array
    {
        return [
            'content' => $fields['content'] ?? '',
            'background' => $fields['background'] ?? null,
        ];
    }

    public function render(array $context): string
    {
        return $this->view->render('blocks/hero', $context);
    }
}
```

## Available Attributes

| Attribute                 | Description                       |
| ------------------------- | --------------------------------- |
| `#[AsAction]`             | Register a WordPress action hook  |
| `#[AsFilter]`             | Register a WordPress filter hook  |
| `#[AsPostType]`           | Register a custom post type       |
| `#[AsTaxonomy]`           | Register a custom taxonomy        |
| `#[AsBlock]`              | Register a native Gutenberg block |
| `#[AsAcfBlock]`           | Register an ACF block             |
| `#[AsBlockPattern]`       | Register a block pattern          |
| `#[AsViewComposer]`       | Add data to specific views        |
| `#[AsTemplateController]` | Handle template rendering         |
| `#[AsShortcode]`          | Register a shortcode              |
| `#[AsRestRoute]`          | Register a REST API endpoint      |

## Documentation

- [Architecture](.planning/architecture.md)
- [Theme Example](.planning/theme_example.md)
- [Views & Interactivity](.planning/architecture_views_update.md)

## Contributing

Contributions are welcome! Please read our contributing guidelines before submitting a PR.

## License

MIT License - see [LICENSE](LICENSE) for details.

## Credits

- [Tempest Framework](https://github.com/tempestphp/tempest-framework) by Brent Roose
- [Timber](https://github.com/timber/timber) by Upstatement
- Inspired by [Acorn](https://github.com/roots/acorn) by Roots
