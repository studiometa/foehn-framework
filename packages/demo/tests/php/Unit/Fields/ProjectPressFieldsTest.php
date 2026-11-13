<?php

declare(strict_types=1);

use Demo\Fields\ProjectPressFields;
use Studiometa\Foehn\Attributes\AsAcfFieldGroup;
use Studiometa\Foehn\Contracts\AcfFieldGroupInterface;

describe('ProjectPressFields', function () {
    it('is an ACF field group on projects', function () {
        $attr = new ReflectionClass(ProjectPressFields::class)->getAttributes(AsAcfFieldGroup::class)[0]->newInstance();

        expect(is_subclass_of(ProjectPressFields::class, AcfFieldGroupInterface::class))->toBeTrue();
        expect($attr->location)->toBe(['post_type' => 'project']);
    });

    it('wraps the ButtonLinkBuilder fragment in a group of its own', function () {
        $fields = ProjectPressFields::fields()->build()['fields'];

        expect(array_column($fields, 'name'))->toBe(['press_quote', 'press_source', 'press_link']);
        expect(array_column($fields[2]['sub_fields'], 'name'))->toBe(['link', 'style']);
    });
});
