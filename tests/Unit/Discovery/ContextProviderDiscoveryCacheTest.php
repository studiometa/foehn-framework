<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\ContextProviderDiscovery;

beforeEach(function () {
    $this->discovery = new ContextProviderDiscovery();
});

describe('ContextProviderDiscovery caching', function () {
    it('converts items to cacheable format with single template', function () {
        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, [
            'templates' => ['single.twig'],
            'className' => 'App\\View\\SingleContextProvider',
            'priority' => 20,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData)->toHaveCount(1);
        expect($cacheableData[0])->toBe([
            'templates' => ['single.twig'],
            'className' => 'App\\View\\SingleContextProvider',
            'priority' => 20,
        ]);
    });

    it('converts items to cacheable format with multiple templates', function () {
        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, [
            'templates' => ['single.twig', 'page.twig', 'archive.twig'],
            'className' => 'App\\View\\CommonContextProvider',
            'priority' => 10,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData[0]['templates'])->toBe(['single.twig', 'page.twig', 'archive.twig']);
    });

    it('handles wildcard templates', function () {
        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, [
            'templates' => ['components/*.twig'],
            'className' => 'App\\View\\ComponentContextProvider',
            'priority' => 10,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData[0]['templates'])->toBe(['components/*.twig']);
    });

    it('uses default priority', function () {
        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, [
            'templates' => ['header.twig'],
            'className' => 'App\\View\\HeaderContextProvider',
            'priority' => 10,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData[0]['priority'])->toBe(10);
    });

    it('can restore from cache', function () {
        $cachedData = [
            [
                'templates' => ['single.twig', 'page.twig'],
                'className' => 'App\\View\\CommonContextProvider',
                'priority' => 10,
            ],
        ];

        $this->discovery->restoreFromCache($cachedData);

        expect($this->discovery->wasRestoredFromCache())->toBeTrue();
    });
});
