# Foehn Starter Theme

A minimal WordPress theme powered by [Føhn](https://github.com/studiometa/foehn-framework): the boot, the configuration, the templates a theme cannot render without, and the front-end build. Nothing else.

That is deliberate. A starting point you delete half of is worse than one you add to, so what is here is what every project needs, and the demonstrations live in [`studiometa/foehn-demo`](../demo) — every attribute the framework ships, in a working theme you can read.

> **Note**
> This package is part of the [Føhn Framework](https://github.com/studiometa/foehn-framework) monorepo.
> Please report issues and submit pull requests in the [main repository](https://github.com/studiometa/foehn-framework).

## Quick start with DDEV

```bash
composer create-project studiometa/foehn-starter my-project
cd my-project
ddev start
```

DDEV starts PHP 8.5, MariaDB and nginx, copies `.env.example` to `.env`, runs `composer install` (which generates `web/`, the symlinks and `wp-config.php`), installs WordPress with `admin` / `admin`, and activates the theme.

```bash
ddev launch              # the site
ddev launch /wp/wp-admin # the admin
```

## Quick start without DDEV

```bash
composer create-project studiometa/foehn-starter my-project
cd my-project
cp .env.example .env
# Edit .env with your database credentials
composer install
```

Then point your web server's document root at `web/`.

## What is here

```
my-project/
├── theme/
│   ├── app/
│   │   ├── ContextProviders/   # GlobalContextProvider — data every template gets
│   │   ├── Controllers/        # single, archive, search, 404
│   │   ├── Hooks/              # theme supports, excerpt length
│   │   ├── Menus/              # header, footer, legal
│   │   └── foehn.config.php    # discovery cache, opt-in cleanup and security hooks
│   ├── assets/
│   │   ├── css/app.css         # Tailwind entry point
│   │   └── js/app.js           # js-toolkit entry point
│   ├── templates/
│   │   ├── layouts/base.twig
│   │   ├── pages/              # single, archive, search, 404
│   │   └── components/         # header, footer, card, pagination
│   ├── functions.php           # one line: Kernel::boot()
│   └── style.css
│
├── .ddev/                      # DDEV configuration
├── vite.config.js              # Vite, with @studiometa/foehn-vite-plugin
├── web/                        # generated document root (gitignored)
├── .env                        # environment variables (DDEV supplies its own)
├── composer.json
└── package.json
```

Every class here is one a theme needs before it renders anything: the controllers that answer WordPress's template hierarchy, the menus the header and footer templates read, the context provider that puts `current_year` in the footer, and the theme supports.

`foehn.config.php` opts into the framework's own cleanup and security hooks — `CleanHeadTags`, `DisableEmoji`, `DisableOembed`, `DisableVersionDisclosure`, `DisableXmlRpc`, `GenericLoginErrors`, `YouTubeNoCookieHooks` — and turns the discovery cache on.

## Adding to it

```bash
ddev wp foehn make:post-type product
ddev wp foehn make:block hero
ddev wp foehn make:controller Product --templates=single-product
ddev wp foehn discovery:list          # what the framework found, and where
```

Or read [the demo](../demo), which has one of everything already written.

## Front-end

```bash
npm install
npm run dev             # Vite dev server, with HMR into WordPress
npm run build           # production build
npm run lint            # JS, CSS and Twig
npm run fix
```

There is no JavaScript test setup here, because there is no JavaScript to test yet. [The demo](../demo) has one configured with Vitest and Playwright, ready to copy.

## Tests

```bash
composer test:starter    # from the monorepo root
./tests/smoke/run.sh     # against a started ddev
```

The smoke test asks the one question this package has to answer: does a project created from it boot and serve a page? On 2026-08-19 the answer was no, with 1409 unit tests passing — those run against WordPress function stubs, so a discovery that registers nothing at all still passes them.

## License

MIT
