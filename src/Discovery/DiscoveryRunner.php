<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Discovery;

use Tempest\Container\Container;

/**
 * Orchestrates the discovery process across WordPress lifecycle phases.
 */
final class DiscoveryRunner
{
    /** @var array<class-string, object> */
    private array $discoveries = [];

    private bool $earlyRan = false;
    private bool $mainRan = false;
    private bool $lateRan = false;

    public function __construct(
        private readonly Container $container,
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
     * @param class-string $discoveryClass
     */
    private function runDiscovery(string $discoveryClass): void
    {
        if (isset($this->discoveries[$discoveryClass])) {
            return;
        }

        $discovery = $this->container->get($discoveryClass);
        $this->discoveries[$discoveryClass] = $discovery;

        // The discovery has already been populated by Tempest's boot process
        // We just need to apply it
        if (method_exists($discovery, 'apply')) {
            $discovery->apply();
        }
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
}
