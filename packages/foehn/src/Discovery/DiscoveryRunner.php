<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use Psr\Cache\CacheItemPoolInterface;
use ReflectionClass;
use Studiometa\Foehn\Attributes\AsDiscovery;
use Studiometa\Foehn\Config\FoehnConfig;
use Tempest\Container\Container;
use Tempest\Discovery\BootDiscovery;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryCache;
use Tempest\Discovery\DiscoveryCacheStrategy;
use Tempest\Discovery\DiscoveryConfig;
use Tempest\Discovery\DiscoveryDiscovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;
use Throwable;

/**
 * Orchestrates the discovery process across WordPress lifecycle phases.
 *
 * Scanning, location building and caching come from tempest/discovery. What Foehn
 * adds is timing: WordPress will not accept a post type before `init`, so this
 * runner discovers once and then calls apply() on each discovery at the phase it
 * belongs to. Tempest's own BootDiscovery::__invoke() applies everything at once,
 * which is why only its build() step is used here.
 *
 * Which discoveries exist is itself a discovery. Tempest's two-pass build finds
 * every class implementing Discovery in a scanned location and resolves it from
 * the container, so a package or a theme's app directory can add one; the phase
 * comes from #[AsDiscovery] on the class, defaulting to Main.
 */
final class DiscoveryRunner
{
    /** @var array<class-string<Discovery>, Discovery> */
    private array $discoveries = [];

    /** @var array<class-string<Discovery>, DiscoveryPhase> */
    private array $phases = [];

    /** @var array<value-of<DiscoveryPhase>, bool> */
    private array $ran = [];

    /** @var array<string, bool> Location path => restored from the cache rather than scanned */
    private array $restored = [];

    private bool $discovered = false;

    public function __construct(
        private readonly Container $container,
        private readonly DiscoveryCache $cache,
        private readonly CacheItemPoolInterface $pool,
        private readonly DiscoveryLocations $locations,
        private readonly ?FoehnConfig $config = null,
    ) {}

    /**
     * Run early discoveries (after_setup_theme).
     * These run before most WordPress initialization.
     */
    public function runEarlyDiscoveries(): void
    {
        $this->runPhase(DiscoveryPhase::Early);
    }

    /**
     * Run main discoveries (init).
     * Post types, taxonomies, blocks, and background jobs are registered here.
     */
    public function runMainDiscoveries(): void
    {
        $this->runPhase(DiscoveryPhase::Main);
    }

    /**
     * Run late discoveries (wp_loaded).
     * Template controllers and REST routes are registered here.
     */
    public function runLateDiscoveries(): void
    {
        $this->runPhase(DiscoveryPhase::Late);
    }

    /**
     * Run all discoveries for a given phase.
     */
    private function runPhase(DiscoveryPhase $phase): void
    {
        if ($this->hasRun($phase)) {
            return;
        }

        $this->ensureDiscovered();

        foreach ($this->discoveries as $discoveryClass => $discovery) {
            if ($this->phases[$discoveryClass] !== $phase) {
                continue;
            }

            $discovery->apply();
        }

        $this->ran[$phase->value] = true;
    }

    /**
     * The phase a discovery class declares, or Main when it declares nothing.
     *
     * @param class-string<Discovery> $discoveryClass
     */
    public static function phaseOf(string $discoveryClass): DiscoveryPhase
    {
        $attributes = new ReflectionClass($discoveryClass)->getAttributes(AsDiscovery::class);

        if ($attributes === []) {
            return DiscoveryPhase::Main;
        }

        return $attributes[0]->newInstance()->phase;
    }

    /**
     * Ensure all locations have been scanned and discoveries populated.
     *
     * Discoveries end up sorted by class name. Scan order is filesystem order and a
     * restored cache replays whatever order it was written in, so nothing about
     * discovery is stable enough to register against; sorting makes a cold request
     * and a warm one apply the same discoveries in the same sequence.
     */
    private function ensureDiscovered(): void
    {
        if ($this->discovered) {
            return;
        }

        $this->discovered = true;

        foreach ($this->build($this->cache) as $discovery) {
            // Tempest's own DiscoveryDiscovery is the first pass, not something Foehn
            // applies at a WordPress phase: its apply() only fills the list of
            // discovery classes the second pass then resolves.
            if ($discovery instanceof DiscoveryDiscovery) {
                continue;
            }

            $this->discoveries[$discovery::class] = $discovery;
            $this->phases[$discovery::class] = self::phaseOf($discovery::class);
        }

        ksort($this->discoveries);
    }

