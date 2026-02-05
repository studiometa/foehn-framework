# Migration to v0.2.0

This guide helps you migrate from Føhn v0.1.x to v0.2.0.

## Breaking Changes

### ViewComposer → ContextProvider

The ViewComposer system has been renamed to ContextProvider for clarity. This affects:

| v0.1.x                   | v0.2.0                      |
| ------------------------ | --------------------------- |
| `#[AsViewComposer]`      | `#[AsContextProvider]`      |
| `ViewComposerInterface`  | `ContextProviderInterface`  |
| `compose()` method       | `provide()` method          |
| `ViewComposerRegistry`   | `ContextProviderRegistry`   |
| `ViewComposerDiscovery`  | `ContextProviderDiscovery`  |
| `make:view-composer` CLI | `make:context-provider` CLI |
| `App\Views\Composers\`   | `App\ContextProviders\`     |

### Migration Steps

#### 1. Update Attribute Imports

**Before:**

```php
use Studiometa\Foehn\Attributes\AsViewComposer;
use Studiometa\Foehn\Contracts\ViewComposerInterface;
```

**After:**

```php
use Studiometa\Foehn\Attributes\AsContextProvider;
use Studiometa\Foehn\Contracts\ContextProviderInterface;
```

#### 2. Update Class Definitions

**Before:**

```php
#[AsViewComposer(templates: ['*'])]
final class GlobalComposer implements ViewComposerInterface
{
    public function compose(array $context): array
    {
        $context['site_name'] = get_bloginfo('name');
        return $context;
    }
}
```

**After:**

```php
#[AsContextProvider(templates: ['*'])]
final class GlobalContextProvider implements ContextProviderInterface
{
    public function provide(array $context): array
    {
        $context['site_name'] = get_bloginfo('name');
        return $context;
    }
}
```

#### 3. Move and Rename Files

Move your context providers from `app/Views/Composers/` to `app/ContextProviders/`:

```bash
# Create new directory
mkdir -p app/ContextProviders

# Move and rename files
mv app/Views/Composers/GlobalComposer.php app/ContextProviders/GlobalContextProvider.php
mv app/Views/Composers/NavigationComposer.php app/ContextProviders/NavigationContextProvider.php
# ... repeat for all composers
```

#### 4. Update Namespaces

Update the namespace in each file:

**Before:**

```php
namespace App\Views\Composers;
```

**After:**

```php
namespace App\ContextProviders;
```

#### 5. Update composer.json (if needed)

If you have explicit PSR-4 mappings, update them:

```json
{
  "autoload": {
    "psr-4": {
      "App\\": "app/"
    }
  }
}
```

Then run:

```bash
composer dump-autoload
```

#### 6. Update Mago Configuration (if used)

If you use Mago for convention enforcement, update your `mago.toml`:

**Before:**

```toml
[[guard.structural.rules]]
on            = "App\\Views\\Composers\\**"
target        = "class"
must-be-named = "*Composer"
must-implement = "Studiometa\\Foehn\\Contracts\\ViewComposerInterface"
```

**After:**

```toml
[[guard.structural.rules]]
on            = "App\\ContextProviders\\**"
target        = "class"
must-be-named = "*ContextProvider"
must-implement = "Studiometa\\Foehn\\Contracts\\ContextProviderInterface"
```

Or simply re-copy the bundled config:

```bash
cp vendor/studiometa/foehn/resources/mago-theme.toml mago.toml
```

## New Features

v0.2.0 also introduces several new features you can adopt:

### Navigation Menus

Register menus declaratively with `#[AsMenu]`:

```php
use Studiometa\Foehn\Attributes\AsMenu;

#[AsMenu(location: 'primary', description: 'Main Navigation')]
#[AsMenu(location: 'footer', description: 'Footer Links')]
final class NavigationMenus {}
```

See [Menus Guide](/guide/menus) for details.

### Image Sizes

