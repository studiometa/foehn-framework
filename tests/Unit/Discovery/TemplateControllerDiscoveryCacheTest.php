<?php

declare(strict_types=1);

use Studiometa\WPTempest\Discovery\TemplateControllerDiscovery;

beforeEach(function () {
    $this->discovery = new TemplateControllerDiscovery();
});

describe('TemplateControllerDiscovery caching', function () {
    it('converts items to cacheable format with single template', function () {
        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, [
            'templates' => ['single'],
            'className' => 'App\\Controllers\\SingleController',
            'priority' => 5,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData)->toHaveCount(1);
        expect($cacheableData[0])->toBe([
            'templates' => ['single'],
            'className' => 'App\\Controllers\\SingleController',
            'priority' => 5,
        ]);
    });

    it('converts items to cacheable format with multiple templates', function () {
        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, [
            'templates' => ['single', 'page', 'singular'],
            'className' => 'App\\Controllers\\ContentController',
            'priority' => 10,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData[0]['templates'])->toBe(['single', 'page', 'singular']);
    });

    it('handles wildcard templates', function () {
        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, [
            'templates' => ['single-*'],
            'className' => 'App\\Controllers\\SinglePostTypeController',
            'priority' => 10,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData[0]['templates'])->toBe(['single-*']);
    });

    it('handles archive templates', function () {
        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, [
            'templates' => ['archive', 'archive-product', 'category'],
            'className' => 'App\\Controllers\\ArchiveController',
            'priority' => 10,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData[0]['templates'])->toContain('archive');
        expect($cacheableData[0]['templates'])->toContain('archive-product');
        expect($cacheableData[0]['templates'])->toContain('category');
    });

    it('uses default priority', function () {
        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, [
            'templates' => ['home'],
            'className' => 'App\\Controllers\\HomeController',
            'priority' => 10,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData[0]['priority'])->toBe(10);
    });

    it('can restore from cache', function () {
        $cachedData = [
            [
                'templates' => ['single', 'page'],
                'className' => 'App\\Controllers\\ContentController',
                'priority' => 10,
            ],
        ];

        $this->discovery->restoreFromCache($cachedData);

        expect($this->discovery->wasRestoredFromCache())->toBeTrue();
    });
});
