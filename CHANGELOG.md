# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Add an `ImageTransformer` abstraction so a template asks for a size without naming who produces it, and a project swaps provider in `foehn.config.php` rather than in every template. `NullTransformer` is the default and returns the URL it was given, so nothing changes until a project asks ([#151])
- Add `GlideTransformer`, a self-hosted driver over `league/glide` — a `suggest`, so nothing pulls `intervention/image` into a project that does not transform images. It follows the uploads: directories on local disk, prefixes of the same bucket under `humanmade/s3-uploads`, reading where the plugin writes and caching beside it. It reuses the plugin's own S3 client, so the endpoint, path-style addressing and checksum settings a non-AWS bucket needs — none of which are constants — apply to transforms too ([#151])
- Add `image_url()` to Twig, and `ImageCacheHooks` to forget an image's transforms when the image itself changes — a cache key is built from the path and the transform, never from the content, so a cropped image would otherwise serve its old pixels indefinitely ([#151])
- **Demo:** Use both ways of sizing an image, because they answer different questions. Homepage cards keep a registered `#[AsImageSize]`; the plates on a project page crop to two ratios and so ask for the crop with `image_url()`. Measured there, a plate is 356ms the first time and 24ms after, served from the bucket by `.ddev/nginx/image-cache.conf` with no WordPress boot. The smoke suite asserts the transform is produced, that the second request does not reach PHP, and that an unsigned one is refused ([#151])

  URLs are signed with `NONCE_SALT`, and unsigned requests are refused before anything is read or written: at a few hundred milliseconds per cold transform, `?w=9999` is otherwise an instruction to spend CPU and disk on demand. GD is the default driver because, measured on a 2777x1973 photograph, it is about twice as fast as Imagick _and_ produces smaller files — and it is the extension that is always present. See `docs/guide/images.md` for the webserver rule that serves cache hits without booting WordPress.

### Fixed

- Dispatch a `#[AsRewriteRule]` whose query variables all come from the URL. `matchableVars()` skipped any variable whose value was a `$matches[n]` capture, so a rule like `index.php?foehn_image=$matches[1]` — every variable captured, nothing constant — was left with nothing to match on and never reached its handler. The rewrite still matched, so the symptom was an ordinary WordPress 404 on a URL that `wp rewrite list` showed as correctly registered. A captured variable is now recorded as required-and-non-empty rather than dropped ([#151])

[#151]: https://github.com/studiometa/foehn-framework/issues/151

## [0.5.3] - 2026-08-21

Nine fixes to what a project actually receives from the starter. Three of them made a freshly scaffolded project unusable in ways a monorepo checkout never shows, because CI lints and tests the Vite plugin only — never the starter as a consumer sees it.

### Fixed

- **Starter:** Stop wiping the WordPress security keys on every `create-project`. The installer generated all eight into `.env` and reported doing so, then the starter's own `post-create-project-cmd` ran `copy('.env.example', '.env')` — after the installer, by definition — and put the empty template back. Every scaffold ended with no keys, silently, until the first production request, which `wp-config.php` refuses while any key is empty ([#142])
- **Starter:** Resolve `tests/php/bootstrap.php` from wherever the autoloader is, rather than by counting directory levels. It used `dirname(__DIR__, 3)` and `dirname(__DIR__, 4)`, which land inside this monorepo and outside an installed project, so the Pest suite could not start at all. The WordPress stubs do ship with `studiometa/foehn`; only the paths were wrong ([#143])
- **Starter:** Point `.oxlintrc.json` at the project's own `node_modules`. It extended `../../node_modules/@studiometa/oxlint-config`, this monorepo's hoisted install, so `oxlint` exited with a config error in any scaffolded project ([#144])
- **Starter:** Keep monorepo-only files out of the published package. `composer.local.json`/`.lock` declare `path` repositories pointing at `../foehn` and `../installer` with `minimum-stability: dev`, and `.ddev/config.yaml` shipped `post-start` hooks whose own comment says they cannot work elsewhere. The Composer-local files are now `export-ignore`d, and the ddev hooks live in `.ddev/config.monorepo.yaml` — tracked, so a fresh clone still bootstraps, and export-ignored, so a project does not inherit it ([#145])
- **Starter:** Fall back to `pages/archive` when a post type has no dedicated template. `ArchiveController` rendered one exact name, so any post type registered with `hasArchive: true` returned HTTP 500 on its archive URL until the theme also added `pages/archive-<type>.twig` — and the exception named the missing file rather than the missing fallback. `SingleController` already used `renderFirst()`. Also reads the post type from `get_queried_object()->name`, since `get_query_var('post_type')` can be an array that interpolates as `Array` ([#146])
- **Installer:** Remove theme symlinks left behind by a previous `theme-name`. Renaming it created the new link and kept the old one, so `wp theme list` showed the same directory twice and `wp theme activate` could pick a name nobody uses. Only links pointing at the theme directory are removed ([#148])
- **Installer:** Report the security keys accurately. `Generated: security keys in .env` printed on every install, including runs that correctly left existing keys alone — which made the output useless for spotting a run that did rewrite them. The no-op path now says `Security keys: already set` and writes nothing ([#148])
- **Demo:** Drop the duplicate `humanmade/s3-uploads` key in `composer.json`. Legal JSON and harmless to resolution, but flagged by editors and schema validators ([#149])

### Added

- **Starter:** A `PageController` and `pages/page.twig`. Pages — the one content type every WordPress site has — had no controller, so they bypassed the view layer entirely: WordPress rendered them its own way and a `pages/page.twig` added to the theme was never read. Resolves by slug rather than by full path, which stops being right the moment a page moves in the tree ([#147])
- **Starter:** Enable `Hooks\Cleanup\DisableGlobalStyles` by default, and document the cascade conflict it solves. WordPress prints the styles it derives from `theme.json` inline and _after_ the theme's stylesheet, so core wins on load order; with no `theme.json` those are core's defaults, and `body { padding: 0 }` flattens a gutter the theme sets. The symptom is a theme rule struck through by an inline sheet the theme never enqueued, which reads as a broken stylesheet rather than a load-order fight. Drop it from `foehn.config.php` if you adopt `theme.json` presets ([#150])
- **Starter:** A packaging test asserting the archive contains no path reaching outside the project, and no `post-create-project-cmd` overwriting `.env`

[#142]: https://github.com/studiometa/foehn-framework/issues/142
[#143]: https://github.com/studiometa/foehn-framework/issues/143
[#144]: https://github.com/studiometa/foehn-framework/issues/144
[#145]: https://github.com/studiometa/foehn-framework/issues/145
[#146]: https://github.com/studiometa/foehn-framework/issues/146
[#147]: https://github.com/studiometa/foehn-framework/issues/147
[#148]: https://github.com/studiometa/foehn-framework/issues/148
[#149]: https://github.com/studiometa/foehn-framework/issues/149
[#150]: https://github.com/studiometa/foehn-framework/issues/150
[0.5.3]: https://github.com/studiometa/foehn-framework/releases/tag/0.5.3

## [0.5.2] - 2026-08-20

### Added

- Add the `gallery` block attribute control: several attachments in one field, ordered, with thumbnails in the sidebar. `image` could not serve — `MediaUpload`'s `multiple` changes what `onSelect` receives and the value stops being a scalar
- Add the `file` block attribute control, for an attachment of any kind. `image` hard-codes `allowedTypes: ['image']`, which left audio, video and documents unreachable from a block; `file` reads the types from the schema and restricts nothing by default
- Add the `posts` block attribute control, a searchable picker for related content. It stores post ids **in the order the author arranged them** and offers move-up, move-down and remove, because a relation list is authored rather than queried. `postTypes` narrows the search; without it every viewable post type is searched. This is the one control with no core equivalent, and the main thing a theme needed ACF for
- Add the `allowedTypes` and `postTypes` editor-only schema keys, feeding the three controls above and stripped before the schema reaches WordPress

### Fixed

- Warn in debug mode when `gallery` or `posts` is declared on an attribute that is not an array. WordPress validates a block attribute against its schema and replaces a mismatch with the default, so a list control on a scalar type kept every selection on screen until the next reload and then lost all but the first — a data loss that reads as an editor bug

[0.5.2]: https://github.com/studiometa/foehn-framework/releases/tag/0.5.2

## [0.5.1] - 2026-08-20

### Added

- Publish `@studiometa/foehn-vite-plugin` to npm from the release workflow, through npm trusted publishing: the job authenticates with an OIDC token and no `NPM_TOKEN` exists anywhere. The plugin now carries the same version as every other package, `0.5.1`, and the job refuses to publish when that version does not match the tag ([#140])

### Fixed

- **Starter, demo:** Require `@studiometa/foehn-vite-plugin` at `^0.5.1` rather than `*`. `*` resolved only through the monorepo's own workspaces, so `npm install` in a project created from the starter ended on a 404 for a package that had never been published once — and `npm run build` could not have worked either, `vite.config.js` importing it ([#140])

[#140]: https://github.com/studiometa/foehn-framework/pull/140
[0.5.1]: https://github.com/studiometa/foehn-framework/releases/tag/0.5.1

## [0.5.0] - 2026-08-20

### Added

- Add `ViteManifest` for enqueuing Vite builds, with dev-server, imported-CSS and ES-module handling ([#137])
- Add `StudiometaUi` hook class registering the `@ui` and `@svg` Twig namespaces; `studiometa/ui` stays a `suggest` ([#138])
- **Starter, demo:** Autoload js-toolkit components from a manifest, and `@studiometa/ui` components through `@studiometa/ui/autoload` ([#138])
- **Demo:** A photographer's portfolio — four page types, Swiss design, 30 credited Unsplash photographs — with the database and media committed and `database/restore.sh` to mount it ([#137])
- Serve uploads from the site's own domain with `S3_UPLOADS_DISABLE_REPLACE_UPLOAD_URL` and a webserver rule, instead of the bucket hostname ([#137])
- Offload uploads to object storage by integrating `humanmade/s3-uploads`: `wp-config.php` defines the `S3_UPLOADS_*` constants from the environment, and `S3UploadsEndpoint` supplies the endpoint filter for R2, Scaleway and MinIO ([#135])
- Add `#[AsBlockBinding]` to register a block bindings source for computed attribute values; declared `#[AsPostMeta]` needs none, being bindable through core's `core/post-meta` ([#129])
- Add `#[AsSettingsPage]`, with a Twig form and `Setting::string()`/`bool()`/`int()`/`number()`; no field abstraction, by decision ([#128])
- Add `#[AsRewriteRule]` and `RewriteHandlerInterface`, flushing only when the declared set changes; `wp foehn rewrite:flush` forces it ([#127])
- **Breaking:** Move the ACF layer to `studiometa/foehn-acf`. Classes keep their namespaces, so a project adds one `composer require` and changes no imports ([#125])
- Add `#[AsPostMeta]` for typed post meta with a REST schema, so custom fields no longer need ACF ([#123])
- Add `wp foehn discovery:list`, reporting every discovery, its phase, its items and their attribute arguments ([#122])
- Add `#[AsDiscovery]`, and discover discovery classes themselves, so a package or a theme can add one ([#121])
- Add a static page cache serving stored HTML before WordPress boots ([#124])
- Page cache: nginx, Apache and a PHP drop-in reader, all computing the same filename; `wp foehn cache:config --server` emits the snippet ([#124])
- Page cache: event-driven invalidation on post, term, menu, option and theme changes ([#124])
- Page cache: query args are normalised and `ignoredQueryArgs` dropped, so tracking parameters do not fragment the cache ([#124])
- Page cache: non-ASCII permalinks resolve to a single cache file across percent-escape casing ([#124])
- Add `wp foehn cache:clear`, `cache:status`, `cache:config` and `cache:warm` ([#124])
- WordPress security keys are read from the environment, listed in `.env.example`, or set in `config/wordpress-salts.config.php` ([#120])
- Add `wp foehn salts:generate` for rotating WordPress security keys ([#120])
- The discovery cache fills itself: a request that scans writes what it found, and `composer install` clears it ([#119])
- Config files may be named for an environment — `foehn.production.config.php` — and are read only there ([#118])
- **Starter:** An integration smoke test run in CI against a real WordPress in ddev, on a cold and a warm discovery cache
- Add tests for `studiometa/foehn-installer`, which had none, and run every package's suite in CI
- Add `#[AsCron]` attribute for recurring background jobs via Action Scheduler ([db208f7], [#111], [#110]):
- Add `#[AsJob]` attribute for async job dispatch with typed DTO payloads ([db208f7], [#111], [#110]):
- Add `HookNameResolver` for deterministic hook names derived from FQCN ([1a728f9], [#111])
- Add `CacheInterface` contract and `TransientCache` implementation for dependency injection ([#96])
- Add `TaggedCache` for tag-based cache invalidation via `CacheInterface::tags()` ([#96])
- Add `Arrayable` interface and `HasToArray` trait for typed DTO context composition ([#97])
- Add built-in DTOs for common ACF field patterns ([#97]):
- Widen `compose()` return type to `array|Arrayable` on block interfaces ([#97])
- **Starter:** Add Hero block example demonstrating DTO context composition ([#97])
- **New package:** `@studiometa/foehn-vite-plugin` — Vite plugin for front-end bundling ([#89]):
- Add fluent `PostQueryBuilder` for null-safe query building ([#91], [#100]):
- Add `QueriesPostType` trait with `query()`, `all()`, `find()`, `first()`, `count()`, `exists()` ([#91], [#100])
- Add `PostTypeRegistry` for class-to-post-type mapping ([#91], [#100])
- Add `Foehn\Models\Post` and `Foehn\Models\Page` base classes with query support ([#91], [#100])
- Add Starter Theme documentation with quick start guide and feature overview ([#101])
- Add `TemplateContext` class for typed template controller context ([#107]):

### Changed

- **Breaking:** Split `packages/starter` in two. `studiometa/foehn-demo` takes the demonstrations and the exhaustive test suites; the starter keeps only what a new project cannot start without ([#130])
- **Starter:** `HeroBlock` is a native block and the starter requires no ACF at all ([#126])
- **Breaking:** `DiscoveryRunner::getDiscoveryPhases()` and `getAllDiscoveryClasses()` are removed; `hasRun()` takes a `DiscoveryPhase`. A discovery's phase lives on `#[AsDiscovery]`, and within a phase they apply in class-name order
- **Breaking:** Discovery is built on `tempest/discovery` rather than Foehn's own scanner, with locations from Composer's `installed.json`. `ClassScanner`, `DiscoveryLocation`, `DiscoveryCache`, `WpDiscoveryItems`, `AttributeCodec`, `CacheableDiscovery` and `WpDiscovery` are gone
- Discovery items are cached as attribute instances through `symfony/cache`, so no discovery describes a cache format. The cache is written per location, and `discovery:status` reports how many locations are warm
- WP-CLI commands are registered under `wp foehn` rather than `wp tempest`
- `discovery:warm` is removed: it and `discovery:generate` did the same thing, and the deployment documentation already used `generate`
- The framework's `Models\Post` and `Models\Page` now register as Timber's class map entries for `post` and `page`, as their `#[AsTimberModel]` attributes ask. A model of your own for the same type still wins
- `Kernel::boot()`'s configuration array is a default that a `foehn.config.php` overrides wholesale
- Upgrade PHP dependencies: Tempest `^3.4` → `^3.18`, Timber `^2.0` → `^2.5`, Pest `^3.0` → `^5.1` (PHPUnit 13), Mago `^1.8` → `^1.46`, `composer/composer` `^2.0` → `^2.10`, ACF Pro stubs `^6.5` → `^6.8`, `studiometa/webpack-config` `^6.3` → `^6.4`
- Declare `tempest/support` as a direct dependency of `studiometa/foehn` — `Filesystem` and `str()` were used across ~15 files but only resolved transitively
- **Starter:** Upgrade WordPress `^6.7` → `^7.0`
- Upgrade front-end dependencies: Vite `^8`, Vitest `^4`, TypeScript `^7.0`, Tailwind `^4.3`, js-toolkit `^3.9`, Playwright `^1.62`, and others
- Widen the `@studiometa/foehn-vite-plugin` Vite peer range to `^6 || ^7 || ^8`
- Run Pest from the monorepo root with `--test-directory`; Pest 5 resolves the test path from the Composer root, not the working directory
- Bump CI to Node 24 — lint-staged 17 requires Node >= 22.22.1
- A discovered item is the attribute instance plus the reflection facts it lacks, rather than a flattened copy, so `itemToCacheable()` and the per-discovery cache plumbing are gone
- `getCacheableData()`, `restoreFromCache()` and `wasRestoredFromCache()` are part of the `WpDiscovery` interface; every discovery is cacheable, so the three `method_exists()` probes are gone
- Bump the discovery cache schema to `2`. A cache written by an earlier version is rejected and rebuilt on the next request
- Generated `make:` files are built by `ClassFileGenerator`, setting attribute arguments structurally rather than by matching literals; a substitution that finds no target fails instead of emitting the placeholder
- Generated class files now declare `strict_types=1`
- Refactor `DiscoveryRunner` to use `runPhase()` loop, reducing code duplication ([aa22c38], [#111])
- **BREAKING:** Require PHP 8.5+ and Tempest Framework v3.0 ([#98])
- **BREAKING:** Remove static `Helpers\Log` helper — use Tempest Logger (`tempest/log`) instead ([#96])
- **BREAKING:** Remove static `Helpers\Cache` helper — use injectable `CacheInterface` instead ([#96])
- **BREAKING:** `TemplateControllerInterface::handle()` now receives typed `TemplateContext` parameter ([#107])
- **BREAKING:** `ContextProviderInterface::provide()` now receives and returns `TemplateContext` ([#107])
- **BREAKING:** `ViewEngineInterface::render()` and `renderFirst()` now accept `array|object` context ([#107])
- Replace `\Tempest\get()` with injected `Container` in `HookDiscovery` and `TwigExtensionDiscovery` ([#96])

### Fixed

- `ConfiguresPostType` and `ConfiguresTaxonomy` never applied, so a post type's rewrite slug was dropped and its archive answered 404 ([#137])
- **Starter, demo:** Built assets were never enqueued, and Vite built outside the theme where nothing is served ([#137])
- **Demo:** `ArchiveController` threw on a post type archive with no template of its own; it falls back now ([#137])
- `config/*.config.php` loaded before `.env`, so project config could not read the project's environment ([#133])
- **Security:** Every install ran on WordPress security keys derived from the web root path, which are guessable and forgeable ([#120])
- The framework's own discoverables never registered, so its Twig extensions and CLI commands were absent ([#118])
- `*.config.php` files were never read, so every project config file did nothing ([#118])
- `#[SkipDiscovery]` was ignored by the scanner, so `make:` command stubs registered their example attributes ([#118])
- **`make:field-group`:** `--post-type`, `--taxonomy` and `--page-template` were ignored, leaving every generated field group located on `post`
- **`make:controller`:** `--templates` with more than one template generated broken code
- Fix a fatal error in the starter config and in four doc pages: `DiscoveryCacheStrategy` was imported from `Tempest\Core`, which no longer exists — it lives in `Tempest\Discovery`
- **Vite plugin:** Import `fast-glob` as a default export — `import { glob }` is not resolvable from real Node ESM and broke every consumer build
- **Vite plugin:** Rename the `vite-plugin-dts` option `rollupTypes` to `bundleTypes` and add the now-optional `@microsoft/api-extractor` peer; without both, v5 silently emitted per-file declarations and never wrote `dist/index.d.ts`
- **Vite plugin:** Correct `.oxfmtrc.json`, which used Biome option names (`indentWidth`, `lineWidth`, …) and was therefore ignored
- **Vite plugin:** Replace `__dirname` with `import.meta.dirname` in `vite.config.ts` for Vite's native config loader
- **Starter:** Remove a duplicate `createFakeViewEngine()` in `HeroBlockTest`, which shadowed the one in `Pest.php` and made the file fatal once Pest loaded `Pest.php`
- **Starter:** Exclude `vendor/**` from Vitest — the framework's Node-only editor test was being collected into the browser suite
- Remove unpaired `restore_error_handler()` / `restore_exception_handler()` calls in `DispatchHelperTest`; nothing installs handlers any more, so they popped PHPUnit's own and PHPUnit 13 fails the run as risky
- Replace `->method()->with()` with `->expects($this->once())->method()->with()` in `RenderApiTest`, deprecated in PHPUnit 13 and removed in 14
- Fix CI lint/analyse failures by removing obsolete `mago:install-binary` step ([3699f4d], [#111])

## [0.4.1] - 2026-02-10

### Changed

- **Installer:** Copy `.env.example` to `.env` during `composer install` ([aabcca6], [#86])
- **Starter:** Remove `.env` hook from DDEV config ([aabcca6], [#86])
- **Starter:** Remove project name from DDEV config to inherit from folder ([8525092])

### Fixed

- **Starter:** Add `index.php` file required by WordPress for standalone themes ([df9c428], [#85])
- **Starter:** Disable DDEV settings management ([df9c428], [#85])
- **Starter:** Refactor menus and image sizes to use dedicated classes ([df9c428], [#85])
- **Starter:** Fix `FoehnConfig` parameter name (`discoveryCacheStrategy`) ([df9c428], [#85])
- **Starter:** Fix controllers to implement `TemplateControllerInterface` ([df9c428], [#85])
- **Starter:** Fix taxonomies to extend `Timber\Term` ([df9c428], [#85])
- **Starter:** Add `front-page` to ArchiveController templates ([df9c428], [#85])
- **Starter:** Fix deprecated `post.preview` usage in card-post template ([df9c428], [#85])
- **Starter:** Move `index.php` to correct theme folder ([f1526a3])

## [0.4.0] - 2026-02-10

### Added

- Transform repository into a monorepo with three packages ([8919d50], [#83]):
  - `studiometa/foehn` — core framework (moved to `packages/foehn/`)
  - `studiometa/foehn-installer` — Composer plugin that generates WordPress web root, symlinks, and wp-config.php ([76b2597])
  - `studiometa/foehn-starter` — starter theme with models, taxonomies, hooks, controllers, templates, and DDEV config ([5e5e3a0])
- Add `GenericLoginErrors` security hook to prevent username enumeration on login ([7825d3a])
- Add `vlucas/phpdotenv` as framework dependency for `.env` file loading ([42e807c])
- Add monorepo split CI workflow to distribute packages to read-only repos on tag push ([770a69f], [3231e72])
- Add DDEV configuration for starter theme with automated WordPress setup ([0e43f8b])

### Changed

- **BREAKING:** Repository renamed from `studiometa/foehn` to `studiometa/foehn-framework` ([960692a])
- Starter theme follows documented conventions: `Controllers/`, `ContextProviders/`, `Taxonomies/` separate from `Models/`, `templates/` with `layouts/components/pages/` ([456f5d1], [f59a3ba])
- Update all documentation to use `templates/` directory and `Taxonomies/` namespace ([30e76fd])
- Update Mago guard rules: `Models` restricted to `Timber\Post`, new `Taxonomies` rule ([30e76fd])

## [0.3.0] - 2026-02-09

### Added

- Add `Cache::tags()` for tagged cache invalidation ([a149b4f], [#78])
- Add `DiscoveryLocation` and `WpDiscoveryItems` for location-aware discovery ([3d0cd34], [#79])
- Add `ClassScanner` for dedicated PSR-4 class scanning ([553efa7], [#79])
- Add `QueryFiltersConfig` and `QueryFiltersHook` for URL-based archive filtering ([b587b62], [#77])
- Add `QueryExtension` with `query_*` Twig helpers for filter UI building ([b587b62], [#77])
- Add Render API REST endpoint for cacheable template rendering via AJAX ([504fe3b], [#67])
- Make `FoehnConfig` discoverable via `app/foehn.config.php` ([4cab60d], [#80])
- Add API documentation for all config classes, discovery system, and view engine ([20246ee], [#80])
- Add configuration and custom discovery guides ([4a35cb2], [#80])
- Add comprehensive migration guide from wp-toolkit to Føhn ([6fa9cc8], [#81])

### Changed

- **BREAKING:** Align `WpDiscovery` interface with Tempest conventions: `discover()` now receives `DiscoveryLocation`, items managed via `WpDiscoveryItems` ([3d0cd34], [#79])
- **BREAKING:** Discovery cache format changed to location-grouped structure (`array<string, array<string, list<array>>>`) ([3d0cd34], [#79])

### Fixed

- Fix user config files being overwritten by framework defaults ([73d9443], [#74])

[#89]: https://github.com/studiometa/foehn-framework/pull/89
[#91]: https://github.com/studiometa/foehn-framework/pull/91
[#96]: https://github.com/studiometa/foehn-framework/pull/96
[#97]: https://github.com/studiometa/foehn-framework/pull/97
[#98]: https://github.com/studiometa/foehn-framework/pull/98
[#100]: https://github.com/studiometa/foehn-framework/pull/100
[#101]: https://github.com/studiometa/foehn-framework/pull/101
[#107]: https://github.com/studiometa/foehn-framework/pull/107
[#110]: https://github.com/studiometa/foehn-framework/pull/110
[#111]: https://github.com/studiometa/foehn-framework/pull/111
[#118]: https://github.com/studiometa/foehn-framework/pull/118
[#119]: https://github.com/studiometa/foehn-framework/pull/119
[#120]: https://github.com/studiometa/foehn-framework/pull/120
[#121]: https://github.com/studiometa/foehn-framework/pull/121
[#122]: https://github.com/studiometa/foehn-framework/pull/122
[#123]: https://github.com/studiometa/foehn-framework/pull/123
[#124]: https://github.com/studiometa/foehn-framework/pull/124
[#125]: https://github.com/studiometa/foehn-framework/pull/125
[#126]: https://github.com/studiometa/foehn-framework/pull/126
[#127]: https://github.com/studiometa/foehn-framework/pull/127
[#128]: https://github.com/studiometa/foehn-framework/pull/128
[#129]: https://github.com/studiometa/foehn-framework/pull/129
[#130]: https://github.com/studiometa/foehn-framework/pull/130
[#133]: https://github.com/studiometa/foehn-framework/pull/133
[#135]: https://github.com/studiometa/foehn-framework/pull/135
[#137]: https://github.com/studiometa/foehn-framework/pull/137
[#138]: https://github.com/studiometa/foehn-framework/pull/138
[0.5.0]: https://github.com/studiometa/foehn-framework/releases/tag/0.5.0
[0.4.1]: https://github.com/studiometa/foehn-framework/releases/tag/0.4.1
[aabcca6]: https://github.com/studiometa/foehn-framework/commit/aabcca6
[8525092]: https://github.com/studiometa/foehn-framework/commit/8525092
[f1526a3]: https://github.com/studiometa/foehn-framework/commit/f1526a3
[3231e72]: https://github.com/studiometa/foehn-framework/commit/3231e72
[0.4.0]: https://github.com/studiometa/foehn-framework/releases/tag/0.4.0
[8919d50]: https://github.com/studiometa/foehn-framework/commit/8919d50
[#83]: https://github.com/studiometa/foehn-framework/pull/83
[76b2597]: https://github.com/studiometa/foehn-framework/commit/76b2597
[5e5e3a0]: https://github.com/studiometa/foehn-framework/commit/5e5e3a0
[7825d3a]: https://github.com/studiometa/foehn-framework/commit/7825d3a
[42e807c]: https://github.com/studiometa/foehn-framework/commit/42e807c
[770a69f]: https://github.com/studiometa/foehn-framework/commit/770a69f
[0e43f8b]: https://github.com/studiometa/foehn-framework/commit/0e43f8b
[960692a]: https://github.com/studiometa/foehn-framework/commit/960692a
[456f5d1]: https://github.com/studiometa/foehn-framework/commit/456f5d1
[f59a3ba]: https://github.com/studiometa/foehn-framework/commit/f59a3ba
[30e76fd]: https://github.com/studiometa/foehn-framework/commit/30e76fd
[a149b4f]: https://github.com/studiometa/foehn-framework/commit/a149b4f
[#78]: https://github.com/studiometa/foehn-framework/pull/78
[b587b62]: https://github.com/studiometa/foehn-framework/commit/b587b62
[b587b62]: https://github.com/studiometa/foehn-framework/commit/b587b62
[#77]: https://github.com/studiometa/foehn-framework/pull/77
[504fe3b]: https://github.com/studiometa/foehn-framework/commit/504fe3b
[#67]: https://github.com/studiometa/foehn-framework/pull/67
[3d0cd34]: https://github.com/studiometa/foehn-framework/commit/3d0cd34
[#79]: https://github.com/studiometa/foehn-framework/pull/79
[20246ee]: https://github.com/studiometa/foehn-framework/commit/20246ee
[4cab60d]: https://github.com/studiometa/foehn-framework/commit/4cab60d
[4a35cb2]: https://github.com/studiometa/foehn-framework/commit/4a35cb2
[#80]: https://github.com/studiometa/foehn-framework/pull/80
[6fa9cc8]: https://github.com/studiometa/foehn-framework/commit/6fa9cc8
[553efa7]: https://github.com/studiometa/foehn-framework/commit/553efa7
[#81]: https://github.com/studiometa/foehn-framework/pull/81
[73d9443]: https://github.com/studiometa/foehn-framework/commit/73d9443
[#74]: https://github.com/studiometa/foehn-framework/pull/74
[0.3.0]: https://github.com/studiometa/foehn-framework/releases/tag/0.3.0

## [0.2.4] - 2026-02-09

### Fixed

- Include Timber global context (`site`, `theme`, `user`, etc.) in `TimberViewEngine` ([ce9e046], [#66])

[ce9e046]: https://github.com/studiometa/foehn-framework/commit/ce9e046
[#66]: https://github.com/studiometa/foehn-framework/pull/66
[0.2.4]: https://github.com/studiometa/foehn-framework/releases/tag/0.2.4

## [0.2.3] - 2026-02-05

### Added

- Add `BlockMarkupExtension` with `wp_block_start()`, `wp_block_end()` and `wp_block()` Twig functions for block pattern templates ([66d1b3d], [#63])
- Add `Cache` helper for WordPress transients with `remember()` pattern ([b68b0d1], [#64])
- Add `Log` helper for debug logging with PSR-3 style levels ([b68b0d1], [#64])

### Removed

- Remove `Validator` helper, recommend third-party packages instead ([0c1aa8f])

### Fixed

- Fix static analysis issues ([fd7f1d3])
- Fix VitePress build by escaping Twig syntax in docs ([1b5a3bb])

[66d1b3d]: https://github.com/studiometa/foehn-framework/commit/66d1b3d
[#63]: https://github.com/studiometa/foehn-framework/pull/63
[b68b0d1]: https://github.com/studiometa/foehn-framework/commit/b68b0d1
[#64]: https://github.com/studiometa/foehn-framework/pull/64
[0c1aa8f]: https://github.com/studiometa/foehn-framework/commit/0c1aa8f
[fd7f1d3]: https://github.com/studiometa/foehn-framework/commit/fd7f1d3
[1b5a3bb]: https://github.com/studiometa/foehn-framework/commit/1b5a3bb
[0.2.3]: https://github.com/studiometa/foehn-framework/releases/tag/0.2.3

## [0.2.2] - 2026-02-05

### Added

- Add `WebpackManifest` helper for enqueuing assets from `@studiometa/webpack-config` manifests ([0898fce], [#60])
- Bundle `studiometa/twig-toolkit` extension with `html_classes()`, `html_styles()`, `html_attributes()` and `{% element %}` tag ([13e1f56], [#62])

[0898fce]: https://github.com/studiometa/foehn-framework/commit/0898fce
[#60]: https://github.com/studiometa/foehn-framework/pull/60
[13e1f56]: https://github.com/studiometa/foehn-framework/commit/13e1f56
[#62]: https://github.com/studiometa/foehn-framework/pull/62
[0.2.2]: https://github.com/studiometa/foehn-framework/releases/tag/0.2.2

## [0.2.1] - 2026-02-05

### Added

- Add `WP` helper for typed access to WordPress globals (`WP::db()`, `WP::query()`, `WP::post()`, `WP::user()`) ([4f8bfc4], [#58])
- Add `Env` helper for environment detection (`Env::isProduction()`, `Env::isDevelopment()`, `Env::isDebug()`) ([4f8bfc4], [#58])
- Add `#[AsTwigExtension]` attribute for declarative Twig extension registration ([3fcddec], [#53])

### Fixed

- Fix `ViewEngineInterface` not registered in DI container for constructor injection ([c00db03], [#57])

[3fcddec]: https://github.com/studiometa/foehn-framework/commit/3fcddec
[#53]: https://github.com/studiometa/foehn-framework/pull/53
[c00db03]: https://github.com/studiometa/foehn-framework/commit/c00db03
[#57]: https://github.com/studiometa/foehn-framework/pull/57
[4f8bfc4]: https://github.com/studiometa/foehn-framework/commit/4f8bfc4
[#58]: https://github.com/studiometa/foehn-framework/pull/58
[0.2.1]: https://github.com/studiometa/foehn-framework/releases/tag/0.2.1

## [0.2.0] - 2026-02-05

### Added

- Add bundled Mago config for theme conventions enforcement ([cbeb0d9], [#52])
- Add enhanced CLI scaffolding commands with `--dry-run` support ([ca3fb53], [#51])
- Add `#[AsImageSize]` attribute for declarative image size registration with auto theme support ([d2eb7b6], [#47])
- Add `#[AsAcfOptionsPage]` attribute for ACF options pages with auto-discovery and `AcfOptionsService` helper ([ab97f93], [#49])
- Add `#[AsAcfFieldGroup]` attribute for non-block ACF field groups with simplified location syntax ([01bdd55], [#48])
- Add `#[AsMenu]` attribute for declarative navigation menu registration with auto-context injection ([c6dcd19], [#46])
- Add theme conventions documentation with directory structure, naming rules, and migration guide ([bef4275], [#43])
- Add `DisableBlockStyles` cleanup hook to dequeue Gutenberg block styles ([29747e3], [#44])
- Add built-in ACF field fragments for reusable field groups ([b64002d], [#45]):
  - `ButtonLinkBuilder`: link with style/size options
  - `ResponsiveImageBuilder`: desktop/mobile image variants
  - `SpacingBuilder`: padding top/bottom controls
  - `BackgroundBuilder`: color, image, and overlay background

### Changed

- **BREAKING:** Rename ViewComposer to ContextProvider ([70164d6], [#50])
  - `#[AsViewComposer]` → `#[AsContextProvider]`
  - `ViewComposerInterface` → `ContextProviderInterface`
  - `compose()` method → `provide()` method
  - `ViewComposerRegistry` → `ContextProviderRegistry`
  - `ViewComposerDiscovery` → `ContextProviderDiscovery`
  - `make:view-composer` CLI → `make:context-provider`

[cbeb0d9]: https://github.com/studiometa/foehn-framework/commit/cbeb0d9
[#52]: https://github.com/studiometa/foehn-framework/pull/52
[ca3fb53]: https://github.com/studiometa/foehn-framework/commit/ca3fb53
[#51]: https://github.com/studiometa/foehn-framework/pull/51
[70164d6]: https://github.com/studiometa/foehn-framework/commit/70164d6
[#50]: https://github.com/studiometa/foehn-framework/pull/50
[d2eb7b6]: https://github.com/studiometa/foehn-framework/commit/d2eb7b6
[#47]: https://github.com/studiometa/foehn-framework/pull/47
[ab97f93]: https://github.com/studiometa/foehn-framework/commit/ab97f93
[#49]: https://github.com/studiometa/foehn-framework/pull/49
[01bdd55]: https://github.com/studiometa/foehn-framework/commit/01bdd55
[#48]: https://github.com/studiometa/foehn-framework/pull/48
[c6dcd19]: https://github.com/studiometa/foehn-framework/commit/c6dcd19
[#46]: https://github.com/studiometa/foehn-framework/pull/46
[bef4275]: https://github.com/studiometa/foehn-framework/commit/bef4275
[#43]: https://github.com/studiometa/foehn-framework/pull/43
[29747e3]: https://github.com/studiometa/foehn-framework/commit/29747e3
[#44]: https://github.com/studiometa/foehn-framework/pull/44
[b64002d]: https://github.com/studiometa/foehn-framework/commit/b64002d
[#45]: https://github.com/studiometa/foehn-framework/pull/45
[0.2.0]: https://github.com/studiometa/foehn-framework/releases/tag/0.2.0

## [0.1.0] - 2026-02-04

### Changed

- REST routes without explicit permission now require `edit_posts` capability instead of just authentication ([2d95397], [#32])

### Added

- Add `debug` config option for logging discovery failures via `trigger_error()` ([72ab351], [#31])
- Add `ValidatesFields` trait for optional ACF block field validation ([f4854d1], [#24])
- Add `rest_default_capability` config option to customize default REST route permission ([2d95397], [#32])
- Add `discovery:warm` CLI command to pre-warm discovery cache during deployment ([a2cab24], [#30])
- Add security documentation for shortcode output escaping with comprehensive XSS prevention guide ([ab0445b], [#29])
- Transform ACF block fields via Timber's ACF integration ([3654df6], [!19]):
  - Transforms raw ACF values (image IDs, post IDs) to Timber objects
  - Supports: image, gallery, file, post_object, relationship, taxonomy, user, date_picker
  - Handles nested fields recursively (repeater, flexible_content, group)
  - New `acf_transform_fields` config option (default: true) to enable/disable
- Add `make:controller` command to scaffold template controllers ([aa08615], [#20])
- Add `make:hooks` command to scaffold hook classes ([aa08615], [#20])
- Add `--fields` flag to `make:acf-block` for auto-generating ACF fields ([aa08615], [#20])
- Add `VideoEmbed` helper and Twig extension for YouTube/Vimeo URL transformation ([3b34300], [#18])
- Add opt-in reusable hook classes for common WordPress patterns ([ff3b2b3], [#13]):
  - Cleanup: `CleanHeadTags`, `CleanContent`, `CleanImageSizes`, `DisableEmoji`, `DisableFeeds`, `DisableOembed`, `DisableGlobalStyles`
  - Security: `SecurityHeaders`, `DisableVersionDisclosure`, `DisableXmlRpc`, `DisableFileEditor`, `RestApiAuth`
  - GDPR: `YouTubeNoCookieHooks`
- Add `hooks` config option in `Kernel::boot()` to activate opt-in hook classes ([ff3b2b3], [#13])
- Add `#[AsTimberModel]` attribute for Timber class map registration without post type/taxonomy registration ([c3ebb04], [#11])
- Auto-initialize Timber in Kernel bootstrap with `timber_templates_dir` config option ([c3bf7df], [#12])
- Add `hierarchical`, `menuPosition`, `labels`, `rewrite` (array|false|null) to `#[AsPostType]` ([b544790], [#7])
- Add `labels`, `rewrite` (array|false|null) to `#[AsTaxonomy]` ([b544790], [#7])
- Add WordPress function stubs for unit testing `apply()` code paths ([812aa6a], [#7])
- Add `discover()` and `apply()` tests for all 11 discovery classes — 359 tests, 1067 assertions ([d7cbe4c], [#7])
- Add discovery cache for production performance ([ffc7536], [#2])
- Add VitePress documentation with guides and API reference ([f69a8b9], [#3])
- Document `#[AsTimberModel]`, `timber_templates_dir`, `hooks` config, and built-in hooks ([a7bde4e], [!17])
- Document `VideoEmbed` helper, ACF field transformation, and `make:controller`/`make:hooks` CLI commands ([3b34300], [!21])
- Add GitHub Pages deployment workflow ([e1178b9], [#3])

### Changed

- Decouple discoveries from Tempest's `Discovery` interface, replace with `WpDiscovery` + `IsWpDiscovery` ([748aace], [#7])
- Rewrite `DiscoveryRunner` to own the full lifecycle: class scanning via Composer PSR-4, phased `apply()` at correct WP hooks ([509febf], [#7])
- Tempest is now used only for the DI container, not for discovery ([509febf], [#7])

### Fixed

- Fix discovery system conflicts with Tempest lifecycle — double discovery, incorrect timing, uninitialized properties ([748aace], [#7])
- Fix root path passed to Tempest causing "Could not locate composer.json" error ([26cb117], [#5])

[c3ebb04]: https://github.com/studiometa/foehn-framework/commit/c3ebb04
[c3bf7df]: https://github.com/studiometa/foehn-framework/commit/c3bf7df
[748aace]: https://github.com/studiometa/foehn-framework/commit/748aace
[26cb117]: https://github.com/studiometa/foehn-framework/commit/26cb117
[509febf]: https://github.com/studiometa/foehn-framework/commit/509febf
[b544790]: https://github.com/studiometa/foehn-framework/commit/b544790
[812aa6a]: https://github.com/studiometa/foehn-framework/commit/812aa6a
[d7cbe4c]: https://github.com/studiometa/foehn-framework/commit/d7cbe4c
[ffc7536]: https://github.com/studiometa/foehn-framework/commit/ffc7536
[f69a8b9]: https://github.com/studiometa/foehn-framework/commit/f69a8b9
[e1178b9]: https://github.com/studiometa/foehn-framework/commit/e1178b9
[#2]: https://github.com/studiometa/foehn-framework/pull/2
[#3]: https://github.com/studiometa/foehn-framework/pull/3
[#5]: https://github.com/studiometa/foehn-framework/pull/5
[#7]: https://github.com/studiometa/foehn-framework/pull/7
[#11]: https://github.com/studiometa/foehn-framework/pull/11
[#12]: https://github.com/studiometa/foehn-framework/pull/12
[#13]: https://github.com/studiometa/foehn-framework/pull/13
[#18]: https://github.com/studiometa/foehn-framework/pull/18
[#20]: https://github.com/studiometa/foehn-framework/pull/20
[!19]: https://github.com/studiometa/foehn-framework/pull/19
[ff3b2b3]: https://github.com/studiometa/foehn-framework/commit/ff3b2b3
[3654df6]: https://github.com/studiometa/foehn-framework/commit/3654df6
[3b34300]: https://github.com/studiometa/foehn-framework/commit/3b34300
[aa08615]: https://github.com/studiometa/foehn-framework/commit/aa08615
[aa08615]: https://github.com/studiometa/foehn-framework/commit/aa08615
[aa08615]: https://github.com/studiometa/foehn-framework/commit/aa08615
[a7bde4e]: https://github.com/studiometa/foehn-framework/commit/a7bde4e
[!17]: https://github.com/studiometa/foehn-framework/pull/17
[!21]: https://github.com/studiometa/foehn-framework/pull/21
[3b34300]: https://github.com/studiometa/foehn-framework/commit/3b34300
[ab0445b]: https://github.com/studiometa/foehn-framework/commit/ab0445b
[#29]: https://github.com/studiometa/foehn-framework/pull/29
[a2cab24]: https://github.com/studiometa/foehn-framework/commit/a2cab24
[#30]: https://github.com/studiometa/foehn-framework/pull/30
[2d95397]: https://github.com/studiometa/foehn-framework/commit/2d95397
[2d95397]: https://github.com/studiometa/foehn-framework/commit/2d95397
[#32]: https://github.com/studiometa/foehn-framework/pull/32
[f4854d1]: https://github.com/studiometa/foehn-framework/commit/f4854d1
[#24]: https://github.com/studiometa/foehn-framework/pull/33
[72ab351]: https://github.com/studiometa/foehn-framework/commit/72ab351
[#31]: https://github.com/studiometa/foehn-framework/pull/31
[0.1.0]: https://github.com/studiometa/foehn-framework/releases/tag/0.1.0
[#85]: https://github.com/studiometa/foehn-framework/pull/85
[df9c428]: https://github.com/studiometa/foehn-framework/commit/df9c428
[#86]: https://github.com/studiometa/foehn-framework/pull/86
