<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsAcfFieldGroup;
use Studiometa\Foehn\Discovery\AcfFieldGroupDiscovery;
use Tests\Fixtures\AcfFieldGroupComplexLocationFixture;
use Tests\Fixtures\AcfFieldGroupFixture;

beforeEach(function () {
    $this->location = testDiscoveryLocation();
    $this->discovery = new AcfFieldGroupDiscovery();
});

describe('AcfFieldGroupDiscovery caching', function () {
    it('keeps the item under its location namespace', function () {
        discoverFixture($this->discovery, AcfFieldGroupFixture::class, $this->location);

        expect($this->discovery->getItems()->getForLocation($this->location))->toHaveCount(1);
    });

    it('restores every item unchanged through a cache file', function () {
        discoverFixture($this->discovery, AcfFieldGroupFixture::class, $this->location);

        $restored = restoreThroughCacheFile($this->discovery, new AcfFieldGroupDiscovery());

        expect(iterator_to_array($restored->getItems()))->toEqual(iterator_to_array($this->discovery->getItems()));
    });

    it('restores the attribute as an instance, not an array', function () {
        discoverFixture($this->discovery, AcfFieldGroupFixture::class, $this->location);

        $item = restoreThroughCacheFile($this->discovery, new AcfFieldGroupDiscovery())
            ->getItems()
            ->getForLocation($this->location)[0];

        expect($item['attribute'])
            ->toBeInstanceOf(AsAcfFieldGroup::class)
            ->and($item['attribute']->name)
            ->toBe('property_fields')
            ->and($item['attribute']->location)
            ->toBe(['post_type' => 'property'])
            ->and($item['attribute']->hideOnScreen)
            ->toBe(['the_content', 'excerpt']);
    });

    it('restores a full ACF location format unchanged', function () {
        discoverFixture($this->discovery, AcfFieldGroupComplexLocationFixture::class, $this->location);

        $restored = restoreThroughCacheFile($this->discovery, new AcfFieldGroupDiscovery());

        expect(iterator_to_array($restored->getItems()))->toEqual(iterator_to_array($this->discovery->getItems()));
    });

    it('keeps one item per discovered class', function () {
        discoverFixture($this->discovery, AcfFieldGroupFixture::class, $this->location);
        discoverFixture($this->discovery, AcfFieldGroupComplexLocationFixture::class, $this->location);

        expect($this->discovery->getItems()->getForLocation($this->location))->toHaveCount(2);
    });
});
