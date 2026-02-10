# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.4.0] - 2026-02-10

### Added

- Transform repository into a monorepo with three packages ([3c65232], [#83]):
  - `studiometa/foehn` — core framework (moved to `packages/foehn/`)
  - `studiometa/foehn-installer` — Composer plugin that generates WordPress web root, symlinks, and wp-config.php ([fd46199])
  - `studiometa/foehn-starter` — starter theme with models, taxonomies, hooks, controllers, templates, and DDEV config ([48d7ce0])
- Add `GenericLoginErrors` security hook to prevent username enumeration on login ([74ca34f])
- Add `vlucas/phpdotenv` as framework dependency for `.env` file loading ([4a467ea])
- Add monorepo split CI workflow to distribute packages to read-only repos on tag push ([03365b6], [d185927])
- Add DDEV configuration for starter theme with automated WordPress setup ([41bc400])

### Changed

- **BREAKING:** Repository renamed from `studiometa/foehn` to `studiometa/foehn-framework` ([09f8e64])
- Starter theme follows documented conventions: `Controllers/`, `ContextProviders/`, `Taxonomies/` separate from `Models/`, `templates/` with `layouts/components/pages/` ([07d8858], [cf0ff30])
- Update all documentation to use `templates/` directory and `Taxonomies/` namespace ([ed29249])
- Update Mago guard rules: `Models` restricted to `Timber\Post`, new `Taxonomies` rule ([ed29249])

## [0.3.0] - 2026-02-09

### Added

- Add `Cache::tags()` for tagged cache invalidation ([43f1f95], [#78])
- Add `DiscoveryLocation` and `WpDiscoveryItems` for location-aware discovery ([758b19f], [#79])
- Add `ClassScanner` for dedicated PSR-4 class scanning ([50466bf], [#79])
- Add `QueryFiltersConfig` and `QueryFiltersHook` for URL-based archive filtering ([a487181], [#77])
- Add `QueryExtension` with `query_*` Twig helpers for filter UI building ([5911c2c], [#77])
- Add Render API REST endpoint for cacheable template rendering via AJAX ([7d9f33a], [#67])
- Make `FoehnConfig` discoverable via `app/foehn.config.php` ([0aaafb3], [#80])
- Add API documentation for all config classes, discovery system, and view engine ([556dd93], [#80])
- Add configuration and custom discovery guides ([59f39e3], [#80])
- Add comprehensive migration guide from wp-toolkit to Føhn ([bc3227a], [#81])

### Changed

- **BREAKING:** Align `WpDiscovery` interface with Tempest conventions: `discover()` now receives `DiscoveryLocation`, items managed via `WpDiscoveryItems` ([758b19f], [#79])
- **BREAKING:** Discovery cache format changed to location-grouped structure (`array<string, array<string, list<array>>>`) ([758b19f], [#79])

### Fixed

- Fix user config files being overwritten by framework defaults ([95ba3d7], [#74])

[d185927]: https://github.com/studiometa/foehn-framework/commit/d185927
[0.4.0]: https://github.com/studiometa/foehn-framework/releases/tag/0.4.0
[3c65232]: https://github.com/studiometa/foehn-framework/commit/3c65232
[#83]: https://github.com/studiometa/foehn-framework/pull/83
[fd46199]: https://github.com/studiometa/foehn-framework/commit/fd46199
[48d7ce0]: https://github.com/studiometa/foehn-framework/commit/48d7ce0
[74ca34f]: https://github.com/studiometa/foehn-framework/commit/74ca34f
[4a467ea]: https://github.com/studiometa/foehn-framework/commit/4a467ea
[03365b6]: https://github.com/studiometa/foehn-framework/commit/03365b6
[41bc400]: https://github.com/studiometa/foehn-framework/commit/41bc400
[09f8e64]: https://github.com/studiometa/foehn-framework/commit/09f8e64
[07d8858]: https://github.com/studiometa/foehn-framework/commit/07d8858
[cf0ff30]: https://github.com/studiometa/foehn-framework/commit/cf0ff30
[ed29249]: https://github.com/studiometa/foehn-framework/commit/ed29249
[43f1f95]: https://github.com/studiometa/foehn-framework/commit/43f1f95
[#78]: https://github.com/studiometa/foehn-framework/pull/78
[a487181]: https://github.com/studiometa/foehn-framework/commit/a487181
[5911c2c]: https://github.com/studiometa/foehn-framework/commit/5911c2c
[#77]: https://github.com/studiometa/foehn-framework/pull/77
[7d9f33a]: https://github.com/studiometa/foehn-framework/commit/7d9f33a
[#67]: https://github.com/studiometa/foehn-framework/pull/67
[758b19f]: https://github.com/studiometa/foehn-framework/commit/758b19f
[#79]: https://github.com/studiometa/foehn-framework/pull/79
[556dd93]: https://github.com/studiometa/foehn-framework/commit/556dd93
[0aaafb3]: https://github.com/studiometa/foehn-framework/commit/0aaafb3
[59f39e3]: https://github.com/studiometa/foehn-framework/commit/59f39e3
[#80]: https://github.com/studiometa/foehn-framework/pull/80
[bc3227a]: https://github.com/studiometa/foehn-framework/commit/bc3227a
[50466bf]: https://github.com/studiometa/foehn-framework/commit/50466bf
[#81]: https://github.com/studiometa/foehn-framework/pull/81
[95ba3d7]: https://github.com/studiometa/foehn-framework/commit/95ba3d7
[#74]: https://github.com/studiometa/foehn-framework/pull/74
[0.3.0]: https://github.com/studiometa/foehn-framework/releases/tag/0.3.0

## [0.2.4] - 2026-02-09

### Fixed

- Include Timber global context (`site`, `theme`, `user`, etc.) in `TimberViewEngine` ([af343d8], [#66])

[af343d8]: https://github.com/studiometa/foehn-framework/commit/af343d8
[#66]: https://github.com/studiometa/foehn-framework/pull/66
[0.2.4]: https://github.com/studiometa/foehn-framework/releases/tag/0.2.4

## [0.2.3] - 2026-02-05

### Added

- Add `BlockMarkupExtension` with `wp_block_start()`, `wp_block_end()` and `wp_block()` Twig functions for block pattern templates ([b94e17c], [#63])
- Add `Cache` helper for WordPress transients with `remember()` pattern ([aa974a8], [#64])
- Add `Log` helper for debug logging with PSR-3 style levels ([aa974a8], [#64])

### Removed

- Remove `Validator` helper, recommend third-party packages instead ([54bfe25])

### Fixed

- Fix static analysis issues ([76a4828])
- Fix VitePress build by escaping Twig syntax in docs ([90e4906])

[b94e17c]: https://github.com/studiometa/foehn-framework/commit/b94e17c
[#63]: https://github.com/studiometa/foehn-framework/pull/63
[aa974a8]: https://github.com/studiometa/foehn-framework/commit/aa974a8
[#64]: https://github.com/studiometa/foehn-framework/pull/64
[54bfe25]: https://github.com/studiometa/foehn-framework/commit/54bfe25
[76a4828]: https://github.com/studiometa/foehn-framework/commit/76a4828
[90e4906]: https://github.com/studiometa/foehn-framework/commit/90e4906
[0.2.3]: https://github.com/studiometa/foehn-framework/releases/tag/0.2.3

## [0.2.2] - 2026-02-05

### Added

- Add `WebpackManifest` helper for enqueuing assets from `@studiometa/webpack-config` manifests ([edd6216], [#60])
- Bundle `studiometa/twig-toolkit` extension with `html_classes()`, `html_styles()`, `html_attributes()` and `{% element %}` tag ([5a7b8cf], [#62])

[edd6216]: https://github.com/studiometa/foehn-framework/commit/edd6216
[#60]: https://github.com/studiometa/foehn-framework/pull/60
[5a7b8cf]: https://github.com/studiometa/foehn-framework/commit/5a7b8cf
[#62]: https://github.com/studiometa/foehn-framework/pull/62
[0.2.2]: https://github.com/studiometa/foehn-framework/releases/tag/0.2.2

## [0.2.1] - 2026-02-05

### Added

- Add `WP` helper for typed access to WordPress globals (`WP::db()`, `WP::query()`, `WP::post()`, `WP::user()`) ([db95247], [#58])
- Add `Env` helper for environment detection (`Env::isProduction()`, `Env::isDevelopment()`, `Env::isDebug()`) ([db95247], [#58])
- Add `#[AsTwigExtension]` attribute for declarative Twig extension registration ([63edb11], [#53])

### Fixed

- Fix `ViewEngineInterface` not registered in DI container for constructor injection ([1f5db42], [#57])

[63edb11]: https://github.com/studiometa/foehn-framework/commit/63edb11
[#53]: https://github.com/studiometa/foehn-framework/pull/53
[1f5db42]: https://github.com/studiometa/foehn-framework/commit/1f5db42
[#57]: https://github.com/studiometa/foehn-framework/pull/57
[db95247]: https://github.com/studiometa/foehn-framework/commit/db95247
[#58]: https://github.com/studiometa/foehn-framework/pull/58
[0.2.1]: https://github.com/studiometa/foehn-framework/releases/tag/0.2.1

## [0.2.0] - 2026-02-05

### Added

- Add bundled Mago config for theme conventions enforcement ([329c89b], [#52])
- Add enhanced CLI scaffolding commands with `--dry-run` support ([4e0f58b], [#51])
- Add `#[AsImageSize]` attribute for declarative image size registration with auto theme support ([614faa0], [#47])
- Add `#[AsAcfOptionsPage]` attribute for ACF options pages with auto-discovery and `AcfOptionsService` helper ([4b52d3d], [#49])
- Add `#[AsAcfFieldGroup]` attribute for non-block ACF field groups with simplified location syntax ([296e69f], [#48])
- Add `#[AsMenu]` attribute for declarative navigation menu registration with auto-context injection ([2ce9f77], [#46])
- Add theme conventions documentation with directory structure, naming rules, and migration guide ([7f180c4], [#43])
- Add `DisableBlockStyles` cleanup hook to dequeue Gutenberg block styles ([a6152ef], [#44])
- Add built-in ACF field fragments for reusable field groups ([0b1c707], [#45]):
  - `ButtonLinkBuilder`: link with style/size options
  - `ResponsiveImageBuilder`: desktop/mobile image variants
  - `SpacingBuilder`: padding top/bottom controls
  - `BackgroundBuilder`: color, image, and overlay background

### Changed

- **BREAKING:** Rename ViewComposer to ContextProvider ([8a4c503], [#50])
  - `#[AsViewComposer]` → `#[AsContextProvider]`
  - `ViewComposerInterface` → `ContextProviderInterface`
  - `compose()` method → `provide()` method
  - `ViewComposerRegistry` → `ContextProviderRegistry`
  - `ViewComposerDiscovery` → `ContextProviderDiscovery`
  - `make:view-composer` CLI → `make:context-provider`

[329c89b]: https://github.com/studiometa/foehn-framework/commit/329c89b
[#52]: https://github.com/studiometa/foehn-framework/pull/52
[4e0f58b]: https://github.com/studiometa/foehn-framework/commit/4e0f58b
[#51]: https://github.com/studiometa/foehn-framework/pull/51
[8a4c503]: https://github.com/studiometa/foehn-framework/commit/8a4c503
[#50]: https://github.com/studiometa/foehn-framework/pull/50
[614faa0]: https://github.com/studiometa/foehn-framework/commit/614faa0
[#47]: https://github.com/studiometa/foehn-framework/pull/47
[4b52d3d]: https://github.com/studiometa/foehn-framework/commit/4b52d3d
[#49]: https://github.com/studiometa/foehn-framework/pull/49
[296e69f]: https://github.com/studiometa/foehn-framework/commit/296e69f
[#48]: https://github.com/studiometa/foehn-framework/pull/48
[2ce9f77]: https://github.com/studiometa/foehn-framework/commit/2ce9f77
[#46]: https://github.com/studiometa/foehn-framework/pull/46
[7f180c4]: https://github.com/studiometa/foehn-framework/commit/7f180c4
[#43]: https://github.com/studiometa/foehn-framework/pull/43
[a6152ef]: https://github.com/studiometa/foehn-framework/commit/a6152ef
[#44]: https://github.com/studiometa/foehn-framework/pull/44
[0b1c707]: https://github.com/studiometa/foehn-framework/commit/0b1c707
[#45]: https://github.com/studiometa/foehn-framework/pull/45
[0.2.0]: https://github.com/studiometa/foehn-framework/releases/tag/0.2.0

## [0.1.0] - 2026-02-04

### Changed

- REST routes without explicit permission now require `edit_posts` capability instead of just authentication ([cb284f8], [#32])

### Added

- Add `debug` config option for logging discovery failures via `trigger_error()` ([3d295e9], [#31])
- Add `ValidatesFields` trait for optional ACF block field validation ([04c283c], [#24])
- Add `rest_default_capability` config option to customize default REST route permission ([343e094], [#32])
- Add `discovery:warm` CLI command to pre-warm discovery cache during deployment ([685132d], [#30])
- Add security documentation for shortcode output escaping with comprehensive XSS prevention guide ([316cbff], [#29])
- Transform ACF block fields via Timber's ACF integration ([8e0d11e], [!19]):
  - Transforms raw ACF values (image IDs, post IDs) to Timber objects
  - Supports: image, gallery, file, post_object, relationship, taxonomy, user, date_picker
  - Handles nested fields recursively (repeater, flexible_content, group)
  - New `acf_transform_fields` config option (default: true) to enable/disable
- Add `make:controller` command to scaffold template controllers ([fe277ae], [#20])
- Add `make:hooks` command to scaffold hook classes ([2e98402], [#20])
- Add `--fields` flag to `make:acf-block` for auto-generating ACF fields ([45a067d], [#20])
- Add `VideoEmbed` helper and Twig extension for YouTube/Vimeo URL transformation ([c19eefb], [#18])
- Add opt-in reusable hook classes for common WordPress patterns ([1bac8e8], [#13]):
  - Cleanup: `CleanHeadTags`, `CleanContent`, `CleanImageSizes`, `DisableEmoji`, `DisableFeeds`, `DisableOembed`, `DisableGlobalStyles`
  - Security: `SecurityHeaders`, `DisableVersionDisclosure`, `DisableXmlRpc`, `DisableFileEditor`, `RestApiAuth`
  - GDPR: `YouTubeNoCookieHooks`
- Add `hooks` config option in `Kernel::boot()` to activate opt-in hook classes ([1bac8e8], [#13])
- Add `#[AsTimberModel]` attribute for Timber class map registration without post type/taxonomy registration ([b6fd69a], [#11])
- Auto-initialize Timber in Kernel bootstrap with `timber_templates_dir` config option ([fae5391], [#12])
- Add `hierarchical`, `menuPosition`, `labels`, `rewrite` (array|false|null) to `#[AsPostType]` ([b544790], [#7])
- Add `labels`, `rewrite` (array|false|null) to `#[AsTaxonomy]` ([b544790], [#7])
- Add WordPress function stubs for unit testing `apply()` code paths ([e3988c7], [#7])
- Add `discover()` and `apply()` tests for all 11 discovery classes — 359 tests, 1067 assertions ([d7cbe4c], [#7])
- Add discovery cache for production performance ([adc01ed], [#2])
- Add VitePress documentation with guides and API reference ([d80fe88], [#3])
- Document `#[AsTimberModel]`, `timber_templates_dir`, `hooks` config, and built-in hooks ([3ec60b1], [!17])
- Document `VideoEmbed` helper, ACF field transformation, and `make:controller`/`make:hooks` CLI commands ([433abae], [!21])
- Add GitHub Pages deployment workflow ([02d6425], [#3])

### Changed

- Decouple discoveries from Tempest's `Discovery` interface, replace with `WpDiscovery` + `IsWpDiscovery` ([748aace], [#7])
- Rewrite `DiscoveryRunner` to own the full lifecycle: class scanning via Composer PSR-4, phased `apply()` at correct WP hooks ([b3d5134], [#7])
- Tempest is now used only for the DI container, not for discovery ([b3d5134], [#7])

### Fixed

- Fix discovery system conflicts with Tempest lifecycle — double discovery, incorrect timing, uninitialized properties ([748aace], [#7])
- Fix root path passed to Tempest causing "Could not locate composer.json" error ([f0b4f27], [#5])

[b6fd69a]: https://github.com/studiometa/foehn-framework/commit/b6fd69a
[fae5391]: https://github.com/studiometa/foehn-framework/commit/fae5391
[748aace]: https://github.com/studiometa/foehn-framework/commit/748aace
[f0b4f27]: https://github.com/studiometa/foehn-framework/commit/f0b4f27
[b3d5134]: https://github.com/studiometa/foehn-framework/commit/b3d5134
[b544790]: https://github.com/studiometa/foehn-framework/commit/b544790
[e3988c7]: https://github.com/studiometa/foehn-framework/commit/e3988c7
[d7cbe4c]: https://github.com/studiometa/foehn-framework/commit/d7cbe4c
[adc01ed]: https://github.com/studiometa/foehn-framework/commit/adc01ed
[d80fe88]: https://github.com/studiometa/foehn-framework/commit/d80fe88
[02d6425]: https://github.com/studiometa/foehn-framework/commit/02d6425
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
[1bac8e8]: https://github.com/studiometa/foehn-framework/commit/1bac8e8
[8e0d11e]: https://github.com/studiometa/foehn-framework/commit/8e0d11e
[c19eefb]: https://github.com/studiometa/foehn-framework/commit/c19eefb
[fe277ae]: https://github.com/studiometa/foehn-framework/commit/fe277ae
[2e98402]: https://github.com/studiometa/foehn-framework/commit/2e98402
[45a067d]: https://github.com/studiometa/foehn-framework/commit/45a067d
[3ec60b1]: https://github.com/studiometa/foehn-framework/commit/3ec60b1
[!17]: https://github.com/studiometa/foehn-framework/pull/17
[!21]: https://github.com/studiometa/foehn-framework/pull/21
[433abae]: https://github.com/studiometa/foehn-framework/commit/433abae
[316cbff]: https://github.com/studiometa/foehn-framework/commit/316cbff
[#29]: https://github.com/studiometa/foehn-framework/pull/29
[685132d]: https://github.com/studiometa/foehn-framework/commit/685132d
[#30]: https://github.com/studiometa/foehn-framework/pull/30
[cb284f8]: https://github.com/studiometa/foehn-framework/commit/cb284f8
[343e094]: https://github.com/studiometa/foehn-framework/commit/343e094
[#32]: https://github.com/studiometa/foehn-framework/pull/32
[04c283c]: https://github.com/studiometa/foehn-framework/commit/04c283c
[#24]: https://github.com/studiometa/foehn-framework/pull/33
[3d295e9]: https://github.com/studiometa/foehn-framework/commit/3d295e9
[#31]: https://github.com/studiometa/foehn-framework/pull/31
[0.1.0]: https://github.com/studiometa/foehn-framework/releases/tag/0.1.0
