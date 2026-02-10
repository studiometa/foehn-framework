<?php

declare(strict_types=1);

namespace App\Taxonomies;

use Studiometa\Foehn\Attributes\AsTaxonomy;
use Studiometa\Foehn\Contracts\ConfiguresTaxonomy;
use Studiometa\Foehn\PostTypes\TaxonomyBuilder;
use Timber\Term;

#[AsTaxonomy(
    name: 'product_category',
    singular: 'Catégorie',
    plural: 'Catégories',
    postTypes: ['product'],
    hierarchical: true,
    showInRest: true,
    showAdminColumn: true,
)]
final class ProductCategory extends Term implements ConfiguresTaxonomy
{
    public static function configureTaxonomy(TaxonomyBuilder $builder): TaxonomyBuilder
    {
        return $builder
            ->setRewrite(['slug' => 'boutique/categorie', 'with_front' => false]);
    }
}
