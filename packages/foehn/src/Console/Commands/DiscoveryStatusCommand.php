<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\Discovery\DiscoveryCache;

#[AsCliCommand(name: 'discovery:status', description: 'Show discovery cache status', longDescription: <<<'DOC'
    ## DESCRIPTION

    Displays the current status of the discovery cache, including:
    - Whether caching is enabled
    - The cache strategy in use
    - Cache file location
    - Whether cache is valid

    ## EXAMPLES

        # Show discovery cache status
        wp tempest discovery:status
    DOC)]
final class DiscoveryStatusCommand implements CliCommandInterface
{
    public function __construct(
        private readonly WpCli $cli,
        private readonly DiscoveryCache $discoveryCache,
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

        // Cache exists
        $exists = $this->discoveryCache->exists();
        $existsText = $exists ? 'Yes' : 'No';
        $this->cli->log("Cache exists: {$existsText}");

        // Cache valid
        $valid = $this->discoveryCache->isValid();
        $validText = $valid ? 'Yes' : 'No';
        $this->cli->log("Cache valid: {$validText}");

        $this->cli->line('');

        $message = match (true) {
            $enabled && $exists && $valid => null,
            $enabled && !$exists => 'Discovery cache is enabled but not generated. Run: wp tempest discovery:generate',
            $enabled && !$valid => 'Discovery cache is enabled but invalid. Run: wp tempest discovery:generate',
            default => null,
        };

        if ($enabled && $exists && $valid) {
            $this->cli->success('Discovery cache is active and valid.');

            return;
        }

        if ($message !== null) {
            $this->cli->warning($message);

            return;
        }

        $this->cli->log('Discovery cache is disabled. Discoveries run at runtime.');
    }
}
