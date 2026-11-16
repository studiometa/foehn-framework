### Fixed

- `@studiometa/foehn-vite-plugin` sets Vite's `base` to `'./'` in a build. With the default `base: '/'`, the chunks of a dynamic `import()` and their `modulepreload` hints pointed at the site root and answered 404, because WordPress serves the build from the theme. A project that set `base: command === 'build' ? './' : '/'` by hand can remove it. A `base` set in `vite.config.js` still wins
