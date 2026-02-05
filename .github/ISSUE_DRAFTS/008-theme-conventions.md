# Define and document theme structure conventions

## Problem

Developers need clear guidance on where to place files in a Foehn-powered theme. Current documentation shows examples but doesn't establish **conventions** that can be enforced or scaffolded by CLI.

## Goals

1. Establish a standard directory structure
2. Document naming conventions
3. Ensure CLI commands generate files in the right places
4. Make it easy for teams to onboard

## Proposed directory structure

```
theme/
├── app/                            # PHP application code
│   │
│   ├── Blocks/                     # Gutenberg blocks
│   │   ├── Acf/                    # ACF Blocks
│   │   │   ├── Hero/
│   │   │   │   └── HeroBlock.php
│   │   │   └── Gallery/
│   │   │       └── GalleryBlock.php
│   │   │
│   │   └── Native/                 # Native WordPress blocks (optional)
│   │       └── Accordion/
│   │           └── AccordionBlock.php
│   │
│   ├── Fields/                     # ACF field definitions
│   │   ├── Fragments/              # Reusable field builders
│   │   │   ├── ButtonLink.php
│   │   │   ├── Image.php
│   │   │   └── Wysiwyg.php
│   │   │
│   │   ├── PostType/               # Field groups for CPTs
│   │   │   ├── ProductFields.php
│   │   │   └── PropertyFields.php
│   │   │
│   │   ├── Page/                   # Field groups for pages
│   │   │   ├── FrontPageFields.php
│   │   │   └── FAQPageFields.php
│   │   │
│   │   ├── Taxonomy/               # Field groups for taxonomies
│   │   │   └── CategoryFields.php
│   │   │
│   │   └── Options/                # ACF Options pages
│   │       ├── ThemeSettings.php
│   │       └── FooterSettings.php
│   │
│   ├── Models/                     # Timber post type models
│   │   ├── Post.php                # Default WP post
│   │   ├── Page.php                # Default WP page
│   │   ├── Product.php             # Custom post type
│   │   └── Testimonial.php
│   │
│   ├── Taxonomies/                 # Taxonomy definitions
│   │   ├── ProductCategory.php
│   │   └── ProductTag.php
│   │
│   ├── Http/                       # HTTP layer
│   │   └── Controllers/            # Template controllers
│   │       ├── SingleController.php
│   │       ├── ArchiveController.php
│   │       ├── SearchController.php
│   │       └── Error404Controller.php
│   │
│   ├── Context/                    # Timber context providers
│   │   ├── GlobalContext.php
│   │   ├── SingleContext.php
│   │   └── ArchiveContext.php
│   │
│   ├── Hooks/                      # WordPress hooks
│   │   ├── ThemeHooks.php          # Theme setup, supports
│   │   ├── AdminHooks.php          # Admin customizations
│   │   └── PerformanceHooks.php    # Caching, optimization
│   │
│   ├── Services/                   # Business logic services
│   │   ├── ImageService.php
│   │   └── RelatedPostsService.php
│   │
│   ├── Menus/                      # Navigation menus
│   │   ├── HeaderMenu.php
│   │   ├── FooterMenu.php
│   │   └── LegalMenu.php
│   │
│   ├── ImageSizes/                 # Custom image sizes
│   │   ├── Card.php
│   │   ├── Hero.php
│   │   └── OgImage.php
│   │
│   ├── Patterns/                   # Block patterns (optional)
│   │   ├── HeroFullWidth.php
│   │   └── TeamGrid.php
│   │
│   └── Theme/                      # Theme configuration
│       └── ThemeConfig.php         # FSE theme.json config (optional)
│
├── templates/                      # Twig templates
│   ├── layouts/
│   │   ├── base.twig               # Main layout
│   │   └── blank.twig              # No header/footer
│   │
│   ├── pages/                      # Page templates
│   │   ├── single.twig
│   │   ├── single-product.twig
│   │   ├── archive.twig
│   │   ├── search.twig
│   │   ├── 404.twig
│   │   └── front-page.twig
│   │
│   ├── blocks/                     # Block templates
│   │   ├── hero.twig
│   │   ├── gallery.twig
│   │   └── testimonials.twig
│   │
│   ├── components/                 # Reusable UI components
│   │   ├── header.twig
│   │   ├── footer.twig
│   │   ├── card-post.twig
│   │   ├── pagination.twig
│   │   └── breadcrumb.twig
│   │
│   └── partials/                   # Small template fragments
│       ├── meta-post.twig
│       └── share-buttons.twig
│
├── assets/                         # Source assets
│   ├── css/
│   │   ├── app.scss
│   │   └── admin.scss
│   ├── js/
│   │   └── app.js
│   └── images/
│
├── dist/                           # Compiled assets (gitignored)
│
├── functions.php                   # Single line: Kernel::boot()
├── style.css                       # Theme header only
├── composer.json
├── package.json
└── README.md
```

## Naming conventions

### PHP Classes

