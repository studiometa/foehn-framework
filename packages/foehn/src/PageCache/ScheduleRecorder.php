<?php

declare(strict_types=1);

namespace Studiometa\Foehn\PageCache;

use Studiometa\Foehn\Blocks\BlockSchedule;

/**
 * Records the URL of a page that renders a scheduled block, with the date it goes stale.
 *
 * Wired with the page cache, on `shutdown`: the page is rendered by then, and
 * {@see BlockSchedule} knows the earliest date at which one of its blocks shows or hides.
 * A request the page cache would not store records nothing.
 */
final readonly class ScheduleRecorder
{
    public function __construct(
        private BlockSchedule $schedule,
        private ScheduleStore $store,
        private Bypass $bypass,
    ) {}

    public function register(): void
    {
        add_action('shutdown', $this->record(...), 10, 0);
    }

    /**
     * Record the URL of the current request when its page has a scheduled change.
     *
     * Handler for the `shutdown` action, which passes an empty string: nothing is read from it.
     */
    public function record(): void
    {
        $this->recordFor($_SERVER, $_COOKIE);
    }

    /**
     * Record the URL of a request when its page has a scheduled change.
     *
     * @param array<string, mixed> $server
     * @param array<string, mixed> $cookies
     */
    public function recordFor(array $server, array $cookies): void
    {
        $at = $this->schedule->nextChange();

        if ($at === null) {
            return;
        }

        if ($this->bypass->forContext($server, $cookies) !== null) {
            return;
        }

        $key = $this->bypass->key($server);

        if ($key === null) {
            return;
        }

        $path = parse_url((string) ($server['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

        $this->store->record((is_ssl() ? 'https://' : 'http://') . $key->host . (is_string($path) ? $path : '/'), $at);
    }
}
