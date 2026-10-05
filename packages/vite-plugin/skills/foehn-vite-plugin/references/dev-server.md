# Dev server, hot file and DDEV

How `npm run dev` (`vite`) works with `@studiometa/foehn-vite-plugin`, and what to check when HMR does not reach WordPress.

## Sequence

1. Vite starts. The plugin's `config` hook expands `input` globs and looks for `<themeDir>/.ddev/config.yaml`.
2. When the HTTP server is listening, the plugin writes the hot file at `<themeDir>/<outDir>/<hotFile>` (`theme/dist/hot` in the starter). It creates `outDir` first, so you do not have to build before `npm run dev`. The content is the URL the server listens on, for example `http://localhost:5173`: `https` when you set `server.https`, the port Vite actually took, and `localhost` for a wildcard or loopback host.
3. WordPress renders a page. `ViteManifest` finds the hot file in `<distPath>/<hotFile>`, then enqueues `<url>/@vite/client` (handle `vite-client`) and `<url>/<entry>` for each `enqueue()` call, all as `type="module"`.
4. A change to a file that matches `reload` sends a full page reload. Changes to JS and CSS go through normal Vite HMR.
5. When the server closes, the plugin deletes the hot file. `vite build` also deletes it at the end of the build.

## Hot file name

`hotFile` is a file name, not a path. The plugin and `ViteManifest` both look for it inside the dist directory, so the defaults agree: `outDir: 'theme/dist'` in `vite.config.js` and `ViteManifest::fromTheme()` with no arguments. If you change `hotFile`, pass the same name as the second argument of `fromTheme()` / `fromChildTheme()`.

To check where PHP looks:

```php
// From wp shell or a test
$vite = \Studiometa\Foehn\Assets\ViteManifest::fromTheme();
var_dump($vite->isDevServer());
```

## DDEV proxy

When `<themeDir>/.ddev/config.yaml` exists, the plugin reads `name` and `router_https_port` from it and sets `server.proxy`:

- Target: `https://<name>.ddev.site`, with `:<router_https_port>` when that port is not `443`. The plugin always uses the `ddev.site` domain: it does not read `project_tld` or `additional_fqdns`.
- `changeOrigin: true`, `secure: false`.
- Vite keeps every request it can serve: its own routes (`/@vite/client`, `/@fs/`, `/@id/`, `/__…`), `/node_modules/`, the HMR ping, and any path that is a file under the Vite root or the public directory, whatever the query (`?import`, `?direct`, `?v=`). The entries `ViteManifest` enqueues are files under the root, so Vite serves them.
- Every other request (WordPress pages, the admin, a file that does not exist) goes to DDEV. The HMR websocket stays on Vite.

So you can open the Vite server URL and get the WordPress site through it. Know this when you debug:

- The plugin looks for `.ddev/` in `themeDir`, not in parent directories. With the default `themeDir` (the directory you run `vite` from), that is the project root, where the starter keeps `.ddev/`.
- The proxy is set only when the file exists. Without DDEV, no proxy is set.

## Checklist when the page has no assets in dev

1. `npm run dev` is running, and `theme/dist/hot` (or `<outDir>/<hotFile>`) exists.
2. The hot file holds a URL the browser can reach (the browser loads from it, not PHP).
3. The entry names in `enqueue()` are the exact strings in `input`.
4. The page HTML has `<script type="module" src=".../@vite/client" id="vite-client">`.
5. If you stopped the dev server and the page still points to it, delete the stale hot file.