Register custom image sizes with `#[AsImageSize]`:

```php
use Studiometa\Foehn\Attributes\AsImageSize;

#[AsImageSize(name: 'card', width: 400, height: 300, crop: true)]
#[AsImageSize(name: 'hero', width: 1920, height: 800, crop: true)]
final class ImageSizes {}
```

See [API: #[AsImageSize]](/api/as-image-size) for details.

### ACF Options Pages

Create options pages with `#[AsAcfOptionsPage]`:

```php
use Studiometa\Foehn\Attributes\AsAcfOptionsPage;
use Studiometa\Foehn\Contracts\AcfOptionsPageInterface;

#[AsAcfOptionsPage(
    pageTitle: 'Theme Settings',
    menuSlug: 'theme-settings',
)]
final class ThemeSettings implements AcfOptionsPageInterface
{
    public static function fields(): FieldsBuilder
    {
        return (new FieldsBuilder('theme_settings'))
            ->addText('site_name');
    }
}
```

See [ACF Options Pages Guide](/guide/acf-options-pages) for details.

### ACF Field Groups (Non-Block)

Register field groups for posts, pages, etc. with `#[AsAcfFieldGroup]`:

```php
use Studiometa\Foehn\Attributes\AsAcfFieldGroup;
use Studiometa\Foehn\Contracts\AcfFieldGroupInterface;

#[AsAcfFieldGroup(location: 'post_type == page')]
final class PageSettings implements AcfFieldGroupInterface
{
    public static function fields(): FieldsBuilder
    {
        return (new FieldsBuilder('page_settings'))
            ->addTrueFalse('show_sidebar');
    }
}
```

### DisableBlockStyles Hook

Dequeue Gutenberg block styles for themes not using blocks:

```php
use Studiometa\Foehn\Kernel;
use Studiometa\Foehn\Hooks\Cleanup\DisableBlockStyles;

Kernel::boot(__DIR__ . '/app', [
    'hooks' => [
        DisableBlockStyles::class,
    ],
]);
```

### Enhanced CLI Commands

The `--dry-run` flag is now available on scaffolding commands:

```bash
wp tempest make:context-provider Header --dry-run
wp tempest make:acf-block Hero --dry-run
```

### Bundled Mago Configuration

Copy the ready-to-use Mago config for theme conventions:

```bash
cp vendor/studiometa/foehn/resources/mago-theme.toml mago.toml
mago guard
```

## Search & Replace Cheatsheet

Quick sed commands for bulk migration:

```bash
# Update imports
find app -name "*.php" -exec sed -i '' 's/AsViewComposer/AsContextProvider/g' {} +
find app -name "*.php" -exec sed -i '' 's/ViewComposerInterface/ContextProviderInterface/g' {} +

# Update method names
find app -name "*.php" -exec sed -i '' 's/public function compose(/public function provide(/g' {} +

# Update namespaces (adjust paths as needed)
find app/Views/Composers -name "*.php" -exec sed -i '' 's/namespace App\\Views\\Composers/namespace App\\ContextProviders/g' {} +
```

## Troubleshooting

### Discovery Not Finding Context Providers

Ensure your namespace matches the directory structure in `composer.json`:

```json
{
  "autoload": {
    "psr-4": {
      "App\\": "app/"
    }
  }
}
```

Run `composer dump-autoload` after moving files.

### Method Not Found Error

If you see `provide() method not found`, you likely forgot to rename `compose()` to `provide()` in your class.

### Mago Guard Failures

If Mago reports violations after migration, ensure:

1. Classes are in `App\ContextProviders\` namespace
2. Class names end with `ContextProvider`
3. Classes implement `ContextProviderInterface`

## See Also

- [Context Providers Guide](/guide/context-providers)
- [Theme Conventions](/guide/theme-conventions)
- [Changelog](https://github.com/studiometa/foehn/blob/main/CHANGELOG.md)
