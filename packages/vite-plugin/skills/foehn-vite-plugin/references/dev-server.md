# Dev server, hot file and DDEV

How `npm run dev` (`vite`) works with `@studiometa/foehn-vite-plugin`, and what to check when HMR does not reach WordPress.

## Sequence

1. Vite starts. The plugin's `config` hook expands `input` globs and looks for `<themeDir>/.ddev/config.yaml`.
2. When the HTTP server is listening, the plugin writes the hot file at `<themeDir>/<outDir>/<hotFile>` (`theme/dist/hot` in the starter). It creates `outDir` first, so you do not have to build before `npm run dev`. The content is the URL the server listens on, for example `http://localhost:5173`: `https` when you set `server.https`, the port Vite actually took, and `localhost` for a wildcard or loopback host.
3. WordPress renders a page. `ViteManifest` finds the hot file in `<distPath>/<hotFile>`, then enqueues `<url>/@vite/client` (handle `vite-client`) and `<url>/<entry>` for each `enqueue()` call, all as `type="module"`.
4. A change to a file that matches `reload` sends a full page reload. Changes to JS and CSS go through normal Vite HMR. With Vite 8, the client in the page also sends its console errors and uncaught errors to the server (see [Browser errors](#browser-errors)).
5. When the server closes, the plugin deletes the hot file. It also deletes it when Ctrl+C (`SIGINT`) or a closed terminal (`SIGHUP`) stops the process, because Vite closes the server only on `SIGTERM`. `vite build` also deletes it at the end of the build. Only a process that is killed (`SIGKILL`) or crashes leaves the file behind.

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
- `server.cors` is set to the localhost origins and the DDEV site, unless `vite.config.js` sets it. Vite allows only localhost pages by default, so a page opened on `https://<name>.ddev.site` could not load `/@vite/client` and had no HMR.

So you can open the Vite server URL and get the WordPress site through it. Know this when you debug:

- The plugin looks for `.ddev/` in `themeDir`, not in parent directories. With the default `themeDir` (the directory you run `vite` from), that is the project root, where the starter keeps `.ddev/`.
- The proxy is set only when the file exists. Without DDEV, no proxy is set.

## Browser errors

The plugin sets `server.forwardConsole: true` when `vite.config.js` does not set it. Vite 8 turns it on by itself only when the Vite process runs inside a coding agent (`AI_AGENT` and similar variables), and a developer usually runs `npm run dev` in their own terminal.

- The Vite client in the page wraps `console.error` and `console.warn` and listens for `error` and `unhandledrejection`. It sends each event over the HMR websocket. The server prints it in its terminal as `[vite] (client) [console.error] …`.
- The plugin listens for the same `vite:forward-console` event and appends one JSON line to `<outDir>/browser-errors.jsonl`: `time`, `level` (`error`, `rejection`, `console`), `class` (`TypeError`, `console.warn`), `message` (2000 characters), `file` and `line` of the first own frame, `stack` (own frames, 8 at most, then `+N other frames`). Own frames are modules under the Vite root outside `node_modules`. Their line is mapped through the module's source map, so a TypeScript file gives the source line. The file is rotated at 256 KB, 3 files at most. `vite build` empties `outDir`, and the log with it.
- `wp foehn errors --browser` reads `<parent theme>/dist/browser-errors.jsonl`. It has the same options as `wp foehn errors`, with `--level=error,rejection,console`.
- Other `logLevels` (`server.forwardConsole: { logLevels: ['error', 'warn', 'log'] }`) are written as `console.log`. `server.forwardConsole: false` turns off the terminal output and the file.
- With Vite 6 or 7 the option does not exist: nothing is sent, and the file is not written.
- Nothing is written when the page does not load the Vite client: a page from the build, or a page on the DDEV URL whose browser blocks the client (see the CORS line below).

## Checklist when the page has no assets in dev

1. `npm run dev` is running, and `theme/dist/hot` (or `<outDir>/<hotFile>`) exists.
2. The hot file holds a URL the browser can reach (the browser loads from it, not PHP).
3. The entry names in `enqueue()` are the exact strings in `input`.
4. The page HTML has `<script type="module" src=".../@vite/client" id="vite-client">`.
5. If you stopped the dev server and the page still points to it, delete the stale hot file.
