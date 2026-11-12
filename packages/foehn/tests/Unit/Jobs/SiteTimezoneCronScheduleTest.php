<?php

declare(strict_types=1);

use Studiometa\Foehn\Jobs\SiteTimezoneCronSchedule;

beforeEach(function () {
    wp_stub_reset();
    loadActionSchedulerSchedules();
    $GLOBALS['wp_stub_timezone'] = 'Europe/Paris';
});

/**
 * A UTC date, as Action Scheduler makes them.
 */
function asUtcDate(string $date): DateTime
{
    return as_get_datetime_object(new DateTimeImmutable($date . ' UTC')->getTimestamp());
}

function asUtcMinute(DateTime $date): string
{
    return new DateTimeImmutable('@' . $date->getTimestamp())->format('Y-m-d H:i');
}

describe('SiteTimezoneCronSchedule', function () {
    it('runs a plain Action Scheduler cron schedule in UTC', function () {
        // The reason for SiteTimezoneCronSchedule: what as_schedule_cron_action() gives.
        $schedule = new ActionScheduler_CronSchedule(asUtcDate('2026-01-15 12:00'), '0 0 * * *');

        expect(asUtcMinute($schedule->get_date()))->toBe('2026-01-16 00:00');
    });

    it('places the first run at midnight in the site timezone', function () {
        $schedule = new SiteTimezoneCronSchedule(asUtcDate('2026-01-15 12:00'), '0 0 * * *');

        // Midnight in Paris is 23:00 UTC in winter.
        expect(asUtcMinute($schedule->get_date()))->toBe('2026-01-15 23:00');
    });

    it('follows daylight saving time', function () {
        $schedule = new SiteTimezoneCronSchedule(asUtcDate('2026-07-15 12:00'), '0 0 * * *');

        // Midnight in Paris is 22:00 UTC in summer.
        expect(asUtcMinute($schedule->get_date()))->toBe('2026-07-15 22:00');
    });

    it('computes the next run in the site timezone after a UTC date', function () {
        $schedule = new SiteTimezoneCronSchedule(asUtcDate('2026-03-28 12:00'), '0 0 * * *');

        expect(asUtcMinute($schedule->get_date()))->toBe('2026-03-28 23:00');

        // Action Scheduler asks for the next run with "now" in UTC, once the run ends.
        // Summer time starts on 29 March 2026, so the next midnight is an hour earlier.
        $next = $schedule->get_next(asUtcDate('2026-03-28 23:01'));

        expect(asUtcMinute($next))->toBe('2026-03-29 22:00');
    });

    it('keeps the site timezone once Action Scheduler stores and reads it back', function () {
        $schedule = new SiteTimezoneCronSchedule(asUtcDate('2026-01-15 12:00'), '30 6 * * 1-5');

        // The database store keeps the serialized schedule, and reads it back through
        // this deserializer, which rejects a class that is not a schedule. Dates come
        // back in UTC.
        $restored = ActionScheduler_ScheduleDeserializer::unserialize(serialize($schedule));

        expect($restored)->toBeInstanceOf(SiteTimezoneCronSchedule::class);
        expect($restored->get_recurrence())->toBe('30 6 * * 1-5');

        // The start is Thursday 15 January at 13:00 in Paris, so the first run is on
        // Friday at 06:30 in Paris, 05:30 UTC, and the next one on Monday.
        expect(asUtcMinute($restored->get_date()))->toBe('2026-01-16 05:30');
        expect(asUtcMinute($restored->get_next(asUtcDate('2026-01-16 05:31'))))->toBe('2026-01-19 05:30');
    });

    it('makes every repeat an instance of itself', function () {
        $schedule = new SiteTimezoneCronSchedule(asUtcDate('2026-01-15 12:00'), '0 0 * * *');

        // What ActionScheduler_ActionFactory::repeat() does.
        $next = $schedule->get_next(asUtcDate('2026-01-15 23:01'));
        $repeat = new $schedule($next, $schedule->get_recurrence(), $schedule->get_first_date());

        expect($repeat)->toBeInstanceOf(SiteTimezoneCronSchedule::class);
        expect(asUtcMinute($repeat->get_date()))->toBe('2026-01-16 23:00');
    });
});
