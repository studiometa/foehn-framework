<?php

declare(strict_types=1);

use Studiometa\Foehn\Places\Config\PlacesConfig;
use Studiometa\Foehn\Places\Search\InvalidPlaceSearch;
use Studiometa\Foehn\Places\Search\PlaceSearchRequest;

function placeRequest(string $query, ?PlacesConfig $config = null, ?callable $termsExist = null): PlaceSearchRequest
{
    return PlaceSearchRequest::fromQueryString(
        $query,
        $config ?? new PlacesConfig(),
        ['place_type', 'place_service'],
        $termsExist ?? static fn(string $taxonomy, array $slugs): bool => true,
    );
}

describe('PlaceSearchRequest', function () {
    it('has defaults for an empty request', function () {
        $request = placeRequest('');

        expect($request->q)
            ->toBeNull()
            ->and($request->view)
            ->toBe('list')
            ->and($request->near)
            ->toBeNull()
            ->and($request->radius)
            ->toBeNull()
            ->and($request->bbox)
            ->toBeNull()
            ->and($request->terms)
            ->toBe([])
            ->and($request->isPositioned())
            ->toBeFalse();
    });

    it('reads every empty value as absent', function () {
        $request = placeRequest('q=&view=&near=&radius=&bbox=&place_type=');

        expect($request->toQueryArgs())->toBe([]);
    });

    it('keeps empty position values absent when their feature is off', function () {
        $config = new PlacesConfig(nearSearch: false, areaSearch: false);

        expect(placeRequest('near=&radius=&bbox=', $config)->isPositioned())->toBeFalse();
    });

    it('reads the text and the view', function () {
        $request = placeRequest('q=Lyon+centre&view=map');

        expect($request->q)->toBe('Lyon centre')->and($request->view)->toBe('map');
    });

    it('applies the default radius to near alone, and rounds near to five decimals', function () {
        $request = placeRequest('near=45.7640012,4.8356999');

        expect((string) $request->near)->toBe('45.764,4.8357')->and($request->radius)->toBe(25);
    });

    it('keeps a radius of the options with near', function () {
        expect(placeRequest('near=45.764,4.8357&radius=50')->radius)->toBe(50);
    });

    it('drops a radius without near, so the search is not positioned', function () {
        $request = placeRequest('radius=50&q=lyon');

        expect($request->radius)
            ->toBeNull()
            ->and($request->isPositioned())
            ->toBeFalse()
            ->and($request->toQueryArgs())
            ->toBe(['q' => 'lyon']);
    });

    it('normalizes a bounding box', function (string $bbox, string $expected) {
        expect((string) placeRequest('bbox=' . $bbox)->bbox)->toBe($expected);
    })->with([
        'plain' => ['4.7,45.6,5,45.9', '4.7,45.6,5,45.9'],
        'rounded' => ['4.123456,45.1,5.1,45.9', '4.12346,45.1,5.1,45.9'],
        'raw longitudes past 180' => ['170,-10,190,10', '170,-10,-170,10'],
        'raw longitudes below -180' => ['-200,-10,-170,10', '160,-10,-170,10'],
        'east on 180' => ['170,-10,180,10', '170,-10,180,10'],
        'a span of 360 or more' => ['-300,-10,100,10', '-180,-10,180,10'],
    ]);

    it('reads term slugs as a list or comma separated', function () {
        $request = placeRequest('place_type[]=agency&place_type[]=showroom&place_service=repair,sale');

        expect($request->terms)->toBe([
            'place_type' => ['agency', 'showroom'],
            'place_service' => ['repair', 'sale'],
        ]);
    });

    it('ignores parameters it does not own', function () {
        expect(placeRequest('foehn_sections=place-results&utm_source=x')->toQueryArgs())->toBe([]);
    });

    it('answers 400 for a broken rule', function (string $query, ?PlacesConfig $config = null) {
        expect(fn() => placeRequest($query, $config))->toThrow(InvalidPlaceSearch::class);
    })->with([
        'near when the search around an address is off' => ['near=45,4', new PlacesConfig(nearSearch: false)],
        'radius when the search around an address is off' => ['radius=25', new PlacesConfig(nearSearch: false)],
        'bbox when the area search is off' => ['bbox=4,45,5,46', new PlacesConfig(areaSearch: false)],
        'near and bbox together' => ['near=45,4&bbox=4,45,5,46'],
        'a repeated q' => ['q=a&q=b'],
        'q as a list' => ['q[]=a'],
        'a repeated view' => ['view=list&view=map'],
        'a repeated near' => ['near=45,4&near=46,4'],
        'a repeated radius' => ['near=45,4&radius=10&radius=25'],
        'a repeated bbox' => ['bbox=4,45,5,46&bbox=4,45,5,46'],
        'an unknown view' => ['view=grid'],
        'a radius outside the options' => ['near=45,4&radius=30'],
        'a radius that is not a number' => ['near=45,4&radius=ten'],
        'a radius outside the options without near' => ['radius=30'],
        'a radius that is not a number without near' => ['radius=ten'],
        'a malformed near' => ['near=45'],
        'a near that is not numbers' => ['near=north,east'],
        'a near number too long' => ['near=45.' . str_repeat('1', 40) . ',4'],
        'a near in hexadecimal' => ['near=0x2D,4'],
        'a latitude out of range' => ['near=91,4'],
        'a longitude out of range' => ['near=45,181'],
        'a malformed bbox' => ['bbox=4,45,5'],
        'a bbox south above north' => ['bbox=4,46,5,45'],
        'a bbox latitude out of range' => ['bbox=4,-91,5,45'],
        'a text too long' => ['q=' . str_repeat('a', 101)],
        'a malformed slug' => ['place_type=<script>'],
        'too many terms' => ['place_type=' . implode(',', range(1, 21))],
    ]);

    it('answers 400, not 404, for an unknown term', function () {
        $exists = static fn(string $taxonomy, array $slugs): bool => $slugs === ['agency'];

        expect(placeRequest('place_type=agency', termsExist: $exists)->terms)->toBe(['place_type' => ['agency']]);
        expect(fn() => placeRequest('place_type=unknown', termsExist: $exists))->toThrow(InvalidPlaceSearch::class);
    });

    it('keeps its state in query arguments', function () {
        expect(placeRequest('q=lyon&view=map&near=45.764,4.8357&place_type[]=agency')->toQueryArgs())->toBe([
            'q' => 'lyon',
            'view' => 'map',
            'near' => '45.764,4.8357',
            'radius' => '25',
            'place_type' => 'agency',
        ]);
    });

    it('reads its own query arguments back through http_build_query()', function () {
        $request = placeRequest('q=lyon&near=0.00001,-0.000004&place_type=agency,shop&place_service=parking');
        $again = placeRequest(http_build_query($request->toQueryArgs()));

        expect($request->toQueryArgs())
            ->toBe([
                'q' => 'lyon',
                'near' => '0.00001,0',
                'radius' => '25',
                'place_type' => 'agency,shop',
                'place_service' => 'parking',
            ])
            ->and($again)
            ->toEqual($request);
    });

    it('writes a bounding box without an exponent', function () {
        $request = placeRequest('bbox=0.00001,-0.00002,0.5,0.00003');

        expect((string) $request->bbox)->toBe('0.00001,-0.00002,0.5,0.00003');
        expect(placeRequest(http_build_query($request->toQueryArgs())))->toEqual($request);
    });

    it('reads the full precision doubles of a map', function () {
        $near = placeRequest('near=0.30000000000000004,2.3522219999999876');
        $bbox = placeRequest('bbox=-4.795789999999987,1e-7,8.123456789012345,0.30000000000000004');

        expect((string) $near->near)->toBe('0.3,2.35222');
        expect((string) $bbox->bbox)->toBe('-4.79579,0,8.12346,0.3');
        expect((string) placeRequest(
            'bbox=-4.795789999999987,0.30000000000000004,8.123456789012345,51.08912345678901',
        )->bbox)
            ->toBe('-4.79579,0.3,8.12346,51.08912');
    });
});
