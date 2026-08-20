<?php

declare(strict_types=1);

namespace Demo\Taxonomies;

use Studiometa\Foehn\Attributes\AsTaxonomy;
use Studiometa\Foehn\Contracts\ConfiguresTaxonomy;
use Studiometa\Foehn\PostTypes\TaxonomyBuilder;
use Timber\Term;

#[AsTaxonomy(
    name: 'project_category',
    singular: 'Series',
    plural: 'Series',
    postTypes: ['project'],
    hierarchical: true,
    showInRest: true,
    showAdminColumn: true,
)]
final class ProjectCategory extends Term implements ConfiguresTaxonomy
{
    public static function configureTaxonomy(TaxonomyBuilder $builder): TaxonomyBuilder
    {
        return $builder->setRewrite(['slug' => 'projects/series', 'with_front' => false]);
    }
}
