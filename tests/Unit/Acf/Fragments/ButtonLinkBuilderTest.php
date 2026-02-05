<?php

declare(strict_types=1);

use Studiometa\Foehn\Acf\Fragments\ButtonLinkBuilder;

function findFieldByName(array $fields, string $name): ?array
{
    foreach ($fields['fields'] as $field) {
        if ($field['name'] === $name) {
            return $field;
        }
    }
    return null;
}

describe('ButtonLinkBuilder', function () {
    it('creates a builder with default values', function () {
        $builder = new ButtonLinkBuilder();

        expect($builder)->toBeInstanceOf(ButtonLinkBuilder::class);
        expect($builder->getName())->toBe('button');
    });

    it('creates a builder with custom name and label', function () {
        $builder = new ButtonLinkBuilder('cta', 'Call to Action');

        expect($builder->getName())->toBe('cta');
    });

    it('builds fields with link, style, and size', function () {
        $builder = new ButtonLinkBuilder();
        $fields = $builder->build();

        expect($fields)->toBeArray();
        expect($fields['fields'])->toBeArray();

        $fieldNames = array_column($fields['fields'], 'name');
        expect($fieldNames)->toContain('link');
        expect($fieldNames)->toContain('style');
        expect($fieldNames)->toContain('size');
    });

    it('can disable size field with null', function () {
        $builder = new ButtonLinkBuilder(sizes: null);
        $fields = $builder->build();

        $fieldNames = array_column($fields['fields'], 'name');
        expect($fieldNames)->toContain('link');
        expect($fieldNames)->toContain('style');
        expect($fieldNames)->not->toContain('size');
    });

    it('accepts custom styles', function () {
        $builder = new ButtonLinkBuilder(
            styles: ['btn-primary' => 'Primary', 'btn-ghost' => 'Ghost'],
        );
        $fields = $builder->build();

        $styleField = findFieldByName($fields, 'style');
        expect($styleField['choices'])->toBe(['btn-primary' => 'Primary', 'btn-ghost' => 'Ghost']);
    });

    it('accepts custom sizes', function () {
        $builder = new ButtonLinkBuilder(
            sizes: ['sm' => 'Small', 'lg' => 'Large'],
        );
        $fields = $builder->build();

        $sizeField = findFieldByName($fields, 'size');
        expect($sizeField['choices'])->toBe(['sm' => 'Small', 'lg' => 'Large']);
    });

    it('can make link required', function () {
        $builder = new ButtonLinkBuilder(required: true);
        $fields = $builder->build();

        $linkField = findFieldByName($fields, 'link');
        expect($linkField['required'])->toBeTrue();
    });
});
