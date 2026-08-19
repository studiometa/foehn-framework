<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsBlock;
use Studiometa\Foehn\Discovery\BlockDiscovery;
use Studiometa\Foehn\Discovery\DiscoveryLocation;
use Tests\Fixtures\BlockFixture;
use Tests\Fixtures\ContainerBlockFixture;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    $this->discovery = new BlockDiscovery();
});

describe('BlockDiscovery caching', function () {
    it('keeps the item under its location namespace', function () {
        discoverFixture($this->discovery, BlockFixture::class, $this->location);

        $cacheData = $this->discovery->getCacheableData();

        expect($cacheData)->toHaveKey('App\\')->and($cacheData['App\\'])->toHaveCount(1);
    });

    it('restores every item unchanged through a cache file', function () {
        discoverFixture($this->discovery, BlockFixture::class, $this->location);

        $restored = restoreThroughCacheFile($this->discovery, new BlockDiscovery());

        expect($restored->wasRestoredFromCache())
            ->toBeTrue()
            ->and($restored->getItems()->all())
            ->toEqual($this->discovery->getItems()->all());
    });

    it('restores the attribute as an instance, not an array', function () {
        discoverFixture($this->discovery, BlockFixture::class, $this->location);

        $item = restoreThroughCacheFile($this->discovery, new BlockDiscovery())->getItems()->all()[0];

        expect($item['attribute'])
            ->toBeInstanceOf(AsBlock::class)
            ->and($item['attribute']->name)
            ->toBe('test/hero')
            ->and($item['attribute']->title)
            ->toBe('Hero Block')
            ->and($item['attribute']->category)
            ->toBe('design')
            ->and($item['attribute']->keywords)
            ->toBe(['hero', 'banner'])
            ->and($item['className'])
            ->toBe(BlockFixture::class);
    });

    it('reports it was not restored when it scanned', function () {
        discoverFixture($this->discovery, BlockFixture::class, $this->location);

        expect($this->discovery->wasRestoredFromCache())->toBeFalse();
    });

    it('restores the inner blocks configuration of a container block', function () {
        discoverFixture($this->discovery, ContainerBlockFixture::class, $this->location);

        $item = restoreThroughCacheFile($this->discovery, new BlockDiscovery())->getItems()->all()[0];

        expect($item['attribute']->allowedBlocks)
            ->toBe(['core/heading', 'core/paragraph'])
            ->and($item['attribute']->innerBlocksTemplate)
            ->toBe([['core/heading', ['level' => 2]]])
            ->and($item['attribute']->innerBlocksTemplateLock)
            ->toBe('insert');
    });

    it('builds the same editor definitions whether scanned or restored', function () {
        discoverFixture($this->discovery, BlockFixture::class, $this->location);
        discoverFixture($this->discovery, ContainerBlockFixture::class, $this->location);

        $restored = restoreThroughCacheFile($this->discovery, new BlockDiscovery());

        expect($restored->getEditorDefinitions())->toBe($this->discovery->getEditorDefinitions());
    });

    it('registers the same block whether scanned or restored', function () {
        discoverFixture($this->discovery, ContainerBlockFixture::class, $this->location);

        wp_stub_reset();
        bootTestContainer();

        try {
            $this->discovery->apply();
            wp_stub_get_calls('add_action')[0]['args']['callback']();
            $scanned = wp_stub_get_calls('register_block_type');

            wp_stub_reset();

            restoreThroughCacheFile($this->discovery, new BlockDiscovery())->apply();
            wp_stub_get_calls('add_action')[0]['args']['callback']();
            $restored = wp_stub_get_calls('register_block_type');
        } finally {
            tearDownTestContainer();
        }

        expect($restored)->toHaveCount(1);
        expect($restored[0]['args']['blockName'])->toBe($scanned[0]['args']['blockName']);
        expect($restored[0]['args']['args']['allowed_blocks'])->toBe($scanned[0]['args']['args']['allowed_blocks']);
        expect($restored[0]['args']['args']['attributes'])->toBe($scanned[0]['args']['args']['attributes']);
    });
});
