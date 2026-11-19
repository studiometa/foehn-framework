<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Places\Config;

use InvalidArgumentException;

/**
 * Configuration of the place locator.
 *
 * The package ships the defaults in `places.config.php`. A project overrides them with
 * its own `app/places.config.php`:
 *
 * ```php
 * use Studiometa\Foehn\Places\Config\PlacesConfig;
 *
 * return new PlacesConfig(
 *     archiveSlug: 'stores',
 *     singular: __('Store', 'theme'),
 *     plural: __('Stores', 'theme'),
 *     schemaType: 'Store',
 *     accessToken: getenv('MAPBOX_ACCESS_TOKEN') ?: null,
 * );
 * ```
 */
final readonly class PlacesConfig
{
    /** The style a map uses when neither the config nor the settings page sets one. */
    public const string DEFAULT_STYLE = 'mapbox://styles/mapbox/light-v11';

    /**
     * @param string $archiveSlug The slug of the archive and of the single places. A change needs a rewrite flush
     * @param string $singular The singular label of the post type
     * @param string $plural The plural label of the post type
     * @param string $typeSingular The singular label of the `place_type` taxonomy
     * @param string $typePlural The plural label of the `place_type` taxonomy
     * @param bool $contactFields Whether the phone, email and website fields exist
     * @param string $schemaType The schema.org type of the JSON-LD node of a place
     * @param string|null $accessToken The Mapbox access token. Wins over the settings page when not null
     * @param string|null $style The Mapbox style URL. Wins over the settings page when not null
     * @param array{0: float, 1: float} $center The center of an empty map, as [longitude, latitude]
     * @param float $zoom The zoom of an empty map
     * @param array<string, mixed> $geocoderOptions Options given to the Mapbox geocoder
     * @param list<string> $facetTaxonomies The taxonomies offered as facets, attached to `place`
     * @param int $listPageSize The number of places on a page of the list
     * @param int $mapResultLimit The most places a map shows
     * @param bool $nearSearch Whether a visitor can search around an address (`near` and `radius`)
     * @param bool $areaSearch Whether a visitor can search the visible map area (`bbox`)
     * @param list<int> $radiusOptions The radii offered around an address, in kilometres
     * @param int $defaultRadius The radius of a search around an address with no radius, one of $radiusOptions
     */
    public function __construct(
        public string $archiveSlug = 'place',
        public string $singular = 'Place',
        public string $plural = 'Places',
        public string $typeSingular = 'Place type',
        public string $typePlural = 'Place types',
        public bool $contactFields = true,
        public string $schemaType = 'Place',
        #[\SensitiveParameter]
        public ?string $accessToken = null,
        public ?string $style = null,
        public array $center = [2.5, 47.0],
        public float $zoom = 5,
        public array $geocoderOptions = [],
        public array $facetTaxonomies = ['place_type'],
        public int $listPageSize = 24,
        public int $mapResultLimit = 250,
        public bool $nearSearch = true,
        public bool $areaSearch = true,
        public array $radiusOptions = [10, 25, 50, 100],
        public int $defaultRadius = 25,
    ) {
        if ($nearSearch && !in_array($defaultRadius, $radiusOptions, true)) {
            throw new InvalidArgumentException(sprintf(
                'PlacesConfig::$defaultRadius (%d) must be one of $radiusOptions (%s).',
                $defaultRadius,
                implode(', ', $radiusOptions),
            ));
        }

        if ($listPageSize < 1 || $mapResultLimit < 1) {
            throw new InvalidArgumentException('PlacesConfig::$listPageSize and $mapResultLimit must be positive.');
        }
    }
}
