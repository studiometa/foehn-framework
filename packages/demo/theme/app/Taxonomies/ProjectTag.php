<?php

declare(strict_types=1);

namespace Demo\Taxonomies;

use Studiometa\Foehn\Attributes\AsTaxonomy;
use Timber\Term;

#[AsTaxonomy(
    name: 'project_tag',
    singular: 'Étiquette',
    plural: 'Étiquettes',
    postTypes: ['project'],
    hierarchical: false,
    showInRest: true,
)]
final class ProjectTag extends Term {}
