# Theme Conventions

This guide establishes the recommended structure and naming conventions for Føhn-based WordPress themes.

## Directory Structure

```
theme/
├── app/                          # PHP application code
│   ├── Blocks/                   # ACF and native blocks
│   │   ├── Hero/
│   │   │   └── HeroBlock.php
│   │   └── Features/
│   │       └── FeaturesBlock.php
│   ├── Console/                  # CLI commands
│   │   └── ImportProductsCommand.php
│   ├── ContextProviders/         # Context providers
│   │   ├── GlobalContextProvider.php
│   │   └── NavigationContextProvider.php
│   ├── Controllers/              # Template controllers
│   │   ├── HomeController.php
│   │   └── SingleController.php
│   ├── Hooks/                    # WordPress action/filter handlers
│   │   ├── ThemeHooks.php
│   │   ├── AssetHooks.php
│   │   └── AdminHooks.php
│   ├── Models/                   # Custom post types (Timber models)
│   │   ├── Product.php
│   │   ├── Event.php
│   │   └── TeamMember.php
│   ├── Patterns/                 # Block patterns
│   │   └── HeroPattern.php
│   ├── Rest/                     # REST API endpoints
│   │   └── ProductsEndpoint.php
│   ├── Services/                 # Business logic services
│   │   ├── CartService.php
│   │   └── NewsletterService.php
│   ├── Shortcodes/               # Shortcode handlers
│   │   └── ButtonShortcode.php
│   ├── Taxonomies/               # Custom taxonomies
│   │   ├── ProductCategory.php
│   │   └── EventType.php
│   └── foehn.config.php          # Config files (Tempest convention)
├── templates/                    # Twig templates
│   ├── blocks/                   # Block templates
│   │   ├── hero.twig
│   │   └── features.twig
│   ├── components/               # Reusable components
│   │   ├── button.twig
│   │   ├── card.twig
│   │   ├── header.twig
│   │   ├── footer.twig
│   │   └── pagination.twig
│   ├── layouts/                  # Base layouts
│   │   └── base.twig
│   ├── pages/                    # Page-specific templates
│   │   ├── home.twig
│   │   └── contact.twig
│   └── patterns/                 # Block pattern templates
│       └── hero.twig
├── assets/                       # Source assets
│   ├── scripts/
│   └── styles/
├── dist/                         # Compiled assets
├── functions.php                 # Kernel bootstrap
└── style.css                     # Theme metadata
```

## PHP Naming Conventions

### Post Types

| Convention     | Example             |
| -------------- | ------------------- |
| **Location**   | `app/Models/`       |
| **Class name** | Singular PascalCase |
| **File name**  | `{ClassName}.php`   |

```php
// app/Models/Product.php
#[AsPostType(name: 'product', singular: 'Product', plural: 'Products')]
final class Product extends Post {}

// app/Models/TeamMember.php
#[AsPostType(name: 'team_member', singular: 'Team Member', plural: 'Team Members')]
final class TeamMember extends Post {}
```

### Taxonomies

| Convention     | Example              |
| -------------- | -------------------- |
| **Location**   | `app/Taxonomies/`    |
| **Class name** | Singular PascalCase  |
| **File name**  | `{ClassName}.php`    |

```php
// app/Taxonomies/ProductCategory.php
#[AsTaxonomy(name: 'product_category', postTypes: ['product'])]
final class ProductCategory {}

// app/Taxonomies/EventType.php
#[AsTaxonomy(name: 'event_type', postTypes: ['event'])]
final class EventType {}
```

### ACF Blocks

| Convention     | Example                   |
| -------------- | ------------------------- |
| **Location**   | `app/Blocks/{BlockName}/` |
| **Class name** | `{BlockName}Block`        |
| **File name**  | `{BlockName}Block.php`    |