| Type             | Convention             | Example                                       |
| ---------------- | ---------------------- | --------------------------------------------- |
| ACF Block        | `{Name}Block`          | `HeroBlock.php`                               |
| Field Group      | `{Context}Fields`      | `ProductFields.php`, `FrontPageFields.php`    |
| Field Fragment   | `{Name}`               | `ButtonLink.php`, `ResponsiveImage.php`       |
| Model            | `{PostType}`           | `Product.php`, `Testimonial.php`              |
| Taxonomy         | `{Name}`               | `ProductCategory.php`                         |
| Controller       | `{Template}Controller` | `SingleController.php`                        |
| Context Provider | `{Scope}Context`       | `GlobalContext.php`, `SingleContext.php`      |
| Service          | `{Domain}Service`      | `ImageService.php`, `RelatedPostsService.php` |
| Hooks            | `{Domain}Hooks`        | `ThemeHooks.php`, `AdminHooks.php`            |
| Menu             | `{Location}Menu`       | `HeaderMenu.php`, `FooterMenu.php`            |
| Image Size       | `{Name}`               | `Card.php`, `Hero.php`, `OgImage.php`         |

### Twig Templates

| Type      | Location                | Example                               |
| --------- | ----------------------- | ------------------------------------- |
| Layout    | `templates/layouts/`    | `base.twig`, `blank.twig`             |
| Page      | `templates/pages/`      | `single.twig`, `archive-product.twig` |
| Block     | `templates/blocks/`     | `hero.twig`, `gallery.twig`           |
| Component | `templates/components/` | `header.twig`, `card-post.twig`       |
| Partial   | `templates/partials/`   | `meta-post.twig`                      |
| Pattern   | `templates/patterns/`   | `hero-full-width.twig`                |

### Block templates naming

ACF Block `name` attribute should match template filename:

```php
#[AsAcfBlock(name: 'hero', ...)]  // → templates/blocks/hero.twig
#[AsAcfBlock(name: 'image-text', ...)]  // → templates/blocks/image-text.twig
```

## CLI commands mapping

| Command                                              | Output location                                  |
| ---------------------------------------------------- | ------------------------------------------------ |
| `make:block Hero --acf`                              | `app/Blocks/Acf/Hero/HeroBlock.php`              |
| `make:block Accordion --native`                      | `app/Blocks/Native/Accordion/AccordionBlock.php` |
| `make:model Product`                                 | `app/Models/Product.php`                         |
| `make:taxonomy ProductCategory`                      | `app/Taxonomies/ProductCategory.php`             |
| `make:field-group ProductFields --post-type=product` | `app/Fields/PostType/ProductFields.php`          |
| `make:field-group FrontPageFields --page=front-page` | `app/Fields/Page/FrontPageFields.php`            |
| `make:options-page ThemeSettings`                    | `app/Fields/Options/ThemeSettings.php`           |
| `make:context GlobalContext`                         | `app/Context/GlobalContext.php`                  |
| `make:controller SingleController`                   | `app/Http/Controllers/SingleController.php`      |
| `make:service ImageService`                          | `app/Services/ImageService.php`                  |
| `make:hooks ThemeHooks`                              | `app/Hooks/ThemeHooks.php`                       |
| `make:menu HeaderMenu`                               | `app/Menus/HeaderMenu.php`                       |
| `make:image-size Card`                               | `app/ImageSizes/Card.php`                        |

## Autoload configuration

```json
{
  "autoload": {
    "psr-4": {
      "App\\": "app/"
    }
  }
}
```

This maps:

- `App\Blocks\Acf\Hero\HeroBlock` → `app/Blocks/Acf/Hero/HeroBlock.php`
- `App\Models\Product` → `app/Models/Product.php`
- `App\Context\GlobalContext` → `app/Context/GlobalContext.php`

## Validation / Linting (future)

Consider a `foehn:validate` command that checks:

```bash
php foehn validate
# ✓ App namespace matches directory structure
# ✓ All ACF blocks have corresponding templates
# ✓ All models with #[AsPostType] extend Timber\Post
# ⚠ Missing template for block 'gallery' (templates/blocks/gallery.twig)
# ✗ ProductFields.php should be in app/Fields/PostType/
```

## Migration guide from wp-toolkit structure

| wp-toolkit                                | Foehn                                                        |
| ----------------------------------------- | ------------------------------------------------------------ |
| `app/ACF/Blocks/`                         | `app/Blocks/Acf/`                                            |
| `app/ACF/Groups/`                         | `app/Fields/PostType/`, `app/Fields/Page/`                   |
| `app/ACF/Fields/`                         | `app/Fields/Fragments/`                                      |
| `app/Managers/ThemeManager.php`           | `app/Hooks/ThemeHooks.php` + `app/Context/GlobalContext.php` |
| `app/Managers/ACFManager.php`             | Auto-discovered, no manager needed                           |
| `app/Managers/CustomPostTypesManager.php` | `app/Models/` with `#[AsPostType]`                           |
| `app/Managers/TaxonomiesManager.php`      | `app/Taxonomies/` with `#[AsTaxonomy]`                       |
| `app/Repositories/`                       | `app/Services/` or keep as-is                                |
| `app/Services/`                           | `app/Services/` (unchanged)                                  |

## Documentation sections needed

1. **Getting Started** - Quick setup with conventions
2. **Directory Structure** - Complete reference
3. **Naming Conventions** - Class and file naming rules
4. **CLI Reference** - All commands with examples
5. **Migration Guide** - From wp-toolkit to Foehn

## Tasks

- [ ] Finalize directory structure
- [ ] Update all CLI commands to follow conventions
- [ ] Add `--dry-run` flag to show where files will be created
- [ ] Create starter theme skeleton
- [ ] Write comprehensive documentation
- [ ] Consider `foehn:validate` command
- [ ] Add VS Code snippets for common patterns

## Labels

`documentation`, `conventions`, `priority-high`
