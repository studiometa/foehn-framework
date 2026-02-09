<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsBlock;
use Studiometa\Foehn\Discovery\BlockDiscovery;
use Studiometa\Foehn\Discovery\DiscoveryLocation;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    $this->discovery = new BlockDiscovery();
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

        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, $this->location, [
            'attribute' => $attribute,
            'className' => 'App\\Blocks\\HeroBlock',
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData['App\\'])->toHaveCount(1);
        expect($cacheableData['App\\'][0]['blockName'])->toBe('my-theme/hero');
        expect($cacheableData['App\\'][0]['title'])->toBe('Hero Block');
        expect($cacheableData['App\\'][0]['category'])->toBe('theme');
        expect($cacheableData['App\\'][0]['icon'])->toBe('cover-image');
        expect($cacheableData['App\\'][0]['description'])->toBe('A hero section block');
        expect($cacheableData['App\\'][0]['keywords'])->toBe(['hero', 'banner']);
        expect($cacheableData['App\\'][0]['supports'])->toBe(['align' => ['wide', 'full']]);
        expect($cacheableData['App\\'][0]['className'])->toBe('App\\Blocks\\HeroBlock');
        expect($cacheableData['App\\'][0]['interactivity'])->toBeFalse();
        expect($cacheableData['App\\'][0]['interactivityNamespace'])->toBeNull();
    });

    it('handles interactive block', function () {
        $attribute = new AsBlock(name: 'my-theme/counter', title: 'Counter', category: 'widgets', interactivity: true);

        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, $this->location, [
            'attribute' => $attribute,
            'className' => 'App\\Blocks\\CounterBlock',
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData['App\\'][0]['interactivity'])->toBeTrue();
        expect($cacheableData['App\\'][0]['interactivityNamespace'])->toBe('my-theme/counter');
        expect($cacheableData['App\\'][0]['supports']['interactivity'])->toBeTrue();
    });

    it('handles custom interactivity namespace', function () {
        $attribute = new AsBlock(
            name: 'my-theme/slider',
            title: 'Slider',
            category: 'media',
            interactivity: true,
            interactivityNamespace: 'my-custom-namespace',
        );

        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, $this->location, [
            'attribute' => $attribute,
            'className' => 'App\\Blocks\\SliderBlock',
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData['App\\'][0]['interactivityNamespace'])->toBe('my-custom-namespace');
    });

    it('handles parent and ancestor constraints', function () {
        $attribute = new AsBlock(
            name: 'my-theme/slide',
            title: 'Slide',
            category: 'media',
            parent: 'my-theme/slider',
            ancestor: ['my-theme/carousel'],
        );

        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, $this->location, [
            'attribute' => $attribute,
            'className' => 'App\\Blocks\\SlideBlock',
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData['App\\'][0]['parent'])->toBe('my-theme/slider');
        expect($cacheableData['App\\'][0]['ancestor'])->toBe(['my-theme/carousel']);
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

        $this->discovery->restoreFromCache(['App\\' => $cachedData]);

        expect($this->discovery->wasRestoredFromCache())->toBeTrue();
    });
});
