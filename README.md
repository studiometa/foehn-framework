# 🍃 Føhn

A modern WordPress framework powered by [Tempest](https://github.com/tempestphp/tempest-framework), featuring attribute-based auto-discovery for hooks, post types, blocks, and more.

[![Latest Version](https://img.shields.io/github/v/release/studiometa/foehn-framework)](https://github.com/studiometa/foehn-framework/releases)
[![PHP Version](https://img.shields.io/badge/php-%5E8.5-blue)](https://php.net)
[![Tests](https://github.com/studiometa/foehn-framework/actions/workflows/ci.yml/badge.svg)](https://github.com/studiometa/foehn-framework/actions/workflows/ci.yml)
[![License](https://img.shields.io/badge/license-GPL--3.0--or--later-blue)](LICENSE)

> [!WARNING]
> **AI-Generated Project** — This project was primarily built by AI coding agents (Claude). While functional and tested, it may contain bugs, security issues, or unexpected behavior. Use at your own risk, especially in production environments.

## Packages

This monorepo contains the following packages:

| Package                                            | Description                                               | Packagist                                                                                                                             |
| -------------------------------------------------- | --------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------- |
| [`studiometa/foehn`](packages/foehn)               | Core framework — auto-discovery, DI, blocks, hooks        | [![Latest](https://img.shields.io/packagist/v/studiometa/foehn)](https://packagist.org/packages/studiometa/foehn)                     |
| [`studiometa/foehn-acf`](packages/acf)             | ACF integration — blocks, field groups, options pages     | [![Latest](https://img.shields.io/packagist/v/studiometa/foehn-acf)](https://packagist.org/packages/studiometa/foehn-acf)             |
| [`studiometa/foehn-installer`](packages/installer) | Composer plugin — generates web root, symlinks, wp-config | [![Latest](https://img.shields.io/packagist/v/studiometa/foehn-installer)](https://packagist.org/packages/studiometa/foehn-installer) |
| [`studiometa/foehn-starter`](packages/starter)     | Starter theme — the minimum a new project needs           | [![Latest](https://img.shields.io/packagist/v/studiometa/foehn-starter)](https://packagist.org/packages/studiometa/foehn-starter)     |
| [`studiometa/foehn-demo`](packages/demo)           | Demo theme — every feature, and the end-to-end tests      | [![Latest](https://img.shields.io/packagist/v/studiometa/foehn-demo)](https://packagist.org/packages/studiometa/foehn-demo)           |

## Quick Start

### New project

```bash
composer create-project studiometa/foehn-starter my-project
```

### Add to existing theme

```bash
composer require "nette/php-generator:4.2.3 as 4.2.1" studiometa/foehn
```

The `nette/php-generator` alias is necessary until Tempest fixes its requirement. See [Installation](https://studiometa.github.io/foehn-framework/guide/installation).

```php
<?php
// functions.php
use Studiometa\Foehn\Kernel;

Kernel::boot(__DIR__ . '/app');
```

## Features

- 🚀 **Zero configuration** — Auto-discovery of components via PHP 8 attributes
- 🎯 **Modern DX** — Type-safe, IDE-friendly, testable
- 🔌 **WordPress native** — Works with Timber, ACF, and Gutenberg blocks
- ⚡ **Minimal boilerplate** — One line to boot your theme
- 📦 **Project generator** — Full web root generation via Composer plugin
- 🏗️ **Starter theme** — Complete example with models, hooks, templates
- 🐳 **Runtime image** — [`ghcr.io/studiometa/foehn-wordpress`](https://studiometa.github.io/foehn-framework/guide/docker-image), NGINX and PHP with Føhn's serving rules already installed

## Available Attributes

| Attribute                 | Description                                           |
| ------------------------- | ----------------------------------------------------- |
| `#[AsAction]`             | Register a WordPress action hook                      |
| `#[AsFilter]`             | Register a WordPress filter hook                      |
| `#[AsPostType]`           | Register a custom post type                           |
| `#[AsTaxonomy]`           | Register a custom taxonomy                            |
| `#[AsPostMeta]`           | Register a meta key, with its REST schema             |
| `#[AsTimberModel]`        | Register a Timber class map                           |
| `#[AsBlock]`              | Register a native Gutenberg block                     |
| `#[AsBlockCategory]`      | Register a block category                             |
| `#[AsBlockBinding]`       | Register a block bindings source                      |
| `#[AsBlockPattern]`       | Register a block pattern                              |
| `#[AsContextProvider]`    | Add data to specific views                            |
| `#[AsTemplateController]` | Handle template rendering                             |
| `#[AsTwigExtension]`      | Register a Twig extension                             |
| `#[AsShortcode]`          | Register a shortcode                                  |
| `#[AsRestRoute]`          | Register a REST API endpoint                          |
| `#[AsRewriteRule]`        | Register a rewrite rule and the class that answers    |
| `#[AsSettingsPage]`       | Register an admin page with the Settings API          |
| `#[AsMenu]`               | Register a navigation menu                            |
| `#[AsImageSize]`          | Register a custom image size                          |
| `#[AsCliCommand]`         | Register a WP-CLI command                             |
| `#[AsCron]`               | Run a class on a schedule                             |
| `#[AsJob]`                | Handle a queued job                                   |
| `#[AsSeeder]`             | Write the sample content of a site                    |
| `#[AsDiscovery]`          | Declare when a custom discovery applies               |
| `#[AsAcfBlock]`           | Register an ACF block (`studiometa/foehn-acf`)        |
| `#[AsAcfFieldGroup]`      | Register an ACF field group (`studiometa/foehn-acf`)  |
| `#[AsAcfOptionsPage]`     | Register an ACF options page (`studiometa/foehn-acf`) |

See the [API Reference](https://studiometa.github.io/foehn-framework/api/) for each attribute.

## Architecture

```
my-project/                     # What is VERSIONED
├── theme/                      # The WordPress theme
│   ├── app/                    # PHP classes (auto-discovered)
│   ├── templates/              # Twig templates
│   ├── functions.php           # Single Kernel::boot() call
│   └── style.css               # Theme header
├── config/                     # Configuration files
├── mu-plugins/                 # Custom mu-plugins (if needed)
├── public/                     # Copied into web/ (robots.txt, .well-known/)
├── .env                        # Environment variables
└── composer.json               # Dependencies

web/                            # GENERATED (100% gitignored)
├── wp/                         # WordPress core
├── wp-content/
│   ├── themes/my-theme → symlink to /theme
│   ├── plugins/                # Composer-managed plugins
│   └── mu-plugins/             # Auto-loaded mu-plugins
├── index.php                   # Generated front controller
├── robots.txt                  # Copied from public/
└── wp-config.php               # Generated from config/
```

## Documentation

📖 **[Full Documentation](https://studiometa.github.io/foehn-framework/)**

- [Getting Started](https://studiometa.github.io/foehn-framework/guide/getting-started)
- [Installation](https://studiometa.github.io/foehn-framework/guide/installation)
- [Theme Conventions](https://studiometa.github.io/foehn-framework/guide/theme-conventions)
- [Security Guide](https://studiometa.github.io/foehn-framework/guide/security)
- [Docker Image](https://studiometa.github.io/foehn-framework/guide/docker-image)
- [AI Agents](https://studiometa.github.io/foehn-framework/guide/ai-agents)
- [API Reference](https://studiometa.github.io/foehn-framework/api/)

### For AI Agents

Each package ships an [Agent Skill](https://agentskills.io/): `foehn`, `foehn-acf` and `foehn-vite-plugin`. Install them pinned to the `studiometa/foehn` version in your `composer.lock` with `npx skills add studiometa/foehn-framework#<version>`. See [AI Agents](https://studiometa.github.io/foehn-framework/guide/ai-agents).

## Development

```bash
# Install dependencies
composer install
npm install

# Run tests
composer test

# Lint & fix
composer lint
composer fix

# Format markdown
npm run fmt
```

## Contributing

Contributions are welcome! Please read our contributing guidelines before submitting a PR.

## License

`studiometa/foehn` and `studiometa/foehn-acf` are released under the GNU General Public License v3.0 or later. See [LICENSE](LICENSE).

`studiometa/foehn-starter`, `studiometa/foehn-installer`, `@studiometa/foehn-vite-plugin` and the demo are released under the MIT License, so the code that `composer create-project` copies into a project stays MIT.

## Credits

- [Tempest Framework](https://github.com/tempestphp/tempest-framework) by Brent Roose
- [Timber](https://github.com/timber/timber) by Upstatement
- Inspired by [Acorn](https://github.com/roots/acorn) by Roots