```php
// app/Blocks/Hero/HeroBlock.php
#[AsAcfBlock(name: 'hero', title: 'Hero Banner')]
final readonly class HeroBlock implements AcfBlockInterface {}

// app/Blocks/FeatureGrid/FeatureGridBlock.php
#[AsAcfBlock(name: 'feature-grid', title: 'Feature Grid')]
final readonly class FeatureGridBlock implements AcfBlockInterface {}
```

Each block has its own directory to keep related files together (PHP, assets, tests).

### Native Blocks

| Convention     | Example                   |
| -------------- | ------------------------- |
| **Location**   | `app/Blocks/{BlockName}/` |
| **Class name** | `{BlockName}Block`        |
| **File name**  | `{BlockName}Block.php`    |

```php
// app/Blocks/Counter/CounterBlock.php
#[AsBlock(name: 'theme/counter', title: 'Counter')]
final readonly class CounterBlock implements InteractiveBlockInterface {}
```

### Hooks

| Convention     | Example             |
| -------------- | ------------------- |
| **Location**   | `app/Hooks/`        |
| **Class name** | `{Domain}Hooks`     |
| **File name**  | `{Domain}Hooks.php` |

```php
// app/Hooks/ThemeHooks.php
final class ThemeHooks {
    #[AsAction('after_setup_theme')]
    public function setup(): void {}
}

// app/Hooks/AssetHooks.php
final class AssetHooks {
    #[AsAction('wp_enqueue_scripts')]
    public function enqueueAssets(): void {}
}

// app/Hooks/AdminHooks.php
final class AdminHooks {
    #[AsAction('admin_init')]
    public function initAdmin(): void {}
}
```

### Context Providers

| Convention     | Example                     |
| -------------- | --------------------------- |
| **Location**   | `app/ContextProviders/`     |
| **Class name** | `{Name}ContextProvider`     |
| **File name**  | `{Name}ContextProvider.php` |

```php
// app/ContextProviders/GlobalContextProvider.php
#[AsContextProvider('*')]
final class GlobalContextProvider implements ContextProviderInterface {}

// app/ContextProviders/NavigationContextProvider.php
#[AsContextProvider('*')]
final class NavigationContextProvider implements ContextProviderInterface {}

// app/ContextProviders/ProductContextProvider.php
#[AsContextProvider('single-product')]
final class ProductContextProvider implements ContextProviderInterface {}
```

### Template Controllers

| Convention     | Example                    |
| -------------- | -------------------------- |
| **Location**   | `app/Controllers/`         |
| **Class name** | `{Template}Controller`     |
| **File name**  | `{Template}Controller.php` |

```php
// app/Controllers/HomeController.php
#[AsTemplateController('front-page')]
final class HomeController implements TemplateControllerInterface {}

// app/Controllers/SingleProductController.php
#[AsTemplateController('single-product')]
final class SingleProductController implements TemplateControllerInterface {}

// app/Controllers/ArchiveController.php
#[AsTemplateController('archive')]
final class ArchiveController implements TemplateControllerInterface {}
```

### REST Endpoints

| Convention     | Example                  |
| -------------- | ------------------------ |
| **Location**   | `app/Rest/`              |
| **Class name** | `{Resource}Endpoint`     |
| **File name**  | `{Resource}Endpoint.php` |

```php
// app/Rest/ProductsEndpoint.php
final class ProductsEndpoint {
    #[AsRestRoute(namespace: 'theme/v1', route: '/products')]
    public function list(): WP_REST_Response {}
}

// app/Rest/NewsletterEndpoint.php
final class NewsletterEndpoint {
    #[AsRestRoute(namespace: 'theme/v1', route: '/newsletter', methods: ['POST'])]
    public function subscribe(WP_REST_Request $request): WP_REST_Response {}
}
```

### Shortcodes

| Convention     | Example               |
| -------------- | --------------------- |
| **Location**   | `app/Shortcodes/`     |
| **Class name** | `{Name}Shortcode`     |
| **File name**  | `{Name}Shortcode.php` |

