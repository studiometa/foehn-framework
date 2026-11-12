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

During development, the plugin writes a `hot` file inside `outDir` containing the dev server URL. It creates `outDir` when needed, so the dev server works before the first build. `ViteManifest::fromTheme()` reads the same file to detect dev mode and inject the Vite client script. If you change `hotFile`, pass the same name as the second argument of `fromTheme()`. The plugin removes the file when the dev server stops, also on Ctrl+C, and at the end of a build.

### Browser Errors

With Vite 8, the plugin turns on [`server.forwardConsole`](https://vite.dev/config/server-options#server-forwardconsole), which Vite turns on only inside a coding agent. The Vite client in the page then sends the console errors and warnings, the uncaught errors and the unhandled promise rejections to the dev server, which prints them in its terminal. The plugin also writes each one to `browser-errors.jsonl` in `outDir`, beside the hot file, as one JSON object per line with the source file and line (mapped through the module's source map). The file is rotated at 256 KB and keeps 3 files at most. `wp foehn errors --browser` reads it, so an agent in another terminal can read the errors of the page.

Set `server.forwardConsole: false` to turn off the terminal output and the file. Vite 6 and 7 send nothing.

### File Watching

The plugin watches files matching the `reload` patterns and triggers a full page reload when they change. This is useful for PHP and Twig files that don't go through Vite.

### DDEV Integration

When a `.ddev/config.yaml` file is detected, the plugin proxies the dev server to the DDEV site. Vite keeps every request it can serve (its own `/@` routes, `/node_modules/`, the HMR connection and every file under the Vite root or the public directory) and sends the others, such as WordPress pages, to DDEV. The plugin also adds the DDEV site to `server.cors`, so a page opened on the DDEV URL can load `/@vite/client` (Vite allows only localhost pages by default). A `server.cors` in your config is kept as is.

## Options

| Option     | Type                 | Default                   | Description                       |
| ---------- | -------------------- | ------------------------- | --------------------------------- |
| `input`    | `string \| string[]` | _required_                | Entry point glob patterns         |
| `reload`   | `string \| string[]` | `["templates/**/*.twig"]` | Patterns to watch for full reload |
| `outDir`   | `string`             | `"dist"`                  | Output directory for built assets |
| `themeDir` | `string`             | `process.cwd()`           | Theme directory context           |
| `hotFile`  | `string`             | `"hot"`                   | Name of the hot file, in `outDir` |

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

## AI agents

This package ships the `foehn-vite-plugin` [Agent Skill](https://agentskills.io/) in [`skills/foehn-vite-plugin/SKILL.md`](skills/foehn-vite-plugin/SKILL.md). See [AI Agents](https://studiometa.github.io/foehn-framework/guide/ai-agents) to install it.

## License

MIT
