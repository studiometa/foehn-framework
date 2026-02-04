<?php

declare(strict_types=1);

use Studiometa\WPTempest\Attributes\AsTemplateController;
use Studiometa\WPTempest\Discovery\TemplateControllerDiscovery;
use Tempest\Discovery\DiscoveryItems;
use Tempest\Discovery\DiscoveryLocation;

beforeEach(function () {
    $this->discovery = new TemplateControllerDiscovery();
    $this->discovery->setItems(new DiscoveryItems());
    $this->location = new DiscoveryLocation(
        namespace: 'App\\Test',
        path: __DIR__,
    );
});

describe('TemplateControllerDiscovery caching', function () {
    it('converts items to cacheable format with single template', function () {
        $attribute = new AsTemplateController(
            templates: 'single',
            priority: 5,
        );

        $this->discovery->getItems()->add($this->location, [
            'attribute' => $attribute,
            'className' => 'App\\Controllers\\SingleController',
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
        $attribute = new AsTemplateController(
            templates: ['single', 'page', 'singular'],
        );

        $this->discovery->getItems()->add($this->location, [
            'attribute' => $attribute,
            'className' => 'App\\Controllers\\ContentController',
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData[0]['templates'])->toBe(['single', 'page', 'singular']);
    });

    it('handles wildcard templates', function () {
        $attribute = new AsTemplateController(
            templates: 'single-*',
        );

        $this->discovery->getItems()->add($this->location, [
            'attribute' => $attribute,
            'className' => 'App\\Controllers\\SinglePostTypeController',
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData[0]['templates'])->toBe(['single-*']);
    });

    it('handles archive templates', function () {
        $attribute = new AsTemplateController(
            templates: ['archive', 'archive-product', 'category'],
        );

        $this->discovery->getItems()->add($this->location, [
            'attribute' => $attribute,
            'className' => 'App\\Controllers\\ArchiveController',
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData[0]['templates'])->toContain('archive');
        expect($cacheableData[0]['templates'])->toContain('archive-product');
        expect($cacheableData[0]['templates'])->toContain('category');
    });

    it('uses default priority', function () {
        $attribute = new AsTemplateController(
            templates: 'home',
        );

        $this->discovery->getItems()->add($this->location, [
            'attribute' => $attribute,
            'className' => 'App\\Controllers\\HomeController',
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
