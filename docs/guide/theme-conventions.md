# Theme Conventions

This guide establishes the recommended structure and naming conventions for Foehn-based WordPress themes.

## Directory Structure

```
theme/
├── app/                          # PHP application code
│   ├── Blocks/                   # ACF and native blocks
│   │   ├── Hero/
│   │   │   └── HeroBlock.php
│   │   └── Features/
│   │       └── FeaturesBlock.php
│   ├── Hooks/                    # WordPress action/filter handlers
│   │   ├── ThemeHooks.php
│   │   ├── AssetHooks.php
│   │   └── AdminHooks.php
│   ├── Models/                   # Post types and taxonomies
│   │   ├── Product.php
│   │   ├── Event.php
│   │   └── ProductCategory.php
│   ├── Patterns/                 # Block patterns
│   │   └── HeroPattern.php
│   ├── Rest/                     # REST API endpoints
│   │   └── ProductsEndpoint.php
│   ├── Services/                 # Business logic services
│   │   ├── CartService.php
│   │   └── NewsletterService.php
│   ├── Shortcodes/               # Shortcode handlers
│   │   └── ButtonShortcode.php
│   └── Views/                    # View layer
│       ├── Composers/            # View composers
│       │   ├── GlobalComposer.php
│       │   └── NavigationComposer.php
│       └── Controllers/          # Template controllers
│           ├── HomeController.php
│           └── SingleController.php
├── views/                        # Twig templates
│   ├── base.twig                 # Base layout
│   ├── blocks/                   # Block templates
│   │   ├── hero.twig
│   │   └── features.twig
│   ├── components/               # Reusable components
│   │   ├── button.twig
│   │   ├── card.twig
│   │   └── pagination.twig
│   ├── pages/                    # Page-specific templates
│   │   ├── home.twig
│   │   └── contact.twig
│   └── partials/                 # Template partials
│       ├── header.twig
│       ├── footer.twig
│       └── sidebar.twig
├── assets/                       # Source assets
│   ├── scripts/
│   └── styles/
├── dist/                         # Compiled assets
├── functions.php                 # Kernel bootstrap
└── style.css                     # Theme metadata
```

## PHP Naming Conventions

### Post Types

| Convention | Example |
| --- | --- |
| **Location** | `app/Models/` |
| **Class name** | Singular PascalCase |
| **File name** | `{ClassName}.php` |

```php
// app/Models/Product.php
#[AsPostType(name: 'product', singular: 'Product', plural: 'Products')]
final class Product extends Post {}

// app/Models/TeamMember.php
#[AsPostType(name: 'team_member', singular: 'Team Member', plural: 'Team Members')]
final class TeamMember extends Post {}
```

### Taxonomies

| Convention | Example |
| --- | --- |
| **Location** | `app/Models/` (alongside post types) |
| **Class name** | Singular PascalCase |
| **File name** | `{ClassName}.php` |

```php
// app/Models/ProductCategory.php
#[AsTaxonomy(name: 'product_category', postTypes: ['product'])]
final class ProductCategory {}

// app/Models/EventType.php
#[AsTaxonomy(name: 'event_type', postTypes: ['event'])]
final class EventType {}
```

### ACF Blocks

| Convention | Example |
| --- | --- |
| **Location** | `app/Blocks/{BlockName}/` |
| **Class name** | `{BlockName}Block` |
| **File name** | `{BlockName}Block.php` |

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

| Convention | Example |
| --- | --- |
| **Location** | `app/Blocks/{BlockName}/` |
| **Class name** | `{BlockName}Block` |
| **File name** | `{BlockName}Block.php` |

```php
// app/Blocks/Counter/CounterBlock.php
#[AsBlock(name: 'theme/counter', title: 'Counter')]
final readonly class CounterBlock implements InteractiveBlockInterface {}
```

### Hooks

| Convention | Example |
| --- | --- |
| **Location** | `app/Hooks/` |
| **Class name** | `{Domain}Hooks` |
| **File name** | `{Domain}Hooks.php` |

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

### View Composers

| Convention | Example |
| --- | --- |
| **Location** | `app/Views/Composers/` |
| **Class name** | `{Name}Composer` |
| **File name** | `{Name}Composer.php` |

```php
// app/Views/Composers/GlobalComposer.php
#[AsViewComposer('*')]
final class GlobalComposer implements ViewComposerInterface {}

// app/Views/Composers/NavigationComposer.php
#[AsViewComposer('*')]
final class NavigationComposer implements ViewComposerInterface {}

// app/Views/Composers/ProductComposer.php
#[AsViewComposer('single-product')]
final class ProductComposer implements ViewComposerInterface {}
```

### Template Controllers

| Convention | Example |
| --- | --- |
| **Location** | `app/Views/Controllers/` |
| **Class name** | `{Template}Controller` |
| **File name** | `{Template}Controller.php` |

```php
// app/Views/Controllers/HomeController.php
#[AsTemplateController('front-page')]
final class HomeController implements TemplateControllerInterface {}

// app/Views/Controllers/SingleProductController.php
#[AsTemplateController('single-product')]
final class SingleProductController implements TemplateControllerInterface {}

// app/Views/Controllers/ArchiveController.php
#[AsTemplateController('archive')]
final class ArchiveController implements TemplateControllerInterface {}
```

### REST Endpoints

| Convention | Example |
| --- | --- |
| **Location** | `app/Rest/` |
| **Class name** | `{Resource}Endpoint` |
| **File name** | `{Resource}Endpoint.php` |

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

