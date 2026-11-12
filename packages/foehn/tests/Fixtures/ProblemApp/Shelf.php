<?php

declare(strict_types=1);

namespace Tests\Fixtures\ProblemApp;

use Studiometa\Foehn\Attributes\AsTaxonomy;
use Timber\Term;

/**
 * A taxonomy for `article`, which the app declares, and `book`, which nothing
 * registers.
 */
#[AsTaxonomy(name: 'shelf', postTypes: ['article', 'book'])]
final class Shelf extends Term {}
