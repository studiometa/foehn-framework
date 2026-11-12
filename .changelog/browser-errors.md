### Added

- `@studiometa/foehn-vite-plugin` turns on Vite 8's `server.forwardConsole` in dev, unless `vite.config.js` sets it: the page sends its console errors and warnings, uncaught errors and unhandled promise rejections to the dev server. The plugin also writes them to `browser-errors.jsonl` in `outDir` (`theme/dist/` in the starter), with the source file and line mapped through the source map, in at most 3 files of 256 KB. Set `server.forwardConsole: false` to turn it off. Vite 6 and 7 send nothing.
- `wp foehn errors --browser` prints the entries of that browser log, with the `--limit`, `--level=error,rejection,console`, `--since`, `--format=json` and `--clear` options.

### Fixed

- In a DDEV project, `@studiometa/foehn-vite-plugin` adds the DDEV site to `server.cors`, unless `vite.config.js` sets it. A page opened on `https://<project>.ddev.site` can now load `/@vite/client` from the dev server, so HMR works there too.

### Dependencies

- `@studiometa/foehn-vite-plugin` requires `@jridgewell/trace-mapping` `^0.3.31`.
