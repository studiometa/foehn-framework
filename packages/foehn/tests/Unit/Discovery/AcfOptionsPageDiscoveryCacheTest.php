<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsAcfOptionsPage;
use Studiometa\Foehn\Discovery\AcfOptionsPageDiscovery;
use Studiometa\Foehn\Discovery\DiscoveryLocation;
use Tests\Fixtures\AcfOptionsPageFixture;
use Tests\Fixtures\AcfOptionsPageFullFixture;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    $this->discovery = new AcfOptionsPageDiscovery();
});

describe('AcfOptionsPageDiscovery caching', function () {
    it('keeps the item under its location namespace', function () {
        discoverFixture($this->discovery, AcfOptionsPageFixture::class, $this->location);

        $cacheData = $this->discovery->getCacheableData();

        expect($cacheData)->toHaveKey('App\\')->and($cacheData['App\\'])->toHaveCount(1);
    });

    it('restores every item unchanged through a cache file', function () {
        discoverFixture($this->discovery, AcfOptionsPageFixture::class, $this->location);

        $restored = restoreThroughCacheFile($this->discovery, new AcfOptionsPageDiscovery());

        expect($restored->wasRestoredFromCache())
            ->toBeTrue()
            ->and($restored->getItems()->all())
            ->toEqual($this->discovery->getItems()->all());
    });

    it('restores the attribute as an instance, not an array', function () {
        discoverFixture($this->discovery, AcfOptionsPageFixture::class, $this->location);

        $item = restoreThroughCacheFile($this->discovery, new AcfOptionsPageDiscovery())->getItems()->all()[0];

        expect($item['attribute'])
            ->toBeInstanceOf(AsAcfOptionsPage::class)
            ->and($item['attribute']->pageTitle)
            ->toBe('Theme Settings')
            ->and($item['attribute']->menuSlug)
            ->toBe('theme-settings')
            ->and($item['attribute']->position)
            ->toBe(59)
            ->and($item['attribute']->redirect)
            ->toBeFalse()
            ->and($item['hasFields'])
            ->toBeTrue();
    });

    it('reports it was not restored when it scanned', function () {
        discoverFixture($this->discovery, AcfOptionsPageFixture::class, $this->location);

        expect($this->discovery->wasRestoredFromCache())->toBeFalse();
    });

    it('restores every optional field of a fully configured page', function () {
        discoverFixture($this->discovery, AcfOptionsPageFullFixture::class, $this->location);

        $item = restoreThroughCacheFile($this->discovery, new AcfOptionsPageDiscovery())->getItems()->all()[0];

        expect($item['attribute']->postId)
            ->toBe('full_settings')
            ->and($item['attribute']->updateButton)
            ->toBe('Save All Settings')
            ->and($item['attribute']->updatedMessage)
            ->toBe('All settings have been saved.')
            ->and($item['attribute']->autoload)
            ->toBeFalse();
    });
});
