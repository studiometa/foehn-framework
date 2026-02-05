# Assets

Foehn provides a helper class for enqueuing scripts and styles from [`@studiometa/webpack-config`](https://github.com/studiometa/webpack-config) manifests.

## Installation

The WebpackManifest helper requires the PHP companion package:

```bash
composer require studiometa/webpack-config
```

## Basic Usage

Use the `WebpackManifest` class with `#[AsAction]` to enqueue your theme assets:

```php
<?php

namespace App\Hooks;

use Studiometa\Foehn\Assets\WebpackManifest;
use Studiometa\Foehn\Attributes\AsAction;

final class AssetHooks
{
    #[AsAction('wp_enqueue_scripts')]
    public function enqueueAssets(): void
    {
        WebpackManifest::fromTheme()
            ->enqueueEntry('css/app', prefix: 'theme')
            ->enqueueEntry('js/app', prefix: 'theme', inFooter: true);
    }
}
```

This will:

1. Load the manifest from `{theme}/dist/assets-manifest.json`
2. Enqueue all CSS files from the `css/app` entry
3. Enqueue all JS files from the `js/app` entry (in the footer)
4. Add content-based version hashes for cache busting

## Factory Methods

### fromTheme()

Creates a manifest instance from the parent theme directory:

```php
// Default: /dist/assets-manifest.json
WebpackManifest::fromTheme();

// Custom path
WebpackManifest::fromTheme('/build/manifest.json', 'build/');
```

### fromChildTheme()

Creates a manifest instance from the child theme directory:

```php
WebpackManifest::fromChildTheme();
```

### Constructor

For full control, use the constructor directly:

```php
$manifest = new WebpackManifest(
    manifestPath: get_template_directory() . '/dist/assets-manifest.json',
    distPath: 'dist/',
    baseUri: get_template_directory_uri() . '/',  // Optional
    basePath: get_template_directory() . '/',     // Optional
);
```

## Enqueueing Assets

### enqueueEntry()

Enqueue all assets from a single entry:

```php
$manifest->enqueueEntry(
    entry: 'css/app',        // Entry name in manifest
    prefix: 'theme',         // Handle prefix (e.g., 'theme-app-css')
    inFooter: false,         // Load scripts in footer
    deps: ['jquery'],        // Dependencies
    media: 'all',            // Media attribute for styles
);
```

### enqueueEntries()

Enqueue multiple entries at once:

```php
$manifest->enqueueEntries(
    entries: ['css/app', 'js/app'],
    prefix: 'theme',
    inFooter: true,
);
```

## Fluent Interface

All methods return `$this` for chaining:

```php
WebpackManifest::fromTheme()
    ->enqueueEntry('css/app', prefix: 'theme')
    ->enqueueEntry('js/app', prefix: 'theme', inFooter: true)
    ->enqueueEntry('css/admin/editor-style', prefix: 'theme-editor');
```

## Conditional Loading

Since you control when to call `enqueueEntry()`, conditional loading is straightforward:

```php
#[AsAction('wp_enqueue_scripts')]
public function enqueueAssets(): void
{
    $manifest = WebpackManifest::fromTheme();

    // Always load base styles
    $manifest->enqueueEntry('css/app', prefix: 'theme');

    // Load JS only on frontend
    if (!is_admin()) {
        $manifest->enqueueEntry('js/app', prefix: 'theme', inFooter: true);
    }

    // Load specific styles for single posts
    if (is_singular('post')) {
        $manifest->enqueueEntry('css/single', prefix: 'theme');
    }
}

#[AsAction('admin_enqueue_scripts')]
public function enqueueAdminAssets(): void
{
    WebpackManifest::fromTheme()
        ->enqueueEntry('css/admin/admin', prefix: 'theme-admin');
}

#[AsAction('login_enqueue_scripts')]
public function enqueueLoginAssets(): void
{
    WebpackManifest::fromTheme()
        ->enqueueEntry('css/admin/login-style', prefix: 'theme-login');
}
```

## Graceful Degradation

The helper fails gracefully when the manifest file is not found (e.g., during development before the first build):

```php
$manifest = WebpackManifest::fromTheme();

// Check if manifest was loaded
if (!$manifest->exists()) {
    // Fallback to unversioned assets
    wp_enqueue_style('theme-style', get_stylesheet_uri());
    return;
}

$manifest->enqueueEntry('css/app', prefix: 'theme');
```

## Advanced: Accessing the Underlying Manifest

For advanced use cases, access the underlying `Studiometa\WebpackConfig\Manifest` instance:

```php
$manifest = WebpackManifest::fromTheme();
$webpackManifest = $manifest->getManifest();

if ($webpackManifest !== null) {
    // Access entry data directly
    $entry = $webpackManifest->entry('js/app');

    // Get all scripts with their attributes
    foreach ($entry->scripts as $handle => $script) {
        echo $script->getAttribute('src');
    }
}
```

## See Also

- [Hooks Guide](/guide/hooks) - Learn about `#[AsAction]`
- [@studiometa/webpack-config](https://github.com/studiometa/webpack-config) - The build tool
