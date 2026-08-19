<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Config\PageCacheConfig;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\Contracts\JobDispatcher;
use Studiometa\Foehn\PageCache\Warmer;
use Studiometa\Foehn\PageCache\WarmUrl;

#[AsCliCommand(name: 'cache:warm', description: 'Fill the page cache from the sitemap', longDescription: <<<'DOC'
    ## OPTIONS

    [--sync]
    : Request every URL now instead of queueing them. Slower, and the only option on a
    site with no Action Scheduler.

    [--limit=<number>]
    : Stop after this many URLs.

    ## DESCRIPTION

    Walks WordPress's own sitemap and requests each URL cookie-free with an
    `X-Foehn-Warm: 1` header, so the first visitor after a deploy is not the one who pays
    for the render.

    The sitemap is the list the site already publishes for crawlers, which makes it the
    right list to warm: there is no second notion of "important pages" to keep in step
    with the one search engines are given.

    Optional. A cache fills itself from traffic — this is for the deploy where you would
    rather pay the cost yourself.

    ## EXAMPLES

        # Queue every sitemap URL through Action Scheduler
        wp foehn cache:warm

        # Request them now
        wp foehn cache:warm --sync

        # Warm the first fifty
        wp foehn cache:warm --sync --limit=50
    DOC)]
final class PageCacheWarmCommand implements CliCommandInterface
{
    public function __construct(
        private readonly WpCli $cli,
        private readonly PageCacheConfig $config,
        private readonly Warmer $warmer,
        private readonly JobDispatcher $dispatcher,
    ) {}

    /**
     * @param array<int, string> $args
     * @param array<string, string> $assocArgs
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        if (!$this->config->enabled) {
            $this->cli->error('The page cache is disabled, so there is nothing to warm.');

            return;
        }

        if (!$this->config->allowsEnvironment()) {
            $this->cli->error(sprintf(
                "The page cache is inert in the '%s' environment, so there is nothing to warm.",
                PageCacheConfig::environment(),
            ));

            return;
        }

        $urls = $this->warmer->urls();
        $limit = (int) ($assocArgs['limit'] ?? 0);

        if ($limit > 0) {
            $urls = array_slice($urls, 0, $limit);
        }

        if ($urls === []) {
            $this->cli->warning('The sitemap listed no URLs. Are sitemaps disabled?');

            return;
        }

        // Queued by default, because a site with two thousand pages should not try to
        // render two thousand pages inside one WP-CLI process.
        ($assocArgs['sync'] ?? null) !== null || !$this->dispatcher->isAvailable()
            ? $this->warmNow($urls)
            : $this->queue($urls);
    }

    /**
     * @param list<string> $urls
     */
    private function warmNow(array $urls): void
    {
        $warmed = 0;
        $failed = [];

        foreach ($urls as $url) {
            $status = $this->warmer->warm($url);

            if ($status === 200) {
                $warmed++;

                continue;
            }

            $failed[] = sprintf('%s (%s)', $url, $status === null ? 'no response' : (string) $status);
        }

        foreach ($failed as $failure) {
            $this->cli->warning('Did not warm ' . $failure);
        }

        $this->cli->success(sprintf('Warmed %d of %d URLs.', $warmed, count($urls)));
    }

    /**
     * @param list<string> $urls
     */
    private function queue(array $urls): void
    {
        foreach ($urls as $url) {
            $this->dispatcher->dispatch(new WarmUrl($url));
        }

        $this->cli->success(sprintf('Queued %d URLs. Action Scheduler will work through them.', count($urls)));
    }
}
