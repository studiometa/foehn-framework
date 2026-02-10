# Foehn Starter Theme

A complete WordPress starter theme demonstrating all [Foehn](https://github.com/studiometa/foehn) features.

## Quick Start with DDEV

```bash
composer create-project studiometa/foehn-starter my-project
cd my-project
ddev start
```

That's it. DDEV will:

1. Start PHP 8.4 + MariaDB + nginx
2. Run `composer install` (generates `web/`, symlinks, wp-config.php)
3. Install WordPress with admin/admin credentials
4. Activate the starter theme and Timber plugin

Open the site: `ddev launch`
Admin panel: `ddev launch /wp/wp-admin` (admin / admin)

## Quick Start without DDEV

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
│   │   ├── ContextProviders/   # Context providers
│   │   ├── Controllers/        # Template controllers
│   │   ├── Hooks/              # WordPress hooks (actions & filters)
│   │   ├── Models/             # Custom post types (Timber models)
│   │   ├── Taxonomies/         # Custom taxonomies
│   │   └── foehn.config.php    # Framework configuration
│   ├── templates/              # Twig templates
│   │   ├── layouts/            # Base layouts
│   │   ├── pages/              # Page templates
│   │   └── components/         # Reusable components
│   ├── functions.php           # Single boot line
│   └── style.css               # Theme header
│
├── .ddev/                      # DDEV configuration
├── web/                        # Generated document root (gitignored)
├── .env                        # Environment variables (not needed with DDEV)
└── composer.json               # Dependencies
```

## What's included

### Custom Post Types

- **Product** — with price, sale price, and product categories
- **Testimonial** — with author info and ratings

### Custom Taxonomies

- **ProductCategory** — hierarchical, with custom rewrite
- **ProductTag** — flat taxonomy for products

### Template Controllers

- **SingleController** — handles all single post/page views
- **ArchiveController** — handles archives, categories, tags
- **SearchController** — search results page
- **Error404Controller** — 404 error page

### Hooks

- **ThemeHooks** — theme setup, image sizes, menus

### Context Providers

- **GlobalContextProvider** — site info, menus, available on all templates

### Built-in Foehn Hooks (via foehn.config.php)

- `CleanHeadTags` — removes unnecessary `<head>` tags
- `DisableEmoji` — removes emoji scripts/styles
- `DisableOembed` — removes oEmbed discovery
- `DisableVersionDisclosure` — hides WordPress version
- `DisableXmlRpc` — disables XML-RPC + pingback
- `GenericLoginErrors` — hides username enumeration on login
- `YouTubeNoCookieHooks` — converts YouTube embeds to no-cookie variant

## Development

### DDEV Commands

```bash
ddev start              # Start the environment
ddev stop               # Stop the environment
ddev launch             # Open the site in browser
ddev ssh                # SSH into the container
ddev composer install   # Run composer inside the container
ddev wp <command>       # Run WP-CLI commands
ddev describe           # Show URLs and connection info
```

### Without DDEV

```bash
# Requirements: PHP 8.4+, Composer 2.x, MySQL/MariaDB

# Development server (via wp-cli)
wp server --docroot=web

# Or configure nginx/apache to point to web/
```

## License

MIT
