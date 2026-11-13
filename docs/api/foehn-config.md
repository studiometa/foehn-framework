# FoehnConfig

Core configuration class for Føhn. Like all other config classes, it can be auto-discovered via a `foehn.config.php` file. A legacy array-based approach via `Kernel::boot()` is also supported.

## Signature

```php
<?php

namespace Studiometa\Foehn\Config;

use Tempest\Discovery\DiscoveryCacheStrategy;

final readonly class FoehnConfig
{
    public function __construct(
        public DiscoveryCacheStrategy $discoveryCacheStrategy = DiscoveryCacheStrategy::NONE,
        public ?string $discoveryCachePath = null,
        /** @var list<class-string> */
        public array $hooks = [],
        /** @var class-string|null */
        public ?string $imageTransformer = null,
        public bool $debug = false,
        public ?bool $recordErrors = null,
        public ?bool $traceRequests = null,
        public string $cliNamespace = 'app',
    );

    public static function fromArray(array $config): self;
    public function isDebugEnabled(): bool;
    public function recordsErrors(): bool;
    public function tracesRequests(): bool;
    public function isDiscoveryCacheEnabled(): bool;
    public function getDiscoveryCachePath(): string;
}
```

## Properties

| Property                 | Type                     | Default | Description                                                                                                                                              |
| ------------------------ | ------------------------ | ------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `discoveryCacheStrategy` | `DiscoveryCacheStrategy` | `NONE`  | Cache strategy for discoveries                                                                                                                           |
| `discoveryCachePath`     | `string\|null`           | `null`  | Custom path for cache files                                                                                                                              |
| `hooks`                  | `class-string[]`         | `[]`    | Opt-in hook classes to activate                                                                                                                          |
| `imageTransformer`       | `class-string\|null`     | `null`  | The `ImageTransformer` class `image_url()` uses, for example `GlideTransformer::class`. `null` gives back the URL unchanged. See [Images](/guide/images) |
| `debug`                  | `bool`                   | `false` | Raise PHP warnings: each class discovery skips, a discovery cache it cannot write, a missing block editor registrar                                      |
| `recordErrors`           | `bool\|null`             | `null`  | Write errors to the log `wp foehn errors` reads. `null` records them in the `local` and `development` environments                                       |
| `traceRequests`          | `bool\|null`             | `null`  | Store a trace of each front-end request for `wp foehn trace`. `null` traces them in `local` and `development`                                            |
| `cliNamespace`           | `string`                 | `'app'` | The WP-CLI command the project's `#[AsCliCommand]` classes go under: `wp app <name>`                                                                     |

## Usage

### Config file (recommended)

Create a config file in your app directory:

```php
<?php
// app/foehn.config.php

use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Hooks\Cleanup\CleanHeadTags;
use Studiometa\Foehn\Hooks\Security\SecurityHeaders;
use Tempest\Discovery\DiscoveryCacheStrategy;

return new FoehnConfig(
    discoveryCacheStrategy: DiscoveryCacheStrategy::FULL,
    discoveryCachePath: WP_CONTENT_DIR . '/cache/foehn',
    hooks: [
        CleanHeadTags::class,
        SecurityHeaders::class,
    ],
    debug: WP_DEBUG,
);
```

Then boot the kernel without any config array:

```php
Kernel::boot(__DIR__ . '/app');
```

### Via Kernel::boot() (legacy)

You can also pass configuration directly to `Kernel::boot()`:

```php
use Studiometa\Foehn\Kernel;

Kernel::boot(__DIR__ . '/app', [
    'discovery_cache' => 'full',
    'discovery_cache_path' => WP_CONTENT_DIR . '/cache/foehn',
    'hooks' => [
        CleanHeadTags::class,
        SecurityHeaders::class,
    ],
    'debug' => WP_DEBUG,
]);
```

> **Note**: If both a `foehn.config.php` file and boot array are provided, the config file takes precedence.

### Discovery Cache Strategies

