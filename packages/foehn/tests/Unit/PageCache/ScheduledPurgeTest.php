<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsCron;
use Studiometa\Foehn\Config\PageCacheConfig;
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

    $this->purge = function (bool $enabled = true): int {
        $config = new PageCacheConfig(enabled: $enabled, path: $this->root, environments: ['production']);

        return new ScheduledPurge(
            $config,
            new Invalidator($config, new Store($config), pageCacheJobs()),
            $this->scheduleStore,
        )();
    };

    $this->pages = new Store(new PageCacheConfig(enabled: true, path: $this->root));
    $this->pages->put(CacheKey::create('example.com', '/'), '<html>home</html>');
    $this->pages->put(CacheKey::create('example.com', '/offers/'), '<html>offers</html>');
});

afterEach(function () {
    removeTestDirectory($this->root);
});

describe('ScheduledPurge', function () {
    it('runs from Action Scheduler every five minutes', function () {
        $attribute = new ReflectionClass(ScheduledPurge::class)->getAttributes(AsCron::class)[0]->newInstance();

        expect($attribute->intervalSeconds)->toBe(300);
    });

    it('purges each page whose date has passed, and only those', function () {
        $this->scheduleStore->record('http://example.com/', time() - 10);
        $this->scheduleStore->record('http://example.com/offers/', time() + 3600);

        expect(($this->purge)())->toBe(1);
        expect($this->pages->has(CacheKey::create('example.com', '/')))->toBeFalse();
        expect($this->pages->has(CacheKey::create('example.com', '/offers/')))->toBeTrue();
        expect($this->scheduleStore->all())
            ->toHaveKey('http://example.com/offers/')
            ->not->toHaveKey('http://example.com/');
    });

    it('does nothing when no date has passed', function () {
        $this->scheduleStore->record('http://example.com/', time() + 3600);

        expect(($this->purge)())->toBe(0);
        expect($this->pages->has(CacheKey::create('example.com', '/')))->toBeTrue();
    });

    it('stays inert on a site without the page cache', function () {
        $this->scheduleStore->record('http://example.com/', time() - 10);

        expect(($this->purge)(enabled: false))->toBe(0);
        expect($this->pages->has(CacheKey::create('example.com', '/')))->toBeTrue();
    });
});
