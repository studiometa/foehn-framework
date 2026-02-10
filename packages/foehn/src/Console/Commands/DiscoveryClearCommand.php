<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\Discovery\DiscoveryCache;

#[AsCliCommand(name: 'discovery:clear', description: 'Clear the discovery cache', longDescription: <<<'DOC'
    ## DESCRIPTION

    Clears all cached discovery files. This forces Foehn to re-discover
    all attributes (post types, taxonomies, blocks, etc.) on the next request.

    Use this command after:
    - Adding or removing attribute-decorated classes
    - Changing attribute parameters
    - Deploying new code

    ## EXAMPLES

        # Clear discovery cache
        wp tempest discovery:clear
    DOC)]
final class DiscoveryClearCommand implements CliCommandInterface
{
    public function __construct(
        private readonly WpCli $cli,
        private readonly DiscoveryCache $discoveryCache,
    ) {}

    /**
     * @param array<int, string> $args
     * @param array<string, string> $assocArgs
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $this->cli->log('Clearing discovery cache...');

        $this->discoveryCache->clear();

        $this->cli->success('Discovery cache cleared.');
    }
}
