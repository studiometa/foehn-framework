<?php

declare(strict_types=1);

namespace Studiometa\Foehn\PageCache;

use Studiometa\Foehn\Cron\HeartbeatEvent;

/**
 * Deletes the stored pages whose scheduled blocks have shown or hidden since they were stored.
 *
 * Runs on the cron heartbeat event ({@see HeartbeatEvent}), which the real cron runs on
 * each pass (`wp cron event run --due-now`). It is a WordPress cron event, so it needs no
 * Action Scheduler. A scheduled block thus shows or hides on a cached page at most one
 * real cron step after its date.
 *
 * Wired by the kernel with the page cache, so it runs only on a site that stores pages.
 */
final readonly class ScheduledPurge
{
    public function __construct(
        private Invalidator $invalidator,
        private ScheduleStore $store,
    ) {}

    public function register(): void
    {
        add_action(HeartbeatEvent::HOOK, $this->__invoke(...), 10, 0);
    }

    /**
     * Purge each URL whose date has passed. Returns the number of URLs purged.
     */
    public function __invoke(): int
    {
        $urls = $this->store->due();

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
