<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use ReflectionClass;
use Studiometa\Foehn\Config\FoehnConfig;
use Tempest\Container\Container;

/**
 * Orchestrates the discovery process across WordPress lifecycle phases.
 *
 * This runner fully owns the discovery lifecycle:
 * 1. Scans classes using Composer's PSR-4 autoload map
 * 2. Calls discover() on each WpDiscovery for every class found
 * 3. Calls apply() at the correct WordPress hook timing
 *
 * Tempest is used only for the DI container, not for discovery.
 */
final class DiscoveryRunner
{
    /** @var array<class-string<WpDiscovery>, WpDiscovery> */
    private array $discoveries = [];

    /** @var array<string, array<string, list<array<string, mixed>>>>|null */
    private ?array $cachedData = null;

    /** @var DiscoveryLocation|null */
    private ?DiscoveryLocation $appLocation = null;

    private bool $cacheLoaded = false;
    private bool $discovered = false;
    private bool $earlyRan = false;
    private bool $mainRan = false;
    private bool $lateRan = false;

    public function __construct(
        private readonly Container $container,
        private readonly ?DiscoveryCache $cache = null,
        private readonly ?string $appPath = null,
        private readonly ?FoehnConfig $config = null,
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

        $this->ensureDiscovered();

        // Hook discovery runs early to catch after_setup_theme hooks
        $this->applyDiscovery(HookDiscovery::class);

        // Image sizes need to be registered early (enables post-thumbnails support)
        $this->applyDiscovery(ImageSizeDiscovery::class);

        // Shortcodes can be registered early
        $this->applyDiscovery(ShortcodeDiscovery::class);

        // CLI commands are registered early so they're available immediately
        $this->applyDiscovery(CliCommandDiscovery::class);

        // Timber class maps need to be available before queries
        $this->applyDiscovery(TimberModelDiscovery::class);

        // Twig extensions are registered early so they're available for all templates
        $this->applyDiscovery(TwigExtensionDiscovery::class);

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

        $this->ensureDiscovered();

        // Post types and taxonomies
        $this->applyDiscovery(PostTypeDiscovery::class);
        $this->applyDiscovery(TaxonomyDiscovery::class);

        // Menus
        $this->applyDiscovery(MenuDiscovery::class);

        // Blocks
        $this->applyDiscovery(AcfBlockDiscovery::class);
        $this->applyDiscovery(BlockDiscovery::class);

        // ACF Field Groups
        $this->applyDiscovery(AcfFieldGroupDiscovery::class);

        // ACF Options Pages
        $this->applyDiscovery(AcfOptionsPageDiscovery::class);
        // Block patterns
        $this->applyDiscovery(BlockPatternDiscovery::class);

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

        $this->ensureDiscovered();

        // Context providers and template controllers
        $this->applyDiscovery(ContextProviderDiscovery::class);
        $this->applyDiscovery(TemplateControllerDiscovery::class);

        // REST API routes
        $this->applyDiscovery(RestRouteDiscovery::class);

        $this->lateRan = true;
    }

    /**
     * Ensure all classes have been scanned and discoveries populated.
     */
    private function ensureDiscovered(): void
    {
        if ($this->discovered) {
            return;
        }

        $this->discovered = true;
        $this->loadCache();

        // Initialize all discovery instances
        foreach (self::getAllDiscoveryClasses() as $discoveryClass) {
            if (($this->discoveries[$discoveryClass] ?? null) !== null) {
                continue;
            }

            $this->discoveries[$discoveryClass] = $this->container->get($discoveryClass);
        }

        // If we have cached data, restore discoveries from cache
        if ($this->cachedData !== null) {
            foreach ($this->discoveries as $className => $discovery) {
                if (
                    ($this->cachedData[$className] ?? null) === null
                    || !method_exists($discovery, 'restoreFromCache')
                ) {
                    continue;
                }

                /** @var array<string, list<array<string, mixed>>> $discoveryData */
                $discoveryData = $this->cachedData[$className];
                $discovery->restoreFromCache($discoveryData);
            }

            return;
        }

        // Build app location
        $this->appLocation = $this->buildAppLocation();

        // No cache — scan classes and run discover()
        if ($this->appLocation !== null) {
            $classes = $this->scanClasses();

            foreach ($classes as $class) {
                foreach ($this->discoveries as $discovery) {
                    $discovery->discover($this->appLocation, $class);
                }
            }
        }

        // Also discover opt-in hook classes from config
        $this->discoverOptInHooks();
    }

    /**
     * Build the DiscoveryLocation for the app directory.
     */
    private function buildAppLocation(): ?DiscoveryLocation
    {
        if ($this->appPath === null) {
            return null;
        }

        return ClassScanner::buildLocation($this->appPath);
    }

    /**
     * Discover opt-in hook classes from FoehnConfig.
     */
    private function discoverOptInHooks(): void
    {
        try {
            $config = $this->container->get(\Studiometa\Foehn\Config\FoehnConfig::class);
        } catch (\Throwable) {
            return;
        }

        // Use the app location for opt-in hooks, or create a fallback
        $location = $this->appLocation ?? DiscoveryLocation::app('App\\', '');

        foreach ($config->hooks as $hookClass) {
            if (!class_exists($hookClass)) {
                continue;
            }

            try {
                $reflection = new ReflectionClass($hookClass);

                foreach ($this->discoveries as $discovery) {
                    $discovery->discover($location, $reflection);
                }
            } catch (\ReflectionException $e) {
                $this->logDiscoveryFailure($hookClass, $e);

                continue;
            }
        }
    }

    /**
     * Apply a specific discovery.
     *
     * @param class-string<WpDiscovery> $discoveryClass
     */
    private function applyDiscovery(string $discoveryClass): void
    {
        if (($this->discoveries[$discoveryClass] ?? null) === null) {
            return;
        }

        $this->discoveries[$discoveryClass]->apply();
    }

    /**
     * Scan classes from the app directory using Composer's PSR-4 autoload map.
     *
     * @return array<ReflectionClass<object>>
     */
    private function scanClasses(): array
    {
        if ($this->appLocation === null) {
            return [];
        }

        return ClassScanner::scan($this->appLocation);
    }

    /**
     * Log a discovery failure when debug mode is enabled.
     */
    private function logDiscoveryFailure(string $className, \ReflectionException $exception): void
    {
        if ($this->config === null || !$this->config->isDebugEnabled()) {
            return;
        }

        $message = sprintf('[Foehn] Discovery failed for class "%s": %s', $className, $exception->getMessage());

        trigger_error($message, E_USER_WARNING);
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
     * @return array<class-string<WpDiscovery>, WpDiscovery>
     */
    public function getDiscoveries(): array
    {
        return $this->discoveries;
    }

    /**
     * Get discovery classes for each phase.
     *
     * @return array<string, array<class-string<WpDiscovery>>>
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
     * @return array<class-string<WpDiscovery>>
     */
    public static function getAllDiscoveryClasses(): array
    {
        /** @var array<class-string<WpDiscovery>> */
        return array_merge(
            self::getDiscoveryPhases()['early'],
            self::getDiscoveryPhases()['main'],
            self::getDiscoveryPhases()['late'],
        );
    }
}
