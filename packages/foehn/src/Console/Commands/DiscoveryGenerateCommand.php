<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Psr\Cache\CacheItemPoolInterface;
use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\Discovery\DiscoveryRunner;
use Tempest\Discovery\DiscoveryCache;
use Tempest\Discovery\DiscoveryCacheStrategy;

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
        wp foehn discovery:generate

        # Generate with full caching strategy
        wp foehn discovery:generate --strategy=full

        # Clear and regenerate
        wp foehn discovery:generate --clear
    DOC)]
final class DiscoveryGenerateCommand implements CliCommandInterface
{
    public function __construct(
        private readonly WpCli $cli,
        private readonly DiscoveryCache $discoveryCache,
        private readonly CacheItemPoolInterface $pool,
        private readonly DiscoveryRunner $runner,
        private readonly FoehnConfig $config,
    ) {}

    /**
     * @param array<int, string> $args
     * @param array<string, string> $assocArgs
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $strategy = $this->determineStrategy($assocArgs);

        if ($strategy === DiscoveryCacheStrategy::NONE) {
            $this->cli->warning('Discovery cache is disabled. Set discovery_cache config or use --strategy option.');

            return;
        }

        if (($assocArgs['clear'] ?? null) !== null) {
            $this->cli->log('Clearing existing cache...');
            $this->pool->clear();
        }

        $this->cli->log("Generating discovery cache using '{$strategy->value}' strategy...");

        $counts = $this->runner->warmCache($this->discoveryCache->withStrategy($strategy));

        $this->cli->success(sprintf('Discovery cache generated successfully (%d discoveries cached).', count($counts)));
        $this->cli->line('');
        $this->cli->log('Cached discoveries:');

        foreach ($counts as $discoveryClass => $itemCount) {
            $shortName = $this->getShortClassName($discoveryClass);
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
        if (($assocArgs['strategy'] ?? null) !== null) {
            return DiscoveryCacheStrategy::resolveFromInput($assocArgs['strategy']);
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
     * Get short class name from FQCN.
     */
    private function getShortClassName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts);
    }
}
