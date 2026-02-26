<?php

declare(strict_types=1);

use App\Taxonomies\ProductTag;
use Studiometa\Foehn\Attributes\AsTaxonomy;
use Timber\Term;

describe('ProductTag', function () {
    it('extends Timber Term', function () {
        expect(is_subclass_of(ProductTag::class, Term::class))->toBeTrue();
    });

    it('has AsTaxonomy attribute with correct config', function () {
        $ref = new ReflectionClass(ProductTag::class);
        $attrs = $ref->getAttributes(AsTaxonomy::class);

        expect($attrs)->toHaveCount(1);

        $attr = $attrs[0]->newInstance();

        expect($attr->name)->toBe('product_tag');
        expect($attr->singular)->toBe('Étiquette');
        expect($attr->plural)->toBe('Étiquettes');
        expect($attr->postTypes)->toBe(['product']);
        expect($attr->hierarchical)->toBeFalse();
        expect($attr->showInRest)->toBeTrue();
    });
});
