<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Psr\Cache\CacheItemPoolInterface;
use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\WpCli;

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
        wp foehn discovery:clear
    DOC)]
final class DiscoveryClearCommand implements CliCommandInterface
{
    public function __construct(
        private readonly WpCli $cli,
        private readonly CacheItemPoolInterface $pool,
    ) {}

    /**
     * @param array<int, string> $args
     * @param array<string, string> $assocArgs
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $this->cli->log('Clearing discovery cache...');

        // The pool is emptied directly rather than through DiscoveryCache::clear(),
        // which also rewrites a strategy marker next to the Tempest package and
        // fails wherever vendor/ is deployed read-only.
        if (!$this->pool->clear()) {
            $this->cli->warning('Could not clear the discovery cache.');

            return;
        }

        $this->cli->success('Discovery cache cleared.');
    }
}
