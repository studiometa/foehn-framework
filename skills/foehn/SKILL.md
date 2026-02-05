---
name: foehn
description: Build WordPress themes with studiometa/foehn - attribute-based auto-discovery for hooks, post types, blocks, REST API, and CLI commands with Timber/Twig integration
---

# Foehn

Modern WordPress framework powered by Tempest, featuring attribute-based auto-discovery.

**Type**: Composer Package

## Installation

```bash
composer require studiometa/foehn
```

## Setup (functions.php)

```php
<?php

use Studiometa\Foehn\Kernel;

require_once __DIR__ . '/vendor/autoload.php';

Kernel::boot(__DIR__ . '/app');
```

## Directory Structure

```
theme/
├── app/
│   ├── Blocks/           # ACF and native blocks
│   ├── Console/          # CLI commands
│   ├── ContextProviders/ # Twig context providers
│   ├── Controllers/      # Template controllers
│   ├── Hooks/            # WordPress hooks
│   ├── Models/           # Post types and taxonomies
│   ├── Patterns/         # Block patterns
│   ├── Rest/             # REST API endpoints
│   ├── Services/         # Business logic
│   └── Shortcodes/       # Shortcodes
├── views/                # Twig templates
├── functions.php
└── style.css
```

## Core Attributes

### #[AsAction] / #[AsFilter]

Register WordPress hooks:

```php
<?php

namespace App\Hooks;

use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Attributes\AsFilter;

final class ThemeHooks
{
    #[AsAction('after_setup_theme')]
    public function setup(): void
    {
        add_theme_support('title-tag');
        add_theme_support('post-thumbnails');
    }

    #[AsAction('wp_enqueue_scripts')]
    public function enqueueAssets(): void
    {
        wp_enqueue_style('theme', get_stylesheet_uri());
    }

    #[AsFilter('excerpt_length')]
    public function excerptLength(): int
    {
        return 20;
    }
}
```

### #[AsPostType]

Register custom post types with Timber models:

```php
<?php

namespace App\Models;

use Studiometa\Foehn\Attributes\AsPostType;
use Timber\Post;

#[AsPostType(
    name: 'product',
    singular: 'Product',
    plural: 'Products',
    public: true,
    hasArchive: true,
    supports: ['title', 'editor', 'thumbnail'],
)]
final class Product extends Post
{
    public function price(): string
    {
        return $this->meta('price') ?? '0';
    }
}
```

### #[AsTaxonomy]

Register taxonomies:

```php
<?php

namespace App\Models;

use Studiometa\Foehn\Attributes\AsTaxonomy;
use Timber\Term;

#[AsTaxonomy(
    name: 'product_category',
    singular: 'Category',
    plural: 'Categories',
    postTypes: ['product'],
    hierarchical: true,
)]
final class ProductCategory extends Term {}
```

### #[AsAcfBlock]

Create ACF blocks:

```php
<?php

namespace App\Blocks\Hero;

use Studiometa\Foehn\Attributes\AsAcfBlock;
use Studiometa\Foehn\Contracts\AcfBlockInterface;
use StoutLogic\AcfBuilder\FieldsBuilder;

#[AsAcfBlock(
    name: 'hero',
    title: 'Hero Banner',
    description: 'A hero banner with title and CTA',
    icon: 'cover-image',
    category: 'theme',
)]
final readonly class HeroBlock implements AcfBlockInterface
{
    public function fields(): FieldsBuilder
    {
        $fields = new FieldsBuilder('hero');
        $fields
            ->addText('title', ['label' => 'Title'])
            ->addWysiwyg('content', ['label' => 'Content'])
            ->addLink('cta', ['label' => 'Call to Action']);

        return $fields;
    }

    public function compose(array $block, array $fields): array
    {
        return $fields;
    }
}
```

Template: `views/blocks/hero.twig`

### #[AsContextProvider]

Add global or template-specific context:

```php
<?php

namespace App\ContextProviders;

use Studiometa\Foehn\Attributes\AsContextProvider;
use Studiometa\Foehn\Contracts\ContextProviderInterface;

#[AsContextProvider('*')]
final class GlobalContextProvider implements ContextProviderInterface
{
    public function provide(): array
    {
        return [
            'menus' => [
                'primary' => \Timber\Timber::get_menu('primary'),
            ],
            'options' => get_fields('options'),
        ];
    }
}
```

### #[AsTemplateController]

Full control over template rendering:

```php
<?php

namespace App\Controllers;

use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;

#[AsTemplateController('single-product')]
final class SingleProductController implements TemplateControllerInterface
{
    public function __construct(
        private readonly ViewEngineInterface $view,
    ) {}

    public function handle(): ?string
    {
        $product = \Timber\Timber::get_post();

        return $this->view->render('single-product', [
            'product' => $product,
            'related' => $this->getRelated($product),
        ]);
    }

    private function getRelated($product): array
    {
        return \Timber\Timber::get_posts([
            'post_type' => 'product',
            'posts_per_page' => 3,
            'post__not_in' => [$product->ID],
        ]);
    }
}
```

### #[AsRestRoute]

Create REST API endpoints:

