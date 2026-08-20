<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsTaxonomy;
use Studiometa\Foehn\Contracts\ConfiguresTaxonomy;
use Studiometa\Foehn\PostTypes\TaxonomyBuilder;
use Timber\Term;

/**
 * A taxonomy that customises its own registration. Timber\Term also declares a
 * protected constructor, so this is the taxonomy half of the same trap.
 */
#[AsTaxonomy(name: 'configurable_tax', singular: 'Configurable', plural: 'Configurables', postTypes: ['post'])]
final class ConfigurableTaxonomyFixture extends Term implements ConfiguresTaxonomy
{
    public static function configureTaxonomy(TaxonomyBuilder $builder): TaxonomyBuilder
    {
        return $builder->setRewrite(['slug' => 'configuré-tax', 'with_front' => false]);
    }
}
