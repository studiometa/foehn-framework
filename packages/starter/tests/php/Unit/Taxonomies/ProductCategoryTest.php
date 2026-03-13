<?php

declare(strict_types=1);

use App\Taxonomies\ProductCategory;
use Studiometa\Foehn\Attributes\AsTaxonomy;
use Studiometa\Foehn\Contracts\ConfiguresTaxonomy;
use Studiometa\Foehn\PostTypes\TaxonomyBuilder;
use Timber\Term;

describe('ProductCategory', function () {
    it('extends Timber Term', function () {
        expect(is_subclass_of(ProductCategory::class, Term::class))->toBeTrue();
    });

    it('implements ConfiguresTaxonomy', function () {
        expect(is_subclass_of(ProductCategory::class, ConfiguresTaxonomy::class))->toBeTrue();
    });

    it('has AsTaxonomy attribute with correct config', function () {
        $ref = new ReflectionClass(ProductCategory::class);
        $attrs = $ref->getAttributes(AsTaxonomy::class);

        expect($attrs)->toHaveCount(1);

        $attr = $attrs[0]->newInstance();

        expect($attr->name)->toBe('product_category');
        expect($attr->singular)->toBe('Catégorie');
        expect($attr->plural)->toBe('Catégories');
        expect($attr->postTypes)->toBe(['product']);
        expect($attr->hierarchical)->toBeTrue();
        expect($attr->showInRest)->toBeTrue();
        expect($attr->showAdminColumn)->toBeTrue();
    });

    it('configures rewrite slug', function () {
        $builder = new TaxonomyBuilder('product_category', 'Catégorie', 'Catégories', ['product']);
        $result = ProductCategory::configureTaxonomy($builder);

        $args = $result->build();

        expect($args['rewrite']['slug'])->toBe('boutique/categorie');
        expect($args['rewrite']['with_front'])->toBeFalse();
    });
});
