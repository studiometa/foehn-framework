<?php

declare(strict_types=1);

namespace Studiometa\Foehn\PageCache;

use Studiometa\Foehn\Attributes\AsCron;
use Studiometa\Foehn\Config\PageCacheConfig;

/**
 * Deletes the stored pages whose scheduled blocks have shown or hidden since they were stored.
 *
 * Runs every five minutes, from Action Scheduler, which the real cron runs on each pass
 * (`wp cron event run --due-now`). So a scheduled block shows or hides on a cached page
 * at most five minutes, or one real cron step when it is longer, after its date.
 *
 * Framework `#[AsCron]` classes are not location-gated, so this schedules itself on
 * every install. The guard keeps it inert on a site without the page cache.
 */
#[AsCron(300)]
final readonly class ScheduledPurge
{
    public function __construct(
        private PageCacheConfig $config,
        private Invalidator $invalidator,
        private ScheduleStore $store,
    ) {}

    /**
     * Purge each URL whose date has passed. Returns the number of URLs purged.
     */
    public function __invoke(): int
    {
        if (!$this->config->enabled || !$this->config->allowsEnvironment()) {
            return 0;
        }

        $now = time();
        $urls = $this->store->due($now);

        if ($urls === []) {
            return 0;
        }

        // The records go first. A render that ends after this records its next date
        // again, and the page it stores is then purged on a later run if it must be.
        $this->store->forget($urls);

        foreach ($urls as $url) {
            $this->invalidator->forgetUrl($url);
        }

        return count($urls);
    }
}
