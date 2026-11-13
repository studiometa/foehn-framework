# Agent instructions

This project is a WordPress site built with [Føhn](https://github.com/studiometa/foehn-framework) (`studiometa/foehn`). The theme is in `theme/`: PHP classes with attributes in `theme/app/` (namespace `Demo\`), Twig templates in `theme/templates/`, and the Vite build in `theme/assets/`. Composer generates `web/`: do not edit it.

The project runs in DDEV. Run WP-CLI as `ddev wp …`. Without DDEV, run `wp …` from the project root.

The Føhn block below is generated. After `composer update`, `npm update` or the install of another Føhn package, refresh it and commit the change:

```bash
ddev wp foehn agents-md
```

<!-- foehn:begin -->
<!-- Written by `wp foehn agents-md` from the installed packages. Run it again after `composer update` or `npm update`: it replaces everything between these markers. -->

## Føhn

The skills below describe the Føhn versions this project has installed. Trust them over what you remember of Føhn, and read the skill and the reference of an area before you write code in it. Run `wp` the way this project runs WP-CLI, for example `ddev wp` in DDEV.

- Before you add a class, read what the project declares: `wp foehn inspect --format=json`, then the sections you need in full, for example `wp foehn inspect post-types templates views --format=json`.
- Create classes with the generators: `wp foehn make:<type> <name> --dry-run --format=json` first, then the same command without `--dry-run`.
- After you add a class or change an attribute, run `wp foehn discovery:clear`, then check that `problems` in `wp foehn inspect --format=json` is empty.
- Put sample content and menus in seeders (`wp foehn make:seeder`, then `wp foehn seed`), never only in the database.
- When a page fails, read `wp foehn errors` (the last PHP error, with its template, controller and view), `wp foehn errors --browser` (the browser console, while `npm run dev` runs) and `wp foehn log` (the debug log, grouped).
- When a page renders the wrong template, view or data, run `wp foehn trace <path>`.

### Skills

Each line gives the command that prints the file. When WordPress cannot boot, read the files directly: a skill is the `SKILL.md` in parentheses, and its reference `<file>` is `references/<file>.md` next to it.

- `wp foehn skill foehn` (`vendor/studiometa/foehn/skills/foehn/SKILL.md`): Build WordPress themes with studiometa/foehn, the attribute-based framework on Tempest discovery and Timber/Twig.
  - `wp foehn skill foehn --reference=blocks`: Native blocks, sidebar controls, containers, the Interactivity API, block patterns and block bindings.
  - `wp foehn skill foehn --reference=content`: Post types, taxonomies, post meta, dates, model queries, sample content and menus with WP-CLI, settings pages and seeders.
  - `wp foehn skill foehn --reference=operations`: Config files, opt-in hooks, the project model, the discovery cache, the page cache, query filters, images, the object cache, verification, security keys and the database export.
  - `wp foehn skill foehn --reference=routes-and-commands`: REST routes, rewrite rules, shortcodes, WP-CLI commands, background jobs, cron, Twig extensions and custom discoveries.
  - `wp foehn skill foehn --reference=views`: Template controllers, `TemplateContext`, context providers, DTOs, sections and the Twig functions Føhn adds.
- `wp foehn skill foehn-acf` (`vendor/studiometa/foehn-acf/skills/foehn-acf/SKILL.md`): Build ACF blocks, ACF field groups and ACF options pages in a Føhn theme with studiometa/foehn-acf.
  - `wp foehn skill foehn-acf --reference=fields`: Detail for SKILL.md: field group locations, field fragments, custom fragments, and the core helpers that work on ACF values.
- `wp foehn skill foehn-vite-plugin` (`node_modules/@studiometa/foehn-vite-plugin/skills/foehn-vite-plugin/SKILL.md`): Configure the Vite build of a Føhn theme with @studiometa/foehn-vite-plugin (the `foehn()` plugin in vite.config.js and its `input`, `reload`, `outDir`, `themeDir` and `hotFile` options) and enqueue…
  - `wp foehn skill foehn-vite-plugin --reference=dev-server`: How `npm run dev` (`vite`) works with `@studiometa/foehn-vite-plugin`, and what to check when HMR does not reach WordPress.

<!-- foehn:end -->

## Project conventions

- The demo shows the features of Føhn on a site a visitor can use. A feature is only shown when a page uses it: add the content that uses it to a seeder, and a check to `tests/smoke/`.
- Sample content and menus: in the seeders of `theme/app/Seeders/`, never only in the database. A seeder is idempotent: it finds each post by slug (`Upsert::post()`), each attachment by meta and each menu by name. Run `ddev wp foehn seed` twice: the second run creates nothing.
- ACF: the free plugin only. ACF blocks and options pages need ACF Pro, which CI cannot install.

## Checks

```bash
composer test:demo        # PHP unit tests, from the monorepo root
npm run test:run          # browser tests of the JS components
./tests/smoke/run.sh      # against a started ddev
npm run build             # front-end build
```
