<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Console\Commands\DiscoveryGenerateCommand;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\Discovery\DiscoveryLocations;
use Studiometa\Foehn\Discovery\DiscoveryRunner;
use Studiometa\Foehn\Discovery\HookDiscovery;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Tempest\Discovery\DiscoveryCache;
use Tempest\Discovery\DiscoveryCacheStrategy;
use Tests\Fixtures\App\CacheableHooks;

beforeEach(function () {
    wp_stub_reset();

    $this->config = new FoehnConfig(
        discoveryCacheStrategy: DiscoveryCacheStrategy::FULL,
        discoveryCachePath: sys_get_temp_dir() . '/foehn-tests/generate',
    );

    $this->pool = new ArrayAdapter();
    $this->cache = new DiscoveryCache($this->config->discoveryCacheStrategy, $this->pool);
    $this->container = bootTestContainer();

    // A directory holding a single hook class, so the command has something real to
    // scan and the cache it writes can be read back.
    $this->appPath = dirname(__DIR__, 2) . '/Fixtures/App';
    $this->locations = new DiscoveryLocations($this->appPath);
    $this->location = $this->locations->app();

    $this->runner = new DiscoveryRunner($this->container, $this->cache, $this->pool, $this->locations);

    $this->command = new DiscoveryGenerateCommand(new WpCli(), $this->cache, $this->pool, $this->runner, $this->config);
});

afterEach(fn() => tearDownTestContainer());

describe('discovery:generate', function () {
    it('writes an entry the runner can be restored from', function () {
        ($this->command)([], []);

        expect($this->pool->getItem($this->location->key)->isHit())->toBeTrue();

        $restored = $this->cache->restore($this->location);

        expect($restored)->toHaveKey(HookDiscovery::class);
        expect($restored[HookDiscovery::class][0]['attribute']->hook)->toBe('init');
        expect($restored[HookDiscovery::class][0]['className'])->toBe(CacheableHooks::class);
    });

    it('writes an entry for every location', function () {
        ($this->command)([], []);

        foreach ($this->locations->all() as $location) {
            expect($this->pool->getItem($location->key)->isHit())->toBeTrue();
        }
    });

    it('reports what it cached', function () {
        ($this->command)([], []);

        $logged = implode("\n", array_column(array_column(wp_stub_get_calls('wp_cli_log'), 'args'), 'message'));

        expect(wp_stub_get_calls('wp_cli_success'))->toHaveCount(1);
        expect($logged)->toContain('HookDiscovery: 1 items');
    });

    it('clears an existing cache when asked', function () {
        ($this->command)([], []);
        ($this->command)([], ['clear' => true]);

        $logged = implode("\n", array_column(array_column(wp_stub_get_calls('wp_cli_log'), 'args'), 'message'));

        expect($logged)->toContain('Clearing existing cache...');
    });

    it('refuses to generate when the requested strategy is none', function () {
        ($this->command)([], ['strategy' => 'none']);

        expect(wp_stub_get_calls('wp_cli_warning'))->toHaveCount(1);
        expect($this->pool->getItem($this->location->key)->isHit())->toBeFalse();
    });

    it('generates for a strategy given on the command line', function () {
        ($this->command)([], ['strategy' => 'partial']);

        $logged = implode("\n", array_column(array_column(wp_stub_get_calls('wp_cli_log'), 'args'), 'message'));

        expect($logged)->toContain("'partial' strategy");
    });

    it('defaults to a full cache when none is configured', function () {
        $config = new FoehnConfig(discoveryCacheStrategy: DiscoveryCacheStrategy::NONE);

        (new DiscoveryGenerateCommand(
            new WpCli(),
            new DiscoveryCache(DiscoveryCacheStrategy::NONE, $this->pool),
            $this->pool,
            $this->runner,
            $config,
        ))([], []);

        $logged = implode("\n", array_column(array_column(wp_stub_get_calls('wp_cli_log'), 'args'), 'message'));

        expect($logged)->toContain("'full' strategy");
        expect(wp_stub_get_calls('wp_cli_warning'))->toBeEmpty();
    });
});
