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
- [ ] 2.1 `#[AsPostType]` attribute
- [ ] 2.2 `#[AsTaxonomy]` attribute
- [ ] 2.3 Automatic Timber classmap integration
- [ ] 2.4 Migrate existing Builders
- [ ] 2.5 Tests

### Phase 3: Views & Templates
- [ ] 3.1 ViewEngine abstraction (Twig adapter)
- [ ] 3.2 `#[AsViewComposer]` attribute
- [ ] 3.3 `#[AsTemplateController]` attribute
- [ ] 3.4 Timber context integration
- [ ] 3.5 Tests

### Phase 4: Blocks - ACF
- [ ] 4.1 `#[AsAcfBlock]` attribute
- [ ] 4.2 BlockDefinition and FieldsBuilder integration
- [ ] 4.3 BlockRenderer with DI
- [ ] 4.4 Migrate existing blocks
- [ ] 4.5 Tests

### Phase 5: Blocks - Native Gutenberg
- [ ] 5.1 `#[AsBlock]` attribute with interactivity option
- [ ] 5.2 Automatic block.json generation
- [ ] 5.3 render.php generation (calls ViewEngine)
- [ ] 5.4 `InteractiveBlockInterface` (initialState, initialContext)
- [ ] 5.5 Twig `InteractivityExtension` (wp_context, wp_directive)
- [ ] 5.6 Assets management (CSS/JS/view.js)
- [ ] 5.7 Tests

### Phase 6: FSE Support
- [ ] 6.1 ThemeConfig → theme.json generator
- [ ] 6.2 `#[AsBlockPattern]` attribute with template support
- [ ] 6.3 `BlockPatternDiscovery` with ViewEngine (Twig for patterns)
- [ ] 6.4 Block categories registration
- [ ] 6.5 Template parts support
- [ ] 6.6 Tests

### Phase 7: Advanced Features
- [ ] 7.1 Shortcodes via `#[AsShortcode]` attribute
- [ ] 7.2 REST API endpoints via `#[AsRestRoute]` attribute
- [ ] 7.3 CLI commands (make:block, make:post-type, etc.)
- [ ] 7.4 Discovery cache (production)
- [ ] 7.5 Tests

### Phase 8: Documentation & Release
- [ ] 8.1 Complete documentation
- [ ] 8.2 Migration guide from wp-toolkit
- [ ] 8.3 Theme examples
- [ ] 8.4 CI/CD setup
- [ ] 8.5 Release v0.1.0

## Key Questions

1. ✅ Full Tempest or selective components? → **Full** (v2.14 mature)
2. ✅ Support Timber/Twig or migrate to Tempest View? → **Timber** (existing ecosystem)
3. ✅ Strict PHP 8.4 or backward compat 8.2? → **PHP 8.4+**
4. ✅ Namespace? → **Studiometa\WPTempest**
5. ✅ Separate repo or monorepo with wp-toolkit? → **Separate**, deprecate wp-toolkit

## Decisions Made

| Decision | Rationale | Date |
|----------|-----------|------|
| Tempest v2.14+ | Mature framework, discovery-first, active development | 2026-02-04 |
| Keep Timber/Twig | Existing ecosystem, no forced migration | 2026-02-04 |
| Support ACF + Native blocks | Backward compat + progressive modernization | 2026-02-04 |
| FSE support | WordPress is moving to FSE, anticipate | 2026-02-04 |
| View Composers pattern | Inspired by Acorn, separation of concerns | 2026-02-04 |
| PHP 8.4+ | Modern features, property hooks | 2026-02-04 |
| Separate repository | Independent releases, deprecate wp-toolkit | 2026-02-04 |
| Pest for testing | Modern testing DX | 2026-02-04 |

## Risks & Mitigations

| Risk | Impact | Mitigation |
|------|--------|------------|
| PHP 8.4 required | Limited hosting support | Document requirements clearly |
| Discovery performance | Slow boot time | Mandatory cache, lazy loading |
| Tempest breaking changes | Maintenance burden | Pin version, follow releases |
| Adoption complexity | Team resistance | Progressive migration, good docs |
| WP lifecycle conflicts | Timing bugs | Exhaustive tests, WP hooks |

## Dependencies

### Composer (package)
```json
{
    "require": {
        "php": "^8.4",
        "tempest/framework": "^2.14",
        "timber/timber": "^2.0"
    },
    "require-dev": {
        "pestphp/pest": "^3.0",
        "phpstan/phpstan": "^2.0"
    }
}
```

### WordPress
- WordPress 6.4+ (mature FSE)
- ACF Pro 6.0+ (blocks support)

## Milestones

| Milestone | Target | Deliverable |
|-----------|--------|-------------|
| M1: Core | +2 weeks | Kernel, DI, Hooks discovery |
| M2: Content | +2 weeks | PostTypes, Taxonomies, Views |
| M3: Blocks | +3 weeks | ACF + Native blocks |
| M4: FSE | +2 weeks | theme.json, patterns |
| M5: Release | +1 week | Docs, v0.1.0 |

**Total estimate: ~10 weeks**

## Documents

| Document | Description |
|----------|-------------|
| [task_plan.md](task_plan.md) | This document - plan and tracking |
| [research_notes.md](research_notes.md) | Research notes and analysis |
| [architecture.md](architecture.md) | wp-tempest technical architecture |
| [architecture_views_update.md](architecture_views_update.md) | ViewEngine for Patterns & Interactivity |
| [theme_example.md](theme_example.md) | Complete theme implementation example |

## Status

**Phase 1: Foundations** ✅ - Kernel, Container, Hooks discovery implemented

---

## Progress Log

| Date | Phase | Action | Result |
|------|-------|--------|--------|
| 2026-02-04 | 0 | Analyze existing wp-toolkit | Identified redundancies with Timber |
| 2026-02-04 | 0 | Evaluate Tempest vs Acorn | Tempest chosen (discovery-first) |
| 2026-02-04 | 0 | Design package architecture | wp-tempest structure defined |
| 2026-02-04 | 0 | Design unified ViewEngine | Patterns + Interactivity via templates |
| 2026-02-04 | 0 | Complete theme example | All use cases documented |
| 2026-02-04 | 0 | Create plan | Documents complete |
| 2026-02-04 | 1 | Project setup | composer.json, LICENSE, README, configs |
| 2026-02-04 | 1 | Kernel + helpers | Bootstrap WP + Tempest integration |
| 2026-02-04 | 1 | Hook attributes | #[AsAction], #[AsFilter] |
| 2026-02-04 | 1 | Discovery system | HookDiscovery, DiscoveryRunner |
| 2026-02-04 | 1 | Pest tests | Attribute coverage |
