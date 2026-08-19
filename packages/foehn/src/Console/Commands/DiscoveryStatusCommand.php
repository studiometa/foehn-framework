<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Psr\Cache\CacheItemPoolInterface;
use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\Discovery\DiscoveryLocations;

#[AsCliCommand(name: 'discovery:status', description: 'Show discovery cache status', longDescription: <<<'DOC'
    ## DESCRIPTION

    Displays the current status of the discovery cache, including:
    - Whether caching is enabled
    - The cache strategy in use
    - Cache file location
    - Whether cache is valid

    ## EXAMPLES

        # Show discovery cache status
        wp foehn discovery:status
    DOC)]
final class DiscoveryStatusCommand implements CliCommandInterface
{
    public function __construct(
        private readonly WpCli $cli,
        private readonly CacheItemPoolInterface $pool,
        private readonly DiscoveryLocations $locations,
        private readonly FoehnConfig $config,
    ) {}

    /**
     * @param array<int, string> $args
     * @param array<string, string> $assocArgs
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $this->cli->line('Discovery Cache Status');
        $this->cli->line('======================');
        $this->cli->line('');

        // Strategy
        $strategy = $this->config->discoveryCacheStrategy;
        $this->cli->log("Strategy: {$strategy->value}");

        // Enabled
        $enabled = $this->config->isDiscoveryCacheEnabled();
        $enabledText = $enabled ? 'Yes' : 'No';
        $this->cli->log("Enabled: {$enabledText}");

        // Cache path
        $cachePath = $this->config->getDiscoveryCachePath();
        $this->cli->log("Cache path: {$cachePath}");

        // The cache is written per discovery location, so a partly warmed cache is
        // a real state: one location can be cached while another is scanned.
        $locations = $this->locations->all();
        $cached = 0;

        foreach ($locations as $location) {
            $isCached = $this->pool->getItem($location->key)->isHit();
            $cached += $isCached ? 1 : 0;

            $this->cli->log(sprintf('  %s %s', $isCached ? '✓' : '·', $location->namespace));
        }

        $this->cli->log(sprintf('Locations cached: %d/%d', $cached, count($locations)));
        $this->cli->line('');

        if (!$enabled) {
            $this->cli->log('Discovery cache is disabled. Discoveries run at runtime.');

            return;
        }

        if ($cached < count($locations)) {
            $this->cli->warning('Discovery cache is enabled but incomplete. Run: wp foehn discovery:warm');

            return;
        }

        $this->cli->success('Discovery cache is active and valid.');
    }
}
