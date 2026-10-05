# Claude Instructions for Føhn

## Project Overview

`studiometa/foehn` is a modern WordPress framework powered by Tempest Framework.
It provides attribute-based auto-discovery for hooks, post types, blocks, and more.

## Tech Stack

- **PHP 8.5+** (required for modern features)
- **Tempest Framework** - Discovery, DI container, reflection
- **Timber/Twig** - Template engine for WordPress
- **Pest** - Testing framework

## Project Structure

This is a monorepo containing multiple packages:

```
packages/
├── foehn/              # Core framework (studiometa/foehn)
│   ├── src/
│   │   ├── Attributes/ # PHP 8 attributes (#[AsAction], #[AsPostType], etc.)
│   │   ├── Blocks/     # Block rendering and management
│   │   ├── Console/    # CLI commands
│   │   ├── Contracts/  # Interfaces
│   │   ├── Discovery/  # Tempest discovery classes
│   │   ├── FSE/        # Full Site Editing support
│   │   ├── PostTypes/  # Post type and taxonomy builders
│   │   ├── Views/      # View engine abstraction
│   │   ├── Kernel.php  # Main bootstrap class
│   │   └── helpers.php # Global helper functions
│   ├── tests/
│   └── composer.json
│
├── installer/          # Composer installer plugin (studiometa/foehn-installer)
│   ├── src/
│   ├── tests/
│   └── composer.json
│
└── starter/            # Starter theme (studiometa/foehn-starter)
    ├── app/
    ├── templates/
    └── composer.json
```

## Commands

```bash
# Testing (from monorepo root)
composer test              # Run Pest tests for all packages
composer test:coverage     # Run with coverage

# Code Quality
composer lint              # Check code (mago lint + fmt --check)
composer fix               # Fix code (mago lint --fix + fmt)
composer analyse           # Static analysis (mago analyse)

# Formatting
npm run fmt                # Format markdown files
npm run fmt:check          # Check markdown formatting
```

## Development Guidelines

### Adding New Attributes

1. Create attribute in `src/Attributes/` with `#[Attribute]` annotation
2. Make it `final readonly class`
3. Use constructor promotion for all properties
4. Add corresponding Discovery class in `src/Discovery/`
5. Register discovery in `src/Discovery/DiscoveryRunner.php`
6. Add tests in `tests/Unit/Attributes/`

### Adding New Discoveries

1. Implement `Tempest\Discovery\Discovery` interface
2. Use `IsDiscovery` trait
3. Implement `discover()` to collect items
4. Implement `apply()` to register with WordPress
5. Add to appropriate phase in `DiscoveryRunner`

### Code Style

- Use `declare(strict_types=1)`
- Classes should be `final` unless designed for extension
- Attributes should be `readonly`
- Use constructor property promotion
- Run `composer fix` before committing

## Planning Documents

See `.planning/` for current and proposed work:

- `roadmap.md` - Current status, delivery order, and recorded decisions
- `operations_spec.md` - Approved operational safeguards and cache controls
- `diagnostics-command-spec.md` - Approved update and production verification
- `abilities_spec.md` - Undecided WordPress Abilities API proposal

Implemented behavior is documented in `docs/`. Maintained examples live in `packages/starter/` and `packages/demo/`.

## Current Phase

Check `.planning/roadmap.md` for current implementation status.

## Commit Guidelines

- Commit each logical step separately
- Use English commit messages
- Include `Co-authored-by: Claude <claude@anthropic.com>` trailer
- Pre-commit hook runs mago (PHP) and oxfmt (Markdown)

## Release Guidelines

- Tags use semver without `v` prefix (e.g., `0.1.0`, not `v0.1.0`)
- Update CHANGELOG.md before tagging
- GitHub Actions workflow handles release creation automatically
- Every package carries the same version, PHP and npm alike, and every inter-package dependency requires that **exact** version: no caret, no range. So `composer create-project` and `npm install` get a combination somebody has run. `0.6.0` shipped a starter requiring `^0.5` — a caret on a `0.x` pins the minor, so create-project kept building `0.5.10` projects — and `foehn-acf` required `^0.5` up to `0.6.2`, so it could not be installed with the current framework
- **Every** release commit moves each of these to the version being tagged:
  - `packages/vite-plugin/package.json`: `version`
  - `packages/acf/composer.json`: `studiometa/foehn`
  - `packages/starter/composer.json` and `packages/demo/composer.json`: `studiometa/foehn` and `studiometa/foehn-installer`
  - `packages/starter/package.json` and `packages/demo/package.json`: `@studiometa/foehn-vite-plugin`, then `npm install --package-lock-only` to update `package-lock.json`
  - the root `composer.json`: the `studiometa/foehn` version of the `packages/foehn` path repository, which the `foehn-acf` pin resolves against in the monorepo
- The release workflow refuses a tag when any `studiometa/foehn*` or `@studiometa/foehn-*` constraint in a package, or the root path repository version, is not the tag itself. The publish job also refuses a tag that is not the `packages/vite-plugin/package.json` version, rather than publish a version nobody tagged
- The Vite plugin publishes to npm through trusted publishing. There is no `NPM_TOKEN`: the job proves its identity with an OIDC token, against a trusted publisher configured on npmjs.com that names this repository and `release.yml`. A rename of the workflow file breaks publishing until the trusted publisher is updated
