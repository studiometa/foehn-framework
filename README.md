# 🍃 Foehn

A modern WordPress framework powered by [Tempest](https://github.com/tempestphp/tempest-framework), featuring attribute-based auto-discovery for hooks, post types, blocks, and more.

[![Latest Version](https://img.shields.io/github/v/release/studiometa/foehn)](https://github.com/studiometa/foehn/releases)
[![PHP Version](https://img.shields.io/badge/php-%5E8.4-blue)](https://php.net)
[![Tests](https://github.com/studiometa/foehn/actions/workflows/ci.yml/badge.svg)](https://github.com/studiometa/foehn/actions/workflows/ci.yml)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

> [!WARNING]
> **AI-Generated Project** — This project was primarily built by AI coding agents (Claude). While functional and tested, it may contain bugs, security issues, or unexpected behavior. Use at your own risk, especially in production environments.

## Features

- 🚀 **Zero configuration** - Auto-discovery of components via PHP 8 attributes
- 🎯 **Modern DX** - Type-safe, IDE-friendly, testable
- 🔌 **WordPress native** - Works with Timber, ACF, and Gutenberg blocks
- ⚡ **Minimal boilerplate** - One line to boot your theme

## Requirements

- PHP 8.4+
- WordPress 6.4+
- Composer

## Installation

```bash
composer require studiometa/foehn
```

## Quick Start

### 1. Boot the kernel

```php
<?php
// functions.php

use Studiometa\Foehn\Kernel;

Kernel::boot(__DIR__ . '/app');
```

### 2. Create a post type

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

use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Attributes\AsFilter;

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

use Studiometa\Foehn\Attributes\AsAcfBlock;
use Studiometa\Foehn\Contracts\AcfBlockInterface;
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
| `#[AsCliCommand]`         | Register a WP-CLI command         |
| `#[AsTimberModel]`        | Register a Timber class map       |

## Documentation

📖 **[Full Documentation](https://studiometa.github.io/foehn/)**

- [Getting Started](https://studiometa.github.io/foehn/guide/getting-started)
- [Installation](https://studiometa.github.io/foehn/guide/installation)
- [Security Guide](https://studiometa.github.io/foehn/guide/security)
- [API Reference](https://studiometa.github.io/foehn/api/)

## Contributing

Contributions are welcome! Please read our contributing guidelines before submitting a PR.

## License

MIT License - see [LICENSE](LICENSE) for details.

## Credits

- [Tempest Framework](https://github.com/tempestphp/tempest-framework) by Brent Roose
- [Timber](https://github.com/timber/timber) by Upstatement
- Inspired by [Acorn](https://github.com/roots/acorn) by Roots
