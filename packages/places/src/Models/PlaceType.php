<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Places\Models;

use Studiometa\Foehn\Attributes\AsTaxonomy;
use Studiometa\Foehn\Contracts\ConfiguresTaxonomy;
use Studiometa\Foehn\Places\Config\PlacesConfig;
use Studiometa\Foehn\PostTypes\TaxonomyBuilder;
use Timber\Term;

use function Tempest\Container\get;

/**
 * The type of a place: hierarchical, so the editor shows a checkbox list, and public,
 * with the `place_type` query var that the default facet uses.
 */
#[AsTaxonomy(
    name: 'place_type',
    postTypes: ['place'],
    singular: 'Place type',
    plural: 'Place types',
    hierarchical: true,
)]
class PlaceType extends Term implements ConfiguresTaxonomy
{
    public const string TAXONOMY = 'place_type';

    public static function configureTaxonomy(TaxonomyBuilder $builder): TaxonomyBuilder
    {
        $config = get(PlacesConfig::class);

        return $builder->setLabels($config->typeSingular, $config->typePlural);
    }
}
