<?php

declare(strict_types=1);

use Studiometa\Foehn\Cron\Heartbeat;
use Studiometa\Foehn\Cron\HeartbeatEvent;

beforeEach(function () {
    wp_stub_reset();

    $this->event = new HeartbeatEvent();
});

describe('HeartbeatEvent', function () {
    it('hooks the schedule, the scheduling and the writer', function () {
        $this->event->register();

        $filters = array_column(array_column(wp_stub_get_calls('add_filter'), 'args'), 'hook');
        $actions = array_column(array_column(wp_stub_get_calls('add_action'), 'args'), 'hook');

        expect($filters)->toBe(['cron_schedules']);
        expect($actions)->toBe(['init', HeartbeatEvent::HOOK]);
    });

    it('adds a one-minute recurrence and keeps the others', function () {
        $this->event->register();

        $schedules = apply_filters('cron_schedules', ['hourly' => ['interval' => 3600, 'display' => 'Hourly']]);

        expect($schedules)->toHaveKey('hourly');
        expect($schedules[HeartbeatEvent::SCHEDULE]['interval'])->toBe(60);
    });

    it('schedules the event every minute when it is not queued', function () {
        $this->event->schedule();

        $calls = wp_stub_get_calls('wp_schedule_event');

        expect($calls)->toHaveCount(1);
        expect($calls[0]['args']['hook'])->toBe('foehn_cron_heartbeat');
        expect($calls[0]['args']['recurrence'])->toBe(HeartbeatEvent::SCHEDULE);
        expect(wp_next_scheduled(HeartbeatEvent::HOOK))->toBeInt();
    });

    it('does not queue it twice', function () {
        // `init` fires on every request. A second event per request would fill the queue.
        $this->event->schedule();
        $this->event->schedule();

        expect(wp_stub_get_calls('wp_schedule_event'))->toHaveCount(1);
    });

    it('writes the current time as the heartbeat, not autoloaded', function () {
        $before = time();

        $this->event->record();

        $calls = wp_stub_get_calls('update_option');

        expect($calls)->toHaveCount(1);
        expect($calls[0]['args']['option'])->toBe(Heartbeat::OPTION);
        expect($calls[0]['args']['autoload'])->toBeFalse();
        expect($calls[0]['args']['value'])->toBeGreaterThanOrEqual($before)->toBeLessThanOrEqual(time());
    });

    it('writes it when WordPress fires the hook', function () {
        $this->event->register();

        $writer = array_values(array_filter(
            wp_stub_get_calls('add_action'),
            fn(array $call) => $call['args']['hook'] === HeartbeatEvent::HOOK,
        ))[0]['args']['callback'];

        $writer();

        expect(new Heartbeat()->recordedAt())->toBeInt();
    });
});
