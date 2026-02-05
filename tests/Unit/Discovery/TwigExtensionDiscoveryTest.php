<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\TwigExtensionDiscovery;
use Tests\Fixtures\InvalidTwigExtensionFixture;
use Tests\Fixtures\NoAttributeFixture;
use Tests\Fixtures\TwigExtensionFixture;
use Tests\Fixtures\TwigExtensionWithPriorityFixture;

beforeEach(function () {
    $this->discovery = new TwigExtensionDiscovery();
});

describe('TwigExtensionDiscovery', function () {
    it('discovers classes with AsTwigExtension attribute', function () {
        $this->discovery->discover(new ReflectionClass(TwigExtensionFixture::class));

        $items = $this->discovery->getItems();

        expect($items)->toHaveCount(1);
        expect($items[0]['className'])->toBe(TwigExtensionFixture::class);
        expect($items[0]['priority'])->toBe(10);
    });

    it('discovers custom priority', function () {
        $this->discovery->discover(new ReflectionClass(TwigExtensionWithPriorityFixture::class));

        $items = $this->discovery->getItems();

        expect($items)->toHaveCount(1);
        expect($items[0]['className'])->toBe(TwigExtensionWithPriorityFixture::class);
        expect($items[0]['priority'])->toBe(5);
    });

    it('ignores classes without the attribute', function () {
        $this->discovery->discover(new ReflectionClass(NoAttributeFixture::class));

        expect($this->discovery->getItems())->toBeEmpty();
        expect($this->discovery->hasItems())->toBeFalse();
    });

    it('ignores classes that do not extend AbstractExtension', function () {
        $this->discovery->discover(new ReflectionClass(InvalidTwigExtensionFixture::class));

        expect($this->discovery->getItems())->toBeEmpty();
        expect($this->discovery->hasItems())->toBeFalse();
    });

    it('reports hasItems correctly', function () {
        expect($this->discovery->hasItems())->toBeFalse();

        $this->discovery->discover(new ReflectionClass(TwigExtensionFixture::class));

        expect($this->discovery->hasItems())->toBeTrue();
    });

    it('provides cacheable data', function () {
        $this->discovery->discover(new ReflectionClass(TwigExtensionFixture::class));
        $this->discovery->discover(new ReflectionClass(TwigExtensionWithPriorityFixture::class));

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData)->toHaveCount(2);
        expect($cacheableData[0])->toBe([
            'className' => TwigExtensionFixture::class,
            'priority' => 10,
        ]);
        expect($cacheableData[1])->toBe([
            'className' => TwigExtensionWithPriorityFixture::class,
            'priority' => 5,
        ]);
    });

    it('can restore from cache', function () {
        $cachedData = [
            ['className' => TwigExtensionFixture::class, 'priority' => 10],
            ['className' => TwigExtensionWithPriorityFixture::class, 'priority' => 5],
        ];

        $this->discovery->restoreFromCache($cachedData);

        expect($this->discovery->wasRestoredFromCache())->toBeTrue();
    });
});
