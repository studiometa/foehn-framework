<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Discovery\DiscoveryLocations;
use Studiometa\Foehn\Discovery\DiscoveryRunner;
use Studiometa\Foehn\Discovery\HookDiscovery;
use Studiometa\Foehn\Hooks\Cleanup\CleanHeadTags;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Tempest\Container\Container;
use Tempest\Container\GenericContainer;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryCache;
use Tempest\Discovery\DiscoveryCacheStrategy;
use Tempest\Discovery\DiscoveryItems;
use Tests\Fixtures\HookFixture;

/**
 * Write a cache entry holding one discovered hook.
 *
 * Every discovery has to be in the entry: Tempest treats a location whose cache is
 * missing any of them as unusable and scans it instead.
 */
function seedDiscoveryCache(DiscoveryCache $cache, ArrayAdapter $pool): void
{
    $location = testDiscoveryLocation();
    $discoveries = new DiscoveryRunner(
        createTestContainer(),
        new DiscoveryCache(DiscoveryCacheStrategy::NONE, $pool),
        $pool,
        new DiscoveryLocations(testAppPath()),
    )->getDiscoveries();

    $discoveries[HookDiscovery::class]->setItems(new DiscoveryItems()->addForLocation($location, [
        [
            'attribute' => new AsAction('init'),
            'className' => HookFixture::class,
            'methodName' => 'onInit',
        ],
    ]));

    $cache->store($location, array_values($discoveries));
}

function createTestContainer(): GenericContainer
{
    $container = new GenericContainer();
    $container->singleton(Container::class, fn() => $container);

    return $container;
}

describe('DiscoveryRunner integration', function () {
    beforeEach(fn() => wp_stub_reset());

    it('hasRun returns false by default for all phases', function () {
        $runner = testDiscoveryRunner(createTestContainer());

        expect($runner->hasRun('early'))->toBeFalse();
        expect($runner->hasRun('main'))->toBeFalse();
        expect($runner->hasRun('late'))->toBeFalse();
    });

    it('returns false for unknown phase', function () {
        expect(testDiscoveryRunner(createTestContainer())->hasRun('unknown'))->toBeFalse();
    });

    it('resolves every discovery class', function () {
        $discoveries = testDiscoveryRunner(createTestContainer())->getDiscoveries();

        expect($discoveries)->toHaveCount(count(DiscoveryRunner::getAllDiscoveryClasses()));

        foreach (DiscoveryRunner::getAllDiscoveryClasses() as $class) {
            expect($discoveries)->toHaveKey($class);
            expect($discoveries[$class])->toBeInstanceOf(Discovery::class);
        }
    });

    it('does not scan classes when no app path is given', function () {
        $runner = testDiscoveryRunner(createTestContainer());

        $runner->runEarlyDiscoveries();

        foreach ($runner->getDiscoveries() as $discovery) {
            expect($discovery->getItems())->toHaveCount(0);
        }
    });

    it('early phase is idempotent', function () {
        $runner = testDiscoveryRunner(createTestContainer());

        $runner->runEarlyDiscoveries();
        $runner->runEarlyDiscoveries();

        expect($runner->hasRun('early'))->toBeTrue();
    });

    it('restores items from a warm cache instead of scanning', function () {
        $pool = new ArrayAdapter();
        $cache = new DiscoveryCache(DiscoveryCacheStrategy::FULL, $pool);

        seedDiscoveryCache($cache, $pool);

        $runner = new DiscoveryRunner(createTestContainer(), $cache, $pool, new DiscoveryLocations(testAppPath()));
        $runner->runEarlyDiscoveries();

        /** @var HookDiscovery $hooks */
        $hooks = $runner->getDiscoveries()[HookDiscovery::class];

        // Nothing lives in the app directory, so an item can only have come from
        // the cache — and it was applied like any scanned one.
        expect($hooks->getItems())->toHaveCount(1);
        expect(wp_stub_get_calls('add_action'))->toHaveCount(1);
    });

    it('ignores what the cache holds when caching is disabled', function () {
        $pool = new ArrayAdapter();

        seedDiscoveryCache(new DiscoveryCache(DiscoveryCacheStrategy::FULL, $pool), $pool);

        $runner = new DiscoveryRunner(
            createTestContainer(),
            new DiscoveryCache(DiscoveryCacheStrategy::NONE, $pool),
            $pool,
            new DiscoveryLocations(testAppPath()),
        );

        $runner->runEarlyDiscoveries();

        expect($runner->getDiscoveries()[HookDiscovery::class]->getItems())->toHaveCount(0);
        expect(wp_stub_get_calls('add_action'))->toBeEmpty();
    });

    it('discovers opt-in hook classes from config', function () {
        $config = new FoehnConfig(hooks: [CleanHeadTags::class]);

        $runner = testDiscoveryRunner(createTestContainer(), testAppPath(), $config);

        $runner->runEarlyDiscoveries();

        // CleanHeadTags has #[AsAction] methods, and lives in the framework: it is
        // reached only because the config named it.
        expect($runner->getDiscoveries()[HookDiscovery::class]->getItems())->not->toHaveCount(0);
    });

    it('skips non-existent hook classes in config', function () {
        $runner = testDiscoveryRunner(
            createTestContainer(),
            testAppPath(),
            new FoehnConfig(hooks: ['NonExistent\\HookClass']),
        );

        $runner->runEarlyDiscoveries();

        expect($runner->hasRun('early'))->toBeTrue();
    });

    it('handles a missing config gracefully', function () {
        $runner = testDiscoveryRunner(createTestContainer(), testAppPath());

        $runner->runEarlyDiscoveries();

        expect($runner->hasRun('early'))->toBeTrue();
    });
});
