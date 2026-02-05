<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\AcfOptionsPageDiscovery;
use Tests\Fixtures\AcfOptionsPageFixture;
use Tests\Fixtures\AcfOptionsSubPageFixture;
use Tests\Fixtures\NoAttributeFixture;

beforeEach(function () {
    $this->discovery = new AcfOptionsPageDiscovery();
});

describe('AcfOptionsPageDiscovery', function () {
    it('discovers ACF options page attributes on classes', function () {
        $this->discovery->discover(new ReflectionClass(AcfOptionsPageFixture::class));

        $items = $this->discovery->getItems();

        expect($items)->toHaveCount(1);
        expect($items[0]['className'])->toBe(AcfOptionsPageFixture::class);
        expect($items[0]['attribute']->pageTitle)->toBe('Theme Settings');
        expect($items[0]['attribute']->menuTitle)->toBe('Theme');
        expect($items[0]['attribute']->menuSlug)->toBe('theme-settings');
        expect($items[0]['attribute']->capability)->toBe('manage_options');
        expect($items[0]['attribute']->position)->toBe(59);
        expect($items[0]['attribute']->iconUrl)->toBe('dashicons-admin-generic');
        expect($items[0]['attribute']->redirect)->toBeFalse();
        expect($items[0]['attribute']->autoload)->toBeTrue();
        expect($items[0]['hasFields'])->toBeTrue();
    });

    it('discovers sub-page options pages', function () {
        $this->discovery->discover(new ReflectionClass(AcfOptionsSubPageFixture::class));

        $items = $this->discovery->getItems();

        expect($items)->toHaveCount(1);
        expect($items[0]['attribute']->pageTitle)->toBe('Social Media');
        expect($items[0]['attribute']->parentSlug)->toBe('theme-settings');
        expect($items[0]['attribute']->isSubPage())->toBeTrue();
        expect($items[0]['hasFields'])->toBeFalse();
    });

    it('ignores classes without ACF options page attribute', function () {
        $this->discovery->discover(new ReflectionClass(NoAttributeFixture::class));

        expect($this->discovery->getItems())->toBeEmpty();
    });

    it('reports hasItems correctly', function () {
        expect($this->discovery->hasItems())->toBeFalse();

        $this->discovery->discover(new ReflectionClass(AcfOptionsPageFixture::class));

        expect($this->discovery->hasItems())->toBeTrue();
    });
});
