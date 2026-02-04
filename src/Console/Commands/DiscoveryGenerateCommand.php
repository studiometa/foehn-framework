<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\Discovery\DiscoveryCache;
use Studiometa\Foehn\Discovery\DiscoveryRunner;
use Tempest\Container\Container;
use Tempest\Core\DiscoveryCacheStrategy;
use Tempest\Discovery\Discovery;

#[AsCliCommand(name: 'discovery:generate', description: 'Generate and cache all discoveries', longDescription: <<<'DOC'
    ## DESCRIPTION

    Compiles and caches all discovery results for production use.
    This command scans all classes for attributes and caches the results,
    improving performance by avoiding runtime reflection.

    Run this command:
    - During deployment
    - After clearing the discovery cache
    - When updating to production

    ## OPTIONS

    [--strategy=<strategy>]
    : Cache strategy to use (full, partial). Defaults to configured strategy.

    [--clear]
    : Clear existing cache before generating

    ## EXAMPLES

        # Generate discovery cache
        wp tempest discovery:generate

        # Generate with full caching strategy
        wp tempest discovery:generate --strategy=full

        # Clear and regenerate
        wp tempest discovery:generate --clear
    DOC)]
final class DiscoveryGenerateCommand implements CliCommandInterface
{
    public function __construct(
        private readonly WpCli $cli,
        private readonly DiscoveryCache $discoveryCache,
        private readonly FoehnConfig $config,
        private readonly Container $container,
    ) {}

    /**
     * @param array<int, string> $args
     * @param array<string, string> $assocArgs
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        // Determine strategy
        $strategy = $this->determineStrategy($assocArgs);

        if ($strategy === DiscoveryCacheStrategy::NONE) {
            $this->cli->warning('Discovery cache is disabled. Set discovery_cache config or use --strategy option.');

            return;
        }

        // Clear if requested
        if (isset($assocArgs['clear'])) {
            $this->cli->log('Clearing existing cache...');
            $this->discoveryCache->clear();
        }

        $this->cli->log("Generating discovery cache using '{$strategy->value}' strategy...");

        // Run all discoveries and collect their data
        $cacheData = $this->collectDiscoveryData();

        // Store the cache
        $this->discoveryCache->store($cacheData);
        $this->discoveryCache->storeStrategy($strategy);

        $discoveryCount = count($cacheData);
        $this->cli->success("Discovery cache generated successfully ({$discoveryCount} discoveries cached).");
        $this->cli->line('');
        $this->cli->log('Cached discoveries:');

        foreach (array_keys($cacheData) as $discoveryClass) {
            $shortName = $this->getShortClassName($discoveryClass);
            $itemCount = count($cacheData[$discoveryClass]);
            $this->cli->log("  - {$shortName}: {$itemCount} items");
        }
    }

    /**
     * Determine the cache strategy to use.
     *
     * @param array<string, string> $assocArgs
     */
    private function determineStrategy(array $assocArgs): DiscoveryCacheStrategy
    {
        if (isset($assocArgs['strategy'])) {
            return DiscoveryCacheStrategy::make($assocArgs['strategy']);
        }

        // Use configured strategy, defaulting to FULL if not set
        $configuredStrategy = $this->config->discoveryCacheStrategy;

        if ($configuredStrategy === DiscoveryCacheStrategy::NONE) {
            // Default to FULL for generate command if no strategy configured
            return DiscoveryCacheStrategy::FULL;
        }

        return $configuredStrategy;
    }

    /**
     * Collect data from all discoveries.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function collectDiscoveryData(): array
    {
        /** @var array<string, array<int, array<string, mixed>>> $cacheData */
        $cacheData = [];

        foreach (DiscoveryRunner::getAllDiscoveryClasses() as $discoveryClass) {
            /** @var Discovery $discovery */
            $discovery = $this->container->get($discoveryClass);

            // Get cacheable data from the discovery
            if (method_exists($discovery, 'getCacheableData')) {
                /** @var array<int, array<string, mixed>> $data */
                $data = $discovery->getCacheableData();

                if (!empty($data)) {
                    $cacheData[$discoveryClass] = $data;
                }
            }
        }

        return $cacheData;
    }

    /**
     * Get short class name from FQCN.
     */
    private function getShortClassName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);
        $last = end($parts);

        return $last !== false ? $last : $fqcn;
    }
}
