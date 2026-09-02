<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Config\PageCacheConfig;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\PageCache\Invalidator;

#[AsCliCommand(name: 'cache:clear', description: 'Clear the static page cache', longDescription: <<<'DOC'
    ## OPTIONS

    [--url=<url>]
    : Clear one URL instead of the whole cache. Its `page/**` subtree goes with it.

    ## DESCRIPTION

    Deletes the stored HTML. Nothing else has to happen: the next request for a page
    renders it and stores it again.

    Part of the documented deploy sequence, after `composer install` has replaced the
    code the stored pages were rendered by:

        composer install && wp foehn cache:config --write && wp foehn cache:clear

    ## EXAMPLES

        # Empty the page cache
        wp foehn cache:clear

        # Drop one page
        wp foehn cache:clear --url=https://example.com/blog/
    DOC)]
final class PageCacheClearCommand implements CliCommandInterface
{
    public function __construct(
        private readonly WpCli $cli,
        private readonly PageCacheConfig $config,
        private readonly Invalidator $invalidator,
    ) {}

    /**
     * @param array<int, string> $args
     * @param array<string, string> $assocArgs
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        if (!$this->config->enabled) {
            // Clearing still runs: files left by a release that had it on are exactly
            // what a project turning it off wants gone.
            $this->cli->warning('The page cache is disabled — clearing what an earlier release left behind.');
        }

        $url = $assocArgs['url'] ?? null;

        if ($url !== null) {
            $this->clearUrl($url);

            return;
        }

        $removed = $this->invalidator->flush();

        $this->cli->success(sprintf('Page cache cleared (%d page%s).', $removed, $removed === 1 ? '' : 's'));
    }

    private function clearUrl(string $url): void
    {
        // Null is a URL that cannot become a cache key at all — no host, or a path this
        // cache would refuse to write. Reported as the argument error it is, rather than
        // as a successful clear of nothing.
        $removed = $this->invalidator->forgetUrl($url, paginated: true);

        if ($removed === null) {
            $this->cli->error("That URL cannot be a cache key: {$url}");

            return;
        }

        $this->cli->success(sprintf('Cleared %s (%d page%s).', $url, $removed, $removed === 1 ? '' : 's'));
    }
}
