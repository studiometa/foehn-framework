<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Cron;

/**
 * When WordPress's scheduler last ran for real, as an operator can read it.
 *
 * A production site keeps `DISABLE_WP_CRON` on and runs `wp cron event run --due-now`
 * from a real cron instead — the Docker image, a Forge scheduled job, a system crontab
 * or a ddev daemon — because a scheduler that only fires when somebody visits the site
 * is one that stops when the traffic does, and a page cache makes that worse, since a
 * cached visit never reaches PHP at all. Each pass runs {@see HeartbeatEvent}, which
 * records the timestamp in one non-autoloaded option, and this reads it.
 *
 * A site with no real cron has no heartbeat to report, so it reads "never". That is the
 * same answer a broken runner gives, and the honest one.
 *
 * A non-numeric value is treated as no heartbeat at all. Something other than a
 * timestamp in that option means whatever wrote it was not the heartbeat event, and a broken
 * heartbeat that reports an age is worse than one that reports nothing: an operator
 * reading "3 minutes ago" from a garbage value stops looking.
 */
final readonly class Heartbeat
{
    /**
     * The option {@see HeartbeatEvent} writes, non-autoloaded.
     *
     * Non-autoloaded because it changes every minute and is read by exactly two
     * callers — this class and production verification — so loading it on every request
     * of every visitor would be a write-heavy row in the autoload blob.
     */
    public const OPTION = 'foehn_cron_last_run';

    /**
     * The recorded timestamp, or null when there is not one to read.
     */
    public function recordedAt(): ?int
    {
        if (!function_exists('get_option')) {
            return null;
        }

        $recorded = get_option(self::OPTION);

        return is_numeric($recorded) ? (int) $recorded : null;
    }

    /**
     * How long ago the last successful run was, in seconds, or null when never.
     *
     * Never negative. A clock that moved backwards, or an option written by a machine
     * whose clock is ahead, would otherwise report a run that has not happened yet.
     */
    public function age(?int $now = null): ?int
    {
        $recorded = $this->recordedAt();

        return $recorded === null ? null : max(0, ($now ?? time()) - $recorded);
    }
}
