# ViteManifest

Helper class for enqueuing scripts and styles built by Vite with `@studiometa/foehn-vite-plugin`.

## Namespace

```php
Studiometa\Foehn\Assets\ViteManifest
```

## How it finds the assets

The Vite plugin writes one of two files in the build directory:

- `hot`, while `npm run dev` runs. It holds the URL of the dev server. `ViteManifest` then loads the Vite client and the entries from that server.
- `.vite/manifest.json`, after `npm run build`. It maps each entry to its built file.

When the `hot` file exists, `enqueue()` uses the dev server. When neither file exists, `enqueue()` does nothing.

## Constructor

```php
public function __construct(
    string $distPath,
    string $distUri,
    string $hotFile = 'hot',
)
```

### Parameters

| Parameter   | Type     | Default  | Description                                   |
| ----------- | -------- | -------- | --------------------------------------------- |
| `$distPath` | `string` | required | Absolute path to the build directory          |
| `$distUri`  | `string` | required | Public URI of the build directory             |
| `$hotFile`  | `string` | `hot`    | Name of the dev server file the plugin writes |

## Static Methods

### fromTheme()

Creates an instance from a directory of the parent theme.

```php
public static function fromTheme(string $distPath = 'dist', string $hotFile = 'hot'): self
```

`$distPath` is relative to the theme directory.

**Example:**

```php
$manifest = ViteManifest::fromTheme();
$manifest = ViteManifest::fromTheme('build');
```

### fromChildTheme()

Creates an instance from a directory of the child theme.

```php
public static function fromChildTheme(string $distPath = 'dist', string $hotFile = 'hot'): self
```

**Example:**

```php
$manifest = ViteManifest::fromChildTheme();
```

## Methods

### enqueue()

Enqueue one entry and the stylesheets it needs.

```php
public function enqueue(
    string $entry,
    string $handle,
    bool $inFooter = false,
    array $deps = [],
    string $media = 'all',
): self
```

| Parameter   | Type       | Default  | Description                               |
| ----------- | ---------- | -------- | ----------------------------------------- |
| `$entry`    | `string`   | required | Entry name, as it appears in the manifest |
| `$handle`   | `string`   | required | Handle to register the script under       |
| `$inFooter` | `bool`     | `false`  | Load the script in the footer             |
| `$deps`     | `string[]` | `[]`     | Dependencies                              |
| `$media`    | `string`   | `all`    | Media attribute of the stylesheets        |

The entry name is the path given to the plugin's `input` in `vite.config.js`, relative to the Vite project root. For example: `theme/assets/js/app.js`.

From a build, `enqueue()` registers:

- each stylesheet of the entry, as `{handle}-style`, then `{handle}-style-1`, `{handle}-style-2`, …
- the script of a JavaScript entry, as `{handle}`, with `type="module"`

An unknown entry enqueues nothing.

**Example:**

```php
ViteManifest::fromTheme()
    ->enqueue('theme/assets/css/app.css', handle: 'theme-styles')
    ->enqueue('theme/assets/js/app.js', handle: 'theme-app', inFooter: true);
```

### exists()

Check if there is a build or a dev server to enqueue from.

```php
public function exists(): bool
```

### isDevServer()

Check if the Vite dev server is running.

```php
public function isDevServer(): bool
```

### devServer()

Get the URL of the dev server.

```php
public function devServer(): ?string
```

Returns `null` when the dev server does not run.

### entries()

Get the entry points of the build.

```php
/** @return list<array{entry: string, file: string, css: list<string>}> */
public function entries(): array
```

Each item gives the entry name that `enqueue()` takes, its built file, and the stylesheets it imports. The list comes from the manifest, so it is empty when the project was never built, also when the dev server runs.

**Example:**

```php
foreach (ViteManifest::fromTheme()->entries() as $entry) {
    // $entry['entry'] → 'theme/assets/js/app.js'
    // $entry['file']  → 'assets/app-B2x9k1.js'
    // $entry['css']   → ['assets/app-D4f8a0.css']
}
```

## Complete Example

```php
<?php

namespace App\Hooks;

use Studiometa\Foehn\Assets\ViteManifest;
use Studiometa\Foehn\Attributes\AsAction;

final class AssetHooks
{
    #[AsAction('wp_enqueue_scripts')]
    public function enqueue(): void
    {
        ViteManifest::fromTheme()
            ->enqueue('theme/assets/css/app.css', handle: 'theme-styles')
            ->enqueue('theme/assets/js/app.js', handle: 'theme-app', inFooter: true);
    }
}
```

## See Also

- [Assets Guide](/guide/assets)
- [`WebpackManifest`](./webpack-manifest)
