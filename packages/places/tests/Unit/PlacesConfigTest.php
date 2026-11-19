<?php

declare(strict_types=1);

use Studiometa\Foehn\Places\Config\PlacesConfig;

describe('PlacesConfig', function () {
    it('refuses a default radius that is not one of the radius options', function () {
        expect(fn() => new PlacesConfig(radiusOptions: [10, 50], defaultRadius: 25))
            ->toThrow(
                InvalidArgumentException::class,
                'PlacesConfig::$defaultRadius (25) must be one of $radiusOptions (10, 50).',
            );
    });

    it('accepts any default radius when the search around an address is off', function () {
        expect(new PlacesConfig(nearSearch: false, radiusOptions: [10], defaultRadius: 25)->defaultRadius)->toBe(25);
    });

    it('refuses a page size or a map limit below 1', function (array $sizes) {
        expect(fn() => new PlacesConfig(...$sizes))->toThrow(InvalidArgumentException::class);
    })->with([
        'list page size' => [['listPageSize' => 0]],
        'map result limit' => [['mapResultLimit' => 0]],
    ]);
});
