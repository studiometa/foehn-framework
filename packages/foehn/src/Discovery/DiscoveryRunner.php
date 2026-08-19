<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use Psr\Cache\CacheItemPoolInterface;
use Studiometa\Foehn\Config\FoehnConfig;
use Tempest\Container\Container;
use Tempest\Discovery\BootDiscovery;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryCache;
use Tempest\Discovery\DiscoveryCacheStrategy;
use Tempest\Discovery\DiscoveryConfig;
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
 */
final class DiscoveryRunner
{
    /** @var array<class-string<Discovery>, Discovery> */
    private array $discoveries = [];

    private bool $discovered = false;
    private bool $earlyRan = false;
    private bool $mainRan = false;
    private bool $lateRan = false;

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
        $this->runPhase('early');
    }

    /**
     * Run main discoveries (init).
     * Post types, taxonomies, blocks, and background jobs are registered here.
     */
    public function runMainDiscoveries(): void
    {
        $this->runPhase('main');
    }

    /**
     * Run late discoveries (wp_loaded).
     * Template controllers and REST routes are registered here.
     */
    public function runLateDiscoveries(): void
    {
        $this->runPhase('late');
    }

    /**
     * Run all discoveries for a given phase.
     *
     * @param 'early'|'main'|'late' $phase
     */
    private function runPhase(string $phase): void
    {
        if ($this->hasRun($phase)) {
            return;
        }

        $this->ensureDiscovered();

        foreach (self::getDiscoveryPhases()[$phase] as $discoveryClass) {
            $this->applyDiscovery($discoveryClass);
        }

        match ($phase) {
            'early' => $this->earlyRan = true,
            'main' => $this->mainRan = true,
            'late' => $this->lateRan = true,
        };
    }

    /**
     * Ensure all locations have been scanned and discoveries populated.
     */
    private function ensureDiscovered(): void
    {
        if ($this->discovered) {
            return;
        }

        $this->discovered = true;

        foreach ($this->build($this->cache) as $discovery) {
            $this->discoveries[$discovery::class] = $discovery;
        }
    }

    /**
     * Scan every location with the given cache and return the populated discoveries.
     *
     * @return array<array-key, Discovery>
     */
    private function build(DiscoveryCache $cache): array
    {
        $locations = $this->locations->all();

        $discoveries = new BootDiscovery($this->container, new DiscoveryConfig(locations: $locations), $cache)->build(
            self::getAllDiscoveryClasses(),
            $locations,
        );

        $this->discoverOptInHooks($discoveries, $cache);

        return $discoveries;
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
     * Apply a specific discovery.
     *
     * @param class-string<Discovery> $discoveryClass
     */
    private function applyDiscovery(string $discoveryClass): void
    {
        if (($this->discoveries[$discoveryClass] ?? null) === null) {
            return;
        }

        $this->discoveries[$discoveryClass]->apply();
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
    public function hasRun(string $phase): bool
    {
        return match ($phase) {
            'early' => $this->earlyRan,
            'main' => $this->mainRan,
            'late' => $this->lateRan,
            default => false,
        };
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

    /**
     * Get discovery classes for each phase.
     *
     * @return array<string, array<class-string<Discovery>>>
     */
    public static function getDiscoveryPhases(): array
    {
        return [
            'early' => [
                HookDiscovery::class,
                ImageSizeDiscovery::class,
                ShortcodeDiscovery::class,
                CliCommandDiscovery::class,
                TimberModelDiscovery::class,
                TwigExtensionDiscovery::class,
            ],
            'main' => [
                PostTypeDiscovery::class,
                TaxonomyDiscovery::class,
                MenuDiscovery::class,
                AcfBlockDiscovery::class,
                AcfFieldGroupDiscovery::class,
                BlockDiscovery::class,
                BlockPatternDiscovery::class,
                AcfOptionsPageDiscovery::class,
                CronDiscovery::class,
                JobDiscovery::class,
            ],
            'late' => [
                ContextProviderDiscovery::class,
                TemplateControllerDiscovery::class,
                RestRouteDiscovery::class,
            ],
        ];
    }

    /**
     * Get all discovery classes.
     *
     * @return array<class-string<Discovery>>
     */
    public static function getAllDiscoveryClasses(): array
    {
        /** @var array<class-string<Discovery>> */
        return array_merge(
            self::getDiscoveryPhases()['early'],
            self::getDiscoveryPhases()['main'],
            self::getDiscoveryPhases()['late'],
        );
    }
}
