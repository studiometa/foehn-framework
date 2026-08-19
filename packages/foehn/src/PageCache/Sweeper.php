<?php

declare(strict_types=1);

namespace Studiometa\Foehn\PageCache;

use Studiometa\Foehn\Attributes\AsCron;
use Studiometa\Foehn\Config\PageCacheConfig;
use Studiometa\Foehn\Jobs\CronInterval;

/**
 * The TTL, enforced by the only thing that can enforce it everywhere.
 *
 * nginx's `try_files` cannot check a file's age, and neither can `mod_rewrite`. On those
 * two read paths a stored page is servable until something deletes it — so with a `ttl`
 * set, this sweep's interval is the real bound on how stale a served page can be. That
 * is why it ships with the fast path rather than after it.
 *
 * Framework `#[AsCron]` classes are not location-gated, so this schedules itself on
 * every install. The guard is what keeps it inert on a site that never enabled the
 * feature: an hourly job that returns immediately costs nothing worth measuring.
 */
#[AsCron(CronInterval::Hourly)]
final readonly class Sweeper
{
    public function __construct(
        private PageCacheConfig $config,
        private Store $store,
    ) {}

    /**
     * Delete what has outlived the TTL. Returns the number of files removed.
     */
    public function __invoke(): int
    {
        if (!$this->config->enabled || $this->config->ttl <= 0) {
            return 0;
        }

        return $this->store->sweep();
    }
}
