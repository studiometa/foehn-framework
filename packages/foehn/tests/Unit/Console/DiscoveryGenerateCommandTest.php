<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Console\Commands\DiscoveryGenerateCommand;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\Discovery\BlockDiscovery;
use Studiometa\Foehn\Discovery\DiscoveryCache;
use Studiometa\Foehn\Discovery\DiscoveryLocation;
use Studiometa\Foehn\Discovery\MenuDiscovery;
use Tempest\Discovery\DiscoveryCacheStrategy;
use Tests\Fixtures\BlockFixture;
use Tests\Fixtures\MenuFixture;

beforeEach(function () {
    wp_stub_reset();

    $this->cacheDir = sys_get_temp_dir() . '/foehn-generate-' . bin2hex(random_bytes(6));
    mkdir($this->cacheDir, 0o755, true);

    $this->config = new FoehnConfig(
        discoveryCacheStrategy: DiscoveryCacheStrategy::FULL,
        discoveryCachePath: $this->cacheDir,
    );

    $this->cache = new DiscoveryCache($this->config);
    $this->container = bootTestContainer();

    // Two discoveries arrive with items already found, the rest stay empty —
    // collectDiscoveryData() has to keep the first two and skip the others.
    $location = DiscoveryLocation::app('App\\', '/tmp/test-app');

    $menus = new MenuDiscovery();
    $menus->discover($location, new ReflectionClass(MenuFixture::class));

    $blocks = new BlockDiscovery();
    $blocks->discover($location, new ReflectionClass(BlockFixture::class));

    $this->container->singleton(MenuDiscovery::class, fn() => $menus);
    $this->container->singleton(BlockDiscovery::class, fn() => $blocks);

    $this->command = new DiscoveryGenerateCommand(new WpCli(), $this->cache, $this->config, $this->container);
});

afterEach(function () {
    tearDownTestContainer();
    exec('rm -rf ' . escapeshellarg($this->cacheDir));
});

describe('discovery:generate', function () {
    it('writes a cache file the discoveries can be restored from', function () {
        ($this->command)([], []);

        expect($this->cacheDir . '/discoveries.php')->toBeFile();

        $restored = $this->cache->restore();

        expect($restored)->toHaveKey(MenuDiscovery::class);
        expect($restored)->toHaveKey(BlockDiscovery::class);

        $menus = new MenuDiscovery();
        $menus->restoreFromCache($restored[MenuDiscovery::class]);

        expect($menus->getItems()->all()[0]['attribute']->location)->toBe('primary');
    });

    it('skips discoveries that found nothing', function () {
        ($this->command)([], []);

        $restored = $this->cache->restore();

        // Only the two seeded discoveries have items; the other 17 are omitted.
        expect($restored)->toHaveCount(2);
    });

    it('reports what it cached', function () {
        ($this->command)([], []);

        $logged = implode("\n", array_column(array_column(wp_stub_get_calls('wp_cli_log'), 'args'), 'message'));

        expect(wp_stub_get_calls('wp_cli_success'))->toHaveCount(1);
        expect($logged)->toContain('MenuDiscovery: 1 items');
        expect($logged)->toContain('BlockDiscovery: 1 items');
    });

    it('stores the strategy it generated for', function () {
        ($this->command)([], []);

        expect($this->cache->isValid())->toBeTrue();
    });

    it('clears an existing cache when asked', function () {
        ($this->command)([], []);
        ($this->command)([], ['clear' => true]);

        $logged = implode("\n", array_column(array_column(wp_stub_get_calls('wp_cli_log'), 'args'), 'message'));

        expect($logged)->toContain('Clearing existing cache...');
        expect($this->cache->restore())->toHaveCount(2);
    });

    it('refuses to generate when the requested strategy is none', function () {
        ($this->command)([], ['strategy' => 'none']);

        expect(wp_stub_get_calls('wp_cli_warning'))->toHaveCount(1);
        expect(file_exists($this->cacheDir . '/discoveries.php'))->toBeFalse();
    });

    it('generates for a strategy given on the command line', function () {
        ($this->command)([], ['strategy' => 'partial']);

        expect($this->cacheDir . '/discoveries.php')->toBeFile();
        expect($this->cache->getStrategy())->toBe(DiscoveryCacheStrategy::FULL);
    });

    it('defaults to a full cache when none is configured', function () {
        $config = new FoehnConfig(
            discoveryCacheStrategy: DiscoveryCacheStrategy::NONE,
            discoveryCachePath: $this->cacheDir,
        );

        (new DiscoveryGenerateCommand(new WpCli(), new DiscoveryCache($config), $config, $this->container))([], []);

        expect($this->cacheDir . '/discoveries.php')->toBeFile();
        expect(wp_stub_get_calls('wp_cli_warning'))->toBeEmpty();
    });
});
