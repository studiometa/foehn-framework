<?php

declare(strict_types=1);

use Studiometa\WPTempest\Attributes\AsBlock;
use Studiometa\WPTempest\Discovery\BlockDiscovery;
use Tempest\Discovery\DiscoveryItems;
use Tempest\Discovery\DiscoveryLocation;

beforeEach(function () {
    $this->discovery = new BlockDiscovery();
    $this->discovery->setItems(new DiscoveryItems());
    $this->location = new DiscoveryLocation(
        namespace: 'App\\Test',
        path: __DIR__,
    );
});

describe('BlockDiscovery caching', function () {
    it('converts items to cacheable format', function () {
        $attribute = new AsBlock(
            name: 'my-theme/hero',
            title: 'Hero Block',
            category: 'theme',
            icon: 'cover-image',
            description: 'A hero section block',
            keywords: ['hero', 'banner'],
            supports: ['align' => ['wide', 'full']],
        );

        $this->discovery->getItems()->add($this->location, [
            'attribute' => $attribute,
            'className' => 'App\\Blocks\\HeroBlock',
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData)->toHaveCount(1);
        expect($cacheableData[0]['blockName'])->toBe('my-theme/hero');
        expect($cacheableData[0]['title'])->toBe('Hero Block');
        expect($cacheableData[0]['category'])->toBe('theme');
        expect($cacheableData[0]['icon'])->toBe('cover-image');
        expect($cacheableData[0]['description'])->toBe('A hero section block');
        expect($cacheableData[0]['keywords'])->toBe(['hero', 'banner']);
        expect($cacheableData[0]['supports'])->toBe(['align' => ['wide', 'full']]);
        expect($cacheableData[0]['className'])->toBe('App\\Blocks\\HeroBlock');
        expect($cacheableData[0]['interactivity'])->toBeFalse();
        expect($cacheableData[0]['interactivityNamespace'])->toBeNull();
    });

    it('handles interactive block', function () {
        $attribute = new AsBlock(
            name: 'my-theme/counter',
            title: 'Counter',
            category: 'widgets',
            interactivity: true,
        );

        $this->discovery->getItems()->add($this->location, [
            'attribute' => $attribute,
            'className' => 'App\\Blocks\\CounterBlock',
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData[0]['interactivity'])->toBeTrue();
        expect($cacheableData[0]['interactivityNamespace'])->toBe('my-theme/counter');
        expect($cacheableData[0]['supports']['interactivity'])->toBeTrue();
    });

    it('handles custom interactivity namespace', function () {
        $attribute = new AsBlock(
            name: 'my-theme/slider',
            title: 'Slider',
            category: 'media',
            interactivity: true,
            interactivityNamespace: 'my-custom-namespace',
        );

        $this->discovery->getItems()->add($this->location, [
            'attribute' => $attribute,
            'className' => 'App\\Blocks\\SliderBlock',
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData[0]['interactivityNamespace'])->toBe('my-custom-namespace');
    });

    it('handles parent and ancestor constraints', function () {
        $attribute = new AsBlock(
            name: 'my-theme/slide',
            title: 'Slide',
            category: 'media',
            parent: 'my-theme/slider',
            ancestor: ['my-theme/carousel'],
        );

        $this->discovery->getItems()->add($this->location, [
            'attribute' => $attribute,
            'className' => 'App\\Blocks\\SlideBlock',
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData[0]['parent'])->toBe('my-theme/slider');
        expect($cacheableData[0]['ancestor'])->toBe(['my-theme/carousel']);
    });

    it('can restore from cache', function () {
        $cachedData = [
            [
                'blockName' => 'my-theme/hero',
                'title' => 'Hero Block',
                'category' => 'theme',
                'icon' => null,
                'description' => null,
                'keywords' => [],
                'supports' => [],
                'parent' => null,
                'ancestor' => [],
                'interactivity' => false,
                'interactivityNamespace' => null,
                'className' => 'App\\Blocks\\HeroBlock',
            ],
        ];

        $this->discovery->restoreFromCache($cachedData);

        expect($this->discovery->wasRestoredFromCache())->toBeTrue();
    });
});
