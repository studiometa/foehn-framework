<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\DiscoveryLocations;
use Studiometa\Foehn\Discovery\DiscoveryPhase;
use Studiometa\Foehn\Discovery\DiscoveryRunner;
use Studiometa\Foehn\Discovery\HookDiscovery;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Tempest\Discovery\DiscoveryCache;
use Tempest\Discovery\DiscoveryCacheStrategy;
use Tempest\Discovery\DiscoveryDiscovery;
use Tempest\Discovery\DiscoveryLocation;
use Tests\Fixtures\App\CacheableHooks;

beforeEach(function () {
    wp_stub_reset();

    $this->container = bootTestContainer();
    $this->pool = new ArrayAdapter();

    // A directory holding a single hook class, so a scan finds something the cache
    // can be checked for afterwards.
    $this->appPath = dirname(__DIR__, 2) . '/Fixtures/App';
    $this->locations = new DiscoveryLocations($this->appPath);
    $this->location = $this->locations->app();

    $this->runner = function (DiscoveryCacheStrategy $strategy): DiscoveryRunner {
        return new DiscoveryRunner(
            $this->container,
            new DiscoveryCache($strategy, $this->pool),
            $this->pool,
            $this->locations,
        );
    };
});

afterEach(fn() => tearDownTestContainer());

describe('discovery cache warming', function () {
    it('writes what it scanned, so the next request does not scan again', function () {
        expect($this->pool->getItem($this->location->key)->isHit())->toBeFalse();

        ($this->runner)(DiscoveryCacheStrategy::FULL)->runEarlyDiscoveries();

        $entry = $this->pool->getItem($this->location->key)->get();

        expect($entry)->toHaveKey(HookDiscovery::class);
        expect($entry[HookDiscovery::class][0]['className'])->toBe(CacheableHooks::class);
    });

    it('serves the second runner from what the first one wrote', function () {
        ($this->runner)(DiscoveryCacheStrategy::FULL)->runEarlyDiscoveries();

        // The fixture directory is still there, so a second scan would find the same
        // item and prove nothing. Tampering with the stored entry separates the two:
        // only a runner reading the cache can register a hook that is not in the code.
        $item = $this->pool->getItem($this->location->key);
        $entry = $item->get();
        $entry[HookDiscovery::class][0]['attribute'] = new Studiometa\Foehn\Attributes\AsAction('wp_footer');
        $this->pool->save($item->set($entry));

        wp_stub_reset();

        ($this->runner)(DiscoveryCacheStrategy::FULL)->runEarlyDiscoveries();

        $hooks = array_column(array_column(wp_stub_get_calls('add_action'), 'args'), 'hook');

        expect($hooks)->toBe(['wp_footer']);
    });

    it('writes nothing when caching is disabled', function () {
        ($this->runner)(DiscoveryCacheStrategy::NONE)->runEarlyDiscoveries();

        expect($this->pool->getItem($this->location->key)->isHit())->toBeFalse();
    });

    it('leaves the app location alone under a partial strategy', function () {
        // Under PARTIAL, Tempest rescans the app on every request. Writing its entry
        // would be a file written per request that nothing ever reads.
        ($this->runner)(DiscoveryCacheStrategy::PARTIAL)->runEarlyDiscoveries();

        expect($this->pool->getItem($this->location->key)->isHit())->toBeFalse();
    });

    it('still caches vendor locations under a partial strategy', function () {
        $vendor = testVendorLocation();

        $runner = new DiscoveryRunner(
            $this->container,
            new DiscoveryCache(DiscoveryCacheStrategy::PARTIAL, $this->pool),
            $this->pool,
            new DiscoveryLocations($vendor->path),
        );

        $runner->runEarlyDiscoveries();

        expect($this->pool->getItem($vendor->key)->isHit())->toBeTrue();
    });

    it('rewrites an entry that predates a discovery class', function () {
        // Tempest rejects a partial entry and rescans the location, so an entry from
        // an older Foehn would otherwise be re-read and re-rejected on every request.
        $this->pool->save($this->pool->getItem($this->location->key)->set([HookDiscovery::class => []]));

        $runner = ($this->runner)(DiscoveryCacheStrategy::FULL);
        $runner->runEarlyDiscoveries();

        $entry = $this->pool->getItem($this->location->key)->get();

        // Every discovery that ran, plus the pass that found them in the first place.
        expect(array_keys($entry))->toHaveCount(count($runner->getDiscoveries()) + 1);
        expect($entry)->toHaveKey(DiscoveryDiscovery::class);
        expect($entry[HookDiscovery::class])->toHaveCount(1);
    });

    it('remembers whether it scanned a location or read it back', function () {
        // Nothing can work this out afterwards: the scan writes the entry, so a
        // location that was scanned is cached a moment later. Only the runner that
        // did it knows which happened.
        $first = ($this->runner)(DiscoveryCacheStrategy::FULL);
        $first->getDiscoveries();

        expect($first->wasRestoredFromCache($this->location))->toBeFalse();

        $second = ($this->runner)(DiscoveryCacheStrategy::FULL);

        expect($second->wasRestoredFromCache($this->location))->toBeTrue();
    });

    it('reports a location as scanned when caching is off', function () {
        expect(($this->runner)(DiscoveryCacheStrategy::NONE)->wasRestoredFromCache($this->location))->toBeFalse();
    });

    it('serves the request when the cache cannot be written', function () {
        $pool = new class extends ArrayAdapter {
            public function save(Psr\Cache\CacheItemInterface $item): bool
            {
                // What a read-only wp-content looks like from here.
                return false;
            }
        };

        $runner = new DiscoveryRunner(
            $this->container,
            new DiscoveryCache(DiscoveryCacheStrategy::FULL, $pool),
            $pool,
            $this->locations,
        );

        $runner->runEarlyDiscoveries();

        // The hook was still registered; only the write was lost.
        expect(wp_stub_get_calls('add_action'))->toHaveCount(1);
    });

    it('does not write twice when the command warms the cache', function () {
        $cache = new DiscoveryCache(DiscoveryCacheStrategy::FULL, $this->pool);

        $counts = ($this->runner)(DiscoveryCacheStrategy::FULL)->warmCache($cache);

        expect($counts)->toHaveKey(HookDiscovery::class);
        expect($this->pool->getItem($this->location->key)->isHit())->toBeTrue();
    });
});

describe('cached locations', function () {
    it('reports a location it has never seen as uncached', function () {
        $runner = new DiscoveryRunner(
            $this->container,
            new DiscoveryCache(DiscoveryCacheStrategy::FULL, $this->pool),
            $this->pool,
            new DiscoveryLocations(testAppPath()),
        );

        $unknown = new DiscoveryLocation('Nowhere\\', testAppPath());

        expect($this->pool->getItem($unknown->key)->isHit())->toBeFalse();
        expect($runner->hasRun(DiscoveryPhase::Early))->toBeFalse();
    });
});
