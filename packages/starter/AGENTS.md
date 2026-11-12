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

   `post-types` gives the models and meta keys, `templates` the controller of each WordPress template, `views` the Twig files and their context keys, `menus` the menu locations, and `settings` the options. `--all` prints every section; the output is long, so name sections instead. The summary starts with `problems`: what Føhn skipped or WordPress will ignore. `ddev wp foehn inspect problems --format=json` gives the fix of each one.

3. Create classes with the generators. Preview first, then write:

   ```bash
   ddev wp foehn make:post-type product --dry-run --format=json
   ddev wp foehn make:post-type product --format=json
   ```

4. After you add a class or change an attribute, run `ddev wp foehn discovery:clear`. The discovery cache is on in every environment. Then check that `problems` in `ddev wp foehn inspect --format=json` is empty: a class that breaks the contract of its attribute is skipped, not fatal.

5. When a page fails, read the last error before you open a browser or the PHP log. It gives the file and line, the Twig template and line, the WordPress template, the controller, the view and its context keys:

   ```bash
   ddev wp foehn errors
   ddev wp foehn errors --limit=5 --format=json
   ```

   The warnings of WordPress and plugins, and `error_log()` messages, are in the debug log. Read it as grouped entries, not with `tail`:

   ```bash
   ddev wp foehn log
   ddev wp foehn log --level=fatal,warning --since=-1hour --format=json
   ```

6. When a page renders the wrong template, view or data, trace it. It requests the page and prints the template files WordPress tried, the controller, each view with its context providers and keys, the blocks and the errors of that request:

   ```bash
   ddev wp foehn trace /about/
   ddev wp foehn trace last --format=json
   ```

## Project conventions

<!-- Fill in this section for the project and keep it current: an agent follows what it says. Remove a line that does not apply. -->

- Sample content and menus: in seeders (`theme/app/Seeders/`), never only in the database, so an empty or replaced database gets them back. Do not create them with one-off `wp post create` or `wp menu` commands. A seeder is idempotent: it finds each post by slug (`Upsert::post()`), each attachment by meta and each menu by name before it creates it. `wp menu item add-*` takes a menu, not a location: `ddev wp menu list --fields=term_id,slug,locations` gives the menu of each location.

  ```bash
  ddev wp foehn make:seeder events --dry-run --format=json
  ddev wp foehn make:seeder events --format=json
  ddev wp foehn discovery:clear
  ddev wp foehn seed                       # every seeder, by order; run it twice: the second run creates nothing
  ddev wp foehn seed --class=EventsSeeder  # one seeder
  ```

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
