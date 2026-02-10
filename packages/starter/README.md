# Foehn Starter Theme

A complete WordPress starter theme demonstrating all [Foehn](https://github.com/studiometa/foehn) features.

## Quick Start

```bash
composer create-project studiometa/foehn-starter my-project
cd my-project
cp .env.example .env
# Edit .env with your database credentials
```

## Project Structure

```
my-project/
├── theme/                      # WordPress theme (versioned)
│   ├── app/
│   │   ├── Hooks/              # WordPress hooks (actions & filters)
│   │   ├── Http/Controllers/   # Template controllers
│   │   ├── Models/             # Custom post types (Timber models)
│   │   ├── Taxonomies/         # Custom taxonomies
│   │   └── Views/Composers/    # Context providers
│   ├── templates/              # Twig templates
│   │   ├── layouts/            # Base layouts
│   │   ├── pages/              # Page templates
│   │   └── components/         # Reusable components
│   ├── functions.php           # Single boot line
│   └── style.css               # Theme header
│
├── config/                     # Configuration files
├── web/                        # Generated document root (gitignored)
├── .env                        # Environment variables
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
- **SecurityHooks** — head cleanup, XML-RPC, login errors

### Context Providers

- **GlobalComposer** — site info, menus, available on all templates

### Built-in Foehn Hooks

- `CleanHeadTags` — removes unnecessary `<head>` tags
- `DisableEmoji` — removes emoji scripts/styles
- `DisableXmlRpc` — disables XML-RPC
- `YouTubeNoCookieHooks` — converts YouTube embeds to no-cookie variant

## Development

### Requirements

- PHP 8.4+
- Composer 2.x
- Node.js 20+ (for frontend assets)

### Commands

```bash
# WordPress development server (via wp-cli)
wp server --docroot=web

# Or configure your web server to point to the web/ directory
```

## License

MIT
