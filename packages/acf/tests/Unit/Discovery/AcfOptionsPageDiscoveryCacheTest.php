<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsAcfOptionsPage;
use Studiometa\Foehn\Discovery\AcfOptionsPageDiscovery;
use Tests\Fixtures\AcfOptionsPageFixture;
use Tests\Fixtures\AcfOptionsPageFullFixture;

beforeEach(function () {
    $this->location = testDiscoveryLocation();
    $this->discovery = new AcfOptionsPageDiscovery();
});

describe('AcfOptionsPageDiscovery caching', function () {
    it('keeps the item under its location namespace', function () {
        discoverFixture($this->discovery, AcfOptionsPageFixture::class, $this->location);

        expect($this->discovery->getItems()->getForLocation($this->location))->toHaveCount(1);
    });

    it('restores every item unchanged through a cache file', function () {
        discoverFixture($this->discovery, AcfOptionsPageFixture::class, $this->location);

        $restored = restoreThroughCacheFile($this->discovery, new AcfOptionsPageDiscovery());

        expect(iterator_to_array($restored->getItems()))->toEqual(iterator_to_array($this->discovery->getItems()));
    });

    it('restores the attribute as an instance, not an array', function () {
        discoverFixture($this->discovery, AcfOptionsPageFixture::class, $this->location);

        $item = restoreThroughCacheFile($this->discovery, new AcfOptionsPageDiscovery())
            ->getItems()
            ->getForLocation($this->location)[0];

        expect($item['attribute'])
            ->toBeInstanceOf(AsAcfOptionsPage::class)
            ->and($item['attribute']->pageTitle)
            ->toBe('Theme Settings')
            ->and($item['attribute']->menuSlug)
            ->toBe('theme-settings')
            ->and($item['attribute']->position)
            ->toBe(59)
            ->and($item['attribute']->redirect)
            ->toBeFalse()
            ->and($item['hasFields'])
            ->toBeTrue();
    });

    it('restores every optional field of a fully configured page', function () {
        discoverFixture($this->discovery, AcfOptionsPageFullFixture::class, $this->location);

        $item = restoreThroughCacheFile($this->discovery, new AcfOptionsPageDiscovery())
            ->getItems()
            ->getForLocation($this->location)[0];

        expect($item['attribute']->postId)
            ->toBe('full_settings')
            ->and($item['attribute']->updateButton)
            ->toBe('Save All Settings')
            ->and($item['attribute']->updatedMessage)
            ->toBe('All settings have been saved.')
            ->and($item['attribute']->autoload)
            ->toBeFalse();
    });
});
