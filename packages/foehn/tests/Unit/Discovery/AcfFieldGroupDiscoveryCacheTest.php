<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsAcfFieldGroup;
use Studiometa\Foehn\Discovery\AcfFieldGroupDiscovery;
use Studiometa\Foehn\Discovery\DiscoveryLocation;
use Tests\Fixtures\AcfFieldGroupComplexLocationFixture;
use Tests\Fixtures\AcfFieldGroupFixture;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    $this->discovery = new AcfFieldGroupDiscovery();
});

describe('AcfFieldGroupDiscovery caching', function () {
    it('keeps the item under its location namespace', function () {
        discoverFixture($this->discovery, AcfFieldGroupFixture::class, $this->location);

        $cacheData = $this->discovery->getCacheableData();

        expect($cacheData)->toHaveKey('App\\')->and($cacheData['App\\'])->toHaveCount(1);
    });

    it('restores every item unchanged through a cache file', function () {
        discoverFixture($this->discovery, AcfFieldGroupFixture::class, $this->location);

        $restored = restoreThroughCacheFile($this->discovery, new AcfFieldGroupDiscovery());

        expect($restored->wasRestoredFromCache())
            ->toBeTrue()
            ->and($restored->getItems()->all())
            ->toEqual($this->discovery->getItems()->all());
    });

    it('restores the attribute as an instance, not an array', function () {
        discoverFixture($this->discovery, AcfFieldGroupFixture::class, $this->location);

        $item = restoreThroughCacheFile($this->discovery, new AcfFieldGroupDiscovery())->getItems()->all()[0];

        expect($item['attribute'])
            ->toBeInstanceOf(AsAcfFieldGroup::class)
            ->and($item['attribute']->name)
            ->toBe('property_fields')
            ->and($item['attribute']->location)
            ->toBe(['post_type' => 'property'])
            ->and($item['attribute']->hideOnScreen)
            ->toBe(['the_content', 'excerpt']);
    });

    it('reports it was not restored when it scanned', function () {
        discoverFixture($this->discovery, AcfFieldGroupFixture::class, $this->location);

        expect($this->discovery->wasRestoredFromCache())->toBeFalse();
    });

    it('restores a full ACF location format unchanged', function () {
        discoverFixture($this->discovery, AcfFieldGroupComplexLocationFixture::class, $this->location);

        $restored = restoreThroughCacheFile($this->discovery, new AcfFieldGroupDiscovery());

        expect($restored->getItems()->all())->toEqual($this->discovery->getItems()->all());
    });

    it('keeps one item per discovered class', function () {
        discoverFixture($this->discovery, AcfFieldGroupFixture::class, $this->location);
        discoverFixture($this->discovery, AcfFieldGroupComplexLocationFixture::class, $this->location);

        expect($this->discovery->getCacheableData()['App\\'])->toHaveCount(2);
    });
});
