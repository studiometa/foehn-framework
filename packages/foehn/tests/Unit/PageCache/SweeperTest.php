<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsCron;
use Studiometa\Foehn\Config\PageCacheConfig;
use Studiometa\Foehn\Jobs\CronInterval;
use Studiometa\Foehn\PageCache\CacheKey;
use Studiometa\Foehn\PageCache\Store;
use Studiometa\Foehn\PageCache\Sweeper;

describe('Sweeper', function () {
    beforeEach(function () {
        wp_stub_reset();
        $this->root = pageCacheRoot();
    });

    afterEach(function () {
        removeTestDirectory($this->root);
    });

    it('runs hourly, which is the bound on how stale nginx can serve', function () {
        $attribute = new ReflectionClass(Sweeper::class)->getAttributes(AsCron::class)[0]->newInstance();

        expect($attribute->intervalSeconds)->toBe(CronInterval::Hourly->value);
    });

    it('deletes what has outlived the TTL', function () {
        $config = new PageCacheConfig(enabled: true, path: $this->root, ttl: 3600);
        $store = new Store($config);
        $store->put(CacheKey::create('example.com', '/stale/'), '<html>stale</html>');
        $store->put(CacheKey::create('example.com', '/fresh/'), '<html>fresh</html>');
        touch($this->root . '/example.com/stale/index.html', time() - 7200);

        expect((new Sweeper($config, $store))())->toBe(1);
        expect($store->has(CacheKey::create('example.com', '/fresh/')))->toBeTrue();
    });

    it('does nothing when the cache keeps pages until something purges them', function () {
        $config = new PageCacheConfig(enabled: true, path: $this->root, ttl: 0);
        $store = new Store($config);
        $store->put(CacheKey::create('example.com', '/'), '<html>home</html>');
        touch($this->root . '/example.com/index.html', time() - (86400 * 30));

        expect((new Sweeper($config, $store))())->toBe(0);
        expect($store->has(CacheKey::create('example.com', '/')))->toBeTrue();
    });

    it('stays inert on a site that never enabled the feature', function () {
        // #[AsCron] classes are not location-gated, so this schedules itself everywhere.
        // The guard inside is what makes that harmless.
        $config = new PageCacheConfig(enabled: false, path: $this->root, ttl: 3600);
        $store = new Store($config);
        $store->put(CacheKey::create('example.com', '/'), '<html>home</html>');
        touch($this->root . '/example.com/index.html', time() - 7200);

        expect((new Sweeper($config, $store))())->toBe(0);
        expect($store->has(CacheKey::create('example.com', '/')))->toBeTrue();
    });
});
