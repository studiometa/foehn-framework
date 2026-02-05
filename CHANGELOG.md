# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[329c89b]: https://github.com/studiometa/foehn/commit/329c89b
[#52]: https://github.com/studiometa/foehn/pull/52
[4e0f58b]: https://github.com/studiometa/foehn/commit/4e0f58b
[#51]: https://github.com/studiometa/foehn/pull/51
[8a4c503]: https://github.com/studiometa/foehn/commit/8a4c503
[#50]: https://github.com/studiometa/foehn/pull/50
[614faa0]: https://github.com/studiometa/foehn/commit/614faa0
[#47]: https://github.com/studiometa/foehn/pull/47
[4b52d3d]: https://github.com/studiometa/foehn/commit/4b52d3d
[#49]: https://github.com/studiometa/foehn/pull/49
[296e69f]: https://github.com/studiometa/foehn/commit/296e69f
[#48]: https://github.com/studiometa/foehn/pull/48
[2ce9f77]: https://github.com/studiometa/foehn/commit/2ce9f77
[#46]: https://github.com/studiometa/foehn/pull/46
[7f180c4]: https://github.com/studiometa/foehn/commit/7f180c4
[#43]: https://github.com/studiometa/foehn/pull/43
[a6152ef]: https://github.com/studiometa/foehn/commit/a6152ef
[#44]: https://github.com/studiometa/foehn/pull/44
[0b1c707]: https://github.com/studiometa/foehn/commit/0b1c707
[#45]: https://github.com/studiometa/foehn/pull/45
[0.2.0]: https://github.com/studiometa/foehn/releases/tag/0.2.0

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

[b6fd69a]: https://github.com/studiometa/foehn/commit/b6fd69a
[fae5391]: https://github.com/studiometa/foehn/commit/fae5391
[748aace]: https://github.com/studiometa/foehn/commit/748aace
[f0b4f27]: https://github.com/studiometa/foehn/commit/f0b4f27
[b3d5134]: https://github.com/studiometa/foehn/commit/b3d5134
[b544790]: https://github.com/studiometa/foehn/commit/b544790
[e3988c7]: https://github.com/studiometa/foehn/commit/e3988c7
[d7cbe4c]: https://github.com/studiometa/foehn/commit/d7cbe4c
[adc01ed]: https://github.com/studiometa/foehn/commit/adc01ed
[d80fe88]: https://github.com/studiometa/foehn/commit/d80fe88
[02d6425]: https://github.com/studiometa/foehn/commit/02d6425
[#2]: https://github.com/studiometa/foehn/pull/2
[#3]: https://github.com/studiometa/foehn/pull/3
[#5]: https://github.com/studiometa/foehn/pull/5
[#7]: https://github.com/studiometa/foehn/pull/7
[#11]: https://github.com/studiometa/foehn/pull/11
[#12]: https://github.com/studiometa/foehn/pull/12
[#13]: https://github.com/studiometa/foehn/pull/13
[#18]: https://github.com/studiometa/foehn/pull/18
[#20]: https://github.com/studiometa/foehn/pull/20
[!19]: https://github.com/studiometa/foehn/pull/19
[1bac8e8]: https://github.com/studiometa/foehn/commit/1bac8e8
[8e0d11e]: https://github.com/studiometa/foehn/commit/8e0d11e
[c19eefb]: https://github.com/studiometa/foehn/commit/c19eefb
[fe277ae]: https://github.com/studiometa/foehn/commit/fe277ae
[2e98402]: https://github.com/studiometa/foehn/commit/2e98402
[45a067d]: https://github.com/studiometa/foehn/commit/45a067d
[3ec60b1]: https://github.com/studiometa/foehn/commit/3ec60b1
[!17]: https://github.com/studiometa/foehn/pull/17
[!21]: https://github.com/studiometa/foehn/pull/21
[433abae]: https://github.com/studiometa/foehn/commit/433abae
[316cbff]: https://github.com/studiometa/foehn/commit/316cbff
[#29]: https://github.com/studiometa/foehn/pull/29
[685132d]: https://github.com/studiometa/foehn/commit/685132d
[#30]: https://github.com/studiometa/foehn/pull/30
[cb284f8]: https://github.com/studiometa/foehn/commit/cb284f8
[343e094]: https://github.com/studiometa/foehn/commit/343e094
[#32]: https://github.com/studiometa/foehn/pull/32
[04c283c]: https://github.com/studiometa/foehn/commit/04c283c
[#24]: https://github.com/studiometa/foehn/pull/33
[3d295e9]: https://github.com/studiometa/foehn/commit/3d295e9
[#31]: https://github.com/studiometa/foehn/pull/31
[0.1.0]: https://github.com/studiometa/foehn/releases/tag/0.1.0
