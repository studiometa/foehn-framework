<?php

declare(strict_types=1);

use Studiometa\WPTempest\Attributes\AsViewComposer;
use Studiometa\WPTempest\Discovery\ViewComposerDiscovery;
use Tempest\Discovery\DiscoveryItems;
use Tempest\Discovery\DiscoveryLocation;

beforeEach(function () {
    $this->discovery = new ViewComposerDiscovery();
    $this->discovery->setItems(new DiscoveryItems());
    $this->location = new DiscoveryLocation(
        namespace: 'App\\Test',
        path: __DIR__,
    );
});

describe('ViewComposerDiscovery caching', function () {
    it('converts items to cacheable format with single template', function () {
        $attribute = new AsViewComposer(
            templates: 'single.twig',
            priority: 20,
        );

        $this->discovery->getItems()->add($this->location, [
            'attribute' => $attribute,
            'className' => 'App\\View\\SingleComposer',
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData)->toHaveCount(1);
        expect($cacheableData[0])->toBe([
            'templates' => ['single.twig'],
            'className' => 'App\\View\\SingleComposer',
            'priority' => 20,
        ]);
    });

    it('converts items to cacheable format with multiple templates', function () {
        $attribute = new AsViewComposer(
            templates: ['single.twig', 'page.twig', 'archive.twig'],
        );

        $this->discovery->getItems()->add($this->location, [
            'attribute' => $attribute,
            'className' => 'App\\View\\CommonComposer',
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData[0]['templates'])->toBe(['single.twig', 'page.twig', 'archive.twig']);
    });

    it('handles wildcard templates', function () {
        $attribute = new AsViewComposer(
            templates: 'components/*.twig',
        );

        $this->discovery->getItems()->add($this->location, [
            'attribute' => $attribute,
            'className' => 'App\\View\\ComponentComposer',
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData[0]['templates'])->toBe(['components/*.twig']);
    });

    it('uses default priority', function () {
        $attribute = new AsViewComposer(
            templates: 'header.twig',
        );

        $this->discovery->getItems()->add($this->location, [
            'attribute' => $attribute,
            'className' => 'App\\View\\HeaderComposer',
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData[0]['priority'])->toBe(10);
    });

    it('can restore from cache', function () {
        $cachedData = [
            [
                'templates' => ['single.twig', 'page.twig'],
                'className' => 'App\\View\\CommonComposer',
                'priority' => 10,
            ],
        ];

        $this->discovery->restoreFromCache($cachedData);

        expect($this->discovery->wasRestoredFromCache())->toBeTrue();
    });
});
