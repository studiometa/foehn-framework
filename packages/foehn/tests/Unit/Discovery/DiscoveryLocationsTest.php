<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\DiscoveryLocations;

describe('DiscoveryLocations::label', function () {
    it('names a location by its namespace', function () {
        $locations = new DiscoveryLocations(testFixturePath('App'));

        $app = $locations->app();

        expect($locations->label($app))->toBe($app->namespace);
    });

    it('distinguishes two locations that share a namespace', function () {
        // studiometa/foehn-acf maps the same PSR-4 prefix as the framework, so a
        // project moving to it changes one Composer requirement and no imports.
        // Diagnostics still have to tell the two apart.
        $locations = new DiscoveryLocations(testFixturePath('App'));

        $sharing = array_values(array_filter(
            $locations->all(),
            static fn($location): bool => $location->namespace === 'Studiometa\\Foehn\\',
        ));

        expect($sharing)->toHaveCount(2);
        expect($locations->label($sharing[0]))->not->toBe($locations->label($sharing[1]));
        expect($locations->label($sharing[0]))->toStartWith('Studiometa\\Foehn\\ (');
    });
});
