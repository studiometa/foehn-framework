<?php

declare(strict_types=1);

use Studiometa\Foehn\Places\Config\PlacesConfig;
use Studiometa\Foehn\Places\Models\Place;
use Studiometa\Foehn\Places\Seo\PlaceJsonLd;
use Studiometa\Foehn\Places\Tests\Fixtures\TestPlace;

function bootPlaces(PlacesConfig $config): void
{
    $container = bootTestContainer();
    $container->singleton(PlacesConfig::class, static fn() => $config);
}

beforeEach(fn() => wp_stub_reset());
afterEach(fn() => tearDownTestContainer());

$values = [
    'latitude' => '45.764',
    'longitude' => '4.8357',
    'street' => '1 rue de la République',
    'postal_code' => '69001',
    'city' => 'Lyon',
    'country' => 'France',
    'phone' => '+33 4 00 00 00 00',
    'email' => 'lyon@example.com',
    'website' => 'https://example.com/lyon',
];

describe('Place', function () use ($values) {
    it('is designed for extension', function () {
        expect(new ReflectionClass(Place::class)->isFinal())->toBeFalse();
    });

    it('reads its coordinates and address', function () use ($values) {
        bootPlaces(new PlacesConfig());
        $place = TestPlace::with(1, $values);

        expect($place->coordinates()?->toLngLat())
            ->toBe([4.8357, 45.764])
            ->and($place->isLocatable())
            ->toBeTrue()
            ->and($place->addressLines())
            ->toBe(['1 rue de la République', '69001 Lyon', 'France']);
    });

    it('is not locatable without valid coordinates, never at [0, 0]', function () {
        bootPlaces(new PlacesConfig());

        expect(TestPlace::with(1, ['latitude' => '', 'longitude' => ''])->coordinates())->toBeNull();
        expect(TestPlace::with(1, ['latitude' => '120', 'longitude' => '4'])->isLocatable())->toBeFalse();
    });

    it('reads its contacts when contact fields are on', function () use ($values) {
        bootPlaces(new PlacesConfig());
        $place = TestPlace::with(1, $values);

        expect($place->phone())
            ->toBe('+33 4 00 00 00 00')
            ->and($place->phoneUri())
            ->toBe('tel:+33400000000')
            ->and($place->email())
            ->toBe('lyon@example.com')
            ->and($place->website())
            ->toBe('https://example.com/lyon');
    });

    it('has no contacts when contact fields are off', function () use ($values) {
        bootPlaces(new PlacesConfig(contactFields: false));
        $place = TestPlace::with(1, $values);

        expect($place->phone())->toBeNull()->and($place->email())->toBeNull()->and($place->website())->toBeNull();
    });
});

describe('PlaceJsonLd', function () use ($values) {
    it('describes a place with the configured type, address, geo and contacts', function () use ($values) {
        bootPlaces($config = new PlacesConfig(schemaType: 'LocalBusiness'));
        $post = new WP_Post();
        $post->ID = 1;
        $GLOBALS['wp_stub_post_fields'][1] = ['post_title' => 'Lyon'];

        $node = new PlaceJsonLd($config)->node(TestPlace::with(1, $values), $post);

        expect($node['@type'])
            ->toBe('LocalBusiness')
            ->and($node['name'])
            ->toBe('Lyon')
            ->and($node['address'])
            ->toBe([
                '@type' => 'PostalAddress',
                'streetAddress' => '1 rue de la République',
                'postalCode' => '69001',
                'addressLocality' => 'Lyon',
                'addressCountry' => 'France',
            ])
            ->and($node['geo'])
            ->toBe(['@type' => 'GeoCoordinates', 'latitude' => 45.764, 'longitude' => 4.8357])
            ->and($node['telephone'])
            ->toBe('+33 4 00 00 00 00')
            ->and($node['email'])
            ->toBe('lyon@example.com')
            ->and($node['sameAs'])
            ->toBe('https://example.com/lyon');
    });

    it('has no contact properties when contact fields are off, and no geo without coordinates', function () use (
        $values,
    ) {
        bootPlaces($config = new PlacesConfig(contactFields: false));
        $post = new WP_Post();
        $post->ID = 1;

        $node = new PlaceJsonLd($config)->node(TestPlace::with(1, [...$values, 'latitude' => '']), $post);

        expect($node['@type'])->toBe('Place')->and($node)->not->toHaveKeys(['telephone', 'email', 'sameAs', 'geo']);
    });
});
