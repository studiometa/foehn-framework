<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\WPTempest\Attributes\AsTaxonomy;
use Timber\Term;

#[AsTaxonomy(
    name: 'project_category',
    postTypes: ['project'],
    singular: 'Category',
    plural: 'Categories',
    hierarchical: true,
)]
final class TaxonomyFixture extends Term {}
