# Assets

Føhn provides two helpers for enqueuing scripts and styles, one per build tool.

| Build tool                                                                   | Helper            | Reads                       |
| ---------------------------------------------------------------------------- | ----------------- | --------------------------- |
| [Vite](https://vite.dev), through `@studiometa/foehn-vite-plugin`            | `ViteManifest`    | `dist/.vite/manifest.json`  |
| [`@studiometa/webpack-config`](https://github.com/studiometa/webpack-config) | `WebpackManifest` | `dist/assets-manifest.json` |

They are not interchangeable: the two tools emit different formats. Reach for `ViteManifest` on a new project — it is what the starter and the demo use.

## Vite

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

Entry names are the paths given to the plugin's `input` in `vite.config.js`, because those are the keys Vite writes into the manifest. They are relative to the Vite project root, which is usually the package rather than the theme — so `theme/assets/js/app.js`, not `assets/js/app.js`.

### What it handles for you

**The dev server.** While `npm run dev` runs, the plugin writes a `hot` file holding the server's URL. `ViteManifest` then loads the Vite client and the entries from that server instead of from the build, so hot module replacement works and a stale `dist/` cannot shadow your edits. Nothing in the theme has to branch on it.

**The CSS a script imported.** A Vite JavaScript chunk carries the stylesheets it imported in a `css` array, separate from its own `file`. Enqueue the script and miss that array and the page loads with no styles and no error anywhere — so `enqueue()` always registers both.

**The module type.** Vite emits ES modules, and a classic `<script>` tag will not parse one. `wp_script_add_data($handle, 'type', 'module')` looks like the way to say so and is not: `WP_Scripts` reads `strategy`, `before`, `after` and `data`, never `type`. `ViteManifest` rewrites the tag through `script_loader_tag`, and only for the handles it enqueued.

**A missing build.** No manifest and no hot file means nothing is enqueued, rather than a fatal error. `exists()` reports which case you are in, and `isDevServer()` whether the dev server is running.

### Where the build has to go

`dist/` must be **inside** the theme. Only the theme directory is served, so a build written beside it is never reachable from a browser:

```js
foehn({
  input: ["theme/assets/js/app.js", "theme/assets/css/app.css"],
  outDir: "theme/dist",
});
```

`fromTheme()` looks in `dist/` under the active theme; pass a different relative path if yours differs. `fromChildTheme()` does the same against the child theme.

### Autoloading components

`@studiometa/js-toolkit` can discover components from the markup instead of being told about each one, which is what both themes do:

```js
import { defineManifest, fromMetaGlob, registerManifests } from "@studiometa/js-toolkit";
import "@studiometa/ui/autoload";

const manifest = defineManifest({
  packageName: "my-theme",
  modules: fromMetaGlob(import.meta.glob("./components/*.js")),
});

registerManifests(manifest);
```

`import.meta.glob` hands Vite a lazy importer per file, `fromMetaGlob` normalises that into the shape the loader wants, and `registerManifests` schedules the start. The loader mounts whatever `[data-component]` it finds and fetches only those modules, so a component nobody uses on a page costs nothing but a manifest entry.

Adding a component is dropping a file into `components/` — nothing in `app.js` changes.

`@studiometa/ui/autoload` registers that package's own manifest as a side effect of the import, which is why it takes no arguments. `data-component="Modal"` then works with no import of `Modal` anywhere.

## Twig components

`studiometa/ui` also ships its components as Twig templates. Install it and opt the hook in:

```bash
composer require studiometa/ui
```

```php
// app/foehn.config.php
use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Hooks\StudiometaUi;

return new FoehnConfig(hooks: [StudiometaUi::class]);
```

That registers the `@ui` and `@svg` Twig namespaces on Timber's loader, which is the only way the components can be reached:

```twig
{% embed '@ui/Accordion/Accordion.twig' with { items: faq } %}
  {% block title %}{{ item.title }}{% endblock %}
  {% block content %}{{ item.content }}{% endblock %}
{% endembed %}
```

The markup arrives carrying `data-component="Accordion"`, so the Twig half and the JavaScript half meet without either importing the other.

It is a hook class rather than automatic registration because framework hook classes are opt-in by design — one registering itself because it happens to sit in a scanned package would let a `composer update` change what a site does.

Nothing breaks when the package is absent: the hook checks for it and returns the environment untouched. `studiometa/ui` is a `suggest`, so the framework itself gains no dependency.

## Webpack

### Installation

The WebpackManifest helper requires the PHP companion package:

```bash
composer require studiometa/webpack-config
```

### Basic Usage

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

### Factory Methods

#### fromTheme()

Creates a manifest instance from the parent theme directory:

```php
// Default: /dist/assets-manifest.json
WebpackManifest::fromTheme();

// Custom path
WebpackManifest::fromTheme('/build/manifest.json', 'build/');
```

#### fromChildTheme()

Creates a manifest instance from the child theme directory:

```php
WebpackManifest::fromChildTheme();
```

#### Constructor

For full control, use the constructor directly:

```php
$manifest = new WebpackManifest(
    manifestPath: get_template_directory() . '/dist/assets-manifest.json',
    distPath: 'dist/',
    baseUri: get_template_directory_uri() . '/',  // Optional
    basePath: get_template_directory() . '/',     // Optional
);
```

### Enqueueing Assets

#### enqueueEntry()

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

#### enqueueEntries()

Enqueue multiple entries at once:

```php
$manifest->enqueueEntries(
    entries: ['css/app', 'js/app'],
    prefix: 'theme',
    inFooter: true,
);
```

### Fluent Interface

All methods return `$this` for chaining:

```php
WebpackManifest::fromTheme()
    ->enqueueEntry('css/app', prefix: 'theme')
    ->enqueueEntry('js/app', prefix: 'theme', inFooter: true)
    ->enqueueEntry('css/admin/editor-style', prefix: 'theme-editor');
```

### Conditional Loading

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

### Graceful Degradation

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

### Advanced: Accessing the Underlying Manifest

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
