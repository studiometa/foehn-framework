<?php

declare(strict_types=1);

use Studiometa\Foehn\Acf\Fragments\BackgroundBuilder;

function findBgFieldByName(array $fields, string $name): ?array
{
    foreach ($fields['fields'] as $field) {
        if ($field['name'] === $name) {
            return $field;
        }
    }
    return null;
}

describe('BackgroundBuilder', function () {
    it('creates a builder with default values', function () {
        $builder = new BackgroundBuilder();

        expect($builder)->toBeInstanceOf(BackgroundBuilder::class);
        expect($builder->getName())->toBe('background');
    });

    it('creates a builder with custom name and label', function () {
        $builder = new BackgroundBuilder('bg', 'Background Settings');

        expect($builder->getName())->toBe('bg');
    });

    it('builds fields with type, color, image, and overlay', function () {
        $builder = new BackgroundBuilder();
        $fields = $builder->build();

        expect($fields)->toBeArray();
        expect($fields['fields'])->toBeArray();

        $fieldNames = array_column($fields['fields'], 'name');
        expect($fieldNames)->toContain('type');
        expect($fieldNames)->toContain('color');
        expect($fieldNames)->toContain('image');
        expect($fieldNames)->toContain('overlay');
        expect($fieldNames)->toContain('overlay_opacity');
    });

    it('uses default background types', function () {
        $builder = new BackgroundBuilder();
        $fields = $builder->build();

        $typeField = findBgFieldByName($fields, 'type');
        expect($typeField['choices'])->toBe([
            'none' => 'None',
            'color' => 'Color',
            'image' => 'Image',
        ]);
    });

    it('accepts custom types', function () {
        $builder = new BackgroundBuilder(
            types: ['none' => 'None', 'image' => 'Image'],
        );
        $fields = $builder->build();

        $typeField = findBgFieldByName($fields, 'type');
        expect($typeField['choices'])->toBe(['none' => 'None', 'image' => 'Image']);
    });

    it('uses custom default type', function () {
        $builder = new BackgroundBuilder(default: 'color');
        $fields = $builder->build();

        $typeField = findBgFieldByName($fields, 'type');
        expect($typeField['default_value'])->toBe('color');
    });

    it('uses custom default overlay opacity', function () {
        $builder = new BackgroundBuilder(defaultOpacity: 70);
        $fields = $builder->build();

        $opacityField = findBgFieldByName($fields, 'overlay_opacity');
        expect($opacityField['default_value'])->toBe(70);
    });

    it('has conditional logic for color field', function () {
        $builder = new BackgroundBuilder();
        $fields = $builder->build();

        $colorField = findBgFieldByName($fields, 'color');
        expect($colorField['conditional_logic'])->toBeArray();
        expect($colorField['conditional_logic'])->not->toBeEmpty();
    });

    it('has conditional logic for image field', function () {
        $builder = new BackgroundBuilder();
        $fields = $builder->build();

        $imageField = findBgFieldByName($fields, 'image');
        expect($imageField['conditional_logic'])->toBeArray();
        expect($imageField['conditional_logic'])->not->toBeEmpty();
    });

    it('has conditional logic for overlay fields', function () {
        $builder = new BackgroundBuilder();
        $fields = $builder->build();

        $overlayField = findBgFieldByName($fields, 'overlay');
        $opacityField = findBgFieldByName($fields, 'overlay_opacity');

        expect($overlayField['conditional_logic'])->toBeArray();
        expect($opacityField['conditional_logic'])->toBeArray();
    });
});
