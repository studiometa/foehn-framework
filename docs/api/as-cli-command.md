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
        public bool $writes = false,
    ) {}
}
```

## Parameters

| Parameter         | Type      | Default | Description                                                                                                                                                                                                                                                             |
| ----------------- | --------- | ------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `name`            | `string`  | —       | Command name (required)                                                                                                                                                                                                                                                 |
| `description`     | `string`  | —       | Short description (required)                                                                                                                                                                                                                                            |
| `longDescription` | `?string` | `null`  | Detailed help (docblock format)                                                                                                                                                                                                                                         |
| `writes`          | `bool`    | `false` | The command changes content or files. When `wp_get_environment_type()` is `production`, it runs only with `--force` or `--dry-run`. Declare `[--force]` in `longDescription`, so WP-CLI accepts it. See [Command that Writes](/guide/cli-commands#command-that-writes). |

## Requirements

The class must implement `Studiometa\Foehn\Console\CliCommandInterface`, which declares `__invoke(array $args, array $assocArgs): void`. Discovery skips a class without it and reports it in `wp foehn discovery:status`.

## Namespace

A command of your project is registered as `wp app <name>`. A command of Føhn is registered as `wp foehn <name>`. A command of another package is registered under the namespace of that package: `wp foehn-acf <name>` for `studiometa/foehn-acf`. `FoehnConfig::$cliNamespace` changes `app` to another name, and `extra.foehn.cli-namespace` in the `composer.json` of a package changes the name of that package. See [Command Namespace](/guide/cli-commands#command-namespace).

## Usage

### Basic Command

```php
<?php

namespace App\Console;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use WP_CLI;

#[AsCliCommand(
    name: 'hello',
    description: 'Say hello',
)]
final class HelloCommand implements CliCommandInterface
{
    public function __invoke(array $args, array $assocArgs): void
    {
        $name = $args[0] ?? 'World';
        WP_CLI::success("Hello, {$name}!");
    }
}
```

**Usage:** `wp app hello John`

### With Options

Declare the arguments and options in `longDescription`. WP-CLI reads them from there, not from the docblock of `__invoke()`.

```php
#[AsCliCommand(
    name: 'import:products',
    description: 'Import products from CSV',
    longDescription: <<<'DOC'
        ## OPTIONS

        <file>
        : Path to CSV file

        [--dry-run]
        : Preview without importing
        DOC,
)]
final class ImportCommand implements CliCommandInterface
{
    public function __invoke(array $args, array $assocArgs): void
    {
        $file = $args[0];
        $dryRun = isset($assocArgs['dry-run']);

        // Import logic
    }
}
```

**Usage:** `wp app import:products data.csv --dry-run`

### With More Than One Action

Føhn calls only `__invoke()`. Other public methods are not subcommands. Read the action from a positional argument:

```php
#[AsCliCommand(
    name: 'object-cache',
    description: 'Manage the object cache',
    longDescription: <<<'DOC'
        ## OPTIONS

        <action>
        : What to do.
        ---
        options:
          - clear
          - warm
        ---
        DOC,
)]
final class ObjectCacheCommand implements CliCommandInterface
{
    public function __invoke(array $args, array $assocArgs): void
    {
        match ($args[0]) {
            'clear' => wp_cache_flush(),
            'warm' => $this->warm(),
        };

        WP_CLI::success('Done');
    }

    private function warm(): void
    {
        // Warm cache
    }
}
```

**Usage:**

- `wp app object-cache clear`
- `wp app object-cache warm`

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

    wp app sync
    wp app sync --force
DOC,
)]
final class SyncCommand implements CliCommandInterface
{
    public function __invoke(array $args, array $assocArgs): void
    {
        // ...
    }
}
```

## Related

- [Guide: CLI Commands](/guide/cli-commands)
