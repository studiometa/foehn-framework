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

## Changelog

- **Never edit `CHANGELOG.md` directly.** Each pull request adds a fragment `.changelog/<slug>.md` (slug = branch name without its prefix, e.g. `fix/acf-block-field-values` → `acf-block-field-values.md`)
- A fragment only contains `### Added|Changed|Deprecated|Removed|Fixed|Security|Dependencies` headings, each followed by its entries — no `#`/`##` heading, no summary line
- **Do not write the pull request number**: the `Release` workflow appends `[[#N]]` and the link definition from the merged pull request that added the fragment
- Entries say what changed for a project and what a project must change, in English
- The `Changelog` check fails when a pull request changes `packages/**` or `docker/**` without a fragment; set the `no-changelog` label for changes users do not see (tests, refactors, tooling)
- Format and tooling: [`weareikko/changelog`](https://github.com/weareikko/changelog)

## Release Guidelines

- Do not tag, bump versions or create GitHub releases by hand. Release with the `Release` workflow: `gh workflow run release.yml -f version=0.6.4` (a prerelease such as `0.7.0-rc.1` publishes the Vite plugin under the `next` dist-tag and does not deploy the demo)
- The workflow moves every version and inter-package pin, moves the demo's default image (`ARG FOEHN_IMAGE` in `packages/demo/Dockerfile`) to the previous release's `-db` variant, updates `package-lock.json`, folds `.changelog/` into `CHANGELOG.md` as `## <version> - <date>`, commits `Release <version>` on `main` and pushes the tag (semver, no `v` prefix). It fails when there is no fragment
- In the same run it then tests the tag, publishes the Vite plugin to npm, splits the packages to their mirrors (Packagist), builds and publishes the runtime image, deploys the demo, and creates the GitHub release. A tag pushed with `GITHUB_TOKEN` starts no workflow, so `split.yml`, `docker.yml` and `deploy-demo.yml` are called from `release.yml`
- Every package carries the same version, PHP and npm alike, and every inter-package dependency requires that **exact** version: no caret, no range. So `composer create-project` and `npm install` get a combination somebody has run. `0.6.0` shipped a starter requiring `^0.5` — a caret on a `0.x` pins the minor, so create-project kept building `0.5.10` projects — and `foehn-acf` required `^0.5` up to `0.6.2`, so it could not be installed with the current framework
- The release moves each of these to the new version:
  - `packages/vite-plugin/package.json` and the root `package.json`: `version`
  - `packages/acf/composer.json`: `studiometa/foehn`
  - `packages/starter/composer.json` and `packages/demo/composer.json`: `studiometa/foehn` and `studiometa/foehn-installer`
  - `packages/starter/package.json` and `packages/demo/package.json`: `@studiometa/foehn-vite-plugin`
  - the root `composer.json`: the `studiometa/foehn` version of the `packages/foehn` path repository, which the `foehn-acf` pin resolves against in the monorepo
- Before it commits, the release refuses a version when any `studiometa/foehn*` or `@studiometa/foehn-*` constraint in a package, the root path repository version, or the `version` of the Vite plugin or the root `package.json` is not that version. A new inter-package dependency must hold the current version so the bump moves it. The publish job checks the plugin version again before it publishes, rather than publish a version nobody tagged
- The Vite plugin publishes to npm through trusted publishing. There is no `NPM_TOKEN`: the job proves its identity with an OIDC token, against a trusted publisher configured on npmjs.com that names this repository and `release.yml`. A rename of the workflow file, or a move of `npm publish` to another file, breaks publishing until the trusted publisher is updated
