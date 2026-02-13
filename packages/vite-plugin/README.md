# @studiometa/foehn-vite-plugin

Vite plugin for the Føhn WordPress framework.

## Installation

```bash
npm install -D @studiometa/foehn-vite-plugin vite
```

## Usage

```ts
// vite.config.ts
import { defineConfig } from "vite";
import tailwindcss from "@tailwindcss/vite";
import foehn from "@studiometa/foehn-vite-plugin";

export default defineConfig({
  plugins: [
    foehn({
      // Glob patterns for entry points
      input: ["src/js/app.js", "src/css/app.css"],

      // Files to watch for full reload (default: ["templates/**/*.twig"])
      reload: ["templates/**/*.twig", "app/**/*.php"],

      // Output directory (default: "dist")
      outDir: "dist",

      // Theme directory context (default: process.cwd())
      themeDir: "./theme",
    }),
    tailwindcss(),
  ],
});
```

## Features

### Glob Input Resolution

Input patterns support glob syntax:

```ts
foehn({
  input: ["src/js/*.js", "src/css/*.css"],
});
```

### Vite Manifest

The plugin enables Vite's native manifest generation (`build.manifest: true`). The manifest is written to `.vite/manifest.json` in the output directory.

### Hot Reload

During development, the plugin writes a `hot` file containing the dev server URL. The PHP side reads this file to detect dev mode and inject the Vite client script.

### File Watching

The plugin watches files matching the `reload` patterns and triggers a full page reload when they change. This is useful for PHP and Twig files that don't go through Vite.

### DDEV Integration

When a `.ddev/config.yaml` file is detected, the plugin automatically configures a proxy to the DDEV site, allowing seamless development with HMR.

## Options

| Option     | Type                 | Default                   | Description                       |
| ---------- | -------------------- | ------------------------- | --------------------------------- |
| `input`    | `string \| string[]` | _required_                | Entry point glob patterns         |
| `reload`   | `string \| string[]` | `["templates/**/*.twig"]` | Patterns to watch for full reload |
| `outDir`   | `string`             | `"dist"`                  | Output directory for built assets |
| `themeDir` | `string`             | `process.cwd()`           | Theme directory context           |
| `hotFile`  | `string`             | `"hot"`                   | Name of the hot file              |

## PHP Integration

Use the `ViteManifest` helper from the Føhn framework to enqueue assets:

```php
use Studiometa\Foehn\Assets\ViteManifest;

#[AsAction("wp_enqueue_scripts")]
public function enqueueAssets(): void
{
    ViteManifest::fromTheme()
        ->enqueue("src/js/app.js", handle: "theme-app", inFooter: true)
        ->enqueue("src/css/app.css", handle: "theme-styles");
}
```

## License

MIT
