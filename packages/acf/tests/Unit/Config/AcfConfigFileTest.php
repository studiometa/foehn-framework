<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\AcfConfig;
use Studiometa\Foehn\Config\ConfigLoader;
use Tempest\Discovery\DiscoveryLocation;

beforeEach(function () {
    $this->container = bootTestContainer();
    $this->location = new DiscoveryLocation('Studiometa\\Foehn\\', dirname(__DIR__, 3) . '/src');
});

afterEach(fn() => tearDownTestContainer());

describe('the package config default', function () {
    it('supplies an AcfConfig without the Kernel registering one', function () {
        // This is the whole mechanism the split rests on: a package ships its
        // defaults as a config file, and needs no service-provider concept.
        new ConfigLoader($this->container)->load([$this->location]);

        expect($this->container->get(AcfConfig::class))->toBeInstanceOf(AcfConfig::class);
        expect($this->container->get(AcfConfig::class)->transformFields)->toBeTrue();
    });

    it('is overridden by a project config file', function () {
        $app = new DiscoveryLocation('Tests\\Fixtures\\Config\\', dirname(__DIR__, 2) . '/Fixtures/Config');

        // ConfigLoader reads vendor locations before app ones. The package's own
        // path is not under vendor/ in this monorepo, so the order is forced
        // here to assert what a real install does.
        new ConfigLoader($this->container)->load([$this->location]);
        new ConfigLoader($this->container)->load([$app]);

        expect($this->container->get(AcfConfig::class)->transformFields)->toBeFalse();
    });
});
