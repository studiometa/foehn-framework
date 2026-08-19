<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Console\Commands\DiscoveryStatusCommand;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\Discovery\DiscoveryLocations;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Tempest\Discovery\DiscoveryCacheStrategy;

/**
 * Everything the command wrote, as one string.
 */
function statusOutput(): string
{
    return implode("\n", [
        ...array_column(array_column(wp_stub_get_calls('wp_cli_log'), 'args'), 'message'),
        ...array_column(array_column(wp_stub_get_calls('wp_cli_line'), 'args'), 'message'),
    ]);
}

beforeEach(function () {
    wp_stub_reset();

    $this->pool = new ArrayAdapter();
    $this->locations = new DiscoveryLocations(testFixturePath('App'));

    $this->status = fn(DiscoveryCacheStrategy $strategy): DiscoveryStatusCommand => new DiscoveryStatusCommand(
        new WpCli(),
        $this->pool,
        $this->locations,
        new FoehnConfig(discoveryCacheStrategy: $strategy),
    );
});

describe('discovery:status', function () {
    it('reports the strategy and the cache path', function () {
        ($this->status)(DiscoveryCacheStrategy::FULL)([], []);

        $output = statusOutput();

        expect($output)->toContain('Strategy: full');
        expect($output)->toContain('Enabled: Yes');
        expect($output)->toContain('Cache path:');
    });

    it('names every location, and marks a cold one', function () {
        ($this->status)(DiscoveryCacheStrategy::FULL)([], []);

        $output = statusOutput();

        foreach ($this->locations->all() as $location) {
            expect($output)->toContain($this->locations->label($location));
        }

        expect($output)->toContain('Locations cached: 0/' . count($this->locations->all()));
    });

    it('tells two locations sharing a namespace apart', function () {
        // The ACF package maps the same PSR-4 prefix as the framework, so the
        // namespace alone names both of them.
        ($this->status)(DiscoveryCacheStrategy::FULL)([], []);

        expect(statusOutput())->toContain('Studiometa\\Foehn\\ (');
    });

    it('warns while the cache is incomplete', function () {
        ($this->status)(DiscoveryCacheStrategy::FULL)([], []);

        expect(wp_stub_get_calls('wp_cli_warning'))->toHaveCount(1);
        expect(wp_stub_get_calls('wp_cli_success'))->toBeEmpty();
    });

    it('reports a warm cache as valid', function () {
        foreach ($this->locations->all() as $location) {
            $this->pool->save($this->pool->getItem($location->key)->set([]));
        }

        ($this->status)(DiscoveryCacheStrategy::FULL)([], []);

        expect(wp_stub_get_calls('wp_cli_success'))->toHaveCount(1);
        expect(statusOutput())->toContain(sprintf('Locations cached: %1$d/%1$d', count($this->locations->all())));
    });

    it('says caching is off rather than warning about it', function () {
        ($this->status)(DiscoveryCacheStrategy::NONE)([], []);

        expect(statusOutput())->toContain('Discovery cache is disabled');
        expect(wp_stub_get_calls('wp_cli_warning'))->toBeEmpty();
        expect(wp_stub_get_calls('wp_cli_success'))->toBeEmpty();
    });
});
