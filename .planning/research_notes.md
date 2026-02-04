# Research Notes: wp-tempest

## 1. Analyse wp-toolkit existant

### Points forts
- `PostTypeBuilder` / `TaxonomyBuilder` : génération labels automatique
- `AssetsManager` : intégration webpack manifest
- `CleanupManager` : hardening WP centralisé

### Redondances avec Timber
| wp-toolkit | Timber natif | Verdict |
|------------|--------------|---------|
| `Repository` | `Timber::get_posts()` | Supprimer |
| `PostRepository` | `Timber::get_posts()` | Supprimer |
| `TermRepository` | `Timber::get_terms()` | Supprimer |
| `CustomPostTypesManager::set_classmap` | `timber/post/classmap` filter | Supprimer |

### Problèmes identifiés
- `ManagerInterface::run()` trop simpliste
- Pas de DI, injection manuelle
- Pas de séparation register/boot
- Pas testable (couplage aux hooks WP)

## 2. Tempest Framework

### Métriques (2026-02-04)
- Version : v2.14.0
- GitHub Stars : 2,056
- Architecture : Monorepo (30+ packages)
- PHP requis : 8.4+
- Mainteneur : Brent Roose (spatie.be)

### Composants utilisables
```
tempest/container    - DI container avec auto-wiring
tempest/discovery    - Auto-discovery via attributs
tempest/reflection   - Réflexion PHP avancée
tempest/console      - CLI commands
tempest/view         - View components (optionnel)
tempest/validation   - Validation (optionnel)
```

### Discovery Pattern
```php
// Tempest scanne les classes et applique les discoveries
final class HookDiscovery implements Discovery
{
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        // Trouve les méthodes avec attributs
    }
    
    public function apply(): void
    {
        // Exécute les enregistrements
    }
}
```

### View Components
- Convention : `x-*.view.php`
- Auto-découverts par `ViewComponentDiscovery`
- Rendu via `ViewRenderer`

## 3. Acorn (Laravel pour WP)

### Ce qu'Acorn apporte
- Service Providers (register/boot)
- View Composers
- Blade templating
- Artisan CLI
- Eloquent ORM (optionnel)

### Différences avec Tempest
| Aspect | Acorn | Tempest |
|--------|-------|---------|
| Philosophie | Convention-first | Discovery-first |
| Config | Fichiers config/*.php | Attributs PHP 8 |
| Providers | Manuels | Auto-découverts |
| Verbosité | Plus de boilerplate | Moins de code |

## 4. WordPress FSE & Gutenberg

### block.json (native blocks)
```json
{
    "$schema": "https://schemas.wp.org/trunk/block.json",
    "apiVersion": 3,
    "name": "theme/notice",
    "title": "Notice",
    "render": "file:./render.php",
    "attributes": {},
    "supports": {}
}
```

### ACF Blocks
```php
acf_register_block_type([
    'name' => 'hero',
    'title' => 'Hero',
    'render_callback' => 'render_hero_block',
]);
```

### theme.json
- Définit design tokens (couleurs, typo, spacing)
- Configure les supports des blocks
- Styles globaux et par block
- Custom templates et template parts

### Block Patterns
```php
register_block_pattern('theme/hero', [
    'title' => 'Hero',
    'content' => '<!-- wp:cover -->...<!-- /wp:cover -->',
]);
```

### Interactivity API (WP 6.5+)
```php
wp_interactivity_state('myblock', ['count' => 0]);
```
```html
<div data-wp-interactive="myblock">
    <span data-wp-text="state.count"></span>
    <button data-wp-on--click="actions.increment">+</button>
</div>
```

## 5. Timber 2.x

### API principale
```php
// Querying
$posts = Timber::get_posts($args);
$post = Timber::get_post($id);
$terms = Timber::get_terms($args);

// Context
$context = Timber::context(); // Auto-populates post, posts, term, etc.

// Rendering
Timber::render('template.twig', $context);
```

### Class Maps
```php
add_filter('timber/post/classmap', function($map) {
    $map['product'] = Product::class;
    return $map;
});
```

### Context Filter
```php
add_filter('timber/context', function($context) {
    $context['menus'] = [...];
    return $context;
});
```

## 6. Décisions d'architecture

### Attributs à créer

| Attribut | WordPress équivalent | Cible |
|----------|---------------------|-------|
| `#[AsAction]` | `add_action()` | Méthode |
| `#[AsFilter]` | `add_filter()` | Méthode |
| `#[AsPostType]` | `register_post_type()` | Classe |
| `#[AsTaxonomy]` | `register_taxonomy()` | Classe |
| `#[AsBlock]` | `register_block_type()` | Classe |
| `#[AsAcfBlock]` | `acf_register_block_type()` | Classe |
| `#[AsBlockPattern]` | `register_block_pattern()` | Classe |
| `#[AsViewComposer]` | `timber/context` filter | Classe |
| `#[AsTemplateController]` | `template_include` | Classe |
| `#[AsShortcode]` | `add_shortcode()` | Méthode |
| `#[AsRestRoute]` | `register_rest_route()` | Méthode |

### Discoveries à implémenter

1. `HookDiscovery` - Actions et filters
2. `PostTypeDiscovery` - CPT + Timber classmap
3. `TaxonomyDiscovery` - Taxonomies
4. `BlockDiscovery` - Native Gutenberg blocks
5. `AcfBlockDiscovery` - ACF blocks
6. `BlockPatternDiscovery` - Block patterns
7. `ViewComposerDiscovery` - Timber context composers
8. `TemplateControllerDiscovery` - Template routing
9. `ShortcodeDiscovery` - Shortcodes
10. `RestRouteDiscovery` - REST API

### Lifecycle WordPress vs Tempest

```
WordPress Boot:
1. mu-plugins loaded
2. plugins loaded  
3. after_setup_theme    ← Kernel::boot() ici
4. init                 ← Discoveries applied
5. wp_loaded
6. template_redirect    ← Template controllers
7. template_include
```

### Cache Strategy

```php
// Development: no cache, discover on each request
// Production: cache discoveries to file/opcache

$kernel->boot(__DIR__ . '/app', [
    'cache' => true,
    'cache_path' => __DIR__ . '/storage/framework/cache',
]);
```

## 7. Questions ouvertes

### PHP Version
- **8.4** : Features complètes Tempest, property hooks
- **8.2** : Plus d'hébergements compatibles
- **Recommandation** : 8.4 pour nouveaux projets, documenter

### Namespace
- `Studiometa\WPTempest` - Clair, lié à Studio Meta
- `WPTempest` - Plus court, générique
- **Recommandation** : `Studiometa\WPTempest`

### Repository
- **Séparé** : Plus flexible, releases indépendantes
- **Monorepo avec wp-toolkit** : Partage code, mais couplage
- **Recommandation** : Séparé, wp-toolkit peut dépendre de wp-tempest

### ViewEngine
- **Timber only** : Simple, écosystème existant
- **Multi-engine** : Twig + Blade + Tempest View
- **Recommandation** : Timber par défaut, interface pour extensibilité
