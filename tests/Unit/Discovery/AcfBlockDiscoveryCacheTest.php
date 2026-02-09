<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsAcfBlock;
use Studiometa\Foehn\Discovery\AcfBlockDiscovery;
use Studiometa\Foehn\Discovery\DiscoveryLocation;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    $this->discovery = new AcfBlockDiscovery();
});

describe('AcfBlockDiscovery caching', function () {
    it('converts items to cacheable format', function () {
        $attribute = new AsAcfBlock(
            name: 'hero',
            title: 'Hero Block',
            category: 'theme',
            icon: 'cover-image',
            description: 'A hero section with image and text',
            keywords: ['hero', 'banner', 'header'],
            mode: 'preview',
            supports: ['align' => true, 'mode' => true],
            postTypes: ['page'],
            parent: 'acf/section',
        );

        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, $this->location, [
            'attribute' => $attribute,
            'className' => 'App\\Blocks\\HeroBlock',
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData['App\\'])->toHaveCount(1);
        expect($cacheableData['App\\'][0]['name'])->toBe('hero');
        expect($cacheableData['App\\'][0]['title'])->toBe('Hero Block');
        expect($cacheableData['App\\'][0]['description'])->toBe('A hero section with image and text');
        expect($cacheableData['App\\'][0]['category'])->toBe('theme');
        expect($cacheableData['App\\'][0]['icon'])->toBe('cover-image');
        expect($cacheableData['App\\'][0]['keywords'])->toBe(['hero', 'banner', 'header']);
        expect($cacheableData['App\\'][0]['mode'])->toBe('preview');
        expect($cacheableData['App\\'][0]['postTypes'])->toBe(['page']);
        expect($cacheableData['App\\'][0]['parent'])->toBe('acf/section');
        expect($cacheableData['App\\'][0]['className'])->toBe('App\\Blocks\\HeroBlock');
    });

    it('applies default supports', function () {
        $attribute = new AsAcfBlock(name: 'simple', title: 'Simple Block');

        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, $this->location, [
            'attribute' => $attribute,
            'className' => 'App\\Blocks\\SimpleBlock',
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData['App\\'][0]['supports'])->toBe([
            'align' => false,
            'mode' => true,
            'multiple' => true,
        ]);
    });

    it('merges custom supports with defaults', function () {
        $attribute = new AsAcfBlock(name: 'custom', title: 'Custom Block', supports: [
            'align' => ['wide', 'full'],
            'jsx' => true,
        ]);

        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, $this->location, [
            'attribute' => $attribute,
            'className' => 'App\\Blocks\\CustomBlock',
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData['App\\'][0]['supports'])->toBe([
            'align' => ['wide', 'full'],
            'mode' => true,
            'multiple' => true,
            'jsx' => true,
        ]);
    });

    it('handles minimal configuration', function () {
        $attribute = new AsAcfBlock(name: 'minimal', title: 'Minimal Block');

        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, $this->location, [
            'attribute' => $attribute,
            'className' => 'App\\Blocks\\MinimalBlock',
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData['App\\'][0]['description'])->toBeNull();
        expect($cacheableData['App\\'][0]['category'])->toBe('common');
        expect($cacheableData['App\\'][0]['icon'])->toBeNull();
        expect($cacheableData['App\\'][0]['keywords'])->toBe([]);
        expect($cacheableData['App\\'][0]['mode'])->toBe('preview');
        expect($cacheableData['App\\'][0]['postTypes'])->toBe([]);
        expect($cacheableData['App\\'][0]['parent'])->toBeNull();
    });

    it('can restore from cache', function () {
        $cachedData = [
            [
                'name' => 'hero',
                'title' => 'Hero Block',
                'description' => 'A hero section',
                'category' => 'theme',
                'icon' => 'cover-image',
                'keywords' => ['hero'],
                'mode' => 'preview',
                'supports' => ['align' => false, 'mode' => true, 'multiple' => true],
                'postTypes' => [],
                'parent' => null,
                'className' => 'App\\Blocks\\HeroBlock',
            ],
        ];

        $this->discovery->restoreFromCache(['App\\' => $cachedData]);

        expect($this->discovery->wasRestoredFromCache())->toBeTrue();
    });
});
