<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\ContextProviderDiscovery;
use Studiometa\Foehn\Discovery\DiscoveryLocation;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    $this->discovery = new ContextProviderDiscovery();
});

describe('ContextProviderDiscovery caching', function () {
    it('converts items to cacheable format with single template', function () {
        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, $this->location, [
            'templates' => ['single.twig'],
            'className' => 'App\\View\\SingleContextProvider',
            'priority' => 20,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData['App\\'])->toHaveCount(1);
        expect($cacheableData['App\\'][0])->toBe([
            'templates' => ['single.twig'],
            'className' => 'App\\View\\SingleContextProvider',
            'priority' => 20,
        ]);
    });

    it('converts items to cacheable format with multiple templates', function () {
        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, $this->location, [
            'templates' => ['single.twig', 'page.twig', 'archive.twig'],
            'className' => 'App\\View\\CommonContextProvider',
            'priority' => 10,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData['App\\'][0]['templates'])->toBe(['single.twig', 'page.twig', 'archive.twig']);
    });

    it('handles wildcard templates', function () {
        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, $this->location, [
            'templates' => ['components/*.twig'],
            'className' => 'App\\View\\ComponentContextProvider',
            'priority' => 10,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData['App\\'][0]['templates'])->toBe(['components/*.twig']);
    });

    it('uses default priority', function () {
        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, $this->location, [
            'templates' => ['header.twig'],
            'className' => 'App\\View\\HeaderContextProvider',
            'priority' => 10,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData['App\\'][0]['priority'])->toBe(10);
    });

    it('can restore from cache', function () {
        $cachedData = [
            [
                'templates' => ['single.twig', 'page.twig'],
                'className' => 'App\\View\\CommonContextProvider',
                'priority' => 10,
            ],
        ];

        $this->discovery->restoreFromCache(['App\\' => $cachedData]);

        expect($this->discovery->wasRestoredFromCache())->toBeTrue();
    });
});
