<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\AcfConfig;
use Studiometa\Foehn\Config\ConfigLoader;
use Studiometa\Foehn\Config\TimberConfig;
use Tempest\Discovery\DiscoveryLocation;

beforeEach(function () {
    wp_stub_reset();

    $this->container = bootTestContainer();
    $this->loader = new ConfigLoader($this->container);
    $this->location = new DiscoveryLocation('Tests\\Fixtures\\Config\\', dirname(__DIR__, 2) . '/Fixtures/Config');
});

afterEach(fn() => tearDownTestContainer());

describe('ConfigLoader', function () {
    it('registers what a config file returns', function () {
        $this->loader->load([$this->location]);

        expect($this->container->get(TimberConfig::class)->templatesDir)->toBe(['production-views']);
    });

    it('skips a file that returns something other than an object', function () {
        // nothing.config.php returns a string; registering it would make the next
        // `get()` for whatever it was meant to configure fail somewhere unrelated.
        expect(fn() => $this->loader->load([$this->location]))->not->toThrow(Throwable::class);
    });

    it('reads only the files named for the current environment', function () {
        $GLOBALS['wp_stub_environment_type'] = 'staging';

        $this->loader->load([$this->location]);

        expect($this->container->get(AcfConfig::class)->transformFields)->toBeFalse();
        // The production file exists but does not apply, so the plain one stands.
        expect($this->container->get(TimberConfig::class)->templatesDir)->toBe(['app-views']);
    });

    it('lets the environment file win over the plain one', function () {
        $GLOBALS['wp_stub_environment_type'] = 'production';

        $this->loader->load([$this->location]);

        expect($this->container->get(TimberConfig::class)->templatesDir)->toBe(['production-views']);
    });

    it('lets the project override what a package ships', function () {
        $vendor = testVendorLocation('Vendor\\Package\\');

        file_put_contents(
            $vendor->path . '/timber.config.php',
            "<?php return new \\Studiometa\\Foehn\\Config\\TimberConfig(templatesDir: ['vendor-views']);",
        );

        try {
            $this->loader->load([$this->location, $vendor]);

            expect($this->container->get(TimberConfig::class)->templatesDir)->not->toBe(['vendor-views']);
        } finally {
            unlink($vendor->path . '/timber.config.php');
        }
    });

    it('finds nothing in a directory that does not exist', function () {
        expect($this->loader->find([new DiscoveryLocation('App\\', testAppPath())]))->toBe([]);
    });
});
