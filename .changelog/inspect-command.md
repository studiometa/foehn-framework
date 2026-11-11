### Added

- `wp foehn inspect` reports the project model from what discovery found: post types with their Timber model, `#[AsPostMeta]` fields and taxonomies, taxonomies, blocks, patterns, block bindings, template controllers, context providers, menus, image sizes, rewrite rules, REST routes, `wp foehn` commands, active plugins with their versions, theme supports and the Vite entry points. `wp foehn inspect <section>` reads one section, and `--format=json` gives the report to a program or a coding agent. See [Project Inspection](https://studiometa.github.io/foehn-framework/guide/cli-commands#project-inspection)
- `ViteManifest::entries()` lists the entry points of the build, and `ViteManifest::devServer()` gives the dev server URL while `npm run dev` runs
