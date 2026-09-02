# Starter Theme

`studiometa/foehn-starter` is the minimum a new project needs: the boot, the configuration, the templates a theme cannot render without, and the front-end build. Nothing else.

That is deliberate. A starting point you delete half of is worse than one you add to, so the demonstrations live in [the demo project](/guide/demo) — every attribute the framework ships, in a theme you can read and run.

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
│   │   ├── ContextProviders/   # GlobalContextProvider — data every template gets
│   │   ├── Controllers/        # single, archive, search, 404
│   │   ├── Hooks/              # theme supports, excerpt length
│   │   ├── Menus/              # header, footer, legal
│   │   └── foehn.config.php    # Framework configuration
│   ├── assets/
│   │   ├── css/app.css         # Tailwind entry point
│   │   └── js/app.js           # js-toolkit entry point
│   ├── templates/              # Twig templates
│   │   ├── layouts/base.twig
│   │   ├── pages/              # single, archive, search, 404
│   │   └── components/         # header, footer, card, pagination
│   ├── functions.php           # Single boot line
│   └── style.css               # Theme header
│
├── web/                        # Generated document root (gitignored)
│   ├── wp/                     # WordPress core
│   ├── wp-content/             # Plugins, uploads
│   └── wp-config.php           # Generated config
│
├── .ddev/                      # DDEV configuration
├── vite.config.js              # Vite, with @studiometa/foehn-vite-plugin
├── .env                        # Environment variables
└── composer.json               # Dependencies
```

## What's Included

Every class in `theme/app/` is one a theme needs before it renders anything:

| Class               | Why it is here                                                       |
| ------------------- | -------------------------------------------------------------------- |
| `Controllers/`      | Answer WordPress's template hierarchy — single, archive, search, 404 |
| `Menus/`            | The three locations `header.twig` and `footer.twig` read             |
| `ContextProviders/` | Puts `current_year` and `is_home` in every template                  |
| `Hooks/ThemeHooks`  | Theme supports, excerpt length and more                              |
| `foehn.config.php`  | The discovery cache, and the framework's cleanup and security hooks  |

There are no post types, blocks, taxonomies, settings pages or bindings. Add your own with the [`make:` commands](/guide/cli-commands), or copy one from [the demo](/guide/demo).

### Security & Cleanup Hooks

`theme/app/foehn.config.php` opts into the framework's own:

```php
return new FoehnConfig(
    discoveryCacheStrategy: DiscoveryCacheStrategy::FULL,
    hooks: [
        CleanHeadTags::class,             // Remove unnecessary <head> tags
        DisableEmoji::class,              // Remove emoji scripts and styles
        DisableOembed::class,             // Remove oEmbed discovery
        DisableVersionDisclosure::class,  // Hide the WordPress version
        DisableXmlRpc::class,             // Disable XML-RPC and pingbacks
        GenericLoginErrors::class,        // Hide username enumeration on login
        YouTubeNoCookieHooks::class,      // Use the no-cookie YouTube domain
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

# local, development, staging or production
WP_ENVIRONMENT_TYPE=local
WP_DEBUG=true
WP_HOME=https://my-project.ddev.site
```

`WP_ENVIRONMENT_TYPE` is the name WordPress itself uses, and the one every part of the framework reads through [`Env`](/api/helpers#env). Anything outside `production` is treated as non-production: the page cache stays inert and the indexing guard keeps the site out of search results.

## Deployment

For production:

1. Set `WP_ENVIRONMENT_TYPE=production` and `WP_DEBUG=false`
2. Discovery cache is already enabled (`DiscoveryCacheStrategy::FULL`)
3. After deployment, warm the cache:
   ```bash
   wp foehn discovery:generate
   ```

## Next Steps

- Read [the demo](/guide/demo) for one worked example of every attribute
- Learn about [Post Types](./post-types.md) to add your first one
- Declare custom fields with [#[AsPostMeta]](/api/as-post-meta), or add [ACF](./acf-blocks.md) when you want its editing UI
- Configure [Template Controllers](./template-controllers.md) for complex layouts
- Review [Theme Conventions](./theme-conventions.md) for best practices