| Strategy  | Value       | Description                           |
| --------- | ----------- | ------------------------------------- |
| `NONE`    | `'none'`    | No caching (default, for development) |
| `FULL`    | `'full'`    | Cache all discoveries (production)    |
| `PARTIAL` | `'partial'` | Cache only vendor discoveries         |

### Opt-in Hooks

Føhn includes built-in hook classes that you can opt into:

```php
'hooks' => [
    \Studiometa\Foehn\Hooks\Cleanup\CleanHeadTags::class,
    \Studiometa\Foehn\Hooks\Cleanup\DisableEmoji::class,
    \Studiometa\Foehn\Hooks\Security\SecurityHeaders::class,
],
```

These classes are not auto-discovered — they must be explicitly listed.

### Debug Mode

When enabled, discovery failures (reflection errors, missing classes) are logged via `trigger_error()`:

```php
'debug' => true,
```

The default is `false`. To follow `WP_DEBUG`, write `debug: WP_DEBUG` in `foehn.config.php`. Only the legacy `Kernel::boot()` array uses `WP_DEBUG` when it has no `'debug'` key.

### Image Transformer

`imageTransformer` is the class that `image_url()` uses to resize images. Give a class that implements `ImageTransformer`, for example `GlideTransformer::class`. `null`, the default, gives back the URL unchanged. The array config takes `'image_transformer' => GlideTransformer::class`. See [Images](/guide/images).

### Error Log

`recordErrors` decides when uncaught exceptions, fatal errors and the warnings of the theme are written to the log `wp foehn errors` reads. `null`, the default, records them when the environment is `local` or `development`; `recordsErrors()` gives the answer. The array config takes `'record_errors' => true`. See [The error log](/guide/troubleshooting#the-error-log).

### Request Trace

`traceRequests` decides when each front-end request sends its id in the `X-Foehn-Trace` header and stores what it ran for `wp foehn trace`. `null`, the default, traces them when the environment is `local` or `development`; `tracesRequests()` gives the answer. The array config takes `'trace_requests' => true`. See [Request Trace](/guide/request-trace).

### CLI Namespace

`cliNamespace` is the WP-CLI command the `#[AsCliCommand]` classes of the project go under: `wp app <name>` by default. The commands of Føhn stay under `wp foehn <name>`, and the commands of each other package go under the namespace of that package. It is one word of lowercase letters, digits, `-` and `_`, and not `foehn`: another value throws an `InvalidArgumentException`. The array config takes `'cli_namespace' => 'site'`. See [Command Namespace](/guide/cli-commands#command-namespace).

## All Config Classes

All Føhn config classes follow the same pattern — discoverable via `*.config.php` files:

| Config Class           | Config file                    | Purpose                                                         |
| ---------------------- | ------------------------------ | --------------------------------------------------------------- |
| `FoehnConfig`          | `app/foehn.config.php`         | Core bootstrap settings                                         |
| `TimberConfig`         | `app/timber.config.php`        | Template directories                                            |
| `AcfConfig`            | `app/acf.config.php`           | ACF field transformation. Needs `studiometa/foehn-acf`          |
| `RestConfig`           | `app/rest.config.php`          | REST API permissions                                            |
| `QueryFiltersConfig`   | `app/query-filters.config.php` | [Query filters](/guide/query-filters#queryfiltersconfig)        |
| `PageCacheConfig`      | `app/page-cache.config.php`    | [Page cache](./page-cache-config)                               |
| `DatabaseExportConfig` | `app/db-export.config.php`     | [Database export](/guide/database-export#declaring-your-tables) |
| `GlideConfig`          | `app/glide.config.php`         | [Image transforms](/guide/images)                               |

The file name is free: Føhn loads every `*.config.php` file of the app directory and binds each object it returns. The names above are the convention.

## Related

- [Kernel](./kernel)
- [Guide: Discovery Cache](/guide/discovery-cache)
- [Guide: Configuration](/guide/configuration)
