# Claude Instructions for Føhn

## Project Overview

`studiometa/foehn` is a modern WordPress framework powered by Tempest Framework.
It provides attribute-based auto-discovery for hooks, post types, blocks, and more.

## Tech Stack

- **PHP 8.4+** (required for modern features)
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

See `.planning/` directory:

- `task_plan.md` - Project phases and progress
- `architecture.md` - Technical architecture
- `research_notes.md` - Design decisions
- `theme_example.md` - Usage examples

## Current Phase

Check `.planning/task_plan.md` for current implementation status.

## Commit Guidelines

- Commit each logical step separately
- Use English commit messages
- Include `Co-authored-by: Claude <claude@anthropic.com>` trailer
- Pre-commit hook runs mago (PHP) and oxfmt (Markdown)

## Release Guidelines

- Tags use semver without `v` prefix (e.g., `0.1.0`, not `v0.1.0`)
- Update CHANGELOG.md before tagging
- GitHub Actions workflow handles release creation automatically
