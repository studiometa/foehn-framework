<?php

declare(strict_types=1);

namespace Tests\Fixtures\InspectApp;

use Studiometa\Foehn\Attributes\AsPostMeta;
use Studiometa\Foehn\Attributes\AsTaxonomy;
use Timber\Term;

#[AsTaxonomy(name: 'brand', postTypes: ['product'], hierarchical: true)]
#[AsPostMeta(key: 'logo', type: 'integer', objectType: 'term')]
final class Brand extends Term {}
