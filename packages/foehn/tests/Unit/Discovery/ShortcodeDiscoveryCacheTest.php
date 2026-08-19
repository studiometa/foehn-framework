<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsShortcode;
use Studiometa\Foehn\Discovery\DiscoveryLocation;
use Studiometa\Foehn\Discovery\ShortcodeDiscovery;
use Tests\Fixtures\ShortcodeFixture;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    $this->discovery = new ShortcodeDiscovery();
});

describe('ShortcodeDiscovery caching', function () {
    it('keeps every item under its location namespace', function () {
        discoverFixture($this->discovery, ShortcodeFixture::class, $this->location);

        $cacheData = $this->discovery->getCacheableData();

        expect($cacheData)->toHaveKey('App\\')->and($cacheData['App\\'])->toHaveCount(2);
    });

    it('restores every item unchanged through a cache file', function () {
        discoverFixture($this->discovery, ShortcodeFixture::class, $this->location);

        $restored = restoreThroughCacheFile($this->discovery, new ShortcodeDiscovery());

        expect($restored->wasRestoredFromCache())
            ->toBeTrue()
            ->and($restored->getItems()->all())
            ->toEqual($this->discovery->getItems()->all());
    });

    it('restores the attribute as an instance, not an array', function () {
        discoverFixture($this->discovery, ShortcodeFixture::class, $this->location);

        $item = restoreThroughCacheFile($this->discovery, new ShortcodeDiscovery())->getItems()->all()[0];

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

        $items = restoreThroughCacheFile($this->discovery, new ShortcodeDiscovery())->getItems()->all();

        expect(array_column($items, 'methodName'))->toBe(['greeting', 'farewell']);
        expect(array_map(static fn(array $i): string => $i['attribute']->tag, $items))->toBe(['greeting', 'farewell']);
    });
});
