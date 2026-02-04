<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Discovery;

use Tempest\Container\Container;
use Tempest\Discovery\Discovery;

/**
 * Orchestrates the discovery process across WordPress lifecycle phases.
 */
final class DiscoveryRunner
{
    /** @var array<class-string, Discovery> */
    private array $discoveries = [];

    /** @var array<string, array<string, mixed>>|null */
    private ?array $cachedData = null;

    private bool $cacheLoaded = false;
    private bool $earlyRan = false;
    private bool $mainRan = false;
    private bool $lateRan = false;

    public function __construct(
        private readonly Container $container,
        private readonly ?DiscoveryCache $cache = null,
    ) {}

    /**
     * Run early discoveries (after_setup_theme).
     * These run before most WordPress initialization.
     */
    public function runEarlyDiscoveries(): void
    {
        if ($this->earlyRan) {
            return;
        }

        $this->loadCache();

        // Hook discovery runs early to catch after_setup_theme hooks
        $this->runDiscovery(HookDiscovery::class);

        // Shortcodes can be registered early
        $this->runDiscovery(ShortcodeDiscovery::class);

        // CLI commands are registered early so they're available immediately
        $this->runDiscovery(CliCommandDiscovery::class);

        $this->earlyRan = true;
    }

    /**
     * Run main discoveries (init).
     * Post types, taxonomies, and blocks are registered here.
     */
    public function runMainDiscoveries(): void
    {
        if ($this->mainRan) {
            return;
        }

        $this->loadCache();

        // Post types and taxonomies
        $this->runDiscovery(PostTypeDiscovery::class);
        $this->runDiscovery(TaxonomyDiscovery::class);

        // Blocks
        $this->runDiscovery(AcfBlockDiscovery::class);
        $this->runDiscovery(BlockDiscovery::class);

        // Block patterns
        $this->runDiscovery(BlockPatternDiscovery::class);

        $this->mainRan = true;
    }

    /**
     * Run late discoveries (wp_loaded).
     * Template controllers and REST routes are registered here.
     */
    public function runLateDiscoveries(): void
    {
        if ($this->lateRan) {
            return;
        }

        $this->loadCache();

        // View composers and template controllers
        $this->runDiscovery(ViewComposerDiscovery::class);
        $this->runDiscovery(TemplateControllerDiscovery::class);

        // REST API routes
        $this->runDiscovery(RestRouteDiscovery::class);

        $this->lateRan = true;
    }

    /**
     * Run a specific discovery.
     *
     * @param class-string<Discovery> $discoveryClass
     */
    private function runDiscovery(string $discoveryClass): void
    {
        if (isset($this->discoveries[$discoveryClass])) {
            return;
        }

        /** @var Discovery $discovery */
        $discovery = $this->container->get($discoveryClass);
        $this->discoveries[$discoveryClass] = $discovery;

        // If we have cached data for this discovery, restore it
        if ($this->cachedData !== null && isset($this->cachedData[$discoveryClass])) {
            $this->restoreDiscoveryFromCache($discovery, $this->cachedData[$discoveryClass]);
        }

        // Apply the discovery
        if (method_exists($discovery, 'apply')) {
            $discovery->apply();
        }
    }

    /**
     * Restore discovery items from cached data.
     *
     * @param Discovery $discovery
     * @param array<string, mixed> $data
     */
    private function restoreDiscoveryFromCache(Discovery $discovery, array $data): void
    {
        // The discovery items are stored directly in the cache
        // We need to inject them back into the discovery
        if (method_exists($discovery, 'restoreFromCache')) {
            $discovery->restoreFromCache($data);
        }
    }

    /**
     * Load cache if available and not already loaded.
     */
    private function loadCache(): void
    {
        if ($this->cacheLoaded) {
            return;
        }

        $this->cacheLoaded = true;

        if ($this->cache === null || !$this->cache->isEnabled()) {
            return;
        }

        $this->cachedData = $this->cache->restore();
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
     * Get all registered discoveries.
     *
     * @return array<class-string, Discovery>
     */
    public function getDiscoveries(): array
    {
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
                ShortcodeDiscovery::class,
                CliCommandDiscovery::class,
            ],
            'main' => [
                PostTypeDiscovery::class,
                TaxonomyDiscovery::class,
                AcfBlockDiscovery::class,
                BlockDiscovery::class,
                BlockPatternDiscovery::class,
            ],
            'late' => [
                ViewComposerDiscovery::class,
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
        $phases = self::getDiscoveryPhases();

        return array_merge(...array_values($phases));
    }
}
