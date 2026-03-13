# Tempest 3.4 Upgrade Plan

> Date: 2026-03-13
> Blog post: https://tempestphp.com/blog/truly-decoupled-discovery
> PR: https://github.com/tempestphp/tempest-framework/pull/2041

## Status: ✅ Migration complete

Dependency changed from `tempest/framework` (all ~30 packages) to 3 individual sub-packages.
All 1124 tests passing on PHP 8.5. Phase 4 (Discovery interface alignment) deferred.

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

## Upgrade phases

### Phase 1: Bump + namespace fixes ✅ DONE

Applied manually (7 files, `Tempest\Core\DiscoveryCacheStrategy` → `Tempest\Discovery\DiscoveryCacheStrategy`).

### Phase 2: Replace `Tempest::boot()` with `GenericContainer` ✅ DONE

Removed full framework bootstrap. Kernel now creates a standalone `GenericContainer`
without loading dotenv, Composer, config discovery, event bus, exception handlers, etc.

### Phase 3: Replace `tempest/framework` with sub-packages ✅ DONE

Replaced single `tempest/framework` dependency with:
- `tempest/container` — DI container, autowiring
- `tempest/discovery` — SkipDiscovery, DiscoveryCacheStrategy
- `tempest/generation` — ClassManipulator for stubs

Transitive: `tempest/reflection`, `tempest/support`.
Went from ~30 installed Tempest packages to 5.

### Phase 4: Extend `Tempest\Discovery\Discovery` — DEFERRED

After analysis, aligning `WpDiscovery` with Tempest's `Discovery` interface offers
minimal value vs significant churn (19 discoveries + 50+ test files):

- **`ClassScanner`**: Still needed — `BootDiscovery` manages its own discovery classes,
  while we need phased `DiscoveryRunner` calling `ClassScanner::scan()`.
- **`DiscoveryLocation`**: Tempest's version calls `realpath()` in constructor, breaking
  all tests that use fake paths like `/tmp/test-app`. Only 42 lines saved.
- **`WpDiscoveryItems`**: Different keying (namespace vs path) and serialization.
- **`ReflectionClass` → `ClassReflector`**: Would require `$class->getReflection()->`
  for `isSubclassOf()`, `implementsInterface()`, `getMethods()` — more verbose, no gain.

This phase can be revisited if Tempest's Discovery API evolves to support lifecycle
phases or if we need interop with third-party Tempest discoveries.

## Architecture validation

Tempest 3.4 **validates our architecture**: we already decoupled our discovery from
Tempest's, which is exactly the direction Tempest is taking for third-party projects.
Our `WpDiscovery` interface, `WpDiscoveryItems`, `DiscoveryLocation`, `ClassScanner`,
and `DiscoveryRunner` all exist because WordPress needs lifecycle-aware discovery.

## Summary

| Phase | Status | Impact |
|-------|--------|--------|
| 1. Namespace fixes | ✅ Done | 7 files, 1 import each |
| 2. Remove `Tempest::boot()` | ✅ Done | -109 lines, faster boot |
| 3. Sub-packages | ✅ Done | 30 → 5 Tempest packages |
| 4. Align Discovery | ⏸ Deferred | Low value, high churn |
