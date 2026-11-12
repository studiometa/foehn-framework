<?php

declare(strict_types=1);

namespace Demo\Taxonomies;

use Studiometa\Foehn\Attributes\AsTaxonomy;
use Timber\Term;

/**
 * Free tags on a project, at /project-tags/<slug>/.
 */
#[AsTaxonomy(
    name: 'project_tag',
    singular: 'Tag',
    plural: 'Tags',
    postTypes: ['project'],
    hierarchical: false,
    showInRest: true,
    rewriteSlug: 'project-tags',
)]
final class ProjectTag extends Term {}
