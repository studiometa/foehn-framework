### Added

- `wp foehn inspect` reports the project model from what discovery found: post types with their Timber model, `#[AsPostMeta]` fields and taxonomies, taxonomies, blocks, patterns, block bindings, template controllers, context providers, menus, image sizes, rewrite rules, REST routes, `wp foehn` commands, active plugins with their versions, theme supports and the Vite entry points. With no section it prints a summary, the names in each section; `wp foehn inspect <section>...` prints the named sections in full, `--all` prints every section, and `--format=json` gives the report on one line to a program or a coding agent. See [Project Inspection](https://studiometa.github.io/foehn-framework/guide/cli-commands#project-inspection)
- `ViteManifest::entries()` lists the entry points of the build, and `ViteManifest::devServer()` gives the dev server URL while `npm run dev` runs

### Fixed

- The starter's ddev image builds WP-CLI as the runtime image does, with the `php-cli-tools` and `react/promise` versions that PHP 8.5 does not deprecate. `ddev wp` no longer prints a `Deprecated:` line ahead of its output, so `ddev wp foehn inspect --format=json` gives valid JSON. A project made from an earlier starter can copy `.ddev/web-build/prepend.Dockerfile.wp-cli` and `.ddev/web-build/Dockerfile.wp-cli`, then run `ddev restart`
