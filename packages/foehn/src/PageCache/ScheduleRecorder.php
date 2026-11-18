<?php

declare(strict_types=1);

namespace Studiometa\Foehn\PageCache;

use Studiometa\Foehn\Blocks\BlockSchedule;

/**
 * Records the URL of a stored page that renders a scheduled block, with the date it goes stale.
 *
 * Called by {@see Recorder} when it stores a page, and only then: the page is rendered by
 * that time, and {@see BlockSchedule} knows the earliest date at which one of its blocks
 * shows or hides. A response the page cache refuses (a 404 it does not keep, a redirect,
 * an incomplete body) records nothing, so a URL that a scanner probes adds no record.
 */
final readonly class ScheduleRecorder
{
    public function __construct(
        private BlockSchedule $schedule,
        private ScheduleStore $store,
    ) {}

    /**
     * Record the URL of a stored page when the page has a scheduled change.
     */
    public function record(CacheKey $key): void
    {
        $at = $this->schedule->nextChange();

        if ($at === null) {
            return;
        }

        // The key holds the decoded path. Encoded again, so a `?` or a `#` in a segment
        // stays in the path when the job reads the URL back.
        $path = implode('/', array_map(rawurlencode(...), explode('/', $key->path)));

        $this->store->record((is_ssl() ? 'https://' : 'http://') . $key->host . $path, $at);
    }
}
