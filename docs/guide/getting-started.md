# Getting Started

WP Tempest is a modern WordPress framework powered by [Tempest Framework](https://github.com/tempestphp/tempest-framework). It brings attribute-based auto-discovery to WordPress development, eliminating boilerplate code for hooks, post types, blocks, and more.

## Why WP Tempest?

Traditional WordPress development requires manually registering every hook, post type, and block. WP Tempest changes this with PHP 8 attributes:

**Before (Traditional WordPress):**

```php
// functions.php - scattered registrations
add_action('init', function () {
    register_post_type('product', [
        'public' => true,
        'label' => 'Products',
        'has_archive' => true,
        // ... 20 more lines
    ]);
});

add_action('after_setup_theme', function () {
    add_theme_support('post-thumbnails');
});

add_filter('excerpt_length', fn () => 30);
```

**After (WP Tempest):**

```php
// app/Models/Product.php
#[AsPostType(name: 'product', singular: 'Product', plural: 'Products', hasArchive: true)]
final class Product extends Post {}

// app/Hooks/ThemeHooks.php
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

## Features

- **Hooks** — `#[AsAction]` and `#[AsFilter]` for WordPress hooks
- **Post Types** — `#[AsPostType]` with Timber integration
- **Taxonomies** — `#[AsTaxonomy]` with hierarchical support
- **Views** — `#[AsViewComposer]` and `#[AsTemplateController]`
- **ACF Blocks** — `#[AsAcfBlock]` with FieldsBuilder
- **Native Blocks** — `#[AsBlock]` with Interactivity API support
- **Block Patterns** — `#[AsBlockPattern]` with Twig templates
- **REST API** — `#[AsRestRoute]` for custom endpoints
- **Shortcodes** — `#[AsShortcode]` for shortcode handlers
- **CLI** — `#[AsCliCommand]` for WP-CLI commands

## Requirements

- PHP 8.4+
- WordPress 6.4+
- Composer

## Next Steps

1. [Install WP Tempest](./installation.md)
2. Learn about [Hooks](./hooks.md)
3. Create [Post Types](./post-types.md)
4. Build [ACF Blocks](./acf-blocks.md)