```php
// app/Shortcodes/ButtonShortcode.php
#[AsShortcode('button')]
final class ButtonShortcode {
    public function render(array $atts, ?string $content): string {}
}
```

### CLI Commands

| Convention     | Example             |
| -------------- | ------------------- |
| **Location**   | `app/Console/`      |
| **Class name** | `{Name}Command`     |
| **File name**  | `{Name}Command.php` |

```php
// app/Console/ImportProductsCommand.php
#[AsCliCommand(name: 'import:products', description: 'Import products from CSV')]
final class ImportProductsCommand {
    public function __invoke(array $args, array $assocArgs): void {}
}

// app/Console/CacheCommand.php
#[AsCliCommand(name: 'cache', description: 'Manage application cache')]
final class CacheCommand {
    public function clear(): void {}
    public function warm(): void {}
}
```

### Block Patterns

| Convention     | Example             |
| -------------- | ------------------- |
| **Location**   | `app/Patterns/`     |
| **Class name** | `{Name}Pattern`     |
| **File name**  | `{Name}Pattern.php` |

```php
// app/Patterns/HeroPattern.php
#[AsBlockPattern(name: 'theme/hero', title: 'Hero Section')]
final readonly class HeroPattern implements BlockPatternInterface {}
```

### Services

| Convention     | Example             |
| -------------- | ------------------- |
| **Location**   | `app/Services/`     |
| **Class name** | `{Name}Service`     |
| **File name**  | `{Name}Service.php` |

```php
// app/Services/CartService.php
final readonly class CartService {
    public function getItemCount(): int {}
}

// app/Services/NewsletterService.php
final readonly class NewsletterService {
    public function subscribe(string $email): bool {}
}
```

### Config Files

Føhn uses Tempest's config discovery. Any file ending in `.config.php` in the `app/` directory is automatically loaded. See the [Configuration guide](/guide/configuration) for all available config classes.

| Convention    | Example             |
| ------------- | ------------------- |
| **Location**  | `app/`              |
| **File name** | `{name}.config.php` |

```php
// app/render-api.config.php
use Studiometa\Foehn\Config\RenderApiConfig;

return new RenderApiConfig(
    templates: ['partials/*', 'components/*'],
);
```

Environment-specific configs are also supported:

| File                           | Environment |
| ------------------------------ | ----------- |
| `{name}.config.php`            | All         |
| `{name}.local.config.php`      | Development |
| `{name}.production.config.php` | Production  |
| `{name}.staging.config.php`    | Staging     |

## Twig Template Conventions

### Template Locations

| Template Type           | Location                | Example                                             |
| ----------------------- | ----------------------- | --------------------------------------------------- |
| **Base layouts**        | `templates/layouts/`    | `templates/layouts/base.twig`                       |
| **Block templates**     | `templates/blocks/`     | `templates/blocks/hero.twig`                        |
| **Components**          | `templates/components/` | `templates/components/button.twig`                  |
| **Page templates**      | `templates/pages/`      | `templates/pages/home.twig`                         |
| **Pattern templates**   | `templates/patterns/`   | `templates/patterns/hero.twig`                      |

### Template Naming

| WordPress Hierarchy       | Twig Template                                                    |
| ------------------------- | ---------------------------------------------------------------- |
| `index.php`               | `templates/pages/index.twig`                                     |
| `front-page.php`          | `templates/pages/home.twig`                                      |
| `single.php`              | `templates/pages/single.twig`                                    |
| `single-{post_type}.php`  | `templates/pages/single-{post_type}.twig`                        |
| `archive.php`             | `templates/pages/archive.twig`                                   |
| `archive-{post_type}.php` | `templates/pages/archive-{post_type}.twig`                       |
| `page.php`                | `templates/pages/page.twig`                                      |
| `page-{slug}.php`         | `templates/pages/page-{slug}.twig`                               |
| `category.php`            | `templates/pages/category.twig`                                  |
| `taxonomy-{taxonomy}.php` | `templates/pages/taxonomy-{taxonomy}.twig`                       |
| `search.php`              | `templates/pages/search.twig`                                    |
| `404.php`                 | `templates/pages/404.twig`                                       |

