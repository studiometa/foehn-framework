<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsTimberModel;
use Studiometa\Foehn\Discovery\TimberModelDiscovery;
use Studiometa\Foehn\Discovery\DiscoveryLocation;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    $this->discovery = new TimberModelDiscovery();
});

describe('TimberModelDiscovery caching', function () {
    it('converts post items to cacheable format', function () {
        $attribute = new AsTimberModel(name: 'post');

        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, $this->location, [
            'attribute' => $attribute,
            'className' => 'App\\Models\\CustomPost',
            'type' => 'post',
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData['App\\'])->toHaveCount(1);
        expect($cacheableData['App\\'][0]['name'])->toBe('post');
        expect($cacheableData['App\\'][0]['className'])->toBe('App\\Models\\CustomPost');
        expect($cacheableData['App\\'][0]['type'])->toBe('post');
    });

    it('converts term items to cacheable format', function () {
        $attribute = new AsTimberModel(name: 'category');

        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, $this->location, [
            'attribute' => $attribute,
            'className' => 'App\\Models\\CustomTerm',
            'type' => 'term',
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData['App\\'])->toHaveCount(1);
        expect($cacheableData['App\\'][0]['name'])->toBe('category');
        expect($cacheableData['App\\'][0]['className'])->toBe('App\\Models\\CustomTerm');
        expect($cacheableData['App\\'][0]['type'])->toBe('term');
    });

    it('can restore from cache', function () {
        $cachedData = [
            [
                'name' => 'post',
                'className' => 'App\\Models\\CustomPost',
                'type' => 'post',
            ],
        ];

        $this->discovery->restoreFromCache(['App\\' => $cachedData]);

        expect($this->discovery->wasRestoredFromCache())->toBeTrue();
    });

    it('handles multiple models', function () {
        $ref = new ReflectionMethod($this->discovery, 'addItem');

        $ref->invoke($this->discovery, $this->location, [
            'attribute' => new AsTimberModel('post'),
            'className' => 'App\\Models\\CustomPost',
            'type' => 'post',
        ]);

        $ref->invoke($this->discovery, $this->location, [
            'attribute' => new AsTimberModel('category'),
            'className' => 'App\\Models\\CustomTerm',
            'type' => 'term',
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData['App\\'])->toHaveCount(2);
        expect($cacheableData['App\\'][0]['name'])->toBe('post');
        expect($cacheableData['App\\'][0]['type'])->toBe('post');
        expect($cacheableData['App\\'][1]['name'])->toBe('category');
        expect($cacheableData['App\\'][1]['type'])->toBe('term');
    });
});
