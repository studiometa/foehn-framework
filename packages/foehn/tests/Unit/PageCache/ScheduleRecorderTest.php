<?php

declare(strict_types=1);

use Studiometa\Foehn\Blocks\BlockSchedule;
use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\PageCache\ScheduleRecorder;
use Studiometa\Foehn\PageCache\ScheduleStore;

beforeEach(function () {
    wp_stub_reset();
    $this->schedule = new BlockSchedule(new FoehnConfig());
    $this->store = new ScheduleStore();
    $this->recorder = new ScheduleRecorder($this->schedule, $this->store, pageCacheBypass());
    $this->from = new DateTimeImmutable('+1 day', wp_timezone());

    $this->renderScheduled = function (): void {
        $this->schedule->render('<p>Hi</p>', ['attrs' => ['visibleFrom' => $this->from->format('Y-m-d\TH:i:s')]]);
    };
});

describe('ScheduleRecorder', function () {
    it('records on shutdown, from the request globals', function () {
        $this->recorder->register();
        $calls = array_values(array_filter(
            array_column(wp_stub_get_calls('add_action'), 'args'),
            static fn(array $call): bool => $call['hook'] === 'shutdown',
        ));

        expect($calls)->toHaveCount(1);

        // `shutdown` fires with an empty string. Before this, it reached a typed parameter
        // and every WP-CLI command that saved a post ended in a fatal error.
        expect($calls[0]['acceptedArgs'])->toBe(0);

        $server = $_SERVER;
        $_SERVER = pageCacheServer(['REQUEST_URI' => '/offers/']);
        ($this->renderScheduled)();

        try {
            call_user_func($calls[0]['callback']);
        } finally {
            $_SERVER = $server;
        }

        expect($this->store->all())->toBe(['http://example.com/offers/' => $this->from->getTimestamp()]);
    });

    it('records the URL of a page with a scheduled block, without its query', function () {
        ($this->renderScheduled)();

        $this->recorder->recordFor(pageCacheServer(['REQUEST_URI' => '/offers/?utm_source=x']), []);

        expect($this->store->all())->toBe(['http://example.com/offers/' => $this->from->getTimestamp()]);
    });

    it('records nothing for a page without a scheduled change', function () {
        $this->schedule->render('<p>Hi</p>', ['attrs' => []]);

        $this->recorder->recordFor(pageCacheServer(), []);

        expect($this->store->all())->toBe([]);
    });

    it('records nothing for a request the page cache does not store', function () {
        ($this->renderScheduled)();

        $this->recorder->recordFor(pageCacheServer(['REQUEST_METHOD' => 'POST']), []);

        expect($this->store->all())->toBe([]);
    });
});
