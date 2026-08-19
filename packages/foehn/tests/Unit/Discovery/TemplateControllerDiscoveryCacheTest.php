<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Discovery\DiscoveryLocation;
use Studiometa\Foehn\Discovery\TemplateControllerDiscovery;
use Tests\Fixtures\TemplateControllerFixture;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    $this->discovery = new TemplateControllerDiscovery();
});

describe('TemplateControllerDiscovery caching', function () {
    it('keeps every item under its location namespace', function () {
        discoverFixture($this->discovery, TemplateControllerFixture::class, $this->location);

        $cacheData = $this->discovery->getCacheableData();

        expect($cacheData)->toHaveKey('App\\')->and($cacheData['App\\'])->toHaveCount(1);
    });

    it('restores every item unchanged through a cache file', function () {
        discoverFixture($this->discovery, TemplateControllerFixture::class, $this->location);

        $restored = restoreThroughCacheFile($this->discovery, new TemplateControllerDiscovery());

        expect($restored->wasRestoredFromCache())
            ->toBeTrue()
            ->and($restored->getItems()->all())
            ->toEqual($this->discovery->getItems()->all());
    });

    it('restores the attribute as an instance, not an array', function () {
        discoverFixture($this->discovery, TemplateControllerFixture::class, $this->location);

        $item = restoreThroughCacheFile($this->discovery, new TemplateControllerDiscovery())->getItems()->all()[0];

        expect($item['attribute'])
            ->toBeInstanceOf(AsTemplateController::class)
            ->and($item['attribute']->getTemplates())
            ->toBe(['single', 'page'])
            ->and($item['attribute']->priority)
            ->toBe(10)
            ->and($item['className'])
            ->toBe(TemplateControllerFixture::class);
    });
});
