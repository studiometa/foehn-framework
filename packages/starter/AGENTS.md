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

2. Read what the project already declares, before you add a class:

   ```bash
   ddev wp foehn inspect --format=json
   ddev wp foehn inspect templates --format=json
   ```

3. Create classes with the generators. Preview first, then write:

   ```bash
   ddev wp foehn make:post-type product --dry-run --format=json
   ddev wp foehn make:post-type product --format=json
   ```

4. After you add a class or change an attribute, run `ddev wp foehn discovery:clear`. The discovery cache is on in every environment.

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
