<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Cron;

/**
 * When WordPress's scheduler last ran for real, as an operator can read it.
 *
 * A production site turns `DISABLE_WP_CRON` on and runs `wp cron event run --due-now`
 * from the container instead, because a scheduler that only fires when somebody visits
 * the site is one that stops when the traffic does — and a page cache makes that worse,
 * since a cached visit never reaches PHP at all. The runner records the timestamp of a
 * successful pass in one non-autoloaded option, and this reads it.
 *
 * **Nothing writes it in this phase.** The writer is the Docker cron runner, which ships
 * separately, so the dashboard says "never" until it does. That is the honest reading of
 * an absent heartbeat rather than a bug: a site with no real cron has no heartbeat to
 * report, and it is the same answer the dashboard must give when the runner is installed
 * and broken.
 *
 * A non-numeric value is treated as no heartbeat at all. Something other than a
 * timestamp in that option means whatever wrote it was not the runner, and a broken
 * heartbeat that reports an age is worse than one that reports nothing: an operator
 * reading "3 minutes ago" from a garbage value stops looking.
 */
final readonly class Heartbeat
{
    /**
     * The option the runner writes, non-autoloaded.
     *
     * Non-autoloaded because it changes every few minutes and is read by exactly two
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
     * Never negative. A clock that moved backwards, or an option written by a container
     * whose clock is ahead, would otherwise report a run that has not happened yet.
     */
    public function age(?int $now = null): ?int
    {
        $recorded = $this->recordedAt();

        return $recorded === null ? null : max(0, ($now ?? time()) - $recorded);
    }
}
