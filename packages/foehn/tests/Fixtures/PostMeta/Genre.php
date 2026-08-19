<?php

declare(strict_types=1);

namespace Tests\Fixtures\PostMeta;

use Studiometa\Foehn\Attributes\AsPostMeta;
use Studiometa\Foehn\Attributes\AsTaxonomy;
use Timber\Term;

#[AsTaxonomy(name: 'genre', singular: 'Genre', plural: 'Genres')]
#[AsPostMeta(key: 'colour', objectType: 'term')]
final class Genre extends Term {}
