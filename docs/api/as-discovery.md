# #[AsDiscovery]

Declares the WordPress lifecycle phase a discovery class applies in.

A discovery is itself discovered: any class implementing `Tempest\Discovery\Discovery` inside a scanned location is found, resolved from the container and run — whether it ships with Føhn, comes from a Composer package or lives in the theme's `app/` directory. This attribute is how such a class chooses its timing.

## Signature

```php
<?php

namespace Studiometa\Foehn\Attributes;

use Attribute;
use Studiometa\Foehn\Discovery\DiscoveryPhase;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsDiscovery
{
    public function __construct(
        public DiscoveryPhase $phase = DiscoveryPhase::Main,
    ) {}
}
```

## Parameters

| Parameter | Type             | Default                | Description         |
| --------- | ---------------- | ---------------------- | ------------------- |
| `phase`   | `DiscoveryPhase` | `DiscoveryPhase::Main` | When `apply()` runs |

## DiscoveryPhase

| Case                    | WordPress hook      | Register here                                                    |
| ----------------------- | ------------------- | ---------------------------------------------------------------- |
| `DiscoveryPhase::Early` | `after_setup_theme` | Theme support, hooks, image sizes, Twig extensions, CLI commands |
| `DiscoveryPhase::Main`  | `init`              | Post types, taxonomies, blocks, meta, menus, cron                |
| `DiscoveryPhase::Late`  | `wp_loaded`         | REST routes, template controllers, context providers             |

The attribute is optional. A discovery without it applies in the `Main` phase, which is where most registration belongs.

## Usage

```php
<?php

namespace App\Discovery;

use Studiometa\Foehn\Attributes\AsDiscovery;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Studiometa\Foehn\Discovery\DiscoveryPhase;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;

#[AsDiscovery(phase: DiscoveryPhase::Early)]
final class WidgetDiscovery implements Discovery
{
    use IsWpDiscovery;

    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        // …
    }

    public function apply(): void
    {
        // …
    }
}
```

## Notes

- **Order within a phase is class name order.** Scan order is filesystem order, and a restored cache replays the order it was written in, so neither is stable enough to register against.
- **A discovery that cannot be constructed must opt out.** An abstract base class implementing `Discovery` is still found; mark it `#[Tempest\Discovery\SkipDiscovery]` so nothing tries to resolve it.
- **Only scanned locations are searched.** A package is scanned when it requires a `tempest/*` package or sets `extra.tempest.can-discover` in its `composer.json`.

## Related

- [Guide: Custom Discovery](/guide/custom-discovery)
- [DiscoveryRunner](./discovery-runner)
- [Guide: Discovery Cache](/guide/discovery-cache)
