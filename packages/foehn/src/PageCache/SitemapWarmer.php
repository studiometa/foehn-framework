<?php

declare(strict_types=1);

namespace Studiometa\Foehn\PageCache;

use Studiometa\Foehn\Attributes\AsJob;
use Studiometa\Foehn\Contracts\JobDispatcher;

/**
 * Queue one {@see WarmUrl} job for each URL the sitemap lists.
 */
#[AsJob(group: 'foehn-page-cache')]
final readonly class SitemapWarmer
{
    public function __construct(
        private Warmer $warmer,
        private JobDispatcher $dispatcher,
    ) {}

    public function __invoke(WarmSitemap $job): void
    {
        foreach ($this->warmer->urls() as $url) {
            $this->dispatcher->dispatch(new WarmUrl($url));
        }
    }
}
