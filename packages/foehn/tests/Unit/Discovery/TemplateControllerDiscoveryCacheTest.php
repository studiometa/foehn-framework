<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsTemplateController;
use Tests\Fixtures\TemplateControllerFixture;

beforeEach(function () {
    $this->location = testDiscoveryLocation();
    $this->discovery = testTemplateControllerDiscovery();
});

describe('TemplateControllerDiscovery caching', function () {
    it('keeps every item under its location namespace', function () {
        discoverFixture($this->discovery, TemplateControllerFixture::class, $this->location);

        expect($this->discovery->getItems()->getForLocation($this->location))->toHaveCount(1);
    });

    it('restores every item unchanged through a cache file', function () {
        discoverFixture($this->discovery, TemplateControllerFixture::class, $this->location);

        $restored = restoreThroughCacheFile($this->discovery, testTemplateControllerDiscovery());

        expect(iterator_to_array($restored->getItems()))->toEqual(iterator_to_array($this->discovery->getItems()));
    });

    it('restores the attribute as an instance, not an array', function () {
        discoverFixture($this->discovery, TemplateControllerFixture::class, $this->location);

        $item = restoreThroughCacheFile($this->discovery, testTemplateControllerDiscovery())
            ->getItems()
            ->getForLocation($this->location)[0];

        expect($item['attribute'])
            ->toBeInstanceOf(AsTemplateController::class)
            ->and($item['attribute']->getTemplates())
            ->toBe(['single', 'page'])
            ->and($item['attribute']->priority)
            ->toBe(10)
            ->and($item['className'])
            ->toBe(TemplateControllerFixture::class);
    });
});
