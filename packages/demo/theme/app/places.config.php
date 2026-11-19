<?php

declare(strict_types=1);

use Studiometa\Foehn\Places\Config\PlacesConfig;

/**
 * The place locator of the demo: the galleries and bookshops that show the work.
 *
 * The token comes from the environment, so the settings page shows it read-only. Without
 * one the locator still works: the list, the text search and the facets need no
 * Mapbox, and the map page says the map is not available.
 *
 * `venue_region` is a project facet taxonomy, attached to `place` in
 * `app/Taxonomies/VenueRegion.php`, next to the package's own `place_type`.
 */
return new PlacesConfig(
    archiveSlug: 'venues',
    singular: 'Venue',
    plural: 'Venues',
    typeSingular: 'Venue type',
    typePlural: 'Venue types',
    schemaType: 'ArtGallery',
    // `.env` fills $_ENV; a container variable, such as a Fly secret, is in getenv().
    accessToken: $_ENV['MAPBOX_ACCESS_TOKEN'] ?? getenv('MAPBOX_ACCESS_TOKEN') ?: null,
    center: [2.35, 46.6],
    zoom: 4.5,
    facetTaxonomies: ['place_type', 'venue_region'],
    listPageSize: 6,
);
