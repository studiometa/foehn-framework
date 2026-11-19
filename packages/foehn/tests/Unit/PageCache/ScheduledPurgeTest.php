<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\PageCacheConfig;
use Studiometa\Foehn\Cron\HeartbeatEvent;
use Studiometa\Foehn\PageCache\CacheKey;
use Studiometa\Foehn\PageCache\Invalidator;
use Studiometa\Foehn\PageCache\ScheduledPurge;
use Studiometa\Foehn\PageCache\ScheduleStore;
use Studiometa\Foehn\PageCache\Store;

beforeEach(function () {
    wp_stub_reset();
    $GLOBALS['wpdb'] = scheduleStoreDatabase();
    $this->root = pageCacheRoot();
    $this->scheduleStore = new ScheduleStore();

    $config = new PageCacheConfig(enabled: true, path: $this->root);
    $this->job = new ScheduledPurge(
        new Invalidator($config, new Store($config), pageCacheJobs()),
        $this->scheduleStore,
    );

    $this->pages = new Store($config);
    $this->pages->put(CacheKey::create('example.com', '/'), '<html>home</html>');
    $this->pages->put(CacheKey::create('example.com', '/offers/'), '<html>offers</html>');
});

afterEach(function () {
    removeTestDirectory($this->root);
});

describe('ScheduledPurge', function () {
    it('runs on the cron heartbeat event, which needs no Action Scheduler', function () {
        $this->job->register();
        $calls = array_values(array_filter(
            array_column(wp_stub_get_calls('add_action'), 'args'),
            static fn(array $call): bool => $call['hook'] === HeartbeatEvent::HOOK,
        ));

        expect($calls)->toHaveCount(1);
        expect($calls[0]['acceptedArgs'])->toBe(0);

        $this->scheduleStore->record('http://example.com/', time() - 10);
        call_user_func($calls[0]['callback']);

        expect($this->pages->has(CacheKey::create('example.com', '/')))->toBeFalse();
    });

    it('purges each page whose date has passed, and only those', function () {
        $this->scheduleStore->record('http://example.com/', time() - 10);
        $this->scheduleStore->record('http://example.com/offers/', time() + 3600);

        expect(($this->job)())->toBe(1);
        expect($this->pages->has(CacheKey::create('example.com', '/')))->toBeFalse();
        expect($this->pages->has(CacheKey::create('example.com', '/offers/')))->toBeTrue();
        expect($this->scheduleStore->all())
            ->toHaveKey('http://example.com/offers/')
            ->not->toHaveKey('http://example.com/');
    });

    it('does nothing when no date has passed', function () {
        $this->scheduleStore->record('http://example.com/', time() + 3600);

        expect(($this->job)())->toBe(0);
        expect($this->pages->has(CacheKey::create('example.com', '/')))->toBeTrue();
    });
});
