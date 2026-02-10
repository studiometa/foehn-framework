<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsTaxonomy;

/**
 * Invalid: has #[AsTaxonomy] but does NOT extend Timber\Term.
 */
#[AsTaxonomy(name: 'invalid', postTypes: ['post'])]
final class InvalidTaxonomyFixture {}
