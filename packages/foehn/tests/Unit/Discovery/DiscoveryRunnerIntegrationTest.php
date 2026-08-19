<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Discovery\DiscoveryLocations;
use Studiometa\Foehn\Discovery\DiscoveryPhase;
use Studiometa\Foehn\Discovery\DiscoveryRunner;
use Studiometa\Foehn\Discovery\HookDiscovery;
use Studiometa\Foehn\Hooks\Cleanup\CleanHeadTags;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Tempest\Container\Container;
use Tempest\Container\GenericContainer;
use Tempest\Discovery\DiscoveryCache;
use Tempest\Discovery\DiscoveryCacheStrategy;
use Tempest\Discovery\DiscoveryConfig;
use Tempest\Discovery\DiscoveryDiscovery;
use Tempest\Discovery\DiscoveryItems;
use Tests\Fixtures\HookFixture;

/**
 * Write a cache entry holding one discovered hook.
 *
 * Two discoveries have to be in it. Which discovery classes exist is itself
 * discovered, so an entry without DiscoveryDiscovery's own items names no
 * discovery to restore — and Tempest treats a location whose entry is missing any
 * of them as unusable and scans it instead.
 */
function seedDiscoveryCache(DiscoveryCache $cache, ArrayAdapter $pool): void
{
    $location = testDiscoveryLocation();

    $classes = new DiscoveryDiscovery(new DiscoveryConfig());
    $classes->setItems(new DiscoveryItems()->addForLocation($location, [HookDiscovery::class]));

    $hooks = new HookDiscovery(createTestContainer());
    $hooks->setItems(new DiscoveryItems()->addForLocation($location, [
        [
            'attribute' => new AsAction('init'),
            'className' => HookFixture::class,
            'methodName' => 'onInit',
        ],
    ]));

    $cache->store($location, [$classes, $hooks]);
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

        expect($runner->hasRun(DiscoveryPhase::Early))->toBeFalse();
        expect($runner->hasRun(DiscoveryPhase::Main))->toBeFalse();
        expect($runner->hasRun(DiscoveryPhase::Late))->toBeFalse();
    });

    it('finds no discovery at all outside a scanned location', function () {
        // The temporary app path sits outside any Composer project, so the only
        // location is the app directory itself. The discoveries live in the
        // framework package, which is not among them — and nothing lists them.
        expect(testDiscoveryRunner(createTestContainer())->getDiscoveries())->toBe([]);
    });

    it('registers nothing when no app path is given', function () {
        $runner = testDiscoveryRunner(createTestContainer());

        $runner->runEarlyDiscoveries();

        expect(wp_stub_get_calls('add_action'))->toBeEmpty();
    });

    it('early phase is idempotent', function () {
        $runner = testDiscoveryRunner(createTestContainer());

        $runner->runEarlyDiscoveries();
        $runner->runEarlyDiscoveries();

        expect($runner->hasRun(DiscoveryPhase::Early))->toBeTrue();
    });

    it('restores items from a warm cache instead of scanning', function () {
        $pool = new ArrayAdapter();
        $cache = new DiscoveryCache(DiscoveryCacheStrategy::FULL, $pool);

        seedDiscoveryCache($cache, $pool);

        $runner = new DiscoveryRunner(createTestContainer(), $cache, $pool, new DiscoveryLocations(testAppPath()));
        $runner->runEarlyDiscoveries();

        /** @var HookDiscovery $hooks */
        $hooks = $runner->getDiscoveries()[HookDiscovery::class];

        // Nothing lives in the app directory, so neither the discovery class nor its
        // item can have come from a scan — and the item was applied like any
        // scanned one.
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

        expect($runner->getDiscoveries())->toBe([]);
        expect(wp_stub_get_calls('add_action'))->toBeEmpty();
    });

    it('discovers opt-in hook classes from config', function () {
        $config = new FoehnConfig(hooks: [CleanHeadTags::class]);

        $runner = testDiscoveryRunner(createTestContainer(), testFixturePath('CustomDiscovery'), $config);

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

        expect($runner->hasRun(DiscoveryPhase::Early))->toBeTrue();
    });

    it('handles a missing config gracefully', function () {
        $runner = testDiscoveryRunner(createTestContainer(), testAppPath());

        $runner->runEarlyDiscoveries();

        expect($runner->hasRun(DiscoveryPhase::Early))->toBeTrue();
    });
});
