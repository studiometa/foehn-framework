# Dev server, hot file and DDEV

How `npm run dev` (`vite`) works with `@studiometa/foehn-vite-plugin`, and what to check when HMR does not reach WordPress.

## Sequence

1. Vite starts. The plugin's `config` hook expands `input` globs and looks for `<themeDir>/.ddev/config.yaml`.
2. When the HTTP server is listening, the plugin writes the hot file at `<themeDir>/<hotFile>`. The content is `http://localhost:<port>`, for example `http://localhost:5173`, with the port the server actually listens on. The plugin always writes `http` and `localhost`, also when you set `server.https` or `server.host`.
3. WordPress renders a page. `ViteManifest` finds the hot file in `<distPath>/<hotFile>`, then enqueues `<url>/@vite/client` (handle `vite-client`) and `<url>/<entry>` for each `enqueue()` call, all as `type="module"`.
4. A change to a file that matches `reload` sends a full page reload. Changes to JS and CSS go through normal Vite HMR.
5. When the server closes, the plugin deletes the hot file. `vite build` also deletes it at the end of the build.

## Make the two hot file paths meet

The plugin resolves the hot file against `themeDir`. `ViteManifest` looks for it inside the dist directory. They agree only when both resolve to the same file.

Check both paths:

```bash
# Where the plugin writes (themeDir defaults to the directory you run vite from)
ls hot theme/dist/hot 2>/dev/null
```

```php
// Where PHP reads, from wp shell or a test
$vite = \Studiometa\Foehn\Assets\ViteManifest::fromTheme();
var_dump($vite->isDevServer());
```

One configuration where they agree, with the starter layout:

```js
foehn({
    input: ["theme/assets/js/app.js", "theme/assets/css/app.css"],
    reload: ["theme/templates/**/*.twig", "theme/app/**/*.php"],
    outDir: "theme/dist",
    hotFile: "theme/dist/hot",
});
```

`fromTheme()` keeps its defaults (`'dist'`, `'hot'`). The plugin uses `writeFile` without creating directories, so `theme/dist/` must exist before `npm run dev` (run `npm run build` once, or create it).

If you use a different `hotFile` name only (not a path), pass the same name as the second argument of `fromTheme()` / `fromChildTheme()`.

## DDEV proxy

When `<themeDir>/.ddev/config.yaml` exists, the plugin reads `name` and `router_https_port` from it and sets `server.proxy`:

- Target: `https://<name>.ddev.site`, with `:<router_https_port>` when that port is not `443`. The plugin always uses the `ddev.site` domain: it does not read `project_tld` or `additional_fqdns`.
- `changeOrigin: true`, `secure: false`.
- Pattern: `^(?!/@|/node_modules|/src)`. Every request path that does not start with `/@`, `/node_modules` or `/src` goes to DDEV.

So you can open the Vite server URL and get the WordPress site through it. Know this when you debug:

- Vite runs its proxy before it transforms modules. A request for an entry outside `/src` (for example `/theme/assets/js/app.js`) matches the pattern and goes to DDEV, not to Vite. If the dev server answers entry requests with WordPress content or a 404, this is the cause.
- The plugin looks for `.ddev/` in `themeDir`, not in parent directories. With the default `themeDir` (the directory you run `vite` from), that is the project root, where the starter keeps `.ddev/`.
- The proxy is set only when the file exists. Without DDEV, no proxy is set.

## Checklist when the page has no assets in dev

1. `npm run dev` is running, and the hot file exists where `ViteManifest` reads it (see above).
2. The hot file holds a URL the browser can reach (the browser loads from it, not PHP).
3. The entry names in `enqueue()` are the exact strings in `input`.
4. The page HTML has `<script type="module" src=".../@vite/client" id="vite-client">`.
5. If you stopped the dev server and the page still points to it, delete the stale hot file.
