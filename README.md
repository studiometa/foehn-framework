# 🍃 Føhn

A modern WordPress framework powered by [Tempest](https://github.com/tempestphp/tempest-framework), featuring attribute-based auto-discovery for hooks, post types, blocks, and more.

[![Latest Version](https://img.shields.io/github/v/release/studiometa/foehn-framework)](https://github.com/studiometa/foehn-framework/releases)
[![PHP Version](https://img.shields.io/badge/php-%5E8.5-blue)](https://php.net)
[![Tests](https://github.com/studiometa/foehn-framework/actions/workflows/ci.yml/badge.svg)](https://github.com/studiometa/foehn-framework/actions/workflows/ci.yml)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

> [!WARNING]
> **AI-Generated Project** — This project was primarily built by AI coding agents (Claude). While functional and tested, it may contain bugs, security issues, or unexpected behavior. Use at your own risk, especially in production environments.

## Packages

This monorepo contains the following packages:

| Package                                            | Description                                                  | Packagist                                                                                                                             |
| -------------------------------------------------- | ------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------- |
| [`studiometa/foehn`](packages/foehn)               | Core framework — auto-discovery, DI, blocks, hooks           | [![Latest](https://img.shields.io/packagist/v/studiometa/foehn)](https://packagist.org/packages/studiometa/foehn)                     |
| [`studiometa/foehn-installer`](packages/installer) | Composer plugin — generates web root, symlinks, wp-config    | [![Latest](https://img.shields.io/packagist/v/studiometa/foehn-installer)](https://packagist.org/packages/studiometa/foehn-installer) |
| [`studiometa/foehn-starter`](packages/starter)     | Starter theme — complete example with create-project support | [![Latest](https://img.shields.io/packagist/v/studiometa/foehn-starter)](https://packagist.org/packages/studiometa/foehn-starter)     |

## Quick Start

### New project

```bash
composer create-project studiometa/foehn-starter my-project
```

### Add to existing theme

```bash
composer require studiometa/foehn
```

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

## Available Attributes

| Attribute                 | Description                       |
| ------------------------- | --------------------------------- |
| `#[AsAction]`             | Register a WordPress action hook  |
| `#[AsFilter]`             | Register a WordPress filter hook  |
| `#[AsPostType]`           | Register a custom post type       |
| `#[AsTaxonomy]`           | Register a custom taxonomy        |
| `#[AsBlock]`              | Register a native Gutenberg block |
| `#[AsAcfBlock]`           | Register an ACF block             |
| `#[AsBlockPattern]`       | Register a block pattern          |
| `#[AsContextProvider]`    | Add data to specific views        |
| `#[AsTemplateController]` | Handle template rendering         |
| `#[AsShortcode]`          | Register a shortcode              |
| `#[AsRestRoute]`          | Register a REST API endpoint      |
| `#[AsCliCommand]`         | Register a WP-CLI command         |
| `#[AsTimberModel]`        | Register a Timber class map       |
| `#[AsMenu]`               | Register a navigation menu        |
| `#[AsImageSize]`          | Register a custom image size      |

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
├── .env                        # Environment variables
└── composer.json               # Dependencies

web/                            # GENERATED (100% gitignored)
├── wp/                         # WordPress core
├── wp-content/
│   ├── themes/my-theme → symlink to /theme
│   ├── plugins/                # Composer-managed plugins
│   └── mu-plugins/             # Auto-loaded mu-plugins
├── index.php                   # Generated front controller
└── wp-config.php               # Generated from config/
```

## Documentation

📖 **[Full Documentation](https://studiometa.github.io/foehn/)**

- [Getting Started](https://studiometa.github.io/foehn/guide/getting-started)
- [Installation](https://studiometa.github.io/foehn/guide/installation)
- [Theme Conventions](https://studiometa.github.io/foehn/guide/theme-conventions)
- [Security Guide](https://studiometa.github.io/foehn/guide/security)
- [API Reference](https://studiometa.github.io/foehn/api/)

### For AI Agents

This package includes an [Agent Skill](https://agentskills.io/) at `packages/foehn/skills/foehn/SKILL.md` with comprehensive usage reference. Compatible agents will discover it automatically.

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

MIT License — see [LICENSE](LICENSE) for details.

## Credits

- [Tempest Framework](https://github.com/tempestphp/tempest-framework) by Brent Roose
- [Timber](https://github.com/timber/timber) by Upstatement
- Inspired by [Acorn](https://github.com/roots/acorn) by Roots
