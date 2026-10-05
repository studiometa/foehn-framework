---
name: foehn-vite-plugin
description: 'Configure the Vite build of a Føhn theme with @studiometa/foehn-vite-plugin (the `foehn()` plugin in vite.config.js and its `input`, `reload`, `outDir`, `themeDir` and `hotFile` options) and enqueue its output on the PHP side with `Studiometa\Foehn\Assets\ViteManifest` (`fromTheme()`, `fromChildTheme()`, `enqueue()`, `exists()`, `isDevServer()`). Load it when editing vite.config.js, adding a JS or CSS entry, wiring `npm run dev` / HMR or the `hot` file, debugging missing or unstyled assets, or touching an `AssetHooks` class in a theme built from studiometa/foehn-starter.'
---

# Føhn Vite plugin

`@studiometa/foehn-vite-plugin` builds the theme's JavaScript and CSS with Vite. `Studiometa\Foehn\Assets\ViteManifest` (in `studiometa/foehn`) puts that build on the page. The two halves meet through files on disk, never through imports. For hooks, `#[AsAction]` and the rest of the PHP side, load the `foehn` skill.

Full guide: https://studiometa.github.io/foehn-framework/guide/assets

## Mental model

The plugin writes one of two things. `ViteManifest` reads whichever one exists:

| Command         | Plugin writes                                 | `ViteManifest` then                                               |
| --------------- | --------------------------------------------- | ----------------------------------------------------------------- |
| `npm run build` | `<outDir>/.vite/manifest.json` (hashed files) | enqueues the hashed files, plus the CSS each JS chunk imported    |
| `npm run dev`   | a `hot` file holding the dev server URL       | enqueues `/@vite/client` and each entry from the dev server (HMR) |
| neither         | nothing                                       | enqueues nothing, no error                                        |

The hot file wins: when it exists and is not empty, the manifest is not read. The plugin removes the hot file when the dev server closes and at the end of a build.

The **entry name** is the key that joins the two halves. It is the path given to `input`, relative to the Vite project root (the directory that holds `vite.config.js`, usually the project root, not the theme). In the starter, that is `theme/assets/js/app.js`, not `assets/js/app.js`.

## Project layout (starter)

```
my-project/
├── vite.config.js
├── package.json          # "dev": "vite", "build": "vite build"
├── .ddev/config.yaml
└── theme/                # the only directory the web server serves
    ├── app/Hooks/AssetHooks.php
    ├── assets/
    │   ├── js/app.js
    │   └── css/app.css
    ├── templates/
    └── dist/             # build output, git-ignored
        └── .vite/manifest.json
```

## vite.config.js

This is the starter's configuration:

```js
import { defineConfig } from "vite";
import tailwindcss from "@tailwindcss/vite";
import foehn from "@studiometa/foehn-vite-plugin";

export default defineConfig({
    plugins: [
        foehn({
            input: ["theme/assets/js/app.js", "theme/assets/css/app.css"],
            reload: ["theme/templates/**/*.twig", "theme/app/**/*.php"],
            // Into the theme: only `theme/` is served, a build beside it is unreachable.
            outDir: "theme/dist",
        }),
        tailwindcss(),
    ],
});
```

The package has a default export and a named export (`import { foehn } from '@studiometa/foehn-vite-plugin'`). `foehn()` returns an array of two plugins (`foehn` and `foehn:reload`); put it in `plugins` as is. The types `FoehnPluginOptions` and `ResolvedFoehnPluginOptions` are exported.

### Options

| Option     | Type                 | Default                   | What it does                                                                                |
| ---------- | -------------------- | ------------------------- | ------------------------------------------------------------------------------------------- |
| `input`    | `string \| string[]` | required                  | Entry files or globs. Becomes `build.rollupOptions.input`.                                  |
| `reload`   | `string \| string[]` | `["templates/**/*.twig"]` | Globs watched by the dev server. A change sends a full page reload.                         |
| `outDir`   | `string`             | `"dist"`                  | Build output, resolved against `themeDir`. Becomes `build.outDir`.                          |
| `themeDir` | `string`             | `process.cwd()`           | Base directory for `input` globs, `reload`, `outDir`, the hot file and the `.ddev/` lookup. |
| `hotFile`  | `string`             | `"hot"`                   | Hot file path, resolved against `themeDir`.                                                 |

The plugin also sets `build.manifest: true`. Do not set `build.outDir`, `build.manifest` or `build.rollupOptions.input` yourself; the plugin owns them.

`themeDir` does not change the Vite root. Manifest keys stay relative to the Vite root, but `input` paths are relative to `themeDir`. When you set `themeDir` to a subdirectory, the entry name in PHP is the path from the Vite root, not the string in `input`.

### Globs in `input`

An `input` item that contains `*`, `?` or `{` is expanded with fast-glob against `themeDir`:

```js
foehn({
    input: ["theme/assets/js/*.js", "theme/assets/css/app.css"],
    outDir: "theme/dist",
});
```

Each file becomes its own entry. Enqueue each one by its own path (for example `theme/assets/js/editor.js`). Do not glob a directory of modules that `app.js` imports (such as `components/`): each match then becomes a separate entry.

## PHP side: AssetHooks

The starter's hook class:

```php
<?php

declare(strict_types=1);

namespace App\Hooks;

use Studiometa\Foehn\Assets\ViteManifest;
use Studiometa\Foehn\Attributes\AsAction;

final class AssetHooks
{
    #[AsAction('wp_enqueue_scripts')]
    public function enqueue(): void
    {
        ViteManifest::fromTheme()
            ->enqueue('theme/assets/css/app.css', handle: 'starter-styles')
            ->enqueue('theme/assets/js/app.js', handle: 'starter-app', inFooter: true);
    }
}
```

