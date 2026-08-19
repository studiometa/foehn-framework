<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsMenu;
use Studiometa\Foehn\Attributes\AsShortcode;
use Studiometa\Foehn\Discovery\Concerns\CacheableDiscovery;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Studiometa\Foehn\Discovery\DiscoveryLocation;
use Studiometa\Foehn\Discovery\WpDiscovery;

// Test implementation of a cacheable discovery
final class TestCacheableDiscovery implements WpDiscovery
{
    use IsWpDiscovery;
    use CacheableDiscovery;

    public function discover(DiscoveryLocation $location, ReflectionClass $class): void
    {
        // For testing, we add items manually
    }

    public function apply(): void
    {
        // No-op for testing
    }

    public function addTestItem(DiscoveryLocation $location, array $item): void
    {
        $this->addItem($location, $item);
    }
}

describe('CacheableDiscovery', function () {
    beforeEach(function () {
        $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
        $this->discovery = new TestCacheableDiscovery();
    });

    it('passes plain item values through untouched', function () {
        $this->discovery->addTestItem($this->location, [
            'className' => 'App\\Thing',
            'methodName' => 'handle',
            'nested' => ['a' => 1, 'b' => [true, null]],
        ]);

        expect($this->discovery->getCacheableData()['App\\'])->toBe([
            [
                'className' => 'App\\Thing',
                'methodName' => 'handle',
                'nested' => ['a' => 1, 'b' => [true, null]],
            ],
        ]);
    });

    it('encodes an attribute value and leaves its siblings alone', function () {
        $this->discovery->addTestItem($this->location, [
            'attribute' => new AsMenu(location: 'primary', description: 'Primary'),
            'className' => 'App\\Menus\\Primary',
        ]);

        $cached = $this->discovery->getCacheableData()['App\\'][0];

        expect($cached['className'])
            ->toBe('App\\Menus\\Primary')
            ->and($cached['attribute'])
            ->toBe([
                '__attribute' => AsMenu::class,
                'args' => ['location' => 'primary', 'description' => 'Primary'],
            ]);
    });

    it('rebuilds every attribute in an item on restore', function () {
        $this->discovery->addTestItem($this->location, [
            'attribute' => new AsShortcode(tag: 'gallery'),
            'className' => 'App\\Shortcodes\\Gallery',
            'methodName' => 'render',
        ]);

        $item = restoreThroughCacheFile($this->discovery, new TestCacheableDiscovery())->getItems()->all()[0];

        expect($item['attribute'])
            ->toBeInstanceOf(AsShortcode::class)
            ->and($item['attribute']->tag)
            ->toBe('gallery')
            ->and($item['className'])
            ->toBe('App\\Shortcodes\\Gallery')
            ->and($item['methodName'])
            ->toBe('render');
    });

    it('reports whether the items came from the cache', function () {
        $this->discovery->addTestItem($this->location, ['className' => 'App\\Thing']);

        expect($this->discovery->wasRestoredFromCache())
            ->toBeFalse()
            ->and(restoreThroughCacheFile($this->discovery, new TestCacheableDiscovery())->wasRestoredFromCache())
            ->toBeTrue();
    });

    it('keeps items grouped by location namespace', function () {
        $vendor = DiscoveryLocation::app('Vendor\\', '/tmp/vendor-app');

        $this->discovery->addTestItem($this->location, ['className' => 'App\\One']);
        $this->discovery->addTestItem($this->location, ['className' => 'App\\Two']);
        $this->discovery->addTestItem($vendor, ['className' => 'Vendor\\Three']);

        $cached = $this->discovery->getCacheableData();

        expect($cached['App\\'])->toHaveCount(2)->and($cached['Vendor\\'])->toHaveCount(1);
    });

    it('returns empty array when no items', function () {
        expect($this->discovery->getCacheableData())->toBeEmpty();
    });
});
