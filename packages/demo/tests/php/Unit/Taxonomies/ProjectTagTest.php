<?php

declare(strict_types=1);

use Demo\Taxonomies\ProjectTag;
use Studiometa\Foehn\Attributes\AsTaxonomy;
use Timber\Term;

describe('ProjectTag', function () {
    it('extends Timber Term', function () {
        expect(is_subclass_of(ProjectTag::class, Term::class))->toBeTrue();
    });

    it('has AsTaxonomy attribute with correct config', function () {
        $ref = new ReflectionClass(ProjectTag::class);
        $attrs = $ref->getAttributes(AsTaxonomy::class);

        expect($attrs)->toHaveCount(1);

        $attr = $attrs[0]->newInstance();

        expect($attr->name)->toBe('project_tag');
        expect($attr->singular)->toBe('Tag');
        expect($attr->plural)->toBe('Tags');
        expect($attr->rewriteSlug)->toBe('project-tags');
        expect($attr->postTypes)->toBe(['project']);
        expect($attr->hierarchical)->toBeFalse();
        expect($attr->showInRest)->toBeTrue();
    });
});
