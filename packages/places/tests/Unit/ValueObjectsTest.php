<?php

declare(strict_types=1);

use Studiometa\Foehn\Places\ValueObjects\BoundingBox;
use Studiometa\Foehn\Places\ValueObjects\PlaceCoordinates;

describe('PlaceCoordinates', function () {
    it('accepts zero, which is a valid value', function () {
        expect(PlaceCoordinates::from(0, 0)?->toLngLat())->toBe([0.0, 0.0]);
        expect(PlaceCoordinates::from('0', '0.0'))->not->toBeNull();
    });

    it('has no value for a missing half, a non-number or a value out of range', function (mixed $lat, mixed $lng) {
        expect(PlaceCoordinates::from($lat, $lng))->toBeNull();
    })->with([
        'missing latitude' => ['', 4.8],
        'missing longitude' => [45.7, null],
        'not a number' => ['north', 4.8],
        'latitude above 90' => [90.1, 4.8],
        'latitude below -90' => [-90.1, 4.8],
        'longitude above 180' => [45.7, 180.1],
        'longitude below -180' => [45.7, -180.1],
    ]);

    it('gives Mapbox the longitude first', function () {
        expect(PlaceCoordinates::from(45.764, 4.8357)?->toLngLat())->toBe([4.8357, 45.764]);
    });

    it('measures known distances', function () {
        $paris = PlaceCoordinates::from(48.8566, 2.3522);
        $lyon = PlaceCoordinates::from(45.764, 4.8357);

        expect($paris?->distanceTo($lyon))->toBeGreaterThan(391_000)->toBeLessThan(393_000);
        expect($paris?->distanceTo($paris))->toBe(0.0);
    });
});

describe('BoundingBox', function () {
    it('contains points inside, on the edges, and not outside', function () {
        $box = BoundingBox::fromBounds(4.7, 45.6, 5.0, 45.9);

        expect($box?->contains(PlaceCoordinates::from(45.75, 4.85)))->toBeTrue();
        expect($box?->contains(PlaceCoordinates::from(45.6, 4.7)))->toBeTrue();
        expect($box?->contains(PlaceCoordinates::from(45.95, 4.85)))->toBeFalse();
        expect($box?->contains(PlaceCoordinates::from(45.75, 5.1)))->toBeFalse();
    });

    it('crosses the antimeridian', function () {
        $box = BoundingBox::fromBounds(170, -20, 190, 20);

        expect($box?->contains(PlaceCoordinates::from(0, 175)))->toBeTrue();
        expect($box?->contains(PlaceCoordinates::from(0, -175)))->toBeTrue();
        expect($box?->contains(PlaceCoordinates::from(0, 0)))->toBeFalse();
    });

    it('drops the longitude constraint for a span of 360 or more', function () {
        $box = BoundingBox::fromBounds(-400, -20, 100, 20);

        expect($box?->contains(PlaceCoordinates::from(0, 179)))->toBeTrue();
        expect($box?->contains(PlaceCoordinates::from(0, -179)))->toBeTrue();
    });
});
