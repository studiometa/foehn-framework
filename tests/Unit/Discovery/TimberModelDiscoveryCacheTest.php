<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsTimberModel;
use Studiometa\Foehn\Discovery\TimberModelDiscovery;

beforeEach(function () {
    $this->discovery = new TimberModelDiscovery();
});

describe('TimberModelDiscovery caching', function () {
    it('converts post items to cacheable format', function () {
        $attribute = new AsTimberModel(name: 'post');

        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, [
            'attribute' => $attribute,
            'className' => 'App\\Models\\CustomPost',
            'type' => 'post',
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData)->toHaveCount(1);
        expect($cacheableData[0]['name'])->toBe('post');
        expect($cacheableData[0]['className'])->toBe('App\\Models\\CustomPost');
        expect($cacheableData[0]['type'])->toBe('post');
    });

    it('converts term items to cacheable format', function () {
        $attribute = new AsTimberModel(name: 'category');

        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, [
            'attribute' => $attribute,
            'className' => 'App\\Models\\CustomTerm',
            'type' => 'term',
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData)->toHaveCount(1);
        expect($cacheableData[0]['name'])->toBe('category');
        expect($cacheableData[0]['className'])->toBe('App\\Models\\CustomTerm');
        expect($cacheableData[0]['type'])->toBe('term');
    });

    it('can restore from cache', function () {
        $cachedData = [
            [
                'name' => 'post',
                'className' => 'App\\Models\\CustomPost',
                'type' => 'post',
            ],
        ];

        $this->discovery->restoreFromCache($cachedData);

        expect($this->discovery->wasRestoredFromCache())->toBeTrue();
    });

    it('handles multiple models', function () {
        $ref = new ReflectionMethod($this->discovery, 'addItem');

        $ref->invoke($this->discovery, [
            'attribute' => new AsTimberModel('post'),
            'className' => 'App\\Models\\CustomPost',
            'type' => 'post',
        ]);

        $ref->invoke($this->discovery, [
            'attribute' => new AsTimberModel('category'),
            'className' => 'App\\Models\\CustomTerm',
            'type' => 'term',
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData)->toHaveCount(2);
        expect($cacheableData[0]['name'])->toBe('post');
        expect($cacheableData[0]['type'])->toBe('post');
        expect($cacheableData[1]['name'])->toBe('category');
        expect($cacheableData[1]['type'])->toBe('term');
    });
});
