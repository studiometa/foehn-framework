<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\ShortcodeDiscovery;
use Tests\Fixtures\NoAttributeFixture;
use Tests\Fixtures\ShortcodeFixture;

beforeEach(function () {
    $this->location = testDiscoveryLocation();
    $this->discovery = new ShortcodeDiscovery();
});

describe('ShortcodeDiscovery', function () {
    it('discovers shortcode attributes on methods', function () {
        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(ShortcodeFixture::class));

        $items = iterator_to_array($this->discovery->getItems());

        expect($items)->toHaveCount(2);

        expect($items[0]['attribute']->tag)->toBe('greeting');
        expect($items[0]['className'])->toBe(ShortcodeFixture::class);
        expect($items[0]['methodName'])->toBe('greeting');

        expect($items[1]['attribute']->tag)->toBe('farewell');
        expect($items[1]['className'])->toBe(ShortcodeFixture::class);
        expect($items[1]['methodName'])->toBe('farewell');
    });

    it('ignores classes without shortcode attributes', function () {
        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(NoAttributeFixture::class));

        expect($this->discovery->getItems())->toHaveCount(0);
    });

    it('reports hasItems correctly', function () {
        expect($this->discovery->getItems())->toHaveCount(0);

        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(ShortcodeFixture::class));

        expect($this->discovery->getItems())->not->toHaveCount(0);
    });
});
