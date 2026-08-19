<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsPostType;
use Studiometa\Foehn\Discovery\PostTypeDiscovery;
use Tests\Fixtures\PostTypeFixture;
use Tests\Fixtures\ProductFixture;

beforeEach(function () {
    $this->location = testDiscoveryLocation();
    $this->discovery = new PostTypeDiscovery();
});

describe('PostTypeDiscovery caching', function () {
    it('keeps the item under its location namespace', function () {
        discoverFixture($this->discovery, PostTypeFixture::class, $this->location);

        expect($this->discovery->getItems()->getForLocation($this->location))->toHaveCount(1);
    });

    it('restores every item unchanged through a cache file', function () {
        discoverFixture($this->discovery, PostTypeFixture::class, $this->location);

        $restored = restoreThroughCacheFile($this->discovery, new PostTypeDiscovery());

        expect(iterator_to_array($restored->getItems()))->toEqual(iterator_to_array($this->discovery->getItems()));
    });

    it('restores the attribute as an instance, not an array', function () {
        discoverFixture($this->discovery, PostTypeFixture::class, $this->location);

        $item = restoreThroughCacheFile($this->discovery, new PostTypeDiscovery())
            ->getItems()
            ->getForLocation($this->location)[0];

        expect($item['attribute'])
            ->toBeInstanceOf(AsPostType::class)
            ->and($item['attribute']->name)
            ->toBe('project')
            ->and($item['attribute']->singular)
            ->toBe('Project')
            ->and($item['attribute']->plural)
            ->toBe('Projects')
            ->and($item['attribute']->menuIcon)
            ->toBe('dashicons-portfolio')
            ->and($item['implementsConfig'])
            ->toBeFalse();
    });

    it('keeps one item per discovered post type', function () {
        discoverFixture($this->discovery, PostTypeFixture::class, $this->location);
        discoverFixture($this->discovery, ProductFixture::class, $this->location);

        expect($this->discovery->getItems()->getForLocation($this->location))->toHaveCount(2);
    });
});
