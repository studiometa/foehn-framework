# WebpackManifest

Helper class for enqueuing scripts and styles from `@studiometa/webpack-config` manifests.

## Namespace

```php
Studiometa\Foehn\Assets\WebpackManifest
```

## Requirements

```bash
composer require studiometa/webpack-config
```

## Constructor

```php
public function __construct(
    string $manifestPath,
    string $distPath = 'dist/',
    ?string $baseUri = null,
    ?string $basePath = null,
)
```

### Parameters

| Parameter       | Type      | Default  | Description                                           |
| --------------- | --------- | -------- | ----------------------------------------------------- |
| `$manifestPath` | `string`  | required | Absolute path to the manifest file                    |
| `$distPath`     | `string`  | `dist/`  | Relative path to dist directory (used to prefix URLs) |
| `$baseUri`      | `?string` | `null`   | Base URI for assets (defaults to theme URI)           |
| `$basePath`     | `?string` | `null`   | Base path for assets (defaults to theme directory)    |

## Static Methods

### fromTheme()

Creates an instance from the parent theme directory.

```php
public static function fromTheme(
    string $manifestPath = '/dist/assets-manifest.json',
    string $distPath = 'dist/',
): self
```

**Example:**

```php
$manifest = WebpackManifest::fromTheme();
$manifest = WebpackManifest::fromTheme('/build/manifest.json', 'build/');
```

### fromChildTheme()

Creates an instance from the child theme directory.

```php
public static function fromChildTheme(
    string $manifestPath = '/dist/assets-manifest.json',
    string $distPath = 'dist/',
): self
```

**Example:**

```php
$manifest = WebpackManifest::fromChildTheme();
```

## Methods

### enqueueEntry()

Enqueue all assets (CSS and JS) from a manifest entry.

```php
public function enqueueEntry(
    string $entry,
    string $prefix = 'theme',
    bool $inFooter = false,
    array $deps = [],
    string $media = 'all',
): self
```

**Parameters:**

| Parameter   | Type       | Default  | Description                     |
| ----------- | ---------- | -------- | ------------------------------- |
| `$entry`    | `string`   | required | Entry name (e.g., `css/app`)    |
| `$prefix`   | `string`   | `theme`  | Handle prefix for wp*enqueue*\* |
| `$inFooter` | `bool`     | `false`  | Load scripts in footer          |
| `$deps`     | `string[]` | `[]`     | Dependencies for scripts/styles |
| `$media`    | `string`   | `all`    | Media attribute for styles      |

**Returns:** `self` (fluent interface)

**Example:**

```php
$manifest->enqueueEntry('css/app', prefix: 'theme', media: 'screen');
$manifest->enqueueEntry('js/app', prefix: 'theme', inFooter: true, deps: ['jquery']);
```

### enqueueEntries()

Enqueue multiple entries at once.

```php
public function enqueueEntries(
    array $entries,
    string $prefix = 'theme',
    bool $inFooter = false,
): self
```

**Parameters:**

| Parameter   | Type       | Default  | Description                     |
| ----------- | ---------- | -------- | ------------------------------- |
| `$entries`  | `string[]` | required | Entry names to enqueue          |
| `$prefix`   | `string`   | `theme`  | Handle prefix for wp*enqueue*\* |
| `$inFooter` | `bool`     | `false`  | Load scripts in footer          |

**Returns:** `self` (fluent interface)

**Example:**

```php
$manifest->enqueueEntries(['css/app', 'js/app'], prefix: 'theme', inFooter: true);
```

### exists()

Check if the manifest was loaded successfully.

```php
public function exists(): bool
```

**Returns:** `true` if manifest file was found and loaded, `false` otherwise.

**Example:**

```php
$manifest = WebpackManifest::fromTheme();

if (!$manifest->exists()) {
    // Manifest not found, use fallback
}
```

### getManifest()

Get the underlying `Studiometa\WebpackConfig\Manifest` instance.

```php
public function getManifest(): ?Manifest
```

**Returns:** The `Manifest` instance, or `null` if file was not found.

**Example:**

```php
$webpackManifest = $manifest->getManifest();

if ($webpackManifest !== null) {
    $entry = $webpackManifest->entry('js/app');
}
```

## Complete Example

```php
<?php

namespace App\Hooks;

use Studiometa\Foehn\Assets\WebpackManifest;
use Studiometa\Foehn\Attributes\AsAction;

final class AssetHooks
{
    #[AsAction('wp_enqueue_scripts')]
    public function enqueueFrontendAssets(): void
    {
        $manifest = WebpackManifest::fromTheme();

        if (!$manifest->exists()) {
            return;
        }

        $manifest
            ->enqueueEntry('css/app', prefix: 'theme')
            ->enqueueEntry('js/app', prefix: 'theme', inFooter: true);
    }

    #[AsAction('admin_enqueue_scripts')]
    public function enqueueAdminAssets(): void
    {
        WebpackManifest::fromTheme()
            ->enqueueEntry('css/admin/admin', prefix: 'theme-admin');
    }

    #[AsAction('enqueue_block_editor_assets')]
    public function enqueueEditorAssets(): void
    {
        WebpackManifest::fromTheme()
            ->enqueueEntry('css/admin/editor-style', prefix: 'theme-editor');
    }
}
```

## See Also

- [Assets Guide](/guide/assets)
- [Hooks Guide](/guide/hooks)
