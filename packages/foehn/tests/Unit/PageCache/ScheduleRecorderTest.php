<?php

declare(strict_types=1);

use Studiometa\Foehn\Blocks\BlockSchedule;
use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Config\PageCacheConfig;
use Studiometa\Foehn\PageCache\Bypass;
use Studiometa\Foehn\PageCache\Recorder;
use Studiometa\Foehn\PageCache\ScheduleStore;

beforeEach(function () {
    wp_stub_reset();
    $GLOBALS['wpdb'] = scheduleStoreDatabase();
    $GLOBALS['wp_stub_environment_type'] = 'production';

    $this->root = pageCacheRoot();
    $this->schedule = new BlockSchedule(new FoehnConfig());
    $this->store = new ScheduleStore();
    $this->from = new DateTimeImmutable('+1 day', wp_timezone());

    $this->recorder = function (array $options = []): Recorder {
        $config = new PageCacheConfig(...[
            'enabled' => true,
            'path' => $this->root,
            'debugHeaders' => false,
            ...$options,
        ]);

        return new Recorder(
            $config,
            pageCacheStore($this->root),
            new Bypass($config),
            pageCacheSchedule($this->schedule),
        );
    };

    $this->renderScheduled = function (): void {
        $this->schedule->render('<p>Hi</p>', ['attrs' => ['visibleFrom' => $this->from->format('Y-m-d\TH:i:s')]]);
    };

    $_SERVER = pageCacheServer(['REQUEST_URI' => '/offers/']);
    $_COOKIE = [];
    $_POST = [];
});

afterEach(function () {
    http_response_code(200);
    removeTestDirectory($this->root);
});

describe('ScheduleRecorder', function () {
    it('records the URL of a stored page with a scheduled block, without its query', function () {
        $_SERVER = pageCacheServer(['REQUEST_URI' => '/offers/?utm_source=x']);
        ($this->renderScheduled)();

        ($this->recorder)()->onFlush(pageCacheBody());

        expect($this->store->all())->toBe(['http://example.com/offers/' => $this->from->getTimestamp()]);
    });

    it('records the URL as it was requested, the canonical one, also without a trailing slash', function () {
        // WordPress redirects any other spelling to the canonical URL, and a redirect is not
        // stored. So the requested URL is the one the purge and a visitor use.
        $_SERVER = pageCacheServer(['REQUEST_URI' => '/offers']);
        ($this->renderScheduled)();

        ($this->recorder)()->onFlush(pageCacheBody());

        expect($this->store->all())->toBe(['http://example.com/offers' => $this->from->getTimestamp()]);
    });

    it('records the home page as the site root', function () {
        $_SERVER = pageCacheServer(['REQUEST_URI' => '/']);
        ($this->renderScheduled)();

        ($this->recorder)()->onFlush(pageCacheBody());

        expect($this->store->all())->toBe(['http://example.com/' => $this->from->getTimestamp()]);
    });

    it('records an encoded URL, which the invalidator decodes to the same page', function () {
        $_SERVER = pageCacheServer(['REQUEST_URI' => '/caf%C3%A9/']);
        ($this->renderScheduled)();

        ($this->recorder)()->onFlush(pageCacheBody());

        expect($this->store->all())->toBe(['http://example.com/caf%C3%A9/' => $this->from->getTimestamp()]);
    });

    it('records nothing for a page without a scheduled change', function () {
        $this->schedule->render('<p>Hi</p>', ['attrs' => []]);

        ($this->recorder)()->onFlush(pageCacheBody());

        expect($this->store->all())->toBe([]);
    });

    it('records nothing for a request the page cache does not store', function () {
        $_SERVER = pageCacheServer(['REQUEST_METHOD' => 'POST']);
        ($this->renderScheduled)();

        ($this->recorder)()->onFlush(pageCacheBody());

        expect($this->store->all())->toBe([]);
    });

    it('records nothing for a 404 the page cache does not keep', function () {
        // A scheduled banner in a site-wide region renders on every 404. Each path a
        // scanner probes would otherwise add a record.
        ($this->renderScheduled)();
        http_response_code(404);

        ($this->recorder)(['cacheNotFound' => false])->onFlush(pageCacheBody());

        expect($this->store->all())->toBe([]);
    });

    it('records nothing for a response the page cache refuses', function () {
        ($this->renderScheduled)();

        ($this->recorder)()->onFlush('<html><body>' . str_repeat('x', 300) . '<b>Fatal error</b>');

        expect($this->store->all())->toBe([]);
    });
});
