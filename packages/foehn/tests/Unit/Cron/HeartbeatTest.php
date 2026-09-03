<?php

declare(strict_types=1);

use Studiometa\Foehn\Cron\Heartbeat;

beforeEach(function () {
    wp_stub_reset();

    $this->heartbeat = new Heartbeat();
});

describe('Heartbeat', function () {
    it('reads the timestamp the cron runner recorded', function () {
        $GLOBALS['wp_stub_options'][Heartbeat::OPTION] = 1_760_000_000;

        expect($this->heartbeat->recordedAt())->toBe(1_760_000_000);
    });

    it('accepts the numeric string a WP-CLI option write leaves behind', function () {
        // `wp option update` stores what it was given, and what it was given came off a
        // shell — so the value in the row is a string. A reader that insisted on an int
        // would report "never" against a heartbeat that is working.
        $GLOBALS['wp_stub_options'][Heartbeat::OPTION] = '1760000000';

        expect($this->heartbeat->recordedAt())->toBe(1_760_000_000);
    });

    it('has no timestamp when nothing has ever recorded one', function () {
        // The state of every site until the Docker cron runner ships, and of every site
        // whose runner is broken. Both have to read as "never" rather than as a number.
        expect($this->heartbeat->recordedAt())->toBeNull();
        expect($this->heartbeat->age())->toBeNull();
    });

    it('treats a non-numeric value as no heartbeat at all', function () {
        // A broken heartbeat that reports an age is worse than one that reports nothing:
        // an operator reading "3 minutes ago" off a garbage value stops looking.
        foreach (['', 'yesterday', 'null', ' '] as $value) {
            $GLOBALS['wp_stub_options'][Heartbeat::OPTION] = $value;

            expect($this->heartbeat->recordedAt())->toBeNull(var_export($value, true) . ' is not a timestamp');
            expect($this->heartbeat->age())->toBeNull();
        }
    });

    it('reports the age in seconds', function () {
        $GLOBALS['wp_stub_options'][Heartbeat::OPTION] = 1_760_000_000;

        expect($this->heartbeat->age(1_760_000_180))->toBe(180);
    });

    it('never reports a run that has not happened yet', function () {
        // A container whose clock is ahead of the web server's, which is ordinary enough
        // in a scaled deployment. A negative age would print as a future time.
        $GLOBALS['wp_stub_options'][Heartbeat::OPTION] = 1_760_000_500;

        expect($this->heartbeat->age(1_760_000_000))->toBe(0);
    });

    it('names a non-autoloaded option the deployment check also reads', function () {
        expect(Heartbeat::OPTION)->toBe('foehn_cron_last_run');
    });
});
