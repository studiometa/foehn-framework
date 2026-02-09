# FoehnConfig

Core configuration class for Føhn. Unlike other config classes, this is passed directly to `Kernel::boot()` and is available during the bootstrap phase.

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

`FoehnConfig` is created internally by `Kernel::boot()` from the config array:

```php
use Studiometa\Foehn\Kernel;
use Studiometa\Foehn\Hooks\Cleanup\CleanHeadTags;
use Studiometa\Foehn\Hooks\Security\SecurityHeaders;

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

## Difference from Other Configs

| Config Class      | Loaded by        | When available           |
| ----------------- | ---------------- | ------------------------ |
| `FoehnConfig`     | `Kernel::boot()` | Immediately at bootstrap |
| `TimberConfig`    | Tempest discovery | After Tempest boots      |
| `AcfConfig`       | Tempest discovery | After Tempest boots      |
| `RestConfig`      | Tempest discovery | After Tempest boots      |
| `RenderApiConfig` | Tempest discovery | After Tempest boots      |

`FoehnConfig` is the only config passed directly to the kernel because it controls the bootstrap process itself (cache strategy, debug mode).

## Related

- [Kernel](./kernel)
- [Guide: Discovery Cache](/guide/discovery-cache)
- [Guide: Configuration](/guide/configuration)