    /**
     * Scan every location with the given cache and return the populated discoveries.
     *
     * The build is Tempest's two-pass form: one pass finds the discovery classes,
     * the second runs them. Passing an explicit list instead would be the only way
     * to reach a discovery that is not in a scanned location — which is to say, one
     * nothing else could reach either.
     *
     * @return array<array-key, Discovery>
     */
    private function build(DiscoveryCache $cache): array
    {
        $locations = $this->locations->all();

        $discoveries = new BootDiscovery($this->container, new DiscoveryConfig(locations: $locations), $cache)->build(
            null,
            $locations,
        );

        $this->discoverOptInHooks($discoveries, $cache);

        // Which locations were scanned rather than restored. Answerable only now:
        // the discovery classes an entry has to hold are not known until the first
        // pass has run. The build itself only reads the cache, so what the pool
        // holds here is still what it held before it started — and what it holds
        // after store() would answer "cached" for a location just scanned.
        foreach ($locations as $location) {
            $this->restored[$location->path] = $this->wasRestored($cache, $location, $discoveries);
        }

        $scanned = array_values(array_filter($locations, fn(DiscoveryLocation $location): bool => $this->shouldStore(
            $cache,
            $location,
            $discoveries,
        )));

        $this->store($cache, $scanned, $discoveries);

        return $discoveries;
    }

    /**
     * Write what was scanned, so the next request does not scan it again.
     *
     * Caching is enabled by configuration and then never happens on its own: a
     * project that turned it on and did not run `discovery:generate` reflected over
     * every location on every request. Storing what a scan produced is the same work
     * the command does, at the one moment the result is already in hand.
     *
     * Nothing here may break a request. A read-only wp-content is a deployment
     * choice, not an error, and a site that cannot write its cache should still
     * serve pages.
     *
     * @param list<DiscoveryLocation> $locations
     * @param array<array-key, Discovery> $discoveries
     */
    private function store(DiscoveryCache $cache, array $locations, array $discoveries): void
    {
        foreach ($locations as $location) {
            try {
                $cache->store($location, $discoveries);
            } catch (Throwable $e) {
                $this->logDiscoveryFailure($location->namespace, $e);
            }
        }
    }

    /**
     * Whether this location's items came out of the cache rather than off disk.
     *
     * The same three conditions Tempest restores under, asked from outside: the
     * cache is on, the strategy reads this kind of location back, and the entry
     * holds every discovery. Nothing else can tell them apart afterwards — a
     * location that was scanned is cached a moment later, by the scan.
     *
     * @param array<array-key, Discovery> $discoveries
     */
    private function wasRestored(DiscoveryCache $cache, DiscoveryLocation $location, array $discoveries): bool
    {
        if (!$cache->enabled) {
            return false;
        }

        $readsBack = match ($cache->strategy) {
            DiscoveryCacheStrategy::FULL => true,
            DiscoveryCacheStrategy::PARTIAL => $location->isVendor(),
            default => false,
        };

        return $readsBack && $this->isCached($location, $discoveries);
    }

    /**
     * Whether a location's items were restored from the cache rather than scanned.
     *
     * What makes a stale cache diagnosable: `discovery:list` cannot ask the pool,
     * because by the time anything asks, the scan it is asking about has already
     * written its own entry.
     */
    public function wasRestoredFromCache(DiscoveryLocation $location): bool
    {
        $this->ensureDiscovered();

        return $this->restored[$location->path] ?? false;
    }

