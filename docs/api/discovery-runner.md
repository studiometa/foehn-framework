# DiscoveryRunner

Orchestrates discovery across the WordPress lifecycle. Scanning, location building and caching come from `tempest/discovery`; what the runner adds is timing — WordPress will not accept a post type before `init`, so it discovers once and then calls `apply()` on each discovery at the phase that discovery declares.

## Signature

```php
<?php

namespace Studiometa\Foehn\Discovery;

use Psr\Cache\CacheItemPoolInterface;
use Studiometa\Foehn\Config\FoehnConfig;
use Tempest\Container\Container;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryCache;

final class DiscoveryRunner
{
    public function __construct(
        Container $container,
        DiscoveryCache $cache,
        CacheItemPoolInterface $pool,
        DiscoveryLocations $locations,
        ?FoehnConfig $config = null,
    );

    public function runEarlyDiscoveries(): void;
    public function runMainDiscoveries(): void;
    public function runLateDiscoveries(): void;
    public function hasRun(DiscoveryPhase $phase): bool;

    /** @return array<class-string<Discovery>, Discovery> */
    public function getDiscoveries(): array;

    /** @return array<class-string<Discovery>, int> */
    public function warmCache(DiscoveryCache $cache): array;
}
```

## Which discoveries run

Nothing lists them. A class implementing `Tempest\Discovery\Discovery` inside a scanned location is found, resolved from the container and run — the framework's own nineteen included. That is what lets a package or a theme's `app/` directory add one; see [Custom Discovery](/guide/custom-discovery).

The phase comes from [`#[AsDiscovery]`](./as-discovery) on the class, defaulting to `DiscoveryPhase::Main`. Within a phase, discoveries apply in class name order.

## Discovery phases

```
after_setup_theme → runEarlyDiscoveries() → DiscoveryPhase::Early
init              → runMainDiscoveries()  → DiscoveryPhase::Main
wp_loaded         → runLateDiscoveries()  → DiscoveryPhase::Late
```

`Kernel` registers all three at priority 1. Each phase runs at most once; a second call is a no-op, which is what `hasRun()` reports.

| Phase   | Framework discoveries                                                                                                                                                                                           |
| ------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Early` | `CliCommandDiscovery`, `HookDiscovery`, `ImageSizeDiscovery`, `ShortcodeDiscovery`, `TimberModelDiscovery`, `TwigExtensionDiscovery`                                                                            |
| `Main`  | `AcfBlockDiscovery`, `AcfFieldGroupDiscovery`, `AcfOptionsPageDiscovery`, `BlockDiscovery`, `BlockPatternDiscovery`, `CronDiscovery`, `JobDiscovery`, `MenuDiscovery`, `PostTypeDiscovery`, `TaxonomyDiscovery` |
| `Late`  | `ContextProviderDiscovery`, `RestRouteDiscovery`, `TemplateControllerDiscovery`                                                                                                                                 |

## How a request builds them

1. **Locations.** `DiscoveryLocations` reads Composer's `installed.json` for the project's own namespaces plus every package that opts into discovery.
2. **First pass.** Every location is scanned for classes implementing `Discovery`.
3. **Second pass.** Those classes are resolved from the container and every scanned class is handed to each one's `discover()`.
4. **Phases.** At `after_setup_theme`, `init` and `wp_loaded`, `apply()` is called on the discoveries belonging to that phase.

Both passes read the discovery cache: a location whose entry is warm is restored rather than scanned. A location that had to be scanned is written back, so the next request does not scan it — see [Discovery Cache](/guide/discovery-cache).

## Related

- [#[AsDiscovery]](./as-discovery)
- [Guide: Custom Discovery](/guide/custom-discovery)
- [Guide: Discovery Cache](/guide/discovery-cache)
- [FoehnConfig](./foehn-config)
