<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\DiscoveryLocation;
use Studiometa\Foehn\Discovery\ShortcodeDiscovery;
use Tests\Fixtures\ShortcodeFixture;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    wp_stub_reset();
    bootTestContainer();
    $this->discovery = new ShortcodeDiscovery();
});

afterEach(fn() => tearDownTestContainer());

describe('ShortcodeDiscovery apply', function () {
    it('registers discovered shortcodes with WordPress', function () {
        $this->discovery->discover($this->location, new ReflectionClass(ShortcodeFixture::class));
        $this->discovery->apply();

        $calls = wp_stub_get_calls('add_shortcode');

        expect($calls)->toHaveCount(2);
        expect($calls[0]['args']['tag'])->toBe('greeting');
        expect($calls[1]['args']['tag'])->toBe('farewell');
    });

    it('registers nothing when no items discovered', function () {
        $this->discovery->apply();

        expect(wp_stub_get_calls('add_shortcode'))->toBeEmpty();
    });

    it('registers the same shortcodes whether scanned or restored from cache', function () {
        $scanned = new ShortcodeDiscovery();
        discoverFixture($scanned, ShortcodeFixture::class, $this->location);

        restoreThroughCacheFile($scanned, $this->discovery)->apply();

        $tags = array_map(static fn(array $call): string => $call['args']['tag'], wp_stub_get_calls('add_shortcode'));

        expect($tags)->toBe(['greeting', 'farewell']);
    });
});
