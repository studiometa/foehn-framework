<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\HookDiscovery;
use Studiometa\Foehn\Discovery\JobDiscovery;
use Studiometa\Foehn\Discovery\StaleDiscoveryCache;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Tempest\Discovery\DiscoveryDiscovery;
use Tests\Fixtures\HookFixture;

/**
 * @param array<array-key, mixed> $entry
 */
function staleCacheWith(array $entry): bool
{
    $pool = new ArrayAdapter();
    $location = testDiscoveryLocation();
    $pool->save($pool->getItem($location->key)->set($entry));

    return StaleDiscoveryCache::namesMissingClass($pool, [$location]);
}

describe('StaleDiscoveryCache', function () {
    it('accepts an empty pool and an entry whose classes all exist', function () {
        expect(StaleDiscoveryCache::namesMissingClass(new ArrayAdapter(), [testDiscoveryLocation()]))->toBeFalse();
        expect(staleCacheWith([
            DiscoveryDiscovery::class => [HookDiscovery::class],
            HookDiscovery::class => [['className' => HookFixture::class, 'methodName' => 'onInit']],
        ]))->toBeFalse();
    });

    it('finds a deleted discovered class, discovery class or job class', function (array $entry) {
        expect(staleCacheWith($entry))->toBeTrue();
    })->with([
        'class name' => [[HookDiscovery::class => [['className' => 'Tests\Fixtures\Deleted']]]],
        'discovery class listed by the first pass' => [[
            DiscoveryDiscovery::class => ['Tests\Fixtures\DeletedDiscovery'],
        ]],
        'discovery class as a key' => [['Tests\Fixtures\DeletedDiscovery' => []]],
        'job handler' => [[JobDiscovery::class => [['handlerClass' => 'Tests\Fixtures\DeletedHandler']]]],
        'job payload' => [[
            JobDiscovery::class => [['handlerClass' => HookFixture::class, 'dtoClass' => 'Tests\Fixtures\DeletedJob']],
        ]],
    ]);
});
