# Starter Theme

`studiometa/foehn-starter` is the minimum a new project needs: the boot, the configuration, the templates a theme cannot render without, and the front-end build. Nothing else.

That is deliberate. A starting point you delete half of is worse than one you add to, so the demonstrations live in [the demo project](/guide/demo) — every attribute the framework ships, in a theme you can read and run.

## Quick Start

### With DDEV (Recommended)

```bash
composer create-project studiometa/foehn-starter my-project
cd my-project
ddev start
npm install && npm run build
```

`composer create-project`:

1. Installs the dependencies
2. Generates `web/`, the symlinks and `wp-config.php`
3. Writes `.env` from `.env.example`, with new security keys

`ddev start`:

1. Starts PHP 8.5, MariaDB and nginx
2. Runs `composer install` when `vendor/` is missing (a fresh clone)
3. Installs WordPress with `admin` / `admin` and activates the theme, when WordPress is not installed

The hook is in `.ddev/config.yaml`. It does not change a site that is already installed.

Open your site, and seed the menus:

```bash
ddev launch              # Frontend
ddev launch /wp/wp-admin # Admin (admin / admin)
ddev wp foehn seed       # The header, footer and legal menus
```

### Without DDEV

```bash
composer create-project studiometa/foehn-starter my-project
cd my-project
# Edit .env: database credentials and WP_HOME
```

Then point the document root of your web server to the `web/` directory, install WordPress (`wp core install`) and activate the theme (`wp theme activate starter-theme`).

## Project Structure

```
my-project/
├── theme/                          # WordPress theme (versioned)
│   ├── app/
│   │   ├── ContextProviders/       # GlobalContextProvider — data every template gets
│   │   ├── Controllers/            # single, page, archive, search, 404, and the password form
│   │   ├── Hooks/                  # AssetHooks (Vite build), ThemeHooks (theme supports, excerpt)
│   │   ├── Menus/                  # header, footer, legal
│   │   ├── Seeders/                # MenusSeeder — the three menus, for `wp foehn seed`
│   │   ├── foehn.config.php        # Framework configuration
│   │   └── page-cache.config.php   # Static page cache, production only
│   ├── assets/
│   │   ├── css/app.css             # Tailwind entry point
│   │   └── js/app.js               # js-toolkit entry point
│   ├── templates/                  # Twig templates
│   │   ├── layouts/base.twig
│   │   ├── pages/                  # single, page, archive, search, 404, password
│   │   ├── sections/               # posts — the archive list, which can render alone
│   │   └── components/             # header, footer, card, pagination
│   ├── functions.php               # Single boot line
│   └── style.css                   # Theme header
│
├── public/                         # Files copied into web/ (robots.txt, .well-known/)
├── web/                            # Generated document root (gitignored)
│   ├── wp/                         # WordPress core
│   ├── wp-content/                 # Plugins, uploads
│   └── wp-config.php               # Generated config
│
├── tests/
│   ├── php/                        # Unit tests: composer test
│   └── smoke/                      # Tests against the site in ddev: composer test:smoke, run.sh
├── .ddev/                          # DDEV configuration
├── vite.config.js                  # Vite, with @studiometa/foehn-vite-plugin
├── .env                            # Environment variables
└── composer.json                   # Dependencies
```

Do not put files in `web/`: the next `composer install` does not keep them. Put the files that must be at the document root, such as `robots.txt` or `.well-known/security.txt`, in `public/`. See [Public Files](./public-files.md).

## What's Included

Every class in `theme/app/` is one a theme needs before it renders anything:

| Class                   | Why it is here                                                                       |
| ----------------------- | ------------------------------------------------------------------------------------ |
| `Controllers/`          | Answer WordPress's template hierarchy — single, page, archive, search, 404           |
| `Menus/`                | The three locations `header.twig` and `footer.twig` read                             |
| `Seeders/MenusSeeder`   | Creates the three menus with a Home link, so `wp foehn seed` fills an empty database |
| `ContextProviders/`     | Puts `current_year` and `is_home` in every template                                  |
| `Hooks/AssetHooks`      | Enqueues the Vite build                                                              |
| `Hooks/ThemeHooks`      | Theme supports, excerpt length and more                                              |
| `foehn.config.php`      | The discovery cache, and the framework's cleanup and security hooks                  |
| `page-cache.config.php` | The [static page cache](./page-cache.md), on in production only                      |

There are no post types, blocks, taxonomies, settings pages or bindings. Add your own with the [`make:` commands](/guide/cli-commands), or copy one from [the demo](/guide/demo).

### Security & Cleanup Hooks

`theme/app/foehn.config.php` opts into the framework's own:

```php
return new FoehnConfig(
    discoveryCacheStrategy: DiscoveryCacheStrategy::FULL,
    hooks: [
        CleanHeadTags::class,             // Remove unnecessary <head> tags
        DisableEmoji::class,              // Remove emoji scripts and styles
        DisableFileEditor::class,         // No theme or plugin file editor in the admin
        DisableGlobalStyles::class,       // No theme.json styles from core (the starter has no theme.json)
        DisableOembed::class,             // Remove oEmbed discovery
        DisableVersionDisclosure::class,  // Hide the WordPress version
        DisableXmlRpc::class,             // Disable XML-RPC and pingbacks
        GenericLoginErrors::class,        // Hide username enumeration on login
        StudiometaUi::class,              // The @ui and @svg Twig namespaces of studiometa/ui
        YouTubeNoCookieHooks::class,      // Use the no-cookie YouTube domain
    ],
);
```

`SecurityHeaders` and `RestApiAuth` are opt-in. If you enable `SecurityHeaders`, or set security headers in the server, also set them in the page cache `responseHeaders`: a cache hit served by nginx does not send them. See [Built-in Hooks](./hooks.md#security-hooks) and [The headers of a hit](./page-cache.md#the-headers-of-a-hit).

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

The name `starter-theme` or `foehn-starter` is in these files. Change each one:

1. `composer.json`: `name`, and `extra.foehn.theme-name` (the theme directory in `web/wp-content/themes/`)
2. `package.json`: `name`
3. `theme/style.css`: `Theme Name` and `Text Domain`
4. `theme/assets/js/app.js`: `packageName`
5. `theme/app/Hooks/AssetHooks.php`: the `starter-styles` and `starter-app` handles
6. `.ddev/config.yaml`: `name`, and `WP_HOME` in `.env.example` and `.env`, together. A different name makes WordPress redirect to a host that ddev does not serve.

Then run `composer install` to regenerate the symlinks, `ddev restart`, and `ddev wp theme activate <your-theme-name>`.

### Add Plugins

Add WordPress plugins via Composer using [wpackagist](https://wpackagist.org/):

```bash
ddev composer require wpackagist-plugin/wordpress-seo
```

Commercial plugins are not on wpackagist. ACF Pro, for example, has its own Composer repository: see the [ACF documentation](https://www.advancedcustomfields.com/resources/installing-acf-pro-with-composer/).

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

# Locally only: run WordPress cron on page loads
DISABLE_WP_CRON=false
```

`WP_ENVIRONMENT_TYPE` is the name WordPress itself uses, and the one every part of the framework reads through [`Env`](/api/helpers#env). Anything outside `production` is treated as non-production: the page cache stays inert and the indexing guard keeps the site out of search results.

The generated `wp-config.php` turns the WordPress pseudo-cron off unless `DISABLE_WP_CRON=false`. `.env.example` sets it to `false`, so cron runs in a local project. In production, remove the line and run a real cron (see [Deployment](#deployment)).

## Deployment

For each release in production:

1. Set `WP_ENVIRONMENT_TYPE=production` and `WP_DEBUG=false` in the environment. Remove the `DISABLE_WP_CRON=false` line that `composer install` copies from `.env.example` into a new `.env`: production uses a real cron.
2. Install the dependencies: `composer install --no-dev`. The installer generates `web/` and copies `public/` into it.
3. Build the front-end: `npm ci && npm run build`.
4. Write the nginx snippet of the page cache, and include it in the server block: `wp foehn cache:config --server=nginx --write`. See [Installing the fast path](./page-cache.md#installing-the-fast-path).
5. Clear the page cache: `wp foehn cache:clear`. It is necessary with [bucket storage](./page-cache.md#storing-pages-in-the-bucket): the bucket keeps the pages of the previous release.
6. Run WordPress cron from a real cron, every minute: `wp cron event run --due-now`. See [The real cron](./verification.md#the-real-cron).
7. Check the release: `wp foehn verify --profile=production`. A failed check exits with a status that is not `0`. See [Verification](./verification.md).

The discovery cache is already on (`DiscoveryCacheStrategy::FULL`). `composer install` clears it, and the first request writes it again.

## Next Steps

- Read [the demo](/guide/demo) for one worked example of every attribute
- Learn about [Post Types](./post-types.md) to add your first one
- Declare custom fields with [#[AsPostMeta]](/api/as-post-meta), or add [ACF](./acf-blocks.md) when you want its editing UI
- Configure [Template Controllers](./template-controllers.md) for complex layouts
- Review [Theme Conventions](./theme-conventions.md) for best practices
