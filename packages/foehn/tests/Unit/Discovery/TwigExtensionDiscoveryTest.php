<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\DiscoveryLocation;
use Studiometa\Foehn\Discovery\TwigExtensionDiscovery;
use Tempest\Container\GenericContainer;
use Tests\Fixtures\InvalidTwigExtensionFixture;
use Tests\Fixtures\NoAttributeFixture;
use Tests\Fixtures\TwigExtensionFixture;
use Tests\Fixtures\TwigExtensionWithPriorityFixture;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    $this->discovery = new TwigExtensionDiscovery(new GenericContainer());
});

describe('TwigExtensionDiscovery', function () {
    it('discovers classes with AsTwigExtension attribute', function () {
        $this->discovery->discover($this->location, new ReflectionClass(TwigExtensionFixture::class));

        $items = $this->discovery->getItems()->all();

        expect($items)->toHaveCount(1);
        expect($items[0]['className'])->toBe(TwigExtensionFixture::class);
        expect($items[0]['attribute']->priority)->toBe(10);
    });

    it('discovers custom priority', function () {
        $this->discovery->discover($this->location, new ReflectionClass(TwigExtensionWithPriorityFixture::class));

        $items = $this->discovery->getItems()->all();

        expect($items)->toHaveCount(1);
        expect($items[0]['className'])->toBe(TwigExtensionWithPriorityFixture::class);
        expect($items[0]['attribute']->priority)->toBe(5);
    });

    it('ignores classes without the attribute', function () {
        $this->discovery->discover($this->location, new ReflectionClass(NoAttributeFixture::class));

        expect($this->discovery->getItems()->isEmpty())->toBeTrue();
        expect($this->discovery->hasItems())->toBeFalse();
    });

    it('ignores classes that do not extend AbstractExtension', function () {
        $this->discovery->discover($this->location, new ReflectionClass(InvalidTwigExtensionFixture::class));

        expect($this->discovery->getItems()->isEmpty())->toBeTrue();
        expect($this->discovery->hasItems())->toBeFalse();
    });

    it('reports hasItems correctly', function () {
        expect($this->discovery->hasItems())->toBeFalse();

        $this->discovery->discover($this->location, new ReflectionClass(TwigExtensionFixture::class));

        expect($this->discovery->hasItems())->toBeTrue();
    });

    it('provides cacheable data', function () {
        $this->discovery->discover($this->location, new ReflectionClass(TwigExtensionFixture::class));
        $this->discovery->discover($this->location, new ReflectionClass(TwigExtensionWithPriorityFixture::class));

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData)->toHaveKey('App\\')->and($cacheableData['App\\'])->toHaveCount(2);
        expect($cacheableData['App\\'][0]['className'])->toBe(TwigExtensionFixture::class);
        expect($cacheableData['App\\'][1]['className'])->toBe(TwigExtensionWithPriorityFixture::class);
    });

    it('can restore from cache', function () {
        $this->discovery->discover($this->location, new ReflectionClass(TwigExtensionFixture::class));
        $this->discovery->discover($this->location, new ReflectionClass(TwigExtensionWithPriorityFixture::class));

        $restored = restoreThroughCacheFile($this->discovery, new TwigExtensionDiscovery(new GenericContainer()));

        expect($restored->wasRestoredFromCache())
            ->toBeTrue()
            ->and($restored->getItems()->all())
            ->toEqual($this->discovery->getItems()->all());
    });
});
