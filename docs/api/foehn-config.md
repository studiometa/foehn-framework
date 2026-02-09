# FoehnConfig

Core configuration class for Føhn. Like all other config classes, it can be auto-discovered via a `foehn.config.php` file. A legacy array-based approach via `Kernel::boot()` is also supported.

## Signature

```php
<?php

namespace Studiometa\Foehn\Config;

use Tempest\Core\DiscoveryCacheStrategy;

final readonly class FoehnConfig
{
    public function __construct(
        public DiscoveryCacheStrategy $discoveryCacheStrategy = DiscoveryCacheStrategy::NONE,
        public ?string $discoveryCachePath = null,
        /** @var list<class-string> */
        public array $hooks = [],
        public bool $debug = false,
    );

    public static function fromArray(array $config): self;
    public function isDebugEnabled(): bool;
    public function isDiscoveryCacheEnabled(): bool;
    public function getDiscoveryCachePath(): string;
}
```

## Properties

| Property                  | Type                      | Default                | Description                            |
| ------------------------- | ------------------------- | ---------------------- | -------------------------------------- |
| `discoveryCacheStrategy`  | `DiscoveryCacheStrategy`  | `NONE`                 | Cache strategy for discoveries         |
| `discoveryCachePath`      | `string\|null`            | `null`                 | Custom path for cache files            |
| `hooks`                   | `class-string[]`          | `[]`                   | Opt-in hook classes to activate        |
| `debug`                   | `bool`                    | `WP_DEBUG` value       | Enable debug logging for discovery     |

## Usage

### Config file (recommended)

Create a config file in your app directory:

```php
<?php
// app/foehn.config.php

use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Hooks\Cleanup\CleanHeadTags;
use Studiometa\Foehn\Hooks\Security\SecurityHeaders;
use Tempest\Core\DiscoveryCacheStrategy;

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

| Strategy   | Value       | Description                              |
| ---------- | ----------- | ---------------------------------------- |
| `NONE`     | `'none'`    | No caching (default, for development)    |
| `FULL`     | `'full'`    | Cache all discoveries (production)       |
| `PARTIAL`  | `'partial'` | Cache only vendor discoveries            |

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

Defaults to the value of `WP_DEBUG` when not explicitly set.

## All Config Classes

All Føhn config classes follow the same pattern — discoverable via `*.config.php` files:

| Config Class      | Config file                   | Purpose                          |
| ----------------- | ----------------------------- | -------------------------------- |
| `FoehnConfig`     | `app/foehn.config.php`        | Core bootstrap settings          |
| `TimberConfig`    | `app/timber.config.php`       | Template directories             |
| `AcfConfig`       | `app/acf.config.php`          | ACF field transformation         |
| `RestConfig`      | `app/rest.config.php`         | REST API permissions             |
| `RenderApiConfig` | `app/render-api.config.php`   | Render API allowlisting          |

## Related

- [Kernel](./kernel)
- [Guide: Discovery Cache](/guide/discovery-cache)
- [Guide: Configuration](/guide/configuration)