```php
<?php

namespace App\Rest;

use Studiometa\Foehn\Attributes\AsRestRoute;
use WP_REST_Request;
use WP_REST_Response;

final class ProductsEndpoint
{
    #[AsRestRoute('theme/v1', '/products', 'GET')]
    public function list(): WP_REST_Response
    {
        $products = \Timber\Timber::get_posts([
            'post_type' => 'product',
            'posts_per_page' => 10,
        ]);

        return new WP_REST_Response(
            array_map(fn($p) => [
                'id' => $p->ID,
                'title' => $p->title(),
                'price' => $p->meta('price'),
            ], $products)
        );
    }

    #[AsRestRoute('theme/v1', '/products/(?P<id>\d+)', 'GET')]
    public function show(WP_REST_Request $request): WP_REST_Response
    {
        $product = \Timber\Timber::get_post($request->get_param('id'));

        if (!$product) {
            return new WP_REST_Response(['error' => 'Not found'], 404);
        }

        return new WP_REST_Response([
            'id' => $product->ID,
            'title' => $product->title(),
            'content' => $product->content(),
        ]);
    }
}
```

### #[AsCliCommand]

Create WP-CLI commands:

```php
<?php

namespace App\Console;

use Studiometa\Foehn\Attributes\AsCliCommand;
use WP_CLI;

#[AsCliCommand(
    name: 'import:products',
    description: 'Import products from CSV',
)]
final class ImportProductsCommand
{
    public function __invoke(array $args, array $assocArgs): void
    {
        $file = $args[0] ?? null;
        $dryRun = isset($assocArgs['dry-run']);

        if (!$file || !file_exists($file)) {
            WP_CLI::error('File not found');
        }

        // Import logic...
        WP_CLI::success('Import complete');
    }
}
```

Usage: `wp tempest import:products products.csv --dry-run`

### #[AsShortcode]

Register shortcodes:

```php
<?php

namespace App\Shortcodes;

use Studiometa\Foehn\Attributes\AsShortcode;

#[AsShortcode('button')]
final class ButtonShortcode
{
    public function render(array $atts, ?string $content): string
    {
        $atts = shortcode_atts([
            'url' => '#',
            'style' => 'primary',
        ], $atts);

        return sprintf(
            '<a href="%s" class="btn btn--%s">%s</a>',
            esc_url($atts['url']),
            esc_attr($atts['style']),
            esc_html($content)
        );
    }
}
```

### #[AsMenu]

Register navigation menus:

```php
<?php

namespace App\Hooks;

use Studiometa\Foehn\Attributes\AsMenu;

#[AsMenu('primary', 'Primary Navigation')]
#[AsMenu('footer', 'Footer Navigation')]
final class MenuHooks {}
```

### #[AsImageSize]

Register image sizes:

```php
<?php

namespace App\Hooks;

use Studiometa\Foehn\Attributes\AsImageSize;

#[AsImageSize('card', 400, 300, true)]
#[AsImageSize('hero', 1920, 800, true)]
final class ImageHooks {}
```

## Twig Extensions

### html_classes / html_styles

From `studiometa/twig-toolkit`:

```twig
<div class="{{ html_classes('block', { 'block--active': is_active }) }}">
<div style="{{ html_styles({ color: text_color, 'font-size': font_size ~ 'px' }) }}">
```

### Block Pattern Markup

```twig
{{ wp_block_start('core/group', { layout: { type: 'constrained' } }) }}
  {{ wp_block('core/heading', { level: 2, content: 'Hello' }) }}
  {{ wp_block('core/paragraph', { content: 'World' }) }}
{{ wp_block_end() }}
```

### Video Embed

```twig
{{ video_url|video_embed }}
{{ video_url|video_embed({ autoplay: true, loop: true }) }}
{{ video_url|video_platform }} {# 'youtube' or 'vimeo' #}
```

## Helpers

### WebpackManifest

For `@studiometa/webpack-config` manifests:

```php
use Studiometa\Foehn\Assets\WebpackManifest;

$manifest = WebpackManifest::create(__DIR__ . '/dist/assets-manifest.json')
    ->withBaseUrl(get_template_directory_uri() . '/dist/');

// Enqueue assets
$manifest->enqueue('css/app.css');
$manifest->enqueue('js/app.js', deps: ['jquery']);

// Get URL
$url = $manifest->url('images/logo.svg');
```

### Cache

WordPress transients wrapper:

```php
use Studiometa\Foehn\Helpers\Cache;

$posts = Cache::remember('recent_posts', 3600, fn() => get_posts(['numberposts' => 10]));

Cache::set('key', $value, 3600);
Cache::get('key', 'default');
Cache::forget('key');
```

### Log

Debug logging (requires `WP_DEBUG_LOG`):

```php
use Studiometa\Foehn\Helpers\Log;

Log::info('User logged in', ['user_id' => 123]);
Log::error('Payment failed', ['order_id' => 456]);
```

## Built-in CLI Commands

```bash
# Scaffolding
wp tempest make:model Product --post-type
wp tempest make:acf-block Hero
wp tempest make:controller single-product
wp tempest make:hooks Seo

# Discovery cache
wp tempest discovery:warm
wp tempest discovery:clear
```

## Naming Conventions

| Directory               | Class Suffix       | Example                   |
| ----------------------- | ------------------ | ------------------------- |
| `app/Blocks/`           | `*Block`           | `HeroBlock`               |
| `app/Console/`          | `*Command`         | `ImportProductsCommand`   |
| `app/ContextProviders/` | `*ContextProvider` | `GlobalContextProvider`   |
| `app/Controllers/`      | `*Controller`      | `SingleProductController` |
| `app/Hooks/`            | `*Hooks`           | `ThemeHooks`              |
| `app/Models/`           | (singular)         | `Product`, `Category`     |
| `app/Patterns/`         | `*Pattern`         | `HeroPattern`             |
| `app/Rest/`             | `*Endpoint`        | `ProductsEndpoint`        |
| `app/Services/`         | `*Service`         | `CartService`             |
| `app/Shortcodes/`       | `*Shortcode`       | `ButtonShortcode`         |

## Documentation

Full documentation: https://studiometa.github.io/foehn/
