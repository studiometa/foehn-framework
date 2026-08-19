<?php

declare(strict_types=1);

use Studiometa\Foehn\Console\Commands\DiscoveryListCommand;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\Discovery\DiscoveryLocations;
use Studiometa\Foehn\Discovery\DiscoveryRunner;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Tempest\Discovery\DiscoveryCache;
use Tempest\Discovery\DiscoveryCacheStrategy;
use Tests\Fixtures\App\CacheableHooks;

/**
 * Everything the command wrote, as one string.
 */
function listedOutput(): string
{
    return implode("\n", [
        ...array_column(array_column(wp_stub_get_calls('wp_cli_log'), 'args'), 'message'),
        ...array_column(array_column(wp_stub_get_calls('wp_cli_line'), 'args'), 'message'),
    ]);
}

function listCommand(DiscoveryCacheStrategy $strategy = DiscoveryCacheStrategy::NONE): DiscoveryListCommand
{
    // The fixture app holds one class with an #[AsAction], so the listing has a
    // real item to describe and the framework's own discoveries are in scope.
    $locations = new DiscoveryLocations(dirname(__DIR__, 2) . '/Fixtures/App');
    $pool = new ArrayAdapter();

    $runner = new DiscoveryRunner(bootTestContainer(), new DiscoveryCache($strategy, $pool), $pool, $locations);

    return new DiscoveryListCommand(new WpCli(), $runner, $locations);
}

beforeEach(fn() => wp_stub_reset());

afterEach(fn() => tearDownTestContainer());

describe('discovery:list', function () {
    it('lists a discovery with the items it found', function () {
        listCommand()([], []);

        $output = listedOutput();

        expect($output)->toContain('HookDiscovery (early)');
        expect($output)->toContain(CacheableHooks::class);
        expect($output)->toContain('AsAction(hook: init');
    });

    it('lists a discovery that found nothing rather than hiding it', function () {
        listCommand()([], []);

        // "PostTypeDiscovery — 0 items" is the answer to most "why is my post type
        // missing" questions, and a listing that omits it answers nothing.
        expect(listedOutput())->toContain('PostTypeDiscovery (main) — 0 items');
    });

    it('reports how many discoveries found something', function () {
        listCommand()([], []);

        expect(listedOutput())->toMatch('/\d+ discoveries with items, \d+ empty\./');
    });

    it('reports each location as scanned or cached', function () {
        listCommand()([], []);

        expect(listedOutput())->toContain('(scanned)');
    });

    it('reports a location restored from the cache as cached', function () {
        $locations = new DiscoveryLocations(dirname(__DIR__, 2) . '/Fixtures/App');
        $pool = new ArrayAdapter();
        $cache = new DiscoveryCache(DiscoveryCacheStrategy::FULL, $pool);

        // The first runner scans and writes; a second one reading the same pool has
        // nothing left to scan. Asking the pool cannot tell those apart — by then
        // both locations are in it.
        new DiscoveryRunner(bootTestContainer(), $cache, $pool, $locations)->getDiscoveries();

        $runner = new DiscoveryRunner(bootTestContainer(), $cache, $pool, $locations);

        (new DiscoveryListCommand(new WpCli(), $runner, $locations))([], []);

        expect(listedOutput())->toContain('(cached)');
        expect(listedOutput())->not->toContain('(scanned)');
    });

    it('filters by discovery short name', function () {
        listCommand()([], ['discovery' => 'Hook']);

        $output = listedOutput();

        expect($output)->toContain('HookDiscovery');
        expect($output)->not->toContain('PostTypeDiscovery');
    });

    it('filters by discovery class name', function () {
        listCommand()([], ['discovery' => Studiometa\Foehn\Discovery\HookDiscovery::class]);

        expect(listedOutput())->toContain('HookDiscovery');
    });

    it('errors on a discovery name that matches nothing', function () {
        listCommand()([], ['discovery' => 'Nonsense']);

        expect(wp_stub_get_calls('wp_cli_error'))->toHaveCount(1);
        expect(listedOutput())->not->toContain('HookDiscovery');
    });

    it('filters by location namespace', function () {
        listCommand()([], ['location' => 'Tests']);

        // The framework's own location is out of scope, so its CLI commands and Twig
        // extensions are not in the listing even though the discoveries still are.
        expect(listedOutput())->toContain('CliCommandDiscovery (early) — 0 items');
    });

    it('errors on a location namespace that matches nothing', function () {
        listCommand()([], ['location' => 'Nowhere']);

        expect(wp_stub_get_calls('wp_cli_error'))->toHaveCount(1);
    });

    it('prints counts alone under --format=count', function () {
        listCommand()([], ['format' => 'count']);

        $output = listedOutput();

        expect($output)->toMatch('/HookDiscovery\s+1/');
        expect($output)->not->toContain(CacheableHooks::class);
    });

    it('prints a document under --format=json', function () {
        listCommand()([], ['format' => 'json']);

        $decoded = json_decode(listedOutput(), true);

        expect($decoded)->toHaveKeys(['locations', 'discoveries']);
        expect($decoded['locations'][0])->toHaveKeys(['namespace', 'path', 'origin']);

        $hooks = array_values(array_filter(
            $decoded['discoveries'],
            static fn(array $discovery): bool => $discovery['name'] === 'HookDiscovery',
        ));

        expect($hooks[0]['phase'])->toBe('early');
        expect($hooks[0]['count'])->toBe(1);
        expect($hooks[0]['items'][0]['values']['className'])->toBe(CacheableHooks::class);
        expect($hooks[0]['items'][0]['attribute'])->toContain('AsAction');
    });

    it('errors on an unknown format', function () {
        listCommand()([], ['format' => 'yaml']);

        expect(wp_stub_get_calls('wp_cli_error'))->toHaveCount(1);
        expect(listedOutput())->toBe('');
    });

    it('registers nothing', function () {
        listCommand()([], []);

        // Listing discovers; applying is what registers, and this must not.
        expect(wp_stub_get_calls('add_action'))->toBeEmpty();
        expect(wp_stub_get_calls('register_post_type'))->toBeEmpty();
    });
});
