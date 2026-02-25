<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\CronDiscovery;
use Studiometa\Foehn\Discovery\DiscoveryLocation;
use Tests\Fixtures\CronCustomHookFixture;
use Tests\Fixtures\CronFixture;
use Tests\Fixtures\InvalidCronFixture;
use Tests\Fixtures\NoAttributeFixture;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    $this->discovery = new CronDiscovery();
});

describe('CronDiscovery', function () {
    it('discovers cron attributes on classes', function () {
        $this->discovery->discover($this->location, new ReflectionClass(CronFixture::class));

        $items = $this->discovery->getItems()->all();

        expect($items)->toHaveCount(1);
        expect($items[0]['className'])->toBe(CronFixture::class);
        expect($items[0]['hook'])->toBe('foehn/tests/fixtures/cron_fixture');
        expect($items[0]['intervalSeconds'])->toBe(86400);
        expect($items[0]['group'])->toBe('foehn');
    });

    it('uses custom hook name when provided', function () {
        $this->discovery->discover($this->location, new ReflectionClass(CronCustomHookFixture::class));

        $items = $this->discovery->getItems()->all();

        expect($items)->toHaveCount(1);
        expect($items[0]['hook'])->toBe('my_plugin/sync_data');
        expect($items[0]['intervalSeconds'])->toBe(3600);
        expect($items[0]['group'])->toBe('my-plugin');
    });

    it('ignores classes without cron attributes', function () {
        $this->discovery->discover($this->location, new ReflectionClass(NoAttributeFixture::class));

        expect($this->discovery->getItems()->isEmpty())->toBeTrue();
    });

    it('ignores classes without __invoke method', function () {
        $this->discovery->discover($this->location, new ReflectionClass(InvalidCronFixture::class));

        expect($this->discovery->getItems()->isEmpty())->toBeTrue();
    });

    it('reports hasItems correctly', function () {
        expect($this->discovery->hasItems())->toBeFalse();

        $this->discovery->discover($this->location, new ReflectionClass(CronFixture::class));

        expect($this->discovery->hasItems())->toBeTrue();
    });

    it('supports caching', function () {
        $this->discovery->discover($this->location, new ReflectionClass(CronFixture::class));

        $cacheData = $this->discovery->getCacheableData();

        expect($cacheData)->not->toBeEmpty();

        // Restore from cache
        $restored = new CronDiscovery();
        $restored->restoreFromCache($cacheData);

        expect($restored->getItems()->all())->toEqual($this->discovery->getItems()->all());
        expect($restored->wasRestoredFromCache())->toBeTrue();
    });
});
