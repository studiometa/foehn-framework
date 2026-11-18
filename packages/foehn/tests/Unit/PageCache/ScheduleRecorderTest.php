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
    it('records on shutdown', function () {
        $this->recorder->register();

        expect(array_column(array_column(wp_stub_get_calls('add_action'), 'args'), 'hook'))->toContain('shutdown');
    });

    it('records the URL of a page with a scheduled block, without its query', function () {
        ($this->renderScheduled)();

        $this->recorder->record(pageCacheServer(['REQUEST_URI' => '/offers/?utm_source=x']), []);

        expect($this->store->all())->toBe(['http://example.com/offers/' => $this->from->getTimestamp()]);
    });

    it('records nothing for a page without a scheduled change', function () {
        $this->schedule->render('<p>Hi</p>', ['attrs' => []]);

        $this->recorder->record(pageCacheServer(), []);

        expect($this->store->all())->toBe([]);
    });

    it('records nothing for a request the page cache does not store', function () {
        ($this->renderScheduled)();

        $this->recorder->record(pageCacheServer(['REQUEST_METHOD' => 'POST']), []);

        expect($this->store->all())->toBe([]);
    });
});
