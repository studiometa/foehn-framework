<?php

declare(strict_types=1);

use Demo\Taxonomies\ProjectCategory;
use Studiometa\Foehn\Attributes\AsTaxonomy;
use Studiometa\Foehn\Contracts\ConfiguresTaxonomy;
use Studiometa\Foehn\PostTypes\TaxonomyBuilder;
use Timber\Term;

describe('ProjectCategory', function () {
    it('extends Timber Term', function () {
        expect(is_subclass_of(ProjectCategory::class, Term::class))->toBeTrue();
    });

    it('implements ConfiguresTaxonomy', function () {
        expect(is_subclass_of(ProjectCategory::class, ConfiguresTaxonomy::class))->toBeTrue();
    });

    it('has AsTaxonomy attribute with correct config', function () {
        $ref = new ReflectionClass(ProjectCategory::class);
        $attrs = $ref->getAttributes(AsTaxonomy::class);

        expect($attrs)->toHaveCount(1);

        $attr = $attrs[0]->newInstance();

        expect($attr->name)->toBe('project_category');
        expect($attr->singular)->toBe('Series');
        expect($attr->plural)->toBe('Series');
        expect($attr->postTypes)->toBe(['project']);
        expect($attr->hierarchical)->toBeTrue();
        expect($attr->showInRest)->toBeTrue();
        expect($attr->showAdminColumn)->toBeTrue();
    });

    it('configures rewrite slug', function () {
        $builder = new TaxonomyBuilder('project_category', 'Catégorie', 'Catégories', ['project']);
        $result = ProjectCategory::configureTaxonomy($builder);

        $args = $result->build();

        expect($args['rewrite']['slug'])->toBe('projects/series');
        expect($args['rewrite']['with_front'])->toBeFalse();
    });
});
