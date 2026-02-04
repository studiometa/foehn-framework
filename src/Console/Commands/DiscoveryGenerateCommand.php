<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Console\Commands;

use Studiometa\WPTempest\Attributes\AsCliCommand;
use Studiometa\WPTempest\Console\CliCommandInterface;
use Studiometa\WPTempest\Console\WpCli;
use Tempest\Core\DiscoveryCache;

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

    [--clear]
    : Clear existing cache before generating

    ## EXAMPLES

        # Generate discovery cache
        wp tempest discovery:generate

        # Clear and regenerate
        wp tempest discovery:generate --clear
    DOC)]
final class DiscoveryGenerateCommand implements CliCommandInterface
{
    public function __construct(
        private readonly WpCli $cli,
        private readonly DiscoveryCache $discoveryCache,
    ) {}

    public function __invoke(array $args, array $assocArgs): void
    {
        if (isset($assocArgs['clear'])) {
            $this->cli->log('Clearing existing cache...');
            $this->discoveryCache->clear();
        }

        $this->cli->log('Generating discovery cache...');

        // The discovery will be run and cached on next WordPress load
        // For now, we just clear and let it rebuild
        $this->discoveryCache->clear();

        $this->cli->success('Discovery cache cleared. It will be regenerated on the next request.');
        $this->cli->line('');
        $this->cli->log('Tip: Visit your WordPress site to trigger cache generation.');
    }
}
