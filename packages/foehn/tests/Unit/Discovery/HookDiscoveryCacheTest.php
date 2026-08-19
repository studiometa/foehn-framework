<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Attributes\AsFilter;
use Studiometa\Foehn\Discovery\HookDiscovery;
use Tempest\Container\GenericContainer;
use Tests\Fixtures\HookFixture;

beforeEach(function () {
    $this->location = testDiscoveryLocation();
    $this->discovery = new HookDiscovery(new GenericContainer());
});

describe('HookDiscovery caching', function () {
    it('keeps one item per hook attribute found', function () {
        discoverFixture($this->discovery, HookFixture::class, $this->location);

        // HookFixture declares two actions and two filters.
        expect($this->discovery->getItems()->getForLocation($this->location))->toHaveCount(4);
    });

    it('restores every item unchanged through a cache file', function () {
        discoverFixture($this->discovery, HookFixture::class, $this->location);

        $restored = restoreThroughCacheFile($this->discovery, new HookDiscovery(new GenericContainer()));

        expect(iterator_to_array($restored->getItems()))->toEqual(iterator_to_array($this->discovery->getItems()));
    });

    it('keeps actions and filters apart by attribute class', function () {
        discoverFixture($this->discovery, HookFixture::class, $this->location);

        $items = restoreThroughCacheFile($this->discovery, new HookDiscovery(new GenericContainer()))
            ->getItems()
            ->getForLocation($this->location);

        $actions = array_values(array_filter($items, static fn(array $i) => $i['attribute'] instanceof AsAction));
        $filters = array_values(array_filter($items, static fn(array $i) => $i['attribute'] instanceof AsFilter));

        expect($actions)->toHaveCount(2)->and($filters)->toHaveCount(2);
        expect($actions[0]['attribute']->hook)->toBe('init');
        expect($filters[0]['attribute']->hook)->toBe('the_content');
    });

    it('restores the priority and accepted args of each hook', function () {
        discoverFixture($this->discovery, HookFixture::class, $this->location);

        $items = restoreThroughCacheFile($this->discovery, new HookDiscovery(new GenericContainer()))
            ->getItems()
            ->getForLocation($this->location);

        $byMethod = array_column(
            array_map(static fn(array $i): array => [
                $i['methodName'],
                [$i['attribute']->priority, $i['attribute']->acceptedArgs],
            ], $items),
            1,
            0,
        );

        expect($byMethod['onInit'])->toBe([10, 1])->and($byMethod['filterTitle'])->toBe([20, 2]);
    });

    it('keeps the method binding of every item', function () {
        discoverFixture($this->discovery, HookFixture::class, $this->location);

        $items = restoreThroughCacheFile($this->discovery, new HookDiscovery(new GenericContainer()))
            ->getItems()
            ->getForLocation($this->location);

        foreach ($items as $item) {
            expect($item['className'])->toBe(HookFixture::class)->and($item['methodName'])->toBeString();
        }
    });
});
