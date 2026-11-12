# Foehn Starter Theme

A minimal WordPress theme powered by [Føhn](https://github.com/studiometa/foehn-framework): the boot, the configuration, the templates a theme cannot render without, and the front-end build. Nothing else.

That is deliberate. A starting point you delete half of is worse than one you add to, so what is here is what every project needs, and the demonstrations live in [`studiometa/foehn-demo`](https://github.com/studiometa/foehn-demo) — every attribute the framework ships, in a working theme you can read.

> **Note**
> This package is part of the [Føhn Framework](https://github.com/studiometa/foehn-framework) monorepo.
> Please report issues and submit pull requests in the [main repository](https://github.com/studiometa/foehn-framework).

## Quick start with DDEV

```bash
composer create-project studiometa/foehn-starter my-project
cd my-project
ddev start
npm install && npm run build
```

`composer create-project` installs the dependencies, generates `web/` and `wp-config.php`, and writes `.env` from `.env.example` with new security keys. `ddev start` starts PHP 8.5, MariaDB and nginx. When WordPress is not installed, it installs it with `admin` / `admin` and activates the theme. When `vendor/` is missing (a fresh clone), it runs `composer install` first.

```bash
ddev launch              # the site
ddev launch /wp/wp-admin # the admin
ddev wp foehn seed       # the header, footer and legal menus
```

Before you change the ddev project name, read [Rename the theme](https://studiometa.github.io/foehn-framework/guide/starter-theme#rename-the-theme): the name is also in `WP_HOME`.

## Quick start without DDEV

```bash
composer create-project studiometa/foehn-starter my-project
cd my-project
# Edit .env: database credentials and WP_HOME
```

Then point the document root of your web server at `web/`, install WordPress (`wp core install`) and activate the theme (`wp theme activate starter-theme`).

## What is here

```
my-project/
├── theme/
│   ├── app/
│   │   ├── ContextProviders/       # GlobalContextProvider — data every template gets
│   │   ├── Controllers/            # single, page, archive, search, 404, and the password form
│   │   ├── Hooks/                  # AssetHooks (Vite build), ThemeHooks (theme supports, excerpt)
│   │   ├── Menus/                  # header, footer, legal
│   │   ├── Seeders/                # MenusSeeder — the three menus, for `wp foehn seed`
│   │   ├── foehn.config.php        # discovery cache, opt-in cleanup and security hooks
│   │   └── page-cache.config.php   # static page cache, production only
│   ├── assets/
│   │   ├── css/app.css             # Tailwind entry point
│   │   └── js/app.js               # js-toolkit entry point
│   ├── templates/
│   │   ├── layouts/base.twig
│   │   ├── pages/                  # single, page, archive, search, 404, password
│   │   ├── sections/               # posts — the archive list, which can render alone
│   │   └── components/             # header, footer, card, pagination
│   ├── functions.php               # one line: Kernel::boot()
│   └── style.css
│
├── tests/
│   ├── php/                        # unit tests: `composer test`
│   └── smoke/                      # tests against the site in ddev: `composer test:smoke`, run.sh
├── .ddev/                          # DDEV configuration
├── vite.config.js                  # Vite, with @studiometa/foehn-vite-plugin
├── public/                         # files copied into web/ (robots.txt, .well-known/)
├── web/                            # generated document root (gitignored)
├── .env                            # environment variables (DDEV supplies its own)
├── composer.json
└── package.json
```

Every class here is one a theme needs before it renders anything: the controllers that answer WordPress's template hierarchy, the menus the header and footer templates read, the context provider that puts `current_year` in the footer, the theme supports and the asset hooks. `MenusSeeder` fills the three menus, so an empty database gets them back with `wp foehn seed`.

`foehn.config.php` opts into the framework's own cleanup and security hooks — `CleanHeadTags`, `DisableEmoji`, `DisableFileEditor`, `DisableGlobalStyles`, `DisableOembed`, `DisableVersionDisclosure`, `DisableXmlRpc`, `GenericLoginErrors`, `StudiometaUi`, `YouTubeNoCookieHooks` — and turns the discovery cache on. `page-cache.config.php` turns the static page cache on in production.

Put the files that must be at the document root, such as `robots.txt` or `.well-known/security.txt`, in `public/`. See [Public files](https://studiometa.github.io/foehn-framework/guide/public-files).

## Adding to it

```bash
ddev wp foehn make:post-type product
ddev wp foehn make:block hero
ddev wp foehn make:controller Product --templates=single-product
ddev wp foehn discovery:list          # what the framework found, and where
```

Or read [the demo](https://github.com/studiometa/foehn-demo), which has one of everything already written.

## AI agents

`AGENTS.md` tells a coding agent how to work in this project, and `CLAUDE.md` imports it for Claude Code. In short: read the Føhn [Agent Skills](https://agentskills.io/) at the versions the project has installed, then read what the project declares, before writing code:

```bash
ddev wp foehn skill                       # the skills of the installed packages
ddev wp foehn skill foehn                 # print one
ddev wp foehn inspect --format=json       # post types, blocks, templates, context keys…
```

The skills are read from `vendor/` and `node_modules/`, so they follow `composer update` and `npm update` with nothing to pin. To install them in an agent's own skill directory instead, see [AI Agents](https://studiometa.github.io/foehn-framework/guide/ai-agents).

`AGENTS.md` holds a generated Føhn block between `<!-- foehn:begin -->` and `<!-- foehn:end -->`: the first commands an agent runs, and an index of the installed skills and their references. After `composer update` or `npm update`, refresh it and commit the change. The command changes nothing outside the markers:

```bash
ddev wp foehn agents-md
```

## Front-end

```bash
npm install
npm run dev             # Vite dev server, with HMR into WordPress
npm run build           # production build
npm run lint            # JS, CSS and Twig
npm run fix
```

There is no JavaScript test setup here, because there is no JavaScript to test yet. [The demo](https://github.com/studiometa/foehn-demo) has one configured with Vitest and Playwright, ready to copy.

## Tests

```bash
composer test            # unit tests, against WordPress function stubs
composer test:smoke      # the site in a started ddev: home page, active theme
./tests/smoke/run.sh     # the site in a started ddev: boot, discovery cache, assets, password form
```

The unit tests pass even when WordPress cannot boot, because they run against function stubs. The smoke tests ask the real site. Add a test to `tests/smoke/` for each page a release must not break.

## License

MIT