    /**
     * Whether a scan of this location is worth writing to the cache.
     *
     * Only what the strategy would read back: under PARTIAL, Tempest restores vendor
     * locations and always rescans the app, so storing the app's would write a file
     * on every request that nothing ever reads.
     *
     * @param array<array-key, Discovery> $discoveries
     */
    private function shouldStore(DiscoveryCache $cache, DiscoveryLocation $location, array $discoveries): bool
    {
        if (!$cache->enabled) {
            return false;
        }

        if ($cache->strategy === DiscoveryCacheStrategy::PARTIAL && !$location->isVendor()) {
            return false;
        }

        return !$this->isCached($location, $discoveries);
    }

    /**
     * Whether the cache holds an entry this runner can use for a location.
     *
     * An entry written before a discovery class existed is missing that key, and
     * Tempest rejects and rescans the whole location rather than restoring part of
     * it — so from here such an entry counts as absent, and gets rewritten.
     *
     * The pool is read rather than DiscoveryCache::restore(), which answers null for
     * a disabled cache and so cannot distinguish "nothing stored" from "not reading".
     *
     * @param array<array-key, Discovery> $discoveries
     */
    private function isCached(DiscoveryLocation $location, array $discoveries): bool
    {
        $item = $this->pool->getItem($location->key);

        if (!$item->isHit()) {
            return false;
        }

        $entry = $item->get();

        if (!is_array($entry)) {
            return false;
        }

        foreach ($discoveries as $discovery) {
            if (!array_key_exists($discovery::class, $entry)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Scan every location and write the result to the cache.
     *
     * Nothing is applied: warming is a build step, and a booted request that
     * applied a second time would register every hook twice.
     *
     * @return array<class-string<Discovery>, int> Item count per discovery class
     */
    public function warmCache(DiscoveryCache $cache): array
    {
        // Build against a disabled cache — otherwise a stale entry would be
        // restored and written straight back out.
        $discoveries = $this->build($cache->withStrategy(DiscoveryCacheStrategy::NONE));

        foreach ($this->locations->all() as $location) {
            $cache->store($location, $discoveries);
        }

        $counts = [];

        foreach ($discoveries as $discovery) {
            $count = count($discovery->getItems());

            if ($count === 0) {
                continue;
            }

            $counts[$discovery::class] = $count;
        }

        return $counts;
    }

    /**
     * Discover the opt-in hook classes listed in FoehnConfig.
     *
     * Those classes ship with the framework, so HookDiscovery ignores them where it
     * finds them — in a vendor location. They are handed to the discoveries again
     * here, against the app location, which is both what makes them opt-in and what
     * puts them in the app's slice of the discovery cache.
     *
     * @param array<array-key, Discovery> $discoveries
     */
    private function discoverOptInHooks(array $discoveries, DiscoveryCache $cache): void
    {
        if ($this->config === null || $this->config->hooks === []) {
            return;
        }

        $location = $this->locations->app();

        if ($location === null) {
            return;
        }

        // A warm cache already holds these items for the app location; discovering
        // them again would register every hook twice.
        if ($cache->enabled && $this->pool->getItem($location->key)->isHit()) {
            return;
        }

        foreach ($this->config->hooks as $hookClass) {
            if (!class_exists($hookClass)) {
                continue;
            }

            try {
                $reflector = new ClassReflector($hookClass);

                foreach ($discoveries as $discovery) {
                    $discovery->discover($location, $reflector);
                }
            } catch (Throwable $e) {
                $this->logDiscoveryFailure($hookClass, $e);

                continue;
            }
        }
    }

    /**
     * Log a discovery failure when debug mode is enabled.
     */
    private function logDiscoveryFailure(string $subject, Throwable $exception): void
    {
        if ($this->config === null || !$this->config->isDebugEnabled()) {
            return;
        }

        $message = sprintf('[Foehn] Discovery failed for "%s": %s', $subject, $exception->getMessage());

        trigger_error($message, E_USER_WARNING);
    }

    /**
     * Check if a discovery phase has been run.
     */
    public function hasRun(DiscoveryPhase $phase): bool
    {
        return $this->ran[$phase->value] ?? false;
    }

    /**
     * Get all registered discoveries, running discovery first if needed.
     *
     * @return array<class-string<Discovery>, Discovery>
     */
    public function getDiscoveries(): array
    {
        $this->ensureDiscovered();

        return $this->discoveries;
    }
}
