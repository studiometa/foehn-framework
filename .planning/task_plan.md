# Task Plan: studiometa/wp-tempest

## Goal

Créer un package Composer `studiometa/wp-tempest` qui intègre Tempest Framework avec WordPress pour offrir une DX moderne (attributs PHP 8, auto-discovery, DI) tout en supportant FSE, Gutenberg, et les workflows existants (Timber/Twig, ACF).

## Phases

### Phase 1: Fondations
- [ ] 1.1 Setup du package Composer (structure, autoload, dépendances)
- [ ] 1.2 Kernel et bootstrap WordPress
- [ ] 1.3 Intégration Container Tempest
- [ ] 1.4 Discovery de base (hooks: actions/filters)
- [ ] 1.5 Tests unitaires de base

### Phase 2: Post Types & Taxonomies
- [ ] 2.1 Attribut `#[AsPostType]`
- [ ] 2.2 Attribut `#[AsTaxonomy]`
- [ ] 2.3 Intégration Timber classmap automatique
- [ ] 2.4 Migration des Builders existants
- [ ] 2.5 Tests

### Phase 3: Views & Templates
- [ ] 3.1 ViewEngine abstraction (Twig adapter)
- [ ] 3.2 Attribut `#[AsViewComposer]`
- [ ] 3.3 Attribut `#[AsTemplateController]`
- [ ] 3.4 Intégration Timber context
- [ ] 3.5 Tests

### Phase 4: Blocks - ACF
- [ ] 4.1 Attribut `#[AsAcfBlock]`
- [ ] 4.2 BlockDefinition et FieldsBuilder intégration
- [ ] 4.3 BlockRenderer avec DI
- [ ] 4.4 Migration des blocks existants
- [ ] 4.5 Tests

### Phase 5: Blocks - Native Gutenberg
- [ ] 5.1 Attribut `#[AsBlock]` avec option interactivity
- [ ] 5.2 Génération block.json automatique
- [ ] 5.3 Génération render.php (appelle ViewEngine)
- [ ] 5.4 `InteractiveBlockInterface` (initialState, initialContext)
- [ ] 5.5 Twig `InteractivityExtension` (wp_context, wp_directive)
- [ ] 5.6 Assets management (CSS/JS/view.js)
- [ ] 5.7 Tests

### Phase 6: FSE Support
- [ ] 6.1 ThemeConfig → theme.json generator
- [ ] 6.2 Attribut `#[AsBlockPattern]` avec template support
- [ ] 6.3 `BlockPatternDiscovery` avec ViewEngine (Twig pour patterns)
- [ ] 6.4 Block categories registration
- [ ] 6.5 Template parts support
- [ ] 6.6 Tests

### Phase 7: Advanced Features
- [ ] 7.1 Shortcodes via attributs `#[AsShortcode]`
- [ ] 7.2 REST API endpoints via attributs `#[AsRestRoute]`
- [ ] 7.3 CLI commands (make:block, make:post-type, etc.)
- [ ] 7.4 Discovery cache (production)
- [ ] 7.5 Tests

### Phase 8: Documentation & Release
- [ ] 8.1 Documentation complète
- [ ] 8.2 Migration guide depuis wp-toolkit
- [ ] 8.3 Exemples de thème
- [ ] 8.4 CI/CD setup
- [ ] 8.5 Release v0.1.0

## Key Questions

1. ✅ Tempest complet ou composants sélectifs ? → **Complet** (v2.14 mature)
2. ✅ Support Timber/Twig ou migration vers Tempest View ? → **Timber** (écosystème existant)
3. ⏳ PHP 8.4 strict ou rétro-compat 8.2 ? → À décider
4. ⏳ Namespace `Studiometa\WPTempest` ou autre ? → À décider
5. ⏳ Repo séparé ou monorepo avec wp-toolkit ? → À décider
6. ⏳ Comment gérer le cache discovery en production ? → À définir

## Decisions Made

| Decision | Rationale | Date |
|----------|-----------|------|
| Tempest v2.14+ | Framework mature, discovery-first, actif | 2026-02-04 |
| Garder Timber/Twig | Écosystème existant, pas de migration forcée | 2026-02-04 |
| Support ACF + Native blocks | Rétro-compat + modernisation progressive | 2026-02-04 |
| FSE support | WordPress va vers FSE, anticiper | 2026-02-04 |
| View Composers pattern | Inspiré Acorn, séparation concerns | 2026-02-04 |

## Risks & Mitigations

| Risk | Impact | Mitigation |
|------|--------|------------|
| PHP 8.4 requis | Hébergements limités | Documenter, évaluer 8.2 compat |
| Performance discovery | Lenteur au boot | Cache obligatoire, lazy loading |
| Breaking changes Tempest | Maintenance | Pin version, suivre releases |
| Complexité adoption | Résistance équipe | Migration progressive, docs |
| Conflit avec WP lifecycle | Bugs timing | Tests exhaustifs, hooks WP |

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
        "phpunit/phpunit": "^11.0",
        "phpstan/phpstan": "^2.0"
    }
}
```

### WordPress
- WordPress 6.4+ (FSE mature)
- ACF Pro 6.0+ (blocks support)

## Milestones

| Milestone | Target | Deliverable |
|-----------|--------|-------------|
| M1: Core | +2 semaines | Kernel, DI, Hooks discovery |
| M2: Content | +2 semaines | PostTypes, Taxonomies, Views |
| M3: Blocks | +3 semaines | ACF + Native blocks |
| M4: FSE | +2 semaines | theme.json, patterns |
| M5: Release | +1 semaine | Docs, v0.1.0 |

**Total estimé : 10 semaines**

## Documents

| Document | Description |
|----------|-------------|
| [task_plan.md](task_plan.md) | Ce document - plan et suivi |
| [research_notes.md](research_notes.md) | Notes de recherche et analyse |
| [architecture.md](architecture.md) | Architecture technique wp-tempest |
| [architecture_views_update.md](architecture_views_update.md) | ViewEngine pour Patterns & Interactivity |
| [theme_example.md](theme_example.md) | Exemple complet de thème |

## Status

**Phase 0 : Planification** ✅ - Architecture définie, prêt pour implémentation

---

## Progress Log

| Date | Phase | Action | Result |
|------|-------|--------|--------|
| 2026-02-04 | 0 | Analyse wp-toolkit existant | Identifié redondances avec Timber |
| 2026-02-04 | 0 | Évaluation Tempest vs Acorn | Tempest choisi (discovery-first) |
| 2026-02-04 | 0 | Design architecture package | Structure wp-tempest définie |
| 2026-02-04 | 0 | Design ViewEngine unified | Patterns + Interactivity via templates |
| 2026-02-04 | 0 | Exemple thème complet | Tous les use cases documentés |
| 2026-02-04 | 0 | Création plan | Documents complets |
