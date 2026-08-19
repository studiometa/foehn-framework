<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsShortcode;
use Studiometa\Foehn\Discovery\ShortcodeDiscovery;
use Tests\Fixtures\ShortcodeFixture;

beforeEach(function () {
    $this->location = testDiscoveryLocation();
    $this->discovery = new ShortcodeDiscovery();
});

describe('ShortcodeDiscovery caching', function () {
    it('keeps every item under its location namespace', function () {
        discoverFixture($this->discovery, ShortcodeFixture::class, $this->location);

        expect($this->discovery->getItems()->getForLocation($this->location))->toHaveCount(2);
    });

    it('restores every item unchanged through a cache file', function () {
        discoverFixture($this->discovery, ShortcodeFixture::class, $this->location);

        $restored = restoreThroughCacheFile($this->discovery, new ShortcodeDiscovery());

        expect(iterator_to_array($restored->getItems()))->toEqual(iterator_to_array($this->discovery->getItems()));
    });

    it('restores the attribute as an instance, not an array', function () {
        discoverFixture($this->discovery, ShortcodeFixture::class, $this->location);

        $item = restoreThroughCacheFile($this->discovery, new ShortcodeDiscovery())
            ->getItems()
            ->getForLocation($this->location)[0];

        expect($item['attribute'])
            ->toBeInstanceOf(AsShortcode::class)
            ->and($item['attribute']->tag)
            ->toBe('greeting')
            ->and($item['className'])
            ->toBe(ShortcodeFixture::class)
            ->and($item['methodName'])
            ->toBe('greeting');
    });

    it('keeps each method binding distinct', function () {
        discoverFixture($this->discovery, ShortcodeFixture::class, $this->location);

        $items = restoreThroughCacheFile($this->discovery, new ShortcodeDiscovery())
            ->getItems()
            ->getForLocation($this->location);

        expect(array_column($items, 'methodName'))->toBe(['greeting', 'farewell']);
        expect(array_map(static fn(array $i): string => $i['attribute']->tag, $items))->toBe(['greeting', 'farewell']);
    });
});
