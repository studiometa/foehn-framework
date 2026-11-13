<?php

declare(strict_types=1);

namespace Demo\Jobs;

use Studiometa\Foehn\Attributes\AsCron;

/**
 * At midnight on 1 January, in the site timezone, empty the page cache.
 *
 * The footer prints the year. A page cached on 31 December keeps last year's until
 * its TTL ends, eight hours later. No content changes at midnight, so no purge would
 * remove it.
 *
 * A cron expression, because the time matters. Føhn reads it in the timezone of
 * Settings > General, which SettingsSeeder sets to Europe/Paris: midnight in Paris on
 * 1 January is 23:00 UTC on 31 December. The action is the public one of the page
 * cache, so this class needs no reference to the cache. With `warmAfterPurge` on, the
 * flush queues the warm-up of the sitemap.
 */
#[AsCron('0 0 1 1 *', group: 'demo')]
final class FlushPageCacheAtNewYear
{
    public function __invoke(): void
    {
        do_action('foehn/page_cache/flush');
    }
}
