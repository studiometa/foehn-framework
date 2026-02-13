# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- **BREAKING:** Require PHP 8.5+ and Tempest Framework v3.0 ([#98])
- **BREAKING:** Remove static `Helpers\Log` helper — use Tempest Logger (`tempest/log`) instead ([#96])
- **BREAKING:** Remove static `Helpers\Cache` helper — use injectable `CacheInterface` instead ([#96])
- Replace `\Tempest\get()` with injected `Container` in `HookDiscovery` and `TwigExtensionDiscovery` ([#96])

### Added

- Add `CacheInterface` contract and `TransientCache` implementation for dependency injection ([#96])
- Add `TaggedCache` for tag-based cache invalidation via `CacheInterface::tags()` ([#96])
- Add `Arrayable` interface and `HasToArray` trait for typed DTO context composition ([#97])
- Add built-in DTOs for common ACF field patterns ([#97]):
  - `LinkData` — matches `ButtonLinkBuilder` output
  - `ImageData` — matches `ResponsiveImageBuilder` output  
  - `SpacingData` — matches `SpacingBuilder` output
- Widen `compose()` return type to `array|Arrayable` on block interfaces ([#97])
- **Starter:** Add Hero block example demonstrating DTO context composition ([#97])
- **New package:** `@studiometa/foehn-vite-plugin` — Vite plugin for front-end bundling ([#89]):
  - Glob input resolution for entry points
  - Vite manifest generation for asset versioning
  - Twig/PHP file watching with full reload
  - Hot file generation for dev server detection
  - Auto DDEV proxy configuration
- Add fluent `PostQueryBuilder` for null-safe query building ([#91], [#100]):
  - Accumulates `WP_Query` parameters with fluent API
  - Null-safe methods: `exclude()`, `page()`, `whereTax()`, `search()` skip empty values
  - Escape hatch: `set()` and `merge()` for any `WP_Query` parameter
  - Execution: `get()`, `first()`, `count()`, `exists()`
- Add `QueriesPostType` trait with `query()`, `all()`, `find()`, `first()`, `count()`, `exists()` ([#91], [#100])
- Add `PostTypeRegistry` for class-to-post-type mapping ([#91], [#100])
- Add `Foehn\Models\Post` and `Foehn\Models\Page` base classes with query support ([#91], [#100])
- Add Starter Theme documentation with quick start guide and feature overview ([#101])

[#101]: https://github.com/studiometa/foehn-framework/pull/101
[#100]: https://github.com/studiometa/foehn-framework/pull/100
[#98]: https://github.com/studiometa/foehn-framework/pull/98
[#97]: https://github.com/studiometa/foehn-framework/pull/97
[#96]: https://github.com/studiometa/foehn-framework/pull/96
[#91]: https://github.com/studiometa/foehn-framework/issues/91
[#89]: https://github.com/studiometa/foehn-framework/pull/89

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