### API

- `ViteManifest::fromTheme(string $distPath = 'dist', string $hotFile = 'hot')`: reads `<parent theme>/<distPath>/`. `$distPath` is relative to the theme directory, so with `outDir: 'theme/dist'` keep the default `'dist'`.
- `ViteManifest::fromChildTheme(string $distPath = 'dist', string $hotFile = 'hot')`: the same against the child theme.
- `new ViteManifest(string $distPath, string $distUri, string $hotFile = 'hot')`: absolute directory path and its public URI, for any other layout.
- `enqueue(string $entry, string $handle, bool $inFooter = false, array $deps = [], string $media = 'all'): self`: fluent.
- `exists(): bool`: a hot file or a manifest was found.
- `isDevServer(): bool`: the hot file was found.

What `enqueue()` does from a build:

- A CSS entry: `wp_enqueue_style("{$handle}-style", ...)`.
- A JS entry: `wp_enqueue_script($handle, ...)` with a `type="module"` tag, plus one style per file in the chunk's `css` array (`{$handle}-style`, `{$handle}-style-1`, ...).
- An entry name that is not in the manifest: nothing, silently.
- Versions are `null`: the file names are content-hashed.

From the dev server, every entry (CSS too) is enqueued as a module script from the server URL, after one `vite-client` script. `$inFooter` and `$media` are not used in that mode.

### Conditional and extra entries

Add the file to `input`, then enqueue it where it is needed:

```php
#[AsAction('wp_enqueue_scripts')]
public function enqueue(): void
{
    $vite = ViteManifest::fromTheme();

    $vite->enqueue('theme/assets/css/app.css', handle: 'theme-styles')
        ->enqueue('theme/assets/js/app.js', handle: 'theme-app', inFooter: true);

    if (is_singular('post')) {
        $vite->enqueue('theme/assets/js/article.js', handle: 'theme-article', inFooter: true);
    }
}
```

Use one `ViteManifest` instance per request where you can. Each instance adds its own `script_loader_tag` filter, which rewrites only the handles that instance enqueued.

## JavaScript entry (starter)

`theme/assets/js/app.js` registers components by discovery, with `@studiometa/js-toolkit`:

```js
import { defineManifest, fromMetaGlob, registerManifests } from "@studiometa/js-toolkit";
import "@studiometa/ui/autoload";

const manifest = defineManifest({
    packageName: "starter-theme",
    modules: fromMetaGlob(import.meta.glob("./components/*.js")),
});

registerManifests(manifest);
```

To add a component, add a file in `theme/assets/js/components/` and put `data-component="ClassName"` in the Twig markup. Do not change `app.js` or `input`. `import.meta.glob` makes lazy chunks, which the manifest and `ViteManifest` handle with no change.

## Gotchas

- **Wrong entry name.** `enqueue('assets/js/app.js', ...)` when `input` says `theme/assets/js/app.js` enqueues nothing, with no error. With the default `themeDir`, copy the exact string from `input`. Open `theme/dist/.vite/manifest.json` to see the keys.
- **Build outside the theme.** `outDir` must be inside the served theme directory (`theme/dist` in the starter). The default `dist` is relative to `themeDir`, which is the project root by default.
- **Hot file location.** The plugin writes the hot file at `<themeDir>/<hotFile>`. `ViteManifest::fromTheme()` reads `<theme>/<distPath>/<hotFile>`. With the starter configuration these are `hot` at the project root and `theme/dist/hot`, which are different files. If `isDevServer()` stays `false` while `npm run dev` runs, make the two paths the same, for example `hotFile: 'theme/dist/hot'` (the plugin does not create the directory, so `theme/dist/` must exist). See [references/dev-server.md](references/dev-server.md).
- **Stale hot file.** The hot file wins over the manifest. If the page loads from `localhost:5173` after you stop the dev server, a hot file was left behind: delete it.
- **No assets at all.** No manifest and no hot file means nothing is enqueued. Run `npm run build`, then check `ViteManifest::fromTheme()->exists()`.
- **Do not add `type="module"` yourself.** `wp_script_add_data($handle, 'type', 'module')` has no effect in WordPress. `ViteManifest` already rewrites the tag.
- **Do not enqueue the imported CSS by hand.** A JS entry's imported CSS is enqueued by `enqueue()`. A separate CSS `input` (like `app.css`) is its own entry and needs its own `enqueue()` call.
- **`reload` globs are relative to `themeDir`.** The default `templates/**/*.twig` does not match the starter's `theme/templates/`. Set `reload` explicitly, as the starter does.
- **`reload` matching is simple.** Changed files are matched with `**`, `*` and `?` only. Brace patterns like `{php,twig}` are watched but never trigger a reload; write one glob per extension.
- **DDEV proxy.** When `<themeDir>/.ddev/config.yaml` exists, the dev server proxies requests to the DDEV site. See [references/dev-server.md](references/dev-server.md).

## Related

- `foehn` skill: hooks (`#[AsAction]`), the kernel, Twig and the rest of the PHP side.
- Assets guide: https://studiometa.github.io/foehn-framework/guide/assets
- Starter theme guide: https://studiometa.github.io/foehn-framework/guide/starter-theme
- Hooks guide: https://studiometa.github.io/foehn-framework/guide/hooks
