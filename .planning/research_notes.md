# Research Notes: wp-tempest

## 1. Existing wp-toolkit Analysis

### Strengths

- `PostTypeBuilder` / `TaxonomyBuilder`: automatic label generation
- `AssetsManager`: webpack manifest integration
- `CleanupManager`: centralized WP hardening

### Redundancies with Timber

| wp-toolkit                             | Timber native                 | Verdict |
| -------------------------------------- | ----------------------------- | ------- |
| `Repository`                           | `Timber::get_posts()`         | Remove  |
| `PostRepository`                       | `Timber::get_posts()`         | Remove  |
| `TermRepository`                       | `Timber::get_terms()`         | Remove  |
| `CustomPostTypesManager::set_classmap` | `timber/post/classmap` filter | Remove  |

### Identified Problems

- `ManagerInterface::run()` too simplistic
- No DI, manual injection
- No separation of register/boot
- Not testable (coupled to WP hooks)

## 2. Tempest Framework

### Metrics (2026-02-04)

- Version: v2.14.0
- GitHub Stars: 2,056
- Architecture: Monorepo (30+ packages)
- PHP required: 8.4+
- Maintainer: Brent Roose (spatie.be)

### Usable Components

```
tempest/container    - DI container with auto-wiring
tempest/discovery    - Auto-discovery via attributes
tempest/reflection   - Advanced PHP reflection
tempest/console      - CLI commands
tempest/view         - View components (optional)
tempest/validation   - Validation (optional)
```

### Discovery Pattern

```php
// Tempest scans classes and applies discoveries
final class HookDiscovery implements Discovery
{
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        // Find methods with attributes
    }

    public function apply(): void
    {
        // Execute registrations
    }
}
```

### View Components

- Convention: `x-*.view.php`
- Auto-discovered by `ViewComponentDiscovery`
- Rendered via `ViewRenderer`

## 3. Acorn (Laravel for WP)

### What Acorn Provides

- Service Providers (register/boot)
- View Composers
- Blade templating
- Artisan CLI
- Eloquent ORM (optional)

### Differences with Tempest

| Aspect     | Acorn               | Tempest          |
| ---------- | ------------------- | ---------------- |
| Philosophy | Convention-first    | Discovery-first  |
| Config     | config/\*.php files | PHP 8 attributes |
| Providers  | Manual              | Auto-discovered  |
| Verbosity  | More boilerplate    | Less code        |

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

- Defines design tokens (colors, typography, spacing)
- Configures block supports
- Global and per-block styles
- Custom templates and template parts

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

### Main API

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

## 6. Architecture Decisions

### Attributes to Create

| Attribute                 | WordPress equivalent        | Target |
| ------------------------- | --------------------------- | ------ |
| `#[AsAction]`             | `add_action()`              | Method |
| `#[AsFilter]`             | `add_filter()`              | Method |
| `#[AsPostType]`           | `register_post_type()`      | Class  |
| `#[AsTaxonomy]`           | `register_taxonomy()`       | Class  |
| `#[AsBlock]`              | `register_block_type()`     | Class  |
| `#[AsAcfBlock]`           | `acf_register_block_type()` | Class  |
| `#[AsBlockPattern]`       | `register_block_pattern()`  | Class  |
| `#[AsViewComposer]`       | `timber/context` filter     | Class  |
| `#[AsTemplateController]` | `template_include`          | Class  |
| `#[AsShortcode]`          | `add_shortcode()`           | Method |
| `#[AsRestRoute]`          | `register_rest_route()`     | Method |

### Discoveries to Implement

1. `HookDiscovery` - Actions and filters
2. `PostTypeDiscovery` - CPT + Timber classmap
3. `TaxonomyDiscovery` - Taxonomies
4. `BlockDiscovery` - Native Gutenberg blocks
5. `AcfBlockDiscovery` - ACF blocks
6. `BlockPatternDiscovery` - Block patterns
7. `ViewComposerDiscovery` - Timber context composers
8. `TemplateControllerDiscovery` - Template routing
9. `ShortcodeDiscovery` - Shortcodes
10. `RestRouteDiscovery` - REST API

### WordPress Lifecycle vs Tempest

```
WordPress Boot:
1. mu-plugins loaded
2. plugins loaded
3. after_setup_theme    ← Kernel::boot() here
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

## 7. Open Questions (Resolved)

### PHP Version

- **8.4**: Full Tempest features, property hooks
- ~~**8.2**: More hosting compatibility~~
- **Decision**: 8.4 for new projects, document clearly

### Namespace

- ~~`WPTempest` - Shorter, generic~~
- **Decision**: `Studiometa\WPTempest`

### Repository

- **Decision**: Separate, wp-toolkit can be deprecated
- ~~Monorepo with wp-toolkit: Share code, but coupling~~

### ViewEngine

- **Decision**: Timber by default, interface for extensibility
- ~~Multi-engine: Twig + Blade + Tempest View~~
