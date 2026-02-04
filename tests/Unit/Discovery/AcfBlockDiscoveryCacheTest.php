<?php

declare(strict_types=1);

use Studiometa\WPTempest\Attributes\AsAcfBlock;
use Studiometa\WPTempest\Discovery\AcfBlockDiscovery;
use Tempest\Discovery\DiscoveryItems;
use Tempest\Discovery\DiscoveryLocation;

beforeEach(function () {
    $this->discovery = new AcfBlockDiscovery();
    $this->discovery->setItems(new DiscoveryItems());
    $this->location = new DiscoveryLocation(
        namespace: 'App\\Test',
        path: __DIR__,
    );
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

        $this->discovery->getItems()->add($this->location, [
            'attribute' => $attribute,
            'className' => 'App\\Blocks\\HeroBlock',
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData)->toHaveCount(1);
        expect($cacheableData[0]['name'])->toBe('hero');
        expect($cacheableData[0]['title'])->toBe('Hero Block');
        expect($cacheableData[0]['description'])->toBe('A hero section with image and text');
        expect($cacheableData[0]['category'])->toBe('theme');
        expect($cacheableData[0]['icon'])->toBe('cover-image');
        expect($cacheableData[0]['keywords'])->toBe(['hero', 'banner', 'header']);
        expect($cacheableData[0]['mode'])->toBe('preview');
        expect($cacheableData[0]['postTypes'])->toBe(['page']);
        expect($cacheableData[0]['parent'])->toBe('acf/section');
        expect($cacheableData[0]['className'])->toBe('App\\Blocks\\HeroBlock');
    });

    it('applies default supports', function () {
        $attribute = new AsAcfBlock(
            name: 'simple',
            title: 'Simple Block',
        );

        $this->discovery->getItems()->add($this->location, [
            'attribute' => $attribute,
            'className' => 'App\\Blocks\\SimpleBlock',
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        // Default supports should be applied
        expect($cacheableData[0]['supports'])->toBe([
            'align' => false,
            'mode' => true,
            'multiple' => true,
        ]);
    });

    it('merges custom supports with defaults', function () {
        $attribute = new AsAcfBlock(
            name: 'custom',
            title: 'Custom Block',
            supports: ['align' => ['wide', 'full'], 'jsx' => true],
        );

        $this->discovery->getItems()->add($this->location, [
            'attribute' => $attribute,
            'className' => 'App\\Blocks\\CustomBlock',
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData[0]['supports'])->toBe([
            'align' => ['wide', 'full'],
            'mode' => true,
            'multiple' => true,
            'jsx' => true,
        ]);
    });

    it('handles minimal configuration', function () {
        $attribute = new AsAcfBlock(
            name: 'minimal',
            title: 'Minimal Block',
        );

        $this->discovery->getItems()->add($this->location, [
            'attribute' => $attribute,
            'className' => 'App\\Blocks\\MinimalBlock',
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData[0]['description'])->toBeNull();
        expect($cacheableData[0]['category'])->toBe('common');
        expect($cacheableData[0]['icon'])->toBeNull();
        expect($cacheableData[0]['keywords'])->toBe([]);
        expect($cacheableData[0]['mode'])->toBe('preview');
        expect($cacheableData[0]['postTypes'])->toBe([]);
        expect($cacheableData[0]['parent'])->toBeNull();
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

        $this->discovery->restoreFromCache($cachedData);

        expect($this->discovery->wasRestoredFromCache())->toBeTrue();
    });
});
