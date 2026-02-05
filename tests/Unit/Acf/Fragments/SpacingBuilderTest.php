<?php

declare(strict_types=1);

use Studiometa\Foehn\Acf\Fragments\SpacingBuilder;

function findSpacingFieldByName(array $fields, string $name): ?array
{
    foreach ($fields['fields'] as $field) {
        if ($field['name'] === $name) {
            return $field;
        }
    }
    return null;
}

describe('SpacingBuilder', function () {
    it('creates a builder with default values', function () {
        $builder = new SpacingBuilder();

        expect($builder)->toBeInstanceOf(SpacingBuilder::class);
        expect($builder->getName())->toBe('spacing');
    });

    it('creates a builder with custom name and label', function () {
        $builder = new SpacingBuilder('margin', 'Margins');

        expect($builder->getName())->toBe('margin');
    });

    it('builds fields with top and bottom spacing', function () {
        $builder = new SpacingBuilder();
        $fields = $builder->build();

        expect($fields)->toBeArray();
        expect($fields['fields'])->toBeArray();

        $fieldNames = array_column($fields['fields'], 'name');
        expect($fieldNames)->toContain('top');
        expect($fieldNames)->toContain('bottom');
    });

    it('uses default spacing sizes', function () {
        $builder = new SpacingBuilder();
        $fields = $builder->build();

        $topField = findSpacingFieldByName($fields, 'top');
        expect($topField['choices'])->toBe([
            'none' => 'None',
            'small' => 'Small',
            'medium' => 'Medium',
            'large' => 'Large',
            'xlarge' => 'Extra Large',
        ]);
    });

    it('accepts custom sizes', function () {
        $builder = new SpacingBuilder(
            sizes: ['xs' => 'Extra Small', 'sm' => 'Small', 'lg' => 'Large'],
        );
        $fields = $builder->build();

        $topField = findSpacingFieldByName($fields, 'top');
        expect($topField['choices'])->toBe(['xs' => 'Extra Small', 'sm' => 'Small', 'lg' => 'Large']);
    });

    it('uses custom default value', function () {
        $builder = new SpacingBuilder(default: 'large');
        $fields = $builder->build();

        $topField = findSpacingFieldByName($fields, 'top');
        $bottomField = findSpacingFieldByName($fields, 'bottom');

        expect($topField['default_value'])->toBe('large');
        expect($bottomField['default_value'])->toBe('large');
    });

    it('accepts custom labels', function () {
        $builder = new SpacingBuilder(
            topLabel: 'Margin Top',
            bottomLabel: 'Margin Bottom',
        );
        $fields = $builder->build();

        $topField = findSpacingFieldByName($fields, 'top');
        $bottomField = findSpacingFieldByName($fields, 'bottom');

        expect($topField['label'])->toBe('Margin Top');
        expect($bottomField['label'])->toBe('Margin Bottom');
    });
});
