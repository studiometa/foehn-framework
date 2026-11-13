<?php

declare(strict_types=1);

namespace Demo\Taxonomies;

use Studiometa\Foehn\Attributes\AsTaxonomy;
use Studiometa\Foehn\Contracts\ConfiguresTaxonomy;
use Studiometa\Foehn\PostTypes\TaxonomyBuilder;
use Timber\Term;

/**
 * The series a project belongs to, at /series/<slug>/.
 *
 * Not /projects/series/<slug>/: WordPress adds a rule for the attachments of a
 * project at projects/<name>/<attachment>/, which comes first and takes that URL.
 */
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
        return $builder->setRewrite(['slug' => 'series', 'with_front' => false]);
    }
}
