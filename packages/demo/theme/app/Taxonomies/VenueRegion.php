<?php

declare(strict_types=1);

namespace Demo\Taxonomies;

use Studiometa\Foehn\Attributes\AsTaxonomy;
use Timber\Term;

/**
 * The region of a venue: a project facet of the place locator, next to `place_type`.
 *
 * Public, so WordPress reads its `venue_region` query var, which is the facet's
 * request parameter. Listed in `PlacesConfig::$facetTaxonomies`.
 */
#[AsTaxonomy(
    name: 'venue_region',
    singular: 'Region',
    plural: 'Regions',
    postTypes: ['place'],
    hierarchical: true,
    showInRest: true,
    showAdminColumn: true,
)]
final class VenueRegion extends Term {}
