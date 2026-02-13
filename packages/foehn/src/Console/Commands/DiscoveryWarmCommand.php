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

#[AsCliCommand(name: 'discovery:warm', description: 'Warm discovery cache by running all discoveries', longDescription: <<<'DOC'
    ## DESCRIPTION

    Warms up the discovery cache by running all discoveries and caching the results.
    This is useful during deployment to avoid slow initial page loads.

    Unlike discovery:generate which only scans and caches, this command actually
    runs all discovery phases (early, main, late) to ensure everything is discovered
    and properly cached.

    Run this command:
    - During deployment after code changes
    - After clearing the discovery cache
    - As part of your CI/CD pipeline

    ## OPTIONS

    [--strategy=<strategy>]
    : Cache strategy to use (full, partial). Defaults to configured strategy.

    ## EXAMPLES

        # Warm discovery cache
        wp tempest discovery:warm

        # Warm with specific strategy
        wp tempest discovery:warm --strategy=full
    DOC)]
final class DiscoveryWarmCommand implements CliCommandInterface
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

        $this->cli->log('Warming discovery cache...');

        // Clear existing cache to force re-discovery
        $this->discoveryCache->clear();

        // Get a fresh discovery runner that will scan all classes
        /** @var DiscoveryRunner $runner */
        $runner = $this->container->get(DiscoveryRunner::class);

        // Run all discovery phases
        $runner->runEarlyDiscoveries();
        $runner->runMainDiscoveries();
        $runner->runLateDiscoveries();

        // Collect and display discovery statistics
        $discoveries = $runner->getDiscoveries();
        $cacheData = $this->collectAndDisplayStats($discoveries);

        // Store the cache
        $this->discoveryCache->store($cacheData);
        $this->discoveryCache->storeStrategy($strategy);

        // Display cache location
        $cachePath = $this->config->getDiscoveryCachePath() . '/discoveries.php';
        $this->cli->log("Cache written to: {$cachePath}");
        $this->cli->success('Discovery cache warmed successfully.');
    }

    /**
     * Determine the cache strategy to use.
     *
     * @param array<string, string> $assocArgs
     */
    private function determineStrategy(array $assocArgs): DiscoveryCacheStrategy
    {
        if (($assocArgs['strategy'] ?? null) !== null) {
            return DiscoveryCacheStrategy::resolveFromInput($assocArgs['strategy']);
        }

        // Use configured strategy, defaulting to FULL if not set
        $configuredStrategy = $this->config->discoveryCacheStrategy;

        if ($configuredStrategy === DiscoveryCacheStrategy::NONE) {
            // Default to FULL for warm command if no strategy configured
            return DiscoveryCacheStrategy::FULL;
        }

        return $configuredStrategy;
    }

    /**
     * Collect cache data and display statistics.
     *
     * @param array<class-string, object> $discoveries
     * @return array<string, array<string, list<array<string, mixed>>>>
     */
    private function collectAndDisplayStats(array $discoveries): array
    {
        /** @var array<string, array<string, list<array<string, mixed>>>> $cacheData */
        $cacheData = [];

        foreach ($discoveries as $className => $discovery) {
            // Get cacheable data from the discovery
            if (!method_exists($discovery, 'getCacheableData')) {
                continue;
            }

            /** @var array<string, list<array<string, mixed>>> $data */
            $data = $discovery->getCacheableData();

            if (empty($data)) {
                continue;
            }

            $cacheData[$className] = $data;

            // Display stats — count items across all locations
            $label = $this->getDiscoveryLabel($className);
            $count = array_sum(array_map('count', $data));
            $this->cli->log("  ✓ {$count} {$label} discovered");
        }

        return $cacheData;
    }

    /**
     * Get a human-readable label for a discovery class.
     */
    private function getDiscoveryLabel(string $className): string
    {
        // Extract the short class name and convert to readable label
        $shortName = substr((string) strrchr($className, '\\'), 1);

        // Remove "Discovery" suffix and convert to plural lowercase
        $label = str_replace('Discovery', '', $shortName);

        // Convert camelCase to space-separated words
        $label = (string) preg_replace('/([a-z])([A-Z])/', '$1 $2', $label);

        // Convert to lowercase and make plural-friendly
        return match (strtolower($label)) {
            'hook' => 'hooks',
            'post type' => 'post types',
            'taxonomy' => 'taxonomies',
            'acf block' => 'ACF blocks',
            'block' => 'blocks',
            'block pattern' => 'block patterns',
            'view composer' => 'view composers',
            'template controller' => 'template controllers',
            'rest route' => 'REST routes',
            'shortcode' => 'shortcodes',
            'cli command' => 'CLI commands',
            'timber model' => 'Timber models',
            default => strtolower($label) . 's',
        };
    }
}