### Block Template Naming

Block templates should match the block name (without prefix):

```php
// Block: #[AsAcfBlock(name: 'hero')]
// Template: templates/blocks/hero.twig

// Block: #[AsAcfBlock(name: 'feature-grid')]
// Template: templates/blocks/feature-grid.twig

// Block: #[AsBlock(name: 'theme/counter')]
// Template: templates/blocks/counter.twig
```

### Component Template Conventions

Components should be self-contained and reusable:

```twig
{# templates/components/button.twig #}
{% set classes = html_classes('btn', {
    'btn--primary': variant == 'primary',
    'btn--secondary': variant == 'secondary',
    'btn--large': size == 'large',
}) %}

<a href="{{ url }}" class="{{ classes }}">
    {{ label }}
</a>
```

Usage:

```twig
{% include 'components/button.twig' with {
    label: 'Learn More',
    url: '/about',
    variant: 'primary',
} %}
```

## Namespace Conventions

Use a consistent namespace structure:

```php
// Root namespace (defined in composer.json)
"autoload": {
    "psr-4": {
        "App\\": "app/"
    }
}
```

| Directory               | Namespace              |
| ----------------------- | ---------------------- |
| `app/Blocks/`           | `App\Blocks`           |
| `app/Console/`          | `App\Console`          |
| `app/ContextProviders/` | `App\ContextProviders` |
| `app/Controllers/`      | `App\Controllers`      |
| `app/Hooks/`            | `App\Hooks`            |
| `app/Models/`           | `App\Models`           |
| `app/Patterns/`         | `App\Patterns`         |
| `app/Rest/`             | `App\Rest`             |
| `app/Services/`         | `App\Services`         |
| `app/Shortcodes/`       | `App\Shortcodes`       |
| `app/Taxonomies/`       | `App\Taxonomies`       |

## Migration from wp-toolkit

If migrating from `studiometa/wp-toolkit`, the directory structure changes significantly. See the [Migration Guide](./migration-wp-toolkit.md) for details.

### Key Changes

| wp-toolkit                            | Føhn                               |
| ------------------------------------- | ---------------------------------- |
| `app/PostTypes/ProductPostType.php`   | `app/Models/Product.php`           |
| `app/Taxonomies/CategoryTaxonomy.php` | `app/Taxonomies/Category.php`      |
| `app/Blocks/HeroBlock.php`            | `app/Blocks/Hero/HeroBlock.php`    |
| Manual Manager registration           | Automatic discovery                |

### File Relocation Checklist

1. **Post types**: Move from `app/PostTypes/` to `app/Models/`, rename from `{Name}PostType.php` to `{Name}.php`
2. **Taxonomies**: Keep in `app/Taxonomies/`, rename from `{Name}Taxonomy.php` to `{Name}.php`
3. **Blocks**: Move from `app/Blocks/{Name}Block.php` to `app/Blocks/{Name}/{Name}Block.php`
4. **Hooks**: Create `app/Hooks/` directory and extract hooks from `functions.php`
5. **Context Providers**: Create `app/ContextProviders/` directory
6. **Controllers**: Create `app/Controllers/` directory

## Best Practices

### Class Design

- Use `final` for classes not designed for inheritance
- Use `readonly` for immutable classes (blocks, services)
- Use constructor property promotion
- Implement the appropriate interface

```php
// Good
final readonly class HeroBlock implements AcfBlockInterface {}

// Avoid
class HeroBlock {}
```

### Single Responsibility

Each class should have one responsibility:

```php
// Good: Separate classes for different concerns
final class ThemeHooks {}      // Theme setup
final class AssetHooks {}      // Asset enqueuing
final class AdminHooks {}      // Admin customizations

// Avoid: One class handling everything
final class Hooks {}           // Too broad
```

