# DiscoveryRunner

Orchestrates the discovery process across WordPress lifecycle phases. The runner fully owns the discovery lifecycle: scanning classes, calling `discover()` on each discovery, and calling `apply()` at the correct WordPress hook timing.

## Signature

```php
<?php

namespace Studiometa\Foehn\Discovery;

use Tempest\Container\Container;

final class DiscoveryRunner
{
    public function __construct(
        Container $container,
        ?DiscoveryCache $cache = null,
        ?string $appPath = null,
        ?FoehnConfig $config = null,
    );

    public function runEarlyDiscoveries(): void;
    public function runMainDiscoveries(): void;
    public function runLateDiscoveries(): void;
    public function hasRun(string $phase): bool;

    /** @return array<class-string<WpDiscovery>, WpDiscovery> */
    public function getDiscoveries(): array;

    /** @return array<string, array<class-string<WpDiscovery>>> */
    public static function getDiscoveryPhases(): array;

    /** @return array<class-string<WpDiscovery>> */
    public static function getAllDiscoveryClasses(): array;
}
```

## Discovery Phases

The runner splits discoveries into three phases, matching WordPress lifecycle hooks:

### Early Phase (`after_setup_theme`)

Runs before most WordPress initialization. Registers:

| Discovery               | Purpose                                     |
| ----------------------- | ------------------------------------------- |
| `HookDiscovery`         | Actions and filters                         |
| `ImageSizeDiscovery`    | Custom image sizes + `post-thumbnails`      |
| `ShortcodeDiscovery`    | Shortcode handlers                          |
| `CliCommandDiscovery`   | WP-CLI commands                             |
| `TimberModelDiscovery`  | Timber class maps                           |
| `TwigExtensionDiscovery`| Twig extensions                             |

### Main Phase (`init`)

Registers content types and blocks:

| Discovery                   | Purpose                                 |
| --------------------------- | --------------------------------------- |
| `PostTypeDiscovery`         | Custom post types                       |
| `TaxonomyDiscovery`        | Custom taxonomies                       |
| `MenuDiscovery`            | Navigation menu locations               |
| `AcfBlockDiscovery`        | ACF blocks                              |
| `BlockDiscovery`           | Native Gutenberg blocks                 |
| `AcfFieldGroupDiscovery`   | ACF field groups                        |
| `AcfOptionsPageDiscovery`  | ACF options pages                       |
| `BlockPatternDiscovery`    | Block patterns                          |

### Late Phase (`wp_loaded`)

Registers features that depend on earlier registrations:

| Discovery                     | Purpose                               |
| ----------------------------- | ------------------------------------- |
| `ContextProviderDiscovery`    | Template context providers            |
| `TemplateControllerDiscovery` | Template controllers                  |
| `RestRouteDiscovery`          | REST API endpoints                    |

## How Discovery Works

1. **Class scanning**: The runner scans all PHP files in the app directory using Composer's PSR-4 autoload map
2. **Discovery pass**: Each class is passed to every discovery's `discover()` method
3. **Phase execution**: At the appropriate WordPress hook, `apply()` is called for discoveries in that phase

```
after_setup_theme → runEarlyDiscoveries() → apply() for early discoveries
init              → runMainDiscoveries()  → apply() for main discoveries
wp_loaded         → runLateDiscoveries()  → apply() for late discoveries
```

## Cache Integration

When a `DiscoveryCache` is provided and has valid cached data:

1. Discoveries are restored from cache via `restoreFromCache()`
2. Class scanning is skipped entirely
3. `apply()` uses the cached items

This eliminates all reflection overhead in production.

## Related

- [WpDiscovery Interface](./wp-discovery)
- [Guide: Discovery Cache](/guide/discovery-cache)
- [Guide: Custom Discovery](/guide/custom-discovery)
- [FoehnConfig](./foehn-config)