| Convention | Example |
| --- | --- |
| **Location** | `app/Shortcodes/` |
| **Class name** | `{Name}Shortcode` |
| **File name** | `{Name}Shortcode.php` |

```php
// app/Shortcodes/ButtonShortcode.php
#[AsShortcode('button')]
final class ButtonShortcode {
    public function render(array $atts, ?string $content): string {}
}
```

### Block Patterns

| Convention | Example |
| --- | --- |
| **Location** | `app/Patterns/` |
| **Class name** | `{Name}Pattern` |
| **File name** | `{Name}Pattern.php` |

```php
// app/Patterns/HeroPattern.php
#[AsBlockPattern(name: 'theme/hero', title: 'Hero Section')]
final readonly class HeroPattern implements BlockPatternInterface {}
```

### Services

| Convention | Example |
| --- | --- |
| **Location** | `app/Services/` |
| **Class name** | `{Name}Service` |
| **File name** | `{Name}Service.php` |

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

## Twig Template Conventions

### Template Locations

| Template Type | Location | Example |
| --- | --- | --- |
| **Base layouts** | `views/` | `views/base.twig` |
| **WordPress templates** | `views/` | `views/single.twig`, `views/archive.twig` |
| **Block templates** | `views/blocks/` | `views/blocks/hero.twig` |
| **Page templates** | `views/pages/` | `views/pages/home.twig` |
| **Partials** | `views/partials/` | `views/partials/header.twig` |
| **Components** | `views/components/` | `views/components/button.twig` |
| **Pattern templates** | `views/patterns/` | `views/patterns/hero.twig` |

### Template Naming

| WordPress Hierarchy | Twig Template |
| --- | --- |
| `index.php` | `views/index.twig` |
| `front-page.php` | `views/front-page.twig` or `views/pages/home.twig` |
| `single.php` | `views/single.twig` |
| `single-{post_type}.php` | `views/single-{post_type}.twig` |
| `archive.php` | `views/archive.twig` |
| `archive-{post_type}.php` | `views/archive-{post_type}.twig` |
| `page.php` | `views/page.twig` |
| `page-{slug}.php` | `views/page-{slug}.twig` |
| `category.php` | `views/category.twig` |
| `taxonomy-{taxonomy}.php` | `views/taxonomy-{taxonomy}.twig` |
| `search.php` | `views/search.twig` |
| `404.php` | `views/404.twig` |

### Block Template Naming

Block templates should match the block name (without prefix):

```php
// Block: #[AsAcfBlock(name: 'hero')]
// Template: views/blocks/hero.twig

// Block: #[AsAcfBlock(name: 'feature-grid')]
// Template: views/blocks/feature-grid.twig

// Block: #[AsBlock(name: 'theme/counter')]
// Template: views/blocks/counter.twig
```

### Component Template Conventions

Components should be self-contained and reusable:

```twig
{# views/components/button.twig #}
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

### Partial Template Conventions

Partials are template fragments that are included in layouts:

```twig
{# views/partials/header.twig #}
<header class="site-header">
    <div class="site-header__logo">
        <a href="{{ site.url }}">{{ site.name }}</a>
    </div>
    <nav class="site-header__nav">
        {% include 'partials/navigation.twig' with { menu: menus.primary } %}
    </nav>
</header>
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

| Directory | Namespace |
| --- | --- |
| `app/Blocks/` | `App\Blocks` |
| `app/Hooks/` | `App\Hooks` |
| `app/Models/` | `App\Models` |
| `app/Patterns/` | `App\Patterns` |
| `app/Rest/` | `App\Rest` |
| `app/Services/` | `App\Services` |
| `app/Shortcodes/` | `App\Shortcodes` |
| `app/Views/Composers/` | `App\Views\Composers` |
| `app/Views/Controllers/` | `App\Views\Controllers` |

## Migration from wp-toolkit

If migrating from `studiometa/wp-toolkit`, the directory structure changes significantly. See the [Migration Guide](./migration-wp-toolkit.md) for details.

### Key Changes

| wp-toolkit | Foehn |
| --- | --- |
| `app/PostTypes/ProductPostType.php` | `app/Models/Product.php` |
| `app/Taxonomies/CategoryTaxonomy.php` | `app/Models/Category.php` |
| `app/Blocks/HeroBlock.php` | `app/Blocks/Hero/HeroBlock.php` |
| Manual Manager registration | Automatic discovery |

### File Relocation Checklist

1. **Post types**: Move from `app/PostTypes/` to `app/Models/`, rename from `{Name}PostType.php` to `{Name}.php`
2. **Taxonomies**: Move from `app/Taxonomies/` to `app/Models/`, rename from `{Name}Taxonomy.php` to `{Name}.php`
3. **Blocks**: Move from `app/Blocks/{Name}Block.php` to `app/Blocks/{Name}/{Name}Block.php`
4. **Hooks**: Create `app/Hooks/` directory and extract hooks from `functions.php`
5. **Views**: Create `app/Views/Composers/` and `app/Views/Controllers/` directories

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

## See Also

- [Getting Started](./getting-started.md)
- [Migration from wp-toolkit](./migration-wp-toolkit.md)
- [Post Types](./post-types.md)
- [ACF Blocks](./acf-blocks.md)
- [View Composers](./view-composers.md)
- [Template Controllers](./template-controllers.md)
