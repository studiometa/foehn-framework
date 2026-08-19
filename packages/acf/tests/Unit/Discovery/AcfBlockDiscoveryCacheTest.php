<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsAcfBlock;
use Studiometa\Foehn\Discovery\AcfBlockDiscovery;
use Tests\Fixtures\AcfBlockFixture;

beforeEach(function () {
    $this->location = testDiscoveryLocation();
    $this->discovery = new AcfBlockDiscovery();
});

describe('AcfBlockDiscovery caching', function () {
    it('keeps the item under its location namespace', function () {
        discoverFixture($this->discovery, AcfBlockFixture::class, $this->location);

        expect($this->discovery->getItems()->getForLocation($this->location))->toHaveCount(1);
    });

    it('restores every item unchanged through a cache file', function () {
        discoverFixture($this->discovery, AcfBlockFixture::class, $this->location);

        $restored = restoreThroughCacheFile($this->discovery, new AcfBlockDiscovery());

        expect(iterator_to_array($restored->getItems()))->toEqual(iterator_to_array($this->discovery->getItems()));
    });

    it('restores the attribute as an instance, not an array', function () {
        discoverFixture($this->discovery, AcfBlockFixture::class, $this->location);

        $item = restoreThroughCacheFile($this->discovery, new AcfBlockDiscovery())
            ->getItems()
            ->getForLocation($this->location)[0];

        expect($item['attribute'])
            ->toBeInstanceOf(AsAcfBlock::class)
            ->and($item['attribute']->name)
            ->toBe('testimonial')
            ->and($item['attribute']->title)
            ->toBe('Testimonial')
            ->and($item['attribute']->category)
            ->toBe('formatting')
            ->and($item['attribute']->keywords)
            ->toBe(['quote', 'testimonial'])
            ->and($item['className'])
            ->toBe(AcfBlockFixture::class);
    });
});
