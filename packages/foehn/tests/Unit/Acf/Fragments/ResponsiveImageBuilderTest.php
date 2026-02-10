<?php

declare(strict_types=1);

use Studiometa\Foehn\Acf\Fragments\ResponsiveImageBuilder;

function findImageFieldByName(array $fields, string $name): ?array
{
    foreach ($fields['fields'] as $field) {
        if ($field['name'] === $name) {
            return $field;
        }
    }
    return null;
}

describe('ResponsiveImageBuilder', function () {
    it('creates a builder with default values', function () {
        $builder = new ResponsiveImageBuilder();

        expect($builder)->toBeInstanceOf(ResponsiveImageBuilder::class);
        expect($builder->getName())->toBe('image');
    });

    it('creates a builder with custom name and label', function () {
        $builder = new ResponsiveImageBuilder('hero_bg', 'Hero Background');

        expect($builder->getName())->toBe('hero_bg');
    });

    it('builds fields with desktop and mobile images', function () {
        $builder = new ResponsiveImageBuilder();
        $fields = $builder->build();

        expect($fields)->toBeArray();
        expect($fields['fields'])->toBeArray();

        $fieldNames = array_column($fields['fields'], 'name');
        expect($fieldNames)->toContain('desktop');
        expect($fieldNames)->toContain('mobile');
    });

    it('can make desktop image required', function () {
        $builder = new ResponsiveImageBuilder(required: true);
        $fields = $builder->build();

        $desktopField = findImageFieldByName($fields, 'desktop');
        $mobileField = findImageFieldByName($fields, 'mobile');

        expect($desktopField['required'])->toBeTrue();
        expect($mobileField['required'] ?? false)->toBeFalse();
    });

    it('accepts custom instructions', function () {
        $builder = new ResponsiveImageBuilder(
            desktopInstructions: 'Custom desktop instructions',
            mobileInstructions: 'Custom mobile instructions',
        );
        $fields = $builder->build();

        $desktopField = findImageFieldByName($fields, 'desktop');
        $mobileField = findImageFieldByName($fields, 'mobile');

        expect($desktopField['instructions'])->toBe('Custom desktop instructions');
        expect($mobileField['instructions'])->toBe('Custom mobile instructions');
    });

    it('uses id return format', function () {
        $builder = new ResponsiveImageBuilder();
        $fields = $builder->build();

        $desktopField = findImageFieldByName($fields, 'desktop');
        $mobileField = findImageFieldByName($fields, 'mobile');

        expect($desktopField['return_format'])->toBe('id');
        expect($mobileField['return_format'])->toBe('id');
    });
});
