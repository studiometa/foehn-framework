<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Jobs;

use ActionScheduler_CronSchedule;
use CronExpression;
use DateTime;

// Action Scheduler is optional, and discovery loads every class of the framework:
// without its parent class, this file declares nothing rather than throw.
if (!class_exists(ActionScheduler_CronSchedule::class)) {
    return;
}

/**
 * An Action Scheduler cron schedule that reads its expression in the site timezone.
 *
 * Action Scheduler makes every schedule date in UTC (`as_get_datetime_object()`):
 * the first date from the start timestamp, every later one from "now" when the
 * previous run ends, and the stored date when it reads the action back. Its cron
 * library matches each field against the date it gets, in that date's timezone. So
 * with `as_schedule_cron_action()`, `0 0 * * *` runs at midnight UTC, not at
 * midnight on the site.
 *
 * This class moves the date to `wp_timezone()` before each match. Action Scheduler
 * makes the next instance with `new $schedule(...)`, so every repeat is an instance
 * of this class too.
 */
final class SiteTimezoneCronSchedule extends ActionScheduler_CronSchedule
{
    /**
     * @param DateTime $start The date and time to run at or after
     * @param CronExpression|string $recurrence The cron expression
     * @param DateTime|null $first The date and time of the first instance
     */
    public function __construct(DateTime $start, $recurrence, ?DateTime $first = null)
    {
        parent::__construct(self::inSiteTimezone($start), $recurrence, $first);
    }

    /**
     * The first date after $after that matches the expression in the site timezone.
     *
     * @param DateTime $after
     *
     * @return DateTime
     */
    #[\Override]
    protected function calculate_next(DateTime $after)
    {
        return parent::calculate_next(self::inSiteTimezone($after));
    }

    /**
     * The properties to serialize, without the two that only Action Scheduler 2 reads.
     *
     * The parent lists `start_timestamp` and `cron`, which are private to it. PHP
     * cannot find a parent's private property by its plain name in a subclass, so
     * each serialize would raise a warning and store neither.
     *
     * @return list<string>
     */
    #[\Override]
    public function __sleep()
    {
        return array_values(array_diff(parent::__sleep(), ['start_timestamp', 'cron']));
    }

    private static function inSiteTimezone(DateTime $date): DateTime
    {
        $local = clone $date;
        $local->setTimezone(wp_timezone());

        return $local;
    }
}
