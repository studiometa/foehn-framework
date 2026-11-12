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
7. Copies the content of `public/` into `web/`

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
    └── uploads/
```

## Public files

The installer copies the content of `public-dir` into the web root, dotfiles included. It copies the files and does not create symlinks. `index.php`, `wp-config.php`, the WordPress directory and `wp-content/` belong to the installer: it does not copy a public file to these paths, and shows a warning. A file removed from `public-dir` is removed from the web root on the next install. See [Public Files](https://studiometa.github.io/foehn-framework/guide/public-files).

## License

MIT
