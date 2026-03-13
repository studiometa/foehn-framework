# Tempest 3.4 Upgrade Plan

> Date: 2026-03-13
> Blog post: https://tempestphp.com/blog/truly-decoupled-discovery
> PR: https://github.com/tempestphp/tempest-framework/pull/2041

## Status: ✅ Phase 1 done — namespace migration complete

**Current constraint**: `"tempest/framework": "^3.4"` in `packages/foehn/composer.json`.
All 1120 tests passing on PHP 8.5.

## What Tempest 3.4 brings

### Decoupled discovery (`tempest/discovery` standalone)

- `tempest/discovery` can now be used without the full framework
- New `BootDiscovery` class bootstraps discovery with any PSR-11 container
- `DiscoveryConfig::autoload(__DIR__)` auto-detects scan locations from `composer.json`
- Discovery classes implementing `Tempest\Discovery\Discovery` are auto-discovered

### Breaking changes (namespace moves)

| Before (3.0–3.3)                      | After (3.4+)                               |
| ------------------------------------- | ------------------------------------------ |
| `Tempest\Core\DiscoveryCacheStrategy` | `Tempest\Discovery\DiscoveryCacheStrategy` |
| `Tempest\Core\DiscoveryCache`         | `Tempest\Discovery\DiscoveryCache`         |
| `Tempest\Core\DiscoveryConfig`        | `Tempest\Discovery\DiscoveryConfig`        |
| `Tempest\Core\Composer`               | `Tempest\Discovery\Composer`               |

Rector handles this automatically via `TempestSetList::TEMPEST_34`.

### Files to update (7 files, 1 import each)

- `src/Config/FoehnConfig.php` — `DiscoveryCacheStrategy`
- `src/Discovery/DiscoveryCache.php` — `DiscoveryCacheStrategy`
- `src/Console/Commands/DiscoveryWarmCommand.php` — `DiscoveryCacheStrategy`
- `src/Console/Commands/DiscoveryGenerateCommand.php` — `DiscoveryCacheStrategy`
- `tests/Unit/Config/FoehnConfigTest.php` — `DiscoveryCacheStrategy`
- `tests/Unit/Discovery/DiscoveryCacheTest.php` — `DiscoveryCacheStrategy`
- `tests/Unit/Discovery/DiscoveryRunnerIntegrationTest.php` — `DiscoveryCacheStrategy`

## Upgrade phases (when PHP 8.5 is available)

### Phase 1: Bump + namespace fixes (effort: low) ✅ DONE

Applied manually (7 files, `Tempest\Core\DiscoveryCacheStrategy` → `Tempest\Discovery\DiscoveryCacheStrategy`).

### Phase 2: Replace `Tempest::boot()` with `BootDiscovery` (effort: medium)

Replace the full framework bootstrap in `Kernel::initializeTempest()`:

```php
// Before (boots entire Tempest framework)
Tempest::boot(self::findProjectRoot($this->appPath));
$this->container = \Tempest\Container\get(Container::class);

// After (boots only container + discovery)
$container = new GenericContainer();
new BootDiscovery(
    container: $container,
    config: DiscoveryConfig::autoload($projectRoot),
)();
$this->container = $container;
```

**Benefits**: faster boot, reduced memory, no unused packages loaded.
**Risk**: need to verify all services resolve correctly without full Tempest kernel.

### Phase 3: Replace `tempest/framework` with sub-packages (effort: medium-high)

What Foehn actually uses:

| Package              | Usage                                                          |
| -------------------- | -------------------------------------------------------------- |
| `tempest/container`  | DI container, `get()`, `GenericContainer`, `Singleton`         |
| `tempest/discovery`  | `SkipDiscovery`, `DiscoveryCacheStrategy`, `DiscoveryLocation` |
| `tempest/reflection` | Indirectly via container                                       |
| `tempest/support`    | `Filesystem` (in `GeneratesFiles`)                             |
| `tempest/generation` | `ClassManipulator` (in `GeneratesFiles`)                       |

Would go from ~30 installed packages to ~5.

### Phase 4: Extend `Tempest\Discovery\Discovery` (effort: high)

Migrate `WpDiscovery` to extend `Tempest\Discovery\Discovery`:

- Switch from `ReflectionClass` to `ClassReflector`
- Switch from `WpDiscoveryItems` to `DiscoveryItems`
- Remove custom `ClassScanner` (replaced by `BootDiscovery`)
- Keep WordPress phase management (early/main/late)

19 discovery files + ClassScanner + DiscoveryRunner + tests affected.

### Phase 5: Not recommended

Replacing our own `WpDiscovery` entirely with Tempest's `Discovery` is **not recommended** because:

- Tempest has no concept of lifecycle phases (early/main/late)
- WordPress hooks must be registered at specific moments
- Our `WpDiscoveryItems` supports location-based caching tailored for WP

## Architecture validation

Tempest 3.4 **validates our architecture**: we already decoupled our discovery from
Tempest's, which is exactly the direction Tempest is taking for third-party projects.
Our `WpDiscovery` interface, `WpDiscoveryItems`, `DiscoveryLocation`, `ClassScanner`,
and `DiscoveryRunner` all exist because WordPress needs lifecycle-aware discovery.
