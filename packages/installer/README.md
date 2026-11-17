# Foehn Installer

Composer plugin that generates the WordPress web root for [Føhn](https://github.com/studiometa/foehn-framework) projects.

> **Note**
> This package is part of the [Føhn Framework](https://github.com/studiometa/foehn-framework) monorepo.
> Please report issues and submit pull requests in the [main repository](https://github.com/studiometa/foehn-framework).

## What it does

On `composer install` and `composer update`, this plugin automatically:

1. Creates the `web/` directory structure (document root)
2. Generates `web/wp-config.php` (loads config from `config/` directory)
3. Generates `web/index.php` (WordPress front controller)
4. Symlinks `theme/` → `web/wp-content/themes/{name}`
5. Symlinks `mu-plugins/` → `web/wp-content/mu-plugins/_custom`
6. Generates the mu-plugin loader
7. Generates the page cache drop-in and the maintenance page drop-in
8. Copies the content of `public/` into `web/`

## Installation

```bash
composer require studiometa/foehn-installer
```

## Configuration

Configure via `extra.foehn` in your project's `composer.json`:

```json
{
  "extra": {
    "foehn": {
      "web-dir": "web",
      "wp-dir": "wp",
      "theme-dir": "theme",
      "theme-name": "my-theme",
      "mu-plugins-dir": "mu-plugins",
      "config-dir": "config",
      "public-dir": "public"
    }
  }
}
```

All options are optional and have sensible defaults.

| Option           | Default      | Description                                  |
| ---------------- | ------------ | -------------------------------------------- |
| `web-dir`        | `web`        | Web root directory (document root)           |
| `wp-dir`         | `wp`         | WordPress core directory within web root     |
| `theme-dir`      | `theme`      | Theme source directory to symlink            |
| `theme-name`     | `theme`      | Theme directory name in `wp-content/themes/` |
| `mu-plugins-dir` | `mu-plugins` | Custom mu-plugins directory to symlink       |
| `config-dir`     | `config`     | Configuration files directory                |
| `public-dir`     | `public`     | Directory copied into the web root           |

## Generated structure

```
web/                            ← Document root (nginx/apache)
├── index.php                   ← Generated front controller
├── robots.txt                  ← Copied from public/
├── wp-config.php               ← Generated configuration
├── wp/                         ← WordPress core (via composer)
└── wp-content/
    ├── themes/
    │   └── my-theme → ../../theme/
    ├── plugins/                ← Composer-managed plugins
    ├── mu-plugins/
    │   ├── 00-loader.php       ← Generated loader
    │   └── _custom → ../../mu-plugins/
    ├── advanced-cache.php      ← Generated page cache drop-in
    ├── maintenance.php         ← Generated maintenance page drop-in
    └── uploads/
```

## Maintenance page

While `.maintenance` exists (`wp maintenance-mode activate`, or a core, plugin or theme update), WordPress loads `wp-content/maintenance.php` and stops, before the theme, the plugins and the database. The generated drop-in sends a `503` with `Retry-After: 600`, then prints `theme/maintenance.php` when the theme has one, and a plain page when it does not.

`theme/maintenance.php` is a plain PHP or HTML file. WordPress functions, Twig and the database are not available. Link the styles with a path, or put them inline:

```php
<!doctype html>
<html lang="fr">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="robots" content="noindex" />
    <title>Maintenance</title>
  </head>
  <body>
    <h1>Site en maintenance</h1>
    <p>Revenez dans quelques minutes.</p>
  </body>
</html>
```

## Public files

The installer copies the content of `public-dir` into the web root, dotfiles included. It copies the files and does not create symlinks. `index.php`, `wp-config.php`, the WordPress directory and `wp-content/` belong to the installer: it does not copy a public file to these paths, and shows a warning. A file removed from `public-dir` is removed from the web root on the next install. See [Public Files](https://studiometa.github.io/foehn-framework/guide/public-files).

## License

MIT
