<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\ImageSizeDiscovery;
use Tests\Fixtures\ImageSizeFixture;

beforeEach(function () {
    $this->discovery = new ImageSizeDiscovery();
});

describe('ImageSizeDiscovery caching', function () {
    it('returns cacheable data', function () {
        $this->discovery->discover(new ReflectionClass(ImageSizeFixture::class));

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData)->toHaveCount(1);
        expect($cacheableData[0])->toBe([
            'name' => 'image_size_fixture',
            'width' => 1200,
            'height' => 630,
            'crop' => true,
            'className' => ImageSizeFixture::class,
        ]);
    });

    it('can restore from cache', function () {
        $cachedData = [
            [
                'name' => 'cached_image',
                'width' => 800,
                'height' => 600,
                'crop' => false,
                'className' => 'App\\ImageSizes\\CachedImage',
            ],
        ];

        $this->discovery->restoreFromCache($cachedData);

        expect($this->discovery->wasRestoredFromCache())->toBeTrue();
    });

    it('returns empty cacheable data when no items discovered', function () {
        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData)->toBeEmpty();
    });

    it('preserves all item data through cache cycle', function () {
        $this->discovery->discover(new ReflectionClass(ImageSizeFixture::class));
        $originalData = $this->discovery->getCacheableData();

        // Create new discovery and restore from cache
        $restoredDiscovery = new ImageSizeDiscovery();
        $restoredDiscovery->restoreFromCache($originalData);

        expect($restoredDiscovery->wasRestoredFromCache())->toBeTrue();
    });
});
