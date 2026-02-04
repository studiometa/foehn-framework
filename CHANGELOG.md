# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- Fix discovery system conflicts with Tempest lifecycle — double discovery, incorrect timing, uninitialized properties ([748aace], [#7])
- Fix root path passed to Tempest causing "Could not locate composer.json" error ([f0b4f27], [#5])

### Changed

- Decouple discoveries from Tempest's `Discovery` interface, replace with `WpDiscovery` + `IsWpDiscovery` ([748aace], [#7])
- Rewrite `DiscoveryRunner` to own the full lifecycle: class scanning via Composer PSR-4, phased `apply()` at correct WP hooks ([b3d5134], [#7])
- Tempest is now used only for the DI container, not for discovery ([b3d5134], [#7])

### Added

- Add `make:controller` command to scaffold template controllers ([fe277ae], [#20])
- Add `make:hooks` command to scaffold hook classes ([2e98402], [#20])
- Add `--fields` flag to `make:acf-block` for auto-generating ACF fields ([45a067d], [#20])
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
- Add GitHub Pages deployment workflow ([02d6425], [#3])

[b6fd69a]: https://github.com/studiometa/wp-tempest/commit/b6fd69a
[fae5391]: https://github.com/studiometa/wp-tempest/commit/fae5391
[748aace]: https://github.com/studiometa/wp-tempest/commit/748aace
[f0b4f27]: https://github.com/studiometa/wp-tempest/commit/f0b4f27
[b3d5134]: https://github.com/studiometa/wp-tempest/commit/b3d5134
[b544790]: https://github.com/studiometa/wp-tempest/commit/b544790
[e3988c7]: https://github.com/studiometa/wp-tempest/commit/e3988c7
[d7cbe4c]: https://github.com/studiometa/wp-tempest/commit/d7cbe4c
[adc01ed]: https://github.com/studiometa/wp-tempest/commit/adc01ed
[d80fe88]: https://github.com/studiometa/wp-tempest/commit/d80fe88
[02d6425]: https://github.com/studiometa/wp-tempest/commit/02d6425
[#2]: https://github.com/studiometa/wp-tempest/pull/2
[#3]: https://github.com/studiometa/wp-tempest/pull/3
[#5]: https://github.com/studiometa/wp-tempest/pull/5
[#7]: https://github.com/studiometa/wp-tempest/pull/7
[#11]: https://github.com/studiometa/wp-tempest/pull/11
[#12]: https://github.com/studiometa/wp-tempest/pull/12
[#13]: https://github.com/studiometa/wp-tempest/pull/13
[#20]: https://github.com/studiometa/wp-tempest/pull/20
[1bac8e8]: https://github.com/studiometa/wp-tempest/commit/1bac8e8
[fe277ae]: https://github.com/studiometa/wp-tempest/commit/fe277ae
[2e98402]: https://github.com/studiometa/wp-tempest/commit/2e98402
[45a067d]: https://github.com/studiometa/wp-tempest/commit/45a067d
