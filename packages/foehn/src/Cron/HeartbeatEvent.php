<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Cron;

/**
 * The WordPress cron event that writes the {@see Heartbeat}.
 *
 * Føhn assumes a real cron: `DISABLE_WP_CRON` is on by default, and the host runs
 * `wp cron event run --due-now` on a schedule — the Docker image's crontab, a Forge
 * scheduled job, a system crontab or a ddev daemon. This event is scheduled every minute,
 * so every pass of that runner finds it due and records the time. A pass that found
 * nothing else to do still runs it, which is what makes the heartbeat a liveness signal
 * rather than an activity one: a quiet, healthy site keeps a fresh heartbeat.
 *
 * The writer lives in PHP, not in the runner, so every host gets it with nothing to
 * configure but the one `wp cron event run --due-now` line. A runner that stops, or that
 * cannot load WordPress, leaves the previous value alone, and the heartbeat goes stale.
 *
 * Registered by the kernel, whatever the project's config says: a heartbeat that has to
 * be opted into is one that production verification fails on every site that forgot.
 */
final readonly class HeartbeatEvent
{
    /**
     * The hook the event fires.
     */
    public const HOOK = 'foehn_cron_heartbeat';

    /**
     * The recurrence the event is scheduled with, added through `cron_schedules`.
     *
     * Core's shortest recurrence is hourly, and a heartbeat that only moved every hour
     * would be stale against the 15-minute cadence the Docker image runs by default.
     */
    public const SCHEDULE = 'foehn_every_minute';

    public const INTERVAL = 60;

    public function register(): void
    {
        add_filter('cron_schedules', $this->schedules(...));
        add_action('init', $this->schedule(...));
        add_action(self::HOOK, $this->record(...));
    }

    /**
     * @param array<string, array{interval: int, display: string}> $schedules
     * @return array<string, array{interval: int, display: string}>
     */
    public function schedules(array $schedules): array
    {
        $schedules[self::SCHEDULE] = [
            'interval' => self::INTERVAL,
            'display' => 'Every minute (Føhn)',
        ];

        return $schedules;
    }

    /**
     * Schedule the event once. WordPress reschedules a recurring event itself.
     */
    public function schedule(): void
    {
        if (wp_next_scheduled(self::HOOK) !== false) {
            return;
        }

        wp_schedule_event(time(), self::SCHEDULE, self::HOOK);
    }

    /**
     * Write the heartbeat, not autoloaded.
     */
    public function record(): void
    {
        update_option(Heartbeat::OPTION, time(), false);
    }
}
