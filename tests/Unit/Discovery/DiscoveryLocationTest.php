<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\DiscoveryLocation;

describe('DiscoveryLocation', function () {
    it('creates an app location', function () {
        $location = DiscoveryLocation::app('App\\', '/path/to/app');

        expect($location->namespace)->toBe('App\\');
        expect($location->path)->toBe('/path/to/app');
        expect($location->isVendor)->toBeFalse();
    });

    it('creates a vendor location', function () {
        $location = DiscoveryLocation::vendor('Vendor\\Package\\', '/path/to/vendor');

        expect($location->namespace)->toBe('Vendor\\Package\\');
        expect($location->path)->toBe('/path/to/vendor');
        expect($location->isVendor)->toBeTrue();
    });

    it('creates a location via constructor', function () {
        $location = new DiscoveryLocation('Custom\\', '/custom/path', isVendor: true);

        expect($location->namespace)->toBe('Custom\\');
        expect($location->path)->toBe('/custom/path');
        expect($location->isVendor)->toBeTrue();
    });

    it('defaults isVendor to false', function () {
        $location = new DiscoveryLocation('Test\\', '/test');

        expect($location->isVendor)->toBeFalse();
    });
});
