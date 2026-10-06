<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\BlockCategoryDiscovery;
use Tests\Fixtures\BlockCategoryFixture;
use Tests\Fixtures\NoAttributeFixture;

beforeEach(function () {
    $this->discovery = new BlockCategoryDiscovery();
});

describe('BlockCategoryDiscovery', function () {
    it('discovers every category declared on a class', function () {
        discoverFixture($this->discovery, BlockCategoryFixture::class);

        $items = iterator_to_array($this->discovery->getItems());

        expect($items)->toHaveCount(2);
        expect($items[0]['className'])->toBe(BlockCategoryFixture::class);
        expect($items[0]['attribute']->slug)->toBe('acme-layout');
        expect($items[0]['attribute']->title)->toBe('Layout');
        expect($items[0]['attribute']->icon)->toBe('layout');
        expect($items[1]['attribute']->slug)->toBe('acme-content');
        expect($items[1]['attribute']->icon)->toBeNull();
    });

    it('ignores classes without the attribute', function () {
        discoverFixture($this->discovery, NoAttributeFixture::class);

        expect($this->discovery->getItems())->toHaveCount(0);
    });
});
