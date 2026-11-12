# Agent instructions

This project is a WordPress site built with [Føhn](https://github.com/studiometa/foehn-framework) (`studiometa/foehn`). The theme is in `theme/`: PHP classes with attributes in `theme/app/` (namespace `App\`), Twig templates in `theme/templates/`, and the Vite build in `theme/assets/`. Composer generates `web/`: do not edit it.

The project runs in DDEV. Run WP-CLI as `ddev wp …`. Without DDEV, run `wp …` from the project root.

## Before you write code

1. Read the Føhn skills at the versions this project has installed:

   ```bash
   ddev wp foehn skill                  # list the skills
   ddev wp foehn skill foehn            # the framework: attributes, conventions, commands
   ddev wp foehn skill foehn --list-references
   ddev wp foehn skill foehn --reference=blocks
   ```

   Read `foehn-vite-plugin` before you change `vite.config.js` or the assets, and `foehn-acf` when the project uses `studiometa/foehn-acf`.

2. Read what the project already declares, before you add a class. Start with the summary, which gives the names in each section and is short enough to read whole. Then read in full the sections your task needs, in one call:

   ```bash
   ddev wp foehn inspect --format=json
   ddev wp foehn inspect post-types templates views menus settings --format=json
   ```

   `post-types` gives the models and meta keys, `templates` the controller of each WordPress template, `views` the Twig files and their context keys, `menus` the menu locations, and `settings` the options. `--all` prints every section; the output is long, so name sections instead.

3. Create classes with the generators. Preview first, then write:

   ```bash
   ddev wp foehn make:post-type product --dry-run --format=json
   ddev wp foehn make:post-type product --format=json
   ```

4. After you add a class or change an attribute, run `ddev wp foehn discovery:clear`. The discovery cache is on in every environment.

## Project conventions

<!-- Fill in this section for the project and keep it current: an agent follows what it says. Remove a line that does not apply. -->

- Sample content: _where it is created, for example `database/seed.php`, run with `ddev wp eval-file database/seed.php`. Add new sample content there, not by hand._
- Menus: _which menu is assigned to each `#[AsMenu]` location, and where the items are created._ `wp menu item add-*` takes a menu, not a location: `ddev wp menu list --fields=term_id,slug,locations` gives the menu of each location.
- Editors: _how editors edit post meta: `custom-fields` support, ACF field groups or block bindings._
- Checks: _the commands to run before a commit, in addition to the ones below._

## When WordPress cannot boot

`wp foehn skill` needs a working WordPress. When it fails, read the same files directly:

- `vendor/studiometa/foehn/skills/foehn/SKILL.md` and its `references/` directory;
- `vendor/studiometa/*/skills/*/SKILL.md` for the other Føhn packages, such as `foehn-acf`;
- `node_modules/@studiometa/foehn-vite-plugin/skills/foehn-vite-plugin/SKILL.md`, after `npm install`.

## Checks

```bash
composer test       # PHP unit tests
npm run lint        # JS, CSS and Twig
npm run build       # front-end build
```
