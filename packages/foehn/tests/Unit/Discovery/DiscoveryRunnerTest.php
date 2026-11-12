<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Discovery\CliCommandDiscovery;
use Studiometa\Foehn\Discovery\DiscoveryLocations;
use Studiometa\Foehn\Discovery\DiscoveryPhase;
use Studiometa\Foehn\Discovery\DiscoveryProblems;
use Studiometa\Foehn\Discovery\DiscoveryRunner;
use Studiometa\Foehn\Discovery\HookDiscovery;
use Studiometa\Foehn\Discovery\PostTypeDiscovery;
use Studiometa\Foehn\Discovery\RestRouteDiscovery;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryCache;
use Tempest\Discovery\DiscoveryCacheStrategy;
use Tests\Fixtures\CustomDiscovery\DefaultPhaseFixtureDiscovery;
use Tests\Fixtures\CustomDiscovery\LateFixtureDiscovery;
use Tests\Fixtures\ProblemApp\Article;
use Tests\Fixtures\ProblemApp\Gallery;

describe('discovery classes are discovered', function () {
    beforeEach(function () {
        wp_stub_reset();
        $this->container = bootTestContainer();

        LateFixtureDiscovery::$applied = 0;
        DefaultPhaseFixtureDiscovery::$applied = 0;
    });

    afterEach(fn() => tearDownTestContainer());

    it('finds the framework discoveries without any of them being listed', function () {
        $discoveries = testDiscoveryRunner($this->container, testFixturePath('CustomDiscovery'))->getDiscoveries();

        // Nothing enumerates these any more: they are found because they implement
        // Discovery and sit in a scanned location, exactly like a third-party one.
        expect($discoveries)->toHaveKey(HookDiscovery::class);
        expect($discoveries)->toHaveKey(PostTypeDiscovery::class);
        expect($discoveries)->toHaveKey(RestRouteDiscovery::class);
        expect($discoveries[HookDiscovery::class])->toBeInstanceOf(Discovery::class);
    });

    it('finds a discovery that ships with neither the framework nor a package', function () {
        $discoveries = testDiscoveryRunner($this->container, testFixturePath('CustomDiscovery'))->getDiscoveries();

        expect($discoveries)->toHaveKey(LateFixtureDiscovery::class);
    });

    it('does not expose the pass that finds the discovery classes', function () {
        $discoveries = testDiscoveryRunner($this->container, testFixturePath('CustomDiscovery'))->getDiscoveries();

        expect($discoveries)->not->toHaveKey(Tempest\Discovery\DiscoveryDiscovery::class);
    });

    it('applies a custom discovery at the phase its attribute declares', function () {
        $runner = testDiscoveryRunner($this->container, testFixturePath('CustomDiscovery'));

        $runner->runEarlyDiscoveries();
        $runner->runMainDiscoveries();

        expect(LateFixtureDiscovery::$applied)->toBe(0);

        $runner->runLateDiscoveries();

        expect(LateFixtureDiscovery::$applied)->toBe(1);
    });

    it('applies a discovery without the attribute in the main phase', function () {
        $runner = testDiscoveryRunner($this->container, testFixturePath('CustomDiscovery'));

        $runner->runEarlyDiscoveries();

        expect(DefaultPhaseFixtureDiscovery::$applied)->toBe(0);

        $runner->runMainDiscoveries();

        expect(DefaultPhaseFixtureDiscovery::$applied)->toBe(1);
    });

    it('applies each discovery once', function () {
        $runner = testDiscoveryRunner($this->container, testFixturePath('CustomDiscovery'));

        $runner->runLateDiscoveries();
        $runner->runLateDiscoveries();

        expect(LateFixtureDiscovery::$applied)->toBe(1);
    });

    it('registers the framework CLI commands in the early phase', function () {
        $runner = testDiscoveryRunner($this->container, testFixturePath('CustomDiscovery'));

        $runner->runEarlyDiscoveries();

        // CliCommandDiscovery declares #[AsDiscovery(phase: Early)] and the framework
        // is a scanned location, so its own commands are found from the fixture app.
        expect($runner->getDiscoveries()[CliCommandDiscovery::class]->getItems())->not->toHaveCount(0);
    });
});

describe('DiscoveryRunner phase execution', function () {
    beforeEach(function () {
        wp_stub_reset();
        $this->container = bootTestContainer();
    });

    afterEach(fn() => tearDownTestContainer());

    it('runs phases and sets hasRun flag', function () {
        $runner = testDiscoveryRunner($this->container, testFixturePath('CustomDiscovery'));

        expect($runner->hasRun(DiscoveryPhase::Early))->toBeFalse();
        expect($runner->hasRun(DiscoveryPhase::Main))->toBeFalse();
        expect($runner->hasRun(DiscoveryPhase::Late))->toBeFalse();

        $runner->runEarlyDiscoveries();
        expect($runner->hasRun(DiscoveryPhase::Early))->toBeTrue();
        expect($runner->hasRun(DiscoveryPhase::Main))->toBeFalse();

        $runner->runMainDiscoveries();
        expect($runner->hasRun(DiscoveryPhase::Main))->toBeTrue();
        expect($runner->hasRun(DiscoveryPhase::Late))->toBeFalse();

        $runner->runLateDiscoveries();
        expect($runner->hasRun(DiscoveryPhase::Late))->toBeTrue();
    });

    it('does not re-run a phase that has already run', function () {
        $runner = testDiscoveryRunner($this->container, testFixturePath('App'));

        $runner->runEarlyDiscoveries();

        // Reset stubs after first run
        wp_stub_reset();

        // Running again should be a no-op
        $runner->runEarlyDiscoveries();

        // No new add_action calls should have been recorded
        expect(wp_stub_get_calls('add_action'))->toBeEmpty();
    });
});

