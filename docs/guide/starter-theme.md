# Starter Theme

The Føhn Starter Theme is a complete WordPress theme demonstrating all framework features. It's the fastest way to start a new Føhn project.

## Quick Start

### With DDEV (Recommended)

```bash
composer create-project studiometa/foehn-starter my-project
cd my-project
ddev start
```

That's it! DDEV will automatically:

1. Start PHP 8.5 + MariaDB + nginx
2. Create `.env` from `.env.example`
3. Run `composer install` (generates `web/`, symlinks, wp-config.php)
4. Install WordPress with admin/admin credentials
5. Activate the starter theme

Open your site:

```bash
ddev launch              # Frontend
ddev launch /wp/wp-admin # Admin (admin / admin)
```

### Without DDEV

```bash
composer create-project studiometa/foehn-starter my-project
cd my-project
cp .env.example .env
# Edit .env with your database credentials
composer install
```

Then point your web server's document root to the `web/` directory.

## Project Structure

```
my-project/
├── theme/                      # WordPress theme (versioned)
│   ├── app/
│   │   ├── Blocks/             # ACF & native blocks
│   │   ├── ContextProviders/   # Global context providers
│   │   ├── Controllers/        # Template controllers
│   │   ├── Data/               # DTOs for typed context
│   │   ├── Hooks/              # WordPress hooks
│   │   ├── ImageSizes/         # Custom image sizes
│   │   ├── Menus/              # Navigation menus
│   │   ├── Models/             # Custom post types
│   │   ├── Taxonomies/         # Custom taxonomies
│   │   └── foehn.config.php    # Framework configuration
│   ├── templates/              # Twig templates
│   │   ├── blocks/             # Block templates
│   │   ├── components/         # Reusable components
│   │   ├── layouts/            # Base layouts
│   │   └── pages/              # Page templates
│   ├── functions.php           # Single boot line
│   └── style.css               # Theme header
│
├── web/                        # Generated document root (gitignored)
│   ├── wp/                     # WordPress core
│   ├── wp-content/             # Plugins, uploads
│   └── wp-config.php           # Generated config
│
├── .ddev/                      # DDEV configuration
├── .env                        # Environment variables
└── composer.json               # Dependencies
```

## What's Included

### Custom Post Types

**Product** (`app/Models/Product.php`)

```php
#[AsPostType(
    name: 'product',
    singular: 'Produit',
    plural: 'Produits',
    public: true,
    hasArchive: true,
    menuIcon: 'dashicons-cart',
)]
final class Product extends TimberPost implements ConfiguresPostType
{
    public function price(): ?float { /* ... */ }
    public function formattedPrice(): string { /* ... */ }
    public function isOnSale(): bool { /* ... */ }
}
```

**Testimonial** (`app/Models/Testimonial.php`) — Customer reviews with ratings.

### Custom Taxonomies

- **ProductCategory** — Hierarchical product categories
- **ProductTag** — Flat tags for products

### Template Controllers

Controllers handle WordPress template hierarchy with dependency injection. The `handle()` method receives a typed `TemplateContext` object:

```php
use Studiometa\Foehn\Views\TemplateContext;

#[AsTemplateController(['single', 'single-*'])]
final readonly class SingleController implements TemplateControllerInterface
{
    public function __construct(
        private ViewEngineInterface $view,
    ) {}

    public function handle(TemplateContext $context): string
    {
        $post = $context->post; // Typed ?Post with IDE support

        return $this->view->renderFirst([
            "pages/single-{$post?->post_type}-{$post?->slug}",
            "pages/single-{$post?->post_type}",
            'pages/single',
        ], $context);
    }
}
```

Included controllers:
- **SingleController** — Single posts/pages
- **ArchiveController** — Archives, categories, tags, front page
- **SearchController** — Search results
- **Error404Controller** — 404 errors

### Context Providers

Global context available on all templates:

```php
use Studiometa\Foehn\Views\TemplateContext;

#[AsContextProvider('*')]
final class GlobalContextProvider implements ContextProviderInterface
{
    public function provide(TemplateContext $context): TemplateContext
    {
        // Note: site, user, post, posts are already in TemplateContext
        // Menus are auto-injected by MenuDiscovery
        return $context
            ->with('current_year', date('Y'))
            ->with('is_home', is_front_page());
    }
}
```

### Blocks

**Hero Block** (`app/Blocks/HeroBlock.php`) — Full-width banner demonstrating:
- ACF FieldsBuilder integration
- Built-in field fragments (`ButtonLinkBuilder`)
- Typed DTO context (`HeroContext`)
- `ImageData` and `LinkData` DTOs

### Menus & Image Sizes

- **Menus**: Header, Footer, Legal
- **Image Sizes**: Card (400×300), Hero (1920×800)

### Security & Cleanup Hooks

Pre-configured in `foehn.config.php`:

```php
return new FoehnConfig(
    discoveryCacheStrategy: DiscoveryCacheStrategy::FULL,
    hooks: [
        CleanHeadTags::class,      // Remove unnecessary <head> tags
        DisableEmoji::class,       // Remove emoji scripts/styles
        DisableOembed::class,      // Remove oEmbed discovery
        DisableVersionDisclosure::class, // Hide WP version
        DisableXmlRpc::class,      // Disable XML-RPC
        GenericLoginErrors::class, // Prevent username enumeration
        YouTubeNoCookieHooks::class, // YouTube no-cookie embeds
    ],
);
```

## DDEV Commands

```bash
ddev start              # Start the environment
ddev stop               # Stop the environment
ddev restart            # Restart after config changes
ddev launch             # Open site in browser
ddev ssh                # SSH into the container
ddev composer <cmd>     # Run Composer commands
ddev wp <cmd>           # Run WP-CLI commands
ddev describe           # Show URLs and info
```

## Customizing the Starter

### Rename the Theme

1. Update `theme/style.css` with your theme name
2. Update `composer.json` extra config:
   ```json
   "extra": {
       "foehn": {
           "theme-name": "your-theme-name"
       }
   }
   ```
3. Run `composer install` to regenerate symlinks

### Add Plugins

Add WordPress plugins via Composer using [wpackagist](https://wpackagist.org/):

```bash
ddev composer require wpackagist-plugin/advanced-custom-fields-pro
```

### Environment Variables

The `.env` file controls database connection and environment:

```env
DB_NAME=db
DB_USER=db
DB_PASSWORD=db
DB_HOST=db

WP_ENV=development
WP_DEBUG=true
WP_HOME=https://my-project.ddev.site
```

## Deployment

For production:

1. Set `WP_ENV=production` and `WP_DEBUG=false`
2. Discovery cache is already enabled (`DiscoveryCacheStrategy::FULL`)
3. After deployment, warm the cache:
   ```bash
   wp foehn discovery:warm
   ```

## Next Steps

- Learn about [Post Types](./post-types.md) to customize Product/Testimonial
- Add [ACF Blocks](./acf-blocks.md) for custom content blocks
- Configure [Template Controllers](./template-controllers.md) for complex layouts
- Review [Theme Conventions](./theme-conventions.md) for best practices
