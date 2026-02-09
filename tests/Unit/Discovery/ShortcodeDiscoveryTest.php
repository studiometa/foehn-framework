<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\ShortcodeDiscovery;
use Tests\Fixtures\NoAttributeFixture;
use Tests\Fixtures\ShortcodeFixture;
use Studiometa\Foehn\Discovery\DiscoveryLocation;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    $this->discovery = new ShortcodeDiscovery();
});

describe('ShortcodeDiscovery', function () {
    it('discovers shortcode attributes on methods', function () {
        $this->discovery->discover($this->location, new ReflectionClass(ShortcodeFixture::class));

        $items = $this->discovery->getItems()->all();

        expect($items)->toHaveCount(2);

        expect($items[0]['tag'])->toBe('greeting');
        expect($items[0]['className'])->toBe(ShortcodeFixture::class);
        expect($items[0]['methodName'])->toBe('greeting');

        expect($items[1]['tag'])->toBe('farewell');
        expect($items[1]['className'])->toBe(ShortcodeFixture::class);
        expect($items[1]['methodName'])->toBe('farewell');
    });

    it('ignores classes without shortcode attributes', function () {
        $this->discovery->discover($this->location, new ReflectionClass(NoAttributeFixture::class));

        expect($this->discovery->getItems()->isEmpty())->toBeTrue();
    });

    it('reports hasItems correctly', function () {
        expect($this->discovery->hasItems())->toBeFalse();

        $this->discovery->discover($this->location, new ReflectionClass(ShortcodeFixture::class));

        expect($this->discovery->hasItems())->toBeTrue();
    });
});
