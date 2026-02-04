# Task Plan: studiometa/wp-tempest

## Goal

Create a Composer package `studiometa/wp-tempest` that integrates Tempest Framework with WordPress to provide a modern DX (PHP 8 attributes, auto-discovery, DI) while supporting FSE, Gutenberg, and existing workflows (Timber/Twig, ACF).

## Phases

### Phase 1: Foundations

- [x] 1.1 Composer package setup (structure, autoload, dependencies)
- [x] 1.2 Kernel and WordPress bootstrap
- [x] 1.3 Tempest Container integration
- [x] 1.4 Basic discovery (hooks: actions/filters)
- [x] 1.5 Unit tests (Pest)

### Phase 2: Post Types & Taxonomies

- [x] 2.1 `#[AsPostType]` attribute
- [x] 2.2 `#[AsTaxonomy]` attribute
- [x] 2.3 Automatic Timber classmap integration
- [x] 2.4 Migrate existing Builders
- [x] 2.5 Tests

### Phase 3: Views & Templates

- [x] 3.1 ViewEngine abstraction (Twig adapter)
- [x] 3.2 `#[AsViewComposer]` attribute
- [x] 3.3 `#[AsTemplateController]` attribute
- [x] 3.4 Timber context integration
- [x] 3.5 Tests

### Phase 4: Blocks - ACF

- [x] 4.1 `#[AsAcfBlock]` attribute
- [x] 4.2 BlockDefinition and FieldsBuilder integration
- [x] 4.3 BlockRenderer with DI
- [x] 4.4 Migrate existing blocks
- [x] 4.5 Tests

### Phase 5: Blocks - Native Gutenberg

- [x] 5.1 `#[AsBlock]` attribute with interactivity option
- [x] 5.2 Automatic block.json generation
- [x] 5.3 render.php generation (calls ViewEngine)
- [x] 5.4 `InteractiveBlockInterface` (initialState, initialContext)
- [x] 5.5 Twig `InteractivityExtension` (wp_context, wp_directive)
- [x] 5.6 Assets management (CSS/JS/view.js)
- [x] 5.7 Tests

### Phase 6: FSE Support

- [x] 6.1 ThemeConfig → theme.json generator
- [x] 6.2 `#[AsBlockPattern]` attribute with template support
- [x] 6.3 `BlockPatternDiscovery` with ViewEngine (Twig for patterns)
- [x] 6.4 Block categories registration
- [x] 6.5 Template parts support
- [x] 6.6 Tests

### Phase 7: Advanced Features

- [x] 7.1 Shortcodes via `#[AsShortcode]` attribute
- [x] 7.2 REST API endpoints via `#[AsRestRoute]` attribute
- [x] 7.3 CLI commands (make:block, make:post-type, etc.)
- [ ] 7.4 Discovery cache (production)
- [x] 7.5 Tests

### Phase 8: Documentation & Release

- [x] 8.1 Complete documentation
- [ ] 8.2 Migration guide from wp-toolkit
- [ ] 8.3 Theme examples
- [x] 8.4 CI/CD setup
- [ ] 8.5 Release v0.1.0

## Status

**All core phases complete** ✅ - Ready for testing and v0.1.0 release

---

## Progress Log

