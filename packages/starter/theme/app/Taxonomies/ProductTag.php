<?php

declare(strict_types=1);

namespace App\Taxonomies;

use Studiometa\Foehn\Attributes\AsTaxonomy;

#[AsTaxonomy(
    name: 'product_tag',
    singular: 'Étiquette',
    plural: 'Étiquettes',
    postTypes: ['product'],
    hierarchical: false,
    showInRest: true,
)]
final class ProductTag
{
    // No additional configuration needed
}
