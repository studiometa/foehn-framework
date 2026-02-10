# 🍃 Føhn

A modern WordPress framework powered by [Tempest Framework](https://tempestphp.com/), featuring attribute-based auto-discovery for hooks, post types, blocks, and more.

## Requirements

- PHP 8.4+
- WordPress 6.4+
- Composer

## Installation

```bash
composer require studiometa/foehn
```

## Quick Start

Bootstrap Føhn in your theme's `functions.php`:

```php
<?php

declare(strict_types=1);

use Studiometa\Foehn\Kernel;

Kernel::boot(__DIR__ . '/app');
```

That's it! Føhn will automatically discover and register all your classes in the `app/` directory.

## Features

### Hooks

Register WordPress actions and filters directly on your methods:

```php
<?php

use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Attributes\AsFilter;

final class ThemeHooks
{
    #[AsAction('after_setup_theme')]
    public function setup(): void
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

### Post Types

Define custom post types as classes with automatic Timber classmap integration:

```php
<?php

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
)]
final class Product extends Post
{
    public function price(): ?float
    {
        return $this->meta('price') ? (float) $this->meta('price') : null;
    }
}
```

### Taxonomies

```php
<?php

use Studiometa\Foehn\Attributes\AsTaxonomy;

#[AsTaxonomy(
    name: 'product_category',
    singular: 'Category',
    plural: 'Categories',
    postTypes: ['product'],
    hierarchical: true,
)]
final class ProductCategory {}
```

### ACF Blocks

Create ACF blocks with dependency injection:

```php
<?php

use Studiometa\Foehn\Attributes\AsAcfBlock;
use Studiometa\Foehn\Contracts\AcfBlockInterface;
use Studiometa\Foehn\Views\ViewEngineInterface;
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
            ->addImage('background')
            ->addWysiwyg('content')
            ->addLink('cta');
    }

    public function compose(array $block, array $fields): array
    {
        return [
            'background' => $fields['background'] ?? null,
            'content' => $fields['content'] ?? '',
            'cta' => $fields['cta'] ?? null,
        ];
    }

    public function render(array $context): string
    {
        return $this->view->render('blocks/hero', $context);
    }
}
```

### Native Gutenberg Blocks with Interactivity API

```php
<?php

use Studiometa\Foehn\Attributes\AsBlock;
use Studiometa\Foehn\Contracts\InteractiveBlockInterface;
use WP_Block;

#[AsBlock(
    name: 'theme/accordion',
    title: 'Accordion',
    category: 'widgets',
    interactivity: true,
)]
final readonly class AccordionBlock implements InteractiveBlockInterface
{
    public static function attributes(): array
    {
        return [
            'items' => ['type' => 'array', 'default' => []],
            'allowMultiple' => ['type' => 'boolean', 'default' => false],
        ];
    }

    public static function initialState(): array
    {
        return [];
    }

    public function initialContext(array $attributes): array
    {
        return [
            'openItems' => [],
            'allowMultiple' => $attributes['allowMultiple'] ?? false,
        ];
    }

    public function render(array $attributes, string $content, WP_Block $block): string
    {
        // ...
    }
}
```

### View Composers

Inject data into specific templates:

```php
<?php

use Studiometa\Foehn\Attributes\AsViewComposer;
use Studiometa\Foehn\Contracts\ViewComposerInterface;

#[AsViewComposer(['single', 'single-*'])]
final readonly class SingleComposer implements ViewComposerInterface
{
    public function compose(array $context): array
    {
        $post = $context['post'] ?? null;

        return array_merge($context, [
            'reading_time' => $this->calculateReadingTime($post->content()),
            'related_posts' => $this->getRelatedPosts($post),
        ]);
    }
}
```

### Template Controllers

Handle template rendering with full control:

```php
<?php

use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Views\ViewEngineInterface;
use Timber\Timber;

#[AsTemplateController('single', 'single-*')]
final readonly class SingleController
{
    public function __construct(
        private ViewEngineInterface $view,
    ) {}

    public function __invoke(): string
    {
        $context = Timber::context();

        return $this->view->renderFirst([
            "pages/single-{$context['post']->post_type}",
            'pages/single',
        ], $context);
    }
}
```

### Block Patterns

Register block patterns with Twig templates:

```php
<?php

use Studiometa\Foehn\Attributes\AsBlockPattern;
use Studiometa\Foehn\Contracts\BlockPatternInterface;

#[AsBlockPattern(
    name: 'theme/hero-full-width',
    title: 'Hero Full Width',
    categories: ['heroes'],
)]
final readonly class HeroFullWidth implements BlockPatternInterface
{
    public function compose(): array
    {
        return [
            'heading' => __('Welcome', 'theme'),
            'cta_text' => __('Learn more', 'theme'),
        ];
    }
}
```

### REST API Routes

```php
<?php

use Studiometa\Foehn\Attributes\AsRestRoute;
use WP_REST_Request;

final class ProductsApi
{
    #[AsRestRoute('theme/v1', '/products', methods: ['GET'])]
    public function index(WP_REST_Request $request): array
    {
        return ['products' => []];
    }

    #[AsRestRoute('theme/v1', '/products/(?P<id>\d+)', methods: ['GET'])]
    public function show(WP_REST_Request $request): array
    {
        return ['product' => []];
    }
}
```

### Shortcodes

```php
<?php

use Studiometa\Foehn\Attributes\AsShortcode;

final class ButtonShortcode
{
    #[AsShortcode('button')]
    public function render(array $atts, ?string $content = null): string
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

### CLI Commands

Føhn provides WP-CLI commands for scaffolding:

```bash
# Generate a new block
wp foehn make:block Hero --acf

# Generate a new post type
wp foehn make:post-type Product

# Generate a new taxonomy
wp foehn make:taxonomy ProductCategory --post-types=product

# Clear discovery cache
wp foehn discovery:clear

# Warm discovery cache
wp foehn discovery:cache
```

## Dependency Injection

All discovered classes support constructor injection via Tempest's container:

```php
<?php

use Studiometa\Foehn\Attributes\AsAction;

final readonly class NewsletterHooks
{
    public function __construct(
        private NewsletterService $newsletter,
        private LoggerInterface $logger,
    ) {}

    #[AsAction('user_register')]
    public function onUserRegister(int $userId): void
    {
        $user = get_user_by('id', $userId);
        $this->newsletter->subscribe($user->user_email);
        $this->logger->info('User subscribed to newsletter', ['user_id' => $userId]);
    }
}
```

## Configuration

Føhn can be configured in your theme:

```php
<?php
// config/foehn.php

return [
    'cache' => [
        'enabled' => wp_get_environment_type() === 'production',
        'path' => get_template_directory() . '/storage/cache',
    ],

    'views' => [
        'paths' => ['templates'],
    ],

    'blocks' => [
        'namespace' => 'theme',
    ],
];
```

## Documentation

For complete documentation, see the [main repository](https://github.com/studiometa/foehn-framework).

## License

MIT License. See [LICENSE](LICENSE) for details.