| Date       | Phase | Action                      | Result                                                |
| ---------- | ----- | --------------------------- | ----------------------------------------------------- |
| 2026-02-04 | 0     | Analyze existing wp-toolkit | Identified redundancies with Timber                   |
| 2026-02-04 | 0     | Evaluate Tempest vs Acorn   | Tempest chosen (discovery-first)                      |
| 2026-02-04 | 0     | Design package architecture | wp-tempest structure defined                          |
| 2026-02-04 | 0     | Design unified ViewEngine   | Patterns + Interactivity via templates                |
| 2026-02-04 | 0     | Complete theme example      | All use cases documented                              |
| 2026-02-04 | 0     | Create plan                 | Documents complete                                    |
| 2026-02-04 | 1     | Project setup               | composer.json, LICENSE, README, configs               |
| 2026-02-04 | 1     | Kernel + helpers            | Bootstrap WP + Tempest integration                    |
| 2026-02-04 | 1     | Hook attributes             | #[AsAction], #[AsFilter]                              |
| 2026-02-04 | 1     | Discovery system            | HookDiscovery, DiscoveryRunner                        |
| 2026-02-04 | 1     | Pest tests                  | Attribute coverage                                    |
| 2026-02-04 | 2     | PostType/Taxonomy attrs     | #[AsPostType], #[AsTaxonomy]                          |
| 2026-02-04 | 2     | Builders                    | PostTypeBuilder, TaxonomyBuilder                      |
| 2026-02-04 | 2     | Discoveries                 | PostTypeDiscovery, TaxonomyDiscovery                  |
| 2026-02-04 | 2     | Tests                       | Full test coverage for Phase 2                        |
| 2026-02-04 | 3     | View contracts              | ViewEngineInterface, ViewComposerInterface            |
| 2026-02-04 | 3     | View attributes             | #[AsViewComposer], #[AsTemplateController]            |
| 2026-02-04 | 3     | View implementations        | TimberViewEngine, ViewComposerRegistry                |
| 2026-02-04 | 3     | View discoveries            | ViewComposerDiscovery, TemplateControllerDiscovery    |
| 2026-02-04 | 3     | Tests                       | Full test coverage for Phase 3                        |
| 2026-02-04 | 4     | ACF Block attribute         | #[AsAcfBlock], AcfBlockInterface                      |
| 2026-02-04 | 4     | ACF Block renderer          | AcfBlockRenderer with DI support                      |
| 2026-02-04 | 4     | ACF Block discovery         | AcfBlockDiscovery with FieldsBuilder                  |
| 2026-02-04 | 4     | Tests                       | Full test coverage for Phase 4                        |
| 2026-02-04 | 5     | Native Block attributes     | #[AsBlock], BlockInterface, InteractiveBlockInterface |
| 2026-02-04 | 5     | Block infrastructure        | BlockRenderer, BlockJsonGenerator                     |
| 2026-02-04 | 5     | Block discovery             | BlockDiscovery with interactivity support             |
| 2026-02-04 | 5     | Twig extension              | InteractivityExtension for Interactivity API          |
| 2026-02-04 | 5     | Tests                       | Full test coverage for Phase 5                        |
| 2026-02-04 | 6     | FSE attributes              | #[AsBlockPattern], #[AsBlockCategory]                 |
| 2026-02-04 | 6     | Pattern discovery           | BlockPatternDiscovery with ViewEngine                 |
| 2026-02-04 | 6     | Theme.json generator        | ThemeJsonGenerator for FSE configuration              |
| 2026-02-04 | 6     | Tests                       | Full test coverage for Phase 6                        |
| 2026-02-04 | 7     | Advanced attributes         | #[AsShortcode], #[AsRestRoute]                        |
| 2026-02-04 | 7     | Advanced discoveries        | ShortcodeDiscovery, RestRouteDiscovery                |
| 2026-02-04 | 7     | Tests                       | Full test coverage for Phase 7                        |
| 2026-02-04 | 8     | CI/CD                       | GitHub Actions workflow                               |
| 2026-02-04 | 7     | CLI commands                | WP-CLI make:_ and discovery:_ commands                |

## Summary

### Implemented Features

| Category       | Features                                       |
| -------------- | ---------------------------------------------- |
| **Hooks**      | `#[AsAction]`, `#[AsFilter]`                   |
| **Content**    | `#[AsPostType]`, `#[AsTaxonomy]`               |
| **Views**      | `#[AsViewComposer]`, `#[AsTemplateController]` |
| **ACF Blocks** | `#[AsAcfBlock]` with FieldsBuilder integration |
| **Blocks**     | `#[AsBlock]` with Interactivity API support    |
| **FSE**        | `#[AsBlockPattern]`, `ThemeJsonGenerator`      |
| **Advanced**   | `#[AsShortcode]`, `#[AsRestRoute]`             |

### Test Coverage

- **148 tests** passing
- **456 assertions**
- All attributes, builders, registries, and CLI commands covered

### Remaining Work

- Discovery cache for production
- Migration guide from wp-toolkit
- Theme example project
- Release v0.1.0
