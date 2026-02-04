# #[AsCliCommand]

Register a class as a WP-CLI command.

## Signature

```php
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsCliCommand
{
    public function __construct(
        public string $name,
        public string $description,
        public ?string $longDescription = null,
    ) {}
}
```

## Parameters

| Parameter         | Type      | Default | Description                     |
| ----------------- | --------- | ------- | ------------------------------- |
| `name`            | `string`  | —       | Command name (required)         |
| `description`     | `string`  | —       | Short description (required)    |
| `longDescription` | `?string` | `null`  | Detailed help (docblock format) |

## Namespace

Commands are registered under `wp tempest <name>`.

## Usage

### Basic Command

```php
<?php

namespace App\Console;

use Studiometa\WPTempest\Attributes\AsCliCommand;
use WP_CLI;

#[AsCliCommand(
    name: 'hello',
    description: 'Say hello',
)]
final class HelloCommand
{
    public function __invoke(array $args, array $assocArgs): void
    {
        $name = $args[0] ?? 'World';
        WP_CLI::success("Hello, {$name}!");
    }
}
```

**Usage:** `wp tempest hello John`

### With Options

```php
#[AsCliCommand(
    name: 'import:products',
    description: 'Import products from CSV',
)]
final class ImportCommand
{
    /**
     * ## OPTIONS
     *
     * <file>
     * : Path to CSV file
     *
     * [--dry-run]
     * : Preview without importing
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $file = $args[0];
        $dryRun = isset($assocArgs['dry-run']);

        // Import logic
    }
}
```

**Usage:** `wp tempest import:products data.csv --dry-run`

### With Subcommands

```php
#[AsCliCommand(
    name: 'cache',
    description: 'Manage cache',
)]
final class CacheCommand
{
    public function clear(): void
    {
        wp_cache_flush();
        WP_CLI::success('Cache cleared');
    }

    public function warm(): void
    {
        // Warm cache
        WP_CLI::success('Cache warmed');
    }
}
```

**Usage:**

- `wp tempest cache clear`
- `wp tempest cache warm`

### With Long Description

```php
#[AsCliCommand(
    name: 'sync',
    description: 'Sync data from API',
    longDescription: <<<'DOC'
## DESCRIPTION

Synchronizes data from the external API.

## OPTIONS

[--force]
: Force full sync

## EXAMPLES

    wp tempest sync
    wp tempest sync --force
DOC,
)]
```

## Related

- [Guide: CLI Commands](/guide/cli-commands)