/**
 * Run a callback and return the E_USER_WARNING messages it raised.
 *
 * @return list<string>
 */
function userWarnings(Closure $callback): array
{
    $warnings = [];

    set_error_handler(function (int $errno, string $errstr) use (&$warnings): bool {
        if ($errno === E_USER_WARNING) {
            $warnings[] = $errstr;
        }

        return true;
    });

    try {
        $callback();
    } finally {
        restore_error_handler();
    }

    return $warnings;
}

describe('DiscoveryRunner problems', function () {
    beforeEach(function () {
        wp_stub_reset();
        $this->container = bootTestContainer();
    });

    afterEach(fn() => tearDownTestContainer());

    it('records a class it skips, and registers the others', function () {
        $runner = testDiscoveryRunner($this->container, testFixturePath('ProblemApp'));

        $classes = array_column(
            iterator_to_array($runner->getDiscoveries()[PostTypeDiscovery::class]->getItems(), false),
            'className',
        );

        expect($classes)->toContain(Article::class);
        expect($classes)->not->toContain(Gallery::class);
        expect($runner->problems())->toBe([[
            'class' => Gallery::class,
            'attribute' => '#[AsPostType]',
            'reason' =>
                Gallery::class
                    . ' has #[AsPostType] but does not extend Timber\Post, so the post type is not registered.',
            'fix' => 'Declare ' . Gallery::class . ' with `extends \Timber\Post`.',
        ]]);
    });

    it('restores what it recorded from a warm discovery cache', function () {
        $pool = new ArrayAdapter();
        $locations = new DiscoveryLocations(testFixturePath('ProblemApp'));
        $runner = fn(): DiscoveryRunner => new DiscoveryRunner(
            bootTestContainer(),
            new DiscoveryCache(DiscoveryCacheStrategy::FULL, $pool),
            $pool,
            $locations,
        );

        $runner()->getDiscoveries();

        // A second scan would find the same class and prove nothing. Changing the
        // stored record does: only a runner reading the cache can return it.
        $item = $pool->getItem($locations->app()->key);
        $entry = $item->get();
        $entry[DiscoveryProblems::class][0]['reason'] = 'Read from the cache.';
        $pool->save($item->set($entry));

        expect(array_column($runner()->problems(), 'reason'))->toBe(['Read from the cache.']);
    });

    it('raises a warning with the fix when debug is enabled', function () {
        $runner = testDiscoveryRunner($this->container, testFixturePath('ProblemApp'), new FoehnConfig(debug: true));

        $warnings = userWarnings(fn() => $runner->getDiscoveries());

        expect($warnings)->toHaveCount(1);
        expect($warnings[0])->toStartWith('[Foehn] ' . Gallery::class . ' has #[AsPostType]');
        expect($warnings[0])->toEndWith('Fix: Declare ' . Gallery::class . ' with `extends \Timber\Post`.');
    });

    it('raises no warning when debug is disabled', function () {
        $runner = testDiscoveryRunner($this->container, testFixturePath('ProblemApp'), new FoehnConfig(debug: false));

        expect(userWarnings(fn() => $runner->getDiscoveries()))->toBe([]);
        expect($runner->problems())->toHaveCount(1);
    });

    it('records each problem once when discovery:generate scans again in the same process', function () {
        $pool = new ArrayAdapter();
        $cache = new DiscoveryCache(DiscoveryCacheStrategy::FULL, $pool);
        $locations = new DiscoveryLocations(testFixturePath('ProblemApp'));
        $runner = new DiscoveryRunner($this->container, $cache, $pool, $locations);

        $runner->getDiscoveries();
        $runner->warmCache($cache);

        // DiscoveryProblems is one instance per container: a second build must
        // start it empty, or the cache it writes holds every problem twice.
        expect($runner->problems())->toHaveCount(1);
        expect($pool->getItem($locations->app()->key)->get()[DiscoveryProblems::class])->toHaveCount(1);
    });

    it('records an opt-in hook class that does not exist', function () {
        $runner = testDiscoveryRunner(
            $this->container,
            testFixturePath('ProblemApp'),
            new FoehnConfig(hooks: ['App\Hooks\Missing']),
        );

        $problem = array_find(
            $runner->problems(),
            static fn(array $problem): bool => $problem['class'] === 'App\Hooks\Missing',
        );

        expect($problem['attribute'])->toBe('hooks');
        expect($problem['reason'])
            ->toBe('FoehnConfig hooks names App\Hooks\Missing, which does not exist, so its hooks are not registered.');
        expect($problem['fix'])
            ->toBe('Fix the class name in `hooks` in foehn.config.php, or remove App\Hooks\Missing from it.');
    });
});