### Dependency Injection

Inject dependencies through constructors:

```php
final readonly class ProductController implements TemplateControllerInterface
{
    public function __construct(
        private ViewEngineInterface $view,
        private CartService $cart,
    ) {}
}
```

### File Organization

- One class per file
- File name matches class name
- Group related classes in directories

## Enforcing Conventions with Mago

[Mago](https://mago.carthage.software/) is a fast PHP toolchain that includes an architectural guard feature. You can use it to automatically enforce theme conventions.

### Installation

```bash
composer require --dev carthage-software/mago
```

### Quick Setup

Føhn includes a ready-to-use Mago configuration. Copy it to your theme:

```bash
cp vendor/studiometa/foehn/resources/mago-theme.toml mago.toml
```

Then run:

```bash
mago guard  # Check conventions
```

### Manual Configuration

If you prefer to configure Mago manually, add the following rules to your theme's `mago.toml`:

<details>
<summary>Click to expand full configuration</summary>

```toml
php-version = "8.4"

[source]
paths = ["app"]
includes = ["vendor"]
excludes = ["cache/**", "var/**", "node_modules/**"]

# =============================================================================
# Structural Guard Rules
# =============================================================================
# These rules enforce naming conventions and class structure for Føhn themes.

# -----------------------------------------------------------------------------
# Blocks: Must be final readonly, named *Block, implement interface
# -----------------------------------------------------------------------------
[[guard.structural.rules]]
on               = "App\\Blocks\\**"
target           = "class"
must-be-named    = "*Block"
must-be-final    = true
must-be-readonly = true
reason           = "Block classes must be final readonly and named *Block."

[[guard.structural.rules]]
on               = "App\\Blocks\\**"
target           = "class"
must-implement   = [
    ["Studiometa\\Foehn\\Contracts\\AcfBlockInterface"],
    ["Studiometa\\Foehn\\Contracts\\BlockInterface"],
    ["Studiometa\\Foehn\\Contracts\\InteractiveBlockInterface"],
]
reason           = "Block classes must implement AcfBlockInterface, BlockInterface, or InteractiveBlockInterface."

# -----------------------------------------------------------------------------
# Hooks: Must be final and named *Hooks
# -----------------------------------------------------------------------------
[[guard.structural.rules]]
on            = "App\\Hooks\\**"
target        = "class"
must-be-named = "*Hooks"
must-be-final = true
reason        = "Hook classes must be final and named *Hooks."

# -----------------------------------------------------------------------------
# Models (Post Types): Must be final and extend Timber\Post
# -----------------------------------------------------------------------------
[[guard.structural.rules]]
on            = "App\\Models\\**"
target        = "class"
must-be-final = true
must-extend   = "Timber\\Post"
not-on        = "App\\Models\\**Interface"
reason        = "Model classes must be final and extend Timber\\Post."

# -----------------------------------------------------------------------------
# Taxonomies: Must be final
# -----------------------------------------------------------------------------
[[guard.structural.rules]]
on            = "App\\Taxonomies\\**"
target        = "class"
must-be-final = true
reason        = "Taxonomy classes must be final."

# -----------------------------------------------------------------------------
# Patterns: Must be final readonly, named *Pattern, implement interface
# -----------------------------------------------------------------------------
[[guard.structural.rules]]
on               = "App\\Patterns\\**"
target           = "class"
must-be-named    = "*Pattern"
must-be-final    = true
must-be-readonly = true
reason           = "Pattern classes must be final readonly and named *Pattern."

[[guard.structural.rules]]
on             = "App\\Patterns\\**"
target         = "class"
must-implement = "Studiometa\\Foehn\\Contracts\\BlockPatternInterface"
reason         = "Pattern classes must implement BlockPatternInterface."

# -----------------------------------------------------------------------------
# REST Endpoints: Must be final and named *Endpoint
# -----------------------------------------------------------------------------
[[guard.structural.rules]]
on            = "App\\Rest\\**"
target        = "class"
must-be-named = "*Endpoint"
must-be-final = true
reason        = "REST endpoint classes must be final and named *Endpoint."

# -----------------------------------------------------------------------------
# Services: Must be final readonly and named *Service
# -----------------------------------------------------------------------------
[[guard.structural.rules]]
on               = "App\\Services\\**"
target           = "class"
must-be-named    = "*Service"
must-be-final    = true
must-be-readonly = true
reason           = "Service classes must be final readonly and named *Service."

# -----------------------------------------------------------------------------
# Shortcodes: Must be final and named *Shortcode
# -----------------------------------------------------------------------------
[[guard.structural.rules]]
on            = "App\\Shortcodes\\**"
target        = "class"
must-be-named = "*Shortcode"
must-be-final = true
reason        = "Shortcode classes must be final and named *Shortcode."

# -----------------------------------------------------------------------------
# Context Providers: Must be final, named *ContextProvider, implement interface
# -----------------------------------------------------------------------------
[[guard.structural.rules]]
on            = "App\\ContextProviders\\**"
target        = "class"
must-be-named = "*ContextProvider"
must-be-final = true
reason        = "Context provider classes must be final and named *ContextProvider."

[[guard.structural.rules]]
on             = "App\\ContextProviders\\**"
target         = "class"
must-implement = "Studiometa\\Foehn\\Contracts\\ContextProviderInterface"
reason         = "Context provider classes must implement ContextProviderInterface."

# -----------------------------------------------------------------------------
# CLI Commands: Must be final and named *Command
# -----------------------------------------------------------------------------
[[guard.structural.rules]]
on            = "App\\Console\\**"
target        = "class"
must-be-named = "*Command"
must-be-final = true
reason        = "CLI command classes must be final and named *Command."

# -----------------------------------------------------------------------------
# Template Controllers: Must be final, named *Controller, implement interface
# -----------------------------------------------------------------------------
[[guard.structural.rules]]
on            = "App\\Controllers\\**"
target        = "class"
must-be-named = "*Controller"
must-be-final = true
reason        = "Template controller classes must be final and named *Controller."

[[guard.structural.rules]]
on             = "App\\Controllers\\**"
target         = "class"
must-implement = "Studiometa\\Foehn\\Contracts\\TemplateControllerInterface"
reason         = "Template controller classes must implement TemplateControllerInterface."
```

</details>

### Running the Guard

```bash
# Check all structural rules
mago guard

# Check with detailed output
mago guard --reporting-format rich

# Check specific directory
mago guard app/Blocks/
```

### Example Output

When a convention is violated, Mago provides clear error messages:

```
error[structural-violation]: Block classes must be final readonly and named *Block.
  ┌─ app/Blocks/Hero/Hero.php:8:1
  │
8 │ class Hero implements AcfBlockInterface
  │ ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
  │
  = The class `App\Blocks\Hero\Hero` does not match the required name pattern `*Block`.
  = Consider renaming to `HeroBlock`.
```

### Customizing Rules

You can adjust the rules to match your team's conventions:

```toml
# Example: Allow non-readonly services
[[guard.structural.rules]]
on            = "App\\Services\\**"
target        = "class"
must-be-named = "*Service"
must-be-final = true
# must-be-readonly = true  # Commented out to allow mutable services
reason        = "Service classes must be final and named *Service."
```

### CI Integration

Add Mago guard to your CI pipeline:

```yaml
# .github/workflows/ci.yml
- name: Check conventions
  run: composer exec mago guard
```

## See Also

- [Getting Started](./getting-started.md)
- [Migration from wp-toolkit](./migration-wp-toolkit.md)
- [Post Types](./post-types.md)
- [ACF Blocks](./acf-blocks.md)
- [Context Providers](./context-providers.md)
- [Template Controllers](./template-controllers.md)
